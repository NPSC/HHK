<?php
/**
 * visitViewer.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Visitview creates HTML markup with specified visit, stay and payment history.  Also saves some of the returned markup.
 *
 * @author Eric
 */
class VisitView {

    public static function createActiveMarkup(\PDO $dbh, array $r, VisitCharges $visitCharge, $keyDepFlag, $visitFeeFlag, $isAdmin,
            $extendVisitDays, $action, $coDate, $showAdjust) {

        $uS = Session::getInstance();

        // Take Payment doesn't need this section.
        if ($action == 'pf' || $action == 'cr') {
            return '';
        }

        $table = new HTMLTable();

        // Get labels
        $labels = new Config_Lite(LABEL_FILE);

        // Notes
        $trNotes = HTMLTable::makeTd('Notes: ', array('class' => 'tdlabel'));

        if ($uS->ConcatVisitNotes) {
            $trNotes .= HTMLTable::makeTd(Notes::markupShell($r['Notes'], 'tavisitnotes'), array('colspan' => '8', 'style'=>'min-width:500px;'));
        } else {
            $trNotes .= HTMLTable::makeTd(Notes::markupShell($r['Visit_Notes'], 'tavisitnotes'), array('colspan' => '8', 'style'=>'min-width:500px;'));
        }


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
        $addGuestButton = '';

        switch ($r['Status']) {
            case VisitStatus::CheckedIn:

                $depHeader = "Expected End";
                $daysHeader = "Expected Nights";

                if ($action == 'ref') {
                    // deal with changed checkout date
                    $deptDT = setTimeZone($uS, $coDate);
                    $deptDT->setTime(0, 0, 0);
                    $arrivalDT = setTimeZone($uS, $r['Arrival_Date']);
                    $arrivalDT->setTime(0, 0, 0);
                    $days = $deptDT->diff($arrivalDT, TRUE)->days;
                    $departureText = $deptDT->format('M, j, Y');

                } else {

                    $departureText = date('M j, Y', strtotime($r['Expected_Departure']));
                    $days = $r['Expected_Nights'];
                }

                $addGuestButton = HTMLContainer::generateMarkup('div',
                    HTMLInput::generateMarkup('Add Guest', array('id'=>'btnAddGuest', 'type'=>'button', 'data-rid'=>$r['idReservation'], 'title'=>'Add another guest to this visit.'))
                    , array('style'=>'float:left;margin-left:.3em;'));

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

            $vFeeSelector = RateChooser::makeVisitFeeSelector(RateChooser::makeVisitFeeArray($dbh, $visitCharge->getVisitFeeCharged()), $visitCharge->getVisitFeeCharged(), 'hhk-feeskeys');

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

        // Patient Name
        $th .= HTMLTable::makeTh($labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital'));
        $tr .= HTMLTable::makeTd($hname);

        if ($r['Patient_Name'] != '') {
            $th .= HTMLTable::makeTh($labels->getString('MemberType', 'patient', 'Patient'));
            $tr .= HTMLTable::makeTd($r['Patient_Name']);
        }


        // add completed rows to table
        $table->addBodyTr($tr);
        $table->addHeaderTr($th);
        $tblMarkup = $table->generateMarkup(array('id' => 'tblActiveVisit', 'style'=>'width:99%;'));

        // Change Rate markup
        if ($uS->RoomPriceModel != ItemPriceCode::None && $action != 'ref') {

            $rateChooser = new RateChooser($dbh);
            $vRs = new VisitRs();
            EditRS::loadRow($r, $vRs);
            $rateTbl = $rateChooser->createChangeRateMarkup($dbh, $vRs, $isAdmin);

            $tblMarkup .= $rateTbl->generateMarkup(array('style'=>'clear:left;margin-bottom:.3em; margin-top:.3em;'));
        }

        // Notes
        $notesTbl = new HTMLTable();
        $notesTbl->addBodyTr($trNotes);
        $tblMarkup .= $notesTbl->generateMarkup(array('id' => 'tblActiveNotes', 'style'=>'clear:left;float:left;'));

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

            $tblMarkup .= $etbl->generateMarkup(array('style'=>'float:left;margin-left:.3em;'));
        }

        // Adjust button
        if ($showAdjust) {

            $tblMarkup .= HTMLContainer::generateMarkup('div',
                    HTMLInput::generateMarkup('Adjust Fees', array('name'=>'paymentAdjust', 'type'=>'button', 'class'=>'hhk-feeskeys', 'title'=>'Create one-time additional charges or discounts.'))
                    , array('style'=>'float:left;margin-left:.3em;'));
        }




        $tblMarkup .= $addGuestButton . HTMLContainer::generateMarkup('div','', array('style'=>'clear:both;'));


        $undoCkoutButton = '';


        // Make undo checkout button.
        if ($r['Status'] == VisitStatus::CheckedOut && $isAdmin) {

            $spnMkup = HTMLContainer::generateMarkup('label', '- Undo Checkout', array('for'=>'undoCkout'))
                    . HTMLInput::generateMarkup('', array('id'=>'undoCkout', 'type'=>'checkbox', 'class'=>'hhk-feeskeys', 'style'=>'margin-right:.3em;margin-left:0.3em;'))
                    . HTMLContainer::generateMarkup('span', 'New Expected Departure Date: ', array('style'=>'margin-right: 0.3em; margin-left:0.3em;'))
                    . HTMLInput::generateMarkup('', array('id'=>'txtUndoDate', 'class'=>'ckdateFut hhk-feeskeys'));

            $undoCkoutButton = HTMLContainer::generateMarkup('span', $spnMkup, array('style'=>'margin:0 1em;', 'title'=>'Undo Checkout'));

        } else if ($r['Status'] == VisitStatus::NewSpan) {

            $spnMkup = HTMLContainer::generateMarkup('label', '- Undo Room Change', array('for'=>'undoRmChg'))
                    . HTMLInput::generateMarkup('', array('id'=>'undoRmChg', 'type'=>'checkbox', 'class'=>'hhk-feeskeys', 'style'=>'margin-right:.3em;margin-left:0.3em;'));

            $undoCkoutButton = HTMLContainer::generateMarkup('span', $spnMkup, array('style'=>'margin:0 1em;', 'title'=>'Undo Room Change'));
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
    public static function createStaysMarkup(\PDO $dbh, $idVisit, $span, $idPrimaryGuest, $isAdmin, $idGuest, Config_Lite $labels, $action = '', $coDate = '') {

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


            try {
                $ckinDT = new \DateTime($r['Checkin_Date']);
                $ckinDT->setTime(0, 0, 0);
            } catch (Exception $ex) {

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

                    if ($action == 'ref' && $coDate != '') {
                        $edDay = new \DateTime($coDate);
                    } else {
                        $edDay = new \DateTime(date('Y-m-d'));
                    }

                    $edDay->setTime(0, 0, 0);
                    $days = $edDay->diff($stDayDT, TRUE)->days;

                    $getCkOutDate = HTMLInput::generateMarkup($edDay->format('M j, Y'), array('id' => 'stayCkOutDate_' . $r['idName'], 'name' =>'[stayCkOutDate][' . $r['idName'] . ']', 'class' => 'ckdate hhk-ckoutDate', 'readonly'=>'readonly'));
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
            if (isset($uS->guestLookups[GL_TableNames::PatientRel][$r['Relationship_Code']])) {
                $rel = $uS->guestLookups[GL_TableNames::PatientRel][$r['Relationship_Code']][1];
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
                    && $r['Visit_Span'] == 0
                    && ($r["Visit_Status"] == VisitStatus::CheckedIn || $r["Visit_Status"] == VisitStatus::CheckedOut)) {

                $tr .= HTMLTable::makeTd(HTMLInput::generateMarkup('', array('id' => 'removeCb_' . $r['idName'], 'name' => '[removeCb][' . $r['idName'] . ']',
                    'data-nm' => $name,
                    'type' => 'checkbox',
                    'class' => 'hhk-removeCB' )), array('style'=>'text-align:center;'));

                $useRemoveHdr = TRUE;
            }

            $sTable->addBodyTr($tr);
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
            $th .= HTMLTable::makeTh($ckOutTitle)
            . HTMLTable::makeTh('Nights');
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


        // Make add guest button
        $guestAddButton = '';
        if ($isAdmin && $visitStatus != VisitStatus::CheckedIn) {

            $guestAddButton = HTMLContainer::generateMarkup('span', '', array('id'=>'guestAdd', 'class'=>'ui-icon ui-icon-circle-plus', 'style'=>'float:left;margin-left: 1.3em; cursor:pointer;', 'title'=>'Add a Guest to this visit'))
                    .HTMLContainer::generateMarkup('span', 'Add Guest', array('style'=>'float:left;margin-left: 0.3em; display:none;', 'class'=>'hhk-addGuest'))
                .HTMLContainer::generateMarkup('span', HTMLInput::generateMarkup('', array('id'=>'txtAddGuest', 'title'=>'Type 3 characters to start the search.')), array('title'=>'Search', 'style'=>'margin-left:0.3em;margin-right:0.3em;padding-bottom:.2em; display:none;', 'class'=>'hhk-addGuest'));
        }


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
        if ($uS->KeyDeposit && $r['Status'] == VisitStatus::CheckedIn && ($action == '' || $action == 'pf') && $visitCharge->getDepositCharged() > 0 && ($visitCharge->getDepositPending() + $visitCharge->getKeyFeesPaid()) < $visitCharge->getDepositCharged()) {
            $includeKeyDep = TRUE;
        }

        $includeVisitFee = FALSE;
        if ($uS->VisitFee && ($action == '' || $action == 'pf') && $visitCharge->getVisitFeeCharged() > 0 && ($visitCharge->getNightsStayed() > $uS->VisitFeeDelayDays || $uS->VisitFeeDelayDays == 0)) {
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


        $currFees = '';
        $paymentMarkup = '';


        if ($includeKeyDep || $includeVisitFee || $includeAddnlCharge || $showRoomFees) {
			
            // Current fees block
            $currFees = HTMLContainer::generateMarkup('fieldset',
                    HTMLContainer::generateMarkup('legend', ($r['Status'] == VisitStatus::CheckedIn ? 'To-Date Fees & Balance Due' : 'Final Fees & Balance Due'), array('style'=>'font-weight:bold;'))
                    . HTMLContainer::generateMarkup('div', self::createCurrentFees($r['Status'], $visitCharge, $includeVisitFee, $showRoomFees, $showGuestNights), array('style'=>'float:left;', 'id'=>'divCurrFees'))
                        , array('class'=>'hhk-panel', 'style'=>'float:left;margin-right:10px;'));

            // Show Final payment?
            $showFinalPayment = FALSE;
            if ($action != 'pf' && ($r['Status'] == VisitStatus::CheckedIn || $r['Status'] == VisitStatus::CheckedOut)) {
                $showFinalPayment = TRUE;
            }

            // New Payments
            $paymentMarkup = PaymentChooser::createMarkup(
                    $dbh,
                    $idGuest,
                    $r['idRegistration'],
                    $visitCharge,
                    $uS->DefaultPayType,
                    $includeKeyDep,
                    $showFinalPayment,
                    FALSE,
                    $r['Pref_Token_Id']
                    );

        }


        return $currFees . $paymentMarkup;
    }


    public static function createCurrentFees($visitStatus, VisitCharges $visitCharge, $showVisitFee = FALSE, $showRoomFees = TRUE, $showGuestNights = FALSE) {


        // Build Output.
        $tbl2 = new HTMLTable();

        // Number of nights
        $tbl2->addBodyTr(
                HTMLTable::makeTd('# of nights stayed:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd($visitCharge->getNightsStayed())
        );


        // Number of guest-nights
        if ($showGuestNights) {

            $additNights = $visitCharge->getGuestNightsStayed() - $visitCharge->getNightsStayed();

            $tbl2->addBodyTr(
                    HTMLTable::makeTd('Additional guest-nights:', array('class'=>'tdlabel'))
                    . HTMLTable::makeTd($additNights < 0 ? 0 : $additNights)
            );
        }

        // Visit Glide
        if ($visitCharge->getGlideCredit() > 0) {
            $tbl2->addBodyTr(
                HTMLTable::makeTd('Room rate aged (days):', array('class'=>'tdlabel'))
                . HTMLTable::makeTd($visitCharge->getGlideCredit()));
        }

        // Room Fees amount pledged
        if ($showRoomFees) {
            $tbl2->addBodyTr(
                HTMLTable::makeTd('Room fees pledged to-date:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($visitCharge->getRoomFeesCharged(), 2), array('style'=>'text-align:right;'))
            );
        }

        $showSubTotal = FALSE;

        // Visit fees charged
        if ($showVisitFee && $visitCharge->getVisitFeeCharged() > 0) {

            // Get labels
            $labels = new Config_Lite(LABEL_FILE);

            $showSubTotal = TRUE;

            $tbl2->addBodyTr(
                HTMLTable::makeTd($labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee'), array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($visitCharge->getVisitFeeCharged(), 2), array('style'=>'text-align:right;'))
            );
        }

        // Additional charges
        if ($visitCharge->getItemInvCharges(ItemId::AddnlCharge) > 0) {

            $showSubTotal = TRUE;

            $tbl2->addBodyTr(
                HTMLTable::makeTd('Additional Charges', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($visitCharge->getItemInvCharges(ItemId::AddnlCharge), 2), array('style'=>'text-align:right;'))
            );
        }

        // MOA
        $totalMOA = 0;
        if ($visitCharge->getItemInvCharges(ItemId::LodgingMOA) > 0) {

            $totalMOA = $visitCharge->getItemInvCharges(ItemId::LodgingMOA);
            $showSubTotal = TRUE;

            $tbl2->addBodyTr(
                HTMLTable::makeTd('Money On Account', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($visitCharge->getItemInvCharges(ItemId::LodgingMOA), 2), array('style'=>'text-align:right;'))
            );
        }

        // Discounts
        $totalDiscounts = $visitCharge->getItemInvCharges(ItemId::Discount) + $visitCharge->getItemInvCharges(ItemId::Waive);
        if ($totalDiscounts != 0) {

            $showSubTotal = TRUE;

            $tbl2->addBodyTr(
                HTMLTable::makeTd('Discounts', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($totalDiscounts, 2), array('style'=>'text-align:right;'))
            );
        }

        $totalCharged =
                $visitCharge->getRoomFeesCharged()
                + $visitCharge->getItemInvCharges(ItemId::AddnlCharge)
                + $totalMOA
                + $totalDiscounts;
                
            //if show visit fee
            if($showVisitFee){
	            $totalCharged += $visitCharge->getVisitFeeCharged();
            }

        // Subtotal line
        if ($showSubTotal) {

            $tbl2->addBodyTr(
                HTMLTable::makeTd('Total Charges:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($totalCharged, 2), array('style'=>'text-align:right;border-top: solid 3px #2E99DD;'))
            );
        }


        // Payments
        $totalPaid = $visitCharge->getRoomFeesPaid()
                + $visitCharge->getVisitFeesPaid()
                + $visitCharge->getItemInvPayments(ItemId::AddnlCharge);



        if ($visitCharge->getItemInvPayments(ItemId::LodgingMOA) > 0) {
            $totalPaid += $visitCharge->getItemInvPayments(ItemId::LodgingMOA);
        }

        // Add Waived amounts.
        $totalPaid += $visitCharge->getItemInvPayments(ItemId::Waive);

        $tbl2->addBodyTr(
                HTMLTable::makeTd('Amount paid to-date:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($totalPaid, 2), array('style'=>'text-align:right;'))
        );

        $amtPending = $visitCharge->getRoomFeesPending() + $visitCharge->getVisitFeesPending() + $visitCharge->getItemInvPending(ItemId::AddnlCharge) + $visitCharge->getItemInvPending(ItemId::Waive);

        // unpaid invoices
        if ($amtPending != 0) {
            $tbl2->addBodyTr(
                HTMLTable::makeTd('Amount Pending:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . number_format($amtPending, 2), array('style'=>'text-align:right;'))
            );
        }

        $dueToday = $totalCharged - $totalPaid - $amtPending;

        // Special class for current balenance.
        $balAttr = array('style'=>'border-top: solid 3px #2E99DD;');
        $feesTitle = "";

        if ($dueToday > 0) {

            $balAttr['class'] = 'ui-state-highlight';
            $balAttr['title'] = 'Payment due today.';

            if ($visitStatus != VisitStatus::CheckedIn) {
                $feesTitle = 'House is owed at checkout:';
            } else {
                $feesTitle = 'House is owed as of today:';
            }

        } else if ($dueToday == 0) {

            $balAttr['title'] = 'No payments are due today.';
            $feesTitle = 'Balance as of today:';

        } else {

            $balAttr['title'] = 'No payments are due today.';

            if ($visitStatus != VisitStatus::CheckedIn) {
                $feesTitle = 'Guest credit at checkout:';
            } else {
                $feesTitle = 'Guest credit as of today:';
            }

        }

        $vfeeBal = $visitCharge->getVisitFeeCharged() - $visitCharge->getVisitFeesPaid() - $visitCharge->getVisitFeesPending();

        $tbl2->addBodyTr(
                HTMLTable::makeTd($feesTitle, array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$' . HTMLContainer::generateMarkup('span', number_format(abs($dueToday), 2), array('id'=>'spnCfBalDue', 'data-vfee'=>number_format($vfeeBal, 2, '.', ''), 'data-bal'=>number_format($dueToday, 2, '.', ''))), $balAttr)
        );

        return $tbl2->generateMarkup() ;

    }


    /**
     * Save a subset of fields in the visit.
     *
     * @param PDO $dbh
     * @param int $idVisit
     * @param int $span
     * @param array $pData
     * @param string $uname
     * @param string $idPrefix
     * @return string
     */
    public static function removeStays(\PDO $dbh, $idVisit, $span, $idGuest, $uname) {

        $reply = '';

        if ($idGuest == 0) {
            return;
        }

        // recordset
        $visitRS = new VisitRs();
        $visitRS->idVisit->setStoredVal($idVisit);
        $rows = EditRS::select($dbh, $visitRS, array($visitRS->idVisit));

        // Currently limited to single span visit.
        if (count($rows) != 1 || $span != 0) {
            return 'Cannot remove guest from the visit.  ';
        }

        EditRS::loadRow($rows[0], $visitRS);

        if ($idGuest == $visitRS->idPrimaryGuest->getStoredVal()) {
            return 'Switch the primary guest to someone else before deleting this guest.  ';
        }


        // Remove guests from the visit span
        if ($visitRS->Status->getStoredVal() == VisitStatus::CheckedIn || $visitRS->Status->getStoredVal() == VisitStatus::CheckedOut) {

            $stayRS = new StaysRS();
            $stayRS->idVisit->setStoredVal($idVisit);
            $stayRS->Visit_Span->setStoredVal($span);
            $stays = EditRS::select($dbh, $stayRS, array($stayRS->idVisit, $stayRS->Visit_Span));

            $countStays = count($stays);

            if ($countStays < 2) {
                return 'Cannot remove last guest from the visit.  ';
            }


            $remainingStays = array();

            // delete guest stays
            foreach ($stays as $s) {

                $stayRS = new StaysRS();
                EditRS::loadRow($s, $stayRS);

                if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedOut && $stayRS->idName->getStoredVal() == $idGuest) {
                    $countStays--;

                    $reply .= "Guest deleted from this visit.  ";

                    //delete record
                    $logText = VisitLog::getDeleteText($stayRS, $stayRS->idStays->getStoredVal());
                    VisitLog::logStay($dbh, $stayRS->idVisit->getStoredVal(), $stayRS->Visit_Span->getStoredVal(), $stayRS->idRoom->getStoredVal(), $stayRS->idStays->getStoredVal(),$stayRS->idName->getStoredVal(), $visitRS->idRegistration->getStoredVal(), $logText, "delete", $uname);

                    EditRS::delete($dbh, $stayRS, array($stayRS->idStays));

                } else {
                    $remainingStays[] = $stayRS;
                }
            }

            // Adjust visit start and end dates, if needed
            if (count($stays) != $countStays) {

                $earliestStart = new \DateTime('2900-01-01');
                $latestEnd = new \DateTime('1984-01-01');

                // Find the earlyest start and latest end of the remaining stays.
                foreach ($remainingStays as $sRs) {

                    $st = new \DateTime($sRs->Span_Start_Date->getStoredVal());
                    $ed = new \DateTime($sRs->Span_End_Date->getStoredVal());

                    if ($st < $earliestStart) {
                        $earliestStart = $st;
                    }

                    if ($ed > $latestEnd) {
                        $latestEnd = $ed;
                    }
                }

                $visitRS->Span_Start->setNewVal($earliestStart->format('y-m-d H:i:s'));
                $visitRS->Arrival_Date->setNewVal($earliestStart->format('y-m-d H:i:s'));

                if ($visitRS->Status->getStoredVal() != VisitStatus::CheckedIn) {
                    $visitRS->Span_End->setNewVal($latestEnd->format('y-m-d H:i:s'));
                    $visitRS->Actual_Departure->setNewVal($latestEnd->format('y-m-d H:i:s'));
                }

                $visitRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
                $visitRS->Updated_By->setNewVal($uname);

                $uctr = EditRS::update($dbh, $visitRS, array($visitRS->idVisit, $visitRS->Span));

                if ($uctr > 0) {
                    $logText = VisitLog::getUpdateText($visitRS);
                    VisitLog::logVisit($dbh, $idVisit, $visitRS->Span->getStoredVal(), $visitRS->idResource->getStoredVal(), $visitRS->idRegistration->getStoredVal(), $logText, "update", $uname);
                    $reply .= 'Visit start and/or end dates changed due to removing the guest.  ';
                }
            }
        }

        return $reply;
    }

    public static function updatePsgNotes(\PDO $dbh, \Psg $psg, $notes) {

        if ($notes != '') {

            $oldNotes = is_null($psg->psgRS->Notes->getStoredVal()) ? '' : $psg->psgRS->Notes->getStoredVal();
            $psg->psgRS->Notes->setNewVal($oldNotes . $notes);
            EditRS::update($dbh, $psg->psgRS, array($psg->psgRS->idPsg));
            EditRS::updateStoredVals($psg->psgRS);
        }

    }

    public static function visitMessageArea($header, $message) {
        $mkup = HTMLContainer::generateMarkup('h4', $header, array('id'=>'h3VisitMsgHdr'))
                . HTMLContainer::generateMarkup('span', $message, array('id'=>'spnVisitMsg'));

        return $mkup;
    }


    /**
     * Move a visit temporally by delta days
     *
     * @param PDO $dbh
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

        if (abs($endDelta) > ($uS->CalViewWeeks * 7) || abs($startDelta) > ($uS->CalViewWeeks * 7)) {
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

        // Pre-filter list of visit spans
        foreach ($visitRcrds as $r) {

            $vRs = new VisitRs();
            EditRS::loadRow($r, $vRs);

            // Save first arrival
            if ($vRs->Span->getStoredVal() == 0) {
                $firstArrival = setTimeZone(NULL, $vRs->Arrival_Date->getStoredVal());
            }

            // no changes to earlier spans on resize, or spans after the next
            if ($startDelta == 0 && ($vRs->Span->getStoredVal() < $span || $vRs->Span->getStoredVal() > ($span + 1))) {
                continue;
            }

            if ($endDelta == 0 && ($vRs->Span->getStoredVal() < ($span - 1) || $vRs->Span->getStoredVal() > ($span))) {
                continue;
            }

            $spans[$vRs->Span->getStoredVal()] = $vRs;

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
        if ($startDelta == 0 && isset($spans[$span])) {

            $vRS = $spans[$span];

            if ($vRS->Status->getStoredVal() != VisitStatus::Active && $vRS->Status->getStoredVal() != VisitStatus::CheckedOut) {
                return 'Cannot change the end date because the visit continues in another room or at another rate.  ';
            }
        }

        $visits = array();
        $now = new \DateTime();
        $tonight = new \DateTime();
        $tonight->setTime(23, 59, 59);

        $today = new \DateTime();
        $today->setTime(10, 0, 0);

        reset($spans);

        // change visit span dates
        foreach ($spans as $s => $vRs) {

            $newStartDT = setTimeZone(NULL, $vRs->Span_Start->getStoredVal());

            if ($vRs->Status->getStoredVal() == VisitStatus::CheckedIn) {

                $newEndDt = setTimeZone(NULL, $vRs->Expected_Departure->getStoredVal());
                $newEndDt->setTime(10,0,0);

                if ($newEndDt < $today) {
                    $newEndDt = $today;
                }

            } else {
                $newEndDt = setTimeZone(NULL, $vRs->Span_End->getStoredVal());
            }

            $modEndDt = new \DateTime($newEndDt->format('Y-m-d H:i:s'));


            if ($endDelta < 0 || $startDelta < 0) {

                // Move back
                $newEndDt->sub($endInterval);
                $modEndDt->sub($endInterval);
                $newStartDT->sub($startInterval);

                // Validity check
                if ($endDelta < 0 && $startDelta == 0) {
                    $endDATE = setTimeZone(NULL, $newEndDt->format('Y-m-d 00:00:00'));
                    $startDATE = setTimeZone(NULL, $newStartDT->format('Y-m-d 00:00:00'));
                    if ($endDATE <= $startDATE) {
                        return "The span End date comes before the Start date.  ";
                    }
                }

                // Only change first arrival if this is the first span
                if ($s == 0 && $startDelta != 0) {
                    $firstArrival->sub($startInterval);
                }

                // Set end time to today if checked in and end time is earlier.
                if ($vRs->Status->getStoredVal() == VisitStatus::CheckedIn) {
                    if ($newEndDt < $tonight) {
                        $modEndDt = $now;
                    }
                }

            } else {

                // Spring ahead
                $newEndDt->add($endInterval);
                $modEndDt->add($endInterval);
                $newStartDT->add($startInterval);

                // Only change first arrival if this is the first span
                if ($s == 0 && $startDelta != 0) {
                    $firstArrival->add($startInterval);
                }

                // Validity check
                if ($startDelta > 0 && $endDelta == 0) {
                    $endDATE = setTimeZone(NULL, $newEndDt->format('Y-m-d 00:00:00'));
                    $startDATE = setTimeZone(NULL, $newStartDT->format('Y-m-d 00:00:00'));
                    if ($endDATE <= $startDATE) {
                        return "The span End date comes before the Start date.  ";
                    }
                }

                // Checked-Out visits cannot move their end date beyond todays date.
                if ($vRs->Status->getStoredVal() == VisitStatus::CheckedOut) {
                    if ($newEndDt > $tonight) {
                        return 'Checked-Out visits cannot move their end date beyond todays date  Use Undo Checkout instead. ';
                    }
                }

                // Checked-in visits cannot move their start date beyond today's date.
                if ($vRs->Status->getStoredVal() == VisitStatus::CheckedIn) {
                    if ($newStartDT > $tonight) {
                        return 'Checked-in visits cannot move their start date beyond todays date. ';
                    }
                }
            }


            // Check visits first.
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
                ':beginDate'=>$newStartDT->format('Y-m-d'),
                ':endDate'=>$modEndDt->format('Y-m-d'),
                ':vstat'=> VisitStatus::Pending,
                ':visit'=>$vRs->idVisit->getStoredVal(),
                ':idr'=>$vRs->idResource->getStoredVal()));

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


            if (count($rows) > 0) {
                // not available
                return 'The Date range is not available';
            }

            $visits[$s]['rs'] = $vRs;
            $visits[$s]['start'] = $newStartDT;
            $visits[$s]['end'] = $newEndDt;

            $stayMsg = self::moveStaysDates($stays[$vRs->Span->getStoredVal()], $startDelta, $endDelta);
            if ($stayMsg != '') {
                return $stayMsg;
            }
        }

        // Check for pre-existing reservations
        $resvs = ReservationSvcs::getCurrentReservations($dbh, $visitRcrds[0]['idReservation'], $visitRcrds[0]['idPrimaryGuest'], 0, $firstArrival, $newEndDt);
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

            if ($visitRS->Span_End->getStoredVal() != '') {
                $visitRS->Span_End->setNewVal($v['end']->format('Y-m-d H:i:s'));
            }
            $visitRS->Expected_Departure->setNewVal($v['end']->format('Y-m-d H:i:s'));
            $estDepart = $v['end']->format('Y-m-d H:i:s');

            if ($visitRS->Actual_Departure->getStoredVal() != '') {
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
        if ($uS->ShowLodgDates) {

            $lines = array();
            $itemDesc = '';
            $stmt = $dbh->query("Select il.*, it.Description as `Item_Description` from
            invoice i left join invoice_line il on i.idInvoice = il.Invoice_Id
            left join item it on il.Item_Id = it.idItem
    where i.Deleted = 0 and il.Deleted = 0 and il.Item_Id = " . ItemId::Lodging . " and i.Order_Number = '$idVisit'");

            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

                $ilRs = new InvoiceLineRS();
                EditRS::loadRow($r, $ilRs);

                $iLine = InvoiceLine::invoiceLineFactory($ilRs->Type_Id->getStoredVal());
                $iLine->loadRecord($ilRs);
                $lines[] = $iLine;
                $itemDesc = $r['Item_Description'];
            }


            if (count($lines) > 0 && $startDelta != 0) {

                foreach ($lines as $il) {

                    $stDT = setTimeZone(NULL, $il->getPeriodStart());
                    $edDT = setTimeZone(NULL, $il->getPeriodEnd());

                    if ($startDelta > 0) {
                        $stDT->add($startInterval);
                        $edDT->add($startInterval);
                    } else {
                        $stDT->sub($startInterval);
                        $edDT->sub($startInterval);
                    }

                    $il->setPeriodStart($stDT->format('Y-m-d'));
                    $il->setPeriodEnd($edDT->format('Y-m-d'));
                    $il->setDescription($itemDesc);

                    $il->updateLine($dbh);

                }
           }
        }

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
            $lastDepart = setTimeZone(NULL, $actualDepart);
        } else {
            $lastDepart = setTimeZone(NULL, $estDepart);
        }

        $reply = ReservationSvcs::moveResvAway($dbh, $firstArrival, $lastDepart, $lastVisitRs->idResource->getStoredVal(), $uname);



        return 'Visit Moved. ' . $reply;
    }

    /**
     * Move the stays in a visit by delta days.
     *
     * @param array $stays
     * @param int $span
     * @param int $startDelta
     * @param int $endDelta
     */
    protected static function moveStaysDates($stays, $startDelta, $endDelta) {

        $startInterval = new \DateInterval('P' . abs($startDelta) . 'D');
        $endInterval = new \DateInterval('P' . abs($endDelta) . 'D');
        $today = new \DateTime();
        $today->setTime(10, 0, 0);

        foreach ($stays as $stayRS) {

            $firstArrival = new \DateTime($stayRS->Checkin_Date->getStoredVal());
            $expectedDeparture = new \DateTime($stayRS->Expected_Co_Date->getStoredVal());
            $newStartDT = new \DateTime($stayRS->Span_Start_Date->getStoredVal());

            if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn) {

                $newEndDt = setTimeZone(NULL, $stayRS->Expected_Co_Date->getStoredVal());
                $newEndDt->setTime(10,0,0);

                if ($newEndDt < $today) {
                    $newEndDt = $today;
                }

            } else {
                $newEndDt = setTimeZone(NULL, $stayRS->Span_End_Date->getStoredVal());
            }


            if ($endDelta < 0 || $startDelta < 0) {
                // Move back
                $newEndDt->sub($endInterval);
                $newStartDT->sub($startInterval);
                $expectedDeparture->sub($endInterval);
                $firstArrival->sub($startInterval);

            } else if (($endDelta > 0 || $startDelta == 0) && $stayRS->Status->getStoredVal() == VisitStatus::CheckedIn) {
                // Spring ahead if checked in
                $newEndDt->add($endInterval);
                $newStartDT->add($startInterval);
                $expectedDeparture->add($endInterval);
                $firstArrival->add($startInterval);

            } else {

                // Spring ahead

                $newStartDT->add($startInterval);

                $firstArrival->add($startInterval);

            }

            // Validity check
            $endDATE = new \DateTime($newEndDt->format('Y-m-d 00:00:00'));
            $startDATE = new \DateTime($newStartDT->format('Y-m-d 00:00:00'));
            if ($endDATE < $startDATE) {
                return "The stay End date comes before the Start date.  ";
            }

            $tday = new \DateTime($today->format('Y-m-d 00:00:00'));
            if ($stayRS->Status->getStoredVal() != VisitStatus::CheckedIn && $endDATE > $tday) {
                return "At least one guest, Id = " . $stayRS->idName->getStoredVal() . ", will have checked out into the future.  ";
            }

            $stayRS->Checkin_Date->setNewVal($firstArrival->format('Y-m-d H:i:s'));
            $stayRS->Span_Start_Date->setNewVal($newStartDT->format('Y-m-d H:i:s'));

            if ($stayRS->Span_End_Date->getStoredVal() != '') {
                $stayRS->Span_End_Date->setNewVal($newEndDt->format('Y-m-d H:i:s'));
            }

            if ($stayRS->Checkout_Date->getStoredVal() != '') {
                $stayRS->Checkout_Date->setNewVal($newEndDt->format('Y-m-d H:i:s'));
            }

            $stayRS->Expected_Co_Date->setNewVal($expectedDeparture->format('Y-m-d H:i:s'));

        }

        return '';
    }

        /**
     * Save the stays in a visit by delta days.
     *
     * @param PDO $dbh
     * @param array $stays
     * @param int $idRegistration
     * @param sring $uname
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
