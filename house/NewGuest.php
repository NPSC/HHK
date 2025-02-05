<?php

use HHK\HTMLControls\{HTMLContainer, HTMLTable, HTMLInput, HTMLSelector};
use HHK\sec\{Session, WebInit};
use HHK\ColumnSelectors;
use HHK\SysConst\GLTableNames;
use HHK\ExcelHelper;
use HHK\sec\Labels;
use HHK\House\Report\ReportFilter;
use HHK\House\Report\NewGuest;

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

// Get labels
$labels = Labels::getLabels();

$mkTable = '';  // var handed to javascript to make the report table or not.
$headerTable = HTMLContainer::generateMarkup('h3', $uS->siteName . ' New ' . $labels->getString('MemberType', 'visitor', 'Guest') . 's Report Details', array('style'=>'margin-top: .5em;'))
        . HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));
$dataTable = '';
$statsTable = '';
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

$cFields[] = array($labels->getString('hospital', 'referralAgent', 'Referral Agent')." First", 'Referral_Agent_First', '', '', 'string', '15', array());
$cFields[] = array($labels->getString('hospital', 'referralAgent', 'Referral Agent')." Last", 'Referral_Agent_Last', '', '', 'string', '15', array());

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
        $whHosp .= " and hs.idAssociation in (".$whAssoc.") ";
    }

    if ($filter->getReportStart() != '' && $filter->getReportEnd() != '') {

        $mkTable = 1;

        $newGuest = new NewGuest($filter->getReportStart(), $filter->getQueryEnd());

        $dataTable = $newGuest->doNewGuestReport($dbh, $colSelector, $whHosp, $local, $labels);

        $newGuest->doReturningGuests($dbh, $whHosp);

        $numAllGuests = $newGuest->getNumberNewGuests() + $newGuest->getNumberReturnGuests();
        $newRatio = 0;

        if ($numAllGuests > 0) {
            $newRatio = ($newGuest->getNumberNewGuests() / $numAllGuests) * 100;
        }

        // Guests
        $sTbl = new HTMLTable();
        $sTbl->addHeader(HTMLTable::makeTh('Guests') . HTMLTable::makeTh('Number') . HTMLTable::makeTh('Percent of Total'));

        $sTbl->addBodyTr(
            HTMLTable::makeTd('New ' .$labels->getString('MemberType', 'visitor', 'Guest'). 's:', array('class'=>'tdlabel'))
            . HTMLTable::makeTd($newGuest->getNumberNewGuests())
            . HTMLTable::makeTd(number_format($newRatio) . '%'));

        $sTbl->addBodyTr(
            HTMLTable::makeTd('Returning ' .$labels->getString('MemberType', 'visitor', 'Guest'). 's:', array('class'=>'tdlabel'))
            . HTMLTable::makeTd($newGuest->getNumberReturnGuests())
            . HTMLTable::makeTd(number_format(100 - $newRatio) . '%'));

        $sTbl->addBodyTr(
            HTMLTable::makeTd('Total ' . $labels->getString('MemberType', 'visitor', 'Guest') . 's:', array('class'=>'tdlabel'))
            . HTMLTable::makeTd($numAllGuests));

        // PSGs
        $newGuest->doNewPSGs($dbh, $whHosp);
        $newGuest->doReturningPSGs($dbh, $whHosp);
        $numAllPSGs = $newGuest->getNumberNewPSGs() + $newGuest->getNumberReturnPSGs();
        $newRatio = 0;

        if ($numAllPSGs > 0) {
            $newRatio = ($newGuest->getNumberNewPSGs() / $numAllPSGs) * 100;
        }

        $pTbl = new HTMLTable();
        $pTbl->addHeader(HTMLTable::makeTh('PSGs') . HTMLTable::makeTh('Number') . HTMLTable::makeTh('Percent of Total'));

        $pTbl->addBodyTr(
            HTMLTable::makeTd('New PSGs', array('class'=>'tdlabel'))
            . HTMLTable::makeTd($newGuest->getNumberNewPSGs())
            . HTMLTable::makeTd(number_format($newRatio) . '%'));

        $pTbl->addBodyTr(
            HTMLTable::makeTd('Returning PSGs', array('class'=>'tdlabel'))
            . HTMLTable::makeTd($newGuest->getNumberReturnPSGs())
            . HTMLTable::makeTd(number_format(100 - $newRatio) . '%'));

        $pTbl->addBodyTr(
            HTMLTable::makeTd('Total PSGs', array('class'=>'tdlabel'))
            . HTMLTable::makeTd($numAllPSGs));


        $statsTable = HTMLContainer::generateMarkup('h3', $uS->siteName . ' New ' .$labels->getString('MemberType', 'visitor', 'Guest'). 's Report Statistics')
                . HTMLContainer::generateMarkup('p', 'These numbers are specific to this report\'s selected filtering parameters.')
                . $sTbl->generateMarkup(array('style'=>'display:inline;')) . $pTbl->generateMarkup(array('style'=>'display:inline;margin-left:10px;'));



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
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <?php echo CSSVARS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>

<script type="text/javascript">
    $(document).ready(function() {
        var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
        var columnDefs = $.parseJSON('<?php echo json_encode($colSelector->getColumnDefs()); ?>');
        var makeTable = '<?php echo $mkTable; ?>';
        $('.ckdate').datepicker({
            yearRange: '<?php echo $uS->StartYear; ?>:+01',
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
                "dom": '<"top ui-toolbar ui-helper-clearfix"ilf><\"hhk-overflow-x\"rt><"bottom ui-toolbar ui-helper-clearfix"lp><"clear">',
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
                <form autocomplete="off">
                <?php echo $dataTable; ?>
                </form>
            </div>
        </div>
    </body>
</html>
