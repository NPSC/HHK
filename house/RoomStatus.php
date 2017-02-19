<?php
/**
 * RoomStatus.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");


require (DB_TABLES . 'nameRS.php');

require (DB_TABLES . 'ReservationRS.php');
require (DB_TABLES . 'AttributeRS.php');

require (CLASSES . 'Notes.php');
require (CLASSES . 'TableLog.php');
require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (HOUSE . 'RoleMember.php');
require (HOUSE . 'Role.php');
require (HOUSE . 'RoomLog.php');
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

// update room cleaning status for existing guest rooms.
ResourceView::dirtyOccupiedRooms($dbh);

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
//                $rooms[$idRoom] = $room;
            }

            $room->setNotes('');
            $room->saveRoom($dbh, $uS->username, TRUE);
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
        $r->saveRoom($dbh, $uS->username, TRUE);
    }
}


$checkingIn = Reservation_1::showListByStatus($dbh, '', '', ReservationStatus::Committed, TRUE, NULL, 1, TRUE);

if ($checkingIn == '') {
    $checkingIn = "<p style='margin-left:60px;'>-None-</p>";
}

// Room table
$cgCols = array(
    array("data" => "Room" ),
    array("data" => "Status" ),
    array("data"=>"Action"),
    array("data"=>"Occupant"),
    array("data" => "Checked In", 'type'=>'date' ),
    array("data" => "Expected Checkout" , 'type'=>'date'),
    array("data" => "Last Cleaned" ),
    array("data"=>"Notes")
    );

$roomTable = new HTMLTable();
$hdrRow = '';

foreach ($cgCols as $c) {
    $hdrRow .= HTMLTable::makeTh($c['data']);
}

$roomTable->addHeaderTr($hdrRow);
$roomTable->addFooterTr($hdrRow);

// Checking out table
$outCols = array(
    array("data" => "Room" ),
    array("data"=>"Visit Status"),
    array("data"=>"Primary Guest"),
    array("data" => "Arrival Date", 'type'=>'date' ),
    array("data" => "Expected Checkout" , 'type'=>'date'),
    array("data"=>"Notes")
);

$ckOutTable = new HTMLTable();
$hdrRow = '';

foreach ($outCols as $c) {
    $hdrRow .= HTMLTable::makeTh($c['data']);
}

$ckOutTable->addHeaderTr($hdrRow);
$ckOutTable->addFooterTr($hdrRow);

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
var dtCols = [
    {
        "aTargets": [ 0 ],
        "bVisible": false,
        "mDataProp": "idRoom"
    },
    {
        "aTargets": [ 1 ],
        "sTitle": "Room",
        "bSearchable": false,
        "bSortable": true,
        "mDataProp": "Title"
    },
    {
        "aTargets": [ 2 ],
        "sTitle": "Status",
        "bSearchable": false,
        "bSortable": true,
        "mDataProp": "Status_Text"
    },
    {
        "aTargets": [ 3 ],
        "sTitle": "Last Cleaned",
        "sType": "date",
        "mDataProp": function (source, type, val) {
            "use strict";
            if (type === 'set') {
                source.Last_Cleaned = val;
                return null;
            } else if (type === 'display') {
                if (source.Date_display === undefined) {
                    if (source.Last_Cleaned !== null) {
                        var dt = new Date(Date.parse(source.Last_Cleaned));
                        source.Date_display = $.datepicker.formatDate("D M d, yy", dt)  //(dt.getMonth() + 1) + '/' + dt.getDate() + '/' + dt.getFullYear() + ' ' + dt.getHours() + ':' + dt.getMinutes();
                    } else {
                        source.Date_display = '';
                    }

                }
                return source.Date_display;
            }
            return source.Last_Cleaned;
        }
    },
     {
        "aTargets": [ 4 ],
        "sTitle": "Notes",
        "bSearchable": false,
        "bSortable": false,
        "mDataProp": "Notes"
    },
    {
        "aTargets": [ 5 ],
        "sTitle": "User",
        "bSortable": true,
        "mDataProp": "Username"
    },
    {
        "aTargets": [ 6 ],
        "sTitle": "Timestamp",
        "sType": "date",
        "mDataProp": function (source, type, val) {
            "use strict";
            if (type === 'set') {
                source.Timestamp = val;
                return null;
            } else if (type === 'display') {
                if (source.Timestamp_display === undefined) {
                    source.Timestamp = new Date(Date.parse(source.Timestamp));
                    source.Timestamp_display = $.datepicker.formatDate("D M d, yy", source.Timestamp);

                }
                return source.Timestamp_display;
            }
            return source.Timestamp;
        }
    }
];
$(document).ready(function() {
    "use strict";
    var cTab = parseInt('<?php echo $currentTab; ?>', 10);
    var cgCols = $.parseJSON('<?php echo json_encode($cgCols); ?>');
    var outCols = $.parseJSON('<?php echo json_encode($outCols); ?>');
    var listEvtTable;
    var coDate = new Date();

    $('#contentDiv').css('margin-top', $('#global-nav').css('height'));
    $('#btnReset1, #btnSubmitClean, #btnReset2, #btnPrint, #btnSubmitTable, #prtCkOut').button();
    $('#mainTabs').tabs({
        beforeActivate: function (event, ui) {
            if (ui.newPanel.length > 0) {
                if (ui.newTab.prop('id') === 'lishoCL' && !listEvtTable) {
                    listEvtTable = $('#dataTbl').dataTable({
                        "aoColumnDefs": dtCols,
                        "bServerSide": true,
                        "bProcessing": true,
                        "bDeferRender": true,
                        "oLanguage": {"sSearch": "Search Log Text:"},
                        "aaSorting": [[6,'asc']],
                        "iDisplayLength": 25,
                        "aLengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                        "Dom": '<"top"ilf>rt<"bottom"ip>',
                        "sAjaxSource": 'ws_resc.php?cmd=clnlog'
                    });
                }
            }
        }
    });
    $('#mainTabs').tabs("option", "active", cTab);

    $('#ckoutDate').datepicker({
        yearRange: '-1:+01',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        numberOfMonths: 1,
        dateFormat: 'M d, yy',
        onClose: function(dateText) {
            var d = new Date(dateText);
            if (d != coDate) {
                coDate = d;
                $('#outTable').DataTable().ajax.url('ws_resc.php?cmd=cleanStat&tbl=outTable&dte=' + $.datepicker.formatDate("yy-mm-dd", coDate));
                $('#outTable').DataTable().ajax.reload();
            }
        }
    });

    $('#ckoutDate').datepicker('setDate', coDate);

    $.extend($.fn.dataTable.defaults, {
        "dom": '<"top"if>rt<"bottom"lp><"clear">',
        "iDisplayLength": 50,
        "aLengthMenu": [[25, 50, -1], [25, 50, "All"]],
        "order": [[ 0, 'asc' ]]
    });

    $('#roomTable').DataTable({
       ajax: {
           url: 'ws_resc.php?cmd=cleanStat&tbl=roomTable',
           dataSrc: 'roomTable'
       },
       "deferRender": true,
       "columns": cgCols
    });

    $('#dirtyTable').DataTable({
       ajax: {
           url: 'ws_resc.php?cmd=cleanStat&tbl=dirtyTable',
           dataSrc: 'dirtyTable'
       },
       "deferRender": true,
       "columns": cgCols
    });

    $('#outTable').DataTable({
       ajax: {
           url: 'ws_resc.php?cmd=cleanStat&tbl=outTable&dte=' + $.datepicker.formatDate("yy-mm-dd", coDate),
           dataSrc: 'outTable'
       },
       "deferRender": true,
       "columns": outCols
    });

    $('#btnPrint').click(function () {
        window.open('ShowHsKpg.php', '_blank');
    });
    $('div#mainTabs').show();
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
            <div id="mainTabs" style="font-size: .8em; display:none;" class="hhk-tdbox">
                <ul>
                    <li><a href="#clnToday">Rooms set Dirty</a></li>
                    <li><a href="#ckin">Guests Checking In</a></li>
                    <li><a href="#ckout">Guests Checking Out</a></li>
                    <li><a href="#showAll">Show All Rooms</a></li>
                    <li id="lishoCL"><a href="#showLog">Show Cleaning Log</a></li>
                </ul>
                <div id="clnToday" class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog">
                    <?php echo $roomTable->generateMarkup(array('id'=>'dirtyTable')); ?>
                    <div class="ui-corner-all submitButtons">
                        <input type="reset" name="btnReset1" value="Reset" id="btnReset1" />
                        <input type="submit" name="btnSubmitClean" value="Save" id="btnSubmitClean" />
                    </div>
                </div>
                <div id="ckin"   class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog">
                    <?php echo $checkingIn; ?>
                </div>
                <div id="ckout"   class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog">
                    <p>
                        <span>Checkout Date: </span><input id="ckoutDate" class="ckdate"/>
                        <input type="button" value="Print" id="prtCkOut" style="margin:3px;"/>
                    </p>
                    <?php echo $ckOutTable->generateMarkup(array('id'=>'outTable')); ?>
                </div>
                <div id="showAll">
                    <?php echo $roomTable->generateMarkup(array('id'=>'roomTable')); ?>
                    <div class="ui-corner-all submitButtons">
                        <input type="button" value="Print" name="btnPrint" id="btnPrint" />
                        <input type="reset" name="btnReset2" value="Reset" id="btnReset2" />
                        <input type="submit" name="btnSubmitTable" value="Save" id="btnSubmitTable" />
                    </div>
                </div>
                <div id="showLog" class="ignrSave">
                  <table cellpadding="0" cellspacing="0" border="0" class="display" id="dataTbl"></table>
                </div>
            </div>
            </form>
        </div>  <!-- div id="contentDiv"-->
    </body>
</html>
