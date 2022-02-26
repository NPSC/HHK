function updateLocal(id) {
    var postUpdate = $.post('ws_tran.php', {cmd:'rmvAcctId', id:id});

    postUpdate.done(function(incmg) {
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

    var postUpdate = $.post('ws_tran.php', {cmd:'update', accountId:accountId, id:id});

    postUpdate.done(function(incmg) {
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
        cmd: 'xfer',
        ids: transferIds
    };

    var posting = $.post('ws_tran.php', parms);
    posting.done(function(incmg) {
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
    });

}

function transferData($btn, start, end, command) {

    var parms = {
        cmd: command,
        st: start,
        en: end
    };

    var posting = $.post('ws_tran.php', parms);

    posting.done(function(incmg) {
        $btn.val('Transfer Payments');

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

    var posting = $.post('ws_tran.php', {cmd:'getAcct', src:source, accountId:item.id});
    posting.done(function(incmg) {
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

                var updteRemote = $('<input type="button" id="btnUpdate" value="" />');

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

$(document).ready(function() {
    var makeTable = $('#hmkTable').val();
    var start = $('#hstart').val();
    var end = $('#hend').val();
    var dateFormat = $('#hdateFormat').val();

    $('#btnHere, #btnCustFields, #btnGetPayments, #btnGetVisits').button();

    $('#printButton').button().click(function() {
        $("div#printArea").printArea();
    });

	// Retrieve HHK Records
    if (makeTable === '1') {
        $('div#printArea').show();
        $('#divPrintButton').show();
        $('#btnPay').hide();
        $('#btnVisits').hide();
        $('#divMembers').empty();

        $('#tblrpt').dataTable({
           'columnDefs': [
                {'targets': [5, 10, 11],
                 'type': 'date',
                 'render': function ( data, type, row ) {return dateRender(data, type, dateFormat);}
                }
            ],
            "displayLength": 50,
            "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
            "dom": '<"top"ilf>rt<"bottom"lp><"clear">'
        });

        $('#TxButton').button().show().click(function () {
            if ($('#TxButton').val() === 'Working...') {
                return;
            }
            $('#TxButton').val('Working...');
            
            txIds = {};
            $('.hhk-txCbox').each(function () {
            	if ($(this).prop('checked')) {
            		txIds[$(this).data('txid')] = $(this).data('txid');
            	}
            });
            transferRemote(txIds);
        });

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
                 'render': function ( data, type, row ) {return dateRender(data, type, dateFormat);}
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

            transferData($(this), start, end, 'payments');
        });

    // Retrieve HHK Visits
    } else if (makeTable === '3') {

        $('div#printArea').show();
        $('#divPrintButton').show();
        $('#TxButton').hide();
        $('#btnPay').hide();
        $('#divMembers').empty();

        $('#tblrpt').dataTable({
            'columnDefs': [
                {'targets': [4, 5],
                 'type': 'date',
                 'render': function ( data, type, row ) {return dateRender(data, type, dateFormat);}
                }
            ],
            "displayLength": 50,
            "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
            "dom": '<"top"ilf>rt<"bottom"lp><"clear">'
        });

        $('#btnVisits').button().show().click(function () {

            if ($(this).val() === 'Transferring ...') {
                return;
            }
            $(this).val('Transferring ...');

            transferData($(this), start, end, 'visits');
        });
    }


    $('.ckdate').datepicker({
        yearRange: '-07:+01',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        numberOfMonths: 1,
        dateFormat: 'M d, yy'
    });

    $('#selCalendar').change(function () {
        if ($(this).val() && $(this).val() != '19') {
            $('#selIntMonth').hide();
        } else {
            $('#selIntMonth').show();
        }
        if ($(this).val() && $(this).val() != '18') {
            $('.dates').hide();
        } else {
            $('.dates').show();
        }
    });

    $('#selCalendar').change();

    createAutoComplete($('#txtRSearch'), 3, {cmd: 'sch', mode: 'name'}, function (item) {getRemote(item, 'remote');}, false, '../house/ws_tran.php');
    createAutoComplete($('#txtSearch'), 3, {cmd: 'role', mode: 'mo'}, function (item) {getRemote(item, 'hhk');}, false);
});
