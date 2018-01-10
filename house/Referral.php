<?php
/**
 * Referral.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");


require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'registrationRS.php');
require (DB_TABLES . 'ActivityRS.php');
require (DB_TABLES . 'visitRS.php');
require (DB_TABLES . 'ReservationRS.php');
require (DB_TABLES . 'MercuryRS.php');
require (DB_TABLES . 'PaymentsRS.php');

require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (MEMBER . 'OrgMember.php');
require (MEMBER . "Addresses.php");
require (MEMBER . "EmergencyContact.php");

require (CLASSES . 'CleanAddress.php');
require (CLASSES . 'AuditLog.php');
require (CLASSES . 'MercPay/Gateway.php');
require (CLASSES . 'MercPay/MercuryHCClient.php');
require (PMT . 'Payments.php');
require (PMT . 'HostedPayments.php');
require (PMT . 'CreditToken.php');
require (CLASSES . 'PaymentSvcs.php');
require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';

require (HOUSE . 'psg.php');
require (HOUSE . 'RoleMember.php');
require (HOUSE . 'Role.php');
require (HOUSE . 'Guest.php');
require (HOUSE . 'Agent.php');
require (HOUSE . 'Patient.php');
require (HOUSE . 'Reservation_1.php');
require (HOUSE . 'RegistrationForm.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'Registration.php');
require (HOUSE . 'Hospital.php');
require (HOUSE . 'VisitLog.php');
require (HOUSE . 'Constraint.php');
require (HOUSE . 'Attributes.php');





try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

$dbh = $wInit->dbh;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();

// Load the session with member - based lookups
$wInit->sessionLoadGenLkUps();
$wInit->sessionLoadGuestLkUps();

// Get labels
$labels = new Config_Lite(LABEL_FILE);

addslashesextended($_POST);

$idGuest = 0;
$idPatient = 0;
$idReserv = 0;
$paymentMarkup = '';
$receiptMarkup = '';

// Hosted payment return
if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $uS->ccgw, $_POST)) === FALSE) {

    $receiptMarkup = $payResult->getReceiptMarkup();
    $paymentMarkup = HTMLContainer::generateMarkup('p', $payResult->getDisplayMessage());
}

if (isset($_POST['hdnCfmRid'])) {

    $idReserv = intval(filter_var($_POST['hdnCfmRid'], FILTER_SANITIZE_NUMBER_INT), 10);
    $resv = Reservation_1::instantiateFromIdReserv($dbh, $idReserv);

    $idGuest = $resv->getIdGuest();

    $guest = new Guest($dbh, '', $idGuest);

    $notes = '';
    if (isset($_POST['tbCfmNotes'])) {
        $notes = filter_var($_POST['tbCfmNotes'], FILTER_SANITIZE_STRING);
    }

    require(HOUSE . 'TemplateForm.php');
    require(HOUSE . 'ConfirmationForm.php');

    $confirmForm = new ConfirmationForm($uS->ConfirmFile);

    $formNotes = $confirmForm->createNotes($notes, FALSE);
    $form = '<!DOCTYPE html>' . $confirmForm->createForm($confirmForm->makeReplacements($resv, $guest, 0, $formNotes));

    header('Content-Disposition: attachment; filename=confirm.doc');
    header("Content-Description: File Transfer");
    header('Content-Type: text/html');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');

    echo($form);
    exit();
}

if (isset($uS->cofrid)) {
    $idReserv = $uS->cofrid;
    unset($uS->cofrid);
}


if (isset($_GET['id'])) {
    $idGuest = intval(filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT), 10);
}

if (isset($_GET['rid'])) {
    $idReserv = intval(filter_var($_GET['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
}

if ($idGuest == 0 && $idReserv > 0) {
    $reserv = Reservation_1::instantiateFromIdReserv($dbh, $idReserv);
    $idGuest = $reserv->getIdGuest();
}



if ($idGuest > 0) {
    // This triggers a getmember.
    $guestid = $idGuest;
    $mk1 = "<h2>Loading...</h2>";
} else {
    // Guest Search markup
    $gMk = Role::createSearchHeaderMkup("gst", "Primary Guest: ");

    $mk1 = HTMLContainer::generateMarkup('h3', $labels->getString('checkin', 'guestInstructions', 'Start with the Primary Guest'));
    $mk1 .= $gMk['hdr'];
    $guestid = "";

}


// Patient
$pMkarray = Role::createSearchHeaderMkup("h_", $labels->getString('MemberType', 'patient', 'Patient') . ' Search: ');
$pmkup = $pMkarray['hdr'];




// Instantiate the alert message control
$alertMsg = new alertMessage("divAlert1");
$alertMsg->set_DisplayAttr("none");
$alertMsg->set_Context(alertMessage::Success);
$alertMsg->set_iconId("alrIcon");
$alertMsg->set_styleId("alrResponse");
$alertMsg->set_txtSpanId("alrMessage");
$alertMsg->set_Text("uh-oh");

$resultMessage = $alertMsg->createMarkup();
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <link rel="stylesheet" href="css/daterangepicker.min.css">
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?> <span id="spnStatus" sytle="margin-left:50px; display:inline;"></span></h1>
            <p id="ajaxError"></p>
            <div id="divAlertMsg"><?php echo $resultMessage; ?></div>
            <div id="paymentMessage" style="clear:left;float:left; margin-top:5px;margin-bottom:5px; display:none;" class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox">
            </div>
            <div style="clear:both"></div>
            <form  action="Referral.php" method="post"  id="form1">
                <div id="datesSection" style="clear:left; float:left; display:none;" class="ui-widget ui-widget-header ui-state-default ui-corner-all hhk-panel">
                </div>
                <div id="patientSection" style="display:none; padding-left:0; margin-top:0;margin-bottom:.3em; clear:left; float:left; min-width: 810px;" class="ui-widget hhk-visitdialog">
                    <?php echo $pmkup; ?>
                </div>
                <div id="guestAccordion" style="padding-left:0; margin-top:0; margin-bottom:1.5em; clear:left; float:left; min-width: 810px;" class="ui-widget hhk-visitdialog">
                </div>
                <div id="guestSearch" style="padding-left:0;padding-top:0; margin-bottom:1.5em; clear:left; float:left;">
                    <?php echo $mk1; ?>
                </div>
                <div id="hospitalSection" style="font-size: .9em; padding-left:0;margin-top:0; margin-bottom:.5em; clear:left; float:left; display:none; min-width: 810px;"  class="ui-widget hhk-visitdialog"></div>

                <div id="rescList" style="clear:left; float:left; font-size: .9em; display:none;" class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog">
                </div>
                <div id="rate" style="float:left; font-size: .9em; display:none;" class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog">
                </div>
                <div id="resvGuest" style="float:left; font-size:.9em; display:none; clear:left;" class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog">
                </div>
                <div id="resvStatus" style="float:left; font-size:.9em; display:none;" class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog">
                </div>
                <div id="notesGuest" style="float:left; font-size:.9em; display:none; width: 600px;" class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog">
                </div>
                <div id="pay" style="float:left; font-size: .9em; display:none; clear:left;" class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog">
                </div>
                <div id="vehicle" style="float:left; font-size: .9em; display:none;" class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox">
                </div>
                <div style="clear:both; padding:30px;"></div>
                <div id="submitButtons" class="ui-corner-all" style="font-size:.9em;">
                    <input type="button" id="btnDelete" value="Delete" style="display:none;"/>
                    <input type="button" id="btnCkinForm" value='Show Registration Form' style="display:none;"/>
                    <input id='btnDone' type='button' value='Find a Room' style="display:none;"/>
                </div>
            </form>
            <div id="patientPrompt" class="hhk-tdbox-noborder" style="display:none;">
                <p id="hhk-patPromptQuery">Will this patient be staying at the House for at least one night?</p>
            </div>
            <div id="faDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.9em;"></div>
            <div id="resDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.9em;"></div>
            <div id="activityDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.9em;"></div>
            <div id="confirmDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;">
                <form id="frmConfirm" action="Referral.php" method="post"></form>
            </div>
            <div id="psgDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;"></div>
            <div id="pmtRcpt" style="font-size: .9em; display:none;"></div>
        </div>  <!-- div id="contentDiv"-->
        <form name="xform" id="xform" method="post"><input type="hidden" name="CardID" id="CardID" value=""/></form>
        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VERIFY_ADDRS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo DR_PICKER_JS; ?>"></script>
        <script type="text/javascript" src="js/referral-min.js"></script>
        <script type="text/javascript">
    var pmtMkup = "<?php echo $paymentMarkup; ?>";
    var rctMkup = '<?php echo $receiptMarkup; ?>';
    var isCheckedOut = false;
    var resvTitle = '<?php echo $labels->getString('guestEdit', 'reservationTitle', 'Reservation'); ?>';
    var fixedRate = '<?php echo RoomRateCategorys::Fixed_Rate_Category; ?>';
    var reserv = new Reserv();
    reserv.patientLabel = '<?php echo $labels->getString('MemberType', 'patient', 'Patient'); ?>';
    reserv.idReserv = '<?php echo $idReserv; ?>';
    reserv.gpnl = '<?php echo $guestid; ?>';
    reserv.patAsGuest = '<?php echo $uS->PatientAsGuest; ?>';
    reserv.patientBirthDate = '<?php echo $uS->InsistPatBD; ?>';
    reserv.showBirthDate = '<?php echo $uS->ShowBirthDate; ?>';
        </script>
    </body>
</html>
