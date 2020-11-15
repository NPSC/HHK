<?php

use HHK\HTMLControls\{HTMLContainer, HTMLTable, HTMLInput, HTMLSelector};
use HHK\sec\{Session, WebInit};
use HHK\ColumnSelectors;
use HHK\SysConst\GLTableNames;
use HHK\Config_Lite\Config_Lite;
use HHK\ExcelHelper;
use HHK\sec\Labels;

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
    
    $pgTitle = 'Primary'.$labels->getString('MemberType', 'guest', 'Guest');

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

$hospitalSelections = array('');
$assocSelections = array('');
$calSelection = '19';

$year = date('Y');
$months = array(date('n'));     // logically overloaded.
$txtStart = '';
$txtEnd = '';
$start = '';
$end = '';
$errorMessage = '';


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
if (isset($uS->guestLookups[GLTableNames::Hospital])) {
    $hospList = $uS->guestLookups[GLTableNames::Hospital];
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



// Report column-selector
// array: title, ColumnName, checked, fixed, Excel Type, Excel colWidth, td parms
$cFields[] = array("Id", 'idName', 'checked', '', 'string', '10', array());
$cFields[] = array("Prefix", 'Name_Prefix', 'checked', '', 'string', '15', array());
$cFields[] = array("First", 'Name_First', 'checked', '', 'string', '20', array());
$cFields[] = array("Middle", 'Name_Middle', 'checked', '', 'string', '20', array());
$cFields[] = array("Last", 'Name_Last', 'checked', '', 'string', '20', array());
$cFields[] = array("Suffix", 'Name_Suffix', 'checked', '', 'string', '15', array());
$cFields[] = array('Primary'.$labels->getString('MemberType', 'guest', 'Guest'), 'Primary', 'checked', '', 'string', '20', array());

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

$cFields[] = array($labels->getString('MemberType', 'patient', 'Patient')." Relation", 'Relationship', 'checked', '', 'string', '20', array());
$cFields[] = array($labels->getString('MemberType', 'patient', 'Patient')." Group Id", 'idPsg', 'checked', '', 'string', '15', array());

if (count($aList) > 0) {
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
        } else {
            $startDT = new DateTime();
        }

        if ($txtEnd != '') {
            $endDT = new DateTime($txtEnd);
        } else {
            $endDT = new DateTime();
        }

        $start = $startDT->format('Y-m-d');
        $end = $endDT->format('Y-m-d');

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

    if ($start != '' && $end != '') {

        $dataArray = doReport($dbh, $colSelector, $start, $end, $whHosp, $whAssoc, count($aList), $local, $labels);

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


        $statsTable = HTMLContainer::generateMarkup('h3', $uS->siteName . 'New ' .$labels->getString('MemberType', 'visitor', 'Guest'). 's Report Statistics')
                . HTMLContainer::generateMarkup('p', 'These numbers are specific to this report\'s selected filtering parameters.')
                . $sTbl->generateMarkup();



        $headerTable .= HTMLContainer::generateMarkup('p', 'Report Period: ' . date('M j, Y', strtotime($start)) . ' thru ' . date('M j, Y', strtotime($end)));

        $hospitalTitles = '';
        foreach ($assocSelections as $h) {
            if (isset($hospList[$h])) {
                $hospitalTitles .= $hospList[$h][1] . ', ';
            }
        }
        foreach ($hospitalSelections as $h) {
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

    } else {
        $errorMessage = 'Missing the dates.';
    }

}

// Setups for the page.
if (count($aList) > 1) {
    $assocs = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($aList, $assocSelections, FALSE),
            array('name'=>'selAssoc[]', 'size'=>(count($aList)), 'multiple'=>'multiple', 'style'=>'min-width:60px;'));
}

$hospitals = HTMLSelector::generateMarkup( HTMLSelector::doOptionsMkup($hList, $hospitalSelections, FALSE),
        array('name'=>'selHospital[]', 'size'=>(count($hList)), 'multiple'=>'multiple', 'style'=>'min-width:60px;'));


$monthSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($monthArray, $months, FALSE), array('name' => 'selIntMonth[]', 'size'=>'12', 'multiple'=>'multiple'));
$yearSelector = HTMLSelector::generateMarkup(getYearOptionsMarkup($year, '2010', $uS->fy_diff_Months, FALSE), array('name' => 'selIntYear', 'size'=>'12'));
$calSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($calOpts, $calSelection, FALSE), array('name' => 'selCalendar', 'size'=>'5'));

$columSelector = $colSelector->makeSelectorTable(TRUE)->generateMarkup(array('style'=>'float:left;'));

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
                "dom": '<"top"ilf>rt<"bottom"ilp><"clear">',
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
                            <th colspan="2"><?php echo $labels->getString('hospital', 'hospital', 'Hospital'); ?>s</th>
                        </tr>
                        <?php if (count($aList) > 1) { ?><tr>
                            <th>Associations</th>
                            <th><?php echo $labels->getString('hospital', 'hospital', 'Hospital'); ?>s</th>
                        </tr><?php } ?>
                        <tr>
                            <?php if (count($aList) > 1) { ?><td style="vertical-align: top;"><?php echo $assocs; ?></td><?php } ?>
                            <td style="vertical-align: top;"><?php echo $hospitals; ?></td>
                        </tr>
                    </table>
                    <?php echo $columSelector; ?>
                    <table style="width:100%; clear:both;">
                        <tr>
                            <!--<td><?php echo $colSelector->getRanges(); ?></td>-->
                            <td style="width:50%;"><span style="color:red;"><?php echo $errorMessage; ?></span></td>
                            <td><input type="submit" name="btnHere" id="btnHere" value="Run Here"/></td>
                            <td><input type="submit" name="btnExcel" id="btnExcel" value="Download to Excel"/></td>
                        </tr>
                    </table>
                </form>
            </div>
            <div id="stats" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="padding:10px;clear:left;">
                <?php echo $statsTable; ?>
            </div>
            <div style="clear:both;"></div>
            <div id="printArea" class="ui-widget ui-widget-content hhk-tdbox" style="display:none; font-size: .8em; padding: 5px; padding-bottom:25px;">
                <div><input id="printButton" value="Print" type="button"/></div>
                <div style="margin-top:10px; margin-bottom:10px; min-width: 350px;">
                    <?php echo $headerTable; ?>
                </div>
                <?php echo $dataTable; ?>
            </div>
        </div>
    </body>
</html>
