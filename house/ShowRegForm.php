<?php
/**
 * ShowRegForm.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");


require(DB_TABLES . "visitRS.php");
require(DB_TABLES . "ReservationRS.php");
require(DB_TABLES . "registrationRS.php");

require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'PaymentsRS.php');
require (DB_TABLES . 'PaymentGwRS.php');
require (DB_TABLES . 'AttributeRS.php');


require CLASSES . 'FinAssistance.php';
require (CLASSES . 'PaymentSvcs.php');

//require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';
require (THIRD_PARTY . 'PHPMailer/v6/src/PHPMailer.php');
require (THIRD_PARTY . 'PHPMailer/v6/src/SMTP.php');
require (THIRD_PARTY . 'PHPMailer/v6/src/Exception.php');

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


require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (MEMBER . 'OrgMember.php');
require (MEMBER . "Addresses.php");
require (MEMBER . "EmergencyContact.php");

require(HOUSE . "psg.php");
require (HOUSE . 'Registration.php');
require (HOUSE . 'RoleMember.php');
require (HOUSE . 'Role.php');
require (HOUSE . 'Guest.php');
require (HOUSE . 'Patient.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'Reservation_1.php');
require (HOUSE . 'ReservationSvcs.php');
require (HOUSE . 'Visit.php');
require (HOUSE . 'RegisterForm.php');
require (HOUSE . 'RegistrationForm.php');
require (HOUSE . 'Attributes.php');
require (HOUSE . 'Constraint.php');
require (HOUSE . 'Vehicle.php');


$wInit = new webInit(WebPageCode::Page);
$pageTitle = $wInit->pageTitle;

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$uS = Session::getInstance();
creditIncludes($uS->PaymentGateway);

$idVisit = 0;
$idGuest = 0;
$idResv = 0;
$span =0;
$idRegistration = 0;
$idPayment = 0;
$paymentMarkup = '';
$regDialogmkup = '';
$receiptMarkup = '';
$invoiceNumber = '';
$menuMarkup = '';
$regButtonStyle = 'display:none;';


// Hosted payment return
if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $_REQUEST)) === FALSE) {

    if ($payResult->getDisplayMessage() != '') {
        $paymentMarkup = HTMLContainer::generateMarkup('p', $payResult->getDisplayMessage());
    }

    $receiptMarkup = $payResult->getReceiptMarkup();

    $idRegistration = $payResult->getIdRegistration();
//    $idInvoice = $payResult->getIdInvoice();
//
//    $invoice = new Invoice($dbh);
//    try {
//        $invoice->loadInvoice($dbh, $idInvoice);
//        $idVisit = $invoice->getOrderNumber();
//    } catch(Exception $ex) {
//
//    }

}

if (isset($_REQUEST['regid'])) {
    $idRegistration = intval(filter_var($_REQUEST['regid'], FILTER_SANITIZE_STRING), 10);
}

if (isset($_GET['vid'])) {
    $idVisit = intval(filter_var($_REQUEST['vid'], FILTER_SANITIZE_STRING), 10);
}

if (isset($_GET['payId'])) {
    $idPayment = intval(filter_var($_REQUEST['payId'], FILTER_SANITIZE_STRING), 10);
}

if (isset($_GET['invoiceNumber'])) {
    $invoiceNumber = filter_var($_REQUEST['invoiceNumber'], FILTER_SANITIZE_STRING);
}

if (isset($_GET['span'])) {
    $span = intval(filter_var($_REQUEST['span'], FILTER_SANITIZE_STRING), 10);
}

if (isset($_GET['gid'])) {
    $idGuest = intval(filter_var($_REQUEST['gid'], FILTER_SANITIZE_STRING), 10);
}

if (isset($_GET['rid'])) {
    $idResv = intval(filter_var($_REQUEST['rid'], FILTER_SANITIZE_STRING), 10);
}

if ($idVisit == 0 && $idResv > 0) {
    $stmt = $dbh->query("Select idVisit from visit where idReservation = $idResv");
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);

    if (count($rows) > 0) {
        $idVisit = $rows[0][0];
    }
}

// Registration Info
if ($idRegistration > 0) {
    $menuMarkup = $wInit->generatePageMenu();

    $reg = new Registration($dbh, 0, $idRegistration);

    $regDialogmkup = HTMLContainer::generateMarkup('div', $reg->createRegMarkup($dbh, FALSE), array('id' => 'regContainer', 'class' => "ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox"));

    $regButtonStyle = '';
}


// Generate Registration Form
$reservArray = ReservationSvcs::generateCkinDoc($dbh, $idResv, $idVisit, $span, '../conf/registrationLogo.png');
$li = '';
$tabContent = '';

foreach ($reservArray['docs'] as $r) {

    $li .= HTMLContainer::generateMarkup('li',
            HTMLContainer::generateMarkup('a', $r['tabTitle'] , array('href'=>'#'.$r['tabIndex'])));


    $tabContent .= HTMLContainer::generateMarkup('div',
        HTMLInput::generateMarkup('Print', array('type'=>'button', 'class'=>'btnPrint', 'data-tab'=>$r['tabIndex']))
        .HTMLContainer::generateMarkup('div', $r['doc'], array('id'=>'PrintArea'.$r['tabIndex'])),
        array('id'=>$r['tabIndex']));

    $sty = $r['style'];
}

$ul = HTMLContainer::generateMarkup('ul', $li, array());
$tabControl = HTMLContainer::generateMarkup('div', $ul . $tabContent, array('id'=>'regTabDiv'));


$shoRegBtn = HTMLInput::generateMarkup('Check In Followup', array('type'=>'button', 'id'=>'btnReg', 'style'=>$regButtonStyle));
$shoStmtBtn = HTMLInput::generateMarkup('Show Statement', array('type'=>'button', 'id'=>'btnStmt', 'style'=>$regButtonStyle));

$regMessage = HTMLContainer::generateMarkup('div', '', array('id'=>'mesgReg', 'style'=>'color: darkgreen; clear:left; font-size:1.5em;display:none;'));

$contrls = HTMLContainer::generateMarkup('div', $shoRegBtn . $shoStmtBtn . $regMessage, array());

unset($reservArray);

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo FAVICON; ?>
        <style type="text/css" media="print">
            #PrintArea {margin:0; padding:0; font: 12px Arial, Helvetica,"Lucida Grande", serif; color: #000;}
            @page { margin: .5cm; }
        </style>
        <?php echo $sty; ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type='text/javascript'>
$(document).ready(function() {
    "use strict";
    var idReg = '<?php echo $idRegistration; ?>';
    var rctMkup = '<?php echo $receiptMarkup; ?>';
    var regMarkup = '<?php echo $regDialogmkup; ?>';
    var payId = '<?php echo $idPayment; ?>';
    var invoiceNumber = '<?php echo $invoiceNumber; ?>';
    var vid = '<?php echo $idVisit; ?>';
    var opt = {mode: 'popup',
        popClose: true,
        popHt      : $('div#PrintArea').height(),
        popWd      : 950,
        popX       : 20,
        popY       : 20,
        popTitle   : 'Guest Registration Form'};

    $('.btnPrint').click(function() {
        opt.popHt = $('div#PrintArea' + $(this).data('tab')).height();
        $('div#PrintArea' + $(this).data('tab')).printArea(opt);
    }).button();

    $('#btnReg').click(function() {
        getRegistrationDialog(idReg);
    }).button();

    $('#btnStmt').click(function() {
        window.open('ShowStatement.php?vid=' + vid, '_blank');
    }).button();

    $('#pmtRcpt').dialog({
        autoOpen: false,
        resizable: true,
        width: 530,
        modal: true,
        title: 'Payment Receipt'
    });

    if (rctMkup !== '') {
        showReceipt('#pmtRcpt', rctMkup, 'Payment Receipt');
    }
    if (regMarkup) {
        showRegDialog(regMarkup, idReg);
    }

    if (payId && payId > 0) {
        reprintReceipt(payId, '#pmtRcpt');
    }

    if (invoiceNumber && invoiceNumber !== '') {
        window.open('ShowInvoice.php?invnum=' + invoiceNumber);
    }

    $('#mainTabs').tabs().show();
    $('#regTabDiv').tabs();

});
</script>
    </head>
    <body>
 <?php echo $menuMarkup; ?>
        <div id="contentDiv" >
            <div id="paymentMessage" style="float:left; margin-top:15px;margin-bottom:5px;" class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox">
                <?php echo $paymentMarkup; ?>
            </div>

            <div id="mainTabs" style="max-width:900px; display:none; font-size:.9em;">
                <ul>
                    <li id="liReg"><a href="#vreg">Registration Form</a></li>
                </ul>
                <div id="vreg" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none; ">
                    <?php echo $contrls; ?>
                    <?php echo $tabControl; ?>
                </div>
            </div>
            <div id="vperm" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none; ">
                <h2>No permission forms were found.</h2>
            </div>
            <div id="pmtRcpt" style="font-size: .9em; display:none;"></div>
            <div id="regDialog"></div>
        </div>
    </body>
</html>
