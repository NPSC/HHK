<?php

use HHK\sec\WebInit;
use HHK\Vite\Vite;

/**
 * KeyStats.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require_once ("AdminIncludes.php");

$wInit = new WebInit();

$dbh = $wInit->dbh;



$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();



// Member category counts
// get the categories
$query = "SELECT `Code`, `Description` FROM `gen_lookups` WHERE `Table_Name` = 'Vol_Category' ORDER BY `Description`;";
    $stmt = $dbh->prepare($query);
    $stmt->execute();
    $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
//$res = queryDB($dbcon, $query);
$typedata = "";

$volCategoryStmt = $dbh->prepare("SELECT IFNULL(`g`.`Description`,'Not Assigned'), COUNT(`v`.`Vol_Code`) AS `count`
                FROM `name_volunteer2` `v` JOIN `name` `n` ON `v`.`idName` = `n`.`idName` AND `n`.`Member_Status` = 'a' AND `v`.`Vol_Category` = :volCategory1
                LEFT JOIN `gen_lookups` `g` ON `v`.`Vol_Code` = `g`.`Code` AND `g`.`Table_Name` = :volCategory2
                WHERE `n`.`idName` > 0 AND `v`.`Vol_Status` = 'a' AND IFNULL(`v`.`Vol_End`,'2999/10/1') > NOW()
                GROUP BY `v`.`Vol_Code`;");

foreach ($rows as $r) {

    $typedata .= "<td style='vertical-align: top;'><table><tr><th>$r[1]</th><th>Count</th></tr>";

    $volCategoryStmt->execute([':volCategory1' => $r[0], ':volCategory2' => $r[0]]);
    $rows2 = $volCategoryStmt->fetchAll(\PDO::FETCH_NUM);
    //$result2 = queryDB($dbcon, $query, true);

    $cntr = 0;

    foreach ($rows2 as $row2) {

        $typedata .= "<tr><td>" . $row2[0] . "</td>
            <td style='text-align:center;'>" . $row2[1] . "</td></tr>";
        $cntr = $cntr + $row2[1];
    }

    $typedata .= "</table></td>";
}



// Member status counts
$query = "SELECT IFNULL(`g`.`Description`,'Not Assigned') AS `Description`, COUNT(`n`.`Member_Status`) AS `count`
                FROM  `name` `n` LEFT JOIN `gen_lookups` `g` ON `n`.`Member_Status` = `g`.`Code` AND `g`.`Table_Name` = 'mem_status'
                WHERE `n`.`idName` > 0
                GROUP BY `n`.`Member_Status`";
    $stmt = $dbh->prepare($query);
    $stmt->execute();
    $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
//$result2 = queryDB($dbcon, $query, true);
$statusdata = "";

foreach ($rows as $row2) {

    $statusdata .= "<tr><td>" . $row2[0] . "</td><td>" . $row2[1] . "</td></tr>";
}



// Member Basis counts
$query = "SELECT IFNULL(`g`.`Description`,'Not Assigned') AS `Description`, COUNT(`n`.`Member_Type`) AS `count`
                FROM  `name` `n` LEFT JOIN `gen_lookups` `g` ON `n`.`Member_Type` = `g`.`Code` AND `g`.`Table_Name` = 'Member_Basis'
                WHERE `n`.`idName` > 0 AND `n`.`Member_Status` = 'a'
                GROUP BY `n`.`Member_Type`";
    $stmt = $dbh->prepare($query);
    $stmt->execute();
    $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
//$result2 = queryDB($dbcon, $query, true);
$basisdata = "";
$basisChartData = "";
$basisChartLabels = "";

foreach ($rows as $row2) {

    $basisdata .= "<tr><td>" . $row2[0] . "</td><td>" . $row2[1] . "</td></tr>";
    $basisChartData .= "$row2[1],";
    $basisChartLabels .= "'$row2[0]',";
}

// de-comma-fie
$basisChartData = substr($basisChartData, 0, strlen($basisChartData) - 1);
$basisChartLabels = substr($basisChartLabels, 0, strlen($basisChartLabels) - 1);


// Guest percentages
$query = "SELECT  (
                SELECT COUNT(*) FROM `name_volunteer2` `v1` JOIN `name_volunteer2` `v2` ON `v1`.`idName` = `v2`.`idName`
                JOIN `name` `n` ON `v1`.`idName` = `n`.`idName` AND `n`.`Member_Status` = 'a'
                WHERE  `v1`.`Vol_Code` = 'g' AND `v2`.`Vol_Code` = 'd' AND `v1`.`Vol_Category` = 'Vol_Type'  AND `v1`.`Vol_Status`='a' AND `v2`.`Vol_Status`='a' AND `v2`.`Vol_Category` = 'Vol_Type'
                )
                 / (
                SELECT COUNT(*) FROM `name_volunteer2` `v1` JOIN `name` `n` ON `v1`.`idName` = `n`.`idName` AND `n`.`Member_Status` = 'a' AND `v1`.`Vol_Status`='a' AND `v1`.`Vol_Category` = 'Vol_Type'
                WHERE `n`.`idName` > 0 AND `v1`.`Vol_Code` = 'g'
                ) * 100 AS `prcent`;";
    $stmt = $dbh->prepare($query);
    $stmt->execute();
    $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
//$result2 = queryDB($dbcon, $query, true);
$rw = $rows[0];


$guestDonors = number_format($rw[0], 2);
$guest = number_format(100 - $rw[0], 2);

$query = "SELECT  (
                SELECT COUNT(*) FROM `name_volunteer2` `v1` JOIN `name_volunteer2` `v2` ON `v1`.`idName` = `v2`.`idName`
                JOIN `name` `n` ON `v1`.`idName` = `n`.`idName`
                WHERE `n`.`idName` > 0 AND `n`.`Member_Status` = 'a' AND `v1`.`Vol_Code` = 'd' AND `v1`.`Vol_Status`='a' AND `v2`.`Vol_Status`='a' AND `v2`.`Vol_Code` = 'Vol'  AND `v1`.`Vol_Category` = 'Vol_Type' AND `v2`.`Vol_Category` = 'Vol_Type')
                 /
                 (SELECT COUNT(*) FROM `name_volunteer2` `v1` JOIN `name` `n` ON `v1`.`idName` = `n`.`idName` AND `n`.`Member_Status` = 'a' AND `v1`.`Vol_Category` = 'Vol_Type'
                WHERE `n`.`idName` > 0 AND `v1`.`Vol_Code` = 'Vol' AND `v1`.`Vol_Status`='a')
                * 100 AS `prcent`;";
    $stmt = $dbh->prepare($query);
    $stmt->execute();
    $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
//$result2 = queryDB($dbcon, $query, true);
$rw = $rows[0];


$DonorVolunteers = number_format((is_null($rw[0]) ? 0 : $rw[0]), 2);
$volunteer = number_format(100 - (is_null($rw[0]) ? 0 : $rw[0]), 2);


// members not in the name_volunteer file
$query = "SELECT COUNT(`n`.`idName`) FROM `name` `n` LEFT JOIN `name_volunteer2` `v` ON `n`.`idName` = `v`.`idName`
        WHERE `n`.`idName` > 0 AND `v`.`idName` IS NULL AND `n`.`Member_Status` = 'a';";
    $stmt = $dbh->prepare($query);
    $stmt->execute();
    $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
//$result2 = queryDB($dbcon, $query);
$rw =$rows[0];

$notVolunteers = $rw[0];

    $stmt = $dbh->prepare($query);
    $stmt->execute();
    $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
//$result2 = queryDB($dbcon, "Select count(n.idName) from name n where n.idName > 0 and n.Member_Status = 'a'");
$rw = $rows[0];

$totalMembers = $rw[0];
$num = number_format($notVolunteers, 0);
$notVolunteersMkup = getPieChartMarkup("Members who are not Volunteers, Donors or Guests: $num", "nvChart");




/*
 *  Details
 */
$data = "";
$detailMkup = "";
$line = array();
$badLine = array();
$header = "<tr><th>Catagory</th><th>Committee</th>";
$preHeader = "<tr><th colspan='2'>";


// get "good" status types
$query = "SELECT `Description` FROM `gen_lookups` WHERE `Table_Name` = 'mem_status' AND `Substitute` = 'm' ORDER BY `Code`;";
    $stmt = $dbh->prepare($query);
    $stmt->execute();
    $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
//$res = queryDB($dbcon, $query);

foreach ($rows as $rw) {
    $header .= "<th>" . $rw[0] . "</th>";
    $line[$rw[0]] = 0;
}


$preHeader .= "<th colspan='" . (count($line) + 1) . "'>Valid Member Statuses</th>";
$header .= "<th style='border-right: 1px solid #D4CCB0;'>Sub Total</th>";

// get "bad" status types
$query = "SELECT `Description` FROM `gen_lookups` WHERE `Table_Name` = 'mem_status' AND `Substitute` <> 'm' ORDER BY `Code`;";
    $stmt = $dbh->prepare($query);
    $stmt->execute();
    $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
//$res = queryDB($dbcon, $query);

foreach ($rows as $rw) {
    $header .= "<th>" . $rw[0] . "</th>";
    $badLine[$rw[0]] = 0;
}


$preHeader .= "<th colspan='" . count($badLine) . "'>Invalid Member Statuses</th><th></th></tr>";

// Grand total for header
$header .= "<th>Total</th></tr>";

// Get the data
$query = "SELECT `gc`.`Description` AS `Category`, `g`.`Description` AS `Committee`, `gs`.`Description` AS `Status`,
`v`.`Vol_Category`, `v`.`Vol_Code`, `n`.`Member_Status`, COUNT(`n`.`Member_Status`) AS `Count`
FROM `name_volunteer2` `v` LEFT JOIN `name` `n` ON `n`.`idName` = `v`.`idName`
LEFT JOIN `gen_lookups` `gc` ON `gc`.`Table_Name` = 'Vol_Category' AND `gc`.`Code` = `v`.`Vol_Category`
LEFT JOIN `gen_lookups` `g` ON `g`.`Table_Name` = `v`.`Vol_Category` AND `g`.`Code` = `v`.`Vol_Code`
LEFT JOIN `gen_lookups` `gs` ON `gs`.`Table_Name` = 'mem_status' AND `gs`.`Code` = `n`.`Member_Status`
WHERE `v`.`Vol_Status` = 'a'
GROUP BY `v`.`Vol_Category`, `v`.`Vol_Code`, `n`.`Member_Status` WITH ROLLUP;";
    $stmt = $dbh->prepare($query);
    $stmt->execute();
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
//$res = queryDB($dbcon, $query);

foreach ($rows as $rw) {

    // For each Category
    if (is_null($rw["Vol_Category"])) {
        // End category, total of all categories, end of report
        //$data .= "<tr><td>". $rw["Category"] . "</td>";
    } else if (is_null($rw["Vol_Code"])) {
        // End Committee, Total for Category
        //$data .= "<td>". $rw["Committee"] . "</td>";
    } else if (is_null($rw["Member_Status"])) {
        // End Status, total for Committee
        $data .= "<tr><td>" . $rw["Category"] . "</td><td>" . $rw["Committee"] . "</td>";

        // run through good statuses
        $tot = 0;
        foreach ($line as $k => $c) {
            $data .= "<td style='text-align:center;'>" . $c . "</td>";
            $tot = $tot + $c;
            $line[$k] = 0;
        }
        $data .= "<td style='text-align:center; font-weight:bold;'>" . $tot . "</td>";

        // run through bad statuses

        foreach ($badLine as $k => $c) {
            $data .= "<td style='text-align:center;'>" . $c . "</td>";

            $badLine[$k] = 0;
        }
        // total for committee
        $data .= "<td style='text-align:center;'>" . $rw["Count"] . "</td></tr>";
    } else {
        // capture status count
        if (array_key_exists($rw["Status"], $line)) {
            $line[$rw["Status"]] = $rw["Count"];
        } else {
            $badLine[$rw["Status"]] = $rw["Count"];
        }
    }
}


// put markup together
$detailMkup = "<p>Note:  Excludes \"Retired\" members.</p>";
$detailMkup .= "<table id='dataTbl' class='display'><thead>" . $preHeader . $header . "</thead><tbody>" . $data . "</tbody></table>";




function getPieChartMarkup($title, $chartId, $width = "335", $height = "120") {

    $mkup = "<div><p>$title</p><div id='$chartId' style='width:" . $width . "px;height:" . $height . "px;'></div></div>";
    return $mkup;
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>

        <?php echo Vite::asset('resources/js/admin.js'); ?>
        
        <?php echo FAVICON; ?>

        <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", () => {

                $( "#tabs" ).tabs();

                detailsDT = $('#dataTbl').dataTable({
                    "aoColumnDefs": [{
                            "sType": 'numeric',
                            "aTargets": [ 2,3,4,6,7,8 ]
                        }],
                    "iDisplayLength": 25,
                    "aLengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                });
            });
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?>>
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div id="tabs" style="font-size:.9em;">
                <ul>
                    <li><a href="#tabs-1">Member Statistics</a></li>
                    <li><a href="#tabs-2">Detailed Spreadsheet</a></li>
                </ul>
                <div id="tabs-1" class="ui-tabs-hide">
                    <h2>Key Member Statistics</h2>
                    <table>
                        <tr>
                            <td rowspan="2" style="vertical-align: top;">
                                <table>
                                    <tr>
                                        <th>Member Status</th>
                                        <th>Count</th>
                                    </tr>
                                        <?php echo $statusdata; ?>
                                    <tr style="border-top: 2px solid black;">
                                        <th>Member Basis</th>
                                        <th>Count</th>
                                    </tr>
                                        <?php echo $basisdata; ?>
                                </table>
                            </td>
                            <th colspan="4">Volunteer Committee Count - Excludes "Retired" Members</th>
                        </tr>
                        <tr>
                            <?php echo $typedata; ?>
                        </tr>
                        <tr><td class="cleartd" colspan="4"><hr /></td></tr>
                        <tr>
                            <td class="cleartd" colspan="4">
                                <table style="width:100%;">
                                    <tr>
                                        <td >Guests that are Donors:</td>
                                        <td ><?php echo $guestDonors; ?>%</td>
                                        <td >Volunteers that are Donors</td>
                                        <td><?php echo $DonorVolunteers; ?>%</td>
                                    </tr>
                                    <tr>
                                        <td class="cleartd" colspan="2">
                                            <div id="gdChart" style="width:335px;height:120px;"></div>
                                        </td>
                                        <td class="cleartd" colspan="2">
                                            <div id="vdChart" style="width:335px;height:120px;"></div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr><td class="cleartd" colspan="4"><hr /></td>
                        </tr>
                        <tr>
                            <td colspan="2"><?php echo $notVolunteersMkup; ?></td>
                        </tr>
                    </table>
                </div>
                <div id="tabs-2" class="ui-tabs-hide">
                    <h2>Detailed Spreadsheet</h2>
                    <?php echo $detailMkup; ?>
                </div>
            </div>
        </div>
    </body>
</html>
