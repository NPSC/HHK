// guestTransfer.js
//
var stopTransfer,
    $visitButton,
    $memberButton,
    $upsertButton,
    $psgCBs,
    $excCBs,
    $relSels,
    cmsTitle,
    username;


function updateLocal(id) {
    var postUpdate = $.post('ws_tran.php', {cmd: 'rmvAcctId', id: id});

    postUpdate.done(function (incmg) {
        $('div#retrieve').empty();

        if (!incmg) {
            alert('Bad Reply from Server');
            return;
        }

        if (incmg.error) {
            if (incmg.gotopage) {
                window.open(incmg.gotopage, '_self');
            }
            // Stop Processing and return.
            flagAlertMessage(incmg.error, true);
            return;
        }

        if (incmg.result) {
            flagAlertMessage(incmg.result, false);

        }
    });
}

function upsert(transferIds, trace) {
    const parms = {
        cmd: 'upsert',
        trace: trace,
        ids: transferIds
    };
    
    $('#divError').empty();
    $('#loadingIcon').show();

    var posting = $.post('ws_tran.php', parms);
    posting.done(function (incmg) {

        $('#loadingIcon').hide();

        if (!incmg) {
            alert('Error: Bad Reply from HHK Web Server');
            return;
        }

        var data = incmg;

        if (data.error || data.errors) {
            if (data.gotopage) {
                window.open(data.gotopage, '_self');
            }

            let errorMsg = "";
            if(data.error){
                errorMsg += "<p>"+data.error+"</p>";
            }

            if(data.errors){
                data.errors.forEach(element => {
                    errorMsg += "<p>"+element+"</p>"
                });
            }

            //flagAlertMessage(data.error, true);
            $('#divError').html($('<div class="ui-state-highlight ui-corner-all m-3 p-2"><h4>Error</h4><div class="ml-2">' + errorMsg + '</div></div>'));
            $('#TxButton').prop('disabled', false);

        }
        
        if (data.table) {
            $('#TxButton').hide();
            $('#divMembers').html(data.table);
            $('#divMembers').prepend($('<p style="font-weight: bold;">Transfer Results</p>'));
        }

        if (data.trace) {
            $('#divMembers').append(data.trace);
        }

    });
}

function updateRemote(id, accountId, useFlagAlert) {

    var postUpdate = $.post('ws_tran.php', {cmd: 'update', accountId: accountId, id: id});

    postUpdate.done(function (incmg) {
        $('#btnUpdate').remove();
        if (!incmg) {
            alert('Bad Reply from Server');
            return;
        }

        if (incmg.error) {
            if (incmg.gotopage) {
                window.open(incmg.gotopage, '_self');
            }
            // Stop Processing and return.
            flagAlertMessage(incmg.error, true);
            return;
        }

        if (incmg.warning) {

            flagAlertMessage(incmg.warning, true);

        } else if (incmg.result) {

            if (useFlagAlert) {
                flagAlertMessage(incmg.result, false);
            } else {
                let tr = '<tr style="border-top: 2px solid #2E99DD;">';
                tr += '<td colspan="10">' + incmg.result + '</td>';
                $('#mTbl').find('tbody').append(tr);
            }
        }

        if (! useFlagAlert) {
            throttleMembers();
        }

    });
}

function fillTable(incmg, $mTbl) {

    let tr = '';

    if (incmg) {

        if ($mTbl.length === 0) {

            // Create header row
            $mTbl = $('<table id="mTbl" style="margin-top:2px;"/>');

            tr = '<thead><tr>';
            for (let id in incmg) {
                for (let key in incmg[id]) {
                    tr += '<th>' + key + '</th>';
                }
                tr += '</tr></thead><tbody></tbody>';
                break;
            }

            $mTbl.append(tr);
            let title = $('<h3 style="margin-top:10px;">Processed ' + cmsTitle + ' Members</h3>');
            $('#divMembers').append(title).append($mTbl).show();
        }


        let first = 'style="border-top: 2px solid #2E99DD;"';
        for (let id in incmg) {

            tr = '<tr ' + first + '>';
            first = '';

            for (let key in incmg[id]) {
                tr += '<td>' + incmg[id][key] + '</td>';
            }
            tr += '</tr>';
        }

        $mTbl.find('tbody').append(tr);
        $('div#retrieve').empty().hide();

        $('div#printArea').show();
    }
}

function transferRemote(transferIds) {
    var parms = {
        cmd: 'members',
        ids: transferIds
    };

    var posting = $.post('ws_tran.php', parms);
    posting.done(function (incmg) {
        $('#btnUpdate').remove();

        if (!incmg) {
            alert('Error: Bad Reply from HHK Web Server');
            return;
        }

        if (incmg.error) {
            if (incmg.gotopage) {
                window.open(incmg.gotopage, '_self');
            }
            // Stop Processing and return.
            flagAlertMessage(incmg.error, true);

            return;
        }

        let tr = '';
        let $mTbl = $('#mTbl');


        if (incmg.members) {

            if ($mTbl.length === 0) {

                // Create header row
                $mTbl = $('<table id="mTbl" style="margin-top:2px;"/>');

                tr = '<thead><tr>';
                for (let id in incmg.members) {
                    for (let key in incmg.members[id]) {
                        tr += '<th>' + key + '</th>';
                    }
                    tr += '</tr></thead><tbody></tbody>';
                    break;
                }

                $mTbl.append(tr);
                let title = $('<h3 style="margin-top:10px;">Processed ' + cmsTitle + ' Members</h3>');
                $('#divMembers').append(title).append($mTbl).show();
            }


            let first = 'style="border-top: 2px solid #2E99DD;"';
            for (let id in incmg.members) {

                tr = '<tr ' + first + '>';
                first = '';

                for (let key in incmg.members[id]) {
                    tr += '<td>' + incmg.members[id][key] + '</td>';
                }
                tr += '</tr>';
            }

            $mTbl.find('tbody').append(tr);
            $('div#retrieve').empty().hide();

            $('div#printArea').show();
        }

        if ($memberButton !== null) {
            throttleMembers();
        }
    });

}

function throttleVisits() {

    if (stopTransfer) {
        $visitButton.val('Resume Visit Transfers');
        return;
    }

    let psgs = [];

    // do the excludes
    $excCBs.each(function () {

        if ($(this).prop('checked')) {

            psgs.push($(this).data('idpsg'));

            $('.hhk-' + $(this).data('idpsg')).css('background-color', 'lightgray');

            $(this).prop({'checked': false, 'disabled': true});

        }
    });

    if (psgs.length > 0) {
        transferExcludes(psgs);
    }

    let donut = true;

    // Do one at a time.
    $psgCBs.each(function () {

        if ($(this).prop('checked')) {

            donut = false;
            const props = {'checked': false, 'disabled': true};
            const rels = [];

            $('.hhk-' + $(this).data('idpsg')).css('background-color', 'lightgray');

            $(this).prop(props).end();

            // Prepare relationship assignments.
            $('.hhk-selRel' + $(this).data('idpsg')).each(function () {
                const rel = {'id': $(this).data('idname'), 'rel': $(this).val()};
                rels.push(rel);
            });

            transferVisits($(this).data('idpsg'), rels);

            return false;
        }
    });

    if (donut) {
        stopTransfer = true;
        $visitButton.val('Start Visit Transfers');
    }
}

function throttleMembers() {

    if (stopTransfer) {
        $('#TxButton').val('Resume Member Transfers');
        return;
    }

    let donut = true;

    // Do one at a time.
     $('input.hhk-tfmem').each(function () {

        if ($(this).prop('checked')) {

            donut = false;
            const props = {'checked': false, 'disabled': true};

            $(this).parents('tr').css('background-color', 'lightgray');

            $(this).prop(props).end();

            transferRemote($(this).data('txid'));

            return false;
        }
    });

    if (donut) {

        let upnut = true;

        if ($('input.hhk-updatemem').length == 0) {
            stopTransfer = true;
            $('#TxButton').val('Start Member Transfers');
            return;
        }

        $('input.hhk-updatemem').each(function() {
            if ($(this).prop('checked')) {

                upnut = false;
                const props = { 'checked': false, 'disabled': true };

                $(this).parents('tr').css('background-color', 'lightgray');

                $(this).prop(props).end();


                updateRemote($(this).data('txid'), $(this).data('txacct'), false);

                return false;

            }
        });

        if (upnut) {
            // Done
            stopTransfer = true;
            $('#TxButton').val('Start Updates');
            let tr = '<tr style="border-top: 2px solid #2E99DD;">';
            tr += '<th colspan="10" class="hhk-visitdialog">Updates</th>';
            $('#mTbl').find('tbody').append(tr);

        }
    }
}

function transferExcludes(psgs) {

    let parms = {
        cmd: 'excludes',
        psgIds: psgs
    };

    let posting = $.post('ws_tran.php', parms);

    posting.done(function (incmg) {

        if (!incmg) {
            alert('Bad Reply from HHK Web Server');
            return;
        }

        if (incmg.error) {
            if (incmg.gotopage) {
                window.open(incmg.gotopage, '_self');
            }
            // Stop Processing and return.
            flagAlertMessage(incmg.error, true);
            return;
        }

        let tr = '';
        let $eTbl = $('#eTbl');

        if (incmg.excludes) {

            if ($eTbl.length === 0) {

                // Create header row
                $eTbl = $('<table id="mTbl" style="margin-top:2px;"/>');

                tr = '<thead><tr>';
                for (let key in incmg.excludes[0]) {
                    tr += '<th>' + key + '</th>';
                }
                tr += '</tr></thead><tbody></tbody>';

                $eTbl.append(tr);
                let title = $('<h3 style="margin-top:7px;">Members Excluded from Neon</h3>');
                $('#divMembers').append(title).append($eTbl).show();
            }

            tr = '';
            for (let i = 0; i < incmg.excludes.length; i++) {

                tr += '<tr>';
                for (let key in incmg.excludes[i]) {
                    tr += '<td>' + incmg.excludes[i][key] + '</td>';
                }
                tr += '</tr>';
            }

            $eTbl.find('tbody').append(tr);
        }

    });
}

function transferVisits(idPsg, rels) {

    let parms = {
        cmd: 'visits',
        psgId: idPsg,
        rels: rels
    };

    let posting = $.post('ws_tran.php', parms);

    posting.done(function (incmg) {

        if (!incmg) {
            alert('Bad Reply from HHK Web Server');
            return;
        }


        if (incmg.error) {
            if (incmg.gotopage) {
                window.open(incmg.gotopage, '_self');
            }
            // Stop Processing and return.
            flagAlertMessage(incmg.error, true);
            return;
        }

        let tr = '';
        let $vTbl = $('#vTbl');
        let $mTbl = $('#mTbl');
        let $hTbl = $('#hTbl');
        let first = true;

        if (incmg.members) {

            if ($mTbl.length === 0) {

                // Create header row
                $mTbl = $('<table id="mTbl" style="margin-top:2px;"/>');

                tr = '<thead><tr>';
                for (let id in incmg.members) {
                    for (let key in incmg.members[id]) {
                        tr += '<th>' + key + '</th>';
                    }
                    tr += '</tr></thead><tbody></tbody>';
                    break;
                }

                $mTbl.append(tr);
                let title = $('<h3 style="margin-top:7px;">New ' + cmsTitle + ' Members</h3>');
                $('#divMembers').append(title).append($mTbl).show();
            }

            tr = '';
            first = 'style="border-top: 2px solid #2E99DD;"';
            for (let id in incmg.members) {

                tr = '<tr ' + first + '>';
                first = '';

                for (let key in incmg.members[id]) {
                    tr += '<td>' + incmg.members[id][key] + '</td>';
                }
                tr += '</tr>';

            }

            $mTbl.find('tbody').append(tr);
        }

        if (incmg.visits) {

            if ($vTbl.length === 0) {

                // Create header row
                $vTbl = $('<table id="vTbl" style="margin-top:2px;"/>');

                tr = '<thead><tr>';
                for (let key in incmg.visits[0]) {
                    tr += '<th>' + key + '</th>';
                }
                tr += '</tr></thead><tbody></tbody>';

                $vTbl.append(tr);
                let title = $('<h3 style="margin-top:7px;">Visit Information</h3>');
                $('#divMembers').append(title).append($vTbl).show();
            }

            tr = '';
            first = true;
            for (let i = 0; i < incmg.visits.length; i++) {

                if (first) {
                    first = false;
                    tr += '<tr style="border-top: 2px solid #2E99DD;">';
                } else {
                    tr += '<tr>';
                }

                for (let key in incmg.visits[i]) {
                    tr += '<td>' + incmg.visits[i][key] + '</td>';
                }
                tr += '</tr>';
            }

            $vTbl.find('tbody').append(tr);
        }

        if (incmg.households) {

            if ($hTbl.length === 0) {

                // Create header row
                $hTbl = $('<table id="hTbl" style="margin-top:2px;"/>');

                tr = '<thead><tr>';
                for (let key in incmg.households[0]) {
                    tr += '<th>' + key + '</th>';
                }
                tr += '</tr></thead><tbody></tbody>';

                $hTbl.append(tr);
                let title = $('<h3 style="margin-top:7px;">Households</h3>');
                $('#divMembers').append(title).append($hTbl).show();
            }

            tr = '';
            first = true;
            for (let i = 0; i < incmg.households.length; i++) {

                if (first) {
                    first = false;
                    tr += '<tr style="border-top: 2px solid #2E99DD;">';
                } else {
                    tr += '<tr>';
                }

                for (let key in incmg.households[i]) {
                    tr += '<td>' + incmg.households[i][key] + '</td>';
                }
                tr += '</tr>';
            }

            $hTbl.find('tbody').append(tr);
        }

        throttleVisits();
    });

    return;
}


function transferPayments($btn, start, end) {

    var parms = {
        cmd: 'payments',
        st: start,
        en: end
    };

    var posting = $.post('ws_tran.php', parms);

    posting.done(function (incmg) {
        $btn.hide();

        if (!incmg) {
            alert('Bad Reply from HHK Web Server');
            return;
        }

        if (incmg.error) {
            if (incmg.gotopage) {
                window.open(incmg.gotopage, '_self');
            }
            // Stop Processing and return.
            flagAlertMessage(incmg.error, true);
            return;
        }

        $('div#retrieve').empty();

        if (incmg.data) {
            $('#divTable').empty().append($(incmg.data)).show();
        }

        if (incmg.members) {
            $('#divMembers').empty().append($(incmg.members)).show();
        }

    });
}

function getRemote(item, source) {
    $('div#printArea').hide();
    $('#divPrintButton').hide();

    var posting = $.post('ws_tran.php', {cmd: 'getAcct', src: source, accountId: item.id, 'url': item.url});

    posting.done(function (incmg) {
        if (!incmg) {
            alert('Bad Reply from HHK Web Server');
            return;
        }

        if (incmg.error) {
            if (incmg.gotopage) {
                window.open(incmg.gotopage, '_self');
            }
            // Stop Processing and return.
            flagAlertMessage(incmg.error, true);
            return;
        }

        if (incmg.data) {
            $('div#retrieve').children().remove();
            $('div#retrieve').html(incmg.data);

            if (incmg.accountId == 'error') {
                return;
            }

            if (source === 'remote') {
                $('div#retrieve').prepend($('<h3>Remote Data</h3>')).show();
                $('#txtRSearch').val('');

            } else {

                var updteRemote = $('<input type="button" id="btnUpdate" value="" style="margin-left:.3em;" />');

                if (incmg.accountId === '') {
                    updteRemote.val('Transfer to Remote');
                    updteRemote.button().click(function () {

                        if ($(this).val() === 'Working...') {
                            return;
                        }
                        $(this).val('Working...');

                        transferRemote([item.id]);
                        $('div#localrecords').hide();
                    });
                } else if (incmg.accountId) {
                    updteRemote.val('Update Remote');
                    updteRemote.button().click(function () {

                        if ($(this).val() === 'Working...') {
                            return;
                        }
                        $(this).val('Working...');
                        updateRemote(item.id, incmg.accountId, true);
                    });
                } else {
                    updteRemote.remove();
                }

                $('div#retrieve').prepend($('<h3>Local (HHK) Data </h3>')).show();
                $('#txtSearch').val('');
            }
        }
    });
}

function setupLogViewer(){
    var dtTransferLogCols = [
        {
            targets: [0],
            className: 'dt-control',
            orderable: false,
            data: null,
            defaultContent: ''
        },
        {
            "targets": [1],
            "title": "Type",
            "searchable": false,
            "sortable": true,
            "data": "Type",
        },
        {
            "targets": [2],
            "title": "Request Endpoint",
            "searchable": false,
            "sortable": false,
            "data": "endpoint",
        },
        {
            "targets": [3],
            "title": "Response Code",
            "searchable": false,
            "sortable": true,
            "data": "responseCode",
        },
        {
            "targets": [4],
            "title": "Request",
            "searchable": true,
            "sortable": true,
            "data": "request",
            "visible": false,
        },
        {
            "targets": [5],
            "title": "Response",
            "searchable": true,
            "sortable": true,
            "data": "response",
            "visible": false,
        },
        {
            "targets": [6],
            "title": "User",
            "searchable": true,
            "sortable": true,
            "data": "username",
        },
        {
            "targets": [7],
            "title": "Timestamp",
            'data': 'Timestamp',
            render: function (data, type) {
                return dateRender(data, type, 'MMM D YYYY h:mm:ss a');
            }
        }
    ];

    
    let logTable = $('#transferLog').dataTable({
                        "columnDefs": dtTransferLogCols,
                        "serverSide": true,
                        "processing": true,
                        //"deferRender": true,
                        "language": { "sSearch": "Search Log:" },
                        "sorting": [[7, 'desc']],
                        "displayLength": 25,
                        "lengthMenu": [[25, 50, 100], [25, 50, 100]],
                        'dom': '<"top"if><"hhk-overflow-x hhk-tbl-wrap"rt><"bottom"lp><"clear">',
                        'autoWidth':false,
                        layout: {
                            topStart: 'info',
                            bottom: 'paging',
                            bottomStart: null,
                            bottomEnd: null
                        },
                        ajax: {
                            url: 'ws_tran.php',
                            data: function(d){
                                d.cmd = 'viewLog',
                                d.service = $("#cmsLogService").val()
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
}


$(document).ready(function () {

    var makeTable = $('#hmkTable').val();
    var start = $('#hstart').val();
    var end = $('#hend').val();
    var dateFormat = $('#hdateFormat').val();
    cmsTitle = $('#cmsTitle').val();
    username = $('#username').val();

    $('#btnHere, #btnCustFields, #btnGetPayments, #btnGetVisits, #btnGetKey, #btnRequest').button();

    $('#printButton').button().click(function () {
        $("div#printArea").printArea();
    });

    if (makeTable == 0) {
        // Salesforce Transfer
        $('div#printArea').show();
        $('#divPrintButton').show();
        $('#btnPay').hide();
        $('#btnVisits').hide();
        $('#divMembers').empty();

        // Checkbutton to show server trace.
        const $cbTrace = $('#cbTraceWrapper');
        $cbTrace.hide();
        if (username == 'npscuser') {
            $cbTrace.show();
        }

        $upsertButton = $('#TxButton');

        $upsertButton
            .button()
            .val('Transfer to '+ cmsTitle)
            .show()
            // click event
            .click(function () {
                let ids = [];
                let n = 0;

                $(this).prop('disabled', true);

                // Loop through the checked names
                $('input.hhk-tfmem').each(function () {

                    if ($(this).prop('checked')) {

                        const props = { 'checked': false, 'disabled': true };

                        $(this).parents('tr').css('background-color', 'lightgray');

                        $(this).prop(props).end();

                        ids[n++] = $(this).data('txid');

                    }
                });

                upsert(ids, $cbTrace.find("input").prop('checked'));
            });

    } // end salesforce xfer.

    // Retrieve HHK Records
    if (makeTable === '1') {

        $('div#printArea').show();
        $('#divPrintButton').show();
        $('#btnPay').hide();
        $('#btnVisits').hide();
        $('#divMembers').empty();


        $memberButton = $('#TxButton');

        stopTransfer = true;

        $memberButton
                .button()
                .val('Start Member Transfers')
                .show();

        $memberButton.click(function () {

                    // Switch the transfer control
                    if (stopTransfer) {
                        stopTransfer = false;
                    } else {
                        stopTransfer = true;
                    }

                    // Update the controls
                    if (stopTransfer) {
                        // Stop
                        $(this).val('Stopping ...');
                    } else {
                        // start
                        $(this).val('Stop Transfers');
                        throttleMembers();
                    }
        });

        // Retrieve HHK Payments
    } else if (makeTable === '2') {

        $('div#printArea').show();
        $('#divPrintButton').show();
        $('#TxButton').hide();
        $('#btnVisits').hide();
        $('#divMembers').empty();

        // $('#tblrpt').dataTable({
        //     'columnDefs': [
        //         {'targets': [4],
        //             'type': 'date',
        //             'render': function (data, type, row) {
        //                 return dateRender(data, type, dateFormat);
        //             }
        //         }
        //     ],
        //     "displayLength": 50,
        //     "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
        //     "dom": '<"top"ilf>rt<"bottom"lp><"clear">'
        // });

        $('#btnPay').button().show().click(function () {

            if ($(this).val() === 'Transferring ...') {
                return;
            }
            $(this).val('Transferring ...');

            transferPayments($(this), start, end);
        });

        // Retrieve HHK Visits
    } else if (makeTable === '3') {

        $('div#printArea').show();
        $('#divPrintButton').show();
        $('#TxButton').hide();
        $('#btnPay').hide();
        $('#divMembers').empty();

        stopTransfer = true;

        $visitButton = $('#btnVisits');
        $psgCBs = $('.hhk-txPsgs');
        $excCBs = $('.hhk-exPsg');
        $relSels = $('.hhk-selRel');

        $excCBs.change(function () {

            let $cbPsg = $('#cbIdPSG' + $(this).data('idpsg'));

            if ($(this).prop('checked')) {

                let props = {'checked': false, 'disabled': true};
                $cbPsg.prop(props);
                $('.hhk-' + $(this).data('idpsg')).css('background-color', 'lightpink');


            } else {
                $cbPsg.prop('disabled', false);
                $('.hhk-' + $(this).data('idpsg')).css('background-color', 'transparent');
            }
        });

        $visitButton
                .button()
                .val('Start Visit Transfers')
                .show()
                .click(function () {

                    $('div#retrieve').empty();

                    // Switch transfer control
                    if (stopTransfer) {
                        stopTransfer = false;
                    } else {
                        stopTransfer = true;
                    }

                    // UPdate controls
                    if (stopTransfer) {
                        // Stop
                        $(this).val('Stopping ...');
                    } else {
                        // start
                        $(this).val('Stop Transfers');
                        throttleVisits();
                    }
                });
    }


    var $logDialog = $("#logDialog").dialog({
        autoOpen: false,
        modal: false,
        minWidth: getDialogWidth(1500),
        title: cmsTitle + ' transfer log',
        buttons: {
            "Close": function (){
                $logDialog.dialog('close');
            }
        },
        open: function (){
            $('#transferLog').DataTable().ajax.reload();
        }

    });

    setupLogViewer();
    $(document).on("click", "#viewLog", function (){
        $logDialog.dialog('open');
    });

    var opt = {mode: 'popup',
        popClose: true,
        popHt: $('#keyMapDiagBox').height(),
        popWd: $('#keyMapDiagBox').width(),
        popX: 20,
        popY: 20,
        popTitle: 'Print Visit Key'};

    var kmd = $('#keyMapDiagBox').dialog({
        autoOpen: false,
        resizable: true,
        modal: false,
        minWidth: getDialogWidth(550),
        title: cmsTitle + ' Visit Transfer Keys',
        buttons: {
            "Print": function () {
                $("div#divPrintKeys").printArea(opt);
            },
            "Close": function () {
                kmd.dialog('close');
            }
        }
    });

    $('#btnGetKey').click(function () {
        kmd.dialog('open');
    });

    $('#hhkdgpallple').button().click(function () {
        $('.hhk-tfmem').each(function (index) {
            $(this).prop('checked', true);
        })
    });

    $('#hhkdgpnople').button().click(function () {
        $('.hhk-tfmem').each(function (index) {
            $(this).prop('checked', false);
        })
    });

    $('#hhkdgpback').button().click(function () {
        $('.hhk-tfmem').each(function (index) {
            $(this).prop('checked', $(this).prop('defaultChecked'));
        })
    });

    $('#hhkdgpnew').button().click(function () {
        $('.hhk-tf-update').each(function (index) {
            $(this).prop('checked', false);
        })
    });

    $('.ckdate').datepicker({
        yearRange: '-07:+01',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        numberOfMonths: 1,
        dateFormat: 'M d, yy'
    });

    $('#btnRelat').click(function () {
        getRelate($('#txtRelat').val());
    });

    $('#btnSoql').click(function () {
        getSOQL($('#txtSoqls').val(), $('#txtSoqlf').val(), $('#txtSoqlw').val());
    });

    $('#selCalendar').change(function () {
        if ($(this).val() && $(this).val() !== '19') {
            $('#selIntMonth').hide();
        } else {
            $('#selIntMonth').show();
        }
        if ($(this).val() && $(this).val() !== '18') {
            $('.dates').hide();
        } else {
            $('.dates').show();
        }
    });

    $('#selCalendar').change();

    createAutoComplete($('#txtRSearch'), 3, {cmd: 'sch', mode: 'name'}, function (item) {
        getRemote(item, 'remote');
    }, false, '../house/ws_tran.php');


    $('#vcategory').show();
});
