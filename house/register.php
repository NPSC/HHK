<?php

use HHK\Exception\RuntimeException;
use HHK\History;
use HHK\House\GuestRegister;
use HHK\House\OperatingHours;
use HHK\House\Report\PaymentReport;
use HHK\House\Report\RoomReport;
use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLSelector};
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Payment\PaymentSvcs;
use HHK\sec\{SecurityComponent, Session, WebInit};
use HHK\sec\Labels;
use HHK\SysConst\GLTableNames;
use HHK\SysConst\ItemPriceCode;
use HHK\SysConst\ReservationStatus;
use HHK\SysConst\RoomRateCategories;
use HHK\US_Holidays;

/**
 * Register.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

$wInit = new webInit();

$dbh = $wInit->dbh;

// get session instance
$uS = Session::getInstance();

// Get labels
$labels = Labels::getLabels();

$isGuestAdmin = SecurityComponent::is_Authorized('guestadmin');

$paymentMarkup = '';
$receiptMarkup = '';
$statusSelector = '';
$payTypeSelector = '';
$resourceGroupBy = 'Type';
$defaultRegisterTab = 0;
$currentReservations = '';
$uncommittedReservations = '';
$waitlist = '';
$guestAddMessage = '';
$shoHosptialName = FALSE;
$colorKey = '';
$countUnpaidInvoices = '';
$rvCols = [];
$wlCols = [];



// Hosted payment return
try {

    if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $_REQUEST)) === FALSE) {

        $receiptMarkup = $payResult->getReceiptMarkup();

        //make receipt copy
        if($receiptMarkup != '' && $uS->merchantReceipt == true) {
            $receiptMarkup = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('div', $receiptMarkup.HTMLContainer::generateMarkup('div', 'Customer Copy', ['style' => 'text-align:center;']), ['style' => 'margin-right: 15px; width: 100%;'])
                .HTMLContainer::generateMarkup('div', $receiptMarkup.HTMLContainer::generateMarkup('div', 'Merchant Copy', ['style' => 'text-align: center']), ['style' => 'margin-left: 15px; width: 100%;'])
                ,
                ['style' => 'display: flex; min-width: 100%;', 'data-merchCopy' => '1']);
        }

        // Display a status message.
        if ($payResult->getDisplayMessage() != '') {
            $paymentMarkup = HTMLContainer::generateMarkup('p', $payResult->getDisplayMessage());
        }
    }

} catch (RuntimeException $ex) {
    $paymentMarkup = $ex->getMessage();
}


// Page Return
if (isset($_POST['btnDlCurGuests'])) {
    // Current guests
    $rows = History::getCheckedInGuestMarkup($dbh, '', FALSE);
    doExcelDownLoad($rows, 'CurrentGuests');
}
if (isset($_POST['btnDlConfRes'])) {
    // Confirmed Reservations
    $history = new History();
    $rows = $history->getReservedGuestsMarkup($dbh, ReservationStatus::Committed, FALSE, '', 1, TRUE);
    doExcelDownLoad($rows, 'ConfirmedResv');
}
if (isset($_POST['btnDlUcRes'])) {
    // Unconfirmed Reservations
    $history = new History();
    $rows = $history->getReservedGuestsMarkup($dbh, ReservationStatus::UnCommitted, FALSE, '', 1, TRUE);
    doExcelDownLoad($rows, 'UnconfirmedResv');
}
if (isset($_POST['btnDlWlist'])) {
    // Waitlist
    $history = new History();
    $rows = $history->getReservedGuestsMarkup($dbh, ReservationStatus::Waitlist, FALSE, '', 1, TRUE);
    doExcelDownLoad($rows, 'Waitlist');
}
if (isset($_POST['btnFeesDl'])) {
    // Dailey report
    PaymentReport::generateDayReport($dbh, $_POST);
}

//set defaultRegisterTab - use tab url param else default
if (isset($_GET["tab"])) {
    $tab = intval(filter_var($_GET["tab"], FILTER_SANITIZE_NUMBER_INT), 10);
    if ($tab < 5) {
        $defaultRegisterTab = $tab;
    }
}

if ($defaultRegisterTab == 0 && $uS->DefaultRegisterTab > 0 && $uS->DefaultRegisterTab < 5) {
    $defaultRegisterTab = $uS->DefaultRegisterTab;
}


// Guest add message
if (isset($_GET['gamess'])) {

    $contents = filter_var($_GET['gamess'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $guestAddMessage = HTMLContainer::generateMarkup('div', $contents, ['style' => 'clear:left;float:left; margin-top:5px;margin-bottom:5px;', 'class' => "hhk-alert ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox"]);

}

$locations = readGenLookupsPDO($dbh, 'Location');
$diags = readGenLookupsPDO($dbh, 'Diagnosis');



// Daily Log
$dailyLog = HTMLContainer::generateMarkup('h3', 'Daily Log'
    . HTMLInput::generateMarkup('Print', ['type' => 'button', 'id' => 'btnPrtDaily', 'style' => 'font-size:.8em;', 'class' => 'ml-5'])
        . HTMLInput::generateMarkup('Refresh', ['type' => 'button', 'id' => 'btnRefreshDaily', 'style' => 'font-size:.8em;', 'class' => 'ml-5'])
        ,
    ['style' => 'background-color:#D3D3D3;', 'class' => 'p-2'])
        . HTMLContainer::generateMarkup('div', "<table id='daily' class='display' style='width:100%;' cellpadding='0' cellspacing='0' border='0'></table>", ['id' => 'divdaily']);

// Currently Checked In guests
        $currentCheckedIn = HTMLContainer::generateMarkup('h3', '<span>Current '.$labels->getString('MemberType', 'visitor', 'Guest').'s</span>' . HTMLInput::generateMarkup('Excel Download', ['type' => 'submit', 'name' => 'btnDlCurGuests', 'class' => 'ml-5', 'style' => 'font-size:.9em;']) . ($uS->smsProvider ? HTMLContainer::generateMarkup('button', 'Text ' . $labels->getString('MemberType', 'visitor', 'Guest') . 's', ['role' => 'button', 'id' => "btnTextCurGuests", 'class' => 'ml-5', 'style' => 'font-size:.9em;']) :""), ['style' => 'background-color:#D3D3D3;', 'class' => 'p-2'])
        . HTMLContainer::generateMarkup('div', "<table id='curres' class='display' style='width:100%;' cellpadding='0' cellspacing='0' border='0'></table>", ['id' => 'divcurres']);

// make registration form print button
$regButton = HTMLContainer::generateMarkup('span', 'Check-in Date: ' . HTMLInput::generateMarkup('', ['id' => 'regckindate', 'class' => 'ckdate hhk-prtRegForm ml-2 mr-3'])
        . HTMLInput::generateMarkup('Print Default Registration Forms', ['id' => 'btnPrintRegForm', 'type' => 'button', 'data-page' => 'PrtRegForm.php', 'class' => 'hhk-prtRegForm mt-3 mt-md-0', 'style' => 'font-size:0.86em;'])
        ,
    ['style' => 'padding:9px;border:solid 1px #62A0CE;background-color:#E8E5E5; align-items:baseline;', "class" => "hhk-flex hhk-flex-wrap my-3 my-lg-0 ml-lg-5"]);

$currentReservations = HTMLContainer::generateMarkup('h3',
        '<span>' . $labels->getString('register', 'reservationTab', 'Confirmed Reservations') . '</span>' .
        HTMLInput::generateMarkup('Excel Download', ['type' => 'submit', 'name' => 'btnDlConfRes', 'style' => 'font-size:.9em;', 'class' => "ml-5"]) . ($uS->smsProvider ? HTMLContainer::generateMarkup('button', 'Text ' . $labels->getString('MemberType', 'visitor', 'Guest') . 's', ['role' => 'button', 'id' => "btnTextConfResvGuests", 'class' => 'ml-5', 'style' => 'font-size:.9em;']): "") . $regButton
        ,
    ['style' => 'background-color:#D3D3D3; align-items:baseline;', "class" => "hhk-flex hhk-flex-wrap p-3"])
        . HTMLContainer::generateMarkup('div', "<table id='reservs' class='display' style='width:100%; 'cellpadding='0' cellspacing='0' border='0'></table>", ['id' => 'divreservs']);

if ($uS->ShowUncfrmdStatusTab) {
    $uncommittedReservations = HTMLContainer::generateMarkup('h3', '<span>' . $labels->getString('register', 'unconfirmedTab', 'UnConfirmed Reservations') . '</span>' . HTMLInput::generateMarkup('Excel Download', ['type' => 'submit', 'name' => 'btnDlUcRes', 'style' => 'font-size:.9em;', 'class' => 'ml-5']) . ($uS->smsProvider ? HTMLContainer::generateMarkup('button', 'Text ' . $labels->getString('MemberType', 'visitor', 'Guest') . 's', ['role' => 'button', 'id' => "btnTextUnConfResvGuests", 'class' => 'ml-5', 'style' => 'font-size:.9em;']): ""), ['style' => 'background-color:#D3D3D3;', 'class' => 'p-2'])
        . HTMLContainer::generateMarkup('div', "<table id='unreserv' class='display' style='width:100%;'cellpadding='0' cellspacing='0' border='0'></table>", ['id' => 'divunreserv']);
}


// make waitlist print button
//$wlButton = HTMLContainer::generateMarkup('span', 'Date: ' . HTMLInput::generateMarkup(date('M j, Y'), array('id'=>'regwldate', 'class'=>'ckdate hhk-prtWL ml-2 mr-3'))
//        . HTMLInput::generateMarkup('Print Wait List', array('id'=>'btnPrintWL', 'type'=>'button', 'data-page'=>'PrtWaitList.php', 'class'=>'hhk-prtWL mt-3 mt-md-0', 'style'=>'font-size:.85em;'))
//        , array('style'=>'padding:9px;border:solid 1px #62A0CE;background-color:#E8E5E5; align-items:baseline;', "class"=>"hhk-flex hhk-flex-wrap my-3 my-md-0 ml-md-5"));


$waitlist = HTMLContainer::generateMarkup('h3', '<span>' . $labels->getString('register', 'waitlistTab', 'Wait List') . '</span>' .
        HTMLInput::generateMarkup('Excel Download', ['type' => 'submit', 'name' => 'btnDlWlist', 'style' => 'font-size:.9em;', "class" => "ml-5"]) . ($uS->smsProvider ? HTMLContainer::generateMarkup('button', 'Text ' . $labels->getString('MemberType', 'visitor', 'Guest') . 's', ['role' => 'button', 'id' => "btnTextWaitlistGuests", 'class' => 'ml-5', 'style' => 'font-size:.9em;']): "")
        //.$wlButton
        ,
    ['style' => 'background-color:#D3D3D3; align-items:baseline;', 'class' => 'hhk-flex hhk-flex-wrap p-2'])
        . HTMLContainer::generateMarkup('div', "<table id='waitlist' class='display' style='width:100%;'cellpadding='0' cellspacing='0' border='0'></table>", ['id' => 'divwaitlist']);


// Hospital Selector
$stmth = $dbh->query("Select idHospital, Title, Reservation_Style, Stay_Style from hospital where Status = 'a' and Title != '(None)' and Hide = 0 order by Title asc");

if ($stmth->rowCount() > 1) {
    $shoHosptialName = TRUE;
}

if ($stmth->rowCount() > 1 && (strtolower($uS->RibbonBottomColor) == 'hospital' || strtolower($uS->RibbonColor) == 'hospital')) {

    $hospLabel = HTMLContainer::generateMarkup('span', $labels->getString('hospital', 'hospital', 'Hospital') . ': ');

    $leftArrow = '<div class="d-md-none d-flex" style="align-items:center"><span class="ui-icon ui-icon-triangle-1-w"></span></div>';
    $rightArrow = '<div class="d-md-none d-flex" style="align-items:center"><span class="ui-icon ui-icon-triangle-1-e"></span></div>';

    // All button
    $colorKey = HTMLContainer::generateMarkup('button', 'All', ['class' => 'btnHosp hospActive', 'data-id' => 0]);

    while ($r = $stmth->fetch(\PDO::FETCH_ASSOC)) {

    	if (strtolower($r['Reservation_Style']) != 'transparent') {

	        $attrs = ['class' => 'btnHosp', 'data-id' => $r['idHospital']];
	        $attrs['style'] = '';
	        if($r['Reservation_Style'] != ''){
	            $attrs['style'] .= 'background-color:' . $r['Reservation_Style'] . ';';
	        }else if($uS->DefaultCalEventColor){
	            $attrs['style'] .= 'background-color: ' . $uS->DefaultCalEventColor . ';';
	        }else{
	            $attrs['style'] .= 'background-color: var(--fc-event-bg-color,#3788d8);';
	        }
	        if($r['Stay_Style'] != ''){
	            $attrs['style'] .= 'color:' . $r['Stay_Style'] . ';';
	        }else if($uS->DefCalEventTextColor){
	            $attrs['style'] .= 'color:' . $uS->DefCalEventTextColor . ';';
	        }

	        $colorKey .= HTMLContainer::generateMarkup('button', $r['Title'], $attrs);
    	}
    }

    $colorKey = HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup("div", $leftArrow . HTMLContainer::generateMarkup("div", $colorKey, ["id" => "hospBtns"]) . $rightArrow, ["class" => "d-flex"]), ["id" => "hospBtnWrapper"]);
}


// Calendar View density
$weeks = intval($uS->CalViewWeeks);
if ($weeks < 1) {
    $weeks = 1;
} else if ($weeks > 3) {
    $weeks = 4;
}

$defaultView = 'timeline' . $weeks . 'weeks';

// Calendar date increment for date navigation controls.
$calDateIncrement = intval($uS->CalDateIncrement);

// show holidays
$holidays = [];
if ($uS->Show_Holidays) {

    $year = date('Y');
    // List holidays for three years, past, now, next year
    for ($i = $year - 1; $i < $year+2; $i++) {

        $hol = new US_Holidays($dbh, $i);
        $list = $hol->get_list();

        foreach($list as $h){
            if ($h['use'] == 1) {
                $date1 = \DateTime::createFromFormat('U', (string)$h['timestamp']);
                $holidays[] = $date1->format('Y') . '-' . $date1->format('n') . '-' . $date1->format('j');
            }
        }
    }
}

//show closed days
$closedDays = [];
if($uS->Show_Closed){
    $operatingHours = new OperatingHours($dbh);
    $closedDays = $operatingHours->getClosedDays();
}
//Resource grouping controls
$rescGroups = readGenLookupsPDO($dbh, 'Room_Group');

if (isset($rescGroups[$uS->CalResourceGroupBy])) {
    $resourceGroupBy = $uS->CalResourceGroupBy;
} else {
    $resourceGroupBy = '';
}

$rescGroupSel = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($rescGroups), $resourceGroupBy, FALSE), ['id' => 'selRoomGroupScheme']);

$showCharges = TRUE;
$addnl = readGenLookupsPDO($dbh, 'Addnl_Charge');

// decide to show payments and invoices
if ($uS->RoomPriceModel == ItemPriceCode::None && count($addnl) == 0 && $uS->VisitFee == FALSE && $uS->KeyDeposit == FALSE) {
    $showCharges = FALSE;

} else {

    // Prepare controls
    $statusList = readGenLookupsPDO($dbh, 'Payment_Status');
    $statusSelector = HTMLSelector::generateMarkup(
            HTMLSelector::doOptionsMkup($statusList, ''),
        ['name' => 'selPayStatus[]', 'id' => 'selPayStatus', 'size' => '6', 'multiple' => 'multiple']);

    $payTypes = [];

    foreach ($uS->nameLookups[GLTableNames::PayType] as $p) {
        if ($p[2] != '') {
            $payTypes[$p[2]] = [$p[2], $p[1]];
        }
    }

    $payTypeSelector = HTMLSelector::generateMarkup(
            HTMLSelector::doOptionsMkup($payTypes, ''),
        ['name' => 'selPayType[]', 'id' => 'selPayType', 'size' => '4', 'multiple' => 'multiple']);

    // Count unpaid invoices

}

$showRateCol = FALSE;
if ($uS->RoomPriceModel != ItemPriceCode::None) {
    $showRateCol = TRUE;
}


if ($uS->UseWLnotes) {
    $showWlNotes = TRUE;
} else {
    $showWlNotes = FALSE;
}

$referralStatuses = "";
if($uS->useOnlineReferral){
    $referralStatuses = json_encode(readGenLookupsPDO($dbh, 'Referral_Form_Status', 'Order'));
}


?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $wInit->pageTitle; ?></title>
        <meta http-equiv="x-ua-compatible" content="IE=edge">
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <?php echo BOOTSTRAP_ICONS_CSS; ?>
        <?php echo CSSVARS; ?>

		<script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
		<script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo FULLCALENDAR_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_SETTINGS ?>"></script>
        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo ADDR_PREFS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BUFFER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTES_VIEWER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VISIT_DIALOG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo REFERRAL_VIEWER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo SMS_DIALOG_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo INVOICE_JS; ?>"></script>
        <?php if ($uS->PaymentGateway == AbstractPaymentGateway::INSTAMED) {echo INS_EMBED_JS;} ?>
        <?php if ($uS->PaymentGateway == AbstractPaymentGateway::DELUXE) {echo DELUXE_EMBED_JS;} ?>

        <style>
            .hhk-justify-r {
                text-align: right;
            }
            .hhk-justify-c {
                text-align: center;
            }
            .fc .fc-toolbar.fc-header-toolbar {
                margin-bottom: 1em;
                font-size: .9em;
            }
            .hhk-fc-slot-title {
                background-color: #E3EFF9;
            }
            .hhk-fc-slot-title.fc-day-today {
                background-color: #fbec88;
            }
            .hhk-fcslot-today {
                background-color: #fbec88;
                opacity: .6;
            }
            .hhk-fcslot-holiday {
                background-color: #dbfcb5;
                opacity: .6;
            }
            .hhk-fcslot-closed {
                background-color: #fcb5b5;
                opacity: .6;
            }

        </style>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";}?> >
        <?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <div>
            <form autocomplete="off">
                <h2 class="hhk-flex" id="page-title-row">
                	<span class="mb-3 mb-md-0"><?php echo $wInit->pageHeading;?></span>
                	<?php echo RoomReport::getGlobalNightsCounter($dbh, $uS->PreviousNights) . RoomReport::getGlobalStaysCounter($dbh) . RoomReport::getGlobalRoomOccupancy($dbh); ?>
                	<span id="name-search" class="d-none d-md-inline">Name Search:
                    	<input type="search" class="allSearch" id="txtsearch" autocomplete='off' size="20" title="Enter at least 3 characters to invoke search" />
                	</span>
                </h2>
                </form>
            </div>

            <div id="hhk-loading-spinner" style="width: 100%; height: 100%; margin-top: 100px; text-align: center"><img src="../images/ui-anim_basic_16x16.gif"><p>Loading...</p></div>

            <?php echo $guestAddMessage; ?>
            <div id="paymentMessage" style="display:none;" class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox my-2"></div>
            <form name="frmdownload" action="#" method="post">
            <div id="mainTabs" style="display:none; font-size:.9em; width: 100%;" class="hhk-mobile-tabs">
            	<div class="hhk-flex ui-widget-header ui-corner-all">
            		<div class="d-xl-none d-flex align-items-center"><span class="ui-icon ui-icon-triangle-1-w"></span></div>
                    <ul class="hhk-flex">
                        <li id="liCal"><a href="#vcal">Calendar</a></li>
                        <li id="liCurrGuests"><a href="#vstays"><span id="spnNumCurrent"></span> Current <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?><span id="spnCurrentS"></span></a></li>
                        <li><a href="#vresvs"><span id="spnNumConfirmed"></span> <?php echo $labels->getString('register', 'reservationTab', 'Confirmed Reservations'); ?></a></li>
                        <?php if ($uS->ShowUncfrmdStatusTab) { ?>
                            <li><a href="#vuncon"><span id="spnNumUnconfirmed"></span> <?php echo $labels->getString('register', 'unconfirmedTab', 'UnConfirmed Reservations'); ?></a></li>
                        <?php } ?>
                        <li><a href="#vwls"><span id="spnNumWaitlist"></span> <?php echo $labels->getString('register', 'waitlistTab', 'Wait Listed'); ?></a></li>
                        <?php if($uS->useOnlineReferral){ ?>
                            <li><a href="#vreferrals"><span id="spnNumReferral"></span> <?php echo $labels->getString('register', 'onlineReferralTab', 'Referrals'); ?> </a></li>
                        <?php }
                         if ($isGuestAdmin && $showCharges) { ?>
                            <li><a href="#vfees"><?php echo $labels->getString('register', 'recentPayTab', 'Recent Payments'); ?></a></li>
                            <li id="liInvoice"><a href="#vInv">Unpaid Invoices</a></li>
                        <?php } ?>
                        <li id="liDaylog"><a href="#vdaily">Daily Log</a></li>
                        <li id="liStaffNotes"><a href="#vStaffNotes">Staff Notes</a></li>
                    </ul>
                    <div class="d-xl-none d-flex align-items-center"><span class="ui-icon ui-icon-triangle-1-e"></span></div>
                </div>
                <div id="vcal" style="clear:left; padding: .6em 1em; display:none;">
                    <?php echo $colorKey; ?>
                    <div id="divGoto" class="hideMobile" style="display: none;">
                        <span id="spnGotoDate" >Go to Date: <input id="txtGotoDate" type="text" class="ckdate" value="" /></span>
                        <span id="pCalLoad" style="text-align:center;"><img src="../images/ui-anim_basic_16x16.gif"></span>
                    </div>
                    <div id="divRoomGrouping" style="position:absolute; padding: 1.2em; display:none;" class="ui-widget ui-front ui-widget-content ui-corner-all ui-widget-shadow">
                        <table>
                            <tr>
                                <td>Select Room Grouping scheme: </td>
                                <td><?php echo $rescGroupSel; ?></td>
                            </tr>
                        </table>
                    </div>
                    <p style="color:red; font-size: large; display:none;" id="pCalError"></p>
                    <div id="calendar" style="margin-top:5px;"></div>
                </div>
                <div id="vstays" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none; ">
                    <?php echo $currentCheckedIn; ?>
                </div>
                <?php if ($uS->ShowUncfrmdStatusTab) { ?>
                <div id="vuncon" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none; ">
                    <?php echo $uncommittedReservations; ?>
                </div>
                <?php } ?>
                <div id="vresvs" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none; ">
                    <?php echo $currentReservations; ?>
                </div>
                <div id="vdaily" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none; ">
                    <?php echo $dailyLog; ?>
                </div>
                <div id="vwls" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none; ">
                    <?php echo $waitlist; ?>
                </div>
                <?php if($uS->useOnlineReferral){ ?>
                <div id="vreferrals" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none;">
                </div>
                <?php } ?>
                <?php if ($isGuestAdmin) { ?>
                <div id="vfees" class="hhk-tdbox hhk-visitdialog" style="display:none; ">
                    <table>
                        <tr>
                            <th>Date Range</th>
                            <th>Status</th>
                            <th>Pay Type</th>
                        </tr><tr>
                            <td>Starting: <input type="text" id="txtfeestart" name="stDate" class="ckdate" value="" /></td>
                            <td rowspan="2"><?php echo $statusSelector; ?></td>
                            <td rowspan="2"><?php echo $payTypeSelector; ?></td>
                        </tr><tr>
                            <td>Ending: <input type="text" id="txtfeeend" name="enDate" class="ckdate" value="" /></td>

                        </tr>
                        <tr>
                            <td><label for="fcbdinv">Show Deleted Invoices </label><input type='checkbox' id='fcbdinv' name="fcbdinv"/></td>
                            <td colspan="2" style="text-align:right;"><input type="submit" name="btnFeesDl" value="Excel Download" style="margin-right:20px;"/><input type="button" id="btnFeesGo" value="Run"/></td>
                        </tr>
                    </table>
                    <div id="rptfeediv" class="hhk-visitdialog"><p id="rptFeeLoading" class="ui-state-active" style="font-size: 1.1em; float:left; display:none; margin:20px; padding: 5px;">Loading Payment Report...</p></div>
                </div>
                <div id="vInv" class="hhk-tdbox hhk-visitdialog" style="display:none;">
                    <input type="button" id="btnInvGo" value="Refresh"/>
                      <div id="rptInvdiv" class="hhk-visitdialog"></div>
                </div>
                <?php } ?>
                <div id="vStaffNotes" class="hhk-tdbox hhk-visitdialog" style="display: none">
                	<div class="staffNotesDiv" class="hhk-visitdialog"></div>
                </div>
            </div>
        </form>
        </div>  <!-- div id="contentDiv"-->
        <div id="keysfees" style="font-size: .9em;"></div>
        <div id="setBillDate" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size: .9em;">
            <span class="ui-helper-hidden-accessible"><input type="text"/></span>
            <table style="width: 100%"><tr>
                    <td class="tdlabel">Invoice Number:</td>
                    <td><span id="spnInvNumber"></span></td>
                </tr><tr>
                    <td class="tdlabel">Payor:</td>
                    <td><span id="spnBillPayor"></span></td>
                </tr><tr>
                    <td class="tdlabel">Bill Date:</td>
                    <td><input id="txtBillDate" value="" readonly="readonly" /></td>
                </tr><tr>
                    <td colspan="2"><textarea rows="2" style="width: 100%" id="taBillNotes" ></textarea></td>
                </tr>
            </table>
        </div>

        <div class="gmenu"></div>

        <form name="xform" id="xform" method="post"></form>

        <div id="faDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.8em;"></div>
        <div id="cardonfile" style="font-size: .9em; display:none;"></div>
        <div id="statEvents" class="hhk-tdbox hhk-visitdialog" style="font-size: .9em; display:none;"></div>
        <div id="pmtRcpt" style="font-size: .9em; display:none;"></div>
        <div id="chgRoomDialog" style="font-size: .9em; display:none;"></div>
        <div id="hsDialog" class="hhk-tdbox hhk-visitdialog hhk-hsdialog" style="display:none;font-size:.8em;"></div>

        <input  type="hidden" id="isGuestAdmin" value='<?php echo $isGuestAdmin; ?>' />
        <input  type="hidden" id="pmtMkup" value='<?php echo $paymentMarkup; ?>' />
        <input  type="hidden" id="rctMkup" value='<?php echo $receiptMarkup; ?>' />
        <input  type="hidden" id="defaultTab" value='<?php echo $defaultRegisterTab; ?>' />
        <input  type="hidden" id="patientLabel" value='<?php echo $labels->getString('MemberType', 'patient', 'Patient'); ?>' />
        <input  type="hidden" id="guestLabel" value='<?php echo $labels->getString('MemberType', 'guest', 'Guest'); ?>' />
        <input  type="hidden" id="visitorLabel" value='<?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>' />
        <input  type="hidden" id="referralFormTitleLabel" value='<?php echo $labels->getString('register', 'onlineReferralTitle', "Referral"); ?>' />
        <input  type="hidden" id="useOnlineReferral" value='<?php echo $uS->useOnlineReferral; ?>' />
        <input  type="hidden" id="reservationLabel" value='<?php echo $labels->getString('GuestEdit', 'reservationTitle'); ?>' />
        <input  type="hidden" id="reservationTabLabel" value='<?php echo $labels->getString('register', 'reservationTab', 'Confirmed Reservations'); ?>' />
        <input  type="hidden" id="unconfirmedResvTabLabel" value='<?php echo $labels->getString('register', 'unconfirmedTab', 'UnConfirmed Reservations'); ?>' />
        <input  type="hidden" id="calDateIncrement" value='<?php echo $calDateIncrement; ?>' />
        <input  type="hidden" id="dateFormat" value='<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>' />
        <input  type="hidden" id="fixedRate" value='<?php echo RoomRateCategories::Fixed_Rate_Category; ?>' />
        <input  type="hidden" id="resvPageName" value='<?php echo 'Reserve.php'; ?>' />
        <input  type="hidden" id="showCreatedDate" value='<?php echo $uS->ShowCreatedDate; ?>' />
        <input  type="hidden" id="shoHospitalName" value='<?php echo $shoHosptialName; ?>' />
        <input  type="hidden" id="showRateCol" value='<?php echo $showRateCol; ?>' />
        <input  type="hidden" id="hospTitle" value='<?php echo $labels->getString('hospital', 'hospital', 'Hospital'); ?>' />
        <input  type="hidden" id="showDiags" value='<?php if (count($diags) > 0) {echo TRUE;} else {echo FALSE;} ?>' />
        <input  type="hidden" id="showLocs" value='<?php if (count($locations) > 0) {echo TRUE;} else {echo FALSE;} ?>' />
        <input  type="hidden" id="locationTitle" value='<?php echo $labels->getString('hospital', 'location', 'Location'); ?>' />
        <input  type="hidden" id="diagnosisTitle" value='<?php echo $labels->getString('hospital', 'diagnosis', 'Diagnosis'); ?>' />
        <input  type="hidden" id="showWlNotes" value='<?php echo $showWlNotes ?>' />
        <input  type="hidden" id="wlTitle" value='<?php echo $labels->getString('referral', 'waitlistNotesLabel', 'WL Notes'); ?>' />
        <input  type="hidden" id="showCharges" value='<?php echo $showCharges ?>' />
        <input  type="hidden" id="acceptResvPay" value='<?php echo $uS->AcceptResvPaymt; ?>' />
        <input  type="hidden" id="defaultEventColor" value='<?php echo $uS->DefaultCalEventColor; ?>' />
        <input  type="hidden" id="defCalEventTextColor" value='<?php echo $uS->DefCalEventTextColor; ?>' />
        <input  type="hidden" id="resourceGroupBy" value='<?php echo $resourceGroupBy; ?>' />
        <input  type="hidden" id="resourceColumnWidth" value='<?php echo $uS->CalRescColWidth; ?>' />
        <input  type="hidden" id="defaultView" value='<?php echo $defaultView; ?>' />
        <input  type="hidden" id="expandResources" value='<?php echo $uS->CalExpandResources; ?>' />
        <input  type="hidden" id="staffNoteCats" value='<?php echo json_encode(readGenLookupsPDO($dbh, 'Staff_Note_Category', 'Order')); ?>' />
        <input  type="hidden" id="holidays" value='<?php echo json_encode($holidays); ?>' />
        <input type="hidden" id="closedDays" value='<?php echo json_encode($closedDays); ?>' />
		<input  type="hidden" id="showCurrentGuestPhotos" value='<?php echo ($uS->showCurrentGuestPhotos && $uS->ShowGuestPhoto); ?>' />

		<script type="text/javascript" src="<?php echo RESV_MANAGER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo REGISTER_JS; ?>"></script>

    </body>
</html>
