<?php


use HHK\Exception\RuntimeException;
use HHK\House\ReserveData\ReserveData;
use HHK\HTMLControls\HTMLContainer;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Payment\PaymentSvcs;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\sec\WebInit;
use HHK\SysConst\RoomRateCategories;
use HHK\SysConst\VisitStatus;
use HHK\SysConst\Mode;


/**
 * CheckingIn.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

try {
    $wInit = new WebInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

$dbh = $wInit->dbh;

// get session instance
$uS = Session::getInstance();

$pageHdr = $wInit->pageHeading;
$bkgrdColor = '';

$labels = Labels::getLabels();
$paymentMarkup = '';
$receiptMarkup = '';
$receiptBilledToEmail = '';
$receiptPaymentId = 0;
$payFailPage = $wInit->page->getFilename();
$idGuest = 0;
$idReserv = 0;
$idPsg = 0;
$idVisit = 0;
$span = 0;
$visitStatus = '';

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


if (isset($uS->cofrid)) {
    $idReserv = $uS->cofrid;
    unset($uS->cofrid);
}


$resvObj = new ReserveData([], 'Checking In');
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
    $resvObj->setSaveButtonLabel('Add '.$labels->getString('MemberType', 'guest', 'Guest'));

}

if (isset($_GET['span'])) {
    $span = intval(filter_var($_GET['span'], FILTER_SANITIZE_NUMBER_INT), 10);
}

if (isset($_GET['vstatus'])) {
    $visitStatus = filter_var($_GET['vstatus'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}


if ($idReserv > 0 || $idGuest > 0 || $idVisit > 0) {

    //$mk1 = "<h2>Loading...</h2>";
    $mk1 = '<div id="hhk-loading-spinner" style="width: 100%; height: 100%; margin-top: 100px; text-align: center"><img src="../images/ui-anim_basic_16x16.gif"><p>Loading...</p></div>';
    $resvObj->setIdResv($idReserv);
    $resvObj->setId($idGuest);
    $resvObj->setIdPsg($idPsg);
    $resvObj->setIdVisit($idVisit);
    $resvObj->setSpan($span);
    $resvObj->setSpanStatus($visitStatus);

} else {

    $mk1 = HTMLContainer::generateMarkup('h2', 'Reservation Id is missing.');

}


$resvAr = $resvObj->toArray();
$resvAr['patBD'] = $resvObj->getPatBirthDateFlag();
$resvAr['gstBD'] = $resvObj->getGuestBirthDateFlag();
$resvAr['patAddr'] = $uS->PatientAddr;
$resvAr['gstAddr'] = $uS->GuestAddr;
$resvAr['addrPurpose'] = $resvObj->getAddrPurpose();
$resvAr['patAsGuest'] = $resvObj->getPatAsGuestFlag();
$resvAr['emergencyContact'] = isset($uS->EmergContactFill) ? $uS->EmergContactFill : FALSE;
$resvAr['isCheckin'] = TRUE;
$resvAr['insistCkinEmail'] = $uS->insistCkinEmail;
$resvAr['insistCkinPhone'] = $uS->insistCkinPhone;
$resvAr['insistCkinAddress'] = $uS->insistCkinAddress;
$resvAr['insistCkinEmail'] = $uS->insistCkinEmail;
$resvAr['insistCkinPhone'] = $uS->insistCkinPhone;
$resvAr['insistCkinAddress'] = $uS->insistCkinAddress;
$resvAr['insistPayFilledIn'] = $uS->InsistCkinPayAmt;
$resvAr['datePickerButtons'] = $uS->RegNoMinorSigLines;

$resvObjEncoded = json_encode($resvAr);

$resvManagerOptions = [];

if ($visitStatus != '' && $visitStatus != VisitStatus::CheckedIn) {
    $pageHdr = 'Visit';
    $bkgrdColor = 'background-color:#f2f2f2';
}


if($uS->UseIncidentReports){
	$resvManagerOptions["UseIncidentReports"] = true;
}else{
	$resvManagerOptions["UseIncidentReports"] = false;
}

$resvManagerOptions["closedDays"] = [];

$resvManagerOptionsEncoded = json_encode($resvManagerOptions);

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $wInit->pageTitle; ?></title>
        <meta http-equiv="x-ua-compatible" content="IE=edge">
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo DR_PICKER_CSS ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo MULTISELECT_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
		<?php echo UPPLOAD_CSS; ?>

		<?php echo INCIDENT_CSS; ?>
		<?php echo GRID_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo CSSVARS; ?>
        <?php echo BOOTSTRAP_ICONS_CSS; ?>
        <?php echo BOOTSTRAP_ICONS_CSS; ?>

<!--        Fix the ugly checkboxes-->
        <style>
            .ui-icon-background, .ui-state-active .ui-icon-background {background-color:#fff;}
        </style>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo LIBPHONENUMBER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo ADDR_PREFS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MULTISELECT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo DR_PICKER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VISIT_DIALOG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BUFFER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BUFFER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo HTMLENTITIES_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo DOMPURIFY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTES_VIEWER_JS ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_MANAGER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JSIGNATURE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo INCIDENT_REP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo SMS_DIALOG_JS; ?>"></script>
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
		<?php if ($uS->UseDocumentUpload) { echo '<script type="text/javascript" src="' . UPPLOAD_JS . '"></script>';
        ?>
        	<script>
        		$(document).ready(function(){
        			window.uploader = new Upploader.Uppload({lang: Upploader.en});
        		});
        	</script>
        <?php
            echo '<script type="text/javascript" src="' . DOC_UPLOAD_JS . '"></script>';
        }?>


    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $wInit->generatePageMenu() ?>
        <div id="contentDiv" class="container-fluid" style="margin-left: auto; <?php echo $bkgrdColor; ?>">
            <h1><?php echo $pageHdr; ?> <span id="spnStatus" style="display:inline;"></span></h1>

            <div id="paymentMessage" style="display:none;" class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox my-2">
                <?php echo $paymentMarkup; ?>
            </div>
            <div id="guestSearch" class="my-3 hhk-panel row" style="margin-right:10px; margin-left:10px;">
                <?php echo $mk1; ?>
            </div>
            <form action="CheckingIn.php" method="post"  id="form1">
                <div id="datesSection" style="display:none;" class="ui-widget ui-widget-header ui-state-default ui-corner-all hhk-panel mb-3"></div>
                <div id="famSection" style="font-size: .9em; display:none; max-width: 100%;" class="ui-widget hhk-visitdialog mb-3"></div>
                <div id="hospitalSection" style="font-size: .9em; display:none;"  class="ui-widget hhk-visitdialog mb-3"></div>
                <div id="resvSection" style="font-size:.9em; display:none; margin-bottom: 70px;" class="ui-widget hhk-visitdialog"></div>
                <div id="submitButtons" class="ui-corner-all" style="font-size:.9em; clear:both;">
                    <table >
                        <tr><td>
                            <span id="pWarnings" style="display:none; font-size: 1.4em; border: 1px solid #ddce99;margin-bottom:3px; padding: 0 2px; color:red; background-color: yellow; float:right;"></span>
                        </td></tr>
                        <tr><td>
                            <input type="button" id="btnShowReg" value='Show Registration Form' style="display:none;"/>
                            <input type='button' id='btnDone' value='Continue' style="display:none;"/>
                        </td></tr>
                    </table>
                </div>
            </form>
            <div id="pmtRcpt" style="font-size: .9em; display:none;"><?php echo $receiptMarkup; ?></div>
            <div id="activityDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.9em;"></div>
            <div id="faDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.9em;"></div>
            <div id="keysfees" style="display:none;font-size: .85em;"></div>
            <div id="hsDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.9em;"></div>
            <div id="vehDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.8em;"></div>
            <div id="ecSearch" style="display:none;">
                <table>
                    <tr>
                        <td>Search: </td><td><input type="text" id="txtemSch" size="15" value="" title="Type at least 3 letters to invoke the search."/></td>
                    </tr>
                    <tr><td><input type="hidden" value="" id="hdnEcSchPrefix"/></td></tr>
                </table>
            </div>
        </div>
        <input type="hidden" value="<?php echo RoomRateCategories::Fixed_Rate_Category; ?>" id="fixedRate"/>
        <input type="hidden" value="<?php echo $payFailPage; ?>" id="payFailPage"/>
        <input type="hidden" value="<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>" id="dateFormat"/>
        <input type="hidden" value='<?php echo $resvObjEncoded; ?>' id="resv"/>
        <input  type="hidden" id="rctMkup" value='<?php echo $receiptMarkup; ?>' />
        <input  type="hidden" id="receiptPaymentId" value='<?php echo $receiptPaymentId; ?>' />
        <input  type="hidden" id="receiptBilledToEmail" value='<?php echo $receiptBilledToEmail; ?>' />
        <input type="hidden" value='<?php echo $resvManagerOptionsEncoded; ?>' id="resvManagerOptions"/>
        <?php if ($uS->PaymentGateway == AbstractPaymentGateway::DELUXE) { echo DeluxeGateway::getIframeMkup(); } ?>

        <script type="text/javascript" src='<?php echo CHECKINGIN_JS; ?>'></script>

        <form name="xform" id="xform" method="post"><input type="hidden" name="CardID" id="CardID" value=""/></form>
    </body>
</html>
