<?php

namespace HHK\Payment\PaymentGateway\Instamed\Connect;

use HHK\Payment\GatewayResponse\AbstractCurlRequest;
use HHK\Exception\PaymentException;

/**
 * ImCurlRequest.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ImCurlRequest extends AbstractCurlRequest {

    protected function execute($url, $params, $accountId, $password) {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_USERPWD, "$accountId:$password");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $responseString = curl_exec($ch);
        $msg = curl_error($ch);
        curl_close($ch);

        if ( ! $responseString ) {
            throw new PaymentException('Network (cURL) Error: ' . $msg);
        }

        $transaction = array();
        parse_str($responseString, $transaction);

        return $transaction;
    }

}
?>