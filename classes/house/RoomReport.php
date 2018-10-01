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
        $uS = Session::getInstance();

        $priceModel = PriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

        // Get Rooms OOS
        $query1 = "SELECT
    rr.idRoom,
    ru.Notes,
    ru.`Status`,
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
    DATE(ru.Start_Date) <= DATE(NOW())
        AND IFNULL(DATE(ru.End_Date), DATE(NOW())) > DATE(NOW());";

        $stmtrs = $dbh->query($query1);

        while ($r = $stmtrs->fetch(\PDO::FETCH_ASSOC)) {

            if ($r['idRoom'] == 0) {
                continue;
            }

            $roomsOOS[$r['idRoom']] = $r;

       }

       // Get notes
       $stmtn = $dbh->query("SELECT
    rn.Reservation_Id,
    n.User_Name,
    CASE
        WHEN n.Title = '' THEN n.Note_Text
        ELSE CONCAT(n.Title, ' - ', n.Note_Text)
    END AS Note_Text
FROM
visit v join reservation_note rn on v.idReservation = rn.Reservation_Id
        JOIN
    note n ON rn.Note_Id = n.idNote
where v.Status = 'a'
ORDER BY rn.Reservation_Id, n.User_Name;");


//        $query = "SELECT
//            r.Util_Priority,
//            r.idRoom,
//            r.`Title`,
//            r.`Status`,
//            gc.Substitute as Cleaning_Days,
//            IFNULL(g.Description, '') AS `Status_Text`,
//            IFNULL(n.Name_Full, '') AS `Name`,
//            r.`Notes`,
//            IFNULL(v.idVisit, 0) AS idVisit,
//            IFNULL(v.Span, 0) AS `Span`,
//            IFNULL(np.Name_Full, '') as `Patient_Name`
//        FROM
//            room r
//                LEFT JOIN
//            stays s ON r.idRoom = s.idRoom AND s.`Status` = 'a'
//                LEFT JOIN
//            `name` n ON s.idName = n.idName
//                LEFT JOIN
//            visit v ON s.idVisit = v.idVisit
//                AND s.Visit_Span = v.Span
//                LEFT JOIN
//            hospital_stay hs on v.idHospital_stay = hs.idHospital_stay
//                LEFT JOIN
//            name np on hs.idPatient = np.idName
//                LEFT JOIN
//            gen_lookups g ON g.Table_Name = 'Room_Status'
//                AND g.Code = r.`Status`
//                LEFT JOIN
//            gen_lookups gc ON gc.Table_Name = 'Room_Cleaning_Days'
//                AND gc.Code = r.Cleaning_Cycle_Code
//        ORDER BY r.idRoom";
//

        $stmt = $dbh->query($query);

        $tableRows = array();
        $idRoom = 0;
        $guests = '';
        $last = array();

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            if ($idRoom != $r['idRoom']) {

                if ($idRoom > 0 && (!isset($roomsOOS[$idRoom]) || $roomsOOS[$idRoom]['Status'] !== ResourceStatus::Unavailable)) {
                    $tableRows[] = self::doDailyMarkup($dbh, $last, $guests, $roomsOOS, $priceModel);
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

        // Print the last room
        if ($last['idRoom'] > 0 && (!isset($roomsOOS[$last['idRoom']]) || $roomsOOS[$last['idRoom']]['Status'] !== ResourceStatus::Unavailable)) {
            $tableRows[] = self::doDailyMarkup($dbh, $last, $guests, $roomsOOS, $priceModel);
        }

        return $tableRows;
    }

    protected static function doDailyMarkup(\PDO $dbh, $r, $guests, $roomsOOS, PriceModel $priceModel) {

        $fixed = array();
        $idVisit = intval($r['idVisit'], 10);
        $stat = '';

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
                    ->sumCurrentRoomCharge($dbh, $priceModel);

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

        //$fixed['Visit_Notes'] = Notes::getNotesDiv($r['Visit_Notes']);
        $fixed['Notes'] = Notes::getNotesDiv($r['Notes']);

        return $fixed;
    }


    public static function roomNOR(PDO $dbh, $startDate, $endDate, $whHosp, $roomGroup) {

        $oneDay = new \DateInterval('P1D');

        if ($startDate == '') {
            return;
        }

        if ($endDate == '') {
            $endDate = date('Y-m-d');
        }

        $stDT = new \DateTime($startDate);
        $endDT = new \DateTime($endDate);
        $endDT->add($oneDay);

        if ($stDT === FALSE || $endDT === FALSE) {
            return;
        }

        $query = "SELECT
    r.idRoom,
    r.Title,
    r.`$roomGroup[0]`,
    r.Max_Occupants,
    s.Span_Start_Date,
    IFNULL(s.Span_End_Date, NOW()) AS `CO_Date`,
    DATEDIFF(IFNULL(s.Span_End_Date, NOW()), s.Span_Start_Date) AS `Nights`,
    hs.idHospital,
    hs.idAssociation
FROM
    room r
        LEFT JOIN
    stays s ON s.idRoom = r.idRoom
        LEFT JOIN
    visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
        LEFT JOIN
    hospital_stay hs on v.idHospital_stay = hs.idHospital_stay
WHERE
    DATEDIFF(IFNULL(s.Span_End_Date, NOW()), s.Span_Start_Date) > 0 $whHosp
        AND s.`On_Leave` = 0
and DATE(s.Span_Start_Date) < '" . $endDT->format('Y-m-d') . "' and ifnull(DATE(s.Span_End_Date),  DATE(now()) ) >= '" . $stDT->format('Y-m-d') ."'"
                . " order by r.Title;";
        $stmt = $dbh->query($query);
        $rows = $stmt->fetchAll();

        $days = array();
        $catDays = array();
        $totals = array();

        $rooms = array();
        $categories = array();
        $now = new \DateTime();
        $now->setTime(23, 59, 59);

        if ($endDT > $now) {
            $endDT = $now->sub($oneDay);
        }

        $th = '';
        $daysInMonths = array();
        $roomsInCategory = array();

        // Set up the days array
        while ($stDT < $endDT) {

            $thisMonth = $stDT->format('M, Y');

            if (isset($daysInMonths[$thisMonth])) {
                $daysInMonths[$thisMonth]++;
            } else {
                $daysInMonths[$thisMonth] = 1;
            }

            $thisDay = $stDT->format('Y-m-d');
            $th .= HTMLTable::makeTh($stDT->format('j'));

            foreach ($rows as $r) {
                $days[$r['idRoom']][$thisDay] = 0;
                $catDays[$r[$roomGroup[0]]][$thisDay] = 0;
            }

            $days['Total'][$thisDay] = 0;

            $stDT->add($oneDay);

        }


        $roomCataegoryTitles = readGenLookupsPDO($dbh, $roomGroup[2]);
        $roomCataegoryTitles[''] = array(0=>'',1=>'Unknown');

        foreach ($rows as $r) {
            $totals[$r['idRoom']] = 0;
            $totals[$r[$roomGroup[0]]] = 0;
            $rooms[$r['idRoom']]['Max'] = $r['Max_Occupants'];
            $rooms[$r['idRoom']]['Title'] = $r['Title'];
            $categories[$r[$roomGroup[0]]]['Title'] = $roomCataegoryTitles[$r[$roomGroup[0]]][1];
        }

        $rooms['Total']['Title'] = 'Total';
        $rooms['Total']['Max'] = 0;
        $totals['Total'] = 0;

        $categories['Total']['title'] = 'Total';


        // Count
        foreach ($rows as $r) {

            $roomsInCategory[$r[$roomGroup[0]]][$r['idRoom']] = 1;

            $rmStartDate = new \DateTime($r['Span_Start_Date']);
            $numNights = $r['Nights'];

            for ($j = 0; $j < $numNights; $j++) {
                $rmDate = $rmStartDate->format('Y-m-d');

                if (isset($days[$r['idRoom']][$rmDate])) {

                    $days[$r['idRoom']][$rmDate]++;
                    $catDays[$r[$roomGroup[0]]][$rmDate]++;
                    $days['Total'][$rmDate]++;
                    $totals[$r['idRoom']]++;
                    $totals[$r[$roomGroup[0]]]++;
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

        $thMonth = '';

        // Month headers
        foreach ($daysInMonths as $m => $c) {
            $thMonth .= HTMLTable::makeTh($m, array('colspan'=>$c));
        }

        $tbl->addHeaderTr(HTMLTable::makeTh(' ') . $thMonth . HTMLTable::makeTh(' ', array('colspan'=>'3')));
        $tbl->addHeaderTr(HTMLTable::makeTh('Room (' . (count($days)-1) . ')') . $th . HTMLTable::makeTh('Room') . HTMLTable::makeTh('Total') . HTMLTable::makeTh('Occupied'));

        $mkup = $tbl->generateMarkup();


        // Category report
        $tbl2 = new HTMLTable();
        $tbl2->addHeaderTr(HTMLTable::makeTh(' ') . $thMonth . HTMLTable::makeTh(' ', array('colspan'=>'2')));
        $tbl2->addHeaderTr(HTMLTable::makeTh($roomGroup[1]) . $th . HTMLTable::makeTh($roomGroup[1]) . HTMLTable::makeTh('Total'));

        foreach ($catDays as $idCat => $rdateArray) {

            $td = HTMLTable::makeTd($categories[$idCat]['Title']. ' (' . count($roomsInCategory[$idCat]) . ')');

            $daysOccupied = 0;
            foreach($rdateArray as $numGuests) {
                $td .= HTMLTable::makeTd($numGuests);
                if ($numGuests > 0 ) {
                    $daysOccupied++;
                }
            }

            $td .= HTMLTable::makeTd($categories[$idCat]['Title']);
            $td .= HTMLTable::makeTd($totals[$idCat]);

            $tbl2->addBodyTr($td);
        }

        $mkup .= $tbl2->generateMarkup();

        return $mkup;
    }

    public static function rescUtilization(PDO $dbh, $startDate, $endDate) {

        if ($startDate == '') {
            return;
        }

        if ($endDate == '') {
            $endDate = date('Y-m-d');
        }

        $oneDay = new DateInterval('P1D');
        $dateFormat = 'Y-m-d';
        $dateTitle = 'j';

        $stDT = new \DateTime($startDate);
        $stDT->setTime(0,0,0);
        $endDT = new \DateTime($endDate);
        $endDT->add($oneDay);

        if ($stDT === FALSE || $endDT === FALSE) {
            return;
        }

        // Counting start date
        $countgDT = new \DateTime($startDate);
        $countgDT->setTime(0, 0, 0);


        // Get all the rooms
        $stResc = $dbh->query("select r.idResource, r.Title "
                . " from resource r left join
resource_use ru on r.idResource = ru.idResource and ru.`Status` = '" . ResourceStatus::Unavailable . "' and DATE(ru.Start_Date) <= '" . $stDT->format('Y-m-d') . "' and DATE(ru.End_Date) >= '" . $endDT->format('Y-m-d') . "'"
                . " where ru.idResource_use is null and r.Type in ('" . ResourceTypes::Room . "', '" . ResourceTypes::RmtRoom . "')"
                . " order by r.Title;");

        $stRows = $stResc->fetchAll(\PDO::FETCH_ASSOC);

        $rescs = array();
        $totals = array();

        foreach ($stRows as $r) {

            $rescs[$r['idResource']] = array(
                'Title'=>$r['Title']);

        }
        unset($stRows);

        $summary = array('nits'=>'Nights', 'oos'=>'OOS', 'to'=>'Delayed', 'un'=>'Unavailable');


        $days = array();

        $th = '';
        $daysInMonths = array();



        // Set up the days array
        while ($countgDT < $endDT) {

            $thisDay = $countgDT->format($dateFormat);
            $th .= HTMLTable::makeTh($countgDT->format($dateTitle));

            $thisMonth = $countgDT->format('M, Y');

            if (isset($daysInMonths[$thisMonth])) {
                $daysInMonths[$thisMonth]++;
            } else {
                $daysInMonths[$thisMonth] = 1;
            }

            foreach ($rescs as $idResc => $r) {

                $days[$idResc][$thisDay]['n'] = 0;
                $days[$idResc][$thisDay]['o'] = 0;
                $days[$idResc][$thisDay]['t'] = 0;
                $days[$idResc][$thisDay]['u'] = 0;

            }

            foreach ($summary as $s => $title) {

                $totals[$s][$thisDay] = 0;

            }


            $countgDT->add($oneDay);

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

            // Collect single day events
            if ($numNights == 0) {
                $numNights++;
            }

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


                    } else if ($r['Status'] == ResourceStatus::Unavailable) {

                        $days[$r['idResource']][$rmDate]['u']++;
                        $totals['un'][$rmDate]++;

                    }
                }

                if ($rmStartDate > $endDT) {
                    break;
                }
                $rmStartDate->add($oneDay);
            }
        }

        // Rooms report
        $tbl = new HTMLTable();
        $thMonth = '';

        // Month headers
        foreach ($daysInMonths as $m => $c) {
            $thMonth .= HTMLTable::makeTh($m, array('colspan'=>$c));
        }

        $tbl->addHeaderTr(HTMLTable::makeTh(' ') . $thMonth . HTMLTable::makeTh(' ', array('colspan'=>'6')));
        $tbl->addHeaderTr(HTMLTable::makeTh('Room ('.count($rescs).')') . $th);

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


        return $tbl->generateMarkup();
    }


}

