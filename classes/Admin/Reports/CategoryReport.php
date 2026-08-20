<?php

namespace HHK\Admin\Reports;

/**
 * CategoryReport.php
 *
 * @category  Reports
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/hhk
 */

use HHK\HTMLControls\selCtrl;
use HHK\sec\Session;
use HHK\Admin\VolCats;
use HHK\ExcelHelper;
use PDO;


class CategoryReport 
{
    public static function processCategory(PDO $dbh, &$selCtrls, selCtrl &$rankCtrl, selCtrl &$dormancyCtrl, selCtrl &$volStatusCtrl, $guestBlackOutDays = 61) { //, &$sortCtrl) {
        $volCat = new VolCats();
        $dlFlag = false;
        $csvFlag = false;

        $uS = Session::getInstance();
        $uname = $uS->username;

        if (isset($_POST["btnCatDL"])) {
            $dlFlag = true;
        }


        if (isset($_POST["btnCSVEmail"])) {
            $csvFlag = true;
        }


        $wClause = "";
        $sumaryRows = array();


        $rankCtrl->setReturnValues($_POST[$rankCtrl->get_htmlNameBase()]);
        $rankSel = $rankCtrl->getCvsCode();
        $rankHeader = $rankCtrl->getCsvLabel();

        //$dormancyCtrl->setReturnValues($_POST[$dormancyCtrl->get_htmlNameBase()]);
        //$dormantActive = $dormancyCtrl->getCvsCode();

        $volStatusCtrl->setReturnValues($_POST[$volStatusCtrl->get_htmlNameBase()]);
        $curRetire = $volStatusCtrl->getCvsCode();
        $retireHeader = $volStatusCtrl->getCsvLabel();


        $andOr = "";
        $andOrTxt = "";
        $totalId = "";
        $groupBy = "";
        $query = "";
        $showDetails = false;
        $intro = "";
        $headr = "";

        $txtreport = '';
        $txtHeader = '';

        if (isset($_POST["rb_andOr"])) {
            $andOr = filter_var($_POST["rb_andOr"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if ($andOr == "or") {
                $andOrTxt = "'Or'";
                $totalId = ", count(vm.Id) as total";
                $groupBy = " group by vm.Id ";
            } else if ($andOr == "and") {
                $andOrTxt = "'And'";
                $totalId = ", count(vm.Id) as total";
                $groupBy = " group by vm.Id ";
            } else if ($andOr == "union") {
                $andOrTxt = "'Union'";
                $totalId = "";
                $groupBy = "";
            } else {
                $andOr = "or";
            }
        }
        $volCat->set_andOr($andOr);


        $codeMarkup = array();
        //$volCodes = array();
        $totalCategories = 0;
        $wParams = array();
        $whIdx = 0;

        foreach ($selCtrls as $k => $ctrl) {
            $ctrl->setReturnValues($_POST[$ctrl->get_htmlNameBase()]);

            if (isset($_POST[$ctrl->get_htmlNameBase()])) {

                $codes = $_POST[$ctrl->get_htmlNameBase()];
                $codeMarkup[$ctrl->get_title()] = "";

                foreach ($codes as $cde) {
                    if ($cde != "") {
                        $catPh = ':wcat' . $whIdx;
                        $codePh = ':wcode' . $whIdx;
                        $wClause .= " OR (`c`.`Vol_Category` = $catPh AND `c`.`Vol_Code` = $codePh) ";
                        $wParams[$catPh] = $k;
                        $wParams[$codePh] = $cde;
                        $whIdx++;
                        $label = $ctrl->get_label($cde);
                        $codeMarkup[$ctrl->get_title()] .= $label . ", ";
                        $totalCategories++;
                    }
                }
            }
        }


        if ($wClause != "") {

            // remove first "or"
            $wClause = substr($wClause, 3);
            $wClause = " AND (" . $wClause . ") ";

            // Exclude dormant members?
            // Mode 'ad' means no where clause.
    //        if ($dormantActive == "act")
    //            $wClause .= " and date_format(curdate(), '%j') >= ifnull(date_format(d.Begin_Active, '%j'), 0) and date_format(curdate(), '%j') <= ifnull(date_format(d.End_Active, '%j'), 366) ";
    //        else if ($dormantActive == "dor")
    //            $wClause .= " and (date_format(curdate(), '%j') < ifnull(date_format(d.Begin_Active, '%j'), 0) or date_format(curdate(), '%j') > ifnull(date_format(d.End_Active, '%j'), 366)) ";
            // Filter for roles
            if ($rankSel != "") {
                $wClause .= " AND `c`.`Vol_Rank` IN ($rankSel) ";
            }

            // Filter for current and retired members?
            if ($curRetire != "") {
                $wClause .= " AND `c`.`Vol_Status` IN ($curRetire) ";
            }

            $wParams[':guestBlackOutDays'] = $guestBlackOutDays;

            /*
            *  The query
            */
            if (isset($_POST["btnMlCat"]) || isset($_POST["btnMlCatDL"])) {
                $query = "SELECT `c2`.`Id` FROM (" . self::makeSQL($wClause, $groupBy, $totalId) . ") AS `c2` ";
            } else if ($csvFlag == true) {
                $query = "SELECT DISTINCT `c2`.`PreferredEmail` FROM (" . self::makeSQL($wClause, $groupBy, $totalId) . ") AS `c2` ";
            } else {
                $query = "SELECT * FROM (" . self::makeSQL($wClause, $groupBy, $totalId) . ") AS `c2` ";
            }

            if ($totalCategories == 1) {
                $showDetails = true;
            }

            if ($andOr == "and") {
                $query .= " WHERE `c2`.`total` = :totalCategories ";
                $wParams[':totalCategories'] = $totalCategories;
            } else if ($andOr == "union") {
                $showDetails = true;
            }


            foreach ($codeMarkup as $key => $code) {
                if ($code != "") {
                    $intro .= "<tr><td class='tdlabel'>$key: </td><td>" . substr($code, 0, (strlen($code) - 2)) . "</td></tr>";
                    $sumaryRows["$key"] = substr($code, 0, (strlen($code) - 2));
                }
            }
            $intro .= "<tr><td class='tdlabel'>Combination Logic: </td><td>$andOrTxt</td></tr>";
            $sumaryRows["Combination Logic"] = $andOrTxt;

    //        if ($dormantActive == "act")
    //            $intro .= "<tr><td  class='tdBox'>Active Members Only.";
    //        else if ($dormantActive == "dor")
    //            $intro .= "<tr><td  class='tdBox'>Dormant Members Only.";
    //        else if ($dormantActive == "ad")
    //            $intro .= "<tr><td  class='tdBox'>Includes Both Dormant and Active.";

            if ($retireHeader != "") {
                $intro .= "<tr><td class='tdlabel'>Status: </td><td>$retireHeader</td></tr>";
                $sumaryRows["Status"] = $retireHeader;
            } else {
                $intro .= "<tr><td class='tdlabel'>Status: </td><td>Current & Retired</td></tr>";
                $sumaryRows["Status"] = "Current & Retired";
            }


            if ($rankHeader != "") {
                $intro .= "<tr><td class='tdlabel'>Role: </td><td>$rankHeader</td></tr>";
                $sumaryRows["Role"] = $rankHeader;
            } else {
                $intro .= "<tr><td class='tdlabel'>Role: </td><td>All</td></tr>";
                $sumaryRows["Role"] = "All";
            }


            // Ordering
            $query .= "ORDER BY `c2`.`Name_Last`, `c2`.`Name_First`";




            $reportRows = 1;


            if ($dlFlag) {
                $fileName = 'CategoryReport';
                $writer = new ExcelHelper($fileName);
                $writer->setAuthor($uname);
                $writer->setTitle('Category Report');

                // build header
                $hdr = array();

                $hdr["Id"] = "string";
                $hdr["Last Name"] = "string";
                $hdr["First Name"] = "string";
                $hdr["Address"] = "string";
                $hdr["City"] = "string";
                $hdr["State"] = "string";
                $hdr["Zip"] = "string";
                $hdr["Phone"] = "string";
                $hdr["Email"] = "string";

                $colWidths = array("10", "20", "20", "20", "20", "10", "10", "15", "35");

                if ($showDetails) {
                    $hdr["Begin"] = "date";
                    $hdr['Retire'] = "date";
                    $hdr["Status"] = "string";
                    $hdr["Description"] = "string";
                    $hdr["Role"] = "string";
                    $hdr["Notes"] = "string";

                    $colWidths = array_merge($colWidths, array("15", "15", "15", "20", "20", "30"));
                }

                $writer->writeSheetHeader('Worksheet', $hdr, $writer->getHdrStyle($colWidths));

                // create summary table
                /* $sHdr = array("Filter"=>"string", "Parameters"=>"string");
                $sColWidths = array("50", "50");
                $sHdrStyle = $writer->getHdrStyle($sColWidths);
                $writer->writeSheetHeader("Constraints", $sHdr, $sHdrStyle);

                $flds = array();
                foreach ($sumaryRows as $key => $val) {
                    if ($key != "" && $val != "") {
                        $flds[] = array($key, $val);
                    }
                }

                $writer->writeSheet($flds, "Constraints"); */
            } else if ($csvFlag) {
                // No header row for the plain email list.
            } else {
                $headr .= "<thead><tr>
                    <th>Id</th>
                    <th>Last</th>
                    <th>First</th>
                    <th>Address</th>
                    <th>City</th>
                    <th>State</th>
                    <th>Zip</th>
                    <th>Phone</th>
                    <th>Email</th>";
                if ($showDetails) {
                    $headr .= "<th>Begin</th><th>Retire</th><th>Status</th><th>Description</th><th>Role</th><th>Note</th>
                        </tr></thead>";
                } else {
                    $headr .= "</tr></thead>";
                }
            }

            $txtreport = "<tbody>";
            if ($csvFlag) {
                $txtreport .= "<tr><td colspan='5'>";
            }

            // get the data set.
            $stmt = $dbh->prepare($query);
            $stmt->execute($wParams);
            //$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            //foreach ($rows as $rw) {

            while ($rw = $stmt->fetch(PDO::FETCH_ASSOC)) {

                if ($dlFlag) {
                    $flds = array(
                        $rw["Id"],
                        $rw["Name_Last"],
                        $rw["Name_First"],
                        $rw["Address"],
                        $rw["City"],
                        $rw["StateProvince"],
                        $rw["PostalCode"],
                        $rw["PreferredPhone"],
                        $rw["PreferredEmail"]
                    );

                    if ($showDetails) {
                        $flds[] = $rw["Vol_Begin"];
                        $flds[] = $rw["Vol_End"];
                        $flds[] = $rw["Vol_Status_Title"];
                        $flds[] = $rw["Description"];
                        $flds[] = $rw["Vol_Rank_Title"];
                        $flds[] = $rw["Vol_Notes"];
                    }

                    $row = $writer->convertStrings($hdr, $flds);
                    $writer->writeSheetRow("Worksheet", $row);

                } else if ($csvFlag) {

                    if ($rw["PreferredEmail"] != "" && $rw["PreferredEmail"] != "x") {

                        $txtreport .= $rw["PreferredEmail"] . ", ";
                    }
                } else {


                    $txtreport .= "<tr><td><a href='NameEdit.php?id=" . $rw["Id"] . "'>" . $rw["Id"] . "</a></td>
    <td>" . $rw["Name_Last"] . "</td>
    <td>" . $rw["Name_First"] . "</td>
    <td>" . $rw["Address"] . "</td>
    <td>" . $rw["City"] . "</td>
    <td>" . $rw["StateProvince"] . "</td>
    <td>" . $rw["PostalCode"] . "</td>
    <td>" . $rw["PreferredPhone"] . "</td>
    <td>" . $rw["PreferredEmail"] . "</td>";

                    if ($showDetails) {

                        $txtreport .= "<td>" . ($rw["Vol_Begin"] == '' ? '' : date('M j, Y', strtotime($rw["Vol_Begin"]))) . "</td>";
                        $txtreport .= "<td>" . ($rw["Vol_End"] == '' ? '' : date('M j, Y', strtotime($rw["Vol_End"]))) . "</td>";

                        $txtreport .= "<td>" . $rw["Vol_Status_Title"] . "</td>";

                        $txtreport .= "<td>" . $rw["Description"] . "</td>";

                        $txtreport .= "<td>" . $rw["Vol_Rank_Title"] . "</td>";

                        if ($rw["Vol_Notes"] != "") {
                            $txtreport .= "<td><span title='" . $rw["Vol_Notes"] . "'>#</span></td>";
                        } else {
                            $txtreport .= "<td>&nbsp;</td>";
                        }
                    }

                    $txtreport .= "</tr>";
                }
            }

            if (!$dlFlag) {
                if ($csvFlag) {
                    $txtreport .= "</td></tr>";
                }
                $txtreport .= "</tbody>";
            }

            // title for category report
            $reportTitle = "Member Category Report.  Date " . date("m/d/Y");
            $txtHeader = "<tr><th colspan='2'>" . $reportTitle . " <input id='Print_Button' type='button' value='Print'/></th></tr>";

            if ($dlFlag) {
                $writer->download();
            }
        }

        if ($txtreport == "")
            $txtreport = "<span style='font-size: 1.5em'>No report.</span>";

        $volCat->set_reportMarkup($headr . $txtreport);
        $volCat->reportHdrMarkup = $txtHeader . $intro;

        return $volCat;
    }

    private static function makeSQL($whereClause, $groupBy, $totalId) {
        $query = "
    SELECT
    `vm`.`Id` AS `Id`,
        `vm`.`Name_Last` AS `Name_Last`,
        `vm`.`Name_First` AS `Name_First`,
        `c`.`Vol_Status` AS `Vol_Status`,
        `vm`.`Preferred_Phone` AS `PreferredPhone`,
        `vm`.`Preferred_Email` AS `PreferredEmail`,
        CASE
            WHEN `vm`.`Bad_Address` = LOWER('true') THEN '*(Bad Address)'
            ELSE (CASE
                WHEN `vm`.`Address_2` <> '' THEN CONCAT(`vm`.`Address_1`, ', ', `vm`.`Address_2`)
                ELSE `vm`.`Address_1`
            END)
        END AS `Address`,
        CASE
            WHEN (`vm`.`Bad_Address` = LOWER('true')) THEN ''
            ELSE `vm`.`City`
        END AS `City`,
        CASE
            WHEN (`vm`.`Bad_Address` = LOWER('true')) THEN ''
            ELSE `vm`.`StateProvince`
        END AS `StateProvince`,
        CASE
            WHEN (`vm`.`Bad_Address` = LOWER('true')) THEN ''
            ELSE `vm`.`PostalCode`
        END AS `PostalCode`,

        `c`.`Vol_Status_Title`,
        `c`.`Vol_Code_Title` AS `Description`,
        `c`.`Vol_Notes` AS `Vol_Notes`,
        `vm`.`Member_Type` AS `Member_Type`,
        `c`.`Vol_Begin` AS `Vol_Begin`,
        `c`.`Vol_End` AS `Vol_End`,
        `c`.`VOl_Rank_Title` AS `Vol_Rank_Title` $totalId
    FROM `vmember_listing_blackout` `vm`
        JOIN `vmember_categories` `c` ON `vm`.`Id` = `c`.`idName`
        WHERE `vm`.`MemberStatus` = 'a'
        AND CASE WHEN `vm`.`idVisit` > 0 THEN ABS(DATEDIFF(NOW(), `vm`.`spanEnd`)) >= :guestBlackOutDays ELSE 1=1 END $whereClause $groupBy";

        return $query;
    }
}