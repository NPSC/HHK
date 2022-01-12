<?php

namespace HHK\Payment\PaymentGateway\Vantiv;

use HHK\Payment\{CreditToken, Receipt};
use HHK\Payment\Invoice\Invoice;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Payment\PaymentGateway\CreditPayments\AbstractCreditPayments;
use HHK\Payment\PaymentGateway\Vantiv\Helper\{MpVersion, AVSResult, CVVResult};
use HHK\Payment\PaymentGateway\Vantiv\Request\{CreditReturnTokenRequest, CreditReversalTokenRequest, CreditSaleTokenRequest, CreditVoidReturnTokenRequest, CreditVoidSaleTokenRequest, InitCkOutRequest};
use HHK\Payment\PaymentManager\PaymentManagerPayment;
use HHK\Payment\PaymentResponse\AbstractCreditResponse;
use HHK\Payment\PaymentResult\{CofResult, PaymentResult, ReturnResult};
use HHK\SysConst\{MpFrequencyValues, MpStatusValues, MpTranType, PaymentMethod, PaymentStatusCode};
use HHK\Tables\EditRS;
use HHK\Tables\Payment\{PaymentRS, Payment_AuthRS};
use HHK\Tables\PaymentGW\CC_Hosted_GatewayRS;
use HHK\sec\{SecurityComponent, Session, SysConfig};
use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLSelector, HTMLTable};
use HHK\Exception\{RuntimeException, PaymentException};
use HHK\Payment\GatewayResponse\GatewayResponseInterface;

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

class VantivGateway extends AbstractPaymentGateway {

    const CARD_ID = 'CardID';
    const PAYMENT_ID = 'PaymentID';

    protected $paymentPageLogoUrl = '';
    protected $manualKey = FALSE;


    public static function getPaymentMethod() {
        return PaymentMethod::Charge;
    }

    public function getGatewayName() {
        return AbstractPaymentGateway::VANTIV;
    }

    public function creditSale(\PDO $dbh, PaymentManagerPayment $pmp, Invoice $invoice, $postbackUrl) {

        $uS = Session::getInstance();
        $payResult = NULL;

        if ($this->getGatewayType() == '') {
            // Undefined Gateway.
            $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
            $payResult->setStatus(PaymentResult::ERROR);
            $payResult->feePaymentError($dbh, $uS);
            $payResult->setDisplayMessage('Location not selected. ');

        } else {

            $tokenRS = CreditToken::getTokenRsFromId($dbh, $pmp->getIdToken());

            // Do we have a token?
            if (CreditToken::hasToken($tokenRS)) {

                $cpay = new CreditSaleTokenRequest();

                $cpay->setPurchaseAmount($invoice->getAmountToPay())
                        ->setTaxAmount(0)
                        ->setCustomerCode($invoice->getSoldToId())
                        ->setToken($tokenRS->Token->getStoredVal())
                        ->setPartialAuth(FALSE)
                        ->setFrequency(MpFrequencyValues::OneTime)
                        ->setInvoice($invoice->getInvoiceNumber())
                        ->setTokenId($tokenRS->idGuest_token->getStoredVal())
                        ->setMemo(MpVersion::PosVersion)
                        ->setOperatorID($uS->username);

                // Run the token transaction
                $tokenResp = TokenTX::CreditSaleToken($dbh, $invoice->getSoldToId(), $invoice->getIdGroup(), $this, $cpay, $pmp->getPayNotes(), $pmp->getPayDate());

                // Analyze the result
                $payResult = $this->analyzeCredSaleResult($dbh, $tokenResp, $invoice, $pmp->getIdToken());

            } else {

            	$this->manualKey = $pmp->getManualKeyEntry();

                // Initialiaze hosted payment
                $fwrder = $this->initHostedPayment($dbh, $invoice, $postbackUrl);

                $payIds = array();
                if (isset($uS->paymentIds)) {
                    $payIds = $uS->paymentIds;
                }

                $payIds[$fwrder['paymentId']] = $invoice->getIdInvoice();
                $uS->paymentIds = $payIds;
                $uS->paymentNotes = $pmp->getPayNotes();
                $uS->paymentDate = $pmp->getPayDate();

                $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
                $payResult->setForwardHostedPayment($fwrder);
                $payResult->setDisplayMessage('Forward to Payment Page. ');
            }
        }

        return $payResult;
    }

    public function voidReturn(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $bid) {

        $uS = Session::getInstance();

        // find the token record
        if ($payRs->idToken->getStoredVal() > 0) {
            $tknRs = CreditToken::getTokenRsFromId($dbh, $payRs->idToken->getStoredVal());
        } else {
            return array('warning' => 'Card-on-File not found.  Unable to Void this return.  ', 'bid' => $bid);
        }

        if (CreditToken::hasToken($tknRs) === FALSE) {
            return array('warning' => 'Card-on-File not found.  Unable to Void this return.  ', 'bid' => $bid);
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

            $csResp = TokenTX::creditVoidReturnToken($dbh, $payRs->idPayor->getstoredVal(), $invoice->getIdGroup(), $this, $revRequest, $payRs, date('Y-m-d H:i:s'));

            switch ($csResp->getStatus()) {

                case AbstractCreditPayments::STATUS_APPROVED:

                    // Update invoice
                    $invoice->updateInvoiceBalance($dbh, $csResp->response->getAuthorizedAmount(), $uS->username);

                    $csResp->idVisit = $invoice->getOrderNumber();
                    $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createVoidMarkup($dbh, $csResp, $uS->siteName, $uS->sId, 'Void Return')));
                    $dataArray['success'] = 'Return is Voided.  ';

                    break;

                case AbstractCreditPayments::STATUS_DECLINED:

                    $dataArray['success'] = 'Declined.';
                    break;

                default:

                    $dataArray['warning'] = '** Void-Return Invalid or Error. **  ' . 'Message: ' . $csResp->response->getMessage();

            }

        } catch (PaymentException $exPay) {

            $dataArray['warning'] = "Void-Return Error = " . $exPay->getMessage();
        }

        $dataArray['bid'] = $bid;
        return $dataArray;
    }

    public function reverseSale(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $bid) {

        $uS = Session::getInstance();
        $dataArray = array();

        // find the token record
        if ($payRs->idToken->getStoredVal() > 0) {
            $tknRs = CreditToken::getTokenRsFromId($dbh, $payRs->idToken->getStoredVal());
        } else {
            return array('warning' => 'Payment Token Id not found.  Unable to Reverse this purchase.  ', 'bid' => $bid);
        }

        if (CreditToken::hasToken($tknRs) === FALSE) {
            return array('warning' => 'Payment Token not found.  Unable to Reverse this purchase.  ', 'bid' => $bid);
        }

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

                $csResp = TokenTX::creditReverseToken($dbh, $payRs->idPayor->getstoredVal(), $invoice->getIdGroup(), $this, $revRequest, $payRs, date('Y-m-d H:i:s'));

                switch ($csResp->response->getStatus()) {

                    case MpStatusValues::Approved:

                        // Update invoice
                        $invoice->updateInvoiceBalance($dbh, 0 - $csResp->response->getAuthorizedAmount(), $uS->username);


                        $csResp->idVisit = $invoice->getOrderNumber();
                        $dataArray['receipt'] = HTMLContainer::generateMarkup('div', nl2br(Receipt::createVoidMarkup($dbh, $csResp, $uS->siteName, $uS->sId, 'Reverse Sale')));
                        $dataArray['success'] = 'Transaction Reversed.  ';

                        break;

                    case MpStatusValues::Declined:

                        // Try Void
                        $dataArray = $this->_voidSale($dbh, $invoice, $payRs, $pAuthRs, $bid);
                        $dataArray['reversal'] = 'Reversal Declined, trying Void.  ';

                        break;

                    default:

                        $dataArray['warning'] = '** Reversal Invalid or Error. **  ' . 'Message: ' . $csResp->response->getMessage();
                }
            } catch (PaymentException $exPay) {

                $dataArray['warning'] = "Reversal Error = " . $exPay->getMessage();
            }

            $dataArray['bid'] = $bid;
            return $dataArray;
        }

        return array('warning' => 'Payment is ineligable for reversal.  ', 'bid' => $bid);
    }

    // Returns a Payment
    protected function _returnPayment(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $returnAmt, $bid) {

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

            $csResp = TokenTX::creditReturnToken($dbh, $payRs->idPayor->getstoredVal(), $invoice->getIdGroup(), $this, $returnRequest, $payRs, date('Y-m-d H:i:s'));

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

        } catch (PaymentException $exPay) {

            return array('warning' => "Payment Error = " . $exPay->getMessage(), 'bid' => $bid);
        }

        return $dataArray;
    }

    public function returnAmount(\PDO $dbh, Invoice $invoice, $rtnToken, $payNotes) {

        $uS = Session::getInstance();
        $rtnResult = NULL;
        $tokenRS = CreditToken::getTokenRsFromId($dbh, $rtnToken);
        $amount = abs($invoice->getAmount());

        // Do we have a token?
        if (CreditToken::hasToken($tokenRS)) {

            if ($tokenRS->Running_Total->getStoredVal() < $amount) {
                throw new PaymentException('Return Failed.  Maximum return for this card is: $' . number_format($tokenRS->Running_Total->getStoredVal(), 2));
            }

            // Set up request
            $returnRequest = new CreditReturnTokenRequest();
            $returnRequest->setCardHolderName($tokenRS->CardHolderName->getStoredVal());
            $returnRequest->setFrequency(MpFrequencyValues::OneTime)->setMemo(MpVersion::PosVersion);
            $returnRequest->setInvoice($invoice->getInvoiceNumber());
            $returnRequest->setPurchaseAmount($amount);

            $returnRequest->setToken($tokenRS->Token->getStoredVal());
            $returnRequest->setTokenId($tokenRS->idGuest_token->getStoredVal());


            $tokenResp = TokenTX::creditReturnToken($dbh, $invoice->getSoldToId(), $invoice->getIdGroup(), $this, $returnRequest, NULL, date('Y-m-d H:i:s'));
            $tokenResp->setPaymentNotes($payNotes);

            // Analyze the result
            $rtnResult = new ReturnResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId(), $tokenRS->idGuest_token->getStoredVal());

            switch ($tokenResp->getStatus()) {

                case AbstractCreditPayments::STATUS_APPROVED:

                    // Update invoice
                    $invoice->updateInvoiceBalance($dbh, 0 - $tokenResp->response->getAuthorizedAmount(), $uS->username);

                    $rtnResult->feePaymentAccepted($dbh, $uS, $tokenResp, $invoice);
                    $rtnResult->setDisplayMessage('Refund by Credit Card.  ');

                    break;

                case AbstractCreditPayments::STATUS_DECLINED:

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
            throw new PaymentException('Return Failed.  Credit card token not found.  ');
        }

        return $rtnResult;
    }

    Protected function initHostedPayment(\PDO $dbh, Invoice $invoice, $postbackUrl) {

        $uS = Session::getInstance();

        // Do a hosted payment.
        $secure = new SecurityComponent();
        $houseUrl = $secure->getSiteURL();

        if ($houseUrl == '') {
            throw new RuntimeException("The house URL is missing.  ");
        }

        if ($invoice->getSoldToId() < 1 || $invoice->getIdGroup() < 1) {
            throw new RuntimeException("The Invoice is missing.  ");
        }

        // Set CC Gateway name
        $uS->ccgw = $this->getGatewayType();
        $uS->manualKey = $this->manualKey;

        $pay = new InitCkOutRequest($uS->siteName, 'Custom');

        // Payment Logo
        if ($this->getPaymentPageLogoUrl() != '') {
        	$pay->setLogoUrl($secure->getRootURL() . $this->getPaymentPageLogoUrl());
        }

        // Card reader?
        if ($this->usePOS && ! $this->manualKey) {
            $pay->setCardEntryMethod('swipe')
            		->setPaymentPageCode('CheckoutPOS_Url');
        } else {
        	$pay->setPaymentPageCode('Checkout_Url');
        }


         $pay->setFrequency(MpFrequencyValues::OneTime)
         		->setInvoice($invoice->getInvoiceNumber())
                ->setMemo(MpVersion::PosVersion)
                ->setPartialAuth(FALSE)
                ->setTaxAmount(0)
                ->setTotalAmount($invoice->getAmountToPay())
                ->setCompleteURL($houseUrl . $postbackUrl)
                ->setReturnURL($houseUrl . $postbackUrl)
                ->setTranType(MpTranType::Sale)
                ->setCVV($this->useCVV ? 'on' : '')
                ->setAVSFields('Zip');

        $CreditCheckOut = HostedCheckout::sendToPortal($dbh, $this, $invoice->getSoldToId(), $invoice->getIdGroup(), $invoice->getInvoiceNumber(), $pay);

        return $CreditCheckOut;
    }

    public function initCardOnFile(\PDO $dbh, $pageTitle, $idGuest, $idGroup, $manualKey, $cardHolderName, $postbackUrl, $selChgType = '', $chgAcct = '', $idx = '') {

        $uS = Session::getInstance();
        $secure = new SecurityComponent();

        $houseUrl = $secure->getSiteURL();
        $siteUrl = $secure->getRootURL();

        if ($houseUrl == '' || $siteUrl == '') {
            throw new RuntimeException("The site/house URL is missing.  ");
        }

        if ($this->getGatewayType() == '') {
            // Undefined Gateway.
            $dataArray['error'] = 'Location not selected. ';
            return $dataArray;
        }

        // This selects the correct merchant from the credentials
        $this->manualKey = $manualKey;

        // Set CC Gateway name
        $uS->ccgw = $this->getGatewayType();
        $uS->manualKey = $this->manualKey;

        $pay = new InitCkOutRequest($uS->siteName, 'Custom');

        // Payment Logo
        if ($this->getPaymentPageLogoUrl() != '') {
        	$pay->setLogoUrl($siteUrl . $this->getPaymentPageLogoUrl());
        }


        // Card reader?
        if ($this->usePOS && ! $this->manualKey) {
        	$pay->setCardEntryMethod('Swipe')
	        		->setPaymentPageCode('CheckoutPOS_Url');
        } else {
        	$pay->setPaymentPageCode('Checkout_Url');
        }

        // The rest...
        $pay ->setFrequency(MpFrequencyValues::OneTime)
	        	->setInvoice('CardInfo')
                ->setMemo(MpVersion::PosVersion)
                ->setTaxAmount('0.00')
                ->setTotalAmount('0.00')
                ->setCompleteURL($houseUrl . $postbackUrl)
                ->setReturnURL($houseUrl . $postbackUrl)
                ->setTranType(MpTranType::ZeroAuth)
                ->setCVV($this->useCVV ? 'on' : '')
                ->setAVSFields('Zip');

        return HostedCheckout::sendToPortal($dbh, $this, $idGuest, $idGroup, 'CardInfo', $pay);

    }

    public function processHostedReply(\PDO $dbh, $post, $ssoToken, $idInv, $payNotes, $payDate) {

    	$uS = Session::getInstance();
    	$payResult = NULL;
        $rtnCode = '';
        $rtnMessage = '';

        if (isset($post['ReturnCode'])) {
            $rtnCode = intval(filter_var($post['ReturnCode'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (isset($post['ReturnMessage'])) {
            $rtnMessage = filter_var($post['ReturnMessage'], FILTER_SANITIZE_STRING);
        }

        // THis eventually selects the merchant id
        if (isset($uS->manualKey)) {
        	$this->manualKey = $uS->manualKey;
        }


        if (isset($post[VantivGateway::PAYMENT_ID])) {

            $paymentId = filter_var($post[VantivGateway::PAYMENT_ID], FILTER_SANITIZE_STRING);

            $cidInfo = $this->getInfoFromCardId($dbh, $paymentId);

            try {
                self::logGwTx($dbh, $rtnCode, '', json_encode($post), 'HostedCoPostBack');
            } catch (\Exception $ex) {
                // Do nothing
            }

            if ($rtnCode > 0) {

                $payResult = new PaymentResult($idInv, $cidInfo['idGroup'], $cidInfo['idName']);
                $payResult->setStatus(PaymentResult::ERROR);
                $payResult->setDisplayMessage($rtnMessage);
                return $payResult;
            }

            try {

                $csResp = HostedCheckout::portalReply($dbh, $this, $cidInfo, $payNotes, $payDate);

                if ($csResp->response->getTranType() == MpTranType::ZeroAuth) {

                    // Zero auth card info
                    $payResult = new CofResult($csResp->response->getDisplayMessage(), $csResp->response->getStatus(), $csResp->idPayor, $csResp->idRegistration);

                    if ($this->useAVS) {
                        $avsResult = new AVSResult($csResp->response->getAVSResult());

                        if ($avsResult->isZipMatch() === FALSE) {
                            $payResult->setDisplayMessage($avsResult->getResultMessage() . '  ');
                        }
                    }

                    if ($this->useCVV) {
                        $cvvResult = new CVVResult($csResp->response->getCvvResult());
                        if ($cvvResult->isCvvMatch() === FALSE) {
                            $payResult->setDisplayMessage($cvvResult->getResultMessage() . '  ');
                        }
                    }

                } else {

                    // Hosted payment response.
                    if ($csResp->getInvoiceNumber() != '') {

                        $invoice = new Invoice($dbh, $csResp->getInvoiceNumber());

                        // Analyze the result
                        $payResult = $this->analyzeCredSaleResult($dbh, $csResp, $invoice, 0);

                    } else {

                        $payResult = new PaymentResult($idInv, $cidInfo['idGroup'], $cidInfo['idName']);
                        $payResult->setStatus(PaymentResult::ERROR);
                        $payResult->setDisplayMessage('Invoice Not Found!  ');
                    }
                }
            } catch (PaymentException $hex) {

                $payResult = new PaymentResult($idInv, $cidInfo['idGroup'], $cidInfo['idName']);
                $payResult->setStatus(PaymentResult::ERROR);
                $payResult->setDisplayMessage($hex->getMessage());
            }
        }

        return $payResult;
    }

    protected function _voidSale(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $bid) {

        $uS = Session::getInstance();
        $dataArray = array('bid'=>$bid);

        // find the token record
        if ($payRs->idToken->getStoredVal() > 0) {
            $tknRs = CreditToken::getTokenRsFromId($dbh, $payRs->idToken->getStoredVal());
        } else {
            return array('warning' => 'Payment Token Id not found.  Unable to Void this purchase.  ', 'bid' => $bid);
        }

        if (CreditToken::hasToken($tknRs) === FALSE) {
            return array('warning' => 'Payment Token not found.  Unable to Void this purchase.  ', 'bid' => $bid);
        }

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

            $csResp = TokenTX::creditVoidSaleToken($dbh, $payRs->idPayor->getstoredVal(), $invoice->getIdGroup(), $this, $voidRequest, $payRs, date('Y-m-d H:i:s'));

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
        } catch (PaymentException $exPay) {

            $dataArray['warning'] = "Void Error = " . $exPay->getMessage();
        }

        return $dataArray;
    }

    public function analyzeCredSaleResult(\PDO $dbh, AbstractCreditResponse $payResp, Invoice $invoice, $idToken) {

        $uS = Session::getInstance();

        $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId(), $idToken);


        switch ($payResp->getStatus()) {

            case AbstractCreditPayments::STATUS_APPROVED:

                // Update invoice
                $invoice->updateInvoiceBalance($dbh, $payResp->response->getAuthorizedAmount(), $uS->username);

                $payResult->feePaymentAccepted($dbh, $uS, $payResp, $invoice);
                $payResult->setDisplayMessage('Paid by Credit Card.  ');

                if ($payResp->isPartialPayment()) {
                    $payResult->setDisplayMessage('** Partially Approved Amount: ' . number_format($payResp->response->getAuthorizedAmount(), 2) . ' (Remaining Balance Due: ' . number_format($invoice->getBalance(), 2) . ').  ');
                }

                if ($this->useAVS) {
                    $avsResult = new AVSResult($payResp->response->getAVSResult());

                    if ($avsResult->isZipMatch() === FALSE) {
                        $payResult->setDisplayMessage($avsResult->getResultMessage() . '  ');
                    }
                }

                if ($this->useCVV) {
                    $cvvResult = new CVVResult($payResp->response->getCvvResult());
                    if ($cvvResult->isCvvMatch() === FALSE && $uS->CardSwipe === FALSE) {
                        $payResult->setDisplayMessage($cvvResult->getResultMessage() . '  ');
                    }
                }

                break;

            case AbstractCreditPayments::STATUS_DECLINED:

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

    public function getPaymentResponseObj(GatewayResponseInterface $creditTokenResponse, $idPayor, $idGroup, $invoiceNumber, $idToken = 0, $payNotes = '') {
        return new TokenResponse($creditTokenResponse, $idPayor, $idGroup, $idToken);
    }

    public function getCofResponseObj(GatewayResponseInterface $verifyCiResponse, $idPayor, $idGroup) {
        return new TokenResponse($verifyCiResponse, $idPayor, $idGroup, 0);
    }

    protected function loadGateway(\PDO $dbh) {

        $query = "select * from `cc_hosted_gateway` where `cc_name` = '" . $this->getGatewayType() . "' and `Gateway_Name` = '" .$this->getGatewayName()."'";
        $stmt = $dbh->query($query);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) < 1) {
            $rows[0] = array();
        }

        $gwRs = new CC_Hosted_GatewayRS();
        EditRS::loadRow($rows[0], $gwRs);

        $manualPassword = $gwRs->Manual_Password->getStoredVal();
        if ($manualPassword != '') {
        	$rows[0]['Manual_Password'] = decryptMessage($manualPassword);
        }

        $rows[0]['Manual_Mid'] = $gwRs->Manual_MerchantId->getStoredVal();

        $password = $gwRs->Password->getStoredVal();
        if ($password != '') {
        	$rows[0]['Password'] = decryptMessage($password);
        }

        $this->useAVS = filter_var($gwRs->Use_AVS_Flag->getStoredVal(), FILTER_VALIDATE_BOOLEAN);
        $this->useCVV = filter_var($gwRs->Use_Ccv_Flag->getStoredVal(), FILTER_VALIDATE_BOOLEAN);
        $this->usePOS = filter_var($gwRs->Retry_Count->getStoredVal(), FILTER_VALIDATE_BOOLEAN);

        $this->paymentPageLogoUrl = $gwRs->Page_Header_URL->getStoredVal();

        return $rows[0];
    }

    protected function setCredentials($gwRow) {

        $this->credentials = $gwRow;
    }

    public function getCredentials() {

    	if ($this->manualKey) {

    		$cred = new \ArrayObject($this->credentials);
    		$copy = $cred->getArrayCopy();

    		$copy['Merchant_Id'] = $copy['Manual_Mid'];
    		$copy['Password'] = $copy['Manual_Password'];

    		return $copy;

    	} else {
    		return $this->credentials;
    	}
    }

    public function getPaymentPageLogoUrl() {
    	return $this->paymentPageLogoUrl;
    }

    public function selectPaymentMarkup(\PDO $dbh, &$payTbl, $index = '') {

    	$selArray = array('name'=>'selccgw'.$index, 'class'=>'hhk-feeskeys'.$index, 'style'=>'width:min-content;', 'title'=>'Select the Location');
    	$manualArray =  array('type'=>'checkbox', 'name'=>'btnvrKeyNumber'.$index, 'class'=>'hhk-feeskeys'.$index, 'title'=>'Check to Key in credit account number');

        // Precheck the manual account number entry checkbox?
        if ($this->checkManualEntryCheckbox) {
        	$manualArray['checked'] = 'checked';
        }

        $keyCb = HTMLContainer::generateMarkup('span',
        		HTMLContainer::generateMarkup('label', 'Type: ', array('for'=>'btnvrKeyNumber'.$index, 'title'=>'Check to Key in credit account number')) .HTMLInput::generateMarkup('', $manualArray)
        , array('style'=>'float:right; margin-top:2px;'));

        if ($this->getGatewayType() != '') {
        	// A location is already selected.

            $sel = HTMLSelector::doOptionsMkup(array(0=>array(0=>$this->getGatewayType(), 1=> ucfirst($this->getGatewayType()))), $this->getGatewayType(), FALSE);

            $payTbl->addBodyTr(
                    HTMLTable::makeTh('Selected Location:', array('style'=>'text-align:right;'))
            		.HTMLTable::makeTd(HTMLSelector::generateMarkup($sel, $selArray)
            				. $keyCb
            				, array('colspan'=>'2'))
            		, array('id'=>'trvdCHName'.$index, 'class'=>'tblCredit'.$index)
            );

        } else {
			// Show all locations, none is preselected.

            $stmt = $dbh->query("Select DISTINCT l.`Merchant`, l.`Title` from `location` l join `room` r on l.idLocation = r.idLocation where r.idLocation is not null and l.`Status` = 'a'");
            $gwRows = $stmt->fetchAll();

            $selArray['size'] = count($gwRows);

            if (count($gwRows) == 1) {
            	// only one merchant
            	$sel = HTMLSelector::doOptionsMkup($gwRows, $gwRows[0][0], FALSE);
            } else {
            	$sel = HTMLSelector::doOptionsMkup($gwRows, '', FALSE);
            }

            $payTbl->addBodyTr(
            		HTMLTable::makeTh('Select a Location:', array('style'=>'text-align:right; width:130px;'))
                    .HTMLTable::makeTd(
                    		HTMLSelector::generateMarkup($sel, $selArray)
                    		. $keyCb
                    		, array('colspan'=>'2'))
                    , array('id'=>'trvdCHName'.$index, 'class'=>'tblCredit'.$index)
            );

        }

    }

    protected static function _createEditMarkup(\PDO $dbh, $gatewayName, $resultMessage = '') {

        $gwRs = new CC_Hosted_GatewayRS();
        $gwRs->Gateway_Name->setStoredVal($gatewayName);
        $rows = EditRS::select($dbh, $gwRs, array($gwRs->Gateway_Name));

        $opts = array(
            array(0, 'False'),
            array(1, 'True'),
        );

        $tbl = new HTMLTable();

        // Spacer
        $tbl->addBodyTr(HTMLTable::makeTd('&nbsp', array('colspan'=>'2')));

        foreach ($rows as $r) {

            $gwRs = new CC_Hosted_GatewayRS();
            EditRS::loadRow($r, $gwRs);

            $indx = $gwRs->idcc_gateway->getStoredVal();

            $tbl->addBodyTr(
                    HTMLTable::makeTh('Merchant Name', array('style' => 'border-top:2px solid black;'))
                    . HTMLTable::makeTd($gwRs->cc_name->getStoredVal(), array('style' => 'border-top:2px solid black;'))
            );

            $tbl->addBodyTr(
                    HTMLTable::makeTh('Merchant Id', array())
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->Merchant_Id->getStoredVal(), array('name' => $indx . '_txtuid', 'size' => '50')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Password', array())
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->Password->getStoredVal(), array('name' => $indx . '_txtpwd', 'size' => '90')) . ' (Obfuscated)')
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Credit URL', array())
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->Credit_Url->getStoredVal(), array('name' => $indx . '_txtcrdurl', 'size' => '90')))
            );
            $tbl->addBodyTr(
                    HTMLTable::makeTh('Token Trans URL', array())
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->Trans_Url->getStoredVal(), array('name' => $indx . '_txttransurl', 'size' => '90')))
            );


            $tbl->addBodyTr(
                    HTMLTable::makeTh('CheckoutPOS URL', array())
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->CheckoutPOS_Url->getStoredVal(), array('name' => $indx . '_txtcoposurl', 'size' => '90')))
            );

            $tbl->addBodyTr(
                        HTMLTable::makeTh('Checkout URL', array())
                        . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->Checkout_Url->getStoredVal(), array('name' => $indx . '_txtckouturl', 'size' => '90')))
            );

            $tbl->addBodyTr(
                		HTMLTable::makeTh('Manual Merchant Id', array())
                		. HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->Manual_MerchantId->getStoredVal(), array('name' => $indx . '_txtManMerchId', 'size' => '90')))
       		);
            $tbl->addBodyTr(
                		HTMLTable::makeTh('Manual Password', array())
                		. HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->Manual_Password->getStoredVal(), array('name' => $indx . '_txtManMerchPW', 'size' => '90')). ' (Obfuscated)')
            );
            $tbl->addBodyTr(
            		HTMLTable::makeTh('Use AVS', array())
            		.HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($opts, $gwRs->Use_AVS_Flag->getStoredVal(), FALSE), array('name' => $indx . '_txtuseAVS')))
            		);

            $tbl->addBodyTr(
                    HTMLTable::makeTh('Use CCV', array())
                    .HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($opts, $gwRs->Use_Ccv_Flag->getStoredVal(), FALSE), array('name' => $indx . '_txtuseCVV')))
            );

            $tbl->addBodyTr(
            		HTMLTable::makeTh('Use Card Swiper', array())
            		.HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($opts, $gwRs->Retry_Count->getStoredVal(), FALSE), array('name' => $indx . '_txtuseSwipe')))
            		);

            $tbl->addBodyTr(
            		HTMLTable::makeTh('Payment Page Logo URL', array())
            		. HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->Page_Header_URL->getStoredVal(), array('name' => $indx . '_txtpageLogourl', 'size' => '90')))
            		);
        }


        if ($resultMessage != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($resultMessage, array('colspan' => '2', 'style' => 'font-weight:bold;')));
        }

        return $tbl->generateMarkup();
    }

    protected static function _saveEditMarkup(\PDO $dbh, $gatewayName, $post) {

        $msg = '';
        $ccRs = new CC_Hosted_GatewayRS();
        $ccRs->Gateway_Name->setStoredVal($gatewayName);
        $rows = EditRS::select($dbh, $ccRs, array($ccRs->Gateway_Name));

        // Use POS
        if (isset($post['selCardSwipe'])) {
            SysConfig::saveKeyValue($dbh, 'sys_config', 'CardSwipe', filter_var($post['selCardSwipe'], FILTER_SANITIZE_STRING));
        }


        foreach ($rows as $r) {

            EditRS::loadRow($r, $ccRs);

            $indx = $ccRs->idcc_gateway->getStoredVal();

            // Merchant Id
            if (isset($post[$indx . '_txtuid'])) {
                $ccRs->Merchant_Id->setNewVal(filter_var($post[$indx . '_txtuid'], FILTER_SANITIZE_STRING));
            }

            // Credit URL
            if (isset($post[$indx . '_txtcrdurl'])) {
                $ccRs->Credit_Url->setNewVal(filter_var($post[$indx . '_txtcrdurl'], FILTER_SANITIZE_STRING));
            }

            // Transaction URL
            if (isset($post[$indx . '_txttransurl'])) {
                $ccRs->Trans_Url->setNewVal(filter_var($post[$indx . '_txttransurl'], FILTER_SANITIZE_STRING));
            }

            // Checkout URL
            if (isset($post[$indx . '_txtckouturl'])) {
                $ccRs->Checkout_Url->setNewVal(filter_var($post[$indx . '_txtckouturl'], FILTER_SANITIZE_STRING));
            }

            // Chekout POS URL
            if (isset($post[$indx . '_txtcoposurl'])) {
            	$ccRs->CheckoutPOS_Url->setNewVal(filter_var($post[$indx . '_txtcoposurl'], FILTER_SANITIZE_STRING));
            }

            // Payment Page Logo URL
            if (isset($post[$indx . '_txtpageLogourl'])) {
            	$ccRs->Page_Header_URL->setNewVal(filter_var($post[$indx . '_txtpageLogourl'], FILTER_SANITIZE_STRING));
            }

            // Manual Merchant Id
            if (isset($post[$indx . '_txtManMerchId'])) {
            	$ccRs->Manual_MerchantId->setNewVal(filter_var($post[$indx . '_txtManMerchId'], FILTER_SANITIZE_STRING));
            }

            // Manual Merchant PW
            if (isset($post[$indx . '_txtManMerchPW'])) {

            	$pw = filter_var($post[$indx . '_txtManMerchPW'], FILTER_SANITIZE_STRING);

            	if ($pw != '' && $ccRs->Manual_Password->getStoredVal() != $pw) {
            		$ccRs->Manual_Password->setNewVal(encryptMessage($pw));
            	} else if ($pw == '') {
            		$ccRs->Manual_Password->setNewVal('');
            	}
            }

            // Use AVS
            if (isset($post[$indx . '_txtuseAVS'])) {
                $ccRs->Use_AVS_Flag->setNewVal(filter_var($post[$indx . '_txtuseAVS'], FILTER_SANITIZE_STRING));
            }

            // Use CCV
            if (isset($post[$indx . '_txtuseCVV'])) {
            	$ccRs->Use_Ccv_Flag->setNewVal(filter_var($post[$indx . '_txtuseCVV'], FILTER_SANITIZE_STRING));
            }

            // Use Card swipe
            if (isset($post[$indx . '_txtuseSwipe'])) {
            	$ccRs->Retry_Count->setNewVal(filter_var($post[$indx . '_txtuseSwipe'], FILTER_SANITIZE_STRING));
            }

            // Password
            if (isset($post[$indx . '_txtpwd'])) {

                $pw = filter_var($post[$indx . '_txtpwd'], FILTER_SANITIZE_STRING);

                if ($pw != '' && $ccRs->Password->getStoredVal() != $pw) {
                    $ccRs->Password->setNewVal(encryptMessage($pw));
                } else if ($pw == '') {
                    $ccRs->Password->setNewVal('');
                }
            }


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
?>
