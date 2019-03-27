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

    if (data.xfer || data.inctx) {
        paymentRedirect (data, $('#xform'));
        return;
    }
    
    if (data.redirTo) {
       location.replace(data.redirTo);
    }

    if (data.success) {
        //flagAlertMessage(data.success, false);
        var cDiv = $('#contentDiv');
        var opt = {mode: 'popup',
            popClose: true,
            popHt      : $('div#RegArea').height(),
            popWd      : 950,
            popX       : 20,
            popY       : 20,
            popTitle   : 'Guest Registration Form'};

        cDiv.children().remove();

        if (data.regform && data.style) {
            cDiv.append($('<div id="print_button" style="float:left;">Print</div>'))
                    .append($('<div id="btnReg" style="float:left; margin-left:10px;">Check In Followup</div>'))
                    .append($('<div id="btnStmt" style="float:left; margin-left:10px;">Show Statement</div>'))
                    .append($('<div id="mesgReg" style="color: darkgreen; clear:left; font-size:1.5em;"></div>'))
                    .append($('<div style="clear: left;" class="RegArea"/>')
                            .append($(data.style)).append($(data.regform)));

            $("div#print_button, div#btnReg, div#btnStmt").button();
            $("div#print_button").click(function() {
                $("div.RegArea").printArea(opt);
            });
            $('div#btnReg').click(function() {
                getRegistrationDialog(data.reg, cDiv);
            });
            $('div#btnStmt').click(function() {
                window.open('ShowStatement.php?vid=' + data.vid, '_blank');
            });
        }

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
        }

        if (data.regDialog) {
            showRegDialog(data.regDialog, data.reg, cDiv);
        }

        if (data.receipt) {
            showReceipt('#pmtRcpt', data.receipt);
        }

        if (data.invoiceNumber && data.invoiceNumber !== '') {
            window.open('ShowInvoice.php?invnum=' + data.invoiceNumber);
        }

    }
}

$(document).ready(function() {
    "use strict";
    var t = this;
    var resv = $.parseJSON($('#resv').val());
    var pageManager = t.pageManager;
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
        width: 900,
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
        width: 530,
        modal: true,
        title: 'Payment Receipt'
    });

    $("#ecSearch").dialog({
        autoOpen: false,
        resizable: false,
        width: 300,
        title: 'Emergency Contact',
        modal: true,
        buttons: {
            "Exit": function() {
                $(this).dialog("close");
            }
        }
    });


    pageManager = new resvManager(resv);

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

    $('#btnDone').click(function () {

        if ($(this).val() === 'Saving >>>>') {
            return;
        }

        $('#pWarnings').hide();

        if (pageManager.verifyInput() === true) {

            $(this).val('Saving >>>>');
            
            $.post(
                'ws_resv.php',
                $('#form1').serialize() + '&cmd=saveCheckin&idPsg=' + pageManager.getIdPsg() + '&rid=' + pageManager.getIdResv() + '&vid=' + pageManager.getIdVisit() + '&span=' + pageManager.getSpan() + '&' + $.param({mem: pageManager.people.list()}),
                function(data) {

                    $('#btnDone').val(resv.saveButtonLabel).show();

                    try {
                        data = $.parseJSON(data);
                    } catch (err) {
                        flagAlertMessage(err.message, 'error');
                        return;
                    }

                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }

                    if (data.error) {
                        flagAlertMessage(data.error, 'error');
                    }

                    
                    ckedIn(data);
                }
            );
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

