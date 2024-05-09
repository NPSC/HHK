<?php

use HHK\House\Report\BillingAgentReport;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\sec\{Session, WebInit};
use HHK\sec\Labels;
use HHK\House\Report\ReservationReport;
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

$report = new BillingAgentReport($dbh, $_REQUEST);

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
        <?php echo NOTY_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <?php echo CSSVARS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>

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
        <script type="text/javascript" src="<?php echo REPORTVIEWER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo SMS_DIALOG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <?php if ($uS->PaymentGateway == AbstractPaymentGateway::INSTAMED) {echo INS_EMBED_JS;} ?>

        <script type="text/javascript">
            var fixedRate = '<?php echo RoomRateCategories::Fixed_Rate_Category; ?>';
            var rctMkup, pmtMkup;
            $(document).ready(function() {

                <?php echo $report->generateReportScript(); ?>
                
		        pmtMkup = $('#pmtMkup').val(),
                rctMkup = $('#rctMkup').val();

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
                    showReceipt('#pmtRcpt', rctMkup, 'Payment Receipt');
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
            <?php echo $report->generateWrapperMarkup(); ?>
        </div>

        <input  type="hidden" id="rctMkup" value='<?php echo $receiptMarkup; ?>' />
        <input  type="hidden" id="pmtMkup" value='<?php echo $paymentMarkup; ?>' />
        <div id="keysfees" style="font-size: .9em; display: none;"></div>
        <div id="pmtRcpt" style="font-size: .9em; display: none;"></div>
        <div id="hsDialog" class="hhk-tdbox hhk-visitdialog hhk-hsdialog" style="display:none;font-size:.8em;"></div>
        <div id="faDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.8em;"></div>
    </body>
</html>
