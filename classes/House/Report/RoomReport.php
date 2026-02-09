<?php

namespace HHK\House\Report;

use DateTime;
use HHK\Common;
use HHK\House\OperatingHours;
use HHK\House\ResourceView;
use HHK\Notes;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLTable;
use HHK\House\Resource\ResourceTypes;
use HHK\Purchase\VisitCharges;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\sec\SysConfig;
use HHK\SysConst\ItemId;
use HHK\SysConst\ResourceStatus;
use HHK\SysConst\RoomState;
use HHK\SysConst\VisitStatus;
use HHK\sec\Session;



/**
 * RoomReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of RoomReport
 * @author Eric
 */
class RoomReport {

    /**
     * Summary of roomNightsbyMonth
     * @var array
     */
    protected $roomNights = [];

    /**
     * Summary of getGlobalNightsCount
     * @param \PDO $dbh
     * @param mixed $year
     * @param mixed $fyDiffMonths
     * @return int
     */
    public static function getGlobalNightsCount(\PDO $dbh, $year = '', $fyDiffMonths = 0) {

        $niteCount = 0;

        if ($year != '') {


            $stmt = $dbh->query("CALL sum_stay_days('$year', '$fyDiffMonths')");

            while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {
                $niteCount = $r[0];
            }

        } else {

            // Entire history
            $query = "SELECT SUM(DATEDIFF(
IFNULL(s.Span_End_Date, NOW()),
DATE(s.Span_Start_Date))) AS `Nights`
FROM stays s WHERE s.`On_Leave` = 0  and s.Span_Start_Date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)";

            $stmt = $dbh->query($query);
            $rows = $stmt->fetchAll();
            if (count($rows) == 1) {
                $niteCount = $rows[0][0];
            }
        }

        return intval($niteCount);
    }

    /**
     * Summary of getGlobalStaysCount
     * @param \PDO $dbh
     * @param mixed $year
     * @param mixed $fiscalYearMonths
     * @return int
     */
    public static function getGlobalStaysCount(\PDO $dbh, $year = '', $fiscalYearMonths = 0) {

        $whClause = '';
        if ($year != '') {
            $whClause = " and Span_Start_Date < DATE_ADD(fiscal_year('$year-12-31', '-$fiscalYearMonths'), INTERVAL 1 DAY) and Span_End_Date >= fiscal_year('$year-01-01', '-$fiscalYearMonths')";
        }

        $query = "select count(*) from stays where `On_Leave` = 0 and `Status` = 'co' and DATEDIFF(Span_End_Date, Span_Start_Date) > 0" . $whClause;
        $stmt = $dbh->query($query);
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
        if (count($rows) == 1) {
            return intval($rows[0][0]);
        } else {
            return 0;
        }
    }

    /**
     * Summary of getGlobalNightsCounter
     * @param \PDO $dbh
     * @param mixed $previousCount
     * @return string
     */
    public static function getGlobalNightsCounter(\PDO $dbh, $previousCount = 0) {

        $uS = Session::getInstance();
        $comment = '.';

        if ($uS->NightsCounter != '') {
            if ($uS->fy_diff_Months !== 0){
                $comment = " this fiscal year.";
            } else {
                $comment = " this calendar year.";
            }
        }

        if (isset($uS->gnc) === FALSE) {

            $year = '';
            $now = new \DateTime();

            if ($uS->NightsCounter != '') {
                $now = new \DateTime();
                $year = $now->format('Y');

                //fix fiscal year inconsistancy
                if ($uS->fy_diff_Months !== 0){
                    $fiscalMonthShift = new \DateInterval('P' . $uS->fy_diff_Months . 'M');
                    $fiscalYearEnd = (new \DateTime($year .'-12-31'))->sub($fiscalMonthShift);
                    if($fiscalYearEnd < $now){
                        $year++; //if fiscal year has ended, assume next year
                    }
                }

            }
            
            $uS->gnc = intval((self::getGlobalNightsCount($dbh, $year, $uS->fy_diff_Months) + $previousCount) / 10);
        }

        $span = HTMLContainer::generateMarkup('span', 'More than <b>' . number_format($uS->gnc * 10) . '</b> nights of rest' . $comment, array('style'=>'margin-left:10px; font-size:.6em;font-weight:normal;', "class"=>"hideMobile"));

        return $span;
    }

    /**
     * Summary of getGlobalStaysCounter
     * @param \PDO $dbh
     * @param mixed $previousCount
     * @return string
     */
    public static function getGlobalStaysCounter(\PDO $dbh, $previousCount = 0) {

        $uS = Session::getInstance();
        $comment = '.';

        if (!$uS->ShoStaysCtr) {
            return '';
        }

        if ($uS->NightsCounter != '') {
            if ($uS->fy_diff_Months !== 0){
                $comment = " this fiscal year.";
            } else {
                $comment = " this calendar year.";
            }
        }

        if (isset($uS->gsc) === FALSE) {

            $year = '';
            $now = new \DateTime();

            if ($uS->NightsCounter != '') {
                $now = new \DateTime();
                $year = $now->format('Y');

                //fix fiscal year inconsistancy
                if ($uS->fy_diff_Months !== 0){
                    $fiscalMonthShift = new \DateInterval('P' . $uS->fy_diff_Months . 'M');
                    $fiscalYearEnd = (new \DateTime($year .'-12-31'))->sub($fiscalMonthShift);
                    if($fiscalYearEnd < $now){
                        $year++; //if fiscal year has ended, assume next year
                    }
                }
            }



            $uS->gsc = intval((self::getGlobalStaysCount($dbh, $year, $uS->fy_diff_Months) + $previousCount), 10);
        }

        $span = HTMLContainer::generateMarkup('span', number_format($uS->gsc) . ' Stays' . $comment, array('style'=>'margin-left:10px;font-size:.6em;font-weight:normal;', "class"=>"hideMobile"));

        return $span;
    }

    /**
     * Get the current room occupancy percentage optionally filtered by room category
     * @param \PDO $dbh
     * @param string $roomCategory
     * @return array
     */
    public static function getCurrentRoomOccupancy(\PDO $dbh, string $roomCategory = 'none') {
        $returnArr = ["Occupancy" => 0, "Category" => "Total"];

        $whereGroupSql = "";
        if($roomCategory != 'none'){
            $whereGroupSql = " and rm.Category = '" . $roomCategory . "'";
        }
        $query = "select round(sum(if(`idVisit` > 0, 1, 0))/count(r.idResource)*100) as 'Occupancy', g.Description as 'Category' from `resource` r
left join visit v on r.idResource = v.idResource and v.Status = 'a'
left join resource_use ru on r.idResource = ru.idResource and date(ru.Start_Date) <= date(now()) and date(ru.End_Date) > date(now())
left join resource_room rr on r.idResource = rr.idResource
left join room rm on rr.idRoom = rm.idRoom
left join gen_lookups g on rm.Category = g.Code and g.Table_Name = 'Room_Category'
where ru.idResource is null" . $whereGroupSql . ";";
        $stmt = $dbh->query($query);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if(isset($row['Occupancy']) && isset($row['Category'])){
            $returnArr["Occupancy"] = $row['Occupancy'];
            $returnArr["Category"] = $roomCategory != 'none' ? $row['Category'] : 'Total';
        }

        return $returnArr;
    }

    public static function getCurrentRoomOccupancyMkup(\PDO $dbh){
        $uS = Session::getInstance();

        if (!$uS->ShowRoomOcc) {
            return '';
        }

        $occupancyArr = self::getCurrentRoomOccupancy($dbh, $uS->RoomOccCat);
        return HTMLContainer::generateMarkup('span', '<strong>' . $occupancyArr['Occupancy'] . '% </strong>' . $occupancyArr['Category'] . ' Occupancy.', array('style'=>'margin-left:10px;font-size:.6em;font-weight:normal;', "class"=>"hideMobile"));
    }
    
    /**
     * Get the number of vacancies for tonight, taking into account current visits, confirmed reservation and OOS rooms.
     * @param \PDO $dbh
     * @return int
     */
    public static function getTonightVacancies(\PDO $dbh): int {
        $stmt = $dbh->prepare(
            "SELECT
            count(r.idResource) as `Vacancies`
            FROM
            resource r
            WHERE
            (r.Retired_At is null or r.Retired_At >= CURDATE()) -- don't include retired rooms
            AND r.idResource NOT IN (
            -- get visits currently checked in as of today and planning to stay tonight
            SELECT v.idResource
                FROM visit v
                WHERE
                    v.Status not in ('" . VisitStatus::CheckedOut . "')
                    AND v.Arrival_Date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                    AND v.Expected_Departure >= DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            )
            AND r.idResource NOT IN (
            -- find all OOS rooms
            SELECT ru.idResource
                FROM resource_use ru
                WHERE
                    ru.Start_Date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                    AND ru.End_Date >= DATE_ADD(CURDATE(), INTERVAL 1 DAY)

            )
            AND r.idResource NOT IN (

            SELECT res.idResource
                FROM reservation res
                WHERE
                    res.Status in ('a')
                    AND res.Expected_Arrival < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                    AND res.Expected_Departure >= DATE_ADD(CURDATE(), INTERVAL 1 DAY)

            );"
        );

        $stmt->execute();
        $vacancies = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['Vacancies'];
        return $vacancies;

    }

    /**
     * Summary of dailyReport
     * @param \PDO $dbh
     * @return array
     */
    public static function dailyReport(\PDO $dbh) {

        $roomsOOS = array();
        $uS = Session::getInstance();

        $priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

        $roomStatuses = Common::readGenLookupsPDO($dbh, 'Room_Status');

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
    ru.Start_Date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        AND IFNULL(ru.End_Date, NOW()) >= DATE_ADD(CURDATE(), INTERVAL 1 DAY);";

        $stmtrs = $dbh->query($query1);

        while ($r = $stmtrs->fetch(\PDO::FETCH_ASSOC)) {

            if ($r['idRoom'] == 0) {
                continue;
            }

            $roomsOOS[$r['idRoom']] = $r;

       }

       // Get notes
       $stmtn = $dbh->query("SELECT
    rn.idLink as `Reservation_Id`,
    n.User_Name,
    CASE
        WHEN n.Title = '' THEN n.Note_Text
        ELSE CONCAT(n.Title, ' - ', n.Note_Text)
    END AS Note_Text,
    n.`Timestamp`
FROM
visit v join link_note rn on v.idReservation = rn.idLink and linkType = 'reservation'
        JOIN
    note n ON rn.idNote = n.idNote
where v.`Status` = 'a' and n.`Status` = 'a'
ORDER BY rn.idLink, n.`Timestamp` DESC;");

        $notes = array();
        $rv = 0;
        while ($n = $stmtn->fetch(\PDO::FETCH_ASSOC)) {

            if ($rv != $n['Reservation_Id']) {
                $dt = new \DateTime($n['Timestamp']);
                $notes[$n['Reservation_Id']] =  $dt->format('M j, Y H:i ') . $n['User_Name'] . '; ' . $n['Note_Text'];
                $rv = $n['Reservation_Id'];
            }
        }

        $query = "SELECT
            rs.Util_Priority,
            r.idRoom,
            r.`Title`,
            r.`Status`,
            s.On_Leave,
            gc.Substitute as Cleaning_Days,
            IFNULL(g.Description, '') AS `Status_Text`,
            IFNULL(n.Name_Full, '') AS `Name`,
            concat(ifnull(date_format(nt.`Timestamp`, '%b %d, %Y'), ''), ' ', ifnull(nt.`Note_Text`, '')) as `Notes`,
            IFNULL(v.idVisit, 0) AS idVisit,
            IFNULL(v.Span, 0) AS `Span`,
            IFNULL(v.idReservation, 0) AS `idResv`,
            IFNULL(np.Name_Full, '') as `Patient_Name`
        FROM
            room r
                LEFT JOIN
            stays s ON r.idRoom = s.idRoom AND s.`Status` = 'a'
                LEFT JOIN
            `name` n ON s.idName = n.idName
                LEFT JOIN
            visit v ON s.idVisit = v.idVisit AND s.Visit_Span = v.Span
				JOIN
            resource_room rr on r.idRoom = rr.idRoom
                JOIN
			resource rs on rr.idResource = rs.idResource
                LEFT JOIN
            hospital_stay hs on v.idHospital_stay = hs.idHospital_stay
                LEFT JOIN
            name np on hs.idPatient = np.idName
                LEFT JOIN
            gen_lookups g ON g.Table_Name = 'Room_Status' AND g.Code = r.`Status`
                LEFT JOIN
            gen_lookups gc ON gc.Table_Name = 'Room_Cleaning_Days'
                AND gc.Code = r.Cleaning_Cycle_Code
                left join
            note nt on nt.idNote = (select ln.idNote from link_note ln join note n on ln.idNote = n.idNote where ln.idLink = r.idRoom and ln.linkType = 'room' and n.Status = 'a' order by ln.idNote desc limit 1)
        
        where (rs.Retired_At is null or rs.Retired_At > '" . (new DateTime())->format('Y-m-d') . "')
        ORDER BY r.idRoom";


        $stmt = $dbh->query($query);

        $tableRows = array();
        $idRoom = 0;
        $guests = '';
        $last = array();

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            if ($idRoom != $r['idRoom']) {

                if ($idRoom > 0 && (!isset($roomsOOS[$idRoom]) || $roomsOOS[$idRoom]['Status'] !== ResourceStatus::Unavailable)) {
                    $tableRows[] = self::doDailyMarkup($dbh, $last, $guests, $roomsOOS, $notes, $priceModel, $roomStatuses);
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
        	$tableRows[] = self::doDailyMarkup($dbh, $last, $guests, $roomsOOS, $notes, $priceModel, $roomStatuses);
        }

        return $tableRows;
    }

    /**
     * Summary of doDailyMarkup
     * @param \PDO $dbh
     * @param mixed $r
     * @param mixed $guests
     * @param mixed $roomsOOS
     * @param mixed $notes
     * @param \HHK\Purchase\PriceModel\AbstractPriceModel $priceModel
     * @param mixed $roomStatuses
     * @return array
     */
    protected static function doDailyMarkup(\PDO $dbh, $r, $guests, $roomsOOS, $notes, AbstractPriceModel $priceModel, $roomStatuses) {

        $fixed = array();
        $idVisit = intval($r['idVisit'], 10);
        $stat = '';
        $statColor = '';

        
        // Mangle room status
        if ($r['Cleaning_Days'] > 0) {
            if ($idVisit > 0) {
                // active room
                $stat = 'Active-' . $r['Status_Text'];
                $statColor = ResourceView::getRoomStatusColor($r['Status'], true);
            } else {
                // Inactive room
                $stat = $r['Status_Text'];
                $statColor = ResourceView::getRoomStatusColor($r['Status'], false);
            }
        } else {
            $stat = $r['idVisit'] > 0 ? 'Active' : 'Vacant';
        }

        // Check OOS
        if (isset($roomsOOS[$r['idRoom']])) {
            $stat = $roomsOOS[$r['idRoom']]['StatusTitle'] . ': ' . $roomsOOS[$r['idRoom']]['OOSCode'];
        }

        // Check On Leave
        if ($r['On_Leave'] > 0) {
            $stat .= ' (On Leave)';
        }

        $fixed['titleSort'] = $r['Util_Priority'];
        $fixed['Title'] = $r['Title'];
        $fixed['Status'] = $stat;
        $fixed['StatusColor'] = $statColor;
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

        $fixed['Visit_Notes'] = '';

        if (isset($notes[$r['idResv']])) {
            $fixed['Visit_Notes'] = $notes[$r['idResv']];
        }

        $fixed['Notes'] = Notes::getNotesDiv($r['Notes']);

        return $fixed;
    }


    /**
     * Summary of roomNOR
     * @param \PDO $dbh
     * @param mixed $startDate
     * @param mixed $endDate
     * @param mixed $whHosp
     * @param mixed $roomGroup
     * @return string
     */
    public static function roomNOR(\PDO $dbh, $startDate, $endDate, $whHosp, $roomGroup) {

        $oneDay = new \DateInterval('P1D');

        if ($startDate == '') {
            return '';
        }

        if ($endDate == '') {
            $endDate = date('Y-m-d');
        }

        $stDT = new \DateTime($startDate);
        $endDT = new \DateTime($endDate);


        if ($stDT === FALSE || $endDT === FALSE) {
            return '';
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
and s.Span_Start_Date < '" . $endDT->format('Y-m-d') . "' and ifnull(s.Span_End_Date,  now() ) >= '" . $stDT->format('Y-m-d') ."'"
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


        $roomCataegoryTitles = Common::readGenLookupsPDO($dbh, $roomGroup[2]);
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
                $f = ($daysOccupied / (count($rdateArray)) * 100);
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
        $tbl->addHeaderTr(HTMLTable::makeTh('Room (' . (count($days) > 0 ? count($days)-1 : 0) . ')') . $th . HTMLTable::makeTh('Room') . HTMLTable::makeTh('Total') . HTMLTable::makeTh('Occupied'));

        $mkup = $tbl->generateMarkup(array("class"=>"mt-2 mb-2"));


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

        $mkup .= $tbl2->generateMarkup(array("class"=>"mt-2 mb-2"));

        return $mkup;
    }

    protected $summary;
    private $th;
    private $daysInMonths;
    protected $rescs;
    protected $totals;
    protected $days;

    /**
     * Summary of rescUtilization
     * @param \PDO $dbh
     * @param string $startDate
     * @param string $endDate
     * @return string
     */
    public function rescUtilization(\PDO $dbh, $startDate, $endDate) {

        $rescStatuses = Common::readGenLookupsPDO($dbh, "Resource_Status");
        
        $this->collectUtilizationData($dbh, $startDate, $endDate, $rescStatuses);

        // Rooms report
        $tbl = new HTMLTable();
        $thMonth = '';

        // Month headers
        foreach ($this->daysInMonths as $m => $c) {
            $thMonth .= HTMLTable::makeTh($m, array('colspan'=>$c));
        }

        $tbl->addHeaderTr(HTMLTable::makeTh(' ') . $thMonth . HTMLTable::makeTh(' ', array('colspan'=>'6')));
        $tbl->addHeaderTr(HTMLTable::makeTh('Room ('.count($this->rescs).')') . $this->th);

        foreach ($this->days as $idRm => $rdateArray) {

            $tds = HTMLTable::makeTd($this->rescs[$idRm]['Title']);

            $daysOccupied['n'] = 0;
            $daysOccupied['o'] = 0;
            $daysOccupied['t'] = 0;
            $daysOccupied['u'] = 0;
            $daysOccupied['c'] = 0;

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

                } else if ($numbers['c'] > 0) {
                    $tds .= HTMLTable::makeTd(' ', array('style'=>'background-color:lightgray;'));
                    $daysOccupied['c']++;
                } else {
                    $tds .= HTMLTable::makeTd(' ');
                }

            }

            $tds .= HTMLTable::makeTd($this->rescs[$idRm]['Title']);

            foreach ($daysOccupied as $k=>$d) {
                if ((isset($rescStatuses[ResourceStatus::OutOfService]) && $k == "o") ||
                    (isset($rescStatuses[ResourceStatus::Delayed]) && $k == "t") ||
                    (isset($rescStatuses[ResourceStatus::Unavailable]) && $k == "u") ||
                    $k == "n" || $k == "c")
                {
                    $tds .= HTMLTable::makeTd($d, array('style'=>'text-align:right;'));
                }
            }

            $tbl->addBodyTr($tds);
        }

        $ctr = 1;

        foreach ($this->totals as $idRm => $rdateArray) {

            $tds = HTMLTable::makeTd($this->summary[$idRm]);

            $daysOccupied = 0;

            foreach($rdateArray as $day => $numbers) {

                $tds .= HTMLTable::makeTd($numbers);
                $daysOccupied += $numbers;
            }

            $tds .= HTMLTable::makeTd($this->summary[$idRm]);

            for ($i = 1; $i < $ctr; $i++) {
                $tds .= HTMLTable::makeTd('');
            }
            $tds .= HTMLTable::makeTd($daysOccupied, array('style'=>'text-align:right;'));

            $tbl->addBodyTr($tds);
            $ctr++;
        }


        return $tbl->generateMarkup();
    }

    public function collectUtilizationData(\PDO $dbh, $startDate, $endDate, array $rescStatuses) {

        if ($startDate == '') {
            return '';
        }

        if ($endDate == '') {
            $endDate = date('Y-m-d');
        }

        $operatingHours = new OperatingHours($dbh);

        $oneDay = new \DateInterval('P1D');
        $dateFormat = 'Y-m-d';
        $dateTitle = 'j';

        $stDT = new \DateTime($startDate);
        $stDT->setTime(0,0,0);
        $endDT = new \DateTime($endDate);


        if ($stDT === FALSE || $endDT === FALSE) {
            return '';
        }

        // Counting start date
        $countgDT = new \DateTime($startDate);
        $countgDT->setTime(0, 0, 0);


        // Get all the rooms
        $stResc = $dbh->query("select r.idResource, r.Title "
                . " from resource r left join
resource_use ru on r.idResource = ru.idResource and ru.`Status` = '" . ResourceStatus::Unavailable . "' and ru.Start_Date < DATE_ADD('" . $stDT->format('Y-m-d') . "', INTERVAL 1 DAY) and ru.End_Date >= '" . $endDT->format('Y-m-d') . "'"
                . " where ru.idResource_use is null and r.Type in ('" . ResourceTypes::Room . "', '" . ResourceTypes::RmtRoom . "')"
                . " and (r.Retired_At is null or r.Retired_At >= DATE_ADD('" . $stDT->format('Y-m-d') . "', INTERVAL 1 DAY))"
                . " order by r.Title;");

        $stRows = $stResc->fetchAll(\PDO::FETCH_ASSOC);

        $this->rescs = array();
        $this->totals = array();

        foreach ($stRows as $r) {

            $this->rescs[$r['idResource']] = array(
                'Title'=>$r['Title']);

        }
        unset($stRows);

        $this->summary = array('nits'=>'Nights');
        if(isset($rescStatuses[ResourceStatus::OutOfService])){
            $this->summary['oos'] = "OOS";
        }
        if (isset($rescStatuses[ResourceStatus::Delayed])) {
            $this->summary['to'] = 'Delayed';
        }
        if (isset($rescStatuses[ResourceStatus::Unavailable])) {
            $this->summary['un'] = 'Unavailable';
        }
        $this->summary['c'] = 'Closed';


        $this->days = array();

        $this->th = '';
        $this->daysInMonths = array();



        // Set up the days array
        while ($countgDT < $endDT) {

            $thisDay = $countgDT->format($dateFormat);
            $this->th .= HTMLTable::makeTh($countgDT->format($dateTitle));

            $thisMonth = $countgDT->format('M, Y');

            $daysClosed = 0;

            if (isset($this->daysInMonths[$thisMonth])) {
                $this->daysInMonths[$thisMonth]++;
            } else {
                $this->daysInMonths[$thisMonth] = 1;
            }

            foreach ($this->rescs as $idResc => $r) {

                $this->days[$idResc][$thisDay]['n'] = 0;
                $this->days[$idResc][$thisDay]['o'] = 0;
                $this->days[$idResc][$thisDay]['t'] = 0;
                $this->days[$idResc][$thisDay]['u'] = 0;
                
                if($operatingHours->isHouseClosed($countgDT)){
                    $this->days[$idResc][$thisDay]['c'] = 1;
                    $daysClosed++;
                }else{
                    $this->days[$idResc][$thisDay]['c'] = 0;
                }
            }

            foreach ($this->summary as $s => $title) {

                $this->totals[$s][$thisDay] = 0;

            }

            $countgDT->add($oneDay);

        }

        $this->th .= HTMLTable::makeTh('Room') .
        HTMLTable::makeTh('Nights') . 
        (isset($rescStatuses[ResourceStatus::OutOfService]) ? HTMLTable::makeTh('OOS') :'') . 
        (isset($rescStatuses[ResourceStatus::Delayed]) ? HTMLTable::makeTh('Delayed') :'') . 
        (isset($rescStatuses[ResourceStatus::Unavailable]) ? HTMLTable::makeTh('Unavailable') :'') . 
        HTMLTable::makeTh('Closed');


        // Collect visit records
        $query = "select
    v.idResource,
    r.Category,
    r.idSponsor,
    v.Span_Start,
    DATEDIFF(ifnull(v.Span_End, now()), v.Span_Start) as `Nights`
from visit v left join resource r on v.idResource = r.idResource
where v.Status != '" . VisitStatus::Pending . "' and DATEDIFF(ifnull(v.Span_End, now()), v.Span_Start) > 0
and v.Span_Start < '" . $endDT->format('Y-m-d') . "' and ifnull(v.Span_End,  datedefaultnow(v.Expected_Departure) ) >= '" . $stDT->format('Y-m-d') ."' order by r.Title;";

        $stmt = $dbh->query($query);

        // Count nights of use
        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $rmStartDate = new \DateTime($r['Span_Start']);
            $numNights = $r['Nights'];

            for ($j = 0; $j < $numNights; $j++) {
                $rmDate = $rmStartDate->format($dateFormat);

                if (isset($this->days[$r['idResource']][$rmDate])) {

                    $this->days[$r['idResource']][$rmDate]['n']++;
                    $this->days[$r['idResource']][$rmDate]['c'] = 0; //room not closed if someone is staying
                    $this->totals['nits'][$rmDate]++;


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

                if (isset($this->days[$r['idResource']][$rmDate])) {

                    if ($r['Status'] == ResourceStatus::Delayed) {

                        $this->days[$r['idResource']][$rmDate]['t']++;
                        $this->totals['to'][$rmDate]++;

                    } else if ($r['Status'] == ResourceStatus::OutOfService) {

                        $this->days[$r['idResource']][$rmDate]['o']++;
                        $this->totals['oos'][$rmDate]++;


                    } else if ($r['Status'] == ResourceStatus::Unavailable) {

                        $this->days[$r['idResource']][$rmDate]['u']++;
                        $this->totals['un'][$rmDate]++;

                    }

                    $this->days[$r['idResource']][$rmDate]['c'] = 0; //room not closed if there's a resource use record
                }

                if ($rmStartDate > $endDT) {
                    break;
                }
                $rmStartDate->add($oneDay);
            }
        }

        // Collect retired rooms
        $rstmt = $dbh->query("Select idResource, `Retired_At`"
                . " from resource where `Retired_At` < '" . $endDT->format('Y-m-d') . "' and `Retired_At` >= '" . $stDT->format('Y-m-d') ."' order by idResource");

        while ($r = $rstmt->fetch(\PDO::FETCH_ASSOC)) {

            $rmStartDate = new \DateTime($r['Retired_At']);
            $numNights = $rmStartDate->diff($endDT, true)->format('d');

            // Collect single day events
            if ($numNights == 0) {
                $numNights++;
            }

            for ($j = 0; $j < $numNights; $j++) {

                $rmDate = $rmStartDate->format($dateFormat);

                if (isset($this->days[$r['idResource']][$rmDate])) {
                    $this->days[$r['idResource']][$rmDate]['u']++;
                    $this->totals['un'][$rmDate]++;
                    $this->days[$r['idResource']][$rmDate]['c'] = 0; //room not closed if it's retired
                }

                if ($rmStartDate > $endDT) {
                    break;
                }
                $rmStartDate->add($oneDay);
            }
        }

        //count up closed days
        foreach($this->days as $idResc=>$dates){
            foreach($dates as $date=>$numbers){
                if($numbers['c'] > 0){
                    $this->totals['c'][$date]++;
                }
            }
        }

    }


	/**
	 * @return array
	 */
	public function getTotals() {
		return $this->totals;
	}

    /**
	 * @return array
	 */
    public function getDays() {
		return $this->days;
	}

	/**
	 * @return array
	 */
	public function getRescs() {
		return $this->rescs;
	}
}
?>
