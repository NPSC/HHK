<?php
/**
 * CheckIn.php
 *
* @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (MEMBER . 'OrgMember.php');
require (MEMBER . "Addresses.php");
require (MEMBER . "EmergencyContact.php");

require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'registrationRS.php');
require (DB_TABLES . 'ActivityRS.php');
require (DB_TABLES . 'visitRS.php');
require (DB_TABLES . 'ReservationRS.php');
require (DB_TABLES . 'AttributeRS.php');

require (HOUSE . 'psg.php');
require (HOUSE . 'RoleMember.php');
require (HOUSE . 'Role.php');
require (HOUSE . 'Guest.php');
require (HOUSE . 'Patient.php');
require (HOUSE . 'Reservation_1.php');
require (HOUSE . 'Registration.php');
require (HOUSE . 'visitViewer.php');
require (HOUSE . 'Vehicle.php');

require (HOUSE . 'Room.php');
require (HOUSE . 'Attributes.php');
require (HOUSE . 'Constraint.php');


try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();

// Load the session with member - based lookups
$wInit->sessionLoadGenLkUps();
$wInit->sessionLoadGuestLkUps();

// Get labels
$labels = new Config_Lite(LABEL_FILE);


$idWaitlist = 0;
$idGuest = 0;
$idPatient = 0;
$pmkup = '';
$pmDisplay = '';
$pInfo = '';
$gInfo = '';
$stayingMarkup = '';
$committedMarkup = '';
$immediateMarkup = '';
$wListMarkup = '';
$patientId = '';
$guestid = '';
$psgId = 0;
$idReserv = 0;
$addGuestId = 0;

$reservListDisplay = 'display:none;';
$textInfo = array();
$postBackPage = 'CheckedIn.php';


// Waitlist Id.
if (isset($_GET['idWL'])) {

    $idWaitlist = intval(filter_Var($_GET['idWL'], FILTER_SANITIZE_NUMBER_INT), 10);

    if ($idWaitlist > 0) {

        $wlRS = new WaitlistRS();
        $wlRS->idWaitlist->setStoredVal($idWaitlist);
        $rows = EditRS::select($dbh, $wlRS, array($wlRS->idWaitlist));

        if (count($rows) == 1) {
            // Got a waitlist
            EditRS::loadRow($rows[0], $wlRS);
            $idGuest = $wlRS->idGuest->getStoredVal();
            $idPatient = $wlRS->idPatient->getStoredVal();

            $textInfo['plast'] = $wlRS->Patient_Last->getStoredVal();
            $textInfo['pfirst'] = $wlRS->Patient_First->getStoredVal();
            $textInfo['glast'] = $wlRS->Guest_Last->getStoredVal();
            $textInfo['gfirst'] = $wlRS->Guest_First->getStoredVal();
            $textInfo['hospital'] = $wlRS->Hospital->getStoredVal();
            $textInfo['gphone'] = $wlRS->Phone->getStoredVal();
            $textInfo['gemail'] = $wlRS->Email->getStoredVal();
        }
    }
}

if (isset($_GET['idGuest'])) {
    $idGuest = intval(filter_var($_GET['idGuest'], FILTER_SANITIZE_NUMBER_INT), 10);
}

if (isset($_GET['rid'])) {
    $idReserv = intval(filter_var($_GET['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
}

if (isset($_GET['gid'])) {
    $addGuestId = intval(filter_var($_GET['gid'], FILTER_SANITIZE_NUMBER_INT), 10);
}


// Set up patient search
if ($uS->OpenCheckin && $idReserv == 0) {

    $infoSpan = HTMLContainer::generateMarkup('span', '', array('class' => 'ui-icon ui-icon-info', 'style' => 'float: left; margin-right: .3em;'));
    //$pInfo = HTMLContainer::generateMarkup('div', "If the Guest is new to the house (not in our system), start here with the Patient's last name." . $infoSpan, array('class' => 'hhk-info ui-widget', 'style' => 'clear:left;padding-top:30px;'));
    $gInfo = HTMLContainer::generateMarkup('div', $labels->getString('checkin', 'guestInstructions', 'Start with the Primary Guest') . $infoSpan, array('class' => 'hhk-info ui-widget', 'style' => 'clear:left;padding-top:30px;'));

    // Patient markup
    if ($idPatient == 0) {

        $pMkarray = Role::createSearchHeaderMkup("h_", $labels->getString('MemberType', 'patient', 'Patient') . ' Search: ', FALSE);
        $pmkup = $pMkarray['hdr'];
        $pmDisplay = 'display:none;';

    } else {
        // This triggers a getmember.
        $patientId = $idPatient;
    }
}

// Guest Search markup
$gMk = Role::createSearchHeaderMkup("gst", "Guest Search: ", TRUE);
$mk1 = $gMk['hdr'];

// Hide guest search?
if ($uS->OpenCheckin && $idReserv == 0) {
    $gSearchDisplay = '';
} else {
    $gSearchDisplay = 'display:none;';
}



if ($idGuest > 0) {

    // This triggers a getmember.
    $guestid = $idGuest;

} else if ($idReserv == 0 && $idGuest == 0) {

    // Set up for a new checkin page
    if ($uS->OpenCheckin) {

        // delete old unfinished reservations
        $dbh->exec("call delImediateResv()");

        // list any Unfinished Reservations
        $immediateMarkup = Reservation_1::showListByStatus($dbh, 'GuestEdit.php', 'CheckIn.php', ReservationStatus::Imediate, FALSE, NULL, 0, TRUE);
        if ($immediateMarkup != '') {
            $immediateMarkup = HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('h3', 'Unfinished Check-Ins ' . HTMLInput::generateMarkup('Delete Unfinished Check-Ins', array('type' => 'button', 'id' => 'btnDelUnfinished', 'style'=>'margin-left:1em;font-size:.9em;')), array('style'=>'padding:5px;background-color: #D3D3D3;')) . $immediateMarkup, array('id' => 'divUnfinished'));
        }
    }

    if ($uS->Reservation) {
        // Load reservations
        $inside = Reservation_1::showListByStatus($dbh, 'Referral.php', 'CheckIn.php', ReservationStatus::Committed, TRUE, NULL, 2, TRUE);
        if ($inside == '') {
            $inside = "<p style='margin-left:60px;'>-None are imminent-</p>";
        }

        $committedMarkup = HTMLContainer::generateMarkup('h3', $labels->getString('register', 'reservationTab', 'Confirmed Reservations') . HTMLContainer::generateMarkup('span', '', array('style'=>'float:right;', 'class'=>'ui-icon ui-icon-triangle-1-e')), array('id'=>'hhk-confResvHdr', 'style'=>'margin-bottom:15px;padding:5px;background-color: #D3D3D3;', 'title'=>'Click to show or hide the ' . $labels->getString('register', 'reservationTab', 'Confirmed Reservations')))
            . HTMLContainer::generateMarkup('div', $inside, array('id'=>'hhk-confResv', 'style'=>'margin-bottom:5px;'));
    }

    if ($uS->Reservation && $uS->OpenCheckin) {

        $inside = Reservation_1::showListByStatus($dbh, 'Referral.php', 'CheckIn.php', ReservationStatus::Waitlist, TRUE, NULL, 2, TRUE);

        if ($inside != '') {
            $wListMarkup = HTMLContainer::generateMarkup('h3', 'Wait List' . HTMLContainer::generateMarkup('span', '', array('style'=>'float:right;', 'class'=>'ui-icon ui-icon-triangle-1-e')), array('id'=>'hhk-wListResvHdr'
                , 'style'=>'margin-bottom:15px;padding:5px;background-color: #D3D3D3;', 'title'=>'Click to show or hide the wait list'))
                    . HTMLContainer::generateMarkup('div', $inside, array('id'=>'hhk-wListResv', 'style'=>'margin-bottom:5px;'));

        }
    }

    $stayingMarkup = HTMLContainer::generateMarkup('div', Reservation_1::showListByStatus($dbh, 'GuestEdit.php', 'CheckIn.php', ReservationStatus::Staying), array('id'=>'hhk-chkedIn'));
    if ($stayingMarkup == '') {
        $stayingMarkup = "<p style='margin-left:60px;'>-None-</p>";
    }
    $reservListDisplay = '';
}

unset($uS->cofId);
unset($uS->regId);

$jsonTextInfo = json_encode($textInfo);

// Instantiate the alert message control
$alertMsg = new alertMessage("divAlert1");
$alertMsg->set_DisplayAttr("none");
$alertMsg->set_Context(alertMessage::Success);
$alertMsg->set_iconId("alrIcon");
$alertMsg->set_styleId("alrResponse");
$alertMsg->set_txtSpanId("alrMessage");
$alertMsg->set_Text("uh-oh");

$resultMessage = $alertMsg->createMarkup();
$patAsGuest = $uS->PatientAsGuest;
$verifyHospDate = $uS->VerifyHospDate;

$confReserv = $labels->getString('register', 'reservationTab', 'Confirmed Reservations');
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_DT_CSS; ?>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>

    <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS; ?>"></script>
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PAG_JS; ?>"></script>
        <style>
            .ui-menu-item {width:300px;font-size:.8em;}
        </style>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?><span id="spnStatus" sytle="margin-left:50px; display:inline;"></span></h1>
            <div id="divTextInfo" class="ui-widget ui-widget-content ui-corner-all hhk-panel" style="display:none;">
                <span id="spnNewPatient"></span><span id="spnNewGuest" style="margin-left:1em;"></span>
            </div>
            <div style="clear:both"></div>
            <p id="ajaxError"></p>
            <div id="divAlertMsg"><?php echo $resultMessage; ?></div>
            <div id="divresvWrapper" style="display: none;">
            <div id="divResvList" style="font-size:.7em; <?php echo $reservListDisplay; ?>" class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog">
                <?php echo $immediateMarkup; ?>
                <?php echo $committedMarkup; ?>
                <?php echo $wListMarkup; ?>
                <h3 id="hhk-chkedInHdr" style='padding:5px;background-color: #D3D3D3;' title="Click to show or hide the Checked-In Guests">Checked-In Guests
                    <span class="ui-icon ui-icon-triangle-1-e" style="float:right;"></span></h3>
                <?php echo $stayingMarkup; ?>
            </div>
            </div>
            <?php echo $pInfo; ?>
            <form  action="CheckIn.php" method="post"  id="form1">
                <div id="hospitalSection" style="font-size:.9em; float:left; display:none; min-width: 500px;"  class="ui-widget hhk-panel hhk-visitdialog"></div>
                <div id="patientSearch" style="clear:left;float:left;font-size: .9em;padding-left:0;<?php echo $pmDisplay; ?>" class="hhk-visitdialog">
                <?php echo $pmkup; ?>
                </div>
                <div id="patientSection" style="clear:left;float:left;font-size: .9em;padding-left:0;display:none;min-width: 500px;" class="hhk-visitdialog"></div>
                <div id="stays" style="clear:left;float:left; font-size: .9em; display:none; " class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog"></div>
                <div id="addRoom" style="clear:left;float:left; font-size: .9em; display:none; " class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog"></div>
                <div id="guestAccordion" style="clear:left;font-size:.9em; margin-top:0;float:left;" class="ui-widget hhk-panel hhk-visitdialog"></div>
                <div id="guestSearchWrapper" style="display: none;">
                    <?php echo $gInfo; ?>
                    <div id="guestSearch" style="clear:left;float:left;font-size: .9em;padding-left:0;<?php echo $gSearchDisplay; ?>" class="hhk-panel">
                        <?php echo $mk1; ?>
                    </div>
                </div>
                <div id="vehicle" style="clear:left;float:left; font-size: .9em; display:none;" class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox">
                </div>
                <div id="rescList" style="clear:left; float:left; font-size: .9em; display:none;" class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog">
                </div>
                <div id="rate" style="clear:left; float:left; font-size: .9em; display:none; " class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog">
                </div>
                <div id="pay" style="clear:left; float:left; font-size: .9em; display:none; " class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog">
                </div>
                <div style="clear:both; min-height: 400px;"></div>
                <input type="hidden" id="txtWlId" value="<?php echo $idWaitlist; ?>" />
                <div id="submitButtons" class="ui-corner-all">
                    <input id='btnDone' type='button' value='Done Adding Guests' style="display:none;"/>
                    <input type="button" value="Check In" id="btnChkin" style="display:none;" />
                </div>
            </form>
        </div>  <!-- div id="contentDiv"-->
        <div id="ecSearch"  style="display:none;">
            <table>
                <tr>
                    <td>Search: </td><td><input type="text" id="txtRelSch" size="15" value="" title="Type at least 3 letters to invoke the search."/></td>
                </tr>
                <tr><td><input type="hidden" value="" id="hdnEcSchPrefix"/></td></tr>
            </table>
        </div>
        <div id="patientPrompt" class="hhk-tdbox-noborder" style="display:none;">
            <p>Will this patient be staying at the House for at least one night?</p>
        </div>
        <div id="psgDialog" class="hhk-tdbox hhk-visitdialog" style="display:none"></div>
        <div id="faDialog" class="hhk-tdbox hhk-visitdialog" style="display:none"></div>
        <div id="pmtRcpt" style="font-size: .9em; display:none;"></div>
        <div id="resDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.9em;"></div>
        <form name="xform" id="xform" method="post"></form>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo VERIFY_ADDRS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VISIT_DIALOG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="js/rcheckin-min.js<?php echo JS_V; ?>"></script>
        <script tyhpe="text/javascript">
    var chkIn;
    var postBkPg = '<?php echo $postBackPage; ?>';
    var isCheckedOut = false;
    chkIn = new CheckIn();
    chkIn.members = [];
    chkIn.currentGuests = 0;
    chkIn.guestPrefix = 1;
    chkIn.patientLabel = '<?php echo $labels->getString('MemberType', 'patient', 'Patient'); ?>';
    chkIn.gpnl = '<?php echo $guestid; ?>';
    chkIn.ppnl = '<?php echo $patientId; ?>';
    chkIn.idPsg = '<?php echo $psgId; ?>';
    chkIn.idReserv = '<?php echo $idReserv; ?>';
    chkIn.addGuestId = '<?php echo $addGuestId; ?>';
    chkIn.patAsGuest = '<?php echo $patAsGuest; ?>';
    chkIn.verifyHospDate = '<?php echo $verifyHospDate; ?>';
    chkIn.fillEmergCont = '<?php echo isset($uS->EmergContactFill) ? $uS->EmergContactFill : 'true'; ?>';
    chkIn.forceNamePrefix = '<?php echo isset($uS->ForceNamePrefix) ? $uS->ForceNamePrefix : 'false'; ?>';
    chkIn.patientBirthDate = '<?php echo $uS->PatientBirthDate; ?>';
    chkIn.resv = new reservation;
        </script>
    </body>
</html>
