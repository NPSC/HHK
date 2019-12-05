<?php
/**
 * Register.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

require (CLASSES . 'History.php');
require (DB_TABLES . 'PaymentsRS.php');
require (DB_TABLES . 'visitRS.php');
require (DB_TABLES . 'PaymentGwRS.php');
require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'ActivityRS.php');

require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (MEMBER . 'OrgMember.php');
require (MEMBER . "Addresses.php");
require (MEMBER . "EmergencyContact.php");

require (PMT . 'GatewayConnect.php');
require (PMT . 'PaymentGateway.php');
require (PMT . 'PaymentResponse.php');
require (PMT . 'PaymentResult.php');
require (PMT . 'Receipt.php');
require (PMT . 'Invoice.php');
require (PMT . 'InvoiceLine.php');
require (PMT . 'CheckTX.php');
require (PMT . 'CashTX.php');
require (PMT . 'Transaction.php');
require (PMT . 'CreditToken.php');

require (CLASSES . 'PaymentSvcs.php');
require (CLASSES . 'Purchase/RoomRate.php');
require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';
require CLASSES . 'TableLog.php';

require (HOUSE . 'Room.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'ResourceView.php');

require (HOUSE . 'VisitLog.php');
require (HOUSE . 'RoomLog.php');
require (HOUSE . 'RoomReport.php');

require (CLASSES . 'CreateMarkupFromDB.php');
require (CLASSES . 'Notes.php');
require(SEC . 'ChallengeGenerator.php');


$wInit = new webInit();

$dbh = $wInit->dbh;

// get session instance
$uS = Session::getInstance();

creditIncludes($uS->PaymentGateway);

$totalRest = $uS->PreviousNights;

// Get labels
$labels = new Config_Lite(LABEL_FILE);

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

$rvCols = array();
$wlCols = array();


if ($uS->DefaultRegisterTab > 0 && $uS->DefaultRegisterTab < 5) {
    $defaultRegisterTab = $uS->DefaultRegisterTab;
}

// Hosted payment return
try {

    if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $uS->ccgw, $_REQUEST)) === FALSE) {

        $receiptMarkup = $payResult->getReceiptMarkup();

        if ($payResult->getDisplayMessage() != '') {
            $paymentMarkup = HTMLContainer::generateMarkup('p', $payResult->getDisplayMessage());
        }
    }

} catch (Hk_Exception_Runtime $ex) {
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
    require (HOUSE . 'PaymentReport.php');
    PaymentReport::generateDayReport($dbh, $_POST);
}

if (isset($_GET['gamess'])) {

    $contents = filter_var($_GET['gamess'], FILTER_SANITIZE_STRING);

    $guestAddMessage = HTMLContainer::generateMarkup('div', $contents, array('style'=>'clear:left;float:left; margin-top:5px;margin-bottom:5px;', 'class'=>"hhk-alert ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox"));

}

$locations = readGenLookupsPDO($dbh, 'Location');
$diags = readGenLookupsPDO($dbh, 'Diagnosis');



// Daily Log
$dailyLog = HTMLContainer::generateMarkup('h3', 'Daily Log'
        . HTMLInput::generateMarkup('Print', array('type'=>'button', 'id'=>'btnPrtDaily', 'style'=>'margin-left:5em;font-size:.8em;'))
        . HTMLInput::generateMarkup('Refresh', array('type'=>'button', 'id'=>'btnRefreshDaily', 'style'=>'margin-left:5em;font-size:.8em;'))
        , array('style' => 'background-color:#D3D3D3; padding:10px;'))
        . HTMLContainer::generateMarkup('div', "<table id='daily' class='display' style='width:100%;' cellpadding='0' cellspacing='0' border='0'></table>", array('id' => 'divdaily'));

// Currently Checked In guests
$currentCheckedIn = HTMLContainer::generateMarkup('h3', 'Current Guests' . HTMLInput::generateMarkup('Excel Download', array('type'=>'submit', 'name'=>'btnDlCurGuests', 'style'=>'margin-left:5em;font-size:.9em;')), array('style' => 'background-color:#D3D3D3; padding:10px;'))
        . HTMLContainer::generateMarkup('div', "<table id='curres' class='display' style='width:100%;' cellpadding='0' cellspacing='0' border='0'></table>", array('id' => 'divcurres'));

// make registration form print button
$regButton = HTMLContainer::generateMarkup('span', 'Check-in Date: ' . HTMLInput::generateMarkup('', array('id'=>'regckindate', 'class'=>'ckdate hhk-prtRegForm'))
        . HTMLInput::generateMarkup('Print Registration Forms', array('id'=>'btnPrintRegForm', 'type'=>'button', 'data-page'=>'PrtRegForm.php', 'class'=>'hhk-prtRegForm', 'style'=>'margin-left:.3em; font-size:0.86em;'))
        , array('style'=>'margin-left:5em;padding:9px;border:solid 1px #62A0CE;background-color:#E8E5E5'));

$currentReservations = HTMLContainer::generateMarkup('h3',
        $labels->getString('register', 'reservationTab', 'Confirmed Reservations') .
        HTMLInput::generateMarkup('Excel Download', array('type'=>'submit', 'name'=>'btnDlConfRes', 'style'=>'margin-left:5em;font-size:.9em;')) . $regButton
        , array('style' => 'background-color:#D3D3D3; padding:10px;'))
        . HTMLContainer::generateMarkup('div', "<table id='reservs' class='display' style='width:100%; 'cellpadding='0' cellspacing='0' border='0'></table>", array('id' => 'divreservs'));

if ($uS->ShowUncfrmdStatusTab) {
    $uncommittedReservations = HTMLContainer::generateMarkup('h3', $labels->getString('register', 'unconfirmedTab', 'UnConfirmed Reservations') . HTMLInput::generateMarkup('Excel Download', array('type'=>'submit', 'name'=>'btnDlUcRes', 'style'=>'margin-left:5em;font-size:.9em;')), array('style' => 'background-color:#D3D3D3; padding:10px;'))
        . HTMLContainer::generateMarkup('div', "<table id='unreserv' class='display' style='width:100%;'cellpadding='0' cellspacing='0' border='0'></table>", array('id' => 'divunreserv'));
}


// make waitlist print button
$wlButton = HTMLContainer::generateMarkup('span', 'Date: ' . HTMLInput::generateMarkup(date('M j, Y'), array('id'=>'regwldate', 'class'=>'ckdate hhk-prtWL'))
        . HTMLInput::generateMarkup('Print Wait List', array('id'=>'btnPrintWL', 'type'=>'button', 'data-page'=>'PrtWaitList.php', 'class'=>'hhk-prtWL', 'style'=>'margin-left:.3em;font-size:.85em;'))
        , array('style'=>'margin-left:5em;padding:9px;border:solid 1px #62A0CE;background-color:#E8E5E5'));


$waitlist = HTMLContainer::generateMarkup('h3', 'Waitlist' .
        HTMLInput::generateMarkup('Excel Download', array('type'=>'submit', 'name'=>'btnDlWlist', 'style'=>'margin-left:5em;font-size:.9em;'))
        .$wlButton
        , array('style' => 'background-color:#D3D3D3; padding:10px;'))
        . HTMLContainer::generateMarkup('div', "<table id='waitlist' class='display' style='width:100%;'cellpadding='0' cellspacing='0' border='0'></table>", array('id' => 'divwaitlist'));


// Hospital Selector
$shoHosptialName = FALSE;
$colorKey = '';
$stmth = $dbh->query("Select idHospital, Title, Reservation_Style, Stay_Style from hospital where Status = 'a' and Title != '(None)'");

if ($stmth->rowCount() > 1 && (strtolower($uS->RegColors) == 'hospital' || (strtolower($uS->GuestNameColor) == 'hospital'))) {

    $shoHosptialName = TRUE;

    $colorKey = HTMLContainer::generateMarkup('span', $labels->getString('hospital', 'hospital', 'Hospital') . ': ');
    // All button
    $colorKey .= HTMLContainer::generateMarkup('span', 'All', array('class'=>'spnHosp', 'data-id'=>0, 'style' => 'border:solid 3px black;font-size:120%;background-color:fff;color:000;'));

    while ($r = $stmth->fetch(\PDO::FETCH_ASSOC)) {

        $attrs = array('class'=>'spnHosp', 'data-id'=>$r['idHospital']);
        $attrs['style'] = 'background-color:' . $r['Reservation_Style'] . ';color:' . $r['Stay_Style'] . ';';

        $colorKey .= HTMLContainer::generateMarkup('span', $r['Title'], $attrs);
    }
}


// View density
$weeks = intval($uS->CalViewWeeks);
if ($weeks < 1) {
    $weeks = 1;
} else if ($weeks > 3) {
    $weeks = 4;
}

$defaultView = 'timeline' . $weeks . 'weeks';

// Calendar date increment for date navigation controls.
$calDateIncrement = intval($uS->CalDateIncrement);

//Resource grouping controls
$rescGroups = readGenLookupsPDO($dbh, 'Room_Group');

if (isset($rescGroups[$uS->CalResourceGroupBy])) {
    $resourceGroupBy = $uS->CalResourceGroupBy;
} else {
    $resourceGroupBy = reset($rescGroups)[0];
}

$rescGroupSel = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($rescGroups), $resourceGroupBy, FALSE), array('id'=>'selRoomGroupScheme'));


// instantiate a ChallengeGenerator object
try {
    $chlgen = new ChallengeGenerator();
    $challengeVar = $chlgen->getChallengeVar("challenge");
} catch (Exception $e) {
    //
}

$showCharges = TRUE;
$addnl = readGenLookupsPDO($dbh, 'Addnl_Charge');

// decide to show payments and invoices
if ($uS->RoomPriceModel == ItemPriceCode::None && count($addnl) == 0) {
    $showCharges = FALSE;

} else {

    // Prepare controls
    $statusList = readGenLookupsPDO($dbh, 'Payment_Status');
    $statusSelector = HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup($statusList, ''), array('name' => 'selPayStatus[]', 'id' => 'selPayStatus', 'size' => '6', 'multiple' => 'multiple'));

    $payTypes = array();

    foreach ($uS->nameLookups[GL_TableNames::PayType] as $p) {
        if ($p[2] != '') {
            $payTypes[$p[2]] = array($p[2], $p[1]);
        }
    }

    $payTypeSelector = HTMLSelector::generateMarkup(
                    HTMLSelector::doOptionsMkup($payTypes, ''), array('name' => 'selPayType[]', 'id' => 'selPayType', 'size' => '4', 'multiple' => 'multiple'));

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

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $wInit->pageTitle; ?></title>
        <meta http-equiv="x-ua-compatible" content="IE=edge">
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <link href='css/fullcalendar.min.css'  rel='stylesheet' type='text/css' />
        <link href='css/scheduler.min.css'  rel='stylesheet' type='text/css' />
        <?php echo NOTY_CSS; ?>
        <?php echo FAVICON; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="js/fullcalendar.min.js"></script>
        <script type="text/javascript" src="../js/hhk-scheduler.min.js"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo ADDR_PREFS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTES_VIEWER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VISIT_DIALOG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MD5_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo INVOICE_JS; ?>"></script>
        <?php if ($uS->PaymentGateway == PaymentGateway::INSTAMED) {echo INS_EMBED_JS;} ?>

        <style>
.hhk-justify-r {
    text-align: right;
}
.hhk-justify-c {
    text-align: center;
}
.ui-menu-item-wrapper {min-width: 130px;}
.fc-bgevent {opacity: .9;}
.hhk-fc-title::after {
    /* generic arrow */
    content: "";
    position: absolute;
    top: 50%;
    margin-top: -5px;
    border: 5px solid #000;
    border-top-color: transparent;
    border-bottom-color: transparent;
    opacity: .9;
}
        </style>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";}?> >
        <?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <div style="float:left; margin-top:10px;">
                <h2><?php echo $wInit->pageHeading; ?><?php echo RoomReport::getGlobalNightsCounter($dbh, $totalRest); ?><?php echo RoomReport::getGlobalStaysCounter($dbh); ?>
                <span style="margin-left:10px; font-size: .65em; background:#EFDBC2;">Name Search:
                <input type="text" class="allSearch" id="txtsearch" size="20" title="Enter at least 3 characters to invoke search" /></span>
                </h2>
            </div>

            <?php echo $guestAddMessage; ?>
            <div id="paymentMessage" style="clear:left;float:left; margin-top:5px;margin-bottom:5px; display:none;" class="hhk-alert ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox"></div>
            <div style="clear:both;"></div>
            <form name="frmdownload" action="#" method="post">
            <div id="mainTabs" style="display:none; font-size:.9em;">
                <ul>
                    <li id="liCal"><a href="#vcal">Calendar</a></li>
                    <li><a href="#vstays">Current Guests (<span id="spnNumCurrent"></span>)</a></li>
                    <li><a href="#vresvs"><?php echo $labels->getString('register', 'reservationTab', 'Confirmed Reservations'); ?> (<span id="spnNumConfirmed"></span>)</a></li>
                    <?php if ($uS->ShowUncfrmdStatusTab) { ?>
                    <li><a href="#vuncon"><?php echo $labels->getString('register', 'unconfirmedTab', 'UnConfirmed Reservations'); ?> (<span id="spnNumUnconfirmed"></span>)</a></li>
                    <?php } ?>
                    <li><a href="#vwls">Wait List (<span id="spnNumWaitlist"></span>)</a></li>
                    <?php if ($isGuestAdmin) { ?>
                        <li><a href="#vactivity">Recent Activity</a></li>
                        <?php if ($showCharges) { ?>
                        <li><a href="#vfees"><?php echo $labels->getString('register', 'recentPayTab', 'Recent Payments'); ?></a></li>
                        <li id="liInvoice"><a href="#vInv">Unpaid Invoices</a></li>
                    <?php } } ?>
                    <li id="liDaylog"><a href="#vdaily">Daily Log</a></li>
                </ul>
                <div id="vcal" style="clear:left; padding: .6em 1em; display:none;">
                    <?php echo $colorKey; ?>
                    <div id="divGoto" style="position:absolute;">
                        <span id="spnGotoDate" >Go to Date: <input id="txtGotoDate" type="text" class="ckdate" value="" /></span>
                        <span id="pCalLoad" style="font-weight:bold;">Loading...</span>
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
                <?php if ($isGuestAdmin) { ?>
                <div id="vactivity" class="hhk-tdbox hhk-visitdialog" style="display:none; ">
                    <table><tr>
                            <th>Reports</th><th>Dates</th>
                        </tr><tr>
                            <td><input id='cbVisits' type='checkbox' checked="checked"/> Visits</td>
                            <td>Starting: <input type="text" id="txtactstart" class="ckdate" value="" /></td>
                        </tr><tr>
                            <td><input id='cbReserv' type='checkbox'/> Reservations</td>
                            <td>Ending: <input type="text" id="txtactend" class="ckdate" value="" /></td>
                        </tr><tr>
                            <td><input id='cbHospStay' type='checkbox'/> <?php echo $labels->getString('hospital', 'hospital', 'Hospital'); ?> Stays</td>
                            <td></td>
                        </tr><tr>
                            <td></td>
                            <td style="text-align: right;"><input type="button" id="btnActvtyGo" value="Submit"/></td>
                        </tr></table>
                    <div id="rptdiv" class="hhk-visitdialog"></div>
                </div>
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
                <div id="vInv" class="hhk-tdbox hhk-visitdialog" style="display:none; ">
                    <input type="button" id="btnInvGo" value="Refresh"/>
                      <div id="rptInvdiv" class="hhk-visitdialog"></div>
                </div>
                <?php } ?>
            </div>
        </form>
        </div>  <!-- div id="contentDiv"-->
        <div id="keysfees" style="font-size: .9em;"></div>
        <div id="setBillDate" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size: .9em;">
            <span class="ui-helper-hidden-accessible"><input type="text"/></span>
            <table><tr>
                    <td class="tdlabel">Invoice Number:</td>
                    <td><span id="spnInvNumber"></span></td>
                </tr><tr>
                    <td class="tdlabel">Payor:</td>
                    <td><span id="spnBillPayor"></span></td>
                </tr><tr>
                    <td class="tdlabel">Bill Date:</td>
                    <td><input id="txtBillDate" value="" readonly="readonly" /></td>
                </tr><tr>
                    <td colspan="2"><textarea rows="2" cols="50" id="taBillNotes" ></textarea></td>
                </tr>
            </table>
        </div>

        <div class="gmenu"></div>

        <div id="faDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.9em;"></div>
        <form name="xform" id="xform" method="post"></form>

        <div id="cardonfile" style="font-size: .9em; display:none;"></div>
        <div id="statEvents" class="hhk-tdbox hhk-visitdialog" style="font-size: .9em; display:none;"></div>
        <div id="pmtRcpt" style="font-size: .9em; display:none;"></div>
        <div id="dchgPw" class="hhk-tdbox hhk-visitdialog" style="font-size: .9em; display:none;">
            <table><tr>
                    <td class="tdlabel">User Name:</td><td style="background-color: white;"><span id="txtUserName"><?php echo $uS->username; ?></span></td>
                </tr><tr>
                    <td class="tdlabel">Enter Old Password:</td><td><input id="txtOldPw" type="password" value=""  /></td>
                </tr><tr>
                    <td class="tdlabel">Enter New Password:</td><td><input id="txtNewPw1" type="password" value=""  /></td>
                </tr><tr>
                    <td class="tdlabel">New Password Again:</td><td><input id="txtNewPw2" type="password" value=""  /></td>
                </tr><tr>
                    <td colspan ="2"><span style="font-size: smaller;">Passwords must have at least 8 characters with at least 1 uppercase letter, 1 lowercase letter and a number.</span></td>
                </tr><tr>
                    <td colspan ="2" style="text-align: center;padding-top:10px;"><span id="pwChangeErrMsg" style="color:red;"></span></td>
                </tr>
            </table>
        </div>
        <input  type="hidden" id="isGuestAdmin" value='<?php echo $isGuestAdmin; ?>' />
        <input  type="hidden" id="pmtMkup" value='<?php echo $paymentMarkup; ?>' />
        <input  type="hidden" id="rctMkup" value='<?php echo $receiptMarkup; ?>' />
        <input  type="hidden" id="defaultTab" value='<?php echo $defaultRegisterTab; ?>' />
        <input  type="hidden" id="resourceGroupBy" value='<?php echo $resourceGroupBy; ?>' />
        <input  type="hidden" id="resourceColumnWidth" value='<?php echo $uS->CalRescColWidth; ?>' />
        <input  type="hidden" id="patientLabel" value='<?php echo $labels->getString('MemberType', 'patient', 'Patient'); ?>' />
        <input  type="hidden" id="challVar" value='<?php echo $challengeVar; ?>' />
        <input  type="hidden" id="defaultView" value='<?php echo $defaultView; ?>' />
        <input  type="hidden" id="calDateIncrement" value='<?php echo $calDateIncrement; ?>' />
        <input  type="hidden" id="dateFormat" value='<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>' />
        <input  type="hidden" id="fixedRate" value='<?php echo RoomRateCategorys::Fixed_Rate_Category; ?>' />
        <input  type="hidden" id="resvPageName" value='<?php echo 'Reserve.php'; ?>' />
        <input  type="hidden" id="showCreatedDate" value='<?php echo $uS->ShowCreatedDate; ?>' />
        <input  type="hidden" id="expandResources" value='<?php echo $uS->CalExpandResources; ?>' />
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

        <script type="text/javascript" src="js/register-min.js?pu=5"></script>

    </body>
</html>
