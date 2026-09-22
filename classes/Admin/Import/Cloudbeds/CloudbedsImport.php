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
 *    The notes on the guest in Cloudbeds become member notes.
 *  - reservations become an HHK reservation, plus a visit and stays when the guest checked in/out. Cloudbeds has no patients or PSGs, so
 *    the patient, hospital, diagnosis, etc. come from custom fields, mapped in the config. Without a mapped patient the main guest is their own patient.
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

    protected CloudbedsConfig $config;
    protected CloudbedsStaging $staging;
    protected CloudbedsFieldMapper $mapper;
    protected CloudbedsFolioImporter $folioImporter;
    protected ?CloudbedsFetcher $fetcher = null;

    public function __construct(\PDO $dbh, ?CloudbedsConfig $config = null) {
        parent::__construct($dbh);

        $this->config = $config ?? CloudbedsConfig::fromDatabase($dbh);
        $this->mapper = $this->config->getFieldMapper();
        $this->staging = new CloudbedsStaging($dbh);
        $this->staging->ensureTables();
        $this->folioImporter = new CloudbedsFolioImporter($dbh, $this->config);

        $this->genLookupMapping = [
            "gender" => "Gender",
            "ethnicity" => "Ethnicity",
            "banned" => "No_Return",
            "mediaSource" => "Media_Source",
            "relationship" => "Patient_Rel_Type",
            "diagnosis" => "Diagnosis",
        ];
        $this->fieldMapping = [];

        $this->loadGenLookups();
        $this->getHospitals();
        $this->getRooms();
    }

    public function getConfig(): CloudbedsConfig {
        return $this->config;
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
     * Imported people are matched on their Cloudbeds profile id, never on name. People without one (patients taken from a custom field)
     * are matched on name and patient relationship.
     */
    protected function findExistingPersonId(array $r, string $memberType, string $first, string $last) {
        if (($r['externalId'] ?? '') !== '') {
            return $this->findPersonByExternalId($r['externalId']);
        }

        // names are stored html escaped
        $stmt = $this->dbh->prepare("select n.`idName` from `name` n join `name_guest` ng on n.`idName` = ng.`idName` where n.`Name_Last` = :last and n.`Name_First` = :first and n.`Member_Status` != 'tbd' and ng.`Relationship_Code` = 'slf' limit 1");
        $stmt->execute([
            ':last' => filter_var($last, FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            ':first' => filter_var($first, FILTER_SANITIZE_FULL_SPECIAL_CHARS),
        ]);
        $id = $stmt->fetchColumn();

        return $id === false ? 0 : (int) $id;
    }

    // ------------------------------------------------------------------------------------------------
    // Profiles -> people
    // ------------------------------------------------------------------------------------------------

    /**
     * @return array{status: string, hhkId: ?int, data: array, message: string}
     */
    protected function importProfile(array $row): array {
        $uS = Session::getInstance();
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

        $this->ensureGenLookups($r);

        $guest = $this->addGuest($r);
        if ($guest === false) {
            return $this->result(CloudbedsStaging::SKIPPED, null, [], 'Profile has no last name');
        }

        $idName = (int) $guest->getIdName();

        if (!empty($mapped['notes']['note.member'])) {
            LinkNote::save($this->dbh, $this->noteText($mapped['notes']['note.member']), $idName, Note::MemberLink, '', $uS->username);
        }

        $noteCount = $this->config->importGuestNotes() ? $this->addGuestNotes($idName, (array) ($payload['guestNotes'] ?? [])) : 0;

        return $this->result(CloudbedsStaging::DONE, $idName, $noteCount > 0 ? ['notes' => $noteCount] : []);
    }

    /**
     * Add the notes Cloudbeds has on a guest to the person as member notes, oldest first. Each note keeps the date it was written and says who wrote it.
     *
     * @param int $idName
     * @param array[] $notes Cloudbeds guest notes ["guestNote", "userName", "dateCreated"]
     * @return int number of notes added
     */
    protected function addGuestNotes(int $idName, array $notes): int {
        $uS = Session::getInstance();
        $count = 0;

        usort($notes, fn($a, $b) => strcmp((string) ($a['dateCreated'] ?? ''), (string) ($b['dateCreated'] ?? '')));

        foreach ($notes as $note) {
            $text = trim((string) ($note['guestNote'] ?? ''));
            if ($text === '') {
                continue;
            }

            $author = trim((string) ($note['userName'] ?? ''));
            $idNote = LinkNote::save($this->dbh, $this->noteText(['Cloudbeds note' . ($author !== '' ? " by $author" : '') . ': ' . $text]), $idName, Note::MemberLink, '', $uS->username);

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

        // guests: the profile ids on the reservation
        $people = $this->ensureReservationPeople($summary, $warnings);
        if (count($people) === 0) {
            throw new \RuntimeException('Reservation has no importable guests');
        }
        // array keys that look like integers are ints in PHP, keep profile ids as strings when comparing
        $mainProfileId = (string) (array_key_first(array_filter($people, fn($p) => $p['main'])) ?? array_key_first($people));

        // custom fields
        $mapped = $this->mapper->apply(CloudbedsFieldMapper::SCOPE_RESERVATION, (array) ($payload['customFields'] ?? []));
        $values = $mapped['values'];

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

        $patientRow = $this->patientRow($values, $people[$mainProfileId]['row']);
        $patientRow['Hospital'] = $hospitalTitle;
        $patientRow['Diagnosis'] = $diagnosis;
        if (trim($values['mrn'] ?? '') !== '') {
            $patientRow['MRN'] = trim($values['mrn']);
        }

        $pat = $this->addPatient($patientRow);
        $idPatient = (int) $pat['patient']->getIdName();
        $psg = $pat['psg'];
        $reg = $pat['reg'];

        $idHospitalStay = 0;
        if ($pat['hospStay'] instanceof HospitalStay) {
            $idHospitalStay = (int) $pat['hospStay']->getIdHospital_Stay();
        } else {
            // no hospital on this reservation, use the patient's existing hospital stay
            $idHospitalStay = (int) (new HospitalStay($this->dbh, $idPatient))->getIdHospital_Stay();
        }

        // everyone else joins the PSG
        $relationship = trim($values['relationship'] ?? '');
        foreach ($people as $profileId => $person) {
            if ($person['idName'] === $idPatient) {
                continue;
            }

            $guestRow = $person['row'];
            if ((string) $profileId === $mainProfileId && $relationship !== '') {
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

        // one HHK reservation (and visit) per Cloudbeds room
        $rooms = array_values((array) ($summary['rooms'] ?? []));
        if (count($rooms) === 0) {
            $rooms = [[]];
        }

        $reservationIds = [];
        $visitIds = [];

        foreach ($rooms as $room) {
            $arrival = CloudbedsNormalizer::dateTime((string) ($room['checkInAt'] ?? $summary['checkInAt'] ?? ''), CloudbedsNormalizer::DEFAULT_ARRIVAL_TIME);
            $expectedDeparture = CloudbedsNormalizer::dateTime((string) ($room['checkOutAt'] ?? $summary['checkOutAt'] ?? ''), CloudbedsNormalizer::DEFAULT_DEPARTURE_TIME);
            if ($arrival === null || $expectedDeparture === null) {
                throw new \RuntimeException('Reservation has no check-in/check-out dates');
            }

            $roomPeople = $this->roomPeople($room, $people);
            $roomGuestId = (string) ($room['guestId'] ?? '');
            $roomMainProfileId = isset($roomPeople[$roomGuestId]) ? $roomGuestId : (isset($roomPeople[$mainProfileId]) ? $mainProfileId : (string) array_key_first($roomPeople));
            $primaryIdName = $roomPeople[$roomMainProfileId]['idName'];

            $guestIds = [];
            foreach ($roomPeople as $person) {
                $guestIds[$person['idName']] = ($person['idName'] === $primaryIdName);
            }

            $idResource = $this->resolveResource((string) ($room['roomName'] ?? ''), $warnings);
            $checkedOut = $visitStatus === VisitStatus::CheckedOut;

            $idResv = $this->insertReservation([
                'idRegistration' => $reg->getIdRegistration(),
                'idGuest' => $primaryIdName,
                'idHospitalStay' => $idHospitalStay,
                'idResource' => $idResource,
                'expectedArrival' => $arrival,
                'expectedDeparture' => $expectedDeparture,
                'actualArrival' => $visitStatus !== null ? $arrival : null,
                'actualDeparture' => $checkedOut ? $expectedDeparture : null,
                'status' => $resvStatus,
                'guests' => $guestIds,
            ]);
            $reservationIds[] = $idResv;

            $lines = array_merge(["Imported from Cloudbeds reservation #" . $reservationId], $mapped['notes']['note.reservation'] ?? []);
            LinkNote::save($this->dbh, $this->noteText($lines), $idResv, Note::ResvLink, '', $uS->username);

            if ($visitStatus !== null) {
                $stays = [];
                foreach (array_keys($guestIds) as $idName) {
                    $stays[] = ['idName' => $idName, 'idRoom' => $idResource, 'checkin' => $arrival, 'checkout' => $checkedOut ? $expectedDeparture : null];
                }

                $visitIds[] = $this->insertVisit([
                    'idReservation' => $idResv,
                    'idRegistration' => $reg->getIdRegistration(),
                    'idHospitalStay' => $idHospitalStay,
                    'idResource' => $idResource,
                    'idPrimaryGuest' => $primaryIdName,
                    'arrival' => $arrival,
                    'expectedDeparture' => $expectedDeparture,
                    'departure' => $checkedOut ? $expectedDeparture : null,
                    'status' => $visitStatus,
                    'stays' => $stays,
                ]);
            }
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
            'primaryGuest' => $people[$mainProfileId]['idName'],
            'reservations' => $reservationIds,
            'visits' => $visitIds,
        ], implode('; ', $warnings));
    }

    /**
     * Make sure every guest on the reservation exists in HHK, creating the ones the profile import didn't get to
     * from the guest details on the reservation.
     *
     * @return array<string, array{idName: int, row: array, main: bool}> keyed by profile id
     */
    protected function ensureReservationPeople(array $summary, array &$warnings): array {
        $people = [];

        foreach ((array) ($summary['guests'] ?? []) as $g) {
            $profileId = (string) ($g['id'] ?? '');
            if ($profileId === '') {
                continue;
            }

            $r = CloudbedsNormalizer::person($g);
            $r['externalId'] = $profileId;

            $idName = $this->findPersonByExternalId($profileId);
            if ($idName === 0) {
                $this->ensureGenLookups($r);
                $guest = $this->addGuest($r);

                if ($guest === false) {
                    $warnings[] = "Guest $profileId has no last name and was not imported";
                    continue;
                }
                $idName = (int) $guest->getIdName();
            }

            $people[$profileId] = ['idName' => $idName, 'row' => $r, 'main' => !empty($g['isMainGuest'])];
        }

        return $people;
    }

    /**
     * The import row for the patient: a patient named by custom fields, or else the main guest is their own patient
     */
    protected function patientRow(array $values, array $mainGuestRow): array {
        $first = trim($values['patient.first'] ?? '');
        $last = trim($values['patient.last'] ?? '');

        if ($last === '' && trim($values['patient.full'] ?? '') !== '') {
            $name = CloudbedsFieldMapper::splitFullName($values['patient.full']);
            $first = $name['first'];
            $last = $name['last'];
        }

        if ($last === '') {
            return $mainGuestRow;
        }

        return [
            'externalId' => '',
            'FirstName' => $first, 'Middle' => '', 'LastName' => $last,
            'Email' => trim($values['patient.Email'] ?? ''), 'Phone' => trim($values['patient.Phone'] ?? ''), 'Mobile' => '',
            'Address' => '', 'Address2' => '', 'City' => '', 'County' => '', 'State' => '', 'ZipCode' => '', 'Country' => '',
            'BirthDate' => trim($values['patient.BirthDate'] ?? ''),
            'Gender' => CloudbedsNormalizer::gender($values['patient.Gender'] ?? '') ?: trim($values['patient.Gender'] ?? ''),
            'Hospital' => '',
        ];
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
