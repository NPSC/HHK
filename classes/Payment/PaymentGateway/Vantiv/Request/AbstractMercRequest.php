<?php

namespace HHK\Payment\PaymentGateway\Vantiv\Request;

use HHK\Exception\PaymentException;
use HHK\Payment\PaymentGateway\Vantiv\Response\AbstractMercResponse;

/**
 * AbstractMercRequest.php
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
abstract class AbstractMercRequest {

    /**
     * Request parameters array
     *
     * @var array
     */
    protected $fields = array();

    /**
     * Gateway array defined in the comment above.
     *
     * @var array
     */
    protected $gateWay = array();

    protected $paymentPageCode;

    protected $title;

    /**
     *
     * @param array $gway
     * @param boolean $trace  True to turn trace on.  This writes to the file system, which may not allow write priv.
     * @return AbstractMercResponse $xaction
     * @throws PaymentException
     */
    public function submit(array $gway, $trace = FALSE) {

        $this->setMerchantId(trim($gway['Merchant_Id']));
        $this->gateWay = $gway;

        // Keep the PW out of the object's fields array
        $req = $this->getFieldsArray();
        $req['Password'] = trim($gway['Password']);

        $data = array("request" => $req);

        try {
            // Create the Soap, prepre the data
            $txClient = new \SoapClient($gway['Credit_Url'], array('trace'=>$trace));

            // Each child object must call its own Soap function.  This can be rewritten so that the children objecs
            // set a string function name, but then we have to get into the Soap.
            $xaction = $this->execute($txClient, $data);

        } catch (\SoapFault $sf) {
            throw new PaymentException('Problem with HHK web server contacting the Mercury Payment system:  ' . $sf->getMessage() .     ' (' . $sf->getCode() . '); ' . ' Trace: ' . $sf->getTraceAsString());
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


    // Each child must call it's own soap method.
    protected abstract function execute(\SoapClient $txClient, array $data);


    protected function setMerchantId($v) {
        $this->fields['MerchantID'] = $v;
        return $this;
    }

    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }

    public function getTitle() {
        return $this->title;
    }

    public function getFieldsArray() {
        return $this->fields;
    }

    public function getPaymentPageCode() {
        return $this->paymentPageCode;
    }

    public function setPaymentPageCode($v) {
        $this->paymentPageCode = $v;
        return $this;
    }
}
?>