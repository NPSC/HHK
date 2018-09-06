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

require (CLASSES . 'MercPay/MercuryHCClient.php');
require (CLASSES . 'MercPay/Gateway.php');
require (PMT . 'Payments.php');
require (PMT . 'HostedPayments.php');
require (PMT . 'Receipt.php');
require (PMT . 'Invoice.php');
require (PMT . 'InvoiceLine.php');
require (PMT . 'CreditToken.php');
require (PMT . 'CheckTX.php');
require (PMT . 'CashTX.php');
require (PMT . 'Transaction.php');

require (CLASSES . 'PaymentSvcs.php');
require (CLASSES . 'Purchase/RoomRate.php');
require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';
require CLASSES . 'TableLog.php';

require (HOUSE . 'Room.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'ResourceView.php');
require (HOUSE . 'visitViewer.php');
require (HOUSE . 'VisitLog.php');
require (HOUSE . 'RoomLog.php');
require (HOUSE . 'RoomReport.php');

require (CLASSES . 'CreateMarkupFromDB.php');
require (CLASSES . 'Notes.php');
require_once(SEC . 'ChallengeGenerator.php');


try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

$dbh = $wInit->dbh;
$pageTitle = $wInit->pageTitle;

$menuMarkup = $wInit->generatePageMenu();

// Load the session with member - based lookups
$wInit->sessionLoadGenLkUps();
$wInit->sessionLoadGuestLkUps();

// get session instance
$uS = Session::getInstance();


$config = new Config_Lite(ciCFG_FILE);
$totalRest = $uS->PreviousNights;

// Get labels
$labels = new Config_Lite(LABEL_FILE);


// Instantiate the alert message control
$alertMsg = new alertMessage("divAlert1");
$alertMsg->set_DisplayAttr("none");
$alertMsg->set_Context(alertMessage::Success);
$alertMsg->set_iconId("alrIcon");
$alertMsg->set_styleId("alrResponse");
$alertMsg->set_txtSpanId("alrMessage");
$alertMsg->set_Text("help");

$resultMessage = $alertMsg->createMarkup();

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
$rvCols = array();
$wlCols = array();


if ($uS->DefaultRegisterTab > 0 && $uS->DefaultRegisterTab < 5) {
    $defaultRegisterTab = $uS->DefaultRegisterTab;
}

// Hosted payment return
if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $uS->ccgw, $_POST)) === FALSE) {

    $receiptMarkup = $payResult->getReceiptMarkup();
    $paymentMarkup = HTMLContainer::generateMarkup('p', $payResult->getDisplayMessage());
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
        . HTMLContainer::generateMarkup('div', "<table id='reservs' class='display' style='width:100%;'cellpadding='0' cellspacing='0' border='0'></table>", array('id' => 'divreservs'));

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

    $colorKey = HTMLContainer::generateMarkup('span', $labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital') . ': ');
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

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <link href='css/fullcalendar.min.css'  rel='stylesheet' type='text/css' />
        <link href='css/scheduler.min.css'  rel='stylesheet' type='text/css' />
        <?php echo NOTY_CSS; ?>
        <?php echo FAVICON; ?>

        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="js/fullcalendar.min.js"></script>
        <script type="text/javascript" src="../js/hhk-scheduler.min.js"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTES_VIEWER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VISIT_DIALOG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MD5_JS; ?>"></script>
        <script type="text/javascript">
            var isGuestAdmin = '<?php echo $isGuestAdmin; ?>';
            var pmtMkup = "<?php echo $paymentMarkup; ?>";
            var rctMkup = '<?php echo $receiptMarkup; ?>';
            var defaultTab = '<?php echo $defaultRegisterTab; ?>';
            var resourceGroupBy = '<?php echo $resourceGroupBy; ?>';
            var patientLabel = '<?php echo $labels->getString('MemberType', 'patient', 'Patient'); ?>';
            var challVar = '<?php echo $challengeVar; ?>';
            var defaultView = '<?php echo $defaultView; ?>';
            var calDateIncrement = '<?php echo $calDateIncrement; ?>';
            var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
            var fixedRate = '<?php echo RoomRateCategorys::Fixed_Rate_Category; ?>';
            var resvPageName = '<?php echo $config->getString('house', 'ReservationPage', 'Reserve.php'); ?>';
            var showCreatedDate = '<?php echo $uS->ShowCreatedDate; ?>';
            var expandResources = '<?php echo $uS->CalExpandResources; ?>';
            var shoHospitalName = '<?php echo $shoHosptialName; ?>';
            var cgCols = [
                {data: 'Action', title: 'Action', sortable: false, searchable:false},
                {data: 'Guest First', title: 'Guest First'},
                {data: 'Guest Last', title: 'Guest Last'},
                {data: 'Checked In', title: 'Checked In', render: function (data, type) {return dateRender(data, type, dateFormat);}},
                {data: 'Nights', title: 'Nights', className: 'hhk-justify-c'},
                {data: 'Expected Departure', title: 'Expected Departure', render: function (data, type) {return dateRender(data, type, dateFormat);}},
                {data: 'Room', title: 'Room', className: 'hhk-justify-c'},
                <?php if ($uS->RoomPriceModel != ItemPriceCode::None) { ?>
                {data: 'Rate', title: 'Rate'},
                <?php } ?>
                {data: 'Phone', title: 'Phone'},
                <?php if (count($uS->guestLookups[GL_TableNames::Hospital]) > 1) { ?>
                {data: 'Hospital', title: '<?php echo $labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital'); ?>'},
                <?php } ?>
                {data: 'Patient', title: patientLabel},
            ];

            var rvCols = [
                {data: 'Action', title: 'Action', sortable: false, searchable:false},
                {data: 'Guest First', title: 'Guest First'},
                {data: 'Guest Last', title: 'Guest Last'},
                {data: 'Expected Arrival', title: 'Expected Arrival', render: function (data, type) {return dateRender(data, type, dateFormat);}},
                {data: 'Nights', title: 'Nights', className: 'hhk-justify-c'},
                {data: 'Expected Departure', title: 'Expected Departure', render: function (data, type) {return dateRender(data, type, dateFormat);}},
                {data: 'Room', title: 'Room', className: 'hhk-justify-c'},
                <?php if ($uS->RoomPriceModel != ItemPriceCode::None) { ?>
                {data: 'Rate', title: 'Rate'},
                <?php } ?>
                {data: 'Occupants', title: 'Occupants', className: 'hhk-justify-c'},
                <?php if (count($uS->guestLookups[GL_TableNames::Hospital]) > 1) { ?>
                {data: 'Hospital', title: '<?php echo $labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital'); ?>'},
                <?php } ?>
                <?php if (count($locations) > 0) { ?>
                {data: 'Location', title: '<?php echo $labels->getString('hospital', 'location', 'Location'); ?>'},
                <?php } if (count($diags) > 0) { ?>
                {data: 'Diagnosis', title: '<?php echo $labels->getString('hospital', 'diagnosis', 'Diagnosis'); ?>'},
                <?php } ?>
                {data: 'Patient', title: patientLabel},
            ];

            var wlCols = [
                {data: 'Action', title: 'Action', sortable: false, searchable:false},
                {data: 'Guest First', title: 'Guest First'},
                {data: 'Guest Last', title: 'Guest Last'},
                <?php if ($uS->ShowCreatedDate) { ?>
                {data: 'Timestamp', title: 'Created On', render: function (data, type) {return dateRender(data, type, dateFormat);}},
                <?php } ?>
                {data: 'Expected Arrival', title: 'Expected Arrival', render: function (data, type) {return dateRender(data, type, dateFormat);}},
                {data: 'Nights', title: 'Nights', className: 'hhk-justify-c'},
                {data: 'Expected Departure', title: 'Expected Departure', render: function (data, type) {return dateRender(data, type, dateFormat);}},
                {data: 'Occupants', title: 'Occupants', className: 'hhk-justify-c'},
                <?php if (count($uS->guestLookups[GL_TableNames::Hospital]) > 1) { ?>
                {data: 'Hospital', title: '<?php echo $labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital'); ?>'},
                <?php } ?>
                <?php if (count($locations) > 0) { ?>
                {data: 'Location', title: '<?php echo $labels->getString('hospital', 'location', 'Location'); ?>'},
                <?php } if (count($diags) > 0) { ?>
                {data: 'Diagnosis', title: '<?php echo $labels->getString('hospital', 'diagnosis', 'Diagnosis'); ?>'},
                <?php } ?>
                {data: 'Patient', title: patientLabel},
                <?php if ($uS->UseWLnotes) { ?>
                {data: 'WL Notes', title: '<?php echo $labels->getString('referral', 'waitlistNotesLabel', 'WL Notes'); ?>'},
                <?php } ?>
            ];

            var dailyCols = [
                {data: 'titleSort', 'visible': false },
                {data: 'Title', title: 'Room', 'orderData': [0, 1], className: 'hhk-justify-c'},
                {data: 'Status', title: 'Status', searchable:false},
                {data: 'Guests', title: 'Guests'},
                {data: 'Patient_Name', title: patientLabel},
                <?php if ($showCharges) { ?>
                {data: 'Unpaid', title: 'Unpaid', className: 'hhk-justify-r'},
                <?php } ?>
                {data: 'Visit_Notes', title: 'Visit Notes', sortable: false},
                {data: 'Notes', title: 'Room Notes', sortable: false},
            ];

        </script>
        <script type="text/javascript" src="js/register-min.js?v2x=n"></script>
        <style>
           #version {
            height: 15px;
            position: absolute;
            right: 2px;
            top: 47px;
            font-size: .6em;
            padding: 0 6px;
            cursor:pointer;
            }
            #version:hover { background-color: yellow; }
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
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";}?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <div style="float:left; margin-top:10px;">
                <h2><?php echo $wInit->pageHeading; ?><?php echo RoomReport::getGlobalNightsCounter($dbh, $totalRest); ?><?php echo RoomReport::getGlobalStaysCounter($dbh); ?>
                <span style="margin-left:10px; font-size: .65em; background:#EFDBC2;">Name Search:
                <input type="text" class="allSearch" id="txtsearch" size="20" title="Enter at least 3 characters to invoke search" /></span>
                </h2>
            </div>
            <div id="divAlertMsg" style="clear:left;"><?php echo $resultMessage; ?></div>
            <div id="paymentMessage" style="clear:left;float:left; margin-top:5px;margin-bottom:5px; display:none;" class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox">
            </div>
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
                    <li><a href="#vdaily">Daily Log</a></li>
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
                            <td><?php if ($uS->Reservation) { ?><input id='cbReserv' type='checkbox'/> Reservations</td><?php } ?>
                            <td>Ending: <input type="text" id="txtactend" class="ckdate" value="" /></td>
                        </tr><tr>
                            <td><input id='cbHospStay' type='checkbox'/> Hospital Stays</td>
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
                    <div id="rptfeediv" class="hhk-visitdialog"></div>
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
        <form name="xform" id="xform" method="post"><input type="hidden" name="CardID" id="CardID" value=""/></form>
        <div id="cardonfile" style="font-size: .9em; display:none;"></div>
        <div id="statEvents" class="hhk-tdbox hhk-visitdialog" style="font-size: .9em; display:none;"></div>
        <div id="pmtRcpt" style="font-size: .9em; display:none;"></div>
        <div id="dchgPw" style="display:none;">
            <table><tr>
                    <td class="tdlabel">User Name:</td><td style="background-color: white;"><span id="txtUserName"><?php echo $uS->username; ?></span></td>
                </tr><tr>
                    <td class="tdlabel">Enter Old Password:</td><td><input id="txtOldPw" type="password" value=""  /></td>
                </tr><tr>
                    <td class="tdlabel">Enter New Password:</td><td><input id="txtNewPw1" type="password" value=""  /></td>
                </tr><tr>
                    <td class="tdlabel">New Password Again:</td><td><input id="txtNewPw2" type="password" value=""  /></td>
                </tr><tr>
                    <td colspan ="2" style="text-align: center;padding-top:10px;"><span id="pwChangeErrMsg" style="color:red;"></span></td>
                </tr>
            </table>
        </div>
    </body>
</html>
