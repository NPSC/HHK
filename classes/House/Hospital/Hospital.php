<?php

namespace HHK\House\Hospital;

use HHK\Config_Lite\Config_Lite;
use HHK\Member\Role\{Agent, Doctor};
use HHK\HTMLControls\{HTMLContainer, HTMLSelector, HTMLTable, HTMLInput};
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\SysConst\{GLTableNames, MemStatus, PhonePurpose};
use HHK\Tables\EditRS;
use HHK\Tables\Registration\HospitalRS;
use HHK\Exception\RuntimeException;
use HHK\House\PSG;

/**
 * Hospital.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
/**
 * Description of Hospital
 *
 * @author Eric
 */
class Hospital {


    public static function loadHospitals(\PDO $dbh) {

        $hospRs = new HospitalRS();
        return EditRS::select($dbh, $hospRs, array());

    }


    public static function createHospitalMarkup(\PDO $dbh, HospitalStay $hstay, $showExitDate = FALSE) {

        $uS = Session::getInstance();

        if ($uS->OpenCheckin) {
            $refrl = self::createReferralMarkup($dbh, $hstay);
            return $refrl['div'];
        } else {
            return self::justHospitalMarkup($dbh, $hstay, $showExitDate);
        }

    }

    public static function justHospitalMarkup(\PDO $dbh, HospitalStay $hstay, $showExitDate = FALSE) {

        $uS = Session::getInstance();

        $hospList = $uS->guestLookups[GLTableNames::Hospital];
        $labels = Labels::getLabels();
        $hList = array();
        $aList = array();
        $assocNoneId = 0;

        foreach ($hospList as $h) {
            if ($h[2] == 'h') {
                $hList[] = array($h[0], $h[1]);
            } else if ($h[2] == 'a') {

                if ($h[1] == '(None)') {
                    $assocNoneId = $h[0];
                    array_unshift($aList, array($h[0], ''));  // Put in front
                } else {

                    $aList[] = array($h[0], $h[1]);
                }
            }
        }

        $table = new HTMLTable();

        $table->addHeaderTr(
                (count($aList) > 0 ? HTMLTable::makeTh('Association') : '')
                .HTMLTable::makeTh($labels->getString('hospital', 'hospital', 'Hospital'))
                .HTMLTable::makeTh($labels->getString('hospital', 'roomNumber', 'Room'))
                .HTMLTable::makeTh($labels->getString('hospital', 'treatmentStart', 'Treatment Start'))
                .($showExitDate ? HTMLTable::makeTh($labels->getString('hospital', 'treatmentEnd', 'Treatment End')) : '')
            );

        $table->addBodyTr(
                (count($aList) > 0 ? HTMLTable::makeTd(
                        HTMLSelector::generateMarkup(
                                HTMLSelector::doOptionsMkup(removeOptionGroups($aList), ($hstay->getAssociationId() == 0 ? $assocNoneId : $hstay->getAssociationId()), FALSE),
                                array('name'=>'selAssoc', 'class'=>'ignrSave')
                                )
                        ) : '')
                .HTMLTable::makeTd(
                        HTMLSelector::generateMarkup(
                                HTMLSelector::doOptionsMkup(removeOptionGroups($hList), ($hstay->getHospitalId() == 0 && count($hList) == 1 ? $hList[0][0] : $hstay->getHospitalId())),
                                array('name'=>'selHospital', )
                                )
                        )
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                $hstay->getRoom(),
                                array('name'=>'psgRoom', 'size'=>'8')
                                )
                        )
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                ($hstay->getArrivalDate() != '' ? date("M j, Y", strtotime($hstay->getArrivalDate())) : ""),
                                array('name'=>'txtEntryDate', 'class'=>'ckdate', 'readonly'=>'readonly'))
                        )
                . ($showExitDate ? HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                ($hstay->getExpectedDepartureDate() != '' ? date("M j, Y", strtotime($hstay->getExpectedDepartureDate())) : ''),
                                array('name'=>'txtExitDate', 'class'=>'ckdate', 'readonly'=>'readonly'))
                        ) : '')
                );

        return $table->generateMarkup();

    }

    /**
     *
     * @param \PDO $dbh
     * @param PSG $psg
     * @param HospitalStay $hstay
     * @param array $post
     *
     */
    public static function saveHospitalMarkup(\PDO $dbh, PSG $psg, HospitalStay $hstay, array $post) {

        return self::saveReferralMarkup($dbh, $psg, $hstay, $post);
    }


    public static function createReferralMarkup(\PDO $dbh, HospitalStay $hstay, $showExitDate = TRUE) {

        $uS = Session::getInstance();
        $referralAgentMarkup = '';
        $doctorMarkup = '';
        $labels = Labels::getLabels();


        $hospitalMkup = self::justHospitalMarkup($dbh, $hstay, $showExitDate);

        if ($uS->ReferralAgent) {

            $raErrorMsg = '';

            try {
                $agent = new Agent($dbh, 'a_', $hstay->getAgentId());

                if ($agent->getIdName() > 0 && $agent->getRoleMember()->get_status() !== MemStatus::Active) {
                    $raErrorMsg = HTMLContainer::generateMarkup('div', 'Agent with Id ' . $hstay->getAgentId() . ' status is "' . $uS->nameLookups['mem_status'][$agent->getRoleMember()->get_status()][1] . '".', array('style'=>'margin:.3em;color:red;'));
                }

            } catch (RuntimeException $hkex) {

                $raErrorMsg = HTMLContainer::generateMarkup('div', 'Agent with Id ' . $hstay->getAgentId() . ' is not defined', array('style'=>'margin:.3em;color:red;'));
                $agent = new Agent($dbh, 'a_', 0);
            }


            $wPhone = $agent->getPhonesObj()->get_Data(PhonePurpose::Work);
            $cPhone = $agent->getPhonesObj()->get_Data(PhonePurpose::Cell);
            $email = $agent->getEmailsObj()->get_Data();

            $ratbl = new HTMLTable();

            $ratbl->addBodyTr(
                HTMLTable::makeTh(HTMLContainer::generateMarkup('span', $labels->getString('hospital', 'referralAgent', 'Referral Agent'), array('style'=>'float:left;')).
                        HTMLContainer::generateMarkup('span', '', array('name'=>'agentSearch', 'class'=>'hhk-agentSearch ui-icon ui-icon-search', 'title'=>'Search', 'style'=>'float: left; margin-left:1.3em;'))
                        . HTMLContainer::generateMarkup('span', HTMLInput::generateMarkup('', array('id'=>'txtAgentSch', 'class'=>'ignrSave', 'size'=>'16', 'title'=>'Type 3 characters to start the search.')), array('title'=>'Search', 'style'=>'float: left; margin-left:0.3em;'))
                        , array('colspan'=>2))
                .HTMLTable::makeTh('Phone')
                .HTMLTable::makeTh('Email', array('rowspan'=>'2', 'style'=>'vertical-align:bottom;')));

            $ratbl->addBodyTr(
                HTMLTable::makeTh('First')
                .HTMLTable::makeTh('Last')
                . HTMLTable::makeTd($uS->nameLookups['Phone_Type'][PhonePurpose::Work][1] . ': ' .
                        HTMLInput::generateMarkup(
                                $wPhone["Phone_Num"],
                                array('id'=>'a_txtPhone'.PhonePurpose::Work, 'name'=>'a_txtPhone[' . PhonePurpose::Work . ']', 'size'=>'16', 'class'=>'hhk-phoneInput hhk-agentInfo'))
                        , array('style'=>'text-align:right;')
                        )
                );

            $ratbl->addBodyTr(
                HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                $agent->getRoleMember()->get_firstName(),
                                array('name'=>'a_txtFirstName', 'size'=>'17', 'class'=>'hhk-agentInfo'))
                        .HTMLInput::generateMarkup($agent->getIdName(), array('name'=>'a_idName', 'type'=>'hidden'))
                        )
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                $agent->getRoleMember()->get_lastName(),
                                array('name'=>'a_txtLastName', 'size'=>'17', 'class'=>'hhk-agentInfo'))
                        )
                . HTMLTable::makeTd($uS->nameLookups['Phone_Type'][PhonePurpose::Cell][1] . ': ' .
                        HTMLInput::generateMarkup(
                                $cPhone["Phone_Num"],
                                array('id'=>'a_txtPhone'.PhonePurpose::Cell, 'name'=>'a_txtPhone[' .PhonePurpose::Cell. ']', 'size'=>'16', 'class'=>'hhk-phoneInput hhk-agentInfo'))
                        , array('style'=>'text-align:right;')
                        )
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                $email["Email"],
                                array('id'=>'a_txtEmail1', 'name'=>'a_txtEmail[1]', 'size'=>'24', 'class'=>'hhk-emailInput hhk-agentInfo'))
                        .HTMLContainer::generateMarkup('span', '', array('class'=>'hhk-send-email'))
                        )
            );

            $referralAgentMarkup = $raErrorMsg . $ratbl->generateMarkup(array('style'=>'margin-top:.3em;'));


        }

        if ($uS->Doctor) {

            $docErrorMsg = '';


            $dtbl = new HTMLTable();
            $dtbl->addBodyTr(
                HTMLTable::makeTh(HTMLContainer::generateMarkup('span', 'Doctor', array('style'=>'float:left;'))
                    .HTMLContainer::generateMarkup('span', '', array('name'=>'doctorSearch', 'class'=>'hhk-docSearch ui-icon ui-icon-search', 'title'=>'Search', 'style'=>'float: left; margin-left:1.3em;'))
                    .HTMLContainer::generateMarkup('span',
                        HTMLInput::generateMarkup('', array('id'=>'txtDocSch', 'class'=>'ignrSave', 'size'=>'16', 'title'=>'Type 3 characters to start the search.')), array('title'=>'Search', 'style'=>'float: left; margin-left:0.3em;'))
                        , array('colspan'=>'2'))
            );

            $dtbl->addBodyTr(HTMLTable::makeTh('First').HTMLTable::makeTh('Last'));

            try {

                $doc = new Doctor($dbh, 'd_', $hstay->getDoctorId());

                if ($doc->getIdName() > 0 && $doc->getRoleMember()->get_status() !== MemStatus::Active) {
                    $docErrorMsg = HTMLContainer::generateMarkup('div', 'Doctor with Id ' . $doc->getIdName() . ' status is "' . $uS->nameLookups['mem_status'][$doc->getRoleMember()->get_status()][1] . '".', array('style'=>'margin:.3em;color:red;'));
                }

            } catch (RuntimeException $hkex) {

                $docErrorMsg = HTMLContainer::generateMarkup('div', 'Doctor with Id ' . $hstay->getDoctorId() . ' is not defined', array('style'=>'margin:.3em;color:red;'));
                $doc = new Doctor($dbh, 'd_', 0);
            }


            $dtbl->addBodyTr(
                HTMLTable::makeTd(($doc->getRoleMember()->get_lastName() == '' ? '' : 'Dr. ') .
                        HTMLInput::generateMarkup(
                                $doc->getRoleMember()->get_firstName(),
                                array('name'=>'d_txtFirstName', 'size'=>'17', 'class'=>'hhk-docInfo'))
                        .HTMLInput::generateMarkup($doc->getIdName(), array('name'=>'d_idName', 'type'=>'hidden'))
                    )
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                $doc->getRoleMember()->get_lastName(),
                                array('name'=>'d_txtLastName', 'size'=>'17', 'class'=>'hhk-docInfo'))
                        )
             );

            $doctorMarkup = $docErrorMsg . $dtbl->generateMarkup(array('style'=>'margin-top:.3em;float:left;'));
        }

        // Diagnosis
        $diags = readGenLookupsPDO($dbh, 'Diagnosis', 'Description');

        if (count($diags) > 0) {

            $diagtbl = new HTMLTable();
            $diagtbl->addBodyTr(
                HTMLTable::makeTh($labels->getString('hospital', 'diagnosis', 'Diagnosis'))
            );

            $myDiagnosis = $hstay->getDiagnosisCode();

            // Use Diagnosis as a text box?
            if ($uS->ShowDiagTB) {
                if ($myDiagnosis == '' || ($myDiagnosis != '' && isset($diags[$myDiagnosis]) === FALSE)) {

                    $diagtbl->addBodyTr(
                        HTMLTable::makeTd(HTMLInput::generateMarkup($hstay->getDiagnosis(), array('name'=>'txtDiagnosis'))));

                    $myDiagnosis = '';
                }
            }


            $diagtbl->addBodyTr(HTMLTable::makeTd(
                HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup($diags, $myDiagnosis, TRUE),
                    array('name'=>'selDiagnosis', ))
                )
            );


            $diagMarkup = $diagtbl->generateMarkup(array('style'=>'margin-top:.3em;float:left;'));

        } else {
            $diagMarkup = '';
        }

        // Location
        $locs = readGenLookupsPDO($dbh, 'Location', 'Description');

        if (count($locs) > 0) {

            $diagtbl = new HTMLTable();
            $diagtbl->addBodyTr(
                HTMLTable::makeTh($labels->getString('hospital', 'location', 'Location'))
            );

            $diagtbl->addBodyTr(HTMLTable::makeTd(
                HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup($locs, $hstay->getLocationCode(), TRUE),
                    array('name'=>'selLocation', ))
                )
            );

            $locMarkup = $diagtbl->generateMarkup(array('style'=>'margin-top:.3em;float:left;'));

        } else {
            $locMarkup = '';
        }



        // Hospital stay log
        if ($hstay->getIdPsg() > 0) {
            $hstayLog = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-circle-triangle-s hhk-showhsLog', 'title'=>'Click to view', 'style'=>'float: left; margin-left:.2em;'))
                .HTMLContainer::generateMarkup('span', 'View ' . $labels->getString('hospital', 'hospital', 'Hospital') . ' Log', array('id'=>'spnhsctrl', 'class'=>'hhk-showhsLog', 'title'=>'Click to view', 'style'=>'float: left; margin-left:.2em;'))
                .HTMLContainer::generateMarkup('div','', array('style'=>'margin-top:.3em;clear:left;float:left;padding:5px;display:none;', 'id'=>'hhk-viewhsLog'))
                .'<script type="text/javascript">
$(document).ready(function () {
    "use strict";
    $(".hhk-showhsLog").click(function () {
        if ($("#spnhsctrl").text() == "View '. $labels->getString('hospital', 'hospital', 'Hospital') . ' Log") {
            $("#hhk-viewhsLog").load("ws_resc.php?cmd=hstay&psg=' . $hstay->getIdPsg() . '" ).show();
            $("#spnhsctrl").text("Hide Log");
        } else {
            $("#hhk-viewhsLog").hide();
            $("#spnhsctrl").text("View '. $labels->getString('hospital', 'hospital', 'Hospital') . ' Log");
        }
    });});</script>'
                , array('style'=>'margin-top:.3em;clear:left;float:left;padding:5px;', 'class'=>'ui-widget-content ui-corner-all'))
                . HTMLContainer::generateMarkup('div','', array('style'=>'clear:left;'));

        } else {
            $hstayLog = '';
        }

        $divClearStyle = HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        $div = HTMLContainer::generateMarkup('div', $hospitalMkup . $referralAgentMarkup . $doctorMarkup . $diagMarkup . $locMarkup . $hstayLog . $divClearStyle, array('style'=>'padding:5px;', 'class'=>'ui-corner-bottom hhk-tdbox'));

        // prepare hospital names
        $hospList = array();
        if (isset($uS->guestLookups[GLTableNames::Hospital])) {
            $hospList = $uS->guestLookups[GLTableNames::Hospital];
        }

        $hospNameTxt = $hstay->getAssocHospNames($hospList);

        // Collapsing header
        $hdr = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', $labels->getString('hospital', 'hospital', 'Hospital') . ': ')
                .HTMLContainer::generateMarkup('span', $hospNameTxt, array('id'=>'spnHospName'))

                , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));

        return array('hdr'=>$hdr, 'div'=>$div);
    }


    public static function saveReferralMarkup(\PDO $dbh, Psg $psg, HospitalStay $hstay, array $post) {

        $uS = Session::getInstance();

        if (isset($post['selAssoc'])) {
            $hstay->setAssociationId(filter_var($post['selAssoc'], FILTER_SANITIZE_STRING));
        }
        if (isset($post['selHospital'])) {
            $hstay->setHospitalId(filter_var($post['selHospital'], FILTER_SANITIZE_STRING));
        }

        if (isset($post['psgRoom'])) {
            $hstay->setRoom(filter_var($post['psgRoom'], FILTER_SANITIZE_STRING));
        }
        if (isset($post['txtEntryDate'])) {
            $dateStr = filter_var($post['txtEntryDate'], FILTER_SANITIZE_STRING);

            if ($dateStr != '') {
                $enDT = new \DateTime($dateStr);
                $enDT->setTimezone(new \DateTimeZone($uS->tz));
                $hstay->setArrivalDate($enDT->format('Y-m-d H:i:s'));
            }
        }

       if (isset($post['txtExitDate'])) {
            $dateStr = filter_var($post['txtExitDate'], FILTER_SANITIZE_STRING);

            if ($dateStr != '') {
                $enDT = new \DateTime($dateStr);
                $enDT->setTimezone(new \DateTimeZone($uS->tz));
                $hstay->setExpectedDepartureDate($enDT->format('Y-m-d H:i:s'));
            }
        }

        if (isset($post['selDiagnosis'])) {

            $myDiagnosis = filter_var($post['selDiagnosis'], FILTER_SANITIZE_STRING);
            $hstay->setDiagnosisCode($myDiagnosis);

            if ($myDiagnosis == '' && isset($post['txtDiagnosis'])) {
                $hstay->setDiagnosis(filter_var($post['txtDiagnosis'], FILTER_SANITIZE_STRING));
            }

        }

        if (isset($post['selLocation'])) {
            $hstay->setLocationCode(filter_var($post['selLocation'], FILTER_SANITIZE_STRING));
        }

        // Doctor
        if (isset($post['d_idName'])) {
            $aId = intval(filter_var($post['d_idName'], FILTER_SANITIZE_NUMBER_INT), 10);

            $doc = new Doctor($dbh, 'd_', $aId);
            try{
                $doc->save($dbh, $post, $uS->username);
            } catch (RuntimeException $ex) {
                // doctor name missing.
            }

            $hstay->setDoctorId($doc->getIdName());

        }


        // Agent
        if (isset($post['a_idName'])) {
            $aId = intval(filter_var($post['a_idName'], FILTER_SANITIZE_NUMBER_INT), 10);

            $agent = new Agent($dbh, 'a_', $aId);
            try{
                $agent->save($dbh, $post, $uS->username);
            } catch (RuntimeException $ex) {
                // agent name missing.
            }

            $hstay->setAgentId($agent->getIdName());

        }

        $hstay->save($dbh, $psg, $hstay->getAgentId(), $uS->username);

    }
}
?>