<?php
/**
 * HostedPayments.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */



/**
 * CardInfo - Create markup for  the Hosted CC portal.
 *
 * @author Eric
 */
class CardInfo {

    public static function sendToPortal(\PDO $dbh, VantivGateway $gway, $idPayor, $idGroup, InitCiRequest $initCi) {

        $dataArray = array();
        $trace = FALSE;

        if (strtolower($gway->getGatewayType()) == 'test') {
            $initCi->setOperatorID('test');
            $trace = TRUE;
        }

        $ciResponse = $initCi->submit($gway->getCredentials(), $trace);

        // Save raw transaction in the db.
        try {
            PaymentGateway::logGwTx($dbh, $ciResponse->getResponseCode(), json_encode($initCi->getFieldsArray()), json_encode($ciResponse->getResultArray()), 'CardInfoInit');
        } catch(Exception $ex) {
            // Do Nothing
        }

        if ($ciResponse->getResponseCode() == 0) {

            // Save the CardID in the database indexed by the guest id.
            $ciq = "replace into `card_id` (`idName`, `idGroup`, `Transaction`, `CardID`, `Init_Date`, `Frequency`, `ResponseCode`)"
                . " values ($idPayor, $idGroup, 'cof', '" . $ciResponse->getCardId() . "', now(), 'OneTime', '" . $ciResponse->getResponseCode() . "')";

            $dbh->exec($ciq);

            $dataArray = array('xfer' => $ciResponse->getCardInfoUrl(), 'cardId' => $ciResponse->getCardId());

        } else {

            // The initialization failed.
            throw new Hk_Exception_Payment("Card-On-File Gateway Error: " . $ciResponse->getResponseText());

        }

        return $dataArray;
    }


    public static function portalReply(\PDO $dbh, VantivGateway $gway, $cardId) {

        $cidInfo = PaymentSvcs::getInfoFromCardId($dbh, $cardId);

        $verify = new VerifyCIRequest();
        $verify->setCardId($cardId);

        $trace = FALSE;

        if (strtolower($gway->getGatewayType()) == 'test') {
            $trace = TRUE;
        }

        // Verify request
        $verifyResponse = $verify->submit($gway->getCredentials(), $trace);
        $vr = new CardInfoResponse($verifyResponse, $cidInfo['idName'], $cidInfo['idGroup']);

        // Save raw transaction in the db.
        try {
            PaymentGateway::logGwTx($dbh, $vr->response->getStatus(), json_encode($verify->getFieldsArray()), json_encode($vr->response->getResultArray()), 'CardInfoVerify');
        } catch(Exception $ex) {
            // Do Nothing
        }


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

    }
}

class CardInfoResponse extends PaymentResponse {


    function __construct(VerifyCiResponse $verifyCiResponse, $idPayor, $idGroup) {
        $this->response = $verifyCiResponse;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->expDate = $verifyCiResponse->getExpDate();
        $this->cardNum = str_ireplace('x', '', $verifyCiResponse->getMaskedAccount());
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
        return array('error'=>'Receipts not available.');
    }

}


class HostedCheckout {

    public static function sendToPortal(\PDO $dbh, VantivGateway $gway, $idPayor, $idGroup, $invoiceNumber, InitCkOutRequest $initCoRequest) {

        $dataArray = array();
        $trace = FALSE;

        if (strtolower($gway->getGatewayType()) == 'test') {
            $initCoRequest->setAVSAddress('4')->setAVSZip('30329');
            $initCoRequest->setOperatorID('test');
            $trace = TRUE;
        }

        $ciResponse = $initCoRequest->submit($gway->getCredentials(), $trace);

        // Save raw transaction in the db.
        try {
            PaymentGateway::logGwTx($dbh, $ciResponse->getResponseCode(), json_encode($initCoRequest->getFieldsArray()), json_encode($ciResponse->getResultArray()), 'HostedCoInit');
        } catch(Exception $ex) {
            // Do Nothing
        }


        if ($ciResponse->getResponseCode() == 0) {

            // Save payment ID
            $ciq = "replace into card_id (idName, `idGroup`, `Transaction`, InvoiceNumber, CardID, Init_Date, Frequency, ResponseCode)"
                . " values ($idPayor, $idGroup, 'hco', '$invoiceNumber', '" . $ciResponse->getPaymentId() . "', now(), 'OneTime', '" . $ciResponse->getResponseCode() . "')";

            $dbh->exec($ciq);

            $dataArray = array('xfer' => $ciResponse->getCheckoutUrl(), 'paymentId' => $ciResponse->getPaymentId());

        } else {

            // The initialization failed.
            throw new Hk_Exception_Payment("Credit Payment Gateway Error: " . $ciResponse->getResponseText());

        }


        return $dataArray;
    }

    public static function portalReply(\PDO $dbh, VantivGateway $gway, $paymentId, $payNotes) {

        $uS = Session::getInstance();

        // Check paymentId
        $cidInfo = PaymentSvcs::getInfoFromCardId($dbh, $paymentId);
        //$cidInfo['idName'] = 25; $cidInfo['idGroup'] = 14; $cidInfo['InvoiceNumber'] = '2444';

        $trace = FALSE;

        if (strtolower($gway->getGatewayType()) == 'test') {
            $trace = TRUE;
        }

        // setup the verify request
        $verify = new VerifyCkOutRequest();
        $verify->setPaymentId($paymentId);

        // Verify request
        $verifyResponse = $verify->submit($gway->getCredentials(), $trace);

//        $object = (object) [
//    'VerifyPaymentResult' =>
//        (object) [
//            "ResponseCode"=>0,
//            "Status"=>"Approved",
//             "StatusMessage"=>"AP",
//            "DisplayMessage"=>"Your transaction has been approved.",
//            "AvsResult"=>"Z",
//            "CvvResult"=>"M",
//                "AuthCode"=>"069586",
//                "Token"=>"lPlIOXLDn6qPAyPh5eYDfdWlLNIUM6HSKnkDQDTloISFQcQAhEQEoCT",
//                "RefNo"=>"5508",
//                "Invoice"=>"2444",
//                "AcqRefData"=>"KaWb585215765742020cW3TPd5e000lS ",
//                "CardType"=>"VISA",
//                "MaskedAccount"=>"xxxxxxxxxxxx7082",
//                "Amount"=>240,
//                "TaxAmount"=>0,
//                "TransPostTime"=>"2015-08-03T17:16:15.213",
//                "CardholderName"=>"Rush University Med Ctr",
//                "AVSAddress"=>"14308 Capital Dr",
//                "AVSZip"=>"60612",
//                "TranType"=>"Sale",
//                "PaymentIDExpired"=>true,
//                "CustomerCode"=>"",
//                "Memo"=>"hhkpos-3.1",
//                "AuthAmount"=>240,
//                "VoiceAuthCode"=>"",
//                "ProcessData"=>"|17|600550672000",
//                "OperatorID"=>"",
//                "TerminalName"=>"",
//                "ExpDate"=>"0220"]];

//        $verifyResponse = new VerifyCkOutResponse($object);
        $vr = new CheckOutResponse($verifyResponse, $cidInfo['idName'], $cidInfo['idGroup'], $cidInfo['InvoiceNumber'], $payNotes);


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
            }

            $transRs = Transaction::recordTransaction($dbh, $vr, $gway->getGatewayType(), $trType, TransMethod::HostedPayment);
            $vr->setIdTrans($transRs->idTrans->getStoredVal());

        } catch(Exception $ex) {

        }

        // record payment
        return SaleReply::processReply($dbh, $vr, $uS->username);

    }

}


class CheckOutResponse extends PaymentResponse {

    function __construct($verifyCkOutResponse, $idPayor, $idGroup, $invoiceNumber, $payNotes) {
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

        if ($this->response->getAuthCode() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Authorization Code: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getAuthCode(), array('style'=>'font-size:.8em;')));
        }

        if ($this->response->getResponseMessage() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Response Message: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getResponseMessage() . ($this->response->getResponseCode() == '' ? '' :  '  (Code: ' . $this->response->getResponseCode() . ")"), array('style'=>'font-size:.8em;')));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Sign: ", array('class'=>'tdlabel')) . HTMLTable::makeTd('', array('style'=>'height:35px; width:250px; border: solid 1px gray;')));

    }

}


