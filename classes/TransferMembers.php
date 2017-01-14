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
    protected $countries;
    protected $states;

    public function __construct($userId, $password, array $customFields = array()) {
        $this->webService = new Neon();
        $this->userId = $userId;
        $this->password = $password;
        $this->customFields = $customFields;
        $this->errorMessage = '';
        $this->pageNumber = 1;
        $this->countries = NULL;
        $this->states = NULL;
    }

    public function __destruct() {
        $this->closeTarget();
    }

    public function searchAccount($searchCriteria) {

        $replys = [];

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

    public function retrieveAccount($accountId) {

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);

        $account = $this->webService->getIndividualAccount($accountId);

        if ($this->checkError($account)) {
            throw new Hk_Exception_Runtime($this->errorMessage);
        }

        return $account;
    }

    public function updateAccount(\PDO $dbh, $accountData, $idName) {

        if ($idName < 1) {
            return array('result'=>'member Id not specified: ' . $idName);
        }


        // Get member data record
        $stmt2 = $this->loadSourceDB($dbh, $r['HHK_ID'], '`vguest_data_neon`');
        if (is_null($stmt2)) {
            return array('result'=>'member Id not found: ' . $idName);
        }

        $localRows = $stmt2->fetchAll();
        $r = $localRows[0];

        if ($r['accountId'] != $accountData['accountId']) {
            return array('result'=>'Account Id mismatch: local Id = ' . $r['accountId'] . ' remote Id = ' . $accountData['accountId']);
        }

        $param['individualAccount.accountId'] = $accountData['accountId'];

        // Name, phone, email
        $this->fillPcName($r, $param);

        // Address
        $this->fillPcAddr($r, $param);

        // Other crap
        $this->fillIndividualAccount($r);

        // Custom Parameters
        $customParamStr = $this->fillCustomFields($r);

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);

        $request = array(
           'method' => 'account/updateIndividualAccount',
          'parameters' => $param,
          'customParmeters' => $customParamStr
        );

        $msg = '';
        $result = $this->webService->go($request);

        if ($this->checkError($result)) {
            $msg = $this->errorMessage;
        }

        return array('result' => $msg);

    }

    public function unwindResponse(&$line, $results, $prefix = '') {

        foreach ($results as $k => $v) {

            if (is_array($v)) {
                $newPrefix = $prefix . $k . '.';
                $this->unwindResponse($line, $v, $newPrefix);
            } else {
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

    public function sendList(\PDO $dbh, array $sourceIds, $username) {

        $replys = [];

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);

        $stmt = $this->loadSourceDB($dbh, $sourceIds, '`vguest_search_neon`');

        if (is_null($stmt)) {
            return array('error'=>'No local data.');
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
                $f['Result'] = 'Multiple Contacts.';
                $replys[] = $f;


            } else if ( isset($result['page']['totalResults'] ) && $result['page']['totalResults'] == 0 ) {

                // Nothing found - create a new account at remote

                // Get member data record
                $stmt2 = $this->loadSourceDB($dbh, $r['HHK_ID'], '`vguest_data_neon`');
                if (is_null($stmt2)) {
                    continue;
                }

                $rows = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

                // Create new account
                $result = $this->createAccount($rows[0]);

                if ($this->checkError($result)) {
                    $f['Result'] = $this->errorMessage;
                    $replys[] = $f;
                    continue;
                }

                $accountId = filter_var($result['accountId'], FILTER_SANITIZE_SPECIAL_CHARS);

                $this->updateLocalNameRecord($dbh, $r['HHK_ID'], $accountId, $username);

                if ($rows[0]['accountId'] != '') {
                    $f['Result'] = 'Contact was deleted at the remote system';
                } else {
                    $f['Result'] = 'New Contact';
                }
                $f['Account ID'] = $accountId;
                $replys[] = $f;

            } else {

                //huh?
                $f['Result'] = 'Huh? Number of returned records not defined.';
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
            'phone1',
            'phone1Type',
            'phone2',
            'phone2Type',
            'phone3',
            'phone3Type',
            'fax',
            'dob',
            'gender.name',
            'deceased',
            'title',
            'department',
        );

        $base = 'individualAccount.';
        $pc = 'primaryContact.';
        $basePc = $base . $pc;

        foreach ($codes as $c) {

            if (isset($r[$c]) && $r[$c] != '') {
                $param[$basePc . $c] = $r[$c];
            } else if (isset($origValues[$pc . $c])) {
                $param[$basePc . $c] = $origValues[$pc . $c];
            }
        }

    }

    protected function fillIndividualAccount($r, &$param, $origValues = array()) {

        $codes = array(
            'individualTypes.individualType.id',
            'individualTypes.individualType.name',
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

            if (isset($r[$c]) && $r[$c] != '') {
                $param[$base . $c] = $r[$c];
            } else if (isset($origValues[$c])) {
                $param[$base . $c] = $origValues[$c];
            }
        }
    }

    protected function fillPcAddr($r, &$param, $origValues = array()) {

        $codes = array(
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

            if (isset($r[$c]) && $r[$c] != '') {
                $param[$basePc . $c] = $r[$c];
            } else if (isset($origValues[$pc . $c])) {
                $param[$basePc . $c] = $origValues[$pc . $c];
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
            'originDetail' => $this->userId,
        );

        $this->fillPcName($r, $param);


        // Address
        if (isset($r['addressLine1']) && $r['addressLine1'] != '') {

            $r['isPrimaryAddress'] = 'true';
            $this->fillPcAddr($r, $param);

        }

        $this->fillIndividualAccount($r, $param);

        // Custom Parameters
        $customParamStr = $this->fillCustomFields($r);


        $request = array(
          'method' => 'account/createIndividualAccount',
          'parameters' => $param,
          'customParmeters' => $customParamStr
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

            // Special handling for custom fields.
            if ((isset($this->customFields[$k]) && $this->customFields[$k] != '')) {
                //$search['criteria'][] = array($this->customFields[$k], 'EQUAL', $v);
            } else if ($k != '' && $v != '') {
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
            $errorMsg .= 'Result: "' . $result['operationResult'] . '", Error Message: ' . $result['errorMessage'];

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

    public function loadSourceDB(\PDO $dbh, $sourceIds, $tableName) {

        $idList = array();
        $parm = '';

        if (is_array($sourceIds)) {

            // clean up the ids
            foreach ($sourceIds as $s) {
                if (intval($s, 10) > 0){
                    $idList[] = intval($s, 10);
                }
            }

            if (count($idList) > 0) {
                $parm = " in (" . implode(',', $idList) . ") ";
            }

        } else if (is_int($sourceIds)) {

            $parm = "=".$sourceIds;

        }

        if ($parm != '') {

            return $dbh->query("Select * from $tableName where HHK_ID $parm");

        }

        return NULL;

    }

    protected function openTarget($userId, $apiKey) {

        $uS = Session::getInstance();

        // Previous session established?
        if (isset($uS->ulsessid) && $uS->ulsessid != '') {
            $this->webService->setSession($uS->ulsessid);
            return TRUE;
        }


        $keys = array('orgId'=>$userId, 'apiKey'=>$apiKey);

        $loginResult = $this->webService->login($keys);


        if ( isset( $loginResult['operationResult'] ) && $loginResult['operationResult'] != 'SUCCESS' ) {
            throw new Hk_Exception_Upload('API Login failed');
        }

        if ($loginResult['userSessionId'] == '') {
            throw new Hk_Exception_Upload('API Session Id is missing');
        }

        $uS->ulsessid = $loginResult['userSessionId'];

        return TRUE;
    }

    protected function closeTarget() {

        $uS = Session::getInstance();
        $uS->ulsessid = '';

        $this->webService->go( array( 'method' => 'common/logout' ) );
        $this->webService->setSession('');

    }


}
