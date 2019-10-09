<?php

/*
 * The MIT License
 *
 * Copyright 2019 Eric.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */


class CurrentAccount {

    protected $numberNitesStayed = 0;
    protected $addnlGuestNites = 0;
    protected $visitGlideCredit = 0;
    protected $visitStatus;
    protected $showRoomFees;
    protected $showGuestNites;
    protected $showVisitFee;

    // Charges.
    protected $lodgingTaxPd = array();
    protected $additionalChargeTax = 0;
    protected $reimburseTax;

    protected $roomCharge = 0;
    protected $totalDiscounts = 0;
    protected $visitFeeCharged = 0;
    protected $additionalCharge = 0;
    protected $unpaidMOA = 0;
    protected $curentTaxItems = array();

    // Visit Fee Balance
    protected $vfeeBal = 0;

    // Room fee balance
    protected $roomFeeBalance = 0;

    // Payments
    protected $totalPaid = 0;

    // Pending amounts
    protected $amtPending = 0;
    protected $dueToday = 0;

    public function __construct($visitStatus, $showVisitFee = FALSE, $showRoomFees = FALSE, $showGuestNights = FALSE) {


        $this->visitStatus = $visitStatus === FALSE ? FALSE : TRUE;
        $this->showRoomFees = $showRoomFees === FALSE ? FALSE : TRUE;
        $this->showGuestNites = $showGuestNights === FALSE ? FALSE : TRUE;
        $this->showVisitFee = $showVisitFee === FALSE ? FALSE : TRUE;

        $this->reimburseTax = array();
    }

    public function load(VisitCharges $visitCharge, ValueAddedTax $vat) {

        $this->numberNitesStayed = $visitCharge->getNightsStayed();

        $this->setAddnlGuestNites($visitCharge->getGuestNightsStayed() - $visitCharge->getNightsStayed());
        $this->setVisitGlideCredit($visitCharge->getGlideCredit());

        // Charges.
        $this->setRoomCharge($visitCharge->getRoomFeesCharged());
        $this->setTotalDiscounts($visitCharge->getItemInvCharges(ItemId::Discount) + $visitCharge->getItemInvCharges(ItemId::Waive));
        $this->setVisitFeeCharged($visitCharge->getVisitFeeCharged());
        $this->setAdditionalCharge($visitCharge->getItemInvCharges(ItemId::AddnlCharge));
        $this->setUnpaidMOA($visitCharge->getItemInvPending(ItemId::LodgingMOA));

        // Reimburse vat?
        foreach($vat->getTimedoutTaxItems(ItemId::Lodging, $visitCharge->getIdVisit(), $visitCharge->getNightsStayed()) as $t) {
            $this->sumReimburseTax($t->getIdTaxingItem(), $visitCharge->getItemInvCharges($t->getIdTaxingItem()));
        }

        // Taxex
        $this->curentTaxItems = $vat->getCurrentTaxedItems($visitCharge->getIdVisit(), $visitCharge->getNightsStayed());

        $this->setAdditionalChargeTax($visitCharge->getTaxInvoices(ItemId::AddnlCharge));


        // Visit Fee Balance
        if ($this->showVisitFee) {
            $this->setVfeeBal($this->getVisitFeeCharged() - $visitCharge->getVisitFeesPaid() - $visitCharge->getVisitFeesPending());
        } else {
            $this->setVisitFeeCharged(0);
        }

        // Room fee balance
        $this->setRoomFeeBalance(($this->getRoomCharge() + $visitCharge->getItemInvCharges(ItemId::Discount)) - $visitCharge->getRoomFeesPaid() - $visitCharge->getRoomFeesPending());

        // Lodging tax already paid
        foreach ($visitCharge->getTaxItemIds() as $tid =>$v) {
            $this->setLodgingTaxPd($tid, $visitCharge->getItemInvPayments($tid));
        }

        // Payments
        $this->setTotalPaid($visitCharge->getRoomFeesPaid()
                + $visitCharge->getVisitFeesPaid()
                + $visitCharge->getItemInvPayments(ItemId::AddnlCharge)
                + $visitCharge->getItemInvPayments(ItemId::Waive)
                + $visitCharge->getItemInvPayments('tax'));

        // Pending amounts
        $this->setAmtPending($visitCharge->get3pRoomFeesPending()
                + $visitCharge->get3pVisitFeesPending()
                + $visitCharge->get3rdPartyPending(ItemId::AddnlCharge)
                + $visitCharge->get3rdPartyPending(ItemId::LodgingMOA)
                + $visitCharge->get3rdPartyPending(ItemId::Waive)
                + $visitCharge->get3rdPartyPending('tax'));

    }

    public function getAddnlGuestNites() {
        return $this->addnlGuestNites;
    }

    public function getVisitGlideCredit() {
        return $this->visitGlideCredit;
    }

    public function getAdditionalChargeTax() {
        return round($this->additionalChargeTax, 2);
    }

    public function getRoomCharge() {
        return $this->roomCharge;
    }

    public function getTotalDiscounts() {
        return $this->totalDiscounts;
    }

    public function getVisitFeeCharged() {
        return $this->visitFeeCharged;
    }

    public function getAdditionalCharge() {
        return $this->additionalCharge;
    }

    public function getUnpaidMOA() {
        return $this->unpaidMOA;
    }

    public function getTotalCharged() {

        return $this->getRoomCharge() + $this->getItemTaxAmt(ItemId::Lodging, $this->getRoomFeeBalance())
                + $this->getAdditionalCharge() + $this->getAdditionalChargeTax()
                + $this->getUnpaidMOA()
                + $this->getTotalDiscounts()
                + $this->getVisitFeeCharged();
    }

    public function getVfeeBal() {
        return $this->vfeeBal;
    }

    public function getTotalPaid() {
        return $this->totalPaid;
    }

    public function getAmtPending() {
        return $this->amtPending;
    }

    public function getNumberNitesStayed() {
        return $this->numberNitesStayed;
    }

    public function getVisitStatus() {
        return $this->visitStatus;
    }

    public function getShowRoomFees() {
        return $this->showRoomFees;
    }

    public function getShowVisitFee() {
        return $this->showVisitFee;
    }

    public function getShowGuestNites() {
        return $this->showGuestNites;
    }

    public function getRoomFeeBalance() {
        return $this->roomFeeBalance;
    }

    public function getDueToday() {
        return $this->dueToday;
    }

    public function getReimburseTax() {
        return $this->reimburseTax;
    }

    public function getLodgingTaxPd($tid) {
        if (isset($this->lodgingTaxPd[$tid])) {
            return $this->lodgingTaxPd[$tid];
        }
        return 0;
    }

    /**
     *
     * @param int $idTaxedItem
     * @return array
     */
    public function getCurentTaxItems($idTaxedItem) {
        $current = array();

        foreach ($this->curentTaxItems as $t) {

            if ($t->getIdTaxedItem() == $idTaxedItem) {
                $current[] = $t;
            }
        }

        return $current;

    }

    public function getItemTaxAmt($idTaxedItem, $balanceAmt) {

        $amt = 0;

        foreach ($this->getCurentTaxItems($idTaxedItem) as $t) {

            if ($t->getIdTaxedItem() == $idTaxedItem) {
                $amt += $t->getTaxAmount($balanceAmt) + $this->getLodgingTaxPd($t->getIdTaxingItem());
            }
        }

        return $amt;
    }

    public function setReimburseTax($taxingId, $reimburseTax) {
        $this->reimburseTax[$taxingId] = $reimburseTax;
        return $this;
    }

    public function sumReimburseTax($taxingId, $reimburseTax) {
        if (isset($this->reimburseTax[$taxingId])) {
            $this->reimburseTax[$taxingId] += $reimburseTax;
        } else {
            $this->reimburseTax[$taxingId] = $reimburseTax;
        }
        return $this;
    }

    public function setLodgingTaxPd($tid, $amt) {
        $this->lodgingTaxPd[$tid] = $amt;
    }

    public function setDueToday() {

        $this->dueToday = round($this->getTotalCharged() - $this->getTotalPaid() - $this->getAmtPending(), 2);

    }

    public function setRoomFeeBalance($roomFeeBalance) {
        $this->roomFeeBalance = $roomFeeBalance;
        return $this;
    }

    public function setAddnlGuestNites($addnlGuestNites) {
        $this->addnlGuestNites = $addnlGuestNites;
        return $this;
    }

    public function setVisitGlideCredit($visitGlideCredit) {
        $this->visitGlideCredit = $visitGlideCredit;
        return $this;
    }

    public function setAdditionalChargeTax($additionalChargeTax) {
        $this->additionalChargeTax = $additionalChargeTax;
        return $this;
    }

    public function setRoomCharge($roomCharge) {
        $this->roomCharge = $roomCharge;
        return $this;
    }

    public function setTotalDiscounts($totalDiscounts) {
        $this->totalDiscounts = $totalDiscounts;
        return $this;
    }

    public function setVisitFeeCharged($visitFeeCharged) {
        $this->visitFeeCharged = $visitFeeCharged;
        return $this;
    }

    public function setAdditionalCharge($additionalCharge) {
        $this->additionalCharge = $additionalCharge;
        return $this;
    }

    public function setUnpaidMOA($unpaidMOA) {
        $this->unpaidMOA = $unpaidMOA;
        return $this;
    }


    public function setVfeeBal($vfeeBal) {
        $this->vfeeBal = $vfeeBal;
        return $this;
    }

    public function setTotalPaid($totalPaid) {
        $this->totalPaid = $totalPaid;
        return $this;
    }

    public function setAmtPending($amtPending) {
        $this->amtPending = $amtPending;
        return $this;
    }


}