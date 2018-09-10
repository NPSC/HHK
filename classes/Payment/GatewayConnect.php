<?php

/*
 * The MIT License
 *
 * Copyright 2018 Eric.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Description of GatewayConnect
 *
 * @author Eric
 */
abstract class GatewayResponse {

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

    /**
     * The child is expected to define $result.
     *
     * @param array $response
     * @throws Hk_Exception_Payment
     */
    function __construct($response) {
        if (is_array($response) || is_object($response)) {
            $this->response = $response;
        } else {
            throw new Hk_Exception_Payment('Empty response object. ');
        }

        $this->parseResponse($response);
    }

    // Returns Result
    protected abstract function parseResponse($response);

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


    public function getAuthorizeAmount() {
        return 0;
    }

}
