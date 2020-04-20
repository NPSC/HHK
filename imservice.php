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
require ('classes/sec/webInit.php');

require ('classes/Payment/GatewayConnect.php');
require ('classes/Payment/PaymentGateway.php');
require ('classes/Payment/PaymentResponse.php');
require ('classes/Payment/Receipt.php');
require ('classes/Payment/Invoice.php');
require ('classes/Payment/InvoiceLine.php');
require ('classes/Payment/CheckTX.php');
require ('classes/Payment/CashTX.php');
require ('classes/Payment/Transaction.php');

require ('classes/Payment/CreditToken.php');
require ('classes/Payment/paymentgateway/CreditPayments.php');

require ('classes/Payment/paymentgateway/instamed/InstamedConnect.php');
require ('classes/Payment/paymentgateway/instamed/InstamedResponse.php');
require ('classes/Payment/paymentgateway/instamed/InstamedGateway.php');

require ('classes/PaymentSvcs.php');

$sequence = ChallengeGenerator::getRandomString();

try {
    $login = new Login();
    $config = $login->initHhkSession('conf/site.cfg');

} catch (Exception $ex) {
    session_unset();
    http_response_code(500);
    exit ();
}

try {
    $dbh = initPDO(TRUE);
} catch (Hk_Exception_Runtime $hex) {
    // Databasae not set up.  Nothing we can do.
    http_response_code(200);
    exit();
}

// Authenticate user
$user = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
$pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';

$u = new UserClass();
$password = md5(md5($pass) . $login->getChallengeVar());

if ($u->_checkLogin($dbh, addslashes($user), $password, FALSE) === FALSE) {

    header('WWW-Authenticate: Basic realm="Hospitality HouseKeeper"');
    header('HTTP/1.0 401 Unauthorized');
    exit("Not authorized");

}

$dbh = NULL;

try {
    $wInit = new webInit(WebPageCode::Service, FALSE);
} catch (Exception $ex) {

    http_response_code(403);
    exit("Forbidden");
}

$uS = Session::getInstance();

// Grab the data
$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, TRUE); //convert JSON into array

// dump payment plan messages.
if (isset($data['PaymentPlanTransactionType'])) {
    $uS->destroy(TRUE);
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
    PaymentGateway::logGwTx($wInit->dbh, '', json_encode(array('user'=>addslashes($user), 'remote IP'=>$remoteIp, 'json Error'=> json_last_error_msg(), 'sequence'=>$sequence)), $inputJSON, 'Webhook');
} catch(Exception $ex) {
    // Do Nothing
}


// Process the webhook.
try {

    $error = PaymentSvcs::processWebhook($wInit->dbh, 'Production', $data);

} catch (Exception $ex) {

    try {
        PaymentGateway::logGwTx($wInit->dbh, '', $ex->getMessage(), json_encode($ex->getTrace()), 'Webhook Error');
    } catch(Exception $ex) {
        // Do Nothing
    }

    $error = TRUE;
}

if($error) {
    http_response_code(500);

} else {
    http_response_code(200);
}

$uS->destroy(TRUE);

exit();
