<?php
namespace HHK\CrmExport\Neon;

use HHK\Integrations\GuzzleAPILogger;
use PDO;


/**
 * Facade for NeonCRM API interactions using GuzzleHttp client.
 * Provides methods for accounts, contacts, donations, households, and more.
 * 
 * @author Will Ireland <wireland@nonprofitsoftwarecorp.org>
 *
 */
class NeonWebService {

    private const API_VERSION = '2.11';
    public const LOG_SERVICE_NAME = 'NeonCRM';

    private const BASE_URI = 'https://api.neoncrm.com/v2/';

    private PDO $dbh;

    private \GuzzleHttp\Client $client;

    public function __construct(PDO $dbh, string $orgId, string $apiKey) {
        $this->dbh = $dbh;
        $this->client = $this->buildClient($orgId, $apiKey);
    }

    /**
     * Set up Guzzle Client for NeonCRM API
     * @return \GuzzleHttp\Client
     */
    protected function buildClient(string $orgId, string $apiKey):\GuzzleHttp\Client{
        return new \GuzzleHttp\Client([
            "base_uri"=>self::BASE_URI,
            "handler" => GuzzleAPILogger::createStack($this->dbh, self::LOG_SERVICE_NAME),
            "headers"=> [
                "Accept" => "application/json",
                "Authorization" => "Basic " . base64_encode($orgId . ":" . $apiKey),
                "NEON-API-VERSION" => self::API_VERSION
            ]
        ]);
    }

    // -------------------------------------------------------------------------
    // Account endpoints
    // -------------------------------------------------------------------------

    /**
     * Retrieve a list of accounts.
     *
     * @param array $params Optional query params: currentPage, email, firstName, lastName, pageSize, userType
     */
    public function listAccounts(array $params = []): array {
        $response = $this->client->get('accounts', ['query' => $params]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Create a new account.
     *
     * @param array $account Account data
     */
    public function createAccount(array $account): array {
        $response = $this->client->post('accounts', ['json' => $account]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Associate an individual account with a company account.
     *
     * @param array $data LinkIndividualToCompany payload
     */
    public function linkIndividualToCompany(array $data): array {
        $response = $this->client->post('accounts/link', ['json' => $data]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Search for accounts using criteria.
     *
     * @param array $searchRequest SearchRequest payload
     */
    public function searchAccounts(array $searchRequest): array {
        $response = $this->client->post('accounts/search', ['json' => $searchRequest]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Retrieve available output columns for account search.
     *
     * @param string|null $searchKey Optional search key filter
     */
    public function getSearchOutputFields(?string $searchKey = null): array {
        $params = $searchKey !== null ? ['searchKey' => $searchKey] : [];
        $response = $this->client->get('accounts/search/outputFields', ['query' => $params]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Retrieve available search criteria fields for accounts.
     *
     * @param string|null $searchKey Optional search key filter
     */
    public function getSearchFields(?string $searchKey = null): array {
        $params = $searchKey !== null ? ['searchKey' => $searchKey] : [];
        $response = $this->client->get('accounts/search/searchFields', ['query' => $params]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Remove an individual account from a company.
     *
     * @param array $data IndividualToCompany payload
     */
    public function unlinkIndividualFromCompany(array $data): array {
        $response = $this->client->post('accounts/unlink', ['json' => $data]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Remove Windfall wealth screening data from all accounts.
     */
    public function removeAllWindfall(): array {
        $response = $this->client->delete('accounts/windfall');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Retrieve a specific account.
     *
     * @param string $id Account ID
     */
    public function getAccount(string $id): array {
        $response = $this->client->get("accounts/{$id}");
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Fully update an account.
     *
     * @param string $id Account ID
     * @param array  $account Account data
     */
    public function updateAccount(string $id, array $account): array {
        $response = $this->client->put("accounts/{$id}", ['json' => $account]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Partially update an account.
     *
     * @param string $id Account ID
     * @param array  $account Partial account data
     */
    public function patchAccount(string $id, array $account): array {
        $response = $this->client->patch("accounts/{$id}", ['json' => $account]);
        return json_decode($response->getBody()->getContents(), true);
    }

    // -------------------------------------------------------------------------
    // Account contact endpoints
    // -------------------------------------------------------------------------

    /**
     * Retrieve contacts associated with a company account.
     *
     * @param string $id          Account ID
     * @param int    $currentPage Page number (default 0)
     */
    public function getAccountContacts(string $id, int $currentPage = 0): array {
        $response = $this->client->get("accounts/{$id}/contacts", ['query' => ['currentPage' => $currentPage]]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Create a new contact for a company account.
     *
     * @param string $id      Account ID
     * @param array  $contact Contact data
     */
    public function createAccountContact(string $id, array $contact): array {
        $response = $this->client->post("accounts/{$id}/contacts", ['json' => $contact]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Retrieve a specific contact for an account.
     *
     * @param string $id        Account ID
     * @param string $contactId Contact ID
     */
    public function getAccountContact(string $id, string $contactId): array {
        $response = $this->client->get("accounts/{$id}/contacts/{$contactId}");
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Fully update a contact.
     *
     * @param string $id        Account ID
     * @param string $contactId Contact ID
     * @param array  $contact   Contact data
     */
    public function updateAccountContact(string $id, string $contactId, array $contact): array {
        $response = $this->client->put("accounts/{$id}/contacts/{$contactId}", ['json' => $contact]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Remove a contact from an account.
     *
     * @param string $id        Account ID
     * @param string $contactId Contact ID
     */
    public function deleteAccountContact(string $id, string $contactId): array {
        $response = $this->client->delete("accounts/{$id}/contacts/{$contactId}");
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Partially update a contact.
     *
     * @param string $id        Account ID
     * @param string $contactId Contact ID
     * @param array  $contact   Partial contact data
     */
    public function patchAccountContact(string $id, string $contactId, array $contact): array {
        $response = $this->client->patch("accounts/{$id}/contacts/{$contactId}", ['json' => $contact]);
        return json_decode($response->getBody()->getContents(), true);
    }

    // -------------------------------------------------------------------------
    // Account history/activity endpoints
    // -------------------------------------------------------------------------

    /**
     * Retrieve donation history for an account.
     *
     * @param string $id     Account ID
     * @param array  $params Optional query params: currentPage, sortColumn (date|amount), sortDirection (DESC|ASC)
     */
    public function getAccountDonations(string $id, array $params = []): array {
        $response = $this->client->get("accounts/{$id}/donations", ['query' => $params]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Retrieve event registrations for an account.
     *
     * @param string $id     Account ID
     * @param array  $params Optional query params: currentPage, eventId, sortColumn, sortDirection
     */
    public function getAccountEventRegistrations(string $id, array $params = []): array {
        $response = $this->client->get("accounts/{$id}/eventRegistrations", ['query' => $params]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Retrieve membership history for an account.
     *
     * @param string $id     Account ID
     * @param array  $params Optional query params: currentPage, pageSize, sortColumn, sortDirection, isActive, primaryActiveMembership
     */
    public function getAccountMemberships(string $id, array $params = []): array {
        $response = $this->client->get("accounts/{$id}/memberships", ['query' => $params]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Retrieve order history for an account.
     *
     * @param string $id     Account ID
     * @param array  $params Optional query params: currentPage, pageSize, sortColumn, sortDirection, transactionTypes
     */
    public function getAccountOrders(string $id, array $params = []): array {
        $response = $this->client->get("accounts/{$id}/orders", ['query' => $params]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Retrieve pledges for an account.
     *
     * @param string $id     Account ID
     * @param array  $params Optional query params: currentPage, sortColumn (date|amount), sortDirection (DESC|ASC)
     */
    public function getAccountPledges(string $id, array $params = []): array {
        $response = $this->client->get("accounts/{$id}/pledges", ['query' => $params]);
        return json_decode($response->getBody()->getContents(), true);
    }

    // -------------------------------------------------------------------------
    // Account Windfall endpoints
    // -------------------------------------------------------------------------

    /**
     * Retrieve Windfall wealth screening data for an account.
     *
     * @param string $id Account ID
     */
    public function getAccountWindfall(string $id): array {
        $response = $this->client->get("accounts/{$id}/windfall");
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Add Windfall data to an account.
     *
     * @param string $id       Account ID
     * @param array  $windfall AccountWindfall payload
     */
    public function addAccountWindfall(string $id, array $windfall): array {
        $response = $this->client->post("accounts/{$id}/windfall", ['json' => $windfall]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Remove Windfall data from an account.
     *
     * @param string $id Account ID
     */
    public function deleteAccountWindfall(string $id): array {
        $response = $this->client->delete("accounts/{$id}/windfall");
        return json_decode($response->getBody()->getContents(), true);
    }

    // -------------------------------------------------------------------------
    // Donation endpoints
    // -------------------------------------------------------------------------

    /**
     * Search for donations, pledges, and pledge payments.
     *
     * @param array $searchRequest SearchRequest payload
     */
    public function searchDonations(array $searchRequest): array {
        $response = $this->client->post('donations/search', ['json' => $searchRequest]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Retrieve available output columns for donation search.
     *
     * @param string|null $searchKey Optional search key filter
     */
    public function getDonationSearchOutputFields(?string $searchKey = null): array {
        $params = $searchKey !== null ? ['searchKey' => $searchKey] : [];
        $response = $this->client->get('donations/search/outputFields', ['query' => $params]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Retrieve available search criteria fields for donations.
     *
     * @param string|null $searchKey Optional search key filter
     */
    public function getDonationSearchFields(?string $searchKey = null): array {
        $params = $searchKey !== null ? ['searchKey' => $searchKey] : [];
        $response = $this->client->get('donations/search/searchFields', ['query' => $params]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Create a new donation.
     *
     * @param array $donation Donation payload
     */
    public function createDonation(array $donation): array {
        $response = $this->client->post('donations', ['json' => $donation]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Retrieve a specific donation.
     *
     * @param string $id Donation ID
     */
    public function getDonation(string $id): array {
        $response = $this->client->get("donations/{$id}");
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Fully update a donation.
     *
     * @param string $id       Donation ID
     * @param array  $donation Donation payload
     */
    public function updateDonation(string $id, array $donation): array {
        $response = $this->client->put("donations/{$id}", ['json' => $donation]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Partially update a donation.
     *
     * @param string $id       Donation ID
     * @param array  $donation Partial donation payload
     */
    public function patchDonation(string $id, array $donation): array {
        $response = $this->client->patch("donations/{$id}", ['json' => $donation]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Delete a donation.
     *
     * @param string $id Donation ID
     */
    public function deleteDonation(string $id): array {
        $response = $this->client->delete("donations/{$id}");
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Add a payment to a donation.
     *
     * @param string $donationId Donation ID
     * @param array  $payment    Payment payload
     */
    public function addDonationPayment(string $donationId, array $payment): array {
        $response = $this->client->post("donations/{$donationId}/payments", ['json' => $payment]);
        return json_decode($response->getBody()->getContents(), true);
    }

    // -------------------------------------------------------------------------
    // Household endpoints
    // -------------------------------------------------------------------------

    /**
     * List households, optionally filtered by householdId or accountId.
     *
     * @param string|null $householdId Optional household ID filter
     * @param string|null $accountId   Optional account ID filter
     */
    public function listHouseholds(?string $householdId = null, ?string $accountId = null): array {
        $params = array_filter([
            'householdId' => $householdId,
            'accountId'   => $accountId,
        ], fn($v) => $v !== null);
        $response = $this->client->get('households/listHouseholds', ['query' => $params]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * List available relation types for households.
     *
     * @param string $relationTypeCategory Relation type category (e.g. INDIVIDUAL_INDIVIDUAL)
     */
    public function listHouseholdRelationTypes(string $relationTypeCategory = 'INDIVIDUAL_INDIVIDUAL'): array {
        $response = $this->client->get('households/listRelationTypes', ['query' => ['relationTypeCategory' => $relationTypeCategory]]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Create a new household.
     *
     * @param array $household HouseHoldDto payload (requires name and contacts)
     */
    public function createHousehold(array $household): array {
        $response = $this->client->post('households', ['json' => $household]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Update a household.
     *
     * @param string $id        Household ID
     * @param array  $household HouseHoldDto payload (requires name and contacts)
     */
    public function updateHousehold(string $id, array $household): array {
        $response = $this->client->put("households/{$id}", ['json' => $household]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Delete a household.
     *
     * @param string $id Household ID
     */
    public function deleteHousehold(string $id): array {
        $response = $this->client->delete("households/{$id}");
        return json_decode($response->getBody()->getContents(), true);
    }

}
