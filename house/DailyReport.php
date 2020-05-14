<?php
/**
 * DailyReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");
require (DB_TABLES . 'PaymentsRS.php');
require (DB_TABLES . 'visitRS.php');
require (HOUSE . 'VisitCharges.php');
require (HOUSE . 'RoomReport.php');
require (CLASSES . 'Purchase/Item.php');


try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die("arrg!  " . $exw->getMessage());
}

// get session instance
$uS = Session::getInstance();

$labels = new Config_Lite(LABEL_FILE);

// Daily Log
$dailyLog = HTMLContainer::generateMarkup('h3', $uS->siteName . ' Daily Log'
        , array('style' => 'background-color:#D3D3D3; padding:10px;'))
        . HTMLContainer::generateMarkup('div', "<table id='daily' class='display' style='width:100%;' cellpadding='0' cellspacing='0' border='0'></table>", array('id' => 'divdaily'));


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
        <script type="text/javascript" src="<?php echo MD5_JS; ?>"></script>
<script type="text/javascript">
    $(document).ready(function() {
        var patientLabel = '<?php echo $labels->getString('MemberType', 'patient', 'Patient'); ?>';
        var dailyCols = [
            {data: 'titleSort', 'visible': false },
            {data: 'Title', title: 'Room', 'orderData': [0, 1], sortable: true, searchable:true},
            {data: 'Status', title: 'Status'},
            {data: 'Guests', title: 'Guests'},
            {data: 'Patient_Name', title: patientLabel},
            {data: 'Unpaid', title: 'Unpaid', className: 'hhk-justify-r'},
            {data: 'Visit_Notes', title: 'Last Visit Note'},
            {data: 'Notes', title: 'Room Notes'}
        ];

        $('#btnHere').button();

        $('#daily').DataTable({
            "dom": '<"top"if>rt<"bottom"lp><"clear">',
            "displayLength": 50,
            "lengthMenu": [[25, 50, -1], [25, 50, "All"]],
            "order": [[ 0, 'asc' ]],
            "processing": true,
            "deferRender": true,
           ajax: {
               url: 'ws_resc.php?cmd=getHist&tbl=daily',
               dataSrc: 'daily'
           },
           "columns": dailyCols,
           "infoCallback": function( settings, start, end, max, total, pre ) {
                return "Prepared: " + dateRender(new Date().toISOString(), 'display', 'ddd, MMM D YYYY, h:mm a');
            }
        });

        $('#printButton').button().click(function() {
            $("#divdaily").printArea();
        });
    });
 </script>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="clear:left;  padding:10px;">
                <form id="fcat" action="DailyReport.php" method="post">
                    <input type="submit" name="btnHere" id="btnHere" value="Reload"/>

                    <input id="printButton" value="Print" type="button" style="margin-left:3em;"/>
                </form>
            </div>
            <div style="clear:both;"></div>
            <div class="ui-widget ui-widget-content hhk-tdbox" style="font-size: .9em; padding: 5px; padding-bottom:25px;">
                <?php echo $dailyLog; ?>
            </div>
        </div>
                
    </body>
</html>
