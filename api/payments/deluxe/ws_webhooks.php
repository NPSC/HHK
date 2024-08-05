<?php
use HHK\Payment\PaymentGateway\Deluxe\DeluxeGateway;
define('ROOT', '../../../');
define('ciCFG_FILE', 'site.cfg' );
define('CONF_PATH', ROOT . 'conf/');

require ('functions/commonFunc.php');

if (file_exists(ROOT.'vendor/autoload.php')) {
    require(ROOT.'vendor/autoload.php');
} else {
    exit("Unable to laod dependancies, be sure to run 'composer install'");
}

try {
    $dbh = initPDO(TRUE);
} catch (RuntimeException $hex) {
    // Databasae not set up.  Nothing we can do.
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
    DeluxeGateway::logGwTx($wInit->dbh, '', json_encode([]), json_encode($_REQUEST), 'Webhook');
} catch(\Exception $ex) {
    // Do Nothing
}