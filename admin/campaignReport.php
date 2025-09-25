<?php

use HHK\sec\{Session, WebInit};
/**
 * campaignReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("AdminIncludes.php");

$wInit = new WebInit();

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();

$uS = Session::getInstance();

$fyMonths = $uS->fy_diff_Months;
$startYear = '2013';


$rb_fyChecked = "checked='checked'";
$rb_cyChecked = "";


if (filter_has_var(INPUT_POST, "selYears")) {
    $yearSelected = filter_input(INPUT_POST, "selYears", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
} else {
    $yearSelected = "all";
}
$selYearOptions = getYearOptionsMarkup($yearSelected, $startYear, $fyMonths);

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>

        <script type="text/javascript">
            // Init j-query
            $(document).ready(function() {

            	$("input[type=submit], input[type=button]").button();

                $('#btnCamp').click( function() {
                    var rb;
                    if ($('#rb_Cal_fy').prop('checked') ) {
                        rb = 'fy';
                    }
                    else {
                        rb = 'cy';
                    }
                    var yr = $('#selYears').val();
                    var fym = $('input#fyMonth').val();
                    $.ajax(
                    { type: "POST",
                        url: "ws_Report.php",
                        data: ({
                            cmd: 'fullcamp',
                            calyear: rb,
                            rptyear: yr,
                            fymonths: fym
                        }),
                        success: handleResponse,
                        error: handleError,
                        datatype: "json"
                    });
                });
                $('#btnList').click( function() {
                    var yr = $('#selYears').val();

                    $.ajax(
                    { type: "POST",
                        url: "ws_Report.php",
                        data: ({
                            cmd: 'listcamp',
                            rptyear: yr
                        }),
                        success: handleResponse,
                        error: handleError,
                        datatype: "json"
                    });
                });
                $('#Print_Button').click(function() {
                    $("div.printArea").printArea();
                });

            });
            function handleResponse(data, statusTxt, xhrObject) {
                if (statusTxt !== "success")
                    alert('Server had a problem.  ' + xhrObject.status + ", "+ xhrObject.responseText);

                if (data) {
                    data = $.parseJSON(data);
                    if (data.error) {
                        alert('Application Error - ' + data.error);
                    }
                    else if (data.success) {
                        $('#divCampaignTable').html('');
                        re = /\$@|\(|\)|\+|\[|\_|\]|\[|\}|\{|\||\\|\!|\$/g;
                        // remove special characters like "$" and "," etc...

                        $('#divCampaignTable').append(data.success.replace(re, ""));
                        $('#reportDiv').css("display", "block");
                    }
                    else {
                        alert('Junk returned from the server! - '+data);
                    }
                }
                else {
                    alert('Nothing returned from the server');
                }
            };
            function handleError(xhrObject, stat, thrwnError) {
                alert("Server error: " + stat + ", " + thrwnError);
            };

        </script>
    </head>
    <body <?php if ($testVersion){ echo "class='testbody'";} ?> >
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
        <h2><?php echo $wInit->pageHeading; ?></h2>
            <div id="vcampaign" class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-3">
                <table>
                    <tr>
                        <th>Year</th>
                        <th>Type</th>
                    </tr>
                    <tr>
                        <td>
                            <select name="selYears" id="selYears">
                                <?php echo $selYearOptions; ?>
                            </select><input type="hidden" id="hdnselCampOptions" value="" />
                        </td>
                        <td>Fiscal Year<input type="radio" id="rb_Cal_fy" name="rb_CalSelect" value ="fy" <?php echo $rb_fyChecked; ?> />&nbsp;Calender Year<input type="radio" id="rb_Cal_cy" value="cy" name="rb_CalSelect" <?php echo $rb_cyChecked; ?> /></td>
                        <td style="text-align:right;"><input type="button" id="btnCamp" value="Run Campaign Roll-ups" /></td>
                    </tr>
                    <tr>
                        <td colspan="2">(Fiscal Year Begins <input type="text" id="fyMonth" value="<?php echo $fyMonths; ?>" size="1" readonly="readonly"/> Months Early)</td>
                        <td style="text-align:right;"><input type="button" id="btnList" value="List Campaign Detail" /></td>
                    </tr>
                </table>
            </div>
            <div id="reportDiv" style="display:none;" class="ui-widget ui-widget-content hhk-widget-content ui-corner-all mb-3" >
                <div class="mb-3"><input id="Print_Button" type="button" value="Print" title="Press to print out this listing."/></div>
                <div id="divCampaignTable" class="printArea" >
                </div>
            </div>
            <div id="submit"></div>
        </div>
    </body>
</html>
