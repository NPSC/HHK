<?php

namespace HHK\Payment\GatewayResponse;

use HHK\Tables\Payment\Payment_AuthRS;
use HHK\SysConst\{MpStatusValues, PaymentStatusCode};

/**
 * GatewayConnect.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class StandInGwResponse implements GatewayResponseInterface {

    protected $pAuthRs;
    protected $invoiceNumber;
    protected $requestAmount;
    protected $operatorId;
    protected $cardholderName;
    protected $expDate;
    protected $token;
    protected $processor;
    protected $merchant;
    protected $status;

    public function __construct(Payment_AuthRS $pAuthRs, $operatorId, $cardholderName, $expDate, $token, $invoiceNumber, $amount, $status = MpStatusValues::Approved) {

        $this->pAuthRs = $pAuthRs;

        $this->invoiceNumber = $invoiceNumber;
        $this->requestAmount = $amount;
        $this->$status = $status;
        $this->operatorId = $operatorId;
        $this->expDate = $expDate;
        $this->cardholderName = $cardholderName;
        $this->token = $token;
        $this->merchant = $this->pAuthRs->Merchant->getStoredVal();
    }

    public function getAVSAddress() {
        return 'Not Available';
    }

    public function getAVSResult() {
        return $this->pAuthRs->AVS->getStoredVal();
    }

    public function getAVSZip() {
        return 'Not Available';
    }

    public function getOperatorId() {
        return $this->operatorId;
    }

    public function getAcqRefData() {
        return $this->pAuthRs->AcqRefData->getStoredVal();
    }

    public function getAuthCode() {
        return $this->pAuthRs->Approval_Code->getStoredVal();
    }

    public function getAuthorizationText() {
        return $this->pAuthRs->Response_Message->getStoredVal();
    }

    public function getAuthorizedAmount() {
        return $this->pAuthRs->Approved_Amount->getStoredVal();
    }

    public function getRequestAmount() {
        return $this->requestAmount;
    }

    public function getCardHolderName() {

        if ($this->cardholderName == '') {
            return htmlentities($this->pAuthRs->Cardholder_Name->getStoredVal(), ENT_QUOTES);
        }

        return htmlentities($this->cardholderName, ENT_QUOTES);
    }

    public function getCardType() {
        return $this->pAuthRs->Card_Type->getStoredVal();
    }

    public function getCvvResult() {
        return $this->pAuthRs->CVV->getStoredVal();
    }

    public function getExpDate() {
        return $this->expDate;
    }

    public function getStatus() {
        return $this->status;
    }

    public function SignatureRequired() {
        return $this->pAuthRs->Signature_Required->getStoredVal();
    }

    public function getInvoiceNumber() {
        return $this->invoiceNumber;
    }

    public function getMaskedAccount() {
        return $this->pAuthRs->Acct_Number->getStoredVal();
    }

    public function getPartialPaymentAmount() {
        return $this->pAuthRs->Approved_Amount->getStoredVal();
    }

    public function getProcessData() {
        return $this->pAuthRs->ProcessData->getStoredVal();
    }

    public function getRefNo() {
        return $this->pAuthRs->Reference_Num->getStoredVal();
    }

    public function isSignatureRequired() {
        if ($this->SignatureRequired() == 1) {
            return TRUE;
        }
        return FALSE;
    }

    public function getResponseCode() {
        if ($this->pAuthRs->Status_Code->getStoredVal() == PaymentStatusCode::Declined) {
            return '001';
        }

        return '000';
    }

    public function getResponseMessage() {
        return $this->pAuthRs->Status_Message->getStoredVal();
    }

    public function getTransactionStatus() {
        return $this->pAuthRs->Response_Code->getStoredVal();
    }

    public function getToken() {
        return $this->token;
    }

    public function saveCardonFile() {

        if ($this->getToken() != '') {
            return TRUE;
        }

        return FALSE;
    }

    public function getTranType() {
        return '';
    }

    public function getProcessor() {
        return $this->processor;
    }

    public function getMerchant() {
        return $this->merchant;
    }

    public function setProcessor($v) {
        $this->processor = $v;
    }

    public function setMerchant($v) {
        $this->merchant = $v;
    }

    public function getTransPostTime() {
        return $this->pAuthRs->Timestamp->getStoredVal();
    }

    public function getEMVApplicationIdentifier() {
        return $this->pAuthRs->EMVApplicationIdentifier->getStoredVal();
    }
    public function getEMVTerminalVerificationResults() {
        return $this->pAuthRs->EMVTerminalVerificationResults->getStoredVal();
    }
    public function getEMVIssuerApplicationData() {
        return $this->pAuthRs->EMVIssuerApplicationData->getStoredVal();
    }
    public function getEMVTransactionStatusInformation() {
        return $this->pAuthRs->EMVTransactionStatusInformation->getStoredVal();
    }
    public function getEMVApplicationResponseCode() {
        return $this->pAuthRs->EMVApplicationResponseCode->getStoredVal();
    }

    public function getErrorMessage() {
        return '';
    }

}
?>