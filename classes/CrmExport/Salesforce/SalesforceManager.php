<?php
namespace HHK\CrmExport\Salesforce;


use HHK\CreateMarkupFromDB;
use HHK\CrmExport\AbstractExportManager;
use HHK\Exception\RuntimeException;
use HHK\SysConst\RelLinkType;
use HHK\Tables\CmsGatewayRS;
use HHK\Tables\EditRS;
use HHK\HTMLControls\{HTMLContainer, HTMLTable, HTMLInput, HTMLSelector};
use HHK\sec\Session;
use HHK\OAuth\Credentials;


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

    const MAX_PAYLOAD_GRAPHS = 70;
    const MAX_NODES = 500;
    const GRAPH_DEPTH = 15;
    const MAX_DIFF_NODES = 15;
    const MAX__GRAPH_FAILS = 14;


    /**
     * Summary of endPoint
     * @var string
     */
    private $endPoint;
    /**
     * Summary of queryEndpoint
     * @var string
     */
    private $queryEndpoint;
    /**
     * Summary of searchEndpoint
     * @var string
     */

     private $searchEndpoint;

    private $getContactEndpoint;

    protected $transferResult;
    protected $errorResult;
    protected $webService;

    protected $uniqueGuests;
    protected $trace;

    /**
     * {@inheritDoc}
     * @see \HHK\CrmExport\AbstractExportManager::__construct()
     */
    public function __construct(\PDO $dbh, $cmsName) {
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
        $credentials->setClientSecret(decryptMessage($this->clientSecret));
        $credentials->setSecurityToken($this->securityToken);
        $credentials->setUsername($this->userId);
        $credentials->setPassword(decryptMessage($this->getPassword()));

        $this->webService = new SF_Connector($credentials);
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
     * Summary of getRelationshipPicklist
     * @return array
     */
    public function getRelationshipPicklist() {

        $result = $this->retrieveURL($this->endPoint . 'sobjects/npe4__Relationship__c/describe');

        $needle = 'fields.15.picklistValues.';
        $parms = [];
        $relatList = [];

        $this->unwindResponse($parms, $result);

        foreach ($parms as $k => $v) {

            if (str_contains($k, $needle)) {

                $fields = explode('.', $k);

                if (isset($fields[4]) && $fields[4] == 'active') {
                    // set the list for this relationship
                    $relatList[$fields[3]] = $v;
                } else if (isset($fields[4]) && $fields[4] == 'value') {
                    $relatList[$fields[3]] = $v;
                }
            }
        }

        return $relatList;
    }

    /**
     * Summary of searchMembers Searches remote with letters from an autocomplete
     * @param mixed $searchCriteria
     * @return array
     */
    public function searchMembers ($searchCriteria) {

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
    public function getMember(\PDO $dbh, $parameters) {

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
    protected function retrieveURL($url) {

        $results = $this->webService->goUrl($url);

        return $results;
    }

    public function retrieveRemoteAccount($accountId) {

        return $this->retrieveURL($this->getContactEndpoint . $accountId);
    }

    /**
     * Summary of exportMembers - Export (copy) HHK members to remote system
     * @param \PDO $dbh
     * @param array $sourceIds list of member Id's to export
     * @return array
     */
    public function exportMembers(\PDO $dbh, array $sourceIds, array $updateIds = []) {

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
                }

                $name = $m['FirstName'] . ' ' . ($m['Middle_Name__c'] == '' ? '' : $m['Middle_Name__c'] . ' ') . $m['LastName'] . ' ' . $m['Suffix__c'];
                $title = ($m['HHK_idName__c'] == '' ? '' : $m['HHK_idName__c'] . ', ') . $name . ($m['Email'] == '' ? '' : ', ' . $m['Email']) . $m['HomePhone'];
                $options[$m['Id']] = [$m['Id'], $title];

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
     * @param bool $linkRelatives
     * @return array
     */
    public function upsertMembers(\PDO $dbh, array $sourceIds, $trace, $linkRelatives = true) {

        $this->uniqueGuests = [];   // Keep track to not repeat a guest upsert into multiple psgs?
        $this->transferResult = [];
        $this->errorResult = [];
        $this->trace = $trace == 'true' ? TRUE : FALSE;

        if (count($sourceIds) == 0) {
            $replys[0] = ['error' => "The list of HHK Id's to send is empty."];
            return $replys;
        }

        // Each PSG uses a compositRequest/Graph to identify members and relationships.
        // GraphId = psgId.

        // get the member records. the rows must be ordered by PSG Id
        $stmt = $dbh->query("Select * from vguest_data_sf where HHK_idName__c in (" . implode(',', $sourceIds) . ") ORDER BY `idPsg`;");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $idPsg = 0;
        $batchRows = [];
        $graphCounter = 0;


        foreach ($rows as $r) {

            if ($idPsg > 0 && $idPsg != $r['idPsg']) {

                // Do we have enough graphs
                if ($graphCounter >= self::MAX_PAYLOAD_GRAPHS || count($batchRows) - 100 >= self::MAX_NODES) {

                    $this->transferBatch($dbh, $batchRows, $linkRelatives);
                    $batchRows = [];
                    $graphCounter = 0;
                }

                $graphCounter++;

            }

            $idPsg = $r['idPsg'];

            $batchRows[] = $r;

        }

        // Anything left?
        if (count($batchRows) > 0) {
            $this->transferBatch($dbh, $batchRows, $linkRelatives);
        }

        // Create an HTML table containing the results
        $result['table'] = CreateMarkupFromDB::generateHTML_Table($this->transferResult, 'tblrpt');

        return $result;
    }

    protected function transferBatch(\PDO $dbh, array $rows, $linkRelatives = true) {

        $psgGuests = [];    // list of guests in PSG
        $psgGraphs = [];  // The collection of psg graphs
        $psgId = 0; // multiple records for each psgId



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

            $body = [
                "graphs" => $psgGraphs,
            ];

            // Transfer this package to SF API
            try {
                $graphsResult = $this->webService->postUrl("{$this->endPoint}composite/graph", $body);

                $this->processGraphsResult($dbh, $graphsResult, $rows);

            } catch (\RuntimeException $ex) {
                $this->errorResult[] = $ex->getMessage();
            }
        }

    }

    /**
     * Summary of createPsgGraph
     * @param mixed $guests  List of Guests in the PSG
     * @param mixed $graphId  PSG Id
     * @return array  The formatted Graph object
     */
    protected function createPsgGraph($guests, $graphId, $linkRelatives) {

        $hasPatient = false;
        $idPatient = 0;
        $subrequests = [];
        $graph = [];

        $additnl = '_' . $graphId;

        // Make Contact subrequests
        foreach ($guests as $g) {

            // Do we have a patient?
            if ($g['Relationship_Code'] == RelLinkType::Self) {
                $hasPatient = true;
                $idPatient = $g['HHK_idName__c'];
            }

            // Don't redefine the guest if already defined in a prevous psg.
            if (isset($this->uniqueGuests[$g['HHK_idName__c']])) {
                continue;
            }

            $this->uniqueGuests[$g['HHK_idName__c']] = 'y';

            // remove extra fields
            foreach ($g as $k => $w) {
                if ($w != '' && $k != 'Relationship_Code' && $k != 'SF_Rel_Type' && $k != 'idPsg' && $k != 'Id' && $k != 'Relationship_Id' && $k != 'HHK_idName__c') {
                    $filteredRow[$k] = $w;
                }
            }

            // Subrequest to upsert guest
            $subrequests[] = [
                "method" => "PATCH",
                "url" => $this->getContactEndpoint . 'HHK_idName__c/' . $g['HHK_idName__c'],
                "referenceId" => "refContact_" . $g['HHK_idName__c'] . $additnl,
                "body" => $filteredRow
            ];
        }


        // If there is a patient, make relationship subrequests
        if ($linkRelatives && $hasPatient && $idPatient > 0) {

            foreach ($guests as $g) {

                if ($g['Relationship_Code'] == RelLinkType::Self) {
                    continue;
                }

                // Only new relationships.
                if ($g['Relationship_Id'] == '') {

                    // build the upsert details file
                    $relationRow['npe4__Contact__c'] = "@{refContact_" . $g['HHK_idName__c'] . $additnl . ".id}";
                    $relationRow['npe4__RelatedContact__c'] = "@{refContact_$idPatient$additnl.id}";
                    $relationRow['npe4__Status__c'] = 'Current';    // 'Current', 'Former'
                    $relationRow['npe4__Type__c'] = $g['SF_Rel_Type'];
                    //$relationRow['Is_an_Emergency_Contact__c']      // t/f
                    $relationRow['Legal_Custody__c'] = $g['Legal_Custody'] == 0 ? 'false' : 'true';       // t/f

                    $subrequests[] = [
                        "method" => "POST",
                        "url" => $this->endPoint . 'sobjects/npe4__Relationship__c/',
                        "referenceId" => "refRel_" . $g['HHK_idName__c'] . $additnl,
                        "body" => $relationRow
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
    protected function processGraphsResult(\PDO $dbh, $graphResult, $guestRows) {

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
     * @param mixed $graph
     * @param mixed $guests
     * @return void
     */
    protected function processCompositeResponse(\PDO $dbh, $graph, $guests) {

        $idPsg = $graph['graphId'];
        $comResp = $graph['graphResponse']['compositeResponse'];
        $isSuccessful = $graph['isSuccessful'];

        // Each compositeSubrequestResult
        foreach ($comResp as $c) {

            $subResponse = AbstractCompositeSubresponse::factory($c, $idPsg, $isSuccessful);

            $guest = $this->findGuest($guests, $idPsg, $subResponse->getidName());


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

            $f['Result'] = $subResponse->processResult($dbh);
            $f['Contact Id'] = $subResponse->getContactId();

            // Collect in one massive result array across batches.
            $this->transferResult[] = $f;
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
     * Summary of updateRemoteMember
     * @param \PDO $dbh
     * @param array $accountData is data returned from remote
     * @param int $idName person to update, 0 -> use localData, > 0 use as index for DB search
     * @param mixed $localData local data for person
     * @param bool $updateIt TRUE = push update to remote, FALSE = just return potential update fields as array.
     * @return string
     */
    public function updateRemoteMember(\PDO $dbh, array $accountData, $idName, $localData = [], $updateIt = FALSE) {

        $msg = 'Already up to date. ';

        $updateFields = $this->getUpdateFields();

        $this->proposedUpdates = [];

        // Load local data if not delivered in the $localData array
        if ($idName > 0) {
            $stmt = $this->loadSearchDB($dbh, 'vguest_search_sf', $idName);
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
     * @param mixed $sourceIds
     * @return \PDOStatement|bool|null
     */
    public static function loadSearchDB(\PDO $dbh, $view, $sourceIds) {

        if ($view == '') {
            return NULL;
        }

        // clean up the ids
        if (is_array($sourceIds)) {

            foreach ($sourceIds as $s) {
                if (intval($s, 10) > 0){
                    $idList[] = intval($s, 10);
                }
            }

        } else {
            $idList[] = intval($sourceIds, 10);
        }

        if (count($idList) > 0) {

            $parm = " in (" . implode(',', $idList) . ") ";
            return $dbh->query("Select * from $view where `HHK_idName__c` $parm");

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
    public function loadSourceDB(\PDO $dbh, $idName, $view, $extraSourceCols = []) {

        $parm = intval($idName, 10);

        if ($view == '') {
            return NULL;
        }

        if ($parm > 0) {

            $stmt = $dbh->query("Select * from $view where HHK_idName__c = $parm");
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

            $query = 'Select ' . $fields . ' FROM Contact WHERE ' . $where . ' LIMIT 10';

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
    public function searchQuery($select, $from, $where) {

        if ($where != '') {
            $where = " WHERE " . $where;
        }
        return $this->webService->search("SELECT $select FROM $from $where LIMIT 100", $this->queryEndpoint);

    }

    /**
     * Summary of getSearchFields
     * @param $dbh
     * @param string $tableName
     * @return array<string>
     */
    public static function getSearchFields($dbh, $tableName) {

        $cols['HHK_idName__c'] = 'HHK_idName__c';
        return $cols;
    }

    protected static function getReturnFields() {

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

     protected static function getUpdateFields() {

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


    /**
     * Summary of showConfig
     * @param \PDO $dbh
     * @return string
     */
    public function showConfig(\PDO $dbh) {

        $markup = $this->showGatewayCredentials();

        try {
            $markup .= $this->createTypeLists($dbh);
        }catch(\Exception $e){

        }

        return $markup;
    }

    /**
     * Summary of showGatewayCredentials
     * @return string
     */
    protected function showGatewayCredentials() {

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
            HTMLTable::makeTh('Username', array())
            . HTMLTable::makeTd(HTMLInput::generateMarkup($this->getUserId(), array('name' => '_txtuserId', 'size' => '90')))
            );
        $tbl->addBodyTr(
            HTMLTable::makeTh('Password', array())
            . HTMLTable::makeTd(HTMLInput::generateMarkup(($this->getPassword() == '' ? '' : self::PW_PLACEHOLDER), array('name' => '_txtpwd', 'size' => '100')))
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
            . HTMLTable::makeTd(HTMLInput::generateMarkup(($this->getClientSecret() == '' ? '' : self::PW_PLACEHOLDER), array('name' => '_txtclientsecret', 'size' => '100')))
            );
        $tbl->addBodyTr(
            HTMLTable::makeTh('Security Token', array())
            . HTMLTable::makeTd(HTMLInput::generateMarkup($this->getSecurityToken(), array('name' => '_txtsectoken', 'size' => '100')))
            );

        $tbl->addBodyTr(
            HTMLTable::makeTh('API Version', array())
            . HTMLTable::makeTd(HTMLInput::generateMarkup($this->getApiVersion(), array('name' => '_txtapiVersion', 'size' => '10')))
            );

        return $tbl->generateMarkup();

    }
    /**
     * Summary of createTypeLists
     * @param \PDO $dbh
     * @return string
     */
    private function createTypeLists(\PDO $dbh) {

        $uS = Session::getInstance();

        $crmItems = $this->getRelationshipPicklist();
        $uS->crmItems = $crmItems;

        $hhkLookup = removeOptionGroups(readGenLookupsPDO($dbh, 'Patient_Rel_Type'));

        $stmtList = $dbh->query("Select * from sf_type_map where List_Name = 'relationTypes'");
        $items = $stmtList->fetchAll(\PDO::FETCH_ASSOC);

        $mappedItems = array();
        foreach ($items as $i) {
            $mappedItems[$i['SF_Type_Code']] = $i;
        }

        $nTbl = new HTMLTable();
        $nTbl->addHeaderTr(HTMLTable::makeTh('HHK Lookup') . HTMLTable::makeTh($this->serviceName . ' Relationship'));

        foreach ($crmItems as $n => $k) {

            $hhkMappedCode = '';
            if (isset($mappedItems[$k])) {
                $hhkMappedCode = $mappedItems[$k]['HHK_Type_Code'];
            }

            $nTbl->addBodyTr(
                HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($hhkLookup, $hhkMappedCode), array('name' => 'selrelationTypes[' . $n . ']')))
                . HTMLTable::makeTd($k)
            );
        }

        $markup = $nTbl->generateMarkup(array('style' => 'margin-top:15px;'), 'relationTypes');


        return $markup;
    }

    /**
     * Summary of saveCredentials
     * @param \PDO $dbh
     * @param string $username
     * @return string
     */
    protected function saveCredentials(\PDO $dbh, $username) {

        $result = '';
        $crmRs = new CmsGatewayRS();

        $rags = [
            '_txtuserId' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            '_txtpwd' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            '_txtclientsecret' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            '_txtEPurl' => FILTER_SANITIZE_URL,
            '_txtclientId' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            '_txtsectoken' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            '_txtapiVersion' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,

        ];

        $post = filter_input_array(INPUT_POST, $rags);

        // User Id
        if (isset($post['_txtuserId'])) {
            $crmRs->username->setNewVal($post['_txtuserId']);
        }

        // Password
        if (isset($post['_txtpwd'])) {

            $pw = $post['_txtpwd'];

            if ($pw != '' && $pw != self::PW_PLACEHOLDER) {
                $crmRs->password->setnewVal(encryptMessage($pw));
            }


        }

        // Client Secret
        if (isset($post['_txtclientsecret'])) {

            $pw = $post['_txtclientsecret'];

            if ($pw != '' && $pw != self::PW_PLACEHOLDER) {
                $crmRs->clientSecret->setnewVal(encryptMessage($pw));
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
        }

        // API Version
        if (isset($post['_txtapiVersion'])) {
            $crmRs->apiVersion->setNewVal($post['_txtapiVersion']);
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
    protected function saveTypeLists(\PDO $dbh) {

        $uS = Session::getInstance();
        $result = '';

        // The list of CRM types should be in the session
        if (isset($uS->crmItems) === false) {
            $result .= 'CRM List Items are missing. ';
        }

        $hhkLookup = removeOptionGroups(readGenLookupsPDO($dbh, 'Patient_Rel_Type'));

        $stmtList = $dbh->query("Select * from sf_type_map where List_Name = 'relationTypes';");
        $items = $stmtList->fetchAll(\PDO::FETCH_ASSOC);

        $mappedItems = [];
        foreach ($items as $i) {
            $mappedItems[$i['SF_Type_Code']] = $i;
        }

        $postedNames = filter_input_array(INPUT_POST, ['selrelationTypes' => ['filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS, 'flags' => FILTER_FORCE_ARRAY]]);
        $matchedNames = $postedNames['selrelationTypes'];

        $updateCount = 0;
        $insertCount = 0;

        // Check input for relationship types selector
        if (is_array($matchedNames)) {

            $usedHhkTypes = [];
            foreach ($matchedNames as $n) {
                if ($n != '') {
                    $usedHhkTypes[$n] = $n;
                }
            }

            foreach ($uS->crmItems as $n => $k) {

                if (isset($matchedNames[$n])) {

                    if ($matchedNames[$n] == '') {
                        // delete if previously set
                        foreach ($mappedItems as $i) {
                            if ($i['SF_Type_Code'] == $k && $i['HHK_Type_Code'] != '') {
                                $dbh->exec("delete from sf_type_map  where idSf_type_map = " . $i['idSf_type_map']);
                                break;
                            }
                        }

                        continue;

                    } else if (isset($hhkLookup[$matchedNames[$n]]) === FALSE) {
                        continue;
                    }

                    if (isset($mappedItems[$k])) {
                        // Update
                        $updateCount += $dbh->exec("update sf_type_map set SF_Type_Code = '$k', SF_Type_name = '$k' where HHK_Type_Code = '$matchedNames[$n]' and List_Name = 'relationTypes';");

                    } else {

                        if (isset($usedHhkTypes[$matchedNames[$n]]) === FALSE) {
                            // Insert
                            $idTypeMap = $dbh->exec("Insert into sf_type_map (List_Name, SF_Type_Code, SF_Type_Name, HHK_Type_Code) "
                                . "values ('relationTypes', '" . $k . "', '" . $k . "', '" . $matchedNames[$n] . "' );");

                            if ($idTypeMap > 0) {
                                $insertCount++;
                                $usedHhkTypes[$matchedNames[$n]] = $matchedNames[$n];
                            }
                        } else {
                            $result .= 'HHK Relationship type already used: ' . $hhkLookup[$matchedNames[$n]][1] . '.  ';
                        }
                    }
                }
            }

        }

        unset($uS->crmItems);

        return $result . ($updateCount > 0 ? "{$updateCount} types updated.  " : '') . ($insertCount > 0 ? "{$insertCount} new types inserted" : '');
    }

    /**
     * Summary of saveConfig
     * @param \PDO $dbh
     * @return string
     */
    public function saveConfig(\PDO $dbh) {

        $uS = Session::getInstance();

        // credentials
        $result = $this->saveCredentials($dbh, $uS->username);
        $result .= $this->saveTypeLists($dbh);
        return $result;

    }
}

