<?php

namespace HHK\Payment\PaymentManager;

use HHK\Purchase\VisitCharges;
use HHK\Payment\Invoice\Invoice;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\SysConst\InvoiceStatus;

/**
 * PaymentManagerPayment.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
  */

/**
 * Description of PaymentManagerPayment
 *
 * @author Eric
 */

class PaymentManagerPayment {

    /**
     * Summary of visitFeePayment
     * @var float|int
     */
    protected $visitFeePayment;

    /**
     * Summary of keyDepositPayment
     * @var float|int
     */
    protected $keyDepositPayment;
    /**
     * Summary of depositRefundAmt
     * @var float|int
     */
    protected $depositRefundAmt;
    /**
     * Summary of ratePayment
     * @var float|int
     */
    protected $ratePayment;
    /**
     * Summary of rateTax
     * @var float|int
     */
    protected $rateTax;
    /**
     * Summary of retainedAmtPayment
     * @var float|int
     */
    protected $retainedAmtPayment;
    /**
     * Summary of houseDiscPayment
     * @var float|int
     */
    protected $houseDiscPayment;

    /**
     * Summary of extraPayment
     * @var float|int
     */
    protected $extraPayment;
    /**
     * Summary of totalPayment
     * @var float|int
     */
    protected $totalPayment;
    /**
     * Summary of overPayment
     * @var float|int
     */
    protected $overPayment;
    /**
     * Summary of guestCredit
     * @var float|int
     */
    protected $guestCredit;
    /**
     * Summary of refundAmount
     * @var float|int
     */
    protected $refundAmount;
    /**
     * Summary of totalRoomChg
     * @var float|int
     */
    protected $totalRoomChg;
    /**
     * Summary of payInvoicesAmt
     * @var float|int
     */
    protected $payInvoicesAmt;
    /**
     * Summary of totalCharges
     * @var float|int
     */
    protected $totalCharges;

    /**
     * Summary of payInvoices
     * @var
     */
    protected $payInvoices;
    /**
     * Summary of payType
     * @var string
     */
    protected $payType;
    /**
     * Summary of rtnPayType
     * @var string
     */
    protected $rtnPayType;
    /**
     * Summary of payNotes
     * @var string
     */
    protected $payNotes;
    /**
     * Summary of payDate
     * @var string
     */
    protected $payDate;
    /**
     * Summary of idToken
     * @var int
     */
    protected $idToken = 0;
    /**
     * Summary of rtnIdToken
     * @var int
     */
    protected $rtnIdToken = 0;
    /**
     * Summary of idInvoicePayor
     * @var int
     */
    protected $idInvoicePayor;
    /**
     * Summary of invoicePayorTaxExempt
     * @var
     */
    protected $invoicePayorTaxExempt;
    /**
     * Summary of checkNumber
     * @var string
     */
    protected $checkNumber = '';
    /**
     * Summary of rtnCheckNumber
     * @var string
     */
    protected $rtnCheckNumber = '';
    /**
     * Summary of transferAcct
     * @var string
     */
    protected $transferAcct = '';
    /**
     * Summary of rtnTransferAcct
     * @var string
     */
    protected $rtnTransferAcct = '';
    /**
     * Summary of chargeCard
     * @var string
     */
    protected $chargeCard = '';
    /**
     * Summary of rtnChargeCard
     * @var string
     */
    protected $rtnChargeCard = '';
    /**
     * Summary of chargeAcct
     * @var string
     */
    protected $chargeAcct = '';
    /**
     * Summary of rtnChargeAcct
     * @var string
     */
    protected $rtnChargeAcct = '';
    /**
     * Summary of finalPaymentFlag
     * @var bool
     */
    protected $finalPaymentFlag;
    /**
     * Summary of reimburseTaxCb
     * @var mixed
     */
    protected $reimburseTaxCb;
    /**
     * Summary of newCardOnFile
     * @var mixed
     */
    protected $newCardOnFile;
    /**
     * Summary of cashTendered
     * @var float|int
     */
    protected $cashTendered;
    /**
     * Summary of newInvoice
     * @var float|int
     */
    protected $newInvoice;
    /**
     * Summary of invoiceNotes
     * @var string
     */
    protected $invoiceNotes;
    /**
     * Summary of balWith
     * @var mixed
     */
    protected $balWith;
    /**
     * Summary of manualKeyEntry
     * @var mixed
     */
    protected $manualKeyEntry;
    /**
     * Summary of cardHolderName
     * @var string
     */
    protected $cardHolderName = '';
    /**
     * Summary of merchant
     * @var string
     */
    protected $merchant = '';
    /**
     *
     * @var AbstractPriceModel
     */
    public $priceModel;

    /**
     *
     * @var VisitCharges
     */
    public $visitCharges;

    /**
     * Summary of __construct
     * @param mixed $payType
     */
    public function __construct($payType) {
        $this->visitFeePayment = 0;
        $this->keyDepositPayment = 0;
        $this->ratePayment = 0;
        $this->rateTax = 0;
        $this->totalPayment = 0;
        $this->totalCharges = 0;
        $this->cashTendered = 0;
        $this->retainedAmtPayment = 0;
        $this->depositRefundAmt = 0;
        $this->totalRoomChg = 0;
        $this->refundAmount = 0;
        $this->extraPayment = 0;

        $this->payInvoices = array();
        $this->payInvoicesAmt = 0;
        $this->idInvoicePayor = 0;
        $this->invoicePayorTaxExempt = false;
        $this->setPayType($payType);
        $this->newCardOnFile = FALSE;
        $this->finalPaymentFlag = FALSE;
        $this->reimburseTaxCb = FALSE;
        $this->manualKeyEntry = FALSE;
        $this->reimburseTaxCb = FALSE;
        $this->invoiceNotes = '';
        $this->balWith = '';
        $this->payDate = '';
        $this->payNotes = '';

    }


    /**
     * Summary of getInvoicesToPay
     * @return array<Invoice>
     */
    public function getInvoicesToPay() {
        return $this->payInvoices;
    }

    /**
     * Summary of addInvoiceByNumber
     * @param \PDO $dbh
     * @param mixed $invoiceNumber
     * @param mixed $paymentAmt
     * @return void
     */
    public function addInvoiceByNumber(\PDO $dbh, $invoiceNumber, $paymentAmt) {

        if ($invoiceNumber != '' && $invoiceNumber != 0) {
            $invoice = new Invoice($dbh, $invoiceNumber);
            if (abs($paymentAmt) > abs($invoice->getBalance())) {
                $paymentAmt = $invoice->getBalance();
            }
            $this->addInvoice($invoice, $paymentAmt);
        }
    }

    /**
     * Summary of addInvoice
     * @param \HHK\Payment\Invoice\Invoice $invoice
     * @param float|int $paymentAmt
     * @return void
     */
    protected function addInvoice(Invoice $invoice, $paymentAmt) {

        if ($invoice->getStatus() == InvoiceStatus::Unpaid)  {
            $this->payInvoices[] = $invoice;
            $this->payInvoicesAmt += $paymentAmt;
        }
    }

    /**
     * Summary of getPayInvoicesAmt
     * @return float|int
     */
    public function getPayInvoicesAmt() {
        return $this->payInvoicesAmt;
    }

    /**
     * Summary of getBalWith
     * @return mixed|string
     */
    public function getBalWith() {
        return $this->balWith;
    }

    /**
     * Summary of setBalWith
     * @param mixed $balWith
     * @return PaymentManagerPayment
     */
    public function setBalWith($balWith) {
        $this->balWith = $balWith;
        return $this;
    }

    /**
     * Summary of getRetainedAmtPayment
     * @return float|int
     */
    public function getRetainedAmtPayment() {
        return $this->retainedAmtPayment;
    }

    /**
     * Summary of setRetainedAmtPayment
     * @param float|int $retainedAmtPayment
     * @return PaymentManagerPayment
     */
    public function setRetainedAmtPayment($retainedAmtPayment) {
        $this->retainedAmtPayment = abs($retainedAmtPayment);
        return $this;
    }

    /**
     * Summary of getRefundAmount
     * @return int|mixed
     */
    public function getRefundAmount() {
        return $this->refundAmount;
    }

    /**
     * Summary of setRefundAmount
     * @param mixed $refundAmount
     * @return PaymentManagerPayment
     */
    public function setRefundAmount($refundAmount) {
        $this->refundAmount = $refundAmount;
        return $this;
    }

    /**
     * Summary of getIdInvoicePayor
     * @return int|mixed
     */
    public function getIdInvoicePayor() {
        return $this->idInvoicePayor;
    }

    /**
     * Summary of setIdInvoicePayor
     * @param mixed $idInvoicePayor
     * @return PaymentManagerPayment
     */
    public function setIdInvoicePayor($idInvoicePayor) {
        $this->idInvoicePayor = $idInvoicePayor;
        return $this;
    }

    /**
     * Summary of getInvoicePayorTaxExempt
     * @return bool|mixed
     */
    public function getInvoicePayorTaxExempt() {
        return $this->invoicePayorTaxExempt;
    }

    /**
     * Summary of setInvoicePayorTaxExempt
     * @param mixed $invoicePayorTaxExempt
     * @return PaymentManagerPayment
     */
    public function setInvoicePayorTaxExempt($invoicePayorTaxExempt) {
        $this->invoicePayorTaxExempt = $invoicePayorTaxExempt;
        return $this;
    }

    /**
     * Summary of setInvoiceNotes
     * @param mixed $invoiceNotes
     * @return PaymentManagerPayment
     */
    public function setInvoiceNotes($invoiceNotes) {
        $this->invoiceNotes = $invoiceNotes;
        return $this;
    }

    /**
     * Summary of getInvoiceNotes
     * @return mixed|string
     */
    public function getInvoiceNotes() {
        return $this->invoiceNotes;
    }

    /**
     * Summary of getPriceModel
     * @return AbstractPriceModel
     */
    public function getPriceModel() {
        return $this->priceModel;
    }

    /**
     * Summary of setPriceModel
     * @param \HHK\Purchase\PriceModel\AbstractPriceModel $priceModel
     * @return PaymentManagerPayment
     */
    public function setPriceModel(AbstractPriceModel $priceModel) {
        $this->priceModel = $priceModel;
        return $this;
    }

    /**
     * Summary of getVisitCharges
     * @return VisitCharges
     */
    public function getVisitCharges() {
        return $this->visitCharges;
    }

    /**
     * Summary of setVisitCharges
     * @param \HHK\Purchase\VisitCharges $visitCharges
     * @return PaymentManagerPayment
     */
    public function setVisitCharges(VisitCharges $visitCharges) {
        $this->visitCharges = $visitCharges;
        return $this;
    }

    /**
     * Summary of getTotalPayment
     * @return int|mixed
     */
    public function getTotalPayment() {
        return $this->totalPayment;
    }

    /**
     * Summary of setTotalPayment
     * @param mixed $totalPayment
     * @return PaymentManagerPayment
     */
    public function setTotalPayment($totalPayment) {
        $this->totalPayment = $totalPayment;
        return $this;
    }

    /**
     * Summary of getTotalCharges
     * @return int|mixed
     */
    public function getTotalCharges() {
        return $this->totalCharges;
    }

    /**
     * Summary of setTotalCharges
     * @param float|int $totalCharges
     * @return PaymentManagerPayment
     */
    public function setTotalCharges($totalCharges) {
        $this->totalCharges = $totalCharges;
        return $this;
    }

    /**
     * Summary of getNewInvoice
     * @return mixed
     */
    public function getNewInvoice() {
        return $this->newInvoice;
    }

    /**
     * Summary of setNewInvoice
     * @param mixed $newInvoice
     * @return PaymentManagerPayment
     */
    public function setNewInvoice($newInvoice) {
        $this->newInvoice = $newInvoice;
        return $this;
    }

    /**
     * Summary of getOverPayment
     * @return mixed
     */
    public function getOverPayment() {
        return $this->overPayment;
    }

    /**
     * Summary of setOverPayment
     * @param float|int $overPayment
     * @return PaymentManagerPayment
     */
    public function setOverPayment($overPayment) {
        $this->overPayment = $overPayment;
        return $this;
    }

    /**
     * Summary of getGuestCredit
     * @return mixed
     */
    public function getGuestCredit() {
        return $this->guestCredit;
    }

    /**
     * Summary of setGuestCredit
     * @param mixed $guestCredit
     * @return PaymentManagerPayment
     */
    public function setGuestCredit($guestCredit) {
        $this->guestCredit = $guestCredit;
        return $this;
    }


    /**
     * Summary of getNewCardOnFile
     * @return bool|mixed
     */
    public function getNewCardOnFile() {
        return $this->newCardOnFile;
    }

    /**
     * Summary of setNewCardOnFile
     * @param mixed $newCardOnFile
     * @return PaymentManagerPayment
     */
    public function setNewCardOnFile($newCardOnFile) {
        $this->newCardOnFile = $newCardOnFile;
        return $this;
    }

    /**
     * Summary of getMerchant
     * @return mixed|string
     */
    public function getMerchant() {
        return $this->merchant;
    }

    /**
     * Summary of setMerchant
     * @param mixed $v
     * @return PaymentManagerPayment
     */
    public function setMerchant($v) {
        $this->merchant = $v;
        return $this;
    }

    /**
     * Summary of getCashTendered
     * @return int|mixed
     */
    public function getCashTendered() {
        return $this->cashTendered;
    }

    /**
     * Summary of setCashTendered
     * @param mixed $cashTendered
     * @return PaymentManagerPayment
     */
    public function setCashTendered($cashTendered) {
        $this->cashTendered = $cashTendered;
        return $this;
    }


    /**
     * Summary of getChargeCard
     * @return string
     */
    public function getChargeCard() {
        return $this->chargeCard;
    }

    /**
     * Summary of getChargeAcct
     * @return string
     */
    public function getChargeAcct() {
        return $this->chargeAcct;
    }

    /**
     * Summary of setChargeCard
     * @param mixed $chargeCard
     * @return PaymentManagerPayment
     */
    public function setChargeCard($chargeCard) {
        $this->chargeCard = trim($chargeCard);
        return $this;
    }

    /**
     * Summary of setChargeAcct
     * @param mixed $v
     * @return PaymentManagerPayment
     */
    public function setChargeAcct($v) {
        $chargeAcct = trim($v);

        if (strlen($chargeAcct) > 4) {
            $chargeAcct = substr($chargeAcct, strlen($chargeAcct) - 4, 4);
        }
        $this->chargeAcct = $chargeAcct;
        return $this;
    }


    /**
     * Summary of getFinalPaymentFlag
     * @return bool|mixed
     */
    public function getFinalPaymentFlag() {
        return $this->finalPaymentFlag;
    }

    /**
     * Summary of setFinalPaymentFlag
     * @param mixed $finalPaymentFlag
     * @return PaymentManagerPayment
     */
    public function setFinalPaymentFlag($finalPaymentFlag) {
        $this->finalPaymentFlag = $finalPaymentFlag;
        return $this;
    }

    /**
     * Summary of getReimburseTaxCb
     * @return bool|mixed
     */
    public function getReimburseTaxCb() {
        return $this->reimburseTaxCb;
    }

    /**
     * Summary of setReimburseTaxCb
     * @param mixed $reimburseTaxCb
     * @return PaymentManagerPayment
     */
    public function setReimburseTaxCb($reimburseTaxCb) {
        $this->reimburseTaxCb = $reimburseTaxCb;
        return $this;
    }

    /**
     * Summary of getVisitFeePayment
     * @return int|mixed
     */
    public function getVisitFeePayment() {
        return $this->visitFeePayment;
    }

    /**
     * Summary of getKeyDepositPayment
     * @return int|mixed
     */
    public function getKeyDepositPayment() {
        return $this->keyDepositPayment;
    }

    /**
     * Summary of getRatePayment
     * @return int|mixed
     */
    public function getRatePayment() {
        return $this->ratePayment;
    }

    /**
     * Summary of getRateTax
     * @return int|mixed
     */
    public function getRateTax() {
        return $this->rateTax;
    }

    /**
     * Summary of getPayType
     * @return mixed
     */
    public function getPayType() {
        return $this->payType;
    }

    /**
     * Summary of getPayDate
     * @return string
     */
    public function getPayDate() {

        if ($this->payDate != '') {

            try {
            	$payDT = new \DateTime($this->payDate);  //setTimeZone(NULL, $this->payDate);

            	$now = new \DateTime();
            	$payDT->setTime($now->format('H'), $now->format('i'));

                $paymentDate = $payDT->format('Y-m-d H:i:s');

                $now->setTime(0, 0, 0);
                $payDT->setTime(0, 0, 0);

                if ($payDT >= $now) {
                    $paymentDate = date('Y-m-d H:i:s');
                }

            } catch (\Exception $ex) {
                $paymentDate = date('Y-m-d H:i:s');
            }

        } else {

            $paymentDate = date('Y-m-d H:i:s');
        }

        return $paymentDate;
    }

    /**
     * Summary of getIdToken
     * @return int|mixed
     */
    public function getIdToken() {
        return $this->idToken;
    }

    /**
     * Summary of getCheckNumber
     * @return string
     */
    public function getCheckNumber() {
        return $this->checkNumber;
    }

    /**
     * Summary of getRtnCheckNumber
     * @return string
     */
    public function getRtnCheckNumber() {
        return $this->rtnCheckNumber;
    }

    /**
     * Summary of getTransferAcct
     * @return string
     */
    public function getTransferAcct() {
        return $this->transferAcct;
    }

    /**
     * Summary of setVisitFeePayment
     * @param mixed $visitFeePayment
     * @return PaymentManagerPayment
     */
    public function setVisitFeePayment($visitFeePayment) {
        $this->visitFeePayment = $visitFeePayment;
        return $this;
    }

    /**
     * Summary of getPayNotes
     * @return string
     */
    public function getPayNotes() {
        return $this->payNotes;
    }

    /**
     * Summary of setPayNotes
     * @param mixed $payNotes
     * @return PaymentManagerPayment
     */
    public function setPayNotes($payNotes) {
        $this->payNotes = trim($payNotes);
        return $this;
    }


    /**
     * Summary of setKeyDepositPayment
     * @param mixed $keyDepositPayment
     * @return PaymentManagerPayment
     */
    public function setKeyDepositPayment($keyDepositPayment) {
        $this->keyDepositPayment = $keyDepositPayment;
        return $this;
    }

    /**
     * Summary of setRatePayment
     * @param mixed $ratePayment
     * @return PaymentManagerPayment
     */
    public function setRatePayment($ratePayment) {
        $this->ratePayment = $ratePayment;
        return $this;
    }

    /**
     * Summary of setRateTax
     * @param mixed $rateTax
     * @return PaymentManagerPayment
     */
    public function setRateTax($rateTax) {
        $this->rateTax = $rateTax;
        return $this;
    }

    /**
     * Summary of setPayType
     * @param mixed $payType
     * @return PaymentManagerPayment
     */
    public function setPayType($payType) {
        $this->payType = $payType;
        return $this;
    }

    /**
     * Summary of getRtnPayType
     * @return mixed
     */
    public function getRtnPayType() {
        return $this->rtnPayType;
    }

    /**
     * Summary of getRtnChargeCard
     * @return mixed|string
     */
    public function getRtnChargeCard() {
        return $this->rtnChargeCard;
    }

    /**
     * Summary of getRtnChargeAcct
     * @return string
     */
    public function getRtnChargeAcct() {
        return $this->rtnChargeAcct;
    }

    /**
     * Summary of setRtnPayType
     * @param mixed $rtnPayType
     * @return void
     */
    public function setRtnPayType($rtnPayType) {

        $this->rtnPayType = $rtnPayType;
    }

    /**
     * Summary of setRtnChargeCard
     * @param mixed $rtnChargeCard
     * @return PaymentManagerPayment
     */
    public function setRtnChargeCard($rtnChargeCard) {
        $this->rtnChargeCard = $rtnChargeCard;
        return $this;
    }

    /**
     * Summary of setRtnChargeAcct
     * @param mixed $rtnChargeAcct
     * @return PaymentManagerPayment
     */
    public function setRtnChargeAcct($rtnChargeAcct) {

        $chargeAcct = trim($rtnChargeAcct);

        if (strlen($chargeAcct) > 4) {
            $chargeAcct = substr($chargeAcct, strlen($chargeAcct) - 4, 4);
        }

        $this->rtnChargeAcct = $chargeAcct;
        return $this;
    }


    /**
     * Summary of setPayDate
     * @param mixed $payDate
     * @return PaymentManagerPayment
     */
    public function setPayDate($payDate) {
        $this->payDate = $payDate;
        return $this;
    }

    /**
     * Summary of setIdToken
     * @param mixed $idToken
     * @return PaymentManagerPayment
     */
    public function setIdToken($idToken) {
        $this->idToken = $idToken;
        return $this;
    }

    /**
     * Summary of getRtnIdToken
     * @return int|mixed
     */
    public function getRtnIdToken() {
        return $this->rtnIdToken;
    }

    /**
     * Summary of setRtnIdToken
     * @param mixed $rtnIdToken
     * @return PaymentManagerPayment
     */
    public function setRtnIdToken($rtnIdToken) {
        $this->rtnIdToken = $rtnIdToken;
        return $this;
    }

    /**
     * Summary of getRtnTransferAcct
     * @return mixed|string
     */
    public function getRtnTransferAcct() {
        return $this->rtnTransferAcct;
    }

    /**
     * Summary of setRtnTransferAcct
     * @param mixed $rtnTransferAcct
     * @return PaymentManagerPayment
     */
    public function setRtnTransferAcct($rtnTransferAcct) {
        $this->rtnTransferAcct = $rtnTransferAcct;
        return $this;
    }


    /**
     * Summary of setCheckNumber
     * @param mixed $checkNumber
     * @return PaymentManagerPayment
     */
    public function setCheckNumber($checkNumber) {
        $this->checkNumber = trim($checkNumber);
        return $this;
    }

    /**
     * Summary of setRtnCheckNumber
     * @param mixed $checkNumber
     * @return PaymentManagerPayment
     */
    public function setRtnCheckNumber($checkNumber) {
        $this->rtnCheckNumber = trim($checkNumber);
        return $this;
    }

    /**
     * Summary of setTransferAcct
     * @param mixed $transferAcct
     * @return PaymentManagerPayment
     */
    public function setTransferAcct($transferAcct) {
        $this->transferAcct = trim($transferAcct);
        return $this;
    }

    /**
     * Summary of getHouseDiscPayment
     * @return mixed
     */
    public function getHouseDiscPayment() {
        return $this->houseDiscPayment;
    }

    /**
     * Summary of setHouseDiscPayment
     * @param mixed $houseDiscPayment
     * @return PaymentManagerPayment
     */
    public function setHouseDiscPayment($houseDiscPayment) {
        $this->houseDiscPayment = $houseDiscPayment;
        return $this;
    }

    /**
     * Summary of getDepositRefundAmt
     * @return int|mixed
     */
    public function getDepositRefundAmt() {
        return $this->depositRefundAmt;
    }

    /**
     * Summary of setDepositRefundAmt
     * @param mixed $depositRefundAmt
     * @return PaymentManagerPayment
     */
    public function setDepositRefundAmt($depositRefundAmt) {
        $this->depositRefundAmt = $depositRefundAmt;
        return $this;
    }

    /**
     * Summary of getTotalRoomChg
     * @return int|mixed
     */
    public function getTotalRoomChg() {
        return $this->totalRoomChg;
    }

    /**
     * Summary of setTotalRoomChg
     * @param mixed $totalRoomChg
     * @return PaymentManagerPayment
     */
    public function setTotalRoomChg($totalRoomChg) {
        $this->totalRoomChg = $totalRoomChg;
        return $this;
    }

    /**
     * Summary of getManualKeyEntry
     * @return bool|mixed
     */
    function getManualKeyEntry() {
        return $this->manualKeyEntry;
    }

    /**
     * Summary of setManualKeyEntry
     * @param mixed $manualKeyEntry
     * @return PaymentManagerPayment
     */
    function setManualKeyEntry($manualKeyEntry) {
        $this->manualKeyEntry = $manualKeyEntry;
        return $this;
    }

    /**
     * Summary of getCardHolderName
     * @return mixed|string
     */
    public function getCardHolderName() {
        return $this->cardHolderName;
    }

    /**
     * Summary of setCardHolderName
     * @param mixed $cardHolderName
     * @return PaymentManagerPayment
     */
    public function setCardHolderName($cardHolderName) {
        $this->cardHolderName = $cardHolderName;
        return $this;
    }


	/**
	 * Summary of extraPayment
	 * @return float|int
	 */
	public function getExtraPayment() {
		return $this->extraPayment;
	}

	/**
	 * Summary of extraPayment
	 * @param float|int $extraPayment Summary of extraPayment
	 * @return self
	 */
	public function setExtraPayment($extraPayment): self {
		$this->extraPayment = $extraPayment;
		return $this;
	}
}
?>