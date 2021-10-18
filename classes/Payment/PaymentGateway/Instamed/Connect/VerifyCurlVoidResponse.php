<?php

namespace HHK\Payment\PaymentGateway\Instamed\Connect;

/**
 * VerifyCurlVoidResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class VerifyCurlVoidResponse extends VerifyCurlResponse {

    public function getResponseMessage() {

        if (isset($this->result['responseMessage'])) {
            return $this->result['responseMessage'];
        }

        $this->getErrorMessage();
    }

    public function getAuthorizedAmount() {
        if (isset($this->result['Amount'])) {
            return $this->result['Amount'];
        }

        return '';
    }

    public function getRequestAmount() {
        if (isset($this->result['Amount'])) {
            return $this->result['Amount'];
        }

        return '';
    }

    public function getErrorMessage() {

        if (isset($this->result['errorMessage'])) {
            return $this->result['errorMessage'];
        }

        return '';
    }

    public function getResponseCode() {

        if (isset($this->result['responseCode'])) {
            return $this->result['responseCode'];
        }

        return '001';  //decline
    }

    public function SignatureRequired() {
        return 0;
    }

}
?>