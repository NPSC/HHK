<?php

/**
 * ws_Report.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require("AdminIncludes.php");
require(CLASSES . "Campaign.php");

$wInit = new webInit(WebPageCode::Service);
$dbh = $wInit->dbh;


function campaignList(PDO $dbh, $yr) {
    // Year where-clause
    $headerYears = "";


    if ($yr != "" && strtolower($yr) != 'all') {
        $query = "select * from vdump_campaigns where year(Start)= :yr or year(End) = :yr;";
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':yr' => $yr));
        $headerYears = "$yr";
    } else {
        $query = "select * from vdump_campaigns;";
        $stmt = $dbh->query($query);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($stmt->rowCount() > 0) {
        // Table header row

        $markup = "<table><thead><tr>";

        foreach ($rows[0] as $k => $v) {
            $markup .= "<th class='hhk-listing'>" . $k . "</th>";
        }

        $markup .= "</tr></thead><tbody>";

        foreach ($rows as $rw) {

            $markup .= "<tr>";

            // peruse the fields in each row
            foreach ($rw as $r) {

                $markup .= "<td class='hhk-listing'>" . $r . "</td>";
            }

            $markup .= "</tr>";
        }

        $markup .= "</tbody></table>";
        //return $events;
        return array("success" => $markup);
    }
    return array("success" => "No Campaigns Found");
}

function campaignReport(PDO $dbh, $rbsel, $yr, $fyMonthsAdjust) {

    // Year where-clause
    $whClause = "";
    $headerYears = "";
    $pArray = NULL;

    if ($rbsel == "cy") {

        if ($yr != "" && $yr != 'all') {
            $whClause = " and year(d.Date_Entered)= :yr ";
            $pArray = array(':yr' => $yr);
            $headerYears = "$yr";
        }

        $query = "select c.Percent_Cut, year(d.Date_Entered) as fy, ifnull(c.Title, 'Sub Total'), ";
    } else {

        if ($yr != "" && $yr != 'all') {
            $whClause = " and year(DATE_ADD(d.Date_Entered, INTERVAL $fyMonthsAdjust MONTH))= :yr ";
            $pArray = array(':yr' => $yr);
            $headerYears = "$yr";
        }
        $query = "select c.Percent_Cut, year(DATE_ADD(d.Date_Entered, INTERVAL $fyMonthsAdjust MONTH)) as fy, ifnull(c.Title, 'Sub Total'), ";
    }

    $query .= " sum(d.Amount) as `Amount`,
            sum(d.Amount - (d.Amount * c.Percent_Cut / 100))
            from donations d join campaign c on LOWER(TRIM(d.Campaign_Code)) = LOWER(TRIM(c.Campaign_Code)) and d.Status = 'a'
            where c.Campaign_Type <> 'ink' $whClause group by fy, c.Title with rollup;";

    $stmt = $dbh->prepare($query);
    $stmt->execute($pArray);
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);



    if ($rbsel == "cy") {
        $txtreport = "<table><tr><td colspan='3' style='text-align:center;'><em>Campaign Donations by Calender Year Report</em></td><td> Date: " . date("M j, Y") . "</td></tr>";
        $txtreport .= "<tr><td></td><td><em>Fetched " . count($rows) . " Records</em></td></tr>";
        $txtreport .= "<tr><th>Year</th><th>Campaign</th><th style='text-align:right;'>Total</th>
            <th>% Cut</th><th style='text-align:right;'>Deduct</th><th style='text-align:right;'>Total</th></tr>";
    } else {
        $txtreport = "<table><tr><td colspan='3' style='text-align:center;'><em>Campaign Donations by Fiscal Year Report</em></td><td> Date: " . date("M j, Y") . "</td></tr>";
        $txtreport .= "<tr><td></td><td><em>Fetched " . count($rows) . " Records</em></td></tr>";
        $txtreport .= "<tr><th>Fiscal Year</th><th>Campaign</th><th style='text-align:right;'>Total</th>
            <th>% Cut</th><th style='text-align:right;'>Deduct</th><th style='text-align:right;'>Total</th></tr>";
    }



    foreach ($rows as $r) {

        $campMkup = $r[2];
        $percentMU = "";
        if ($r[0] != 0) {
            $percentMU = number_format($r[0], 0) . "%";
        }

        if (is_null($r[1])) {
            $campMkup = "Total";
            $class = "class='tdlabel'";
            $dateMkup = "";
            $percentMU = "";
        } else {
            if ($campMkup == "Sub Total") {
                //$dateMkup = "";
                $percentMU = "";
                $class = "class='tdlabel'";
            } else {
                $dateMkup = $r[1];
                $class = "";
            }
        }

        $totalMkup = number_format($r[3], 2);

        $finalTotalMU = '';
        if ($r[4] != '') {
            $finalTotalMU = number_format($r[4], 2);
        }

        $cut = ($r[3] - $r[4]);
        $cutMU = "";
        if ($cut == 0) {
            $cutMU = "";
        } else {
            $cutMU = "<span class='costDeduct'>" . number_format(($r[3] - $r[4]), 2) . "</span>";
        }

        if ($rbsel == "cy" && $dateMkup != "") {
            // Calendar Year
            $txtreport .= "<tr><td $class style='width:100px;'>" . $dateMkup . "</td><td $class>" . $campMkup . "</td>
                <td style='text-align:right;'>" . $totalMkup . "</td><td>" . $percentMU . "</td>
                <td style='text-align:right;'>" . $cutMU . "</td><td style='text-align:right;'>" . $finalTotalMU . "</td></tr>";
        } else {
            // Fiscal Year
            if ($dateMkup != "") {
                $dateMkup = "FY" . $dateMkup;
            }

            $txtreport .= "<tr><td $class style='width:100px;'>" . $dateMkup . "</td><td $class>" . $campMkup . "</td><td style='text-align:right;'>" . $totalMkup . "</td>
                <td>" . $percentMU . "</td><td style='text-align:right;'>" . $cutMU . "</td><td style='text-align:right;'>" . $finalTotalMU . "</td></tr>";
        }
    }

    //return $events;
    return $txtreport . "</table>";
}

function campaignInKindReport(PDO $dbh, $rbsel, $yr, $fyMonthsAdjust) {

    // Year where-clause
    $whClause = "";
    $headerYears = "";
    $pArray = NULL;

    if ($rbsel == "cy") {

        if ($yr != "" && $yr != 'all') {
            $whClause = " and year(d.Date_Entered)= :yr ";
            $pArray = array(':yr' => $yr);
            $headerYears = "$yr";
        }

        $query = "select c.Percent_Cut, year(d.Date_Entered) as fy, ifnull(c.Title, 'Sub Total'), ";
    } else {

        if ($yr != "" && $yr != 'all') {
            $whClause = " and year(DATE_ADD(d.Date_Entered, INTERVAL $fyMonthsAdjust MONTH))= :yr ";
            $pArray = array(':yr' => $yr);
            $headerYears = "$yr";
        }
        $query = "select c.Percent_Cut, year(DATE_ADD(d.Date_Entered, INTERVAL $fyMonthsAdjust MONTH)) as fy, ifnull(c.Title, 'Sub Total'), ";
    }

    $query .= "sum(d.Amount)
            from donations d join campaign c on LOWER(TRIM(d.Campaign_Code)) = LOWER(TRIM(c.Campaign_Code)) and d.Status = 'a'
            where c.Campaign_Type = 'ink' $whClause group by fy, c.Title with rollup;";

    $stmt = $dbh->prepare($query);
    $stmt->execute($pArray);
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);



    if ($rbsel == "cy") {
        $txtreport = "<table><tr><td colspan='3' style='text-align:center;'><em>In Kind Campaigns by Calender Year Report</em></td><td> Date: " . date("M j, Y") . "</td>";
        $txtreport .= "<td></td><td><em>Fetched " . count($rows) . " Records</em></td></tr>";
        $txtreport .= "<tr><th>Year</th><th>Campaign</th><th style='text-align:right;'>Total</th></tr>";
    } else {
        $txtreport = "<table><tr><td colspan='3' style='text-align:center;'><em>In Kind Campaigns by Fiscal Year Report</em></td><td> Date: " . date("M j, Y") . "</td></tr>";
        $txtreport .= "<tr><td></td><td><em>Fetched " . count($rows) . " Records</em></td></tr>";
        $txtreport .= "<tr><th>Fiscal Year</th><th>Campaign</th><th style='text-align:right;'>Total</th></tr>";
    }



    foreach ($rows as $r) {

        $campMkup = $r[2];

        if (is_null($r[1])) {
            $campMkup = "Total";
            $class = "class='tdlabel'";
            $dateMkup = "";
        } else {
            if ($campMkup == "Sub Total") {
                //$dateMkup = "";
                $class = "class='tdlabel'";
            } else {
                $dateMkup = $r[1];
                $class = "";
            }
        }

        $totalMkup = number_format($r[3], 2);


        if ($rbsel == "cy" && $dateMkup != "") {
            // Calendar Year
            $txtreport .= "<tr><td $class style='width:100px;'>" . $dateMkup . "</td><td $class>" . $campMkup . "</td>
                    <td style='text-align:right;'>" . $totalMkup . "</td></tr>";
        } else {
            // Fiscal Year
            if ($dateMkup != "") {
                $dateMkup = "FY" . $dateMkup;
            }

            $txtreport .= "<tr><td $class style='width:100px;'>" . $dateMkup . "</td><td $class>" . $campMkup . "</td><td style='text-align:right;'>" . $totalMkup . "</td></tr>";
        }
    }

    //return $events;
    return $txtreport . "</table>";
}



$c = "";
if (isset($_REQUEST["cmd"])) {
    $c = filter_var($_REQUEST["cmd"], FILTER_SANITIZE_STRING);
}

$events = array();

switch ($c) {
    case "fullcamp":

        //get
        $rb = filter_var(urldecode($_REQUEST["calyear"]), FILTER_SANITIZE_STRING);

        $yr = filter_var(urldecode($_REQUEST["rptyear"]), FILTER_SANITIZE_STRING);

        $fyMonths = filter_var(urldecode($_REQUEST["fymonths"]), FILTER_SANITIZE_STRING);

        $events = array('success' => campaignReport($dbh, $rb, $yr, $fyMonths) . campaignInKindReport($dbh, $rb, $yr, $fyMonths));

        break;


    case "roomrev":

        //get
        $rb = filter_var(urldecode($_REQUEST["calyear"]), FILTER_SANITIZE_STRING);

        $yr = filter_var(urldecode($_REQUEST["rptyear"]), FILTER_SANITIZE_STRING);

        $fyMonths = filter_var(urldecode($_REQUEST["fymonths"]), FILTER_SANITIZE_STRING);

        $events = array('success' => roomRevReport($dbh, $rb, $yr, $fyMonths));

        break;


    case "listcamp":

        $yr = filter_var(urldecode($_REQUEST["rptyear"]), FILTER_SANITIZE_STRING);

        $events = campaignList($dbh, $yr);

        break;

    case "demog":

        $intType = 'Y';
        $intVal = 1;
        $startDate = '2005-01-01';
        $endDate = '';
        $sourceZip = '';

        if (isset($_GET['intType'])) {
            $intType = filter_var($_GET['intType'], FILTER_SANITIZE_STRING);
        }

        if (isset($_GET['intVal'])) {
            $intVal = intVal(filter_var($_GET['intVal'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        try {
            $interval = new DateInterval('P' . $intVal . $intType);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }

        if (isset($_GET['stDate'])) {
            $startDate = filter_var($_GET['stDate'], FILTER_SANITIZE_STRING);
        }

        if (isset($_GET['enDate'])) {
            $endDate = filter_var($_GET['enDate'], FILTER_SANITIZE_STRING);
        }

        if (isset($_GET['szip'])) {
            $sourceZip = filter_var($_GET['szip'], FILTER_SANITIZE_STRING);
        }

        // Don't JSON encode this.
        return GuestReport::demogReport($dbh, $interval, $startDate, $endDate, $sourceZip);

        break;

    default:
        $events = array("error" => "Bad Command:  $c");
}


echo( json_encode($events) );
exit();

