<?php

namespace HHK\Payment\PaymentGateway\Vantiv;

use HHK\Payment\{CreditToken, Transaction};
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Payment\PaymentGateway\CreditPayments\{ReturnReply, ReverseReply, SaleReply, VoidReply, VoidReturnReply};
use HHK\SysConst\{Mode, MpStatusValues, PaymentStatusCode, TransMethod, TransType};
use HHK\Tables\Payment\PaymentRS;
use HHK\sec\Session;
use HHK\Exception\PaymentException;
use HHK\Payment\PaymentGateway\Vantiv\Request\CreditAdjustTokenRequest;
use HHK\Payment\PaymentGateway\Vantiv\Request\CreditReturnTokenRequest;
use HHK\Payment\PaymentGateway\Vantiv\Request\CreditReversalTokenRequest;
use HHK\Payment\PaymentGateway\Vantiv\Request\CreditSaleTokenRequest;
use HHK\Payment\PaymentGateway\Vantiv\Request\CreditVoidReturnTokenRequest;
use HHK\Payment\PaymentGateway\Vantiv\Request\CreditVoidSaleTokenRequest;

/**
 * TokenTX.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


/**
 * Description of TokenTX
 *
 * @author Eric
 */
class TokenTX {

    /**
     *
     * @param \PDO $dbh
     * @param int $idGuest
     * @param string $gwName
     * @param CreditSaleTokenRequest $cstReq
     * @return TokenResponse
     */
    public static function CreditSaleToken(\PDO $dbh, $idGuest, $idReg, VantivGateway $gway, CreditSaleTokenRequest $cstReq, $payNotes, $payDate) {

        $uS = Session::getInstance();
        $trace = FALSE;

        if (strtolower($uS->mode) !== Mode::Live) {
            $cstReq->setOperatorID('test');
            //$trace = TRUE;
        }

        // Call to web service
        $creditResponse = $cstReq->submit($gway->getCredentials(), $trace);
        $creditResponse->setMerchant($gway->getGatewayType());

        $vr = new TokenResponse($creditResponse, $idGuest, $idReg, $cstReq->getTokenId(), PaymentStatusCode::Paid);

        $vr->setPaymentDate($payDate);
        $vr->setPaymentNotes($payNotes);
        $vr->setResult($creditResponse->getStatus());

        if ($creditResponse->getStatus() != MpStatusValues::Approved) {
            $vr->setPaymentStatusCode(PaymentStatusCode::Declined);
        }

        // Save raw transaction in the db.
        AbstractPaymentGateway::logGwTx($dbh, $vr->response->getStatus(), json_encode($cstReq->getFieldsArray()), json_encode($vr->response->getResultArray()), 'CreditSaleToken');


        // New Token?
        if ($vr->response->getToken() != '') {

            $guestTokenRs = CreditToken::getTokenRsFromId($dbh, $vr->getIdToken());

            $vr->response->setMaskedAccount($guestTokenRs->MaskedAccount->getStoredVal());
            $vr->response->setCardHolderName($guestTokenRs->CardHolderName->getStoredVal());
            $vr->response->setOperatorId($cstReq->getOperatorID());
            $vr->cardType = $guestTokenRs->CardType->getStoredVal();
            $vr->expDate = $guestTokenRs->ExpDate->getStoredVal();
        }

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $vr, $gway->getGatewayName(), TransType::Sale, TransMethod::Token);
        $vr->setIdTrans($transRs->idTrans->getStoredVal());


        // Record Payment
        return SaleReply::processReply($dbh, $vr, $uS->username);


    }

    public static function CreditAdjustToken(\PDO $dbh, $idGuest, $idReg, VantivGateway $gway, CreditAdjustTokenRequest $cstReq) {
        throw new PaymentException("Credit Adjust Sale Amount is not implememnted yet. ");
    }

    public static function creditVoidSaleToken(\PDO $dbh, $idGuest, $idReg, VantivGateway $gway, CreditVoidSaleTokenRequest $voidSale, PaymentRS $payRs, $payDate) {

        if ($payRs->idPayment->getStoredVal() == 0) {
            throw new PaymentException('Payment Id not given.  ');
        }

        $uS = Session::getInstance();
        $trace = FALSE;


        if (strtolower($uS->mode) !== Mode::Live) {
            $voidSale->setOperatorID('test');
        } else {
            $voidSale->setOperatorID($uS->username);
        }

        // Call to web service
        $creditResponse = $voidSale->submit($gway->getCredentials(), $trace);
        $creditResponse->setMerchant($gway->getGatewayType());

        $vr = new TokenResponse($creditResponse, $idGuest, $idReg, $voidSale->getTokenId(), PaymentStatusCode::VoidSale);
        $vr->setPaymentDate($payDate);
        $vr->setResult($creditResponse->getStatus());

        if ($creditResponse->getStatus() != MpStatusValues::Approved) {
            $vr->setPaymentStatusCode(PaymentStatusCode::Declined);
        }

        // Save raw transaction in the db.
        AbstractPaymentGateway::logGwTx($dbh, $vr->response->getStatus(), json_encode($voidSale->getFieldsArray()), json_encode($vr->response->getResultArray()), 'CreditVoidSaleToken');


        // New Token?
        if ($vr->response->getToken() != '') {
            $guestTokenRs = CreditToken::getTokenRsFromId($dbh, $vr->getIdToken());
            $vr->response->setMaskedAccount($guestTokenRs->MaskedAccount->getStoredVal());
            $vr->response->setCardHolderName($guestTokenRs->CardHolderName->getStoredVal());
            $vr->response->setOperatorId($voidSale->getOperatorID());
            $vr->cardType = $guestTokenRs->CardType->getStoredVal();
            $vr->expDate = $guestTokenRs->ExpDate->getStoredVal();
        }

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $vr, $gway->getGatewayType(), TransType::Void, TransMethod::Token);
        $vr->setIdTrans($transRs->idTrans->getStoredVal());

        // Record payment
        return VoidReply::processReply($dbh, $vr, $uS->username, $payRs);

    }

    public static function creditReverseToken(\PDO $dbh, $idGuest, $idReg, VantivGateway $gway, CreditReversalTokenRequest $reverseSale, PaymentRS $payRs, $payDate) {

        if ($payRs->idPayment->getStoredVal() == 0) {
            throw new PaymentException('Payment Id not given.  ');
        }

        $uS = Session::getInstance();
        $trace = FALSE;


        if (strtolower($uS->mode) !== Mode::Live) {
            $reverseSale->setOperatorID('test');
            //$trace = TRUE;
        } else {
            $reverseSale->setOperatorID($uS->username);
        }

        // Call to web service
        $creditResponse = $reverseSale->submit($gway->getCredentials(), $trace);
        $creditResponse->setMerchant($gway->getGatewayType());

        $vr = new TokenResponse($creditResponse, $idGuest, $idReg, $reverseSale->getTokenId(), PaymentStatusCode::Reverse);
        $vr->setPaymentDate($payDate);
        $vr->setResult($creditResponse->getStatus());

        if ($creditResponse->getStatus() != MpStatusValues::Approved) {
            $vr->setPaymentStatusCode(PaymentStatusCode::Declined);
        }

        // Save raw transaction in the db.
        AbstractPaymentGateway::logGwTx($dbh, $vr->response->getStatus(), json_encode($reverseSale->getFieldsArray()), json_encode($vr->response->getResultArray()), 'CreditReverseToken');


        // New Token?
        if ($vr->response->getToken() != '') {
            $guestTokenRs = CreditToken::getTokenRsFromId($dbh, $vr->getIdToken());
            $vr->response->setMaskedAccount($guestTokenRs->MaskedAccount->getStoredVal());
            $vr->response->setCardHolderName($guestTokenRs->CardHolderName->getStoredVal());
            $vr->response->setOperatorId($reverseSale->getOperatorID());
            $vr->cardType = $guestTokenRs->CardType->getStoredVal();
            $vr->expDate = $guestTokenRs->ExpDate->getStoredVal();

        }

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $vr, $gway->getGatewayType(), TransType::Reverse, TransMethod::Token);
        $vr->setIdTrans($transRs->idTrans->getStoredVal());

        // Record payment
        return ReverseReply::processReply($dbh, $vr, $uS->username, $payRs);

    }

    public static function creditReturnToken(\PDO $dbh, $idGuest, $idReg, VantivGateway $gway, CreditReturnTokenRequest $returnSale, $payRs, $payDate) {

        if (is_null($payRs) === FALSE && $payRs->idPayment->getStoredVal() == 0) {
            throw new PaymentException('Payment Id not given.  ');
        }

        $uS = Session::getInstance();
        $trace = FALSE;

        if (strtolower($uS->mode) !== Mode::Live) {
            $returnSale->setOperatorID('test');
            //$trace = TRUE;
       } else {
            $returnSale->setOperatorID($uS->username);
        }

        // Call to web service
        $creditResponse = $returnSale->submit($gway->getCredentials(), $trace);
        $creditResponse->setMerchant($gway->getGatewayType());

        $vr = new TokenResponse($creditResponse, $idGuest, $idReg, $returnSale->getTokenId(), PaymentStatusCode::Retrn);
        $vr->setPaymentDate($payDate);
        $vr->setResult($creditResponse->getStatus());

        if ($creditResponse->getStatus() != MpStatusValues::Approved) {
            $vr->setPaymentStatusCode(PaymentStatusCode::Declined);
        }

        // Save raw transaction in the db.
        AbstractPaymentGateway::logGwTx($dbh, $vr->response->getStatus(), json_encode($returnSale->getFieldsArray()), json_encode($vr->response->getResultArray()), 'CreditReturnToken');


        // New Token?
        if ($vr->response->getToken() != '') {
            $guestTokenRs = CreditToken::getTokenRsFromId($dbh, $vr->getIdToken());
            $vr->response->setMaskedAccount($guestTokenRs->MaskedAccount->getStoredVal());
            $vr->response->setCardHolderName($guestTokenRs->CardHolderName->getStoredVal());
            $vr->response->setOperatorId($returnSale->getOperatorID());
            $vr->cardType = $guestTokenRs->CardType->getStoredVal();
            $vr->expDate = $guestTokenRs->ExpDate->getStoredVal();
        }


        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $vr, $gway->getGatewayType(), TransType::Retrn, TransMethod::Token);
        $vr->setIdTrans($transRs->idTrans->getStoredVal());

        // Record payment
        return ReturnReply::processReply($dbh, $vr, $uS->username, $payRs);

    }

    public static function creditVoidReturnToken (\PDO $dbh, $idGuest, $idReg, VantivGateway $gway, CreditVoidReturnTokenRequest $returnVoid, PaymentRS $payRs, $payDate) {

        if ($payRs->idPayment->getStoredVal() == 0) {
            throw new PaymentException('DB Payment Id not given.  ');
        }

        $uS = Session::getInstance();
        $trace = FALSE;

        if (strtolower($uS->mode) !== Mode::Live) {
            $returnVoid->setOperatorID('test');
            //$trace = TRUE;
        }

        // Call to web service
        $creditResponse = $returnVoid->submit($gway->getCredentials(), $trace);
        $creditResponse->setMerchant($gway->getGatewayType());

        $vr = new TokenResponse($creditResponse, $idGuest, $idReg, $returnVoid->getTokenId(), PaymentStatusCode::VoidReturn);
        $vr->setPaymentDate($payDate);
        $vr->setResult($creditResponse->getStatus());

        if ($creditResponse->getStatus() != MpStatusValues::Approved) {
            $vr->setPaymentStatusCode(PaymentStatusCode::Declined);
        }

        // Save raw transaction in the db.
        AbstractPaymentGateway::logGwTx($dbh, $vr->response->getStatus(), json_encode($returnVoid->getFieldsArray()), json_encode($vr->response->getResultArray()), 'CreditVoidReturnToken');

        // New Token?
        if ($vr->response->getToken() != '') {
            $guestTokenRs = CreditToken::getTokenRsFromId($dbh, $vr->getIdToken());
            $vr->response->setMaskedAccount($guestTokenRs->MaskedAccount->getStoredVal());
            $vr->response->setCardHolderName($guestTokenRs->CardHolderName->getStoredVal());
            $vr->response->setOperatorId($returnVoid->getOperatorID());
            $vr->expDate = $guestTokenRs->ExpDate->getStoredVal();
        }


        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $vr, $gway->getGatewayType(), TransType::VoidReturn, TransMethod::Token);
        $vr->idTrans = $transRs->idTrans->getStoredVal();

        // Record payment
        return VoidReturnReply::processReply($dbh, $vr, $uS->username, $payRs);

    }
}
?>