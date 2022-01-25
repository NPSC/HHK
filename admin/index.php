<?php

use HHK\sec\{Session, Login, ScriptAuthClass, SecurityComponent};
use HHK\Exception\{InvalidArgumentException, RuntimeException};
use HHK\SysConst\{Mode, CodeVersion};
use HHK\HTMLControls\{HTMLContainer};
use HHK\sec\SysConfig;
use HHK\sec\SAML;

/**
 * index.php  (admin)
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");

// get session instance
$uS = Session::getInstance();

// Logout command?
if (isset($_GET["log"])) {
    $log = filter_var($_GET["log"], FILTER_SANITIZE_STRING);
    if ($log == "lo") {

        $uS->destroy(true);
        header('location:index.php');
        exit();
    }
}


try {

    $login = new Login();
    $dbh = $login->initHhkSession(ciCFG_FILE);

} catch (InvalidArgumentException $pex) {
    exit ("<h3>Database Access Error.   <a href='index.php'>Continue</a></h3>");

} catch (Exception $ex) {
    echo ("<h3>Server Error</h3>" . $ex->getMessage());
}


// Load the page information
try {
    $page = new ScriptAuthClass($dbh);
} catch (Exception $ex) {
    $uS->destroy(true);
    exit("Error accessing web page data table: " . $ex->getMessage());
}


if (isset($_POST['txtUname'])) {
    $events = $login->checkPost($dbh, $_POST, $page->get_Default_Page());
    echo json_encode($events);
    exit();
}

// disclamer
$disclaimer = '';
if ($uS->mode != Mode::Live) {
    $disclaimer = 'Welcome to this demonstration version of Hospitality HouseKeeper! Do NOT use real guest or patient names.  This demonstration web site is not HIPAA compliant and not intended to be used for storing Protected Health Information.';
}

$build = 'Build:' . CodeVersion::VERSION . '.' . CodeVersion::BUILD;

$icons = array();

foreach ($uS->siteList as $r) {

    if ($r["Site_Code"] != "r") {
        $icons[$r["Site_Code"]] = "<span class='" . $r["Class"] . "' style='margin-right:.3em;'></span>";
    }
}

$siteName = HTMLContainer::generateMarkup('h3', $icons[$page->get_Site_Code()] . 'Administration Site', array("style"=>"text-align:center;"));

$loginMkup = $login->newLoginForm();
$samlMkup = SAML::getIdpMarkup($dbh);
$announcementWidget = $login->rssWidget("Welcome", "https://nonprofitsoftwarecorp.org/npsc-news/feed", 3);
$linkMkup = $login->getLinksMarkup($uS, $dbh);
$footerMkup = $login->getFooterMarkup();

$cspURL = $page->getHostName();

header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src $cspURL; style-src $cspURL 'unsafe-inline';"); // FF 23+ Chrome 25+ Safari 7+ Opera 19+
header("X-Content-Security-Policy: default-src $cspURL; style-src $cspURL 'unsafe-inline';"); // IE 10+

if (SecurityComponent::isHTTPS()) {
    header('Strict-Transport-Security: max-age=31536000'); // FF 4 Chrome 4.0.211 Opera 12
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $uS->siteName; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo GRID_CSS; ?>
        <?php echo FAVICON; ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo LOGIN_JS; ?>"></script>
        <style>
            .ui-state-error {
                border: 1px solid #cd0a0a;
                background: #fef1ec url("images/ui-bg_glass_95_fef1ec_1x400.png") 50% 50% repeat-x;
                color: #cd0a0a;
            }
        </style>
    </head>
    <body <?php if ($uS->testVersion) {echo "class='testbody'";} ?> >
        <div id="page">
            <div class='pageHeader'>
                <h2 class="px-3 py-2"><?php echo $uS->siteName; ?></h2>
            </div>
            <div class="build"><?php echo $build; ?></div>
            <div id="contentDiv" class="container mx-auto">
                <div style="text-align:center;" class="mb-3">
                    <a href="https://nonprofitsoftwarecorp.org/products-services/hospitality-housekeeper-software/" target="_blank"><img width="250" alt='Hospitality HouseKeeper Logo' src="../images/hhkLogo.png"></a>
                </div>
                <?php echo $siteName; ?>
                <div class="row mb-3">
                    <div class="col-12">
                        <?php echo $disclaimer . $login->IEMsg(); ?>
                    </div>
                </div>
                <div class="row justify-content-center">
					<div class="col-xl-4 col-md-6">
                        <div id="formlogin">
                            <?php echo $loginMkup; ?>
                        </div>

                        <?php echo $samlMkup . $linkMkup; ?>
                    </div>
                    <div class="d-none d-lg-block col-md-6">
						<?php echo $announcementWidget; ?>
					</div>
                </div>
                <?php echo $footerMkup; ?>
        	</div>
        </div>
    </body>
</html>

