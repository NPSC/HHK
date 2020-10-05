<?php
use HHK\sec\Login;
use HHK\Exception\InvalidArgumentException;
use HHK\Exception\RuntimeException;
use HHK\sec\UserClass;
use HHK\House\GLCodes\GLParameters;
use HHK\House\GLCodes\GLCodes;
use HHK\sec\SysConfig;
use HHK\HTMLControls\HTMLTable;
use HHK\House\GLCodes\GLTemplateRecord;

/**
 * GenLedger.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
  */

require 'homeIncludes.php';

// Access the login object, set session vars,
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
	exit($hex->getMessage());
}

$u = new UserClass();

if(!$u->isCron()){

//	header('WWW-Authenticate: Basic realm="Hospitality HouseKeeper"');
	header('HTTP/1.0 401 Unauthorized');
	exit("Not authorized");

}


$today = new DateTime();


try {
	$glParm = new GLParameters($dbh, 'Gl_Code');
	
	$startDay = $glParm->getStartDay();
	
	// Exit if not start day.
	if ($today->format('d') != $glParm->getStartDay()) {
		exit();
	}
	
	$glCodes = new GLCodes($dbh, $today->format('m'), $today->format('Y'), $glParm, new GLTemplateRecord());
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
