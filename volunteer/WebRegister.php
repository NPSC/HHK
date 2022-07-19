<?php
use HHK\sec\SysConfig;
use HHK\sec\Login;
use HHK\sec\Session;
use HHK\Exception\RuntimeException;
use HHK\sec\ScriptAuthClass;
use HHK\AlertControl\AlertMessage;
use Phelium\Component\reCAPTCHA;
use HHK\fbUserClass;
use HHK\sec\SecurityComponent;

/**
 * WebRegister.php
 *
 * @category  Volunteer
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
require('VolIncludes.php');

function processGuest(\PDO $dbh, $username, fbUserClass $fbc) {

    // is this username taken?
    $query = "select v.Id, v.Fullname, v.Name_last, v.Name_First, v.Preferred_Phone, v.Preferred_Email, v.MemberStatus, ifnull(w.Status, '')
        from vmember_listing v join w_users w on v.Id = w.idName where LOWER(w.User_Name) = :uname;";
    $stmt = $dbh->prepare($query);
    $stmt->execute(array(':uname' => $username));
    $rows = $stmt->fetchAll();

    if (count($rows) > 0) {
        $r = $rows[0];

        // have this user name already.  Same Person?
        if (strtolower($fbc->get_em()) == strtolower($r["Preferred_Email"])) {
            return array("warning" => "Our records indicate that you are already registered.", "pun" => $username);

        } else if (strtolower($fbc->get_ln()) == strtolower($r["Name_Last"]) && (strtolower($fbc->get_fn()) == strtolower($r["Name_First"]) || strtolower($fbc->get_fn()) == strtolower($r["Name_Nickname"]))) {

            return array("warning" => "Our records indicate that you may already be registered.  If not, try a different User Name.", "pun" => $username);
        } else {
            // duplicate
            return array("warning" => "That User Name is already taken.  Choose another.", "pun" => $username);
        }
    }


    if ($fbc->get_fbid() != "") {
        $whereStr = " fb_id='" . $fbc->get_fbid() . "'";
        $events = $fbc->saveToDB($dbh, $whereStr);

        if (isset($events["success"])) {
            $mail = prepareEmail();

            $mail->From = SysConfig::getKeyValue($dbh, 'sys_config', 'ReturnAddress');
            $mail->addReplyTo(SysConfig::getKeyValue($dbh, 'sys_config', 'ReturnAddress'));
            $mail->FromName = SysConfig::getKeyValue($dbh, 'sys_config', 'siteName');
            $mail->addAddress($fbc->get_em());     // Add a recipient
            $mail->addBCC(SysConfig::getKeyValue($dbh, 'sys_config', 'ReturnAddress'));
            $mail->isHTML(true);

            $mail->Subject = SysConfig::getKeyValue($dbh, 'sys_config', 'RegSubj');

            if ($fbc->get_ph() != "") {
                $phon ='<tr><th class="tdlabel">Phone</th><td class="tdBox"><span>' . $fbc->get_ph().'</span></td></tr>';
            } else {
                $phon = '';
            }

            $mail->msgHTML('
<html>
<head>
<style type="text/css">
h4 {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 16px;
    font-weight: bold;
    color: #FB883C;
    margin: 0px;
    padding: 0px;
}
.tdBox {
    border: 1px solid #7E8771;
}
TH
{
    padding: 3px 7px;
}
TD
{
     padding: 3px 7px;
    vertical-align: top;
}
table
{
    border-collapse:collapse;
}
.tdBox {
    border: 1px solid #D4CCB0;
    vertical-align: top;
}
.tdlabel {
    text-align: right;
    font-size: .8em;
}

</style>
</head>
    <body>
      <h4>Thank you ' . $fbc->get_fn() . ' ' . $fbc->get_ln() . ' for signing up to the ' . SysConfig::getKeyValue($dbh, 'sys_config', 'siteName') . ' Volunteer Website</h4>
       <p>The ' . SysConfig::getKeyValue($dbh, 'sys_config', 'siteName') . ' will contact you when you are cleared to log in to the Volunteer Website.</p>
       <div>
            <table>
            <caption>Volunteer Information</caption>
                '.$phon.'
                <tr>
                    <th class="tdlabel tdBox">Email</th><td class="tdBox"><span>'.$fbc->get_em().'</span></td>
                </tr>
                <tr>
                    <th class="tdlabel tdBox">User Name</th><td class="tdBox"><span>'.$username.'</span></td>
                </tr>
            </table>
       </div>
    </body>
</html>');



            if($mail->send() === FALSE) {
            	return array("error" => "Your registration succeeded, but the notification Email failed.  Please contact the " . SysConfig::getKeyValue($dbh, 'sys_config', 'siteName') . ".");
            }

        } else {
            return $events;
        }
    } else {
        $events = array("error" => "Bad User Name");
    }
    return $events;
}


try {

    $login = new Login();
    $login->initHhkSession(ciCFG_FILE);

} catch (PDOException $pex) {
    exit("<h3>Database Error.  </h3>");
} catch (Exception $ex) {
    exit ("<h3>Server Error</h3>" . $ex->getMessage());
}


// get session instance
$ssn = Session::getInstance();

if ($ssn->logged && isset($ssn->uid)) {
    header( "location:index.php");
    exit();
}

$pageTitle = $ssn->siteName;
$houseTitle = $ssn->siteName;
$logoLink = '';

// define db connection obj
// define db connection obj
try {
    $dbh = initPDO(TRUE);
} catch (RuntimeException $hex) {
    exit('<h3>' . $hex->getMessage() . '; <a href="index.php">Continue</a></h3>');
}

// Load the page information
try {

    $page = new ScriptAuthClass($dbh);

} catch (PDOException $ex) {

    $ssn->destroy(TRUE);
    exit("Error - Database problem accessing page.");
}

$donAlert = new AlertMessage("donateResponseContainer");
$donAlert->set_DisplayAttr("none");
$donAlert->set_Context(AlertMessage::Alert);
$donAlert->set_iconId("donateResponseIcon");
$donAlert->set_styleId("donateResponse");
$donAlert->set_txtSpanId("donResultMessage");
$donAlert->set_Text("working");

$completeDisplay = 'display:none;';
$formDisplay = 'display:block;';
$errMessage = '';

$siteKey = SysConfig::getKeyValue($dbh, 'sys_config', 'HHK_Site_Key');  //$config->getString('recaptcha', 'Site_Key', '') == '' ? $config->getString('recaptcha', 'HHK_Site_Key', '') : $config->getString('recaptcha', 'Site_Key');
$secret = decryptMessage(SysConfig::getKeyValue($dbh, 'sys_config', 'HHK_Secret_Key')); //$config->getString('recaptcha', 'Secret_Key', '') == '' ? $config->getString('recaptcha', 'HHK_Secret_Key', '') : $config->getString('recaptcha', 'Secret_Key');

$reCAPTCHA = new reCAPTCHA($siteKey, $secret);

if (isset($_POST['g-recaptcha-response'])) {

    if ($reCAPTCHA->isValid($_POST['g-recaptcha-response'])) {

        $data = $_POST;
        //encyrpt PW
        if(isset($ssn->sitePepper) && $ssn->sitePepper != ''){
            $data['pw'] = password_hash($_POST['pw'] . $ssn->sitePepper, PASSWORD_ARGON2ID);
        }else{
            $data['pw'] = md5($_POST['pw']);
        }

        $web = new fbUserClass("");
        $web->loadFromArray($data);

        if ($web->get_pifhUsername() != "") {

            $username = $web->get_pifhUsername();
            // set the fbid as the username.  It won't conflict with the facebook id's, i hope.
            $web->set_fbid($username);

            // Set access code...
            $web->set_accessCode("web");

            // Check tsable fbx - did we already register, are we waiting?
            $r = $web->selectRow($dbh, "  fb_id = " . $dbh->quote($username) . " and Access_Code = 'web' ");

            if (!is_null($r)) {

                switch ($r["Status"]) {
                    case 'a':
                        $msg = "This User Name is already taken, or you are already registered.";
                        break;
                    case 'w':
                        $msg = "This User Name is already taken, or you are waiting for registration approval.";
                        break;
                    case 'd':
                        $msg = "This User Name is disabled.";
                        break;
                    case 'x':

                    default:
                        $msg = "Unknown Error";
                }

                $errMessage = 'Registration Failed: ' . $msg;

            } else {
                $events = processGuest($dbh, $username, $web);

                if (isset($events['success'])) {
                    $completeDisplay = 'display:block;';
                    $formDisplay = 'display:none;';
                }
            }

        } else {
            $errMessage = 'Registration Failed: ' . "Missing User Name.";

        }
    } else {
        $errMessage = 'Registration Failed: ' . "reCaptcha error.";
    }
}


$getDonReplyMessage = $donAlert->createMarkup();

$cspURL = $page->getHostName();

header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src $cspURL https://www.google.com/; style-src $cspURL https://www.gstatic.com/ 'unsafe-inline'; script-src $cspURL https://www.google.com/ https://www.gstatic.com/;"); // FF 23+ Chrome 25+ Safari 7+ Opera 19+
header("X-Content-Security-Policy: default-src $cspURL https://www.google.com/; style-src $cspURL https://www.gstatic.com/ 'unsafe-inline'; script-src $cspURL https://www.google.com/ https://www.gstatic.com/;"); // IE 10+

if (SecurityComponent::isHTTPS()) {
	header('Strict-Transport-Security: max-age=31536000'); // FF 4 Chrome 4.0.211 Opera 12
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo PUBLIC_CSS; ?>
        <?php echo FAVICON; ?>

        <?php echo $reCAPTCHA->getScript(); ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo LOGIN_JS; ?>"></script>
	<script type="text/javascript" src="js/webReg.js"></script>
    </head>
    <body>
        <div id="wrapper">
            <a href="<?php echo $logoLink; ?>"><span id="logoLT"></span></a>
            <div style="clear:both;"></div>
            <div id="cancelDiv" class="dispNone">
                <h3>Registration Canceled.  <a href="index.php">Back to Login Page</a></h3>
            </div>
            <div id="regCompleteDiv" style='<?php echo $completeDisplay; ?>' >
                <h1  style="padding:10px 10px 10px 150px;">Registration Complete</h1>
                <p  style="padding:10px;">The <?php echo $houseTitle; ?> must approve you.  An email has been sent. Please wait until they contact you.</p>
            </div>
            <div id="regFormDiv" style="padding:0 25px 25px; font-size: .9em;<?php echo $formDisplay; ?>" class="ui-widget ui-widget-content ui-corner-all">
                <form action='WebRegister.php' Method='POST' id='form1'>
                <h1>Register for On-Line Access</h1>
                <span style="font-size:.8em;">* Required</span>
                <table style="margin-bottom:10px;">
                    <thead>
                        <tr>
                            <th>First Name</th>
                            <th>Last Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>*<input id="txtFirstName" name='fn' title="Enter your first name here" type="text" value="" class='hhk-txtInput' /></td>
                            <td>*<input id="txtLastName" name='ln' title="Enter your Last name here" type="text" value="" class='hhk-txtInput' /></td>
                        </tr>
                    </tbody>
                </table>
                <table style="margin-bottom:10px;">
                    <thead>
                        <tr>
                            <th>Phone</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input name='ph' id='txtPhone' type='text'  size='20' value="" class='hhk-txtInput' /></td>
                            <td>*<input name='em' id='txtEmail' type='text' size='25' value="" class='hhk-txtInput' /></td>
                        </tr>
                    </tbody>
                </table>
                <table style="margin-bottom:10px;">
                    <thead>
                        <tr>
                            <th title="Pick a user name with at least 6 alphanumeric characters">User Name</th>
                            <th title="Choose a password with at least 8 characters">Password</th>
                            <th title="Enter your new password again">Password Again</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>*<input title="Pick a user name with at least 6 alphanumeric characters"  id="txtPun" name='pun' type="text" value="" size='15' class='hhk-txtInput' /></td>
                            <td>*<input title="Choose a password with at least 8 characters" id='txtPW' type='password' size='15' value="" class='hhk-txtInput' />
                                <input type='hidden' id='pwHdn' name='pw' value=''/></td>
                            <td>*<input title="Enter your new password again" id='txtPW2' type='password' size='15' value="" class='hhk-txtInput' /></td>
                        </tr>
                    </tbody>
                </table>
                <div class="validateTips"><?php echo $getDonReplyMessage; ?></div>
                <table style="width:100%">
                    <tr>
                        <td>
                            <?php echo $reCAPTCHA->getHtml(); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align:right; ">
                            <input type='button' value='Register' id="btnReg" style="float:right;"/>
                            <input type='button' id="btnCancel" value='Cancel'/>
                        </td>
                    </tr>
                    <tr>
                        <td><p id='returnError' class='ui-state-highlight' style='font-size:1.2em;'><?php echo $errMessage; ?></p></td>
                    </tr>
                </table>
                </form>
            </div>
        </div>
    </body>
</html>

