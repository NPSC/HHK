<?php

namespace HHK\Payment\GatewayResponse;

/**
 * GatewayResponseInterface.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


interface GatewayResponseInterface {

    public function getResponseCode();
    public function getResponseMessage();
    public function getTranType();
    public function getMerchant();
    public function getProcessor();

    public function getAuthorizedAmount();
    public function getRequestAmount();
    public function getPartialPaymentAmount();
    public function getAuthCode();
    public function getTransPostTime();
    public function getAuthorizationText();
    public function getRefNo();
    public function getAcqRefData();
    public function getProcessData();
    public function getTransactionStatus();

    public function getAVSAddress();
    public function getAVSResult();
    public function getAVSZip();
    public function getCvvResult();

    public function getCardType();
    public function getMaskedAccount();
    public function getCardHolderName();
    public function getExpDate();
    public function SignatureRequired();

    public function getToken();
    public function saveCardonFile();
    public function getInvoiceNumber();
    public function getOperatorId();
    public function getErrorMessage();

    public function getEMVApplicationIdentifier();
    public function getEMVTerminalVerificationResults();
    public function getEMVIssuerApplicationData();
    public function getEMVTransactionStatusInformation();
    public function getEMVApplicationResponseCode();

}
?>