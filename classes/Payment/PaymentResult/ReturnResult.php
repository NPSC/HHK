<?php

namespace HHK\Payment\PaymentResult;

use HHK\Payment\Receipt;
use HHK\Payment\Invoice\Invoice;
use HHK\Payment\PaymentResponse\AbstractPaymentResponse;
use HHK\sec\Session;

/**
 * PaymentResult.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ReturnResult extends PaymentResult {
    
    public function feePaymentAccepted(\PDO $dbh, Session $uS, AbstractPaymentResponse $rtnResp, Invoice $invoice) {
        
        // set status
        $this->status = PaymentResult::ACCEPTED;
        
        // zero total invoices do not have payment records.
        if ($rtnResp->getIdPayment() > 0 && $this->idInvoice > 0) {
            $this->createPaymentInvoiceRcrd($dbh, $rtnResp->getIdPayment(), $this->idInvoice, $rtnResp->getAmount());
        }
        
        
        // Make out receipt
        $this->receiptMarkup = Receipt::createReturnMarkup($dbh, $rtnResp, $uS->siteName, $uS->sId);
        
        // Email receipt
        try {
            $this->displayMessage .= $this->emailReceipt($dbh);
        } catch (\Exception $ex) {
            $this->displayMessage .= "Email Failed, Error = " . $ex->getMessage();
        }
    }
    
    public function getReplyMessage() {
        return $this->replyMessage . $this->displayMessage;
    }
    
}
?>