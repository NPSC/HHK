<?php

use HHK\House\Report\GuestCensusReport;
use HHK\sec\{Session, WebInit, Labels};

/**
 * GuestCensusReport.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
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

$report = new GuestCensusReport($dbh, $_REQUEST);

if (isset($_POST['btnHere-' . $report->getInputSetReportName()])) {
    $dataTableWrapper = $report->generateMarkup();
}

if (isset($_POST['btnExcel-' . $report->getInputSetReportName()])) {
    ini_set('memory_limit', "280M");
    $report->downloadExcel("GuestCensusReport");
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
        <?php echo BOOTSTRAP_ICONS_CSS; ?>

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
                var columnDefs = $.parseJSON('<?php echo json_encode($report->colSelector->getColumnDefs()); ?>');

                // PDF button: summary lines under the title, and the guest roster in red/black.
                // pdfmake can't render HTML, so each party in the print markup becomes a colored text run.
                var pdfCustomize = function (doc, dt) {

                    var summary = [];
                    $('#repSummary p').each(function () {
                        summary.push($(this).text());
                    });
                    $('#repSummary th.tdlabel').each(function () {
                        summary.push($(this).text() + ': ' + $(this).next('td').text());
                    });

                    var tableIdx = doc.content.findIndex(function (c) { return c.table; });
                    doc.content.splice(tableIdx, 0, {text: summary.join('\n'), margin: [0, 0, 0, 8]});
                    tableIdx++;

                    doc.pageMargins = [20, 20, 20, 20];
                    doc.defaultStyle.fontSize = 8;
                    doc.styles.tableHeader.fontSize = 7;
                    doc.styles.tableBodyEven.alignment = 'center';
                    doc.styles.tableBodyOdd.alignment = 'center';

                    var body = doc.content[tableIdx].table.body;
                    var rosterCol = body[0].findIndex(function (c) { return c.text === 'Guest Roster'; });
                    var dateCol = body[0].findIndex(function (c) { return c.text === 'Date'; });

                    // Narrow count columns and one-line dates leave the rest of the width to the roster.
                    doc.content[tableIdx].table.widths = body[0].map(function (c, i) {
                        return (i === rosterCol ? '*' : (i === dateCol ? 'auto' : 36));
                    });

                    if (dateCol >= 0) {
                        body.forEach(function (row) { row[dateCol].noWrap = true; });
                    }

                    if (rosterCol < 0) {
                        return;
                    }

                    var dataCol = dt.columns().header().toArray().findIndex(function (h) { return $(h).text() === 'Guest Roster'; });
                    var rows = dt.rows({search: 'applied', order: 'applied'}).data();

                    for (var i = 1; i < body.length; i++) {

                        var markup = $('<div>').html(rows[i - 1][dataCol]).children('[data-print]').attr('data-print');

                        if (!markup) {
                            continue;
                        }

                        var runs = [];
                        $('<div>').html(markup).contents().each(function () {
                            var style = (this.nodeType === 1 ? this.getAttribute('style') || '' : '');
                            var color = style.match(/color:\s*(#[0-9a-fA-F]{6})/);
                            runs.push({text: $(this).text(), color: (color ? color[1] : undefined), bold: /font-weight:\s*bold/.test(style)});
                        });

                        body[i][rosterCol] = {text: runs, style: body[i][rosterCol].style, alignment: 'left'};
                    }
                };

                <?php echo $report->generateReportScript(); ?>

            });
         </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <?php echo $report->generateFilterMarkup() . $dataTableWrapper; ?>
        </div>
    </body>
</html>
