<?php

namespace HHK\Payment\PaymentGateway\Deluxe\Response;

use HHK\Exception\PaymentException;
use HHK\Payment\GatewayResponse\AbstractGatewayResponse;
use HHK\Payment\GatewayResponse\GatewayResponseInterface;

/**
 * VoidGatewayResponse.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2024 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class VoidGatewayResponse extends AbstractGatewayResponse implements GatewayResponseInterface {

    protected function parseResponse() {

        if(is_array($this->response)){
            $this->result = $this->response;
        }else{
            throw new PaymentException("Void response is missing from the payment gateway response.  ");
        }
    }

    public function getResponseCode() {

        if (isset($this->result['responseCode'])) {
            return $this->result['responseCode'];
        }

        return '';
    }

    public function getResponseMessage() {

        if (isset($this->result['responseMessage']) && is_array($this->result['responseMessage'])) {
            return implode(', ', $this->result['responseMessage']);
        }else if(isset($this->result['responseMessage'])){
            return $this->result['responseMessage'];
        }

        return '';
    }

    public function getAuthorizedAmount()
    {
        if (isset($this->result['amount'])) {
            return $this->result['amount'];
        }

        return '';
    }

    public function getAcqRefData() {
        if(isset($this->result['paymentId'])){
            return $this->result['paymentId'];
        }

        return '';
    }
    
    public function getAuthCode() {
        if(isset($this->result['authResponse'])){
            return $this->result['authResponse'];
        }

        return '';
    }
    
    /**
     * @inheritDoc
     */
    public function getAuthorizationText() {
    }
    
    /**
     * @inheritDoc
     */
    public function getAVSAddress() {
    }
    
    /**
     * @inheritDoc
     */
    public function getAVSResult() {
    }
    
    /**
     * @inheritDoc
     */
    public function getAVSZip() {
    }
    
    /**
     * @inheritDoc
     */
    public function getCardHolderName() {
    }
    
    /**
     * @inheritDoc
     */
    public function getCardType() {
    }
    
    /**
     * @inheritDoc
     */
    public function getCvvResult() {
    }
    
    /**
     * @inheritDoc
     */
    public function getEMVApplicationIdentifier() {
    }
    
    /**
     * @inheritDoc
     */
    public function getEMVApplicationResponseCode() {
    }
    
    /**
     * @inheritDoc
     */
    public function getEMVIssuerApplicationData() {
    }
    
    /**
     * @inheritDoc
     */
    public function getEMVTerminalVerificationResults() {
    }
    
    /**
     * @inheritDoc
     */
    public function getEMVTransactionStatusInformation() {
    }
    
    /**
     * @inheritDoc
     */
    public function getErrorMessage() {
    }
    
    /**
     * @inheritDoc
     */
    public function getExpDate() {
    }
    
    /**
     * @inheritDoc
     */
    public function getInvoiceNumber() {
        if(isset($this->result['invoiceNumber'])){
            return $this->result['invoiceNumber'];
        }

        return '';
    }
    
    /**
     * @inheritDoc
     */
    public function getMaskedAccount() {
    }
    
    /**
     * @inheritDoc
     */
    public function getMerchant() {
    }
    
    /**
     * @inheritDoc
     */
    public function getOperatorId() {
    }
    
    /**
     * @inheritDoc
     */
    public function getPartialPaymentAmount() {
    }
    
    /**
     * @inheritDoc
     */
    public function getProcessData() {
    }
    
    /**
     * @inheritDoc
     */
    public function getProcessor() {
    }
    
    /**
     * @inheritDoc
     */
    public function getRefNo() {
    }
    
    /**
     * @inheritDoc
     */
    public function getRequestAmount() {
    }
    
    /**
     * @inheritDoc
     */
    public function getStatus() {
        return $this->getResponseCode();
    }
    
    /**
     * @inheritDoc
     */
    public function getToken() {
    }
    
    /**
     * @inheritDoc
     */
    public function getTransactionStatus() {
    }
    
    /**
     * @inheritDoc
     */
    public function getTransPostTime() {
    }
    
    /**
     * @inheritDoc
     */
    public function getTranType() {
    }
    
    /**
     * @inheritDoc
     */
    public function saveCardonFile() {
    }
    
    /**
     * @inheritDoc
     */
    public function SignatureRequired() {
    }
}