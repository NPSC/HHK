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
 * Markup for the NameEdit "Record Donation" dialog: one label/field row per input.
 *
 * Every input keeps its id and the hhk-ajx-dondata class; nameEd.js collects the
 * values by those ids when the donation is saved.
 *
 * @package name
 * @author Eric
 */
class DonateMarkup {


    public static function createDonateMarkup($campaignOptions, $addrOpts, $prefAddr, $salCodes, $letterDefault, $envDefault, $assocDonorList, $defaultAssocDonor, $assocDonorLabel, $payTypes, $studentOptions = NULL) {

        $tbl = new HTMLTable();

        $tbl->addBodyTr(
            self::labelCell('dselCamp', 'Campaign')
            . HTMLTable::makeTd(HTMLSelector::generateMarkup($campaignOptions, array('id'=>'dselCamp', 'class'=>'hhk-ajx-dondata')))
        );

        $tbl->addBodyTr(
            self::labelCell('ddate', 'Date')
            . HTMLTable::makeTd(HTMLInput::generateMarkup(date('M j, Y'), array('id'=>'ddate', 'class'=>'ckdate hhk-ajx-dondata')))
        );

        $tbl->addBodyTr(
            self::labelCell('dselPaytype', 'Pay With')
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($payTypes, '', FALSE), array('id'=>'dselPaytype', 'class'=>'hhk-ajx-dondata')))
        );

        // Amount, with the selected campaign's allowed range shown underneath.
        $tbl->addBodyTr(
            self::labelCell('damount', 'Amount')
            . HTMLTable::makeTd(
                HTMLInput::generateMarkup('', array('id'=>'damount', 'class'=>'hhk-ajx-dondata', "type"=>"number", "min"=>"0", "step"=>"0.01", "inputmode"=>"decimal"))
                . HTMLContainer::generateMarkup('div',
                    'Allowed: ' . HTMLContainer::generateMarkup('span', '', array('id'=>'cLimits')),
                    array('class'=>'hhk-donate-hint'))
            )
        );

        $tbl->addBodyTr(
            self::labelCell('dselAddress', 'Address')
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($addrOpts, $prefAddr, FALSE), array('id'=>'dselAddress', 'class'=>'hhk-ajx-dondata')))
        );

        $tbl->addBodyTr(
            self::labelCell('selAssoc', $assocDonorLabel)
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($assocDonorList, $defaultAssocDonor), array('id'=>'selAssoc', 'class'=>'hhk-ajx-dondata')))
        );

        $tbl->addBodyTr(
            self::labelCell('dselSalutation', 'Salutation')
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($salCodes, $letterDefault, FALSE), array('id'=>'dselSalutation', 'class'=>'hhk-ajx-dondata')))
        );

        $tbl->addBodyTr(
            self::labelCell('dselEnvelope', 'Envelope')
            . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($salCodes, $envDefault, FALSE), array('id'=>'dselEnvelope', 'class'=>'hhk-ajx-dondata')))
        );

        $tbl->addBodyTr(
            self::labelCell('dnote', 'Notes')
            . HTMLTable::makeTd(HTMLContainer::generateMarkup('textarea', '', array('id'=>'dnote', 'class'=>'hhk-ajx-dondata', 'rows'=>'2')))
        );

        // Reply message
        $donAlert = new AlertMessage("donateResponseContainer");
        $donAlert->set_DisplayAttr("none");
        $donAlert->set_Context(AlertMessage::Success);
        $donAlert->set_iconId("donateResponseIcon");
        $donAlert->set_styleId("donateResponse");
        $donAlert->set_txtSpanId("donResultMessage");
        $donAlert->set_Text("oh-oh");

        return $tbl->generateMarkup(array('class'=>'hhk-donate-form')) . $donAlert->createMarkup();
    }

    /**
     * Label cell for one form row.
     *
     * @param string $forId id of the input the label belongs to
     * @param string $text
     * @return string
     */
    protected static function labelCell($forId, $text) {
        return HTMLTable::makeTd(
            HTMLContainer::generateMarkup('label', $text, array('for'=>$forId)),
            array('class'=>'tdlabel'));
    }
}
