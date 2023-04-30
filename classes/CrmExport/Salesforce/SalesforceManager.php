<?php
namespace HHK\CrmExport\Salesforce;

use HHK\CrmExport\AbstractExportManager;
use HHK\Tables\CmsGatewayRS;
use HHK\Tables\EditRS;
use HHK\HTMLControls\{HTMLTable, HTMLSelector, HTMLInput};
use HHK\sec\Session;
use HHK\CrmExport\OAuth\Credentials;
use HHK\Exception\{RuntimeException};
use GuzzleHttp\Utils;
/**
 *
 * @author Eric
 *
 */
class SalesforceManager extends AbstractExportManager {



    const oAuthEndpoint = 'services/oauth2/token';
    const SearchViewName = 'vguest_search_sf';
    
    private $endPoint;
    private $queryEndpoint;
    private $searchEndpoint;

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

    public function getExplicit(\PDO $dbh, $url, $query = '') {

        $resultStr = '';

        if ($query != '') {
            $results = $this->webService->search($query, $url);
        } else {
            $results = $this->webService->goUrl($url);
        }


        $parms = array();
        $this->unwindResponse($parms, $results);

        foreach ($parms as $k => $v) {
            $resultStr .= $k . '=' . $v . '<br/>';
        }

        return $resultStr;
    }

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
                $reply = 'Error - HHK Id not found';
            } else {
                foreach ($row as $k => $v) {
                    
                    if ($k == 'External_Id' && $v == SELF::EXCLUDE_TERM) {
                        $resultStr->addBodyTr(HTMLTable::makeTd($k, array()) . HTMLTable::makeTd('*Excluded*'));
                    } else {
                        $resultStr->addBodyTr(HTMLTable::makeTd($k, array()) . HTMLTable::makeTd($v));
                    }
                }

                $reply = $resultStr->generateMarkup();
                $this->setAccountId($row['External_Id']);
            }

        } else if ($source == 'remote') {

            //  accounts
            $result = $this->retrieveRemoteAccount($url);

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
    public function retrieveRemoteAccount($url) {

        $results = $this->webService->goUrl($url);

        return $results;
    }

    public function exportMembers(\PDO $dbh, array $sourceIds) {

        $replys = array();

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


        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $f = array();   // output array

            // Prefill output array
            foreach ($r as $k => $v) {

                if ($k != '') {
                    $f[$k] = $v;
                }
            }

            // Search target system
            $result = $this->searchTarget($r);


            // Test results
            if ( isset($result['totalSize']) && $result['totalSize'] == 1 ) {

                // We have a similar contact.  Check for address change

                // Make sure the external Id is defined locally
                if (isset($result['records'][0]['Id']) && $result['records'][0]['Id'] != '') {
                    // This is an Update

                    $this->updateLocalExternalId($dbh, $r['HHK_idName__c'], $result['records'][0]['Id']);
                    $f['Account ID'] = $result['records'][0]['Id'];
                    $f['Result'] = 'Previously Transferred.';
                    $f['Update'] = 'q';

                } else {
                    $f['Result'] = 'The search results Account Id is empty.';
                }

                $replys[$r['HHK_idName__c']] = $f;


            } else if ( isset($result['totalSize']) && $result['totalSize'] > 1 ) {

                // We have more than one contact...
                $f['Result'] = 'There are ' . $result['totalSize'] .' Accounts. No action Taken';
                $replys[$r['HHK_idName__c']] = $f;


            } else if ( isset($result['totalSize']) && $result['totalSize'] == 0 ) {

                // Nothing found - create a new account at remote

                // Get member data record
                $row = $this->loadSourceDB($dbh, $r['HHK_idName__c'], 'vguest_data_sf');

                if (is_null($row)) {
                    continue;
                }

                $filteredRow = [];
                
                // Check external Id
                if (isset($row['External_Id']) && $row['External_Id'] == self::EXCLUDE_TERM) {
                    // Skip excluded members.
                    Continue;
                } else if (isset($row['External_Id'])) {
                    $row['External_Id'] = '';
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

                $accountId = filter_var($newAcctResult['Id'], FILTER_SANITIZE_SPECIAL_CHARS);

                $this->updateLocalExternalId($dbh, $r['HHK_idName__c'], $accountId);

                if ($accountId != '') {
                    $f['Result'] = 'New Salesforce Account';
                } else {
                    $f['Result'] = 'Salesforce Account Missing';
                }
                $f['Account ID'] = $accountId;
                $replys[$r['HHK_idName__c']] = $f;

            } else {

                //huh?
                $f['Result'] = 'API ERROR: The Number of returned records is not defined.';
                $replys[$r['HHK_idName__c']] = $f;
            }

        }

        return $replys;

    }

    public function updateRemoteMember(\PDO $dbh, array $accountData, $idName, $extraSourceCols = [], $updateAddr = TRUE) {

        if ($idName < 1) {
            throw new RuntimeException('HHK Member Id not specified: ' . $idName);
        }


        // Get member data record
        $r = $this->loadSourceDB($dbh, $idName, 'vguest_data_neon', $extraSourceCols);


        if (is_null($r)) {
            throw new RuntimeException('HHK Member Id not found: ' . $idName);
        }

        if (isset($accountData['accountId']) === FALSE) {
            throw new RuntimeException("Remote account id not found for " . $r['firstName'] . " " . $r['lastName'] . ": HHK Id = " . $idName . ", Account Id = " . $r['accountId']);
        }

        if ($r['accountId'] != $accountData['accountId']) {
            throw new RuntimeException("Account Id mismatch: local account Id = " . $r['accountId'] . ", remote account Id = " . $accountData['accountId'] . ", HHK Id = " . $idName);
        }

        $unwound = array();
        $this->unwindResponse($unwound, $accountData);

        $param['individualAccount.accountId'] = $unwound['accountId'];

        // Name, phone, email
        NeonHelper::fillPcName($r, $param, $unwound);

        // Address
        if (isset($r['addressLine1']) && $r['addressLine1'] != '') {

            if ($updateAddr) {
                $r['isPrimaryAddress'] = 'true';
                NeonHelper::fillPcAddr($r, $param, $unwound);
            } else {
                // dont update address from HHK.
                NeonHelper::fillPcAddr(array(), $param, $unwound);
            }

        }

        // Other crap
        NeonHelper::fillOther($r, $param, $unwound);

        // Custom Parameters
        $paramStr = NeonHelper::fillIndividualAccount($r) . NeonHelper::fillCustomFields($r, $unwound);

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);

        $request = array(
            'method' => 'account/updateIndividualAccount',
            'parameters' => $param,
            'customParmeters' => $paramStr
        );

        $result = $this->webService->go($request);

        if ($this->checkError($result)) {
            $msg = $this->errorMessage;
        } else {
            $msg = 'Updated ' . $r['firstName'] . ' ' . $r['lastName'];
        }

        return $msg;

    }
    
    protected function checkError($result) {

        if (isset($result['errors']) && count($result['errors']) > 0) {

            foreach($result['errors'] as $e) {
                $this->errorMessage .= $e;
            }
            return TRUE;
        }

        return FALSE;
    }

    public static function loadSearchDB(\PDO $dbh, $view, $sourceIds) {

        if ($view == '') {
            return NULL;
        }

        // clean up the ids
        foreach ($sourceIds as $s) {
            if (intval($s, 10) > 0){
                $idList[] = intval($s, 10);
            }
        }

        if (count($idList) > 0) {

            $parm = " in (" . implode(',', $idList) . ") ";
            return $dbh->query("Select * from $view where `HHK_idName__c` $parm");

        }

        return NULL;
    }

    protected function searchTarget(array $r) {

        $result = [];
        $fields = '';
        $where = '';
        $searchFields = $this->getSearchFields(NULL, '');

        $type = 'Contact.';

        // Colunm names for $r are also feild names for SF
        foreach ($r as $k => $v) {

            if ($k != '') {

                $fields .= ($fields == '' ? $type.$k : ',' . $type.$k);

                if ($v != '' && isset($searchFields[$k])) {
                    $where .= ($where == '' ? $type.$k . "='" . $v . "'" : " AND " . $type.$k . "='" . $v . "'");
                }
            }
        }

        if ($fields != '' && $where != '') {

            $query = 'Select ' . $fields . ' FROM Contact WHERE ' . $where . ' LIMIT 10';

            $result = $this->webService->search($query, $this->queryEndpoint);

        }

        return $result;

    }

    public static function getSearchFields($dbh, $tableName) {

        $cols = array();

        $cols['Id'] = 'Id';
        $cols['FirstName'] = 'FirstName';
        //$cols['Middle_Name__c'] = 'Middle_Name__c';
        $cols['LastName'] = 'LastName';
        $cols['Email'] = 'Email';

        return $cols;
    }

    public function showConfig(\PDO $dbh) {

        $markup = $this->showGatewayCredentials();

        return $markup;
    }

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

    public function saveConfig(\PDO $dbh) {

        $uS = Session::getInstance();

        // credentials
        return $this->saveCredentials($dbh, $uS->username);

    }

    public function loadSourceDB(\PDO $dbh, $idName, $view, $extraSourceCols = []) {

        $parm = intval($idName, 10);

        if ($view == '') {
            return NULL;
        }

        if ($parm > 0) {

            $stmt = $dbh->query("Select * from $view where HHK_idName__c = $parm");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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

}

