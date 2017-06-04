<?php

/*
 * Register.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Register
 *
 * @author Eric
 */
class Register {

    /**
     *
     * @param PDO $dbh
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    public static function getRegister(\PDO $dbh, $startTime, $endTime) {

        $uS = Session::getInstance();
        $events = array();
        $p1d = new \DateInterval('P1D');
        $today = new \DateTime();
        $today->setTime(0, 0, 0);


        if ($startTime == "" || $endTime == "") {
            return $events;
        }

        $beginDate = new \DateTime(date("Y-m-d", $startTime));
        $endDate = new \DateTime(date("Y-m-d", $endTime));

        // Get list of resources
        $qu = "select r.*, ru.idResource_use
from resource r
	left join
resource_use ru on r.idResource = ru.idResource and ru.`Status` = '" . ResourceStatus::Unavailable . "' and DATE(ru.Start_Date) <= DATE('" . $beginDate->format('Y-m-d') . "') and DATE(ru.End_Date) >= DATE('" . $endDate->format('Y-m-d') . "')
 where ru.idResource_use is null
 order by Util_Priority;";
        $rstmt = $dbh->query($qu);
        $rescs = array();
        $level = 0;

        foreach ($rstmt->fetchAll(\PDO::FETCH_ASSOC) as $re) {
            $re["_level_"] = $level++;
            $rescs[$re["idResource"]] = $re;
        }


        // Add waitlist resource id = 0
        $rescs[0] = array(
            'idResource' => 0,
            'Border_Color' => 'grey',
            'Background_Color' => '#333333',
            'Text_Color' => 'white',
            '_level_' => $level,
            'Status' => RoomAvailable::Unavailable,
            'Title' => 'Waitlist');

        // get list of hospital colors
        $hospitals = array(0 => array('idHospital'=>0, 'Background_Color'=>'blue', 'Text_Color'=>'white'));
        if ($uS->RegColors) {
            $hstmt = $dbh->query("Select idHospital, Reservation_Style as Background_Color, Stay_Style as Text_Color from hospital where Title != '(None)'");
            foreach ($hstmt->fetchAll(\PDO::FETCH_ASSOC) as $h) {
                $hospitals[$h['idHospital']] = $h;
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

            if ($r["idResource"] == 0 || isset($rescs[$r["idResource"]]) === FALSE) {
                continue;
            }

            $startDT = new \DateTime($r['Start_Date']);
            $endDT = new \DateTime($r['End_Date']);
            $endDT->setTime(10, 0, 0);
            $endDT->sub($p1d);

            // Determine if the resource is in use "TODAY"
            $now = new \DateTime();
            if (($startDT <= $now && $endDT >= $now) || $startDT->format('Y-m-d') == $now->format('Y-m-d')) {
                $rescUsed[$r["idResource"]] = 'y';
            }

            // Set Start and end for fullCalendar control
            $c = array(
                'id' => 'RR' . $eventCounter++,
                'idReservation' => 0,
                'idResc' => $r["idResource"],
                'Span' => 0,
                'idHosp' => 0,
                'start' => $startDT->format('Y-m-d\TH:i:00'),
                'end' => $endDT->format('Y-m-d\TH:i:00'),
                'title' => HTMLContainer::generateMarkup('span', $rescs[$r["idResource"]]['Title'] . ' - ' . $r['StatusTitle'], array('style'=>'white-space:nowrap;')),
                'allDay' => 1,
                'backgroundColor' => 'gray',
                'textColor' => 'white',
                'borderColor' => 'black',
                "level" => $rescs[$r["idResource"]]["_level_"]
            );


            $events[] = $c;

        }



        // Visits
        $query = "select * from vregister where Visit_Status <> '" . VisitStatus::Pending . "' and
    DATE(Span_Start) < DATE('" . $endDate->format('Y-m-d') . "') and ifnull(DATE(Span_End), case when DATE(now()) > DATE(Expected_Departure) then DATE(now()) else DATE(Expected_Departure) end) >= DATE('" .$beginDate->format('Y-m-d') . "');";
        $stmtv = $dbh->query($query);

        while ($r = $stmtv->fetch(\PDO::FETCH_ASSOC)) {

            if ($r["idResource"] == 0 || isset($rescs[$r["idResource"]]) === FALSE) {
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
                        'idResc' => 0,
                        'Span' => 0,
                        'idHosp' => 0,
                        'start' => $dtendDate->format('Y-m-d\TH:i:00'),
                        'end' => $dtendDate->format('Y-m-d\TH:i:00'),
                        'title' => HTMLContainer::generateMarkup('span', $rescs[$r["idResource"]]['Title'], array('style'=>'white-space:nowrap;')),
                        'allDay' => 1,
                        'backgroundColor' => 'black',
                        'textColor' => 'white',
                        'borderColor' => 'Yellow',
                        "level" => $rescs[$r["idResource"]]["_level_"]
                    );

                    $events[] = $c;

                    // Determine if the resource is in use "TODAY"
                    if ($dtendDate->format('Y-m-d') == $now->format('Y-m-d')) {
                        $rescUsed[$r["idResource"]] = 'y';
                    }

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
                        'idResc' => 0,
                        'Span' => 0,
                        'idHosp' => 0,
                        'start' => $dtendDate->format('Y-m-d\TH:i:00'),
                        'end' => $dtendDate->format('Y-m-d\TH:i:00'),
                        'title' => HTMLContainer::generateMarkup('span', $rescs[$r["idResource"]]['Title'], array('style'=>'white-space:nowrap;')),
                        'allDay' => 1,
                        'backgroundColor' => 'black',
                        'textColor' => 'white',
                        'borderColor' => 'white',
                        "level" => $rescs[$r["idResource"]]["_level_"]
                    );
                    $events[] = $c;


                    // Determine if the resource is in use "TODAY"
                    if ($dtendDate->format('Y-m-d') == $now->format('Y-m-d')) {
                        $rescUsed[$r["idResource"]] = 'y';
                    }

                    $dtendDate->add($p1d);
                    $dtendDate->setTime(10, 0, 0);
                    $dateInfo = getDate($dtendDate->format('U'));
                }


                // Check for holidays again?
                while ($myHolidays->is_holiday($dtendDate->format('U'))) {
                    $c = array(
                        'id' => 'H' . $eventCounter++,
                        'idReservation' => 0,
                        'idResc' => 0,
                        'Span' => 0,
                        'idHosp' => 0,
                        'start' => $dtendDate->format('Y-m-d\TH:i:00'),
                        'end' => $dtendDate->format('Y-m-d\TH:i:00'),
                        'title' => HTMLContainer::generateMarkup('span', $rescs[$r["idResource"]]['Title'], array('style'=>'white-space:nowrap;')),
                        'allDay' => 1,
                        'backgroundColor' => 'black',
                        'textColor' => 'white',
                        'borderColor' => 'Yellow',
                        "level" => $rescs[$r["idResource"]]["_level_"]
                    );
                    $events[] = $c;

                    // Determine if the resource is in use "TODAY"
                    if ($dtendDate->format('Y-m-d') == $now->format('Y-m-d')) {
                        $rescUsed[$r["idResource"]] = 'y';
                    }

                    $dtendDate->add($p1d);
                    $dtendDate->setTime(10, 0, 0);
                }
            }


            // Determine if the resource is in use "TODAY"
            if (($startDT <= $now && $dtend >= $now) || $startDT->format('Y-m-d') == $now->format('Y-m-d')) {
                $rescUsed[$r["idResource"]] = 'y';
            }

            $title = $rescs[$r["idResource"]]['Title'] . '-' . $r['Guest Last']; //$r['title'];
            if ($r['Guest Last'] == '') {
                $title = $rescs[$r["idResource"]]['Title'];  //$r['title'];
            }

            if ($r['Visit_Status'] == VisitStatus::NewSpan) {
                $title = HTMLContainer::generateMarkup('span', $title.' (rm)', array( 'title'=>'Room Changed', 'style'=>'white-space:nowrap;'));
            } else if ($r['Visit_Status'] == VisitStatus::ChangeRate) {
                $title = HTMLContainer::generateMarkup('span', $title.' ($)', array( 'title'=>'Rate Changed', 'style'=>'white-space:nowrap;'));
            } else {
                $title = HTMLContainer::generateMarkup('span', ($extended ? $title . htmlentities('>>') : $title), array('style'=>'white-space:nowrap;'));
            }

            // Set Start and end for fullCalendar control
            $s['id'] = $r['id'];
            $s['idVisit'] = $r['idVisit'];
            $s['Span'] = $r['Span'];
            $s['idHosp'] = $r['idHospital'];
            $s['idAssoc'] = $r['idAssociation'];


            $s['start'] = $startDT->format('Y-m-d\TH:i:00');
            $s['end'] = $endDT->format('Y-m-d\TH:i:00');
            $s['title'] = $title;
            $s['allDay'] = 1;


            if ($uS->RegColors == 'hospital') {
                $s['backgroundColor'] = ($r['idAssociation'] > 0 ? $hospitals[$r['idAssociation']]['Background_Color'] : $hospitals[$r['idHospital']]['Background_Color']);
                $s['textColor'] = ($r['idAssociation'] > 0 ? $hospitals[$r['idAssociation']]['Text_Color'] : $hospitals[$r['idHospital']]['Text_Color']);
                $s['borderColor'] = $s['backgroundColor'];
            } else {
                $s['backgroundColor'] = $rescs[$r["idResource"]]['Background_Color'];
                $s['textColor'] = $rescs[$r["idResource"]]['Text_Color'];
                $s['borderColor'] = $rescs[$r["idResource"]]['Border_Color'];
            }

            $s["level"] = $rescs[$r["idResource"]]["_level_"];


            $events[] = $s;
        }




        // Check reservations
        if ($uS->Reservation) {

            $query = "select * from vreservation_events where Status in ('" . ReservationStatus::Committed . "','" . ReservationStatus::UnCommitted . "','" . ReservationStatus::Waitlist . "') "
                    . " and DATE(Expected_Arrival) < DATE('" . $endDate->format('Y-m-d') . "') and DATE(Expected_Departure) > DATE('" . $beginDate->format('Y-m-d') . "') order by Expected_Arrival";

            $stmt = $dbh->query($query);

            $eventId = 9000;

            while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {


                if ($r['Status'] == ReservationStatus::Waitlist) {
                    $r["idResource"] = 0;
                    $rescs[0]["_level_"] ++;
                }

                // Resource deleted out from under us?
                if (isset($rescs[$r["idResource"]]) === FALSE) {
                    continue;
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
                        $events[] = $c;

                        // Determine if the resource is in use "TODAY"
                        if ($stDT->format('Y-m-d') == $now->format('Y-m-d')) {
                            $rescUsed[$r["idResource"]] = 'y';
                        }

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
                            'idHosp' => 0,
                            'start' => $stDT->format('Y-m-d\TH:i:00'),
                            'end' => $stDT->format('Y-m-d\TH:i:00'),
                            'title' => HTMLContainer::generateMarkup('span', $rescs[$r["idResource"]]['Title'], array('style'=>'white-space:nowrap;')),
                            'allDay' => 1,
                            'backgroundColor' => 'black',
                            'textColor' => 'white',
                            'borderColor' => 'white',
                            "level" => $rescs[$r["idResource"]]["_level_"]
                        );
                        $events[] = $c;

                        // Determine if the resource is in use "TODAY"
                        if ($stDT->format('Y-m-d') == $now->format('Y-m-d')) {
                            $rescUsed[$r["idResource"]] = 'y';
                        }

                        $stDT->sub($p1d);
                        $dateInfo = getDate($stDT->format('U'));
                    }


                    // Days before fall on a holiday too?
                    while ($myHolidays->is_holiday($stDT->format('U'))) {
                        $c = array(
                            'id' => 'H' . $eventId++,
                            'idReservation' => 0,
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
                        $events[] = $c;

                        // Determine if the resource is in use "TODAY"
                        if ($stDT->format('Y-m-d') == $now->format('Y-m-d')) {
                            $rescUsed[$r["idResource"]] = 'y';
                        }

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

                        $events[] = $c;

                        // Determine if the resource is in use "TODAY"
                        if ($clDate->format('Y-m-d') == $now->format('Y-m-d')) {
                            $rescUsed[$r["idResource"]] = 'y';
                        }

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
                    $events[] = $c;

                    // Determine if the resource is in use "TODAY"
                    if ($clDate->format('Y-m-d') == $now->format('Y-m-d')) {
                        $rescUsed[$r["idResource"]] = 'y';
                    }

                    $clDate->add($p1d);
                    $dateInfo = getDate($clDate->format('U'));
                }

                // Now End date fall on a holiday?
                while ($beginHolidays->is_holiday($clDate->format('U')) || $endHolidays->is_holiday($clDate->format('U'))) {
                    $c = array(
                        'id' => 'H' . $eventId++,
                        'idReservation' => 0,
//                    'Span' => 0,
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
                    $events[] = $c;

                    // Determine if the resource is in use "TODAY"
                    if ($clDate->format('Y-m-d') == $now->format('Y-m-d')) {
                        $rescUsed[$r["idResource"]] = 'y';
                    }

                    $clDate->add($p1d);
                    $clDate->setTime(10, 0, 0);
                }

                $endDT->sub(new \DateInterval("P1D"));


                $s['id'] = 'v' . $eventId++;
                $s['idReservation'] = $r['idReservation'];


                $title = HTMLContainer::generateMarkup('span', $rescs[$r["idResource"]]['Title'], array('id'=>'hkr'.$r['idReservation'], 'style'=>'white-space:nowrap;', 'class'=>'hhk-schrm', 'title'=>'Click to Change Rooms'))
                        . '-' . $r['Guest Last'];

                // Dont allow reservations to precede "today"
//                if ($startDT <= $now) {
//                    $startDT = new DateTime();
//                    $startDT->add($p1d);
//                    $startDT->setTime(16, 0, 0);
//                    $title = htmlentities('<<') . $title;
//                } else {
                    // Determine if the resource is in use "TODAY"
                    if (($startDT <= $now && $endDT >= $now) || $startDT->format('Y-m-d') == $now->format('Y-m-d')) {
                        $rescUsed[$r["idResource"]] = 'y';
                    }
//                }

                $title = HTMLContainer::generateMarkup('span', $title, array('style'=>'white-space:nowrap;width:100%;', 'title'=>'Click to go to Reservation Page'));

                $s['start'] = $startDT->format('Y-m-d\TH:i:00');
                $s['end'] = $endDT->format('Y-m-d\TH:i:00');
                $s['title'] = $title;
                $s['idHosp'] = $r['idHospital'];
                $s['idAssoc'] = $r['idAssociation'];
                $s['allDay'] = 1;

                if ($uS->RegColors == 'hospital') {
                    $s['backgroundColor'] = ($r['idAssociation'] > 0 ? $hospitals[$r['idAssociation']]['Background_Color'] : $hospitals[$r['idHospital']]['Background_Color']);
                    $s['textColor'] = ($r['idAssociation'] > 0 ? $hospitals[$r['idAssociation']]['Text_Color'] : $hospitals[$r['idHospital']]['Text_Color']);
                    $s['borderColor'] = 'black';
                } else {
                    $s['backgroundColor'] = $rescs[$r["idResource"]]['Background_Color'];
                    $s['textColor'] = $rescs[$r["idResource"]]['Text_Color'];
                    $s['borderColor'] = 'black';  //$rescs[$r["idResource"]]['Border_Color'];
                }

                if ($r['Status'] == ReservationStatus::UnCommitted) {
                    $s['borderStyle'] = 'dashed';
                }

                $s["level"] = $rescs[$r["idResource"]]["_level_"];

                $events[] = $s;
            }
        }

        // Now add unused resources
        foreach ($rescs as $re) {
            if (isset($rescUsed[$re['idResource']]) === FALSE && $re['idResource'] > 0) {
                $events[] = array(
                    "id" => "r" . $re["_level_"] . "u",
                    "start" => date("Y-m-d\T10:00:00"),
                    "end" => date("Y-m-d\T11:10:00"),
                    'idHosp' => 0,
                    'idResc' => $re['idResource'],
                    "title" => HTMLContainer::generateMarkup('span', $re["Title"], array('style'=>'white-space:nowrap;')),
                    'allDay' => 1,
                    'backgroundColor' => 'white',
                    'textColor' => ($re["Status"] != RoomAvailable::Unavailable ? 'black' : 'gray'),
                    'borderColor' => $re["Border_Color"],
                    "level" => $re["_level_"]
                );
            }
        }

        // Add Waitlist marker
        if ($uS->Reservation) {
            $events[] = array(
                "id" => "wl" . $level,
                "start" => date("Y-m-d\T10:00:00"),
                "end" => date("Y-m-d\T11:10:00"),
                'idHosp' => 0,
                'idResc' => 0,
                "title" => 'Waitlist',
                'allDay' => 1,
                'backgroundColor' => 'black',
                'textColor' => 'white',
                'borderColor' => 'black',
                "level" => $level
            );
        }


        return $events;
    }

}
