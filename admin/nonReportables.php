<?php
/**
 * nonReportables.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");
require(CLASSES . "chkBoxCtrlClass.php");
require(CLASSES . "selCtrl.php");

$wInit = new webInit();
$dbh = $wInit->dbh;
$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();


// get excludeds
$query = "Select * from vnon_reporting_list;";
$stmt = $dbh->query($query);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
//$res = queryDB($dbcon, $query);
$markup = "";


if (count($rows) > 0) {
    // Table header row

    $markup = "<thead><tr>";
    foreach ($rows[0] as $k => $v) {
        //$finfo = mysqli_fetch_field_direct($res, $i);
        $markup .= "<th>" . $k . "</th>";
    }
    $markup .= "</tr></thead><tbody>";


    foreach ($rows as $rw) {

        $markup .= "<tr>";
        // peruse the fields in each row
        foreach ($rw as $k => $r) {



            if ($k == 'Id') {
                $fld = $r;
                $markup .= "<td><a href='NameEdit.php?id=$fld'>" . $fld . "</a></td>";
            } else {

                $fld = $r;
                $markup .= "<td>" . $fld . "</td>";
            }
        }
        $markup .= "</tr>";
    }

    $markup .= "</tbody>";
    //return $events;
} else {
    $markup = "No Non-Reportables Found";
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

        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PAG_JS; ?>"></script>
        <script type="text/javascript">
            // Init j-query
            $(document).ready(function() {
                try {
                    listTable = $('#tblCategory').dataTable({
                        "iDisplayLength": 50,
                        "aLengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                        "dom": '<"top"ilf>rt<"bottom"ip>'
                    });
                }
                catch (err) {}
            });
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?>>
<?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1 style="margin: 10px 5px;">View Non-Reporting Members, Bad Addresses & More.</h1>
            <p></p>
            <div style="font-size:.9em;" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail">
                <table id="tblCategory" cellpadding="0" cellspacing="0" border="0" class="display">
<?php echo $markup; ?>
                </table>
            </div>
            <div id="result"></div>
        </div>
    </body>
</html>
