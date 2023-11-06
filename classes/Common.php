<?php

/**
 * Common functions used around HHK
 */
namespace HHK;
use HHK\sec\Crypto;
use HHK\sec\SecurityComponent;
use HHK\sec\Session;
use HHK\Exception\RuntimeException;
use HHK\sec\SysConfig;
use HHK\SysConst\WebRole;
use \PDO;

/**
 * Common functions used around HHK
 */
class Common {

     /**
     * Initialize DB connection
    *
    * @param bool $override
    * @throws \Exception
    * @return \PDO|void
    */
    public static function initPDO(bool $override = FALSE)
    {
        $ssn = Session::getInstance();
        $roleCode = $ssn->rolecode;

        if (! isset($ssn->databaseURL) && $override == FALSE) {
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
            } catch (\Exception $ex) {
                $ssn->destroy();
                throw new RuntimeException("<p>Missing Database Session Initialization: " . $ex->getMessage() . "</p>");
            }

            $dbuName = (!empty($config['db'][ 'ReadonlyUser']) ? $config['db'][ 'ReadonlyUser'] : '');
            $dbPw = Crypto::decryptMessage((!empty($config['db']['ReadonlyPassword']) ? $config['db']['ReadonlyPassword'] : ''));
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
            self::syncTimeZone($dbh);

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

    private static function syncTimeZone(PDO $dbh)
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

    public static function readGenLookupsPDO(PDO $dbh, $tbl, $orderBy = "Code")
    {
        $safeTbl = str_replace("'", '', $tbl);
        $query = "SELECT `Code`, `Description`, `Substitute`, `Type`, `Order` FROM `gen_lookups` WHERE `Table_Name` = '$safeTbl' order by `$orderBy`;";
        $stmt = $dbh->query($query);
    
        $genArray = array();
    
        while ($row = $stmt->fetch(PDO::FETCH_BOTH)) {
            $genArray[$row["Code"]] = $row;
        }
    
        return $genArray;
    }

    /**
     * Generate and download Excel file from multidimentional array
     *
     * @param array $rows
     * @param string $fileName
     * @return void
     */
    public static function doExcelDownLoad(array $rows, string $fileName):void
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

    public static function newDateWithTz($strDate, $strTz)
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

    public static function setTimeZone($uS, $strDate)
    {
        if (is_null($uS) || $uS instanceof Session == FALSE) {
            $uS = Session::getInstance();
        }

        return Common::newDateWithTz($strDate, $uS->tz);
    }

    public static function incCounter(PDO $dbh, $counterName)
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

    public static function getRandomString($length=40){
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

    public static function readLookups(PDO $dbh, $category, $orderBy = "Code", $includeUnused = false)
    {
        if ($includeUnused) {
            $where = "";
        } else {
            $where = "and `Use` = 'y'";
        }

        $query = "SELECT `Code`, `Title`, `Use`, `Show`, `Type`, `Other` as 'Icon' FROM `lookups` WHERE `Category` = '$category' $where order by `$orderBy`;";
        $stmt = $dbh->query($query);
        $genArray = array();

        while ($row = $stmt->fetch(PDO::FETCH_BOTH)) {
            $genArray[$row['Code']] = $row;
        }

        return $genArray;
    }

    public static function removeOptionGroups($gArray)
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

}


?>