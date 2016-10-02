<?php

/**
 * SecurityComponent.php
 *
 * @category  Site
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2015 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
abstract class SecurityComponent {


    protected static function loadWebSite(PDO $dbh, $HTTP_Host, $doc_root) {

        $uS = Session::getInstance();
        $HTTP_Host = strtolower($HTTP_Host);
        $doc_root = strtolower($doc_root);

        // Load all the web sites.
        if (isset($uS->siteList) === FALSE) {

            $stmt = $dbh->query("Select Site_Code, Required_Group_Code, LOWER(Relative_Address) as Relative_Address, Index_Page, Default_Page, Description, LOWER(HTTP_Host) as HTTP_Host, Path_To_CSS as `Class`
                from web_sites");

            $sl = array();
            if ($stmt->rowCount() > 0) {
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
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
        if (isset($uS->webSite) && $uS->webSite["Relative_Address"] == $doc_root && $uS->webSite["HTTP_Host"] == $HTTP_Host) {

            return $uS->webSite;
        } else {

            unset($uS->webSite);
            unset($uS->webPages);

            foreach ($uS->siteList as $ws) {
                if (trim($ws["Relative_Address"]) == trim($doc_root) && trim($ws["HTTP_Host"]) == trim($HTTP_Host)) {
                    $uS->webSite = $ws;
                }
            }


            if (isset($uS->webSite)) {

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
                    page_securitygroup s ON p.idPage = s.idPage
                where
                    p.Web_Site = :wcode
                order by p.Type , p.Menu_Parent , p.Menu_Position;";

                $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
                $stmt->execute(array(":wcode" => strtolower($uS->webSite["Site_Code"])));

                if ($stmt->rowCount() > 0) {
                    $wp = array();
                    $lastId = 0;

                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {

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

        return array();
    }

    /*
     *  die_if_not_Logged_In
     *  redirects browser if not logged in.
     */

    public static function die_if_not_Logged_In($pageType, $loginPage, $pageAddress) {
        $ssn = Session::getInstance();

        if ($ssn->ssl === TRUE) {

            // Must access pages through SSL
            if (empty($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) == 'off' ) {
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

}

