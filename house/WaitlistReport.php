<?php
use HHK\sec\WebInit;
use HHK\sec\Session;
use HHK\Config_Lite\Config_Lite;
use HHK\HTMLControls\HTMLContainer;
use HHK\sec\Labels;
use HHK\DataTableServer\SSP;

/**
 * WaitlistReport.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2021 <nonprofitsoftwarecorp.org>
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
$dbh = $wInit->dbh;
$demographics = readGenLookupsPDO($dbh, 'Demographics');

if(isset($_GET['cmd'])){
    $cmd = filter_var($_GET['cmd'],FILTER_SANITIZE_STRING);
    if($cmd == 'getDailyWaitlist'){
        $columns = array(
            array( 'db' => 'MRN',  'dt' => 'MRN' ),
            array( 'db' => 'Patient First',   'dt' => 'Patient First' ),
            array( 'db' => 'Patient Last', 'dt' => 'Patient Last'),
            array( 'db' => 'Expected_Arrival', 'dt' => 'Expected_Arrival'),
            array( 'db' => 'Expected_Departure', 'dt' => 'Expected_Departure'),
            array( 'db' => 'Room Number', 'dt' => 'Room Number'),
            array( 'db' => 'Referral Agent', 'dt' => 'Referral Agent'),
            array( 'db' => 'Ethnicity', 'dt' => 'Ethnicity'),
            array( 'db' => 'Income_Bracket', 'dt' => 'Income_Bracket'),
            array( 'db' => 'Age_Bracket', 'dt' => 'Age_Bracket'),
            array( 'db' => 'Waitlist Notes', 'dt'=> 'Waitlist Notes')
        );
        echo json_encode(SSP::simple($_REQUEST, $wInit->dbh, 'vdaily_waitlist', 'idReservation', $columns));
        exit;
    }
}

// Daily Waitlist
$waitlist = HTMLContainer::generateMarkup('h3', $uS->siteName . ' Daily Waitlist'
        , array('style' => 'background-color:#D3D3D3; padding:10px;'))
        . HTMLContainer::generateMarkup('div', "<table id='waitlist' class='display' style='width:100%;' cellpadding='0' cellspacing='0' border='0'></table>",
        		array('id' => 'divwaitlist'));


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
        <?php echo NOTY_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
<script type="text/javascript">
    $(document).ready(function() {
        var patientLabel = '<?php echo $labels->getString('MemberType', 'patient', 'Patient'); ?>';
        var mrnLabel = '<?php echo $labels->getString('Hospital', 'MRN', 'MRN'); ?>';
        var roomLabel = '<?php echo $labels->getString('Hospital', 'roomNumber', 'Room'); ?>';
        var referralAgentLabel = '<?php echo $labels->getString('Hospital', 'referralAgent'); ?>';
        var ethnicityLabel = '<?php echo (isset($demographics['Ethnicity']['Description']) ? $demographics['Ethnicity']['Description'] : 'Ethnicity'); ?>';
        var incomeBracketLabel = '<?php echo (isset($demographics['Income_Bracket']['Description']) ? $demographics['Income_Bracket']['Description'] : 'Income Bracket'); ?>';
        var ageBracketLabel = '<?php echo (isset($demographics['Age_Bracket']['Description']) ? $demographics['Age_Bracket']['Description'] : 'Age Bracket'); ?>';

        var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>';
        var waitlistCols = [
            {data: 'MRN', 'title': mrnLabel },
            {data: 'Patient First', title: patientLabel + ' First'},
            {data: 'Patient Last', title: patientLabel + ' Last'},
            {
            	data: 'Expected_Arrival',
            	title: 'Anticipated Arrival',
            	render: function(data,type){
            		return dateRender(data, type, dateFormat);
            	}
            },
            {
            	data: 'Expected_Departure',
            	title: 'Anticipated Departure',
            	render: function(data,type){
            		return dateRender(data, type, dateFormat);
            	}
            },
            {data: 'Room Number', title: roomLabel},
            {data: 'Referral Agent', title: referralAgentLabel},
            {data: 'Ethnicity', title: ethnicityLabel},
            {data: 'Income_Bracket', title: incomeBracketLabel},
            {data: 'Age_Bracket', title: ageBracketLabel},
            {data: 'Waitlist Notes', title: 'Waitlist Notes'}
        ];

        var $table = $('#waitlist').DataTable({
            "dom": '<"top ui-toolbar ui-helper-clearfix"if>Brt<"bottom ui-toolbar ui-helper-clearfix"lp><"clear">',
            "displayLength": 50,
            "lengthMenu": [[5, 25, 50, -1], [5, 25, 50, "All"]],
            "order": [[ 3, 'asc' ]],
            "processing": true,
            "deferRender": true,
           ajax: {
               url: 'WaitlistReport.php?cmd=getDailyWaitlist',
           },
           "columns": waitlistCols,
             buttons: [
                {
                    text: 'Reload',
                    className: 'ui-corner-all',
                    action: function ( e, dt, node, config ) {
                        dt.ajax.reload();
                    }
                },
                {
                    extend: 'pdf',
                    text: 'Download PDF',
                    className: 'ui-corner-all',
                    filename: 'Daily Waitlist Report ' + dateRender(new Date().toISOString(), 'display', 'MMM D, YYYY'),
                    orientation: 'landscape',
                    title: function(){
                    	var siteName = '<?php echo $uS->siteName; ?>';
                    	return siteName + '\r\nWaitlist as of ' + dateRender(new Date().toISOString(), 'display', 'ddd, MMM D YYYY, h:mm a');
                    }
                }
            ]
        });
        
        $table.rows().every( function ( rowIdx, tableLoop, rowLoop ) {
    		var data = this.data();
    		console.log(data);
    		this
        		.child(
            		'test'
        		)
        	.show();
		} );
        
        $('#vcategory').append($table.buttons().container());
        
    });
 </script>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail hhk-tdbox hhk-visitdialog" style="clear:left;  padding:10px;">
            </div>
            <div style="clear:both;"></div>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-tdbox" style="font-size: .9em; padding: 5px; padding-bottom:25px; margin: 10px 0;">
                <?php echo $waitlist; ?>
            </div>
        </div>
    </body>
</html>
