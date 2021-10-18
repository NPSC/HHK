<?php

namespace HHK\Payment\PaymentGateway\Instamed\Connect;

use HHK\Payment\GatewayResponse\AbstractGatewayResponse;
use HHK\Exception\PaymentException;

/**
 * PollingResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class PollingResponse extends AbstractGatewayResponse {

    const WAIT = 'NEW';
    const EXPIRED = 'EXPIRED';
    const COMPLETE = 'complete';


    protected function parseResponse() {

        if (isset($this->response->GetSSOTokenStatusResponse)) {
            $this->result = $this->response->GetSSOTokenStatusResponse;
        } else {
            throw new PaymentException("GetSSOTokenStatusResponse is missing from the payment gateway response.  ");
        }
    }

    public function getResponseCode() {

        if (isset($this->result['GetSSOTokenStatusResult'])) {
            return $this->result['GetSSOTokenStatusResult'];
        } else {
            throw new PaymentException("GetSSOTokenStatusResult is missing from the payment gateway response.  ");
        }
    }

    public function isWaiting() {
        if ($this->getResponseCode() == PollingResponse::WAIT) {
            return TRUE;
        }
        return FALSE;
    }

    public function isExpired() {
        if ($this->getResponseCode() == PollingResponse::EXPIRED) {
            return TRUE;
        }
        return FALSE;
    }

    public function isComplete() {
        if ($this->getResponseCode() == PollingResponse::COMPLETE) {
            return TRUE;
        }
        return FALSE;
    }

}
?>