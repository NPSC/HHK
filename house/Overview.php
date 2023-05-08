<?php

use HHK\sec\WebInit;
use HHK\sec\Session;
use HHK\CreateMarkupFromDB;
use HHK\HTMLControls\HTMLTable;


/**
 * Overview.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");

try {
    $wInit = new WebInit();
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;


// get session instance
$uS = Session::getInstance();
$sites = '';

function todArrival(\PDO $dbh) {

    $tod[] = ['Arrival Time of Day', 'Check-ins'];

    $stmt = $dbh->query("SELECT
    TIME_FORMAT(v.Arrival_Date, '%l %p') as `TOD`,
    COUNT(HOUR(v.Arrival_Date)) as `Number`
FROM
    visit v
WHERE YEAR(v.Arrival_Date) > 2020
GROUP BY HOUR(v.Arrival_Date)
ORDER BY HOUR(v.Arrival_Date)");

    while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {

        $tod[] = [$r[0], intval($r[1])];

    }

    return $tod;
}

function todDeparture(\PDO $dbh) {

    $tod[] = ['Departure Time of Day', 'Checkouts'];

    $stmt = $dbh->query("SELECT
    TIME_FORMAT(v.Actual_Departure, '%l %p') as `TOD`,
    COUNT(HOUR(v.Actual_Departure)) as `Number`
FROM
    visit v
WHERE YEAR(v.Actual_Departure) > 2020 and v.Actual_Departure is not null
GROUP BY HOUR(v.Actual_Departure)
ORDER BY HOUR(v.Actual_Departure)");

    while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {

        $tod[] = [$r[0], intval($r[1])];

    }

    return $tod;
}

$markup = '';

$todArrivalData = json_encode(todArrival($dbh));
$todDepartureData = json_encode(todDeparture($dbh));

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $wInit->pageTitle; ?></title>

        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo NAVBAR_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>

        <script src="https://www.gstatic.com/charts/loader.js"></script>

<script type="text/javascript">
    google.charts.load('current', {packages: ['corechart']});
    google.charts.setOnLoadCallback(drawTODCheckin);

    function drawTODCheckin() {

        let data = $.parseJSON($('#todData').val());
        let dataTable = google.visualization.arrayToDataTable(data);

        var view = new google.visualization.DataView(dataTable);
        view.setColumns([0, 1]);

        var chart = new google.visualization.ColumnChart(document.getElementById('roomPie'));
        let options = {
        	height:550,
        	width:1200,
        	title:"HHK Check-in, Checkout Time of Day Distribution",
        	legend: {position: 'top'},
        	vAxis: {title: 'Number of Check-ins'}
        };

        chart.draw(view, options);
	}
</script>

    </head>
    <body>
        <?php echo $wInit->generatePageMenu(); ?>

        <div id="contentDiv">
			<h2 class="hhk-flex" style="justify-content: space-between;align-items: baseline;"><?php echo $wInit->pageHeading; ?></h2>
            <div id='roomPie' style="border: 1px solid #ccc"></div>
			<div id="printArea" class="ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-visitdialog" style="font-size: .9em; padding: 5px; padding-bottom:25px; margin: 10px 0">
			<?php echo $markup; ?>
			</div>
        </div>
        <input type='hidden' id='todData' value='<?php echo $todArrivalData;?>' />

    </body>
</html>
