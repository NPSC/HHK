<?php

use HHK\sec\WebInit;
use HHK\SysConst\WebPageCode;
use HHK\sec\SecurityComponent;
use HHK\sec\Session;
use HHK\sec\Login;
use HHK\sec\ScriptAuthClass;
use HHK\Photo;
use HHK\Update\SiteConfig;
use HHK\Document\ListDocuments;
use HHK\Document\Document;
use HHK\House\Vehicle;
use HHK\HTMLControls\HTMLContainer;
use HHK\House\Report\ActivityReport;
use HHK\SysConst\GLTableNames;
use HHK\House\ResourceView;
use HHK\House\Constraint\Constraints;
use HHK\History;
use HHK\House\Report\RoomReport;
use HHK\SysConst\ReservationStatus;
use HHK\House\Room\Room;
use HHK\SysConst\RoomState;
use HHK\Payment\Invoice\Invoice;
use HHK\HTMLControls\HTMLTable;
use HHK\Payment\Receipt;
use HHK\Exception\PaymentException;
use HHK\Exception\CsrfException;
use HHK\Document\FormTemplate;
use HHK\Document\FormDocument;

/**
 * ws_forms.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2021 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
/**
 *  includes and requires
 */
require ("homeIncludes.php");


try {
    
    $login = new Login();
    $dbh = $login->initHhkSession(ciCFG_FILE);
	
	$secret = decryptMessage(SysConfig::getKeyValue($dbh, 'sys_config', 'HHK_Secret_Key'));
	$csrfToken = '';
	if(isset($_POST['csrfToken'])){
		$csrfToken = filter_var($_POST['csrfToken'], FILTER_SANITIZE_STRING);
	}
	$login->verifyCSRF($csrfToken);
    
} catch (InvalidArgumentException $pex) {
    exit ("<h3>Database Access Error.   <a href='index.php'>Continue</a></h3>");
} catch (CsrfException $e) {
		exit(json_encode(['status'=>'error', 'errors'=>['server'=>$e->getMessage()]]));
} catch (Exception $ex) {
    exit ("<h3>" . $ex->getMessage());
}


// Load the page information
try {
    $page = new ScriptAuthClass($dbh);
} catch (Exception $ex) {
    $uS->destroy(true);
    exit('<h2>Page not in database.</h2>');
}

addslashesextended($_REQUEST);
$c = "";

// Get our command
if (isset($_REQUEST["cmd"])) {
    $c = filter_var($_REQUEST["cmd"], FILTER_SANITIZE_STRING);
}

$uS = Session::getInstance();


$events = array();

try {

    switch ($c) {
            
         case "submitform" :
		 
			$recaptchaToken = '';
			if(isset($_POST['recaptchaToken'])){
				$recaptchaToken = filter_var($_POST['recaptchaToken'], FILTER_SANITIZE_STRING);
			}
			
			$events = verifyRecaptcha($recaptchaToken);
			
            $formRenderData = '';
            if(isset($_POST['formRenderData'])){
                try{
                    json_decode(stripslashes($_REQUEST['formRenderData']));
                    $formRenderData = stripslashes($_REQUEST['formRenderData']);
                }catch(\Exception $e){
                    
                }
            }
			
            //$formDocument = new FormDocument();
            //$events = $formDocument->saveNew($dbh, $formRenderData);
            break;
			
        default:
            $events = array("error" => "Bad Command: \"" . $c . "\"");
    }

} catch (PDOException $ex) {
    $events = array("error" => "Database Error: " . $ex->getMessage());

} catch (Exception $ex) {
    $events = array("error" => "Programming Error: " . $ex->getMessage());
}



if (is_array($events)) {
    echo (json_encode($events));
} else {
    echo $events;
}

function verifyRecaptcha($token){
	
	$apiKey = "AIzaSyDwMdFwC4mKidWXykt5b8LSAWjIADqraCc";
	$projectID = "helical-clock-316420";
	$siteKey = "6LemLyQbAAAAAKKaz91-FZCSI8cRs-l9DCYmEadO";
	
	
	$ch = curl_init();
	
	$data = [
		"event"=>[
			"token"=>$token,
			"siteKey"=>$siteKey,
			"expectedAction"=>"submit"
		]
	];

	curl_setopt($ch, CURLOPT_URL,"https://recaptchaenterprise.googleapis.com/v1beta1/projects/" . $projectID . "/assessments?key=" . $apiKey);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
	
	// Receive server response ...
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$server_output = curl_exec($ch);

	curl_close ($ch);
	
	try{
		$response = json_decode($server_output);
		
		return $response;
		
		//if($response->tokenProperties->valid && $response->tokenProperties->action == 'submit' && $response->score > 0.6){
		//	return true;
		//}else{
		//	return false;
		//}
	}catch(\Exception $e){
		
	}
}
exit();