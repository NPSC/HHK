<?php

namespace HHK\Payment\PaymentResponse;

use HHK\Tables\Payment\Payment_AuthRS;
use HHK\Tables\EditRS;

/**
 * AbstractCreditResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

abstract class AbstractCreditResponse extends AbstractPaymentResponse {

    /**
     * Summary of response
     * @var
     */
    public $response;
    /**
     * Summary of idPaymentAuth
     * @var int
     */
    public $idPaymentAuth;
    /**
     * Summary of cardType
     * @var string
     */
    public $cardType;
    /**
     * Summary of expDate
     * @var string
     */
    public $expDate;

    /**
     * Summary of cardNum: account number
     * @var string
     */
    public $cardNum;
    /**
     * Summary of cardName: Cardholder name
     * @var string
     */
    public $cardName;

    public $parentIdPayment = 0;

    /**
     * Summary of recordPaymentAuth
     * @param \PDO $dbh
     * @param string $paymentGatewayName
     * @param mixed $username
     * @return void
     */
    public function recordPaymentAuth(\PDO $dbh, $paymentGatewayName, $username) {

        if ($this->idPayment > 0) {

            //Payment Detail
            $pDetailRS = new Payment_AuthRS();
            $pDetailRS->idPayment->setNewVal($this->idPayment);
            $pDetailRS->Approved_Amount->setNewVal($this->response->getAuthorizedAmount());
            $pDetailRS->Approval_Code->setNewVal($this->response->getAuthCode());
            $pDetailRS->Status_Message->setNewVal($this->response->getResponseMessage());
            $pDetailRS->Reference_Num->setNewVal($this->response->getRefNo());
            $pDetailRS->Acct_Number->setNewVal($this->response->getMaskedAccount());
            $pDetailRS->Card_Type->setNewVal($this->response->getCardType());
            $pDetailRS->Cardholder_Name->setNewVal($this->response->getCardHolderName());
            $pDetailRS->AVS->setNewVal($this->response->getAVSResult());
            $pDetailRS->Invoice_Number->setNewVal($this->getInvoiceNumber());
            $pDetailRS->idTrans->setNewVal($this->getIdTrans());
            $pDetailRS->AcqRefData->setNewVal($this->response->getAcqRefData());
            $pDetailRS->ProcessData->setNewVal($this->response->getProcessData());
            $pDetailRS->CVV->setNewVal($this->response->getCvvResult());
            $pDetailRS->Processor->setNewVal($paymentGatewayName);
            $pDetailRS->Merchant->setNewVal($this->response->getMerchant());
            $pDetailRS->Response_Message->setNewVal($this->response->getAuthorizationText());
            $pDetailRS->Response_Code->setNewVal($this->response->getTransactionStatus());
            $pDetailRS->Customer_Id->setNewVal($this->response->getOperatorId());
            $pDetailRS->Signature_Required->setNewVal($this->response->SignatureRequired());
            $pDetailRS->PartialPayment->setNewVal($this->response->getPartialPaymentAmount() > 0 ? 1 : 0);

            $pDetailRS->Updated_By->setNewVal($username);
            $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
            $pDetailRS->Status_Code->setNewVal($this->getPaymentStatusCode());

            // EMV
            $pDetailRS->EMVApplicationIdentifier->setNewVal($this->response->getEMVApplicationIdentifier());
            $pDetailRS->EMVApplicationResponseCode->setNewVal($this->response->getEMVApplicationResponseCode());
            $pDetailRS->EMVIssuerApplicationData->setNewVal($this->response->getEMVIssuerApplicationData());
            $pDetailRS->EMVTerminalVerificationResults->setNewVal($this->response->getEMVTerminalVerificationResults());
            $pDetailRS->EMVTransactionStatusInformation->setNewVal($this->response->getEMVTransactionStatusInformation());


            $this->idPaymentAuth = EditRS::insert($dbh, $pDetailRS);

        }

    }

    /**
     * Summary of isPartialPayment
     * @return bool
     */
    public function isPartialPayment() {
        return $this->partialPaymentFlag;
    }

    /**
     * Summary of setPartialPayment
     * @param mixed $v
     * @return void
     */
    public function setPartialPayment($v) {
        if ($v) {
            $this->partialPaymentFlag = TRUE;
        } else {
            $this->partialPaymentFlag = FALSE;
        }
    }

    /**
     * Summary of getIdPaymentAuth
     * @return int
     */
    public function getIdPaymentAuth() {
        return $this->idPaymentAuth;
    }

    public function getParentIdPayment(){
        return $this->parentIdPayment;
    }

    public function setParentIdPayment($v){
        $this->parentIdPayment = $v;
    }

}
?>