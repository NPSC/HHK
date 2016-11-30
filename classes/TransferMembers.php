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

    public function __construct($userId, $password, array $customFieldIds) {
        $this->webService = new Neon();
        $this->userId = $userId;
        $this->password = $password;
        $this->customFields = $customFieldIds;
    }

    public function sendList(\PDO $dbh, array $sourceIds) {

        $replys = [];

        // Check in with the web service
        $this->openTarget($this->userId, $this->password);

        $stmt = $this->loadSourceDB($dbh, $sourceIds);

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $result = $this->searchTarget($r['Prefix'], $r['First'], $r['Last'], $r['Suffix'], $r['Address'], $r['City'], $r['State']);

            if( isset($result['page']['totalResults'] ) && $result['page']['totalResults'] >= 1 ) {
                // We have a similar contact.

            } else {

                // Create new contact
                $create = $this->createAccount($r);

                if (isset($create['accountId']) && $create['accountId'] != '') {

                    $accountId = filter_var($create['accountId'], FILTER_SANITIZE_SPECIAL_CHARS);

                    $upd = $dbh->exec("Update name set External_Id = '$accountId' where idName = ". $r['Id'] . " and External_Id = ''");

                    if ($upd > 0) {
                        $r['External_Id'] = $accountId;
                        $replys[] = $r;
                    }

                } else {
                    $r['External_Id'] = $create['msg'];
                    $replys[] = $r;
                }
            }

        }

        return $replys;

    }

    protected function createAccount(array $r) {

        $phoneMapping = array(
            Phone_Purpose::Cell => 'Mobile',
            Phone_Purpose::Cell2 => 'Mobile',
            Phone_Purpose::Home => 'Home',
            Phone_Purpose::Work => 'Work',
        );

        $errorMsg = '';

        $param = array(
            'originDetail' => $this->userId,
            //'individualAccount.source.id' => 'hhk',
            'responseType' => 'JSON',
            'individualAccount.individualTypes.individualType.name' => 'Guest',
//            'individualAccount.customFieldDataList.customFieldData.fieldId' => 87,
//            '&individualAccount.customFieldDataList.customFieldData.fieldOptionId' => '',
//            'individualAccount.customFieldDataList.customFieldData.fieldValue' => $r['idPsg'],

            'individualAccount.primaryContact.firstName' => $r['First'],
            'individualAccount.primaryContact.lastName' => $r['Last'],
            'individualAccount.primaryContact.middleName' => $r['Middle'],
            'individualAccount.primaryContact.prefix' => $r['Prefix'],
            'individualAccount.primaryContact.suffix' => $r['Suffix'],

            'individualAccount.primaryContact.email1' => $r['Email'],
            'individualAccount.primaryContact.phone1' => $r['Phone'],
            'individualAccount.primaryContact.phone1Type' => $phoneMapping['mc'],

            'individualAccount.primaryContact.addresses.address.isPrimaryAddress' => 'true',
            'individualAccount.primaryContact.addresses.address.addressType.name' => 'Home',
            'individualAccount.primaryContact.addresses.address.addressLine1' => $r['Address'],
            'individualAccount.primaryContact.addresses.address.city' => $r['City'],
            'individualAccount.primaryContact.addresses.address.state.code' => $r['State'],
            'individualAccount.primaryContact.addresses.address.country.id' => $r['Country'],
            'individualAccount.primaryContact.addresses.address.zipCode' => $r['Zip'],
        );

        if ($r['BirthDate'] != '') {
            $param['individualAccount.primaryContact.dob'] = date('Y-m-d', strtotime($r['BirthDate']));

        }

        $request = array(
          'method' => 'account/createIndividualAccount',
          'parameters' => $param,
          );

        $result = $this->webService->go($request);

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

            $errorMsg .= 'Create Individual failed';
        }

        $acctId = '';
        if (isset($result['accountId'])) {
            $acctId = $result['accountId'];
        }

        return array('accountId'=>$acctId, 'msg'=>$errorMsg);
    }

    protected function searchTarget($prefix, $first, $last, $suffix, $street, $city, $state) {

        $search = array(
            'method' => 'account/listAccounts',
            'columns' => array(
                'standardFields' => array('Account ID', 'Account Type', 'Prefix', 'First Name', 'Middle Name', 'Last Name', 'Suffix', 'Address Line 1', 'City', 'State' ),
            ),
            'page' => array(
                'currentPage' => 1,
                'pageSize' => 5,
                'sortColumn' => 'Last Name',
                'sortDirection' => 'ASC',
            ),
        );

        $searchCriteria = array(
            'First Name' =>$first,
            'Last Name' => $last
        );

        if ($prefix != '') {
            $searchCriteria['Prefix'] = $prefix;
        }
        if ($suffix != '') {
            $searchCriteria['Suffix'] = $suffix;
        }
        if ($street != '') {
            $searchCriteria['Address Line 1'] = $street;
        }
        if ($city != '') {
            $searchCriteria['City'] = $city;
        }
        if ($state != '') {
            $searchCriteria['State'] = $state;
        }

        foreach ($searchCriteria as $k => $v) {

            if ($k != '' && $v != '') {
                $search['criteria'][] = array($k, 'EQUAL', $v);
            }
        }


        // Execute the search.
        return $this->webService->search($search);

    }

    protected function loadSourceDB(\PDO $dbh, array $sourceIds) {

        $stmt = $dbh->query("Select * from vguest_listing where Id > 0 and Id in (" . implode(',', $sourceIds) . ") ");

        return $stmt;

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

        $this->webService->go( array( 'method' => 'common/logout' ) );

    }


}
