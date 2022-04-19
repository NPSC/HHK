<?php
namespace HHK\House\Appointment;

use HHK\sec\Session;
use HHK\House\Event;


/*
 * TimeGrid.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class TimeGrid {


    public static function getTimeGrid(\PDO $dbh, $startDate, $endDate, $timezone) {


        $s = array(
            'id' => 1,
            'start' => '2022-04-18T15:30:00-05:00',
            'title' => 'Ireland',
            'color' => 'yellow',
            'textColor' => 'black'
        );

        $event = new Event($s, $timezone);
        $events[] = $event->toArray();

        $s = array(
            'id' => 2,
            'start' => '2022-04-18T15:30:00-05:00',
            'title' => 'Crane',
            'color' => 'green',
            'textColor' => '#fff'
        );

        $event = new Event($s, $timezone);
        $events[] = $event->toArray();

        $s = array(
            'id' => 3,
            'start' => '2022-04-19T15:30:00-05:00',
            'title' => 'VanderMeer',
            'color' => 'tan',
            'textColor' => 'black'
        );

        $event = new Event($s, $timezone);
        $events[] = $event->toArray();

        return $events;
    }


}

