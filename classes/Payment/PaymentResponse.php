<?php
/**
 * PaymentResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


abstract class PaymentResponse {

    protected $paymentType;
    protected $amountDue = 0.0;
    protected $invoiceNumber = '';
    protected $partialPaymentFlag = FALSE;
    protected $amount;
    protected $paymentDate;


    public $idPayor = 0;
    public $idVisit;
    public $idReservation;
    public $idRegistration;
    public $idTrans = 0;
    public $response;
    public $idGuestToken = 0;
    public $checkNumber;
    public $payNotes = '';


    /**
     *
     * @var PaymentRS
     */
    public $paymentRs;

    public abstract function getStatus();
    public abstract function receiptMarkup(\PDO $dbh, &$tbl);


    public function getPaymentType() {
        return $this->paymentType;
    }

    public function getAmountDue() {
        return $this->amountDue;
    }

    public function getAmount() {
        return $this->amount;
    }

    public function getPaymentDate() {
        return $this->paymentDate;
    }

    public function getInvoiceNumber() {
        return $this->invoiceNumber;
    }

    public function isPartialPayment() {
        return $this->partialPaymentFlag;
    }

    public function setPartialPayment($v) {
        if ($v) {
            $this->partialPaymentFlag = TRUE;
        } else {
            $this->partialPaymentFlag = FALSE;
        }
    }

    public function getIdToken() {
        return $this->idGuestToken;
    }

    public function setIdToken($idToken) {
        $this->idGuestToken = intval($idToken, 0);
    }

    public function getIdPayment() {

        if (is_null($this->paymentRs) === FALSE) {
            return $this->paymentRs->idPayment->getStoredVal();
        }

        return 0;
    }


    public function getIdTrans() {
        return $this->idTrans;
    }

    public function setIdTrans($idTrans) {
        $this->idTrans = $idTrans;
        return $this;
    }

}

