<?php
/**
 * imservice.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ('functions/commonFunc.php');
require ('classes/config/Lite.php');

require ('classes/PDOdata.php');
require ('classes/Exception_hk/Hk_Exception.php');
require 'classes/SiteConfig.php';
require ('classes/SysConst.php');

require ('classes/tables/HouseRS.php');
require ('classes/tables/PaymentGwRS.php');
require ('classes/tables/PaymentsRS.php');

require ('classes/sec/sessionClass.php');
require ('classes/sec/Login.php');
require ('classes/sec/SecurityComponent.php');
require ('classes/sec/ScriptAuthClass.php');

require ('classes/MercPay/Gateway.php');
require ('classes/Payment/GatewayConnect.php');
require ('classes/Payment/PaymentGateway.php');
require ('classes/Payment/Payments.php');
require ('classes/Payment/CreditToken.php');
require ('classes/Payment/Transaction.php');

require ('classes/PaymentSvcs.php');

$valid_passwords = array ("gorecki" => '98uYe$r0Q');
$valid_users = array_keys($valid_passwords);

$user = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
$pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';

$validated = (in_array($user, $valid_users)) && ($pass == $valid_passwords[$user]);

if (!$validated) {
  header('WWW-Authenticate: Basic realm="Hospitality HouseKeeper"');
  header('HTTP/1.0 401 Unauthorized');
  die ("Not authorized");
}

// Grab the data
$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, TRUE); //convert JSON into array


// dump payment plan messages.
//if (isset($data['TransactionType']) === FALSE) {
//     http_response_code(200);
//     exit();
//}

$login = new Login();
$config = $login->initializeSession('conf/site.cfg');

// define db connection obj
$dbh = initPDO(TRUE);


try {
    Gateway::saveGwTx($dbh, '', json_encode(array('user'=>addslashes($user))), $inputJSON, 'Webhook');
} catch(Exception $ex) {
    // Do Nothing
}

$whookResp = new WebhookResponse($data);

//$payment_message = new stdClass();
//$payment_message->single_sign_on_token = $input['SingleSignOnToken'];
//$payment_message->response = $inputJSON;
//$payment_message->is_approved = $input['CurrentTransactionStatusCode'] == 'C';
//$payment_message->response_date_time = $input['ResponseDateTime'];
$error = false;

if($error) {
    http_response_code(500);
} else {
    http_response_code(200);
}

exit();
