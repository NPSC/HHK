<?php

namespace HHK\Payment\PaymentGateway\Deluxe\Response;

use HHK\Exception\PaymentException;
use HHK\Payment\GatewayResponse\AbstractGatewayResponse;
use HHK\Payment\GatewayResponse\GatewayResponseInterface;
use HHK\Tables\PaymentGW\Guest_TokenRS;

/**
 * RefundGatewayResponse.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2024 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class RefundGatewayResponse extends AbstractGatewayResponse implements GatewayResponseInterface {

    protected Guest_TokenRS $tokenRS;

    public function __construct($response, Guest_TokenRS $tokenRS, $tranType = ''){
        $this->tokenRS = $tokenRS;
        parent::__construct($response, $tranType);
    }

    protected function parseResponse() {

        if(is_array($this->response)){
            $this->result = $this->response;
        }else{
            throw new PaymentException("Refund response is missing from the payment gateway response.  ");
        }
    }

    public function getResponseCode() {

        if (isset($this->result['responseCode'])) {
            return $this->result['responseCode'];
        }

        return '';
    }

    public function getStatus(){
        return $this->getResponseCode();
    }

    public function getMessage() {

        if (isset($this->result['responseMessage']) && is_string($this->result['responseMessage'])) {
            return $this->result['responseMessage'];
        }else if (isset($this->result['responseMessage']) && is_array($this->result['responseMessage'])){
            return implode(", ", $this->result['responseMessage']);
        }

        return '';
    }

    public function getResponseMessage(){
        return $this->getMessage();
    }

    public function getAuthorizedAmount()
    {
        if (isset($this->result['amountApproved'])) {
            return $this->result['amountApproved'];
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
        return $this->tokenRS->CardHolderName->getStoredVal();
    }
    
    /**
     * @inheritDoc
     */
    public function getCardType() {
        return $this->tokenRS->CardType->getStoredVal();
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
    public function getExpDate() {
        return $this->tokenRS->ExpDate->getStoredVal();
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
        return $this->tokenRS->MaskedAccount->getStoredVal();
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
    public function getToken() {
        $this->tokenRS->Token->getStoredVal();
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
    public function SignatureRequired() {
    }
}