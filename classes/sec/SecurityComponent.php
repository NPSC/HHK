<?php
namespace HHK\sec;

use HHK\Common;
use HHK\Exception\AuthException;
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
     * @param bool $isLogin write log and throw exception if user flow is login
     * @param string|null $webSite when given, checks $name as a page on this specific site via a
     *  direct DB lookup instead of $uS->webPages - which SitePage only ever populates for the site
     *  of the page currently being served, so a same-signature check for a page on some other site
     *  would otherwise always silently return false. Costs a query; omit for the common same-site
     *  case, where the request-scoped cache already has the answer for free.
     * @param \PDO|null $dbh reused for both the $webSite lookup and (if $isLogin) the log insert;
     *  a connection is opened via Common::initPDO() if not given.
     * @return bool
     * @throws AuthException
     */
    public static function is_Authorized($name, $isLogin = false, ?string $webSite = null, ?\PDO $dbh = null) {

        if (self::is_Admin()) {
            return TRUE;
        }

        $uS = Session::getInstance();
        $pageCode = array();
        $pageTitle = '';

        //parse url before checking authorization
        $parsedName = parse_url($name);
        if($parsedName !== FALSE && isset($parsedName["path"]) && $parsedName["path"] != ''){
            $name = $parsedName["path"];
        }

        if ($webSite !== null) {

            $dbh = $dbh ?? Common::initPDO(true);
            $stmt = $dbh->prepare(
                "SELECT `p`.`Title`, `s`.`Group_Code` FROM `page` `p`
                    LEFT JOIN `page_securitygroup` `s` ON `p`.`idPage` = `s`.`idPage`
                WHERE `p`.`File_Name` = :fileName AND `p`.`Web_Site` = :webSite AND `p`.`Hide` = 0;"
            );
            $stmt->execute([':fileName' => $name, ':webSite' => $webSite]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) == 0) {
                return FALSE;
            }

            $pageCode = array_column($rows, 'Group_Code');
            $pageTitle = $rows[0]['Title'];

        } else if ($name != "" && isset($uS->webPages[$name]) && !is_null($uS->webPages[$name])) {

            $r = $uS->webPages[$name];
            $pageCode = $r["Codes"];
            $pageTitle = $r["Title"];

        } else {
            return FALSE;
        }

        // check authorization codes.
        $isAuthorized = self::does_User_Code_Match($pageCode);
        $isIpRestricted = self::does_User_Code_Match($pageCode, true);

        if($isAuthorized){
            return true;
        }else if ($isIpRestricted){
            $errorMsg = "Unauthorized for page:" . ($pageTitle != '' ? $pageTitle : $name) . " at this location";

            if($isLogin){
                $dbh = $dbh ?? Common::initPDO(true);
                UserClass::insertUserLog($dbh, $errorMsg, ($uS->username != "" ? $uS->username : "<empty>"));
                throw new AuthException($errorMsg);
            }
            return false;
        }else{
            $errorMsg = "Unauthorized for page: " . ($pageTitle != '' ? $pageTitle : $name);

            if($isLogin){
                $dbh = $dbh ?? Common::initPDO(true);
                UserClass::insertUserLog($dbh, $errorMsg, ($uS->username != "" ? $uS->username : "<empty>"));
                throw new AuthException($errorMsg);
            }
            return false;
        }

    }

    /**
     * Resolves a stored per-user default page (a bare File_Name that could belong to more than
     * one site - WebUser's page picker lets an account be given a default from either Admin or
     * House) into a browsable, site-relative path, but only if the current user is actually
     * authorized for it. Site codes are tried in the order given; the first one the page is both
     * found in and authorized for wins.
     * @param \PDO $dbh
     * @param string $fileName
     * @param string[] $siteCodes candidate site codes, in priority order
     * @return string relative path (e.g. "house/register.php"), or "" if $fileName isn't
     *  authorized in any of the given sites.
     */
    public static function resolveAuthorizedPage(\PDO $dbh, string $fileName, array $siteCodes): string {

        $uS = Session::getInstance();
        $siteList = $uS->siteList ?? [];

        foreach ($siteCodes as $siteCode) {

            if (!isset($siteList[$siteCode])) {
                continue;
            }

            if (self::is_Authorized($fileName, false, $siteCode, $dbh)) {
                return $siteList[$siteCode]['Relative_Address'] . $fileName;
            }
        }

        return '';
    }

    /**
     * Summary of rerouteIfNotLoggedIn
     * @param mixed $pageType
     * @param mixed $loginPage
     * @return void
     */
    public function rerouteIfNotLoggedIn($pageType, $loginPage) {

        $ssn = Session::getInstance();

        if (isset($ssn->logged) == FALSE || $ssn->logged == FALSE || (isset($ssn->userAgent) && $ssn->userAgent != filter_input(INPUT_SERVER, "HTTP_USER_AGENT", FILTER_SANITIZE_FULL_SPECIAL_CHARS) )) {

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

        if (isset($ssn->logged) == FALSE || $ssn->logged == FALSE || (isset($ssn->userAgent) && $ssn->userAgent != filter_input(INPUT_SERVER, "HTTP_USER_AGENT", FILTER_SANITIZE_FULL_SPECIAL_CHARS) )) {

            $ssn->destroy(TRUE);

            if ($pageType != WebPageCode::Page) {

                echo json_encode(array("error" => "Unauthorized.", 'gotopage' => $loginPage));

            } else {

                if ($this->fileName != '') {
                    //build redirect path
                    $xf = $this->fileName;
                    if(count($_GET) > 0){
                        $xf .= "?" . http_build_query($_GET);
                    }
                    header("Location: " . $loginPage . "?xf=" . urlencode($xf));
                } else {
                    header("Location: " . $loginPage);
                }
            }

            exit();
        }
    }

    /**
     * Check if user is authorized for a set of page codes or if the user is IP Restricted
     * @param array $pageCodes
     * @param bool $isIpRestricted causes function to return true if the user is IP restricted for the given page(s)
     * @return bool
     */
    public static function does_User_Code_Match(array $pageCodes, bool $isIpRestricted = false) {

        $ssn = Session::getInstance();

        if($isIpRestricted){
            $userCodes = $ssn->groupcodesIpRestricted;
        } else {
            $userCodes = $ssn->groupcodes;
        }

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
     * Picks a landing page for a just-authenticated user out of all configured sites
     * (the shared login lives at the site root, so it can't rely on a single site's
     * own page list the way a site-scoped login page can). House takes priority;
     * any other non-root site the user is authorized for is used as a fallback.
     * Site membership (web_sites.Required_Group_Code) and the specific Default_Page's
     * own page-level permissions are two independent checks - a user can satisfy the
     * former and still be blocked by the latter, so both are checked before a site's
     * Default_Page is returned; otherwise the user would land past login with no
     * error and then hit a bare "Unauthorized" on the page itself.
     * @param \PDO $dbh
     * @return string relative path (e.g. "house/register.php"), or "" if the user
     *  isn't authorized for any site's Default_Page.
     */
    public static function getAuthorizedDefaultPage(\PDO $dbh): string {

        $uS = Session::getInstance();
        $siteList = $uS->siteList ?? [];
        $siteCodes = array_unique(array_merge(['h'], array_keys($siteList)));

        foreach ($siteCodes as $siteCode) {

            if ($siteCode == 'r' || !isset($siteList[$siteCode])) {
                continue;
            }

            $site = $siteList[$siteCode];

            if (self::is_Admin() || self::does_User_Code_Match($site['Groups'] ?? [])) {

                if (self::is_Authorized($site['Default_Page'], false, $siteCode, $dbh)) {
                    return $site['Relative_Address'] . $site['Default_Page'];
                }
            }
        }

        return '';
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
     * @throws RuntimeException
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
     * Does the user have the Admin role or is the THE Admin user.
     * @return bool
     */
    public static function is_Admin(): bool {
        $tokn = false;
        $ssn = Session::getInstance();
        $roleCode = intval($ssn->rolecode);
        $userName = $ssn->username;

        if ($roleCode > 0 && is_string($userName)) {

            // Authorization Bypass
            if ($roleCode <= 10 || self::is_TheAdmin()) {
                return true;
            }
        }
        return false;
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

