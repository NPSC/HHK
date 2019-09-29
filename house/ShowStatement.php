<?php
/**
 * ShowStatement.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");

require(DB_TABLES . "visitRS.php");
require(DB_TABLES . "registrationRS.php");
require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'PaymentsRS.php');
require (DB_TABLES . 'PaymentGwRS.php');
require (DB_TABLES . 'ItemRS.php');


require CLASSES . 'FinAssistance.php';
require CLASSES . 'ValueAddedTax.php';
require CLASSES . 'Purchase/Item.php';
require(CLASSES . 'Purchase/RoomRate.php');

require PMT . 'Receipt.php';
require PMT . 'Invoice.php';
require (PMT . 'CreditToken.php');

require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';

require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (MEMBER . 'OrgMember.php');
require (MEMBER . "Addresses.php");
require (MEMBER . "EmergencyContact.php");

require(HOUSE . "psg.php");
require (HOUSE . 'Registration.php');
require (HOUSE . 'RoleMember.php');
require (HOUSE . 'Role.php');
require (HOUSE . 'Guest.php');
require (HOUSE . 'Patient.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'Reservation_1.php');
require (HOUSE . 'ReservationSvcs.php');
require (HOUSE . 'Visit.php');
require (HOUSE . 'RegisterForm.php');
require (HOUSE . 'RegistrationForm.php');
require (HOUSE . 'Vehicle.php');

$wInit = new webInit(WebPageCode::Page);
$pageTitle = $wInit->pageTitle;

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$uS = Session::getInstance();

$idVisit = 0;
$idGuest = 0;
$idRegistration = 0;
$msg = '';

$guest = NULL;
$sty = '';
$emtableMarkup = '';
$emAddr = '';

$includeLogo = TRUE;


function createScript() {
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
        popTitle   : 'Guest Statement'};

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

    $stmt1 = $dbh->query("select * from `vvisit_stmt` where `idRegistration` = $idRegistration order by `idVisit`, `Span`");
    $spans = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    if (count($spans) > 0) {

        $stmt = $dbh->query("SELECT s.idVisit, s.Visit_Span, SUM(DATEDIFF(IFNULL(s.Span_End_Date, NOW()), s.Span_Start_Date)) AS GDays
     FROM stays s join visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
     WHERE v.idRegistration = $idRegistration GROUP BY s.idVisit, s.Visit_Span");

        $stays = array();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stays[$r['idVisit']][$r['Visit_Span']] = $r['GDays'];
        }

        for ($n=0; $n<count($spans); $n++) {

            if (isset($stays[$spans[$n]['idVisit']][$spans[$n]['Span']])) {
                $spans[$n]['Guest_Nights'] = $stays[$spans[$n]['idVisit']][$spans[$n]['Span']];
            }

        }


        $guest = new Guest($dbh, '', $spans[(count($spans) - 1)]['idPrimaryGuest']);
        $name = $guest->getRoleMember();

        $priceModel = PriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
        $stmtMarkup = Receipt::createComprehensiveStatements($dbh, $spans, $idRegistration, $name->get_fullName(), $priceModel, $includeLogo);

    } else {
        $stmtMarkup = 'No Information.';
    }

} else if ($idVisit > 0) {

    $visit = new Visit($dbh, 0, $idVisit);


    // Generate Statement
    $guest = new Guest($dbh, '', $visit->getPrimaryGuestId());
    $name = $guest->getRoleMember();

    $stmtMarkup = Receipt::createStatementMarkup($dbh, $idVisit, $name->get_fullName(), $includeLogo);

} else {
    $stmtMarkup = 'No Information.';
}

$stmtMarkup = HTMLContainer::generateMarkup('div', $stmtMarkup, array('id'=>'divStmt', 'style'=>'clear:left;max-width: 800px;font-size:.9em;', 'class'=>'PrintArea ui-widget ui-widget-content ui-corner-all hhk-panel'));

if (isset($_POST['btnWord'])) {


    $form = "<!DOCTYPE html>"
            . "<html>"
                . "<head>"
                    . "<style type='text/css'>" . file_get_contents('css/redmond/jquery-ui.min.css') . "</style>"
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

$emSubject = $wInit->siteName . " Guest Statement";

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
        $emTbl->generateMarkup(array(), 'Email Guest Statement'), array('id'=>'formEm'))

        .HTMLContainer::generateMarkup('form',
                HTMLInput::generateMarkup('Print', array('id'=>'btnPrint', 'style'=>'margin-right:1em;'))
                .HTMLInput::generateMarkup('Download to MS Word', array('name'=>'btnWord', 'type'=>'submit'))
                .HTMLInput::generateMarkup($idRegistration, array('name'=>'hdnIdReg', 'type'=>'hidden'))
                .HTMLInput::generateMarkup($idVisit, array('name'=>'hdnIdVisit', 'type'=>'hidden'))
                , array('name'=>'frmwrod','action'=>'ShowStatement.php', 'method'=>'post'))
        ,array('style'=>'margin-left:100px;margin-bottom:10px; float:left;', 'class'=>'ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox'));


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


            $mail = prepareEmail();

            $mail->From = $uS->FromAddress;
            $mail->FromName = $uS->siteName;
            $mail->addAddress($emAddr);     // Add a recipient
            $mail->addReplyTo($uS->ReplyToAddr);

            $mail->isHTML(true);

            $mail->Subject = $emSubject;
            $mail->msgHTML($stmtMarkup);


            if($mail->send()) {
                $msg .= "Email sent.  ";
            } else {
                $msg .= "Email failed!  " . $mail->ErrorInfo;
            }

        }

        echo (json_encode(array('msg'=>$msg)));

    } else {
        echo "<script type='text/javascript'>" . createScript() . "</script>" . $emtableMarkup . $stmtMarkup;
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
        popTitle   : 'Guest Statement'};

    $('#btnPrint').click(function() {
        $('div.PrintArea').printArea(opt);
    });
});
</script>
    </head>
    <body>
        <div id="contentDiv">
            <?php echo $msg; ?>
            <?php echo $emtableMarkup; ?>
            <div class="hhk-tdbox hhk-visitdialog">
                <?php echo $stmtMarkup; ?>
            </div>
        </div>
    </body>
</html>
