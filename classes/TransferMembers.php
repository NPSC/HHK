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

    public function __construct($userId, $password, array $customFieldIds = array()) {
        $this->webService = new Neon();
        $this->userId = $userId;
        $this->password = $password;
        $this->customFields = $customFieldIds;
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


        // Log in with the web service
        $this->openTarget($this->userId, $this->password);




        $request = array(
            'method' => 'account/updateIndividualAccount',

        );


        $result = $this->webService->go($request);

        if ($this->checkError($result)) {
            throw new Hk_Exception_Runtime($this->errorMessage);
        }



    }

    public function unwindResponse(&$line, $results, $prefix = '') {

        foreach ($results as $k => $v) {

            if (is_array($v)) {

                $newPrefix = $prefix . $k . '.';

                unwindResponse($line, $v, $newPrefix);

            } else {

                $line .= $prefix . $k . '=' . $v . '<br/>';
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

    public function sendList(\PDO $dbh, array $sourceIds, array $customFields, $username) {

        $replys = [];

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);

        $stmt = $this->loadSourceDB($dbh, $sourceIds, '`vguest_search_neon`');

        if (is_null($stmt)) {
            return array('error'=>'No local data.');
        }

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $result = $this->searchTarget($customFields, $r);

            $f = array();

            foreach ($r as $k => $v) {

                if ($k != '') {
                    $f[$k] = $v;
                }
            }

            if ($this->checkError($result)) {
                $f['Result'] = $this->errorMessage;
                $replys[] = $f;
                continue;
            }

            if( isset($result['page']['totalResults'] ) && $result['page']['totalResults'] >= 1 ) {

                // We have a similar contact.

                // Make sure the external Id is defined locally
                if ($r['Account ID'] == '' && $result['searchResults'][0]['Account ID'] != '') {
                    $this->updateLocalNameRecord($dbh, $r['HHK_ID'], $result['searchResults'][0]['Account ID'], $username);
                    $f['Account ID'] = $result['searchResults'][0]['Account ID'];
                }

                $f['Result'] = 'Previously Transferred.';
                $replys[] = $f;


            } else {

                // Get member data record
                $stmt2 = $this->loadSourceDB($dbh, array($r['HHK_ID']), '`vguest_data_neon`');
                $rows = $stmt2->fetchAll();

                // Create new contact
                $result = $this->createAccount($rows[0], $customFields);

                if ($this->checkError($result)) {
                    $f['Result'] = $this->errorMessage;
                    $replys[] = $f;
                    continue;
                }

                $accountId = filter_var($result['accountId'], FILTER_SANITIZE_SPECIAL_CHARS);

                $this->updateLocalNameRecord($dbh, $r['HHK_ID'], $accountId, $username);

                $f['Result'] = 'New Contact';
                $f['Account ID'] = $accountId;
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

    protected function createAccount(array $r, array $customFields) {

        $phoneMapping = array(
            Phone_Purpose::Cell => 'Mobile',
            Phone_Purpose::Cell2 => 'Mobile',
            Phone_Purpose::Home => 'Home',
            Phone_Purpose::Work => 'Work',
        );


        $param = array(
            'originDetail' => $this->userId,
            'individualAccount.source.name' => 'HHK',
            'responseType' => 'JSON',
        );

        if (isset($r['First Name']) && $r['First Name'] != '') {
            $param['individualAccount.primaryContact.firstName'] = $r['First Name'];
        }

        if (isset($r['Last Name']) && $r['Last Name'] != '') {
            $param['individualAccount.primaryContact.lastName'] = $r['Last Name'];
        }

        if (isset($r['Middle Name']) && $r['Middle Name'] != '') {
            $param['individualAccount.primaryContact.middleName'] = $r['Middle Name'];
        }

        if (isset($r['Preferred Name']) && $r['Preferred Name'] != '') {
            $param['individualAccount.primaryContact.preferredName'] = $r['Preferred Name'];
        }

        if (isset($r['Prefix']) && $r['Prefix'] != '') {
            $param['individualAccount.primaryContact.prefix'] = $r['Prefix'];
        }

        if (isset($r['Suffix']) && $r['Suffix'] != '') {
            $param['individualAccount.primaryContact.suffix'] = $r['Suffix'];
        }

        if (isset($r['Email']) && $r['Email'] != '') {
            $param['individualAccount.primaryContact.email1'] = $r['Email'];
        }

        // Phone
        if (isset($r['Phone Number']) && $r['Phone Number'] != '') {

            $param['individualAccount.primaryContact.phone1'] = $r['Phone Number'];

            if (isset($r['Phone Type']) && $r['Phone Type'] != '' && isset($phoneMapping[$r['Phone Type']])) {
                $param['individualAccount.primaryContact.phone1Type'] = $phoneMapping[$r['Phone Type']];
            }

        }

        // Address
        if (isset($r['Address Line 1']) && $r['Address Line 1'] != '') {

            $param['individualAccount.primaryContact.addresses.address.isPrimaryAddress'] = 'true';
            $param['individualAccount.primaryContact.addresses.address.addressType.name'] = 'Home';

            $param['individualAccount.primaryContact.addresses.address.addressLine1'] = $r['Address Line 1'];

            if (isset($r['Address Line 2']) && $r['Address Line 2'] != '') {
                $param['individualAccount.primaryContact.addresses.address.addressLine2'] = $r['Address Line 2'];
            }

            if (isset($r['City']) && $r['City'] != '') {
                $param['individualAccount.primaryContact.addresses.address.city'] = $r['City'];
            }

            if (isset($r['State Code']) && $r['State Code'] != '') {
                $param['individualAccount.primaryContact.addresses.address.state.code'] = $r['State Code'];
            }

            if (isset($r['County']) && $r['County'] != '') {
                $param['individualAccount.primaryContact.addresses.address.county'] = $r['County'];
            }

            if (isset($r['Country Id']) && $r['Country Id'] != '') {
                $param['individualAccount.primaryContact.addresses.address.country.id'] = $r['Country Id'];
            }

            if (isset($r['Zip Code']) && $r['Zip Code'] != '') {
                $param['individualAccount.primaryContact.addresses.address.zipCode'] = $r['Zip Code'];
            }

        }

        if (isset($r['BirthDate']) && $r['BirthDate'] != '') {
            $param['individualAccount.primaryContact.dob'] = date('Y-m-d', strtotime($r['BirthDate']));
        }

        if (isset($r['Relationship Code']) && $r['Relationship Code'] == RelLinkType::Self) {
            $param['individualAccount.individualTypes.individualType.name'] = 'Patient';
        } else {
            $param['individualAccount.individualTypes.individualType.name'] = 'Guest';
        }

        // Custom Parameters
        $customParamStr = '';

        foreach ($customFields as $k => $v) {

            if ($r[$k] != '') {

                $cparam = array(
                    'individualAccount.customFieldDataList.customFieldData.fieldId' => $v,
                    'individualAccount.customFieldDataList.customFieldData.fieldOptionId' => '',
                    'individualAccount.customFieldDataList.customFieldData.fieldValue' => $r[$k]
                );

                $customParamStr .= '&' . http_build_query($cparam);
            }
        }


        $request = array(
          'method' => 'account/createIndividualAccount',
          'parameters' => $param,
          'customParmeters' => $customParamStr
          );

        return $this->webService->go($request);

    }

    /**
     *
     * @param array $searchCriteria Use defined field names
     * @param array $outputParms Use defined field names
     * @return array
     */
    protected function searchTarget(array $customFields, array $searchCriteria, array $outputParms = []) {

        if (count($outputParms) == 0) {
            $outputParms = array('Account ID', 'Account Type', 'Prefix', 'First Name', 'Middle Name', 'Last Name', 'Suffix', 'Address Line 1', 'City', 'State', 'Zip Code' );
        }

        $search = array(
            'method' => 'account/listAccounts',
            'columns' => array(
                'standardFields' => $outputParms,
            ),
            'page' => array(
                'currentPage' => 1,
                'pageSize' => 200,
                'sortColumn' => 'Last Name',
                'sortDirection' => 'ASC',
            ),
        );

        foreach ($searchCriteria as $k => $v) {

            if ((isset($customFields[$k]) && $customFields[$k] != '')) {
                $search['criteria'][] = array($customFields[$k], 'EQUAL', $v);
            } else if ($k != '' && $v != '' && intval($v, 10) != 0) {
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

    protected function loadSourceDB(\PDO $dbh, array $sourceIds, $tableName) {

        $idList = array();

        // clean up the ids
        foreach ($sourceIds as $s) {
            if (intval($s, 10) > 0){
                $idList[] = intval($s, 10);
            }
        }

        if (count($idList) > 0) {

            return $dbh->query("Select * from $tableName where HHK_ID in (" . implode(',', $idList) . ") ");

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
