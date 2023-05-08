<?php

namespace HHK\Payment\GatewayResponse;

use HHK\Exception\PaymentException;

/**
 * AbstractGatewayResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

abstract class AbstractGatewayResponse {

    /**
     *
     * @var array
     */
    protected $response;
    protected $errors;

    /**
     *
     * @var array
     */
    protected $result;

    protected $tranType;
    protected $processor;
    protected $merchant;

    /**
     * The child is expected to define $result.
     *
     * @param array $response
     * @param string $tranType
     * @throws PaymentException
     */
    function __construct($response, $tranType = '') {
        if (is_array($response) || is_object($response)) {
            $this->response = $response;
        } else {
            throw new PaymentException('Empty response object. ');
        }

        $this->tranType = $tranType;

        $this->parseResponse();
    }

    // Returns Result
    protected abstract function parseResponse();

    public abstract function getResponseCode();


    public function getResultArray() {
        if (isset($this->result)) {
            return $this->result;
        }
        return array();
    }

    public function getTranType() {
        return $this->tranType;
    }

    public function getProcessor() {
        return $this->processor;
    }

    public function getMerchant() {
        return $this->merchant;
    }

    public function setMerchant($v) {
    	$this->merchant = $v;
    }

    public function getErrorMessage() {

        if (isset($this->result['errorMessage'])) {
            return $this->result['errorMessage'];
        }
        return '';
    }

    public function saveCardonFile() {
        return TRUE;
    }
}
?>