<?php

namespace HHK\Purchase;

use HHK\House\Visit\Visit;
use HHK\Payment\Receipt;
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
    protected $span;
    protected $finalVisitCoDate;

    /**
     *
     * @var array
     */
    private $itemSums;

    private $taxItemIds;

    public function __construct($idVisit, $span = 0) {
        $this->idVisit = $idVisit;
        $this->span = $span;
    }

    public function sumCurrentRoomCharge(\PDO $dbh, AbstractPriceModel $priceModel, $newPayment = 0, $calcDaysPaid = FALSE, $givenPaid = NULL) {
        return $this->getVisitData($priceModel->loadVisitNights($dbh, $this->idVisit), $priceModel, $newPayment, $calcDaysPaid, $givenPaid);
    }

    public function sumDatedRoomCharge(\PDO $dbh, AbstractPriceModel $priceModel, $coDate, $newPayment = 0, $calcDaysPaid = FALSE, $givenPaid = NULL) {

        // Get spans with current nights calculated.
        $spans = $priceModel->loadVisitNights($dbh, $this->idVisit);

        // Access the last span
        $span = $spans[(count($spans) - 1)];

        $arrDT = new \DateTime($span['Span_Start']);
        $arrDT->setTime(0, 0, 0);

        $depDT = new \DateTime($coDate);

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
        $rates = Receipt::processRatesRooms($spans);

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
        if ($visitFeeCharge > 0 && ($this->getNightsStayed() > $uS->VisitFeeDelayDays || $uS->VisitFeeDelayDays == ''
                || $this->getVisitFeesPaid() + $this->getVisitFeesPending() > 0)) {
            $this->visitFeeCharged = $visitFeeCharge;
        }

        return $this;

    }


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

    public function getRoomFeesPending() {
        return $this->getItemInvPending(ItemId::Lodging) + $this->getItemInvPending(ItemId::LodgingReversal);
    }

    public function get3pRoomFeesPending() {
        return $this->get3rdPartyPending(ItemId::Lodging) + $this->get3rdPartyPending(ItemId::LodgingReversal);
    }

    public function getTaxExemptRoomFees() {
        return $this->getTaxExempt(ItemId::Lodging) + $this->getTaxExempt(ItemId::LodgingReversal);
    }

    public function getVisitFeesPending() {
        return $this->getItemInvPending(ItemId::VisitFee);
    }

    public function get3pVisitFeesPending() {
        return $this->get3rdPartyPending(ItemId::VisitFee);
    }

    public function getDepositPending() {
        return $this->getItemInvPending(ItemId::KeyDeposit)
                + $this->getItemInvPending(ItemId::DepositRefund);
    }

    public function getDepositPayType() {
        return $this->depositPayType;
    }

    public function getItemInvCharges($idItem) {
        if (isset($this->itemSums[$idItem])) {
            return $this->itemSums[$idItem][InvoiceStatus::Unpaid] + $this->itemSums[$idItem][InvoiceStatus::Paid];
        }
        return 0;
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

    public function get3rdPartyPending($idItem) {
        if (isset($this->itemSums[$idItem])) {
            return $this->itemSums[$idItem][self::THIRD_PARTY];
        }
        return 0;
    }

    public function getTaxInvoices($idItem) {
        if (isset($this->itemSums[$idItem][self::TAX_PAID])) {
            return $this->itemSums[$idItem][self::TAX_PAID];
        }
        return 0;
    }

    public function getTaxExempt($idItem) {
        if (isset($this->itemSums[$idItem]['tax_exempt'])) {
            return $this->itemSums[$idItem]['tax_exempt'];
        }
        return 0;
    }

    public function getItemTaxItemAmount($idItem, $idTaxItem) {

        if (isset($this->itemSums[$idItem][$idTaxItem])) {
            return $this->itemSums[$idItem][$idTaxItem];
        }
        return 0;
    }


    public function getTaxItemIds() {
        return $this->taxItemIds;
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
    	if ($this->nightsStayed < 0) {
    		return 0;
    	}
        return $this->nightsStayed;
    }

    public function getGuestNightsStayed() {
    	if ($this->guestNightsStayed < 0) {
    		return 0;
    	}
        return $this->guestNightsStayed;
    }

    public function getNightsPaid() {
    	if ($this->nightsPaid < 0) {
    		return 0;
    	}
        return $this->nightsPaid;
    }

    public function getNightsToPay() {
    	if ($this->nightsToPay < 0) {
    		return 0;
    	}
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
    public function getSpan() {
        return $this->span;
    }
    public function getFinalVisitCoDate() {
    	return $this->finalVisitCoDate;
    }

}

?>