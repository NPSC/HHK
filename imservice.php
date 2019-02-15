<?php
/**
 * imservice.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


require ('classes/PDOdata.php');

require ('classes/tables/HouseRS.php');
require ('classes/tables/PaymentGwRS.php');
require ('classes/tables/PaymentsRS.php');

require ('functions/commonFunc.php');
require ('classes/config/Lite.php');
require ('classes/sec/sessionClass.php');
require ('classes/Exception_hk/Hk_Exception.php');
require ('classes/sec/SecurityComponent.php');
require ('classes/sec/ScriptAuthClass.php');
require ('classes/SysConst.php');
require ('classes/sec/webInit.php');
require ('classes/Purchase/PriceModel.php');

require ('classes/Payment/GatewayConnect.php');
require ('classes/Payment/PaymentGateway.php');
require ('classes/Payment/Payments.php');
require ('classes/Payment/Receipt.php');
require ('classes/Payment/Invoice.php');
require ('classes/Payment/InvoiceLine.php');
require ('classes/Payment/CreditToken.php');
require ('classes/Payment/Transaction.php');

require ('classes/PaymentSvcs.php');

$userName = '';
#password = '';

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE); //convert JSON into array
$headers = apache_request_headers();

$auth_header = $headers['Authorization'];

if($auth_header != NULL) {

    $credentials = base64_decode(str_replace("Basic ","",$auth_header));
    $parts = explode(":", $credentials);
    $username = $parts[0];
    $password = $parts[1];
}

if($username != "gorecki" || $password != '98uYe$r0Q') {
    http_response_code(401);
    //echo "Invalid username or Password";
    return;
}

try {
    Gateway::saveGwTx($dbh, '', array(), $inputJSON, 'Webhook');
} catch(Exception $ex) {
    // Do Nothing
}


$payment_message = new stdClass();
$payment_message->single_sign_on_token = $input['SingleSignOnToken'];
$payment_message->response = $inputJSON;
$payment_message->is_approved = $input['CurrentTransactionStatusCode'] == 'C';
$payment_message->response_date_time = $input['ResponseDateTime'];
$error = false;

if($error) {
        http_response_code(500);

} else {
    http_response_code(200);

}

