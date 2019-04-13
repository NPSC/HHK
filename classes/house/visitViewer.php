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
        if ($action == 'cr') {
            return '';
        }

        $table = new HTMLTable();

        // Get labels
        $labels = new Config_Lite(LABEL_FILE);


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
    public static function createStaysMarkup(\PDO $dbh, $idResv, $idVisit, $span, $idPrimaryGuest, $isAdmin, $idGuest, Config_Lite $labels, $action = '', $coDate = '') {

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

                    if ($action == 'ref' && $coDate != '') {
                        $edDay = new \DateTime($coDate);
                    } else {
                        $edDay = new \DateTime(date('Y-m-d'));
                    }

                    $edDay->setTime(0, 0, 0);
                    $days = $edDay->diff($stDayDT, TRUE)->days;

                    $getCkOutDate = HTMLInput::generateMarkup($edDay->format('M j, Y'), array('id' => 'stayCkOutDate_' . $r['idName'], 'name' =>'[stayCkOutDate][' . $r['idName'] . ']', 'class' => 'ckdate hhk-ckoutDate', 'readonly'=>'readonly'));

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
        if ($uS->KeyDeposit && $r['Status'] == VisitStatus::CheckedIn && ($action == '' || $action == 'pf' || $action == 'ref') && $visitCharge->getDepositCharged() > 0 && ($visitCharge->getDepositPending() + $visitCharge->getKeyFeesPaid()) < $visitCharge->getDepositCharged()) {
            $includeKeyDep = TRUE;
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
        $lastDeparture = NULL;
        $visitCheckedIn = FALSE;

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
                $firstArrival = new \DateTime($vRs->Arrival_Date->getStoredVal());
            }

            if ($vRs->Status->getStoredVal() == VisitStatus::CheckedIn) {
                $visitCheckedIn = TRUE;
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
        $tonight->add(new DateInterval('P1D'));
        $tonight->setTime(0,0,0);

        $today = new \DateTime();
        $today->setTime(intval($uS->CheckOutTime), 0, 0);

        reset($spans);

        // change visit span dates
        foreach ($spans as $s => $vRs) {

            $spanStartDT = new \DateTime($vRs->Span_Start->getStoredVal());

            if ($vRs->Status->getStoredVal() == VisitStatus::CheckedIn) {

                $spanEndDt = new \DateTime($vRs->Expected_Departure->getStoredVal());
                $spanEndDt->setTime(intval($uS->CheckOutTime),0,0);

                if ($spanEndDt < $tonight) {
                    $spanEndDt = new \DateTime();
                    $spanEndDt->setTime(intval($uS->CheckOutTime), 0, 0);
                }

            } else {
                // Checked out
                $spanEndDt = new \DateTime($vRs->Span_End->getStoredVal());
            }


            // Cases: end and start both change identically => move; or end or start change => shrink/expand
            if ($endDelta < 0 || $startDelta < 0) {

                // Move back
                $spanEndDt->sub($endInterval);
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

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($rows) > 0) {
                // not available
                return 'The Date range is not available';
            }

            $visits[$s]['rs'] = $vRs;
            $visits[$s]['start'] = $spanStartDT;
            $visits[$s]['end'] = $spanEndDt;

            $stayMsg = self::moveStaysDates($stays[$vRs->Span->getStoredVal()], $startDelta, $endDelta, $spanStartDT, $spanEndDt);

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

                    $stDT = new \DateTime($il->getPeriodStart());
                    $edDT = new \DateTime($il->getPeriodEnd());

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
     * @param DateTime $spanEndDT
     */
    protected static function moveStaysDates($stays, $startDelta, $endDelta, \DateTime $spanStartDT, \DateTime $spanEndDT) {

        $uS = Session::getInstance();

        $startInterval = new \DateInterval('P' . abs($startDelta) . 'D');
        $endInterval = new \DateInterval('P' . abs($endDelta) . 'D');

        $tonight = new \DateTime();
        $tonight->add(new DateInterval('P1D'));
        $tonight->setTime(0,0,0);

        $today = new \DateTime();
        $today->setTime(intval($uS->CheckOutTime), 0, 0);

        foreach ($stays as $stayRS) {

            $checkInDT = new \DateTime($stayRS->Checkin_Date->getStoredVal());
            $stayStartDT = new \DateTime($stayRS->Span_Start_Date->getStoredVal());

            if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn) {

                $stayEndDt = new \DateTime($stayRS->Expected_Co_Date->getStoredVal());
                $stayEndDt->setTime(intval($uS->CheckOutTime),0,0);

                if ($stayEndDt < $tonight) {
                    $stayEndDt = new \DateTime();
                    $stayEndDt->setTime(intval($uS->CheckOutTime), 0, 0);
                }
            } else {
                $stayEndDt = new \DateTime($stayRS->Span_End_Date->getStoredVal());
            }


            if ($endDelta < 0 && $startDelta < 0) {
                // Move the entire stay back

                $stayStartDT->sub($startInterval);
                $checkInDT->sub($startInterval);
                $stayEndDt->sub($endInterval);

            } else if ($startDelta > 0 && $endDelta > 0) {
                // Move entire stay ahead

                $stayStartDT->add($startInterval);
                $checkInDT->add($startInterval);
                $stayEndDt->add($endInterval);

            } else if ($startDelta == 0) {
                // Manipulate end of visit

                $testDT = new \DateTime($stayEndDt->format('Y-m-d H:i:s'));

                if ($endDelta < 0) {
                    // Shrink

                    // Checked out to late
                    // Checked in to late
                    if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn) {
                        // Is my Start Date after the new ending date?

                    } else {

                    }
                } else if ($endDelta > 0) {
                    // Expand

                    if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn) {
                        $stayEndDt->add($endInterval);
                    } else {

                        $testDT->add($endInterval);

                        if ($spanEndDT->diff($testDT, TRUE)->days < 1) {
                            $stayEndDt->add($endInterval);
                        }
                    }
                }

            } else if ($endDelta == 0) {
                // Manipulate Start of visit

                if ($startDelta > 0) {
                    // Shrink
                } else if ($startDelta < 0) {
                    // Expand

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

            
            if ($stayRS->Span_End_Date->getStoredVal() != '') {
                $stayRS->Span_End_Date->setNewVal($stayEndDt->format('Y-m-d H:i:s'));
            }

            if ($stayRS->Checkout_Date->getStoredVal() != '') {
                $stayRS->Checkout_Date->setNewVal($stayEndDt->format('Y-m-d H:i:s'));
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
