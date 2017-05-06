<?php
/**
 * Register.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

require (CLASSES . 'History.php');
require (DB_TABLES . 'PaymentsRS.php');
require (DB_TABLES . 'visitRS.php');
require (DB_TABLES . 'MercuryRS.php');
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

function doExcelDownLoad($rows, $fileName) {

    if (count($rows) === 0) {
        return;
    }

    require_once CLASSES . 'OpenXML.php';

    $reportRows = 1;
    $sml = OpenXML::createExcel('', $fileName);

    // build header
    $hdr = array();
    $n = 0;

    $keys = array_keys($rows[0]);

    foreach ($keys as $t) {
        $hdr[$n++] = $t;
    }

    OpenXML::writeHeaderRow($sml, $hdr);
    $reportRows++;

    foreach ($rows as $r) {

        $n = 0;
        $flds = array();

        foreach ($r as $col) {

            $flds[$n++] = array('type' => "s", 'value' => $col);
        }


        $reportRows = OpenXML::writeNextRow($sml, $flds, $reportRows);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '.xlsx"');
    header('Cache-Control: max-age=0');

    OpenXML::finalizeExcel($sml);
    exit();

}

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

$isGuestAdmin = ComponentAuthClass::is_Authorized('guestadmin');

$paymentMarkup = '';
$receiptMarkup = '';



// Hosted payment return
if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $uS->ccgw, $_POST)) === FALSE) {

    $receiptMarkup = $payResult->getReceiptMarkup();
    $paymentMarkup = HTMLContainer::generateMarkup('p', $payResult->getDisplayMessage());
}

$history = new History();

// Page Return
if (isset($_POST['btnDlCurGuests'])) {
    // Current guests
    $rows = History::getCheckedInGuestMarkup($dbh, '', FALSE);
    doExcelDownLoad($rows, 'CurrentGuests');
}
if (isset($_POST['btnDlConfRes'])) {
    // Confirmed Reservations
    $rows = $history->getReservedGuestsMarkup($dbh, ReservationStatus::Committed, '', FALSE);
    doExcelDownLoad($rows, 'ConfirmedResv');
}
if (isset($_POST['btnDlUcRes'])) {
    // Unconfirmed Reservations
    $rows = $history->getReservedGuestsMarkup($dbh, ReservationStatus::UnCommitted, '', FALSE);
    doExcelDownLoad($rows, 'UnconfirmedResv');
}
if (isset($_POST['btnDlWlist'])) {
    // Waitlist
    $rows = $history->getReservedGuestsMarkup($dbh, ReservationStatus::Waitlist, '', FALSE);
    doExcelDownLoad($rows, 'Waitlist');
}
if (isset($_POST['btnFeesDl'])) {

    require (HOUSE . 'PaymentReport.php');

    PaymentReport::generateDayReport($dbh, $_POST);
}



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

if (isset($uS->roomCount) === FALSE) {
    $stmt = $dbh->query("Select count(*) from resource");
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    $uS->roomCount = $rows[0][0];
}

$roomCount = max(array($uS->roomCount, 10));

if ($uS->Reservation) {
    $roomCount += 9;
}

$divFontSize = 'font-size:.9em;';
if ($roomCount > 20) {
    $divFontSize = 'font-size:.8em;';
}

// Current guests columns
$cgCols = array(
    array("data" => "Action" ),
    array("data" => "Guest" ),
    array("data" => "Checked-In", 'type'=>'date' ),
    array("data" => "Nights", 'className'=>'hhk-justify-c' ),
    array("data" => "Expected Departure" , 'type'=>'date'),
    array("data" => "Room" )
    );

if ($uS->RoomPriceModel != ItemPriceCode::None) {
    $cgCols[] = array("data" => "Rate" );
    $cgCols[] = array("data" => "Amount", 'className'=>'hhk-justify-r' );
}

$cgCols[] = array("data" => "Phone" );
$cgCols[] = array("data" => $labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital') );
$cgCols[] = array("data" => $labels->getString('MemberType', 'patient', 'Patient') );


$currTable = new HTMLTable();
$hdrRow = '';

foreach ($cgCols as $c) {
    $hdrRow .= HTMLTable::makeTh($c['data']);
}

$currTable->addHeaderTr($hdrRow);
$currTable->addFooterTr($hdrRow);

// Currently Checked In guests
$currentCheckedIn = HTMLContainer::generateMarkup('h3', 'Current Guests' . HTMLInput::generateMarkup('Excel Download', array('type'=>'submit', 'name'=>'btnDlCurGuests', 'style'=>'margin-left:5em;')), array('style' => 'background-color:#D3D3D3; padding:10px;'))
        . HTMLContainer::generateMarkup('div', $currTable->generateMarkup(array('id'=>'curres', 'style'=>'width:100%;')), array('id' => 'divcurres'));

// Confirmed reservations and waitlist
$currentReservations = '';
$uncommittedReservations = '';
$waitlist = '';
$rvCols = array();

$wlCols = array();

if ($uS->Reservation) {

    $locations = readGenLookupsPDO($dbh, 'Location');
    $diags = readGenLookupsPDO($dbh, 'Diagnosis');

    $rvCols = array(
        array("data" => "Action" ),
        array("data" => "Guest" ),
        array("data" => "Expected Arrival" , 'type'=>'date'),
        array("data" => "Nights", 'className'=>'hhk-justify-c' ),
        array("data" => "Expected Departure" , 'type'=>'date'),
        array("data" => "Room" )
        );

    if ($uS->RoomPriceModel != ItemPriceCode::None) {
        $rvCols[] = array("data" => "Rate" );
    }

    $rvCols[] = array("data" => "Occupants" );
    $rvCols[] = array("data" => $labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital') );

    if (count($locations) > 0) {
        $rvCols[] = array("data" => $labels->getString('hospital', 'location', 'Unit') );
    }

    if (count($diags) > 0) {
        $rvCols[] = array("data" => $labels->getString('hospital', 'diagnosis', 'Diagnosis') );
    }

    $rvCols[] = array("data" => $labels->getString('MemberType', 'patient', 'Patient') );

    $hdrRow = '';

    foreach ($rvCols as $c) {
        $hdrRow .= HTMLTable::makeTh($c['data']);
    }

    $currTable = new HTMLTable();
    $currTable->addHeaderTr($hdrRow);
    $currTable->addFooterTr($hdrRow);

    // make registration form print button
    $regButton = HTMLContainer::generateMarkup('span', 'Check-in Date: ' . HTMLInput::generateMarkup('', array('id'=>'regckindate', 'class'=>'ckdate hhk-prtRegForm'))
            . HTMLInput::generateMarkup('Print Registration Forms', array('id'=>'btnPrintRegForm', 'type'=>'button', 'data-page'=>'PrtRegForm.php', 'class'=>'hhk-prtRegForm', 'style'=>'margin-left:.3em;'))
            , array('style'=>'margin-left:5em;padding:9px;border:solid 1px #62A0CE;background-color:#E8E5E5'));

    $currentReservations = HTMLContainer::generateMarkup('h3',
            $labels->getString('register', 'reservationTab', 'Confirmed Reservations') .
            HTMLInput::generateMarkup('Excel Download', array('type'=>'submit', 'name'=>'btnDlConfRes', 'style'=>'margin-left:5em;')) . $regButton
            , array('style' => 'background-color:#D3D3D3; padding:10px;'))
            . HTMLContainer::generateMarkup('div', $currTable->generateMarkup(array('id'=> 'reservs', 'style'=>'width:100%;')), array('id' => 'divreservs'));

    if ($uS->ShowUncfrmdStatusTab) {
        $uncommittedReservations = HTMLContainer::generateMarkup('h3', $labels->getString('register', 'unconfirmedTab', 'UnConfirmed Reservations') . HTMLInput::generateMarkup('Excel Download', array('type'=>'submit', 'name'=>'btnDlUcRes', 'style'=>'margin-left:5em;')), array('style' => 'background-color:#D3D3D3; padding:10px;'))
            . HTMLContainer::generateMarkup('div', $currTable->generateMarkup(array('id'=> 'unreserv', 'style'=>'width:100%;')), array('id' => 'divunreserv'));
    }


    $wlCols = array(
        array("data" => "Action" ),
        array("data" => "Guest" ),
        array("data" => "Expected Arrival" , 'type'=>'date'),
        array("data" => "Nights", 'className'=>'hhk-justify-c' ),
        array("data" => "Expected Departure" , 'type'=>'date'),
        array("data" => "Phone")
        );

    if ($uS->RoomPriceModel != ItemPriceCode::None) {
        $wlCols[] = array("data" => "Rate" );
    }

    $wlCols[] = array("data" => "Occupants" );
    $wlCols[] = array("data" => $labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital') );

    if (count($locations) > 0) {
        $wlCols[] = array("data" => $labels->getString('hospital', 'location', 'Unit') );
    }
    if (count($diags) > 0) {
        $wlCols[] = array("data" => $labels->getString('hospital', 'diagnosis', 'Diagnosis') );
    }

    $wlCols[] = array("data" => $labels->getString('MemberType', 'patient', 'Patient') );

    $whdrRow = '';

    foreach ($wlCols as $c) {
        $whdrRow .= HTMLTable::makeTh($c['data']);
    }

    $wlTable = new HTMLTable();
    $wlTable->addHeaderTr($whdrRow);
    $wlTable->addFooterTr($whdrRow);

        // make registration form print button
    $wlButton = HTMLContainer::generateMarkup('span', 'Date: ' . HTMLInput::generateMarkup(date('M j, Y'), array('id'=>'regwldate', 'class'=>'ckdate hhk-prtWL'))
            . HTMLInput::generateMarkup('Print Wait List', array('id'=>'btnPrintWL', 'type'=>'button', 'data-page'=>'PrtWaitList.php', 'class'=>'hhk-prtWL', 'style'=>'margin-left:.3em;'))
            , array('style'=>'margin-left:5em;padding:9px;border:solid 1px #62A0CE;background-color:#E8E5E5'));


    $waitlist = HTMLContainer::generateMarkup('h3', 'Waitlist' .
            HTMLInput::generateMarkup('Excel Download', array('type'=>'submit', 'name'=>'btnDlWlist', 'style'=>'margin-left:5em;'))
            .$wlButton
            , array('style' => 'background-color:#D3D3D3; padding:10px;'))
            . HTMLContainer::generateMarkup('div', $wlTable->generateMarkup(array('id'=> 'waitlist', 'style'=>'width:100%;')), array('id' => 'divwaitlist'));
}

// Hospital Selector
$colorKey = '';
$stmth = $dbh->query("Select idHospital, Title, Reservation_Style, Stay_Style from hospital where Status = 'a' and Title != '(None)'");

if ($stmth->rowCount() > 1 && strtolower($uS->RegColors) == 'hospital') {

    $colorKey = HTMLContainer::generateMarkup('span', $labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital') . ': ');
    // All button
    $colorKey .= HTMLContainer::generateMarkup('span', 'All', array('class'=>'spnHosp', 'data-id'=>0, 'style' => 'border:solid 3px black;font-size:120%;background-color:fff;color:000;'));

    while ($r = $stmth->fetch(\PDO::FETCH_ASSOC)) {

        $attrs = array('class'=>'spnHosp', 'data-id'=>$r['idHospital']);

        if ($uS->RegColors == 'hospital') {
            $attrs['style'] = 'background-color:' . $r['Reservation_Style'] . ';color:' . $r['Stay_Style'] . ';';
        }

        $colorKey .= HTMLContainer::generateMarkup('span', $r['Title'], $attrs);
    }
}

// View density
$weeks = $uS->CalViewWeeks;
if ($weeks < 1) {
    $weeks = 1;
} else if ($weeks > 5) {
    $weeks = 5;
}

$viewWeeks = '';  //HTMLContainer::generateMarkup('span', 'View ' . HTMLInput::generateMarkup($weeks, array('size'=>'1', 'id'=>'txtViewWeeks')) . ' weeks', array('style'=>'margin-right:5px;'));

// instantiate a ChallengeGenerator object
try {
    $chlgen = new ChallengeGenerator();
    $challengeVar = $chlgen->getChallengeVar("challenge");
} catch (Exception $e) {
    //
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo JQ_DT_CSS; ?>

        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        <link href='css/fullcalendar.css'  rel='stylesheet' type='text/css' />
        <?php echo HOUSE_CSS; ?>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_DTJQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?>js/hhkcalendar-min.js<?php echo JS_V; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo VERIFY_ADDRS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo MOMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VISIT_DIALOG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo MD5_JS; ?>"></script>
        <script type="text/javascript">
            var isGuestAdmin = '<?php echo $isGuestAdmin; ?>';
            var pmtMkup = "<?php echo $paymentMarkup; ?>";
            var rctMkup = '<?php echo $receiptMarkup; ?>';
            var roomCnt = '<?php echo $roomCount; ?>';
            var defaultTab = '<?php echo $uS->DefaultRegisterTab; ?>';
            var cgCols = $.parseJSON('<?php echo json_encode($cgCols); ?>');
            var rvCols = $.parseJSON('<?php echo json_encode($rvCols); ?>');
            var wlCols = $.parseJSON('<?php echo json_encode($wlCols); ?>');
            var challVar = '<?php echo $challengeVar; ?>';
            var viewDays = '<?php echo ($weeks * 7); ?>';
        </script>
        <script type="text/javascript" src="js/register-min.js<?php echo JS_V; ?>"></script>
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
    .fc-content {
        height: 680px;
        overflow-y: auto;
    }
</style>
    </head>
    <body <?php
    if ($wInit->testVersion) {
        echo "class='testbody'";
    }
    ?>>
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
            <div id="mainTabs" style="display:none; <?php echo $divFontSize; ?>">
                <ul>
                    <li id="liCal"><a href="#vcal">Calendar</a></li>
                    <li><a href="#vstays">Current Guests</a></li>
                    <?php if ($uS->Reservation) { ?>
                        <li><a href="#vresvs"><?php echo $labels->getString('register', 'reservationTab', 'Confirmed Reservations'); ?></a></li>
                        <?php if ($uS->ShowUncfrmdStatusTab) { ?>
                        <li><a href="#vuncon"><?php echo $labels->getString('register', 'unconfirmedTab', 'UnConfirmed Reservations'); ?></a></li>
                        <?php } ?>
                        <li><a href="#vwls">Wait List</a></li>
                    <?php } ?>
                    <?php if ($isGuestAdmin) { ?>
                        <li><a href="#vactivity">Recent Activity</a></li>
                        <?php if ($uS->RoomPriceModel != ItemPriceCode::None) { ?>
                        <li><a href="#vfees"><?php echo $labels->getString('register', 'recentPayTab', 'Recent Payments'); ?></a></li>
                        <li id="liInvoice"><a href="#vInv">Unpaid Invoices</a></li>
                    <?php } } ?>
                </ul>
                <div id="vcal" style="clear:left; padding: .6em 1em; display:none; <?php echo $divFontSize; ?>">
                    <?php echo $viewWeeks; echo $colorKey; ?>
                    <div id="calendar"></div>
                </div>
                <div id="vstays" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none; <?php echo $divFontSize; ?>">
                    <?php echo $currentCheckedIn; ?>
                </div>
<?php if ($uS->ShowUncfrmdStatusTab) { ?>
                <div id="vuncon" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none; <?php echo $divFontSize; ?>">
                    <?php echo $uncommittedReservations; ?>
                </div>
<?php } ?>
                <div id="vresvs" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none; <?php echo $divFontSize; ?>">
                    <?php echo $currentReservations; ?>
                </div>
                <div id="vwls" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none; <?php echo $divFontSize; ?>">
<?php echo $waitlist; ?>
                </div>
<?php if ($isGuestAdmin) { ?>
                <div id="vactivity" class="hhk-tdbox hhk-visitdialog" style="display:none; <?php echo $divFontSize; ?>">
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
                <div id="vfees" class="hhk-tdbox hhk-visitdialog" style="display:none; <?php echo $divFontSize; ?>">
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
                <div id="vInv" class="hhk-tdbox hhk-visitdialog" style="display:none; <?php echo $divFontSize; ?>">
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
