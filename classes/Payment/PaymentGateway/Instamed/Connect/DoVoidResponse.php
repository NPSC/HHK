<?php

namespace HHK\Payment\PaymentGateway\Instamed\Connect;

use HHK\Exception\PaymentException;
use HHK\Payment\GatewayResponse\AbstractGatewayResponse;

/**
 * DoVoidResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class DoVoidResponse extends AbstractGatewayResponse {

    protected function parseResponse() {

        if (isset($this->response->SecondaryCreditCardVoidRequestData)) {
            $this->result = $this->response->DoCreditCardSecondaryVoidResponse;
        } else {
            throw new PaymentException("DoCreditCardSecondaryVoidResponse is missing from the payment gateway response.  ");
        }
    }

    public function getResponseCode() {

        if (isset($this->result['PrimaryTransactionStatus'])) {
            return $this->result['PrimaryTransactionStatus'];
        }

        return '';
    }
}
?>