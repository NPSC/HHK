<?php
/**
 * ws_calendar.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
/**
 *  includes and requries
 */
require ("homeIncludes.php");

require (DB_TABLES . 'nameRS.php');
require (HOUSE . 'GuestRegister.php');
require (CLASSES . 'US_Holidays.php');
require (HOUSE . 'Reservation_1.php');


$wInit = new webInit(WebPageCode::Service);

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$uS = Session::getInstance();


$guestAdmin = SecurityComponent::is_Authorized("guestadmin");

$c = "";

// Get our command
if (isset($_REQUEST["cmd"])) {
    $c = filter_var($_REQUEST["cmd"], FILTER_SANITIZE_STRING);
}


try {

    switch ($c) {

        case 'resclist':

            $start = '';
            $end = '';
            $timezone = '';

            if (isset($_REQUEST["start"])) {
                $start = filter_var(urldecode($_REQUEST["start"]), FILTER_SANITIZE_STRING);
            }
            if (isset($_REQUEST["end"])) {
                $end = filter_var(urldecode($_REQUEST["end"]), FILTER_SANITIZE_STRING);
            }

            if (isset($_REQUEST["timezone"])) {
                $timezone = filter_var(urldecode($_REQUEST["timezone"]), FILTER_SANITIZE_STRING);
            }

            $events = GuestRegister::getCalendarRescs($dbh, $start, $end, $timezone);
            break;

        case 'eventlist':

            $start = '';
            $end = '';
            $timezone = NULL;

            if (isset($_REQUEST["start"])) {
                $start = filter_var(urldecode($_REQUEST["start"]), FILTER_SANITIZE_STRING);
            }
            if (isset($_REQUEST["end"])) {
                $end = filter_var(urldecode($_REQUEST["end"]), FILTER_SANITIZE_STRING);
            }

            if (isset($_REQUEST["timezone"])) {
                $timezone = filter_var(urldecode($_REQUEST["timezone"]), FILTER_SANITIZE_STRING);
            }

            $guestRegister = new GuestRegister();
            $events = $guestRegister->getRegister($dbh, $start, $end, $timezone);
            break;

        default:
            $events = array("error" => "Bad Command: \"" . $c . "\"");
    }

} catch (PDOException $ex) {
    $events = array("error" => "Database Error: " . $ex->getMessage() . "<br/>" . $ex->getTraceAsString());
} catch (Hk_Exception $ex) {
    $events = array("error" => "HouseKeeper Server Error: " . $ex->getMessage() . "<br/>" . $ex->getTraceAsString());
} catch (Exception $ex) {
    $events = array("error" => "Web Server Error: " . $ex->getMessage());
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

exit();


