<?php
/**
 * ReservReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");

require (CLASSES . 'ColumnSelectors.php');
require CLASSES . 'OpenXML.php';
require(HOUSE . 'ReportFilter.php');


try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;

// get session instance
$uS = Session::getInstance();
$labels = new Config_Lite(LABEL_FILE);
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


$mkTable = '';  // var handed to javascript to make the report table or not.
$headerTable = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));
$dataTable = '';
$status = '';
$statusSelections = array();

$filter = new ReportFilter();
$filter->createTimePeriod(date('Y'), '19', $uS->fy_diff_Months);
$filter->createHospitals();

// Report column selector
// array: title, ColumnName, checked, fixed, Excel Type, Excel Style
$cFields[] = array('Resv Id', 'idReservation', 'checked', 'f', 'n', '');
$cFields[] = array("Room", 'Room', 'checked', '', 's', '');

if ((count($filter->getAList()) + count($filter->getHList())) > 1) {

    $cFields[] = array($labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital'), 'Hospital', 'checked', '', 's', '');

    if (count($filter->getAList()) > 0) {
        $cFields[] = array("Association", 'Assoc', 'checked', '', 's', '');
    }
}

$locations = readGenLookupsPDO($dbh, 'Location');
if (count($locations) > 0) {
    $cFields[] = array($labels->getString('hospital', 'location', 'Location'), 'Location', 'checked', '', 's', '', array());
}

$diags = readGenLookupsPDO($dbh, 'Diagnosis');
if (count($diags) > 0) {
    $cFields[] = array($labels->getString('hospital', 'diagnosis', 'Diagnosis'), 'Diagnosis', 'checked', '', 's', '', array());
}

// Reservation statuses
$statusList = removeOptionGroups($uS->guestLookups['ReservStatus']);


$cFields[] = array("Doctor", 'Name_Doctor', '', '', 's', '');
$cFields[] = array("Agent", 'Name_Agent', '', '', 's', '');


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
$cFields[] = array("Arrive", 'Arrival', 'checked', '', 'n', PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14, array(), 'date');
$cFields[] = array("Depart", 'Departure', 'checked', '', 'n', PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14, array(), 'date');
$cFields[] = array("Nights", 'Nights', 'checked', '', 'n', '');
$cFields[] = array("Rate", 'FA_Category', 'checked', '', 's', '');
$cFields[] = array("Status", 'Status_Title', 'checked', '', 's', '');
$cFields[] = array("Created Date", 'Created_Date', 'checked', '', 'n', PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14, array(), 'date');

$colSelector = new ColumnSelectors($cFields, 'selFld');




if (isset($_POST['btnHere']) || isset($_POST['btnExcel'])) {

    ini_set('memory_limit', "280M");

    $local = TRUE;
    if (isset($_POST['btnExcel'])) {
        $local = FALSE;
    }

    // set the column selectors
    $colSelector->setColumnSelectors($_POST);
    $filter->loadSelectedTimePeriod();
    $filter->loadSelectedHospitals();


    $whDates = " r.Expected_Arrival <= '" . $filter->getReportEnd() . "' and ifnull(r.Actual_Departure, r.Expected_Departure) >= '" . $filter->getReportStart() . "' ";

    if (isset($_POST['selResvStatus'])) {
        $statusSelections = filter_var_array($_POST['selResvStatus'], FILTER_SANITIZE_STRING);
    }

    // Hospitals
    $whHosp = '';
    foreach ($filter->getSelectedHosptials() as $a) {
        if ($a != '') {
            if ($whHosp == '') {
                $whHosp .= $a;
            } else {
                $whHosp .= ",". $a;
            }
        }
    }

    $whAssoc = '';
    foreach ($filter->getSelectedAssocs() as $a) {
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
    re.`Type`,
    re.`Status` as `RescStatus`,
    re.`Category`,
    rr.`Title` as `Rate`,
    rr.FA_Category,
    g.Title as 'Status_Title',
    hs.idPsg,
    hs.idHospital,
    hs.idAssociation,
    nd.Name_Full as `Name_Doctor`,
    nr.Name_Full as `Name_Agent`,
    ifnull(gl.`Description`, '') as `Diagnosis`,
    ifnull(g2.`Description`, '') as `Location`,
    r.Timestamp as `Created_Date`
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
    name nd ON hs.idDoctor = nd.idName
        left join
    name nr ON hs.idReferralAgent = nr.idName
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
        LEFT JOIN
    gen_lookups g2 ON g2.Table_Name = 'Location'
        and g2.`Code` = hs.`Location`
where " . $whDates . $whHosp . $whAssoc . $whStatus . " order by r.idRegistration";


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

    $stmt = $dbh->query($query);

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
        $statusDT = new DateTime($r['Created_Date']);


        if ($local) {

            $r['Status_Title'] = HTMLContainer::generateMarkup('a', $r['Status_Title'], array('href'=>$config->getString('house', 'ReservationPage', 'Reserve.php') . '?rid=' . $r['idReservation']));
            $r['Arrival'] = $arrivalDT->format('c');
            $r['Departure'] = $departureDT->format('c');
            $r['Created_Date'] = $statusDT->format('c');
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
            $r['Created_Date'] = PHPExcel_Shared_Date::PHPToExcel($statusDT);
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

        $dataTable = $tbl->generateMarkup(array('id'=>'tblrpt', 'class'=>'display'));
        $mkTable = 1;
        $headerTable .= HTMLContainer::generateMarkup('p', 'Report Period: ' . date('M j, Y', strtotime($filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($filter->getReportEnd())));

        $hospitalTitles = '';
        $hospList = $filter->getHospitals();

        foreach ($filter->getSelectedAssocs() as $h) {
            if (isset($hospList[$h])) {
                $hospitalTitles .= $hospList[$h][1] . ', ';
            }
        }
        foreach ($filter->getSelectedHosptials() as $h) {
            if (isset($hospList[$h])) {
                $hospitalTitles .= $hospList[$h][1] . ', ';
            }
        }

        if ($hospitalTitles != '') {
            $h = trim($hospitalTitles);
            $hospitalTitles = substr($h, 0, strlen($h) - 1);
            $headerTable .= HTMLContainer::generateMarkup('p', 'Hospitals: ' . $hospitalTitles);
        } else {
            $headerTable .= HTMLContainer::generateMarkup('p', 'All Hospitals');
        }

        $statusTitles = '';
        foreach ($statusSelections as $s) {
            if (isset($statusList[$s])) {
                $statusTitles .= $statusList[$s][1] . ', ';
            }
        }

        if ($statusTitles != '') {
            $s = trim($statusTitles);
            $statusTitles = substr($s, 0, strlen($s) - 1);
            $headerTable .= HTMLContainer::generateMarkup('p', 'Statuses: ' . $statusTitles);
        } else {
            $headerTable .= HTMLContainer::generateMarkup('p', 'All Statuses');
        }


    } else {

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $file . '.xlsx"');
        header('Cache-Control: max-age=0');

        OpenXML::finalizeExcel($sml);
        exit();

    }

}

// Setups for the page.

$statusSelector = HTMLSelector::generateMarkup(
        HTMLSelector::doOptionsMkup($statusList, $statusSelections), array('name' => 'selResvStatus[]', 'size'=>'6', 'multiple'=>'multiple'));

$columSelector = $colSelector->makeSelectorTable(TRUE)->generateMarkup(array('style'=>'float:left;'));

$timePeriodMarkup = $filter->timePeriodMarkup($config)->generateMarkup(array('style'=>'float: left;'));
$hospitalMarkup = $filter->hospitalMarkup()->generateMarkup(array('style'=>'float: left;margin-left:5px;'));

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
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
        var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
        var columnDefs = $.parseJSON('<?php echo json_encode($colSelector->getColumnDefs()); ?>');
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

            $('#tblrpt').dataTable({
            'columnDefs': [
                {'targets': columnDefs,
                 'type': 'date',
                 'render': function ( data, type, row ) {return dateRender(data, type, dateFormat);}
                }
             ],
                "displayLength": 50,
                "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                "dom": '<"top"ilf>rt<"bottom"lp><"clear">',
            });
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
                    <?php echo $timePeriodMarkup; ?>
                    <table style="float: left;">
                        <tr>
                            <th>Status</th>
                        </tr>
                        <tr>
                            <td><?php echo $statusSelector; ?></td>
                        </tr>
                    </table>
                    <?php if (count($filter->getHospitals()) > 1) {
                            echo $hospitalMarkup;
                        }
                        echo $columSelector; ?>
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
                <?php echo $headerTable; ?>
                <?php echo $dataTable; ?>
            </div>
        </div>
    </body>
</html>
