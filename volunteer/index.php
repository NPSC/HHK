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


// get session instance
$ssn = Session::getInstance();


// disclamer
$disclaimer = $config->get("site", "Disclaimer", "");
$logoLink = $config->getString("site", "Public_URL", "");


$pageTitle = $ssn->siteName;

// define db connection obj
$dbh = initPDO();

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


// Force user to verify personal info on first login.
if (checkHijack($ssn)) {
    $pge = "VolNameEdit.php";
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

$copyYear = date('Y');

$loginMkup = $login->loginForm($uname);
?>
<!DOCTYPE HTML>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link href="css/publicStyle.css" rel="stylesheet" type="text/css" />
        <script type="text/javascript" src="<?php echo $ssn->resourceURL; ?>js/md5-min.js"></script>
        <link rel="icon" type="image/png" href="../images/hhkLogo.png" />
    </head>
    <body onload="javascript:loadBody();">
        <div id="wrapper">
            <a href="<?php echo $logoLink; ?>"><div id="logoLT"></div></a>
            <div style="clear:both;"></div>
            <div id="formlogin">
                <p style="margin:16px; color: #4E4E4E;"><?php echo $disclaimer ?></p>
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
    </body>
</html>

