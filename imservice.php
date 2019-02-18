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
require ('classes/sec/UserClass.php');
require ('classes/sec/ChallengeGenerator.php');
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


$uS = Session::getInstance();

try {
    $login = new Login();
    $config = $login->initializeSession('conf/site.cfg');
} catch (Exception $ex) {
    http_response_code(500);
    exit ("<h3>" . $ex->getMessage());
}


// Override user credentials
$dbh = initPDO(TRUE);


$user = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
$pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';

$u = new UserClass();
$password = md5(md5($pass) . $login->getChallengeVar());

if ($u->_checkLogin($dbh, addslashes($user), $password, FALSE) === FALSE) {

    header('WWW-Authenticate: Basic realm="Hospitality HouseKeeper"');
    header('HTTP/1.0 401 Unauthorized');
    die ("Not authorized");

}


// Grab the data
$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, TRUE); //convert JSON into array


// dump payment plan messages.
if (isset($data['PaymentPlanTransactionType'])) {
     http_response_code(200);
     exit();
}


// Find Remote IP Address
if (filter_has_var(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR')) {
    $remoteIp = filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR', FILTER_VALIDATE_IP);
} else {
    $remoteIp = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
}


// log the data
try {
    Gateway::saveGwTx($dbh, '', json_encode(array('user'=>addslashes($user), 'remote IP'=>$remoteIp)), $inputJSON, 'Webhook');
} catch(Exception $ex) {
    // Do Nothing
}


// Deal with it
$error = PaymentSvcs::processWebhook($dbh, $data);

if($error) {
    http_response_code(500);
} else {
    http_response_code(200);
}

exit();
