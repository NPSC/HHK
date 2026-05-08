<?php
namespace HHK\CrmExport\Neon;

/**
 *
 * @author Eric
 *
 */
final class NeonHelper {

    private CONST MAX_CUSTOM_PROPERTYS = 50;

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
        if (isset($r['source.name']) && $r['source.name'] != '') {
            $param['donation']['source']['name'] = $r['source.name'];
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

    public static function fillPcName(array $r, array &$param, array $origValues = array()): void {

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
            'dob',
            'phone1',
            'phone1Type',
            'phone2',
            'phone2Type',
            'phone3',
            'phone3Type',
        );

        foreach ($simpleCodes as $c) {
            if (isset($r[$c])) {
                $param['individualAccount']['primaryContact'][$c] = $r[$c];
            } else if (isset($origValues['primaryContact.' . $c])) {
                $param['individualAccount']['primaryContact'][$c] = $origValues['primaryContact.' . $c];
            }
        }

        // gender.name maps to a nested path
        if (isset($r['gender.code'])) {
            $param['individualAccount']['primaryContact']['gender']['code'] = $r['gender.code'];
        } else if (isset($origValues['primaryContact.gender.code'])) {
            $param['individualAccount']['primaryContact']['gender']['code'] = $origValues['primaryContact.gender.code'];
        }

        foreach ($simpleNonEmptyCodes as $c) {
            // these codes must be missing if not defined
            if (isset($r[$c]) && $r[$c] != '') {
                $param['individualAccount']['primaryContact'][$c] = $r[$c];
            } else if (isset($origValues['primaryContact.' . $c]) && $origValues['primaryContact.' . $c] != '') {
                $param['individualAccount']['primaryContact'][$c] = $origValues['primaryContact.' . $c];
            }
        }

        // dob maps to a nested path
        if (isset($r['dob'])) {
            $dob = new \DateTime($r['dob']);
            $param['individualAccount']['primaryContact']['dob'] = [
                'year' => $dob->format('Y'),
                'month' => $dob->format('m'),
                'day' => $dob->format('d'),
            ];
        } else if (isset($origValues['primaryContact.dob'])) {
            $dob = new \DateTime($origValues['primaryContact.dob']);
            $param['individualAccount']['primaryContact']['dob'] = [
                'year' => $dob->format('Y'),
                'month' => $dob->format('m'),
                'day' => $dob->format('d'),
            ];
        }
    }

    public static function fillIndividualAccount(array $r, array &$param): void {

        if (isset($r['individualType.id']) && $r['individualType.id'] > 0) {
            $param['individualAccount']['individualTypes']['individualType'][] = ['id' => $r['individualType.id']];
        }

        if (isset($r['individualType.id2']) && $r['individualType.id2'] > 0) {
            $param['individualAccount']['individualTypes']['individualType'][] = ['id' => $r['individualType.id2']];
        }
    }

    public static function fillOther(array $r, array &$param, array $origValues = []): void {

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
            } else if (isset($origValues[$c])) {
                $param['individualAccount'][$c] = $origValues[$c];
            }
        }

        // login fields
        if (isset($r['login.username'])) {
            $param['individualAccount']['login']['username'] = $r['login.username'];
        } else if (isset($origValues['login.username'])) {
            $param['individualAccount']['login']['username'] = $origValues['login.username'];
        }

        if (isset($r['login.password'])) {
            $param['individualAccount']['login']['password'] = $r['login.password'];
        } else if (isset($origValues['login.password'])) {
            $param['individualAccount']['login']['password'] = $origValues['login.password'];
        }

        if (isset($r['login.orgId'])) {
            $param['individualAccount']['login']['orgId'] = $r['login.orgId'];
        } else if (isset($origValues['login.orgId'])) {
            $param['individualAccount']['login']['orgId'] = $origValues['login.orgId'];
        }

        if (isset($r['source.name'])) {
            $param['individualAccount']['source']['name'] = $r['source.name'];
        } else if (isset($origValues['source.name'])) {
            $param['individualAccount']['source']['name'] = $origValues['source.name'];
        }
    }

    public static function fillPcAddr(array $r, array &$param, array $origValues = array()): void {

        $simpleCodes = array(
            'addressId',
            'isPrimaryAddress',
            'addressLine1',
            'addressLine2',
            'addressLine3',
            'addressLine4',
            'city',
            'province',
            'zipCode',
            'zipCodeSuffix',
            'startDate',
            'endDate',
        );

        $address = [];

        $addrBase = 'primaryContact.addresses.address.';

        foreach ($simpleCodes as $c) {
            if (isset($r[$c])) {
                $address[$c] = $r[$c];
            } else if (isset($origValues[$addrBase . '0.' . $c])) {
                $address[$c] = $origValues[$addrBase . '0.' . $c];
            }
        }

        // addressType.name
        if (isset($r['addressType.name'])) {
            $address['addressType']['name'] = $r['addressType.name'];
        } else if (isset($origValues[$addrBase . '0.addressType.name'])) {
            $address['addressType']['name'] = $origValues[$addrBase . '0.addressType.name'];
        }

        // state.code
        if (isset($r['state.code'])) {
            $address['state']['code'] = $r['state.code'];
        } else if (isset($origValues[$addrBase . '0.state.code'])) {
            $address['state']['code'] = $origValues[$addrBase . '0.state.code'];
        }

        // country.id
        if (isset($r['country.id'])) {
            $address['country']['id'] = $r['country.id'];
        } else if (isset($origValues[$addrBase . '0.country.id'])) {
            $address['country']['id'] = $origValues[$addrBase . '0.country.id'];
        }

        $param['individualAccount']['primaryContact']['addresses'][] = $address;
    }

    public static function fillCustomFields(array $customFields, array $r, array &$param, array $origValues = array()): void {

        foreach ($customFields as $k => $v) {

            if (isset($r[$k]) && $r[$k] != '') {
                $param['individualAccount']['accountCustomFields'][] = [
                    'id'       => $v,
                    'value'    => $r[$k],
                ];
            } else {
                $fieldValue = self::findCustomField($origValues, $v);

                if ($fieldValue !== FALSE) {
                    $param['individualAccount']['accountCustomFields'][] = [
                        'id'       => $v,
                        'value'    => $fieldValue,
                    ];
                }
            }
        }

        // Search Neon custom fields that we don't control and copy them.
        self::fillOtherCustomFields($customFields, $origValues, $param);
    }

    protected static function fillOtherCustomFields($customFields, $origValues, array &$param) {

        $condition = TRUE;
        $index = 0;

        if (isset($origValues['customFieldDataList']['customFieldData'])) {

            // Move Neon fieldId's to key position
            $fieldCustom = array_flip($customFields);

            $cfValues = $origValues['customFieldDataList']['customFieldData'];

            while ($condition) {

                if (isset($cfValues[$index])) {

                    // Is this not one of my field Ids?
                    if (isset($cfValues[$index]["fieldId"]) && isset($fieldCustom[$cfValues[$index]["fieldId"]]) === FALSE) {
                        // Found other custom field
                        $param['individualAccount']['customFieldDataList']['customFieldData'][] = [
                            'fieldId'       => $cfValues[$index]["fieldId"],
                            'fieldOptionId' => $cfValues[$index]["fieldOptionId"],
                            'fieldValue'    => $cfValues[$index]["fieldValue"],
                        ];
                    }

                } else {
                    // end of custom fields
                    $condition = FALSE;
                }

                $index++;

                if ($index > self::MAX_CUSTOM_PROPERTYS) {
                    $condition = FALSE;
                }
            }
        }
    }

    /**
     *
     * @param array $origValues
     * @param string $base
     * @param mixed $fieldId
     * @return boolean|mixed
     */
    public static function findCustomField($origValues, $fieldId) {

        // find custom field index from neon
        $fieldValue = FALSE;
        $condition = TRUE;
        $index = 0;

        if (isset($origValues['customFieldDataList']['customFieldData'])) {

            $cfValues = $origValues['customFieldDataList']['customFieldData'];

            while ($condition) {

                if (isset($cfValues[$index])) {

                    // Is this my field Id?
                    if (isset($cfValues[$index]["fieldId"]) && $cfValues[$index]["fieldId"] == $fieldId) {
                        // Found the given custom field

                        $fieldValue = $cfValues[$index]["fieldValue"];
                        $condition = FALSE;
                    }

                } else {
                    // end of custom fields
                    $condition = FALSE;
                }

                $index++;

                if ($index > self::MAX_CUSTOM_PROPERTYS) {
                    $condition = FALSE;
                }
            }
        }

        return $fieldValue;
    }

}
