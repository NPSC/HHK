<?php

require ("homeIncludes.php");


require (DB_TABLES . 'PaymentGwRS.php');
require (DB_TABLES . 'PaymentsRS.php');

require (PMT . 'GatewayConnect.php');
require (PMT . 'PaymentGateway.php');
require (PMT . 'PaymentResponse.php');

// Find Remote IP Address
if (filter_has_var(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR')) {
    $remoteIp = filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR', FILTER_VALIDATE_IP);
} else {
    $remoteIp = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
}

$inputJSON = '';

if (isset($_REQUEST)) {
    $inputJSON = json_encode($_REQUEST);
}

try {
    $dbh = initPDO(TRUE);
} catch (Hk_Exception_Runtime $hex) {
    // Databasae not set up.  Nothing we can do.
    http_response_code(200);
    exit();
}


// log the data
try {
    PaymentGateway::logGwTx($dbh, '', json_encode(array('remote IP'=>$remoteIp, 'json Error'=> json_last_error_msg())), $inputJSON, 'ConvergeResponse');
} catch(Exception $ex) {
    // Do Nothing
}


?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Converge Response Page</title>
    </head>
    <body>
        <?php var_dump($_REQUEST); ?>
    </body>
</html>
