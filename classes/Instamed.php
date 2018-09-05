<?php
/**
 * Instamed.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Instamed
 *
 * @author Eric
 */

class Instamed {

    function doRead($url, $data) {
        //Create HTTP stream context . The function stream_context_create  returns a context with the supplied options. In this case http
        $context = stream_context_create(array(
            'http' => array(
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded,'.'\r\n'.
               'Content-Length:'.strlen($data).'\r\n'.
               'Expect: 100-continue,'.'\r\n'.
               'Connection: Keep-Alive,'.'\r\n',

            'content' => $data
            )
        ));

        // POST Data
        //  $response collects the response received from the server
        return get_headers($url, 1,$context);

    }

    function newHostedPayment() {
        //Build Query to send.  The function `http_build_query` creates a URL encoded query string
        $data = http_build_query(array(
         "securityKey" => "jN+4s4/+v1E4AuQ3",
         "accountID" => "NP.SOFTWARE.TEST@instamed.net",
         "ssoAlias" => "1_TestPatientPaymentS",
         "userName" => "testuser",
        "userID" => "testuser",
         "patientID" => "123456",
         "patientFirstName " => "Ben",
         "patientLastName " => "Dover",
         "amount" => "11.00",
         "responseActionType" => "header",
            'returnURL'=>'http://localhost/hhk/',
            "requestToken"=>'true',
         "RelayState" => "https://online.instamed.com/providers/Form/PatientPayments/NewPaymentSimpleSSO",
        "lightWeight" => "true"
            ));

        $response = doRead("https://online.instamed.com/providers/Forms/SSO/ACS_SAML2.aspx", $data);

        $relayState = $response['relayState'];
    }

}
