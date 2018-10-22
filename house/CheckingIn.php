<?php
/**
 * CheckingIn.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'registrationRS.php');
require (DB_TABLES . 'ActivityRS.php');
require (DB_TABLES . 'visitRS.php');
require (DB_TABLES . 'ReservationRS.php');
require (DB_TABLES . 'PaymentGwRS.php');
require (DB_TABLES . 'PaymentsRS.php');

require (CLASSES . 'MercPay/MercuryHCClient.php');
require (CLASSES . 'MercPay/Gateway.php');

require (PMT . 'GatewayConnect.php');
require (PMT . 'PaymentGateway.php');

require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (MEMBER . 'OrgMember.php');
require (MEMBER . "Addresses.php");
require (MEMBER . "EmergencyContact.php");

require (CLASSES . 'CleanAddress.php');
require (CLASSES . 'AuditLog.php');
require (CLASSES . 'PaymentSvcs.php');
require (CLASSES . 'Purchase/Item.php');
require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';
require CLASSES . 'TableLog.php';

require (HOUSE . 'psg.php');
require (HOUSE . 'RoleMember.php');
require (HOUSE . 'Role.php');
require (HOUSE . 'Guest.php');
require (HOUSE . 'Agent.php');
require (HOUSE . 'Patient.php');
require (HOUSE . 'Reservation_1.php');
require (HOUSE . 'ReserveData.php');
require (HOUSE . 'RegistrationForm.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'Registration.php');
require (HOUSE . 'Hospital.php');
require (HOUSE . 'VisitLog.php');
require (HOUSE . 'Constraint.php');
require (HOUSE . 'Attributes.php');


try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

$dbh = $wInit->dbh;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();


// Get labels
$labels = new Config_Lite(LABEL_FILE);
$paymentMarkup = '';
$receiptMarkup = '';
$payFailPage = $wInit->page->getFilename();
$idGuest = 0;
$idReserv = 0;
$idPsg = 0;
$idVisit = 0;
$span = -1;

// Hosted payment return
if (isset($_POST['CardID']) || isset($_POST['PaymentID']) || isset($_POST[InstamedGateway::INSTAMED_TRANS_VAR])) {

    require (PMT . 'Payments.php');
    require (PMT . 'HostedPayments.php');
    require (PMT . 'Receipt.php');
    require (PMT . 'Invoice.php');
    require (PMT . 'InvoiceLine.php');
    require (PMT . 'CreditToken.php');
    require (PMT . 'CheckTX.php');
    require (PMT . 'CashTX.php');
    require (PMT . 'Transaction.php');

    require (HOUSE . 'PaymentManager.php');
    require (HOUSE . 'PaymentChooser.php');

    if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $uS->ccgw, $_POST)) === FALSE) {
        $receiptMarkup = $payResult->getReceiptMarkup();
        $paymentMarkup = HTMLContainer::generateMarkup('p', $payResult->getDisplayMessage());
    }
}


if (isset($uS->cofrid)) {
    $idReserv = $uS->cofrid;
    unset($uS->cofrid);
}


$resvObj = new ReserveData(array(), 'Check-in');
$resvObj->setSaveButtonLabel('Check-in');


if (isset($_GET['id'])) {
    $idGuest = intval(filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT), 10);
}

if (isset($_GET['rid'])) {
    $idReserv = intval(filter_var($_GET['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
}

if (isset($_GET['idPsg'])) {
    $idPsg = intval(filter_var($_GET['idPsg'], FILTER_SANITIZE_NUMBER_INT), 10);
}

if (isset($_GET['vid'])) {
    $idVisit = intval(filter_var($_GET['vid'], FILTER_SANITIZE_NUMBER_INT), 10);
}

if (isset($_GET['span'])) {
    $span = intval(filter_var($_GET['span'], FILTER_SANITIZE_NUMBER_INT), 10);
}


if ($idReserv > 0 || $idGuest > 0 || $idVisit > 0) {

    $mk1 = "<h2>Loading...</h2>";
    $resvObj->setIdResv($idReserv);
    $resvObj->setId($idGuest);
    $resvObj->setIdPsg($idPsg);
    $resvObj->setIdVisit($idVisit);
    $resvObj->setSpan($span);


} else {

    $mk1 = HTMLContainer::generateMarkup('h2', 'Reservation Id is missing.');

}


$resvAr = $resvObj->toArray();
$resvAr['patBD'] = $resvObj->getPatBirthDateFlag();
$resvAr['patAddr'] = $uS->PatientAddr;
$resvAr['gstAddr'] = $uS->GuestAddr;
$resvAr['addrPurpose'] = $resvObj->getAddrPurpose();
$resvAr['patAsGuest'] = $resvObj->getPatAsGuestFlag();
$resvAr['emergencyContact'] = isset($uS->EmergContactFill) ? $uS->EmergContactFill : FALSE;
$resvAr['isCheckin'] = TRUE;

$resvObjEncoded = json_encode($resvAr);

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $wInit->pageTitle; ?></title>

        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo DR_PICKER_CSS ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo NOTY_CSS; ?>

        <?php echo FAVICON; ?>
<!--        Fix the ugly checkboxes-->
        <style>
            .ui-icon-background, .ui-state-active .ui-icon-background {background-color:#fff;}
        </style>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo ADDR_PREFS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo DR_PICKER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VISIT_DIALOG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTES_VIEWER_JS ?>"></script>
        <script type="text/javascript" src="<?php echo DIRRTY_JS; ?>"></script>
        <script type="text/javascript" src="js/embed.js" data-displaymode="popup" data-hostname="https://online.instamed.com/providers"></script>

        <script type="text/javascript" src="<?php echo RESV_MANAGER_JS; ?>"></script>

    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $wInit->generatePageMenu() ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?> <span id="spnStatus" sytle="margin-left:50px; display:inline;"></span></h1>

            <div id="paymentMessage" style="clear:left;float:left; margin-top:5px;margin-bottom:5px; display:none;" class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox">
                <?php echo $paymentMarkup; ?>
            </div>
            <div id="guestSearch" style="padding-left:0;padding-top:0; margin-bottom:1.5em; clear:left; float:left;">
                <?php echo $mk1; ?>
            </div>

            <form action="CheckingIn.php" method="post"  id="form1">
                <div id="datesSection" style="clear:left; float:left; display:none;" class="ui-widget ui-widget-header ui-state-default ui-corner-all hhk-panel"></div>
                <div id="famSection" style="clear:left; float:left; font-size: .9em; display:none; width: 100%; margin-bottom:.5em;" class="ui-widget hhk-visitdialog"></div>
                <div id="hospitalSection" style="font-size: .9em; margin-bottom:.5em; clear:left; float:left; display:none; width: 100%;"  class="ui-widget hhk-visitdialog"></div>
                <div id="resvSection" style="clear:left; float:left; font-size:.9em; display:none; margin-bottom:.5em; width: 100%;" class="ui-widget hhk-visitdialog"></div>
                <div style="clear:both;min-height: 70px;">.</div>
                <div id="submitButtons" class="ui-corner-all" style="font-size:.9em; clear:both;">
                    <input type="button" id="btnShowReg" value='Show Registration Form' style="display:none;"/>
                    <input type='button' id='btnDone' value='Continue' style="display:none;"/>
                </div>

            </form>

            <div id="pmtRcpt" style="font-size: .9em; display:none;"><?php echo $receiptMarkup; ?></div>
            <div id="activityDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.9em;"></div>
            <div id="faDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.9em;"></div>
            <div id="keysfees" style="display:none;font-size: .85em;"></div>
            <div id="ecSearch"  style="display:none;">
                <table>
                    <tr>
                        <td>Search: </td><td><input type="text" id="txtemSch" size="15" value="" title="Type at least 3 letters to invoke the search."/></td>
                    </tr>
                    <tr><td><input type="hidden" value="" id="hdnEcSchPrefix"/></td></tr>
                </table>
            </div>

        </div>
        <form name="xform" id="xform" method="post"><input type="hidden" name="CardID" id="CardID" value=""/></form>

<script type="text/javascript">
var fixedRate = '<?php echo RoomRateCategorys::Fixed_Rate_Category; ?>';
var payFailPage = '<?php echo $payFailPage; ?>';
var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
var pageManager;

function ckedIn(data) {
    "use strict";
    $("#divAlert1").hide();

    if (data.warning) {
        flagAlertMessage(data.warning, 'warning');
    }

    paymentRedirect(data, $('#xform'));

    if (data.success) {
        //flagAlertMessage(data.success, false);
        var cDiv = $('#contentDiv');
        var opt = {mode: 'popup',
            popClose: true,
            popHt      : $('div#RegArea').height(),
            popWd      : 950,
            popX       : 20,
            popY       : 20,
            popTitle   : 'Guest Registration Form'};

        cDiv.children().remove();

        if (data.regform && data.style) {
            cDiv.append($('<div id="print_button" style="float:left;">Print</div>'))
                    .append($('<div id="btnReg" style="float:left; margin-left:10px;">Check In Followup</div>'))
                    .append($('<div id="btnStmt" style="float:left; margin-left:10px;">Show Statement</div>'))
                    .append($('<div id="mesgReg" style="color: darkgreen; clear:left; font-size:1.5em;"></div>'))
                    .append($('<div style="clear: left;" class="RegArea"/>')
                            .append($(data.style)).append($(data.regform)));

            $("div#print_button, div#btnReg, div#btnStmt").button();
            $("div#print_button").click(function() {
                $("div.RegArea").printArea(opt);
            });
            $('div#btnReg').click(function() {
                getRegistrationDialog(data.reg, cDiv);
            });
            $('div#btnStmt').click(function() {
                window.open('ShowStatement.php?vid=' + data.vid, '_blank');
            });
        }

        if (data.ckmeout) {
            var buttons = {
                "Show Statement": function() {
                    window.open('ShowStatement.php?vid=' + data.vid, '_blank');
                },
                "Check Out": function() {
                    saveFees(data.gid, data.vid, 0, true, 'register.php');
                },
                "Cancel": function() {
                    $(this).dialog("close");
                }
            };
            viewVisit(data.gid, data.vid, buttons, 'Check Out', 'co', 0, data.ckmeout);
        }

        if (data.regDialog) {
            showRegDialog(data.regDialog, data.reg, cDiv);
        }

        if (data.receipt) {
            showReceipt('#pmtRcpt', data.receipt);
        }

        if (data.invoiceNumber && data.invoiceNumber !== '') {
            window.open('ShowInvoice.php?invnum=' + data.invoiceNumber);
        }

    }
}

$(document).ready(function() {
    "use strict";
    var t = this;
    var resv = $.parseJSON('<?php echo $resvObjEncoded; ?>');
    var pageManager = t.pageManager;

    $.widget( "ui.autocomplete", $.ui.autocomplete, {
        _resizeMenu: function() {
            var ul = this.menu.element;
            ul.outerWidth( Math.max(
                    ul.width( "" ).outerWidth() + 1,
                    this.element.outerWidth()
            ) * 1.1 );
        }
    });

// Dialog Boxes

    $("#activityDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: 900,
        modal: true,
        title: 'Reservation Activity Log',
        close: function () {$('div#submitButtons').show();},
        open: function () {$('div#submitButtons').hide();},
        buttons: {
            "Exit": function() {
                $(this).dialog("close");
            }
        }
    });

    $('#keysfees').dialog({
        autoOpen: false,
        resizable: true,
        modal: true,
        close: function() {$('#submitButtons').show();}
    });

    $('#pmtRcpt').dialog({
        autoOpen: false,
        resizable: true,
        width: 530,
        modal: true,
        title: 'Payment Receipt'
    });

    $("#ecSearch").dialog({
        autoOpen: false,
        resizable: false,
        width: 300,
        title: 'Emergency Contact',
        modal: true,
        buttons: {
            "Exit": function() {
                $(this).dialog("close");
            }
        }
    });


    pageManager = new resvManager(resv);

    // hide the alert on mousedown
    $(document).mousedown(function (event) {

        var target = $(event.target);

        if (target[0].id !== 'divSelAddr' && target[0].closest('div') && target[0].closest('div').id !== 'divSelAddr') {
            $('#divSelAddr').remove();
        }
    });

// Buttons
    $('#btnDone, #btnShowReg').button();

    $('#btnShowReg').click(function () {
        window.open('ShowRegForm.php?rid=' + pageManager.getIdResv(), '_blank');
    });

    $('#btnDone').click(function () {

        if ($(this).val() === 'Saving >>>>') {
            return;
        }

        if (pageManager.verifyInput() === true) {

            $.post(
                'ws_resv.php',
                $('#form1').serialize() + '&cmd=saveCheckin&idPsg=' + pageManager.getIdPsg() + '&rid=' + pageManager.getIdResv() + '&vid=' + pageManager.getIdVisit() + '&span=' + pageManager.getSpan() + '&' + $.param({mem: pageManager.people.list()}),
                function(data) {

                    try {
                        data = $.parseJSON(data);
                    } catch (err) {
                        flagAlertMessage(err.message, 'error');
                        return;
                    }

                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }

                    if (data.error) {
                        flagAlertMessage(data.error, 'error');
                    }

                    $('#btnDone').val(resv.saveButtonLabel).show();
                    ckedIn(data);

                }
            );

            $(this).val('Saving >>>>');
        }

    });


    if (parseInt(resv.id, 10) > 0 || parseInt(resv.rid, 10) > 0 || parseInt(resv.vid, 10) > 0) {

        // Avoid automatic new guest for existing reservations.
        if (parseInt(resv.id, 10) === 0 && parseInt(resv.rid, 10) > 0) {
            resv.id = -2;
        }

        resv.cmd = 'getCkin';
        pageManager.getReserve(resv);

    } else {

    }
});
        </script>
    </body>
</html>
