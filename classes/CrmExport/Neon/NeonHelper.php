<?php
namespace HHK\CrmExport\Neon;

use DateTime;

/**
 *
 * @author Eric
 *
 */
final class NeonHelper {

    public static function fillDonation(array $r, array &$param): void {

        if (isset($r['accountId']) && $r['accountId'] != '') {
            $param['donation']['accountId'] = $r['accountId'];
        }
        if (isset($r['amount']) && $r['amount'] != '') {
            $param['donation']['amount'] = $r['amount'];
        }
        if (isset($r['date']) && $r['date'] != '') {
            $param['donation']['date'] = $r['date'];
        }
        if (isset($r['fund.id']) && $r['fund.id'] != '') {
            $param['donation']['fund']['id'] = $r['fund.id'];
        }
    }

    public static function fillPayment(array $r, array &$param): void {

        if (isset($r['amount']) && $r['amount'] != '') {
            $param['Payment']['amount'] = $r['amount'];
        }
        if (isset($r['tenderType.id']) && $r['tenderType.id'] != '') {
            $param['Payment']['tenderType']['id'] = $r['tenderType.id'];
        }
        if (isset($r['note']) && $r['note'] != '') {
            $param['Payment']['note'] = $r['note'];
        }

        switch ($r['tenderType.id']) {

            // Charge
            case '2':
                $param['Payment']['creditCardOfflinePayment']['cardNumber'] = '444444444444' . $r['cardNumber'];
                $param['Payment']['creditCardOfflinePayment']['cardHolder'] = $r['cardHolder'];
                $param['Payment']['creditCardOfflinePayment']['cardType']['name'] = $r['cardType.name'];
                break;

                // Check
            case '3':
                $param['Payment']['checkPayment']['CheckNumber'] = $r['CheckNumber'];
                break;

        }

    }

    public static function fillPcName(array $r, array &$param): void {

        $simpleCodes = array(
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
            'deceased',
            'title',
            'department',
        );

        $simpleNonEmptyCodes = array(
            'dob'
        );

        foreach ($simpleCodes as $c) {
            if (isset($r[$c])) {
                $param['individualAccount']['primaryContact'][$c] = $r[$c];
            }
        }

        // deceased is boolean
        if (isset($r['deceased'])) {
            $param['individualAccount']['primaryContact']['deceased'] = filter_var($r['deceased'], FILTER_VALIDATE_BOOLEAN);
        }
        
        // gender.name maps to a nested path
        if (isset($r['gender.code']) && $r['gender.code'] != '') {
            $param['individualAccount']['primaryContact']['gender']['code'] = $r['gender.code'];
        }

        foreach ($simpleNonEmptyCodes as $c) {
            // these codes must be missing if not defined
            if (isset($r[$c]) && $r[$c] != '') {
                if($c == 'dob'){
                    $param['individualAccount']['primaryContact'][$c] = self::formatDate(new DateTime($r[$c]));
                }else{
                    $param['individualAccount']['primaryContact'][$c] = $r[$c];
                }
            }
        }
    }

    public static function formatDate(DateTime $date):array {
        return [
            'year' => $date->format('Y'),
            'month' => $date->format('m'),
            'day' => $date->format('d'),
        ];
    }

    public static function fillIndividualAccount(array $r, array &$param): void {

        $param['individualAccount']['individualTypes'] = []; //reset individual types to prevent duplicates

        if (isset($r['individualType.id']) && $r['individualType.id'] > 0) {
            $param['individualAccount']['individualTypes'][] = ['id' => $r['individualType.id']];
        }

        if (isset($r['individualType.id2']) && $r['individualType.id2'] > 0) {
            $param['individualAccount']['individualTypes'][] = ['id' => $r['individualType.id2']];
        }
    }

    public static function fillOther(array $r, array &$param): void {

        $simpleCodes = array(
            'noSolicitation',
            'url',
            'existingOrganizationId',
            'organizationName',
            'twitterPage',
            'facebookPage',
        );

        foreach ($simpleCodes as $c) {
            if (isset($r[$c])) {
                $param['individualAccount'][$c] = $r[$c];
            }
        }

        // login fields
        if (isset($r['login.username'])) {
            $param['individualAccount']['login']['username'] = $r['login.username'];
        }

        if (isset($r['login.password'])) {
            $param['individualAccount']['login']['password'] = $r['login.password'];
        }

        if (isset($r['login.orgId'])) {
            $param['individualAccount']['login']['orgId'] = $r['login.orgId'];
        }

        if (isset($r['source.code'], $r['source.name'])) {
            $param['individualAccount']['source'] = ['id' => $r['source.code'], 'name' => $r['source.name'], 'status'=>"ACTIVE"];
        }
    }

    public static function fillPcAddr(array $r, array &$param): void {

        $simpleCodes = array(
            'addressId',
            'isPrimaryAddress',
            'addressLine1',
            'addressLine2',
            'addressLine3',
            'addressLine4',
            'city',
            'county',
            'stateProvince',
            'zipCode',
            'zipCodeSuffix',
            'startDate',
            'endDate',

            'phone1',
            'phone1Type',
            'phone2',
            'phone2Type',
            'phone3',
            'phone3Type'
        );

        $address = [];

        $origAddresses = $param['individualAccount']['primaryContact']['addresses'] ?? [];

        foreach ($simpleCodes as $c) {
            if (isset($r[$c])) {
                $address[$c] = $r[$c];
            } else if (isset($origAddresses[0][$c])) {
                $address[$c] = $origAddresses[0][$c];
            }
        }

        // addressType.name
        if (isset($r['addressType.name'])) {
            $address['addressType']['name'] = $r['addressType.name'];
        } else if (isset($origAddresses[0]['addressType']['name'])) {
            $address['addressType']['name'] = $origAddresses[0]['addressType']['name'];
        }

        // state.code
        if (isset($r['stateProvince.code'])) {
            $address['stateProvince']['code'] = $r['stateProvince.code'];
        } else if (isset($origAddresses[0]['stateProvince']['code'])) {
            $address['stateProvince']['code'] = $origAddresses[0]['stateProvince']['code'];
        }

        // country.id
        if (isset($r['country.id'])) {
            $address['country']['id'] = $r['country.id'];
        } else if (isset($origAddresses[0]['country']['id'])) {
            $address['country']['id'] = $origAddresses[0]['country']['id'];
        }

        $param['individualAccount']['primaryContact']['addresses'][0] = $address;
    }

    public static function fillCustomFields(array $customFields, array $r, array &$param): void {

        $customFieldParams = isset($param['individualAccount']['accountCustomFields']) ? $param['individualAccount']['accountCustomFields']: [];

        foreach ($customFields as $k => $v) {
            if (isset($r[$k]) && $r[$k] != '') {
                static::addOrUpdateCustomField($customFieldParams, $v, $r[$k]);
            }
        }
        $param['individualAccount']['accountCustomFields'] = $customFieldParams;
    }

    /**
     *
     * @param array $origValues
     * @param mixed $fieldId
     * @return boolean|mixed
     */
    public static function findCustomField($origValues, $fieldId) {

        // find custom field index from neon
        $fieldValue = FALSE;
        $condition = TRUE;
        $index = 0;

        if (isset($origValues['individualAccount']['accountCustomFields'])) {

            foreach($origValues['individualAccount']['accountCustomFields'] as $k=>$v){
                if($v['id'] == $fieldId){
                    return $v['value'];
                }
            }
        }
        return false;
    }

    public static function addOrUpdateCustomField(array &$origList, string $id, string|int|array $value){
        $found = false;
        foreach($origList as $k=>$v){
            if(isset($v['id']) && $v['id'] == $id){
                $origList[$k]['value'] = $value;
                $found = true;
                continue;
            }
        }
        
        if($found == false){
            $origList[] = [
                'id'=>$id,
                'value'=>$value
            ];
        }
    }

}
