<?php

use HHK\Exception\RuntimeException;
use HHK\House\Report\VisitIntervalReport;
use HHK\HTMLControls\HTMLContainer;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;
use HHK\Payment\PaymentSvcs;
use HHK\sec\{
    Session,
    WebInit,
    Labels
};
use HHK\SysConst\RoomRateCategories;
use HHK\SysConst\Mode;


/**
 * VisitInterval.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require "homeIncludes.php";

try {
    $wInit = new WebInit();
} catch (Exception $exw) {
    die("Arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

// get session instance
$uS = Session::getInstance();


// Get labels
$labels = Labels::getLabels();
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
        if ($receiptMarkup != '' && $uS->merchantReceipt == true) {
            $receiptMarkup = HTMLContainer::generateMarkup(
                'div',
                HTMLContainer::generateMarkup('div', $receiptMarkup . HTMLContainer::generateMarkup('div', 'Customer Copy', ['style' => 'text-align:center;']), ['style' => 'margin-right: 15px; width: 100%;'])
                . HTMLContainer::generateMarkup('div', $receiptMarkup . HTMLContainer::generateMarkup('div', 'Merchant Copy', ['style' => 'text-align: center']), ['style' => 'margin-left: 15px; width: 100%;'])
                ,
                ['style' => 'display: flex; min-width: 100%;', 'data-merchCopy' => '1'],
            );
        }

        // Display a status message.
        if ($payResult->getDisplayMessage() != '') {
            $paymentMarkup = HTMLContainer::generateMarkup('p', $payResult->getDisplayMessage());
        }

        if (WebInit::isAJAX()) {
            echo json_encode(["receipt"=>$receiptMarkup, ($payResult->wasError() ? "error": "success")=>$payResult->getDisplayMessage(), 'idPayment'=>$receiptPaymentId, 'billToEmail'=>$receiptBilledToEmail]);
            exit;
        }
    }

} catch (RuntimeException $ex) {
    if (WebInit::isAJAX()) {
        echo json_encode(["error" => $ex->getMessage()]);
        exit;
    } else {
        $paymentMarkup = $ex->getMessage();
    }
}


$dataTableWrapper = '';

$report = new VisitIntervalReport($dbh, $_REQUEST);

if (isset($_POST['btnHere-' . $report->getInputSetReportName()]) || isset($_POST['btnStatsOnly-' . $report->getInputSetReportName()])) {
    $dataTableWrapper = $report->generateMarkup();
}

if (isset($_POST['btnExcel-' . $report->getInputSetReportName()])) {
    $report->downloadExcel("VisitReport");
}

$dateFormat = $labels->getString("momentFormats", "report", "MMM D, YYYY");

if ($uS->CoTod) {
    $dateFormat .= ' H:mm';
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <?php echo NOTY_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <?php echo CSSVARS; ?>
        <?php echo BOOTSTRAP_ICONS_CSS; ?>
        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_MANAGER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VISIT_DIALOG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BUFFER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo HTMLENTITIES_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo DOMPURIFY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTES_VIEWER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo INVOICE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo REPORTFIELDSETS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VISIT_INTERVAL_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo SMS_DIALOG_JS; ?>"></script>
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
            var dateFormat = '<?php echo $dateFormat; ?>';
            $(document).ready(function() {
                <?php echo $report->generateReportScript(); ?>
            });
        </script>
</head>

<body <?php if ($wInit->testVersion)
    echo "class='testbody'"; ?>>
    <?php echo $wInit->generatePageMenu(); ?>
    <div id="contentDiv">
        <h2><?php echo $wInit->pageHeading; ?></h2>
        <div id="paymentMessage" style="display:none;"
            class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox my-2"></div>

        <?php echo $report->generateFilterMarkup() . $dataTableWrapper; ?>
    </div>
    <input type="hidden" value="<?php echo RoomRateCategories::Fixed_Rate_Category; ?>" id="fixedRate" />
    <input type="hidden" id="rctMkup" value='<?php echo $receiptMarkup; ?>' />
    <input  type="hidden" id="receiptPaymentId" value='<?php echo $receiptPaymentId; ?>' />
    <input  type="hidden" id="receiptBilledToEmail" value='<?php echo $receiptBilledToEmail; ?>' />
    <input type="hidden" id="pmtMkup" value='<?php echo $paymentMarkup; ?>' />
    <div id="keysfees" style="font-size: .9em;"></div>
    <div id="pmtRcpt" style="font-size: .9em; display:none;"></div>
    <div id="hsDialog" class="hhk-tdbox hhk-visitdialog hhk-hsdialog" style="display:none;font-size:.8em;"></div>
    <div id="vehDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.8em;"></div>
    <div id="faDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.8em;"></div>
    <?php if ($uS->PaymentGateway == AbstractPaymentGateway::DELUXE) {
        echo DeluxeGateway::getIframeMkup();
    } ?>
    <form name="xform" id="xform" method="post"></form>
</body>

</html>
