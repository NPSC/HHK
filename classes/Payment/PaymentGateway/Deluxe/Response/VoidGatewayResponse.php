<?php

namespace HHK\Payment\PaymentGateway\Deluxe\Response;

use HHK\Exception\PaymentException;
use HHK\Payment\GatewayResponse\AbstractGatewayResponse;

/**
 * VoidGatewayResponse.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2024 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class VoidGatewayResponse extends AbstractGatewayResponse {

    protected function parseResponse() {

        if(is_array($this->response)){
            $this->result = $this->response;
        }else{
            throw new PaymentException("Void response is missing from the payment gateway response.  ");
        }
    }

    public function getResponseCode() {

        if (isset($this->result['responseCode'])) {
            return $this->result['responseCode'];
        }

        return '';
    }
}