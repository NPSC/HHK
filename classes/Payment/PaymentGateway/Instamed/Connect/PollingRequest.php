<?php

namespace HHK\Payment\PaymentGateway\Instamed\Connect;

use HHK\Payment\GatewayResponse\AbstractSoapRequest;

/**
 * PollingRequest.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


class PollingRequest extends AbstractSoapRequest {

    protected function execute(\SoapClient $soapClient, $data) {
        return new PollingResponse($soapClient->GetSSOTokenStatus($data));
    }
}
?>