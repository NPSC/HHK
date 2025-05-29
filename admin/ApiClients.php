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

$noReport = "";
$markup = "";
$isAdmin = false;

if($uS->rolecode == '10'){ //if Admin User
    $isAdmin = true;
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

            let dtAPIUsersCols = [
            {
                "targets": [1],
                "title": "Client ID",
                "searchable": false,
                "sortable": false,
                "data": "client_id",
            },
            {
                "targets": [2],
                "title": "Client Name",
                "searchable": false,
                "sortable": true,
                "data": "name",
            },
            {
                "targets": [3],
                "title": "Scopes",
                "searchable": false,
                "sortable": true,
                "data": "scopes",
                render: function (data, type) {
                    return data.split(',').join('<br/>');
                }
            },
            {
                "targets": [4],
                "title": "Issued To",
                "searchable": true,
                "sortable": true,
                "data": "issuedTo",
            },
            {
                "targets": [5],
                "title": "Lased Used",
                "searchable": true,
                "sortable": true,
                "data": "LastUsed",
                render: function (data, type) {
                    return dateRender(data, type, 'MMM D YYYY h:mm:ss a');
                }
            },
            {
                "targets": [6],
                "title": "Created At",
                "searchable": true,
                "sortable": true,
                "data": "Timestamp",
                render: function (data, type) {
                    return dateRender(data, type, 'MMM D YYYY h:mm:ss a');
                }
            },
        ];

        if (isAdmin) {
            // Add to beginning of the array
            dtAPIUsersCols.unshift( {data: 'client_id',  title: 'x', sortable:false} );
        }


        $('#dataTbl').dataTable({
            "displayLength": 25,
            order: false,
            "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
            columns: dtAPIUsersCols,
            layout: {
                topStart: 'info',
                bottom: 'paging',
                bottomStart: null,
                bottomEnd: null
            },
            "serverSide": true,
            "processing": true,
            ajax: {
                url: 'ws_gen.php',
                data: {
                    'cmd': 'showOauthClients'
                }
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
                </table>
                </form>
            </div>
        </div>
        <input id="isAdmin" type="hidden" value="<?php echo $isAdmin; ?>"/>
        <input  type="hidden" id="dateFormat" value='<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>' />
        <input  type="hidden" id="dateTimeFormat" value='<?php echo $labels->getString("momentFormats", "dateTime", "MMM D YYYY h:mm a"); ?>' />
    </body>
</html>
