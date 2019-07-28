<?php
/**
 * ws_vol.php
 *
 * @category  Volunteer
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

require("VolIncludes.php");
require(SEC . 'UserClass.php');
require(SEC . 'ChallengeGenerator.php');
require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';



$wInit = new webInit(WebPageCode::Service);
$dbh = $wInit->dbh;


// get session instance
$uS = Session::getInstance();


$userId = $uS->uid;
$uname = $uS->username;

addslashesextended($_REQUEST);

if (isset($_REQUEST["cmd"])) {
    $c = filter_var($_REQUEST["cmd"], FILTER_SANITIZE_STRING);
}

$events = array();

switch ($c) {
    case "list":

        $codes = '';
        if (isset($_POST["code"])) {
            $codes = filter_var($_POST["code"], FILTER_SANITIZE_STRING);
        }

        $events = listMembers($dbh, $codes);

        break;

    case "chairs":

        $codes = '';
        if (isset($_POST["code"])) {
            $codes = filter_var($_POST["code"], FILTER_SANITIZE_STRING);
        }

        $desc = '';
        if (isset($_POST["desc"])) {
            $desc = filter_var($_POST["desc"], FILTER_SANITIZE_STRING);
        }

        $events = listChairs($dbh, $codes, $desc);

        break;

    case "chgpw":

        $oldPw = ''; $newPw = '';

        if (isset($_POST["old"])) {
            $oldPw = filter_var($_POST["old"], FILTER_SANITIZE_STRING);
        }
        if (isset($_POST["newer"])) {
            $newPw = filter_var($_POST["newer"], FILTER_SANITIZE_STRING);
        }

        $events = changePW($dbh, $oldPw, $newPw, $uname, $userId);

        break;

    case "sendEmail" :

        $vcc = filter_var($_POST["vcc"], FILTER_SANITIZE_STRING);

        $subj = filter_var($_POST["subj"], FILTER_SANITIZE_STRING);

        $body = filter_var($_POST["body"], FILTER_SANITIZE_STRING);

        $events = volSendMail($dbh, $vcc, $subj, $body, $userId);
        break;

    default:
        $events = array("error" => "Bad Command");
}


echo( json_encode($events) );
exit();



function volSendMail(\PDO $dbh, $vcc, $subj, $body, $id) {

    $events = array();

    if ($vcc != "" && $subj != "" && $body != "") {

        $parts = explode("|", $vcc);

        if (count($parts) == 2) {

            $query = "select Id, concat(Name_First, ' ', Name_Last) as `Name`, PreferredPhone, PreferredEmail from vvol_categories2
                where Vol_Status='a'  and Category_Code=:cat and Vol_Code=:cod;";
            $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $stmt->execute(array(':cat'=>$parts[0], ':cod'=>$parts[1]));

            $missingEmail = array();
            $emailAddrs = array();
            $em = prepareEmail();

            // Collect members in one of two containers from above
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {

                if ($r['PreferredEmail'] != "") {
                    // Send email
                    if ($r['Id'] == $id) {
                        $em->addReplyTo($r['PreferredEmail']);
                    }

                    $emailAddrs[] = array('Name'=>$r["Name"], 'Email'=>$r["PreferredEmail"]);

                } else {
                    // missing email address
                    $missingEmail[$r["Name"]] = $r["PreferredPhone"];
                }
            }


            // Send the email


            $sentFlag = FALSE;

            // Send the message
            if (count($emailAddrs) > 0) {

                $em->addBCC($emailAddrs[0]['Email']);

                for ($j = 1; $j < count($emailAddrs); $j++)  {

                    $em->addBCC($emailAddrs[$j]['Email']);

                }

                $cBody = preg_replace_callback("/(&#[0-9]+;)/",
                        function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $body);

                $cSubj = preg_replace_callback("/(&#[0-9]+;)/",
                        function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $subj);

                $em->isHTML(true);

                $em->Subject = $uS->RegSubj;

                $em->From = $uS->Admin_Address;
                $em->addAddress($uS->Admin_Address);
                $em->Subject = $cSubj;
                $em->msgHTML($cBody);

                $sentFlag = $em->send();
            }

            if ($sentFlag) {
                // summarize results
                $msg = "Email sent to " . count($emailAddrs) . "recipients.\n";
                if (count($missingEmail) > 0) {
                    $msg .= count($missingEmail) . " members do not have email addresses:\n";

                    foreach ($missingEmail as $name => $num) {
                        $msg .= $name . ",  " . $num . "\n";
                    }
                }

                $events = array("success"=>$msg);

            } else {
                $events = array("error"=>"Send Email Failed");
            }

        } else {
            // wrong count of vol_cat | vol_code
            $events = array("error"=>"Bad committee name.");
        }
    } else {
        // One or more parameters are blank.
        $events = array("error"=>"Missing parameters.");
    }
    return $events;
}

function changePW(\PDO $dbh, $oldPw, $newPw, $uname, $id) {

    $event = array();

    $u = new UserClass();

    if ($u->_checkLogin($dbh, $uname, $oldPw) === FALSE) {
        return array('error'=>$u->logMessage);
    }

    if ($u->updateDbPassword($dbh, $id, $oldPw, $newPw, $uname) === TRUE) {
        $event = array('success'=>'Password updated.');
    } else {
        $event = array('error'=>'Password is unchanged.');
    }

    return $event;
}

function listChairs(\PDO $dbh, $codes, $desc) {

    if ($codes != "") {

        $title = $desc;
        $parts = explode("|", $codes);

        if (count($parts) == 2) {

            $query = "select Name_Last, Name_First, PreferredPhone, PreferredEmail, Vol_Rank, Category, Description from vvol_categories2
                where Vol_Status='a' and (Vol_Rank_Code = 'c' or Vol_Rank_Code = 'cc') and Category_Code='" . $parts[0] . "' and Vol_Code='" . $parts[1] . "';";
            $res = $dbh->query($query);

            $tbl = new HTMLTable();
            $tbl->addHeaderTr(HTMLTable::makeTh('First').HTMLTable::makeTh('Last').HTMLTable::makeTh('Phone').HTMLTable::makeTh('Email').HTMLTable::makeTh('Role'));

            while ($r = $res->fetch(\PDO::FETCH_ASSOC)) {

                $tbl->addBodyTr(
                        $tbl->makeTd($r["Name_Last"])
                        . $tbl->makeTd($r["Name_First"])
                        . $tbl->makeTd($r["PreferredPhone"])
                        . $tbl->makeTd($r["PreferredEmail"])
                        . $tbl->makeTd($r["Vol_Rank"])
                );

                $title = $r["Category"] . "/" . $r["Description"];
            }

            return array('table' => $tbl->generateMarkup(array('id'=>'tblListTable', 'style'=>'width:100%;')), 'title' => $title, 'removeId'=>'tblListTable');
         }
    }

    return array("error" => "invalid vol codes - " . $codes);
}


function listMembers(\PDO $dbh, $codes) {

    if ($codes != "") {

        $title = '';
        $parts = explode("|", $codes);

        if (count($parts) == 2) {

            $query = "select Name_Last, Name_First, PreferredPhone, PreferredEmail, Vol_Rank, Category, Description from vvol_categories2
                where Vol_Status='a' and Category_Code='" . $parts[0] . "' and Vol_Code='" . $parts[1] . "';";
            $res = $dbh->query($query);

            $tbl = new HTMLTable();
            $tbl->addHeaderTr(HTMLTable::makeTh('First').HTMLTable::makeTh('Last').HTMLTable::makeTh('Phone').HTMLTable::makeTh('Email').HTMLTable::makeTh('Role'));

            while ($r = $res->fetch(\PDO::FETCH_ASSOC)) {

                $tbl->addBodyTr(
                        HTMLTable::makeTd($r["Name_Last"])
                        . HTMLTable::makeTd($r["Name_First"])
                        . HTMLTable::makeTd($r["PreferredPhone"])
                        . HTMLTable::makeTd($r["PreferredEmail"])
                        . HTMLTable::makeTd($r["Vol_Rank"])
                );

                $title = $r["Category"] . "/" . $r["Description"];
            }

            return array('table' => $tbl->generateMarkup(array('id'=>'tblListTable', 'style'=>'width:100%;')), 'title' => $title, 'removeId'=>'tblListTable');
        }
    }

    return array("error" => "invalid vol codes: " . $codes);
}

