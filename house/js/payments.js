/**
 * payments.js
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC
 */

/**
 * 
 * @param {int} orderNumber
 * @param {jquery} $diagBox
 * @returns {undefined}
 */
var gblAdjustData = [];
function getApplyDiscDiag(orderNumber, $diagBox) {
    "use strict";
    
    if (!orderNumber || orderNumber == '' || orderNumber == 0) {
        flagAlertMessage('Order Number is missing', 'error');
        return;
    }
    
    $.post('ws_ckin.php',
            {
                cmd: 'getHPay',
                ord: orderNumber,
                arrDate: $('#spanvArrDate').text()
            },
        function(data) {
          if (data) {
            try {
                data = $.parseJSON(data);
            } catch (e) {
                alert("Parser error - " + e.message);
                return;
            }
            
            if (data.error) {
                if (data.gotopage) {
                    window.location.assign(data.gotopage);
                }
                
                flagAlertMessage(data.error, 'error');
                
            } else if (data.markup) {
                $diagBox.children().remove();
                var buttons = {
                    "Save": function() {
                        
                        var amt = parseFloat($('#housePayment').val().replace('$', '').replace(',', '')),
                            vid = $('#housePayment').data('vid'),
                            item = '',
                            adjDate = $.datepicker.formatDate("yy-mm-dd", $('#housePaymentDate').datepicker('getDate')),
                            notes = $('#housePaymentNote').val();
                            
                        if (isNaN(amt)) {
                            amt = 0;
                        }
                        
                        if ($('#cbAdjustPmt1').prop('checked')) {
                            item = $('#cbAdjustPmt1').data('item');
                        } else {
                            item = $('#cbAdjustPmt2').data('item');
                        }
                        
                        saveDiscountPayment(vid, item, amt, $('#selHouseDisc').val(), $('#selAddnlChg').val(), adjDate, notes);
                        $(this).dialog('close');
                    },
                    "Cancel": function() {
                        $(this).dialog("close");
                    }
                };

                $diagBox.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(data.markup)));
                
                $( "#cbAdjustType" ).buttonset();

                $('#cbAdjustPmt1, #cbAdjustPmt2').change(function () {

                    var hid = $(this).data('hid'),
                        sho = $(this).data('sho');

                    $('#' + hid).val('');
                    $('#' + sho).val('');
                    $('#housePayment').val('');

                    if ($(this).prop('checked')) {

                        $('#' + sho).show();
                        $('#' + hid).hide();

                    } else {

                        $('#' + hid).hide();
                        $('#' + sho).show();
                    }
                });

                gblAdjustData['disc'] = data.disc;
                gblAdjustData['addnl'] = data.addnl;

                $('#selAddnlChg, #selHouseDisc').change(function () {
                    var amts = gblAdjustData[$(this).data('amts')];
                    $('#housePayment').val(amts[$(this).val()]);
                });

                if ($('#cbAdjustPmt1').length > 0) {
                    $('#cbAdjustPmt1').prop('checked', true);
                    $('#cbAdjustPmt1').change();
                } else {
                    $('#cbAdjustPmt2').prop('checked', true);
                    $('#cbAdjustPmt2').change();
                }

                $diagBox.dialog('option', 'buttons', buttons);
                $diagBox.dialog('option', 'title', 'Adjust Fees');
                $diagBox.dialog('option', 'width', 400);
                $diagBox.dialog('open');
            }
        }
    });
}
/**
 * 
 * @param {type} orderNumber
 * @param {type} item
 * @param {type} amt
 * @param {type} discount
 * @param {type} addnlCharge
 * @param {type} adjDate
 * @param {string} notes
 * @returns {undefined}
 */
function saveDiscountPayment(orderNumber, item, amt, discount, addnlCharge, adjDate, notes) {
    "use strict";
    $.post('ws_ckin.php',
            {
                cmd: 'saveHPay',
                ord: orderNumber,
                item: item,
                amt: amt,
                dsc: discount,
                chg: addnlCharge,
                adjDate: adjDate,
                notes: notes
            },
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
            }
            
            if (data.reply && data.reply != '') {
                flagAlertMessage(data.reply, 'success');
                $('#keysfees').dialog("close");
            }
            
            if (data.receipt && data.receipt !== '') {
                
                if ($('#keysfees').length > 0) {
                    $('#keysfees').dialog("close");
                }

                showReceipt('#pmtRcpt', data.receipt, 'Payment Receipt');
            }
            
         }
    });
}

/**
 * 
 * @param {object} item
 * @param {int} orderNum
 * @returns {undefined}
 */
function getInvoicee(item, orderNum) {
    "use strict";
    var cid = parseInt(item.id, 10);
    if (isNaN(cid) === false && cid > 0) {
        $('#txtInvName').val(item.value);
        $('#txtInvId').val(cid);
    } else {
        $('#txtInvName').val('');
        $('#txtInvId').val('');
    }
    $('#txtOrderNum').val(orderNum);
    $('#txtInvSearch').val('');
}

function invoiceAction(idInvoice, action, eid, container, show) {
    "use strict";
    $.post('ws_resc.php', {cmd: 'invAct', iid: idInvoice, x:eid, action: action, 'sbt':show},
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

                if (data.eid == '0') {
                    flagAlertMessage(data.delete, 'success');
                    $('#btnInvGo').click();
                } else {
                    $('#' + data.eid).parents('tr').first().hide('fade');
                }

            }
            if (data.markup) {
                var contr = $(data.markup);
                if (container != undefined && container != '') {
                    $(container).append(contr);
                } else {
                    $('body').append(contr);
                }
                contr.position({
                    my: 'left top',
                    at: 'left bottom',
                    of: "#" + data.eid
                });
            }
        }
    });
}

/**
 * 
 * @param {string} btnid
 * @param {string} vorr
 * @param {int} idPayment
 * @param {float} amt
 * @returns {undefined}
 */
function sendVoidReturn(btnid, vorr, idPayment, amt) {
    
    var prms = {pid: idPayment, bid: btnid};
    
    if (vorr && vorr === 'v') {
        prms.cmd = 'void';
    } else if (vorr && vorr === 'rv') {
        prms.cmd = 'revpmt';
    } else if (vorr && vorr === 'r') {
        prms.cmd = 'rtn';
        prms.amt = amt;
    } else if (vorr && vorr === 'ur') {
        prms.cmd = 'undoRtn';
        prms.amt = amt;
    } else if (vorr && vorr === 'vr') {
        prms.cmd = 'voidret';
    } else if (vorr && vorr === 'd') {
        prms.cmd = 'delWaive';
        prms.iid = amt;
    }
    $.post('ws_ckin.php', prms, function (data) {
        var revMessage = '';
        if (data) {
            try {
                data = $.parseJSON(data);
            } catch (err) {
                alert("Parser error - " + err.message);
                return;
            }
            if (data.bid) {
                // clear button control
                $('#' + data.bid).remove();
            }
            if (data.error) {
                if (data.gotopage) {
                    window.location.assign(data.gotopage);
                }
                flagAlertMessage(data.error, 'error');
                return;
            }
            if (data.reversal && data.reversal !== '') {
                revMessage = data.reversal;
            }
            if (data.warning) {
                flagAlertMessage(revMessage + data.warning, 'warning');
                return;
            }
            if (data.success) {
                 flagAlertMessage(revMessage + data.success, 'success');
            }
            if (data.receipt) {
                showReceipt('#pmtRcpt', data.receipt, 'Receipt');
            }
        }
    });
}

var payCtrls = function () {
    var t = this;
    t.keyDepAmt = $('#keyDepAmt');
    t.keyDepCb = $('#keyDepRx');
    t.visitFeeAmt = $('#visitFeeAmt');
    t.visitFeeCb = $('#visitFeeCb');
    t.feePayAmt = $('input#feesPayment');
    t.feesCharges = $('#feesCharges');
    t.totalPayment = $('#totalPayment');
    t.totalCharges = $('#totalCharges');
    t.cashTendered = $('#txtCashTendered');
    t.invoiceCb = $('.hhk-payInvCb');
    t.adjustBtn = $('#paymentAdjust');
    t.msg = $('#payChooserMsg');
    t.heldAmtTb = $('#heldAmount');
    t.heldCb = $('#cbHeld');
    t.hsDiscAmt = $('#HsDiscAmount');
    t.depRefundAmt = $('#DepRefundAmount');
    t.finalPaymentCb = $('input#cbFinalPayment');
    t.overPay = $('#txtOverPayAmt');
    t.guestCredit = $('#guestCredit');
    t.selBalTo = $('#selexcpay');
    
};

function amtPaid() {
    "use strict";
    var p = new payCtrls(),
        kdep = 0, 
        vfee = 0,
        feePay = 0,
        feePayStr = '',
        feeCharge = 0, 
        invAmt = 0, 
        heldAmt = 0,
        heldTotal = 0,
        totCharges = 0,
        ckedInCharges = 0,
        hsPay = 0,
        totPay = 0, 
        depRfAmt = 0,
        guestCreditAmt = 0,
        overPayAmt = 0,
        isChdOut = isCheckedOut;
    
    p.msg.text('').hide();
    
    // Visit fees
    if (p.visitFeeCb.length > 0) {
        vfee = parseFloat($('#spnvfeeAmt').data('amt'));
        if (isNaN(vfee) || vfee < 0 || p.visitFeeCb.prop("checked") === false) {
            vfee = 0;
            p.visitFeeAmt.val('');
        } else {
            p.visitFeeAmt.val(vfee.toFixed(2).toString());
        }
    }

    // Deposits
    if (!isChdOut && p.keyDepCb.length > 0) {
        kdep = parseFloat($('#spnDepAmt').data('amt'));
        if (isNaN(kdep) || kdep < 0 || p.keyDepCb.prop("checked") === false) {
            kdep = 0;
            p.keyDepAmt.val('');
        } else {
            p.keyDepAmt.val(kdep.toFixed(2).toString());
        }
    }

    // Unpaid Invoices
    if (p.invoiceCb.length > 0) {
        
        p.invoiceCb.each(function () {
            
            var invnum = parseInt($(this).data('invnum'));
            var amtCtrl = $('#' + invnum + 'invPayAmt');
            var maxamt = parseFloat($(this).data('invamt'));
            var amt;
            
            if ($(this).prop('checked') === true) {
                
                amtCtrl.prop('disabled', false);
                
                if (amtCtrl.val() === '') {
                    amtCtrl.val(maxamt.toFixed(2).toString());
                }
                
                amt = parseFloat(amtCtrl.val().replace('$', '').replace(',', ''));
                
                if (isNaN(amt) || amt == 0) {
                    amt = 0;
                    amtCtrl.val('');
                } else if (Math.abs(amt) > Math.abs(maxamt)) {
                    amt = maxamt;
                    amtCtrl.val(amt.toFixed(2).toString());
                }
                
                invAmt += amt;
                
            } else {
                if (amtCtrl.val() !== '') {
                    amtCtrl.val('');
                    amtCtrl.prop('disabled', true);
                }
            }
        });
    }


    // Fees Payments
    if (p.feePayAmt.length > 0) {
        
        feePayStr = p.feePayAmt.val().replace('$', '').replace(',', '');
        feePay = parseFloat(feePayStr);
        
        if (isNaN(feePay) || feePay < 0) {
            p.feePayAmt.val('');
            feePay = 0;
        }
    }

    // Fees Charges (checkout)
    if (p.feesCharges.length > 0) {
        feeCharge = parseFloat(p.feesCharges.val());
        if (isNaN(feeCharge)) {
            feeCharge = 0;
        }
    }

    // Guest Credit (checkout)
    if (p.guestCredit.length > 0) {
        guestCreditAmt = parseFloat(p.guestCredit.val());
        if (isNaN(guestCreditAmt)) {
            guestCreditAmt = 0;
        }
    }

    // Deposit refund? (checkout)
    if (p.depRefundAmt.length > 0) {
        depRfAmt = parseFloat(p.depRefundAmt.val());
        if (isNaN(depRfAmt)) {
            depRfAmt = 0;
        }
    }

    // Test held amount if any.
    if (p.heldCb.length > 0) {
        
        heldTotal = parseFloat(p.heldCb.data('amt'));
        
        if (isNaN(heldTotal) || heldTotal < 0) {
            heldTotal = 0;
        }
              
        if (p.heldCb.prop("checked")) {
            heldAmt = heldTotal;
        }
        
    }


    // Compute total charges
    totCharges = vfee + kdep + feeCharge + invAmt + guestCreditAmt + depRfAmt;


    if (!isChdOut) {
        totCharges += feePay;
    }
    
    // Adjust charges by any held amount
    if (totCharges > 0 && heldAmt > 0) {

        // reduce total charges
        if (heldAmt > totCharges && !isChdOut) {
            heldAmt = totCharges;
            totCharges = 0;
        } else {
            totCharges -= heldAmt;
        }

    } else if (totCharges < 0 && heldAmt > 0) {
        // Increase return
        totCharges -= heldAmt;
        
    } else if (totCharges === 0 && heldAmt > 0 && isChdOut) {
        
        totCharges -= heldAmt;
        
    } else if (p.heldCb.length > 0) {
 
        p.heldAmtTb.val('');
    }



    if (isChdOut) {
        
        $('.hhk-minPayment').show('fade');

        // Payment amount
        if (totCharges < 0) {
            totPay = totCharges - feePay;
        } else {
            totPay = totCharges + feePay;
        }

        // Manage overpayment
        if ((totCharges - feePay) <= 0) {

            $('.hhk-HouseDiscount').hide();
            p.hsDiscAmt.val('');
            p.finalPaymentCb.prop('checked', false);

            
            overPayAmt = 0 - (totCharges - feePay);

            
            if (p.selBalTo.val() === 'r') {

                if (totCharges >= 0) {

                    if (feePay !== totCharges) {
                        alert('Pay Room Fees amount is reduced to: $' + totCharges.toFixed(2).toString());
                    }
                    feePay = totCharges;
                    overPayAmt = 0;
                    p.selBalTo.val('');
                    $('#txtRtnAmount').val('');
                    $('#divReturnPay').hide();

                } else {

                    if (feePay > 0) {
                        alert('Pay Room Fees amount is reduced to: $0.00');
                    }
                    overPayAmt -= feePay;
                    feePay = 0;
                    $('#divReturnPay').show('fade');
                    $('#txtRtnAmount').val(overPayAmt.toFixed(2).toString());
                }

            } else {
                $('#txtRtnAmount').val('');
                $('#divReturnPay').hide();
            }

            totPay = feePay;
            
            if (overPayAmt > 0) {
                $('.hhk-Overpayment').show('fade');
            } else {
                $('.hhk-Overpayment').hide();
            }


        } else {

            // Manage Underpayment
            $('.hhk-Overpayment').hide();
            overPayAmt = 0;
            
            if (p.finalPaymentCb.prop('checked')) {
                
                hsPay = totCharges - feePay;
                
                if (hsPay <= 0) {
                    hsPay = 0;
                    p.hsDiscAmt.val('');
                } else {
                    p.hsDiscAmt.val((0 - hsPay).toFixed(2).toString());
                }
                
                totPay = feePay;

            } else {
                p.hsDiscAmt.val('');
                totPay = vfee + kdep + invAmt + feePay;
            }


            $('.hhk-HouseDiscount').show('fade');


        }

    } else {

        // still checked in
        $('.hhk-Overpayment').hide();
        $('.hhk-HouseDiscount').hide();
        p.hsDiscAmt.val('');
        overPayAmt = 0;
        totPay = totCharges;
        ckedInCharges = vfee + kdep + invAmt + feePay;

    }



    if (totPay > 0 || (totPay < 0 && ! isChdOut)) {

        $('.paySelectTbl').show('fade');
        $('.hhk-minPayment').show('fade');
        
        if (totPay < 0 && ! isChdOut) {
            $('#txtRtnAmount').val((0 - totPay).toFixed(2).toString());
        }

    } else {

        totPay = 0;

        $('.paySelectTbl').hide();

        if (isChdOut === false && ckedInCharges === 0.0) {
            $('.hhk-minPayment').hide();
            heldAmt = 0;
        } else {
            $('.hhk-minPayment').show('fade');
        }
    }

    if (feePay === 0 && feePayStr === '') {
        p.feePayAmt.val('');
    } else {
        p.feePayAmt.val(feePay.toFixed(2).toString());
    }

    if (overPayAmt === 0) {
        p.overPay.val('');
    } else {
        p.overPay.val(overPayAmt.toFixed(2).toString());
    }

    if (heldAmt > 0) {
        p.heldAmtTb.val((0 - heldAmt).toFixed(2).toString());
    } else {
        p.heldAmtTb.val('');
    }
    
    p.totalCharges.val(totCharges.toFixed(2).toString());
    p.totalPayment.val(totPay.toFixed(2).toString());
    $('#spnPayAmount').text('$' + totPay.toFixed(2).toString());
    
    p.cashTendered.change();
}
    

/**
 * 
 * @param {jquery} $rateSelector
 * @param {int} idVisit
 * @param {int} visitSpan
 * @param {jquery} $diagBox
 * @returns {undefined}
 */
function setupPayments($rateSelector, idVisit, visitSpan, $diagBox) {
    "use strict";
    var ptsel = $('#PayTypeSel');
    var chg = $('.tblCredit');
    var p = new payCtrls();
    
    if (chg.length === 0) {
        chg = $('.hhk-mcred');
    }
    
    if (ptsel.length > 0) {
        ptsel.change(function () {
            $('.hhk-cashTndrd').hide();
            $('.hhk-cknum').hide();
            $('#tblInvoice').hide();
            $('.hhk-transfer').hide();
            $('.hhk-tfnum').hide();
            chg.hide();
            $('.hhkKeyNumber').hide();
            $('#tdCashMsg').hide();
            $('.paySelectNotes').show();
            
            if ($(this).val() === 'cc') {
                chg.show('fade');
                $('.hhkKeyNumber').show();
            } else if ($(this).val() === 'ck') {
                $('.hhk-cknum').show('fade');
            } else if ($(this).val() === 'in') {
                $('#tblInvoice').show('fade');
                $('.paySelectNotes').hide();
            } else if ($(this).val() === 'tf') {
                $('.hhk-transfer').show('fade');
            } else {
               $('.hhk-cashTndrd').show('fade');
            }
        });
        ptsel.change();
    }

    // Set up return table
    var rtnsel = $('#rtnTypeSel');
    var rtnchg = $('.tblCreditr');
    
    if (rtnchg.length === 0) {
        rtnchg = $('.hhk-mcredr');
    }
    
    if (rtnsel.length > 0) {
        rtnsel.change(function () {
            rtnchg.hide();
            $('.hhk-transferr').hide();
            $('.payReturnNotes').show();
            $('.hhk-cknum').hide();

            if ($(this).val() === 'cc') {
                rtnchg.show('fade');
            } else if ($(this).val() === 'ck') {
                $('.hhk-cknum').show('fade');
            } else if ($(this).val() === 'tf') {
                $('.hhk-transferr').show('fade');
            } else if ($(this).val() === 'in') {
                $('.payReturnNotes').hide();
            }
        });
        rtnsel.change();
    }


    if (p.selBalTo.length > 0) {
        p.selBalTo.change(function () {
            amtPaid();
        });
    }
    
    if (p.finalPaymentCb.length > 0) {
        p.finalPaymentCb.change(function () {
            amtPaid();
        });
    }

    if (p.keyDepCb.length > 0) {
        p.keyDepCb.change(function() {
            amtPaid();
        });
    }

    if (p.heldCb.length > 0) {
        p.heldCb.change(function() {
            amtPaid();
        });
    }
    
    if (p.invoiceCb.length > 0) {

        p.invoiceCb.change(function() {
            amtPaid();
        });
        
        $('.hhk-payInvAmt').change(function() {
            amtPaid();
        });
    }

    if (p.visitFeeCb.length > 0) {
        p.visitFeeCb.change(function() {
            amtPaid();
        });
    }

    if (p.feePayAmt.length > 0) {
        
        p.feePayAmt.change(function() {
            $(this).removeClass('ui-state-error');
            amtPaid();
        });
    }

    if (p.cashTendered.length > 0) {
        p.cashTendered.change(function () {
            p.cashTendered.removeClass('ui-state-highlight');
            $('#tdCashMsg').hide();
            var total = parseFloat(p.totalPayment.val().replace(',', ''));
            
            if (isNaN(total) || total < 0) {
                total = 0;
            }
            
            var cash = parseFloat(p.cashTendered.val().replace('$', '').replace(',', ''));
            if (isNaN(cash) || cash < 0) {
                cash = 0;
                p.cashTendered.val('');
            }
            var diff = cash - total;
            if (diff < 0) {
                diff = 0;
                p.cashTendered.addClass('ui-state-highlight');
            }
            $('#txtCashChange').text('$' + diff.toFixed(2).toString());
        });
    }
    
    // Extra payments or credits applied.
    if (p.adjustBtn.length > 0) {
        p.adjustBtn.button();
        p.adjustBtn.click(function () {
            getApplyDiscDiag(idVisit, $diagBox);
        });
    }

    // Delete invoice
    $('#divPmtMkup').on('click', '.invAction', function (event) {
        event.preventDefault();
        if ($(this).data('stat') == 'del') {
            if (!confirm('Delete this Invoice?')) {
                return;
            }
        }

        invoiceAction($(this).data('iid'), $(this).data('stat'), event.target.id, '#keysfees', true);
    });

    // Billing agent chooser set up
    if ($('#txtInvSearch').length > 0) {
        
        $('#txtInvSearch').keypress(function (event) {
            
            var mm = $(this).val();
            if (event.keyCode == '13') {

                if (mm == '' || !isNumber(parseInt(mm, 10))) {

                    alert("Don't press the return key unless you enter an Id.");
                    event.preventDefault();

                } else {

                    $.getJSON("../house/roleSearch.php", {cmd: "filter", 'basis':'ba', letters:mm},
                    function(data) {
                        try {
                            data = data[0];
                        } catch (err) {
                            alert("Parser error - " + err.message);
                            return;
                        }
                        if (data && data.error) {
                            if (data.gotopage) {
                                response();
                                window.open(data.gotopage);
                            }
                            data.value = data.error;
                        }
                        getInvoicee(data, idVisit);
                    });

                }
            }
        });
        createAutoComplete($('#txtInvSearch'), 3, {cmd: "filter", 'basis':'ba'}, function (item) { getInvoicee(item, idVisit); }, false);
    }

    // Days - Payment calculator
    $('#daystoPay').change(function () {
        var days = parseInt($(this).val()),
            idVisit = parseInt($(this).data('vid')),
            fixed = parseFloat($('#txtFixedRate').val()),
            noGuests = parseInt($('#spnNumGuests').text()),
            feePayAmt = p.feePayAmt;

        if (isNaN(noGuests)) {
            noGuests = 1;
        }

        if (isNaN(fixed)) {
            fixed = 0;
        }

        var adjust = parseFloat($('#txtadjAmount').val());
        if (isNaN(adjust)) {
            adjust = 0;
        }

        if (isNaN(days)) {
            $(this).val('');
            return;
        }

        if (days > 0) {
            
            daysCalculator(days, $rateSelector.val(), idVisit, fixed, adjust, noGuests, 0, function(amt) {
                feePayAmt.val(amt.toFixed(2).toString());
                feePayAmt.change();
            });
        }
    });

    amtPaid();
}

function daysCalculator(days, idRate, idVisit, fixedAmt, adjAmt, numGuests, idResv, rtnFunction) {
    
    if (days > 0) {
        var parms = {cmd:'rtcalc', vid: idVisit, rid: idResv, nites: days, rcat: idRate, fxd: fixedAmt, adj: adjAmt, gsts: numGuests};
        // ask momma how much
        $.post('ws_ckin.php', parms,
            function(data) {
                if (!data) {
                    alert('Bad Reply from Server');
                    return;
                }
                try {
                    data = $.parseJSON(data);
                } catch (err) {
                    alert("Parser error - " + err.message);
                    return;
                }
                if (data.error) {
                    if (data.gotopage) {
                        window.open(data.gotopage);
                    }
                    flagAlertMessage(data.error, 'error');
                    return;
                }
                if (data.amt) {
                    
                    var amt = parseFloat(data.amt);
                    
                    if (isNaN(amt) || amt < 0) {
                        amt = 0;
                    }
                    
                    rtnFunction(amt);
                }
        });
    }

}

function verifyBalDisp() {
    
    if ($('#selexcpay').val() == '' && $('#txtOverPayAmt').val() != '') {
        $('#payChooserMsg').text('Set "Apply To" to the desired overpayment disposition. ').show();
        $('#selexcpay').addClass('ui-state-highlight');
        $('#pWarnings').text('Set "Apply To" to the desired overpayment disposition.').show();
        return false;
    } else {
        $('#payChooserMsg').text('').hide();
        $('#selexcpay').removeClass('ui-state-highlight');
    }
    return true;
}

function verifyAmtTendrd() {
    "use strict";
    if ($('#PayTypeSel').length === 0) {
        return true;
    }
    
    $('#tdCashMsg').hide('fade');
    $('#tdInvceeMsg').text('').hide();
    
    if ($('#PayTypeSel').val() === 'ca') {
        
        var total = parseFloat($('#totalPayment').val().replace('$', '').replace(',', '')),
            tendered = parseFloat($('#txtCashTendered').val().replace('$', '').replace(',', '')),
            remTotal = $('#remtotalPayment');
            
        if (remTotal.length > 0) {
            total = parseFloat(remTotal.val().replace('$', '').replace(',', ''));
        }
        
        if (isNaN(total) || total < 0) {
            total = 0;
        }

        if (isNaN(tendered) || tendered < 0) {
            tendered = 0;
        }
    
        if (total > 0 && tendered <= 0) {
            $('#tdCashMsg').text('Enter the amount paid into "Amount Tendered" ').show();
            $('#pWarnings').text('Enter the amount paid into "Amount Tendered"').show();
            return false;
        }
        
        if (total > 0 && tendered < total) {
            $('#tdCashMsg').text('Amount tendered is not enough ').show('fade');
            $('#pWarnings').text('Amount tendered is not enough').show();
            return false;
        }
    } else if ($('#PayTypeSel').val() === 'in') {
        
        var idPayor = parseInt($('#txtInvId').val(), 10);
        
        if (isNaN(idPayor) || idPayor < 1) {
            $('#tdInvceeMsg').text('The Invoicee is missing. ').show('fade');
            return false;
        }
    }
    return true;
}


/**
 * 
 * @param {string} dialogId
 * @param {string} markup 
 * @param {string} title
 * @param {int} width
 * @returns {undefined}
 */
function showReceipt(dialogId, markup, title, width) {
    var pRecpt = $(dialogId);
    var btn = $("<div id='print_button' style='margin-left:1em;'>Print</div>");
    var opt = {mode: "popup", 
        popClose: false,
        popHt      : 500,
        popWd      : 400,
        popX       : 200,
        popY       : 200,
        popTitle   : title};

    if (width === undefined || !width) {
        width = 550;
    }

    pRecpt.children().remove();
    pRecpt.append($(markup).addClass('ReceiptArea').css('max-width', (width + 'px') ));

    btn.button();
    btn.click(function() {
        $(".ReceiptArea").printArea(opt);
        pRecpt.dialog('close');
    });

    pRecpt.prepend(btn);
    pRecpt.dialog("option", "title", title);
    pRecpt.dialog('option', 'buttons', {});
    pRecpt.dialog('option', 'width', width);
    pRecpt.dialog('open');

    opt.popHt = $('#pmtRcpt').height();
    opt.popWd = $('#pmtRcpt').width();

}

/**
 * 
 * @param {int} pid
 * @param {string} idDialg
 * @returns {undefined}
 */
function reprintReceipt(pid, idDialg) {
    
    $.post('ws_ckin.php',
            {
                cmd: 'getPrx',
                pid: pid
            },
        function(data) {
          if (data) {
            try {
                data = $.parseJSON(data);
            } catch (e) {
                alert("Parser error - " + e.message);
                return;
            }
            
            if (data.error) {
                if (data.gotopage) {
                    window.location.assign(data.gotopage);
                }
                flagAlertMessage(data.error, 'error');
                
            }
            
            
            // launch receipt dialog box
            showReceipt(idDialg, data.receipt, 'Receipt Copy');
          }
    });

}

function paymentRedirect (data, $xferForm) {
    "use strict";
    if (data) {

        if (data.hostedError) {
            flagAlertMessage(data.hostedError, 'error');

        } else if (data.cvtx) {
            
            window.location.assign(data.cvtx);
            
        } else if (data.xfer && $xferForm.length > 0) {

            $xferForm.children('input').remove();
            $xferForm.prop('action', data.xfer);

            if (data.paymentId && data.paymentId != '') {
                $xferForm.append($('<input type="hidden" name="PaymentID" value="' + data.paymentId + '"/>'));
            } else if (data.cardId && data.cardId != '') {
                $xferForm.append($('<input type="hidden" name="CardID" value="' + data.cardId + '"/>'));
            } else {
                flagAlertMessage('PaymentId and CardId are missing!', 'error');
                return;
            }

            $xferForm.submit();

        } else if (data.inctx) {

            $('#contentDiv').empty().append($('<p>Processing Credit Payment...</p>'));
            InstaMed.launch(data.inctx);
            $('#instamed').css('visibility', 'visible').css('margin-top', '50px;');
        }
    }
}


function cardOnFile(id, idGroup, postBackPage) {
    
    var parms = {cmd: 'cof', idGuest: id, idGrp: idGroup, pbp: postBackPage};
    
    $('#tblupCredit').find('input').each(function() {
        if (this.checked) {
            parms[$(this).attr('id')] = $(this).val();
        }
    });
    
    // Go to the server for payment data, then come back and submit to new URL to enter credit info.
    $.post('ws_ckin.php', parms,
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
            if (data.hostedError) {
                flagAlertMessage(data.hostedError, 'error');
            }

            paymentRedirect (data, $('#xform'));
            
            if (data.success && data.success != '') {
                flagAlertMessage(data.success, 'success');
            }

            if (data.COFmkup && data.COFmkup !== '') {
                $('#tblupCredit').remove();
                $('#upCreditfs').append($(data.COFmkup));
            }
        }
    });
}

function updateCredit(id, idReg, name, strCOFdiag, pbp) {
    
    var gnme = '';
    
    if (name && name != '') {
        gnme = ' - ' + name;
    }
    
    $.post('ws_ckin.php',
            {
                cmd: 'viewCredit',
                idGuest: id,
                reg: idReg,
                pbp: pbp
            },
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
            }
            
            var buttons = {
                "Continue": function() {
                    cardOnFile(id, idReg, data.pbp);
                    $(this).dialog("close");
                },
                "Cancel": function() {
                    $(this).dialog("close");
                }
            };

            if (data.success) {
                var cof = $('#' + strCOFdiag);
                cof.children().remove();
                cof.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog"/>').append($(data.success)));
                cof.dialog('option', 'buttons', buttons);
                cof.dialog('option', 'width', 400);
                cof.dialog('option', 'title', 'Card On File' + gnme);
                cof.dialog('open');
            }
        }
    });
}

