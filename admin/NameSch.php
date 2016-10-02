<?php
/**
 * NameSch.php
 *
 * @category  member
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
require ("AdminIncludes.php");
require (CLASSES . 'CreateMarkupFromDB.php');
require (CLASSES . 'Purchase/RoomRate.php');
require (CLASSES . 'History.php');
define('FULLC_JS', 'js/fullcalendar.min.js');
define('FULLC_CSS', 'css/fullcalendar.css');

$wInit = new webInit();

$dbh = $wInit->dbh;


$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();

$uS = Session::getInstance();

$guestHistory = 'f';
if (isset($uS->siteList[WebSiteCode::House])) {
    // Guest History tab markup
    $guestHistory = CreateMarkupFromDB::generateHTML_Table(History::getCheckedInGuestMarkup($dbh, '../house/GuestEdit.php', FALSE), 'curres');
}

$recHistory = History::getMemberHistoryMarkup($dbh);

$volHistory = 'f';
if (isset($uS->siteList[WebSiteCode::Volunteer])) {
    // Vol history
    $now = new DateTime();
    $volHistory = CreateMarkupFromDB::generateHTML_Table(History::getVolEventsMarkup($dbh, $now->sub(new DateInterval('P3D'))), 'volH');
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"  "http://www.w3.org/TR/html4/loose.dtd">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />
        <link href="css/default.css" rel="stylesheet" type="text/css" />
<?php echo TOP_NAV_CSS; ?>
        <link href="<?php echo FULLC_CSS; ?>" rel="stylesheet" type="text/css" />
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript">
    function isNumber(n) {
        "use strict";
        return !isNaN(parseFloat(n)) && isFinite(n);
    }
    $(document).ready(function() {
        var lastXhr;
        var d=new Date();
        $('#historyTabs').tabs({
            // Fetch hte calender events when the calendar is visible.
            activate: function(event, ui) {
                if (ui.newTab.index() === 3) {
                    $('#calendar').fullCalendar('render');
                }
            }
        });
        $('#txtsearch').autocomplete({
            source: function (request, response) {
                // Don't send for numbers
                if (isNumber(parseInt(request.term, 10))) {
                    response();
                }
                var schType = 'm';
                if ($('#rbmemEmail').prop("checked")) {
                    schType = 'e';
                }
                // get more data
                var inpt = {
                    cmd: "srrel",
                    letters: request.term,
                    basis: schType,
                    id: 0
                };
                lastXhr = $.getJSON("liveNameSearch.php", inpt,
                    function(data, status, xhr) {
                     if (xhr === lastXhr) {
                        if (data.error) {
                            data.value = data.error;
                        }

                        response(data);
                    }
                    });
            },
            minLength: 3,
            select: function( event, ui ) {
                if (!ui.item) {
                    return;
                }
                if (ui.item.id == 'i') {
                    // New Individual
                    window.location = "NameEdit.php?cmd=newind";
                } else if (ui.item.id == 'o') {
                    window.location = "NameEdit.php?cmd=neworg";
                }

                var cid = parseInt(ui.item.id, 10);
                if (isNumber(cid)) {
                    window.location = "NameEdit.php?id=" + cid;
                }
            }
        });
        $('#txtsearch').keypress(function (event) {
            var mm = $(this).val();
            if (event.keyCode == '13') {
                if (mm == '' || !isNumber(parseInt(mm, 10))) {
                    alert("Don't press the return key unless you enter an Id.");
                    event.preventDefault();
                } else {
                    window.location = "NameEdit.php?id=" + mm;
                }
            }
        });
        $('#calendar').fullCalendar({
            //aspectRatio: 1.6,
            theme: true,
            header: {left: 'title', center: 'agendaWeek,agendaDay', right: 'today prev,next' },
            allDayDefault: false,
            lazyFetching: true,
            draggable: false,
            editable: false,
            selectHelper: false,
            selectable: false,
            unselectAuto: false,
            minTime: '5:00am',
            firstHour: 7,
            year: d.getFullYear(),
            month: d.getMonth(),
            ignoreTimezone: false,
            defaultView: 'agendaDay',
            eventSources: [{
                url: "../volunteer/gCalFeed.php?c=getday",
                ignoreTimezone: false
            }]
        });
        $("#btnRefresh").click( function () {
            $('#calendar').fullCalendar( 'refetchEvents');
        });
        $('#gotoDate').change( function() {
            var gtDate = new Date($('#gotoDate').datepicker('getDate'));
            $('#calendar').fullCalendar('gotoDate', gtDate);
        });
    });
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?> >
<?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-member-detail"  style="background:#EFDBC2; margin-bottom:10px;">
                <div style="float: left; border-width: 1px; border-color: gray; border-style: ridge; padding: 2px;">
                    <span>Search: </span>
                    <span style="margin: 0 10px;">
                        <label for="rbmemName">Name</label><input type="radio" name="msearch" checked="checked" id="rbmemName" />
                        <label for="rbmemEmail">Email</label><input type="radio" name="msearch" id="rbmemEmail" />
                    </span>
                    <input type="text" id="txtsearch" size="20" title="Enter at least 3 characters to invoke search" />
                </div>
            </div>
            <div style="clear:both; margin-top:50px"></div>
            <div id="historyTabs" class="hhk-member-detail" style="margin-bottom: 10px;">
                <ul>
                    <li><a href="#memHistory">Member History</a></li>
                    <?php if ($guestHistory != 'f') echo "<li><a href='" . "#resHistory" . "'>Current Guests</a></li>"; ?>
                    <?php if ($volHistory != 'f') echo "<li><a href='" . "#volHistory" . "'>Recent Event history</a></li>"; ?>
                    <li><a href="#important">Today's Events</a></li>
                </ul>
                <div id="memHistory">
                    <h3>Member History</h3>
                    <?php echo $recHistory ?>
                </div>
                <?php if ($guestHistory != 'f') { ?>
                <div id="resHistory">
                    <h3>Current Guests</h3>
                    <?php echo $guestHistory; ?>
                </div> <?php }; ?>
                <?php if ($volHistory != 'f') { ?>
                <div id="volHistory" class="ui-widget">
                    <?php echo $volHistory ?>
                </div> <?php }; ?>
                <div id="important">
                    <div style="margin-bottom:7px; padding:3px; min-width:800px;">
                        <div id="btnRefresh" style="font-size: 0.9em; float: left;">
                            <button>Refresh Calendar</button>
                        </div>
                        <div style="font-size: 0.9em; float: left; padding-top:5px;">
                            <label for="gotoDate" style="margin-left:15px;">Go To Date: </label>
                            <input type="text" id="gotoDate" class="ckdate ignrSave" value=""/>
                            <label for="includeHouseCal" style="margin-left:15px;">Include House Calendar</label>
                            <input type="checkbox" id="includeHouseCal" class="ignrSave" checked="checked" />
                        </div>
                        <div style="clear: both;"></div>
                        <div id="calendar"></div>
                    </div>
                </div>
            </div>
        </div>  <!-- div id="page"-->
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo FULLC_JS; ?>"></script>
    </body>
</html>
