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
    public array $profiles = [];
    public array $profileReservations = [];
    public array $pmsReservations = [];
    public array $guestListEntries = [];
    public array $notes = [];
    public array $folios = [];

    public function getProfilesPage(int $offset, int $limit = self::PROFILES_PAGE_SIZE): array {
        return ['data' => array_slice($this->profiles, $offset, $limit), 'total' => count($this->profiles)];
    }

    public function getProfileCustomFields(string $profileId): array {
        $this->calls[] = "customFields:$profileId";
        return [['customFieldId' => '1', 'name' => 'Ethnicity', 'value' => "for $profileId"]];
    }

    public function iterateProfileReservations(string $profileId): \Generator {
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

    public function getReservationsPage(string $propertyId, int $pageNumber): array {
        return ['data' => $this->pmsReservations[$propertyId] ?? [], 'total' => count($this->pmsReservations[$propertyId] ?? [])];
    }

    public function getGuestNotes(string $propertyId, string $guestId): array {
        $this->calls[] = "notes:$propertyId:$guestId";
        return $this->notes["$propertyId:$guestId"] ?? [];
    }

    public function getFolios(string $propertyId, string $reservationId): array {
        $this->calls[] = "folios:$propertyId:$reservationId";
        return $this->folios[$reservationId] ?? [];
    }

    public function getFolioTransactions(string $propertyId, string $reservationId, string $folioId): array {
        return [];
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
            ['id' => 'P1', 'firstName' => 'Jane'], ['id' => 'P2', 'firstName' => 'Sam'],
            ['id' => 'P3', 'firstName' => 'Merged', 'isMerged' => true],
            ['id' => 'P4', 'firstName' => 'Loner'], ['id' => 'P5', 'firstName' => 'Extra1'], ['id' => 'P6', 'firstName' => 'Extra2'],
            ['id' => 'P8', 'firstName' => 'CurrentlyStaying'],
        ];
        $guests = [['id' => 'P1', 'firstName' => 'Jane', 'lastName' => 'Doe', 'isMainGuest' => true], ['id' => 'P2', 'firstName' => 'Sam', 'lastName' => 'Lee']];
        $threeGuests = [['id' => 'P1', 'isMainGuest' => true], ['id' => 'P5'], ['id' => 'P6']];
        $resv = fn(string $id, string $property, string $status, array $guests = []) => ['id' => $id, 'property' => ['id' => $property], 'reservationStatus' => $status, 'guests' => $guests];
        $client->profileReservations = [
            'P1' => [$resv('R1', '42', 'checked_out', $guests), $resv('R9', '99', 'checked_out')],
            'P2' => [$resv('R1', '42', 'checked_out', $guests), $resv('R2', '42', 'canceled', [$guests[1]])],
            'P5' => [$resv('R3', '42', 'checked_out', $threeGuests)],
            'P6' => [$resv('R3', '42', 'checked_out', $threeGuests)],
            'P8' => [$resv('R6', '42', 'checked_in', [['id' => 'P8', 'isMainGuest' => true]])],
            // P4 has no reservations at all
        ];
        // what getGuestList reports as a stay. R2 (canceled) and R9 (a property that is not configured) are never reported, so they
        // never get seeded, regardless of what the Guest Profiles API's own reservation summaries say.
        $client->guestListEntries['42'] = [
            ['reservationID' => 'R1', 'guestID' => 'G1', 'status' => 'checked_out', 'isMainGuest' => true],
            ['reservationID' => 'R1', 'guestID' => 'G2', 'status' => 'checked_out', 'isMainGuest' => false],
            ['reservationID' => 'R3', 'guestID' => 'G1', 'status' => 'checked_out', 'isMainGuest' => true],
            ['reservationID' => 'R3', 'guestID' => 'G5', 'status' => 'checked_out', 'isMainGuest' => false],
            ['reservationID' => 'R3', 'guestID' => 'G6', 'status' => 'checked_out', 'isMainGuest' => false],
            ['reservationID' => 'R6', 'guestID' => 'G8', 'status' => 'checked_in', 'isMainGuest' => true],
        ];
        $client->pmsReservations['42'] = [
            ['reservationID' => 'R1', 'guestID' => 'G1', 'profileID' => 'P1', 'guestList' => ['G1' => ['guestFirstName' => 'Jane', 'guestLastName' => 'Doe'], 'G2' => ['guestFirstName' => 'Sam', 'guestLastName' => 'Lee']],
                'customFields' => [['customFieldID' => '7', 'shortcode' => 'hosp', 'customFieldName' => 'Hospital', 'customFieldValue' => 'Mercy']]],
            ['reservationID' => 'R2', 'guestID' => 'G2', 'profileID' => 'P2', 'guestList' => ['G2' => ['guestFirstName' => 'Sam', 'guestLastName' => 'Lee']], 'customFields' => []],
            ['reservationID' => 'R3', 'guestID' => 'G1', 'profileID' => 'P1', 'guestList' => ['G1' => [], 'G5' => [], 'G6' => []], 'customFields' => []],
            ['reservationID' => 'R404', 'guestID' => 'X', 'profileID' => 'PX', 'guestList' => [], 'customFields' => []],
        ];
        $client->notes = [
            '42:G1' => [['guestNoteID' => '1', 'userName' => 'Jane', 'dateCreated' => '2024-01-02 10:00:00', 'guestNote' => 'first'], ['guestNoteID' => '2', 'userName' => 'Bo', 'dateCreated' => '2024-02-03 09:00:00', 'guestNote' => 'second']],
            '42:G5' => [['guestNoteID' => '5', 'guestNote' => 'must not be fetched, G5 is not paired to a profile']],
            '42:G2' => [['guestNoteID' => '3', 'userName' => 'Bo', 'dateCreated' => '2024-03-04 09:00:00', 'guestNote' => 'third']],
            // the notes of whoever has this as a guest id, which must not be picked up by profile id
            '42:P1' => [['guestNoteID' => '99', 'guestNote' => 'someone else']],
        ];
        $client->folios = ['R1' => [['id' => 'F1']]];

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
        $this->assertSame(['P1', 'P2', 'P4', 'P5', 'P6', 'P8'], array_keys($profiles), 'merged profiles are not staged');
        $this->assertSame('Ethnicity', $profiles['P1']['customFields'][0]['name']);

        $reservations = $staging->payloads('reservation');
        $this->assertSame(['R1', 'R3'], array_keys($reservations), 'only reservations getGuestList reports as a stay are ever staged: R2 is canceled, R9 is at a property that is not configured, R6 is checked-in only (not counted by default)');
        $this->assertSame(['P1', 'P2'], $reservations['R1']['profileIds'], 'a reservation is staged once with all its guests');
        $this->assertSame('hosp', $reservations['R1']['customFields'][0]['shortcode']);

        $this->assertSame(['F1'], array_keys($staging->payloads('folio')), 'only reservations that became visits have folios');
    }

    public function testProfilesWithNoQualifyingStayAreNotImported(): void
    {
        [, $staging, $client] = $this->fetch();

        $profiles = $staging->payloads('profile');

        $this->assertTrue($profiles['P1']['hasStay']);
        $this->assertContains('customFields:P1', $client->calls);

        $this->assertFalse($profiles['P4']['hasStay'], 'no reservations at all');
        $this->assertSame([], $profiles['P4']['customFields'], 'the extra API call is skipped for a profile that will not be imported');
        $this->assertNotContains('customFields:P4', $client->calls);
    }

    public function testACheckedInOnlyStayIsExcludedByDefaultButIncludedWhenConfigured(): void
    {
        [, $staging] = $this->fetch();
        $this->assertFalse($staging->payloads('profile')['P8']['hasStay'], 'checked-in only, and includeCurrentGuests is off by default');
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

    public function testGuestNotesAreFetchedByPmsGuestIdNotProfileId(): void
    {
        [, $staging, $client] = $this->fetch();

        $profiles = $staging->payloads('profile');
        $reservations = $staging->payloads('reservation');

        $this->assertSame(['P1' => 'G1', 'P2' => 'G2'], $reservations['R1']['guestIds'], 'the main guest is paired by the reservation, the only other guest by elimination');
        $this->assertSame(['P1' => 'G1'], $reservations['R3']['guestIds'], 'two other guests on each side can not be told apart');
        $this->assertSame([['guestId' => 'G1', 'propertyId' => '42']], $profiles['P1']['guestRefs']);
        $this->assertSame([['guestId' => 'G2', 'propertyId' => '42']], $profiles['P2']['guestRefs'], 'G2 is found on two reservations and kept once');

        $this->assertSame(['1', '2'], array_column($profiles['P1']['guestNotes'], 'guestNoteID'));
        $this->assertSame(['3'], array_column($profiles['P2']['guestNotes'], 'guestNoteID'));

        $this->assertEqualsCanonicalizing(['notes:42:G1', 'notes:42:G2'], array_values(array_filter($client->calls, fn($c) => str_starts_with($c, 'notes:'))));
    }

    public function testProfilesWithoutAPmsGuestIdGetNoNotes(): void
    {
        [, $staging] = $this->fetch();

        $profiles = $staging->payloads('profile');

        foreach (['P4', 'P5', 'P6', 'P8'] as $unpaired) {
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
