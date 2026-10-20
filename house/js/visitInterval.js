
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

// Called by the report DataTable (AbstractReport::generateReportScript) after each draw.
function drawCallback(settings) {
    $('.hhk-viewVisit').button();
}

$(document).ready(function () {

    let pmtMkup = $('#pmtMkup').val(),
        rctMkup = $('#rctMkup').val(),
        receiptPaymentId = $('#receiptPaymentId').val(),
        receiptBilledToEmail = $('#receiptBilledToEmail').val();

    fixedRate = $('#fixedRate').val();

    $('input[type="submit"]').click(function () {
        $('#paymentMessage').hide();
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

    $(document).on('click', '.hhk-viewVisit', function () {
        let vid = $(this).data('vid'),
            gid = $(this).data('gid'),
            span = $(this).data('span');

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

    if (rctMkup !== '') {
        showReceipt('#pmtRcpt', rctMkup, 'Payment Receipt', 550, receiptPaymentId, receiptBilledToEmail);
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

    // disappear the pop-up room chooser.
    $(document).mousedown(function (event) {
        var target = $(event.target);
        if ($('div#insDetailDiv').length > 0 && target[0].id !== 'insDetailDiv' && target.parents("#" + 'insDetailDiv').length === 0) {
            $('div#insDetailDiv').remove();
        }
    });

    var detailDiv = $("<div>").attr('id', 'insDetailDiv');
    $("body").append(detailDiv);
    $(document).on('click', '.insAction', function (event) {
        viewInsurance($(this).data('idname'), event.target.id, detailDiv);
    });

});
