<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
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

    public function __construct($userId, $password, array $customFieldIds) {
        $this->webService = new Neon();
        $this->userId = $userId;
        $this->password = $password;
        $this->customFields = $customFieldIds;
        $this->errorMessage = '';
    }

    public function __destruct() {
        $this->closeTarget();
    }

    public function sendList(\PDO $dbh, array $sourceIds, $username) {

        $replys = [];

        // Log in with the web service
        $this->openTarget($this->userId, $this->password);

        $stmt = $this->loadSourceDB($dbh, $sourceIds, '`vguest_search_neon`');

        if (is_null($stmt)) {
            return array('error'=>'No local data.');
        }

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $result = $this->searchTarget($r);

            if ($this->checkError($result)) {
                $r['External_Id'] = $this->errorMessage;
                $replys[] = $r;
                continue;
            }

            if( isset($result['page']['totalResults'] ) && $result['page']['totalResults'] >= 1 ) {

                // We have a similar contact.
                $r['External_Id'] = 'Previously Transferred.';
                $replys[] = $r;

            } else {

                // Create new contact
                $result = $this->createAccount($r);

                if ($this->checkError($result)) {
                    $r['External_Id'] = $this->errorMessage;
                    $replys[] = $r;
                    continue;
                }

                $accountId = filter_var($result['accountId'], FILTER_SANITIZE_SPECIAL_CHARS);

                $this->updateNameRecord($dbh, $r['idName'], $accountId, $username);

                $r['External_Id'] = $accountId;
                $replys[] = $r;

            }
        }

        return $replys;

    }

    protected function updateNameRecord(\PDO $dbh, $idName, $externalId, $username) {

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

    protected function createAccount(array $r) {

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

            'individualAccount.customFieldDataList.customFieldData.fieldId' => '87',
            'individualAccount.customFieldDataList.customFieldData.fieldOptionId' => '',
            'individualAccount.customFieldDataList.customFieldData.fieldValue' => $r['_idPsg'],

            'individualAccount.primaryContact.firstName' => $r['First Name'],
            'individualAccount.primaryContact.lastName' => $r['Last Name'],
            'individualAccount.primaryContact.middleName' => $r['Middle Name'],
            'individualAccount.primaryContact.prefix' => $r['Prefix'],
            'individualAccount.primaryContact.suffix' => $r['Suffix'],

            'individualAccount.primaryContact.email1' => $r['Email'],
            'individualAccount.primaryContact.phone1' => $r['Phone Number'],

            'individualAccount.primaryContact.addresses.address.isPrimaryAddress' => 'true',
            'individualAccount.primaryContact.addresses.address.addressType.name' => 'Home',
            'individualAccount.primaryContact.addresses.address.addressLine1' => $r['Address Line 1'],
            'individualAccount.primaryContact.addresses.address.addressLine2' => $r['_Address Line 2'],
            'individualAccount.primaryContact.addresses.address.city' => $r['City'],
            'individualAccount.primaryContact.addresses.address.state.code' => $r['_State Code'],
            'individualAccount.primaryContact.addresses.address.country.id' => ($r['_Country Code'] == 'US' ? '1' : ''),
            'individualAccount.primaryContact.addresses.address.zipCode' => $r['Zip Code'],
        );

        if (isset($r['_Phone_Type']) && $r['_Phone_Type'] != '' && isset($phoneMapping[$r['_Phone Type']])) {
            $param['individualAccount.primaryContact.phone1Type'] = $phoneMapping[$r['_Phone_Type']];
        }

        if (isset($r['_BirthDate']) && $r['_BirthDate'] != '') {
            $param['individualAccount.primaryContact.dob'] = date('Y-m-d', strtotime($r['_BirthDate']));
        }

        if (isset($r['_Relationship_Code']) && $r['_Relationship_Code'] == RelLinkType::Self) {
            $param['individualAccount.individualTypes.individualType.name'] = 'Patient';
        } else {
            $param['individualAccount.individualTypes.individualType.name'] = 'Guest';
        }

        $request = array(
          'method' => 'account/createIndividualAccount',
          'parameters' => $param,
          );

        return $this->webService->go($request);

    }

    /**
     *
     * @param array $searchCriteria Use defined field names
     * @param array $outputParms Use defined field names
     * @return array
     */
    protected function searchTarget(array $searchCriteria, array $outputParms = []) {

        if (count($outputParms) == 0) {
            $outputParms = array('Account ID', 'Account Type', 'Prefix', 'First Name', 'Middle Name', 'Last Name', 'Suffix', 'Address Line 1', 'City', 'State' );
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

            if ($k != '' && $k[0] != '_' && $v != '') {
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

            return $dbh->query("Select * from $tableName where _idName in (" . implode(',', $idList) . ") ");

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
