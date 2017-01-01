<?php
/**
 * GuestRegister.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
  */

require 'homeIncludes.php';

require (CLASSES . 'History.php');
require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';

require (CLASSES . 'CreateMarkupFromDB.php');

function prepareEmail3(Config_Lite $config) {

    $mail = new PHPMailer;

    switch ($config->getString('email_server', 'Type', 'mail')) {

        case 'smtp':

            $mail->isSMTP();

            $mail->SMTPDebug = $config->getString('email_server', 'Debug', '0');;

            $mail->Host = $config->getString('email_server', 'Host', '');
            $mail->SMTPAuth = $config->getBool('email_server', 'Auth_Required', 'true');
            $mail->Username = $config->getString('email_server', 'Username', '');

            if ($config->getString('email_server', 'Password', '') != '') {
                $mail->Password = decryptMessage($config->getString('email_server', 'Password', ''));
            }

            if ($config->getString('email_server', 'Port', '') != '') {
                $mail->Port = $config->getString('email_server', 'Port', '');
            }

            if ($config->getString('email_server', 'Secure', '') != '') {
                $mail->SMTPSecure = $config->getString('email_server', 'Secure', '');
            }

            break;

        case 'mail':
            $mail->isMail();
            break;

    }

    return $mail;
}

function getCheckedInMarkup(PDO $dbh, $page) {

    $query = "select * from vcurrent_residents order by `Room`;";
    $stmt = $dbh->query($query);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $returnRows = array();

    foreach ($rows as $r) {

        $fixedRows = array();



        // Build the page anchor
        if ($page != '') {
            $fixedRows['Guest'] = HTMLContainer::generateMarkup('a', $r['Guest'], array('href'=>"$page?id=" . $r["Id"]));
        } else {
            $fixedRows['Guest'] = $r['Guest'];
        }



        // House Phone
        if (strtolower($r['House Phone']) == 'y' && $r['Phone'] == '') {
            $fixedRows['Phone'] = '(House Phone)';
        } else {
            $fixedRows['Phone'] = $r['Phone'];
        }


        // Date?
        $fixedRows['Checked-In'] = date('M j, Y', strtotime($r['Checked-In']));

        // Days
        $stDay = new DateTime($r['Checked-In']);
        $stDay->setTime(10, 0, 0);
        $edDay = new DateTime(date('Y-m-d 10:00:00'));
        //$edDay->setTime(11, 0, 0);
        $fixedRows['Nights'] = $edDay->diff($stDay, TRUE)->days;

        // Expected Departure
        if ($r['Expected Depart'] != '') {
            $fixedRows['Expected Depart'] = date('M j, Y', strtotime($r['Expected Depart']));
        } else {
            $fixedRows['Expected Depart'] = '';
        }

        // Room name?
        $fixedRows["Room"] = HTMLContainer::generateMarkup('span', $r["Room"], array('style'=>'background-color:' . $r["backColor"]. ';color:' . $r["textColor"] . ';'));


//        // Hospital
//        $hospital = '';
//        if ($r['idAssociation'] > 0 && isset($hospitals[$r['idAssociation']][1])) {
//            $hospital .= $hospitals[$r['idAssociation']][1] . ' / ';
//        }
//        if ($r['idHospital'] > 0 && isset($hospitals[$r['idAssociation']][1])) {
//            $hospital .= $hospitals[$r['idHospital']][1];
//        }
//
//        $fixedRows['Hospital'] = $hospital;


        $fixedRows['Patient'] = $r['Patient'];

        $returnRows[] = $fixedRows;
    }
    return $returnRows;

}


// Only one caller
if ($_SERVER['REMOTE_ADDR'] != '216.97.230.50') {
    exit();
}

$config = new Config_Lite(ciCFG_FILE);

$siteName = $config->get("site", "Site_Name", "Hospitality HouseKeeper");
$from = $config->get("house", "Admin_Address", "");      // Email address message will show as coming from.
$to = $config->get("house", "Guest_Register_Email", "");      // Email address to send dump file to


// Exit if no one to mail this to...
if ($to == '') {
    exit();
}


$dbConfig = $config->getSection('db');
if (is_array($dbConfig)) {
    $dbUrl = $dbConfig['URL'];
    $dbuser = $dbConfig['User'];
    $dbpwd = decryptMessage($dbConfig['Password']);
    $dbname = $dbConfig['Schema'];
}

try {
    $dbh = new PDO(
            "mysql:host=" . $dbUrl . ";dbname=" . $dbname . ";charset=Latin1",
            $dbuser,
            $dbpwd,
            array(PDO::ATTR_PERSISTENT => true)
            );

    $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbh->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);

} catch (PDOException $e) {
    print "Error!: " . $e->getMessage() . "<br/>";

    die();
}


//$stmt = $dbh->query("Select `idHospital` as `Code`, `Title` as `Description`, `Type` as `Substitute` from hospital where `Status` ='a'");
//
//$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
//$nameLookups = array();
//
//foreach ($rows as $r) {
//    $hospitals[$r['Code']] = array($r['Code'],$r['Description'],$r['Substitute']);
//}


$currentCheckedIn = '<style>table {border:none;} td, th {padding: 10px; border: solid 1px black;}</style>';
$currentCheckedIn .= "<h2>Pay-it-Forward House Guest Register as of " . date('M j, Y  g:ia') . "</h2>";
$currentCheckedIn .= CreateMarkupFromDB::generateHTML_Table(getCheckedInMarkup($dbh, ''), '');

$mail = prepareEmail3($config);

$mail->From = $from;
$mail->addReplyTo($from);
$mail->FromName = $siteName;

$tos = explode(',', $to);
foreach ($tos as $t) {
    $bcc = filter_var($t, FILTER_SANITIZE_EMAIL);
    if ($bcc !== FALSE && $bcc != '') {
        $mail->addAddress($bcc);
    }
}

if ($from != '') {
    $mail->addBCC($from);
}


$mail->isHTML(true);

$mail->Subject = $config->getString('site', 'Site_Name', '') . ' Guest Register';
$mail->msgHTML($currentCheckedIn);


$mail->send();
//echo $currentCheckedIn;
exit();
