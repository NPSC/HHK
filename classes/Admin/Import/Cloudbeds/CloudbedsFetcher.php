<?php
namespace HHK\Admin\Import\Cloudbeds;

/**
 * Pulls data from Cloudbeds into the staging table, in resumable steps that each stay within a time budget so
 * they can be driven from repeated web requests:
 *
 *  1. guestList          which reservations count as a stay, from the PMS getGuestList endpoint (see CloudbedsConfig::getStayedFrom() etc),
 *                        plus a second pass for Confirmed reservations checking out from today onward (not yet checked in, but still
 *                        worth importing as a future reservation - see import specs.md). Seeds a reservation row per qualifying
 *                        reservation; nothing else ever creates one, so only reservations one of these two passes reports are ever
 *                        staged or imported.
 *  2. reservationFields  reservation custom fields, from the PMS API, filtered to the same timeframe. Also queues the main guest's
 *                        profile id to be fetched next - Cloudbeds only pairs a guest profile id with a PMS guest id for a
 *                        reservation's main guest (see CloudbedsGuestMatcher), so that pairing is the only way in.
 *  3. profiles           each queued profile is fetched and matched against its own reservations (Guest Profiles API); only a match
 *                        against a reservation already staged in step 1 counts as a stay (a profile with none is never imported, see
 *                        CloudbedsImport::importProfile()), and only then are its custom fields fetched. Any other guest found on a
 *                        matched reservation is queued here too, so this discovers every profile connected to a qualifying reservation
 *                        without ever enumerating the whole account's guest history.
 *  4. guestNotes         the notes on each guest by PMS guest id, from getGuestNotes (skipped when guest notes aren't imported). Some
 *                        accounts have notes getGuestNotes never returns but that getGuestList's own guestNotes does (seen on a live
 *                        account) - those are added too, carried over from the guestList step since they have no date or author to
 *                        refetch by. See fetchGuestNotes().
 *
 * Folios are not fetched at all - CloudbedsFolioImporter, CloudbedsStaging::FOLIO and the folio-related value maps still
 * exist, but nothing ever stages a folio row, so that part of the importer is effectively disabled.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class CloudbedsFetcher {

    public const STEPS = ['guestList', 'reservationFields', 'profiles', 'guestNotes'];

    public function __construct(
        protected CloudbedsClient $client,
        protected CloudbedsStaging $staging,
        protected CloudbedsConfig $config
    ) {
    }

    /**
     * Do fetch work until the time budget is spent or everything has been fetched
     *
     * @param int $seconds time budget
     * @return array{complete: bool, step: string, message: string}
     */
    public function run(int $seconds = 20): array {
        $this->staging->ensureTables();
        $deadline = microtime(true) + $seconds;
        $message = '';

        while (true) {
            $step = $this->staging->getMeta('fetchStep', self::STEPS[0]);

            if ($step === 'complete') {
                return ['complete' => true, 'step' => $step, 'message' => 'Fetch complete'];
            }

            $finished = match ($step) {
                'guestList' => $this->fetchGuestList($deadline),
                'reservationFields' => $this->fetchReservationFields($deadline),
                'profiles' => $this->fetchProfiles($deadline),
                'guestNotes' => $this->fetchGuestNotes($deadline),
                default => throw new \RuntimeException("Unknown fetch step '$step'"),
            };

            $message = $this->describe($step);

            if ($finished) {
                $next = self::STEPS[array_search($step, self::STEPS, true) + 1] ?? 'complete';
                $this->staging->setMeta('fetchStep', $next);
                continue;
            }

            return ['complete' => false, 'step' => $step, 'message' => $message];
        }
    }

    protected function describe(string $step): string {
        $counts = $this->staging->counts();
        return match ($step) {
            'guestList' => 'Finding guests who stayed: ' . array_sum($counts[CloudbedsStaging::RESERVATION]) . ' qualifying reservations found',
            'reservationFields' => 'Fetching reservation details: ' . array_sum($counts[CloudbedsStaging::RESERVATION]) . ' qualifying reservations enriched, ' . array_sum($counts[CloudbedsStaging::PROFILE]) . ' guest profiles found so far',
            'profiles' => 'Fetching guest profiles: ' . $this->staging->countUnfetched(CloudbedsStaging::PROFILE) . ' left, ' . array_sum($counts[CloudbedsStaging::PROFILE]) . ' found so far',
            'guestNotes' => 'Fetching guest notes: ' . $this->staging->getMeta('guestNotesDone', '0') . ' guests done, ' . $this->staging->getMeta('guestNotesUnpaired', '0') . ' without a Cloudbeds guest id (no notes)',
            default => '',
        };
    }

    /**
     * Seed a reservation row for every reservation getGuestList reports as stayed (per the configured timeframe and statuses),
     * plus - per import specs.md ("For dates in the future, HHK will bring in reservations that aren't yet checked in") - every
     * Confirmed reservation checking out from today onward, regardless of the configured stay statuses. Nothing else ever
     * creates a reservation row, so this is what decides which reservations (and, transitively, which guest profiles) end up
     * imported.
     */
    protected function fetchGuestList(float $deadline): bool {
        if (!$this->fetchGuestListPass($deadline, 'guestList', $this->config->getStayedFrom(), $this->config->getStayedTo(), $this->config->getStayStatuses())) {
            return false;
        }

        return $this->fetchGuestListPass($deadline, 'guestListFuture', date('Y-m-d'), $this->config->getStayedTo(), ['confirmed']);
    }

    /**
     * One getGuestList sweep across every configured property, for a given status set and date range - paginated and
     * resumable via $metaPrefix-scoped staging meta keys, so fetchGuestList()'s two passes track their progress independently.
     *
     * @param string[] $statuses
     */
    protected function fetchGuestListPass(float $deadline, string $metaPrefix, string $checkOutFrom, string $checkOutTo, array $statuses): bool {
        foreach ($this->config->getPropertyIds() as $propertyId) {
            if ($this->staging->getMeta("{$metaPrefix}Done:$propertyId") === '1') {
                continue;
            }

            while (microtime(true) < $deadline) {
                $pageNumber = (int) $this->staging->getMeta("{$metaPrefix}Page:$propertyId", '1');
                $page = $this->client->getGuestListPage($propertyId, $pageNumber, $checkOutFrom, $checkOutTo, $statuses);

                foreach ($page['data'] as $entry) {
                    $reservationId = (string) ($entry['reservationID'] ?? '');
                    $guestId = (string) ($entry['guestID'] ?? '');
                    if ($reservationId === '' || $guestId === '') {
                        continue;
                    }

                    $staged = $this->staging->getPayload(CloudbedsStaging::RESERVATION, $reservationId) ?? [
                        'reservationId' => $reservationId,
                        'propertyId' => $propertyId,
                        'summary' => [],
                        'profileIds' => [],
                        'customFields' => [],
                        'guestListGuests' => [],
                    ];

                    $staged['guestListStatus'] = (string) ($entry['status'] ?? '');
                    if (!in_array($guestId, array_column($staged['guestListGuests'], 'guestId'), true)) {
                        // getGuestList's own guest notes (no date/author, unlike getGuestNotes) - some accounts have notes
                        // here that getGuestNotes does not return at all, see fetchGuestNotes()
                        $staged['guestListGuests'][] = ['guestId' => $guestId, 'isMainGuest' => !empty($entry['isMainGuest']), 'notes' => (array) ($entry['guestNotes'] ?? [])];
                    }

                    $this->staging->upsert(CloudbedsStaging::RESERVATION, $reservationId, $staged, '', $propertyId);
                }

                if (count($page['data']) < CloudbedsClient::PMS_PAGE_SIZE || $pageNumber * CloudbedsClient::PMS_PAGE_SIZE >= $page['total']) {
                    $this->staging->setMeta("{$metaPrefix}Done:$propertyId", '1');
                    break;
                }

                $this->staging->setMeta("{$metaPrefix}Page:$propertyId", $pageNumber + 1);
            }

            if ($this->staging->getMeta("{$metaPrefix}Done:$propertyId") !== '1') {
                return false;
            }
        }

        return true;
    }

    /**
     * Reservation custom fields and each reservation's main guest (PMS profileID/guestID), from the PMS API, filtered to the
     * same timeframe guestList used. Queues the main guest's profile id to be fetched in the profiles step - that, plus any
     * roommate discovered there, is the complete set of profiles this import ever looks at.
     */
    protected function fetchReservationFields(float $deadline): bool {
        // mirrors fetchGuestList()'s own (unconditional) date range exactly - unlike guestList, this step never decides
        // which reservations qualify itself (that's the $staged !== null check below), so there is no reason for its
        // filter to be any wider than guestList's, even when includeCurrentGuests() means guestList's own filter still
        // applies to a checked-in guest's scheduled checkout date
        $checkOutFrom = $this->config->getStayedFrom();
        $checkOutTo = $this->config->getStayedTo();

        foreach ($this->config->getPropertyIds() as $propertyId) {
            if ($this->staging->getMeta("resvFieldsDone:$propertyId") === '1') {
                continue;
            }

            while (microtime(true) < $deadline) {
                $pageNumber = (int) $this->staging->getMeta("resvPage:$propertyId", '1');
                $page = $this->client->getReservationsPage($propertyId, $pageNumber, $checkOutFrom, $checkOutTo);

                foreach ($page['data'] as $item) {
                    $reservationId = (string) ($item['reservationID'] ?? '');
                    $staged = $reservationId !== '' ? $this->staging->getPayload(CloudbedsStaging::RESERVATION, $reservationId) : null;

                    if ($staged !== null) {
                        $staged['customFields'] = (array) ($item['customFields'] ?? []);
                        $staged['pmsGuestList'] = (array) ($item['guestList'] ?? []);
                        $staged['mainProfileId'] = (string) ($item['profileID'] ?? '');
                        $staged['mainGuestId'] = (string) ($item['guestID'] ?? '');
                        $this->staging->upsert(CloudbedsStaging::RESERVATION, $reservationId, $staged, '', $propertyId);

                        if ($staged['mainProfileId'] !== '' && $this->staging->getPayload(CloudbedsStaging::PROFILE, $staged['mainProfileId']) === null) {
                            $this->staging->upsert(CloudbedsStaging::PROFILE, $staged['mainProfileId'], ['id' => $staged['mainProfileId']]);
                        }
                    }
                }

                if (count($page['data']) < CloudbedsClient::PMS_PAGE_SIZE || $pageNumber * CloudbedsClient::PMS_PAGE_SIZE >= $page['total']) {
                    $this->staging->setMeta("resvFieldsDone:$propertyId", '1');
                    break;
                }

                $this->staging->setMeta("resvPage:$propertyId", $pageNumber + 1);
            }

            if ($this->staging->getMeta("resvFieldsDone:$propertyId") !== '1') {
                return false;
            }
        }

        return true;
    }

    /**
     * Fetch each profile queued by fetchReservationFields() (a qualifying reservation's main guest) or discovered as a
     * roommate below (another guest on a reservation one of these profiles turns out to share) - nothing else ever
     * queues a profile, so this never has to look at the account's whole guest history. For each: fetch the full
     * profile record, then match it against its own reservations (Guest Profiles API) to find which, if any, are
     * qualifying reservations already staged by guestList - that match is what "hasStay" means, and is also how the
     * profile id gets paired with a PMS guest id (CloudbedsGuestMatcher) so guest notes can be fetched later. Custom
     * fields are only fetched for a profile that has a stay, since that is the only kind that gets imported.
     */
    protected function fetchProfiles(float $deadline): bool {
        $propertyIds = $this->config->getPropertyIds();

        while (microtime(true) < $deadline) {
            $rows = $this->staging->nextUnfetched(CloudbedsStaging::PROFILE, 10);
            if (count($rows) === 0) {
                return true;
            }

            foreach ($rows as $row) {
                $profileId = $row['cloudbedsId'];
                $profile = $this->client->getProfileById($profileId);
                $hasStay = false;

                // merged profiles are no longer active in Cloudbeds, their reservations belong to the surviving profile
                if ($profile !== null && empty($profile['isMerged'])) {
                    foreach ($this->client->iterateProfileReservations($profileId) as $reservation) {
                        $resvPropertyId = (string) ($reservation['property']['id'] ?? '');
                        // the Guest Profiles API's own "id" for a reservation is a different id than the PMS reservationID
                        // (and than getGuestList/getReservations stage this reservation under) - "externalId" is the one
                        // that actually matches the PMS side, confirmed against live data
                        $reservationId = (string) ($reservation['externalId'] ?? '');
                        if ($reservationId === '' || !in_array($resvPropertyId, $propertyIds, true)) {
                            continue;
                        }

                        // only a reservation the guest list step already staged (i.e. reported as stayed) is ever enriched or imported
                        $staged = $this->staging->getPayload(CloudbedsStaging::RESERVATION, $reservationId);
                        if ($staged === null) {
                            continue;
                        }

                        $hasStay = true;
                        $staged['summary'] = $reservation;
                        if (!in_array($profileId, $staged['profileIds'], true)) {
                            $staged['profileIds'][] = $profileId;
                        }

                        // queue any other guest on this reservation that hasn't been seen yet, so it gets fetched too
                        foreach ((array) ($reservation['guests'] ?? []) as $guest) {
                            $otherId = (string) ($guest['id'] ?? '');
                            if ($otherId !== '' && $this->staging->getPayload(CloudbedsStaging::PROFILE, $otherId) === null) {
                                $this->staging->upsert(CloudbedsStaging::PROFILE, $otherId, ['id' => $otherId]);
                            }
                        }

                        $staged['guestIds'] = CloudbedsGuestMatcher::match(
                            (array) ($reservation['guests'] ?? []),
                            (array) ($staged['pmsGuestList'] ?? []),
                            (string) ($staged['mainProfileId'] ?? ''),
                            (string) ($staged['mainGuestId'] ?? '')
                        );

                        $this->staging->upsert(CloudbedsStaging::RESERVATION, $reservationId, $staged, '', $resvPropertyId);

                        foreach ($staged['guestIds'] as $pid => $gid) {
                            $guestListGuest = current(array_filter($staged['guestListGuests'], fn($g) => $g['guestId'] === (string) $gid)) ?: [];
                            $this->rememberGuest((string) $pid, (string) $gid, $resvPropertyId, (array) ($guestListGuest['notes'] ?? []));
                        }
                    }
                }

                // re-read the staged payload rather than starting from $row['payload']: rememberGuest() may already have
                // attached guestRefs to this row above (either from this profile's own main-guest pairing, or from another
                // profile processed earlier that shares a reservation with it), and those must not be lost here
                $current = $this->staging->getPayload(CloudbedsStaging::PROFILE, $profileId) ?? ['id' => $profileId];
                $payload = ($profile ?? []) + $current;
                $payload['hasStay'] = $hasStay;
                // custom fields are only useful for profiles that will actually be imported
                $payload['customFields'] = $hasStay ? $this->client->getProfileCustomFields($profileId) : [];

                $this->staging->setPayload((int) $row['id'], $payload);
                $this->staging->markFetched((int) $row['id']);
            }
        }

        return false;
    }

    /**
     * Note a profile's PMS guest id at a property, where its notes are - plus any notes getGuestList already handed us
     * for that guest id, so fetchGuestNotes() can fall back to them when getGuestNotes doesn't return the same note.
     */
    protected function rememberGuest(string $profileId, string $guestId, string $propertyId, array $guestListNotes = []): void {
        $profile = $this->staging->getPayload(CloudbedsStaging::PROFILE, $profileId);
        if ($profile === null) {
            return;
        }

        foreach ((array) ($profile['guestRefs'] ?? []) as $ref) {
            if ($ref['guestId'] === $guestId && $ref['propertyId'] === $propertyId) {
                return;
            }
        }

        $profile['guestRefs'][] = ['guestId' => $guestId, 'propertyId' => $propertyId, 'guestListNotes' => $guestListNotes];
        $this->staging->upsert(CloudbedsStaging::PROFILE, $profileId, $profile);
    }

    /**
     * Each profile's notes, by the PMS guest ids paired with it in the reservation step. A profile with no PMS guest id has no notes fetched
     * (the profile id is not a guest id), and is counted so it can be reported. Walks the profiles in order, keeping a cursor.
     *
     * getGuestNotes is the primary source (it has the note's date and author), but some accounts have notes that show up
     * in getGuestList's own guestNotes and never in getGuestNotes at all - those are added too, so nothing gets lost,
     * but only to fill a gap: a note getGuestNotes already returned (richer, dated) is never replaced by the dateless
     * getGuestList version of the same note.
     */
    protected function fetchGuestNotes(float $deadline): bool {
        if (!$this->config->importGuestNotes()) {
            return true;
        }

        while (microtime(true) < $deadline) {
            $rows = $this->staging->nextProfilesAfter((int) $this->staging->getMeta('guestNotesCursor', '0'), 10);
            if (count($rows) === 0) {
                return true;
            }

            foreach ($rows as $row) {
                $payload = $row['payload'];

                $notes = [];
                foreach ((array) ($payload['guestRefs'] ?? []) as $ref) {
                    foreach ($this->client->getGuestNotes((string) $ref['propertyId'], (string) $ref['guestId']) as $note) {
                        $noteId = (string) ($note['guestNoteID'] ?? '');
                        $notes[$ref['guestId'] . ':' . ($noteId !== '' ? $noteId : md5(json_encode($note)))] = $note;
                    }

                    foreach ((array) ($ref['guestListNotes'] ?? []) as $note) {
                        $noteId = (string) ($note['ID'] ?? '');
                        $key = $ref['guestId'] . ':' . ($noteId !== '' ? $noteId : md5(json_encode($note)));
                        // normalized to the same shape as a getGuestNotes note, minus the date/author it doesn't have
                        $notes[$key] ??= ['guestNoteID' => $noteId, 'guestNote' => (string) ($note['note'] ?? ''), 'userName' => '', 'dateCreated' => ''];
                    }
                }

                $payload['guestNotes'] = array_values($notes);
                $this->staging->setPayload((int) $row['id'], $payload);

                if (count((array) ($payload['guestRefs'] ?? [])) === 0) {
                    $this->staging->setMeta('guestNotesUnpaired', (int) $this->staging->getMeta('guestNotesUnpaired', '0') + 1);
                }

                $this->staging->setMeta('guestNotesCursor', (int) $row['id']);
                $this->staging->setMeta('guestNotesDone', (int) $this->staging->getMeta('guestNotesDone', '0') + 1);
            }
        }

        return false;
    }
}
