<?php
/**
 * GuestTransfer.php
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

require ("homeIncludes.php");

require CLASSES . 'CreateMarkupFromDB.php';
require CLASSES . 'OpenXML.php';

try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$pageHeader = $wInit->pageHeading;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();

// Load the session with member - based lookups
$wInit->sessionLoadGenLkUps();
$wInit->sessionLoadGuestLkUps();

$config = new Config_Lite(ciCFG_FILE);

// Instantiate the alert message control
$alertMsg = new alertMessage("divAlert1");
$alertMsg->set_DisplayAttr("none");
$alertMsg->set_Context(alertMessage::Success);
$alertMsg->set_iconId("alrIcon");
$alertMsg->set_styleId("alrResponse");
$alertMsg->set_txtSpanId("alrMessage");
$alertMsg->set_Text("help");

$resultMessage = $alertMsg->createMarkup();

$isGuestAdmin = ComponentAuthClass::is_Authorized('guestadmin');

$labels = new Config_Lite(LABEL_FILE);



function getPeopleReport(\PDO $dbh, $local, $start, $end) {

    $uS = Session::getInstance();
    $transferIds = [];

    $query = "select vg.Id, vg.Prefix, vg.First as `Guest First`, vg.Last as `Guest Last`, vg.Suffix, ifnull(vg.BirthDate, '') as `Birth Date`, "
        . " vg.Address, vg.City, vg.County, vg.State, vg.Zip, CASE WHEN vg.Country = '' THEN 'US' ELSE vg.Country END as `Country`, vg.Phone, vg.Email, "
        . " max(ifnull(s.Span_Start_Date, '')) as `Arrival`, ifnull(s.Span_End_Date, '') as `Departure` "
        . " from stays s
        join
    vguest_listing vg on vg.Id = s.idName
where ifnull(vg.External_Id, '') = '' and ifnull(DATE(s.Span_End_Date), DATE(now())) > DATE('$start') and DATE(s.Span_Start_Date) < DATE('$end') "
            . " GROUP BY vg.Id";

    $stmt = $dbh->query($query);

    if (!$local) {

        $reportRows = 1;
        $file = 'GuestTransfer';
        $sml = OpenXML::createExcel('Guest Tracker', 'Guest Transfer');
    }

    $rows = array();
    $firstRow = TRUE;

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {


        if ($uS->county === FALSE) {
            unset($r['County']);
        }

        $transferIds[] = $r['Id'];

        if ($firstRow) {

            $firstRow = FALSE;

            if ($local === FALSE) {

                // build header
                $hdr = array();
                $n = 0;
                $noReturn = '';

                // HEader row
                $keys = array_keys($r);
                foreach ($keys as $k) {


                    $hdr[$n++] =  $k;
                }

                if ($noReturn != '') {
                    $hdr[$n++] =  $noReturn;
                }

                OpenXML::writeHeaderRow($sml, $hdr);
                $reportRows++;
            }
        }

        if ($local) {

            //$r['Id'] = HTMLContainer::generateMarkup('a', $r['Id'], array('href'=>'GuestEdit.php?id=' . $r['Id'] . '&psg=' . $r['idPsg']));

            if (isset($r['Birth Date']) && $r['Birth Date'] != '') {
                $r['Birth Date'] = date('M j, Y', strtotime($r['Birth Date']));
            }
            if ($r['Arrival'] != '') {
                $r['Arrival'] = date('M j, Y', strtotime($r['Arrival']));
            }
            if ($r['Departure'] != '') {
                $r['Departure'] = date('M j, Y', strtotime($r['Departure']));
            }
            unset($r['idPsg']);



            $rows[] = $r;

        } else {

            $n = 0;
            $flds = array();


            foreach ($r as $key => $col) {

                if (($key == 'Arrival' or $key == 'Departure' || $key == 'Birth Date') && $col != '') {

                    $flds[$n++] = array('type' => "n",
                        'value' => PHPExcel_Shared_Date::PHPToExcel(new DateTime($col)),
                        'style' => PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14);

                } else {
                    $flds[$n++] = array('type' => "s", 'value' => $col);
                }
            }

            $reportRows = OpenXML::writeNextRow($sml, $flds, $reportRows);
        }

    }

    if ($local) {

        $dataTable = CreateMarkupFromDB::generateHTML_Table($rows, 'tblrpt');
        return array('mkup' =>$dataTable, 'xfer'=>$transferIds);


    } else {

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $file . '.xlsx"');
        header('Cache-Control: max-age=0');

        OpenXML::finalizeExcel($sml);
        exit();

    }
}


$mkTable = '';
$dataTable = '';
$settingstable = '';
$year = date('Y');
$months = array(date('n'));     // logically overloaded.
$txtStart = '';
$txtEnd = '';
$start = '';
$end = '';
$errorMessage = '';
$calSelection = '19';
$transferIds = [];


$monthArray = array(
    1 => array(1, 'January'), 2 => array(2, 'February'),
    3 => array(3, 'March'), 4 => array(4, 'April'), 5 => array(5, 'May'), 6 => array(6, 'June'),
    7 => array(7, 'July'), 8 => array(8, 'August'), 9 => array(9, 'September'), 10 => array(10, 'October'), 11 => array(11, 'November'), 12 => array(12, 'December'));

if ($uS->fy_diff_Months == 0) {
    $calOpts = array(18 => array(18, 'Dates'), 19 => array(19, 'Month'), 21 => array(21, 'Cal. Year'), 22 => array(22, 'Year to Date'));
} else {
    $calOpts = array(18 => array(18, 'Dates'), 19 => array(19, 'Month'), 20 => array(20, 'Fiscal Year'), 21 => array(21, 'Calendar Year'), 22 => array(22, 'Year to Date'));
}


// Process report.
if (isset($_POST['btnHere']) || isset($_POST['btnExcel'])) {

    $local = TRUE;
    if (isset($_POST['btnExcel'])) {
        $local = FALSE;
    }

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



    // Create settings markup
    $sTbl = new HTMLTable();


    $results = getPeopleReport($dbh, $local, $start, $end);
    $dataTable = $results['mkup'];
    $transferIds = $results['xfer'];


    $sTbl->addBodyTr(HTMLTable::makeTh('Guest Transfer', array('colspan'=>'4')));
    $sTbl->addBodyTr(HTMLTable::makeTd('From', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($start))) . HTMLTable::makeTd('Thru', array('class'=>'tdlabel')) . HTMLTable::makeTd(date('M j, Y', strtotime($end))));
    $settingstable = $sTbl->generateMarkup();

    $mkTable = 1;

}



// Setups for the page.

$monthSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($monthArray, $months, FALSE), array('name' => 'selIntMonth[]', 'size'=>'5','multiple'=>'multiple'));
$yearSelector = HTMLSelector::generateMarkup(getYearOptionsMarkup($year, $config->getString('site', 'Start_Year', '2010'), $uS->fy_diff_Months, FALSE), array('name' => 'selIntYear', 'size'=>'5'));
$calSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($calOpts, $calSelection, FALSE), array('name' => 'selCalendar', 'size'=>count($calOpts)));

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        <?php echo JQ_UI_CSS; ?>
        <?php echo TOP_NAV_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <style>.hhk-rowseparater { border-top: 2px #0074c7 solid !important; }</style>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo STATE_COUNTRY_JS; ?>"></script>
<script type="text/javascript">
    $(document).ready(function() {
    $('#contentDiv').css('margin-top', $('#global-nav').css('height'));
        var makeTable = '<?php echo $mkTable; ?>';
        var transferIds = <?php echo json_encode($transferIds); ?>;
        $('#btnHere, #btnExcel').button();

        if (makeTable === '1') {
            $('div#printArea').show();
            $('#divPrintButton').show();
            try {
                $('#tblrpt').dataTable({
                    "iDisplayLength": 50,
                    "aLengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                    "dom": '<"top"ilf>rt<"bottom"lp><"clear">',
                });
            }
            catch (err) { }

            $('#printButton').button().click(function() {
                $("div#printArea").printArea();
            });

            $('#TxButton').button().click(function () {
                var parms = {
                    cmd: 'xfer',
                    ids: transferIds,
                };
                var posting = $.post('ws_tran.php', parms);
                posting.done(function(incmg) {
                    if (!incmg) {
                        alert('Bad Reply from Server');
                        return;
                    }
                    try {
                    incmg = $.parseJSON(incmg);
                    } catch (err) {
                        alert('Bad JSON Encoding');
                        return;
                    }

                    if (incmg.error) {
                        if (incmg.gotopage) {
                            window.open(incmg.gotopage, '_self');
                        }
                        // Stop Processing and return.
                        flagAlertMessage(incmg.error, true);
                        return;
                    }

                    if (incmg.data) {

                        $('#printArea').replaceWith(incmg.data);

                    }
                });
            });
        }

        $('.ckdate').datepicker({
            yearRange: '-05:+01',
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
    });
 </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <div id="divAlertMsg"><?php echo $resultMessage; ?></div>
            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="clear:left; min-width: 400px; padding:10px;">
                <form id="fcat" action="GuestTransfer.php" method="post">
                   <table style="clear:left;float: left;">
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
                    <table style="width:100%; margin-top: 15px;">
                        <tr>
                            <td><input type="submit" name="btnHere" id="btnHere" value="Run Here"/></td>
                            <td><input type="submit" name="btnExcel" id="btnExcel" value="Download to Excel"/></td>
                        </tr>
                    </table>
                </form>
            </div>
            <div style="clear:both;"></div>
            <div id="divPrintButton" style="display:none;margin-top:6px;margin-bottom:3px;">
                <input id="printButton" value="Print" type="button" />
                <input id="TxButton" value="Transfer" type="button" style="margin-left:2em;"/>
            </div>
            <div id="printArea" class="ui-widget ui-widget-content hhk-tdbox hhk-visitdialog" style="float:left;display:none; font-size: .8em; padding: 5px; padding-bottom:25px;">
                <div style="margin-bottom:.5em;"><?php echo $settingstable; ?></div>
                <?php echo $dataTable; ?>
            </div>
        </div>
    </body>
</html>
