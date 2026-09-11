<?php

namespace HHK\House\Report;

use HHK\Common;
use HHK\HTMLControls\HTMLContainer;
use HHK\sec\Session;
use HHK\sec\Labels;
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

    /** @var array<string,string> cancel-type ReservStatus codes => Title, e.g. ['c' => 'Guest Canceled', ...] */
    private array $cancelCodes = [];

    /** @var string "excel" or "here" - the guest roster renders as plain text for Excel, HTML pills on screen. */
    private string $dispType;

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

        return $mkup;
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

            foreach ($rooms as $room) {

                $guestCount = count($room['guests']);
                $peopleInHouse += $guestCount;

                if ($room['isPaid']) {
                    $roomsPaid++;
                    $peoplePaid += $guestCount;
                } else {
                    $roomsUnpaid++;
                }

                $primaryName = $room['primaryLastName'] != '' ? $room['primaryLastName'] : 'Unknown';

                if ($this->dispType == 'excel') {

                    // Excel can't render the HTML pills, so fall back to plain text.
                    $rosterParts[] = $primaryName . ' (' . $guestCount . ')';

                } else {

                    // Bootstrap's own "badge in a badge" pattern for a labeled count.
                    $pillContent = htmlspecialchars($primaryName) . ' '
                        . HTMLContainer::generateMarkup('span', $guestCount, ['class'=>'badge rounded-pill bg-light text-dark']);

                    $pillClass = 'badge rounded-pill d-inline-flex align-items-center gap-1 text-decoration-none hhk-guest-pill '
                        . ($room['isPaid'] ? 'bg-secondary text-white' : 'bg-danger text-white');

                    if ($room['primaryIdName'] > 0) {
                        $entry = HTMLContainer::generateMarkup('a', $pillContent, [
                            'href' => 'GuestEdit.php?id=' . $room['primaryIdName'],
                            'target' => '_blank',
                            'title' => "Go to $primaryName's Guest Edit page",
                            'class' => $pillClass . ' hhk-guest-pill-link',
                        ]);
                    } else {
                        $entry = HTMLContainer::generateMarkup('span', $pillContent, ['class'=>$pillClass]);
                    }

                    $rosterParts[] = $entry;
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
                'GuestRoster' => implode($this->dispType == 'excel' ? ', ' : '', $rosterParts),
            ];

            foreach (array_keys($this->cancelCodes) as $code) {
                $row['Canceled_' . $code] = $cancelCounts[$d][$code] ?? 0;
            }

            foreach (array_keys($this->cancelCodes) as $code) {
                $row['Bednights_' . $code] = $cancelBednights[$d][$code] ?? 0;
            }

            $this->resultSet[] = $row;
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

        $query = "select
    v.idVisit,
    v.Span,
    s.idName,
    n.Name_Last,
    (case when s.idName = v.idPrimaryGuest then 1 else 0 end) as isPrimary,
    date(s.Span_Start_Date) as SpanStart,
    date(ifnull(s.Span_End_Date, datedefaultnow(s.Expected_Co_Date))) as SpanEnd,
    ifnull((
        select sum(il.Amount)
        from invoice_line il
            join invoice i on il.Invoice_Id = i.idInvoice
        where i.Deleted = 0 and il.Deleted = 0 and i.Order_Number = v.idVisit
            and il.Item_Id in (" . ItemId::Lodging . ", " . ItemId::LodgingReversal . ")
    ), 0) as LodgingCharged,
    ifnull((
        select sum(il.Amount)
        from invoice_line il
            join invoice i on il.Invoice_Id = i.idInvoice
        where i.Deleted = 0 and il.Deleted = 0 and i.Order_Number = v.idVisit
            and il.Item_Id in (" . ItemId::Lodging . ", " . ItemId::LodgingReversal . ")
            and i.`Status` in ('" . InvoiceStatus::Paid . "', '" . InvoiceStatus::Carried . "')
    ), 0) as LodgingPaid
from stays s
    join visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
    join name n on s.idName = n.idName
where date(s.Span_Start_Date) < '" . $queryEnd . "'
    and date(ifnull(s.Span_End_Date, datedefaultnow(s.Expected_Co_Date))) > '" . $start . "'
order by v.idVisit, v.Span";

        $stmt = $this->dbh->query($query);

        $dayRooms = [];

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $key = $r['idVisit'] . '-' . $r['Span'];

            $spanStart = max($r['SpanStart'], $start);
            $spanEndExcl = min($r['SpanEnd'], $queryEnd);

            if ($spanStart >= $spanEndExcl) {
                continue;
            }

            // A visit is only "paid" if it has actually been charged for lodging
            // and that charge has been fully covered - no invoice at all (nothing
            // charged yet) counts as unpaid, same as a partially-paid invoice.
            $isPaid = $r['LodgingCharged'] > 0 && $r['LodgingPaid'] >= $r['LodgingCharged'];

            $curDate = new \DateTime($spanStart);
            $endDt = new \DateTime($spanEndExcl);

            for (; $curDate < $endDt; $curDate->add(new \DateInterval('P1D'))) {

                $d = $curDate->format('Y-m-d');

                if (!isset($dayRooms[$d][$key])) {
                    $dayRooms[$d][$key] = [
                        'isPaid' => $isPaid,
                        'primaryLastName' => '',
                        'primaryIdName' => 0,
                        'guests' => [],
                    ];
                }

                $dayRooms[$d][$key]['guests'][] = $r['idName'];

                if ($r['isPrimary'] == 1) {
                    $dayRooms[$d][$key]['primaryLastName'] = $r['Name_Last'];
                    $dayRooms[$d][$key]['primaryIdName'] = $r['idName'];
                }
            }
        }

        return $dayRooms;
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
    r.Expected_Arrival,
    r.Expected_Departure,
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
            $nights = max(0, (strtotime($r['Expected_Departure']) - strtotime($r['Expected_Arrival'])) / 86400);

            $counts[$d][$newStatus] = ($counts[$d][$newStatus] ?? 0) + $guestCount;
            $bednights[$d][$newStatus] = ($bednights[$d][$newStatus] ?? 0) + ($guestCount * $nights);
        }

        return [$counts, $bednights];
    }
}
