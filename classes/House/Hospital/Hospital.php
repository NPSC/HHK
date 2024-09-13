<?php

namespace HHK\House\Hospital;


use HHK\Member\Role\{Agent, Doctor};
use HHK\HTMLControls\{HTMLContainer, HTMLSelector, HTMLTable, HTMLInput};
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\SysConst\{GLTableNames, HospitalType, MemStatus, PhonePurpose, VolMemberType};
use HHK\Tables\EditRS;
use HHK\Tables\Registration\HospitalRS;
use HHK\Exception\RuntimeException;
use HHK\House\PSG;
use HHK\Member\MemberSearch;

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

    /**
     * Summary of loadHospitals
     * @param \PDO $dbh
     * @return array
     */
    public static function loadHospitals(\PDO $dbh) {

        $hospRs = new HospitalRS();
        return EditRS::select($dbh, $hospRs, array(), 'and', array($hospRs->Title));

    }

    /**
     * Summary of justHospitalMarkup
     * @param \HHK\House\Hospital\HospitalStay $hstay
     * @param mixed $offerBlank
     * @param array $referralHospitalData
     * @return string
     */
    protected static function justHospitalMarkup(HospitalStay $hstay, $offerBlank = TRUE, array $referralHospitalData = []) {

        $uS = Session::getInstance();

        $hospList = $uS->guestLookups[GLTableNames::Hospital];
        $labels = Labels::getLabels();
        $hList = array();
        $aList = array();
        $assocNoneId = 0;

        foreach ($hospList as $h) {
            if ($h[2] == HospitalType::Hospital && ($h[3] == 'a' || $h[0] == $hstay->getHospitalId())) {
                $hList[] = array($h[0], $h[1]);
            } else if ($h[2] == HospitalType::Association && ($h[3] == 'a' || $h[0] == $hstay->getAssociationId())) {

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
                (count($aList) > 0 && $hstay->getHospitalId() != $assocNoneId ? HTMLTable::makeTh('Association') : '')
                .HTMLTable::makeTh($labels->getString('hospital', 'hospital', 'Hospital'))
        		.HTMLTable::makeTh($labels->getString('hospital', 'roomNumber', 'Room'))
                .($mrn == '' ? '' : HTMLTable::makeTh($mrn))
            );

        $table->addBodyTr(
                (count($aList) > 0 && $hstay->getHospitalId() != $assocNoneId ? HTMLTable::makeTd(
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
        				    (isset($referralHospitalData['mrn']) && $referralHospitalData['mrn'] != '' ? $referralHospitalData['mrn'] : $hstay->getMrn()),
        						array('name'=>'psgMrn', 'size'=>'14', 'class'=>'ignrSave hospital-stay')
        						)
        				))
        );

        $hospMkup = $table->generateMarkup(array('style'=>'display:inline-table'));


        $trtSt = $labels->getString('hospital', 'treatmentStart', '');
        $trtEnd = $labels->getString('hospital', 'treatmentEnd', '');
        $hospDates = '';
        $treatStart = '';
        $treatEnd = '';

        // Check for guest referral dates.
        if (isset($referralHospitalData['treatmentStart']) && $referralHospitalData['treatmentStart'] != '') {

            $treatDT = new \DateTime($referralHospitalData['treatmentStart']);
            $treatStart = $treatDT->format('M j, Y');
        }
        if (isset($referralHospitalData['treatmentEnd']) && $referralHospitalData['treatmentEnd'] != '') {

            $treatDT = new \DateTime($referralHospitalData['treatmentEnd']);
            $treatEnd = $treatDT->format('M j, Y');
        }

        if ($trtSt !== '' || $trtEnd !== '') {

	        $table2 = new HTMLTable();

	        $table2->addHeaderTr(
	        		($trtSt == '' ? '' : HTMLTable::makeTh($trtSt))
	        		.($trtEnd !== '' ? HTMLTable::makeTh($trtEnd) : '')
	        		);

	        $table2->addBodyTr(
	        		($trtSt == '' ? '' : HTMLTable::makeTd(
	        				HTMLInput::generateMarkup(
	        						($hstay->getArrivalDate() != '' ? date("M j, Y", strtotime($hstay->getArrivalDate())) : $treatStart),
	        						array('name'=>'txtEntryDate', 'class'=>'ckhsdate ignrSave hospital-stay', 'readonly'=>'readonly'))
	        				))
	        		. ($trtEnd !== '' ? HTMLTable::makeTd(
	        				HTMLInput::generateMarkup(
	        						($hstay->getExpectedDepartureDate() != '' ? date("M j, Y", strtotime($hstay->getExpectedDepartureDate())) : $treatEnd),
	        						array('name'=>'txtExitDate', 'class'=>'ckhsdate ignrSave hospital-stay', 'readonly'=>'readonly'))
	        				) : '')
	       );

	        $hospDates = $table2->generateMarkup(array('style'=>'display:inline-table'));
        }

        return HTMLContainer::generateMarkup('div', $hospMkup . $hospDates, array('id'=>'hospRow'));

    }

    /**
     * Summary of createReferralMarkup
     * @param \PDO $dbh
     * @param \HHK\House\Hospital\HospitalStay $hstay
     * @param mixed $offerBlankHosp
     * @param array $referralHospitalData
     * @return array
     */
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


            $wPhone = $agent->getPhonesObj()->get_Data(PhonePurpose::Work)["Phone_Num"];
            $wExt = $agent->getPhonesObj()->get_Data(PhonePurpose::Work)["Phone_Extension"];
            $cPhone = $agent->getPhonesObj()->get_Data(PhonePurpose::Cell)["Phone_Num"];
            $email = $agent->getEmailsObj()->get_Data()['Email'];
            $name = ['first' => $agent->getRoleMember()->get_firstName(), 'last' => $agent->getRoleMember()->get_lastName()];
            $idName = $agent->getIdName();
            $guestSubmittedAgent = '';

            // Guest Referral agent selected?
            if (isset($referralHospitalData['referralAgent']) && $referralHospitalData['referralAgent']['lastName'] != '') {

                // Agent already assigned?
                if ($hstay->getAgentId() > 0) {
                    // Agent already assigned.

                    // Is guest's submitted agent the same name?
                    if ($referralHospitalData['referralAgent']['lastName'] != $agent->getRoleMember()->get_lastName()
                        || $referralHospitalData['referralAgent']['firstName'] != $agent->getRoleMember()->get_firstName()) {

                            // No, The guest's agent name is different than the one saved.
                            $guestSubmittedAgent = HTMLContainer::generateMarkup('span', 'Different Agent submitted: '. $referralHospitalData['referralAgent']['firstName'] . ' ' . $referralHospitalData['referralAgent']['lastName']
                            . ', ' . $referralHospitalData['referralAgent']['phone'] . ($referralHospitalData['referralAgent']['extension']== '' ? '' :'x' . $referralHospitalData['referralAgent']['extension']) . ', ' .$referralHospitalData['referralAgent']['email'],
                            ['class' => 'ui-state-highlight']);

                    }

                } else {
                    // Agent unassigned.

                    // Does guest's submitted agent already exist?
                    $memberSearch = new MemberSearch($referralHospitalData['referralAgent']['firstName'] . ' ' . $referralHospitalData['referralAgent']['lastName']);
                    $results = $memberSearch->volunteerCmteFilter($dbh, VolMemberType::ReferralAgent, '');

                    if (count($results) == 2 && $results[0]['id'] > 0) {
                        // Agent exists, assign agent

                        $wPhone = $results[0]['wphone'];
                        $wExt = $results[0]['wext'];
                        $cPhone = $results[0]['cphone'];
                        $email = $results[0]['email'];
                        $name = array('first'=>$results[0]['first'], 'last'=>$results[0]['last']);
                        $idName = $results[0]['id'];

                        $guestSubmittedAgent = HTMLContainer::generateMarkup('span', 'Existing Agent submitted: '. $referralHospitalData['referralAgent']['firstName'] . ' ' . $referralHospitalData['referralAgent']['lastName']
                            . ', ' . $referralHospitalData['referralAgent']['phone'] . ', ' .$referralHospitalData['referralAgent']['email'],
                            array('class'=>'ui-state-highlight'));


                    } else {
                        // agent not exists yet.

                        $wPhone = preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '($1) $2-$3', $referralHospitalData['referralAgent']['phone']);
                        $cPhone = '';
                        $email = $referralHospitalData['referralAgent']['email'];
                        $name = array('first'=>$referralHospitalData['referralAgent']['firstName'], 'last'=>$referralHospitalData['referralAgent']['lastName']);
                        $idName = 0;

                        $guestSubmittedAgent = HTMLContainer::generateMarkup('span', 'New Agent submitted: '. $referralHospitalData['referralAgent']['firstName'] . ' ' . $referralHospitalData['referralAgent']['lastName']
                            . ', ' . $referralHospitalData['referralAgent']['phone'] . ', ' .$referralHospitalData['referralAgent']['email'],
                            array('class'=>'ui-state-highlight'));
                    }

                }

            }

            $ratbl = new HTMLTable();

            $ratbl->addBodyTr(
                HTMLTable::makeTh(HTMLContainer::generateMarkup('span', $labels->getString('hospital', 'referralAgent', 'Referral Agent')).
                        HTMLContainer::generateMarkup('span', '', array('name'=>'agentSearch', 'class'=>'hhk-agentSearch ui-icon ui-icon-search', 'title'=>'Search', 'style'=>'margin-left:1.3em;'))
                        . HTMLContainer::generateMarkup('span', HTMLInput::generateMarkup('', ['id' => 'txtAgentSch', 'class' => 'ignrSave', 'size' => '16', 'title' => 'Type 3 characters to start the search.']), ['title' => 'Search', 'style' => 'margin-left:0.3em;'])
                        ,
                    ['colspan' => '3', 'id' => 'a_titleTh'])
                .HTMLTable::makeTd($guestSubmittedAgent, ['colspan' => '3'])
            );

            $ratbl->addBodyTr(
                HTMLTable::makeTh("x", array('class'=>'a_actions')) .
                HTMLTable::makeTh('First')
                .HTMLTable::makeTh('Last')
                .HTMLTable::makeTh('Phone', ['class' => 'hhk-agentInfo', 'colspan' => '2', 'style' => 'min-width:362px'])
                .HTMLTable::makeTh('Email', ['style' => 'vertical-align:bottom;', 'class' => 'hhk-agentInfo'])
                ,
                ['class' => 'hhk-agentInfo']);

            $ratbl->addBodyTr(
                HTMLTable::makeTd(HTMLContainer::generateMarkup('button', HTMLContainer::generateMarkup('span', '', ['class' => 'ui-icon ui-icon-trash']), ['class' => 'ui-corner-all ui-state-default ui-button ui-widget', 'style' => 'padding: 0.2em 0.4em;', 'id' => 'a_delete', 'type' => 'button']), ['class' => 'a_actions']) .
                HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                $name['first'],
                                array('name'=>'a_txtFirstName', 'size'=>'17', 'class'=>'hhk-agentInfo hospital-stay name'))
                    .HTMLInput::generateMarkup($idName, ['name' => 'a_idName', 'type' => 'hidden', 'class' => 'hospital-stay'])
                        )
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                $name['last'],
                        ['name' => 'a_txtLastName', 'size' => '17', 'class' => 'hhk-agentInfo hospital-stay name'])
                        )
                . HTMLTable::makeTd($uS->nameLookups['Phone_Type'][PhonePurpose::Cell][1] . ': ' .
                        HTMLInput::generateMarkup(
                                $cPhone,
                        ['id' => 'a_txtPhone' . PhonePurpose::Cell, 'name' => 'a_txtPhone[' . PhonePurpose::Cell . ']', 'size' => '16', 'class' => 'hhk-phoneInput hhk-agentInfo hospital-stay'])
                        ,
                    ['style' => 'text-align:right;']
                )
                . HTMLTable::makeTd($uS->nameLookups['Phone_Type'][PhonePurpose::Work][1] . ': ' .
                    HTMLInput::generateMarkup(
                        $wPhone,
                        ['id' => 'a_txtPhone' . PhonePurpose::Work, 'name' => 'a_txtPhone[' . PhonePurpose::Work . ']', 'size' => '16', 'class' => 'hhk-phoneInput hhk-agentInfo hospital-stay'])
                    . ' x: '
                    . HTMLInput::generateMarkup(
                        $wExt,
                        ['id' => 'a_txtExtn' . PhonePurpose::Work, 'name' => 'a_txtExtn[' . PhonePurpose::Work . ']', 'size' => '6', 'class' => 'hhk-phoneInput hhk-agentInfo hospital-stay'])
                    ,
                    ['style' => 'text-align:right;']
                )
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                                $email,
                        ['id' => 'a_txtEmail1', 'name' => 'a_txtEmail[1]', 'size' => '24', 'class' => 'hhk-emailInput hhk-agentInfo hospital-stay'])
                        .HTMLContainer::generateMarkup('span', '', ['class' => 'hhk-send-email'])
                        )
                ,
                ['class' => 'hhk-agentInfo']);

            $referralAgentMarkup = $raErrorMsg . $ratbl->generateMarkup(['style' => 'margin-top:.5em;']);


        }

        if ($uS->Doctor) {

            $docErrorMsg = '';


            try {

                $doc = new Doctor($dbh, 'd_', $hstay->getDoctorId());

                if ($doc->getIdName() > 0 && $doc->getRoleMember()->get_status() !== MemStatus::Active) {
                    $docErrorMsg = HTMLContainer::generateMarkup('div', 'Doctor with Id ' . $doc->getIdName() . ' status is "' . $uS->nameLookups['mem_status'][$doc->getRoleMember()->get_status()][1] . '".', array('style'=>'margin:.3em;color:red;'));
                }

            } catch (RuntimeException $hkex) {

                $docErrorMsg = HTMLContainer::generateMarkup('div', 'Doctor with Id ' . $hstay->getDoctorId() . ' is not defined', array('style'=>'margin:.3em;color:red;'));
                $doc = new Doctor($dbh, 'd_', 0);
            }

            $docFirst = $doc->getRoleMember()->get_firstName();
            $docLast = $doc->getRoleMember()->get_lastName();
            $idDoc = $doc->getIdName();

            $guestSubmittedDoc = '';

            // Guest Referral agent selected?
            if (isset($referralHospitalData['doctor']) && trim($referralHospitalData['doctor']) != '') {

                // Get rid of leading "Dr."
                $docString = str_ireplace('dr.' , '', trim(str_ireplace('dr ' , '', trim($referralHospitalData['doctor']))));

                // Seperate and clean up doctor's name
                $parts = explode(' ', strtolower(trim($docString)));

                if (count($parts) > 1) {

                    // first or last name?
                    if (stristr($parts[0], ',') === FALSE) {
                        //first name first
                        $docFirst = $parts[0];
                        $docLast = $parts[1];
                    } else {
                        // last name first
                        $docFirst = $parts[1];
                        $docLast = str_replace(',', '', $parts[0]);
                    }

                } else {
                    $docFirst = '';
                    $docLast = $parts[0];
                }

                // Recapitalize name.
                $docFirst = ucfirst($docFirst);
                $docLast = ucfirst($docLast);

                // Doctor already assigned?
                if ($hstay->getDoctorId() > 0) {
                    // Doctor already assigned.

                    // Is guest's submitted agent the same name?
                    if ($docLast != $doc->getRoleMember()->get_lastName()) {

                        // No, The guest's agent name is different than the one saved.
                        $guestSubmittedDoc = HTMLTable::makeTd(HTMLContainer::generateMarkup('span', 'Different Doctor submitted: '.$referralHospitalData['doctor'],
                            array('class'=>'ui-state-highlight')), array('colspan'=>'3'));
                    }

                } else {
                    // Doctor unassigned.

                    // Does guest's submitted Doctor already exist?
                    $memberSearch = new MemberSearch($docFirst . ' '. $docLast);
                    $results = $memberSearch->volunteerCmteFilter($dbh, VolMemberType::Doctor, '');

                    if (count($results) == 2 && $results[0]['id'] > 0) {
                        // Doctor exists

                        $docFirst = $results[0]['first'];
                        $docLast = $results[0]['last'];
                        $idDoc = $results[0]['id'];

                        $guestSubmittedDoc = HTMLTable::makeTd(HTMLContainer::generateMarkup('span', 'Existing Doctor submitted: '.$referralHospitalData['doctor'],
                            array('class'=>'ui-state-highlight')), array('colspan'=>'3'));

                    } else {
                        // Doctor not exists yet.
                        $idDoc = 0;
                        $guestSubmittedDoc = HTMLTable::makeTd(HTMLContainer::generateMarkup('span', 'New Doctor submitted: '.$referralHospitalData['doctor'],
                            array('class'=>'ui-state-highlight')), array('colspan'=>'3'));
                    }
                }
            }

            $dtbl = new HTMLTable();
            $dtbl->addBodyTr(
                HTMLTable::makeTh(HTMLContainer::generateMarkup('span', 'Doctor')
                    .HTMLContainer::generateMarkup('span', '', array('name'=>'doctorSearch', 'class'=>'hhk-docSearch ui-icon ui-icon-search', 'title'=>'Search', 'style'=>'margin-left:1.3em;'))
                    .HTMLContainer::generateMarkup('span',
                        HTMLInput::generateMarkup('', array('id'=>'txtDocSch', 'class'=>'ignrSave', 'size'=>'16', 'title'=>'Type 3 characters to start the search.')), array('title'=>'Search', 'style'=>'margin-left:0.3em;'))
                    , array('colspan'=>'3'))
                );

            if ($guestSubmittedDoc != '') {
                // add guest referral comment
                $dtbl->addBodyTr($guestSubmittedDoc);
            }

            $dtbl->addBodyTr(HTMLTable::makeTh("x", array('class'=>'d_actions')) . HTMLTable::makeTh('First').HTMLTable::makeTh('Last'), array('class'=>'hhk-docInfo'));

            $dtbl->addBodyTr(
                HTMLTable::makeTd(HTMLContainer::generateMarkup('button', HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-trash')), array('class'=>'ui-corner-all ui-state-default ui-button ui-widget', 'style'=>'padding: 0.2em 0.4em;', 'id'=>'d_delete', 'type'=>'button')), array('class'=>'d_actions')) .
                HTMLTable::makeTd(($doc->getRoleMember()->get_lastName() == '' ? '' : 'Dr. ') .
                        HTMLInput::generateMarkup(
                            $docFirst,
                                array('name'=>'d_txtFirstName', 'size'=>'17', 'class'=>'hhk-docInfo hospital-stay name'))
                    .HTMLInput::generateMarkup($idDoc, array('name'=>'d_idName', 'type'=>'hidden', 'class'=>'hospital-stay'))
                    )
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup(
                            $docLast,
                                array('name'=>'d_txtLastName', 'size'=>'17', 'class'=>'hhk-docInfo hospital-stay name'))
                        )
                , array('class'=>'hhk-docInfo'));

            $doctorMarkup = $docErrorMsg . $dtbl->generateMarkup(array('style'=>'display:inline-table; vertical-align: top;'));
        }

        // Diagnosis
        $diags = readGenLookupsPDO($dbh, 'Diagnosis', 'Description');
        $diagCats = readGenLookupsPDO($dbh, 'Diagnosis_Category', 'Description');

        if (count($diags) > 0) {

            $diagtbl = new HTMLTable();

            $myDiagnosis = (isset($referralHospitalData['diagnosis']) && $referralHospitalData['diagnosis'] != '' ? $referralHospitalData['diagnosis'] : $hstay->getDiagnosisCode());
            $diagnosisDetails = (isset($referralHospitalData['diagnosisDetails']) && $referralHospitalData['diagnosisDetails'] != '' ? $referralHospitalData['diagnosisDetails']: $hstay->getDiagnosis2());

            $diagId = (isset($diags[$myDiagnosis]) ? $myDiagnosis : '');
            $diagCat = (!empty($diagId) && isset($diagCats[$diags[$diagId]['Substitute']]) ? $diagCats[$diags[$diagId]['Substitute']][1] . ": " : '');

            if ($uS->UseDiagSearch){
                $diagtbl->addBodyTr(
                    HTMLTable::makeTh(
                        HTMLContainer::generateMarkup("span", $labels->getString('hospital', 'diagnosis', 'Diagnosis'))
                      . HTMLContainer::generateMarkup("span", "", array("class"=>"ui-icon ui-icon-search", "style"=>"margin-left:1.3em; margin-right:0.3em;"))
                      . HTMLInput::generateMarkup("", array('id'=>'diagSearch', 'type'=>'search'))
                        . HTMLInput::generateMarkup($diagId, array("type"=>"hidden", "name"=>"selDiagnosis", 'class'=>'hospital-stay')), array("colspan"=>"2"))
                );

                $selectedClass = "";
                $selectedDiag = "";
                if(empty($diagId)){
                   $selectedClass = "d-none";
                }else{
                    $selectedDiag = $diagCat . $diags[$myDiagnosis][1];
                }

                $diagtbl->addBodyTr(
                    HTMLTable::makeTd(HTMLContainer::generateMarkup("button", HTMLContainer::generateMarkup("span", "", array("class"=>"ui-icon ui-icon-trash")), array("class"=>"ui-corner-all ui-state-default ui-button ui-widget", "style"=>"padding: 0.2em 0.4em;", "id"=>"delDiagnosis"))) .
                    HTMLTable::makeTd(HTMLContainer::generateMarkup("strong", $labels->getString('hospital', 'diagnosis', 'Diagnosis') . ": ") . HTMLContainer::generateMarkup("span", $selectedDiag, array("id"=>"selectedDiag")))
                    , array("class"=>$selectedClass));

            }else{
                //prepare diag categories for doOptionsMkup
                foreach($diags as $key=>$diag){
                    if(!empty($diag['Substitute'])){
                        $diags[$key][2] = $diagCats[$diag['Substitute']][1];
                    }
                }

                $diagtbl->addBodyTr(
                    HTMLTable::makeTh($labels->getString('hospital', 'diagnosis', 'Diagnosis'))
                );
                $diagtbl->addBodyTr(HTMLTable::makeTd(
                    HTMLSelector::generateMarkup(
                        HTMLSelector::doOptionsMkup($diags, $myDiagnosis, TRUE),
                        array('name'=>'selDiagnosis', 'class'=>'hospital-stay', 'style'=>'width: 100%'))
                ));
            }

            // Use Diagnosis as a text box?
            if ($uS->ShowDiagTB) {
                if ($myDiagnosis != '' && isset($diags[$myDiagnosis]) === FALSE) {

                    $diagtbl->addBodyTr(
                        HTMLTable::makeTd(HTMLInput::generateMarkup($hstay->getDiagnosis(), array('name'=>'txtDiagnosis', 'class'=>'hospital-stay', "style"=>"width:100%")), array("colspan"=>"2")));

                    $myDiagnosis = '';
                }else{
                    $diagtbl->addBodyTr(
                        HTMLTable::makeTd(HTMLInput::generateMarkup($diagnosisDetails, array('name'=>'txtDiagnosis', 'class'=>'hospital-stay', 'placeholder'=>$labels->getString('hospital','diagnosisDetail', 'Diagnosis Details'),  "style"=>"width:100%")), array("colspan"=>"2")));
                    $diagnosisDetails = '';
                }
            }

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
                    HTMLSelector::doOptionsMkup($locs, (isset($referralHospitalData['location']) && $referralHospitalData['location'] != '' ? $referralHospitalData['location'] : $hstay->getLocationCode()), TRUE),
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
            self::justHospitalMarkup($hstay, $offerBlankHosp, $referralHospitalData)
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
            $hstay->setAssociationId(filter_var($post['selAssoc'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }
        if (isset($post['selHospital'])) {
            $hstay->setHospitalId(filter_var($post['selHospital'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        if (isset($post['psgRoom'])) {
        	$hstay->setRoom(filter_var($post['psgRoom'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }
        if (isset($post['psgMrn'])) {
            $MRN = filter_var($post['psgMrn'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $MRN = str_replace(["/", "-","_"], "", trim($MRN));
            $hstay->setMrn($MRN);
        }
        if (isset($post['txtEntryDate'])) {
            $dateStr = filter_var($post['txtEntryDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if ($dateStr != '') {
                $enDT = new \DateTime($dateStr);
                $enDT->setTimezone(new \DateTimeZone($uS->tz));
                $hstay->setArrivalDate($enDT->format('Y-m-d H:i:s'));
            }else{
                $hstay->setArrivalDate("");
            }
        }

       if (isset($post['txtExitDate'])) {
            $dateStr = filter_var($post['txtExitDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if ($dateStr != '') {
                $enDT = new \DateTime($dateStr);
                $enDT->setTimezone(new \DateTimeZone($uS->tz));
                $hstay->setExpectedDepartureDate($enDT->format('Y-m-d H:i:s'));
            }else{
                $hstay->setExpectedDepartureDate("");
            }
        }

        if (isset($post['selDiagnosis'])) {

            $myDiagnosis = filter_var($post['selDiagnosis'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $hstay->setDiagnosisCode($myDiagnosis);
        }

        if (isset($post['txtDiagnosis'])) {
            $hstay->setDiagnosis2(filter_var($post['txtDiagnosis'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        if (isset($post['selLocation'])) {
            $hstay->setLocationCode(filter_var($post['selLocation'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
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

    /**
     * Check if a given hospital/association is attached to reservations or visits
     * @param \PDO $dbh
     * @param int $idHosp
     * @return bool
     */
    public static function isHospitalInUse(\PDO $dbh, int $idHosp){
        $totalRecords = 0;

        $resvQuery = "select count(hs.idHospital_stay) from hospital_stay hs
        join reservation r on hs.idHospital_stay = r.idHospital_Stay where hs.idHospital = :idH or hs.idAssociation = :idA;";

        $visitQuery = "select count(hs.idHospital_stay) from hospital_stay hs
        join visit v on hs.idHospital_stay = v.idHospital_Stay where hs.idHospital = :idH or hs.idAssociation = :idA;";

        $resvStmt = $dbh->prepare($resvQuery);
        $resvStmt->execute([":idH" => $idHosp, ":idA"=>$idHosp]);
        $resvRows = $resvStmt->fetchAll(\PDO::FETCH_NUM);
        if(isset($resvRows[0][0])){
            $totalRecords += $resvRows[0][0];
        }else{
            //something's wrong, abort
            return true;
        }

        $visitStmt = $dbh->prepare($visitQuery);
        $visitStmt->execute([":idH" => $idHosp, ":idA"=>$idHosp]);
        $visitRows = $visitStmt->fetchAll(\PDO::FETCH_NUM);
        if(isset($visitRows[0][0])){
            $totalRecords += $visitRows[0][0];
        }else{
            //something's wrong, abort
            return true;
        }

        return ($totalRecords > 0);
    }
}