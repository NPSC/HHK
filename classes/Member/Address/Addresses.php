<?php

namespace HHK\Member\Address;

use HHK\HTMLControls\HTMLTable;
use HHK\SysConst\PhonePurpose;

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


    /** @var Email/ContactPoint */
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

        $table = new HTMLTable();

        $table->addBodyTr(
                HTMLTable::makeTh("Preferred Phone"));

        // Make phone number
        $phData = $phone->get_Data();

        if ($phData['Preferred_Phone'] == PhonePurpose::NoPhone) {
            $phoneMkup = 'No Phone';
        } else {
            $phoneMkup = $phData["Phone_Num"] . ($phData["Phone_Extension"] == "" ? "" : " x".$phData["Phone_Extension"]);
        }



        $table->addBodyTr(
                HTMLTable::makeTd($phoneMkup)
                );

        $table->addBodyTr(HTMLTable::makeTd("&nbsp;", array('style'=>'border-width:0;')));

        $table->addBodyTr(HTMLTable::makeTh("Preferred Email"));

        $emData = $email->get_Data();
        $table->addBodyTr(
                HTMLTable::makeTd($emData["Email"])
                );

        return $table->generateMarkup();
    }

}
?>