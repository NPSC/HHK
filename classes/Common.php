<?php
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
}


?>