<?php
namespace HHK\CrmExport\Salesforce;

use HHK\CrmExport\AbstractExportManager;
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
    public function getRelationship($post) {

        $result = $this->retrieveURL($this->endPoint . 'sobjects/npe4__Relationship__c/a0B740000003pmmEAA');

        $parms = array();
        $this->unwindResponse($parms, $result);
        $resultStr = new HTMLTable();

        foreach ($parms as $k => $v) {
            $resultStr->addBodyTr(HTMLTable::makeTd($k, array()) . HTMLTable::makeTd($v));
        }

        return $resultStr->generateMarkup();
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



            // Test results
            if ( isset($rawResult['totalSize']) && $rawResult['totalSize'] == 1 ) {

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


            } else if ( isset($rawResult['totalSize']) && $rawResult['totalSize'] > 1 ) {

                // Multiple records.

                $title = '';
                $options = [];

                // Look through the results
                foreach ($rawResult['records'] as $m) {

                    $name  = $m['FirstName'] . ' ' . ($m['Middle_Name__c'] == '' ? '' : $m['Middle_Name__c'] . ' ') . $m['LastName'] . ' ' . $m['Suffix__c'];
                    $title = ($m['HHK_idName__c'] == '' ? '' : $m['HHK_idName__c'] . ', ') . $name . ($m['Email'] == '' ? '' : ', ' . $m['Email']) . $m['HomePhone'];
                    $options[$m['Id']] = [$m['Id'], $title];

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
                            $f['Result'] = 'Up to date.';
                        }

                        $this->updateLocalExternalId($dbh, $r['HHK_idName__c'], $m['Id']);
                        $f['Id'] = $m['Id'];

                        $replys[$r['HHK_idName__c']] = $f;
                        return $replys;
                    }
                }

                $f['Result'] = ' Found ' . $rawResult['totalSize'] . ' similar accounts ';
                // Create selector
                $f['Result'] .= HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($options, '', TRUE), array('name'=>'selmultimem_' . $r['HHK_idName__c'], 'class'=>'multimemsels'));

                $f['Result'] .= ' Found ' . $rawResult['totalSize'] . ' similar accounts';
                $replys[$r['HHK_idName__c']] = $f;



            } else if ( isset($rawResult['totalSize']) && $rawResult['totalSize'] == 0 ) {

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
                    Continue;
                } else if (isset($row['Id'])) {
                    $row['Id'] = '';
                }

                foreach ($row as $k => $w) {
                    if ($w != '') {
                        $filteredRow[$k] = $w;
                    }
                }

                // Create new account
                $newAcctResult = $this->webService->postUrl($this->endPoint . 'sobjects/Contact/', $filteredRow);

                if ($this->checkError($newAcctResult)) {
                    $f['Result'] = $this->errorMessage;
                    $replys[$r['HHK_idName__c']] = $f;
                    continue;
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
                    $where .= ($where == '' ? $type.$k . "='" . $v . "'" : " AND " . $type.$k . "='" . $v . "'");
                }
            }
        }

        // Id field set?
        if ($r['Id'] !== '') {
            // Blow away the other search terms.
            $where = $type."Id='" . $r['Id'] . "'";
        }


        if ($fields != '' && $where != '') {

            $query = 'Select ' . $fields . ' FROM Contact WHERE ' . $where . ' LIMIT 10';

            $result = $this->webService->search($query, $this->queryEndpoint);

//            if ($r['HHK_idName__c'] == 87) {
 //               var_dump($result);
 //          }
        }

        return $result;

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
        //$cols['Middle_Name__c'] = 'Middle_Name__c';
        $cols['LastName'] = 'LastName';
        $cols['Email'] = 'Email';

        return $cols;
    }

    public static function getReturnFields() {

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

     public static function getUpdateFields() {

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
            . HTMLTable::makeTd(HTMLInput::generateMarkup($this->getPassword(), array('name' => '_txtpwd', 'size' => '100')) . ' (Obfuscated)')
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
            . HTMLTable::makeTd(HTMLInput::generateMarkup($this->getClientSecret(), array('name' => '_txtclientsecret', 'size' => '100')) . ' (Obfuscated)')
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

            if ($pw != '' && $this->getPassword() != $pw) {
                $pw = encryptMessage($pw);
            }

            $crmRs->password->setnewVal($pw);
        }

        // Client Secret
        if (isset($post['_txtclientsecret'])) {

            $pw = $post['_txtclientsecret'];

            if ($pw != '' && $this->getClientSecret() != $pw) {
                $pw = encryptMessage($pw);
            }

            $crmRs->clientSecret->setnewVal($pw);
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
                $result = 'New CMS gateway created.  Id = '.$idGateway;
            }

        } else {
            // Update

            $crmRs->Gateway_Name->setStoredVal($this->getServiceName());
            $rc = EditRS::update($dbh, $crmRs, [$crmRs->Gateway_Name]);

            if ($rc > 0) {
                // something updated
                EditRS::updateStoredVals($crmRs);
                $result = 'New CMS gateway Updated.  ';
            }
        }

        $this->loadCredentials($crmRs);
        return $result;
    }

    /**
     * Summary of saveConfig
     * @param \PDO $dbh
     * @return string
     */
    public function saveConfig(\PDO $dbh) {

        $uS = Session::getInstance();

        // credentials
        return $this->saveCredentials($dbh, $uS->username);

    }
}

