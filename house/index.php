<?php
use HHK\sec\Session;
use HHK\sec\Login;
use HHK\sec\Labels;
use HHK\Exception\InvalidArgumentException;
use HHK\sec\ScriptAuthClass;
use HHK\SysConst\Mode;
use HHK\SysConst\CodeVersion;
use HHK\HTMLControls\HTMLContainer;
use HHK\sec\SecurityComponent;
use HHK\sec\SAML;

/**
 * Index.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
include ("homeIncludes.php");

// get session instance
$uS = Session::getInstance();

//assume logout
//$uS->destroy(TRUE);

// Logout command?
$log = filter_input(INPUT_GET, 'log', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if ($log == "lo") {
    $uS->destroy(true);
    header('location:index.php');
    exit();
}

// Access the login object, set session vars,
try {

    $login = new Login();
    $dbh = $login->initHhkSession(CONF_PATH, ciCFG_FILE);

} catch (InvalidArgumentException $pex) {
    exit ("<h3>Database Access Error.   <a href='index.php'>Continue</a></h3>");

} catch (Exception $ex) {
    exit ("<h3>" . $ex->getMessage());
}


// Load the page information
try {
    $page = new ScriptAuthClass($dbh);
} catch (Exception $ex) {
    $uS->destroy(true);
    exit('<h2>The HHK Guest Tracking Site is not enabled.</h2>');
}

// Login hook
if (filter_has_var(INPUT_POST, 'txtUname')) {
    // User loged in
    $events = $login->checkPost($dbh, $_POST, $page->get_Default_Page());
    echo json_encode($events);
    exit();
}

// Get labels
$labels = Labels::getLabels();

// disclamer
$disclaimer = '';
if ($uS->mode != Mode::Live) {
    $disclaimer = HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup("div", 'Welcome to this demonstration version of Hospitality HouseKeeper! Do NOT use real guest or patient names.  This demonstration web site is not HIPAA compliant and not intended to be used for storing Protected Health Information.', array("class"=>"col-xl-10")), array("class"=>"row justify-content-center mb-3"));
}

$build = 'Build:' . CodeVersion::VERSION . '.' . CodeVersion::BUILD;

$icons = array();

foreach ($uS->siteList as $r) {

    if ($r["Site_Code"] != "r") {
        $icons[$r["Site_Code"]] = "<span class='" . $r["Class"] . " mr-1'></span>";
    }
}

$siteName = HTMLContainer::generateMarkup('h2', $icons[$page->get_Site_Code()] . $labels->getString('MemberType', 'guest', 'Guest').' Tracking Site', array("class"=>"center"));

$loginMkup = $login->loginForm();
$samlMkup = SAML::getIdpMarkup($dbh);

$announcementWidget = $login->welcomeWidget("Tip of the Week");
$linkMkup = $login->getLinksMarkup($uS, $dbh);
$newsletterMkup = $login->getNewsletterMarkup();
$row2 = HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup('div', $linkMkup, array("class"=>"col-lg-7 mb-3")) . HTMLContainer::generateMarkup("div", $newsletterMkup, array("class"=>"col-lg-5")),array("class"=>"row justify-content-center mb-3"));

$footerMkup = $login->getFooterMarkup();

$cspURL = $page->getHostName() . " manage.hospitalityhousekeeper.net nonprofitsoftwarecorp.org";

header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src $cspURL; script-src $cspURL; style-src $cspURL; frame-src nonprofitsoftwarecorp.us18.list-manage.com 'unsafe-inline';"); // FF 23+ Chrome 25+ Safari 7+ Opera 19+
header("X-Content-Security-Policy: default-src $cspURL; script-src $cspURL; style-src $cspURL; frame-src nonprofitsoftwarecorp.us18.list-manage.com 'unsafe-inline';"); // IE 10+

if (SecurityComponent::isHTTPS()) {
    header('Strict-Transport-Security: max-age=31536000'); // FF 4 Chrome 4.0.211 Opera 12
}

?>
<!DOCTYPE HTML>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $uS->siteName; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo ROOT_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo GRID_CSS; ?>
        <?php echo FAVICON; ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo LOGIN_JS; ?>"></script>

    </head>
    <body <?php if ($uS->testVersion) { echo "class='testbody'"; } ?>>
        <div id="page">
            <div class='pageHeader'>
                <h2 class="px-3 py-2"><?php echo $uS->siteName; ?></h2>
            </div>
            <div class="build"><?php echo $build; ?></div>
            <div id="contentDiv" class="container mx-auto">
            	<div class="row justify-content-center mb-3">
                    <div class="col-xl-4 col-md-6 my-auto">
                        <a href="https://nonprofitsoftwarecorp.org/products-services/hospitality-housekeeper-software/" target="_blank"><img width="250" alt='Hospitality HouseKeeper Logo' src="../images/hhkLogo.png"></a>
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
                        <?php echo $samlMkup . $loginMkup; ?>
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

