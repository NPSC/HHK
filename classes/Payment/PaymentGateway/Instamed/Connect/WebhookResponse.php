<?php

namespace HHK\Payment\PaymentGateway\Instamed\Connect;

use HHK\Payment\GatewayResponse\{AbstractGatewayResponse, GatewayResponseInterface};
use HHK\Payment\PaymentGateway\Instamed\InstamedGateway;
use HHK\SysConst\MpTranType;
use HHK\Exception\PaymentException;

/**
 * WebhookResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


class WebhookResponse extends AbstractGatewayResponse implements GatewayResponseInterface {

    public function parseResponse(){

        if(is_array($this->response)){
            $this->result = $this->response;
        }else{
            throw new PaymentException("Webhook data is invalid.  ");
        }
            return '';
    }

    public function getResponseCode() {
        if (isset($this->result['ResponseCode'])) {
            return $this->result['ResponseCode'];
        }
        return '';
    }

    public function getStatus() {
        return $this->getResponseCode();
    }

    public function getResponseMessage() {
        if (isset($this->result['CurrentTransactionStatusDescription'])) {
            return $this->result['CurrentTransactionStatusDescription'];
        }
        return '';
    }

    public function getTranType() {
        if ($this->getTransactionAction() == 'Sale' && $this->getTransactionStatus() == InstamedGateway::VOID) {
            return MpTranType::Void;
        } else if ($this->getTransactionAction() == 'Sale') {
            return MpTranType::Sale;
        } else if ($this->getTransactionAction() == 'Refund') {
            return MpTranType::ReturnAmt;
        }
        return '';
    }

    public function getToken() {
        return $this->getPaymentPlanID();
    }

    public function saveCardonFile() {
        if (isset($this->result['SaveCardOnFile']) && strtolower($this->result['SaveCardOnFile']) === 'true') {
            return TRUE;
        }

        return FALSE;
    }

    public function getSsoToken() {
        if (isset($this->result['SingleSignOnToken'])) {
            return $this->result['SingleSignOnToken'];
        }
        return '';    }

    public function getCardType() {
        if (isset($this->result['CardType'])) {
            return $this->result['CardType'];
        }
        return '';
    }

    public function getMaskedAccount() {
        if (isset($this->result['CardLastFourDigits'])) {
            $last4 = str_replace('*', '', $this->result['CardLastFourDigits']);
            return $last4;
        }
        return '';
    }

    public function getCardHolderName() {
        if (isset($this->result['CardHolderName'])) {
            return $this->result['CardHolderName'];
        }
        return '';
    }

    public function getExpDate() {

        if (isset($this->result['ExpDate'])) {
            return str_replace('/', '', $this->result['ExpDate']);
        }

        return '';
    }

    public function SignatureRequired() {
        return 0;
    }

    public function getAuthorizedAmount() {

        if ($this->getPartialPaymentAmount() != '') {
            return $this->getPartialPaymentAmount();
        } else if (isset($this->result['Amount'])) {
            return $this->result['Amount'];
        }

        return '';
    }

    public function getRequestAmount() {
        if (isset($this->result['RequestAmount'])) {
            return $this->result['RequestAmount'];
        }
        return '';
    }

    public function getPartialPaymentAmount() {
        if (isset($this->result['PartialApprovalAmount'])) {
            return $this->result['PartialApprovalAmount'];
        }
        return '';
    }

    public function getAuthCode() {

        if (isset($this->result['AuthorizationCode'])) {
            return $this->result['AuthorizationCode'];
        }
        return '';
    }

    public function getAVSAddress() {
        return '';
    }

    public function getAVSResult() {
        //AddressVerificationResponseCode
        return $this->result['AddressVerificationResponseCode'];
    }

    public function getAVSZip() {
        return '';
    }

    public function getCvvResult() {
        return $this->result['CardVerificationResponseCode'];
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
        return $this->getPrimaryTransactionID();
    }
    public function getProcessData() {
        return '';
    }

    public function getOperatorId() {
        if (isset($this->result['UserID'])) {
            return $this->result['UserID'];
        }
        return '';
    }

    public function getPaymentPlanID() {
        if (isset($this->result['PaymentPlanID'])) {
            return $this->result['PaymentPlanID'];
        }
        return '';
    }

    public function getPrimaryTransactionID() {
        if (isset($this->result['OriginalTransactionID'])) {
            return $this->result['OriginalTransactionID'];
        }
        return '';
    }


    public function getTransactionStatus() {
        if (isset($this->result['CurrentTransactionStatusCode'])) {
            return $this->result['CurrentTransactionStatusCode'];
        }
        return '';
    }

    public function getTransactionAction() {
        if (isset($this->result['TransactionAction'])) {
            return $this->result['TransactionAction'];
        }
        return '';
    }

    public function getTransPostTime() {
        // UCT
        if (isset($this->result['ResponseDateTime'])) {
            return $this->result['ResponseDateTime'];
        }
        return '';
    }

    public function getAuthorizationText() {
        if (isset($this->result['AuthorizationText'])) {
            return $this->result['AuthorizationText'];
        }
        return '';

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

    public function getEMVApplicationName() {
        return '';
    }


    public function getEMVCardHolderVerification() {
        return '';
    }

}
?>