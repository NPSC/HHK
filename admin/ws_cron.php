<?php
use GO\Scheduler;
use HHK\Cron\EmailCheckedoutJob;
use HHK\Cron\AbstractJob;
use HHK\Cron\JobFactory;
use HHK\Cron\JobInterface;
use HHK\sec\Login;
use HHK\sec\UserClass;
use HHK\sec\Session;
/**
 * ws_cron.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");

try {

    $login = new Login();
    $login->initHhkSession(CONF_PATH, ciCFG_FILE);

} catch (InvalidArgumentException $pex) {
    exit ("Database Access Error.");

} catch (Exception $ex) {
    exit ($ex->getMessage());
}

try {
    $dbh = initPDO(TRUE);
} catch (RuntimeException $hex) {
    exit( $hex->getMessage());
}

$u = new UserClass();
if(!$u->isCron()){
    // Authenticate user
    $user = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
    $pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';

    if (($user == '' && $pass == '') || $u->_checkLogin($dbh, addslashes($user), $pass, FALSE) === FALSE) {

        header('WWW-Authenticate: Basic realm="Hospitality HouseKeeper"');
        header('HTTP/1.0 401 Unauthorized');
        exit("Not authorized");

    }
}

$uS = Session::getInstance();
$uS->logged = true;
$uS->username = "JobScheduler";

$scheduler = new Scheduler();
$allowedIntervals = AbstractJob::AllowedIntervals;

//Get jobs from DB
$stmt = $dbh->query("select * from cronjobs");
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

            $jobObjs[] = $jobObj;
            $scheduler->call(function($jobObj){
                    $jobObj->run();
                },array("jobObj"=>$jobObj))
                ->$interval($time); // $time must be in format hh:mm or mm or "m h dom mon dow"
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