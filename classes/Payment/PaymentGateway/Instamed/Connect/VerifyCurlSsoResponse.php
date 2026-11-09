<?php

namespace HHK\Payment\PaymentGateway\Instamed\Connect;

use HHK\Payment\PaymentGateway\Instamed\InstamedGateway;

/**
 * VerifyCurlSsoResponse.php
 *
 * Wraps the array returned by ImCurlRequest::submitSso() (the query values of
 * Instamed's relayState header) for the hosted-page initialization request.
 *
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class VerifyCurlSsoResponse extends VerifyCurlResponse {

    public function parseResponse() {

        parent::parseResponse();

        if (!isset($this->result[InstamedGateway::RELAY_STATE])) {
            $this->errors = 'response is missing. ';
        }

        return '';
    }

    public function getRelayState() {
        return $this->result[InstamedGateway::RELAY_STATE] ?? '';
    }

    public function getToken() {
        return $this->result['token'] ?? '';
    }

    public function getResponseCode() {
        return isset($this->result['respCode']) ? intval($this->result['respCode'], 10) : 0;
    }

    public function getResponseMessage() {
        return $this->result['respMessage'] ?? '';
    }

}
