<?php
namespace HHK\CrmExport\Salesforce;


use HHK\Common;
use HHK\CreateMarkupFromDB;
use HHK\CrmExport\AbstractExportManager;
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
use PDOStatement;


/**
 *
 * @author Eric
 *
 */
class SalesforceManager extends AbstractExportManager {


    const oAuthEndpoint = 'services/oauth2/token';
    const SearchViewName = 'vguest_search_sf';
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
     * Fetch picklist lookups from salesforce. Stored as $this->picklists[$object][$fieldName][$value] = $label.
     * @param string $object Salesforce object eg Account, Contact, npe4__Relationship__c
     * @return array
     */
    public function getPicklists(string $object): array {

        $result = $this->retrieveURL($this->endPoint . 'sobjects/'.$object.'/describe');

        $this->picklists[$object] = [];

        if(is_array($result) && isset($result['fields'])) {
            foreach($result['fields'] as $f) {
                if (isset($f['name'], $f['picklistValues']) && \count($f['picklistValues']) > 0) {
                    $fieldValues = [];
                    foreach ($f['picklistValues'] as $pv) {
                        if (isset($pv['active'], $pv['value'], $pv['label']) && $pv['active'] === true) {
                            $fieldValues[$pv['value']] = $pv['label'];
                        }
                    }
                    if (!empty($fieldValues)) {
                        $this->picklists[$object][$f['name']] = $fieldValues;
                    }
                }
            }
        }

        return $this->picklists;
    }

    /**
     * Defines which SF picklist fields to surface in the config UI.
     * Each key becomes the List_Name in sf_type_map and the POST field prefix.
     * Override in a subclass to add more mappings.
     * @return array{label:string, sfObject:string, sfField:string, hhkLookup:string}[]
     */
    protected static function getPicklistFields(): array {
        return [
            'relationTypes' => [
                'label'     => 'Relationship Types',
                'sfObject'  => 'npe4__Relationship__c',
                'sfField'   => 'npe4__Type__c',
                'hhkLookup' => 'Patient_Rel_Type',
            ],
            'salutation' => [
                'label'     => 'Salutation',
                'sfObject'  => 'Contact',
                'sfField'   => 'Salutation',
                'hhkLookup' => 'Name_Prefix',
            ],
        ];
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

            $row = $this->loadSourceDB($dbh, $id, 'vguest_data_sf');

            if (is_null($row)) {
                $reply = 'Error - HHK Id not found, or member is not a guest or patient. ';
                $this->setAccountId('error');
            } else {
                foreach ($row as $k => $v) {

                    if ($k == 'Id' && $v == SELF::EXCLUDE_TERM) {
                        $resultStr->addBodyTr(HTMLTable::makeTd($k, []) . HTMLTable::makeTd('*Excluded*'));
                    } else {
                        $resultStr->addBodyTr(HTMLTable::makeTd($k, []) . HTMLTable::makeTd($v));
                    }
                }

                $reply = $resultStr->generateMarkup();
                $this->setAccountId($row['Id']);
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
        $stmt = $this->loadSearchDB($dbh, 'vguest_search_sf', $sourceIds);

        if (is_null($stmt)) {
            $replys[0] = ['error' => 'No local records were found.'];
            return $replys;
        }

        // Run through the local records.
        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $f = array();   // output fields array
            $searchData = [];

            // Clean up names fresh from the DB
            $r['FirstName'] = $this->unencodeHTML($r['FirstName']);
            $r['Middle_Name__c'] = $this->unencodeHTML($r['Middle_Name__c']);
            $r['LastName'] = $this->unencodeHTML($r['LastName']);


            // Prefill output array
            $rf = $this->getReturnFields();
            foreach ($r as $k => $v) {

                if ($k != '') {

                    $searchData[$k] = $v;

                    // Replace SF column names with better
                    if (isset($rf[$k])) {
                        $f[$rf[$k]] = $v;
                    }
                }
            }

            // Collect address into a single column
            $f['Address'] = $f['Street'] . ', ' . $f['City'] . ', ' . $f['State'] . ', ' . $f['Zip'];
            unset($f['Street'], $f['City'], $f['State'], $f['Zip']);

            // collect name in single column
            $f['Name'] = $f['Name'] . ' ' . ($f['Middle'] == '' ? '' : $f['Middle'] . ' ') . $f['Last Name'] . ' ' . $f['Suffix'] . ($f['Nickname'] == '' ? '' : ', ' . $f['Nickname']);
            unset($f['Middle'], $f['Last Name'], $f['Suffix'], $f['Nickname']);



            // Search target system.  Treat return as user input.
            $rawResult = $this->searchTarget($searchData);

            if ($this->checkError($rawResult)) {
                $f['Result'] = $this->errorMessage;
                $replys[$r['HHK_idName__c']] = $f;
                continue;
            }

            // Need a totalSize
            if (isset($rawResult['totalSize']) === FALSE) {
                $f['Result'] = 'API ERROR: totalSize parameter is missing;  ' . $this->errorMessage;
                $replys[$r['HHK_idName__c']] = $f;
                continue;
            }

            // Test results
            if ($rawResult['totalSize'] == 1 ) {

                // We have a similar contact

                if (isset($rawResult['records'][0]['Id']) && $rawResult['records'][0]['Id'] != '') {
                    // This is an Update

                    $this->updateRemoteMember($dbh, $rawResult['records'][0], 0, $r, FALSE);

                    if (count($this->getProposedUpdates()) > 0) {
                        $f['Result'] = HTMLInput::generateMarkup('', array('id'=>'updt_'.$r['HHK_idName__c'], 'class'=>'hhk-txCbox hhk-updatemem', 'data-txid'=>$r['HHK_idName__c'], 'data-txacct'=>$rawResult['records'][0]['Id'], 'type'=>'checkbox'));
                        $label =  'Updates Proposed: ';
                        foreach ($this->getProposedUpdates() as $k => $v) {
                            $label .= $k . '=' . $v . '; ';
                        }

                        $f['Result'] .= HTMLContainer::generateMarkup('label', $label, array('for'=>'updt_'.$r['HHK_idName__c'], 'style'=>'margin-left:.3em; background-color:#FBF6CD;'));

                    } else {
                        $f['Result'] = 'Up to date.';
                    }

                    // Make sure the external Id is defined locally
                    $this->updateLocalExternalId($dbh, $r['HHK_idName__c'], $rawResult['records'][0]['Id']);
                    $f['Id'] = $rawResult['records'][0]['Id'];



                } else {
                    $f['Result'] = 'The search results Account Id is empty.';
                }

                $replys[$r['HHK_idName__c']] = $f;


            } else if ($rawResult['totalSize'] > 1 ) {

                // Multiple records.

                $title = '';
                $options = [];

                // Look through the results
                foreach ($rawResult['records'] as $m) {

                    // Did we find our HHK ID?
                    if ($m['HHK_idName__c'] != '' && $m['HHK_idName__c'] == $r['HHK_idName__c']) {

                        $this->updateRemoteMember($dbh, $m, 0, $r, FALSE);

                        if (count($this->getProposedUpdates()) > 0) {

                            $f['Result'] = HTMLInput::generateMarkup('', array('id'=>'updt_'.$r['HHK_idName__c'], 'class'=>'hhk-txCbox hhk-updatemem', 'data-txid'=>$r['HHK_idName__c'], 'data-txacct'=>$m['Id'], 'type'=>'checkbox'));
                            $label =  'Updates Proposed: ';
                            foreach ($this->getProposedUpdates() as $k => $v) {
                                $label .= $k . '=' . $v . '; ';
                            }

                            $f['Result'] .= HTMLContainer::generateMarkup('label', $label, array('for'=>'updt_'.$r['HHK_idName__c'], 'style'=>'margin-left:.3em; background-color:#FBF6CD;'));

                        } else {
                            $f['Result'] = 'Up to date. (MR)';
                        }

                        $this->updateLocalExternalId($dbh, $r['HHK_idName__c'], $m['Id']);
                        $f['Id'] = $m['Id'];

                        $replys[$r['HHK_idName__c']] = $f;
                        return $replys;
                    }

                    $name = $m['FirstName'] . ' ' . ($m['Middle_Name__c'] == '' ? '' : $m['Middle_Name__c'] . ' ') . $m['LastName'] . ' ' . $m['Suffix__c'];
                    $title = ($m['HHK_idName__c'] == '' ? '' : $m['HHK_idName__c'] . ', ') . $name . ($m['Email'] == '' ? '' : ', ' . $m['Email']) . $m['HomePhone'];
                    $options[$m['Id']] = [$m['Id'], $title];
                }

                $f['Result'] = ' Found ' . $rawResult['totalSize'] . ' similar accounts ';
                // Create selector
                $f['Result'] .= HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($options, '', TRUE), array('name'=>'selmultimem_' . $r['HHK_idName__c'], 'class'=>'multimemsels'));

                $f['Result'] .= ' Found ' . $rawResult['totalSize'] . ' similar accounts';
                $replys[$r['HHK_idName__c']] = $f;



            } else if ($rawResult['totalSize'] == 0 ) {

                // Check for not finding the account Id
                if ($r['Id'] != '') {
                    // Account was deleted from the Salesforce side.
                    $f['Result'] = 'Account Deleted at Saleforce';
                    $replys[$r['HHK_idName__c']] = $f;
                    continue;
                }


                // Nothing found - create a new account at remote

                // Get member data record
                $row = $this->loadSourceDB($dbh, $r['HHK_idName__c'], 'vguest_data_sf');

                if (is_null($row)) {
                    continue;
                }

                $filteredRow = [];

                // Check external Id
                if (isset($row['Id']) && $row['Id'] == self::EXCLUDE_TERM) {
                    // Skip excluded members.
                    continue;
                } else if (isset($row['Id'])) {
                    // Our local account/contact must be wrong.
                    $row['Id'] = '';
                }

                foreach ($row as $k => $w) {
                    if ($w != '') {
                        $filteredRow[$k] = $w;
                    }
                }

                // Create new account
                try {

                    $newAcctResult = $this->webService->postUrl($this->endPoint . 'sobjects/Contact/', $filteredRow);

                    if ($this->checkError($newAcctResult)) {
                        $f['Result'] = $this->errorMessage;
                        $replys[$r['HHK_idName__c']] = $f;
                        continue;
                    }

                } catch (\RuntimeException $ex) {

                    if (strstr($ex->getMessage(), 'DUPLICATE_VALUE')) {
                        // mark duplicate and continue
                        $f['Result'] = $ex->getMessage();
                        $replys[$r['HHK_idName__c']] = $f;
                        continue;
                    }

                    // Re-throw the exception.
                    throw new \RuntimeException($ex->getMessage());
                }


                $accountId = filter_var($newAcctResult['id'], FILTER_SANITIZE_SPECIAL_CHARS);

                $this->updateLocalExternalId($dbh, $r['HHK_idName__c'], $accountId);

                if ($accountId != '') {
                    $f['Result'] = 'New Salesforce Account';
                } else {
                    $f['Result'] = 'Salesforce Account Missing';
                }
                $f['Id'] = $accountId;
                $replys[$r['HHK_idName__c']] = $f;

            } else {

                $f['Result'] = 'API ERROR: '. $this->errorMessage;

                $replys[$r['HHK_idName__c']] = $f;
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
        $stmt = $dbh->query("SELECT * FROM `vguest_data_sf` WHERE `HHK_idName__c` IN (" . implode(',', $sourceIds) . ") ORDER BY `idPsg`;");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $idPsg = 0;
        $batchRows = [];
        $graphCounter = 1;
        $batches = [];

        foreach ($rows as $r) {

            if ($idPsg > 0 && $idPsg != $r['idPsg']) {

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

            $idPsg = $r['idPsg'];

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

        if ($linkRelatives) {
            $this->verifyRelationshipIds($dbh, $rows);
        }

        // Collect each psg into a guests array and process it as a composit request set to make a graph
        // $rows must be ordered by PSG Id
        foreach ($rows as $r) {

            // New PSG Id?
            if ($psgId > 0 && $r['idPsg'] != $psgId) {

                // Yes, new Id.  Process current psg
                $graph = $this->createPsgGraph($psgGuests, $psgId, $linkRelatives);

                // Add to collection
                if (count($graph) > 0) {
                    $psgGraphs[] = $graph;
                }

                $psgGuests = [];
            }

            $psgId = $r['idPsg'];
            $psgGuests[$r['HHK_idName__c']] = $r;
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
            if ($g['Relationship_Code'] == RelLinkType::Self) {
                $hasPatient = true;
                $idPatient  = $g['HHK_idName__c'];
                break;
            }
        }

        // Use the patient as the primary contact. When SF (NPSP) creates a new Contact it
        // auto-creates a Household Account — we GET that AccountId and use it to link all
        // other guests in this PSG to the same Account.
        $primaryGuest       = $guests[$idPatient] ?? array_values($guests)[0];
        $primaryHhkId       = $primaryGuest['HHK_idName__c'];
        $primarySfId        = $primaryGuest['Id'];
        $primaryIsNew       = ($primarySfId == '');
        $primaryInThisGraph = !isset($this->uniqueGuests[$primaryHhkId]);

        // --- Phase 1: upsert the primary contact ---
        if ($primaryInThisGraph) {
            $this->uniqueGuests[$primaryHhkId] = 'y';
            $contactsInThisGraph[$primaryHhkId] = true;

            $filteredRow = [];
            foreach ($primaryGuest as $k => $w) {
                if ($w != '' && $k != 'Relationship_Code' && $k != 'SF_Rel_Type' && $k != 'Legal_Custody' && $k != 'idPsg' && $k != 'Id' && $k != 'Relationship_Id' && $k != 'HHK_idName__c') {
                    $filteredRow[$k] = $w;
                }
            }

            $subrequests[] = [
                "method" => "PATCH",
                "url" => $this->getContactEndpoint . 'HHK_idName__c/' . $primaryHhkId,
                "referenceId" => "refContact_" . $primaryHhkId . $additnl,
                "body" => $filteredRow
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
                "body" => [
                    "HHK_idPsg__c" => (string)$graphId,
                ]
            ];
        }

        // --- Phase 3: upsert remaining contacts, linked to the primary's Account ---
        foreach ($guests as $g) {

            if ($g['HHK_idName__c'] == $primaryHhkId) {
                continue;
            }

            if (isset($this->uniqueGuests[$g['HHK_idName__c']])) {
                continue;
            }

            $this->uniqueGuests[$g['HHK_idName__c']] = 'y';
            $contactsInThisGraph[$g['HHK_idName__c']] = true;

            // remove extra fields — reset each iteration so no prior guest's data bleeds in
            $filteredRow = [];
            foreach ($g as $k => $w) {
                if ($w != '' && $k != 'Relationship_Code' && $k != 'SF_Rel_Type' && $k != 'Legal_Custody' && $k != 'idPsg' && $k != 'Id' && $k != 'Relationship_Id' && $k != 'HHK_idName__c') {
                    $filteredRow[$k] = $w;
                }
            }

            if ($canGetAccountId) {
                $filteredRow['AccountId'] = '@{' . $accountRefId . '.AccountId}';
            }

            $subrequests[] = [
                "method" => "PATCH",
                "url" => $this->getContactEndpoint . 'HHK_idName__c/' . $g['HHK_idName__c'],
                "referenceId" => "refContact_" . $g['HHK_idName__c'] . $additnl,
                "body" => $filteredRow
            ];
        }


        // If there is a patient, make relationship subrequests
        if ($linkRelatives && $hasPatient && $idPatient > 0) {

            // Resolve the patient's reference once — prefer direct SF ID if their Contact was sent in a prior graph
            if (isset($contactsInThisGraph[$idPatient])) {
                $patientRef = "@{refContact_$idPatient$additnl.id}";
            } elseif (isset($guests[$idPatient]) && $guests[$idPatient]['Id'] != '') {
                $patientRef = $guests[$idPatient]['Id'];
            } else {
                $patientRef = null;  // can't link — patient contact hasn't been synced yet
            }

            foreach ($guests as $g) {

                if ($g['Relationship_Code'] == RelLinkType::Self) {
                    continue;
                }


                // Resolve the guest's reference — prefer direct SF ID if their Contact was sent in a prior graph
                    if (isset($contactsInThisGraph[$g['HHK_idName__c']])) {
                        $guestRef = "@{refContact_" . $g['HHK_idName__c'] . $additnl . ".id}";
                    } elseif ($g['Id'] != '') {
                        $guestRef = $g['Id'];
                    } else {
                        continue;  // guest and patient must both be in SF before a relationship can be created
                    }

                if ($g['Relationship_Id'] == '') {

                    if ($patientRef === null) {
                        continue;
                    }

                    // New relationship — POST with contact references
                    $subrequests[] = [
                        "method" => "POST",
                        "url" => $this->endPoint . 'sobjects/npe4__Relationship__c/',
                        "referenceId" => "refRel_" . $g['HHK_idName__c'] . $additnl,
                        "body" => [
                            'npe4__Contact__c' => $patientRef,
                            'npe4__RelatedContact__c' => $guestRef,
                            'npe4__Status__c' => 'Current',
                            'npe4__Type__c' => $g['SF_Rel_Type'],
                            'Legal_Custody__c' => $g['Legal_Custody'] == 0 ? 'false' : 'true',
                        ]
                    ];

                } else {

                    // Existing relationship — PATCH updatable fields only (contact lookups are immutable)
                    $subrequests[] = [
                        "method" => "PATCH",
                        "url" => $this->endPoint . 'sobjects/npe4__Relationship__c/' . $g['Relationship_Id'],
                        "referenceId" => "refRel_" . $g['HHK_idName__c'] . $additnl,
                        "body" => [
                            'npe4__Status__c' => 'Current',
                            'npe4__Type__c' => $g['SF_Rel_Type'],
                            'Legal_Custody__c' => $g['Legal_Custody'] == 0 ? 'false' : 'true',
                        ]
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
                'HHK Id' => $guest['HHK_idName__c'],
                'Name' => ($guest['Salutation'] == '' ? '' : $guest['Salutation'] . ' ') . $guest['FirstName'] . ' ' . ($guest['Middle_Name__c'] == '' ? '' : $guest['Middle_Name__c'] . ' ') . $guest['LastName'] . ' ' . $guest['Suffix__c'] . ($guest['Nickname__c'] == '' ? '' : ', ' . $guest['Nickname__c']),
                'PSG Id' =>$idPsg,
                'Contact Type' => $guest['Contact_Type__c'],
                'Birthdate' => $guest['Birthdate'] != '' ? date('M j, Y', strtotime($guest['Birthdate'])) : '',
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
            $hhkId = count($guest) > 0 ? $guest['HHK_idName__c'] : '';
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
            if ($g['idPsg'] == $idPsg && $g['HHK_idName__c'] == $idName) {
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
            if (!empty($r['Relationship_Id'])) {
                $relIds[] = $r['Relationship_Id'];
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
            if (!empty($row['Relationship_Id']) && !isset($existingIds[$row['Relationship_Id']])) {
                $this->updateLocalRelationshipId($dbh, (int)$row['HHK_idName__c'], (int)$row['idPsg'], '');
                $row['Relationship_Id'] = '';
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
        $stmt      = $dbh->query("SELECT * FROM `vguest_data_sf` WHERE `Relationship_Id` IS NOT NULL AND `Relationship_Id` != '' ORDER BY `idPsg`");
        $guestRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($guestRows)) {
            return ['table' => '<p>No relationships found to check.</p>', 'errors' => []];
        }

        // Pre-query SF for the Contact side of every stored relationship
        $relIds       = array_values(array_unique(array_column($guestRows, 'Relationship_Id')));
        $relDirections = $this->fetchRelationshipDirections($relIds);

        // Build a psgId → patient SF Contact ID map from the same view
        $psgIds     = array_unique(array_column($guestRows, 'idPsg'));
        $patStmt    = $dbh->query("SELECT `HHK_idName__c`, `Id`, `idPsg`, `Relationship_Code` FROM `vguest_data_sf` WHERE `idPsg` IN (" . implode(',', $psgIds) . ")");
        $patientSfIds = [];
        while ($p = $patStmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($p['Relationship_Code'] == RelLinkType::Self && $p['Id'] !== '') {
                $patientSfIds[$p['idPsg']] = $p['Id'];
            }
        }

        // Build one atomic composite graph per backwards relationship
        $graphs     = [];
        $rowByGraph = [];

        foreach ($guestRows as $r) {

            if ($r['Relationship_Code'] == RelLinkType::Self) {
                continue;
            }

            if ($r['Id'] === '' || ($r['SF_Rel_Type'] ?? '') === '') {
                continue;  // guest not in SF or no type mapping
            }

            $patientSfId = $patientSfIds[$r['idPsg']] ?? null;
            if ($patientSfId === null) {
                continue;  // patient not yet in SF
            }

            $dir = $relDirections[$r['Relationship_Id']] ?? null;
            if ($dir === null) {
                continue;  // relationship no longer exists in SF
            }

            if ($dir['contactId'] === $patientSfId) {
                $transferResult[] = ['HHK Id' => $r['HHK_idName__c'], 'PSG Id' => $r['idPsg'], 'Old Rel Id' => $r['Relationship_Id'], 'New Rel Id' => '', 'Result' => 'Already correct'];
                continue;
            }

            $graphId = $r['HHK_idName__c'] . '_' . $r['idPsg'];

            $graphs[$graphId] = [
                'graphId'          => $graphId,
                'compositeRequest' => [
                    [
                        'method'      => 'DELETE',
                        'url'         => $this->endPoint . 'sobjects/npe4__Relationship__c/' . $r['Relationship_Id'],
                        'referenceId' => 'refDelRel_' . $graphId,
                    ],
                    [
                        'method'      => 'POST',
                        'url'         => $this->endPoint . 'sobjects/npe4__Relationship__c/',
                        'referenceId' => 'refRel_' . $graphId,
                        'body'        => [
                            'npe4__Contact__c'        => $patientSfId,
                            'npe4__RelatedContact__c' => $r['Id'],
                            'npe4__Status__c'         => 'Current',
                            'npe4__Type__c'           => $r['SF_Rel_Type'],
                            'Legal_Custody__c'        => $r['Legal_Custody'] == 0 ? 'false' : 'true',
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
                            $this->updateLocalRelationshipId($dbh, (int)$row['HHK_idName__c'], (int)$row['idPsg'], $newRelId);
                        }

                        $transferResult[] = ['HHK Id' => $row['HHK_idName__c'], 'PSG Id' => $row['idPsg'], 'Old Rel Id' => $row['Relationship_Id'], 'New Rel Id' => $newRelId, 'Result' => 'Fixed'];

                    } elseif ($row !== null) {
                        $error = '';
                        foreach ($graphResult['graphResponse']['compositeResponse'] as $subResp) {
                            if (isset($subResp['body'][0]['errorCode'])) {
                                $error = $subResp['body'][0]['errorCode'] . ': ' . ($subResp['body'][0]['message'] ?? '');
                                break;
                            }
                        }
                        $transferResult[] = ['HHK Id' => $row['HHK_idName__c'], 'PSG Id' => $row['idPsg'], 'Old Rel Id' => $row['Relationship_Id'], 'New Rel Id' => '', 'Result' => 'Error: ' . $error];
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

        $updateFields = $this->getUpdateFields();

        $this->proposedUpdates = [];

        // Load local data if not delivered in the $localData array
        if ($idName > 0) {
            $stmt = $this->loadSearchDB($dbh, 'vguest_search_sf', [$idName]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (isset($rows[0])) {
                $localData = $rows[0];
            }
        }

        // Collect any updates, if any
        foreach ($updateFields as $u) {

            if ((isset($localData[$u]) && $localData[$u] !== '' && isset($accountData[$u]) && trim($localData[$u]) !== trim($accountData[$u]))
                    || (isset($localData[$u]) && $localData[$u] !== '' && isset($accountData[$u]) === FALSE)) {
                $this->proposedUpdates[$u] = $localData[$u];
            }
        }

        // Actually update the remote account
        if (count($this->proposedUpdates) > 0 && $updateIt) {

            // Update account
            $acctResult = $this->webService->patchUrl($this->endPoint . 'sobjects/Contact/' . $accountData['Id'], $this->proposedUpdates);

            if ($this->checkError($acctResult)) {
                $msg = $this->errorMessage;
            } else {
                $msg = '(' . $localData['HHK_idName__c'] . ') ' . $localData['FirstName'] . ' ' . $localData['LastName'] . ' is Updated: ';
                foreach ($this->proposedUpdates as $k => $v) {
                    $msg .= $k . ' was ' . ($accountData[$k] == '' ? '-empty-' : $accountData[$k]) . ', now '. $v . '; ';
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
            return $dbh->query("SELECT * FROM `$view` WHERE `HHK_idName__c` $parm");

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

            $stmt = $dbh->query("SELECT * FROM `$view` WHERE `HHK_idName__c` = $parm");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) == 0) {
                return NULL;
            }

            if (count($extraSourceCols) > 0) {
                foreach ($extraSourceCols as $k => $v) {
                    $rows[0][$k] = $v;
                }
            }

            $rows[0]['FirstName'] = $this->unencodeHTML($rows[0]['FirstName']);
            $rows[0]['Middle_Name__c'] = $this->unencodeHTML($rows[0]['Middle_Name__c']);
            $rows[0]['LastName'] = $this->unencodeHTML($rows[0]['LastName']);
            $rows[0]['Nickname__c'] = $this->unencodeHTML($rows[0]['Nickname__c']);

            return $rows[0];
        }

        return NULL;
    }

    /**
     * Summary of searchTarget - Search the remote system for a specified local person.
     * @param array $r array containing local values for a person
     * @return array
     */
    protected function searchTarget(array $r) {

        $result = [];
        $fields = '';
        $where = '';
        $searchFields = $this->getSearchFields(NULL, '');
        $returnFields = $this->getReturnFields();
        $type = 'Contact.';

        // Colunm names for $r are also feild names for SF
        foreach ($r as $k => $v) {

            if ($k != '') {

                if (isset($returnFields[$k])) {
                    $fields .= ($fields == '' ? $type.$k : ',' . $type.$k);
                }

                if ($v != '' && isset($searchFields[$k])) {
                    $where .= ($where == '' ? '('. $type.$k . "='" . $v . "'" : " AND " . $type.$k . "='" . $v . "'");
                }
            }
        }

        // Id field set?
        if ($r['Id'] !== '') {
            // Blow away the other search terms.
            $where = $type."Id='" . $r['Id'] . "'";
        } else {
            // Check for existing HHK_Id in remote.
            $where .= ($where == '' ? $type."HHK_idName__c=" . $r['HHK_idName__c'] : ") OR " . $type."HHK_idName__c=" . $r['HHK_idName__c']);
        }


        if ($fields != '' && $where != '') {

            $query = 'SELECT ' . $fields . ' FROM `Contact` WHERE ' . $where . ' LIMIT 10';

            $result = $this->webService->search($query, $this->queryEndpoint);

        }

        return $result;
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
     * Summary of getSearchFields
     * @param \PDO|null $dbh
     * @param string $tableName
     * @return array
     */
    public static function getSearchFields(?\PDO $dbh, string $tableName): array {

        $cols['HHK_idName__c'] = 'HHK_idName__c';
        return $cols;
    }

    protected static function getReturnFields(): array {

        return [
            'Id' => 'Id',
            'HHK_idName__c' => 'HHK Id',
            'Salutation' => 'Prefix',
            'FirstName' => 'Name',
            'Middle_Name__c' => 'Middle',
            'LastName' => 'Last Name',
            'Suffix__c' => 'Suffix',
            'Nickname__c' => 'Nickname',
            'Gender__c' => 'Gender',
            'Birthdate' => 'Birthdate',
            'MailingStreet' => 'Street',
            'MailingCity' => 'City',
            'MailingState' => 'State',
            'MailingPostalCode' => 'Zip',
            'HomePhone' => 'Home Phone',
            'Email' => 'Email',
            'Contact_Type__c' => 'Type',
            'Deceased__c' => 'Deceased',
        ];

    }

    protected static function getUpdateFields(): array {

        return [
            'Id',
            'HHK_idName__c',
            'Salutation',
            'FirstName',
            'Middle_Name__c',
            'LastName',
            'Suffix__c',
            'Nickname__c',
            'Gender__c',
            'Birthdate',
            'MailingStreet',
            'MailingCity',
            'MailingState',
            'MailingPostalCode',
            'HomePhone',
            'Email',
            'Contact_Type__c',
            'Deceased__c',
        ];

    }

    public function createCustomFields() {

        $customFields = [
            'Contact.HHK_idName__c' => [
                'FullName' => 'Contact.HHK_idName__c',
                'Metadata' => [
                    'type' => 'Text',
                    'label' => 'HHK ID',
                    'length' => 255,
                    'unique' => true,
                    'externalId' => true
                ]
            ],
            'Account.HHK_idPsg__c' => [
                'FullName' => 'Account.HHK_idPsg__c',
                'Metadata' => [
                    'type' => 'Text',
                    'label' => 'HHK PSG ID',
                    'length' => 255,
                    'unique' => true,
                    'externalId' => true
                ]
            ],
        ];

        foreach ($customFields as $k=>$payload) {
            try {
                $result = $this->webService->postUrl($this->endPoint . 'tooling/sobjects/CustomField', $payload);
            } catch (\RuntimeException $ex) {
                
            }
        }

    } 


    /**
     * Summary of showConfig
     * @param \PDO $dbh
     * @return string
     */
    public function showConfig(\PDO $dbh): string {

        $markup = $this->showGatewayCredentials();

        try {
            $markup .= $this->createTypeLists($dbh);
        }catch(\Exception $e){

        }

        $markup .= $this->showMaintenanceSection();

        return $markup;
    }

    protected function showMaintenanceSection(): string {

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
        return $tbl->generateMarkup();

    }
    /**
     * Summary of createTypeLists
     * Generates one mapping table per entry in getPicklistFields().
     * @param \PDO $dbh
     * @return string
     */
    private function createTypeLists(\PDO $dbh): string {

        $uS = Session::getInstance();
        $markup = '';
        $sessionPicklists = [];

        foreach (static::getPicklistFields() as $listName => $config) {

            if (!isset($this->picklists[$config['sfObject']])) {
                $this->getPicklists($config['sfObject']);
            }

            $sfFieldValues = $this->picklists[$config['sfObject']][$config['sfField']] ?? [];
            $sessionPicklists[$listName] = array_keys($sfFieldValues);

            $sfOptions = ['' => ['', '-- None --']];
            foreach ($sfFieldValues as $sfCode => $sfLabel) {
                $sfOptions[$sfCode] = [$sfCode, $sfLabel];
            }

            $hhkLookup = HTMLSelector::removeOptionGroups(Common::readGenLookupsPDO($dbh, $config['hhkLookup']));

            $stmtList = $dbh->query("SELECT * FROM `sf_type_map` WHERE `List_Name` = '$listName'");
            $mappedItems = [];
            foreach ($stmtList->fetchAll(\PDO::FETCH_ASSOC) as $i) {
                $mappedItems[$i['HHK_Type_Code']] = $i;
            }

            $nTbl = new HTMLTable();
            $nTbl->addHeaderTr(
                HTMLTable::makeTh('HHK ' . $config['label'])
                . HTMLTable::makeTh('Salesforce ' . $config['label'])
            );

            foreach ($hhkLookup as $hhkCode => $hhkItem) {
                $currentSfCode = $mappedItems[$hhkCode]['SF_Type_Code'] ?? '';
                $nTbl->addBodyTr(
                    HTMLTable::makeTd($hhkItem[1])
                    . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                        HTMLSelector::doOptionsMkup($sfOptions, $currentSfCode, TRUE),
                        ['name' => "sel{$listName}[{$hhkCode}]", 'style' => 'width:100%;']
                    ))
                );
            }

            //$markup .= $nTbl->generateMarkup(['style' => 'margin-top:15px;'], $listName);
            $markup .= HTMLContainer::generateMarkup('div', $nTbl->generateMarkup([], $listName), ['class'=>'ui-widget ui-widget-content ui-corner-all p-2 mb-3 mr-2']);
        }

        $uS->crmItems = $sessionPicklists;
        return HTMLContainer::generateMarkup('div', $markup, ['class'=>'hhk-flex mt-2']);
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

        $crmRs->Updated_By->setNewVal($username);
        $crmRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));


        if ($this->getGatewayId() < 1) {
            // Insert

            $crmRs->Gateway_Name->setNewVal($this->getServiceName());
            $idGateway = EditRS::insert($dbh, $crmRs);

            if ($idGateway > 0) {
                EditRS::updateStoredVals($crmRs);
                $this->gatewayId = $idGateway;
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
        }

        $this->loadCredentials($crmRs);
        return $result;
    }

    /**
     * Summary of saveTypeLists
     * @param \PDO $dbh
     * @return string
     */
    protected function saveTypeLists(\PDO $dbh): string {

        $uS = Session::getInstance();

        if (isset($uS->crmItems) === false) {
            return 'CRM List Items are missing. ';
        }

        $upsertCount = 0;

        foreach (static::getPicklistFields() as $listName => $config) {

            $validSfCodes = $uS->crmItems[$listName] ?? [];
            $hhkLookup = HTMLSelector::removeOptionGroups(Common::readGenLookupsPDO($dbh, $config['hhkLookup']));

            $stmtList = $dbh->query("SELECT * FROM `sf_type_map` WHERE `List_Name` = '$listName';");
            $mappedItems = [];
            foreach ($stmtList->fetchAll(\PDO::FETCH_ASSOC) as $i) {
                $mappedItems[$i['HHK_Type_Code']] = $i;
            }

            $postedNames = filter_input_array(INPUT_POST, ["sel{$listName}" => ['filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS, 'flags' => FILTER_FORCE_ARRAY]]);
            $matchedNames = $postedNames["sel{$listName}"] ?? [];

            if (!\is_array($matchedNames)) {
                continue;
            }

            foreach ($hhkLookup as $hhkCode => $hhkItem) {

                if (!isset($matchedNames[$hhkCode])) {
                    continue;
                }

                $sfTypeCode = $matchedNames[$hhkCode];

                if ($sfTypeCode == '') {
                    if (isset($mappedItems[$hhkCode])) {
                        $stmt = $dbh->prepare("DELETE FROM `sf_type_map` WHERE `idSf_type_map` = :id;");
                        $stmt->execute(['id' => $mappedItems[$hhkCode]['idSf_type_map']]);
                    }
                    continue;
                }

                if (!\in_array($sfTypeCode, $validSfCodes, true)) {
                    continue;
                }

                $stmt = $dbh->prepare("INSERT INTO `sf_type_map` (`List_Name`, `SF_Type_Code`, `SF_Type_Name`, `HHK_Type_Code`) VALUES (:listName, :sfCode, :sfName, :hhkCode) ON DUPLICATE KEY UPDATE `SF_Type_Code` = VALUES(`SF_Type_Code`), `SF_Type_Name` = VALUES(`SF_Type_Name`);");
                $stmt->execute([
                    'listName' => $listName,
                    'sfCode'   => $sfTypeCode,
                    'sfName'   => $sfTypeCode,
                    'hhkCode'  => $hhkCode,
                ]);

                if ($dbh->lastInsertId() > 0) {
                    $upsertCount++;
                }
            }
        }

        unset($uS->crmItems);

        return $upsertCount > 0 ? "{$upsertCount} types updated.  " : '';
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
        $result .= $this->saveTypeLists($dbh);
        return $result;

    }

    public function getLogServiceName(): string{
        return self::LOG_SERVICE_NAME;
    }
}

