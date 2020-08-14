<?php

namespace HHK\Payment\PaymentGateway\Instamed\Connect;

/**
 * VerifyCurlCofResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class VerifyCurlCofResponse extends VerifyCurlResponse {

    public function getToken() {
        if (isset($this->result['saveOnFileTransactionID'])) {
            return $this->result['saveOnFileTransactionID'];
        }

        return '';
    }

    public function saveCardonFile() {
        if ($this->getToken() != '') {
            return TRUE;
        }
        return FALSE;
    }

    public function SignatureRequired() {
        return 0;
    }

}
?>