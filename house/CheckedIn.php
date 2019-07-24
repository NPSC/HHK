<?php
/**
 * CheckedIn.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");
require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'registrationRS.php');
require (DB_TABLES . 'ReservationRS.php');
require (DB_TABLES . 'visitRS.php');
require (DB_TABLES . 'PaymentGwRS.php');
require (DB_TABLES . 'PaymentsRS.php');
require (DB_TABLES . 'AttributeRS.php');

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

require (CLASSES . 'Purchase/Item.php');
require (CLASSES . 'PaymentSvcs.php');
require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';

require (HOUSE . 'psg.php');

require (HOUSE . 'Role.php');
require (HOUSE . 'Guest.php');
require (HOUSE . 'Patient.php');
require (HOUSE . 'RoleMember.php');
require (HOUSE . 'Registration.php');
require (HOUSE . 'RegistrationForm.php');
require (HOUSE . 'ReservationSvcs.php');
require (HOUSE . 'Reservation_1.php');
require (HOUSE . 'RegisterForm.php');
require (HOUSE . 'visitViewer.php');
require (HOUSE . 'Visit.php');
require (HOUSE . 'Constraint.php');
require (HOUSE . 'Vehicle.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'Hospital.php');


try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;

// get session instance
$uS = Session::getInstance();
creditIncludes($uS->PaymentGateway);

$menuMarkup = $wInit->generatePageMenu();


$sty = '';
$regForm = '';
$regDialogmkup = '';
$idRegistration = 0;
$idVisit = 0;
$showCkinDoc = 'display:none;';
$paymentMarkup = '';
$receiptMarkup = '';

// Hosted payment return
if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $_REQUEST)) === FALSE) {

    if ($payResult->getDisplayMessage() != '') {
        $paymentMarkup = HTMLContainer::generateMarkup('p', $payResult->getDisplayMessage());
    }

    $receiptMarkup = $payResult->getReceiptMarkup();

    $idRegistration = $payResult->getIdRegistration();
    $idInvoice = $payResult->getIdInvoice();


    if ($idInvoice > 0) {
        // get the registration from the invoice.
        $invoice = new Invoice($dbh);
        $invoice->loadInvoice($dbh, $idInvoice);

        $idRegistration = $invoice->getIdGroup();
        $idVisit = $invoice->getOrderNumber();

    }

    if ($idRegistration > 0) {

        $reg = new Registration($dbh, 0, $idRegistration);

        $regDialogmkup = HTMLContainer::generateMarkup('div', $reg->createRegMarkup($dbh, FALSE), array('id' => 'regContainer', 'class' => "ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox"));
        $showCkinDoc = '';

    }

    if ($idVisit == 0 && isset($_REQUEST['rid'])) {
        // Find visit
        $idResv = intval(filter_var($_REQUEST['rid'], FILTER_SANITIZE_NUMBER_INT), 10);

        if ($idResv > 0) {

            $stmt = $dbh->query("select idVisit from visit where Span = 0 and idReservation = $idResv");
            $rows = $stmt->fetchAll(PDO::FETCH_NUM);

            if (count($rows) > 0) {
                $idVisit = $rows[0][0];
            }
        }
    }

    if ($idVisit > 0) {

        $reservArray = ReservationSvcs::generateCkinDoc($dbh, 0, $idVisit, 0, '../images/receiptlogo.png');

        $sty = $reservArray['style'];
        $regForm = $reservArray['doc'];
        unset($reservArray);

    } else {
        $regForm = 'No Register Information.';
    }

} else {
    $paymentMarkup = HTMLContainer::generateMarkup('span', 'No Information.', array('style'=>'margin-right:1em;'));
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>

        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo FAVICON; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>

        <?php echo $sty; ?>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv" >
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div id="paymentMessage" style="float:left; margin-top:15px;margin-bottom:5px;" class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox">
                <?php echo $paymentMarkup; ?>
            </div>
            <div id='receiptContainer' style="clear:left;<?php echo $showCkinDoc; ?>">
                <div id="print_button" style="float:left;">Print</div>
                <div id="btnReg" style="float:left; margin-left:10px;">Check In Followup</div>
                <div id="mesgReg" style="color: darkgreen; clear:left; font-size:1.5em;"></div>
                <div style="clear: left;" class="PrintArea"/><?php echo $regForm; ?></div>
            </div>
        </div>  <!-- div id="contentDiv"-->
        <div id="pmtRcpt" style="font-size: .9em; display:none;"></div>
        <div id="regDialog">
            <?php echo $regDialogmkup; ?>
        </div>
        <script type="text/javascript">
    function getRegistrationDialog(idreg) {
        "use strict";
        if (idreg == 0) {
            return;
        }
        $.post('ws_ckin.php', {cmd: 'getReg', reg: idreg},
        function(data) {
            if (!data) {
                alert('Bad Reply from Server');
                return;
            }
            try {
                data = $.parseJSON(data);
            } catch (err) {
                alert('Bad JSON Encoding');
                return;
            }
            if (data.error) {

                alert(data.error);
                return;

            } else if (data.success) {
                $('#regDialog').children().remove();
                $('#regDialog').append($(data.success));
                $('#regDialog').dialog('open');
            }
        }
        );
    }
    $(document).ready(function() {
        "use strict";
        var idReg = '<?php echo $idRegistration; ?>';
        var rctMkup = '<?php echo $receiptMarkup; ?>';
        $("div#print_button, div#btnReg").button();
        $("div#print_button").click(function() {
            $("div.PrintArea").printArea();
        });
        $('div#btnReg').click(function() {
            getRegistrationDialog(idReg);
        });
        $('#pmtRcpt').dialog({
            autoOpen: false,
            resizable: true,
            modal: true,
        });
        if (rctMkup !== '') {
            showReceipt('#pmtRcpt', rctMkup, 'Payment Receipt');
        }
        if (idReg > 0) {
            $('#regDialog').dialog({
                autoOpen: true,
                width: 360,
                resizable: true,
                modal: true,
                title: 'Registration Info',
                buttons: {
                    "Save": function() {
                        var parms = {};
                        $('.hhk-regvalue').each(function() {
                            if ($(this).attr('type') === 'checkbox') {
                                if (this.checked !== false) {
                                    parms[$(this).attr('name')] = 'on';
                                }
                            } else {
                                parms[$(this).attr('name')] = this.value;
                            }
                        });
                        $.post('ws_ckin.php',
                                {cmd: 'saveReg',
                                    reg: idReg,
                                    parm: parms},
                        function(data) {
                            $('#regDialog').dialog("close");
                            if (!data) {
                                alert('Bad Reply from Server');
                                return;
                            }
                            try {
                                data = $.parseJSON(data);
                            } catch (err) {
                                alert('Bad JSON Encoding');
                                return;
                            }
                            if (data.error) {
                                alert(data.error);
                                return;
                            } else if (data.success) {
                                $('#mesgReg').text(data.success);
                            }
                        });
                    },
                    "Cancel": function() {
                        $(this).dialog("close");
                    }
                }
            });
        }
    });
        </script>
    </body>
</html>
