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
use HHK\House\Report\CurrentGuestReport;
use HHK\House\Report\GuestVehicleReportOld;
use HHK\House\Report\VehiclesReport;
use HHK\House\Report\BirthdayReport;

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

$currentGuestReport = new CurrentGuestReport($dbh, $_REQUEST);
$birthdayReport = new BirthdayReport($dbh, $_REQUEST);
$vehicleReport = new VehiclesReport($dbh, $_REQUEST);

$resultMessage = "";

$guestReportMkup = '';
$birthdayReportMkup = '';
$tab = 0;

if (isset($_POST['btnHere-' . $currentGuestReport->getInputSetReportName()])){
    $guestReportMkup = $currentGuestReport->generateMarkup();
}

if (isset($_POST['btnExcel-' . $currentGuestReport->getInputSetReportName()])) {
    $currentGuestReport->downloadExcel("CurrentGuestsReport");
}

if (isset($_POST['btnHere-' . $birthdayReport->getInputSetReportName()])){
    $tab = 1;
    $birthdayReportMkup = $birthdayReport->generateMarkup();
}

if (isset($_POST['btnExcel-' . $birthdayReport->getInputSetReportName()])) {
    $tab = 1;
    $birthdayReport->downloadExcel("BirthdayReport");
}

//vehicle report
$vehicleReportMkup = '';
if ($uS->TrackAuto) {
    $vehicleReportMkup = $vehicleReport->generateMarkup() . $vehicleReport->generateEmailDialog();
}


$vehicleMessage = '';
$emtableMarkupv = '';


?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <?php echo CSSVARS; ?>

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
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript">
    $(document).ready(function () {
        "use strict";


        var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM d, YYYY"); ?>';
        var tabReturn = '<?php echo $tab; ?>';

        $('#btnEmail, #btnPrint, #btnEmailv, #btnPrintv').button();

        <?php echo $currentGuestReport->generateReportScript() .
                $birthdayReport->generateReportScript() .
                $vehicleReport->generateReportScript() ?>

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
            <h2><?php echo $wInit->pageHeading; ?></h2>

            <div id="mainTabs" style="display:none;font-size: .9em;">
                <ul>
                    <li><a href="#tabGuest">Resident <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s</a></li>
                    <li><a href="#tabBirthday">Birthday Report</a></li>
                    <?php if ($uS->TrackAuto) { ?>
                    <li><a href="#tabVeh">Vehicles</a></li>
                    <li><a href="#tabsrch"><?php echo $labels->getString('referral', 'licensePlate', 'License Plate'); ?> Search</a></li>
                    <?php } ?>
                </ul>
                <div id="tabGuest" class="hhk-tdbox hhk-visitdialog" style=" padding-bottom: 1.5em; display:none;">
                	<?php echo $currentGuestReport->generateFilterMarkup() . $guestReportMkup; ?>
                </div>
                <div id="tabBirthday" class="hhk-tdbox hhk-visitdialog" style=" padding-bottom: 1.5em; display:none;">
                	<?php echo $birthdayReport->generateFilterMarkup() . $birthdayReportMkup; ?>
                </div>
                <div id="tabVeh" class="hhk-tdbox" style="padding-bottom: 1.5em; display:none;">
                    <?php echo $vehicleReportMkup; ?>
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
