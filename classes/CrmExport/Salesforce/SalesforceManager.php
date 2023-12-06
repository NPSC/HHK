<?php
namespace HHK\CrmExport\Salesforce;

use BadFunctionCallException;
use HHK\CrmExport\AbstractExportManager;
use HHK\Exception\RuntimeException;
use HHK\Exception\UnexpectedValueException;
use HHK\Member\Relation\RelationCode;
use HHK\SysConst\RelLinkType;
use HHK\Tables\CmsGatewayRS;
use HHK\Tables\EditRS;
use HHK\HTMLControls\{HTMLContainer, HTMLTable, HTMLInput, HTMLSelector};
use HHK\sec\Session;
use HHK\CrmExport\OAuth\Credentials;


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

    private $getAcctEndpoint;

    /**
     * {@inheritDoc}
     * @see \HHK\CrmExport\AbstractExportManager::__construct()
     */
    public function __construct(\PDO $dbh, $cmsName) {
        parent::__construct($dbh, $cmsName);

        // build the urls
        $this->endPoint = 'services/data/v' . $this->getApiVersion() . "/";
        $this->queryEndpoint = $this->endPoint . 'query';
        $this->searchEndpoint = $this->endPoint . 'search';
        $this->getAcctEndpoint = $this->endPoint . 'sobjects/Contact/';


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
     * @param mixed $post
     * @return string
     */
    public function getRelationship($accountId) {

        // Cutout to test list.
        if ($accountId == 'picklist') {
            $parms = $this->getRelationshipPicklist();
        } else {
            $result = $this->retrieveURL($this->endPoint . 'sobjects/npe4__Relationship__c' . $accountId);

            $parms = array();
            $this->unwindResponse($parms, $result);

        }

        $resultStr = new HTMLTable();

        foreach ($parms as $k => $v) {
            $resultStr->addBodyTr(HTMLTable::makeTd($k, array()) . HTMLTable::makeTd($v));
        }

        return $resultStr->generateMarkup();
    }

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
                $namArray['Phone'] = isset($r['phone']) ? $r['phone'] : '';
                $namArray['Email'] = isset($r['email']) ? $r['email'] : '';
                $attributes = $r['attributes'];
                $namArray['url'] = $attributes['url'];
                $namArray['Type'] = $attributes['type'];

                $replys[] = $namArray;
            }

            if (count($replys) === 0) {
                $replys[] = array("id" => 0, "value" => "No one found.");
            }

        } else {
            $replys[] = array("id" => 0, "value" => "No one found.");
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

        $source = (isset($parameters['src']) ? $parameters['src'] : '');
        $id = (isset($parameters['accountId']) ? $parameters['accountId'] : '');
        $url = (isset($parameters['url']) ? $parameters['url'] : '');
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
                        $resultStr->addBodyTr(HTMLTable::makeTd($k, array()) . HTMLTable::makeTd('*Excluded*'));
                    } else {
                        $resultStr->addBodyTr(HTMLTable::makeTd($k, array()) . HTMLTable::makeTd($v));
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
                $resultStr->addBodyTr(HTMLTable::makeTd($k, array()) . HTMLTable::makeTd($v));
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

        return $this->retrieveURL($this->getAcctEndpoint . $accountId);
    }

    /**
     * Summary of exportMembers - Export (copy) HHK members to remote system
     * @param \PDO $dbh
     * @param array $sourceIds list of member Id's to export
     * @return array
     */
    public function exportMembers(\PDO $dbh, array $sourceIds, array $updateIds = []) {

        if (count($sourceIds) == 0) {
            $replys[0] = array('error'=>"The list of HHK Id's to send is empty.");
            return $replys;
        }


        // Load search parameters for each source ID
        $stmt = $this->loadSearchDB($dbh, 'vguest_search_sf', $sourceIds);

        if (is_null($stmt)) {
            $replys[0] = array('error'=>'No local records were found.');
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
                    // Account was missing from the Salesforce side.
                    $f['Result'] = 'Account/Contact not found';
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


    public function upsertMembers(\PDO $dbh, array $sourceIds) {

        if (count($sourceIds) == 0) {
            $replys[0] = array('error' => "The list of HHK Id's to send is empty.");
            return $replys;
        }

        // get the member records
        $stmt = $dbh->query("Select * from vguest_data_sf where HHK_idName__c in (" . implode(',', $sourceIds) . ")");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // create graphs

        // People
        foreach ($rows as $row) {
            $filteredRow = [];

            // Check external Id
            if (isset($row['Id']) && $row['Id'] == self::EXCLUDE_TERM) {
                // Skip excluded members.
                Continue;
            }

            foreach ($row as $k => $w) {
                if ($w != '' && $k != 'Relationship_Code' && $k != 'PatientId') {
                    $filteredRow[$k] = $w;
                }
            }

            // Add/update person
            $subrequest[] = [
                "method" => "PATCH",
                "url" => $this->getAcctEndpoint . 'HHK_idName__c/' . $row['HHK_idName__c'],
                "referenceId" => "refContact" . $row['HHK_idName__c'],
                "body" => $filteredRow
            ];
        }

        // Relationships
        foreach ($rows as $row) {
            $relationRow = [];

            // Check external Id
            if(isset($row['Id']) && $row['Id'] == self::EXCLUDE_TERM) {
                // Skip excluded members.
                continue;
            }

            foreach($row as $k => $w) {
                if($w != '' && $k != 'Relationship_Code' && $k != 'PatientId') {
                    $relationRow[$k] = $w;
                }
            }

            if($row['Relationship_Code'] != RelLinkType::Self) {
                // Add relationship record
                $subrequest[] = [
                    "method" => "PATCH",
                    "url" => $this->endPoint.'sobjects/npe4__Relationship__c/',
                    "referenceId" => "refContact".$row['HHK_idName__c'],
                    "body" => $relationRow
                ];
            }


        }

        if (count($subrequest) > 0) {

            $compositRequest["compositeRequest"] = $subrequest;

            return $compositRequest;
        }

        $replys[0] = array('error' => "The list of HHK Id's to send is empty.");
        return $replys;
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

        $cols = array();

        $cols['Id'] = 'Id';
        $cols['FirstName'] = 'FirstName';
        $cols['LastName'] = 'LastName';
        $cols['Email'] = 'Email';

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

        $markup .= $this->createTypeLists($dbh);

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

        $mappedItems = array();
        foreach ($items as $i) {
            $mappedItems[$i['SF_Type_Code']] = $i;
        }

        $postedNames = filter_input_array(INPUT_POST, array('selrelationTypes' => array('filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS, 'flags' => FILTER_FORCE_ARRAY)));
        $matchedNames = $postedNames['selrelationTypes'];

        $usedHhkTypes = [];
        foreach ($matchedNames as $n) {
            if ($n != '') {
                $usedHhkTypes[$n] = $n;
            }
        }

        $updateCount = 0;
        $insertCount = 0;

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

        unset($uS->crmItems);

        return $result . ($updateCount > 0 ? $updateCount.' types updated.  ' : '') . ($insertCount > 0 ? $insertCount . 'new types inserted' : '');
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

