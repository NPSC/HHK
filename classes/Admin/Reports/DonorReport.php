<?php

namespace HHK\Admin\Reports;

/**
 * donorReportManager.php
 *
 * @category  Reports
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

use HHK\SysConst\CampaignType;
use HHK\SysConst\MemStatus;
use HHK\SysConst\SalutationPurpose;
use HHK\sec\Session;
use HHK\Admin\VolCats;
use HHK\Admin\MemberSalutation\IndividualSalutation;
use HHK\Admin\MemberSalutation\OrganizationSalutation;
use HHK\ExcelHelper;
use PDO;

class DonorReport
{
    public static function prepDonorRpt(PDO $dbh, &$cbBasisDonor, &$donSelMemberType, $overrideSalutations, $letterSalutation, $envSalutation, $showAmounts = FALSE, $streamlined = FALSE) {

        ini_set('memory_limit', "128M");

        $voldCat = new VolCats();
        $sumaryRows = array();
        $typeMarkup = "";
        $ljClause = "";
        $wclause = "";
        $notNull = array();
        $totalCategories = 0;

        $uS = Session::getInstance();
        $uname = $uS->username;

        $includeDeceased = filter_has_var(INPUT_POST, "exDeceased");
        $slFlag = !filter_has_var(INPUT_POST, "btnstreamlined");

        if (filter_input(INPUT_POST, "rb_dandOr", FILTER_SANITIZE_FULL_SPECIAL_CHARS) == "or") {
            $andOr = "or";
        } else {
            $andOr = "and";
        }

        $voldCat->set_andOr($andOr);


        $donSelMemberType->setReturnValues($_POST[$donSelMemberType->get_htmlNameBase()]);

        foreach ($donSelMemberType->get_codeArray() as $code) {
            if ($code != "" && $donSelMemberType->get_value($code)) {
                $abr = "c" . $totalCategories;
                $ljClause .= " left join
            vmember_categories `$abr` on vd.Id = `$abr`.idName and `$abr`.Vol_Status = 'a' and `$abr`.Vol_Category = 'Vol_Type' and `$abr`.Vol_Code = '$code' ";

                $notNull[$totalCategories] = "`$abr`.idName is not null ";
                $typeMarkup .= $donSelMemberType->get_label($code) . " $andOr ";
                $totalCategories++;
            }
        }



        if ($totalCategories > 0) {

            $wclause = " and (" . $notNull[0];
            // Build the where clause
            for ($n = 1; $n < count($notNull); $n++) {
                if ($andOr == "and") {
                    $wclause .= "and " . $notNull[$n];
                } else {
                    $wclause .= "or " . $notNull[$n];
                }
            }

            $wclause .= ")";
        }

        $minAmt = 0;
        $maxAmt = 0;

        // collect the parameters
        $maxAmt = intval(filter_input(INPUT_POST, "txtmax", FILTER_SANITIZE_NUMBER_INT));
        $minAmt = intval(filter_input(INPUT_POST, "txtmin", FILTER_SANITIZE_NUMBER_INT));
        
        if (!$showAmounts) {
            $maxAmt = 0;
            $minAmt = 0;
        }

        $sDate = filter_var($_POST["sdate"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if ($sDate != '') {
            $sDate = date("Y/m/d", strtotime($sDate));
        }

        $eDate = filter_var($_POST["edate"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if ($eDate != "") {
            $eDate .= "23:59:59";
            $eDate = date("Y/m/d", strtotime($eDate));
        }

        //$ordr = filter_var($_POST["selOrder"], FILTER_SANITIZE_FULL_SPCIAL_CHARS);
        $roll = filter_var($_POST["selrollup"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);


        $dlFlag = filter_has_var(INPUT_POST, "btnDonDL");

        // Report type selectors
        if ($roll == "rd") {
            $rollup = true;
        } else {
            $rollup = false;
        }

        $oClause = "";
        $txtreport = "<tbody>";
        $txtIntro = "";
        $totalClause = "";

        if ($showAmounts) {

            // Deal with the amount where clauses
            if ($maxAmt != 0 && $minAmt != 0) {
                $totalClause .= "having  `Total` <= '$maxAmt' and `Total` >= '$minAmt'";
            } else if ($maxAmt != 0) {
                $totalClause .= "having  `Total` <= '$maxAmt'";
            } else if ($minAmt != 0) {
                $totalClause .= "having  `Total` >= '$minAmt'" ;
            }

        }

        // Donation date where clauses
        if ($sDate != "" || $eDate != "") {

            if ($sDate == "") {
                $sDate = "1905-1-1";
            }
            if ($eDate == "") {
                $eDate = date('Y-m-d');
            }

        $dateClause = " and vd.Effective_Date >= '" . $sDate . "' and vd.Effective_Date <= '" . $eDate . "' ";

        } else {
            $dateClause = "";
        }



        $campList = "";
        $campCount = 0;
        $selClause = "";
        $campRecords = 0;


        // check campaign codes
        if (filter_has_var(INPUT_POST, "selDonCamp")) {
            $campcodes = $_POST["selDonCamp"];

            // Get all the campaign codes
            $qu = "Select Campaign_Code, Title, Campaign_Type from campaign;";
            $stmt = $dbh->query($qu);
            $cRows = $stmt->fetchAll(PDO::FETCH_NUM);
            $campRecords = count($cRows);  //mysqli_num_rows($res);

            foreach ($cRows as $rw) {
                // add a clause for each indicidually selected campaign
                foreach ($campcodes as $item) {
                    if ($item == $rw[0]) {
                        $selClause .= " or LOWER(TRIM(vd.Campaign_Code)) = LOWER(TRIM('" . $rw[0] . "')) ";
                        $campList .= $rw[1] . ", ";
                        $campCount++;
                    }
                }
            }

            // remove first "or"
            if ($selClause != "") {
                $selClause = substr($selClause, 3);
                $selClause = " and (" . $selClause . ") ";
            } else {
                $selClause = "";
            }

            if ($campCount == 0) {
                // Select all campaigns....
                $campCount = $campRecords;
            }

            // remove last ', ' from the list
            $campList = substr($campList, 0, strlen($campList) - 2);
        }

        // Do we include companies, non-profits and members?
        if(isset($_POST[$cbBasisDonor->get_htmlNameBase()])){
            $cbBasisDonor->setReturnValues($_POST[$cbBasisDonor->get_htmlNameBase()]);
        }else{
            $cbBasisDonor->setReturnValues([]);
        }

        if ($slFlag) {
            $sumaryRows["Basis"] = $cbBasisDonor->setCsvLabel();
        }
        $mTypeList = $cbBasisDonor->setSqlString();
        if ($mTypeList != "") {
            $selClause .= " and vd.Member_Type in ($mTypeList) ";
        }


        // Set up ordering clauses based on users selections
        if ($dlFlag) {
            $oClause = " order by Donor_Last ";
        }

        $endD = $eDate;

        // Fix up date descriptors
        if ($sDate == "1905-1-1") {
            $startD = "The Beginning";
        } else {
            $startD = $sDate;
        }

        if ($eDate == "" && $sDate == "") {
            $fromDate = "For any date";
        } else if ($sDate == "") {
            $fromDate = "For dates before " . $endD;
        } else if ($eDate == "") {
            $fromDate = "For dates after " . $startD;
        } else {
            $fromDate = "For dates between " . $startD . " and " . $endD;
        }
        $sumaryRows["Date Range"] = $fromDate;

        // Fix up min and max amount descriptors
        if ($maxAmt == 0 && $minAmt == 0) {
            $between = "Any donation amount";
        } else if ($minAmt == 0) {
            $between = "Amounts less than $" . $maxAmt;
        } else if ($maxAmt == 0) {
            $between = "Amounts more than $" . $minAmt;
        } else {
            $between = "Amounts between $" . $minAmt . " and $" . $maxAmt;
        }

        if ($slFlag) {
            $sumaryRows["Amount Range"] = $between;
        }

        // Fix up the campaign list descriptors
        if ($campList == "") {
            $campString = " All Campaigns";
        } else {
            $campString = $campList;
        }
        $sumaryRows["Campaigns"] = $campString;


        // Define the Cstegory markup
        if ($typeMarkup != "") {
            $typeMarkup = substr($typeMarkup, 0, (strlen($typeMarkup) - 4));
        } else {
            $typeMarkup = "All Member Types";
        }

        if ($slFlag) {
            $sumaryRows["Member Types"] = $typeMarkup;
            // Exclude Deceased members
            if ($includeDeceased) {
                $sumaryRows["Deceased Members"] = "Included";
            } else {
                $sumaryRows["Deceased Members"] = "Excluded";
            }
        }



        // set up some variables for the query and translation
        $hdr = array(
            "Id"=>"string",
            "*"=>"string",
            "Last Name"=>"string",
            "Salutation Name"=>"string",
            "Address Name"=>"string",
            "Care-Of"=>"string",
            "Address"=>"string",
            "City"=>"string",
            "State"=>"string",
            "Zip"=>"string",
            'Email'=>"string",
        );

        $colWidths = array("10", "10", "20", "20", "20", "20", "20", "20", "10", "10", "35");


        // We use a different query string for roll-up and individual reports
        // set up the query, open the result set and create the header markup for each case

        if ($rollup) {
            if ($slFlag) {
                $sumaryRows['Report Type'] = "Rollup Report - Monetary Donations Only";
            }

            $query = "from vindividual_donations vd $ljClause where vd.Campaign_Type <> 'ink' $wclause $dateClause $selClause group by id $totalClause $oClause";

            $stmt = $dbh->query("select vd.*, sum(vd.Amount) as Total, sum(vd.Tax_Free) as `Tot_TaxFree`, count(vd.Id) as numDon "  . $query);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $reportTitle = "Donor Roll-up Report (Monetary Donations Only).   Date: " . date("m/d/Y");

            $amountsHdr = array(
                    "Total"=>"dollar",
                    "Vendor Amount"=>"dollar",
                    "Free & Clear"=>"dollar"
            );
            $amountsColWidths = array("15", "15", "15");

            // header - after opening the result set to the the number of records.
            if ($dlFlag) {
                $file = "DonorRollup";
                $writer = new ExcelHelper($file);
                $writer->setAuthor($uname);
                $writer->setTitle("Donor Roll-up Report");

                // build header
                $hdr["#"] = "string";

                $colWidths[] = "10";

                if ($showAmounts) {
                    $hdr = array_merge($hdr, $amountsHdr);
                    $colWidths = array_merge($colWidths, $amountsColWidths);
                }

                $hdrStyle = $writer->getHdrStyle($colWidths);
                $writer->writeSheetHeader("Worksheet", $hdr, $hdrStyle);

            } else {

                $txtIntro .= "<tr><th colspan='2'>" . $reportTitle . "</th></tr>";

                foreach ($sumaryRows as $key => $val) {
                    if ($key != "" && $val != "") {

                        $txtIntro .= "<tr><td class='tdlabel'>$key: </td><td>" . $val . "</td></tr>";
                    }
                }

                $txtIntro .= "<tr><td class='tdlabel'>Records Fetched: </td><td>" . count($rows) . "</td></tr>";
                $txtheadr = "<thead><tr><th style='width:40px;'>Id</th><th> * </th><th>Last Name</th><th>First</th>";
                if ($showAmounts) {
                    $txtheadr .= "<th>Donations</th><th>Total</th><th>Vendor Amount</th><th>Free & Clear</th>";
                }
                $txtheadr .= "</tr></thead>";
            }

        } else {
            // Not rollup
            if ($roll == 'ft') {

                // First don report
                if ($slFlag) {
                    $sumaryRows['Report Type'] = "First Donations Report";
                }

                $query = "from vindividual_donations vd $ljClause where 1=1 $wclause $selClause group by vd.id " . ($totalClause != "" ? $totalClause . "and " : "having") . " min(vd.Effective_Date) >= '" . $sDate . "' and min(vd.Effective_Date) <= '" . $eDate . "' $oClause ";

                $stmt = $dbh->query("select vd.*, vd.Amount as Total, vd.Tax_Free as `Tot_TaxFree`, min(vd.Effective_Date)" . $query);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $reportTitle = "First Donations Report.   Date: " . date("m/d/Y");
            } else {
                // Individual donation report
                if ($slFlag) {
                    $sumaryRows['Report Type'] = "Individual Donation Report";
                }


                $query = "select vd.*, vd.Amount as Total, vd.Tax_Free as `Tot_TaxFree` from vindividual_donations vd $ljClause where 1=1 $wclause $dateClause $selClause $totalClause $oClause";

                $stmt = $dbh->query($query);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $reportTitle = "Individual Donation Report.   Date: " . date("m/d/Y");
            }

            // header - after opening the result set to get number of rows.
            if ($dlFlag) {
                $file = 'IndividualDonationReport';
                $writer = new ExcelHelper($file);
                $writer->setAuthor($uname);
                $writer->setTitle("Individual Donation Report");

                $amountsHdr = array(
                        "Total"=>"dollar",
                        "Vendor Amount"=>"dollar",
                        "Free & Clear"=>"dollar",
                        "Pay Type" => "string"
                );
                $amountsColWidths = array("15", "15", "15", "14");

                if ($showAmounts) {
                    $hdr = array_merge($hdr, $amountsHdr);
                    $colWidths = array_merge($colWidths, $amountsColWidths);
                }
                $hdr["Campaign"] = "string";
                $hdr["Date"] = "MM/DD/YYYY";
                $hdr["Merge Code"] = "string";
                $hdr["Notes"] = "string";

                $colWidths = array_merge($colWidths, array("20", "15", "15", "30"));

                $hdrStyle = $writer->getHdrStyle($colWidths);
                $writer->writeSheetHeader("Worksheet", $hdr, $hdrStyle);

            } else {
                $txtIntro .= "<tr><th colspan='2'>" . $reportTitle . "</th></tr>";

                foreach ($sumaryRows as $key => $val) {
                    if ($key != "" && $val != "") {

                        $txtIntro .= "<tr><td class='tdlabel'>$key: </td><td>" . $val . "</td></tr>";
                    }
                }
                if ($slFlag) {
                    $txtIntro .= "<tr><td class='tdlabel'>Records Fetched: </td><td>" . count($rows) . "</td></tr>";
                    $txtheadr = "<thead><tr><th style='width:40px;'>Id</th><th> * </th><th>Last Name</th><th>Envelope Salutation</th>";
                    if ($showAmounts) {
                        $txtheadr .= "<th>Total</th><th>Vendor Amount</th><th>Free & Clear</th><th>Pay Type</th>";
                    }

                } else {
                    $txtheadr = "<thead><tr><th>Name</th>";
                    if ($showAmounts) {
                        $txtheadr .= "<th>Total</th><th>Pay Type</th>";
                    }

                }

                $txtheadr .= "<th>Campaign</th><th>Date</th><th>Note</th></tr></thead>";
            }
        }

        // running total for the report
        $reportAmt = 0.0;
        $houseTotal = 0.0;
        $inKindAmt = 0.0;
        $deceased = 0;

        // Major donation amount
        $majorFloat = floatval($uS->Major_Donation);

        // Loop through data
        foreach ($rows as $r) {

            // format amounts
            $amountMkup = number_format($r["Total"], 2, '.', '');
            $taxFreeMkup = '';
            $vendorAmt = '';
            if ($r['Campaign_Type'] != CampaignType::InKind) {
                $taxFreeMkup = number_format($r["Tot_TaxFree"], 2, '.', '');

                $vendorDiff = ($r["Total"] - $r["Tot_TaxFree"]);
                if ($vendorDiff > 0) {
                    $vendorAmt = number_format($vendorDiff, 1, '.', '');
                }
            }

            $amtFloat = floatval($r["Total"]);
            $houseFloat = floatval($r["Tot_TaxFree"]);
            $majorDonorMark = "";

            // Test for Major Donation status
            if ($majorFloat > 0 && $amtFloat >= $majorFloat) {
                $isMajorDonor = true;
                $majorDonorMark = " * ";
            } else {
                $isMajorDonor = false;
            }

            // Report Totals
            if ($r['Campaign_Type'] != CampaignType::InKind) {
                $reportAmt += $amtFloat;
                $houseTotal += $houseFloat;
            } else {
                $inKindAmt += $amtFloat;
            }

            // salutation
            $donor = NULL;  $partner = NULL;
            $careof = "";
            if ($r["isCompany"] && $r["Donor_Company"] != "") {

                $donor = new OrganizationSalutation($r["Donor_Company"]);

                if (($r["Assoc_Status"] != MemStatus::Deceased || $includeDeceased) && $r["Care_Of_Id"] > 0 && $r["Assoc_Company"] == $r["Donor_Company"]) {
                    $empl = new IndividualSalutation($r["Assoc_Last"], $r["Assoc_First"], $r["Assoc_Middle"], $r["Assoc_Nickname"], $r["Assoc_Prefix"], $r["Assoc_Suffix"], $r["Assoc_Gender"]);
                    if ($overrideSalutations) {
                        $careof = $empl->getMarkup(SalutationPurpose::Envelope, $envSalutation, NULL);
                    } else {
                        $careof = $empl->getMarkup(SalutationPurpose::Envelope, $r["Envelope_Name_Code"], NULL);
                    }
                }

            } else {

                if ($r["Donor_Status"] != MemStatus::Deceased || $includeDeceased) {

                    $donor = new IndividualSalutation($r["Donor_Last"], $r["Donor_First"], $r["Donor_Middle"], $r["Donor_Nickname"], $r["Donor_Prefix"], $r["Donor_Suffix"], $r["Donor_Gender"]);

                    // add partner name only if alive and still married to donor.
                    if (($r["Assoc_Status"] != MemStatus::Deceased || $includeDeceased) && $r["Assoc_Id"] > 0 && ($r["Donor_Partner_Id"] == $r["Assoc_Id"])) {
                        $partner = new IndividualSalutation($r["Assoc_Last"], $r["Assoc_First"], $r["Assoc_Middle"], $r["Assoc_Nickname"], $r["Assoc_Prefix"], $r["Assoc_Suffix"], $r["Assoc_Gender"]);
                    } else {
                        $partner = null;
                    }

                // Maybe the donor is dead and the partner is alive
                } else if (($r["Assoc_Status"] != MemStatus::Deceased || $includeDeceased) && $r["Assoc_Id"] > 0) {
                    // Use the partner as the donor
                    $donor = new IndividualSalutation($r["Assoc_Last"], $r["Assoc_First"], $r["Assoc_Middle"], $r["Assoc_Nickname"], $r["Assoc_Prefix"], $r["Assoc_Suffix"], $r["Assoc_Gender"]);
                    // Replace the address with the partner's address
                    $r["Address_1"] = $r["Assoc_Address_1"];
                    $r["Address_2"] = $r["Assoc_Address_2"];
                    $r["City"] = $r["Assoc_City"];
                    $r["State"] = $r["Assoc_State"];
                    $r["Zipcode"] = $r["Assoc_Zipcode"];
                    $r['Email'] = $r['Assoc_Email'];
                    $r["Donor_Last"] = $r["Assoc_Last"];
                    $r["Donor_First"] = $r["Assoc_First"];

                    $partner = null;
                } else {
                    // Donors are deceased.
                    $donor = null;
                    $deceased++;
                }
            }

            if (is_null($donor) === FALSE) {
                if ($overrideSalutations) {
                    $salName = $donor->getMarkup(SalutationPurpose::Letter, $letterSalutation, $partner);
                    $envName = $donor->getMarkup(SalutationPurpose::Envelope, $envSalutation, $partner);

                } else {
                    $salName = $donor->getMarkup(SalutationPurpose::Letter, $r["Salutation_Code"], $partner);
                    $envName = $donor->getMarkup(SalutationPurpose::Envelope, $r["Envelope_Name_Code"], $partner);
                }
            } else {
                continue;
            }

            if ($r["Address_2"] != "") {
                $combinedAddr = $r["Address_1"] . ", " . $r["Address_2"];
            } else {
                $combinedAddr = $r["Address_1"];
            }



            if ($dlFlag) {
                if ($rollup) {
                    $flds = array(
                        $r["id"],
                        $majorDonorMark,
                        $r["Donor_Last"],
                        $salName,
                        $envName,
                        $careof,
                        $combinedAddr,
                        $r["City"],
                        $r["State"],
                        $r["Zipcode"],
                        $r["Email"],
                        $r["numDon"]
                    );

                    if ($showAmounts) {
                        $flds[] = $amountMkup;
                        $flds[] = $vendorAmt;
                        $flds[] = $taxFreeMkup;
                    }

                    $row = $writer->convertStrings($hdr, $flds);
                    $writer->writeSheetRow("Worksheet", $row);

                } else {
                    // Excel output individual donor report
                    $flds = array(
                        $r["id"],
                        $majorDonorMark,
                        $r["Donor_Last"],
                        $salName,
                        $envName,
                        $careof,
                        $combinedAddr,
                        $r["City"],
                        $r["State"],
                        $r["Zipcode"],
                        $r["Email"]
                    );

                    if ($showAmounts) {
                        $flds[] = $amountMkup;
                        $flds[] = $vendorAmt;
                        $flds[] = $taxFreeMkup;
                        $flds[] = $r['Pay Type'];
                    }
                    $flds[] = $r["Campaign_Title"];
                    $flds[] = $r["Effective_Date"];
                    $flds[] = $r["Mail_Merge_Code"];
                    $flds[] = $r["Note"];

                    $row = $writer->convertStrings($hdr, $flds);
                    $writer->writeSheetRow("Worksheet", $row);
                }
            } else {
                // not dlflag - local report
                if ($rollup) {

                    // webpage output with roll-up
                    $txtreport .= "<tr>
                        <td style='width:40px;'><a href='NameEdit.php?id=" . $r["id"] . "'>" . $r["id"] . "</a></td>
                        <td>$majorDonorMark</td>
                        <td>" . $r["Donor_Last"] . "</td>
                        <td>" . $r["Donor_First"] . "</td>";

                    if ($showAmounts) {
                        $txtreport .= "<td style='text-align:center;'>" . $r["numDon"] . "</td><td style='text-align:right;'>" . $amountMkup . "</td><td style='text-align:right;'>" . $vendorAmt . "</td><td style='text-align:right;'>" . $taxFreeMkup . "</td>";
                    }
                    $txtreport .= "</tr>";

                } else {

                    //webpage output individual donor report
                    $txtreport .= "<tr>";

                    $txtreport .= ($slFlag ? "<td style='width:40px;'><a href='NameEdit.php?id=" . $r["id"] . "'>" . $r["id"] . "</a></td>" : '');
                    $txtreport .= ($slFlag ? "<td>$majorDonorMark</td>" . "<td>" . $r["Donor_Last"] . "</td>" : "<td>" . $r["Donor_First"] . ' '. $r["Donor_Last"] . ' ' .$r["Donor_Suffix"] . "</td>");
                    $txtreport .= ($slFlag ? "<td>" . $envName . "</td>" : '');

                    if ($showAmounts) {

                        $txtreport .= "<td style='text-align:right;'>" . $amountMkup . "</td>";
                        $txtreport .= ($slFlag ? "<td style='text-align:right;'>" . $vendorAmt . "</td><td style='text-align:right;'>" . $taxFreeMkup . "</td>" : '')
                        . "<td>" . $r['Pay Type'] . "</td>";
                    }

                    $txtreport .= "<td>" . $r["Campaign_Title"] . "</td><td>" . date("Y/m/d", strtotime($r["Effective_Date"])) . "</td><td>" . $r["Note"] . "</td></tr>";
                }
            }
        }


        // Finalize download.
        if ($dlFlag) {
            $writer->download();
        }

        $voldCat->set_reportMarkup($txtheadr . $txtreport . "</tbody>");

        if ($slFlag) {
            $txtIntro .= "<tr><td class='tdlabel'>Deceased Records not shown: </td><td>" . $deceased . "</td></tr>";
        }

        if ($showAmounts) {
            if ($inKindAmt > 0 && $slFlag) {
                $txtIntro .= "<tr><td class='tdlabel'>In Kind Total: </td><td>$" . number_format($inKindAmt, 2) . "</td></tr>";
            }

            if ($slFlag) {
                $txtIntro .= "<tr><td class='tdlabel'>Report Total: </td><td>$" . number_format($reportAmt, 2) . "</td></tr>";
            }

            $txtIntro .= "<tr><td class='tdlabel' style='font-weight:bold;'>House Total: </td><td style='font-weight:bold;'>$" . number_format($houseTotal, 2) . "</td></tr>";
        }

        $voldCat->reportHdrMarkup = $txtIntro;
        return $voldCat;
    }
}