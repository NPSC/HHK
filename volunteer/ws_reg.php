<?php
/**
 * ws_reg.php
 *
 * @category  Volunteer
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

require ('VolIncludes.php');
require (SEC . 'UserClass.php');
require(SEC . 'ChallengeGenerator.php');
require(CLASSES . 'fbUserClass.php');
require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';
require(THIRD_PARTY . 'autoload.php');



// define db connection obj
$dbh = initPDO();
$uS = Session::getInstance();

// Get the site configuration object
$config = new Config_Lite(ciCFG_FILE);

addslashesextended($_POST);

// set timezone
date_default_timezone_set($uS->tz);

$secret = $config->getString('recaptcha', 'Secret_Key', '') == '' ? $config->getString('recaptcha', 'HHK_Secret_Key', '') : $config->getString('recaptcha', 'Secret_Key');
$remoteIp = filter_var($_SERVER["REMOTE_ADDR"], FILTER_SANITIZE_STRING);

$recaptcha = new \ReCaptcha\ReCaptcha($secret);
$resp = $recaptcha->verify($_POST['rcr'], $remoteIp);

if (!$resp->isSuccess()) {

    foreach ($resp->getErrorCodes() as $code) {
        echo '<p>ReCpatcha: ' , $code , '</p> ';
    }

    exit();
}

//Check request
if (isset($_POST['c'])) {
    $c = filter_var($_POST['c'], FILTER_SANITIZE_STRING);
} else {
    echo(json_encode(array("error" => "Bad Command. ")));
    exit();
}

$events = array();
try {
    // switch on command...
    if ($c == "fb") {

        $events = saveNewFb($dbh, $config);

    } else if ($c == "web") {

        $events = saveNewWeb($dbh, $config, $_POST);
    }

} catch (PDOException $ex) {
    $events = array("error" => "Database Error: " . $ex->getMessage() . "; " . $ex->getTraceAsString());
} catch (Hk_Exception $ex) {
    $events = array("error" => "HouseKeeper Error: " . $ex->getMessage());
} catch (Exception $ex) {
    $events = array("error" => "PHP Error: " . $ex->getMessage());
}


// return results.
echo( json_encode($events));
exit();




function saveNewWeb(PDO $dbh, Config_Lite $config, $post) {
    $events = array();

    $web = new fbUserClass("");
    $web->loadFromArray($post);

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
                    exit();
                    break;
                default:
                    exit();
            }
            $events = array("warning" => $msg);
        } else {
            $events = processGuest($dbh, $config, $username, $web);
        }
    } else {
        $events = array("error" => "Missing User Name");
    }

    return $events;
}

function saveNewFb(PDO $dbh, $config) {
    $events = array();

    $fbc = new fbUserClass("");
    $fbc->loadFromArray($_POST);

    if ($fbc->get_fun() != "") {
        // set access code
        $fbc->set_accessCode("fb");

        $events = processGuest($dbh, $config, $fbc->get_fun(), $fbc);
    } else {
        $events = array("error" => "username is missing");
    }

    return $events;
}

function processGuest(\PDO $dbh, \Config_Lite $config, $username, \fbUserClass $fbc) {

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
