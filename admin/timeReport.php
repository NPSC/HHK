<?php

use HHK\sec\{Session, WebInit};
use HHK\HTMLControls\selCtrl;

/**
 * timeReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require("AdminIncludes.php");

$wInit = new webInit();

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();

// Load the session with member - based lookups
$wInit->sessionLoadGenLkUps();
$wInit->sessionLoadVolLkUps();

// Don't track time for Vol_Type
$showMemberTypes = false;
$fyMonthsAdjust = $uS->fy_diff_Months;

$catmarkup = "";
$makeTable = 0;

// Selector Controls for Category section
$gSel = readGenLookupsPDO($dbh, "Vol_Category");
$catSelCtrls = array();

foreach ($gSel as $selData) {
    if ($selData[0] != 'Vol_Type' || $showMemberTypes === TRUE) {
        $catSelCtrls[$selData[0]] = new selCtrl($dbh, $selData[0], false, "sel" . $selData[0], true, $selData[1]);
    }
}

$typeCtrl = new selCtrl($dbh, "HourReportType", false, "selReportType", FALSE, "", "Code");

$fyOptions = "";
$now = getDate();
$fy = 1;
$returnedYear = "";

if (filter_has_var(INPUT_POST, "selIntFy")) {
    $returnedYear = intval(filter_var($_POST["selIntFy"], FILTER_SANITIZE_NUMBER_INT));
}

for ($y = $now["year"]; $y > ($now["year"] - 4); $y--) {
    $sd = "";
    if ($returnedYear == ($y + $fy)) {
        $sd = "selected='selected'";
    } else if ($returnedYear == "" && intval($now["year"]) == ($y + $fy)) {
        $sd = "selected='selected'";
    }
    $fyOptions .= "<option value='" . ($y + $fy) . "' $sd>" . ($y + $fy) . "</option>";
}



// Postback logic
if (filter_has_var(INPUT_POST, "btnCat") || filter_has_var(INPUT_POST, "btnCatDL")) {

    require("functions" . DS . "TimeReportMgr.php");

    $typeCtrl->setReturnValues($_POST[$typeCtrl->get_htmlNameBase()]);

    $catmarkup = processTime($dbh, $catSelCtrls, $typeCtrl, $fyMonthsAdjust);
    $makeTable = 1;
}

$catSelMarkup = "";
$catSelTitleMarkup = "";


// Create category/code selector controls.
foreach ($catSelCtrls as $sel) {
    $catSelMarkup .= "<td>" . $sel->createMarkup(5, true) . "</td>";
    $catSelTitleMarkup .= "<th>" . $sel->get_title() . "</th>";
}

$reportTypeSelMarkup = $typeCtrl->createMarkup(3);
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>

        <script type="text/javascript">
            // Init j-query and the page blocker.
//            var listTable;
//            var makeTable = <?php echo $makeTable; ?>;
            $(document).ready(function() {

            	$("input[type=submit], input[type=button]").button();

                var listTable;
                var makeTable = <?php echo $makeTable; ?>;
                var now = new Date();
                $('#selIntMonth option:eq(' + now.getMonth() + ')').prop("selected", true);
                $('#m').css('display', 'table-cell');
                $('#fy').css('display', 'none');
                $('#cy').css('display', 'none');
                $('#selHoursInterval').change(function() {
                    $('#m').css('display', 'none');
                    $('#fy').css('display', 'none');

                    $('#' + $(this).val()).css('display', 'table-cell');
                });

                $('.hhk-showHours').css('display', 'table-cell');

                if (listTable) {
                    listTable.fnDestroy();
                }
                if (makeTable === 1) {
                    $('div#printArea').css('display', 'block');
                    try {
                        listTable = $('#tblCategory').dataTable({
                            "iDisplayLength": 50,
                            "aLengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                            "dom": '<"top"lf><"hhk-overflow-x"rt><"bottom"ip>'
                        });
                    }
                    catch (err) {

                    }
                }
                $('#Print_Button').click(function() {
                    $("div#printArea").printArea();
                });
            });
        </script>
    </head>
    <body <?php if ($testVersion){ echo "class='testbody'";} ?> >
<?php echo $menuMarkup; ?>
        <div id="contentDiv">
        	<h2>Time Reports</h2>
            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-3">
                <form id="fcat" action="timeReport.php" method="post">
                    <table>
                        <tr>
<?php echo $catSelTitleMarkup; ?>
                        </tr><tr>
                            <?php echo $catSelMarkup; ?>
                        </tr>
                    </table>
                    <table class="mb-3">
                        <tr>
                            <th>Report Type</th>
                            <th>Time Period</th>
                        </tr><tr>
                            <td rowspan="3">
<?php echo $reportTypeSelMarkup; ?>
                            </td>
                            <td class="hhk-showHours">
                                <select id="selHoursInterval" name="selHoursInterval"><option value="m">Month</option><option value="fy">Fiscal Year</option><option value="cy">Calendar Year</option></select>
                            </td>
                        </tr>
                        <tr><td class="hhk-showHours">
                                <span id="m"><select id="selIntMonth" name="selIntMonth"><option value="1">Jan</option><option value="2">Feb</option><option value="3">Mar</option><option value="4">Apr</option><option value="5">May</option><option value="6">June</option><option value="7">July</option><option value="8">Aug</option><option value="9">Sep</option><option value="10">Oct</option><option value="11">Nov</option><option value="12">Dec</option></select></span>
                                <span id="fy"><select id="selIntFy" name="selIntFy"><?php echo $fyOptions; ?></select></span>
                            </td></tr>
                        <tr><td class="hhk-showHours">
                                <select id="selIntDetail" name="selIntDetail"><option value="ru">Roll up by volunteer</option><option value="in">Show each instance</option></select>
                            </td></tr>
                    </table>
                    <h4 style="text-align:center;">Regular Time Reports:</h4>
                    <div class="hhk-flex mt-1" style="justify-content: space-evenly;">
                    	<input type="submit" name="btnCat" value="Run Time Report" />
                        <input type="submit" name="btnCatDL" value="Download to Excel File" />
                    </div>
                </form>
            </div>
            <div id="printArea" class="ui-widget ui-widget-content hhk-widget-content ui-corner-all" style="display:none;">
<?php echo $catmarkup; ?>
            </div>

        </div>
    </body>
</html>
