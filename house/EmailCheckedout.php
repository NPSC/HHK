<?php
/**
 * EmailedCheckedout.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
  */

require 'homeIncludes.php';
require(HOUSE . 'TemplateForm.php');
require(HOUSE . 'SurveyForm.php');

require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';


try {
    $config = new Config_Lite(ciCFG_FILE);
} catch (Exception $ex) {
    exit("Configurtion file is missing, path=".ciCFG_FILE);
}

try {
    $labels = new Config_Lite(LABEL_FILE);
} catch (Exception $ex) {
    exit("Label file is missing, path=".LABEL_FILE);
}


try {
    $dbConfig = $config->getSection('db');
} catch (Config_Lite_Exception $e) {
    exit("Database configurtion data is missing.");
}


if (is_array($dbConfig)) {

    if (strtoupper($dbConfig['DBMS']) != 'MYSQL') {
        exit('Only works on MySQL.  You are using: ' . $dbConfig['DBMS']);
    }

    // Don't use local UNIX socket
    $url = $dbConfig['URL'];
    if (strtolower($url) == 'localhost') {
        $url = '127.0.0.1';
    }

    $dbh = initMY_SQL($url, $dbConfig['Schema'], $dbConfig['User'], decryptMessage($dbConfig['Password']));

} else {

    exit("Bad Database Configurtion");
}

$uS = Session::getInstance();

$sendEmail = TRUE;
if (isset($_POST)) {
    // Don't send email when run as a web page.
    $sendEmail = FALSE;

    // Check for user logged in.
    if (!$uS->logged) {
        exit();
    }

    // Check user authorization
    if ($uS->rolecode > WebRole::WebUser) {
        exit('Unauthorized.');
    }
}

$siteName = $config->get("site", "Site_Name", "Hospitality HouseKeeper");
$from = $config->get("house", "NoReply", "");      // Email address message will show as coming from.
$maxAutoEmail = $config->getString('email_server', 'MaxAutoEmail');

$subjectLine = $labels->getString('referral', 'Survey_Subject', '');

if ($subjectLine == '') {
    exit("Subject line is missing.  Go to Labels & Prompts, referral -> Survey_Subject.");
}

if ($from == '') {
    exit("From/Reply To address is missing.  Go to System Configuration, House, NoReply.");
}



if (strtolower($uS->SolicitBuffer) === 'off') {
    if ($sendEmail) {
        exit();
    } else {
        exit('Auto Email is off.  Go to System Configuration, Solicit Buffer.');
    }
}

$delayDays = intval($uS->SolicitBuffer, 10);

if ($delayDays <1) {
    exit("Delay days not set properly.  Go to System Configuration, SolicitBuffer.");
}

// Load guests

$paramList[":delayDays"] = $delayDays;

$stmt = $dbh->prepare("SELECT
    n.Name_First,
    n.Name_Last,
    n.Name_Suffix,
    n.Name_Prefix,
    ne.Email,
    v.idVisit,
    np.idName,
    MAX(v.Actual_Departure) AS `Last_Departure`
FROM
    stays s
        JOIN
    visit v ON v.idVisit = s.idVisit
        AND v.Span = s.Visit_Span
        JOIN
    hospital_stay hp ON v.idHospital_stay = hp.idHospital_stay
        JOIN
    `name` n ON s.idName = n.idName
        JOIN
    `name` np ON hp.idPatient = np.idName
        AND np.Member_Status != 'd'
        JOIN
    name_email ne ON n.idName = ne.idName
        AND n.Preferred_Email = ne.Purpose
WHERE
    n.Member_Status != 'd'
        AND v.`Status` = 'co'
GROUP BY s.idName HAVING DateDiff(NOW(), MAX(v.Actual_Departure)) = :delayDays;", array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));

$stmt->execute($paramList);
$numRecipients = $stmt->rowCount();

if ($numRecipients > $maxAutoEmail) {
    // to many recipients.
    $stmt = NULL;
    exit("The number of email recipients, " . $stmt->rowCount() . " is higher than the maximum number allowed, $maxAutoEmail. See System Configuration, email_server -> MaxAutoEmail");
}

$mail = prepareEmail($config);

$mail->From = $from;
$mail->addReplyTo($from);
$mail->FromName = $siteName;

$mail->isHTML(true);
$mail->Subject = $subjectLine;

$sForm = new SurveyForm('survey.html');
$badAddresses = 0;

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {

    if (isset($r['Email']) && $r['Email'] != '') {
        // Verify Email Address
        $emailAddr = filter_var($r['Email'], FILTER_VALIDATE_EMAIL);

        if ($emailAddr === FALSE || $emailAddr == '') {
            $badAddresses++;
            continue;
        }
    } else {
        $badAddresses++;
        continue;
    }

    $form = $sForm->createForm($sForm->makeReplacements($r));

    if ($sendEmail) {

        $mail->clearAddresses();
        $mail->addAddress($emailAddr);

        $mail->Subject = $subjectLine;
        $mail->msgHTML($form);

        if ($mail->send() === FALSE) {
            echo $mail->ErrorInfo . '<br/>';
        }

    } else {
        echo "===========================<br/>(Email Address: " . $r['Email'] . ',  Visit Id: ' . $r['idVisit'] . ', Patient Id: ' . $r['idName'] . ")<br/>" . $subjectLine . "<br/>" . $form;
    }

    // Log in Visit Log?

}

$copyEmail = filter_var($config->getString('house', 'Auto_Email_Address'), FILTER_VALIDATE_EMAIL);

if ($sendEmail && $copyEmail && $copyEmail != '') {

    $mail->clearAddresses();
    $mail->addAddress($copyEmail);
    $mail->Subject = "Auto Email Results: " . $numRecipients . " messages sent. Bad: ".$badAddresses;

    $mail->msgHTML($sForm->templateFile);

    $mail->send();

} else if (!$sendEmail) {
    echo "<br/><br/><hr/>Auto Email Results: " . $numRecipients . " messages sent. Bad: ".$badAddresses;
    echo "<br/> Subject Line: " . $subjectLine;
    echo "<br/>Body Template:<br/>" . $sForm->templateFile;
}

// Log - Activity?


exit();
