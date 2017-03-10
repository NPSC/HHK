<?php
/**
 * WebInit.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * WebInit Class
 *
 * Web page or service initialization and authorization
 *
 */
class webInit {
    public $page;
    public $menuTitle;
    public $pageTitle = "";
    public $pageHeading = '';
    public $resourceURL;
    public $siteName;
    public $testVersion;

    public $dbh;

    /**
     *
     * @param string $Page_Type
     */
    function __construct($Page_Type = WebPageCode::Page, $addCSP = TRUE) {

        // find out what page we are on
        $parts = explode("/", filter_input(INPUT_SERVER, "SCRIPT_NAME", FILTER_SANITIZE_STRING));
        $pageAddress = $parts[count($parts) - 1];

        // check session for login - redirects to index.php otherwise
        SecurityComponent::die_if_not_Logged_In($Page_Type, "index.php", $pageAddress);

        // get session instance
        $uS = Session::getInstance();

        // set timezone
        date_default_timezone_set($uS->tz);

        // Run as test?
        $this->testVersion = $uS->testVersion;
        $this->siteName = $uS->siteName;
        $this->resourceURL = $uS->resourceURL;

        /*
        * if test version, put a big TEST on the page
        */
        if ($this->testVersion !== FALSE) {
            $this->menuTitle = "TEST VERSION";
            $this->pageTitle = "TEST - " . $this->siteName;
        } else {
            $this->menuTitle = $this->siteName;
            $this->pageTitle = $this->siteName;
        }

        // define db connection obj
        $this->dbh = initPDO();

        // Page authorization check
        $this->page = new ScriptAuthClass($this->dbh);
        $this->page->Authorize_Or_Die();

        $this->pageHeading = $this->page->get_Page_Title();

        $this->sessionLoadGenLkUps();

        // Check session timeout
        $t = time();

        if (isset($uS->SessionTimeout) === FALSE || $uS->SessionTimeout < 1) {
            $uS->SessionTimeout = 30;
        }

        if (!isset($uS->timeout_idle)) {
            $uS->timeout_idle = $t + ($uS->SessionTimeout * 60);
        } else {
            if ($uS->timeout_idle < time()) {
                $uS->logged = FALSE;
                SecurityComponent::die_if_not_Logged_In($Page_Type, "index.php", $pageAddress);
            } else {
                $uS->timeout_idle = $t + ($uS->SessionTimeout * 60);
            }
        }

        if ($addCSP) {
            $cspURL = $uS->siteList[$this->page->get_Site_Code()]['HTTP_Host'];
            header("Content-Security-Policy: default-src $cspURL; script-src $cspURL 'unsafe-inline'; style-src $cspURL 'unsafe-inline';"); // FF 23+ Chrome 25+ Safari 7+ Opera 19+
            header("X-Content-Security-Policy: default-src $cspURL; script-src $cspURL 'unsafe-inline'; style-src $cspURL 'unsafe-inline';"); // IE 10+

            header('X-Frame-Options: SAMEORIGIN');
            $isHttps = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off';
            if ($isHttps) {
              header('Strict-Transport-Security: max-age=31536000'); // FF 4 Chrome 4.0.211 Opera 12
            }
        }
    }


    public function logout($page = 'index.php') {
        $uS = Session::getInstance();
        $uS->destroy();
        header( "Location: $page");
    }


    public function generatePageMenu() {
        // generate menu markup if page type = 'p'
        return $this->page->generateMenu($this->menuTitle);

    }

    public function sessionLoadGenLkUps() {

        // get session instance
        $uS = Session::getInstance();

        // Load session if not already there
        if (isset($uS->nameLookups) === FALSE) {
            $this->reloadGenLkUps($uS);
        }
        return $uS->nameLookups;

    }

    public function reloadGenLkUps($uS) {

        $query = "select `Table_Name`, `Code`, `Description`, `Substitute` from `gen_lookups`
            where `Table_Name` in ('Address_Purpose','Email_Purpose','rel_type', 'NoReturnReason', 'Member_Basis','mem_status','Name_Prefix','Name_Suffix','Phone_Type', 'Pay_Type', 'Salutation', 'Role_Codes') order by `Table_Name`, `Code`;";
        $stmt = $this->dbh->query($query);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $nameLookups = array();

        foreach ($rows as $r) {
            $nameLookups[$r['Table_Name']][$r['Code']] = array($r['Code'],$r['Description'],$r['Substitute']);
        }

        // Demographics
        $demos = readGenLookupsPDO($this->dbh, 'Demographics', 'Order');

        foreach ($demos as $d) {

            $entries = readGenLookupsPDO($this->dbh, $d[0], 'Order');

            foreach ($entries as $e) {
                $nameLookups[$d[0]][$e['Code']] = array($e['Code'],$e['Description'],$e['Substitute']);
            }
        }

        $uS->nameLookups = $nameLookups;

        SysConfig::getCategory($this->dbh, $uS, "'f'", $uS->sconf);
        SysConfig::getCategory($this->dbh, $uS, "'r'", $uS->sconf);
        SysConfig::getCategory($this->dbh, $uS, "'d'", $uS->sconf);

        return $uS->nameLookups;

    }

    public function sessionLoadGuestLkUps() {

        // get session instance
        $uS = Session::getInstance();

        // Load session if not already there
        if (isset($uS->guestLookups) === FALSE) {
            $this->reloadSessionGuestLUs();

        }
        return $uS->guestLookups;

    }

    public function reloadSessionGuestLUs() {

        // get session instance
        $uS = Session::getInstance();

        // Load sys config table entries.
        SysConfig::getCategory($this->dbh, $uS, "'h'", $uS->sconf);

        $query = "select `Table_Name`, `Code`, `Description`, `Substitute` from `gen_lookups`
            where `Table_Name` in ('Patient_Rel_Type', 'WL_Status', 'Key_Disposition', 'Key_Deposit_Code', 'Room_Category', 'Static_Room_Rate', 'Room_Type', 'Resource_Type', 'Resource_Status', 'Room_Status', 'Visit_Status')
            UNION Select 'Hospitals' as `Table_Name`, `idHospital` as `Code`, `Title` as `Description`, `Type` as `Substitute` from hospital where `Status` ='a'
            UNION select `Category` as `Table_Name`, `Code`, `Title` as `Description`, `Other` as `Substitute` from `lookups` where `Show` = 'y'
            order by `Table_Name`, `Description`;";
        $stmt = $this->dbh->query($query);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $nameLookups = array();

        foreach ($rows as $r) {
            $nameLookups[$r['Table_Name']][$r['Code']] = array($r['Code'],$r['Description'],$r['Substitute']);
        }


        $uS->guestLookups = $nameLookups;

        return $uS->guestLookups;

    }

    public function sessionLoadVolLkUps() {

        // get session instance
        $uS = Session::getInstance();

        // Load session if not already there
        if (isset($uS->volLookups) === FALSE) {

            $this->reloadSessionVolLkUps();
        }

        return $uS->volLookups;

    }

    public function reloadSessionVolLkUps() {

        // get session instance
        $uS = Session::getInstance();

        $stmt = $this->dbh->query("select `Table_Name`, `Code`, `Description`, `Substitute` from `gen_lookups`
            where `Table_Name` in ('Vol_Category', 'Vol_Rank') order by `Table_Name`, `Description`;");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $nameLookups = array();

        foreach ($rows as $r) {
            $nameLookups[$r['Table_Name']][$r['Code']] = array($r['Code'],$r['Description'],$r['Substitute']);
        }

        $uS->volLookups = $nameLookups;

        SysConfig::getCategory($this->dbh, $uS, "'v'", $uS->sconf);
        return $uS->volLookups;

    }

}


class SysConfig {

    public static function getCategory(\PDO $dbh, \Session $uS, $category, $tableName)
    {

        if ($tableName == '' || $category == '') {
            throw new Hk_Exception_Runtime('System Configuration database table name or category not specified.  ');
        }

        $stmt = $dbh->query("select `Key`,`Value`,`Type` from `" . $tableName . "` where Category in ($category) order by `Key`");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $r) {

            $val = self::getTypedVal($r['Type'], $r['Value']);
            $uS->$r['Key'] = $val;
        }

        unset($rows);
        $stmt = NULL;

    }

    public static function getKeyValue(\PDO $dbh, $tableName, $key) {

        if ($tableName == '' || $key == '') {
            throw new Hk_Exception_Runtime('System Configuration database table name or key not specified.  ');
        }

        $stmt = $dbh->query("select `Value`,`Type` from `" . $tableName . "` where `Key` = '$key' ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows == 1)) {
            return $rows[0]['Value'];
        } else {
            throw new Hk_Exception_Runtime('System Configuration key not found.  ');
        }

    }

    public static function saveKeyValue(\PDO $dbh, $tableName, $key, $value) {

        $oldVal = self::getKeyValue($dbh, $tableName, $key);

        if ($oldVal != $value) {
            // Update table
            $query = "update `" . $tableName . "` set `Value` = :val where `Key` = :key";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(':val'=>$value, ':key'=>$key));

            $uS = Session::getInstance();
            $logText = $key . ':' .$oldVal . '|_|' . $value;
            HouseLog::logSysConfig($dbh, $key, $value, $logText, $uS->username);

        }
    }

    public static function getTypedVal($type, $value) {

        switch ($type) {
            case 'i':
                $val = (int)$value;
                break;
            case 'b':
                $val = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                break;
            default:
                $val = $value;
        }

        return $val;
    }

}