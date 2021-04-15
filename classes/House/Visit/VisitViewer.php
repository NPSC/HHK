<?php

namespace HHK\House\Visit;

use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\HTMLControls\{HTMLContainer, HTMLTable};
use HHK\HTMLControls\HTMLInput;
use HHK\House\Reservation\ReservationSvcs;
use HHK\House\Reservation\Reservation_1;
use HHK\Payment\Invoice\Invoice;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Purchase\CurrentAccount;
use HHK\Purchase\PaymentChooser;
use HHK\Purchase\ValueAddedTax;
use HHK\Purchase\VisitCharges;
use HHK\SysConst\GLTableNames;
use HHK\SysConst\ItemId;
use HHK\SysConst\ItemPriceCode;
use HHK\SysConst\VisitStatus;
use HHK\TableLog\VisitLog;
use HHK\Tables\EditRS;
use HHK\Tables\Visit\StaysRS;
use HHK\Tables\Visit\VisitRS;
use HHK\Purchase\RateChooser;

/**
 * visitViewer.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Visitview creates HTML markup with specified visit, stay and payment history.  Also saves some of the returned markup.
 *
 * @author Eric
 */
class VisitViewer {

    public static function createActiveMarkup(\PDO $dbh, array $r, VisitCharges $visitCharge, $keyDepFlag, $visitFeeFlag, $isAdmin,
            $extendVisitDays, $action, $coDate, $showAdjust) {

        $uS = Session::getInstance();

        // Take Payment doesn't need this section.
        if ($action == 'cr') {
            return '';
        }

        $table = new HTMLTable();

        // Get labels
        $labels = Labels::getLabels();


        // Notes
        $notesContainer = HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Notes', array('style'=>'font-weight:bold;'))
                , array('id'=>'visitNoteViewer', 'style'=>'clear:left; float:left; width:90%;', 'class'=>'hhk-panel'));


        // Key Deposit
        $kdRow = '';
        $kdHeader = '';

        if ($keyDepFlag && $visitCharge->getDepositCharged() > 0) {

            $keyDepAmount = $visitCharge->getKeyFeesPaid() + $visitCharge->getDepositPending();
            $depAmtText = ($keyDepAmount == 0 ? "" : number_format($keyDepAmount, 2) . ($r['DepositPayType'] != '' ? '(' . $r['DepositPayType'] . ')' : ''));

            // Deposit Disposition selector - only if
            if (($r['Status'] == VisitStatus::CheckedIn || $r['Status'] == VisitStatus::CheckedOut) && $keyDepAmount != 0) {

                $kdRow .= HTMLTable::makeTd(($keyDepAmount == 0 ? "" : "$")
                        .HTMLContainer::generateMarkup('span', $depAmtText, array('id' => 'kdPaid', 'style'=>'margin-right:7px;', 'data-amt'=>$keyDepAmount)));

                $kdHeader .= HTMLTable::makeTh($labels->getString('resourceBuilder', 'keyDepositLabel', 'Deposit'));

            } else {

                $kdRow = HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $depAmtText, array('id' => 'kdPaid', 'data-amt'=>$keyDepAmount)), array('style' => 'text-align:center;'));

                $kdHeader = HTMLTable::makeTh($labels->getString('resourceBuilder', 'keyDepositLabel', 'Deposit'));
            }

        }

        // Arrival text
        $tr = HTMLTable::makeTd(HTMLContainer::generateMarkup('span', date('M j, Y', strtotime($r['Arrival_Date'])), array('id'=>'spanvArrDate')));
        $th = HTMLTable::makeTh('First Arrival');

        $departureText = "";
        $days = "";

        switch ($r['Status']) {
            case VisitStatus::CheckedIn:

                $depHeader = "Expected End";
                $daysHeader = "Expected Nights";

                if ($action == 'ref') {
                    // deal with changed checkout date
                    $deptDT = new \DateTime($coDate);
                    $deptDT->setTime(0, 0, 0);
                    $arrivalDT = new \DateTime($r['Arrival_Date']);
                    $arrivalDT->setTime(0, 0, 0);
                    $days = $deptDT->diff($arrivalDT, TRUE)->days;
                    $departureText = $deptDT->format('M, j, Y');

                } else {

                    $departureText = date('M j, Y', strtotime($r['Expected_Departure']));
                    $days = $r['Expected_Nights'];
                }

                break;

            case VisitStatus::NewSpan:

                $depHeader = "Room Changed";
                $daysHeader = "Span Nights";
                $departureText = date('M j, Y', strtotime($r['Span_End']));
                $days = $r['Actual_Span_Nights'];
                break;

            case VisitStatus::ChangeRate:

                $depHeader = "Changed Room Rate";
                $daysHeader = "Span Nights";
                $departureText = date('M j, Y', strtotime($r['Span_End']));
                $days = $r['Actual_Span_Nights'];
                break;

            case VisitStatus::CheckedOut:
                $depHeader = "Actual End";
                $daysHeader = "Actual Nights";
                $departureText = date('M j, Y', strtotime($r['Actual_Departure']));
                $days = $r['Actual_Nights'];

                break;

            default:
                return HTMLContainer::generateMarkup('h2', "System Error:  Incomplete Visit Record.  Contact your support people.", array("style" => 'color:red;'));

        }

        if ($action != 'co') {
            // Departure
            $tr .= HTMLTable::makeTd($departureText);
            // Days
            $tr .= HTMLTable::makeTd($days, array('style' => 'text-align:center;'));

            $th .= HTMLTable::makeTh($depHeader) . HTMLTable::makeTh($daysHeader);

        }

        // Visit fee
        if ($visitFeeFlag) {

            $rateChooser = new RateChooser($dbh);

            $vFeeSelector = $rateChooser->makeVisitFeeSelector(
                    $rateChooser->makeVisitFeeArray($dbh, $visitCharge->getVisitFeeCharged()), $visitCharge->getVisitFeeCharged(), 'hhk-feeskeys');

            $th .= HTMLTable::makeTh($labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee'));
            $tr .= HTMLTable::makeTd($vFeeSelector);
        }

        // Key Deposit
        if ($action != 'cf') {
            $tr .= $kdRow;
            $th .= $kdHeader;
        }

        // hospital
        $hname = $r['Hospital'];

        if ($r['Association'] != '' && $r['Association'] != '(None)') {
            $hname = $r['Association'] . ' / ' . $hname;
        }

        //$hospitalIcon = HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon hhk-hospitalstay', 'data-idhs'=>$r['idHospital_stay'], 'style'=>"float: right; margin-left:.3em; margin-right:.7em; margin-top:2px; background-image: url('../images/HospitalIcon.png');", 'title'=>$labels->getString('Hospital', 'hospital', 'Hospital').' Viewoer'));
        $hospitalButton = HTMLInput::generateMarkup($hname
        		, array(
        				'type'=>'button',
        				'class'=>'hhk-hospitalstay ui-corner-all hhk-hospTitleBtn ui-button ignrSave',
        				'data-idhs'=>$r['idHospital_stay'],
        		    'style'=>($uS->guestLookups['Hospitals'][$r['idHospital']][5] ? "color:".$uS->guestLookups['Hospitals'][$r['idHospital']][5]."; border: 1px solid black;": '') . ($uS->guestLookups['Hospitals'][$r['idHospital']][4] ? "background:".$uS->guestLookups['Hospitals'][$r['idHospital']][4] . ";" : '').";",
        				'title'=>$labels->getString('Hospital', 'hospital', 'Hospital').' Details')
        		);
        
        $th .= HTMLTable::makeTh($labels->getString('hospital', 'hospital', 'Hospital'));
        $tr .= HTMLTable::makeTd($hospitalButton, array('id'=>'hhk-HospitalTitle'));

        // Patient Name
        if ($r['Patient_Name'] != '') {
            $th .= HTMLTable::makeTh($labels->getString('MemberType', 'patient', 'Patient'));
            $tr .= HTMLTable::makeTd($r['Patient_Name']);
        }


        // add completed rows to table
        $table->addBodyTr($tr);
        $table->addHeaderTr($th);
        $tblMarkup = $table->generateMarkup(array('id' => 'tblActiveVisit', 'style'=>'width:99%;'));

        // Adjust button
        if ($showAdjust && $action != 'ref') {

            $tblMarkup .= HTMLContainer::generateMarkup('div',
                    HTMLInput::generateMarkup('Adjust Fees...', array('name'=>'paymentAdjust', 'type'=>'button', 'class'=>'hhk-feeskeys', 'title'=>'Create one-time additional charges or discounts.'))
                    , array('style'=>'float:left;margin-right:.5em;margin-bottom:.3em; margin-top:.3em;'));
        }

        // Change Rate markup
        if ($uS->RoomPriceModel != ItemPriceCode::None && $action != 'ref') {

            $rateChooser = new RateChooser($dbh);
            $vRs = new VisitRs();
            EditRS::loadRow($r, $vRs);
            $rateTbl = $rateChooser->createChangeRateMarkup($dbh, $vRs, $isAdmin);

            $tblMarkup .= $rateTbl->generateMarkup(array('style'=>'float:left;margin-bottom:.3em; margin-top:.3em;'));
        }

        // Weekender button
        if ($r['Status'] == VisitStatus::CheckedIn && $extendVisitDays > 0 && $action != 'ref') {
            $etbl = new HTMLTable();

            $olStmt = $dbh->query("select sum(On_Leave) from `stays` where `stays`.`idVisit` = " . $r['idVisit'] . " and `stays`.`Status` = 'a' ");
            $olRows = $olStmt->fetchAll(\PDO::FETCH_NUM);

            if ($olRows[0][0] > 0) {

                $etbl->addHeaderTr(HTMLTable::makeTh(
                        HTMLContainer::generateMarkup('label', 'On Leave', array('for'=>'leaveRetCb', 'style'=>'margin-right:.5em;'))
                        .HTMLInput::generateMarkup('', array('name' => 'leaveRetCb', 'type' => 'checkbox', 'class' => 'hhk-feeskeys hhk-extVisitSw'))));
                $etbl->addBodyTr(HTMLTable::makeTd(
                        HTMLInput::generateMarkup('', array('id'=>'returningRb', 'name'=>'rbExtRet', 'type'=>'radio', 'checked'=>'checked', 'style'=>'margin-left:.3em;', 'class'=>'hhk-feeskeys'))
                        .HTMLContainer::generateMarkup('label', 'Returning', array('for'=>'returningRb', 'style'=>'margin-left:.3em;')), array('style'=>'display:none;', 'class'=>'hhk-extendVisit')));
                $etbl->addBodyTr(HTMLTable::makeTd(
                        HTMLInput::generateMarkup('', array('id'=>'noReturnRb', 'name'=>'rbExtRet', 'type'=>'radio', 'style'=>'margin-left:.3em;', 'class'=>'hhk-feeskeys'))
                        .HTMLContainer::generateMarkup('label', 'Not Returning', array('for'=>'noReturnRb', 'style'=>'margin-left:.3em;')), array('style'=>'display:none;', 'class'=>'hhk-extendVisit')));
                $etbl->addBodyTr(HTMLTable::makeTd(
                        HTMLContainer::generateMarkup('span', 'Date:', array('style'=>'margin-left:.3em;'))
                        . HTMLInput::generateMarkup(date('M j, Y'), array('id'=>'txtWRetDate', 'style'=>'margin-left:.3em;', 'class'=>'ckdate hhk-feeskeys')), array('style'=>'display:none;', 'class'=>'hhk-extendVisit')));

            } else {

                $etbl->addHeaderTr(HTMLTable::makeTh(
                        HTMLContainer::generateMarkup('label', 'Weekend Leave', array('for'=>'extendCb', 'style'=>'margin-right:.5em;'))
                        .HTMLInput::generateMarkup('', array('name' => 'extendCb', 'type' => 'checkbox', 'class' => 'hhk-feeskeys hhk-extVisitSw'))));
                $etbl->addBodyTr(HTMLTable::makeTd(
                        HTMLContainer::generateMarkup('span', 'For:', array('style'=>'margin-left:.3em;'))
                        . HTMLInput::generateMarkup($extendVisitDays, array('name' => 'extendDays', 'size'=>'2', 'style'=>'margin-left:.3em;', 'class' => 'hhk-feeskeys'))
                        . HTMLContainer::generateMarkup('span', 'days', array('style'=>'margin-left:.3em;')), array('style'=>'display:none;', 'class'=>'hhk-extendVisit')));
                $etbl->addBodyTr(HTMLTable::makeTd(
                        HTMLContainer::generateMarkup('span', 'Starting:', array('style'=>'margin-left:.3em;'))
                        . HTMLInput::generateMarkup('Today', array('name' => 'txtWStart', 'readonly'=>'readonly', 'size'=>'7', 'style'=>'margin-left:.3em;')), array('style'=>'display:none;', 'class'=>'hhk-extendVisit')));

                if ($uS->RoomPriceModel != ItemPriceCode::None) {
                    $etbl->addBodyTr(HTMLTable::makeTd(
                        HTMLContainer::generateMarkup('label', 'No Room Charge', array('for'=>'noChargeCb', 'style'=>'margin-right:.5em;'))
                        .HTMLInput::generateMarkup(date('M j, Y'), array('name' => 'noChargeCb', 'type' => 'checkbox', 'class' => 'hhk-feeskeys')), array('style'=>'display:none;', 'class'=>'hhk-extendVisit')));
                }
            }

            $tblMarkup .= $etbl->generateMarkup(array('style'=>'float:left;margin-left:.5em;margin-bottom:.3em; margin-top:.3em;'));
        }


        $tblMarkup .= $notesContainer;

        $undoCkoutButton = '';

        // Make undo checkout button.
        if ($r['Status'] == VisitStatus::CheckedOut) {

            $spnMkup = HTMLContainer::generateMarkup('label', '- Undo Checkout', array('for'=>'undoCkout'))
                    . HTMLInput::generateMarkup('', array('id'=>'undoCkout', 'type'=>'checkbox', 'class'=>'hhk-feeskeys', 'style'=>'margin-right:.3em;margin-left:0.3em;'))
                    . HTMLContainer::generateMarkup('span', 'New Expected Departure Date: ', array('style'=>'margin-right: 0.3em; margin-left:0.3em;'))
                    . HTMLInput::generateMarkup('', array('id'=>'txtUndoDate', 'class'=>'ckdateFut hhk-feeskeys'));

            $undoCkoutButton = HTMLContainer::generateMarkup('span', $spnMkup, array('style'=>'margin:0 1em;', 'title'=>'Undo Checkout'));

        } else if ($r['Status'] == VisitStatus::NewSpan) {

            $spnMkup = HTMLContainer::generateMarkup('label', '- Undo Room Change', array('for'=>'undoRmChg'))
                    . HTMLInput::generateMarkup('', array('id'=>'undoRmChg', 'type'=>'checkbox', 'class'=>'hhk-feeskeys', 'style'=>'margin-right:.3em;margin-left:0.3em;'));

            $undoCkoutButton = HTMLContainer::generateMarkup('span', $spnMkup, array('style'=>'margin:0.1em;', 'title'=>'Undo Room Change'));
        }



        return
            HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Visit' . $undoCkoutButton, array('style'=>'font-weight:bold;'))
                . $tblMarkup
                , array('class'=>'hhk-panel', 'style'=>'margin-bottom:10px;'));

    }

    /**
     *
     * @param \PDO $dbh
     * @param integer $idVisit
     * @param integer $span
     * @param boolean $isAdmin
     * @param integer $idGuest
     * @param string $action
     * @return string
     */
    public static function createStaysMarkup(\PDO $dbh, $idResv, $idVisit, $span, $idPrimaryGuest, $isAdmin, $idGuest, $labels, $action = '', $coDate = []) {

        $uS = Session::getInstance();

        $includeAction = FALSE;
        $useRemoveHdr = FALSE;
        $someoneCheckedIn = FALSE;
        $ckOutTitle = '';
        $sTable = new HTMLTable();
        $priGuests = array();
        $rows = array();
        $hdrPgRb = '';
        $chkInTitle = '';
        $visitStatus = '';
        $guestAddButton = '';
        $onLeave = 0;
        $idV = intval($idVisit, 10);
        $idS = intval($span, 10);

        if ($idV > 0 && $idS > -1) {
            // load stays for this visit
            $stmt = $dbh->query("select * from `vstays_listing` where `idVisit` = $idVisit and `Visit_Span` = $span order by `Status`, `Span_Start_Date` desc;");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }


        foreach ($rows as $r) {

            $visitStatus = $rows[0]['Visit_Status'];
            $onLeave = $r['On_Leave'];
            $days = 0;

            $actionButton = "";
            $ckOutDate = "";
            $name = $r['Name_First'] . ' ' . $r['Name_Last'];

            if (($action == 'so' || $action == 'ref') && $r['Status'] != VisitStatus::CheckedIn) {
                continue;
            }

            // Preselect checkout box
            if ($action == 'co' && $r['idName'] == $idGuest) {
                // Mark check-out checkbox
                $r['Cked'] = "y";
            }

            // Prepare checkbox attributes.
            $cbAttr = array(
                'id' => 'stayActionCb_' . $r['idName'],
                'name' => '[stayActionCb][' . $r['idName'] . ']',
                'data-nm' => $name,
                'type' => 'checkbox',
                'class' => 'hhk-ckoutCB',
                'style' => 'margin-right:.3em;'
            );

            if (isset($r['Cked']) || $action == 'ref') {
                $cbAttr['checked'] = 'checked';
            }

            // Primary guest selector.
            $hdrPgRb = '';
            if ($r["Visit_Status"] == VisitStatus::CheckedIn && count($rows) > 1) {

                $pgAttrs = array('name'=>'rbPriGuest', 'type'=>'radio', 'class'=>'hhk-feeskeys', 'title'=>'Make the Primary Guest');

                // Only set the first instance of any guest.
                if ($r['idName'] == $idPrimaryGuest && isset($priGuests[$idPrimaryGuest]) === FALSE) {
                    $pgAttrs['checked'] = 'checked';
                    $priGuests[$idPrimaryGuest] = 'y';
                }

                $pgRb = HTMLInput::generateMarkup($r['idName'], $pgAttrs);
                $hdrPgRb = HTMLTable::makeTh('Pri', array('title'=>'Primary Guest'));
            }

            $stDayDT = new \DateTime($r['Span_Start_Date']);
            $stDayDT->setTime(0, 0, 0);

            $chkInTitle = 'Checked In';

            // Action button depends on status
            if ($r["Visit_Status"] == VisitStatus::CheckedIn) {

                if ($r['Status'] == VisitStatus::CheckedIn) {

                    $someoneCheckedIn = TRUE;

                    if ($action == 'ref' && isset($coDate[$r['idName']])) {
                    	$edDay = new \DateTime($coDate[$r['idName']]);
                    } else {
                        $edDay = new \DateTime(date('Y-m-d'));
                    }

                    $edDay->setTime(0, 0, 0);
                    $days = $edDay->diff($stDayDT, TRUE)->days;

                    $getCkOutDate = HTMLInput::generateMarkup($edDay->format('M j, Y'), array('id' => 'stayCkOutDate_' . $r['idName'], 'name' =>'[stayCkOutDate][' . $r['idName'] . ']', 'class' => 'ckdate hhk-ckoutDate', 'readonly'=>'readonly', 'data-gid'=>$r['idName']));

                    if ($uS->CoTod) {
                        $getCkOutDate .= HTMLInput::generateMarkup(date('H'), array('id' => 'stayCkOutHour_' . $r['idName'], 'name' =>'[stayCkOutHour][' . $r['idName'] . ']', 'size'=>'3'));
                    }

                    $ckOutDate = HTMLInput::generateMarkup(date('M j, Y', strtotime($r['Expected_Co_Date'])), array('id' => 'stayExpCkOut_' . $r['idName'], 'name' => '[stayExpCkOut][' . $r['idName'] . ']', 'class' => 'ckdateFut hhk-expckout', 'readonly'=>'readonly'));
                    $ckOutTitle = "Exp'd Check Out";
                    $actionButton = HTMLInput::generateMarkup('', $cbAttr) . $getCkOutDate;

                    if ($action == 'co' || $action == 'ref' || $action == '') {
                        $includeAction = TRUE;
                    }

                } else {

                    $edDay = new \DateTime($r['Span_End_Date']);
                    $edDay->setTime(0, 0, 0);

                    $days = $edDay->diff($stDayDT, TRUE)->days;

                    // Don't show 0-day checked - out stays.
                    if ($days == 0 && !$uS->ShowZeroDayStays) {
                        continue;
                    }

                    $ckOutDate = HTMLContainer::generateMarkup('span', $r['Span_End_Date'] != '' ? date('M j, Y H:i', strtotime($r['Span_End_Date'])) : '');

                }

            } else {

                $edDay = new \DateTime($r['Span_End_Date']);
                $edDay->setTime(0, 0, 0);

                $days = $edDay->diff($stDayDT, TRUE)->days;

                // Don't show 0-day checked - out stays.
                if ($days == 0 && !$uS->ShowZeroDayStays) {
                    continue;
                }

                switch ($r['Visit_Status']) {
                    case VisitStatus::ChangeRate:
                        $ckOutTitle = "Rate Changed";
                        break;

                    case VisitStatus::NewSpan:
                        $ckOutTitle = "Room Changed";
                        break;

                    case VisitStatus::CheckedOut:
                        $ckOutTitle = "Checked Out";
                        break;

                }

                $ckOutDate = HTMLContainer::generateMarkup('span', $r['Span_End_Date'] != '' ? date('M j, Y H:i', strtotime($r['Span_End_Date'])) : '');
            }

            // guest Name
            if ($idGuest == $r['idName']) {
                $idMarkup = HTMLContainer::generateMarkup('a', $name, array('href' => 'GuestEdit.php?id=' . $r['idName'] . '&psg='.$r['idPsg'], 'class' => 'ui-state-highlight'));
            } else {
                $idMarkup = HTMLContainer::generateMarkup('a', $name, array('href' => 'GuestEdit.php?id=' . $r['idName'] . '&psg='.$r['idPsg']));
            }

            // Relationship to patient
            $rel = '';
            if (isset($uS->guestLookups[GLTableNames::PatientRel][$r['Relationship_Code']])) {
                $rel = $uS->guestLookups[GLTableNames::PatientRel][$r['Relationship_Code']][1];
            }


            $tr = ($hdrPgRb == '' ? '' : HTMLTable::makeTd($pgRb))
                // idName
                .HTMLTable::makeTd($idMarkup)
                // Relationship
                .HTMLTable::makeTd($rel)

                // Status
                . HTMLTable::makeTd($r['On_Leave'] > 0 ? 'On Leave' : $r['Status_Title'])

                // room
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $r["Room"]))

                // CheckIn date
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup(date('M j, Y', strtotime($r['Span_Start_Date'])), array('id' => 'stayCkInDate_' . $r['idStays'], 'class'=>'hhk-stayckin ckdate', 'readonly'=>'raadonly'))
                        . ' ' . date('H:i', strtotime($r['Span_Start_Date'])));


            if ($action == '') {
                // Check Out/Expected check out date
                $tr .=  HTMLTable::makeTd($ckOutDate)

                // Days
                . HTMLTable::makeTd($days);
            }


            // Action button
            $tr .=  ($includeAction === TRUE ? HTMLTable::makeTd($actionButton) : "");

            // Remove button - only if more than one guest is staying
            if ($action == ''
                    && count($rows) > 1
                    && $r['On_Leave'] == 0
                    && $r['Status'] != VisitStatus::CheckedIn
                    && $r['idName'] != $idPrimaryGuest
//                    && $r['Visit_Span'] == 0
//                    && ($r["Visit_Status"] == VisitStatus::CheckedIn || $r["Visit_Status"] == VisitStatus::CheckedOut)
                    ) {

                $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup('', array('id' => 'removeCb_' . $r['idStays'], 'name' => '[removeCb][' . $r['idStays'] . ']',
                    'data-nm' => $name,
                    'type' => 'checkbox',
                    'class' => 'hhk-removeCB' )), array('style'=>'text-align:center;'));

                $useRemoveHdr = TRUE;
            }

            if ($r['Status'] != VisitStatus::CheckedIn) {
                $sTable->addBodyTr($tr, array('style'=>'background-color:#f2f2f2;'));
            } else {
                $sTable->addBodyTr($tr);
            }
        }

        // Adjust headers in this condition
        if ($someoneCheckedIn === FALSE && $visitStatus == VisitStatus::CheckedIn) {
            // Set if there are no expected checkouts
            $ckOutTitle = ($onLeave > 0 ? 'Ending' : 'Checked Out');
            $chkInTitle = ($onLeave > 0 ? 'Starting' : 'Checked In');
        }

        // Table header
        $th = ($hdrPgRb == '' ? '' : $hdrPgRb)
            . HTMLTable::makeTh('Name')
            . HTMLTable::makeTh($labels->getString('MemberType', 'patient', 'Patient') . ' Relation')
            . HTMLTable::makeTh('Status')
            . HTMLTable::makeTh('Room')
            . HTMLTable::makeTh($chkInTitle);

        if ($action == '') {
            $th .= HTMLTable::makeTh($ckOutTitle) . HTMLTable::makeTh('Nights');

            // Make add guest button
            $guestAddButton = HTMLInput::generateMarkup('Add Guest...', array('id'=>'btnAddGuest', 'type'=>'button', 'style'=>'margin-left:1.3em; font-size:.8em;', 'data-rid'=>$idResv, 'data-vstatus'=>$visitStatus, 'data-vid'=>$idVisit, 'data-span'=>$span, 'title'=>'Add another guest to this visit.'));

        }

        if ($includeAction) {

            $td = 'Check Out';

            // Checkout ALL button.
            if (count($rows) > 1) {

                $td .= HTMLInput::generateMarkup('All', array('id'=>'cbCoAll', 'type'=>'button', 'style'=>'margin-right:.5em;margin-left:.5em;'));
            }

            $th .= HTMLTable::makeTh($td);
        }

        if ($useRemoveHdr) {
            $th .= HTMLTable::makeTh('Remove');
        }

        $sTable->addHeaderTr($th);

        $dvTable = HTMLContainer::generateMarkup('div', $sTable->generateMarkup(array('id' => 'tblStays', 'style'=>'width:99%')), array('style'=>'max-height:150px;overflow:auto'));


        $titleMkup = HTMLContainer::generateMarkup('span', 'Guests', array('style'=>'float:left;'));





        return HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', $titleMkup . $guestAddButton, array('style'=>'font-weight:bold;'))
                . $dvTable
                , array('class'=>'hhk-panel', 'style'=>'margin-bottom:10px;'));

    }

    public static function createPaymentMarkup(\PDO $dbh, $r, VisitCharges $visitCharge, $idGuest = 0, $action = '') {

        // Notes action = return nothing.
        if ($action == 'no' || $action == 'cf'|| $action == 'cr'|| $action == 'tr') {
            return '';
        }

        $uS = Session::getInstance();

        $includeKeyDep = FALSE;
        $unpaidKeyDep = FALSE;
        
        if ($uS->KeyDeposit && $r['Status'] == VisitStatus::CheckedIn && ($action == '' || $action == 'pf' || $action == 'ref')) {
            $includeKeyDep = TRUE;
            if($visitCharge->getDepositCharged() > 0 && ($visitCharge->getDepositPending() + $visitCharge->getKeyFeesPaid()) < $visitCharge->getDepositCharged()){
                $unpaidKeyDep = TRUE;
            }
        }
        
        $includeVisitFee = FALSE;
        if ($uS->VisitFee && ($action == '' || $action == 'pf' || $action == 'ref') && $visitCharge->getVisitFeeCharged() > 0) {
            $includeVisitFee = TRUE;
        }

        $includeAddnlCharge = FALSE;
        $addnls = readGenLookupsPDO($dbh, 'Addnl_Charge');
        $discs = readGenLookupsPDO($dbh, 'House_Discount');
        if (count($addnls) > 0 || count($discs) > 0) {
            $includeAddnlCharge = TRUE;
        }

        $showRoomFees = TRUE;
        if ($uS->RoomPriceModel == ItemPriceCode::None) {
            $showRoomFees = FALSE;
        }

        $showGuestNights = FALSE;
        if ($uS->RoomPriceModel == ItemPriceCode::PerGuestDaily) {
            $showGuestNights = TRUE;
        }

        // Any taxes
        $vat = new ValueAddedTax($dbh, $visitCharge->getIdVisit());

        $currFees = '';
        $paymentMarkup = '';


        if ($includeKeyDep || $includeVisitFee || $includeAddnlCharge || $showRoomFees) {

            // Current fees block
            $currFees = HTMLContainer::generateMarkup('fieldset',
                    HTMLContainer::generateMarkup('legend', ($r['Status'] == VisitStatus::CheckedIn ? 'To-Date Fees & Balance Due' : 'Final Fees & Balance Due'), array('style'=>'font-weight:bold;'))
                    . HTMLContainer::generateMarkup('div', self::createCurrentFees($r['Status'], $visitCharge, $vat, $includeVisitFee, $showRoomFees, $showGuestNights), array('style'=>'float:left;', 'id'=>'divCurrFees'))
                        , array('class'=>'hhk-panel', 'style'=>'float:left;margin-right:10px;'));

            // Show Final payment?
            $showFinalPayment = FALSE;
            if ($action != 'pf' && ($r['Status'] == VisitStatus::CheckedIn || $r['Status'] == VisitStatus::CheckedOut)) {
                $showFinalPayment = TRUE;
            }

            $paymentGateway = AbstractPaymentGateway::factory($dbh, $uS->PaymentGateway, AbstractPaymentGateway::getCreditGatewayNames($dbh, $visitCharge->getIdVisit(), $visitCharge->getSpan(), $r['idRegistration']));

            // New Payments
            $paymentMarkup = PaymentChooser::createMarkup(
                    $dbh,
                    $idGuest,
                    $r['idRegistration'],
                    $visitCharge,
                    $paymentGateway,
                    $uS->DefaultPayType,
                    $unpaidKeyDep,
                    $showFinalPayment,
                    FALSE,
                    $r['Pref_Token_Id']
                    );

        }


        return $currFees . $paymentMarkup;
    }

    public static function createCurrentFees($visitStatus, VisitCharges $visitCharge, ValueAddedTax $vat, $showVisitFee = FALSE, $showRoomFees = TRUE, $showGuestNights = FALSE) {

        $roomAccount = new CurrentAccount($visitStatus, $showVisitFee, $showRoomFees, $showGuestNights);

        $roomAccount->load($visitCharge, $vat);
        $roomAccount->setDueToday();

        return self::currentBalanceMarkup($roomAccount);
    }

    protected static function currentBalanceMarkup(CurrentAccount $curAccount) {

        $tbl2 = new HTMLTable();
        $showSubTotal = FALSE;
        // Get labels
        $labels = Labels::getLabels();
        
        // Number of nights
        $tbl2->addBodyTr(
                HTMLTable::makeTd('# of nights stayed:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd($curAccount->getNumberNitesStayed())
        );


        // Number of guest-nights
        if ($curAccount->getShowGuestNites()) {

            $tbl2->addBodyTr(
                    HTMLTable::makeTd('Additional guest-nights:', array('class'=>'tdlabel'))
                    . HTMLTable::makeTd($curAccount->getAddnlGuestNites() < 0 ? 0 : $curAccount->getAddnlGuestNites())
            );
        }

        // Visit Glide
        if ($curAccount->getVisitGlideCredit() > 0) {
            $tbl2->addBodyTr(
                HTMLTable::makeTd('Room rate aged (days):', array('class'=>'tdlabel'))
                . HTMLTable::makeTd($curAccount->getVisitGlideCredit()));
        }

        // Room Fees Charged
        if ($curAccount->getShowRoomFees()) {

            $tbl2->addBodyTr(
            		HTMLTable::makeTd($labels->getString('PaymentChooser', 'RmFeesPledged', 'Room fees pledged to-date') . ':', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($curAccount->getRoomCharge(), 2), array('style'=>'text-align:right;'))
            );
        }

            // Lodging Taxes
            if (count($curAccount->getCurentTaxItems(ItemId::Lodging)) > 0) {

                $showSubTotal = TRUE;

                foreach ($curAccount->getCurentTaxItems(ItemId::Lodging) as $t) {
                    $taxedRoomFees = $curAccount->getRoomCharge() + $curAccount->getTotalDiscounts() - $curAccount->getTaxExemptRoomFees();
                    
                    if ($curAccount->getRoomFeeBalance() < 0) {
                        if($taxedRoomFees > 0){
                            $taxAmt = $t->getTaxAmount($taxedRoomFees);
                        }else{
                            $taxAmt = 0;
                        }
                    } else {
                        $taxAmt = $curAccount->getLodgingTaxPd($t->getIdTaxingItem()) + $t->getTaxAmount($curAccount->getRoomFeeBalance());
                    }

                    $tbl2->addBodyTr(
                        HTMLTable::makeTd($t->getTaxingItemDesc() .  ' (' . $t->getTextPercentTax() . ' of $' . number_format($taxedRoomFees, 2) . '):', array('class'=>'tdlabel', 'style'=>'font-size:small;'))
                        . HTMLTable::makeTd('$' . number_format($taxAmt, 2), array('style'=>'text-align:right;font-size:small;'))
                    );
                }
            }
            
            //}

        // Visit fees charged
        if ($curAccount->getVisitFeeCharged() > 0) {

            $showSubTotal = TRUE;

            $tbl2->addBodyTr(
                HTMLTable::makeTd($labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee') . ':', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($curAccount->getVisitFeeCharged(), 2), array('style'=>'text-align:right;'))
            );
        }

        // Additional charges
        if ($curAccount->getAdditionalCharge() > 0) {

            $showSubTotal = TRUE;

            $tbl2->addBodyTr(
                HTMLTable::makeTd('Additional Charges:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($curAccount->getAdditionalCharge(), 2), array('style'=>'text-align:right;'))
            );

            // Additional Charge taxes?
            if ($curAccount->getAdditionalChargeTax() > 0) {

                $taxingItems = $curAccount->getCurentTaxItems(ItemId::AddnlCharge);

                foreach ($taxingItems as $t) {
                    $tbl2->addBodyTr(
                        HTMLTable::makeTd('Additional Charges Tax (' . $t->getTextPercentTax() . '):', array('class'=>'tdlabel', 'style'=>'font-size:small;'))
                        . HTMLTable::makeTd('$' . number_format($curAccount->getAdditionalChargeTax(), 2), array('style'=>'text-align:right;font-size:small;'))
                    );
                }
            }
        }
        
        // Discounts
        if ($curAccount->getTotalDiscounts() != 0) {
            
            $showSubTotal = TRUE;
            
            $tbl2->addBodyTr(
                HTMLTable::makeTd('Discounts & Waives:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($curAccount->getTotalDiscounts(), 2), array('style'=>'text-align:right;'))
                );
        }

        // Unpaid MOA
        if ($curAccount->getUnpaidMOA() > 0) {

            $showSubTotal = TRUE;

            $tbl2->addBodyTr(
                HTMLTable::makeTd('Money On Account:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($curAccount->getUnpaidMOA(), 2), array('style'=>'text-align:right;'))
            );
        }

        // Subtotal line
        if ($showSubTotal) {

            $tbl2->addBodyTr(
                HTMLTable::makeTd('Total Charges:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($curAccount->getTotalCharged(), 2), array('style'=>'text-align:right;border-top: solid 3px #2E99DD;'))
            );
        }

        // Total Paid to date
        $tbl2->addBodyTr(
                HTMLTable::makeTd('Amount paid to-date:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($curAccount->getTotalPaid(), 2), array('style'=>'text-align:right;'))
        );

        // unpaid invoices
        if ($curAccount->getAmtPending() != 0) {
            $tbl2->addBodyTr(
                HTMLTable::makeTd('Amount Pending:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($curAccount->getAmtPending(), 2), array('style'=>'text-align:right;'))
            );
        }



        // Special class for current balance.
        $balAttr = array('style'=>'border-top: solid 3px #2E99DD;text-align:right;');
        $feesTitle = "";

        if ($curAccount->getDueToday() > 0) {

            $balAttr['class'] = 'ui-state-highlight';
            $balAttr['title'] = 'Payment due today.';

            if ($curAccount->getVisitStatus() != VisitStatus::CheckedIn) {
                $feesTitle = 'House is owed at checkout:';
            } else {
                $feesTitle = 'House is owed as of today:';
            }

        } else if ($curAccount->getDueToday() == 0) {

            $balAttr['title'] = 'No payments are due today.';
            $feesTitle = 'Balance as of today:';

        } else {

            $balAttr['title'] = 'No payments are due today.';

            if ($curAccount->getVisitStatus() != VisitStatus::CheckedIn) {
                $feesTitle = 'Guest credit at checkout:';
            } else {
                $feesTitle = 'Guest credit as of today:';
            }
        }

        $tbl2->addBodyTr(
                HTMLTable::makeTd($feesTitle, array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . HTMLContainer::generateMarkup('span', number_format(abs($curAccount->getDueToday()), 2)
                        , array(
                            'id'=>'spnCfBalDue',
                        		'data-rmbal'=> number_format($curAccount->getRoomFeeBalance(), 2, '.', ''),
                                'data-taxedrmbal'=> number_format($curAccount->getTaxedRoomFeeBalance(), 2, '.', ''),
                            'data-vfee'=>number_format($curAccount->getVfeeBal(), 2, '.', ''),
                            'data-totbal'=>number_format($curAccount->getDueToday(), 2, '.', '')))
                        , $balAttr)
        );

        return $tbl2->generateMarkup() ;

    }

    /**
     * Save a subset of fields in the visit.
     *
     * @param \PDO $dbh
     * @param int $idVisit
     * @param int $span
     * @param array $pData
     * @param string $uname
     * @param string $idPrefix
     * @return string
     */
    public static function removeStay(\PDO $dbh, $idVisit, $span, $idStay, $uname) {

        if ($idStay == 0) {
            return;
        }

        $reply = '';

        $today = new \DateTimeImmutable();
        $today->setTime(0,0,0);
        $stayCoversSpan = FALSE;
        $stayFound = FALSE;
        $earliestStart = new \DateTime('2900-01-01');
        $latestEnd = new \DateTime('1984-01-01');


        // recordset
        $visitRS = new VisitRs();
        $visitRS->idVisit->setStoredVal($idVisit);
        $visitRS->Span->setStoredVal($span);

        $rows = EditRS::select($dbh, $visitRS, array($visitRS->idVisit, $visitRS->Span));

        if (count($rows) != 1) {
            return 'The Visit Span is not found.  ';
        }

        EditRS::loadRow($rows[0], $visitRS);

        // Span dates
        $spanStartDT = new \DateTime($visitRS->Span_Start->getStoredVal());
        $spanStartTime = $spanStartDT->format('H:i:s');
        $spanStartDT->setTime(0,0,0);

        if ($visitRS->Span_End->getStoredVal() != '') {
            $spanEndDT = new \DateTime($visitRS->Span_End->getStoredVal());
            $spanEndTime = $spanEndDT->format('H:i:s');
        } else {
            $spanEndDT = new \DateTime($visitRS->Expected_Departure->getStoredVal());
            $spanEndTime = $spanEndDT->format('H:i:s');
            if ($spanEndDT < $today) {
                $spanEndDT = $today;
            }
        }

        $spanEndDT->setTime(0, 0, 0);

        $stayRs = new StaysRS();
        $stayRs->idVisit->setStoredVal($idVisit);
        $stayRs->Visit_Span->setStoredVal($span);
        $stayRows = EditRS::select($dbh, $stayRs, array($stayRs->idVisit, $stayRs->Visit_Span));

        foreach ($stayRows as $st) {

            if ($st['idStays'] == $idStay) {

                EditRS::loadRow($st, $stayRs);
                $stayFound = TRUE;

            } else {

                $stayStartDT = new \DateTime($st['Span_Start_Date']);
                $stayStartDT->setTime(0, 0, 0);

                if ($st['Span_End_Date'] != '') {
                    $stayEndDT = new \DateTime($st['Span_End_Date']);
                } else {
                    $stayEndDT = new \DateTime($st['Expected_Co_Date']);
                    if ($stayEndDT < $today) {
                        $stayEndDT = $today;
                    }
                }
                $stayEndDT->setTime(0,0,0);

                // Find stays that extend the full span.
                if ($st['Status'] == $visitRS->Status->getStoredVal() && $stayStartDT == $spanStartDT && $stayEndDT == $spanEndDT) {
                    $stayCoversSpan = TRUE;
                }

                // find earliest start and latest end
                if ($stayStartDT < $earliestStart) {
                    $earliestStart = $stayStartDT;
                }
                if ($stayEndDT > $latestEnd) {
                    $latestEnd = $stayEndDT;
                }
            }
        }

        if ($stayFound === FALSE) {
            return "The Guest Stay is not found ($idStay).  ";
        }

        // Primary guest
        if ($stayRs->idName->getStoredVal() == $visitRS->idPrimaryGuest->getStoredVal()) {
            return 'Switch the primary guest to someone else before deleting this guest.  ';
        }


        // Does another stay extend the full span duration?
        if ($stayCoversSpan) {

            //delete record
            EditRS::delete($dbh, $stayRs, array($stayRs->idStays));

            $logText = VisitLog::getDeleteText($stayRs, $stayRs->idStays->getStoredVal());
            VisitLog::logStay($dbh, $idVisit, $span, $stayRs->idRoom->getStoredVal(), $stayRs->idStays->getStoredVal(),$stayRs->idName->getStoredVal(), $visitRS->idRegistration->getStoredVal(), $logText, "delete", $uname);

            return 'Guest deleted from this visit span.  ';

        } else if ($visitRS->Status->getStoredVal() == VisitStatus::NewSpan || $visitRS->Status->getStoredVal() == VisitStatus::ChangeRate) {
            return 'This guest stay defines the span length so this stay cannot be deleted.  ';
        }

        //delete stay record
        EditRS::delete($dbh, $stayRs, array($stayRs->idStays));

        $logText = VisitLog::getDeleteText($stayRs, $stayRs->idStays->getStoredVal());
        VisitLog::logStay($dbh, $idVisit, $span, $stayRs->idRoom->getStoredVal(), $stayRs->idStays->getStoredVal(),$stayRs->idName->getStoredVal(), $visitRS->idRegistration->getStoredVal(), $logText, "delete", $uname);


        $visitRS->Span_Start->setNewVal($earliestStart->format('y-m-d ' . $spanStartTime));

        if ($span == 0) {
            $visitRS->Arrival_Date->setNewVal($earliestStart->format('y-m-d H:i:s'));
        }

        if ($visitRS->Status->getStoredVal() != VisitStatus::CheckedIn) {

            $visitRS->Span_End->setNewVal($latestEnd->format('y-m-d ' . $spanEndTime));
            $visitRS->Actual_Departure->setNewVal($latestEnd->format('y-m-d ' . $spanEndTime));
        }

        $visitRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $visitRS->Updated_By->setNewVal($uname);

        $uctr = EditRS::update($dbh, $visitRS, array($visitRS->idVisit, $visitRS->Span));

        if ($uctr > 0) {
            $logText = VisitLog::getUpdateText($visitRS);
            VisitLog::logVisit($dbh, $idVisit, $visitRS->Span->getStoredVal(), $visitRS->idResource->getStoredVal(), $visitRS->idRegistration->getStoredVal(), $logText, "update", $uname);
            $reply .= 'Visit start and/or end dates changed due to removing the guest.  ';
        }


        return $reply;
    }

    /**
     * Move a visit temporally by delta days
     *
     * @param \PDO $dbh
     * @param int $idVisit
     * @param int $span
     * @param int $startDelta
     * @param int $endDelta
     * @param string $uname
     * @return string
     */
    public static function moveVisit(\PDO $dbh, $idVisit, $span, $startDelta, $endDelta, $uname) {

        $uS = Session::getInstance();

        if ($startDelta == 0 && $endDelta == 0) {
            return '';
        }

        if (abs($endDelta) > ($uS->MaxExpected) || abs($startDelta) > ($uS->MaxExpected)) {
            return 'Move refused, change too large: Start Delta = ' . $startDelta . ', End Delta = ' . $endDelta;
        }

        // get visit recordsets, order by span
        $visitRS = new VisitRs();
        $visitRS->idVisit->setStoredVal($idVisit);
        $visitRcrds = EditRS::select($dbh, $visitRS, array($visitRS->idVisit), 'and', array($visitRS->Span));

        // Bad visit?.
        if (count($visitRcrds) < 1) {
            return 'Visit not found';
        }

        $startInterval = new \DateInterval('P' . abs($startDelta) . 'D');
        $endInterval = new \DateInterval('P' . abs($endDelta) . 'D');

        $spans = array();
        $stays = array();
        $firstArrival = NULL;


        $lastSpanId = 0;
        foreach ($visitRcrds as $r) {
            if ($r['Span'] > $lastSpanId) {
                $lastSpanId = $r['Span'];
            }
        }

        // Pre-filter list of visit spans
        foreach ($visitRcrds as $r) {

            $vRs = new VisitRs();
            EditRS::loadRow($r, $vRs);

            // Save first arrival
            if ($vRs->Span->getStoredVal() == 0) {
                $firstArrival = newDateWithTz($vRs->Arrival_Date->getStoredVal(), $uS->tz);
            }

            // Changing only the end of the visit, need only the last span
            if ($startDelta == 0 && $vRs->Span->getStoredVal() < $lastSpanId) {
                continue;
            }

            // Changing only the start of the visit - need only the first span
            if ($endDelta == 0 && $vRs->Span->getStoredVal() > 0) {
                continue;
            }

            $spans[$vRs->Span->getStoredVal()] = $vRs;

            // Collect the stays.
            $stayRS = new StaysRS();
            $stayRS->idVisit->setStoredVal($vRs->idVisit->getStoredVal());
            $stayRS->Visit_Span->setStoredVal($vRs->Span->getStoredVal());
            $rows = EditRS::select($dbh, $stayRS, array($stayRS->idVisit, $stayRS->Visit_Span));

            foreach ($rows as $st) {

                $stayRS = new StaysRS();
                EditRS::loadRow($st, $stayRS);
                $stays[$vRs->Span->getStoredVal()][] = $stayRS;

            }
        }

        // Check the case that user moved the end of a ribbon inbetween spans.
        if (isset($spans[$span]) === FALSE) {
            return 'Use only the begining span or the very last span to resize this visit.  ';
        }


        $visits = array();

        $tonight = new \DateTime();
        $tonight->add(new \DateInterval('P1D'));
        $tonight->setTime(0,0,0);

        $today = new \DateTime();
        $today->setTime(intval($uS->CheckOutTime), 0, 0);

        reset($spans);

        // change visit span dates
        foreach ($spans as $s => $vRs) {

            $spanStartDT = newDateWithTz($vRs->Span_Start->getStoredVal(), $uS->tz);

            if ($vRs->Status->getStoredVal() == VisitStatus::CheckedIn) {

                $spanEndDt = newDateWithTz($vRs->Expected_Departure->getStoredVal(), $uS->tz);
                $spanEndDt->setTime(intval($uS->CheckOutTime),0,0);

                if ($spanEndDt < $tonight) {
                    $spanEndDt = newDateWithTz('', $uS->tz);
                    $spanEndDt->setTime(intval($uS->CheckOutTime), 0, 0);
                }

            } else {
                // Checked out
                $spanEndDt = newDateWithTz($vRs->Span_End->getStoredVal(), $uS->tz);
            }


            // Cases: end and start both change identically => move; or end or start change => shrink/expand
            if ($endDelta < 0 || $startDelta < 0) {

                // Move back
                $spanEndDt->sub($endInterval);

                if ($vRs->Status->getStoredVal() == VisitStatus::CheckedIn && $spanEndDt < $tonight) {
                    $spanEndDt = new \DateTime();
                    $spanEndDt->setTime(intval($uS->CheckOutTime), 0, 0);
                }

                $spanStartDT->sub($startInterval);

                // Only change first arrival if this is the first span
                if ($s == 0) {
                    $firstArrival->sub($startInterval);
                }

            } else {

                // Spring ahead
                $spanEndDt->add($endInterval);
                $spanStartDT->add($startInterval);

                // Only change first arrival if this is the first span
                if ($s == 0) {
                    $firstArrival->add($startInterval);
                }

                // Checked-Out spans cannot move their end date beyond todays date.
                if ($vRs->Status->getStoredVal() != VisitStatus::CheckedIn) {
                    if ($spanEndDt >= $tonight) {
                        return 'Checked-Out visits cannot move their end date beyond todays date  Use Undo Checkout instead. ';
                    }
                }

                // Checked-in spans cannot move their start date beyond today's date.
                if ($vRs->Status->getStoredVal() == VisitStatus::CheckedIn) {
                    if ($spanStartDT >= $tonight) {
                        return 'Checked-in visits cannot move their start date beyond todays date. ';
                    }
                }
            }

            // Visit Still Good?
            if ($vRs->Status->getStoredVal() == VisitStatus::CheckedIn && ($spanEndDt < $spanStartDT || $spanEndDt < $today)) {
                return "The visit span End date cannot come before the Start date, or before today.  ";
            } else if ($vRs->Status->getStoredVal() != VisitStatus::CheckedIn && $spanEndDt <= $spanStartDT) {
                return "The visit span End date cannot come before or on the Start date.  ";
            }


            // Check room availability.
            $query = "select v.idResource from vregister v where v.Visit_Status <> :vstat and v.idVisit != :visit and v.idResource = :idr and
        DATE(v.Span_Start) < :endDate
        AND DATEDIFF(IFNULL(DATE(v.Span_End),
            CASE
                WHEN NOW() > DATE(v.Expected_Departure) THEN ADDDATE(NOW(), 1)
                ELSE DATE(v.Expected_Departure)
            END),
            v.Span_Start) != 0 and
        ifnull(DATE(v.Span_End), case when now() > DATE(v.Expected_Departure) then AddDate(now(), 1) else DATE(v.Expected_Departure) end) > :beginDate";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(
                ':beginDate'=>$spanStartDT->format('Y-m-d'),
                ':endDate'=>$spanEndDt->format('Y-m-d'),
                ':vstat'=> VisitStatus::Pending,
                ':visit'=>$vRs->idVisit->getStoredVal(),
                ':idr'=>$vRs->idResource->getStoredVal()));

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) > 0) {
                // not available
                return 'The Date range is not available';
            }

            $visits[$s]['rs'] = $vRs;
            $visits[$s]['start'] = $spanStartDT;
            $visits[$s]['end'] = $spanEndDt;

            $stayMsg = self::moveStaysDates($stays[$vRs->Span->getStoredVal()], $startDelta, $endDelta, $visits[$s]);

            if ($stayMsg != '') {
                return $stayMsg;
            }
        }

        // Check for pre-existing reservations
        $resvs = ReservationSvcs::getCurrentReservations($dbh, $visitRcrds[0]['idReservation'], $visitRcrds[0]['idPrimaryGuest'], 0, $firstArrival, $spanEndDt);

        if (count($resvs) > 0) {
            return "The Move overlaps another reservation or visit.  ";
        }

        $actualDepart = NULL;
        $estDepart = NULL;

        // If I got this far, all the resouorces are available.
        foreach ($visits as $v) {

            $visitRS = $v['rs'];

            $visitRS->Span_Start->setNewVal($v['start']->format('Y-m-d H:i:s'));
            $visitRS->Arrival_Date->setNewVal($firstArrival->format('Y-m-d H:i:s'));

            if ($visitRS->Status->getStoredVal() == VisitStatus::CheckedIn) {

                $visitRS->Expected_Departure->setNewVal($v['end']->format('Y-m-d H:i:s'));
                $estDepart = $v['end']->format('Y-m-d H:i:s');
                $actualDepart = NULL;

            } else {

                $visitRS->Span_End->setNewVal($v['end']->format('Y-m-d H:i:s'));
                $visitRS->Actual_Departure->setNewVal($v['end']->format('Y-m-d H:i:s'));
                $actualDepart = $v['end']->format('Y-m-d H:i:s');
            }

            $visitRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
            $visitRS->Updated_By->setNewVal($uname);

            $cnt = EditRS::update($dbh, $visitRS, array($visitRS->idVisit, $visitRS->Span));
            if ($cnt > 0) {
                $logText = VisitLog::getUpdateText($visitRS);
                VisitLog::logVisit($dbh, $idVisit, $visitRS->Span->getStoredVal(), $visitRS->idResource->getStoredVal(), $visitRS->idRegistration->getStoredVal(), $logText, "update", $uname);
            }

            self::saveStaysDates($dbh, $stays[$visitRS->Span->getStoredVal()], $visitRS->idRegistration->getStoredVal(), $uname);

        }


        // Update any invoice line dates
		Invoice::updateInvoiceLineDates($dbh, $idVisit, $startDelta);
		
        $lastVisit = array_pop($visits);
        $lastVisitRs = $lastVisit['rs'];

        // Reservation
        //$vRS = $visits[0]['rs'];
        $reserv = Reservation_1::instantiateFromIdReserv($dbh, $lastVisitRs->idReservation->getStoredVal());
        if ($reserv->isNew() === FALSE) {

            $reserv->setActualArrival($firstArrival->format('Y-m-d H:i:s'));
            $reserv->setExpectedArrival($firstArrival->format('Y-m-d H:i:s'));

            if (is_null($actualDepart) === FALSE && $actualDepart != '') {
                $reserv->setActualDeparture($actualDepart);
            }

            if (is_null($estDepart) === FALSE && $estDepart != '') {
                $reserv->setExpectedDeparture($estDepart);
            }
            $reserv->saveReservation($dbh, $lastVisitRs->idRegistration->getStoredVal(), $uname);
        }

        if (is_null($actualDepart) === FALSE && $actualDepart != '') {
            $lastDepart = new \DateTime($actualDepart);
        } else {
            $lastDepart = new \DateTime($estDepart);
        }

        $lastDepart->setTime(intval($uS->CheckOutTime), 0, 0);
        $firstArrival->setTime(intval($uS->CheckInTime), 0, 0);

        $reply = ReservationSvcs::moveResvAway($dbh, $firstArrival, $lastDepart, $lastVisitRs->idResource->getStoredVal(), $uname);

        if ($startDelta == 0) {
            $reply = 'Visit checkout date changed. ' . $reply;
        } else {
            $reply = 'Visit Moved. ' . $reply;
        }
        return $reply;
    }

    /**
     * Move the stays in a visit by delta days.
     *
     * @param array $stays
     * @param int $span
     * @param int $startDelta
     * @param int $endDelta
     * @param \DateTime $spanEndDT
     */
    protected static function moveStaysDates($stays, $startDelta, $endDelta, $visits) {

        $uS = Session::getInstance();

        $startInterval = new \DateInterval('P' . abs($startDelta) . 'D');
        $endInterval = new \DateInterval('P' . abs($endDelta) . 'D');

        $tonight = new \DateTime();
        $tonight->add(new \DateInterval('P1D'));
        $tonight->setTime(0,0,0);

        $today = new \DateTime();
        $today->setTime(intval($uS->CheckOutTime), 0, 0);

        $spanStartDT = \DateTimeImmutable::createFromMutable($visits['start']->setTime(10,0,0));
        $spanEndDT = \DateTimeImmutable::createFromMutable($visits['end']->setTime(10,0,0));


        foreach ($stays as $stayRS) {

            $checkInDT = new \DateTimeImmutable($stayRS->Checkin_Date->getStoredVal());
            $stayStartDT = new \DateTimeImmutable($stayRS->Span_Start_Date->getStoredVal());

            if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn) {

                $stayEndDt = new \DateTimeImmutable($stayRS->Expected_Co_Date->getStoredVal());

                if ($stayEndDt < $tonight) {
                    $stayEnd = new \DateTimeImmutable();
                    $stayEndDt = $stayEnd->setTime(intval($uS->CheckOutTime), 0, 0);

                }
            } else {
                $stayEndDt = new \DateTimeImmutable($stayRS->Span_End_Date->getStoredVal());
            }


            if ($endDelta < 0 && $startDelta < 0) {
                // Move the entire stay back

                $stayStartDT = $stayStartDT->sub($startInterval);
                $checkInDT = $checkInDT->sub($startInterval);
                $stayEndDt = $stayEndDt->sub($endInterval);

            } else if ($startDelta > 0 && $endDelta > 0) {
                // Move entire stay ahead

                $stayStartDT = $stayStartDT->add($startInterval);
                $checkInDT = $checkInDT->add($startInterval);
                $stayEndDt = $stayEndDt->add($endInterval);


            // Manipulate end of visit
            } else if ($startDelta == 0) {

                if ($endDelta < 0) {
                    // Shrink

                    // Checked in to late
                    if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn) {
                        // Is my Start Date after the span ending date?
                        if ($stayStartDT->setTime(10, 0, 0) > $spanEndDT) {
                            return "Cannot shrink the visit span this far - a stay checks in after the new span end date.  ";
                        }

                    } else {
                        // checked in too late and cannot tolerate a 0 day stay.
                        if ($stayStartDT->setTime(10, 0, 0) >= $spanEndDT) {
                            return "Cannot shrink the visit span this far - a stay starts after the new span end date.  ";
                        }

                        // Dont shrink these
                        if ($stayEndDt->setTime(10, 0, 0) <= $spanEndDT) {
                            continue;
                        }
                    }

                    // Shrink to span end date.
                    $stayEndDt = $spanEndDT->setTime(intval($uS->CheckOutTime),0,0);

                } else if ($endDelta > 0) {
                    // Expand

                    if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn) {

                        $stayEndDt = $stayEndDt->add($endInterval);

                    } else {

                        $oldSpanEndDT = new \DateTime($visits['rs']->Span_End->getStoredVal());
                        $oldSpanEndDT->setTime(10,0,0);

                        // If ends on old span end date and the span is checked out, expand the stay.
                        if ($oldSpanEndDT->diff($stayEndDt->setTime(10,0,0), TRUE)->days < 1 && $visits['rs']->Status->getStoredVal() != VisitStatus::CheckedIn) {
                            $stayEndDt = $stayEndDt->add($endInterval);
                        }
                    }
                }

            // Manipulate Start of visit
            } else if ($endDelta == 0) {

                if ($startDelta > 0) {
                    // Shrink

                    if ($stayEndDt->setTime(10, 0, 0) <= $spanStartDT) {
                        return "Cannot shrink the visit span this far - a stay checks out before or on the new span start date.  ";
                    }

                    if ($stayStartDT->setTime(10, 0, 0) >= $spanStartDT) {
                        // ignore these
                        continue;
                    }

                    // Shrink to span start date.
                    $stayStartDT = $spanStartDT->setTime(intval($uS->CheckInTime),0,0);

                } else if ($startDelta < 0) {
                    // Expand

                    $oldSpanStartDT = new \DateTime($visits['rs']->Span_Start->getStoredVal());
                    $oldSpanStartDT->setTIme(10,0,0);

                    // If ends on old span end date, expand the stay.
                    if ($oldSpanStartDT->diff($stayStartDT->setTime(10,0,0), TRUE)->days < 1) {
                        $stayStartDT = $stayStartDT->sub($startDelta);
                    }
                }
            }

            // Validity check
            $endDATE = new \DateTime($stayEndDt->format('Y-m-d 00:00:00'));
            $startDATE = new \DateTime($stayStartDT->format('Y-m-d 00:00:00'));
            if ($endDATE < $startDATE) {
                return "The stay End date comes before the Start date.  ";
            }

            $tday = new \DateTime($today->format('Y-m-d 00:00:00'));
            if ($stayRS->Status->getStoredVal() != VisitStatus::CheckedIn && $endDATE > $tday) {
                return "At least one guest, Id = " . $stayRS->idName->getStoredVal() . ", will have checked out into the future.  ";
            }

            $stayRS->Checkin_Date->setNewVal($checkInDT->format('Y-m-d H:i:s'));
            $stayRS->Span_Start_Date->setNewVal($stayStartDT->format('Y-m-d H:i:s'));

            if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn) {
                $stayRS->Expected_Co_Date->setNewVal($spanEndDT->format('Y-m-d H:i:s'));
            } else {
                $stayRS->Span_End_Date->setNewVal($stayEndDt->format('Y-m-d H:i:s'));
                $stayRS->Checkout_Date->setNewVal($stayEndDt->format('Y-m-d H:i:s'));
            }
        }

        return '';
    }

        /**
     * Save the stays in a visit by delta days.
     *
     * @param \PDO $dbh
     * @param array $stays
     * @param int $idRegistration
     * @param string $uname
     */
    public static function saveStaysDates(\PDO $dbh, $stays, $idRegistration, $uname) {


        foreach ($stays as $stayRS) {

            $stayRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
            $stayRS->Updated_By->setNewVal($uname);

            EditRS::update($dbh, $stayRS, array($stayRS->idStays));
            $logText = VisitLog::getUpdateText($stayRS);
            VisitLog::logStay($dbh, $stayRS->idVisit->getStoredVal(), $stayRS->Visit_Span->getStoredVal(), $stayRS->idRoom->getStoredVal(), $stayRS->idStays->getStoredVal(), $stayRS->idName->getStoredVal(), $idRegistration, $logText, "update", $uname);
        }
    }

}
?>