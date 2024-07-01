<?php

namespace HHK\Payment\PaymentGateway\Deluxe\Response;

use HHK\Payment\GatewayResponse\GatewayResponseInterface;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;

/**
 * AuthorizeGatewayResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of LocalGatewayResponse
 *
 * @author Eric
 */
 
class AuthorizeGatewayResponse implements GatewayResponseInterface {
    
    protected $tranType;
    protected $merchant = '';
    protected $processor = AbstractPaymentGateway::DELUXE;
    protected $operatorId;
    protected $cardHolderName;
    protected $authorizedAmount;
    protected $account;
    protected $cardType;
    protected $invoiceNumber;
    protected $token;
    protected $expDate;
    protected $acqRefData;
    
    public function __construct($token, $amount, $invoiceNumber, $cardType, $cardAcct, $expDate, $cardHolderName, $tranType, $operatorId, $acqRefData) {
        
        $this->tranType = $tranType;
        $this->setOperatorId($operatorId);
        $this->setCardHolderName( $cardHolderName);
        $this->authorizedAmount = $amount;
        $this->account = substr($cardAcct, -4);
        $this->cardType = $cardType;
        $this->invoiceNumber = $invoiceNumber;
        $this->token = $token;
        $this->expDate = $expDate;
        $this->acqRefData = $acqRefData;
    }
    
    public function getStatus() {
        return '';
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
        return $this->cardHolderName;
    }
    
    public function setCardHolderName($v) {
        $this->cardHolderName = $v;
    }
    
    public function getMaskedAccount() {
        return $this->account;
    }
    
    public function setMaskedAccount($v) {
        $this->account = $v;
    }
    
    public function getAuthorizedAmount() {
        return $this->authorizedAmount;
    }
    
    public function getCardType() {
        return $this->cardType;
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
        return $this->expDate;
    }
    
    public function getOperatorId() {
        return $this->operatorId;
    }
    
    public function setOperatorId($v) {
        $this->operatorId = $v;
    }
    
    public function getResponseMessage() {
        return '';
    }
    
    public function getResponseCode() {
        return '';
    }
    
    public function getTransPostTime() {
        return '';
    }
    
    public function getAcqRefData() {
        return $this->acqRefData;
    }
    
    public function getAuthCode() {
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
        return $this->token;
    }
    
    protected function getRandomString($length=40){
        if(!is_int($length)||$length<1){
            $length = 40;
        }
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $randstring = '';
        $maxvalue = strlen($chars) - 1;
        for($i=0; $i<$length; $i++){
            $randstring .= substr($chars, rand(0,$maxvalue), 1);
        }
        return $randstring;
    }

    public function setExpDate($v){
        $this->expDate = $v;
    }
    
    
}
?>