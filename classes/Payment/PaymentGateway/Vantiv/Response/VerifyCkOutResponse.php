<?php

namespace HHK\Payment\PaymentGateway\Vantiv\Response;

use HHK\Payment\GatewayResponse\GatewayResponseInterface;
use HHK\SysConst\MpTranType;
use HHK\Exception\PaymentException;

/**
 * VerifyCkOutResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */



/**
* Non Profit Software Corporation HostedCheckout PHP Client
*
* ©2013-2017 Non Profit Software Corporation - all rights reserved.
*
* Disclaimer:
* This software and all specifications and documentation contained
* herein or provided to you hereunder (the "Software") are provided
* free of charge strictly on an "AS IS" basis. No representations or
* warranties are expressed or implied, including, but not limited to,
* warranties of suitability, quality, merchantability, or fitness for a
* particular purpose (irrespective of any course of dealing, custom or
* usage of trade), and all such warranties are expressly and
* specifically disclaimed. on Profit Software Corporation shall have no
* liability or responsibility to you nor any other person or entity
* with respect to any liability, loss, or damage, including lost
* profits whether foreseeable or not, or other obligation for any cause
* whatsoever, caused or alleged to be caused directly or indirectly by
* the Software. Use of the Software signifies agreement with this
* disclaimer notice.
*/


// Credit Payment Hosted transactions

class VerifyCkOutResponse extends AbstractMercResponse implements GatewayResponseInterface {

    function __construct($response) {
        parent::__construct($response);

        if (isset($this->response->VerifyPaymentResult)) {
            $this->result = $this->response->VerifyPaymentResult;
        } else {
            throw new PaymentException("VerifyPaymentResult is missing from the payment gateway response.  ");
        }

        $this->tranType = MpTranType::Sale;
        if (isset($this->result->TranType)) {
            $this->tranType = $this->result->TranType;
        }

    }

    public function getStatus() {
        if (isset($this->result->Status)) {
            return $this->result->Status;
        }
        return '';
    }

    public function getStatusMessage() {
        if (isset($this->result->StatusMessage)) {
            return $this->result->StatusMessage;
        }
        return '';
    }

    public function getMessage() {
        if (isset($this->result->StatusMessage)) {
            return $this->result->StatusMessage;
        }
        return '';
    }

    public function getDisplayMessage() {
        if (isset($this->result->DisplayMessage)) {
            return $this->result->DisplayMessage;
        }
        return '';
    }

    public function getToken() {
        if (isset($this->result->Token)) {
            return $this->result->Token;
        }
        return '';
    }

    public function getCardType() {
        if (isset($this->result->CardType)) {
            return $this->result->CardType;
        }
        return '';
    }

    public function getCardUsage() {
        if (isset($this->result->CardUsage)) {
            return $this->result->CardUsage;
        }
        return '';
    }

    public function getMaskedAccount() {
        if (isset($this->result->MaskedAccount)) {
            return str_ireplace('x', '', $this->result->MaskedAccount);
        }
        return '';
    }

    public function getPaymentIDExpired() {
        if (isset($this->result->PaymentIDExpired)) {
            return $this->result->PaymentIDExpired;
        }
        return '';
    }

    public function getCardHolderName() {
        if (isset($this->result->CardholderName)) {
            return $this->result->CardholderName;
        }
        return '';
    }

    public function getExpDate() {
        if (isset($this->result->ExpDate)) {
            return $this->result->ExpDate;
        }
        return '';
    }

    public function getAcqRefData() {
        if (isset($this->result->AcqRefData)) {
            return $this->result->AcqRefData;
        }
        return '';
    }

    public function getAuthorizedAmount() {
        if (isset($this->result->AuthAmount)) {
            return $this->result->AuthAmount;
        }
        return '';
    }

    public function getRequestAmount() {
        if (isset($this->result->Amount)) {
            return $this->result->Amount;
        }
        return '';
    }

    public function getAuthCode() {

        if (isset($this->result->AuthCode)) {
            return $this->result->AuthCode;
        }
        return '';
    }

    public function getAVSAddress() {
        // Address used for AVS verification. Note it is truncated to 8 characters.
        if (isset($this->result->AVSAddress)) {
            return $this->result->AVSAddress;
        }
        return '';
    }

    public function getAVSResult() {
        if (isset($this->result->AvsResult)) {
            return $this->result->AvsResult;
        }
        return '';
    }

    public function getAVSZip() {
        // Postal code used for AVS verification
        if (isset($this->result->AVSZip)) {
            return $this->result->AVSZip;
        }
        return '';
    }

    public function getCvvResult() {
        if (isset($this->result->CvvResult)) {
            return $this->result->CvvResult;
        }
        return '';
    }

    public function getInvoiceNumber() {
        if (isset($this->result->Invoice)) {
            return $this->result->Invoice;
        }
        return '';
    }

    public function getMemo() {
        if (isset($this->result->Memo)) {
            return $this->result->Memo;
        }
        return '';
    }

    public function getProcessData() {
        if (isset($this->result->ProcessData)) {
            return $this->result->ProcessData;
        }
        return '';
    }

    public function getRefNo() {
        if (isset($this->result->RefNo)) {
            return $this->result->RefNo;
        }
        return '';
    }

    public function getTaxAmount() {
        if (isset($this->result->TaxAmount)) {
            return $this->result->TaxAmount;
        }
        return '';
    }

    public function getAmount() {
        if (isset($this->result->Amount)) {
            return $this->result->Amount;
        }
        return '';
    }

    public function getTransPostTime() {
        if (isset($this->result->TransPostTime)) {
            return $this->result->TransPostTime;
        }
        return '';
    }

    public function getCustomerCode() {
        if (isset($this->result->CustomerCode)) {
            return $this->result->CustomerCode;
        }
        return '';
    }

    public function getOperatorID() {
        if (isset($this->result->OperatorID)) {
            return $this->result->OperatorID;
        }
        return '';
    }

    public function getAuthorizationText() {
        return '';
    }

    public function getTransactionStatus() {
        return '';
    }

    public function getPartialPaymentAmount() {
        return 0;
    }

    public function getResponseMessage() {
        return $this->getDisplayMessage();
    }

}
?>