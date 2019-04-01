<?php

/*
 * The MIT License
 *
 * Copyright 2019 Eric.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
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
    protected $cofUrl;
    protected $returnUrl;
    protected $voidUrl;

    protected function getPaymentMethod() {
        return PaymentMethod::Charge;
    }

    public function creditSale(\PDO $dbh, $pmp, $invoice, $postbackUrl) {

        $uS = Session::getInstance();

        // Initialiaze hosted payment
        try {

            $fwrder = $this->initHostedPayment($dbh, $invoice, $postbackUrl);

            $payIds = array();
            if (isset($uS->paymentIds)) {
                $payIds = $uS->paymentIds;
            }
            $payIds[$fwrder['PaymentId']] = $invoice->getIdInvoice();
            $uS->paymentIds = $payIds;

            $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
            $payResult->setForwardHostedPayment($fwrder);
            $payResult->setDisplayMessage('Forward to Payment Page. ');
        } catch (Hk_Exception_Payment $hpx) {

            $payResult = new PaymentResult($invoice->getIdInvoice(), 0, 0);
            $payResult->setStatus(PaymentResult::ERROR);
            $payResult->setDisplayMessage($hpx->getMessage());
        }

        return $payResult;
    }

    public function voidSale(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, $paymentNotes, $bid) {

        // Find hte detail record.
        $stmt = $dbh->query("Select * from payment_auth where idPayment = " . $payRs->idPayment->getStoredVal() . " order by idPayment_auth");
        $arows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($arows) < 1) {
            return array('warning' => 'Payment Detail record not found.  Unable to Void this purchase. ', 'bid' => $bid);
        }

        $pAuthRs = new Payment_AuthRS();
        EditRS::loadRow(array_pop($arows), $pAuthRs);

        if ($pAuthRs->Status_Code->getStoredVal() == PaymentStatusCode::Paid) {
            return $this->sendVoid($dbh, $payRs, $pAuthRs, $invoice, $paymentNotes, $bid);
        }

        return array('warning' => 'Payment is ineligable for void.  ', 'bid' => $bid);
    }

    public function reverseSale(\PDO $dbh, PaymentRS $payRs, Invoice $invoice, $bid, $paymentNotes) {

        return $this->voidSale($dbh, $invoice, $payRs, $paymentNotes, $bid);
    }

    public function returnSale(\PDO $dbh, PaymentRS $payRs, Invoice $invoice, $returnAmt, $bid) {

        // Find hte detail record.
        $stmt = $dbh->query("Select * from payment_auth where idPayment = " . $payRs->idPayment->getStoredVal() . " order by idPayment_auth");
        $arows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($arows) < 1) {
            return array('warning' => 'Payment Detail record not found.  Unable to Return. ', 'bid' => $bid);
        }

        $pAuthRs = new Payment_AuthRS();
        EditRS::loadRow(array_pop($arows), $pAuthRs);

        if ($pAuthRs->Status_Code->getStoredVal() == PaymentStatusCode::Paid) {

            // Determine amount to return
            if ($returnAmt == 0) {
                $returnAmt = $pAuthRs->Approved_Amount->getStoredVal();
            } else if ($returnAmt > $pAuthRs->Approved_Amount->getStoredVal()) {
                return array('warning' => 'Return Failed.  Return amount is larger than the original purchase amount.  ', 'bid' => $bid);
            }

            return $this->sendReturn($dbh, $payRs, $pAuthRs, $invoice, $returnAmt, $bid);
        }

        return array('warning' => 'This Payment is ineligable for Return. ', 'bid' => $bid);
    }

    protected function initHostedPayment(\PDO $dbh, Invoice $invoice, $postbackUrl) {

        $uS = Session::getInstance();
        $dataArray = array();

        if ($invoice->getSoldToId() < 1 || $invoice->getIdGroup() < 1) {
            throw new Hk_Exception_Runtime("Card Holder information is missing.  ");
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
            'incontext' => 'true',
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

            $rowCount = EditRS::insert($dbh, $ssoTknRs);

            if (count($rowCount) != 1) {
                throw new Hk_Exception_Payment("Database Insert error. ");
            }

            $uS->imtoken = $headerResponse->getToken();

            $dataArray = array('inctx' => $headerResponse->getRelayState(), 'PaymentId' => $headerResponse->getToken());
        } else {

            // The initialization failed.
            unset($uS->imtoken);
            throw new Hk_Exception_Payment("Credit Payment Gateway Error: " . $headerResponse->getResponseMessage());
        }

        return $dataArray;
    }

    public function initCardOnFile(\PDO $dbh, $pageTitle, $idGuest, $idGroup, $cardHolderName, $postbackUrl) {

        $uS = Session::getInstance();
        $dataArray = array();

        $patInfo = $this->getPatientInfo($dbh, $idGroup);

        $data = array(
            'patientID' => $patInfo['idName'],
            'patientFirstName' => $patInfo['Name_First'],
            'patientLastName' => $patInfo['Name_Last'],
            InstaMedCredentials::U_ID => $uS->uid,
            InstaMedCredentials::U_NAME => $uS->username,
            'lightWeight' => 'true',
            'incontext' => 'true',
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

            $dataArray = array('inctx' => $headerResponse->getRelayState(), 'CardId' => $headerResponse->getToken());
        } else {

            // The initialization failed.
            unset($uS->imtoken);
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

        $resp = $curlRequest->submit($params, $this->NvpUrl);

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
        $reply = '';

        $params = $this->getCredentials()->toCurl()
                . "&transactionType=CreditCard"
                . "&transactionAction=SimpleRefund"
                . "&primaryCardPresentStatus=PresentManualKey"
                . "&primaryTransactionID=" . $pAuthRs->AcqRefData->getStoredVal()
                . "&amount=" . number_format($returnAmt, 2);

        $curlRequest = new ImCurlRequest();

        $resp = $curlRequest->submit($params, $this->NvpUrl);

        $resp['InvoiceNumber'] = $invoice->getInvoiceNumber();
        $resp['Amount'] = $payRs->Amount->getStoredVal();

        $curlResponse = new VerifyCurlResponse($resp, MpTranType::ReturnSale);

        // Save raw transaction in the db.
        try {
            self::logGwTx($dbh, $curlResponse->getResponseCode(), $params, json_encode($curlResponse->getResultArray()), 'CreditReturnToken');
        } catch (Exception $ex) {
            // Do Nothing
        }

        // Make a return response...
        $sr = new ImPaymentResponse($curlResponse, $payRs->idPayor->getStoredVal(), $invoice->getIdGroup(), $invoice->getInvoiceNumber(), '');

        // Record transaction
        try {
            $transRs = Transaction::recordTransaction($dbh, $sr, $this->gwName, TransType::Retrn, TransMethod::Token);
            $sr->setIdTrans($transRs->idTrans->getStoredVal());
        } catch (Exception $ex) {
            // do nothing
        }

        // Record return
        $csResp = ReturnReply::processReply($dbh, $sr, $uS->username, $payRs);


        $dataArray = array('bid' => $bid);

        switch ($csResp->getStatus()) {

            case CreditPayments::STATUS_APPROVED:

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizedAmount(), $uS->username);

                $reply .= 'Payment is Returned.  ';
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

    public function processHostedReply(\PDO $dbh, $post, $ssoToken, $idInv, $payNotes, $userName = '') {

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

        if ($ssoToken === NULL || $ssoToken == '') {

            $payResult = new PaymentResult($idInv, 0, 0);
            $payResult->setDisplayMessage('Missing Token. ');

            return $payResult;
        }

        // Finally, process the transaction
        if ($transType == InstamedGateway::HCO_TRANS) {

            try {
                $payResult = $this->completeHostedPayment($dbh, $idInv, $ssoToken, $payNotes, $userName);
            } catch (Hk_Exception_Payment $hex) {

                $payResult = new PaymentResult($idInv, 0, 0);
                $payResult->setStatus(PaymentResult::ERROR);
                $payResult->setDisplayMessage($hex->getMessage());
            }
        } else if ($transType == InstamedGateway::COF_TRANS) {

            $payResult = $this->completeCof($dbh, $ssoToken);
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
                    EditRS::update($dbh, $ssoTknRs, array($ssoTknRs->Token));
                    $error = FALSE;
            }

            if ($error === FALSE) {
                $ssoTknRs->State->setNewVal(WebHookStatus::Complete);
                EditRS::update($dbh, $ssoTknRs, array($ssoTknRs->Token));
            }
        }

        return $error;
    }

    public function completeCof(\PDO $dbh, $ssoToken) {

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
        $resp = $curl->submit($params, $this->NvpUrl);

        $resp['InvoiceNumber'] = 0;
        $resp['Amount'] = 0;

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

    protected function completeHostedPayment(\PDO $dbh, $idInv, $ssoToken, $paymentNotes, $userName) {

        $uS = Session::getInstance();
        $partlyApproved = FALSE;

        // Check DB for record
        $ssoTknRs = new SsoTokenRS();
        $ssoTknRs->Token->setStoredVal($ssoToken);
        $tokenRow = EditRS::select($dbh, $ssoTknRs, array($ssoTknRs->Token));

        if (count($tokenRow) < 1) {
            $payResult = new PaymentResult($idInv, 0, 0);
            $payResult->setDisplayMessage('SSO Token not found');
            return $payResult;
        }

        EditRS::loadRow($tokenRow[0], $ssoTknRs);

        //get transaction details
        $params = $this->getCredentials()->toCurl()
                . "&transactionAction=ViewReceipt"
                . "&requestToken=false"
                . "&singleSignOnToken=" . $ssoToken;

        $curl = new ImCurlRequest();
        $resp = $curl->submit($params, $this->NvpUrl);

        $resp['InvoiceNumber'] = $ssoTknRs->InvoiceNumber->getStoredVal();
        $resp['Amount'] = $ssoTknRs->Amount->getStoredVal();

        $curlResponse = new VerifyCurlResponse($resp, MpTranType::Sale);

        // Save raw transaction in the db.
        try {
            self::logGwTx($dbh, $curlResponse->getResponseCode(), $params, json_encode($curlResponse->getResultArray()), 'HostedCoVerify');
        } catch (Exception $ex) {
            // Do Nothing
        }


        //Wait for web hook
        $state = $this->waitForWebhook($dbh, $ssoTknRs, 5);

        if ($state == WebHookStatus::Init) {
            // Webhook has not shown up yet.

            $payResult = new PaymentResult($idInv, 0, 0);
            $payResult->setStatus(PaymentResult::ERROR);
            $payResult->setDisplayMessage('** Payment status unknown, try again later. *** ');
            return $payResult;

        } else if ($state == WebHookStatus::Error) {
            // HHK's webhook processing failed..

            $payResult = new PaymentResult($idInv, 0, 0);
            $payResult->setStatus(PaymentResult::ERROR);
            $payResult->setDisplayMessage('** Payment processing error in HHK **');
            return $payResult;
        }


        // Create reciept.
        $invoice = new Invoice($dbh, $ssoTknRs->InvoiceNumber->getStoredVal());

        $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId(), 0);

        $pAuthRs = new Payment_AuthRS();
        $pAuthRs->AcqRefData->setStoredVal($curlResponse->getPrimaryTransactionID());
        $pauths = EditRS::select($dbh, $pAuthRs, array($pAuthRs->AcqRefData));

        if (count($pauths) < 1) {
            throw new Hk_Exception_Payment('Charge payment record not found.');
        }

        EditRS::loadRow($pauths[count($pauths)-1], $pAuthRs);

        $payRs = new PaymentRS();
        $payRs->idPayment->setStoredVal($pAuthRs->idPayment->getStoredVal());
        $pays = EditRS::select($dbh, $payRs, array($payRs->idPayment));

        EditRS::loadRow($pays[0], $payRs);

        $gTRs = new Guest_TokenRS();
        $gTRs->idGuest_token->setStoredVal($payRs->idToken->getStoredVal());
        $guestTkns = EditRS::select($dbh, $gTRs, array($gTRs->idGuest_token));

        if (count($guestTkns) > 0) {
            EditRS::loadRow($guestTkns[0], $gTRs);
        }

        // Partially approved?
        if ($curlResponse->getPartialPaymentAmount() > 0) {
            $partlyApproved = TRUE;
        }

        $gwResp = new StandInGwResponse($pAuthRs, $gTRs->OperatorID->getStoredVal(), $gTRs->CardHolderName->getStoredVal(), $gTRs->ExpDate->getStoredVal(), $gTRs->Token->getStoredVal(), $invoice->getInvoiceNumber(), $payRs->Amount->getStoredVal());
        $payResp = new ImPaymentResponse($gwResp, $ssoTknRs->idName->getStoredVal(), $ssoTknRs->idGroup->getStoredVal(), $ssoTknRs->InvoiceNumber->getStoredVal(), $paymentNotes, $partlyApproved);

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

//    protected function pollPaymentStatus($token, $trace = FALSE) {
//
//        $data = $this->getCredentials()->toSOAP();
//
//        $data['tokenID'] = $token;
//
//        $soapReq = new PollingRequest();
//
//        return new PollingResponse($soapReq->submit($data, $this->soapUrl, $trace));
//
//    }

    protected function loadGateway(\PDO $dbh) {

        $gwRs = new InstamedGatewayRS();
        $gwRs->cc_name->setStoredVal($this->getGwName());


        $rows = EditRS::select($dbh, $gwRs, array($gwRs->cc_name));

        if (count($rows) == 1) {

            $gwRs = new InstamedGatewayRS();
            EditRS::loadRow($rows[0], $gwRs);

            $this->ssoUrl = $gwRs->providersSso_Url->getStoredVal();
            $this->soapUrl = $gwRs->soap_Url->getStoredVal();
            $this->NvpUrl = $gwRs->nvp_Url->getStoredVal();

            $this->useAVS = filter_var($gwRs->Use_AVS_Flag->getStoredVal(), FILTER_VALIDATE_BOOLEAN);
            $this->useCVV = filter_var($gwRs->Use_Ccv_Flag->getStoredVal(), FILTER_VALIDATE_BOOLEAN);
        } else {
            throw new Hk_Exception_Runtime('The credit card payment gateway is not found: ' . $this->getGwName() . '.  ');
        }

        return $gwRs;
    }

    protected function setCredentials($gwRs) {

        $this->credentials = new InstaMedCredentials($gwRs);

        $this->saleUrl = 'https://online.instamed.com/providers/Form/PatientPayments/NewPatientPaymentSSO';
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

    protected function waitForWebhook(\PDO $dbh, SsoTokenRS $ssoTknRs, $delaySeconds = 5) {

        $slept = 0;
        $state = WebHookStatus::Init;

        while ($slept < $delaySeconds) {

            $tokenRow = EditRS::select($dbh, $ssoTknRs, array($ssoTknRs->Token));

            if (count($tokenRow) > 0 && $tokenRow[0]['State'] != WebHookStatus::Init) {
                $state = $tokenRow[0]['State'];
                $slept = $delaySeconds + 2;
            } else {
                $slept++;
                sleep(1);
            }
        }

        return $state;
    }

    public function getPaymentResponseObj(iGatewayResponse $vcr, $idPayor, $idGroup, $invoiceNumber, $idToken = 0, $payNotes = '') {
        return new ImPaymentResponse($vcr, $idPayor, $idGroup, $invoiceNumber, $payNotes);
    }

    public function getCofResponseObj(iGatewayResponse $vcr, $idPayor, $idGroup) {
        return new ImCofResponse($vcr, $idPayor, $idGroup);
    }

    public function createEditMarkup(\PDO $dbh, $resultMessage = '') {

        $gwRs = new InstamedGatewayRS();
        $rows = EditRS::select($dbh, $gwRs, array());

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
                    HTMLTable::makeTh('Security Key', array('class' => 'tdlabel'))
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
                    HTMLTable::makeTh('SOAP WSDL', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->soap_Url->getStoredVal(), array('name' => $indx . '_txtsurl', 'size' => '90')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('NVP URL', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->nvp_Url->getStoredVal(), array('name' => $indx . '_txtnvpurl', 'size' => '90')))
            );
//            $tbl->addBodyTr(
//                    HTMLTable::makeTh('Card on File URL', array('class'=>'tdlabel'))
//                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->COF_Url->getStoredVal(), array('name'=>$indx .'_txtcofurl', 'size'=>'90')))
//            );
        }

        if ($resultMessage != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($resultMessage, array('colspan' => '2', 'style' => 'font-weight:bold;')));
        }

        return $tbl->generateMarkup();
    }

    public function SaveEditMarkup(\PDO $dbh, $post) {

        $msg = '';
        $ccRs = new InstamedGatewayRS();

        $rows = EditRS::select($dbh, $ccRs, array());

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

            if (isset($post[$indx . '_txtsurl'])) {
                $ccRs->soap_Url->setNewVal(filter_var($post[$indx . '_txtsurl'], FILTER_SANITIZE_STRING));
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

            $ccRs->Use_Ccv_Flag->setNewVal(0);
            $ccRs->Use_AVS_Flag->setNewVal(0);

            // Save record.
            $num = EditRS::update($dbh, $ccRs, array($ccRs->idcc_gateway));

            if ($num > 0) {
                $msg .= HTMLContainer::generateMarkup('p', $ccRs->cc_name->getStoredVal() . " - Payment Credentials Updated.  ");
            } else {
                $msg .= HTMLContainer::generateMarkup('p', $ccRs->cc_name->getStoredVal() . " - No changes detected.  ");
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
    protected $securityKey;
    protected $accountID;
    protected $terminalId;
    protected $workstationId;
    protected $ssoAlias;
    protected $id;

    public function __construct(InstamedGatewayRS $gwRs) {

        $this->accountID = $gwRs->account_Id->getStoredVal();
        $this->securityKey = $gwRs->security_Key->getStoredVal();
        $this->ssoAlias = $gwRs->sso_Alias->getStoredVal();
        $this->merchantId = $gwRs->merchant_Id->getStoredVal();
        $this->storeId = $gwRs->store_Id->getStoredVal();
        $this->terminalId = $gwRs->terminal_Id->getStoredVal();
        $this->workstationId = $gwRs->WorkStation_Id->getStoredVal();

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

    public function toCurl() {

        return
                InstaMedCredentials::MERCHANT_ID . '=' . $this->merchantId
                . '&' . InstaMedCredentials::STORE_ID . '=' . $this->storeId
                . '&' . InstaMedCredentials::TERMINAL_ID . '=' . $this->terminalId
                . '&' . InstaMedCredentials::WORKSTATION_ID . '=' . $this->workstationId;
    }

    public function toSOAP() {

        return array(
            InstaMedCredentials::ACCT_ID => $this->accountID,
            'password' => decryptMessage($this->securityKey),
            'alias' => $this->ssoAlias,
        );
    }

}

