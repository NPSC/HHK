<?php
/**
 * commonFunc.php
 *
 * @category  Utility
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

function initPDO($override = FALSE) {
    $ssn = Session::getInstance();
    /* Get Sectors from session */
    if (!isset($ssn->databaseURL)) {
        die('<br/>Missing Database Initialization (initPDO)<br/>');
    }

    $dbuName = $ssn->databaseUName;
    $dbPw = $ssn->databasePWord;

    if ($ssn->rolecode >= WebRole::Guest && $override === FALSE) {
        // Get the site configuration object
        try {
            $config = new Config_Lite(ciCFG_FILE);
        } catch (Exception $ex) {
            $ssn->destroy();
            throw new Hk_Exception_Runtime("Configurtion file is missing: " . $ex);
        }

        $dbuName = $config->getString('db', 'ReadonlyUser', '');
        $dbPw = decryptMessage($config->getString('db', 'ReadonlyPassword', ''));
    }

    try {
        $dbh = new PDO(
                "mysql:host=".$ssn->databaseURL.";dbname=" . $ssn->databaseName,
                $dbuName,
                $dbPw,
                array(PDO::ATTR_PERSISTENT => true)
                );

        $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $dbh->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);

        // Syncromize PHP and mySQL timezones
        syncTimeZone($dbh);

    } catch (PDOException $e) {
        print "Error!: " . $e->getMessage() . "<br/>";
        $ssn->destroy();
        die();
    }
    return $dbh;
}

function initDB() {
    $ssn = Session::getInstance();
    /* Get Sectors from session */
    if (!isset($ssn->databaseURL)) {
        die('Missing Database Initialization (initDB)');
    }

    // Open the connection
    $mysqli = mysqli_connect($ssn->databaseURL, $ssn->databaseUName, $ssn->databasePWord) or die('initDB cannot connect to the database because: ' . mysqli_error($mysqli));
    mysqli_select_db($mysqli, $ssn->databaseName);

    return $mysqli;
}

function syncTimeZone(\PDO $dbh) {

    $now = new DateTime();
    $mins = $now->getOffset() / 60;
    $sgn = ($mins < 0 ? -1 : 1);
    $mins = abs($mins);
    $hrs = floor($mins / 60);
    $mins -= $hrs * 60;
    $offset = sprintf('%+d:%02d', $hrs*$sgn, $mins);
    $dbh->exec("SET time_zone='$offset';");

}

/*
  DB Closing method.
  Pass the connection variable
  obtained through initDB().
 */
function closeDB($connection) {
        mysqli_close($connection);
}

function queryDB($dbcon, $query, $silence=true, $errCode = "0") {

    if (!$silence)
        ECHO "<br />At queryDB, query=" . $query . "<br />";

    // Connection exists?
    if ($dbcon == null) {
            trigger_error("Error  db connection object is not defined. at queryDB, Query = " . $query);
            $errors = array("error" => "db connection object is not defined");
            return $errors;
    }
    else  {
        // Connection exists, so leave it alone.
        if (($QDBres = mysqli_query($dbcon, $query)) === false) {
            trigger_error("My Error: ".mysqli_error($dbcon)."; at queryDB query= " . $query);
            $errors = array("error" => mysqli_error($dbcon));
            return $errors;
        }
    }
    return $QDBres;
}

function stripslashes_gpc(&$value) {
    $value = stripslashes($value);
}

function prepareEmail(Config_Lite $config) {


    $mail = new PHPMailer;
    $mailService = $config->getString('email_server', 'Type', 'mail');

    switch (strtolower($mailService)) {

        case 'smtp':

            $mail->isSMTP();

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

            $mail->SMTPDebug = $config->getString('email_server', 'Debug', '0');

            break;

        case 'mail':
            $mail->isMail();
            break;

    }

    return $mail;
}

// This is named backwards.  I'll start the new name, but it may take a while for all the code to comply
function addslashesextended(&$arr_r) {

    if (get_magic_quotes_gpc()) {
        array_walk_recursive($arr_r, 'stripslashes_gpc');

    }
}
function stripSlashesExtended(&$arr_r) {

    if (get_magic_quotes_gpc()) {
        array_walk_recursive($arr_r, 'stripslashes_gpc');

    }
}


function setTimeZone($uS, $strDate) {

    if (is_null($uS) || is_a($uS, 'Session') == FALSE) {
        $uS = Session::getInstance();
    }

    if (isset($uS->tz) === FALSE || $uS->tz == '') {
        throw new Exception('Session Timezone var (tz) not set.');
    }

    if ($strDate != '') {

        try {
            $theDT = new DateTime($strDate);
            $theDT->setTimezone(new DateTimeZone($uS->tz));
        } catch (Exception $ex) {
            $theDT = new DateTime();
        }

    } else {

        try {
            $theDT = new DateTime();
            $theDT->setTimezone(new DateTimeZone($uS->tz));
        } catch (Exception $ex) {
            $theDT = new DateTime();
        }
    }

    return $theDT;

}


function incCounter(PDO $dbh, $counterName) {

        $dbh->query("CALL IncrementCounter('$counterName', @num);");

        foreach ($dbh->query("SELECT @num") as $row) {
            $rptId = $row[0];
        }

        if ($rptId == 0) {
            throw new Hk_Exception_Runtime("Increment counter not set up for $counterName.");
        }

        return $rptId;
}

function checkHijack($uS) {
    if ($uS->vaddr == "y" || $uS->vaddr == "Y") {
        return true;
    } else {
        return false;
    }
}

function setHijack(PDO $dbh, $uS, $code = "") {

    $id = $uS->uid;
    $query = "update w_users set Verify_Address = '$code' where idName = $id;";
    $dbh->exec($query);
    $uS->vaddr = $code;
    return true;
}

function getYearArray() {

    $curYear = intval(date("Y"));

    $yrs = array();
    // load years
    for ($i = $curYear - 5; $i <= $curYear; $i++) {
        $yrs[$i] = array($i, $i);
    }
    return $yrs;
}

function getYearOptionsMarkup($slctd, $startYear, $fyMonths, $showAllYears = TRUE) {
    $markup = "";

    $curYear = intval(date("Y")) + 1;

    // Get month number of start of FY
    $fyDate = 12 - $fyMonths;

    // Show next year in list if we are already into the new FY
    if ($fyDate <= intval(date("n"))) {
        $curYear++;
    }

    if ($showAllYears) {
        if ($slctd == "all" || $slctd == "") {
            $markup .= "<option value='all' selected='selected'>All Years</option>";
        } else {
            $markup .= "<option value='all'>All Years</option>";
        }
    }

    // load years
    for ($i = $startYear; $i <= $curYear; $i++) {
        if ($slctd == $i) {
            $slctMarkup = "selected='selected'";
        } else {
            $slctMarkup = "";
        }
        $markup .= "<option value='" . $i . "' $slctMarkup>" . $i . "</option>";
    }
    return $markup;
}


function getKey() { return "017d609a4b2d8910685595C8";  }

function getIV() {
    return "fYfhHeDm";
}


function encryptMessage($input) {
    $key = getKey();
    $iv = getIV();
    $bit_check=8;// bit amount for diff algor.

    return encrypt($input,$key,$iv,$bit_check);
}

function getNotesKey($keyPart) {
    return "E4HD9h4DhS56DY" . trim($keyPart) . "3Nf";
}


function encryptNotes($input, $pw) {
    $crypt = "";
    if ($pw != "" && $input != "") {
        $key = getNotesKey($pw);
        $iv = getIV();
        $bit_check=8;// bit amount for diff algor.
        $crypt = encrypt($input,$key,$iv,$bit_check);
    }

    return $crypt;
}

function decryptMessage($encrypt) {
    $iv = getIV();// 8 bit IV
    $bit_check=8;// bit amount for diff algor.

    return decrypt($encrypt,getKey(),$iv,$bit_check);
}

function decryptNotes($encrypt, $pw) {
    $clear = "";

    if ($pw != "" && $encrypt != "") {
        $iv = getIV();// 8 bit IV
        $bit_check=8;// bit amount for diff algor.
        $key = getNotesKey($pw);
        $clear = decrypt($encrypt,$key,$iv,$bit_check);
    }

    return $clear;
}

function encrypt($text,$key,$iv,$bit_check) {
    $text_num =str_split($text,$bit_check);

    $text_num = $bit_check - strlen($text_num[count($text_num) - 1]);

    for ($i=0; $i<$text_num; $i++)
    {$text = $text . chr($text_num);}

    $cipher = mcrypt_module_open(MCRYPT_TRIPLEDES,'','cbc','');
    mcrypt_generic_init($cipher, $key, $iv);
    $decrypted = mcrypt_generic($cipher,$text);
    mcrypt_generic_deinit($cipher);
    return base64_encode($decrypted);
}

function decrypt($encrypted_text,$key,$iv,$bit_check){
    $cipher = mcrypt_module_open(MCRYPT_TRIPLEDES,'','cbc','');
    mcrypt_generic_init($cipher, $key, $iv);
    $decrypted = mdecrypt_generic($cipher, base64_decode($encrypted_text));
    mcrypt_generic_deinit($cipher);
    $last_char=substr($decrypted,-1);

    for($i=1; $i < $bit_check; $i++){
        if(chr($i)==$last_char){
            $decrypted=substr($decrypted, 0, strlen($decrypted) - $i);
            break;
        }
    }
    return $decrypted;
}

function readGenLookups($con, $tbl, $orderBy = "Code") {

    $query = "SELECT Code, Description, Substitute, Type FROM gen_lookups WHERE Table_Name = '" . $tbl . "' order by $orderBy;";

    if (!is_a($con, 'mysqli')) {
        return readGenLookupsPDO($con, $tbl, $orderBy);
    } else {
        $res = queryDB($con, $query, true);
    }

    $genArray = array();

    while ($row = mysqli_fetch_array($res)) {
        $genArray[$row["Code"]] = $row;
    }
    mysqli_free_result($res);
    return $genArray;
}

function readGenLookupsPDO(PDO $dbh, $tbl, $orderBy = "Code") {

    $query = "SELECT Code, Description, Substitute, Type FROM gen_lookups WHERE Table_Name = :tbl order by `$orderBy`;";
    $stmt = $dbh->prepare($query);
    $stmt->bindParam(':tbl', $tbl, PDO::PARAM_STR);

    $genArray = array();

    if ($stmt->execute()) {
        foreach ($stmt->fetchAll() as $row) {
            $genArray[$row["Code"]] = $row;
        }
    } else {

    }
    return $genArray;
}


function readLookups(PDO $dbh, $tbl, $orderBy = "Code") {

    $query = "SELECT Code, Title FROM lookups WHERE Category = :tbl and `Use` = 'y' order by `$orderBy`;";
    $stmt = $dbh->prepare($query);
    $stmt->bindParam(':tbl', $tbl, PDO::PARAM_STR);

    $genArray = array();

    if ($stmt->execute()) {
        foreach ($stmt->fetchAll() as $row) {
            $genArray[$row["Code"]] = $row;
        }
    } else {

    }
    return $genArray;
}

function doOptionsMkup($gArray, $sel, $offerBlank = true) {
    $data = "";
    if ($offerBlank) {
        $sel = trim($sel);
        if (is_null($sel) || $sel == "") {
            $data = "<option value='' selected='selected'></option>";
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

function DoLookups($con, $tbl, $sel, $offerBlank = true) {

    $g = readGenLookups($con, $tbl);

    return doOptionsMkup($g, $sel, $offerBlank);
}

function removeOptionGroups($gArray) {
    $clean = array();
    if (is_array($gArray)) {
        foreach ($gArray as $s) {
            $clean[$s[0]] = array($s[0],$s[1]);
        }
    }
    return $clean;
}

function saveGenLk(PDO $dbh, $tblName, array $desc, array $subt, $del, $type = array()) {

    if (isset($desc)) {

        foreach ($desc as $k => $r) {

            $code = trim(filter_var($k, FILTER_SANITIZE_STRING));

            if ($code == '') {
                continue;
            }

            $glRs = new GenLookupsRS();
            $glRs->Table_Name->setStoredVal($tblName);
            $glRs->Code->setStoredVal($code);

            $rates = EditRS::select($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));

            if (count($rates) == 1) {

                $uS = Session::getInstance();

                EditRS::loadRow($rates[0], $glRs);

                if ($del != NULL && isset($del[$code])) {
                    // delete
                    EditRS::delete($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));
                    $logText = HouseLog::getDeleteText($glRs, $tblName . $code);
                    HouseLog::logGenLookups($dbh, $tblName, $code, $logText, 'delete', $uS->username);

                } else {
                    // update
                    if (isset($desc[$code]) && $desc[$code] != '') {
                        $glRs->Description->setNewVal(filter_var($desc[$code], FILTER_SANITIZE_STRING));
                    }
                    if (isset($subt[$code])) {
                        $glRs->Substitute->setNewVal(filter_var($subt[$code], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
                    }
                    if (isset($type[$code])) {
                        $glRs->Type->setNewVal(filter_var($type[$code], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
                    }

                    $ctr = EditRS::update($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));

                    if ($ctr > 0) {
                        $logText = HouseLog::getUpdateText($glRs, $tblName . $code);
                        HouseLog::logGenLookups($dbh, $tblName, $code, $logText, 'update', $uS->username);
                    }
                }
            }
        }
    }
}

function replaceGenLk(PDO $dbh, $tblName, array $desc, array $subt, $del, $replace, array $replaceWith) {

    $rowsAffected = 0;

    if (isset($desc)) {

        foreach ($desc as $k => $r) {

            $code = trim(filter_var($k, FILTER_SANITIZE_STRING));

            if ($code == '') {
                continue;
            }

            $glRs = new GenLookupsRS();
            $glRs->Table_Name->setStoredVal($tblName);
            $glRs->Code->setStoredVal($code);

            $rates = EditRS::select($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));

            if (count($rates) == 1) {
                $uS = Session::getInstance();

                EditRS::loadRow($rates[0], $glRs);

                if ($del != NULL && isset($del[$code])) {

                    // delete
                    if (is_null($replace) === FALSE) {

                        $rowCount = $replace($dbh, $replaceWith[$code], $code);

                        if ($rowCount !== FALSE) {
                            $rowsAffected += $rowCount;
                        }
                    }

                    EditRS::delete($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));
                    $logText = HouseLog::getDeleteText($glRs, $tblName . $code);
                    HouseLog::logGenLookups($dbh, $tblName, $code, $logText, 'delete', $uS->username);

                } else {
                    // update
                    if (isset($desc[$code]) && $desc[$code] != '') {
                        $glRs->Description->setNewVal(filter_var($desc[$code], FILTER_SANITIZE_STRING));
                    }

                    if (isset($subt[$code])) {

                        $glRs->Substitute->setNewVal(filter_var($subt[$code], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
                    }

                    $ctr = EditRS::update($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));

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


