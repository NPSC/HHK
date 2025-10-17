<?php

namespace HHK\Payment\PaymentResult;


use HHK\Payment\PaymentSvcs;
use HHK\Payment\Receipt;
use HHK\Payment\Invoice\Invoice;
use HHK\Payment\PaymentResponse\AbstractPaymentResponse;
use HHK\Tables\EditRS;
use HHK\Tables\Payment\PaymentInvoiceRS;
use HHK\sec\Session;

/**
 * PaymentResult.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class PaymentResult {

    protected $displayMessage = '';
    protected $errorMessage = '';
    protected $status = '';
    protected $idName = 0;
    protected $idRegistration = 0;
    protected $idToken;
    protected $idPayment = 0;

    protected $receiptMarkup = '';
    protected $invoiceMarkup = '';
    protected $forwardHostedPayment;
    protected $idInvoice = 0;
    protected $invoiceNumber = '';
    protected $replyMessage = '';

    const ACCEPTED = 'a';
    const DENIED = 'd';
    const ERROR = 'e';
    const FORWARDED = 'f';

    function __construct($idInvoice, $idRegistration, $idName, $idToken = 0) {

        $this->idRegistration = $idRegistration;
        $this->idInvoice = $idInvoice;
        $this->idName = $idName;
        $this->forwardHostedPayment = array();
        $this->idToken = $idToken;
    }

    public function feePaymentAccepted(\PDO $dbh, Session $uS, AbstractPaymentResponse $payResp, Invoice $invoice) {

        // set status
        $this->status = PaymentResult::ACCEPTED;
        $this->idPayment = $payResp->getIdPayment();

        // zero total invoices do not have payment records.
        if ($payResp->getIdPayment() > 0 && $this->idInvoice > 0) {
            $this->createPaymentInvoiceRcrd($dbh, $payResp->getIdPayment(), $this->idInvoice, $payResp->getAmount());
        }

        // Make out receipt
        $this->receiptMarkup = Receipt::createSaleMarkup($dbh, $invoice, $uS->siteName, $uS->sId, $payResp);

        // Email receipt
        $emailResults = $this->emailReceipt($dbh);
        $this->displayMessage .= isset($emailResults['success']) ? "  ". $emailResults['success'] : "";
        $this->errorMessage .= isset($emailResults['error']) ? "  " . $emailResults['error'] : "";

    }

    public function feePaymentRejected(\PDO $dbh, Session $uS, AbstractPaymentResponse $payResp, Invoice $invoice) {

        $this->status = PaymentResult::DENIED;
        $this->idPayment = $payResp->getIdPayment();

        if ($payResp->getIdPayment() > 0 && $this->idInvoice > 0) {
            // payment-invoice
            $this->createPaymentInvoiceRcrd($dbh, $payResp->getIdPayment(), $this->idInvoice, $payResp->getAmount());
        }

        // Make out receipt
        $this->receiptMarkup = Receipt::createDeclinedMarkup($dbh, $invoice, $uS->siteName, $uS->sId, $payResp);

        // Email receipt
        $emailResults = $this->emailReceipt($dbh);
        $this->displayMessage .= isset($emailResults['success']) ? "  ". $emailResults['success'] : "";
        $this->errorMessage .= isset($emailResults['error']) ? "  " . $emailResults['error'] : "";

    }

    public function feePaymentError(\PDO $dbh, Session $uS) {


    }

    public function feePaymentInvoiced(\PDO $dbh, Invoice $invoice) {

        //$this->invoiceMarkup = $invoice->createMarkup($dbh);
        $this->invoiceNumber = $invoice->getInvoiceNumber();

    }

    protected function createPaymentInvoiceRcrd(\PDO $dbh, $idPayment, $idInvoice, $amount) {

        // payment-invoice
        $payInvRs = new PaymentInvoiceRS();
        $payInvRs->Invoice_Id->setStoredVal($idInvoice);
        $payInvRs->Payment_Id->setStoredVal($idPayment);
        $invPayRows = EditRS::select($dbh, $payInvRs, array($payInvRs->Invoice_Id, $payInvRs->Payment_Id));

        if (count($invPayRows) == 0) {
            // Make a payment-invoice entry.
            $payInvRs->Amount->setNewVal($amount);
            $payInvRs->Invoice_Id->setNewVal($idInvoice);
            $payInvRs->Payment_Id->setNewVal($idPayment);
            EditRS::insert($dbh, $payInvRs);
        }
    }

    /**
     * Decide whether to automatically send the receipt via email and send accordingly
     * @param \PDO $dbh
     * @return array{error: string}|array{success: string}
     */
    public function emailReceipt(\PDO $dbh) {

        $autoEmailAr = $this->isAutoEmailEligible($dbh, $this->idRegistration, $this->idName);

        if ($autoEmailAr['autoEmail'] == true) {
            $toAddr = $autoEmailAr['email'];
            $invoice = new Invoice($dbh);
            $invoice->loadInvoice($dbh, 0, $this->idPayment);
            return PaymentSvcs::sendReceiptEmail($dbh, $this->receiptMarkup, $invoice, $toAddr);
        }
        return [];
    }

    /**
     * Determine if an automated receipt email should be sent
     * 
     * @param \PDO $dbh
     * @param mixed $idRegistration
     * @param mixed $idName
     * @return array{autoEmail: bool, email: string|array{autoEmail: bool, email: null}}
     */
    public static function isAutoEmailEligible(\PDO $dbh, $idRegistration, $idName){
        $uS = Session::getInstance();

        $query = "SELECT IFNULL(ne.Email, '') as 'Email', if(n.Name_Full = '', n.Company, n.Name_Full) as `Name_Full`, r.Email_Receipt, nv.Vol_Code  FROM
    `name` n
		left join
	`name_guest` ng on n.idName = ng.idName
		left join registration r on ng.idPsg = r.idPsg
        LEFT JOIN
    `name_email` ne ON n.idName = ne.idName
        AND n.Preferred_Email = ne.Purpose
        LEFT JOIN
    `name_volunteer2` nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'
WHERE 
    (r.idregistration = :idreg or r.idRegistration is null) AND n.idName = :id group by n.idName";

        if(!$uS->autoEmailReceipts){
            $query .= " and r.Email_Receipt in (1, null)";
        }

        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':idreg'=>$idRegistration, ':id'=>$idName));

        $billagentreceipt = $uS->autoEmailBillingAgentReceipt;
        $billingagentrece = $uS->autoEmailBillingAgentRece;
        $thirdpartyreceipts = $uS->autoEmail3rdPartyReceipts;
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) > 0 && $rows[0]['Email'] != '' && 
        (
            (
                $rows[0]["Vol_Code"] == "ba" 
                && $uS->autoEmail3rdPartyReceipts
            )
            || 
            (
                $rows[0]["Vol_Code"] !== "ba" 
                && (
                    $uS->autoEmailReceipts 
                    || $rows[0]["Email_Receipt"] == "1"
                    )
            )
        )
        ) {
            return ['autoEmail'=>true, 'email'=>$rows[0]['Email']];
        }else{
            return ['autoEmail'=>false, 'email'=>null];
        }

    }

    public function wasError() {
        if ($this->getStatus() == self::ERROR || $this->getStatus() == self::DENIED) {
            return TRUE;
        }
        return FALSE;
    }

    public function getReceiptMarkup() {
        return $this->receiptMarkup;
    }

    public function getDisplayMessage() {
        return $this->displayMessage;
    }

    public function getErrorMessage() {
        return $this->errorMessage;
    }

    public function getInvoiceMarkup() {
        return $this->invoiceMarkup;
    }

    public function getIdInvoice() {
        return $this->idInvoice;
    }

    public function getInvoiceNumber() {
        return $this->invoiceNumber;
    }

    public function getIdToken() {
        return $this->idToken;
    }

    public function getReplyMessage() {
        return $this->replyMessage;
    }

    public function setReplyMessage($replyMessage) {
        $this->replyMessage .= $replyMessage;
        return $this;
    }

    public function setDisplayMessage($displayMessage) {
        $this->displayMessage = $displayMessage . $this->displayMessage;
        return $this;
    }

    public function setErrorMessage($displayMessage) {
        $this->errorMessage = $displayMessage . $this->errorMessage;
        return $this;
    }

    /**
     * Summary of getForwardHostedPayment
     * @return array
     */
    public function getForwardHostedPayment() {
        return $this->forwardHostedPayment;
    }

    public function setForwardHostedPayment(array $fwdHostedPayment) {
        $this->setStatus(PaymentResult::FORWARDED);
        $this->forwardHostedPayment = $fwdHostedPayment;
        return $this;
    }

    public function getStatus() {
        return $this->status;
    }

    public function setStatus($s) {
        $this->status = $s;
        return $this;
    }

    public function getIdName() {
        return $this->idName;
    }

    public function getIdRegistration() {
        return $this->idRegistration;
    }

    public function getIdPayment() {
        return $this->idPayment;
    }

    public function getInvoiceBillToEmail(\PDO $dbh){
        try{
            $invoice = new Invoice($dbh);
            $invoice->loadInvoice($dbh, 0, $this->idPayment);
            return $invoice->getBillToEmail($dbh);
        }catch (\Exception $e){
            return '';
        }
    }

}