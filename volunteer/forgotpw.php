<?php
/**
 * forgotpw.php
 *
 * @category  Site
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

die();

include_once ('VolIncludes.php');
include_once (BASE_SITE .  'classes' . DS . 'UserClass.php');
require_once(BASE_SITE .  'classes' . DS . 'ChallengeGenerator.php');

// Get the site configuration object
$config = new Config_Lite(ciCFG_FILE);

// Run as test?
$testVersion = $config->getBool('site', 'Run_As_Test', true);

// define db connection obj
//$dbcon = initDB($config->getSection("db"));

$pageTitle = $config["site"]["Site_Name"];

// get version
define ("VER", $config["code"]["Build"]);


// if test version, put a big TEST on the page
if ($testVersion == true)
    $testHeader = "<span style='color:red;'>Test Version</span>";
else
    $testHeader = "<span>$pageTitle</span>";



$queryDiv = "display:block;";
$replyDiv = "display:none;";

if (isset($_POST["btnSum"])) {
    $dbcon = initDB();
    addslashesextended($_POST);

    if (isset($_POST["txtem"])) {
        $em = filter_var($_POST["txtem"], FILTER_SANITIZE_EMAIL);
        if ($em != "" && filter_var($em, FILTER_VALIDATE_EMAIL)) {

            $query = "select e.idName, ifnull(w.Enc_PW, 'null') from  name_email e left join w_users w on e.idName = w.idName where e.Email = '$em';";
            $res = queryDB($dbcon, $query);

            if (($r = mysqli_fetch_row($res))) {
                // record exists
                if ($r[0] > 0) {
                    if ($r[1] == "null") {
                        // w_users record does not exist
                        $replyMsg = "<h2>You are not registered with web access to the Pay-it-Forward House.</h2>
                            <p><a href=register_web.php >Register Now</a></p>";
                        $queryDiv = "display:none;";
                        $replyDiv = "display:block;";
                    }
                    else if ($r[1] == "") {
                        // w_users exists, empty password (could be facebook)
                        // send them to register_web?
                        $replyMsg = "<h2>You are not registered with web access to the Pay-it-Forward House.</h2>
                            <p><a href=register_web.php >Register Now</a></p>";
                        $queryDiv = "display:none;";
                        $replyDiv = "display:block;";
                    }
                    else {
                        // password exists, get a new one
                        if (strlen($r[1]) > 7) {
                            $pw = substr($r[1], 0, 8);
                        }
                        else {
                            $myDate = getdate();
                            $pw = "u$23)@H". $myDate["yday"];
                        }

                        $encPW = md5($pw);
                        $query = "update w_users w join name_email e on w.idName = e.idName
                            set w.Enc_PW='$encPW' where e.Email='$em';";

                        queryDB($dbcon, $query);

                        if (mysqli_affected_rows ($dbcon) == 1) {
                            // update successful, email the new pw
                            $to = $em;
                            $msg = "<br/>Your new p@ssw0rd is:<br/><br/>$pw";
                            $subj = "Reply to your Pay it Forward House request";
                            if (sendEmailToInfo($to, '', $subj, $msg)) {
                                // email started!
                                $replyMsg = "<h2>Email sent</h2>";
                                $queryDiv = "display:none;";
                                $replyDiv = "display:block;";
                            }
                            else {
                                // email failed!!
                                $replyMsg = "<h2>Email Failed</h2>";
                                $queryDiv = "display:none;";
                                $replyDiv = "display:block;";
                            }
                        }
                        else {
                            // update not successful, return an error.
                            $replyMsg = "<h2>Password change failed</h2>";
                            $queryDiv = "display:none;";
                            $replyDiv = "display:block;";
                        }
                    }
                }
                else {
                    // some kind of db error?
                    $replyMsg = "<h2>Password change failed</h2>";
                    $queryDiv = "display:none;";
                    $replyDiv = "display:block;";
                }

            }
            else {
                // record does NOT exist
                $replyMsg = "<h2>You are not registered with web access to the Pay-it-Forward House.</h2>
                    <p><a href=register_web.php >Register Now</a></p>";
                $queryDiv = "display:none;";
                $replyDiv = "display:block;";
            }
        }
         else {
             // no email address
         }
    }
}


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
    "http://www.w3.org/TR/html4/loose.dtd">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title></title>
        <link href="css/publicStyle.css" rel="stylesheet" type="text/css" />
    </head>
    <body>
        <?php echo $testHeader; ?>
        <div id="wrapper">

            <a href="http://www.payitforwardhouse.org"><div id="logoLT"></div></a>
            <div style="clear:both;"></div>
             <div id="queryDiv" style="<?php echo $queryDiv; ?>">
                 <form method="post" action="forgotpw.php" name="form1">
                     <h3>Enter your user name and email address and we will send you a new password.</h3><p>&nbsp;</p>
                     <table>
                         <tr>
                 <td class="tdlabel">User Name: </td><td><input type="text" name="txtun" value="" /></td>
                     </tr><tr>
                 <td class="tdlabel">Email address: </td><td><input type="text" name="txtem" value="" /></td>
                     </tr><tr>
                     <td class="tdlabel" colspan="2"><input type="submit" name="btnSum" value="Do it!" /></td>
                     </tr>
                 </table>
             </form>
             </div>
             <div id="replyDiv" style="<?php echo $replyDiv; ?>">
                 <?php echo $replyMsg; ?>
             </div>
        </div>
    </body>
</html>
