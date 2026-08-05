<?php
use HHK\sec\WebInit;
use HHK\sec\Session;
use HHK\HTMLControls\HTMLContainer;
use HHK\sec\Labels;
use HHK\Vite\Vite;

/**
 * DailyReport.php
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
    die("arrg!  " . $exw->getMessage());
}

// get session instance
$uS = Session::getInstance();

$labels = Labels::getLabels();

// Daily Log
$dailyLog = HTMLContainer::generateMarkup('h3', $uS->siteName . ' Daily Log'
        , array('style' => 'background-color:#D3D3D3; padding:10px;'))
        . HTMLContainer::generateMarkup('div', "<table id='daily' class='display' style='width:100%;' cellpadding='0' cellspacing='0' border='0'></table>",
        		array('id' => 'divdaily'));


?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $wInit->pageTitle; ?></title>

        <?php echo Vite::asset('resources/js/house.js'); ?>
        
        <?php echo FAVICON; ?>
        <?php echo CSSVARS; ?>

        <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", () => {
                var patientLabel = '<?php echo $labels->getString('MemberType', 'patient', 'Patient'); ?>';
                var dailyCols = [
                    {data: 'titleSort', 'visible': false },
                    {data: 'Title', title: 'Room', 'orderData': [0, 1], sortable: true, searchable:true},
                    {
                    'data': 'Status',
                    'title': 'Status',
                    'searchable': false,
                    'sortable': true,
                    'createdCell': function(td, cellData, rowData, col){
                        if(rowData.StatusColor){
                            $(td).css("background-color", rowData.StatusColor);
                        }
                    }

                },
                    {data: 'Guests', title: '<?php echo $labels->getString("MemberType", "visitor", "Guest"); ?>'+'s'},
                    {data: 'Patient_Name', title: patientLabel},
                    {data: 'Unpaid', title: 'Unpaid', className: 'hhk-justify-r'},
                    {data: 'Visit_Notes', title: 'Last Visit Note'},
                    {data: 'Notes', title: 'Room Notes'}
                ];

                $('#btnHere').button();

                let dailyTbl = $('#daily').DataTable({
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
                    },
                    layout: {
                        top1Start: {
                            "buttons": [
                            {
                                extend: "print",
                                className: "ui-corner-all",
                                autoPrint: true,
                                paperSize: "letter",
                                title: function(){
                                    return "Daily Log";
                                },
                                messageTop: function(){
                                    return "Prepared: " + dateRender(new Date().toISOString(), 'display', 'ddd, MMM D YYYY, h:mm a');
                                },
                                customize: function (win) {
                                    $(win.document.body)
                                        .css("font-size", "0.9em");

                                    $(win.document.body).find("table")
                                        //.addClass("compact")
                                        .css("font-size", "inherit");
                                }
                            },
                            {
                                text: 'Refresh',
                                action: function ( e, dt, node, config ) {
                                    dailyTbl.ajax.reload();
                                }
                            }
                            ]
                        }
                    }
                });
            });
        </script>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-tdbox" style="font-size: .9em; padding: 5px; padding-bottom:25px; margin: 10px 0;">
            <form autocomplete="off">
                <?php echo $dailyLog; ?>
                </form>
            </div>
        </div>

    </body>
</html>
