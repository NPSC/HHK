<?php

use HHK\sec\WebInit;
use HHK\SysConst\WebPageCode;
use HHK\sec\SecurityComponent;
use HHK\sec\Session;
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
    
} catch (InvalidArgumentException $pex) {
    exit ("<h3>Database Access Error.   <a href='index.php'>Continue</a></h3>");
    
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

/* @var $dbh PDO */
$dbh = $wInit->dbh;
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
            $formRenderData = '';
            if(isset($_POST['formRenderData'])){
                try{
                    json_decode(stripslashes($_REQUEST['formRenderData']));
                    $data = stripslashes($_REQUEST['formRenderData']);
                }catch(\Exception $e){
                    
                }
            }
            
            $submittedData = '';
            if(isset($_POST['submittedData'])){
                try{
                    json_decode(stripslashes($_REQUEST['submittedData']));
                    $data = stripslashes($_REQUEST['submittedData']);
                }catch(\Exception $e){
                    
                }
            }
            
            $formDocument = new FormDocument();
            $events = $formDocument->saveNew($dbh, $formRenderData, $_POST);
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

exit();