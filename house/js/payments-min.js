var gblAdjustData=[];function getApplyDiscDiag(e,t){"use strict";e&&""!=e&&0!=e?$.post("ws_ckin.php",{cmd:"getHPay",ord:e,arrDate:$("#spanvArrDate").text()},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,"error");else if(e.markup){t.children().remove();var a={Save:function(){var e=parseFloat($("#housePayment").val().replace("$","").replace(",","")),t=$("#housePayment").data("vid"),a=$.datepicker.formatDate("yy-mm-dd",$("#housePaymentDate").datepicker("getDate")),s=$("#housePaymentNote").val();isNaN(e)&&(e=0),saveDiscountPayment(t,$("#cbAdjustPmt1").prop("checked")?$("#cbAdjustPmt1").data("item"):$("#cbAdjustPmt2").data("item"),e,$("#selHouseDisc").val(),$("#selAddnlChg").val(),a,s),$(this).dialog("close")},Cancel:function(){$(this).dialog("close")}};t.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(e.markup))),$("#cbAdjustType").buttonset(),$("#cbAdjustPmt1, #cbAdjustPmt2").change(function(){var e=$(this).data("hid"),t=$(this).data("sho");$("#"+e).val(""),$("#"+t).val(""),$("#housePayment").val(""),$(this).prop("checked")?($("#"+t).show(),$("#"+e).hide()):($("#"+e).hide(),$("#"+t).show())}),gblAdjustData.disc=e.disc,gblAdjustData.addnl=e.addnl,$("#selAddnlChg, #selHouseDisc").change(function(){var e=gblAdjustData[$(this).data("amts")];$("#housePayment").val(e[$(this).val()])}),$("#cbAdjustPmt1").length>0?($("#cbAdjustPmt1").prop("checked",!0),$("#cbAdjustPmt1").change()):($("#cbAdjustPmt2").prop("checked",!0),$("#cbAdjustPmt2").change()),t.dialog("option","buttons",a),t.dialog("option","title","Adjust Fees"),t.dialog("option","width",400),t.dialog("open")}}}):flagAlertMessage("Order Number is missing","error")}function saveDiscountPayment(e,t,a,s,i,r,o){"use strict";$.post("ws_ckin.php",{cmd:"saveHPay",ord:e,item:t,amt:a,dsc:s,chg:i,adjDate:r,notes:o},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error&&(e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,"error")),e.reply&&""!=e.reply&&(flagAlertMessage(e.reply,"success"),$("#keysfees").dialog("close")),e.receipt&&""!==e.receipt&&($("#keysfees").length>0&&$("#keysfees").dialog("close"),showReceipt("#pmtRcpt",e.receipt,"Payment Receipt"))}})}function getInvoicee(e,t){"use strict";var a=parseInt(e.id,10);!1===isNaN(a)&&a>0?($("#txtInvName").val(e.value),$("#txtInvId").val(a)):($("#txtInvName").val(""),$("#txtInvId").val("")),$("#txtOrderNum").val(t),$("#txtInvSearch").val("")}function invoiceAction(e,t,a,s,i){"use strict";$.post("ws_resc.php",{cmd:"invAct",iid:e,x:a,action:t,sbt:i},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)return e.gotopage&&window.location.assign(e.gotopage),void flagAlertMessage(e.error,"error");if(e.delete&&("0"==e.eid?(flagAlertMessage(e.delete,"success"),$("#btnInvGo").click()):$("#"+e.eid).parents("tr").first().hide("fade")),e.markup){var t=$(e.markup);null!=s&&""!=s?$(s).append(t):$("body").append(t),t.position({my:"left top",at:"left bottom",of:"#"+e.eid})}}})}function sendVoidReturn(e,t,a,s){var i={pid:a,bid:e};t&&"v"===t?i.cmd="void":t&&"rv"===t?i.cmd="revpmt":t&&"r"===t?(i.cmd="rtn",i.amt=s):t&&"vr"===t?i.cmd="voidret":t&&"d"===t&&(i.cmd="delWaive",i.iid=s),$.post("ws_ckin.php",i,function(e){var t="";if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.bid&&$("#"+e.bid).remove(),e.error)return e.gotopage&&window.location.assign(e.gotopage),void flagAlertMessage(e.error,"error");if(e.reversal&&""!==e.reversal&&(t=e.reversal),e.warning)return void flagAlertMessage(t+e.warning,"warning");e.success&&flagAlertMessage(t+e.success,"success"),e.receipt&&showReceipt("#pmtRcpt",e.receipt,"Receipt")}})}var chgRoomList,payCtrls=function(){var e=this;e.keyDepAmt=$("#keyDepAmt"),e.keyDepCb=$("#keyDepRx"),e.visitFeeAmt=$("#visitFeeAmt"),e.visitFeeCb=$("#visitFeeCb"),e.feePayAmt=$("input#feesPayment"),e.feesCharges=$("#feesCharges"),e.totalPayment=$("#totalPayment"),e.totalCharges=$("#totalCharges"),e.cashTendered=$("#txtCashTendered"),e.invoiceCb=$(".hhk-payInvCb"),e.adjustBtn=$("#paymentAdjust"),e.msg=$("#payChooserMsg"),e.heldAmtTb=$("#heldAmount"),e.heldCb=$("#cbHeld"),e.hsDiscAmt=$("#HsDiscAmount"),e.depRefundAmt=$("#DepRefundAmount"),e.finalPaymentCb=$("input#cbFinalPayment"),e.overPay=$("#txtOverPayAmt"),e.guestCredit=$("#guestCredit"),e.selBalTo=$("#selexcpay")};function amtPaid(){"use strict";var e=new payCtrls,t=0,a=0,s=0,i="",r=0,o=0,n=0,d=0,l=0,h=0,c=0,p=0,g=0,m=0,v=0,u=isCheckedOut;e.msg.text("").hide(),e.visitFeeCb.length>0&&(a=parseFloat($("#spnvfeeAmt").data("amt")),isNaN(a)||a<0||!1===e.visitFeeCb.prop("checked")?(a=0,e.visitFeeAmt.val("")):e.visitFeeAmt.val(a.toFixed(2).toString())),!u&&e.keyDepCb.length>0&&(t=parseFloat($("#spnDepAmt").data("amt")),isNaN(t)||t<0||!1===e.keyDepCb.prop("checked")?(t=0,e.keyDepAmt.val("")):e.keyDepAmt.val(t.toFixed(2).toString())),e.invoiceCb.length>0&&e.invoiceCb.each(function(){var e,t=parseInt($(this).data("invnum")),a=$("#"+t+"invPayAmt"),s=parseFloat($(this).data("invamt"));!0===$(this).prop("checked")?(a.prop("disabled",!1),""===a.val()&&a.val(s.toFixed(2).toString()),e=parseFloat(a.val().replace("$","").replace(",","")),isNaN(e)||0==e?(e=0,a.val("")):Math.abs(e)>Math.abs(s)&&(e=s,a.val(e.toFixed(2).toString())),o+=e):""!==a.val()&&(a.val(""),a.prop("disabled",!0))}),e.feePayAmt.length>0&&(i=e.feePayAmt.val().replace("$","").replace(",",""),s=parseFloat(i),(isNaN(s)||s<0)&&(e.feePayAmt.val(""),s=0)),e.feesCharges.length>0&&(r=parseFloat(e.feesCharges.val()),isNaN(r)&&(r=0)),e.guestCredit.length>0&&(m=parseFloat(e.guestCredit.val()),isNaN(m)&&(m=0)),e.depRefundAmt.length>0&&(g=parseFloat(e.depRefundAmt.val()),isNaN(g)&&(g=0)),e.heldCb.length>0&&(d=parseFloat(e.heldCb.data("amt")),(isNaN(d)||d<0)&&(d=0),e.heldCb.prop("checked")&&(n=d)),l=a+t+r+o+m+g,u||(l+=s),l>0&&n>0?n>l&&!u?(n=l,l=0):l-=n:l<0&&n>0?l-=n:0===l&&n>0&&u?l-=n:e.heldCb.length>0&&e.heldAmtTb.val(""),u?($(".hhk-minPayment").show("fade"),p=l<0?l-s:l+s,l-s<=0?($(".hhk-HouseDiscount").hide(),e.hsDiscAmt.val(""),e.finalPaymentCb.prop("checked",!1),v=0-(l-s),"r"===e.selBalTo.val()?l>=0?(s!==l&&alert("Pay Room Fees amount is reduced to: $"+l.toFixed(2).toString()),s=l,v=0,e.selBalTo.val(""),$("#txtRtnAmount").val(""),$("#divReturnPay").hide()):(s>0&&alert("Pay Room Fees amount is reduced to: $0.00"),v-=s,s=0,$("#divReturnPay").show("fade"),$("#txtRtnAmount").val(v.toFixed(2).toString())):($("#txtRtnAmount").val(""),$("#divReturnPay").hide()),p=s,v>0?$(".hhk-Overpayment").show("fade"):$(".hhk-Overpayment").hide()):($(".hhk-Overpayment").hide(),v=0,e.finalPaymentCb.prop("checked")?((c=l-s)<=0?(c=0,e.hsDiscAmt.val("")):e.hsDiscAmt.val((0-c).toFixed(2).toString()),p=s):(e.hsDiscAmt.val(""),p=a+t+o+s),$(".hhk-HouseDiscount").show("fade"))):($(".hhk-Overpayment").hide(),$(".hhk-HouseDiscount").hide(),e.hsDiscAmt.val(""),v=0,p=l,h=a+t+o+s),p>0||p<0&&!u?($(".paySelectTbl").show("fade"),$(".hhk-minPayment").show("fade"),p<0&&!u&&$("#txtRtnAmount").val((0-p).toFixed(2).toString())):(p=0,$(".paySelectTbl").hide(),!1===u&&0===h?($(".hhk-minPayment").hide(),n=0):$(".hhk-minPayment").show("fade")),0===s&&""===i?e.feePayAmt.val(""):e.feePayAmt.val(s.toFixed(2).toString()),0===v?e.overPay.val(""):e.overPay.val(v.toFixed(2).toString()),n>0?e.heldAmtTb.val((0-n).toFixed(2).toString()):e.heldAmtTb.val(""),e.totalCharges.val(l.toFixed(2).toString()),e.totalPayment.val(p.toFixed(2).toString()),$("#spnPayAmount").text("$"+p.toFixed(2).toString()),e.cashTendered.change()}function setupPayments(e,t,a,s,i,r){"use strict";var o=$("#PayTypeSel"),n=$(".tblCredit"),d=new payCtrls;0===n.length&&(n=$(".hhk-mcred")),o.length>0&&(o.change(function(){$(".hhk-cashTndrd").hide(),$(".hhk-cknum").hide(),$("#tblInvoice").hide(),$(".hhk-transfer").hide(),$(".hhk-tfnum").hide(),n.hide(),$("#tdCashMsg").hide(),$(".paySelectNotes").show(),"cc"===$(this).val()?n.show("fade"):"ck"===$(this).val()?$(".hhk-cknum").show("fade"):"in"===$(this).val()?($("#tblInvoice").show("fade"),$(".paySelectNotes").hide()):"tf"===$(this).val()?$(".hhk-transfer").show("fade"):$(".hhk-cashTndrd").show("fade")}),o.change());var l=$("#rtnTypeSel"),h=$(".tblCreditr");0===h.length&&(h=$(".hhk-mcredr")),l.length>0&&(l.change(function(){h.hide(),$(".hhk-transferr").hide(),$(".payReturnNotes").show(),$(".hhk-cknum").hide(),"cc"===$(this).val()?h.show("fade"):"ck"===$(this).val()?$(".hhk-cknum").show("fade"):"tf"===$(this).val()?$(".hhk-transferr").show("fade"):"in"===$(this).val()&&$(".payReturnNotes").hide()}),l.change()),d.selBalTo.length>0&&d.selBalTo.change(function(){amtPaid()}),d.finalPaymentCb.length>0&&d.finalPaymentCb.change(function(){amtPaid()}),d.keyDepCb.length>0&&d.keyDepCb.change(function(){amtPaid()}),d.heldCb.length>0&&d.heldCb.change(function(){amtPaid()}),d.invoiceCb.length>0&&(d.invoiceCb.change(function(){amtPaid()}),$(".hhk-payInvAmt").change(function(){amtPaid()})),d.visitFeeCb.length>0&&d.visitFeeCb.change(function(){amtPaid()}),d.feePayAmt.length>0&&d.feePayAmt.change(function(){$(this).removeClass("ui-state-error"),amtPaid()}),d.cashTendered.length>0&&d.cashTendered.change(function(){d.cashTendered.removeClass("ui-state-highlight"),$("#tdCashMsg").hide();var e=parseFloat(d.totalPayment.val().replace(",",""));(isNaN(e)||e<0)&&(e=0);var t=parseFloat(d.cashTendered.val().replace("$","").replace(",",""));(isNaN(t)||t<0)&&(t=0,d.cashTendered.val(""));var a=t-e;a<0&&(a=0,d.cashTendered.addClass("ui-state-highlight")),$("#txtCashChange").text("$"+a.toFixed(2).toString())}),e&&t&&t.length>0&&(chgRoomList=e,$("table#moveTable").on("change","select",function(){$(this).removeClass("ui-state-error");var t=$(this).val();if(""==t&&(t=0),d.keyDepAmt.length>0&&e[t]&&(0===e[t].key?($("#spnDepAmt").data("amt",""),$("#spnDepAmt").text(""),d.keyDepAmt.val(""),d.keyDepCb.prop("checked",!1),$(".hhk-kdrow").hide()):($("#spnDepAmt").data("amt",e[t].key),$("#spnDepAmt").text("($"+e[t].key+")"),d.keyDepAmt.val(e[t].key),$(".hhk-kdrow").show("fade")),amtPaid()),t>0&&e[t]&&$("#myRescId").length>0){$("#rmChgMsg").text("").hide(),$("#rmDepMessage").text("").hide();var s=$("#myRescId").data("idresc"),i=$("#myRescId").data("pmdl");if(e[s].rate!==e[t].rate&&"b"===i&&$("#rmChgMsg").text("The room rate is different.").show("fade"),e[s].key!==e[t].key){var r="";$("#spnDepMsg").hide(),$("#selDepDisposition").show("fade"),0==e[t].key?"0"!=$("#kdPaid").data("amt")&&(r="There is no deposit for this room.  Set the Deposit Status (above) accordingly."):r="The deposit for this room is $"+e[t].key.toFixed(2).toString(),$("#rmDepMessage").text(r).show("fade")}else $("#selDepDisposition").hide(),$("#spnDepMsg").show("fade")}a.change()}),t.change(),$("#resvChangeDate").datepicker("option","onClose",function(e){$("#rbReplaceRoomnew").prop("checked",!0),""!==e&&getVisitRoomList(s,i,$("#resvChangeDate").val(),t)})),d.adjustBtn.length>0&&(d.adjustBtn.button(),d.adjustBtn.click(function(){getApplyDiscDiag(s,r)})),$("#divPmtMkup").on("click",".invAction",function(e){e.preventDefault(),("del"!=$(this).data("stat")||confirm("Delete this Invoice?"))&&invoiceAction($(this).data("iid"),$(this).data("stat"),e.target.id,"#keysfees",!0)}),$("#txtInvSearch").length>0&&($("#txtInvSearch").keypress(function(e){var t=$(this).val();"13"==e.keyCode&&(""!=t&&isNumber(parseInt(t,10))?$.getJSON("../house/roleSearch.php",{cmd:"filter",basis:"ba",letters:t},function(e){try{e=e[0]}catch(e){return void alert("Parser error - "+e.message)}e&&e.error&&(e.gotopage&&(response(),window.open(e.gotopage)),e.value=e.error),getInvoicee(e,s)}):(alert("Don't press the return key unless you enter an Id."),e.preventDefault()))}),createAutoComplete($("#txtInvSearch"),3,{cmd:"filter",basis:"ba"},function(e){getInvoicee(e,s)},!1)),$("#daystoPay").change(function(){var e=parseInt($(this).val()),t=parseInt($(this).data("vid")),s=parseFloat($("#txtFixedRate").val()),i=parseInt($("#spnNumGuests").text()),r=d.feePayAmt;isNaN(i)&&(i=1),isNaN(s)&&(s=0);var o=parseFloat($("#txtadjAmount").val());isNaN(o)&&(o=0),isNaN(e)?$(this).val(""):e>0&&daysCalculator(e,a.val(),t,s,o,i,0,function(e){r.val(e.toFixed(2).toString()),r.change()})}),amtPaid()}function getVisitRoomList(e,t,a,s){s.prop("disabled",!0),$("#hhk-roomChsrtitle").addClass("hhk-loading"),$("#rmDepMessage").text("").hide();var i={cmd:"chgRoomList",idVisit:e,span:t,chgDate:a,selRescId:s.val()};$.post("ws_ckin.php",i,function(e){var t;s.prop("disabled",!1),$("#hhk-roomChsrtitle").removeClass("hhk-loading");try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)return e.gotopage&&window.open(e.gotopage),void flagAlertMessage(e.error,"error");e.resc&&(chgRoomList=e.resc),e.sel&&(t=$(e.sel),s.children().remove(),t.children().appendTo(s),s.val(e.idResc).change())})}function daysCalculator(e,t,a,s,i,r,o,n){if(e>0){var d={cmd:"rtcalc",vid:a,rid:o,nites:e,rcat:t,fxd:s,adj:i,gsts:r};$.post("ws_ckin.php",d,function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)return e.gotopage&&window.open(e.gotopage),void flagAlertMessage(e.error,"error");if(e.amt){var t=parseFloat(e.amt);(isNaN(t)||t<0)&&(t=0),n(t)}}else alert("Bad Reply from Server")})}}function verifyBalDisp(){return""==$("#selexcpay").val()&&""!=$("#txtOverPayAmt").val()?($("#payChooserMsg").text('Set "Apply To" to the desired overpayment disposition. ').show(),$("#selexcpay").addClass("ui-state-highlight"),$("#pWarnings").text('Set "Apply To" to the desired overpayment disposition.').show(),!1):($("#payChooserMsg").text("").hide(),$("#selexcpay").removeClass("ui-state-highlight"),!0)}function verifyAmtTendrd(){"use strict";if(0===$("#PayTypeSel").length)return!0;if($("#tdCashMsg").hide("fade"),$("#tdInvceeMsg").text("").hide(),"ca"===$("#PayTypeSel").val()){var e=parseFloat($("#totalPayment").val().replace("$","").replace(",","")),t=parseFloat($("#txtCashTendered").val().replace("$","").replace(",","")),a=$("#remtotalPayment");if(a.length>0&&(e=parseFloat(a.val().replace("$","").replace(",",""))),(isNaN(e)||e<0)&&(e=0),(isNaN(t)||t<0)&&(t=0),e>0&&t<=0)return $("#tdCashMsg").text('Enter the amount paid into "Amount Tendered" ').show(),$("#pWarnings").text('Enter the amount paid into "Amount Tendered"').show(),!1;if(e>0&&t<e)return $("#tdCashMsg").text("Amount tendered is not enough ").show("fade"),$("#pWarnings").text("Amount tendered is not enough").show(),!1}else if("in"===$("#PayTypeSel").val()){var s=parseInt($("#txtInvId").val(),10);if(isNaN(s)||s<1)return $("#tdInvceeMsg").text("The Invoicee is missing. ").show("fade"),!1}return!0}function showReceipt(e,t,a,s){var i=$(e),r=$("<div id='print_button' style='margin-left:1em;'>Print</div>"),o={mode:"popup",popClose:!1,popHt:500,popWd:400,popX:200,popY:200,popTitle:a};void 0!==s&&s||(s=550),i.children().remove(),i.append($(t).addClass("ReceiptArea").css("max-width",s+"px")),r.button(),r.click(function(){$(".ReceiptArea").printArea(o),i.dialog("close")}),i.prepend(r),i.dialog("option","title",a),i.dialog("option","buttons",{}),i.dialog("option","width",s),i.dialog("open"),o.popHt=$("#pmtRcpt").height(),o.popWd=$("#pmtRcpt").width()}function reprintReceipt(e,t){$.post("ws_ckin.php",{cmd:"getPrx",pid:e},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error&&(e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,"error")),showReceipt(t,e.receipt,"Receipt Copy")}})}function paymentRedirect(e,t){"use strict";if(e)if(e.hostedError)flagAlertMessage(e.hostedError,"error");else if(e.cvtx)window.location.assign(e.cvtx);else if(e.xfer&&t.length>0){if(t.children("input").remove(),t.prop("action",e.xfer),e.paymentId&&""!=e.paymentId)t.append($('<input type="hidden" name="PaymentID" value="'+e.paymentId+'"/>'));else{if(!e.cardId||""==e.cardId)return void flagAlertMessage("PaymentId and CardId are missing!","error");t.append($('<input type="hidden" name="CardID" value="'+e.cardId+'"/>'))}t.submit()}else e.inctx&&($("#contentDiv").empty().append($("<p>Processing Credit Payment...</p>")),InstaMed.launch(e.inctx),$("#instamed").css("visibility","visible").css("margin-top","50px;"))}function cardOnFile(e,t,a){var s={cmd:"cof",idGuest:e,idGrp:t,pbp:a};$("#tblupCredit").find("input").each(function(){this.checked&&(s[$(this).attr("id")]=$(this).val())}),$.post("ws_ckin.php",s,function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)return e.gotopage&&window.location.assign(e.gotopage),void flagAlertMessage(e.error,"error");e.hostedError&&flagAlertMessage(e.hostedError,"error"),paymentRedirect(e,$("#xform")),e.success&&""!=e.success&&flagAlertMessage(e.success,"success"),e.COFmkup&&""!==e.COFmkup&&($("#tblupCredit").remove(),$("#upCreditfs").append($(e.COFmkup)))}})}function updateCredit(e,t,a,s,i){var r="";a&&""!=a&&(r=" - "+a),$.post("ws_ckin.php",{cmd:"viewCredit",idGuest:e,reg:t,pbp:i},function(a){if(a){try{a=$.parseJSON(a)}catch(e){return void alert("Parser error - "+e.message)}a.error&&(a.gotopage&&window.location.assign(a.gotopage),flagAlertMessage(a.error,"error"));var i={Continue:function(){cardOnFile(e,t,a.pbp),$(this).dialog("close")},Cancel:function(){$(this).dialog("close")}};if(a.success){var o=$("#"+s);o.children().remove(),o.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog"/>').append($(a.success))),o.dialog("option","buttons",i),o.dialog("option","width",400),o.dialog("option","title","Card On File"+r),o.dialog("open")}}})}
