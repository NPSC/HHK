<?php
/**
 * RoomUtilization.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

require (CLASSES . 'History.php');

require (HOUSE . 'VisitLog.php');
require (HOUSE . 'RoomLog.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'ResourceView.php');
require (HOUSE . 'RoomReport.php');

require (CLASSES . 'ColumnSelectors.php');
require CLASSES . 'OpenXML.php';



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
$labels = new Config_Lite(LABEL_FILE);

// Instantiate the alert message control
$alertMsg = new alertMessage("divAlert1");
$alertMsg->set_DisplayAttr("none");
$alertMsg->set_Context(alertMessage::Success);
$alertMsg->set_iconId("alrIcon");
$alertMsg->set_styleId("alrResponse");
$alertMsg->set_txtSpanId("alrMessage");
$alertMsg->set_Text("help");

$resultMessage = $alertMsg->createMarkup();

$hospitalSelections = array();
$assocSelections = array();
$groupingSelection = 'Category';
$calSelection = '19';
$mkTable = '';

$year = date('Y');
$months = array(date('n'));     // logically overloaded.
$status = '';
$txtStart = '';
$txtEnd = '';


$monthArray = array(
    0 => array(0, 'December'), 1 => array(1, 'January'), 2 => array(2, 'February'),
    3 => array(3, 'March'), 4 => array(4, 'April'), 5 => array(5, 'May'), 6 => array(6, 'June'),
    7 => array(7, 'July'), 8 => array(8, 'August'), 9 => array(9, 'September'), 10 => array(10, 'October'), 11 => array(11, 'November'), 12 => array(12, 'December'), 13 => array(13, 'January'));

if ($uS->fy_diff_Months == 0) {
    $calOpts = array(19 => array(19, 'Month'), 21 => array(21, 'Cal. Year'), 22 => array(22, 'Year to Date'));
} else {
    $calOpts = array(19 => array(19, 'Month'), 20 => array(20, 'Fiscal Year'), 21 => array(21, 'Calendar Year'), 22 => array(22, 'Year to Date'));
}


// Hospital and association lists
$hospList = array();
if (isset($uS->guestLookups[GL_TableNames::Hospital])) {
    $hospList = $uS->guestLookups[GL_TableNames::Hospital];
}

$hList = array();
$aList = array();
foreach ($hospList as $h) {
    if ($h[2] == 'h') {
        $hList[] = array(0=>$h[0], 1=>$h[1]);
    } else if ($h[2] == 'a' && $h[1] != '(None)') {
        $aList[] = array(0=>$h[0], 1=>$h[1]);
    }
}

// Room Groupings
$roomGroups = readGenLookupsPDO($dbh, 'Room_Group');


// Callback
if (isset($_POST['btnByGuest']) || isset($_POST['btnByRoom'])) {

    // Room Grouping
    if (isset($_POST['selGroup'])) {
        $groupingSelection = filter_var($_POST['selGroup'], FILTER_SANITIZE_STRING);
    }

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

    } else if ($calSelection == 22) {
        // Year to date
        $start = date('Y') . '-01-01';

        $endDT = new DateTime();
        $endDT->add(new DateInterval('P1D'));
        $end = $endDT->format('Y-m-d');

    } else {
        // Months
        $interval = 'P' . count($months) . 'M';
        $month = $months[0];

        if ($month < 1) {
            $y = $year - 1;
            $start = $y . '-12-01';
        } else if ($month > 12) {
            $y = $year + 1;
            $start = $y . '-01-01';
        } else {
            $start = $year . '-' . $month . '-01';
        }

        $endDate = new DateTime($start);
        $endDate->add(new DateInterval($interval));
        $endDate->sub(new DateInterval('P1D'));

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
        $whHosp .= " and hs.idAssociation in (".$whAssoc.") ";
    }

    $mkTable = 1;

    if (isset($_POST['btnByGuest'])) {
        $output = RoomReport::roomNOR($dbh, $start, $end, $whHosp, $roomGroups[$groupingSelection]);
    } else {
        $output = RoomReport::rescUtilization($dbh, $start, $end, $roomGroups[$groupingSelection]);
    }
}


// Setups for the page.
$assocs = '';
if (count($aList) > 0) {
    $assocs = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($aList, $assocSelections),
                array('name'=>'selAssoc[]', 'size'=>'3', 'multiple'=>'multiple', 'style'=>'min-width:60px;'));
}

$numHosp = count($hList) + 1;

$hospitals = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($hList, $hospitalSelections),
                array('name'=>'selHospital[]', 'size'=>$numHosp, 'multiple'=>'multiple', 'style'=>'min-width:60px;'));


$monthSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($monthArray, $months, FALSE), array('name' => 'selIntMonth[]', 'size'=>'14', 'multiple'=>'multiple'));
$yearSelector = HTMLSelector::generateMarkup(getYearOptionsMarkup($year, $config->getString('site', 'Start_Year', '2010'), $uS->fy_diff_Months, FALSE), array('name' => 'selIntYear', 'size'=>'12'));

$roomGrouping = HTMLSelector::generateMarkup(
        HTMLSelector::doOptionsMkup(removeOptionGroups($roomGroups), $groupingSelection, FALSE), array('name' => 'selGroup', 'size'=>'4'));

$calSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($calOpts, $calSelection, FALSE), array('name' => 'selCalendar', 'size'=>'4'));

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <?php echo FAVICON; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>

        <script type="text/javascript">
$(document).ready(function() {
    "use strict";
    var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
    var makeTable = '<?php echo $mkTable; ?>';
    $('#btnHere, #btnExcel, #cbColClearAll, #cbColSelAll').button();

    $('.ckdate').datepicker({
        yearRange: '-05:+01',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        numberOfMonths: 1,
        dateFormat: 'M d, yy'
    });

    $('#selCalendar').change(function () {
        $('#selIntYear').show();
        if ($(this).val() && $(this).val() != '19') {
            $('#selIntMonth').hide();
        } else {
            $('#selIntMonth').show();
        }
        if ($(this).val() && $(this).val() != '18') {
            $('.dates').hide();
        } else {
            $('.dates').show();
            $('#selIntYear').hide();
        }
    });
    $('#selCalendar').change();

    $('#btnByGuest, #btnByRoom').button();

    if (makeTable === '1') {

        $('div#printArea').css('display', 'block');

//        $('#tblrpt').dataTable({
//        'columnDefs': [
//            {'targets': columnDefs,
//             'type': 'date',
//             'render': function ( data, type, row ) {return dateRender(data, type, dateFormat);}
//            }
//         ],
//            "displayLength": 50,
//            "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
//            "dom": '<"top"ilf>rt<"bottom"lp><"clear">',
//        });
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
            <div id="divAlertMsg"><?php echo $resultMessage; ?></div>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-member-detail hhk-visitdialog">
                <form action="RoomUtilization.php" method="post"  id="form1" name="form1" >
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
                            <td style="vertical-align: top;"><?php echo $calSelector; ?></td>
                            <td style="vertical-align: top;"><?php echo $monthSelector; ?></td>
                            <td style="vertical-align: top;"><?php echo $yearSelector; ?></td>
                        </tr>
<!--                        <tr>
                            <td colspan="3">
                                <span class="dates" style="margin-right:.3em;">Start:</span>
                                <input type="text" value="<?php echo $txtStart; ?>" name="stDate" id="stDate" class="ckdate dates" style="margin-right:.3em;"/>
                                <span class="dates" style="margin-right:.3em;">End:</span>
                                <input type="text" value="<?php echo $txtEnd; ?>" name="enDate" id="enDate" class="ckdate dates"/></td>
                        </tr>-->
                    </table>
                    <?php if ((count($aList) + count($hList)) > 1) { ?>
                    <table style="float: left;margin-left:5px;">
                        <tr>
                            <?php if (count($aList) > 0) echo '<th>Associations</th>';  ?>
                            <th>Hospitals</th>
                        </tr>
                        <tr>
                            <?php if (count($aList) > 0) {echo '<td style="vertical-align: top;">'. $assocs .'</td>';} ?>
                            <td style="vertical-align: top;"><?php echo $hospitals; ?></td>
                        </tr>
                    </table>
                    <?php } ?>
                    <table style="float: left;margin-left:5px;">
                        <tr>
                            <th>Room Grouping</th>
                        </tr>
                        <tr>
                            <td><?php echo $roomGrouping; ?></td>
                        </tr>
                    </table>
                    <table style="width:100%; clear:both; margin-top:10px;">
                        <tr>
                            <td style="text-align:right;">
                                <input type="submit" name="btnByRoom" value="By Room" id="btnByRoom" />
                            </td>
                            <td colspan="2" style="text-align:right;">
                                <input type="submit" name="btnByGuest" value="By Guest" id="btnByGuest" />
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
            <div style="clear:both;"></div>
            <div id="printArea" class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-member-detail hhk-visitdialog" style="display:none; font-size: .9em; padding: 5px; padding-bottom:25px;">
                <div><input id="printButton" value="Print" type="button"/></div>
                <?php echo $output; ?>
            </div>
        </div>  <!-- div id="contentDiv"-->
    </body>
</html>
