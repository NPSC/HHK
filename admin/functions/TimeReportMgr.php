<?php

/**
 * TimeReportMgr.php
 *
 *
 * @category  Reports
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

use HHK\CreateMarkupFromDB;
use HHK\HTMLControls\selCtrl;
use HHK\sec\Session;
use HHK\SysConst\VolCalendarStatus;
use HHK\ExcelHelper;

function processTime(PDO $dbh, &$selCtrls, selCtrl &$selRptType, $fyMonthsAdjust) {

    $dlFlag = FALSE;


    $uS = Session::getInstance();
    $uname = $uS->username;

    if (isset($_POST["btnCatDL"])) {
        $dlFlag = true;
    }

    $wClause = "";
    $sumaryRows = array();
    $rows = array();
    $txtReport = "No Report";
    $codeMarkup = array();

    $sumHours = FALSE;
    $groupBy = "";
    $query = "";

    if (isset($_POST["selIntDetail"])) {
        $detail = filter_var($_POST["selIntDetail"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if ($detail == "ru") {
            $groupBy = " group by g.Description, vm.Id with rollup";
            $sumHours = TRUE;
            $sumaryRows["Detail"] = "Roll Up";
        } else {
            $groupBy = "";
            $sumHours = FALSE;
            $sumaryRows["Detail"] = "Each Record";
        }
    }



    $totalCategories = 0;

    foreach ($selCtrls as $k => $ctrl) {
        $ctrl->setReturnValues($_POST[$ctrl->get_htmlNameBase()]);

        if (isset($_POST[$ctrl->get_htmlNameBase()])) {

            $codes = $_POST[$ctrl->get_htmlNameBase()];
            $codeMarkup[$ctrl->get_title()] = '';

            foreach ($codes as $cde) {
                if ($cde != "") {
                    $wClause .= " or (c.E_Vol_Category='" . $k . "' and c.E_Vol_Code = '$cde') ";
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
        $wClause = " and (" . $wClause . ") ";

        // Time period
        if (isset($_POST["selHoursInterval"])) {
            $now = getDate();
            $intMonth = 0;
            $interval = filter_var($_POST["selHoursInterval"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            switch ($interval) {
                case "m":
                    $sumaryRows["Period"] = "Month";

                    if (isset($_POST["selIntMonth"])) {
                        $month = filter_var($_POST["selIntMonth"], FILTER_SANITIZE_NUMBER_INT);
                        $intMonth = intval($month);
                    }
                    if ($intMonth < 1 || $intMonth > 12) {
                        $intMonth = $now["mon"];
                    }

                    $sumaryRows["Month"] = $intMonth;
                    $wClause .= " and MONTH(c.E_End) = $intMonth and YEAR(c.E_END) = " . $now["year"];

                    break;

                case 'fy':
                    $sumaryRows["Period"] = "Fiscal Year";

                    if (isset($_POST["selIntFy"])) {
                        $year = filter_var($_POST["selIntFy"], FILTER_SANITIZE_NUMBER_INT);
                        $intYear = intval($year);
                    }

                    $sumaryRows["Year"] = $intYear;
                    $wClause .= " and YEAR(DATE_ADD(c.E_End, INTERVAL $fyMonthsAdjust MONTH)) = $intYear ";
                    break;

                case "cy":
                    $sumaryRows["Period"] = "Calendar Year";

                    if (isset($_POST["selIntFy"])) {
                        $year = filter_var($_POST["selIntFy"], FILTER_SANITIZE_NUMBER_INT);
                        $intYear = intval($year);
                    }

                    $sumaryRows["Year"] = $intYear;
                    $wClause .= " and YEAR(c.E_End) = $intYear ";
                    break;

                default:
            }
        }

        // REport Type
        if (isset($_POST[$selRptType->get_htmlNameBase()])) {

            $rType = $_POST[$selRptType->get_htmlNameBase()];
            $sumaryRows["Report Type"] = $selRptType->get_label($rType);

            if ($rType == "l") {
                $wClause .= " and c.E_Status = '" . VolCalendarStatus::Logged . "' ";
            } else if ($rType == 'ul') {
                $wClause .= " and c.E_Status = '" . VolCalendarStatus::Active . "' ";
            }
        }


        /*
         *  The query
         */
        if (isset($_POST["btnMlCat"]) || isset($_POST["btnMlCatDL"])) {
            $query = "Select c2.Id from (" . makeSQL($wClause, $groupBy, $sumHours) . ") as c2 ";
        } else {
            $query = makeSQL($wClause, $groupBy, $sumHours);
        }

        foreach ($codeMarkup as $key => $code) {
            if ($code != "") {
                $sumaryRows["$key"] = substr($code, 0, (strlen($code) - 2));
            }
        }

        $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // title for category report
        $reportTitle = "Volunteer Time Report.  Date " . date("m/d/Y");

        $rws = array();

        // pre-process for roll-up totals
        foreach ($rows as $r) {
            $n = 0;
            $isTotal = FALSE;

            foreach ($r as $k => $col) {
                if ($n == 0 && is_null($col)) {
                    if($dlFlag){
                        $r[$k] = "Report Total";
                    }else{
                        $r[$k] = "<strong>Report Total</strong>";
                    }
                    $isTotal = TRUE;
                }
                if ($n == 1 && is_null($col)) {
                    if ($isTotal === FALSE) {
                        if($dlFlag){
                            $r[$k] = "Subtotal";
                        }else{
                            $r[$k] = "<strong>Subtotal</strong>";
                        }
                    }
                    $isTotal = TRUE;
                }
                if ($n > 2 && $isTotal === TRUE) {
                    $r[$k] = '';
                }
                $n++;
            }

            $rws[] = $r;
        }

        if ($dlFlag && count($rws) > 0) {
            $file = "VolunteerTimeReport";
            $writer = new ExcelHelper($file);
            $writer->setAuthor($uname);
            $writer->setTitle("Volunteer Time Report");

            // Header row
            $hdr = array();
            $colWidths = array();

            foreach ($rows[0] as $k => $v) {
                if($k == "Hours"){
                    $hdr[$k] = "price";
                    $colWidths[] = "10";
                }else if ($k == "Email"){
                    $hdr[$k] = "string";
                    $colWidths[] = "35";
                }else{
                    $hdr[$k] = "string";
                    $colWidths[] = "20";
                }
            }

            $hdrStyle = $writer->getHdrStyle($colWidths);
            $writer->writeSheetHeader("Worksheet", $hdr, $hdrStyle);

            // Summary sheet
            /* $sHdr = array("Filter"=>"string", "Parameters"=>"string");
            $sColWidths = array("50","50");
            $sHdrStyle = $writer->getHdrStyle($sColWidths);
            $writer->writeSheetHeader("Constraints", $sHdr, $sHdrStyle);

            // create summary table
            $sheet = array();
            foreach ($sumaryRows as $key => $val) {
                if ($key != "" && $val != "") {
                    $flds = array($key,$val);
                    $sheet[] = $writer->convertStrings($sHdr, $flds);
                }
            }
            $writer->writeSheet($sheet, "Constraints"); */

            // Main report
            foreach ($rws as $r) {
                $flds = array();
                foreach ($r as $v) {
                    $flds[] = $v;
                }
                $writer->writeSheetRow("Worksheet", $r);
            }
            $writer->download();
        } else {

            $txtReport = CreateMarkupFromDB::generateHTMLSummary($sumaryRows, $reportTitle);
            $txtReport .= CreateMarkupFromDB::generateHTML_Table($rws, "tblCategory");
        }
    }

    return $txtReport;
}

function makeSQL($whereClause, $groupBy, $sumHours) {
    $sum = "";
    $endPar = "";
    $showStartEnd = "";

    if ($sumHours !== false) {
        $sum = "SUM(";
        $endPar = ")";
    } else {
        $groupBy = "order by g.Description, vm.Name_Last";
        $showStartEnd = "c.E_Start as `Start Date`, c.E_End as `End Date`,
            case when c.E_Status = '" . VolCalendarStatus::Logged . "' then 'Logged' else 'Open' end as `Status`,";
    }

    return "select
    g.Description as `Category`,
    vm.Id AS `Id`,
    $sum TRUNCATE(TIMESTAMPDIFF(MINUTE, c.E_Start, c.E_End) / 60, 2) $endPar as `Hours`,
    $showStartEnd
    vm.Name_Last as `Last`,
    vm.Name_First as `First`,
    vm.Preferred_Phone AS `Phone`,
    vm.Preferred_Email AS `Email`,
    case
        when vm.Bad_Address = LOWER('true') then '*(Bad Address)'
        else (case
            when vm.Address_2 <> '' then concat(vm.Address_1, ', ', vm.Address_2)
            else vm.Address_1
        end)
    end AS `Address`,
    case
        when (vm.Bad_Address = LOWER('true')) then ''
        else vm.City
    end as `City`,
    case
        when (vm.Bad_Address = LOWER('true')) then ''
        else vm.StateProvince
    end as `State`,
    case
        when (vm.Bad_Address = LOWER('true')) then ''
        else vm.PostalCode
    end as `Zip Code`

from
    vmember_listing vm
    left join mcalendar c on vm.Id = c.idName or vm.Id = c.idName2
    left join gen_lookups g ON g.Table_Name = c.E_Vol_Category and g.Code = c.E_Vol_Code
where c.E_Status != 'd' and
        vm.MemberStatus = 'a' $whereClause $groupBy;";
}

function makeLogSQL($whereClause, $groupBy, $sumHours) {
    $sum = "";
    $endPar = "";
    $showStartEnd = "";

    if ($sumHours !== false) {
        $sum = "SUM(";
        $endPar = ")";
    } else {
        $groupBy = "order by g.Description, vm.Name_Last";
        $showStartEnd = "c.Start as `Start Date`, c.End as `End Date`,";
    }

    return "select
    g.Description as `Category`,
    vm.Id AS `Id`,
    $sum TRUNCATE(TIMESTAMPDIFF(MINUTE, c.Start, c.End) / 60, 2) $endPar as `Hours`,
    $showStartEnd
    vm.Name_Last as `Last`,
    vm.Name_First as `First`,
    vm.Preferred_Phone AS `Phone`,
    vm.Preferred_Email AS `Email`,
    case
        when vm.Bad_Address = LOWER('true') then '*(Bad Address)'
        else (case
            when vm.Address_2 <> '' then concat(vm.Address_1, ', ', vm.Address_2)
            else vm.Address_1
        end)
    end AS `Address`,
    case
        when (vm.Bad_Address = LOWER('true')) then ''
        else vm.City
    end as `City`,
    case
        when (vm.Bad_Address = LOWER('true')) then ''
        else vm.StateProvince
    end as `State`,
    case
        when (vm.Bad_Address = LOWER('true')) then ''
        else vm.PostalCode
    end as `Zip Code`

from
    vmember_listing vm
    left join volunteer_hours c on vm.Id = c.idName or vm.Id = c.idName2
    left join gen_lookups g ON g.Table_Name = c.Vol_Category and g.Code = c.Vol_Code
where
        vm.MemberStatus = 'a' $whereClause $groupBy;";
}
?>