<?php
use HHK\sec\WebInit;
use GO\Scheduler;
use HHK\Cron\EmailCheckedoutJob;
use HHK\Cron\AbstractJob;
use HHK\Cron\JobFactory;
use HHK\Cron\JobInterface;
/**
 * ws_cron.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");

$wInit = new webInit(WebPageCode::Service);

$dbh = $wInit->dbh;

$uS = Session::getInstance();
$scheduler = new Scheduler();

//Get jobs from DB
$stmt = $dbh->query("select * from cronjobs");
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$jobObjs = array();
$results = array();

//Set up scheduler
foreach($jobs as $job){
    if($job['Status'] == 'a'){
        $jobObj = JobFactory::make($dbh, $job['idJob']);

        if($jobObj instanceof JobInterface){
            $jobObjs[] = $jobObj;
            $scheduler->call($jobObj->run())->daily($job['Time']); // $job['time'] must be in format hh:mm
        }
    }
}

//Run all scheduled jobs
$scheduler->run();

//Gather results
foreach($jobObjs as $jobObj){
    $results[$jobObj->idJob] = ["status"=>$jobObj->status, "logMsg"=>$jobObj->logMsg];
}

//send results as json
if (is_array($results)) {
    echo (json_encode($results));
} else {
    echo $results;
}

exit();

?>