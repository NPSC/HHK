<?php
namespace Tests\Unit\Admin\Import\Cloudbeds;

use GuzzleHttp\ClientInterface;
use HHK\Admin\Import\Cloudbeds\CloudbedsClient;
use HHK\Admin\Import\Cloudbeds\CloudbedsConfig;
use HHK\Admin\Import\Cloudbeds\CloudbedsFetcher;
use HHK\Admin\Import\Cloudbeds\CloudbedsStaging;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The staging table, in memory
 */
class InMemoryStaging extends CloudbedsStaging {

    /** @var array<int, array> */
    public array $rows = [];
    public array $meta = [];

    public function ensureTables(): void {}

    public function getMeta(string $key, string $default = ''): string {
        return $this->meta[$key] ?? $default;
    }

    public function setMeta(string $key, string|int $value): void {
        $this->meta[$key] = (string) $value;
    }

    protected function find(string $type, string $cloudbedsId): ?int {
        foreach ($this->rows as $id => $row) {
            if ($row['entityType'] === $type && $row['cloudbedsId'] === $cloudbedsId) {
                return $id;
            }
        }
        return null;
    }

    public function upsert(string $type, string $cloudbedsId, array $payload, string $parentId = '', string $propertyId = ''): void {
        $id = $this->find($type, $cloudbedsId) ?? (count($this->rows) + 1);
        $this->rows[$id] = ($this->rows[$id] ?? ['id' => $id, 'entityType' => $type, 'cloudbedsId' => $cloudbedsId, 'status' => 'pending', 'fetchedAt' => null, 'hhkData' => null]) + [];
        $this->rows[$id]['payload'] = $payload;
        $this->rows[$id]['parentId'] = $parentId;
        $this->rows[$id]['propertyId'] = $propertyId;
    }

    public function getPayload(string $type, string $cloudbedsId): ?array {
        $id = $this->find($type, $cloudbedsId);
        return $id === null ? null : $this->rows[$id]['payload'];
    }

    public function setPayload(int $rowId, array $payload): void {
        $this->rows[$rowId]['payload'] = $payload;
    }

    public function nextUnfetched(string $type, int $limit): array {
        return array_slice(array_values(array_filter($this->rows, fn($r) => $r['entityType'] === $type && $r['fetchedAt'] === null && $r['status'] === 'pending')), 0, $limit);
    }

    public function nextProfilesAfter(int $afterId, int $limit): array {
        return array_slice(array_values(array_filter($this->rows, fn($r) => $r['entityType'] === 'profile' && $r['status'] === 'pending' && $r['id'] > $afterId)), 0, $limit);
    }

    public function markFetched(int $rowId): void {
        $this->rows[$rowId]['fetchedAt'] = 'now';
    }

    public function countUnfetched(string $type): int {
        return count($this->nextUnfetched($type, PHP_INT_MAX));
    }

    public function counts(): array {
        $counts = [];
        foreach (['profile', 'reservation', 'folio'] as $type) {
            $counts[$type] = ['pending' => count(array_filter($this->rows, fn($r) => $r['entityType'] === $type))];
        }
        return $counts;
    }

    public function eachPayload(string $type, callable $callback): void {
        foreach ($this->rows as $row) {
            if ($row['entityType'] === $type) {
                $callback($row['payload']);
            }
        }
    }

    /** @return array[] payloads of a type by cloudbeds id */
    public function payloads(string $type): array {
        $out = [];
        foreach ($this->rows as $row) {
            if ($row['entityType'] === $type) {
                $out[$row['cloudbedsId']] = $row['payload'];
            }
        }
        return $out;
    }
}

/**
 * Cloudbeds, from canned data. Records the calls it gets.
 */
class FakeCloudbeds extends CloudbedsClient {

    public array $calls = [];
    /** @var array<string, array> profile id => profile, for getProfileById */
    public array $profiles = [];
    public array $profileReservations = [];
    public array $pmsReservations = [];
    public array $guestListEntries = [];
    public array $notes = [];

    public function getProfileById(string $profileId): ?array {
        $this->calls[] = "profileById:$profileId";
        return $this->profiles[$profileId] ?? null;
    }

    public function getProfileCustomFields(string $profileId): array {
        $this->calls[] = "customFields:$profileId";
        return [['customFieldId' => '1', 'name' => 'Ethnicity', 'value' => "for $profileId"]];
    }

    public function iterateProfileReservations(string $profileId): \Generator {
        $this->calls[] = "reservations:$profileId";
        yield from $this->profileReservations[$profileId] ?? [];
    }

    public function getGuestListPage(string $propertyId, int $pageNumber, string $checkOutFrom, string $checkOutTo, array $statuses): array {
        $this->calls[] = "guestList:$propertyId:$pageNumber:" . implode(',', $statuses) . ($checkOutFrom !== '' || $checkOutTo !== '' ? ":$checkOutFrom..$checkOutTo" : '');

        if ($pageNumber > 1) {
            return ['data' => [], 'total' => 0];
        }

        $entries = array_values(array_filter($this->guestListEntries[$propertyId] ?? [], fn($e) => in_array($e['status'], $statuses, true)));
        return ['data' => $entries, 'total' => count($entries)];
    }

    public function getReservationsPage(string $propertyId, int $pageNumber, string $checkOutFrom = '', string $checkOutTo = ''): array {
        $this->calls[] = "reservationFields:$propertyId:$pageNumber" . ($checkOutFrom !== '' || $checkOutTo !== '' ? ":$checkOutFrom..$checkOutTo" : '');
        $data = $this->pmsReservations[$propertyId] ?? [];
        return ['data' => $data, 'total' => count($data)];
    }

    public function getGuestNotes(string $propertyId, string $guestId): array {
        $this->calls[] = "notes:$propertyId:$guestId";
        return $this->notes["$propertyId:$guestId"] ?? [];
    }
}

#[CoversClass(CloudbedsFetcher::class)]
class CloudbedsFetcherTest extends TestCase
{
    protected function fetch(array $configExtra = []): array
    {
        $config = new CloudbedsConfig($configExtra + ['apiKey' => 'k', 'organizationId' => '1', 'propertyIds' => [42]]);
        $staging = new InMemoryStaging($this->createStub(\PDO::class));
        $client = new FakeCloudbeds($config, $this->createStub(\PDO::class), $this->createStub(ClientInterface::class));

        $client->profiles = [
            'P1' => ['id' => 'P1', 'firstName' => 'Jane'],
            'P2' => ['id' => 'P2', 'firstName' => 'Sam'],
            'P3' => ['id' => 'P3', 'firstName' => 'Merged', 'isMerged' => true],
            'P4' => ['id' => 'P4', 'firstName' => 'Loner'],
            'P5' => ['id' => 'P5', 'firstName' => 'Extra1'],
            'P6' => ['id' => 'P6', 'firstName' => 'Extra2'],
            'P8' => ['id' => 'P8', 'firstName' => 'CurrentlyStaying'],
        ];

        $r1Guests = [['id' => 'P1', 'firstName' => 'Jane', 'lastName' => 'Doe', 'isMainGuest' => true], ['id' => 'P2', 'firstName' => 'Sam', 'lastName' => 'Lee']];
        // P4 (no reservations of its own) and P3 (merged) are also on the Guest Profiles API's guest list for R3, to
        // exercise "discovered but never actually confirms a stay" without disturbing the R1/R3 guest id matching below
        $r3Guests = [['id' => 'P1', 'isMainGuest' => true], ['id' => 'P5'], ['id' => 'P6'], ['id' => 'P4'], ['id' => 'P3']];
        // the Guest Profiles API's own "id" is a different id than the PMS reservation id - matching must go through
        // "externalId" instead (confirmed against live data), so these two are deliberately given different values
        $resv = fn(string $id, string $property, string $status, array $guests = []) => ['id' => "gp-$id", 'externalId' => $id, 'property' => ['id' => $property], 'reservationStatus' => $status, 'guests' => $guests];

        $client->profileReservations = [
            'P1' => [$resv('R1', '42', 'checked_out', $r1Guests), $resv('R3', '42', 'checked_out', $r3Guests), $resv('R9', '99', 'checked_out')],
            'P2' => [$resv('R1', '42', 'checked_out', $r1Guests), $resv('R2', '42', 'canceled', [$r1Guests[1]])],
            'P5' => [$resv('R3', '42', 'checked_out', $r3Guests)],
            'P6' => [$resv('R3', '42', 'checked_out', $r3Guests)],
            'P8' => [$resv('R6', '42', 'checked_in', [['id' => 'P8', 'isMainGuest' => true]])],
            // P4 has no reservations at all, so it never confirms a stay even though it's on R3's guest list
            // P3 is merged - its reservations are never queried
        ];

        // what getGuestList reports as a stay. R2 (canceled) and R9 (a property that is not configured) are never
        // reported, so they never get seeded, regardless of what the Guest Profiles API's own reservation summaries say.
        $client->guestListEntries['42'] = [
            // getGuestList's own guestNotes for G1: note '1' duplicates one getGuestNotes also returns (with a different,
            // wrong-looking text, to prove the getGuestNotes version wins) and note '10' is exclusive to getGuestList
            // (getGuestNotes never returns it, see testGuestListNotesFillGapsGetGuestNotesMisses())
            ['reservationID' => 'R1', 'guestID' => 'G1', 'status' => 'checked_out', 'isMainGuest' => true, 'guestNotes' => [['ID' => '1', 'note' => 'stale duplicate, must be ignored'], ['ID' => '10', 'note' => 'only getGuestList has this one']]],
            ['reservationID' => 'R1', 'guestID' => 'G2', 'status' => 'checked_out', 'isMainGuest' => false],
            ['reservationID' => 'R3', 'guestID' => 'G1', 'status' => 'checked_out', 'isMainGuest' => true],
            ['reservationID' => 'R3', 'guestID' => 'G5', 'status' => 'checked_out', 'isMainGuest' => false],
            ['reservationID' => 'R3', 'guestID' => 'G6', 'status' => 'checked_out', 'isMainGuest' => false],
            ['reservationID' => 'R6', 'guestID' => 'G8', 'status' => 'checked_in', 'isMainGuest' => true],
        ];
        // the source of the profile ids that seed the profiles step: only R1 and R3's main guest (P1) is a real seed here,
        // since R6's main guest (P8) is only ever seeded once R6 itself is staged (i.e. includeCurrentGuests). R2 and R404
        // are reservations the PMS knows about that guestList never staged, so they must never seed a profile either.
        $client->pmsReservations['42'] = [
            ['reservationID' => 'R1', 'guestID' => 'G1', 'profileID' => 'P1', 'guestList' => ['G1' => ['guestFirstName' => 'Jane', 'guestLastName' => 'Doe'], 'G2' => ['guestFirstName' => 'Sam', 'guestLastName' => 'Lee']],
                'customFields' => [['customFieldID' => '7', 'shortcode' => 'hosp', 'customFieldName' => 'Hospital', 'customFieldValue' => 'Mercy']]],
            ['reservationID' => 'R2', 'guestID' => 'G2', 'profileID' => 'P2', 'guestList' => ['G2' => ['guestFirstName' => 'Sam', 'guestLastName' => 'Lee']], 'customFields' => []],
            ['reservationID' => 'R3', 'guestID' => 'G1', 'profileID' => 'P1', 'guestList' => ['G1' => [], 'G5' => [], 'G6' => []], 'customFields' => []],
            ['reservationID' => 'R6', 'guestID' => 'G8', 'profileID' => 'P8', 'guestList' => ['G8' => []], 'customFields' => []],
            ['reservationID' => 'R404', 'guestID' => 'X', 'profileID' => 'PX', 'guestList' => [], 'customFields' => []],
        ];
        $client->notes = [
            '42:G1' => [['guestNoteID' => '1', 'userName' => 'Jane', 'dateCreated' => '2024-01-02 10:00:00', 'guestNote' => 'first'], ['guestNoteID' => '2', 'userName' => 'Bo', 'dateCreated' => '2024-02-03 09:00:00', 'guestNote' => 'second']],
            '42:G5' => [['guestNoteID' => '5', 'guestNote' => 'must not be fetched, G5 is not paired to a profile']],
            '42:G2' => [['guestNoteID' => '3', 'userName' => 'Bo', 'dateCreated' => '2024-03-04 09:00:00', 'guestNote' => 'third']],
            // the notes of whoever has this as a guest id, which must not be picked up by profile id
            '42:P1' => [['guestNoteID' => '99', 'guestNote' => 'someone else']],
        ];

        $result = (new CloudbedsFetcher($client, $staging, $config))->run(20);

        return [$result, $staging, $client];
    }

    public function testGuestListIsTheFirstStep(): void
    {
        $this->assertSame('guestList', CloudbedsFetcher::STEPS[0]);
    }

    public function testFetchesEverythingInOrderAndCompletes(): void
    {
        [$result, $staging] = $this->fetch();

        $this->assertTrue($result['complete']);
        $this->assertSame('complete', $staging->getMeta('fetchStep'));

        $profiles = $staging->payloads('profile');
        $this->assertEqualsCanonicalizing(['P1', 'P2', 'P3', 'P4', 'P5', 'P6'], array_keys($profiles),
            'only profiles connected to a qualifying reservation are ever fetched - P8 (checked-in only) is excluded by default and never even discovered');
        $this->assertSame('Ethnicity', $profiles['P1']['customFields'][0]['name']);
        $this->assertSame('Jane', $profiles['P1']['firstName']);

        $reservations = $staging->payloads('reservation');
        $this->assertSame(['R1', 'R3'], array_keys($reservations), 'only reservations getGuestList reports as a stay are ever staged: R2 is canceled, R9 is at a property that is not configured, R6 is checked-in only (not counted by default)');
        $this->assertSame(['P1', 'P2'], $reservations['R1']['profileIds'], 'a reservation is staged once with all its confirmed guests');
        $this->assertSame(['P1', 'P5', 'P6'], $reservations['R3']['profileIds'], 'P4 and P3 are on the Guest Profiles API guest list but never confirm the stay themselves (no reservations / merged)');
        $this->assertSame('hosp', $reservations['R1']['customFields'][0]['shortcode']);
    }

    public function testFoliosAreNeverFetched(): void
    {
        [, $staging, $client] = $this->fetch();

        $this->assertSame([], $staging->payloads('folio'), 'the folios fetch step was intentionally removed');
        $this->assertSame([], array_filter($client->calls, fn($c) => str_starts_with($c, 'folios:')));
    }

    public function testTheWholeAccountsProfileHistoryIsNeverEnumerated(): void
    {
        // there is no bulk "list all profiles" call left at all - every profile fetch is scoped to one id
        [, , $client] = $this->fetch();
        $this->assertFalse(method_exists($client, 'getProfilesPage'));
        $this->assertEqualsCanonicalizing(['P1', 'P2', 'P3', 'P4', 'P5', 'P6'], array_unique(array_map(
            fn($c) => explode(':', $c)[1],
            array_values(array_filter($client->calls, fn($c) => str_starts_with($c, 'profileById:')))
        )));
    }

    public function testProfilesWithNoQualifyingStayAreNotImported(): void
    {
        [, $staging, $client] = $this->fetch();

        $profiles = $staging->payloads('profile');

        $this->assertTrue($profiles['P1']['hasStay']);
        $this->assertContains('customFields:P1', $client->calls);

        $this->assertFalse($profiles['P4']['hasStay'], 'discovered as a guest on R3, but has no reservations of its own');
        $this->assertSame([], $profiles['P4']['customFields'], 'the extra API call is skipped for a profile that will not be imported');
        $this->assertNotContains('customFields:P4', $client->calls);
    }

    public function testMergedProfilesAreNeverImportedAndTheirReservationsAreNeverQueried(): void
    {
        [, $staging, $client] = $this->fetch();

        $profiles = $staging->payloads('profile');

        $this->assertFalse($profiles['P3']['hasStay']);
        $this->assertTrue($profiles['P3']['isMerged']);
        $this->assertSame([], $profiles['P3']['customFields']);
        $this->assertNotContains('reservations:P3', $client->calls, "a merged profile's reservations belong to the surviving profile, so they are never even queried");
        $this->assertNotContains('customFields:P3', $client->calls);
    }

    public function testACheckedInOnlyStayIsExcludedByDefaultButIncludedWhenConfigured(): void
    {
        [, $staging] = $this->fetch();
        $this->assertArrayNotHasKey('P8', $staging->payloads('profile'), 'checked-in only, and includeCurrentGuests is off by default - P8 is never even discovered, let alone fetched');
        $this->assertArrayNotHasKey('R6', $staging->payloads('reservation'));

        [, $staging2, $client2] = $this->fetch(['includeCurrentGuests' => true]);
        $this->assertTrue($staging2->payloads('profile')['P8']['hasStay']);
        $this->assertArrayHasKey('R6', $staging2->payloads('reservation'));
        $this->assertNotEmpty(array_filter($client2->calls, fn($c) => str_starts_with($c, 'guestList:42:1:checked_out,checked_in')));
    }

    public function testGuestListIsFilteredByTheConfiguredDateRange(): void
    {
        [, , $client] = $this->fetch(['stayedFrom' => '2024-01-01', 'stayedTo' => '2024-12-31']);
        $call = current(array_filter($client->calls, fn($c) => str_starts_with($c, 'guestList:')));
        $this->assertStringContainsString('2024-01-01..2024-12-31', $call);
    }

    public function testReservationFieldsIsAlsoFilteredByTheSameDateRange(): void
    {
        [, , $client] = $this->fetch(['stayedFrom' => '2024-01-01', 'stayedTo' => '2024-12-31']);
        $call = current(array_filter($client->calls, fn($c) => str_starts_with($c, 'reservationFields:42:1')));
        $this->assertSame('reservationFields:42:1:2024-01-01..2024-12-31', $call);
    }

    public function testReservationFieldsStaysDateFilteredWhenCurrentGuestsAreIncluded(): void
    {
        // reservationFields never decides which reservations qualify itself (guestList already did, via the staged
        // lookup), so it has no reason to use a wider window than guestList's own - which stays date filtered here too
        [, , $client] = $this->fetch(['stayedFrom' => '2024-01-01', 'stayedTo' => '2024-12-31', 'includeCurrentGuests' => true]);
        $call = current(array_filter($client->calls, fn($c) => str_starts_with($c, 'reservationFields:42:1')));
        $this->assertSame('reservationFields:42:1:2024-01-01..2024-12-31', $call);
    }

    public function testFutureConfirmedReservationsAreFetchedRegardlessOfConfiguredStatuses(): void
    {
        // the normal fixture has no 'confirmed'-status guestList entries, so this only proves the second pass is made
        // with the right status and date range - see testFutureConfirmedReservationIsStaged() for the staged result
        [, , $client] = $this->fetch();
        $call = current(array_filter($client->calls, fn($c) => str_starts_with($c, 'guestList:42:1:confirmed:')));
        $this->assertNotFalse($call, 'a second guestList pass for Confirmed reservations is always made, not gated by includeCurrentGuests');
        $this->assertStringContainsString('confirmed:' . date('Y-m-d') . '..', $call, 'the future pass starts from today, per import specs.md');
    }

    public function testFutureConfirmedReservationIsStaged(): void
    {
        $config = new CloudbedsConfig(['apiKey' => 'k', 'organizationId' => '1', 'propertyIds' => [42]]);
        $staging = new InMemoryStaging($this->createStub(\PDO::class));
        $client = new FakeCloudbeds($config, $this->createStub(\PDO::class), $this->createStub(ClientInterface::class));

        $client->profiles = ['P9' => ['id' => 'P9', 'firstName' => 'Future']];
        $client->guestListEntries['42'] = [
            ['reservationID' => 'R7', 'guestID' => 'G7', 'status' => 'confirmed', 'isMainGuest' => true],
        ];
        $client->pmsReservations['42'] = [
            ['reservationID' => 'R7', 'guestID' => 'G7', 'profileID' => 'P9', 'guestList' => ['G7' => []], 'customFields' => []],
        ];
        $client->profileReservations['P9'] = [
            ['id' => 'gp-R7', 'externalId' => 'R7', 'property' => ['id' => '42'], 'reservationStatus' => 'confirmed', 'guests' => [['id' => 'P9', 'isMainGuest' => true]]],
        ];

        $result = (new CloudbedsFetcher($client, $staging, $config))->run(20);

        $this->assertTrue($result['complete']);
        $this->assertArrayHasKey('R7', $staging->payloads('reservation'), "a Confirmed (not yet checked in) reservation is staged even though it's not in the configured stay statuses");
        $this->assertTrue($staging->payloads('profile')['P9']['hasStay']);
    }

    public function testReservationsWithoutAnyImportedProfileAreCountedAsASafetyNet(): void
    {
        [, $staging] = $this->fetch();
        $this->assertSame(0, $staging->countReservationsWithoutProfile(), 'every staged reservation in the normal fixture has at least one guest profile');
    }

    public function testGuestNotesAreFetchedByPmsGuestIdNotProfileId(): void
    {
        [, $staging, $client] = $this->fetch();

        $profiles = $staging->payloads('profile');
        $reservations = $staging->payloads('reservation');

        $this->assertSame(['P1' => 'G1', 'P2' => 'G2'], $reservations['R1']['guestIds'], 'the main guest is paired by the reservation, the only other guest by elimination');
        $this->assertSame(['P1' => 'G1'], $reservations['R3']['guestIds'], 'more than one other guest on each side can not be told apart');
        $this->assertSame('G1', $profiles['P1']['guestRefs'][0]['guestId']);
        $this->assertSame([['guestId' => 'G2', 'propertyId' => '42', 'guestListNotes' => []]], $profiles['P2']['guestRefs'], 'G2 is found on two reservations and kept once');

        $this->assertEqualsCanonicalizing(['1', '2', '10'], array_column($profiles['P1']['guestNotes'], 'guestNoteID'), 'note 10 is only ever returned by getGuestList, see testGuestListNotesFillGapsGetGuestNotesMisses()');
        $this->assertSame(['3'], array_column($profiles['P2']['guestNotes'], 'guestNoteID'));

        $this->assertEqualsCanonicalizing(['notes:42:G1', 'notes:42:G2'], array_values(array_filter($client->calls, fn($c) => str_starts_with($c, 'notes:'))));
    }

    public function testGuestListNotesFillGapsGetGuestNotesMisses(): void
    {
        [, $staging] = $this->fetch();

        $notes = $staging->payloads('profile')['P1']['guestNotes'];
        $byId = [];
        foreach ($notes as $note) {
            $byId[$note['guestNoteID']] = $note;
        }

        $this->assertSame('first', $byId['1']['guestNote'], "getGuestNotes' version of a note it shares with getGuestList wins, not getGuestList's dateless duplicate");
        $this->assertSame('Jane', $byId['1']['userName']);

        $this->assertSame('only getGuestList has this one', $byId['10']['guestNote'], 'a note getGuestNotes never returns is still preserved, from getGuestList');
        $this->assertSame('', $byId['10']['userName'], 'getGuestList has no author or date for its notes');
        $this->assertSame('', $byId['10']['dateCreated']);
    }

    public function testProfilesWithoutAPmsGuestIdGetNoNotes(): void
    {
        [, $staging] = $this->fetch();

        $profiles = $staging->payloads('profile');

        foreach (['P3', 'P4', 'P5', 'P6'] as $unpaired) {
            $this->assertArrayNotHasKey('guestRefs', $profiles[$unpaired]);
            $this->assertSame([], $profiles[$unpaired]['guestNotes']);
        }
        $this->assertSame(6, (int) $staging->getMeta('guestNotesDone'), 'every profile is walked');
        $this->assertSame(4, (int) $staging->getMeta('guestNotesUnpaired'), 'and the ones without a guest id are counted');
    }

    public function testNoGuestNotesAreFetchedWhenTurnedOff(): void
    {
        [$result, $staging, $client] = $this->fetch(['importGuestNotes' => false]);

        $this->assertTrue($result['complete']);
        $this->assertSame([], array_filter($client->calls, fn($c) => str_starts_with($c, 'notes:')));
        $this->assertArrayNotHasKey('guestNotes', $staging->payloads('profile')['P1']);
    }
}
