<?php
/**
 * Reserve.php
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
$paymentMarkup = '';
$receiptMarkup = '';

// Hosted payment return
if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $uS->ccgw, $_POST)) === FALSE) {

    $receiptMarkup = $payResult->getReceiptMarkup();
    $paymentMarkup = HTMLContainer::generateMarkup('p', $payResult->getDisplayMessage());
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $wInit->pageTitle; ?></title>
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        <link rel="stylesheet" href="css/daterangepicker.min.css">
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <script type="text/javascript" src="../js/jquery.daterangepicker.min.js"></script>
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

        </div>
    </body>
</html>
