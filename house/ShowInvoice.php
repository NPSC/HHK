<?php

use HHK\Notification\Mail\HHKMailer;
use HHK\sec\{Session, WebInit};
use HHK\SysConst\WebPageCode;
use HHK\Payment\Invoice\Invoice;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLContainer;

/**
 * ShowInvoice.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");


$wInit = new webInit(WebPageCode::Page);
$pageTitle = $wInit->pageTitle;

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$uS = Session::getInstance();
$logoUrl = $uS->resourceURL . 'images/registrationLogo.png';


$stmtMarkup = '';
$emAddr = '';
$emtableMarkup = '';
$msg = '';
$sty = '';
$guest = NULL;
$invNum = '';

if (isset($_GET["invnum"])) {
    $invNum = filter_var($_GET["invnum"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

// Catch post-back
if (isset($_POST['hdninvnum'])) {
    $invNum = filter_var($_POST["hdninvnum"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

try {

    if ($invNum != '') {

        $invoice = new Invoice($dbh, $invNum);
        $stmtMarkup = $invoice->createMarkup($dbh);

        if (isset($_POST['btnWord'])) {


            $form = "<!DOCTYPE html>"
                    . "<html>"
                        . "<head>"
                            . "<style type='text/css'>" . file_get_contents('css/house.css') . "</style>"
                        . "</head>"
                        . "<body><div class='ui-widget ui-widget-content ui-corner-all hhk-panel'" . $stmtMarkup . '</div></body>'
                    . '</html>';

            header('Content-Disposition: attachment; filename=Invoice.doc');
            header("Content-Description: File Transfer");
            header('Content-Type: text/html');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Expires: 0');

            echo($form);
            exit();

        }

        if (isset($_POST['txtEmail'])) {
            $emAddr = filter_var($_POST['txtEmail'], FILTER_SANITIZE_EMAIL);
            if ($emAddr == '') {
                $msg .= "The Email address is required.  ";
            }
        }

        if (isset($_POST['txtSubject'])) {
            $emSubject = filter_var($_POST['txtSubject'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if ($emSubject == '') {
                $msg .= "The Subject is required.  ";
            }
        }

        if (isset($_POST['btnEmail']) && $emAddr != '' && $emSubject != '' && $stmtMarkup != '') {

            try{
                $mail = new HHKMailer($dbh);

                $mail->From = $uS->FromAddress;
                $mail->FromName = htmlspecialchars_decode($uS->siteName, ENT_QUOTES);
                $mail->addAddress($emAddr);     // Add a recipient
                $mail->addReplyTo($uS->ReplyTo);

                $mail->isHTML(true);

                $mail->Subject = htmlspecialchars_decode($emSubject, ENT_QUOTES);
                $mail->msgHTML($stmtMarkup);

                $mail->send();
                $msg .= "Email sent.  ";
            }catch(\Exception $e){
                $msg .= "Email failed!  " . $mail->ErrorInfo;
            }
        }

        $emSubject = $wInit->siteName . " Invoice";

        // if (is_null($guest) === FALSE && $emAddr == '') {
        //     $email = $guest->getEmailsObj()->get_data($guest->getEmailsObj()->get_preferredCode());
        //     $emAddr = $email["Email"];
        // }



    // create send email table
        if ($invoice->isDeleted() === FALSE) {
            $emTbl = new HTMLTable();
            $emTbl->addBodyTr(HTMLTable::makeTd('Subject: ' . HTMLInput::generateMarkup($emSubject, array('name' => 'txtSubject', 'style' => 'width: 100%; margin-left: 0.5em;')), array("class"=>"hhk-flex", "style"=>"align-items:center;")));
            $emTbl->addBodyTr(HTMLTable::makeTd(
                            'Email: '
                            . HTMLInput::generateMarkup($emAddr, array('name' => 'txtEmail', 'style' => 'width:100%; margin-left: 0.5em;'))
                . HTMLInput::generateMarkup($invNum, array('name' => 'hdninvnum', 'type' => 'hidden')), array("class"=>"hhk-flex", "style"=>"align-items:center;")));
            $emTbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup('Send Email', array('name' => 'btnEmail', 'type' => 'submit', 'style'=>'font-size:0.9em;'))));

            $emtableMarkup .= $emTbl->generateMarkup(array("style"=>"width:100%;"), 'Email Invoice');
        }
    } else {
        $msg .= 'Invoice not found.';
    }
} catch (Exception $ex) {
    $msg .= $ex->getMessage();
}


if ($msg != '') {
    $msg = HTMLContainer::generateMarkup('div', $msg, array('class' => 'ui-state-highlight ui-widget ui-widget-content ui-corner-all', 'style' => 'font-size:14pt; padding: 0.5em; margin-left:10px;'));
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo CSSVARS; ?>
        <?php echo $sty; ?>
        <?php echo FAVICON; ?>
        <style type="text/css" media="print">
            body {margin:0; padding:0; line-height: 1.4em; word-spacing:1px; letter-spacing:0.2px; font: 13px Arial, Helvetica,"Lucida Grande", serif; color: #000;}
            .hhk-noprint {display:none;}
        </style>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type='text/javascript'>
$(document).ready(function () {
    "use strict";
    $('#btnPrint, #btnEmail, #btnWord').button();
    var opt = {mode: 'popup',
        popClose: true,
        popHt      : $('#divBody').height(),
        popWd      : $('#divBody').width(),
        popX       : 20,
        popY       : 20,
        popTitle   : 'Print Invoice'};

    $('#btnPrint').click(function () {
        $("div.PrintArea").printArea(opt);
    });
});
        </script>
    </head>
    <body>
        <div id="contentDiv">
            <div style="float:left; margin-top:5px;margin-bottom:5px;" class="hhk-noprint">
                <?php echo $msg; ?>
            </div>
            <?php if($stmtMarkup != '') { ?>
            <div style="margin-top: 10px;">
                <div style='margin-bottom:10px; max-width:800px' class='hhk-noprint ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox'>
                    <form name="formEm" method="Post" action="ShowInvoice.php">
                    <?php echo $emtableMarkup; ?>
                        <input type="button" value="Print" id='btnPrint' style="margin-right:.3em;margin-top:.5em;font-size:0.9em;"/>
                        <input type="submit" value="Download MS Word" name='btnWord' id='btnWord' style="margin-right:.3em;margin-top:.5em; font-size:0.9em;"/>
                    </form>

                </div>
                <div id="divBody" style="max-width: 800px;" class='PrintArea ui-widget ui-widget-content ui-corner-all hhk-panel'>
                        <?php echo $stmtMarkup; ?>
                </div>
            </div>
            <?php } ?>
        </div>

    </body>
</html>
