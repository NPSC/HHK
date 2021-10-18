<?php

namespace HHK\Payment\GatewayResponse;

use HHK\Exception\PaymentException;

/**
 * AbstractCurlRequest.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

abstract class AbstractCurlRequest {

    public function submit($parmStr, $url, $accountId, $password, $trace = FALSE) {

        if ($url == '') {
            throw new PaymentException('Curl Request is missing the URL.  ');
        }

        $xaction = $this->execute($url, $parmStr, $accountId, $password);

        try {
            if ($trace) {
                file_put_contents(REL_BASE_DIR . 'patch' . DS . 'soapLog.xml', '; |new__' . $parmStr . '|||' . json_encode($xaction), FILE_APPEND);
            }

        } catch(\Exception $ex) {

            throw new PaymentException('Trace file error:  ' . $ex->getMessage());
        }

        return $xaction;
    }

    protected abstract function execute($url, $params, $accountId, $password);

}
?>