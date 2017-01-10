<?php
/**
 * RestData.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of RestData
 *
 * @author Eric Crane <ecrane at nonprofitsoftwarecorp.org>
 */
class RestData {
    //put your code here

    public function fillName(array $account, NameRS $nameRs = NULL) {

        if (is_null($nameRs)) {
            $nRs = new NameRS();
        } else {
            $nRs = $nameRs;
        }

        if (isset($account['primaryContact'])) {
            $pri = $account['primaryContact'];
        } else {
            return $nRs;
        }

        $dobDT = setTimeZone(NULL, $pri['dob']);

        $nRs->BirthDate->setNewVal($dobDT->format('Y-m-d H:i:s'));
        $nRs->External_Id->setNewVal($account['accountId']);
        $nRs->Name_First->setNewVal($pri['firstName']);
        $nRs->Name_Last->setNewVal($pri['lastName']);
        $nRs->Name_Middle->setNewVal($pri['middleName']);
        $nRs->Name_Nickname->setNewVal($pri['preferredName']);
        $nRs->Name_Prefix->setNewVal($pri['prefix']);
        $nRs->Name_Suffix->setNewVal($pri['suffix']);
        $nRs->Gender->setNewVal($pri['gender']['code']);
        $nRs->Title->setNewVal($pri['title']);

        // Member Status
        $decd = filter_var($pri['deceased'], FILTER_VALIDATE_BOOLEAN);
        if ($decd) {
            $nRs->Member_Status->setNewVal(MemStatus::Deceased);
        } else {
            $nRs->Member_Status->setNewVal(MemStatus::Active);
        }

        // Member Basis
        $nRs->Member_Type->setNewVal(MemBasis::Indivual);
        $nRs->Record_Company->setNewVal(0);
        $nRs->Record_Member->setNewVal(1);

        // Excludes
        if (isset($account['noSolicitation'])) {

            $noSol = filter_var($account['noSolicitation'], FILTER_VALIDATE_BOOLEAN);

            if ($noSol) {
                $nRs->Exclude_Directory->setNewVal(1);
                $nRs->Exclude_Email->setNewVal(1);
                $nRs->Exclude_Mail->setNewVal(1);
                $nRs->Exclude_Phone->setNewVal(1);
            }
        }

        return $nRs;

    }

    public function fillAddress(array $account, NameAddressRS $nameAddrRs = NULL) {

        if (is_null($nameAddrRs)) {
            $naRs = new NameAddressRS();
        } else {
            $naRs = $nameAddrRs;
        }

        if (isset($account['primaryContact']['addresses'])) {
            $pri = $account['primaryContact']['addresses'];
        } else {
            return $naRs;
        }

        foreach ($pri as $r) {

            if ($r['addressType']['name'] != Address_Purpose::Home || $r['isPrimaryAddress'] == 'false') {
                continue;
            }

            if (isset($r['addressLine1'])) {
                $naRs->Address_1->setNewVal($r['addressLine1']);
            }

            if (isset($r['addressLine2'])) {
                $naRs->Address_2->setNewVal($r['addressLine2']);
            }

            if (isset($r['city'])) {
                $naRs->Address_1->setNewVal($r['city']);
            }

            if (isset($r['state'])) {
                $naRs->Address_1->setNewVal($r['state']);
            }

            if (isset($r['county'])) {
                $naRs->Address_1->setNewVal($r['county']);
            }

            if (isset($r['zipCode'])) {
                $naRs->Postal_Code->setNewVal($r['zipCode']);
            }

            if (isset($r['country'])) {

                $naRs->Address_1->setNewVal($r['country']['name']);
            }

            $naRs->Purpose->setNewVal(Address_Purpose::Home);
        }

        return $naRs;

    }

    public function fillEmail(array $account, NameEmailRS $nameEmailRs = NULL) {

        if (is_null($nameEmailRs)) {
            $naRs = new NameEmailRS();
        } else {
            $naRs = $nameEmailRs;
        }

        if (isset($account['primaryContact'])) {
            $pri = $account['primaryContact'];
        } else {
            return $naRs;
        }

        // Grab the first email address.
        foreach ($pri as $r) {

            for ($i = 1; $i<4; $i++) {

                if(isset($r['email' . $i])) {
                    $naRs->Email->setNewVal($r['email' . $i]);
                    break;
                }
            }
        }

        $naRs->Purpose->setNewVal(Email_Purpose::Home);

    }

    public function fillPhone(array $account, NamePhoneRS $namePhoneRs = NULL) {

        if (is_null($namePhoneRs)) {
            $naRs = new NamePhoneRS();
        } else {
            $naRs = $namePhoneRs;
        }

        if (isset($account['primaryContact'])) {
            $pri = $account['primaryContact'];
        } else {
            return $naRs;
        }

        $phone_Code = '';

        // Grab the home or mobile number.
        foreach ($pri as $k =>$r) {

            if (stristr($k, 'phone')) {

                for ($i = 1; $i<4; $i++) {

                    if(isset($r['phone' . $i . 'Type'])) {

                        if ($r['phone' . $i . 'Type'] == 'Home') {
                            $naRs->Phone_Num->setNewVal($r['phone' . $i]);
                            $phone_Code = Phone_Purpose::Home;
                            break;
                        }

                        if($r['phone' . $i . 'Type'] == 'Mobile') {
                            $naRs->Phone_Num->setNewVal($r['phone' . $i]);
                            $phone_Code = Phone_Purpose::Cell;
                            break;
                        }
                    }

                }
            }

        }

        $naRs->Phone_Code->setNewVal($phone_Code);

    }

}
