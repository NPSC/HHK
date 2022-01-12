<?php

namespace HHK\Payment\PaymentGateway\Vantiv\Request;

use HHK\Payment\PaymentGateway\Vantiv\Response\InitCkOutResponse;
use HHK\Exception\PaymentException;

/**
 * InitCkOutRequest.php
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
class InitCkOutRequest extends AbstractMercRequest {

    function __construct($pageTitle = '', $displayStyle = '', $title = '') {

        if ($pageTitle != '') {
            $this->setPageTitle($pageTitle);
        }

        if ($displayStyle != '') {
            $this->setDisplayStyle($displayStyle);
        }

        $this->title = $title;
    }

    protected function execute(\SoapClient $txClient, array $data) {
        if ($this->getPaymentPageCode() == '') {
            throw new PaymentException('Mercury Payment Page is not set.  ');
        }
        return new InitCkOutResponse($txClient->InitializePayment($data), $this->gateWay[$this->getPaymentPageCode()]);
    }

    public function setFrequency($frequency) {
        $this->fields["Frequency"] = $frequency;
        return $this;
    }

    public function setCompleteURL($completeURL) {
        $this->fields["ProcessCompleteUrl"] = $completeURL;
        return $this;
    }

    public function setReturnURL($returnURL) {
        $this->fields["ReturnUrl"] = $returnURL;
        return $this;
    }

    public function setCardHolderName($v) {
        $this->fields["CardHolderName"] = $v;
        return $this;
    }

    public function setTranType($v) {
        $this->fields["TranType"] = $v;
        return $this;
    }

    public function setTotalAmount($v) {
        $amt = number_format($v, 2, '.', '');
        $this->fields["TotalAmount"] = $amt;
        return $this;
    }

    public function setInvoice($v) {
        $this->fields["Invoice"] = $v;
        return $this;
    }

    public function setMemo($v) {
        $this->fields["Memo"] = $v;
        return $this;
    }

    public function setTaxAmount($v) {
        $amt = number_format($v, 2, '.', '');
        $this->fields["TaxAmount"] = $amt;
        return $this;
    }

    public function setCVV($v) {
        //Valid values = off or on. Determines whether CVV field is displayed. Default is on.
        if (strtolower($v) == 'on') {
            $this->fields["CVV"] = 'on';
        } else if (strtolower($v) == 'off') {
            $this->fields["CVV"] = 'off';
        }
        return $this;
    }

    public function setAVSZip($v) {
        if ($v != '' && is_numeric($v)) {
            $a = substr($v, 0, 9);
            $this->fields["AVSZip"] = $a;
        }
         return $this;
    }

    public function setAVSAddress($v) {
        $this->fields["AVSAddress"] = $v;
        return $this;
    }

    public function setAVSFields($v) {
        // Valid values = Off, Zip, or Both.
        if (strtolower($v) == 'off') {
            $this->fields["AVSFields"] = 'Off';
        } else if (strtolower($v) == 'zip') {
            $this->fields["AVSFields"] = 'Zip';
        } else if (strtolower($v) == 'both') {
            $this->fields["AVSFields"] = 'Both';
        }
        return $this;
    }

    public function setOperatorID($v) {

    	if ($v != '') {
    		$v = substr($v, 0, 10);
    	}

    	$this->fields["OperatorID"] = $v;
        return $this;
    }

    public function setDisplayStyle($v) {
        // Valid values are Mercury or Custom
        if ($v != '' && strtolower($v) != 'mercury') {
            $this->fields["DisplayStyle"] = 'Custom';
        }
        return $this;
    }

    public function setLogoUrl($v) {
        if ($v != '') {
            $this->fields["LogoUrl"] = $v;
        }
        return $this;
    }

    public function setPageTitle($v) {
        if ($v != '') {
            $this->fields["PageTitle"] = $v;
        }
        return $this;
    }

    public function setOrderTotal($v) {
        // Valid values are on and off.
        if ($v === TRUE) {
            $f = 'on';
        } else {
            $f = 'off';
        }
        $this->fields["OrderTotal"] = $f;
        return $this;
    }

    /**
     *
     * @param bool $v
     * @return InitCkOutRequest
     */
    public function setPartialAuth($v) {
        // Values = on or off
        if ($v === TRUE) {
            $f = 'on';
        } else {
            $f = 'off';
        }
        $this->fields["PartialAuth"] = $f;
        return $this;
    }

    public function setDefaultSwipe($v) {
        //Valid values = Manual or Swipe
        if ($v != '') {
            $this->fields["DefaultSwipe"] = $v;
        }
        return $this;
    }

    public function setCardEntryMethod($v) {
    	if ($v != '') {
    		$this->fields["CardEntryMethod"] = $v;
    	}
    	return $this;
    }
    public function setKeypad($v) {
    	if (strtolower($v) == 'on') {
    		$this->fields["Keypad"] = 'On';
    	} else if (strtolower($v) == 'off') {
    		$this->fields['Keypad'] = 'Off';
    	}
    	return $this;
    }

}
?>