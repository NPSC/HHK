<?php
// Confirm.php
// Confirm gateway payment.

require ("homeIncludes.php");
require (PMT . 'PaymentGateway.php');

$secure = new SecurityComponent();
$houseUrl = $secure->getSiteURL();

$resultStr = '';
$transferPageStr = '';
$resultParms = array();

if (isset($_GET[InstamedGateway::TRANSFER_VAR])) {
    $resultStr = filter_input(INPUT_GET, InstamedGateway::TRANSFER_VAR, FILTER_SANITIZE_STRING);
}

if ($resultStr == '') {

    $transferPageStr = InstamedGateway::TRANSFER_DEFAULT_PAGE;

} else {

    $resultVar = decryptMessage($resultStr);


    parse_str($resultVar, $resultParms);

    if (isset($resultParms[InstamedGateway::TRANSFER_POSTBACK_PAGE_VAR])) {

        $transferPageStr = $resultParms[InstamedGateway::TRANSFER_POSTBACK_PAGE_VAR];

        unset($resultParms[InstamedGateway::TRANSFER_POSTBACK_PAGE_VAR]);
    }

}


$finalPage = $houseUrl . $transferPageStr . '?' . http_build_query($resultParms);

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title></title>

        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>

 <script type="text/javascript">
    var forwardURL = '<?php echo $finalPage; ?>';

$(document).ready(function () {
    "use strict";

    var w = opener;

    if (!w) {
        w = parent.window;
    }
    else {
        w = opener.window;
    }

    w.location = forwardURL;

});

    </script>
    </head>
    <body>

    </body>
</html>
