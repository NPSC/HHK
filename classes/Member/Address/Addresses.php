<?php

namespace HHK\Member\Address;

use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLTable;
use HHK\sec\Session;
use HHK\SysConst\EmailPurpose;
use HHK\SysConst\PhonePurpose;
use HHK\Tables\Name\NamePhoneRS;

/**
 * Addresses.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Addresses
 * @author Eric
 */
class Addresses {


    /** @var Emails/ContactPoint */
    public $email;

    /** @var Phones/ContactPoint */
    public $phone;

    /**
     *
     * @param Phones $phone
     * @param Emails $email
     * @return string
     */
    public static function getPreferredPanel(Phones $phone, Emails $email, $useHousePhone = FALSE) {

        /**
         * @var HTMLTable
         */
        $table = new HTMLTable();

        $table->addBodyTr(
                HTMLTable::makeTh("Preferred Phone"));

        // Make phone number
        $phData = $phone->get_Data();
        $uS = Session::getInstance();

        
        if ($phData['Preferred_Phone'] == PhonePurpose::NoPhone) {
            $phoneMkup = 'No Phone';
        } else {
            $phoneMkup = $phData["Phone_Num"] . ($phData["Phone_Extension"] == "" ? "" : " x" . $phData["Phone_Extension"]);
        }

        //sms dialog
        $cellPhone = $phone->get_recordSet(PhonePurpose::Cell);
        if($uS->smsProvider && $cellPhone instanceof NamePhoneRS && $cellPhone->Phone_Search->getStoredVal() != "" &&  $cellPhone->SMS_status->getStoredVal() == "opt_in"){
            $phoneMkup .= HTMLContainer::generateMarkup("button", HTMLContainer::generateMarkup("i", "", ['class'=>'bi bi-chat-dots-fill']), ['class'=>"ui-button ui-corner-all hhk-btn-small ml-2 btnTextGuest", "data-idname" => $cellPhone->idName->getStoredVal()]);
        }


        $table->addBodyTr(
            HTMLTable::makeTd($phoneMkup)
        );

        $table->addBodyTr(HTMLTable::makeTd("&nbsp;", array('style'=>'border-width:0;')));

        $table->addBodyTr(HTMLTable::makeTh("Preferred Email"));

        $emData = $email->get_Data();

        if ($emData['Preferred_Email'] == EmailPurpose::NoEmail) {
            $emMkup = 'No Email';
        } else {
            $emMkup = $emData["Email"];
        }

        $table->addBodyTr(
            HTMLTable::makeTd($emMkup)
        );

        return $table->generateMarkup();
    }

}