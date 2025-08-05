<?php

use HHK\House\Report\BillingAgentReport;
use HHK\HTMLControls\HTMLContainer;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;
use HHK\Payment\PaymentSvcs;
use HHK\sec\{Session, WebInit};
use HHK\sec\Labels;
use HHK\SysConst\Mode;
use HHK\SysConst\RoomRateCategories;

/**
 * BillingAgentReport.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");

try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;

// get session instance
$uS = Session::getInstance();
$labels = Labels::getLabels();
$menuMarkup = $wInit->generatePageMenu();

$paymentMarkup = '';
$receiptMarkup = '';
$receiptBilledToEmail = '';
$receiptPaymentId = 0;

// Hosted payment return
try {

    if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $_REQUEST)) === FALSE) {

        $receiptMarkup = $payResult->getReceiptMarkup();
        $receiptBilledToEmail = $payResult->getInvoiceBillToEmail($dbh);
        $receiptPaymentId = $payResult->getIdPayment();

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

        if(WebInit::isAJAX()){
            echo json_encode(["receipt"=>$receiptMarkup, ($payResult->wasError() ? "error": "success")=>$payResult->getDisplayMessage(), 'idPayment'=>$receiptPaymentId, 'billToEmail'=>$receiptBilledToEmail]);
            exit;
        }
    }

} catch (RuntimeException $ex) {
    if(WebInit::isAJAX()){
        echo json_encode(["error"=>$ex->getMessage()]);
        exit;
    } else {
        $paymentMarkup = $ex->getMessage();
    }
}


$dataTableWrapper = '';

$report = new BillingAgentReport($dbh, $_REQUEST);

if (isset($_POST['btnHere-' . $report->getInputSetReportName()])) {
    $dataTableWrapper = $report->generateMarkup();
}

if (isset($_POST['btnExcel-' . $report->getInputSetReportName()])) {
    ini_set('memory_limit', "280M");
    $report->downloadExcel("BillingAgentReport");
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>
        <?php echo BOOTSTRAP_ICONS_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <?php echo CSSVARS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo SMS_DIALOG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VISIT_DIALOG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BUFFER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTES_VIEWER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo INVOICE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo REPORTFIELDSETS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <?php if ($uS->PaymentGateway == AbstractPaymentGateway::INSTAMED) {echo INS_EMBED_JS;} ?>
        <?php 
            if ($uS->PaymentGateway == AbstractPaymentGateway::DELUXE) {
                if ($uS->mode == Mode::Live) {
                    echo DELUXE_EMBED_JS;
                }else{
                    echo DELUXE_SANDBOX_EMBED_JS;
                }
            }
        ?>

        <script type="text/javascript">
            var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
            var columnDefs = $.parseJSON('<?php echo json_encode($report->colSelector->getColumnDefs()); ?>');
            var fixedRate = '<?php echo RoomRateCategories::Fixed_Rate_Category; ?>';
            var rctMkup, pmtMkup, receiptBilledToEmail, receiptPaymentId;
            $(document).ready(function() {

                var drawCallback = function (settings) {
                    $('.hhk-viewVisit').button();
                    $('.hhk-viewVisit').click(function () {
                        var vid = $(this).data('vid');
                        var gid = $(this).data('gid');
                        var span = $(this).data('span');

                        var buttons = {
                            "Show Statement": function() {
                                window.open('ShowStatement.php?vid=' + vid, '_blank');
                            },
                            "Show Registration Form": function() {
                                window.open('ShowRegForm.php?vid=' + vid + '&span=' + span, '_blank');
                            },
                            "Save": function() {
                                saveFees(gid, vid, span, false, 'VisitInterval.php');
                            },
                            "Cancel": function() {
                                $(this).dialog("close");
                            }
                        };
                        viewVisit(gid, vid, buttons, 'Edit Visit #' + vid + '-' + span, '', span);
                    });
                };

                <?php echo $report->filter->getTimePeriodScript(); ?>;
                <?php echo $report->generateReportScript(); ?>
                
		        pmtMkup = '<?php echo $paymentMarkup; ?>'
                rctMkup = '<?php echo $receiptMarkup; ?>'
                receiptBilledToEmail = '<?php echo $receiptBilledToEmail; ?>'
                receiptPaymentId = '<?php echo $receiptPaymentId; ?>'

                $('#keysfees').dialog({
                    autoOpen: false,
                    resizable: true,
                    modal: true,
                    close: function () {$('div#submitButtons').show();},
                    open: function () {$('div#submitButtons').hide();}
                });
                $('#pmtRcpt').dialog({
                    autoOpen: false,
                    resizable: true,
                    width: getDialogWidth(530),
                    modal: true,
                    title: 'Payment Receipt'
                });
                $("#faDialog").dialog({
                    autoOpen: false,
                    resizable: true,
                    width: getDialogWidth(650),
                    modal: true,
                    title: 'Income Chooser'
                });

                if (rctMkup !== '') {
                    showReceipt('#pmtRcpt', rctMkup, 'Payment Receipt', 550, receiptPaymentId, receiptBilledToEmail);
                }
                if (pmtMkup !== '') {
                    $('#paymentMessage').html(pmtMkup).show("pulsate", {}, 400);
                }

                $('#keysfees').mousedown(function (event) {
                    var target = $(event.target);
                    if ( target[0].id !== 'pudiv' && target.parents("#" + 'pudiv').length === 0) {
                        $('div#pudiv').remove();
                    }
                });

                // disappear the pop-up room chooser.
                $(document).mousedown(function (event) {
                    var target = $(event.target);
                    if ($('div#insDetailDiv').length > 0 && target[0].id !== 'insDetailDiv' && target.parents("#" + 'insDetailDiv').length === 0) {
                        $('div#insDetailDiv').remove();
                    }
                });

            });
         </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <?php echo $report->generateFilterMarkup() . $dataTableWrapper; ?>
        </div>

        <div id="keysfees" style="font-size: .9em; display: none;"></div>
        <div id="pmtRcpt" style="font-size: .9em; display: none;"></div>
        <div id="hsDialog" class="hhk-tdbox hhk-visitdialog hhk-hsdialog" style="display:none;font-size:.8em;"></div>
        <div id="vehDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.8em;"></div>
        <div id="faDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.8em;"></div>
        <?php if ($uS->PaymentGateway == AbstractPaymentGateway::DELUXE) {
            echo DeluxeGateway::getIframeMkup(); } ?>
    </body>
</html>
