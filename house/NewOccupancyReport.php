<?php

use HHK\ExcelHelper;
use HHK\House\Report\DailyOccupancyReport;
use HHK\House\Report\NewOccupancyReport;
use HHK\House\Report\RoomReport;
use HHK\sec\{Session, WebInit};
use HHK\sec\Labels;

/**
 * ReservReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");

try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;

// get session instance
$uS = Session::getInstance();
$labels = Labels::getLabels();
$menuMarkup = $wInit->generatePageMenu();

$dataTableWrapper = '';
$activeTab = 0;
$todayDT = new \DateTimeImmutable();


/**
 * Summary of getSum
 * @param array $totals
 * @return array
 */
function getSum($totals) {

    $sum = [];

    foreach ($totals as $idRm => $rdateArray) {

        $daysOccupied = 0;

        foreach($rdateArray as $day => $numbers) {

            $daysOccupied += $numbers;
        }

        $sum[$idRm] = $daysOccupied;
    }

    return $sum;
}

$occupancyReport = new NewOccupancyReport($dbh, $_REQUEST);
$dailyOccupancyReport = new DailyOccupancyReport($dbh, $_REQUEST);

if (isset($_POST['btnHere-' . $occupancyReport->getInputSetReportName()])) {
    $activeTab = 1;
    $dataTableWrapper = "<div class='mt-3'><button class='ui-button ui-button-default ui-corner-all' id='print-" . $occupancyReport->getInputSetReportName() . "'>Print</button></div>" . $occupancyReport->generateMarkup();
}

if (isset($_POST['btnExcel-' . $occupancyReport->getInputSetReportName()])) {
    ini_set('memory_limit', "280M");
    //$occupancyReport->downloadExcel("OccupancyReport");
    $occupancyReport->downloadExcel("OccupancyReport", ExcelHelper::ACTION_EMAIL, "wireland@nonprofitsoftwarecorp.org");
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
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
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo REPORTFIELDSETS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo REPORTVIEWER_JS ?>"></script>

        <script type="text/javascript">
            $(document).ready(function() {

                $("#repSummary>img").remove();

                <?php echo $occupancyReport->generateReportScript(); ?>
                
            });
         </script>

    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <div id="<?php echo $occupancyReport->getInputSetReportName(); ?>Wrapper">
                <?php echo $occupancyReport->generateFilterMarkup(); ?>
            </div>
        </div>
    </body>
</html>
