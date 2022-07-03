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

/**
 * ReservReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");

// 7/1/2021 - Added "Days" column.  EKC

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

$mkTable = '';  // var handed to javascript to make the report table or not.
$headerTable = HTMLContainer::generateMarkup('h3', $uS->siteName . ' Reservation Report', array('style'=>'margin-top: .5em;'))
        . HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));
$dataTable = '';


$reservationReport = new ReservationReport($dbh, $_REQUEST);

if (isset($_POST['btnHere']) || isset($_POST['btnExcel'])) {

    ini_set('memory_limit', "280M");

    $local = TRUE;
    if (isset($_POST['btnExcel'])) {
        $local = FALSE;
    }

    $resultSet = $reservationReport->getResultSet();

    if ($local) {
        $tbl = new HTMLTable();
        $th = '';

        foreach ($reservationReport->filteredTitles as $t) {
            $th .= HTMLTable::makeTh($t);
        }
        $tbl->addHeaderTr($th);

    } else {

        $reportRows = 1;
        $file = 'ReservReport';
        $writer = new ExcelHelper($file);
        $writer->setAuthor($uS->username);
        $writer->setTitle("Reservation Report");

        // build header
        $hdr = array();
        $colWidths = array();


        foreach($reservationReport->filteredFields as $field){
            $hdr[$field[0]] = $field[4]; //set column header name and type;
            $colWidths[] = $field[5]; //set column width
        }

        $hdrStyle = $writer->getHdrStyle($colWidths);
        $writer->writeSheetHeader("Sheet1", $hdr, $hdrStyle);
    }

    $curVisit = 0;
    $curRoom = '';
    $curRate = '';

    foreach($resultSet as $r) {

        if ($curVisit != $r['idReservation']) {
            $curVisit = $r['idReservation'];
            $curRoom = $r['Room'];
            $curRate = $r['FA_Category'];
        } else if ($curRoom != $r['Room']) {
            $curRoom = $r['Room'];
        } else if ($curRate != $r['FA_Category']) {
            $curRate = $r['FA_Category'];
        } else {
            continue;
        }

        if ($r['FA_Category'] == RoomRateCategories::Fixed_Rate_Category) {
            $rate = $r['Fixed_Room_Rate'];
        } else {
            $rate = $r['Rate'];
        }


        $r['Assoc'] = '';
        if ($r['idAssociation'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']]) && $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1] != '(None)') {
            $r['Assoc'] = $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1];
        }
        if ($r['idHospital'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idHospital']])) {
            $r['Hospital'] = $uS->guestLookups[GLTableNames::Hospital][$r['idHospital']][1];
        }



        $arrivalDT = new DateTime($r['Arrival']);
        $departureDT = new DateTime($r['Departure']);
        $statusDT = new DateTime($r['Created_Date']);
        $lastUpdatedDT = new DateTime($r['Last_Updated']);

        // add Days column
        $r['Days'] = $r['Nights'] + 1;

        if ($local) {

            $r['Status_Title'] = HTMLContainer::generateMarkup('a', $r['Status_Title'], array('href'=>'Reserve.php?rid=' . $r['idReservation']));
            $r['Arrival'] = $arrivalDT->format('c');
            $r['Departure'] = $departureDT->format('c');
            $r['Created_Date'] = $statusDT->format('c');
            $r['Last_Updated'] = $lastUpdatedDT->format('c');
            $r['Name_Last'] = HTMLContainer::generateMarkup('a', $r['Name_Last'], array('href'=>'GuestEdit.php?id=' . $r['idGuest'] . '&psg=' . $r['idPsg']));
            $r['FA_Category'] = $rate;

            $tr = '';
            foreach ($fltrdFields as $f) {
                $tr .= HTMLTable::makeTd($r[$f[1]]);
            }

            $tbl->addBodyTr($tr);

        } else {

            $r['FA_Category'] = $rate;

            $flds = array();

            foreach ($fltrdFields as $f) {
                $flds[] = $r[$f[1]];
            }

            $row = $writer->convertStrings($hdr, $flds);
            $writer->writeSheetRow("Sheet1", $row);
        }
    }

    // Finish the report
    if ($local) {

        $dataTable = $tbl->generateMarkup(array('id'=>'tblrpt', 'class'=>'display'));
        $mkTable = 1;
        $headerTable .= HTMLContainer::generateMarkup('p', 'Report Period: ' . date('M j, Y', strtotime($reservationReport->filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($reservationReport->filter->getReportEnd())));

        $hospitalTitles = '';
        $hospList = $reservationReport->filter->getHospitals();

        foreach ($reservationReport->filter->getSelectedAssocs() as $h) {
            if (isset($hospList[$h])) {
                $hospitalTitles .= $hospList[$h][1] . ', ';
            }
        }
        foreach ($reservationReport->filter->getSelectedHosptials() as $h) {
            if (isset($hospList[$h])) {
                $hospitalTitles .= $hospList[$h][1] . ', ';
            }
        }

        if ($hospitalTitles != '') {
            $h = trim($hospitalTitles);
            $hospitalTitles = substr($h, 0, strlen($h) - 1);
            $headerTable .= HTMLContainer::generateMarkup('p', $labels->getString('hospital', 'hospital', 'Hospital').'s: ' . $hospitalTitles);
        } else {
            $headerTable .= HTMLContainer::generateMarkup('p', 'All '.$labels->getString('hospital', 'hospital', 'Hospital').'s');
        }

        $statusTitles = '';
        foreach ($reservationReport->selectedResvStatuses as $s) {
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
        $writer->download();
    }

}

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
        <?php echo GRID_CSS; ?>
        <?php echo NOTY_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo REPORTFIELDSETS_JS; ?>"></script>

<script type="text/javascript">
    $(document).ready(function() {
        var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
        var columnDefs = $.parseJSON('<?php echo json_encode($reservationReport->colSelector->getColumnDefs()); ?>');
        var makeTable = '<?php echo $mkTable; ?>';
        $('#btnHere, #btnExcel, #cbColClearAll, #cbColSelAll').button();

        <?php echo $reservationReport->filter->getTimePeriodScript(); ?>;
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
                "dom": '<"top ui-toolbar ui-helper-clearfix"ilf>rt<"bottom ui-toolbar ui-helper-clearfix"lp><"clear">',
            });
            $('#printButton').button().click(function() {
                $("div#printArea").printArea();
            });
        }

        $('#includeFields').fieldSets({'reportName': 'reserv', 'defaultFields': <?php echo json_encode($reservationReport->getDefaultFields()) ?>});
    });
 </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <?php echo $reservationReport->getFilterMarkup(); ?>
            <div id="printArea" class="ui-widget ui-widget-content ui-corner-all hhk-tdbox" style="display:none; font-size: .9em; padding: 5px; padding-bottom:25px; margin: 10px 0;">
                <div><input id="printButton" value="Print" type="button"/></div>
                <?php echo $headerTable; ?>
                <?php echo $dataTable; ?>
            </div>
        </div>
    </body>
</html>
