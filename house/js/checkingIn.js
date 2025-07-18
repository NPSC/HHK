var fixedRate;
var payFailPage;
var dateFormat;
var pageManager;

function ckedIn(data) {
    "use strict";
    $("#divAlert1").hide();

    if (data.warning) {
        flagAlertMessage(data.warning, 'warning');
    }

    if (data.xfer || data.inctx || data.deluxehpf) {
        paymentRedirect(data, $('#xform'));
        return;
    }

    if (data.redirTo) {
       location.replace(data.redirTo);
    }

    if (data.success) {

        if (data.ckmeout) {
            var buttons = {
                "Show Statement": function() {
                    window.open('ShowStatement.php?vid=' + data.vid, '_blank');
                },
                "Check Out": function() {
                    saveFees(data.gid, data.vid, 0, true, 'register.php');
                },
                "Cancel": function() {
                    $(this).dialog("close");
                }
            };
            viewVisit(data.gid, data.vid, buttons, 'Check Out', 'co', 0, data.ckmeout);
            return;
        }

        location.replace('ShowRegForm.php?regid='+data.regid+'&vid='+data.vid+'&payId='+data.payId+'&invoiceNumber='+data.invoiceNumber);

    }
}

$(document).ready(function() {
    "use strict";
    var t = this;
    var resv = $.parseJSON($('#resv').val());
    var pageManagerOptions = $.parseJSON($('#resvManagerOptions').val());
    fixedRate = $('#fixedRate').val();
    payFailPage = $('#payFailPage').val();
    dateFormat = $('#dateFormat').val();

    $.widget( "ui.autocomplete", $.ui.autocomplete, {
        _resizeMenu: function() {
            var ul = this.menu.element;
            ul.outerWidth( Math.max(
                    ul.width( "" ).outerWidth() + 1,
                    this.element.outerWidth()
            ) * 1.1 );
        }
    });

// Dialog Boxes

    $("#activityDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: getDialogWidth(900),
        modal: true,
        title: 'Reservation Activity Log',
        close: function () {$('div#submitButtons').show();},
        open: function () {$('div#submitButtons').hide();},
        buttons: {
            "Exit": function() {
                $(this).dialog("close");
            }
        }
    });

    $('#keysfees').dialog({
        autoOpen: false,
        resizable: true,
        modal: true,
        close: function() {$('#submitButtons').show();}
    });

    $('#pmtRcpt').dialog({
        autoOpen: false,
        resizable: true,
        width: getDialogWidth(530),
        modal: true,
        title: 'Payment Receipt'
    });

    $("#ecSearch").dialog({
        autoOpen: false,
        resizable: false,
        width: getDialogWidth(300),
        title: 'Emergency Contact',
        modal: true,
        buttons: {
            "Exit": function() {
                $(this).dialog("close");
            }
        }
    });


    pageManager = new resvManager(resv, pageManagerOptions);

    // hide the alert on mousedown
    $(document).mousedown(function (event) {

        if (isIE()) {

            var target = $(event.target[0]);

            if (target.id && target.id !== undefined && target.id !== 'divSelAddr' && target.closest('div') && target.closest('div').id !== 'divSelAddr') {
                $('#divSelAddr').remove();
            }

        } else {

            if (event.target.className === undefined || event.target.className !== 'hhk-addrPickerPanel') {
                $('#divSelAddr').remove();
            }
        }
    });

// Buttons
    $('#btnDone, #btnShowReg').button();

    $('#btnShowReg').click(function () {
        window.open('ShowRegForm.php?rid=' + pageManager.getIdResv(), '_blank');
    });

    $('#form1').on('submit', function(e){
        e.preventDefault();
    });

    $('#btnDone').click(function () {

        if ($(this).val() === 'Saving >>>>') {
            return;
        }

        $('#pWarnings').hide();

        if (pageManager.verifyInput() === true) {

            $(this).val('Saving >>>>');

            const formData = new FormData($('#form1')[0]);
            const jsonObject = {};

            formData.append('cmd', 'saveCheckin');
            formData.append('idPsg', pageManager.getIdPsg());
            formData.append('prePayment', pageManager.getPrePaymtAmt());
            formData.append('rid', pageManager.getIdResv());

            //diagnosis
            let txtDiagnosis = $('#txtDiagnosis').val();
            if (typeof txtDiagnosis === "string") {
                txtDiagnosis = buffer.Buffer.from(txtDiagnosis).toString("base64");
            }
            formData.append('txtDiagnosis', txtDiagnosis);

            // convert to base json object
            for (const pair of formData.entries()) {
                jsonObject[pair[0]] = pair[1];
            }

            // Add people to the json object
            jsonObject.mem = pageManager.people.list();

            try {
                // Handle the response from the server
                jsonFetch(jsonObject, 'ws_resv.php', (text) => {

                    $('#btnDone').val(resv.saveButtonLabel).show();

                    const data = JSON.parse(text);

                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }

                    if (data.error) {
                        flagAlertMessage(data.error, 'error');
                    }

//                    if (!data.vid || data.vid === 0) {
//                        pageManager.loadResv(data);
//                    } else {
                        ckedIn(data);
//                    }
                });

            } catch (error) {
                console.error("Error handling form submission:", error);

                flagAlertMessage("An error occurred during form submission.", "error");
            }

        }
    });


    if (parseInt(resv.id, 10) > 0 || parseInt(resv.rid, 10) > 0 || parseInt(resv.vid, 10) > 0) {

        // Avoid automatic new guest for existing reservations.
        if (parseInt(resv.id, 10) === 0 && parseInt(resv.rid, 10) > 0) {
            resv.id = -2;
        }

        resv.cmd = 'getCkin';
        pageManager.getReserve(resv);

    }
});

