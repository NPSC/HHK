<?php

use HHK\Config_Lite\Config_Lite;
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
    doExcelDownLoad($rows, 'Show All Rooms');
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

    // Delete notes
    if ($guestAdmin && isset($_POST[$prefix . 'cbDeln'])) {

        foreach ($_POST[$prefix . 'cbDeln'] as $key => $p) {

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

    // Set Notes
    if (isset($_POST[$prefix . 'taNotes'])) {

        foreach ($_POST[$prefix . 'taNotes'] as $key => $p) {

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

            $notes = filter_var($p, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if ($notes != '') {
                $oldNotes = is_null($room->getNotes()) ? '' : $room->getNotes();
                $room->setNotes($oldNotes . "\r\n" . date('m-d-Y') . ', ' . $uS->username . ' - ' . $notes);
            }
        }
    }

    // Save all rooms
    foreach ($rooms as $r) {
        $r->saveRoom($dbh, $uS->username, TRUE);
    }
}

//Resource grouping controls
$rescGroups = readGenLookupsPDO($dbh, 'Room_Group');

$rescGroupBy = '';

if (isset($rescGroups[$uS->CalResourceGroupBy])) {
    $rescGroupBy = $uS->CalResourceGroupBy;
    $groupingTitle = $rescGroups[$uS->CalResourceGroupBy]["Description"];
}

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
        <script type="text/javascript" src="<?php echo NOTES_VIEWER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BUFFER_JS; ?>"></script>

        <script type="text/javascript">

            function getDtBtns(title){
                return [
                    {
                        extend: "print",
                        className: "ui-corner-all",
                        autoPrint: true,
                        paperSize: "letter",
                        exportOptions: {
                            columns: ":not('.noPrint')",
                            orthogonal:"print",
                        },
                        title: function(){
                            return title;
                        },
                        messageBottom: function(){
                            var now = moment().format("MMM D, YYYY") + " at " + moment().format("h:mm a");
                            return '<div style="padding-top: 10px; position: fixed; bottom: 0; right: 0">Printed on '+now+'</div>';
                        },
                        customize: function (win) {
                            $(win.document.body).css("font-size", "0.9em");

                            $(win.document.body).find("table").css("font-size", "inherit");
                        }
                    }
                ];
            }

            $(document).ready(function () {
                "use strict";

                var dateFormat = '<?php echo "ddd MMM D, YYYY"; ?>';
                var groupingTitle = $('#groupingTitle').val();
                
                var cgCols = [
                    {	'data': 'Group_Title',
                        'title': groupingTitle,
                        "visible": false
                    },
                    {
                        'data': 'Room',
                        'title': 'Room',
                        'searchable': true
                    },
                    {
                        'data': 'Status',
                        'title': 'Status',
                        'searchable': false,
                        'sortable': true
                    },
                    {
                        'data': 'Action',
                        'title': 'Action',
                        'searchable': false,
                        'sortable': false,
                        className: "noPrint"
                    },
                    {
                        'data': 'Occupant',
                        'title': 'Occupant',
                        'searchable': true,
                        'sortable': true
                    },
                    {
                        'data': 'numGuests',
                        'title': 'Guests',
                        'searchable': false,
                        'sortable': true
                    },
                    {
                        'data': 'Checked_In',
                        'title': 'Checked In',
                        'type': 'date',
                        render: function (data, type, row) {
                            return dateRender(data, type, dateFormat);
                        },
                        'searchable': true,
                        'sortable': true
                    },
                    {
                        'data': 'Expected_Checkout',
                        'title': 'Expected Checkout',
                        'type': 'date',
                        render: function (data, type, row) {
                            return dateRender(data, type, dateFormat);
                        },
                        'searchable': true,
                        'sortable': true
                    },
                    {
                        'data': 'Last_Cleaned',
                        'title': 'Last Cleaned',
                        'type': 'date',
                        render: function (data, type, row) {
                            return dateRender(data, type, dateFormat);
                        },
                        'searchable': true,
                        'sortable': true
                    },
                    {
                        'data': 'Last_Deep_Clean',
                        'title': 'Last Deep Clean',
                        'type': 'date',
                        render: function (data, type, row) {
                            if(type == 'print'){
                                return $(data).val();
                            }else{
                                return data;
                            }
                        },
                        'searchable': true,
                        'sortable': true
                    },
                    {
                        'data': 'Notes',
                        'title': 'Latest Note',
                        'searchable': true,
                        'sortable': false
                    }
                ];

        var inCols = [
                    {
                        'data': 'Primary Guest',
                        'title': '<?php echo $labels->getString('MemberType', 'primaryGuest', 'Primary Guest'); ?>',
                        'searchable': true,
                        'sortable': true
                    },
                    {
                        'data': 'Guests',
                        'title': '<?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s',
                        'searchable': true,
                        'sortable': true
                    },
                    {
                        'data': 'Arrival Date',
                        'title': 'Expected Arrival',
                        'type': 'date',
                        render: function (data, type) {
                            return dateRender(data, type, dateFormat);
                        },
                        'searchable': true,
                        'sortable': true
                    },
                    {
                        'data': 'Expected Departure',
                        'title': 'Expected Departure',
                        'type': 'date',
                        render: function (data, type) {
                            return dateRender(data, type, dateFormat);
                        },
                        'searchable': true,
                        'sortable': true
                    },
                    {
                        'data': 'Room',
                        'title': 'Room',
                        'searchable': true,
                        'sortable': false
                    },
                    {
                        'data': 'Nights',
                        'title': 'Nights',
                        'searchable': true,
                        'sortable': false
                    }
                ];

                var outCols = [
                    {
                        'data': 'Room',
                        'title': 'Room',
                        'searchable': true,
                        'sortable': true
                    },
                    {
                        'data': 'Visit Status',
                        'title': 'Status',
                        'searchable': false,
                        'sortable': true
                    },
                    {
                        'data': 'Primary Guest',
                        'title': 'Occupant',
                        'searchable': true,
                        'sortable': true
                    },
                    {
                        'data': 'Arrival Date',
                        'title': 'Checked In',
                        'type': 'date',
                        render: function (data, type) {
                            return dateRender(data, type, dateFormat);
                        },
                        'searchable': true,
                        'sortable': true
                    },
                    {
                        'data': 'Expected Checkout',
                        'title': 'Expected Checkout',
                        'type': 'date',
                        render: function (data, type) {
                            return dateRender(data, type, dateFormat);
                        },
                        'searchable': true,
                        'sortable': true
                    },
                    {
                        'data': 'Notes',
                        'title': 'Latest Note',
                        'searchable': true,
                        'sortable': false
                    }
                ];

                var dtColDefs = [
                    {
                        'targets': [0],
                        'data': 'Room',
                        'title': 'Room',
                        'searchable': true,
                        'sortable': true
                    },
                    {
                        'targets': [1],
                        'data': 'Type',
                        'visible': false
                    },
                    {
                        'targets': [2],
                        'data': 'Status',
                        'title': 'Status',
                        'searchable': false,
                        'sortable': true
                    },
                    {
                        "targets": [3],
                        'data': 'Last Cleaned',
                        'title': 'Last Cleaned',
                        render: function (data, type, row) {
                            return dateRender(data, type, dateFormat);
                        }
                    },
                    {
                        "targets": [4],
                        'data': 'Last Deep Clean',
                        'title': 'Last Deep Clean',
                        render: function (data, type, row) {
                            return dateRender(data, type, dateFormat);
                        }
                    },
                    {
                        'targets': [5],
                        'data': 'Notes',
                        'title': 'Latest Note',
                        'searchable': true,
                        'sortable': false
                    },
                    {
                        'targets': [6],
                        'data': 'User',
                        'title': 'User',
                        'sortable': true,
                        'searchable': true
                    },
                    {
                        "targets": [7],
                        'data': 'Timestamp',
                        'title': 'Timestamp',
                        render: function (data, type, row) {
                            return dateRender(data, type, dateFormat);
                        }
                    }
                ];
            
                var cTab = parseInt('<?php echo $currentTab; ?>', 10);
                var listEvtTable;
                var coDate = new Date();

                $.extend($.fn.dataTable.defaults, {
                    "dom": '<"top"if>rt<"bottom"lp><"clear">',
                    "displayLength": 50,
                    "lengthMenu": [[25, 50, -1], [25, 50, "All"]]
                });

                $('#btnReset1, #btnSubmitClean, #btnReset2, #btnPrintAll, #btnExcelAll, #btnSubmitTable, #prtCkOut, #prtCkIn, #prtClnToday').button();

                $("#roomDetailsDialog").dialog({
                    width: getDialogWidth(1000),
                    autoOpen: false,
                    modal: true,
                    close: function(){
                        $("#roomDetailsDialog").empty();
                    }
                });

                $("#mainTabs").on("click", ".roomDetails", function(e){
                    
                    let $this = $(this);
                    $("#roomDetailsDialog").empty();
                    $("#roomDetailsDialog").append('<div class="roomNotes"></div>');

                    $("#roomDetailsDialog").find(".roomNotes").notesViewer({
                        linkId: $this.data("idroom"),
                        linkType: 'room',
                        newNoteAttrs: {id:'taNewRNote', name:'taNewRNote'},
                        alertMessage: function(text, type) {
                            flagAlertMessage(text, type);
                        }
                    });

                    $("#roomDetailsDialog").dialog("option", "title", $this.data("title"));
                    $("#roomDetailsDialog").dialog("open");

                });

                $('#mainTabs').tabs({
                    beforeActivate: function (event, ui) {
                        if (ui.newPanel.length > 0) {
                            if (ui.newTab.prop('id') === 'lishoCL' && !listEvtTable) {

                                listEvtTable = $('#dataTbl').DataTable({
                                    "processing": true,
                                    "serverSide": true,
                                    "ajax": {
                                        "url": "ws_resc.php?cmd=clnlog",
                                        "type": "POST"
                                    },
                                    "columnDefs": dtColDefs,
                                    "deferRender": true,
                                    "order": [[7, 'desc']],
                                    "pageLength": 50,
                                    "lengthMenu": [25, 50, 100],
                                    "dom": '<"top"Bif><\"hhk-overflow-x\"rt><"bottom"lp>',
                                    "buttons": getDtBtns("Housekeeping - Cleaning Log"),
                                });
                            }
                        }
                    }
                });
                $('#mainTabs').tabs("option", "active", cTab);

				$('#ckInDate').datepicker({
                    yearRange: '-1:+01',
                    changeMonth: true,
                    changeYear: true,
                    autoSize: true,
                    numberOfMonths: 1,
                    dateFormat: 'M d, yy',
                    onClose: function (dateText) {
                        var d = new Date(dateText);
                        if (d != coDate) {
                            coDate = d;
                            $('#inTable').DataTable().ajax.url('ws_resc.php?cmd=cleanStat&tbl=inTable&stdte=' + $.datepicker.formatDate("yy-mm-dd", coDate) + '&enddte=' + $.datepicker.formatDate("yy-mm-dd", coDate));
                            var updatedBtn = getDtBtns("Housekeeping - <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s Checking In - " + $.datepicker.formatDate("M d, yy", coDate))[0];
                            $('#inTable').DataTable().button(0).remove().add(0, updatedBtn);
                            $('#inTable').DataTable().ajax.reload();
                        }
                    }
                });

                $('#ckInDate').datepicker('setDate', coDate);

                $('#inButtonSet.week-button-group').on('click', 'button', function (e) {
                    var btn = $(this)
                    $('.week-button-group button').removeClass("ui-state-active");
                    if (btn.data("weeks")) {
                        var startDate = new Date();
                        var endDate = new Date();
                        endDate.setDate(startDate.getDate() + (btn.data("weeks") * 7));

                        $('#inTable').DataTable().ajax.url('ws_resc.php?cmd=cleanStat&tbl=inTable&stdte=' + $.datepicker.formatDate("yy-mm-dd", startDate) + '&enddte=' + $.datepicker.formatDate("yy-mm-dd", endDate));
                        var updatedBtn = getDtBtns("Housekeeping - <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s Checking In - " + $.datepicker.formatDate("M d, yy", startDate) + " to " + $.datepicker.formatDate("M d, yy", endDate))[0];
                        $('#inTable').DataTable().button(0).remove().add(0, updatedBtn);
                        $('#inTable').DataTable().ajax.reload();
                        btn.addClass("ui-state-active");
                    }
                });

                $('#ckoutDate').datepicker({
                    yearRange: '-1:+01',
                    changeMonth: true,
                    changeYear: true,
                    autoSize: true,
                    numberOfMonths: 1,
                    dateFormat: 'M d, yy',
                    onClose: function (dateText) {
                        var d = new Date(dateText);
                        if (d != coDate) {
                            coDate = d;
                            $('#outTable').DataTable().ajax.url('ws_resc.php?cmd=cleanStat&tbl=outTable&stdte=' + $.datepicker.formatDate("yy-mm-dd", coDate) + '&enddte=' + $.datepicker.formatDate("yy-mm-dd", coDate));
                            var updatedBtn = getDtBtns("Housekeeping - <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s Checking Out - " + $.datepicker.formatDate("M d, yy", coDate))[0];
                            $('#outTable').DataTable().button(0).remove().add(0, updatedBtn);
                            $('#outTable').DataTable().ajax.reload();
                        }
                    }
                });

                $('#ckoutDate').datepicker('setDate', coDate);

                $('#outButtonSet.week-button-group').on('click', 'button', function (e) {
                    var btn = $(this)
                    $('.week-button-group button').removeClass("ui-state-active");
                    if (btn.data("weeks")) {
                        var startDate = new Date();
                        var endDate = new Date();
                        endDate.setDate(startDate.getDate() + (btn.data("weeks") * 7));

                        $('#outTable').DataTable().ajax.url('ws_resc.php?cmd=cleanStat&tbl=outTable&stdte=' + $.datepicker.formatDate("yy-mm-dd", startDate) + '&enddte=' + $.datepicker.formatDate("yy-mm-dd", endDate));
                        var updatedBtn = getDtBtns("Housekeeping - <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s Checking Out - " + $.datepicker.formatDate("M d, yy", startDate) + " to " + $.datepicker.formatDate("M d, yy", endDate))[0];
                        $('#outTable').DataTable().button(0).remove().add(0, updatedBtn);
                        $('#outTable').DataTable().ajax.reload();
                        btn.addClass("ui-state-active");
                    }
                });


                $('#roomTable').dataTable({
                    ajax: {
                        url: 'ws_resc.php?cmd=cleanStat&tbl=roomTable',
                        dataSrc: 'roomTable'
                    },
                    "deferRender": true,
                    "columns": cgCols,
                    rowGroup: {dataSrc: 'Group_Title'},
                    "dom": '<"top"Bif><\"hhk-overflow-x\"rt><"bottom"lp>',
                    "buttons": getDtBtns("Housekeeping - All Rooms"),
                    "initComplete": function(settings, json){
                    	$('.ckdate').datepicker({
                            yearRange: '<?php echo $uS->StartYear; ?>:+01',
                            changeMonth: true,
                            changeYear: true,
                            autoSize: true,
                            numberOfMonths: 1,
                            maxDate: 0,
                            dateFormat: 'M d, yy'
                        });
                    }
                });

                $('#dirtyTable').dataTable({
                    'responsive':true,
                    ajax: {
                        url: 'ws_resc.php?cmd=cleanStat&tbl=dirtyTable',
                        dataSrc: 'dirtyTable'
                    },
                    "deferRender": true,
                    "columns": cgCols,
                    rowGroup: {dataSrc: 'Group_Title'},
                    "dom": '<"top"Bif><\"hhk-overflow-x\"rt><"bottom"lp>',
                    "buttons": getDtBtns("Housekeeping - Rooms Not Ready"),
                    "initComplete": function(settings, json){
                    	$('.ckdate').datepicker({
                            yearRange: '<?php echo $uS->StartYear; ?>:+01',
                            changeMonth: true,
                            changeYear: true,
                            autoSize: true,
                            numberOfMonths: 1,
                            maxDate: 0,
                            dateFormat: 'M d, yy'
                        });
                    }
                });

                var outTbl = $('#outTable').DataTable({
                    ajax: {
                        url: 'ws_resc.php?cmd=cleanStat&tbl=outTable&stdte=' + $.datepicker.formatDate("yy-mm-dd", coDate) + '&enddte=' + $.datepicker.formatDate("yy-mm-dd", coDate),
                        dataSrc: 'outTable'
                    },
                    "deferRender": true,
                    "columns": outCols,
                    "dom": '<"top"Bif><\"hhk-overflow-x\"rt><"bottom"lp>',
                    "buttons": getDtBtns("Housekeeping - <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s Checking Out - " + $.datepicker.formatDate("M d, yy", coDate)),
                });
                outTbl.buttons().container().appendTo("#ckout .tbl-btns");

                var inTbl = $('#inTable').DataTable({
                    ajax: {
                        url: 'ws_resc.php?cmd=cleanStat&tbl=inTable&stdte=' + $.datepicker.formatDate("yy-mm-dd", coDate) + '&enddte=' + $.datepicker.formatDate("yy-mm-dd", coDate),
                        dataSrc: 'inTable'
                    },
                    "deferRender": true,
                    "columns": inCols,
                    "dom": '<"top"Bif><\"hhk-overflow-x\"rt><"bottom"lp>',
                    "buttons": getDtBtns("Housekeeping - <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s Checking In - " + $.datepicker.formatDate("M d, yy", coDate)),
                });

                inTbl.buttons().container().appendTo("#ckin .tbl-btns");

                $('#atblgetter').dataTable({
                    'responsive':true,
                    'columnDefs': [
                        {'targets': [3, 4],
                            'type': 'date',
                            'render': function (data, type) {
                                return dateRender(data, type, dateFormat);
                            }
                        }
                    ],
                    "dom": '<"top"if><\"hhk-overflow-x\"rt><"bottom"lp>',
                });

                $('#outButtonSet').controlgroup();
                $('#inButtonSet').controlgroup();

                $('div#mainTabs').show();
            });
        </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv" style="margin-bottom: 60px;">
            <div class="my-1">
                <h1><?php echo $wInit->pageHeading; ?></h1>
            </div>

            <div class="hhk-list-group">
                <div class="list-group-item list-group-item-action active" aria-current="true">
                    <div class="d-flex w-100 justify-content-between">
                    <h5 class="mb-1">List group item heading</h5>
                    <small>3 days ago</small>
                    </div>
                    <p class="mb-1">Some placeholder content in a paragraph.</p>
                    <small>And some small print.</small>
                </div>
                <div class="list-group-item list-group-item-action">
                    <div class="d-flex w-100 justify-content-between">
                    <h5 class="mb-1">List group item heading</h5>
                    <small class="text-body-secondary">3 days ago</small>
                    </div>
                    <p class="mb-1">Some placeholder content in a paragraph.</p>
                    <small class="text-body-secondary">And some muted small print.</small>
                </div>
                <div class="list-group-item list-group-item-action">
                    <div class="d-flex w-100 justify-content-between">
                    <h5 class="mb-1">List group item heading</h5>
                    <small class="text-body-secondary">3 days ago</small>
                    </div>
                    <p class="mb-1">Some placeholder content in a paragraph.</p>
                    <small class="text-body-secondary">And some muted small print.</small>
                </div>
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
