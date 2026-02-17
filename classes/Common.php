<?php

namespace HHK;

use DateTime;
use DebugBar\DataCollector\PDO\PDOCollector;
use DebugBar\DataCollector\PDO\TraceablePDO;
use HHK\Debug\DebugBarSupport;
use HHK\Exception\RuntimeException;
use HHK\Exception\UnexpectedValueException;
use HHK\HTMLControls\HTMLSelector;
use HHK\sec\Session;
use HHK\SysConst\{WebRole};
use HHK\sec\{SecurityComponent, SysConfig};
use PDO;


/**
 * Common.php
 * 
 * Contains common methods that don't belong anywhere else
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
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
                //$config = parse_ini_file(ciCFG_FILE, true);
                $config = parse_ini_file(CONF_PATH . ciCFG_FILE, true);
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

            $bar = DebugBarSupport::bar();
            
            if($bar){
                try{
                    $dbh = new TraceablePDO($dbh);
                    $pdoCollector = new PDOCollector();
                    $pdoCollector->addConnection($dbh, 'main');
                    $bar->addCollector($pdoCollector);
                }catch(\Exception $e){

                }
            }

            $dbh->exec("SET SESSION wait_timeout = 3600;");

            // Syncromize PHP and mySQL timezones
            static::syncTimeZone($dbh);

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


    /**
     * Sync house timezone in PHP + database
     * @param \PDO $dbh
     * @return void
     */
    public static function syncTimeZone(PDO $dbh)
    {
        $tz = SysConfig::getKeyValue($dbh, 'sys_config', 'tz', 'utc');
        date_default_timezone_set($tz);
        $now = new DateTime();
        $tmins = $now->getOffset() / 60;
        $sgn = ($tmins < 0 ? - 1 : 1);
        $mins = abs($tmins);
        $hrs = floor($mins / 60);
        $mins -= $hrs * 60;
        $offset = sprintf('%+d:%02d', $hrs * $sgn, $mins);
        $dbh->exec("SET time_zone='$offset';");
    }


    /**
     * Create new DateTime object from string
     * @param mixed $strDate
     * @param mixed $strTz
     * @throws \Exception
     * @return DateTime
     */
    public static function newDateWithTz($strDate, $strTz): DateTime
    {
        if ($strTz == '') {
            throw new \Exception('(newDateWithTz) - timezone not set.  ');
        }

        if ($strDate != '') {
            $theDT = new DateTime($strDate);
        } else {
            $theDT = new DateTime();
        }

        $theDT->setTimezone(new \DateTimeZone($strTz));
        return $theDT;
    }

    /**
     * Takes a string date and converts to a DateTime object with the house timezone
     * @param mixed $uS
     * @param mixed $strDate
     * @return DateTime
     */
    public static function setTimeZone($uS, $strDate): DateTime
    {
        if (is_null($uS) || $uS instanceof Session == FALSE) {
            $uS = Session::getInstance();
        }

        return static::newDateWithTz($strDate, $uS->tz);
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

    public static function readGenLookupsPDO(PDO $dbh, $tbl, $orderBy = "Code")
    {
        $safeTbl = str_replace("'", '', $tbl);
        $query = "SELECT `Code`, `Description`, `Substitute`, `Type`, `Order`, `Attributes` FROM `gen_lookups` WHERE `Table_Name` = '$safeTbl' order by `$orderBy`;";
        $stmt = $dbh->query($query);

        $genArray = array();

        while ($row = $stmt->fetch(\PDO::FETCH_BOTH)) {
            $genArray[$row["Code"]] = $row;
        }

        return $genArray;
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

        while ($row = $stmt->fetch(\PDO::FETCH_BOTH)) {
            $genArray[$row['Code']] = $row;
        }

        return $genArray;
    }



    /**
     * Generate a random string
     * @param int $length
     * @return string
     */
    public static function getRandomString(int $length=40): string{
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

    /**
     * Summary of parseStringToArray
     * @param string $input
     * @param mixed $parameter
     * @throws \HHK\Exception\RuntimeException
     * @return array
     */
    public static function parseKeysToArray(array $inputArray): array
    {
        $result = [];

        foreach ($inputArray as $key => $parameter) {
        
            // short circuit for actuall arrays.
            if (is_array($parameter)) {
                $result[$key] = $parameter;

            } else {

                preg_match_all('/([a-zA-Z0-9_]+)(?:\[([a-zA-Z0-9_]+)\](?:\[([a-zA-Z0-9_]+)\])?)?/', $key, $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    if (count($match) === 2) { // Simple parameter, no array
                        $result[$match[1]] = $parameter;

                    } elseif (count($match) === 3) { // Single level array
                        $key1 = $match[1];
                        $key2 = $match[2];
                        if (!isset($result[$key1])) {
                            $result[$key1] = [];
                        }
                        $result[$key1][$key2] = $parameter; 
                    } elseif (count($match) === 4) { // Double level array
                        $key1 = $match[1];
                        $key2 = $match[2];
                        $key3 = $match[3];

                        if (!isset($result[$key1])) {
                            $result[$key1] = [];
                        }
                        if (!isset($result[$key1][$key2])) {
                            $result[$key1][$key2] = [];
                        }

                        $result[$key1][$key2][$key3] = $parameter; 
                    } else {
                        throw new UnexpectedValueException("Input parameter '$key' is malformed."); 
                    }
                }
            }
        }
        return $result;
    }

    public static function doLookups($con, $tbl, $sel, $offerBlank = true)
    {
        $g = static::readGenLookupsPDO($con, $tbl);

        return HTMLSelector::doOptionsMkup($g, $sel, $offerBlank);
    }
}