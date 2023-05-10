<?php

namespace HHK\Payment\PaymentGateway\Vantiv\Response;

use HHK\Payment\GatewayResponse\GatewayResponseInterface;
use HHK\Exception\PaymentException;

/**
 * CreditTokenResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */



/**
* Non Profit Software Corporation HostedCheckout PHP Client
*
* ©2013-2023 Non Profit Software Corporation - all rights reserved.
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

// Mercury Token transactions
class CreditTokenResponse extends AbstractMercResponse implements GatewayResponseInterface {

    /**
     * Summary of __construct
     * @param mixed $response
     * @param mixed $resultName
     * @param mixed $tranType
     * @throws \HHK\Exception\PaymentException
     */
    function __construct($response, $resultName, $tranType = '') {
        parent::__construct($response);

        $this->tranType = $tranType;

        foreach ($this->response as $k => $v) {
            if ($k == $resultName) {
                $this->result = $v;
            }
        }

        if (is_null($this->result)) {
            throw new PaymentException($resultName . ' is missing from the payment gateway response. ');
        }
    }

    public function getTransactionStatus() {

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

    public function getCardHolderName() {
        if (isset($this->result->CardHolderName)) {
            return $this->result->CardHolderName;
        }

        return '';
    }

    public function setCardHolderName($v) {
        $this->result->CardHolderName = $v;
    }

    public function getExpDate() {
        return '';
    }

    public function getOperatorId() {
        if (isset($this->result->OperatorId)) {
            return $this->result->OperatorId;
        }

        return '';
    }

    public function setOperatorId($v) {
        $this->result->OperatorId = $v;
    }

    public function getResponseMessage() {
        return $this->getMessage();
    }

    public function getResponseCode() {
        return $this->getMessage();
    }

    public function getTransPostTime() {
        return '';
    }

    public function getMaskedAccount() {
        if (isset($this->result->Account)) {
            return str_ireplace('x', '', $this->result->Account);
        } else if (isset($this->result->MaskedAccount)) {
            return str_ireplace('x', '', $this->result->MaskedAccount);
        }

        return '';
    }

    public function setMaskedAccount($v) {
        $this->result->Account = $v;
    }


    public function getAcqRefData() {
        if (isset($this->result->AcqRefData)) {
            return $this->result->AcqRefData;
        }
        return '';
    }

    public function getAuthCode() {
        if (isset($this->result->AuthCode)) {
            return $this->result->AuthCode;
        }
        return '';
    }

    public function getAuthorizedAmount() {
        if (isset($this->result->AuthorizeAmount)) {
            return $this->result->AuthorizeAmount;
        } else if (isset($this->result->Amount)) {
            return $this->result->Amount;
        }
        return 0.00;
    }

    public function getRequestAmount() {
        if (isset($this->result->AuthAmount)) {
            return $this->result->AuthAmount;
        }
        return '';
    }


    public function getAVSResult() {
        if (isset($this->result->AVSResult)) {
            return $this->result->AVSResult;
        }
        return '';
    }

    public function getBatchNo() {
        if (isset($this->result->BatchNo)) {
            return $this->result->BatchNo;
        }
        return '';
    }

    public function getCardType() {
        if (isset($this->result->CardType)) {
            return $this->result->CardType;
        }
        return '';
    }

    public function getCvvResult() {
        if (isset($this->result->CVVResult)) {
            return $this->result->CVVResult;
        }
        return '';
    }

    public function getGratuityAmount() {
        if (isset($this->result->GratuityAmount)) {
            return $this->result->GratuityAmount;
        }
        return 0.00;
    }

    public function getInvoiceNumber() {
        if (isset($this->result->Invoice)) {
            return $this->result->Invoice;
        }
        return '';
    }

    public function getPurchaseAmount() {
        if (isset($this->result->PurchaseAmount)) {
            return $this->result->PurchaseAmount;
        }
        return 0.00;
    }

    public function getRefNo() {
        if (isset($this->result->RefNo)) {
            return $this->result->RefNo;
        }
        return '';
    }

    public function getProcessData() {
         if (isset($this->result->ProcessData)) {
            return $this->result->ProcessData;
        }
       return '';
    }

    public function getStatus() {
        if (isset($this->result->Status)) {
            return $this->result->Status;
        }
        return '';
    }

    public function getMessage() {
        if (isset($this->result->Message)) {
            return $this->result->Message;
        }
        return '';
    }

    public function getStatusMessage() {
        if (isset($this->result->Message)) {
            return $this->result->Message;
        }
        return '';
    }

    public function getDisplayMessage() {
        return $this->getStatus();
    }

    public function getToken() {
        if (isset($this->result->Token)) {
            return $this->result->Token;
        }
        return '';
    }

}
?>