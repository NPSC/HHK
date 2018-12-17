<?php


/**
 * Description of PaymentGateway
 *
 * @author Eric
 */
abstract class PaymentGateway {

    const VANTIV = 'vantiv';
    const INSTAMED = 'instamed';
    const LOCAL = '';

    protected $gwName;
    protected $credentials;
    protected $responseErrors;
    protected $useAVS;
    protected $useCVV;


    public function __construct(\PDO $dbh, $gwName) {

        $this->setGwName($gwName);
        $this->setCredentials($this->loadGateway($dbh));
    }

    /**
     *  Get the gateway information from the database.
     */
    protected abstract function loadGateway(\PDO $dbh);

    /**
     *  Interpret database info into payment gateway credentials object.
     */
    protected abstract function setCredentials($credentials);

    public abstract function creditSale(\PDO $dbh, $pmp, $invoice, $postbackUrl);
    public abstract function processHostedReturn(\PDO $dbh, $post, $token, $idInv, $payNotes);

    public abstract function createEditMarkup(\PDO $dbh);
    public abstract function SaveEditMarkup(\PDO $dbh, $post);

    public static function logGwTx(PDO $dbh, $status, $request, $response, $transType) {

        $gwRs = new Gateway_TransactionRS();

        $gwRs->Vendor_Response->setNewVal($response);
        $gwRs->Vendor_Request->setNewVal($request);
        $gwRs->GwResultCode->setNewVal($status);
        $gwRs->GwTransCode->setNewVal($transType);

        return EditRS::insert($dbh, $gwRs);

    }

    public function updatePayTypes(\PDO $dbh, $username) {

        $uS = Session::getInstance();
        $msg = '';

        $glRs = new GenLookupsRS();
        $glRs->Table_Name->setStoredVal('Pay_Type');
        $glRs->Code->setStoredVal(PayType::Charge);
        $rows = EditRS::select($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));

        if (count($rows) > 0) {
            $glRs = new GenLookupsRS();
            EditRS::loadRow($rows[0], $glRs);


            if ($this->getGwName() != PaymentGateway::LOCAL) {
                $glRs->Substitute->setNewVal(PaymentMethod::Charge);
            } else {
                $glRs->Substitute->setNewVal(PaymentMethod::ChgAsCash);
            }


            $ctr = EditRS::update($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));

            if ($ctr > 0) {
                $logText = HouseLog::getUpdateText($glRs);
                HouseLog::logGenLookups($dbh, 'Pay_Type', PayType::Charge, $logText, "update", $username);
                $msg = "Pay_Type Charge is updated.  ";
            }
        }

        return $msg;
    }

    public static function factory(\PDO $dbh, $gwType, $gwName) {

        switch ($gwType) {

            case PaymentGateway::VANTIV:

                return new VantivGateway($dbh, $gwName);
                break;

            case PaymentGateway::INSTAMED:

                return new InstamedGateway($dbh, $gwName);
                break;

            default:

                return new LocalGateway($dbh, $gwName);
        }
    }

    public function getGwName() {
        return $this->gwName;
    }

    public function getResponseErrors() {
        return $this->responseErrors;
    }

    public function getCredentials() {
        return $this->credentials;
    }

    protected function setGwName($gwName) {
        $this->gwName = $gwName;
        return $this;
    }

    public function useAVS() {
        if ($this->credentials->Use_AVS_Flag->getStoredVal() > 0) {
            return TRUE;
        }
        return FALSE;
    }

}

class VantivGateway extends PaymentGateway {

    const CARD_ID = 'CardID';
    const PAYMENT_ID = 'PaymentID';

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
            $tokenResp = TokenTX::CreditSaleToken($dbh, $invoice->getSoldToId(), $uS->ccgw, $cpay, $pmp->getPayNotes());

            // Analyze the result
            $payResult = self::AnalyzeCredSaleResult($dbh, $tokenResp, $invoice, $pmp->getIdToken());


        } else {

            // Initialiaze hosted payment
            $fwrder = $this->initHostedPayment($dbh, $invoice, $guest, $addr, $postbackUrl);

            $payIds = array();
            if (isset($uS->paymentIds)) {
                $payIds = $uS->paymentIds;
            }
            $payIds[$fwrder['PaymentId']] = $invoice->getIdInvoice();
            $uS->paymentIds = $payIds;
            $uS->paymentNotes = $pmp->getPayNotes();

            $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
            $payResult->setForwardHostedPayment($fwrder);
            $payResult->setDisplayMessage('Forward to Payment Page. ');

        }

        return $payResult;

    }

    public function voidSale(\PDO $dbh, $invoice, PaymentRS $payRs, $paymentNotes, $bid) {

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

    public function reverseSale(\PDO $dbh, PaymentRS $payRs, $invoice, $bid, $paymentNotes) {

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

                $csResp = TokenTX::creditReverseToken($dbh, $payRs->idPayor->getstoredVal(), $this->gwName, $revRequest, $payRs, $paymentNotes);

                switch ($csResp->response->getStatus()) {

                    case MpStatusValues::Approved:

                        // Update invoice
                        $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizeAmount(), $uS->username);

                        $reply .= 'Payment is reversed.  ';
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

    public function returnSale(\PDO $dbh, PaymentRS $payRs, Invoice $invoice, $returnAmt, $bid) {

        // find the token
        if ($payRs->idToken->getStoredVal() > 0) {
            $tknRs = CreditToken::getTokenRsFromId($dbh, $payRs->idToken->getStoredVal());
        } else {
            return array('warning' => 'Return Failed.  Payment Token not found.  ', 'bid' => $bid);
        }

        // Find hte detail record.
        $stmt = $dbh->query("Select * from payment_auth where idPayment = " . $payRs->idPayment->getStoredVal() . " order by idPayment_auth");
        $arows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($arows) < 1) {
            return array('warning' => 'Payment Detail record not found.  Unable to Return. ', 'bid' => $bid);
        }

        $pAuthRs = new Payment_AuthRS();
        EditRS::loadRow(array_pop($arows), $pAuthRs);

        if ($pAuthRs->Status_Code->getStoredVal() != PaymentStatusCode::Paid && $pAuthRs->Status_Code->getStoredVal() != PaymentStatusCode::VoidReturn) {
            return array('warning' => 'This Payment is ineligable for Return. ', 'bid' => $bid);
        }


        // Set up request
        $returnRequest = new CreditReturnTokenRequest();
        $returnRequest->setCardHolderName($tknRs->CardHolderName->getStoredVal());
        $returnRequest->setFrequency(MpFrequencyValues::OneTime)->setMemo(MpVersion::PosVersion);
        $returnRequest->setInvoice($invoice->getInvoiceNumber());

        // Determine amount to return
        if ($returnAmt == 0) {
            $returnRequest->setPurchaseAmount($pAuthRs->Approved_Amount->getStoredVal());
        } else if ($returnAmt <= $pAuthRs->Approved_Amount->getStoredVal()) {
            $returnRequest->setPurchaseAmount($returnAmt);
        } else {
            return array('warning' => 'Return Failed.  Return amount is larger than the original purchase amount.  ', 'bid' => $bid);
        }

        $returnRequest->setToken($tknRs->Token->getStoredVal());
        $returnRequest->setTokenId($tknRs->idGuest_token->getStoredVal());

        try {

            $csResp = TokenTX::creditReturnToken($dbh, $payRs->idPayor->getstoredVal(), $uS->ccgw, $returnRequest, $payRs);

            switch ($csResp->response->getStatus()) {

                case MpStatusValues::Approved:


                    // Update invoice
                    $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizeAmount(), $uS->username);

                    $reply .= 'Payment is Returned.  ';
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

    public function initHostedPayment(\PDO $dbh, Invoice $invoice, Guest $guest, $addr, $postbackUrl) {

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

        $pay    ->setAVSZip($addr["Postal_Code"])
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

        $CreditCheckOut = HostedCheckout::sendToPortal($dbh, $this->gwName, $invoice->getSoldToId(), $invoice->getIdGroup(), $invoice->getInvoiceNumber(), $pay);

        return $CreditCheckOut;
    }

    public function initCardOnFile(\PDO $dbh, $pageTitle, $idGuest, $idGroup, $cardHolderName, $postBackPage) {

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


        return CardInfo::sendToPortal($dbh, $this->gwName, $idGuest, $idGroup, $initCi);
    }

    public function processHostedReturn(\PDO $dbh, $post, $token, $idInv, $payNotes, $userName = '') {

        $payResult = NULL;
        $rtnCode = '';

        if (isset($post['ReturnCode'])) {
            $rtnCode = intval(filter_var($post['ReturnCode'], FILTER_SANITIZE_NUMBER_INT), 10);
        }


        if (isset($post[VantivGateway::CARD_ID])) {

            $cardId = filter_var($post[VantivGateway::CARD_ID], FILTER_SANITIZE_STRING);

            // Save postback in the db.
            try {
                Gateway::saveGwTx($dbh, $rtnCode, '', json_encode($post), 'CardInfoPostBack');
            } catch (Exception $ex) {
                // Do nothing
            }

            try {

                $vr = CardInfo::portalReply($dbh, $this->getGwName(), $cardId, $post);

                $payResult = new CofResult($vr->response->getDisplayMessage(), $vr->response->getStatus(), $vr->idPayor, $vr->idRegistration);

            } catch (Hk_Exception_Payment $hex) {
                $payResult = new cofResult($hex->getMessage(), PaymentResult::ERROR, 0, 0);
            }

        } else if (isset($post[VantivGateway::PAYMENT_ID])) {

            $paymentId = filter_var($post[VantivGateway::PAYMENT_ID], FILTER_SANITIZE_STRING);

            try {
                Gateway::saveGwTx($dbh, $rtnCode, '', json_encode($post), 'HostedCoPostBack');
            } catch (Exception $ex) {
                // Do nothing
            }

            try {

                $csResp = HostedCheckout::portalReply($dbh, $this->getGwName(), $paymentId, $payNotes);

                if ($csResp->getInvoice() != '') {

                    $invoice = new Invoice($dbh, $csResp->getInvoice());

                    // Analyze the result
                    $payResult = self::AnalyzeCredSaleResult($dbh, $csResp, $invoice);

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

            $csResp = TokenTX::creditVoidSaleToken($dbh, $payRs->idPayor->getstoredVal(), $uS->ccgw, $voidRequest, $payRs, $paymentNotes);

            switch ($csResp->response->getStatus()) {

                case MpStatusValues::Approved:

                    // Update invoice
                    $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizeAmount(), $uS->username);

                    $csResp->idVisit = $invoice->getOrderNumber();
                    $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createVoidMarkup($dbh, $csResp, $uS->siteName, $uS->sId)));
                    $dataArray['success'] = 'Payment is void.  ';

                    break;

                case MpStatusValues::Declined:

                    if (strtoupper($csResp->response->getMessage()) == 'ITEM VOIDED') {

                        // Update invoice
                        $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizeAmount(), $uS->username);

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

            $dataArray['warning'] =  "Void Error = " . $exPay->getMessage();
        }

        return $dataArray;
    }

    protected function loadGateway(\PDO $dbh) {

        $query = "select * from `cc_hosted_gateway` where cc_name = :ccn";
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':ccn'=>$this->gwName));

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

        return $gwRs;

    }

    protected function setCredentials($gwRs) {

        $this->credentials = $gwRs;

    }

    public function createEditMarkup(\PDO $dbh, $resultMessage = '') {

        $stmt = $dbh->query("Select * from cc_hosted_gateway");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $tbl = new HTMLTable();

        foreach ($rows as $r) {

            $indx = $r['idcc_gateway'];
            $tbl->addBodyTr(HTMLTable::makeTd($r['cc_name'], array('class'=>'tdlabel'))
            .HTMLTable::makeTd(HTMLInput::generateMarkup($r['Merchant_Id'], array('name'=>$indx . '_txtMid')))
            .HTMLTable::makeTd(HTMLInput::generateMarkup($r['Password'], array('name'=>$indx .'_txtpw')))
            .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>$indx .'_txtpw2')))
             .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>$indx .'cbCVV')))
             .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>$indx .'cbAVS')))
             .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>$indx .'cbDel'))));
        }

        if ($resultMessage != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($resultMessage, array('colspan'=>'6', 'style'=>'font-weight:bold;')));
        }

        $tbl->addHeader(HTMLTable::makeTh('Name') . HTMLTable::makeTh('Merchant Id')
                . HTMLTable::makeTh('Password') . HTMLTable::makeTh('Password Again') . HTMLTable::makeTh('use CVV') . HTMLTable::makeTh('use AVS') . HTMLTable::makeTh('Delete'));

        return $tbl->generateMarkup();
    }

    public function SaveEditMarkup(\PDO $dbh, $post) {

        $msg = '';
        $ccRs = new Cc_Hosted_GatewayRS();
        $rows = EditRS::select($dbh, $ccRs, array());

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

                if  (isset($post[$indx . 'cbCVV'])) {
                    $ccRs->Use_Ccv_Flag->setNewVal(1);
                } else {
                    $ccRs->Use_Ccv_Flag->setNewVal(0);
                }

                if  (isset($post[$indx . 'cbAVS'])) {
                    $ccRs->Use_AVS_Flag->setNewVal(1);
                } else {
                    $ccRs->Use_AVS_Flag->setNewVal(0);
                }

                // Save record.
                $num = EditRS::update($dbh, $ccRs, array($ccRs->idcc_gateway));

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


/**
 * Description of InstamedGateway
 *
 * @author Eric
 */
class InstamedGateway extends PaymentGateway {

    const RELAY_STATE = 'relayState';
    const INVOICE_NUMBER = 'additionalInfo1';
    const GROUP_ID = 'additionalInfo2';

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


    protected $ssoUrl;
    protected $soapUrl;
    protected $NvpUrl;
    protected $saleUrl;
    protected $cofUrl;
    protected $returnUrl;
    protected $voidUrl;

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

    public function voidSale(\PDO $dbh, $invoice, PaymentRS $payRs, $paymentNotes, $bid) {

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

        if ($pAuthRs->Status_Code->getStoredVal() == PaymentStatusCode::Paid && $pAuthRs->Status_Code->getStoredVal() != PaymentStatusCode::VoidReturn) {

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

        $data = array (
            'patientID' => $patInfo['idName'],
            'patientFirstName' => $patInfo['Name_First'],
            'patientLastName' => $patInfo['Name_Last'],
            'amount' => $invoice->getAmountToPay(),

            InstamedGateway::GROUP_ID => $invoice->getIdGroup(),
            InstamedGateway::INVOICE_NUMBER => $invoice->getInvoiceNumber(),

            InstaMedCredentials::U_ID => $uS->uid,
            InstaMedCredentials::U_NAME => $uS->username,
            //'id' => 'NP.SOFTWARE.TEST',

            'incontext' => 'true',
            'lightWeight' => 'true',
            'isReadOnly' => 'true',
            'preventCheck' => 'true',
            'preventCash'  => 'true',
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
            Gateway::saveGwTx($dbh, $headerResponse->getResponseCode(), json_encode($req), json_encode($headerResponse->getResultArray()), 'HostedCoInit');
        } catch(Exception $ex) {
            // Do Nothing
        }

        if ($headerResponse->getToken() != '') {

            // Save payment ID
            $ciq = "replace into card_id (idName, `idGroup`, `Transaction`, InvoiceNumber, CardID, Init_Date, Frequency, ResponseCode, Amount)"
                . " values (" . $invoice->getSoldToId() . " , " . $invoice->getIdGroup() . ", '" . InstamedGateway::HCO_TRANS . "', '" . $invoice->getInvoiceNumber() . "', '" . $headerResponse->getToken() . "', now(), 'OneTime', '" . $headerResponse->getResponseCode() . "', " . $invoice->getAmountToPay() . ")";

            $dbh->exec($ciq);

            $uS->imtoken = $headerResponse->getToken();

            $dataArray = array('inctx' => $headerResponse->getRelayState(), 'PaymentId' => $headerResponse->getToken() );

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

        $data = array (
            'patientID' => $patInfo['idName'],
            'patientFirstName' => $patInfo['Name_First'],
            'patientLastName' => $patInfo['Name_Last'],

            InstamedGateway::GROUP_ID => $idGroup,
            InstaMedCredentials::U_ID => $uS->uid,
            InstaMedCredentials::U_NAME => $uS->username,
            //'id' => 'NP.SOFTWARE.TEST',
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
            Gateway::saveGwTx($dbh, $headerResponse->getResponseCode(), json_encode($allData), json_encode($headerResponse->getResultArray()), 'HostedCoInit');
        } catch(Exception $ex) {
            // Do Nothing
        }

        // Verify response
        if ($headerResponse->getToken() != '') {

            // Save payment ID
            $ciq = "replace into card_id (idName, `idGroup`, `Transaction`, InvoiceNumber, CardID, Init_Date, Frequency, ResponseCode)"
                . " values (" . $idGuest . " , " . $idGroup . ", '" . InstamedGateway::COF_TRANS . "', '', '" . $headerResponse->getToken() . "', now(), 'OneTime', " . $headerResponse->getResponseCode() . ")";

            $dbh->exec($ciq);

            $uS->imtoken = $headerResponse->getToken();

            $dataArray = array('inctx' => $headerResponse->getRelayState(), 'CardId' => $headerResponse->getToken() );

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
                . "&primaryTransactionID=" . $pAuthRs->Reference_Num->getStoredVal();

        $curlRequest = new CurlRequest();

        $resp = $curlRequest->submit($params, $this->NvpUrl);

        $resp['InvoiceNumber'] = $invoice->getInvoiceNumber();
        $resp['Amount'] = $payRs->Amount->getStoredVal();
        $resp['cardBrand'] = $pAuthRs->Card_Type->getStoredVal();
        $resp['lastFourDigits'] = $pAuthRs->Acct_Number->getStoredVal();

        $curlResponse = new VerifyCurlResponse($resp, MpTranType::Void);

        // Save raw transaction in the db.
        try {
            Gateway::saveGwTx($dbh, $curlResponse->getStatus(), $params, json_encode($curlResponse->getResultArray()), 'ImTokenVoid');
        } catch(Exception $ex) {
            // Do Nothing
        }

        // Make a void response...
        $sr = new ImVoidResponse($curlResponse, $payRs->idPayor->getStoredVal(), $invoice->getIdGroup(), $invoice->getInvoiceNumber(), $paymentNotes);

        // Record transaction
        try {
            $transRs = Transaction::recordTransaction($dbh, $sr, $this->gwName, TransType::Void, TransMethod::Token);
            $sr->setIdTrans($transRs->idTrans->getStoredVal());

        } catch(Exception $ex) {
            // do nothing
        }

        // Record payment
        $csResp = VoidReply::processReply($dbh, $sr, $uS->username, $payRs);


        switch ($csResp->getStatus()) {

            case CreditPayments::STATUS_APPROVED:

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizeAmount(), $uS->username);

                $csResp->idVisit = $invoice->getOrderNumber();
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createVoidMarkup($dbh, $csResp, $uS->siteName, $uS->sId)));
                $dataArray['success'] = 'Payment is void.  ';

                break;

            case CreditPayments::STATUS_DECLINED:

                if (strtoupper($csResp->response->getMessage()) == 'APPROVED') {

                    // Update invoice
                    $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizeAmount(), $uS->username);

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

        return $dataArray;
    }

    protected function sendReturn(\PDO $dbh, PaymentRS $payRs, Payment_AuthRS $pAuthRs, Invoice $invoice, $returnAmt, $bid) {

        $uS = Session::getInstance();
        $reply = '';

        $params = $this->getCredentials()->toCurl()
                . "&transactionType=CreditCard"
                . "&transactionAction=SimpleRefund"
                . "&primaryCardPresentStatus=PresentManualKey"
                . "&primaryTransactionID=" . $pAuthRs->Reference_Num->getStoredVal()
                . "&amount=" . number_format($returnAmt, 2);

        $curlRequest = new CurlRequest();

        $resp = $curlRequest->submit($params, $this->NvpUrl);

        $resp['InvoiceNumber'] = $invoice->getInvoiceNumber();
        $resp['Amount'] = $payRs->Amount->getStoredVal();

        $curlResponse = new VerifyCurlResponse($resp);

        // Save raw transaction in the db.
        try {
            Gateway::saveGwTx($dbh, $curlResponse->getStatus(), $params, json_encode($curlResponse->getResultArray()), 'ImTokenReturn');
        } catch(Exception $ex) {
            // Do Nothing
        }

        // Make a return response...
        $sr = new ImReturnResponse($curlResponse, $payRs->idPayor->getStoredVal(), $invoice->getIdGroup(), $invoice->getInvoiceNumber(), '');

        // Record transaction
        try {
            $transRs = Transaction::recordTransaction($dbh, $sr, $this->gwName, TransType::Void, TransMethod::Token);
            $sr->setIdTrans($transRs->idTrans->getStoredVal());

        } catch(Exception $ex) {
            // do nothing
        }

        // Record return
        $csResp = ReturnReply::processReply($dbh, $sr, $uS->username, $payRs);


        $dataArray = array('bid' => $bid);

        switch ($csResp->getStatus()) {

            case CreditPayments::STATUS_APPROVED:

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizeAmount(), $uS->username);

                $reply .= 'Payment is Returned.  ';
                $csResp->idVisit = $invoice->getOrderNumber();
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createReturnMarkup($dbh, $csResp, $uS->siteName, $uS->sId)));

                break;

            case CreditPayments::STATUS_DECLINED:

                $dataArray['warning'] = $csResp->response->getMessage();

                break;

            default:

                $dataArray['warning'] = "Payment Error = " . $exPay->getMessage();

        }

        return $dataArray;
    }

    public function processHostedReturn(\PDO $dbh, $post, $ssoToken, $idInv, $payNotes, $userName = '') {

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

            $payResult = new PaymentResult(0, 0, 0);
            $payResult->setDisplayMessage('User Canceled.');

            return $payResult;

        } else if ($transResult != InstamedGateway::POSTBACK_COMPLETE) {

            $payResult = new PaymentResult(0, 0, 0);
            $payResult->setDisplayMessage('Undefined Result: ' . $transResult);

            return $payResult;
        }

        if ($ssoToken === NULL || $ssoToken == '') {

            $payResult = new PaymentResult(0, 0, 0);
            $payResult->setDisplayMessage('Missing Token. ');

            return $payResult;
        }

        // Check DB for record
        $cidInfo = PaymentSvcs::getInfoFromCardId($dbh, $ssoToken);

        if (count($cidInfo) < 1) {

            $payResult = new PaymentResult(0, 0, 0);
            $payResult->setDisplayMessage('');

            return $payResult;
        }


        // Finally, process the transaction
        if ($transType == InstamedGateway::HCO_TRANS) {

            try {
                $payResult = $this->completeHostedPayment($dbh, $idInv, $ssoToken, $payNotes, $userName, $cidInfo);

            } catch (Hk_Exception_Payment $hex) {

                $payResult = new PaymentResult($idInv, 0, 0);
                $payResult->setStatus(PaymentResult::ERROR);
                $payResult->setDisplayMessage($hex->getMessage());
            }

        } else if ($transType == InstamedGateway::COF_TRANS) {

            $payResult = $this->completeCof($dbh, $ssoToken, $cidInfo);
        }

        return $payResult;
    }

    public function completeCof(\PDO $dbh, $ssoToken, $cidInfo) {

        //get transaction details
        $params = $this->getCredentials()->toCurl()
                . "&transactionAction=ViewReceipt"
                . "&requestToken=false"
                . "&singleSignOnToken=" . $ssoToken;

        $curl = new CurlRequest();
        $resp = $curl->submit($params, $this->NvpUrl);

        $resp['InvoiceNumber'] = 0;
        $resp['Amount'] = 0;

        $response = new VerifyCurlResponse($resp);

        // Save raw transaction in the db.
        try {
            Gateway::saveGwTx($dbh, $response->getStatus(), $params, json_encode($response->getResultArray()), 'COFVerify');
        } catch(Exception $ex) {
            // Do Nothing
        }

        $vr = new ImCofResponse($response, $cidInfo['idName'], $cidInfo['idGroup']);

        // save token
        CreditToken::storeToken($dbh, $vr->idRegistration, $vr->idPayor, $response);

        return new CofResult($vr->response->getDisplayMessage(), $vr->getStatus(), $vr->idPayor, $vr->idRegistration);

    }

    protected function completeHostedPayment(\PDO $dbh, $idInv, $ssoToken, $paymentNotes, $userName, $cidInfo) {

        //get transaction details
        $params = $this->getCredentials()->toCurl()
                . "&transactionAction=ViewReceipt"
                . "&requestToken=false"
                . "&singleSignOnToken=" . $ssoToken;

        $curl = new CurlRequest();
        $resp = $curl->submit($params, $this->NvpUrl);

        $resp['InvoiceNumber'] = $cidInfo['InvoiceNumber'];
        $resp['Amount'] = $cidInfo['Amount'];

        $curlResponse = new VerifyCurlResponse($resp, MpTranType::Sale);

        // Save raw transaction in the db.
        try {
            Gateway::saveGwTx($dbh, $curlResponse->getStatus(), $params, json_encode($curlResponse->getResultArray()), 'HostedCoVerify');
        } catch(Exception $ex) {
            // Do Nothing
        }

        // Make a sale response...
        $sr = new ImSaleResponse($curlResponse, $cidInfo['idName'], $cidInfo['idGroup'], $cidInfo['InvoiceNumber'], $paymentNotes);

        // Record transaction
        try {
            $transRs = Transaction::recordTransaction($dbh, $sr, $this->gwName, TransType::Sale, TransMethod::HostedPayment);
            $sr->setIdTrans($transRs->idTrans->getStoredVal());

        } catch(Exception $ex) {
            // do nothing
        }

        // record payment
        $ssr = SaleReply::processReply($dbh, $sr, $userName);


        if ($ssr->getInvoice() != '') {

            $invoice = new Invoice($dbh, $ssr->getInvoice());

            // Analyze the result
            $payResult = PaymentSvcs::AnalyzeCredSaleResult($dbh, $ssr, $invoice, 0, FALSE, FALSE);

        } else {

            $payResult = new PaymentResult($idInv, 0, 0);
            $payResult->setStatus(PaymentResult::ERROR);
            $payResult->setDisplayMessage('Invoice Not Found!  ');
        }

        return $payResult;
    }

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
            throw new Hk_Exception_Runtime('The credit card payment gateway is not found: ' . $this->getGwName() .'.  ');
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
            'header' => 'Content-Type: application/x-www-form-urlencoded,'.'\r\n'.
            'Content-Length:'.strlen($data).'\r\n'.
            'Expect: 100-continue,'.'\r\n'.
            'Connection: Keep-Alive,'.'\r\n',
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



    public function createEditMarkup(\PDO $dbh, $resultMessage = '') {

        $gwRs = new InstamedGatewayRS();
        $rows = EditRS::select($dbh, $gwRs, array());

        $tbl = new HTMLTable();

        foreach ($rows as $r) {

            $gwRs = new InstamedGatewayRS();
            EditRS::loadRow($r, $gwRs);

            $indx = $gwRs->idcc_gateway->getStoredVal();

            $tbl->addBodyTr(
                    HTMLTable::makeTh('Name', array('style'=>'border-top:2px solid black;', 'class'=>'tdlabel'))
                    .HTMLTable::makeTd($gwRs->cc_name->getStoredVal(), array('style'=>'border-top:2px solid black;'))
            );

            $tbl->addBodyTr(
                    HTMLTable::makeTh('Account Id', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->account_Id->getStoredVal(), array('name'=>$indx . '_txtaid', 'size'=>'80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Security Key', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->security_Key->getStoredVal(), array('name'=>$indx .'_txtsk', 'size'=>'80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('SSO Alias', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->sso_Alias->getStoredVal(), array('name'=>$indx .'_txtsalias', 'size'=>'80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Merchant Id', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->merchant_Id->getStoredVal(), array('name'=>$indx .'_txtuid', 'size'=>'80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Store Id', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->store_Id->getStoredVal(), array('name'=>$indx .'_txtuname', 'size'=>'80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Terminal Id', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->terminal_Id->getStoredVal(), array('name'=>$indx .'_txttremId', 'size'=>'80')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('SSO URL', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->providersSso_Url->getStoredVal(), array('name'=>$indx .'_txtpurl', 'size'=>'90')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('SOAP WSDL', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->soap_Url->getStoredVal(), array('name'=>$indx .'_txtsurl', 'size'=>'90')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('NVP URL', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->nvp_Url->getStoredVal(), array('name'=>$indx .'_txtnvpurl', 'size'=>'90')))
            );
//            $tbl->addBodyTr(
//                    HTMLTable::makeTh('Card on File URL', array('class'=>'tdlabel'))
//                    .HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->COF_Url->getStoredVal(), array('name'=>$indx .'_txtcofurl', 'size'=>'90')))
//            );

        }

        if ($resultMessage != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($resultMessage, array('colspan'=>'2', 'style'=>'font-weight:bold;')));
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
    const U_NAME = 'userName';
    const U_ID = 'userID';

    public $merchantId;
    public $storeId;

    protected $securityKey;
    protected $accountID;
    protected $terminalId;
    protected $ssoAlias;
    protected $id;


    public function __construct(InstamedGatewayRS $gwRs) {

        $this->accountID = $gwRs->account_Id->getStoredVal();
        $this->securityKey = $gwRs->security_Key->getStoredVal();
        $this->ssoAlias = $gwRs->sso_Alias->getStoredVal();
        $this->merchantId = $gwRs->merchant_Id->getStoredVal();
        $this->storeId = $gwRs->store_Id->getStoredVal();
        $this->terminalId = $gwRs->terminal_Id->getStoredVal();

        $parts = explode('@', $this->accountID);

        $this->id = $parts[0];

    }

    public function toSSO() {

        return array(
            InstaMedCredentials::ACCT_ID => $this->accountID,
            InstaMedCredentials::SEC_KEY => decryptMessage($this->securityKey),
            InstaMedCredentials::SSO_ALIAS => $this->ssoAlias,
            InstaMedCredentials::ID => $this->id,
        );
    }

    public function toCurl() {

        return
            InstaMedCredentials::MERCHANT_ID . '=' . $this->merchantId
            . '&' . InstaMedCredentials::STORE_ID . '=' . $this->storeId
            . '&' . InstaMedCredentials::TERMINAL_ID . '=' . $this->terminalId;

    }

    public function toSOAP() {

        return array(
            InstaMedCredentials::ACCT_ID => $this->accountID,
            'password' => decryptMessage($this->securityKey),
            'alias' => $this->ssoAlias,
        );
    }

}


//class PollingRequest extends SoapRequest {
//
//    protected function execute(\SoapClient $soapClient, $data) {
//        return new PollingResponse($soapClient->GetSSOTokenStatus($data));
//    }
//}


class LocalGateway extends PaymentGateway {

    protected function loadGateway(\PDO $dbh) {

    }

    protected function setCredentials($credentials) {

    }

    public function SaveEditMarkup(\PDO $dbh, $post) {

    }

    public function createEditMarkup(\PDO $dbh) {
        return '';
    }

    public function creditSale(\PDO $dbh, $pmp, $invoice, $postbackUrl) {

    }

    public function processHostedReturn(\PDO $dbh, $post, $token, $idInv, $payNotes) {

    }

}