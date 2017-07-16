<?php
/**
 * RoomView.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");
require (HOUSE . 'RoomReport.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'ResourceView.php');

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

// Load the session with member - based lookups
$wInit->sessionLoadGenLkUps();
$wInit->sessionLoadGuestLkUps();
$config = new Config_Lite(ciCFG_FILE);

$resultMessage = '';
$hospitalSelections = array('');
$assocSelections = array('');
$calSelection = '19';

$year = date('Y');
$months = array(date('n'));     // logically overloaded.
$month = 1;
$txtStart = '';
$txtEnd = '';
$status = '';

$start = '';
$end = '';
$errorMessage = '';
$nights = '';
$oos = '';
$axisBottom = '';
$title = '';
$roomCount = '';

$monthArray = array(
    1 => array(1, 'January'), 2 => array(2, 'February'),
    3 => array(3, 'March'), 4 => array(4, 'April'), 5 => array(5, 'May'), 6 => array(6, 'June'),
    7 => array(7, 'July'), 8 => array(8, 'August'), 9 => array(9, 'September'), 10 => array(10, 'October'), 11 => array(11, 'November'), 12 => array(12, 'December'));

if ($uS->fy_diff_Months == 0) {
    $calOpts = array(18 => array(18, 'Dates'), 19 => array(19, 'Month'), 21 => array(21, 'Cal. Year'), 22 => array(22, 'Year to Date'));
} else {
    $calOpts = array(18 => array(18, 'Dates'), 19 => array(19, 'Month'), 20 => array(20, 'Fiscal Year'), 21 => array(21, 'Calendar Year'), 22 => array(22, 'Year to Date'));
}


// Hospital and association lists
$hospList = array();
if (isset($uS->guestLookups[GL_TableNames::Hospital])) {
    $hospList = $uS->guestLookups[GL_TableNames::Hospital];
}

$hList[] = array(0=>'', 1=>'(All)');
$aList[] = array(0=>'', 1=>'(All)');
foreach ($hospList as $h) {
    if ($h[2] == 'h') {
        $hList[] = array(0=>$h[0], 1=>$h[1]);
    } else if ($h[2] == 'a') {
        $aList[] = array(0=>$h[0], 1=>$h[1]);
    }
}


if (isset($_POST['btnByGuest']) || isset($_POST['btnByRoom'])) {
    // gather input
    if (isset($_POST['selCalendar'])) {
        $calSelection = intval(filter_var($_POST['selCalendar'], FILTER_SANITIZE_NUMBER_INT), 10);
    }

    if (isset($_POST['selIntMonth'])) {
        $months = filter_var_array($_POST['selIntMonth'], FILTER_SANITIZE_NUMBER_INT);
    }

    if (isset($_POST['selIntYear'])) {
        $year = intval(filter_var($_POST['selIntYear'], FILTER_SANITIZE_NUMBER_INT), 10);
    }

    if (isset($_POST['stDate'])) {
        $txtStart = filter_var($_POST['stDate'], FILTER_SANITIZE_STRING);
    }

    if (isset($_POST['enDate'])) {
        $txtEnd = filter_var($_POST['enDate'], FILTER_SANITIZE_STRING);
    }

    if (isset($_POST['selAssoc'])) {
        $reqs = $_POST['selAssoc'];
        if (is_array($reqs)) {
            $assocSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
        }
    }

    if (isset($_POST['selHospital'])) {
        $reqs = $_POST['selHospital'];
        if (is_array($reqs)) {
            $hospitalSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
        }
    }

    if ($calSelection == 20) {
        // fiscal year
        $adjustPeriod = new DateInterval('P' . $uS->fy_diff_Months . 'M');
        $startDT = new DateTime($year . '-01-01');
        $startDT->sub($adjustPeriod);
        $start = $startDT->format('Y-m-d');

        $endDT = new DateTime(($year + 1) . '-01-01');
        $end = $endDT->sub($adjustPeriod)->format('Y-m-d');

    } else if ($calSelection == 21) {
        // Calendar year
        $startDT = new DateTime($year . '-01-01');
        $start = $startDT->format('Y-m-d');

        $end = ($year + 1) . '-01-01';

    } else if ($calSelection == 18) {
        // Dates
        if ($txtStart != '') {
            $startDT = new DateTime($txtStart);
            $start = $startDT->format('Y-m-d');
        }

        if ($txtEnd != '') {
            $endDT = new DateTime($txtEnd);
            $end = $endDT->format('Y-m-d');
        }

    } else if ($calSelection == 22) {
        // Year to date
        $start = $year . '-01-01';

        $endDT = new DateTime($year . date('m') . date('d'));
        $endDT->add(new DateInterval('P1D'));
        $end = $endDT->format('Y-m-d');

    } else {
        // Months
        $interval = 'P' . count($months) . 'M';
        $month = $months[0];
        $start = $year . '-' . $month . '-01';

        $endDate = new DateTime($start);
        $endDate->add(new DateInterval($interval));

        $end = $endDate->format('Y-m-d');
    }


    // Hospitals
    $whHosp = '';
    foreach ($hospitalSelections as $a) {
        if ($a != '') {
            if ($whHosp == '') {
                $whHosp .= $a;
            } else {
                $whHosp .= ",". $a;
            }
        }
    }

    $whAssoc = '';
    foreach ($assocSelections as $a) {

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
        $whAssoc = " and hs.idAssociation in (".$whAssoc.") ";
    }


    $totals = RoomReport::rescUtilization($dbh, $start, $end, 'm', 32, TRUE);

    $roomCount = $totals['roomCount'];

    $nights = json_encode(array_values($totals['Nights']));

    $axisBottom = json_encode(array_keys($totals['Nights']));
    $title = 'Year over Year Rooms in use for ' . $monthArray[$month][1] . ' ' . ($year - 1) . ' - ' .$year;



    // Last YEar
    $lstart = ($year - 1) . '-' . $month . '-01';

    $lendDate = new DateTime($lstart);
    $lendDate->add(new DateInterval('P1M'));
    $lendDate->sub(new DateInterval("P1D"));

    $ltotals = RoomReport::rescUtilization($dbh, $lstart, $lendDate->format('Y-m-d'), 'm', 32, TRUE);

    $oos = json_encode(array_values($ltotals['Nights']));


}


// Setups for the page.
if (count($aList) > 1) {
    $assocs = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($aList, $assocSelections, FALSE),
            array('name'=>'selAssoc[]', 'size'=>(count($aList)), 'multiple'=>'multiple', 'style'=>'min-width:60px;'));
}

$hospitals = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($hList, $hospitalSelections, FALSE),
        array('name'=>'selHospital[]', 'size'=>(count($hList)), 'multiple'=>'multiple', 'style'=>'min-width:60px;'));

$monSize = 7;
if (count($hList) > 7) {

    $monSize = count($hList);

    if ($monSize > 12) {
        $monSize = 12;
    }
}

$monthSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($monthArray, $months, FALSE), array('name' => 'selIntMonth[]', 'size'=>$monSize, 'multiple'=>'multiple'));
$yearSelector = HTMLSelector::generateMarkup(getYearOptionsMarkup($year, $config->getString('site', 'Start_Year', '2010'), $uS->fy_diff_Months, FALSE), array('name' => 'selIntYear', 'size'=>'5'));
$calSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($calOpts, $calSelection, FALSE), array('name' => 'selCalendar', 'size'=>count($calOpts)));


?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
<?php echo JQ_UI_CSS; ?>

        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
    <link href='css/house.css' rel='stylesheet' type='text/css' />
        <style>
            #roomMonth { width: 600px; height: 300px; float:left; }
        </style>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
<script type="text/javascript">
$(document).ready(function () {
    "use strict";
    $('.ckdate').datepicker({
        yearRange: '-05:+02',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        numberOfMonths: 1,
        dateFormat: 'M d, yy'
    });
    $('#selCalendar').change(function () {
        if ($(this).val() && $(this).val() != '19') {
            $('#selIntMonth').hide();
        } else {
            $('#selIntMonth').show();
        }
        if ($(this).val() && $(this).val() != '18') {
            $('.dates').hide();
        } else {
            $('.dates').show();
        }
    });
    $('#selCalendar').change();
    $('#btnByRoom').button();

    // Load the Visualization API and the corechart package.
    google.charts.load('current', {'packages':['corechart']});

    // Callback that creates and populates a data table,
    // instantiates the pie chart, passes in the data and
    // draws it.
    function drawChart() {

        // Create the data table.
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Topping');
        data.addColumn('number', 'Slices');
        data.addRows([
          ['Mushrooms', 3],
          ['Onions', 1],
          ['Olives', 1],
          ['Zucchini', 1],
          ['Pepperoni', 2]
        ]);

        // Set chart options
        var options = {'title':'<?php echo $title; ?>',
                       'width':400,
                       'height':300};

        // Instantiate and draw our chart, passing in some options.
        var chart = new google.visualization.PieChart(document.getElementById('chart_div'));
        chart.draw(data, options);

    }

    // Set a callback to run when the Google Visualization API is loaded.
    google.charts.setOnLoadCallback(drawChart);


//    try {
//    var nits = $.parseJSON('<?php echo $nights; ?>'),
//        oos = $.parseJSON('<?php echo $oos; ?>'),
//        axBottom = $.parseJSON('<?php echo $axisBottom; ?>'),
//        title = '<?php echo $title; ?>',
//        rmCount = '<?php echo $roomCount; ?>',
//        yr = '<?php echo $year; ?>';
//
//	$('#roomMonth').gchart({
//            type: 'line',
//            maxValue: rmCount,
//		title: title,
//                titleColor: 'green',
//		backgroundColor: $.gchart.gradient('horizontal', 'ccffff', 'ccffff00'),
//		series: [$.gchart.series(yr, nits, 'black'),
//                        $.gchart.series((yr - 1), oos, 'red')],
//		axes: [$.gchart.axis('bottom', axBottom, 'black'),
//		$.gchart.axis('left', 0, rmCount, 'red', 'right')
//		],
//		legend: 'right'});
//
//            } catch (err) {}
});
    </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <div style="float:left; margin-right: 100px; margin-top:10px;">
                <h1><?php echo $wInit->pageHeading; ?></h1>
            </div>
            <?php echo $resultMessage ?>
            <div style="clear:both;"></div>
            <form action="RoomView.php" method="post"  id="form1" name="form1" >
                <div class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-member-detail hhk-visitdialog">
                    <table style="float: left;">
                        <tr>
                            <th colspan="3">Time Period</th>
                        </tr>
                        <tr>
                            <th>Interval</th>
                            <th style="min-width:100px; ">Month</th>
                            <th>Year</th>
                        </tr>
                        <tr>
                            <td><?php echo $calSelector; ?></td>
                            <td><?php echo $monthSelector; ?></td>
                            <td><?php echo $yearSelector; ?></td>
                        </tr>
                        <tr>
                            <td colspan="3">
                                <span class="dates" style="margin-right:.3em;">Start:</span>
                                <input type="text" value="<?php echo $txtStart; ?>" name="stDate" id="stDate" class="ckdate dates" style="margin-right:.3em;"/>
                                <span class="dates" style="margin-right:.3em;">End:</span>
                                <input type="text" value="<?php echo $txtEnd; ?>" name="enDate" id="enDate" class="ckdate dates"/></td>
                        </tr>
                    </table>
                    <table style="float: left;">
                        <tr>
                            <th colspan="2">Hospitals</th>
                        </tr>
                        <?php if (count($aList) > 1) { ?><tr>
                            <th>Associations</th>
                            <th>Hospitals</th>
                        </tr><?php } ?>
                        <tr>
                            <?php if (count($aList) > 1) { ?><td><?php echo $assocs; ?></td><?php } ?>
                            <td><?php echo $hospitals; ?></td>
                        </tr>
                    </table>
                    <table style="width:100%; clear:both;">
                        <tr>
                            <td style="text-align: right;"><input type="submit" name="btnByRoom" value="Go" id="btnByRoom" /></td>
                        </tr>
                    </table>
                </div>
            </form>
            <div style="clear:both;"></div>
            <div id="chart_div"></div>

        </div>  <!-- div id="contentDiv"-->
    </body>
</html>
