<?php

/**
 * PaymentSvcs.php
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

    public function feePaymentAccepted(\PDO $dbh, Session $uS, PaymentResponse $payResp, Invoice $invoice) {

        // set status
        $this->status = PaymentResult::ACCEPTED;

        // zero total invoices do not have payment records.
        if ($payResp->getIdPayment() > 0 && $this->idInvoice > 0) {
            // payment-invoice
            $payInvRs = new PaymentInvoiceRS();
            $payInvRs->Amount->setNewVal($payResp->getAmount());
            $payInvRs->Invoice_Id->setNewVal($this->idInvoice);
            $payInvRs->Payment_Id->setNewVal($payResp->getIdPayment());
            EditRS::insert($dbh, $payInvRs);

        }

        // Make out receipt
        $this->receiptMarkup = Receipt::createSaleMarkup($dbh, $invoice, $uS->siteName, $uS->sId, $payResp);

        // Email receipt
        try {
            $this->displayMessage .= $this->emailReceipt($dbh);
        } catch (Exception $ex) {
            $this->displayMessage .= "Email Failed, Error = " . $ex->getMessage();
        }

    }

    public function feePaymentRejected(\PDO $dbh, Session $uS, PaymentResponse $payResp, Invoice $invoice) {

        $this->status = PaymentResult::DENIED;

        if ($payResp->getIdPayment() > 0 && $this->idInvoice > 0) {
            // payment-invoice
            $payInvRs = new PaymentInvoiceRS();
            $payInvRs->Amount->setNewVal($payResp->getAmount());
            $payInvRs->Invoice_Id->setNewVal($this->idInvoice);
            $payInvRs->Payment_Id->setNewVal($payResp->getIdPayment());
            EditRS::insert($dbh, $payInvRs);
        }

        // Make out receipt
        $this->receiptMarkup = Receipt::createDeclinedMarkup($dbh, $invoice, $uS->siteName, $uS->sId, $payResp);

        // Email receipt
        try {
            $this->displayMessage .= $this->emailReceipt($dbh);
        } catch (Exception $ex) {
            $this->displayMessage .= "Email Failed, Error = " . $ex->getMessage();
        }

    }

    public function feePaymentError(\PDO $dbh, Session $uS) {


    }

    public function feePaymentInvoiced(\PDO $dbh, Invoice $invoice) {

        //$this->invoiceMarkup = $invoice->createMarkup($dbh);
        $this->invoiceNumber = $invoice->getInvoiceNumber();

    }

    public function emailReceipt(\PDO $dbh) {

        $config = new Config_Lite(ciCFG_FILE);
        $toAddr = '';
        $guestName = '';
        $guestHasEmail = FALSE;

        $fromAddr = $config->getString('guest_email', 'FromAddress', '');

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


        $mail = prepareEmail($config);

        $mail->From = $fromAddr;
        $mail->addReplyTo($config->getString('guest_email', 'ReplyTo', ''));
        $mail->FromName = $config->getString('site', 'Site_Name', '');

        $mail->addAddress($toAddrSan);     // Add a recipient

        $bccEntry = $config->getString('guest_email', 'BccAddress', '');
        $bccs = explode(',', $bccEntry);

        foreach ($bccs as $b) {

            $bcc = filter_var($b, FILTER_SANITIZE_EMAIL);

            if ($bcc !== FALSE && $bcc != '') {
                $mail->addBCC($bcc);
            }
        }

        $mail->isHTML(true);

        $mail->Subject = $config->getString('site', 'Site_Name', '') . ' Payment Receipt';
        $mail->msgHTML($this->receiptMarkup);

        if($mail->send()) {
            if ($guestHasEmail) {
                return "Email sent" . $guestName;
            }
        } else {
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

}


class cofResult extends PaymentResult {

    function __construct($displayMessage, $status, $idName, $idRegistration) {

        parent::__construct(0, $idRegistration, $idName);

        $this->displayMessage = $displayMessage;
        $this->status = $status;

    }

}


class ReturnResult extends PaymentResult {

    public function feePaymentAccepted(\PDO $dbh, Session $uS, PaymentResponse $rtnResp, Invoice $invoice) {

        // set status
        $this->status = PaymentResult::ACCEPTED;

        if ($rtnResp->getIdPayment() > 0 && $this->idInvoice > 0) {
            // payment-invoice
            $payInvRs = new PaymentInvoiceRS();
            $payInvRs->Amount->setNewVal($rtnResp->getAmount());
            $payInvRs->Invoice_Id->setNewVal($this->idInvoice);
            $payInvRs->Payment_Id->setNewVal($rtnResp->getIdPayment());
            EditRS::insert($dbh, $payInvRs);

        }

        // Make out receipt
        $this->receiptMarkup = Receipt::createReturnMarkup($dbh, $rtnResp, $uS->siteName, $uS->sId);

        // Email receipt
        try {
            $this->displayMessage .= $this->emailReceipt($dbh);
        } catch (Exception $ex) {
            $this->displayMessage .= "Email Failed, Error = " . $ex->getMessage();
        }
    }
}



/**
 * Description of PaymentSvcs
 *
 * @author Eric
 */
class PaymentSvcs {


    public static function payAmount(\PDO $dbh, Invoice $invoice, PaymentManagerPayment $pmp, $postbackUrl, $paymentDate = '') {

        $uS = Session::getInstance();

        // Check the status
        if ($invoice->getStatus() != InvoiceStatus::Unpaid) {
            $payResult = new PaymentResult(0, 0, 0);
            $payResult->setReplyMessage('Error:  The payment status must be unpaid, instead it is: '.$invoice->getStatus());
            return $payResult;
        }

        // Check the payment amount.
        if ($invoice->getAmountToPay() == 0) {

            // Pay 0 amounts as cash.
            $pmp->setPayType(PayType::Cash);
        }

        if ($invoice->getAmountToPay() < 0) {
            $payResult = new PaymentResult(0, 0, 0);
            $payResult->setReplyMessage('warning:  Cannot Pay a negative amount. ');
            return $payResult;
        }

        // Check balance
        if (abs($invoice->getAmountToPay()) > abs($invoice->getBalance())) {
            $payResult = new PaymentResult(0, 0, 0);
            $payResult->setReplyMessage('error:  Payment (' . $invoice->getAmountToPay() . ') cannot be larger than the remaining balance (' . $invoice->getBalance() . ') on an invoice.');
            return $payResult;
        }

        $amount = $invoice->getAmountToPay();
        $payResult = NULL;


        if ($paymentDate != '') {

            try {
                $payDT = new DateTime($paymentDate);
                $paymentDate = $payDT->format('Y-m-d H:i:s');

                $now = new DateTime();
                $now->setTime(0, 0, 0);
                $payDT->setTime(0, 0, 0);
                if ($payDT > $now) {
                    $paymentDate = date('Y-m-d H:i:s');
                }

            } catch (Exception $ex) {
                $paymentDate = date('Y-m-d H:i:s');
            }

        } else {

            $paymentDate = date('Y-m-d H:i:s');
        }


        switch ($pmp->getPayType()) {

          case PayType::Charge:

            // Payment Gateway
            $gateway = PaymentGateway::factory($dbh, $uS->PaymentGateway, $uS->ccgw);

            $payResult = $gateway->CreditSale($dbh, $pmp, $invoice, $postbackUrl);


            break;

          case PayType::ChargeAsCash:

            // Manual Charge
            $pAuthRs = new Payment_AuthRS();
            $pAuthRs->Card_Type->setStoredVal($pmp->getChargeCard());
            $pAuthRs->Acct_Number->setStoredVal($pmp->getChargeAcct());
            $pAuthRs->Status_Code->setStoredVal('000');
            $pAuthRs->Timestamp->setStoredVal(date('Y-m-d H:i:s', strtotime($pmp->getPayDate())));

            $cashResp = new ManualChargeResponse($amount, $invoice->getSoldToId(), $invoice->getInvoiceNumber(), $pmp->getChargeCard(), $pmp->getChargeAcct(), $pmp->getPayNotes());

            ChargeAsCashTX::sale($dbh, $cashResp, $uS->username, $paymentDate);

            // Update invoice
            $invoice->updateInvoiceBalance($dbh, $cashResp->getAmount(), $uS->username);

            $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
            $payResult->feePaymentAccepted($dbh, $uS, $cashResp, $invoice);
            $payResult->setDisplayMessage('External Credit Payment Recorded.  ');

            break;


          case PayType::Cash:

            $cashResp = new CashResponse($amount, $invoice->getSoldToId(), $invoice->getInvoiceNumber(), $pmp->getPayNotes());

            CashTX::cashSale($dbh, $cashResp, $uS->username, $paymentDate);

            // Update invoice
            $invoice->updateInvoiceBalance($dbh, $cashResp->getAmount(), $uS->username);

            $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
            $payResult->feePaymentAccepted($dbh, $uS, $cashResp, $invoice);
            $payResult->setDisplayMessage('Cash Payment.  ');

            break;

          case PayType::Check:

            $ckResp = new CheckResponse($amount, $invoice->getSoldToId(), $invoice->getInvoiceNumber(), $pmp->getCheckNumber(), $pmp->getPayNotes());

            CheckTX::checkSale($dbh, $ckResp, $uS->username, $paymentDate);

            // Update invoice
            $invoice->updateInvoiceBalance($dbh, $ckResp->getAmount(), $uS->username);

            $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
            $payResult->feePaymentAccepted($dbh, $uS, $ckResp, $invoice);
            $payResult->setDisplayMessage('Payment by Check.  ');

            break;

          case PayType::Transfer:

            $ckResp = new TransferResponse($amount, $invoice->getSoldToId(), $invoice->getInvoiceNumber(), $pmp->getTransferAcct(), $pmp->getPayNotes());

            TransferTX::sale($dbh, $ckResp, $uS->username, $paymentDate);

            // Update invoice
            $invoice->updateInvoiceBalance($dbh, $ckResp->getAmount(), $uS->username);

            $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
            $payResult->feePaymentAccepted($dbh, $uS, $ckResp, $invoice);
            $payResult->setDisplayMessage('Payment by Transfer.  ');

            break;

          case PayType::Invoice:

            $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
            $payResult->feePaymentInvoiced($dbh, $invoice);
            $payResult->setDisplayMessage('Amount Invoiced.  ');
            break;

        }

        return $payResult;
    }

    /**
     * Return an Amount directly from an invoice.  No payment record needed.
     *
     * @param \PDO $dbh
     * @param Invoice $invoice
     * @param PaymentManagerPayment $pmp
     * @param string $postPage
     * @param string $paymentDate
     * @return \ReturnResult
     */
    public static function returnAmount(\PDO $dbh, Invoice $invoice, PaymentManagerPayment $pmp, $paymentDate = '') {

        $uS = Session::getInstance();

        // Check the status
        if ($invoice->getStatus() != InvoiceStatus::Unpaid) {
            $rtnResult = new ReturnResult(0, 0, 0);
            $rtnResult->setReplyMessage('Error:  The return status must be "unpaid", instead it is: '.$invoice->getStatus());
            return $rtnResult;
        }

        // Check the payment amount.
        if ($invoice->getAmountToPay() == 0) {
            $rtnResult = new ReturnResult(0, 0, 0);
            $rtnResult->setReplyMessage('warning:  Invoice\'s Amount to Return is 0');
            return $rtnResult;
        }

        if ($invoice->getAmountToPay() > 0) {
            $rtnResult = new ReturnResult(0, 0, 0);
            $rtnResult->setReplyMessage('warning:  Cannot Return this amount. ');
            return $rtnResult;
        }

        // Check balance
        if (abs($invoice->getAmountToPay()) > abs($invoice->getBalance())) {
            $rtnResult = new ReturnResult(0, 0, 0);
            $rtnResult->setReplyMessage('error:  Return (' . $invoice->getAmountToPay() . ') cannot be larger than the remaining balance (' . $invoice->getBalance() . ') on the invoice.');
            return $rtnResult;
        }

        // Use positive amounts for return amount (This is not return payment.)
        $amount = abs($invoice->getAmountToPay());
        $rtnResult = NULL;


        if ($paymentDate != '') {

            try {
                $payDT = new DateTime($paymentDate);
                $paymentDate = $payDT->format('Y-m-d H:i:s');

                $now = new DateTime();
                $now->setTime(0, 0, 0);
                $payDT->setTime(0, 0, 0);
                if ($payDT > $now) {
                    $paymentDate = date('Y-m-d H:i:s');
                }

            } catch (Exception $ex) {
                $paymentDate = date('Y-m-d H:i:s');
            }

        } else {

            $paymentDate = date('Y-m-d H:i:s');
        }

        switch ($pmp->getRtnPayType()) {

            case PayType::Charge:

                $tokenRS = CreditToken::getTokenRsFromId($dbh, $pmp->getRtnIdToken());

                // Do we have a token?
                if (CreditToken::hasToken($tokenRS)) {

                    if ($tokenRS->Running_Total->getStoredVal() < $amount) {
                        throw new Hk_Exception_Payment('Return Failed.  Maximum return for this card is: $' . number_format($tokenRS->Running_Total->getStoredVal(), 2));
                    }

                    // Set up request
                    $returnRequest = new CreditReturnTokenRequest();
                    $returnRequest->setCardHolderName($tokenRS->CardHolderName->getStoredVal());
                    $returnRequest->setFrequency(MpFrequencyValues::OneTime)->setMemo(MpVersion::PosVersion);
                    $returnRequest->setInvoice($invoice->getInvoiceNumber());
                    $returnRequest->setPurchaseAmount($amount);

                    $returnRequest->setToken($tokenRS->Token->getStoredVal());
                    $returnRequest->setTokenId($tokenRS->idGuest_token->getStoredVal());


                    $tokenResp = TokenTX::creditReturnToken($dbh, $invoice->getSoldToId(), $uS->ccgw, $returnRequest, NULL, $pmp->getPayNotes());

                    // Analyze the result
                    $rtnResult = self::AnalyzeCreditReturnResult($dbh, $tokenResp, $invoice, $pmp->getRtnIdToken());
                    $rtnResult->setDisplayMessage('Refund to Credit Card.  ');

                } else {
                    throw new Hk_Exception_Payment('Return Failed.  Credit card token not found.  ');
                }

                break;

            case PayType::ChargeAsCash:

                // Manual Charge// $amount, $idPayor, $invoiceNumber, $chargeType, $chargeAcct, $payNote = '', $idToken = 0
                $cashResp = new ManualChargeResponse($amount, $invoice->getSoldToId(), $invoice->getInvoiceNumber(), $pmp->getRtnChargeCard(), $pmp->getRtnChargeAcct(), $pmp->getPayNotes());

                ChargeAsCashTX::refundAmount($dbh, $cashResp, $uS->username, $paymentDate);

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, (0 - $cashResp->getAmount()), $uS->username);

                $rtnResult = new ReturnResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
                $rtnResult->feePaymentAccepted($dbh, $uS, $cashResp, $invoice);
                $rtnResult->setDisplayMessage('External Credit Refund Recorded.  ');

                break;

            case PayType::Cash:

                $cashResp = new CashResponse($amount, $invoice->getSoldToId(), $invoice->getInvoiceNumber(), $pmp->getPayNotes());

                CashTX::returnAmount($dbh, $cashResp, $uS->username, $paymentDate);

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, (0 - $cashResp->getAmount()), $uS->username);

                $rtnResult = new ReturnResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
                $rtnResult->feePaymentAccepted($dbh, $uS, $cashResp, $invoice);
                $rtnResult->setDisplayMessage('Cash Return.  ');
                break;

            case PayType::Check:

                $ckResp = new CheckResponse($amount, $invoice->getSoldToId(), $invoice->getInvoiceNumber(), $pmp->getRtnCheckNumber(), $pmp->getPayNotes());

                CheckTX::returnAmount($dbh, $ckResp, $uS->username, $paymentDate);

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, (0 - $ckResp->getAmount()), $uS->username);

                $rtnResult = new ReturnResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
                $rtnResult->feePaymentAccepted($dbh, $uS, $ckResp, $invoice);
                $rtnResult->setDisplayMessage('Check Cut for Return.  ');
                break;

            case PayType::Invoice:

                $rtnResult = new ReturnResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
                $rtnResult->feePaymentInvoiced($dbh, $invoice);
                $rtnResult->setDisplayMessage('Return Amount Invoiced.  ');
                break;

        }

        return $rtnResult;
    }

    public static function voidFees(\PDO $dbh, $idPayment, $bid, $postbackUrl, $paymentNotes = '') {

        $uS = Session::getInstance();

        $payRs = new PaymentRS();
        $payRs->idPayment->setStoredVal($idPayment);
        $pments = EditRS::select($dbh, $payRs, array($payRs->idPayment));

        if (count($pments) != 1) {
            return array('warning' => 'Payment record not found for Void/Reverse.  ', 'bid' => $bid);
        }

        EditRS::loadRow($pments[0], $payRs);

        // Already voided, or otherwise ineligible
        if ($payRs->Status_Code->getStoredVal() != PaymentStatusCode::Paid) {
            return array('warning' => 'Payment is ineligable for Void/Reverse.  ', 'bid' => $bid);
        }

        $invoice = new Invoice($dbh);
        $invoice->loadInvoice($dbh, 0, $idPayment);

        if ($payRs->idPayment_Method->getStoredVal() != PaymentMethod::Charge) {
            return array('warning' => 'Use Return instead.  ', 'bid' => $bid);
        }

        // Load gateway
        $gateway = PaymentGateway::factory($dbh, $uS->PaymentGateway, $uS->ccgw);

        return $gateway->voidSale($dbh, $invoice, $payRs, $paymentNotes, $bid, $postbackUrl);

    }

    public static function reversalFees(\PDO $dbh, $idPayment, $bid, $paymentNotes = '') {

        $uS = Session::getInstance();

        $payRs = new PaymentRS();
        $payRs->idPayment->setStoredVal($idPayment);
        $pments = EditRS::select($dbh, $payRs, array($payRs->idPayment));

        if (count($pments) != 1) {
            return array('warning' => 'Payment record not found.  Unable to Reverse/Void this purchase.  ', 'bid' => $bid);
        }

        EditRS::loadRow($pments[0], $payRs);

        // Already voided, or otherwise ineligible
        if ($payRs->Status_Code->getStoredVal() != PaymentStatusCode::Paid) {
            return array('warning' => 'Payment is ineligable for Reversal/Void.  ', 'bid' => $bid);
        }

        $invoice = new Invoice($dbh);
        $invoice->loadInvoice($dbh, 0, $idPayment);

        if ($payRs->idPayment_Method->getStoredVal() != PaymentMethod::Charge) {
            return array('warning' => 'Use Return instead.  ', 'bid' => $bid);
        }

        // Load gateway
        $gateway = PaymentGateway::factory($dbh, $uS->PaymentGateway, $uS->ccgw);

        return $gateway->reverseSale($dbh, $payRs, $invoice, $bid, $paymentNotes);

    }

    public static function returnPayment(\PDO $dbh, $idPayment, $bid, $returnAmt) {

        $uS = Session::getInstance();
        $dataArray = array('bid' => $bid);
        $reply = '';

        $payRs = new PaymentRS();
        $payRs->idPayment->setStoredVal($idPayment);
        $pments = EditRS::select($dbh, $payRs, array($payRs->idPayment));

        if (count($pments) != 1) {
             return array('warning' => 'Payment record not found.  ', 'bid' => $bid);
        }

        EditRS::loadRow($pments[0], $payRs);

        // Already voided, or otherwise ineligible
        if ($payRs->Status_Code->getStoredVal() != PaymentStatusCode::Paid) {
            return array('warning' => 'This Payment is ineligable for return.  ', 'bid' => $bid);
        }


        // Get the invoice record
        $invoice = new Invoice($dbh);
        $invoice->loadInvoice($dbh, 0, $idPayment);


        switch ($payRs->idPayment_Method->getStoredVal()) {

            case PaymentMethod::Charge:

                // Load gateway
                $gateway = PaymentGateway::factory($dbh, $uS->PaymentGateway, $uS->ccgw);
                $dataArray = $gateway->returnSale($dbh, $payRs, $invoice, $returnAmt, $bid);

                break;

            case PaymentMethod::Cash:

                // Determine amount to return
                if (abs($returnAmt) > abs($payRs->Amount->getStoredVal())) {
                    return array('warning' => 'Return Failed.  Return amount is larger than the original purchase amount.  ', 'bid' => $bid);
                }

                $cashResp = new CashResponse($returnAmt, $payRs->idPayor->getStoredVal(), $invoice->getInvoiceNumber());

                CashTX::returnPayment($dbh, $cashResp, $uS->username, $payRs);

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, 0 - $cashResp->getAmount(), $uS->username);

                $reply .= 'Payment is Returned.  ';

                $cashResp->idVisit = $invoice->getOrderNumber();
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createReturnMarkup($dbh, $cashResp, $uS->siteName, $uS->sId)));

                break;

            case PaymentMethod::ChgAsCash:

                // Find hte detail record.
                $pAuthRs = new Payment_AuthRS();
                $pAuthRs->idPayment->setStoredVal($payRs->idPayment->getStoredVal());
                $arows = EditRS::select($dbh, $pAuthRs, array($pAuthRs->idPayment));

                if (count($arows) != 1) {
                    throw new Hk_Exception_Payment('Payment Detail record not found. ');
                }

                EditRS::loadRow($arows[0], $pAuthRs);

                // Determine amount to return
                if ($returnAmt > $payRs->Amount->getStoredVal()) {
                    return array('warning' => 'Return Failed.  Return amount ($' . number_format($returnAmt,2) . ') is larger than the original purchase amount ($' . number_format($payRs->Amount->getStoredVal(), 2) . ').  ', 'bid' => $bid);
                } else if ($returnAmt <= 0 && $payRs->Is_Refund->getStoredVal() == 0) {
                    return array('warning' => 'Return Failed.  Return amount must be larger than 0.  ', 'bid' => $bid);
                }

                $cashResp = new ManualChargeResponse($returnAmt, $payRs->idPayor->getStoredVal(), $invoice->getInvoiceNumber(), $pAuthRs->Card_Type->getStoredVal(), $pAuthRs->Acct_Number->getStoredVal());

                ChargeAsCashTX::returnPayment($dbh, $cashResp, $uS->username, $payRs);

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, 0 - $cashResp->getAmount(), $uS->username);

                $reply .= 'Payment is Returned.  ';

                $cashResp->idVisit = $invoice->getOrderNumber();
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createReturnMarkup($dbh, $cashResp, $uS->siteName, $uS->sId)));

                break;

            case PaymentMethod::Check:

                // Find hte detail record.
                $pAuthRs = new PaymentInfoCheckRS();
                $pAuthRs->idPayment->setStoredVal($payRs->idPayment->getStoredVal());
                $arows = EditRS::select($dbh, $pAuthRs, array($pAuthRs->idPayment));

                if (count($arows) != 1) {
                    throw new Hk_Exception_Payment('Payment Detail record not found. ');
                }

                EditRS::loadRow($arows[0], $pAuthRs);

                // Determine amount to return
                if ($returnAmt > $payRs->Amount->getStoredVal()) {
                    return array('warning' => 'Return Failed.  Return amount is larger than the original purchase amount.  ', 'bid' => $bid);
                } else if ($returnAmt <= 0) {
                    return array('warning' => 'Return Failed.  Return amount must be larger than 0.  ', 'bid' => $bid);
                }

                $cashResp = new CheckResponse($returnAmt, $payRs->idPayor->getStoredVal(), $invoice->getInvoiceNumber(), $pAuthRs->Check_Number->getStoredVal());

                CheckTX::checkReturn($dbh, $cashResp, $uS->username, $payRs);

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, 0 - $cashResp->getAmount(), $uS->username);

                $reply .= 'Payment is Returned.  ';

                $cashResp->idVisit = $invoice->getOrderNumber();
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createReturnMarkup($dbh, $cashResp, $uS->siteName, $uS->sId)));
                break;

            case PaymentMethod::Transfer:

                // Find hte detail record.
                $pAuthRs = new PaymentInfoCheckRS();
                $pAuthRs->idPayment->setStoredVal($payRs->idPayment->getStoredVal());
                $arows = EditRS::select($dbh, $pAuthRs, array($pAuthRs->idPayment));

                if (count($arows) != 1) {
                    throw new Hk_Exception_Payment('Payment Detail record not found. ');
                }

                EditRS::loadRow($arows[0], $pAuthRs);

                // Determine amount to return
                if ($returnAmt > $payRs->Amount->getStoredVal()) {
                    return array('warning' => 'Return Failed.  Return amount is larger than the original purchase amount.  ', 'bid' => $bid);
                } else if ($returnAmt <= 0) {
                    return array('warning' => 'Return Failed.  Return amount must be larger than 0.  ', 'bid' => $bid);
                }

                $cashResp = new TransferResponse($returnAmt, $payRs->idPayor->getStoredVal(), $invoice->getInvoiceNumber(), $pAuthRs->Check_Number->getStoredVal());

                TransferTX::transferReturn($dbh, $cashResp, $uS->username, $payRs);

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, 0 - $cashResp->getAmount(), $uS->username);

                $reply .= 'Payment is Returned.  ';

                $cashResp->idVisit = $invoice->getOrderNumber();
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createReturnMarkup($dbh, $cashResp, $uS->siteName, $uS->sId)));
                break;

            default:
                throw new Hk_Exception_Payment('Unknown pay type.  ');
        }

        $dataArray['success'] = $reply;
        return $dataArray;
    }

    public static function AnalyzeCreditReturnResult(\PDO $dbh, PaymentResponse $rtnResp, Invoice $invoice, $idToken = 0) {

        $uS = Session::getInstance();

        $rtnResult = new ReturnResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId(), $idToken);

        switch ($rtnResp->getStatus()) {

            case CreditPayments::STATUS_APPROVED:

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, 0 - $rtnResp->response->getAuthorizeAmount(), $uS->username);

                $rtnResult->feePaymentAccepted($dbh, $uS, $rtnResp, $invoice);
                $rtnResult->setDisplayMessage('Refund by Credit Card.  ');

                break;

            case CreditPayments::STATUS_DECLINED:

                $rtnResult->setStatus(PaymentResult::DENIED);
                $rtnResult->feePaymentRejected($dbh, $uS, $rtnResp, $invoice);
                $rtnResult->setDisplayMessage('** The Return is Declined. **  Message: ' . $rtnResp->response->getResponseMessage());

                break;

            default:

                $rtnResult->setStatus(PaymentResult::ERROR);
                $rtnResult->feePaymentError($dbh, $uS);
                $rtnResult->setDisplayMessage('** Return Invalid or Error **  Message: ' . $rtnResp->response->getResponseMessage());
        }

        return $rtnResult;
    }

    public static function voidReturnFees(\PDO $dbh, $idPayment, $bid, $paymentDate = '') {

        $uS = Session::getInstance();
        $dataArray = array('bid' => $bid);
        $reply = '';

        $payRs = new PaymentRS();
        $payRs->idPayment->setStoredVal($idPayment);
        $pments = EditRS::select($dbh, $payRs, array($payRs->idPayment));

        if (count($pments) != 1) {
            return array('warning' => 'Payment record not found.  Unable to Void this return.  ', 'bid' => $bid);
        }

        EditRS::loadRow($pments[0], $payRs);

        // Already voided, or otherwise ineligible
        if ($payRs->Status_Code->getStoredVal() != PaymentStatusCode::Retrn && $payRs->Is_Refund->getStoredVal() === 0) {
            return array('warning' => 'Return is ineligable for Voiding.  ', 'bid' => $bid);
        }

        $invoice = new Invoice($dbh);
        $invoice->loadInvoice($dbh, 0, $idPayment);

        if ($payRs->idPayment_Method->getStoredVal() != PaymentMethod::Charge) {
            return array('warning' => 'Not Available.  ', 'bid' => $bid);
        }

        // find the token record
        if ($payRs->idToken->getStoredVal() > 0) {
            $tknRs = CreditToken::getTokenRsFromId($dbh, $payRs->idToken->getStoredVal());
        } else {
            return array('warning' => 'Payment Token Id not found.  Unable to Void this return.  ', 'bid' => $bid);
        }

        if (CreditToken::hasToken($tknRs) === FALSE) {
            return array('warning' => 'Payment Token not found.  Unable to Void this return.  ', 'bid' => $bid);
        }

        // Find hte detail record.
        $stmt = $dbh->query("Select * from payment_auth where idPayment = " . $payRs->idPayment->getStoredVal() . " order by idPayment_auth");
        $arows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($arows) < 1) {
            return array('warning' => 'Payment Detail record not found.  Unable to Void this Return. ', 'bid' => $bid);
        }

        $pAuthRs = new Payment_AuthRS();
        EditRS::loadRow(array_pop($arows), $pAuthRs);

        if ($pAuthRs->Status_Code->getStoredVal() !== PaymentStatusCode::Retrn) {
            return array('warning' => 'Return is ineligable for Voiding.  ', 'bid' => $bid);
        }

        // Set up request
        $revRequest = new CreditVoidReturnTokenRequest();
        $revRequest->setAuthCode($pAuthRs->Approval_Code->getStoredVal())
            ->setCardHolderName($tknRs->CardHolderName->getStoredVal())
            ->setFrequency(MpFrequencyValues::OneTime)->setMemo(MpVersion::PosVersion)
            ->setInvoice($invoice->getInvoiceNumber())
            ->setPurchaseAmount($pAuthRs->Approved_Amount->getStoredVal())
            ->setRefNo($pAuthRs->Reference_Num->getStoredVal())
            ->setToken($tknRs->Token->getStoredVal())
            ->setTokenId($tknRs->idGuest_token->getStoredVal())
            ->setTitle('CreditVoidReturnToken');

        try {

            $csResp = TokenTX::creditVoidReturnToken($dbh, $payRs->idPayor->getstoredVal(), $uS->ccgw, $revRequest, $payRs);

            switch ($csResp->getStatus()) {

                case CreditPayments::STATUS_APPROVED:

                    // Update invoice
                    $invoice->updateInvoiceBalance($dbh, $csResp->response->getAuthorizeAmount(), $uS->username);

                    $reply .= 'Return is Voided.  ';
                    $csResp->idVisit = $invoice->getOrderNumber();
                    //$dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createSaleMarkup($dbh, $csResp, $uS->resourceURL . 'images/receiptlogo.png', $uS->siteName, $uS->sId, 'Reverse Sale')));
                    $dataArray['success'] = $reply;

                    break;

                case CreditPayments::STATUS_DECLINED:

                    $dataArray['success'] = 'Declined.';
                    break;

                default:

                    $dataArray['warning'] = '** Void-Return Invalid or Error. **  ' . 'Message: ' . $csResp->response->getMessage();

            }

        } catch (Hk_Exception_Payment $exPay) {

            $dataArray['warning'] = "Void-Return Error = " . $exPay->getMessage();
        }

        return $dataArray;
    }

    public static function AnalyzeCredSaleResult(\PDO $dbh, PaymentResponse $payResp, \Invoice $invoice, $idToken = 0, $useAVS = TRUE, $useCVV = TRUE) {

        $uS = Session::getInstance();

        $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId(), $idToken);


        switch ($payResp->getStatus()) {

            case CreditPayments::STATUS_APPROVED:

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, $payResp->response->getAuthorizedAmount(), $uS->username);

                $payResult->feePaymentAccepted($dbh, $uS, $payResp, $invoice);
                $payResult->setDisplayMessage('Paid by Credit Card.  ');

                if ($payResp->isPartialPayment()) {
                    $payResult->setDisplayMessage('** Partially Approved Amount: ' . number_format($payResp->response->getAuthorizedAmount(), 2) . ' (Remaining Balance Due: ' . number_format($invoice->getBalance(), 2) . ').  ');
                }

                if ($useAVS) {
                    $avsResult = new AVSResult($payResp->response->getAVSResult());

                    if ($avsResult->isZipMatch() === FALSE) {
                        $payResult->setDisplayMessage($avsResult->getResultMessage() . '  ');
                    }
                }

                if ($useCVV) {
                    $cvvResult = new CVVResult($payResp->response->getCvvResult());
                    if ($cvvResult->isCvvMatch() === FALSE && $uS->CardSwipe === FALSE) {
                        $payResult->setDisplayMessage($cvvResult->getResultMessage() . '  ');
                    }
                }

                break;

            case CreditPayments::STATUS_DECLINED:

                $payResult->setStatus(PaymentResult::DENIED);
                $payResult->feePaymentRejected($dbh, $uS, $payResp, $invoice);

                $msg = '** The Payment is Declined. **';
                if ($payResp->response->getResponseMessage() != '') {
                    $msg .= 'Message: ' . $payResp->response->getResponseMessage();
                }
                $payResult->setDisplayMessage($msg);

                break;

            default:

                $payResult->setStatus(PaymentResult::ERROR);
                $payResult->feePaymentError($dbh, $uS);
                $payResult->setDisplayMessage('** Payment Invalid or Error **  Message: ' . $payResp->response->getResponseMessage());
        }

        return $payResult;
    }

    public static function getInfoFromCardId(\PDO $dbh, $cardId) {

        $infoArray = array();

        $query = "select `idName`, `idGroup`, `InvoiceNumber`, `Amount` from `card_id` where `CardID` = :cid";
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':cid'=>$cardId));

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) > 0) {

            $infoArray = $rows[0];

            // Delete to discourge replays.
            $stmt = $dbh->prepare("delete from card_id where CardID = :cid");
            $stmt->execute(array(':cid'=>$cardId));

        }

        return $infoArray;
    }

    public static function processWebhook(\PDO $dbh, $data) {

        $uS = Session::getInstance();

        // Payment Gateway
        $gateway = PaymentGateway::factory($dbh, $uS->PaymentGateway, $uS->ccgw);

        $payNotes = '';

        if (isset($uS->paymentNotes)) {
            $payNotes = $uS->paymentNotes;
            unset($uS->paymentNotes);
        }

        return $gateway->processWebhook($dbh, $data, $payNotes, $uS->username);

    }

    public static function processSiteReturn(\PDO $dbh, $post) {

        $uS = Session::getInstance();

        // Payment Gateway
        $gateway = PaymentGateway::factory($dbh, $uS->PaymentGateway, $uS->ccgw);

        $payNotes = '';
        $idInv = 0;
        $tokenId = '';

        if (isset($uS->paymentNotes)) {
            $payNotes = $uS->paymentNotes;
            unset($uS->paymentNotes);
        }

        if (isset($uS->imtoken)) {
            $tokenId = $uS->imtoken;
            unset($uS->imtoken);
        }

        if (isset($uS->paymentIds[$tokenId])) {
            $idInv = $uS->paymentIds[$tokenId];
        }

        if (isset($uS->imcomplete)) {
            $post = $uS->imcomplete;
            unset($uS->imcomplete);
        }

        return $gateway->processHostedReply($dbh, $post, $tokenId, $idInv, $payNotes, $uS->username);

    }

    public static function generateReceipt(\PDO $dbh, $idPayment) {

        $uS = Session::getInstance();

        if ($idPayment < 1) {
            return array('warning'=>'Payment Id is not rational: ' . $idPayment);
        }

        $payRs = new PaymentRS();
        $payRs->idPayment->setStoredVal($idPayment);
        $pments = EditRS::select($dbh, $payRs, array($payRs->idPayment));

        if (count($pments) != 1) {
             return array('warning' => 'Payment record not found.  ');
        }

        EditRS::loadRow($pments[0], $payRs);

        // Get the invoice record
        $invoice = new Invoice($dbh);
        $invoice->loadInvoice($dbh, 0, $idPayment);

        $payResp = NULL;

        switch ($payRs->idPayment_Method->getStoredVal()) {

            case PaymentMethod::Cash:
                $payResp = new CashResponse($payRs->Amount->getStoredVal(), $payRs->idPayor->getStoredVal(), $invoice->getInvoiceNumber());
                $payResp->paymentRs = $payRs;
                break;

            case PaymentMethod::Check:

                $ckRs = new PaymentInfoCheckRS();
                $ckRs->idPayment->setStoredVal($idPayment);
                $rows = EditRS::select($dbh, $ckRs, array($ckRs->idPayment));

                if (count($rows) != 1) {
                    return array('warning'=>'Check payment record not found.');
                }

                EditRS::loadRow($rows[0], $ckRs);
                $payResp = new CheckResponse($payRs->Amount->getStoredVal(), $payRs->idPayor->getStoredVal(), $invoice->getInvoiceNumber(), $rows[0]['Check_Number']);
                $payResp->paymentRs = $payRs;
                break;

            case PaymentMethod::Transfer:

                $ckRs = new PaymentInfoCheckRS();
                $ckRs->idPayment->setStoredVal($payRs->idPayment->getStoredVal());
                $rows = EditRS::select($dbh, $ckRs, array($ckRs->idPayment));

                if (count($rows) != 1) {
                    return array('warning'=>'Transfer payment record not found.');
                }

                EditRS::loadRow($rows[0], $ckRs);
                $payResp = new TransferResponse($payRs->Amount->getStoredVal(), $payRs->idPayor->getStoredVal(), $invoice->getInvoiceNumber(), $rows[0]['Check_Number']);
                $payResp->paymentRs = $payRs;
                break;

            case PaymentMethod::Charge:

                $stmt = $dbh->query("SELECT * FROM payment_auth where idPayment = $idPayment order by `Timestamp`");
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                if (count($rows) < 1) {
                    return array('warning'=>'Charge payment record not found.');
                }

                $pAuthRs = new Payment_AuthRS();
                EditRS::loadRow($rows[count($rows)-1], $pAuthRs);

                $gTRs = new Guest_TokenRS();
                $gTRs->idGuest_token->setStoredVal($payRs->idToken->getStoredVal());
                $guestTkns = EditRS::select($dbh, $gTRs, array($gTRs->idGuest_token));

                if (count($guestTkns) > 0) {
                    EditRS::loadRow($guestTkns[0], $gTRs);
                }

                $gwResp = new StandInGwResponse($pAuthRs, $gTRs->OperatorID->getStoredVal(), $gTRs->CardHolderName->getStoredVal(), $gTRs->ExpDate->getStoredVal(), $gTRs->Token->getStoredVal(), $invoice->getInvoiceNumber(), $payRs->Amount->getStoredVal());

                try {
                    $gateway = PaymentGateway::factory($dbh, $pAuthRs->Processor->getStoredVal(), $uS->ccgw);
                } catch (Hk_Exception_Runtime $hex) {
                    // Grab the local gateway
                    $gateway = PaymentGateway::factory($dbh, '', '');
                }

                $payResp = $gateway->getPaymentResponseObj($gwResp, $payRs->idPayor->getStoredVal(), $invoice->getIdGroup(), $invoice->getInvoiceNumber());
                $payResp->paymentRs = $payRs;
                break;

            case PaymentMethod::ChgAsCash:

                $stmt = $dbh->query("SELECT * FROM payment_auth where idPayment = $idPayment order by idPayment_auth");
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                if (count($rows) < 1) {
                    return array('warning'=>'Charge payment record not found.');
                }

                $pAuthRs = new Payment_AuthRS();
                EditRS::loadRow($rows[0], $pAuthRs);

                $payResp = new ManualChargeResponse($payRs->Amount->getStoredVal(), $payRs->idPayor->getStoredVal(), $invoice->getInvoiceNumber(), $pAuthRs->Card_Type->getStoredVal(), $pAuthRs->Acct_Number->getStoredVal());
                $payResp->paymentRs = $payRs;
                break;

        }

        $dataArray = array();

        $statusCode = $payRs->Status_Code->getStoredVal();

        if ($statusCode == PaymentStatusCode::Paid && $payRs->Is_Refund->getStoredVal() > 0) {
            $statusCode = PaymentStatusCode::Retrn;
        }

        switch ($statusCode) {

            case PaymentStatusCode::Paid:

                $dataArray['receipt'] = Receipt::createSaleMarkup($dbh, $invoice, $uS->siteName, $uS->sId, $payResp);
                break;

            case PaymentStatusCode::Declined:

                $dataArray['receipt'] = Receipt::createDeclinedMarkup($dbh, $invoice, $uS->siteName, $uS->sId, $payResp);
                break;

            case PaymentStatusCode::VoidSale:
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createVoidMarkup($dbh, $payResp, $uS->siteName, $uS->sId)));
                break;

            case PaymentStatusCode::VoidReturn:
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createVoidMarkup($dbh, $payResp, $uS->siteName, $uS->sId, 'Void Return')));
                break;

            case PaymentStatusCode::Reverse:
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createVoidMarkup($dbh, $payResp, $uS->siteName, $uS->sId, 'Reverse Sale')));
                break;

            case PaymentStatusCode::Retrn:
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createReturnMarkup($dbh, $payResp, $uS->siteName, $uS->sId)));
                break;

        }

        return $dataArray;
    }


}


