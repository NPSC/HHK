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
            dtAPIUsersCols.unshift( 
                {
                    data: 'client_id',  
                    title: '', 
                    sortable:false,
                    render(data, type){
                        return '<button type="button" class="edit-client ui-button ui-corner-all" data-client-id="' + data + '">View</button>';
                    }
                } 
            );
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

        var dtAPIAccessLogCols = [
        {
            targets: [0],
            className: 'dt-control',
            orderable: false,
            data: null,
            defaultContent: ''
        },
        {
            "targets": [1],
            "title": "Request Endpoint",
            "searchable": false,
            "sortable": false,
            "data": "requestPath",
        },
        {
            "targets": [2],
            "title": "Response Code",
            "searchable": false,
            "sortable": true,
            "data": "responseCode",
        },
        {
            "targets": [3],
            "title": "OAuth Client ID",
            "searchable": false,
            "sortable": true,
            "data": "oauth_client_id",
        },
        {
            "targets": [4],
            "title": "oauth_access_token_id",
            "searchable": true,
            "sortable": true,
            "data": "oauth_access_token_id",
            "visible": false,
        },
        {
            "targets": [5],
            "title": "Request",
            "searchable": true,
            "sortable": true,
            "data": "request",
            "visible": false,
        },
        {
            "targets": [6],
            "title": "Response",
            "searchable": true,
            "sortable": true,
            "data": "response",
            "visible": false,
        },
        {
            "targets": [7],
            "title": "IP Address",
            "searchable": true,
            "sortable": true,
            "data": "ip_address",
        },
        {
            "targets": [8],
            "title": "Timestamp",
            'data': 'Timestamp',
            render: function (data, type) {
                return dateRender(data, type, 'MMM D YYYY h:mm:ss a');
            }
        }
    ];

    
    window.client_id = 0;
    let logTable = $('#clientAccessLog').dataTable({
                        "columnDefs": dtAPIAccessLogCols,
                        "serverSide": true,
                        "processing": true,
                        //"deferRender": true,
                        "language": { "sSearch": "Search Log:" },
                        "sorting": [[8, 'desc']],
                        "displayLength": 25,
                        "lengthMenu": [[25, 50, 100], [25, 50, 100]],
                        layout: {
                            topStart: 'info',
                            bottom: 'paging',
                            bottomStart: null,
                            bottomEnd: null
                        },
                        ajax: {
                            url: 'ws_gen.php',
                            data: function(d){
                                d.cmd = 'showAPIAccessLog',
                                d.clientId = window.client_id
                            }
                        }
                    });

                    logTable.on('click', 'td.dt-control', function (e) {
                        let tr = e.target.closest('tr');
                        let row = logTable.DataTable().row(tr);
                    
                        if (row.child.isShown()) {
                            // This row is already open - close it
                            row.child.hide();
                        }
                        else {
                            // Open this row
                            row.child(formatAPIDetails(row.data())).show();
                        }
                    });

                    function formatAPIDetails(row){
        return `
            <div>` +
            (row.oauth_access_token_id ?
                `<div class="mb-3">
                    <strong>Access Token ID</strong>
                    <pre style="white-space: pre-wrap;">${row.oauth_access_token_id}</pre>
                </div>` : '') +
                `<div class="mb-3">
                    <strong>Request</strong>
                    <pre style="white-space: pre-wrap;">${row.request}</pre>
                </div>
                <div>
                    <strong>Response</strong>
                    <pre style="white-space: pre-wrap;">${row.response}</pre>
                </div>
            </div>
        `;
    }
        $('#dataTbl').on('click', 'button.edit-client', function(){
            window.client_id = $(this).data("client-id");
            $.ajax({
                url: 'ws_gen.php',
                data: {
                    'cmd': 'getOauthClient',
                    'clientId': client_id
                },
                dataType: 'json',
                success: function(data) {
                    let $dialog = $("#clientDetailsDialog");
                    $dialog.find("#client-id").text(data.client.client_id);
                    $dialog.find("#client-name").text(data.client.name);
                    $dialog.find("#client-secret").text(data.client.secret);
                    $dialog.find("#client-scopes").text(data.client.scopes);
                    $dialog.find("#client-issued-to").text(data.client.issuedTo);
                    $dialog.find("#client-issued-at").text(data.client.Timestamp);
                    $dialog.find("#client-last-used").text(data.client.LastUsed);


                    //access Tokens
                    let $tokenTbl = $dialog.find("table#accessTokens");
                    $tokenTbl.find("tbody").empty();
                    $.each(data.accessTokens, function(index, value) {
                        let $contentRow = $(`<tr>
                            <td>${value.client_id}</td>
                            <td>${value.scopes.join(", ")}</td>
                            <td>${value.Timestamp}</td>
                            <td>${value.expires_at}</td>
                            <td>${value.revoked}</td>
                        </tr>`);
                        $contentRow.appendTo($tokenTbl.find("tbody"));
                    });

                    logTable.DataTable().ajax.reload();

                    $dialog.dialog("open");
                }
            });
        });

        $("#clientDetailsDialog").dialog({
            autoOpen: false,
            width: 1500,
            height: 800,
            title: 'Client Details',
            modal: true,
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
        <div id="clientDetailsDialog" style="font-size: 0.9em;">
            <table style="width: 100%" id="clientDetailsTable" class="mb-3">
                <thead>
                    <th>Client ID</th>
                    <th>Client Name</th>
                    <th>Client Secret</th>
                    <th>Scopes</th>
                    <th>Issued To</th>
                    <th>Issued At</th>
                    <th>Last Used</th>
                </thead>
                <tr>
                    <td id="client-id"></td>
                    <td id="client-name"></td>
                    <td id="client-secret"></td>
                    <td id="client-scopes"></td>
                    <td id="client-issued-to"></td>
                    <td id="client-issued-at"></td>
                    <td id="client-last-used"></td>
                </tr>
            </table>
            <h3>Active Access Tokens</h3>
            <table style="width: 100%" id="accessTokens" class="mb-3">
                <thead>
                    <th>Client ID</th>
                    <th>Scopes</th>
                    <th>Issued At</th>
                    <th>Expires At</th>
                    <th>Revoked</th>
                </thead>
                <tbody>

                </tbody>
            </table>
            <h3>API Access Log</h3>
            <table id="clientAccessLog"></table>

            <pre id="client-raw"></pre>
        </div>
        <input id="isAdmin" type="hidden" value="<?php echo $isAdmin; ?>"/>
        <input  type="hidden" id="dateFormat" value='<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>' />
        <input  type="hidden" id="dateTimeFormat" value='<?php echo $labels->getString("momentFormats", "dateTime", "MMM D YYYY h:mm a"); ?>' />
    </body>
</html>
