<?php
/**
 * ReservReport.php
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

require (CLASSES . 'ColumnSelectors.php');
require CLASSES . 'OpenXML.php';


try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();

// Load the session with member-based lookups
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



$mkTable = '';  // var handed to javascript to make the report table or not.
$headerTable = '';
$dataTable = '';

$hospitalSelections = array();
$assocSelections = array();
$statusSelections = array();
$calSelection = '19';

$year = date('Y');
$months = array(date('n'));     // logically overloaded.
$status = '';
$txtStart = '';
$txtEnd = '';

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

$hList = array();
$aList = array();
foreach ($hospList as $h) {
    if ($h[2] == 'h') {
        $hList[] = array(0=>$h[0], 1=>$h[1]);
    } else if ($h[2] == 'a' && $h[1] != '(None)') {
        $aList[] = array(0=>$h[0], 1=>$h[1]);
    }
}

// Report column selector
// array: title, ColumnName, checked, fixed, Excel Type, Excel Style
$cFields[] = array('Resv Id', 'idReservation', 'checked', 'f', 'n', '');
$cFields[] = array("Room", 'Room', 'checked', '', 's', '');
$cFields[] = array('Hospital', 'Hospital', 'checked', '', 's', '');

if (count($aList) > 0) {
    $cFields[] = array("Association", 'Assoc', 'checked', '', 's', '');
}

$cFields[] = array("Diagnosis", 'Diagnosis', 'checked', '', 's', '');
$cFields[] = array("First", 'Name_First', 'checked', '', 's', '');
$cFields[] = array("Last", 'Name_Last', 'checked', '', 's', '');

// Address.
$pFields = array('gAddr', 'gCity');
$pTitles = array('Address', 'City');

if ($uS->county) {
    $pFields[] = 'gCounty';
    $pTitles[] = 'County';
}

$pFields = array_merge($pFields, array('gState', 'gCountry', 'gZip'));
$pTitles = array_merge($pTitles, array('State', 'Country', 'Zip'));

$cFields[] = array($pTitles, $pFields, '', '', 's', '', array());


$cFields[] = array("Room Phone", 'Phone', '', '', 's', '');
$cFields[] = array("Guest Phone", 'Phone_Num', '', '', 's', '');
$cFields[] = array("Arrive", 'Arrival', 'checked', '', 'n', PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14);
$cFields[] = array("Depart", 'Departure', 'checked', '', 'n', PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14);
$cFields[] = array("Nights", 'Nights', 'checked', '', 'n', '');
$cFields[] = array("Rate", 'FA_Category', 'checked', '', 's', '');
$cFields[] = array("Status", 'Status_Title', 'checked', '', 's', '');
$cFields[] = array("Status Date", 'Status_Date', 'checked', '', 'n', PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14);

$colSelector = new ColumnSelectors($cFields, 'selFld');




if (isset($_POST['btnHere']) || isset($_POST['btnExcel'])) {

    ini_set('memory_limit', "280M");

    $local = TRUE;
    if (isset($_POST['btnExcel'])) {
        $local = FALSE;
    }

    // set the column selectors
    $colSelector->setColumnSelectors($_POST);


    // gather input
    if (isset($_POST['selResvStatus'])) {
        $statusSelections = filter_var_array($_POST['selResvStatus'], FILTER_SANITIZE_STRING);
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

    $whDates = " r.Expected_Arrival <= '$end' and ifnull(r.Actual_Departure, r.Expected_Departure) >= '$start' ";


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

    // Visit status selections
    $whStatus = '';
    foreach ($statusSelections as $s) {
        if ($s != '') {
            if ($whStatus == '') {
                $whStatus = "'" . $s . "'";
            } else {
                $whStatus .= ",'".$s . "'";
            }
        }
    }
    if ($whStatus != '') {
        $whStatus = "and r.Status in (" . $whStatus . ") ";
    }


    $query = "select
    r.idReservation,
    r.idGuest,
    concat(ifnull(na.Address_1, ''), '', ifnull(na.Address_2, ''))  as gAddr,
    ifnull(na.City, '') as gCity,
    ifnull(na.County, '') as gCounty,
    ifnull(na.State_Province, '') as gState,
    ifnull(na.Country_Code, '') as gCountry,
    ifnull(na.Postal_Code, '') as gZip,
    np.Phone_Num,
    rm.Phone,
    ifnull(r.Actual_Arrival, r.Expected_Arrival) as `Arrival`,
    ifnull(r.Actual_Departure, r.Expected_Departure) as `Departure`,
    r.Fixed_Room_Rate,
    r.`Status` as `ResvStatus`,
    DATEDIFF(r.Expected_Departure, r.Expected_Arrival) as `Nights`,
    ifnull(n.Name_Last, '') as Name_Last,
    ifnull(n.Name_First, '') as Name_First,
    re.Title as `Room`,
    re.Type,
    re.Status as `RescStatus`,
    re.Category,
    rr.Title as `Rate`,
    rr.FA_Category,
    g.Title as 'Status_Title',
    hs.idPsg,
    hs.idHospital,
    hs.idAssociation,
    ifnull(gl.Description, '') as Diagnosis,
    r.Last_Updated as `Status_Date`
from
    reservation r
        left join
    resource re ON re.idResource = r.idResource
        left join
    name n ON r.idGuest = n.idName
        left join
    name_address na ON n.idName = na.idName and n.Preferred_Mail_Address = na.Purpose
        left join
    name_phone np ON n.idName = np.idName and n.Preferred_Phone = np.Phone_Code
        left join
    hospital_stay hs ON r.idHospital_Stay = hs.idHospital_stay
        left join
    room_rate rr ON r.idRoom_rate = rr.idRoom_rate
        left join resource_room rer on r.idResource = rer.idResource
        left join room rm on rer.idRoom = rm.idRoom
        left join
    lookups g ON g.Category = 'ReservStatus'
        and g.`Code` = r.`Status`
        left join
    gen_lookups gl ON gl.Table_Name = 'Diagnosis'
        and gl.`Code` = hs.Diagnosis
where " . $whDates . $whHosp . $whAssoc . $whStatus . " order by r.idRegistration";

    $stmt = $dbh->query($query);

    $fltrdTitles = $colSelector->getFilteredTitles();
    $fltrdFields = $colSelector->getFilteredFields();

    if ($local) {
        $tbl = new HTMLTable();
        $th = '';

        foreach ($fltrdTitles as $t) {
            $th .= HTMLTable::makeTh($t);
        }
        $tbl->addHeaderTr($th);

    } else {

        $reportRows = 1;
        $file = 'ReservReport';
        $sml = OpenXML::createExcel($uS->username, 'Reservation Report');

        // build header
        $hdr = array();
        $n = 0;

        foreach ($fltrdTitles as $t) {
            $hdr[$n++] = $t;
        }

        OpenXML::writeHeaderRow($sml, $hdr);
        $reportRows++;
    }

    $curVisit = 0;
    $curRoom = '';
    $curRate = '';
    $nites = 0;
    $totalNights = 0;


    $rrates = array();

    $roomRateRS = new Room_RateRS();
    $rows = EditRS::select($dbh, $roomRateRS, array());

    foreach ($rows as $r) {
        $roomRateRS = new Room_RateRS();
        EditRS::loadRow($r, $roomRateRS);
        $rrates[$roomRateRS->FA_Category->getStoredVal()] = $roomRateRS;
    }

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

        if ($curVisit != $r['idReservation']) {
            $curVisit = $r['idReservation'];
            $curRoom = $r['Room'];
            $curRate = $r['FA_Category'];
            $nites = 0;
        } else if ($curRoom != $r['Room']) {
            $curRoom = $r['Room'];
        } else if ($curRate != $r['FA_Category']) {
            $curRate = $r['FA_Category'];
        } else {
            $nites += $r['Nights'];
            continue;
        }

        $nites += $r['Nights'];
        $totalNights += $r['Nights'];

        if ($r['FA_Category'] == RoomRateCategorys::Fixed_Rate_Category) {
            $rate = $r['Fixed_Room_Rate'];
        } else {
            $rate = $r['Rate'];
        }


        $r['Assoc'] = '';
        if ($r['idAssociation'] > 0 && isset($uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']]) && $uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']][1] != '(None)') {
            $r['Assoc'] = $uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']][1];
        }
        if ($r['idHospital'] > 0 && isset($uS->guestLookups[GL_TableNames::Hospital][$r['idHospital']])) {
            $r['Hospital'] = $uS->guestLookups[GL_TableNames::Hospital][$r['idHospital']][1];
        }



        $arrivalDT = new DateTime($r['Arrival']);
        $departureDT = new DateTime($r['Departure']);
        $statusDT = new DateTime($r['Status_Date']);


        if ($local) {

            $r['Status_Title'] = HTMLContainer::generateMarkup('a', $r['Status_Title'], array('href'=>'Referral.php?rid=' . $r['idReservation']));
            $r['Arrival'] = $arrivalDT->format('M j, Y');
            $r['Departure'] = $departureDT->format('M j, Y');
            $r['Status_Date'] = $statusDT->format('M j, Y');
            $r['Name_Last'] = HTMLContainer::generateMarkup('a', $r['Name_Last'], array('href'=>'GuestEdit.php?id=' . $r['idGuest'] . '&psg=' . $r['idPsg']));
            $r['FA_Category'] = $rate;

            $tr = '';
            foreach ($fltrdFields as $f) {
                $tr .= HTMLTable::makeTd($r[$f[1]]);
            }

            $tbl->addBodyTr($tr);

        } else {

            $r['Arrival'] = PHPExcel_Shared_Date::PHPToExcel($arrivalDT);
            $r['Departure'] = PHPExcel_Shared_Date::PHPToExcel($departureDT);
            $r['Status_Date'] = PHPExcel_Shared_Date::PHPToExcel($statusDT);
            $r['FA_Category'] = $rate;

            $n = 0;
            $flds = array();

            foreach ($fltrdFields as $f) {
                $flds[$n++] = array('type' => $f[4], 'value' => $r[$f[1]], 'style'=>$f[5]);
            }

            $reportRows = OpenXML::writeNextRow($sml, $flds, $reportRows);

        }
    }

    // Finish the report
    if ($local) {

        $dataTable = $tbl->generateMarkup(array('id'=>'tblrpt'));
        $mkTable = 1;

    } else {

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $file . '.xlsx"');
        header('Cache-Control: max-age=0');

        OpenXML::finalizeExcel($sml);
        exit();

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


$monthSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($monthArray, $months, FALSE), array('name' => 'selIntMonth[]', 'size'=>'12', 'multiple'=>'multiple'));
$yearSelector = HTMLSelector::generateMarkup(getYearOptionsMarkup($year, $config->getString('site', 'Start_Year', '2010'), $uS->fy_diff_Months, FALSE), array('name' => 'selIntYear', 'size'=>'12'));

$statusList = removeOptionGroups($uS->guestLookups['ReservStatus']);
$statusSelector = HTMLSelector::generateMarkup(
        HTMLSelector::doOptionsMkup($statusList, $statusSelections), array('name' => 'selResvStatus[]', 'size'=>'6', 'multiple'=>'multiple'));
$calSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($calOpts, $calSelection, FALSE), array('name' => 'selCalendar', 'size'=>'5'));

$columSelector = $colSelector->makeSelectorTable(TRUE)->generateMarkup(array('style'=>'float:left;'));

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo TOP_NAV_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PRINT_AREA_JS ?>"></script>
<script type="text/javascript">
    $(document).ready(function() {
    $('#contentDiv').css('margin-top', $('#global-nav').css('height'));
        var isGuestAdmin = '<?php echo $isGuestAdmin; ?>';
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
        $('#cbColClearAll').click(function () {
            $('#selFld option').each(function () {
                $(this).prop('selected', false);
            });
        });
        $('#cbColSelAll').click(function () {
            $('#selFld option').each(function () {
                $(this).prop('selected', true);
            });
        });
        if (makeTable === '1') {
            $('div#printArea').css('display', 'block');
            try {
                listTable = $('#tblrpt').dataTable({
                    "iDisplayLength": 50,
                    "aLengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                    "dom": '<"top"ilf>rt<"bottom"lp><"clear">',
                });
            }
            catch (err) { }
            $('#printButton').button().click(function() {
                $("div#printArea").printArea();
            });
        }
    });
 </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <div id="divAlertMsg"><?php echo $resultMessage; ?></div>
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="clear:left; min-width: 400px; padding:10px;">
                <form id="fcat" action="ReservReport.php" method="post">
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
                            <th>Status</th>
                        </tr>
                        <tr>
                            <td><?php echo $statusSelector; ?></td>
                        </tr>
                    </table>
                    <table style="float: left;">
                        <tr>
                            <?php if (count($aList) > 0) echo '<th>Associations</th>';  ?>
                            <th>Hospitals</th>
                        </tr>
                        <tr>
                            <?php if (count($aList) > 0) echo '<td style="vertical-align: top;">'. $assocs .'</td>'; ?>
                            <td style="vertical-align: top;"><?php echo $hospitals; ?></td>
                        </tr>
                    </table>
                    <?php echo $columSelector; ?>
                    <table style="width:100%; clear:both;">
                        <tr>
                            <td style="width:50%;"></td>
                            <td><input type="submit" name="btnHere" id="btnHere" value="Run Here"/></td>
                            <td><input type="submit" name="btnExcel" id="btnExcel" value="Download to Excel"/></td>
                        </tr>
                    </table>
                </form>
            </div>
            <div style="clear:both;"></div>
            <div id="printArea" class="ui-widget ui-widget-content hhk-tdbox" style="display:none; font-size: .9em; padding: 5px; padding-bottom:25px;">
                <div><input id="printButton" value="Print" type="button"/></div>
                <table style="margin-top:20px; margin-bottom:10px; min-width: 350px;">
                    <?php echo $headerTable; ?>
                </table>
                <?php echo $dataTable; ?>
            </div>
        </div>
    </body>
</html>
