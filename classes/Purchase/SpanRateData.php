<?php

namespace HHK\Purchase;

use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\SysConst\VisitStatus;

/**
 * SpanRateData.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of SpanRateData
 *
 * @author Eric
 */

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


    public function spanRate($r, $paid, $calculateNightsPaid, AbstractPriceModel $priceModel) {

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

        // any expected future days?
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
?>