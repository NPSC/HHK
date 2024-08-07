/**
 * payments.js
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
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


function roundTo(n, digits) {

    if (digits === undefined) {
        digits = 0;
    }

    let multiplicator = Math.pow(10, digits);
    n = parseFloat((n * multiplicator).toFixed(11));
    return Math.round(n) / multiplicator;
}


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
            function (data) {
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
                            "Save": function () {

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
                            "Cancel": function () {
                                $(this).dialog("close");
                            }
                        };

                        $diagBox.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(data.markup)));

                        $("#cbAdjustType").buttonset();

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
                        $diagBox.dialog('option', 'width', getDialogWidth(430));
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
 * @param (integer) index
 * @returns {undefined}
 */
function getInvoicee(item, orderNum, index) {
    "use strict";
    let cid = parseInt(item.id, 10);
    if (isNaN(cid) === false && cid > 0) {
        $('#txtInvName' + index).val(item.value);
        $('#txtInvId' + index).val(cid);
        setTaxExempt(item.taxExempt);
    } else {
        $('#txtInvName' + index).val('');
        $('#txtInvId' + index).val('');
        setTaxExempt(false);
    }
    $('#txtOrderNum').val(orderNum);
    $('#txtInvSearch').val('');
    amtPaid();
}

function setTaxExempt(taxExempt) {
    if (taxExempt == '1') {
        $('.hhk-TaxingItem').removeClass('hhk-applyTax').val('0.00');

    } else {
        $('.hhk-TaxingItem').addClass('hhk-applyTax');
    }
}

/**
 *
 * @param {str} btnid
 * @param {str} vorr
 * @param {int} idPayment
 * @param {decimal} amt
 * @param {object} refresh
 * @returns {undefined}
 */
function sendVoidReturn(btnid, vorr, idPayment, amt, refresh) {

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

	$.post('ws_ckin.php', prms, function(data) {
		let revMessage = '';
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
			if (data.reversal && data.reversal !== '') {
				revMessage = data.reversal;
				refresh();
			}
			if (data.warning) {
				flagAlertMessage(revMessage + data.warning, 'warning');
				refresh();
				return;
			}
			if (data.success) {
				flagAlertMessage(revMessage + data.success, 'success');
				refresh();
			}

            if (data.receipt) {
                showReceipt('#pmtRcpt', data.receipt, 'Receipt');
            }
        }
    });
}

class UpdateTaxes {
    constructor($taxingItems) {
        this.taxingItems = $taxingItems;
    }

    calcTax(preTaxAmt) {
        let taxAmt = 0;

        if (this.taxingItems.length > 0) {

            this.taxingItems.each(function () {
                let rate = parseFloat($(this).data('taxrate'));
                let tax = roundTo(preTaxAmt * rate, 2);
                $(this).val(tax.toFixed(2).toString());
                taxAmt += tax;
            });

            if (taxAmt <= 0) {
                taxAmt = 0;
            }
        }

        return taxAmt;
    }
}

class AmountVariables {
    constructor(isCheckedOut) {
        var t = this;
        t.kdep = 0;
        t.kdepCharge = 0;
        t.vfee = 0;
        t.visitfeeCharge = 0;
        t.feePay = 0;
        t.feePayPreTax = 0;
        t.feePayText = '';
        t.prePayRoomAmt = 0;
        t.invAmt = 0;
        t.invCharge = 0;
        t.invPayment = 0;
        t.heldAmt = 0;
        t.chkingIn = 0;
        t.reimburseAmt = 0;
        t.totCharges = 0;
        t.ckedInCharges = 0;
        t.totPay = 0;
        t.depRfAmt = 0;
        t.depRfPay = 0;
        t.roomBalTaxDue = 0;
        t.feePayTaxAmt = 0;
        t.overPayAmt = 0;
        t.totReturns = 0;
        t.totReturnTax = 0;
        t.totReturnPreTax = 0;
        t.isChdOut = isCheckedOut;
        t.roomBalDue = 0;
        t.totRmBalDue = 0;
        t.extraPayment = 0;
    }
}

class PayCtrls {
    constructor() {
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
        t.cashTendered = $('#txtCashTendered');
        t.extraPay = $('#extraPay');
        t.invoiceCb = $('.hhk-payInvCb');
        t.adjustBtn = $('#paymentAdjust');
        t.msg = $('#payChooserMsg');
        t.heldAmtTb = $('#heldAmount');
        t.heldCb = $('#cbHeld');
        t.reimburseVatCb = $('#cbReimburseVAT');
        t.reimburseVatAmt = $('#reimburseVat');
        t.hsDiscAmt = $('#HsDiscAmount');
        t.houseWaiveCb = $('input#houseWaiveCb');
        t.overPay = $('#txtOverPayAmt');
        t.guestCredit = $('#guestCredit');
        t.selBalTo = $('#selexcpay');
        t.taxingItems = $('.hhk-TaxingItem.hhk-applyTax');
    }
}


function getPaymentData(p, a) {

    // Visit fees - vfee
    if (p.visitFeeCb.length > 0) {

        a.visitfeeCharge = parseFloat($('#spnvfeeAmt').data('amt'));

        if (isNaN(a.vfee) || a.vfee < 0 || p.visitFeeCb.prop("checked") === false) {
            a.vfee = 0;
            p.visitFeeAmt.val('');
        } else {
            a.vfee = a.visitfeeCharge;
            p.visitFeeAmt.val(a.visitfeeCharge.toFixed(2).toString());
        }
    }

    // Deposit Payments - kdep
    if (!a.isChdOut && p.keyDepCb.length > 0) {

        a.kdepCharge = parseFloat($('#hdnKeyDepAmt').val());

        let kdamtPaid = parseFloat($('#kdPaid').data('amt'));
        if (isNaN(kdamtPaid)) {
            kdamtPaid = 0;
        }

        if (isNaN(a.kdepCharge) || a.kdepCharge <= 0 || kdamtPaid > 0) {

            a.kdepCharge = 0;
            a.kdep = 0;
            p.keyDepAmt.val('');
            p.keyDepCb.prop('checked', false);
            // hide row
            $('.hhk-kdrow').hide();

        } else {

            if (p.keyDepCb.prop('checked') === false) {
                p.keyDepAmt.val('');
                a.kdep = 0;
            } else {
                p.keyDepAmt.val(a.kdepCharge.toFixed(2).toString());
                a.kdep = a.kdepCharge;
            }

            // unhide row
            $('.hhk-kdrow').show();
        }
    }

    // Unpaid Invoices - invAmt
    if (p.invoiceCb.length > 0) {

        p.invoiceCb.each(function () {

            let invnum = parseInt($(this).data('invnum'));
            let amtCtrl = $('#' + invnum + 'invPayAmt');
            let maxamt = parseFloat($(this).data('invamt'));
            let amt;

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

                a.invAmt += amt;
                a.invCharge += maxamt;


            } else {
                if (amtCtrl.val() !== '') {
                    amtCtrl.val('');
                    amtCtrl.prop('disabled', true);
                }
            }
        });
    }

    // Deposit refund? depRfAmt
    if (p.depRefundAmt.length > 0 && a.isChdOut) {

        a.depRfAmt = parseFloat(p.depRefundAmt.data('amt'));

        if (isNaN(a.depRfAmt) || a.depRfAmt < 0 || p.depRefundCb.prop('checked') === false) {

            a.depRfPay = 0;
            p.depRefundAmt.val('');

        } else {
            a.depRfPay = a.depRfAmt;
            p.depRefundAmt.val((0 - a.depRfAmt).toFixed(2).toString());
        }
    }

    // Test held amount if any. - heldAmt
    if (p.heldCb.length > 0) {

        let ppFlag = parseInt(p.heldCb.data('prepay'));
        a.heldAmt = parseFloat(p.heldCb.data('amt'));
        a.chkingIn = parseInt(p.heldCb.data('chkingin'));

        if (isNaN(a.chkingIn)) {
            a.chkingIn = 0;
        }

        if (isNaN(a.heldAmt) || a.heldAmt < 0 || p.heldCb.prop("checked") === false) {
            a.heldAmt = 0;
        }

        // Reservation checking in logic.
        if (a.chkingIn == 1) {

            if (p.heldCb.prop("checked") === true && ppFlag == 1) {
                a.prePayRoomAmt = a.heldAmt;
            } else if (p.heldCb.prop("checked") === false) {
                a.prePayRoomAmt = 0;
            }
        } else {
            a.prePayRoomAmt = 0;
        }

        // if (p.heldCb.prop("checked") === true && a.chkingIn == 1 && ppFlag == 1) {
        //     a.prePayRoomAmt = a.heldAmt;
        // } else if (p.heldCb.prop("checked") === false && a.chkingIn == 1) {
        //     a.prePayRoomAmt = 0;
        // }
    }

    // Reimburse value added taxes
    if (p.reimburseVatCb.length > 0) {

        a.reimburseAmt = parseFloat(p.reimburseVatCb.data('amt'));

        if (isNaN(a.reimburseAmt) || a.reimburseAmt < 0 || p.reimburseVatCb.prop('checked') === false) {
            a.reimburseAmt = 0;
        }
    }

}

function getPaymentAmount(p, a) {

    if (p.feePayAmt.length > 0) {

        // if (a.isChdOut && p.RoomChargesPayCb.prop('checked') === true && p.feePayAmt.val() == '') {
        //     p.feePayAmt.val(p.feesCharges.val());
        // } else if (a.isChdOut && p.RoomChargesPayCb.filter(':visible').length == 1 && p.RoomChargesPayCb.prop('checked') === false) {
        //     p.feePayAmt.val('');
        // }

        a.feePayText = p.feePayAmt.val().replace('$', '').replace(',', '');
        a.feePayPreTax = roundTo(parseFloat(a.feePayText), 2);

        if (isNaN(a.feePayPreTax) || a.feePayPreTax <= 0) {
            a.feePayPreTax = 0;
        }

        if (a.feePayText === '0.00') {
            a.feePayText = '0';
        }

        // allow zeros through.
        if (a.feePayText !== '0') {
            a.feePayText = '';
        }

        // Add reservation pre-pay only once.
        let remiandr = (a.prePayRoomAmt - a.vfee - a.kdep - (a.invAmt < 0 ? 0 : a.invAmt));

        // Fill up room fees to pre-payment amount, minus other charges   && a.feePayPreTax < remiandr
        if (remiandr > 0) {
            a.feePayPreTax = remiandr;
        } else if (a.prePayRoomAmt > 0) {
            a.feePayPreTax = 0;
        }

        let updateTaxes = new UpdateTaxes(p.taxingItems);
        a.feePayTaxAmt = updateTaxes.calcTax(a.feePayPreTax);

        // Only tax up to the room balance due.
        if (a.feePayTaxAmt > (a.roomBalTaxDue - a.totReturnTax) && a.isChdOut) {
            a.feePayTaxAmt = (a.roomBalTaxDue - a.totReturnTax < 0 ? 0 : a.roomBalTaxDue - a.totReturnTax);
        }

        a.feePay = a.feePayPreTax + a.feePayTaxAmt;
    }

    // Extra payments.
    if (p.extraPay.length > 0) {

        let extraPayText = p.extraPay.val().replace('$', '').replace(',', '');
        a.extraPayment = roundTo(parseFloat(extraPayText), 2);

        if (isNaN(a.extraPayment) || a.extraPayment <= 0) {
            a.extraPayment = 0;
        }

        if (a.extraPayment.toFixed(2) > 0) {
            p.extraPay.val(a.extraPayment.toFixed(2).toString());
        } else {
            p.extraPay.val('');
        }
    }
}

function doOverpayment(p, a) {

    let originalFeePayAmt = a.feePay,
        minAmtDue = 0;

    const updateFeeTaxes = new UpdateTaxes(p.taxingItems);

    // discern payment disposition.
    if (p.selBalTo.val() === 'r' && a.overPayAmt > 0) {
        // Refund

        if (a.totRmBalDue > 0) {
            minAmtDue = a.totRmBalDue;
        }

        let deltaFeePay = a.feePayPreTax - minAmtDue;

        if (deltaFeePay > a.overPayAmt) {

            a.feePayPreTax -= deltaFeePay;
            a.overPayAmt -= deltaFeePay;

            // Not a fefund
            $('#txtRtnAmount').val('');
            $('#divReturnPay').hide();
            p.selBalTo.val('');

        } else if (a.overPayAmt >= deltaFeePay) {

            // Only if actually paying more than we owe.
            if (deltaFeePay >= 0) {
                a.overPayAmt -= deltaFeePay;
                // subtract any extra amount.
                if (a.extraPayment > 0) {
                    a.overPayAmt -= a.extraPayment;
                    a.totPay -= a.extraPayment;
                    p.extraPay.val('');
                    alert('Extra Payment amount is reduced to 0');
                }
                a.feePayPreTax = minAmtDue;
            }

            if (a.overPayAmt > 0) {
                $('#divReturnPay').show('fade');
                $('#txtRtnAmount').val(a.overPayAmt.toFixed(2).toString());

            } else {
                // a.overPayAmt is 0
                $('#txtRtnAmount').val('');
                $('#divReturnPay').hide();
                p.selBalTo.val('');
            }
        }

        a.feePay = a.feePayPreTax + updateFeeTaxes.calcTax(a.feePayPreTax);
        a.totPay = (a.totPay - originalFeePayAmt) + a.feePay;

        if (originalFeePayAmt != a.feePay) {
            alert('Pay Room Fees amount is reduced to: $' + a.feePay.toFixed(2).toString());
        }

    } else {
        // Not asking for a refund
        $('#txtRtnAmount').val('');
        $('#divReturnPay').hide();
    }

    // Manage page formats for overpayment
    if (a.overPayAmt.toFixed(2) <= 0) {
        // no overpayments, disappear
        p.overPay.val('');
        $('.hhk-Overpayment').hide();
        p.selBalTo.val('');
    } else {
        p.overPay.val(a.overPayAmt.toFixed(2).toString());
        $('.hhk-Overpayment').show('fade');
    }

    return;
}

function doHouseWaive(p, a,) {

    if (a.overPayAmt < 0 ||(a.overPayAmt == 0 && a.totCharges > 0)) {
        // Show house wave entities.

        $('.totalPaymentTr').show('fade');
        $('.hhk-HouseDiscount').show();

        if (p.houseWaiveCb.prop('checked')) {
            // Manage House Waive of underpaid amount

            let houseWaive = Math.max((a.roomBalDue - a.feePayPreTax), 0) + a.invAmt + a.vfee - a.totReturns;
            let underPayAmt = 0 - a.overPayAmt;

            if (houseWaive > 0) {
                p.hsDiscAmt.val(houseWaive.toFixed(2).toString());
                a.totPay = (Math.max(a.totCharges - houseWaive, 0) - a.totReturns) - a.roomBalTaxDue;
            } else if (a.overPayAmt < 0) {
                p.hsDiscAmt.val(underPayAmt.toFixed(2).toString());
                a.totPay -= underPayAmt;
            } else {
                // No House Waive
                p.hsDiscAmt.val('');
                $('.hhk-HouseDiscount').hide();
                p.houseWaiveCb.prop('checked', false);
            }

        } else {
            // No House Waive
            p.hsDiscAmt.val('');
        }
    } else {
        // hide and clear house waive entities
        $('.hhk-HouseDiscount').hide();
        $('.totalPaymentTr').hide();
        p.hsDiscAmt.val('');
        p.houseWaiveCb.prop('checked', false);
    }
}


function amtPaid() {
    "use strict";

    var p = new PayCtrls(),
        a = new AmountVariables(isCheckedOut);

    // Hide error messages
    p.msg.text('').hide();

    // Room balance due
    a.roomBalDue = parseFloat($('#spnCfBalDue').data('rmbal'));

    if (isNaN(a.roomBalDue)) {
        a.roomBalDue = 0;
    } else {

        let taxedRoomBalDue = parseFloat($('#spnCfBalDue').data('taxedrmbal'));

        p.taxingItems.each(function () {
            let rate = parseFloat($(this).data('taxrate'))
            if (a.roomBalDue < 0) { // if room bal is credit
                a.roomBalTaxDue += roundTo(taxedRoomBalDue * rate, 2, 'ceil');
            } else {
                a.roomBalTaxDue += roundTo(a.roomBalDue * rate, 2);
            }
        });
    }

    // sucks the payment data from the paying fields and fills in the amount variables
    getPaymentData(p, a);

    // Fees Payments: feePay
    getPaymentAmount(p, a);

    // Credits
    a.totReturns = + a.reimburseAmt + a.heldAmt + a.depRfPay;

    // Payments
    a.totPay = a.invAmt + a.vfee + a.kdep + a.feePay;


    if (a.isChdOut) {
        // Checked out
        $('.hhk-minPayment').show('fade');
        p.hsDiscAmt.val('');
        a.overPayAmt = 0;

        a.totRmBalDue = roundTo(a.roomBalDue + a.roomBalTaxDue, 2);

        // Change to invAmt instead of invCharge.
        a.totCharges = a.invAmt + a.visitfeeCharge + a.kdepCharge;

        // Show correct row for charges due
        if (a.totRmBalDue > 0) {
            // Room fees are still owed.
            p.feesCharges.val(a.roomBalDue.toFixed(2).toString());
            $('.hhk-GuestCredit').hide();
            $('.hhk-RoomFees').show('fade');
            $('.hhk-RoomCharge').show('fade');
            $('#daystoPay').hide();

            a.totCharges += a.totRmBalDue;

        } else {
            // room fees are overpaid.
            p.guestCredit.val(a.totRmBalDue.toFixed(2).toString());
            $('.hhk-RoomFees').hide();
            $('.hhk-extraPayTr').show('fade');
            $('.hhk-GuestCredit').show('fade');

            a.totReturns -= a.totRmBalDue;  // add to total returns
            a.totReturns += a.extraPayment;
            a.totRmBalDue = 0;

        }

        // use up any returns by reducing the total payment
        if (a.totReturns >= a.totPay) {
            a.totReturns -= a.totPay;  // reduce total payment by any fee payments.
            a.overPayAmt = a.totReturns
            a.totPay = a.extraPayment;
        } else {
            // totPay is > totReturns
            a.overPayAmt = a.totPay - a.totCharges;
            a.totPay -= a.totReturns;
        }


        doOverpayment(p, a);

        doHouseWaive(p, a);



    // else still checked in
    } else {
        // Reset to check-in configuration
        $('#daystoPay').show('fade');
        $('.hhk-RoomFees').show('fade');
        $('.hhk-Overpayment').hide();
        $('.totalPaymentTr').show('fade');
        $('.hhk-HouseDiscount').hide();
        $('.hhk-RoomCharge').hide();
        $('.hhk-GuestCredit').hide();
        $('#divReturnPay').hide();
        $('#txtRtnAmount').val('');

        p.hsDiscAmt.val('');
        a.totPay -= a.totReturns;
    }


    // Show payment today box?
    if (a.totPay > 0) {
        // Bring up the payment selector
        $('.paySelectTbl').show('fade');
        $('.hhk-minPayment').show();
    } else {
        a.totPay = 0;
        $('.paySelectTbl').hide();

        if (! a.isChdOut) {
            $('.hhk-minPayment').hide();
        }

    }

    if (a.feePay === 0) {
        p.feePayAmt.val(a.feePayText);
    } else {
        p.feePayAmt.val(a.feePayPreTax.toFixed(2).toString());

    }


    if (a.heldAmt.toFixed(2) > 0) {
        p.heldAmtTb.val((0 - a.heldAmt).toFixed(2).toString());
    } else {
        p.heldAmtTb.val('');
    }

    if (a.reimburseAmt > 0) {
        p.reimburseVatAmt.val((0 - a.reimburseAmt).toFixed(2).toString());
    } else {
        p.reimburseVatAmt.val('');
    }

    p.totalPayment.val(a.totPay.toFixed(2).toString());
    $('#spnPayAmount').text('$' + a.totPay.toFixed(2).toString());

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
function setupPayments(rate, idVisit, visitSpan, $diagBox, strInvoiceBox) {
    "use strict";
    var ptsel = $('#PayTypeSel');
    var chg = $('.tblCredit');
    var $chrgExpand = $('.tblCreditExpand');//$('#trvdCHName');
    var p = new PayCtrls();

    if (chg.length === 0) {
        chg = $('.hhk-mcred');
    }

    if (ptsel.length > 0) {
        ptsel.on('change', function () {
            $('.hhk-cashTndrd').hide();
            $('.hhk-cknum').hide();
            $('#tblInvoice').hide();
            getInvoicee('', idVisit, '');
            $('.hhk-transfer').hide();
            $('.hhk-tfnum').hide();
            chg.hide();
            $chrgExpand.hide();
            $('#tdCashMsg').hide();
            $('.paySelectNotes').show();

            if ($(this).val() === 'cc') {
                chg.show('fade');
                if ($(document).find('input[name=rbUseCard]:checked').val() == 0) {
                    $chrgExpand.show('fade');
                } else {
                    $chrgExpand.hide('fade');
                }
                $(document).find('input[name=rbUseCard]:checked').trigger('change');

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

    }

    // Card on file Cardholder name.
    setupCOF($chrgExpand);


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
            $('.hhk-cknumr').hide();

            if ($(this).val() === 'cc') {
                rtnchg.show('fade');
            } else if ($(this).val() === 'ck') {
                $('.hhk-cknumr').show('fade');
            } else if ($(this).val() === 'tf') {
                $('.hhk-transferr').show('fade');
            }
        });
        rtnsel.change();
    }


    if (p.selBalTo.length > 0) {
        p.selBalTo.change(function () {
            amtPaid();
        });
    }

    if (p.houseWaiveCb.length > 0) {
        p.houseWaiveCb.change(function () {
            amtPaid();
        });
    }

    if (p.keyDepCb.length > 0) {
        p.keyDepCb.change(function () {
            amtPaid();
        });
    }

    if (p.depRefundCb.length > 0) {
        p.depRefundCb.change(function () {
            amtPaid();
        });
    }

    if (p.heldCb.length > 0) {
        p.heldCb.change(function () {
            amtPaid();
        });
    }

    if (p.reimburseVatCb.length > 0) {
        p.reimburseVatCb.change(function () {
            amtPaid();
        });
    }

    if (p.invoiceCb.length > 0) {

        p.invoiceCb.change(function () {
            amtPaid();
        });

        $('.hhk-payInvAmt').change(function () {
            amtPaid();
        });
    }

    if (p.visitFeeCb.length > 0) {
        p.visitFeeCb.change(function () {
            amtPaid();
        });
    }

    if (p.feePayAmt.length > 0) {
        p.feePayAmt.change(function () {
            $(this).removeClass('ui-state-error');
            amtPaid();
        });
    }

    if (p.feesCharges.length > 0) {
        p.feesCharges.click(function () {
            p.feePayAmt.val($(this).val()).focus();
            p.feePayAmt.change();
        })
    }

    if (p.extraPay.length > 0) {
        p.extraPay.change(function () {
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

    // View/Delete invoice
    $('#divPmtMkup, #div-hhk-payments').on('click', '.invAction', function (event) {
        event.preventDefault();
        if ($(this).data('stat') == 'del') {
            if (!confirm('Delete Invoice ' + $(this).data('inb') + ($(this).data('payor') != '' ? ' for ' + $(this).data('payor') : '') + '?')) {
                return;
            }
        }

        invoiceAction($(this).data('iid'), $(this).data('stat'), event.target.id, strInvoiceBox, true);
    });


    // Billing agent chooser set up
    createInvChooser(idVisit, '');  //createInvChooser(idVisit, 'r');


    // Days - Payment calculator
    $('#daystoPay').change(function () {
        let days = parseInt($(this).val()),
                idVisit = parseInt($(this).data('vid')),
                fixed = parseFloat($('#txtFixedRate').val()),
                noGuests = parseInt($('#spnNumGuests').text()),
                feePayAmt = p.feePayAmt,
                tax = parseFloat($('#spnRcTax').data('tax')),
                adjust = parseFloat($('#seladjAmount').find(':selected').data('amount'));

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

            daysCalculator(days, rate, idVisit, fixed, adjust, noGuests, 0, function (amt) {
                feePayAmt.val(amt.toFixed(2).toString());
                feePayAmt.change();
            });
        }
    });

    if (ptsel.length > 0) {
        ptsel.change();
    } else {
        amtPaid();
    }
}

function createInvChooser(idVisit, index) {

    if ($('#txtInvSearch' + index).length > 0) {

        $('#txtInvSearch' + index).keypress(function (event) {
            // Handle CR character.
            var mm = $(this).val();
            if (event.keyCode == '13') {

                if (mm == '' || !isNumber(parseInt(mm, 10))) {

                    alert("Don't press the return key unless you enter an Id.");
                    event.preventDefault();

                } else {

                    $.getJSON("../house/roleSearch.php", {cmd: "filter", 'basis': 'ba', letters: mm},
                            function (data) {
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
                                getInvoicee(data, idVisit, index);

                            });

                }
            }
        });

        createAutoComplete($('#txtInvSearch' + index), 3, {cmd: "filter", 'basis': 'ba'}, function (item) {
            getInvoicee(item, idVisit, index);
        }, false);
    }
}

function daysCalculator(days, idRate, idVisit, fixedAmt, adjAmt, numGuests, idResv, rtnFunction) {

    if (days > 0) {
        var parms = {cmd: 'rtcalc', vid: idVisit, rid: idResv, nites: days, rcat: idRate, fxd: fixedAmt, adj: adjAmt, gsts: numGuests};
        // ask momma how much
        $.post('ws_ckin.php', parms,
                function (data) {
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
    let total = parseFloat($('#totalPayment').val().replace('$', '').replace(',', ''));

    $('#tdCashMsg').hide('fade');
    $('#tdInvceeMsg').text('').hide();
    $('#tdChargeMsg').text('').hide();

    if ($('#PayTypeSel').val() === 'ca') {

        let tendered = parseFloat($('#txtCashTendered').val().replace('$', '').replace(',', '')),
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

        let idPayor = parseInt($('#txtInvId').val(), 10);

        if ((isNaN(idPayor) || idPayor < 1) && total != 0) {
            $('#tdInvceeMsg').text('The Invoicee is missing. ').show('fade');
            return false;
        }

    } else if ($('#PayTypeSel').val() === 'cc') {

        if ($('#selccgw').length > 0 && $('#selccgw').val() === '') {
            $('#tdChargeMsg').text('Select a location.').show('fade');
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
    var opt = {
        mode: "popup",
        popClose: false,
        popHt: 500,
        popWd: 400,
        popX: 200,
        popY: 200,
        popTitle: title
    };

    if (width === undefined || !width) {
        if ($(markup).data('merchcopy') == '1') {
            width = 900;
        } else {
            width = 550;
        }
    }

    pRecpt.children().remove();
    pRecpt.append($(markup).addClass('ReceiptArea').css('max-width', (width + 'px')));

    btn.button();
    btn.click(function () {
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
            function (data) {
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

function paymentRedirect(data, $xferForm, initialParams) {
    "use strict";
    if (data) {
console.log("redirect called");
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

            // openiframe(data.inctx, 600, 400, "Add New Card On File");

        } else if (data.deluxehpf) {
            //var height = (data.useSwipe ? 200 : 400);
            //var width = (data.useSwipe ? 400 : 650);
            var height = 650;
            var width = 650;

            var title = (data.deluxehpf.cmd == "payment" ? "Enter Payment Details" : "Add New Card On File");
            var deluxeSmtBtnTxt = (data.deluxehpf.cmd == "payment" ? "Submit Payment" : "Save Card On File");

            if (initialParams && initialParams.idVisit != undefined && initialParams.span != undefined) {
                title += " for Visit " + initialParams.idVisit + "-" + initialParams.span;
            }else if (initialParams && initialParams.resvId != undefined) {
                title += " for Reservation " + initialParams.resvId;
            }


            var $deluxeDialog = $("#deluxeDialog");

            if (data.deluxehpf.cmd == "payment" && data.deluxehpf.payAmount > 0) {
                $deluxeDialog.html('<div class="row justify-content-center mt-2" id="deluxePayInfo"><div class="col" style="text-align: center;"><label class="mx-1">Payment Amount</label><input class="mx-1" size="10" disabled="" value="$ ' + data.deluxehpf.payAmount.toFixed(2) + '" id="deluxePayAmount" type="text"></div></div>');
            }

            $deluxeDialog.attr("style", "overflow-y: hidden;").dialog({
                modal: true,
                width: getDialogWidth(650),
                height: 450,
                autoOpen: true,
                title: title,
                open: function (event, ui) {
                    var options = {
                        containerId: "deluxeDialog",
                        xtoken: data.deluxehpf.hpfToken,
                        xrtype: "Generate Token",
                        xbtntext: deluxeSmtBtnTxt,
                        xmsrattached: data.deluxehpf.useSwipe,
                        xswptext: "Please Swipe Card now...",
                        xPM:1,

                    };

                    HostedForm.init(options, {
                        onSuccess: (hpfData) => {
                            let submitData = {
                                token: hpfData.data.token,
                                nameOnCard: hpfData.data.nameOnCard,
                                expDate: hpfData.data.expDate,
                                cardType: hpfData.data.cardType,
                                maskedPan: hpfData.data.maskedPan,
                                cmd: data.deluxehpf.cmd,
                                pbp: data.deluxehpf.pbp
                            }

                            if (data.deluxehpf.idGroup) {
                                submitData.psg = data.deluxehpf.idGroup;
                            }
                            if (data.deluxehpf.idPayor) {
                                submitData.id = data.deluxehpf.idPayor;
                            }
                            if(data.deluxehpf.invoiceNum) {
                                submitData.invoiceNum = data.deluxehpf.invoiceNum;
                            }

                            $deluxeDialog.empty().append('<div id="hhk-loading-spinner" style="width: 100%; height: 100%; margin-top: 100px; text-align: center"><img src="../images/ui-anim_basic_16x16.gif"><p>Working...</p></div>');

                            $.post(encodeURI(data.deluxehpf.pbp),
                                submitData,
                                function (data) {
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
                    
                                        if (data.COFmkup) {
                                            $('#tblupCredit' + data.idx).remove();
                                            $('#upCreditfs').prepend($(data.COFmkup));
                                            setupCOF($('.tblCreditExpand' + data.idx), data.idx);
                                        }
                                        
                                        if (initialParams !== undefined && initialParams.resvId !== undefined) {
                                            $("#btnDone").click();
                                        }
                                        
                                        if (data.gotopage) {
                                            window.location.assign(data.gotopage);
                                        }

                                        if (data.success && data.success !== '') {
                                            flagAlertMessage(data.success, 'success');
                                        }
                            
                                        if (data.warning && data.warning !== '') {
                                            flagAlertMessage(data.warning, 'error');
                                        }
                            
                                        if (data.receipt && data.receipt !== '') {
                                            showReceipt('#pmtRcpt', data.receipt, 'Payment Receipt');
                                        }

                                        $deluxeDialog.dialog("close");
                                    }
                                });
                        
                        },
                        onFailure: (data) => { console.log(JSON.stringify(data)); },
                        onInvalid: (data) => { console.log(JSON.stringify(data)); }
                    }).then((instance) => { instance.renderHpf(); });
                },
                close: function (event, ui) {
                    //HostedForm.destroy();
                    $(this).find("iframe").remove();
                    $(this).dialog('destroy').empty();
                }

            });
        }
    }
}

/**
 *
 * @param {jqobject} $chgExpand
 * @param {string} $idx
 * @returns {undefined}
 */
function setupCOF($chgExpand, idx) {

    if (idx === undefined || idx === null) {
        idx = '';
    }

    // Card on file Cardholder name.
    if ($chgExpand.length > 0) {

        $(document).find('input[name=rbUseCard' + idx + ']').on('change', function () {
            if ($(this).val() == 0 || ($(this).prop('checked') === true && $(this).prop('type') === 'checkbox')) {
                $chgExpand.show();
            } else {
                $chgExpand.hide();
                $('#btnvrKeyNumber' + idx).prop('checked', false).change();
                $('#txtvdNewCardName' + idx).val('');
            }

            $('#tdChargeMsg' + idx).text('').hide();
            $('#selccgw' + idx).removeClass('ui-state-highlight');
        });

        if ($(document).find('input[name=rbUseCard' + idx + ']:checked').val() > 0 || ($(document).find('input[name=rbUseCard' + idx + ']').prop('checked') === false && $(document).find('input[name=rbUseCard' + idx + ']').prop('type') === 'checkbox')) {
            $chgExpand.hide();
        }

        $(document).find('input[name=rbUseCard' + idx + ']').trigger('change');

        // Instamed-specific controls
        if ($('#btnvrKeyNumber' + idx).length > 0) {
            $('#btnvrKeyNumber' + idx).change(function () {

                if ($('input[name=rbUseCard' + idx + ']:checked').val() == 0 || ($('input[name=rbUseCard' + idx + ']').prop('checked') === true && $('input[name=rbUseCard' + idx + ']').prop('type') === 'checkbox')) {
                    $('#txtvdNewCardName' + idx).show();
                } else {
                    $('#txtvdNewCardName' + idx).hide();
                    $('#txtvdNewCardName' + idx).val('');
                }
            });

            $('#btnvrKeyNumber' + idx).change();
        }

        if ($('#txtvdNewCardName' + idx).length > 0) {

            $('#txtvdNewCardName' + idx).keydown(function (e) {

                var key = e.which || e.keycode;

                if (key >= 48 && key <= 57 || key >= 96 && key <= 105) {  // both number keys and numpad number keys.
                    $('#lhnameerror').show();
                    return false;
                }

                $('#lhnameerror').hide();
                return true;
            });
        }
    }

}

function cardOnFile(id, idGroup, postBackPage, idx) {

    $('#tdChargeMsg' + idx).text('').hide();

    // Selected Merchant?
    if ($('#selccgw' + idx).length > 0 && ($('input[name=rbUseCard' + idx + ']:checked').val() == 0 || $('input[name=rbUseCard' + idx + ']').prop('checked') === true)) {

        $('#selccgw' + idx).removeClass('ui-state-highlight');

        if ($('#selccgw' + idx).val().length === 0) {
            $('#tdChargeMsg' + idx).text('Select a location.').show('fade');
            $('#selccgw' + idx).addClass('ui-state-highlight');
            return false;
        }
    }

    // Set up ajax call
    var parms = {cmd: 'cof', idGuest: id, idGrp: idGroup, pbp: postBackPage, index: idx};

    $('#tblupCredit' + idx).find('input').each(function () {

        if ($(this).attr('type') === 'checkbox') {
            if (this.checked !== false) {
                parms[$(this).attr('id')] = 'on';
            }
        } else if ($(this).attr('type') === 'radio') {
            if (this.checked !== false) {
                parms[$(this).attr('id')] = this.value;
            }
        } else {
            parms[$(this).attr('id')] = this.value;
        }
    });

    if ($('#selccgw' + idx).length > 0) {
        parms['selccgw' + idx] = $('#selccgw' + idx).val();
    }

    // For local gateway
    if ($('#selChargeType' + idx).length > 0) {
        parms['selChargeType' + idx] = $('#selChargeType' + idx).val();
    }

    // Go to the server for payment data, then come back and submit to new URL to enter credit info.
    $.post('ws_ckin.php', parms,
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
                    if (data.hostedError) {
                        flagAlertMessage(data.hostedError, 'error');
                    }

                    paymentRedirect(data, $('#xform'));

                    if ((data.success && data.success != '') || (data.COFmsg && data.COFmsg != '')) {
                        flagAlertMessage((data.success === undefined ? '' : data.success) + (data.COFmsg === undefined ? '' : data.COFmsg), 'success');
                    }

                    if (data.COFmkup && data.COFmkup !== '') {
                        $('#tblupCredit' + idx).remove();
                        $('#upCreditfs').prepend($(data.COFmkup));
                        setupCOF($('.tblCreditExpand' + idx), idx);
                    }
                }
            });
}

function paymentsTable(tableID, containerID, refreshPayments) {

    var ptbl = $('#' + tableID).DataTable({
        'columnDefs': [
            {
                'targets': 8,
                'type': 'date',
                'render': function (data, type, row) {
                    return dateRender(data, type);
                }
            },
            {
                'targets': 9,
                'type': 'date',
                'render': function (data, type, row) {
                    return dateRender(data, type);
                }
            }
        ],
        'dom': '<"top"if><"hhk-overflow-x hhk-tbl-wrap"rt><"bottom"lp><"clear">',
        'displayLength': 50,
        'order': [[8, 'asc']],
        'lengthMenu': [[25, 50, -1], [25, 50, "All"]],
        drawCallback: function () {
            $('#' + containerID).find('input[type=button]').button();
        }
    });

    $('#btnPayHistRef').button().click(function () {
        refreshPayments();
    });


    // Invoice viewer
    $('#' + containerID).on('click', '.invAction', function (event) {
        invoiceAction($(this).data('iid'), 'view', event.target.id);
    });

	// Void/Reverse Payment button
	$('#' + containerID).on('click', '.hhk-voidPmt', function() {
		var btn = $(this);
		var amt = parseFloat(btn.data("amt"));
		if (btn.val() !== "Saving..." && confirm("Void/Reverse this payment for $" + amt.toFixed(2).toString() + "?")) {
			btn.val('Saving...');
			sendVoidReturn(btn.attr('id'), 'rv', btn.data('pid'), null, refreshPayments);
		}
	});

    // Void-return button
    $('#' + containerID).on('click', '.hhk-voidRefundPmt', function () {
        var btn = $(this);
        if (btn.val() !== 'Saving...' && confirm('Void this Return?')) {
            btn.val('Saving...');
            sendVoidReturn(btn.attr('id'), 'vr', btn.data('pid'), null, refreshPayments);
        }
    });

    // Return button
    $('#' + containerID).on("click", ".hhk-returnPmt", function () {
        var btn = $(this);
        var amt = parseFloat(btn.data("amt"));
        if (btn.val() !== "Saving..." && confirm("Return this payment for $" + amt.toFixed(2).toString() + "?")) {
            btn.val("Saving...");
            sendVoidReturn(btn.attr("id"), "r", btn.data("pid"), amt, refreshPayments);
        }
    });

    // Undo Return
    $('#' + containerID).on("click", ".hhk-undoReturnPmt", function () {
        var btn = $(this);
        var amt = parseFloat(btn.data("amt"));
        if (btn.val() !== "Saving..." && confirm("Undo this Return/Refund for $" + amt.toFixed(2).toString() + "?")) {
            btn.val("Saving...");
            sendVoidReturn(btn.attr("id"), "ur", btn.data("pid"), null, refreshPayments);
        }
    });

    // Delete waive button
    $('#' + containerID).on('click', '.hhk-deleteWaive', function () {
        var btn = $(this);

        if (btn.val() !== 'Deleting...' && confirm('Delete this House payment?')) {
            btn.val('Deleting...');
            sendVoidReturn(btn.attr('id'), 'd', btn.data('ilid'), btn.data('iid'), null, refreshPayments);
        }
    });

    $('#' + containerID).on('click', '.pmtRecpt', function () {
        reprintReceipt($(this).data('pid'), '#pmtRcpt');
    });

    $('#' + containerID).mousedown(function (event) {
        var target = $(event.target);
        if (target[0].id !== 'pudiv' && target.parents("#" + 'pudiv').length === 0) {
            $('div#pudiv').remove();
        }
    });

}