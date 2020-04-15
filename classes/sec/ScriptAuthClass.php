<?php
/**
 * ScriptAuthClass.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class ScriptAuthClass extends SecurityComponent {

    private $siteCode = "";
    private $indexPage = "";
    private $pageCodes = array();
    private $pageTitle = "";
    private $loginPage = "";
    private $defaultPage = "";
    private $pageType = "";


    function __construct(\PDO $dbh) {

        parent::__construct();
        $uS = Session::getInstance();

        SysConfig::getCategory($dbh, $uS, "'a'", 'sys_config');


        // try reading the web site table
        try {

            $site = $this->loadWebSite($dbh);

        } catch (Hk_Exception_Runtime $hex) {

            $uS->destroy(TRUE);
            exit("error: ".$hex->getMessage());
        }

        if (!is_null($site)) {
            $this->siteCode = $site["Site_Code"];
            $this->indexPage = $site["Index_Page"];
            $this->defaultPage = $site["Default_Page"];
        }

        // try reading the page table
        if ($this->siteCode != "" && $this->getFileName() != "") {

            if (isset($uS->webPages[$this->getFileName()]) && !is_null($uS->webPages[$this->getFileName()])) {

                $page = $uS->webPages[$this->getFileName()];

                $this->pageCodes = $page["Codes"];
                $this->pageTitle = $page["Title"];
                $this->loginPage = $page["Login"];
                $this->pageType = $page["Type"];

            } else {
                exit('Page file name not found in database. ');
            }
        } else {
            exit('Web Site Code or page file name not defined. ');
        }
    }

    protected function loadWebSite(\PDO $dbh) {

        $uS = Session::getInstance();


        // Load all the web sites.
        if (isset($uS->siteList) === FALSE) {

            $stmt = $dbh->query("Select Site_Code, Required_Group_Code, Relative_Address, Index_Page, Default_Page, Description, Path_To_CSS as `Class`
                from web_sites where Relative_Address != '';");

            $sl = array();

            if ($stmt->rowCount() > 0) {

                while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

                    if ($r['Site_Code'] == WebSiteCode::Volunteer && !$uS->Volunteers) {
                        continue;
                    }

                    $site = array(
                        "Site_Code" => $r["Site_Code"],
                        "Relative_Address" => $r['Relative_Address'],
                        "Index_Page" => $r["Index_Page"],
                        "Default_Page" => $r["Default_Page"],
                        "Description" => $r["Description"],
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
        if (isset($uS->webSite) && $uS->webSite["Relative_Address"] == $this->getHhkSiteDir()) {

            return $uS->webSite;
        }

        // Load Site
        unset($uS->webSite);
        unset($uS->webPages);

        foreach ($uS->siteList as $ws) {

            if ($ws["Relative_Address"] == $this->getHhkSiteDir()) {
                $uS->webSite = $ws;
                break;
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

            throw new Hk_Exception_Runtime("web_sites not found.  Host: " . $this->getRootURL() . "  Doc Root: " . $this->getHhkSiteDir());
        }

        return $uS->webSite;

    }


    public function Authorize_Or_Die() {

        $this->die_if_not_Logged_In($this->get_Page_Type(), $this->get_Login_Page());

        if (self::is_Admin() === FALSE) {
            // Not the admin, so check authorization codes.

            if (self::does_User_Code_Match($this->pageCodes) === FALSE) {

                if ($this->get_Page_Type() == "p") {

                    echo("Unauthorized");

                } else if ($this->get_Page_Type() == "s") {

                    $rtn = array("error" => "Unauthorized-");
                    $uS = Session::getInstance();
                    $uS->destroy(TRUE);
                    echo json_encode($rtn);

                } else {
                    echo("No Such Page.  <a href='" . $this->indexPage . "'>Continue</a>");
                }

                exit();
            }
        }
    }

    public function get_Login_Page() {
        if ($this->loginPage != '') {
            return $this->loginPage;
        } else {
            return 'index.php';
        }
    }

    public function get_Default_Page() {
        return $this->defaultPage;
    }

    public function get_Site_Code() {
        return $this->siteCode;
    }

    public function get_Page_Title() {
        return $this->pageTitle;
    }

    public function get_Page_Type() {
        return $this->pageType;
    }

    public function generateMenu($pageHeader, $dbh = false) {
        // only generate menu for pages, not services or components
        if ($this->get_Page_Type() != WebPageCode::Page) {
            return '';
        }

        $menu = array();
        $uS = Session::getInstance();
        $pageAnchors = array();

        foreach ($uS->webPages as $fn => $r) {

            if ($r['Type'] != WebPageCode::Page) {
                continue;
            }

            if (self::is_Admin() || self::does_User_Code_Match($r['Codes'])) {
                // remove leading underline char - special code for level 1 page names.
                $fn = str_replace('_', '', $fn);

                // Get my data
                if (self::is_TheAdmin()) {
                    // Show auth codes for admin account for sanity check
                    $pageAnchors[$r['idPage']]['Title'] = $r['Title'] . " (" . $r['Codes'][0] . ")";
                    $pageAnchors[$r['idPage']]['File_Name'] = $fn;
                } else {
                    $pageAnchors[$r['idPage']]['Title'] = $r['Title'];
                    $pageAnchors[$r['idPage']]['File_Name'] = $fn;
                }

                $menu[$r['Parent']][$r['Position']] = $r['idPage'];
            }
        }


        $markup = "<header id='global-nav'>" . $this->getSiteIcons($uS->siteList);
        $markup .= "<div id='global-title'>$pageHeader</div><div id='navContainer'><div id='nav'>";

        // process
        foreach ($menu["0"] as $item) {

            $markup .= "<ul class='links'>";

            if (isset($menu[$item])) {

                // children to capture
                $chd = '';
                $cls = '';

                foreach ($menu[$item] as $k => $chld) {

                    if (strtolower($k) != "none") {

                        $myClass = '';

                        if ($pageAnchors[$chld]["File_Name"] == $this->getFileName()) {
                            $myClass = " class='hhk-myPage' ";
                            $cls = ' hhk-myMenuTop';
                        }

                        $chd .= "<li$myClass><a href='".$pageAnchors[$chld]["File_Name"]."'>" . $pageAnchors[$chld]["Title"] . "</a></li>";
                    }
                }

                if ($chd != '') {
                    $chd = "<ul>" . $chd . "</ul>";
                }

                $markup .= "<li class='dropdown$cls'><a href='#'>" . $pageAnchors[$item]["Title"] . "</a>" . $chd;

            } else {
                $clss = '';
                if ($pageAnchors[$item]["File_Name"] == $this->getFileName()) {
                    $clss = " class='hhk-myMenuTop' ";
                }
                $markup .= "<li$clss><a href='".$pageAnchors[$item]["File_Name"]."'>" . $pageAnchors[$item]["Title"] . "</a>";
            }
            $markup .= "</li></ul>";
        }

        $disclaimer = '';
        if ($uS->mode != Mode::Live) {
            $disclaimer = HTMLContainer::generateMarkup('span', 'Demo Site - Do not use actual guest or patient names!', array('style'=>'font-weight:bold;margin-right:.9em;'));
        }
        $markup .= "</div></div></header>
            <div id='version'>$disclaimer User:" . $uS->username . ", Build:" . $uS->ver . "</div>";
        
        // instantiate a ChallengeGenerator object
        try {
            $chlgen = new ChallengeGenerator();
            $challengeVar = $chlgen->getChallengeVar("challenge");
        } catch (Exception $e) {
            //
        }
        
        //add user settings modal
        if($dbh && isset($challengeVar) && isset($uS)){
            $markup .= UserClass::createUserSettingsMarkup($dbh);
            $markup .= '<input  type="hidden" id="challVar" value="' . $challengeVar . '" />';
            $markup .= '<input  type="hidden" id="isPassExpired" value="' . UserClass::isPassExpired($dbh, $uS) . '" />';
        }
        
        return $markup;
    }

    protected function getSiteIcons($siteList) {

        $mu = "<ul id='ulIcons' style='float:left;padding-top:5px;' class='ui-widget ui-helper-clearfix hhk-ui-icons'>";
        $siteCount = 0;
        $siteMu = '';

        $config = new Config_Lite(ciCFG_FILE);
        $tutorialURL = $config->getString('site', 'Tutorial_URL', '');
        $hufURL = $config->getString('site', 'HUF_URL', '');

        foreach ($siteList as $r) {

            if ($r["Site_Code"] != "r" && (self::is_Admin() || self::does_User_Code_Match($r["Groups"]))) {
                $siteCount++;
                // put in the site list.
                $siteMu .= "<a  href='" . $this->getRootURL() . $r["Relative_Address"] . $r["Default_Page"] . "'>"
                      . "<li class='ui-widget-header ui-corner-all' title='" . $r["Description"] . "'>"
                        . "<span class='" . $r["Class"] . "' ></span>"
                      . "</li></a>";
            }
        }

        if ($siteCount > 0) {
             $mu .= $siteMu;
        }

        // Tutorial site
        if ($tutorialURL != '') {
            $mu .= "<a href='" . $tutorialURL . "' target='blank'>"
                  . "<li class='ui-widget-header ui-corner-all' title='Tutorial Site'>"
                  . "<span class='ui-icon ui-icon-video' ></span>"
                  . "</li></a>";
        }

        // HHK Users Forum
        if ($hufURL != '') {
            $mu .= "<a href='" . $hufURL . "' target='blank'>"
                  . "<li class='ui-widget-header ui-corner-all' title='HHK Users Forum' >"
                  . "<span class='ui-icon ui-icon-flag' ></span>"
                  . "</li></a>";
        }

        $mu .= "</ul>";

        return HTMLContainer::generateMarkup('div', $mu, array('id'=>'divNavIcons'));

    }

}
