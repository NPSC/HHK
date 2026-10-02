<?php
namespace HHK\Admin\Import\Cloudbeds;

use HHK\Admin\Import\AbstractImport;
use HHK\Admin\Import\ImportInterface;
use HHK\House\Hospital\HospitalStay;
use HHK\Note\LinkNote;
use HHK\Note\Note;
use HHK\sec\Session;
use HHK\SysConst\VisitStatus;

/**
 * Imports people, reservations/visits and folios (as invoices and payments) from Cloudbeds.
 *
 * Data is fetched from Cloudbeds into a staging table first (see CloudbedsFetcher), then imported in batches by startImport():
 *
 *  - only guests with a stay (by default checked-out; see CloudbedsConfig::includeCurrentGuests()) in the configured timeframe are
 *    imported at all; see CloudbedsFetcher's guestList step, driven by Cloudbeds' getGuestList endpoint.
 *  - guest profiles become HHK people. The Cloudbeds guest profile id is stored in name.External_Id, which is how imported people are tracked.
 *    The notes on the guest in Cloudbeds become PSG notes (see addProfileNotesToPsg()), not member notes - a guest profile has no PSG of
 *    its own until a reservation gives it one, so these are only attached once importReservation() gets to that guest.
 *  - reservations become an HHK reservation, plus a visit and stays when the guest checked in/out. Cloudbeds has no patients or PSGs, so
 *    the patient, hospital, diagnosis, etc. come from custom fields, mapped in the config. Without a mapped patient the main guest is their own patient.
 *    Those custom fields are normally on the reservation, but can also be mapped from the main guest's profile-level custom fields
 *    (Cloudbeds has no separate concept for the patient), see the merge in importReservation().
 *  - folios become invoices on the visit, with their payments (see CloudbedsFolioImporter).
 *
 * The person/PSG/reservation/visit logic is shared with the CSV importer through AbstractImport.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class CloudbedsImport extends AbstractImport implements ImportInterface {

    /**
     * Which gen lookup table each guest/reservation custom field target feeds, for the "Gen Lookup Values" review on the
     * settings page (see CloudbedsStaging::summarizeGenLookupValues() and createMissingGenLookupValues()).
     */
    public const GEN_LOOKUP_TARGETS = [
        'guest.Gender' => 'Gender',
        'guest.Ethnicity' => 'Ethnicity',
        'guest.Banned' => 'No_Return',
        'guest.mediaSource' => 'Media_Source',
        'diagnosis' => 'Diagnosis',
        'relationship' => 'Patient_Rel_Type',
    ];

    protected CloudbedsConfig $config;
    protected CloudbedsStaging $staging;
    protected CloudbedsFieldMapper $mapper;
    protected CloudbedsFolioImporter $folioImporter;
    protected ?CloudbedsFetcher $fetcher = null;

    /** @var array<string, string> [Code => Description] of the site's enabled demographics, see CloudbedsConfigStore::loadEnabledDemographics() */
    protected array $enabledDemographics = [];

    public function __construct(\PDO $dbh, ?CloudbedsConfig $config = null) {
        parent::__construct($dbh);

        $this->config = $config ?? CloudbedsConfig::fromDatabase($dbh);
        $this->mapper = $this->config->getFieldMapper();
        $this->staging = new CloudbedsStaging($dbh);
        $this->staging->ensureTables();
        $this->folioImporter = new CloudbedsFolioImporter($dbh, $this->config);
        $this->enabledDemographics = (new CloudbedsConfigStore($dbh))->loadEnabledDemographics();

        $this->genLookupMapping = [
            "gender" => "Gender",
            "ethnicity" => "Ethnicity",
            "banned" => "No_Return",
            "mediaSource" => "Media_Source",
            "relationship" => "Patient_Rel_Type",
            "diagnosis" => "Diagnosis",
        ];
        // "map to any enabled demographic" targets (guest.demog.<Code> / patient.demog.<Code>, see CloudbedsFieldMapper::isValidTarget())
        // need their own gen lookup category pre-cached the same way the fixed ones above are, for findIdGenLookup() to resolve them
        foreach (array_keys($this->enabledDemographics) as $code) {
            $this->genLookupMapping['demog_' . $code] = $code;
        }
        $this->fieldMapping = [];

        $this->loadGenLookups();
        $this->getHospitals();
        $this->getRooms();
    }

    public function getConfig(): CloudbedsConfig {
        return $this->config;
    }

    public function getMapper(): CloudbedsFieldMapper {
        return $this->mapper;
    }

    /**
     * GEN_LOOKUP_TARGETS plus one 'guest.demog.<Code>' / 'patient.demog.<Code>' => <Code> entry per enabled demographic
     * (see CloudbedsConfigStore::loadEnabledDemographics()), so a site-configured "map to any enabled demographic" target
     * gets the same "Gen Lookup Values" review/create-missing support the fixed targets already have.
     *
     * @return array<string, string> [target => gen lookup table name]
     */
    public function getGenLookupTargets(): array {
        $targets = self::GEN_LOOKUP_TARGETS;

        foreach (array_keys($this->enabledDemographics) as $code) {
            $targets['guest.demog.' . $code] = $code;
            $targets['patient.demog.' . $code] = $code;
        }

        return $targets;
    }

    /**
     * Create any gen lookup values found in the fetched data (for one of getGenLookupTargets()' tables) that don't exist in
     * HHK yet. This is the same thing createMissing.genLookups does automatically per record during import, offered here so
     * the values can be reviewed and created ahead of time instead.
     *
     * @param string $genLookupTableName
     * @return array{success: string}|array{error: string}
     */
    public function createMissingGenLookupValues(string $genLookupTableName): array {
        $targets = array_filter($this->getGenLookupTargets(), fn($table) => $table === $genLookupTableName);
        if (count($targets) === 0) {
            return ['error' => "Unknown gen lookup table '$genLookupTableName'"];
        }

        $found = $this->staging->summarizeGenLookupValues($this->mapper, $targets);
        $insertCount = 0;

        try {
            $this->dbh->beginTransaction();

            foreach (array_keys($found[$genLookupTableName] ?? []) as $value) {
                if ($this->findIdGenLookup($genLookupTableName, $value) === '') {
                    $this->createGenLookup($genLookupTableName, $value);
                    $insertCount++;
                }
            }

            $this->dbh->commit();
        } catch (\Throwable $e) {
            if ($this->dbh->inTransaction()) {
                $this->dbh->rollBack();
            }
            return ['error' => $e->getMessage()];
        }

        return ['success' => "$insertCount $genLookupTableName value(s) created."];
    }

    /**
     * Create an HHK room for every Cloudbeds room name found in the fetched data that isn't already mapped to an existing
     * HHK room and doesn't match one by name (the same check resolveResource() makes during import). This is the same
     * thing createMissing.rooms does automatically per reservation during import, offered here - like "Create Missing
     * Rooms" in the CSV importer - so rooms can be reviewed and created ahead of time instead.
     *
     * @return array{success: string}|array{error: string}
     */
    public function createMissingRooms(): array {
        $names = $this->staging->summarizeValues()['rooms'] ?? [];
        $insertCount = 0;

        try {
            $this->dbh->beginTransaction();

            foreach ($names as $name) {
                $name = trim($name);
                if ($name === '' || $this->config->getMappedRoomId($name) > 0 || (int) $this->findIdResource($name) > 0) {
                    continue;
                }

                $this->createRoom($name);
                $insertCount++;
            }

            $this->dbh->commit();
        } catch (\Throwable $e) {
            if ($this->dbh->inTransaction()) {
                $this->dbh->rollBack();
            }
            return ['error' => $e->getMessage()];
        }

        return ['success' => "$insertCount room(s) created."];
    }

    public function getStaging(): CloudbedsStaging {
        return $this->staging;
    }

    /**
     * Fetch data from Cloudbeds into the staging table. Call repeatedly until it reports complete.
     *
     * @param int $seconds time budget for this call
     * @return array{complete: bool, step: string, message: string}
     */
    public function fetch(int $seconds = 20): array {
        $this->fetcher ??= new CloudbedsFetcher(new CloudbedsClient($this->config, $this->dbh), $this->staging, $this->config);
        return $this->fetcher->run($seconds);
    }

    /**
     * Import the next batch of staged records: people first, then reservations/visits, then folios.
     * A record that fails is marked as an error and the rest of the batch continues.
     *
     * @param int $limit
     * @return array{success: bool, batch: int, workerId: string, patients: int, guests: int, errors: int, progress: array}
     */
    public function startImport(int $limit = 100): array {
        ini_set('max_execution_time', '300');

        $workerId = bin2hex(random_bytes(16));
        $this->importedPatients = 0;
        $this->importedGuests = 0;
        $errors = 0;

        $rows = $this->staging->claimBatch($workerId, $limit);

        foreach ($rows as $row) {
            try {
                $this->dbh->beginTransaction();

                $result = match ($row['entityType']) {
                    CloudbedsStaging::PROFILE => $this->importProfile($row),
                    CloudbedsStaging::RESERVATION => $this->importReservation($row),
                    CloudbedsStaging::FOLIO => $this->importFolio($row),
                };

                if ($result['status'] === CloudbedsStaging::SKIPPED) {
                    $this->staging->markSkipped((int) $row['id'], $result['message']);
                } else {
                    $this->staging->markDone((int) $row['id'], $result['hhkId'], $result['data'], $result['message']);
                }

                $this->dbh->commit();

            } catch (\Throwable $e) {
                if ($this->dbh->inTransaction()) {
                    $this->dbh->rollBack();
                }

                $this->staging->markError((int) $row['id'], $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
                $errors++;
            }
        }

        return [
            'success' => true,
            'batch' => count($rows),
            'workerId' => $workerId,
            'patients' => $this->importedPatients,
            'guests' => $this->importedGuests,
            'errors' => $errors,
            'progress' => $this->staging->getProgress(),
        ];
    }

    /**
     * Undo the people part of an import: sets imported people's member status to 'tbd' and puts the staged records back to pending.
     * You must go into Misc->Delete Member Records to finish the undo process.
     *
     * HHK's delete member routine requires the person's visits, stays, invoices and payments to be deleted first, and there is
     * no existing tool that removes those, so once reservations or folios have been imported this refuses to run.
     * Restore a database backup to redo an import that got that far.
     *
     * @return array
     */
    public function undoImport(): array {
        $counts = $this->staging->counts();
        $imported = $counts[CloudbedsStaging::RESERVATION][CloudbedsStaging::DONE] + $counts[CloudbedsStaging::FOLIO][CloudbedsStaging::DONE];

        if ($imported > 0) {
            return ['error' => "Cannot undo: " . $counts[CloudbedsStaging::RESERVATION][CloudbedsStaging::DONE] . " reservations and " . $counts[CloudbedsStaging::FOLIO][CloudbedsStaging::DONE] . " folios have been imported. HHK can only delete people after their visits, stays, invoices and payments are deleted, and there is no tool for that. Restore a database backup to start over."];
        }

        try {
            $this->dbh->beginTransaction();

            $this->dbh->exec("update `name` n join `" . CloudbedsStaging::TBL_NAME . "` i on i.`entityType` = 'profile' and i.`status` = 'done' and i.`cloudbedsId` = n.`External_Id` set n.`Member_Status` = 'tbd'");
            $this->staging->resetProcessed();

            $this->dbh->commit();
        } catch (\Throwable $e) {
            if ($this->dbh->inTransaction()) {
                $this->dbh->rollBack();
            }
            return ['error' => $e->getMessage()];
        }

        return ['success' => "Imported people have been set for 'To Be Deleted', use the 'Delete Member Records' function to delete them."];
    }

    // ------------------------------------------------------------------------------------------------
    // Cloudbeds specific overrides of AbstractImport
    // ------------------------------------------------------------------------------------------------

    /**
     * Names from Cloudbeds are clean text, don't add slashes to them
     */
    protected function cleanName(string $name): string {
        return trim($name);
    }

    /**
     * Imported people are matched first on their Cloudbeds profile id. If that doesn't find anyone - no previous import
     * ever paired this Cloudbeds id with an HHK person, or there is no id at all (a patient named only in a custom
     * field, e.g. "Veteran Name if different than guest in room" - Cloudbeds never gives a person like that a profile
     * id at all) - fall back to the same name search the CSV importer uses (see AbstractImport::findPerson()), so a
     * person already in HHK from another source (e.g. entered at the front desk, or a prior visit as a relative's
     * named veteran) isn't duplicated.
     *
     * An exact name match must be unambiguous to be used on its own (see the $limit=false below - unlike the CSV
     * importer, a same-name collision here is resolved rather than picked arbitrarily). An ambiguous match is
     * narrowed two ways, in order:
     *  1. If exactly one candidate has a real Cloudbeds profile id (a bare number - see PMS_GUEST_ID_PREFIX for why
     *     that's how a real one is told apart from a companion's namespaced one), that one is authoritative - a
     *     veteran who is also, separately, someone's own guest (checked in under their own profile at some point)
     *     is a live-confirmed case: a relative's reservation naming them can be processed before the veteran's own
     *     reservation ever is, creating an orphan, name-only patient with no external id first; once the veteran's
     *     own, profile-backed record exists, it - not the orphan - is the one every later mention of them should
     *     resolve to.
     *  2. Otherwise, for a patient, by MRN (see findPatientByMrnAndLastName() - the "Last 4 of Veteran's Social"
     *     value some properties map to it is only 4 digits, so it's corroborated with the already-matched last name
     *     rather than trusted alone, to guard against two different veterans coincidentally sharing it). This alone
     *     can't break a tie between several orphans that are already duplicates of the very same veteran (they all
     *     share the same MRN, because they really are the same person) - narrowing #1 is what actually prevents
     *     that pile-up once the real, profile-backed record shows up.
     * Still ambiguous after both is treated as not found rather than risk merging into the wrong person.
     *
     * A lookup that still finds nobody (no ambiguity to narrow - genuinely nobody by that exact name) has one more
     * fallback before giving up, in either direction:
     *  - a "guest" lookup tries an orphan self patient of the same name (see findOrphanSelfPatient()) - closing the
     *    gap #1 above describes, for the case where the orphan already exists when the veteran's own first
     *    self-check-in runs (so narrowing #1 never gets a chance to run for it - the external id lookup above
     *    already "succeeds" against the guest record this same reservation just created for them).
     *  - a "patient" lookup tries any existing person with a real Cloudbeds profile id and this exact name, 'slf' or
     *    not (see findRealGuestByName()) - closing the reverse gap: importProfile() runs in its own phase, entirely
     *    before any reservation does, and creates every real guest as a bare guest with no patient role yet, so
     *    whichever reservation (the veteran's own, or a relative's naming them) happens to have the lower staging id
     *    is the one that gets to promote them to 'slf' first - if that's a relative's reservation, the plain
     *    'slf'-only search just above always finds nobody, no matter which of the two actually ran first.
     */
    protected function findExistingPersonId(array $r, string $memberType, string $first, string $last) {
        if (($r['externalId'] ?? '') !== '') {
            $id = $this->findPersonByExternalId($r['externalId']);
            if ($id > 0) {
                return $id;
            }
        }

        $id = $this->findPerson($first, $last, $memberType, false, $r['Phone'] ?? '', $r['Email'] ?? '');

        if (is_array($id)) {
            $withRealExternalId = array_values(array_filter($id, fn($row) => ctype_digit((string) ($row[3] ?? ''))));
            if (count($withRealExternalId) === 1) {
                return (int) $withRealExternalId[0][0];
            }

            $candidates = array_map(fn($row) => (int) $row[0], $id);

            if ($memberType === 'patient') {
                $mrn = trim((string) ($r['MRN'] ?? ''));
                if ($mrn !== '') {
                    $idHospital = (int) ($this->hospitals[trim(strtolower((string) ($r['Hospital'] ?? '')))] ?? 0);
                    $mrnMatch = $this->findPatientByMrnAndLastName($mrn, $last, $idHospital);
                    if ($mrnMatch > 0 && in_array($mrnMatch, $candidates, true)) {
                        return $mrnMatch;
                    }
                }
            }

            return 0;
        }

        if ((int) $id === 0) {
            if ($memberType === 'guest') {
                $orphanMatch = $this->findOrphanSelfPatient($first, $last, trim((string) ($r['MRN'] ?? '')));
                if ($orphanMatch > 0) {
                    return $orphanMatch;
                }
            } elseif ($memberType === 'patient') {
                // the reverse case: importProfile() runs in its own phase, entirely before any reservation does, and
                // creates every real guest (by their real Cloudbeds profile id) as a bare guest with no patient role
                // yet - so whichever reservation has the lower staging id "wins" the chance to promote them to their
                // own ('slf') patient first. If that happens to be a relative's reservation naming them (this call),
                // the plain 'slf'-only search just above always finds nobody, since they aren't 'slf' yet - whatever
                // the actual order between the veteran's own reservation and the relative's one turns out to be.
                // Finding them here by name (see findRealGuestByName()) and promoting them is what addPatient()'s
                // existing-person branch already does for anyone found - it establishes the self PSG relationship.
                $realGuestMatch = $this->findRealGuestByName($first, $last);
                if ($realGuestMatch > 0) {
                    return $realGuestMatch;
                }
            }
        }

        return (int) $id;
    }

    /**
     * An existing person with a real Cloudbeds profile id (a bare number - see PMS_GUEST_ID_PREFIX) matching this
     * exact name, regardless of their current relationship role - see findExistingPersonId()'s "patient" fallback.
     * No MRN corroboration is possible here (unlike findOrphanSelfPatient()/findPatientByMrnAndLastName()): a person
     * who hasn't yet been promoted to being their own patient has no hospital_stay row, so no MRN, to check against.
     *
     * @return int idName, 0 if there is no single match
     */
    protected function findRealGuestByName(string $first, string $last): int {
        $newFirst = trim(htmlentities($first));
        $newLast = trim(htmlentities($last));
        if ($newFirst === '' || $newLast === '') {
            return 0;
        }

        $stmt = $this->dbh->prepare(
            "select idName from name where Name_First = :first and Name_Last = :last and External_Id regexp '^[0-9]{9,}\$'"
        );
        $stmt->execute([':first' => $newFirst, ':last' => $newLast]);
        $ids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

        return count($ids) === 1 ? $ids[0] : 0;
    }

    /**
     * An existing orphan self patient (see patientRow() - a relative named them in a text field, so they have no
     * Cloudbeds profile id of their own) matching this exact name - see findExistingPersonId()'s "guest" fallback,
     * which is what this is really for: the real person's own first self-check-in must recognize and promote this
     * record (addGuest() links the real external id to whatever idName it's given) rather than create a second,
     * disconnected one, since an orphan can never be found by external id. Requires the last name to match
     * (guaranteed, by the exact-name query) and, when more than one same-named orphan exists, MRN too - the same
     * safety principle as findPatientByMrnAndLastName().
     *
     * @return int idName, 0 if there is no single, safe match
     */
    protected function findOrphanSelfPatient(string $first, string $last, string $mrn): int {
        $newFirst = trim(htmlentities($first));
        $newLast = trim(htmlentities($last));
        if ($newFirst === '' || $newLast === '') {
            return 0;
        }

        $stmt = $this->dbh->prepare(
            "select distinct n.idName from name n join name_guest ng on n.idName = ng.idName
             where ng.Relationship_Code = 'slf' and (n.External_Id = '' or n.External_Id is null)
             and n.Name_First = :first and n.Name_Last = :last"
        );
        $stmt->execute([':first' => $newFirst, ':last' => $newLast]);
        $idNames = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

        if (count($idNames) === 1) {
            return $idNames[0];
        }

        $mrn = trim($mrn);
        if (count($idNames) > 1 && $mrn !== '') {
            // a separate query against hospital_stay directly, rather than joining it above, since one person can
            // have several stays (several hospital_stay rows), which would make a single real match look ambiguous
            $placeholders = implode(',', array_fill(0, count($idNames), '?'));
            $stmt2 = $this->dbh->prepare("select distinct idPatient from hospital_stay where idPatient in ($placeholders) and MRN = ?");
            $stmt2->execute([...$idNames, $mrn]);
            $withMrn = array_map('intval', $stmt2->fetchAll(\PDO::FETCH_COLUMN));
            if (count($withMrn) === 1) {
                return $withMrn[0];
            }
        }

        return 0;
    }

    /**
     * A patient already in HHK with this MRN, corroborated by last name (see findExistingPersonId() - guards against
     * a coincidental collision on a short value like "Last 4 of Veteran's Social" matching two different people).
     * Scoped to the given hospital when known, since an MRN is usually only unique within one hospital's own records.
     *
     * @return int idName, 0 if there is no single unambiguous match
     */
    protected function findPatientByMrnAndLastName(string $mrn, string $lastName, int $idHospital = 0): int {
        $mrn = trim($mrn);
        $lastName = trim($lastName);
        if ($mrn === '' || $lastName === '') {
            return 0;
        }

        $sql = "select distinct hs.idPatient from hospital_stay hs join name n on n.idName = hs.idPatient
                where hs.MRN = :mrn and n.Name_Last = :lastName";
        $params = [':mrn' => $mrn, ':lastName' => $lastName];
        if ($idHospital > 0) {
            $sql .= " and hs.idHospital = :idHospital";
            $params[':idHospital'] = $idHospital;
        }

        $stmt = $this->dbh->prepare($sql);
        $stmt->execute($params);
        $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return count($ids) === 1 ? (int) $ids[0] : 0;
    }

    /**
     * Prefix for a companion guest's HHK External_Id (see ensureReservationPeople()): the Guest Profiles API never
     * pairs a companion (non-main guest) with their own real, persistent Guest Profile id - only their PMS guest id
     * is ever known for them (see CloudbedsGuestMatcher). A bare PMS guest id must never be stored as if it were a
     * real profile id - findPersonByExternalId() would then be unable to tell the two id spaces apart, and a person
     * legitimately re-encountered later under their real profile id (see importProfile()) would not be recognized as
     * the same person, creating a duplicate. This prefix keeps the two spaces structurally distinct.
     */
    protected const PMS_GUEST_ID_PREFIX = 'pmsguest:';

    protected static function pmsGuestExternalId(string $pmsGuestId): string {
        return self::PMS_GUEST_ID_PREFIX . $pmsGuestId;
    }

    // ------------------------------------------------------------------------------------------------
    // Profiles -> people
    // ------------------------------------------------------------------------------------------------

    /**
     * @return array{status: string, hhkId: ?int, data: array, message: string}
     */
    protected function importProfile(array $row): array {
        $profileId = $row['cloudbedsId'];
        $payload = $row['payload'];

        $existing = $this->findPersonByExternalId($profileId);
        if ($existing > 0) {
            return $this->result(CloudbedsStaging::DONE, $existing, [], 'Already imported');
        }

        if (!($payload['hasStay'] ?? false)) {
            return $this->result(CloudbedsStaging::SKIPPED, null, [], 'No stay in the configured timeframe');
        }

        $r = CloudbedsNormalizer::person($payload);
        $r['externalId'] = $profileId;

        $mapped = $this->mapper->apply(CloudbedsFieldMapper::SCOPE_GUEST, (array) ($payload['customFields'] ?? []));
        foreach ($mapped['values'] as $target => $value) {
            $r[substr($target, strlen('guest.'))] = $value;
        }
        // a mapped BirthDate is free text from a custom field (unlike Cloudbeds' own native birthday field), so it needs
        // its own parsing rather than the generic date parsing addGuest() falls back to - see parseCustomFieldDate()
        if (isset($mapped['values']['guest.BirthDate'])) {
            $r['BirthDate'] = CloudbedsNormalizer::parseCustomFieldDate($r['BirthDate']);
        }

        $this->ensureGenLookups($r);

        $guest = $this->addGuest($r);
        if ($guest === false) {
            return $this->result(CloudbedsStaging::SKIPPED, null, [], 'Profile has no last name');
        }

        $idName = (int) $guest->getIdName();

        // guest notes (Cloudbeds' own, and the note.member custom field) aren't attached here: a guest profile has no
        // PSG of its own yet, only a reservation gives it one. See addProfileNotesToPsg(), called from importReservation().

        return $this->result(CloudbedsStaging::DONE, $idName);
    }

    /**
     * A profile's Cloudbeds guest notes and its note.member custom field, attached to the PSG the guest's reservation
     * joins rather than to the guest's own member record. Imported once per profile - tracked on the profile's own
     * staging row - the first time that guest is encountered on a qualifying reservation, whichever PSG that turns out
     * to be; a guest who later turns up on a different patient's reservation does not get the notes repeated there.
     */
    protected function addProfileNotesToPsg(string $profileId, int $idPsg): void {
        $row = $this->staging->getRow(CloudbedsStaging::PROFILE, $profileId);
        if ($row === null || !empty($row['payload']['notesImportedToPsg'])) {
            return;
        }

        $payload = $row['payload'];
        $uS = Session::getInstance();

        $mapped = $this->mapper->apply(CloudbedsFieldMapper::SCOPE_GUEST, (array) ($payload['customFields'] ?? []));
        if (!empty($mapped['notes']['note.member'])) {
            LinkNote::save($this->dbh, $this->noteText($mapped['notes']['note.member']), $idPsg, Note::PsgLink, '', $uS->username);
        }

        if ($this->config->importGuestNotes()) {
            $this->addGuestNotes($idPsg, (array) ($payload['guestNotes'] ?? []));
        }

        $payload['notesImportedToPsg'] = true;
        $this->staging->setPayload((int) $row['id'], $payload);
    }

    /**
     * Add the notes Cloudbeds has on a guest to the PSG as PSG notes, oldest first. Each note keeps the date it was written
     * (when Cloudbeds gives us one - see CloudbedsFetcher::fetchGuestNotes()) and says who wrote it.
     *
     * @param int $idPsg
     * @param array[] $notes Cloudbeds guest notes ["guestNote", "userName", "dateCreated"]
     * @return int number of notes added
     */
    protected function addGuestNotes(int $idPsg, array $notes): int {
        $uS = Session::getInstance();
        $count = 0;

        usort($notes, fn($a, $b) => strcmp((string) ($a['dateCreated'] ?? ''), (string) ($b['dateCreated'] ?? '')));

        foreach ($notes as $note) {
            $text = trim((string) ($note['guestNote'] ?? ''));
            if ($text === '') {
                continue;
            }

            $author = trim((string) ($note['userName'] ?? ''));
            $idNote = LinkNote::save($this->dbh, $this->noteText(['Cloudbeds note' . ($author !== '' ? " by $author" : '') . ': ' . $text]), $idPsg, Note::PsgLink, '', $uS->username);

            // the date the note was written, not the date it was imported
            $written = CloudbedsNormalizer::dateTime((string) ($note['dateCreated'] ?? ''), '00:00:00');
            if (is_int($idNote) && $idNote > 0 && $written !== null) {
                $stmt = $this->dbh->prepare("update `note` set `Timestamp` = :written where `idNote` = :idNote");
                $stmt->execute([':written' => $written, ':idNote' => $idNote]);
            }

            $count++;
        }

        return $count;
    }

    // ------------------------------------------------------------------------------------------------
    // Reservations -> PSG, reservation, visit, stays
    // ------------------------------------------------------------------------------------------------

    /**
     * @return array{status: string, hhkId: ?int, data: array, message: string}
     */
    protected function importReservation(array $row): array {
        $uS = Session::getInstance();
        $reservationId = $row['cloudbedsId'];
        $payload = $row['payload'];
        $summary = (array) ($payload['summary'] ?? []);
        $warnings = [];

        $cloudbedsStatus = (string) ($summary['reservationStatus'] ?? '');
        $statusMap = $this->config->mapStatus($cloudbedsStatus);
        if ($statusMap === null) {
            throw new \RuntimeException("Unmapped reservation status '$cloudbedsStatus'. Add it to reservationStatusMap in the import config.");
        }
        if ($statusMap['reservation'] === null) {
            return $this->result(CloudbedsStaging::SKIPPED, null, [], "Reservations with status '$cloudbedsStatus' are not imported");
        }
        $resvStatus = $statusMap['reservation'];
        $visitStatus = $statusMap['visit'];

        // the reservation's real, persistent Guest Profile id and PMS guest id - a different id space than
        // ensureReservationPeople()'s own guest-id keying, already known from the PMS reservation fields
        // (fetchReservationFields()).
        $realMainProfileId = (string) ($payload['mainProfileId'] ?? '');
        $mainGuestId = (string) ($payload['mainGuestId'] ?? '');

        // custom fields, from three possible sources, each overriding the one before it:
        //  1. the guest's persistent profile-level custom fields (Guest Profiles API) - lowest priority, since a
        //     property mostly uses these for guest.* targets rather than patient/hospital/vehicle ones.
        //  2. the reservation's own top-level custom fields (PMS getReservations).
        //  3. the main guest's own per-slot custom fields (PMS getReservations' guestList[guestId].customFields) -
        //     confirmed against live data as where this account's veteran-specific fields (Branch of Service, Door
        //     Code, Gender/Ethnicity of veteran, Relationship to Patient, Special Needs, ...) actually live; the
        //     reservation's own top-level customFields rarely has more than a couple of fields for this account.
        // Computed before ensureReservationPeople() below so its MRN value can help it recognize the main guest's
        // own first self-check-in as the same person a relative's earlier, separate reservation already named as
        // their patient (see findOrphanSelfPatient()) - that reconciliation needs to happen before any guest or
        // patient record is created for this reservation, not after.
        $values = [];
        $notes = [];

        // 1. guest profile-level (lowest priority). Cloudbeds has no concept of the patient distinct from the guest, so
        // properties sometimes store that data on the guest profile instead of the reservation. Only the main guest's
        // profile is consulted, by their real profile id (not $people's own guest-id keying - see ensureReservationPeople()).
        $mainGuestPayload = $realMainProfileId !== '' ? $this->staging->getPayload(CloudbedsStaging::PROFILE, $realMainProfileId) : null;
        if ($mainGuestPayload !== null) {
            $guestMapped = $this->mapper->apply(CloudbedsFieldMapper::SCOPE_GUEST, (array) ($mainGuestPayload['customFields'] ?? []));

            foreach ($guestMapped['values'] as $target => $value) {
                if (!str_starts_with($target, 'guest.') && $target !== 'note.member') {
                    $values[$target] = $value;
                }
            }
            foreach (['note.reservation', 'note.psg'] as $noteTarget) {
                $notes[$noteTarget] = array_merge($notes[$noteTarget] ?? [], $guestMapped['notes'][$noteTarget] ?? []);
            }
        }

        // 2. the reservation's own top-level custom fields
        $mapped = $this->mapper->apply(CloudbedsFieldMapper::SCOPE_RESERVATION, (array) ($payload['customFields'] ?? []));
        foreach ($mapped['values'] as $target => $value) {
            $values[$target] = $value;
        }
        foreach (['note.reservation', 'note.psg'] as $noteTarget) {
            $notes[$noteTarget] = array_merge($notes[$noteTarget] ?? [], $mapped['notes'][$noteTarget] ?? []);
        }

        // 3. the main guest's own per-slot custom fields (highest priority) - Cloudbeds attaches these to the
        // reservation's own guest-list entry rather than the persistent profile, but a site's own mapping may still
        // categorize them as guest-scope fields (confirmed against a live config - see applyEitherScope()), so each
        // field is checked against whichever scope it's actually mapped under.
        $guestSlotFields = (array) ($payload['pmsGuestList'][$mainGuestId]['customFields'] ?? []);
        if (count($guestSlotFields) > 0) {
            $guestSlotMapped = $this->mapper->applyEitherScope($guestSlotFields, CloudbedsFieldMapper::SCOPE_GUEST, CloudbedsFieldMapper::SCOPE_RESERVATION);
            foreach ($guestSlotMapped['values'] as $target => $value) {
                if (!str_starts_with($target, 'guest.') && $target !== 'note.member') {
                    $values[$target] = $value;
                }
            }
            foreach (['note.reservation', 'note.psg'] as $noteTarget) {
                $notes[$noteTarget] = array_merge($notes[$noteTarget] ?? [], $guestSlotMapped['notes'][$noteTarget] ?? []);
            }
        }

        $mapped['notes'] = $notes;

        // guests: the reservation's guest list, keyed by PMS guest id (see ensureReservationPeople()). The mapped MRN
        // (if any) lets the main guest's first self-check-in recognize a name-only orphan patient a relative's
        // earlier, separate reservation already created for them (see findOrphanSelfPatient()).
        $people = $this->ensureReservationPeople($summary, $realMainProfileId, $mainGuestId, trim($values['mrn'] ?? ''), $warnings);
        if (count($people) === 0) {
            throw new \RuntimeException('Reservation has no importable guests');
        }
        // array keys that look like integers are ints in PHP, keep guest ids as strings when comparing
        $mainGuestKey = (string) (array_key_first(array_filter($people, fn($p) => $p['main'])) ?? array_key_first($people));

        // patient, PSG, registration and hospital stay
        $hospitalTitle = trim($values['hospital'] ?? '');
        if ($hospitalTitle === '' && $this->config->getDefaultHospitalId() > 0) {
            $hospitalTitle = $this->getHospitalTitle($this->config->getDefaultHospitalId());
            if ($hospitalTitle === '') {
                $warnings[] = 'The default hospital no longer exists in HHK';
            }
        }
        if ($hospitalTitle !== '' && $this->ensureHospital($hospitalTitle) === 0) {
            $warnings[] = "Hospital '$hospitalTitle' does not exist in HHK";
        }
        $diagnosis = trim($values['diagnosis'] ?? '');
        $this->ensureGenLookup('Diagnosis', $diagnosis);

        $patientRow = $this->patientRow($values, $people[$mainGuestKey]['row']);
        $patientRow['Hospital'] = $hospitalTitle;
        $patientRow['Diagnosis'] = $diagnosis;
        if (trim($values['mrn'] ?? '') !== '') {
            $patientRow['MRN'] = trim($values['mrn']);
        }
        $this->ensureGenLookups($patientRow);

        $pat = $this->addPatient($patientRow);
        $idPatient = (int) $pat['patient']->getIdName();
        $psg = $pat['psg'];
        $reg = $pat['reg'];
        $idPsg = (int) $psg->getIdPsg();

        // each guest profile's own notes (Cloudbeds guest notes and its note.member custom field), now that this
        // reservation has given them a PSG - see addProfileNotesToPsg(). Only possible for a person whose real profile
        // id is known (the main guest, via $realMainProfileId/'profileId') - a companion is only ever known by their
        // PMS guest id, whose profile was never fetched, so there is nothing to look up for them here.
        foreach ($people as $person) {
            if (($person['profileId'] ?? '') !== '') {
                $this->addProfileNotesToPsg($person['profileId'], $idPsg);
            }
        }

        $idHospitalStay = 0;
        if ($pat['hospStay'] instanceof HospitalStay) {
            $idHospitalStay = (int) $pat['hospStay']->getIdHospital_Stay();
        } else {
            // no hospital on this reservation, use the patient's existing hospital stay
            $idHospitalStay = (int) (new HospitalStay($this->dbh, $idPatient))->getIdHospital_Stay();
        }

        // everyone else joins the PSG
        $relationship = trim($values['relationship'] ?? '');
        foreach ($people as $guestKey => $person) {
            if ($person['idName'] === $idPatient) {
                continue;
            }

            $guestRow = $person['row'];
            if ((string) $guestKey === $mainGuestKey && $relationship !== '') {
                $this->ensureGenLookup('Patient_Rel_Type', $relationship);
                if ($this->findIdGenLookup('Patient_Rel_Type', $relationship) !== '') {
                    $guestRow['Relationship_to_Patient'] = $relationship;
                } else {
                    $warnings[] = "Relationship '$relationship' does not exist in HHK";
                }
            }

            $this->addGuest($guestRow, $psg);
        }

        // vehicle
        $vehicle = [
            'make' => $values['vehicle.make'] ?? '', 'model' => $values['vehicle.model'] ?? '', 'color' => $values['vehicle.color'] ?? '',
            'license' => $values['vehicle.license'] ?? '', 'state' => $values['vehicle.state'] ?? '',
        ];
        if (trim(implode('', $vehicle)) !== '') {
            $this->insertVehicle($reg, $vehicle);
        }

        // one HHK reservation (and visit) per Cloudbeds room. A guest who changed rooms mid-stay has more than one here,
        // each carrying its own status - a segment they've since left is "checked_out" even while the reservation as a
        // whole is still "checked_in" (they're now in a later segment) - see the per-room status override below.
        $rooms = array_values((array) ($summary['rooms'] ?? []));
        $hadRoomData = count($rooms) > 0;
        if (!$hadRoomData) {
            $rooms = [[]];
        }

        $reservationIds = [];
        $visitIds = [];

        foreach ($rooms as $room) {
            // Cloudbeds sometimes reports a room segment with no room assigned at all yet (e.g. the guest is between
            // rooms and the new one isn't finalized) - per import specs.md, no room means no imported visit for this
            // segment; a later re-fetch picks it up once Cloudbeds assigns a room. This only applies to a real, explicit
            // "no room" segment from Cloudbeds' own room list, not the single synthetic segment used below when
            // Cloudbeds gave no room breakdown for the reservation at all.
            if ($hadRoomData && trim((string) ($room['roomName'] ?? '')) === '' && trim((string) ($room['roomId'] ?? '')) === '') {
                continue;
            }

            $arrival = CloudbedsNormalizer::dateTime((string) ($room['checkInAt'] ?? $summary['checkInAt'] ?? ''), CloudbedsNormalizer::DEFAULT_ARRIVAL_TIME);
            $expectedDeparture = CloudbedsNormalizer::dateTime((string) ($room['checkOutAt'] ?? $summary['checkOutAt'] ?? ''), CloudbedsNormalizer::DEFAULT_DEPARTURE_TIME);
            if ($arrival === null || $expectedDeparture === null) {
                throw new \RuntimeException('Reservation has no check-in/check-out dates');
            }

            $roomPeople = $this->roomPeople($room, $people);
            $roomGuestId = (string) ($room['guestId'] ?? '');
            $roomMainGuestKey = isset($roomPeople[$roomGuestId]) ? $roomGuestId : (isset($roomPeople[$mainGuestKey]) ? $mainGuestKey : (string) array_key_first($roomPeople));
            $primaryIdName = $roomPeople[$roomMainGuestKey]['idName'];

            $guestIds = [];
            foreach ($roomPeople as $person) {
                $guestIds[$person['idName']] = ($person['idName'] === $primaryIdName);
            }

            $idResource = $this->resolveResource((string) ($room['roomName'] ?? ''), $warnings);

            // this room segment's own status, not the reservation's overall one, decides whether it is done - a segment
            // Cloudbeds itself reports as checked out is departed regardless of what the guest's current (later) segment
            // is doing; anything else (not yet checked into this room, or any other value) is this reservation's current
            // segment, so it follows the reservation's own overall status
            $segResvStatus = $resvStatus;
            $segVisitStatus = $visitStatus;
            if ((string) ($room['status'] ?? '') === 'checked_out') {
                $segResvStatus = CloudbedsConfig::DEFAULT_STATUS_MAP['checked_out']['reservation'];
                $segVisitStatus = CloudbedsConfig::DEFAULT_STATUS_MAP['checked_out']['visit'];
            }
            $segCheckedOut = $segVisitStatus === VisitStatus::CheckedOut;

            $idResv = $this->insertReservation([
                'idRegistration' => $reg->getIdRegistration(),
                'idGuest' => $primaryIdName,
                'idHospitalStay' => $idHospitalStay,
                'idResource' => $idResource,
                'expectedArrival' => $arrival,
                'expectedDeparture' => $expectedDeparture,
                'actualArrival' => $segVisitStatus !== null ? $arrival : null,
                'actualDeparture' => $segCheckedOut ? $expectedDeparture : null,
                'status' => $segResvStatus,
                'guests' => $guestIds,
            ]);
            $reservationIds[] = $idResv;

            $lines = array_merge(["Imported from Cloudbeds reservation #" . $reservationId], $mapped['notes']['note.reservation'] ?? []);
            LinkNote::save($this->dbh, $this->noteText($lines), $idResv, Note::ResvLink, '', $uS->username);

            if ($segVisitStatus !== null) {
                $stays = [];
                foreach (array_keys($guestIds) as $idName) {
                    $stays[] = ['idName' => $idName, 'idRoom' => $idResource, 'checkin' => $arrival, 'checkout' => $segCheckedOut ? $expectedDeparture : null];
                }

                $visitIds[] = $this->insertVisit([
                    'idReservation' => $idResv,
                    'idRegistration' => $reg->getIdRegistration(),
                    'idHospitalStay' => $idHospitalStay,
                    'idResource' => $idResource,
                    'idPrimaryGuest' => $primaryIdName,
                    'arrival' => $arrival,
                    'expectedDeparture' => $expectedDeparture,
                    'departure' => $segCheckedOut ? $expectedDeparture : null,
                    'status' => $segVisitStatus,
                    'stays' => $stays,
                ]);
            }
        }

        if (count($reservationIds) === 0) {
            return $this->result(CloudbedsStaging::SKIPPED, null, [], 'No room segment with an assigned room could be imported');
        }

        if (!empty($mapped['notes']['note.psg'])) {
            LinkNote::save($this->dbh, $this->noteText($mapped['notes']['note.psg']), $psg->getIdPsg(), Note::PsgLink, '', $uS->username);
        }

        if (count($visitIds) > 1) {
            $warnings[] = 'Multiple rooms: the folio is attached to the first visit';
        }

        return $this->result(CloudbedsStaging::DONE, $reservationIds[0], [
            'psg' => (int) $psg->getIdPsg(),
            'registration' => (int) $reg->getIdRegistration(),
            'patient' => $idPatient,
            'primaryGuest' => $people[$mainGuestKey]['idName'],
            'reservations' => $reservationIds,
            'visits' => $visitIds,
        ], implode('; ', $warnings));
    }

    /**
     * Make sure every guest on the reservation exists in HHK, creating the ones the profile import didn't get to
     * from the guest details on the reservation.
     *
     * The Guest Profiles API's own reservation.guests[].id is the PMS guest id (confirmed against live data) - not a
     * Guest Profile id, despite it being documented as one on CloudbedsGuestMatcher::match(). By itself it is not a
     * reusable identity for a person across imports: Cloudbeds only ever pairs a PMS guest id with a real profile id
     * for a reservation's main guest (see CloudbedsGuestMatcher), so that pairing - $mainProfileId/$mainGuestId,
     * already known from fetchReservationFields() - is used to identify the main guest by their real profile id, the
     * same id importProfile() uses for them. This is what makes the two agree on the same HHK person instead of each
     * creating their own separate record for the same guest. A companion can only ever be identified by their PMS
     * guest id here, which is namespaced (see PMS_GUEST_ID_PREFIX) so it can never be confused with, or overwrite, a
     * real profile id.
     *
     * @return array<string, array{idName: int, row: array, main: bool, profileId: string}> keyed by PMS guest id
     *         (matches room['guestId']/additionalGuestIds, also PMS guest ids). 'profileId' is the person's real
     *         Guest Profile id when known (always for the main guest), '' otherwise.
     */
    protected function ensureReservationPeople(array $summary, string $mainProfileId, string $mainGuestId, string $mrn, array &$warnings): array {
        $people = [];

        foreach ((array) ($summary['guests'] ?? []) as $g) {
            $guestId = (string) ($g['id'] ?? '');
            if ($guestId === '') {
                continue;
            }

            $isMain = !empty($g['isMainGuest']) || ($mainGuestId !== '' && $guestId === $mainGuestId);
            $realProfileId = ($isMain && $mainProfileId !== '') ? $mainProfileId : '';
            $externalId = $realProfileId !== '' ? $realProfileId : self::pmsGuestExternalId($guestId);

            $r = CloudbedsNormalizer::person($g);
            $r['externalId'] = $externalId;
            if ($isMain) {
                // lets the main guest's first-ever self-check-in recognize a name-only orphan patient a relative's
                // earlier, separate reservation already created for them - see findExistingPersonId()'s "guest"
                // fallback and findOrphanSelfPatient()
                $r['MRN'] = $mrn;
            }

            $idName = $this->findPersonByExternalId($externalId);
            if ($idName === 0) {
                $this->ensureGenLookups($r);
                $guest = $this->addGuest($r);

                if ($guest === false) {
                    $warnings[] = "Guest $guestId has no last name and was not imported";
                    continue;
                }
                $idName = (int) $guest->getIdName();
            }

            $people[$guestId] = ['idName' => $idName, 'row' => $r, 'main' => $isMain, 'profileId' => $realProfileId];
        }

        return $people;
    }

    /**
     * The import row for the patient: a patient named by custom fields, or else the main guest is their own patient.
     * Demographics (name, contact info, address, birth date, gender, ethnicity) all come from the mapped patient.* custom
     * fields (see CloudbedsFieldMapper::RESERVATION_FIELDS['Patient']) - a brand new patient has none of these for free the
     * way a guest does from Cloudbeds' own profile fields, since Cloudbeds has no concept of the patient.
     */
    protected function patientRow(array $values, array $mainGuestRow): array {
        $first = trim($values['patient.first'] ?? '');
        $last = trim($values['patient.last'] ?? '');

        if ($last === '' && trim($values['patient.full'] ?? '') !== '') {
            $name = CloudbedsFieldMapper::splitFullName($values['patient.full']);
            $first = $name['first'];
            $last = $name['last'];
        }

        // fields that describe the patient regardless of whether they turn out to be a separate person (below) or the
        // main guest themselves (see $last === '' below) - including any "map to any enabled demographic" values
        $patientFields = [
            'Middle' => trim($values['patient.Middle'] ?? ''),
            'Email' => trim($values['patient.Email'] ?? ''), 'Phone' => trim($values['patient.Phone'] ?? ''), 'Mobile' => trim($values['patient.Mobile'] ?? ''),
            'Address' => trim($values['patient.Address'] ?? ''), 'Address2' => trim($values['patient.Address2'] ?? ''),
            'City' => trim($values['patient.City'] ?? ''), 'County' => trim($values['patient.County'] ?? ''),
            'State' => trim($values['patient.State'] ?? ''), 'ZipCode' => trim($values['patient.ZipCode'] ?? ''), 'Country' => trim($values['patient.Country'] ?? ''),
            'BirthDate' => CloudbedsNormalizer::parseCustomFieldDate($values['patient.BirthDate'] ?? ''),
            'Gender' => CloudbedsNormalizer::gender($values['patient.Gender'] ?? '') ?: trim($values['patient.Gender'] ?? ''),
            'Ethnicity' => trim($values['patient.Ethnicity'] ?? ''),
        ];
        foreach ($values as $target => $value) {
            if (str_starts_with($target, 'patient.demog.')) {
                $patientFields['demog.' . substr($target, strlen('patient.demog.'))] = trim((string) $value);
            }
        }

        if ($last === '') {
            // no separate veteran/patient named - the guest is their own patient, but a mapped patient.* value still
            // describes them and must not be lost; only overlay fields something was actually mapped for, so the
            // guest's own existing data isn't clobbered with blanks. Gender is the one exception: per import specs.md,
            // Cloudbeds' own native profile Gender (already on $mainGuestRow) wins over the "Gender of Veteran if
            // different" custom field's value when both are present.
            $overlay = array_filter($patientFields, fn($v) => $v !== '');
            if (trim((string) ($mainGuestRow['Gender'] ?? '')) !== '') {
                unset($overlay['Gender']);
            }
            return array_merge($mainGuestRow, $overlay);
        }

        return [
            'externalId' => '',
            'FirstName' => $first, 'LastName' => $last,
            'Hospital' => '',
        ] + $patientFields;
    }

    /**
     * The people staying in a room. A room lists its guests by profile id; if it doesn't, everyone on the reservation is in it.
     *
     * @return array<string, array{idName: int, row: array, main: bool}>
     */
    protected function roomPeople(array $room, array $people): array {
        $ids = array_map('strval', array_filter(array_merge([$room['guestId'] ?? ''], (array) ($room['additionalGuestIds'] ?? []))));
        $roomPeople = array_intersect_key($people, array_flip($ids));

        return count($roomPeople) > 0 ? $roomPeople : $people;
    }

    // ------------------------------------------------------------------------------------------------
    // Folios -> invoices and payments
    // ------------------------------------------------------------------------------------------------

    /**
     * @return array{status: string, hhkId: ?int, data: array, message: string}
     */
    protected function importFolio(array $row): array {
        $uS = Session::getInstance();
        $payload = $row['payload'];
        $reservationId = (string) ($row['parentId'] !== '' ? $row['parentId'] : ($payload['reservationId'] ?? ''));

        $resv = $this->staging->getRow(CloudbedsStaging::RESERVATION, $reservationId);
        if ($resv === null) {
            return $this->result(CloudbedsStaging::SKIPPED, null, [], "Reservation $reservationId was not fetched");
        }
        if ($resv['status'] === CloudbedsStaging::ERROR) {
            throw new \RuntimeException("Reservation $reservationId failed to import, retry it first: " . $resv['message']);
        }
        if ($resv['status'] !== CloudbedsStaging::DONE || empty($resv['hhkData']['visits'])) {
            return $this->result(CloudbedsStaging::SKIPPED, null, [], "Reservation $reservationId was not imported as a visit, so its folio is not imported");
        }

        $data = $resv['hhkData'];
        $invoice = $this->folioImporter->import($payload, (int) $data['visits'][0], (int) $data['registration'], (int) $data['primaryGuest'], $uS->username);

        if ($invoice === null) {
            return $this->result(CloudbedsStaging::SKIPPED, null, [], 'Folio has no charges');
        }

        $message = implode('; ', $invoice['warnings']);

        if ($invoice['idInvoice'] === 0) {
            return $this->result(CloudbedsStaging::SKIPPED, null, [], $message);
        }

        return $this->result(CloudbedsStaging::DONE, $invoice['idInvoice'], [
            'invoiceNumber' => $invoice['invoiceNumber'], 'amount' => $invoice['amount'], 'paid' => $invoice['paid'],
        ], $message);
    }

    // ------------------------------------------------------------------------------------------------
    // Lookups
    // ------------------------------------------------------------------------------------------------

    /**
     * Create any missing gen lookup values used by an import row, if the config allows it
     */
    protected function ensureGenLookups(array $r): void {
        $this->ensureGenLookup('Gender', (string) ($r['Gender'] ?? ''));
        $this->ensureGenLookup('Ethnicity', (string) ($r['Ethnicity'] ?? ''));
        $this->ensureGenLookup('No_Return', (string) ($r['Banned'] ?? ''));
        $this->ensureGenLookup('Media_Source', (string) ($r['mediaSource'] ?? ''));

        // same "create missing" support for any other mapped demographic (guest.demog.<Code> / patient.demog.<Code>),
        // see AbstractImport::addGuest()/addPatient() for where the matching demog.<Code> keys get applied
        foreach ($r as $key => $value) {
            if (str_starts_with((string) $key, 'demog.')) {
                $this->ensureGenLookup(substr((string) $key, strlen('demog.')), (string) $value);
            }
        }
    }

    protected function ensureGenLookup(string $genLookupTableName, string $value): void {
        $value = trim($value);

        if ($value !== '' && $this->config->createMissing('genLookups') && $this->findIdGenLookup($genLookupTableName, $value) === '') {
            $this->createGenLookup($genLookupTableName, $value);
        }
    }

    /**
     * @return int idHospital, 0 if it doesn't exist and can't be created
     */
    protected function ensureHospital(string $title): int {
        $key = strtolower(trim($title));

        if (isset($this->hospitals[$key])) {
            return (int) $this->hospitals[$key];
        }

        return $this->config->createMissing('hospitals') ? $this->createHospital(trim($title)) : 0;
    }

    /**
     * @return string the hospital's title, empty if there is no such hospital
     */
    protected function getHospitalTitle(int $idHospital): string {
        $stmt = $this->dbh->prepare("select `Title` from `hospital` where `idHospital` = :id");
        $stmt->execute([':id' => $idHospital]);
        $title = $stmt->fetchColumn();

        return $title === false ? '' : (string) $title;
    }

    protected function resolveResource(string $cloudbedsRoomName, array &$warnings): int {
        $cloudbedsRoomName = trim($cloudbedsRoomName);
        if ($cloudbedsRoomName === '') {
            return 0;
        }

        // mapped to an HHK room
        $mappedId = $this->config->getMappedRoomId($cloudbedsRoomName);
        if ($mappedId > 0) {
            if (in_array((string) $mappedId, array_map('strval', $this->rooms ?? []), true)) {
                return $mappedId;
            }
            $warnings[] = "The HHK room mapped to '$cloudbedsRoomName' no longer exists, matching by name instead";
        }

        // otherwise the room with the same name
        $title = $cloudbedsRoomName;

        $idResource = (int) $this->findIdResource($title);
        if ($idResource > 0) {
            return $idResource;
        }

        if ($this->config->createMissing('rooms')) {
            return $this->createRoom($title);
        }

        $warnings[] = "Room '$title' does not exist in HHK";
        return 0;
    }

    // ------------------------------------------------------------------------------------------------

    /**
     * @param string[] $lines
     */
    protected function noteText(array $lines): string {
        return htmlspecialchars(trim(implode("\n", $lines)), ENT_QUOTES);
    }

    /**
     * @return array{status: string, hhkId: ?int, data: array, message: string}
     */
    protected function result(string $status, ?int $hhkId, array $data = [], string $message = ''): array {
        return ['status' => $status, 'hhkId' => $hhkId, 'data' => $data, 'message' => $message];
    }
}
