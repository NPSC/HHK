<?php
/**
 * Index.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
include ("homeIncludes.php");
require (SEC . 'UserClass.php');
require(SEC . 'ChallengeGenerator.php');
require(SEC . 'Login.php');

// get session instance
$uS = Session::getInstance();


// Logout command?
if (isset($_GET["log"])) {

    $log = filter_var($_GET["log"], FILTER_SANITIZE_STRING);

    if ($log == "lo") {

        $uS->destroy(TRUE);
        header('location:index.php');
        exit();
    }
}


// Access the login object, set session vars,
try {

    $login = new Login();
    $config = $login->initializeSession(ciCFG_FILE);

} catch (PDOException $pex) {
    exit ("<h3>Database Error.  </h3>");

} catch (Exception $ex) {
    exit ("<h3>" . $ex->getMessage());
}


// define db connection obj
$dbh = initPDO(TRUE);


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
$disclaimer = $config->get("site", "Disclaimer", "");

if ($uS->mode != Mode::Live) {
    $disclaimer = 'Welcome to this demonstration version of Hospitality HouseKeeper! Do NOT use real guest or patient names.  This demonstration web site is not HIPAA complient and not intended to be used for storing Protected Health Information.';
}


$volSiteURL = $config->get("site", "Volunteer_URL", "");
$tutorialSiteURL = $config->getString('site', 'Tutorial_URL'. '');
$trainingSiteURL = $config->getString('site', 'Training_URL'. '');
$build = 'Build:' . $config->getString('code', 'Version', '*') . '.' . $config->getString('code', 'Build', '*');

$pageTitle = $uS->siteName;

$icons = array();

foreach ($uS->siteList as $r) {

    if ($r["Site_Code"] != "r") {
        $icons[$r["Site_Code"]] = "<span class='" . $r["Class"] . "' style='float: left; margin-left:.3em;margin-top:2px;'></span>";
    }
}

$siteName = HTMLContainer::generateMarkup('h3', 'Guest Tracking Site' . $icons[$page->get_Site_Code()]);

$volLinkMkup = '';
$houseLinkMkup = '';
$tutorialMkup = '';


if ($volSiteURL != '' && isset($icons['v'])) {
    $volLinkMkup = HTMLContainer::generateMarkup('div',
        HTMLContainer::generateMarkup('p',
                'I want to go to the ' . $icons['v'] . HTMLContainer::generateMarkup('a', 'Volunteer web site', array('href'=>$volSiteURL))), array('style'=>'margin-top:40px;'));
}

if ($tutorialSiteURL != '') {
    $tutorialMkup .= HTMLContainer::generateMarkup('div',
             HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('a', 'You Tube Video Tutorials', array('href'=>$tutorialSiteURL)), array('style'=>'margin-left:15px;')), array('style'=>'margin-top:25px;'));
}

if ($trainingSiteURL != '') {
    $tutorialMkup .= HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('a', 'HHK Playground', array('href'=>$trainingSiteURL)), array('style'=>'margin-left:15px;')), array('style'=>'margin-top:25px;'));
}

$copyYear = date('Y');

$loginMkup = $login->loginForm();

$cspURL = $uS->siteList[$page->get_Site_Code()]['HTTP_Host'];

header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src $cspURL; style-src $cspURL 'unsafe-inline';"); // FF 23+ Chrome 25+ Safari 7+ Opera 19+
header("X-Content-Security-Policy: default-src $cspURL; style-src $cspURL 'unsafe-inline';"); // IE 10+

$isHttps = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off';
if ($isHttps)
{
  header('Strict-Transport-Security: max-age=31536000'); // FF 4 Chrome 4.0.211 Opera 12
}

?>
<!DOCTYPE HTML>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $uS->siteName; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <script type="text/javascript" src="<?php echo MD5_JS; ?>"></script>
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="../js/login.js"></script>
    </head>
    <body <?php if ($uS->testVersion) { echo "class='testbody'"; } ?>>
        <div id="page">
            <div class='pageSpacer'>
                <h2 style="color:white"><?php echo $uS->siteName; ?></h2>
            </div>
            <div style="float:right;font-size: .6em;margin-right:5px;"><?php echo $build; ?></div>
            <div id="contentDiv">
                    <a href="http://hospitalityhousekeeper.org/" target="blank"><img width="250" alt='Hospitality HouseKeeper Logo' src="../images/hhkLogo.png"></a>
                    <div style="clear:left; margin-bottom: 20px;"></div>
                <div id="formlogin" style="float:left;" >
                    <div><?php echo $siteName; ?>
                        <p style="margin-left:6px; width: 50%;"><?php echo $disclaimer ?></p>
                    </div>
                    <?php echo $loginMkup;  echo $volLinkMkup;  echo $tutorialMkup;?>
                </div>
                <div style="clear:left;"></div>
                <div style="margin-top: 100px;width:500px;">
                    <hr>
                    <div><a href ="http://nonprofitsoftwarecorp.org" ><div class="nplogo"></div></a></div>
                    <div style="float:right;font-size: smaller; margin-top:5px;margin-right:.3em;">&copy; <?php echo $copyYear; ?> Non Profit Software</div>
                </div>
            </div>
        </div>
    </body>
</html>

