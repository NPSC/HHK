<?php
/**
 * commonFunc.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
function initPDO($override = FALSE) {

    $ssn = Session::getInstance();
    $roleCode = $ssn->rolecode;

    if (!isset($ssn->databaseURL)) {
        die('<p>Missing Database URL (initPDO)</p>');
    }

    $dbuName = $ssn->databaseUName;
    $dbPw = $ssn->databasePWord;


    if ($roleCode >= WebRole::Guest && $override === FALSE) {
        // Get the site configuration object
        try {
            $config = new Config_Lite(ciCFG_FILE);
        } catch (Exception $ex) {
            $ssn->destroy();
            exit("<p>Missing Database Session Initialization: " . $ex->getMessage() . "</p>");
        }

        $dbuName = $config->getString('db', 'ReadonlyUser', '');
        $dbPw = decryptMessage($config->getString('db', 'ReadonlyPassword', ''));
    }

    try {

        switch (strtoupper($ssn->dbms)) {

            case 'MS_SQL':
                $dbh = initMS_SQL($ssn->databaseURL, $ssn->databaseName, $dbuName, $dbPw);
                break;

            case 'MYSQL':
                $dbh = initMY_SQL($ssn->databaseURL, $ssn->databaseName, $dbuName, $dbPw);

                $dbh->exec("SET SESSION wait_timeout = 3600;");

                break;

            case 'ODBC':
                return initODBC($ssn->databaseURL);


            default:
                // Use mysql
                $dbh = initMY_SQL($ssn->databaseURL, $ssn->databaseName, $dbuName, $dbPw);
                $dbh->exec("SET SESSION wait_timeout = 3600;");

        }

        $dbh->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $dbh->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_NATURAL);

        // Syncromize PHP and mySQL timezones
        syncTimeZone($dbh);

    } catch (\PDOException $e) {

        $ssn->destroy();

        if ($roleCode >= WebRole::DefaultRole && $override === FALSE) {
            exit("<br/>Database Error: " . $e->getMessage());
        }

        header('location:../reset.php?r=' . $e->getMessage());
        die();
    }

    return $dbh;
}

function initMS_SQL($dbURL, $dbName, $dbuName, $dbPw) {


    return new \PDO("sqlsrv:server=$dbURL;Database=$dbName", $dbuName, $dbPw);

}

function initODBC($dbURL) {

    /* Connect using Windows Authentication. */
    return new \PDO("odbc:$dbURL");

}

function initMy_SQL($dbURL, $dbName, $dbuName, $dbPw) {

    return new \PDO(
        "mysql:host=" . $dbURL . ";dbname=" . $dbName, $dbuName, $dbPw
    );

}

function syncTimeZone(\PDO $dbh) {

    $now = new \DateTime();
    $tmins = $now->getOffset() / 60;
    $sgn = ($tmins < 0 ? -1 : 1);
    $mins = abs($tmins);
    $hrs = floor($mins / 60);
    $mins -= $hrs * 60;
    $offset = sprintf('%+d:%02d', $hrs * $sgn, $mins);
    $dbh->exec("SET time_zone='$offset';");
}

function stripslashes_gpc(&$value) {
    $value = stripslashes($value);
}

function doExcelDownLoad($rows, $fileName) {

    if (count($rows) === 0) {
        return;
    }

    require_once CLASSES . 'OpenXML.php';

    $reportRows = 1;
    $sml = OpenXML::createExcel('', $fileName);

    // build header
    $hdr = array();
    $n = 0;

    $keys = array_keys($rows[0]);

    foreach ($keys as $t) {
        $hdr[$n++] = $t;
    }

    OpenXML::writeHeaderRow($sml, $hdr);
    $reportRows++;

    foreach ($rows as $r) {

        $n = 0;
        $flds = array();

        foreach ($r as $col) {

            $flds[$n++] = array('type' => "s", 'value' => $col);
        }


        $reportRows = OpenXML::writeNextRow($sml, $flds, $reportRows);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '.xlsx"');
    header('Cache-Control: max-age=0');

    OpenXML::finalizeExcel($sml);
    exit();

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
        throw new \Exception('Session Timezone var (tz) not set.');
    }

    if ($strDate != '') {

        try {
            $theDT = new \DateTime($strDate);
            $theDT->setTimezone(new \DateTimeZone($uS->tz));
        } catch (\Exception $ex) {
            $theDT = new \DateTime();
        }
    } else {

        try {
            $theDT = new \DateTime();
            $theDT->setTimezone(new \DateTimeZone($uS->tz));
        } catch (Exception $ex) {
            $theDT = new \DateTime();
        }
    }

    return $theDT;
}

function incCounter(\PDO $dbh, $counterName) {

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

function setHijack(\PDO $dbh, $uS, $code = "") {

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

function getKey() {
    return "017d609a4b2d8910685595C8df";
}

function getIV() {
    return "fYfhHeDmf j98UUy4";
}

function encryptMessage($input) {
    $key = getKey();
    $iv = getIV();

    return encrypt_decrypt('encrypt', $input, $key, $iv);
}

function getNotesKey($keyPart) {
    return "E4HD9h4DhS56DY" . trim($keyPart) . "3Nf";
}

function encryptNotes($input, $pw) {
    $crypt = "";
    if ($pw != "" && $input != "") {
        $key = getNotesKey($pw);
        $iv = getIV();

        $crypt = encrypt_decrypt('encrypt', $input, $key, $iv);
    }

    return $crypt;
}

function decryptNotes($encrypt, $pw) {
    $clear = "";

    if ($pw != "" && $encrypt != "") {

        $key = getNotesKey($pw);
        $clear = encrypt_decrypt('decrypt', $encrypt, $key, getIV());
    }

    return $clear;
}

function decryptMessage($encrypt) {

    return encrypt_decrypt('decrypt', $encrypt, getKey(), getIV());
}


/**
 * simple method to encrypt or decrypt a plain text string
 * initialization vector(IV) has to be the same when encrypting and decrypting
 *
 * @param string $action: can be 'encrypt' or 'decrypt'
 * @param string $string: string to encrypt or decrypt
 *
 * @return string
 */
function encrypt_decrypt($action, $string, $secret_key, $secret_iv) {
    $output = false;
    $encrypt_method = "AES-256-CBC";
//    $secret_key = 'This is my secret key';
//    $secret_iv = 'This is my secret iv';
    // hash
    $key = hash('sha256', $secret_key);

    // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
    $iv = substr(hash('sha256', $secret_iv), 0, 16);
    if ( $action == 'encrypt' ) {
        $output = base64_encode(openssl_encrypt($string, $encrypt_method, $key, 0, $iv));

    } else if( $action == 'decrypt' ) {
        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
    }
    return $output;
}



function readGenLookups($con, $tbl, $orderBy = "Code") {

    if (!is_a($con, 'mysqli')) {
        return readGenLookupsPDO($con, $tbl, $orderBy);
    } else {
        throw new Hk_Exception_Runtime('Non-PDO access not supported.  ');
    }
}

function readGenLookupsPDO(\PDO $dbh, $tbl, $orderBy = "Code") {

    $safeTbl = str_replace("'", '', $tbl);
    $query = "SELECT `Code`, `Description`, `Substitute`, `Type`, `Order` FROM `gen_lookups` WHERE `Table_Name` = '$safeTbl' order by `$orderBy`;";
    $stmt = $dbh->query($query);

    $genArray = array();

    while ($row = $stmt->fetch(\PDO::FETCH_BOTH)) {
        $genArray[$row["Code"]] = $row;
    }

    return $genArray;
}

function readLookups(\PDO $dbh, $tbl, $orderBy = "Code") {

    $query = "SELECT `Code`, `Title` FROM `lookups` WHERE `Category` = '$tbl' and `Use` = 'y' order by `$orderBy`;";
    $stmt = $dbh->query($query);
    $genArray = array();

    while ($row = $stmt->fetch(\PDO::FETCH_BOTH)) {
        $genArray[$row["Code"]] = $row;
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
            $clean[$s[0]] = array($s[0], $s[1]);
        }
    }
    return $clean;
}

function saveGenLk(\PDO $dbh, $tblName, array $desc, array $subt, $del, $type = array()) {

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

function replaceGenLk(\PDO $dbh, $tblName, array $desc, array $subt, array $order, $del, $replace, array $replaceWith) {

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

                        $rowCount = $replace($dbh, $replaceWith[$code], $code, $tblName);

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

                        if (is_numeric($subt[$code])) {
                            $glRs->Substitute->setNewVal(filter_var($subt[$code], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
                        } else {
                            $glRs->Substitute->setNewVal(filter_var($subt[$code], FILTER_SANITIZE_STRING));
                        }
                    }

                    if (isset($order[$code])) {

                        $glRs->Order->setNewVal(intval(filter_var($order[$code], FILTER_SANITIZE_NUMBER_INT), 10));
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
