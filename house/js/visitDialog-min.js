/**
 * visitDialog.js
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 */ function setupVisitNotes(e,t){return t.notesViewer({linkId:e,linkType:"visit",newNoteAttrs:{id:"taNewVNote",name:"taNewVNote"},alertMessage:function(e,t){flagAlertMessage(e,t)}}),t}function viewHospitalStay(e,t,a){$.post("ws_resv.php",{cmd:"viewHS",idhs:e},function(i){if(!i){alert("Bad Reply from Server");return}try{i=$.parseJSON(i)}catch(h){alert("Bad JSON Encoding");return}if(i.error){i.gotopage&&window.open(i.gotopage,"_self"),flagAlertMessage(i.error,"error");return}i.success&&(a.empty(),a.append($(i.success)),a.dialog({autoOpen:!0,width:getDialogWidth(1e3),resizable:!0,modal:!0,title:i.title?i.title:"Hospital Details",buttons:{Cancel:function(){$(this).dialog("close")},Save:function(){saveHospitalStay(e,t),$(this).dialog("close")}}}),$("#keysfees").length>0&&$("#keysfees").on("dialogclose",function(e,t){a.dialog("isOpen")&&a.dialog("close")}),createAutoComplete($(".hhk-hsdialog #txtAgentSch"),3,{cmd:"filter",add:"phone",basis:"ra"},getAgent),""===$(".hhk-hsdialog #a_txtLastName").val()&&$(".hhk-hsdialog .hhk-agentInfo").hide(),$(document).on("click","#a_delete",function(){$(".hhk-hsdialog #a_idName").val(""),$(".hhk-hsdialog input.hhk-agentInfo").val(""),$(".hhk-hsdialog .hhk-agentInfo").hide()}),""!==$(".hhk-hsdialog #a_idName").val()?$(".hhk-hsdialog input.hhk-agentInfo.name").attr("readonly","readonly"):$(".hhk-hsdialog input.hhk-agentInfo.name").removeAttr("readonly"),createAutoComplete($(".hhk-hsdialog #txtDocSch"),3,{cmd:"filter",basis:"doc"},getDoc),""===$(".hhk-hsdialog #d_txtLastName").val()&&$(".hhk-hsdialog .hhk-docInfo").hide(),""!==$(".hhk-hsdialog #d_idName").val()?$(".hhk-hsdialog input.hhk-docInfo.name").attr("readonly","readonly"):$(".hhk-hsdialog input.hhk-docInfo.name").removeAttr("readonly"),$(document).on("click","#d_delete",function(){$(".hhk-hsdialog #d_idName").val(""),$(".hhk-hsdialog input.hhk-docInfo").val(""),$(".hhk-hsdialog .hhk-docInfo").hide()}),$(".ckhsdate").datepicker({yearRange:"-01:+01",changeMonth:!0,changeYear:!0,autoSize:!0,dateFormat:"M d, yy"}))})}function saveHospitalStay(e,t){var a=[{name:"cmd",value:"saveHS"},{name:"idhs",value:e},{name:"idv",value:t}],a=a.concat($(".hospital-stay").serializeArray());$.post("ws_resv.php",a,function(e){if(!e){alert("Bad Reply from Server");return}try{e=$.parseJSON(e)}catch(t){alert("Bad JSON Encoding");return}if(e.error){e.gotopage&&window.open(e.gotopage,"_self"),flagAlertMessage(e.error,"error");return}e.success&&flagAlertMessage(e.success,"success")})}var isCheckedOut=!1;function viewVisit(e,t,a,i,h,n,s){"use strict";$.post("ws_ckin.php",{cmd:"visitFees",idVisit:t,idGuest:e,action:h,span:n,ckoutdt:s},function(s){if(s){try{s=$.parseJSON(s)}catch(o){alert("Parser error - "+o.message);return}if(s.error){if(s.gotopage){window.location.assign(s.gotopage);return}flagAlertMessage(s.error,"error");return}var d=$("#keysfees");if(d.children().remove(),d.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(s.success))),d.find(".ckdate").datepicker({yearRange:"-07:+01",changeMonth:!0,changeYear:!0,autoSize:!0,numberOfMonths:1,maxDate:0,dateFormat:"M d, yy"}),d.find(".ckdateFut").datepicker({yearRange:"-01:+01",changeMonth:!0,changeYear:!0,autoSize:!0,numberOfMonths:1,minDate:0,dateFormat:"M d, yy"}),d.css("background-color","#fff"),"ref"===h&&(d.css("background-color","#FEFF9B"),$(".hhk-ckoutDate").prop("disabled",!0)),$(".hhk-extVisitSw").length>0&&($("#extendDays").change(function(){$("#extendDays").removeClass("ui-state-error")}),$("#extendDate").change(function(){$("#rbOlpicker-ext").prop("checked",!0)}),$('input[name="rbOlpicker"]').change(function(){"ext"!==$(this).val()&&$("#extendDate").val("")}),$(".hhk-extVisitSw").change(function(){this.checked?($("#rateChgCB").prop("checked",!1).change().prop("disabled",!0),$(".hhk-extendVisit").show("fade")):($(".hhk-extendVisit").hide("fade"),$("#rateChgCB").prop("disabled",isCheckedOut))}),$(".hhk-extVisitSw").trigger("change")),$("#rateChgCB").length>0){let r=$("#chgRateDate");r.datepicker({changeMonth:!0,changeYear:!0,autoSize:!0,numberOfMonths:1,dateFormat:"M d, yy",maxDate:new Date(s.end),minDate:new Date(s.start)}),r.change(function(){""!==this.value&&r.siblings("input#rbReplaceRate").prop("checked",!0)}),$("input#rbReplaceRate").change(function(){this.checked&&""===r.val()?r.val($.datepicker.formatDate("M d, yy",new Date)):r.val("")}),$("#rateChgCB").change(function(){this.checked?($(".hhk-extVisitSw").prop("checked",!1).change().prop("disabled",!0),$(".changeRateTd").show()):($(".changeRateTd").hide("fade"),$(".hhk-extVisitSw").prop("disabled",isCheckedOut))}),$("#rateChgCB").change()}$("#tblActiveVisit").on("click",".hhk-hospitalstay",function(e){e.preventDefault(),viewHospitalStay($(this).data("idhs"),t,$("#hsDialog"))}),$("#spnExPay").hide(),isCheckedOut=!1;var c=0,l=0,p=0;if($("#spnCfBalDue").length>0&&(c=parseFloat($("#spnCfBalDue").data("rmbal")),l=parseFloat($("#spnCfBalDue").data("vfee")),p=parseFloat($("#spnCfBalDue").data("totbal"))),$("input.hhk-ckoutCB").length>0)$("#tblStays").on("change","input.hhk-ckoutCB",function(){var s=!0,o=1,r=new Date,g={};if(!1===this.checked?$(this).next().val(""):""===$(this).next().val()&&$(this).next().val($.datepicker.formatDate("M d, yy",new Date)),$("input.hhk-ckoutCB").each(function(){if(!1===this.checked)s=!1;else if(""!=$(this).next().val()){var e=new Date($(this).next().val());g[$(this).next().data("gid")]=e.toDateString(),e.getTime()>r.getTime()?($(this).next().val(""),s=!1):e.getTime()>o&&(o=e.getTime())}}),!0===s){isCheckedOut=!0;var r=new Date,u=r.getFullYear()+"-"+r.getMonth()+"-"+r.getDate(),k=new Date(o),f=k.getFullYear()+"-"+k.getMonth()+"-"+k.getDate();if(k.getTime()>r.getTime())return!1;if(u!==f&&"ref"!==h){d.children().remove(),d.dialog("option","buttons",{}),d.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog"/>').append($('<div class="ui-autocomplete-loading" style="width:5em;">Loading</div>'))),viewVisit(e,t,a,i,"ref",n,g);return}$("#rateChgCB").prop("checked",!1).trigger("change").prop("disabled",!0),$(".hhk-extVisitSw").prop("checked",!1).trigger("change").prop("disabled",!0),$("#paymentAdjust").prop("disabled",!0),$(".hhk-kdrow").hide("fade"),$(".hhk-finalPayment").show("fade");var v=parseFloat($("#kdPaid").data("amt"));isNaN(v)&&(v=0),v>0?($("#DepRefundAmount").val((0-v).toFixed(2).toString()),$(".hhk-refundDeposit").show("fade")):($("#DepRefundAmount").val(""),$(".hhk-refundDeposit").hide("fade")),$("#cbDepRefundApply").trigger("change"),p<0?($("#guestCredit").val(c.toFixed(2).toString()),$("#feesCharges").val(""),$(".hhk-RoomCharge").hide(),$(".hhk-GuestCredit").show()):($("#feesCharges").val(c.toFixed(2).toString()),$("#guestCredit").val(""),$(".hhk-GuestCredit").hide(),$(".hhk-RoomCharge").show(),$("#visitFeeCb").length>0&&Math.abs(p)>=l&&$("#visitFeeCb").prop("checked",!0).prop("disabled",!0).trigger("change")),$("input#cbFinalPayment").change()}else if("ref"===h){d.children().remove(),d.dialog("option","buttons",{}),d.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog"/>').append($('<div class="ui-autocomplete-loading" style="width:5em;">Loading</div>'))),viewVisit(e,t,a,i,"",n);return}else isCheckedOut=!1,$(".hhk-finalPayment").hide("fade"),$(".hhk-GuestCredit").hide("fade"),$(".hhk-RoomCharge").hide("fade"),$("#feesCharges").val(""),$("#guestCredit").val(""),$(".hhk-refundDeposit").hide("fade"),$("#DepRefundAmount").val(""),$("input#cbFinalPayment").prop("checked",!1),$("input#cbFinalPayment").change(),$("#cbDepRefundApply").trigger("change"),$("#visitFeeCb").prop("checked",!1).prop("disabled",!1).trigger("change"),$("#rateChgCB").prop("checked",!1).change().prop("disabled",!1),$(".hhk-extVisitSw").prop("checked",!1).change().prop("disabled",!1),$("#paymentAdjust").prop("disabled",!1)}),$("#tblStays").on("change","input.hhk-ckoutDate",function(){""!=$(this).val()?$(this).prev().prop("checked",!0):$(this).prev().prop("checked",!1),$("input.hhk-ckoutCB").change()}),$("#cbCoAll").button().click(function(){$("input.hhk-ckoutCB").each(function(){$(this).prop("checked",!0)}),$("input.hhk-ckoutCB").change()}),$("input.hhk-ckoutCB").change();else{isCheckedOut=!0,$(".hhk-finalPayment").show();var g=parseFloat($("#kdPaid").data("amt"));isNaN(g)?($("#DepRefundAmount").val(""),$(".hhk-refundDeposit").hide("fade")):($("#DepRefundAmount").val((0-g).toFixed(2).toString()),$(".hhk-refundDeposit").show("fade"),$("#cbDepRefundApply").trigger("change")),c<0?($("#guestCredit").val(c.toFixed(2).toString()),$("#feesCharges").val(""),$(".hhk-RoomCharge").hide(),$(".hhk-GuestCredit").show()):($("#feesCharges").val(c.toFixed(2).toString()),$("#guestCredit").val(""),$(".hhk-GuestCredit").hide(),$(".hhk-RoomCharge").show()),d.css("background-color","#F2F2F2")}setupPayments($("#selRateCategory"),t,n,$("#pmtRcpt"),"#keysfees");let u=$("#btnFapp");u.length>0&&(u.button(),u.click(function(){getIncomeDiag(u.data("rid"))})),$("#btnAddGuest").length>0&&($("#btnAddGuest").button(),$("#btnAddGuest").click(function(){window.location.assign("CheckingIn.php?vid="+$(this).data("vid")+"&span="+$(this).data("span")+"&rid="+$(this).data("rid")+"&vstatus="+$(this).data("vstatus"))})),$("#selRateCategory").length>0&&($("#selRateCategory").change(function(){$(this).val()==fixedRate?($(".hhk-fxFixed").show("fade"),$(".hhk-fxAdj").hide("fade")):($(".hhk-fxFixed").hide("fade"),$(".hhk-fxAdj").show("fade"))}),$("#selRateCategory").change()),setupVisitNotes(t,d.find("#visitNoteViewer")),d.dialog("option","buttons",a),d.dialog("option","title",i),d.dialog("option","width",.92*$(window).width()),d.dialog("option","height",$(window).height()),d.dialog("open")}})}function saveFees(e,t,a,i,h){"use strict";let n=[],s=[],o=!1,d={cmd:"saveFees",idGuest:e,idVisit:t,span:a,rtntbl:!0===i?"1":"0",pbp:h};if($("input.hhk-expckout").each(function(){let e=$(this).attr("id").split("_");e.length>0&&(d[e[0]+"["+e[1]+"]"]=$(this).val())}),$("input.hhk-stayckin").each(function(){let e=$(this).attr("id").split("_");e.length>0&&(d[e[0]+"["+e[1]+"]"]=$(this).val())}),$("#undoCkout").length>0&&$("#undoCkout").prop("checked")&&(o=!0),(!isCheckedOut||!1!==verifyBalDisp()||!1!==o)&&!1!==verifyAmtTendrd()){if($(".hhk-extVisitSw").length>0&&$("#extendCb").prop("checked")&&1>$("#extendDays").val()){$("#extendDays").addClass("ui-state-error"),flagAlertMessage("Weekend Leave days must be filled in. ","error");return}if($("input.hhk-ckoutCB").each(function(){if(this.checked){let e=$(this).attr("id").split("_");if(e.length>0){d["stayActionCb["+e[1]+"]"]="on";var t=$("#stayCkOutDate_"+e[1]).datepicker("getDate");if(t){var a=new Date;t.setHours(a.getHours(),a.getMinutes(),0,0)}else t=new Date;$("#stayCkOutHour_"+e[1]).length>0&&(d["stayCkOutHour["+e[1]+"]"]=$("#stayCkOutHour_"+e[1]).val()),d["stayCkOutDate["+e[1]+"]"]=t.toJSON(),n.push($(this).data("nm")+", "+t.toDateString())}}}),$("input.hhk-removeCB").each(function(){if(this.checked){let e=$(this).attr("id").split("_");e.length>0&&(d[e[0]+"["+e[1]+"]"]="on",s.push($(this).data("nm")))}}),n.length>0&&!1===confirm("Check Out:\n"+n.join("\n")+"?")||s.length>0&&!1===confirm("Remove:\n"+s.join("\n")+"?")){$("#keysfees").dialog("close");return}$("#keyDepAmt").removeClass("ui-state-highlight"),d.txtRibbonNote=$("#txtRibbonNote").val(),$("#taNewVNote").length>0&&""!==$("#taNewVNote").val()&&(d.taNewVNote=$("#taNewVNote").val()),$("#noticeToCheckout").length>0&&""!==$("#noticeToCheckout").val()&&(d.noticeToCheckout=$("#noticeToCheckout").val()),$(".hhk-feeskeys").each(function(){if("checkbox"===$(this).attr("type"))!1!==this.checked&&(d[$(this).attr("id")]="on");else if($(this).hasClass("ckdate")){var e=$(this).datepicker("getDate");e?d[$(this).attr("id")]=e.toJSON():d[$(this).attr("id")]=""}else"radio"===$(this).attr("type")?!1!==this.checked&&(d[$(this).attr("id")]=$(this).val()):d[$(this).attr("id")]=$(this).val()}),$("#keysfees").css("background-color","white"),$("#keysfees").empty().append('<div id="hhk-loading-spinner" style="width: 100%; height: 100%; margin-top: 100px; text-align: center"><img src="../images/ui-anim_basic_16x16.gif"><p>Working...</p></div>'),$.post("ws_ckin.php",d,function(e){try{e=$.parseJSON(e)}catch(t){alert("Parser error - "+t.message);return}if(e.error){e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,"error");return}if($("#keysfees").dialog("close"),paymentRedirect(e,$("#xform")),"undefined"!=typeof refreshdTables&&refreshdTables(e),"undefined"!=typeof pageManager){var a={date1:new Date($("#gstDate").val()),date2:new Date($("#gstCoDate").val())};pageManager.doOnDatesChange(a)}e.success&&""!==e.success&&(flagAlertMessage(e.success,"success"),"undefined"!=typeof calendar&&calendar.refetchEvents()),e.warning&&""!==e.warning&&flagAlertMessage(e.warning,"error"),e.receipt&&""!==e.receipt&&showReceipt("#pmtRcpt",e.receipt,"Payment Receipt"),e.invoiceNumber&&""!==e.invoiceNumber&&window.open("ShowInvoice.php?invnum="+e.invoiceNumber)})}}function updateVisitMessage(e,t){$("#h3VisitMsgHdr").text(e),$("#spnVisitMsg").text(t),$("#visitMsg").effect("pulsate")}