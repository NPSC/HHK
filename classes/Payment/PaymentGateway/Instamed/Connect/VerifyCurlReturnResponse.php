<?php

namespace HHK\Payment\PaymentGateway\Instamed\Connect;

/**
 * VerifyCurlReturnResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class VerifyCurlReturnResponse extends VerifyCurlResponse {

    public function getResponseMessage() {

        if (isset($this->result['responseMessage'])) {
            return $this->result['responseMessage'];
        }

        return $this->getErrorMessage();
    }

    public function getResponseCode() {
        if (isset($this->result['responseCode'])) {
            return $this->result['responseCode'];
        }

        return $this->getErrorCode();
    }

    public function getTransactionStatus() {

        if (isset($this->result['transactionStatus'])) {
            return $this->result['transactionStatus'];
        }

        return $this->getErrorCode();
    }

    public function getErrorMessage() {

        if (isset($this->result['errorMessage'])) {
            return $this->result['errorMessage'];
        }

        return '';
    }

    public function getErrorCode() {

        if (isset($this->result['errorCode'])) {
            return $this->result['errorCode'];
        }

        return '';
    }

}
?>