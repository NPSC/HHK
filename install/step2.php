<?php
use HHK\Common;
use HHK\sec\Login;
use HHK\sec\Session;
use HHK\Exception\RuntimeException;
use HHK\HTMLControls\HTMLContainer;
use HHK\Update\Install;
use HHK\Update\Patch;
use HHK\SysConst\WebSiteCode;
use HHK\Update\SiteConfig;
use HHK\Update\SiteLog;
use HHK\SysConst\CodeVersion;

/**
 * step2.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require_once ("InstallIncludes.php");

require_once(FUNCTIONS . 'mySqlFunc.php');

$pageTitle = "HHK Installer";

try{
    $installer = new Install();
    $uS = Session::getInstance();
}catch(Exception $e){
    echo $e->getMessage();
}

$errorMsg = '';
$resultAccumulator = "";

$resultMsg = '';

?>
<!DOCTYPE HTML>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <script type="text/javascript" src="../<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="../js/install.js"></script>
    </head>
    <body>
        <div id="page" style="width:800px;">
            <div>
                <h2 class="logo">Hospitality HouseKeeper Installation Process</h2>
                <h3>Step Two: Install Database</h3>
            </div><div class='pageSpacer'></div>
            <div id="content" style="margin:10px; width:100%;">
                <div><span id="errorMsg" style="color:red;"></span></div>

                    <table>
                        <tr>
                            <th style='text-align: right;'>URL:</th><td><?php echo $uS->databaseURL; ?></td>
                        </tr><tr>
                            <th style='text-align: right;'>Schema:</th><td><?php echo $uS->databaseName; ?></td>
                        </tr><tr>
                            <th style='text-align: right;'>User:</th><td><?php echo $uS->databaseUName; ?></td>
                        </tr>
                    </table><br/>

                    <p id="successMsg"></p>
                    <form method="post" action="step2.php" name="form1" id="installdb">
                        <fieldset>
                            <legend>1.  Install Database</legend>
                            <button type="submit" name="btnSave" id="btnSave">Install DB</button>
                        </fieldset>
                    </form>
                    <form method="post" action="step2.php" name="form1" id="loadmd">
                        <fieldset>
                            <legend>2.  Load Metadata</legend>
                            <p>Admin account password: <input type='password' id='txtpw1'/><span id='spanpwerror' style='color:red; margin-left: .5em;'></span></p>
                            <p>Admin account password again: <input type='password' id='txtpw2'/></p>

                            <button type="submit" id="btnMeta" style="margin:20px;">Load Metadata</button><span id='spanDone' style='font-weight: bold;'></span>
                        </fieldset>
                    </form>
                    <fieldset>
                        <legend>3.  Load Zip Codes</legend>
                        <form enctype="multipart/form-data" action="" method="POST" name="formz">
                            <!-- MAX_FILE_SIZE must precede the file input field -->
                            <input type="hidden" name="MAX_FILE_SIZE" value="10000000" />
                            <!-- Name of input element determines name in $_FILES array -->
                            <input name="zipfile" type="file" />
                            <button type="submit" value="Load Zip Code File" style="margin-left:20px;margin-right:20px;">Load Zip Code File</button><span id="zipMsg"></span>
                            <?php echo $resultMsg; ?>
                        </form>
                    </fieldset>
                    <a href="step3.php">Next...</a>
            </div>
        </div>
    </body>
</html>

