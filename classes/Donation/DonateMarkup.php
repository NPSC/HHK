<?php

namespace HHK\Donation;

use HHK\HTMLControls\{HTMLTable, HTMLSelector, HTMLInput};
use HHK\AlertControl\AlertMessage;
use HHK\HTMLControls\HTMLContainer;
use HHK\House\Report\ReportFilter;
use HHK\sec\Session;

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
                // hhk-money: setupNumericInputs (js/pag.js) strips non-numbers and formats to 2 decimals.
                HTMLInput::generateMarkup('', array('id'=>'damount', 'class'=>'hhk-ajx-dondata hhk-money', "type"=>"text", "inputmode"=>"decimal", "placeholder"=>"0.00"))
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
     * The NameEdit donations list filter: a time period and campaigns (the house report filter).
     * Used to build the filter markup on the page and to read the posted filter in donate.php.
     *
     * @param \PDO $dbh
     * @return ReportFilter
     */
    public static function createListFilter(\PDO $dbh): ReportFilter {

        $uS = Session::getInstance();

        $filter = new ReportFilter();
        $filter->createTimePeriod(date('Y'), ReportFilter::CAL_YEAR, intval($uS->fy_diff_Months))
            ->createCampaigns($dbh);

        return $filter;
    }

    /**
     * Collapsed filter section for the top of the NameEdit Donations tab.
     *
     * The controls are not in a form of their own (the tab is inside NameEdit's main form);
     * nameEd.js posts them with the list request when Apply is pressed.
     *
     * @param ReportFilter $filter
     * @return string
     */
    public static function listFilterMarkup(ReportFilter $filter): string {

        $selectors = HTMLContainer::generateMarkup('div',
            $filter->timePeriodMarkup('', 'don')->generateMarkup(array('class'=>'mr-2 mb-2'))
            . $filter->campaignMarkup('don')->generateMarkup(array('class'=>'mr-2 mb-2'))
            , array('class'=>'hhk-flex hhk-flex-wrap'));

        $buttons = HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('button', '<i class="bi bi-funnel mr-1"></i>Apply', array('type'=>'button', 'id'=>'btnDonFilterApply', 'class'=>'ui-button ui-corner-all ui-widget mr-2'))
            . HTMLContainer::generateMarkup('button', '<i class="bi bi-x-lg mr-1"></i>Clear', array('type'=>'button', 'id'=>'btnDonFilterClear', 'class'=>'ui-button ui-corner-all ui-widget'))
            , array('class'=>'mb-2'));

        return HTMLContainer::generateMarkup('details',
            HTMLContainer::generateMarkup('summary', 'Filter' . HTMLContainer::generateMarkup('span', '', array('id'=>'donFilterState', 'class'=>'ml-2', 'style'=>'font-weight:normal;')), array('style'=>'cursor:pointer; font-weight:bold; padding:0.3em 0;'))
            . HTMLContainer::generateMarkup('div', $selectors . $buttons, array('class'=>'pt-2'))
            , array('id'=>'donFilter', 'class'=>'ui-widget ui-widget-content ui-corner-all hhk-tdbox mb-2', 'style'=>'padding:0.3em 0.7em;'));
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
