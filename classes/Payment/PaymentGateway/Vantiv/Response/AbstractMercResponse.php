<?php

namespace HHK\Payment\PaymentGateway\Vantiv\Response;

use HHK\Exception\PaymentException;
use HHK\Payment\PaymentGateway\Vantiv\Helper\MpReturnCodeValues;

/**
 * AbstractMercResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */



/**
* Non Profit Software Corporation HostedCheckout PHP Client
*
* ©2013-2017 Non Profit Software Corporation - all rights reserved.
*
* Disclaimer:
* This software and all specifications and documentation contained
* herein or provided to you hereunder (the "Software") are provided
* free of charge strictly on an "AS IS" basis. No representations or
* warranties are expressed or implied, including, but not limited to,
* warranties of suitability, quality, merchantability, or fitness for a
* particular purpose (irrespective of any course of dealing, custom or
* usage of trade), and all such warranties are expressly and
* specifically disclaimed. on Profit Software Corporation shall have no
* liability or responsibility to you nor any other person or entity
* with respect to any liability, loss, or damage, including lost
* profits whether foreseeable or not, or other obligation for any cause
* whatsoever, caused or alleged to be caused directly or indirectly by
* the Software. Use of the Software signifies agreement with this
* disclaimer notice.
*/


// Base class Mercury Request and Response objects.

abstract class AbstractMercResponse {

    protected $response;

    protected $result;

    protected $tranType;
    protected $merchant;
    protected $processor = 'vantiv';

    /**
     * The child is expected to define $result.
     *
     * @param array $response
     * @throws PaymentException
     */
    function __construct($response) {
        if (is_array($response) || is_object($response)) {
            $this->response = $response;
        } else {
            throw new PaymentException('Empty response object. ');
        }
    }

    public function setProcessor($v) {
        $this->processor = $v;
    }

    public function setMerchant($v) {
        $this->merchant = $v;
    }

    public function getProcessor() {
        return $this->processor;
    }

    public function getMerchant() {
        return $this->merchant;
    }

    public function getResponseCode() {
        if (isset($this->result->ResponseCode)) {
            return $this->result->ResponseCode;
        }
        return '';
    }

    public function getResponseText() {
        if (isset($this->result->ResponseCode)) {
            return MpReturnCodeValues::responseCodeToText($this->result->ResponseCode);
        }
        return '';
    }


    public function getResultArray() {
        if (isset($this->result)) {
            return $this->result;
        }
        return array();
    }

    public function getTranType() {
        return $this->tranType;
    }


    public function getAuthorizedAmount() {
        return 0;
    }

    public function saveCardOnFile() {
        return TRUE;
    }

    public function getEMVApplicationIdentifier() {
        return '';
    }
    public function getEMVTerminalVerificationResults() {
        return '';
    }
    public function getEMVIssuerApplicationData() {
        return '';
    }
    public function getEMVTransactionStatusInformation() {
        return '';
    }
    public function getEMVApplicationResponseCode() {
        return '';
    }

    public function SignatureRequired() {
        return 1;
    }

    public function getErrorMessage() {
        return '';
    }

}
?>