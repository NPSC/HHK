<?php
namespace Tests\Unit\Admin\Import\Cloudbeds;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use HHK\Admin\Import\Cloudbeds\CloudbedsClient;
use HHK\Admin\Import\Cloudbeds\CloudbedsConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

#[CoversClass(CloudbedsClient::class)]
class CloudbedsClientTest extends TestCase
{
    /** @var array<int, array{request: RequestInterface}> */
    protected array $history = [];
    protected array $sleeps = [];

    protected function client(array $responses, array $configExtra = []): CloudbedsClient
    {
        $this->history = [];
        $this->sleeps = [];

        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        $config = new CloudbedsConfig($configExtra + ['apiKey' => 'cbat_key', 'organizationId' => '777', 'propertyIds' => [42]]);
        $http = new Client(['handler' => $stack, 'base_uri' => 'https://api.cloudbeds.com/']);

        return new CloudbedsClient($config, $this->createStub(\PDO::class), $http, function (int $s) { $this->sleeps[] = $s; });
    }

    protected function json(array $data, int $status = 200, array $headers = []): Response
    {
        return new Response($status, $headers + ['Content-Type' => 'application/json'], json_encode($data));
    }

    protected function request(int $i): RequestInterface
    {
        return $this->history[$i]['request'];
    }

    public function testCallsAreAlwaysLoggedLikeOtherHhkGuzzleClients(): void
    {
        // GuzzleAPILogger::createStack() needs a working PDO to write the log row, which a stub can't provide,
        // so this only checks that the client is built with a real dbh and no opt-out, matching the other integrations
        $config = new CloudbedsConfig(['apiKey' => 'k', 'organizationId' => '1', 'propertyIds' => [1]]);
        $ref = new \ReflectionMethod(CloudbedsClient::class, '__construct');
        $params = $ref->getParameters();

        $this->assertSame('dbh', $params[1]->getName());
        $this->assertFalse($params[1]->isOptional(), 'every request must be logged, so a PDO is required, not opt-in');
        $this->assertSame(\PDO::class, $params[1]->getType()?->getName());
    }

    public function testApiKeyIsSentAsHeaderAndProfilesGetOrganization(): void
    {
        $c = $this->client([$this->json(['offset' => 0, 'limit' => 250, 'total' => 1, 'data' => [['id' => '1']]])]);

        $page = $c->getProfilesPage(0);

        $this->assertSame([['id' => '1']], $page['data']);
        $this->assertSame(1, $page['total']);
        $req = $this->request(0);
        $this->assertSame('/guest-profiles/v1/profiles', $req->getUri()->getPath());
        $this->assertSame('cbat_key', $req->getHeaderLine('x-api-key'));
        $this->assertSame('', $req->getHeaderLine('Authorization'));
        $this->assertSame('777', $req->getHeaderLine('X-Organization-Id'));
        parse_str($req->getUri()->getQuery(), $q);
        $this->assertSame('0', $q['offset']);
        $this->assertSame('250', $q['limit']);
        $this->assertSame('true', $q['includeTotal']);
        $this->assertArrayNotHasKey('filter', $q, 'no timeframe given, so profiles are not prefiltered');
    }

    public function testProfilesAreFilteredByCheckoutWhenATimeframeIsGiven(): void
    {
        $c = $this->client([$this->json(['data' => []])]);
        $c->getProfilesPage(0, 250, '2024-01-01', '2024-12-31');

        parse_str($this->request(0)->getUri()->getQuery(), $q);
        $this->assertSame('checkoutAt:greater_than_or_equal:2024-01-01;checkoutAt:less_than_or_equal:2024-12-31', $q['filter']);
    }

    public function testProfilesFilterCanBeOneSidedOnly(): void
    {
        $c = $this->client([$this->json(['data' => []])]);
        $c->getProfilesPage(0, 250, '2024-01-01', '');

        parse_str($this->request(0)->getUri()->getQuery(), $q);
        $this->assertSame('checkoutAt:greater_than_or_equal:2024-01-01', $q['filter']);
    }

    public function testAccessTokenIsSentAsBearer(): void
    {
        $c = $this->client([$this->json(['data' => []])], ['apiKey' => '', 'accessToken' => 'tok']);
        $c->getProfilesPage(0);

        $this->assertSame('Bearer tok', $this->request(0)->getHeaderLine('Authorization'));
        $this->assertSame('', $this->request(0)->getHeaderLine('x-api-key'));
    }

    public function testUnwrapsSingleElementListResponses(): void
    {
        $c = $this->client([$this->json([['offset' => 0, 'limit' => 250, 'total' => 2, 'data' => [['id' => 'a'], ['id' => 'b']]]])]);
        $page = $c->getProfilesPage(0);

        $this->assertCount(2, $page['data']);
        $this->assertSame(2, $page['total']);
    }

    public function testRetriesRateLimitingHonoringRetryAfter(): void
    {
        $c = $this->client([
            $this->json(['message' => 'slow down'], 429, ['Retry-After' => '3']),
            $this->json(['message' => 'oops'], 503),
            $this->json(['data' => [['id' => 'x']]]),
        ]);

        $page = $c->getProfilesPage(0);

        $this->assertSame([['id' => 'x']], $page['data']);
        $this->assertSame([3, 4], $this->sleeps, 'Retry-After first, then exponential backoff for the 2nd attempt');
        $this->assertCount(3, $this->history);
    }

    public function testGivesUpAfterMaxAttempts(): void
    {
        $c = $this->client(array_fill(0, 5, $this->json(['message' => 'still busy'], 429)));

        try {
            $c->getProfilesPage(0);
            $this->fail('expected exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('HTTP 429', $e->getMessage());
            $this->assertStringContainsString('still busy', $e->getMessage());
            $this->assertSame(429, $e->getCode());
        }
        $this->assertCount(5, $this->history);
    }

    public function testClientErrorsAreNotRetried(): void
    {
        $c = $this->client([$this->json(['message' => 'Profile not found'], 404)]);

        try {
            $c->getProfileCustomFields('nope');
            $this->fail('expected exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Profile not found', $e->getMessage());
        }
        $this->assertCount(1, $this->history);
        $this->assertSame([], $this->sleeps);
    }

    public function testProfileReservationsPaginateUntilAShortPage(): void
    {
        $full = array_map(fn($i) => ['id' => (string) $i], range(1, 250));
        $c = $this->client([$this->json(['data' => $full]), $this->json(['data' => [['id' => '251']]])]);

        $all = iterator_to_array($c->iterateProfileReservations('P1'), false);

        $this->assertCount(251, $all);
        $this->assertSame('/guest-profiles/v1/profiles/P1/reservations', $this->request(1)->getUri()->getPath());
        parse_str($this->request(1)->getUri()->getQuery(), $q);
        $this->assertSame('250', $q['offset']);
    }

    public function testReservationsPageUsesPropertyAndCustomFields(): void
    {
        $c = $this->client([$this->json(['success' => true, 'data' => [['reservationID' => '9']], 'total' => 1])]);
        $page = $c->getReservationsPage('42', 3);

        $this->assertSame(1, $page['total']);
        parse_str($this->request(0)->getUri()->getQuery(), $q);
        $this->assertSame('42', $q['propertyID']);
        $this->assertSame('true', $q['includeCustomFields']);
        $this->assertSame('true', $q['includeGuestsDetails'], 'the guest list is how PMS guest ids are found');
        $this->assertSame('3', $q['pageNumber']);
        $this->assertSame('100', $q['pageSize']);
    }

    public function testReservationsPageThrowsOnUnsuccessfulResponse(): void
    {
        $c = $this->client([$this->json(['success' => false, 'message' => 'bad property'])]);
        $this->expectExceptionMessage('bad property');
        $c->getReservationsPage('42', 1);
    }

    public function testCustomFieldDefinitionsAreFetchedForTheProperty(): void
    {
        $c = $this->client([$this->json(['success' => true, 'data' => [['name' => 'Hospital', 'shortcode' => 'hosp', 'applyTo' => 'reservation']]])]);
        $defs = $c->getCustomFieldDefinitions('42');

        $this->assertSame('hosp', $defs[0]['shortcode']);
        $this->assertSame('/api/v1.3/getCustomFields', $this->request(0)->getUri()->getPath());
        parse_str($this->request(0)->getUri()->getQuery(), $q);
        $this->assertSame('42', $q['propertyID']);
    }

    public function testCustomFieldDefinitionsDoNotRetry(): void
    {
        $c = $this->client([$this->json(['message' => 'busy'], 503), $this->json(['data' => []])]);

        try {
            $c->getCustomFieldDefinitions('42');
            $this->fail('expected exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('HTTP 503', $e->getMessage());
        }
        $this->assertCount(1, $this->history, 'the config page must not wait on retries');
        $this->assertSame([], $this->sleeps);
    }

    public function testGuestListFlattensTheGuestIdKeyedMapAndSendsTheFilters(): void
    {
        $c = $this->client([$this->json(['success' => true, 'data' => [
            'G1' => ['reservationID' => 'R1', 'status' => 'checked_out', 'guestFirstName' => 'Jane'],
            'G2' => ['reservationID' => 'R1', 'guestID' => 'G2-explicit', 'status' => 'checked_out'],
        ], 'total' => 2])]);

        $page = $c->getGuestListPage('42', 2, '2024-01-01', '2024-12-31', ['checked_out', 'checked_in']);

        $this->assertSame(2, $page['total']);
        $this->assertSame('G1', $page['data'][0]['guestID'], 'the map key is used when the entry has no guestID of its own');
        $this->assertSame('G2-explicit', $page['data'][1]['guestID'], 'an explicit guestID in the entry is kept');

        $req = $this->request(0);
        $this->assertSame('/api/v1.3/getGuestList', $req->getUri()->getPath());
        parse_str($req->getUri()->getQuery(), $q);
        $this->assertSame('42', $q['propertyIDs']);
        $this->assertSame('checked_out,checked_in', $q['status']);
        $this->assertSame('2024-01-01', $q['checkOutFrom']);
        $this->assertSame('2024-12-31', $q['checkOutTo']);
        $this->assertSame('2', $q['pageNumber']);
        $this->assertSame('true', $q['includeGuestInfo']);
    }

    public function testGuestListOmitsBlankDateBounds(): void
    {
        $c = $this->client([$this->json(['success' => true, 'data' => []])]);
        $c->getGuestListPage('42', 1, '', '', ['checked_out']);

        parse_str($this->request(0)->getUri()->getQuery(), $q);
        $this->assertArrayNotHasKey('checkOutFrom', $q);
        $this->assertArrayNotHasKey('checkOutTo', $q);
    }

    public function testGuestListThrowsOnUnsuccessfulResponse(): void
    {
        $c = $this->client([$this->json(['success' => false, 'message' => 'bad status filter'])]);
        $this->expectExceptionMessage('bad status filter');
        $c->getGuestListPage('42', 1, '', '', ['checked_out']);
    }

    public function testGuestNotesAreFetchedForTheGuestAtTheProperty(): void
    {
        $c = $this->client([$this->json(['success' => true, 'data' => [['guestNoteID' => '5', 'userName' => 'Jane', 'dateCreated' => '2024-03-01 10:00:00', 'guestNote' => 'Allergic to nuts']]])]);

        $notes = $c->getGuestNotes('42', '777');

        $this->assertSame('Allergic to nuts', $notes[0]['guestNote']);
        $this->assertSame('/api/v1.3/getGuestNotes', $this->request(0)->getUri()->getPath());
        parse_str($this->request(0)->getUri()->getQuery(), $q);
        $this->assertSame('42', $q['propertyID']);
        $this->assertSame('777', $q['guestID']);
    }

    public function testGuestNotesOfAnUnknownGuestAreEmpty(): void
    {
        $c = $this->client([
            $this->json(['message' => 'Guest not found'], 404),
            $this->json(['success' => false, 'message' => 'Guest does not exist']),
        ]);

        $this->assertSame([], $c->getGuestNotes('42', '1'));
        $this->assertSame([], $c->getGuestNotes('42', '2'));
    }

    public function testGuestNotesFailuresThatAreNotAMissingGuestAreNotHidden(): void
    {
        $c = $this->client([$this->json(['message' => 'Missing scope read:guest'], 403), $this->json(['success' => false, 'message' => 'Rate limited by upstream'])]);

        try {
            $c->getGuestNotes('42', '1');
            $this->fail('expected exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('read:guest', $e->getMessage());
        }

        $this->expectExceptionMessage('Rate limited by upstream');
        $c->getGuestNotes('42', '1');
    }

    public function testRoomNamesAreFlattenedAndPaged(): void
    {
        $full = array_map(fn($i) => ['roomID' => (string) $i, 'roomName' => "Room $i"], range(1, 100));
        $c = $this->client([
            $this->json(['success' => true, 'data' => [['propertyID' => '42', 'rooms' => $full]]]),
            $this->json(['success' => true, 'data' => [['propertyID' => '42', 'rooms' => [['roomID' => '101', 'roomName' => 'Room 101'], ['roomID' => '102', 'roomName' => 'Room 1']]]]]),
        ]);

        $names = $c->getRoomNames('42');

        $this->assertCount(101, $names, 'duplicate names are listed once');
        $this->assertSame('Room 101', end($names));
        parse_str($this->request(1)->getUri()->getQuery(), $q);
        $this->assertSame('42', $q['propertyIDs']);
        $this->assertSame('2', $q['pageNumber']);
    }

    public function testPaymentMethodsAreReadFromTheMethodsList(): void
    {
        $c = $this->client([$this->json(['success' => true, 'data' => ['propertyID' => '42', 'methods' => [['method' => 'cash', 'code' => 'cash', 'name' => 'Cash'], ['method' => 'credit', 'code' => 'visa', 'name' => 'Credit Card']]]])]);

        $methods = $c->getPaymentMethods('42');

        $this->assertSame(['cash', 'credit'], array_column($methods, 'method'));
        $this->assertSame('/api/v1.3/getPaymentMethods', $this->request(0)->getUri()->getPath());
    }

    public function testFoliosUsePropertyHeaderAndFlatFilterParams(): void
    {
        $c = $this->client([$this->json([['id' => 'F1'], ['id' => 'F2']])]);
        $folios = $c->getFolios('42', '555');

        $this->assertCount(2, $folios);
        $req = $this->request(0);
        $this->assertSame('/accounting/v1.0/folios', $req->getUri()->getPath());
        $this->assertSame('42', $req->getHeaderLine('X-Property-ID'));
        parse_str($req->getUri()->getQuery(), $q);
        $this->assertSame('555', $q['sourceId']);
        $this->assertSame('RESERVATION', $q['sourceKind']);
    }

    public function testFolioTransactionsFlattenGroupsAndFollowPageTokens(): void
    {
        $c = $this->client([
            $this->json(['groups' => ['2024-01-01' => ['transactions' => [['id' => 't1'], ['id' => 't2']]], '2024-01-02' => ['transactions' => [['id' => 't3']]]], 'nextPageToken' => 'tok2']),
            $this->json(['groups' => ['2024-01-03' => ['transactions' => [['id' => 't4']]]]]),
        ]);

        $tx = $c->getFolioTransactions('42', '555', '99');

        $this->assertSame(['t1', 't2', 't3', 't4'], array_column($tx, 'id'));

        $first = json_decode((string) $this->request(0)->getBody(), true);
        $this->assertSame(555, $first['sourceId']);
        $this->assertSame('RESERVATION', $first['sourceKind']);
        $this->assertSame(99, $first['folioId']);
        $this->assertTrue($first['posted']);
        $this->assertArrayNotHasKey('pageToken', $first);

        $second = json_decode((string) $this->request(1)->getBody(), true);
        $this->assertSame('tok2', $second['pageToken']);
        $this->assertSame('POST', $this->request(1)->getMethod());
        $this->assertSame('42', $this->request(1)->getHeaderLine('X-Property-ID'));
    }
}
