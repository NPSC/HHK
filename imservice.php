<?php
use HHK\sec\Login;
use HHK\Exception\RuntimeException;
use HHK\sec\UserClass;
use HHK\sec\WebInit;
use HHK\SysConst\WebPageCode;
use HHK\sec\Session;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Payment\PaymentSvcs;

/**
 * imservice.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

// Configuration filename and paths
define('ciCFG_FILE', 'site.cfg' );
define('CONF_PATH', 'conf/');

require ('functions/commonFunc.php');

require ('vendor/autoload.php');

$sequence = getRandomString();

try {
    $login = new Login();
    $login->initHhkSession('conf/', 'site.cfg');

} catch (\Exception $ex) {
    session_unset();
    http_response_code(500);
    exit ();
}

try {
    $dbh = initPDO(TRUE);
} catch (RuntimeException $hex) {
    // Databasae not set up.  Nothing we can do.
    http_response_code(200);
    exit();
}

// Authenticate user
$user = (isset($_SERVER["PHP_AUTH_USER"]) ? filter_var($_SERVER['PHP_AUTH_USER'], FILTER_SANITIZE_FULL_SPECIAL_CHARS): null);
$pass = (isset($_SERVER['PHP_AUTH_PW']) ? filter_var($_SERVER["PHP_AUTH_PW"], FILTER_UNSAFE_RAW): null);

$u = new UserClass();

if (is_null($user) || $u->_checkLogin($dbh, addslashes($user), $pass, FALSE, FALSE) === FALSE) {

    header('WWW-Authenticate: Basic realm="Hospitality HouseKeeper"');
    header('HTTP/1.0 401 Unauthorized');
    exit("Not authorized");

}

$dbh = NULL;

try {
    $wInit = new WebInit(WebPageCode::Service, FALSE);
} catch (\Exception $ex) {

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
    AbstractPaymentGateway::logGwTx($wInit->dbh, '', json_encode(array('user'=>addslashes($user), 'remote IP'=>$remoteIp, 'json Error'=> json_last_error_msg(), 'sequence'=>$sequence)), $inputJSON, 'Webhook');
} catch(\Exception $ex) {
    // Do Nothing
}


// Process the webhook.
try {

    $error = PaymentSvcs::processWebhook($wInit->dbh, $data);

} catch (\Exception $ex) {

    try {
        AbstractPaymentGateway::logGwTx($wInit->dbh, '', $ex->getMessage(), json_encode($ex->getTrace()), 'Webhook Error');
    } catch(\Exception $ex) {
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
?>