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

    /**
     * Summary of getResponseCode
     * @return mixed
     */
    public function getResponseCode();
    /**
     * Summary of getResponseMessage
     * @return mixed
     */
    public function getResponseMessage();
    /**
     * Summary of getTranType
     * @return mixed
     */
    public function getTranType();
    /**
     * Summary of getMerchant
     * @return mixed
     */
    public function getMerchant();
    /**
     * Summary of getProcessor
     * @return mixed
     */
    public function getProcessor();

    /**
     * Summary of getAuthorizedAmount
     * @return mixed
     */
    public function getAuthorizedAmount();
    /**
     * Summary of getRequestAmount
     * @return mixed
     */
    public function getRequestAmount();
    /**
     * Summary of getPartialPaymentAmount
     * @return mixed
     */
    public function getPartialPaymentAmount();
    /**
     * Summary of getAuthCode
     * @return mixed
     */
    public function getAuthCode();
    /**
     * Summary of getTransPostTime
     * @return mixed
     */
    public function getTransPostTime();
    /**
     * Summary of getAuthorizationText
     * @return mixed
     */
    public function getAuthorizationText();
    /**
     * Summary of getRefNo
     * @return mixed
     */
    public function getRefNo();
    /**
     * Summary of getAcqRefData
     * @return mixed
     */
    public function getAcqRefData();
    /**
     * Summary of getProcessData
     * @return mixed
     */
    public function getProcessData();
    /**
     * Summary of getTransactionStatus
     * @return mixed
     */
    public function getTransactionStatus();
    /**
     * Summary of getStatus
     * @return mixed
     */
    public function getStatus();

    /**
     * Summary of getAVSAddress
     * @return mixed
     */
    public function getAVSAddress();
    /**
     * Summary of getAVSResult
     * @return mixed
     */
    public function getAVSResult();
    /**
     * Summary of getAVSZip
     * @return mixed
     */
    public function getAVSZip();
    /**
     * Summary of getCvvResult
     * @return mixed
     */
    public function getCvvResult();

    /**
     * Summary of getCardType
     * @return mixed
     */
    public function getCardType();
    /**
     * Summary of getMaskedAccount
     * @return mixed
     */
    public function getMaskedAccount();
    /**
     * Summary of getCardHolderName
     * @return mixed
     */
    public function getCardHolderName();
    /**
     * Summary of getExpDate
     * @return mixed
     */
    public function getExpDate();
    /**
     * Summary of SignatureRequired
     * @return mixed
     */
    public function SignatureRequired();

    /**
     * Summary of getToken
     * @return mixed
     */
    public function getToken();
    /**
     * Summary of saveCardonFile
     * @return mixed
     */
    public function saveCardonFile();
    /**
     * Summary of getInvoiceNumber
     * @return mixed
     */
    public function getInvoiceNumber();
    /**
     * Summary of getOperatorId
     * @return mixed
     */
    public function getOperatorId();
    /**
     * Summary of getErrorMessage
     * @return mixed
     */
    public function getErrorMessage();

    /**
     * Summary of getEMVApplicationIdentifier
     * @return mixed
     */
    public function getEMVApplicationIdentifier();
    /**
     * Summary of getEMVTerminalVerificationResults
     * @return mixed
     */
    public function getEMVTerminalVerificationResults();
    /**
     * Summary of getEMVIssuerApplicationData
     * @return mixed
     */
    public function getEMVIssuerApplicationData();
    /**
     * Summary of getEMVTransactionStatusInformation
     * @return mixed
     */
    public function getEMVTransactionStatusInformation();
    /**
     * Summary of getEMVApplicationResponseCode
     * @return mixed
     */
    public function getEMVApplicationResponseCode();

}
?>