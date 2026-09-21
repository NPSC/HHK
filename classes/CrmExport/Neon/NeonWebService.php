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

    public const API_VERSION = '2.11';
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
                "Authorization" => "Basic " . base64_encode("{$orgId}:{$apiKey}"),
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

    // -------------------------------------------------------------------------
    // Properties endpoints
    // -------------------------------------------------------------------------

    /**
     * Get a list of available activity statuses.
     */
    public function getActivityStatuses(): array {
        $response = $this->client->get('properties/activityStatuses');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get address types.
     */
    public function getAddressTypes(): array {
        $response = $this->client->get('properties/addressTypes');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get company types.
     */
    public function getCompanyTypes(): array {
        $response = $this->client->get('properties/companyTypes');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get countries.
     */
    public function getCountries(): array {
        $response = $this->client->get('properties/countries');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get the current authenticated system user.
     */
    public function getCurrentSystemUser(): array {
        $response = $this->client->get('properties/currentSystemUser');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get all event categories.
     */
    public function getEventCategories(): array {
        $response = $this->client->get('properties/eventCategories');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Create a new event category.
     *
     * @param array $category IdNamePair payload (name, status)
     */
    public function createEventCategory(array $category): array {
        $response = $this->client->post('properties/eventCategories', ['json' => $category]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get a specific event category.
     *
     * @param string $id Event category ID
     */
    public function getEventCategory(string $id): array {
        $response = $this->client->get("properties/eventCategories/{$id}");
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Update an event category.
     *
     * @param string $id       Event category ID
     * @param array  $category IdNamePair payload (name, status)
     */
    public function updateEventCategory(string $id, array $category): array {
        $response = $this->client->put("properties/eventCategories/{$id}", ['json' => $category]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Delete an event category.
     *
     * @param string $id Event category ID
     */
    public function deleteEventCategory(string $id): array {
        $response = $this->client->delete("properties/eventCategories/{$id}");
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get event topics.
     */
    public function getEventTopics(): array {
        $response = $this->client->get('properties/eventTopics');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get funds.
     */
    public function getFunds(): array {
        $response = $this->client->get('properties/funds');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get genders.
     */
    public function getGenders(): array {
        $response = $this->client->get('properties/genders');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get individual types.
     */
    public function getIndividualTypes(): array {
        $response = $this->client->get('properties/individualTypes');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get the organization profile.
     */
    public function getOrganizationProfile(): array {
        $response = $this->client->get('properties/organizationProfile');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get name prefixes.
     */
    public function getPrefixes(): array {
        $response = $this->client->get('properties/prefixes');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get donation purposes.
     */
    public function getPurposes(): array {
        $response = $this->client->get('properties/purposes');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get relation types.
     *
     * @param string|null $relationTypeCategory Optional category filter (e.g. INDIVIDUAL_INDIVIDUAL)
     */
    public function getRelationTypes(?string $relationTypeCategory = null): array {
        $params = $relationTypeCategory !== null ? ['relationTypeCategory' => $relationTypeCategory] : [];
        $response = $this->client->get('properties/relationTypes', ['query' => $params]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get solicitation methods.
     */
    public function getSolicitationMethods(): array {
        $response = $this->client->get('properties/solicitationMethods');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get donation sources.
     */
    public function getSources(): array {
        $response = $this->client->get('properties/sources');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get states/provinces.
     */
    public function getStateProvinces(): array {
        $response = $this->client->get('properties/stateProvinces');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get system timezones.
     */
    public function getSystemTimezones(): array {
        $response = $this->client->get('properties/systemTimezones');
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get all system users.
     */
    public function getSystemUsers(): array {
        $response = $this->client->get('properties/systemUsers');
        return json_decode($response->getBody()->getContents(), true);
    }

    // -------------------------------------------------------------------------
    // Custom Fields endpoints
    // -------------------------------------------------------------------------

    /**
     * Get custom fields for a component category.
     *
     * @param string      $category            Required. One of: Account, Donation, Event, Attendee, Individual, Company, Activity, Membership, Product, Prospect, Grant
     * @param bool|null   $isEventSpecificField Optional filter for event-specific fields
     * @param bool|null   $attendeeQuestion     Optional filter for attendee question fields
     */
    public function getCustomFields(string $category, ?bool $isEventSpecificField = null, ?bool $attendeeQuestion = null): array {
        $params = array_filter([
            'category'            => $category,
            'isEventSpecificField' => $isEventSpecificField,
            'attendeeQuestion'    => $attendeeQuestion,
        ], fn($v) => $v !== null);
        $response = $this->client->get('customFields', ['query' => $params]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Create a custom field.
     *
     * @param array $customField CustomFieldData payload (requires displayType, dataType, component)
     */
    public function createCustomField(array $customField): array {
        $response = $this->client->post('customFields', ['json' => $customField]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get a specific custom field.
     *
     * @param string $id Custom field ID
     */
    public function getCustomField(string $id): array {
        $response = $this->client->get("customFields/{$id}");
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Update a custom field.
     *
     * @param string $id          Custom field ID
     * @param array  $customField CustomFieldData payload
     */
    public function updateCustomField(string $id, array $customField): array {
        $response = $this->client->put("customFields/{$id}", ['json' => $customField]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Delete a custom field.
     *
     * @param string $id Custom field ID
     */
    public function deleteCustomField(string $id): array {
        $response = $this->client->delete("customFields/{$id}");
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get custom field groups for a component.
     *
     * @param string $component Required. One of: Account, Donation, Event, Attendee, Individual, Company, Activity, Membership, Product, Prospect, Grant
     */
    public function getCustomFieldGroups(string $component): array {
        $response = $this->client->get('customFields/groups', ['query' => ['component' => $component]]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Create a custom field group.
     *
     * @param array $group CustomFieldGroup payload (requires displayName and component)
     */
    public function createCustomFieldGroup(array $group): array {
        $response = $this->client->post('customFields/groups', ['json' => $group]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Update a custom field group.
     *
     * @param int   $groupId Group ID
     * @param array $group   BaseCustomFieldGroup payload (requires displayName)
     */
    public function updateCustomFieldGroup(int $groupId, array $group): array {
        $response = $this->client->put("customFields/groups/{$groupId}", ['json' => $group]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Delete a custom field group.
     *
     * @param int $groupId Group ID
     */
    public function deleteCustomFieldGroup(int $groupId): array {
        $response = $this->client->delete("customFields/groups/{$groupId}");
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Add custom fields to a group.
     *
     * @param string   $groupId      Target group ID
     * @param string[] $customFields Array of custom field IDs to add to the group
     */
    public function addCustomFieldsToGroup(string $groupId, array $customFields): array {
        $response = $this->client->post('customFields/addToGroup', ['json' => ['groupId' => $groupId, 'customFields' => $customFields]]);
        return json_decode($response->getBody()->getContents(), true);
    }

}
