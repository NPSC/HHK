<?php
namespace HHK\CrmExport\Neon;

/**
 *
 * @author Eric
 *
 */
final class NeonHelper {

    Const MAX_CUSTOM_PROPERTYS = 50;

    public static function fillDonation($r, &$param) {

        $codes = array(
            'accountId',
            'amount',
            'date',
            'fund.id',
            'source.name',
        );

        $base = 'donation.';

        foreach ($codes as $c) {

            if (isset($r[$c]) && $r[$c] != '') {
                $param[$base . $c] = $r[$c];
            }
        }
    }

    public static function fillPayment($r, &$param) {

        $codes = array(
            'amount',
            'tenderType.id',
            'note',
        );

        $base = 'Payment.';

        foreach ($codes as $c) {

            if (isset($r[$c]) && $r[$c] != '') {
                $param[$base . $c] = $r[$c];
            }
        }

        switch ($r['tenderType.id']) {

            // Charge
            case '2':
                $param[$base . 'creditCardOfflinePayment.cardNumber'] = '444444444444' . $r['cardNumber'];
                $param[$base . 'creditCardOfflinePayment.cardHolder'] = $r['cardHolder'];
                $param[$base . 'creditCardOfflinePayment.cardType.name'] = $r['cardType.name'];
                break;

                // Check
            case '3':
                $param[$base . 'checkPayment.CheckNumber'] = $r['CheckNumber'];
                break;

        }

    }

    public static function fillPcName($r, &$param, $origValues = array()) {

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

    public static function fillIndividualAccount($r) {

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

    public static function fillOther($r, &$param, $origValues = array()) {

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

    public static function fillPcAddr($r, &$param, $origValues = array()) {

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

    public static function fillCustomFields($customFields, $r, $origValues = array()) {

        $customParamStr = '';
        $base = 'individualAccount.customFieldDataList.customFieldData.';


        foreach ($customFields as $k => $v) {

            if (isset($r[$k]) && $r[$k] != '') {
                // We have this custom field.

                $cparam = array(
                    $base . 'fieldId' => $v,
                    $base . 'fieldOptionId' => '',
                    $base . 'fieldValue' => $r[$k]
                );

                $customParamStr .= '&' . http_build_query($cparam);

            } else {
                // We don't have the custom field, see if one exists in Neon and if so, copy it.

                $fieldValue = self::findCustomField($origValues, $v);

                if ($fieldValue !== FALSE) {

                    $cparam = array(
                        $base . 'fieldId' => $v,
                        $base . 'fieldOptionId' => '',
                        $base . 'fieldValue' => $fieldValue
                    );

                    $customParamStr .= '&' . http_build_query($cparam);
                }
            }
        }

        // Search Neon custome fields that we don't control and copy them.
        $customParamStr .= self::fillOtherCustomFields($customFields, $origValues);

        return $customParamStr;

    }

    protected static function fillOtherCustomFields($customFields, $origValues) {

        $condition = TRUE;
        $index = 0;
        $customParamStr = '';
        $base = 'individualAccount.customFieldDataList.customFieldData.';

        if (isset($origValues['customFieldDataList']['customFieldData'])) {

            // Move Neon filedId's to key position
            $fieldCustom = array_flip($customFields);

            $cfValues = $origValues['customFieldDataList']['customFieldData'];

            while ($condition) {

                if (isset($cfValues[$index])) {

                    // Is this not one of my field Ids?
                    if (isset($cfValues[$index]["fieldId"]) && isset($fieldCustom[$cfValues[$index]["fieldId"]]) === FALSE) {
                        // Found other custom field

                        $cparam = array(
                            $base . 'fieldId' => $cfValues[$index]["fieldId"],
                            $base . 'fieldOptionId' => $cfValues[$index]["fieldOptionId"],
                            $base . 'fieldValue' => $cfValues[$index]["fieldValue"]
                        );

                        $customParamStr .= '&' . http_build_query($cparam);

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

        return $customParamStr;
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

