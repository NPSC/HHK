<?php
namespace HHK\CrmExport\Salesforce;


use Exception;
use HHK\Common;
use HHK\CreateMarkupFromDB;
use HHK\CrmExport\AbstractExportManager;
use HHK\CrmExport\FieldMapper;
use HHK\CrmExport\Salesforce\Subresponse\AbstractCompositeSubresponse;
use HHK\Crypto;
use HHK\Exception\RuntimeException;
use HHK\SysConst\RelLinkType;
use HHK\Tables\CmsGatewayRS;
use HHK\Tables\EditRS;
use HHK\Tables\Name\Name_GuestRS;
use HHK\HTMLControls\{HTMLContainer, HTMLTable, HTMLInput, HTMLSelector};
use HHK\sec\Session;
use HHK\OAuth\Credentials;
use HHK\sec\SecurityComponent;
use PDOStatement;


/**
 *
 * @author Eric
 *
 */
class SalesforceManager extends AbstractExportManager {


    const oAuthEndpoint = 'services/oauth2/token';
    const SearchViewName = 'vguest_canonical';
    const PW_PLACEHOLDER = '**********';

    /**
     * Documentation for the following Composite Graph payload limitations.
     * website:  https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/resources_composite_graph_limits.htm
     */

    const int MAX_PAYLOAD_GRAPHS = 70;
    const int MAX_NODES = 500;
    const int GRAPH_DEPTH = 15;
    const int MAX_DIFF_NODES = 15;
    const int MAX__GRAPH_FAILS = 14;

    private const REQUIRED_FIELDS = [
        'Contact' => ['hhk_id'],
    ];

    private const CONDITIONAL_REQUIRED_FIELDS = [
        'Account' => ['psg_id'],
        'npe4__Relationship__c' => ['relationship_to_patient'],
    ];


    private string $endPoint;

    private string $queryEndpoint;

    private string $searchEndpoint;

    private string $getContactEndpoint;

    protected $transferResult;
    protected $errorResult;
    protected SF_connector $webService;

    protected $uniqueGuests;
    protected bool $trace;
    protected $traceData;
    protected array $picklists;
    protected array $objectFields = [];
    protected array $hhkFieldDetails = [];
    protected FieldMapper $fieldMapper;

    const string LOG_SERVICE_NAME = "SalesForce";

    /**
     * {@inheritDoc}
     * @see \HHK\CrmExport\AbstractExportManager::__construct()
     */
    public function __construct(\PDO $dbh, string $cmsName) {
        parent::__construct($dbh, $cmsName);

        // build the urls
        $this->endPoint = '/services/data/v' . $this->getApiVersion() . "/";
        $this->queryEndpoint = $this->endPoint . 'query';
        $this->searchEndpoint = $this->endPoint . 'search';
        $this->getContactEndpoint = $this->endPoint . 'sobjects/Contact/';

        $credentials = new Credentials();
        $credentials->setBaseURI($this->endpointURL);
        $credentials->setTokenURI(self::oAuthEndpoint);
        $credentials->setClientId($this->clientId);
        $credentials->setClientSecret(Crypto::decryptMessage($this->clientSecret));
        $credentials->setSecurityToken($this->securityToken);
        $credentials->setUsername($this->userId);
        $credentials->setPassword(Crypto::decryptMessage($this->getPassword()));

        $this->webService = new SF_Connector($dbh, $credentials);
        
        try{
            $this->fieldMapper = new FieldMapper($dbh, (int) $this->getGatewayId());
        }catch(Exception $e){

        }
    }

    /**
     * Summary of getRelationship
     * @param mixed $accountId
     * @return string
     */
    public function getRelationship($accountId) {

        $result = $this->retrieveURL($this->endPoint . 'sobjects/npe4__Relationship__c/' . $accountId);

        $parms = [];
        $this->unwindResponse($parms, $result);
        $resultStr = new HTMLTable();

        foreach ($parms as $k => $v) {
            $resultStr->addBodyTr(HTMLTable::makeTd($k, []) . HTMLTable::makeTd($v));
        }

        return $resultStr->generateMarkup();
    }

    /**
     * Fetch sObject describe data for one or more objects in a single Composite Batch request.
     * Already-cached objects are skipped. Populates $this->objectFields and $this->picklists.
     *
     * @param string[] $objects SF object API names, e.g. ['Contact', 'Account', 'npe4__Relationship__c']
     */
    private function fetchObjectDescribe(array $objects): void {
        $uS = Session::getInstance();
        $cached = $uS->sf_describe ?? [];

        // Restore any session-cached describes into instance properties
        foreach ($objects as $obj) {
            if (!isset($this->objectFields[$obj]) && isset($cached[$obj])) {
                $this->objectFields[$obj]    = $cached[$obj]['fields'];
                $this->picklists[$obj]       = $cached[$obj]['picklists'];
                $this->hhkFieldDetails[$obj] = $cached[$obj]['hhkFieldDetails'] ?? [];
            }
        }

        $toFetch = array_values(array_filter($objects, fn($obj) => !isset($this->objectFields[$obj])));
        if (empty($toFetch)) {
            return;
        }

        // Pre-initialize so a failed response doesn't trigger another attempt.
        foreach ($toFetch as $obj) {
            $this->objectFields[$obj] = [];
            $this->picklists[$obj]    = [];
        }

        $batchRequests = array_map(
            fn($obj) => ['method' => 'GET', 'url' => "{$this->endPoint}sobjects/{$obj}/describe"],
            $toFetch
        );

        $result = $this->webService->postUrl(
            "{$this->endPoint}composite/batch",
            ['batchRequests' => $batchRequests, 'haltOnError' => false]
        );

        if (!\is_array($result) || !isset($result['results'])) {
            return;
        }

        foreach ($result['results'] as $i => $batchResult) {
            if (($batchResult['statusCode'] ?? 0) !== 200 || !isset($batchResult['result']['fields'])) {
                continue;
            }
            $this->processDescribeFields($toFetch[$i], $batchResult['result']['fields']);
        }

        // Persist to session so subsequent page loads don't re-fetch
        foreach ($toFetch as $obj) {
            $cached[$obj] = [
                'fields'         => $this->objectFields[$obj] ?? [],
                'picklists'      => $this->picklists[$obj] ?? [],
                'hhkFieldDetails' => $this->hhkFieldDetails[$obj] ?? [],
            ];
        }
        $uS->sf_describe = $cached;
    }

    /**
     * Parse a describe fields array into $this->objectFields and $this->picklists for one object.
     */
    private function processDescribeFields(string $object, array $fields): void {
        foreach ($fields as $f) {
            if (!isset($f['name']) || !$f['createable'] == true || !$f['updateable'] == true) {
                continue;
            }

            $this->objectFields[$object][$f['name']] = $f['label'] ?? $f['name'];

            if (str_starts_with($f['name'], 'HHK_') && str_ends_with($f['name'], '__c')) {
                $this->hhkFieldDetails[$object][$f['name']] = [
                    'label'      => $f['label'] ?? $f['name'],
                    'type'       => $f['type'] ?? '',
                    'length'     => $f['length'] ?? 0,
                    'unique'     => $f['unique'] ?? false,
                    'externalId' => $f['externalId'] ?? false,
                ];
            }

            if (!empty($f['picklistValues'])) {
                $fieldValues = [];
                foreach ($f['picklistValues'] as $pv) {
                    if (($pv['active'] ?? false) && isset($pv['value'], $pv['label'])) {
                        $fieldValues[$pv['value']] = $pv['label'];
                    }
                }
                if (!empty($fieldValues)) {
                    $this->picklists[$object][$f['name']] = $fieldValues;
                }
            }
        }
    }

    /**
     * Fetch picklist lookups from salesforce. Stored as $this->picklists[$object][$fieldName][$value] = $label.
     * @param string $object Salesforce object eg Account, Contact, npe4__Relationship__c
     * @return array
     */
    public function getPicklists(string $object): array {
        $this->fetchObjectDescribe([$object]);
        return $this->picklists;
    }

    /**
     * All SF field API names => labels for the given object.
     * Returns [] if not connected or object not found.
     */
    public function getObjectFields(string $object): array {
        $this->fetchObjectDescribe([$object]);
        return $this->objectFields[$object];
    }

    /**
     * Maps HHK canonical field names to the gen_lookups Table_Name that holds their
     * enumerated values. Used by the picklist mapping modal to populate the HHK side.
     * sf_type_map.HHK_Type_Code stores the gen_lookups Code column for these fields.
     */
    protected static function getHhkPicklistTable(string $hhkField): string {
        return match($hhkField) {
            'prefix'                  => 'Name_Prefix',
            'suffix'                  => 'Name_Suffix',
            'gender'                  => 'Gender',
            'relationship_to_patient' => 'Patient_Rel_Type',
            default                   => '',
        };
    }

    /**
     * Summary of searchMembers Searches remote with letters from an autocomplete
     * @param array $searchCriteria
     * @return array
     */
    public function searchMembers (array $searchCriteria): array {

        $replys = [];

        $query = "FIND {" . $searchCriteria['letters'] . "*} IN Name Fields RETURNING Contact(Id, Name, phone, email)";

        $result = $this->webService->search($query, $this->searchEndpoint);

        if (isset($result['searchRecords'])) {

            foreach ($result['searchRecords'] as $r) {

                $namArray['id'] = $r["Id"];
                $namArray['fullName'] = $r["Name"];
                $namArray['value'] = $r['Name'];
                $namArray['Phone'] = $r['phone'] ?? '';
                $namArray['Email'] = $r['email'] ?? '';
                $attributes = $r['attributes'];
                $namArray['url'] = $attributes['url'];
                $namArray['Type'] = $attributes['type'];

                $replys[] = $namArray;
            }

            if (count($replys) === 0) {
                $replys[] = ["id" => 0, "value" => "No one found."];
            }

        } else {
            $replys[] = ["id" => 0, "value" => "No one found."];
        }


        return $replys;
    }

    /**
     * Summary of getMember - local or remote retrieve member details.
     * @param \PDO $dbh
     * @param array $parameters
     * @return string
     */
    public function getMember(\PDO $dbh, array $parameters): string {

        $source = $parameters['src'] ?? '';
        $id = $parameters['accountId'] ?? '';
        $url = $parameters['url'] ?? '';
        $reply = '';
        $resultStr = new HTMLTable();
        $this->setAccountId('');

        if ($source === 'hhk') {

            $row = $this->loadSourceDB($dbh, $id, 'vguest_canonical');

            if (is_null($row)) {
                $reply = 'Error - HHK Id not found, or member is not a guest or patient. ';
                $this->setAccountId('error');
            } else {
                foreach ($row as $k => $v) {

                    if ($k == 'external_id' && $v == SELF::EXCLUDE_TERM) {
                        $resultStr->addBodyTr(HTMLTable::makeTd($k, []) . HTMLTable::makeTd('*Excluded*'));
                    } else {
                        $resultStr->addBodyTr(HTMLTable::makeTd($k, []) . HTMLTable::makeTd($v));
                    }
                }

                $reply = $resultStr->generateMarkup();
                $this->setAccountId($row['external_id']);
            }

        } else if ($source == 'remote') {

            //  accounts
            $result = $this->retrieveURL($url);

            $parms = array();
            $this->unwindResponse($parms, $result);

            foreach ($parms as $k => $v) {
                $resultStr->addBodyTr(HTMLTable::makeTd($k, []) . HTMLTable::makeTd($v));
            }

            $reply = $resultStr->generateMarkup();

        } else {
            $reply = "Source for search not found: " . $source;
        }

        return $reply;
    }

    /**
     * The Account Id is embedded into the URL.
     *
     * @param string $url
     * @return mixed
     */
    protected function retrieveURL(string $url) {

        $results = $this->webService->goUrl($url);

        return $results;
    }

    public function retrieveRemoteAccount(string|int $accountId):array {

        return $this->retrieveURL($this->getContactEndpoint . $accountId);
    }

    /**
     * Summary of exportMembers - Export (copy) HHK members to remote system
     * @param \PDO $dbh
     * @param array $sourceIds list of member Id's to export
     * @return array
     */
    public function exportMembers(\PDO $dbh, array $sourceIds, array $updateIds = []): array {
        $replys = [];
        
        if (count($sourceIds) == 0) {
            $replys[0] = ['error' => "The list of HHK Id's to send is empty."];
            return $replys;
        }


        // Load search parameters for each source ID
        $stmt = $this->loadSearchDB($dbh, 'vguest_canonical', $sourceIds);

        if (is_null($stmt)) {
            $replys[0] = ['error' => 'No local records were found.'];
            return $replys;
        }

        // Run through the local records.
        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $f = [];   // output fields array

            // Clean up names fresh from the DB
            $r['first_name']  = $this->unencodeHTML($r['first_name']);
            $r['middle_name'] = $this->unencodeHTML($r['middle_name']);
            $r['last_name']   = $this->unencodeHTML($r['last_name']);

            // Build display row from canonical keys → human labels
            $rf = $this->getReturnFields();
            foreach ($rf as $hhkField => $label) {
                $f[$label] = $r[$hhkField] ?? '';
            }

            // Collect address into a single column
            $f['Address'] = $f['Street'] . ', ' . $f['City'] . ', ' . $f['State'] . ', ' . $f['Zip'];
            unset($f['Street'], $f['City'], $f['State'], $f['Zip']);

            // Collect name into a single column
            $f['Name'] = $f['Name'] . ' ' . ($f['Middle'] == '' ? '' : $f['Middle'] . ' ') . $f['Last Name'] . ' ' . $f['Suffix'] . ($f['Nickname'] == '' ? '' : ', ' . $f['Nickname']);
            unset($f['Middle'], $f['Last Name'], $f['Suffix'], $f['Nickname']);

            // Search target system.  Treat return as user input.
            $rawResult = $this->searchTarget($r);

            if ($this->checkError($rawResult)) {
                $f['Result'] = $this->errorMessage;
                $replys[$r['hhk_id']] = $f;
                continue;
            }

            // Need a totalSize
            if (isset($rawResult['totalSize']) === FALSE) {
                $f['Result'] = 'API ERROR: totalSize parameter is missing;  ' . $this->errorMessage;
                $replys[$r['hhk_id']] = $f;
                continue;
            }

            // Test results
            if ($rawResult['totalSize'] == 1 ) {

                // We have a similar contact

                if (isset($rawResult['records'][0]['Id']) && $rawResult['records'][0]['Id'] != '') {
                    // This is an Update

                    $this->updateRemoteMember($dbh, $rawResult['records'][0], 0, $r, FALSE);

                    if (count($this->getProposedUpdates()) > 0) {
                        $f['Result'] = HTMLInput::generateMarkup('', ['id' => 'updt_' . $r['hhk_id'], 'class' => 'hhk-txCbox hhk-updatemem', 'data-txid' => $r['hhk_id'], 'data-txacct' => $rawResult['records'][0]['Id'], 'type' => 'checkbox']);
                        $label = 'Updates Proposed: ';
                        foreach ($this->getProposedUpdates() as $k => $v) {
                            $label .= "$k=$v; ";
                        }
                        $f['Result'] .= HTMLContainer::generateMarkup('label', $label, ['for' => 'updt_' . $r['hhk_id'], 'style' => 'margin-left:.3em; background-color:#FBF6CD;']);
                    } else {
                        $f['Result'] = 'Up to date.';
                    }

                    // Make sure the external Id is defined locally
                    $this->updateLocalExternalId($dbh, $r['hhk_id'], $rawResult['records'][0]['Id']);
                    $f['Id'] = $rawResult['records'][0]['Id'];

                } else {
                    $f['Result'] = 'The search results Account Id is empty.';
                }

                $replys[$r['hhk_id']] = $f;


            } else if ($rawResult['totalSize'] > 1 ) {

                // Multiple records.

                $title = '';
                $options = [];

                // Look through the results — $m has SF field names (from remote response)
                foreach ($rawResult['records'] as $m) {

                    // Did we find our HHK ID?
                    if ($m['HHK_idName__c'] != '' && $m['HHK_idName__c'] == $r['hhk_id']) {

                        $this->updateRemoteMember($dbh, $m, 0, $r, FALSE);

                        if (count($this->getProposedUpdates()) > 0) {
                            $f['Result'] = HTMLInput::generateMarkup('', ['id' => 'updt_' . $r['hhk_id'], 'class' => 'hhk-txCbox hhk-updatemem', 'data-txid' => $r['hhk_id'], 'data-txacct' => $m['Id'], 'type' => 'checkbox']);
                            $label = 'Updates Proposed: ';
                            foreach ($this->getProposedUpdates() as $k => $v) {
                                $label .= "$k=$v; ";
                            }
                            $f['Result'] .= HTMLContainer::generateMarkup('label', $label, ['for' => 'updt_' . $r['hhk_id'], 'style' => 'margin-left:.3em; background-color:#FBF6CD;']);
                        } else {
                            $f['Result'] = 'Up to date. (MR)';
                        }

                        $this->updateLocalExternalId($dbh, $r['hhk_id'], $m['Id']);
                        $f['Id'] = $m['Id'];

                        $replys[$r['hhk_id']] = $f;
                        return $replys;
                    }

                    // SF response uses SF field names
                    $name  = $m['FirstName'] . ' ' . ($m['Middle_Name__c'] == '' ? '' : $m['Middle_Name__c'] . ' ') . $m['LastName'] . ' ' . $m['Suffix__c'];
                    $title = ($m['HHK_idName__c'] == '' ? '' : $m['HHK_idName__c'] . ', ') . $name . ($m['Email'] == '' ? '' : ', ' . $m['Email']) . $m['HomePhone'];
                    $options[$m['Id']] = [$m['Id'], $title];
                }

                $f['Result'] = ' Found ' . $rawResult['totalSize'] . ' similar accounts ';
                $f['Result'] .= HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($options, '', TRUE), ['name' => 'selmultimem_' . $r['hhk_id'], 'class' => 'multimemsels']);
                $f['Result'] .= ' Found ' . $rawResult['totalSize'] . ' similar accounts';
                $replys[$r['hhk_id']] = $f;


            } else if ($rawResult['totalSize'] == 0 ) {

                // Check for not finding the account Id
                if ($r['external_id'] != '') {
                    // Account was deleted from the Salesforce side.
                    $f['Result'] = 'Account Deleted at Saleforce';
                    $replys[$r['hhk_id']] = $f;
                    continue;
                }

                // Nothing found - create a new account at remote

                // Get member data record
                $row = $this->loadSourceDB($dbh, $r['hhk_id'], 'vguest_canonical');

                if (is_null($row)) {
                    continue;
                }

                // Check external Id
                if (isset($row['external_id']) && $row['external_id'] == self::EXCLUDE_TERM) {
                    // Skip excluded members.
                    continue;
                }

                // Translate canonical row to SF-named Contact payload
                $filteredRow = $this->fieldMapper->translateRow($row, 'Contact');

                // Create new account
                try {

                    $newAcctResult = $this->webService->postUrl($this->endPoint . 'sobjects/Contact/', $filteredRow);

                    if ($this->checkError($newAcctResult)) {
                        $f['Result'] = $this->errorMessage;
                        $replys[$r['hhk_id']] = $f;
                        continue;
                    }

                } catch (\RuntimeException $ex) {

                    if (strstr($ex->getMessage(), 'DUPLICATE_VALUE')) {
                        // mark duplicate and continue
                        $f['Result'] = $ex->getMessage();
                        $replys[$r['hhk_id']] = $f;
                        continue;
                    }

                    // Re-throw the exception.
                    throw new \RuntimeException($ex->getMessage());
                }

                $accountId = filter_var($newAcctResult['id'], FILTER_SANITIZE_SPECIAL_CHARS);

                $this->updateLocalExternalId($dbh, $r['hhk_id'], $accountId);

                $f['Result'] = $accountId != '' ? 'New Salesforce Account' : 'Salesforce Account Missing';
                $f['Id']     = $accountId;
                $replys[$r['hhk_id']] = $f;

            } else {

                $f['Result'] = "API ERROR: {$this->errorMessage}";
                $replys[$r['hhk_id']] = $f;
            }

        }

        return $replys;
    }


    /**
     * Summary of upsertMembers Bulk insert/update of members
     * @param \PDO $dbh
     * @param array $sourceIds
     * @param mixed $trace
     * @param bool $linkRelatives
     * @return array
     */
    public function upsertMembers(\PDO $dbh, array $sourceIds, bool $trace = false, bool $linkRelatives = true): array {

        $this->uniqueGuests = [];   // Keep track to not repeat a guest upsert into multiple psgs?
        $this->transferResult = [];
        $this->errorResult = [];
        $this->trace = $trace == 'true' ? TRUE : FALSE;
        $this->traceData = '';


        if (count($sourceIds) == 0) {
            $replys[0] = ['error' => "The list of HHK Id's to send is empty."];
            return $replys;
        }

        // Each PSG uses a compositRequest/Graph to identify members and relationships.
        // GraphId = psgId.

        // get the member records. the rows must be ordered by PSG Id
        $stmt = $dbh->query("SELECT * FROM `vguest_canonical` WHERE `hhk_id` IN (" . implode(',', $sourceIds) . ") ORDER BY `psg_id`;");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $idPsg = 0;
        $batchRows = [];
        $graphCounter = 1;
        $batches = [];

        foreach ($rows as $r) {

            if ($idPsg > 0 && $idPsg != $r['psg_id']) {

                // Do we have enough graphs
                if ($graphCounter >= $this->getMaxPSGsPerBatch() || $graphCounter >= self::MAX_PAYLOAD_GRAPHS || count($batchRows) >= self::MAX_NODES - 100) {

                    try{
                        $batches[] = ["batchBody" => $this->prepareBatch($dbh, $batchRows, $linkRelatives), "batchRows"=>$batchRows];
                    }catch(\Exception){

                    }

                    $batchRows = [];
                    $graphCounter = 1;
                }

                $graphCounter++;

            }

            $idPsg = $r['psg_id'];

            $batchRows[] = $r;

        }

        // Anything left?
        if (count($batchRows) > 0) {
            try{
                $batches[] = ["batchBody"=>$this->prepareBatch($dbh, $batchRows, $linkRelatives), "batchRows"=>$batchRows];
            }catch(\Exception){

            }
        }

        $this->sendbatches($dbh, $batches);

        // Create an HTML table containing the results (re-index since transferResult is keyed by HHK_psg)
        $result['table'] = CreateMarkupFromDB::generateHTML_Table(array_values($this->transferResult), 'tblrpt');

        if ($this->trace) {
            $result['trace'] = $this->traceData;
        }
        
        if(count($this->errorResult) > 0){
            $result["errors"] = $this->errorResult;
        }

        return $result;
    }

    /**
     * Prepare a single batch and return an array formatted for an SF request
     * @param \PDO $dbh
     * @param array $rows
     * @param bool $linkRelatives
     * @return array{graphs: array|bool}
     */
    protected function prepareBatch(\PDO $dbh, array $rows, bool $linkRelatives = true): array {

        $psgGuests = [];    // list of guests in PSG
        $psgGraphs = [];  // The collection of psg graphs
        $psgId = 0; // multiple records for each psgId

        $linkRelatives = $linkRelatives && $this->fieldMapper->hasObject('npe4__Relationship__c');

        if ($linkRelatives) {
            $this->verifyRelationshipIds($dbh, $rows);
        }

        // Collect each psg into a guests array and process it as a composit request set to make a graph
        // $rows must be ordered by PSG Id
        foreach ($rows as $r) {

            // New PSG Id?
            if ($psgId > 0 && $r['psg_id'] != $psgId) {

                // Yes, new Id.  Process current psg
                $graph = $this->createPsgGraph($psgGuests, $psgId, $linkRelatives);

                // Add to collection
                if (count($graph) > 0) {
                    $psgGraphs[] = $graph;
                }

                $psgGuests = [];
            }

            $psgId = $r['psg_id'];
            $psgGuests[$r['hhk_id']] = $r;
        }

        // And last group
        if ($psgId > 0) {

            $graph = $this->createPsgGraph($psgGuests, $psgId, $linkRelatives);

            if (count($graph) > 0) {
                $psgGraphs[] = $graph;
            }

        }


        // Anything to transfer?
        if (count($psgGraphs) > 0) {

            return [
                "graphs" => $psgGraphs,
            ];

            
        }else{
            throw new RuntimeException("Empty batch");
        }

    }

    /**
     * Prepare and send an array of batches to SF
     * @param \PDO $dbh
     * @param array $batches
     * @return void
     */
    protected function sendbatches(\PDO $dbh, array $batches): void{
        $batchBodies = [];
        foreach($batches as $batchId=>$batch){
            if($batch["batchBody"]){
                $batchBodies[$batchId] = $batch["batchBody"];
            }
        }

        // Show request trace?
            if ($this->trace) {
                $sentAt = new \DateTime();
                $this->traceData .= "<p>Transfer initiated at: " . $sentAt->format(DATE_W3C) . "</p>";
            }

            // Transfer this package to SF API
            try {
                $batchResults = $this->webService->postUrlAsync($this->endPoint . "composite/graph", $batchBodies);

                if ($this->trace) {
                    $completedAt = new \DateTime();
                    $this->traceData .= "<p>Transfer completed at: " . $completedAt->format(DATE_W3C) . "</p>";
                    $this->traceData .= "<p>Elapsed Time: " . $completedAt->getTimestamp() - $sentAt->getTimestamp() . " seconds</p>";
                    $this->traceData .= "<p>Total requests sent: " . count($batchResults['batchResults']) . "</p>";
                }

                    foreach($batchResults['batchResults'] as $batchId=>$batchResult){
                        if ($this->trace) {
                            $this->traceData .= "<hr class='my-3'><h4>Request</h4><pre>" . json_encode($batchBodies[$batchId], JSON_PRETTY_PRINT) . "</pre>";
                        }

                        if(isset($batchResult['success'])){
                            if ($this->trace) {
                                $this->traceData .= "<h4>Response</h4><pre>" . json_encode($batchResult['success'], JSON_PRETTY_PRINT) . "</pre>";
                            }
                            $this->processGraphsResult($dbh, $batchResult['success'], $batches[$batchId]["batchRows"]);
                        }else if (isset($batchResult["error"])){
                            $this->errorResult[] = $batchResult["error"];
                            if ($this->trace) {
                                $this->traceData .= "<h4>Errors</h4><pre>" .json_encode($batchResult['error'], JSON_PRETTY_PRINT) . "</pre>";
                            }
                        }
                    }

            } catch (\RuntimeException $ex) {
                $this->errorResult[] = $ex->getMessage();

                if ($this->trace) {
                    $this->traceData .= "<h4>Errors</h4><pre>" .json_encode($ex->getMessage(), JSON_PRETTY_PRINT) . "</pre>";
                }
            }
    }

    /**
     * Summary of createPsgGraph
     * @param array $guests  List of Guests in the PSG
     * @param mixed $graphId  PSG Id
     * @param bool $linkRelatives  Whether to create relationship links between the guests in the PSG
     * @return array  The formatted Graph object
     */
    protected function createPsgGraph(array $guests, $graphId, bool $linkRelatives): array {

        $hasPatient = false;
        $idPatient = 0;
        $subrequests = [];
        $graph = [];
        $contactsInThisGraph = [];  // guests whose Contact subrequest is in this specific graph

        $additnl = '_' . $graphId;

        // Pre-scan: detect patient regardless of uniqueGuests status (needed for relationship subrequests).
        foreach ($guests as $g) {
            if ($g['relationship_code'] == RelLinkType::Self) {
                $hasPatient = true;
                $idPatient  = $g['hhk_id'];
                break;
            }
        }

        // Use the patient as the primary contact. When SF (NPSP) creates a new Contact it
        // auto-creates a Household Account — we GET that AccountId and use it to link all
        // other guests in this PSG to the same Account.
        $primaryGuest       = $guests[$idPatient] ?? array_values($guests)[0];
        $primaryHhkId       = $primaryGuest['hhk_id'];
        $primarySfId        = $primaryGuest['external_id'];
        $primaryIsNew       = ($primarySfId == '');
        $primaryInThisGraph = !isset($this->uniqueGuests[$primaryHhkId]);

        // --- Phase 1: upsert the primary contact ---
        if ($primaryInThisGraph) {
            $this->uniqueGuests[$primaryHhkId] = 'y';
            $contactsInThisGraph[$primaryHhkId] = true;

            $subrequests[] = [
                "method" => "PATCH",
                "url" => $this->getContactEndpoint . 'HHK_idName__c/' . $primaryHhkId,
                "referenceId" => "refContact_" . $primaryHhkId . $additnl,
                "body" => $this->fieldMapper->translateRow($primaryGuest, 'Contact'),
            ];
        }

        // --- Phase 2: GET AccountId from the primary contact ---
        // A new contact (201) gives us an id to GET from; an existing contact (204, no body)
        // must be fetched by the locally-stored SF Contact ID.
        $accountRefId    = "refGetAccount" . $additnl;
        $canGetAccountId = false;

        if ($primaryInThisGraph && $primaryIsNew) {
            $subrequests[] = [
                "method" => "GET",
                "url" => $this->getContactEndpoint . '@{refContact_' . $primaryHhkId . $additnl . '.id}?fields=AccountId',
                "referenceId" => $accountRefId
            ];
            $canGetAccountId = true;
        } elseif ($primarySfId != '') {
            $subrequests[] = [
                "method" => "GET",
                "url" => $this->getContactEndpoint . $primarySfId . '?fields=AccountId',
                "referenceId" => $accountRefId
            ];
            $canGetAccountId = true;
        }
        // else: primary is brand-new and already processed in a prior graph this batch —
        // no AccountId available; remaining guests get their own SF-generated accounts.

        // --- Phase 2b: stamp HHK_idPsg__c on the Account ---
        if ($canGetAccountId) {
            $subrequests[] = [
                "method" => "PATCH",
                "url" => $this->endPoint . 'sobjects/Account/@{' . $accountRefId . '.AccountId}',
                "referenceId" => "refPatchAccount" . $additnl,
                "body" => $this->fieldMapper->translateRow(['psg_id' => (string)$graphId], 'Account'),
            ];
        }

        // --- Phase 3: upsert remaining contacts, linked to the primary's Account ---
        foreach ($guests as $g) {

            if ($g['hhk_id'] == $primaryHhkId) {
                continue;
            }

            if (isset($this->uniqueGuests[$g['hhk_id']])) {
                continue;
            }

            $this->uniqueGuests[$g['hhk_id']] = 'y';
            $contactsInThisGraph[$g['hhk_id']] = true;

            $contactFields = $this->fieldMapper->translateRow($g, 'Contact');

            if ($canGetAccountId) {
                $contactFields['AccountId'] = "@{{$accountRefId}.AccountId}";
            }

            $subrequests[] = [
                "method" => "PATCH",
                "url" => $this->getContactEndpoint . 'HHK_idName__c/' . $g['hhk_id'],
                "referenceId" => "refContact_" . $g['hhk_id'] . $additnl,
                "body" => $contactFields,
            ];
        }


        // If there is a patient, make relationship subrequests
        if ($linkRelatives && $hasPatient && $idPatient > 0) {

            // Resolve the patient's reference once — prefer direct SF ID if their Contact was sent in a prior graph
            if (isset($contactsInThisGraph[$idPatient])) {
                $patientRef = "@{refContact_$idPatient$additnl.id}";
            } elseif (isset($guests[$idPatient]) && $guests[$idPatient]['external_id'] != '') {
                $patientRef = $guests[$idPatient]['external_id'];
            } else {
                $patientRef = null;  // can't link — patient contact hasn't been synced yet
            }

            foreach ($guests as $g) {

                if ($g['relationship_code'] == RelLinkType::Self) {
                    continue;
                }

                // Resolve the guest's reference — prefer direct SF ID if their Contact was sent in a prior graph
                if (isset($contactsInThisGraph[$g['hhk_id']])) {
                    $guestRef = "@{refContact_" . $g['hhk_id'] . $additnl . ".id}";
                } elseif ($g['external_id'] != '') {
                    $guestRef = $g['external_id'];
                } else {
                    continue;  // guest and patient must both be in SF before a relationship can be created
                }

                $relFields = $this->fieldMapper->translateRow($g, 'npe4__Relationship__c');

                if ($g['relationship_id'] == '') {

                    if ($patientRef === null) {
                        continue;
                    }

                    // New relationship — POST with contact references
                    $subrequests[] = [
                        "method" => "POST",
                        "url" => $this->endPoint . 'sobjects/npe4__Relationship__c/',
                        "referenceId" => "refRel_" . $g['hhk_id'] . $additnl,
                        "body" => [
                            'npe4__Contact__c'        => $patientRef,
                            'npe4__RelatedContact__c' => $guestRef,
                            'npe4__Status__c'         => 'Current',
                            ...$relFields,
                        ],
                    ];

                } else {

                    // Existing relationship — PATCH updatable fields only (contact lookups are immutable)
                    $subrequests[] = [
                        "method" => "PATCH",
                        "url" => $this->endPoint . 'sobjects/npe4__Relationship__c/' . $g['relationship_id'],
                        "referenceId" => "refRel_" . $g['hhk_id'] . $additnl,
                        "body" => ['npe4__Status__c' => 'Current', ...$relFields],
                    ];
                }
            }
        }

        // Make any subrequests?
        if (count($subrequests) > 0) {

            $graph = [
                'graphId' => $graphId,
                'compositeRequest' => $subrequests
            ];
        }

        return $graph;
    }

    /**
     * Summary of processGraphResult
     * @param \PDO $dbh
     * @param mixed $graphResult
     * @param mixed $guestRows
     * @return void
     */
    protected function processGraphsResult(\PDO $dbh, $graphResult, $guestRows): void {

        $result = [];

        // Top level
        if (isset($graphResult['graphs'])) {

            foreach ($graphResult['graphs'] as $graph) {

                // Each graph has a collection of subCompositeResponces
                $this->processCompositeResponse($dbh, $graph, $guestRows);

            }

        } else {
            // graphs object is missing.
            $this->errorResult[] = 'graphs collection is missing.';
        }

    }

    /**
     * Summary of processCompositeResponse is a collection of compositSubrequestResults
     * @param \PDO $dbh
     * @param array $graph
     * @param array $guests
     * @return void
     */
    protected function processCompositeResponse(\PDO $dbh, array $graph, array $guests): void {

        $idPsg = $graph['graphId'];
        $comResp = $graph['graphResponse']['compositeResponse'];
        $isSuccessful = $graph['isSuccessful'];

        // Each compositeSubrequestResult
        foreach ($comResp as $c) {

            $subResponse = AbstractCompositeSubresponse::factory($c, $idPsg, $isSuccessful);

            // factory() returns null for Account subrequests (refAccount_*) — skip them.
            if ($subResponse === null) {
                continue;
            }

            $guest = $this->findGuest($guests, $idPsg, $subResponse->getIdName());


            $f = (count($guest) > 0) ? [
                'Contact Id' => '',
                'HHK Id' => $guest['hhk_id'],
                'Name' => ($guest['prefix'] == '' ? '' : $guest['prefix'] . ' ') . $guest['first_name'] . ' ' . ($guest['middle_name'] == '' ? '' : $guest['middle_name'] . ' ') . $guest['last_name'] . ' ' . $guest['suffix'] . ($guest['nickname'] == '' ? '' : ', ' . $guest['nickname']),
                'PSG Id' => $idPsg,
                'Contact Type' => $guest['contact_type'],
                'Birthdate' => $guest['birthdate'] != '' ? date('M j, Y', strtotime($guest['birthdate'])) : '',
                'Result' => '',
            ] : [
                'Contact Id' => '',
                'HHK Id' => '',
                'Name' => '',
                'PSG Id' => $idPsg,
                'Contact Type' => '',
                'Birthdate' => '',
                'Result' => '',
            ];

            $resultStr = $subResponse->processResult($dbh);
            $contactId = $subResponse->getContactId();

            // Group by HHK ID + PSG so contact and relationship subrequests for the same
            // person collapse into one row instead of producing duplicate lines.
            $hhkId = count($guest) > 0 ? $guest['hhk_id'] : '';
            $key   = $hhkId . '_' . $idPsg;

            if (isset($this->transferResult[$key])) {
                $this->transferResult[$key]['Result'] .= '; ' . $resultStr;
                if ($this->transferResult[$key]['Contact Id'] === '' && $contactId !== '') {
                    $this->transferResult[$key]['Contact Id'] = $contactId;
                }
            } else {
                $f['Result']     = $resultStr;
                $f['Contact Id'] = $contactId;
                $this->transferResult[$key] = $f;
            }
        }
    }

    /**
     * Summary of findGuest
     * @param mixed $guests
     * @param mixed $idPsg
     * @param mixed $idName
     * @return mixed
     */
    private function findGuest($guests, $idPsg, $idName) {

        foreach ($guests as $g) {
            if ($g['psg_id'] == $idPsg && $g['hhk_id'] == $idName) {
                return $g;
            }
        }

        return [];
    }

    /**
     * Query SF for the Contact side of a set of npe4__Relationship__c records.
     * Returns a map of relationship_id -> ['contactId' => ...].
     */
    /**
     * Checks which stored relationship IDs still exist in SF. Clears stale IDs from
     * the rows array (by reference) and from the local DB so they are recreated on the
     * next composite graph run instead of failing with a 404 PATCH.
     */
    protected function verifyRelationshipIds(\PDO $dbh, array &$rows): void {

        $relIds = [];
        foreach ($rows as $r) {
            if (!empty($r['relationship_id'])) {
                $relIds[] = $r['relationship_id'];
            }
        }

        if (empty($relIds)) {
            return;
        }

        $idList  = implode("','", array_unique($relIds));
        $query   = "SELECT Id FROM npe4__Relationship__c WHERE Id IN ('$idList') LIMIT 2000";
        $result  = $this->webService->search($query, $this->queryEndpoint);

        $existingIds = [];
        if (isset($result['records'])) {
            foreach ($result['records'] as $rec) {
                $existingIds[$rec['Id']] = true;
            }
        }

        foreach ($rows as &$row) {
            if (!empty($row['relationship_id']) && !isset($existingIds[$row['relationship_id']])) {
                $this->updateLocalRelationshipId($dbh, (int)$row['hhk_id'], (int)$row['psg_id'], '');
                $row['relationship_id'] = '';
            }
        }
        unset($row);
    }

    protected function fetchRelationshipDirections(array $relIds): array {

        if (empty($relIds)) {
            return [];
        }

        $idList = implode("','", $relIds);
        $query  = "SELECT Id, npe4__Contact__c FROM npe4__Relationship__c WHERE Id IN ('$idList') LIMIT 2000";
        $result = $this->webService->search($query, $this->queryEndpoint);

        $map = [];
        if (isset($result['records'])) {
            foreach ($result['records'] as $r) {
                $map[$r['Id']] = ['contactId' => $r['npe4__Contact__c'] ?? ''];
            }
        }
        return $map;
    }

    /**
     * One-time maintenance method: finds every locally-stored relationship whose
     * npe4__Contact__c is the guest instead of the patient, deletes it, and
     * recreates it in the correct direction — atomically, one graph per fix.
     * Updates the local External_Id on success.
     */
    public function fixInverseRelationships(\PDO $dbh): array {

        $transferResult = [];
        $errorResult    = [];

        // All non-patient rows that have an SF relationship record
        $stmt      = $dbh->query("SELECT * FROM `vguest_canonical` WHERE `relationship_id` IS NOT NULL AND `relationship_id` != '' ORDER BY `psg_id`");
        $guestRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($guestRows)) {
            return ['table' => '<p>No relationships found to check.</p>', 'errors' => []];
        }

        // Pre-query SF for the Contact side of every stored relationship
        $relIds       = array_values(array_unique(array_column($guestRows, 'relationship_id')));
        $relDirections = $this->fetchRelationshipDirections($relIds);

        // Build a psgId → patient SF Contact ID map from the same view
        $psgIds     = array_unique(array_column($guestRows, 'psg_id'));
        $patStmt    = $dbh->query("SELECT `hhk_id`, `external_id`, `psg_id`, `relationship_code` FROM `vguest_canonical` WHERE `psg_id` IN (" . implode(',', $psgIds) . ")");
        $patientSfIds = [];
        while ($p = $patStmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($p['relationship_code'] == RelLinkType::Self && $p['external_id'] !== '') {
                $patientSfIds[$p['psg_id']] = $p['external_id'];
            }
        }

        // Build one atomic composite graph per backwards relationship
        $graphs     = [];
        $rowByGraph = [];

        foreach ($guestRows as $r) {

            if ($r['relationship_code'] == RelLinkType::Self) {
                continue;
            }

            if ($r['external_id'] === '' || ($r['relationship_to_patient'] ?? '') === '') {
                continue;  // guest not in SF or no type mapping
            }

            $patientSfId = $patientSfIds[$r['psg_id']] ?? null;
            if ($patientSfId === null) {
                continue;  // patient not yet in SF
            }

            $dir = $relDirections[$r['relationship_id']] ?? null;
            if ($dir === null) {
                continue;  // relationship no longer exists in SF
            }

            if ($dir['contactId'] === $patientSfId) {
                $transferResult[] = ['HHK Id' => $r['hhk_id'], 'PSG Id' => $r['psg_id'], 'Old Rel Id' => $r['relationship_id'], 'New Rel Id' => '', 'Result' => 'Already correct'];
                continue;
            }

            $graphId = $r['hhk_id'] . '_' . $r['psg_id'];

            $relFields = $this->fieldMapper->translateRow($r, 'npe4__Relationship__c');

            $graphs[$graphId] = [
                'graphId'          => $graphId,
                'compositeRequest' => [
                    [
                        'method'      => 'DELETE',
                        'url'         => "{$this->endPoint}sobjects/npe4__Relationship__c/{$r['relationship_id']}",
                        'referenceId' => "refDelRel_$graphId",
                    ],
                    [
                        'method'      => 'POST',
                        'url'         => "{$this->endPoint}sobjects/npe4__Relationship__c/",
                        'referenceId' => "refRel_$graphId",
                        'body'        => [
                            'npe4__Contact__c'        => $patientSfId,
                            'npe4__RelatedContact__c' => $r['external_id'],
                            'npe4__Status__c'         => 'Current',
                            ...$relFields,
                        ],
                    ],
                ],
            ];
            $rowByGraph[$graphId] = $r;
        }

        if (empty($graphs)) {
            return ['table' => CreateMarkupFromDB::generateHTML_Table($transferResult, 'tblfix'), 'errors' => []];
        }

        // Chunk into batches of MAX_PAYLOAD_GRAPHS (SF enforced limit) and send in parallel
        $batchBodies = [];
        foreach (array_chunk(array_values($graphs), self::MAX_PAYLOAD_GRAPHS) as $batchId => $batchGraphs) {
            $batchBodies[$batchId] = ['graphs' => $batchGraphs];
        }

        try {
            $batchResults = $this->webService->postUrlAsync($this->endPoint . 'composite/graph', $batchBodies);

            foreach ($batchResults['batchResults'] as $batchResult) {
                if (!isset($batchResult['success']['graphs'])) {
                    $errorResult[] = $batchResult['error'] ?? 'Unknown batch error';
                    continue;
                }

                foreach ($batchResult['success']['graphs'] as $graphResult) {
                    $graphId = $graphResult['graphId'];
                    $row     = $rowByGraph[$graphId] ?? null;

                    if ($graphResult['isSuccessful'] && $row !== null) {
                        $newRelId = '';
                        foreach ($graphResult['graphResponse']['compositeResponse'] as $subResp) {
                            if (str_starts_with($subResp['referenceId'], 'refRel_') && isset($subResp['body']['id'])) {
                                $newRelId = $subResp['body']['id'];
                                break;
                            }
                        }

                        if ($newRelId !== '') {
                            $this->updateLocalRelationshipId($dbh, (int)$row['hhk_id'], (int)$row['psg_id'], $newRelId);
                        }

                        $transferResult[] = ['HHK Id' => $row['hhk_id'], 'PSG Id' => $row['psg_id'], 'Old Rel Id' => $row['relationship_id'], 'New Rel Id' => $newRelId, 'Result' => 'Fixed'];

                    } elseif ($row !== null) {
                        $error = '';
                        foreach ($graphResult['graphResponse']['compositeResponse'] as $subResp) {
                            if (isset($subResp['body'][0]['errorCode'])) {
                                $error = $subResp['body'][0]['errorCode'] . ': ' . ($subResp['body'][0]['message'] ?? '');
                                break;
                            }
                        }
                        $transferResult[] = ['HHK Id' => $row['hhk_id'], 'PSG Id' => $row['psg_id'], 'Old Rel Id' => $row['relationship_id'], 'New Rel Id' => '', 'Result' => "Error: $error"];
                    }
                }
            }
        } catch (\RuntimeException $ex) {
            $errorResult[] = $ex->getMessage();
        }

        $result = ['table' => CreateMarkupFromDB::generateHTML_Table($transferResult, 'tblfix')];
        if (!empty($errorResult)) {
            $result['errors'] = $errorResult;
        }
        return $result;
    }

    /**
     * Update the locally-stored SF relationship ID for a guest/PSG pair.
     */
    private function updateLocalRelationshipId(\PDO $dbh, int $idName, int $idPsg, string $newRelId): void {

        $nameRs = new Name_GuestRS();
        $nameRs->idName->setStoredVal($idName);
        $nameRs->idPsg->setStoredVal($idPsg);
        $rows = EditRS::select($dbh, $nameRs, [$nameRs->idName, $nameRs->idPsg]);

        if (!empty($rows)) {
            EditRS::loadRow($rows[0], $nameRs);
            $nameRs->External_Id->setNewVal($newRelId);
            EditRS::update($dbh, $nameRs, [$nameRs->idName, $nameRs->idPsg]);
        }
    }

    /**
     * Summary of updateRemoteMember
     * @param \PDO $dbh
     * @param array $accountData is data returned from remote
     * @param int $idName person to update, 0 -> use localData, > 0 use as index for DB search
     * @param mixed $localData local data for person
     * @param bool $updateIt TRUE = push update to remote, FALSE = just return potential update fields as array.
     * @return string
     */
    public function updateRemoteMember(\PDO $dbh, array $accountData, $idName, $localData = [], $updateIt = FALSE): string {

        $msg = 'Already up to date. ';

        $this->proposedUpdates = [];

        // Load local data if not delivered in the $localData array
        if ($idName > 0) {
            $stmt = $this->loadSearchDB($dbh, 'vguest_canonical', [$idName]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (isset($rows[0])) {
                $localData = $rows[0];
            }
        }

        // Collect any fields where local value differs from remote value
        foreach ($this->fieldMapper->getExportMap('Contact') as $hhkField => $crmField) {
            $localVal  = $localData[$hhkField] ?? '';
            $remoteVal = $accountData[$crmField] ?? null;

            if ($localVal !== '' && ($remoteVal === null || trim($localVal) !== trim((string) $remoteVal))) {
                $this->proposedUpdates[$crmField] = $localVal;
            }
        }

        // Actually update the remote account
        if (count($this->proposedUpdates) > 0 && $updateIt) {

            // Update account
            $acctResult = $this->webService->patchUrl($this->endPoint . 'sobjects/Contact/' . $accountData['Id'], $this->proposedUpdates);

            if ($this->checkError($acctResult)) {
                $msg = $this->errorMessage;
            } else {
                $msg = "({$localData['hhk_id']}) {$localData['first_name']} {$localData['last_name']} is Updated: ";
                foreach ($this->proposedUpdates as $k => $v) {
                    $msg .= "$k was " . ($accountData[$k] == '' ? '-empty-' : $accountData[$k]) . ", now $v; ";
                }
            }
        }

        return $msg;
    }

    /**
     * Summary of checkError
     * @param mixed $result
     * @return bool
     */
    protected function checkError($result) {

        if (isset($result['errors']) && count($result['errors']) > 0) {

            foreach($result['errors'] as $e) {
                $this->errorMessage .= $e . ', ';
            }
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Summary of loadSearchDB - load search record for specified person(s)
     * @param \PDO $dbh
     * @param string $view DB view to use
     * @param array $sourceIds
     * @return PDOStatement|bool|null
     */
    public static function loadSearchDB(\PDO $dbh, string $view, array $sourceIds): bool|PDOStatement|null {

        if ($view == '') {
            return NULL;
        }

        // clean up the ids
        $idList = [];
        foreach ($sourceIds as $s) {
            if (intval($s, 10) > 0){
                $idList[] = intval($s, 10);
            }
        }

        if (count($idList) > 0) {

            $parm = " in (" . implode(',', $idList) . ") ";
            return $dbh->query("SELECT * FROM `$view` WHERE `hhk_id` $parm");

        }

        return NULL;
    }

    /**
     * Summary of loadSourceDB - Load the "data" record for idName.
     * @param \PDO $dbh
     * @param int $idName Id of person to get
     * @param string $view Database view to use
     * @param array $extraSourceCols
     * @return mixed
     */
    public function loadSourceDB(\PDO $dbh, int $idName, string $view, array $extraSourceCols = []) {

        $parm = intval($idName, 10);

        if ($view == '') {
            return NULL;
        }

        if ($parm > 0) {

            $stmt = $dbh->query("SELECT * FROM `$view` WHERE `hhk_id` = $parm");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) == 0) {
                return NULL;
            }

            if (count($extraSourceCols) > 0) {
                foreach ($extraSourceCols as $k => $v) {
                    $rows[0][$k] = $v;
                }
            }

            $rows[0]['first_name']  = $this->unencodeHTML($rows[0]['first_name']);
            $rows[0]['middle_name'] = $this->unencodeHTML($rows[0]['middle_name']);
            $rows[0]['last_name']   = $this->unencodeHTML($rows[0]['last_name']);
            $rows[0]['nickname']    = $this->unencodeHTML($rows[0]['nickname']);

            return $rows[0];
        }

        return NULL;
    }

    /**
     * Search the remote SF system for a person using the canonical local row.
     * SELECT uses all mapped Contact fields; WHERE uses SF Id when known, else HHK Id.
     * @param array $r canonical vguest_canonical row
     * @return array
     */
    protected function searchTarget(array $r): array {

        $type = 'Contact.';

        // SELECT: mapped Contact fields + identity fields
        $crmFields = array_unique(['Id', 'HHK_idName__c', ...$this->fieldMapper->getCrmFields('Contact')]);
        $fields    = implode(',', array_map(fn($f) => "$type$f", $crmFields));

        // WHERE: search by SF Id when available, else by HHK Id
        $where = $r['external_id'] !== ''
            ? "{$type}Id='{$r['external_id']}'"
            : "{$type}HHK_idName__c={$r['hhk_id']}";

        return $this->webService->search("SELECT $fields FROM Contact WHERE $where LIMIT 10", $this->queryEndpoint);
    }

    /**
     * Summary of searchQuery
     * @param string $select
     * @param string $from
     * @param string $where
     * @return mixed
     */
    public function searchQuery(string $select, string $from, string $where) {

        if ($where != '') {
            $where = " WHERE " . $where;
        }
        return $this->webService->search("SELECT $select FROM $from $where LIMIT 100", $this->queryEndpoint);

    }

    /**
     * Maps canonical vguest_canonical field names to human-readable display labels
     * used in the exportMembers result table.
     */
    protected static function getReturnFields(): array {
        return [
            'external_id'         => 'Id',
            'hhk_id'              => 'HHK Id',
            'prefix'              => 'Prefix',
            'first_name'          => 'Name',
            'middle_name'         => 'Middle',
            'last_name'           => 'Last Name',
            'suffix'              => 'Suffix',
            'nickname'            => 'Nickname',
            'gender'              => 'Gender',
            'birthdate'           => 'Birthdate',
            'address.home.street'      => 'Street',
            'address.home.city'        => 'City',
            'address.home.state'       => 'State',
            'address.home.postal_code' => 'Zip',
            'home_phone'               => 'Home Phone',
            'email'                    => 'Email',
            'contact_type'             => 'Type',
            'is_deceased'              => 'Deceased',
        ];
    }

    private const DEFAULT_CUSTOM_FIELDS = [
        [
            'object'     => 'Contact',
            'field_name' => 'HHK_idName',
            'label'      => 'HHK ID',
            'type'       => 'Text',
            'length'     => 255,
            'unique'     => true,
            'externalId' => true,
            'required'   => true,
        ],
        [
            'object'     => 'Account',
            'field_name' => 'HHK_idPsg',
            'label'      => 'HHK PSG ID',
            'type'       => 'Text',
            'length'     => 255,
            'unique'     => true,
            'externalId' => true,
            'required'   => true,
        ],
    ];

    private const CUSTOM_FIELD_TYPES = [
        'Text'     => 'Text',
        'Number'   => 'Number',
        'Checkbox' => 'Checkbox',
        'Date'     => 'Date',
        'DateTime' => 'Date/Time',
        'Email'    => 'Email',
        'Phone'    => 'Phone',
        'Picklist' => 'Picklist',
        'TextArea' => 'Text Area',
        'Url'      => 'URL',
    ];

    private const CUSTOM_FIELD_OBJECTS = ['Contact', 'Account', 'npe4__Relationship__c'];
    protected function showCustomFieldsSection(): string {

        $sfTypeLabels = [
            'string' => 'Text', 'double' => 'Number', 'int' => 'Number',
            'boolean' => 'Checkbox', 'date' => 'Date', 'datetime' => 'Date/Time',
            'email' => 'Email', 'phone' => 'Phone', 'textarea' => 'Text Area',
            'url' => 'URL', 'picklist' => 'Picklist',
        ];

        $tbl = new HTMLTable();
        $tbl->addHeaderTr(
            HTMLTable::makeTh('Object')
            . HTMLTable::makeTh('Field Name')
            . HTMLTable::makeTh('Label')
            . HTMLTable::makeTh('Type')
            . HTMLTable::makeTh('Length')
            . HTMLTable::makeTh('Unique', ['class' => 'text-center'])
            . HTMLTable::makeTh('External ID', ['class' => 'text-center'])
            . HTMLTable::makeTh('', ['class' => 'text-center'])
        );

        $defaultKeys = [];
        foreach (self::DEFAULT_CUSTOM_FIELDS as $df) {
            $apiName = $df['field_name'] . '__c';
            $defaultKeys[$df['object'] . '.' . $apiName] = true;
            $exists = isset(($this->objectFields[$df['object']] ?? [])[$apiName]);

            $statusContent = $exists
                ? HTMLContainer::generateMarkup('span', 'Exists', ['style' => 'color:#28a745;font-weight:bold;font-size:0.85em;'])
                : HTMLContainer::generateMarkup('span', 'Missing', ['style' => 'color:#dc3545;font-weight:bold;font-size:0.85em;']);
            $statusContent .= ' ' . HTMLContainer::generateMarkup('span', 'Required', ['style' => 'color:#c00;font-weight:bold;font-size:0.85em;']);

            $tbl->addBodyTr(
                HTMLTable::makeTd($df['object'])
                . HTMLTable::makeTd($apiName)
                . HTMLTable::makeTd($df['label'])
                . HTMLTable::makeTd(self::CUSTOM_FIELD_TYPES[$df['type']] ?? $df['type'])
                . HTMLTable::makeTd((string)$df['length'])
                . HTMLTable::makeTd($df['unique'] ? 'Yes' : 'No', ['class' => 'text-center'])
                . HTMLTable::makeTd($df['externalId'] ? 'Yes' : 'No', ['class' => 'text-center'])
                . HTMLTable::makeTd($statusContent, ['class' => 'text-center']),
                ['data-required' => '1']
            );
        }

        foreach (self::CUSTOM_FIELD_OBJECTS as $obj) {
            foreach (($this->hhkFieldDetails[$obj] ?? []) as $apiName => $detail) {
                if (isset($defaultKeys[$obj . '.' . $apiName])) {
                    continue;
                }

                $typeLabel = $sfTypeLabels[$detail['type']] ?? ucfirst($detail['type']);
                $lengthStr = ($detail['length'] > 0 && $detail['type'] !== 'boolean') ? (string)$detail['length'] : '';
                $statusContent = HTMLContainer::generateMarkup('span', 'Exists', ['style' => 'color:#28a745;font-weight:bold;font-size:0.85em;']);

                $tbl->addBodyTr(
                    HTMLTable::makeTd($obj)
                    . HTMLTable::makeTd(htmlspecialchars($apiName))
                    . HTMLTable::makeTd(htmlspecialchars(string: $detail['label']))
                    . HTMLTable::makeTd($typeLabel)
                    . HTMLTable::makeTd($lengthStr)
                    . HTMLTable::makeTd($detail['unique'] ? 'Yes' : 'No', ['class' => 'text-center'])
                    . HTMLTable::makeTd($detail['externalId'] ? 'Yes' : 'No', ['class' => 'text-center'])
                    . HTMLTable::makeTd($statusContent, ['class' => 'text-center'])
                );
            }
        }

        $content = HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('h4', 'Custom Fields')
            . $tbl->generateMarkup([], ''),
            ['class' => 'ui-widget ui-widget-content ui-corner-all p-2 mb-3 mr-2']
        );

        return HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('h3', 'Custom Fields', ['style' => 'border-top:2px solid black;', 'class' => 'mt-3 pt-2 mb-2'])
            . HTMLContainer::generateMarkup('p', 'HHK custom fields found on Salesforce objects.', ['class' => 'mb-2', 'style' => 'font-size:0.9em;color:#555;'])
            . $content,
            []
        );
    }


    /**
     * Summary of showConfig
     * @param \PDO $dbh
     * @return string
     */
    public function showConfig(\PDO $dbh): string {

        $markup = $this->showSetupGuide();
        $markup .= $this->showGatewayCredentials();

        try {
            $markup .= $this->createFieldMappingSection($dbh);
        } catch (Exception) {
        }

        $markup .= $this->showCustomFieldsSection();
        $markup .= $this->showMaintenanceSection();

        return $markup;
    }

    protected function showMaintenanceSection(): string {
        if(SecurityComponent::is_TheAdmin()){
            $tbl = new HTMLTable();
            $tbl->addBodyTr(
                HTMLTable::makeTh('Maintenance', ['style' => 'border-top:2px solid black;'])
                . HTMLTable::makeTd(
                    HTMLInput::generateMarkup('Fix Inverse Relationships', ['type' => 'submit', 'name' => '_fixInverseRelations', 'class' => 'btn btn-warning btn-sm'])
                    . HTMLContainer::generateMarkup('span', ' Corrects existing relationship records where Contact and Related Contact are swapped. Run once after initial sync.', ['class' => 'ml-2']),
                    ['style' => 'border-top:2px solid black;']
                )
            );
            return $tbl->generateMarkup(['style' => 'margin-top:15px;']);
        }else{
            return '';
        }
    }

    protected function showSetupGuide(): string {

        $btn = HTMLInput::generateMarkup('Setup Guide', [
            'type'  => 'button',
            'id'    => 'btnSfSetupGuide',
            'class' => 'ui-button ui-corner-all ui-widget',
        ]);

        $body = <<<'HTML'
<div id="sfSetupGuideDialog" style="display:none; font-size:0.9em; line-height: 1.2em;" class="user-agent-spacing">

<h4>1. Create an External Client App</h4>
<ol>
<li>In Salesforce, go to <strong>Setup &rarr; Apps &rarr; External Client Apps &rarr; External Cleint App Manager &rarr; New Connected App</strong>.</li>
<li>Fill in a name (e.g. &ldquo;HHK Integration&rdquo;) and contact email.</li>
<li>Under <strong>API (Enable OAuth Settings)</strong>, check <em>Enable OAuth Settings</em>.</li>
<li>Set the <strong>Callback URL</strong> to your HHK site URL (e.g. <code>https://yoursite.hospitalityhousekeeper.net</code>). This is not used for HHK but is required by Salesforce.</li>
<li>Add the following <strong>OAuth Scopes</strong>:
  <ul>
    <li><em>Manage user data via APIs (api)</em></li>
    <li><em>Access the Salesforce API Platform (sfap_api)</em></li>
  </ul>
</li>
<li>Check <strong>Enable Client Credentials Flow</strong>.</li>
<li>Uncheck <strong>Require Proof Key for Code Exchange (PKCE) extention for Supported Authorization Flows</strong></li>
<li>Save, then click <strong>Settings &rarr; OAuth Settings &rarr; Consumer Key and Secret</strong> to retrieve the <em>Consumer Key</em> (Client ID) and <em>Consumer Secret</em>.</li>
</ol>

<h4>2. Configure API Access</h4>
<ol>
<li>Go to Apps &rarr; External Client Apps &rarr; External Client App Manager</strong>, find the app you just created, and click <strong>Policies &rarr; Edit &rarr; OAuth Policies</strong>.</li>
<li>Set <strong>Permitted Users</strong> to <em>All users may self-authorize</em> (or restrict to a profile/permission set).</li>
<li>Check "Enable Client Credentials Flow" and assign a user to "Run As"
<li>Set <strong>IP Relaxation</strong> to <em>Relax IP restrictions</em>.</li>
<li>Set <strong>Refresh Token Policy</strong> to <em>Refresh token is valid until revoked</em>.</li>
</ol>

<h4>3. Required Custom Fields</h4>
<p>HHK requires two custom fields on Salesforce objects. These are listed in the <strong>Custom Fields</strong> section below. Both must exist before transfers will work:</p>
<ul>
<li><strong>Contact.HHK_idName__c</strong>: Text(255), Unique, External ID. Links a Salesforce Contact to an HHK member.</li>
<li><strong>Account.HHK_idPsg__c</strong>: Text(255), Unique, External ID. Links a Salesforce Household Account to an HHK Patient Support Group.</li>
</ul>
<p>Create these fields in Salesforce under <strong>Setup &rarr; Object Manager &rarr; [Object] &rarr; Fields &amp; Relationships &rarr; New</strong>. Ensure field-level security grants visibility to the API user&rsquo;s profile.</p>

<h4>4. Enter Credentials in HHK</h4>
<ol>
<li><strong>Endpoint URL</strong>: Your Salesforce instance URL (e.g. <code>https://yourorg.my.salesforce.com</code>). Do not include a trailing slash.</li>
<li><strong>Client ID</strong>: The Consumer Key from the External Client App</li>
<li><strong>Client Secret</strong>: The Consumer Secret from the External Client App.</li>
<li><strong>Maximum PSGs per batch</strong>: The number of PSGs to transfer in one composite graph request, increasing this value can reduce the number of API calls used when transferring</li>
</ol>

<h4>5. Field Mapping</h4>
<p>After saving credentials, the <strong>Field Mapping</strong> section lets you choose which HHK fields are sent to Salesforce and which Salesforce fields they map to. Fields with picklist (dropdown) values can be mapped value-by-value using the mapping button.</p>

<h4>6. NPSP Relationships (Optional)</h4>
<p>If your org uses the Nonprofit Success Pack (NPSP), enable <strong>Link Households &amp; Relationships</strong> to automatically group contacts into Household Accounts and create <code>npe4__Relationship__c</code> records between guests and patients.</p>

</div>
HTML;

        $script = <<<'JS'
<script>
(function ($) {
    var $dlg = $('#sfSetupGuideDialog').dialog({
        autoOpen: false,
        modal: true,
        width: 700,
        maxHeight: 600,
        title: 'Salesforce Integration Setup Guide',
        buttons: {
            'Close': function () { $(this).dialog('close'); }
        }
    });
    $('#btnSfSetupGuide').on('click', function () { $dlg.dialog('open'); });
}(jQuery));
</script>
JS;

        return HTMLContainer::generateMarkup('div',
            $btn . $body . $script,
            ['class' => 'mb-3']
        );
    }

    /**
     * Summary of showGatewayCredentials
     * @return string
     */
    protected function showGatewayCredentials(): string {

        $tbl = new HTMLTable();

        $tbl->addBodyTr(
            HTMLTable::makeTh('CRM Name', array('style' => 'border-top:2px solid black;'))
            . HTMLTable::makeTd($this->getServiceTitle(), array('style' => 'border-top:2px solid black;'))
            );

        $tbl->addBodyTr(
            HTMLTable::makeTh('CRM Gateway Id', array())
            . HTMLTable::makeTd($this->getGatewayId())
            );
        $tbl->addBodyTr(
            HTMLTable::makeTh('Endpoint URL', array())
            . HTMLTable::makeTd(HTMLInput::generateMarkup($this->getEndpointUrl(), array('name' => '_txtEPurl', 'size' => '100')))
            );

        $tbl->addBodyTr(
            HTMLTable::makeTh('Client Id', array())
            . HTMLTable::makeTd(HTMLInput::generateMarkup($this->getClientId(), array('name' => '_txtclientId', 'size' => '100')))
            );
        $tbl->addBodyTr(
            HTMLTable::makeTh('Client Secret', array())
            . HTMLTable::makeTd(HTMLInput::generateMarkup(($this->getClientSecret() == '' ? '' : self::PW_PLACEHOLDER), array('type' => 'password', 'name' => '_txtclientsecret', 'size' => '100')))
            );

        $tbl->addBodyTr(
            HTMLTable::makeTh('API Version', array())
            . HTMLTable::makeTd(HTMLInput::generateMarkup($this->getApiVersion(), array('name' => '_txtapiVersion', 'size' => '10')))
            );

        $tbl->addBodyTr(
            HTMLTable::makeTh('Maximum PSGs per batch', array())
            . HTMLTable::makeTd(HTMLInput::generateMarkup($this->getMaxPSGsPerBatch(), array('name' => '_txtmaxPSGsPerBatch', 'size' => '10')) . "<span class='ml-2'>Salesforce enforced limit: " . self::MAX_PAYLOAD_GRAPHS . "</span>")
            );

        $linkRelAttrs = ['type' => 'checkbox', 'name' => '_cbLinkRelatives', 'id' => '_cbLinkRelatives'];
        if ($this->getLinkRelatives()) {
            $linkRelAttrs['checked'] = 'checked';
        }
        $tbl->addBodyTr(
            HTMLTable::makeTh('Link Households & Relationships', array())
            . HTMLTable::makeTd(
                HTMLInput::generateMarkup('1', $linkRelAttrs)
                . HTMLContainer::generateMarkup('label', ' Group contacts into Household Accounts and create Relationship records between guests and patients', ['for' => '_cbLinkRelatives', 'class' => 'ml-1'])
            )
        );

        return $tbl->generateMarkup();

    }
    /**
     * Render the picklist value mapping table for one SF field, loaded into the modal via AJAX.
     * List_Name in sf_type_map uses "{sfObject}:{sfField}" format.
     */
    public function getPicklistMapMarkup(\PDO $dbh, string $sfObject, string $sfField, string $hhkField): string {
        if (!isset($this->picklists[$sfObject])) {
            try {
                $this->fetchObjectDescribe([$sfObject]);
            } catch (Exception) {
                return '<p class="text-danger">Could not load Salesforce field values. Verify your connection.</p>';
            }
        }

        $sfValues = $this->picklists[$sfObject][$sfField] ?? [];
        if (empty($sfValues)) {
            return '<p>This Salesforce field has no picklist values.</p>';
        }

        $listName = "{$sfObject}:{$sfField}";

        $stmt = $dbh->prepare("SELECT `HHK_Type_Code`, `SF_Type_Code` FROM `sf_type_map` WHERE `List_Name` = :ln");
        $stmt->execute(['ln' => $listName]);
        $existing = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $existing[$r['HHK_Type_Code']] = $r['SF_Type_Code'];
        }

        $sfOptions = [['', '-- None --']];
        foreach ($sfValues as $sfCode => $sfLabel) {
            $sfOptions[] = [$sfCode, "{$sfLabel} ({$sfCode})"];
        }

        $hhkLookupTable = static::getHhkPicklistTable($hhkField);
        $hhkRows = $hhkLookupTable !== ''
            ? HTMLSelector::removeOptionGroups(Common::readGenLookupsPDO($dbh, $hhkLookupTable))
            : [];

        $tbl = new HTMLTable();
        $tbl->addHeaderTr(HTMLTable::makeTh('HHK Value') . HTMLTable::makeTh('Salesforce Value'));

        if (!empty($hhkRows)) {
            foreach ($hhkRows as $hhkCode => $hhkItem) {
                $currentSf = $existing[$hhkCode] ?? '';
                $tbl->addBodyTr(
                    HTMLTable::makeTd(htmlspecialchars($hhkItem[1]))
                    . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                        HTMLSelector::doOptionsMkup($sfOptions, $currentSf, false),
                        ['name' => "pklmap[{$hhkCode}]", 'id' => false, 'style' => 'width:100%;']
                    ))
                );
            }
        } else {
            $tbl->addBodyTr(HTMLTable::makeTd(
                HTMLContainer::generateMarkup('em', 'No HHK value list is configured for this field.'),
                ['colspan' => '2']
            ));
        }

        return $tbl->generateMarkup([], '');
    }

    /**
     * Persist picklist value mappings POSTed from the modal (AJAX, returns array for JSON encoding).
     * Expects POST: sfObject, sfField, pklmap[hhkCode]=sfCode.
     * @return array{success?:string, error?:string}
     */
    public function savePicklistMap(\PDO $dbh): array {
        $sfObject = filter_input(INPUT_POST, 'sfObject', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        $sfField  = filter_input(INPUT_POST, 'sfField',  FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        $pklmap   = filter_input(INPUT_POST, 'pklmap',   FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?: [];

        if ($sfObject === '' || $sfField === '') {
            return ['error' => 'Missing sfObject or sfField.'];
        }

        $listName = "{$sfObject}:{$sfField}";

        if (!isset($this->picklists[$sfObject])) {
            try {
                $this->fetchObjectDescribe([$sfObject]);
            } catch (Exception) {
                return ['error' => 'Could not load Salesforce picklist values.'];
            }
        }
        $validSfCodes = array_keys($this->picklists[$sfObject][$sfField] ?? []);

        $stmt = $dbh->prepare("SELECT `idSf_type_map`, `HHK_Type_Code` FROM `sf_type_map` WHERE `List_Name` = :ln");
        $stmt->execute(['ln' => $listName]);
        $existing = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $existing[$r['HHK_Type_Code']] = (int) $r['idSf_type_map'];
        }

        $ins = $dbh->prepare(
            "INSERT INTO `sf_type_map` (`List_Name`, `SF_Type_Code`, `SF_Type_Name`, `HHK_Type_Code`)
             VALUES (:ln, :sfCode, :sfName, :hhkCode)
             ON DUPLICATE KEY UPDATE `SF_Type_Code` = VALUES(`SF_Type_Code`), `SF_Type_Name` = VALUES(`SF_Type_Name`)"
        );
        $del = $dbh->prepare("DELETE FROM `sf_type_map` WHERE `idSf_type_map` = :id");
        $count = 0;

        foreach ($pklmap as $hhkCode => $sfCode) {
            $hhkCode = strip_tags(trim((string) $hhkCode));
            $sfCode  = strip_tags(trim((string) $sfCode));

            if ($sfCode === '') {
                if (isset($existing[$hhkCode])) {
                    $del->execute(['id' => $existing[$hhkCode]]);
                    $count++;
                }
            } elseif (\in_array($sfCode, $validSfCodes, true)) {
                $ins->execute([
                    'ln'      => $listName,
                    'sfCode'  => $sfCode,
                    'sfName'  => $this->picklists[$sfObject][$sfField][$sfCode] ?? $sfCode,
                    'hhkCode' => $hhkCode,
                ]);
                $count++;
            }
        }

        return ['success' => "{$count} value mapping(s) saved."];
    }

    /**
     * Render a hidden+checkbox pair that always submits 0 or 1.
     */
    private function makeCheckbox(string $name, bool $checked): string {
        $cbAttrs = ['type' => 'checkbox', 'name' => $name, 'id' => false];
        if ($checked) {
            $cbAttrs['checked'] = 'checked';
        }
        return HTMLInput::generateMarkup('0', ['type' => 'hidden', 'name' => $name, 'id' => false])
            . HTMLInput::generateMarkup('1', $cbAttrs);
    }

    /**
     * Build a single tbody <tr> for the field mapping table.
     *
     * @param string $obj        SF object name (e.g. 'Contact')
     * @param string $hhkField   Canonical HHK field name (current value to pre-select)
     * @param array  $row        crm_field_map DB row
     * @param int    $idx        Row index for input name arrays
     * @param array  $sfFields   SF field name => label from describe (empty if not connected)
     * @param array  $hhkOptions exportable fields: code => [code, label] from gen_lookups
     */
    private function makeFieldMapRow(string $obj, string $hhkField, array $row, int $idx, array $sfFields, array $hhkOptions): string {
        $crmField = $row['crm_field'] ?? '';
        $inExport = (bool) ($row['in_export'] ?? 1);
        $inSearch = (bool) ($row['in_search'] ?? 0);

        $isRequired = \in_array($hhkField, self::REQUIRED_FIELDS[$obj] ?? [], true);
        $isConditional = !$isRequired && \in_array($hhkField, self::CONDITIONAL_REQUIRED_FIELDS[$obj] ?? [], true);

        $hhkOpts = HTMLSelector::doOptionsMkup($hhkOptions, $hhkField, true);
        $hhkCell = HTMLTable::makeTd(
            HTMLSelector::generateMarkup($hhkOpts, ['name' => "fldmap_hhk[{$obj}][{$idx}]", 'id' => false, 'style' => 'max-width:200px;'])
        );

        if (!empty($sfFields)) {
            $opts = HTMLContainer::generateMarkup('option', '-- SF field --', ['value' => '']);
            foreach ($sfFields as $sfName => $sfLabel) {
                $attrs = ['value' => $sfName];
                if ($sfName === $crmField) {
                    $attrs['selected'] = 'selected';
                }
                $opts .= HTMLContainer::generateMarkup('option', htmlspecialchars("{$sfLabel} ({$sfName})"), $attrs);
            }
            $crmInput = HTMLSelector::generateMarkup($opts, ['name' => "fldmap_crm[{$obj}][{$idx}]", 'id' => false, 'style' => 'max-width:280px;']);
        } else {
            $crmInput = HTMLInput::generateMarkup($crmField, ['name' => "fldmap_crm[{$obj}][{$idx}]", 'id' => false, 'size' => '30', 'placeholder' => 'SF field API name']);
        }

        $hasPicklist = !empty(($this->picklists[$obj] ?? [])[$crmField] ?? []);
        $valuesBtn = $hasPicklist
            ? HTMLInput::generateMarkup('Edit Mapping', [
                'type'            => 'button',
                'class'           => 'ui-button ui-corner-all ui-widget ui-button-small hhk-fldmap-picklist',
                'data-sfobject'   => $obj,
                'data-sffield'    => $crmField,
                'data-hhkfield'   => $hhkField,
                'id'              => false,
              ])
            : '';

        if ($isRequired) {
            $actionCell = HTMLTable::makeTd(
                HTMLContainer::generateMarkup('span', 'Required', ['style' => 'color:#c00;font-weight:bold;font-size:0.85em;', 'title' => 'This field is required for the integration to function']),
                ['class' => 'text-center']
            );
        } elseif ($isConditional) {
            $actionCell = HTMLTable::makeTd(
                HTMLContainer::generateMarkup('span', 'Required', [
                    'class' => 'hhk-cond-required-label',
                    'style' => 'color:#c00;font-weight:bold;font-size:0.85em;' . ($this->linkRelatives ? '' : 'display:none;'),
                    'title' => 'Required when creating relationships in Salesforce',
                ])
                . HTMLContainer::generateMarkup('ul',
                    HTMLContainer::generateMarkup('li',
                        HTMLContainer::generateMarkup('span', '', ['class' => 'bi bi-trash3']),
                        ['class' => 'hhk-fldmap-remove ui-corner-all ui-state-default p-1', 'title' => 'Remove mapping']
                    ),
                    ['class' => 'ui-widget ui-helper-clearfix hhk-ui-icons hhk-cond-remove-ui', 'style' => $this->linkRelatives ? 'display:none;' : '']
                ),
                ['class' => 'text-center actionBtns']
            );
        } else {
            $actionCell = HTMLTable::makeTd(
                HTMLContainer::generateMarkup('ul',
                    HTMLContainer::generateMarkup('li',
                        HTMLContainer::generateMarkup('span', '', ['class' => 'bi bi-trash3']),
                        ['class' => 'hhk-fldmap-remove ui-corner-all ui-state-default p-1', 'title' => 'Remove mapping']
                    ),
                    ['class' => 'ui-widget ui-helper-clearfix hhk-ui-icons']
                ),
                ['class' => 'text-center actionBtns']
            );
        }

        $sortHandle = HTMLTable::makeTd(
            HTMLContainer::generateMarkup('span', '', ['class' => 'ui-icon ui-icon-arrowthick-2-n-s']),
            ['class' => 'sort-handle', 'title' => 'Drag to sort', 'style' => 'cursor:move;']
        );

        return $sortHandle
            . $hhkCell
            . HTMLTable::makeTd($crmInput)
            . HTMLTable::makeTd($this->makeCheckbox("fldmap_exp[{$obj}][{$idx}]", $inExport), ['class' => 'text-center'])
            . HTMLTable::makeTd($this->makeCheckbox("fldmap_srch[{$obj}][{$idx}]", $inSearch), ['class' => 'text-center'])
            . HTMLTable::makeTd($valuesBtn, ['class' => 'text-center'])
            . $actionCell;
    }

    /**
     * Render the field mapping configuration section.
     * Fetches live SF field lists from the describe endpoint when connected.
     * HHK field options come from gen_lookups (Table_Name = 'crm_exportable_fields').
     */
    private function createFieldMappingSection(\PDO $dbh): string {
        if ((int) $this->getGatewayId() < 1) {
            return '';
        }

        $objects = [
            'Contact'               => 'Contact',
            'Account'               => 'Account (Household)',
            'npe4__Relationship__c' => 'Relationship',
        ];

        // Load available HHK fields from gen_lookups ordered by Order; Substitute column = optgroup name
        $hhkOptions = Common::readGenLookupsPDO($dbh, 'crm_exportable_fields', 'Order');

        $stmt = $dbh->prepare("SELECT * FROM `crm_field_map` WHERE `gateway_id` = :gw ORDER BY `display_order`, `crm_object`, `hhk_field`");
        $stmt->execute(['gw' => (int) $this->getGatewayId()]);
        $allMappings = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $allMappings[$row['crm_object']][$row['hhk_field']] = $row;
        }

        // One batch request for all three objects instead of three sequential describe calls.
        try {
            $this->fetchObjectDescribe(\array_keys($objects));
        } catch (Exception) {
        }
        $sfFieldsByObject = [];
        foreach (\array_keys($objects) as $obj) {
            $sfFieldsByObject[$obj] = $this->objectFields[$obj] ?? [];
        }

        // Build grouped HHK options for JS: { group: { code: label, ... }, ... }
        $hhkGrouped = [];
        foreach ($hhkOptions as $code => $row) {
            $group = (string) ($row['Substitute'] ?? $row[2] ?? '');
            $hhkGrouped[$group][$code] = $row['Description'] ?? $row[1];
        }
        $hhkFieldsJson = htmlspecialchars(\json_encode($hhkGrouped, \JSON_HEX_QUOT | \JSON_HEX_APOS), \ENT_QUOTES);

        $markup = '';

        foreach ($objects as $obj => $label) {
            $rows     = $allMappings[$obj] ?? [];
            $sfFields = $sfFieldsByObject[$obj];
            $domId    = preg_replace('/[^a-zA-Z0-9]/', '_', $obj);
            $idx      = 0;

            $tbl = new HTMLTable();
            $tbl->addHeaderTr(
                HTMLTable::makeTh('')
                . HTMLTable::makeTh('HHK Field')
                . HTMLTable::makeTh('Salesforce Field')
                . HTMLTable::makeTh('Export', ['class' => 'text-center', 'title' => 'Include this field when pushing records to Salesforce'])
                . HTMLTable::makeTh('Search', ['class' => 'text-center', 'title' => 'Use this field when searching Salesforce for existing contacts'])
                . HTMLTable::makeTh('Dropdown', ['class' => 'text-center', 'title' => 'Map HHK dropdown values to Salesforce picklist values'])
                . HTMLTable::makeTh('')
            );

            foreach ($rows as $hhkField => $row) {
                $isHardRequired = \in_array($hhkField, self::REQUIRED_FIELDS[$obj] ?? [], true);
                $isConditionalField = \in_array($hhkField, self::CONDITIONAL_REQUIRED_FIELDS[$obj] ?? [], true);
                $isRequired = $isHardRequired || ($this->linkRelatives && $isConditionalField);
                $trAttrs = [];
                if ($isRequired) {
                    $trAttrs['data-required'] = '1';
                }
                if ($isConditionalField && !$isHardRequired) {
                    $trAttrs['data-conditional-required'] = '1';
                }
                $tbl->addBodyTr($this->makeFieldMapRow($obj, $hhkField, $row, $idx, $sfFields, $hhkOptions), $trAttrs);
                $idx++;
            }

            $sfFieldsJson = htmlspecialchars(\json_encode($sfFields, \JSON_HEX_QUOT | \JSON_HEX_APOS), \ENT_QUOTES);
            $addBtn = HTMLInput::generateMarkup('+ Add Mapping', [
                'type'           => 'button',
                'class'          => 'ui-button ui-corner-all ui-widget hhk-fldmap-addrow mt-2',
                'data-obj'       => $obj,
                'data-domid'     => $domId,
                'data-sffields'  => $sfFieldsJson,
                'data-hhkfields' => $hhkFieldsJson,
                'data-idx'       => $idx,
                'id'             => "fldmap_add_{$domId}",
            ]);

            $connected = !empty($sfFields) ? '' : HTMLContainer::generateMarkup('small', ' (Connect to Salesforce to enable field lookup)', ['class' => 'text-muted ml-2']);

            $markup .= HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('h4', "{$label}{$connected}")
                . HTMLContainer::generateMarkup('div', $tbl->generateMarkup(['class' => 'sortable'], ''), ['id' => "fldmap_tbl_{$domId}"])
                . $addBtn,
                ['class' => 'ui-widget ui-widget-content ui-corner-all p-2 mb-3 mr-2']
            );
        }

        $markup .= $this->getFieldMapScript();

        $modal = HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('div', '', ['id' => 'sfPicklistModalBody', 'class' => 'hhk-loading']),
            ['id' => 'sfPicklistModal', 'title' => 'Map Picklist Values', 'style' => 'display:none;']
        );

        return HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('h3', 'Field Mapping', ['style' => 'border-top:2px solid black;','class'=>'mt-3 pt-2 mb-2'])
            . HTMLContainer::generateMarkup('div', $markup, ['class' => 'hhk-flex flex-wrap'])
            . $modal,
            []
        );
    }

    private function getFieldMapScript(): string {
        return <<<'JS'
<script>
(function ($) {
    function escHtml(str) {
        return $('<div>').text(String(str)).html();
    }

    // sfFields: flat { name: label } — no grouping needed for SF fields
    function buildFlatSelect(name, fields, placeholder) {
        var opts = '<option value="">' + escHtml(placeholder) + '</option>';
        $.each(fields, function (code, lbl) {
            opts += '<option value="' + escHtml(code) + '">' + escHtml(lbl) + '</option>';
        });
        return '<select name="' + escHtml(name) + '" style="max-width:280px;">' + opts + '</select>';
    }

    // hhkGroups: { groupName: { code: label, ... }, ... } — mirrors Substitute optgroups
    function buildGroupedSelect(name, groups, placeholder) {
        var opts = '<option value="">' + escHtml(placeholder) + '</option>';
        $.each(groups, function (groupName, fields) {
            var inner = '';
            $.each(fields, function (code, lbl) {
                inner += '<option value="' + escHtml(code) + '">' + escHtml(lbl) + '</option>';
            });
            opts += '<optgroup label="' + escHtml(groupName) + '">' + inner + '</optgroup>';
        });
        return '<select name="' + escHtml(name) + '" style="max-width:200px;">' + opts + '</select>';
    }

    function syncConditionalRequired() {
        var linked = $('#_cbLinkRelatives').is(':checked');
        $('tr[data-conditional-required="1"]').each(function () {
            var $row = $(this);
            $row.data('required', linked ? '1' : '');
            $row.find('.hhk-cond-required-label').toggle(linked);
            $row.find('.hhk-cond-remove-ui').toggle(!linked);
        });
    }
    $(document).off('.hhkcondreq').on('change.hhkcondreq', '#_cbLinkRelatives', syncConditionalRequired);
    syncConditionalRequired();

    $('table.sortable tbody').sortable({
        handle: '.sort-handle',
        axis: 'y',
        cursor: 'move',
        update: function () {
            $(this).find('tr').each(function (i) {
                $(this).find('input, select').each(function () {
                    var n = $(this).attr('name');
                    if (n) { $(this).attr('name', n.replace(/\[\d+\]$/, '[' + i + ']')); }
                });
            });
        }
    });

    var $sfPicklistDlg = $('#sfPicklistModal').dialog({
        autoOpen: false,
        modal: true,
        width: 560,
        maxHeight: 520,
        buttons: {
            'Save': function () {
                var dlg  = $(this);
                var data = {
                    cmd:      'savePicklistMap',
                    sfObject: dlg.data('sfObject'),
                    sfField:  dlg.data('sfField'),
                    hhkField: dlg.data('hhkField')
                };
                $('#sfPicklistModalBody select').each(function () {
                    data[$(this).attr('name')] = $(this).val();
                });
                $.post('ws_gen.php', data, function (resp) {
                    if (resp && resp.error) {
                        alert(resp.error);
                    } else {
                        dlg.dialog('close');
                    }
                }, 'json');
            },
            'Cancel': function () { $(this).dialog('close'); }
        }
    });

    $(document).off('.hhkfldmap')
        .on('click.hhkfldmap', '.hhk-fldmap-remove', function () {
            var $row = $(this).closest('tr');
            if ($row.data('required')) { return; }
            $row.remove();
        })
        .on('click.hhkfldmap', '.hhk-fldmap-picklist', function () {
            var btn      = $(this);
            var sfObject = btn.data('sfobject');
            var sfField  = btn.data('sffield');
            var hhkField = btn.data('hhkfield');

            $sfPicklistDlg
                .data('sfObject', sfObject)
                .data('sfField',  sfField)
                .data('hhkField', hhkField)
                .dialog('option', 'title', 'Map dropdown values: ' + hhkField + ' → ' + sfField);

            $('#sfPicklistModalBody').addClass('hhk-loading').empty();
            $sfPicklistDlg.dialog('open');

            $.get('ws_gen.php', { cmd: 'sfPicklistMap', sfObject: sfObject, sfField: sfField, hhkField: hhkField },
                function (html) {
                    $('#sfPicklistModalBody').removeClass('hhk-loading').html(html);
                }
            );
        })
        .on('click.hhkfldmap', '.hhk-fldmap-addrow', function () {
        var btn       = $(this);
        var obj       = btn.data('obj');
        var domId     = btn.data('domid');
        var sfFields  = btn.data('sffields')  || {};
        var hhkGroups = btn.data('hhkfields') || {};
        var idx       = parseInt(btn.data('idx'), 10);
        btn.data('idx', idx + 1);

        var hhkInput = buildGroupedSelect('fldmap_hhk[' + obj + '][' + idx + ']', hhkGroups, '-- HHK field --');

        var hasSfFields = Object.keys(sfFields).length > 0;
        var crmInput = hasSfFields
            ? buildFlatSelect('fldmap_crm[' + obj + '][' + idx + ']', sfFields, '-- SF field --')
            : '<input type="text" name="fldmap_crm[' + obj + '][' + idx + ']" size="30" placeholder="SF field API name">';

        var n = function (suffix) { return 'fldmap_' + suffix + '[' + obj + '][' + idx + ']'; };
        var chk = function (suffix, checked) {
            return '<input type="hidden" name="' + n(suffix) + '" value="0">'
                 + '<input type="checkbox" name="' + n(suffix) + '" value="1"' + (checked ? ' checked' : '') + '>';
        };

        var row = '<tr>'
            + '<td class="sort-handle" title="Drag to sort" style="cursor:move;"><span class="ui-icon ui-icon-arrowthick-2-n-s"></span></td>'
            + '<td>' + hhkInput + '</td>'
            + '<td>' + crmInput + '</td>'
            + '<td class="text-center">' + chk('exp', true)  + '</td>'
            + '<td class="text-center">' + chk('srch', false) + '</td>'
            + '<td></td>'
            + '<td class="text-center"><ul class="ui-widget ui-helper-clearfix hhk-ui-icons"><li class="hhk-fldmap-remove ui-corner-all ui-state-default p-1" title="Remove mapping"><span class="bi bi-trash3"></span></li></ul></td>'
            + '</tr>';

        $('#fldmap_tbl_' + domId + ' tbody').append(row);
        });
}(jQuery));
</script>
JS;
    }

    /**
     * Persist field mapping changes POSTed from createFieldMappingSection.
     * Does a per-object delete-then-reinsert so removed rows are honoured.
     */
    protected function saveFieldMappings(\PDO $dbh): string {
        $gatewayId = (int) $this->getGatewayId();
        if ($gatewayId < 1) {
            return '';
        }

        $objects = ['Contact', 'Account', 'npe4__Relationship__c'];

        $hhkArrays  = filter_input(INPUT_POST, 'fldmap_hhk',  \FILTER_DEFAULT, \FILTER_REQUIRE_ARRAY) ?: [];
        $crmArrays  = filter_input(INPUT_POST, 'fldmap_crm',  \FILTER_DEFAULT, \FILTER_REQUIRE_ARRAY) ?: [];
        $expArrays  = filter_input(INPUT_POST, 'fldmap_exp',  \FILTER_DEFAULT, \FILTER_REQUIRE_ARRAY) ?: [];
        $srchArrays = filter_input(INPUT_POST, 'fldmap_srch', \FILTER_DEFAULT, \FILTER_REQUIRE_ARRAY) ?: [];

        // Check submitted data has a non-empty CRM mapping for a given HHK field
        $hasMappedField = function (string $obj, string $hhkField) use ($hhkArrays, $crmArrays): bool {
            foreach ($hhkArrays[$obj] ?? [] as $i => $hf) {
                if (trim(strip_tags((string) $hf)) === $hhkField) {
                    return trim(strip_tags((string) ($crmArrays[$obj][$i] ?? ''))) !== '';
                }
            }
            return false;
        };

        // Validate required fields before saving
        $errors = [];
        foreach (self::REQUIRED_FIELDS as $reqObj => $reqFields) {
            foreach ($reqFields as $rf) {
                if (!$hasMappedField($reqObj, $rf)) {
                    $errors[] = "{$rf} must be mapped to a Salesforce field for {$reqObj}.";
                }
            }
        }
        if ($this->linkRelatives) {
            foreach (self::CONDITIONAL_REQUIRED_FIELDS as $reqObj => $reqFields) {
                foreach ($reqFields as $rf) {
                    if (!$hasMappedField($reqObj, $rf)) {
                        $errors[] = "{$rf} must be mapped to a Salesforce field for {$reqObj} when Link Households & Relationships is enabled.";
                    }
                }
            }
        }
        if (!empty($errors)) {
            return 'Field mappings not saved. ' . \implode(' ', $errors);
        }

        $del = $dbh->prepare("DELETE FROM `crm_field_map` WHERE `gateway_id` = :gw AND `crm_object` = :obj");
        $ins = $dbh->prepare(
            "INSERT INTO `crm_field_map`
                 (`gateway_id`, `crm_object`, `hhk_field`, `crm_field`, `in_export`, `in_search`, `display_order`)
             VALUES (:gw, :obj, :hhk, :crm, :exp, :srch, :ord)
             ON DUPLICATE KEY UPDATE
                 `crm_field` = VALUES(`crm_field`),
                 `in_export` = VALUES(`in_export`),
                 `in_search` = VALUES(`in_search`),
                 `display_order` = VALUES(`display_order`)"
        );

        $count = 0;

        foreach ($objects as $obj) {
            $hhkFields = $hhkArrays[$obj] ?? [];
            if (empty($hhkFields)) {
                continue;
            }

            $del->execute(['gw' => $gatewayId, 'obj' => $obj]);

            foreach ($hhkFields as $i => $hhkField) {
                $hhkField = trim(strip_tags((string) $hhkField));
                $crmField = trim(strip_tags((string) ($crmArrays[$obj][$i] ?? '')));

                if ($hhkField === '' || $crmField === '') {
                    continue;
                }

                $ins->execute([
                    'gw'   => $gatewayId,
                    'obj'  => $obj,
                    'hhk'  => $hhkField,
                    'crm'  => $crmField,
                    'exp'  => (int) ($expArrays[$obj][$i] ?? 1),
                    'srch' => (int) ($srchArrays[$obj][$i] ?? 0),
                    'ord'  => ($i + 1) * 10,
                ]);
                $count++;
            }
        }

        // Reload FieldMapper with updated mappings
        $this->fieldMapper = new FieldMapper($dbh, $gatewayId);

        return $count > 0 ? "{$count} field mapping(s) saved.  " : '';
    }

    /**
     * Summary of saveCredentials
     * @param \PDO $dbh
     * @param string $username
     * @return string
     */
    protected function saveCredentials(\PDO $dbh, string $username): string {

        $result = '';
        $crmRs = new CmsGatewayRS();

        $rags = [
            '_txtclientsecret' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            '_txtEPurl' => FILTER_SANITIZE_URL,
            '_txtclientId' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            '_txtapiVersion' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            '_txtmaxPSGsPerBatch' => FILTER_SANITIZE_NUMBER_INT

        ];

        $post = filter_input_array(INPUT_POST, $rags);

        // User Id
        if (isset($post['_txtuserId'])) {
            $crmRs->username->setNewVal($post['_txtuserId']);
        }else{
            $crmRs->username->setNewVal('');
        }

        // Password
        if (isset($post['_txtpwd'])) {

            $pw = $post['_txtpwd'];

            if ($pw != '' && $pw != self::PW_PLACEHOLDER) {
                $crmRs->password->setNewVal(Crypto::encryptMessage($pw));
            }


        }else{
            $crmRs->password->setNewVal('');
        }

        // Client Secret
        if (isset($post['_txtclientsecret'])) {

            $pw = $post['_txtclientsecret'];

            if ($pw != '' && $pw != self::PW_PLACEHOLDER) {
                $crmRs->clientSecret->setNewVal(Crypto::encryptMessage($pw));
            }

        }

        // Endpoint URL
        if (isset($post['_txtEPurl'])) {
            $crmRs->endpointUrl->setNewVal($post['_txtEPurl']);
        }

        // Client Id
        if (isset($post['_txtclientId'])) {
            $crmRs->clientId->setNewVal($post['_txtclientId']);
        }

        // Security Token
        if (isset($post['_txtsectoken'])) {
            $crmRs->securityToken->setNewVal($post['_txtsectoken']);
        } else {
            $crmRs->securityToken->setNewVal('');
        }

        // API Version
        if (isset($post['_txtapiVersion'])) {
            $crmRs->apiVersion->setNewVal($post['_txtapiVersion']);
        }

        if(isset($post['_txtmaxPSGsPerBatch'])) {
            $maxPSGs = intval($post['_txtmaxPSGsPerBatch']);
            $crmRs->retryCount->setNewVal($maxPSGs > 0 && $maxPSGs < self::MAX_PAYLOAD_GRAPHS ? $maxPSGs:self::MAX_PAYLOAD_GRAPHS);
        }else{
            $crmRs->retryCount->setNewVal(self::MAX_PAYLOAD_GRAPHS);
        }

        $linkRel = filter_input(INPUT_POST, '_cbLinkRelatives', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $crmRs->userLoginUrl->setNewVal($linkRel ? '1' : '0');

        $crmRs->Updated_By->setNewVal($username);
        $crmRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));


        if ($this->getGatewayId() < 1) {
            // Insert

            $crmRs->Gateway_Name->setNewVal($this->getServiceName());
            $idGateway = EditRS::insert($dbh, $crmRs);

            if ($idGateway > 0) {
                EditRS::updateStoredVals($crmRs);
                // EditRS::insert doesn't write lastInsertId back onto the RS object, so set it
                // manually before loadCredentials() reads it from $cmsRs->idcms_gateway below.
                $crmRs->idcms_gateway->setStoredVal($idGateway);
                FieldMapper::insertDefaults($dbh, $idGateway, $this->getServiceName());
                $this->fieldMapper = new FieldMapper($dbh, $idGateway);
                $result = $this->getServiceName().' gateway created.  Id = '.$idGateway;
            }

        } else {
            // Update

            $crmRs->Gateway_Name->setStoredVal($this->getServiceName());
            $rc = EditRS::update($dbh, $crmRs, [$crmRs->Gateway_Name]);

            if ($rc > 0) {
                // something updated
                EditRS::updateStoredVals($crmRs);
                $result = $this->getServiceTitle().' gateway Updated.  ';
            } else {
                $result = $this->getServiceTitle() . ' No Updates Found.  ';
            }

            // Seed defaults for existing gateways that predate the field mapping system.
            FieldMapper::insertDefaults($dbh, $this->getGatewayId(), $this->getServiceName());
            // EditRS::updateStoredVals clears idcms_gateway (never assigned a newVal), so
            // re-set it here so loadCredentials doesn't reset $this->gatewayId to 0.
            $crmRs->idcms_gateway->setStoredVal($this->getGatewayId());
        }

        $this->loadCredentials($crmRs);
        return $result;
    }

    /**
     * Summary of saveConfig
     * @param \PDO $dbh
     * @return string
     */
    public function saveConfig(\PDO $dbh): string {

        $uS = Session::getInstance();

        // One-time maintenance action — skip the normal credential save when this button is clicked
        if (filter_input(INPUT_POST, '_fixInverseRelations') !== null) {
            $fixResult = $this->fixInverseRelationships($dbh);
            $result = $fixResult['table'] ?? '';
            foreach ($fixResult['errors'] ?? [] as $err) {
                $result .= HTMLContainer::generateMarkup('p', htmlspecialchars($err), ['class' => 'text-danger']);
            }
            return $result;
        }

        // credentials
        $result = $this->saveCredentials($dbh, $uS->username);
        FieldMapper::insertDefaults($dbh, $this->getGatewayId(), $this->getServiceName());
        $result .= $this->saveFieldMappings($dbh);
        return $result;

    }

    public function getSearchFields(?\PDO $dbh, string $tableName): array {
        $stmt = $dbh->prepare("SELECT `crm_field` FROM `crm_field_map` WHERE `in_search` = 1 AND `gateway_id` = :gatewayId");
        $stmt->execute([':gatewayId' => $this->getGatewayId()]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getLogServiceName(): string{
        return self::LOG_SERVICE_NAME;
    }

    /**
     * Build the SF transfer table grouped by PSG.
     * Each PSG group has a checkbox to select/deselect all members, plus individual member checkboxes.
     *
     * @return array{mkup: string, xfer: list<int>}|false
     */
    /**
     * Maps canonical HHK field names to their vguest_transfer column name and header label.
     * Multiple canonical fields may map to the same column (e.g. name parts → Name).
     * A column appears if any of its contributing fields are in the export map.
     *
     * To add a new field: add it to vguest_transfer, crm_exportable_fields, and this list.
     */
    protected function getTransferColumns(): array {
        return [
            'prefix'                   => ['col' => 'Name',      'header' => 'Name'],
            'first_name'               => ['col' => 'Name',      'header' => 'Name'],
            'middle_name'              => ['col' => 'Name',      'header' => 'Name'],
            'last_name'                => ['col' => 'Name',      'header' => 'Name'],
            'suffix'                   => ['col' => 'Name',      'header' => 'Name'],
            'nickname'                 => ['col' => 'Name',      'header' => 'Name'],
            'address.home.street'      => ['col' => 'Address',   'header' => 'Address'],
            'address.home.city'        => ['col' => 'Address',   'header' => 'Address'],
            'address.home.state'       => ['col' => 'Address',   'header' => 'Address'],
            'address.home.postal_code' => ['col' => 'Address',   'header' => 'Address'],
            'address.home.country'     => ['col' => 'Address',   'header' => 'Address'],
            'home_phone'               => ['col' => 'Phone',     'header' => 'Phone'],
            'email'                    => ['col' => 'Email',     'header' => 'Email'],
            'birthdate'                => ['col' => 'Birthdate', 'header' => 'Birthdate'],
            'gender'                   => ['col' => 'Gender',    'header' => 'Gender'],
            'is_deceased'              => ['col' => 'No Return', 'header' => 'No Return'],
        ];
    }

    public function getTransferReport(\PDO $dbh, string $start, string $end): array|bool {

        $excludeTerm = self::EXCLUDE_TERM;
        $transferIds = [];
        $psgGroups = [];

        $linkRelationships = $this->linkRelatives && $this->fieldMapper->hasObject('npe4__Relationship__c');

        $exportedHhkFields = array_keys($this->fieldMapper->getExportMap('Contact'));
        $allTransferCols = $this->getTransferColumns();
        $visibleColumns = [];
        foreach ($allTransferCols as $hhkField => $colDef) {
            if (\in_array($hhkField, $exportedHhkFields, true) && !isset($visibleColumns[$colDef['col']])) {
                $visibleColumns[$colDef['col']] = $colDef;
            }
        }

        $stmt = $dbh->query(
            "SELECT vt.*, ng.Relationship_Code
             FROM `vguest_transfer` vt
             JOIN `name_guest` ng ON vt.`HHK Id` = ng.idName AND vt.`PSG Id` = ng.idPsg
             WHERE IFNULL(DATE(vt.`Departure`), DATE(NOW())) >= DATE('$start')
               AND DATE(vt.`Arrival`) < DATE('$end')
             GROUP BY vt.`HHK ID`
             ORDER BY vt.`PSG Id`"
        );

        if ($stmt->rowCount() == 0) {
            return false;
        }

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $transferIds[] = $r['HHK Id'];
            $psgId = $r['PSG Id'];

            if ($r['Address'] == ', ,   ') {
                $r['Address'] = '';
            }

            $hhkId = $r['HHK Id'];
            $isPatient = ($r['Relationship_Code'] ?? '') === RelLinkType::Self;

            switch ($r['External Id']) {
                case '':
                    $checked = ($r['Email'] !== '' || ($r['Address'] !== '' && $r['Bad Addr'] === ''));
                    $attrs = ['name' => "tf_{$hhkId}", 'class' => 'hhk-txCbox hhk-tfmem', 'data-txid' => $hhkId, 'data-psg' => $psgId, 'type' => 'checkbox'];
                    if ($checked) { $attrs['checked'] = 'checked'; }
                    if ($isPatient) { $attrs['data-patient'] = '1'; }
                    $r['External Id'] = HTMLInput::generateMarkup('', $attrs);
                    break;
                case $excludeTerm:
                    $r['External Id'] = 'Excluded';
                    break;
                default:
                    $updateAttrs = ['name' => "tf_{$hhkId}", 'style' => 'margin-right:2px;', 'class' => 'hhk-txCbox hhk-tfmem hhk-tf-update', 'data-txid' => $hhkId, 'data-psg' => $psgId, 'type' => 'checkbox', 'checked' => 'checked'];
                    if ($isPatient) { $updateAttrs['data-patient'] = '1'; }
                    $r['External Id'] = HTMLInput::generateMarkup('', $updateAttrs) . $r['External Id'];
                    break;
            }

            $r['_link'] = HTMLContainer::generateMarkup('a', (string) $hhkId, ['href' => 'GuestEdit.php?id=' . $hhkId]);

            if ($r['Birthdate'] != '') {
                $r['Birthdate'] = date('M j, Y', strtotime($r['Birthdate']));
            }

            $psgGroups[$psgId][] = $r;
        }

        $tbl = new HTMLTable();
        $headerCells = HTMLTable::makeTh('PSG')
            . HTMLTable::makeTh('Transfer')
            . HTMLTable::makeTh('HHK Id');
        foreach ($visibleColumns as $colDef) {
            $headerCells .= HTMLTable::makeTh($colDef['header']);
        }
        $tbl->addHeaderTr($headerCells);

        foreach ($psgGroups as $psgId => $members) {
            $first = true;
            foreach ($members as $r) {
                $cells = '';

                if ($first) {
                    $first = false;
                    $psgCb = HTMLInput::generateMarkup('', [
                        'type' => 'checkbox',
                        'class' => 'hhk-txPsg mr-1',
                        'data-psg' => $psgId,
                        'checked' => 'checked',
                    ]);
                    $cells .= HTMLTable::makeTd(
                        $psgCb . HTMLContainer::generateMarkup('label', (string) $psgId),
                        ['rowspan' => count($members), 'style' => 'vertical-align:top;']
                    );
                    $rowStyle = 'border-top: 2px solid #2E99DD;';
                } else {
                    $rowStyle = '';
                }

                $cells .= HTMLTable::makeTd($r['External Id'])
                    . HTMLTable::makeTd($r['_link']);

                foreach ($visibleColumns as $colDef) {
                    $cells .= HTMLTable::makeTd($r[$colDef['col']] ?? '');
                }

                $tbl->addBodyTr($cells, ['class' => 'hhk-psg-' . $psgId, 'style' => $rowStyle]);
            }
        }

        $dataTable = $tbl->generateMarkup(['id' => 'tblrpt']);

        $allorNone = HTMLInput::generateMarkup('All', ['type' => 'button', 'id' => 'hhkdgpallple', 'class' => 'hhk-aon', 'style' => 'margin-right:3px;'])
            . HTMLInput::generateMarkup('None', ['type' => 'button', 'id' => 'hhkdgpnople', 'class' => 'hhk-aon', 'style' => 'margin-right:3px;'])
            . HTMLInput::generateMarkup('Reset', ['type' => 'button', 'id' => 'hhkdgpback', 'class' => 'hhk-aon', 'style' => 'margin-right:3px;'])
            . HTMLInput::generateMarkup('New Only', ['type' => 'button', 'id' => 'hhkdgpnew', 'class' => 'hhk-aon', 'style' => 'margin-right:1px;']);

        $label = HTMLContainer::generateMarkup('span', 'Transfer checkboxes: ');
        $frame = HTMLContainer::generateMarkup('div', $label . $allorNone, ['style' => 'margin-top:1ex; margin-bottom:3px;']);
        $linkRelFlag = HTMLInput::generateMarkup($linkRelationships ? '1' : '0', ['type' => 'hidden', 'id' => 'hlinkRel']);

        return ['mkup' => $frame . $linkRelFlag . $dataTable, 'xfer' => $transferIds];
    }
}

