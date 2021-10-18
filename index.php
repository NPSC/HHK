<?php

use HHK\sec\{Login};
use HHK\Exception\RuntimeException;
use HHK\sec\SysConfig;
use HHK\SysConst\CodeVersion;
use HHK\sec\SecurityComponent;

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

require ('vendor/autoload.php');
require ('functions' . DS . 'commonFunc.php');

$dbh = Login::initHhkSession(ciCFG_FILE);


// try {
//     $dbh = initPDO(TRUE);
// } catch (RuntimeException $hex) {
//     exit('<h3>' . $hex->getMessage() . '; <a href="index.php">Continue</a></h3>');
// }


$pageTitle = SysConfig::getKeyValue($dbh, 'sys_config', 'siteName');


$build = 'Build:' . CodeVersion::VERSION . '.' . CodeVersion::BUILD;
$copyYear = date('Y');
$secureComp = new SecurityComponent(TRUE);

header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self';"); // FF 23+ Chrome 25+ Safari 7+ Opera 19+
header("X-Content-Security-Policy: default-src 'self'; script-src 'self' style-src 'self';"); // IE 10+

if (SecurityComponent::isHTTPS()) {
    header('Strict-Transport-Security: max-age=31536000'); // FF 4 Chrome 4.0.211 Opera 12
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $pageTitle; ?></title>
        <link rel="icon" type="image/svg+xml" href="favicon.svg" />
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
            <div class="build"><?php echo $build; ?></div>
            <div id="content" class="content">
                <div class="innerContent">
                    <a href="http://nonprofitsoftwarecorp.org/products-services/hospitality-housekeeper-software/" target="blank"><img width="250" alt='Hospitality HouseKeeper Logo' src="images/hhkLogo.png"></a>
                    <div class="divspace"></div>
                    <ul>
                        <li><a href="<?php echo $secureComp->getSiteURL() . 'admin'; ?>">Administration Site</a></li>
                        <li><a href="<?php echo $secureComp->getSiteURL() . 'house'; ?>">Guest Tracking</a></li>
                        <?php if(SysConfig::getKeyValue($dbh, 'sys_config', 'volunteers')){ ?>
	                        <li><a href="<?php echo $secureComp->getSiteURL() . 'volunteer'; ?>">Volunteers' Site</a></li>
                        <?php } ?>
                    </ul>
                    <div class="divmorespace">
                        <hr>
                        <a class="nplogo" href ="https://nonprofitsoftwarecorp.org" ></a>
                        <div class="copyright">&copy; <?php echo $copyYear; ?> Non-Profit Software Corporation</div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
