<?php
/**
 * VolListing.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");


$wInit = new webInit();
$dbh = $wInit->dbh;
$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();


// Create volunteer report
$query = "SELECT * FROM vweb_users where Id > 0 order by Id;";
$stmt = $dbh->query($query);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$noReport = "";
$markup = "";

if (count($rows) > 0) {
    // Table header row
    $markup = "<thead><tr><th>x</th>";
    foreach ($rows[0] as $k => $v) {
        $markup .= "<th>" . $k . "</th>";
    }
    $markup .= "</tr></thead><tbody>";

    $clnRows = array();
    $id = 0;
    $collector = '';
    $lastRow = array();

    // Collect auth codes
    foreach ($rows as $rw) {

        if ($id != $rw['Id']) {
            // finish old row
            if ($id != 0) {
                $lastRow['Authorization Code'] = $collector;
                $clnRows[] = $lastRow;
            }

            $id = $rw['Id'];
            $collector = $rw['Authorization Code'];
            unset($lastRow);
            $lastRow = $rw;
        } else {
            // add this auth code
            $collector .= "<br/>" . $rw['Authorization Code'];
        }
    }

    // Finish last record
    if ($id != 0) {
        $lastRow['Authorization Code'] = $collector;
        $clnRows[] = $lastRow;
    }


    // peruse the rows
    foreach ($clnRows as $rw) {

        $markup .= "<tr class='trClass" . $rw['Id'] . "' >";
        // peruse the fields in each row
        foreach ($rw as $k => $r) {


            if ($k == 'Id') {
                $markup .= "<td><input type='checkbox' id='delCkBox" . $r . "' name='" . $r . "' class='delCkBox' /></td>";
                $markup .= "<td><a href='NameEdit.php?id=" . $r . "'>" . $r . "</a></td>";
            } else if ($k == 'Last Login') {
                $markup .= "<td>" . ($r == '' ? '' : date('m/d/Y g:ia', strtotime($r))) . "</td>";
            } else {
                $markup .= "<td>" . $r . "</td>";
            }
        }
        $markup .= "</tr>";
    }
    $markup .= "</tbody>";
} else {
    $noReport = "No Web Users";
}
$volReport = $markup;
?>
<!DOCTYPE html >
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />
        <link href="<?php echo JQ_DT_CSS; ?>" rel="stylesheet" type="text/css" />
        <?php echo DEFAULT_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript">
    $(document).ready(function() {

        $('#dataTbl').dataTable({
            "displayLength": 25,
            "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
            "Dom": '<"top"ilf>rt<"bottom"ip>'
        });
        $('div#vollisting').on('change', 'input.delCkBox', function() {
            if ($(this).prop('checked')) {
                var rep = confirm("Delete this record?");
                if (!rep) {
                    $(this).prop('checked', false);
                    return;
                }

                var usr = $(this).attr('name');
                deletewu(usr);
            }
        });
        function deletewu(usr) {
            $.get("liveNameSearch.php",
                    {cmd: "delwu", id: usr},
            function(data) {
                if (data != null && data != "") {
                    var names = $.parseJSON(data);
                    if (names[0])
                        names = names[0];

                    if (names && names.success) {
                        // all ok
                        $('tr.trClass' + usr).css({'display': 'none'});
                    }
                    else if (names.error) {
                        alert("Web Server error: " + names.error);
                    }
                    else {
                        alert("Empty data returned from the web server");
                    }
                }
                else {
                    alert('Nothing was returned from the web server');

                }
            }
            );
        }
    });
</script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?>>
<?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div id="vollisting" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail">
                <?php echo $noReport ?>
                <table id="dataTbl" cellpadding="0" cellspacing="0" border="0" class="display">
<?php echo $volReport ?>
                </table>
            </div>
        </div>

    </body>
</html>
