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
 * Summary of todData
 * @param PDO $dbh
 * @return array
 */
function todData(\PDO $dbh) {

    $hours = ['12 AM', '1 AM', '2 AM', '3 AM', '4 AM', '5 AM', '6 AM', '7 AM', '8 AM', '9 AM', '10 AM', '11 AM',
                '12 PM', '1 PM', '2 PM', '3 PM', '4 PM', '5 PM', '6 PM', '7 PM', '8 PM', '9 PM', '10 PM', '11 PM'];
    $result[] = ['Time of Day', 'Check-ins', 'Checkouts'];
    $tod = [];
    $toa = [];
    $sinceDT = new \DateTime();
    $sinceDT->sub(new \DateInterval('P1Y'));
    $since = $sinceDT->format('Y-m-d');

    // Get arrivals
    $stmt = $dbh->query("SELECT
            TIME_FORMAT(v.Arrival_Date, '%l %p') as `TOD`,
            COUNT(HOUR(v.Arrival_Date)) as `Number`
        FROM
            visit v
        WHERE DATE(v.Arrival_Date) > DATE('$since') and v.Actual_Departure is not null
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
        WHERE DATE(v.Actual_Departure) > DATE('$since') and v.Actual_Departure is not null
        GROUP BY HOUR(v.Actual_Departure)
        ORDER BY HOUR(v.Actual_Departure)");

    while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {
        $tod[$r[0]] = intval($r[1]);
    }

    // Collect all toa's
    foreach ($hours as $h) {

        $result[] = [
            $h,
            (isset($toa[$h]) ? $toa[$h] : 0),
            (isset($tod[$h]) ? $tod[$h] : 0)
        ];
    }

    return $result;
}

/**
 * Summary of rmNiteData
 * @param PDO $dbh
 * @param string $year
 * @return array
 */
function rmNiteData(\PDO $dbh, $year) {

    $y = intval($year, 10);
    if ($y < 1990) {
        return [];
    }

    $lastY = $y - 1;

    $startDT = new \DateTimeImmutable('01-01-'.$y);
    $interval = new DateInterval("P1M");
    $period = new DatePeriod($startDT, $interval, 11);

    $y1[] = [];

    $roomReport = new RoomReport();

    foreach ($period as $periodDT) {

        $roomReport->collectUtilizationData($dbh, $periodDT->format('Y-m-01'), $periodDT->add($interval)->format('Y-m-d'));

        $sum = getSum($roomReport->getTotals());

        $y1[$periodDT->format('M')] = (isset($sum['nits']) ? $sum['nits'] : 0);
    }

    // Last year
    $startDT = new \DateTimeImmutable('01-01-'.$lastY);
    $interval = new DateInterval("P1M");
    $period = new DatePeriod($startDT, $interval, 11);

    $data[] = ['Month', "$year", "$lastY"];

    $roomReport = new RoomReport();

    foreach ($period as $periodDT) {

        $roomReport->collectUtilizationData($dbh, $periodDT->format('Y-m-01'), $periodDT->add($interval)->format('Y-m-d'));
        $sum = getSum($roomReport->getTotals());
        $data[] = [$periodDT->format('M'), (isset($y1[$periodDT->format('M')]) ? $y1[$periodDT->format('M')] : 0), (isset($sum['nits']) ? $sum['nits'] : 0)];
    }

    return $data;
}

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
                    height:500,
                    width: 1100,
                    chart: {title:"HHK Check-in, Checkout Time-of-Day Distribution",
                            subtitle: 'Over the last 12 months'}
                };

                var chart = new google.charts.Bar(document.getElementById('todChart'));
                chart.draw(dataTable, google.charts.Bar.convertOptions(options));
            }

            function drawRoomMonth() {

                //let data = <?php echo json_encode(rmNiteData($dbh, $todayDT->format('Y'))); ?>;

                $.post('OccupancyReport.php', {cmd: 'getRoomNights'}, function(data) {
                    try {
                        data = $.parseJSON(data);
                    } catch (err) {
                        alert("Parser error - " + err.message);
                        return false;
                    }

                    if (data.error) {
                        if (data.gotopage) {
                            window.location.assign(data.gotopage);
                        }
                        flagAlertMessage(data.error, 'error');
                        return false;
                    }

                    let dataTable = google.visualization.arrayToDataTable(data.info);

                    let options = {
                        height:500,
                        width: 990,
                        chart: {title:"Room-Nights by month"}
                    };

                    var chart = new google.charts.Bar(document.getElementById('rmdChart'));
                    chart.draw(dataTable, google.charts.Bar.convertOptions(options));

                });
            }
        </script>

        <script type="text/javascript">
            $(document).ready(function() {
                let activeTab = <?php echo $activeTab; ?>

            	$("#occupancyTabs").tabs({
            		active: activeTab,
                    beforeActivate: function(event, ui) {

                        // Time of day Distribution
                        if (ui.newTab.prop('id') == 'todTab') {
                            google.charts.setOnLoadCallback(drawTODCheckin);
                        }

                        // room-month distribution
                        if (ui.newTab.prop('id') == 'rmdTab') {
                            google.charts.setOnLoadCallback(drawRoomMonth);
                        }

                    }
                });

                // This is the best hook for running the two pie charts.  It only happens when you press Run Here
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
                    <li id='rmdTab'><a href="#rmDoc">Occupancy Distribution</a></li>
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
            	<div id="rmDoc">
                    <div id='rmdChart'></div>
            	</div>
            </div>
        </div>
    </body>
</html>
