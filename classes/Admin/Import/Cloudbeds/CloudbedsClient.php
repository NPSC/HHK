<?php
namespace HHK\Admin\Import\Cloudbeds;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use HHK\Integrations\GuzzleAPILogger;

/**
 * Thin client for the Cloudbeds APIs used by the importer:
 *  - Guest Profiles API v1 (/guest-profiles/v1): profiles, profile custom fields and profile reservations
 *  - PMS API v1.3 (/api/v1.3): reservation custom fields, guest notes, guest list, custom field definitions, rooms and payment methods
 *  - Accounting API v1.0 (/accounting/v1.0): folios and folio transactions
 *
 * API keys are sent in the x-api-key header, OAuth access tokens as a Bearer token.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class CloudbedsClient {

    public const PROFILES_PAGE_SIZE = 250;
    public const PMS_PAGE_SIZE = 100;
    public const TRANSACTIONS_PAGE_SIZE = 100;

    protected const MAX_ATTEMPTS = 5;

    protected ClientInterface $http;

    /** @var callable(int):void */
    protected $sleep;

    /**
     * @param CloudbedsConfig $config
     * @param \PDO $dbh every call is logged to the External API log, like the rest of HHK's Guzzle clients
     * @param ClientInterface|null $http Inject a client (e.g. with a mock handler) for testing
     * @param callable|null $sleep Called with the seconds to wait before a retry, defaults to sleep()
     */
    public function __construct(protected CloudbedsConfig $config, \PDO $dbh, ?ClientInterface $http = null, ?callable $sleep = null) {
        $this->sleep = $sleep ?? 'sleep';

        if ($http === null) {
            $http = new Client([
                'base_uri' => $config->getBaseUrl() . '/',
                'timeout' => 60,
                'http_errors' => true,
                'handler' => GuzzleAPILogger::createStack($dbh, 'Cloudbeds'),
            ]);
        }

        $this->http = $http;
    }

    /**
     * Send a request, retrying rate limited (429) and server error (5xx) responses with backoff.
     *
     * @param string $method
     * @param string $path relative to the base url
     * @param array $options Guzzle request options
     * @param array $headers extra headers
     * @param int $maxAttempts 1 to not retry
     * @throws \RuntimeException on a non retryable error, or after the last retry
     * @return array decoded JSON body
     */
    public function request(string $method, string $path, array $options = [], array $headers = [], int $maxAttempts = self::MAX_ATTEMPTS): array {

        $options['headers'] = array_merge(['Accept' => 'application/json'], $this->authHeaders(), $headers, $options['headers'] ?? []);

        for ($attempt = 1; ; $attempt++) {
            try {
                $response = $this->http->request($method, ltrim($path, '/'), $options);
                $body = (string) $response->getBody();
                $data = $body === '' ? [] : json_decode($body, true);

                if (!is_array($data)) {
                    throw new \RuntimeException("Cloudbeds returned an invalid JSON response for $method $path");
                }

                return $data;

            } catch (BadResponseException $e) {
                $status = $e->getResponse()->getStatusCode();
                $retryable = $status === 429 || ($status >= 500 && $status !== 501);

                if ($retryable && $attempt < $maxAttempts) {
                    $retryAfter = (int) $e->getResponse()->getHeaderLine('Retry-After');
                    ($this->sleep)($retryAfter > 0 ? min($retryAfter, 60) : 2 ** $attempt);
                    continue;
                }

                throw new \RuntimeException("Cloudbeds $method $path failed with HTTP $status: " . $this->errorMessage((string) $e->getResponse()->getBody()), $status, $e);

            } catch (ConnectException $e) {
                if ($attempt < $maxAttempts) {
                    ($this->sleep)(2 ** $attempt);
                    continue;
                }
                throw new \RuntimeException("Cloudbeds $method $path connection failed: " . $e->getMessage(), 0, $e);
            }
        }
    }

    protected function authHeaders(): array {
        if ($this->config->getAccessToken() !== '') {
            return ['Authorization' => 'Bearer ' . $this->config->getAccessToken()];
        }
        return ['x-api-key' => $this->config->getApiKey()];
    }

    protected function errorMessage(string $body): string {
        $data = json_decode($body, true);
        if (is_array($data)) {
            $msg = $data['message'] ?? $data['error'] ?? $data['detail'] ?? null;
            if (is_array($msg)) {
                $msg = json_encode($msg);
            }
            if ($msg) {
                return (string) $msg;
            }
        }
        return mb_substr($body, 0, 300);
    }

    /**
     * Endpoints return either the object or a single element list wrapping it, depending on the spec example
     */
    protected function unwrapPage(array $response): array {
        if (array_is_list($response) && isset($response[0]) && is_array($response[0]) && array_key_exists('data', $response[0])) {
            return $response[0];
        }
        return $response;
    }

    protected function profileHeaders(): array {
        return ['X-Organization-Id' => $this->config->getOrganizationId()];
    }

    /**
     * The custom fields defined on a property. Quick and without retries, this is used to fill in the configuration page.
     *
     * @param string $propertyId
     * @return array[] ["name", "shortcode", "applyTo" ("guest" or "reservation"), ...]
     */
    public function getCustomFieldDefinitions(string $propertyId): array {
        $resp = $this->request('GET', 'api/v1.3/getCustomFields', ['query' => ['propertyID' => $propertyId], 'timeout' => 10], [], 1);

        if (isset($resp['success']) && $resp['success'] === false) {
            throw new \RuntimeException('Cloudbeds getCustomFields failed: ' . ($resp['message'] ?? 'unknown error'));
        }

        return (array) ($resp['data'] ?? []);
    }

    /**
     * The notes on a guest
     *
     * @param string $propertyId
     * @param string $guestId the PMS guest id (not the guest profile id)
     * @return array[] ["guestNoteID", "userName", "dateCreated", "dateModified", "guestNote"]. Empty if Cloudbeds has no such guest.
     */
    public function getGuestNotes(string $propertyId, string $guestId): array {
        try {
            $resp = $this->request('GET', 'api/v1.3/getGuestNotes', ['query' => ['propertyID' => $propertyId, 'guestID' => $guestId]]);
        } catch (\RuntimeException $e) {
            if (in_array($e->getCode(), [400, 404, 422], true)) {
                return [];
            }
            throw $e;
        }

        if (isset($resp['success']) && $resp['success'] === false) {
            if (preg_match('/not found|invalid|does not exist|no such/i', (string) ($resp['message'] ?? ''))) {
                return [];
            }
            throw new \RuntimeException('Cloudbeds getGuestNotes failed: ' . ($resp['message'] ?? 'unknown error'));
        }

        return array_values(array_filter((array) ($resp['data'] ?? []), 'is_array'));
    }

    /**
     * The names of the property's rooms. Quick and without retries, this is used to fill in the configuration page.
     *
     * @param string $propertyId
     * @return string[]
     */
    public function getRoomNames(string $propertyId): array {
        $names = [];

        for ($pageNumber = 1; $pageNumber <= 50; $pageNumber++) {
            $resp = $this->request('GET', 'api/v1.3/getRooms', ['query' => ['propertyIDs' => $propertyId, 'pageNumber' => $pageNumber, 'pageSize' => self::PMS_PAGE_SIZE], 'timeout' => 10], [], 1);

            if (isset($resp['success']) && $resp['success'] === false) {
                throw new \RuntimeException('Cloudbeds getRooms failed: ' . ($resp['message'] ?? 'unknown error'));
            }

            $pageRooms = 0;
            foreach ((array) ($resp['data'] ?? []) as $property) {
                foreach ((array) ($property['rooms'] ?? []) as $room) {
                    $pageRooms++;
                    if (trim((string) ($room['roomName'] ?? '')) !== '') {
                        $names[] = trim((string) $room['roomName']);
                    }
                }
            }

            if ($pageRooms < self::PMS_PAGE_SIZE) {
                break;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * The payment methods the property accepts. Quick and without retries, this is used to fill in the configuration page.
     *
     * @param string $propertyId
     * @return array[] ["method", "code", "name"]
     */
    public function getPaymentMethods(string $propertyId): array {
        $resp = $this->request('GET', 'api/v1.3/getPaymentMethods', ['query' => ['propertyID' => $propertyId], 'timeout' => 10], [], 1);

        if (isset($resp['success']) && $resp['success'] === false) {
            throw new \RuntimeException('Cloudbeds getPaymentMethods failed: ' . ($resp['message'] ?? 'unknown error'));
        }

        return (array) ($resp['data']['methods'] ?? []);
    }

    /**
     * One page of the property's guests (one entry per guest per reservation), filtered by check-out date range and reservation status.
     * Cloudbeds keys this by PMS guest id; each entry also carries that id under "guestID" so it can be handled the same as other pages.
     *
     * @param string $propertyId
     * @param int $pageNumber starting at 1
     * @param string $checkOutFrom Y-m-d, blank for no lower bound
     * @param string $checkOutTo Y-m-d, blank for no upper bound
     * @param string[] $statuses reservation statuses to include, e.g. ['checked_out']
     * @return array{data: array[], total: int}
     */
    public function getGuestListPage(string $propertyId, int $pageNumber, string $checkOutFrom, string $checkOutTo, array $statuses): array {
        $query = [
            'propertyIDs' => $propertyId,
            'status' => implode(',', $statuses),
            'includeGuestInfo' => 'true',
            'pageNumber' => $pageNumber,
            'pageSize' => self::PMS_PAGE_SIZE,
        ];
        if ($checkOutFrom !== '') {
            $query['checkOutFrom'] = $checkOutFrom;
        }
        if ($checkOutTo !== '') {
            $query['checkOutTo'] = $checkOutTo;
        }

        $resp = $this->request('GET', 'api/v1.3/getGuestList', ['query' => $query]);

        if (isset($resp['success']) && $resp['success'] === false) {
            throw new \RuntimeException('Cloudbeds getGuestList failed: ' . ($resp['message'] ?? 'unknown error'));
        }

        $data = [];
        foreach ((array) ($resp['data'] ?? []) as $guestId => $entry) {
            if (is_array($entry)) {
                $entry['guestID'] = (string) ($entry['guestID'] ?? $guestId);
                $data[] = $entry;
            }
        }

        return ['data' => $data, 'total' => (int) ($resp['total'] ?? count($data))];
    }

    /**
     * One page of guest profiles
     *
     * @param int $offset
     * @param int $limit
     * @return array{data: array, total: ?int}
     */
    public function getProfilesPage(int $offset, int $limit = self::PROFILES_PAGE_SIZE, string $checkOutFrom = '', string $checkOutTo = ''): array {
        $query = ['offset' => $offset, 'limit' => $limit, 'includeTotal' => 'true', 'sort' => 'dateModified:asc'];

        // narrows the enumeration to profiles with a reservation checking out in this range, per the endpoint's own
        // checkinAt/checkoutAt filter. The exact server-side semantics (e.g. whether it matches ANY of a profile's
        // reservations, which is what this assumes, versus some aggregate value) are not documented and this has not
        // been verified against a live account - see CloudbedsFetcher::fetchProfiles().
        $filters = [];
        if ($checkOutFrom !== '') {
            $filters[] = "checkoutAt:greater_than_or_equal:$checkOutFrom";
        }
        if ($checkOutTo !== '') {
            $filters[] = "checkoutAt:less_than_or_equal:$checkOutTo";
        }
        if (count($filters) > 0) {
            $query['filter'] = implode(';', $filters);
        }

        $page = $this->unwrapPage($this->request('GET', 'guest-profiles/v1/profiles', [
            'query' => $query,
        ], $this->profileHeaders()));

        return ['data' => (array) ($page['data'] ?? []), 'total' => isset($page['total']) ? (int) $page['total'] : null];
    }

    /**
     * @param string $profileId
     * @return array[] custom fields as returned by the Guest Profiles API (customFieldId, name, label, value, ...)
     */
    public function getProfileCustomFields(string $profileId): array {
        $page = $this->unwrapPage($this->request('GET', 'guest-profiles/v1/profiles/' . rawurlencode($profileId) . '/custom-fields', [], $this->profileHeaders()));
        return (array) ($page['data'] ?? []);
    }

    /**
     * All reservations of a profile (main guest or additional guest)
     *
     * @param string $profileId
     * @return \Generator<array>
     */
    public function iterateProfileReservations(string $profileId): \Generator {
        $offset = 0;
        $limit = self::PROFILES_PAGE_SIZE;

        do {
            $page = $this->unwrapPage($this->request('GET', 'guest-profiles/v1/profiles/' . rawurlencode($profileId) . '/reservations', [
                'query' => ['offset' => $offset, 'limit' => $limit],
            ], $this->profileHeaders()));

            $rows = (array) ($page['data'] ?? []);
            foreach ($rows as $row) {
                yield $row;
            }

            $offset += $limit;
        } while (count($rows) >= $limit);
    }

    /**
     * One page of PMS reservations including their custom fields and guest lists (guests by PMS guest id)
     *
     * @param string $propertyId
     * @param int $pageNumber starting at 1
     * @return array{data: array, total: int}
     */
    public function getReservationsPage(string $propertyId, int $pageNumber): array {
        $resp = $this->request('GET', 'api/v1.3/getReservations', [
            'query' => [
                'propertyID' => $propertyId,
                'includeCustomFields' => 'true',
                'includeGuestsDetails' => 'true',
                'pageNumber' => $pageNumber,
                'pageSize' => self::PMS_PAGE_SIZE,
            ],
        ]);

        if (isset($resp['success']) && $resp['success'] === false) {
            throw new \RuntimeException('Cloudbeds getReservations failed: ' . ($resp['message'] ?? 'unknown error'));
        }

        return ['data' => (array) ($resp['data'] ?? []), 'total' => (int) ($resp['total'] ?? 0)];
    }

    /**
     * Folios of a reservation
     *
     * @param string $propertyId
     * @param string $reservationId
     * @return array[]
     */
    public function getFolios(string $propertyId, string $reservationId): array {
        $resp = $this->request('GET', 'accounting/v1.0/folios', [
            'query' => ['sourceId' => $reservationId, 'sourceKind' => 'RESERVATION'],
        ], ['X-Property-ID' => $propertyId]);

        return array_is_list($resp) ? $resp : (array) ($resp['data'] ?? []);
    }

    /**
     * Posted transactions on a folio, flattened out of the grouped response
     *
     * @param string $propertyId
     * @param string $reservationId
     * @param string $folioId
     * @return array[]
     */
    public function getFolioTransactions(string $propertyId, string $reservationId, string $folioId): array {
        $transactions = [];
        $pageToken = null;

        do {
            $body = [
                'sourceId' => (int) $reservationId,
                'sourceKind' => 'RESERVATION',
                'folioId' => (int) $folioId,
                'posted' => true,
                'includeVoided' => false,
                'limit' => self::TRANSACTIONS_PAGE_SIZE,
            ];
            if ($pageToken !== null) {
                $body['pageToken'] = $pageToken;
            }

            $resp = $this->request('POST', 'accounting/v1.0/folios/transactions', ['json' => $body], ['X-Property-ID' => $propertyId]);

            foreach ((array) ($resp['groups'] ?? []) as $group) {
                foreach ((array) ($group['transactions'] ?? []) as $transaction) {
                    $transactions[] = $transaction;
                }
            }

            $pageToken = $resp['nextPageToken'] ?? null;
        } while (!empty($pageToken));

        return $transactions;
    }
}
