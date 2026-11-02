<?php

namespace HHK\Admin\Reports;

use HHK\Admin\MemberSalutation\OrganizationSalutation;
use HHK\Admin\MemberSalutation\IndividualSalutation;
use HHK\SysConst\SalutationPurpose;
use HHK\Excel\ExcelHelper;

/**
 * MailList.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class MailList {

    // Do we collect company care/of names?
    // Do we address both partners?
    // Individuals with Work addresses?
    // Format:  Excel, HTML

    const FORMAT_HTML = "html";
    const FORMAT_EXCEL = "excel";
    public static function createList($stmt, $format, $formalcy, $include_CareOf = FALSE, $include_Partner = FALSE, $exclude_WorkAddr = TRUE) {

        // header -
        $file = "MailList";
        $writer = new ExcelHelper($file);
        $writer->setTitle("Mail List");

        $hdr = array(
            "Id"=>"string",
            "Last Name"=>"string",
            "Name"=>"string",
            "Care Of"=> "string",
            "Address"=>"string",
            "City"=>"string",
            "State"=>"string",
            "Zip"=>"string"
        );
        
        $colWidths = array("10", "20", "20", "20", "20", "20", "10", "10");

        $hdrStyle = $writer->getHdrStyle($colWidths);
        
        $writer->writeSheetHeader("Sheet1", $hdr, $hdrStyle);


        //-- Dump unwanted members
        //--
        //--  Relatives at same address
        //--  Corporate reps at home or company


        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            // salutation
            $salName = "";
            $careof = "";

            if (!$r["isCompany"] && $r["Donor_Company"] != "") {

                $donor = new OrganizationSalutation($r["Donor_Company"]);

                if ($r["fm"] > 0 ) {
                    $partner = new IndividualSalutation($r["Assoc_Last"], $r["Assoc_First"], $r["Assoc_Middle"], $r["Assoc_Nickname"], $r["Assoc_Prefix"], $r["Assoc_Suffix"], $r["Assoc_Gender"]);
                    $careof = $partner->getMarkup(SalutationPurpose::Envelope, $formalcy, NULL);
                }

                $salName = $donor->getMarkup(SalutationPurpose::Envelope, $formalcy, null);

            } else {

                $donor = new IndividualSalutation($r["Donor_Last"], $r["Donor_First"], $r["Donor_Middle"], $r["Donor_Nickname"], $r["Donor_Prefix"], $r["Donor_Suffix"], $r["Donor_Gender"]);

                // add partner name only if alive and still married to donor.
                if ($r["sp"] > 0 && $r["adr_count"] > 1 ) {
                    $partner = new IndividualSalutation($r["Assoc_Last"], $r["Assoc_First"], $r["Assoc_Middle"], $r["Assoc_Nickname"], $r["Assoc_Prefix"], $r["Assoc_Suffix"], $r["Assoc_Gender"]);
                } else {
                    $partner = null;
                }

                $salName = $donor->getMarkup(SalutationPurpose::Envelope, $formalcy, $partner);
                $careof = "";

            }


            $flds = array(
                $r["id"],
                $r["Donor_Last"],
                $salName,
                $careof,
                $r['street'],
                $r["city"],
                $r["state"],
                $r["zip"]
            );

            $row = $writer->convertStrings($hdr, $flds);
            $writer->writeSheetRow("Sheet1", $row);
        }


        // Finalize download.
        $writer->download();
    }

    /**
     * Runs the mailing list query: one row per household address, ready for createList().
     *
     * Mailable members are active, not excluded from mail, and have a good preferred address. Guests are
     * left out until $guestBlackOutDays have passed since their last stay ended.
     * Spouses at the same address share a row (grouped on the relationship id); organizations get one row
     * per active care-of contact.
     *
     * @param \PDO $dbh
     * @param int $guestBlackOutDays
     * @param string $mTypeList quoted Member_Type codes from chkBoxCtrl::setSqlString(), or '' for all
     * @return \PDOStatement
     */
    public static function queryMailList(\PDO $dbh, int $guestBlackOutDays, string $mTypeList = ''): \PDOStatement {

        $memberTypeClause = ($mTypeList != '' ? " AND `n`.`Member_Type` IN ($mTypeList) " : '');

        $stmt = $dbh->prepare("SELECT
    CASE WHEN `n`.`Record_Member` = 1 THEN '1' ELSE '0' END AS `isCompany`,
    `n`.`idName` AS `id`,
    CONCAT(IFNULL(`na`.`Address_1`, ''), IFNULL(`na`.`Address_2`, ''), IFNULL(`na`.`City`, '')) AS `adr_frag`,
    CONCAT(IFNULL(`na`.`Address_1`, ''), ' ', IFNULL(`na`.`Address_2`, '')) AS `street`,
    IFNULL(`na`.`City`, '') AS `city`,
    IFNULL(`na`.`State_Province`, '') AS `state`,
    IFNULL(`na`.`Postal_Code`, '') AS `zip`,
    IFNULL(`sp`.`idName`, 0) AS `sp`,
    CASE WHEN `n`.`Record_Member` = 1 THEN
            CASE
                WHEN COALESCE(`r1`.`idRelationship`, `r2`.`idRelationship`) IS NULL THEN `n`.`idName`
                WHEN `sp`.`idName` IS NOT NULL THEN COALESCE(`r1`.`idRelationship`, `r2`.`idRelationship`)
                ELSE 0
            END
        ELSE IFNULL(`co`.`idName`, 0)
    END AS `fm`,
    CASE WHEN COALESCE(`r1`.`idRelationship`, `r2`.`idRelationship`) IS NULL THEN '' ELSE 'sp' END AS `rel`,
    COUNT(*) AS `adr_count`,
    CASE WHEN `n`.`Record_Company` = 0 THEN `n`.`Name_Last` ELSE `n`.`Company` END AS `Donor_Last`,
    `n`.`Name_First` AS `Donor_First`,
    `n`.`Name_Nickname` AS `Donor_Nickname`,
    IFNULL(`g1`.`Description`, '') AS `Donor_Prefix`,
    IFNULL(`g2`.`Description`, '') AS `Donor_Suffix`,
    `n`.`Name_Middle` AS `Donor_Middle`,
    `n`.`Gender` AS `Donor_Gender`,
    `n`.`Company` AS `Donor_Company`,
    CASE WHEN `n`.`Record_Member` = 1 THEN IFNULL(`sp`.`Name_First`, '') ELSE IFNULL(`co`.`Name_First`, '') END AS `Assoc_First`,
    CASE WHEN `n`.`Record_Member` = 1 THEN IFNULL(`sp`.`Name_Last`, '') ELSE IFNULL(`co`.`Name_Last`, '') END AS `Assoc_Last`,
    CASE WHEN `n`.`Record_Member` = 1 THEN IFNULL(`sp`.`Name_Nickname`, '') ELSE IFNULL(`co`.`Name_Nickname`, '') END AS `Assoc_Nickname`,
    CASE WHEN `n`.`Record_Member` = 1 THEN IFNULL(`g3`.`Description`, '') ELSE IFNULL(`g5`.`Description`, '') END AS `Assoc_Prefix`,
    CASE WHEN `n`.`Record_Member` = 1 THEN IFNULL(`g4`.`Description`, '') ELSE IFNULL(`g6`.`Description`, '') END AS `Assoc_Suffix`,
    CASE WHEN `n`.`Record_Member` = 1 THEN IFNULL(`sp`.`Name_Middle`, '') ELSE IFNULL(`co`.`Name_Middle`, '') END AS `Assoc_Middle`,
    CASE WHEN `n`.`Record_Member` = 1 THEN IFNULL(`sp`.`Gender`, '') ELSE IFNULL(`co`.`Gender`, '') END AS `Assoc_Gender`
FROM `name` `n`
    JOIN `name_address` `na` ON `na`.`idName` = `n`.`idName` AND `na`.`Purpose` = `n`.`Preferred_Mail_Address`
    LEFT JOIN `relationship` `r1` ON `r1`.`idName` = `n`.`idName`
        AND `r1`.`Relation_Type` = 'sp' AND `r1`.`Status` = 'a'
    LEFT JOIN `relationship` `r2` ON `r1`.`idRelationship` IS NULL AND `r2`.`Target_Id` = `n`.`idName`
        AND `r2`.`Relation_Type` = 'sp' AND `r2`.`Status` = 'a'
    LEFT JOIN `name` `sp` ON `sp`.`idName` = COALESCE(`r1`.`Target_Id`, `r2`.`idName`)
        AND `sp`.`idName` > 0 AND `sp`.`Member_Status` = 'a'
    LEFT JOIN `name` `co` ON `n`.`Record_Member` <> 1 AND `co`.`Company_Id` = `n`.`idName`
        AND `co`.`idName` > 0 AND `co`.`Company_CareOf` = 'y' AND `co`.`Member_Status` = 'a'
    LEFT JOIN `gen_lookups` `g1` ON `g1`.`Table_Name` = 'Name_Prefix' AND `g1`.`Code` = `n`.`Name_Prefix`
    LEFT JOIN `gen_lookups` `g2` ON `g2`.`Table_Name` = 'Name_Suffix' AND `g2`.`Code` = `n`.`Name_Suffix`
    LEFT JOIN `gen_lookups` `g3` ON `g3`.`Table_Name` = 'Name_Prefix' AND `g3`.`Code` = `sp`.`Name_Prefix`
    LEFT JOIN `gen_lookups` `g4` ON `g4`.`Table_Name` = 'Name_Suffix' AND `g4`.`Code` = `sp`.`Name_Suffix`
    LEFT JOIN `gen_lookups` `g5` ON `g5`.`Table_Name` = 'Name_Prefix' AND `g5`.`Code` = `co`.`Name_Prefix`
    LEFT JOIN `gen_lookups` `g6` ON `g6`.`Table_Name` = 'Name_Suffix' AND `g6`.`Code` = `co`.`Name_Suffix`
WHERE `n`.`idName` > 0
    AND `n`.`Member_Status` = 'a'
    AND `n`.`Exclude_Mail` <> 1
    AND LOWER(IFNULL(`na`.`Bad_Address`, '')) <> 'true'
    AND IFNULL(`na`.`Address_1`, '') <> ''
    AND IFNULL(`na`.`Postal_Code`, '') NOT IN ('', '0')
    $memberTypeClause
    -- leave out patient/guest volunteer types, unless they also have another active type
    AND (NOT EXISTS (SELECT 1 FROM `name_volunteer2` `nv` WHERE `nv`.`idName` = `n`.`idName` AND `nv`.`Vol_Status` = 'a' AND `nv`.`Vol_Category` = 'Vol_Type')
        OR EXISTS (SELECT 1 FROM `name_volunteer2` `nv` WHERE `nv`.`idName` = `n`.`idName` AND `nv`.`Vol_Status` = 'a' AND `nv`.`Vol_Category` = 'Vol_Type' AND `nv`.`Vol_Code` NOT IN ('p', 'g')))
    -- guests only after the solicitation blackout since their last stay
    AND (NOT EXISTS (SELECT 1 FROM `name_guest` `ng` WHERE `ng`.`idName` = `n`.`idName`)
        OR NOT EXISTS (SELECT 1 FROM `stays` `s` WHERE `s`.`idName` = `n`.`idName`
            AND (`s`.`Checkout_Date` IS NULL OR DATE(`s`.`Checkout_Date`) >= DATE_SUB(CURDATE(), INTERVAL :blackOutDays DAY))))
GROUP BY `adr_frag`, `rel`, `fm`
ORDER BY `zip`, `Donor_Last`, `Donor_First`");

        $stmt->execute([':blackOutDays' => $guestBlackOutDays]);

        return $stmt;
    }

}
?>