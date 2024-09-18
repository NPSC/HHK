<?php

use HHK\Notification\Mail\HHKMailer;
use HHK\sec\{Session, WebInit};
use HHK\SysConst\WebPageCode;
use HHK\Payment\Invoice\Invoice;
use HHK\HTMLControls\HTMLContainer;
use HHK\TableLog\HouseLog;
use Mpdf\Mpdf;
use PHPMailer\PHPMailer\PHPMailer;

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
$emAddrs = [];
$emtableMarkup = '';
$msg = '';
$emBody = '';
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
        $stmtMarkup = $invoice->createPDFMarkup($dbh);

        $emAddrs[] = $invoice->getBillToEmail($dbh);

        if (isset($_POST['btnWord'])) {

            HouseLog::logDownload($dbh, "Invoice", "Word", "Invoice $invNum Word Doc downloaded", $uS->username);

            $form = "<!DOCTYPE html>"
                    . "<html>"
                        . "<head>"
                            . "<style type='text/css'>" . file_get_contents("css/house.css") . file_get_contents("css/invoice.css") . "</style>"
                        . "</head>"
                        . "<body><div class='PrintArea'>" . $stmtMarkup . '</div></body>'
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
            $emAddr = filter_var($_POST['txtEmail'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if ($emAddr == '') {
                $msg .= "The Email address is required.  ";
            } else {
                if (str_contains($emAddr, ",")) { //assume multiple addresses
                    $emAddrs = explode(",", $emAddr);
                } else {
                    $emAddrs = [$emAddr];
                }
                foreach($emAddrs as $key=>$addr){
                    $emAddrs[$key] = filter_var($addr, FILTER_SANITIZE_EMAIL);
                    if($emAddrs[$key] == ""){
                        unset($emAddrs[$key]);
                    }
                }
            }
            
        }

        if (isset($_POST['txtSubject'])) {
            $emSubject = filter_var($_POST['txtSubject'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if ($emSubject == '') {
                $msg .= "The Subject is required.  ";
            }
        }

        if (isset($_POST['txtBody'])) {
            $emBody = str_replace(["\r\n", "\r", "\n"], "<br/>", filter_var($_POST['txtBody'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            if ($emBody == '') {
                $msg .= "The email Body is required.  ";
            }
        }

        if(isset($_GET['pdfDownload']) && $stmtMarkup != ''){
            $invoice->makePDF($dbh, true);
        }

        if (isset($_POST['btnEmail']) && count($emAddrs) > 0 && $emSubject != '' && $emBody && $stmtMarkup != '') {

            foreach ($emAddrs as $emAddr) {
                if ($emAddr != '') {
                    try {
                        $mail = new HHKMailer($dbh);

                        $mail->From = $uS->FromAddress;
                        $mail->FromName = htmlspecialchars_decode($uS->siteName, ENT_QUOTES);
                        $mail->addAddress($emAddr);     // Add a recipient
                        $mail->addReplyTo($uS->ReplyTo);

                        $mail->isHTML(true);

                        $mail->Subject = htmlspecialchars_decode($emSubject, ENT_QUOTES);
                        //$mail->msgHTML($stmtMarkup);
                        $mail->msgHTML($emBody);

                        $pdfContent = $invoice->makePDF($dbh);
                        $mail->addStringAttachment($pdfContent, 'Invoice.pdf', PHPMailer::ENCODING_BASE64, 'application/pdf');

                        $mail->send();
                        $msg .= "Email sent to " . $emAddr . ".  ";

                        //update invoice EmailDate
                        $invoice->setEmailDate($dbh, new DateTime(), $uS->username);
                    } catch (\Exception $e) {
                        $msg .= "Email failed!  " . $e->getMessage() . $mail->ErrorInfo;
                    }
                }
            }
        }

        $emSubject = $wInit->siteName . " Invoice";

        $emBody = $uS->InvoiceEmailBody;

        // create send email table
        if ($invoice->isDeleted() === FALSE) {
            $emtableMarkup = Invoice::makeEmailTbl("<strong class='mr-2'>" . $uS->siteName . "</strong><small>&lt;" . $uS->FromAddress . "&gt;</small>", $emSubject, $emAddrs, $emBody, $invNum);
        }
    } else {
        $msg .= 'Invoice not found.';
    }
} catch (Exception $ex) {
    $msg .= $ex->getMessage();
}


if ($msg != '') {
    $msg = HTMLContainer::generateMarkup('div', $msg, array('class' => 'ui-state-highlight ui-widget ui-widget-content ui-corner-all d-inline-block p-2 ml-3'));
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo INVOICE_CSS; ?>
        <?php echo CSSVARS; ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>
        <?php echo BOOTSTRAP_ICONS_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
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
            <div class=" my-3 hhk-noprint">
            <?php echo $msg; ?>
            </div>
            <?php if($stmtMarkup != '') { ?>
            <div class="mt-3 invPage">
                <div class='hhk-noprint'>
                    <form class="formEm" name="formEm" method="POST" action="ShowInvoice.php">
                        <?php echo $emtableMarkup; ?>
                        
                    </form>

                </div>
                <div id="divBody" class='ui-widget ui-widget-content ui-corner-all PrintArea hhk-panel'>
                    <?php echo $stmtMarkup; ?>
                </div>
            </div>
            <?php } ?>
        </div>
    </body>
</html>
