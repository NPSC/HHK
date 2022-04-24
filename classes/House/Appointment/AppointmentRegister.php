<?php
namespace HHK\House\Appointment;

use HHK\sec\Session;
use HHK\House\Event;
use HHK\SysConst\AppointmentType;
use HHK\Exception\UnexpectedValueException;


/*
 * AppointmentRegister.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class AppointmentRegister {


    public static function getCalendarRescs(\PDO $dbh, $startDate, $endDate, $timezone, $view, $rescGroupBy) {

        $timeslots = array();

        $tsStartTime = NULL;
        $tsEndTime = NULL;
        $tsDuration = NULL;
        $first = TRUE;

        // get timeslots, used as resources
        // Get indicated appointment template
        $stmt = $dbh->query("select
            Start_ToD,
            End_ToD,
            Timeslot_Duration
          from appointment_template
          where Weekday_Index >= '0' AND Weekday_Index < '7';" );

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $startTod = new \DateTimeImmutable($r['Start_ToD']);
            $endTod = new \DateTimeImmutable($r['End_ToD']);
            $duration = new \DateInterval('PT' . $r['Timeslot_Duration'] . 'M');

            if ($first) {

                $first = FALSE;
                $tsStartTime = $startTod;
                $tsEndTime = $endTod;
                $tsDuration = $duration;

            } else {

                if ($startTod < $tsStartTime) {
                    $tsStartTime = $startTod;
                }

                if ($endTod > $tsEndTime) {
                    $tsEndTime = $endTod;
                }

                if ($duration->i < $tsDuration->i) {
                    $tsDuration = $duration;
                }
            }

        }

        // Generate timeslots
        $period = new \DatePeriod($tsStartTime, $tsDuration, $tsEndTime);

        foreach ($period as $dt) {

            $timeslots[] = [
                'id' => $dt->format('H:i:s'),
                'title' => $dt->format('g:ia'),
                'duration' => $tsDuration->i
            ];
        }

        // add one timeslot for loose reservations
        $timeslots[] = [
            'id' => 0,
            'title' => 'Unassigned',
            'duration' => 0
        ];

        return $timeslots;
    }

    /**
     * @param \PDO $dbh
     * @param string $startDate
     * @param string $endDate
     * @param string $timezone
     * @throws UnexpectedValueException
     * @return array|NULL[]|array[]
     */
    public static function getTimeGrid(\PDO $dbh, $startDate, $endDate, $timezone, $url) {

        $uS = Session::getInstance();
        $events = [];

        if ($startDate == "" || $endDate == "") {
            return $events;
        }

        if ($timezone == '') {
            $timezone = $uS->tz;
        }

        $beginDate = Event::parseDateTime($startDate, new \DateTimeZone($timezone));
        $endDate = Event::parseDateTime($endDate, new \DateTimeZone($timezone));


        $stmt = $dbh->query("SELECT
    a.idAppointment,
    a.Date_Appt,
    a.Time_Appt,
    a.Reservation_Id,
    a.`Type`,
    IFNULL(n.Name_Last, '') as Name_Last
FROM
    appointment a
		LEFT JOIN
    reservation r on a.Reservation_Id = r.idReservation
		LEFT JOIN
    `name` n on r.idGuest = n.idName
WHERE
    a.`Date_Appt` >= DATE('" . $beginDate->format('Y-m_d') . "') AND a.`Date_Appt` < DATE('" . $endDate->format('Y-m_d') . "')
    AND a.`Status` = 'a';");

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $apptDate = new \DateTime($r['Date_Appt'] . ' ' . $r['Time_Appt']);

            $s = array(
                'id' => $r['idAppointment'],
                'start' => $apptDate->format('Y-m-d\T00:20:00'),
                'end' => $apptDate->format('Y-m-d\T23:30:00'),
                'tpe' => $r['Type'],
                'resourceId' => $apptDate->format('H:i:00'),
                'startEditable' => FALSE,
            );

            // Type
            switch ($r['Type']) {

                case AppointmentType::Block:

                    $s['title'] = 'Blocked';
                    $s['color'] = 'lightgray';
                    $s['textColor'] = 'black';
                    $s['editable'] = FALSE;
                    $s['resourceEditable'] = FALSE;
                    break;

                case AppointmentType::Reservation:

                    $s['title'] = $r['Name_Last'];
                    $s['color'] = 'lightgreen';
                    $s['textColor'] = 'black';
                    $s['rid'] = $r['Reservation_Id'];
                    $s['url'] = $url . '?rid=' . $r['Reservation_Id'];

                    break;

                default:
                    throw new UnexpectedValueException("Appointment Type value missing or wrong: " . $r['Type']);
            }

            $event = new Event($s, new \DateTimeZone($timezone));
            $events[] = $event->toArray();

        }

        return $events;
    }

}

