<?php
namespace HHK\sec;

use HHK\Exception\RuntimeException;
use HHK\SysConst\WebPageCode;

/**
 * SecurityComponent.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class SecurityComponent {

    private $fileName = '';

    private $path = '';

    private $hostName = '';

    private $siteURL = '';

    private $rootURL = '';

    private $hhkSiteDir = '';

    private $rootPath = '';


    /**
     * Summary of __construct
     */
    public function __construct() {
        $this->defineThisURL();
    }

    /**
     * Summary of is_Authorized
     * @param mixed $name
     * @return bool
     */
    public static function is_Authorized($name) {

        if (self::is_Admin()) {
            return TRUE;
        }

        $uS = Session::getInstance();
        $pageCode = array();

        //parse url before checking authorization
        $parsedName = parse_url($name);
        if($parsedName !== FALSE && isset($parsedName["path"]) && $parsedName["path"] != ''){
            $name = $parsedName["path"];
        }

        // try reading the page table
        if ($name != "" && isset($uS->webPages[$name])) {
            $r = $uS->webPages[$name];

            if (!is_null($r)) {
                $pageCode = $r["Codes"];
            }
        } else {
            return FALSE;
        }

        // check authorization codes.
        return self::does_User_Code_Match($pageCode);

    }

    /**
     * Summary of rerouteIfNotLoggedIn
     * @param mixed $pageType
     * @param mixed $loginPage
     * @return void
     */
    public function rerouteIfNotLoggedIn($pageType, $loginPage) {

        $ssn = Session::getInstance();

        if ((isset($ssn->logged) == FALSE || $ssn->logged == FALSE) && isset($ssn->userAgent) == FALSE || $ssn->userAgent != filter_input(INPUT_SERVER, "HTTP_USER_AGENT", FILTER_SANITIZE_FULL_SPECIAL_CHARS) ) {

            $ssn->destroy(TRUE);

            if ($pageType != WebPageCode::Page) {

                echo json_encode(["error" => "Unauthorized.", 'gotopage' => $loginPage]);

            } else {

                //build redirect path
                $xf = $this->fileName;
                if(count($_GET) > 0){
                    $xf .= "?" . http_build_query($_GET);
                }

                header("Location: " . $loginPage . "?xf=" . urlencode($xf));
            }

            exit();
        }
    }

    /**
     * Summary of die_if_not_Logged_In
     * @param string $pageType
     * @param mixed $loginPage
     * @return void
     */
    public function die_if_not_Logged_In($pageType, $loginPage) {
        $ssn = Session::getInstance();

        if ($ssn->ssl === TRUE && self::isHTTPS() === FALSE) {

            // Must access pages through SSL
            header("Location: " . $this->getSiteURL() . 'index.php');
            exit();
        }

        if ((isset($ssn->logged) == FALSE || $ssn->logged == FALSE) && isset($ssn->userAgent) == FALSE || $ssn->userAgent != filter_input(INPUT_SERVER, "HTTP_USER_AGENT", FILTER_SANITIZE_FULL_SPECIAL_CHARS) ) {

            $ssn->destroy(TRUE);

            if ($pageType != WebPageCode::Page) {

                echo json_encode(array("error" => "Unauthorized.", 'gotopage' => $loginPage));

            } else {

                if ($this->fileName != '') {
                    header("Location: " . $loginPage . "?xf=" . $this->fileName);
                } else {
                    header("Location: " . $loginPage);
                }
            }

            exit();
        }
    }

    /**
     * Summary of does_User_Code_Match
     * @param array $pageCodes
     * @return bool
     */
    protected static function does_User_Code_Match(array $pageCodes) {

        $ssn = Session::getInstance();
        $userCodes = $ssn->groupcodes;

        foreach ($pageCodes as $pageCode) {
            // allow access to public pages.
            if ($pageCode == "pub") {
                return TRUE;

            }

            if ($pageCode != "" && is_array($userCodes)) {

                foreach ($userCodes as $c) {

                    if ($c == $pageCode) {
                        return TRUE;
                    }
                }
            }
        }

        return FALSE;
    }

    /**
     * Summary of isHTTPS
     * @return bool
     */
    public static function isHTTPS() {

        $serverHTTPS = (isset($_SERVER["HTTPS"]) ? $_SERVER["HTTPS"] : '');

        if (empty($serverHTTPS) || strtolower($serverHTTPS) == 'off' ) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Summary of defineThisURL
     * @throws \HHK\Exception\RuntimeException
     * @return void
     */
    private function defineThisURL() {

        $scriptName = filter_var((isset($_SERVER["SCRIPT_NAME"]) ? $_SERVER["SCRIPT_NAME"]: false), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $serverName = filter_var((isset($_SERVER["SERVER_NAME"]) ? $_SERVER["SERVER_NAME"]: false), FILTER_SANITIZE_URL);

        if (is_null($scriptName) || $scriptName === FALSE) {
            throw new RuntimeException('Script name not set.');
        }

        if (is_null($serverName) || $serverName === FALSE) {
            throw new RuntimeException('Server name not set.');
        }

        // scriptName = /rootDirs.../hhkSiteDir/filename
        // where hhkSiteDir is one of admin, house, volunteer or nothun
        //       roodDirs may be blank as well.
        //
        // find out what page we are on
        $parts = explode("/", $scriptName);

        // file name
        $this->fileName = $parts[count($parts) - 1];
        unset($parts[count($parts) - 1]);   // remove file name

        $this->path = implode("/", $parts) . '/';

        if (count($parts) >= 1) {

            $this->hhkSiteDir = $parts[count($parts) - 1] . '/';

            if ($this->hhkSiteDir != 'admin/' && $this->hhkSiteDir != 'house/' && $this->hhkSiteDir != 'volunteer/' && $this->hhkSiteDir != 'auth/') {
                // assume the root path.
                $this->hhkSiteDir = '/';
                $this->rootPath = $this->getPath();

            } else {

                unset($parts[count($parts) - 1]);

                // THe root path is what's left.
                $this->rootPath = implode("/", $parts) . '/';
            }

        } else {
            $this->hhkSiteDir = '/';
            $this->rootPath = $this->getPath();
        }


        // remove leading www if present.
        $hostParts = explode(".", $serverName);

        if (strtolower($hostParts[0]) == "www") {
            unset($hostParts[0]);
            $this->hostName = implode(".", $hostParts);
        } else {
            $this->hostName = $serverName;
        }

        if (self::isHTTPS()) {
            $this->siteURL = "https://" . $this->getHostName() . $this->getPath();
            $this->rootURL = "https://" . $this->getHostName() . $this->getRootPath();
        } else {
            // non-SSL access.
            $this->siteURL = "http://" . $this->getHostName() . $this->getPath();
            $this->rootURL = "http://" . $this->getHostName() . $this->getRootPath();
        }

    }

    /**
     * Summary of setResourceURL
     * @param \PDO $dbh
     * @return mixed
     */
    public function setResourceURL(\PDO $dbh){
        try{
            $resourceURL = SysConfig::getKeyValue($dbh, 'sys_config', 'resourceURL');
            if($resourceURL == '' || $resourceURL == "http://./"){
                SysConfig::saveKeyValue($dbh, "sys_config", "resourceURL", $this->getRootURL());
            }
            return SysConfig::getKeyValue($dbh, 'sys_config', 'resourceURL', '');
        }catch(\Exception $e){
            return $this->getRootURL();
        }
    }

    /**
     * Summary of is_Admin
     * @return bool
     */
    public static function is_Admin() {
        $tokn = false;
        $ssn = Session::getInstance();
        $roleCode = $ssn->rolecode;
        $userName = $ssn->username;

        // try to int-ify the roleCode if it is not an int
        if (!is_int($roleCode)) {
            $intRole = intval($roleCode);
        } else {
            $intRole = $roleCode;
        }

        if ($intRole > 0 && is_string($userName)) {

            // Authorization Bypass
            if ($intRole <= 10 || self::is_TheAdmin()) {
                $tokn = TRUE;
            }
        }
        return $tokn;
    }

    // Checks for THE admin account.
    /**
     * Summary of is_TheAdmin
     * @return bool
     */
    public static function is_TheAdmin() {
        $tokn = false;
        $ssn = Session::getInstance();
        $userName = $ssn->username;
        $id = $ssn->uid;

        if (is_string($userName)) {

            // Authorization Bypass
            if (strtolower($userName) == "admin" && $id == -1) {
                $tokn = TRUE;
            }
        }
        return $tokn;
    }


    public function getFileName() {
        return $this->fileName;
    }

    public function getPath() {
        return $this->path;
    }

    public function getRootPath() {
        return $this->rootPath;
    }

    public function getHostName() {
        return $this->hostName;
    }

    public function getHhkSiteDir() {
        return $this->hhkSiteDir;
    }

    public function getSiteURL() {
        return $this->siteURL;
    }
    public function getRootURL() {
        return $this->rootURL;
    }

}

