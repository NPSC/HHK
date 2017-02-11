<?php

/**
 * Pages.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Pages
 *
 * @author Eric
 */
class Pages {

    public static function editPages(\PDO $dbh, $post) {

        foreach ($post['txtPageTitle'] as $$k => $v) {

            $pageId = intval(filter_var($k, FILTER_SANITIZE_STRING), 10);

            if ($pageId < 1) {
                continue;
            }

            $pageRs = new PageRS();
            $pageRs->idPage->setStoredVal($pageId);
            $rows = EditRS::select($dbh, $pageRs, array($pageRs->idPage));

            if (count($rows) != 1) {
                throw new Hk_Exception_Runtime("Page not found, id= ". $pageId);
            }

            EditRS::loadRow($rows[0], $pageRs);

            $pageRs->Title->setNewVal(filter_var($v, FILTER_SANITIZE_STRING));

            if (isset($post['cbHide'][$pageId])) {
                $pageRs->Hide->setNewVal(filter_var($post['cbHide'][$pageId], FILTER_SANITIZE_NUMBER_INT));
            }

            $pageRs->Menu_Parent->setNewVal($post['selParentId'][$pageId]);
            $pageRs->Menu_Position->setNewVal($post['txtParentPosition'][$pageId]);

            $pageRs->Updated_By->setNewVal('admin');
            $pageRs->Last_Updated->setNewVal(date('y-m-d H:i:s'));

            // uupdate page record.
            EditRS::update($dbh, $pageRs, array($pageRs->idPage));

            // Secrutiy codes.
            if (isset($post["selSecCode"][$pageId])) {

                $newGroupCodes = filter_var_array($post["selSecCode"][$pageId], FILTER_SANITIZE_STRING);

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

                    if ($c != '' && array_search($c, $existingCodes) === FALSE) {

                        // New code not found, add it
                        $psgRs = new Page_SecurityGroupRS();
                        $psgRs->Group_Code->setNewVal($c);
                        $psgRs->idPage->setNewVal($pageId);

                        EditRS::insert($dbh, $psgRs);

                    }
                }
            }
         }

    }

    public static function getPages(\PDO $dbh, $site) {

        $uS = Session::getInstance();

        $siteList = $uS->siteList;

        if (isset($siteList[$site]) == FALSE) {
            throw new Hk_Exception_Runtime("Web site code not found: " . $site);
        }



        $query = "SELECT
`p`.`idPage`,
`p`.`File_Name`,
`p`.`Login_Page_Id`,
`p`.`Title`,
`p`.`Hide`,
`p`.`Menu_Parent`,
`p`.`Menu_Position`,
s.Group_Code,
`p`.`Type`,
p.Web_Site,
gt.Description as Type_Description,
gs.Title as Security_Description
from page p left join page_securitygroup s on p.idPage = s.idPage
    left join gen_lookups gt on p.Type = gt.Code and gt.TABLE_NAME='Page_Type'
    left join w_groups gs on s.Group_Code = gs.Group_Code
    where p.Web_Site = :site
 order by p.Menu_Parent, p.Menu_Position, p.idPage;";

        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':site' => $site));
        $pageRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $parentMenus = array();

        // collect menu parents
        foreach ($pageRows as $r) {

            if ($r['Menu_Parent'] === '0' && $r['File_Name'] != 'index.php') {
                $parentMenus[$r['idPage']] = array(0=>$r['idPage'], 1=>$r['Title'], 2=>'');
            }
        }

        $parentMenus[0] = array(0=>0, 1=>'Page', 2=>'');
        $parentMenus[''] = array(0=>'', 1=>' (none) ', 2=>'');

        $tbl = new HTMLTable();

        $tbl->addHeaderTr(
                HTMLTable::makeTh('Page Id')
                .HTMLTable::makeTh('Page Type')
                .HTMLTable::makeTh('File Name')

                .HTMLTable::makeTh('Title')
                .HTMLTable::makeTh('Hide')
                .HTMLTable::makeTh('Menu Parent')
                .HTMLTable::makeTh('Menu Pos.')
                .HTMLTable::makeTh('Security Codes')
                );

        $secGroups = array();

        // Get list of security groups
        $stmtg = $dbh->query("Select Group_Code as `Code`, Title as `Description`, '' as Substitute from w_groups");
        while ($r = $stmtg->fetch(PDO::FETCH_NUM)) {
            $secGroups[$r[0]] = $r;
        }

        $pageId = 0;
        $lastRow = '';
        $auths = '';

        foreach ($pageRows as $rw) {

            if ($rw['idPage'] != $pageId) {

                $pageId = $rw['idPage'];

                // Clean up last row
                if ($lastRow != '') {
                    $lastRow .= HTMLTable::makeTd(
                    HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($secGroups, $auths, FALSE), array('name'=>'selSecCode'))
                            );
                    $tbl->addBodyTr( $lastRow, array('class'=>'trPages'));

                }

                // Set up for new page entry
                $lastRow = HTMLTable::makeTd(HTMLInput::generateMarkup($rw['idPage'], array('name'=>'txtIdPage', 'readonly'=>'readonly', 'style'=>'background-color:transparent;text-align:right;', 'size'=>'5')))
                        .HTMLTable::makeTd($rw["Type_Description"])
                        .HTMLTable::makeTd($rw["File_Name"]);


                $hideAttr = array('type'=>'checkbox', 'name'=>'cbHide[' . $rw['idPage'] . ']');

                if ($r['Hide'] > 0) {
                    $hideAttr['checked'] = 'checked';
                }

                if ($rw['Type'] == WebPageCode::Page && $rw['File_Name'] != 'index.php') {

                    $lastRow .= HTMLTable::makeTd(HTMLInput::generateMarkup($rw["Title"], array('name'=>'txtPageTitle[' . $rw['idPage'] . ']', 'size'=>'20')))
                        .HTMLTable::makeTd(HTMLInput::generateMarkup('', $hideAttr))
                        .HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($parentMenus, $rw["Menu_Parent"], FALSE), array('name'=>'selParentId[' . $rw['idPage'] . ']')))
                        .HTMLTable::makeTd(HTMLInput::generateMarkup($rw["Menu_Position"], array('name'=>'txtParentPosition[' . $rw['idPage'] . ']', 'size'=>'2')));

                } else {

                    $lastRow .= HTMLTable::makeTd($rw["Title"])
                        .HTMLTable::makeTd('')
                        .HTMLTable::makeTd('')
                        .HTMLTable::makeTd('');
                }

                $auths = array();
                $auths[$rw['Group_Code']] = $rw["Security_Description"];

            } else {
                $auths[$rw['Group_Code']]  = $rw["Security_Description"];
            }
        }

        // Clean up last row
        if ($lastRow != '') {
                    $lastRow .= HTMLTable::makeTd(
                    HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($secGroups, $auths, FALSE), array('name'=>'selSecCode'))
                            );
            $tbl->addBodyTr( $lastRow, array('class'=>'trPages'));

        }

        $mkup = HTMLContainer::generateMarkup('h2', $siteList[$site]['Description'] . ' Pages') . $tbl->generateMarkup(array('id'=>'tblPages', 'class'=>'display'));

        return array('site'=>$site, "success" => $mkup);
    }

    public static function editSite(\PDO $dbh, $fields) {
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
