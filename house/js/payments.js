/**
 * payments.js
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
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

					$("#cbAdjustType").buttonset();

					$('#cbAdjustPmt1, #cbAdjustPmt2').change(function() {

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

					$('#selAddnlChg, #selHouseDisc').change(function() {
						var amts = parseFloat(gblAdjustData[$(this).data('amts')][$(this).val()]);
						$('#housePayment').val(amts.toFixed(2));
						$('#housePayment').change();
					});

					$('#housePayment').change(function() {
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
 * @param {string} btnid
 * @param {string} vorr
 * @param {int} idPayment
 * @param {float} amt
 * @param (function) refresh
 * @returns {undefined}
 */
function sendVoidReturn(btnid, vorr, idPayment, amt, refresh) {

	var prms = { pid: idPayment, bid: btnid };

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

			this.taxingItems.each(function() {
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
		t.vfee = 0;
		t.feePay = 0;
		t.feePayPreTax = 0;
		t.feePayText = '';
		t.prePayRoomAmt = 0;
		t.invAmt = 0;
		t.heldAmt = 0;
		t.chkingIn = 0;
		t.reimburseAmt = 0;
		t.totCharges = 0;
		t.ckedInCharges = 0;
		t.totPay = 0;
		t.depRfAmt = 0;
		t.roomBalTaxDue = 0;
		t.feePayTaxAmt = 0;
		t.overPayAmt = 0;
		t.totReturns = 0;
		t.totReturnTax = 0;
		t.totReturnPreTax = 0;
		t.isChdOut = isCheckedOut;
		t.roomBalDue = parseFloat($('#spnCfBalDue').data('rmbal'));
		t.taxedRoomBalDue = parseFloat($('#spnCfBalDue').data('taxedrmbal'));
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
		t.taxingItems = $('.hhk-TaxingItem.hhk-applyTax');
	}
}

class RefundValidater {
	constructor(amtVars, $selBalTo, $taxingItems, updateFeeTaxes) {
		this.a = amtVars;
		this.$selBalTo = $selBalTo;
		this.$taxingItems = $taxingItems;
		this.updateFeeTaxes = updateFeeTaxes;
	}

	validate() {

		if (this.a.totPay >= 0) {

			if (this.a.feePayPreTax >= this.a.overPayAmt) {
				alert('Pay Room Fees amount is reduced to: $' + (this.a.feePayPreTax - this.a.overPayAmt).toFixed(2).toString());

				this.a.feePayPreTax = this.a.feePayPreTax - this.a.overPayAmt;
				this.a.feePayTaxAmt = this.updateFeeTaxes.calcTax(this.a.feePayPreTax);
			}


			this.a.feePay = this.a.feePayPreTax + this.a.feePayTaxAmt;
			this.a.overPayAmt = 0;

			this.$selBalTo.val('');
			$('#txtRtnAmount').val('');
			$('#divReturnPay').hide();
			$('.hhk-Overpayment').hide();

		} else {

			if (this.a.feePay > 0) {
				alert('Pay Room Fees amount is reduced to: $0.00');
			}

			this.a.overPayAmt -= this.a.feePay;

			this.a.feePayPreTax = 0;
			this.a.feePayTaxAmt = 0;
			this.$taxingItems.each(function() {
				$(this).val('');
			});
			this.a.feePay = 0;

			$('#divReturnPay').show();
			$('#txtRtnAmount').val(this.a.overPayAmt.toFixed(2).toString());
		}
	}

}


function getPaymentData(p, a) {
		
	// Visit fees - vfee
	if (p.visitFeeCb.length > 0) {

		a.vfee = parseFloat($('#spnvfeeAmt').data('amt'));

		if (isNaN(a.vfee) || a.vfee < 0 || p.visitFeeCb.prop("checked") === false) {
			a.vfee = 0;
			p.visitFeeAmt.val('');
		} else {
			p.visitFeeAmt.val(a.vfee.toFixed(2).toString());
		}
	}

	// Deposits - kdep
	if (!a.isChdOut && p.keyDepCb.length > 0) {

		a.kdep = parseFloat($('#hdnKeyDepAmt').val());

		if (isNaN(a.kdep) || a.kdep <= 0) {

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
				a.kdep = parseFloat($('#hdnKeyDepAmt').val());
				p.keyDepAmt.val(a.kdep.toFixed(2).toString());
			}

			// unhide row
			$('.hhk-kdrow').show();
		}
	}

	// Unpaid Invoices - invAmt
	if (p.invoiceCb.length > 0) {

		p.invoiceCb.each(function() {

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

			a.depRfAmt = 0;
			p.depRefundAmt.val('');

		} else {
			p.depRefundAmt.val((0 - a.depRfAmt).toFixed(2).toString());
		}
	}

	// Test held amount if any. - heldAmt
	if (p.heldCb.length > 0) {

		let ppFlag = parseInt(p.heldCb.data('prepay'));
		a.heldAmt = parseFloat(p.heldCb.data('amt'));
		a.chkingIn = parseInt(p.heldCb.data('chkingin'));
		
		if (isNaN(a.chkingIn)){
			a.chkingIn = 0;
		}

		if (isNaN(a.heldAmt) || a.heldAmt < 0 || p.heldCb.prop("checked") === false) {
			a.heldAmt = 0;
		}
		
		// Reservation checking in logic.
		if (p.heldCb.prop("checked") === true && a.chkingIn == 1 && ppFlag == 1) {
			a.prePayRoomAmt = a.heldAmt;
		} else if (p.heldCb.prop("checked") === false && a.chkingIn == 1) {
			a.prePayRoomAmt = 0;
		}
	}

	// Reimburse value added taxes
	if (p.reimburseVatCb.length > 0) {

		a.reimburseAmt = parseFloat(p.reimburseVatCb.data('amt'));

		if (isNaN(a.reimburseAmt) || a.reimburseAmt < 0 || p.reimburseVatCb.prop('checked') === false) {
			a.reimburseAmt = 0;
		}
	}

}



function roundTo(n, digits) {

	if (digits === undefined) {
		digits = 0;
	}

	let multiplicator = Math.pow(10, digits);
	n = parseFloat((n * multiplicator).toFixed(11));
	return Math.round(n) / multiplicator;
}



function amtPaid() {
	"use strict";

	var p = new PayCtrls(),
		a = new AmountVariables(isCheckedOut),
		updateFeeTaxes = new UpdateTaxes(p.taxingItems),
		refundValidater = new RefundValidater(a, p.selBalTo, p.taxingItems, updateFeeTaxes);

	// Hide error messages
	p.msg.text('').hide();

	// Room balance due
	if (isNaN(a.roomBalDue)) {
		a.roomBalDue = 0;
	} else {

		p.taxingItems.each(function() {
			let rate = parseFloat($(this).data('taxrate'));
			if (a.roomBalDue < 0) { // if room bal is credit
				a.roomBalTaxDue += roundTo(a.taxedRoomBalDue * rate, 2, 'ceil');
			} else {
				a.roomBalTaxDue += roundTo(a.roomBalDue * rate, 2);
			}
		});
	}

	// sucks the payment data from the paying fields and fills in the amount variables
	getPaymentData(p, a);

	a.totReturns = a.heldAmt + a.depRfAmt + a.reimburseAmt;

	p.taxingItems.each(function() {

		let rate = parseFloat($(this).data('taxrate'));
		a.totReturnTax += roundTo(a.totReturns / (1 + rate), 2);
	});

	if (a.totReturnTax > a.roomBalTaxDue) {
		a.totReturnTax = a.roomBalTaxDue;
	}

	a.totReturnPreTax = a.totReturns - a.totReturnTax;

	// Fees Payments - feePay
	if (p.feePayAmt.length > 0) {

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
		let remiandr = (a.prePayRoomAmt - a.vfee - a.kdep - a.invAmt);
		
		// Fill up room fees to pre-payment amount, minus other charges   && a.feePayPreTax < remiandr
		if (remiandr > 0) {
			a.feePayPreTax = remiandr;
		} else if (a.prePayRoomAmt > 0) {
			a.feePayPreTax = 0;
		}
		
		a.feePayTaxAmt = updateFeeTaxes.calcTax(a.feePayPreTax);

		// Only tax up to the room balance due.
		if (a.feePayTaxAmt > (a.roomBalTaxDue - a.totReturnTax) && a.isChdOut) {
			a.feePayTaxAmt = (a.roomBalTaxDue - a.totReturnTax < 0 ? 0 : a.roomBalTaxDue - a.totReturnTax);
		}

		a.feePay = a.feePayPreTax + a.feePayTaxAmt;
	}


	if (a.isChdOut) {
		// Checked out
		$('.hhk-minPayment').show('fade');
		p.hsDiscAmt.val('');

		let totRmBalDue = roundTo(a.roomBalDue + a.roomBalTaxDue, 2);

		// Show correct row for charges due
		if (totRmBalDue >= 0) {
			p.feesCharges.val(totRmBalDue.toFixed(2).toString());
			$('.hhk-GuestCredit').hide();
			$('.hhk-RoomCharge').show();
		} else {
			p.guestCredit.val(totRmBalDue.toFixed(2).toString());
			$('.hhk-RoomCharge').hide();
			$('.hhk-GuestCredit').show();
		}


		a.totCharges = a.vfee + a.invAmt + totRmBalDue - a.totReturns;

		a.totPay = a.totCharges + a.feePay;

		if (a.totCharges > 0) {
			
			//let hsPay = roundTo((a.vfee + a.invAmt + a.roomBalDue - a.totReturnPreTax - a.feePayPreTax), 2);
			let hsMax = roundTo((a.vfee + a.invAmt + a.roomBalDue - a.totReturnPreTax), 2);
			let hsPay = hsMax - a.feePay;
			
			if (hsMax > 0) {

				$('.hhk-HouseDiscount').show();
				
				if (p.finalPaymentCb.prop('checked') && hsPay > 0) {
					// Manage House Waive of underpaid amount

					let taxBal = roundTo((a.roomBalTaxDue - (a.feePayTaxAmt + a.totReturnTax)), 2);

					p.feesCharges.val((totRmBalDue - taxBal).toFixed(2).toString());
					
					p.hsDiscAmt.val(hsPay.toFixed(2).toString());
					
					a.totCharges = a.totCharges - taxBal;
					a.totPay = a.feePay;

					// Clear overpayment selector
					$('.hhk-Overpayment').hide();
					p.selBalTo.val('');
					$('#txtRtnAmount').val('');
					$('#divReturnPay').hide();

				} else {
					// No House Waive
					p.hsDiscAmt.val('');
					a.totPay = a.vfee + a.invAmt + a.feePay;
					
					if (hsPay <= 0) {
						$('.hhk-HouseDiscount').hide();
						p.finalPaymentCb.prop('checked', false);

					}
				}

			} else {
				// clear House waive selector
				p.finalPaymentCb.prop('checked', false);
				p.hsDiscAmt.val('');
				$('.hhk-HouseDiscount').hide();
			}

			// Do we need an overpayment line?
			if (a.totPay - a.totCharges > 0) {

				a.overPayAmt = a.totPay - a.totCharges;

				$('.hhk-Overpayment').show();

				if (p.selBalTo.val() === 'r') {
					refundValidater.validate();
					a.totPay = a.vfee + a.invAmt + a.feePay;
				} else {
					$('#txtRtnAmount').val('');
					$('#divReturnPay').hide();
				}
			} else {
				$('.hhk-Overpayment').hide();
			}

		} else {
			// totCharges <= 0
			p.finalPaymentCb.prop('checked', false);
			p.hsDiscAmt.val('');

			a.overPayAmt = Math.abs(a.totCharges) + a.feePay;

			if (p.selBalTo.val() === 'r') {

				refundValidater.validate();
				a.totPay = a.totCharges + a.feePay;

			} else {
				$('#txtRtnAmount').val('');
				$('#divReturnPay').hide();

				a.totPay = a.feePay;
			}

			if (a.overPayAmt.toFixed(2) > 0) {
				$('.hhk-Overpayment').show();
				$('.hhk-HouseDiscount').hide();
			} else {
				$('.hhk-Overpayment').hide();
				$('.hhk-HouseDiscount').hide();
			}
		}


		// else still checked in
	} else {

		// still checked in
		a.totCharges = a.vfee + a.kdep + a.invAmt + a.feePay;

		// Adjust charges by any held amount
		if (a.totCharges > 0 && a.heldAmt > 0) {

			// reduce total charges
			if (a.heldAmt > a.totCharges) {
				a.heldAmt = a.totCharges;
				a.totCharges = 0;
			} else {
				a.totCharges -= a.heldAmt;
			}

		} else if (a.totCharges < 0 && a.heldAmt > 0) {
			// Increase return
			a.totCharges -= a.heldAmt;

		} else if (p.heldCb.length > 0) {

			p.heldAmtTb.val('');
		}

		// Adjust charges by any reimbursed taxes
		if (a.totCharges > 0 && a.reimburseAmt > 0) {

			// reduce total charges
			if (a.reimburseAmt > a.totCharges) {
				a.reimburseAmt = 0;
			} else {
				a.totCharges -= a.reimburseAmt;
			}

		} else if (a.totCharges < 0 && a.reimburseAmt > 0) {
			// Increase return
			a.totCharges -= a.reimburseAmt;

		} else if (p.reimburseVatCb.length > 0) {

			a.reimburseAmt = 0;
		}


		$('.hhk-Overpayment').hide();
		$('.hhk-HouseDiscount').hide();
		p.hsDiscAmt.val('');
		a.overPayAmt = 0;
		a.totPay = a.totCharges;
		a.ckedInCharges = a.vfee + a.kdep + a.invAmt + a.feePay;

	}



	if (a.totPay > 0 || (a.totPay < 0 && !a.isChdOut)) {

		$('.paySelectTbl').show();
		$('.hhk-minPayment').show();

		if (a.totPay < 0 && !a.isChdOut) {
			$('#txtRtnAmount').val((0 - a.totPay).toFixed(2).toString());
		}

	} else {

		a.totPay = 0;

		$('.paySelectTbl').hide();

		if (a.overPayAmt <= 0) {
			$('#divReturnPay').hide();
		}

		if (a.isChdOut === false && a.ckedInCharges === 0.0) {
			$('.hhk-minPayment').hide();
			a.heldAmt = 0;
			a.reimburseAmt = 0;
		} else {
			$('.hhk-minPayment').show();
		}
	}

	if (a.feePay === 0) {
		p.feePayAmt.val(a.feePayText);
		//$('#feesTax').val('');
	} else {
		p.feePayAmt.val(a.feePayPreTax.toFixed(2).toString());
		//$('#feesTax').val(feePayTaxAmt.toFixed(2).toString());
	}

	if (a.overPayAmt.toFixed(2) <= 0) {
		p.overPay.val('');
	} else {
		p.overPay.val(a.overPayAmt.toFixed(2).toString());
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

	p.totalCharges.val(a.totCharges.toFixed(2).toString());
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
function setupPayments($rateSelector, idVisit, visitSpan, $diagBox, strInvoiceBox) {
	"use strict";
	var ptsel = $('#PayTypeSel');
	var chg = $('.tblCredit');
	var $chrgExpand = $('#trvdCHName');
	var p = new PayCtrls();

	if (chg.length === 0) {
		chg = $('.hhk-mcred');
	}

	if (ptsel.length > 0) {
		ptsel.change(function() {
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
				if ($('input[name=rbUseCard]:checked').val() == 0) {
					$chrgExpand.show();
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
		// folloeing moved to end of setup.
		//ptsel.change();

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
		rtnsel.change(function() {
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
		p.selBalTo.change(function() {
			amtPaid();
		});
	}

	if (p.finalPaymentCb.length > 0) {
		p.finalPaymentCb.change(function() {
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
		p.cashTendered.change(function() {
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
		p.adjustBtn.click(function() {
			getApplyDiscDiag(idVisit, $diagBox);
		});
	}

	// View/Delete invoice
	$('#divPmtMkup, #div-hhk-payments').on('click', '.invAction', function(event) {
		event.preventDefault();
		if ($(this).data('stat') == 'del') {
			if (!confirm('Delete this Invoice?')) {
				return;
			}
		}

		invoiceAction($(this).data('iid'), $(this).data('stat'), event.target.id, strInvoiceBox, true);
	});

	// Billing agent chooser set up
	createInvChooser(idVisit, '');
	//createInvChooser(idVisit, 'r');

	// Days - Payment calculator
	$('#daystoPay').change(function() {
		var days = parseInt($(this).val()),
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

			daysCalculator(days, $rateSelector.val(), idVisit, fixed, adjust, noGuests, 0, function(amt) {
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

		$('#txtInvSearch' + index).keypress(function(event) {
			// Handle CR character.
			var mm = $(this).val();
			if (event.keyCode == '13') {

				if (mm == '' || !isNumber(parseInt(mm, 10))) {

					alert("Don't press the return key unless you enter an Id.");
					event.preventDefault();

				} else {

					$.getJSON("../house/roleSearch.php", { cmd: "filter", 'basis': 'ba', letters: mm },
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
							getInvoicee(data, idVisit, index);

						});

				}
			}
		});

		createAutoComplete($('#txtInvSearch' + index), 3, { cmd: "filter", 'basis': 'ba' }, function(item) { getInvoicee(item, idVisit, index); }, false);
	}
}

function daysCalculator(days, idRate, idVisit, fixedAmt, adjAmt, numGuests, idResv, rtnFunction) {

	if (days > 0) {
		var parms = { cmd: 'rtcalc', vid: idVisit, rid: idResv, nites: days, rcat: idRate, fxd: fixedAmt, adj: adjAmt, gsts: numGuests };
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
	$('#tdChargeMsg').text('').hide();

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

function paymentRedirect(data, $xferForm) {
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

			// openiframe(data.inctx, 600, 400, "Add New Card On File");

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

		$('input[name=rbUseCard' + idx + ']').on('change', function() {
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

		if ($('input[name=rbUseCard' + idx + ']:checked').val() > 0 || ($('input[name=rbUseCard' + idx + ']').prop('checked') === false && $('input[name=rbUseCard' + idx + ']').prop('type') === 'checkbox')) {
			$chgExpand.hide();
		}

		// Instamed-specific controls
		if ($('#btnvrKeyNumber' + idx).length > 0) {
			$('#btnvrKeyNumber' + idx).change(function() {

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

			$('#txtvdNewCardName' + idx).keydown(function(e) {

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

		if ($('#selccgw' + idx + ' option:selected').length === 0) {
			$('#tdChargeMsg' + idx).text('Select a location.').show('fade');
			$('#selccgw' + idx).addClass('ui-state-highlight');
			return false;
		}
	}

	// Set up ajax call
	var parms = { cmd: 'cof', idGuest: id, idGrp: idGroup, pbp: postBackPage, index: idx };

	$('#tblupCredit' + idx).find('input').each(function() {

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

				paymentRedirect(data, $('#xform'));

				if ((data.success && data.success != '') || (data.COFmsg && data.COFmsg != '')) {
					flagAlertMessage((data.success === undefined ? '' : data.success) + (data.COFmsg === undefined ? '' : data.COFmsg), 'success');
				}

				if (data.COFmkup && data.COFmkup !== '') {
					$('#tblupCredit' + idx).remove();
					$('#upCreditfs').prepend($(data.COFmkup));
					setupCOF($('#trvdCHName' + idx), idx);
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
				'render': function(data, type, row) { return dateRender(data, type); }
			},
			{
				'targets': 9,
				'type': 'date',
				'render': function(data, type, row) { return dateRender(data, type); }
			}
		],
		'dom': '<"top"if><"hhk-overflow-x hhk-tbl-wrap"rt><"bottom"lp><"clear">',
		'displayLength': 50,
		'order': [[8, 'asc']],
		'lengthMenu': [[25, 50, -1], [25, 50, "All"]],
		drawCallback: function(){
			$('#' + containerID).find('input[type=button]').button();
		}
	});

	$('#btnPayHistRef').button().click(function() {
		refreshPayments();
	});


	// Invoice viewer
	$('#' + containerID).on('click', '.invAction', function(event) {
		invoiceAction($(this).data('iid'), 'view', event.target.id);
	});

	// Void/Reverse button
	$('#' + containerID).on('click', '.hhk-voidPmt', function() {
		var btn = $(this);
		var amt = parseFloat(btn.data("amt"));
		if (btn.val() !== "Saving..." && confirm("Void/Reverse this payment for $" + amt.toFixed(2).toString() + "?")) {
			btn.val('Saving...');
			sendVoidReturn(btn.attr('id'), 'rv', btn.data('pid'), null, refreshPayments);
		}
	});

	// Void-return button
	$('#' + containerID).on('click', '.hhk-voidRefundPmt', function() {
		var btn = $(this);
		if (btn.val() !== 'Saving...' && confirm('Void this Return?')) {
			btn.val('Saving...');
			sendVoidReturn(btn.attr('id'), 'vr', btn.data('pid'), null, refreshPayments);
		}
	});

	// Return button
	$('#' + containerID).on("click", ".hhk-returnPmt", function() {
		var btn = $(this);
		var amt = parseFloat(btn.data("amt"));
		if (btn.val() !== "Saving..." && confirm("Return this payment for $" + amt.toFixed(2).toString() + "?")) {
			btn.val("Saving...");
			sendVoidReturn(btn.attr("id"), "r", btn.data("pid"), amt, refreshPayments);
		}
	});

	// Undo Return
	$('#' + containerID).on("click", ".hhk-undoReturnPmt", function() {
		var btn = $(this);
		var amt = parseFloat(btn.data("amt"));
		if (btn.val() !== "Saving..." && confirm("Undo this Return/Refund for $" + amt.toFixed(2).toString() + "?")) {
			btn.val("Saving...");
			sendVoidReturn(btn.attr("id"), "ur", btn.data("pid"), null, refreshPayments);
		}
	});

	// Delete waive button
	$('#' + containerID).on('click', '.hhk-deleteWaive', function() {
		var btn = $(this);

		if (btn.val() !== 'Deleting...' && confirm('Delete this House payment?')) {
			btn.val('Deleting...');
			sendVoidReturn(btn.attr('id'), 'd', btn.data('ilid'), btn.data('iid'), null, refreshPayments);
		}
	});

	$('#' + containerID).on('click', '.pmtRecpt', function() {
		reprintReceipt($(this).data('pid'), '#pmtRcpt');
	});

	$('#' + containerID).mousedown(function(event) {
		var target = $(event.target);
		if (target[0].id !== 'pudiv' && target.parents("#" + 'pudiv').length === 0) {
			$('div#pudiv').remove();
		}
	});

}