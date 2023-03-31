<?php

use HHK\AlertControl\AlertMessage;
use HHK\Config_Lite\Config_Lite;
use HHK\sec\{Session, WebInit};
use HHK\HTMLControls\HTMLContainer;
use HHK\House\Report\ReportFilter;
use HHK\ColumnSelectors;
use HHK\HTMLControls\HTMLTable;
use HHK\SysConst\RoomRateCategories;
use HHK\SysConst\GLTableNames;
use HHK\HTMLControls\HTMLSelector;
use HHK\ExcelHelper;
use HHK\sec\Labels;
use HHK\House\Report\ReportFieldSet;
use HHK\House\Report\ReservationReport;
use HHK\House\Report\QuarterlyOccupancyReport;
use HHK\House\Report\DailyOccupancyReport;

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
            google.charts.load('current', {packages: ['corechart']});
			google.charts.setOnLoadCallback(drawGuestsPerNight);
            google.charts.setOnLoadCallback(drawDiagnosisTotals);

            function drawGuestsPerNight() {

                let data = <?php echo json_encode($occupancyReport->getGuestAvgPerNight()); ?>;

                let dataTable = google.visualization.arrayToDataTable(data);

                var view = new google.visualization.DataView(dataTable);

                var chart = new google.visualization.PieChart(document.getElementById('guestsPerNight'));
                let options = {
                	height:300,
                	width:300,
                	'chartArea': {'width': '100%', 'height': '60%'},
                	legend: {position: 'top', alignment: 'center'},
                };

                chart.draw(view, options);
        	}

        	function drawDiagnosisTotals() {

                let data = <?php echo json_encode($occupancyReport->getDiagnosisCategoryTotals()); ?>;

                let dataTable = google.visualization.arrayToDataTable(data);

                var view = new google.visualization.DataView(dataTable);

                var chart = new google.visualization.PieChart(document.getElementById('diagnosisCategoryTotals'));
                let options = {
                	height:300,
                	width: 400,
                	'chartArea': {'width': '100%', 'height': '80%'},
                	legend: {position: 'right', alignment: 'center'},
                };

                chart.draw(view, options);
        	}

        </script>

        <script type="text/javascript">
            $(document).ready(function() {
            	$("#occupancyTabs").tabs({
            		active: <?php echo $activeTab; ?>,
            		activate: function(event, ui){

            		}
            	});

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
            	</ul>

            	<div id="dailyOcc">
            		<?php echo "<div><button class='ui-button ui-button-default ui-corner-all' id='print-" . $dailyOccupancyReport->getInputSetReportName() . "'>Print</button></div>" . $dailyOccupancyReport->generateMarkup(); ?>
            	</div>
            	<div id="historicalOcc">
            		<?php echo $occupancyReport->generateFilterMarkup(false) . $dataTableWrapper; ?>
            	</div>
            </div>


        </div>
    </body>
</html>