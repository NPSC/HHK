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
    public static function CreditSaleToken(\PDO $dbh, $idGuest, VantivGateway $gway, CreditSaleTokenRequest $cstReq, $payNotes = '') {

        $uS = Session::getInstance();
        $trace = FALSE;

        if (strtolower($gway->getGatewayType()) == 'test') {
            $cstReq->setAddress('4')->setZip('30329')->setOperatorID('test');
            //$trace = TRUE;
        }

        // Call to web service
        $creditResponse = $cstReq->submit($gway->getCredentials(), $trace);

        $vr = new TokenResponse($creditResponse, $idGuest, $cstReq->getTokenId(), $payNotes);


        // Save raw transaction in the db.
        PaymentGateway::logGwTx($dbh, $vr->response->getStatus(), json_encode($cstReq->getFieldsArray()), json_encode($vr->response->getResultArray()), 'CreditSaleToken');


        // New Token?
        if ($vr->response->getToken() != '') {
            $guestTokenRs = CreditToken::updateToken($dbh, $vr);
            $vr->cardNum = str_ireplace('x', '', $guestTokenRs->MaskedAccount->getStoredVal());
            $vr->cardName = $guestTokenRs->CardHolderName->getStoredVal();
            $vr->cardType = $guestTokenRs->CardType->getStoredVal();
            $vr->expDate = $guestTokenRs->ExpDate->getStoredVal();
            $vr->idToken = $guestTokenRs->idGuest_token->getStoredVal();
        }

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $vr, $gwName, TransType::Sale, TransMethod::Token);
        $vr->setIdTrans($transRs->idTrans->getStoredVal());


        // Record Payment
        return SaleReply::processReply($dbh, $vr, $uS->username);


    }

    public static function CreditAdjustToken(\PDO $dbh, $idGuest, VantivGateway $gway, CreditAdjustTokenRequest $cstReq, $payNotes = '') {
        throw new Hk_Exception_Payment("Credit Adjust Sale Amount is not implememnted yet. ");
    }

    public static function creditVoidSaleToken(\PDO $dbh, $idGuest, VantivGateway $gway, CreditVoidSaleTokenRequest $voidSale, PaymentRS $payRs, $payNotes = '') {

        if ($payRs->idPayment->getStoredVal() == 0) {
            throw new Hk_Exception_Payment('Payment Id not given.  ');
        }

        $uS = Session::getInstance();
        $trace = FALSE;

        if (strtolower($gway->getGatewayType()) == 'test') {

            $voidSale->setOperatorID('test');
            //$trace = TRUE;

        } else {
            $voidSale->setOperatorID($uS->username);
        }

        // Call to web service
        $creditResponse = $voidSale->submit($gway->getCredentials(), $trace);
        $vr = new TokenResponse($creditResponse, $idGuest, $voidSale->getTokenId(), $payNotes);

        // Save raw transaction in the db.
        PaymentGateway::logGwTx($dbh, $vr->response->getStatus(), json_encode($voidSale->getFieldsArray()), json_encode($vr->response->getResultArray()), 'CreditVoidSaleToken');


        // New Token?
        if ($vr->response->getToken() != '') {
            $guestTokenRs = CreditToken::updateToken($dbh, $vr);
            $vr->cardNum = str_ireplace('x', '', $guestTokenRs->MaskedAccount->getStoredVal());
            $vr->cardType = $guestTokenRs->CardType->getStoredVal();
            $vr->cardName = $guestTokenRs->CardHolderName->getStoredVal();
            $vr->expDate = $guestTokenRs->ExpDate->getStoredVal();
            $vr->idToken = $guestTokenRs->idGuest_token->getStoredVal();
        }

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $vr, $gway->getGatewayType(), TransType::Void, TransMethod::Token);
        $vr->setIdTrans($transRs->idTrans->getStoredVal());

        // Record payment
        return VoidReply::processReply($dbh, $vr, $uS->username, $payRs);

    }

    public static function creditReverseToken(\PDO $dbh, $idGuest, VantivGateway $gway, CreditReversalTokenRequest $reverseSale, PaymentRS $payRs, $payNotes = '') {

        if ($payRs->idPayment->getStoredVal() == 0) {
            throw new Hk_Exception_Payment('Payment Id not given.  ');
        }

        $uS = Session::getInstance();
        $trace = FALSE;

        if (strtolower($gway->getGatewayType()) == 'test') {
            $reverseSale->setOperatorID('test');
            //$trace = TRUE;
        } else {
            $reverseSale->setOperatorID($uS->username);
        }

        // Call to web service
        $creditResponse = $reverseSale->submit($gway->getCredentials(), $trace);
        $vr = new TokenResponse($creditResponse, $idGuest, $reverseSale->getTokenId(), $payNotes);

        // Save raw transaction in the db.
        PaymentGateway::logGwTx($dbh, $vr->response->getStatus(), json_encode($reverseSale->getFieldsArray()), json_encode($vr->response->getResultArray()), 'CreditReverseToken');


        // New Token?
        if ($vr->response->getToken() != '') {
            $guestTokenRs = CreditToken::updateToken($dbh, $vr);
            $vr->cardNum = str_ireplace('x', '', $guestTokenRs->MaskedAccount->getStoredVal());
            $vr->cardType = $guestTokenRs->CardType->getStoredVal();
            $vr->cardName = $guestTokenRs->CardHolderName->getStoredVal();
            $vr->expDate = $guestTokenRs->ExpDate->getStoredVal();
            $vr->idToken = $guestTokenRs->idGuest_token->getStoredVal();
        }

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $vr, $gway->getGatewayType(), TransType::Reverse, TransMethod::Token);
        $vr->setIdTrans($transRs->idTrans->getStoredVal());

        // Record payment
        return ReverseReply::processReply($dbh, $vr, $uS->username, $payRs);

    }


    public static function creditReturnToken(\PDO $dbh, $idGuest, VantivGateway $gway, CreditReturnTokenRequest $returnSale, $payRs, $payNotes = '') {

        if (is_null($payRs) === FALSE && $payRs->idPayment->getStoredVal() == 0) {
            throw new Hk_Exception_Payment('DB Payment Id not given.  ');
        }

        $uS = Session::getInstance();
        $trace = FALSE;

        if (strtolower($gway->getGatewayType()) == 'test') {
            $returnSale->setOperatorID('test');
            //$trace = TRUE;
       } else {
            $returnSale->setOperatorID($uS->username);
        }

        // Call to web service
        $creditResponse = $returnSale->submit($gway->getCredentials(), $trace);
        $vr = new TokenResponse($creditResponse, $idGuest, $returnSale->getTokenId(), $payNotes);


        // Save raw transaction in the db.
        PaymentGateway::logGwTx($dbh, $vr->response->getStatus(), json_encode($returnSale->getFieldsArray()), json_encode($vr->response->getResultArray()), 'CreditReturnToken');


        // New Token?
        if ($vr->response->getToken() != '') {
            $guestTokenRs = CreditToken::updateToken($dbh, $vr);
            $vr->cardNum = str_ireplace('x', '', $guestTokenRs->MaskedAccount->getStoredVal());
            $vr->cardName = $guestTokenRs->CardHolderName->getStoredVal();
            $vr->cardType = $guestTokenRs->CardType->getStoredVal();
            $vr->expDate = $guestTokenRs->ExpDate->getStoredVal();
            $vr->idToken = $guestTokenRs->idGuest_token->getStoredVal();
        }


        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $vr, $gway->getGatewayType(), TransType::Retrn, TransMethod::Token);
        $vr->setIdTrans($transRs->idTrans->getStoredVal());

        // Record payment
        return ReturnReply::processReply($dbh, $vr, $uS->username, $payRs);

    }

    public static function creditVoidReturnToken (\PDO $dbh, $idGuest, VantivGateway $gway, CreditVoidReturnTokenRequest $returnVoid, PaymentRS $payRs) {

        if ($payRs->idPayment->getStoredVal() == 0) {
            throw new Hk_Exception_Payment('DB Payment Id not given.  ');
        }

        $uS = Session::getInstance();
        $trace = FALSE;

        if (strtolower($gway->getGatewayType()) == 'test') {
            $returnVoid->setOperatorID('test');
            //$trace = TRUE;
        }

        // Call to web service
        $creditResponse = $returnVoid->submit($gway->getCredentials(), $trace);
        $vr = new TokenResponse($creditResponse, $idGuest, $returnVoid->getTokenId());


        // Save raw transaction in the db.
        PaymentGateway::logGwTx($dbh, $vr->response->getStatus(), json_encode($returnVoid->getFieldsArray()), json_encode($vr->response->getResultArray()), 'CreditVoidReturnToken');

        // New Token?
        if ($vr->response->getToken() != '') {
            $guestTokenRs = CreditToken::updateToken($dbh, $vr);
            $vr->cardNum = str_ireplace('x', '', $guestTokenRs->MaskedAccount->getStoredVal());
            $vr->cardName = $guestTokenRs->CardHolderName->getStoredVal();
            $vr->expDate = $guestTokenRs->ExpDate->getStoredVal();
            $vr->idToken = $guestTokenRs->idGuest_token->getStoredVal();
        }


        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $vr, $gway->getGatewayType(), TransType::VoidReturn, TransMethod::Token);
        $vr->idTrans = $transRs->idTrans->getStoredVal();

        // Record payment
        return VoidReturnReply::processReply($dbh, $vr, $uS->username, $payRs);

    }
}



class TokenResponse extends PaymentResponse {

    /**
     *
     * @var CreditTokenResponse
     */
    public $response;
    public $idToken = '';


    function __construct(CreditTokenResponse $creditTokenResponse, $idPayor, $idToken, $payNotes = '') {
        $this->response = $creditTokenResponse;
        $this->paymentType = PayType::Charge;
        $this->idPayor = $idPayor;
        $this->idToken = $idToken;
        $this->invoiceNumber = $creditTokenResponse->getInvoice();
        $this->amount = $creditTokenResponse->getAuthorizeAmount();
        $this->payNotes = $payNotes;
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
        $tbl->addBodyTr(HTMLTable::makeTd($this->cardType . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd("xxxxx...". $this->cardNum));

        if ($this->cardName != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Card Holder: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($this->cardName));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Sign: ", array('class'=>'tdlabel')) . HTMLTable::makeTd('', array('style'=>'height:35px; width:250px; border: solid 1px gray;')));

    }

}
