<?php

use HHK\Config_Lite\Config_Lite;
use HHK\HTMLControls\HTMLContainer;
use HHK\Member\Address\Address;
use HHK\sec\{Session, WebInit};
use HHK\House\Report\ReportFilter;
use HHK\House\Report\GuestDemogReport;
use HHK\sec\Labels;

/**
 * occDemo.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require('homeIncludes.php');

$wInit = new webInit();

$uS = Session::getInstance();

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;
$menuMarkup = $wInit->generatePageMenu();

$houseAddr = Address::getHouseAddress($dbh);
$zip = (!empty($houseAddr['zip']) ? $houseAddr['zip'] : $uS->Zip_Code);


$report = "";

$headerTable = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));


// Get labels
$labels = Labels::getLabels();

$hospitalSelections = array('');
$assocSelections = array('');
$calSelection = '22';
$whichGuests = 'new';
$guestsvspatients = 'guestsandpatients';
$title = '';

$year = date('Y');
$txtStart = '';
$txtEnd = '';
$status = '';
$statsTable = '';
$start = '';
$end = '';
$errorMessage = '';
$dateInterval = new DateInterval('P1M');

$filter = new ReportFilter();
$filter->createTimePeriod(date('Y'), '19', $uS->fy_diff_Months, array(ReportFilter::DATES));
$filter->createHospitals();
$filter->createResourceGroups(readGenLookupsPDO($dbh, 'Room_Group'), $uS->CalResourceGroupBy);
$filter->createDiagnoses($dbh);

if (isset($_POST['rbAllGuests'])) {
    $whichGuests = filter_var($_POST['rbAllGuests'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}
if (isset($_POST['rbGuestsPatients'])) {
    $guestsvspatients = filter_var($_POST['rbGuestsPatients'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}
$filterBtnMkup = GuestDemogReport::generateFilterBtnMarkup($whichGuests, $guestsvspatients);

// Run Selected year Report?
if (isset($_POST['btnSmt'])) {

    $filter->loadSelectedTimePeriod();
    $filter->loadSelectedHospitals();
    $filter->loadSelectedResourceGroups();
    $filter->loadSelectedDiagnoses();

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

    $roomGroupBy = $filter->getSelectedResourceGroups();

    $whDiags = '';
    foreach($filter->getSelectedDiagnoses() as $d){
        if ($d != '') {
            if ($whDiags == '') {
                $whDiags .= "'".$d."'";
            } else {
                $whDiags .= ",'". $d."'";
            }
        }
    }

    if ($whDiags != '') {
        $whDiags = " and hs.Diagnosis in (".$whDiags.") ";
    }

    $whPatient = "";
    if($guestsvspatients == "patients"){
        $whPatient = "and hs.idPatient = s.idName";
    }


    $report = GuestDemogReport::demogReport($dbh, $filter->getReportStart(), $filter->getQueryEnd(), $whHosp, $whAssoc, $whDiags, $whichGuests, $whPatient, $zip, $roomGroupBy);

    $title = HTMLContainer::generateMarkup('h3', $uS->siteName . ' ' . $labels->getString('MemberType', 'visitor', 'Guest'). ' Demographics compiled on ' . date('D M j, Y'), array('style'=>'margin-top: .5em;'));
    $title .= HTMLContainer::generateMarkup('p', 'Report Interval: ' . date('M j, Y', strtotime($filter->getReportStart())) . ' Thru ' . date('M j, Y', strtotime($filter->getReportEnd())));
}


// Setups for the page.
$timePeriodMarkup = $filter->timePeriodMarkup()->generateMarkup();
$hospitalMarkup = $filter->hospitalMarkup()->generateMarkup();
$roomGroupMarkup = $filter->resourceGroupsMarkup()->generateMarkup();
$diagnosisMarkup = $filter->diagnosisMarkup()->generateMarkup();

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <?php echo CSSVARS; ?>
        <style>
            .hhk-tdTitle {
                background-color: #F2F2F2;
            }
        </style>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript">
    // Init jQuery and the page blocker.
    $(document).ready(function() {
        $('#btnSmt, #btnCkZip').button();
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
                    $('#zipDistAnswer').text('Zip Code not found.').closest('tr').show();
                    return;
                } else if (data.success) {
                    $('#zipDistAnswer').text(data.success + ' nautical miles').closest('tr').show();
                }
            });
        });

        // disappear the pop-up nameDetails.
        $(document).mousedown(function (event) {
            var target = $(event.target);
            if ($('div#nameDetails').length > 0 && target[0].id !== 'nameDetails' && target.parents("#" + 'nameDetails').length === 0) {
                $('div#nameDetails').remove();
            }
        });

        $('.getNameDetails').click(function(){
            var detailsbtn = $(this);
            let idNames = $(this).data('idnames');
            let title = $(this).data('title');
            $.ajax({
                url: 'ws_resc.php',
                method: 'post',
                data: {
                    cmd: "getNameDetails",
                    idNames: idNames,
                    title: title
                },
                dataType: "json",
                success: function(data){
                    if (data.error) {
                        if (data.gotopage) {
                            window.location.assign(data.gotopage);
                        }
                        flagAlertMessage(data.error, 'error');
                        return;
                    }
                    if(data.resultMkup){
                        var contr = $(data.resultMkup).addClass('nameDetails');

                        $('body').append(contr);
                        contr.position({
                            my: 'left top',
                            at: 'left bottom',
                            of: detailsbtn
                        });
                    }
                }

            });
        });

        <?php echo $filter->getTimePeriodScript(); ?>;
    });
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?> >
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?> Demography Report</h2>
            <div id="vreport" class="ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-visitdialog filterWrapper">
                <form action="occDemo.php" method="post">
                	<div class="hhk-flex" id="filterSelectors">
                        <?php
                            echo $timePeriodMarkup;

                            if (count($filter->getHospitals()) > 1) {
                                echo $hospitalMarkup;
                            }

                            if(count($filter->getDiagnoses()) > 1) {
                                echo $diagnosisMarkup;
                            }

                            echo $roomGroupMarkup;
                        ?>

                        <div style="margin-left:130px;">
                            <fieldset class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox"><legend>Nautical Distance Calculator</legend>
                            <table>
                            <tr><th>From</th><th>To</th></tr>
                            <tr><td><input type="text" id="txtZipFrom" value="<?php echo $zip ?>" size="5"/></td><td><input type="text" id="txtZipTo" value="" size="5"/></td></tr>
                            <tr style="display:none"><td colspan="2"><span id="zipDistAnswer"></span></td></tr><tr><td colspan="2"><input type="button" id="btnCkZip" value="Get Zip Distance" class="ui-button ui-corner-all ui-widget"/></td></tr>
                        </table></fieldset>
                        </div>
                    </div>
					<?php echo $filterBtnMkup; ?>
                </form>
            </div>
            <?php if($report != ""){ ?>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-tdbox" id="hhk-reportWrapper">
                <?php echo $title . $report; ?>
            </div>
            <?php } ?>
        </div>
    </body>
</html>
