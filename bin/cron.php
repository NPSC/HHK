<?php
use GO\Scheduler;
use HHK\Common;
use HHK\Cron\AbstractJob;
use HHK\Cron\JobFactory;
use HHK\Cron\JobInterface;
use HHK\Exception\RuntimeException;
use HHK\sec\Login;
use HHK\sec\Session;

/**
 * cron.php
 *
 * Runs the Job Scheduler's due jobs.  Command line only - it is outside the web root
 * and refuses to run under a web server.  Call it every minute from the crontab:
 *
 *   * * * * * php /var/www/<site>/bin/cron.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require (__DIR__ . '/../functions/commonDefines.php');

try {

    $login = new Login();
    $login->initHhkSession();

} catch (\Exception $ex) {
    fwrite(STDERR, "Database Access Error: " . $ex->getMessage() . PHP_EOL);
    exit(1);
}

try {
    $dbh = Common::initPDO(TRUE);
} catch (RuntimeException $hex) {
    fwrite(STDERR, $hex->getMessage() . PHP_EOL);
    exit(1);
}

$uS = Session::getInstance();
$uS->logged = true;
$uS->username = "JobScheduler";

$scheduler = new Scheduler();
$allowedIntervals = AbstractJob::AllowedIntervals;

//Get jobs from DB
$stmt = $dbh->prepare("SELECT * FROM `cronjobs`");
$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$jobObjs = array();
$results = array();

//Set up scheduler
foreach($jobs as $job){
    if($job['Status'] == 'a'){
        $jobObj = JobFactory::make($dbh, $job['idJob']);
        $interval = $job["Interval"];
        $day = $job["Day"];
        $hour = $job["Hour"];
        $minute = $job["Minute"];
        $time = "";

        if($jobObj instanceof JobInterface && in_array($interval, $allowedIntervals)){

            //format day/time for scheduler
            switch($interval){
                case "monthly":
                    // ->at("m h dom mon dow")
                    $interval = "at";
                    $time = $minute . " " . $hour . " " . $day . " * ?";
                    break;
                case "weekly":
                    // ->at("m h dom mon dow")
                    $interval = "at";
                    $time = $minute . " " . $hour . " ? * " . $day;
                    break;
                case "daily":
                    // ->daily("hh:mm")
                    $time = $hour . ":" . $minute;
                    break;
                case "hourly":
                    // ->hourly("mm")
                    $time = $minute;
                    break;
            }

            if($time != ""){
                $jobObjs[$job['idJob']] = $jobObj;
                $scheduler->call(function($jobObj){
                        $jobObj->run();
                    },array("jobObj"=>$jobObj))
                    ?->$interval($time); // $time must be in format hh:mm or mm or "m h dom mon dow"
            }
        }
    }
}

//Run all scheduled jobs
$scheduler->run();

//Gather results
foreach($jobObjs as $idJob=>$jobObj){
    $results[$idJob] = ["status"=>$jobObj->getStatus(), "logMsg"=>$jobObj->getLogMsg()];
}

//send results as json
echo json_encode($results) . PHP_EOL;

exit(0);
