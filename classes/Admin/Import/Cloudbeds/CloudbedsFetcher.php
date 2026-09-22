<?php
namespace HHK\Admin\Import\Cloudbeds;

/**
 * Pulls data from Cloudbeds into the staging table, in resumable steps that each stay within a time budget so
 * they can be driven from repeated web requests:
 *
 *  1. guestList          which reservations count as a stay, from the PMS getGuestList endpoint (see CloudbedsConfig::getStayedFrom() etc).
 *                        Seeds a reservation row per qualifying reservation; nothing else ever creates one, so only reservations
 *                        getGuestList reports as stayed (in the configured timeframe) are ever staged or imported.
 *  2. profiles           every (non merged) guest profile
 *  3. profileDetails     each seeded reservation is matched up with its guest profiles, and a profile's custom fields are fetched
 *                        only once it has at least one (a profile with none is never imported, see CloudbedsImport::importProfile())
 *  4. reservationFields  reservation custom fields, from the PMS API. Also pairs profiles with their PMS guest ids where Cloudbeds allows it (see CloudbedsGuestMatcher).
 *  5. guestNotes         the notes on each guest by PMS guest id, from the PMS API (skipped when guest notes aren't imported)
 *  6. folios             folios and their transactions, for reservations that became visits
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class CloudbedsFetcher {

    public const STEPS = ['guestList', 'profiles', 'profileDetails', 'reservationFields', 'guestNotes', 'folios'];

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
                'profiles' => $this->fetchProfiles($deadline),
                'profileDetails' => $this->fetchProfileDetails($deadline),
                'reservationFields' => $this->fetchReservationFields($deadline),
                'guestNotes' => $this->fetchGuestNotes($deadline),
                'folios' => $this->fetchFolios($deadline),
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
            'profiles' => 'Fetching profiles: ' . array_sum($counts[CloudbedsStaging::PROFILE]) . ' fetched',
            'profileDetails' => 'Fetching profile custom fields and reservations: ' . $this->staging->countUnfetched(CloudbedsStaging::PROFILE) . ' profiles left, ' . array_sum($counts[CloudbedsStaging::RESERVATION]) . ' qualifying reservations matched',
            'reservationFields' => 'Fetching reservation custom fields',
            'guestNotes' => 'Fetching guest notes: ' . $this->staging->getMeta('guestNotesDone', '0') . ' guests done, ' . $this->staging->getMeta('guestNotesUnpaired', '0') . ' without a Cloudbeds guest id (no notes)',
            'folios' => 'Fetching folios: ' . $this->staging->countUnfetched(CloudbedsStaging::RESERVATION) . ' reservations left, ' . array_sum($counts[CloudbedsStaging::FOLIO]) . ' folios found',
            default => '',
        };
    }

    /**
     * Seed a reservation row for every reservation getGuestList reports as stayed (per the configured timeframe and statuses).
     * profileDetails() only ever enriches a reservation that already exists here; it never creates one, so this is what decides
     * which reservations (and, transitively, which guest profiles) end up imported.
     */
    protected function fetchGuestList(float $deadline): bool {
        $checkOutFrom = $this->config->getStayedFrom();
        $checkOutTo = $this->config->getStayedTo();
        $statuses = $this->config->getStayStatuses();

        foreach ($this->config->getPropertyIds() as $propertyId) {
            if ($this->staging->getMeta("guestListDone:$propertyId") === '1') {
                continue;
            }

            while (microtime(true) < $deadline) {
                $pageNumber = (int) $this->staging->getMeta("guestListPage:$propertyId", '1');
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
                        $staged['guestListGuests'][] = ['guestId' => $guestId, 'isMainGuest' => !empty($entry['isMainGuest'])];
                    }

                    $this->staging->upsert(CloudbedsStaging::RESERVATION, $reservationId, $staged, '', $propertyId);
                }

                if (count($page['data']) < CloudbedsClient::PMS_PAGE_SIZE || $pageNumber * CloudbedsClient::PMS_PAGE_SIZE >= $page['total']) {
                    $this->staging->setMeta("guestListDone:$propertyId", '1');
                    break;
                }

                $this->staging->setMeta("guestListPage:$propertyId", $pageNumber + 1);
            }

            if ($this->staging->getMeta("guestListDone:$propertyId") !== '1') {
                return false;
            }
        }

        return true;
    }

    protected function fetchProfiles(float $deadline): bool {
        $limit = CloudbedsClient::PROFILES_PAGE_SIZE;

        // prefilter by the same timeframe guestList uses, so this doesn't have to enumerate the whole account's guest
        // history just to later throw most of it away in profileDetails() - a big win when the account has years of
        // guests but the configured timeframe is narrow. Skipped for a currently checked-in guest (includeCurrentGuests),
        // since an in-progress stay has no final checkout date yet to filter on.
        $checkOutFrom = $this->config->includeCurrentGuests() ? '' : $this->config->getStayedFrom();
        $checkOutTo = $this->config->includeCurrentGuests() ? '' : $this->config->getStayedTo();

        while (microtime(true) < $deadline) {
            $offset = (int) $this->staging->getMeta('profileOffset', '0');
            $page = $this->client->getProfilesPage($offset, $limit, $checkOutFrom, $checkOutTo);

            foreach ($page['data'] as $profile) {
                // merged profiles are no longer active in Cloudbeds, their reservations belong to the surviving profile
                if (!empty($profile['isMerged']) || !isset($profile['id'])) {
                    continue;
                }
                $this->staging->upsert(CloudbedsStaging::PROFILE, (string) $profile['id'], $profile);
            }

            $offset += count($page['data']);
            $this->staging->setMeta('profileOffset', $offset);

            if (count($page['data']) < $limit || ($page['total'] !== null && $offset >= $page['total'])) {
                return true;
            }
        }

        return false;
    }

    protected function fetchProfileDetails(float $deadline): bool {
        $propertyIds = $this->config->getPropertyIds();

        while (microtime(true) < $deadline) {
            $rows = $this->staging->nextUnfetched(CloudbedsStaging::PROFILE, 10);
            if (count($rows) === 0) {
                return true;
            }

            foreach ($rows as $row) {
                $profileId = $row['cloudbedsId'];
                $payload = $row['payload'];
                $hasStay = false;

                foreach ($this->client->iterateProfileReservations($profileId) as $reservation) {
                    $propertyId = (string) ($reservation['property']['id'] ?? '');
                    if (!isset($reservation['id']) || !in_array($propertyId, $propertyIds, true)) {
                        continue;
                    }

                    $reservationId = (string) $reservation['id'];

                    // only a reservation the guest list step already staged (i.e. reported as stayed) is ever enriched or imported
                    $staged = $this->staging->getPayload(CloudbedsStaging::RESERVATION, $reservationId);
                    if ($staged === null) {
                        continue;
                    }

                    $staged['summary'] = $reservation;
                    if (!in_array($profileId, $staged['profileIds'], true)) {
                        $staged['profileIds'][] = $profileId;
                    }

                    $this->staging->upsert(CloudbedsStaging::RESERVATION, $reservationId, $staged, '', $propertyId);
                    $hasStay = true;
                }

                $payload['hasStay'] = $hasStay;
                // custom fields are only useful for profiles that will actually be imported
                $payload['customFields'] = $hasStay ? $this->client->getProfileCustomFields($profileId) : [];

                $this->staging->setPayload((int) $row['id'], $payload);
                $this->staging->markFetched((int) $row['id']);
            }
        }

        return false;
    }

    protected function fetchReservationFields(float $deadline): bool {
        foreach ($this->config->getPropertyIds() as $propertyId) {
            if ($this->staging->getMeta("resvFieldsDone:$propertyId") === '1') {
                continue;
            }

            while (microtime(true) < $deadline) {
                $pageNumber = (int) $this->staging->getMeta("resvPage:$propertyId", '1');
                $page = $this->client->getReservationsPage($propertyId, $pageNumber);

                foreach ($page['data'] as $item) {
                    $reservationId = (string) ($item['reservationID'] ?? '');
                    $staged = $reservationId !== '' ? $this->staging->getPayload(CloudbedsStaging::RESERVATION, $reservationId) : null;

                    if ($staged !== null) {
                        $staged['customFields'] = (array) ($item['customFields'] ?? []);
                        $staged['guestIds'] = CloudbedsGuestMatcher::match((array) ($staged['summary']['guests'] ?? []), (array) ($item['guestList'] ?? []), (string) ($item['profileID'] ?? ''), (string) ($item['guestID'] ?? ''));
                        $this->staging->upsert(CloudbedsStaging::RESERVATION, $reservationId, $staged, '', $propertyId);

                        foreach ($staged['guestIds'] as $profileId => $guestId) {
                            $this->rememberGuest((string) $profileId, (string) $guestId, $propertyId);
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
     * Note a profile's PMS guest id at a property, where its notes are
     */
    protected function rememberGuest(string $profileId, string $guestId, string $propertyId): void {
        $profile = $this->staging->getPayload(CloudbedsStaging::PROFILE, $profileId);
        if ($profile === null) {
            return;
        }

        foreach ((array) ($profile['guestRefs'] ?? []) as $ref) {
            if ($ref['guestId'] === $guestId && $ref['propertyId'] === $propertyId) {
                return;
            }
        }

        $profile['guestRefs'][] = ['guestId' => $guestId, 'propertyId' => $propertyId];
        $this->staging->upsert(CloudbedsStaging::PROFILE, $profileId, $profile);
    }

    /**
     * Each profile's notes, by the PMS guest ids paired with it in the reservation step. A profile with no PMS guest id has no notes fetched
     * (the profile id is not a guest id), and is counted so it can be reported. Walks the profiles in order, keeping a cursor.
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

    protected function fetchFolios(float $deadline): bool {
        while (microtime(true) < $deadline) {
            $rows = $this->staging->nextUnfetched(CloudbedsStaging::RESERVATION, 10);
            if (count($rows) === 0) {
                return true;
            }

            foreach ($rows as $row) {
                $payload = $row['payload'];
                $status = $this->config->mapStatus((string) ($payload['summary']['reservationStatus'] ?? ''));

                // folios are only imported for reservations that became visits
                if ($status !== null && $status['visit'] !== null) {
                    $reservationId = $row['cloudbedsId'];
                    $propertyId = (string) $payload['propertyId'];

                    foreach ($this->client->getFolios($propertyId, $reservationId) as $folio) {
                        if (!isset($folio['id'])) {
                            continue;
                        }
                        $folioId = (string) $folio['id'];
                        $this->staging->upsert(CloudbedsStaging::FOLIO, $folioId, [
                            'folio' => $folio,
                            'reservationId' => $reservationId,
                            'propertyId' => $propertyId,
                            'transactions' => $this->client->getFolioTransactions($propertyId, $reservationId, $folioId),
                        ], $reservationId, $propertyId);
                    }
                }

                $this->staging->markFetched((int) $row['id']);
            }
        }

        return false;
    }
}
