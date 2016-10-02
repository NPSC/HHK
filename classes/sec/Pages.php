<?php

/**
 * Pages.php
 *
 * @category  Site
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of Pages
 *
 * @author Eric
 */
class Pages {

    public static function deletePage(PDO $dbh, $pageId) {

        if ($pageId > 0) {

            $page = new PageRS();
            $page->idPage->setStoredVal($pageId);
            EditRS::delete($dbh, $page, array($page->idPage));

            $sg = new Page_SecurityGroupRS();
            $sg->idPage->setStoredVal($pageId);
            EditRS::delete($dbh, $sg, array($sg->idPage));

            return array("success" => "Page Deleted");
        }

        return array("error" => "Bad Page Id.");
    }

    public static function editPages(PDO $dbh, $pa) {

        $pageRs = new PageRS();

        $page = filter_var($pa["pid"], FILTER_SANITIZE_STRING);

        if ($page == "new") {
            $page = '0';
        }

        $pageId = intval($page, 10);

        If ($pageId > 0) {
            $pageRs->idPage->setStoredVal($pageId);
            $rows = EditRS::select($dbh, $pageRs, array($pageRs->idPage));

            if (count($rows) != 1) {
                throw new Hk_Exception_Runtime("Page not found, id= ". $pageId);
            }

            EditRS::loadRow($rows[0], $pageRs);
        }

        if (isset($pa["inFileName"])) {
            $pageRs->File_Name->setNewVal(filter_var($pa["inFileName"], FILTER_SANITIZE_STRING));
        }

        if (isset($pa["inLogin"])) {
            $pageRs->Login_Page_Id->setNewVal(intval(filter_var($pa["inLogin"], FILTER_SANITIZE_NUMBER_INT), 10));
        }

        if (isset($pa["inTitle"])) {
            $pageRs->Title->setNewVal(filter_var($pa["inTitle"], FILTER_SANITIZE_STRING));
        }

        if (isset($pa["inPmenu"])) {
            $pageRs->Menu_Parent->setNewVal(filter_var($pa["inPmenu"], FILTER_SANITIZE_STRING));
        }

        if (isset($pa["inPosMenu"])) {
            $pageRs->Menu_Position->setNewVal(filter_var($pa["inPosMenu"], FILTER_SANITIZE_STRING));
        }

        if (isset($pa["selType"])) {
            $pageRs->Type->setNewVal(filter_var($pa["selType"], FILTER_SANITIZE_STRING));
        }

        if (isset($pa["website"])) {
            $pageRs->Web_Site->setNewVal(filter_var($pa["website"], FILTER_SANITIZE_STRING));
        }

        if (isset($pa["selSecCode"])) {
            $newGroupCodes = filter_var_array($pa["selSecCode"], FILTER_SANITIZE_STRING);
        }


        $pageRs->Updated_By->setNewVal('admin');
        $pageRs->Last_Updated->setNewVal(date('y-m-d H:i:s'));

        $events = array();


        // NEw page?
        if ($pageId == 0) {
            // Insert
            $pageId = EditRS::insert($dbh, $pageRs);

            foreach ($newGroupCodes as $c) {

                if ($c != '') {

                    $psgRs = new Page_SecurityGroupRS();
                    $psgRs->Group_Code->setNewVal($c);
                    $psgRs->idPage->setNewVal($pageId);

                    EditRS::insert($dbh, $psgRs);
                }
            }

            $events = array("success" => "Added new Page. Id = " . $pageId);

        } else if ($pageId > 0) {
            // uupdate
            EditRS::update($dbh, $pageRs, array($pageRs->idPage));

            // Get existing security group codes
            $psgRs = new Page_SecurityGroupRS();
            $psgRs->idPage->setStoredVal($pageId);
            $psgRows = EditRS::select($dbh, $psgRs, array($psgRs->idPage));

            $existingCodes = array();

            // Remove any codes from the new codes array
            foreach ($psgRows as $r) {

                $oldpsgRs = new Page_SecurityGroupRS();
                EditRS::loadRow($r, $oldpsgRs);

                // Delete missing codes
                if (array_search($oldpsgRs->Group_Code->getStoredVal(), $newGroupCodes) === FALSE) {
                    // Delete this security group code.
                    EditRS::delete($dbh, $oldpsgRs, array($oldpsgRs->Group_Code, $oldpsgRs->idPage));
                } else {
                    $existingCodes[] = $oldpsgRs->Group_Code->getStoredVal();
                }
            }

            // Add any new codes.
            foreach ($newGroupCodes as $c) {

                if (array_search($c, $existingCodes) === FALSE) {

                    if ($c != '') {

                        // New code not found, add it
                        $psgRs = new Page_SecurityGroupRS();
                        $psgRs->Group_Code->setNewVal($c);
                        $psgRs->idPage->setNewVal($pageId);

                        EditRS::insert($dbh, $psgRs);
                    }
                }
            }

            $events = array("success" => "Updated Page. Id = " . $pageId);
         }

        return $events;
    }

    public static function getPages(PDO $dbh, $site) {
        $query = "SELECT
`p`.`idPage`,
`p`.`File_Name`,
`p`.`Login_Page_Id`,
`p`.`Title`,
`p`.`Menu_Parent`,
`p`.`Menu_Position`,
s.Group_Code as SecCode,
`p`.`Type`, p.Web_Site,
gt.Description as Type_Description,
gs.Title as Security_Description
from page p left join page_securitygroup s on p.idPage = s.idPage
    left join gen_lookups gt on p.Type = gt.Code and gt.TABLE_NAME='Page_Type'
    left join w_groups gs on s.Group_Code = gs.Group_Code
    where p.Web_Site = :site
 order by p.Menu_Parent, p.Menu_Position, p.idPage;";

        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':site' => $site));
        $pageRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tbl = new HTMLTable();
        //$markup = "<table  id='tblPages' class='display'><thead><tr>";
        $tbl->addHeaderTr(
                HTMLTable::makeTh('Page Id')
                .HTMLTable::makeTh('File Name')
                .HTMLTable::makeTh('Login Page')
                .HTMLTable::makeTh('Title')
                .HTMLTable::makeTh('Menu Parent')
                .HTMLTable::makeTh('Menu Pos.')
                .HTMLTable::makeTh('Page Type')
                .HTMLTable::makeTh('Security Codes')
                );



        //$markup .= "</tr></thead><tbody>";
        $menuParent = array();
        $pageId = 0;
        $lastRow = '';
        $markup = '';

        foreach ($pageRows as $rw) {

            if ($rw['idPage'] != $pageId) {
                $pageId = $rw['idPage'];

                // Clean up last row
                if ($lastRow != '') {
                    $tbl->addBodyTr( $lastRow . "</td>", array('class'=>'trPages'));

                    if ($rw["Menu_Parent"] == "0") {
                        $menuParent[$rw['idPage']] = $rw;
                    }
                }

                //$lastRow = "<tr  class='trPages' >";
                // page ID
                $lastRow = "<td><input type='button' id='b_" . $rw['idPage'] . "' value='" . $rw['idPage'] . "' onclick='pageEditButton(" . $rw['idPage'] . ")' title='Press to Edit This Page' /></td>";
                $lastRow .= "<td>" . $rw["File_Name"] . "</td>";
                $lastRow .= "<td>" . $rw["Login_Page_Id"] . "</td>";

                $lastRow .= "<td>" . $rw["Title"] . "</td>";
                $lastRow .= "<td>" . $rw["Menu_Parent"] . "</td>";
                $lastRow .= "<td>" . $rw["Menu_Position"] . "</td>";
                $lastRow .= "<td>" . $rw["Type_Description"] . "</td>";
                $lastRow .= "<td>" . $rw["Security_Description"];

            } else {
                $lastRow .= "," . $rw["Security_Description"];
            }
        }

        // Clean up last row
        if ($lastRow != '') {
            $tbl->addBodyTr( $lastRow . "</td>", array('class'=>'trPages'));

            if ($rw["Menu_Parent"] == "0") {
                $menuParent[$rw['idPage']] = $rw;
            }
        }


        return array("success" => $tbl->generateMarkup(array('id'=>'tblPages', 'class'=>'display')), "parent" => $menuParent);
    }

    public static function editSite(PDO $dbh, $fields) {
        $siteCode = '';

        if (isset($fields["inSiteCode"])) {
            $siteCode = filter_var($fields["inSiteCode"], FILTER_SANITIZE_STRING);
        } else {
            return array("error" => "Bad Site Code. ");
        }

        $siteRs = new Web_SitesRS();
        $siteRs->Site_Code->setStoredVal($siteCode);
        $siteRows = EditRS::select($dbh, $siteRs, array($siteRs->Site_Code));

        if (count($siteRows) != 1) {
            return array("error" => "Site Code not found. ");
        }

        EditRS::loadRow($siteRows[0], $siteRs);

        if (isset($fields["inDescription"])) {
            $siteRs->Description->setNewVal(filter_var($fields["inDescription"], FILTER_SANITIZE_STRING));
        }

        if (isset($fields["inHostAddr"])) {
            $siteRs->HTTP_Host->setNewVal(filter_var($fields["inHostAddr"], FILTER_SANITIZE_STRING));
        }

        if (isset($fields["inRelAddr"])) {
            $siteRs->Relative_Address->setNewVal(filter_var($fields["inRelAddr"], FILTER_SANITIZE_STRING));
        }

        if (isset($fields["inCss"])) {
            $siteRs->Path_To_CSS->setNewVal(filter_var($fields["inCss"], FILTER_SANITIZE_STRING));
        }

        if (isset($fields["inJs"])) {
            $siteRs->Path_To_JS->setNewVal(filter_var($fields["inJs"], FILTER_SANITIZE_STRING));
        }

        if (isset($fields["inDefault"])) {
            $siteRs->Default_Page->setNewVal(filter_var($fields["inDefault"], FILTER_SANITIZE_STRING));
        }


        if (isset($fields["inIndex"])) {
            $siteRs->Index_Page->setNewVal(filter_var($fields["inIndex"], FILTER_SANITIZE_STRING));
        }

        if (isset($fields["siteSecCode"])) {
            $codes = filter_var_array($fields["siteSecCode"], FILTER_SANITIZE_STRING);
            $codeCDS = '';
            foreach ($codes as $c) {
                if ($codeCDS == '') {
                    $codeCDS = $c;
                } else {
                    $codeCDS .= ',' . $c;
                }
            }
            $siteRs->Required_Group_Code->setNewVal($codeCDS);
        }

        EditRS::update($dbh, $siteRs, array($siteRs->Site_Code));

        $events = array("success" => "Updated Site Code = " . $siteCode);


        return $events;
    }

}

?>
