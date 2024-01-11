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
 */ var gblAdjustData = []; function roundTo(e, t) { void 0 === t && (t = 0); let a = Math.pow(10, t); return Math.round(e = parseFloat((e * a).toFixed(11))) / a } function getApplyDiscDiag(e, t) { "use strict"; if (!e || "" == e || 0 == e) { flagAlertMessage("Order Number is missing", "error"); return } $.post("ws_ckin.php", { cmd: "getHPay", ord: e, arrDate: $("#spanvArrDate").text() }, function (e) { if (e) { try { e = $.parseJSON(e) } catch (a) { alert("Parser error - " + a.message); return } e.error ? (e.gotopage && window.location.assign(e.gotopage), flagAlertMessage(e.error, "error")) : e.markup && (t.children().remove(), t.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(e.markup))), $("#cbAdjustType").buttonset(), $("#cbAdjustPmt1, #cbAdjustPmt2").change(function () { var e = $(this).data("hid"), t = $(this).data("sho"); $("." + e).val(""), $("." + t).val(""), $("#housePayment").val(""), $("#housePayment").change(), $("." + t).show(), $("." + e).hide() }), gblAdjustData.disc = e.disc, gblAdjustData.addnl = e.addnl, $("#selAddnlChg, #selHouseDisc").change(function () { var e = parseFloat(gblAdjustData[$(this).data("amts")][$(this).val()]); $("#housePayment").val(e.toFixed(2)), $("#housePayment").change() }), $("#housePayment").change(function () { if ($("#cbAdjustPmt2").prop("checked") && $("#houseTax").length > 0) { var e = parseFloat($("#houseTax").data("tax")), t = parseFloat($("#housePayment").val().replace("$", "").replace(",", "")), a = 0, r = 0; isNaN(e) && (e = 0), isNaN(t) && (t = 0), a = e * t, r = t + a, $("#houseTax").val(a > 0 ? a.toFixed(2) : ""), $("#totalHousePayment").val(r > 0 ? r.toFixed(2) : "") } }), $("#cbAdjustPmt1").length > 0 ? ($("#cbAdjustPmt1").prop("checked", !0), $("#cbAdjustPmt1").change()) : ($("#cbAdjustPmt2").prop("checked", !0), $("#cbAdjustPmt2").change()), t.dialog("option", "buttons", { Save: function () { var e = parseFloat($("#housePayment").val().replace("$", "").replace(",", "")), t = parseFloat($("#houseTax").val()), a = $("#housePayment").data("vid"), r = "", n = $.datepicker.formatDate("yy-mm-dd", $("#housePaymentDate").datepicker("getDate")), i = $("#housePaymentNote").val(); isNaN(e) && (e = 0), isNaN(t) && (t = 0), r = $("#cbAdjustPmt1").prop("checked") ? $("#cbAdjustPmt1").data("item") : $("#cbAdjustPmt2").data("item"), saveDiscountPayment(a, r, e, $("#selHouseDisc").val(), $("#selAddnlChg").val(), n, i), $(this).dialog("close") }, Cancel: function () { $(this).dialog("close") } }), t.dialog("option", "title", "Adjust Fees"), t.dialog("option", "width", getDialogWidth(430)), t.dialog("open")) } }) } function saveDiscountPayment(e, t, a, r, n, i, o) { "use strict"; $.post("ws_ckin.php", { cmd: "saveHPay", ord: e, item: t, amt: a, dsc: r, chg: n, adjDate: i, notes: o }, function (e) { if (e) { try { e = $.parseJSON(e) } catch (t) { alert("Parser error - " + t.message); return } e.error && (e.gotopage && window.location.assign(e.gotopage), flagAlertMessage(e.error, "error")), e.reply && "" != e.reply && (flagAlertMessage(e.reply, "success"), $("#keysfees").dialog("close")), e.receipt && "" !== e.receipt && ($("#keysfees").length > 0 && $("#keysfees").dialog("close"), showReceipt("#pmtRcpt", e.receipt, "Payment Receipt")) } }) } function getInvoicee(e, t, a) { "use strict"; let r = parseInt(e.id, 10); !1 === isNaN(r) && r > 0 ? ($("#txtInvName" + a).val(e.value), $("#txtInvId" + a).val(r), setTaxExempt(e.taxExempt)) : ($("#txtInvName" + a).val(""), $("#txtInvId" + a).val(""), setTaxExempt(!1)), $("#txtOrderNum").val(t), $("#txtInvSearch").val(""), amtPaid() } function setTaxExempt(e) { "1" == e ? $(".hhk-TaxingItem").removeClass("hhk-applyTax").val("0.00") : $(".hhk-TaxingItem").addClass("hhk-applyTax") } function sendVoidReturn(e, t, a, r, n) { var i = { pid: a, bid: e }; t && "v" === t ? i.cmd = "void" : t && "rv" === t ? i.cmd = "revpmt" : t && "r" === t ? (i.cmd = "rtn", i.amt = r) : t && "ur" === t ? (i.cmd = "undoRtn", i.amt = r) : t && "vr" === t ? i.cmd = "voidret" : t && "d" === t && (i.cmd = "delWaive", i.iid = r), $.post("ws_ckin.php", i, function (e) { let t = ""; if (e) { try { e = $.parseJSON(e) } catch (a) { alert("Parser error - " + a.message); return } if (e.error) { e.gotopage && window.location.assign(e.gotopage), flagAlertMessage(e.error, "error"); return } if (e.reversal && "" !== e.reversal && (t = e.reversal, n()), e.warning) { flagAlertMessage(t + e.warning, "warning"), n(); return } e.success && (flagAlertMessage(t + e.success, "success"), n()), e.receipt && showReceipt("#pmtRcpt", e.receipt, "Receipt") } }) } class UpdateTaxes { constructor(e) { this.taxingItems = e } calcTax(e) { let t = 0; return this.taxingItems.length > 0 && (this.taxingItems.each(function () { let a = roundTo(e * parseFloat($(this).data("taxrate")), 2); $(this).val(a.toFixed(2).toString()), t += a }), t <= 0 && (t = 0)), t } } class AmountVariables { constructor(e) { var t = this; t.kdep = 0, t.kdepCharge = 0, t.vfee = 0, t.visitfeeCharge = 0, t.feePay = 0, t.feePayPreTax = 0, t.feePayText = "", t.prePayRoomAmt = 0, t.invAmt = 0, t.invCharge = 0, t.invPayment = 0, t.heldAmt = 0, t.chkingIn = 0, t.reimburseAmt = 0, t.totCharges = 0, t.ckedInCharges = 0, t.totPay = 0, t.depRfAmt = 0, t.depRfPay = 0, t.roomBalTaxDue = 0, t.feePayTaxAmt = 0, t.overPayAmt = 0, t.totReturns = 0, t.totReturnTax = 0, t.totReturnPreTax = 0, t.isChdOut = e, t.roomBalDue = 0, t.totRmBalDue = 0, t.extraPayment = 0 } } class PayCtrls { constructor() { var e = this; e.keyDepAmt = $("#keyDepAmt"), e.keyDepCb = $("#keyDepRx"), e.depRefundCb = $("#cbDepRefundApply"), e.depRefundAmt = $("#DepRefundAmount"), e.visitFeeAmt = $("#visitFeeAmt"), e.visitFeeCb = $("#visitFeeCb"), e.feePayAmt = $("input#feesPayment"), e.feesCharges = $("#feesCharges"), e.totalPayment = $("#totalPayment"), e.cashTendered = $("#txtCashTendered"), e.extraPay = $("#extraPay"), e.invoiceCb = $(".hhk-payInvCb"), e.adjustBtn = $("#paymentAdjust"), e.msg = $("#payChooserMsg"), e.heldAmtTb = $("#heldAmount"), e.heldCb = $("#cbHeld"), e.reimburseVatCb = $("#cbReimburseVAT"), e.reimburseVatAmt = $("#reimburseVat"), e.hsDiscAmt = $("#HsDiscAmount"), e.houseWaiveCb = $("input#houseWaiveCb"), e.overPay = $("#txtOverPayAmt"), e.guestCredit = $("#guestCredit"), e.selBalTo = $("#selexcpay"), e.taxingItems = $(".hhk-TaxingItem.hhk-applyTax") } } function getPaymentData(e, t) { if (e.visitFeeCb.length > 0 && (t.visitfeeCharge = parseFloat($("#spnvfeeAmt").data("amt")), isNaN(t.vfee) || t.vfee < 0 || !1 === e.visitFeeCb.prop("checked") ? (t.vfee = 0, e.visitFeeAmt.val("")) : (t.vfee = t.visitfeeCharge, e.visitFeeAmt.val(t.visitfeeCharge.toFixed(2).toString()))), !t.isChdOut && e.keyDepCb.length > 0) { t.kdepCharge = parseFloat($("#hdnKeyDepAmt").val()); let a = parseFloat($("#kdPaid").data("amt")); isNaN(a) && (a = 0), isNaN(t.kdepCharge) || t.kdepCharge <= 0 || a > 0 ? (t.kdepCharge = 0, t.kdep = 0, e.keyDepAmt.val(""), e.keyDepCb.prop("checked", !1), $(".hhk-kdrow").hide()) : (!1 === e.keyDepCb.prop("checked") ? (e.keyDepAmt.val(""), t.kdep = 0) : (e.keyDepAmt.val(t.kdepCharge.toFixed(2).toString()), t.kdep = t.kdepCharge), $(".hhk-kdrow").show()) } if (e.invoiceCb.length > 0 && e.invoiceCb.each(function () { let e = parseInt($(this).data("invnum")), a = $("#" + e + "invPayAmt"), r = parseFloat($(this).data("invamt")), n; !0 === $(this).prop("checked") ? (a.prop("disabled", !1), "" === a.val() && a.val(r.toFixed(2).toString()), isNaN(n = parseFloat(a.val().replace("$", "").replace(",", ""))) || 0 == n ? (n = 0, a.val("")) : (Math.abs(n) > Math.abs(r) && (n = r), a.val(n.toFixed(2).toString())), t.invAmt += n, t.invCharge += r) : "" !== a.val() && (a.val(""), a.prop("disabled", !0)) }), e.depRefundAmt.length > 0 && t.isChdOut && (t.depRfAmt = parseFloat(e.depRefundAmt.data("amt")), isNaN(t.depRfAmt) || t.depRfAmt < 0 || !1 === e.depRefundCb.prop("checked") ? (t.depRfPay = 0, e.depRefundAmt.val("")) : (t.depRfPay = t.depRfAmt, e.depRefundAmt.val((0 - t.depRfAmt).toFixed(2).toString()))), e.heldCb.length > 0) { let r = parseInt(e.heldCb.data("prepay")); t.heldAmt = parseFloat(e.heldCb.data("amt")), t.chkingIn = parseInt(e.heldCb.data("chkingin")), isNaN(t.chkingIn) && (t.chkingIn = 0), (isNaN(t.heldAmt) || t.heldAmt < 0 || !1 === e.heldCb.prop("checked")) && (t.heldAmt = 0), 1 == t.chkingIn ? !0 === e.heldCb.prop("checked") && 1 == r ? t.prePayRoomAmt = t.heldAmt : !1 === e.heldCb.prop("checked") && (t.prePayRoomAmt = 0) : t.prePayRoomAmt = 0 } e.reimburseVatCb.length > 0 && (t.reimburseAmt = parseFloat(e.reimburseVatCb.data("amt")), (isNaN(t.reimburseAmt) || t.reimburseAmt < 0 || !1 === e.reimburseVatCb.prop("checked")) && (t.reimburseAmt = 0)) } function getPaymentAmount(e, t) { if (e.feePayAmt.length > 0) { t.feePayText = e.feePayAmt.val().replace("$", "").replace(",", ""), t.feePayPreTax = roundTo(parseFloat(t.feePayText), 2), (isNaN(t.feePayPreTax) || t.feePayPreTax <= 0) && (t.feePayPreTax = 0), "0.00" === t.feePayText && (t.feePayText = "0"), "0" !== t.feePayText && (t.feePayText = ""); let a = t.prePayRoomAmt - t.vfee - t.kdep - (t.invAmt < 0 ? 0 : t.invAmt); a > 0 ? t.feePayPreTax = a : t.prePayRoomAmt > 0 && (t.feePayPreTax = 0); let r = new UpdateTaxes(e.taxingItems); t.feePayTaxAmt = r.calcTax(t.feePayPreTax), t.feePayTaxAmt > t.roomBalTaxDue - t.totReturnTax && t.isChdOut && (t.feePayTaxAmt = t.roomBalTaxDue - t.totReturnTax < 0 ? 0 : t.roomBalTaxDue - t.totReturnTax), t.feePay = t.feePayPreTax + t.feePayTaxAmt } if (e.extraPay.length > 0) { let n = e.extraPay.val().replace("$", "").replace(",", ""); t.extraPayment = roundTo(parseFloat(n), 2), (isNaN(t.extraPayment) || t.extraPayment <= 0) && (t.extraPayment = 0), t.extraPayment.toFixed(2) > 0 ? e.extraPay.val(t.extraPayment.toFixed(2).toString()) : e.extraPay.val("") } } function doOverpayment(e, t) { let a = t.feePay, r = 0, n = new UpdateTaxes(e.taxingItems); if ("r" === e.selBalTo.val() && t.overPayAmt > 0) { t.totRmBalDue > 0 && (r = t.totRmBalDue); let i = Math.max(t.feePayPreTax - r, 0); i > t.overPayAmt ? (t.feePayPreTax -= i, t.overPayAmt -= i, $("#txtRtnAmount").val(""), $("#divReturnPay").hide(), e.selBalTo.val("")) : t.overPayAmt >= i && (t.overPayAmt -= i, t.extraPayment > 0 && (t.overPayAmt -= t.extraPayment, t.totPay -= t.extraPayment, e.extraPay.val(""), alert("Extra Payment amount is reduced to 0")), t.feePayPreTax = r, t.overPayAmt > 0 ? ($("#divReturnPay").show("fade"), $("#txtRtnAmount").val(t.overPayAmt.toFixed(2).toString())) : ($("#txtRtnAmount").val(""), $("#divReturnPay").hide(), e.selBalTo.val(""))), t.feePay = t.feePayPreTax + n.calcTax(t.feePayPreTax), t.totPay = t.totPay - a + t.feePay, a != t.feePay && alert("Pay Room Fees amount is reduced to: $" + t.feePay.toFixed(2).toString()) } else $("#txtRtnAmount").val(""), $("#divReturnPay").hide(); 0 >= t.overPayAmt.toFixed(2) ? (e.overPay.val(""), $(".hhk-Overpayment").hide(), e.selBalTo.val("")) : (e.overPay.val(t.overPayAmt.toFixed(2).toString()), $(".hhk-Overpayment").show("fade")) } function doHouseWaive(e, t) { if (t.overPayAmt < 0 || 0 == t.overPayAmt && t.totCharges > 0) { if ($(".totalPaymentTr").show("fade"), $(".hhk-HouseDiscount").show(), e.houseWaiveCb.prop("checked")) { let a = Math.max(t.roomBalDue - t.feePayPreTax, 0) + t.invAmt + t.vfee - t.totReturns, r = 0 - t.overPayAmt; a > 0 ? (e.hsDiscAmt.val(a.toFixed(2).toString()), t.totPay = Math.max(t.totCharges - a, 0) - t.totReturns - t.roomBalTaxDue) : t.overPayAmt < 0 ? (e.hsDiscAmt.val(r.toFixed(2).toString()), t.totPay -= r) : (e.hsDiscAmt.val(""), $(".hhk-HouseDiscount").hide(), e.houseWaiveCb.prop("checked", !1)) } else e.hsDiscAmt.val("") } else $(".hhk-HouseDiscount").hide(), $(".totalPaymentTr").hide(), e.hsDiscAmt.val(""), e.houseWaiveCb.prop("checked", !1) } function amtPaid() { "use strict"; var e = new PayCtrls, t = new AmountVariables(isCheckedOut); if (e.msg.text("").hide(), t.roomBalDue = parseFloat($("#spnCfBalDue").data("rmbal")), isNaN(t.roomBalDue)) t.roomBalDue = 0; else { let a = parseFloat($("#spnCfBalDue").data("taxedrmbal")); e.taxingItems.each(function () { let e = parseFloat($(this).data("taxrate")); t.roomBalDue < 0 ? t.roomBalTaxDue += roundTo(a * e, 2, "ceil") : t.roomBalTaxDue += roundTo(t.roomBalDue * e, 2) }) } getPaymentData(e, t), getPaymentAmount(e, t), t.totReturns = +t.reimburseAmt + t.heldAmt + t.depRfPay, t.totPay = t.invAmt + t.vfee + t.kdep + t.feePay, t.isChdOut ? ($(".hhk-minPayment").show("fade"), e.hsDiscAmt.val(""), t.overPayAmt = 0, t.totRmBalDue = roundTo(t.roomBalDue + t.roomBalTaxDue, 2), t.totCharges = t.invAmt + t.visitfeeCharge + t.kdepCharge, t.totRmBalDue > 0 ? (e.feesCharges.val(t.roomBalDue.toFixed(2).toString()), $(".hhk-GuestCredit").hide(), $(".hhk-RoomFees").show("fade"), $(".hhk-RoomCharge").show("fade"), $("#daystoPay").hide(), t.totCharges += t.totRmBalDue) : (e.guestCredit.val(t.totRmBalDue.toFixed(2).toString()), $(".hhk-RoomFees").hide(), $(".hhk-extraPayTr").show("fade"), $(".hhk-GuestCredit").show("fade"), t.totReturns -= t.totRmBalDue, t.totReturns += t.extraPayment, t.totRmBalDue = 0), t.totReturns >= t.totPay ? (t.totReturns -= t.totPay, t.overPayAmt = t.totReturns, t.totPay = t.extraPayment) : (t.overPayAmt = t.totPay - t.totCharges, t.totPay -= t.totReturns), doOverpayment(e, t), doHouseWaive(e, t)) : ($("#daystoPay").show("fade"), $(".hhk-RoomFees").show("fade"), $(".hhk-Overpayment").hide(), $(".totalPaymentTr").show("fade"), $(".hhk-HouseDiscount").hide(), $(".hhk-RoomCharge").hide(), $(".hhk-GuestCredit").hide(), $("#divReturnPay").hide(), $("#txtRtnAmount").val(""), e.hsDiscAmt.val(""), t.totPay -= t.totReturns), t.totPay > 0 ? ($(".paySelectTbl").show("fade"), $(".hhk-minPayment").show()) : (t.totPay = 0, $(".paySelectTbl").hide(), t.isChdOut || $(".hhk-minPayment").hide()), 0 === t.feePay ? e.feePayAmt.val(t.feePayText) : e.feePayAmt.val(t.feePayPreTax.toFixed(2).toString()), t.heldAmt.toFixed(2) > 0 ? e.heldAmtTb.val((0 - t.heldAmt).toFixed(2).toString()) : e.heldAmtTb.val(""), t.reimburseAmt > 0 ? e.reimburseVatAmt.val((0 - t.reimburseAmt).toFixed(2).toString()) : e.reimburseVatAmt.val(""), e.totalPayment.val(t.totPay.toFixed(2).toString()), $("#spnPayAmount").text("$" + t.totPay.toFixed(2).toString()), e.cashTendered.change() } function setupPayments(e, t, a, r, n) { "use strict"; var i = $("#PayTypeSel"), o = $(".tblCredit"), s = $("#trvdCHName"), d = new PayCtrls; 0 === o.length && (o = $(".hhk-mcred")), i.length > 0 && i.change(function () { $(".hhk-cashTndrd").hide(), $(".hhk-cknum").hide(), $("#tblInvoice").hide(), getInvoicee("", t, ""), $(".hhk-transfer").hide(), $(".hhk-tfnum").hide(), o.hide(), s.hide(), $("#tdCashMsg").hide(), $(".paySelectNotes").show(), "cc" === $(this).val() ? (o.show("fade"), 0 == $("input[name=rbUseCard]:checked").val() && s.show()) : "ck" === $(this).val() ? $(".hhk-cknum").show("fade") : "in" === $(this).val() ? ($("#tblInvoice").show("fade"), $(".paySelectNotes").hide()) : "tf" === $(this).val() ? $(".hhk-transfer").show("fade") : $(".hhk-cashTndrd").show("fade") }), setupCOF(s); var h = $("#rtnTypeSel"), c = $(".tblCreditr"); 0 === c.length && (c = $(".hhk-mcredr")), h.length > 0 && (h.change(function () { c.hide(), $(".hhk-transferr").hide(), $(".payReturnNotes").show(), $(".hhk-cknumr").hide(), "cc" === $(this).val() ? c.show("fade") : "ck" === $(this).val() ? $(".hhk-cknumr").show("fade") : "tf" === $(this).val() && $(".hhk-transferr").show("fade") }), h.change()), d.selBalTo.length > 0 && d.selBalTo.change(function () { amtPaid() }), d.houseWaiveCb.length > 0 && d.houseWaiveCb.change(function () { amtPaid() }), d.keyDepCb.length > 0 && d.keyDepCb.change(function () { amtPaid() }), d.depRefundCb.length > 0 && d.depRefundCb.change(function () { amtPaid() }), d.heldCb.length > 0 && d.heldCb.change(function () { amtPaid() }), d.reimburseVatCb.length > 0 && d.reimburseVatCb.change(function () { amtPaid() }), d.invoiceCb.length > 0 && (d.invoiceCb.change(function () { amtPaid() }), $(".hhk-payInvAmt").change(function () { amtPaid() })), d.visitFeeCb.length > 0 && d.visitFeeCb.change(function () { amtPaid() }), d.feePayAmt.length > 0 && d.feePayAmt.change(function () { $(this).removeClass("ui-state-error"), amtPaid() }), d.feesCharges.length > 0 && d.feesCharges.click(function () { d.feePayAmt.val($(this).val()).focus(), d.feePayAmt.change() }), d.extraPay.length > 0 && d.extraPay.change(function () { $(this).removeClass("ui-state-error"), amtPaid() }), d.cashTendered.length > 0 && d.cashTendered.change(function () { d.cashTendered.removeClass("ui-state-highlight"), $("#tdCashMsg").hide(); var e = parseFloat(d.totalPayment.val().replace(",", "")); (isNaN(e) || e < 0) && (e = 0); var t = parseFloat(d.cashTendered.val().replace("$", "").replace(",", "")); (isNaN(t) || t < 0) && (t = 0, d.cashTendered.val("")); var a = t - e; a < 0 && (a = 0, d.cashTendered.addClass("ui-state-highlight")), $("#txtCashChange").text("$" + a.toFixed(2).toString()) }), d.adjustBtn.length > 0 && (d.adjustBtn.button(), d.adjustBtn.click(function () { getApplyDiscDiag(t, r) })), $("#divPmtMkup, #div-hhk-payments").on("click", ".invAction", function (e) { e.preventDefault(), ("del" != $(this).data("stat") || confirm("Delete Invoice " + $(this).data("inb") + ("" != $(this).data("payor") ? " for " + $(this).data("payor") : "") + "?")) && invoiceAction($(this).data("iid"), $(this).data("stat"), e.target.id, n, !0) }), createInvChooser(t, ""), $("#daystoPay").change(function () { let t = parseInt($(this).val()), a = parseInt($(this).data("vid")), r = parseFloat($("#txtFixedRate").val()), n = parseInt($("#spnNumGuests").text()), i = d.feePayAmt, o = parseFloat($("#spnRcTax").data("tax")), s = parseFloat($("#seladjAmount").find(":selected").data("amount")); $(this).val(""), isNaN(n) && (n = 1), isNaN(r) && (r = 0), isNaN(o) && (o = 0), isNaN(s) && (s = 0), !isNaN(t) && t > 0 && daysCalculator(t, e, a, r, s, n, 0, function (e) { i.val(e.toFixed(2).toString()), i.change() }) }), i.length > 0 ? i.change() : amtPaid() } function createInvChooser(e, t) { $("#txtInvSearch" + t).length > 0 && ($("#txtInvSearch" + t).keypress(function (a) { var r = $(this).val(); "13" == a.keyCode && ("" != r && isNumber(parseInt(r, 10)) ? $.getJSON("../house/roleSearch.php", { cmd: "filter", basis: "ba", letters: r }, function (a) { try { a = a[0] } catch (r) { alert("Parser error - " + r.message); return } a && a.error && (a.gotopage && (response(), window.open(a.gotopage)), a.value = a.error), getInvoicee(a, e, t) }) : (alert("Don't press the return key unless you enter an Id."), a.preventDefault())) }), createAutoComplete($("#txtInvSearch" + t), 3, { cmd: "filter", basis: "ba" }, function (a) { getInvoicee(a, e, t) }, !1)) } function daysCalculator(e, t, a, r, n, i, o, s) { e > 0 && $.post("ws_ckin.php", { cmd: "rtcalc", vid: a, rid: o, nites: e, rcat: t, fxd: r, adj: n, gsts: i }, function (e) { if (!e) { alert("Bad Reply from Server"); return } try { e = $.parseJSON(e) } catch (t) { alert("Parser error - " + t.message); return } if (e.error) { e.gotopage && window.open(e.gotopage), flagAlertMessage(e.error, "error"); return } if (e.amt) { var a = parseFloat(e.amt); (isNaN(a) || a < 0) && (a = 0), s(a) } }) } function verifyBalDisp() { return "" == $("#selexcpay").val() && "" != $("#txtOverPayAmt").val() ? ($("#payChooserMsg").text('Set "Apply To" to the desired overpayment disposition. ').show(), $("#selexcpay").addClass("ui-state-highlight"), $("#pWarnings").text('Set "Apply To" to the desired overpayment disposition.').show(), !1) : ($("#payChooserMsg").text("").hide(), $("#selexcpay").removeClass("ui-state-highlight"), !0) } function verifyAmtTendrd() { "use strict"; if (0 === $("#PayTypeSel").length) return !0; let e = parseFloat($("#totalPayment").val().replace("$", "").replace(",", "")); if ($("#tdCashMsg").hide("fade"), $("#tdInvceeMsg").text("").hide(), $("#tdChargeMsg").text("").hide(), "ca" === $("#PayTypeSel").val()) { let t = parseFloat($("#txtCashTendered").val().replace("$", "").replace(",", "")), a = $("#remtotalPayment"); if (a.length > 0 && (e = parseFloat(a.val().replace("$", "").replace(",", ""))), (isNaN(e) || e < 0) && (e = 0), (isNaN(t) || t < 0) && (t = 0), e > 0 && t <= 0) return $("#tdCashMsg").text('Enter the amount paid into "Amount Tendered" ').show(), $("#pWarnings").text('Enter the amount paid into "Amount Tendered"').show(), !1; if (e > 0 && t < e) return $("#tdCashMsg").text("Amount tendered is not enough ").show("fade"), $("#pWarnings").text("Amount tendered is not enough").show(), !1 } else if ("in" === $("#PayTypeSel").val()) { let r = parseInt($("#txtInvId").val(), 10); if ((isNaN(r) || r < 1) && 0 != e) return $("#tdInvceeMsg").text("The Invoicee is missing. ").show("fade"), !1 } else if ("cc" === $("#PayTypeSel").val() && $("#selccgw").length > 0 && "" === $("#selccgw").val()) return $("#tdChargeMsg").text("Select a location.").show("fade"), !1; return !0 } function showReceipt(e, t, a, r) { var n = $(e), i = $("<div id='print_button' style='margin-left:1em;'>Print</div>"), o = { mode: "popup", popClose: !1, popHt: 500, popWd: 400, popX: 200, popY: 200, popTitle: a }; void 0 !== r && r || (r = "1" == $(t).data("merchcopy") ? 900 : 550), n.children().remove(), n.append($(t).addClass("ReceiptArea").css("max-width", r + "px")), i.button(), i.click(function () { $(".ReceiptArea").printArea(o), n.dialog("close") }), n.prepend(i), n.dialog("option", "title", a), n.dialog("option", "buttons", {}), n.dialog("option", "width", r), n.dialog("open"), o.popHt = $("#pmtRcpt").height(), o.popWd = $("#pmtRcpt").width() } function reprintReceipt(e, t) { $.post("ws_ckin.php", { cmd: "getPrx", pid: e }, function (e) { if (e) { try { e = $.parseJSON(e) } catch (a) { alert("Parser error - " + a.message); return } e.error && (e.gotopage && window.location.assign(e.gotopage), flagAlertMessage(e.error, "error")), showReceipt(t, e.receipt, "Receipt Copy") } }) } function paymentRedirect(e, t) { "use strict"; if (e) { if (e.hostedError) flagAlertMessage(e.hostedError, "error"); else if (e.cvtx) window.location.assign(e.cvtx); else if (e.xfer && t.length > 0) { if (t.children("input").remove(), t.prop("action", e.xfer), e.paymentId && "" != e.paymentId) t.append($('<input type="hidden" name="PaymentID" value="' + e.paymentId + '"/>')); else if (e.cardId && "" != e.cardId) t.append($('<input type="hidden" name="CardID" value="' + e.cardId + '"/>')); else { flagAlertMessage("PaymentId and CardId are missing!", "error"); return } t.submit() } else e.inctx && ($("#contentDiv").empty().append($("<p>Processing Credit Payment...</p>")), InstaMed.launch(e.inctx), $("#instamed").css("visibility", "visible").css("margin-top", "50px;")) } } function setupCOF(e, t) { null == t && (t = ""), e.length > 0 && ($("input[name=rbUseCard" + t + "]").on("change", function () { 0 == $(this).val() || !0 === $(this).prop("checked") && "checkbox" === $(this).prop("type") ? e.show() : (e.hide(), $("#btnvrKeyNumber" + t).prop("checked", !1).change(), $("#txtvdNewCardName" + t).val("")), $("#tdChargeMsg" + t).text("").hide(), $("#selccgw" + t).removeClass("ui-state-highlight") }), ($("input[name=rbUseCard" + t + "]:checked").val() > 0 || !1 === $("input[name=rbUseCard" + t + "]").prop("checked") && "checkbox" === $("input[name=rbUseCard" + t + "]").prop("type")) && e.hide(), $("#btnvrKeyNumber" + t).length > 0 && ($("#btnvrKeyNumber" + t).change(function () { 0 == $("input[name=rbUseCard" + t + "]:checked").val() || !0 === $("input[name=rbUseCard" + t + "]").prop("checked") && "checkbox" === $("input[name=rbUseCard" + t + "]").prop("type") ? $("#txtvdNewCardName" + t).show() : ($("#txtvdNewCardName" + t).hide(), $("#txtvdNewCardName" + t).val("")) }), $("#btnvrKeyNumber" + t).change()), $("#txtvdNewCardName" + t).length > 0 && $("#txtvdNewCardName" + t).keydown(function (e) { var t = e.which || e.keycode; return t >= 48 && t <= 57 || t >= 96 && t <= 105 ? ($("#lhnameerror").show(), !1) : ($("#lhnameerror").hide(), !0) })) } function cardOnFile(e, t, a, r) { if ($("#tdChargeMsg" + r).text("").hide(), $("#selccgw" + r).length > 0 && (0 == $("input[name=rbUseCard" + r + "]:checked").val() || !0 === $("input[name=rbUseCard" + r + "]").prop("checked")) && ($("#selccgw" + r).removeClass("ui-state-highlight"), 0 === $("#selccgw" + r + " option:selected").length)) return $("#tdChargeMsg" + r).text("Select a location.").show("fade"), $("#selccgw" + r).addClass("ui-state-highlight"), !1; var n = { cmd: "cof", idGuest: e, idGrp: t, pbp: a, index: r }; $("#tblupCredit" + r).find("input").each(function () { "checkbox" === $(this).attr("type") ? !1 !== this.checked && (n[$(this).attr("id")] = "on") : "radio" === $(this).attr("type") ? !1 !== this.checked && (n[$(this).attr("id")] = this.value) : n[$(this).attr("id")] = this.value }), $("#selccgw" + r).length > 0 && (n["selccgw" + r] = $("#selccgw" + r).val()), $("#selChargeType" + r).length > 0 && (n["selChargeType" + r] = $("#selChargeType" + r).val()), $.post("ws_ckin.php", n, function (e) { if (e) { try { e = $.parseJSON(e) } catch (t) { alert("Parser error - " + t.message); return } if (e.error) { e.gotopage && window.location.assign(e.gotopage), flagAlertMessage(e.error, "error"); return } e.hostedError && flagAlertMessage(e.hostedError, "error"), paymentRedirect(e, $("#xform")), (e.success && "" != e.success || e.COFmsg && "" != e.COFmsg) && flagAlertMessage((void 0 === e.success ? "" : e.success) + (void 0 === e.COFmsg ? "" : e.COFmsg), "success"), e.COFmkup && "" !== e.COFmkup && ($("#tblupCredit" + r).remove(), $("#upCreditfs").prepend($(e.COFmkup)), setupCOF($("#trvdCHName" + r), r)) } }) } function paymentsTable(e, t, a) { $("#" + e).DataTable({ columnDefs: [{ targets: 8, type: "date", render: function (e, t, a) { return dateRender(e, t) } }, { targets: 9, type: "date", render: function (e, t, a) { return dateRender(e, t) } }], dom: '<"top"if><"hhk-overflow-x hhk-tbl-wrap"rt><"bottom"lp><"clear">', displayLength: 50, order: [[8, "asc"]], lengthMenu: [[25, 50, -1], [25, 50, "All"]], drawCallback: function () { $("#" + t).find("input[type=button]").button() } }), $("#btnPayHistRef").button().click(function () { a() }), $("#" + t).on("click", ".invAction", function (e) { invoiceAction($(this).data("iid"), "view", e.target.id) }), $("#" + t).on("click", ".hhk-voidPmt", function () { var e = $(this), t = parseFloat(e.data("amt")); "Saving..." !== e.val() && confirm("Void/Reverse this payment for $" + t.toFixed(2).toString() + "?") && (e.val("Saving..."), sendVoidReturn(e.attr("id"), "rv", e.data("pid"), null, a)) }), $("#" + t).on("click", ".hhk-voidRefundPmt", function () { var e = $(this); "Saving..." !== e.val() && confirm("Void this Return?") && (e.val("Saving..."), sendVoidReturn(e.attr("id"), "vr", e.data("pid"), null, a)) }), $("#" + t).on("click", ".hhk-returnPmt", function () { var e = $(this), t = parseFloat(e.data("amt")); "Saving..." !== e.val() && confirm("Return this payment for $" + t.toFixed(2).toString() + "?") && (e.val("Saving..."), sendVoidReturn(e.attr("id"), "r", e.data("pid"), t, a)) }), $("#" + t).on("click", ".hhk-undoReturnPmt", function () { var e = $(this), t = parseFloat(e.data("amt")); "Saving..." !== e.val() && confirm("Undo this Return/Refund for $" + t.toFixed(2).toString() + "?") && (e.val("Saving..."), sendVoidReturn(e.attr("id"), "ur", e.data("pid"), null, a)) }), $("#" + t).on("click", ".hhk-deleteWaive", function () { var e = $(this); "Deleting..." !== e.val() && confirm("Delete this House payment?") && (e.val("Deleting..."), sendVoidReturn(e.attr("id"), "d", e.data("ilid"), e.data("iid"), null, a)) }), $("#" + t).on("click", ".pmtRecpt", function () { reprintReceipt($(this).data("pid"), "#pmtRcpt") }), $("#" + t).mousedown(function (e) { var t = $(e.target); "pudiv" !== t[0].id && 0 === t.parents("#pudiv").length && $("div#pudiv").remove() }) }