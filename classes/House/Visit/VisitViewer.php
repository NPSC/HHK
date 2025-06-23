<?php

namespace HHK\House\Visit;

use HHK\House\OperatingHours;
use HHK\Purchase\PriceModel\PriceGuestDay;
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
use HHK\Purchase\RoomRate;
use HHK\SysConst\GLTableNames;
use HHK\SysConst\ItemId;
use HHK\SysConst\ItemPriceCode;
use HHK\SysConst\VisitStatus;
use HHK\TableLog\VisitLog;
use HHK\Tables\EditRS;
use HHK\Tables\Visit\StaysRS;
use HHK\Tables\Visit\VisitRS;
use HHK\Purchase\RateChooser;
use HHK\SysConst\RoomRateCategories;

/**
 * visitViewer.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Visitview creates HTML markup with specified visit, stay and payment history.  Also saves some of the returned markup.
 *
 * @author Eric
 */
class VisitViewer {

    /**
     * Summary of createActiveMarkup
     * @param \PDO $dbh
     * @param mixed $vSpanListing
     * @param \HHK\Purchase\VisitCharges $visitCharge
     * @param bool $keyDepFlag
     * @param bool $visitFeeFlag
     * @param int $extendVisitDays
     * @param string $action
     * @param string $coDate
     * @param bool $showAdjust
     * @return string
     */
    public static function createActiveMarkup(\PDO $dbh, array $vSpanListing, VisitCharges $visitCharge, $keyDepFlag, $visitFeeFlag,
            $extendVisitDays, $action, $coDate, $showAdjust) {

        $uS = Session::getInstance();

        // Change Rooms doesn't need this section.
        if ($action == 'cr') {
            return '';
        }

        $table = new HTMLTable();

        // Get labels
        $labels = Labels::getLabels();


        // Notes
        $notesContainer = HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Notes', ['style' => 'font-weight:bold;'])
                ,['id' => 'visitNoteViewer', 'class' => 'hhk-panel']);


        // Key Deposit
        $kdRow = '';
        $kdHeader = '';

        if ($keyDepFlag && $visitCharge->getDepositCharged() > 0) {

            $keyDepAmount = $visitCharge->getKeyFeesPaid() + $visitCharge->getDepositPending();
            $depAmtText = ($keyDepAmount == 0 ? "" : number_format($keyDepAmount, 2) . ($vSpanListing['DepositPayType'] != '' ? '(' . $vSpanListing['DepositPayType'] . ')' : ''));

            // Deposit Disposition selector - only if
            if (($vSpanListing['Status'] == VisitStatus::CheckedIn || $vSpanListing['Status'] == VisitStatus::CheckedOut) && $keyDepAmount != 0) {

                $kdRow .= HTMLTable::makeTd(($keyDepAmount == 0 ? "" : "$")
                        .HTMLContainer::generateMarkup('span', $depAmtText, ['id' => 'kdPaid', 'style'=>'margin-right:7px;', 'data-amt'=>$keyDepAmount]));

                $kdHeader .= HTMLTable::makeTh($labels->getString('resourceBuilder', 'keyDepositLabel', 'Deposit'));

            } else {

                $kdRow = HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $depAmtText, ['id' => 'kdPaid', 'data-amt'=>$keyDepAmount]), ['style' => 'text-align:center;']);

                $kdHeader = HTMLTable::makeTh($labels->getString('resourceBuilder', 'keyDepositLabel', 'Deposit'));
            }

        }

        // Arrival text
        $tr = HTMLTable::makeTd(HTMLContainer::generateMarkup('span', date('M j, Y', strtotime($vSpanListing['Arrival_Date'])), ['id' => 'spanvArrDate']));
        $th = HTMLTable::makeTh('First Arrival');

        if($uS->noticetoCheckout){
            $noticeToCheckout = ($vSpanListing['Notice_to_Checkout'] ? date('M j, Y', strtotime($vSpanListing['Notice_to_Checkout'])) : '');
            $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup($noticeToCheckout, array('class'=>'ckdate', 'id'=>'noticeToCheckout','name'=>'noticeToCheckout', 'size'=>'12')));
            $th .= HTMLTable::makeTh(Labels::getString("Visit", "noticeToCheckout", "Notice to Checkout"));
        }

        $departureText = "";
        $days = "";

        switch ($vSpanListing['Status']) {
            case VisitStatus::CheckedIn:

                $depHeader = "Expected End";
                $daysHeader = "Expected Nights";

                if ($action == 'ref') {
                    // deal with changed checkout date
                    $deptDT = new \DateTime($coDate);
                    $deptDT->setTime(0, 0, 0);
                    $arrivalDT = new \DateTime($vSpanListing['Arrival_Date']);
                    $arrivalDT->setTime(0, 0, 0);
                    $days = $deptDT->diff($arrivalDT, TRUE)->days;
                    $departureText = $deptDT->format('M, j, Y');

                } else {

                    $departureText = date('M j, Y', strtotime($vSpanListing['Expected_Departure']));
                    $days = $vSpanListing['Expected_Nights'];
                }

                break;

            case VisitStatus::NewSpan:

                $depHeader = "Room Changed";
                $daysHeader = "Span Nights";
                $departureText = date('M j, Y', strtotime($vSpanListing['Span_End']));
                $days = $vSpanListing['Actual_Span_Nights'];
                break;

            case VisitStatus::ChangeRate:

                $depHeader = "Changed Room Rate";
                $daysHeader = "Span Nights";
                $departureText = date('M j, Y', strtotime($vSpanListing['Span_End']));
                $days = $vSpanListing['Actual_Span_Nights'];
                break;

            case VisitStatus::CheckedOut:
                $depHeader = "Actual End";
                $daysHeader = "Actual Nights";
                $departureText = date('M j, Y', strtotime($vSpanListing['Actual_Departure']));
                $days = $vSpanListing['Actual_Nights'];

                break;

            case VisitStatus::Reserved:
                $depHeader = "Expected End";
                $daysHeader = "Expected Nights";

                if ($action == 'ref') {
                    // deal with changed checkout date
                    $deptDT = new \DateTime($coDate);
                    $deptDT->setTime(0, 0, 0);
                    $arrivalDT = new \DateTime($vSpanListing['Arrival_Date']);
                    $arrivalDT->setTime(0, 0, 0);
                    $days = $deptDT->diff($arrivalDT, TRUE)->days;
                    $departureText = $deptDT->format('M, j, Y');

                } else {

                    $departureText = date('M j, Y', strtotime($vSpanListing['Expected_Departure']));
                    $days = $vSpanListing['Expected_Nights'];
                }

                break;


            default:
                return HTMLContainer::generateMarkup('h2', "System Error:  Incomplete Visit Record.  Contact your support people.", ["style" => 'color:red;']);

        }

        // Departure date and days
        if ($action != 'co') {
            // Departure
            $tr .= HTMLTable::makeTd($departureText);
            // Days
            $tr .= HTMLTable::makeTd($days, ['style' => 'text-align:center;']);

            $th .= HTMLTable::makeTh($depHeader) . HTMLTable::makeTh($daysHeader);

        }

        //Room Rate
        $rateTitle = RoomRate::getRateDescription($dbh, $vSpanListing['idRoom_Rate'], $vSpanListing['Rate_Category'], $vSpanListing['Expected_Rate']);
        if ($vSpanListing['Rate_Category'] == RoomRateCategories::Fixed_Rate_Category) {
            $rateTitle .= ': $' . number_format($vSpanListing['Pledged_Rate'], 2);
        }

        $tr .= HTMLTable::makeTd($rateTitle);
        $th .= HTMLTable::makeTh('Room Rate');

        // Visit fee
        if ($visitFeeFlag) {

            if ($vSpanListing['Status'] == VisitStatus::Reserved) {
                $vFeeSelector = HTMLContainer::generateMarkup('span', '$' . number_format($visitCharge->getVisitFeeCharged(), 2));
            } else {

                $rateChooser = new RateChooser($dbh);

                $vFeeSelector = $rateChooser->makeVisitFeeSelector(
                    $rateChooser->makeVisitFeeArray($dbh, $visitCharge->getVisitFeeCharged()), $visitCharge->getVisitFeeCharged(), 'hhk-feeskeys');
            }

            $th .= HTMLTable::makeTh($labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee'));
            $tr .= HTMLTable::makeTd($vFeeSelector);
        }


        // Key Deposit
        if ($action != 'cf') {
            $tr .= $kdRow;
            $th .= $kdHeader;
        }

        // hospital
        $hname = $vSpanListing['Hospital'];

        if ($vSpanListing['Association'] != '' && $vSpanListing['Association'] != '(None)') {
            $hname = $vSpanListing['Association'] . ' / ' . $hname;
        }

        $hospitalButton = '';
        if ($vSpanListing['idHospital'] > 0) {

            $hospitalButton = HTMLInput::generateMarkup($hname
        	,
                [
                    'type' => 'button',
                    'class' => 'hhk-hospitalstay ui-corner-all hhk-hospTitleBtn ui-button ignrSave',
                    'data-idhs' => $vSpanListing['idHospital_stay'],
                    'style' => ($uS->guestLookups['Hospitals'][$vSpanListing['idHospital']][5] ? "color:" . $uS->guestLookups['Hospitals'][$vSpanListing['idHospital']][5] . "; border: 1px solid black;" : '') . ($uS->guestLookups['Hospitals'][$vSpanListing['idHospital']][4] ? "background:" . $uS->guestLookups['Hospitals'][$vSpanListing['idHospital']][4] . ";" : '') . ";",
                    'title' => $labels->getString('Hospital', 'hospital', 'Hospital') . ' Details'
                ]
            );
        }else if($vSpanListing['idHospital_stay'] > 0 && $vSpanListing['idHospital'] == 0){
            $hospitalButton = HTMLInput::generateMarkup("No Hospital Assigned"
        	,
                [
                    'type' => 'button',
                    'class' => 'hhk-hospitalstay ui-corner-all hhk-hospTitleBtn ui-button ignrSave',
                    'data-idhs' => $vSpanListing['idHospital_stay'],
                    'style' => "color:#fff; border: 1px solid #2C3E50; background: #5c9ccc;",
                    'title' => $labels->getString('Hospital', 'hospital', 'Hospital') . ' Details'
                ]
            );
        }

        $th .= HTMLTable::makeTh($labels->getString('hospital', 'hospital', 'Hospital'));
        $tr .= HTMLTable::makeTd($hospitalButton, array('id'=>'hhk-HospitalTitle'));

        // Patient Name
        if ($vSpanListing['Patient_Name'] != '') {
            $th .= HTMLTable::makeTh($labels->getString('MemberType', 'patient', 'Patient'));
            $tr .= HTMLTable::makeTd($vSpanListing['Patient_Name']);
        }

        if(isset($vSpanListing["Checked_In_By"])){
            $th .= HTMLTable::makeTh("Checked In By");
            $tr .= HTMLTable::makeTd($vSpanListing['Checked_In_By']);
        }


        // add completed rows to table
        $table->addBodyTr($tr);
        $table->addHeaderTr($th);
        $tblMarkup = $table->generateMarkup(['id' => 'tblActiveVisit', 'style' => 'width:100%; min-width: max-content;']);

        $weekendRowMkup = "";

        // Change Rate markup
        if ($uS->RoomPriceModel != ItemPriceCode::None && $action != 'ref' && $vSpanListing['Status'] != VisitStatus::Reserved) {

            $rateChooser = new RateChooser($dbh);
            $vRs = new VisitRs();
            EditRS::loadRow($vSpanListing, $vRs);
            $rateTbl = $rateChooser->createChangeRateMarkup($dbh, $vRs);

            $weekendRowMkup .= $rateTbl->generateMarkup();
        }

        // Weekender button
        if ($vSpanListing['Status'] == VisitStatus::CheckedIn && $extendVisitDays > 0 && $action != 'ref') {
            $etbl = new HTMLTable();

            $olStmt = $dbh->query("select `On_Leave`, `Span_Start_Date`  from `stays` where `stays`.`idVisit` = " . $vSpanListing['idVisit'] . " and `stays`.`Status` = 'a' LIMIT 0, 1 ");
            $olRows = $olStmt->fetchAll(\PDO::FETCH_NUM);

            if (isset($olRows[0][0]) && $olRows[0][0] > 0) {
                // On leave
                $leaveStartDT = new \DateTimeImmutable($olRows[0][1]);
                $leaveEndDT = $leaveStartDT->add(new \DateInterval('P'. $olRows[0][0] . 'D'));

                $etbl->addBodyTr(HTMLTable::makeTh(
                        HTMLContainer::generateMarkup('label', 'On Leave Until: ' . $leaveEndDT->format('M j, Y'), ['for' => 'leaveRetCb', 'style' => 'margin-right:.5em;'])
                        .HTMLInput::generateMarkup('', ['name' => 'leaveRetCb', 'type' => 'checkbox', 'class' => 'hhk-feeskeys hhk-extVisitSw', 'title' => 'Return from leave or extend leave']))

                .HTMLTable::makeTd(HTMLInput::generateMarkup('ext', array('name'=>'rbOlpicker', 'id'=>'rbOlpicker-ext', 'type'=>'radio', 'class' => 'hhk-feeskeys'))
                    . HTMLContainer::generateMarkup('label', 'Extend Until:', ['style' => 'margin-left:.3em;', 'for' => 'rbOlpicker-ext'])
                    . HTMLInput::generateMarkup('', ['name' => 'extendDate', 'style' => 'margin-left:.3em;', 'class' => 'hhk-feeskeys ckdateFut'])
                    , ['style' => 'display:none;', 'class' => 'hhk-extendVisit'])

                .HTMLTable::makeTd(HTMLInput::generateMarkup('rtDate', ['name' => 'rbOlpicker', 'id' => 'rbOlpicker-rtDate', 'type' => 'radio', 'checked' => 'checked', 'class' => 'hhk-feeskeys'])
                    . HTMLContainer::generateMarkup('label', 'Returned:', ['style' => 'margin-left:.3em;', 'for' => 'rbOlpicker-rtDate'])
                    . HTMLInput::generateMarkup(date('M j, Y'), ['id' => 'txtWRetDate', 'style' => 'margin-left:.3em;', 'class' => 'ckdate hhk-feeskeys'])
                    , ['style' => 'display:none;', 'class' => 'hhk-extendVisit']));

            } else {
                // Not on leave
                $ths = HTMLTable::makeTh(
                        HTMLContainer::generateMarkup('label', 'Weekend Leave', ['for' => 'extendCb', 'style' => 'margin-right:.5em;'])
                        .HTMLInput::generateMarkup('', ['name' => 'extendCb', 'type' => 'checkbox', 'class' => 'hhk-feeskeys hhk-extVisitSw', 'title' => 'Start Leave']))
                .HTMLTable::makeTd(
                        HTMLContainer::generateMarkup('span', 'For:', ['style' => 'margin-left:.3em;'])
                    . HTMLInput::generateMarkup($extendVisitDays, ['name' => 'extendDays', 'size' => '2', 'style' => 'margin-left:.3em;', 'class' => 'hhk-feeskeys'])
                    . HTMLContainer::generateMarkup('span', 'nights', ['style' => 'margin-left:.3em;'])
                    , ['style' => 'display:none;', 'class' => 'hhk-extendVisit'])
                .HTMLTable::makeTd(
                        HTMLContainer::generateMarkup('span', 'Starting:', ['style' => 'margin-left:.3em;'])
                    . HTMLInput::generateMarkup(date('M j, Y'), ['name' => 'txtWStart', 'readonly' => 'readonly', 'class' => 'hhk-feeskeys ckdate', 'style' => 'margin-left:.3em;'])
                    , ['style' => 'display:none;', 'class' => 'hhk-extendVisit']);

                if ($uS->RoomPriceModel != ItemPriceCode::None) {
                    $ths .= HTMLTable::makeTd(
                        HTMLContainer::generateMarkup('label', 'No Room Charge', ['for' => 'noChargeCb', 'style' => 'margin-right:.5em;'])
                        .HTMLInput::generateMarkup('', ['name' => 'noChargeCb', 'type' => 'checkbox', 'class' => 'hhk-feeskeys'])
                        ,['style' => 'display:none;', 'class' => 'hhk-extendVisit']);
                }

                $etbl->addBodyTr($ths);
            }

            $weekendRowMkup .= $etbl->generateMarkup(array('style'=>'margin-left:.5em;'));
        }

        if($weekendRowMkup != ""){
            $tblMarkup .= HTMLContainer::generateMarkup("div", $weekendRowMkup, array("class"=>'hhk-flex my-2'));
        }

        //Ribbon note
        $ribbonTbl = new HTMLTable();
        $ribbonTbl->addHeaderTr(HTMLTable::makeTh("Ribbon Note", ['title' => 'This note shows on the visit ribbon on the calemdar page']) . HTMLTable::makeTd(HTMLInput::generateMarkup($vSpanListing['Notes'], ['name' => 'txtRibbonNote', 'size' => '25', 'maxlength' => '20', 'title' => 'Maximun 20 characters'])));
        $ribbonTblMarkup = $ribbonTbl->generateMarkup(["class" => "my-2"]);

        $tblMarkup .= $ribbonTblMarkup . $notesContainer;

        $visitBoxLabel = 'Visit';

        // Adjust button
        if ($showAdjust && $action != 'ref') {

            $visitBoxLabel .= HTMLInput::generateMarkup('Adjust Fees...', ['name' => 'paymentAdjust', 'type' => 'button', 'style' => 'font-size:.8em;', 'title' => 'Create one-time additional charges or discounts.', 'class' => 'ml-3']);
        }

        if ($vSpanListing['Status'] == VisitStatus::CheckedIn && $action != 'ref' && $uS->TrackAuto) {

            $visitBoxLabel .= HTMLInput::generateMarkup('Edit Vehicles...', ['name' => 'vehAdjust', 'type' => 'button', 'style' => 'font-size:.8em;', 'title' => 'Edit vehicles for this visit.', 'class' => 'ui-button ui-corner-all ui-widget ml-2', 'role' => 'button']);
        }


        // Make undo checkout button.
        if ($vSpanListing['Status'] == VisitStatus::CheckedOut) {

            $spnMkup = HTMLContainer::generateMarkup('label', ' Undo Checkout', ['for' => 'undoCkout'])
                    . HTMLInput::generateMarkup('', ['id' => 'undoCkout', 'type' => 'checkbox', 'class' => 'hhk-feeskeys', 'style' => 'margin-right:.3em;margin-left:0.3em;'])
                    . HTMLContainer::generateMarkup('span', 'New Expected Departure Date: ', ['style' => 'margin-right: 0.3em; margin-left:0.3em;'])
                    . HTMLInput::generateMarkup('', ['id' => 'txtUndoDate', 'class' => 'ckdateFut hhk-feeskeys']);

            $visitBoxLabel .= HTMLContainer::generateMarkup('span', $spnMkup, ['style' => 'margin:0 1em;', 'title' => 'Undo Checkout']);

        } else if ($vSpanListing['Status'] == VisitStatus::NewSpan) {

            // Make undo Room Change button
            $spnMkup = HTMLContainer::generateMarkup('label', '- Undo Room Change', ['for' => 'undoRmChg'])
                    . HTMLInput::generateMarkup('', ['id' => 'undoRmChg', 'type' => 'checkbox', 'class' => 'hhk-feeskeys', 'style' => 'margin-right:.3em;margin-left:0.3em;']);

            $visitBoxLabel .= HTMLContainer::generateMarkup('span', $spnMkup, ['style' => 'margin:0.1em;', 'title' => 'Undo Room Change']);

        } else if ($vSpanListing['Status'] == VisitStatus::CheckedIn && $vSpanListing['Has_Future_Change'] > 0) {

            // Make undo Future Room Change button
            $spnMkup = HTMLContainer::generateMarkup('label', '- Delete Future Room Change', ['for' => 'delFutRmChg'])
                . HTMLInput::generateMarkup('Delete Future Room Change', ['id' => 'delFutRmChg', 'name' => 'delFutRmChg','type' => 'checkbox', 'class' => 'hhk-feeskeys', 'style' => 'margin-right:.3em;margin-left:0.3em;']);

            $visitBoxLabel .= HTMLContainer::generateMarkup('span', $spnMkup, ['style' => 'margin:0.1em;', 'title' => 'Delete Future Room Change']);
        }



        return
            HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', $visitBoxLabel, ['style' => 'font-weight:bold;'])
                   . HTMLContainer::generateMarkup("div", $tblMarkup, ["style" => "overflow:auto;"])
                ,
                ['class' => 'hhk-panel', 'style' => 'margin-bottom:10px;']);

    }

    /**
     * Summary of createStaysMarkup
     * @param \PDO $dbh
     * @param int $idResv
     * @param int $idVisit
     * @param int $span
     * @param bool $idFutureSpan
     * @param int $idPrimaryGuest
     * @param int $idGuest
     * @param Labels $labels
     * @param string $action
     * @param array $coDates
     * @return string
     */
    public static function createStaysMarkup(\PDO $dbh, $idResv, $idVisit, $span, $isFutureSpan, $idPrimaryGuest, $idGuest, $labels, $action = '', $coDates = []) {

        $includeActionHdr = FALSE;  // Checkout-All button.
        $useRemoveHdr = FALSE;      // Enable the "Remove" column in stays table.
        $useAddGuestButton = TRUE;
        $ckOutTitle = '';
        $sTable = new HTMLTable();  // Table to collect each stays markup row.
        $staysDtable = [];     // results of vstays_listing.
        $ckinRows = [];      // The db doesn't sort `status`.
        $staysDtable_rows = 0;      // Number of rows in the staysDtable.
        $hdrPgRb = '';      // Auto generated. Primary guest column header.  blank = no primary guest column.
        $chkInTitle = 'Checked In';
        $visitStatus = '';
        $guestAddButton = '';
        $sendMsgButton = '';
        $prevSpanStatus = '';
        $idV = intval($idVisit, 10);
        $idS = intval($span, 10);
        $activeSpan = $idS;

        if ($isFutureSpan) {
            $activeSpan = $idS - 1;
        }


        $titleMkup = HTMLContainer::generateMarkup('span', $labels->getString('MemberType', 'visitor', 'Guest') . 's', ['style' => 'float:left;']);

        if ($idV > 0 && $activeSpan > -1) {
            // load stays for this specific visit-span
            $stmt = $dbh->query("select * from `vstays_listing` where `idVisit` = $idV and `Visit_Span` = $activeSpan order by `Span_Start_Date` desc;");
            $staysDtable = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $staysDtable_rows = count($staysDtable);

            if ($staysDtable_rows < 1) {

                // Return an error in-band
                return HTMLContainer::generateMarkup(
                    'fieldset',
                    HTMLContainer::generateMarkup('legend', $titleMkup , ['style' => 'font-weight:bold;'])
                    .HTMLContainer::generateMarkup('div', 'Error finding the visit, visit Id = ' . $idV . ', Span = ' . $activeSpan)
                    ,
                    ['class' => 'hhk-panel', 'style' => 'margin-bottom:10px;']
                );
            }

            $visitStatus = $staysDtable[0]['Visit_Status'];
        }

        // Get previous span status
        if ($idV > 0 && $idS > 0 && !$isFutureSpan) {
            $idS--;
            $stmt = $dbh->query("select `Status` from `visit` where `idVisit` = $idV and `Span` = $idS;");
            $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
            if(count($rows) > 0) {
                if ($rows[0][0] == VisitStatus::ChangeRate) {
                    $prevSpanStatus = '($)';
                } else if ($rows[0][0] == VisitStatus::NewSpan) {
                    $prevSpanStatus = '(rm)';
                }
            }
        }

        // cherry pick the checked in stays.
        // Add them to the stays table.
        if ($visitStatus == VisitStatus::CheckedIn) {

            $ckOutTitle = "Exp'd Check Out";

            foreach ($staysDtable as $k => $r) {

                if ($r['Status'] == VisitStatus::CheckedIn) {

                    $r['Status_Title'] .= HTMLContainer::generateMarkup('span', $prevSpanStatus, ['style' => 'font-size:.8em;margin-left:10px;']);

                    $bodyTr = self::createStayRowMarkup($r, $staysDtable_rows, $action, $isFutureSpan, $idGuest, $coDates, $idPrimaryGuest, $useRemoveHdr, $includeActionHdr, $hdrPgRb);
                    $sTable->addBody($bodyTr);
                    $ckinRows[$k] = 'y';

                    if ($r['On_Leave'] > 0) {
                        $useAddGuestButton = FALSE;
                    }
                }
            }

        } else {

            switch ($visitStatus) {
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
        }

        // Add the rest to the stays table, skipping the checked-ins.
        foreach ($staysDtable as $j => $r) {

            if (!isset($ckinRows[$j])) {

                $bodyTr = self::createStayRowMarkup($r, $staysDtable_rows, $action, $isFutureSpan, $idGuest, $coDates, $idPrimaryGuest, $useRemoveHdr, $includeActionHdr, $hdrPgRb);
                $sTable->addBody($bodyTr);
            }
        }

        // Table header
        $th = ($hdrPgRb == '' ? '' : $hdrPgRb)
            . HTMLTable::makeTh('Name')
            . HTMLTable::makeTh($labels->getString('MemberType', 'patient', 'Patient') . ' Relation')
            . HTMLTable::makeTh('Status')
            . HTMLTable::makeTh('Room')
            . HTMLTable::makeTh($chkInTitle);

        // 'Add Guest' button
        if ($action == '') {

            $th .= HTMLTable::makeTh($ckOutTitle) . HTMLTable::makeTh('Nights');

            if ($useAddGuestButton && !$isFutureSpan) {
                // Make add guest button
                $guestAddButton = HTMLInput::generateMarkup('Add ' . $labels->getString('MemberType', 'visitor', 'Guest') . '...', ['id' => 'btnAddGuest', 'type' => 'button', 'style' => 'margin-left:1.3em; font-size:.8em;', 'data-rid' => $idResv, 'data-vstatus' => $visitStatus, 'data-vid' => $idVisit, 'data-span' => $span, 'title' => 'Add another guest to this visit.']);
            }

            $uS = Session::getInstance();
            if($uS->smsProvider){
                //Make Send Message button
                $sendMsgButton = HTMLContainer::generateMarkup('button', 'Text ' . $labels->getString('MemberType', 'visitor', 'Guest') . 's...', ["role" => "button", "class" => "viewMsgs ui-button ui-corner-all", "style" => "font-size: 0.8em; margin-left: 0.5em;"]);
            }
        }

        // 'Checkout All' button
        if ($includeActionHdr && !$isFutureSpan) {

            $td = 'Check Out';

            // Checkout ALL button.
            if ($staysDtable_rows > 1) {

                $td .= HTMLInput::generateMarkup('All', ['id' => 'cbCoAll', 'type' => 'button', 'style' => 'margin-right:.5em;margin-left:.5em;']);
            }

            $th .= HTMLTable::makeTh($td);
        }

        // add 'Remove' checkbox
        if ($useRemoveHdr && !$isFutureSpan) {
            $th .= HTMLTable::makeTh('Remove');
        }

        $sTable->addHeaderTr($th);

        $dvTable = HTMLContainer::generateMarkup('div', $sTable->generateMarkup(['id' => 'tblStays', 'style' => 'width: 99%; min-width: max-content;']), ['style' => 'max-height:150px;overflow:auto']);


        return HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', $titleMkup . $guestAddButton . $sendMsgButton , ['style' => 'font-weight:bold;'])
                . $dvTable
                , ['class' => 'hhk-panel', 'style' => 'margin-bottom:10px;']);

    }

    /**
     * Summary of createStayRowMarkup
     * @param array $r
     * @param int $numberRows
     * @param string $action
     * @param bool $idFutureSpan
     * @param int $idGuest
     * @param array $coDates
     * @param int $idPrimaryGuest
     * @param bool $useRemoveHdr
     * @param bool $includeActionHdr
     * @param string $hdrPgRb
     * @return string
     */
    protected static function createStayRowMarkup($r, $numberRows, $action, $isFutureSpan, $idGuest, $coDates, &$idPrimaryGuest, &$useRemoveHdr, &$includeActionHdr, &$hdrPgRb) {

        $uS = Session::getInstance();
        $days = 0;

        $actionButton = "";
        $ckOutDate = "";
        $name = $r['Name_First'] . ' ' . $r['Name_Last'];

        if (($action == 'so' || $action == 'ref') && $r['Status'] != VisitStatus::CheckedIn) {
            return '';
        }

        // Preselect checkout box
        if ($action == 'co' && $r['idName'] == $idGuest) {
            // Mark check-out checkbox
            $r['Cked'] = "y";
        }

        // Prepare checkbox attributes.
        $cbAttr = [
            'id' => 'stayActionCb_' . $r['idName'],
            'name' => '[stayActionCb][' . $r['idName'] . ']',
            'data-nm' => $name,
            'type' => 'checkbox',
            'class' => 'hhk-ckoutCB',
            'style' => 'margin-right:.3em;',
        ];

        if (isset($r['Cked']) || $action == 'ref') {
            $cbAttr['checked'] = 'checked';
        }

        // Primary guest selector.
        if ($r["Visit_Status"] == VisitStatus::CheckedIn && $numberRows > 1 && !$isFutureSpan) {

            $pgAttrs = ['name' => 'rbPriGuest', 'type' => 'radio', 'class' => 'hhk-feeskeys', 'title' => 'Make the ' . Labels::getString('MemberType', 'primaryGuest', 'Primary Guest')];
            $pgRb = '';

            // Only set the first instance of primary guest.
            if ($r['idName'] == $idPrimaryGuest ) {
                $pgAttrs['checked'] = 'checked';
                $idPrimaryGuest = 0;
            }

            $pgRb = HTMLInput::generateMarkup($r['idName'], $pgAttrs);
            $hdrPgRb = HTMLTable::makeTh('Pri', ['title' => Labels::getString('MemberType', 'primaryGuest', 'Primary Guest')]);
        }

        $stDayDT = new \DateTime($r['Span_Start_Date']);
        $stDayDT->setTime(0, 0, 0);

        // Action button depends on status
        if ($r["Visit_Status"] == VisitStatus::CheckedIn) {

            if ($r['Status'] == VisitStatus::CheckedIn) {

                if ($action == 'ref' && isset($coDates[$r['idName']])) {
                    $edDay = new \DateTime($coDates[$r['idName']]);
                } else {
                    $edDay = new \DateTime(date('Y-m-d'));
                }

                $edDay->setTime(0, 0, 0);
                $days = $edDay->diff($stDayDT, TRUE)->days;

                $getCkOutDate = HTMLInput::generateMarkup(
                    $edDay->format('M j, Y')
                    , ['id' => 'stayCkOutDate_' . $r['idName'],
                        'name' =>'[stayCkOutDate][' . $r['idName'] . ']',
                        'class' => 'ckdate hhk-ckoutDate',
                        'readonly'=>'readonly',
                        'data-ckin' => date('M j, Y', strtotime($r['Span_Start_Date'])),
                        'data-gid'=>$r['idName']
                    ]
                );

                if ($uS->CoTod) {
                    $getCkOutDate .= HTMLInput::generateMarkup(date('H'), ['id' => 'stayCkOutHour_' . $r['idName'], 'name' => '[stayCkOutHour][' . $r['idName'] . ']', 'size' => '3']);
                }

                if (!$isFutureSpan) {
                    $ckOutDate = HTMLInput::generateMarkup(date('M j, Y', strtotime($r['Expected_Co_Date'])), ['id' => 'stayExpCkOut_' . $r['idName'], 'name' => '[stayExpCkOut][' . $r['idName'] . ']', 'class' => 'ckdateFut hhk-expckout', 'readonly' => 'readonly']);
                    $actionButton = HTMLInput::generateMarkup('', $cbAttr) . $getCkOutDate;
                } else {
                    $ckOutDate = HTMLContainer::generateMarkup('span', $r['Expected_Co_Date'] != '' ? date('M j, Y', strtotime($r['Expected_Co_Date'])) : '');
                    $actionButton = '';
                }

                //
                if ($action == 'co' || $action == 'ref' || $action == '') {
                    $includeActionHdr = TRUE;
                }

            } else {

                $edDay = new \DateTime(is_null($r['Span_End_Date']) ? '' : $r['Span_End_Date']);
                $edDay->setTime(0, 0, 0);

                $days = $edDay->diff($stDayDT, TRUE)->days;

                // Don't show 0-day checked - out stays.
                if ($days == 0 && !$uS->ShowZeroDayStays) {
                    return '';
                }

                $ckOutDate = HTMLContainer::generateMarkup('span', $r['Span_End_Date'] != '' ? date('M j, Y H:i', strtotime($r['Span_End_Date'])) : '');

            }

        } else {

            $edDay = new \DateTime(is_null($r['Span_End_Date']) ? '' : $r['Span_End_Date']);
            $edDay->setTime(0, 0, 0);

            $days = $edDay->diff($stDayDT, TRUE)->days;

            // Don't show 0-day checked - out stays.
            if ($days == 0 && !$uS->ShowZeroDayStays) {
                return '';
            }

            $ckOutDate = HTMLContainer::generateMarkup('span', $r['Span_End_Date'] != '' ? date('M j, Y H:i', strtotime($r['Span_End_Date'])) : '');
        }

        // guest Name
        if ($idGuest == $r['idName']) {
            $idMarkup = HTMLContainer::generateMarkup('a', $name, ['href' => 'GuestEdit.php?id=' . $r['idName'] . '&psg=' . $r['idPsg'], 'class' => 'ui-state-highlight']);
        } else {
            $idMarkup = HTMLContainer::generateMarkup('a', $name, ['href' => 'GuestEdit.php?id=' . $r['idName'] . '&psg=' . $r['idPsg']]);
        }

        //if SMS enabled
        //@TODO check if sms is enabled and mobile number exists
        $idMarkup = HTMLContainer::generateMarkup('div', $idMarkup, ["class" => "hhk-flex", "style" => "justify-content: space-between;"]);

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

        // Status - "On LEAVE" only allowed for checked-in visits.
        . HTMLTable::makeTd($r['On_Leave'] > 0 && $r["Visit_Status"] == VisitStatus::CheckedIn ? 'On Leave' : $r['Status_Title'])

        // room
        . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $r["Room"]))

        // CheckIn date
            . HTMLTable::makeTd(
                $isFutureSpan ? HTMLContainer::generateMarkup('span', date('M j, Y H:i', strtotime($r['Span_Start_Date']))) :
                HTMLInput::generateMarkup(date('M j, Y', strtotime($r['Span_Start_Date'])), ['id' => 'stayCkInDate_' . $r['idStays'], 'class' => 'hhk-stayckin ckdate', 'readonly' => 'raadonly'])
                . ' ' . date('H:i', strtotime($r['Span_Start_Date']))
            );


        if ($action == '') {
            // Check Out/Expected check out date
            $tr .=  HTMLTable::makeTd($ckOutDate)

            // Days
            . HTMLTable::makeTd($days);
        }


        // Action button column
        $tr .=  ($includeActionHdr === TRUE ? HTMLTable::makeTd($actionButton) : "");

        // Remove button - only if more than one guest is staying
        if ($action == ''
            && $numberRows > 1
            && $r['On_Leave'] == 0
            && $r['Status'] != VisitStatus::CheckedIn
            && $r['idName'] != $idPrimaryGuest
            //                    && $r['Visit_Span'] == 0
        //                    && ($r["Visit_Status"] == VisitStatus::CheckedIn || $r["Visit_Status"] == VisitStatus::CheckedOut)
            ) {

                $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup('', [
                'id' => 'removeCb_' . $r['idStays'],
                'name' => '[removeCb][' . $r['idStays'] . ']',
                'data-nm' => $name,
                'type' => 'checkbox',
                'class' => 'hhk-removeCB',
            ]), ['style' => 'text-align:center;']);

                $useRemoveHdr = TRUE;
        }

        if ($r['Status'] == VisitStatus::CheckedIn) {
            $bodyTr = HTMLContainer::generateMarkup('tr', $tr, []);
        } else {
            $bodyTr = HTMLContainer::generateMarkup('tr', $tr, ['style' => 'background-color:#f2f2f2;']);
        }

        return $bodyTr;

    }

    /**
     * Summary of createPaymentMarkup
     * @param \PDO $dbh
     * @param array $r
     * @param \HHK\Purchase\VisitCharges $visitCharge
     * @param int $idGuest
     * @param string $action
     * @return string
     */
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

            $pm = $visitCharge->getPriceModel();

            if (!is_null($pm) && $pm->hasPerGuestCharge) {
                $showGuestNights = TRUE;
            }
        }

        // Any taxes
        $vat = new ValueAddedTax($dbh);

        $currFees = '';
        $paymentMarkup = '';


        if ($includeKeyDep || $includeVisitFee || $includeAddnlCharge || $showRoomFees) {

            // Current fees block
            $currFees = HTMLContainer::generateMarkup('fieldset',
                    HTMLContainer::generateMarkup('legend', ($r['Status'] == VisitStatus::CheckedIn ? 'To-Date Fees & Balance Due' : 'Final Fees & Balance Due'), ['style'=>'font-weight:bold;'])
                    . HTMLContainer::generateMarkup('div', self::createCurrentFees($r['Status'], $visitCharge, $vat, $includeVisitFee, $showRoomFees, $showGuestNights), ['id'=>'divCurrFees'])
                        , ['class'=>'hhk-panel mr-2','style'=>'min-width: max-content;']);

            // Enable Final payment?
            $enableFinalPayment = FALSE;
            if ($action != 'pf' && ($r['Status'] == VisitStatus::CheckedIn || $r['Status'] == VisitStatus::CheckedOut)) {
                $enableFinalPayment = TRUE;
            }

            $paymentGateway = AbstractPaymentGateway::factory($dbh, $uS->PaymentGateway, AbstractPaymentGateway::getCreditGatewayNames($dbh, $visitCharge->getIdVisit(), $visitCharge->getSpan(), $r['idRegistration']));

            // New Payments
            $paymentMarkup = PaymentChooser::createMarkup(
                    $dbh,
                    $idGuest,
                    0,
                    $r['idRegistration'],
                    $visitCharge,
                    $paymentGateway,
                    $uS->DefaultPayType,
                    $enableFinalPayment,
                    $r['Pref_Token_Id']
                    );

        }


        return $currFees . $paymentMarkup;
    }

    /**
     * Summary of createCurrentFees
     * @param string $visitStatus
     * @param \HHK\Purchase\VisitCharges $visitCharge
     * @param \HHK\Purchase\ValueAddedTax $vat
     * @param mixed $showVisitFee
     * @param mixed $showRoomFees
     * @param mixed $showGuestNights
     * @return string
     */
    public static function createCurrentFees($visitStatus, VisitCharges $visitCharge, ValueAddedTax $vat, $showVisitFee = FALSE, $showRoomFees = TRUE, $showGuestNights = FALSE) {

        $roomAccount = new CurrentAccount($visitStatus, $showVisitFee, $showRoomFees, $showGuestNights);

        $roomAccount->load($visitCharge, $vat);
        $roomAccount->setDueToday();

        return self::currentBalanceMarkup($roomAccount);
    }

    /**
     * Summary of currentBalanceMarkup
     * @param \HHK\Purchase\CurrentAccount $curAccount
     * @return string
     */
    protected static function currentBalanceMarkup(CurrentAccount $curAccount) {

        $uS = Session::getInstance();
        $tbl2 = new HTMLTable();
        $showSubTotal = FALSE;
        // Get labels
        $labels = Labels::getLabels();
        $totalTaxAmt = 0;
        $partialPaymentAmt = 0;

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

                $totalTaxAmt += $taxAmt;

                $tbl2->addBodyTr(
                    HTMLTable::makeTd($t->getTaxingItemDesc() .  ' (' . $t->getTextPercentTax() . ' of $' . number_format($taxedRoomFees, 2) . '):', array('class'=>'tdlabel', 'style'=>'font-size:small;'))
                    . HTMLTable::makeTd('$' . number_format($taxAmt, 2), array('style'=>'text-align:right;font-size:small;'))
                );
            }
        }



        // Cleaning fees charged
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

        // Partial guest payments
        $dbh = initPDO(true);
        $stmt = $dbh->prepare("SELECT ifnull(sum(`Amount` - `Balance`), 0)
FROM `invoice` `i` left join `name_volunteer2` `nv` on `i`.`Sold_To_Id` = `nv`.`idName` AND `nv`.`Vol_Category` = 'Vol_Type' and `nv`.`Vol_Code` = 'ba'
where `Deleted` = 0 and `Status` = 'up'
	and `Amount` - `Balance` > 0
	and `Order_Number` = :idvisit
    and ifnull(nv.idName, 0) = 0;");

            // "SELECT ifnull(sum(`Amount` - `Balance`), 0) FROM `invoice`
	        // where `Deleted` = 0 and `Status` = 'up'
            // and `Amount` - `Balance` > 0
            // and `Order_Number` = :idvisit
            // and `Suborder_Number` = :span;");

        $stmt->execute([':idvisit'=> $curAccount->getIdVisit()]);

        $row = $stmt->fetchAll(\PDO::FETCH_NUM);
        $partialPaymentAmt = round($row[0][0], 2);


        if ($partialPaymentAmt < 0) {
            $partialPaymentAmt = 0.0;
        }


        // Partial payments to date
        if ($partialPaymentAmt > 0) {

            $tbl2->addBodyTr(
                HTMLTable::makeTd('Partial Payments:', array('class' => 'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($partialPaymentAmt, 2), array('style' => 'text-align:right;'))
            );
        }

        // Total Paid to date
        $tbl2->addBodyTr(
                HTMLTable::makeTd('Amount paid to-date:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($curAccount->getTotalPaid() + $partialPaymentAmt, 2), array('style'=>'text-align:right;'))
        );

        // unpaid invoices
        if ($curAccount->getAmtPending3P() != 0) {
            $tbl2->addBodyTr(
                HTMLTable::makeTd('Amount Pending:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($curAccount->getAmtPending3P(), 2), array('style'=>'text-align:right;'))
            );
        }



        // Special class for current balance.
        $balAttr = array();
        $feesTitle = "";
        $dueToday = $curAccount->getDueToday() - $partialPaymentAmt;

        if ($dueToday > 0) {

            $balAttr['class'] = 'ui-state-highlight';
            $balAttr['title'] = 'Payment due today.';

            if ($curAccount->getVisitStatus() != VisitStatus::CheckedIn) {
                $feesTitle = 'House is owed at checkout:';
            } else {
                $feesTitle = 'House is owed as of today:';
            }

        } else if ($dueToday == 0) {

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
                . HTMLTable::makeTd('$' . HTMLContainer::generateMarkup('span', number_format(abs($dueToday), 2)
                        , array(
                            'id'=>'spnCfBalDue',
                        	'data-rmbal'=> number_format($curAccount->getRoomFeeBalance(), 2, '.', ''),
                            'data-taxedrmbal'=> number_format($curAccount->getTaxedRoomFeeBalance(), 2, '.', ''),
                            'data-vfee'=>number_format($curAccount->getVfeeBal(), 2, '.', ''),
                            'data-totbal'=>number_format($curAccount->getDueToday(), 2, '.', '')))
                        , $balAttr)
            , array('style'=>'border: solid 2px #2E99DD;text-align:right;')
        );

        // TODO
        // Total Due at end of visit -- but not for Gorecki House or PriceGuestDaily
        if ($curAccount->getVisitStatus() == VisitStatus::CheckedIn && !stristr(strtolower($uS->siteName), 'gorecki') && $uS->RoomPriceModel !== ItemPriceCode::PerGuestDaily) {

            $feesToCharge = round($curAccount->getRoomFeesToCharge());

            if ($feesToCharge > 0) {
                $feesToCharge += $totalTaxAmt;
            }

            $finalCharge = $curAccount->getTotalCharged() + $feesToCharge - $curAccount->getTotalPaid() - $curAccount->getAmtPending3P() - $partialPaymentAmt;

            $tbl2->addBodyTr(
                HTMLTable::makeTd('Exp\'d payment at checkout:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . HTMLContainer::generateMarkup('span', number_format($finalCharge, 2)))
            );
        }

        return $tbl2->generateMarkup();

    }

    /**
     * Save a subset of fields in the visit.
     *
     * @param \PDO $dbh
     * @param int $idVisit
     * @param int $span
     * @param int $idStay
     * @param string $uname
     * @return string
     */
    public static function removeStay(\PDO $dbh, $idVisit, $span, $idStay, $uname) {

        if ($idStay == 0) {
            return '';
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

        $rows = EditRS::select($dbh, $visitRS, [$visitRS->idVisit, $visitRS->Span]);

        if (count($rows) != 1) {
            return 'The Visit Span is not found.  idVisit=' . $idVisit . ', span=' .$span;
        }

        EditRS::loadRow($rows[0], $visitRS);

        // Load stays
        $stayRs = new StaysRS();
        $stayRs->idVisit->setStoredVal($idVisit);
        $stayRs->Visit_Span->setStoredVal($span);
        $stayRows = EditRS::select($dbh, $stayRs, [$stayRs->idVisit, $stayRs->Visit_Span]);

        if (count($stayRows) < 2) {
            return 'Cannot remove the last stay in the visit span.  ';
        }

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
     * @return array
     */
    public static function moveVisit(\PDO $dbh, $idVisit, $span, $startDelta, $endDelta, $uname) {

        $uS = Session::getInstance();

        if ($startDelta == 0 && $endDelta == 0) {
            return [];
        }

        if (abs($endDelta) > ($uS->MaxExpected) || abs($startDelta) > ($uS->MaxExpected)) {
            return ['error'=>'Move refused, change too large: Start Delta = ' . $startDelta . ', End Delta = ' . $endDelta];
        }

        // get visit recordsets, order by span
        $visitRS = new VisitRs();
        $visitRS->idVisit->setStoredVal($idVisit);
        $visitRcrds = EditRS::select($dbh, $visitRS, [$visitRS->idVisit], 'and', [$visitRS->Span]);

        // Bad visit?.
        if (count($visitRcrds) < 1) {
            return ['error'=>'Visit not found'];
        }

        $startInterval = new \DateInterval('P' . abs($startDelta) . 'D');
        $endInterval = new \DateInterval('P' . abs($endDelta) . 'D');

        $spans = [];
        $stays = [];
        $firstArrival = NULL;
        $hasReservedSpan = false;
        $reserveSpanRs = null;


        $lastSpanId = 0;
        foreach ($visitRcrds as $r) {

            if ($r['Status'] == VisitStatus::Reserved) {
                $hasReservedSpan = true;
                $reserveSpanRs = $r;
            }

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
            return ['error'=>'Use only the begining span or the very last span to resize this visit.'];
        }


        $visits = [];

        $tonight = new \DateTime();
        $tonight->add(new \DateInterval('P1D'));
        $tonight->setTime(0,0,0);

        $today = new \DateTime();
        $today->setTime(intval($uS->CheckOutTime), 0, 0);

        reset($spans);

        // change visit span dates
        foreach ($spans as $s => $vRs) {

            $spanStartDT = newDateWithTz($vRs->Span_Start->getStoredVal(), $uS->tz);

            if ($vRs->Status->getStoredVal() == VisitStatus::CheckedIn || $vRs->Status->getStoredVal() == VisitStatus::Reserved) {

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
                if ($vRs->Status->getStoredVal() != VisitStatus::CheckedIn && $vRs->Status->getStoredVal() != VisitStatus::Reserved) {
                    if ($spanEndDt >= $tonight) {
                        return ['error'=>'Checked-Out visits cannot move their end date beyond todays date  Use Undo Checkout instead. '];
                    }
                }

                // Checked-in spans cannot move their start date beyond today's date.
                if ($vRs->Status->getStoredVal() == VisitStatus::CheckedIn) {
                    if ($spanStartDT >= $tonight) {
                        return ['error'=>'Checked-in visits cannot move their start date beyond todays date. '];
                    }
                }
            }

            // Visit Still Good?
            if ($vRs->Status->getStoredVal() == VisitStatus::CheckedIn && ($spanEndDt < $spanStartDT || $spanEndDt < $today)) {
                return ['error'=>"The visit span End date cannot come before the Start date, or before today.  "];
            } else if ($vRs->Status->getStoredVal() != VisitStatus::CheckedIn && $spanEndDt <= $spanStartDT) {
                return ['error'=>"The visit span End date cannot come before or on the Start date.  "];
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
                return ['error'=>'The Date range is not available'];
            }

            $visits[$s]['rs'] = $vRs;
            $visits[$s]['start'] = $spanStartDT;
            $visits[$s]['end'] = $spanEndDt;


            if (isset($stays[$vRs->Span->getStoredVal()])) {
                
                $stayMsg = self::moveStaysDates($dbh, $stays[$vRs->Span->getStoredVal()], $startDelta, $endDelta, $visits[$s]);

                if ($stayMsg != '') {
                    return ['error'=>$stayMsg];
                }
            }
        }

        // Check for pre-existing reservations
        $resvs = ReservationSvcs::getCurrentReservations($dbh, $visitRcrds[0]['idReservation'], $visitRcrds[0]['idPrimaryGuest'], 0, $firstArrival, $spanEndDt);

        if (count($resvs) > 0) {
            return ['error'=>"The Move overlaps another reservation or visit.  "];
        }

        $actualDepart = NULL;
        $estDepart = NULL;

        // If I got this far, all the resouorces are available.
        foreach ($visits as $v) {

            $visitRS = $v['rs'];

            $visitRS->Span_Start->setNewVal($v['start']->format('Y-m-d H:i:s'));
            $visitRS->Arrival_Date->setNewVal($firstArrival->format('Y-m-d H:i:s'));

            if ($visitRS->Status->getStoredVal() == VisitStatus::CheckedIn || $visitRS->Status->getStoredVal() == VisitStatus::Reserved) {

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

            if (isset($stays[$visitRS->Span->getStoredVal()])) {
                self::saveStaysDates($dbh, $stays[$visitRS->Span->getStoredVal()], $visitRS->idRegistration->getStoredVal(), $uname);
            }
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

        $operatingHours = new OperatingHours($dbh);
        if($operatingHours->isHouseClosed($firstArrival)){
            $reply .= "-  Info: The house is closed on that Start date. ";
        }

        if ($startDelta == 0) {
            $reply = ['success'=>'Visit checkout date changed. ' . $reply];
        } else {
            $reply = ['success'=>'Visit Moved. ' . $reply];
        }
        return $reply;
    }

    /**
     * Move the stays in a visit by delta days.
     *
     * @param array $stays
     * @param int $startDelta
     * @param int $endDelta
     * @return string
     */
    protected static function moveStaysDates(\PDO $dbh, $stays, $startDelta, $endDelta, $visits) {

        $uS = Session::getInstance();

        $startInterval = new \DateInterval('P' . abs($startDelta) . 'D');
        $endInterval = new \DateInterval('P' . abs($endDelta) . 'D');

        $tonight = new \DateTime();
        $tonight->add(new \DateInterval('P1D'));
        $tonight->setTime(0,0,0);

        $today = new \DateTime();
        $today->setTime(intval($uS->CheckOutTime), 0, 0);

        /**
         * @var \DateTimeImmutable $spanStartDT
         */
        $spanStartDT = \DateTimeImmutable::createFromMutable($visits['start']->setTime(10,0,0));

        /**
         * @var \DateTimeImmutable $spanEndDT
         */
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
                        $stayStartDT = $stayStartDT->sub(new \DateInterval('P' .$startDelta . 'D'));
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

    public static function changeVisitFee(\PDO $dbh, $visitFeeOption, Visit $visit) {

        $uS = Session::getInstance();
        $vFees = readGenLookupsPDO($dbh, 'Visit_Fee_Code');
        $reply = '';

        if (isset($vFees[$visitFeeOption])) {

            $resv = Reservation_1::instantiateFromIdReserv($dbh, $visit->getReservationId());

            if ($resv->isNew() === FALSE) {

                if ($resv->getVisitFee() != $vFees[$visitFeeOption][2]) {
                    // visit fee is updated.

                    $visitCharge = new VisitCharges($visit->getIdVisit());
                    $visitCharge->sumPayments($dbh);

                    if ($visitCharge->getVisitFeesPaid() > 0) {
                        // Change to no visit fee, already paid fee
                        $reply .= ' Return Cleaning Fee Payment and delete the invoice before changing it.  ';

                    } else {

                        $resv->setVisitFee($vFees[$visitFeeOption][2]);
                        $resv->saveReservation($dbh, $visit->getIdRegistration(), $uS->username);

                        $reply .= 'Cleaning Fee Setting Updated.  ';
                    }
                }
            }
        }

        return $reply;

    }
}