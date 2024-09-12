<?php

use HHK\Notification\Mail\HHKMailer;
use HHK\sec\{Session, WebInit, Labels};
use HHK\SysConst\WebPageCode;
use HHK\Member\Role\Guest;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\Payment\Statement;
use HHK\House\Visit\Visit;
use HHK\HTMLControls\HTMLContainer;
use HHK\Note\Note;
use HHK\Note\LinkNote;
use HHK\House\Registration;
use HHK\TableLog\HouseLog;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * ShowStatement.php
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

$idVisit = 0;
$idGuest = 0;
$idRegistration = 0;
$msg = '';

$guest = NULL;
$sty = '';
$emtableMarkup = '';
$emAddr = '';

$includeLogo = TRUE;


function createScript($guestLabel) {
    return "
    $('#btnPrint, #btnEmail, #btnWord').button();
    $('#btnEmail').click(function () {
        if ($('#btnEmail').val() == 'Sending...') {
            return;
        }
        $('#emMsg').text('');
        if ($('#txtEmail').val() === '') {
            $('#emMsg').text('Enter an Email Address.  ').css('color', 'red');
            return;
        }
        if ($('#txtSubject').val() === '') {
            $('#emMsg').text('Enter a Subject line.').css('color', 'red');
            return;
        }
        $('#btnEmail').val('Sending...');
        $.post('ShowStatement.php', $('#formEm').serialize() + '&cmd=email' + '&reg=' + $(this).data('reg') + '&vid=' + $(this).data('vid'), function(data) {
            $('#btnEmail').val('Send Email');
            try {
                data = $.parseJSON(data);
            } catch (err) {
                alert('Bad JSON Encoding');
                return;
            }
            if (data.error) {
                if (data.gotopage) {
                    window.open(data.gotopage, '_self');
                }
            }
            if (data.msg) {
                $('#emMsg').text(data.msg).css('color', 'red');
            }
        });
    });
    var opt = {mode: 'popup',
        popClose: true,
        popHt      : $('#divStmt').height(),
        popWd      : $('#divStmt').width(),
        popX       : 20,
        popY       : 20,
        popTitle   : '$guestLabel' +' Statement'};

    $('#btnPrint').click(function() {
        $('div.PrintArea').printArea(opt);
    });
    ";
}

if (isset($_REQUEST['vid'])) {
    $idVisit = intval(filter_var($_REQUEST["vid"], FILTER_SANITIZE_NUMBER_INT), 10);
}

if (isset($_REQUEST['reg'])) {
    $idRegistration = intval(filter_var($_REQUEST['reg'], FILTER_SANITIZE_NUMBER_INT), 10);
}

if (isset($_POST['hdnIdReg'])) {
    $idRegistration = intval(filter_var($_REQUEST['hdnIdReg'], FILTER_SANITIZE_NUMBER_INT), 10);
    $includeLogo = FALSE;
}

if (isset($_POST['hdnIdVisit'])) {
    $idVisit = intval(filter_var($_REQUEST["hdnIdVisit"], FILTER_SANITIZE_NUMBER_INT), 10);
    $includeLogo = FALSE;
}

$statementTitle = "";
if ($idRegistration > 0) {
    // Comprehensive Statement

    $priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
    $stmtMarkup = Statement::createComprehensiveStatements($dbh, $idRegistration, $includeLogo);
    $statementTitle = "Comprehensive Statement for PSG $idRegistration";

} else if ($idVisit > 0) {
    // Visit Statement

    try {
        $visit = new Visit($dbh, 0, $idVisit);


        // Generate Statement
        $guest = new Guest($dbh, '', $visit->getPrimaryGuestId());
        $name = $guest->getRoleMember();

        $stmtMarkup = Statement::createStatementMarkup($dbh, $idVisit, $name->get_fullName(), $includeLogo);
    }catch(\Exception $e){
        $msg = $e->getMessage();
        $stmtMarkup = $e->getMessage();
    }

    $statementTitle = "Visit $idVisit";

} else {
    $stmtMarkup = 'No Information.';
}

$stmtMarkup = HTMLContainer::generateMarkup('div', $stmtMarkup, array('id'=>'divStmt', 'class'=>'PrintArea ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog'));

if (isset($_POST['btnWord'])) {

    HouseLog::logDownload($dbh, "Statement", "Word", "Statement Word Doc for $statementTitle downloaded", $uS->username);

    $form = "<!DOCTYPE html>"
            . "<html>"
                . "<head>"
                    . "<style type='text/css'>" . file_get_contents('css/jqui/jquery-ui.min.css') . "</style>"
                    . "<style type='text/css'>" . file_get_contents('css/house.css') . "</style>"
                . "</head>"
                . "<body><div class='ui-widget ui-widget-content ui-corner-all hhk-panel'" . $stmtMarkup . '</div></body>'
            . '</html>';

    header('Content-Disposition: attachment; filename=Statement.doc');
    header("Content-Description: File Transfer");
    header('Content-Type: text/html');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');

    echo($form);
    exit();

}

$emSubject = $wInit->siteName .' '. $labels->getString('MemberType', 'visitor', 'Guest')." Statement";

$emBody = "Hello,\n" . 
        "Your Statement from " . $uS->siteName . " is attached.\n\r" . 
        "Thank You,\n" . 
        $uS->siteName;

if (is_null($guest) === FALSE && $emAddr == '') {
    $email = $guest->getEmailsObj()->get_data($guest->getEmailsObj()->get_preferredCode());
    $emAddr = $email["Email"];
}
//echo Statement::createEmailStmtWrapper($stmtMarkup);
//exit;
$emtableMarkup = Statement::makeEmailTbl("<strong class='mr-2'>" . $uS->siteName . "</strong><small>&lt;" . $uS->FromAddress . "&gt;</small>",$emSubject, $emAddr, $emBody, $idRegistration, $idVisit);

if(isset($_GET['pdfDownload']) && $stmtMarkup != ''){
    Statement::makePDF($stmtMarkup, true);
}

if (isset($_REQUEST['cmd'])) {

    $cmd = filter_var($_REQUEST['cmd'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if ($cmd == 'email') {

        $return = [];

        if (isset($_POST['txtEmail'])) {
            $emAddr = filter_var($_POST['txtEmail'], FILTER_VALIDATE_EMAIL);
        }

        if (isset($_POST['txtSubject'])) {
            $emSubject = filter_var($_POST['txtSubject'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        if (isset($_POST['txtBody'])) {
            $emBody = str_replace(["\r\n", "\r", "\n"], "<br/>", filter_var($_POST['txtBody'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            if ($emBody == '') {
                $msg .= "The email Body is required.  ";
            }
        }

        if ($emAddr == '' || $emSubject == '') {
            $return["error"] = "Subject and Email must be present and valid.";
        } else if ($stmtMarkup == '') {
            $return["error"] = "No Statement.  ";
        } else {

            try{

                $mail = new HHKMailer($dbh);

                $mail->From = $uS->FromAddress;
                $mail->FromName = htmlspecialchars_decode($uS->siteName, ENT_QUOTES);
                $mail->addAddress($emAddr);     // Add a recipient
                $mail->addReplyTo($uS->ReplyTo);

                $bccs = explode(',', $uS->BccAddress);
                foreach ($bccs as $bcc) {
                    if ($bcc != '') {
                        $mail->addBCC(filter_var($bcc, FILTER_SANITIZE_EMAIL));
                    }
                }

                $mail->Subject = htmlspecialchars_decode($emSubject, ENT_QUOTES);
                //$mail->msgHTML(Statement::createEmailStmtWrapper($stmtMarkup));
                $mail->msgHTML($emBody);
                $mail->addStringAttachment(Statement::makePDF($stmtMarkup), "Statement.pdf", PHPMailer::ENCODING_BASE64, 'application/pdf');

                $mail->send();
                $return["msg"] = "Email sent.";

                // Make a note in the visit or PSG
                if($idVisit > 0){
                    $noteText = 'Visit Statement email sent to ' . $emAddr;
                    LinkNote::save($dbh, $noteText, $idVisit, Note::VisitLink, '', $uS->username, $uS->ConcatVisitNotes);
                }elseif($idRegistration > 0){
                    $noteText = 'Comprehensive Statement email sent to ' . $emAddr;
                    $reg = new Registration($dbh, 0, $idRegistration);
                    $idPsg = $reg->getIdPsg();
                    LinkNote::save($dbh, $noteText, $idRegistration, Note::PsgLink, '', $uS->username, $uS->ConcatVisitNotes);
                }
            }catch (\Exception $e){
                $return["error"] = "Email failed! " . $mail->ErrorInfo;
                
            }

        }

        echo json_encode($return);

    } else {
        echo "<div id='stmtDiv'><script type='text/javascript'>" . createScript($labels->getString('Member', 'guest', 'Guest')) . "</script>" . $emtableMarkup . $stmtMarkup . "</div>";
    }

    exit();

}




if ($msg != '') {
    $msg = HTMLContainer::generateMarkup('div', $msg, array('class'=>'ui-state-highlight ui-corner-all', 'style'=>'font-size:14pt; padding:0.5em; margin-top: 0.5em'));
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
        <?php echo CSSVARS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo GRID_CSS; ?>
        <?php echo STATEMENT_CSS; ?>
        <?php echo BOOTSTRAP_ICONS_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type='text/javascript'>
$(document).ready(function() {
    "use strict";
    $('#btnPrint, #btnEmail, #btnWord').button();
    $('#btnEmail').click(function () {
        if ($('#btnEmail').val() == 'Sending...') {
            return;
        }
        if ($('#txtEmail').val() === '') {
            flagAlertMessage('Enter an Email Address.', 'error');
            return;
        }
        if ($('#txtSubject').val() === '') {
            flagAlertMessage('Enter a Subject line', 'error');
            return;
        }
        $('#btnEmail').val('Sending...');
        $.post('ShowStatement.php', $('#formEm').serialize() + '&cmd=email' + '&reg=' + $(this).data('reg') + '&vid=' + $(this).data('vid'), function(data) {
            $('#btnEmail').val('Send Email');
            try {
                data = $.parseJSON(data);
            } catch (err) {
                flagAlertMessage('Bad JSON Encoding', 'error');
                return;
            }
            if (data.error) {
                if (data.gotopage) {
                    window.open(data.gotopage, '_self');
                }
                flagAlertMessage(data.error, "error");
            }
            if (data.msg) {
                flagAlertMessage(data.msg, 'success');
            }
        });
    });

    var opt = {mode: 'popup',
        popClose: true,
        popHt      : $('#divStmt').height(),
        popWd      : $('#divStmt').width(),
        popX       : 20,
        popY       : 20,
        popTitle   : '<?php echo $labels->getString('MemberType', 'guest', 'Guest'); ?>'+' Statement'};

    $('#btnPrint').click(function() {
        $('div.PrintArea').printArea(opt);
    });
});
</script>
    </head>
    <body>
        <div id="contentDiv">
            <div id="stmtDiv" class="mt-3">
                <?php echo $msg; ?>
                <form id="formEm" name="formEm" method="POST" action="ShowStatement.php">
                    <?php echo $emtableMarkup; ?>
                </form>
                <?php echo $stmtMarkup; ?>
            </div>
        </div>

        
    </body>
</html>