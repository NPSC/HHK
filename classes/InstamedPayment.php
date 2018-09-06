<?php

/**
 * InstamedPayment.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of InstamedPayment
 *
 * @author Will
 */
class InstamedPayment {

    const securityKey = "jN+4s4/+v1E4AuQ3";
    const accountID = "NP.SOFTWARE.TEST@instamed.net";
    const ssoAlias = "1_TestPatientPaymentS";
    const userName = "testuser";
    const userID = "testuser";
    const returnURL = "http://localhost/HHK/";

    protected $patientID = 0;
    protected $patientFirstName = '';
    protected $patientLastName = '';
    protected $amount = 0;

    public function __construct($options = []) {

        if ($options['patientID']) {
            $this->patientID = $options['patientID'];
        }
        if ($options['patientFirstName']) {
            $this->patientFirstName = $options['patientFirstName'];
        }
        if ($options['patientLastName']) {
            $this->patientLastName = $options['patientLastName'];
        }
        if ($options['amount']) {
            $this->amount = $options['amount'];
        }
    }

    function doRead($url, $data) {
        //Create HTTP stream context . The function stream_context_create  returns a context with the supplied options. In this case http
        $context = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded,' . '\r\n' .
                'Content-Length:' . strlen($data) . '\r\n' .
                'Expect: 100-continue,' . '\r\n' .
                'Connection: Keep-Alive,' . '\r\n',
                'content' => $data
            )
        ));

        // POST Data
        //  $response collects the response received from the server
        return get_headers($url, 1, $context);
    }

    function newHostedPayment() {
        //Build Query to send.  The function `http_build_query` creates a URL encoded query string
        $data = http_build_query(array(
            "securityKey" => self::securityKey,
            "accountID" => self::accountID,
            "ssoAlias" => self::ssoAlias,
            "userName" => self::userName,
            "userID" => self::userID,
            "hideGuarantorID" => true,
            "patientID" => $this->patientID,
            "patientFirstName" => $this->patientFirstName,
            "patientLastName" => $this->patientLastName,
            "amount" => $this->amount,
            "responseActionType" => "header",
            'returnURL' => self::returnURL,
            "requestToken" => 'true',
            "RelayState" => "https://online.instamed.com/providers/Form/PatientPayments/NewPatientPaymentSSO",
            "lightWeight" => "true"
        ));

        $response = $this->doRead("https://online.instamed.com/providers/Forms/SSO/ACS_SAML2.aspx", $data);

        return $response['relayState'];
    }

}

$options = [
    "patientID" => "12345",
    "patientFirstName" => "Ben",
    "patientLastName" => "Dover",
    "amount" => "10"
];

$payment = new InstamedPayment($options);

$responseURL = $payment->newHostedPayment();

header("location:" . $responseURL);
?>