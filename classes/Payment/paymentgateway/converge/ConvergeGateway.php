<?php
/**
 * ConvergeGateway.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of ConvergeGateWay
 *
 * @author Eric
 */
class ConvergeGateway extends PaymentGateway {

    const TRANS_VAR = 'cvt';
    const RESULT_VAR = 'cvres';
    // query string parameter values
    const HCO_TRANS = 'cvsale';
    const COF_TRANS = 'cvcof';

    protected $hostedInitURL;
    protected $hostedPaymentURL;
    protected $xmlFormURL;

    protected function getPaymentMethod() {
        return PaymentMethod::Charge;
    }

    protected function getGatewayName() {
        return 'converge';
    }

    protected function initHostedPayment(\PDO $dbh, Invoice $invoice, $postbackUrl) {

        $uS = Session::getInstance();
        $dataArray = array();

        if ($invoice->getSoldToId() < 1 || $invoice->getIdGroup() < 1) {
            throw new Hk_Exception_Runtime("Card Holder information is missing.  ");
        }

        $params = $this->getCredentials()->toCurl()
                . "&ssl_transaction_type=ccsale"
                . "&ssl_invoicenumber=" . $invoice->getInvoiceNumber()
                . "&ssl_amount=" . number_format($invoice->getAmountToPay(), 2);

        $curlRequest = new ConvergeCurlRequest();
        $resp = $curlRequest->submit($params, $this->hostedInitURL);
        $httpRespCode = $curlRequest->getCurlInfo();

        // Save raw transaction in the db.
        try {
            self::logGwTx($dbh, $httpRespCode['http_code'], $params, ($httpRespCode['http_code'] == 200 ? json_encode($resp) : json_encode($httpRespCode)), 'HostedCoInit');
        } catch (Exception $ex) {
            // Do Nothing
        }

        if ($httpRespCode['http_code'] == 200 && $resp != '') {

            // Save payment ID
            $ciq = "replace into card_id (idName, `idGroup`, `Transaction`, InvoiceNumber, CardID, Init_Date, Frequency, ResponseCode)"
                    . " values (" . $invoice->getSoldToId() . " , " . $invoice->getIdGroup() . ", '" . self::HCO_TRANS . "', '', '" . $resp . "', now(), 'OneTime', 0)";

            $dbh->exec($ciq);

            $uS->cvtoken = $resp;

            $dataArray = array('cvtx' => $this->hostedPaymentURL . '?ssl_txn_auth_token='. $resp);

        } else {

            // The initialization failed.
            unset($uS->cvtoken);
            throw new Hk_Exception_Payment("Credit Payment Gateway Error: Response Code = " . $httpRespCode['http_code'] . ', Error Message = ' . $curlRequest->getErrorMsg());
        }

        return $dataArray;
    }

    public function initCardOnFile(\PDO $dbh, $pageTitle, $idGuest, $idGroup, $cardHolderName, $postbackUrl) {

        $uS = Session::getInstance();
        $dataArray = array();

        $data = $this->getCredentials()->toCurl()
                . "&ssl_transaction_type=ccgettoken";

        $curlRequest = new ConvergeCurlRequest();
        $resp = $curlRequest->submit($data, $this->hostedInitURL);

        // Save raw transaction in the db.
        try {
            self::logGwTx($dbh, '', $data, json_encode($resp), 'CardInfoInit');
        } catch (Exception $ex) {
            // Do Nothing
        }

        // Verify response
        if ($resp != '') {

            // Save payment ID
            $ciq = "replace into card_id (idName, `idGroup`, `Transaction`, InvoiceNumber, CardID, Init_Date, Frequency, ResponseCode)"
                    . " values (" . $idGuest . " , " . $idGroup() . ", '" . self::COF_TRANS . "', '', '" . $resp . "', now(), 'OneTime', 0)";

            $dbh->exec($ciq);

            $uS->cvtoken = $resp;

            $dataArray = array('cvtx' => $this->hostedPaymentURL . '?ssl_txn_auth_token='. $resp);
        } else {

            // The initialization failed.
            unset($uS->cvtoken);
            throw new Hk_Exception_Payment("Credit Payment Gateway Error: " . $resp->getResponseMessage());
        }

        return $dataArray;
    }

    protected function sendVoid(\PDO $dbh, PaymentRS $payRs, Payment_AuthRS $pAuthRs, Invoice $invoice, $paymentNotes, $bid) {

        $uS = Session::getInstance();
        $dataArray['bid'] = $bid;

        $params = $this->getCredentials()->toCurl()
                . "&ssl_transaction_type=ccvoid"
                . "&ssl_txn_id=" . $pAuthRs->AcqRefData->getStoredVal();

        $curlRequest = new ConvergeCurlRequest();

        $resp = $curlRequest->submit($params, $this->NvpUrl);

        $resp['InvoiceNumber'] = $invoice->getInvoiceNumber();
        $resp['Amount'] = $payRs->Amount->getStoredVal();
        $resp['cardBrand'] = $pAuthRs->Card_Type->getStoredVal();
        $resp['lastFourDigits'] = $pAuthRs->Acct_Number->getStoredVal();

        $curlResponse = new VerifyCvCurlResponse($resp, MpTranType::Void);

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
                . "&ssl_transaction_type=ccreturn"
                . "&ssl_txn_id=" . $pAuthRs->AcqRefData->getStoredVal()
                . "&ssl_amount=" . number_format($returnAmt, 2);

        $curlRequest = new ConvergeCurlRequest();

        $resp = $curlRequest->submit($params, $this->NvpUrl);

        $resp['InvoiceNumber'] = $invoice->getInvoiceNumber();
        $resp['Amount'] = $payRs->Amount->getStoredVal();

        $curlResponse = new VerifyCvCurlResponse($resp, MpTranType::ReturnSale);

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

//        if (isset($post[InstamedGateway::INSTAMED_TRANS_VAR])) {
//            $transType = filter_var($post[InstamedGateway::INSTAMED_TRANS_VAR], FILTER_SANITIZE_STRING);
//        }
//
//        // Not a payment return so get out.
//        if ($transType == '') {
//            return $payResult;
//        }
//
//        if (isset($post[InstamedGateway::INSTAMED_RESULT_VAR])) {
//            $transResult = filter_var($post[InstamedGateway::INSTAMED_RESULT_VAR], FILTER_SANITIZE_STRING);
//        }
//
//        if ($transResult == InstamedGateway::POSTBACK_CANCEL) {
//
//            $payResult = new PaymentResult($idInv, 0, 0);
//            $payResult->setDisplayMessage('User Canceled.');
//
//            return $payResult;
//        } else if ($transResult != InstamedGateway::POSTBACK_COMPLETE) {
//
//            $payResult = new PaymentResult($idInv, 0, 0);
//            $payResult->setDisplayMessage('Undefined Result: ' . $transResult);
//
//            return $payResult;
//        }
//
//        if ($ssoToken === NULL || $ssoToken == '') {
//
//            $payResult = new PaymentResult($idInv, 0, 0);
//            $payResult->setDisplayMessage('Missing Token. ');
//
//            return $payResult;
//        }
//
//        // Finally, process the transaction
//        if ($transType == InstamedGateway::HCO_TRANS) {
//
//            try {
//                $payResult = $this->completeHostedPayment($dbh, $idInv, $ssoToken, $payNotes, $userName);
//            } catch (Hk_Exception_Payment $hex) {
//
//                $payResult = new PaymentResult($idInv, 0, 0);
//                $payResult->setStatus(PaymentResult::ERROR);
//                $payResult->setDisplayMessage($hex->getMessage());
//            }
//        } else if ($transType == InstamedGateway::COF_TRANS) {
//
//            $payResult = $this->completeCof($dbh, $ssoToken);
//        }
//
        return $payResult;
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

        $curl = new ConvergeCurlRequest();
        $resp = $curl->submit($params, $this->NvpUrl);

        $resp['InvoiceNumber'] = 0;
        $resp['Amount'] = 0;

        $response = new VerifyCurlResponse($resp);

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

        $curl = new ConvergeCurlRequest();
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

    protected function loadGateway(\PDO $dbh) {

        $gwRs = new InstamedGatewayRS();
        $gwRs->cc_name->setStoredVal($this->gwType);
        $gwRs->Gateway_Name->setStoredVal($this->getGatewayName());

        $rows = EditRS::select($dbh, $gwRs, array($gwRs->Gateway_Name, $gwRs->cc_name));

        if (count($rows) == 1) {

            $gwRs = new InstamedGatewayRS();
            EditRS::loadRow($rows[0], $gwRs);

            $this->hostedInitURL = $gwRs->providersSso_Url->getStoredVal();
            $this->hostedPaymentURL = $gwRs->nvp_Url->getStoredVal();
            $this->xmlFormURL = $gwRs->soap_Url->getStoredVal();

            $this->useAVS = filter_var($gwRs->Use_AVS_Flag->getStoredVal(), FILTER_VALIDATE_BOOLEAN);
            $this->useCVV = filter_var($gwRs->Use_Ccv_Flag->getStoredVal(), FILTER_VALIDATE_BOOLEAN);
        } else {
            throw new Hk_Exception_Runtime('The credit card payment gateway is not found: ' . $this->getGatewayName() . '.  ');
        }

        return $gwRs;
    }

    protected function setCredentials($gwRs) {

        $this->credentials = new ConvergeCredentials($gwRs);

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

    public function getPaymentResponseObj(iGatewayResponse $vcr, $idPayor, $idGroup, $invoiceNumber, $idToken = 0, $payNotes = '', $isPartial = FALSE) {
        return new ConvergePaymentResponse($vcr, $idPayor, $idGroup, $invoiceNumber, $idToken, $payNotes, $isPartial);
    }

    public function getCofResponseObj(iGatewayResponse $vcr, $idPayor, $idGroup) {
        return new ConvergeCofResponse($vcr, $idPayor, $idGroup);
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
                    HTMLTable::makeTh('Merchant Id', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->merchant_Id->getStoredVal(), array('name' => $indx . '_txtmid', 'size' => '80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Merchant User Id', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->store_Id->getStoredVal(), array('name' => $indx . '_txtmuid', 'size' => '80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Merchant PIN', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->security_Key->getStoredVal(), array('name' => $indx . '_txtmPIN', 'size' => '100')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Token URL', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->providersSso_Url->getStoredVal(), array('name' => $indx . '_txttkurl', 'size' => '90')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Hospted Payment URL', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->nvp_Url->getStoredVal(), array('name' => $indx . '_txthppurl', 'size' => '90')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('XML URL', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->soap_Url->getStoredVal(), array('name'=>$indx .'_txtcofurl', 'size'=>'90')))
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

            if (isset($post[$indx . '_txtmid'])) {
                $ccRs->merchant_Id->setNewVal(filter_var($post[$indx . '_txtmid'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtmuid'])) {
                $ccRs->store_Id->setNewVal(filter_var($post[$indx . '_txtmuid'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtmPIN'])) {

                $pw = filter_var($post[$indx . '_txtmPIN'], FILTER_SANITIZE_STRING);

                if ($pw != '' && $ccRs->security_Key->getStoredVal() != $pw) {
                    $ccRs->security_Key->setNewVal(encryptMessage($pw));
                } else if ($pw == '') {
                    $ccRs->security_Key->setNewVal('');
                }
            }

            if (isset($post[$indx . '_txttkurl'])) {
                $ccRs->providersSso_Url->setNewVal(filter_var($post[$indx . '_txttkurl'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txthppurl'])) {
                $ccRs->nvp_Url->setNewVal(filter_var($post[$indx . '_txthppurl'], FILTER_SANITIZE_STRING));
            }

            if (isset($post[$indx . '_txtcofurl'])) {
                $ccRs->soap_Url->setNewVal(filter_var($post[$indx . '_txtcofurl'], FILTER_SANITIZE_STRING));
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

class ConvergeCurlRequest extends CurlRequest {

    protected $errorMsg = '';
    protected $curlInfo = array();

    protected function execute($url, $params) {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,$url); // set POST target URL
        curl_setopt($ch,CURLOPT_POST, true); // set POST method
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch,CURLOPT_POSTFIELDS, $params);

        $responseString = curl_exec($ch);
        $this->errorMsg = curl_error($ch);
        $this->curlInfo = curl_getinfo($ch);
        curl_close($ch);

        if ( ! $responseString ) {
            throw new Hk_Exception_Payment('Network (cURL) Error: ' . $this->errorMsg);
        }

        return $responseString;
    }

    public function getErrorMsg() {
        return $this->errorMsg;
    }

    public function getCurlInfo() {
        return $this->curlInfo;
    }

}

class ConvergeCredentials {

    // NVP names
    const SEC_KEY = 'ssl_pin';
    const MERCHANT_ID = 'ssl_merchant_id';
    const USER_ID = 'ssl_user_id';

    public $merchantId;
    public $userId;
    protected $securityKey;

    public function __construct(InstamedGatewayRS $gwRs) {

        $this->securityKey = $gwRs->security_Key->getStoredVal();
        $this->merchantId = $gwRs->merchant_Id->getStoredVal();
        $this->userId = $gwRs->store_Id->getStoredVal();

    }

    public function toCurl() {

        return
                self::MERCHANT_ID . '=' . $this->merchantId
                . '&' . self::USER_ID . '=' . $this->userId
                . '&' . self::SEC_KEY . '=' . decryptMessage($this->securityKey);
    }

}

