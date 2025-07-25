<?php

use HHK\sec\{Session, WebInit, Labels};
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

$dataTableWrapper = '';

$reservationReport = new ReservationReport($dbh, $_REQUEST);

if (isset($_POST['btnHere-' . $reservationReport->getInputSetReportName()])) {
    $dataTableWrapper = $reservationReport->generateMarkup();
}

if (isset($_POST['btnExcel-' . $reservationReport->getInputSetReportName()])) {
    ini_set('memory_limit', "280M");
    $reservationReport->downloadExcel("reservReport");
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
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
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo REPORTFIELDSETS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>

        <script type="text/javascript">
            $(document).ready(function() {
                var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
                var columnDefs = $.parseJSON('<?php echo json_encode($reservationReport->colSelector->getColumnDefs()); ?>');

                <?php echo $reservationReport->filter->getTimePeriodScript(); ?>;
                <?php echo $reservationReport->generateReportScript(); ?>

                function viewInsurance(idName, eventTarget, detailDiv) {
                    "use strict";
                    detailDiv.empty();
                    $.post('ws_resc.php', { cmd: 'viewInsurance', idName: idName },
                        function (data) {
                            if (data) {
                                try {
                                    data = $.parseJSON(data);
                                } catch (err) {
                                    alert("Parser error - " + err.message);
                                    return;
                                }
                                if (data.error) {
                                    if (data.gotopage) {
                                        window.location.assign(data.gotopage);
                                    }
                                    flagAlertMessage(data.error, 'error');
                                    return;
                                }

                                if (data.markup) {
                                    var contr = $(data.markup);

                                    $('body').append(contr);
                                    contr.position({
                                        my: 'left top',
                                        at: 'left bottom',
                                        of: "#" + eventTarget
                                    });
                                }
                            }
                        });
                }

                $(document).mousedown(function (event) {
                    var target = $(event.target);
                    if ($('div#insDetailDiv').length > 0 && target[0].id !== 'insDetailDiv' && target.parents("#" + 'insDetailDiv').length === 0) {
                        $('div#insDetailDiv').remove();
                    }
                });
                
                var detailDiv = $("<div>").attr('id', 'insDetailDiv');
                $("body").append(detailDiv);
                $('#tblreservrpt').on('click', '.insAction', function (event) {
                    viewInsurance($(this).data('idname'), event.target.id, detailDiv);
                });

            });
         </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <?php echo $reservationReport->generateFilterMarkup() . $dataTableWrapper; ?>
        </div>
    </body>
</html>
