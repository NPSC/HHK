<?php
namespace HHK\sec;
use HHK\Exception\RuntimeException;
use HHK\SysConst\{WebPageCode, Mode};

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
class WebInit {

    /**
     * Summary of page
     * @var ScriptAuthClass
     */
    public $page;

    /**
     * Summary of menuTitle
     * @var string
     */
    public $menuTitle;

    /**
     * Summary of pageTitle
     * @var string
     */
    public $pageTitle = "";

    /**
     * Summary of pageHeading
     * @var string
     */
    public $pageHeading = '';

    /**
     * Summary of resourceURL
     * @var string
     */
    public $resourceURL;

    /**
     * Summary of siteName
     * @var string
     */
    public $siteName;

    /**
     * Summary of testVersion
     * @var string
     */
    public $testVersion;

    const SYS_CONFIG = 'sys_config';



    /**
     * @var \PDO
     */
    public $dbh;

    /**
     * Summary of __construct
     * @param string $page_Type
     * @param bool $addCSP
     */
    function __construct($page_Type = WebPageCode::Page, $addCSP = TRUE) {

        $secComp = new SecurityComponent();
        $secComp->rerouteIfNotLoggedIn($page_Type, 'index.php');

        // define db connection obj
        try {

            $this->dbh = initPDO(FALSE);

        } catch (RuntimeException $hex) {

            if ($page_Type == WebPageCode::Page) {
                echo('<h3>' . $hex->getMessage() . '; <a href="index.php">Continue</a></h3>');
            } else {
                $rtn = array("error" => $hex->getMessage());
                echo json_encode($rtn);
            }

            exit();
        }

        // Page authorization check
        $this->page = new ScriptAuthClass($this->dbh);

        $this->page->Authorize_Or_Die();

        // get session instance
        $uS = Session::getInstance();

        // Run as test?
        $this->testVersion = $uS->testVersion;
        $this->siteName = $uS->siteName;

        $this->menuTitle = $this->siteName;
        $this->pageTitle = $this->siteName;

        // Demo or training version?
        if ($uS->mode !== Mode::Live) {
            $this->menuTitle = '*' . strtoupper($uS->mode) . "* " . $this->siteName;
            $this->pageTitle = strtoupper($uS->mode) . " - " . $this->siteName;
        }

        // Deprecated 7/23
        /*
        * if test version, put a big TEST on the page
        */
//        if ($this->testVersion !== FALSE) {
            //$this->menuTitle = "TEST VERSION";
           //$this->pageTitle = "TEST - " . $this->siteName;
//        }


        $this->pageHeading = $this->page->get_Page_Title();

        $this->sessionLoadGenLkUps();
        $this->sessionLoadGuestLkUps();

        // set timezone
        date_default_timezone_set($uS->tz);

        // Check session timeout
        if (isset($uS->SessionTimeout) === FALSE || $uS->SessionTimeout < 1) {
            $uS->SessionTimeout = 30;
        }

        if (!isset($uS->timeout_idle)) {
            $uS->timeout_idle = time() + ($uS->SessionTimeout * 60);
        } else {
            if ($uS->timeout_idle < time()) {
                $uS->logged = FALSE;
                $this->page->die_if_not_Logged_In($page_Type, "index.php");
            } else {
                $uS->timeout_idle = time() + ($uS->SessionTimeout * 60);
            }
        }

        if ($addCSP) {
            $cspURL = $this->page->getHostName();
            header("Content-Security-Policy: "
                    . "default-src data: blob: $cspURL https://fonts.gstatic.com/ https://online.instamed.com https://pay.instamed.com https://instamedprd.cachefly.net; "
                    . "script-src $cspURL https://www.gstatic.com/charts/ https://online.instamed.com https://pay.instamed.com https://instamedprd.cachefly.net 'unsafe-inline' 'unsafe-eval'; "
                    . "style-src $cspURL https://fonts.googleapis.com https://fonts.gstatic.com/ https://www.gstatic.com https://online.instamed.com https://pay.instamed.com https://instamedprd.cachefly.net 'unsafe-inline';"
                    . "connect-src $cspURL tablet.sigwebtablet.com:47290 ;"
                    . "object-src $cspURL blob: ; "); // FF 23+ Chrome 25+ Safari 7+ Opera 19+

            header("X-Content-Security-Policy: "
                    . "default-src data: blob: $cspURL https://fonts.gstatic.com/ https://online.instamed.com https://pay.instamed.com https://instamedprd.cachefly.net; "
                    . "script-src $cspURL https://www.gstatic.com/charts/ https://online.instamed.com https://pay.instamed.com https://instamedprd.cachefly.net 'unsafe-inline'; "
                    . "style-src $cspURL https://fonts.googleapis.com https://fonts.gstatic.com/ https://www.gstatic.com https://online.instamed.com https://pay.instamed.com https://instamedprd.cachefly.net 'unsafe-inline'; "
                    . "connect-src $cspURL tablet.sigwebtablet.com:47290 ;"
                    . "object-src $cspURL blob: ;"); // IE 10+

            header('X-Frame-Options: DENY');

            if (SecurityComponent::isHTTPS()) {
                header('Strict-Transport-Security: max-age=31536000'); // FF 4 Chrome 4.0.211 Opera 12
            }
        }

        //add cache control
        if ($page_Type == WebPageCode::Service){
            header("Cache-Control: must-revalidate,no-cache,no-store");
        }
    }



    /**
     * Summary of generatePageMenu
     * @return string
     */
    public function generatePageMenu() {
        // generate menu markup if page type = 'p'
        return $this->page->generateMenu($this->menuTitle, $this->dbh);

    }

    /**
     * Summary of sessionLoadGenLkUps
     * @return mixed
     */
    public function sessionLoadGenLkUps() {

        // get session instance
        $uS = Session::getInstance();

        // Load session if not already there
        if (isset($uS->nameLookups) === FALSE) {
            $this->reloadGenLkUps($uS);
        }
        return $uS->nameLookups;

    }

    /**
     * Summary of loadNameLookups
     * @param \PDO $dbh
     * @param Session $uS
     * @return void
     */
    public static function loadNameLookups(\PDO $dbh, $uS){
        $query = "select `Table_Name`, `Code`, `Description`, `Substitute` from `gen_lookups`
            where `Table_Name` in ('Address_Purpose','Email_Purpose','rel_type', 'NoReturnReason', 'Member_Basis','mem_status','Name_Prefix','Name_Suffix','Phone_Type', 'Pay_Type', 'Salutation', 'Role_Codes', 'Referral_Form_Status') order by `Table_Name`, `Code`;";
        $stmt = $dbh->query($query);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $nameLookups = array();

        foreach ($rows as $r) {
            $nameLookups[$r['Table_Name']][$r['Code']] = array($r['Code'],$r['Description'],$r['Substitute']);
        }

        // Demographics
        $demos = readGenLookupsPDO($dbh, 'Demographics', 'Order');

        foreach ($demos as $d) {

            $entries = readGenLookupsPDO($dbh, $d[0], 'Order');

            foreach ($entries as $e) {
                $nameLookups[$d[0]][$e['Code']] = array($e['Code'],$e['Description'],$e['Substitute']);
            }
        }

        $uS->nameLookups = $nameLookups;

    }

    /**
     * Summary of reloadGenLkUps
     * @param Session $uS
     * @return mixed
     */
    public function reloadGenLkUps($uS) {

        $this->loadNameLookups($this->dbh, $uS);

        SysConfig::getCategory($this->dbh, $uS, "'a'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $uS, "'d'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $uS, "'es'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $uS, "'f'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $uS, "'fg'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $uS, "'pr'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $uS, "'v'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $uS, "'ga'", webInit::SYS_CONFIG);

        return $uS->nameLookups;

    }

    /**
     * Summary of sessionLoadGuestLkUps
     * @return mixed
     */
    public function sessionLoadGuestLkUps() {

        // get session instance
        $uS = Session::getInstance();

        // Load session if not already there
        if (isset($uS->guestLookups) === FALSE) {
            $this->reloadSessionGuestLUs();

        }
        return $uS->guestLookups;

    }

    /**
     * Summary of reloadSessionGuestLUs
     * @return mixed
     */
    public function reloadSessionGuestLUs() {

        // get session instance
        $uS = Session::getInstance();

        SysConfig::getCategory($this->dbh, $uS, "'h'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $uS, "'ha'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $uS, "'hf'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $uS, "'c'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $uS, "'g'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $uS, "'p'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $uS, "'ga'", webInit::SYS_CONFIG);

        $query = "select `Table_Name`, `Code`, `Description`, `Substitute` from `gen_lookups`
            where `Table_Name` in ('Patient_Rel_Type', 'Key_Deposit_Code', 'Room_Category', 'Static_Room_Rate', 'Room_Rate_Adjustment', 'Room_Type', 'Resource_Type', 'Resource_Status', 'Room_Status', 'Visit_Status')
            UNION select `Category` as `Table_Name`, `Code`, `Title` as `Description`, `Other` as `Substitute` from `lookups` where `Use` = 'y'
            order by `Table_Name`, `Description`;";
        $stmt = $this->dbh->query($query);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $nameLookups = array();

        foreach ($rows as $r) {
            $nameLookups[$r['Table_Name']][$r['Code']] = array($r['Code'],$r['Description'],$r['Substitute']);
        }

        //get hospitals with status
        $query = "select idHospital, IF(`status`='r', concat(`Title`, ' (Retired)'), `Title`) as `Title`, `Type`, `Status`, `Reservation_Style`, `Stay_Style` from hospital where `Status` in ('a','r') order by `Status` asc, `Title` asc";
        $stmt = $this->dbh->query($query);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
        	$nameLookups["Hospitals"][$r['idHospital']] = array($r['idHospital'],$r['Title'],$r['Type'], $r['Status'], $r['Reservation_Style'], $r['Stay_Style']);
        }

        $uS->guestLookups = $nameLookups;

        return $uS->guestLookups;

    }

    /**
     * Summary of sessionLoadVolLkUps
     * @return mixed
     */
    public function sessionLoadVolLkUps() {

        // get session instance
        $uS = Session::getInstance();

        // Load session if not already there
        if (isset($uS->volLookups) === FALSE) {

            $this->reloadSessionVolLkUps();
        }

        return $uS->volLookups;

    }

    /**
     * Summary of reloadSessionVolLkUps
     * @return mixed
     */
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

        SysConfig::getCategory($this->dbh, $uS, "'v'", webInit::SYS_CONFIG);
        return $uS->volLookups;

    }

    /**
     * Summary of resetSessionIdle
     * @return void
     */
    public static function resetSessionIdle():void
    {
        $uS = Session::getInstance();
        if(isset($uS->SessionTimeout)){
            $uS->timeout_idle = time() + ($uS->SessionTimeout * 60);
        }else{
            $uS->timeout_idle = time() + (30 * 60);
        }
    }

}

?>