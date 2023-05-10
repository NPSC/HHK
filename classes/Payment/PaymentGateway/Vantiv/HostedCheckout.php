<?php

namespace HHK\Payment\PaymentGateway\Vantiv;

use HHK\Payment\{CreditToken, Transaction};
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Payment\PaymentGateway\CreditPayments\SaleReply;
use HHK\Payment\PaymentGateway\Vantiv\Request\{InitCkOutRequest, VerifyCkOutRequest};
use HHK\Payment\PaymentGateway\Vantiv\Response\{InitCkOutResponse, VerifyCkOutResponse};
use HHK\SysConst\{Mode, MpStatusValues, MpTranType, TransMethod, TransType};
use HHK\sec\Session;
use HHK\Exception\PaymentException;

/**
 * HostedCheckout.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


class HostedCheckout {

    /**
     * Summary of sendToPortal
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentGateway\Vantiv\VantivGateway $gway
     * @param int $idPayor
     * @param int $idGroup
     * @param string $invoiceNumber
     * @param \HHK\Payment\PaymentGateway\Vantiv\Request\InitCkOutRequest $initCoRequest
     * @throws \HHK\Exception\PaymentException
     * @return array
     */
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

        /**
         * @var InitCkOutResponse
         */
        $ciResponse = $initCoRequest->submit($gway->getCredentials(), $trace);
        $ciResponse->setMerchant($gway->getGatewayType());

        // Save raw transaction in the db.
        try {
            AbstractPaymentGateway::logGwTx($dbh, $ciResponse->getResponseCode(), json_encode($initCoRequest->getFieldsArray()), json_encode($ciResponse->getResultArray()), 'HostedCoInit');
        } catch(\Exception $ex) {
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
            throw new PaymentException("Credit Payment Gateway Error: " . $ciResponse->getResponseText());

        }


        return $dataArray;
    }

    /**
     * Summary of portalReply
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentGateway\Vantiv\VantivGateway $gway
     * @param mixed $cidInfo
     * @param string $payNotes
     * @param string $payDate
     * @return CheckOutResponse|\HHK\Payment\PaymentResponse\AbstractCreditResponse
     */
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
        /**
         * @var VerifyCkOutResponse
         */
        $verifyResponse = $verify->submit($gway->getCredentials(), $trace);

        $verifyResponse->setMerchant($gway->getGatewayType());

        $vr = new CheckOutResponse($verifyResponse, $cidInfo['idName'], $cidInfo['idGroup'], $cidInfo['InvoiceNumber'], $payNotes, $payDate);
        $vr->setResult($verifyResponse->getStatus());  // Set result string.

        // Save raw transaction in the db.
        try {
            AbstractPaymentGateway::logGwTx($dbh, $vr->response->getStatus(), json_encode($verify->getFieldsArray()), json_encode($vr->response->getResultArray()), 'HostedCoVerify');
        } catch(\Exception $ex) {
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

        } catch(\Exception $ex) {

        }

        if ($verifyResponse->getTranType() == MpTranType::ZeroAuth) {

            // Zero Auth Response
            if ($vr->response->getResponseCode() == 0 && $vr->response->getStatus() == MpStatusValues::Approved) {

                if ($vr->response->getToken() != '') {

                    try {
                        $vr->idGuestToken = CreditToken::storeToken($dbh, $vr->idRegistration, $vr->idPayor, $vr->response);
                    } catch(\Exception $ex) {
                        $vr->idGuestToken = 0;
                    }

                } else {
                    $vr->idGuestToken = 0;
                }
            }

            return $vr;

        } else {

            // record payment
            return SaleReply::processReply($dbh, $vr, $uS->username);
        }

    }

}
?>