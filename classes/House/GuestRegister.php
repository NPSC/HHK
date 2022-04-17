<?php

namespace HHK\House;

use HHK\sec\Session;
use HHK\SysConst\ResourceStatus;
use HHK\US_Holidays;
use HHK\House\Reservation\Reservation_1;
use HHK\SysConst\VisitStatus;
use HHK\SysConst\ReservationStatus;
use HHK\SysConst\CalEventKind;



/*
 * GuestRegister.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class GuestRegister {

    protected $noAssocId;

    public static function getCalendarRescs(\PDO $dbh, $startDate, $endDate, $timezone, $view, $rescGroupBy) {

        $uS = Session::getInstance();
        $rescs = array();

        if ($startDate == '') {
            return array();
        }

        if ($timezone == '') {
            $timezone = $uS->tz;
        }

        $beginDT = Event::parseDateTime($startDate, new \DateTimeZone($timezone));

        if ($endDate != '') {
            $endDT = Event::parseDateTime($endDate, new \DateTimeZone($timezone));
        } else if ($view != '') {

            $endDT = Event::parseDateTime($startDate, new \DateTimeZone($timezone));

            switch ($view) {
                case 'timeline1weeks':
                    $endDT->add(new \DateInterval('P1W'));
                    break;

                case 'timeline2weeks':
                    $endDT->add(new \DateInterval('P2W'));
                    break;

                case 'timeline3weeks':
                    $endDT->add(new \DateInterval('P3W'));
                    break;

                case 'timeline4weeks':
                    $endDT->add(new \DateInterval('P26W'));
                    break;

            }
        }

        //Resource grouping controls
        $rescGroups = readGenLookupsPDO($dbh, 'Room_Group');


        $genJoin = '';
        $genTableName = '';
        $orderBy = 'r.Util_Priority';

        foreach ($rescGroups as $g) {

        	if ($rescGroupBy === $g[0]) {

        		$genJoin = " left join `gen_lookups` g on g.`Table_Name` = '" . $g[2] . "' and g.`Code` = rm." . $g[0] . " ";
        		$orderBy = "g.`Order`, " . $orderBy;
        		$genTableName = $g[2];
        		break;
        	}
        }


        // Get list of resources
        $qu = "SELECT
    r.idResource as `id`,
    r.Title as `title`,
    r.Background_Color as `bgColor`,
    r.Text_Color as `textColor`,
    rm.Max_Occupants as `maxOcc`,
    rm.`Type`,
	rm.`Status`,
	rm.`Category`,
	rm.`Report_Category`,
    rm.`Floor`
from resource r
	left join
resource_use ru on r.idResource = ru.idResource  and ru.`Status` = '" . ResourceStatus::Unavailable . "'  and DATE(ru.Start_Date) <= DATE('" . $beginDT->format('Y-m-d') . "') and DATE(ru.End_Date) >= DATE('" . $endDT->format('Y-m-d') . "')
    left join resource_room rr on r.idResource = rr.idResource
    left join room rm on rr.idRoom = rm.idRoom
	$genJoin
where ru.idResource_use is null
 order by $orderBy;";

        $rstmt = $dbh->query($qu);
        $rawRescs = $rstmt->fetchAll(\PDO::FETCH_ASSOC);

        $roomGroups = array();

        $groups = readGenLookupsPDO($dbh, $genTableName, 'Order');

        // Count the room grouping types
        foreach ($rawRescs as $r) {

        	$notFound = TRUE;

        	foreach ($groups as $g) {

        		if ($g[0] == $r[$rescGroupBy]) {

        			$notFound = FALSE;

        			if (isset($roomGroups[$g[0]])) {
        				$roomGroups[$g[0]]['cnt']++;
        			} else {
        				$roomGroups[$g[0]]['cnt'] = 1;
        				$roomGroups[$g[0]]['title'] = $g[1];
        			}

        			break;
        		}
        	}

       		if ($notFound && $r[$rescGroupBy] == '') {

	       		if (isset($roomGroups[''])) {
	       			$roomGroups['']['cnt']++;
	       		} else {
	       			$roomGroups['']['cnt'] = 1;
	       			$roomGroups['']['title'] = 'not set';
	       		}
	       	}

	       	if ($notFound && $r[$rescGroupBy] != '') {

	        	if (isset($roomGroups[$r[$rescGroupBy]])) {
	        		$roomGroups[$r[$rescGroupBy]]['cnt']++;
	        	} else {
	        		$roomGroups[$r[$rescGroupBy]]['cnt'] = 1;
	        		$roomGroups[$r[$rescGroupBy]]['title'] = 'missing index: ' . $r[$rescGroupBy];
	        	}
	        }

	    }


        // Set the grouping totals in the group titles
        foreach ($rawRescs as $r) {

        	if (isset($r[$rescGroupBy]) && count($roomGroups) > 0 && isset($roomGroups[$r[$rescGroupBy]])) {
        		$r[$rescGroupBy] = htmlspecialchars_decode($roomGroups[$r[$rescGroupBy]]['title'], ENT_QUOTES) . ' (' . $roomGroups[$r[$rescGroupBy]]['cnt'] . ')';
        	}

            // Fix room title
            $r['title'] = htmlspecialchars_decode($r['title'], ENT_QUOTES);

            $rescs[] = $r;
        }

        // Add waitlist
        $rescs[] = array(
                'id' => 0,
        		'title' => ($genTableName != '' ? ' ' : 'Waitlist'),
                'bgColor' => '#333',
                //'textColor' => '#fff',
                'maxOcc' => 0,
                'Type' => 'Waitlist',
                'Floor' => 'Waitlist',
                'roomStatus' => '',
                'Category' => 'Waitlist',
                'Report_Category' => 'Waitlist'
            );

        return $rescs;

    }

    /**
     *
     * @param \PDO $dbh
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    public function getRegister(\PDO $dbh, $startTime, $endTime, $timezone) {

        $uS = Session::getInstance();
        $events = array();
        $p1d = new \DateInterval('P1D');
        $today = new \DateTime();
        $today->setTime(0, 0, 0);


        if ($startTime == "" || $endTime == "") {
            return $events;
        }

        if ($timezone == '') {
            $timezone = $uS->tz;
        }

        $beginDate = Event::parseDateTime($startTime, new \DateTimeZone($timezone));
        $endDate = Event::parseDateTime($endTime, new \DateTimeZone($timezone));

        // get list of hospital colors
        $hospitals = $this->getHospitals($dbh);

        $nameColors = $this->getGuestColors($dbh, $uS->GuestNameColor, $hospitals);

        // Get cleaning holidays for the current year(s)
        $beginHolidays = new US_Holidays($dbh, $beginDate->format('Y'));
        $endHolidays = new US_Holidays($dbh, $endDate->format('Y'));


        $nonClean = Reservation_1::loadNonCleaningDays($dbh);


        $this->getRoomOosEvents($dbh, $beginDate, $endDate, $timezone, $events);

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

                $dtendDate = new \DateTime($r['Span_End']);
                $dtendDate->setTime(10, 0, 0);

            } else {

                // Expected Departure

                $dtendDate = new \DateTime($r['Expected_Departure']);
                $dtendDate->setTime(10, 0, 0);

                if ($now > $dtendDate) {
                    $endDT = $now;
                    $extended = TRUE;
                } else {
                    $endDT = new \DateTime($r['Expected_Departure']);
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
                $this->addVisitBlackouts($events, $myHolidays, $dtendDate, $timezone, $r["idResource"], $nonClean);
            }

            $backgroundBorderColor = $this->addBackgroundEvent($r, $hospitals, $startDT, $endDT, $timezone, $uS->RegColors, $events);

            // show event on first day of calendar
            if ($endDT->format('Y-m-d') == $beginDate->format('Y-m-d') && $extended) {
                $endDT->add(new \DateInterval('P1D'));
            }

            // Render Event
            $titleText = htmlspecialchars_decode($r['Guest Last'] . ($r['Ribbon_Note'] ? " - " . $r['Ribbon_Note']: ''), ENT_QUOTES);
            $visitExtended = FALSE;

            if ($r['Visit_Status'] == VisitStatus::NewSpan) {
                $titleText .= ' (rm)';
            }

            if ($r['Visit_Status'] == VisitStatus::ChangeRate) {
                $titleText .= ' ($)';
            }

            if ($extended) {
                $visitExtended = TRUE;
            }

            $statusText = $r['Status_Text'];

            // Check for on leave
            if (isset($r['On_Leave']) && $r['On_Leave'] > 0) {
                $titleText .= ' (On Leave)';
                $statusText = 'On Leave';
            }

            // Set ribbon color
            $this->setRibbonColors($uS->GuestNameColor, $r, $s, $nameColors);

            //
            $s['id'] = 'v' . $r['id'];
            $s['idVisit'] = $r['idVisit'];
            $s['Span'] = $r['Span'];
            $s['idHosp'] = htmlspecialchars_decode($r['idHospital'], ENT_QUOTES);
            $s['idAssoc'] = htmlspecialchars_decode($r['idAssociation'], ENT_QUOTES);
            $s['hospName'] = $hospitals[$r['idHospital']]['Title'];
            $s['resourceId'] = $r["idResource"];
            $s['idResc'] = $r["idResource"];
            $s['start'] = $startDT->format('Y-m-d\TH:i:00');
            $s['end'] = $endDT->format('Y-m-d\TH:i:00');
            $s['title'] = $titleText;
            $s['guests'] = $r['Guest_Count'];
            $s['extended'] = $visitExtended;
            $s['allDay'] = 1;
            $s['fullName'] = htmlspecialchars_decode($r['Name_Full'], ENT_QUOTES);
            $s['visitStatus'] = $statusText;
            $s['vStatusCode'] = $r['Visit_Status'];
            $s['borderColor'] = $backgroundBorderColor;
            $event = new Event($s, $timezone);
            $events[] = $event->toArray();

        }




    // Reservations
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
                        'kind' => CalEventKind::BO,
                        'editable' => false,
                        'resourceId' => $r["idResource"],
                        'start' => $stDT->format('Y-m-d\TH:i:00'),
                        'end' => $stDT->format('Y-m-d\TH:i:00'),
                        'title' => 'H',
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
                        'id' => 'BO' . $eventId++,
                        'kind' => CalEventKind::BO,
                        'editable' => false,
                        'resourceId' => $r["idResource"],
                        'start' => $stDT->format('Y-m-d\TH:i:00'),
                        'end' => $stDT->format('Y-m-d\TH:i:00'),
                        'title' => 'BO',
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
                        'kind' => CalEventKind::BO,
                        'editable' => false,
                        'resourceId' => $r["idResource"],
                        'start' => $stDT->format('Y-m-d\TH:i:00'),
                        'end' => $stDT->format('Y-m-d\TH:i:00'),
                        'title' => 'H',
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
                        'kind' => CalEventKind::BO,
                        'editable' => false,
                        'resourceId' => $r["idResource"],
                        'start' => $clDate->format('Y-m-d\TH:i:00'),
                        'end' => $clDate->format('Y-m-d\TH:i:00'),
                        'title' => 'H',
                        'allDay' => 1,
                        'backgroundColor' => 'black',
                        'textColor' => 'white',
                        'borderColor' => 'Yellow',

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
                    'id' => 'BO' . $eventId++,
                    'kind' => CalEventKind::BO,
                    'editable' => false,
                    'resourceId' => $r["idResource"],
                    'start' => $clDate->format('Y-m-d\TH:i:00'),
                    'end' => $clDate->format('Y-m-d\TH:i:00'),
                    'title' => 'BO',
                    'allDay' => 1,
                    'backgroundColor' => 'black',
                    'textColor' => 'white',
                    'borderColor' => 'white',

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
                    'kind' => CalEventKind::BO,
                    'editable' => FALSE,
                    'resourceId' => $r["idResource"],
                    'start' => $clDate->format('Y-m-d\TH:i:00'),
                    'end' => $clDate->format('Y-m-d\TH:i:00'),
                    'title' => 'H',
                    'allDay' => 1,
                    'backgroundColor' => 'black',
                    'textColor' => 'white',
                    'borderColor' => 'Yellow',

                );
                $event = new Event($c, $timezone);
                $events[] = $event->toArray();

                $clDate->add($p1d);
                $clDate->setTime(10, 0, 0);
            }

            //$endDT->sub(new \DateInterval("P1D"));

            // Waitlist omit background event.
            if ($r['idResource'] != 0 && $r['idHospital'] > 0) {
                $backgroundBorderColor = $this->addBackgroundEvent($r, $hospitals, $startDT, $endDT, $timezone, $uS->RegColors, $events);
            }

            $s['id'] = 'r' . $eventId++;
            $s['idReservation'] = $r['idReservation'];
            $s['className'] = 'hhk-schrm';
            $s['borderColor'] = '#111';

            // Set ribbon color  htmlspecialchars_decode($r['title'], ENT_QUOTES);
            $this->setRibbonColors($uS->GuestNameColor, $r, $s, $nameColors);

            $s['start'] = $startDT->format('Y-m-d\TH:i:00');
            $s['end'] = $endDT->format('Y-m-d\TH:i:00');
            $s['title'] = '<span id="' . $r['idReservation'] . '" class="hhk-schrm ui-icon ui-icon-arrowthick-2-n-s" style="background-color:white; border:1px solid black;  margin-right:.3em;"></span>' . htmlspecialchars_decode($r['Guest Last'] . ($r['Ribbon_Note'] ? " - " . $r['Ribbon_Note']: ''), ENT_QUOTES);
            $s['hospName'] = htmlspecialchars_decode($hospitals[$r['idHospital']]['Title'], ENT_QUOTES);
            $s['idHosp'] = $r['idHospital'];
            $s['idAssoc'] = $r['idAssociation'];
            $s['allDay'] = 1;
            $s['resourceId'] = $r["idResource"];
            $s['idResc'] = $r["idResource"];
            $s['resvStatus'] = $r['Status_Text'];
            $s['status'] = $r['Status'];
            $s['fullName'] = htmlspecialchars_decode($r['Name_Full'], ENT_QUOTES);

            $event = new Event($s, $timezone);
            $events[] = $event->toArray();

        }

        return $events;
    }



    protected function addBackgroundEvent($r, $hospitals, $startDT, $endDT, $timezone, $regColors, &$events) {
        $backgroundBorderColor = '';

            // Use Association colors?
        if (strtolower($regColors) == 'hospital') {

            $h = array();

            // Background Event
            $h['rendering'] = 'background';
            $h['kind'] = CalEventKind::BAK;
            $h['editable'] = FALSE;
            $h['id'] = 'b' . (isset($r['id']) ? $r['id'] : $r['idReservation']);
            $h['idHosp'] = $r['idHospital'];
            $h['idAssoc'] = $r['idAssociation'];
            $h['resourceId'] = $r["idResource"];

            $h['start'] = $startDT->format('Y-m-d\TH:i:00');
            $h['end'] = $endDT->format('Y-m-d\TH:i:00');
            $h['title'] = '';
            $h['allDay'] = 1;


            if ($r['idAssociation'] != $this->noAssocId && $r['idAssociation'] > 0) {

            	if (isset($hospitals[$r['idAssociation']])) {
                	$h['backgroundColor'] = $hospitals[$r['idAssociation']]['Background_Color'];
            	}

            } else if (isset($hospitals[$r['idHospital']])) {
                $h['backgroundColor'] = $hospitals[$r['idHospital']]['Background_Color'];
            }

            $h['borderColor'] = $h['backgroundColor'];
            $backgroundBorderColor = $h['borderColor'];

            $hEvent = new Event($h, $timezone);
            $events[] = $hEvent->toArray();
        }

        return $backgroundBorderColor;
    }

    protected function addVisitBlackouts(&$events, $myHolidays, $dtendDate, $timezone, $idResc, $nonClean) {

        $p1d = new \DateInterval('P1D');

        while ($myHolidays->is_holiday($dtendDate->format('U'))) {
                    $c = array(
                        'id' => 'H' . $idResc,
                        'kind' => CalEventKind::BO,
                        'editable' => FALSE,
                        'resourceId' => $idResc,
                        'start' => $dtendDate->format('Y-m-d\TH:i:00'),
                        'end' => $dtendDate->format('Y-m-d\TH:i:00'),
                        'title' => 'H',
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
                        'id' => 'BO' . $idResc,
                        'kind' => CalEventKind::BO,
                        'editable' => FALSE,
                        'resourceId' => $idResc,
                        'start' => $dtendDate->format('Y-m-d\TH:i:00'),
                        'end' => $dtendDate->format('Y-m-d\TH:i:00'),
                        'title' => 'BO',
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
                        'id' => 'H' . $idResc,
                        'kind' => CalEventKind::BO,
                        'editable' => FALSE,
                        'resourceId' => $idResc,
                        'start' => $dtendDate->format('Y-m-d\TH:i:00'),
                        'end' => $dtendDate->format('Y-m-d\TH:i:00'),
                        'title' => 'H',
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

    protected function getRoomOosEvents(\PDO $dbh, \DateTime $beginDate, \DateTime $endDate, $timezone, &$events) {

        $idCounter = 10;

        $query1 = "SELECT
    ru.*, g.Description AS `StatusTitle`, ifnull(gr.Description, 'Undefined') as `reasonTitle`
FROM
    resource_use ru
        LEFT JOIN
    gen_lookups g ON g.Table_Name = 'Resource_Status'
        AND g.Code = ru.Status        LEFT JOIN
    gen_lookups gr ON gr.Table_Name = 'OOS_Codes'
        AND gr.Code = ru.OOS_Code
where DATE(ru.Start_Date) < DATE('" . $endDate->format('Y-m-d') . "') and ifnull(DATE(ru.End_Date), DATE(now())) > DATE('" . $beginDate->format('Y-m-d') . "');";

        $stmtrs = $dbh->query($query1);

        while ($r = $stmtrs->fetch(\PDO::FETCH_ASSOC)) {

            if ($r["idResource"] == 0) {
                continue;
            }

            $stDateDT = new \Datetime($r['Start_Date']);
            $enDateDT = new \DateTime($r['End_Date']);

            // Filter Unavailable events.
            if ($r['Status'] == ResourceStatus::Unavailable) {

                if (($stDateDT >= $beginDate && $stDateDT < $endDate) || ($enDateDT > $beginDate && $enDateDT <= $endDate)) {
                    // take it.
                } else {
                    continue;
                }
            }

            // Set Start and end for fullCalendar control
            $c = array(
                'id' => 'RR' . $idCounter++,
                'kind' => CalEventKind::OOS,
                'resourceId' => $r["idResource"],
                'reason' => $r['reasonTitle'],
            		'start' => $stDateDT->format('Y-m-d\TH:i:00'),
            		'end' => $enDateDT->format('Y-m-d\TH:i:00'),
                'title' => $r['StatusTitle'],
                'allDay' => 1,
                'backgroundColor' => 'gray',
                'textColor' => 'white',
                'borderColor' => 'black',
            );

            $event = new Event($c, $timezone);
            $events[] = $event->toArray();

        }
    }

    protected function getGuestColors(\PDO $dbh, $guestDemographic, $hospitals) {

        $nameColors = array();

        if (strtolower($guestDemographic) == 'hospital') {

            foreach($hospitals as $h) {

                $nameColors[$h['idHospital']] = array(
                    't' => trim(strtolower($h['Text_Color'])),
                    'b' => trim(strtolower($h['Background_Color']))
                );
            }

        // Get guest name colorings
        } else if ($guestDemographic != '') {

            $demogs = readGenLookupsPDO($dbh, $guestDemographic);

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

        return $nameColors;

    }

    protected function getHospitals(\PDO $dbh) {

        $hospitals = array(0 => array('Title'=>'', 'idHospital'=>0, 'Background_Color'=>'blue', 'Text_Color'=>'white'));
        $this->noAssocId = 0;

        $hstmt = $dbh->query("Select IF(status='r', concat(Title, ' (Retired)'), Title) as Title, idHospital, Reservation_Style as Background_Color, Stay_Style as Text_Color from hospital;"); // where `Status` = 'a'

        foreach ($hstmt->fetchAll(\PDO::FETCH_ASSOC) as $h) {

            $h['Title'] = htmlspecialchars_decode($h['Title'], ENT_QUOTES);
            $hospitals[$h['idHospital']] = $h;

            if ($h['Title'] == '(None)') {
                $this->noAssocId = $h['idHospital'];
            }
        }

        return $hospitals;
    }

    protected function setRibbonColors($colorIndex, $r, &$s, $nameColors) {

        // Set ribbon color
        if ($colorIndex != '') {

            if (isset($r[$colorIndex]) && isset($nameColors[$r[$colorIndex]])){

                // Use Demographics colors
                $s['backgroundColor'] = $nameColors[$r[$colorIndex]]['b'];
                $s['textColor'] = $nameColors[$r[$colorIndex]]['t'];

            } else if (isset($nameColors[$r['idHospital']])) {

                // Use Hospital colors
                if ($r['idAssociation'] != $this->noAssocId && $r['idAssociation'] > 0 && isset($nameColors[$r['idAssociation']])) {
                    // Association color overrides the hospital color.
                    $s['backgroundColor'] = $nameColors[$r['idAssociation']]['b'];
                    $s['textColor'] = $nameColors[$r['idAssociation']]['t'];
                } else {
                    $s['backgroundColor'] = $nameColors[$r['idHospital']]['b'];
                    $s['textColor'] = $nameColors[$r['idHospital']]['t'];
                }
            }
        }
    }

    protected function isHexColor(string $color){

        if(preg_match('/^#[a-f0-9]{6}$/i', $color)){
            return true;
        }else{
            return false;
        }
    }

}


?>