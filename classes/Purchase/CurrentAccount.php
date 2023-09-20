<?php

namespace HHK\Purchase;

use HHK\SysConst\ItemId;


/**
 * Summary of CurrentAccount
 */
class CurrentAccount {

    /**
     * Summary of numberNitesStayed
     * @var int
     */
    protected $numberNitesStayed = 0;
    /**
     * Summary of addnlGuestNites
     * @var int
     */
    protected $addnlGuestNites = 0;
    /**
     * Summary of visitGlideCredit
     * @var int
     */
    protected $visitGlideCredit = 0;
    /**
     * Summary of visitStatus
     * @var string
     */
    protected $visitStatus;
    /**
     * Summary of showRoomFees
     * @var bool
     */
    protected $showRoomFees;
    /**
     * Summary of showGuestNites
     * @var bool
     */
    protected $showGuestNites;
    /**
     * Summary of showVisitFee
     * @var bool
     */
    protected $showVisitFee;

    // Charges.
    /**
     * Summary of lodgingTaxPd
     * @var array
     */
    protected $lodgingTaxPd = array();
    /**
     * Summary of additionalChargeTax
     * @var float
     */
    protected $additionalChargeTax = 0.0;
    /**
     * Summary of reimburseTax
     * @var array
     */
    protected $reimburseTax;

    /**
     * Summary of roomCharge
     * @var float
     */
    protected $roomCharge = 0;
    /**
     * Summary of totalDiscounts
     * @var float
     */
    protected $totalDiscounts = 0;
    /**
     * Summary of visitFeeCharged
     * @var float
     */
    protected $visitFeeCharged = 0;
    /**
     * Summary of additionalCharge
     * @var float
     */
    protected $additionalCharge = 0;
    /**
     * Summary of unpaidMOA
     * @var float
     */
    protected $unpaidMOA = 0;
    /**
     * Summary of curentTaxItems
     * @var array
     */
    protected $curentTaxItems = array();
    /**
     * Summary of taxExemptRoomFees
     * @var float
     */
    protected $taxExemptRoomFees = 0;
    /**
     * Summary of roomFeesToCharge
     * @var float
     */
    protected $roomFeesToCharge = 0;

    // Visit Fee Balance
    /**
     * Summary of vfeeBal
     * @var float
     */
    protected $vfeeBal = 0;

    // Room fee balance
    /**
     * Summary of roomFeeBalance
     * @var float
     */
    protected $roomFeeBalance = 0;
    /**
     * Summary of taxedroomFeeBalance
     * @var float
     */
    protected $taxedroomFeeBalance = 0;

    // Payments
    /**
     * Summary of totalPaid
     * @var float
     */
    protected $totalPaid = 0;

    // Pending amounts
    /**
     * Summary of amtPending
     * @var float
     */
    protected $amtPending = 0;
    /**
     * Summary of dueToday
     * @var float
     */
    protected $dueToday = 0;

    /**
     * Summary of __construct
     * @param string $visitStatus
     * @param bool $showVisitFee
     * @param bool $showRoomFees
     * @param bool $showGuestNights
     */
    public function __construct($visitStatus, $showVisitFee = FALSE, $showRoomFees = FALSE, $showGuestNights = FALSE) {


        $this->visitStatus = $visitStatus;
        $this->showRoomFees = $showRoomFees === FALSE ? FALSE : TRUE;
        $this->showGuestNites = $showGuestNights === FALSE ? FALSE : TRUE;
        $this->showVisitFee = $showVisitFee === FALSE ? FALSE : TRUE;

        $this->reimburseTax = array();
    }

    /**
     * Summary of load
     * @param \HHK\Purchase\VisitCharges $visitCharge
     * @param \HHK\Purchase\ValueAddedTax $vat
     * @return void
     */
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

        $this->setRoomFeesToCharge($visitCharge->getFeesToPay());

        // Reimburse vat?
        foreach($vat->getTimedoutTaxItems(ItemId::Lodging, $visitCharge->getIdVisit(), $visitCharge->getNightsStayed()) as $t) {
            $this->sumReimburseTax($t->getIdTaxingItem(), $visitCharge->getItemInvCharges($t->getIdTaxingItem()));
        }

        // Taxes
        $this->curentTaxItems = $vat->getCurrentTaxedItems($visitCharge->getIdVisit(), $visitCharge->getNightsStayed());

        $this->setAdditionalChargeTax($visitCharge->getTaxInvoices(ItemId::AddnlCharge));

        $this->taxExemptRoomFees = $visitCharge->getTaxExemptRoomFees();

        // Visit Fee Balance
        if ($this->showVisitFee) {
            $this->setVfeeBal($this->getVisitFeeCharged() - $visitCharge->getVisitFeesPaid() - $visitCharge->getVisitFeesPending());
        } else {
            $this->setVisitFeeCharged(0);
        }

        // Room fee balance
        $fees = $this->getRoomCharge() + $visitCharge->getItemInvCharges(ItemId::Discount);
        $taxedFees = $fees - $this->taxExemptRoomFees;

        $pending = $visitCharge->getRoomFeesPaid() + $visitCharge->getRoomFeesPending();
        //$pending +=  + $visitCharge->getRoomFeesPending();

        $taxedFeesPending = $pending - $this->taxExemptRoomFees;

        $this->setRoomFeeBalance($fees - $pending);
        //$this->setRoomFeeBalance($fees);

        // taxed Room fee balance
        //taxed charges - taxed charges paid
        if($taxedFeesPending > $taxedFees) {
            if($taxedFees < 0){
                $this->taxedroomFeeBalance = $this->getRoomFeeBalance() - ($fees - $this->taxExemptRoomFees);
            }else{
                $this->taxedroomFeeBalance = $this->getRoomFeeBalance();
            }
        }

        // Lodging tax already paid
        foreach ($visitCharge->getTaxItemIds() as $tid =>$v) {
                $this->setLodgingTaxPd($tid, $visitCharge->getItemTaxItemAmount(ItemId::Lodging, $tid));
                $this->setLodgingTaxPd($tid, $visitCharge->getItemTaxItemAmount(ItemId::LodgingReversal, $tid));
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

    /**
     * Summary of getAddnlGuestNites
     * @return int|mixed
     */
    public function getAddnlGuestNites() {
        return $this->addnlGuestNites;
    }

    /**
     * Summary of getVisitGlideCredit
     * @return int|mixed
     */
    public function getVisitGlideCredit() {
        return $this->visitGlideCredit;
    }

    /**
     * Summary of getAdditionalChargeTax
     * @return float
     */
    public function getAdditionalChargeTax() {
        return round($this->additionalChargeTax, 2);
    }

    /**
     * Summary of getRoomCharge
     * @return int|mixed
     */
    public function getRoomCharge() {
        return $this->roomCharge;
    }

    /**
     * Summary of getTotalDiscounts
     * @return int|mixed
     */
    public function getTotalDiscounts() {
        return $this->totalDiscounts;
    }

    /**
     * Summary of getVisitFeeCharged
     * @return int|mixed
     */
    public function getVisitFeeCharged() {
        return $this->visitFeeCharged;
    }

    /**
     * Summary of getAdditionalCharge
     * @return int|mixed
     */
    public function getAdditionalCharge() {
        return $this->additionalCharge;
    }

    /**
     * Summary of getUnpaidMOA
     * @return int|mixed
     */
    public function getUnpaidMOA() {
        return $this->unpaidMOA;
    }

    /**
     * Summary of getRoomFeesToCharge
     * @return int|mixed
     */
    public function getRoomFeesToCharge() {
        return $this->roomFeesToCharge;
    }



    /**
     * Summary of getTotalCharged
     * @return float
     */
    public function getTotalCharged() {

        return $this->getRoomCharge() + $this->getItemTaxAmt(ItemId::Lodging, $this->getRoomFeeBalance())
                + $this->getAdditionalCharge() + $this->getAdditionalChargeTax()
                + $this->getUnpaidMOA()
                + $this->getTotalDiscounts()
                + $this->getVisitFeeCharged();
    }

    /**
     * Summary of getVfeeBal
     * @return int|mixed
     */
    public function getVfeeBal() {
        return $this->vfeeBal;
    }

    /**
     * Summary of getTotalPaid
     * @return int|mixed
     */
    public function getTotalPaid() {
        return $this->totalPaid;
    }

    /**
     * Summary of getAmtPending
     * @return int|mixed
     */
    public function getAmtPending() {
        return $this->amtPending;
    }

    /**
     * Summary of getNumberNitesStayed
     * @return int
     */
    public function getNumberNitesStayed() {
        return $this->numberNitesStayed;
    }

    /**
     * Summary of getVisitStatus
     * @return string
     */
    public function getVisitStatus() {
        return $this->visitStatus;
    }

    /**
     * Summary of getShowRoomFees
     * @return bool
     */
    public function getShowRoomFees() {
        return $this->showRoomFees;
    }

    /**
     * Summary of getTaxExemptRoomFees
     * @return int
     */
    public function getTaxExemptRoomFees() {
        return $this->taxExemptRoomFees;
    }

    /**
     * Summary of getShowVisitFee
     * @return bool
     */
    public function getShowVisitFee() {
        return $this->showVisitFee;
    }

    /**
     * Summary of getShowGuestNites
     * @return bool
     */
    public function getShowGuestNites() {
        return $this->showGuestNites;
    }

    /**
     * Summary of getRoomFeeBalance
     * @return int|mixed
     */
    public function getRoomFeeBalance() {
        return $this->roomFeeBalance;
    }

    /**
     * Summary of getTaxedRoomFeeBalance
     * @return int
     */
    public function getTaxedRoomFeeBalance(){
        return $this->taxedroomFeeBalance;
    }

    /**
     * Summary of getDueToday
     * @return float|int
     */
    public function getDueToday() {
        return $this->dueToday;
    }

    /**
     * Summary of getReimburseTax
     * @return array
     */
    public function getReimburseTax() {
        return $this->reimburseTax;
    }

    /**
     * Summary of getLodgingTaxPd
     * @param mixed $tid
     * @return mixed
     */
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

    /**
     * Summary of getItemTaxAmt
     * @param mixed $idTaxedItem
     * @param mixed $balanceAmt
     * @return float|int
     */
    public function getItemTaxAmt($idTaxedItem, $balanceAmt) {

        $amt = 0;

        foreach ($this->getCurentTaxItems($idTaxedItem) as $t) {

            if ($this->getRoomFeeBalance() < 0) {
                $amt += $t->getTaxAmount(($this->getRoomCharge() - $this->taxExemptRoomFees + $this->getTotalDiscounts() > 0 ? $this->getRoomCharge() - $this->taxExemptRoomFees + $this->getTotalDiscounts():0));
            } else {
                $amt += $this->getLodgingTaxPd($t->getIdTaxingItem()) + $t->getTaxAmount($balanceAmt);
            }

        }

        return $amt;
    }

    /**
     * Summary of setReimburseTax
     * @param mixed $taxingId
     * @param mixed $reimburseTax
     * @return static
     */
    public function setReimburseTax($taxingId, $reimburseTax) {
        $this->reimburseTax[$taxingId] = $reimburseTax;
        return $this;
    }

    /**
     * Summary of sumReimburseTax
     * @param mixed $taxingId
     * @param mixed $reimburseTax
     * @return static
     */
    public function sumReimburseTax($taxingId, $reimburseTax) {
        if (isset($this->reimburseTax[$taxingId])) {
            $this->reimburseTax[$taxingId] += $reimburseTax;
        } else {
            $this->reimburseTax[$taxingId] = $reimburseTax;
        }
        return $this;
    }

    /**
     * Summary of setLodgingTaxPd
     * @param mixed $tid
     * @param mixed $amt
     * @return void
     */
    public function setLodgingTaxPd($tid, $amt) {
        if (isset($this->lodgingTaxPd[$tid])) {
            $this->lodgingTaxPd[$tid] += $amt;
        } else {
            $this->lodgingTaxPd[$tid] = $amt;
        }
    }

    /**
     * Summary of setDueToday
     * @return void
     */
    public function setDueToday() {

        $this->dueToday = round($this->getTotalCharged() - $this->getTotalPaid() - $this->getAmtPending(), 2);

    }

    /**
     * Summary of setRoomFeeBalance
     * @param mixed $roomFeeBalance
     * @return static
     */
    public function setRoomFeeBalance($roomFeeBalance) {
        $this->roomFeeBalance = $roomFeeBalance;
        return $this;
    }

    /**
     * Summary of setAddnlGuestNites
     * @param mixed $addnlGuestNites
     * @return static
     */
    public function setAddnlGuestNites($addnlGuestNites) {
        $this->addnlGuestNites = $addnlGuestNites;
        return $this;
    }

    /**
     * Summary of setRoomFeesToCharge
     * @param mixed $toCharge
     * @return static
     */
    public function setRoomFeesToCharge($toCharge) {
        $this->roomFeesToCharge = $toCharge;
        return $this;
    }

    /**
     * Summary of setVisitGlideCredit
     * @param mixed $visitGlideCredit
     * @return static
     */
    public function setVisitGlideCredit($visitGlideCredit) {
        $this->visitGlideCredit = $visitGlideCredit;
        return $this;
    }

    /**
     * Summary of setAdditionalChargeTax
     * @param mixed $additionalChargeTax
     * @return static
     */
    public function setAdditionalChargeTax($additionalChargeTax) {
        $this->additionalChargeTax = $additionalChargeTax;
        return $this;
    }

    /**
     * Summary of setRoomCharge
     * @param mixed $roomCharge
     * @return static
     */
    public function setRoomCharge($roomCharge) {
        $this->roomCharge = $roomCharge;
        return $this;
    }

    /**
     * Summary of setTotalDiscounts
     * @param mixed $totalDiscounts
     * @return static
     */
    public function setTotalDiscounts($totalDiscounts) {
        $this->totalDiscounts = $totalDiscounts;
        return $this;
    }

    /**
     * Summary of setVisitFeeCharged
     * @param mixed $visitFeeCharged
     * @return static
     */
    public function setVisitFeeCharged($visitFeeCharged) {
        $this->visitFeeCharged = $visitFeeCharged;
        return $this;
    }

    /**
     * Summary of setAdditionalCharge
     * @param mixed $additionalCharge
     * @return static
     */
    public function setAdditionalCharge($additionalCharge) {
        $this->additionalCharge = $additionalCharge;
        return $this;
    }

    /**
     * Summary of setUnpaidMOA
     * @param mixed $unpaidMOA
     * @return static
     */
    public function setUnpaidMOA($unpaidMOA) {
        $this->unpaidMOA = $unpaidMOA;
        return $this;
    }


    /**
     * Summary of setVfeeBal
     * @param mixed $vfeeBal
     * @return static
     */
    public function setVfeeBal($vfeeBal) {
        $this->vfeeBal = $vfeeBal;
        return $this;
    }

    /**
     * Summary of setTotalPaid
     * @param mixed $totalPaid
     * @return static
     */
    public function setTotalPaid($totalPaid) {
        $this->totalPaid = $totalPaid;
        return $this;
    }

    /**
     * Summary of setAmtPending
     * @param mixed $amtPending
     * @return static
     */
    public function setAmtPending($amtPending) {
        $this->amtPending = $amtPending;
        return $this;
    }


}