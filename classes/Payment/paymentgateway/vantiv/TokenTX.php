<?php
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
     * @param \CreditSaleTokenRequest $cstReq
     * @return \TokenResponse
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
        PaymentGateway::logGwTx($dbh, $vr->response->getStatus(), json_encode($cstReq->getFieldsArray()), json_encode($vr->response->getResultArray()), 'CreditSaleToken');


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
        throw new Hk_Exception_Payment("Credit Adjust Sale Amount is not implememnted yet. ");
    }

    public static function creditVoidSaleToken(\PDO $dbh, $idGuest, $idReg, VantivGateway $gway, CreditVoidSaleTokenRequest $voidSale, PaymentRS $payRs, $payDate) {

        if ($payRs->idPayment->getStoredVal() == 0) {
            throw new Hk_Exception_Payment('Payment Id not given.  ');
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
        PaymentGateway::logGwTx($dbh, $vr->response->getStatus(), json_encode($voidSale->getFieldsArray()), json_encode($vr->response->getResultArray()), 'CreditVoidSaleToken');


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
            throw new Hk_Exception_Payment('Payment Id not given.  ');
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
        PaymentGateway::logGwTx($dbh, $vr->response->getStatus(), json_encode($reverseSale->getFieldsArray()), json_encode($vr->response->getResultArray()), 'CreditReverseToken');


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
            throw new Hk_Exception_Payment('Payment Id not given.  ');
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
        PaymentGateway::logGwTx($dbh, $vr->response->getStatus(), json_encode($returnSale->getFieldsArray()), json_encode($vr->response->getResultArray()), 'CreditReturnToken');


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
            throw new Hk_Exception_Payment('DB Payment Id not given.  ');
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
        PaymentGateway::logGwTx($dbh, $vr->response->getStatus(), json_encode($returnVoid->getFieldsArray()), json_encode($vr->response->getResultArray()), 'CreditVoidReturnToken');

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


class TokenResponse extends CreditResponse {


    function __construct($creditTokenResponse, $idPayor, $idRegistration, $idToken, $paymentStatusCode) {

        $this->response = $creditTokenResponse;
        $this->idPayor = $idPayor;
        $this->setIdToken($idToken);
        $this->idRegistration = $idRegistration;
        $this->invoiceNumber = $creditTokenResponse->getInvoiceNumber();
        $this->amount = $creditTokenResponse->getAuthorizedAmount();
        $this->setPaymentStatusCode($paymentStatusCode);

    }

    public function getPaymentMethod() {
        return PaymentMethod::Charge;
    }

    public function getStatus() {

        switch ($this->response->getStatus()) {

            case MpStatusValues::Approved:
                $pr = CreditPayments::STATUS_APPROVED;
                break;

            case MpStatusValues::Declined:
                $pr = CreditPayments::STATUS_DECLINED;
                break;

            default:
                $pr = CreditPayments::STATUS_DECLINED;
        }

        return $pr;
    }

    public function receiptMarkup(\PDO $dbh, &$tbl) {

        $tbl->addBodyTr(HTMLTable::makeTd("Credit Card:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($this->getAmount(), 2)));
        $tbl->addBodyTr(HTMLTable::makeTd($this->response->getCardType() . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd("xxx...". $this->response->getMaskedAccount()));

        if ($this->response->getCardHolderName() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Card Holder: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($this->response->getCardHolderName()));
        }

        if ($this->response->getAuthCode() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Authorization Code: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getAuthCode() . ' ('.ucfirst($this->response->getMerchant()). ')', array('style'=>'font-size:.8em;')));
        }

        if ($this->response->getResponseMessage() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Response Message: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getResponseMessage() . ($this->response->getResponseCode() == '' ? '' :  '  (Code: ' . $this->response->getResponseCode() . ")"), array('style'=>'font-size:.8em;')));
        }


        $tbl->addBodyTr(HTMLTable::makeTd("Sign: ", array('class'=>'tdlabel')) . HTMLTable::makeTd('', array('style'=>'height:35px; width:250px; border: solid 1px gray;')));

    }

}

