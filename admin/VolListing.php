<?php

use HHK\sec\{Session, UserClass, WebInit, SecurityComponent};
use HHK\sec\Labels;

/**
 * VolListing.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");


$wInit = new webInit();
$dbh = $wInit->dbh;
$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;
$uS = Session::getInstance();

// Get labels
$labels = Labels::getLabels();
$menuMarkup = $wInit->generatePageMenu();

//disable inactive users
$stmt = $dbh->query("select * from w_users");
if ($stmt->rowCount() > 0) {
    $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach($users as $user){
        $user = UserClass::disableInactiveUser($dbh, $user);
    }
}

// Create volunteer report
$query = "SELECT * FROM vweb_users order by Id;";
$stmt = $dbh->query($query);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$noReport = "";
$markup = "";
$isAdmin = false;

if($uS->rolecode == '10'){ //if Admin User
    $isAdmin = true;
}

if (count($rows) > 0) {
    // Table header row
    $markup = "<thead><tr>";
    if($isAdmin){
        $markup .= "<th>x</th>";
    }
    foreach ($rows[0] as $k => $v) {
        $markup .= "<th>$k</th>";
    }
    $markup .= "</tr></thead><tbody>";

    $clnRows = [];
    $id = 0;
    $collector = '';
    $lastRow = [];

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

        if ($rw["Id"] > 0 || SecurityComponent::is_TheAdmin()) { //hide Admin user unless logged in as The Admin

            $markup .= "<tr class='trClass" . $rw['Id'] . "' >";
            // peruse the fields in each row
            foreach ($rw as $k => $r) {

                if ($k == 'Id') {

                    if ($isAdmin) {
                        $markup .= "<td><input type='checkbox' id='delCkBox$r' name='$r' class='delCkBox' /></td>";
                    }

                    if ($r < 1) {
                        $markup .= "<td>$r</td>";
                    } else {
                        $markup .= "<td><a href='NameEdit.php?id=$r'>$r</a></td>";
                    }

                } else {
                    $markup .= "<td>$r</td>";
                }
            }
            $markup .= "</tr>";
        }
    }
    $markup .= "</tbody>";
} else {
    $noReport = "No Web Users";
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>

        <?php echo DEFAULT_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <link href="../css/datatables.min.css" rel="stylesheet">

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script src="../js/datatables2.min.js"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>

        <script type="text/javascript">

    $(document).ready(function() {

        let isAdmin = $('#isAdmin').val(),
            dateFormat = $('#dateFormat').val(),
            dateTimeFormat = $('#dateTimeFormat').val(),
            wUserCols;

        wUserCols = [
            {data: 'Id', title: 'id', sortable: false},
            {data: 'Name', title: 'Name'},
            {data: 'Username', title: 'Username'},
            {data: 'Type', title: 'Type'},
            {data: 'Status', title: 'Status'},
            {data: 'Role', title: 'Role'},
            {data: 'Default Page', title: 'Default Page'},
            {data: 'Authorization Code', title: 'Authorization Code', sortable: false},
            {data: 'Last Login', title: 'Last Login', render: function (data, type) {return dateRender(data, type, dateTimeFormat);}},
            {data: 'Password Changed', title: 'Password Changed', render: function (data, type) {return dateRender(data, type, dateTimeFormat);}},
            {data: 'Password Expires', title: 'Password Expires', render: function (data, type) {return dateRender(data, type, dateTimeFormat);}},
            {data: 'Two Factor Enabled', title: 'Two Factor Enabled'},
            {data: 'Updated By', title: 'Updated By'},
            {data: 'Last Updated', title: 'Last Updated', render: function (data, type) {return dateRender(data, type, dateFormat);}},
        ];

        if (isAdmin) {
            // Add to beginning of the array
            wUserCols.unshift( {data: 'x',  title: 'x', sortable:false} );
        }


        $('#dataTbl').dataTable({
            "displayLength": 25,
            order: false,
            "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
            columns: wUserCols,
            layout: {
                topStart: 'info',
                bottom: 'paging',
                bottomStart: null,
                bottomEnd: null
            }
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
            <div id="vollisting" class="ui-widget ui-widget-content ui-corner-all hhk-widget-content">
                <?php echo $noReport ?>
                <form autocomplete="off">
                <table id="dataTbl" class="display">
                    <?php echo $markup ?>
                </table>
                </form>
            </div>
        </div>
        <input id="isAdmin" type="hidden" value="<?php echo $isAdmin; ?>"/>
        <input  type="hidden" id="dateFormat" value='<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>' />
        <input  type="hidden" id="dateTimeFormat" value='<?php echo $labels->getString("momentFormats", "dateTime", "MMM D YYYY h:mm a"); ?>' />
    </body>
</html>
