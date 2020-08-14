<?php

namespace HHK\Payment\PaymentResponse;

use HHK\Tables\EditRS;
use HHK\Tables\Payment\PaymentRS;

/**
 * AbstractPaymentResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


abstract class AbstractPaymentResponse {

    protected $amount;
    protected $invoiceNumber = '';
    protected $partialPaymentFlag = FALSE;
    protected $paymentDate;
    protected $payNotes = '';
    protected $refund = FALSE;
    protected $result = '';  // vendor specific
    protected $paymentStatusCode;

    public $idPayor = 0;
    public $idVisit;
    public $idReservation;
    public $idRegistration;
    public $idTrans = 0;
    public $idGuestToken = 0;
    protected $idPayment;

    public abstract function getStatus();

    public abstract function receiptMarkup(\PDO $dbh, &$tbl);

    // One of the PaymentMethods
    public abstract function getPaymentMethod();


    // Record a payment
    public function recordPayment(\PDO $dbh, $username, $attempts = 1) {

        $payRs = new PaymentRS();
        $payRs->Amount->setNewVal($this->getAmount());
        $payRs->Payment_Date->setNewVal($this->getPaymentDate());
        $payRs->idPayor->setNewVal($this->getIdPayor());
        $payRs->idTrans->setNewVal($this->getIdTrans());
        $payRs->idToken->setNewVal($this->getIdToken());
        $payRs->idPayment_Method->setNewVal($this->getPaymentMethod());
        $payRs->Result->setNewVal($this->getResult());
        $payRs->Attempt->setNewVal($attempts);
        $payRs->Status_Code->setNewVal($this->getPaymentStatusCode());
        $payRs->Created_By->setNewVal($username);
        $payRs->Notes->setNewVal($this->getPaymentNotes());
        $payRs->Is_Refund->setNewVal($this->isRefund());

        $this->setIdPayment(EditRS::insert($dbh, $payRs));
        
    }

    // One of the PaymentStatusCodes
    public function getPaymentStatusCode() {
        return $this->paymentStatusCode;
    }

    public function setPaymentStatusCode($v) {
        $this->paymentStatusCode = $v;
    }

    public function getAmount() {
        return $this->amount;
    }

    public function getResult() {
        return $this->result;
    }

    public function setResult($v) {
        $this->result = $v;
    }

    public function getPaymentDate() {
    	if ($this->paymentDate == '') {
    		return date('Y-m-d H:i:s');
    	}
        return $this->paymentDate;
    }

    public function setPaymentDate($d) {
        $this->paymentDate = $d;
    }

    public function getPaymentNotes() {
        return $this->payNotes;
    }

    public function setPaymentNotes($v) {
        $this->payNotes = $v;
    }

    public function getIdPayor() {
        return $this->idPayor;
    }

    public function getInvoiceNumber() {
        return $this->invoiceNumber;
    }

    public function isRefund() {
        if ($this->refund) {
            return 1;
        } else {
            return 0;
        }
    }

    public function setRefund($v) {
        if ($v) {
            $this->refund = TRUE;
        } else {
            $this->refund = FALSE;
        }
    }

    public function getIdPayment() {
        return $this->idPayment;
    }

    public function setIdPayment($v) {
        $this->idPayment = intval($v, 10);
    }

    public function getIdToken() {
        return $this->idGuestToken;
    }

    public function setIdToken($idToken) {
        $this->idGuestToken = intval($idToken, 10);
    }

    public function getIdTrans() {
        return $this->idTrans;
    }

    public function setIdTrans($idTrans) {
        $this->idTrans = $idTrans;
        return $this;
    }
}
?>