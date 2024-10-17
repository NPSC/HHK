<?php

namespace HHK\Payment\PaymentGateway\Instamed\Connect;

use HHK\Payment\GatewayResponse\{AbstractGatewayResponse, GatewayResponseInterface};
use HHK\Exception\PaymentException;

/**
 * VerifyCurlResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class VerifyCurlResponse extends AbstractGatewayResponse implements GatewayResponseInterface {

    public function parseResponse(){

        if(is_array($this->response)){
            $this->result = $this->response;
        }else{
            throw new PaymentException("Curl transaction response is invalid.  ");
        }
            return '';
    }

    public function getResponseCode() {
        if (isset($this->result['responseCode'])) {
            return $this->result['responseCode'];
        }
        return '';
    }

    public function getStatus() {
        return $this->getResponseCode();
    }

    public function getResponseMessage() {
        if (isset($this->result['responseMessage'])) {
            return $this->result['responseMessage'];
        }
        return '';
    }

    public function getMessage(){
        return $this->getResponseMessage();
    }

    public function getToken() {
        return $this->getPaymentPlanID();
    }

    public function getCardType() {
        if (isset($this->result['cardBrand'])) {
            return $this->result['cardBrand'];
        }
        return '';
    }

    public function getMaskedAccount() {
        if (isset($this->result['lastFourDigits'])) {
            return $this->result['lastFourDigits'];
        }
        return '';
    }

    public function getCardHolderName() {
        if (isset($this->result['cardHolderName'])) {
            return $this->result['cardHolderName'];
        }
        return '';
    }

    public function getExpDate() {

        if (isset($this->result['cardExpirationMonth']) && isset($this->result['cardExpirationYear'])) {

	    if($this->result['cardExpirationMonth'] < 10){
            	$month = '0' . $this->result['cardExpirationMonth'];
            }else{
	        $month = $this->result['cardExpirationMonth'];
            }

            $year = $this->result['cardExpirationYear'];



            return $month . substr($year, 2);
        }

        return '';
    }

    public function getAuthorizedAmount() {

        if ($this->getPartialPaymentAmount() != '') {
            return $this->getPartialPaymentAmount();
        } else if (isset($this->result['Amount'])) {
            return $this->result['Amount'];
        }

        return '';
    }

    public function getPartialPaymentAmount() {
        if (isset($this->result['partialApprovalAmount'])) {
            return trim($this->result['partialApprovalAmount']);
        }
        return '';
    }

    public function getRequestAmount() {
        if (isset($this->result['Amount'])) {
            return trim($this->result['Amount']);
        }
        return '';
    }

    public function getAuthCode() {

        if (isset($this->result['authorizationNumber'])) {
            return $this->result['authorizationNumber'];
        }
        return '';
    }

    public function getAVSAddress() {
        return '';
    }

    public function getAVSResult() {
        //AddressVerificationResponseCode
        return '';
    }

    public function SignatureRequired() {
        return 0;
    }

    public function isSignatureRequired() {
        if (isset($this->result['isSignatureRequired'])) {
            $sr = filter_var($this->result['isSignatureRequired'], FILTER_VALIDATE_BOOLEAN);
            return $sr;
        }

        return TRUE;
    }

    public function getAVSZip() {
        return '';
    }

    public function getCvvResult() {
        //CardVerificationResponseCode
        return '';
    }

    public function getInvoiceNumber() {
        if (isset($this->result['InvoiceNumber'])) {
            return $this->result['InvoiceNumber'];
        }

        return '';
    }

    public function getRefNo() {
        return $this->getPaymentPlanID();
    }
    public function getAcqRefData() {

        if ($this->getTransactionId() != '') {
            return $this->getTransactionId();
        }
        return $this->getPrimaryTransactionID();
    }

    public function getProcessData() {
        return $this->getTransactionId();
    }

    public function getOperatorId() {
        if (isset($this->result['userID'])) {
            return $this->result['userID'];
        }
        return '';
    }

    public function getPaymentPlanID() {
        if (isset($this->result['paymentPlanID'])) {
            return $this->result['paymentPlanID'];
        }
        return '';
    }

    public function saveCardonFile() {
        if ($this->getPaymentPlanID() != '') {
            return TRUE;
        }
        return FALSE;
    }

    public function getPrimaryTransactionID() {
        if (isset($this->result['primaryTransactionID'])) {
            return $this->result['primaryTransactionID'];
        }
        return '';
    }

    public function getTransactionId() {
        if (isset($this->result['transactionID'])) {
            return $this->result['transactionID'];
        }
        return '';
    }

    public function getTransactionStatus() {
        if (isset($this->result['primaryTransactionStatus'])) {
            return $this->result['primaryTransactionStatus'];
        } else if (isset($this->result['transactionStatus'])) {
            return $this->result['transactionStatus'];
        }
        return '';
    }

    public function getTransPostTime() {
        if (isset($this->result['transactionDate'])) {
            return $this->result['transactionDate'];
        }
        return '';
    }

    public function getAuthorizationText() {
        if (isset($this->result['authorizationText'])) {
            return $this->result['authorizationText'];
        }
        return '';

    }

    public function IsEMVVerifiedByPIN() {

        if (isset($this->result['IsEMVVerifiedByPIN'])) {
            if ($this->result['IsEMVVerifiedByPIN'] == 'true') {
                return TRUE;
            }
        }
        return FALSE;

    }

    public function getEMVApplicationIdentifier() {
        if (isset($this->result['EMVApplicationIdentifier'])) {
            return $this->result['EMVApplicationIdentifier'];
        }
        return '';
    }
    public function getEMVTerminalVerificationResults() {
        if (isset($this->result['EMVTerminalVerificationResults'])) {
            return $this->result['EMVTerminalVerificationResults'];
        }
        return '';
    }
    public function getEMVIssuerApplicationData() {
        if (isset($this->result['EMVIssuerApplicationData'])) {
            return $this->result['EMVIssuerApplicationData'];
        }
        return '';
    }
    public function getEMVTransactionStatusInformation() {
        if (isset($this->result['EMVTransactionStatusInformation'])) {
            return $this->result['EMVTransactionStatusInformation'];
        }
        return '';
    }
    public function getEMVApplicationResponseCode() {
        if (isset($this->result['EMVApplicationResponseCode'])) {
            return $this->result['EMVApplicationResponseCode'];
        }
        return '';
    }

}
?>