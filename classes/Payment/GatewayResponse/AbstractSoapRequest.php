<?php

namespace HHK\Payment\GatewayResponse;

use HHK\Exception\PaymentException;

/**
 * AbstractSoapRequest.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

abstract class AbstractSoapRequest {

    protected $gateWay;

    public function submit(array $req, $url, $trace = FALSE) {

        try {
            // Create the Soap, prepre the data
            $txClient = new \SoapClient($url, array('trace'=>$trace));

            $xaction = $this->execute($txClient, $req);

        } catch (\SoapFault $sf) {

            throw new PaymentException('Problem with HHK web server contacting the payment gateway:  ' . $sf->getMessage() .     ' (' . $sf->getCode() . '); ' . ' Trace: ' . $sf->getTraceAsString());
        }

        try {
            if ($trace) {
                file_put_contents(REL_BASE_DIR . 'patch' . DS . 'soapLog.xml', $txClient->__getLastRequest() . $txClient->__getLastResponse(), FILE_APPEND);
            }

        } catch(\Exception $ex) {

            throw new PaymentException('Trace file error:  ' . $ex->getMessage());
        }

        return $xaction;
    }

    protected abstract function execute(\SoapClient $sc, $data);

}
?>