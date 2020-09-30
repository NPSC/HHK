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
use HHK\House\Report\ReportFieldSet;

/**
 * ws_report.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
/**
 *  includes and requires
 */
require ("homeIncludes.php");


$wInit = new WebInit(WebPageCode::Service);

/* @var $dbh PDO */
$dbh = $wInit->dbh;
$guestAdmin = SecurityComponent::is_Authorized("guestadmin");
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
        case 'listFieldSets':
            $report = '';
            
            if (isset($_REQUEST["report"])) {
                $report = filter_var(urldecode($_REQUEST["report"]), FILTER_SANITIZE_STRING);
            }
            
            $events = ["status"=>"success", "report"=>$report, "fieldSets"=>ReportFieldSet::listFieldSets($dbh, $report)];
            
            break;
        
        case 'getFieldSet':
            $idFieldSet = '';
            
            if (isset($_REQUEST["idFieldSet"])) {
                $idFieldSet = filter_var(urldecode($_REQUEST["idFieldSet"]), FILTER_VALIDATE_INT);
            }
            
            $row = ReportFieldSet::getFieldSet($dbh, $idFieldSet);
            
            if($row){
                $events = ["status"=>"success", "fieldSet"=>$row];
            }else{
                $events = ["error"=>"Field set not found"];
            }
            
            break;
            
        case 'createFieldSet':
            if (isset($_REQUEST["report"])) {
                $report = filter_var(urldecode($_REQUEST["report"]), FILTER_SANITIZE_STRING);
            }
            if (isset($_REQUEST["title"])) {
                $title = filter_var(urldecode($_REQUEST["title"]), FILTER_SANITIZE_STRING);
            }
            if (isset($_REQUEST["global"])) {
                $global = filter_var(urldecode($_REQUEST["global"]), FILTER_SANITIZE_NUMBER_INT);
            }
            if (isset($_REQUEST["fields"])) {
                $fields = filter_var_array($_REQUEST["fields"], FILTER_SANITIZE_STRING);
            }
            try{
                $events = ReportFieldSet::createFieldSet($dbh, $report, $title, $fields, $global);
            }catch(\Exception $e){
                $events = ['error'=>$e->getMessage()];
            }
            
            break;
            
        case 'updateFieldSet':
            if (isset($_REQUEST['idFieldSet'])){
                $idFieldSet = filter_var($_REQUEST['idFieldSet'], FILTER_SANITIZE_NUMBER_INT);
            }
            if (isset($_REQUEST["report"])) {
                $report = filter_var(urldecode($_REQUEST["report"]), FILTER_SANITIZE_STRING);
            }
            if (isset($_REQUEST["title"])) {
                $title = filter_var(urldecode($_REQUEST["title"]), FILTER_SANITIZE_STRING);
            }
            if (isset($_REQUEST["global"])) {
                $global = filter_var(urldecode($_REQUEST["global"]), FILTER_SANITIZE_NUMBER_INT);
            }
            if (isset($_REQUEST["fields"])) {
                $fields = filter_var_array($_REQUEST["fields"], FILTER_SANITIZE_STRING);
            }
            
            $events = ReportFieldSet::updateFieldSet($dbh, $idFieldSet, $report, $title, $fields, $global);
            
            break;
            
        default:
            $events = array("error" => "Bad Command: \"" . $c . "\"");
    }
} catch (\PDOException $ex) {
    $events = array("error" => "Database Error: " . $ex->getMessage());
    
} catch (\Exception $ex) {
    $events = array("error" => "Programming Error: " . $ex->getMessage());
}



if (is_array($events)) {
    echo (json_encode($events));
} else {
    echo $events;
}

exit();

?>