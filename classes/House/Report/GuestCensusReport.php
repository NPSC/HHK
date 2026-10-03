<?php

namespace HHK\House\Report;

use HHK\Common;
use HHK\ExcelHelper;
use HHK\ExcelRichText;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLTable;
use HHK\sec\Session;
use HHK\sec\Labels;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\Purchase\VisitCharges;
use HHK\SysConst\{ItemId, InvoiceStatus, ReservationStatusType};


/**
 * GuestCensusReport.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Day-by-day guest census over the selected time frame: check-ins, check-outs,
 * occupied/paid room-nights, a guest roster, and cancellations by cancel code.
 *
 * Occupancy is based on actual `stays`/`visit` records (who was physically
 * checked in), not on reservation Expected/Actual dates, so waitlisted,
 * unconfirmed, no-show and cancelled reservations are naturally excluded.
 *
 * @author Will
 */

class GuestCensusReport extends AbstractReport implements ReportInterface {

    const PAID_COLOR = '#000000';
    const UNPAID_COLOR = '#CC0000';

    /** @var array<string,string> cancel-type ReservStatus codes => Title, e.g. ['c' => 'Guest Canceled', ...] */
    private array $cancelCodes = [];

    /** @var string "excel" or "here" - the guest roster renders as plain text for Excel, HTML pills on screen. */
    private string $dispType;

    private ?AbstractPriceModel $priceModel = null;

    /** @var array<string,int> column key => total over the report period, for every column except Date and GuestRoster */
    private array $totals = [];

    public function __construct(\PDO $dbh, array $request = []){
        $uS = Session::getInstance();

        $this->reportTitle = $uS->siteName . ' ' . Labels::getString('memberType', 'guest', 'Guest') . ' Census Report';
        $this->inputSetReportName = "guestcensus";

        $this->dispType = (filter_has_var(INPUT_POST, "btnExcel-" . $this->inputSetReportName) ? "excel" : "here");

        foreach (Common::readLookups($dbh, 'ReservStatus', 'Code', FALSE) as $status) {
            if ($status['Type'] == ReservationStatusType::Cancelled) {
                $this->cancelCodes[$status['Code']] = $status['Title'];
            }
        }

        parent::__construct($dbh, $this->inputSetReportName, $request);

        $this->printFooter = true;
        $this->printKeepHtml = true;
    }

    public function makeQuery(): void {
        // Result rows are computed day-by-day in PHP (see getResultSet()) rather
        // than by a single flat SQL statement, so there's nothing to build here.
    }

    public function makeFields(): array {

        $fields = [];

        $fields[] = array("Date", 'Date', 'checked', 'f', 'MM/DD/YYYY', '15', array(), 'date');
        $fields[] = array("Checked In", 'CheckedIn', 'checked', '', 'integer', '10');
        $fields[] = array("Checked Out", 'CheckedOut', 'checked', '', 'integer', '10');
        $fields[] = array("People Paid for Lodging", 'PeoplePaid', 'checked', '', 'integer', '10');
        $fields[] = array("Rooms Paid", 'RoomsPaid', 'checked', '', 'integer', '10');
        $fields[] = array("Rooms Unpaid", 'RoomsUnpaid', 'checked', '', 'integer', '10');
        $fields[] = array("Rooms Occupied", 'RoomsOccupied', 'checked', '', 'integer', '10');
        $fields[] = array("People in House", 'PeopleInHouse', 'checked', '', 'integer', '10');
        $fields[] = array("Guest Roster", 'GuestRoster', 'checked', '', 'string', '40');

        foreach ($this->cancelCodes as $code => $title) {
            $fields[] = array('Canceled: ' . $title, 'Canceled_' . $code, 'checked', '', 'integer', '10');
        }

        foreach ($this->cancelCodes as $code => $title) {
            $fields[] = array('Bednights Canceled: ' . $title, 'Bednights_' . $code, 'checked', '', 'integer', '10');
        }

        return $fields;
    }

    public function makeFilterMkup(): void {
        $this->filterMkup .= $this->filter->timePeriodMarkup()->generateMarkup();
        $this->filterMkup .= $this->getColSelectorMkup();
    }

    public function makeSummaryMkup(): string {

        $mkup = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));
        $mkup .= HTMLContainer::generateMarkup('p', 'Report Period: ' . date('M j, Y', strtotime($this->filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($this->filter->getReportEnd())));

        if (count($this->resultSet) > 0) {

            $tbl = new HTMLTable();

            foreach ($this->getSummaryStats() as $label => $value) {
                $tbl->addBodyTr(HTMLTable::makeTh($label, ['class'=>'tdlabel']) . HTMLTable::makeTd($value));
            }

            $mkup .= $tbl->generateMarkup(['class'=>'mt-2']);
        }

        return $mkup;
    }

    /**
     * Summary box lines, computed from the period totals.
     *
     * @return array<string,string> label => display value
     */
    private function getSummaryStats(): array {

        $uS = Session::getInstance();
        $t = $this->totals;
        $nights = count($this->resultSet);
        $occupied = $t['RoomsOccupied'] ?? 0;
        $unpaid = $t['RoomsUnpaid'] ?? 0;
        $people = $t['PeopleInHouse'] ?? 0;
        $checkedIn = $t['CheckedIn'] ?? 0;

        $guestsCanceled = 0;
        foreach (array_keys($this->cancelCodes) as $code) {
            $guestsCanceled += $t['Canceled_' . $code] ?? 0;
        }

        $stats = [
            'Number of Guests Canceled' => (string) $guestsCanceled,
            'Percentage of Rooms Unpaid' => ($occupied > 0 ? round(100 * $unpaid / $occupied) . "% ($unpaid / $occupied room nights)" : 'n/a'),
        ];

        // Sleeping Spaces are only editable in Resource Builder when showSleepingSpaces is on.
        if ($uS->showSleepingSpaces) {
            $bedNights = $nights * $this->getSleepingSpaces();
            $stats['Percentage of Bednights'] = ($bedNights > 0 ? round(100 * $people / $bedNights) . "% ($people / $bedNights bednights)" : 'n/a - set Sleeping Spaces in Resource Builder');
        }

        $stats['Average Length of Stay'] = ($checkedIn > 0 ? number_format($people / $checkedIn, 2) . " nights ($people / $checkedIn checked in)" : 'n/a');

        return $stats;
    }

    /**
     * Total sleeping spaces of the rooms in service during the report period - rooms
     * whose resources were all retired before the period starts are left out.
     */
    private function getSleepingSpaces(): int {

        $stmt = $this->dbh->query("select ifnull(sum(r.Sleeping_Spaces), 0)
from room r
where exists (select 1 from resource_room rr join resource re on rr.idResource = re.idResource
    where rr.idRoom = r.idRoom and (re.Retired_At is null or date(re.Retired_At) > '" . $this->filter->getReportStart() . "'))");

        return intval($stmt->fetchColumn());
    }

    protected function makeFooterMkup(HTMLTable $tbl): void {

        if (count($this->resultSet) == 0) {
            return;
        }

        $style = ['style'=>'font-weight:bold; border-top:2px solid black;'];
        $tr = '';

        foreach ($this->filteredFields as $f) {

            if ($f[1] == 'Date') {
                $tr .= HTMLTable::makeTd('Total', $style);
            } else {
                $tr .= HTMLTable::makeTd($this->totals[$f[1]] ?? '', $style);
            }
        }

        $tbl->addFooterTr($tr);
    }

    protected function writeExcelFooter(ExcelHelper $writer, array $hdr): void {

        if (count($this->resultSet) == 0) {
            return;
        }

        // A label in the Date column would be read as a date, so the roster column carries it.
        $flds = [];
        foreach ($this->filteredFields as $f) {

            if ($f[1] == 'GuestRoster') {
                $flds[] = 'Total';
            } else {
                $flds[] = $this->totals[$f[1]] ?? '';
            }
        }

        $writer->writeSheetRow("Sheet1", $flds, ['font-style'=>'bold']);

        $writer->writeSheetHeader("Summary", ['Summary'=>'string', 'Value'=>'string'], $writer->getHdrStyle(['30', '45']));
        foreach ($this->getSummaryStats() as $label => $value) {
            $writer->writeSheetRow("Summary", [$label, $value]);
        }
    }

    /**
     * Builds one row per calendar day in the report range. Overridden (rather than
     * relying on $this->query) because several columns - the guest roster and the
     * per-cancel-code totals - are aggregated across many stays/visit/log rows per
     * day, which is more naturally done in PHP than in a single flat SQL statement.
     */
    public function getResultSet(): array {

        $start = $this->filter->getReportStart();
        $queryEnd = $this->filter->getQueryEnd();

        $this->resultSet = [];

        if ($start == $queryEnd) {
            return $this->resultSet;
        }

        $dayRooms = $this->getRoomsByDay($start, $queryEnd);
        [$checkIns, $checkOuts] = $this->getCheckInOutCountsByDay($start, $queryEnd);
        [$cancelCounts, $cancelBednights] = $this->getCancellationsByDay($start, $queryEnd);

        $curDate = new \DateTime($start);
        $endDt = new \DateTime($queryEnd);

        for (; $curDate < $endDt; $curDate->add(new \DateInterval('P1D'))) {

            $d = $curDate->format('Y-m-d');
            $rooms = $dayRooms[$d] ?? [];

            $roomsPaid = 0;
            $roomsUnpaid = 0;
            $peopleInHouse = 0;
            $peoplePaid = 0;
            $rosterParts = [];
            $printParts = [];
            $excelRoster = new ExcelRichText();

            foreach ($rooms as $room) {

                $guestCount = count($room['guests']);
                $peopleInHouse += $guestCount;

                if ($room['isPaid']) {
                    $roomsPaid++;
                    $peoplePaid += $guestCount;
                } else {
                    $roomsUnpaid++;
                }

                // Spec: primary guest's last name and party size, red when unpaid that night, black when paid.
                $entryText = ($room['primaryLastName'] != '' ? $room['primaryLastName'] : 'Unknown') . ' (' . $guestCount . ')';
                $color = ($room['isPaid'] ? self::PAID_COLOR : self::UNPAID_COLOR);

                if ($this->dispType == 'excel') {

                    if (!$excelRoster->isEmpty()) {
                        $excelRoster->addRun(', ');
                    }
                    $excelRoster->addRun($entryText, $color);

                } else {

                    $primaryName = $room['primaryLastName'] != '' ? $room['primaryLastName'] : 'Unknown';

                    // Bootstrap's own "badge in a badge" pattern for a labeled count.
                    $pillContent = htmlspecialchars($primaryName) . ' '
                        . HTMLContainer::generateMarkup('span', $guestCount, ['class'=>'badge rounded-pill bg-light text-dark']);

                    $pillClass = 'badge rounded-pill d-inline-flex align-items-center gap-1 text-decoration-none hhk-guest-pill '
                        . ($room['isPaid'] ? 'bg-secondary text-white' : 'bg-danger text-white');

                    if ($room['primaryIdName'] > 0) {
                        $entry = HTMLContainer::generateMarkup('a', $pillContent, [
                            'href' => 'GuestEdit.php?id=' . $room['primaryIdName'],
                            'target' => '_blank',
                            'title' => htmlspecialchars("Go to $primaryName's Guest Edit page"),
                            'class' => $pillClass . ' hhk-guest-pill-link',
                        ]);
                    } else {
                        $entry = HTMLContainer::generateMarkup('span', $pillContent, ['class'=>$pillClass]);
                    }

                    $rosterParts[] = $entry;

                    // The print view has no Bootstrap styles, so it prints this plain red/black version instead of the pills.
                    $printParts[] = HTMLContainer::generateMarkup('span', htmlspecialchars($entryText),
                        ['style' => "color:$color;" . ($room['isPaid'] ? '' : ' font-weight:bold;')]);
                }
            }

            $row = [
                'Date' => $d . ' 00:00:00',
                'CheckedIn' => $checkIns[$d] ?? 0,
                'CheckedOut' => $checkOuts[$d] ?? 0,
                'PeoplePaid' => $peoplePaid,
                'RoomsPaid' => $roomsPaid,
                'RoomsUnpaid' => $roomsUnpaid,
                'RoomsOccupied' => count($rooms),
                'PeopleInHouse' => $peopleInHouse,
                'GuestRoster' => ($this->dispType == 'excel' ? $excelRoster
                    : (count($rosterParts) > 0 ? HTMLContainer::generateMarkup('span', implode('', $rosterParts), ['data-print' => htmlspecialchars(implode(', ', $printParts), ENT_QUOTES)]) : '')),
            ];

            foreach (array_keys($this->cancelCodes) as $code) {
                $row['Canceled_' . $code] = $cancelCounts[$d][$code] ?? 0;
            }

            foreach (array_keys($this->cancelCodes) as $code) {
                $row['Bednights_' . $code] = $cancelBednights[$d][$code] ?? 0;
            }

            $this->resultSet[] = $row;
        }

        $this->totals = [];
        foreach ($this->resultSet as $row) {
            foreach ($row as $k => $v) {
                if ($k != 'Date' && $k != 'GuestRoster') {
                    $this->totals[$k] = ($this->totals[$k] ?? 0) + $v;
                }
            }
        }

        return $this->resultSet;
    }

    /**
     * Buckets every stays/visit span overlapping [$start, $queryEnd) by the calendar
     * nights it covers within that window.
     *
     * @return array<string,array<string,array{isPaid:bool,primaryLastName:string,guests:array}>>
     *         keyed by date (Y-m-d) => "idVisit-Span" => room occupancy data for that night
     */
    private function getRoomsByDay(string $start, string $queryEnd): array {

        // A guest still checked in past their expected checkout is still here tonight,
        // so their open stay runs through tonight rather than ending on the expected date.
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $stayEnd = "date(ifnull(s.Span_End_Date, case when s.Expected_Co_Date is null or date(s.Expected_Co_Date) < '$today' then '$tomorrow' else s.Expected_Co_Date end))";

        $query = "select
    v.idVisit,
    v.Span,
    s.idName,
    v.idPrimaryGuest,
    ifnull(pg.Name_Last, '') as Primary_Last,
    date(s.Span_Start_Date) as SpanStart,
    $stayEnd as SpanEnd,
    (select date(min(v2.Span_Start)) from visit v2 where v2.idVisit = v.idVisit) as VisitStart
from stays s
    join visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
    left join name pg on v.idPrimaryGuest = pg.idName
where date(s.Span_Start_Date) < '" . $queryEnd . "'
    and $stayEnd > '" . $start . "'
order by v.idVisit, v.Span";

        $stmt = $this->dbh->query($query);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $paidThru = $this->getPaidThruDates($rows);

        $dayRooms = [];

        foreach ($rows as $r) {

            $key = $r['idVisit'] . '-' . $r['Span'];

            $spanStart = max($r['SpanStart'], $start);
            $spanEndExcl = min($r['SpanEnd'], $queryEnd);

            if ($spanStart >= $spanEndExcl) {
                continue;
            }

            $curDate = new \DateTime($spanStart);
            $endDt = new \DateTime($spanEndExcl);

            for (; $curDate < $endDt; $curDate->add(new \DateInterval('P1D'))) {

                $d = $curDate->format('Y-m-d');

                // The roster always names the visit's primary guest, even on nights they weren't staying.
                if (!isset($dayRooms[$d][$key])) {
                    $dayRooms[$d][$key] = [
                        'isPaid' => $d < $paidThru[$r['idVisit']],
                        'primaryLastName' => $r['Primary_Last'],
                        'primaryIdName' => intval($r['idPrimaryGuest']),
                        'guests' => [],
                    ];
                }

                $dayRooms[$d][$key]['guests'][] = $r['idName'];
            }
        }

        return $dayRooms;
    }

    /**
     * Invoices are only cut for the amount actually paid, so the visit's real room
     * charge has to come from the price model. A night counts as paid when it falls
     * within the nights covered by guest and 3rd-party lodging payments, counted from
     * the first night of the visit. Like the Visit Interval Report, house payments
     * (waives, discounts and subsidy invoices) and unpaid invoices don't count, and a
     * partly paid night is unpaid.
     *
     * @param array $rows getRoomsByDay() rows; only idVisit and VisitStart are used
     * @return array<int,string> idVisit => Y-m-d of the visit's first unpaid night
     */
    private function getPaidThruDates(array $rows): array {

        $visitStarts = [];
        foreach ($rows as $r) {
            $visitStarts[intval($r['idVisit'])] = $r['VisitStart'];
        }

        if (count($visitStarts) == 0) {
            return [];
        }

        $uS = Session::getInstance();

        // Waive and discount lines are negative, so they net the house share out of the lodging total.
        $stmt = $this->dbh->query("select i.Order_Number as idVisit, sum(il.Amount) as Paid
from invoice_line il
    join invoice i on il.Invoice_Id = i.idInvoice
where il.Deleted = 0 and i.Deleted = 0
    and i.`Status` in ('" . InvoiceStatus::Paid . "', '" . InvoiceStatus::Carried . "')
    and il.Item_Id in (" . ItemId::Lodging . ", " . ItemId::Waive . ", " . ItemId::Discount . ", " . ItemId::LodgingReversal . ")
    and i.Sold_To_Id != " . intval($uS->subsidyId) . "
    and i.Order_Number in (" . implode(',', array_keys($visitStarts)) . ")
group by i.Order_Number");

        $paidAmts = [];
        while ($p = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $paidAmts[intval($p['idVisit'])] = floatval($p['Paid']);
        }

        $paidThru = [];

        foreach ($visitStarts as $idVisit => $visitStart) {

            $nightsPaid = 0;
            $paid = $paidAmts[$idVisit] ?? 0;

            if ($paid > 0) {

                if (is_null($this->priceModel)) {
                    $this->priceModel = AbstractPriceModel::priceModelFactory($this->dbh, $uS->RoomPriceModel);
                }

                $visitCharge = new VisitCharges($idVisit);
                $visitCharge->sumCurrentRoomCharge($this->dbh, $this->priceModel, 0, TRUE, $paid);
                $nightsPaid = $visitCharge->getNightsPaid();
            }

            $dt = new \DateTime($visitStart);
            $dt->add(new \DateInterval('P' . $nightsPaid . 'D'));
            $paidThru[$idVisit] = $dt->format('Y-m-d');
        }

        return $paidThru;
    }

    /**
     * @return array{0: array<string,int>, 1: array<string,int>} [checkIns, checkOuts], each keyed by date (Y-m-d)
     */
    private function getCheckInOutCountsByDay(string $start, string $queryEnd): array {

        $checkIns = [];

        $stmt = $this->dbh->query("select date(s.Checkin_Date) as D, count(distinct s.idVisit, s.idName) as Cnt
from stays s
where s.Checkin_Date is not null
    and date(s.Checkin_Date) >= '" . $start . "' and date(s.Checkin_Date) < '" . $queryEnd . "'
group by date(s.Checkin_Date)");

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $checkIns[$r['D']] = (int) $r['Cnt'];
        }

        $checkOuts = [];

        $stmt = $this->dbh->query("select date(s.Checkout_Date) as D, count(distinct s.idVisit, s.idName) as Cnt
from stays s
where s.Checkout_Date is not null
    and date(s.Checkout_Date) >= '" . $start . "' and date(s.Checkout_Date) < '" . $queryEnd . "'
group by date(s.Checkout_Date)");

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $checkOuts[$r['D']] = (int) $r['Cnt'];
        }

        return [$checkIns, $checkOuts];
    }

    /**
     * Finds reservation status changes into a cancel-type code within [$start, $queryEnd),
     * bucketed by the date the change was logged (not the expected arrival date).
     *
     * Guest count and nights-canceled are read from the reservation's current
     * Expected_Arrival/Expected_Departure and reservation_guest rows, since the
     * activity log only records the status change itself, not a full snapshot.
     *
     * @return array{0: array<string,array<string,int>>, 1: array<string,array<string,int>>}
     *         [counts, bednights], each keyed by date (Y-m-d) => cancel code => total
     */
    private function getCancellationsByDay(string $start, string $queryEnd): array {

        $counts = [];
        $bednights = [];

        if (count($this->cancelCodes) == 0) {
            return [$counts, $bednights];
        }

        $query = "select
    date(rl.Timestamp) as CancelDate,
    rl.Log_Text,
    datediff(r.Expected_Departure, r.Expected_Arrival) as Nights,
    (select count(*) from reservation_guest rg where rg.idReservation = rl.idReservation) as GuestCount
from reservation_log rl
    join reservation r on rl.idReservation = r.idReservation
where rl.Log_Type = 'reservation' and rl.Sub_Type = 'update'
    and rl.Log_Text like '%`Status`%'
    and date(rl.Timestamp) >= '" . $start . "' and date(rl.Timestamp) < '" . $queryEnd . "'
order by rl.Timestamp";

        $stmt = $this->dbh->query($query);

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $decoded = json_decode($r['Log_Text']);

            if (!is_object($decoded)) {
                continue;
            }

            $newStatus = null;

            foreach ($decoded as $k => $v) {

                if (str_ireplace('`', '', $k) !== 'Status') {
                    continue;
                }

                if (stristr($v, '|_|') !== FALSE) {
                    $parts = explode('|', $v);
                    if (count($parts) == 3) {
                        $newStatus = $parts[2];
                    }
                }
            }

            if ($newStatus === null || !isset($this->cancelCodes[$newStatus])) {
                continue;
            }

            $d = $r['CancelDate'];
            $guestCount = (int) $r['GuestCount'];
            $nights = max(0, (int) $r['Nights']);

            $counts[$d][$newStatus] = ($counts[$d][$newStatus] ?? 0) + $guestCount;
            $bednights[$d][$newStatus] = ($bednights[$d][$newStatus] ?? 0) + ($guestCount * $nights);
        }

        return [$counts, $bednights];
    }
}
