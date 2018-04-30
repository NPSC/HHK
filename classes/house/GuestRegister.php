<?php

/*
 * GuestRegister.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */




class GuestRegister {

    public static function getCalendarRescs(\PDO $dbh, $startTime = '', $endTime = '', $timezone = '') {

        $uS = Session::getInstance();
        $rescs = array();
        $p1d = new \DateInterval('P1D');
        $today = new \DateTime();
        $today->setTime(0, 0, 0);


        if ($startTime == "" || $endTime == "") {
            return $rescs;
        }

        $beginDate = new \DateTime($startTime);
        $endDate = new \DateTime($endTime);

        // Get list of resources
        $qu = "select r.*, ru.idResource_use
from resource r
	left join
resource_use ru on r.idResource = ru.idResource and ru.`Status` = '" . ResourceStatus::Unavailable . "' and DATE(ru.Start_Date) <= DATE('" . $beginDate->format('Y-m-d') . "') and DATE(ru.End_Date) >= DATE('" . $endDate->format('Y-m-d') . "')
 where ru.idResource_use is null
 order by Util_Priority;";
        $rstmt = $dbh->query($qu);


        while ($re = $rstmt->fetch(\PDO::FETCH_ASSOC)) {

            $rescs[] = array(
                'id' => $re['idResource'],
                'title' => $re['Title'],
            );
        }

        return $rescs;

    }

    /**
     *
     * @param PDO $dbh
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    public static function getRegister(\PDO $dbh, $startTime, $endTime, $timezone) {

        $uS = Session::getInstance();
        $events = array();
        $p1d = new \DateInterval('P1D');
        $today = new \DateTime();
        $today->setTime(0, 0, 0);


        if ($startTime == "" || $endTime == "") {
            return $events;
        }

        $beginDate = self::parseDateTime($_GET['start']);
        $endDate = self::parseDateTime($_GET['end']);

        // get list of hospital colors
        $noAdminId = 0;
        $hospitals = array(0 => array('idHospital'=>0, 'Background_Color'=>'blue', 'Text_Color'=>'white'));
        if ($uS->RegColors) {
            $hstmt = $dbh->query("Select Title, idHospital, Reservation_Style as Background_Color, Stay_Style as Text_Color from hospital where `Status` = 'a';");
            foreach ($hstmt->fetchAll(\PDO::FETCH_ASSOC) as $h) {
                $hospitals[$h['idHospital']] = $h;

                if ($h['Title'] == '(None)') {
                    $noAdminId = $h['idHospital'];
                }
            }
        }

        $nameColors = array();

        // Get guest name colorings
        if ($uS->GuestNameColor != '') {

            $demogs = readGenLookupsPDO($dbh, $uS->GuestNameColor);

            foreach ($demogs as $d) {

                if ($d[2] != '') {

                    // Split colors out of CDL
                    $splits = explode(',', $d[2]);

                    $nameColors[$d[0]] = array(
                        't' => trim(strtolower($splits[0])),
                        'b' => isset($splits[1]) ? trim(strtolower($splits[1])) : 'transparent'
                    );
                }
            }
        }

        // Get cleaning holidays for the current year(s)
        $beginHolidays = new US_Holidays($dbh, $beginDate->format('Y'));
        $endHolidays = new US_Holidays($dbh, $endDate->format('Y'));


        $rescUsed = array();
        $nonClean = Reservation_1::loadNonCleaningDays($dbh);
        $eventCounter = 0;

        // Room statuses
        $query1 = "select ru.*, g.Description as `StatusTitle` from resource_use ru left join gen_lookups g on g.Table_Name = 'Resource_Status' and g.Code = ru.Status
where DATE(Start_Date) < DATE('" . $endDate->format('Y-m-d') . "') and ifnull(DATE(End_Date), DATE(now())) > DATE('" . $beginDate->format('Y-m-d') . "');";
        $stmtrs = $dbh->query($query1);

        while ($r = $stmtrs->fetch(\PDO::FETCH_ASSOC)) {

            if ($r["idResource"] == 0) {
                continue;
            }

            // Set Start and end for fullCalendar control
            $c = array(
                'id' => 'RR' . $eventCounter++,
                'idReservation' => 0,
                'resourceId' => $r["idResource"],
                'Span' => 0,
                'idHosp' => 0,
                'start' => $r['Start_Date'],
                'end' => $r['End_Date'],
                'title' => $r['StatusTitle'],
                'allDay' => 1,
                'backgroundColor' => 'gray',
                'textColor' => 'white',
                'borderColor' => 'black',

            );

            $event = new Event($c, $timezone);
            $events[] = $event->toArray();

        }



        // Visits
        $query = "select * from vregister where Visit_Status <> '" . VisitStatus::Pending . "' and
    DATE(Span_Start) < DATE('" . $endDate->format('Y-m-d') . "') and ifnull(DATE(Span_End), case when DATE(now()) > DATE(Expected_Departure) then DATE(now()) else DATE(Expected_Departure) end) >= DATE('" .$beginDate->format('Y-m-d') . "');";
        $stmtv = $dbh->query($query);

        while ($r = $stmtv->fetch(\PDO::FETCH_ASSOC)) {

            if ($r["idResource"] == 0) {
                continue;
            }

            $startDT = new \DateTime($r['Span_Start']);
            $extended = FALSE;
            $now = new \DateTime();
            $now->setTime(23, 59, 59);
            $s = array();


            if ($r['Span_End'] != "") {

                if (date('Y-m-d', strtotime($r['Span_Start'])) == date('Y-m-d', strtotime($r['Span_End']))) {
                    // Dont need to see these on the register.
                    continue;
                }

                $endDT = new \DateTime($r['Span_End']);
                $dtend = new \DateTime($r['Span_End']);
                $dtendDate = new \DateTime($r['Span_End']);
                $dtendDate->setTime(10, 0, 0);
                $endDT->sub($p1d);

                $s['borderColor'] = 'black';
            } else {

                // Expected Departure
                $dtend = new \DateTime($r['Expected_Departure']);
                $dtendDate = new \DateTime($r['Expected_Departure']);
                $dtendDate->setTime(10, 0, 0);

                if ($now > $dtendDate) {
                    $endDT = $now;
                    $dtend = $now;
                    $extended = TRUE;
                } else {
                    $endDT = new \DateTime($r['Expected_Departure']);
                    $endDT->sub($p1d);
                }
            }

            // End date fall on a holiday?
            $validHolidays = TRUE;
            $endYear = $dtendDate->format('Y');

            if ($endYear == $beginHolidays->getYear()) {
                $myHolidays = $beginHolidays;
            } else if ($endYear == $endHolidays->getYear()) {
                $myHolidays = $endHolidays;
            } else {
                $validHolidays = FALSE;
            }

            // Check end date for cleaning holidays
            if ($extended === FALSE && $validHolidays === TRUE && $r['Visit_Status'] != VisitStatus::ChangeRate) {

                while ($myHolidays->is_holiday($dtendDate->format('U'))) {
                    $c = array(
                        'id' => 'H' . $eventCounter++,
                        'idReservation' => 0,
                        'resourceId' => $r["idResource"],
                        'Span' => 0,
                        'idHosp' => 0,
                        'start' => $dtendDate->format('Y-m-d\TH:i:00'),
                        'end' => $dtendDate->format('Y-m-d\TH:i:00'),
                        'title' => 'out',
                        'allDay' => 1,
                        'backgroundColor' => 'black',
                        'textColor' => 'white',
                        'borderColor' => 'Yellow',

                    );

                    $event = new Event($c, $timezone);
                    $events[] = $event->toArray();

                    $dtendDate->add($p1d);
                    $dtendDate->setTime(10, 0, 0);
                }

                // end date fall on non-cleaning weekday?
                $dateInfo = getDate($dtendDate->format('U'));
                $limit = 5;

                while (array_search($dateInfo['wday'], $nonClean) !== FALSE && $limit-- > 0) {
                    // Add a Cleaning Black-Out Event

                    $c = array(
                        'id' => $r['id'] . 'BO' . $dateInfo['wday'],
                        'idReservation' => 0,
                        'resourceId' => $r["idResource"],
                        'Span' => 0,
                        'idHosp' => 0,
                        'start' => $dtendDate->format('Y-m-d\TH:i:00'),
                        'end' => $dtendDate->format('Y-m-d\TH:i:00'),
                        'title' => HTMLContainer::generateMarkup('span', $rescs[$r["idResource"]]['Title'], array('style'=>'white-space:nowrap;')),
                        'allDay' => 1,
                        'backgroundColor' => 'black',
                        'textColor' => 'white',
                        'borderColor' => 'white',

                    );

                    $event = new Event($c, $timezone);
                    $events[] = $event->toArray();


                    $dtendDate->add($p1d);
                    $dtendDate->setTime(10, 0, 0);
                    $dateInfo = getDate($dtendDate->format('U'));
                }


                // Check for holidays again?
                while ($myHolidays->is_holiday($dtendDate->format('U'))) {
                    $c = array(
                        'id' => 'H' . $eventCounter++,
                        'idReservation' => 0,
                        'resourceId' => $r["idResource"],
                        'Span' => 0,
                        'idHosp' => 0,
                        'start' => $dtendDate->format('Y-m-d\TH:i:00'),
                        'end' => $dtendDate->format('Y-m-d\TH:i:00'),
                        'title' => HTMLContainer::generateMarkup('span', $rescs[$r["idResource"]]['Title'], array('style'=>'white-space:nowrap;')),
                        'allDay' => 1,
                        'backgroundColor' => 'black',
                        'textColor' => 'white',
                        'borderColor' => 'Yellow',

                    );
                    $event = new Event($c, $timezone);
                    $events[] = $event->toArray();

                    $dtendDate->add($p1d);
                    $dtendDate->setTime(10, 0, 0);
                }
            }


            $titleText = $r['Guest Last'];
            $spnArray = array('style'=>'white-space:nowrap;padding-left:2px;padding-right:2px;');

            if ($r['Visit_Status'] == VisitStatus::NewSpan) {
                $titleText .= ' (rm)';
                $spnArray['title'] = 'Room Changed';
            } else if ($r['Visit_Status'] == VisitStatus::ChangeRate) {
                $titleText .= ' ($)';
                $spnArray['title'] = 'Rate Changed';
            } else if ($extended) {
                $titleText .= htmlentities('>>');
                $spnArray['title'] = 'Past Expected Departure Date';
            }

            if ($uS->GuestNameColor != '' && isset($r[$uS->GuestNameColor])) {
                if (isset($nameColors[$r[$uS->GuestNameColor]])){
                    $spnArray['style'] .= 'background-color:' . $nameColors[$r[$uS->GuestNameColor]]['b'] . '; color:' . $nameColors[$r[$uS->GuestNameColor]]['t'] . ';';
                }
            }

            $title =  HTMLContainer::generateMarkup('span', $titleText, $spnArray);

            // Set Start and end for fullCalendar control
            $s['id'] = $r['id'];
            $s['idVisit'] = $r['idVisit'];
            $s['Span'] = $r['Span'];
            $s['idHosp'] = $r['idHospital'];
            $s['idAssoc'] = $r['idAssociation'];
            $s['resourceId'] = $r["idResource"];

            $s['start'] = $startDT->format('Y-m-d\TH:i:00');
            $s['end'] = $endDT->format('Y-m-d\TH:i:00');
            $s['title'] = $title;
            $s['allDay'] = 1;

            // Use Hospital colors?
            if (strtolower($uS->RegColors) == 'hospital') {

                // Use Association colors?
                if ($r['idAssociation'] != $noAdminId && $r['idAssociation'] > 0) {
                    $s['backgroundColor'] = $hospitals[$r['idAssociation']]['Background_Color'];
                    $s['textColor'] = $hospitals[$r['idAssociation']]['Text_Color'];
                } else {
                    $s['backgroundColor'] = $hospitals[$r['idHospital']]['Background_Color'];
                    $s['textColor'] = $hospitals[$r['idHospital']]['Text_Color'];
                }

                $s['borderColor'] = $s['backgroundColor'];

            } else {
                $s['backgroundColor'] = $rescs[$r["idResource"]]['Background_Color'];
                $s['textColor'] = $rescs[$r["idResource"]]['Text_Color'];
                $s['borderColor'] = $rescs[$r["idResource"]]['Border_Color'];
            }


            $event = new Event($s, $timezone);
            $events[] = $event->toArray();
        }




        // Check reservations
        if ($uS->Reservation) {

            $query = "select * from vregister_resv where Status in ('" . ReservationStatus::Committed . "','" . ReservationStatus::UnCommitted . "','" . ReservationStatus::Waitlist . "') "
                    . " and DATE(Expected_Arrival) < DATE('" . $endDate->format('Y-m-d') . "') and DATE(Expected_Departure) > DATE('" . $beginDate->format('Y-m-d') . "') order by Expected_Arrival";

            $stmt = $dbh->query($query);

            $eventId = 9000;

            while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {


                if ($r['Status'] == ReservationStatus::Waitlist) {
                    $r["idResource"] = 0;

                }

                $startDT = new \DateTime($r['Expected_Arrival']);
                $stDT = new \DateTime($r['Expected_Arrival']);
                $stDT->setTime(10, 0, 0);

                $extended = FALSE;
                $now = new \DateTime();
                $s = array();

                $endDT = new \DateTime($r['Expected_Departure']);
                $clDate = new \DateTime($r['Expected_Departure']);
                $clDate->setTime(10, 0, 0);


                $dateInfo = getDate(strtotime($r['Expected_Arrival']));

                // start date fall on a holiday?
                $validHolidays = TRUE;
                $stYear = $stDT->format('Y');

                if ($stYear == $beginHolidays->getYear()) {
                    $myHolidays = $beginHolidays;
                } else if ($stYear == $endHolidays->getYear()) {
                    $myHolidays = $endHolidays;
                } else {
                    $validHolidays = FALSE;
                }


                // Start date fall on a holiday or is a non work weekday?
                if ($validHolidays === TRUE && ($myHolidays->is_holiday($stDT->format('U')) || array_search($dateInfo['wday'], $nonClean) !== FALSE)) {

                    $stDT->sub($p1d);

                    // Days before fall on a holiday too?
                    while ($myHolidays->is_holiday($stDT->format('U'))) {
                        $c = array(
                            'id' => 'H' . $eventId++,
                            'idReservation' => 0,
                            'resourceId' => $r["idResource"],
                            'start' => $stDT->format('Y-m-d\TH:i:00'),
                            'end' => $stDT->format('Y-m-d\TH:i:00'),
                            'idHosp' => 0,
                            'title' => HTMLContainer::generateMarkup('span', $rescs[$r["idResource"]]['Title'], array('style'=>'white-space:nowrap;')),
                            'allDay' => 1,
                            'backgroundColor' => 'black',
                            'textColor' => 'white',
                            'borderColor' => 'Yellow',

                        );

                        $event = new Event($c, $timezone);
                        $events[] = $event->toArray();

                        $stDT->sub($p1d);
                        $stDT->setTime(10, 0, 0);
                    }

                    $dateInfo = getDate($stDT->getTimestamp());
                    $limit = 5;

                    while (array_search($dateInfo['wday'], $nonClean) !== FALSE && $limit-- > 0) {
                        // Add a Cleaning Black-Out Event
                        $c = array(
                            'id' => 'c' . $eventId++,
                            'idReservation' => 0,
                            'resourceId' => $r["idResource"],
                            'idHosp' => 0,
                            'start' => $stDT->format('Y-m-d\TH:i:00'),
                            'end' => $stDT->format('Y-m-d\TH:i:00'),
                            'title' => HTMLContainer::generateMarkup('span', $rescs[$r["idResource"]]['Title'], array('style'=>'white-space:nowrap;')),
                            'allDay' => 1,
                            'backgroundColor' => 'black',
                            'textColor' => 'white',
                            'borderColor' => 'white',

                        );
                        $event = new Event($c, $timezone);
                        $events[] = $event->toArray();

                        $stDT->sub($p1d);
                        $dateInfo = getDate($stDT->format('U'));
                    }


                    // Days before fall on a holiday too?
                    while ($myHolidays->is_holiday($stDT->format('U'))) {
                        $c = array(
                            'id' => 'H' . $eventId++,
                            'idReservation' => 0,
                            'resourceId' => $r["idResource"],
                            'start' => $stDT->format('Y-m-d\TH:i:00'),
                            'end' => $stDT->format('Y-m-d\TH:i:00'),
                            'idHosp' => 0,
                            'title' => HTMLContainer::generateMarkup('span', $rescs[$r["idResource"]]['Title'], array('style'=>'white-space:nowrap;')),
                            'allDay' => 1,
                            'backgroundColor' => 'black',
                            'textColor' => 'white',
                            'borderColor' => 'Yellow',
                            "level" => $rescs[$r["idResource"]]["_level_"]
                        );
                        $event = new Event($c, $timezone);
                        $events[] = $event->toArray();

                        $stDT->sub($p1d);
                        $stDT->setTime(10, 0, 0);
                    }
                }


                $validHolidays = TRUE;
                $edYear = $clDate->format('Y');

                if ($edYear == $beginHolidays->getYear()) {
                    $myHolidays = $beginHolidays;
                } else if ($edYear == $endHolidays->getYear()) {
                    $myHolidays = $endHolidays;
                } else {
                    $validHolidays = FALSE;
                }

                if ($validHolidays) {
                    // End date fall on a holiday?
                    while ($myHolidays->is_holiday($clDate->format('U'))) {

                        $c = array(
                            'id' => 'H' . $eventId++,
                            'idReservation' => 0,
                            'resourceId' => $r["idResource"],
                            'start' => $clDate->format('Y-m-d\TH:i:00'),
                            'end' => $clDate->format('Y-m-d\TH:i:00'),
                            'idHosp' => 0,
                            'title' => HTMLContainer::generateMarkup('span', $rescs[$r["idResource"]]['Title'], array('style'=>'white-space:nowrap;')),
                            'allDay' => 1,
                            'backgroundColor' => 'black',
                            'textColor' => 'white',
                            'borderColor' => 'Yellow',
                            "level" => $rescs[$r["idResource"]]["_level_"]
                        );

                        $event = new Event($c, $timezone);
                        $events[] = $event->toArray();

                        $clDate->add($p1d);
                        $clDate->setTime(10, 0, 0);
                    }
                }
                // end date fall on non-cleaning day?
                $dateInfo = getDate(strtotime($r['Expected_Departure']));
                $limit = 5;

                while (array_search($dateInfo['wday'], $nonClean) !== FALSE && $limit-- > 0) {
                    // Add a Cleaning Black-Out Event
                    $c = array(
                        'id' => 'c' . $eventId++,
                        'idReservation' => 0,
                        'resourceId' => $r["idResource"],
                        'idHosp' => 0,
                        'start' => $clDate->format('Y-m-d\TH:i:00'),
                        'end' => $clDate->format('Y-m-d\TH:i:00'),
                        'title' => HTMLContainer::generateMarkup('span', $rescs[$r["idResource"]]['Title'], array('style'=>'white-space:nowrap;')),
                        'allDay' => 1,
                        'backgroundColor' => 'black',
                        'textColor' => 'white',
                        'borderColor' => 'white',
                        "level" => $rescs[$r["idResource"]]["_level_"]
                    );
                    $event = new Event($c, $timezone);
                    $events[] = $event->toArray();

                    $clDate->add($p1d);
                    $dateInfo = getDate($clDate->format('U'));
                }

                // Now End date fall on a holiday?
                while ($beginHolidays->is_holiday($clDate->format('U')) || $endHolidays->is_holiday($clDate->format('U'))) {
                    $c = array(
                        'id' => 'H' . $eventId++,
                        'idReservation' => 0,
                            'resourceId' => $r["idResource"],
                        'start' => $clDate->format('Y-m-d\TH:i:00'),
                        'end' => $clDate->format('Y-m-d\TH:i:00'),
                        'idHosp' => 0,
                        'title' => HTMLContainer::generateMarkup('span', $rescs[$r["idResource"]]['Title'], array('style'=>'white-space:nowrap;')),
                        'allDay' => 1,
                        'backgroundColor' => 'black',
                        'textColor' => 'white',
                        'borderColor' => 'Yellow',
                        "level" => $rescs[$r["idResource"]]["_level_"]
                    );
                    $event = new Event($c, $timezone);
                    $events[] = $event->toArray();

                    $clDate->add($p1d);
                    $clDate->setTime(10, 0, 0);
                }

                $endDT->sub(new \DateInterval("P1D"));


                $s['id'] = 'v' . $eventId++;
                $s['idReservation'] = $r['idReservation'];


                $spnArray = array('style'=>'white-space:nowrap;padding-left:2px;padding-right:2px;');

                if ($uS->GuestNameColor != '' && isset($r[$uS->GuestNameColor])) {
                    if (isset($nameColors[$r[$uS->GuestNameColor]])){
                        $spnArray['style'] .= 'background-color:' . $nameColors[$r[$uS->GuestNameColor]]['b'] . '; color:' . $nameColors[$r[$uS->GuestNameColor]]['t'] . ';';
                    }
                }

                $title = $r['Guest Last'];

                // Dont allow reservations to precede "today"
//                if ($startDT <= $now) {
//                    $startDT = new DateTime();
//                    $startDT->add($p1d);
//                    $startDT->setTime(16, 0, 0);
//                    $title = htmlentities('<<') . $title;
//                }


                $s['start'] = $startDT->format('Y-m-d\TH:i:00');
                $s['end'] = $endDT->format('Y-m-d\TH:i:00');
                $s['title'] = $title;
                $s['idHosp'] = $r['idHospital'];
                $s['idAssoc'] = $r['idAssociation'];
                $s['allDay'] = 1;
                $s['resourceId'] = $r["idResource"];

                if (strtolower($uS->RegColors) == 'hospital') {
                    // Use Association colors?
                    if ($r['idAssociation'] != $noAdminId && $r['idAssociation'] > 0) {
                        $s['backgroundColor'] = $hospitals[$r['idAssociation']]['Background_Color'];
                        $s['textColor'] = $hospitals[$r['idAssociation']]['Text_Color'];
                    } else {
                        $s['backgroundColor'] = $hospitals[$r['idHospital']]['Background_Color'];
                        $s['textColor'] = $hospitals[$r['idHospital']]['Text_Color'];
                    }

                    $s['borderColor'] = 'black';

                } else {
                    $s['backgroundColor'] = $rescs[$r["idResource"]]['Background_Color'];
                    $s['textColor'] = $rescs[$r["idResource"]]['Text_Color'];
                    $s['borderColor'] = 'black';
                }

                if ($r['Status'] == ReservationStatus::UnCommitted) {
                    $s['borderStyle'] = 'dashed';
                }

                $event = new Event($s, $timezone);
                $events[] = $event->toArray();

            }
        }

        return $events;
    }

        // Date Utilities
    //----------------------------------------------------------------------------------------------


    // Parses a string into a DateTime object, optionally forced into the given timezone.
    public static function parseDateTime($string, $timezone=null) {
      $date = new DateTime(
        $string,
        $timezone ? $timezone : new DateTimeZone('UTC')
          // Used only when the string is ambiguous.
          // Ignored if string has a timezone offset in it.
      );
      if ($timezone) {
        // If our timezone was ignored above, force it.
        $date->setTimezone($timezone);
      }
      return $date;
    }


    // Takes the year/month/date values of the given DateTime and converts them to a new DateTime,
    // but in UTC.
    public static function stripTime($datetime) {
      return new DateTime($datetime->format('Y-m-d'));
    }


}


class Event {

  // Tests whether the given ISO8601 string has a time-of-day or not
  const ALL_DAY_REGEX = '/^\d{4}-\d\d-\d\d$/'; // matches strings like "2013-12-29"

  public $title;
  public $allDay; // a boolean
  public $start; // a DateTime
  public $end; // a DateTime, or null
  public $properties = array(); // an array of other misc properties


  // Constructs an Event object from the given array of key=>values.
  // You can optionally force the timezone of the parsed dates.
  public function __construct($array, $timezone=null) {

    $this->title = $array['title'];

    if (isset($array['allDay'])) {
      // allDay has been explicitly specified
      $this->allDay = (bool)$array['allDay'];
    }
    else {
      // Guess allDay based off of ISO8601 date strings
      $this->allDay = preg_match(self::ALL_DAY_REGEX, $array['start']) &&
        (!isset($array['end']) || preg_match(self::ALL_DAY_REGEX, $array['end']));
    }

    if ($this->allDay) {
      // If dates are allDay, we want to parse them in UTC to avoid DST issues.
      $timezone = null;
    }

    // Parse dates
    $this->start = GuestRegister::parseDateTime($array['start'], $timezone);
    $this->end = isset($array['end']) ? GuestRegister::parseDateTime($array['end'], $timezone) : null;

    // Record misc properties
    foreach ($array as $name => $value) {
      if (!in_array($name, array('title', 'allDay', 'start', 'end'))) {
        $this->properties[$name] = $value;
      }
    }
  }


  // Returns whether the date range of our event intersects with the given all-day range.
  // $rangeStart and $rangeEnd are assumed to be dates in UTC with 00:00:00 time.
  public function isWithinDayRange($rangeStart, $rangeEnd) {

    // Normalize our event's dates for comparison with the all-day range.
    $eventStart = GuestRegister::stripTime($this->start);

    if (isset($this->end)) {
      $eventEnd = GuestRegister::stripTime($this->end); // normalize
    }
    else {
      $eventEnd = $eventStart; // consider this a zero-duration event
    }

    // Check if the two whole-day ranges intersect.
    return $eventStart < $rangeEnd && $eventEnd >= $rangeStart;
  }


  // Converts this Event object back to a plain data array, to be used for generating JSON
  public function toArray() {

    // Start with the misc properties (don't worry, PHP won't affect the original array)
    $array = $this->properties;

    $array['title'] = $this->title;

    // Figure out the date format. This essentially encodes allDay into the date string.
    if ($this->allDay) {
      $format = 'Y-m-d'; // output like "2013-12-29"
    }
    else {
      $format = 'c'; // full ISO8601 output, like "2013-12-29T09:00:00+08:00"
    }

    // Serialize dates into strings
    $array['start'] = $this->start->format($format);
    if (isset($this->end)) {
      $array['end'] = $this->end->format($format);
    }

    return $array;
  }

}




