<?php

namespace HHK\House;

use HHK\sec\Session;
use HHK\SysConst\CalendarStatusColors;
use HHK\SysConst\ResourceStatus;
use HHK\US_Holidays;
use HHK\House\Reservation\Reservation_1;
use HHK\SysConst\RoomState;
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
    protected $ribbonColors;
    protected $robbonBottomColors;
    const WAITLIST_RESC_ID = '9999';

    public static function getCalendarRescs(\PDO $dbh, $startDate, $endDate, $timezone, $rescGroupBy) {

        $uS = Session::getInstance();
        $rescs = array();

        if ($startDate == '') {
            return array();
        }

        if ($timezone == '') {
            $timezone = $uS->tz;
        }

        $beginDT = self::parseDateTime($startDate, new \DateTimeZone($timezone));

        if ($endDate != '') {
            $endDT = self::parseDateTime($endDate, new \DateTimeZone($timezone));

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
    concat('id-', r.idResource) as `id`,
    r.idResource as `idResc`,
    r.Title as `title`,
    r.Background_Color as `bgColor`,
    r.Text_Color as `textColor`,
    rm.Max_Occupants as `maxOcc`,
    rm.`Type`,
	rm.`Status`,
    ifnull(stat.Description, 'Unknown') as `Status_Text`,
	rm.`Category`,
	rm.`Report_Category`,
    rm.`Floor`,
    r.`Util_Priority`
from resource r
	left join
resource_use ru on r.idResource = ru.idResource  and ru.`Status` = '" . ResourceStatus::Unavailable . "'  and DATE(ru.Start_Date) <= DATE('" . $beginDT->format('Y-m-d') . "') and DATE(ru.End_Date) >= DATE('" . $endDT->format('Y-m-d') . "')
    left join resource_room rr on r.idResource = rr.idResource
    left join room rm on rr.idRoom = rm.idRoom
    left join gen_lookups stat on stat.Table_Name = 'Room_Status' and stat.Code = rm.Status
	$genJoin
where ru.idResource_use is null
    and (r.Retired_At is null or r.Retired_At > '" . $beginDT->format('Y-m-d') . "')
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

            $r['hoverText'] = '';

            // Room color
            switch($uS->Room_Colors) {
                case 'housekeeping': //use housekeeping status colors
                    if ($r['Status'] == RoomState::TurnOver || $r['Status'] == RoomState::Dirty) {
                        $r['bgColor'] = 'yellow';
                    } else if ($r['Status'] == RoomState::Ready) {
                        $r['bgColor'] = '#3fff0f';
                    }else{
                        $r['bgColor'] = '';
                    }

                    $r['textColor'] = '';
                    $r['hoverText'] .= "Status: " . $r['Status_Text'] . " | ";
                    break;
                case 'room': //use Resource Builder room colors
                    break;
                default: //None
                    $r['bgColor'] =  '';
                    $r['textColor'] = '';
            }

            //Room hover text
            $r['hoverText'] .= "Maximum Occupants: " . $r['maxOcc'];

            //cast priority to int
            $r['Util_Priority'] = intval($r['Util_Priority'], 10);

            $rescs[] = $r;
        }

        // Add waitlist
        $rescs[] = array(
                'id' => "id-" . self::WAITLIST_RESC_ID,
                'idResc' => self::WAITLIST_RESC_ID,
        		'title' => ($genTableName != '' ? ' ' : 'Waitlist'),
                'bgColor' => '#555',
                //'textColor' => '#fff',
                'maxOcc' => 0,
                'Type' => 'Waitlist',
                'Floor' => 'Waitlist',
                'roomStatus' => '',
                'Category' => 'Waitlist',
                'Report_Category' => 'Waitlist',
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

        $beginDate = self::parseDateTime($startTime, new \DateTimeZone($timezone));
        $endDate = self::parseDateTime($endTime, new \DateTimeZone($timezone));

        // get list of hospital colors
        $hospitals = $this->getHospitals($dbh);

        $this->getRibbonColors($dbh, $hospitals);

        // Get cleaning holidays for the current year(s)
        $beginHolidays = new US_Holidays($dbh, $beginDate->format('Y'));
        $endHolidays = new US_Holidays($dbh, $endDate->format('Y'));
        $nonClean = Reservation_1::loadNonCleaningDays($dbh);


        $this->getRoomOosEvents($dbh, $beginDate, $endDate, $timezone, $events);
        $this->getRetiredRoomEvents($dbh, $beginDate, $endDate, $timezone, $events);


        // Visits
        $query = "select vr.*, s.On_Leave, count(*) as `Guest_Count` from vregister vr left join stays s on `vr`.`idVisit` = `s`.`idVisit`
        AND `vr`.`Span` = `s`.`Visit_Span`
        AND `vr`.`Visit_Status` = `s`.`Status` where vr.Visit_Status not in ('" . VisitStatus::Pending . "' , '" . VisitStatus::Cancelled . "') and
            DATE(vr.Span_Start) <= DATE('" . $endDate->format('Y-m-d') . "') and ifnull(DATE(vr.Span_End), case when DATE(now()) > DATE(vr.Expected_Departure) then DATE(now()) else DATE(vr.Expected_Departure) end) >= DATE('" .$beginDate->format('Y-m-d') . "') group by vr.id;";
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

                if ($r['Span'] < 1 && date('Y-m-d', strtotime($r['Span_Start'])) == date('Y-m-d', strtotime($r['Span_End']))) {
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

            $validHolidays = FALSE;
            // New parameter controls BO days
            if ($uS->UseCleaningBOdays) {
                $validHolidays = TRUE;
            }

            // End date fall on a holiday?
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
            $this->setRibbonColors($r, $s);

            //
            $s['id'] = 'v' . $r['id'];
            $s['idVisit'] = $r['idVisit'];
            $s['Span'] = $r['Span'];
            $s['idHosp'] = htmlspecialchars_decode($r['idHospital'], ENT_QUOTES);
            $s['idAssoc'] = htmlspecialchars_decode($r['idAssociation'], ENT_QUOTES);
            $s['hospName'] = $hospitals[$r['idHospital']]['Title'];
            $s['resourceId'] = "id-" . $r["idResource"];
            $s['idResv'] = $r['idReservation'];
            $s['idResc'] = $r["idResource"];
            $s['start'] = $startDT->format('Y-m-d\TH:i:00');
            $s['end'] = $endDT->format('Y-m-d\TH:i:00');
            $s['title'] = $titleText;
            $s['guests'] = $r['Guest_Count'];
            $s['extended'] = $visitExtended;
            $s['allDay'] = 1;
            $s['fullName'] = htmlspecialchars_decode((!is_null($r['Name_Full']) ? $r['Name_Full']: ''), ENT_QUOTES);
            $s['visitStatus'] = $statusText;
            $s['vStatusCode'] = $r['Visit_Status'];
            $s['resourceEditable'] = 0;
            $event = new Event($s, $timezone);
            $events[] = $event->toArray();

        }




    // Reservations
        $query = "select * from vregister_resv where Status in ('" . ReservationStatus::Committed . "','" . ReservationStatus::UnCommitted . "','" . ReservationStatus::Waitlist . "') "
                . " and DATE(Expected_Arrival) <= DATE('" . $endDate->format('Y-m-d') . "') and DATE(Expected_Departure) > DATE('" . $beginDate->format('Y-m-d') . "') order by Expected_Arrival asc, idReservation asc";

        $stmt = $dbh->query($query);

        $eventId = 9000;

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            if ($r['Status'] == ReservationStatus::Waitlist) {
                $r["idResource"] = SELF::WAITLIST_RESC_ID;

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
            $validHolidays = FALSE;
            // New parameter controls BO days
            if ($uS->UseCleaningBOdays) {
                $validHolidays = TRUE;
            }

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
                        'resourceId' => "id-" . $r["idResource"],
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
                        'resourceId' => "id-" . $r["idResource"],
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
                        'resourceId' => "id-" . $r["idResource"],
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
                        'resourceId' => "id-" . $r["idResource"],
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

                // end date fall on non-cleaning day?
                $dateInfo = getDate(strtotime($r['Expected_Departure']));
                $limit = 5;

                while (array_search($dateInfo['wday'], $nonClean) !== FALSE && $limit-- > 0) {
                    // Add a Cleaning Black-Out Event
                    $c = array(
                        'id' => 'BO' . $eventId++,
                        'kind' => CalEventKind::BO,
                        'editable' => false,
                        'resourceId' => "id-" . $r["idResource"],
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
                        'resourceId' => "id-" . $r["idResource"],
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
            // Waitlist omit background event.
//            if ($r['idResource'] != 0 && $r['idHospital'] > 0) {
//                $backgroundBorderColor = $this->addBackgroundEvent($r, $hospitals, $uS->HospitalColorBar);
//            }

            $s['id'] = 'r' . $eventId++;
            $s['idReservation'] = $r['idReservation'];
            $s['borderColor'] = '#111';

            // Set ribbon color
            $this->setRibbonColors($r, $s);

            $s['start'] = $startDT->format('Y-m-d\TH:i:00');
            $s['end'] = $endDT->format('Y-m-d\TH:i:00');
            $s['title'] =  htmlspecialchars_decode($r['Guest Last'] . ($r['Ribbon_Note'] ? " - " . $r['Ribbon_Note']: ''), ENT_QUOTES);
            $s['hospName'] = htmlspecialchars_decode($hospitals[$r['idHospital']]['Title'], ENT_QUOTES);
            $s['idHosp'] = $r['idHospital'];
            $s['idAssoc'] = $r['idAssociation'];
            $s['allDay'] = 1;
            $s['resourceId'] = "id-" . $r["idResource"];
            $s['idResc'] = $r["idResource"];
            $s['resvStatus'] = $r['Status_Text'];
            $s['status'] = $r['Status'];
            $s['fullName'] = htmlspecialchars_decode($r['Name_Full'], ENT_QUOTES);

            $event = new Event($s, $timezone);
            $events[] = $event->toArray();

        }

        return $events;
    }

    protected function getEventTitle(array $r): string {
        $uS = Session::getInstance();

        switch ($uS->RibbonText){
            case "pgl": //Primary Guest Last Name
                return $r['Guest Last'] . ($r['Ribbon_Note'] ? " - " . $r['Ribbon_Note'] : '');
            case "pgf": //Primary Guest Full Name
                return $r['Name_Full'] . ($r['Ribbon_Note'] ? " - " . $r['Ribbon_Note'] : '');
            case "pl": //Patient Last Name
                return "";
            case "pf": //Patient Full Name
                return "";
            default:
                return $r['Guest Last'] . ($r['Ribbon_Note'] ? " - " . $r['Ribbon_Note'] : '');
        }
    }


    // Parses a string into a DateTime object, optionally forced into the given timezone.
    public static function parseDateTime($string, $timezone=null) {
      $date = new \DateTime(
        $string,
        $timezone ? $timezone : new \DateTimeZone('UTC')
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
      return new \DateTime($datetime->format('Y-m-d'));
    }


//     protected function addBackgroundEvent($r, $hospitals, $hospitalColors) {

//         $uS = Session::getInstance();
//         $backgroundBorderColor = '';

//         $hospitalColors = $uS->RibbonBottomColor || strtolower($uS->GuestNameColor) == 'hospital';

//             // Use Association colors?
//         if ($hospitalColors) {

//             if ($r['idAssociation'] != $this->noAssocId && $r['idAssociation'] > 0) {

//             	if (isset($hospitals[$r['idAssociation']])) {
//             	    $backgroundBorderColor = $hospitals[$r['idAssociation']]['Background_Color'];
//             	}

//             } else if (isset($hospitals[$r['idHospital']])) {
//                 $backgroundBorderColor = $hospitals[$r['idHospital']]['Background_Color'];
//             }

//         }

//         return $backgroundBorderColor;
//     }

    protected function addVisitBlackouts(&$events, $myHolidays, $dtendDate, $timezone, $idResc, $nonClean) {

        $p1d = new \DateInterval('P1D');

        while ($myHolidays->is_holiday($dtendDate->format('U'))) {
                    $c = array(
                        'id' => 'H' . $idResc,
                        'kind' => CalEventKind::BO,
                        'editable' => FALSE,
                        'resourceId' => "id-" . $idResc,
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
                        'resourceId' => "id-" . $idResc,
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
                        'resourceId' => 'id-' . $idResc,
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
    ru.*, g.Description AS `StatusTitle`, ifnull(gr.Description, 'Unspecified') as `reasonTitle`
FROM
    resource_use ru
        LEFT JOIN
    gen_lookups g ON g.Table_Name = 'Resource_Status'
        AND g.Code = ru.Status        LEFT JOIN
    gen_lookups gr ON gr.Table_Name = 'OOS_Codes'
        AND gr.Code = ru.OOS_Code
where DATE(ru.Start_Date) <= DATE('" . $endDate->format('Y-m-d') . "') and ifnull(DATE(ru.End_Date), DATE(now())) >= DATE('" . $beginDate->format('Y-m-d') . "');";

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
                'resourceId' => "id-" . $r["idResource"],
                'idResc' => $r["idResource"],
                'reason' => $r['reasonTitle'] . ($r['Notes'] != '' ? " - " . $r['Notes']: ''),
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

    protected function getRetiredRoomEvents(\PDO $dbh, \DateTime $beginDate, \DateTime $endDate, $timezone, &$events) {

        $idCounter = 10;

        $query = "select `idResource`, `Retired_At` from `resource` where `Retired_At` is not null;";

        $stmt = $dbh->query($query);

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            if ($r["idResource"] == 0) {
                continue;
            }

            $RetiredDT = new \Datetime($r['Retired_At']);

            if($RetiredDT >= $beginDate && $RetiredDT <= $endDate){

            }else{
                continue;
            }

            // Set Start and end for fullCalendar control
            $c = array(
                'id' => 'RET' . $idCounter++,
                'kind' => CalEventKind::OOS,
                'resourceId' => "id-" . $r["idResource"],
                'idResc' => $r["idResource"],
                'reason' => '',
            		'start' => $RetiredDT->format('Y-m-d\TH:i:00'),
            		'end' => $endDate->format('Y-m-d\TH:i:00'),
                'title' => "Retired",
                'allDay' => 1,
                'backgroundColor' => 'gray',
                'textColor' => 'white',
                'borderColor' => 'black',
            );

            $event = new Event($c, $timezone);
            $events[] = $event->toArray();
        }

    }

    protected function getRibbonColors(\PDO $dbh, $hospitals) {

        $uS = Session::getInstance();
        $this->ribbonColors = array();
        $this->robbonBottomColors = [];

        // Ribbon backgrounds
        $demogs = readGenLookupsPDO($dbh, $uS->RibbonColor);

        if (strtolower($uS->RibbonColor) == 'hospital') {

            foreach($hospitals as $h) {

                $this->ribbonColors[$h['idHospital']] = array(
                    't' => trim(strtolower($h['Text_Color'])),
                    'b' => trim(strtolower($h['Background_Color']))

                );
            }

            // Get guest name colorings
        } else if ($uS->RibbonColor != '') {

            foreach ($demogs as $d) {
                if ($d["Attributes"] && $attributes = json_decode($d["Attributes"], true)){
                    $this->ribbonColors[$d[0]] = array(
                        't' => isset($attributes["textColor"]) ? trim(strtolower($attributes["textColor"])) : "#ffffff",
                        'b' => isset($attributes["backgroundColor"]) ? trim(strtolower($attributes["backgroundColor"])) : 'transparent'
                    );
                }else if ($d[2] != '') {

                    // Split colors out of CDL
                    $splits = explode(',', $d[2]);

                    $this->ribbonColors[$d[0]] = array(
                        't' => trim(strtolower($splits[0])),
                        'b' => isset($splits[1]) ? trim(strtolower($splits[1])) : 'transparent'
                    );
                }else{
                    $this->ribbonColors[$d[0]] = array(
                        't' => "#ffffff",
                        'b' => ($uS->DefaultCalEventColor != '' ? $uS->DefaultCalEventColor: "#3788d8")
                    );
                }
            }
        }


        // Ribbon bottom-bars
        $demogs = readGenLookupsPDO($dbh, $uS->RibbonBottomColor);

        if (strtolower($uS->RibbonBottomColor) == 'hospital') {

            foreach($hospitals as $h) {

                $this->robbonBottomColors[$h['idHospital']] = trim(strtolower($h['Background_Color']));

            }

            // Get guest name colorings
        } else if ($uS->RibbonBottomColor != '') {

            foreach ($demogs as $d) {

                if ($d[2] != '') {

                    // Split colors out of CDL
                    $splits = explode(',', $d[2]);

                    $this->robbonBottomColors[$d[0]] = isset($splits[1]) ? trim(strtolower($splits[1])) : '';

                }else{
                    $this->robbonBottomColors[$d[0]] = ($uS->DefaultCalEventColor != '' ? $uS->DefaultCalEventColor : '');
                }
            }
        }

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

    protected function setRibbonColors($r, &$s) {

        $uS = Session::getInstance();        //$s['backBorderColor'] = $this->addBackgroundEvent($r, $hospitals);

        $today = (new \DateTime())->setTime(0,0,0);

        // Set ribbon color
        if ($uS->RibbonColor != '') {

            if (isset($r[$uS->RibbonColor]) && isset($this->ribbonColors[$r[$uS->RibbonColor]])){

                // Use Demographics colors
                $s['backgroundColor'] = $this->ribbonColors[$r[$uS->RibbonColor]]['b'];
                $s['textColor'] = $this->ribbonColors[$r[$uS->RibbonColor]]['t'];

            } else if (isset($this->ribbonColors[$r['idHospital']])) {

                // Use Hospital colors
                if ($r['idAssociation'] != $this->noAssocId && $r['idAssociation'] > 0 && isset($this->ribbonColors[$r['idAssociation']])) {
                    // Association color overrides the hospital color.
                    $s['backgroundColor'] = $this->ribbonColors[$r['idAssociation']]['b'];
                    $s['textColor'] = $this->ribbonColors[$r['idAssociation']]['t'];
                } else {
                    $s['backgroundColor'] = $this->ribbonColors[$r['idHospital']]['b'];
                    $s['textColor'] = $this->ribbonColors[$r['idHospital']]['t'];
                }
            } else if ($uS->RibbonColor == "Calendar_Status_Colors"){

                $expectedArrival = (isset($r["Expected_Arrival"]) ? (new \DateTime($r["Expected_Arrival"]))->setTime(0,0,0) : "");
                $expectedDeparture = (isset($r["Expected_Departure"]) ? (new \DateTime($r["Expected_Departure"]))->setTime(0,0,0) : "");

                if ($expectedArrival instanceof \DateTimeInterface) {
                    $arrivalDiff = $today->diff($expectedArrival);
                    $arrivalDiffDays = (integer) $arrivalDiff->format("%R%a");
                }

                if ($expectedDeparture instanceof \DateTimeInterface) {
                    $departureDiff = $today->diff($expectedDeparture);
                    $departureDiffDays = (integer) $departureDiff->format("%R%a");
                }

                if(isset($r["Visit_Status"]) && in_array($r["Visit_Status"], [VisitStatus::CheckedOut, VisitStatus::ChangeRate, VisitStatus::NewSpan]) == false && $expectedDeparture instanceof \DateTimeInterface && $departureDiffDays == 0){ //checking out today
                    $s['backgroundColor'] = $this->ribbonColors[CalendarStatusColors::CheckingOutToday]['b'];
                    $s['textColor'] = $this->ribbonColors[CalendarStatusColors::CheckingOutToday]['t'];
                } else if(isset($r["Visit_Status"]) && in_array($r["Visit_Status"], [VisitStatus::CheckedOut, VisitStatus::ChangeRate, VisitStatus::NewSpan]) == false && $expectedDeparture instanceof \DateTimeInterface && $departureDiffDays < 0){ //checked in past expected departure
                    $s['backgroundColor'] = $this->ribbonColors[CalendarStatusColors::CheckedInPastExpectedDepart]['b'];
                    $s['textColor'] = $this->ribbonColors[CalendarStatusColors::CheckedInPastExpectedDepart]['t'];
                }else if(isset($r["Visit_Status"]) && in_array($r["Visit_Status"], [VisitStatus::CheckedOut, VisitStatus::ChangeRate, VisitStatus::NewSpan]) == false && $expectedDeparture instanceof \DateTimeInterface && $departureDiffDays == 1){ //checking out tomorrow
                    $s['backgroundColor'] = $this->ribbonColors[CalendarStatusColors::CheckingOutTomorrow]['b'];
                    $s['textColor'] = $this->ribbonColors[CalendarStatusColors::CheckingOutTomorrow]['t'];
                } else if(isset($r["Visit_Status"]) && $r["Visit_Status"] == VisitStatus::CheckedIn){ //checked in
                    $s['backgroundColor'] = $this->ribbonColors[CalendarStatusColors::CheckedIn]['b'];
                    $s['textColor'] = $this->ribbonColors[CalendarStatusColors::CheckedIn]['t'];
                } else if(isset($r["Status"]) && $r["Status"] == 'a' && $expectedArrival instanceof \DateTimeInterface && $arrivalDiffDays == 0){ //arriving today
                    $s['backgroundColor'] = $this->ribbonColors[CalendarStatusColors::CheckingInToday]['b'];
                    $s['textColor'] = $this->ribbonColors[CalendarStatusColors::CheckingInToday]['t'];
                } else if(isset($r["Status"]) && $r["Status"] == 'a' && $expectedArrival instanceof \DateTimeInterface && $arrivalDiffDays == 1){ //arriving tomorrow
                    $s['backgroundColor'] = $this->ribbonColors[CalendarStatusColors::CheckingInTomorrow]['b'];
                    $s['textColor'] = $this->ribbonColors[CalendarStatusColors::CheckingInTomorrow]['t'];
                } else if(isset($r["Status"]) && $r["Status"] == 'a' && $expectedArrival instanceof \DateTimeInterface && $arrivalDiffDays > 1){ //arriving in future
                    $s['backgroundColor'] = $this->ribbonColors[CalendarStatusColors::CheckingInFuture]['b'];
                    $s['textColor'] = $this->ribbonColors[CalendarStatusColors::CheckingInFuture]['t'];
                }else if(isset($r["Status"]) && $r["Status"] == 'a' && $expectedArrival instanceof \DateTimeInterface && $arrivalDiffDays < 0){ //arriving in past (but not checked in)
                    $s['backgroundColor'] = $this->ribbonColors[CalendarStatusColors::CheckingInPast]['b'];
                    $s['textColor'] = $this->ribbonColors[CalendarStatusColors::CheckingInPast]['t'];
                } else if(isset($r["Status"]) && $r["Status"] == 'w'){ //waitlist
                    $s['backgroundColor'] = $this->ribbonColors[CalendarStatusColors::Waitlist]['b'];
                    $s['textColor'] = $this->ribbonColors[CalendarStatusColors::Waitlist]['t'];
                }else if(isset($r["Status"]) && $r["Status"] == 'uc'){
                    $s['backgroundColor'] = $this->ribbonColors[CalendarStatusColors::Unconfirmed]['b'];
                    $s['textColor'] = $this->ribbonColors[CalendarStatusColors::Unconfirmed]['t'];
                } else if (isset($r["Visit_Status"]) && in_array($r["Visit_Status"], [VisitStatus::CheckedOut, VisitStatus::ChangeRate, VisitStatus::NewSpan])) { //checked out
                    $s['backgroundColor'] = $this->ribbonColors[CalendarStatusColors::CheckedOut]['b'];
                    $s['textColor'] = $this->ribbonColors[CalendarStatusColors::CheckedOut]['t'];
                }
            }
        }

        // Set ribbon-bar color
        if ($uS->RibbonBottomColor != '') {

            if (isset($r[$uS->RibbonBottomColor]) && isset($this->robbonBottomColors[$r[$uS->RibbonBottomColor]])){

                // Use Demographics colors
                $s['backBorderColor'] = $this->robbonBottomColors[$r[$uS->RibbonBottomColor]];

            } else if (isset($this->robbonBottomColors[$r['idHospital']])) {

                // Use Hospital colors
                if ($r['idAssociation'] != $this->noAssocId && $r['idAssociation'] > 0 && isset($this->robbonBottomColors[$r['idAssociation']])) {
                    // Association color overrides the hospital color.
                    $s['backBorderColor'] = $this->robbonBottomColors[$r['idAssociation']];
                } else {
                    $s['backBorderColor'] = $this->robbonBottomColors[$r['idHospital']];
                }
            } else if ($uS->RibbonBottomColor == "Calendar_Status_Colors"){

                $expectedArrival = (isset($r["Expected_Arrival"]) ? (new \DateTime($r["Expected_Arrival"]))->setTime(0,0,0) : "");
                $expectedDeparture = (isset($r["Expected_Departure"]) ? (new \DateTime($r["Expected_Departure"]))->setTime(0,0,0) : "");

                if($expectedArrival instanceof \DateTimeInterface){
                    $arrivalDiff = $today->diff($expectedArrival);
                    $arrivalDiffDays = (integer)$arrivalDiff->format( "%R%a" );
                }

                if($expectedDeparture instanceof \DateTimeInterface){
                    $departureDiff = $today->diff($expectedDeparture);
                    $departureDiffDays = (integer)$departureDiff->format( "%R%a" );
                }

                if (isset($r["Visit_Status"]) && in_array($r["Visit_Status"], [VisitStatus::CheckedOut, VisitStatus::ChangeRate, VisitStatus::NewSpan]) == false && $expectedDeparture instanceof \DateTimeInterface && $departureDiffDays == 0) { //checking out today
                    $s['backBorderColor'] = $this->robbonBottomColors[CalendarStatusColors::CheckingOutToday];
                }else if(isset($r["Visit_Status"]) && in_array($r["Visit_Status"], [VisitStatus::CheckedOut, VisitStatus::ChangeRate, VisitStatus::NewSpan]) == false && $expectedDeparture instanceof \DateTimeInterface && $departureDiffDays < 0){ //checked in past expected departure
                    $s['backBorderColor'] = $this->robbonBottomColors[CalendarStatusColors::CheckedInPastExpectedDepart];
                }else if(isset($r["Visit_Status"]) && in_array($r["Visit_Status"], [VisitStatus::CheckedOut, VisitStatus::ChangeRate, VisitStatus::NewSpan]) == false && $expectedDeparture instanceof \DateTimeInterface && $departureDiffDays == 1){ //checking out tomorrow
                    $s['backBorderColor'] = $this->robbonBottomColors[CalendarStatusColors::CheckingOutTomorrow];
                } else if(isset($r["Visit_Status"]) && $r["Visit_Status"] == VisitStatus::CheckedIn){ //checked in
                    $s['backBorderColor'] = $this->robbonBottomColors[CalendarStatusColors::CheckedIn];
                } else if(isset($r["Status"]) && $r["Status"] == 'a' && $expectedArrival instanceof \DateTimeInterface && $arrivalDiffDays == 0){ //arriving today
                    $s['backBorderColor'] = $this->robbonBottomColors[CalendarStatusColors::CheckingInToday];
                } else if(isset($r["Status"]) && $r["Status"] == 'a' && $expectedArrival instanceof \DateTimeInterface && $arrivalDiffDays == 1){ //arriving tomorrow
                    $s['backBorderColor'] = $this->robbonBottomColors[CalendarStatusColors::CheckingInTomorrow];
                } else if(isset($r["Status"]) && $r["Status"] == 'a' && $expectedArrival instanceof \DateTimeInterface && $arrivalDiffDays > 1){ //arriving in future
                    $s['backBorderColor'] = $this->robbonBottomColors[CalendarStatusColors::CheckingInFuture];
                }else if(isset($r["Status"]) && $r["Status"] == 'a' && $expectedArrival instanceof \DateTimeInterface && $arrivalDiffDays < 0){ //arriving in past (but not checked in)
                    $s['backBorderColor'] = $this->robbonBottomColors[CalendarStatusColors::CheckingInPast];
                } else if(isset($r["Status"]) && $r["Status"] == 'w'){ //waitlist
                    $s['backBorderColor'] = $this->robbonBottomColors[CalendarStatusColors::Waitlist];
                }else if(isset($r["Status"]) && $r["Status"] == 'uc'){
                    $s['backBorderColor'] = $this->robbonBottomColors[CalendarStatusColors::Unconfirmed];
                } else if (isset($r["Visit_Status"]) && in_array($r["Visit_Status"], [VisitStatus::CheckedOut, VisitStatus::ChangeRate, VisitStatus::NewSpan])) { //checked out
                    $s['backBorderColor'] = $this->robbonBottomColors[CalendarStatusColors::CheckedOut];
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

      $this->title = '';;
      if (isset($array['title'])) {
           $this->title = $array['title'];
      }

    if (isset($array['allDay'])) {
      // allDay has been explicitly specified
      $this->allDay = (bool)$array['allDay'];
    }
    else {
      // Guess allDay based off of ISO8601 date strings
      $this->allDay = preg_match(self::ALL_DAY_REGEX, $array['start']) &&
        (!isset($array['end']) || preg_match(self::ALL_DAY_REGEX, $array['end']));
    }

    if (is_string($timezone)) {
        $timezone = new \DateTimeZone($timezone);
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