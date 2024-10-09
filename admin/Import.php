<?php

use HHK\CreateMarkupFromDB;
use HHK\History;
use HHK\sec\{Session, WebInit};
use HHK\SysConst\{WebSiteCode};
use HHK\Admin\Import\Upload;
use HHK\Admin\Import\ImportMarkup;
use HHK\Admin\Import\Import;

/**
 * Import.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");

$wInit = new webInit();

$dbh = $wInit->dbh;


$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();

$uS = Session::getInstance();

//load import in progress
$import = new ImportMarkup($dbh);

if(isset($_GET['includeTbl'])){
    $importTbl = $import->generateMkup(true);
}else{
    $importTbl = $import->generateMkup();
}

if(isset($_FILES['csvFile'])){

    try{
        $upload = new Upload($dbh, $_FILES['csvFile']);

        if($upload->upload()){
            $import = new ImportMarkup($dbh);
            $importTbl = $import->generateMkup();
        }

    }catch (\Exception $e){
        $errorMsg = $e->getMessage();
    }
}

if(isset($_POST["undo"])){
    $import = new Import($dbh);
    $return = $import->undoImport();
    if(is_array($return)){
        echo json_encode($return);
        exit;
    }else{
        echo json_encode(array("error"=>"Invalid Response"));
        exit;
    }
}

if(isset($_POST["startImport"]) && isset($_POST["limit"])){
    $limit = intval(filter_var($_POST["limit"], FILTER_SANITIZE_NUMBER_INT), 10);
    $import = new Import($dbh);
    $return = $import->startImport($limit);
    if(is_array($return)){
        echo json_encode($return);
        exit;
    }else{
        echo json_encode(array("error"=>"Invalid Response"));
        exit;
    }
}

$cmd = "";
if(filter_has_var(INPUT_POST, "cmd") && $cmd = filter_input(INPUT_POST, "cmd", FILTER_SANITIZE_FULL_SPECIAL_CHARS)){
	$import = new Import($dbh);
	switch($cmd){
		case 'makeRooms':
			$return = $import->makeMissingRooms();
			break;
		case 'makeDoctors':
			$return = $import->makeMissingDoctors();
			break;
		case 'makeHosps':
			$return = $import->makeMissingHospitals();
			break;
		case 'makeDiags':
			$return = $import->makeMissingDiags();
			break;
		case 'makeEthnicities':
			$return = $import->makeMissingEthnicities();
			break;
		case 'makeGenders':
			$return = $import->makeMissingGenders();
			break;
		default:
			exit;
	}

	if(is_array($return)){
        echo json_encode($return);
        exit;
    }else{
        echo json_encode(array("error"=>"Invalid Response"));
        exit;
    }

}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>

		<style>

		#progressBar{
		  height:20px;
		  margin: 0 -1px;
		}

		#progressBar .progressValue {
            background-color: rgb(77, 141, 67);
        }

        #progressBar .progressValueText {
            position: absolute;
            width: 100%;
            text-align:center;
        }

		</style>

        <script type="text/javascript">

        	var startImport = function(){
				$.ajax({
					url: "Import.php",
					method: "post",
					data:{
						startImport:true,
						limit: 500
					},
					dataType:"json",
					success: function(data){
    					if(data.success && data.progress.progress){
    						$("#progressBar .progressValue").css("width", data.progress.progress + "%");
    						$("#progressBar .progressValueText").text(data.progress.progress + "%");
    						if(data.progress.progress >=50){
    							$("#progressBar .progressValueText").css("color","white");
    						}else{
    							$("#progressBar .progressValueText").css("color","black");
    						}

    						if(data.progress.progress < 100){
    							startImport();
    						}else{
    							$("#progressBar .spinner").hide();
    							$("#startImport").show();
    						}
    					}else if(data.error){
    						console.log(data.error);
    					}
    				}
				});
			}

			$(document).ready(function(){

				$(document).on("click", "#startImport", function(){
					$("#progressBar").removeClass("d-none").addClass("d-flex");
					$("#progressBar .progressValue").css("width", "0%");
    				$("#progressBar .progressValueText").text("0%");
    				$("#progressBar .progressValueText").css("color","black");
					$("#progressBar .spinner").show();
					$("#startImport").hide();
					startImport();
				});

				$(document).on("click", "#undo", function(){
    				$.ajax({
    					url: "Import.php",
    					method: "post",
    					data:{
    						undo:true,
    					},
    					dataType:"json",
    					success: function(data){
        					if(data.success){
								flagAlertMessage(data.success, false);
        					}else if(data.error){
        						flagAlertMessage(data.error, true);
        					}
        				}
    				});
				});

				$(document).on("click", ".makeMissing", function(){
					if($(this).data("entity")){
						data = {cmd:"make"+$(this).data("entity")}
					
						$.ajax({
							url: "Import.php",
							method: "post",
							data:data,
							dataType:"json",
							success: function(data){
								if(data.success){
									flagAlertMessage(data.success, false);
								}else if(data.error){
									flagAlertMessage(data.error, true);
								}
							}
						});
					}
				});
			});

        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?> >
		<?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-3">
				<form method="post" enctype="multipart/form-data">
					<div>
						<label for="csvFile">Upload CSV File:</label>
						<input type="file" id="csvFile" name="csvFile" accept=".csv">
					</div>
					<?php if(isset($errorMsg)){ ?>
					<div>
						<?php echo $errorMsg; ?>
					</div>
					<?php } ?>
					<div>
						<input type="submit" value="Upload" class="ui-button ui-corner-all">
					</div>
				</form>
            </div>

			<?php if(isset($importTbl)){ ?>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-3" style="max-width:100%">
				<div class="hhk-flex"><h2>Import Progress</h2><a href="Import.php?includeTbl=true" class="ui-button ui-corner-all ml-3">Show raw data</a><button class="ui-button ui-corner-all ml-3" id="startImport">Start Import</button><button class="ui-button ui-corner-all ml-3" id="undo">Undo Import</button></div>
				<div id="progressBar" class="ui-widget ui-widget-content ui-corner-all d-none">
					<div class="progressValue ui-corner-all"></div>
					<img src="../images/ui-anim_basic_16x16.gif" class="spinner">
					<div class="progressValueText">0%</div>
				</div>
				<?php echo $importTbl; ?>
            </div>
			<?php } ?>

        </div> <!-- div id="page"-->
    </body>
</html>
