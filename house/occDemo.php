<?php
/**
 * occDemo.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require("homeIncludes.php");

require(HOUSE . "GuestReport.php");

$wInit = new webInit();
$wInit->sessionLoadGenLkUps();

$uS = Session::getInstance();

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;
$menuMarkup = $wInit->generatePageMenu();

$config = new Config_Lite(ciCFG_FILE);
$zip = $config->getString('house', 'Zip_Code', '');
$startYear = $config->getString('site', 'Start_Year', '2005');

$report = "";

$headerTable = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));

$tabOpened = 0;

// Get labels
$labels = new Config_Lite(LABEL_FILE);

$hospitalSelections = array('');
$assocSelections = array('');
$calSelection = '22';


$year = date('Y');
$txtStart = '';
$txtEnd = '';
$status = '';
$statsTable = '';
$start = '';
$end = '';
$errorMessage = '';
$dateInterval = new DateInterval('P1M');

if ($uS->fy_diff_Months == 0) {
    $calOpts = array(18 => array(18, 'Dates'), 21 => array(21, 'Cal. Year'), 22 => array(22, 'Year to Date'));
} else {
    $calOpts = array(18 => array(18, 'Dates'), 20 => array(20, 'Fiscal Year'), 21 => array(21, 'Calendar Year'), 22 => array(22, 'Year to Date'));
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

// Run Selected year Report?
if (isset($_POST['btnSmt'])) {

    // gather input
    if (isset($_POST['selCalendar'])) {
        $calSelection = intval(filter_var($_POST['selCalendar'], FILTER_SANITIZE_NUMBER_INT), 10);
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

        $endDT = new DateTime($year . date('m') . '01');
        $endDT->add(new DateInterval('P1M'));
        $end = $endDT->format('Y-m-d');

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

    $report = GuestReport::demogReport($dbh, $start, $end, $whHosp, $whAssoc, $zip);
    $tabOpened = 0;
}


// Setups for the page.
if (count($aList) > 1) {
    $assocs = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($aList, $assocSelections, FALSE),
            array('name'=>'selAssoc[]', 'size'=>(count($aList)), 'multiple'=>'multiple', 'style'=>'min-width:60px;'));
}

$hospitals = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($hList, $hospitalSelections, FALSE),
        array('name'=>'selHospital[]', 'size'=>(count($hList)), 'multiple'=>'multiple', 'style'=>'min-width:60px;'));

$yearSelector = HTMLSelector::generateMarkup(getYearOptionsMarkup($year, $config->getString('site', 'Start_Year', '2010'), $uS->fy_diff_Months, FALSE), array('name' => 'selIntYear', 'size'=>'7'));
$calSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($calOpts, $calSelection, FALSE), array('name' => 'selCalendar', 'size'=>'4'));

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo TOP_NAV_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <style>
            .hhk-tdTitle {
                background-color: #F2F2F2;
            }
        </style>
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript">
    // Init j-query and the page blocker.
    $(document).ready(function() {
        $('#btnSmt, #btnCkZip').button();
        $('.ckdate').datepicker({
            yearRange: '-05:+02',
            changeMonth: true,
            changeYear: true,
            autoSize: true,
            numberOfMonths: 1,
            dateFormat: 'M d, yy'
        });
        $('#selCalendar').change(function () {
            $('#selIntYear').show();
            if ($(this).val() && $(this).val() != '18') {
                $('.dates').hide();
            } else {
                $('.dates').show();
                $('#selIntYear').hide();
            }
        });
        $('#selCalendar').change();

        $('#btnCkZip').click(function() {
            var zipf = $('#txtZipFrom').val();
            if (!zipf || zipf.length !== 5) {
                return;
            }
            var zipt = $('#txtZipTo').val();
            if (!zipt || zipt.length !== 5) {
                return;
            }
            $.post('../admin/ws_gen.php', {cmd: 'zipd', 'zipf': zipf, 'zipt': zipt},
            function(data) {
                try {
                    data = $.parseJSON(data);
                } catch (err) {
                    alert('Bad JSON Encoding');
                    return;
                }
                if (data.error) {
                    $('#zipDistAnswer').text('Zip Code not found.');
                    return;
                } else if (data.success) {
                    $('#zipDistAnswer').text(data.success + ' miles');
                }
            });
        });
    });
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?> >
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2>Guest Demography Report</h2>
            <div id="vreport" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="clear:left; min-width: 400px; padding:10px;">
                <form action="occDemo.php" method="post">
                    <table style="float: left;">
                        <tr>
                            <th colspan="3">Time Period</th>
                        </tr>
                        <tr>
                            <th>Interval</th>
                            <th>Year</th>
                        </tr>
                        <tr>
                            <td style="vertical-align: top;"><?php echo $calSelector; ?></td>
                            <td style="vertical-align: top;"><?php echo $yearSelector; ?></td>
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
                            <?php if (count($aList) > 1) { ?><td style="vertical-align: top;"><?php echo $assocs; ?></td><?php } ?>
                            <td style="vertical-align: top;"><?php echo $hospitals; ?></td>
                        </tr>
                    </table>
                    <div style="float:right;margin-left:130px;">
                        <fieldset class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox"><legend>Distance Calculator</legend>
                        <table>
                        <tr><th>From</th><th>To</th></tr>
                        <tr><td><input type="text" id="txtZipFrom" value="<?php echo $zip ?>" size="5"/></td><td><input type="text" id="txtZipTo" value="" size="5"/></td></tr>
                        <tr><td colspan="2"><span id="zipDistAnswer"></span></td></tr><tr><td colspan="2"><input type="button" id="btnCkZip" value="Get Zip Distance"/></td></tr>
                    </table></fieldset>
                    </div>
                    <div style="clear:both;"></div>
                    <table style="padding-top:20px; ">
                        <tr>
                            <td>
                                <input type="submit" id="btnSmt" name="btnSmt" value="Run Report" />
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
            <div style="clear:both;"></div>
            <div id="printArea" style="margin-top:10px;margin-bottom:10px;font-size: .9em;">
                <?php echo $report; ?>
            </div>
        </div>
    </body>
</html>
