<?php

namespace HHK\Payment\PaymentGateway\Vantiv\Response;

use HHK\Exception\PaymentException;

/**
 * InitCkOutResponse.php
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


// Credit Payment Hosted transactions

class InitCkOutResponse extends AbstractMercResponse {

    /**
     * Summary of checkoutURL
     * @var string
     */
    private $checkoutURL = '';  //Checkout_Url

    /**
     * Summary of __construct
     * @param mixed $response
     * @param mixed $checkoutURL
     * @throws \HHK\Exception\PaymentException
     */
    function __construct($response, $checkoutURL) {
        parent::__construct($response);
        $this->checkoutURL = $checkoutURL;

        if (isset($this->response->InitializePaymentResult)) {
            $this->result = $this->response->InitializePaymentResult;
        } else {
            throw new PaymentException("InitializePaymentResult is missing from the payment gateway response.  ");
        }
    }

    /**
     * Summary of getMessage
     * @return mixed
     */
    public function getMessage() {
        if (isset($this->result->Message)) {
            return $this->result->Message;
        }
        return '';
    }

    /**
     * Summary of getPaymentId
     * @return mixed
     */
    public function getPaymentId() {
        if (isset($this->result->PaymentID)) {
            return $this->result->PaymentID;
        }
        return '';
    }

    /**
     * Summary of getCheckoutUrl
     * @return mixed|string
     */
    public function getCheckoutUrl() {
        return $this->checkoutURL;
    }

}
?>