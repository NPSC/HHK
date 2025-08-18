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
        $validPhone = Phones::validateAndFormatPhoneNumber($phData["Phone_Num"]);
        $phTdAttrs = [];

        
        if ($phData['Preferred_Phone'] == PhonePurpose::NoPhone) {
            $phoneMkup = 'No Phone';
        } else if($phData["Phone_Num"] !== "" && $validPhone["isValid"] === false) { //if phone is invalid, add a triangle
            $phTdAttrs["class"] = "ui-state-error";
            $phoneMkup = HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup("span", $phData["Phone_Num"] . ($phData["Phone_Extension"] == "" ? "" : " x" . $phData["Phone_Extension"])) . HTMLContainer::generateMarkup('i', '', ["class"=>"bi bi-exclamation-triangle-fill ml-2"]), ['class'=>'hhk-flex justify-content-between', "title"=>"Phone number is invalid"]);
        }else {
            $phoneMkup = $phData["Phone_Num"] . ($phData["Phone_Extension"] == "" ? "" : " x" . $phData["Phone_Extension"]);

            //sms dialog
            if($uS->smsProvider && $phData["Preferred_Phone"] == PhonePurpose::Cell && $phData["Unformatted_Phone"] != "" && $phData["SMS_opt_in"] == "opt_in" && $validPhone["isValid"] == true){
                $phoneMkup .= HTMLContainer::generateMarkup("button", HTMLContainer::generateMarkup("i", "", ['class'=>'bi bi-chat-dots-fill']), ['class'=>"ui-button ui-corner-all hhk-btn-small ml-2 btnTextGuest", "data-idname" => $phone->getIdName()]);
            }

        }

        $table->addBodyTr(
            HTMLTable::makeTd($phoneMkup, $phTdAttrs)
        );

        $table->addBodyTr(HTMLTable::makeTd("&nbsp;", array('style'=>'border-width:0;')));

        $table->addBodyTr(HTMLTable::makeTh("Preferred Email"));

        $emData = $email->get_Data();
        $emTdAttrs = [];

        if ($emData['Preferred_Email'] == EmailPurpose::NoEmail) {
            $emMkup = 'No Email';
        } else if ($emData["Email"] !=="" && filter_var($emData["Email"], FILTER_VALIDATE_EMAIL) === false){ //if email is invalid, add a triangle
            $emTdAttrs["class"] = "ui-state-error";
            $emMkup = HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup("span", $emData["Email"]) . HTMLContainer::generateMarkup('i', '', ["class"=>"bi bi-exclamation-triangle-fill ml-2"]), ['class'=>'hhk-flex justify-content-between', "title"=>"Email address is invalid"]);
        } else {
            $emMkup = $emData["Email"];
        }

        $table->addBodyTr(
            HTMLTable::makeTd($emMkup, $emTdAttrs)
        );

        return $table->generateMarkup();
    }

}