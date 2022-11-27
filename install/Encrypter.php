<?php
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

if (isset($_POST['btnSmt'])) {

    if (isset($_POST['clrtxt'])) {
        $clear = filter_var($_POST['clrtxt'], FILTER_SANITIZE_STRING);
        $mudTxt = encryptMessage($clear);
    }
}

if (isset($_POST['btndec'])) {

    if (isset($_POST['enctxt'])) {
        $clear = filter_var($_POST['enctxt'], FILTER_SANITIZE_STRING);
        $clearTxt = decryptMessage($clear);
    }
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
            <div style="margin:10px;">
            	<input type="submit" name="btninfo" value="Php Info" />
            </div>
        </div>
        </form>
            <?php  if (isset($_POST['btninfo'])) phpinfo(); ?>

    </body>
</html>

