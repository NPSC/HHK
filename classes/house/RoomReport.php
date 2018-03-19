<?php
/**
 * RoomReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of RoomReport
 * @author Eric
 */
class RoomReport {

    protected static function getGlobalNightsCount(PDO $dbh, $year = '') {

        if ($year != '') {
            // Filter out one year
            $query = "SELECT SUM(DATEDIFF(
IFNULL(s.Span_End_Date, NOW()),
case when year(s.Span_Start_Date) < $year then DATE('$year-01-01') else Date(s.Span_Start_Date) end)) AS `Nights`
FROM stays s WHERE s.`On_Leave` = 0  and DATE(s.Span_Start_Date) <= DATE('$year-12-31') and Date(ifnull(s.Span_End_Date, now())) >= DATE('$year-01-01') ";

        } else {
            // Entire history
            $query = "SELECT SUM(DATEDIFF(
IFNULL(s.Span_End_Date, NOW()),
DATE(s.Span_Start_Date))) AS `Nights`
FROM stays s WHERE s.`On_Leave` = 0  and DATE(s.Span_Start_Date) <= DATE(NOW())";

        }

        $stmt = $dbh->query($query);
        $rows = $stmt->fetchAll();
        if (count($rows) == 1) {
            return $rows[0][0];
        } else {
            return 0;
        }
    }

    protected static function getGlobalStaysCount(PDO $dbh, $year = '') {

        $whClause = '';
        if ($year != '') {
            $whClause = " and DATE(Span_Start_Date) <= '$year-12-31' and Date(ifnull(Span_End_Date, now())) >= '$year-01-01'";
        }

        $query = "select count(*) as `Stays` "
                . " from stays where `On_Leave` = 0 and `Status` = 'co' and DATEDIFF(ifnull(Span_End_Date, now()), Span_Start_Date) > 0" . $whClause;
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

    public static function dailyReport(\PDO $dbh) {

        $roomsOOS = array();

        // Get Rooms OOS
        $query1 = "SELECT
    rr.idRoom,
    ru.Notes,
    IFNULL(g.Description, '') AS `StatusTitle`,
    IFNULL(g2.Description, '') AS `OOSCode`
FROM
    resource_use ru
        JOIN
    resource_room rr ON ru.idResource = rr.idResource
        LEFT JOIN
    gen_lookups g ON g.Table_Name = 'Resource_Status'
        AND g.Code = ru.`Status`
        LEFT JOIN
    gen_lookups g2 ON g2.Table_Name = 'OOS_Codes'
        AND g2.Code = ru.OOS_Code
WHERE
    DATE(Start_Date) <= DATE(NOW())
        AND IFNULL(DATE(End_Date), DATE(NOW())) > DATE(NOW());";

        $stmtrs = $dbh->query($query1);

        while ($r = $stmtrs->fetch(\PDO::FETCH_ASSOC)) {

            if ($r['idRoom'] == 0) {
                continue;
            }

            $roomsOOS[$r['idRoom']] = $r;

       }

        $query = "SELECT
            r.Util_Priority,
            r.idRoom,
            r.`Title`,
            r.`Status`,
            gc.Substitute as Cleaning_Days,
            IFNULL(g.Description, '') AS `Status_Text`,
            IFNULL(n.Name_Full, '') AS `Name`,
            r.`Notes`,
            IFNULL(v.`Notes`, '') AS `Visit_Notes`,
            IFNULL(v.idVisit, 0) AS idVisit,
            IFNULL(v.Span, 0) AS `Span`,
            IFNULL(np.Name_Full, '') as `Patient_Name`
        FROM
            room r
                LEFT JOIN
            stays s ON r.idRoom = s.idRoom AND s.`Status` = 'a'
                LEFT JOIN
            `name` n ON s.idName = n.idName
                LEFT JOIN
            visit v ON s.idVisit = v.idVisit
                AND s.Visit_Span = v.Span
                LEFT JOIN
            hospital_stay hs on v.idHospital_stay = hs.idHospital_stay
                LEFT JOIN
            name np on hs.idPatient = np.idName
                LEFT JOIN
            gen_lookups g ON g.Table_Name = 'Room_Status'
                AND g.Code = r.`Status`
                LEFT JOIN
            gen_lookups gc ON gc.Table_Name = 'Room_Cleaning_Days'
                AND gc.Code = r.Cleaning_Cycle_Code
        ORDER BY r.idRoom";


        $stmt = $dbh->query($query);

        $tableRows = array();
        $idRoom = 0;
        $guests = '';
        $last = array();

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            if ($idRoom != $r['idRoom']) {

                if ($idRoom > 0) {
                    $tableRows[] = self::doDailyMarkup($dbh, $last, $guests, $roomsOOS);
                }

                $guests = '';
                $idRoom = $r['idRoom'];
            }

            if ($guests == '') {
                $guests = $r['Name'];
            } else {
                $guests .= ', ' . $r['Name'];
            }

            $last = $r;
        }

        $tableRows[] = self::doDailyMarkup($dbh, $last, $guests, $roomsOOS);

        return $tableRows;
    }

    protected static function doDailyMarkup(\PDO $dbh, $r, $guests, $roomsOOS) {

        $uS = Session::getInstance();
        $fixed = array();

        $idVisit = intval($r['idVisit'], 10);

        // Mangle room status
        if ($r['Cleaning_Days'] > 0) {
            if ($r['Status'] == RoomState::TurnOver) {
                $stat = HTMLContainer::generateMarkup('span', $r['Status_Text'], array('style'=>'background-color:yellow;'));
            } else if ($r['idVisit'] > 0 && $r['Status'] == RoomState::Dirty) {
                $stat = HTMLContainer::generateMarkup('span', 'Active-Dirty', array('style'=>'background-color:#E3FF14;'));
            } else if ($r['idVisit'] > 0 && $r['Status'] == RoomState::Clean) {
                $stat = HTMLContainer::generateMarkup('span', 'Active', array('style'=>'background-color:lightgreen;'));
            } else if ($r['Status'] == RoomState::Dirty) {
                $stat = HTMLContainer::generateMarkup('span', 'Dirty', array('style'=>'background-color:yellow;'));
            } else {
                $stat = HTMLContainer::generateMarkup('span', $r['Status_Text']);
            }
        } else {
            if ($r['idVisit'] > 0) {
                $stat = HTMLContainer::generateMarkup('span', 'Active', array('style'=>'background-color:lightgreen;'));
            } else {
                $stat = HTMLContainer::generateMarkup('span', 'Empty');
            }
        }

        // Check OOS
        if (isset($roomsOOS[$r['idRoom']])) {
            $stat = $roomsOOS[$r['idRoom']]['StatusTitle'] . ': ' . $roomsOOS[$r['idRoom']]['OOSCode'];
        }

        $fixed['titleSort'] = $r['Util_Priority'];
        $fixed['Title'] = $r['Title'];
        $fixed['Status'] = $stat;
        $fixed['Guests'] = $guests;
        $fixed['Patient_Name'] = $r['Patient_Name'];

        if ($idVisit > 0) {

            // get unpaid amount
            $visitCharge = new VisitCharges($idVisit);
            $visitCharge->sumPayments($dbh)
                    ->sumCurrentRoomCharge($dbh, PriceModel::priceModelFactory($dbh, $uS->RoomPriceModel));

            $totalMOA = 0;
            if ($visitCharge->getItemInvCharges(ItemId::LodgingMOA) > 0) {
                $totalMOA = $visitCharge->getItemInvCharges(ItemId::LodgingMOA);
            }

            // Discounts
            $totalDiscounts = $visitCharge->getItemInvCharges(ItemId::Discount) + $visitCharge->getItemInvCharges(ItemId::Waive);

            $totalCharged =
                    $visitCharge->getRoomFeesCharged()
                    + $visitCharge->getVisitFeeCharged()
                    + $visitCharge->getItemInvCharges(ItemId::AddnlCharge)
                    + $totalMOA
                    + $totalDiscounts;

            $totalPaid = $visitCharge->getRoomFeesPaid()
                    + $visitCharge->getVisitFeesPaid()
                    + $visitCharge->getItemInvPayments(ItemId::AddnlCharge);

            if ($visitCharge->getItemInvPayments(ItemId::LodgingMOA) > 0) {
                $totalPaid += $visitCharge->getItemInvPayments(ItemId::LodgingMOA);
            }

            // Add Waived amounts.
            $totalPaid += $visitCharge->getItemInvPayments(ItemId::Waive);
            $amtPending = $visitCharge->getRoomFeesPending() + $visitCharge->getVisitFeesPending() + $visitCharge->getItemInvPending(ItemId::AddnlCharge) + $visitCharge->getItemInvPending(ItemId::Waive);
            $dueToday = $totalCharged - $totalPaid - $amtPending;

            if ($dueToday < 0) {
                $dueToday = 0;
            }

            $fixed['Unpaid'] = '$' . number_format($dueToday, 2);

        } else {
            $fixed['Unpaid'] = '';
        }

        $fixed['Visit_Notes'] = Notes::getNotesDiv($r['Visit_Notes']);
        $fixed['Notes'] = Notes::getNotesDiv($r['Notes']);

        return $fixed;
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

        $stDT = new \DateTime($startDate);
        $endDT = new \DateTime($endDate);
        $endDT->add(new \DateInterval('P1D'));

        if ($stDT === FALSE || $endDT === FALSE) {
            return;
        }

        $query = "SELECT
    r.idRoom,
    r.Title,
    r.Category,
    r.Max_Occupants,
    s.Span_Start_Date,
    IFNULL(s.Span_End_Date, NOW()) AS `CO_Date`,
    DATEDIFF(IFNULL(s.Span_End_Date, NOW()), s.Span_Start_Date) AS `Nights`
FROM
    room r
        LEFT JOIN
    stays s ON s.idRoom = r.idRoom
WHERE
    DATEDIFF(IFNULL(s.Span_End_Date, NOW()), s.Span_Start_Date) > 0
        AND s.`On_Leave` = 0
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
        $now = new \DateTime();
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
            $rmStartDate = new \DateTime($r['Span_Start_Date']);
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

            if ($rooms[$idRm]['Title'] != 'Total') {
                $f = ($daysOccupied / count($rdateArray) * 100);
                $td .= HTMLTable::makeTd(number_format($f, 0) . "%");
            }

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

        $stDT = new \DateTime($startDate);
        $stDT->setTime(0,0,0);
        $endDT = new \DateTime($endDate);
        $endDT->add(new DateInterval('P1D'));

        if ($stDT === FALSE || $endDT === FALSE) {
            return;
        }

        // Counting start date
        $countgDT = new \DateTime($startDate);
        $countgDT->setTime(0, 0, 0);


        // Get all the rooms
        $stResc = $dbh->query("select r.idResource, r.Title "
                . " from resource r left join
resource_use ru on r.idResource = ru.idResource and ru.`Status` = '" . ResourceStatus::Unavailable . "' and ru.Start_Date <= '" . $stDT->format('Y-m-d 00:00:00') . "' and ru.End_Date >= '" . $endDT->format('Y-m-d 00:00:00') . "'"
                . " where ru.idResource_use is null and r.Type in ('" . ResourceTypes::Room . "', '" . ResourceTypes::RmtRoom . "', '" . ResourceTypes::Partition . "')"
                . " order by r.Title;");

        $stRows = $stResc->fetchAll(\PDO::FETCH_ASSOC);

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
        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $rmStartDate = new \DateTime($r['Span_Start']);
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

        while ($r = $rstmt->fetch(\PDO::FETCH_ASSOC)) {

            $rmStartDate = new \DateTime($r['Start_Date']);
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

