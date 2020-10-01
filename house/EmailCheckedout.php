<?php
use HHK\Config_Lite\Config_Lite;
use HHK\sec\Login;
use HHK\Exception\RuntimeException;
use HHK\sec\UserClass;
use HHK\sec\Session;
use HHK\SysConst\WebRole;
use HHK\sec\SysConfig;
use HHK\House\TemplateForm\SurveyForm;
use HHK\sec\Labels;

/**
 * EmailedCheckedout.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
  */

require 'homeIncludes.php';

try {
    $labels = Labels::getLabels();
} catch (Exception $ex) {
    exit("Label file is missing, path=".LABEL_FILE);
}


try {
	
	$login = new Login();
	$login->initHhkSession(ciCFG_FILE);
	
} catch (InvalidArgumentException $pex) {
	exit ("Database Access Error.");
	
} catch (Exception $ex) {
	exit ($ex->getMessage());
}

// Override user DB login credentials
try {
    $dbh = initPDO(TRUE);
} catch (RuntimeException $hex) {
    exit( $hex->getMessage());
}

$u = new UserClass();
if(!$u->isCron()){
    // Authenticate user
    $user = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
    $pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
    
    if ($u->_checkLogin($dbh, addslashes($user), $pass, FALSE) === FALSE) {
        
//        header('WWW-Authenticate: Basic realm="Hospitality HouseKeeper"');
        header('HTTP/1.0 401 Unauthorized');
        exit("Not authorized");
        
    }
}


$sendEmail = TRUE;

if (isset($_GET['sendemail']) && strtolower(filter_input(INPUT_GET, 'sendemail', FILTER_SANITIZE_STRING)) == 'no') {
    // Don't send email when run as a web page.
    $sendEmail = FALSE;

    $uS = Session::getInstance();

    // Check for user logged in.
    if (!$uS->logged) {
        exit();
    }

    // Check user authorization
    if ($uS->rolecode > WebRole::WebUser) {
        exit('Unauthorized.');
    }
}

$siteName = SysConfig::getKeyValue($dbh, 'sys_config', 'siteName');
$from = SysConfig::getKeyValue($dbh, 'sys_config', 'NoReplyAddr');      // Email address message will show as coming from.
$maxAutoEmail = SysConfig::getKeyValue($dbh, 'sys_config', 'MaxAutoEmail');

$subjectLine = $labels->getString('referral', 'Survey_Subject', '');

if ($subjectLine == '') {
    exit("Subject line is missing.  Go to Labels & Prompts, referral -> Survey_Subject.");
}

if ($from == '') {
    exit("From/Reply To address is missing.  Go to System Configuration, House, NoReply.");
}

$buffer = SysConfig::getKeyValue($dbh, 'sys_config', 'SolicitBuffer');


if (strtolower($buffer) === 'off') {
    if ($sendEmail) {
        exit();
    } else {
        exit('Auto Email is off.  Go to System Configuration, Solicit Buffer.');
    }
}

$delayDays = intval($buffer, 10);

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
$recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($numRecipients > $maxAutoEmail) {
    // to many recipients.
    $stmt = NULL;
    exit("The number of email recipients, " . $stmt->rowCount() . " is higher than the maximum number allowed, $maxAutoEmail. See System Configuration, email_server -> MaxAutoEmail");
}

$mail = prepareEmail();

$mail->From = $from;
$mail->addReplyTo($from);
$mail->FromName = $siteName;

$mail->isHTML(true);
$mail->Subject = $subjectLine;

$stmt = $dbh->query("Select d.`idDocument`, g.`Code`, g.`Description` from `document` d join gen_lookups g on d.idDocument = g.`Substitute` join gen_lookups fu on fu.`Substitute` = g.`Table_Name` where fu.`Code` = 's' AND fu.`Table_Name` = 'Form_Upload' order by g.`Order`");
$docRow = $stmt->fetch();
if($docRow){
    $sForm = new SurveyForm($dbh, $docRow['idDocument']);
}else{
    exit("Cannot find Survey document");
}

$badAddresses = 0;
$resultsRegister = '';
$deparatureDT = new \DateTime();
$deparatureDT->sub(new \DateInterval('P' . $delayDays . 'D'));

foreach ($recipients as $r) {

    $deparatureDT = new \DateTime($r['Last_Departure']);

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
            $resultsRegister .= "<p>Email send error: " . $mail->ErrorInfo . '</p>';
        }

        $resultsRegister .= "<p>Email Address: " . $r['Email'] . ',  Visit Id: ' . $r['idVisit'] . ', Patient Id: ' . $r['idName'] . "</p>";

    } else {
        echo "===========================<br/>(Email Address: " . $r['Email'] . ',  Visit Id: ' . $r['idVisit'] . ', Patient Id: ' . $r['idName'] . ")<br/>" . $subjectLine . "<br/>" . $form;
    }

    // Log in Visit Log?

}

$copyEmail = filter_var(SysConfig::getKeyValue($dbh, 'sys_config', 'Auto_Email_Address'), FILTER_VALIDATE_EMAIL);

if ($sendEmail && $copyEmail && $copyEmail != '') {

    $mail->clearAddresses();
    $mail->addAddress($copyEmail);
    $mail->Subject = "Auto Email Results for guests leaving " . $deparatureDT->format('M j, Y');

    $messg = "<p>Today's date: " . date('M j, Y');
    $messg .= "<p>For guests leaving " . $deparatureDT->format('M j, Y') . ', ' . $numRecipients . " messages were sent. Bad Emails: " . $badAddresses . "</p>";
    $messg .= "<p>Subject Line: </p>" . $subjectLine;
    $messg .= "<p>Template Text: </p>" . $sForm->template . "<br/>";
    $messg .= "<p>Results:</p>" . $resultsRegister;

    $mail->msgHTML($messg);

    $mail->send();

} else if (!$sendEmail) {
    echo "<br/><br/><hr/>Auto Email Results: " . $numRecipients . " messages sent. Bad: ".$badAddresses;
    echo "<p>For guests leaving " . $deparatureDT->format('M j, Y');
    echo "<br/> Subject Line: " . $subjectLine;
    echo "<br/>Body Template:<br/>" . $sForm->template;
}

// Log - Activity?


exit();
