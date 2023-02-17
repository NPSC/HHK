<?php
use HHK\Exception\InvalidArgumentException;
use HHK\SysConst\WebPageCode;
use HHK\sec\WebInit;
use HHK\House\GuestRegister;

/**
 * ws_calendar.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
/**
 *  includes and requries
 */
require ("homeIncludes.php");


try {
    $wInit = new WebInit(WebPageCode::Service);
} catch (InvalidArgumentException $ex) {
    // Password may be missing
    exit('');
}

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$c = "";

// Get our command
if (isset($_REQUEST["cmd"])) {
    $c = htmlspecialchars($_REQUEST["cmd"]);
}


try {

    switch ($c) {

        case 'resclist':

            $start = '';
            $end = '';
            $timezone = '';
            $groupBy = 'Type';

            if (isset($_REQUEST["start"])) {
                $start = htmlspecialchars(urldecode($_REQUEST["start"]));
            }
            if (isset($_REQUEST["end"])) {
                $end = htmlspecialchars(urldecode($_REQUEST["end"]));
            }

            if (isset($_REQUEST["gpby"])) {
                $groupBy = htmlspecialchars(urldecode($_REQUEST["gpby"]));
            }

            if (isset($_REQUEST["timezone"])) {
                $timezone = htmlspecialchars(urldecode($_REQUEST["timezone"]));
            }

            $events = GuestRegister::getCalendarRescs($dbh, $start, $end, $timezone, $groupBy);
            break;

        case 'eventlist':

            $start = '';
            $end = '';
            $timezone = NULL;

            if (isset($_REQUEST["start"])) {
                $start = htmlspecialchars(urldecode($_REQUEST["start"]));
            }
            if (isset($_REQUEST["end"])) {
                $end = htmlspecialchars(urldecode($_REQUEST["end"]));
            }

            if (isset($_REQUEST["timezone"])) {
                $timezone = htmlspecialchars(urldecode($_REQUEST["timezone"]));
            }

            $guestRegister = new GuestRegister();
            $events = $guestRegister->getRegister($dbh, $start, $end, $timezone);
            break;

        default:
            $events = array("error" => "Bad Command: \"" . $c . "\"");
    }

} catch (PDOException $ex) {
    $events = array("error" => "Database Error: " . $ex->getMessage() . "<br/>" . $ex->getTraceAsString());
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
?>