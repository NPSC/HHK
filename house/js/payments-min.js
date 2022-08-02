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
 */ /**
 *
 * @param {int} orderNumber
 * @param {jquery} $diagBox
 * @returns {undefined}
 */ var gblAdjustData=[];function getApplyDiscDiag(a,b){"use strict";if(!a||""==a||0==a){flagAlertMessage("Order Number is missing","error");return}$.post("ws_ckin.php",{cmd:"getHPay",ord:a,arrDate:$("#spanvArrDate").text()},function(a){if(a){try{a=$.parseJSON(a)}catch(c){alert("Parser error - "+c.message);return}if(a.error)a.gotopage&&window.location.assign(a.gotopage),flagAlertMessage(a.error,"error");else if(a.markup){b.children().remove();var d={Save:function(){var a=parseFloat($("#housePayment").val().replace("$","").replace(",","")),b=parseFloat($("#houseTax").val()),d=$("#housePayment").data("vid"),c="",e=$.datepicker.formatDate("yy-mm-dd",$("#housePaymentDate").datepicker("getDate")),f=$("#housePaymentNote").val();isNaN(a)&&(a=0),isNaN(b)&&(b=0),c=$("#cbAdjustPmt1").prop("checked")?$("#cbAdjustPmt1").data("item"):$("#cbAdjustPmt2").data("item"),saveDiscountPayment(d,c,a,$("#selHouseDisc").val(),$("#selAddnlChg").val(),e,f),$(this).dialog("close")},Cancel:function(){$(this).dialog("close")}};b.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(a.markup))),$("#cbAdjustType").buttonset(),$("#cbAdjustPmt1, #cbAdjustPmt2").change(function(){var a=$(this).data("hid"),b=$(this).data("sho");$("."+a).val(""),$("."+b).val(""),$("#housePayment").val(""),$("#housePayment").change(),$("."+b).show(),$("."+a).hide()}),gblAdjustData.disc=a.disc,gblAdjustData.addnl=a.addnl,$("#selAddnlChg, #selHouseDisc").change(function(){var a=parseFloat(gblAdjustData[$(this).data("amts")][$(this).val()]);$("#housePayment").val(a.toFixed(2)),$("#housePayment").change()}),$("#housePayment").change(function(){if($("#cbAdjustPmt2").prop("checked")&&$("#houseTax").length>0){var c=parseFloat($("#houseTax").data("tax")),a=parseFloat($("#housePayment").val().replace("$","").replace(",","")),b=0,d=0;isNaN(c)&&(c=0),isNaN(a)&&(a=0),b=c*a,d=a+b,$("#houseTax").val(b>0?b.toFixed(2):""),$("#totalHousePayment").val(d>0?d.toFixed(2):"")}}),$("#cbAdjustPmt1").length>0?($("#cbAdjustPmt1").prop("checked",!0),$("#cbAdjustPmt1").change()):($("#cbAdjustPmt2").prop("checked",!0),$("#cbAdjustPmt2").change()),b.dialog("option","buttons",d),b.dialog("option","title","Adjust Fees"),b.dialog("option","width",getDialogWidth(430)),b.dialog("open")}}})}function saveDiscountPayment(a,b,c,d,e,f,g){"use strict";$.post("ws_ckin.php",{cmd:"saveHPay",ord:a,item:b,amt:c,dsc:d,chg:e,adjDate:f,notes:g},function(a){if(a){try{a=$.parseJSON(a)}catch(b){alert("Parser error - "+b.message);return}a.error&&(a.gotopage&&window.location.assign(a.gotopage),flagAlertMessage(a.error,"error")),a.reply&&""!=a.reply&&(flagAlertMessage(a.reply,"success"),$("#keysfees").dialog("close")),a.receipt&&""!==a.receipt&&($("#keysfees").length>0&&$("#keysfees").dialog("close"),showReceipt("#pmtRcpt",a.receipt,"Payment Receipt"))}})}function getInvoicee(b,d,a){"use strict";var c=parseInt(b.id,10);!1===isNaN(c)&&c>0?($("#txtInvName"+a).val(b.value),$("#txtInvId"+a).val(c),setTaxExempt(b.taxExempt)):($("#txtInvName"+a).val(""),$("#txtInvId"+a).val(""),setTaxExempt(!1)),$("#txtOrderNum").val(d),$("#txtInvSearch"+a).val("")}function setTaxExempt(a){"1"==a?$(".hhk-TaxingItem").removeClass("hhk-applyTax").val("0.00").parent("tr").hide():$(".hhk-TaxingItem").addClass("hhk-applyTax").parent("tr").show(),amtPaid()}function sendVoidReturn(d,a,e,c,f){var b={pid:e,bid:d};a&&"v"===a?b.cmd="void":a&&"rv"===a?b.cmd="revpmt":a&&"r"===a?(b.cmd="rtn",b.amt=c):a&&"ur"===a?(b.cmd="undoRtn",b.amt=c):a&&"vr"===a?b.cmd="voidret":a&&"d"===a&&(b.cmd="delWaive",b.iid=c),$.post("ws_ckin.php",b,function(a){var b="";if(a){try{a=$.parseJSON(a)}catch(c){alert("Parser error - "+c.message);return}if(a.error){a.gotopage&&window.location.assign(a.gotopage),flagAlertMessage(a.error,"error");return}if(a.reversal&&""!==a.reversal&&(b=a.reversal,f()),a.warning){flagAlertMessage(b+a.warning,"warning"),f();return}a.success&&(flagAlertMessage(b+a.success,"success"),f()),a.receipt&&showReceipt("#pmtRcpt",a.receipt,"Receipt")}})}var payCtrls=function(){var a=this;a.keyDepAmt=$("#keyDepAmt"),a.keyDepCb=$("#keyDepRx"),a.depRefundCb=$("#cbDepRefundApply"),a.depRefundAmt=$("#DepRefundAmount"),a.visitFeeAmt=$("#visitFeeAmt"),a.visitFeeCb=$("#visitFeeCb"),a.feePayAmt=$("input#feesPayment"),a.feesCharges=$("#feesCharges"),a.totalPayment=$("#totalPayment"),a.totalCharges=$("#totalCharges"),a.cashTendered=$("#txtCashTendered"),a.invoiceCb=$(".hhk-payInvCb"),a.adjustBtn=$("#paymentAdjust"),a.msg=$("#payChooserMsg"),a.heldAmtTb=$("#heldAmount"),a.heldCb=$("#cbHeld"),a.reimburseVatCb=$("#cbReimburseVAT"),a.reimburseVatAmt=$("#reimburseVat"),a.hsDiscAmt=$("#HsDiscAmount"),a.finalPaymentCb=$("input#cbFinalPayment"),a.overPay=$("#txtOverPayAmt"),a.guestCredit=$("#guestCredit"),a.selBalTo=$("#selexcpay")};function roundTo(b,a){void 0===a&&(a=0);var c=Math.pow(10,a);return Math.round(b=parseFloat((b*c).toFixed(11)))/c}function amtPaid(){"use strict";var a=new payCtrls,m=0,c=0,h=0,i=0,o="",j=0,e=0,d=0,b=0,v=0,f=0,p=0,q=0,l=0,g=0,u=0,r=0,w=0,n=isCheckedOut,s=parseFloat($("#spnCfBalDue").data("rmbal")),z=parseFloat($("#spnCfBalDue").data("taxedrmbal")),t=(parseFloat($("#spnCfBalDue").data("totbal")),$(".hhk-TaxingItem.hhk-applyTax"));if(isNaN(s)?s=0:t.each(function(){var a=parseFloat($(this).data("taxrate"));s<0?q+=roundTo(z*a,2,"ceil"):q+=roundTo(s*a,2)}),a.msg.text("").hide(),a.visitFeeCb.length>0&&(c=parseFloat($("#spnvfeeAmt").data("amt")),isNaN(c)||c<0|| !1===a.visitFeeCb.prop("checked")?(c=0,a.visitFeeAmt.val("")):a.visitFeeAmt.val(c.toFixed(2).toString())),!n&&a.keyDepCb.length>0&&(m=parseFloat($("#hdnKeyDepAmt").val()),isNaN(m)||m<0|| !1===a.keyDepCb.prop("checked")?(m=0,a.keyDepAmt.val("")):(a.keyDepAmt.val(m.toFixed(2).toString()),$(".hhk-kdrow").show())),a.invoiceCb.length>0&&a.invoiceCb.each(function(){var b,d=parseInt($(this).data("invnum")),a=$("#"+d+"invPayAmt"),c=parseFloat($(this).data("invamt"));!0===$(this).prop("checked")?(a.prop("disabled",!1),""===a.val()&&a.val(c.toFixed(2).toString()),b=parseFloat(a.val().replace("$","").replace(",","")),isNaN(b)||0==b?(b=0,a.val("")):(Math.abs(b)>Math.abs(c)&&(b=c),a.val(b.toFixed(2).toString())),j+=b):""!==a.val()&&(a.val(""),a.prop("disabled",!0))}),a.depRefundAmt.length>0&&n&&(p=parseFloat(a.depRefundAmt.data("amt")),isNaN(p)||p<0|| !1===a.depRefundCb.prop("checked")?(p=0,a.depRefundAmt.val("")):a.depRefundAmt.val((0-p).toFixed(2).toString())),a.heldCb.length>0&&(e=parseFloat(a.heldCb.data("amt")),(isNaN(e)||e<0|| !1===a.heldCb.prop("checked"))&&(e=0)),a.reimburseVatCb.length>0&&(d=parseFloat(a.reimburseVatCb.data("amt")),(isNaN(d)||d<0|| !1===a.reimburseVatCb.prop("checked"))&&(d=0)),u=e+p+d,t.each(function(){var a=parseFloat($(this).data("taxrate"));r+=roundTo(u/(1+a),2)}),r>q&&(r=q),w=u-r,a.feePayAmt.length>0&&(i=roundTo(parseFloat(o=a.feePayAmt.val().replace("$","").replace(",","")),2),"0.00"===o&&(o="0"),"0"!==o&&(o=""),(isNaN(i)||i<=0)&&(i=0),t.length>0&&(t.each(function(){var b=parseFloat($(this).data("taxrate")),a=roundTo(i*b,2);$(this).val(a.toFixed(2).toString()),l+=a}),l>q-r&&n&&(l=q-r),l<=0&&(l=0)),h=i+l),n){$(".hhk-minPayment").show("fade"),g=0,a.hsDiscAmt.val("");var k=roundTo(s+q,2);if(s>=0?(a.feesCharges.val(k.toFixed(2).toString()),$(".hhk-GuestCredit").hide(),$(".hhk-RoomCharge").show()):(a.guestCredit.val(k.toFixed(2).toString()),$(".hhk-RoomCharge").hide(),$(".hhk-GuestCredit").show(),j>0&&(k+j<=0?(k+=j,j=0):(j=k+j,k=0)),c>0&&(k+c<=0?(k+=c,c=0):(c=k+c,k=0))),b=c+j+k-u,f=c+j+h,b>=f&&b>0){var x=roundTo(c+j+s-w-i,2);if(x>0){if(a.finalPaymentCb.prop("checked")){var y=roundTo(q-(l+r),2);a.hsDiscAmt.val(x.toFixed(2).toString()),a.feesCharges.val((k-y).toFixed(2).toString()),b-=y,f=h}else a.hsDiscAmt.val("");$(".hhk-Overpayment").hide(),$(".hhk-HouseDiscount").show("fade")}else a.finalPaymentCb.prop("checked",!1),a.hsDiscAmt.val(""),$(".hhk-HouseDiscount").hide(),$(".hhk-Overpayment").hide();a.selBalTo.val(""),$("#txtRtnAmount").val(""),$("#divReturnPay").hide()}else a.finalPaymentCb.prop("checked",!1),a.hsDiscAmt.val(""),g=b>=0?f-b:c+h-b,"r"===a.selBalTo.val()?(b>=0?(i>g&&alert("Pay Room Fees amount is reduced to: $"+(i-g).toFixed(2).toString()),i-=g,h=i+l,g=0,a.selBalTo.val(""),$("#txtRtnAmount").val(""),$("#divReturnPay").hide()):(h>0&&alert("Pay Room Fees amount is reduced to: $0.00"),g-=h,i=0,l=0,t.each(function(){$(this).val("")}),h=0,$("#divReturnPay").show("fade"),$("#txtRtnAmount").val(g.toFixed(2).toString())),f=c+j+h-g):($("#txtRtnAmount").val(""),$("#divReturnPay").hide()),g.toFixed(2)>0?($(".hhk-Overpayment").show("fade"),$(".hhk-HouseDiscount").hide()):($(".hhk-Overpayment").hide(),$(".hhk-HouseDiscount").hide())}else(b=c+m+j+h)>0&&e>0?e>b?(e=b,b=0):b-=e:b<0&&e>0?b-=e:a.heldCb.length>0&&a.heldAmtTb.val(""),b>0&&d>0?d>b?d=0:b-=d:b<0&&d>0?b-=d:a.reimburseVatCb.length>0&&(d=0),$(".hhk-Overpayment").hide(),$(".hhk-HouseDiscount").hide(),a.hsDiscAmt.val(""),g=0,f=b,v=c+m+j+h;f>0||f<0&&!n?($(".paySelectTbl").show("fade"),$(".hhk-minPayment").show("fade"),f<0&&!n&&$("#txtRtnAmount").val((0-f).toFixed(2).toString())):(f=0,$(".paySelectTbl").hide(),$("#divReturnPay").hide(),!1===n&&0===v?($(".hhk-minPayment").hide(),e=0,d=0):$(".hhk-minPayment").show("fade")),0===h?a.feePayAmt.val(o):a.feePayAmt.val(i.toFixed(2).toString()),0==g.toFixed(2)?a.overPay.val(""):a.overPay.val(g.toFixed(2).toString()),e.toFixed(2)>0?a.heldAmtTb.val((0-e).toFixed(2).toString()):a.heldAmtTb.val(""),d>0?a.reimburseVatAmt.val((0-d).toFixed(2).toString()):a.reimburseVatAmt.val(""),a.totalCharges.val(b.toFixed(2).toString()),a.totalPayment.val(f.toFixed(2).toString()),$("#spnPayAmount").text("$"+f.toFixed(2).toString()),a.cashTendered.change()}function setupPayments(h,f,i,j){"use strict";var b=$("#PayTypeSel"),d=$(".tblCredit"),g=$("#trvdCHName"),a=new payCtrls;0===d.length&&(d=$(".hhk-mcred")),b.length>0&&(b.change(function(){$(".hhk-cashTndrd").hide(),$(".hhk-cknum").hide(),$("#tblInvoice").hide(),getInvoicee("",f,""),$(".hhk-transfer").hide(),$(".hhk-tfnum").hide(),d.hide(),g.hide(),$("#tdCashMsg").hide(),$(".paySelectNotes").show(),"cc"===$(this).val()?(d.show("fade"),0==$("input[name=rbUseCard]:checked").val()&&g.show()):"ck"===$(this).val()?$(".hhk-cknum").show("fade"):"in"===$(this).val()?($("#tblInvoice").show("fade"),$(".paySelectNotes").hide()):"tf"===$(this).val()?$(".hhk-transfer").show("fade"):$(".hhk-cashTndrd").show("fade")}),b.change()),setupCOF(g);var c=$("#rtnTypeSel"),e=$(".tblCreditr");0===e.length&&(e=$(".hhk-mcredr")),c.length>0&&(c.change(function(){e.hide(),$(".hhk-transferr").hide(),$(".payReturnNotes").show(),$(".hhk-cknumr").hide(),"cc"===$(this).val()?e.show("fade"):"ck"===$(this).val()?$(".hhk-cknumr").show("fade"):"tf"===$(this).val()&&$(".hhk-transferr").show("fade")}),c.change()),a.selBalTo.length>0&&a.selBalTo.change(function(){amtPaid()}),a.finalPaymentCb.length>0&&a.finalPaymentCb.change(function(){amtPaid()}),a.keyDepCb.length>0&&a.keyDepCb.change(function(){amtPaid()}),a.depRefundCb.length>0&&a.depRefundCb.change(function(){amtPaid()}),a.heldCb.length>0&&a.heldCb.change(function(){amtPaid()}),a.reimburseVatCb.length>0&&a.reimburseVatCb.change(function(){amtPaid()}),a.invoiceCb.length>0&&(a.invoiceCb.change(function(){amtPaid()}),$(".hhk-payInvAmt").change(function(){amtPaid()})),a.visitFeeCb.length>0&&a.visitFeeCb.change(function(){amtPaid()}),a.feePayAmt.length>0&&a.feePayAmt.change(function(){$(this).removeClass("ui-state-error"),amtPaid()}),a.cashTendered.length>0&&a.cashTendered.change(function(){a.cashTendered.removeClass("ui-state-highlight"),$("#tdCashMsg").hide();var b=parseFloat(a.totalPayment.val().replace(",",""));(isNaN(b)||b<0)&&(b=0);var c=parseFloat(a.cashTendered.val().replace("$","").replace(",",""));(isNaN(c)||c<0)&&(c=0,a.cashTendered.val(""));var d=c-b;d<0&&(d=0,a.cashTendered.addClass("ui-state-highlight")),$("#txtCashChange").text("$"+d.toFixed(2).toString())}),a.adjustBtn.length>0&&(a.adjustBtn.button(),a.adjustBtn.click(function(){getApplyDiscDiag(f,j)})),$("#divPmtMkup").on("click",".invAction",function(a){a.preventDefault(),("del"!=$(this).data("stat")||confirm("Delete this Invoice?"))&&invoiceAction($(this).data("iid"),$(this).data("stat"),a.target.id,"#keysfees",!0)}),createInvChooser(f,""),$("#daystoPay").change(function(){var b=parseInt($(this).val()),g=parseInt($(this).data("vid")),c=parseFloat($("#txtFixedRate").val()),d=parseInt($("#spnNumGuests").text()),i=a.feePayAmt,f=parseFloat($("#spnRcTax").data("tax")),e=parseFloat($("#seladjAmount").find(":selected").data("amount"));$(this).val(""),isNaN(d)&&(d=1),isNaN(c)&&(c=0),isNaN(f)&&(f=0),isNaN(e)&&(e=0),!isNaN(b)&&b>0&&daysCalculator(b,h.val(),g,c,e,d,0,function(a){i.val(a.toFixed(2).toString()),i.change()})}),amtPaid()}function createInvChooser(b,a){$("#txtInvSearch"+a).length>0&&($("#txtInvSearch"+a).keypress(function(d){var c=$(this).val();"13"==d.keyCode&&(""!=c&&isNumber(parseInt(c,10))?$.getJSON("../house/roleSearch.php",{cmd:"filter",basis:"ba",letters:c},function(c){try{c=c[0]}catch(d){alert("Parser error - "+d.message);return}c&&c.error&&(c.gotopage&&(response(),window.open(c.gotopage)),c.value=c.error),getInvoicee(c,b,a)}):(alert("Don't press the return key unless you enter an Id."),d.preventDefault()))}),createAutoComplete($("#txtInvSearch"+a),3,{cmd:"filter",basis:"ba"},function(c){getInvoicee(c,b,a)},!1))}function daysCalculator(a,b,c,d,e,f,g,i){if(a>0){var h={cmd:"rtcalc",vid:c,rid:g,nites:a,rcat:b,fxd:d,adj:e,gsts:f};$.post("ws_ckin.php",h,function(a){if(!a){alert("Bad Reply from Server");return}try{a=$.parseJSON(a)}catch(c){alert("Parser error - "+c.message);return}if(a.error){a.gotopage&&window.open(a.gotopage),flagAlertMessage(a.error,"error");return}if(a.amt){var b=parseFloat(a.amt);(isNaN(b)||b<0)&&(b=0),i(b)}})}}function verifyBalDisp(){return""==$("#selexcpay").val()&&""!=$("#txtOverPayAmt").val()?($("#payChooserMsg").text('Set "Apply To" to the desired overpayment disposition. ').show(),$("#selexcpay").addClass("ui-state-highlight"),$("#pWarnings").text('Set "Apply To" to the desired overpayment disposition.').show(),!1):($("#payChooserMsg").text("").hide(),$("#selexcpay").removeClass("ui-state-highlight"),!0)}function verifyAmtTendrd(){"use strict";if(0===$("#PayTypeSel").length)return!0;var a=parseFloat($("#totalPayment").val().replace("$","").replace(",",""));if($("#tdCashMsg").hide("fade"),$("#tdInvceeMsg").text("").hide(),$("#tdChargeMsg").text("").hide(),"ca"===$("#PayTypeSel").val()){var b=parseFloat($("#txtCashTendered").val().replace("$","").replace(",","")),c=$("#remtotalPayment");if(c.length>0&&(a=parseFloat(c.val().replace("$","").replace(",",""))),(isNaN(a)||a<0)&&(a=0),(isNaN(b)||b<0)&&(b=0),a>0&&b<=0)return $("#tdCashMsg").text('Enter the amount paid into "Amount Tendered" ').show(),$("#pWarnings").text('Enter the amount paid into "Amount Tendered"').show(),!1;if(a>0&&b<a)return $("#tdCashMsg").text("Amount tendered is not enough ").show("fade"),$("#pWarnings").text("Amount tendered is not enough").show(),!1}else if("in"===$("#PayTypeSel").val()){var d=parseInt($("#txtInvId").val(),10);if((isNaN(d)||d<1)&&0!=a)return $("#tdInvceeMsg").text("The Invoicee is missing. ").show("fade"),!1}else if("cc"===$("#PayTypeSel").val()&&$("#selccgw").length>0&&""===$("#selccgw").val())return $("#tdChargeMsg").text("Select a location.").show("fade"),!1;return!0}function showReceipt(g,d,e,b){var a=$(g),c=$("<div id='print_button' style='margin-left:1em;'>Print</div>"),f={mode:"popup",popClose:!1,popHt:500,popWd:400,popX:200,popY:200,popTitle:e};void 0!==b&&b||(b="1"==$(d).data("merchcopy")?900:550),a.children().remove(),a.append($(d).addClass("ReceiptArea").css("max-width",b+"px")),c.button(),c.click(function(){$(".ReceiptArea").printArea(f),a.dialog("close")}),a.prepend(c),a.dialog("option","title",e),a.dialog("option","buttons",{}),a.dialog("option","width",b),a.dialog("open"),f.popHt=$("#pmtRcpt").height(),f.popWd=$("#pmtRcpt").width()}function reprintReceipt(a,b){$.post("ws_ckin.php",{cmd:"getPrx",pid:a},function(a){if(a){try{a=$.parseJSON(a)}catch(c){alert("Parser error - "+c.message);return}a.error&&(a.gotopage&&window.location.assign(a.gotopage),flagAlertMessage(a.error,"error")),showReceipt(b,a.receipt,"Receipt Copy")}})}function paymentRedirect(a,b){"use strict";if(a){if(a.hostedError)flagAlertMessage(a.hostedError,"error");else if(a.cvtx)window.location.assign(a.cvtx);else if(a.xfer&&b.length>0){if(b.children("input").remove(),b.prop("action",a.xfer),a.paymentId&&""!=a.paymentId)b.append($('<input type="hidden" name="PaymentID" value="'+a.paymentId+'"/>'));else if(a.cardId&&""!=a.cardId)b.append($('<input type="hidden" name="CardID" value="'+a.cardId+'"/>'));else{flagAlertMessage("PaymentId and CardId are missing!","error");return}b.submit()}else a.inctx&&($("#contentDiv").empty().append($("<p>Processing Credit Payment...</p>")),InstaMed.launch(a.inctx),$("#instamed").css("visibility","visible").css("margin-top","50px;"))}}function setupCOF(b,a){null==a&&(a=""),b.length>0&&($("input[name=rbUseCard"+a+"]").on("change",function(){0==$(this).val()|| !0===$(this).prop("checked")&&"checkbox"===$(this).prop("type")?b.show():(b.hide(),$("#btnvrKeyNumber"+a).prop("checked",!1).change(),$("#txtvdNewCardName"+a).val("")),$("#tdChargeMsg"+a).text("").hide(),$("#selccgw"+a).removeClass("ui-state-highlight")}),($("input[name=rbUseCard"+a+"]:checked").val()>0|| !1===$("input[name=rbUseCard"+a+"]").prop("checked")&&"checkbox"===$("input[name=rbUseCard"+a+"]").prop("type"))&&b.hide(),$("#btnvrKeyNumber"+a).length>0&&($("#btnvrKeyNumber"+a).change(function(){0==$("input[name=rbUseCard"+a+"]:checked").val()|| !0===$("input[name=rbUseCard"+a+"]").prop("checked")&&"checkbox"===$("input[name=rbUseCard"+a+"]").prop("type")?$("#txtvdNewCardName"+a).show():($("#txtvdNewCardName"+a).hide(),$("#txtvdNewCardName"+a).val(""))}),$("#btnvrKeyNumber"+a).change()),$("#txtvdNewCardName"+a).length>0&&$("#txtvdNewCardName"+a).keydown(function(b){var a=b.which||b.keycode;return a>=48&&a<=57||a>=96&&a<=105?($("#lhnameerror").show(),!1):($("#lhnameerror").hide(),!0)}))}function cardOnFile(c,d,e,a){if($("#tdChargeMsg"+a).text("").hide(),$("#selccgw"+a).length>0&&(0==$("input[name=rbUseCard"+a+"]:checked").val()|| !0===$("input[name=rbUseCard"+a+"]").prop("checked"))&&($("#selccgw"+a).removeClass("ui-state-highlight"),0===$("#selccgw"+a+" option:selected").length))return $("#tdChargeMsg"+a).text("Select a location.").show("fade"),$("#selccgw"+a).addClass("ui-state-highlight"),!1;var b={cmd:"cof",idGuest:c,idGrp:d,pbp:e,index:a};$("#tblupCredit"+a).find("input").each(function(){"checkbox"===$(this).attr("type")?!1!==this.checked&&(b[$(this).attr("id")]="on"):"radio"===$(this).attr("type")?!1!==this.checked&&(b[$(this).attr("id")]=this.value):b[$(this).attr("id")]=this.value}),$("#selccgw"+a).length>0&&(b["selccgw"+a]=$("#selccgw"+a).val()),$("#selChargeType"+a).length>0&&(b["selChargeType"+a]=$("#selChargeType"+a).val()),$.post("ws_ckin.php",b,function(b){if(b){try{b=$.parseJSON(b)}catch(c){alert("Parser error - "+c.message);return}if(b.error){b.gotopage&&window.location.assign(b.gotopage),flagAlertMessage(b.error,"error");return}b.hostedError&&flagAlertMessage(b.hostedError,"error"),paymentRedirect(b,$("#xform")),(b.success&&""!=b.success||b.COFmsg&&""!=b.COFmsg)&&flagAlertMessage((void 0===b.success?"":b.success)+(void 0===b.COFmsg?"":b.COFmsg),"success"),b.COFmkup&&""!==b.COFmkup&&($("#tblupCredit"+a).remove(),$("#upCreditfs").prepend($(b.COFmkup)),setupCOF($("#trvdCHName"+a),a))}})}function paymentsTable(b,a,c){$("#"+b).dataTable({columnDefs:[{targets:8,type:"date",render:function(a,b,c){return dateRender(a,b)}},{targets:9,type:"date",render:function(a,b,c){return dateRender(a,b)}}],dom:'<"top"if>rt<"bottom"lp><"clear">',displayLength:50,order:[[8,"asc"]],lengthMenu:[[25,50,-1],[25,50,"All"]]}).on("draw",function(){$("#"+a).find("input[type=button]").button()}),$("#"+a).find("input[type=button]").button(),$("#btnPayHistRef").button().click(function(){c()}),$("#"+a).on("click",".invAction",function(a){invoiceAction($(this).data("iid"),"view",a.target.id)}),$("#"+a).on("click",".hhk-voidPmt",function(){var a=$(this),b=parseFloat(a.data("amt"));"Saving..."!==a.val()&&confirm("Void/Reverse this payment for $"+b.toFixed(2).toString()+"?")&&(a.val("Saving..."),sendVoidReturn(a.attr("id"),"rv",a.data("pid"),null,c))}),$("#"+a).on("click",".hhk-voidRefundPmt",function(){var a=$(this);"Saving..."!==a.val()&&confirm("Void this Return?")&&(a.val("Saving..."),sendVoidReturn(a.attr("id"),"vr",a.data("pid"),null,c))}),$("#"+a).on("click",".hhk-returnPmt",function(){var a=$(this),b=parseFloat(a.data("amt"));"Saving..."!==a.val()&&confirm("Return this payment for $"+b.toFixed(2).toString()+"?")&&(a.val("Saving..."),sendVoidReturn(a.attr("id"),"r",a.data("pid"),b,c))}),$("#"+a).on("click",".hhk-undoReturnPmt",function(){var a=$(this),b=parseFloat(a.data("amt"));"Saving..."!==a.val()&&confirm("Undo this Return/Refund for $"+b.toFixed(2).toString()+"?")&&(a.val("Saving..."),sendVoidReturn(a.attr("id"),"ur",a.data("pid"),null,c))}),$("#"+a).on("click",".hhk-deleteWaive",function(){var a=$(this);"Deleting..."!==a.val()&&confirm("Delete this House payment?")&&(a.val("Deleting..."),sendVoidReturn(a.attr("id"),"d",a.data("ilid"),a.data("iid"),null,c))}),$("#"+a).on("click",".pmtRecpt",function(){reprintReceipt($(this).data("pid"),"#pmtRcpt")}),$("#"+a).mousedown(function(b){var a=$(b.target);"pudiv"!==a[0].id&&0===a.parents("#pudiv").length&&$("div#pudiv").remove()})}