<?php

namespace HHK\Admin\Reports;

/**
 * directoryReport.php
 *
 * @category  Reports
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 *
 * @param array $r vmember_directory record
 * @param string $type Relationship link type
 * @return array suitable for XLSXWriter
 */
use HHK\MailList;
use HHK\HTMLControls\chkBoxCtrl;
use HHK\HTMLControls\selCtrl;
use HHK\SysConst\SalutationCodes;
use HHK\ExcelHelper;

class DirectoryReport
{
    private static function DirCkExcludes($r, $type = '') {
        $street = "";
        $city = "";
        $state = "";
        $zip = "";

        if ($r["Exclude_Mail"] == 1) {
            $street = "x";
        } else if ($r["Bad_Address"] == "true") {
            $street = "**BAD ADDRESS**";
        } else {
            if (isset($r["Addresss_2"])) {
            $street = $r["Address_1"] . " " . $r["Addresss_2"];
            } else {
                $street = $r["Address_1"];
            }
            $city = $r["City"];
            $state = $r["StateProvince"];
            $zip = $r["PostalCode"];
        }

        $name = "";
        if ($r["MemberRecord"] == 1) {
            $name = $r["Fullname"];
        } else {
            $name = $r["Company"];
        }

        $flds = array(
            $r["Id"],
            $type,
            $name,
            $street,
            $city,
            $state,
            $zip,
            $r["Exclude_Phone"] == 1 ? 'x' : $r["Preferred_Phone"],
            $r["Exclude_Email"] == 1 ? 'x' : $r["Preferred_Email"]
        );

        return $flds;
    }


    public static function dirReport(\PDO $dbh, chkBoxCtrl $cbBasisDir, chkBoxCtrl $cbRelationDir, selCtrl $selDirType, $guestBlackOutDays, $emailBlockSize = 200) {

        ini_set('memory_limit', "128M");

        // Form returned to generate directory
        $dlFlag = false;
        if (filter_has_var(INPUT_POST, "btnExcel")) {
            $dlFlag = true;

        }

        $emlFlag = false;

        if (filter_has_var(INPUT_POST, $selDirType->get_htmlNameBase())) {
            $selDirType->setReturnValues($_POST[$selDirType->get_htmlNameBase()]);
        }
        $dordr = $selDirType->getCvsCode();
        if ($dordr == "'e'") {
            $emlFlag = true;
        }

        $wClause = "";

        if (filter_has_var(INPUT_POST, $cbBasisDir->get_htmlNameBase())) {
            $cbBasisDir->setReturnValues($_POST[$cbBasisDir->get_htmlNameBase()]);
        }
        $incBasis = $cbBasisDir->setCsvLabel();

        if ($incBasis == "") {
            return "No Report - Select a Member Basis.";
        }

        $mTypeList = $cbBasisDir->setSqlString();
        if ($mTypeList != "") {
            $wClause .= " AND `vm2`.`Member_Type` IN ($mTypeList) ";
        }

        if (filter_has_var(INPUT_POST, $cbRelationDir->get_htmlNameBase())) {
            $cbRelationDir->setReturnValues($_POST[$cbRelationDir->get_htmlNameBase()]);
        }
        $rTypeList = $cbRelationDir->setCsvLabel();
        if (filter_has_var(INPUT_POST, "cbEmployee")) {
            if ($rTypeList == "") {
                $rTypeList = "Employee";
            } else {
                $rTypeList .= ", Employee";
            }
        }
        if ($rTypeList != "") {
            $incRel = $rTypeList;
        } else {
            $incRel = "None Selected.";
        }

        $txtreport = "";
        $dirmarkup = "";


    // Directory
        if ($dordr == "'d'") {

            $query = "SELECT DISTINCT `vm2`.* FROM `vmember_directory` `vm2`
    LEFT JOIN `name_volunteer2` `nv` ON `vm2`.`Id` = `nv`.`idName` AND `nv`.`Vol_Status` = 'a' AND `nv`.`Vol_Category` = 'Vol_Type'
    WHERE IFNULL(`nv`.`Vol_Code`, '') NOT IN ('p', 'g') $wClause ORDER BY `Name_Last`, `vm2`.`Name_First`;";

            $stmt = $dbh->prepare($query);
            $stmt->execute();
            $lineCtr = 1;

            // Header
            if (!$dlFlag) {
                $txtreport .= "<table>";
                $txtreport .= "<thead><tr><th colspan='2'>Member Directory   Date: " . date("m/d/Y") . "</th></tr></thead>";
                $txtreport .= "<tbody><tr><td class='tdlabel'>Member Basis: </td><td>" . $incBasis . "</td></tr></tbody>";
                $txtreport .="</table>";
                $txtreport .= "<table style='margin-top:10px;' id='tblDirectory'><thead><tr><th style='width:40px'>Id</th><th></th><th>Name</th>";
                $txtreport .= "<th>Address</th><th>City</th><th style='width:15px;'>State</th><th>Zip</th><th>Phone</th>";
                $txtreport .= "<th>Email</th><th>Employer</th></tr></thead>";
            } else {

                $file = 'HouseDirectory';
                $writer = new ExcelHelper($file);
                $writer->setTitle("House Directory");

                $hdr = array(
                "Id"=>"string",
                "*"=>"string",
                "Name"=>"string",
                "Address"=>"string",
                "City"=>"string",
                "State"=>"string",
                "Zip"=>"string",
                "Phone"=>"string",
                "Email"=>"string"
                );
                $colWidths = array("10", "10", "20", "20", "20", "10", "10", "15", "35");
                $hdrStyle = $writer->getHdrStyle($colWidths);
                $writer->writeSheetHeader("Worksheet", $hdr, $hdrStyle);
            }

            $showEmployee = true;
            $txtreport .= "<tbody>";
                while ( $rw = $stmt->fetch(\PDO::FETCH_ASSOC)) {

    //           foreach ($rows as $rw) {
                // Check for Company Here
                if ($rw["MemberRecord"] == 0) {
                    if ($dlFlag) {
                        $row = $writer->convertStrings($hdr, self::DirCkExcludes($rw, 'O'));
                        $writer->writeSheetRow("Worksheet",$row);
                    } else {
                            $flds = self::DirCkExcludes($rw);

        $mkup = "<tr><td><a href='NameEdit.php?id=" . $rw["Id"] . "'>" . $rw["Id"] . "</a></td>
                <td><span title='Organization'>O</span></td>
                <td>" . $flds[2] . "</td>";
        $mkup .= "<td>" . $flds[3] . "</td>
            <td>" . $flds[4] . "</td>
            <td>" . $flds[5] . "</td>
            <td>" . $flds[6] . "</td>
            <td>" . $flds[7] . "</td>";
        $mkup .= "<td>" . $flds[8] . "</td><td>" . $rw["Company"] . "</td></tr>";

                        $txtreport .= $mkup;
                    }

                } else {
                    // Individual member...
                    if ($dlFlag) {
                        $row = $writer->convertStrings($hdr, self::DirCkExcludes($rw, ''));
                        $writer->writeSheetRow("Worksheet", $row);
                    } else {
                        $flds = self::DirCkExcludes($rw);

        $mkup = "<tr><td><a href='NameEdit.php?id=" . $rw["Id"] . "'>" . $rw["Id"] . "</a></td>
                <td><span></span></td>
                <td>" . $flds[2] . "</td>";
        $mkup .= "<td>" . $flds[3] . "</td>
            <td>" . $flds[4] . "</td>
            <td>" . $flds[5] . "</td>
            <td>" . $flds[6] . "</td>
            <td>" . $flds[7] . "</td>";
        $mkup .= "<td>" . $flds[8] . "</td><td>" . $rw["Company"] . "</td></tr>";

                        $txtreport .= $mkup;
                    }

                }  // company or individual
            }  // while data exists.

            $txtreport .= "</tbody></table>";

            if ($dlFlag) {
                $writer->download();
            }
            $dirmarkup = "<div class='ui-widget ui-widget-content ui-corner-all hhk-widget-content'>". $txtreport . "</div>";
        }

        // Mail list
        else if ($dordr == "'m'") {
            // Create Mailing List

            if ($dlFlag) {

                // Refresh the address staging table with current data before generating the list.
                MailList::fillMailistTable($dbh, $guestBlackOutDays);

                $wClause = '';
                if ($mTypeList != "") {
                    $wClause .= " AND `vm`.`Member_Type` IN ($mTypeList) ";
                }

                $stmt = $dbh->prepare("SELECT `a`.`mr` AS `isCompany`, `a`.`id`, `a`.`street`, `a`.`city`, `a`.`state`, `a`.`zip`, `a`.`sp`, `a`.`fm`, `a`.`rel`, COUNT(`a`.`adr_frag`) AS `adr_count`,
    `vm`.`Name_Last` AS `Donor_Last`,
    `vm`.`Name_First` AS `Donor_First`,
    `vm`.`Name_Nickname` AS `Donor_Nickname`,
    `vm`.`Name_Prefix` AS `Donor_Prefix`,
    `vm`.`Name_Suffix` AS `Donor_Suffix`,
    `vm`.`Name_Middle` AS `Donor_Middle`,
    `vm`.`Title` AS `Donor_Title`,
    `vm`.`Gender` AS `Donor_Gender`,
    `vm`.`Company` AS `Donor_Company`,
    `vm`.`Address_Code` AS `Donor_Preferred_Addr_Code`,
    CASE WHEN `vm`.`MemberRecord` THEN IFNULL(`vp`.`Name_First`, '') ELSE IFNULL(`ve`.`Name_First`, '') END AS `Assoc_First`,
    CASE WHEN `vm`.`MemberRecord` THEN IFNULL(`vp`.`Name_Last`, '') ELSE IFNULL(`ve`.`Name_Last`, '') END AS `Assoc_Last`,
    CASE WHEN `vm`.`MemberRecord` THEN IFNULL(`vp`.`Name_Nickname`, '') ELSE IFNULL(`ve`.`Name_Nickname`, '') END AS `Assoc_Nickname`,
    CASE WHEN `vm`.`MemberRecord` THEN IFNULL(`vp`.`Name_Prefix`, '') ELSE IFNULL(`ve`.`Name_Prefix`, '') END AS `Assoc_Prefix`,
    CASE WHEN `vm`.`MemberRecord` THEN IFNULL(`vp`.`Name_Suffix`, '') ELSE IFNULL(`ve`.`Name_Suffix`, '') END AS `Assoc_Suffix`,
    CASE WHEN `vm`.`MemberRecord` THEN IFNULL(`vp`.`Name_Middle`, '') ELSE IFNULL(`ve`.`Name_Middle`,'') END AS `Assoc_Middle`,
    CASE WHEN `vm`.`MemberRecord` THEN '' ELSE IFNULL(`ve`.`Title`, '') END AS `Assoc_Title`,
    CASE WHEN `vm`.`MemberRecord` THEN '' ELSE IFNULL(`ve`.`Company`, '') END AS `Assoc_Company`,
    CASE WHEN `vm`.`MemberRecord` THEN IFNULL(`vp`.`Gender`, '') ELSE IFNULL(`ve`.`Gender`, '') END AS `Assoc_Gender`,
    CASE WHEN `vm`.`MemberRecord` THEN IFNULL(`vp`.`Address_Code`,'') ELSE IFNULL(`ve`.`Address_Code`,'') END AS `Assoc_Preferred_Addr_Code`
    FROM `mail_listing` `a` LEFT JOIN `vmember_listing_noex` `vm` ON `a`.`id` = `vm`.`Id`
    LEFT JOIN `vmember_listing_noex` `vp` ON `vp`.`Id` = `a`.`sp`
    LEFT JOIN `vmember_listing_noex` `ve` ON `ve`.`Id` = `a`.`fm` AND `a`.`mr` = 0
    LEFT JOIN `name_volunteer2` `nv` ON `vm`.`Id` = `nv`.`idName` AND `nv`.`Vol_Status` = 'a' AND `nv`.`Vol_Category` = 'Vol_Type'
    WHERE IFNULL(`nv`.`Vol_Code`, '') NOT IN ('p', 'g') $wClause
    GROUP BY `a`.`adr_frag`, `a`.`rel`, `a`.`fm`"
                    . " ORDER BY `a`.`zip`, `vm`.`Name_Last`, `vm`.`Name_First`");
                $stmt->execute();

                MailList::createList($stmt, MailList::FORMAT_EXCEL, SalutationCodes::Formal, FALSE, FALSE, TRUE);
            }

            $txtreport = "<div class='ui-widget ui-widget-content'><table><tr><th>Only Available as an Excel file.</th></tr>";

            //$txtreport .= "<tr><td class='tdlabel'>Member Basis: </td><td>" . $incBasis . "</td></tr></table>";

            $dirmarkup = $txtreport; // . "<table style='margin-top:10px;'>". $mlArray["rpt"] . "</table></div>";
        }

        // Email list
        else if ($dordr == "'e'") {

            // Create Email list.
            $query = "SELECT `vm2`.`Email`, `vm2`.`Name`, `vm2`.`idName`, `v`.`idVisit`, MAX(IFNULL(`v`.`Span_End`, NOW())) AS `spanEnd`
    FROM `vemail_directory` `vm2`
        LEFT JOIN `name_guest` `ng` ON `vm2`.`idName` = `ng`.`idName`
        LEFT JOIN `registration` `r` ON `ng`.`idPsg` = `r`.`idPsg`
        LEFT JOIN `visit` `v` ON `r`.`idRegistration` = `v`.`idRegistration` AND `v`.`Status` IN ('co', 'a')
    LEFT JOIN `name_volunteer2` `nv` ON `vm2`.`idName` = `nv`.`idName` AND `nv`.`Vol_Status` = 'a' AND `nv`.`Vol_Category` = 'Vol_Type'
    WHERE IFNULL(`nv`.`Vol_Code`, '') NOT IN ('p', 'g') $wClause
    GROUP BY `vm2`.`idName`
    HAVING CASE WHEN `v`.`idVisit` IS NOT NULL THEN DATEDIFF(NOW(), spanEnd) > :guestBlackOutDays ELSE 1=1 END";

            $stmt = $dbh->prepare($query);
            $stmt->execute([':guestBlackOutDays' => $guestBlackOutDays]);
            // $rows = $stmt->fetchAll(PDO::FETCH_NUM);

            if ($dlFlag) {

                $reportRows = 1;

                $file = "Emaildirectory";
                $writer = new ExcelHelper($file);
                $writer->setTitle('Email Directory');

                $hdr = array("Email"=>"string", "Name"=>"String");
                // foreach ($rows as $rw) {
                while ($rw = $stmt->fetch(\PDO::FETCH_ASSOC)) {

                    $flds = array(
                        $rw['Email'],
                        $rw['Name']
                    );
                    $row = $writer->convertStrings($hdr, $flds);
                    $writer->writeSheetRow("Worksheet", $row);
                }

                $writer->download();

            } else {

                $txtreport = "<table><tr><td colspan='5'>Number of Email addresses returned: " .$stmt->rowCount() . "</td></tr><tr><td colspan='5'>";
                $firstRecord = true;

                $numRcrds = 0;
                $multiplier = 1;
                while ($rw = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    if ($firstRecord) {
                        $txtreport .= $rw['Email'];
                        $firstRecord = false;
                    } else {
                        $txtreport .= ", " . $rw['Email'];
                    }
                    $numRcrds++;

                    if ($numRcrds >= ($emailBlockSize * $multiplier)) {
                        $txtreport .= "</td></tr><tr><td colspan='5'>Record Number = $numRcrds</td></tr><tr><td>";
                        $firstRecord = true;
                        $multiplier++;
                    }
                }

                $dirmarkup = $txtreport . "</td></tr></table>";
            }

        }
        return $dirmarkup;
    }
}