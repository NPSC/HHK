<?php

namespace HHK\Payment\PaymentResponse;

use HHK\Tables\EditRS;
use HHK\Tables\Payment\PaymentRS;


/**
 * AbstractPaymentResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


abstract class AbstractPaymentResponse {

    /**
     * Summary of amount
     * @var float
     */
    protected $amount;
    /**
     * Summary of invoiceNumber
     * @var string
     */
    protected $invoiceNumber = '';
    /**
     * Summary of partialPaymentFlag
     * @var bool
     */
    protected $partialPaymentFlag = FALSE;
    /**
     * Summary of paymentDate
     * @var string
     */
    protected $paymentDate;
    /**
     * Summary of payNotes
     * @var string
     */
    protected $payNotes = '';
    /**
     * Summary of refund
     * @var bool
     */
    protected $refund = FALSE;
    /**
     * Summary of result
     * @var string
     */
    protected $result = '';  // vendor specific
    /**
     * Summary of paymentStatusCode
     * @var string
     */
    protected $paymentStatusCode;
    /**
     * Summary of paymentType
     * @var string
     */
    protected $paymentType;

    /**
     * Summary of idPayor
     * @var int
     */
    public $idPayor = 0;
    /**
     * Summary of idVisit
     * @var int
     */
    public $idVisit;
    /**
     * Summary of idReservation
     * @var int
     */
    public $idReservation;
    /**
     * Summary of idRegistration
     * @var int
     */
    public $idRegistration;
    /**
     * Summary of idTrans
     * @var int
     */
    public $idTrans = 0;
    /**
     * Summary of idGuestToken
     * @var int
     */
    public $idGuestToken = 0;
    /**
     * Summary of idPayment
     * @var int
     */
    protected $idPayment;
    /**
     * Summary of paymentRs
     * @var PaymentRS
     */
    public $paymentRs;

    /**
     * Summary of getStatus
     * @return string
     */
    public abstract function getStatus();

    /**
     * Summary of receiptMarkup
     * @param \PDO $dbh
     * @param mixed $tbl
     */
    public abstract function receiptMarkup(\PDO $dbh, &$tbl);

    // One of the PaymentMethods
    /**
     * Summary of getPaymentMethod
     * @return mixed
     */
    public abstract function getPaymentMethod();


    // Record a payment
    /**
     * Summary of recordPayment
     * @param \PDO $dbh
     * @param string $username
     * @param int $attempts
     * @return void
     */
    public function recordPayment(\PDO $dbh, $username, $attempts = 1, $parentPaymentId = 0) {

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
        $payRs->parent_idPayment->setNewVal($parentPaymentId);

        $this->setIdPayment(EditRS::insert($dbh, $payRs));

    }

    // One of the PaymentStatusCodes
    /**
     * Summary of getPaymentStatusCode
     * @return mixed
     */
    public function getPaymentStatusCode() {
        return $this->paymentStatusCode;
    }

    /**
     * Summary of setPaymentStatusCode
     * @param mixed $v
     * @return void
     */
    public function setPaymentStatusCode($v) {
        $this->paymentStatusCode = $v;
    }

    /**
     * Summary of getAmount
     * @return float
     */
    public function getAmount() {
        return $this->amount;
    }

    /**
     * Summary of getResult
     * @return mixed
     */
    public function getResult() {
        return $this->result;
    }

    /**
     * Summary of setResult
     * @param mixed $v
     * @return void
     */
    public function setResult($v) {
        $this->result = $v;
    }

    /**
     * Summary of getPaymentDate
     * @return mixed|string
     */
    public function getPaymentDate() {
    	if ($this->paymentDate == '') {
    		return date('Y-m-d H:i:s');
    	}
        return $this->paymentDate;
    }

    /**
     * Summary of setPaymentDate
     * @param mixed $d
     * @return void
     */
    public function setPaymentDate($d) {
        $this->paymentDate = $d;
    }

    /**
     * Summary of getPaymentNotes
     * @return mixed
     */
    public function getPaymentNotes() {
        return $this->payNotes;
    }

    /**
     * Summary of setPaymentNotes
     * @param mixed $v
     * @return void
     */
    public function setPaymentNotes($v) {
        $this->payNotes = $v;
    }

    /**
     * Summary of getIdPayor
     * @return int
     */
    public function getIdPayor() {
        return $this->idPayor;
    }

    /**
     * Summary of getInvoiceNumber
     * @return string
     */
    public function getInvoiceNumber() {
        return $this->invoiceNumber;
    }

    /**
     * Summary of isRefund
     * @return int
     */
    public function isRefund() {
        if ($this->refund) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * Summary of setRefund
     * @param mixed $v
     * @return void
     */
    public function setRefund($v) {
        if ($v) {
            $this->refund = TRUE;
        } else {
            $this->refund = FALSE;
        }
    }

    /**
     * Summary of getIdPayment
     * @return int
     */
    public function getIdPayment() {
        return $this->idPayment;
    }

    /**
     * Summary of setIdPayment
     * @param int $v
     * @return void
     */
    public function setIdPayment($v) {
        $this->idPayment = intval($v, 10);
    }

    /**
     * Summary of getIdToken
     * @return int
     */
    public function getIdToken() {
        return $this->idGuestToken;
    }

    /**
     * Summary of setIdToken
     * @param int $idToken
     * @return void
     */
    public function setIdToken($idToken) {
        $this->idGuestToken = intval($idToken, 10);
    }

    /**
     * Summary of getIdTrans
     * @return int
     */
    public function getIdTrans() {
        return $this->idTrans;
    }

    /**
     * Summary of setIdTrans
     * @param int $idTrans
     * @return AbstractPaymentResponse
     */
    public function setIdTrans($idTrans) {
        $this->idTrans = $idTrans;
        return $this;
    }
}
