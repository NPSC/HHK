<?php

use HHK\CreateMarkupFromDB;
use HHK\History;
use HHK\sec\{Session, WebInit};
use HHK\SysConst\{WebSiteCode};

/**
 * NameSch.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");
// require (CLASSES . 'CreateMarkupFromDB.php');
// require (CLASSES . 'Purchase/RoomRate.php');
// require (CLASSES . 'History.php');

$wInit = new webInit();

$dbh = $wInit->dbh;


$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();

$uS = Session::getInstance();

try {
    $guestHistory = 'f';
    if (isset($uS->siteList[WebSiteCode::House])) {
        // Guest History tab markup
        $guestHistory = CreateMarkupFromDB::generateHTML_Table(History::getCheckedInGuestMarkup($dbh, '../house/GuestEdit.php', FALSE, TRUE), 'curres');
    }

    $recHistory = History::getMemberHistoryMarkup($dbh);

} catch (Exception $ex) {
    $recHistory = $ex->getMessage();
}


$volHistory = 'f';

try {

    if (isset($uS->siteList[WebSiteCode::Volunteer])) {
        // Vol history
        $now = new \DateTime();
        $volHistory = CreateMarkupFromDB::generateHTML_Table(History::getVolEventsMarkup($dbh, $now->sub(new \DateInterval('P3D'))), 'volH');
    }

} catch (Exception $ex) {
    $volHistory = $ex->getMessage();
}

?>
<!DOCTYPE html">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo FULLC_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo GRID_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo FULLC_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>

        <script type="text/javascript">
    function isNumber(n) {
        "use strict";
        return !isNaN(parseFloat(n)) && isFinite(n);
    }
    $(document).ready(function() {

    $.widget( "ui.autocomplete", $.ui.autocomplete, {
        _resizeMenu: function() {
            var ul = this.menu.element;
            ul.outerWidth( Math.max(
                    ul.width( "" ).outerWidth() + 1,
                    this.element.outerWidth()
            ) * 1.1 );
        }
    });

        var d=new Date();
        $('#historyTabs').tabs({
            // Fetch hte calender events when the calendar is visible.
            activate: function(event, ui) {
                if (ui.newTab.index() === 3) {
                    $('#calendar').fullCalendar('render');
                }
            }
        });

    createAutoComplete(
        $('#txtsearch'),
        3,
        {cmd: "srrel", id: 0},
        function( item ) {
            if (item.id === 'i') {
                // New Individual
                window.location = "NameEdit.php?cmd=newind";
            } else if (item.id === 'o') {
                window.location = "NameEdit.php?cmd=neworg";
            }

            var cid = parseInt(item.id, 10);
            if (isNumber(cid)) {
                window.location = "NameEdit.php?id=" + cid;
            }
        },
        false,
        "liveNameSearch.php",
        $('#txtBasis')
    );

    $('input[name="msearch"]').click(function () {
        if ($('#rbmemName').prop('checked')) {
            $('#txtBasis').val('m');
        } else {
            $('#txtBasis').val('e');
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
        $(".fc-icon-wrap").append("\u00A0"); //fix short icon buttons
        $("#btnRefresh button").button();
        $("#btnRefresh").click( function () {
            $('#calendar').fullCalendar( 'refetchEvents');
        });
        $('#gotoDate').change( function() {
            var gtDate = new Date($('#gotoDate').datepicker('getDate'));
            $('#calendar').fullCalendar('gotoDate', gtDate);
        });
        $('#historyTabs').show();
        $('#txtsearch').focus();
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
                        <label for="rbmemName">Name</label><input type="radio" name="msearch" checked="checked" id="rbmemName" value="m" />
                        <label for="rbmemEmail">Email</label><input type="radio" name="msearch" id="rbmemEmail" value="e" />
                    </span><input type="hidden" id="txtBasis" value="m"/>
                    <input type="search" id="txtsearch" size="20" title="Enter at least 3 characters to invoke search" />
                </div>
            </div>
            <div style="clear:both; margin-top:50px"></div>
            <div id="historyTabs" class="hhk-member-detail" style="margin-bottom: 10px; display:none;">
                <ul>
                    <li><a href="#memHistory">Member History</a></li>
                    <?php
                        if ($guestHistory != 'f'){echo "<li><a href='" . "#resHistory" . "'>Current Guests</a></li>";}
                        if ($volHistory != 'f'){
                            echo "<li><a href='" . "#volHistory" . "'>Recent Event history</a></li>"
                                ."<li><a href='#important'>Today's Events</a></li>";
                        }
                    ?>
                </ul>
                <div id="memHistory" class="hhk-tdbox">
                    <h3>Member History</h3>
                    <?php echo $recHistory ?>
                </div>
                <?php if ($guestHistory != 'f') { ?>
                <div id="resHistory" class="hhk-tdbox">
                    <h3>Current Guests</h3>
                    <?php echo $guestHistory; ?>
                </div> <?php }; ?>
                <?php if ($volHistory != 'f') { ?>
                <div id="volHistory" class="ui-widget">
                    <?php echo $volHistory ?>
                </div>
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
                </div> <?php }; ?>
            </div>
        </div>  <!-- div id="page"-->
    </body>
</html>
