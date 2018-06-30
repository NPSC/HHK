<?php
/**
 * VisitCharge.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of VisitCharge
 *
 * @author Eric
 */
class VisitCharges {

    protected $feesCharged = 0; // Current fees charges up until today
    protected $feesToCharge = 0;    // fees to be charged thru the end of the visit
    protected $visitFeeCharged = 0;
    protected $DepositCharged = 0;
    protected $depositPayType = '';

    protected $nightsStayed = 0;
    protected $guestNightsStayed = 0;
    protected $nightsToStay = 0;
    protected $nightsPaid = 0;
    protected $excessPaid = 0;
    protected $nightsToPay = 0;
    protected $glideCredit = 0;
    protected $idVisit;

    /**
     *
     * @var type
     */
    private $itemSums;

    public function __construct($idVisit) {
        $this->idVisit = $idVisit;
        $this->itemSums = array();
    }



    public function sumCurrentRoomCharge(\PDO $dbh, PriceModel $priceModel, $newPayment = 0, $calcDaysPaid = FALSE, $givenPaid = NULL) {
        return $this->getVisitData($priceModel->loadVisitNights($dbh, $this->idVisit), $priceModel, $newPayment, $calcDaysPaid, $givenPaid);
    }


    public function sumDatedRoomCharge(\PDO $dbh, PriceModel $priceModel, $coDate, $newPayment = 0, $calcDaysPaid = FALSE, $givenPaid = NULL) {

        // Get current nights .
        $spans = $priceModel->loadVisitNights($dbh, $this->idVisit, $coDate);

        // Access the last span
        $span = $spans[(count($spans) - 1)];

        $arrDT = new \DateTime($span['Span_Start']);
        $arrDT->setTime(0, 0, 0);
        $depDT = new \DateTime($coDate);

        $span['Expected_Departure'] = $depDT->format('Y-m-d H:i:s');

        $depDT->setTime(0, 0, 0);

        $span['Actual_Span_Nights'] = $depDT->diff($arrDT, TRUE)->days;


        if ($span['Status'] != VisitStatus::CheckedIn) {
            $span['Span_End'] = $depDT->format('Y-m-d H:i:s');
            $span['Actual_Departure'] = $depDT->format('Y-m-d H:i:s');
        }

        $spans[(count($spans) - 1)] = $span;

        return $this->getVisitData($spans, $priceModel, $newPayment, $calcDaysPaid, $givenPaid);
    }

    protected function getVisitData($spans, PriceModel $priceModel, $newPayment = 0, $calcDaysPaid = FALSE, $givenPaid = NULL) {

        $uS = Session::getInstance();

        if ($newPayment > 0) {
            $calcDaysPaid = TRUE;
        }

        $this->visitFeeCharged = 0;
        $visitFeeCharge = 0;

        foreach ($spans as $s) {

            // Search for visit fee
            $amt = floatval($s['Visit_Fee_Amount']);
            if ($amt > $visitFeeCharge) {
                $visitFeeCharge = $amt;
            }

            // Get the last deposit amount.
            $this->DepositCharged = floatval($s['Deposit_Amount']);
            $this->depositPayType = $s['DepositPayType'];

        }

        $this->glideCredit = $spans[0]['Rate_Glide_Credit'];
        $this->nightsStayed = 0;
        $this->guestNightsStayed = 0;
        $this->nightsToStay = 0;
        $this->nightsToPay = 0;
        $this->nightsPaid = 0;
        $this->feesCharged = 0;
        $this->feesToCharge = 0;
        $this->excessPaid = 0;


        // Collect rates
        $rates = Receipt::processRatesRooms($spans);

        if (is_null($givenPaid)) {
            $paid = $this->getRoomFeesPaid() + $this->getRoomFeesPending();
        } else {
            $paid = $givenPaid;
        }

        $srd = NULL;
        $rateSummary = array();


        // orders and rates
        foreach ($rates as $r) {

            $srd = new SpanRateData();
            $srd->spanRate($r, $paid, $calcDaysPaid, $priceModel);

            $paid = $srd->newPaid;

            $rateAmt = array();
            $rateAmt['idrate'] = $r['idrate'];
            $rateAmt['cat'] = $r['cat'];
            $rateAmt['amt'] = $r['amt'];
            $rateAmt['adj'] = $r['adj'];
            $rateAmt['glide'] = $r['glide'];
            $rateAmt['status'] = $r['status'];
            $rateAmt['paid'] = $srd->paid;
            $rateAmt['excess'] = $srd->excessPaid;
            $rateAmt['charged'] = $srd->totCharge;
            $rateAmt['days'] = $srd->totalDays;
            $rateAmt['days2pay'] = $srd->daysToPay;
            $rateAmt['daysPaid'] = $srd->totalDaysPaid;
            $rateAmt['aveGDay'] = $srd->aveGuestsDay;


            $rateSummary[] = $rateAmt;

            $this->guestNightsStayed += $srd->currentGuestDays;
            $this->nightsStayed += $srd->currentDays;
            $this->nightsToStay += $srd->futureDays;
            $this->nightsPaid += $srd->totalDaysPaid;

            $this->feesCharged += $srd->curCharge;
            $this->feesToCharge += $srd->futureCharge;
            $this->excessPaid += $srd->excessPaid;
        }


        if ($calcDaysPaid) {

            $daysBeingPaid = 0;

            foreach ($rateSummary as $rateAmt) {

                if ($rateAmt['days2pay'] == 0 && $rateAmt['status'] != VisitStatus::CheckedIn) {
                    continue;
                }

                // Do I have enough to pay this span?
                $unpaid = $rateAmt['charged'] - $rateAmt['paid'];

                if ($newPayment >= $unpaid && ($unpaid > 0 || $rateAmt['charged'] == 0)) {

                    $daysBeingPaid += $rateAmt['days2pay'];
                    $newPayment -= $unpaid;
                    continue;

                } else {
                    // not enough, or payments into the future.
                    $totPaid = $newPayment + $rateAmt['paid'];
                    $priceModel->setCreditDays($rateAmt['glide']);
                    $daysPaid = $priceModel->daysPaidCalculator($totPaid, $rateAmt['idrate'], $rateAmt['cat'], $rateAmt['amt'], $rateAmt['adj'], $rateAmt['aveGDay']);
                    if ($daysPaid == 0) {
                        $daysBeingPaid += $rateAmt['daysPaid'];
                    } else {
                        $daysBeingPaid += ($daysPaid - $rateAmt['daysPaid']);
                    }
                    $newPayment = $priceModel->getRemainderAmt();
                   break;
                }
            }

            $this->nightsToPay = $daysBeingPaid;
            $this->excessPaid = $newPayment;

        }


        // Should we charge a visit fee now?
        if (($this->getNightsStayed() > $uS->VisitFeeDelayDays || $uS->VisitFeeDelayDays == 0) &&
                $visitFeeCharge > 0 && $visitFeeCharge > ($this->getVisitFeesPaid() + $this->getVisitFeesPending())) {
            $this->visitFeeCharged = $visitFeeCharge - ($this->getVisitFeesPaid() + $this->getVisitFeesPending());
        }

        return $this;

    }


    public function sumPayments(\PDO $dbh) {

        $items = Item::loadItems($dbh);
        $invStatuses = readGenLookupsPDO($dbh, 'Invoice_Status');

        // Pre-define the item sums array
        foreach ($items as $i) {

            foreach ($invStatuses as $s) {
                $this->itemSums[$i['idItem']][$s[0]] = 0;
            }
        }

        $invLines = $this->loadInvoiceLines($dbh, $this->getIdVisit());

        // Sum the amounts
        foreach ($invLines as $l) {

            $stat = $l['Status'];

            if ($stat == InvoiceStatus::Carried) {
                $this->findLastDelegatedInvoiceStatus($l, $invLines, $stat);
            }

            $this->itemSums[$l['Item_Id']][$stat] += $l['Amount'];

        }

        return $this;
    }

    private function findLastDelegatedInvoiceStatus($line, $invLines, &$stat) {

        foreach ($invLines as $l) {

            if ($l['idInvoice'] == $line['Delegated_Invoice_Id']) {

                if ($l['Status'] == InvoiceStatus::Carried) {
                    $this->findLastDelegatedInvoiceStatus($l, $invLines, $stat);

                } else {
                    $stat = $l['Status'];
                }

                break;
            }
        }

        return;
    }


    public static function loadInvoiceLines(\PDO $dbh, $idVisit) {

        if ($idVisit < 1) {
            return array();
        }

        $query = "select
    il.idInvoice_Line,
    i.idInvoice,
    i.Invoice_Number,
    i.Delegated_Invoice_Id,
    i.Amount as `Invoice_Amount`,
    i.Sold_To_Id,
    i.idGroup,
    i.Invoice_Date,
    i.`Status`,
    i.Carried_Amount,
    i.Balance,
    il.Price,
    il.Amount,
    il.Quantity,
    il.Description,
    il.Item_Id,
    il.Period_Start,
    il.Period_End
from
    invoice_line il join invoice i ON il.Invoice_Id = i.idInvoice
where
    i.Deleted = 0 and il.Deleted = 0 and i.Order_Number = '" . $idVisit . "'"
                . " order by il.idInvoice_Line";
        $stmt = $dbh->query($query);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);

    }

    public function getRoomFeesPending() {
        return $this->getItemInvPending(ItemId::Lodging) + $this->getItemInvPending(ItemId::LodgingReversal);
    }

    public function getVisitFeesPending() {
        return $this->getItemInvPending(ItemId::VisitFee);
    }

    public function getDepositPending() {
        return $this->getItemInvPending(ItemId::KeyDeposit)
                + $this->getItemInvPending(ItemId::DepositRefund);
    }

    public function getItemInvCharges($idItem) {
        if (isset($this->itemSums[$idItem])) {
            return $this->itemSums[$idItem][InvoiceStatus::Unpaid] + $this->itemSums[$idItem][InvoiceStatus::Paid];
        }
        return 0;
    }

    public function getDepositPayType() {
        return $this->depositPayType;
    }


    public function getItemInvPayments($idItem) {
        if (isset($this->itemSums[$idItem])) {
            return $this->itemSums[$idItem][InvoiceStatus::Paid];
        }
        return 0;
    }

    public function getItemInvPending($idItem) {
        if (isset($this->itemSums[$idItem])) {
            return $this->itemSums[$idItem][InvoiceStatus::Unpaid];
        }
        return 0;
    }

    public function getRoomFeesCharged() {
        return $this->feesCharged;
    }

    public function getDepositCharged() {
        return $this->DepositCharged;
    }

    public function getVisitFeeCharged() {
        return $this->visitFeeCharged;
    }

    public function getNightsStayed() {
        return $this->nightsStayed;
    }

    public function getGuestNightsStayed() {
        return $this->guestNightsStayed;
    }

    public function getNightsPaid() {
        return $this->nightsPaid;
    }

    public function getNightsToPay() {
        return $this->nightsToPay;
    }

    public function getExcessPaid() {
        return $this->excessPaid;
    }

    public function getFeesToPay() {
        return $this->feesToCharge;
    }

    public function getGlideCredit() {
        return $this->glideCredit;
    }

    public function getRoomFeesPaid() {
        return $this->getItemInvPayments(ItemId::Lodging)
                + $this->getItemInvPayments(ItemId::LodgingReversal);
    }

    public function getVisitFeesPaid() {
        return $this->getItemInvPayments(ItemId::VisitFee);
    }

    public function getKeyFeesPaid() {
        return $this->getItemInvPayments(ItemId::KeyDeposit)
                    + $this->getItemInvPayments(ItemId::DepositRefund);
    }

    public function getIdVisit() {
        return $this->idVisit;
    }

}

class CheckinCharges extends VisitCharges {

    /**
     *
     * @param \PDO $dbh
     * @param int $idVisit
     * @param float $visitFeeCharged
     * @param float $depositCharged
     */
    public function __construct($idVisit, $visitFeeCharged, $depositCharged) {
        parent::__construct($idVisit);

        $this->DepositCharged = $depositCharged;
        $this->visitFeeCharged = $visitFeeCharged;
    }

    public function getRoomFeesPaid() {

        // TODO:  look for retained lodging fees.
        return 0;
    }

}

class SpanRateData {

    public $curCharge;
    public $futureCharge;
    public $totCharge;
    public $paid;
    public $newPaid;

    public $currentDays;
    public $currentGuestDays;
    public $aveGuestsDay;
    public $futureDays;
    public $totalDays;


    public $daysToPay;

    public $currentDaysPaid;
    public $futureDaysPaid;
    public $totalDaysPaid;
    public $excessPaid;


    public function spanRate($r, $paid, $calculateNightsPaid, PriceModel $priceModel) {

        // Figure days stayed
        $this->currentDays = abs($r['days']);

        if (isset($r['gdays'])) {
            $this->currentGuestDays = abs($r['gdays']);
        } else {
            $this->currentGuestDays = abs($r['days']);
        }
        $this->paid = $paid;
        $this->futureDays = 0;
        $this->futureCharge = 0.0;
        $this->currentDaysPaid = 0;
        $this->futureDaysPaid = 0;
        $this->totalDaysPaid = 0;
        $this->excessPaid = 0;

        $this->newPaid = 0;

        $this->aveGuestsDay = 1;

        if ($this->currentDays > 0) {
            $this->aveGuestsDay = $this->currentGuestDays / $this->currentDays;
        }

        // any future days?
        if ($r['status'] == VisitStatus::CheckedIn) {

            $expDepDT = new \DateTime($r['expEnd']);
            $expDepDT->setTime(0, 0, 0);
            $now = new \DateTime();
            $now->setTime(0, 0, 0);

            if ($expDepDT > $now) {
                $this->futureDays = $expDepDT->diff($now, TRUE)->days;
            }
        }

        $this->totalDays = $this->currentDays + $this->futureDays;

        // Figure charges
        $priceModel->setCreditDays($r['glide']);
        $this->curCharge = (1 + $r['adj'] / 100) * $priceModel->amountCalculator($this->currentDays, $r['idrate'], $r['cat'], $r['amt'], $this->currentGuestDays);


        if ($this->futureDays > 0) {

            $priceModel->setCreditDays($r['glide']);
            $this->totCharge = (1 + $r['adj'] / 100) * $priceModel->amountCalculator($this->totalDays, $r['idrate'], $r['cat'], $r['amt'], ($this->totalDays * $this->aveGuestsDay));
            $this->futureCharge = $this->totCharge - $this->curCharge;

        } else {
            $this->totCharge = $this->curCharge;
            $this->futureCharge = 0;
        }

        if ($calculateNightsPaid === FALSE) {
            return;
        }

        // calculate nights paid
        if ($paid == 0) {
            // not paid yet.
            $this->totalDaysPaid = 0;
            $this->currentDaysPaid = 0;
            $this->futureDaysPaid = 0;
            $this->daysToPay = $this->totalDays;

        } else if ($paid >= $this->totCharge && $r['status'] != VisitStatus::CheckedIn) {
            // Paid in full.

            $this->totalDaysPaid = $this->totalDays;
            $this->currentDaysPaid = $this->currentDays;
            $this->futureDaysPaid = $this->futureDays;
            $this->daysToPay = 0;

            $this->newPaid = ($paid - $this->totCharge);

        } else {

            $priceModel->setCreditDays($r['glide']);
            $daysPaid = $priceModel->daysPaidCalculator($paid, $r['idrate'], $r['cat'], $r['amt'], $r['adj'], $this->aveGuestsDay);
            $this->excessPaid = $priceModel->getRemainderAmt();

            $this->totalDaysPaid = $daysPaid;

            if ($daysPaid > 0 && $daysPaid > $this->currentDays) {

                $this->futureDaysPaid = $daysPaid - $this->currentDays;
                $this->currentDaysPaid = $this->currentDays;

            } else if ($daysPaid > 0) {

                $this->currentDaysPaid = $daysPaid;

            }

            $this->daysToPay = $this->totalDays - $this->totalDaysPaid;

        }
    }
}