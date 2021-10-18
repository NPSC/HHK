<?php

use HHK\sec\{Session, Login, ScriptAuthClass, SecurityComponent};
use HHK\Exception\{InvalidArgumentException, RuntimeException};
use HHK\SysConst\{Mode, CodeVersion};
use HHK\HTMLControls\{HTMLContainer};
use HHK\sec\SysConfig;

/**
 * index.php  (admin)
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");
require(SEC . 'Login.php');
require(THIRD_PARTY . 'GoogleAuthenticator.php');

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
if ($uS->mode != Mode::Live) {
    $disclaimer = 'Welcome to this demonstration version of Hospitality HouseKeeper! Do NOT use real guest or patient names.  This demonstration web site is not HIPAA compliant and not intended to be used for storing Protected Health Information.';
} else {
    $disclaimer = '';
}

$tutorialSiteURL = SysConfig::getKeyValue($dbh, 'sys_config', 'Tutorial_URL');
$trainingSiteURL = SysConfig::getKeyValue($dbh, 'sys_config', 'Training_URL');
$build = 'Build:' . CodeVersion::VERSION . '.' . CodeVersion::BUILD;

$icons = array();

foreach ($uS->siteList as $r) {

    if ($r["Site_Code"] != "r") {
        $icons[$r["Site_Code"]] = "<span class='" . $r["Class"] . "' style='float: left; margin-left:.3em;margin-top:2px;'></span>";
    }
}

$siteName = HTMLContainer::generateMarkup('h3', 'Administration Site' . $icons[$page->get_Site_Code()]);
$extLinkIcon = "<span class='ui-icon ui-icon-extlink' style='float: right; margin-right:.3em;margin-top:2px;'></span>";
$linkMkup = '';


if ($tutorialSiteURL != '') {
    $linkMkup .=
            HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('a', 'You Tube Videos' . $extLinkIcon, array('href'=>$tutorialSiteURL, 'target'=>'_blank')), array('style'=>"margin-top:25px;float:left;"));
}

if ($trainingSiteURL != '') {
    $linkMkup .=
            HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('a', 'HHK Playground' . $extLinkIcon, array('href'=>$trainingSiteURL, 'target'=>'_blank')), array('style'=>"margin-top:25px;clear:left;float:left"));
}

$copyYear = date('Y');

$loginMkup = $login->loginForm();
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
            <div class='pageSpacer'>
                <h2 style="color:white;"><?php echo $uS->siteName; ?></h2>
            </div>
            <div style="float:right;font-size: .6em;margin-right:5px;"><?php echo $build; ?></div>
            <div id="content">
                <a href="https://nonprofitsoftwarecorp.org/products-services/hospitality-housekeeper-software/" target="blank"><img width="250" alt='Hospitality HouseKeeper Logo' src="../images/hhkLogo.png"></a>
                <div style="clear:left; margin-bottom: 20px;"></div>
                <div id="formlogin" style="float:left;" >
                    <div><?php echo $siteName; ?>
                        <div style="margin-left:6px; width: 65%;">
                        	<?php echo $disclaimer . $login->IEMsg(); ?>
                        </div>
                    </div>
                    <?php echo $loginMkup . $linkMkup; ?></div>
                </div>

                <div style="clear:left;"></div>
                <div style="margin-top: 70px;width:500px;">
                    <hr>
                    <div><a href ="https://nonprofitsoftwarecorp.org" ><span class="nplogo"></span></a></div>
                    <div style="float:right;font-size: smaller; margin-top:5px;margin-right:.3em;">&copy; <?php echo $copyYear; ?> Non Profit Software Corporation</div>
                </div>
        </div>
    </body>
</html>

