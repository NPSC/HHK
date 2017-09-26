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


// Get the site configuration object
$config = new Config_Lite(ciCFG_FILE);

$secureComp = new SecurityComponent(TRUE);

// Run as test?
/* @var $testVersion bool */
$testVersion = $config->getBool('site', 'Run_As_Test', FALSE);

$pageTitle = $config->getString("site", "Site_Name", "Hospitality House");
$adminDir = $config->getString("site", "Admin_Dir", "");
$volunteerDir = $config->getString("site", "Volunteer_Dir", "");
$houseDir = $config->getString("site", "House_Dir", "");
$trainingURL = $config->getString("site", "Training_URL", "");
$build = 'Build:' . $config->getString('code', 'Version', '*') . '.' . $config->getString('code', 'Build', '*');

$tz = $config->getString('calendar', 'TimeZone', 'America/Chicago');
date_default_timezone_set($tz);

$copyYear = date('Y');

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
        <title><?php echo $config->getString("site", "Site_Name", "Hospitality House"); ?></title>
        <link rel="icon" type="image/png" href="images/hhkIcon.png" />
        <link href='root.css' rel='stylesheet' type='text/css' />
    </head>
    <body>
        <div id="page">
            <div class="topNavigation"></div>
            <div>
                <h2 class="hhk-title">
                    <?php echo $config->getString("site", "Site_Name", "Hospitality House"); ?>
                </h2>
            </div><div class='pageSpacer'></div>
            <div style="float:right;font-size: .6em;margin-right:5px;"><?php echo $build; ?></div>
            <div id="content" style="clear:both; margin-left: 100px;margin-top:10px;">
                <div style="margin: auto; float:left; width:450px;">
                    <a href="http://nonprofitsoftwarecorp.org/products-services/hospitality-housekeeper-software/" target="blank"><img width="250" alt='Hospitality HouseKeeper Logo' src="images/hhkLogo.png"></a>
                    <div style="clear:left; margin-bottom: 20px;"></div>
                    <ul style="margin: 20px; line-height: 1.9em;">
                        <li><a href="<?php echo $secureComp->getSiteURL() . $adminDir; ?>">Administration Site</a></li>
                        <?php if ($volunteerDir != '') { ?>
                        <li><a href="<?php echo $secureComp->getSiteURL() . $volunteerDir; ?>">Volunteers' Site</a></li>
                        <?php }
                            if ($houseDir != '') { ?>
                        <li><a href="<?php echo $secureComp->getSiteURL() . $houseDir; ?>">Guest Tracking</a></li>
                        <?php }
                            if ($trainingURL != '') { ?>
                        <li><a href="<?php echo $trainingURL; ?>">Training & Demonstration</a></li>
                            <?php } ?>
                    </ul>
                    <div style="margin-top: 100px;">
                        <hr>
                        <div><a href ="http://nonprofitsoftwarecorp.org" ><div class="nplogo"></div></a></div>
                        <div style="float:right;font-size: smaller; margin-top:5px;margin-right:.3em;">&copy; <?php echo $copyYear; ?> Non Profit Software</div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
