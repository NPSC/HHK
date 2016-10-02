<?php
/**
 * RoomStatus.php
 *
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


require (DB_TABLES . 'nameRS.php');

require (DB_TABLES . 'ReservationRS.php');
require (DB_TABLES . 'AttributeRS.php');

require (CLASSES . 'Notes.php');
require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (HOUSE . 'RoleMember.php');
require (HOUSE . 'Role.php');
require (HOUSE . 'HouseLog.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'ResourceView.php');
require (HOUSE . 'Reservation_1.php');
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

$guestAdmin = ComponentAuthClass::is_Authorized("guestadmin");

$resultMessage = "";
$currentTab = 2;

if (isset($_POST['btnSubmitTable']) or isset($_POST['btnSubmitClean'])) {

    $rooms = array();
    $prefix = '';

    if (isset($_POST['btnSubmitClean'])) {
        $prefix = RoomState::Dirty;
        $currentTab = 0;
    }

    // Set clean
    $clnRooms = array();
    if (isset($_POST[$prefix.'cbClean'])) {
        $clnRooms = $_POST[$prefix.'cbClean'];

    }

    foreach ($clnRooms as $key => $p) {

        $idRoom = intval(filter_var($key, FILTER_SANITIZE_NUMBER_INT), 10);
        if ($idRoom == 0) {
            continue;
        }

        if (isset($rooms[$idRoom])) {
            $room = $rooms[$idRoom];
        } else {
            $room = new Room($dbh, $idRoom);
            $rooms[$idRoom] = $room;
        }

        $room->putClean();
    }


    // Set dirty
    $dtyRooms = array();
    if (isset($_POST[$prefix.'cbDirty'])) {
        $dtyRooms = $_POST[$prefix.'cbDirty'];
    }

    foreach ($dtyRooms as $key => $p) {

        $idRoom = intval(filter_var($key, FILTER_SANITIZE_NUMBER_INT), 10);
        if ($idRoom == 0) {
            continue;
        }

        if (isset($rooms[$idRoom])) {
            $room = $rooms[$idRoom];
        } else {
            $room = new Room($dbh, $idRoom);
            $rooms[$idRoom] = $room;
        }

        $room->putDirty();
    }


    // Delete notes
    if ($guestAdmin) {

        $delRooms = array();
        if (isset($_POST[$prefix.'cbDeln'])) {
            $delRooms = $_POST[$prefix.'cbDeln'];
        }

        foreach ($delRooms as $key => $p) {

            $idRoom = intval(filter_var($key, FILTER_SANITIZE_NUMBER_INT), 10);
            if ($idRoom == 0) {
                continue;
            }

            if (isset($rooms[$idRoom])) {
                $room = $rooms[$idRoom];
            } else {
                $room = new Room($dbh, $idRoom);
                $rooms[$idRoom] = $room;
            }

            $room->setNotes('');
            $room->saveRoom($dbh, $uS->username);
        }
    }

    $notesRooms = array();
    if (isset($_POST[$prefix.'taNotes'])) {
        $notesRooms = $_POST[$prefix.'taNotes'];
    }

    foreach ($notesRooms as $key => $p) {

        $idRoom = intval(filter_var($key, FILTER_SANITIZE_NUMBER_INT), 10);
        if ($idRoom == 0) {
            continue;
        }

        if (isset($rooms[$idRoom])) {
            $room = $rooms[$idRoom];
        } else {
            $room = new Room($dbh, $idRoom);
            $rooms[$idRoom] = $room;
        }

        $notes = filter_var($p, FILTER_SANITIZE_STRING);
        if ($notes != '') {
            $oldNotes = is_null($room->getNotes()) ? '' : $room->getNotes();
            $room->setNotes($oldNotes . "\r\n" . date('m-d-Y') . ', ' . $uS->username . ' - ' . $notes);
        }
    }


    foreach ($rooms as $r) {
        $r->saveRoom($dbh, $uS->username);
    }
}


$stmt = $dbh->query("select DISTINCT
    r.idRoom,
ifnull(v.idVisit, 0) as idVisit,
    r.Title,
    r.`Status`,
    r.`State`,
    r.`Availability`,
    r.`Cleaning_Cycle_Code`,
    ifnull(n.Name_Full, '') as `Name`,
    ifnull(v.Arrival_Date, '') as `Arrival`,
    ifnull(v.Expected_Departure, '') as `Expected_Departure`,
    r.Last_Cleaned,
    r.Notes
from
    room r
        left join
    resource_room rr ON r.idRoom = rr.idRoom
        left join
    visit v ON rr.idResource = v.idResource and v.`Status` = '" . VisitStatus::CheckedIn . "'
        left join
    name n ON v.idPrimaryGuest = n.idName
        left join resource re on rr.idResource = re.idResource
where re.`Type` != '". ResourceTypes::Partition ."' and re.`Type` != '" .ResourceTypes::Block. "';");


$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$roomTable = ResourceView::roomsClean($dbh, $rows, 'tblFac', $uS->guestLookups[GL_TableNames::RoomStatus], '', $guestAdmin);

$checkingIn = Reservation_1::showListByStatus($dbh, '', '', ReservationStatus::Committed, TRUE, NULL, 1, TRUE);

if ($checkingIn == '') {
    $checkingIn = "<p style='margin-left:60px;'>-None-</p>";
}

reset($rows);
$cleanToday = ResourceView::roomsClean($dbh, $rows, 'tblcln', $uS->guestLookups[GL_TableNames::RoomStatus], RoomState::Dirty, $guestAdmin);

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo TOP_NAV_CSS; ?>
        <link href="css/house.css" rel="stylesheet" type="text/css" />
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        <?php echo JQ_DT_CSS ?>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript">
            $(document).ready(function() {
                "use strict";
    $('#contentDiv').css('margin-top', $('#global-nav').css('height'));
                var cTab = parseInt('<?php echo $currentTab; ?>', 10);
                $('#btnReset1, #btnSubmitClean, #btnReset2, #btnPrint, #btnSubmitTable').button();

                $('#mainTabs').tabs();
                $('#mainTabs').tabs("option", "active", cTab);
                $('#tblFac').dataTable({"iDisplayLength": 50, "dom": '<"top"if>rt<"bottom"lp><"clear">', "order": [[0, "asc"]]});
                $('#btnPrint').click(function () {
                    window.open('ShowHsKpg.php', '_blank');
                });
            });
        </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <div style="float:left; margin-right: 100px; margin-top:10px;">
                <h1><?php echo $wInit->pageHeading; ?></h1>
            </div>
            <?php echo $resultMessage ?>
            <div style="clear:both;"></div>
            <form action="RoomStatus.php" method="post"  id="form1" name="form1" >
            <div id="mainTabs" style="font-size: .8em;" class="hhk-tdbox">
                <ul>
                    <li><a href="#clnToday">Rooms to be Cleaned</a></li>
                    <li><a href="#ckin">Guests Checking In</a></li>
                    <li><a href="#showAll">Show All Rooms</a></li>
                </ul>
                <div id="clnToday" class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog">
                    <?php echo $cleanToday; ?>
                    <div class="ui-corner-all submitButtons">
                        <input type="reset" name="btnReset1" value="Reset" id="btnReset1" />
                        <input type="submit" name="btnSubmitClean" value="Save" id="btnSubmitClean" />
                    </div>
                </div>
                <div id="ckin"   class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog">
                    <?php echo $checkingIn; ?>
                </div>
                <div id="showAll">
                    <?php echo $roomTable; ?>
                    <div class="ui-corner-all submitButtons">
                        <input type="button" value="Print" name="btnPrint" id="btnPrint" />
                        <input type="reset" name="btnReset2" value="Reset" id="btnReset2" />
                        <input type="submit" name="btnSubmitTable" value="Save" id="btnSubmitTable" />
                    </div>
                </div>
            </div>
            </form>
        </div>  <!-- div id="contentDiv"-->
    </body>
</html>
