<?php

use HHK\Config_Lite\Config_Lite;
use HHK\HTMLControls\HTMLContainer;
use HHK\sec\{Session, WebInit};
use HHK\House\Report\ReportFilter;
use HHK\House\Report\GuestReport;
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

$zip = $uS->Zip_Code;


$report = "";

$headerTable = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));


// Get labels
$labels = Labels::getLabels();

$hospitalSelections = array('');
$assocSelections = array('');
$calSelection = '22';
$newGuestsChecked = 'checked="checked"';
$allGuestsStartedChecked = '';
$allGuestsStayedChecked = '';
$whichGuests = 'new';
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


// Run Selected year Report?
if (isset($_POST['btnSmt'])) {

    $filter->loadSelectedTimePeriod();
    $filter->loadSelectedHospitals();


    if (isset($_POST['rbAllGuests'])) {
        $whichGuests = filter_var($_POST['rbAllGuests'], FILTER_SANITIZE_STRING);

        if ($whichGuests == 'allStarted') {
            $newGuestsChecked = '';
            $allGuestsStartedChecked = 'checked="checked"';
        }else if($whichGuests == 'allStayed') {
            $newGuestsChecked = '';
            $allGuestsStartedChecked = '';
            $allGuestsStayedChecked = 'checked="checked"';
        } else {
            $whichGuests = 'new';
        }
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

    $report = GuestReport::demogReport($dbh, $filter->getReportStart(), $filter->getReportEnd(), $whHosp, $whAssoc, $whichGuests, $zip);

    $title = HTMLContainer::generateMarkup('h3', $uS->siteName . ' ' . $labels->getString('MemberType', 'visitor', 'Guest'). ' Demographics compiled on ' . date('D M j, Y'), array('style'=>'margin-top: .5em;'));

}


// Setups for the page.
$timePeriodMarkup = $filter->timePeriodMarkup()->generateMarkup(array('style'=>'float: left;'));
$hospitalMarkup = $filter->hospitalMarkup()->generateMarkup(array('style'=>'float: left;margin-left:5px;'));

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo NOTY_CSS; ?>
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
        <script type="text/javascript">
    // Init j-query and the page blocker.
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
                    $('#zipDistAnswer').text('Zip Code not found.');
                    return;
                } else if (data.success) {
                    $('#zipDistAnswer').text(data.success + ' miles');
                }
            });
        });
        <?php echo $filter->getTimePeriodScript(); ?>;
        $('#vreport').show();
    });
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?> >
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?> Demography Report</h2>
            <div id="vreport" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="display:none; clear:left; min-width: 400px; padding:10px;">
                <form action="occDemo.php" method="post">
                    <?php
                        echo $timePeriodMarkup;

                        if (count($filter->getHospitals()) > 1) {
                            echo $hospitalMarkup;
                        }
                    ?>

                    <div style="float:right;margin-left:130px;">
                        <fieldset class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox"><legend>Distance Calculator</legend>
                        <table>
                        <tr><th>From</th><th>To</th></tr>
                        <tr><td><input type="text" id="txtZipFrom" value="<?php echo $zip ?>" size="5"/></td><td><input type="text" id="txtZipTo" value="" size="5"/></td></tr>
                        <tr><td colspan="2"><span id="zipDistAnswer"></span></td></tr><tr><td colspan="2"><input type="button" id="btnCkZip" value="Get Zip Distance"/></td></tr>
                    </table></fieldset>
                    </div>
                    <div style="clear:both;"></div>
                    <table style="margin-top:10px; width: 100%;">
                        <tr>
                            <td>
                                <input type="radio" name="rbAllGuests" id="rbnewG" value="new" <?php echo $newGuestsChecked; ?> style="margin-right:.3em;" /><label for="rbnewG" style="margin-right:.5em;">First Time <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s Only </label>
                                <input type="radio" name="rbAllGuests" id="rbAllGStartStay" value="allStarted" <?php echo $allGuestsStartedChecked; ?> style="margin-right:.3em;" /><label for="rbAllGStartStay" style="margin-right:.5em;">All <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s who started stay </label>
                                <input type="radio" name="rbAllGuests" id="rbAllGStay" value="allStayed" <?php echo $allGuestsStayedChecked; ?> style="margin-right:.3em;" /><label for="rbAllGStay">All <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s who stayed </label>
                            </td>
                            <td>
                                <input type="submit" id="btnSmt" name="btnSmt" value="Run Report" />
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
            <div style="clear:both;"></div>
            <div id="printArea" style="margin-top:10px;margin-bottom:10px;font-size: .9em;">
                <?php echo $title . $report; ?>
            </div>
        </div>
    </body>
</html>
