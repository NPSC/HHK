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
$sty = "";
$blankFormTitle = "Registration Form";


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
        $regContents = $doc->getDoc();
        if($uS->RegForm == "3"){
            $form = new CustomRegisterForm();
            $sty = $form->getStyling();
        }
    }

}

if($idVisit || $idResv){

    // Generate Registration Form
    $reservArray = ReservationSvcs::generateCkinDoc($dbh, $idResv, $idVisit, $span, '../conf/registrationLogo.png');
    $signedDocsArray = ReservationSvcs::getSignedCkinDocs($dbh, (isset($reservArray['idPsg']) ? $reservArray['idPsg']: 0), $idResv, $idVisit);

    $li = '';
    $tabContent = '';

    foreach ($reservArray['docs'] as $r) {

        $li .= HTMLContainer::generateMarkup('li',
                HTMLContainer::generateMarkup('a', $r['tabTitle'] , array('href'=>'#'.$r['tabIndex'])));

        $tabContent .= HTMLContainer::generateMarkup('div',
            HTMLInput::generateMarkup('Print', array('type'=>'button', 'class'=>'btnPrint mb-3', 'data-tab'=>$r['tabIndex'], 'data-title'=>(!empty($r["pageTitle"]) ? $r["pageTitle"] : $labels->getString('MemberType', 'guest', 'Guest') . ' Registration Form')))
            . (isset($r['allowSave']) && $r['allowSave'] ? HTMLInput::generateMarkup('Save', array('type'=>'button', 'class'=>'btnSave mb-3 ml-3', 'data-tab'=>$r['tabIndex'])) : '')
            .HTMLContainer::generateMarkup('div', $r['doc'], array('class'=>'PrintArea'))
            .HTMLInput::generateMarkup('Print', array('type'=>'button', 'class'=>'btnPrint mt-4', 'data-tab'=>$r['tabIndex'], 'data-title'=>(!empty($r["pageTitle"]) ? $r["pageTitle"] : $labels->getString('MemberType', 'guest', 'Guest') . ' Registration Form')))
            . (isset($r['allowSave']) && $r['allowSave'] ? HTMLInput::generateMarkup('Save', array('type'=>'button', 'class'=>'btnSave mt-4 ml-3', 'data-tab'=>$r['tabIndex'])): ''),
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
                .$r['Doc']
                .HTMLInput::generateMarkup('Print', array('type'=>'button', 'class'=>'btnPrint mt-4', 'data-tab'=>$r['Doc_Id'], 'data-title'=>$labels->getString('MemberType', 'guest', 'Guest') . ' Registration Form')),
                array('id'=>$r['Doc_Id']));
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
$shoStmtBtn = HTMLContainer::generateMarkup("button", "Show Statement <span class='ui-icon ui-icon-extlink' style='float: right; margin-left: .3em;'></span>", array('class'=>'ml-2', 'id'=>'btnStmt', 'style'=>$regButtonStyle));
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
        <script type="text/javascript" src="<?php echo REG_FORM_ESIGN_JS; ?>"></script>

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
        popHt      : $('div.PrintArea').height(),
        popWd      : 950,
        popX       : 20,
        popY       : 20,
        popTitle   : '<?php echo $labels->getString('MemberType', 'guest', 'Guest'); ?>' + ' Registration Form',
        extraHead  : $('#regFormStyle').prop('outerHTML')};

    $('#mainTabs').tabs();

    $('.btnPrint').click(function() {
        opt.popHt = $(this).closest('.ui-tabs-panel').find('div.PrintArea').height();
        opt.popTitle = $(this).data('title');
        $(this).closest('.ui-tabs-panel').find('div.PrintArea').printArea(opt);
    }).button();

    $('.btnSave').click(function(){
    	var isSigned = ($(this).closest('.ui-tabs-panel').find("div.PrintArea .signDate:visible").length > 0);

    	if(!isSigned){
    		flagAlertMessage("<strong>Error:</strong> At least one signature is required", true);
    		return;
    	}
    	var docCode = $(this).data("tab");
    	$(this).closest('.ui-tabs-panel').find("div.PrintArea .btnSign").remove();
    	var formContent = $(this).closest('.ui-tabs-panel').find("div.PrintArea")[0].outerHTML;

    	var formData = new FormData();
		formData.append('cmd', 'saveRegForm');
		formData.append('guestId', '<?php echo (isset($reservArray['idPrimaryGuest']) ? $reservArray['idPrimaryGuest'] : 0) ?>');
		formData.append('psgId', '<?php echo (isset($reservArray['idPsg']) ? $reservArray['idPsg'] : 0) ?>');
		formData.append('idVisit', '<?php echo $idVisit; ?>');
		formData.append('idResv', '<?php echo $idResv; ?>');
		formData.append('docTitle', "Registration Form");
		formData.append('docContents', formContent);

		$.ajax({
			url: 'ws_ckin.php',
			dataType: 'JSON',
			type: 'post',
			data: formData,
			contentType: false,
			processData: false,
			success: function (data) {
				if (data.idDoc > 0) {
			    	flagAlertMessage("<strong>Success:</strong> Registration form saved successfully", false);
			    	$(".btnSave").hide();
			    } else {
			        if (data.error) {
						flagAlertMessage("<strong>Error: </strong>" + data.error, true);
			        } else {

			        }
			    }
			},
		});

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
        width: getDialogWidth(530),
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

    $('#mainTabs').show();
    $('#regTabDiv, #signedRegTabDiv').tabs();

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
            	<p style="text-align:center">Use your mouse, finger or touch pen to sign</p>
            	<div class="signature ui-widget-content ui-corner-all"></div>
            </div>
            <div id="topazDialog" style="display:none; text-align:center;">
            	<input type="hidden" id="idName">
            	<input type="hidden" id="formCode">
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
