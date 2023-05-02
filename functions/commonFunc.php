<?php

use HHK\Exception\RuntimeException;
use HHK\sec\Session;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\SysConst\{WebRole};
use HHK\Config_Lite\Config_Lite;
use PHPMailer\PHPMailer\PHPMailer;
use HHK\HTMLControls\{HTMLContainer, HTMLTable};
use HHK\SysConst\PaymentMethod;
use HHK\Tables\{EditRS, GenLookupsRS, LookupsRS};
use HHK\TableLog\HouseLog;
use HHK\ExcelHelper;
use HHK\sec\{SecurityComponent, SysConfig};
use HHK\House\Reservation\Reservation_1;
use HHK\SysConst\ReservationStatus;

/**
 * commonFunc.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
function initPDO($override = FALSE)
{
    $ssn = Session::getInstance();
    $roleCode = $ssn->rolecode;

    if (! isset($ssn->databaseURL)) {
        throw new RuntimeException('<p>Missing Database URL (initPDO)</p>');
    }

    $dbuName = $ssn->databaseUName;
    $dbPw = $ssn->databasePWord;
    $dbHost = $ssn->databaseURL;
    $dbName = $ssn->databaseName;

    if ($roleCode >= WebRole::Guest && $override === FALSE) {
        // Get the site configuration object
        try {
            $config = parse_ini_file(ciCFG_FILE, true);
        } catch (Exception $ex) {
            $ssn->destroy();
            throw new RuntimeException("<p>Missing Database Session Initialization: " . $ex->getMessage() . "</p>");
        }

        $dbuName = (!empty($config['db'][ 'ReadonlyUser']) ? $config['db'][ 'ReadonlyUser'] : '');
        $dbPw = decryptMessage((!empty($config['db']['ReadonlyPassword']) ? $config['db']['ReadonlyPassword'] : ''));
    }

    try {

    	$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    	$options = [
    			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    			PDO::ATTR_EMULATE_PREPARES   => FALSE,
    	];

    	$dbh = new PDO($dsn, $dbuName, $dbPw, $options);

        $dbh->exec("SET SESSION wait_timeout = 3600;");

        // Syncromize PHP and mySQL timezones
        syncTimeZone($dbh);

    } catch (\PDOException $e) {

        $ssn->destroy(TRUE);

        if ($roleCode >= WebRole::DefaultRole && $override === FALSE) {
            throw new RuntimeException("<br/>Database Error: " . $e->getMessage());
        }

        $sec = new SecurityComponent();

        header('location:' . $sec->getRootURL(). 'reset.php?r=' . $e->getMessage());
        die();
    }

    return $dbh;
}


function syncTimeZone(\PDO $dbh)
{
    $tz = SysConfig::getKeyValue($dbh, 'sys_config', 'tz', 'utc');
    date_default_timezone_set($tz);
    $now = new \DateTime();
    $tmins = $now->getOffset() / 60;
    $sgn = ($tmins < 0 ? - 1 : 1);
    $mins = abs($tmins);
    $hrs = floor($mins / 60);
    $mins -= $hrs * 60;
    $offset = sprintf('%+d:%02d', $hrs * $sgn, $mins);
    $dbh->exec("SET time_zone='$offset';");
}

/**
 * Return a date with time = 0
 *
 * @param unknown $dateTime
 * @throws Exception
 * @return \DateTime|\DateTimeImmutable
 */
function justDate($dateTime) {

    if ($dateTime instanceof \DateTime) {
        $dateTime->setTime(0,0,0);
    } else if ($dateTime instanceof \DateTimeImmutable) {
        $dateTime =  $dateTime->setTime(0,0,0);
    } else if (is_string($dateTime)) {
        $dateTime = new \DateTime($dateTime);
        $dateTime->setTime(0,0,0);
    } else {
        throw new Exception('Not a date or string date. ');
    }

    return $dateTime;
}

function doExcelDownLoad($rows, $fileName)
{
    if (count($rows) === 0) {
        return;
    }

    $reportRows = 1;
    $writer = new ExcelHelper($fileName);

    // build header
    $hdr = array();
    $colWidths = array();

    $keys = array_keys($rows[0]);

    foreach ($keys as $t) {
        $hdr[$t] = "string";
        $colWidths[] = "20";
    }

    $hdrStyle = $writer->getHdrStyle($colWidths);

    $writer->writeSheetHeader("Sheet1", $hdr, $hdrStyle);

    foreach ($rows as $r) {

        $flds = array_values($r);

        $row = $writer->convertStrings($hdr, $flds);
        $writer->writeSheetRow("Sheet1", $row);
    }
    $writer->download();
}

function prepareEmail()
{

    $uS = Session::getInstance();

    $mail = new PHPMailer(true);

    if($uS->DKIMdomain && @file_get_contents($uS->keyPath . '/dkim/dkimPrivateKey.pem')){
        $mail->DKIM_domain = $uS->DKIMdomain;
        $mail->DKIM_private = $uS->keyPath . '/dkim/dkimPrivateKey.pem';
        $mail->DKIM_selector = "hhk";
        $mail->DKIM_identity = $mail->From;
    }

    switch (strtolower($uS->EmailType)) {

        case 'smtp':

            $mail->isSMTP();

            $mail->Host = $uS->SMTP_Host;
            $mail->SMTPAuth = $uS->SMTP_Auth_Required;
            $mail->Username = $uS->SMTP_Username;

            if ($uS->SMTP_Password != '') {
                $mail->Password = decryptMessage($uS->SMTP_Password);
            }

            if ($uS->SMTP_Port != '') {
                $mail->Port = $uS->SMTP_Port;
            }

            if ($uS->SMTP_Secure != '') {
                $mail->SMTPSecure = $uS->SMTP_Secure;
            }

            $mail->SMTPDebug = $uS->SMTP_Debug;

            break;

        case 'mail':
            $mail->isMail();
            break;
    }

    return $mail;
}

function doPaymentMethodTotals(\PDO $dbh, $month, $year) {

	$startDT = new \DateTimeImmutable(intval($year) . '-' . intval($month) . '-01');
	$start = $startDT->format('Y-m-d');

	// End date is the beginning of the next month.
	$endDT = $startDT->add(new DateInterval('P1M'));
	$end = $endDT->format('Y-m-d');

	$tbl = new HTMLTable();

	// get payment methods
	$payTypes = array();
	$stmtp = $dbh->query("select * from payment_method");
	while ($t = $stmtp->fetch(\PDO::FETCH_NUM)) {
		if ($t[0] > 0 && $t[0] != PaymentMethod::ChgAsCash) {
			$payTypes[$t[0]] = $t[1];
		}
	}
	$payTypes[''] = 'Total';

	// Payment Method Totals
	$pmStmt = $dbh->query("Select p.idPayment_Method,
    		sum(Case
    				When p.Is_Refund = 1 Then (0 - p.Amount)
    				When p.Status_Code = 'r' and Date(p.Timestamp) < Date('$end') and Date(p.Timestamp) >= Date('$start')
    				  and Date(p.Last_Updated) < Date('$end') and Date(p.Last_Updated) >= Date('$start') then 0
    				When p.Status_Code = 'r' and Date(p.Timestamp) < Date('$start')
    				  and Date(p.Last_Updated) < Date('$end') and Date(p.Last_Updated) >= Date('$start') then (0 - p.Amount)
    				Else p.Amount
    				END
    				) as mAmount
    		FROM
    		`payment` `p`
    		where
    		p.Status_Code not in ('d', 'v')
    		and (
    				(Date(p.Timestamp) < Date('2020-07-01') and Date(p.Timestamp) >= Date('$start'))
    				or (Date(p.Last_Updated) < Date('2020-07-01') and Date(p.Last_Updated) >= Date('$start'))
    				)
    		Group by p.idPayment_Method WITH ROLLUP");

	while ($r = $pmStmt->fetch(PDO::FETCH_NUM)) {
		$tbl->addBodyTr(HTMLTable::makeTd($payTypes[$r[0]], array('class'=>'tdlabel')) . HTMLTable::makeTd($r[1], array('style'=>'text-align:right;')));
	}

	return $tbl->generateMarkup();
}

function newDateWithTz($strDate, $strTz)
{
    if ($strTz == '') {
        throw new \Exception('(newDateWithTz) - timezone not set.  ');
    }

    if ($strDate != '') {
        $theDT = new \DateTime($strDate);
    } else {
        $theDT = new \DateTime();
    }

    $theDT->setTimezone(new \DateTimeZone($strTz));
    return $theDT;
}

function setTimeZone($uS, $strDate)
{
    if (is_null($uS) || $uS instanceof Session == FALSE) {
        $uS = Session::getInstance();
    }

    return newDateWithTz($strDate, $uS->tz);
}

function incCounter(\PDO $dbh, $counterName)
{
    $dbh->query("CALL IncrementCounter('$counterName', @num);");

    foreach ($dbh->query("SELECT @num") as $row) {
        $rptId = $row[0];
    }

    if ($rptId == 0) {
        throw new RuntimeException("Increment counter not set up for $counterName.");
    }

    return $rptId;
}

function checkHijack($uS)
{
    if ($uS->vaddr == "y" || $uS->vaddr == "Y") {
        return true;
    } else {
        return false;
    }
}

function setHijack(\PDO $dbh, $uS, $code = "")
{
    $id = $uS->uid;
    $query = "update w_users set Verify_Address = '$code' where idName = $id;";
    $dbh->exec($query);
    $uS->vaddr = $code;
    return true;
}

function getYearArray()
{
    $curYear = intval(date("Y"));

    $yrs = array();
    // load years
    for ($i = $curYear - 5; $i <= $curYear; $i ++) {
        $yrs[$i] = array(
            $i,
            $i
        );
    }
    return $yrs;
}

function getYearOptionsMarkup($slctd, $startYear, $fyMonths, $showAllYears = TRUE)
{
    $markup = "";

    $curYear = intval(date("Y")) + 1;

    // Get month number of start of FY
    $fyDate = 12 - $fyMonths;

    // Show next year in list if we are already into the new FY
    if ($fyDate <= intval(date("n"))) {
        $curYear ++;
    }

    if ($showAllYears) {
        if ($slctd == "all" || $slctd == "") {
            $markup .= "<option value='all' selected='selected'>All Years</option>";
        } else {
            $markup .= "<option value='all'>All Years</option>";
        }
    }

    // load years
    for ($i = $startYear; $i <= $curYear; $i ++) {
        if ($slctd == $i) {
            $slctMarkup = "selected='selected'";
        } else {
            $slctMarkup = "";
        }
        $markup .= "<option value='" . $i . "' $slctMarkup>" . $i . "</option>";
    }
    return $markup;
}

function getKey()
{
    return "017d609a4b2d8910685595C8df";
}

function getIV()
{
    return "fYfhHeDmf j98UUy4";
}

function encryptMessage($input)
{
    $key = getKey();
    $iv = getIV();

    return encrypt_decrypt('encrypt', $input, $key, $iv);
}

function getNotesKey($keyPart)
{
    return "E4HD9h4DhS56DY" . trim($keyPart) . "3Nf";
}

function encryptNotes($input, $pw)
{
    $crypt = "";
    if ($pw != "" && $input != "") {
        $key = getNotesKey($pw);
        $iv = getIV();

        $crypt = encrypt_decrypt('encrypt', $input, $key, $iv);
    }

    return $crypt;
}

function decryptNotes($encrypt, $pw)
{
    $clear = "";

    if ($pw != "" && $encrypt != "") {

        $key = getNotesKey($pw);
        $clear = encrypt_decrypt('decrypt', $encrypt, $key, getIV());
    }

    return $clear;
}

function decryptMessage($encrypt)
{
    return encrypt_decrypt('decrypt', $encrypt, getKey(), getIV());
}

/**
 * simple method to encrypt or decrypt a plain text string
 * initialization vector(IV) has to be the same when encrypting and decrypting
 *
 * @param string $action:
 *            can be 'encrypt' or 'decrypt'
 * @param string $string:
 *            string to encrypt or decrypt
 *
 * @return string
 */
function encrypt_decrypt($action, $string, $secret_key, $secret_iv)
{
    $output = false;
    $encrypt_method = "AES-256-CBC";
    // $secret_key = 'This is my secret key';
    // $secret_iv = 'This is my secret iv';
    // hash
    $key = hash('sha256', $secret_key);

    // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
    $iv = substr(hash('sha256', $secret_iv), 0, 16);
    if ($action == 'encrypt') {
        $output = base64_encode(openssl_encrypt($string, $encrypt_method, $key, 0, $iv));
    } else if ($action == 'decrypt') {
        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
    }
    return $output;
}

function readGenLookups($con, $tbl, $orderBy = "Code")
{
    if (! is_a($con, 'mysqli')) {
        return readGenLookupsPDO($con, $tbl, $orderBy);
    } else {
        throw new RuntimeException('Non-PDO access not supported.  ');
    }
}

function readGenLookupsPDO(\PDO $dbh, $tbl, $orderBy = "Code")
{
    $safeTbl = str_replace("'", '', $tbl);
    $query = "SELECT `Code`, `Description`, `Substitute`, `Type`, `Order` FROM `gen_lookups` WHERE `Table_Name` = '$safeTbl' order by `$orderBy`;";
    $stmt = $dbh->query($query);

    $genArray = array();

    while ($row = $stmt->fetch(\PDO::FETCH_BOTH)) {
        $genArray[$row["Code"]] = $row;
    }

    return $genArray;
}

function readLookups(\PDO $dbh, $category, $orderBy = "Code", $includeUnused = false)
{
    if ($includeUnused) {
        $where = "";
    } else {
        $where = "and `Use` = 'y'";
    }

    $query = "SELECT `Code`, `Title`, `Use`, `Show`, `Type`, `Other` as 'Icon' FROM `lookups` WHERE `Category` = '$category' $where order by `$orderBy`;";
    $stmt = $dbh->query($query);
    $genArray = array();

    while ($row = $stmt->fetch(\PDO::FETCH_BOTH)) {
        $genArray[$row['Code']] = $row;
    }

    return $genArray;
}

function doOptionsMkup($gArray, $sel, $offerBlank = true, $placeholder = "")
{
    $data = "";
    if ($offerBlank) {
        $sel = trim($sel);
        if (is_null($sel) || $sel == "") {
            $data = "<option value='' selected='selected' disabled='disabled'>" . $placeholder . "</option>";
        } else {
            $data = "<option value=''></option>";
        }
    }
    foreach ($gArray as $row) {

        if ($sel == $row[0]) {
            $data = $data . "<option value='" . $row[0] . "' selected='selected'>" . $row[1] . "</option>";
        } else {
            $data = $data . "<option value='" . $row[0] . "'>" . $row[1] . "</option>";
        }
    }

    return $data;
}

function DoLookups($con, $tbl, $sel, $offerBlank = true)
{
    $g = readGenLookups($con, $tbl);

    return doOptionsMkup($g, $sel, $offerBlank);
}

function removeOptionGroups($gArray)
{
    $clean = array();
    if (is_array($gArray)) {
        foreach ($gArray as $s) {
            $clean[$s[0]] = array(
                $s[0],
                $s[1]
            );
        }
    }
    return $clean;
}

function saveGenLk(\PDO $dbh, $tblName, array $desc, array $subt, array $del, array $type = array())
{
    if (isset($desc)) {

        foreach ($desc as $k => $r) {

            $code = trim(filter_var($k, FILTER_SANITIZE_FULL_SPECIAL_CHARS));

            if ($code == '') {
                continue;
            }

            $glRs = new GenLookupsRS();
            $glRs->Table_Name->setStoredVal($tblName);
            $glRs->Code->setStoredVal($code);

            $rates = EditRS::select($dbh, $glRs, array(
                $glRs->Table_Name,
                $glRs->Code
            ));

            if (count($rates) == 1) {

                $uS = Session::getInstance();

                EditRS::loadRow($rates[0], $glRs);

                if ($del != NULL && isset($del[$code])) {
                    // delete
                    EditRS::delete($dbh, $glRs, array(
                        $glRs->Table_Name,
                        $glRs->Code
                    ));
                    $logText = HouseLog::getDeleteText($glRs, $tblName . $code);
                    HouseLog::logGenLookups($dbh, $tblName, $code, $logText, 'delete', $uS->username);
                } else {
                    // update
                    if (isset($desc[$code]) && $desc[$code] != '') {
                        $glRs->Description->setNewVal(filter_var($desc[$code], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
                    }
                    if (isset($subt[$code])) {
                        $glRs->Substitute->setNewVal(filter_var($subt[$code], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
                    }
                    if (isset($type[$code])) {
                        $glRs->Type->setNewVal(filter_var($type[$code], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
                    }

                    $ctr = EditRS::update($dbh, $glRs, array(
                        $glRs->Table_Name,
                        $glRs->Code
                    ));

                    if ($ctr > 0) {
                        $logText = HouseLog::getUpdateText($glRs, $tblName . $code);
                        HouseLog::logGenLookups($dbh, $tblName, $code, $logText, 'update', $uS->username);
                    }
                }
            }
        }
    }
}

function replaceGenLk(\PDO $dbh, $tblName, array $desc, array $subt, array $order, $del, $replace, array $replaceWith)
{
    $rowsAffected = 0;

    if (isset($desc)) {

        foreach ($desc as $k => $r) {

            $code = trim(filter_var($k, FILTER_SANITIZE_FULL_SPECIAL_CHARS));

            if ($code == '') {
                continue;
            }

            $glRs = new GenLookupsRS();
            $glRs->Table_Name->setStoredVal($tblName);
            $glRs->Code->setStoredVal($code);

            $rates = EditRS::select($dbh, $glRs, array(
                $glRs->Table_Name,
                $glRs->Code
            ));

            if (count($rates) == 1) {
                $uS = Session::getInstance();

                EditRS::loadRow($rates[0], $glRs);

                if ($del != NULL && isset($del[$code])) {

                    // delete
                    if (is_null($replace) === FALSE) {

                        $rowCount = $replace($dbh, $replaceWith[$code], $code, $tblName);

                        if ($rowCount !== FALSE) {
                            $rowsAffected += $rowCount;
                        }
                    }

                    EditRS::delete($dbh, $glRs, array(
                        $glRs->Table_Name,
                        $glRs->Code
                    ));
                    $logText = HouseLog::getDeleteText($glRs, $tblName . $code);
                    HouseLog::logGenLookups($dbh, $tblName, $code, $logText, 'delete', $uS->username);
                } else {
                    // update
                    if (isset($desc[$code]) && $desc[$code] != '') {
                        $glRs->Description->setNewVal(filter_var($desc[$code], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
                    }

                    if (isset($subt[$code])) {

                        if (is_numeric($subt[$code])) {
                            $glRs->Substitute->setNewVal(filter_var($subt[$code], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
                        } else {
                            $glRs->Substitute->setNewVal(filter_var($subt[$code], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
                        }
                    }

                    if (isset($order[$code])) {

                        $glRs->Order->setNewVal(intval(filter_var($order[$code], FILTER_SANITIZE_NUMBER_INT), 10));
                    }

                    $ctr = EditRS::update($dbh, $glRs, array(
                        $glRs->Table_Name,
                        $glRs->Code
                    ));

                    if ($ctr > 0) {
                        $logText = HouseLog::getUpdateText($glRs, $tblName . $code);
                        HouseLog::logGenLookups($dbh, $tblName, $code, $logText, 'update', $uS->username);
                    }
                }
            }
        }
    }

    return $rowsAffected;
}

function replaceLookups(\PDO $dbh, $category, array $title, array $use)
{
    $uS = Session::getInstance();
    $rowsAffected = 0;

    if (isset($title)) {

        $reserveStatuses = readLookups($dbh, "ReservStatus", "Code", true);

        foreach ($title as $k => $r) {

            $code = trim(filter_var($k, FILTER_SANITIZE_FULL_SPECIAL_CHARS));

            if ($code == '') {
                continue;
            }

            $lookRs = new LookupsRS();
            $lookRs->Category->setStoredVal($category);
            $lookRs->Code->setStoredVal($code);

            $rates = EditRS::select($dbh, $lookRs, array(
                $lookRs->Category,
                $lookRs->Code
            ));

            if (count($rates) == 1) {

                EditRS::loadRow($rates[0], $lookRs);

                if (Reservation_1::isRemovedStatus($code, $reserveStatuses)) {
                    if (isset($use[$code])) {
                        // activate
                        $lookRs->Use->setNewVal("y");
                        $lookRs->Show->setNewVal("y");
                    } else if ($code != ReservationStatus::Canceled) {  // Make sure at least one code is available
                        $lookRs->Use->setNewVal("n");
                        $lookRs->Show->setNewVal("n");
                    }
                }

                // update
                if (isset($title[$code]) && $title[$code] != '') {
                    $lookRs->Title->setNewVal(filter_var($title[$code], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
                }

                $ctr = EditRS::update($dbh, $lookRs, array(
                    $lookRs->Category,
                    $lookRs->Code
                ));

                if ($ctr > 0) {
                    $logText = HouseLog::getUpdateText($lookRs, $category . $code);
                    HouseLog::logGenLookups($dbh, $category, $code, $logText, 'update', $uS->username);
                }
            }
        }
    }

    return $rowsAffected;
}

/**
 * Show guest photo HTML
 *
 * @param int $idGuest
 * @param int $widthPx - desired pixel width of image
 * @return string HTML formatted
 */

function showGuestPicture ($idGuest, $widthPx) {

    return HTMLContainer::generateMarkup('div',
        HTMLContainer::generateMarkup('div',
        HTMLContainer::generateMarkup('div',
        HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-plusthick'))
        , array("class"=>"ui-button ui-corner-all ui-widget upload-guest-photo", 'style'=>'padding: .3em;')) . HTMLContainer::generateMarkup('div',
        htmlContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-trash'))
        , array("class"=>"ui-button ui-corner-all ui-widget delete-guest-photo", 'style'=>'padding: .3em'))
        , array('style'=>"display:none;", 'id'=>'hhk-guest-photo-actions'))
        ,array("class"=>"ui-widget ui-widget-content ui-corner-all", "style"=>"width:" . $widthPx . "px; height:" . $widthPx . "px; background-image: url(../house/ws_resc.php?cmd=getguestphoto&guestId=$idGuest);", 'id'=>'hhk-guest-photo'));
}

/**
 * create thumbnail from uploaded photo
 *
 * @param $_FILES['photo'] $photo
 * @param int $newwidth
 * @param int $newheight
 * @return object photo data
 */
function makeThumbnail($photo, $newwidth, $newheight)
{
    if ($photo['type'] && $photo['tmp_name']) {
        $mime = $photo['type'];
        $file = $photo['tmp_name'];
        $temp = imagecreatetruecolor($newwidth, $newheight); // temp GD image object
        list ($oldwidth, $oldheight) = getimagesize($file); // get current width & height

        ob_start(); // start object buffer to capture image data

        switch ($mime) {
            case 'image/jpg':
            case 'image/jpeg':

                $image = imagecreatefromjpeg($file); // create GD image from input file
                imagecopyresampled($temp, $image, 0, 0, 0, 0, $newwidth, $newheight, $oldwidth, $oldheight); // resize image and save to $temp object
                imagejpeg($temp); // output image
                break;

            case 'image/png':

                $image = imagecreatefrompng($file); // create GD image from input file
                imagecopyresampled($temp, $image, 0, 0, 0, 0, $newwidth, $newheight, $oldwidth, $oldheight); // resize image and save to $temp object
                imagepng($temp); // output image
                break;

            default:
                throw new Exception("File Type not supported");
                break;
        }

        $thumbnailData = ob_get_contents(); // send object buffer/image data to variable
        ob_end_clean(); // close object buffer

        return $thumbnailData;
    } else {
        return false;
    }
}

// Random String
function getRandomString($length=40){
	if(!is_int($length)||$length<1){
		$length = 40;
	}
	$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	$randstring = '';
	$maxvalue = strlen($chars) - 1;
	for($i=0; $i<$length; $i++){
		$randstring .= substr($chars, rand(0,$maxvalue), 1);
	}
	return $randstring;
}
