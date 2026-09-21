<?php

use HHK\Crypto;
/**
 * step1.php
 *
 * @category  Site
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
require ("InstallIncludes.php");

$mudTxt = '';
$errorMsg = '';
$clearTxt = '';

$input = filter_input_array(INPUT_POST,
    array(
        'clrtxt'=>FILTER_SANITIZE_SPECIAL_CHARS,
        'enctxt'=>FILTER_SANITIZE_SPECIAL_CHARS
    )
);

if (filter_has_var(INPUT_POST, 'btnSmt') && !empty($input['clrtxt'])) {
    $mudTxt = Crypto::encryptMessage($input['clrtxt']);
}

if (filter_has_var(INPUT_POST, 'btndec') && !empty($input['enctxt'])) {
    $clearTxt = Crypto::decryptMessage($input['enctxt']);
}



?>
<!DOCTYPE HTML>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>HHK Encrypter</title>
    </head>
    <body>
        <form method="Post">
        <div id="page" style="width:900px;">
            <div style="margin:10px;">
                <input name="clrtxt" type="text" /><br>
                <p><?php echo $mudTxt; ?></p>
                <input type="submit" name="btnSmt" value="Encrypt" />
            </div>
            <div style="margin:10px;">
                <input name="enctxt" type="text" /><br>
                <p><?php echo $clearTxt; ?></p>
                <input type="submit" name="btndec" value="Decrypt" />
            </div>
            <!-- <div style="margin:10px;">
            	<input type="submit" name="btninfo" value="Php Info" />
            </div> -->
        </div>
        </form>
        <?php  //if (isset($_POST['btninfo'])) phpinfo(); ?>

    </body>
</html>

