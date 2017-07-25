<?php
/*
 * TransferMembers.php
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of TransferMembers
 *
 * @author Eric
 */
class TransferMembers {

    protected $webService;
    protected $userId;
    protected $password;
    protected $customFields;
    protected $errorMessage;
    protected $pageNumber;

    public function __construct($userId, $password, array $customFields = array()) {

        $this->userId = $userId;
        $this->password = $password;
        $this->customFields = $customFields;
        $this->errorMessage = '';
        $this->pageNumber = 1;
        $this->txMethod = '';
        $this->txParams = '';
    }

    /** Last name search remote
     *
     * @param string $searchCriteria A set of left most letters to search
     * @return array Array of names as search result
     */
    public function searchAccount($searchCriteria) {

        $replys = array();

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);

        $msearch = new MemberSearch($searchCriteria['letters']);
        $standardFields = array('Account ID', 'Account Type', 'Deceased', 'Prefix', 'First Name', 'Middle Name', 'Last Name', 'Suffix', 'Preferred Name', 'Address Line 1', 'City', 'State', 'Zip Code');

        $search = array(
          'method' => 'account/listAccounts',
          'criteria' => array(
            //array( 'First Name', 'CONTAIN', str_replace('%', '', $msearch->getName_First())),
            array( 'Last Name', 'CONTAIN', str_replace('%', '', $msearch->getName_Last())),
          ),
          'columns' => array(
            'standardFields' => $standardFields,
            'customFields' => array(),
          ),
          'page' => array(
            'currentPage' => $this->pageNumber,
            'pageSize' => 20,
            'sortColumn' => 'Last Name',
            'sortDirection' => 'ASC',
          ),
        );

        $result = $this->webService->search($search);

        if ($this->checkError($result)) {

            $replys['error'] = $this->errorMessage;

        } else if (isset($result['searchResults'])) {

            foreach ($result['searchResults'] as $r) {

                $namArray['id'] = $r["Account ID"];
                $namArray['fullName'] = $r["First Name"] . ' ' . $r["Last Name"];
                $namArray['value'] = ($r['Prefix'] != '' ? $r['Prefix'] . ' ' : '' )
                    . $r["Last Name"] . ", " . $r["First Name"]
                    . ($r['Suffix'] != '' ? ', ' . $r['Suffix'] : '' )
                    . ($r['Preferred Name'] != '' ? ' (' . $r['Preferred Name'] . ')' : '' )
                    . ($r['Deceased'] !== 'No' ? ' [Deceased] ' : '')
                    . ($r['City'] != '' ? '; ' . $r['City'] : '')
                    . ($r['State'] != '' ? ', ' . $r['State'] : '')
                    . ($r['Zip Code'] != '' ? ', ' . $r['Zip Code'] : '');

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
     *
     * @param mixed $accountId Remote account Id
     * @return mixed The Remote account object.
     * @throws Hk_Exception_Runtime
     */
    public function retrieveAccount($accountId) {

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);

        $account = $this->webService->getIndividualAccount($accountId);

        if ($this->checkError($account)) {
            throw new Hk_Exception_Runtime($this->errorMessage);
        }

        return $account;
    }

    /** Update Individual Account including name, phone, address and email
     *
     * @param \PDO $dbh
     * @param array $accountData
     * @param int $idName
     * @return string
     * @throws Hk_Exception_Runtime
     */
    public function updateAccount(\PDO $dbh, $accountData, $idName) {

        if ($idName < 1) {
            throw new Hk_Exception_Runtime('HHK Member Id not specified: ' . $idName);
        }


        // Get member data record
        $r = $this->loadSourceDB($dbh, $idName);
        if (is_null($r)) {
            throw new Hk_Exception_Runtime('HHK Member Id not found: ' . $idName);
        }


        if (isset($accountData['accountId']) === FALSE) {
            throw new Hk_Exception_Runtime('Remote account id not found: ' . $r['accountId']);
        }

        if ($r['accountId'] != $accountData['accountId']) {
            throw new Hk_Exception_Runtime('Account Id mismatch: local Id = ' . $r['accountId'] . ' remote Id = ' . $accountData['accountId']);
        }

        $unwound = array();
        $this->unwindResponse($unwound, $accountData);

        $param['individualAccount.accountId'] = $unwound['accountId'];

        // Name, phone, email
        $this->fillPcName($r, $param, $unwound);

        // Address
        $this->fillPcAddr($r, $param, $unwound);

        // Other crap
        $this->fillOther($r, $param, $unwound);

        $paramStr = $this->fillIndividualAccount($r);

        // Custom Parameters
        $paramStr .= $this->fillCustomFields($r);

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);

        $request = array(
           'method' => 'account/updateIndividualAccount',
           'parameters' => $param,
           'customParmeters' => $paramStr
        );

        $msg = 'Updated ' . $r['firstName'] . ' ' . $r['lastName'];
        $result = $this->webService->go($request);

        if ($this->checkError($result)) {
            $msg = $this->errorMessage;
        }

        return $msg;

    }

    public function unwindResponse(&$line, $results, $prefix = '') {

        foreach ($results as $k => $v) {

            if (is_array($v)) {
                $newPrefix = $prefix . $k . '.';
                $this->unwindResponse($line, $v, $newPrefix);
            } else {
                if (is_bool($v)) {
                    if ($v) {
                        $v = 'true';
                    } else {
                        $v = 'false';
                    }
                }
                $line[$prefix . $k] = $v;
            }
        }

        return;
    }


    public function listCustomFields() {

        $request = array(
            'method' => 'common/listCustomFields',
            'parameters' => array('searchCriteria.component' => 'Account')
        );

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);
        $result = $this->webService->go($request);

        if ($this->checkError($result)) {
            throw new Hk_Exception_Runtime($this->errorMessage);
        }

        if (isset($result['customFields']['customField'])) {
            return $result['customFields']['customField'];
        }

        return array();
    }

    public function getCountryIds() {

        $countries = array();

        $request = array(
            'method' => 'account/listCountries',
        );

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);
        $result = $this->webService->go($request);

        if ($this->checkError($result)) {
            throw new Hk_Exception_Runtime($this->errorMessage);
        }

        if (isset($result['countries']['country'])) {

            foreach ($result['countries']['country'] as $c) {
                $countries[$c['id']] = $c['name'];
            }
        }

        return $countries;
    }

    public function listNeonType($method, $listName, $listItem) {

        $types = array();

        $request = array(
            'method' => $method,
        );

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);
        $result = $this->webService->go($request);

        if ($this->checkError($result)) {
            throw new Hk_Exception_Runtime('Method:' . $method . ', List Name: ' . $listName . ', Error Message: ' .$this->errorMessage);
        }

        if (isset($result[$listName][$listItem])) {

            foreach ($result[$listName][$listItem] as $c) {
                $types[$c['id']] = $c['name'];
            }
        }

        return $types;
    }


    public function recordGuestPayment(\PDO $dbh) {



    }

    /**
     *
     * @param \PDO $dbh
     * @param array $sourceIds
     * @param string $username
     * @return array
     */
    public function sendList(\PDO $dbh, array $sourceIds, $username) {

        $replys = array();

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);

        $stmt = $this->loadSearchDB($dbh, $sourceIds);

        if (is_null($stmt)) {
            return array('error'=>'No local records were found.');
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

            if ($this->checkError($result)) {
                $f['Result'] = $this->errorMessage;
                $replys[] = $f;
                continue;
            }

            // Check for NEON not finding the account Id
            if ( isset($result['page']['totalResults'] ) && $result['page']['totalResults'] == 0 && $r['Account Id'] != '') {

                // search again without the Neon Acct Id
                $r['Account Id'] = '';

                // Search target system
                $result = $this->searchTarget($r);

                if ($this->checkError($result)) {
                    $f['Result'] = $this->errorMessage;
                    $replys[] = $f;
                    continue;
                }

            }

            // Test results
            if ( isset($result['page']['totalResults'] ) && $result['page']['totalResults'] == 1 ) {

                // We have a similar contact.

                // Make sure the external Id is defined locally
                if ($result['searchResults'][0]['Account ID'] != '') {
                    $this->updateLocalNameRecord($dbh, $r['HHK_ID'], $result['searchResults'][0]['Account ID'], $username);
                    $f['Account ID'] = $result['searchResults'][0]['Account ID'];
                }

                $f['Result'] = 'Previously Transferred.';
                $replys[] = $f;


            } else if ( isset($result['page']['totalResults'] ) && $result['page']['totalResults'] > 1 ) {

                // We have more than one contact...
                $f['Result'] = 'Multiple Accounts.';
                $replys[] = $f;


            } else if ( isset($result['page']['totalResults'] ) && $result['page']['totalResults'] == 0 ) {

                // Nothing found - create a new account at remote

                // Get member data record
                $row = $this->loadSourceDB($dbh, $r['HHK_ID']);

                if (is_null($row)) {
                    continue;
                }

                // Create new account
                $result = $this->createAccount($row);

                if ($this->checkError($result)) {
                    $f['Result'] = $this->errorMessage;
                    $replys[] = $f;
                    continue;
                }

                $accountId = filter_var($result['accountId'], FILTER_SANITIZE_SPECIAL_CHARS);

                $this->updateLocalNameRecord($dbh, $r['HHK_ID'], $accountId, $username);

                if ($row['accountId'] != '') {
                    $f['Result'] = 'New NeonCRM Account';
                } else {
                    $f['Result'] = 'New NeonCRM Account';
                }
                $f['Account ID'] = $accountId;
                $replys[] = $f;

            } else {

                //huh?
                $f['Result'] = 'API ERROR: The Number of returned records is not defined.';
                $replys[] = $f;
            }

        }

        return $replys;

    }

    protected function updateLocalNameRecord(\PDO $dbh, $idName, $externalId, $username) {

        if ($externalId != '') {
            $nameRs = new NameRS();
            $nameRs->idName->setStoredVal($idName);
            $rows = EditRS::select($dbh, $nameRs, array($nameRs->idName));
            EditRS::loadRow($rows[0], $nameRs);

            $nameRs->External_Id->setNewVal($externalId);
            $upd = EditRS::update($dbh, $nameRs, array($nameRs->idName));

            if ($upd > 0) {
                NameLog::writeUpdate($dbh, $nameRs, $nameRs->idName->getStoredVal(), $username);
            }
        }
    }

    protected function fillPcName($r, &$param, $origValues = array()) {

        $codes = array(
            'contactId',
            'firstName',
            'lastName',
            'middleName',
            'preferredName',
            'prefix',
            'suffix',
            'salutation',
            'email1',
            'email2',
            'email3',
            'fax',
            'gender.name',
            'deceased',
            'title',
            'department',
        );

        $nonEmptyCodes = array(
            'dob',
            'phone1',
            'phone1Type',
            'phone2',
            'phone2Type',
            'phone3',
            'phone3Type',
        );

        $base = 'individualAccount.';
        $pc = 'primaryContact.';
        $basePc = $base . $pc;

        foreach ($codes as $c) {

            if (isset($r[$c])) {
                $param[$basePc . $c] = $r[$c];
            } else if (isset($origValues[$pc . $c])) {
                $param[$basePc . $c] = $origValues[$pc . $c];
            }
        }

        foreach ($nonEmptyCodes as $c) {
        // these codes must be missing if not defined
            if (isset($r[$c]) && $r[$c] != '') {
                $param[$basePc . $c] = $r[$c];
            } else if (isset($origValues[$pc . $c]) && $origValues[$pc . $c] != '') {
                $param[$basePc . $c] = $origValues[$pc . $c];
            }
        }
    }

    protected function fillIndividualAccount($r) {

        //$base = 'individualAccount.individualTypes.';
        $indBase = 'individualType.id';
        $str = '';

        if (isset($r[$indBase]) && $r[$indBase] > 0) {
            $str = '&individualAccount.individualTypes.individualType.id=' . $r[$indBase];
        }


        if (isset($r['individualType.id2']) && $r['individualType.id2'] > 0) {
            $str .= '&individualAccount.individualTypes.individualType.id=' . $r['individualType.id2'];
        }

        return $str;
    }

    protected function fillOther($r, &$param, $origValues = array()) {

        $codes = array(
            'noSolicitation',
            'url',
            'login.username',
            'login.password',
            'login.orgId',
            'source.name',
            'existingOrganizationId',
            'organizationName',
            'twitterPage',
            'facebookPage',
        );

        $base = 'individualAccount.';

        foreach ($codes as $c) {

            if (isset($r[$c])) {
                $param[$base . $c] = $r[$c];
            } else if (isset($origValues[$c])) {
                $param[$base . $c] = $origValues[$c];
            }
        }
    }

    protected function fillPcAddr($r, &$param, $origValues = array()) {

        $codes = array(
            'addressId',
            'isPrimaryAddress',
            'isShippingAddress',
            'addressType.name',
            'addressLine1',
            'addressLine2',
            'addressLine3',
            'addressLine4',
            'city',
            'state.code',
            'province',
            'country.id',
            'zipCode',
            'zipCodeSuffix',
            'startDate',
            'endDate',
        );


        $pc = 'primaryContact.addresses.address.';
        $basePc = 'individualAccount.' . $pc;

        foreach ($codes as $c) {

            if (isset($r[$c])) {
                $param[$basePc . $c] = $r[$c];
            } else if (isset($origValues[$pc . '0.' . $c])) {
                $param[$basePc . $c] = $origValues[$pc . '0.' . $c];
            }
        }
    }

    protected function fillCustomFields($r) {

        $customParamStr = '';
        $base = 'individualAccount.customFieldDataList.customFieldData.';

        foreach ($this->customFields as $k => $v) {

            if (isset($r[$k]) && $r[$k] != '') {

                $cparam = array(
                    $base . 'fieldId' => $v,
                    $base . 'fieldOptionId' => '',
                    $base . 'fieldValue' => $r[$k]
                );

                $customParamStr .= '&' . http_build_query($cparam);

            }
        }

        return $customParamStr;

    }

    protected function createAccount(array $r) {

        $param = array(
            'originDetail' => 'Hospitality HouseKeeper Connector',
        );

        $this->fillPcName($r, $param);


        // Address
        if (isset($r['addressLine1']) && $r['addressLine1'] != '') {

            $r['isPrimaryAddress'] = 'true';
            $this->fillPcAddr($r, $param);

        }

        $paramStr = $this->fillIndividualAccount($r);

        $this->fillOther($r, $param);

        // Custom Parameters
        $paramStr .= $this->fillCustomFields($r);


        $request = array(
          'method' => 'account/createIndividualAccount',
          'parameters' => $param,
          'customParmeters' => $paramStr
          );

        return $this->webService->go($request);

    }

    protected function searchTarget(array $searchCriteria) {

        // Set up request
        $search = array(
            'method' => 'account/listAccounts',
            'columns' => array(
                'standardFields' => array('Account ID', 'Account Type'),
            ),
            'page' => array(
                'currentPage' => 1,
                'pageSize' => 200,
                'sortColumn' => 'Account ID',
                'sortDirection' => 'ASC',
            ),
        );

        // Apply search criteria
        foreach ($searchCriteria as $k => $v) {

            if (isset($this->customFields[$k]) == FALSE && $k != '' && $v != '') {
                $search['criteria'][] = array($k, 'EQUAL', $v);
            }
        }

        // Execute the search.
        return $this->webService->search($search);

    }

    protected function checkError($result) {

        $errorMsg = '';

        if (isset($result['operationResult']) && $result['operationResult'] == 'FAIL') {

            $errorMsg .= 'Result: "' . $result['operationResult'] . '", Date: ' . date('M j, Y H:i:s', strtotime($result['responseDateTime'])). "<br/>";

            foreach ($result['errors'] as $key => $errors) {

                $errorMsg .= $key . "<br/>";

                foreach ($errors as $e) {
                    $errorMsg .= $e['errorCode'] . ': ' . $e['errorMessage'] . "<br/>";
                }

            }

        } else if (isset($result['operationResult']) && $result['operationResult'] == 'ERROR') {
            $errorMsg .= 'Result: ' . $result['operationResult'] . ', Error Message: ' . $result['errorMessage'];

        } else if ( isset( $result['operationResult'] ) && $result['operationResult'] != 'SUCCESS' ) {

            $errorMsg .= 'Transaction failed';
        }

        $this->errorMessage = $errorMsg;


        if ($errorMsg != '') {
            return TRUE;
        } else {
            return FALSE;
        }

    }

    protected function loadSearchDB(\PDO $dbh, $sourceIds) {

        // clean up the ids
        foreach ($sourceIds as $s) {
            if (intval($s, 10) > 0){
                $idList[] = intval($s, 10);
            }
        }

        if (count($idList) > 0) {

            $parm = " in (" . implode(',', $idList) . ") ";
            return $dbh->query("Select * from vguest_search_neon where HHK_ID $parm");

        }

        return NULL;
    }

    public function loadSourceDB(\PDO $dbh, $idName) {

        $parm = intval($idName, 10);

        if ($parm > 0) {

            $stmt = $dbh->query("Select * from vguest_data_neon where HHK_ID = $parm");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) > 1) {
                $rows[0]['individualType.id2'] = $rows[1]['individualType.id'];
            } else if (count($rows) == 1) {
                $rows[0]['individualType.id2'] = '';
            }   else {
                $rows[0]['No Data'] = '';
            }

            return $rows[0];

        }

        return NULL;

    }

    protected function openTarget($userId, $apiKey) {

        if (function_exists('curl_version') === FALSE) {
            throw new Hk_Exception_Upload('PHP configuration error: cURL functions are missing.');
        }

        $keys = array('orgId'=>$userId, 'apiKey'=>$apiKey);

        $this->webService = new Neon();
        $loginResult = $this->webService->login($keys);


        if ( isset( $loginResult['operationResult'] ) && $loginResult['operationResult'] != 'SUCCESS' ) {
            throw new Hk_Exception_Upload('API Login failed');
        }

        if ($loginResult['userSessionId'] == '') {
            throw new Hk_Exception_Upload('API Session Id is missing');
        }

        return TRUE;
    }

    protected function closeTarget() {

        $this->webService->go( array( 'method' => 'common/logout' ) );
        $this->webService->setSession('');

    }

    public function getTxMethod() {
        return $this->webService->txMethod;
    }

    public function getTxParams() {
        return $this->webService->txParams;
    }


}
