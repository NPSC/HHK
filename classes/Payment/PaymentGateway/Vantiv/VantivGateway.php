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
use HHK\Payment\PaymentGateway\Vantiv\Response\CreditTokenResponse;
use HHK\Tables\House\LocationRS;
use HHK\TableLog\AbstractTableLog;
use HHK\TableLog\HouseLog;

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

    public function __construct(\PDO $dbh, $gwType = '', $tokenId = 0) {

        // Glean gwType if not there.
        if ($gwType == '' && $tokenId > 0) {

            // Find merchant (gwType) from the token.
            $tknRs = CreditToken::getTokenRsFromId($dbh, $tokenId);

            if (CreditToken::hasToken($tknRs)) {
                $gwType = $tknRs->Merchant->getStoredVal();
            }
        }

        parent::__construct($dbh, $gwType);
    }

    public static function getPaymentMethod() {
        return PaymentMethod::Charge;
    }

    public function getGatewayName() {
        return AbstractPaymentGateway::VANTIV;
    }
    
    public function hasUndoReturnPmt() {
    	return False;
    }

    public function hasUndoReturnAmt() {
    	return False;
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
            $rtnMessage = filter_var($post['ReturnMessage'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        // THis eventually selects the merchant id
        if (isset($uS->manualKey)) {
        	$this->manualKey = $uS->manualKey;
        }


        if (isset($post[VantivGateway::PAYMENT_ID])) {

            $paymentId = filter_var($post[VantivGateway::PAYMENT_ID], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

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
            $rows[0] = [];
            //throw new PaymentException('Payment Gateway Merchant "' . $this->getGatewayType() . '" is not found for '.$this->getGatewayName());
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

        if ($resultMessage != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($resultMessage, array('colspan' => '2', 'class' => 'ui-state-highlight')));
        }

        foreach ($rows as $r) {

            $tbl->addBodyTr(HTMLTable::makeTd('&nbsp', array('colspan'=>'2')), array('style'=>'border-top: solid 1px black;'));

            $gwRs = new CC_Hosted_GatewayRS();
            EditRS::loadRow($r, $gwRs);

            $indx = $gwRs->idcc_gateway->getStoredVal();
            $title = ucfirst($gwRs->cc_name->getStoredVal());

            // Nerchant name
            $tbl->addBodyTr(
                HTMLTable::makeTh('Merchant Name:', array('class' => 'tdlabel'))
                . HTMLTable::makeTd($title)
            );

            $tbl->addBodyTr(
                HTMLTable::makeTh('Merchant Id:', array('class' => 'tdlabel'))
                . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->Merchant_Id->getStoredVal(), array('name' => $indx . '_txtuid', 'size' => '50')))
            );
            $tbl->addBodyTr(
                HTMLTable::makeTh('Password:', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->Password->getStoredVal(), array('name' => $indx . '_txtpwd', 'size' => '90')) . ' (Obfuscated)')
            );
            $tbl->addBodyTr(
                HTMLTable::makeTh('Credit URL:', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->Credit_Url->getStoredVal(), array('name' => $indx . '_txtcrdurl', 'size' => '90')))
            );
            $tbl->addBodyTr(
                HTMLTable::makeTh('Token Trans URL:', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->Trans_Url->getStoredVal(), array('name' => $indx . '_txttransurl', 'size' => '90')))
            );


            $tbl->addBodyTr(
                HTMLTable::makeTh('CheckoutPOS URL:', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->CheckoutPOS_Url->getStoredVal(), array('name' => $indx . '_txtcoposurl', 'size' => '90')))
            );

            $tbl->addBodyTr(
                HTMLTable::makeTh('Checkout URL:', array('class' => 'tdlabel'))
                        . HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->Checkout_Url->getStoredVal(), array('name' => $indx . '_txtckouturl', 'size' => '90')))
            );

            $tbl->addBodyTr(
                HTMLTable::makeTh('Manual Merchant Id:', array('class' => 'tdlabel'))
                		. HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->Manual_MerchantId->getStoredVal(), array('name' => $indx . '_txtManMerchId', 'size' => '90')))
       		);
            $tbl->addBodyTr(
                HTMLTable::makeTh('Manual Password:', array('class' => 'tdlabel'))
                		. HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->Manual_Password->getStoredVal(), array('name' => $indx . '_txtManMerchPW', 'size' => '90')). ' (Obfuscated)')
            );
            $tbl->addBodyTr(
                HTMLTable::makeTh('Use AVS:', array('class' => 'tdlabel'))
            		.HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($opts, $gwRs->Use_AVS_Flag->getStoredVal(), FALSE), array('name' => $indx . '_txtuseAVS')))
            		);

            $tbl->addBodyTr(
                HTMLTable::makeTh('Use CCV:', array('class' => 'tdlabel'))
                    .HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($opts, $gwRs->Use_Ccv_Flag->getStoredVal(), FALSE), array('name' => $indx . '_txtuseCVV')))
            );

            $tbl->addBodyTr(
                HTMLTable::makeTh('Use Card Swiper:', array('class' => 'tdlabel'))
            		.HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($opts, $gwRs->Retry_Count->getStoredVal(), FALSE), array('name' => $indx . '_txtuseSwipe')))
            		);

            $tbl->addBodyTr(
                HTMLTable::makeTh('Payment Page Logo URL:', array('class' => 'tdlabel'))
            		. HTMLTable::makeTd(HTMLInput::generateMarkup($gwRs->Page_Header_URL->getStoredVal(), array('name' => $indx . '_txtpageLogourl', 'size' => '90')))
            		);


            // Set my rooms
            $tbl->addBodyTr(
                HTMLTable::makeTh('Set '.$title.' Rooms:', array('class' => 'tdlabel'))
                . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => $indx . '_cbSetRooms', 'class'=>'hhk-setMerchantRooms', 'data-merchant'=>$title, 'type'=>'checkbox')))
                );

            // Delete me
            $tbl->addBodyTr(
                HTMLTable::makeTh('Delete ' . $title . ':', array('class' => 'tdlabel'))
                . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => $indx . '_cbdelMerchant', 'class'=>'hhk-delMerchant', 'data-merchant'=>$title, 'type'=>'checkbox')))
                );
        }


        // New location
        $tbl->addBodyTr(HTMLTable::makeTd('&nbsp', array('colspan'=>'2')), array('style'=>'border-top: solid 1px black;'));
        $tbl->addBodyTr(
            HTMLTable::makeTh('Add a new merchant', array('class' => 'tdlabel'))
            .HTMLTable::makeTd('New Merchant Name: '.HTMLInput::generateMarkup('', array('name' => 'txtnewMerchant', 'size' => '50')))

        );

        return $tbl->generateMarkup();
    }

    protected static function _saveEditMarkup(\PDO $dbh, $gatewayName, $post) {

        $uS = Session::getInstance();

        $msg = '';

        $ccRs = new CC_Hosted_GatewayRS();
        $ccRs->Gateway_Name->setStoredVal($gatewayName);
        $rows = EditRS::select($dbh, $ccRs, array($ccRs->Gateway_Name));

        // Add new merchant
        if (isset($post['txtnewMerchant'])) {
            // Add a new default row

            $merchantName = strtolower(filter_var($post['txtnewMerchant'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));

            if ( ! empty($merchantName)) {

                $isThere = FALSE;

                // Compare with previous merchant names
                foreach ($rows as $r) {

                    $ccRs = new CC_Hosted_GatewayRS();
                    EditRS::loadRow($r, $ccRs);

                    if ($ccRs->cc_name->getStoredVal() == $merchantName) {
                        $isThere = TRUE;
                        break;
                    }
                }

                reset($rows);

                // Don't let them make a new merchant with the same name.
                if ($isThere) {
                    $msg .= HTMLContainer::generateMarkup('p', 'Merchant name ' . $merchantName . ' already exists.');
                } else {
                    //Insert new gateway record
                    $num = self::insertGwRecord($dbh, $gatewayName, $merchantName, new CC_Hosted_GatewayRS());
                    self::checkLocationTable($dbh, $merchantName);

                    // Retrieve the new record.
                    $ccRs = new CC_Hosted_GatewayRS();
                    $ccRs->Gateway_Name->setStoredVal($gatewayName);
                    $ccRs->cc_name->setStoredVal($merchantName);
                    $newrows = EditRS::select($dbh, $ccRs, array($ccRs->Gateway_Name, $ccRs->cc_name));

                    $rows[] = $newrows[0];
                }
            }
        }

        foreach ($rows as $r) {

            $ccRs = new CC_Hosted_GatewayRS();
            EditRS::loadRow($r, $ccRs);

            $indx = $ccRs->idcc_gateway->getStoredVal();
            $merchantName = $ccRs->cc_name->getStoredVal();

            // Delete Merchant
            if (isset($post[$indx . '_cbdelMerchant']) && $ccRs->cc_name->getStoredVal() != '') {

                $result = self::deleteMerchant($dbh, $ccRs);
                $msg .= HTMLContainer::generateMarkup('p', $result);

                continue;
            }

            // Merchant Id
            if (isset($post[$indx . '_txtuid'])) {
                $ccRs->Merchant_Id->setNewVal(filter_var($post[$indx . '_txtuid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            }

            // Credit URL
            if (isset($post[$indx . '_txtcrdurl'])) {
                $ccRs->Credit_Url->setNewVal(filter_var($post[$indx . '_txtcrdurl'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            }

            // Transaction URL
            if (isset($post[$indx . '_txttransurl'])) {
                $ccRs->Trans_Url->setNewVal(filter_var($post[$indx . '_txttransurl'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            }

            // Checkout URL
            if (isset($post[$indx . '_txtckouturl'])) {
                $ccRs->Checkout_Url->setNewVal(filter_var($post[$indx . '_txtckouturl'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            }

            // Chekout POS URL
            if (isset($post[$indx . '_txtcoposurl'])) {
            	$ccRs->CheckoutPOS_Url->setNewVal(filter_var($post[$indx . '_txtcoposurl'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            }

            // Payment Page Logo URL
            if (isset($post[$indx . '_txtpageLogourl'])) {
            	$ccRs->Page_Header_URL->setNewVal(filter_var($post[$indx . '_txtpageLogourl'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            }

            // Manual Merchant Id
            if (isset($post[$indx . '_txtManMerchId'])) {
            	$ccRs->Manual_MerchantId->setNewVal(filter_var($post[$indx . '_txtManMerchId'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            }

            // Manual Merchant PW
            if (isset($post[$indx . '_txtManMerchPW'])) {

            	$pw = filter_var($post[$indx . '_txtManMerchPW'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            	if ($pw != '' && $ccRs->Manual_Password->getStoredVal() != $pw) {
            		$ccRs->Manual_Password->setNewVal(encryptMessage($pw));
            	} else if ($pw == '') {
            		$ccRs->Manual_Password->setNewVal('');
            	}
            }

            // Use AVS
            if (isset($post[$indx . '_txtuseAVS'])) {
                $ccRs->Use_AVS_Flag->setNewVal(filter_var($post[$indx . '_txtuseAVS'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            }

            // Use CCV
            if (isset($post[$indx . '_txtuseCVV'])) {
            	$ccRs->Use_Ccv_Flag->setNewVal(filter_var($post[$indx . '_txtuseCVV'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            }

            // Use Card swipe
            if (isset($post[$indx . '_txtuseSwipe'])) {
            	$ccRs->Retry_Count->setNewVal(filter_var($post[$indx . '_txtuseSwipe'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            }

            // Password
            if (isset($post[$indx . '_txtpwd'])) {

                $pw = filter_var($post[$indx . '_txtpwd'], FILTER_UNSAFE_RAW);

                if ($pw != '' && $ccRs->Password->getStoredVal() != $pw) {
                    $ccRs->Password->setNewVal(encryptMessage($pw));
                } else if ($pw == '') {
                    $ccRs->Password->setNewVal('');
                }
            }

            $num = 0;

            // Save record.
            if ($merchantName != '') {
                //Update

                $ccRs->Updated_By->setNewVal($uS->username);
                $ccRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                $num = EditRS::update($dbh, $ccRs, array($ccRs->idcc_gateway));

                if ($num > 0) {

                    $logText = AbstractTableLog::getUpdateText($ccRs);
                    HouseLog::logGeneral($dbh, 'CC Gateway', $ccRs->idcc_gateway->getStoredVal(), $logText, $uS->username, 'update');

                    self::checkLocationTable($dbh, $merchantName);
                }

            } else if (empty($merchantName)) {
                $num = 'Merchant name is missing.';
            }

            // Set Merchant Rooms
            if (isset($post[$indx . '_cbSetRooms']) && $ccRs->cc_name->getStoredVal() != '') {

                $rooms = self::setMerchantRooms($dbh, $ccRs);

                if ($rooms > 0) {
                    $msg .= HTMLContainer::generateMarkup('p', $ccRs->Gateway_Name->getStoredVal() . " - " . $rooms . " rooms set to $merchantName");
                }

            }

            if (intval($num, 10) == 0 && $num !== 0) {
                $msg .= HTMLContainer::generateMarkup('p', $num);
            } else if ($num > 0) {
                $msg .= HTMLContainer::generateMarkup('p', $ccRs->Gateway_Name->getStoredVal() . " " . $merchantName . " - Payment Credentials Updated.  ");
            } else {
                $msg .= HTMLContainer::generateMarkup('p', $ccRs->Gateway_Name->getStoredVal() . " " . $ccRs->cc_name->getStoredVal() . " - No changes detected.  ");
            }
        }

        return $msg;
    }

    private static function insertGwRecord(\PDO $dbh, $gatewayName, $merchantName, CC_Hosted_GatewayRS $ccRs) {

        $uS = Session::getInstance();

        // Check for previous entry
        $rs = new CC_Hosted_GatewayRS();
        $rs->Gateway_Name->setStoredVal($gatewayName);
        $rs->cc_name->setStoredVal($merchantName);
        $rows = EditRS::select($dbh, $rs, array($rs->Gateway_Name, $rs->cc_name));

        if (count($rows) > 0) {
            return 'Merchant ' . $merchantName . ' already exists for gateway ' .$gatewayName;
        }

        //Insert gateway record
        $ccRs->Gateway_Name->setNewVal($gatewayName);
        $ccRs->cc_name->setNewVal($merchantName);
        $ccRs->Updated_By->setNewVal($uS->username);
        $ccRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
        $num = EditRS::insert($dbh, $ccRs);

        if ($num > 0) {
            $logText = AbstractTableLog::getInsertText($ccRs);
            HouseLog::logGeneral($dbh, 'CC Gateway', $num, $logText, $uS->username, 'insert');
        }

        return $num;
    }

    private static function setMerchantRooms(\PDO $dbh, CC_Hosted_GatewayRS $ccRs) {

        $idLocation = 0;
        $num = 0;

        $locRs = new LocationRS();
        $locRs->Merchant->setStoredVal($ccRs->cc_name->getStoredVal());
        $r = EditRS::select($dbh, $locRs, array($locRs->Merchant));

        if (count($r) > 0) {
            EditRS::loadRow($r[0], $locRs);
            $idLocation = $locRs->idLocation->getStoredVal();

            $num = $dbh->exec("update room set idLocation = $idLocation");
        }

        return $num;
    }

    private static function deleteMerchant(\PDO $dbh, CC_Hosted_GatewayRS $ccRs) {

        $uS = Session::getInstance();
        $num = '';
        $merchantName = $ccRs->cc_name->getStoredVal();

        //
        $result = EditRS::delete($dbh, $ccRs, array($ccRs->idcc_gateway));

        if ($result) {

            $logText = AbstractTableLog::getDeleteText($ccRs, $ccRs->idcc_gateway->getStoredVal());
            HouseLog::logGeneral($dbh, 'CC Gateway', $ccRs->idcc_gateway->getStoredVal(), $logText, $uS->username, 'delete');

            $num = 'Merchant ' . $merchantName . ' is deleted. ';

        } else {
            $num = 'Merchant ' . $merchantName . ' was not found! ';
        }

        // Deal with location table
        $locRs = new LocationRS();
        $locRs->Merchant->setStoredVal($merchantName);
        $r = EditRS::select($dbh, $locRs, array($locRs->Merchant));

        if (count($r) > 0) {

            EditRS::loadRow($r[0], $locRs);

            $result = EditRS::delete($dbh, $locRs, array($locRs->idLocation));

            if ($result) {
                $logText = AbstractTableLog::getDeleteText($locRs, $locRs->idLocation->getStoredVal());
                HouseLog::logGeneral($dbh, 'Location', $locRs->idLocation->getStoredVal(), $logText, $uS->username, 'delete');
                $num .= 'Location ' . $merchantName . ' is deleted. ';
            }

        } else {
            $num .= 'Location ' . $merchantName . ' was not found! ';
        }

        return $num;
    }

    private static function checkLocationTable(\PDO $dbh, $merchantName) {

        $uS = Session::getInstance();
        $num = 0;

        $locRs = new LocationRS();
        $locRs->Merchant->setStoredVal($merchantName);
        $locRows = EditRS::select($dbh, $locRs, array($locRs->Merchant));

        if (count($locRows) == 0) {
            // Insert

            $locRs = new LocationRS();
            $locRs->Merchant->setNewVal($merchantName);
            $locRs->Title->setNewVal(ucfirst($merchantName));
            $locRs->Status->setNewVal('a');
            $locRs->Updated_By->setNewVal($uS->username);
            $locRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            $num = EditRS::insert($dbh, $locRs);

            if ($num > 0) {
                $logText = AbstractTableLog::getInsertText($locRs);
                HouseLog::logGeneral($dbh, 'Location', $num, $logText, $uS->username, 'insert');
            }

        } else {
            // Update
            EditRS::loadRow($locRows[0], $locRs);

            $locRs->Merchant->setNewVal($merchantName);
            $locRs->Title->setNewVal(ucfirst($merchantName));
            $locRs->Status->setNewVal('a');
            $locRs->Updated_By->setNewVal($uS->username);
            $locRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            $num = EditRS::update($dbh, $locRs, array($locRs->idLocation));

            if ($num > 0) {
                $logText = AbstractTableLog::getUpdateText($locRs);
                HouseLog::logGeneral($dbh, 'Location', $locRs->idLocation->getStoredVal(), $logText, $uS->username, 'update');
            }
        }

        return $num;
    }
}

?>
