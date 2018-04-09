<?php
/**
 * WebRegister.php
 *
 * @category  Volunteer
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
require('VolIncludes.php');
require(SEC . 'UserClass.php');
require(SEC . 'ChallengeGenerator.php');
require(SEC . 'Login.php');
require(CLASSES . 'fbUserClass.php');
require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';

require THIRD_PARTY .'reCAPTCHA.php';
use Phelium\Component\reCAPTCHA;

function processGuest(\PDO $dbh, \Config_Lite $config, $username, fbUserClass $fbc) {

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
            $mail = prepareEmail($config);

            $mail->From = $config->getString("vol_email", "ReturnAddress", "");
            $mail->addReplyTo($config->getString("vol_email", "ReturnAddress", ""));
            $mail->FromName = $config->getString('site', 'Site_Name', 'Hospitality HouseKeeper');
            $mail->addAddress($fbc->get_em());     // Add a recipient
            $mail->addBCC($config->getString("vol_email", "ReturnAddress", ""));
            $mail->isHTML(true);

            $mail->Subject = $config->get("vol_email", "RegSubj", "Volunteer Registration");

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
      <h4>Thank you ' . $fbc->get_fn() . ' ' . $fbc->get_ln() . ' for signing up to the ' . $config->getString("site", "Site_Name", "House") . ' Volunteer Website</h4>
       <p>The ' . $config->getString("site", "Site_Name", "House") . ' will contact you when you are cleared to log in to the Volunteer Website.</p>
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
                 return array("error" => "Your registration succeeded, but the notification Email failed.  Please contact the " . $config->getString("site", "Site_Name", "House") . ".");
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
    $config = $login->initializeSession(ciCFG_FILE);

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
$logoLink = $config->getString("site", "Public_URL", "");

// define db connection obj
$dbh = initPDO(TRUE);

// Load the page information
try {

    $page = new ScriptAuthClass($dbh);

} catch (PDOException $ex) {

    $ssn->destroy(TRUE);
    exit("Error - Database problem accessing page.");
}

$donAlert = new alertMessage("donateResponseContainer");
$donAlert->set_DisplayAttr("none");
$donAlert->set_Context(alertMessage::Alert);
$donAlert->set_iconId("donateResponseIcon");
$donAlert->set_styleId("donateResponse");
$donAlert->set_txtSpanId("donResultMessage");
$donAlert->set_Text("working");

$completeDisplay = 'display:none;';
$formDisplay = 'display:block;';
$errMessage = '';

$siteKey = $config->getString('recaptcha', 'Site_Key', '') == '' ? $config->getString('recaptcha', 'HHK_Site_Key', '') : $config->getString('recaptcha', 'Site_Key');
$secret = $config->getString('recaptcha', 'Secret_Key', '') == '' ? $config->getString('recaptcha', 'HHK_Secret_Key', '') : $config->getString('recaptcha', 'Secret_Key');

$reCAPTCHA = new reCAPTCHA($siteKey, $secret);

if (isset($_POST['g-recaptcha-response'])) {

    if ($reCAPTCHA->isValid($_POST['g-recaptcha-response'])) {

        $web = new fbUserClass("");
        $web->loadFromArray($_POST);

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
                $events = processGuest($dbh, $config, $username, $web);

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


?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />
        <?php echo PUBLIC_CSS; ?>
        <?php echo $reCAPTCHA->getScript(); ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="../js/md5-min.js"></script>
        <style type="text/css">
            .lblFor {
                text-align: right;
            }
            div.dispNone {
                display:none;
            }
            div.dispBlock {
                display:block;
                background-color: #FFFFFF;
                border: 3px solid #CDC8B2;
                padding: 25px;
            }
            .underNotes {
                font-size: .85em;
                font-style: italic;
                color: #4E4E4E;
            }
            input.ui-state-error {
                background:  repeat scroll 50% 50% #EFCFC2;
                color: #4C3000;
            }
        </style>
        <script type="text/javascript">
function checkStrength(pwCtrl) {
    var strongRegex = new RegExp("^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])(?=.{8,})");
    var mediumRegex = new RegExp("^(((?=.*[a-z])(?=.*[A-Z]))|((?=.*[a-z])(?=.*[0-9]))|((?=.*[A-Z])(?=.*[0-9])))(?=.{8,})");
    var rtn = true;
    if(strongRegex.test(pwCtrl.val())) {
        pwCtrl.css('background-color', 'green');
    } else if(mediumRegex.test(pwCtrl.val())) {
        pwCtrl.css('background-color', 'orange');
    } else {
        pwCtrl.css('background-color', 'red');
        rtn = false;
    }
    return rtn;
}
function setAlert(msgText) {
    msgText = msgText.replace(/^\s+|\s+$/g, "");
    var spn = document.getElementById('donResultMessage');
    if (msgText == '') {
        // hide the control
        $('#donateResponseContainer').attr("style", "display:none;");
    }
    else {
        // define the error message markup
        $('#donateResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
        $('#donateResponseContainer').attr("style", "display:block;");
        $('#donateResponseIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
        spn.innerHTML = "<strong>Warning: </strong>" + msgText;
    }
}
function updateTips(t) {
    setAlert(t);
}
function checkLength(o, n, min, max) {
    if (o.val().length > max || o.val().length < min) {
        o.addClass("ui-state-error");
        if (min == max)
            updateTips("Length of the " + n + " must be " + max + ".");
        else
            updateTips("Length of the " + n + " must be between " +
                    min + " and " + max + ".");
        return false;
    } else {
        return true;
    }
}
function checkRegexp(o, regexp, n) {
    if (!regexp.test(o.val()) && o.val() != "") {
        o.addClass("ui-state-error");

        updateTips(n);
        return false;
    } else {
        return true;
    }
}

$(document).ready(function() {
    var rexEmail = /^[A-Z0-9._%+-]+@(?:[A-Z0-9-]+\.)+[A-Z]{2,4}$/i,
        regPhone = /^(?:(?:[\+]?([\d]{1,3}(?:[ ]+|[\-.])))?[(]?([2-9][\d]{2})[\-\/)]?(?:[ ]+)?)?([2-9][0-9]{2})[\-.\/)]?(?:[ ]+)?([\d]{4})(?:(?:[ ]+|[xX]|(i:ext[\.]?)){1,2}([\d]{1,5}))?$/,
        $psw = $('#txtPW'),
        $ps2 = $('#txtPW2'),
        $phone = $('#txtPhone');


    $('#btnCancel, #btnReg').button();

    $("#btnCancel").click(function() {
        // This is a one-way trip.
        $('#cancelDiv').removeClass("dispNone").addClass("dispBlock");
        $('#regFormDiv').removeClass("dispBlock").addClass("dispNone");
    });

    $psw.change(function() {
        updateTips("");
        if (checkStrength($psw)) {
            if ($ps2.val() !== "" && $ps2.val() !== this.value) {
                updateTips("Passwords do not match");
            }
        } else {
            updateTips("A password must have at least 8 characters including upper case and lower case letters and numbers.");
        }
    });

    $ps2.change(function() {
        if ($psw.val() !== "" && $psw.val() !== this.value) {
            updateTips("Passwords do not match");
        }
    });

    $phone.change(function() {
        regPhone.lastIndex = 0;
        // 0 = matached, 1 = 1st capturing group, 2 = 2nd, etc.
        var numarry = regPhone.exec(this.value);
        if (numarry !== null && numarry.length > 3) {
            this.value = "";
            // Country code?
            if (numarry[1] !== null && numarry[1] !== "")
                this.value = '+' + numarry[1];
            // The main part
            this.value = '(' + numarry[2] + ') ' + numarry[3] + '-' + numarry[4].substr(0, 4);
        }
    });

    $("#btnReg").click(function() {

        $('.hhk-txtInput').removeClass("ui-state-error");
        setAlert('');
        $('#returnError').text('');

        if ($('#g-recaptcha-response').val() == '') {
            updateTips("Click the box on the reCAPTCHA");
            return;
        }

        if (!checkLength($('#txtFirstName'), 'First name', 1, 45))
            return;
        if (!checkLength($('#txtLastName'), 'Last name', 1, 45))
            return;
        if (!checkLength($('#txtEmail'), 'Email address', 5, 100))
            return;
        if (!checkRegexp($('#txtEmail'), rexEmail, 'Incorrect Email'))
            return;
        if (!checkLength($('#txtPun'), 'User Name', 6, 45))
            return;
        if (!checkLength($psw, 'Password', 8, 45))
            return;

        if ($psw.val() !== $ps2.val()) {
            updateTips("Passwords do not match");
            return;
        }

        $('#pwHdn').val(hex_md5($psw.val()));

        $('#form1').submit();

    });

    $('input:first').focus();

});
        </script>
    </head>
    <body>
        <div id="wrapper">
            <a href="<?php echo $logoLink; ?>"><div id="logoLT"></div></a>
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
                            <td>*<input title="Choose a password with at least 7 characters" id='txtPW' type='password' size='15' value="" class='hhk-txtInput' />
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
