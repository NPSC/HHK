<?php
use HHK\sec\{SecurityComponent, Session, WebInit};
use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLSelector};
use HHK\sec\Labels;


/**
 * Register.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

$wInit = new webInit();

$dbh = $wInit->dbh;

// get session instance
$uS = Session::getInstance();

// Get labels
$labels = Labels::getLabels();




?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $wInit->pageTitle; ?></title>
        <meta http-equiv="x-ua-compatible" content="IE=edge">
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <?php echo CSSVARS; ?>

		<script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
		<script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo ADDR_PREFS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BUFFER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTES_VIEWER_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript">
$(document).ready(function () {
    "use strict";
    $('.ckdate').datepicker();
    $('input[type="button"], input[type="submit"]').button();
    $('#btnActvtyGo').click(function () {
        $(".hhk-alert").hide();
        let stDate = $('#txtactstart').datepicker("getDate");
        if (stDate === null) {
            $('#txtactstart').addClass('ui-state-highlight');
            flagAlertMessage('Enter start date', 'alert');
            return;
        } else {
            $('#txtactstart').removeClass('ui-state-highlight');
        }
        let edDate = $('#txtactend').datepicker("getDate");
        if (edDate === null) {
            edDate = new Date();
        }
        let parms = {
            cmd: 'actrpt',
            start: stDate.toLocaleDateString(),
            end: edDate.toLocaleDateString()
        };
        if ($('#cbVisits').prop('checked')) {
            parms.visit = 'on';
        }
        if ($('#cbReserv').prop('checked')) {
            parms.resv = 'on';
        }
        if ($('#cbHospStay').prop('checked')) {
            parms.hstay = 'on';
        }
        $.post('ws_resc.php', parms,
            function (data) {
                if (data) {
                    try {
                        data = $.parseJSON(data);
                    } catch (err) {
                        alert("Parser error - " + err.message);
                        return;
                    }
                    if (data.error) {
                        if (data.gotopage) {
                            window.open(data.gotopage, '_self');
                        }
                        flagAlertMessage(data.error, 'error');

                    } else if (data.success) {
                        $('#rptdiv').remove();
                        $('#vactivity').append($('<div id="rptdiv"/>').append($(data.success)));
                        $('.hhk-viewvisit').css('cursor', 'pointer');
                        $('#rptdiv').on('click', '.hhk-viewvisit', function () {
                            if ($(this).data('visitid')) {
                                let parts = $(this).data('visitid').split('_');
                                if (parts.length !== 2)
                                    return;
                                var buttons = {
                                    "Save": function () {
                                        saveFees(0, parts[0], parts[1]);
                                    },
                                    "Cancel": function () {
                                        $(this).dialog("close");
                                    }
                                };
                                viewVisit(0, parts[0], buttons, 'View Visit', 'n', parts[1]);
                            } else if ($(this).data('reservid')) {
                                window.location.assign('Reserve.php?rid=' + $(this).data('reservid'));
                            }
                        });
                    }
                }
            });
    });
});
    </script>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";}?> >
        <?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <div>
            <form autocomplete="off">
                <h2 class="hhk-flex" id="page-title-row">
                	<span class="mb-3 mb-md-0"><?php echo $wInit->pageHeading;?></span>
                </h2>
           		<div id="vactivity" class="hhk-tdbox hhk-visitdialog" style="margin-top:10px;">
                <table><tr>
                        <th>Reports</th><th>Dates</th>
                    </tr><tr>
                        <td><input id='cbVisits' type='checkbox' checked="checked"/> Visits</td>
                        <td>Starting: <input type="text" id="txtactstart" class="ckdate" value="" /></td>
                    </tr><tr>
                        <td><input id='cbReserv' type='checkbox'/> Reservations</td>
                        <td>Ending: <input type="text" id="txtactend" class="ckdate" value="" /></td>
                    </tr><tr>
                        <td><input id='cbHospStay' type='checkbox'/> <?php echo $labels->getString('hospital', 'hospital', 'Hospital'); ?> Stays</td>
                        <td></td>
                    </tr><tr>
                        <td></td>
                        <td style="text-align: right;"><input type="button" id="btnActvtyGo" value="Submit"/></td>
                    </tr></table>
                <div id="rptdiv" class="hhk-visitdialog"></div>
            	</div>
            </form>
            </div>

		</div>
    </body>
</html>
