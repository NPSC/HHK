<?php
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

function DirCkExcludes($r, $type = '') {
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


function dirReport(\PDO $dbh, chkBoxCtrl $cbBasisDir, chkBoxCtrl $cbRelationDir, selCtrl $selDirType, $guestBlackOutDays, $emailBlockSize = 200) {

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
        $wClause .= " and vm2.Member_Type in ($mTypeList) ";
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


// Directory
    if ($dordr == "'d'") {

        $query = "select distinct vm2.* from vmember_directory vm2
 left join name_volunteer2 nv on vm2.Id = nv.idName and nv.Vol_Status = 'a' and nv.Vol_Category = 'Vol_Type'
 where ifnull(nv.Vol_Code, '') not in ('p', 'g') $wClause order by `Name_Last`, `vm2`.`Name_First`;";

        $stmt = $dbh->query($query);
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
                    $row = $writer->convertStrings($hdr, DirCkExcludes($rw, 'O'));
                    $writer->writeSheetRow("Worksheet",$row);
                } else {
                        $flds = DirCkExcludes($rw);

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
                    $row = $writer->convertStrings($hdr, DirCkExcludes($rw, ''));
                    $writer->writeSheetRow("Worksheet", $row);
                } else {
                    $flds = DirCkExcludes($rw);

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

            $wClause = '';
            if ($mTypeList != "") {
                $wClause .= " and vm.Member_Type in ($mTypeList) ";
            }

            $stmt = $dbh->query("select a.mr as `isCompany`, a.id, a.street, a.city, a.state, a.zip, a.sp, a.fm, a.rel, count(a.adr_frag) as adr_count,
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
left join vmember_listing_noex ve ON ve.Id = a.fm and a.mr = 0
left join name_volunteer2 nv on vm.Id = nv.idName and nv.Vol_Status = 'a' and nv.Vol_Category = 'Vol_Type'
where ifnull(nv.Vol_Code, '') not in ('p', 'g') $wClause
 group by a.adr_frag, a.rel, a.fm"
                . " order by a.zip, vm.Name_Last, vm.Name_First");

            MailList::createList($stmt, MailList::FORMAT_EXCEL, SalutationCodes::Formal, FALSE, FALSE, TRUE);
        }

        $txtreport = "<div class='ui-widget ui-widget-content'><table><tr><th>Only Available as an Excel file.</th></tr>";

        //$txtreport .= "<tr><td class='tdlabel'>Member Basis: </td><td>" . $incBasis . "</td></tr></table>";

        $dirmarkup = $txtreport; // . "<table style='margin-top:10px;'>". $mlArray["rpt"] . "</table></div>";
    }

    // Email list
    else if ($dordr == "'e'") {

        // Create Email list.
        $query = "select vm2.Email, vm2.`Name`, vm2.idName, v.idVisit, max(ifnull(v.Span_End, now())) as spanEnd
from vemail_directory vm2
	left join name_guest ng on vm2.idName = ng.idName
	left join registration r on ng.idPsg = r.idPsg
	left join visit v on r.idRegistration = v.idRegistration and v.Status in ('co', 'a')
left join name_volunteer2 nv on vm2.idName = nv.idName and nv.Vol_Status = 'a' and nv.Vol_Category = 'Vol_Type'
where ifnull(nv.Vol_Code, '') not in ('p', 'g') $wClause
group by vm2.idName
  having case when v.idVisit is not null then DATEDIFF(now(), spanEnd) > $guestBlackOutDays else 1=1 end";

        $stmt = $dbh->query($query);
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