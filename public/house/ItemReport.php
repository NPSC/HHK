<?php
use HHK\House\Report\ItemReport;
use HHK\sec\WebInit;
use HHK\sec\Session;
use HHK\sec\Labels;
use HHK\Vite\Vite;

/**
 * ItemReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");


try {
    $wInit = new WebInit();
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

$report = new ItemReport($dbh, $_REQUEST);

if (isset($_POST['btnHere-' . $report->getInputSetReportName()])) {
    $dataTableWrapper = $report->generateMarkup();
}

if (isset($_POST['btnExcel-' . $report->getInputSetReportName()])) {
    ini_set('memory_limit', "280M");
    $report->downloadExcel("ItemReport");
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>

        <?php echo Vite::asset('resources/js/house.js'); ?>
        
        <?php echo FAVICON; ?>
        <?php echo CSSVARS; ?>

        <script type="text/javascript" src="<?php echo REPORTFIELDSETS_JS; ?>" defer></script>

        <script type="text/javascript">
            function invoiceAction(idInvoice, action, eid, container, show) {
                $.post('ws_resc.php', {cmd: 'invAct', iid: idInvoice, x:eid, action: action, 'sbt':show},
                function(data) {
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
                            //flagAlertMessage(data.error, true);
                            return;
                        }
                        if (data.markup) {
                            var contr = $(data.markup);
                            if (container != undefined && container != '') {
                                $(container).append(contr);
                            } else {
                                $('body').append(contr);
                            }
                            $('body').append(contr);
                            contr.position({
                                my: 'left top',
                                at: 'left bottom',
                                of: "#" + data.eid
                            });
                        }
                    }
                });
            }
            var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
            document.addEventListener("DOMContentLoaded", () => {

                <?php echo $report->generateReportScript(); ?>

                // disappear the pop-up room chooser.
                $(document).mousedown(function (event) {
                    var target = $(event.target);
                    if ($('div#pudiv').length > 0 && target[0].id !== 'pudiv' && target.parents("#" + 'pudiv').length === 0) {
                        $('div#pudiv').remove();
                    }
                });

                $(document).on('click', '.invAction', function (event) {
                    invoiceAction($(this).data('iid'), 'view', event.target.id, '', true);
                });

            });
        </script>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
        <h2><?php echo $wInit->pageHeading; ?></h2>
            <?php echo $report->generateFilterMarkup() . $dataTableWrapper; ?>
        </div>
    </body>
</html>
