<?php

namespace HHK\Admin;

use HHK\MailList;
use HHK\SysConst\MemStatus;
use HHK\SysConst\SalutationCodes;

/**
 * SolicitReportGen.php
 *
 * Runs a database report for solicitation reports.
 *
 * @category  Utility
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of SolicitReportGen
 *
 *
 */
class SolicitReportGen {

    public static function createSqlSelect(\PDO $dbh, array $p) {

        $whereCl = "";

        // Member Status
        if (isset($p["cbActive"]) && !isset($p["cbInactive"])) {
            $whereCl .= " and vm.MemberStatus = '". MemStatus::Active ."' ";
        } else if (isset($p["cbInactive"]) && !isset($p["cbActive"])) {
            $whereCl .= " and vm.MemberStatus = '". MemStatus::Inactive ."' ";
        }

        // Member Designation
        if (isset($p["cbInd"]) && !isset($p["cbOrg"])) {
            $whereCl .= " and vm.MemberRecord = '1' ";
        } else if (isset($p["cbOrg"]) && !isset($p["cbInd"])) {
            $whereCl .= " and vm.MemberRecord = '0' ";
        }


        // Include Member Types
        $inclMT_Clause = self::loadCodes($p['selInType']);
        if ($inclMT_Clause != "") {

            $inclMT_Clause = " left join name_volunteer2 nv on vm.Id = nv.idName and nv.Vol_Status = 'a' and nv.Vol_Category = 'Vol_Type' and nv.Vol_Code in (" . $inclMT_Clause . ")";
            $whereCl .= " and nv.idName is not null ";
        }


        // Now the Member type removes
        $exMT_Clause = self::loadCodes($p['selExType']);
        if ($exMT_Clause != "") {

            $exMT_Clause = " left join name_volunteer2 nv2 on vm.Id = nv2.idName and nv2.Vol_Status = 'a' and nv2.Vol_Category = 'Vol_Type' and nv2.Vol_Code in (" . $exMT_Clause . ")";
            $whereCl .= " and nv2.idName is null ";
        }


        // Campaigns
        $inclCP_Clause = "";
        $exCP_Clause = "";

        $inclCampCodes = self::loadCodes($p['selCamp']);
        if ($inclCampCodes != "") {

            $inclCP_Clause = " left join donations d on d.Status='a' and (vm.Id = d.Donor_Id or vm.Id = d.Assoc_Id) and d.Campaign_Code in (" . $inclCampCodes . ")";
            $whereCl .= " and d.Donor_Id is not null ";
        }

        $exCampCodes = self::loadCodes($p['selCampEx']);
        if ($exCampCodes != "") {

            $exCP_Clause = " left join donations d2 on d2.Status = 'a' and (vm.Id = d2.Donor_Id or vm.Id = d2.Assoc_Id) and d2.Campaign_Code in (" . $exCampCodes . ")";
            $whereCl .= " and d2.Donor_Id is null ";
        }


        $query = "select a.mr as `isCompany`, a.id, a.street, a.city, a.state, a.zip, a.sp, a.fm, a.rel, count(a.adr_frag) as adr_count,
vm.Name_Last AS Donor_Last,
vm.Name_First AS Donor_First,
vm.Name_Nickname AS Donor_Nickname,
vm.Name_Prefix AS Donor_Prefix,
vm.Name_Suffix AS Donor_Suffix,
vm.Name_Middle AS Donor_Middle,
vm.Title AS Donor_Title,
vm.Gender AS Donor_Gender,
vm.Company AS Donor_Company,
vm.Address_Code as Donor_Preferred_Addr_Code,
case when vm.MemberRecord then ifnull(vp.Name_First, '') else ifnull(ve.Name_First, '') end AS Assoc_First,
case when vm.MemberRecord then ifnull(vp.Name_Last, '') else ifnull(ve.Name_Last, '') end AS Assoc_Last,
case when vm.MemberRecord then ifnull(vp.Name_Nickname, '') else ifnull(ve.Name_Nickname, '') end AS Assoc_Nickname,
case when vm.MemberRecord then ifnull(vp.Name_Prefix, '') else ifnull(ve.Name_Prefix, '') end AS Assoc_Prefix,
case when vm.MemberRecord then ifnull(vp.Name_Suffix, '') else ifnull(ve.Name_Suffix, '') end AS Assoc_Suffix,
case when vm.MemberRecord then ifnull(vp.Name_Middle, '') else ifnull(ve.Name_Middle,'') end AS Assoc_Middle,
case when vm.MemberRecord then '' else ifnull(ve.Title, '') end as Assoc_Title,
case when vm.MemberRecord then '' else ifnull(ve.Company, '') end as Assoc_Company,
case when vm.MemberRecord then ifnull(vp.Gender, '') else ifnull(ve.Gender, '') end AS Assoc_Gender,
case when vm.MemberRecord then ifnull(vp.Address_Code,'') else ifnull(ve.Address_Code,'') end as Assoc_Preferred_Addr_Code

from mail_listing a left join vmember_listing_noex vm on a.id = vm.Id
left join vmember_listing_noex vp ON vp.Id = a.sp
left join vmember_listing_noex ve ON ve.Id = a.fm and a.mr = 0 "
    . $inclMT_Clause . $exMT_Clause . $inclCP_Clause . $exCP_Clause
    . " where 1=1 "
    . $whereCl
    . "  group by a.adr_frag, a.rel, a.fm"
                . " order by a.zip, vm.Name_Last, vm.Name_First";

    $stmt = $dbh->query($query);

        MailList::createList($stmt, MailList::FORMAT_EXCEL, SalutationCodes::Formal, FALSE, FALSE, TRUE);

    }

    protected static function loadCodes($codes) {
        $cdClause = '';

        if (isset($codes)) {

            $codes = filter_var_array($codes, FILTER_SANITIZE_STRING);

            foreach ($codes as $cde) {

                $cde = trim($cde);

                if ($cde == '') {
                    continue;
                }

                $cleanCode = str_replace("'", "", str_replace(";", "", str_replace("\\", "", $cde)));

                if ($cdClause == "") {
                    $cdClause .= "'$cleanCode'";

                } else {
                    $cdClause .= ",'$cleanCode'";

                }
            }
        }

        return $cdClause;

    }
}

?>
