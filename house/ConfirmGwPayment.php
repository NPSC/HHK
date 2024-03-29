<?php
use HHK\sec\SecurityComponent;
use HHK\sec\Session;
use HHK\Payment\PaymentGateway\Instamed\InstamedGateway;


require ("homeIncludes.php");


$secure = new SecurityComponent();
$houseUrl = $secure->getSiteURL();

$resultStr = '';
$transferPageStr = '';
$resultParms = array();
$forwardPageParms = array();

if (isset($_GET[InstamedGateway::TRANSFER_VAR])) {
    $resultStr = filter_input(INPUT_GET, InstamedGateway::TRANSFER_VAR, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

if ($resultStr == '') {

    $transferPageStr = InstamedGateway::TRANSFER_DEFAULT_PAGE;

} else {

    $resultVars = decryptMessage($resultStr);


    parse_str($resultVars, $resultParms);

    if (isset($resultParms[InstamedGateway::TRANSFER_POSTBACK_PAGE_VAR])) {

        $transferPageStr = $resultParms[InstamedGateway::TRANSFER_POSTBACK_PAGE_VAR];

        unset($resultParms[InstamedGateway::TRANSFER_POSTBACK_PAGE_VAR]);

        // Load forwarded page parms.
        if (isset($resultParms['id'])) {
            $forwardPageParms['id'] = $resultParms['id'];
        }
        if (isset($resultParms['psg'])) {
            $forwardPageParms['psg'] = $resultParms['psg'];
        }
        if (isset($resultParms['idPsg'])) {
            $forwardPageParms['idPsg'] = $resultParms['idPsg'];
        }
        if (isset($resultParms['rid'])) {
        	$forwardPageParms['rid'] = $resultParms['rid'];
        }
        if (isset($resultParms['vid'])) {
        	$forwardPageParms['vid'] = $resultParms['vid'];
        }
    }

}

$uS = Session::getInstance();
$uS->imcomplete = $resultParms;

$finalPage = $houseUrl . $transferPageStr . (count($forwardPageParms) > 0 ? '?' . http_build_query($forwardPageParms) : '');

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
