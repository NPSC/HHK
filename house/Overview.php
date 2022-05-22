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

// try {
//     $wInit = new WebInit();
// } catch (Exception $exw) {
//     die("arrg!  " . $exw->getMessage());
// }

// get session instance
$uS = Session::getInstance();
$sites = '';

function houseTable() {

    $dsn = "mysql:host=10.138.0.21;dbname=overview;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES   => TRUE,
    ];

    $obh = new PDO($dsn, 'overview', '', $options);
    $obh->exec("SET SESSION wait_timeout = 3600;");

    $sites = new HTMLTable();

    $stmt = $obh->query("Select * from site order by Start_Date;");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {

        $schema = $r['Db_Schema'];

        $stmt = $obh->query("select count(idRoom) from `$schema`.`room`;");
        $rws = $stmt->fetchAll(PDO::FETCH_NUM);
        $rooms = $rws[0][0];

        $tr = HTMLTable::makeTd($r['Title'])
            .HTMLTable::makeTd($r['Start_Date'] == '' ? '' : date('M j, Y', strtotime($r['Start_Date'])))
            .HTMLTable::makeTd(number_format($r['Rate'], 2), array('style'=>'text-align:right;'))
            .HTMLTable::makeTd(number_format($r['Rate'] * $r['Contracted_Rooms'], 2), array('style'=>'text-align:right;'))
            .HTMLTable::makeTd($r['Contracted_Rooms'], array('style'=>'text-align:center;'))
            .HTMLTable::makeTd($rooms, array('style'=>'text-align:center;'));

        $stmt = $obh->query("CALL `$schema`.sum_visit_days(2021);");
        $gts = $stmt->fetchAll(\PDO::FETCH_NUM);

        $cnt = $gts[0][0];


        $tr .=  HTMLTable::makeTd(number_format($cnt, 0, ".", ","), array('style'=>'text-align:center;'));

        $sites->addBodyTr($tr);
    }

    $sites->addHeaderTr(
        HTMLTable::makeTh('House')
        .HTMLTable::makeTh('Start')
        .HTMLTable::makeTh('Rate')
        .HTMLTable::makeTh('Monthly Charge')
        .HTMLTable::makeTh('Contracted Rooms')
        .HTMLTable::makeTh('Current Rooms')
        .HTMLTable::makeTh('2021 Nights')
        );

    return $sites->generateMarkup();

}


function DocTable() {

    $dsn = "mysql:host=10.138.0.21;dbname=overview;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES   => TRUE,
    ];

    $obh = new PDO($dsn, 'overview', '', $options);
    $obh->exec("SET SESSION wait_timeout = 3600;");

    $sites = new HTMLTable();

    $stmt = $obh->query("Select * from site order by Start_Date;");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hhkMBytes = 0;

    // Each house
    foreach ($rows as $r) {

        $schema = $r['Db_Schema'];
        $schemaBytes = 0;

        $stmt = $obh->query("SELECT
  TABLE_NAME AS `Table`,
  TABLE_ROWS,
  DATA_LENGTH,
  INDEX_LENGTH,
  ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024) AS `Size`
FROM
  information_schema.TABLES
WHERE
  TABLE_TYPE != 'VIEW' AND
  TABLE_NAME = 'document' AND
--  (DATA_LENGTH + INDEX_LENGTH) > 5000000 AND
  TABLE_SCHEMA = '$schema'
ORDER BY
  (DATA_LENGTH + INDEX_LENGTH) DESC;");

        // Each house table
        while ($h = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $tr = HTMLTable::makeTd($r['Title'])
            .HTMLTable::makeTd($h['Table'])
            .HTMLTable::makeTd(number_format($h['TABLE_ROWS'], 0), array('style'=>'text-align:right;'))
            .HTMLTable::makeTd(number_format($h['DATA_LENGTH'], 0), array('style'=>'text-align:right;'))
            .HTMLTable::makeTd(number_format($h['INDEX_LENGTH'], 0), array('style'=>'text-align:right;'))
            .HTMLTable::makeTd(number_format($h['Size'], 0), array('style'=>'text-align:right;'));

            $sites->addBodyTr($tr);

            $schemaBytes += ($h['DATA_LENGTH'] + $h['INDEX_LENGTH']);
        }

        $sites->addBodyTr(HTMLTable::makeTd($r['Title'] . ' Total:', array('colspan'=>'5', 'style'=>'text-align:right;')) . HTMLTable::makeTd(number_format($schemaBytes, 0), array('style'=>'text-align:right;')) );

        $hhkMBytes += $schemaBytes;
    }

    $sites->addBodyTr(HTMLTable::makeTd('Overall Total:', array('colspan'=>'5', 'style'=>'text-align:right;')) . HTMLTable::makeTd(number_format($hhkMBytes, 0), array('style'=>'text-align:right;')) );


    $sites->addHeaderTr(
        HTMLTable::makeTh('House')
        .HTMLTable::makeTh('Table')
        .HTMLTable::makeTh('Rows')
        .HTMLTable::makeTh('Data')
        .HTMLTable::makeTh('Index')
        .HTMLTable::makeTh('Combined (MB)')
        );

    return $sites->generateMarkup();

}

$markup = DocTable();

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>HHK</title>

        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
                <?php echo FAVICON; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

        <script src="https://www.gstatic.com/charts/loader.js"></script>

	<script type="text/javascript">
 //   google.charts.load('current', {packages: ['corechart']});

 //   google.charts.setOnLoadCallback(drawIncomeChart);
 //   google.charts.setOnLoadCallback(drawGuestsChart);

     function drawRateChart() {

		$.post('ws_chart.php', {cmd: 'rmrate'}, function (rawData) {
            try {
               var data = $.parseJSON(rawData);
            } catch (err) {
                alert("Parser error - " + err.message);
                return false;
            }
            if (data.error) {
                alert(data.error);
                return false;
            }
            if (data.warning && data.warning !== '') {
                alert(data.warning);
                return false;
            }

			var dataTable = google.visualization.arrayToDataTable(data);
       		var chart = new google.visualization.PieChart(document.getElementById('myPieChart'));
			var options = {
				height:550,
				width:700,
				title:"HHK Client Rates",
				pieSliceText: 'label'
			};

       		chart.draw(dataTable, options);
		});

     }

      function drawGuestsChart() {

		$.post('ws_chart.php', {cmd: 'guestsByHouse'}, function (rawData) {
            try {
                var data = $.parseJSON(rawData);
            } catch (err) {
                alert("Parser error - " + err.message);
                return false;
            }
            if (data.error) {
                flagAlertMessage(data.error, 'error');
                return false;
            }
            if (data.warning && data.warning !== '') {
                flagAlertMessage(data.warning, 'alert');
                return false;
            }

			var dataTable = google.visualization.arrayToDataTable(data);
       		var chart = new google.visualization.ColumnChart(document.getElementById('roomPie'));
			var options = {
				height:550,
				width:700,
				title:"HHK Visit Nights",
				legend: {position: 'none'}
			};

       		chart.draw(dataTable, options);
		});

     }

      function drawIncomeChart() {

		$.post('ws_chart.php', {cmd: 'incomeByHouse'}, function (rawData) {
            try {
                var data = $.parseJSON(rawData);
            } catch (err) {
                alert("Parser error - " + err.message);
                return false;
            }
            if (data.error) {
                flagAlertMessage(data.error, 'error');
                return false;
            }
            if (data.warning && data.warning !== '') {
                flagAlertMessage(data.warning, 'alert');
                return false;
            }

			var dataTable = google.visualization.arrayToDataTable(data);
       		var chart = new google.visualization.ColumnChart(document.getElementById('myPieChart'));
			var options = {
				height:550,
				width:700,
				title:"HHK Client Income",
				legend: {position: 'none'}
			};

       		chart.draw(dataTable, options);
		});

     }

    </script>

    </head>
    <body>

        <div id="contentDiv">
            <h2>Overview</h2>

            <div id='myPieChart' style="border: 1px solid #ccc"></div>
            <div id='roomPie' style="border: 1px solid #ccc"></div>
			<div id="printArea" class="ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-visitdialog" style="font-size: .9em; padding: 5px; padding-bottom:25px; margin: 10px 0">
			<?php echo $markup; ?>
			</div>
        </div>

    </body>
</html>
