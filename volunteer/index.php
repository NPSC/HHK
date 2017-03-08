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
require ('VolIncludes.php');
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

$uname = '';
if (isset($_GET['u'])) {
    $uname = filter_var($_GET['u'], FILTER_SANITIZE_STRING);
}


try {

    $login = new Login();
    $config = $login->initializeSession(ciCFG_FILE);
} catch (PDOException $pex) {
    exit("<h3>Database Error.  </h3>");
} catch (Exception $ex) {
    echo ("<h3>Server Error</h3>" . $ex->getMessage());
}


// define db connection obj
$dbh = initPDO();

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

$icons = array();

foreach ($uS->siteList as $r) {

    if ($r["Site_Code"] != "r") {
        $icons[$r["Site_Code"]] = "<span class='" . $r["Class"] . "' style='float: left; margin-left:.3em;margin-top:2px;'></span>";
    }
}

$siteName = HTMLContainer::generateMarkup('h3', 'Volunteer Site' . $icons[$page->get_Site_Code()]);

// disclamer
$disclaimer = $config->get("site", "Disclaimer", "");
$logoLink = $config->getString("site", "Public_URL", "");

$pageTitle = $uS->siteName;

$copyYear = date('Y');

$loginMkup = $login->loginForm($uname);

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
        <title><?php echo $pageTitle; ?></title>
        <link href="css/publicStyle.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />

        <script type="text/javascript" src="../js/md5-min.js"></script>
        <script type="text/javascript" src="<?php echo $uS->resourceURL; ?><?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $uS->resourceURL; ?><?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $uS->resourceURL; ?>js/login.js"></script>
        <link rel="icon" type="image/png" href="../images/hhkLogo.png" />
    </head>
    <body >
        <div id="page">
            <div id="content">
            <a href="<?php echo $logoLink; ?>"><div id="logoLT"></div></a>
            <div style="clear:both;"></div>
            <div id="formlogin"  style="float:left;">
                    <div style="margin-top:10px;"><?php echo $siteName; ?>
                        <p style="margin-left:6px; width: 50%;"><?php echo $disclaimer ?></p>
                    </div>
                <?php echo $loginMkup; ?>
                <div style="padding-top: 25px;">
                    <table>
                        <tr>
                            <td><a href ="register_web.php" ><span style="font-size: 1em;color: #4E4E4E;">Register for access to Volunteer Pages</span></a></td>
                        </tr>
                    </table>
                </div>
            </div>
                <div style="clear:left;"></div>
                <div style="margin-top: 50px;width:600px;">
                    <hr>
                    <div><a href ="http://nonprofitsoftwarecorp.org" ><div class="nplogo"></div></a></div>
                    <div style="float:right;font-size: smaller; margin-top:5px;margin-right:.3em;">&copy; <?php echo $copyYear; ?> Non Profit Software</div>
                </div>
            </div>
        </div>
    </body>
</html>

