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
require(SEC . 'Login.php');

function getCheckedInMarkup(PDO $dbh) {

    $query = "select * from vcurrent_residents order by `Room`;";
    $stmt = $dbh->query($query);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $returnRows = array();

    foreach ($rows as $r) {

        $fixedRows = array();

        // Build the page anchor
        $fixedRows['Guest'] = $r['Guest'];


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

        $fixedRows['Nights'] = $edDay->diff($stDay, TRUE)->days;

        // Expected Departure
        if ($r['Expected Depart'] != '') {
            $fixedRows['Expected Depart'] = date('M j, Y', strtotime($r['Expected Depart']));
        } else {
            $fixedRows['Expected Depart'] = '';
        }

        // Room name?
        $fixedRows["Room"] = HTMLContainer::generateMarkup('span', $r["Room"], array('style'=>'background-color:' . $r["backColor"]. ';color:' . $r["textColor"] . ';'));

        $fixedRows['Patient'] = $r['Patient'];

        $returnRows[] = $fixedRows;
    }
    return $returnRows;

}



try {

    $login = new Login();
    $config = $login->initializeSession(ciCFG_FILE);

} catch (PDOException $pex) {
    exit ("<h3>Database Error.  </h3>");

} catch (Exception $ex) {
    exit ("<h3>" . $ex->getMessage());
}


$siteName = $config->get("site", "Site_Name", "Hospitality HouseKeeper");
$from = $config->get("house", "Admin_Address", "");      // Email address message will show as coming from.
$to = $config->get("house", "Guest_Register_Email", "");      // Email address to send dump file to


// Exit if no one to mail this to...
if ($to == '') {
    exit();
}

// define db connection obj
$dbh = initPDO();

$currentCheckedIn = '<style>table {border:none;} td, th {padding: 10px; border: solid 1px black;}</style>';
$currentCheckedIn .= "<h2>Pay-it-Forward House Guest Register as of " . date('M j, Y  g:ia') . "</h2>";
$currentCheckedIn .= CreateMarkupFromDB::generateHTML_Table(getCheckedInMarkup($dbh, ''), '');

$mail = prepareEmail($config);

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
