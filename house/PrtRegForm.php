<?php
/**
 * PrtRegForm.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

require(DB_TABLES . "visitRS.php");
require(DB_TABLES . "ReservationRS.php");
require(DB_TABLES . "registrationRS.php");
require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'PaymentsRS.php');
require (DB_TABLES . 'MercuryRS.php');
require (DB_TABLES . 'AttributeRS.php');

require CLASSES . 'FinAssistance.php';
require (PMT . 'Payments.php');
require (PMT . 'CreditToken.php');
require (PMT . 'Receipt.php');

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
require (HOUSE . 'Attributes.php');
require (HOUSE . 'Constraint.php');

require (HOUSE . 'Vehicle.php');

$wInit = new webInit(WebPageCode::Page);
$pageTitle = $wInit->pageTitle;

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$uS = Session::getInstance();

$queryForm = '';
$regForm = '';
$sty = '';
$checkinDate = '';

if (isset($_GET['d'])) {
    $checkinDate = filter_var($_GET['d'], FILTER_SANITIZE_STRING);
}

if (isset($_POST['regckindate'])) {
    $checkinDate = filter_var($_POST['regckindate'], FILTER_SANITIZE_STRING);
}

if ($checkinDate == '') {

    $queryForm = HTMLContainer::generateMarkup('div', 'Check-in Date: ' . HTMLInput::generateMarkup('', array('name' => 'regckindate', 'class' => 'ckdate hhk-prtRegForm'))
                    . HTMLInput::generateMarkup('Print Registration Forms', array('id' => 'btnPrintRegForm', 'type' => 'submit', 'data-page' => 'PrtRegForm.php', 'class' => 'hhk-prtRegForm', 'style' => 'margin-left:.3em;'))
                    , array('style' => 'margin-left:5em;padding:15px;margin:10px;border:solid 1px #62A0CE;background-color:#E8E5E5;float:left;'));

} else {

    $ckinDT = new DateTime($checkinDate);

    // get reservations on the date indicated
    $stmt = $dbh->query("select idReservation from reservation where Status = '" . ReservationStatus::Committed . "' and DATE(Expected_Arrival) = '" . $ckinDT->format('Y-m-d') . "'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) == 0) {
        $regForm = '<h2 style="margin-top:20px;">No registrations found for ' . $ckinDT->format('M j, Y') . '</h2>';
    }

    foreach ($rows as $r) {

        $reservArray = ReservationSvcs::generateCkinDoc($dbh, $r['idReservation'], 0, $wInit->resourceURL . 'images/registrationLogo.png', $uS->mode);
        $sty = $reservArray['style'];

        $regForm .= $reservArray['doc'] . HTMLContainer::generateMarkup('div', '', array('style'=>'page-break-before: right;'));

    }
}
?>
<!DOCTYPE html>
<html lang="en" moznomarginboxes>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <link href="css/house.css" rel="stylesheet" type="text/css" />
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        <?php echo $sty; ?>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript">
            $(document).ready(function () {
            "use strict";
                    $('#btnPrintRegForm').button();
                    $('.ckdate').datepicker();
             });
        </script>
    </head>
    <body>
        <form action="#" method="post" name="form1">
            <?php echo $queryForm; ?>
        </form>
<?php echo $regForm; ?>
    </body>
</html>
