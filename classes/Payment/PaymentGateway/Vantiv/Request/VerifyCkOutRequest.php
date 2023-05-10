<?php

namespace HHK\Payment\PaymentGateway\Vantiv\Request;

use HHK\Payment\PaymentGateway\Vantiv\Response\VerifyCkOutResponse;

/**
 * VerifyCkOutRequest.php
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

class VerifyCkOutRequest extends AbstractMercRequest{

    /**
     * Summary of execute
     * @param \SoapClient $txClient
     * @param mixed $data
     * @return VerifyCkOutResponse
     */
    protected function execute(\SoapClient $txClient, array $data): VerifyCkOutResponse {
        return new VerifyCkOutResponse($txClient->VerifyPayment($data));
    }

    /**
     * Summary of setPaymentId
     * @param mixed $paymentId
     * @return VerifyCkOutRequest
     */
    public function setPaymentId($paymentId) {
        $this->fields["PaymentID"] = $paymentId;
        return $this;
    }
}
?>