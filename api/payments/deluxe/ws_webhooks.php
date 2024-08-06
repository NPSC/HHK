<?php
use HHK\sec\Login;
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;

define('ROOT', '../../../');
define('ciCFG_FILE', 'site.cfg' );
define('CONF_PATH', ROOT . 'conf/');

$resp = [];

require (ROOT.'functions/commonFunc.php');

if (file_exists(ROOT.'vendor/autoload.php')) {
    require(ROOT.'vendor/autoload.php');
} else {
    http_response_code(500);
    $resp["error"] = "Unable to laod dependancies, be sure to run 'composer install'";
    echo json_encode($resp);
    exit();
}

try {
    $login = new Login();
    $login->initHhkSession(CONF_PATH, ciCFG_FILE);

} catch (\Exception $ex) {
    session_unset();
    http_response_code(500);
    $resp["error"] = $ex->getMessage();
    echo json_encode($resp);
    exit ();
}

try {
    $dbh = initPDO(TRUE);
} catch (RuntimeException $hex) {
    // Databasae not set up.  Nothing we can do.
    http_response_code(500);
    $resp["error"] = $hex->getMessage();
    echo json_encode($resp);
    exit();
}

// Find Remote IP Address
if (filter_has_var(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR')) {
    $remoteIp = filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR', FILTER_VALIDATE_IP);
} else {
    $remoteIp = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
}

$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, TRUE); //convert JSON into array

if(isset($data["EventType"])){
    // log the data
    try {
        DeluxeGateway::logGwTx($dbh, '', json_encode([]), json_encode($data), 'Webhook');
        $resp["success"] = "success";
        echo json_encode($resp);
    } catch(\Exception $ex) {
        http_response_code(500);
        $resp["error"] = $ex->getMessage();
        echo json_encode($resp);
        exit();
    }
}else{
    http_response_code(500);
    $resp["error"] = "Unable to find EventType";
    echo json_encode($resp);
    exit();
}
