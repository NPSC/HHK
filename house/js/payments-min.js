var gblAdjustData=[];function getApplyDiscDiag(e,a){"use strict";e&&""!=e&&0!=e?$.post("ws_ckin.php",{cmd:"getHPay",ord:e,arrDate:$("#spanvArrDate").text()},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,"error");else if(e.markup){a.children().remove();var t={Save:function(){var e=parseFloat($("#housePayment").val().replace("$","").replace(",","")),t=$("#housePayment").data("vid"),a=$.datepicker.formatDate("yy-mm-dd",$("#housePaymentDate").datepicker("getDate")),i=$("#housePaymentNote").val();isNaN(e)&&(e=0),saveDiscountPayment(t,$("#cbAdjustPmt1").prop("checked")?$("#cbAdjustPmt1").data("item"):$("#cbAdjustPmt2").data("item"),e,$("#selHouseDisc").val(),$("#selAddnlChg").val(),a,i),$(this).dialog("close")},Cancel:function(){$(this).dialog("close")}};a.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(e.markup))),$("#cbAdjustType").buttonset(),$("#cbAdjustPmt1, #cbAdjustPmt2").change(function(){var e=$(this).data("hid"),t=$(this).data("sho");$("#"+e).val(""),$("#"+t).val(""),$("#housePayment").val(""),$(this).prop("checked")?($("#"+t).show(),$("#"+e).hide()):($("#"+e).hide(),$("#"+t).show())}),gblAdjustData.disc=e.disc,gblAdjustData.addnl=e.addnl,$("#selAddnlChg, #selHouseDisc").change(function(){var e=gblAdjustData[$(this).data("amts")];$("#housePayment").val(e[$(this).val()])}),0<$("#cbAdjustPmt1").length?($("#cbAdjustPmt1").prop("checked",!0),$("#cbAdjustPmt1").change()):($("#cbAdjustPmt2").prop("checked",!0),$("#cbAdjustPmt2").change()),a.dialog("option","buttons",t),a.dialog("option","title","Adjust Fees"),a.dialog("option","width",400),a.dialog("open")}}}):flagAlertMessage("Order Number is missing","error")}function saveDiscountPayment(e,t,a,i,r,s,n){"use strict";$.post("ws_ckin.php",{cmd:"saveHPay",ord:e,item:t,amt:a,dsc:i,chg:r,adjDate:s,notes:n},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error&&(e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,"error")),e.reply&&""!=e.reply&&(flagAlertMessage(e.reply,"success"),$("#keysfees").dialog("close")),e.receipt&&""!==e.receipt&&(0<$("#keysfees").length&&$("#keysfees").dialog("close"),showReceipt("#pmtRcpt",e.receipt,"Payment Receipt"))}})}function getInvoicee(e,t){"use strict";var a=parseInt(e.id,10);!1===isNaN(a)&&0<a?($("#txtInvName").val(e.value),$("#txtInvId").val(a)):($("#txtInvName").val(""),$("#txtInvId").val("")),$("#txtOrderNum").val(t),$("#txtInvSearch").val("")}function invoiceAction(e,t,a,i,r){"use strict";$.post("ws_resc.php",{cmd:"invAct",iid:e,x:a,action:t,sbt:r},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)return e.gotopage&&window.location.assign(e.gotopage),void flagAlertMessage(e.error,"error");if(e.delete&&("0"==e.eid?(flagAlertMessage(e.delete,"success"),$("#btnInvGo").click()):$("#"+e.eid).parents("tr").first().hide("fade")),e.markup){var t=$(e.markup);null!=i&&""!=i?$(i).append(t):$("body").append(t),t.position({my:"left top",at:"left bottom",of:"#"+e.eid})}}})}function sendVoidReturn(e,t,a,i){var r={pid:a,bid:e};t&&"v"===t?r.cmd="void":t&&"rv"===t?r.cmd="revpmt":t&&"r"===t?(r.cmd="rtn",r.amt=i):t&&"ur"===t?(r.cmd="undoRtn",r.amt=i):t&&"vr"===t?r.cmd="voidret":t&&"d"===t&&(r.cmd="delWaive",r.iid=i),$.post("ws_ckin.php",r,function(e){var t="";if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.bid&&$("#"+e.bid).remove(),e.error)return e.gotopage&&window.location.assign(e.gotopage),void flagAlertMessage(e.error,"error");if(e.reversal&&""!==e.reversal&&(t=e.reversal),e.warning)return void flagAlertMessage(t+e.warning,"warning");e.success&&flagAlertMessage(t+e.success,"success"),e.receipt&&showReceipt("#pmtRcpt",e.receipt,"Receipt")}})}var payCtrls=function(){var e=this;e.keyDepAmt=$("#keyDepAmt"),e.keyDepCb=$("#keyDepRx"),e.visitFeeAmt=$("#visitFeeAmt"),e.visitFeeCb=$("#visitFeeCb"),e.feePayAmt=$("input#feesPayment"),e.feesCharges=$("#feesCharges"),e.totalPayment=$("#totalPayment"),e.totalCharges=$("#totalCharges"),e.cashTendered=$("#txtCashTendered"),e.invoiceCb=$(".hhk-payInvCb"),e.adjustBtn=$("#paymentAdjust"),e.msg=$("#payChooserMsg"),e.heldAmtTb=$("#heldAmount"),e.heldCb=$("#cbHeld"),e.hsDiscAmt=$("#HsDiscAmount"),e.depRefundAmt=$("#DepRefundAmount"),e.finalPaymentCb=$("input#cbFinalPayment"),e.overPay=$("#txtOverPayAmt"),e.guestCredit=$("#guestCredit"),e.selBalTo=$("#selexcpay")};function amtPaid(){"use strict";var e=new payCtrls,t=0,a=0,i=0,r="",s=0,n=0,o=0,d=0,l=0,c=0,h=0,p=0,g=0,v=0,u=0,m=isCheckedOut;e.msg.text("").hide(),0<e.visitFeeCb.length&&(a=parseFloat($("#spnvfeeAmt").data("amt")),isNaN(a)||a<0||!1===e.visitFeeCb.prop("checked")?(a=0,e.visitFeeAmt.val("")):e.visitFeeAmt.val(a.toFixed(2).toString())),!m&&0<e.keyDepCb.length&&(t=parseFloat($("#spnDepAmt").data("amt")),isNaN(t)||t<0||!1===e.keyDepCb.prop("checked")?(t=0,e.keyDepAmt.val("")):e.keyDepAmt.val(t.toFixed(2).toString())),0<e.invoiceCb.length&&e.invoiceCb.each(function(){var e,t=parseInt($(this).data("invnum")),a=$("#"+t+"invPayAmt"),i=parseFloat($(this).data("invamt"));!0===$(this).prop("checked")?(a.prop("disabled",!1),""===a.val()&&a.val(i.toFixed(2).toString()),e=parseFloat(a.val().replace("$","").replace(",","")),isNaN(e)||0==e?(e=0,a.val("")):Math.abs(e)>Math.abs(i)&&(e=i,a.val(e.toFixed(2).toString())),n+=e):""!==a.val()&&(a.val(""),a.prop("disabled",!0))}),0<e.feePayAmt.length&&(r=e.feePayAmt.val().replace("$","").replace(",",""),i=parseFloat(r),(isNaN(i)||i<0)&&(e.feePayAmt.val(""),i=0)),0<e.feesCharges.length&&(s=parseFloat(e.feesCharges.val()),isNaN(s)&&(s=0)),0<e.guestCredit.length&&(v=parseFloat(e.guestCredit.val()),isNaN(v)&&(v=0)),0<e.depRefundAmt.length&&(g=parseFloat(e.depRefundAmt.val()),isNaN(g)&&(g=0)),0<e.heldCb.length&&(d=parseFloat(e.heldCb.data("amt")),(isNaN(d)||d<0)&&(d=0),e.heldCb.prop("checked")&&(o=d)),l=a+t+s+n+v+g,m||(l+=i),0<l&&0<o?l<o&&!m?(o=l,l=0):l-=o:l<0&&0<o?l-=o:0===l&&0<o&&m?l-=o:0<e.heldCb.length&&e.heldAmtTb.val(""),m?($(".hhk-minPayment").show("fade"),p=l<0?l-i:l+i,l-i<=0?($(".hhk-HouseDiscount").hide(),e.hsDiscAmt.val(""),e.finalPaymentCb.prop("checked",!1),u=0-(l-i),"r"===e.selBalTo.val()?0<=l?(i!==l&&alert("Pay Room Fees amount is reduced to: $"+l.toFixed(2).toString()),i=l,u=0,e.selBalTo.val(""),$("#txtRtnAmount").val(""),$("#divReturnPay").hide()):(0<i&&alert("Pay Room Fees amount is reduced to: $0.00"),u-=i,i=0,$("#divReturnPay").show("fade"),$("#txtRtnAmount").val(u.toFixed(2).toString())):($("#txtRtnAmount").val(""),$("#divReturnPay").hide()),p=i,0<u?$(".hhk-Overpayment").show("fade"):$(".hhk-Overpayment").hide()):($(".hhk-Overpayment").hide(),u=0,p=e.finalPaymentCb.prop("checked")?((h=l-i)<=0?(h=0,e.hsDiscAmt.val("")):e.hsDiscAmt.val((0-h).toFixed(2).toString()),i):(e.hsDiscAmt.val(""),a+t+n+i),$(".hhk-HouseDiscount").show("fade"))):($(".hhk-Overpayment").hide(),$(".hhk-HouseDiscount").hide(),e.hsDiscAmt.val(""),u=0,p=l,c=a+t+n+i),0<p||p<0&&!m?($(".paySelectTbl").show("fade"),$(".hhk-minPayment").show("fade"),p<0&&!m&&$("#txtRtnAmount").val((0-p).toFixed(2).toString())):(p=0,$(".paySelectTbl").hide(),!1===m&&0===c?($(".hhk-minPayment").hide(),o=0):$(".hhk-minPayment").show("fade")),0===i&&""===r?e.feePayAmt.val(""):e.feePayAmt.val(i.toFixed(2).toString()),0===u?e.overPay.val(""):e.overPay.val(u.toFixed(2).toString()),0<o?e.heldAmtTb.val((0-o).toFixed(2).toString()):e.heldAmtTb.val(""),e.totalCharges.val(l.toFixed(2).toString()),e.totalPayment.val(p.toFixed(2).toString()),$("#spnPayAmount").text("$"+p.toFixed(2).toString()),e.cashTendered.change()}function setupPayments(n,a,e,t){"use strict";var i=$("#PayTypeSel"),r=$(".tblCredit"),o=new payCtrls;0===r.length&&(r=$(".hhk-mcred")),0<i.length&&(i.change(function(){$(".hhk-cashTndrd").hide(),$(".hhk-cknum").hide(),$("#tblInvoice").hide(),$(".hhk-transfer").hide(),$(".hhk-tfnum").hide(),r.hide(),$(".hhkKeyNumber").hide(),$("#tdCashMsg").hide(),$(".paySelectNotes").show(),"cc"===$(this).val()?(r.show("fade"),0==$("input[name=rbUseCard]:checked").val()&&$(".hhkKeyNumber").show()):"ck"===$(this).val()?$(".hhk-cknum").show("fade"):"in"===$(this).val()?($("#tblInvoice").show("fade"),$(".paySelectNotes").hide()):"tf"===$(this).val()?$(".hhk-transfer").show("fade"):$(".hhk-cashTndrd").show("fade")}),i.change());var s=$("#rtnTypeSel"),d=$(".tblCreditr");0===d.length&&(d=$(".hhk-mcredr")),0<s.length&&(s.change(function(){d.hide(),$(".hhk-transferr").hide(),$(".payReturnNotes").show(),$(".hhk-cknum").hide(),"cc"===$(this).val()?d.show("fade"):"ck"===$(this).val()?$(".hhk-cknum").show("fade"):"tf"===$(this).val()?$(".hhk-transferr").show("fade"):"in"===$(this).val()&&$(".payReturnNotes").hide()}),s.change()),0<o.selBalTo.length&&o.selBalTo.change(function(){amtPaid()}),0<o.finalPaymentCb.length&&o.finalPaymentCb.change(function(){amtPaid()}),0<o.keyDepCb.length&&o.keyDepCb.change(function(){amtPaid()}),0<o.heldCb.length&&o.heldCb.change(function(){amtPaid()}),0<o.invoiceCb.length&&(o.invoiceCb.change(function(){amtPaid()}),$(".hhk-payInvAmt").change(function(){amtPaid()})),0<o.visitFeeCb.length&&o.visitFeeCb.change(function(){amtPaid()}),0<o.feePayAmt.length&&o.feePayAmt.change(function(){$(this).removeClass("ui-state-error"),amtPaid()}),0<o.cashTendered.length&&o.cashTendered.change(function(){o.cashTendered.removeClass("ui-state-highlight"),$("#tdCashMsg").hide();var e=parseFloat(o.totalPayment.val().replace(",",""));(isNaN(e)||e<0)&&(e=0);var t=parseFloat(o.cashTendered.val().replace("$","").replace(",",""));(isNaN(t)||t<0)&&(t=0,o.cashTendered.val(""));var a=t-e;a<0&&(a=0,o.cashTendered.addClass("ui-state-highlight")),$("#txtCashChange").text("$"+a.toFixed(2).toString())}),0<o.adjustBtn.length&&(o.adjustBtn.button(),o.adjustBtn.click(function(){getApplyDiscDiag(a,t)})),$("#divPmtMkup").on("click",".invAction",function(e){e.preventDefault(),"del"==$(this).data("stat")&&!confirm("Delete this Invoice?")||invoiceAction($(this).data("iid"),$(this).data("stat"),e.target.id,"#keysfees",!0)}),0<$("#txtInvSearch").length&&($("#txtInvSearch").keypress(function(e){var t=$(this).val();"13"==e.keyCode&&(""!=t&&isNumber(parseInt(t,10))?$.getJSON("../house/roleSearch.php",{cmd:"filter",basis:"ba",letters:t},function(e){try{e=e[0]}catch(e){return void alert("Parser error - "+e.message)}e&&e.error&&(e.gotopage&&(response(),window.open(e.gotopage)),e.value=e.error),getInvoicee(e,a)}):(alert("Don't press the return key unless you enter an Id."),e.preventDefault()))}),createAutoComplete($("#txtInvSearch"),3,{cmd:"filter",basis:"ba"},function(e){getInvoicee(e,a)},!1)),$("#daystoPay").change(function(){var e=parseInt($(this).val()),t=parseInt($(this).data("vid")),a=parseFloat($("#txtFixedRate").val()),i=parseInt($("#spnNumGuests").text()),r=o.feePayAmt;isNaN(i)&&(i=1),isNaN(a)&&(a=0);var s=parseFloat($("#txtadjAmount").val());isNaN(s)&&(s=0),isNaN(e)?$(this).val(""):0<e&&daysCalculator(e,n.val(),t,a,s,i,0,function(e){r.val(e.toFixed(2).toString()),r.change()})}),amtPaid()}function daysCalculator(e,t,a,i,r,s,n,o){if(0<e){var d={cmd:"rtcalc",vid:a,rid:n,nites:e,rcat:t,fxd:i,adj:r,gsts:s};$.post("ws_ckin.php",d,function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)return e.gotopage&&window.open(e.gotopage),void flagAlertMessage(e.error,"error");if(e.amt){var t=parseFloat(e.amt);(isNaN(t)||t<0)&&(t=0),o(t)}}else alert("Bad Reply from Server")})}}function verifyBalDisp(){return""==$("#selexcpay").val()&&""!=$("#txtOverPayAmt").val()?($("#payChooserMsg").text('Set "Apply To" to the desired overpayment disposition. ').show(),$("#selexcpay").addClass("ui-state-highlight"),$("#pWarnings").text('Set "Apply To" to the desired overpayment disposition.').show(),!1):($("#payChooserMsg").text("").hide(),$("#selexcpay").removeClass("ui-state-highlight"),!0)}function verifyAmtTendrd(){"use strict";if(0===$("#PayTypeSel").length)return!0;if($("#tdCashMsg").hide("fade"),$("#tdInvceeMsg").text("").hide(),"ca"===$("#PayTypeSel").val()){var e=parseFloat($("#totalPayment").val().replace("$","").replace(",","")),t=parseFloat($("#txtCashTendered").val().replace("$","").replace(",","")),a=$("#remtotalPayment");if(0<a.length&&(e=parseFloat(a.val().replace("$","").replace(",",""))),(isNaN(e)||e<0)&&(e=0),(isNaN(t)||t<0)&&(t=0),0<e&&t<=0)return $("#tdCashMsg").text('Enter the amount paid into "Amount Tendered" ').show(),$("#pWarnings").text('Enter the amount paid into "Amount Tendered"').show(),!1;if(0<e&&t<e)return $("#tdCashMsg").text("Amount tendered is not enough ").show("fade"),$("#pWarnings").text("Amount tendered is not enough").show(),!1}else if("in"===$("#PayTypeSel").val()){var i=parseInt($("#txtInvId").val(),10);if(isNaN(i)||i<1)return $("#tdInvceeMsg").text("The Invoicee is missing. ").show("fade"),!1}return!0}function showReceipt(e,t,a,i){var r=$(e),s=$("<div id='print_button' style='margin-left:1em;'>Print</div>"),n={mode:"popup",popClose:!1,popHt:500,popWd:400,popX:200,popY:200,popTitle:a};void 0!==i&&i||(i=550),r.children().remove(),r.append($(t).addClass("ReceiptArea").css("max-width",i+"px")),s.button(),s.click(function(){$(".ReceiptArea").printArea(n),r.dialog("close")}),r.prepend(s),r.dialog("option","title",a),r.dialog("option","buttons",{}),r.dialog("option","width",i),r.dialog("open"),n.popHt=$("#pmtRcpt").height(),n.popWd=$("#pmtRcpt").width()}function reprintReceipt(e,t){$.post("ws_ckin.php",{cmd:"getPrx",pid:e},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error&&(e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,"error")),showReceipt(t,e.receipt,"Receipt Copy")}})}function paymentRedirect(e,t){"use strict";if(e)if(e.hostedError)flagAlertMessage(e.hostedError,"error");else if(e.cvtx)window.location.assign(e.cvtx);else if(e.xfer&&0<t.length){if(t.children("input").remove(),t.prop("action",e.xfer),e.paymentId&&""!=e.paymentId)t.append($('<input type="hidden" name="PaymentID" value="'+e.paymentId+'"/>'));else{if(!e.cardId||""==e.cardId)return void flagAlertMessage("PaymentId and CardId are missing!","error");t.append($('<input type="hidden" name="CardID" value="'+e.cardId+'"/>'))}t.submit()}else e.inctx&&($("#contentDiv").empty().append($("<p>Processing Credit Payment...</p>")),InstaMed.launch(e.inctx),$("#instamed").css("visibility","visible").css("margin-top","50px;"))}function cardOnFile(e,t,a){var i={cmd:"cof",idGuest:e,idGrp:t,pbp:a};$("#tblupCredit").find("input").each(function(){this.checked&&(i[$(this).attr("id")]=$(this).val())}),$.post("ws_ckin.php",i,function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)return e.gotopage&&window.location.assign(e.gotopage),void flagAlertMessage(e.error,"error");e.hostedError&&flagAlertMessage(e.hostedError,"error"),paymentRedirect(e,$("#xform")),e.success&&""!=e.success&&flagAlertMessage(e.success,"success"),e.COFmkup&&""!==e.COFmkup&&($("#tblupCredit").remove(),$("#upCreditfs").append($(e.COFmkup)))}})}function updateCredit(i,r,e,s,t){var n="";e&&""!=e&&(n=" - "+e),$.post("ws_ckin.php",{cmd:"viewCredit",idGuest:i,reg:r,pbp:t},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error&&(e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,"error"));var t={Continue:function(){cardOnFile(i,r,e.pbp),$(this).dialog("close")},Cancel:function(){$(this).dialog("close")}};if(e.success){var a=$("#"+s);a.children().remove(),a.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog"/>').append($(e.success))),a.dialog("option","buttons",t),a.dialog("option","width",400),a.dialog("option","title","Card On File"+n),a.dialog("open")}}})}
