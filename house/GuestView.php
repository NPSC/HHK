<?php
use HHK\sec\WebInit;
use HHK\Config_Lite\Config_Lite;
use HHK\sec\Session;
use HHK\CreateMarkupFromDB;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLTable;
use HHK\sec\Labels;
use HHK\House\Report\{ReportFilter, ReportFieldSet};
use HHK\ColumnSelectors;
use HHK\House\Report\GuestVehicleReport;

/**
 * GuestView.php
 * List resident guests and their vehicles.
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");


try {
    $wInit = new WebInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

$dbh = $wInit->dbh;
$pageTitle = $wInit->pageTitle;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();

$labels = Labels::getLabels();

$guestVehicleReport = new GuestVehicleReport($dbh);

$columnSelector = $guestVehicleReport->setupGuestFields();

$resultMessage = "";

$guestReportMkup = '';

if (isset($_POST['btnHere'])){
    $guestReportMkup = $guestVehicleReport->getGuestMkup();
}

//vehicle report
$vehicleReportMkup = '';
if ($uS->TrackAuto) {
    $vehicleReportMkup = $guestVehicleReport->getVehicleMkup();
}


$guestMessage = '';
$vehicleMessage = '';
$emtableMarkupv = '';
$tab = 0;

if(isset($_POST['btnEmailv'])){
    $emailAddress = '';
    $subject = '';

    if (isset($_POST['txtEmailv'])) {
        $emailAddress = filter_var($_POST['txtEmailv'], FILTER_SANITIZE_EMAIL);
    }

    if (isset($_POST['txtSubjectv'])) {
        $subject = filter_var($_POST['txtSubjectv'], FILTER_SANITIZE_STRING);
    }

    $vehicleMessage = $guestVehicleReport->sendEmail("vehicles", $subject, $emailAddress);
    $tab = 1;
}

if (isset($_POST['btnEmail'])) {

    $emailAddress = '';
    $subject = '';

    if (isset($_POST['txtEmail'])) {
    	$emailAddress = filter_var($_POST['txtEmail'], FILTER_SANITIZE_STRING);
    }

    if (isset($_POST['txtSubject'])) {
    	$subject = filter_var($_POST['txtSubject'], FILTER_SANITIZE_STRING);
    }

    $guestMessage = $guestVehicleReport->sendEmail("guests", $subject, $emailAddress);
}

// create send guest email table
$emTbl = new HTMLTable();
$emTbl->addHeaderTr(HTMLTable::makeTh('Email the Current ' .$labels->getString('MemberType', 'visitor', 'Guest') . ' Report'));
$emTbl->addBodyTr(HTMLTable::makeTd('Subject: ' . HTMLInput::generateMarkup("Current ".$labels->getString('MemberType', 'visitor', 'Guest')."s Report", array('name' => 'txtSubject', 'size' => '70'))));
$emTbl->addBodyTr(HTMLTable::makeTd(
        'Email: '
        . HTMLInput::generateMarkup('', array('name' => 'txtEmail', 'size' => '70'))));

$emTbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup('Send Email', array('name' => 'btnEmail', 'type' => 'submit')) . HTMLContainer::generateMarkup('span', $guestMessage, array('style'=>'color:red;margin-left:.5em;'))));

$emtableMarkup = $emTbl->generateMarkup(array('style'=>'margin-bottom: 0.5em;'));

if ($uS->TrackAuto) {
    // create send vehicle email table
    $emTblv = new HTMLTable();
    $emTblv->addBodyTr(HTMLTable::makeTd('Subject: ' . HTMLInput::generateMarkup('Vehicle Report', array('name' => 'txtSubjectv', 'size' => '70'))));
    $emTblv->addBodyTr(HTMLTable::makeTd(
            'Email: '
        . HTMLInput::generateMarkup('', array('name' => 'txtEmailv', 'value'=>$uS->vehicleReportEmail, 'size' => '70'))));

    $emTblv->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup('Send Email', array('name' => 'btnEmailv', 'type' => 'submit')) . HTMLContainer::generateMarkup('span', $vehicleMessage, array('style'=>'color:red;margin-left:.5em;'))));

    $emtableMarkupv = $emTblv->generateMarkup(array(), 'Email the Vehicle Report');
}
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
        <?php echo GRID_CSS; ?>
        <?php echo NOTY_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo REPORTFIELDSETS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript">
    $(document).ready(function () {
        "use strict";
        $('#includeFields').fieldSets({'reportName': 'GuestView', 'defaultFields': <?php echo json_encode($guestVehicleReport->defaultFields) ?>});

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

        var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM d, YYYY"); ?>';
        var tabReturn = '<?php echo $tab; ?>';
        var columnDefs = $.parseJSON('<?php echo json_encode($guestVehicleReport->colSelector->getColumnDefs()); ?>');

        $('#btnEmail, #btnPrint, #btnEmailv, #btnPrintv').button();
        $('#tblList').dataTable({
            "displayLength": 50,
            "dom": '<"top"if>rt<"bottom"lp><"clear">',
            "order": [[0, 'asc']],
            'columnDefs': [
                {'targets': columnDefs,
                 'type': 'date',
                 'render': function ( data, type, row ) {return dateRender(data, type, dateFormat);}
                }
             ]
        });
        $('#tblListv').dataTable({
            "displayLength": 50,
            "dom": '<"top"if>rt<"bottom"lp><"clear">',
            "order": [[0, 'asc']],
            'columnDefs': [
                {'targets': [4,5],
                 'type': 'date',
                 'render': function ( data, type ) {return dateRender(data, type, dateFormat);}
                }
            ]
        });
        $('#btnPrint').click(function() {
            $("div.PrintArea").printArea();
        });
        $('#btnPrintv').click(function() {
            $("div.PrintAreav").printArea();
        });

        function dispVehicle(item) {

            if (item.id > 0) {

                var $tr = $('<tr />');

                $tr.append($('<td>' + item.License_Number + '</td>'))
                    .append($('<td>' + item.Make + '</td>'))
                    .append($('<td>' + item.Model + '</td>'))
                    .append($('<td>' + item.Color + '</td>'))
                    .append($('<td>' + item.State_Reg + '</td>'))
                    .append($('<td><a href="GuestEdit.php?id=' + item.id + '">' + item.Patient + '</a></td>'))
                    .append($('<td>' + item.Room + '</td>'));

                $('#tbl').append($tr);

            }
        }

        $.widget( "ui.autocomplete", $.ui.autocomplete, {
            _resizeMenu: function() {
		var ul = this.menu.element;
		ul.outerWidth( Math.max(

			// Firefox wraps long text (possibly a rounding bug)
			// so we add 1px to avoid the wrapping (#7513)
			ul.width( "" ).outerWidth() + 1,
			this.element.outerWidth()
		) * 1.1 );
            }
        });
        createAutoComplete($('#schTag'), 3, {cmd: 'vehsch'},
            dispVehicle,
            false, 'ws_resc.php'
        );

        $('#mainTabs').tabs();
        $('#mainTabs').tabs("option", "active", tabReturn);
        $('#mainTabs').show();
    });
        </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <div style="float:left; margin-right: 100px; margin-top:10px;">
                <h2><?php echo $wInit->pageHeading; ?></h2>
            </div>

            <div style="clear:both;"></div>
            <div id="mainTabs" style="display:none;font-size: .9em;">
                <ul>
                    <li><a href="#tabGuest">Resident <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s</a></li>
                    <?php if ($uS->TrackAuto) { ?>
                    <li><a href="#tabVeh">Vehicles</a></li>
                    <li><a href="#tabsrch"><?php echo $labels->getString('referral', 'licensePlate', 'License Plate'); ?> Search</a></li>
                    <?php } ?>
                </ul>
                <div id="tabGuest" class="hhk-tdbox hhk-visitdialog" style=" padding-bottom: 1.5em; display:none;">
                	<form method="post" action="GuestView.php">
                		<div id="guestFilters" style="margin-bottom: 0.5em">
                			<div style="display: inline-block;">
                				<?php echo $columnSelector; ?>
                				<div id="actions" style="text-align: right;">
                					<input type="submit" name="btnHere" id="btnHere" value="Run Here">
                				</div>
                			</div>
                		</div>
                    	<?php if($guestReportMkup !== false) { ?>
                    	<div class="guestRptContent">
                            <div id="formEm">
                                <?php echo $emtableMarkup; ?>
                            </div>
                            <input type="button" value="Print" id='btnPrint' name='btnPrint' style="margin-right:.3em;"/>
                            <div class="PrintArea">
                                <?php echo $guestReportMkup; ?>
                            </div>
                        </div>
                        <?php } ?>
                    </form>
                </div>
                <div id="tabVeh" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none;">
                    <form name="formEmv" method="Post" action="GuestView.php">
                        <?php echo $emtableMarkupv; ?>
                    </form>
                    <input type="button" value="Print" id='btnPrintv' name='btnPrintv' style="margin-right:.3em;"/>
                    <div class="PrintAreav">
                        <?php echo $vehicleReportMkup; ?>
                    </div>
                </div>
                <div id="tabsrch" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none;">
                    Search <?php echo $labels->getString('referral', 'licensePlate', 'License Plate'); ?>:
                            <input type="text" id="schTag" />
                    <div id="divResults" style="margin-top:1em;">
                        <table id="tbl">
                            <thead>
                                <tr>
                                    <th><?php echo $labels->getString('referral', 'licensePlate', 'License Plate'); ?></th>
                                    <th>Make</th>
                                    <th>Model</th>
                                    <th>Color</th>
                                    <th>Registered</th>
                                    <th><?php echo $labels->getString('memberType', 'patient', 'Patient'); ?></th>
                                    <th>Room</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>  <!-- div id="contentDiv"-->
    </body>
</html>
