<?php
/**
 * ShowRegForm.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");


require(DB_TABLES . "visitRS.php");
require(DB_TABLES . "ReservationRS.php");
require(DB_TABLES . "registrationRS.php");

require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'PaymentsRS.php');
require (DB_TABLES . 'PaymentGwRS.php');
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

require (CLASSES . 'Parsedown.php');

function getVisitFromGuest(\PDO $dbh, $guestId) {

    $stmt = $dbh->prepare("Select idVisit from stays where `Status` = :stat and idName = :id");
    $stmt->execute(array(':id' => $guestId, ':stat' => VisitStatus::Active));

    $idVisit = 0;
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);

    if (count($rows) > 0) {
        $idVisit = $rows[0][0];
    }

    return $idVisit;
}

$wInit = new webInit(WebPageCode::Page);
$pageTitle = $wInit->pageTitle;

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$uS = Session::getInstance();

$idVisit = 0;
$idGuest = 0;
$idResv = 0;

if (isset($_GET['vid'])) {
    $idVisit = intval(filter_var($_REQUEST['vid'], FILTER_SANITIZE_STRING), 10);
}

if (isset($_GET['gid'])) {
    $idGuest = intval(filter_var($_REQUEST['gid'], FILTER_SANITIZE_STRING), 10);
}

if (isset($_GET['rid'])) {
    $idResv = intval(filter_var($_REQUEST['rid'], FILTER_SANITIZE_STRING), 10);
}

if ($idVisit == 0 && $idResv > 0) {
    $stmt = $dbh->query("Select idVisit from visit where idReservation = $idResv");
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);

    if (count($rows) > 0) {
        $idVisit = $rows[0][0];
    }
}

// Generate Registration
$reservArray = ReservationSvcs::generateCkinDoc($dbh, $idResv, $idVisit, '../conf/registrationLogo.png');

$sty = $reservArray['style'];
$regForm = $reservArray['doc'];
unset($reservArray);

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
            #PrintArea {margin:0; padding:0; font: 12px Arial, Helvetica,"Lucida Grande", serif; color: #000;}
            @page { margin: .5cm; }
        </style>
        <?php echo $sty; ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type='text/javascript'>
$(document).ready(function() {
    "use strict";
    var opt = {mode: 'popup',
        popClose: true,
        popHt      : $('div#PrintArea').height(),
        popWd      : 950,
        popX       : 20,
        popY       : 20,
        popTitle   : 'Guest Registration Form'};

    $('#btnPrint').button();

    $('#btnPrint').click(function() {
        $('div#PrintArea').printArea(opt);
    });

    $('#mainTabs').tabs().show();

});
</script>
    </head>
    <body>
<!--        <h2><?php echo $wInit->pageHeading; ?></h2>-->
        <div id="mainTabs" style="max-width:900px; display:none; font-size:.9em;">
            <ul>
                <li id="liReg"><a href="#vreg">Registration Form</a></li>
<!--                <li><a href="#vperm">Permissions</a></li>-->
            </ul>
            <div id="vreg" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none; ">
                <div style="margin:10px;">
                    <input type="button" id="btnPrint" value="Print"/>
                </div>
                <div id="PrintArea">
                    <?php echo $regForm; ?>
                </div>
            </div>
            <div id="vperm" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none; ">
                <h2>No permission forms were found.</h2>
            </div>
        </div>
    </body>
</html>
