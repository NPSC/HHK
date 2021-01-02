<?php
use HHK\sec\Session;
use HHK\sec\Login;
use HHK\Exception\RuntimeException;
use HHK\sec\ScriptAuthClass;
use HHK\HTMLControls\HTMLContainer;
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
require ('VolIncludes.php');
//require(SEC . 'Login.php');


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

$uname = '';
if (isset($_GET['u'])) {
    $uname = filter_var($_GET['u'], FILTER_SANITIZE_STRING);
}


try {

    $login = new Login();
    $dbh = $login->initHhkSession(ciCFG_FILE);
} catch (PDOException $pex) {
    exit("<h3>Database Error.  </h3>");
} catch (Exception $ex) {
    echo ("<h3>Server Error</h3>" . $ex->getMessage());
}


// define db connection obj
// try {
//     $dbh = initPDO(TRUE);
// } catch (RuntimeException $hex) {
//     exit('<h3>' . $hex->getMessage() . '; <a href="index.php">Continue</a></h3>');
// }


// Load the page information
try {
    $page = new ScriptAuthClass($dbh);
} catch (Exception $ex) {
    $uS->destroy(true);
    exit("<h2>The HHK Volunteer Site is not enabled.</h2>");
}


if (isset($_POST['txtUname'])) {
    $events = $login->checkPost($dbh, $_POST, $page->get_Default_Page());
    echo json_encode($events);
    exit();
}

$icons = array();

foreach ($uS->siteList as $r) {

    if ($r["Site_Code"] != "r") {
        $icons[$r["Site_Code"]] = "<span class='" . $r["Class"] . "' style='float: left; margin-left:.3em;margin-top:2px;'></span>";
    }
}

$siteName = HTMLContainer::generateMarkup('h3', 'Volunteer Site' . $icons[$page->get_Site_Code()]);

// disclamer
$disclaimer = $uS->Disclaimer;
$logoLink = "";
$copyYear = date('Y');

$loginMkup = $login->loginForm($uname);

$cspURL = $page->getHostName();

header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src $cspURL; style-src $cspURL 'unsafe-inline';"); // FF 23+ Chrome 25+ Safari 7+ Opera 19+
header("X-Content-Security-Policy: default-src $cspURL; style-src $cspURL 'unsafe-inline';"); // IE 10+

if (SecurityComponent::isHTTPS()) {
    header('Strict-Transport-Security: max-age=31536000'); // FF 4 Chrome 4.0.211 Opera 12
}

?>
<!DOCTYPE HTML>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $uS->siteName; ?></title>
        <?php echo PUBLIC_CSS; ?>
        <?php echo JQ_UI_CSS; ?>
        <?php echo FAVICON; ?>

        <script type="text/javascript" src="<?php echo MD5_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo LOGIN_JS; ?>"></script>
    </head>
    <body >
        <div id="page">
            <div id="content">
            <a href="<?php echo $logoLink; ?>"><span id="logoLT"></span></a>
            <div style="clear:both;"></div>
            <div id="formlogin"  style="float:left;">
                    <div style="margin-top:10px;"><?php echo $siteName; ?>
                        <p style="margin-left:6px; width: 50%;"><?php echo $disclaimer ?></p>
                    </div>
                <?php echo $loginMkup; ?>
                <div style="padding-top: 25px;">
                    <table>
                        <tr>
                            <td><a href ="WebRegister.php" ><span style="font-size: 1em;color: #4E4E4E;">Register for access to Volunteer Pages</span></a></td>
                        </tr>
                    </table>
                </div>
            </div>
                <div style="clear:left;"></div>
                <div style="margin-top: 50px;width:600px;">
                    <hr>
                    <div><a href ="http://nonprofitsoftwarecorp.org" ><span class="nplogo"></span></a></div>
                    <div style="float:right;font-size: smaller; margin-top:5px;margin-right:.3em;">&copy; <?php echo $copyYear; ?> Non Profit Software Corporation</div>
                </div>
            </div>
        </div>
    </body>
</html>

