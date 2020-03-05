<?php
/**
 * HostedPayments.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


class HostedCheckout {

    public static function sendToPortal(\PDO $dbh, VantivGateway $gway, $idPayor, $idGroup, $invoiceNumber, InitCkOutRequest $initCoRequest) {

        $uS = Session::getInstance();
        $dataArray = array();
        $trace = FALSE;

        if (strtolower($uS->mode) !== Mode::Live) {
            $initCoRequest->setOperatorID('test');
//            $trace = TRUE;
        } else {
            $initCoRequest->setOperatorID($uS->username);
        }

        $ciResponse = $initCoRequest->submit($gway->getCredentials(), $trace);
        $ciResponse->setMerchant($gway->getGatewayType());

        // Save raw transaction in the db.
        try {
            PaymentGateway::logGwTx($dbh, $ciResponse->getResponseCode(), json_encode($initCoRequest->getFieldsArray()), json_encode($ciResponse->getResultArray()), 'HostedCoInit');
        } catch(Exception $ex) {
            // Do Nothing
        }


        if ($ciResponse->getResponseCode() == 0) {

            // Save payment ID
            $ciq = "replace into card_id (idName, `idGroup`, `Transaction`, InvoiceNumber, CardID, Init_Date, Frequency, ResponseCode, Merchant)"
                . " values ($idPayor, $idGroup, 'hco', '$invoiceNumber', '" . $ciResponse->getPaymentId() . "', now(), 'OneTime', '" . $ciResponse->getResponseCode() . "', '".$gway->getGatewayName()."')";

            $dbh->exec($ciq);

            $dataArray = array('xfer' => $ciResponse->getCheckoutUrl(), 'paymentId' => $ciResponse->getPaymentId());

        } else {

            // The initialization failed.
            throw new Hk_Exception_Payment("Credit Payment Gateway Error: " . $ciResponse->getResponseText());

        }


        return $dataArray;
    }

    public static function portalReply(\PDO $dbh, VantivGateway $gway, $cidInfo, $payNotes, $payDate) {

        $uS = Session::getInstance();

        $trace = FALSE;

        if (strtolower($gway->getGatewayType()) == 'test') {
            $trace = TRUE;
        }

        // setup the verify request
        $verify = new VerifyCkOutRequest();
        $verify->setPaymentId($cidInfo['CardID']);

        // Verify request
        $verifyResponse = $verify->submit($gway->getCredentials(), $trace);

        $verifyResponse->setMerchant($gway->getGatewayType());

        $vr = new CheckOutResponse($verifyResponse, $cidInfo['idName'], $cidInfo['idGroup'], $cidInfo['InvoiceNumber'], $payNotes, $payDate);
        $vr->setResult($verifyResponse->getStatus());  // Set result string.

        // Save raw transaction in the db.
        try {
            PaymentGateway::logGwTx($dbh, $vr->response->getStatus(), json_encode($verify->getFieldsArray()), json_encode($vr->response->getResultArray()), 'HostedCoVerify');
        } catch(Exception $ex) {
            // Do Nothing
        }

        // Record transaction
        try {

            if ($verifyResponse->getTranType() == MpTranType::ReturnAmt) {
                $trType = TransType::Retrn;
            } else if ($verifyResponse->getTranType() == MpTranType::Sale) {
                $trType = TransType::Sale;
            } else if ($verifyResponse->getTranType() == MpTranType::ZeroAuth) {
                $trType = TransType::ZeroAuth;
            }

            $transRs = Transaction::recordTransaction($dbh, $vr, $gway->getGatewayType(), $trType, TransMethod::HostedPayment);
            $vr->setIdTrans($transRs->idTrans->getStoredVal());

        } catch(Exception $ex) {

        }

        if ($verifyResponse->getTranType() == MpTranType::ZeroAuth) {

            // Zero Auth Response
            if ($vr->response->getResponseCode() == 0 && $vr->response->getStatus() == MpStatusValues::Approved) {

                if ($vr->response->getToken() != '') {

                    try {
                        $vr->idToken = CreditToken::storeToken($dbh, $vr->idRegistration, $vr->idPayor, $vr->response);
                    } catch(Exception $ex) {
                        $vr->idToken = 0;
                    }

                } else {
                    $vr->idToken = 0;
                }
            }

            return $vr;

        } else {

            // record payment
            return SaleReply::processReply($dbh, $vr, $uS->username);
        }

    }

}


class CheckOutResponse extends CreditResponse {

    function __construct($verifyCkOutResponse, $idPayor, $idGroup, $invoiceNumber, $payNotes, $payDate) {
        $this->response = $verifyCkOutResponse;
        $this->paymentType = PayType::Charge;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->invoiceNumber = $invoiceNumber;
        $this->expDate = $verifyCkOutResponse->getExpDate();
        $this->cardNum = str_ireplace('x', '', $verifyCkOutResponse->getMaskedAccount());
        $this->cardType = $verifyCkOutResponse->getCardType();
        $this->cardName = $verifyCkOutResponse->getCardHolderName();
        $this->amount = $verifyCkOutResponse->getAuthorizedAmount();
        $this->payNotes = $payNotes;
        $this->setPaymentDate($payDate);
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
        $tbl->addBodyTr(HTMLTable::makeTd($this->cardType . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd("xxxxx...". $this->cardNum));

        if ($this->cardName != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Card Holder: ", array('class'=>'tdlabel')) . HTMLTable::makeTd(htmlentities($this->cardName, ENT_QUOTES)));
        }

        if ($this->response->getAuthCode() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Authorization Code: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getAuthCode() . ' ('.ucfirst($this->response->getMerchant()). ')', array('style'=>'font-size:.8em;')));
        }

        if ($this->response->getResponseMessage() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Response Message: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getResponseMessage() . ($this->response->getResponseCode() == '' ? '' :  '  (Code: ' . $this->response->getResponseCode() . ")"), array('style'=>'font-size:.8em;')));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Sign: ", array('class'=>'tdlabel')) . HTMLTable::makeTd('', array('style'=>'height:35px; width:250px; border: solid 1px gray;')));

    }

    public function getPaymentStatusCode() {

        if ($this->getStatus() == CreditPayments::STATUS_APPROVED) {
            return PaymentStatusCode::Paid;
        } else {
            return PaymentStatusCode::Declined;
        }
    }

}


