<?php

use HHK\House\Report\DailyOccupancyReport;
use HHK\House\Report\QuarterlyOccupancyReport;
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

$occupancyReport = new QuarterlyOccupancyReport($dbh, $_REQUEST);
$dailyOccupancyReport = new DailyOccupancyReport($dbh, $_REQUEST);

if (isset($_POST['btnHere-' . $occupancyReport->getInputSetReportName()])) {
    $activeTab = 1;
    $dataTableWrapper = "<div class='mt-3'><button class='ui-button ui-button-default ui-corner-all' id='print-" . $occupancyReport->getInputSetReportName() . "'>Print</button></div>" . $occupancyReport->generateMarkup();
}

if (isset($_POST['btnExcel-' . $occupancyReport->getInputSetReportName()])) {
    $activeTab = 1;
    $occupancyReport->downloadExcel();
}

if (filter_has_var(INPUT_POST, 'cmd')) {

    $cmd = filter_input(INPUT_POST, 'cmd', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if ($cmd = 'getRoomNights') {

        $data = array('info'=>rmNiteData($dbh, date('Y')));
        echo(json_encode($data));
    }

    exit();
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

        <script src="https://www.gstatic.com/charts/loader.js"></script>

        <script type="text/javascript">
            google.charts.load('current', {packages: ['corechart']});
			google.charts.setOnLoadCallback(drawGuestsPerNight);
            google.charts.setOnLoadCallback(drawDiagnosisTotals);

            function drawGuestsPerNight() {

                let data = <?php echo json_encode($occupancyReport->getGuestAvgPerNight()); ?>;

                let dataTable = google.visualization.arrayToDataTable(data);

                var view = new google.visualization.DataView(dataTable);

                var chart = new google.visualization.PieChart(document.getElementById('guestsPerNight'));

                let options = {
                    height:350,
                    width: 500,
                    chartArea: {'width': 400, 'height': 400},
                    legend: {
                        position: 'right',
                        alignment: 'center',
                        textStyle: {
                            fontSize: 14
                        }
                    }
                };
                chart.draw(view, options);
            }

            function drawDiagnosisTotals() {

                let data = <?php echo json_encode($occupancyReport->getDiagnosisCategoryTotals()); ?>;

                let dataTable = google.visualization.arrayToDataTable(data);

                var view = new google.visualization.DataView(dataTable);

                var chart = new google.visualization.PieChart(document.getElementById('diagnosisCategoryTotals'));
                let options = {
                    height:500,
                    width: 800,
                    chartArea: {'width': "80%", 'height': "100%"},
                    legend: {
                        position: 'right',
                        alignment: 'center',
                        textStyle: {
                            fontSize: 14
                        }
                    }
                };

                chart.draw(view, options);
            }
        </script>

        <script type="text/javascript">
            $(document).ready(function() {
                let activeTab = <?php echo $activeTab; ?>

            	$("#occupancyTabs").tabs({
            		active: activeTab,
                });

                // This is the best hook for running the two pie charts.  It only happens when you press Run Here
                if (activeTab == 1) {
                    google.charts.setOnLoadCallback(drawDiagnosisTotals);
                    google.charts.setOnLoadCallback(drawGuestsPerNight);
                }

                $("#historicalOcc #repSummary>img").remove();

                var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
                var columnDefs = $.parseJSON('<?php echo json_encode($occupancyReport->colSelector->getColumnDefs()); ?>');

                <?php echo $occupancyReport->filter->getTimePeriodScript(); ?>;

                $(document).on('click', '#print-<?php echo $occupancyReport->getInputSetReportName();?>', function(){
                	$(this).closest('.ui-tabs-panel').find("#hhk-reportWrapper").printArea({
                		extraHead: `
                			<style>
                    		#hhk-reportWrapper {
                                border: 0;
                                font-size: 0.9em;
                                background: none;
                            }

                            #hhk-reportWrapper #repSummary>.hhk-flex {
                                display: block;
                            }

                            #hhk-reportWrapper .hhk-print-row{
                            	
                            	margin-bottom: 2em;
                            }

                            #hhk-reportWrapper .hhk-pieChart {
                            	page-break-inside:avoid;
                            }

                            #hhk-reportWrapper .hhk-pieChart div {
                                margin: 0 auto;
                            }

                            #hhk-reportWrapper .ui-icon {
                            	display:none;
                            }
                            </style>`
                	});
                });
                $(document).on('click', '#print-<?php echo $dailyOccupancyReport->getInputSetReportName();?>', function(){
                	$(this).closest('.ui-tabs-panel').find("#hhk-reportWrapper").printArea({
                		extraHead: `
                			<style>
                    		#hhk-reportWrapper {
                                border: 0;
                                font-size: 0.9em;
                            }

                            #hhk-reportWrapper .ui-icon {
                            	display:none;
                            }
                            </style>`
                	});
                });
            });
         </script>

    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <div id="occupancyTabs" style="font-size:0.9em;">
            	<ul>
            		<li><a href="#dailyOcc">Daily Occupancy</a></li>
            		<li><a href="#historicalOcc">Historical Occupancy</a></li>
            	</ul>
            	<div id="dailyOcc">
            		<?php echo "<div><button class='ui-button ui-button-default ui-corner-all' id='print-" . $dailyOccupancyReport->getInputSetReportName() . "'>Print</button></div>" . $dailyOccupancyReport->generateMarkup(); ?>
            	</div>
            	<div id="historicalOcc">
            		<?php echo $occupancyReport->generateFilterMarkup() . $dataTableWrapper; ?>
            	</div>
            </div>
        </div>
    </body>
</html>
