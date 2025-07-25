<?php

use HHK\AlertControl\AlertMessage;
use HHK\Config_Lite\Config_Lite;
use HHK\sec\{Session, WebInit};
use HHK\SysConst\GLTableNames;
use HHK\House\Report\RoomReport;
use HHK\HTMLControls\HTMLSelector;
use HHK\sec\Labels;
use HHK\House\Report\ReportFilter;

/**
 * RoomUtilization.php
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

$groupingSelection = 'Category';
$mkTable = '';

$filter = new ReportFilter();
$filter->createTimePeriod(date('Y'), '19', $uS->fy_diff_Months);
$filter->createHospitals();

$status = '';
$txtStart = '';
$txtEnd = '';
$output = '';

// Room Groupings
$roomGroups = readGenLookupsPDO($dbh, 'Room_Group');


// Callback
if (isset($_POST['btnByGuest']) || isset($_POST['btnByRoom'])) {

    $filter->loadSelectedTimePeriod();
    $filter->loadSelectedHospitals();

    // Room Grouping
    if (isset($_POST['selGroup'])) {
        $groupingSelection = filter_var($_POST['selGroup'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }


    // Hospitals
    $whHosp = '';
    foreach ($filter->getSelectedHosptials() as $a) {
        if ($a != '') {
            if ($whHosp == '') {
                $whHosp .= $a;
            } else {
                $whHosp .= ",". $a;
            }
        }
    }

    $whAssoc = '';
    foreach ($filter->getSelectedAssocs() as $a) {
        if ($a != '') {
            if ($whAssoc == '') {
                $whAssoc .= $a;
            } else {
                $whAssoc .= ",". $a;
            }
        }
    }

    if ($whHosp != '') {
        $whHosp = " and hs.idHospital in (".$whHosp.") ";
    }

    if ($whAssoc != '') {
        $whHosp .= " and hs.idAssociation in (".$whAssoc.") ";
    }

    $mkTable = 1;

    if (isset($_POST['btnByGuest'])) {
        $output = RoomReport::roomNOR($dbh, $filter->getReportStart(), $filter->getQueryEnd(), $whHosp, $roomGroups[$groupingSelection]);
    } else {
        $roomReport = new RoomReport();
        $output = $roomReport->rescUtilization($dbh, $filter->getReportStart(), $filter->getQueryEnd());
    }
}


// Setups for the page.
$timePeriodMarkup = $filter->timePeriodMarkup()->generateMarkup(array('style'=>'float: left;'));
$hospitalMarkup = $filter->hospitalMarkup()->generateMarkup(array('style'=>'float: left;margin-left:5px;'));

$roomGrouping = HTMLSelector::generateMarkup(
        HTMLSelector::doOptionsMkup(removeOptionGroups($roomGroups), $groupingSelection, FALSE), array('name' => 'selGroup', 'size'=>'4'));

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <?php echo CSSVARS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>

        <script type="text/javascript">
$(document).ready(function() {
    "use strict";
    var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
    var makeTable = '<?php echo $mkTable; ?>';
    $('#btnHere, #btnExcel, #cbColClearAll, #cbColSelAll').button();

    <?php echo $filter->getTimePeriodScript(); ?>

    $('#btnByGuest, #btnByRoom').button();

    if (makeTable === '1') {

        $('div#printArea').css('display', 'block');

        $('#printButton').button().click(function() {
            $("div#printArea").printArea();
        });
    }

});
        </script>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog" style="display:inline-block; font-size:0.9em;">
                <form action="RoomUtilization.php" method="post"  id="form1" name="form1" >
                    <div class="ui-helper-clearfix">
                    <?php echo $timePeriodMarkup; ?>
                    <?php
                        if (count($filter->getHospitals()) > 1) {
                            echo $hospitalMarkup;
                        }
                    ?>
                    <table style="float: left;margin-left:5px;">
                        <tr>
                            <th>Room Grouping</th>
                        </tr>
                        <tr>
                            <td><?php echo $roomGrouping; ?></td>
                        </tr>
                    </table>
                    </div>
                    <div style="text-align:center; margin-top: 10px;">
                    	<input type="submit" name="btnByRoom" value="By Room" id="btnByRoom" style="margin-right: 1em;"/>
                    	<input type="submit" name="btnByGuest" value="By <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>" id="btnByGuest" />
                    </div>
                </form>
            </div>
            <div id="printArea" class="ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-visitdialog hhk-overflow-x" style="display:none; font-size: .9em; padding: 5px; padding-bottom:25px; margin: 10px 0;">
                <div class="mb-2 mt-1"><input id="printButton" value="Print" type="button"/></div>
                <?php echo $output; ?>
            </div>
        </div>  <!-- div id="contentDiv"-->
    </body>
</html>
