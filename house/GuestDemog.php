<?php
use HHK\sec\WebInit;
use HHK\sec\Session;
use HHK\DataTableServer\SSP;
use HHK\Tables\Name\NameRS;
use HHK\Tables\Name\NameDemogRS;
use HHK\Tables\EditRS;
use HHK\AuditLog\NameLog;
use HHK\sec\Labels;

/**
 * GiestDemog.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require("homeIncludes.php");

$wInit = new WebInit();

$dbh = $wInit->dbh;

$uS = Session::getInstance();

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;
$menuMarkup = $wInit->generatePageMenu();

$demos = array();

$whDemos = '';
$columns = array(
            array("db"=>'idName', "dt"=>"id"),
            array("db"=>'Name_Full', "dt"=>"Name"),
            array("db"=>'Patient_Name', "dt"=>Labels::getString('memberType', 'patient', 'Patient') . " Name")
           );

foreach (readGenLookupsPDO($dbh, 'Demographics') as $d) {

    if (strtolower($d[2]) == 'y') {

        if ($whDemos == '') {
            $whDemos = ' (';
        } else {
            $whDemos .= ' or ';
        }

        if ($d[0] == 'Gender') {
            $whDemos .= " `Gender` = '' ";
            $columns[] = array("db"=>"Gender", 'dt'=>"Gender");
        } else {
            $whDemos .= " `" . $d[0] . "` = '' ";
            $columns[] = array("db"=>$d[0], "dt"=>$d[0]);
        }

        $demos[$d[0]] = array(
            'title' => $d[1],
            'list' => removeOptionGroups(readGenLookupsPDO($dbh, $d[0], 'Order')),
        );
    }
}

if(strlen($whDemos) > 0){
    $whDemos .= ") ";
}

function getDemographicField($tableName, $recordSet) {

    if ($tableName == 'Gender') {
        return $recordSet->Gender;
    } else {

        foreach ($recordSet as $k => $v) {

            if ($k == $tableName) {
                return $v;
            }
        }
    }

    return NULL;
}

function getMissingDemogs($dbh, $columns, $whDemos){
    try{
        return SSP::complex($_REQUEST, $dbh, 'vguest_demog', 'idName', $columns, null, $whDemos);
    }catch(\Exception $e){
        return array("error"=>"An error occurred while loading DataTable: " . $e->getMessage());
    }

}

function saveMissingDemogs($dbh, $uS, $demos){
    try{

        foreach ($demos as $j => $d) {

            if (isset($_POST['sel' . $j])) {

                foreach ($_POST['sel' . $j] as $k => $v) {

                    $id = intval(filter_var($k, FILTER_SANITIZE_NUMBER_INT), 10);

                    if ($j == 'Gender') {
                        $nameRS = new NameRS();
                    } else {
                        $nameRS = new NameDemogRS();
                    }

                    $nameRS->idName->setStoredVal($id);
                    $rows = EditRS::select($dbh, $nameRS, array($nameRS->idName));

                    if (count($rows) === 1) {

                        EditRS::loadRow($rows[0], $nameRS);

                        $dbField = getDemographicField($j, $nameRS);

                        if (isset($_POST['cbUnkn'][$k])) {
                            $dbField->setNewVal('z');
                        } else {
                            $dbField->setNewVal(filter_var($v, FILTER_SANITIZE_STRING));
                        }

                        $nameRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                        $nameRS->Updated_By->setNewVal($uS->username);

                        $numRows = EditRS::update($dbh, $nameRS, array($nameRS->idName));

                        if ($numRows > 0) {
                            NameLog::writeUpdate($dbh, $nameRS, $nameRS->idName->getStoredVal(), $uS->username);
                        }
                    }
                }
            }
        }

        return array("success"=> "Demographics updated successfully.");

    }catch(\Exception $e){
        return array("error"=>"Error: " . $e->getMessage());
    }
}

$cmd = isset($_REQUEST['cmd']) ? $_REQUEST['cmd']: '';
$events = '';

if ($cmd){

    switch ($cmd){
        case 'getMissingDemog':
            $events = getMissingDemogs($dbh, $columns, $whDemos);
            break;
        case 'save':
            $events = saveMissingDemogs($dbh, $uS, $demos);
            break;
        default:
            $events = array("error" => "Bad Command: \"" . $cmd . "\"");
    }

    if (is_array($events)) {

        $json = json_encode($events);

        if ($json !== FALSE) {
            echo ($json);
        } else {
            $events = array("error" => "PHP json encoding error: " . json_last_error_msg());
            echo json_encode($events);
        }

    } else {
        echo $events;
    }

    exit;
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_UI_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
		<?php echo MULTISELECT_CSS; ?>
		<?php echo NAVBAR_CSS; ?>

		<style>
		  .fixedHeader-floating, .fixedHeader-locked {
		      font-size: 0.8em !important;
		      font-family: Lucida Grande,Lucida Sans,Arial,sans-serif !important;
		  }

		  .fixedHeader-floating *, .fixedHeader-locked * {
		      font-size: 1em;
		  }
		</style>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MULTISELECT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MISSINGDEMOG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript">
    $(document).ready(function() {
        "use strict";
        $('#btnnotind').button();

    });
        </script>
    </head>
    <body <?php if ($testVersion) {echo "class='testbody'";} ?> >
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-tdbox d-inline-block mt-3 p-2" style="font-size:.8em;padding:15px;margin-top:15px;">
            	<table id="dataTbl"></table>
            </div>
            <input type="hidden" id="columns" value='<?php echo json_encode($columns); ?>'>
            <input type="hidden" id="demos" value='<?php echo json_encode($demos); ?>'>
        </div>
    </body>
</html>
