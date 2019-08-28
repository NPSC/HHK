<?php
/**
 * InstamedGateway.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


class InstamedGateway extends PaymentGateway {

    const RELAY_STATE = 'relayState';
    const INVOICE_NUMBER = 'additionalInfo1';

    // query string parameter names
    const INSTAMED_TRANS_VAR = 'imt';
    const INSTAMED_RESULT_VAR = 'imres';
    // query string parameter values
    const HCO_TRANS = 'imsale';
    const COF_TRANS = 'imcof';
    const VOID_TRANS = 'imvoid';
    const RETURN_TRANS = 'imret';
    const POSTBACK_CANCEL = 'x';
    const POSTBACK_COMPLETE = 'c';
    const APPROVED = '000';
    const PARTIAL_APPROVAL = '010';
    // IM's backward way to get back to my original page.
    const TRANSFER_URL = 'ConfirmGwPayment.php';
    const TRANSFER_VAR = 'intfr';  // query sgring parameter name for the TRANSFER_URL
    const TRANSFER_DEFAULT_PAGE = 'register.php';
    const TRANSFER_POSTBACK_PAGE_VAR = 'pg';

    // Transaction Status Codes
    const AUTH = 'A';
    const CAPTURED_APPROVED = 'C';
    const CHARGEBACK = 'CB';
    const DECLINE = 'D';
    const VOID = 'V';
    const CANCELLED = 'X';

    protected $ssoUrl;
    protected $soapUrl;
    protected $NvpUrl;
    protected $saleUrl;
    protected $saleTokenUrl;
    protected $cofUrl;
    protected $returnUrl;
    protected $voidUrl;

    protected function getPaymentMethod() {
        return PaymentMethod::Charge;
    }

    protected function getGatewayName() {
        return 'instamed';
    }
    public function creditSale(\PDO $dbh, $pmp, $invoice, $postbackUrl) {

        $uS = Session::getInstance();
        $payResult = NULL;

        $tokenRS = CreditToken::getTokenRsFromId($dbh, $pmp->getIdToken());

        // Do we have a token?
        if (CreditToken::hasToken($tokenRS)) {
            // Token available

            $params = $this->getCredentials()->toCurl(FALSE)
                    . "&amount=" . $invoice->getAmountToPay()
                    . "&transactionType=CreditCard"
                    . "&transactionAction=authcapt"
                    . "&CardPresentStatus=notpresentphone"
                    . "&paymentMethodID=" . $tokenRS->Token->getStoredVal();

            $curlRequest = new ImCurlRequest();

            $resp = $curlRequest->submit($params, $this->saleTokenUrl, $this->getCredentials()->id, $this->getCredentials()->password);

            $resp['InvoiceNumber'] = $invoice->getInvoiceNumber();
            $resp['Amount'] = $invoice->getAmountToPay();

            $curlResponse = new VerifyCurlResponse($resp, MpTranType::Sale);

            // Save raw transaction in the db.
            try {
                self::logGwTx($dbh, $curlResponse->getResponseCode(), $params, json_encode($curlResponse->getResultArray()), 'CreditSaleToken');
            } catch (Exception $ex) {
                // Do Nothing
            }

            // Make a sale response...
            $sr = new ImPaymentResponse($curlResponse, $invoice->getSoldToId(), $invoice->getIdGroup(), $invoice->getInvoiceNumber(), $pmp->getPayNotes(), ($curlResponse->getPartialPaymentAmount() > 0 ? TRUE : FALSE));

            // Record transaction
            try {
                $transRs = Transaction::recordTransaction($dbh, $sr, $this->gwName, TransType::Sale, TransMethod::Token);
                $sr->setIdTrans($transRs->idTrans->getStoredVal());
            } catch (Exception $ex) {
                // do nothing
            }

            // record payment
            $payResp = SaleReply::processReply($dbh, $sr, $uS->username);

            // Analyze the result
            switch ($payResp->getStatus()) {

                case CreditPayments::STATUS_APPROVED:

                    // Update invoice
                    $invoice->updateInvoiceBalance($dbh, $payResp->response->getAuthorizedAmount(), $uS->username);

                    if ($payResp->getIdPayment() > 0 && $invoice->getIdInvoice() > 0) {
                        // payment-invoice
                        $payInvRs = new PaymentInvoiceRS();
                        $payInvRs->Amount->setNewVal($payResp->response->getAuthorizedAmount());
                        $payInvRs->Invoice_Id->setNewVal($invoice->getIdInvoice());
                        $payInvRs->Payment_Id->setNewVal($payResp->getIdPayment());
                        EditRS::insert($dbh, $payInvRs);

                    }

                    break;

                case CreditPayments::STATUS_DECLINED:

                    if ($payResp->getIdPayment() > 0 && $invoice->getIdInvoice() > 0) {
                        // payment-invoice
                        $payInvRs = new PaymentInvoiceRS();
                        $payInvRs->Amount->setNewVal($payResp->response->getAuthorizedAmount());
                        $payInvRs->Invoice_Id->setNewVal($invoice->getIdInvoice());
                        $payInvRs->Payment_Id->setNewVal($payResp->getIdPayment());
                        EditRS::insert($dbh, $payInvRs);

                    }

                    break;
            }


            $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());

            switch ($payResp->getStatus()) {

                case CreditPayments::STATUS_APPROVED:

                    $payResult->feePaymentAccepted($dbh, $uS, $payResp, $invoice);
                    $payResult->setDisplayMessage('Paid by Credit Card.  ');

                    if ($payResp->isPartialPayment()) {
                        $payResult->setDisplayMessage('** Partially Approved Amount: ' . number_format($payResp->response->getAuthorizedAmount(), 2) . ' (Remaining Balance Due: ' . number_format($invoice->getBalance(), 2) . ').  ');
                    }

                    break;

                case CreditPayments::STATUS_DECLINED:

                    $payResult->feePaymentRejected($dbh, $uS, $payResp, $invoice);

                    $msg = '** The Payment is Declined. **';
                    if ($payResp->response->getResponseMessage() != '') {
                        $msg .= 'Message: ' . $payResp->response->getResponseMessage();
                    }
                    $payResult->setDisplayMessage($msg);

                    break;

                default:

                    $payResult->setStatus(PaymentResult::ERROR);
                    $payResult->setDisplayMessage('** Payment Invalid or Error **  Message: ' . $payResp->response->getResponseMessage());
            }


        } else {
            // Initialiaze hosted payment
            try {

                $fwrder = $this->initHostedPayment($dbh, $invoice, $postbackUrl, $pmp->getManualKeyEntry(), $pmp->getCardHolderName());

                $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
                $payResult->setForwardHostedPayment($fwrder);
                $payResult->setDisplayMessage('Forward to Payment Page. ');

            } catch (Hk_Exception_Payment $hpx) {

                $payResult = new PaymentResult($invoice->getIdInvoice(), 0, 0);
                $payResult->setStatus(PaymentResult::ERROR);
                $payResult->setDisplayMessage($hpx->getMessage());
            }
        }

        return $payResult;
    }


    protected function initHostedPayment(\PDO $dbh, Invoice $invoice, $postbackUrl, $manualKey, $cardHolderName) {

        $uS = Session::getInstance();
        $dataArray = array();

        if ($invoice->getSoldToId() < 1 || $invoice->getIdGroup() < 1) {
            throw new Hk_Exception_Runtime("Invoice payor information is missing.  ");
        }

        $patInfo = $this->getPatientInfo($dbh, $invoice->getIdGroup());

        $data = array(
            'patientID' => $patInfo['idName'],
            'patientFirstName' => $patInfo['Name_First'],
            'patientLastName' => $patInfo['Name_Last'],
            'amount' => $invoice->getAmountToPay(),
            InstamedGateway::INVOICE_NUMBER => $invoice->getInvoiceNumber(),
            InstaMedCredentials::U_ID => $uS->uid,
            InstaMedCredentials::U_NAME => $uS->username,
            'creditCardKeyed' => ($manualKey ? 'true' : 'false'),
            'lightWeight' => 'true',
            'isReadOnly' => 'true',
            'preventCheck' => 'true',
            'preventCash' => 'true',
            'suppressReceipt' => 'true',
            'hideGuarantorID' => 'true',
            'responseActionType' => 'header',
            'cancelURL' => $this->buildPostbackUrl($postbackUrl, InstamedGateway::HCO_TRANS, InstamedGateway::POSTBACK_CANCEL),
            'confirmURL' => $this->buildPostbackUrl($postbackUrl, InstamedGateway::HCO_TRANS, InstamedGateway::POSTBACK_COMPLETE),
            'requestToken' => 'true',
            'RelayState' => $this->saleUrl,
        );

        if ($manualKey && $cardHolderName != '') {
            $data['cardHolderName'] = $cardHolderName;
        }

        $req = array_merge($data, $this->getCredentials()->toSSO());
        $headerResponse = $this->doHeaderRequest(http_build_query($req));

        unset($req[InstaMedCredentials::SEC_KEY]);

        // Save raw transaction in the db.
        try {
            self::logGwTx($dbh, $headerResponse->getResponseCode(), json_encode($req), json_encode($headerResponse->getResultArray()), 'HostedCoInit');
        } catch (Exception $ex) {
            // Do Nothing
        }

        if ($headerResponse->getToken() != '') {

            // Save ssoToken
            $ssoTknRs = new SsoTokenRS();
            $ssoTknRs->Amount->setNewVal($invoice->getAmountToPay());
            $ssoTknRs->InvoiceNumber->setNewVal($invoice->getInvoiceNumber());
            $ssoTknRs->Token->setNewVal($headerResponse->getToken());
            $ssoTknRs->idGroup->setNewVal($invoice->getIdGroup());
            $ssoTknRs->idName->setNewVal($invoice->getSoldToId());
            $ssoTknRs->State->setNewVal(WebHookStatus::Init);
            $ssoTknRs->CardHolderName->setNewVal($cardHolderName);

            EditRS::insert($dbh, $ssoTknRs);

            $uS->imtoken = $headerResponse->getToken();

            $payIds = array();
            if (isset($uS->paymentIds)) {
                $payIds = $uS->paymentIds;
            }

            $payIds[$headerResponse->getToken()] = $invoice->getIdInvoice();
            $uS->paymentIds = $payIds;

            $dataArray = array('inctx' => $headerResponse->getRelayState(), 'PaymentId' => $headerResponse->getToken());
        } else {

            // The initialization failed.
            unset($uS->imtoken);
            throw new Hk_Exception_Payment("Credit Payment Gateway Error: " . $headerResponse->getResponseMessage());
        }

        return $dataArray;
    }

    public function initCardOnFile(\PDO $dbh, $pageTitle, $idGuest, $idGroup, $manualKey, $cardHolderName, $postbackUrl) {

        $uS = Session::getInstance();
        $dataArray = array();

        $patInfo = $this->getPatientInfo($dbh, $idGroup);

        $data = array(
            'patientID' => $patInfo['idName'],
            'patientFirstName' => $patInfo['Name_First'],
            'patientLastName' => $patInfo['Name_Last'],
            InstaMedCredentials::U_ID => $uS->uid,
            InstaMedCredentials::U_NAME => $uS->username,
            'cardHolderName' => $cardHolderName,
            'lightWeight' => 'true',
            'creditCardKeyed' => ($manualKey ? 'true' : 'false'),
            'responseActionType' => 'header',
            'cancelURL' => $this->buildPostbackUrl($postbackUrl, InstamedGateway::COF_TRANS, InstamedGateway::POSTBACK_CANCEL),
            'confirmURL' => $this->buildPostbackUrl($postbackUrl, InstamedGateway::COF_TRANS, InstamedGateway::POSTBACK_COMPLETE),
            'requestToken' => 'true',
            'RelayState' => $this->cofUrl,
        );

        $allData = array_merge($data, $this->getCredentials()->toSSO());

        $headerResponse = $this->doHeaderRequest(http_build_query($allData));


        // remove password.
        unset($allData[InstaMedCredentials::SEC_KEY]);

        // Save raw transaction in the db.
        try {
            self::logGwTx($dbh, $headerResponse->getResponseCode(), json_encode($allData), json_encode($headerResponse->getResultArray()), 'CardInfoInit');
        } catch (Exception $ex) {
            // Do Nothing
        }

        // Verify response
        if ($headerResponse->getToken() != '') {

            // Save payment ID
            $ciq = "replace into card_id (idName, `idGroup`, `Transaction`, InvoiceNumber, CardID, Init_Date, Frequency, ResponseCode)"
                    . " values (" . $idGuest . " , " . $idGroup . ", '" . InstamedGateway::COF_TRANS . "', '', '" . $headerResponse->getToken() . "', now(), 'OneTime', " . $headerResponse->getResponseCode() . ")";

            $dbh->exec($ciq);

            $uS->imtoken = $headerResponse->getToken();
            $uS->cardHolderName = $cardHolderName;

            $dataArray = array('inctx' => $headerResponse->getRelayState(), 'CardId' => $headerResponse->getToken());
        } else {

            // The initialization failed.
            unset($uS->imtoken);
            unset($uS->cardHolderName);
            throw new Hk_Exception_Payment("Credit Payment Gateway Error: " . $headerResponse->getResponseMessage());
        }

        return $dataArray;
    }

    protected function sendVoid(\PDO $dbh, PaymentRS $payRs, Payment_AuthRS $pAuthRs, Invoice $invoice, $paymentNotes, $bid) {

        $uS = Session::getInstance();
        $dataArray['bid'] = $bid;

        $params = $this->getCredentials()->toCurl()
                . "&transactionType=CreditCard"
                . "&transactionAction=Void"
                . "&primaryCardPresentStatus=PresentManualKey"
                . "&primaryTransactionID=" . $pAuthRs->AcqRefData->getStoredVal();

        $curlRequest = new ImCurlRequest();

        $resp = $curlRequest->submit($params, $this->NvpUrl, $this->getCredentials()->id, $this->getCredentials()->password);

        $resp['InvoiceNumber'] = $invoice->getInvoiceNumber();
        $resp['Amount'] = $payRs->Amount->getStoredVal();
        $resp['cardBrand'] = $pAuthRs->Card_Type->getStoredVal();
        $resp['lastFourDigits'] = $pAuthRs->Acct_Number->getStoredVal();

        $curlResponse = new VerifyCurlVoidResponse($resp, MpTranType::Void);

        // Save raw transaction in the db.
        try {
            self::logGwTx($dbh, $curlResponse->getResponseCode(), $params, json_encode($curlResponse->getResultArray()), 'CreditVoidSaleToken');
        } catch (Exception $ex) {
            // Do Nothing
        }

        // Make a void response...
        $sr = new ImPaymentResponse($curlResponse, $payRs->idPayor->getStoredVal(), $invoice->getIdGroup(), $invoice->getInvoiceNumber(), $paymentNotes);

        // Record transaction
        try {
            $transRs = Transaction::recordTransaction($dbh, $sr, $this->gwName, TransType::Void, TransMethod::Token);
            $sr->setIdTrans($transRs->idTrans->getStoredVal());
        } catch (Exception $ex) {
            // do nothing
        }

        // Record payment
        $csResp = VoidReply::processReply($dbh, $sr, $uS->username, $payRs);


        switch ($csResp->getStatus()) {

            case CreditPayments::STATUS_APPROVED:

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizedAmount(), $uS->username);

                $csResp->idVisit = $invoice->getOrderNumber();
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createVoidMarkup($dbh, $csResp, $uS->siteName, $uS->sId)));
                $dataArray['success'] = 'Payment is void.  ';

                break;

            case CreditPayments::STATUS_DECLINED:

                $dataArray['warning'] = '** Void Declined. **  Message: ' . $csResp->response->getResponseMessage();

                break;

            default:

                $dataArray['warning'] = '** Void Invalid or Error. **  Message: ' . $csResp->getErrorMessage();
        }

        return $dataArray;
    }

    protected function sendReturn(\PDO $dbh, PaymentRS $payRs, Payment_AuthRS $pAuthRs, Invoice $invoice, $returnAmt, $bid) {

        $uS = Session::getInstance();

        $csResp = $this->processReturnPayment($dbh, $payRs, $pAuthRs->AcqRefData->getStoredVal(), $invoice, $returnAmt, $uS->username, '');

        $dataArray = array('bid' => $bid);

        switch ($csResp->getStatus()) {

            case CreditPayments::STATUS_APPROVED:

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizedAmount(), $uS->username);

                $csResp->idVisit = $invoice->getOrderNumber();
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createReturnMarkup($dbh, $csResp, $uS->siteName, $uS->sId)));

                break;

            case CreditPayments::STATUS_DECLINED:

                $dataArray['warning'] = $csResp->response->getResponseMessage();

                break;

            default:

                $dataArray['warning'] = $csResp->getErrorMessage();
        }

        return $dataArray;
    }

    Public function returnAmount(\PDO $dbh, Invoice $invoice, $rtnToken, $paymentNotes = '') {

        $uS = Session::getInstance();

        // Find a credit payment
        $idGroup = intval($invoice->getIdGroup(), 10);
        $amount = abs($invoice->getAmount());
        $idToken = intval($rtnToken, 10);

        $stmt = $dbh->query("select pa.AcqRefData
from payment p join payment_auth pa on p.idPayment = pa.idPayment
	join payment_invoice pi on p.idPayment = pi.Payment_Id
    join invoice i on pi.Invoice_Id = i.idInvoice
where p.Status_Code = 's' and p.Is_Refund = 0 and p.idToken = $idToken and i.idGroup = $idGroup and pa.Approved_Amount > $amount"
                . " order by pa.Approved_Amount");

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) == 0) {

            $payResult = new ReturnResult($invoice->getIdInvoice(), 0, 0);
            $payResult->setStatus(PaymentResult::ERROR);
            $payResult->setDisplayMessage('** An appropriate payment was not found for this return amount: ' . $amount . ' **');
            return $payResult;
        }


        $csResp = $this->processReturnPayment($dbh, NULL, $rows[0]['AcqRefData'], $invoice, $amount, $uS->username, $paymentNotes);

        $payResult = new ReturnResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());

        switch ($csResp->getStatus()) {

            case CreditPayments::STATUS_APPROVED:

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizedAmount(), $uS->username);

                $payResult->feePaymentAccepted($dbh, $uS, $csResp, $invoice);
                $payResult->setDisplayMessage('Amount Returned by Credit Card.  ');

                break;

            case CreditPayments::STATUS_DECLINED:

                $payResult->feePaymentRejected($dbh, $uS, $csResp, $invoice);

                $msg = '** The Return is Declined. **';
                if ($csResp->response->getResponseMessage() != '') {
                    $msg .= 'Message: ' . $csResp->response->getResponseMessage();
                }
                $payResult->setDisplayMessage($msg);

                break;

            default:

                $payResult->setStatus(PaymentResult::ERROR);
                $payResult->setDisplayMessage('**  Error Message: ' . $csResp->response->getResponseMessage());
        }

        return $payResult;
    }

    /**
     *
     * @param \PDO $dbh
     * @param PaymentRS $payRs
     * @param string $paymentTransId
     * @param Invoice $invoice
     * @param float $returnAmt
     * @param string $userName
     * @return type
     */
    protected function processReturnPayment(\PDO $dbh, $payRs, $paymentTransId, Invoice $invoice, $returnAmt, $userName, $paymentNotes) {

        $params = $this->getCredentials()->toCurl()
                . "&transactionType=CreditCard"
                . "&transactionAction=SimpleRefund"
                . "&primaryCardPresentStatus=PresentManualKey"
                . "&primaryTransactionID=" . $paymentTransId
                . "&amount=" . number_format($returnAmt, 2);

        $curlRequest = new ImCurlRequest();

        $resp = $curlRequest->submit($params, $this->NvpUrl, $this->getCredentials()->id, $this->getCredentials()->password);

        $resp['InvoiceNumber'] = $invoice->getInvoiceNumber();
        $resp['Amount'] = $returnAmt;

        $curlResponse = new VerifyCurlReturnResponse($resp, MpTranType::ReturnSale);

        // Save raw transaction in the db.
        try {
            self::logGwTx($dbh, $curlResponse->getResponseCode(), $params, json_encode($curlResponse->getResultArray()), 'CreditReturnToken');
        } catch (Exception $ex) {
            // Do Nothing
        }

        // Make a return response...
        $sr = new ImReturnResponse($curlResponse, $invoice->getSoldToId(), $invoice->getIdGroup(), $invoice->getInvoiceNumber(), $paymentNotes);

        // Record transaction
        try {
            $transRs = Transaction::recordTransaction($dbh, $sr, $this->gwName, TransType::Retrn, TransMethod::Token);
            $sr->setIdTrans($transRs->idTrans->getStoredVal());
        } catch (Exception $ex) {
            // do nothing
        }

        // Record return
        return ReturnReply::processReply($dbh, $sr, $userName, $payRs);

    }

    public function processHostedReply(\PDO $dbh, $post, $ssoToken, $idInv, $payNotes, $userName = '') {

        $uS = Session::getInstance();
        $transType = '';
        $transResult = '';
        $payResult = NULL;

        if (isset($post[InstamedGateway::INSTAMED_TRANS_VAR])) {
            $transType = filter_var($post[InstamedGateway::INSTAMED_TRANS_VAR], FILTER_SANITIZE_STRING);
        }

        // Not a payment return so get out.
        if ($transType == '') {
            return $payResult;
        }

        if (isset($post[InstamedGateway::INSTAMED_RESULT_VAR])) {
            $transResult = filter_var($post[InstamedGateway::INSTAMED_RESULT_VAR], FILTER_SANITIZE_STRING);
        }

        if ($transResult == InstamedGateway::POSTBACK_CANCEL) {

            $payResult = new PaymentResult($idInv, 0, 0);
            $payResult->setDisplayMessage('User Canceled.');

            return $payResult;

        } else if ($transResult != InstamedGateway::POSTBACK_COMPLETE) {

            $payResult = new PaymentResult($idInv, 0, 0);
            $payResult->setDisplayMessage('Undefined Result: ' . $transResult);

            return $payResult;
        }


        // Finally, process the transaction
        if ($transType == InstamedGateway::HCO_TRANS) {

            try {

                $payResult = $this->completeHostedPayment($dbh, $idInv, $ssoToken, $payNotes);

            } catch (Hk_Exception_Payment $hex) {

                $payResult = new PaymentResult($idInv, 0, 0);
                $payResult->setStatus(PaymentResult::ERROR);
                $payResult->setDisplayMessage($hex->getMessage());
            }

        } else if ($transType == InstamedGateway::COF_TRANS) {

            $payResult = $this->completeCof($dbh, $ssoToken, $uS->cardHolderName);
        }

        return $payResult;
    }

    public function processWebhook(\PDO $dbh, $data, $payNotes, $userName) {

        $webhookResp = new WebhookResponse($data);
        $error = FALSE;

        if ($webhookResp->getSsoToken() == '') {
            return FALSE;
        }

        // Check DB for record
        $ssoTknRs = new SsoTokenRS();
        $ssoTknRs->Token->setStoredVal($webhookResp->getSsoToken());

        $tokenRows = EditRS::select($dbh, $ssoTknRs, array($ssoTknRs->Token));

        if (count($tokenRows) < 1) {
            // Not an error that webhook can do something about, so return No Error.
            return FALSE;
        }

        EditRS::loadRow($tokenRows[0], $ssoTknRs);

        if ($webhookResp->getTranType() == MpTranType::Sale) {

            if ($webhookResp->getPartialPaymentAmount() > 0) {
                $isPartialPayment = TRUE;
            } else {
                $isPartialPayment = FALSE;
            }

            // Make a sale response...
            $sr = new ImPaymentResponse($webhookResp, $ssoTknRs->idName->getStoredVal(), $ssoTknRs->idGroup->getStoredVal(), $ssoTknRs->InvoiceNumber->getStoredVal(), $payNotes, $isPartialPayment);

            // Record transaction
            try {
                $transRs = Transaction::recordTransaction($dbh, $sr, $this->gwName, TransType::Sale, TransMethod::Webhook);
                $sr->setIdTrans($transRs->idTrans->getStoredVal());
            } catch (Exception $ex) {
                // do nothing
            }

            // record payment
            $payResp = SaleReply::processReply($dbh, $sr, $userName);

            $invoice = new Invoice($dbh, $payResp->getInvoiceNumber());


            // Analyze the result
            switch ($payResp->getStatus()) {

                case CreditPayments::STATUS_APPROVED:

                    // Update invoice
                    $invoice->updateInvoiceBalance($dbh, $payResp->response->getAuthorizedAmount(), $userName);

                    if ($payResp->getIdPayment() > 0 && $invoice->getIdInvoice() > 0) {
                        // payment-invoice
                        $payInvRs = new PaymentInvoiceRS();
                        $payInvRs->Amount->setNewVal($payResp->response->getAuthorizedAmount());
                        $payInvRs->Invoice_Id->setNewVal($invoice->getIdInvoice());
                        $payInvRs->Payment_Id->setNewVal($payResp->getIdPayment());
                        EditRS::insert($dbh, $payInvRs);

                        $error = FALSE;
                    }

                    break;

                case CreditPayments::STATUS_DECLINED:

                    if ($payResp->getIdPayment() > 0 && $invoice->getIdInvoice() > 0) {
                        // payment-invoice
                        $payInvRs = new PaymentInvoiceRS();
                        $payInvRs->Amount->setNewVal($payResp->response->getAuthorizedAmount());
                        $payInvRs->Invoice_Id->setNewVal($invoice->getIdInvoice());
                        $payInvRs->Payment_Id->setNewVal($payResp->getIdPayment());
                        EditRS::insert($dbh, $payInvRs);

                        $error = FALSE;
                    }

                    break;

                default:
                    $ssoTknRs->State->setNewVal(WebHookStatus::Error);
                    $ssoTknRs->idPaymentAuth->setNewVal($payResp->idPaymentAuth);
                    EditRS::update($dbh, $ssoTknRs, array($ssoTknRs->Token));
                    $error = FALSE;
            }

            if ($error === FALSE) {
                $ssoTknRs->State->setNewVal(WebHookStatus::Complete);
                $ssoTknRs->idPaymentAuth->setNewVal($payResp->idPaymentAuth);
                EditRS::update($dbh, $ssoTknRs, array($ssoTknRs->Token));
            }
        }

        return $error;
    }

    protected function completeCof(\PDO $dbh, $ssoToken, $cardHolderName) {

        $cidInfo = PaymentSvcs::getInfoFromCardId($dbh, $ssoToken);

        if (count($cidInfo) < 1) {

            $payResult = new PaymentResult(0, 0, 0);
            $payResult->setDisplayMessage('');

            return $payResult;
        }

        //get transaction details
        $params = $this->getCredentials()->toCurl()
                . "&transactionAction=ViewReceipt"
                . "&requestToken=false"
                . "&singleSignOnToken=" . $ssoToken;

        $curl = new ImCurlRequest();
        $resp = $curl->submit($params, $this->NvpUrl, $this->getCredentials()->id, $this->getCredentials()->password);

        $resp['InvoiceNumber'] = 0;
        $resp['Amount'] = 0;

        if (isset($resp['cardHolderName']) === FALSE || $resp['cardHolderName'] == '') {
            $resp['cardHolderName'] = $cardHolderName;
        }

        $response = new VerifyCurlCofResponse($resp);

        // Save raw transaction in the db.
        try {
            self::logGwTx($dbh, $response->getResponseCode(), $params, json_encode($response->getResultArray()), 'CardInfoVerify');
        } catch (Exception $ex) {
            // Do Nothing
        }

        $vr = new ImCofResponse($response, $cidInfo['idName'], $cidInfo['idGroup']);

        // save token
        CreditToken::storeToken($dbh, $vr->idRegistration, $vr->idPayor, $response);

        return new CofResult($vr->response->getResponseMessage(), $vr->getStatus(), $vr->idPayor, $vr->idRegistration);
    }

    protected function completeHostedPayment(\PDO $dbh, $idInv, $ssoToken, $paymentNotes) {

        $uS = Session::getInstance();
        $partlyApproved = FALSE;

        //Wait for web hook
        $ssoTknRs = $this->waitForWebhook($dbh, $ssoToken, 5);

        // Analyze web hook results.
        if ($ssoTknRs->State->getStoredVal() == WebHookStatus::Init) {
            // Webhook has not shown up yet.

            $payResult = new PaymentResult($idInv, 0, 0);
            $payResult->setStatus(PaymentResult::ERROR);
            $payResult->setDisplayMessage('** Web Hook is delayed *** ');
            return $payResult;

        } else if ($ssoTknRs->State->getStoredVal() == WebHookStatus::Error) {
            // HHK's webhook processing failed..

            $payResult = new PaymentResult($idInv, 0, 0);
            $payResult->setStatus(PaymentResult::ERROR);
            $payResult->setDisplayMessage('** Payment processing error in HHK **');
            return $payResult;
        }


        // Get PaymentAuth record.
        $pAuthRs = new Payment_AuthRS();
        $pAuthRs->idPayment_auth->setStoredVal($ssoTknRs->idPaymentAuth->getStoredVal());
        $pauthRows = EditRS::select($dbh, $pAuthRs, array($pAuthRs->idPayment_auth));

        if (count($pauthRows) < 1) {
            throw new Hk_Exception_Payment('Charge paymentAuth record not found.');
        }

        EditRS::loadRow($pauthRows[0], $pAuthRs);

        // Get associated payment record.
        $payRs = new PaymentRS();
        $payRs->idPayment->setStoredVal($pAuthRs->idPayment->getStoredVal());
        $payRows = EditRS::select($dbh, $payRs, array($payRs->idPayment));

        if (count($payRows) < 1) {
            throw new Hk_Exception_Payment('Payment record not found.');
        }

        EditRS::loadRow($payRows[0], $payRs);

        // Update Payment notes
        if ($paymentNotes != '' && $paymentNotes != $payRs->Notes->getStoredVal()) {

            $payRs->Notes->setNewVal($paymentNotes);
            EditRS::update($dbh, $payRs, array($payRs->idPayment));
            EditRS::updateStoredVals($payRs);
        }

        // get The guest token recordl.
        $gTRs = new Guest_TokenRS();
        $gTRs->idGuest_token->setStoredVal($payRs->idToken->getStoredVal());
        $guestTkns = EditRS::select($dbh, $gTRs, array($gTRs->idGuest_token));

        if (count($guestTkns) > 0) {
            EditRS::loadRow($guestTkns[0], $gTRs);
        }

        // get the name if we need it.
        if ($pAuthRs->Cardholder_Name->getStoredVal() == '' && $ssoTknRs->CardHolderName->getStoredVal() != '') {

            $pAuthRs->Cardholder_Name->setNewVal($ssoTknRs->CardHolderName->getStoredVal());
            EditRS::update($dbh, $pAuthRs, array($pAuthRs->idPayment_auth));
            EditRS::updateStoredVals($pAuthRs);
        }

        if ($gTRs->CardHolderName->getStoredVal() == '' && $ssoTknRs->CardHolderName->getStoredVal() != '') {
            $gTRs->CardHolderName->setNewVal($ssoTknRs->CardHolderName->getStoredVal());
            EditRS::update($dbh, $gTRs, array($gTRs->idGuest_token));
            EditRS::updateStoredVals($gTRs);
        }

        // Partially approved?
        if ($pAuthRs->PartialPayment->getStoredVal() > 0) {
            $partlyApproved = TRUE;
        }

        $gwResp = new StandInGwResponse($pAuthRs, $gTRs->OperatorID->getStoredVal(), $pAuthRs->Cardholder_Name->getStoredVal(), $gTRs->ExpDate->getStoredVal(), $gTRs->Token->getStoredVal(), $idInv, $payRs->Amount->getStoredVal());
        $payResp = new ImPaymentResponse($gwResp, $ssoTknRs->idName->getStoredVal(), $ssoTknRs->idGroup->getStoredVal(), $ssoTknRs->InvoiceNumber->getStoredVal(), $paymentNotes, $partlyApproved);

        $payResp->setPaymentDate($payRs->Payment_Date->getStoredVal());

        $invoice = new Invoice($dbh);
        $invoice->loadInvoice($dbh, $idInv);

        $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());

        switch ($payResp->getStatus()) {

            case CreditPayments::STATUS_APPROVED:

                $payResult->feePaymentAccepted($dbh, $uS, $payResp, $invoice);
                $payResult->setDisplayMessage('Paid by Credit Card.  ');

                if ($payResp->isPartialPayment()) {
                    $payResult->setDisplayMessage('** Partially Approved Amount: ' . number_format($payResp->response->getAuthorizedAmount(), 2) . ' (Remaining Balance Due: ' . number_format($invoice->getBalance(), 2) . ').  ');
                }

                break;

            case CreditPayments::STATUS_DECLINED:

                $payResult->feePaymentRejected($dbh, $uS, $payResp, $invoice);

                $msg = '** The Payment is Declined. **';
                if ($payResp->response->getResponseMessage() != '') {
                    $msg .= 'Message: ' . $payResp->response->getResponseMessage();
                }
                $payResult->setDisplayMessage($msg);

                break;

            default:

                $payResult->setStatus(PaymentResult::ERROR);
                $payResult->setDisplayMessage('** Payment Invalid or Error **  Message: ' . $payResp->response->getResponseMessage());
        }

        return $payResult;
    }

    protected function loadGateway(\PDO $dbh) {

        $gwRs = new InstamedGatewayRS();
        $gwRs->cc_name->setStoredVal($this->gwType);
        $gwRs->Gateway_Name->setStoredVal($this->getGatewayName());

        $rows = EditRS::select($dbh, $gwRs, array($gwRs->Gateway_Name, $gwRs->cc_name));

        if (count($rows) == 1) {

            $gwRs = new InstamedGatewayRS();
            EditRS::loadRow($rows[0], $gwRs);

            $this->ssoUrl = $gwRs->providersSso_Url->getStoredVal();
            $this->soapUrl = '';  //$gwRs->soap_Url->getStoredVal();
            $this->NvpUrl = $gwRs->nvp_Url->getStoredVal();

            $this->useAVS = filter_var($gwRs->Use_AVS_Flag->getStoredVal(), FILTER_VALIDATE_BOOLEAN);
            $this->useCVV = filter_var($gwRs->Use_Ccv_Flag->getStoredVal(), FILTER_VALIDATE_BOOLEAN);
        } else {
            throw new Hk_Exception_Runtime('The credit card payment gateway is not found: ' . $this->getGatewayName() . '.  ');
        }

        return $gwRs;
    }

    protected function setCredentials($gwRs) {

        $this->credentials = new InstaMedCredentials($gwRs);

        $this->saleTokenUrl = 'https://connect.instamed.com/payment/NVP.aspx?';
        $this->saleUrl = 'https://online.instamed.com/providers/Form/PatientPayments/NewPaymentSimpleSSO';
        $this->cofUrl = 'https://online.instamed.com/providers/Form/PatientPayments/NewPaymentPlanSimpleSSO';
        $this->voidUrl = 'https://online.instamed.com/providers/Form/PatientPayments/VoidPaymentSSO?';
        $this->returnUrl = 'https://online.instamed.com/providers/Form/PatientPayments/RefundPaymentSSO?';
    }

    protected function doHeaderRequest($data) {

        //Create HTTP stream context
        $context = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded,' . '\r\n' .
                'Content-Length:' . strlen($data) . '\r\n' .
                'Expect: 100-continue,' . '\r\n' .
                'Connection: Keep-Alive,' . '\r\n',
                'content' => $data
            )
        ));

        $headers = get_headers($this->ssoUrl, 1, $context);

        return new HeaderResponse($headers);
    }

    protected function buildPostbackUrl($postbackPageUrl, $transVar, $resultVar) {

        $parms = array();
        $parts = parse_url($postbackPageUrl);

        $secure = new SecurityComponent();
        $houseUrl = $secure->getSiteURL();

        if ($houseUrl == '') {
            throw new Hk_Exception_Runtime("The site/house URL is missing.  ");
        }

        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $parms);
        }

        if (isset($parts['path']) && $parts['path'] !== '') {
            $parms[InstamedGateway::TRANSFER_POSTBACK_PAGE_VAR] = $parts['path'];
        }

        $parms[InstamedGateway::INSTAMED_TRANS_VAR] = $transVar;
        $parms[InstamedGateway::INSTAMED_RESULT_VAR] = $resultVar;

        $queryStr = encryptMessage(http_build_query($parms));

        return $houseUrl . InstamedGateway::TRANSFER_URL . '?' . InstamedGateway::TRANSFER_VAR . '=' . $queryStr;
    }

    protected function getPatientInfo(\PDO $dbh, $idRegistration) {

        $idReg = intval($idRegistration);

        $stmt = $dbh->query("Select n.idName, n.Name_First, n.Name_Last
from registration r join psg p on r.idPsg = p.idPsg
	join name n on p.idPatient = n.idName
where r.idRegistration =" . $idReg);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            return $rows[0];
        }

        return array();
    }

    /**
     *
     * @param \PDO $dbh
     * @param string $ssoToken
     * @param int $delaySeconds
     * @return SsoTokenRS
     */
    protected function waitForWebhook(\PDO $dbh, $ssoToken, $delaySeconds = 5) {

        $ssoTknRs = NULL;
        $slept = 0;

        while ($slept < $delaySeconds) {

            // Check DB for record
            $ssoTknRs = new SsoTokenRS();
            $ssoTknRs->Token->setStoredVal($ssoToken);
            $tokenRow = EditRS::select($dbh, $ssoTknRs, array($ssoTknRs->Token));
            EditRS::loadRow($tokenRow[0], $ssoTknRs);

            if (count($tokenRow) > 0 && $tokenRow[0]['State'] != WebHookStatus::Init) {

                $slept = $delaySeconds + 2;

            } else {
                $slept++;
                sleep(1);
            }
        }

        return $ssoTknRs;
    }

    public function getPaymentResponseObj(iGatewayResponse $vcr, $idPayor, $idGroup, $invoiceNumber, $idToken = 0, $payNotes = '') {
        return new ImPaymentResponse($vcr, $idPayor, $idGroup, $invoiceNumber, $payNotes);
    }

    public function getCofResponseObj(iGatewayResponse $vcr, $idPayor, $idGroup) {
        return new ImCofResponse($vcr, $idPayor, $idGroup);
    }

    public function createEditMarkup(\PDO $dbh, $resultMessage = '') {

        $gwRs = new InstamedGatewayRS();
        $gwRs->Gateway_Name->setStoredVal($this->getGatewayName());
        $rows = EditRS::select($dbh, $gwRs, array($gwRs->Gateway_Name));

        $tbl = new HTMLTable();

        foreach ($rows as $r) {

            $gwRs = new InstamedGatewayRS();
            EditRS::loadRow($r, $gwRs);

            $indx = $gwRs->idcc_gateway->getStoredVal();

            $tbl->addBodyTr(
                    HTMLTable::makeTh('Name', array('style' => 'border-top:2px solid black;', 'class' => 'tdlabel'))
                    . HTMLTable::makeTd($gwRs->cc_name->getStoredVal(), array('style' => 'border-top:2px solid black;'))
            );

            $tbl->addBodyTr(
                    HTMLTable::makeTh('Account Id', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->account_Id->getStoredVal(), array('name' => $indx . '_txtaid', 'size' => '80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Password', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->password->getStoredVal(), array('name' => $indx . '_txtpwd', 'size' => '80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('SSO Password', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->security_Key->getStoredVal(), array('name' => $indx . '_txtsk', 'size' => '80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('SSO Alias', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->sso_Alias->getStoredVal(), array('name' => $indx . '_txtsalias', 'size' => '80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Merchant Id', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->merchant_Id->getStoredVal(), array('name' => $indx . '_txtuid', 'size' => '80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Store Id', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->store_Id->getStoredVal(), array('name' => $indx . '_txtuname', 'size' => '80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Terminal Id', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->terminal_Id->getStoredVal(), array('name' => $indx . '_txttremId', 'size' => '80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Workstation Id', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->WorkStation_Id->getStoredVal(), array('name' => $indx . '_txtwsId', 'size' => '80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('SSO URL', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->providersSso_Url->getStoredVal(), array('name' => $indx . '_txtpurl', 'size' => '90')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('NVP URL', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->nvp_Url->getStoredVal(), array('name' => $indx . '_txtnvpurl', 'size' => '90')))
            );
        }

        if ($resultMessage != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($resultMessage, array('colspan' => '2', 'style' => 'font-weight:bold;')));
        }

        return $tbl->generateMarkup();
    }

    public function SaveEditMarkup(\PDO $dbh, $post) {

        $msg = '';
        $ccRs = new InstamedGatewayRS();
        $ccRs->Gateway_Name->setStoredVal($this->getGatewayName());
        $rows = EditRS::select($dbh, $ccRs, array($ccRs->Gateway_Name));

        foreach ($rows as $r) {

            EditRS::loadRow($r, $ccRs);

            $indx = $ccRs->idcc_gateway->getStoredVal();

            if (isset($post[$indx . '_txtaid'])) {
                $ccRs->account_Id->setNewVal(filter_var($post[$indx . '_txtaid'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtsalias'])) {
                $ccRs->sso_Alias->setNewVal(filter_var($post[$indx . '_txtsalias'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtuid'])) {
                $ccRs->merchant_Id->setNewVal(filter_var($post[$indx . '_txtuid'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtuname'])) {
                $ccRs->store_Id->setNewVal(filter_var($post[$indx . '_txtuname'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txttremId'])) {
                $ccRs->terminal_Id->setNewVal(filter_var($post[$indx . '_txttremId'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtwsId'])) {
                $ccRs->WorkStation_Id->setNewVal(filter_var($post[$indx . '_txtwsId'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtpurl'])) {
                $ccRs->providersSso_Url->setNewVal(filter_var($post[$indx . '_txtpurl'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtnvpurl'])) {
                $ccRs->nvp_Url->setNewVal(filter_var($post[$indx . '_txtnvpurl'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtsk'])) {

                $pw = filter_var($post[$indx . '_txtsk'], FILTER_SANITIZE_STRING);

                if ($pw != '' && $ccRs->security_Key->getStoredVal() != $pw) {
                    $ccRs->security_Key->setNewVal(encryptMessage($pw));
                } else if ($pw == '') {
                    $ccRs->security_Key->setNewVal('');
                }
            }
            if (isset($post[$indx . '_txtpwd'])) {

                $pw = filter_var($post[$indx . '_txtpwd'], FILTER_SANITIZE_STRING);

                if ($pw != '' && $ccRs->password->getStoredVal() != $pw) {
                    $ccRs->password->setNewVal(encryptMessage($pw));
                } else if ($pw == '') {
                    $ccRs->password->setNewVal('');
                }
            }

            $ccRs->Use_Ccv_Flag->setNewVal(0);
            $ccRs->Use_AVS_Flag->setNewVal(0);

            // Save record.
            $num = EditRS::update($dbh, $ccRs, array($ccRs->Gateway_Name, $ccRs->idcc_gateway));

            if ($num > 0) {
                $msg .= HTMLContainer::generateMarkup('p', $ccRs->Gateway_Name->getStoredVal() . " " . $ccRs->cc_name->getStoredVal() . " - Payment Credentials Updated.  ");
            } else {
                $msg .= HTMLContainer::generateMarkup('p', $ccRs->Gateway_Name->getStoredVal() . " " . $ccRs->cc_name->getStoredVal() . " - No changes detected.  ");
            }
        }

        return $msg;
    }

}

class InstaMedCredentials {

    // NVP names
    const SEC_KEY = 'securityKey';
    const ACCT_ID = 'accountID';
    const ID = 'id';
    const SSO_ALIAS = 'ssoAlias';
    const MERCHANT_ID = 'merchantId';
    const STORE_ID = 'storeId';
    const TERMINAL_ID = 'terminalID';
    const WORKSTATION_ID = 'additionalInfo6';
    const U_NAME = 'userName';
    const U_ID = 'userID';

    public $merchantId;
    public $storeId;
    public $password;
    public $id;

    protected $securityKey;
    protected $accountID;
    protected $terminalId;
    protected $workstationId;
    protected $ssoAlias;


    public function __construct(InstamedGatewayRS $gwRs) {

        $this->accountID = $gwRs->account_Id->getStoredVal();
        $this->securityKey = $gwRs->security_Key->getStoredVal();
        $this->ssoAlias = $gwRs->sso_Alias->getStoredVal();
        $this->merchantId = $gwRs->merchant_Id->getStoredVal();
        $this->storeId = $gwRs->store_Id->getStoredVal();
        $this->terminalId = $gwRs->terminal_Id->getStoredVal();
        $this->workstationId = $gwRs->WorkStation_Id->getStoredVal();
        $this->password = decryptMessage($gwRs->password->getStoredVal());

        $parts = explode('@', $this->accountID);
        $this->id = $parts[0];
    }

    public function toSSO() {

        return array(
            InstaMedCredentials::ACCT_ID => $this->accountID,
            InstaMedCredentials::SEC_KEY => decryptMessage($this->securityKey),
            InstaMedCredentials::SSO_ALIAS => $this->ssoAlias,
            InstaMedCredentials::ID => $this->id,
            InstaMedCredentials::WORKSTATION_ID => $this->workstationId,
        );
    }

    public function toCurl($useWorkstationId = TRUE) {

        return
                InstaMedCredentials::MERCHANT_ID . '=' . $this->merchantId
                . '&' . InstaMedCredentials::STORE_ID . '=' . $this->storeId
                . '&' . InstaMedCredentials::TERMINAL_ID . '=' . $this->terminalId
                . ($useWorkstationId ? '&' . InstaMedCredentials::WORKSTATION_ID . '=' . $this->workstationId : '');
    }

    public function toSOAP() {

        return array(
            InstaMedCredentials::ACCT_ID => $this->accountID,
            'password' => decryptMessage($this->securityKey),
            'alias' => $this->ssoAlias,
        );
    }

}

