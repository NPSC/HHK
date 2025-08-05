function invPay(id, pbp, dialg) {
    // cash payment
    if (verifyAmtTendrd() === false) {
        return;
    }

    var parms = {cmd: 'payInv', pbp: pbp, id: id};

    // Fees and Keys
    $('.hhk-feeskeys').each(function() {
        if ($(this).attr('type') === 'checkbox') {
            if (this.checked !== false) {
                parms[$(this).attr('id')] = 'on';
                parms[$(this).attr('name')] = 'on';
            }
        } else if ($(this).hasClass('ckdate')) {
            var tdate = $(this).datepicker('getDate');
            if (tdate) {
                parms[$(this).attr('id')] = $(this).val();
            } else {
                 parms[$(this).attr('id')] = '';
            }
        } else if ($(this).attr('type') === 'radio') {
            if (this.checked !== false) {
                parms[$(this).attr('name')] = $(this).val();
            }
        } else{
            parms[$(this).attr('id')] = $(this).val();
            parms[$(this).attr('name')] = $(this).val();
        }
    });
    //dialg.dialog("close");
    $('#keysfees').empty().append('<div id="hhk-loading-spinner" style="width: 100%; height: 100%; padding-top: 50px; text-align: center"><img src="../images/ui-anim_basic_16x16.gif"><p>Working...</p></div>');

    $.post('ws_ckin.php', parms,
        function(data) {
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
                $('#keysfees').dialog("close");
            }

            paymentRedirect(data, $('#xform'));

            $('#keysfees').dialog("close");

            if (data.success && data.success !== '') {
                flagAlertMessage(data.success, 'success');
            }

            if (data.receipt && data.receipt !== '') {
                const idPayment = (typeof data.idPayment == 'number' ? data.idPayment:false);
                const billToEmail = (typeof data.billToEmail == 'string' ? data.billToEmail:"");
                showReceipt('#pmtRcpt', data.receipt, 'Payment Receipt', 550, idPayment, billToEmail);
            }

            $('#btnInvGo').click();
    });
}

// Load the Invoice Payment dialog
function invLoadPc(nme, id, iid) {
"use strict";
    var buttons = {
        "Pay Fees": function() {
            invPay(id, 'register.php', $('div#keysfees'));
        },
        "Cancel": function() {
            $(this).dialog("close");
        }
    };

    $.post('ws_ckin.php',
        {
            cmd: 'showPayInv',
            id: id,
            iid: iid
        },

        function(data) {
        "use strict";
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

            } else if (data.mkup) {

                $('div#keysfees').children().remove();
                $('div#keysfees').append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(data.mkup)));
                $('div#keysfees .ckdate').datepicker({
                    yearRange: '-01:+01',
                    changeMonth: true,
                    changeYear: true,
                    autoSize: true,
                    numberOfMonths: 1,
                    dateFormat: 'M d, yy'
                });

                isCheckedOut = false;
                setupPayments(data.resc, '', '', 0, $('#pmtRcpt'));

                $('#keysfees').dialog('option', 'buttons', buttons);
                $('#keysfees').dialog('option', 'title', 'Pay Invoice');
                $('#keysfees').dialog('option', 'width', 800);
                $('#keysfees').dialog('open');
            }
        }
    });
}

function invSetBill(inb, name, idDiag, idElement, billDate, notes, notesElement) {
    "use strict";
    var dialg =  $(idDiag);
    var buttons = {
        "Save": function() {

            var dt;
            var nt = dialg.find('#taBillNotes').val();

            if (dialg.find('#txtBillDate').val() != '' && dialg.find('#txtBillDate').datepicker('getDate')) {
                dt = dialg.find('#txtBillDate').val();
            }

            $.post('ws_resc.php', {cmd: 'invSetBill', inb:inb, date:dt, ele: idElement, nts: nt, ntele: notesElement},
              function(data) {

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

                    } else if (data.success) {

                        if (data.elemt && data.strDate) {
                            $(data.elemt).text(data.strDate);

                        }

                        if (data.notesElemt && data.notes) {
                            $(data.notesElemt).text(data.notes);

                        }

                        flagAlertMessage(data.success, 'info');
                    }
                }
            });

            $(this).dialog("close");

        },
        "Cancel": function() {
            $(this).dialog("close");
        }
    };

    dialg.find('#spnInvNumber').text(inb);
    dialg.find('#spnBillPayor').text(name);
    dialg.find('#txtBillDate').val(billDate);
    dialg.find('#taBillNotes').val(notes);
    dialg.find('#txtBillDate').datepicker({numberOfMonths: 1});

    dialg.dialog('option', 'buttons', buttons);
    dialg.dialog('option', 'width', 450);
    dialg.dialog('open');
}

function invoiceAction(idInvoice, action, eid, container, show) {
    "use strict";
    $.post('ws_resc.php', {cmd: 'invAct', iid: idInvoice, x:eid, 'container':container, action: action, 'sbt':show},
      function(data) {
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

            if (data.delete) {

                if (!data.eid || data.eid == '0' || !container || container == '') {
                    // Register page or InvoiceReport -> unpaid invoices tab delete action
                    flagAlertMessage(data.delete, 'success');
                    $('#btnInvGo').click();  // repaint unpaid invoices tab
                } else {
                    // Paying today section, unpaid invoices listing delete icon.
                    //
                    if ($('#' + data.eid).parentsUntil('.keysfees', '.hhk-payInvoice').length > 0) {
                        // called from pay invoices dialog
                        $('#' + data.eid).parents('tr').first().remove();
                        amtPaid();
                        $('#btnInvGo').click();  // repaint unpaid invoices tab
                    } else {
                        // called from visit viewer.
                        $('#keysfees').dialog("close");
                    }
                }

            }
            if (data.markup) {
                let contr = $(data.markup);
                let myOf = $("#" + data.eid);

                if (container != undefined && container != '') {
                    $(container).append(contr);
                } else {
                    $('body').append(contr);
                }
                contr.position({
                    my: 'left top',
                    at: 'left bottom',
                    of: myOf
                });
            }
        }
    });
}

// ,
// of: $("#" + data.eid)