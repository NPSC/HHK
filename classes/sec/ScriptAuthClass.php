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

        // try reading the web site table
        try {

            $site = self::loadWebSite($dbh, $this->getHostName(), $this->getPath());

        } catch (Hk_Exception $hex) {

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

            if (isset($uS->webPages[$this->getFileName()])) {

                $page = $uS->webPages[$this->getFileName()];

                if (!is_null($page)) {

                    $this->pageCodes = $page["Codes"];
                    $this->pageTitle = $page["Title"];
                    $this->loginPage = $page["Login"];
                    $this->pageType = $page["Type"];
                }
            }
        }
    }

    public function Authorize_Or_Die() {

        $this->die_if_not_Logged_In($this->get_Page_Type(), $this->get_Login_Page());

        $tokn = self::is_Admin();

        if ($tokn == FALSE) {
            // Not the admin, so check authorization codes.
            $tokn = self::does_User_Code_Match($this->pageCodes);

            if ($tokn == FALSE) {
                if ($this->get_Page_Type() == "p") {
                    include("../errorPages/forbid.html");
                } else if ($this->get_Page_Type() == "s") {
                    $rtn = array("error" => "Unauthorized");
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

    public function generateMenu($pageHeader) {
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


        $markup = "<header id='global-nav'>" . self::getSiteIcons($uS->ssl, $uS->siteList, $uS->tutURL, $uS->HufURL);
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

        return $markup;
    }

    protected static function getSiteIcons($isSSL, $siteList, $tutorialURL, $hufURL) {

        $mu = "<ul id='ulIcons' style='float:left;padding-top:5px;' class='ui-widget ui-helper-clearfix'>";
        $siteCount = 0;
        $proto = 'http://';
        if ($isSSL) {
            $proto = 'https://';
        }

        $siteMu = '';
        foreach ($siteList as $r) {

            if ($r["Site_Code"] != "r" && (self::is_Admin() || self::does_User_Code_Match($r["Groups"]))) {
                $siteCount++;
                // put in the site list.
                $siteMu .= "<li class='ui-widget-header ui-corner-all' title='" . $r["Description"] . "'>"
                      . "<a  href='" . $proto . $r["HTTP_Host"] . $r["Relative_Address"] . $r["Default_Page"] . "'>"
                        . "<span class='" . $r["Class"] . "' ></span></a>"
                      . "</li>";
            }
        }

        if ($siteCount > 0) {
             $mu .= $siteMu;
        }

        // Tutorial site
        if ($tutorialURL != '') {
            $mu .= "<li class='ui-widget-header ui-corner-all' title='Tutorial Site'>"
                  . "<a href='" . $tutorialURL . "' target='blank'>"
                  . "<span class='ui-icon ui-icon-video' ></span></a>"
                  . "</li>";
        }

        // HHK Users Forum
        if ($hufURL != '') {
            $mu .= "<li class='ui-widget-header ui-corner-all' title='HHK Users Forum' >"
                  . "<a href='" . $hufURL . "' target='blank'>"
                  . "<span class='ui-icon ui-icon-flag' ></span></a>"
                  . "</li>";
        }

        $mu .= "</ul>";

        return HTMLContainer::generateMarkup('div', $mu, array('id'=>'divNavIcons'));

    }

}
