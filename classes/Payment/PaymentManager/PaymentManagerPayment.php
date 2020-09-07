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
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
  */

/**
 * Description of PaymentManagerPayment
 *
 * @author Eric
 */
  
class PaymentManagerPayment {
    
    protected $visitFeePayment;
    protected $keyDepositPayment;
    protected $depositRefundAmt;
    protected $ratePayment;
    protected $rateTax;
    protected $retainedAmtPayment;
    protected $houseDiscPayment;
    protected $totalPayment;
    protected $overPayment;
    protected $guestCredit;
    protected $refundAmount;
    protected $totalRoomChg;
    protected $payInvoicesAmt;
    protected $totalCharges;
    
    protected $payInvoices;
    protected $payType;
    protected $rtnPayType;
    protected $payNotes;
    protected $payDate;
    protected $idToken = 0;
    protected $rtnIdToken = 0;
    protected $idInvoicePayor;
    protected $checkNumber = '';
    protected $rtnCheckNumber = '';
    protected $transferAcct = '';
    protected $rtnTransferAcct = '';
    protected $chargeCard = '';
    protected $rtnChargeCard = '';
    protected $chargeAcct = '';
    protected $rtnChargeAcct = '';
    protected $finalPaymentFlag;
    protected $reimburseTaxCb;
    protected $newCardOnFile;
    protected $cashTendered;
    protected $newInvoice;
    protected $invoiceNotes;
    protected $balWith;
    protected $manualKeyEntry;
    protected $cardHolderName = '';
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
        
        
        $this->payInvoices = array();
        $this->payInvoicesAmt = 0;
        $this->idInvoicePayor = 0;
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
    
    
    public function getInvoicesToPay() {
        return $this->payInvoices;
    }
    
    public function addInvoiceByNumber(\PDO $dbh, $invoiceNumber, $paymentAmt) {
        
        if ($invoiceNumber != '' && $invoiceNumber != 0) {
            $invoice = new Invoice($dbh, $invoiceNumber);
            if (abs($paymentAmt) > abs($invoice->getBalance())) {
                $paymentAmt = $invoice->getBalance();
            }
            $this->addInvoice($invoice, $paymentAmt);
        }
    }
    
    protected function addInvoice(Invoice $invoice, $paymentAmt) {
        
        if ($invoice->getStatus() == InvoiceStatus::Unpaid)  {
            $this->payInvoices[] = $invoice;
            $this->payInvoicesAmt += $paymentAmt;
        }
    }
    
    public function getPayInvoicesAmt() {
        return $this->payInvoicesAmt;
    }
    
    public function getBalWith() {
        return $this->balWith;
    }
    
    public function setBalWith($balWith) {
        $this->balWith = $balWith;
        return $this;
    }
    
    public function getRetainedAmtPayment() {
        return $this->retainedAmtPayment;
    }
    
    public function setRetainedAmtPayment($retainedAmtPayment) {
        $this->retainedAmtPayment = abs($retainedAmtPayment);
        return $this;
    }
    
    public function getRefundAmount() {
        return $this->refundAmount;
    }
    
    public function setRefundAmount($refundAmount) {
        $this->refundAmount = $refundAmount;
        return $this;
    }
    
    public function getIdInvoicePayor() {
        return $this->idInvoicePayor;
    }
    
    public function setIdInvoicePayor($idInvoicePayor) {
        $this->idInvoicePayor = $idInvoicePayor;
        return $this;
    }
    
    public function setInvoiceNotes($invoiceNotes) {
        $this->invoiceNotes = $invoiceNotes;
        return $this;
    }
    
    public function getInvoiceNotes() {
        return $this->invoiceNotes;
    }
    
    public function getPriceModel() {
        return $this->priceModel;
    }
    
    public function setPriceModel(AbstractPriceModel $priceModel) {
        $this->priceModel = $priceModel;
        return $this;
    }
    
    public function getVisitCharges() {
        return $this->visitCharges;
    }
    
    public function setVisitCharges(VisitCharges $visitCharges) {
        $this->visitCharges = $visitCharges;
        return $this;
    }
    
    public function getTotalPayment() {
        return $this->totalPayment;
    }
    
    public function setTotalPayment($totalPayment) {
        $this->totalPayment = $totalPayment;
        return $this;
    }
    
    public function getTotalCharges() {
        return $this->totalCharges;
    }
    
    public function setTotalCharges($totalCharges) {
        $this->totalCharges = $totalCharges;
        return $this;
    }
    
    public function getNewInvoice() {
        return $this->newInvoice;
    }
    
    public function setNewInvoice($newInvoice) {
        $this->newInvoice = $newInvoice;
        return $this;
    }
    
    public function getOverPayment() {
        return $this->overPayment;
    }
    
    public function setOverPayment($overPayment) {
        $this->overPayment = $overPayment;
        return $this;
    }
    
    public function getGuestCredit() {
        return $this->guestCredit;
    }
    
    public function setGuestCredit($guestCredit) {
        $this->guestCredit = $guestCredit;
        return $this;
    }
    
    
    public function getNewCardOnFile() {
        return $this->newCardOnFile;
    }
    
    public function setNewCardOnFile($newCardOnFile) {
        $this->newCardOnFile = $newCardOnFile;
        return $this;
    }
    
    public function getMerchant() {
        return $this->merchant;
    }
    
    public function setMerchant($v) {
        $this->merchant = $v;
        return $this;
    }
    
    public function getCashTendered() {
        return $this->cashTendered;
    }
    
    public function setCashTendered($cashTendered) {
        $this->cashTendered = $cashTendered;
        return $this;
    }
    
    
    public function getChargeCard() {
        return $this->chargeCard;
    }
    
    public function getChargeAcct() {
        return $this->chargeAcct;
    }
    
    public function setChargeCard($chargeCard) {
        $this->chargeCard = trim($chargeCard);
        return $this;
    }
    
    public function setChargeAcct($v) {
        $chargeAcct = trim($v);
        
        if (strlen($chargeAcct) > 4) {
            $chargeAcct = substr($chargeAcct, strlen($chargeAcct) - 4, 4);
        }
        $this->chargeAcct = $chargeAcct;
        return $this;
    }
    
    
    public function getFinalPaymentFlag() {
        return $this->finalPaymentFlag;
    }
    
    public function setFinalPaymentFlag($finalPaymentFlag) {
        $this->finalPaymentFlag = $finalPaymentFlag;
        return $this;
    }
    
    public function getReimburseTaxCb() {
        return $this->reimburseTaxCb;
    }
    
    public function setReimburseTaxCb($reimburseTaxCb) {
        $this->reimburseTaxCb = $reimburseTaxCb;
        return $this;
    }
    
    public function getVisitFeePayment() {
        return $this->visitFeePayment;
    }
    
    public function getKeyDepositPayment() {
        return $this->keyDepositPayment;
    }
    
    public function getRatePayment() {
        return $this->ratePayment;
    }
    
    public function getRateTax() {
        return $this->rateTax;
    }
    
    public function getPayType() {
        return $this->payType;
    }
    
    public function getPayDate() {
        
        if ($this->payDate != '') {
            
            try {
            	$payDT = setTimeZone(NULL, $this->payDate);
                $paymentDate = $payDT->format('Y-m-d H:i:s');
                
                $now = new \DateTime();
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
    
    public function getIdToken() {
        return $this->idToken;
    }
    
    public function getCheckNumber() {
        return $this->checkNumber;
    }
    
    public function getRtnCheckNumber() {
        return $this->rtnCheckNumber;
    }
    
    public function getTransferAcct() {
        return $this->transferAcct;
    }
    
    public function setVisitFeePayment($visitFeePayment) {
        $this->visitFeePayment = $visitFeePayment;
        return $this;
    }
    
    public function getPayNotes() {
        return $this->payNotes;
    }
    
    public function setPayNotes($payNotes) {
        $this->payNotes = trim($payNotes);
        return $this;
    }
    
    
    public function setKeyDepositPayment($keyDepositPayment) {
        $this->keyDepositPayment = $keyDepositPayment;
        return $this;
    }
    
    public function setRatePayment($ratePayment) {
        $this->ratePayment = $ratePayment;
        return $this;
    }
    
    public function setRateTax($rateTax) {
        $this->rateTax = $rateTax;
        return $this;
    }
    
    public function setPayType($payType) {
        $this->payType = $payType;
        return $this;
    }
    
    public function getRtnPayType() {
        return $this->rtnPayType;
    }
    
    public function getRtnChargeCard() {
        return $this->rtnChargeCard;
    }
    
    public function getRtnChargeAcct() {
        return $this->rtnChargeAcct;
    }
    
    public function setRtnPayType($rtnPayType) {
        
        $this->rtnPayType = $rtnPayType;
    }
    
    public function setRtnChargeCard($rtnChargeCard) {
        $this->rtnChargeCard = $rtnChargeCard;
        return $this;
    }
    
    public function setRtnChargeAcct($rtnChargeAcct) {
        
        $chargeAcct = trim($rtnChargeAcct);
        
        if (strlen($chargeAcct) > 4) {
            $chargeAcct = substr($chargeAcct, strlen($chargeAcct) - 4, 4);
        }
        
        $this->rtnChargeAcct = $chargeAcct;
        return $this;
    }
    
    
    public function setPayDate($payDate) {
        $this->payDate = $payDate;
        return $this;
    }
    
    public function setIdToken($idToken) {
        $this->idToken = $idToken;
        return $this;
    }
    
    public function getRtnIdToken() {
        return $this->rtnIdToken;
    }
    
    public function setRtnIdToken($rtnIdToken) {
        $this->rtnIdToken = $rtnIdToken;
        return $this;
    }
    
    public function getRtnTransferAcct() {
        return $this->rtnTransferAcct;
    }
    
    public function setRtnTransferAcct($rtnTransferAcct) {
        $this->rtnTransferAcct = $rtnTransferAcct;
        return $this;
    }
    
    
    public function setCheckNumber($checkNumber) {
        $this->checkNumber = trim($checkNumber);
        return $this;
    }
    
    public function setRtnCheckNumber($checkNumber) {
        $this->rtnCheckNumber = trim($checkNumber);
        return $this;
    }
    
    public function setTransferAcct($transferAcct) {
        $this->transferAcct = trim($transferAcct);
        return $this;
    }
    
    public function getHouseDiscPayment() {
        return $this->houseDiscPayment;
    }
    
    public function setHouseDiscPayment($houseDiscPayment) {
        $this->houseDiscPayment = $houseDiscPayment;
        return $this;
    }
    
    public function getDepositRefundAmt() {
        return $this->depositRefundAmt;
    }
    
    public function setDepositRefundAmt($depositRefundAmt) {
        $this->depositRefundAmt = $depositRefundAmt;
        return $this;
    }
    
    public function getTotalRoomChg() {
        return $this->totalRoomChg;
    }
    
    public function setTotalRoomChg($totalRoomChg) {
        $this->totalRoomChg = $totalRoomChg;
        return $this;
    }
    
    function getManualKeyEntry() {
        return $this->manualKeyEntry;
    }
    
    function setManualKeyEntry($manualKeyEntry) {
        $this->manualKeyEntry = $manualKeyEntry;
        return $this;
    }
    
    public function getCardHolderName() {
        return $this->cardHolderName;
    }
    
    public function setCardHolderName($cardHolderName) {
        $this->cardHolderName = $cardHolderName;
        return $this;
    }
    
}
?>