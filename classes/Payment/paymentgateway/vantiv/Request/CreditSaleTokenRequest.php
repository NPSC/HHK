<?php

namespace HHK\Payment\PaymentGateway\Vantiv\Request;

use HHK\SysConst\MpTranType;
use HHK\Payment\PaymentGateway\Vantiv\Response\CreditTokenResponse;

/**
 * CreditSaleTokenRequest.php
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



// Mercury Token transactions

class CreditSaleTokenRequest extends AbstractMercTokenRequest {

    protected function execute(\SoapClient $txClient, array $data) {
        return new CreditTokenResponse($txClient->CreditSaleToken($data), 'CreditSaleTokenResult', MpTranType::Sale);
    }

    public function setAddress($v) {
        $this->fields["Address"] = substr($v, 0, 19);
        return $this;
    }

    public function setCVV($v) {
        if ($v != '' && is_numeric($v)) {
            $a = substr($v, 0, 4);
            $this->fields["CVV"] = $a;
        }
        return $this;
    }

    public function setCustomerCode($v) {
        if ($v != '') {
            $a = substr($v, 0, 16);
            $this->fields["CustomerCode"] = $a;
        }
        return $this;
    }

    public function setPurchaseAmount($v) {
        $amt = number_format($v, 2, '.', '');
        $this->fields["PurchaseAmount"] = $amt;
        return $this;
    }

    public function setTaxAmount($v) {
        $amt = number_format($v, 2, '.', '');
        $this->fields["TaxAmount"] = $amt;
        return $this;
    }

    public function setPartialAuth($v) {
        if ($v === TRUE) {
            $f = 'true';
        } else {
            $f = 'false';
        }
        $this->fields["PartialAuth"] = $f;
        return $this;
    }

    public function setZip($v) {
        if ($v != '' && is_numeric($v)) {
            $a = substr($v, 0, 9);
            $this->fields["Zip"] = $a;
        }
        return $this;
    }

}
?>