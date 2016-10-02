<?php
/**
 * index.php  (admin)
 *
 * @category  Site
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
require_once ("AdminIncludes.php");

require_once (SEC . 'UserClass.php');
require_once(SEC . 'ChallengeGenerator.php');
require_once(SEC . 'Login.php');

// Logout command?
if (isset($_GET["log"])) {
    $log = filter_var($_GET["log"], FILTER_SANITIZE_STRING);
    if ($log == "lo") {
        // get session instance
        $ssn = Session::getInstance();
        $ssn->destroy();
        header('location:index.php');
        exit();
    }
}


try {

    $login = new Login();
    $config = $login->initializeSession(ciCFG_FILE);

} catch (PDOException $pex) {
    exit ("<h3>Database Error.  </h3>");

} catch (Exception $ex) {
    echo ("<h3>Server Error</h3>" . $ex->getMessage());
}


// get session instance
$ssn = Session::getInstance();

//
$volSiteURL = $config->getString("site", 'Volunteer_URL', '');
$houseSiteUrl = $config->getString("site", 'House_URL', '');
$tutorialSiteURL = $config->getString('site', 'Tutorial_URL'. '');
$build = 'Build:' . $config->getString('code', 'Version', '*') . '.' . $config->getString('code', 'Build', '*');

// disclamer
$disclaimer = $config->get('site', 'Disclaimer', '');

$pageTitle = $ssn->siteName;

// define db connection obj
$dbh = initPDO(TRUE);

// Load the page information
try {

    $page = new ScriptAuthClass($dbh);

} catch (PDOException $ex) {

    $ssn->destroy();
    exit("Error - Database problem accessing page.");
}

$login->checkPost($dbh, $_POST);


// Get next page address
if (isset($_GET["xf"])) {

    $loc = urldecode($_GET["xf"]);
    $pge = filter_var($loc, FILTER_SANITIZE_STRING);
    $login->setAction($page->get_Login_Page() . "?xf=" . $pge);

} else {

    $pge = $page->get_Default_Page();
    $login->setAction($page->get_Login_Page());

}


if ($pge != "" && $pge != $page->get_Login_Page()) {

    if (isset($ssn->logged)) {
        // they are logged in.
        // check authorization to next page
        if (ComponentAuthClass::is_Authorized($pge)) {

            $dbh = null;
            header('Location: ' . $pge);
            exit();

        } else {

            $ssn->destroy();
            $login->setValidateMsg("Unauthorized for page: " . $pge);
        }
    }
} else {

    $login->setValidateMsg("Missing default page!");
}

$icons = array();

foreach ($ssn->siteList as $r) {

    if ($r["Site_Code"] != "r") {
        $icons[$r["Site_Code"]] = "<span class='" . $r["Class"] . "' style='float: left; margin-left:.3em;margin-top:2px;'></span>";
    }
}

$siteName = HTMLContainer::generateMarkup('h3', 'Administration Site' . $icons[$page->get_Site_Code()]);


$volLinkMkup = '';
$houseLinkMkup = '';
$tutorialMkup = '';

if ($volSiteURL != '' && isset($icons['v'])) {
    $volLinkMkup = HTMLContainer::generateMarkup('div',
        HTMLContainer::generateMarkup('p',
                'I want to go to the ' . $icons['v'] . HTMLContainer::generateMarkup('a', 'Volunteer web site', array('href'=>$volSiteURL))), array('style'=>'margin-top:30px;'));
}

if ($houseSiteUrl != '' && isset($icons['h'])) {

    $houseLinkMkup = HTMLContainer::generateMarkup('div',
        HTMLContainer::generateMarkup('p',
                'I want to go to the ' . $icons['h'] . HTMLContainer::generateMarkup('a', 'Guest Tracking web site', array('href'=>$houseSiteUrl))), array('style'=>'margin-top:30px;'));
}

if ($tutorialSiteURL != '') {
    $tutorialMkup = HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('h3', 'Tutorial Videos')
            . HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('a', 'You Tube Videos', array('href'=>$tutorialSiteURL)), array('style'=>'margin-left:15px;')), array('style'=>'margin-top:35px;'));
}

$copyYear = date('Y');

$loginMkup = $login->loginForm();

?>
<!DOCTYPE HTML>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />
        <link href="css/default.css" rel="stylesheet" type="text/css" />
        <script type="text/javascript" src="../js/md5-min.js"></script>
    </head>
    <body <?php if ($ssn->testVersion) {echo "class='testbody'";} ?>  onload="javascript:loadBody();">
        <div id="page">
            <div class='pageSpacer'>
                <h2 style="color:white;"><?php echo $pageTitle; ?></h2></div>
            <div id="content">
                    <a href="http://hospitalityhousekeeper.org/" target="blank"><img width="250" alt='Hospitality HouseKeeper Logo' src="../images/hhkLogo.png"></a>
                    <div style="clear:left; margin-bottom: 20px;"></div>
                <div id="formlogin" style="float:left;" >
                    <div><?php echo $siteName; ?>
                        <p style="margin-left:6px; width: 50%;"><?php echo $disclaimer ?></p>
                    </div>
                    <?php echo $loginMkup; ?><?php echo $volLinkMkup; ?><?php echo $houseLinkMkup; ?>
                    <?php echo $tutorialMkup; ?>
                </div>
            </div>
                <div style="clear:left;"></div>
                <div style="margin-top: 70px;width:500px;">
                    <hr>
                    <div><a href ="http://nonprofitsoftwarecorp.org" ><div class="nplogo"></div></a></div>
                    <div style="float:right;font-size: smaller; margin-top:5px;margin-right:.3em;">&copy; <?php echo $copyYear; ?> Non Profit Software</div>
                </div>
        </div>
    </body>
</html>

