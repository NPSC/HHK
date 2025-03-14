<?php

use HHK\sec\{Login, Labels, SecurityComponent, ScriptAuthClass, SysConfig, Session};
use HHK\Exception\RuntimeException;
use HHK\SysConst\CodeVersion;
use HHK\HTMLControls\HTMLContainer;
use HHK\SysConst\Mode;

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
define('CONF_PATH', P_ROOT . 'conf' . DS);
define('ciCFG_FILE', 'site.cfg');
date_default_timezone_set('America/Chicago');

if (file_exists('vendor/autoload.php')) {
    require('vendor/autoload.php');
} else {
    exit("Unable to laod dependancies, be sure to run 'composer install'");
}

require ('functions' . DS . 'commonFunc.php');

try {
    $dbh = Login::initHhkSession(CONF_PATH, ciCFG_FILE);
    $uS = Session::getInstance();
}catch (\Exception $e){
    exit($e->getMessage());
}

try {
    $page = new ScriptAuthClass($dbh);
} catch (Exception $ex) {
    $uS->destroy(true);
    exit('<h2>The HHK Guest Tracking Site is not enabled.</h2>');
}

// Get labels
$labels = Labels::getLabels();

// disclamer
$disclaimer = '';
if ($uS->mode != Mode::Live) {
    $disclaimer = HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup("div", 'Welcome to this demonstration version of Hospitality HouseKeeper! Do NOT use real guest or patient names.  This demonstration web site is not HIPAA compliant and not intended to be used for storing Protected Health Information.', array("class"=>"col-xl-10")), array("class"=>"row justify-content-center mb-3"));
}
$icons = array();
foreach ($uS->siteList as $r) {

    if ($r["Site_Code"] != "r") {
        $icons[$r["Site_Code"]] = "<span class='" . $r["Class"] . " mr-1'></span>";
    }
}

$pageTitle = SysConfig::getKeyValue($dbh, 'sys_config', 'siteName');
$siteName = '<h2 class="center">Hospitality Housekeeper</h2>';
$build = 'Build:' . CodeVersion::VERSION . '.' . CodeVersion::BUILD;

$announcementWidget = Login::welcomeWidget("Tip of the Week", '.');
$linkMkup = Login::getLinksMarkup($uS, $dbh);
$newsletterMkup = Login::getNewsletterMarkup();
$row2 = HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup('div', $linkMkup, array("class"=>"col-lg-7 mb-3")) . HTMLContainer::generateMarkup("div", $newsletterMkup, array("class"=>"col-lg-5")),array("class"=>"row justify-content-center mb-3"));
$footerMkup = Login::getFooterMarkup();
$secureComp = new SecurityComponent();

$cspURL = $page->getHostName() . " manage.hospitalityhousekeeper.net nonprofitsoftwarecorp.org";

header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src $cspURL; script-src $cspURL; style-src $cspURL; frame-src nonprofitsoftwarecorp.us18.list-manage.com 'unsafe-inline';"); // FF 23+ Chrome 25+ Safari 7+ Opera 19+
header("X-Content-Security-Policy: default-src $cspURL; script-src $cspURL; style-src $cspURL; frame-src nonprofitsoftwarecorp.us18.list-manage.com 'unsafe-inline';"); // IE 10+

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
        <link href="house/css/jqui/jquery-ui.min.css" rel="stylesheet" type="text/css" />
        <link href='css/bootstrap-grid.min.css' rel='stylesheet' type='text/css' />
        <link href='css/root.css' rel='stylesheet' type='text/css' />
        <script type="text/javascript" src="js/jquery-min.js"></script>
        <script type="text/javascript" src="js/jquery-ui.min.js"></script>
        <script type="text/javascript" src="js/login.js?eG=94"></script>

    </head>
    <body <?php if ($uS->testVersion) {echo "class='testbody'";} ?> >
        <div id="page">
            <div class='pageHeader'>
                <h2 class="px-3 py-2">
                    <?php echo $pageTitle; ?>
                </h2>
            </div>
            <div class="build"><?php echo $build; ?></div>
            <div id="contentDiv" class="container mx-auto">
            	<div class="row justify-content-center mb-3">
                	<div class="col-xl-4 col-md-6 my-auto">
                        <a href="https://nonprofitsoftwarecorp.org/products-services/hospitality-housekeeper-software/" target="_blank"><img width="250" alt='Hospitality HouseKeeper Logo' src="images/hhkLogo.png"></a>
                    </div>
                    <div class="col-md-6 my-auto">
                    	<div>
                        	<?php echo $siteName; ?>
                        </div>
                    </div>
                </div>
               	<?php echo $disclaimer; ?>
                <?php echo Login::trainingMsg(); ?>
                <div class="row justify-content-center">
					<div class="col-xl-4 col-md-6">
						<div class="ui-widget center">
							<div class="ui-widget-header ui-corner-top p-1">Sites</div>
							<div class="ui-widget-content ui-corner-bottom p-3 smaller">
								<ul class="list-style-none">
									<li><a href="<?php echo $secureComp->getSiteURL() . 'admin'; ?>" class="ui-button ui-corner-all"><?php echo $icons['a']; ?>Administration Site</a></li>
                                	<li class="mt-3"><a href="<?php echo $secureComp->getSiteURL() . 'house'; ?>" class="ui-button ui-corner-all"><?php echo $icons['h']; ?>Guest Tracking</a></li>
                                	<?php if(SysConfig::getKeyValue($dbh, 'sys_config', 'volunteers')){ ?>
        	                        	<li class="mt-3"><a href="<?php echo $secureComp->getSiteURL() . 'volunteer'; ?>" class="ui-button ui-corner-all"><?php echo $icons['v']; ?>Volunteers' Site</a></li>
                                	<?php } ?>
								</ul>
							</div>
						</div>
                    </div>
                    <div class="d-none d-md-block col-md-6">
						<?php echo $announcementWidget . $row2; ?>
					</div>
                </div>
                <?php echo $footerMkup; ?>
        	</div>
        </div>
    </body>
</html>
