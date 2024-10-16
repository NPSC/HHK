<?php

namespace HHK\Payment\PaymentGateway\Deluxe\Response;

use HHK\Exception\PaymentException;
use HHK\Payment\GatewayResponse\AbstractGatewayResponse;
use HHK\Payment\GatewayResponse\GatewayResponseInterface;
use HHK\Tables\PaymentGW\Guest_TokenRS;

/**
 * TransactionWebhookResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of PaymentGatewayResponse
 *
 * @author Will
 */
 
class TransactionWebhookResponse extends AbstractGatewayResponse implements GatewayResponseInterface {
    
    protected Guest_TokenRS $tokenRS;

    protected $invoiceNumber;

    protected $operatorId;
    
    public function __construct($response, Guest_TokenRS $tokenRS, $tranType = '', $invoiceNumber, $operatorId) {

        $this->tokenRS = $tokenRS;
        $this->operatorId = $operatorId;
        $this->invoiceNumber = $invoiceNumber;

        parent::__construct($response, $tranType);
    }

    protected function parseResponse() {

        if(is_array($this->response)){
            $this->result = $this->response;
        }else{
            throw new PaymentException("Payment response is missing from the payment gateway response.  ");
        }
    }
    
    public function getStatus() {
        return $this->getResponseCode();
    }
    
    public function getTranType() {
        return $this->tranType;
    }
    
    public function getProcessor() {
        return $this->processor;
    }
    
    public function getMerchant() {
        return $this->merchant;
    }

    public function setMerchant($v){
        $this->merchant = $v;
    }

    public function saveCardOnFile() {
        return TRUE;
    }

    public function getCardHolderName() {
        return $this->tokenRS->CardHolderName->getStoredVal();
    }
    
    public function getMaskedAccount() {
        return $this->tokenRS->MaskedAccount->getStoredVal();
    }
    
    public function getAuthorizedAmount() {
        if (isset($this->result['amountApproved'])) {
            return $this->result['amountApproved'];
        }

        return '';
    }
    
    public function getCardType() {
        return $this->tokenRS->CardType->getStoredVal();
    }
    
    public function getInvoiceNumber() {
        return $this->invoiceNumber;
    }
    
    public function getEMVApplicationIdentifier() {
        return '';
    }
    public function getEMVTerminalVerificationResults() {
        return '';
    }
    public function getEMVIssuerApplicationData() {
        return '';
    }
    public function getEMVTransactionStatusInformation() {
        return '';
    }
    public function getEMVApplicationResponseCode() {
        return '';
    }
    
    public function SignatureRequired() {
        return 0;
    }
    
    public function getErrorMessage() {
        return '';
    }
    
    public function getTransactionStatus() {
        return '';
    }
    
    public function getPartialPaymentAmount() {
        return 0;
    }
    
    public function getAuthorizationText() {
        return '';
    }
    
    public function getAVSAddress() {
        return '';
    }
    
    public function getAVSZip() {
        return '';
    }
    
    public function getExpDate() {
        return $this->tokenRS->ExpDate->getStoredVal();
    }
    
    public function getOperatorId() {
        return $this->operatorId;
    }
    
    public function setOperatorId($v) {
        $this->operatorId = $v;
    }
    
    public function getMessage() {
        if (isset($this->result['responseMessage']) && is_string($this->result['responseMessage'])) {
            return $this->result['responseMessage'];
        }else if (isset($this->result['responseMessage']) && is_array($this->result['responseMessage'])){
            return implode(", ", $this->result['responseMessage']);
        }

        return '';
    }
    
    public function getResponseCode() {
        if (isset($this->result['responseCode'])) {
            return $this->result['responseCode'];
        }

        return '';
    }
    
    public function getTransPostTime() {
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
    
    public function getRequestAmount() {
        return '';
    }
    
    public function getAVSResult() {
        return '';
    }
    
    public function getCvvResult() {
        return '';
    }
    
    public function getRefNo() {
        return '';
    }
    
    public function getProcessData() {
        return '';
    }
    
    public function getToken() {
        return $this->tokenRS->Token->getStoredVal();
    }
}