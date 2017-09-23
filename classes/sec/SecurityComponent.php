<?php
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

    public function __construct($isRoot = FALSE) {

        $this->defineThisURL($isRoot);
    }

    protected static function loadWebSite(\PDO $dbh, $host, $root) {

        $uS = Session::getInstance();
        $HTTP_Host = strtolower($host);
        $doc_root = strtolower($root);

        // Load all the web sites.
        if (isset($uS->siteList) === FALSE) {

            $stmt = $dbh->query("Select Site_Code, Required_Group_Code, LOWER(Relative_Address) as Relative_Address, Index_Page, Default_Page, Description, LOWER(HTTP_Host) as HTTP_Host, Path_To_CSS as `Class`
                from web_sites");

            $sl = array();

            if ($stmt->rowCount() > 0) {

                while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $site = array(
                        "Site_Code" => $r["Site_Code"],
                        "Relative_Address" => $r["Relative_Address"],
                        "Index_Page" => $r["Index_Page"],
                        "Default_Page" => $r["Default_Page"],
                        "Description" => $r["Description"],
                        "HTTP_Host" => $r["HTTP_Host"],
                        "Class" => $r["Class"]
                    );

                    $codes = explode(',', $r["Required_Group_Code"]);

                    foreach ($codes as $c) {

                        $c = trim($c);

                        if ($c != '') {
                            $site['Groups'][] = $c;
                        }
                    }

                    $sl[$r["Site_Code"]] = $site;
                }

                $uS->siteList = $sl;

            } else {
                throw new Hk_Exception_Runtime("web_sites records not found.");
            }
        }

        // Is our web site page list loaded?
        if (isset($uS->webSite) && $uS->webSite["Relative_Address"] == $doc_root) {  // && $uS->webSite["HTTP_Host"] == $HTTP_Host) {

            return $uS->webSite;
        }

        // Load Site
        unset($uS->webSite);
        unset($uS->webPages);

        foreach ($uS->siteList as $ws) {

            if (trim($ws["Relative_Address"]) == trim($doc_root)) {  // && trim($ws["HTTP_Host"]) == trim($HTTP_Host)) {
                $uS->webSite = $ws;
            }
        }


        if (isset($uS->webSite)) {

            $wsCode = strtolower($uS->webSite["Site_Code"]);
            $where = " where p.Web_Site = '$wsCode' and p.Hide = 0 ";
            $orderBy = " order by p.Type, p.Menu_Parent, p.Menu_Position";

            // Get list of pages
            $query = "select
                p.idPage as idPage,
                p.File_Name,
                p.Title as Title,
                p.Type as Type,
                p.Menu_Parent,
                p.Menu_Position,
                case
                    when p.Login_Page_Id > 0 then p1.File_Name
                    else ''
                end as Login_Page,
                ifnull(s.Group_Code, '') as Group_Code
            from
                page p
                    left join
                page p1 ON p.Login_Page_Id = p1.idPage
                    left join
                page_securitygroup s ON p.idPage = s.idPage";

            try {
                $stmt = $dbh->query($query . $where . $orderBy);
            } catch (PDOException $pex) {
                $where = " where p.Web_Site = '$wsCode' ";
                $stmt = $dbh->query($query . $where . $orderBy);
            }

            if ($stmt->rowCount() > 0) {
                $wp = array();
                $lastId = 0;

                while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

                    if ($lastId == $r['idPage']) {

                        $wp[$r['File_Name']]['Codes'][] = $r['Group_Code'];

                    } else {

                        $wp[$r['File_Name']] = array(
                            'idPage' => $r['idPage'],
                            'Title' => $r['Title'],
                            'Type' => $r['Type'],
                            'Parent' => $r['Menu_Parent'],
                            'Position' => $r['Menu_Position'],
                            'Login' => $r['Login_Page'],
                            'Codes' => array($r['Group_Code'])
                        );
                    }

                    $lastId = $r['idPage'];
                }

                $uS->webPages = $wp;
            } else {
                throw new Hk_Exception_Runtime("Web pages list not found.");
            }
        } else {

            throw new Hk_Exception_Runtime("web_sites not found.  Host: " . $HTTP_Host . "  Doc Root: " . $doc_root);
        }

        return $uS->webSite;

    }

    public static function is_Authorized($name) {

        if (self::is_Admin()) {
            return TRUE;
        }

        $uS = Session::getInstance();

        $pageCode = array();
        // try reading the page table
        if ($name != "" && isset($uS->webPages[$name])) {
            $r = $uS->webPages[$name];

            if (!is_null($r)) {
                $pageCode = $r["Codes"];
            }
        }

        // check authorization codes.
        $tokn = self::does_User_Code_Match($pageCode);

        return $tokn;
    }

    public static function die_if_not_Logged_In($pageType, $loginPage, $pageAddress) {
        $ssn = Session::getInstance();

        if ($ssn->ssl === TRUE) {

            $serverHTTPS = filter_input(INPUT_SERVER, "HTTPS", FILTER_SANITIZE_STRING);

            // Must access pages through SSL
            if (empty($serverHTTPS) || strtolower($serverHTTPS) == 'off' ) {
                // non-SSL access.
               header("Location: " . $ssn->resourceURL);
            }
        }


        if (!$ssn->logged) {

            $ssn->destroy();

            if ($pageType != WebPageCode::Page) {

                echo json_encode(array("error" => "Unauthorized", 'gotopage' => $loginPage));

            } else {

                if ($pageAddress != '') {
                    header("Location: " . $loginPage . "?xf=" . $pageAddress);
                } else {
                    header("Location: " . $loginPage);
                }
            }

            exit();
        }
    }

    protected static function does_User_Code_Match(array $pageCodes) {
        $tokn = FALSE;
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

        return $tokn;
    }

    public static function isHTTPS() {

        $serverHTTPS = filter_input(INPUT_SERVER, "HTTPS", FILTER_SANITIZE_STRING);

        if (empty($serverHTTPS) || strtolower($serverHTTPS) == 'off' ) {
            return FALSE;
        }

        return TRUE;
    }

    public function defineThisURL($isRoot = FALSE) {

        $scriptName = filter_input(INPUT_SERVER, "SCRIPT_NAME", FILTER_SANITIZE_STRING);
        $serverName = filter_input(INPUT_SERVER, "SERVER_NAME", FILTER_SANITIZE_URL);

        if (is_null($scriptName) || $scriptName === FALSE) {
            throw new Hk_Exception_Runtime('Script name not set.');
        }

        if (is_null($serverName) || $serverName === FALSE) {
            throw new Hk_Exception_Runtime('Server name not set.');
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

        if ($isRoot === FALSE && count($parts) >= 1) {

            $this->hhkSiteDir = $parts[count($parts) - 1] . '/';
            unset($parts[count($parts) - 1]);

            // THe root path is what's left.
            $this->rootPath = implode("/", $parts) . '/';


        } else {
            $this->hhkSiteDir = '';
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
    public static function is_TheAdmin() {
        $tokn = false;
        $ssn = Session::getInstance();
        $userName = $ssn->username;

        if (is_string($userName)) {

            // Authorization Bypass
            if (strtolower($userName) == "admin") {
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

    public function getSiteURL() {
        return $this->siteURL;
    }
    public function getRootURL() {
        return $this->rootURL;
    }

}

