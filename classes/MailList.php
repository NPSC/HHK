<?php

namespace HHK;

use HHK\SysConst\SalutationPurpose;
use HHK\Admin\MemberSalutation\OrganizationSalutation;
use HHK\Admin\MemberSalutation\IndividualSalutation;

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

    public static function fillMailistTable(\PDO $dbh, $guestBlackOutDays) {

//        $dbh->exec("drop temporary table if exists `$tempTableName`;");
//        $dbh->exec("create temporary table if not exists `$tempTableName` "
//                . "(`id` int,`mr` varchar(5),`adr_frag` varchar(200),`street` varchar(200),`city` varchar(100),`state` varchar(5),"
//                . "`zip` varchar(10),`sp` int,`fm` int,`rel` varchar(5),`cde` varchar(5), `ngid` int);");
        $dbh->exec("delete from `mail_listing`;");

        // generare the address table
        $affectedRows = $dbh->exec("insert into `mail_listing`
select v.Id,
    v.MemberRecord as mr,
    concat(v.Address_1, v.Address_2, v.City) as frag,
concat(v.Address_1, ' ', v.Address_2) as Street,
v.City,
v.StateProvince,
v.PostalCode,
    ifnull(vp.Id,0),
case
    when v.MemberRecord = 1 then
          -- Individual members
          case
            when r.Relation_Type is null then v.Id
            when r.Relation_Type = 'sp' and vp.Id is not null then r.idRelationship
            else 0
          end
    else
          -- Organizations
          case
            when ifnull(ve.Company_CareOf, 0) = 'y' then ve.Id
            else 0
          end
end as Family_Member,
ifnull(r.Relation_Type, '') as Relationship,
v.Address_Code
from
    vmember_listing_noex v
        left join
    vmember_listing_noex ve on v.Id = ve.Company_Id and ve.Company_CareOf = 'y'  and ve.MemberStatus = 'a'
        left join
    vmember_listing_noex vp on v.SpouseId = vp.Id and vp.MemberStatus = 'a'
left join
relationship r ON (v.Id = r.idName or v.Id = r.Target_Id)
    and r.Relation_Type = 'sp'
    and r.Status = 'a'
    left join
name_guest ng on v.Id = ng.idName
where
    v.MemberStatus = 'a'
    and v.Exclude_Mail = 0
    and LOWER(v.Bad_Address) <> 'true'
    and v.Address_1 <> ''
    and v.PostalCode <> ''
    and v.PostalCode <> '0'
    and case when ng.idName is not null
        then ifnull(DATEDIFF(now(), (select max(ifnull(Checkout_Date, now())) from stays where idName = v.Id)), ($guestBlackOutDays + 2)) > $guestBlackOutDays
        else 1=1 end");

        return $affectedRows;

    }

}
?>