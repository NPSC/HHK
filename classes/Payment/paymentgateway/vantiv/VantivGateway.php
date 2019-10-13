<?php
/**
 * VantivGateway.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


/**
 * Description of VantivGateway
 *
 * @author Eric
 */

class VantivGateway extends PaymentGateway {

    const CARD_ID = 'CardID';
    const PAYMENT_ID = 'PaymentID';

    public function getPaymentMethod() {
        return PaymentMethod::Charge;
    }

    public function getGatewayName() {
        return 'vantiv';
    }

    public function creditSale(\PDO $dbh, $pmp, $invoice, $postbackUrl) {

        $uS = Session::getInstance();
        $payResult = NULL;

        $guest = new Guest($dbh, '', $invoice->getSoldToId());
        $addr = $guest->getAddrObj()->get_data($guest->getAddrObj()->get_preferredCode());

        $tokenRS = CreditToken::getTokenRsFromId($dbh, $pmp->getIdToken());

        // Do we have a token?
        if (CreditToken::hasToken($tokenRS)) {

            $cpay = new CreditSaleTokenRequest();

            $cpay->setPurchaseAmount($invoice->getAmountToPay())
                    ->setTaxAmount(0)
                    ->setCustomerCode($invoice->getSoldToId())
                    ->setAddress($addr["Address_1"])
                    ->setZip($addr["Postal_Code"])
                    ->setToken($tokenRS->Token->getStoredVal())
                    ->setPartialAuth(FALSE)
                    ->setCardHolderName($tokenRS->CardHolderName->getStoredVal())
                    ->setFrequency(MpFrequencyValues::OneTime)
                    ->setInvoice($invoice->getInvoiceNumber())
                    ->setTokenId($tokenRS->idGuest_token->getStoredVal())
                    ->setMemo(MpVersion::PosVersion);

            // Run the token transaction
            $tokenResp = TokenTX::CreditSaleToken($dbh, $invoice->getSoldToId(), $invoice->getIdGroup(), $this, $cpay, $pmp->getPayNotes());

            // Analyze the result
            $payResult = $this->analyzeCredSaleResult($dbh, $tokenResp, $invoice, $pmp->getIdToken());
        } else {

            // Initialiaze hosted payment
            $fwrder = $this->initHostedPayment($dbh, $invoice, $guest, $addr, $postbackUrl);

            $payIds = array();
            if (isset($uS->paymentIds)) {
                $payIds = $uS->paymentIds;
            }

            $payIds[$fwrder['paymentId']] = $invoice->getIdInvoice();
            $uS->paymentIds = $payIds;
            $uS->paymentNotes = $pmp->getPayNotes();

            $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
            $payResult->setForwardHostedPayment($fwrder);
            $payResult->setDisplayMessage('Forward to Payment Page. ');
        }

        return $payResult;
    }

    public function voidSale(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, $paymentNotes, $bid) {

        // find the token record
        if ($payRs->idToken->getStoredVal() > 0) {
            $tknRs = CreditToken::getTokenRsFromId($dbh, $payRs->idToken->getStoredVal());
        } else {
            return array('warning' => 'Payment Token Id not found.  Unable to Void this purchase.  ', 'bid' => $bid);
        }

        if (CreditToken::hasToken($tknRs) === FALSE) {
            return array('warning' => 'Payment Token not found.  Unable to Void this purchase.  ', 'bid' => $bid);
        }

        // Find hte detail record.
        $stmt = $dbh->query("Select * from payment_auth where idPayment = " . $payRs->idPayment->getStoredVal() . " order by idPayment_auth");
        $arows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($arows) < 1) {
            return array('warning' => 'Payment Detail record not found.  Unable to Void this purchase. ', 'bid' => $bid);
        }

        $pAuthRs = new Payment_AuthRS();
        EditRS::loadRow(array_pop($arows), $pAuthRs);

        if ($pAuthRs->Status_Code->getStoredVal() == PaymentStatusCode::Paid || $pAuthRs->Status_Code->getStoredVal() == PaymentStatusCode::VoidReturn) {
            return $this->sendVoid($dbh, $payRs, $pAuthRs, $tknRs, $invoice, $paymentNotes);
        }

        return array('warning' => 'Payment is ineligable for void.  ', 'bid' => $bid);
    }

    public function voidReturn(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs) {

        $uS = Session::getInstance();

        // find the token record
        if ($payRs->idToken->getStoredVal() > 0) {
            $tknRs = CreditToken::getTokenRsFromId($dbh, $payRs->idToken->getStoredVal());
        } else {
            return array('warning' => 'Card-on-File not found.  Unable to Void this return.  ');
        }

        if (CreditToken::hasToken($tknRs) === FALSE) {
            return array('warning' => 'Card-on-File not found.  Unable to Void this return.  ');
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

            $csResp = TokenTX::creditVoidReturnToken($dbh, $payRs->idPayor->getstoredVal(), $invoice->getIdGroup(), $this, $revRequest, $payRs);

            switch ($csResp->getStatus()) {

                case CreditPayments::STATUS_APPROVED:

                    // Update invoice
                    $invoice->updateInvoiceBalance($dbh, $csResp->response->getAuthorizeAmount(), $uS->username);

                    $csResp->idVisit = $invoice->getOrderNumber();
                    $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createSaleMarkup($dbh, $csResp, $uS->resourceURL . 'images/receiptlogo.png', $uS->siteName, $uS->sId, 'Void Return')));
                    $dataArray['success'] = 'Return is Voided.  ';

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

    public function reverseSale(\PDO $dbh, PaymentRS $payRs, Invoice $invoice, $bid, $paymentNotes) {

        $uS = Session::getInstance();

        // find the token record
        if ($payRs->idToken->getStoredVal() > 0) {
            $tknRs = CreditToken::getTokenRsFromId($dbh, $payRs->idToken->getStoredVal());
        } else {
            return array('warning' => 'Payment Token Id not found.  Unable to Reverse this purchase.  ', 'bid' => $bid);
        }

        if (CreditToken::hasToken($tknRs) === FALSE) {
            return array('warning' => 'Payment Token not found.  Unable to Reverse this purchase.  ', 'bid' => $bid);
        }

        // Find hte detail record.
        $stmt = $dbh->query("Select * from payment_auth where idPayment = " . $payRs->idPayment->getStoredVal() . " order by idPayment_auth");
        $arows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($arows) < 1) {
            return array('warning' => 'Payment Detail record not found.  Unable to Reverse this purchase. ', 'bid' => $bid);
        }

        $pAuthRs = new Payment_AuthRS();
        EditRS::loadRow(array_pop($arows), $pAuthRs);

        if ($pAuthRs->Status_Code->getStoredVal() == PaymentStatusCode::Paid || $pAuthRs->Status_Code->getStoredVal() == PaymentStatusCode::VoidReturn) {

            // Set up request
            $revRequest = new CreditReversalTokenRequest();
            $revRequest->setAuthCode($pAuthRs->Approval_Code->getStoredVal())
                    ->setCardHolderName($tknRs->CardHolderName->getStoredVal())
                    ->setFrequency(MpFrequencyValues::OneTime)->setMemo(MpVersion::PosVersion)
                    ->setInvoice($invoice->getInvoiceNumber())
                    ->setPurchaseAmount($pAuthRs->Approved_Amount->getStoredVal())
                    ->setRefNo($pAuthRs->Reference_Num->getStoredVal())
                    ->setProcessData($pAuthRs->ProcessData->getStoredVal())
                    ->setAcqRefData($pAuthRs->AcqRefData->getStoredVal())
                    ->setToken($tknRs->Token->getStoredVal())
                    ->setTokenId($tknRs->idGuest_token->getStoredVal())
                    ->setTitle('CreditReversalToken');

            try {

                $csResp = TokenTX::creditReverseToken($dbh, $payRs->idPayor->getstoredVal(), $invoice->getIdGroup(), $this, $revRequest, $payRs, $paymentNotes);

                switch ($csResp->response->getStatus()) {

                    case MpStatusValues::Approved:

                        // Update invoice
                        $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizedAmount(), $uS->username);


                        $csResp->idVisit = $invoice->getOrderNumber();
                        $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createVoidMarkup($dbh, $csResp, $uS->siteName, $uS->sId, 'Reverse Sale')));
                        $dataArray['success'] = $reply;

                        break;

                    case MpStatusValues::Declined:

                        // Try Void
                        $dataArray = self::sendVoid($dbh, $payRs, $pAuthRs, $tknRs, $invoice, $paymentNotes);
                        $dataArray['reversal'] = 'Reversal Declined, trying Void.  ';

                        break;

                    default:

                        $dataArray['warning'] = '** Reversal Invalid or Error. **  ' . 'Message: ' . $csResp->response->getMessage();
                }
            } catch (Hk_Exception_Payment $exPay) {

                $dataArray['warning'] = "Reversal Error = " . $exPay->getMessage();
            }

            return $dataArray;
        }

        return array('warning' => 'Payment is ineligable for reversal.  ', 'bid' => $bid);
    }

    // Returns a Payment
    protected function _returnPayment(\PDO $dbh, PaymentRS $payRs, Payment_AuthRS $pAuthRs, Invoice $invoice, $returnAmt, $bid) {

        $uS = Session::getInstance();

        // find the token
        if ($payRs->idToken->getStoredVal() > 0) {
            $tknRs = CreditToken::getTokenRsFromId($dbh, $payRs->idToken->getStoredVal());
        } else {
            return array('warning' => 'Return Failed.  Payment Token not found.  ', 'bid' => $bid);
        }

        // Set up request
        $returnRequest = new CreditReturnTokenRequest();
        $returnRequest->setCardHolderName($tknRs->CardHolderName->getStoredVal());
        $returnRequest->setFrequency(MpFrequencyValues::OneTime)->setMemo(MpVersion::PosVersion);
        $returnRequest->setInvoice($invoice->getInvoiceNumber());

        // Determine amount to return
        $returnRequest->setPurchaseAmount($returnAmt);

        $returnRequest->setToken($tknRs->Token->getStoredVal());
        $returnRequest->setTokenId($tknRs->idGuest_token->getStoredVal());

        $dataArray = array('bid' => $bid);

        try {

            $csResp = TokenTX::creditReturnToken($dbh, $payRs->idPayor->getstoredVal(), $invoice->getIdGroup(), $this, $returnRequest, $payRs);

            switch ($csResp->response->getStatus()) {

                case MpStatusValues::Approved:


                    // Update invoice
                    $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizedAmount(), $uS->username);

                    $csResp->idVisit = $invoice->getOrderNumber();
                    $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createReturnMarkup($dbh, $csResp, $uS->siteName, $uS->sId)));

                    break;

                case MpStatusValues::Declined:

                    return array('warning' => $csResp->response->getMessage(), 'bid' => $bid);

                    break;

                default:

                    return array('warning' => '** Return Invalid or Error. **  ', 'bid' => $bid);
            }

        } catch (Hk_Exception_Payment $exPay) {

            return array('warning' => "Payment Error = " . $exPay->getMessage(), 'bid' => $bid);
        }

        return $dataArray;
    }

    public function returnAmount(\PDO $dbh, Invoice $invoice, $rtnToken, $paymentNotes = '') {

        $tokenRS = CreditToken::getTokenRsFromId($dbh, $rtnToken);
        $amount = abs($invoice->getAmount());

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


            $tokenResp = TokenTX::creditReturnToken($dbh, $invoice->getSoldToId(), $invoice->getIdGroup(), $this, $returnRequest, NULL, $paymentNotes);

            // Analyze the result
            $rtnResult = new ReturnResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId(), $idToken);

            switch ($tokenResp->getStatus()) {

                case CreditPayments::STATUS_APPROVED:

                    // Update invoice
                    $invoice->updateInvoiceBalance($dbh, 0 - $tokenResp->response->getAuthorizedAmount(), $uS->username);

                    $rtnResult->feePaymentAccepted($dbh, $uS, $tokenResp, $invoice);
                    $rtnResult->setDisplayMessage('Refund by Credit Card.  ');

                    break;

                case CreditPayments::STATUS_DECLINED:

                    $rtnResult->setStatus(PaymentResult::DENIED);
                    $rtnResult->feePaymentRejected($dbh, $uS, $tokenResp, $invoice);
                    $rtnResult->setDisplayMessage('** The Return is Declined. **  Message: ' . $tokenResp->response->getResponseMessage());

                    break;

                default:

                    $rtnResult->setStatus(PaymentResult::ERROR);
                    $rtnResult->feePaymentError($dbh, $uS);
                    $rtnResult->setDisplayMessage('** Return Invalid or Error **  Message: ' . $tokenResp->response->getResponseMessage());
            }

        } else {
            throw new Hk_Exception_Payment('Return Failed.  Credit card token not found.  ');
        }

    }

    Protected function initHostedPayment(\PDO $dbh, Invoice $invoice, Guest $guest, $addr, $postbackUrl) {

        $uS = Session::getInstance();

        // Do a hosted payment.
        $config = new Config_Lite(ciCFG_FILE);
        $secure = new SecurityComponent();

        $houseUrl = $secure->getSiteURL();
        $siteUrl = $secure->getRootURL();
        $logo = $config->getString('financial', 'PmtPageLogoUrl', '');

        if ($houseUrl == '' || $siteUrl == '') {
            throw new Hk_Exception_Runtime("The site/house URL is missing.  ");
        }

        if ($invoice->getSoldToId() < 1 || $invoice->getIdGroup() < 1) {
            throw new Hk_Exception_Runtime("Card Holder information is missing.  ");
        }

        $pay = new InitCkOutRequest($uS->siteName, 'Custom');


        // Card reader?
        if ($uS->CardSwipe) {
            $pay->setDefaultSwipe('Swipe')
                    ->setCardEntryMethod('Both')
                    ->setPaymentPageCode('Checkout_Url');
        } else {
            $pay->setPaymentPageCode('Checkout_Url');
        }

        $pay->setPartialAuth(TRUE);

        $pay->setAVSZip($addr["Postal_Code"])
                ->setAVSAddress($addr['Address_1'])
                ->setCardHolderName($guest->getRoleMember()->get_fullName())
                ->setFrequency(MpFrequencyValues::OneTime)
                ->setInvoice($invoice->getInvoiceNumber())
                ->setMemo(MpVersion::PosVersion)
                ->setTaxAmount(0)
                ->setTotalAmount($invoice->getAmountToPay())
                ->setCompleteURL($houseUrl . $postbackUrl)
                ->setReturnURL($houseUrl . $postbackUrl)
                ->setTranType(MpTranType::Sale)
                ->setLogoUrl($siteUrl . $logo)
                ->setCVV('on')
                ->setAVSFields('both');

        $CreditCheckOut = HostedCheckout::sendToPortal($dbh, $this, $invoice->getSoldToId(), $invoice->getIdGroup(), $invoice->getInvoiceNumber(), $pay);

        return $CreditCheckOut;
    }

    public function initCardOnFile(\PDO $dbh, $pageTitle, $idGuest, $idGroup, $manualKey, $cardHolderName, $postBackPage) {

        $uS = Session::getInstance();

        $secure = new SecurityComponent();
        $config = new Config_Lite(ciCFG_FILE);

        $houseUrl = $secure->getSiteURL();
        $siteUrl = $secure->getRootURL();
        $logo = $config->getString('financial', 'PmtPageLogoUrl', '');

        if ($houseUrl == '' || $siteUrl == '') {
            throw new Hk_Exception_Runtime("The site/house URL is missing.  ");
        }

        if ($idGuest < 1 || $idGroup < 1) {
            throw new Hk_Exception_Runtime("Card Holder information is missing.  ");
        }


        $initCi = new InitCiRequest($pageTitle, 'Custom');

        // Card reader?
        if ($uS->CardSwipe) {
            $initCi->setDefaultSwipe('Swipe')
                    ->setCardEntryMethod('Both')
                    ->setPaymentPageCode('CardInfo_Url');
        } else {
            $initCi->setPaymentPageCode('CardInfo_Url');
        }

        $initCi->setCardHolderName($cardHolderName)
                ->setFrequency(MpFrequencyValues::OneTime)
                ->setCompleteURL($houseUrl . $postBackPage)
                ->setReturnURL($houseUrl . $postBackPage)
                ->setLogoUrl($siteUrl . $logo);


        return CardInfo::sendToPortal($dbh, $this, $idGuest, $idGroup, $initCi);
    }

    public function processHostedReply(\PDO $dbh, $post, $ssoToken, $idInv, $payNotes) {

        $payResult = NULL;
        $rtnCode = '';
        $rtnMessage = '';

        if (isset($post['ReturnCode'])) {
            $rtnCode = intval(filter_var($post['ReturnCode'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (isset($post['ReturnMessage'])) {
            $rtnMessage = filter_var($post['ReturnMessage'], FILTER_SANITIZE_STRING);
        }


        if (isset($post[VantivGateway::CARD_ID])) {

            $cardId = filter_var($post[VantivGateway::CARD_ID], FILTER_SANITIZE_STRING);

            // Save postback in the db.
            try {
                self::logGwTx($dbh, $rtnCode, '', json_encode($post), 'CardInfoPostBack');
            } catch (Exception $ex) {
                // Do nothing
            }

            if ($rtnCode > 0) {

                $payResult = new cofResult($rtnMessage, PaymentResult::ERROR, 0, 0);
                return $payResult;
            }

            try {

                $vr = CardInfo::portalReply($dbh, $this, $cardId);

                $payResult = new CofResult($vr->response->getDisplayMessage(), $vr->response->getStatus(), $vr->idPayor, $vr->idRegistration);

            } catch (Hk_Exception_Payment $hex) {
                $payResult = new cofResult($hex->getMessage(), PaymentResult::ERROR, 0, 0);
            }

        } else if (isset($post[VantivGateway::PAYMENT_ID])) {

            $paymentId = filter_var($post[VantivGateway::PAYMENT_ID], FILTER_SANITIZE_STRING);

            try {
                self::logGwTx($dbh, $rtnCode, '', json_encode($post), 'HostedCoPostBack');
            } catch (Exception $ex) {
                // Do nothing
            }

            if ($rtnCode > 0) {

                $payResult = new PaymentResult($idInv, 0, 0);
                $payResult->setStatus(PaymentResult::ERROR);
                $payResult->setDisplayMessage($rtnMessage);
                return $payResult;
            }

            try {

                $csResp = HostedCheckout::portalReply($dbh, $this, $paymentId, $payNotes);

                if ($csResp->getInvoiceNumber() != '') {

                    $invoice = new Invoice($dbh, $csResp->getInvoiceNumber());

                    // Analyze the result
                    $payResult = $this->analyzeCredSaleResult($dbh, $csResp, $invoice, 0, TRUE, TRUE);

                } else {

                    $payResult = new PaymentResult($idInv, 0, 0);
                    $payResult->setStatus(PaymentResult::ERROR);
                    $payResult->setDisplayMessage('Invoice Not Found!  ');
                }
            } catch (Hk_Exception_Payment $hex) {

                $payResult = new PaymentResult($idInv, 0, 0);
                $payResult->setStatus(PaymentResult::ERROR);
                $payResult->setDisplayMessage($hex->getMessage());
            }
        }

        return $payResult;
    }

    protected function sendVoid(\PDO $dbh, PaymentRS $payRs, Payment_AuthRS $pAuthRs, Guest_TokenRS $tknRs, Invoice $invoice, $paymentNotes = '') {

        $uS = Session::getInstance();
        $dataArray = array();

        // Set up request
        $voidRequest = new CreditVoidSaleTokenRequest();
        $voidRequest->setAuthCode($pAuthRs->Approval_Code->getStoredVal());
        $voidRequest->setCardHolderName($tknRs->CardHolderName->getStoredVal());
        $voidRequest->setFrequency(MpFrequencyValues::OneTime)->setMemo(MpVersion::PosVersion);
        $voidRequest->setInvoice($invoice->getInvoiceNumber());
        $voidRequest->setPurchaseAmount($pAuthRs->Approved_Amount->getStoredVal());
        $voidRequest->setRefNo($pAuthRs->Reference_Num->getStoredVal());
        $voidRequest->setToken($tknRs->Token->getStoredVal());
        $voidRequest->setTokenId($tknRs->idGuest_token->getStoredVal());

        try {

            $csResp = TokenTX::creditVoidSaleToken($dbh, $payRs->idPayor->getstoredVal(), $invoice->getIdGroup(), $this, $voidRequest, $payRs, $paymentNotes);

            switch ($csResp->response->getStatus()) {

                case MpStatusValues::Approved:

                    // Update invoice
                    $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizedAmount(), $uS->username);

                    $csResp->idVisit = $invoice->getOrderNumber();
                    $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createVoidMarkup($dbh, $csResp, $uS->siteName, $uS->sId)));
                    $dataArray['success'] = 'Payment is void.  ';

                    break;

                case MpStatusValues::Declined:

                    if (strtoupper($csResp->response->getMessage()) == 'ITEM VOIDED') {

                        // Update invoice
                        $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizedAmount(), $uS->username);

                        $csResp->idVisit = $invoice->getOrderNumber();
                        $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createVoidMarkup($dbh, $csResp, $uS->siteName, $uS->sId)));
                        $dataArray['success'] = 'Payment is void.  ';
                    } else {

                        $dataArray['warning'] = $csResp->response->getMessage();
                    }

                    break;

                default:

                    $dataArray['warning'] = '** Void Invalid or Error. **  ' . 'Message: ' . $csResp->response->getMessage();
            }
        } catch (Hk_Exception_Payment $exPay) {

            $dataArray['warning'] = "Void Error = " . $exPay->getMessage();
        }

        return $dataArray;
    }

    public function analyzeCredSaleResult(\PDO $dbh, PaymentResponse $payResp, \Invoice $invoice, $idToken, $useAVS = FALSE, $useCVV = FALSE) {

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

    public function getPaymentResponseObj(iGatewayResponse $creditTokenResponse, $idPayor, $idGroup, $invoiceNumber, $idToken = 0, $payNotes = '') {
        return new TokenResponse($creditTokenResponse, $idPayor, $idToken, $payNotes);
    }

    public function getCofResponseObj(iGatewayResponse $verifyCiResponse, $idPayor, $idGroup) {
        return new CardInfoResponse($verifyCiResponse, $idPayor, $idGroup);
    }

    protected function loadGateway(\PDO $dbh) {

        $query = "select * from `cc_hosted_gateway` where cc_name = :ccn and Gateway_Name = 'vantiv'";
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':ccn' => $this->gwType));

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) != 1) {
            throw new Hk_Exception_Runtime('The credit card payment gateway is not defined.');
        }

        if (isset($rows[0]['Password']) && $rows[0]['Password'] != '') {
            $rows[0]['Password'] = decryptMessage($rows[0]['Password']);
        }

        $gwRs = new Cc_Hosted_GatewayRS();
        EditRS::loadRow($rows[0], $gwRs);

        $this->useAVS = filter_var($gwRs->Use_AVS_Flag->getStoredVal(), FILTER_VALIDATE_BOOLEAN);
        $this->useCVV = filter_var($gwRs->Use_Ccv_Flag->getStoredVal(), FILTER_VALIDATE_BOOLEAN);

        return $rows[0];
    }

    protected function setCredentials($gwRow) {

        $this->credentials = $gwRow;
    }

    public function createEditMarkup(\PDO $dbh, $resultMessage = '') {

        $stmt = $dbh->query("Select * from cc_hosted_gateway where Gateway_Name = 'vantiv'");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $tbl = new HTMLTable();

        foreach ($rows as $r) {

            $indx = $r['idcc_gateway'];
            $tbl->addBodyTr(HTMLTable::makeTd($r['cc_name'], array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($r['Merchant_Id'], array('name' => $indx . '_txtMid')))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($r['Password'], array('name' => $indx . '_txtpw')))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => $indx . '_txtpw2')))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('type' => 'checkbox', 'name' => $indx . 'cbCVV')))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('type' => 'checkbox', 'name' => $indx . 'cbAVS')))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('type' => 'checkbox', 'name' => $indx . 'cbDel'))));
        }

        if ($resultMessage != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($resultMessage, array('colspan' => '6', 'style' => 'font-weight:bold;')));
        }

        $tbl->addHeader(HTMLTable::makeTh('Name') . HTMLTable::makeTh('Merchant Id')
                . HTMLTable::makeTh('Password') . HTMLTable::makeTh('Password Again') . HTMLTable::makeTh('use CVV') . HTMLTable::makeTh('use AVS') . HTMLTable::makeTh('Delete'));

        return $tbl->generateMarkup();
    }

    public function SaveEditMarkup(\PDO $dbh, $post) {

        $msg = '';
        $ccRs = new Cc_Hosted_GatewayRS();
        $ccRs->Gateway_Name->setStoredVal($this->getGatewayName());
        $rows = EditRS::select($dbh, $ccRs, array($ccRs->Gateway_Name));

        foreach ($rows as $r) {

            EditRS::loadRow($r, $ccRs);

            $indx = $ccRs->idcc_gateway->getStoredVal();

            // Clear the entries??
            if (isset($post[$indx . 'cbDel'])) {

                $ccRs->Merchant_Id->setNewVal('');
                $ccRs->Password->setNewVal('');
                $num = EditRS::update($dbh, $ccRs, array($ccRs->idcc_gateway));
                $msg .= HTMLContainer::generateMarkup('p', $ccRs->cc_name->getStoredVal() . " - Payment Credentials Deleted.  ");
            } else {

                if (isset($post[$indx . '_txtMid'])) {
                    $mid = filter_var($post[$indx . '_txtMid'], FILTER_SANITIZE_STRING);
                    $ccRs->Merchant_Id->setNewVal($mid);
                }

                if (isset($post[$indx . '_txtpw']) && isset($post[$indx . '_txtpw2']) && $post[$indx . '_txtpw2'] != '') {

                    $pw = filter_var($post[$indx . '_txtpw'], FILTER_SANITIZE_STRING);
                    $pw2 = filter_var($post[$indx . '_txtpw2'], FILTER_SANITIZE_STRING);

                    if ($pw != '' && $pw != $ccRs->Password->getStoredVal()) {

                        // Don't save the pw blank characters
                        if ($pw == $pw2) {

                            $ccRs->Password->setNewVal(encryptMessage($pw));
                        } else {
                            // passwords don't match
                            $msg .= HTMLContainer::generateMarkup('p', $ccRs->cc_name->getStoredVal() . " - Passwords do not match.  ");
                        }
                    }
                }

                if (isset($post[$indx . 'cbCVV'])) {
                    $ccRs->Use_Ccv_Flag->setNewVal(1);
                } else {
                    $ccRs->Use_Ccv_Flag->setNewVal(0);
                }

                if (isset($post[$indx . 'cbAVS'])) {
                    $ccRs->Use_AVS_Flag->setNewVal(1);
                } else {
                    $ccRs->Use_AVS_Flag->setNewVal(0);
                }

                // Save record.
                $num = EditRS::update($dbh, $ccRs, array($ccRs->Gateway_Name, $ccRs->idcc_gateway));

                if ($num > 0) {
                    $msg .= HTMLContainer::generateMarkup('p', $ccRs->cc_name->getStoredVal() . " - Payment Credentials Updated.  ");
                } else {
                    $msg .= HTMLContainer::generateMarkup('p', $ccRs->cc_name->getStoredVal() . " - No changes detected.  ");
                }
            }
        }

        return $msg;
    }

}
