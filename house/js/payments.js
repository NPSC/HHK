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
                            tax = parseFloat($('#houseTax').val()),
                            vid = $('#housePayment').data('vid'),
                            item = '',
                            adjDate = $.datepicker.formatDate("yy-mm-dd", $('#housePaymentDate').datepicker('getDate')),
                            notes = $('#housePaymentNote').val();

                        if (isNaN(amt)) {
                            amt = 0;
                        }
                        if (isNaN(tax)) {
                            tax = 0;
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

                    $('.' + hid).val('');
                    $('.' + sho).val('');
                    $('#housePayment').val('');
                    $('#housePayment').change();

                    $('.' + sho).show();
                    $('.' + hid).hide();

                });

                gblAdjustData['disc'] = data.disc;
                gblAdjustData['addnl'] = data.addnl;

                $('#selAddnlChg, #selHouseDisc').change(function () {
                    var amts = parseFloat(gblAdjustData[$(this).data('amts')][$(this).val()]);
                    $('#housePayment').val(amts.toFixed(2));
                    $('#housePayment').change();
                });

                $('#housePayment').change(function () {
                    if ($('#cbAdjustPmt2').prop('checked') && $('#houseTax').length > 0) {

                        var tax = parseFloat($('#houseTax').data('tax')),
                            amt = parseFloat($('#housePayment').val().replace('$', '').replace(',', '')),
                            taxAmt = 0.0,
                            totalAmt = 0.0;

                        if (isNaN(tax)) {
                            tax = 0;
                        }
                        if (isNaN(amt)) {
                            amt = 0;
                        }

                        taxAmt = tax * amt;
                        totalAmt = amt + taxAmt;
                        $('#houseTax').val((taxAmt > 0 ? (taxAmt).toFixed(2) : ''));
                        $('#totalHousePayment').val((totalAmt > 0 ? totalAmt.toFixed(2) : ''));
                    }
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
                $diagBox.dialog('option', 'width', 430);
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
    t.depRefundCb = $('#cbDepRefundApply');
    t.depRefundAmt = $('#DepRefundAmount');
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
    t.reimburseVatCb = $('#cbReimburseVAT');
    t.reimburseVatAmt = $('#reimburseVat');
    t.hsDiscAmt = $('#HsDiscAmount');
    t.finalPaymentCb = $('input#cbFinalPayment');
    t.overPay = $('#txtOverPayAmt');
    t.guestCredit = $('#guestCredit');
    t.selBalTo = $('#selexcpay');

};

function roundTo(n, digits) {

    if (digits === undefined) {
        digits = 0;
    }

    var multiplicator = Math.pow(10, digits);
    n = parseFloat((n * multiplicator).toFixed(11));
    return Math.round(n) / multiplicator;
}

function amtPaid() {
    "use strict";
    var p = new payCtrls(),
        kdep = 0,
        vfee = 0,
        feePay = 0,
        feePayPreTax = 0,
        feePayText = '',
        invAmt = 0,
        heldAmt = 0,
        reimburseAmt = 0,
        totCharges = 0,
        ckedInCharges = 0,
        totPay = 0,
        depRfAmt = 0,
        roomBalTaxDue = 0,
        feePayTaxAmt = 0,
        overPayAmt = 0,
        totReturns = 0,
        totReturnTax = 0,
        totReturnPreTax = 0,
        isChdOut = isCheckedOut,
        roomBalDue = parseFloat($('#spnCfBalDue').data('rmbal')),
        totalBalDue = parseFloat($('#spnCfBalDue').data('totbal')),
        $taxingItems = $('.hhk-TaxingItem');

    if (isNaN(roomBalDue)) {
        roomBalDue = 0;
    } else {

        $taxingItems.each(function () {
            var rate = parseFloat($(this).data('taxrate'));
            roomBalTaxDue += roundTo(roomBalDue * rate, 2);
        });
    }

    p.msg.text('').hide();

    // Visit fees - vfee
    if (p.visitFeeCb.length > 0) {

        vfee = parseFloat($('#spnvfeeAmt').data('amt'));

        if (isNaN(vfee) || vfee < 0 || p.visitFeeCb.prop("checked") === false) {
            vfee = 0;
            p.visitFeeAmt.val('');
        } else {
           p.visitFeeAmt.val(vfee.toFixed(2).toString());
        }
    }

    // Deposits - kdep
    if (!isChdOut && p.keyDepCb.length > 0) {

        kdep = parseFloat($('#hdnKeyDepAmt').val());

        if (isNaN(kdep) || kdep < 0 || p.keyDepCb.prop("checked") === false) {
            kdep = 0;
            p.keyDepAmt.val('');
        } else {
            p.keyDepAmt.val(kdep.toFixed(2).toString());
        }
    }

    // Unpaid Invoices - invAmt
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
                } else {
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

    // Deposit refund? depRfAmt
    if (p.depRefundAmt.length > 0 && isChdOut) {

        depRfAmt = parseFloat(p.depRefundAmt.data('amt'));

        if (isNaN(depRfAmt) || depRfAmt < 0 || p.depRefundCb.prop('checked') === false) {

            depRfAmt = 0;
            p.depRefundAmt.val('');

        } else {
            p.depRefundAmt.val((0 - depRfAmt).toFixed(2).toString());
        }
    }

    // Test held amount if any. - heldAmt
    if (p.heldCb.length > 0) {

        heldAmt = parseFloat(p.heldCb.data('amt'));

        if (isNaN(heldAmt) || heldAmt < 0 || p.heldCb.prop("checked") === false) {
            heldAmt = 0;
        }
    }

    // Reimburse value added taxes
    if (p.reimburseVatCb.length > 0) {

        reimburseAmt = parseFloat(p.reimburseVatCb.data('amt'));

        if (isNaN(reimburseAmt) || reimburseAmt < 0 || p.reimburseVatCb.prop('checked') === false) {
            reimburseAmt = 0;
        }
    }

    totReturns = heldAmt + depRfAmt + reimburseAmt;

    $taxingItems.each(function () {
        var rate = parseFloat($(this).data('taxrate'));

        totReturnTax += roundTo(totReturns / (1 + rate), 2);
    });

    if (totReturnTax > roomBalTaxDue) {
       totReturnTax = roomBalTaxDue;
    }

    totReturnPreTax = totReturns - totReturnTax;

    // Fees Payments - feePay
    if (p.feePayAmt.length > 0) {

        feePayText = p.feePayAmt.val().replace('$', '').replace(',', '');
        feePayPreTax = roundTo(parseFloat(feePayText), 2);

        // allow zeros through.
        if (feePayText !== '0') {
            feePayText = '';
        }

        if (isNaN(feePayPreTax) || feePayPreTax <= 0) {
            feePayPreTax = 0;
        }

        if ($taxingItems.length > 0) {

            $taxingItems.each(function () {
                var rate = parseFloat($(this).data('taxrate'));
                var tax = roundTo(feePayPreTax * rate, 2);
                $(this).val(tax.toFixed(2).toString());
                feePayTaxAmt += tax;
            });


            // Only tax up to the room balance due.
            if (feePayTaxAmt > (roomBalTaxDue - totReturnTax) && isChdOut) {
                feePayTaxAmt = (roomBalTaxDue - totReturnTax);
            }

            if (feePayTaxAmt <= 0) {
                feePayTaxAmt = 0;
            }

        }

        feePay = feePayPreTax + feePayTaxAmt;
    }


    if (isChdOut) {
        // Checked out
        $('.hhk-minPayment').show('fade');
        overPayAmt = 0;
        p.hsDiscAmt.val('');

        var totRmBalDue = roundTo(roomBalDue + roomBalTaxDue, 2);

        // Show correct row for charges due
        if (roomBalDue >= 0) {
            p.feesCharges.val(totRmBalDue.toFixed(2).toString());
            $('.hhk-GuestCredit').hide();
            $('.hhk-RoomCharge').show();
        } else {
            p.guestCredit.val(totRmBalDue.toFixed(2).toString());
            $('.hhk-RoomCharge').hide();
            $('.hhk-GuestCredit').show();
        }


        totCharges = vfee + invAmt + totRmBalDue - totReturns;

        totPay = vfee + invAmt + feePay;

        if (totCharges >= totPay) {

            var hsPay = roundTo((vfee + invAmt + roomBalDue - totReturnPreTax - feePayPreTax), 2);

            // Underpaid
            if (hsPay > 0){

                if (p.finalPaymentCb.prop('checked')) {
                    // Manage House Waive of underpaid amount

                    var taxBal = roundTo((roomBalTaxDue - (feePayTaxAmt + totReturnTax)), 2);
                    p.hsDiscAmt.val(hsPay.toFixed(2).toString());

                    p.feesCharges.val((totRmBalDue - taxBal).toFixed(2).toString());

                    totCharges = totCharges - taxBal;
                    totPay = feePay;

                } else {
                    // Guest underpaid, no House Waive
                    p.hsDiscAmt.val('');
                    //totPay = ;
                }

                $('.hhk-Overpayment').hide();
                $('.hhk-HouseDiscount').show('fade');

            } else {
                p.finalPaymentCb.prop('checked', false);
                p.hsDiscAmt.val('');
                $('.hhk-HouseDiscount').hide();
                $('.hhk-Overpayment').hide();
            }

            // Clear overpayment selector
            p.selBalTo.val('');
            $('#txtRtnAmount').val('');
            $('#divReturnPay').hide();
        }

        // overpaid
        else {
            // Guest credit

            p.finalPaymentCb.prop('checked', false);
            p.hsDiscAmt.val('');

            overPayAmt = totPay - totCharges;

            if (p.selBalTo.val() === 'r') {

                if (totCharges >= 0) {

                    if (feePayPreTax > overPayAmt) {
                        alert('Pay Room Fees amount is reduced to: $' + (feePayPreTax - overPayAmt).toFixed(2).toString());
                    }

                    feePayPreTax = feePayPreTax - overPayAmt;
                    feePay = feePayPreTax + feePayTaxAmt;
                    overPayAmt = 0;

                    p.selBalTo.val('');
                    $('#txtRtnAmount').val('');
                    $('#divReturnPay').hide();

                } else {

                    if (feePay > 0) {
                        alert('Pay Room Fees amount is reduced to: $0.00');
                    }
                    overPayAmt -= feePay;
                    feePayPreTax = 0;
                    feePayTaxAmt = 0;
                    $taxingItems.each(function () {
                        $(this).val('');
                    });
                    feePay = 0;

                    $('#divReturnPay').show('fade');
                    $('#txtRtnAmount').val(overPayAmt.toFixed(2).toString());
                }

                totPay = vfee + invAmt + feePay;

            } else {
                $('#txtRtnAmount').val('');
                $('#divReturnPay').hide();
            }


            if (overPayAmt.toFixed(2) > 0) {
                $('.hhk-Overpayment').show('fade');
                $('.hhk-HouseDiscount').hide();
            } else {
                $('.hhk-Overpayment').hide();
                $('.hhk-HouseDiscount').hide();
            }
        }
    // else still checked in
    } else {

        // still checked in
        totCharges = vfee + kdep + invAmt + feePay;

        // Adjust charges by any held amount
        if (totCharges > 0 && heldAmt > 0) {

            // reduce total charges
            if (heldAmt > totCharges) {
                heldAmt = totCharges;
                totCharges = 0;
            } else {
                totCharges -= heldAmt;
            }

        } else if (totCharges < 0 && heldAmt > 0) {
            // Increase return
            totCharges -= heldAmt;

        } else if (p.heldCb.length > 0) {

            p.heldAmtTb.val('');
        }

        // Adjust charges by any reimbursed taxes
        if (totCharges > 0 && reimburseAmt > 0) {

            // reduce total charges
            if (reimburseAmt > totCharges) {
                reimburseAmt = 0;
            } else {
                totCharges -= reimburseAmt;
            }

        } else if (totCharges < 0 && reimburseAmt > 0) {
            // Increase return
            totCharges -= reimburseAmt;

        } else if (p.reimburseVatCb.length > 0) {

            reimburseAmt = 0;
        }


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

        // manage cof box
        if ($('#cbNewCard').length > 0) {
            $('#cbNewCard').prop('checked', false).change().prop('disabled', true);
        }

        if (totPay < 0 && ! isChdOut) {
            $('#txtRtnAmount').val((0 - totPay).toFixed(2).toString());
        }

    } else {

        totPay = 0;

        $('.paySelectTbl').hide();

        // manage cof box
        if ($('#cbNewCard').length > 0) {
            $('#cbNewCard').prop('disabled', false);
        }

        if (isChdOut === false && ckedInCharges === 0.0) {
            $('.hhk-minPayment').hide();
            heldAmt = 0;
            reimburseAmt = 0;
        } else {
            $('.hhk-minPayment').show('fade');
        }
    }

    if (feePay === 0) {
        p.feePayAmt.val(feePayText);
        //$('#feesTax').val('');
    } else {
        p.feePayAmt.val(feePayPreTax.toFixed(2).toString());
        //$('#feesTax').val(feePayTaxAmt.toFixed(2).toString());
    }

    if (overPayAmt.toFixed(2) == 0) {
        p.overPay.val('');
    } else {
        p.overPay.val(overPayAmt.toFixed(2).toString());
    }

    if (heldAmt.toFixed(2) > 0) {
        p.heldAmtTb.val((0 - heldAmt).toFixed(2).toString());
    } else {
        p.heldAmtTb.val('');
    }

    if (reimburseAmt > 0) {
        p.reimburseVatAmt.val((0 - reimburseAmt).toFixed(2).toString());
    } else {
        p.reimburseVatAmt.val('');
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
            $('#trvdCHName').hide();
            $('#tdCashMsg').hide();
            $('.paySelectNotes').show();

            if ($(this).val() === 'cc') {
                chg.show('fade');
                if ($('input[name=rbUseCard]:checked').val() == 0) {
                    $('#trvdCHName').show();
                }
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

    // Card on file Cardholder name.
    if ($('#trvdCHName').length > 0) {

        $('input[name=rbUseCard]').on('change', function () {
            if ($(this).val() == 0) {
                $('#trvdCHName').show();
            } else {
                $('#trvdCHName').hide();
                $('#btnvrKeyNumber').prop('checked', false).change();
            }
        });

        if ($('input[name=rbUseCard]:checked').val() > 0) {
            $('#trvdCHName').hide();
        }

        $('#btnvrKeyNumber').change(function() {

            if (this.checked && $('input[name=rbUseCard]:checked').val() == 0) {
                $('#txtvdNewCardName').show();
            } else {
                $('#txtvdNewCardName').hide();
                $('#txtvdNewCardName').val('');
            }
        });

        $('#btnvrKeyNumber').change();
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

    if (p.depRefundCb.length > 0) {
        p.depRefundCb.change(function() {
            amtPaid();
        });
    }

    if (p.heldCb.length > 0) {
        p.heldCb.change(function() {
            amtPaid();
        });
    }

    if (p.reimburseVatCb.length > 0) {
        p.reimburseVatCb.change(function() {
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
            feePayAmt = p.feePayAmt,
            tax = parseFloat($('#spnRcTax').data('tax')),
            adjust = parseFloat($('#txtadjAmount').val());

        $(this).val('');

        if (isNaN(noGuests)) {
            noGuests = 1;
        }

        if (isNaN(fixed)) {
            fixed = 0;
        }

        if (isNaN(tax)) {
            tax = 0;
        }

        if (isNaN(adjust)) {
            adjust = 0;
        }

        if (isNaN(days)) {

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
    var total = parseFloat($('#totalPayment').val().replace('$', '').replace(',', ''));

    $('#tdCashMsg').hide('fade');
    $('#tdInvceeMsg').text('').hide();
    $('#tdChargeMsg').text('');

    if ($('#PayTypeSel').val() === 'ca') {

        var tendered = parseFloat($('#txtCashTendered').val().replace('$', '').replace(',', '')),
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

        if ((isNaN(idPayor) || idPayor < 1) && total != 0) {
            $('#tdInvceeMsg').text('The Invoicee is missing. ').show('fade');
            return false;
        }
        
    } else if ($('#PayTypeSel').val() === 'cc') {
        
        if ($('#selccgw').length > 0 && $('#selccgw').val() === '') {
            $('#tdChargeMsg').text('Select a location.');
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

function setupCOF() {

    // Card on file Cardholder name.
    if ($('#trCHName').length > 0) {

        $('#cbNewCard').change(function () {

            if (this.checked) {
                $('.hhkKeyNumber').show();
            } else {
                $('.hhkKeyNumber').hide();
                $('#cbKeyNumber').prop('checked', false).change();
            }
        });

        $('#cbNewCard').change();

        $('#cbKeyNumber').change(function() {

            if (this.checked && $('#cbNewCard').prop('checked') === true) {
                $('#trCHName').show();
            } else {
                $('#trCHName').hide();
            }
        });

        $('#cbKeyNumber').change();
    }

}

function cardOnFile(id, idGroup, postBackPage) {

    var parms = {cmd: 'cof', idGuest: id, idGrp: idGroup, pbp: postBackPage};

    $('#tblupCredit').find('input').each(function() {

        if ($(this).attr('type') === 'checkbox') {
            if (this.checked !== false) {
                parms[$(this).attr('id')] = 'on';
            }
        } else if ($(this).hasClass('ckdate')) {
            var tdate = $(this).datepicker('getDate');
            if (tdate) {
                parms[$(this).attr('id')] = tdate.toJSON();
            } else {
                 parms[$(this).attr('id')] = '';
            }
        } else if ($(this).attr('type') === 'radio') {
            if (this.checked !== false) {
                parms[$(this).attr('id')] = this.value;
            }
        } else{
            parms[$(this).attr('id')] = this.value;
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
                setupCOF();
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

                setupCOF();
                cof.dialog('open');
            }
        }
    });
}

function paymentsTable(tableID, containerID) {
    
    $('#' + tableID).dataTable({
        'columnDefs': [
            {'targets': 8,
             'type': 'date',
             'render': function ( data, type, row ) {return dateRender(data, type);}
            }
         ],
        'dom': '<"top"if>rt<"bottom"lp><"clear">',
        'displayLength': 50,
        'lengthMenu': [[25, 50, -1], [25, 50, "All"]]
    });

    // Invoice viewer
    $('#' + containerID).on('click', '.invAction', function (event) {
        invoiceAction($(this).data('iid'), 'view', event.target.id);
    });

    // Void/Reverse button
    $('#' + containerID).on('click', '.hhk-voidPmt', function () {
        var btn = $(this);
        var amt = parseFloat(btn.data("amt"));
        if (btn.val() !== "Saving..." && confirm("Void/Reverse this payment for $" + amt.toFixed(2).toString() + "?")) {
            btn.val('Saving...');
            sendVoidReturn(btn.attr('id'), 'rv', btn.data('pid'));
        }
    });

    // Void-return button
    $('#' + containerID).on('click', '.hhk-voidRefundPmt', function () {
        var btn = $(this);
        if (btn.val() !== 'Saving...' && confirm('Void this Return?')) {
            btn.val('Saving...');
            sendVoidReturn(btn.attr('id'), 'vr', btn.data('pid'));
        }
    });

    // Return button
    $('#' + containerID).on("click", ".hhk-returnPmt", function() {
        var btn = $(this);
        var amt = parseFloat(btn.data("amt"));
        if (btn.val() !== "Saving..." && confirm("Return this payment for $" + amt.toFixed(2).toString() + "?")) {
            btn.val("Saving...");
            sendVoidReturn(btn.attr("id"), "r", btn.data("pid"), amt);
        }
    });

    // Undo Return
    $('#' + containerID).on("click", ".hhk-undoReturnPmt", function () {
        var btn = $(this);
        var amt = parseFloat(btn.data("amt"));
        if (btn.val() !== "Saving..." && confirm("Undo this Return/Refund for $" + amt.toFixed(2).toString() + "?")) {
            btn.val("Saving...");
            sendVoidReturn(btn.attr("id"), "ur", btn.data("pid"));
        }
    });

    // Delete waive button
    $('#' + containerID).on('click', '.hhk-deleteWaive', function () {
        var btn = $(this);

        if (btn.val() !== 'Deleting...' && confirm('Delete this House payment?')) {
            btn.val('Deleting...');
            sendVoidReturn(btn.attr('id'), 'd', btn.data('ilid'), btn.data('iid'));
        }
    });

    $('#' + containerID).on('click', '.pmtRecpt', function () {
        reprintReceipt($(this).data('pid'), '#pmtRcpt');
    });

    $('#' + containerID).mousedown(function (event) {
        var target = $(event.target);
        if ( target[0].id !== 'pudiv' && target.parents("#" + 'pudiv').length === 0) {
            $('div#pudiv').remove();
        }
    });

}