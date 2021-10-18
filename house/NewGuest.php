<?php

use HHK\HTMLControls\{HTMLContainer, HTMLTable, HTMLInput, HTMLSelector};
use HHK\sec\{Session, WebInit};
use HHK\ColumnSelectors;
use HHK\SysConst\GLTableNames;
use HHK\Config_Lite\Config_Lite;
use HHK\ExcelHelper;
use HHK\sec\Labels;
use HHK\House\Report\ReportFilter;

/**
 * NewGuest.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2020 <nonprofitsoftwarecorp.org>
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
// get session instance
$uS = Session::getInstance();

$labels = Labels::getLabels();
function doReport(\PDO $dbh, ColumnSelectors $colSelector, $start, $end, $whHosp, $whAssoc, $numberAssocs, $local, $labels) {

    // get session instance
    $uS = Session::getInstance();
    
    $pgTitle = $labels->getString('MemberType', 'primaryGuest', 'Primary Guest');

    $query = "SELECT
    s.idName,
    IFNULL(g1.Description, '') AS `Name_Prefix`,
    n.Name_First,
    n.Name_Middle,
    n.Name_Last,
    IFNULL(g2.Description, '') AS `Name_Suffix`,
    CASE when s.idName = v.idPrimaryGuest then '$pgTitle' else '' end as `Primary`,
    CASE when IFNULL(na.Address_2, '') = '' THEN IFNULL(na.Address_1, '') ELSE CONCAT(IFNULL(na.Address_1, ''), ' ', IFNULL(na.Address_2, '')) END AS `Address`,
    IFNULL(na.City, '') AS `City`,
    IFNULL(na.County, '') AS `County`,
    IFNULL(na.State_Province, '') AS `State_Province`,
    IFNULL(na.Postal_Code, '') AS `Postal_Code`,
    IFNULL(na.Country_Code, '') AS `Country`,
	IFNULL(np.Phone_Num, '') AS `Phone`,
	IFNULL(ne.Email, '') AS `Email`,
    IFNULL(g3.Description, '') AS `Relationship`,
    IFNULL(ng.idPsg, 0) as `idPsg`,
    IFNULL(hs.idHospital, 0) AS `idHospital`,
    IFNULL(hs.idAssociation, 0) AS `idAssociation`,
	IFNULL(v.Actual_Departure, '') AS `Visit End`,
    MIN(s.Checkin_Date) AS `First Stay`
FROM
    stays s
        JOIN
    visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
        JOIN
    `name` n ON s.idName = n.idname
        LEFT JOIN
    name_address na ON n.idName = na.idName
        AND n.Preferred_Mail_Address = na.Purpose
        LEFT JOIN
    name_phone np ON n.idName = np.idName AND n.Preferred_Phone = np.Phone_Code
        LEFT JOIN
    name_email ne ON n.idName = ne.idName AND n.Preferred_Email = ne.Purpose
        LEFT JOIN
    hospital_stay hs ON v.idHospital_stay = hs.idHospital_stay
        LEFT JOIN
    `name_guest` ng ON s.idName = ng.idName and hs.idPsg = ng.idPsg
        LEFT JOIN
    gen_lookups g1 ON g1.`Table_Name` = 'Name_Prefix'
        AND g1.`Code` = n.Name_Prefix
        LEFT JOIN
    gen_lookups g2 ON g2.`Table_Name` = 'Name_Suffix'
        AND g2.`Code` = n.Name_Suffix
	left join
    `gen_lookups` `g3` on `g3`.`Table_Name` = 'Patient_Rel_Type'
        and `g3`.`Code` = `ng`.`Relationship_Code`
WHERE
    n.Member_Status != 'TBD'
        AND n.Record_Member = 1
        $whAssoc $whHosp
GROUP BY s.idName
HAVING DATE(`First Stay`) >= DATE('$start')
    AND DATE(`First Stay`) < DATE('$end')
ORDER BY `First Stay`";

    $stmt = $dbh->query($query);

    $tbl = '';
    $sml = null;
    $reportRows = 0;
    $numNewGuests = $stmt->rowCount();

    if ($numberAssocs > 0) {
        $hospHeader = $labels->getString('hospital', 'hospital', 'Hospital').'/Assoc';
    } else {
        $hospHeader = $labels->getString('hospital', 'hospital', 'Hospital');
    }

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

        $file = 'NewGuests';
        
        $writer = new ExcelHelper($file);
        

        // build header
        $hdr = array();
        $colWidths = array();
        
        foreach($fltrdFields as $field){
            $hdr[$field[0]] = $field[4]; //set column header name and type;
            $colWidths[] = $field[5]; //set column width
        }
        
        $hdrStyle = $writer->getHdrStyle($colWidths);
        $writer->writeSheetHeader("Sheet1", $hdr, $hdrStyle);
        $reportRows++;

    }



    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

        // Hospital
        $hospital = '';

        if ($r['idAssociation'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']]) && $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1] != '(None)') {
            $hospital .= $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1] . ' / ';
            $assoc = $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1];
        }
        if ($r['idHospital'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idHospital']])) {
            $hospital .= $uS->guestLookups[GLTableNames::Hospital][$r['idHospital']][1];
            $hosp = $uS->guestLookups[GLTableNames::Hospital][$r['idHospital']][1];
        }

        $r['hospitalAssoc'] = $hospital;
        unset($r['idHospital']);
        unset($r['idAssociation']);

        $arrivalDT = new DateTime($r['First Stay']);


        if ($local) {

            $r['idName'] = HTMLContainer::generateMarkup('a', $r['idName'], array('href'=>'GuestEdit.php?id=' . $r['idName'] . '&psg=' . $r['idPsg']));

            $r['First Stay'] = $arrivalDT->format('c');

            $tr = '';
            foreach ($fltrdFields as $f) {
                $tr .= HTMLTable::makeTd($r[$f[1]], $f[6]);
            }

            $tbl->addBodyTr($tr);

        } else {

            $r['First Stay'] = $arrivalDT->format('Y-m-d');


            $flds = array();
            
            foreach ($fltrdFields as $f) {
                //$flds[$n++] = array('type' => $f[4], 'value' => $g[$f[1]], 'style'=>$f[5]);
                $flds[] = $r[$f[1]];
            }
            
            $row = $writer->convertStrings($hdr, $flds);
            $writer->writeSheetRow("Sheet1", $row);

        }

    }   // End of while



    // Finalize and print.
    if ($local) {

        $dataTable = $tbl->generateMarkup(array('id'=>'tblrpt', 'class'=>'display'));

        // Stats table
        $stmt2 = $dbh->query("Select COUNT(distinct idName) from stays "
                . "where DATE(Checkin_Date) >= DATE('$start') and DATE(Checkin_Date) < DATE('$end')");
        $rows = $stmt2->fetchAll(\PDO::FETCH_NUM);

        return array('tbl'=>$dataTable, 'new'=>$numNewGuests, 'all'=>$rows[0][0]);

    } else {
        $writer->download();
    }
}


$mkTable = '';  // var handed to javascript to make the report table or not.
$headerTable = HTMLContainer::generateMarkup('h3', $uS->siteName . ' New ' . $labels->getString('MemberType', 'visitor', 'Guest') . 's Report Details', array('style'=>'margin-top: .5em;'))
        . HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));
$dataTable = '';
$statsTable = '';

// Get labels
$labels = Labels::getLabels();
$errorMessage = '';

$filter = new ReportFilter();
$filter->createTimePeriod(date('Y'), '19', $uS->fy_diff_Months);
$filter->createHospitals();

// Report column-selector
// array: title, ColumnName, checked, fixed, Excel Type, Excel colWidth, td parms
$cFields[] = array("Id", 'idName', 'checked', '', 'string', '10', array());
$cFields[] = array("Prefix", 'Name_Prefix', 'checked', '', 'string', '15', array());
$cFields[] = array("First", 'Name_First', 'checked', '', 'string', '20', array());
$cFields[] = array("Middle", 'Name_Middle', 'checked', '', 'string', '20', array());
$cFields[] = array("Last", 'Name_Last', 'checked', '', 'string', '20', array());
$cFields[] = array("Suffix", 'Name_Suffix', 'checked', '', 'string', '15', array());
$cFields[] = array($labels->getString('MemberType', 'primaryGuest', 'Primary Guest'), 'Primary', 'checked', '', 'string', '20', array());

    $pFields = array('Address', 'City');
    $pTitles = array('Address', 'City');

    if ($uS->county) {
        $pFields[] = 'County';
        $pTitles[] = 'County';
    }

    $pFields = array_merge($pFields, array('State_Province', 'Postal_Code', 'Country'));
    $pTitles = array_merge($pTitles, array('State', 'Zip', 'Country'));

    $cFields[] = array($pTitles, $pFields, '', '', 'string', '20', array());
    
$cFields[] = array('Phone', 'Phone', 'checked', '', 'string', '20', array());
$cFields[] = array('Email', 'Email', 'checked', '', 'string', '20', array());
    
$cFields[] = array("First Stay", 'First Stay', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
$cFields[] = array("Visit End", 'Visit End', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');

$cFields[] = array($labels->getString('MemberType', 'patient', 'Patient')." Relation", 'Relationship', 'checked', '', 'string', '20', array());
$cFields[] = array($labels->getString('GuestEdit', 'psgTab', 'Patient Support Group')."  Id", 'idPsg', 'checked', '', 'string', '15', array());

if (count($filter->getAList()) > 0) {
    $cFields[] = array($labels->getString('hospital', 'hospital', 'Hospital')." / Assoc", 'hospitalAssoc', 'checked', '', 'string', '20', array());
} else {
    $cFields[] = array($labels->getString('hospital', 'hospital', 'Hospital'), 'hospitalAssoc', 'checked', '', 'string', '20', array());
}

$colSelector = new ColumnSelectors($cFields, 'selFld');


if (isset($_POST['btnHere']) || isset($_POST['btnExcel'])) {

    $local = TRUE;
    if (isset($_POST['btnExcel'])) {
        $local = FALSE;
    }

    // set the column selectors
    $colSelector->setColumnSelectors($_POST);
    
    $filter->loadSelectedTimePeriod();
    $filter->loadSelectedHospitals();

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

    if ($filter->getReportStart() != '' && $filter->getReportEnd() != '') {

        $dataArray = doReport($dbh, $colSelector, $filter->getReportStart(), $filter->getReportEnd(), $whHosp, $whAssoc, count($filter->getAList()), $local, $labels);

        $dataTable = $dataArray['tbl'];
        $mkTable = 1;

        // Stats Table
        $numNewGuests = $dataArray['new'];
        $numAllGuests = $dataArray['all'];
        $numReturnGuests = max($numAllGuests - $numNewGuests, 0);

        $newRatio = 0;
        if ($numAllGuests > 0) {
            $newRatio = ($numNewGuests / $numAllGuests) * 100;
        }

        $sTbl = new HTMLTable();
        $sTbl->addHeader(HTMLTable::makeTh('') . HTMLTable::makeTh('Number') . HTMLTable::makeTh('Percent of Total'));

        $sTbl->addBodyTr(HTMLTable::makeTd('New ' .$labels->getString('MemberType', 'visitor', 'Guest'). 's:', array('class'=>'tdlabel')) . HTMLTable::makeTd($numNewGuests) . HTMLTable::makeTd(number_format($newRatio) . '%'));
        $sTbl->addBodyTr(HTMLTable::makeTd('Returning ' .$labels->getString('MemberType', 'visitor', 'Guest'). 's:', array('class'=>'tdlabel')) . HTMLTable::makeTd($numReturnGuests) . HTMLTable::makeTd(number_format(100 - $newRatio) . '%'));
        $sTbl->addBodyTr(HTMLTable::makeTd('Total ' .$labels->getString('MemberType', 'visitor', 'Guest'). 's:', array('class'=>'tdlabel')) . HTMLTable::makeTd($numAllGuests));


        $statsTable = HTMLContainer::generateMarkup('h3', $uS->siteName . ' New ' .$labels->getString('MemberType', 'visitor', 'Guest'). 's Report Statistics')
                . HTMLContainer::generateMarkup('p', 'These numbers are specific to this report\'s selected filtering parameters.')
                . $sTbl->generateMarkup();



        $headerTable .= HTMLContainer::generateMarkup('p', 'Report Period: ' . date('M j, Y', strtotime($filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($filter->getReportEnd())));

        $hospitalTitles = '';
        $hospitalTitles = $filter->getSelectedHospitalsString();

        if ($hospitalTitles != 'All') {
            $headerTable .= HTMLContainer::generateMarkup('p', $labels->getString('hospital', 'hospital', 'Hospital').'s: ' . $hospitalTitles);
        } else {
            $headerTable .= HTMLContainer::generateMarkup('p', 'All '.$labels->getString('hospital', 'hospital', 'Hospital').'s');
        }

    } else {
        $errorMessage = 'Missing the dates.';
    }

}

// Setups for the page.
$timePeriodMarkup = $filter->timePeriodMarkup()->generateMarkup(array('style'=>'float: left;'));
$hospitalMarkup = $filter->hospitalMarkup()->generateMarkup(array('style'=>'float: left;margin-left:5px;'));
$columSelector = $colSelector->makeSelectorTable(TRUE)->generateMarkup(array('style'=>'float:left;margin-left:5px'));

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

<script type="text/javascript">
    $(document).ready(function() {
        var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
        var columnDefs = $.parseJSON('<?php echo json_encode($colSelector->getColumnDefs()); ?>');
        var makeTable = '<?php echo $mkTable; ?>';
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
        $('#btnHere, #btnExcel, #cbColClearAll, #cbColSelAll').button();

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
            $('div#printArea, div#stats').css('display', 'block');

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
    });
 </script>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <div id="vnewguests" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="clear:left; min-width: 400px; padding:10px;">
                <p class="ui-state-active" style="max-width: 750px; margin:9px;padding:4px;">Shows the number of <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s STARTING their stay during the selected period.  This may not be the same number as the total number of <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s at the house.</p>
                <form id="fcat" action="NewGuest.php" method="post">
                	<div class="ui-helper-clearfix">
                    	<?php echo $timePeriodMarkup; ?>
                    	<?php echo $hospitalMarkup; ?>
                    	<?php echo $columSelector; ?>
                    </div>
                    <div style="text-align:center; margin-top: 10px;">
                    	<span style="color:red; margin-right:1em;"><?php echo $errorMessage; ?></span>
                        <input type="submit" name="btnHere" id="btnHere" value="Run Here" style="margin-right: 1em;"/>
                    	<input type="submit" name="btnExcel" id="btnExcel" value="Download to Excel"/>
                    </div>
                </form>
            </div>
            <div id="stats" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="display: none; padding:10px; margin: 10px 0; clear:left;">
                <?php echo $statsTable; ?>
            </div>
            <div style="clear:both;"></div>
            <div id="printArea" class="ui-widget ui-widget-content ui-corner-all hhk-tdbox" style="display:none; font-size: .8em; padding: 5px; padding-bottom:25px; margin-bottom: 10px;">
                <div><input id="printButton" value="Print" type="button"/></div>
                <div style="margin-top:10px; margin-bottom:10px; min-width: 350px;">
                    <?php echo $headerTable; ?>
                </div>
                <?php echo $dataTable; ?>
            </div>
        </div>
    </body>
</html>
