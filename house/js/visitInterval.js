
function viewInsurance(idName, eventTarget, detailDiv) {
    "use strict";
    detailDiv.empty();
    $.post('ws_resc.php', { cmd: 'viewInsurance', idName: idName },
        function (data) {
            if (data) {
                try {
                    data = $.parseJSON(data);
                } catch (err) {
                    alert("Parser error - " + err.message);
                    return;
                }
                if (data.error) {
                    if (data.gotopage) {
                        window.location.assign(data.gotopage);
                    }
                    flagAlertMessage(data.error, 'error');
                    return;
                }

                if (data.markup) {
                    var contr = $(data.markup);

                    $('body').append(contr);
                    contr.position({
                        my: 'left top',
                        at: 'left bottom',
                        of: "#" + eventTarget
                    });
                }
            }
        });
}

var fixedRate;  // used by VisitDialog.hs

$(document).ready(function () {

    let startYear = $('#startYear').val(),
        columnDefs = $.parseJSON($('#columnDefs').val()),
        pmtMkup = $('#pmtMkup').val(),
        makeTable = $('#makeTable').val(),
        dateFormat = $('#dateFormat').val(),
        rctMkup = $('#rctMkup').val(),
        defaultFields = $('#defaultFields').val();

    $('#selCalendar').change(function () {
        $('#selIntYear').show();
        if ($(this).val() && $(this).val() != '19') {
            $('#selIntMonth').hide();
        } else {
            $('#selIntMonth').show();
        }
        if ($(this).val() && $(this).val() != '18') {
            $('.dates').hide();
        } else {
            $('.dates').show();
            $('#selIntYear').hide();
        }
    });
    $('#selCalendar').change();

    $('.ckdate').datepicker({
        yearRange: startYear+':+02',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        numberOfMonths: 1,
        dateFormat: 'M d, yy'
    });;


    $('#btnHere, #btnExcel, #btnStatsOnly, #cbColClearAll, #cbColSelAll').button();
    $('#btnHere, #btnExcel').click(function () {
        $('#paymentMessage').hide();
    });
    $('#cbColClearAll').click(function () {
        $('#selFld option').each(function () {
            $(this).prop('selected', false);
        });
    });
    $('#cbColSelAll').click(function () {
        $('#selFld option').each(function () {
            $(this).prop('selected', true);
        });
    });
    $('#keysfees').dialog({
        autoOpen: false,
        resizable: true,
        modal: true,
        close: function () { $('div#submitButtons').show(); },
        open: function () { $('div#submitButtons').hide(); }
    });
    $('#pmtRcpt').dialog({
        autoOpen: false,
        resizable: true,
        width: getDialogWidth(530),
        modal: true,
        title: 'Payment Receipt'
    });
    $("#faDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: getDialogWidth(650),
        modal: true,
        title: 'Income Chooser'
    });

    $('.hhk-viewVisit').button();
    $('.hhk-viewVisit').click(function () {
        let vid = $(this).data('vid'),
            gid = $(this).data('gid'),
            span = $(this).data('span');

        fixedRate = $('#fixedRate').val();

        const buttons = {
            "Show Statement": function () {
                window.open('ShowStatement.php?vid=' + vid, '_blank');
            },
            "Show Registration Form": function () {
                window.open('ShowRegForm.php?vid=' + vid + '&span=' + span, '_blank');
            },
            "Save": function () {
                saveFees(gid, vid, span, false, 'VisitInterval.php');
            },
            "Cancel": function () {
                $(this).dialog("close");
            }
        };
        viewVisit(gid, vid, buttons, 'Edit Visit #' + vid + '-' + span, '', span);
    });

    if (makeTable === '1') {
        $('div#printArea, div#stats').css('display', 'block');

        $('#tblrpt').dataTable({
            'columnDefs': [
                {
                    'targets': columnDefs,
                    'type': 'date',
                    'render': function (data, type, row) { return dateRender(data, type, dateFormat); }
                }
            ],
            "displayLength": 50,
            "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
            "dom": '<"top ui-toolbar ui-helper-clearfix"if><"hhk-overflow-x"rt><"bottom ui-toolbar ui-helper-clearfix"lp>',
        });
        $('#printButton').button().click(function () {
            $("div#printArea").printArea();
        });
    }
    if (rctMkup !== '') {
        showReceipt('#pmtRcpt', rctMkup, 'Payment Receipt');
    }
    if (pmtMkup !== '') {
        $('#paymentMessage').html(pmtMkup).show("pulsate", {}, 400);
    }

    $('#keysfees').mousedown(function (event) {
        var target = $(event.target);
        if (target[0].id !== 'pudiv' && target.parents("#" + 'pudiv').length === 0) {
            $('div#pudiv').remove();
        }
    });

    $('#includeFields').fieldSets({ 'reportName': 'visit', 'defaultFields': defaultFields});

    // disappear the pop-up room chooser.
    $(document).mousedown(function (event) {
        var target = $(event.target);
        if ($('div#insDetailDiv').length > 0 && target[0].id !== 'insDetailDiv' && target.parents("#" + 'insDetailDiv').length === 0) {
            $('div#insDetailDiv').remove();
        }
    });

    var detailDiv = $("<div>").attr('id', 'insDetailDiv');
    $("body").append(detailDiv);
    $('#tblrpt').on('click', '.insAction', function (event) {
        viewInsurance($(this).data('idname'), event.target.id, detailDiv);
    });

});
