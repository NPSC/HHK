<?php

namespace HHK\Payment\PaymentGateway\Vantiv\Request;

use HHK\Exception\PaymentException;
use HHK\Payment\PaymentGateway\Vantiv\Response\AbstractMercResponse;
/**
 * AbstractMercTokenRequest.php
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
abstract class AbstractMercTokenRequest extends AbstractMercRequest {

    protected $tokenId;
    protected $transType;

    /**
     * The password is handled differently for Tokens.
     *
     * @param array $gway
     * @return AbstractMercResponse
     * @throws PaymentException
     */
    public function submit(array $gway, $trace = FALSE) {

        $this->setMerchantId($gway['Merchant_Id']);
        $data = array("request" => $this->getFieldsArray(), "password" => $gway['Password']);

        try {
            $txClient = new \SoapClient($gway['Trans_Url'], array('trace'=>$trace));
            $xaction = $this->execute($txClient, $data);

        } catch (\SoapFault $sf) {
            throw new PaymentException('Problem with HHK web server contacting the Mercury Payment system:  ' . $sf->getMessage());
        }

        try {
            if ($trace) {
                file_put_contents(REL_BASE_DIR . 'patch' . DS . 'soapLog.xml', $txClient->__getLastRequest() . $txClient->__getLastResponse(), FILE_APPEND);
            }
        } catch(\Exception $ex) {
            //throw new Hk_Exception_Payment('Trace file error:  ' . $ex->getMessage());
        }

        return $xaction;
    }

    public function setCardHolderName($v) {
        if ($v != '') {
            $a = substr($v, 0, 30);
            $this->fields["CardHolderName"] = $a;
        }
        return $this;
    }

    public function getCardHolderName() {
        return $this->fields["CardHolderName"];
    }

    public function setFrequency($frequency) {
        if ($frequency == 'Recurring' || $frequency == 'OneTime') {
            $this->fields["Frequency"] = $frequency;
        }
        return $this;
    }

    public function setInvoice($v) {
        if ($v != '') {
            $a = substr($v, 0, 16);
            $this->fields["Invoice"] = $a;
        }
        return $this;
    }

    public function setToken($v) {
        if ($v != '') {
            $a = substr($v, 0, 100);
            $this->fields["Token"] = $a;
        }
        return $this;
    }

    public function setTokenId($idToken) {
        $this->tokenId = $idToken;
        return $this;
    }

    public function getTokenId() {
        return $this->tokenId;
    }

    public function setMemo($v) {
        if ($v != '') {
            $a = substr($v, 0, 40);
            $this->fields["Memo"] = $a;
        }
        return $this;
    }

    public function setOperatorID($v) {
        if ($v != '') {
            $a = substr($v, 0, 10);
            $this->fields["OperatorID"] = $a;
        }
        return $this;
    }

    public function getOperatorID() {
        if (isset($this->fields["OperatorID"])) {
            return $this->fields["OperatorID"];
        }
        return '';
    }

    public function setTerminalName($v) {
        if ($v != '') {
            $a = substr($v, 0, 20);
            $this->fields["TerminalName"] = $a;
        }
        return $this;
    }

}
?>