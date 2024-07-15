<?php

use HHK\sec\{Session, WebInit, Labels};
use HHK\Payment\PaymentSvcs;
use HHK\SysConst\WebPageCode;
use HHK\HTMLControls\HTMLContainer;
use HHK\House\Registration;
use HHK\House\Reservation\ReservationSvcs;
use HHK\HTMLControls\HTMLInput;
use HHK\Document\Document;
use HHK\House\RegistrationForm\CustomRegisterForm;


/**
 * ShowRegForm.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");

$wInit = new webInit(WebPageCode::Page);
$pageTitle = $wInit->pageTitle;

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$uS = Session::getInstance();
$labels = Labels::getLabels();

$tabControl = "";
$idVisit = 0;
$idResv = 0;
$span = 0;
$idRegistration = 0;
$idDoc = 0;
$sty = "";
$regContents = "";
$idPayment = 0;
$paymentMarkup = '';
$regDialogmkup = '';
$receiptMarkup = '';
$invoiceNumber = '';
$menuMarkup = '';
$regButtonStyle = 'display:none;';
$showSignedTab = false;
$isTopazRequired = false;
$blankFormTitle = "Registration Form";
$signatures = array();


// Hosted payment return
try {

    if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $_REQUEST)) === FALSE) {

        $receiptMarkup = $payResult->getReceiptMarkup();

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
            $data = [($payResult->wasError() ? "error" : "success") => $payResult->getDisplayMessage()];
            if($payResult->wasError() == false){
                $data["gotopage"] = 'ShowRegForm.php?regid=' . $payResult->getIdRegistration() . '&vid=' . $_GET['vid'] . '&payId=' . $payResult->getIdPayment() . '&invoiceNumber=' . $payResult->getInvoiceNumber();
            }
            echo json_encode($data);
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

if (isset($_REQUEST['regid'])) {
    $idRegistration = intval(filter_var($_REQUEST['regid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS), 10);
}

if (isset($_GET['vid'])) {
    $idVisit = intval(filter_var($_REQUEST['vid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS), 10);
}

if (isset($_GET['payId'])) {
    $idPayment = intval(filter_var($_REQUEST['payId'], FILTER_SANITIZE_FULL_SPECIAL_CHARS), 10);
}

if (isset($_GET['invoiceNumber'])) {
    $invoiceNumber = filter_var($_REQUEST['invoiceNumber'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

if (isset($_GET['span'])) {
    $span = intval(filter_var($_REQUEST['span'], FILTER_SANITIZE_FULL_SPECIAL_CHARS), 10);
}

if (isset($_GET['rid'])) {
    $idResv = intval(filter_var($_REQUEST['rid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS), 10);
}

if(isset($_GET["idDoc"])){
    $idDoc = intval(filter_var($_REQUEST['idDoc'], FILTER_SANITIZE_FULL_SPECIAL_CHARS), 10);
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

if($idDoc > 0){
    $doc = new Document($idDoc);
    $doc->loadDocument($dbh);
    if($doc->getType() == "reg"){
        $regContents = (str_starts_with($doc->getMimeType(), "base64:") ? base64_decode($doc->getDoc()) : $doc->getDoc());
        if($uS->RegForm == "3"){
            $form = new CustomRegisterForm();
            $docSignatures = json_decode($doc->getUserData());
            if($docSignatures){
                $signatures['vreg'] = $docSignatures;
            }
        }
    }

}

if($idVisit || $idResv){

    // Generate Registration Form
    $reservArray = ReservationSvcs::generateCkinDoc($dbh, $idResv, $idVisit, $span, '../conf/registrationLogo.png');
    $signedDocsArray = ReservationSvcs::getSignedCkinDocs($dbh, (isset($reservArray['idPsg']) ? $reservArray['idPsg']: 0), (isset($reservArray['idReservation']) ? $reservArray['idReservation'] : $idResv), $idVisit);

    $li = '';
    $tabContent = '';
    $uuid = uniqid();
    $uS->regFormObjs = [$uuid=>$reservArray['docs']]; //save docs to session for signing

    foreach ($reservArray['docs'] as $r) {

        $li .= HTMLContainer::generateMarkup('li',
                HTMLContainer::generateMarkup('a', $r['tabTitle'] , array('href'=>'#'.$r['tabIndex'])));

        $tabContent .= HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('button', 'Print', array('class'=>'btnPrint mb-3', 'data-tab'=>$r['tabIndex'], 'data-title'=>(!empty($r["pageTitle"]) ? $r["pageTitle"] : $labels->getString('MemberType', 'guest', 'Guest') . ' Registration Form')))
            . (isset($r['allowSave']) && $r['allowSave'] ? HTMLContainer::generateMarkup('button', 'Save', array('class'=>'btnSave mb-3 ml-3', 'data-tab'=>$r['tabIndex'], 'data-uuid'=>$uuid)) : '')
            .HTMLContainer::generateMarkup('div', $r['doc'], array('class'=>'PrintArea'))
            .HTMLContainer::generateMarkup('button', 'Print', array('class'=>'btnPrint mt-4', 'data-tab'=>$r['tabIndex'], 'data-title'=>(!empty($r["pageTitle"]) ? $r["pageTitle"] : $labels->getString('MemberType', 'guest', 'Guest') . ' Registration Form')))
            . (isset($r['allowSave']) && $r['allowSave'] ? HTMLContainer::generateMarkup('button', 'Save', array('class'=>'btnSave mt-4 ml-3', 'data-tab'=>$r['tabIndex'],  'data-uuid'=>$uuid)): ''),
            array('id'=>$r['tabIndex']));

        $sty = $r['style'];

        //is Topaz sigWeb required?
        if(!empty($r['signType']) && $r['signType'] == 'topaz'){
            $isTopazRequired = true;
        }
    }

    $tabControl = "";
    if(count($reservArray['docs']) > 0){
        $ul = HTMLContainer::generateMarkup('ul', $li, array());
        $tabControl = HTMLContainer::generateMarkup('div', $ul . $tabContent, array('id'=>'regTabDiv'));
    }else if(isset($reservArray['error'])){
        $tabControl = HTMLContainer::generateMarkup("div", $reservArray['error'], array("class"=>"ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox my-2"));
    }

    $signedLi = '';
    $signedTabContent = '';

    $signedDocCount = count($signedDocsArray);
    if($signedDocCount > 0){
        $showSignedTab = true;
        
        $blankFormTitle = "Blank Registration Form";
        
        foreach ($signedDocsArray as $r) {

            $signedDate = new \DateTime($r['timestamp']);

            $signedLi .= HTMLContainer::generateMarkup('li',
                HTMLContainer::generateMarkup('a', "Signed on " . $signedDate->format("M j, Y") , array('href'=>'#'.$r['Doc_Id'])));


            $signedTabContent .= HTMLContainer::generateMarkup('div',
                HTMLInput::generateMarkup('Print', array('type'=>'button', 'class'=>'btnPrint mb-3', 'data-tab'=>$r['Doc_Id'], 'data-title'=>$labels->getString('MemberType', 'guest', 'Guest') . ' Registration Form'))
                .(str_starts_with($r['Mime_Type'], "base64:") ? base64_decode($r['Doc']) : $r['Doc'])
                .HTMLInput::generateMarkup('Print', array('type'=>'button', 'class'=>'btnPrint mt-4', 'data-tab'=>$r['Doc_Id'], 'data-title'=>$labels->getString('MemberType', 'guest', 'Guest') . ' Registration Form')),
                array('id'=>$r['Doc_Id']));
            $docSignatures = json_decode($r["Signatures"], true);
            if($docSignatures){
                $signatures[$r["Doc_Id"]] = $docSignatures;
            }
        }

        $signedUl = HTMLContainer::generateMarkup('ul', $signedLi, array());
        $signedTabControl = HTMLContainer::generateMarkup('div', $signedUl . $signedTabContent, array('id'=>'signedRegTabDiv'));
    }

}else if($idDoc > 0){
    $tabControl = HTMLContainer::generateMarkup('div',
        HTMLInput::generateMarkup(
            'Print', ['type'=>'button', 'class'=>'btnPrint mb-3', 'data-tab'=>'', 'data-title'=>$labels->getString('MemberType', 'guest', 'Guest') . ' Registration Form']) .
        $regContents
    );
}
//"<span class='ui-icon ui-icon-extlink' style='float: right; margin-left: .3em;'></span>"

$shoStmtBtn = HTMLInput::generateMarkup("Show Statement", array('type'=>'button', 'id'=>'btnStmt', 'style'=>$regButtonStyle));
$shoRegBtn = HTMLInput::generateMarkup('Check In Followup', array('type'=>'button', 'id'=>'btnReg', 'style'=>$regButtonStyle));

$regMessage = HTMLContainer::generateMarkup('div', '', array('id'=>'mesgReg', 'style'=>'color: darkgreen; clear:left; font-size:1.5em;display:none;'));

$contrls = HTMLContainer::generateMarkup('div', $shoRegBtn . $shoStmtBtn . $regMessage, array('class'=>'my-2'));

//unset($reservArray);

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo CSSVARS; ?>
        <?php echo BOOTSTRAP_ICONS_CSS; ?>
        <?php echo ($uS->RegForm == 3 ? CUSTOM_REGFORM_CSS : ""); ?>

        <style type="text/css" media="print">
            .PrintArea {margin:0; padding:0; font: 12px Arial, Helvetica,"Lucida Grande", serif; color: #000;}
            @page { margin: .5cm; }
        </style>
        <?php echo $sty; ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JSIGNATURE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <?php echo ($isTopazRequired ? '<script type="text/javascript" src="' . TOPAZ_SIGWEB_JS . '"></script>': ''); ?>
        <script type="text/javascript" src="<?php echo REG_FORM_JS; ?>"></script>

        <script type='text/javascript'>

            $(document).ready(function(){
                let idReg = '<?php echo $idRegistration; ?>';
                let rctMkup = '<?php echo $receiptMarkup; ?>';
                let regMarkup = '<?php echo $regDialogmkup; ?>';
                let payId = '<?php echo $idPayment; ?>';
                let invoiceNumber = '<?php echo $invoiceNumber; ?>';
                let vid = '<?php echo $idVisit; ?>';
                let rid = '<?php echo $idResv ?>';
                let idPrimaryGuest = '<?php echo (isset($reservArray['idPrimaryGuest']) ? $reservArray['idPrimaryGuest'] : 0) ?>';
                let idPsg = '<?php echo (isset($reservArray['idPsg']) ? $reservArray['idPsg'] : 0) ?>';
                let signatures = <?php echo json_encode($signatures); ?>;

                setupRegForm(idReg, rctMkup, regMarkup, payId, invoiceNumber, vid, rid, idPrimaryGuest, idPsg);
                setupEsign();
                loadSignatures(signatures);
            });
        </script>
    </head>
    <body>
 <?php echo $menuMarkup; ?>
        <div id="contentDiv" >
            <div id="paymentMessage" style="display:none;" class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox my-2">
                <?php echo $paymentMarkup; ?>
            </div>

            <div id="mainTabs" class="mt-2" style="max-width:900px; display:none; font-size:.9em;">
                <ul>
                    <li id="liReg"><a href="#vreg"><?php echo $blankFormTitle; ?></a></li>
                    <?php if($showSignedTab){ ?>
                    <li id="liSignedReg"><a href="#vsignedReg">Signed Registration Forms (<?php echo $signedDocCount; ?>)</a></li>
                    <?php } ?>
                </ul>
                <div id="vreg" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none; ">
                    <?php echo $contrls; ?>
                    <?php echo $tabControl; ?>
                </div>
                <?php if($showSignedTab){ ?>
                <div id="vsignedReg" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none; ">
                    <?php echo $signedTabControl; ?>
                </div>
                <?php } ?>
            </div>
            <div id="vperm" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none; ">
                <h2>No permission forms were found.</h2>
            </div>
            <div id="pmtRcpt" style="font-size: .9em; display:none;"></div>
            <div id="regDialog"></div>
            <div id="jSignDialog" style="display:none;">
            	<input type="hidden" id="idName">
            	<input type="hidden" id="formCode">
                <input type="hidden" id="idBtn">
            	<p style="text-align:center">Use your mouse, finger or touch pen to sign</p>
            	<div class="signature ui-widget-content ui-corner-all"></div>
            </div>
            <div id="topazDialog" style="display:none; text-align:center;">
            	<input type="hidden" id="idName">
            	<input type="hidden" id="formCode">
                <input type="hidden" id="idBtn">
            	<p style="text-align:center">Use your Topaz Signature Pad to sign</p>
            	<canvas name="signature" id="sigImg" class="signature ui-widget-content ui-corner-all" width="500" height="100"></canvas>
            	<div class="alertContainer" id="sigWebAlert" style="display:none;">
                    <div id="alertMessage" style="margin-top:5px;margin-bottom:5px; " class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox">

                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
