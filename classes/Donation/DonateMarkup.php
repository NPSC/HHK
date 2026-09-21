<?php

namespace HHK\Donation;

use HHK\HTMLControls\{HTMLTable, HTMLSelector, HTMLInput};
use HHK\AlertControl\AlertMessage;
use HHK\HTMLControls\HTMLContainer;

/**
 * Donate.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Donate
 * @package name
 * @author Eric
 */
class DonateMarkup {


    public static function createDonateMarkup($campaignOptions, $addrOpts, $prefAddr, $salCodes, $letterDefault, $envDefault, $assocDonorList, $defaultAssocDonor, $assocDonorLabel, $payTypes, $studentOptions = NULL) {


        $newDon = new HTMLTable();
        $newDon->addHeaderTr(
                HTMLTable::makeTh('Campaign')
                .HTMLTable::makeTh('Date')
                .HTMLTable::makeTh('Allowed')
                .HTMLTable::makeTh('Pay With')
                .HTMLTable::makeTh('Amount')
                );

        $newDon->addBodyTr(

                HTMLTable::makeTd(HTMLSelector::generateMarkup($campaignOptions, array('id'=>'dselCamp', 'class'=>'hhk-ajx-dondata')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup(date('M j, Y'), array('id'=>'ddate', 'class'=>'ckdate hhk-ajx-dondata')))
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', array('id'=>'cLimits')))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(
                        HTMLSelector::doOptionsMkup($payTypes, '', FALSE), array('id'=>'dselPaytype', 'class'=>'hhk-ajx-dondata')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('id'=>'damount', 'class'=>'hhk-ajx-dondata', 'style'=>'width: 116px', "type"=>"number", "min"=>"0", "step"=>"0.01", "inputmode"=>"decimal")))
                );

        $tbl = new HTMLTable();
        $tbl->addHeaderTr(
                HTMLTable::makeTh('Address')
                .HTMLTable::makeTh($assocDonorLabel)
                .HTMLTable::makeTh('Salutation')
                .HTMLTable::makeTh('Envelope')
                .HTMLTable::makeTh('Notes')
                );

        $tbl->addBodyTr(
                 HTMLTable::makeTd(HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup($addrOpts, $prefAddr, FALSE), array('id'=>'dselAddress', 'class'=>'hhk-ajx-dondata')))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup($assocDonorList, $defaultAssocDonor), array('id'=>'selAssoc', 'class'=>'hhk-ajx-dondata')))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(
                        HTMLSelector::doOptionsMkup($salCodes, $letterDefault, FALSE), array('id'=>'dselSalutation', 'class'=>'hhk-ajx-dondata')))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(
                        HTMLSelector::doOptionsMkup($salCodes, $envDefault, FALSE), array('id'=>'dselEnvelope', 'class'=>'hhk-ajx-dondata')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('id'=>'dnote', 'class'=>'hhk-ajx-dondata')))
                );

        // Reply message
        $donAlert = new AlertMessage("donateResponseContainer");
        $donAlert->set_DisplayAttr("none");
        $donAlert->set_Context(AlertMessage::Success);
        $donAlert->set_iconId("donateResponseIcon");
        $donAlert->set_styleId("donateResponse");
        $donAlert->set_txtSpanId("donResultMessage");
        $donAlert->set_Text("oh-oh");
        $donReplyMessage = $donAlert->createMarkup();

        $tbl->addBodyTr(
                HTMLTable::makeTd($donReplyMessage, array('colspan'=>'6')));

        return $newDon->generateMarkup() . $tbl->generateMarkup();
    }
}
