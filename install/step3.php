<?php
use HHK\Common;
use HHK\sec\Login;
use HHK\Exception\RuntimeException;
use HHK\sec\Session;
use HHK\sec\SysConfig;
use HHK\sec\WebInit;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\TableLog\HouseLog;
use HHK\HTMLControls\HTMLSelector;

/**
 * step3.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("InstallIncludes.php");

try {

    $login = new Login();
    $config = $login->initHhkSession(CONF_PATH, ciCFG_FILE);
} catch (PDOException $pex) {
    echo ("Database Error.  " . $pex->getMessage());
} catch (Exception $ex) {
    echo ("<h3>Server Error</h3>" . $ex->getMessage());
}

// define db connection obj
try {
    $dbh = Common::initPDO(TRUE);
} catch (RuntimeException $hex) {
    exit('<h3>' . $hex->getMessage() . '; <a href="index.php">Continue</a></h3>');
}


// get session instance
$ssn = Session::getInstance();

SysConfig::getCategory($dbh, $ssn, "'f'", WebInit::SYS_CONFIG);
SysConfig::getCategory($dbh, $ssn, "'r'", webInit::SYS_CONFIG);
SysConfig::getCategory($dbh, $ssn, "'d'", webInit::SYS_CONFIG);
SysConfig::getCategory($dbh, $ssn, "'h'", webInit::SYS_CONFIG);
SysConfig::getCategory($dbh, $ssn, "'a'", WebInit::SYS_CONFIG);
SysConfig::getCategory($dbh, $ssn, "'hf'", webInit::SYS_CONFIG);
SysConfig::getCategory($dbh, $ssn, "'ha'", webInit::SYS_CONFIG);
SysConfig::getCategory($dbh, $ssn, "'p'", webInit::SYS_CONFIG);
SysConfig::getCategory($dbh, $ssn, "'g'", webInit::SYS_CONFIG);

$pageTitle = $ssn->siteName;

$errorMsg = '';

if (isset($_POST['btnNext'])) {
    $ssn->destroy(true);
    header('location:../index.php');
}

$rPrices = Common::readGenLookupsPDO($dbh, 'Price_Model');

$modelSel = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($rPrices, '', TRUE), array('name'=>'selModel', 'style'=>"margin-top:20px;margin-right:10px;"));

?>
<!DOCTYPE HTML>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
    </head>
    <body>
        <div id="page" style="width:900px;">
            <div>
                <h2 class="logo">Hospitality HouseKeeper Installation Process</h2>
                <h3>Step Three: Initialize House</h3>
            </div><div class='pageSpacer'></div>
            <div id="content" style="margin:10px; width:100%;">
                <div><span style="color:red;"><?php echo $errorMsg; ?></span></div>

                <form method="post" action="step3.php" name="form1" id="form1">
                    <fieldset>
                        <legend>Create Rooms</legend>
                        How Many: <input type="text" name="txtRooms" size="5" style="margin-top:20px;margin-right:10px;"/>
                        Select Room Rate Plan: <?php echo $modelSel; ?>
                        Use Financial Assistance:<input type="checkbox" name="cbFin"  style="margin-top:20px;"/>
                        <input type="submit" name="btnRoom" id="btnRoom" value="Install Rooms" style="margin-left:17px;margin-top:20px;"/>
                    </fieldset>
                    <input type="submit" name="btnNext" id="btnNext" value="3.  Done" style="margin-left:17px;margin-top:20px;"/>
                </form>
            </div>
        </div>
    </body>
</html>

