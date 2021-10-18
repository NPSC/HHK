<?php

namespace HHK\Payment\PaymentResult;

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
        try {
            $this->displayMessage .= $this->emailReceipt($dbh);
        } catch (\Exception $ex) {
            $this->displayMessage .= "Email Failed, Error = " . $ex->getMessage();
        }

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
        try {
            $this->displayMessage .= $this->emailReceipt($dbh);
        } catch (\Exception $ex) {
            $this->displayMessage .= "Email Failed, Error = " . $ex->getMessage();
        }

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

    public function emailReceipt(\PDO $dbh) {

        $uS = Session::getInstance();
        $toAddr = '';
        $guestName = '';
        $guestHasEmail = FALSE;

        $fromAddr = $uS->FromAddress;

        if ($fromAddr == '') {
            // Config data not set.

            return '';
        }


        $query = "SELECT ne.Email, n.Name_Full FROM
    `registration` r,
    `name` n
        LEFT JOIN
    `name_email` ne ON n.idName = ne.idName
        AND n.Preferred_Email = ne.Purpose
WHERE r.Email_Receipt = 1 and
    r.idregistration = :idreg AND n.idName = :id";

        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':idreg'=>$this->idRegistration, ':id'=>$this->idName));

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) > 0 && $rows[0]['Email'] != '') {
            $toAddr = $rows[0]['Email'];
            $guestName = ' to ' . $rows[0]['Name_Full'];
            $guestHasEmail = TRUE;
        }

        $toAddrSan = filter_var($toAddr, FILTER_SANITIZE_EMAIL);

        if ($toAddrSan === FALSE || $toAddrSan == '') {
            // Config data not set.

            return '';
        }


        try{
            $mail = prepareEmail();
    
            $mail->From = $fromAddr;
            $mail->addReplyTo($uS->ReplyTo);
            $mail->FromName = $uS->siteName;
    
            $mail->addAddress($toAddrSan);     // Add a recipient
    
            $bccEntry = $uS->BccAddress;
            $bccs = explode(',', $bccEntry);
    
            foreach ($bccs as $b) {
    
                $bcc = filter_var($b, FILTER_SANITIZE_EMAIL);
    
                if ($bcc !== FALSE && $bcc != '') {
                    $mail->addBCC($bcc);
                }
            }
    
            $mail->isHTML(true);
    
            $mail->Subject = $uS->siteName . ' Payment Receipt';
            $mail->msgHTML($this->receiptMarkup);
    
            $mail->send();
            if ($guestHasEmail) {
                return "Email sent" . $guestName;
            }
            
        }catch(\Exception $e){
            return "Send Email failed:  " . $mail->ErrorInfo;
        }

        return '';

    }

    public function wasError() {
        if ($this->getStatus() == self::ERROR) {
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

}

?>