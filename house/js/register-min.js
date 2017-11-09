function isNumber(e){"use strict";return!isNaN(parseFloat(e))&&isFinite(e)}function refreshdTables(e){"use strict";e.curres&&$("#divcurres").length>0&&$("#curres").DataTable().ajax.reload(),e.reservs&&$("div#vresvs").length>0&&$("#reservs").DataTable().ajax.reload(),e.waitlist&&$("div#vwls").length>0&&$("#waitlist").DataTable().ajax.reload(),e.unreserv&&$("div#vuncon").length>0&&$("#unreserv").DataTable().ajax.reload(),$("#divdaily").length>0&&$("#daily").DataTable().ajax.reload()}function cgResvStatus(e,t){$.post("ws_ckin.php",{cmd:"rvstat",rid:e,stat:t},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)return e.gotopage&&window.location.assign(e.gotopage),void flagAlertMessage(e.error,!0);e.success&&(flagAlertMessage(e.success,!1),$("#calendar").hhkCalendar("refetchEvents")),refreshdTables(e)}})}function invPay(e,t,a){if(!1!==verifyAmtTendrd()){var i={cmd:"payInv",pbp:t,id:e};$(".hhk-feeskeys").each(function(){if("checkbox"===$(this).attr("type"))!1!==this.checked&&(i[$(this).attr("id")]="on");else if($(this).hasClass("ckdate")){var e=$(this).datepicker("getDate");i[$(this).attr("id")]=e?e.toJSON():""}else"radio"===$(this).attr("type")?!1!==this.checked&&(i[$(this).attr("id")]=this.value):i[$(this).attr("id")]=this.value}),a.dialog("close"),$.post("ws_ckin.php",i,function(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error&&(e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,!0)),paymentReply(e,!1),$("#btnInvGo").click()})}}function invLoadPc(e,t,a){"use strict";var i={"Pay Fees":function(){invPay(t,"register.php",$("div#keysfees"))},Cancel:function(){$(this).dialog("close")}};$.post("ws_ckin.php",{cmd:"showPayInv",id:t,iid:a},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error?(e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,!0)):e.mkup&&($("div#keysfees").children().remove(),$("div#keysfees").append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(e.mkup))),$("div#keysfees .ckdate").datepicker({yearRange:"-01:+01",changeMonth:!0,changeYear:!0,autoSize:!0,numberOfMonths:1,dateFormat:"M d, yy"}),isCheckedOut=!1,setupPayments(e.resc,"","",0,$("#pmtRcpt")),$("#keysfees").dialog("option","buttons",i),$("#keysfees").dialog("option","title","Pay Invoice"),$("#keysfees").dialog("option","width",800),$("#keysfees").dialog("open"))}})}function invSetBill(e,t,a,i,s,n,r){"use strict";var o=$(a),d={Save:function(){var t,a=o.find("#taBillNotes").val();""!=o.find("#txtBillDate").val()&&(t=o.find("#txtBillDate").datepicker("getDate").toJSON()),$.post("ws_resc.php",{cmd:"invSetBill",inb:e,date:t,ele:i,nts:a,ntele:r},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error?(e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,!0)):e.success&&(e.elemt&&e.strDate&&$(e.elemt).text(e.strDate),e.notesElemt&&e.notes&&$(e.notesElemt).text(e.notes),flagAlertMessage(e.success,!1))}}),$(this).dialog("close")},Cancel:function(){$(this).dialog("close")}};o.find("#spnInvNumber").text(e),o.find("#spnBillPayor").text(t),o.find("#txtBillDate").val(s),o.find("#taBillNotes").val(n),o.find("#txtBillDate").datepicker({numberOfMonths:1}),o.dialog("option","buttons",d),o.dialog("option","width",500),o.dialog("open")}function chgRoomCleanStatus(e,t){"use strict";confirm("Change the room status?")&&$.post("ws_resc.php",{cmd:"saveRmCleanCode",idr:e,stat:t},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)return e.gotopage&&window.location.assign(e.gotopage),void flagAlertMessage("Server error - "+e.error,!0);refreshdTables(e),e.msg&&""!=e.msg&&flagAlertMessage(e.msg,!1)}})}function payFee(e,t,a,i){var s={"Show Statement":function(){window.open("ShowStatement.php?vid="+a,"_blank")},"Pay Fees":function(){saveFees(t,a,i,!1,"register.php")},Cancel:function(){$(this).dialog("close")}};viewVisit(t,a,s,"Pay Fees for "+e,"pf",i)}function editPSG(e){var t={Cancel:function(){$(this).dialog("close")}};$.post("ws_ckin.php",{cmd:"viewPSG",psg:e},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,!0);else if(e.markup){var a=$("div#keysfees");a.children().remove(),a.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(e.markup))),a.dialog("option","buttons",t),a.dialog("option","title","View Patient Support Group"),a.dialog("option","width",900),a.dialog("open")}}})}function ckOut(e,t,a,i){var s={"Show Statement":function(){window.open("ShowStatement.php?vid="+a,"_blank")},"Show Registration Form":function(){window.open("ShowRegForm.php?vid="+a,"_blank")},"Check Out":function(){saveFees(t,a,i,!0,"register.php")},Cancel:function(){$(this).dialog("close")}};viewVisit(t,a,s,"Check Out "+e,"co",i)}function editVisit(e,t,a,i){var s={"Show Statement":function(){window.open("ShowStatement.php?vid="+a,"_blank")},"Show Registration Form":function(){window.open("ShowRegForm.php?vid="+a,"_blank")},Save:function(){saveFees(t,a,i,!0,"register.php")},Cancel:function(){$(this).dialog("close")}};viewVisit(t,a,s,"Edit Visit #"+a+"-"+i,"",i)}function getStatusEvent(e,t,a){"use strict";$.post("ws_resc.php",{cmd:"getStatEvent",tp:t,title:a,id:e},function(a){if(a){try{a=$.parseJSON(a)}catch(e){return void alert("Parser error - "+e.message)}if(a.error)a.gotopage&&window.location.assign(a.gotopage),alert("Server error - "+a.error);else if(a.tbl){$("#statEvents").children().remove().end().append($(a.tbl)),$(".ckdate").datepicker({autoSize:!0,dateFormat:"M d, yy"});var i={Save:function(){saveStatusEvent(e,t)},Cancel:function(){$(this).dialog("close")}};$("#statEvents").dialog("option","buttons",i),$("#statEvents").dialog("open")}}})}function saveStatusEvent(e,t){"use strict";$.post("ws_resc.php",$("#statForm").serialize()+"&cmd=saveStatEvent&id="+e+"&tp="+t,function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error&&(e.gotopage&&window.location.assign(e.gotopage),alert("Server error - "+e.error)),e.reload&&1==e.reload&&$("#calendar").hhkCalendar("refetchEvents"),e.msg&&""!=e.msg&&flagAlertMessage(e.msg,!1)}$("#statEvents").dialog("close")})}function cgRoom(e,t,a,i){var s={"Change Rooms":function(){saveFees(t,a,i,!0,"register.php")},Cancel:function(){$(this).dialog("close")}};viewVisit(t,a,s,"Change Rooms for "+e,"cr",i)}function moveVisit(e,t,a,i,s){$.post("ws_ckin.php",{cmd:e,idVisit:t,span:a,sdelta:i,edelta:s},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error?(e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,!0)):e.success&&($("#calendar").hhkCalendar("refetchEvents"),flagAlertMessage(e.success,!1),refreshdTables(e))}})}function getRoomList(e,t){e&&$.post("ws_ckin.php",{cmd:"rmlist",rid:e,x:t},function(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)return e.gotopage&&window.location.assign(e.gotopage),void flagAlertMessage(e.error,!0);if(e.container){var t=$(e.container);$("body").append(t),t.position({my:"top",at:"bottom",of:"#"+e.eid}),$("#selRoom").change(function(){""!=$("#selRoom").val()?(confirm("Change room to "+$("#selRoom option:selected").text()+"?")&&$.post("ws_ckin.php",{cmd:"setRoom",rid:e.rid,idResc:$("#selRoom").val()},function(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)return e.gotopage&&window.location.assign(e.gotopage),void flagAlertMessage(e.error,!0);e.msg&&""!=e.msg&&flagAlertMessage(e.msg,!1),$("#calendar").hhkCalendar("refetchEvents"),refreshdTables(e)}),t.remove()):t.remove()})}})}function checkStrength(e){var t=new RegExp("^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*])(?=.{8,})"),a=new RegExp("^(((?=.*[a-z])(?=.*[A-Z]))|((?=.*[a-z])(?=.*[0-9]))|((?=.*[A-Z])(?=.*[0-9])))(?=.{8,})"),i=!0;return t.test(e.val())?e.removeClass("ui-state-error"):a.test(e.val())?e.removeClass("ui-state-error"):(e.addClass("ui-state-error"),i=!1),i}$(document).ready(function(){"use strict";var e=new Date,t=0;if($.widget("ui.autocomplete",$.ui.autocomplete,{_resizeMenu:function(){var e=this.menu.element;e.outerWidth(1.1*Math.max(e.width("").outerWidth()+1,this.element.outerWidth()))}}),""!==pmtMkup&&$("#paymentMessage").html(pmtMkup).show("pulsate",{},400),$(':input[type="button"], :input[type="submit"]').button(),$.datepicker.setDefaults({yearRange:"-10:+02",changeMonth:!0,changeYear:!0,autoSize:!0,numberOfMonths:2,dateFormat:"M d, yy"}),$("#vstays").on("click",".stpayFees",function(e){e.preventDefault(),$("#divAlert1, #paymentMessage").hide(),payFee($(this).data("name"),$(this).data("id"),$(this).data("vid"),$(this).data("spn"))}),$("#vstays").on("click",".applyDisc",function(e){e.preventDefault(),$("#divAlert1, #paymentMessage").hide(),getApplyDiscDiag($(this).data("vid"),$("#pmtRcpt"))}),$("#vstays, #vresvs, #vwls, #vuncon").on("click",".stupCredit",function(e){e.preventDefault(),$("#divAlert1, #paymentMessage").hide(),updateCredit($(this).data("id"),$(this).data("reg"),$(this).data("name"),"cardonfile")}),$("#vstays").on("click",".stckout",function(e){e.preventDefault(),$("#divAlert1, #paymentMessage").hide(),ckOut($(this).data("name"),$(this).data("id"),$(this).data("vid"),$(this).data("spn"))}),$("#vstays").on("click",".stvisit",function(e){e.preventDefault(),$("#divAlert1, #paymentMessage").hide(),editVisit($(this).data("name"),$(this).data("id"),$(this).data("vid"),$(this).data("spn"))}),$("#vstays").on("click",".hhk-getPSGDialog",function(e){e.preventDefault(),$("#divAlert1, #paymentMessage").hide(),editPSG($(this).data("psg"))}),$("#vstays").on("click",".stchgrooms",function(e){e.preventDefault(),$("#divAlert1, #paymentMessage").hide(),cgRoom($(this).data("name"),$(this).data("id"),$(this).data("vid"),$(this).data("spn"))}),$("#vstays").on("click",".stcleaning",function(e){e.preventDefault(),$("#divAlert1, #paymentMessage").hide(),chgRoomCleanStatus($(this).data("idroom"),$(this).data("clean"))}),$("#vresvs, #vwls, #vuncon").on("click",".resvStat",function(e){e.preventDefault(),$("#divAlert1, #paymentMessage").hide(),cgResvStatus($(this).data("rid"),$(this).data("stat"))}),$.extend($.fn.dataTable.defaults,{dom:'<"top"if>rt<"bottom"lp><"clear">',displayLength:50,lengthMenu:[[25,50,-1],[25,50,"All"]],order:[[3,"asc"]],processing:!0,deferRender:!0}),$("#curres").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=curres",dataSrc:"curres"},drawCallback:function(e){$("#curres .gmenu").menu()},columns:cgCols}),$("#daily").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=daily",dataSrc:"daily"},order:[[0,"asc"]],columns:dailyCols,infoCallback:function(e,t,a,i,s,n){return"Printed on: "+dateRender((new Date).toLocaleString(),"display","ddd, MMM D YYYY, h:mm a")}}),$("#reservs").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=reservs",dataSrc:"reservs"},drawCallback:function(e){$("#reservs .gmenu").menu()},columns:rvCols}),$("#unreserv").length>0&&$("#unreserv").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=unreserv",dataSrc:"unreserv"},drawCallback:function(e){$("#unreserv .gmenu").menu()},columns:rvCols}),$("#waitlist").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=waitlist",dataSrc:"waitlist"},order:[[4,"asc"]],drawCallback:function(e){$("#waitlist .gmenu").menu()},columns:wlCols}),$(".ckdate3").datepicker({onClose:function(e,t){var a=$(this).prop("defaultValue");""!=e&&e!=a&&(changeExptDeparture($(this).data("id"),$(this).data("vid"),e,$(this)),$(this).val($(this).prop("defaultValue")))}}),$("#statEvents").dialog({autoOpen:!1,resizable:!0,width:830,modal:!0,title:"Manage Status Events"}),$("#keysfees").dialog({autoOpen:!1,resizable:!0,modal:!0,close:function(e,t){$("div#submitButtons").show()},open:function(e,t){$("div#submitButtons").hide()}}),$("#keysfees").mousedown(function(e){var t=$(e.target);"pudiv"!==t[0].id&&0===t.parents("#pudiv").length&&$("div#pudiv").remove()}),$("#faDialog").dialog({autoOpen:!1,resizable:!0,width:650,modal:!0,title:"Income Chooser"}),$("#setBillDate").dialog({autoOpen:!1,resizable:!0,modal:!0,title:"Set Invoice Billing Date"}),$("#pmtRcpt").dialog({autoOpen:!1,resizable:!0,width:530,modal:!0,title:"Payment Receipt"}),$("#cardonfile").dialog({autoOpen:!1,resizable:!0,modal:!0,title:"Update Credit Card On File",close:function(e,t){$("div#submitButtons").show()},open:function(e,t){$("div#submitButtons").hide()}}),$(".ckdate").datepicker(),""===$("#txtactstart").val()&&((a=new Date).setTime(a.getTime()-432e6),$("#txtactstart").datepicker("setDate",a)),""===$("#txtfeestart").val()){var a=new Date;a.setTime(a.getTime()-2592e5),$("#txtfeestart").datepicker("setDate",a)}$("#txtsearch").keypress(function(e){var t=$(this).val();"13"==e.keyCode&&(""!==t&&isNumber(parseInt(t,10))?(t>0&&window.location.assign("GuestEdit.php?id="+t),e.preventDefault()):(alert("Don't press the return key unless you enter an Id."),e.preventDefault()))}),createAutoComplete($("#txtsearch"),3,{cmd:"role",mode:"mo",gp:"1"},function(e){var t=e.id;t>0&&window.location.assign("GuestEdit.php?id="+t)},!1);var i=parseInt(viewDays,10);$("#calendar").hhkCalendar({defaultView:"twoweeks",viewDays:i,hospitalSelector:null,theme:!0,contentHeight:30*parseInt(roomCnt),header:{left:"title",center:"goto",right:"refresh,today prev,next"},allDayDefault:!0,lazyFetching:!0,draggable:!1,editable:!0,selectHelper:!0,selectable:!0,unselectAuto:!0,year:e.getFullYear(),month:e.getMonth(),ignoreTimezone:!0,eventSources:[{url:"ws_ckin.php?cmd=register",ignoreTimezone:!0}],select:function(e,t,a,i,s){},eventDrop:function(e,t,a,i,s,n,r,o){$("#divAlert1, #paymentMessage").hide(),e.idVisit>0&&isGuestAdmin&&confirm("Move Visit to a new start date?")&&moveVisit("visitMove",e.idVisit,e.Span,t,t),e.idReservation>0&&isGuestAdmin&&confirm("Move Reservation to a new start date?")&&moveVisit("reservMove",e.idReservation,e.Span,t,t),s()},eventResize:function(e,t,a,i,s,n,r){$("#divAlert1, #paymentMessage").hide(),e.idVisit>0&&isGuestAdmin&&confirm("Move check out date?")&&moveVisit("visitMove",e.idVisit,e.Span,0,t),e.idReservation>0&&isGuestAdmin&&confirm("Move expected end date?")&&moveVisit("reservMove",e.idReservation,e.Span,0,t),i()},eventClick:function(e,t,a){if($("#divAlert1, #paymentMessage").hide(),e.idResc&&e.idResc>0)getStatusEvent(e.idResc,"resc",e.title);else{if(e.idReservation&&e.idReservation>0){if(t.target.classList.contains("hhk-schrm"))return void getRoomList(e.idReservation,t.target.id);window.location.assign("Referral.php?rid="+e.idReservation)}if(!isNaN(parseInt(e.id,10))){var i={"Show Statement":function(){window.open("ShowStatement.php?vid="+e.idVisit,"_blank")},"Show Registration Form":function(){window.open("ShowRegForm.php?vid="+e.idVisit,"_blank")},Save:function(){saveFees(0,e.idVisit,e.Span,!0,"register.php")},Cancel:function(){$(this).dialog("close")}};viewVisit(0,e.idVisit,i,"Edit Visit #"+e.idVisit+"-"+e.Span,"",e.Span)}}},eventRender:function(e,a){return void 0==t||0===t||e.idAssoc==t||e.idHosp==t||0==e.idHosp}}),$(document).mousedown(function(e){var t=$(e.target);"pudiv"!==t[0].id&&0===t.parents("#pudiv").length&&$("div#pudiv").remove()}),$(".spnHosp").length>0&&$(".spnHosp").click(function(){$(".spnHosp").css("border","solid 1px black").css("font-size","100%"),t=parseInt($(this).data("id"),10),isNaN(t)&&(t=0),$("#calendar").hhkCalendar("rerenderEvents"),$(this).css("border","solid 3px black").css("font-size","120%")}),$("#btnActvtyGo").click(function(){$("#divAlert1, #paymentMessage").hide();var e=$("#txtactstart").datepicker("getDate");if(null===e)return $("#txtactstart").addClass("ui-state-highlight"),void flagAlertMessage("Enter start date",!0);$("#txtactstart").removeClass("ui-state-highlight");var t=$("#txtactend").datepicker("getDate");null===t&&(t=new Date);var a={cmd:"actrpt",start:e.toJSON(),end:t.toJSON()};$("#cbVisits").prop("checked")&&(a.visit="on"),$("#cbReserv").prop("checked")&&(a.resv="on"),$("#cbHospStay").prop("checked")&&(a.hstay="on"),$.post("ws_resc.php",a,function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error?(e.gotopage&&window.open(e.gotopage,"_self"),flagAlertMessage(e.error,!0)):e.success&&($("#rptdiv").remove(),$("#vactivity").append($('<div id="rptdiv"/>').append($(e.success))),$(".hhk-viewvisit").css("cursor","pointer"),$("#rptdiv").on("click",".hhk-viewvisit",function(){if($(this).data("visitid")){var e=$(this).data("visitid").split("_");if(2!==e.length)return;var t={Save:function(){saveFees(0,e[0],e[1])},Cancel:function(){$(this).dialog("close")}};viewVisit(0,e[0],t,"View Visit","n",e[1])}else $(this).data("reservid")&&window.location.assign("Referral.php?id="+$(this).data("reservid"))}))}})}),$("#btnFeesGo").click(function(){$("#divAlert1, #paymentMessage").hide();var e=$("#txtfeestart").datepicker("getDate");if(null===e)return $("#txtfeestart").addClass("ui-state-highlight"),void flagAlertMessage("Enter start date",!0);$("#txtfeestart").removeClass("ui-state-highlight");var t=$("#txtfeeend").datepicker("getDate");null===t&&(t=new Date);var a=$("#selPayStatus").val()||[],i=$("#selPayType").val()||[],s={cmd:"actrpt",start:e.toJSON(),end:t.toJSON(),st:a,pt:i};!1!==$("#fcbdinv").prop("checked")&&(s.sdinv="on"),s.fee="on",$.post("ws_resc.php",s,function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error?(e.gotopage&&window.open(e.gotopage,"_self"),flagAlertMessage(e.error,!0)):e.success&&($("#rptfeediv").remove(),$("#vfees").append($('<div id="rptfeediv"/>').append($(e.success))),$("#feesTable").dataTable({columnDefs:[{targets:8,type:"date",render:function(e,t,a){return dateRender(e,t)}}],dom:'<"top"if>rt<"bottom"lp><"clear">',displayLength:50,lengthMenu:[[25,50,-1],[25,50,"All"]]}),$("#rptfeediv").on("click",".invAction",function(e){invoiceAction($(this).data("iid"),"view",e.target.id)}),$("#rptfeediv").on("click",".hhk-voidPmt",function(){var e=$(this);"Saving..."!=e.val()&&confirm("Void/Reverse?")&&(e.val("Saving..."),sendVoidReturn(e.attr("id"),"rv",e.data("pid")))}),$("#rptfeediv").on("click",".hhk-voidRefundPmt",function(){var e=$(this);"Saving..."!=e.val()&&confirm("Void this Return?")&&(e.val("Saving..."),sendVoidReturn(e.attr("id"),"vr",e.data("pid")))}),$("#rptfeediv").on("click",".hhk-returnPmt",function(){var e=$(this);if("Saving..."!=e.val()){var t=parseFloat($(this).data("amt"));confirm("Return $"+t.toFixed(2).toString()+"?")&&(e.val("Saving..."),sendVoidReturn(e.attr("id"),"r",e.data("pid"),t))}}),$("#rptfeediv").on("click",".hhk-deleteWaive",function(){var e=$(this);"Deleting..."!=e.val()&&confirm("Delete this House payment?")&&(e.val("Deleting..."),sendVoidReturn(e.attr("id"),"d",e.data("ilid"),e.data("iid")))}),$("#rptfeediv").on("click",".pmtRecpt",function(){reprintReceipt($(this).data("pid"),"#pmtRcpt")}))}})}),$("#btnInvGo").click(function(){var e={cmd:"actrpt",st:["up"],inv:"on"};$.post("ws_resc.php",e,function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error?(e.gotopage&&window.open(e.gotopage,"_self"),flagAlertMessage(e.error,!0)):e.success&&($("#rptInvdiv").remove(),$("#vInv").append($('<div id="rptInvdiv" style="min-height:500px;"/>').append($(e.success))),$("#rptInvdiv .gmenu").menu(),$("#rptInvdiv").on("click",".invLoadPc",function(e){e.preventDefault(),$("#divAlert1, #paymentMessage").hide(),invLoadPc($(this).data("name"),$(this).data("id"),$(this).data("iid"))}),$("#rptInvdiv").on("click",".invSetBill",function(e){e.preventDefault(),$("#divAlert1, #paymentMessage").hide(),invSetBill($(this).data("inb"),$(this).data("name"),"div#setBillDate","#trBillDate"+$(this).data("inb"),$("#trBillDate"+$(this).data("inb")).text(),$("#divInvNotes"+$(this).data("inb")).text(),"#divInvNotes"+$(this).data("inb"))}),$("#rptInvdiv").on("click",".invAction",function(e){e.preventDefault(),$("#divAlert1, #paymentMessage").hide(),("del"!=$(this).data("stat")||confirm("Delete this Invoice?"))&&("vem"!==$(this).data("stat")?(invoiceAction($(this).data("iid"),$(this).data("stat"),e.target.id),$("#rptInvdiv .gmenu").menu("collapse")):window.open("ShowInvoice.php?invnum="+$(this).data("inb")))}),$("#InvTable").dataTable({columnDefs:[{targets:[2,4],type:"date",render:function(e,t,a){return dateRender(e,t)}}],dom:'<"top"if>rt<"bottom"lp><"clear">',displayLength:50,lengthMenu:[[20,50,100,-1],[20,50,100,"All"]],order:[[1,"asc"]]}))}})}),$("#btnPrintRegForm").click(function(){window.open($(this).data("page")+"?d="+$("#regckindate").val(),"_blank")}),$("#btnPrintWL").click(function(){window.open($(this).data("page")+"?d="+$("#regwldate").val(),"_blank")}),$("#btnPrtDaily").button().click(function(){$("#divdaily").printArea()}),$("#btnRefreshDaily").button().click(function(){$("#daily").DataTable().ajax.reload()}),""!==rctMkup&&showReceipt("#pmtRcpt",rctMkup,"Payment Receipt"),$("#version").click(function(){$("div#dchgPw").find("input").removeClass("ui-state-error").val(""),$("#pwChangeErrMsg").text(""),$("#dchgPw").dialog("option","title","Change Your Password"),$("#dchgPw").dialog("open"),$("#txtOldPw").focus()}),$("div#dchgPw").on("change","input",function(){$(this).removeClass("ui-state-error"),$("#pwChangeErrMsg").text("")}),$("#dchgPw").dialog({autoOpen:!1,width:450,resizable:!0,modal:!0,buttons:{Save:function(){var e,t,a=$("#txtOldPw"),i=$("#txtNewPw1"),s=$("#txtNewPw2"),n=$("#pwChangeErrMsg");if(""==a.val())return a.addClass("ui-state-error"),a.focus(),void n.text("Enter your old password");if(!1===checkStrength(i))return i.addClass("ui-state-error"),n.text("Password must have 8 characters including at least one uppercase and one lower case alphabetical character and one number."),void i.focus();if(i.val()===s.val()){if(a.val()==i.val())return i.addClass("ui-state-error"),n.text("The new password must be different from the old password"),void i.focus();e=hex_md5(hex_md5(a.val())+challVar),t=hex_md5(i.val()),a.val(""),i.val(""),s.val(""),$.post("ws_admin.php",{cmd:"chgpw",old:e,newer:t},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error?(e.gotopage&&window.open(e.gotopage,"_self"),flagAlertMessage(e.error,!0)):e.success?($("#dchgPw").dialog("close"),flagAlertMessage(e.success,!1)):e.warning&&$("#pwChangeErrMsg").text(e.warning)}})}else n.text("New passwords do not match")},Cancel:function(){$(this).dialog("close")}}}),$("#mainTabs").tabs({beforeActivate:function(e,t){"liInvoice"===t.newTab.prop("id")&&$("#btnInvGo").click()},activate:function(e,t){"liCal"===t.newTab.prop("id")&&$("#calendar").hhkCalendar("render")}}),$("#mainTabs").show(),$("#mainTabs").tabs("option","active",defaultTab),$("#calendar").hhkCalendar("render")});
