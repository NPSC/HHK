<?php

namespace HHK\House\Hospital;


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
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
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

    protected static function justHospitalMarkup(HospitalStay $hstay, $offerBlank = TRUE) {

        $uS = Session::getInstance();

        $hospList = $uS->guestLookups[GLTableNames::Hospital];
        $labels = Labels::getLabels();
        $hList = array();
        $aList = array();
        $assocNoneId = 0;

        foreach ($hospList as $h) {
            if ($h[2] == 'h' && ($h[3] == 'a' || $h[0] == $hstay->getHospitalId())) {
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

        $mrn = $labels->getString('hospital', 'MRN', '');

        $table->addHeaderTr(
                (count($aList) > 0 ? HTMLTable::makeTh('Association') : '')
                .HTMLTable::makeTh($labels->getString('hospital', 'hospital', 'Hospital'))
        		.HTMLTable::makeTh($labels->getString('hospital', 'roomNumber', 'Room'))
        		.($mrn == '' ? '' : HTMLTable::makeTh($mrn))
            );

        $table->addBodyTr(
                (count($aList) > 0 ? HTMLTable::makeTd(
                        HTMLSelector::generateMarkup(
                                HTMLSelector::doOptionsMkup(removeOptionGroups($aList), ($hstay->getAssociationId() == 0 ? $assocNoneId : $hstay->getAssociationId()), FALSE),
                                array('name'=>'selAssoc', 'class'=>'ignrSave hospital-stay')
                                )
                        ) : '')
                .HTMLTable::makeTd(
                        HTMLSelector::generateMarkup(
                                HTMLSelector::doOptionsMkup(removeOptionGroups($hList), ($hstay->getHospitalId() == 0 && count($hList) == 1 ? $hList[0][0] : $hstay->getHospitalId()), $offerBlank),
                        		array('name'=>'selHospital', 'class'=>'ignrSave hospital-stay' )
                                )
                        )
        		. HTMLTable::makeTd(
        				HTMLInput::generateMarkup(
        						$hstay->getRoom(),
        						array('name'=>'psgRoom', 'size'=>'8', 'class'=>'ignrSave hospital-stay')
        						)
        				)
        		. ($mrn == '' ? '' : HTMLTable::makeTd(
        				HTMLInput::generateMarkup(
        						$hstay->getMrn(),
        						array('name'=>'psgMrn', 'size'=>'14', 'class'=>'ignrSave hospital-stay')
        						)
        				))
        );

        $hospMkup = $table->generateMarkup(array('style'=>'display:inline-table'));


        $trtSt = $labels->getString('hospital', 'treatmentStart', '');
        $trtEnd = $labels->getString('hospital', 'treatmentEnd', '');
        $hospDates = '';

        if ($trtSt !== '' || $trtEnd !== '') {

	        $table2 = new HTMLTable();

	        $table2->addHeaderTr(
	        		($trtSt == '' ? '' : HTMLTable::makeTh($trtSt))
	        		.($trtEnd !== '' ? HTMLTable::makeTh($trtEnd) : '')
	        		);

	        $table2->addBodyTr(
	        		($trtSt == '' ? '' : HTMLTable::makeTd(
	        				HTMLInput::generateMarkup(
	        						($hstay->getArrivalDate() != '' ? date("M j, Y", strtotime($hstay->getArrivalDate())) : ""),
	        						array('name'=>'txtEntryDate', 'class'=>'ckhsdate ignrSave hospital-stay', 'readonly'=>'readonly'))
	        				))
	        		. ($trtEnd !== '' ? HTMLTable::makeTd(
	        				HTMLInput::generateMarkup(
	        						($hstay->getExpectedDepartureDate() != '' ? date("M j, Y", strtotime($hstay->getExpectedDepartureDate())) : ''),
	        						array('name'=>'txtExitDate', 'class'=>'ckhsdate ignrSave hospital-stay', 'readonly'=>'readonly'))
	        				) : '')
	       );

	        $hospDates = $table2->generateMarkup(array('style'=>'display:inline-table'));
        }

        return HTMLContainer::generateMarkup('div', $hospMkup . $hospDates, array('id'=>'hospRow'));

    }

    public static function createReferralMarkup(\PDO $dbh, HospitalStay $hstay, $offerBlankHosp = TRUE, array $referralHospitalData = []) {

        $uS = Session::getInstance();
        $referralAgentMarkup = '';
        $doctorMarkup = '';
        $labels = Labels::getLabels();


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

            // Guest Referral agent selected?
            if (isset($referralHospitalData['referralAgent']) && $referralHospitalData['referralAgent']['lastName'] != '') {

                // Agent already saved?
                if ($agent->getIdName() > 0) {

                    // Is our agent the same name?

                } else {

                    // Does our agent already exist?

                    // if not, queue up referral agent as new agent.

                }
            }

            $ratbl = new HTMLTable();

            $ratbl->addBodyTr(
                HTMLTable::makeTh(HTMLContainer::generateMarkup('span', $labels->getString('hospital', 'referralAgent', 'Referral Agent')).
                        HTMLContainer::generateMarkup('span', '', array('name'=>'agentSearch', 'class'=>'hhk-agentSearch ui-icon ui-icon-search', 'title'=>'Search', 'style'=>'margin-left:1.3em;'))
                        . HTMLContainer::generateMarkup('span', HTMLInput::generateMarkup('', array('id'=>'txtAgentSch', 'class'=>'ignrSave', 'size'=>'16', 'title'=>'Type 3 characters to start the search.')), array('title'=>'Search', 'style'=>'margin-left:0.3em;'))
                        , array('colspan'=>'3', 'id'=>'a_titleTh'))
                .HTMLTable::makeTh('', array('colspan'=>'3'))
            );

            $ratbl->addBodyTr(
                HTMLTable::makeTh("x", array('class'=>'a_actions')) .
                HTMLTable::makeTh('First')
                .HTMLTable::makeTh('Last')
                .HTMLTable::makeTh('Phone', array('class'=>'hhk-agentInfo', 'colspan'=>'2'))
                .HTMLTable::makeTh('Email', array('style'=>'vertical-align:bottom;', 'class'=>'hhk-agentInfo'))
                , array('class'=>'hhk-agentInfo'));

            $ratbl->addBodyTr(
                HTMLTable::makeTd(HTMLContainer::generateMarkup('button', HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-trash')), array('class'=>'ui-corner-all ui-state-default ui-button ui-widget', 'style'=>'padding: 0.2em 0.4em;', 'id'=>'a_delete', 'type'=>'button')), array('class'=>'a_actions')) .
                HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                $agent->getRoleMember()->get_firstName(),
                                array('name'=>'a_txtFirstName', 'size'=>'17', 'class'=>'hhk-agentInfo hospital-stay name'))
                        .HTMLInput::generateMarkup($agent->getIdName(), array('name'=>'a_idName', 'type'=>'hidden', 'class'=>'hospital-stay'))
                        )
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                $agent->getRoleMember()->get_lastName(),
                                array('name'=>'a_txtLastName', 'size'=>'17', 'class'=>'hhk-agentInfo hospital-stay name'))
                        )
                . HTMLTable::makeTd($uS->nameLookups['Phone_Type'][PhonePurpose::Cell][1] . ': ' .
                        HTMLInput::generateMarkup(
                                $cPhone["Phone_Num"],
                                array('id'=>'a_txtPhone'.PhonePurpose::Cell, 'name'=>'a_txtPhone[' .PhonePurpose::Cell. ']', 'size'=>'16', 'class'=>'hhk-phoneInput hhk-agentInfo hospital-stay'))
                        , array('style'=>'text-align:right;')
                        )
                . HTMLTable::makeTd($uS->nameLookups['Phone_Type'][PhonePurpose::Work][1] . ': ' .
                    HTMLInput::generateMarkup(
                        $wPhone["Phone_Num"],
                        array('id'=>'a_txtPhone'.PhonePurpose::Work, 'name'=>'a_txtPhone[' . PhonePurpose::Work . ']', 'size'=>'16', 'class'=>'hhk-phoneInput hhk-agentInfo hospital-stay'))
                    , array('style'=>'text-align:right;')
                    )
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                $email["Email"],
                                array('id'=>'a_txtEmail1', 'name'=>'a_txtEmail[1]', 'size'=>'24', 'class'=>'hhk-emailInput hhk-agentInfo hospital-stay'))
                        .HTMLContainer::generateMarkup('span', '', array('class'=>'hhk-send-email'))
                        )
                , array('class'=>'hhk-agentInfo'));

            $referralAgentMarkup = $raErrorMsg . $ratbl->generateMarkup(array('style'=>'margin-top:.5em;'));


        }

        if ($uS->Doctor) {

            $docErrorMsg = '';


            $dtbl = new HTMLTable();
            $dtbl->addBodyTr(
                HTMLTable::makeTh(HTMLContainer::generateMarkup('span', 'Doctor')
                    .HTMLContainer::generateMarkup('span', '', array('name'=>'doctorSearch', 'class'=>'hhk-docSearch ui-icon ui-icon-search', 'title'=>'Search', 'style'=>'margin-left:1.3em;'))
                    .HTMLContainer::generateMarkup('span',
                        HTMLInput::generateMarkup('', array('id'=>'txtDocSch', 'class'=>'ignrSave', 'size'=>'16', 'title'=>'Type 3 characters to start the search.')), array('title'=>'Search', 'style'=>'margin-left:0.3em;'))
                        , array('colspan'=>'3'))
            );

            $dtbl->addBodyTr(HTMLTable::makeTh("x", array('class'=>'d_actions')) . HTMLTable::makeTh('First').HTMLTable::makeTh('Last'), array('class'=>'hhk-docInfo'));

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
                HTMLTable::makeTd(HTMLContainer::generateMarkup('button', HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-trash')), array('class'=>'ui-corner-all ui-state-default ui-button ui-widget', 'style'=>'padding: 0.2em 0.4em;', 'id'=>'d_delete', 'type'=>'button')), array('class'=>'d_actions')) .
                HTMLTable::makeTd(($doc->getRoleMember()->get_lastName() == '' ? '' : 'Dr. ') .
                        HTMLInput::generateMarkup(
                                $doc->getRoleMember()->get_firstName(),
                                array('name'=>'d_txtFirstName', 'size'=>'17', 'class'=>'hhk-docInfo hospital-stay name'))
                        .HTMLInput::generateMarkup($doc->getIdName(), array('name'=>'d_idName', 'type'=>'hidden', 'class'=>'hospital-stay'))
                    )
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                $doc->getRoleMember()->get_lastName(),
                                array('name'=>'d_txtLastName', 'size'=>'17', 'class'=>'hhk-docInfo hospital-stay name'))
                        )
                , array('class'=>'hhk-docInfo'));

            $doctorMarkup = $docErrorMsg . $dtbl->generateMarkup(array('style'=>'display:inline-table; vertical-align: top;'));
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
                        HTMLTable::makeTd(HTMLInput::generateMarkup($hstay->getDiagnosis(), array('name'=>'txtDiagnosis', 'class'=>'hospital-stay'))));

                    $myDiagnosis = '';
                }
            }


            $diagtbl->addBodyTr(HTMLTable::makeTd(
                HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup($diags, $myDiagnosis, TRUE),
                		array('name'=>'selDiagnosis', 'class'=>'hospital-stay'))
                )
            );


            $diagMarkup = $diagtbl->generateMarkup(array('style'=>'display:inline-table; vertical-align: top;'));

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
                		array('name'=>'selLocation', 'class'=>'hospital-stay'))
                )
            );

            $locMarkup = $diagtbl->generateMarkup(array('style'=>'display:inline-table; vertical-align: top;'));

        } else {
            $locMarkup = '';
        }

        $docRowMkup = HTMLContainer::generateMarkup('div', $doctorMarkup . $diagMarkup . $locMarkup, array('style'=>'margin-top: .5em;', 'id'=>'docRow'));

        // Hospital stay log
        if ($hstay->getIdPsg() > 0) {
            $hstayLog = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('div',
                    HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-circle-triangle-s hhk-showhsLog', 'title'=>'Click to view', 'style'=>'display:inline-block; margin-left:.2em;'))
                    .HTMLContainer::generateMarkup('span', 'View ' . $labels->getString('hospital', 'hospital', 'Hospital') . ' Log', array('id'=>'spnhsctrl', 'class'=>'hhk-showhsLog', 'title'=>'Click to view', 'style'=>'display: inline-block; margin-left:.2em;'))
                ,array('id'=>'hospLogTitle'))
                .HTMLContainer::generateMarkup('div','', array('style'=>'margin-top:.3em;padding:5px;display:none;', 'id'=>'hhk-viewhsLog'))
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
                , array('style'=>'margin-top:.5em;padding:5px; display:inline-block', 'class'=>'ui-widget-content ui-corner-all'));

        } else {
            $hstayLog = '';
        }

        $div = HTMLContainer::generateMarkup('div',
            self::justHospitalMarkup($hstay, $offerBlankHosp)
        		. $referralAgentMarkup
        		. $docRowMkup
        		. $hstayLog
        		, array('style'=>'padding:5px;', 'class'=>'ui-corner-bottom hhk-tdbox')
        );

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

        return array('hdr'=>$hdr, 'title'=>$labels->getString('hospital', 'hospital', 'Hospital') . ' Details', 'div'=>$div);
    }

    public static function saveReferralMarkup(\PDO $dbh, PSG $psg, HospitalStay $hstay, array $post, $idResv = -1) {

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
        if (isset($post['psgMrn'])) {
        	$hstay->setMrn(filter_var($post['psgMrn'], FILTER_SANITIZE_STRING));
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

        return $hstay->save($dbh, $psg, $hstay->getAgentId(), $uS->username, $idResv);

    }
}
?>