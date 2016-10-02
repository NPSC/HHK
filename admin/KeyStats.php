<?php
/**
 * KeyStats.php
 *
 * @category  Reports
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
require_once ("AdminIncludes.php");

$wInit = new webInit();

$dbh = $wInit->dbh;



$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();



// Member category counts
// get the categories
$query = "select Code, Description from gen_lookups where Table_Name = 'Vol_Category' order by Description;";
    $stmt = $dbh->query($query);
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
//$res = queryDB($dbcon, $query);
$typedata = "";

foreach ($rows as $r) {

    $typedata .= "<td style='vertical-align: top;'><table><tr><th>$r[1]</th><th>Count</th></tr>";

    $query = "select ifnull(g.Description,'Not Assigned'), count(v.Vol_Code) as `count`
                from name_volunteer2 v join name n on v.idName = n.idName and `n`.`Member_Status` = 'a' and v.Vol_Category = '" . $r[0] . "'
                left join gen_lookups g on v.Vol_Code = g.Code and g.Table_Name = '" . $r[0] . "'
                where n.idName > 0 and v.Vol_Status = 'a' and ifnull(v.Vol_End,'2999/10/1') > now()
                group by v.Vol_Code;";
    $stmt = $dbh->query($query);
    $rows2 = $stmt->fetchAll(PDO::FETCH_NUM);
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
$query = "select ifnull(g.Description,'Not Assigned') as Description, count(n.Member_Status) as `count`
                from  name n left join gen_lookups g on n.Member_Status = g.Code and g.Table_Name = 'mem_status'
                where n.idName > 0
                group by n.Member_Status";
    $stmt = $dbh->query($query);
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
//$result2 = queryDB($dbcon, $query, true);
$statusdata = "";

foreach ($rows as $row2) {

    $statusdata .= "<tr><td>" . $row2[0] . "</td><td>" . $row2[1] . "</td></tr>";
}



// Member Basis counts
$query = "select ifnull(g.Description,'Not Assigned') as Description, count(n.Member_Type) as `count`
                from  name n left join gen_lookups g on n.Member_Type = g.Code and g.Table_Name = 'Member_Basis'
                where n.idName > 0 and n.Member_Status = 'a'
                group by n.Member_Type";
    $stmt = $dbh->query($query);
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
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
$query = "select  (
                select count(*) from name_volunteer2 v1 join name_volunteer2 v2 on v1.idName = v2.idName
                join name n on v1.idName = n.idName and n.Member_Status = 'a'
                where  v1.Vol_Code = 'g' and v2.Vol_Code = 'd' and v1.Vol_Category = 'Vol_Type'  and v1.Vol_Status='a' and v2.Vol_Status='a' and v2.Vol_Category = 'Vol_Type'
                )
                 / (
                select count(*) from name_volunteer2 v1 join name n on v1.idName = n.idName and n.Member_Status = 'a' and v1.Vol_Status='a' and v1.Vol_Category = 'Vol_Type'
                where n.idName > 0 and v1.Vol_Code = 'g'
                ) * 100 as prcent;";
    $stmt = $dbh->query($query);
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
//$result2 = queryDB($dbcon, $query, true);
$rw = $rows[0];


$guestDonors = number_format($rw[0], 2);
$guest = number_format(100 - $rw[0], 2);

$query = "select  (
                select count(*) from name_volunteer2 v1 join name_volunteer2 v2 on v1.idName = v2.idName
                join name n on v1.idName = n.idName
                where n.idName > 0 and n.Member_Status = 'a' and v1.Vol_Code = 'd' and v1.Vol_Status='a' and v2.Vol_Status='a' and v2.Vol_Code = 'Vol'  and v1.Vol_Category = 'Vol_Type' and v2.Vol_Category = 'Vol_Type')
                 /
                 (select count(*) from name_volunteer2 v1 join name n on v1.idName = n.idName and n.Member_Status = 'a' and v1.Vol_Category = 'Vol_Type'
                where n.idName > 0 and v1.Vol_Code = 'Vol' and v1.Vol_Status='a')
                * 100 as prcent;";
    $stmt = $dbh->query($query);
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
//$result2 = queryDB($dbcon, $query, true);
$rw = $rows[0];


$DonorVolunteers = number_format($rw[0], 2);
$volunteer = number_format(100 - $rw[0], 2);


// members not in the name_volunteer file
$query = "select count(n.idName) from name n left join name_volunteer2 v on n.idName = v.idName
        where n.idName > 0 and v.idName is null and n.Member_Status = 'a';";
    $stmt = $dbh->query($query);
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
//$result2 = queryDB($dbcon, $query);
$rw =$rows[0];

$notVolunteers = $rw[0];

    $stmt = $dbh->query($query);
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
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
$query = "Select Description from gen_lookups where Table_Name = 'mem_status' and Substitute = 'm' order by Code;";
    $stmt = $dbh->query($query);
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
//$res = queryDB($dbcon, $query);

foreach ($rows as $rw) {
    $header .= "<th>" . $rw[0] . "</th>";
    $line[$rw[0]] = 0;
}


$preHeader .= "<th colspan='" . (count($line) + 1) . "'>Valid Member Statuses</th>";
$header .= "<th style='border-right: 1px solid #D4CCB0;'>Sub Total</th>";

// get "bad" status types
$query = "Select Description from gen_lookups where Table_Name = 'mem_status' and Substitute <> 'm' order by Code;";
    $stmt = $dbh->query($query);
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
//$res = queryDB($dbcon, $query);

foreach ($rows as $rw) {
    $header .= "<th>" . $rw[0] . "</th>";
    $badLine[$rw[0]] = 0;
}


$preHeader .= "<th colspan='" . count($badLine) . "'>Invalid Member Statuses</th><th></th></tr>";

// Grand total for header
$header .= "<th>Total</th></tr>";

// Get the data
$query = "select gc.Description as `Category`, g.Description as `Committee`, gs.Description as `Status`,
v.Vol_Category, v.Vol_Code, n.Member_Status, count(n.Member_Status) as `Count`
from name_volunteer2 v left join name n on n.idName = v.idName
left join gen_lookups gc on gc.Table_Name = 'Vol_Category' and gc.Code = v.Vol_Category
left join gen_lookups g on g.Table_Name = v.Vol_Category and g.Code = v.Vol_Code
left join gen_lookups gs on gs.Table_Name = 'mem_status' and gs.Code = n.Member_Status
where v.Vol_Status = 'a'
group by v.Vol_Category, v.Vol_Code, n.Member_Status with rollup;";
    $stmt = $dbh->query($query);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        <title><?php echo $pageTitle; ?></title>
        <link href="css/default.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />
        <link href="<?php echo JQ_DT_CSS; ?>" rel="stylesheet" type="text/css" />
<?php echo TOP_NAV_CSS; ?>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="../js/jquery.gchart.js"></script>
        <script type="text/javascript">
            // Init j-query.
            $(document).ready(function() {
                var gdData = [$.gchart.series([<?php echo $guestDonors; ?>, <?php echo($guest); ?>])];
                var vdData = [$.gchart.series([<?php echo $DonorVolunteers; ?>, <?php echo($volunteer); ?>])];
                var notVolData = [$.gchart.series([<?php echo $notVolunteers; ?>, <?php echo($totalMembers); ?>])];

                gdData[0].color = ['327E04'];
                notVolData[0].color = ['002EE6'];

                $( "#tabs" ).tabs();
                $("#gdChart").gchart({series: gdData, backgroundColor: 'f5f3e5', dataLabels: ['Guest Donors', 'Guests']});
                $("#vdChart").gchart({series: vdData, backgroundColor: 'f5f3e5', dataLabels: ['Vol. Donors', 'Volunteers']});
                $("#nvChart").gchart({series: notVolData, backgroundColor: 'f5f3e5', dataLabels: ['Unregistered', 'Registered']});
                detailsDT = $('#dataTbl').dataTable({
                    "aoColumnDefs": [{
                            "sType": 'numeric',
                            "aTargets": [ 2,3,4,6,7,8 ]
                        }],
                    "iDisplayLength": 25,
                    "aLengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                    "sDom": '<"top"ilfp>rt<"bottom"p>'
                });
            });
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?>>
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <div id="tabs">
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
