<?php
/**
 * index.php
 *
 * @category  Site
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
define('DS', DIRECTORY_SEPARATOR);
define('P_ROOT', dirname(__FILE__) . DS);
define('ciCFG_FILE', P_ROOT . 'conf' . DS . 'site.cfg');
date_default_timezone_set('America/Chicago');

require ('classes' . DS . 'config' . DS . 'Lite.php');
require ('classes' . DS . 'sec' . DS . 'SecurityComponent.php');
require ('classes' . DS . 'Exception_hk' . DS . 'Hk_Exception.php');
require ('classes' . DS . 'SysConst.php');
require ('classes' . DS . 'PDOdata.php');
require ('functions' . DS . 'commonFunc.php');
require ('classes' . DS . 'sec' . DS . 'sessionClass.php');
require ('classes' . DS . 'sec' . DS . 'webInit.php');
require('classes' . DS . 'sec' . DS . 'Login.php');


$config = Login::initHhkSession(ciCFG_FILE);


try {
    $dbh = initPDO(TRUE);
} catch (Hk_Exception_Runtime $hex) {
    exit('<h3>' . $hex->getMessage() . '; <a href="index.php">Continue</a></h3>');
}


$pageTitle = SysConfig::getKeyValue($dbh, 'sys_config', 'siteName');


$build = 'Build:' . CodeVersion::VERSION . '.' . CodeVersion::BUILD;
$copyYear = date('Y');
$secureComp = new SecurityComponent(TRUE);

header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'"); // FF 23+ Chrome 25+ Safari 7+ Opera 19+
header("X-Content-Security-Policy: default-src 'self'; script-src 'self' style-src 'self' 'unsafe-inline';"); // IE 10+

if (SecurityComponent::isHTTPS()) {
    header('Strict-Transport-Security: max-age=31536000'); // FF 4 Chrome 4.0.211 Opera 12
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link rel="icon" type="image/png" href="images/hhkIcon.png" />
        <link href='root.css' rel='stylesheet' type='text/css' />
    </head>
    <body>
        <div id="page">
            <div class="topNavigation"></div>
            <div>
                <h2 class="hhk-title">
                    <?php echo $pageTitle; ?>
                </h2>
            </div><div class='pageSpacer'></div>
            <div style="float:right;font-size: .6em;margin-right:5px;"><?php echo $build; ?></div>
            <div id="content" style="clear:both; margin-left: 100px;margin-top:10px;">
                <div style="margin: auto; float:left; width:450px;">
                    <a href="http://nonprofitsoftwarecorp.org/products-services/hospitality-housekeeper-software/" target="blank"><img width="250" alt='Hospitality HouseKeeper Logo' src="images/hhkLogo.png"></a>
                    <div style="clear:left; margin-bottom: 20px;"></div>
                    <ul style="margin: 20px; line-height: 1.9em;">
                        <li><a href="<?php echo $secureComp->getSiteURL() . 'admin'; ?>">Administration Site</a></li>
                        <li><a href="<?php echo $secureComp->getSiteURL() . 'house'; ?>">Guest Tracking</a></li>
                        <?php if(SysConfig::getKeyValue($dbh, 'sys_config', 'volunteers')){ ?>
	                        <li><a href="<?php echo $secureComp->getSiteURL() . 'volunteer'; ?>">Volunteers' Site</a></li>
                        <?php } ?>
                    </ul>
                    <div style="margin-top: 100px;">
                        <hr>
                        <div class="nplogo"><a href ="http://nonprofitsoftwarecorp.org" ></a></div>
                        <div style="float:right;font-size: smaller; margin-top:5px;margin-right:.3em;">&copy; <?php echo $copyYear; ?> Non Profit Software Corporation</div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
