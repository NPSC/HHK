<?php
namespace HHK\sec;

use HHK\Exception\{RuntimeException, DuplicateException, ValidationException};
use HHK\SysConst\WebPageCode;
use HHK\Tables\EditRS;
use HHK\Tables\WebSec\{PageRS, Page_SecurityGroupRS, Web_SitesRS};
use HHK\HTMLControls\{HTMLTable, HTMLInput, HTMLSelector, HTMLContainer};
use HHK\Common;

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

    protected string $pageErrors = '';

    public function editPages(\PDO $dbh) {

        $args = [
            'hdnWebSite' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'hdnloginPageId' => FILTER_SANITIZE_NUMBER_INT,
            'txtIdPage' => [
                'filter'=> FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'flags'=> FILTER_REQUIRE_ARRAY
            ],
            'selPageType' => [
                'filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'flags' => FILTER_REQUIRE_ARRAY
            ],
            'txtFileName' => [
                'filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'flags' => FILTER_REQUIRE_ARRAY
            ],
            'txtPageTitle' => [
                'filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'flags' => FILTER_REQUIRE_ARRAY
            ],
            'cbHide' => [
                'filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'flags' => FILTER_REQUIRE_ARRAY
            ],
            'selParentId' => [
                'filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'flags' => FILTER_REQUIRE_ARRAY
            ],
            'txtParentPosition' => [
                'filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'flags' => FILTER_REQUIRE_ARRAY
            ],
            "selSecCode" => [
                'filter'=> FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'flags'=> FILTER_REQUIRE_ARRAY
            ],
        ];

        $post = filter_input_array(INPUT_POST, $args);

        $uS = Session::getInstance();
        $website = '';
        $loginPageId = 0;
        $secGroups = array();
        $pageErrors = "";

        // Get list of security groups
        $stmtg = $dbh->query("Select Group_Code, Title, '' as Substitute from w_groups");
        while ($r = $stmtg->fetch(\PDO::FETCH_NUM)) {
            $secGroups[$r[0]] = $r;
        }

        $siteList = $uS->siteList;

        if (isset($post['hdnWebSite'])) {
            $website = $post['hdnWebSite'];
        }

        if (isset($siteList[$website]) === FALSE) {
            throw new RuntimeException("Web site code not found");
        }

        if (isset($post['hdnloginPageId'])) {
            $loginPageId = intval($post['hdnloginPageId'], 10);
        }

        if ($loginPageId < 1) {
            throw new RuntimeException("Login Page Id not found");
        }

        $pageTypes = Common::readGenLookupsPDO($dbh, 'Page_Type');
        $positionMenus = [];


        foreach ($post['txtIdPage'] as $k => $v) {

            $pageId = intval($k, 10);
            $newGroupCodes = array();

            if ($pageId < 0) {
                continue;
            }

            $pageRs = new PageRS();

            if ($pageId > 0) {
                // Existing page
                $pageRs->idPage->setStoredVal($pageId);
                $rows = EditRS::select($dbh, $pageRs, array($pageRs->idPage));

                if (count($rows) != 1) {
                    throw new RuntimeException("Page not found, id= ". $pageId);
                }

                EditRS::loadRow($rows[0], $pageRs);

            } else {
                // New page

                $pageRs->Web_Site->setNewVal($website);

                // Page type
                if (isset($post['selPageType'][$pageId])) {
                    $pgType = $post['selPageType'][$pageId];
                }

                if (isset($pageTypes[$pgType])) {
                    $pageRs->Type->setNewVal($pgType);
                } else {
                    continue;
                }

                // Login page Id
                if ($pgType == WebPageCode::Page) {
                    $pageRs->Login_Page_Id->setNewVal($loginPageId);
                } else {
                    $pageRs->Login_Page_Id->setNewVal(0);
                }

                // File Name
                if (isset($post['txtFileName'][$pageId]) && $post['txtFileName'][$pageId] != '' && file_exists("../" . $siteList[$post["hdnWebSite"]]["Relative_Address"] . $post['txtFileName'][$pageId])) {
                    $pageRs->File_Name->setNewVal($post['txtFileName'][$pageId]);
                } else {
                    throw new ValidationException("Page filename (" . $siteList[$post["hdnWebSite"]]["Relative_Address"] . $post['txtFileName'][$pageId] . ") doesn't exist.");
                }
            }

            // Title
            if (isset($post['txtPageTitle'][$pageId])) {
                $pageRs->Title->setNewVal($post['txtPageTitle'][$pageId]);
            }

            // Hidden page
            if (isset($post['cbHide'][$pageId])) {
                $pageRs->Hide->setNewVal(1);
            } else {
                $pageRs->Hide->setNewVal(0);
            }

            // menu parent/ menu position
            if (isset($post['selParentId'][$pageId])) {

                $pageRs->Menu_Parent->setNewVal($post['selParentId'][$pageId]);

                $menuParent = $post['selParentId'][$pageId];

                // Menu Position
                if (isset($post['txtParentPosition'][$pageId])) {

                    $menuPosition = $post['txtParentPosition'][$pageId];

                    if ($menuParent > 0) {
                        if (isset($positionMenus[$menuParent][$menuPosition])) {
                            // double on same position
                            $pageErrors .= "Page Menu Position (" . $menuPosition . ") is already used, id= " . $pageId;

                        } else {
                            $positionMenus[$menuParent][$menuPosition] = 1;
                        }
                    }

                    $pageRs->Menu_Position->setNewVal($menuPosition);
                }
            }

            if (isset($post["selSecCode"][$pageId])) {
                $newGroupCodes = $post["selSecCode"][$pageId];
            }


            $pageRs->Updated_By->setNewVal('admin');
            $pageRs->Last_Updated->setNewVal(date('y-m-d H:i:s'));

            if ($pageId == 0) {
                $pageId = EditRS::insert($dbh, $pageRs);
            } else {
                // uupdate page record.
                EditRS::update($dbh, $pageRs, array($pageRs->idPage));
            }

            // Secrutiy codes.
            if (count($newGroupCodes) > 0) {

                $flipped = array_flip($newGroupCodes);
                $existingCodes = array();

                // Get existing security group codes
                $psgRs = new Page_SecurityGroupRS();
                $psgRs->idPage->setStoredVal($pageId);
                $psgRows = EditRS::select($dbh, $psgRs, array($psgRs->idPage));


                // Remove any codes from the new codes array
                foreach ($psgRows as $r) {

                    $oldpsgRs = new Page_SecurityGroupRS();
                    EditRS::loadRow($r, $oldpsgRs);

                    if (!isset($flipped[$oldpsgRs->Group_Code->getStoredVal()])) {

                        // Delete this security group code.
                        EditRS::delete($dbh, $oldpsgRs, array($oldpsgRs->Group_Code, $oldpsgRs->idPage));
                    } else {
                        $existingCodes[$oldpsgRs->Group_Code->getStoredVal()] = $oldpsgRs->Group_Code->getStoredVal();
                    }
                }

                // Add any new codes.
                foreach ($newGroupCodes as $c) {

                    if ($c == '' || isset($secGroups[$c]) === FALSE || isset($existingCodes[$c])) {
                        continue;
                    }

                    // New code not found, add it
                    $psgRs = new Page_SecurityGroupRS();
                    $psgRs->Group_Code->setNewVal($c);
                    $psgRs->idPage->setNewVal($pageId);

                    EditRS::insert($dbh, $psgRs);
                }
            }
         }

         return $website;

    }

    public function getPages(\PDO $dbh, $site) {

        $uS = Session::getInstance();
        $indexPageId = 0;
        $siteList = $uS->siteList;

        if (isset($siteList[$site]) == FALSE) {
            throw new RuntimeException("Web site code not found: " . $site);
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
 order by p.idPage;";

        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':site' => $site));
        $pageRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $parentMenus = array();
        $positionMenus = [];

        // collect menu parents
        foreach ($pageRows as $r) {

            if ($r['Menu_Parent'] === '0' && $r['File_Name'] != 'index.php') {
                $parentMenus[$r['idPage']] = array(0=>$r['idPage'], 1=>$r['Title'], 2=>'');
            }

            if ($r['File_Name'] == 'index.php') {
                $indexPageId = $r['idPage'];
            }

            // Save position value for each menu parent.
            if ($r['Menu_Parent'] > 0 && !isset($positionMenus[$r['Menu_Parent']][$r["Menu_Position"]])) {
                $positionMenus[$r['Menu_Parent']][$r["Menu_Position"]] = $r['idPage'];
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
        $stmtg = $dbh->query("Select Group_Code, Title, '' as Substitute from w_groups");
        while ($r = $stmtg->fetch(\PDO::FETCH_NUM)) {
            $secGroups[$r[0]] = $r;
        }

        $pageId = 0;
        $lastRow = '';
        $auths = array();

        foreach ($pageRows as $rw) {

            if ($rw['idPage'] != $pageId) {

                // Clean up last row
                if ($lastRow != '') {
                    $lastRow .= HTMLTable::makeTd(
                        HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($secGroups, $auths, FALSE), array('name'=>'selSecCode[' . $pageId . '][]', 'class'=>'hhk-multisel', 'multiple'=>'multiple'))
                            );
                    $tbl->addBodyTr( $lastRow, array('class'=>'trPages'));

                }

                $pageId = $rw['idPage'];

                // Set up for new page entry
                $lastRow = HTMLTable::makeTd(HTMLInput::generateMarkup($pageId, array('name'=>'txtIdPage[' . $pageId . ']', 'readonly'=>'readonly', 'style'=>'background-color:transparent;text-align:right;', 'size'=>'5')))
                        .HTMLTable::makeTd($rw["Type_Description"])
                        .HTMLTable::makeTd($rw["File_Name"]);


                $hideAttr = array('type'=>'checkbox', 'name'=>'cbHide[' . $pageId . ']');

                if ($rw['Hide'] > 0) {
                    $hideAttr['checked'] = 'checked';
                }

                if ($rw['Type'] == WebPageCode::Page && $rw['File_Name'] != 'index.php') {

                    $lastRow .= HTMLTable::makeTd(HTMLInput::generateMarkup($rw["Title"], array('name'=>'txtPageTitle[' . $pageId . ']', 'size'=>'20')))
                        .HTMLTable::makeTd(HTMLInput::generateMarkup('', $hideAttr))
                        .HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($parentMenus, $rw["Menu_Parent"], FALSE), array('name'=>'selParentId[' . $pageId . ']', 'class'=>'hhk-selmenu')));


                    $parray = array('name'=>'txtParentPosition[' . $pageId . ']', 'size'=>'2');

                    if (isset($positionMenus[$rw["Menu_Parent"]][$rw["Menu_Position"]]) && $positionMenus[$rw["Menu_Parent"]][$rw["Menu_Position"]] != $pageId) {
                        // Seen this menu position before
                        $parray['class'] = 'ui-state-error';
                        $parray['title'] = 'Already used by page id ' . $positionMenus[$rw["Menu_Parent"]][$rw["Menu_Position"]];
                    }

                    $lastRow .= HTMLTable::makeTd(HTMLInput::generateMarkup($rw["Menu_Position"], $parray));

                } else {

                    $lastRow .= HTMLTable::makeTd($rw["Title"])
                        .HTMLTable::makeTd('')
                        .HTMLTable::makeTd('')
                        .HTMLTable::makeTd('');
                }

                $auths = array();
                $auths[] = $rw['Group_Code'];

            } else {
                $auths[]  = $rw['Group_Code'];
            }
        }

        // Clean up last row
        if ($lastRow != '') {
            $lastRow .= HTMLTable::makeTd(
                        HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($secGroups, $auths, FALSE), array('name'=>'selSecCode[' . $pageId . '][]', 'class'=>'hhk-multisel', 'multiple'=>'multiple'))
                            );
            $tbl->addBodyTr( $lastRow, array('class'=>'trPages'));

        }

        $pageTypes = Common::readGenLookupsPDO($dbh, 'Page_Type');

        // New Page
        $newRow = HTMLTable::makeTd(HTMLInput::generateMarkup('New', array('name'=>'txtIdPage[0]', 'readonly'=>'readonly', 'style'=>'background-color:transparent;text-align:center;', 'size'=>'5')))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($pageTypes, ''), array('name'=>'selPageType[0]')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtFileName[0]', 'size'=>'15')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtPageTitle[0]', 'size'=>'20')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'cbHide[0]')))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($parentMenus, '', FALSE), array('name'=>'selParentId[0]', 'class'=>'hhk-selmenu')))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtParentPosition[0]', 'size'=>'2')))
                . HTMLTable::makeTd(
                    HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($secGroups, '', FALSE), array('name'=>'selSecCode[0][]', 'class'=>'hhk-multisel', 'multiple'=>'multiple'))
                );

        $tbl->addBodyTr( $newRow, array('class'=>'trPages'));

        $mkup = HTMLContainer::generateMarkup('h2', $siteList[$site]['Description'] . ' Pages')
                . HTMLInput::generateMarkup($site, array('name'=>'hdnWebSite', 'type'=>'hidden'))
                . HTMLInput::generateMarkup($indexPageId, array('name'=>'hdnloginPageId', 'type'=>'hidden'))
                . $tbl->generateMarkup(array('id'=>'tblPages', 'class'=>'display'));

        return array('site'=>$site, "success" => $mkup);
    }

    public static function editSite(\PDO $dbh, $fields) {
        $siteCode = '';

        if (isset($fields["inSiteCode"])) {
            $siteCode = filter_var($fields["inSiteCode"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
            $siteRs->Description->setNewVal(filter_var($fields["inDescription"], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        if (isset($fields["inHostAddr"])) {
            $siteRs->HTTP_Host->setNewVal(filter_var($fields["inHostAddr"], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        if (isset($fields["inRelAddr"])) {
            $siteRs->Relative_Address->setNewVal(filter_var($fields["inRelAddr"], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        if (isset($fields["inCss"])) {
            $siteRs->Path_To_CSS->setNewVal(filter_var($fields["inCss"], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        if (isset($fields["inJs"])) {
            $siteRs->Path_To_JS->setNewVal(filter_var($fields["inJs"], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        if (isset($fields["inDefault"])) {
            $siteRs->Default_Page->setNewVal(filter_var($fields["inDefault"], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }


        if (isset($fields["inIndex"])) {
            $siteRs->Index_Page->setNewVal(filter_var($fields["inIndex"], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        if (isset($fields["siteSecCode"])) {
            $codes = filter_var_array($fields["siteSecCode"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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

        $events = array("success" => "Updated Site: " . $siteRs->Description->getStoredVal());


        return $events;
    }

    public function getPageErrors() {
        return $this->pageErrors;
    }
}
?>