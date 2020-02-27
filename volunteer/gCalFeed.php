<?php
/**
 * gCalFeed.php
 *
 * @category  Volunteer
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

require ('VolIncludes.php');


require (DB_TABLES . 'volCalendarRS.php');
//require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';
require (THIRD_PARTY . 'PHPMailer/v6/src/PHPMailer.php');
require (THIRD_PARTY . 'PHPMailer/v6/src/SMTP.php');
require (THIRD_PARTY . 'PHPMailer/v6/src/Exception.php');
require('classes' . DS . 'cEventClass.php');
require(CLASSES . "shellEvent_Class.php");
require (CLASSES . 'UserCategories.php');

require(CLASSES . 'VolCal.php');

$wInit = new webInit(WebPageCode::Service);

$dbh = $wInit->dbh;


// get session instance
$uS = Session::getInstance();



$roleId = $uS->rolecode;
$uname = $uS->username;
$uid = $uS->uid;


$events = array();

addslashesextended($_GET);


//Check GET
if (isset($_GET['c'])) {
    $c = filter_var($_GET['c'], FILTER_SANITIZE_STRING);
} else {

    echo array("error" => "Bad Command");
    exit();
}


$myId = intval($uid, 10);
$cats = new UserCategories($myId, $roleId, $uname);
$cats->loadFromDb($dbh);

$vcc = "";
if (isset($_GET['vcc'])) {
    $vcc = filter_var(urldecode($_GET['vcc']), FILTER_SANITIZE_STRING);
}


try {

    switch ($c) {

        case "get":
            $startTime = 0;
            $endTime = 0;

            if (isset($_GET['start'])) {
                $startTime = intval(filter_var(urldecode($_GET['start']), FILTER_SANITIZE_NUMBER_INT), 10);
            }
            if (isset($_GET['end'])) {
                $endTime = intval(filter_var(urldecode($_GET['end']), FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $houseCal = 1;
            if (isset($_GET['hc'])) {
                $houseCal = intval(filter_var(urldecode($_GET['hc']), FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $events = VolCal::GetCalView($dbh, $startTime, $endTime, $houseCal, $vcc, $cats);

            break;

        case "getday":
            $startTime = 0;
            $endTime = 0;

            if (isset($_GET['start'])) {
                $startTime = intval(filter_var(urldecode($_GET['start']), FILTER_SANITIZE_NUMBER_INT), 10);
            }
            if (isset($_GET['end'])) {
                $endTime = intval(filter_var(urldecode($_GET['end']), FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $events = VolCal::GetDayView($dbh, $startTime, $endTime, $cats);

            break;


        case "list":
            $startTime = '';
            $endTime = '';
            if (isset($_GET['start'])) {
                $startTime = filter_var(urldecode($_GET["start"]), FILTER_SANITIZE_STRING);
            }
            if (isset($_GET['end'])) {
                $endTime = filter_var(urldecode($_GET["end"]), FILTER_SANITIZE_STRING);
            }

            $events = VolCal::GetListView($dbh, $startTime, $endTime, $vcc, $cats);

            break;


        case "getevent":
            $eid = "";
            if (isset($_GET["eid"])) {
                $eid = filter_var(urldecode($_GET["eid"]), FILTER_SANITIZE_STRING);
            }

            $events = VolCal::getEvent($dbh, $eid);
            break;


        case "new":
            $evt = new cEventClass();
            $evt->LoadFromGetString($_GET);

            $events = VolCal::CreateCalEvent($dbh, $evt, $cats);
            break;

        case "upd":
            $evt = new cEventClass();
            $evt->LoadFromGetString($_GET);

            $events = VolCal::UpdateCalEvent($dbh, $evt, $cats);
            break;

        case "del":
            $eid = "";
            $delall = "0";
            $justme = "0";
            $sendemail = "0";

            if (isset($_GET["id"])) {
                $eid = filter_var($_GET["id"], FILTER_SANITIZE_NUMBER_INT);
            }
            if (isset($_GET["delall"])) {
                $delall = filter_var($_GET["delall"], FILTER_SANITIZE_NUMBER_INT);
            }
            if (isset($_GET["justme"])) {
                $justme = filter_var($_GET["justme"], FILTER_SANITIZE_STRING);
            }
            if (isset($_GET["sendemail"])) {
                $sendemail = filter_var($_GET["sendemail"], FILTER_SANITIZE_STRING);
            }

            $events = VolCal::DeleteCalEvent($dbh, $eid, $delall, $justme, $sendemail, $cats);

            break;

        case "drp":
            if (isset($_GET["id"])) {
                $gets["id"] = filter_var($_GET["id"], FILTER_SANITIZE_STRING);
            }
            if (isset($_GET["dayDelta"])) {
                $gets["dayDelta"] = filter_var($_GET["dayDelta"], FILTER_SANITIZE_NUMBER_INT);
            }
            if (isset($_GET["minuteDelta"])) {
                $gets["minuteDelta"] = filter_var($_GET["minuteDelta"], FILTER_SANITIZE_NUMBER_INT);
            }
            if (isset($_GET["allDay"])) {
                $gets["allDay"] = filter_var($_GET["allDay"], FILTER_VALIDATE_BOOLEAN);
            }

            $events = VolCal::MoveEvent($dbh, $gets, $cats);

            break;

        case "rsz":
            if (isset($_GET["id"])) {
                $gets["id"] = filter_var($_GET["id"], FILTER_SANITIZE_STRING);
            }
            if (isset($_GET["dayDelta"])) {
                $gets["dayDelta"] = filter_var($_GET["dayDelta"], FILTER_SANITIZE_NUMBER_INT);
            }
            if (isset($_GET["minuteDelta"])) {
                $gets["minuteDelta"] = filter_var($_GET["minuteDelta"], FILTER_SANITIZE_NUMBER_INT);
            }

            $events = VolCal::ResizeEvent($dbh, $gets, $cats);

            break;

        default:
            $events[] = array("error" => "Bad Command to Calendar Feeder");
    }

} catch (PDOException $ex) {

    $events = array("error" => "Database Error" . $ex->getMessage());

} catch (Exception $ex) {

    $events = array("error" => "HouseKeeper Error" . $ex->getMessage());
}



echo( json_encode($events) );
exit();

