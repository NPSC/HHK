<?php

use HHK\Common;
use HHK\ExcelHelper;
use HHK\sec\{SecurityComponent, Session, WebInit};
use HHK\House\ResourceView;
use HHK\SysConst\RoomState;
use HHK\House\Room\Room;
use HHK\sec\Labels;

/**
 * RoomStatus.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");


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

$labels = Labels::getLabels();
$guestAdmin = SecurityComponent::is_Authorized("guestadmin");

// update room cleaning status for existing guest rooms.
ResourceView::dirtyOccupiedRooms($dbh);

$currentTab = 2;

if (isset($_POST['btnExcelAll'])) {

    // download to excel
    $rows = ResourceView::roomsClean($dbh, '', $guestAdmin, true);
    ExcelHelper::doExcelDownLoad($rows, 'Show All Rooms');
}

if (isset($_POST['btnSubmitTable']) or isset($_POST['btnSubmitClean'])) {

    $rooms = array();
    $prefix = '';
    $currentTab = 3;

    if (isset($_POST['btnSubmitClean'])) {
        $prefix = RoomState::Dirty;
        $currentTab = 0;
    }

    // Set clean
    if (isset($_POST[$prefix . 'cbClean'])) {

        foreach ($_POST[$prefix . 'cbClean'] as $key => $p) {

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
    }

    // Set Ready
    if (isset($_POST[$prefix . 'cbReady']) && $uS->HouseKeepingSteps > 1) {

        foreach ($_POST[$prefix . 'cbReady'] as $key => $p) {

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

            $room->putReady();
        }
    }

    // Set dirty
    if (isset($_POST[$prefix . 'cbDirty'])) {

        foreach ($_POST[$prefix . 'cbDirty'] as $key => $p) {

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
    }

    // Set deep clean date
    if (isset($_POST[$prefix . 'deepCleanDate'])) {

        foreach ($_POST[$prefix . 'deepCleanDate'] as $key => $p) {

            $idRoom = intval(filter_var($key, FILTER_SANITIZE_NUMBER_INT), 10);
            $deepCleanDate = filter_var($p, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if ($idRoom == 0) {
                continue;
            }

            if (isset($rooms[$idRoom])) {
                $room = $rooms[$idRoom];
            } else {
                $room = new Room($dbh, $idRoom);
                $rooms[$idRoom] = $room;
            }

            $room->setLastDeepCleanDate($deepCleanDate);
        }
    }

    // Save all rooms
    foreach ($rooms as $r) {
        $r->saveRoom($dbh, $uS->username, TRUE);
    }

}

//Resource grouping controls
$rescGroups = Common::readGenLookupsPDO($dbh, 'Room_Group');

$rescGroupBy = '';

if (isset($rescGroups[$uS->CalResourceGroupBy])) {
    $rescGroupBy = $uS->CalResourceGroupBy;
    $groupingTitle = $rescGroups[$uS->CalResourceGroupBy]["Description"];
}
//add datetime to reservations report
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="theme-color" content="#5c9ccc">
        
        <title><?php echo $pageTitle; ?></title>

        <?php echo JQ_UI_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <?php echo CSSVARS; ?>

        <style type="text/css"  media="print">
            #ckout {margin:0; padding:0; font: 12px Arial, Helvetica,"Lucida Grande", serif; color: #000;}
            @page { margin: 1cm; }
        </style>

        <?php echo JQ_DT_CSS ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo HTMLENTITIES_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo DOMPURIFY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTES_VIEWER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BUFFER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo HOUSEKEEPING_JS; ?>"></script>

        <script type="text/javascript">
            window.labels = {
                primaryGuest: "<?php echo $labels->getString('MemberType', 'primaryGuest', 'Primary Guest'); ?>",
                visitor: "<?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>"
            }
            window.curTab = parseInt('<?php echo $currentTab; ?>', 10);
            window.startYear = "<?php echo $uS->StartYear; ?>";
        </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv" style="margin-bottom: 60px;">
            <div class="my-1">
                <h1><?php echo $wInit->pageHeading; ?></h1>
            </div>

            <form action="RoomStatus.php" method="post"  id="form1" name="form1">
                <div id="mainTabs" style="font-size: .8em; display:none; width: 100%" class="hhk-tdbox hhk-mobile-tabs">
                    <div class="hhk-flex ui-widget-header ui-corner-all">
                        <div class="d-xl-none d-flex" style="align-items:center"><span class="ui-icon ui-icon-triangle-1-w"></span></div>
                        <ul class="hhk-flex" style="border:none;">
                            <li><a href="#clnToday">Rooms Not Ready</a></li>
                            <li><a href="#ckin"><?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s Checking In</a></li>
                            <li><a href="#ckout"><?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s Checking Out</a></li>
                            <li><a href="#showAll">Show All Rooms</a></li>
                            <li id="lishoCL"><a href="#showLog">Show Cleaning Log</a></li>
                        </ul>
                        <div class="d-xl-none d-flex" style="align-items:center"><span class="ui-icon ui-icon-triangle-1-e"></span></div>
                    </div>
                    <div id="clnToday">
                        <table id='dirtyTable' class=' order-column display ' style='width:100%;'></table>
                        <div class="ui-corner-all submitButtons">
                            <input type="reset" name="btnReset1" value="Reset" id="btnReset1" />
                            <input type="submit" name="btnSubmitClean" value="Save" id="btnSubmitClean" />
                        </div>
                    </div>
                    <div id="ckin">
						<div class="hhk-flex tbl-btns">
                            <div id="inButtonSet" class="week-button-group">
                                <button type="button" data-weeks="1" class="ui-corner-left">1 Week</button>
                                <button type="button" data-weeks="2" class="">2 Weeks</button>
                                <button type="button" data-weeks="4" class="ui-corner-right">4 Weeks</button>
                            </div>
                            <div class="ui-widget ui-widget-content ui-corner-all mx-3 p-1">
                                <label>Or Choose Check-in Date: </label>
                                <input id="ckInDate" class="ckdate"/>
                            </div>
                        </div>

                        <table id='inTable' class=' order-column display ' style='width:100%;' ></table>
                    </div>
                    <div id="ckout">
                        <div class="hhk-flex tbl-btns">
                            <div id="outButtonSet" class="week-button-group">
                                <button type="button" data-weeks="1" class="ui-corner-left">1 Week</button>
                                <button type="button" data-weeks="2" class="">2 Weeks</button>
                                <button type="button" data-weeks="4" class="ui-corner-right">4 Weeks</button>
                            </div>
                            <div class="ui-widget ui-widget-content ui-corner-all mx-3 p-1">
                                <label>Or Choose Checkout Date: </label>
                                <input id="ckoutDate" class="ckdate"/>
                            </div>
                        </div>

                        <table id='outTable' class=' order-column display ' style='width:100%;' ></table>
                    </div>
                    <div id="showAll">
                        <table id='roomTable' class=' order-column display ' style='width:100%;'></table>
                        <div class="ui-corner-all submitButtons">
                            <input type="submit" value="Download to Excel" id="btnExcelAll" name="btnExcelAll" />
                            <input type="reset" name="btnReset2" value="Reset" id="btnReset2" />
                            <input type="submit" name="btnSubmitTable" value="Save" id="btnSubmitTable" />
                        </div>
                    </div>
                    <div id="showLog" class="ignrSave">
                        <table style="width:100%;" class='order-column display ' id="dataTbl"></table>
                    </div>
                </div>
            </form>
            <div id="roomDetailsDialog" style="font-size: 0.8em;"></div>
            <input type="hidden" value="<?php echo $groupingTitle;  ?>" id='groupingTitle' />
        </div>  <!-- div id="contentDiv"-->
    </body>
</html>
