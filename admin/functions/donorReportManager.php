<?php
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
function prepDonorRpt(PDO $dbh, &$cbBasisDonor, &$donSelMemberType, $overrideSalutations, $letterSalutation, $envSalutation, $showAmounts = FALSE) {

    ini_set('memory_limit', "128M");

    $voldCat = new VolCats();
    $sumaryRows = array();

    $uS = Session::getInstance();
    $uname = $uS->username;

    $includeDeceased = FALSE;
    if (isset($_POST["exDeceased"])) {
        $includeDeceased = TRUE;
    }


    if (isset($_POST["rb_dandOr"]) && $_POST["rb_dandOr"] == "or") {
        $andOr = "or";
//        $totalId = "";
//        $totalOrder = "";
//        $groupBy = "";
    } else {
        $andOr = "and";
//        $totalId = ", count(Id) as numId";
//        $totalOrder = "order by numId desc ";
//        $groupBy = " group by Id ";
    }
    $voldCat->set_andOr($andOr);


    $typeMarkup = "";
    $ljClause = "";
    $wclause = "";
    $notNull = array();
    $totalCategories = 0;

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

    $minAmt = "";
    $maxAmt = "";

    // collect the parameters
    //
    if (isset($_POST["txtmax"])) {
        $maxAmt = filter_var($_POST["txtmax"], FILTER_SANITIZE_NUMBER_INT);
    } else {
        $maxAmt = "";
    }
    if (isset($_POST["txtmin"])) {
        $minAmt = filter_var($_POST["txtmin"], FILTER_SANITIZE_NUMBER_INT);
    } else {
        $minAmt = "";
    }

    if (!$showAmounts) {
        $maxAmt = "";
        $minAmt = "";
    }

    $sDate = filter_var($_POST["sdate"], FILTER_SANITIZE_STRING);
    if ($sDate != '') {
        $sDate = date("Y/m/d", strtotime($sDate));
    }

    $eDate = filter_var($_POST["edate"], FILTER_SANITIZE_STRING);
    if ($eDate != "") {
        $eDate .= "23:59:59";
        $eDate = date("Y/m/d", strtotime($eDate));
    }

    //$ordr = filter_var($_POST["selOrder"], FILTER_SANITIZE_STRING);
    $roll = filter_var($_POST["selrollup"], FILTER_SANITIZE_STRING);


    if (isset($_POST["btnDonDL"])) {
        $dlFlag = true;
    } else {
        $dlFlag = false;
    }


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
            $totalClause .= "having  Total <= $maxAmt and Total >= $minAmt";
        } else if ($maxAmt != 0) {
            $totalClause .= "having  Total <= $maxAmt";
        } else if ($minAmt != 0) {
            $totalClause .= "having  Total >= " . $minAmt;
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
    $hasStudents = FALSE;

    // check campaign codes
    if (isset($_POST["selDonCamp"])) {
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
                    if ($rw[2] == CampaignType::Scholarship) {
                        $hasStudents = TRUE;
                    }
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
    $cbBasisDonor->setReturnValues($_POST[$cbBasisDonor->get_htmlNameBase()]);


    $sumaryRows["Basis"] = $cbBasisDonor->setCsvLabel();
    $mTypeList = $cbBasisDonor->setSqlString();
    if ($mTypeList != "") {
        $selClause .= " and vd.Member_Type in ($mTypeList) ";
    }


    // Set up ordering clauses based on users selections
    if ($dlFlag) {
        $oClause = " order by vd.Donor_Last ";
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
    if ($maxAmt == "" && $minAmt == "") {
        $between = "Any donation amount";
    } else if ($minAmt == "") {
        $between = "Amounts less than $" . $maxAmt;
    } else if ($maxAmt == "") {
        $between = "Amounts more than $" . $minAmt;
    } else {
        $between = "Amounts between $" . $minAmt . " and $" . $maxAmt;
    }
    $sumaryRows["Amount Range"] = $between;

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
    $sumaryRows["Member Types"] = $typeMarkup;

    // Exclude Deceased members
    if ($includeDeceased) {
        $sumaryRows["Deceased Members"] = "Included";
    } else {
        $sumaryRows["Deceased Members"] = "Excluded";
    }


    // set up some variables for the query and translation
    $hdr = array();

    $n = 0;
    $file = "";
    $reportRows = 1;

    // We use a different query string for roll-up and individual reports
    // set up the query, open the result set and create the header markup for each case
    if ($rollup) {
        $sumaryRows['Report Type'] = "Rollup Report - Monetary Donations Only";

        $query = "from vindividual_donations vd $ljClause where vd.Campaign_Type <> 'ink' $wclause $dateClause $selClause group by id $totalClause $oClause";

        $stmt = $dbh->query("select vd.*, sum(vd.Amount) as Total, sum(vd.Tax_Free) as `Tot_TaxFree`, count(vd.Id) as numDon "  . $query);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $reportTitle = "Donor Roll-up Report (Monetary Donations Only).   Date: " . date("m/d/Y");

        // header - after opening the result set to the the number of records.
        if ($dlFlag) {
            $file = "DonorRollup";
            $sml = OpenXML::createExcel($uname, 'Donor Roll-up Report');
            // build header

            $hdr[$n++] = "Id";
            $hdr[$n++] = "* ";
            $hdr[$n++] = "Last Name";
            $hdr[$n++] = "Salutation Name";
            $hdr[$n++] = "Address Name";
            $hdr[$n++] = "Care-Of";
            $hdr[$n++] = "Address";
            $hdr[$n++] = "City";
            $hdr[$n++] = "State";
            $hdr[$n++] = "Zip";

            if ($showAmounts) {
                $hdr[$n++] = "#";
                $hdr[$n++] = "Total";
                $hdr[$n++] = "Free & Clear";
            }

            OpenXML::writeHeaderRow($sml, $hdr);
            $reportRows++;
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
                $txtheadr .= "<th>#</th><th>Total</th><th>House Total</th>";
            }
            $txtheadr .= "</tr></thead>";
        }
    } else {
        // Individual donation report
        $sumaryRows['Report Type'] = "Individual Donation Report";

        $query = "from vindividual_donations vd $ljClause where 1=1 $wclause $dateClause $selClause $totalClause $oClause";

        $stmt = $dbh->query("select vd.*, vd.Amount as Total, vd.Tax_Free as `Tot_TaxFree` " . $query);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $reportTitle = "Individual Donation Report.   Date: " . date("m/d/Y");

        // header - after opening the result set to get number of rows.
        if ($dlFlag) {
            $file = 'IndividualDonationReport';
            $sml = OpenXML::createExcel($uname, 'Individual Donation Report');

            $hdr[$n++] = "Id";
            $hdr[$n++] = "* ";
            $hdr[$n++] = "Last Name";
            $hdr[$n++] = "Salutation Name";
            $hdr[$n++] = "Address Name";
            $hdr[$n++] = "Care-Of";
            $hdr[$n++] = "Address";
            $hdr[$n++] = "City";
            $hdr[$n++] = "State";
            $hdr[$n++] = "Zip";

            if ($showAmounts) {
                $hdr[$n++] = "Total";
                $hdr[$n++] = "% to Vendor";
                $hdr[$n++] = "Free & Clear";
            }
            $hdr[$n++] = "Campaign";
            if ($hasStudents) {
                $hdr[$n++] = "Student";
            } else {
                $hdr[$n++] = "Fund Code";
            }
            $hdr[$n++] = "Date";
            $hdr[$n++] = "Merge Code";
            $hdr[$n++] = "Notes";

            OpenXML::writeHeaderRow($sml, $hdr);
            $reportRows++;
        } else {
            $txtIntro .= "<tr><th colspan='2'>" . $reportTitle . "</th></tr>";

            foreach ($sumaryRows as $key => $val) {
                if ($key != "" && $val != "") {

                    $txtIntro .= "<tr><td class='tdlabel'>$key: </td><td>" . $val . "</td></tr>";
                }
            }
            $txtIntro .= "<tr><td class='tdlabel'>Records Fetched: </td><td>" . count($rows) . "</td></tr>";

            $txtheadr = "<thead><tr><th style='width:40px;'>Id</th><th> * </th><th>Last Name</th><th>Envelope Salutation</th>";

            if ($showAmounts) {
                $txtheadr .= "<th>Total</th><th>% to Vendor</th><th>Free & Clear</th>";
            }

            $txtheadr .= "<th>Campaign</th>" . ($hasStudents ? "<th>Student</th>" : "<th>Fund Code</th>") . "<th>Date</th><th>Note</th></tr></thead>";
        }
    }

    // running total for the report
    $reportAmt = 0.0;
    $houseTotal = 0.0;
    $inKindAmt = 0.0;
    $deceased = 0;

    if ($dlFlag) {

        // Create a new worksheet called “My Data”
        $myWorkSheet = new PHPExcel_Worksheet($sml, 'Constraints');
        // Attach the “My Data” worksheet as the first worksheet in the PHPExcel object
        $sml->addSheet($myWorkSheet, 1);
        $sml->setActiveSheetIndex(1);

        $sRows = OpenXML::writeHeaderRow($sml, array(0=>'Filter', 1=>'Parameters'));

        // create summary table
        foreach ($sumaryRows as $key => $val) {
            if ($key != "" && $val != "") {
                $flds = array(0 => array('type' => "s",
                        'value' => $key,
                        'style' => "sRight"
                    ),
                    1 => array('type' => "s",
                        'value' => $val
                    )
                );
                $sRows = OpenXML::writeNextRow($sml, $flds, $sRows);

            }
        }

        $sml->setActiveSheetIndex(0);

    }

    // Get the site configuration object
    try {
        $config = new Config_Lite(ciCFG_FILE);
    } catch (Exception $ex) {
        $uS->destroy();
        throw new Hk_Exception_Runtime("Configurtion file is missing, path=".ciCFG_FILE, 999, $ex);
    }

    // Major donation amount
    $majorFloat = floatval($config->get("financial", "Major_Donation_Amount", "600"));

    // Loop through data
    foreach ($rows as $r) {

        // format amounts
        $amountMkup = number_format($r["Total"], 2, '.', '');
        $taxFreeMkup = '';
        $percentCut = '0.0';
        if ($r['Campaign_Type'] != CampaignType::InKind) {
            $taxFreeMkup = number_format($r["Tot_TaxFree"], 2, '.', '');
            $percentCut = number_format($r["Percent_Cut"], 1, '.', '');
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

            $donor = new OrganizationSal($r["Donor_Company"]);

            if (($r["Assoc_Status"] != MemStatus::Deceased || $includeDeceased) && $r["Care_Of_Id"] > 0 && $r["Assoc_Company"] == $r["Donor_Company"]) {
                $empl = new IndividualSal($r["Assoc_Last"], $r["Assoc_First"], $r["Assoc_Middle"], $r["Assoc_Nickname"], $r["Assoc_Prefix"], $r["Assoc_Suffix"], $r["Assoc_Gender"]);
                if ($overrideSalutations) {
                    $careof = $empl->getMarkup(SalutationPurpose::Envelope, $envSalutation, NULL);
                } else {
                    $careof = $empl->getMarkup(SalutationPurpose::Envelope, $r["Envelope_Name_Code"], NULL);
                }
            }

        } else {

            if ($r["Donor_Status"] != MemStatus::Deceased || $includeDeceased) {

                $donor = new IndividualSal($r["Donor_Last"], $r["Donor_First"], $r["Donor_Middle"], $r["Donor_Nickname"], $r["Donor_Prefix"], $r["Donor_Suffix"], $r["Donor_Gender"]);

                // add partner name only if alive and still married to donor.
                if (($r["Assoc_Status"] != MemStatus::Deceased || $includeDeceased) && $r["Assoc_Id"] > 0 && ($r["Donor_Partner_Id"] == $r["Assoc_Id"])) {
                    $partner = new IndividualSal($r["Assoc_Last"], $r["Assoc_First"], $r["Assoc_Middle"], $r["Assoc_Nickname"], $r["Assoc_Prefix"], $r["Assoc_Suffix"], $r["Assoc_Gender"]);
                } else {
                    $partner = null;
                }

            // Maybe the donor is dead and the partner is alive
            } else if (($r["Assoc_Status"] != MemStatus::Deceased || $includeDeceased) && $r["Assoc_Id"] > 0) {
                // Use the partner as the donor
                $donor = new IndividualSal($r["Assoc_Last"], $r["Assoc_First"], $r["Assoc_Middle"], $r["Assoc_Nickname"], $r["Assoc_Prefix"], $r["Assoc_Suffix"], $r["Assoc_Gender"]);
                // Replace the address with the partner's address
                $r["Address_1"] = $r["Assoc_Address_1"];
                $r["Address_2"] = $r["Assoc_Address_2"];
                $r["City"] = $r["Assoc_City"];
                $r["State"] = $r["Assoc_State"];
                $r["Zipcode"] = $r["Assoc_Zipcode"];
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
                    0 => array('type' => "n",
                        'value' => $r["id"]
                    ),
                    1 => array('type' => "s",
                        'value' => $majorDonorMark
                    ),
                    2 => array('type' => "s",
                        'value' => $r["Donor_Last"]
                    ),
                    3 => array('type' => "s",
                        'value' => $salName
                    ),
                    4 => array('type' => "s",
                        'value' => $envName
                    ),
                    5 => array('type' => "s",
                        'value' => $careof
                    ),
                    6 => array('type' => "s",
                        'value' => $combinedAddr
                    ),
                    7 => array('type' => "s",
                        'value' => $r["City"]
                    ),
                    8 => array('type' => "s",
                        'value' => $r["State"]
                    ),
                    9 => array('type' => "s",
                        'value' => $r["Zipcode"],
                        'style' => '00000'
                    )
                );

                if ($showAmounts) {
                    $flds[10] = array('type' => "n",
                        'value' => $r["numDon"]);

                    $flds[11] = array('type' => "n",
                        'value' => $amountMkup,
                        'style' => PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

                    $flds[12] = array('type' => "n",
                        'value' => $taxFreeMkup,
                        'style' => PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                }

                $reportRows = OpenXML::writeNextRow($sml, $flds, $reportRows);

            } else {
                // Excel output individual donor report
                $n = 0;
                $flds = array(
                    $n++ => array('type' => "n",
                        'value' => $r["id"]
                    ),
                    $n++ => array('type' => "s",
                        'value' => $majorDonorMark
                    ),
                    $n++ => array('type' => "s",
                        'value' => $r["Donor_Last"]
                    ),
                    $n++ => array('type' => "s",
                        'value' => $salName
                    ),
                    $n++ => array('type' => "s",
                        'value' => $envName
                    ),
                    $n++ => array('type' => "s",
                        'value' => $careof
                    ),
                    $n++ => array('type' => "s",
                        'value' => $combinedAddr
                    ),
                    $n++ => array('type' => "s",
                        'value' => $r["City"]
                    ),
                    $n++ => array('type' => "s",
                        'value' => $r["State"]
                    ),
                    $n++ => array('type' => "s",
                        'value' => $r["Zipcode"],
                        'style' => PHPExcel_Style_NumberFormat::FORMAT_TEXT
                    )
                );

                if ($showAmounts) {
                    $flds[$n++] = array('type' => "n",
                        'value' => $amountMkup,
                        'style' => PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1
                    );
                    $flds[$n++] = array('type' => "n",
                        'value' => $percentCut/100,
                        'style' => PHPExcel_Style_NumberFormat::FORMAT_PERCENTAGE_00
                    );
                    $flds[$n++] = array('type' => "n",
                        'value' => $taxFreeMkup,
                        'style' => PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1
                    );
                    $flds[$n++] = array('type' => "s",
                        'value' => $r["Campaign_Title"]
                    );
                    $flds[$n++] = array('type' => "n",
                        'value' => $r["Fund_Code"]
                    );
                    $flds[$n++] = array('type' => "s",
                        'value' => date("m/d/Y", strtotime($r["Effective_Date"])),
                        'style' => PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14
                    );
                    $flds[$n++] = array('type' => "s",
                        'value' => $r["Mail_Merge_Code"]
                    );
                    $flds[$n++] = array('type' => "s",
                        'value' => $r["Note"]
                    );
                } else {
                    $flds[$n++] = array('type' => "s",
                        'value' => $r["Campaign_Title"]
                    );
                    $flds[$n++] = array('type' => "n",
                        'value' => $r["Fund_Code"]
                    );
                    $flds[$n++] = array('type' => "s",
                        'value' => date("m/d/Y", strtotime($r["Effective_Date"])),
                        'style' => PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX14
                    );
                    $flds[$n++] = array('type' => "s",
                        'value' => $r["Mail_Merge_Code"]
                    );
                    $flds[$n++] = array('type' => "s",
                        'value' => $r["Note"]
                    );
                }

                $reportRows = OpenXML::writeNextRow($sml, $flds, $reportRows);
            }
        } else {
            // not dlflag - local report
            if ($rollup) {

                // webpage output with roll-up
                $txtreport .= "<tr><td style='width:40px;'><a href='NameEdit.php?id=" . $r["id"] . "'>" . $r["id"] . "</a></td><td>$majorDonorMark</td><td>" .
                        $r["Donor_Last"] . "</td><td>" . $r["Donor_First"] . "</td>";
                if ($showAmounts) {
                    $txtreport .= "<td style='text-align:center;'>" . $r["numDon"] . "</td><td style='text-align:right;'>" . $amountMkup . "</td><td style='text-align:right;'>" . $taxFreeMkup . "</td>";
                }
                $txtreport .= "</tr>";
            } else {
                //webpage output individual donor report
                $txtreport .= "<tr><td style='width:40px;'><a href='NameEdit.php?id=" . $r["id"] . "'>" . $r["id"] . "</a></td>
                    <td>$majorDonorMark</td><td>" . $r["Donor_Last"] . "</td><td>" . $envName . "</td>";
                if ($showAmounts) {
                    $txtreport .= "<td style='text-align:right;'>" . $amountMkup . "</td><td style='text-align:center;'>" . $percentCut . "</td><td style='text-align:right;'>" . $taxFreeMkup . "</td>";
                }
                $txtreport .= "<td>" . $r["Campaign_Title"] . "</td><td>" . $r["Fund_Code"] . "</td><td>" . date("Y/m/d", strtotime($r["Effective_Date"])) . "</td><td>" . $r["Note"] . "</td></tr>";
            }
        }
    }


    // Finalize download.
    if ($dlFlag) {
        // Redirect output to a client's web browser (Excel2007)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $file . '.xlsx"');
        header('Cache-Control: max-age=0');

        OpenXML::finalizeExcel($sml);
        exit();
    }

    $voldCat->set_reportMarkup($txtheadr . $txtreport . "</tbody>");
        $txtIntro .= "<tr><td class='tdlabel'>Deceased Records not shown: </td><td>" . $deceased . "</td></tr>";
    if ($showAmounts) {
        if ($inKindAmt > 0) {
            $txtIntro .= "<tr><td class='tdlabel'>In Kind Total: </td><td>$" . number_format($inKindAmt, 2) . "</td></tr>";
        }
        $txtIntro .= "<tr><td class='tdlabel'>Report Total: </td><td>$" . number_format($reportAmt, 2) . "</td></tr>";
        $txtIntro .= "<tr><td class='tdlabel' style='font-weight:bold;'>House Total: </td><td style='font-weight:bold;'>$" . number_format($houseTotal, 2) . "</td></tr>";
    }
    $voldCat->reportHdrMarkup = $txtIntro;
    return $voldCat;
}

