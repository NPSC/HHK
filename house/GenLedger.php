<?php
/**
 * GenLedger.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
  */

require 'homeIncludes.php';
require(HOUSE . 'GlCodes.php');
require(CLASSES . 'SFTPConnection.php');
require(SEC . 'Login.php');

//require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';
require (THIRD_PARTY . 'PHPMailer/v6/src/PHPMailer.php');
require (THIRD_PARTY . 'PHPMailer/v6/src/SMTP.php');
require (THIRD_PARTY . 'PHPMailer/v6/src/Exception.php');

// Access the login object, set session vars,
try {
	
	$login = new Login();
	$login->initHhkSession(ciCFG_FILE);
	
} catch (Hk_Exception_InvalidArguement $pex) {
	exit ("Database Access Error.");
	
} catch (Exception $ex) {
	exit ($ex->getMessage());
}

// Override user DB login credentials
try {
	$dbh = initPDO(TRUE);
} catch (Hk_Exception_Runtime $hex) {
	exit($hex->getMessage());
}

$u = new UserClass();

if(!$u->isCron()){

	header('WWW-Authenticate: Basic realm="Hospitality HouseKeeper"');
	header('HTTP/1.0 401 Unauthorized');
	exit("Not authorized");

}


$today = new DateTime();
$today->sub(new DateInterval('P1M'));

try {
	$glParm = new GlParameters($dbh, 'Gl_Code');
	$glCodes = new GlCodes($dbh, $today->format('m'), $today->format('Y'), $glParm, new GlTemplateRecord());
	$bytesWritten = $glCodes->mapRecords()->transferRecords();

} catch (Exception $ex) {
	exit($ex->getMessage());
}

$notificationAddress = SysConfig::getKeyValue($dbh, 'sys_config', 'NotificationAddress');

if ($notificationAddress != '') {

	// Mail Report
	$siteName = SysConfig::getKeyValue($dbh, 'sys_config', 'siteName');
	$from = SysConfig::getKeyValue($dbh, 'sys_config', 'NoReplyAddr');
	
	$mail = prepareEmail();

	$mail->From = $from;
	$mail->addReplyTo($from);
	$mail->FromName = $siteName;

	$mail->isHTML(true);
	$mail->Subject = 'GL Transfer Report';

	$addrArry = $mail->parseAddresses($notificationAddress);

	foreach ($addrArry as $a) {
		$mail->addAddress($a['address']);
	}

	$etbl = new HTMLTable();

	foreach ($glCodes->getErrors() as $e) {
		$etbl->addBodyTr(HTMLTable::makeTd($e));
	}

	if ($bytesWritten != '') {
		$etbl->addBodyTr(HTMLTable::makeTd("Bytes Written: ". number_format($bytesWritten)));
	}
	
	$mail->msgHTML($etbl->generateMarkup());
	
	if ($mail->send() === FALSE) {
		echo $mail->ErrorInfo;
	}
}

exit();
