<?php


use HHK\House\Report\DailyOccupancyReport;
use HHK\House\Report\QuarterlyOccupancyReport;
use HHK\sec\{Session, WebInit};
use HHK\sec\Labels;

/**
 * ReservReport.php
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

function todData(\PDO $dbh) {

    $tod[] = ['Arrival Time of Day', 'Check-ins', 'Checkouts'];
    $toa = [];

    // Get arrivals
    $stmt = $dbh->query("SELECT
            TIME_FORMAT(v.Arrival_Date, '%l %p') as `TOD`,
            COUNT(HOUR(v.Arrival_Date)) as `Number`
        FROM
            visit v
        WHERE YEAR(v.Arrival_Date) > 2020
        GROUP BY HOUR(v.Arrival_Date)
        ORDER BY HOUR(v.Arrival_Date)");

    while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {

        $toa[$r[0]] = intval($r[1]);

    }

    // Get departures
    $stmt = $dbh->query("SELECT
            TIME_FORMAT(v.Actual_Departure, '%l %p') as `TOD`,
            COUNT(HOUR(v.Actual_Departure)) as `Number`
        FROM
            visit v
        WHERE YEAR(v.Actual_Departure) > 2020 and v.Actual_Departure is not null
        GROUP BY HOUR(v.Actual_Departure)
        ORDER BY HOUR(v.Actual_Departure)");

    while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {


        $tod[] = [$r[0], (isset($toa[$r[0]]) ? $toa[$r[0]] : 0), intval($r[1])];

    }

    return $tod;
}


$occupancyReport = new QuarterlyOccupancyReport($dbh, $_REQUEST);
$dailyOccupancyReport = new DailyOccupancyReport($dbh, $_REQUEST);

if (isset($_POST['btnHere-' . $occupancyReport->getInputSetReportName()])) {
    $activeTab = 1;
    $dataTableWrapper = "<div class='mt-3'><button class='ui-button ui-button-default ui-corner-all' id='print-" . $occupancyReport->getInputSetReportName() . "'>Print</button></div>" . $occupancyReport->generateMarkup();
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
            google.charts.load('current', {packages: ['corechart', 'bar']});

            function drawGuestsPerNight() {

                let data = <?php echo json_encode($occupancyReport->getGuestAvgPerNight()); ?>;

                let dataTable = google.visualization.arrayToDataTable(data);

                var view = new google.visualization.DataView(dataTable);

                var chart = new google.visualization.PieChart(document.getElementById('guestsPerNight'));

                let options = {
                    height:350,
                    width: 500,
                    chartArea: {'width': '90%', 'height': '100%'},
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
                    height:350,
                    width: 500,
                    chartArea: {'width': '90%', 'height': '100%'},
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

            function drawTODCheckin() {

                let data = <?php echo json_encode(todData($dbh)); ?>;
                let dataTable = google.visualization.arrayToDataTable(data);

                let options = {
                    height:600,
                    width: 950,
                    chart: {title:"HHK Check-in, Checkout Time of Day Distribution"}
                };

                var chart = new google.charts.Bar(document.getElementById('todChart'));
                chart.draw(dataTable, google.charts.Bar.convertOptions(options));
            }
        </script>

        <script type="text/javascript">
            $(document).ready(function() {
                let activeTab = <?php echo $activeTab; ?>

            	$("#occupancyTabs").tabs({
            		active: activeTab,
                    beforeActivate: function(event, ui) {

                        if (ui.newTab.prop('id') == 'todTab') {
                            google.charts.setOnLoadCallback(drawTODCheckin);
                        }
                    }
                });

                if (activeTab == 1) {
                    google.charts.setOnLoadCallback(drawDiagnosisTotals);
                    google.charts.setOnLoadCallback(drawGuestsPerNight);
                }

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
                            	flex-wrap: nowrap;
                            	margin-bottom: 2em;
                            }

                            #hhk-reportWrapper .hhk-pieChart {
                            	page-break-inside:avoid;
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
                    <li id='todTab'><a href="#todDoc">Check-in/Out Time of Day</a></li>
            	</ul>

            	<div id="dailyOcc">
            		<?php echo "<div><button class='ui-button ui-button-default ui-corner-all' id='print-" . $dailyOccupancyReport->getInputSetReportName() . "'>Print</button></div>" . $dailyOccupancyReport->generateMarkup(); ?>
            	</div>
            	<div id="historicalOcc">
            		<?php echo $occupancyReport->generateFilterMarkup(false) . $dataTableWrapper; ?>
            	</div>
            	<div id="todDoc">
                    <div id='todChart'></div>
            	</div>
            </div>


        </div>
    </body>
</html>
