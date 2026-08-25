<?php
use HHK\sec\{Session, WebInit};
use HHK\sec\Labels;
use HHK\Vite\Vite;


/**
 * Register.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

$wInit = new WebInit();

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

        <?php echo Vite::asset(['resources/js/house.js', 'resources/js/house/resv.js']); ?>
        
        <?php echo FAVICON; ?>
        <?php echo CSSVARS; ?>


        <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", () => {
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
                                    data = JSON.parse(data);
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
