// GuestTransfer.js
//
var stopTransfer,
        $visitButton,
        $memberButton,
        $psgCBs,
        $excCBs,
        $relSels,
        cmsTitle;


function updateLocal(id) {
    var postUpdate = $.post('ws_tran.php', {cmd: 'rmvAcctId', id: id});

    postUpdate.done(function (incmg) {
        $('div#retrieve').empty();

        if (!incmg) {
            alert('Bad Reply from Server');
            return;
        }

        try {
            incmg = $.parseJSON(incmg);
        } catch (err) {
            alert('Bad JSON Encoding');
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

function updateRemote(id, accountId) {

    var postUpdate = $.post('ws_tran.php', {cmd: 'update', accountId: accountId, id: id});

    postUpdate.done(function (incmg) {
        $('#btnUpdate').val('Update Remote');
        if (!incmg) {
            alert('Bad Reply from Server');
            return;
        }

        try {
            incmg = $.parseJSON(incmg);
        } catch (err) {
            alert('Bad JSON Encoding');
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

            var updteLocal = $('<input type="button" id="btnLocal" value="" />');
            $('#btnUpdate').hide();

            flagAlertMessage(incmg.warning, true);

            updteLocal.val('Remove Remote Account Id from Local Record');

            updteLocal.button().click(function () {

                if ($(this).val() === 'Working...') {
                    return;
                }
                $(this).val('Working...');

                updateLocal(id);
            });

            $('div#retrieve').prepend(updteLocal);

        } else if (incmg.result) {
            flagAlertMessage(incmg.result, false);
        }
    });
}

function transferRemote(transferIds) {
    var parms = {
        cmd: 'members',
        ids: transferIds
    };

    var posting = $.post('ws_tran.php', parms);
    posting.done(function (incmg) {
        $('#TxButton').val('Transfer').hide();
        if (!incmg) {
            alert('Bad Reply from HHK Web Server');
            return;
        }
        try {
            incmg = $.parseJSON(incmg);
        } catch (err) {
            alert('Bad JSON Encoding');
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
            $('div#retrieve').empty();
            $('#printArea').show();
            $('#divTable').empty().append($(incmg.data));
        }
        
        throttleMembers();
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
        $memberButton.val('Resume Member Transfers');
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
        stopTransfer = true;
        $memberButton.val('Start Member Transfers');
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
        try {
            incmg = $.parseJSON(incmg);
        } catch (err) {
            alert('Bad JSON Encoding');
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
        try {
            incmg = $.parseJSON(incmg);
        } catch (err) {
            alert('Bad JSON Encoding');
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

        try {
            incmg = $.parseJSON(incmg);
        } catch (err) {
            alert('Bad JSON Encoding');
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
        try {
            incmg = $.parseJSON(incmg);
        } catch (err) {
            alert('Bad JSON Encoding');
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

            if (source === 'remote') {
                $('div#retrieve').prepend($('<h3>Remote Data</h3>'));
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
                    });
                } else if (incmg.accountId) {
                    updteRemote.val('Update Remote');
                    updteRemote.button().click(function () {

                        if ($(this).val() === 'Working...') {
                            return;
                        }
                        $(this).val('Working...');
                        updateRemote(item.id, incmg.accountId);
                    });
                } else {
                    updteRemote = '';
                }

                $('div#retrieve').prepend($('<h3>Local (HHK) Data </h3>').append(updteRemote));
                $('#txtSearch').val('');
            }
        }
    });
}

function getRequest(url, q) {
    $('div#printArea').hide();
    $('#divPrintButton').hide();

    // code certain words
    q_ = q.replace('Select', 'Find');

    var posting = $.post('ws_tran.php', {cmd: 'getReq', 'url': url, 'q': q_});

    posting.done(function (incmg) {
        if (!incmg) {
            alert('Bad Reply from HHK Web Server');
            return;
        }
        try {
            incmg = $.parseJSON(incmg);
        } catch (err) {
            alert('Bad JSON Encoding');
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

            $('div#retrieve').prepend($('<h3>Remote Data</h3>'));
            $('#txtRequest').val('');

        }
    });
}


$(document).ready(function () {

    var makeTable = $('#hmkTable').val();
    var start = $('#hstart').val();
    var end = $('#hend').val();
    var dateFormat = $('#hdateFormat').val();
    cmsTitle = $('#cmsTitle').val();

    $('#btnHere, #btnCustFields, #btnGetPayments, #btnGetVisits, #btnGetKey, #btnRequest').button();

    $('#printButton').button().click(function () {
        $("div#printArea").printArea();
    });

    // Retrieve HHK Records
    if (makeTable === '1') {

        $('div#printArea').show();
        $('#divPrintButton').show();
        $('#btnPay').hide();
        $('#btnVisits').hide();
        $('#divMembers').empty();
        
        $memberButton = $('#TxButton');
        
        stopTransfer = true;

        $('#tblrpt').dataTable({
            'columnDefs': [
                {'targets': [7],
                    'type': 'date',
                    'render': function (data, type, row) {
                        return dateRender(data, type, dateFormat);
                    }
                }
            ],
            "displayLength": 50,
            "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
            "dom": '<"top"ilf>rt<"bottom"lp><"clear">'
        });

        $memberButton
                .button()
                .val('Start Member Transfers')
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
                        throttleMembers();
                    }
                });

//        $('#TxButton').button().show().click(function () {
//            if ($('#TxButton').val() === 'Working...') {
//                return;
//            }
//            $('#TxButton').val('Working...');
//
//            let txIds = {};
//            $('.hhk-txCbox').each(function () {
//                if ($(this).prop('checked')) {
//                    txIds[$(this).data('txid')] = $(this).data('txid');
//                }
//            });
//            transferRemote(txIds);
//        });

        // Retrieve HHK Payments
    } else if (makeTable === '2') {

        $('div#printArea').show();
        $('#divPrintButton').show();
        $('#TxButton').hide();
        $('#btnVisits').hide();
        $('#divMembers').empty();

        $('#tblrpt').dataTable({
            'columnDefs': [
                {'targets': [4],
                    'type': 'date',
                    'render': function (data, type, row) {
                        return dateRender(data, type, dateFormat);
                    }
                }
            ],
            "displayLength": 50,
            "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
            "dom": '<"top"ilf>rt<"bottom"lp><"clear">'
        });

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

    $('.ckdate').datepicker({
        yearRange: '-07:+01',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        numberOfMonths: 1,
        dateFormat: 'M d, yy'
    });

    $('#btnRequest').click(function () {
        getRequest($('#txtRequest').val(), $('#txtQuery').val());
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
    createAutoComplete($('#txtSearch'), 3, {cmd: 'role', mode: 'mo'}, function (item) {
        getRemote(item, 'hhk');
    }, false);

    $('#vcategory').show();
});
