<?php
use HHK\Notification\Mail\HHKMailer;
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
	$login->initHhkSession(CONF_PATH, ciCFG_FILE);

} catch (\Exception $ex) {
	exit ($ex->getMessage());
}

$u = new UserClass();

// Only the cron job can run this.
if(!$u->isCron()){
	header('HTTP/1.0 401 Unauthorized');
	exit("Not authorized");

}

// DB login
try {
	$dbh = initPDO(TRUE);
} catch (RuntimeException $hex) {
	exit($hex->getMessage());
}





try {
	$glParm = new GLParameters($dbh, 'Gl_Code');
	$today = new DateTime();

	// Exit if not start day.
	if ($today->format('d') != $glParm->getStartDay()) {
		exit();
	}

	$today = new \DateTime();

	$glCodes = new GLCodes($dbh, $today->format('m'), $today->format('Y'), $glParm, new GLTemplateRecord());

	$bytesWritten = $glCodes->mapRecords()->transferRecords();

} catch (\Exception $ex) {
	exit($ex->getMessage());
}

$notificationAddress = SysConfig::getKeyValue($dbh, 'sys_config', 'NotificationAddress');

if ($notificationAddress != '') {

	// Mail Report
	$siteName = SysConfig::getKeyValue($dbh, 'sys_config', 'siteName');
	$from = SysConfig::getKeyValue($dbh, 'sys_config', 'NoReplyAddr');

	$mail = new HHKMailer($dbh);

	$mail->From = $from;
	$mail->addReplyTo($from);
	$mail->FromName = htmlspecialchars_decode($siteName, ENT_QUOTES);

	$mail->isHTML(true);
	$mail->Subject = htmlspecialchars_decode($siteName, ENT_QUOTES) . ' GL Transfer Report' . (strtolower(stristr($glParm->getRemoteFilePath(), 'test') == TRUE ? ' THIS IS A TEST' : ''));

	$addrArry = $mail->parseAddresses($notificationAddress);

	foreach ($addrArry as $a) {
		$mail->addAddress($a['address']);
	}

	$etbl = new HTMLTable();

	foreach ($glCodes->getErrors() as $e) {
		$etbl->addBodyTr(HTMLTable::makeTd($e));
	}

	$etbl->addBodyTr(HTMLTable::makeTd("Bytes Written: ". number_format($bytesWritten)));
	$etbl->addBodyTr(HTMLTable::makeTd('FTP Host:  ' . $glParm->getHost()));
	$etbl->addBodyTr(HTMLTable::makeTd('File Path:  ' . $glParm->getRemoteFilePath()));
	$etbl->addBodyTr(HTMLTable::makeTd('Processed at:  ' . $today->format('M j, Y H:i')));

	$mail->msgHTML($etbl->generateMarkup());

	if ($mail->send() === FALSE) {
		echo $mail->ErrorInfo;
	}
}

exit();
