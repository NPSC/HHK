<?php
/**
 * ShowStatement.php
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

require ("homeIncludes.php");

require(DB_TABLES . "visitRS.php");
require(DB_TABLES . "registrationRS.php");
require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'PaymentsRS.php');
require (DB_TABLES . 'MercuryRS.php');

require CLASSES . 'FinAssistance.php';
require CLASSES . 'Purchase/Item.php';
require(CLASSES . 'Purchase/RoomRate.php');

require PMT . 'Receipt.php';
require PMT . 'Invoice.php';

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


function createScript() {
    return "
    $('#btnPrint, #btnEmail').button();
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
    $('#btnPrint').click(function() {
        $('div.PrintArea').printArea();
    });";
}

if (isset($_REQUEST["vid"])) {
    $idVisit = intval(filter_var($_REQUEST["vid"], FILTER_SANITIZE_NUMBER_INT), 10);
}

if (isset($_REQUEST['reg'])) {
    $idRegistration = intval(filter_var($_REQUEST['reg'], FILTER_SANITIZE_NUMBER_INT), 10);
}

    $logoUrl = $uS->resourceURL . 'images/registrationLogo.png';

    if ($idRegistration > 0) {

        $stmt1 = $dbh->prepare("select * from `vvisit_stmt` where `idRegistration` = :idreg order by `idVisit`, `Span`");
        $stmt1->execute(array(':idreg'=>$idRegistration));
        $spans = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        $guest = new Guest($dbh, '', $spans[(count($spans) - 1)]['idPrimaryGuest']);
        $name = $guest->getNameObj();


        $stmtMarkup = Receipt::createComprehensiveStatements($dbh, $spans, $idRegistration, $name->get_fullName(), $logoUrl);


    } else if ($idVisit > 0) {

        $visit = new Visit($dbh, 0, $idVisit);


        // Generate Statement
        $guest = new Guest($dbh, '', $visit->getPrimaryGuestId());
        $name = $guest->getNameObj();

        $stmtMarkup = Receipt::createStatementMarkup($dbh, $idVisit, $logoUrl, $name->get_fullName());

    } else {
        $stmtMarkup = 'No Information.';
    }

$stmtMarkup = HTMLContainer::generateMarkup('div', $stmtMarkup, array('style'=>'clear:left;max-width: 800px;font-size:.9em;', 'class'=>'PrintArea ui-widget ui-widget-content ui-corner-all hhk-panel'));


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
        .HTMLInput::generateMarkup('Print', array('id'=>'btnPrint', 'style'=>'margin-right:.3em;'))
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

            $config = new Config_Lite(ciCFG_FILE);

            $mail = prepareEmail($config);

            $mail->From = $config->getString('guest_email', 'FromAddress', '');
            $mail->FromName = $uS->siteName;
            $mail->addAddress($emAddr);     // Add a recipient
            $mail->addReplyTo($config->getString('guest_email', 'ReplyTo', ''));

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
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        <style type="text/css" media="print">
            .PrintArea {margin:0; padding:0; font: 12px Arial, Helvetica,"Lucida Grande", serif; color: #000;}
            @page { margin: 1cm; }
        </style>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PRINT_AREA_JS; ?>"></script>
        <script type='text/javascript'>
$(document).ready(function() {
    "use strict";
    <?php echo createScript(); ?>
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
