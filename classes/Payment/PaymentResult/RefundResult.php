<?php

namespace HHK\Payment\PaymentResult;

use HHK\Payment\Receipt;
use HHK\Payment\Invoice\Invoice;
use HHK\Payment\PaymentResponse\AbstractPaymentResponse;
use HHK\sec\Session;

/**
 * RefundResult.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class RefundResult extends PaymentResult
{

    /**
     * Summary of feePaymentAccepted
     * @param \PDO $dbh
     * @param \HHK\sec\Session $uS
     * @param \HHK\Payment\PaymentResponse\AbstractPaymentResponse $rtnResp
     * @param \HHK\Payment\Invoice\Invoice $invoice
     * @return void
     */
    public function feePaymentAccepted(\PDO $dbh, Session $uS, AbstractPaymentResponse $rtnResp, Invoice $invoice)
    {

        // set status
        $this->status = PaymentResult::ACCEPTED;

        // zero total invoices do not have payment records.
        if ($rtnResp->getIdPayment() > 0 && $this->idInvoice > 0) {
            $this->createPaymentInvoiceRcrd($dbh, $rtnResp->getIdPayment(), $this->idInvoice, $rtnResp->getAmount());
        }


        // Make out receipt
        $this->receiptMarkup = Receipt::createRefundAmtMarkup($dbh, $rtnResp, $uS->siteName, $uS->sId);

        // Email receipt
        try {
            $this->displayMessage .= $this->emailReceipt($dbh);
        } catch (\Exception $ex) {
            $this->displayMessage .= "Email Failed, Error = " . $ex->getMessage();
        }
    }


    /**
     * Summary of getReplyMessage
     * @return string
     */
    public function getReplyMessage()
    {
        return $this->replyMessage . $this->displayMessage;
    }

}