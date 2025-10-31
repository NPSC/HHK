<?php

use HHK\sec\Session;
use HHK\sec\Login;
use HHK\sec\ScriptAuthClass;
use HHK\Exception\CsrfException;
use HHK\Document\FormTemplate;
use HHK\Document\FormDocument;
use HHK\sec\Recaptcha;

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
    $dbh = $login->initHhkSession(CONF_PATH, ciCFG_FILE);

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

$c = "";

// Get our command
if (isset($_REQUEST["cmd"])) {
    $c = filter_var($_REQUEST["cmd"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

$uS = Session::getInstance();


$events = array();

try {

    switch ($c) {

        case 'schzip':

            if (isset($_GET['zip'])) {
                $zip = filter_var($_GET['zip'], FILTER_SANITIZE_NUMBER_INT);
                $events = searchZip($dbh, $zip);
            }
            break;

         case 'gettemplate':

             $id = filter_var($_GET["id"], FILTER_SANITIZE_NUMBER_INT);
             if($id > 0){
                 $formTemplate = new FormTemplate();
                 if($formTemplate->loadTemplate($dbh, $id)){
                     $events['formData'] = $formTemplate->getTemplate();
                     $events['formSettings'] = $formTemplate->getSettings();
                     $events['lookups'] = $formTemplate->getLookups($dbh);
                 }else{
                     $events['error'] = "Form Template not found";
                 }
             }else{
                 $events['error'] = "Form Template not found";
             }
             break;

         case 'getform':

             if(!$uS->logged){
                 $events['error'] = "Unauthorized for page: Please login";
             }else{
                 $id = filter_var($_GET["id"], FILTER_SANITIZE_NUMBER_INT);
                 if($id > 0){
                     $formDocument = new FormDocument();
                     if($formDocument->loadDocument($dbh, $id)){
                         $events['formData'] = $formDocument->getDoc();
                         $events['formSettings']['formStyle'] = "";
                         $events['lookups'] = FormTemplate::getLookups($dbh);

                     }else{
                         $events['error'] = "Document not found";
                     }
                 }else{
                     $events['error'] = "No Referral form found";
                 }
             }
             break;

         case 'previewform':

             $style = "";
             $formData = "";
             if(isset($_REQUEST['style'])){
                 $style = filter_var($_REQUEST['style'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
             }
             if(isset($_REQUEST['initialGuests'])){
                 $initialGuests = filter_var($_REQUEST['initialGuests'], FILTER_SANITIZE_NUMBER_INT);
             }
             if(isset($_REQUEST['maxGuests'])){
                 $maxGuests = filter_var($_REQUEST['maxGuests'], FILTER_SANITIZE_NUMBER_INT);
             }
             if(isset($_REQUEST['formData'])){
                $formData = filter_var($_REQUEST['formData'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
             }

             if(!$uS->logged){
                 $events['error'] = "Unauthorized for page: Please login";
             }else{
                 $events['formData'] = $formData;
                 $events['formSettings']['formStyle'] = $style;
                 $events['formSettings']['enableRecaptcha'] = false;
                 $events['formSettings']['initialGuests'] = $initialGuests;
                 $events['formSettings']['maxGuests'] = $maxGuests;
                 $events['lookups'] = FormTemplate::getLookups($dbh);
             }
             break;

         case "submitform" :

			$recaptchaToken = '';
			if(isset($_POST['recaptchaToken'])){
				$recaptchaToken = filter_var($_POST['recaptchaToken'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
			}

			$recaptcha = new Recaptcha();
			if(($uS->mode == 'demo' || $uS->mode == 'live') && $recaptchaToken != ''){
			     $score = $recaptcha->verify($recaptchaToken);
			}else{
			    $score = 1.0;
			}

            $fields = array();
            if(isset($_POST['formRenderData'])){
                try{
                    $jsonStr = base64_decode($_REQUEST['formRenderData']);
                    $fields = json_decode($jsonStr);
                    if(!is_array($fields)){
                        try{
                            $body = "New bug report received from " . $uS->siteName . "\r\n\r\n";
                            $body .= "Request Type: AJAX\r\n\r\n";
                            $body .= "Details: \r\n\r\n";
                            $body .= "Message: fields variable is null\r\n\r\n";
                            $body .= "doc: " .$_POST['formRenderData'];
            
                            $subject = "New bug report received from " . $uS->siteName;
                            $headers = "From: BugReporter<noreply@nonprofitsoftwarecorp.org>\r\n";
                            mail($uS->errorReportEmail, $subject, $body, $headers);
                            
                        }catch(\Exception $e){}

                        $events = ['status'=>'error', 'errors'=>['server'=>'Unable to read form, check your submission for special characters and try again.']];
                        break;
                    }
                }catch(\Exception $e){
                    $events = ['status'=>'error', 'errors'=>['server'=>'Unable to read form, check your submission for special characters and try again.']];
                    break;
                }
            }else{
                $events = ['status'=>'error', 'errors'=>['server'=>'The form appears to be empty, please try again']];
                break;
            }

            $templateId = '';
            if(isset($_POST['template'])){
                $templateId = intval(filter_var($_POST['template'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

			if($score >= 0.5){
				$formDocument = new FormDocument();
				$events = $formDocument->saveNew($dbh, $fields, $templateId);
				$events['recaptchaScore'] = $score;
			}else{
				$events = ['status'=>'error', 'errors'=>['server'=>'Recaptcha spam check failed'], 'recaptchaScore'=>$score];
			}
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

function searchZip(\PDO $dbh, $zip) {

    $query = "select * from postal_codes where Zip_Code like :zip LIMIT 10";
    $stmt = $dbh->prepare($query);
    $stmt->execute(array(':zip'=>$zip . "%"));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = array();
    foreach ($rows as $r) {
        $ent = array();

        $ent['value'] = $r['Zip_Code'];
        $ent['label'] = $r['City'] . ', ' . $r['State'] . ', ' . $r['Zip_Code'];
        $ent['City'] = $r['City'];
        $ent['County'] = $r['County'];
        $ent['State'] = $r['State'];

        $events[] = $ent;
    }

    return $events;
}
exit();