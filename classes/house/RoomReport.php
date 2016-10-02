<?php
/**
 * RoomReport.php
 *
 * Room report & markup
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of RoomReport
 * @author Eric
 */
class RoomReport {

    public static function getGlobalNightsCount(PDO $dbh, $year = '') {

        $whClause = '';
        if ($year != '') {
            $whClause = " and DATE(s.Span_Start_Date) <= '$year-12-31' and Date(ifnull(s.Span_End_Date, now())) >= '$year-01-01'";
        }

        $query = "select sum(DATEDIFF(ifnull(s.Span_End_Date, now()), s.Span_Start_Date)) as `Nights` "
                . " from stays s where s.`On_Leave` = 0 and DATEDIFF(ifnull(s.Span_End_Date, now()), s.Span_Start_Date) > 0" . $whClause;
        $stmt = $dbh->query($query);
        $rows = $stmt->fetchAll();
        if (count($rows) == 1) {
            return $rows[0][0];
        } else {
            return 0;
        }
    }

    public static function getGlobalStaysCount(PDO $dbh, $year = '') {

        $whClause = '';
        if ($year != '') {
            $whClause = " and DATE(Span_Start_Date) <= '$year-12-31' and Date(ifnull(Span_End_Date, now())) >= '$year-01-01'";
        }

        $query = "select count(*) as `Stays` "
                . " from stays where `On_Leave` = 0 and DATEDIFF(ifnull(Span_End_Date, now()), Span_Start_Date) > 0" . $whClause;
        $stmt = $dbh->query($query);
        $rows = $stmt->fetchAll();
        if (count($rows) == 1) {
            return $rows[0][0];
        } else {
            return 0;
        }
    }

    public static function getGlobalNightsCounter(PDO $dbh, $previousCount = 0) {

        $uS = Session::getInstance();
        $comment = '.';

        if ($uS->NightsCounter != '') {
            $comment = " this calendar year.";
        }

        if (isset($uS->gnc) === FALSE) {

            $year = '';
            $now = new DateTime();

            if ($uS->NightsCounter != '') {
                $now = new DateTime();
                $year = $now->format('Y');

            }

            $uS->gnc = intval((self::getGlobalNightsCount($dbh, $year) + $previousCount) / 10);
        }

        $span = HTMLContainer::generateMarkup('span', 'More than ' . number_format($uS->gnc * 10) . ' nights of rest' . $comment, array('style'=>'margin-left:200px;font-size:.6em;font-weight:normal;'));

        return $span;
    }

    public static function getGlobalStaysCounter(PDO $dbh, $previousCount = 0) {

        $uS = Session::getInstance();
        $comment = '.';

        if (!$uS->ShoStaysCtr) {
            return '';
        }

        if ($uS->NightsCounter != '') {
            $comment = " this calendar year.";
        }

        if (isset($uS->gsc) === FALSE) {

            $year = '';
            $now = new DateTime();

            if ($uS->NightsCounter != '') {
                $now = new DateTime();
                $year = $now->format('Y');
            }

            $uS->gsc = intval((self::getGlobalStaysCount($dbh, $year) + $previousCount), 10);
        }

        $span = HTMLContainer::generateMarkup('span', number_format($uS->gsc) . ' Stays' . $comment, array('style'=>'margin-left:40px;font-size:.6em;font-weight:normal;'));

        return $span;
    }



    public static function roomNOR(PDO $dbh, $startDate, $endDate, $period, $maxDays = 366) {

        if ($startDate == '') {
            return;
        }

        if ($endDate == '') {
            $endDate = date('Y-m-d');
        }

        if ($period == 'y') {
            $startDate = date('Y-01-01', strtotime($startDate));
            $endDate = date('Y-12-31', strtotime($endDate));
        }

        $stDT = new DateTime($startDate);
        $endDT = new DateTime($endDate);
        $endDT->add(new DateInterval('P1D'));

        if ($stDT === FALSE || $endDT === FALSE) {
            return;
        }

        $query = "select r.idRoom, r.Title, r.Category, r.Max_Occupants, s.Span_Start_Date, ifnull(s.Span_End_Date,now()) as `CO_Date`, DATEDIFF(ifnull(s.Span_End_Date, now()), s.Span_Start_Date) as `Nights`
from room r left join stays s on s.idRoom = r.idRoom
where DATEDIFF(ifnull(s.Span_End_Date, now()), s.Span_Start_Date) > 0 and s.`On_Leave` = 0
and s.Span_Start_Date < '" . $endDT->format('Y-m-d 00:00:00') . "' and ifnull(s.Span_End_Date,  now() ) >= '" . $stDT->format('Y-m-d 00:00:00') ."'"
                . " order by r.Title;";
        $stmt = $dbh->query($query);
        $rows = $stmt->fetchAll();

        $days = array();
        $catDays = array();
        $totals = array();
        $oneDay = new DateInterval('P1D');
        $rooms = array();

        $categories = array();
        $size = 0;
//        $th = HTMLTable::makeTh('');
        $now = new DateTime();
        $now->setTime(23, 59, 59);

        if ($endDT > $now) {
            $endDT = $now->sub($oneDay);
        }

        // Set up the days array
        $th = '';
        while ($stDT < $endDT && $size < $maxDays) {

            $thisDay = $stDT->format('Y-m-d');
            $th .= HTMLTable::makeTh($stDT->format('j'));

            foreach ($rows as $r) {
                $days[$r['idRoom']][$thisDay] = 0;
                $catDays[$r['Category']][$thisDay] = 0;
            }

            $days['Total'][$thisDay] = 0;

            $stDT->add($oneDay);
            $size++;
        }

        $th .= HTMLTable::makeTh('Room');
        $th .= HTMLTable::makeTh('Total');

        $roomCataegoryTitles = readGenLookupsPDO($dbh, 'Room_Category');

        foreach ($rows as $r) {
            $totals[$r['idRoom']] = 0;
            $totals[$r['Category']] = 0;
            $rooms[$r['idRoom']]['Max'] = $r['Max_Occupants'];
            $rooms[$r['idRoom']]['Title'] = $r['Title'];
            $categories[$r['Category']]['Title'] = $roomCataegoryTitles[$r['Category']][1];
        }

        $rooms['Total']['Title'] = 'Total';
        $rooms['Total']['Max'] = 0;
        $totals['Total'] = 0;

        $categories['Total']['title'] = 'Total';


        // Count
        foreach ($rows as $r) {
            $rmStartDate = new DateTime($r['Span_Start_Date']);
            $numNights = $r['Nights'];

            for ($j = 0; $j < $numNights; $j++) {
                $rmDate = $rmStartDate->format('Y-m-d');

                if (isset($days[$r['idRoom']][$rmDate])) {

                    $days[$r['idRoom']][$rmDate]++;
                    $catDays[$r['Category']][$rmDate]++;
                    $days['Total'][$rmDate]++;
                    $totals[$r['idRoom']]++;
                    $totals[$r['Category']]++;
                    $totals['Total']++;
                }

                if ($rmStartDate > $endDT) {
                    break;
                }
                $rmStartDate->add($oneDay);
            }

        }


        // Rooms report
        $tbl = new HTMLTable();
        $tbl->addHeaderTr(HTMLTable::makeTh('Room') . $th . HTMLTable::makeTh('Occupied'));

        foreach ($days as $idRm => $rdateArray) {

            $td = HTMLTable::makeTd($rooms[$idRm]['Title']);

            $daysOccupied = 0;
            foreach($rdateArray as $numGuests) {
                $td .= HTMLTable::makeTd($numGuests);
                if ($numGuests > 0 ) {
                    $daysOccupied++;
                }
            }

            $td .= HTMLTable::makeTd($rooms[$idRm]['Title']);
            $td .= HTMLTable::makeTd($totals[$idRm]);

            $f = ($daysOccupied / count($rdateArray) * 100);

            $td .= HTMLTable::makeTd(number_format($f, 0) . "%");

            $tbl->addBodyTr($td);
        }

        // subtract the extra day off the end date
        $endDT->sub($oneDay);

        $mkup = $tbl->generateMarkup(array(), $endDT->format('F, Y'));


        // Category report
        $tbl2 = new HTMLTable();
        $tbl2->addHeaderTr(HTMLTable::makeTh('Category') . $th);

        foreach ($catDays as $idRm => $rdateArray) {

            $td = HTMLTable::makeTd($categories[$idRm]['Title']);

            $daysOccupied = 0;
            foreach($rdateArray as $numGuests) {
                $td .= HTMLTable::makeTd($numGuests);
                if ($numGuests > 0 ) {
                    $daysOccupied++;
                }
            }

            $td .= HTMLTable::makeTd($categories[$idRm]['Title']);
            $td .= HTMLTable::makeTd($totals[$idRm]);

            $tbl2->addBodyTr($td);
        }
        $mkup .= $tbl2->generateMarkup(array(), $endDT->format('F, Y'));

        return $mkup;
    }

    public static function rescUtilization(PDO $dbh, $startDate, $endDate, $period, $maxDays = 366, $noTable = FALSE) {

        if ($startDate == '') {
            return;
        }

        if ($endDate == '') {
            $endDate = date('Y-m-d');
        }

        $onePeriod = new DateInterval('P1D');
        $dateFormat = 'Y-m-d';
        $dateTitle = 'j';
        $captionDateFormat = 'F, Y';

        if ($period == 'y') {
            $startDate = date('Y-01-01', strtotime($startDate));
            $endDate = date('Y-12-31', strtotime($endDate));
            $onePeriod = new DateInterval('P1M');
            $dateFormat = 'Y-m';
            $dateTitle = 'n';
            $maxDays = (int)$maxDays / 12;
            $captionDateFormat = 'Y';
        }

        $stDT = new DateTime($startDate);
        $stDT->setTime(0,0,0);
        $endDT = new DateTime($endDate);
        $endDT->add(new DateInterval('P1D'));

        if ($stDT === FALSE || $endDT === FALSE) {
            return;
        }

        // Counting start date
        $countgDT = new DateTime($startDate);
        $countgDT->setTime(0, 0, 0);


        // Get all the rooms
        $stResc = $dbh->query("select r.idResource, r.Title "
                . " from resource r left join
resource_use ru on r.idResource = ru.idResource and ru.`Status` = '" . ResourceStatus::Unavailable . "' and ru.Start_Date <= '" . $stDT->format('Y-m-d 00:00:00') . "' and ru.End_Date >= '" . $endDT->format('Y-m-d 00:00:00') . "'"
                . " where ru.idResource_use is null and r.Type in ('" . ResourceTypes::Room . "', '" . ResourceTypes::RmtRoom . "')"
                . " order by r.Title;");

        $stRows = $stResc->fetchAll(PDO::FETCH_ASSOC);

        $rescs = array();
        $totals = array();
        $expTotals = array();
        $expRoomCount = count($stRows);

        foreach ($stRows as $r) {

            $rescs[$r['idResource']] = array(
                'Title'=>$r['Title']);

        }
        unset($stRows);

        $summary = array('nits'=>'Nights', 'oos'=>'OOS', 'to'=>'Delayed', 'un'=>'Unavailable');

        $size = 0;
        $days = array();
        $oneDay = new DateInterval('P1D');
        $th = '';


        // Set up the days array
        while ($countgDT < $endDT && $size < $maxDays) {

            $thisDay = $countgDT->format($dateFormat);
            $th .= HTMLTable::makeTh($countgDT->format($dateTitle));
            $titleDay = $countgDT->format($dateTitle);

            foreach ($rescs as $idResc => $r) {

                $days[$idResc][$thisDay]['n'] = 0;
                $days[$idResc][$thisDay]['o'] = 0;
                $days[$idResc][$thisDay]['t'] = 0;
                $days[$idResc][$thisDay]['u'] = 0;

            }

            foreach ($summary as $s => $title) {

                $totals[$s][$thisDay] = 0;
                $expTotals[$title][$titleDay] = 0;
            }


            $countgDT->add($onePeriod);
            $size++;
        }

        $th .= HTMLTable::makeTh('Room');
        $th .= HTMLTable::makeTh('Nights');
        $th .= HTMLTable::makeTh('OOS');
        $th .= HTMLTable::makeTh('Delayed');
        $th .= HTMLTable::makeTh('Unavailable');





        // Collect visit records
        $query = "select
    v.idResource,
    r.Category,
    r.idSponsor,
    v.Span_Start,
    DATEDIFF(ifnull(v.Span_End, now()), v.Span_Start) as `Nights`
from visit v left join resource r on v.idResource = r.idResource
where v.Status != '" . VisitStatus::Pending . "' and DATEDIFF(ifnull(v.Span_End, now()), v.Span_Start) > 0
and v.Span_Start < '" . $endDT->format('Y-m-d 00:00:00') . "' and ifnull(v.Span_End,  now() ) >= '" . $stDT->format('Y-m-d 00:00:00') ."' order by r.Title;";

        $stmt = $dbh->query($query);

        // Count nights of use
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rmStartDate = new DateTime($r['Span_Start']);
            $numNights = $r['Nights'];

            for ($j = 0; $j < $numNights; $j++) {
                $rmDate = $rmStartDate->format($dateFormat);

                if (isset($days[$r['idResource']][$rmDate])) {

                    $days[$r['idResource']][$rmDate]['n']++;
                    $totals['nits'][$rmDate]++;
                    $expTotals['Nights'][$rmStartDate->format($dateTitle)]++;

                }

                if ($rmStartDate > $endDT) {
                    break;
                }
                $rmStartDate->add($oneDay);
            }

        }

        // Collect resource use records
        $rstmt = $dbh->query("Select idResource, `Status`, `Start_Date`, `End_Date`, DATEDIFF(ifnull(`End_Date`, now()), `Start_Date`) as `Nights`"
                . " from resource_use where Start_Date < '" . $endDT->format('Y-m-d 00:00:00') . "' and ifnull(End_Date, now()) >= '" . $stDT->format('Y-m-d 00:00:00') ."' order by idResource");

        while ($r = $rstmt->fetch(PDO::FETCH_ASSOC)) {

            $rmStartDate = new DateTime($r['Start_Date']);
            $numNights = $r['Nights'];

            for ($j = 0; $j < $numNights; $j++) {

                $rmDate = $rmStartDate->format($dateFormat);
                $rmDateTitle = $rmStartDate->format($dateTitle);


                if (isset($days[$r['idResource']][$rmDate])) {

                    if ($r['Status'] == ResourceStatus::Delayed) {

                        $days[$r['idResource']][$rmDate]['t']++;
                        $totals['to'][$rmDate]++;

                    } else if ($r['Status'] == ResourceStatus::OutOfService) {

                        $days[$r['idResource']][$rmDate]['o']++;
                        $totals['oos'][$rmDate]++;
                        $expTotals['OOS'][$rmDateTitle]++;

                    } else if ($r['Status'] == ResourceStatus::Unavailable) {

                        $days[$r['idResource']][$rmDate]['u']++;
                        $totals['un'][$rmDate]++;
                        $expTotals['Unavailable'][$rmDateTitle]++;
                    }
                }

                if ($rmStartDate > $endDT) {
                    break;
                }
                $rmStartDate->add($oneDay);
            }
        }

        if ($noTable) {
            $expTotals['roomCount'] = $expRoomCount;
            return $expTotals;
        }

        // Rooms report
        $tbl = new HTMLTable();
        $tbl->addHeaderTr(HTMLTable::makeTh('Room') . $th);

        foreach ($days as $idRm => $rdateArray) {

            $tds = HTMLTable::makeTd($rescs[$idRm]['Title'], array('style'=>'font-family: \"Times New Roman\", Times, serif;'));

            $daysOccupied['n'] = 0;
            $daysOccupied['o'] = 0;
            $daysOccupied['t'] = 0;
            $daysOccupied['u'] = 0;

            foreach($rdateArray as $day => $numbers) {

                if ($numbers['n'] > 0 ) {
                    $tds .= HTMLTable::makeTd(' ', array('style'=>'background-color:lightgreen;'));
                     $daysOccupied['n']++;

                } else if ($numbers['o'] > 0 ) {
                    $tds .= HTMLTable::makeTd(' ', array('style'=>'background-color:gray;'));
                     $daysOccupied['o']++;

                } else if ($numbers['u'] > 0 ) {
                    $tds .= HTMLTable::makeTd(' ', array('style'=>'background-color:black;'));
                     $daysOccupied['u']++;

                } else if ($numbers['t'] > 0 ) {
                    $tds .= HTMLTable::makeTd(' ', array('style'=>'background-color:brown;'));
                     $daysOccupied['t']++;

                } else {
                    $tds .= HTMLTable::makeTd(' ');
                }

            }

            $tds .= HTMLTable::makeTd($rescs[$idRm]['Title'], array('style'=>'font-family: \"Times New Roman\", Times, serif;'));

            foreach ($daysOccupied as $d) {
                $tds .= HTMLTable::makeTd($d, array('style'=>'text-align:right;'));
            }

            $tbl->addBodyTr($tds);
        }

        $ctr = 1;

        foreach ($totals as $idRm => $rdateArray) {

            $tds = HTMLTable::makeTd($summary[$idRm]);

            $daysOccupied = 0;

            foreach($rdateArray as $day => $numbers) {

                $tds .= HTMLTable::makeTd($numbers);
                $daysOccupied += $numbers;
            }

            $tds .= HTMLTable::makeTd($summary[$idRm]);

            for ($i = 1; $i < $ctr; $i++) {
                $tds .= HTMLTable::makeTd('');
            }
            $tds .= HTMLTable::makeTd($daysOccupied, array('style'=>'text-align:right;'));


            $tbl->addBodyTr($tds);
            $ctr++;
        }


        return $tbl->generateMarkup(array(), $endDT->sub(new DateInterval('P1D'))->format($captionDateFormat));
    }


}

