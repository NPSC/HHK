<?php

use HHK\sec\{Session, WebInit, Labels};
use HHK\SysConst\WebPageCode;
use HHK\Member\Role\Guest;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\Payment\Statement;
use HHK\House\Visit\Visit;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLInput;
use HHK\Note\Note;
use HHK\Note\LinkNote;
use HHK\House\Registration;

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


if ($idRegistration > 0) {
    // Comprehensive Statement

    $priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
    $stmtMarkup = Statement::createComprehensiveStatements($dbh, $idRegistration, $includeLogo);


} else if ($idVisit > 0) {
    // Visit Statement

    $visit = new Visit($dbh, 0, $idVisit);


    // Generate Statement
    $guest = new Guest($dbh, '', $visit->getPrimaryGuestId());
    $name = $guest->getRoleMember();

    $stmtMarkup = Statement::createStatementMarkup($dbh, $idVisit, $name->get_fullName(), $includeLogo);

} else {
    $stmtMarkup = 'No Information.';
}

$stmtMarkup = HTMLContainer::generateMarkup('div', $stmtMarkup, array('id'=>'divStmt', 'style'=>'clear:left;max-width: 800px;font-size:.9em;', 'class'=>'PrintArea ui-widget ui-widget-content ui-corner-all hhk-panel'));

if (isset($_POST['btnWord'])) {


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

if (is_null($guest) === FALSE && $emAddr == '') {
    $email = $guest->getEmailsObj()->get_data($guest->getEmailsObj()->get_preferredCode());
    $emAddr = $email["Email"];
}


// create send email table
$emTbl = new HTMLTable();
$emTbl->addBodyTr(HTMLTable::makeTd('Subject: ' . HTMLInput::generateMarkup($emSubject, array('name'=>'txtSubject', 'size'=>'70', 'class'=>'ignrSave'))));
$emTbl->addBodyTr(HTMLTable::makeTd(
        'Email: '
        . HTMLInput::generateMarkup($emAddr, array('name'=>'txtEmail', 'size'=>'70', 'class'=>'ignrSave'))));
$emTbl->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('span','', array('id'=>'emMsg', 'style'=>'color:red;'))));
$emTbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup('Send Email', array('name'=>'btnEmail', 'type'=>'button', 'data-reg'=>$idRegistration, 'data-vid'=>$idVisit))));

$emtableMarkup .= HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('form',
		$emTbl->generateMarkup(array(), 'Email '.$labels->getString('MemberType', 'visitor', 'Guest') . ' Statement'), array('id'=>'formEm'))

        .HTMLContainer::generateMarkup('form',
                HTMLInput::generateMarkup('Print', array('id'=>'btnPrint', 'style'=>'margin-right:1em;'))
                .HTMLInput::generateMarkup('Download to MS Word', array('name'=>'btnWord', 'type'=>'submit'))
                .HTMLInput::generateMarkup($idRegistration, array('name'=>'hdnIdReg', 'type'=>'hidden'))
                .HTMLInput::generateMarkup($idVisit, array('name'=>'hdnIdVisit', 'type'=>'hidden'))
                , array('name'=>'frmwrod','action'=>'ShowStatement.php', 'method'=>'post'))
        ,array('style'=>'margin-bottom:10px;', 'class'=>'ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox'));


if (isset($_REQUEST['cmd'])) {

    $cmd = filter_var($_REQUEST['cmd'], FILTER_SANITIZE_STRING);

    if ($cmd == 'email') {

        $msg = '';

        if (isset($_POST['txtEmail'])) {
            $emAddr = filter_var($_POST['txtEmail'], FILTER_SANITIZE_EMAIL);
        }

        if (isset($_POST['txtSubject'])) {
            $emSubject = filter_var($_POST['txtSubject'], FILTER_SANITIZE_STRING);
        }

        if ($emAddr == '' || $emSubject == '') {
            $msg .= "The Email Address and Subject are both required.  ";
        } else if ($stmtMarkup == '') {
            $msg .= "No Statement.  ";
        } else {

            try{

                $mail = prepareEmail();

                $mail->From = $uS->FromAddress;
                $mail->FromName = $uS->siteName;
                $mail->addAddress($emAddr);     // Add a recipient
                $mail->addReplyTo($uS->ReplyTo);

                $bccs = explode(',', $uS->BccAddress);
                foreach ($bccs as $bcc) {
                    if ($bcc != '') {
                        $mail->addBCC(filter_var($bcc, FILTER_SANITIZE_EMAIL));
                    }
                }

                $mail->isHTML(true);

                $mail->Subject = $emSubject;
                $mail->msgHTML($stmtMarkup);


                $mail->send();
                $msg .= "Email sent.  ";

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
                $msg .= "Email failed! " . $mail->ErrorInfo;
            }

        }

        echo (json_encode(array('msg'=>$msg)));

    } else {
        echo "<script type='text/javascript'>" . createScript($labels->getString('Member', 'guest', 'Guest')) . "</script>" . $emtableMarkup . $stmtMarkup;
    }

    exit();

}




if ($msg != '') {
    $msg = HTMLContainer::generateMarkup('div', $msg, array('class'=>'ui-state-highlight', 'style'=>'font-size:14pt;'));
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
        <style type="text/css" media="print">
            .PrintArea {margin:0; padding:0; font: 12px Arial, Helvetica,"Lucida Grande", serif; color: #000;}
            @page { margin: 1cm; }
        </style>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type='text/javascript'>
$(document).ready(function() {
    "use strict";
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
        popTitle   : '<?php echo $labels->getString('MemberType', 'guest', 'Guest'); ?>'+' Statement'};

    $('#btnPrint').click(function() {
        $('div.PrintArea').printArea(opt);
    });
});
</script>
    </head>
    <body>
        <div id="contentDiv">
        	<div style="width:800px;">
                <?php echo $msg; ?>
                <?php echo $emtableMarkup; ?>
                <div class="hhk-tdbox hhk-visitdialog">
                    <?php echo $stmtMarkup; ?>
                </div>
            </div>
        </div>
    </body>
</html>
