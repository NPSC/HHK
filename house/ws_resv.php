<?php
/**
 * ws_resv.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
/**
 *  includes and requries
 */
require ("homeIncludes.php");

require(DB_TABLES . "visitRS.php");
require(DB_TABLES . "registrationRS.php");
require(DB_TABLES . "ReservationRS.php");

require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'ItemRS.php');
require (DB_TABLES . 'ActivityRS.php');
require (DB_TABLES . 'MercuryRS.php');
require (DB_TABLES . 'PaymentsRS.php');
require (DB_TABLES . 'AttributeRS.php');

require CLASSES . 'CleanAddress.php';
require CLASSES . 'AuditLog.php';
require CLASSES . 'History.php';
require (CLASSES . 'CreateMarkupFromDB.php');

require (CLASSES . 'Notes.php');
require (CLASSES . 'US_Holidays.php');
require (CLASSES . 'PaymentSvcs.php');
require (CLASSES . 'FinAssistance.php');


require (CLASSES . 'MercPay/MercuryHCClient.php');
require (CLASSES . 'MercPay/Gateway.php');
require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';


require (PMT . 'Payments.php');
require (PMT . 'TokenTX.php');
require (PMT . 'HostedPayments.php');
require (PMT . 'Invoice.php');
require (PMT . 'InvoiceLine.php');
require (PMT . 'Receipt.php');
require (PMT . 'CreditToken.php');
require (PMT . 'Transaction.php');
require (PMT . 'CashTX.php');
require (PMT . 'CheckTX.php');

require (CLASSES . 'Purchase/Item.php');

require(CLASSES . 'Purchase/RoomRate.php');

require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (MEMBER . 'OrgMember.php');
require (MEMBER . "Addresses.php");
require (MEMBER . "EmergencyContact.php");

require (HOUSE . 'RoleMember.php');
require (HOUSE . 'Role.php');
require (HOUSE . 'ActivityReport.php');
require (HOUSE . 'Agent.php');
require (HOUSE . 'Attributes.php');
require (HOUSE . 'Constraint.php');
require (HOUSE . 'Doctor.php');
require (HOUSE . 'Guest.php');
require (HOUSE . 'Hospital.php');

require (HOUSE . 'HouseServices.php');
require (HOUSE . 'Patient.php');
require (HOUSE . 'PaymentManager.php');
require (HOUSE . 'PaymentChooser.php');
require (HOUSE . "psg.php");
require (HOUSE . 'RateChooser.php');
require (HOUSE . 'Registration.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'RoomChooser.php');
require (HOUSE . 'Reservation_1.php');
require (HOUSE . 'Reservation.php');
require (HOUSE . 'ReservationSvcs.php');
require (HOUSE . 'RegisterForm.php');
require (HOUSE . 'ReserveData.php');
require (HOUSE . 'RegistrationForm.php');
require (HOUSE . 'VisitLog.php');
require (HOUSE . 'RoomLog.php');
require (HOUSE . 'Vehicle.php');
require (HOUSE . 'Visit.php');
require (HOUSE . "visitViewer.php");
require (HOUSE . 'Waitlist.php');
require (HOUSE . 'WaitlistSvcs.php');
require (HOUSE . 'Register.php');
require (HOUSE . 'VisitCharges.php');


$wInit = new webInit(WebPageCode::Service);

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$uS = Session::getInstance();


$guestAdmin = ComponentAuthClass::is_Authorized("guestadmin");

$c = "";

// Get our command
if (isset($_REQUEST["cmd"])) {
    $c = filter_var($_REQUEST["cmd"], FILTER_SANITIZE_STRING);
}


$events = array();


try {

    switch ($c) {

    case "getresv":

        $rData = new ReserveData($_POST);

        $resv = Reservation::reservationFactoy($dbh, $rData);

        $events = $resv->createMarkup($dbh);

        break;

    default:
        $events = array("error" => "Bad Command: \"" . $c . "\"");
}

} catch (PDOException $ex) {
    $events = array("error" => "Database Error: " . $ex->getMessage() . "<br/>" . $ex->getTraceAsString());
} catch (Hk_Exception $ex) {
    $events = array("error" => "HouseKeeper Server Error: " . $ex->getMessage() . "<br/>" . $ex->getTraceAsString());
} catch (Exception $ex) {
    $events = array("error" => "Web Server Error: " . $ex->getMessage());
}



if (is_array($events)) {

    $json = json_encode($events);

    if ($json !== FALSE) {
        echo ($json);
    } else {
        $events = array("error" => "PHP json encoding error: " . json_last_error_msg());
        echo json_encode($events);
    }

} else {
    echo $events;
}

exit();

