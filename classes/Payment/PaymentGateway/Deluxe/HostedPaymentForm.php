<?php

namespace HHK\Payment\PaymentGateway\Deluxe;

use HHK\Payment\{CreditToken, Transaction};
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Payment\PaymentGateway\CreditPayments\SaleReply;
use HHK\Payment\PaymentGateway\Vantiv\Request\{InitCkOutRequest, VerifyCkOutRequest};
use HHK\Payment\PaymentGateway\Vantiv\Response\{InitCkOutResponse, VerifyCkOutResponse};
use HHK\SysConst\{Mode, MpStatusValues, MpTranType, TransMethod, TransType};
use HHK\sec\Session;
use HHK\Exception\PaymentException;

/**
 * HostedPaymentForm.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2024 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


class HostedPaymentForm {

    /**
     * Summary of sendToPortal
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway $gway
     * @param int $idPayor
     * @param int $idGroup
     * @throws \HHK\Exception\PaymentException
     * @return array
     */
    public static function sendToPortal(\PDO $dbh, DeluxeGateway $gway, $idPayor, $idGroup, $manualKey, $postbackUrl, $cmd) {

        $uS = Session::getInstance();
        $dataArray = array();
        $trace = FALSE;

        $creds = $gway->getCredentials();

        $dataArray["type"] = "deluxe-hpf";
        $dataArray["idPayor"] = $idPayor;
        $dataArray["idGroup"] = $idGroup;
        $dataArray["hpfToken"] = $creds['hpfAccessToken'];
        $dataArray["useSwipe"] = ($manualKey == false);
        $dataArray["pbp"] = html_entity_decode($postbackUrl);
        $dataArray["cmd"] = $cmd;

        return $dataArray;
    }

    /**
     * Summary of portalReply
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway $gway
     * @param mixed $cidInfo
     * @param string $payNotes
     * @param string $payDate
     * @return CheckOutResponse|\HHK\Payment\PaymentResponse\AbstractCreditResponse
     */
    public static function portalReply(\PDO $dbh, DeluxeGateway $gway, $cidInfo, $payNotes, $payDate) {

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