<?php

namespace HHK\Purchase;

use HHK\House\Visit\Visit;
use HHK\Payment\Statement;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\SysConst\InvoiceLineType;
use HHK\SysConst\InvoiceStatus;
use HHK\SysConst\ItemId;
use HHK\SysConst\VisitStatus;
use HHK\sec\Session;
/**
 * VisitCharges.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of VisitCharges
 *
 * @author Eric
 */
class VisitCharges {

    const THIRD_PARTY = '3p';
    const TAX_PAID = 'tax';

    /**
     * Summary of feesCharged
     * @var int|float
     */
    protected $feesCharged = 0; // Current fees charges up until today
    /**
     * Summary of feesToCharge
     * @var int|float
     */
    protected $feesToCharge = 0;    // fees to be charged thru the end of the visit
    /**
     * Summary of visitFeeCharged
     * @var int|float
     */
    protected $visitFeeCharged = 0;
    /**
     * Summary of DepositCharged
     * @var int|float
     */
    protected $DepositCharged = 0;
    /**
     * Summary of depositPayType
     * @var string
     */
    protected $depositPayType = '';

    /**
     * Summary of nightsStayed
     * @var int
     */
    protected $nightsStayed = 0;
    /**
     * Summary of guestNightsStayed
     * @var int
     */
    protected $guestNightsStayed = 0;
    /**
     * Summary of nightsToStay
     * @var int
     */
    protected $nightsToStay = 0;
    /**
     * Summary of nightsPaid
     * @var int
     */
    protected $nightsPaid = 0;
    /**
     * Summary of excessPaid
     * @var int|float
     */
    protected $excessPaid = 0;
    /**
     * Summary of nightsToPay
     * @var int
     */
    protected $nightsToPay = 0;
    /**
     * Summary of glideCredit
     * @var int
     */
    protected $glideCredit = 0;
    /**
     * Summary of idVisit
     * @var int
     */
    protected $idVisit;
    /**
     * Summary of span
     * @var int
     */
    protected $span;
    /**
     * Summary of finalVisitCoDate
     * @var \DateTime
     */
    protected $finalVisitCoDate;

    /**
     * Summary of priceModel
     * @var mixed
     */
    protected $priceModel;

    /**
     *
     * @var array
     */
    private $itemSums;

    /**
     * Summary of taxItemIds
     * @var array
     */
    private $taxItemIds;

    /**
     * Summary of __construct
     * @param int $idVisit
     * @param int $span
     */
    public function __construct($idVisit, $span = 0) {
        $this->idVisit = $idVisit;
        $this->span = $span;
        $this->priceModel = NULL;
    }

    /**
     * Summary of sumCurrentRoomCharge
     * @param \PDO $dbh
     * @param \HHK\Purchase\PriceModel\AbstractPriceModel $priceModel
     * @param float|int $newPayment
     * @param bool $calcDaysPaid
     * @param mixed $givenPaid
     * @return VisitCharges
     */
    public function sumCurrentRoomCharge(\PDO $dbh, AbstractPriceModel $priceModel, $newPayment = 0, $calcDaysPaid = FALSE, $givenPaid = NULL) {
        $this->priceModel = $priceModel;
        return $this->getVisitData($priceModel->loadVisitNights($dbh, $this->idVisit), $priceModel, $newPayment, $calcDaysPaid, $givenPaid);
    }

    /**
     * Summary of sumDatedRoomCharge
     * @param \PDO $dbh
     * @param \HHK\Purchase\PriceModel\AbstractPriceModel $priceModel
     * @param string $coDate
     * @param float|int $newPayment
     * @param bool $calcDaysPaid
     * @param mixed $givenPaid
     * @return mixed
     */
    public function sumDatedRoomCharge(\PDO $dbh, AbstractPriceModel $priceModel, $coDate, $newPayment = 0, $calcDaysPaid = FALSE, $givenPaid = NULL) {

        $this->priceModel = $priceModel;
        $depDT = new \DateTime($coDate);

        // Get spans with current nights calculated.
        $spans = $priceModel->loadVisitNights($dbh, $this->idVisit, $depDT);

        // Access the last span, assuming the earlier ones are alreay checked out...
        $span = $spans[(count($spans) - 1)];

        $arrDT = new \DateTime($span['Span_Start']);
        $arrDT->setTime(0, 0, 0);

        // Check stays for last checkout date;
        $allStays = Visit::loadStaysStatic($dbh, $this->idVisit, $this->span, '');
        foreach ($allStays as $stayRS) {

        	// Get the latest checkout date
        	if ($stayRS->Span_End_Date->getStoredVal() != '') {

        		$dt = new \DateTime($stayRS->Span_End_Date->getStoredVal());

        		if ($dt > $depDT) {
        			$depDT = $dt;
        		}
        	}
        }


        $span['Expected_Departure'] = $depDT->format('Y-m-d H:i:s');
        $this->finalVisitCoDate = $depDT;

        $depDT->setTime(0, 0, 0);

        $span['Actual_Span_Nights'] = $depDT->diff($arrDT, TRUE)->days;


        if ($span['Status'] != VisitStatus::CheckedIn) {
            $span['Span_End'] = $depDT->format('Y-m-d H:i:s');
            $span['Actual_Departure'] = $depDT->format('Y-m-d H:i:s');
        }

        $spans[(count($spans) - 1)] = $span;

        reset($spans);

        return $this->getVisitData($spans, $priceModel, $newPayment, $calcDaysPaid, $givenPaid);
    }

    /**
     * Summary of getVisitData
     * @param mixed $spans
     * @param \HHK\Purchase\PriceModel\AbstractPriceModel $priceModel
     * @param float|int $newPayment
     * @param bool $calcDaysPaid
     * @param mixed $givenPaid
     * @return VisitCharges
     */
    protected function getVisitData($spans, AbstractPriceModel $priceModel, $newPayment = 0, $calcDaysPaid = FALSE, $givenPaid = NULL) {

        $uS = Session::getInstance();

        if ($newPayment > 0) {
            $calcDaysPaid = TRUE;
        }

        $this->nightsStayed = 0;
        $this->guestNightsStayed = 0;
        $this->nightsToStay = 0;
        $this->nightsToPay = 0;
        $this->nightsPaid = 0;
        $this->feesCharged = 0;
        $this->feesToCharge = 0;
        $this->excessPaid = 0;
        $this->visitFeeCharged = 0;
        $visitFeeCharge = 0;
        $this->DepositCharged = 0;
        $this->depositPayType = '';

        // Find any visit fees and deposits.
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


        // Collect rates
        $rates = Statement::processRatesRooms($spans);

        if (is_null($givenPaid)) {
            $paid = $this->getRoomFeesPaid() + $this->getRoomFeesPending();
        } else {
            $paid = $givenPaid;
        }


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
            $rateAmt['span'] = $r['span'];


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
            $payment = $newPayment;

            foreach ($rateSummary as $rateAmt) {

                if ($rateAmt['days2pay'] == 0 && $rateAmt['status'] != VisitStatus::CheckedIn) {
                    continue;
                }

                // Do I have enough to pay this span?
                $unpaid = $rateAmt['charged'] - ($payment + $rateAmt['paid']);

                if ($payment >= $unpaid && ($unpaid > 0 || $rateAmt['charged'] == 0) && $rateAmt['span'] < (count($rateSummary) - 1)) {

                    $daysBeingPaid += $rateAmt['days2pay'];
                    $payment -= $unpaid;
                    continue;

                } else {
                    // not enough, or payments into the future.
                    $totPaid = $payment + $rateAmt['paid'];
                    $priceModel->setCreditDays($rateAmt['glide']);
                    $daysPaid = $priceModel->daysPaidCalculator($totPaid, $rateAmt['idrate'], $rateAmt['cat'], $rateAmt['amt'], $rateAmt['adj'], $rateAmt['aveGDay']);

                    if ($daysPaid == 0) {
                        $daysBeingPaid += $rateAmt['daysPaid'];
                    } else {
                        $daysBeingPaid += ($daysPaid - $rateAmt['daysPaid']);

                        if ($daysBeingPaid < 0) {
                            $daysBeingPaid = 0;
                        }
                    }

                    $payment = $priceModel->getRemainderAmt();

                   break;
                }
            }

            $this->nightsToPay = $daysBeingPaid;
            $this->excessPaid = $payment;

        }


        // Should we charge a visit fee now?
        $delayDays = intval($uS->VisitFeeDelayDays);
        if ($visitFeeCharge > 0 && ($delayDays == 0 || $this->getNightsStayed() > $delayDays
                || $this->getVisitFeesPaid() + $this->getVisitFeesPending() > 0)) {
            $this->visitFeeCharged = $visitFeeCharge;
        }

        return $this;
    }


    /**
     * Summary of sumPayments
     * @param \PDO $dbh
     * @return VisitCharges
     */
    public function sumPayments(\PDO $dbh) {

        $this->itemSums = array();
        $this->taxItemIds = array();
        $vat = new ValueAddedTax($dbh);
        $taxitems = $vat->getAllTaxedItems($this->idVisit);

        $items = Item::loadItems($dbh);
        $invStatuses = readGenLookupsPDO($dbh, 'Invoice_Status');

        // Pre-define the item sums array
        foreach ($items as $i) {

            foreach ($invStatuses as $s) {
                $this->itemSums[$i['idItem']][$s[0]] = 0;
            }

            $this->itemSums[$i['idItem']][self::THIRD_PARTY] = 0;
            $this->itemSums[$i['idItem']][self::TAX_PAID] = 0;
            $this->itemSums[$i['idItem']]['tax_exempt'] = 0;
        }

        // predefine taxes
        foreach ($invStatuses as $s) {
            $this->itemSums[self::TAX_PAID][$s[0]] = 0;
        }

        $this->itemSums[self::TAX_PAID][self::THIRD_PARTY] = 0;


        $invLines = $this->loadInvoiceLines($dbh, $this->getIdVisit());

        // Sum the amounts
        foreach ($invLines as $l) {

            $stat = $l['Status'];


            if ($stat == InvoiceStatus::Carried) {
                // find status of my carrying (child) invoice
                $this->findLastDelegatedInvoiceStatus($l, $invLines, $stat);
            }

            $this->itemSums[$l['Item_Id']][$stat] += $l['Amount'];

            // is this a tax?
            if ($l['Type_Id'] == InvoiceLineType::Tax && $stat != InvoiceStatus::Carried) {

                $this->taxItemIds[$l['Item_Id']] = 't';

                $this->itemSums[self::TAX_PAID][$stat] += $l['Amount'];

                $this->itemSums[$l['Source_Item_Id']][self::TAX_PAID] += $l['Amount'];

                // Set item-taxing item amounts.
                if (isset($this->itemSums[$l['Source_Item_Id']][$l['Item_Id']])) {
                    $this->itemSums[$l['Source_Item_Id']][$l['Item_Id']] += $l['Amount'];
                } else {
                    $this->itemSums[$l['Source_Item_Id']][$l['Item_Id']] = $l['Amount'];
                }
            }

            // Third Party invoice
            if ($l['Billing_Agent'] > 0 && $stat == InvoiceStatus::Unpaid) {

                $this->itemSums[$l['Item_Id']][self::THIRD_PARTY] += $l['Amount'];

                if ($l['Type_Id'] == InvoiceLineType::Tax) {
                    $this->itemSums[self::TAX_PAID][self::THIRD_PARTY] += $l['Amount'];
                }
            }

            //Tax exempt
            if($l['tax_exempt'] == 1){
                foreach($taxitems as $taxitem){
                    if($taxitem->getIdTaxedItem() == $l['Item_Id']){
                        $this->itemSums[$l['Item_Id']]['tax_exempt'] += $l['Amount'];
                        break;
                    }
                }
            }

        }

        return $this;
    }

    /**
     * Summary of findLastDelegatedInvoiceStatus
     * @param array $line
     * @param array $invLines
     * @param mixed $stat
     * @return void
     */
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

    /**
     * Summary of loadInvoiceLines
     * @param \PDO $dbh
     * @param int $idVisit
     * @return array
     */
    public static function loadInvoiceLines(\PDO $dbh, $idVisit) {

        $_idVisit = intval($idVisit, 10);

        if ($_idVisit < 1) {
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
    il.Period_End,
    il.Type_Id,
    il.Source_Item_Id,
    i.tax_exempt,
    ifnull(nv.idName, 0) as Billing_Agent
from
    invoice_line il join invoice i ON il.Invoice_Id = i.idInvoice
    left join name_volunteer2 nv on i.Sold_To_Id = nv.idName and nv.Vol_Category = 'Vol_Type' and nv.Vol_Code = 'ba' and nv.Vol_Status = 'a'
where
    i.Deleted = 0 and il.Deleted = 0 and i.Order_Number = '" . $_idVisit . "'"
                . " order by il.idInvoice_Line";
        $stmt = $dbh->query($query);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);

    }

    /**
     * Summary of getPriceModel
     * @return AbstractPriceModel
     */
    public function getPriceModel() {
        return $this->priceModel;
    }

    /**
     * Summary of getRoomFeesPending
     * @return float|int
     */
    public function getRoomFeesPending() {
        return $this->getItemInvPending(ItemId::Lodging) + $this->getItemInvPending(ItemId::LodgingReversal);
    }

    /**
     * Summary of get3pRoomFeesPending
     * @return float|int
     */
    public function get3pRoomFeesPending() {
        return $this->get3rdPartyPending(ItemId::Lodging) + $this->get3rdPartyPending(ItemId::LodgingReversal);
    }

    /**
     * Summary of getTaxExemptRoomFees
     * @return float|int
     */
    public function getTaxExemptRoomFees() {
        return $this->getTaxExempt(ItemId::Lodging) + $this->getTaxExempt(ItemId::LodgingReversal);
    }

    /**
     * Summary of getVisitFeesPending
     * @return float|int
     */
    public function getVisitFeesPending() {
        return $this->getItemInvPending(ItemId::VisitFee);
    }

    /**
     * Summary of get3pVisitFeesPending
     * @return mixed
     */
    public function get3pVisitFeesPending() {
        return $this->get3rdPartyPending(ItemId::VisitFee);
    }

    /**
     * Summary of getDepositPending
     * @return float|int
     */
    public function getDepositPending() {
        return $this->getItemInvPending(ItemId::KeyDeposit)
                + $this->getItemInvPending(ItemId::DepositRefund);
    }

    /**
     * Summary of getDepositPayType
     * @return string
     */
    public function getDepositPayType() {
        return $this->depositPayType;
    }

    /**
     * Summary of getItemInvCharges
     * @param mixed $idItem
     * @return float|int
     */
    public function getItemInvCharges($idItem) {
        if (isset($this->itemSums[$idItem])) {
            return $this->itemSums[$idItem][InvoiceStatus::Unpaid] + $this->itemSums[$idItem][InvoiceStatus::Paid];
        }
        return 0;
    }

    /**
     * Summary of getItemInvPayments
     * @param mixed $idItem
     * @return mixed
     */
    public function getItemInvPayments($idItem) {
        if (isset($this->itemSums[$idItem])) {
            return $this->itemSums[$idItem][InvoiceStatus::Paid];
        }
        return 0;
    }

    /**
     * Summary of getItemInvPending
     * @param int $idItem
     * @return mixed
     */
    public function getItemInvPending($idItem) {
        if (isset($this->itemSums[$idItem])) {
            return $this->itemSums[$idItem][InvoiceStatus::Unpaid];
        }
        return 0;
    }

    /**
     * Summary of get3rdPartyPending
     * @param int $idItem
     * @return mixed
     */
    public function get3rdPartyPending($idItem) {
        if (isset($this->itemSums[$idItem])) {
            return $this->itemSums[$idItem][self::THIRD_PARTY];
        }
        return 0;
    }

    /**
     * Summary of getTaxInvoices
     * @param int $idItem
     * @return mixed
     */
    public function getTaxInvoices($idItem) {
        if (isset($this->itemSums[$idItem][self::TAX_PAID])) {
            return $this->itemSums[$idItem][self::TAX_PAID];
        }
        return 0;
    }

    /**
     * Summary of getTaxExempt
     * @param int $idItem
     * @return mixed
     */
    public function getTaxExempt($idItem) {
        if (isset($this->itemSums[$idItem]['tax_exempt'])) {
            return $this->itemSums[$idItem]['tax_exempt'];
        }
        return 0;
    }

    /**
     * Summary of getItemTaxItemAmount
     * @param int $idItem
     * @param int $idTaxItem
     * @return mixed
     */
    public function getItemTaxItemAmount($idItem, $idTaxItem) {

        if (isset($this->itemSums[$idItem][$idTaxItem])) {
            return $this->itemSums[$idItem][$idTaxItem];
        }
        return 0;
    }


    /**
     * Summary of getTaxItemIds
     * @return mixed
     */
    public function getTaxItemIds() {
        return $this->taxItemIds;
    }

    /**
     * Summary of getRoomFeesCharged
     * @return float|int
     */
    public function getRoomFeesCharged() {
        return $this->feesCharged;
    }

    /**
     * Summary of getDepositCharged
     * @return float|int
     */
    public function getDepositCharged() {
        return $this->DepositCharged;
    }

    /**
     * Summary of getVisitFeeCharged
     * @return float|int
     */
    public function getVisitFeeCharged() {
        return $this->visitFeeCharged;
    }

    /**
     * Summary of getNightsStayed
     * @return int
     */
    public function getNightsStayed() {
    	if ($this->nightsStayed < 0) {
    		return 0;
    	}
        return $this->nightsStayed;
    }

    /**
     * Summary of getGuestNightsStayed
     * @return int
     */
    public function getGuestNightsStayed() {
    	if ($this->guestNightsStayed < 0) {
    		return 0;
    	}
        return $this->guestNightsStayed;
    }

    /**
     * Summary of getNightsPaid
     * @return int
     */
    public function getNightsPaid() {
    	if ($this->nightsPaid < 0) {
    		return 0;
    	}
        return $this->nightsPaid;
    }

    /**
     * Summary of getNightsToPay
     * @return int
     */
    public function getNightsToPay() {
    	if ($this->nightsToPay < 0) {
    		return 0;
    	}
        return $this->nightsToPay;
    }

    /**
     * Summary of getExcessPaid
     * @return float|int
     */
    public function getExcessPaid() {
        return $this->excessPaid;
    }

    /**
     * Summary of getFeesToPay
     * @return float|int
     */
    public function getFeesToPay() {
        return $this->feesToCharge;
    }

    /**
     * Summary of getGlideCredit
     * @return int
     */
    public function getGlideCredit() {
        return $this->glideCredit;
    }

    /**
     * Summary of getRoomFeesPaid
     * @return float|int
     */
    public function getRoomFeesPaid() {
        return $this->getItemInvPayments(ItemId::Lodging)
                + $this->getItemInvPayments(ItemId::LodgingReversal);
    }

    /**
     * Summary of getVisitFeesPaid
     * @return float|int
     */
    public function getVisitFeesPaid() {
        return $this->getItemInvPayments(ItemId::VisitFee);
    }

    /**
     * Summary of getKeyFeesPaid
     * @return float|int
     */
    public function getKeyFeesPaid() {
        return $this->getItemInvPayments(ItemId::KeyDeposit)
                    + $this->getItemInvPayments(ItemId::DepositRefund);
    }

    /**
     * Summary of getIdVisit
     * @return int
     */
    public function getIdVisit() {
        return $this->idVisit;
    }
    /**
     * Summary of getSpan
     * @return int
     */
    public function getSpan() {
        return $this->span;
    }
    /**
     * Summary of getFinalVisitCoDate
     * @return \DateTime
     */
    public function getFinalVisitCoDate() {
    	return $this->finalVisitCoDate;
    }

}
