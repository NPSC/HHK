/**
 * guestload.js
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
*/ function isNumber(e){return!isNaN(parseFloat(e))&&isFinite(e)}var dtCols=[{targets:[0],title:"Date",data:"Date",render:function(e,t){return dateRender(e,t,dateFormat)}},{targets:[1],title:"Type",searchable:!1,sortable:!1,data:"Type"},{targets:[2],title:"Sub-Type",searchable:!1,sortable:!1,data:"Sub-Type"},{targets:[3],title:"User",searchable:!1,sortable:!0,data:"User"},{targets:[4],visible:!1,data:"Id"},{targets:[5],title:"Log Text",sortable:!1,data:"Log Text"}];function relationReturn(e){var t=$.parseJSON(e);if(t.error)t.gotopage&&window.open(t.gotopage,"_self"),flagAlertMessage(t.error,"error");else if(t.success){if(t.rc&&t.markup){var i=$("#acm"+t.rc);i.children().remove();var a=$(t.markup);i.append(a.children())}flagAlertMessage(t.success,"success")}}function setupPsgNotes(e,t){return t.notesViewer({linkId:e,linkType:"psg",newNoteAttrs:{id:"psgNewNote",name:"psgNewNote"},alertMessage:function(e,t){flagAlertMessage(e,t)}}),t}function manageRelation(e,t,i,a){$.post("ws_admin.php",{id:e,rId:t,rc:i,cmd:a},relationReturn)}function paymentRefresh(){var e=$("#psgList").tabs("option","active");$("#psgList").tabs("load",e)}$(document).ready(function(){"use strict";var e,t,i,a,n=memberData,s=1,o="../admin/ws_gen.php?cmd=chglog&vw=vguest_audit_log&uid="+n.id;if($("#divFuncTabs").tabs({collapsible:!0}),$("#vIncidentContent").incidentViewer({guestLabel:n.guestLabel,visitorLabel:n.visitorLabel,guestId:n.id,psgId:n.idPsg,alertMessage:function(e,t){flagAlertMessage(e,t)}}),useDocUpload&&$("#vDocsContent").docUploader({visitorLabel:n.visitorLabel,guestId:n.id,psgId:n.idPsg,alertMessage:function(e,t){flagAlertMessage(e,t)}}),$(".btnTextGuest").smsDialog({guestId:n.id}),$("#submit").dialog({autoOpen:!1,resizable:!1,width:getDialogWidth(300),modal:!0,buttons:{Exit:function(){$(this).dialog("close")}}}),$("#keysfees").dialog({autoOpen:!1,resizable:!0,modal:!0,close:function(){$("div#submitButtons").show()},open:function(){$("div#submitButtons").hide()}}),$("#pmtRcpt").dialog({autoOpen:!1,resizable:!0,modal:!0,title:"Payment Receipt"}),$("#faDialog").dialog({autoOpen:!1,resizable:!0,width:getDialogWidth(600),modal:!0,title:"Income Chooser"}),""!==rctMkup&&showReceipt("#pmtRcpt",rctMkup),""!==pmtMkup&&$("#paymentMessage").html(pmtMkup).show(),$(".hhk-view-visit").click(function(e){var t=$(this).data("vid"),i=$(this).data("gid"),a=$(this).data("span");!$(e.target).hasClass("hhk-hospitalstay")&&(viewVisit(i,t,{"Show Statement":function(){window.open("ShowStatement.php?vid="+t,"_blank")},"Show Registration Form":function(){window.open("ShowRegForm.php?vid="+t+"&span="+a,"_blank")},Save:function(){saveFees(i,t,a,!1,"GuestEdit.php?id="+i+"&psg="+n.idPsg)},Cancel:function(){$(this).dialog("close")}},"Edit Visit #"+t+"-"+a,"",a),$("#divAlert1").hide())}),$("#resvAccordion").accordion({heightStyle:"content",collapsible:!0,active:!1,icons:!1}),$("div.hhk-relations").each(function(){var e=$(this).attr("name");$(this).on("click","td.hhk-deletelink",function(){n.id>0&&confirm($(this).attr("title")+"?")&&manageRelation(n.id,$(this).attr("name"),e,"delRel")}),$(this).on("click","td.hhk-newlink",function(){if(n.id>0){var t=$(this).attr("title");$("#hdnRelCode").val(e),$("#submit").dialog("option","title",t),$("#submit").dialog("open")}})}),$("#cbNoVehicle").change(function(){this.checked?$("#tblVehicle").hide():$("#tblVehicle").show()}),$("#cbNoVehicle").change(),$("#btnNextVeh, #exAll, #exNone").button(),$("#btnNextVeh").click(function(){$("#trVeh"+s).show("fade"),++s>4&&$("#btnNextVeh").hide("fade")}),$("#divNametabs").tabs({beforeActivate:function(t,i){"chglog"!==i.newTab.prop("id")||e||(e=$("#dataTbl").dataTable({columnDefs:dtCols,serverSide:!0,processing:!0,deferRender:!0,language:{search:"Search Log Text:"},sorting:[[0,"desc"]],displayLength:25,lengthMenu:[[25,50,100,-1],[25,50,100,"All"]],Dom:'<"top"ilf>rt<"bottom"ip>',ajax:{url:o}}))},collapsible:!0}),$("#btnSubmit, #btnReset, #btnCred").button(),$("#phEmlTabs").tabs(),$("#emergTabs").tabs(),$("#addrsTabs").tabs(),$("#InsTabs").tabs(),i=$("#psgList").tabs({collapsible:!0,beforeActivate:function(e,i){i.newPanel.length>0&&("fin"===i.newTab.prop("id")&&(getIncomeDiag(0,n.idReg),e.preventDefault()),"lipsg"!==i.newTab.prop("id")||t||(t=setupPsgNotes(n.idPsg,$("#psgNoteViewer"))))},load:function(e,t){"pmtsTable"===t.tab.prop("id")&&paymentsTable("feesTable","rptfeediv",paymentRefresh),"stmtTab"===t.tab.prop("id")&&$("#stmtDiv textarea.hhk-autosize").trigger("input")}}),n.psgOnly&&i.tabs("disable"),i.tabs("enable",psgTabIndex),i.tabs("option","active",psgTabIndex),$("#cbnoReturn").change(function(){this.checked?$("#selnoReturn").show():$("#selnoReturn").hide()}),$("#cbnoReturn").change(),0===n.id)$("#divFuncTabs").tabs("option","disabled",[2,3,4]),$("#phEmlTabs").tabs("option","active",1),$("#phEmlTabs").tabs("option","disabled",[0]);else{var r=parseInt($("#addrsTabs").children("ul").data("actidx"),10);isNaN(r)&&(r=0),$("#addrsTabs").tabs("option","active",r)}if($.datepicker.setDefaults({yearRange:"-0:+02",changeMonth:!0,changeYear:!0,autoSize:!0,numberOfMonths:1,dateFormat:"M d, yy"}),$(".ckdate").datepicker({yearRange:"-02:+03"}),$(".ckbdate").datepicker({yearRange:"-99:+00",changeMonth:!0,changeYear:!0,autoSize:!0,maxDate:0,dateFormat:"M d, yy",showButtonPanel:n.datePickerButtons,beforeShow:function(e){setTimeout(function(){var t=$(e).datepicker("widget").find(".ui-datepicker-buttonpane");t.empty(),$("<button>",{text:"Minor",click:function(){var t=$(e),i=$.datepicker._getInst(t[0]);i.input.val("Minor");var a=$.datepicker._get(i,"onSelect");a?a.apply(i.input?i.input[0]:null,["Minor",i]):i.input&&i.input.trigger("change"),i.inline?$.datepicker._updateDatepicker(i):($.datepicker._hideDatepicker(),$.datepicker._lastInput=i.input[0],"object"!=typeof i.input[0]&&i.input.trigger("focus"),$.datepicker._lastInput=null)}}).appendTo(t).addClass("ui-datepicker-clear ui-state-default ui-priority-primary ui-corner-all")},1)},onChangeMonthYear:function(e,t,i){setTimeout(function(){var e=$(i).datepicker("widget").find(".ui-datepicker-buttonpane");e.empty(),$("<button>",{text:"Minor",click:function(){var e=$(i.input),t=$.datepicker._getInst(e[0]);t.input.val("Minor");var a=$.datepicker._get(t,"onSelect");a?a.apply(t.input?t.input[0]:null,["Minor",t]):t.input&&t.input.trigger("change"),t.inline?$.datepicker._updateDatepicker(t):($.datepicker._hideDatepicker(),$.datepicker._lastInput=t.input[0],"object"!=typeof t.input[0]&&t.input.trigger("focus"),$.datepicker._lastInput=null)}}).appendTo(e).addClass("ui-datepicker-clear ui-state-default ui-priority-primary ui-corner-all")},1)}}),$("#cbLastConfirmed").change(function(){$(this).prop("checked")?$("#txtLastConfirmed").datepicker("setDate","+0"):$("#txtLastConfirmed").val($("#txtLastConfirmed").prop("defaultValue"))}),$("#txtLastConfirmed").change(function(){$("#txtLastConfirmed").val()==$("#txtLastConfirmed").prop("defaultValue")?$("#cbLastConfirmed").prop("checked",!1):$("#cbLastConfirmed").prop("checked",!0)}),$(".checklistTbl").on("change",".hhk-checkboxlist",function(){$(this).prop("checked")?($("#date"+$(this).data("code")).datepicker("setDate","+0"),$("#disp"+$(this).data("code")).show()):($("#date"+$(this).data("code")).val($("#date"+$(this).data("code")).prop("defaultValue")),$("#disp"+$(this).data("code")).hide())}),verifyAddrs("div#nameTab, div#hospitalSection"),addrPrefs(n),createZipAutoComplete($("input.hhk-zipsearch"),"ws_admin.php",a),$("#btnSubmit").click(function(){if("Saving>>>>"===$(this).val())return!1;$(this).val("Saving>>>>")}),$("#txtsearch").keypress(function(e){var t=$(this).val();"13"==e.keyCode&&(""!=t&&isNumber(parseInt(t,10))?(t>0&&window.location.assign("GuestEdit.php?id="+t),e.preventDefault()):(alert("Don't press the return key unless you enter an Id."),e.preventDefault()))}),$("#cbdeceased").change(function(){$(this).prop("checked")?$("#disp_deceased").show():$("#disp_deceased").hide()}),$("#cbbackgroundcheck").change(function(){$(this).prop("checked")?($("#txtBackgroundCheckDate").datepicker("setDate","+0"),$("#disp_backgroundcheck").show()):($("#txtBackgroundCheckDate").val(""),$("#disp_backgroundcheck").hide())}),$("select.hhk-multisel").each(function(){$(this).multiselect({selectedList:3})}),createRoleAutoComplete($("#txtsearch"),3,{cmd:"guest"},function(e){e.id>0&&window.location.assign("GuestEdit.php?id="+e.id)},!1),createRoleAutoComplete($("#txtMRNsearch"),3,{cmd:"mrn"},function(e){e.id>0&&window.location.assign("GuestEdit.php?id="+e.id)},!1),createRoleAutoComplete($("#txtPhsearch"),5,{cmd:"phone"},function(e){e.id>0&&window.location.assign("GuestEdit.php?id="+e.id)},!1),createAutoComplete($("#txtRelSch"),3,{cmd:"srrel",basis:$("#hdnRelCode").val(),id:n.id},function(e){$.post("ws_admin.php",{rId:e.id,id:n.id,rc:$("#hdnRelCode").val(),cmd:"newRel"},relationReturn)}),""!==resultMessage&&flagAlertMessage(resultMessage,"alert"),$("input.hhk-check-button").click(function(){"exAll"===$(this).prop("id")?$("input.hhk-ex").prop("checked",!0):$("input.hhk-ex").prop("checked",!1)}),$("#divFuncTabs").show(),$(".hhk-showonload").show(),$("#txtsearch").focus(),$(document).find("bfh-states").each(function(){$(this).data("dirrty-initial-value",$(this).data("state"))}),$(document).find("bfh-country").each(function(){$(this).data("dirrty-initial-value",$(this).data("country"))}),$("#btnCred").click(function(){cardOnFile($(this).data("id"),$(this).data("idreg"),"GuestEdit.php?id="+$(this).data("id")+"&psg="+n.idPsg,$(this).data("indx"))}),setupCOF($("#trvdCHNameg"),$("#btnCred").data("indx")),$("#keysfees").mousedown(function(e){var t=$(e.target);"pudiv"!==t[0].id&&0===t.parents("#pudiv").length&&$("div#pudiv").remove()}),$("#form1").dirrty(),$("#btnActvtyGo").button().click(function(){$(".hhk-alert").hide();var e=$("#txtactstart").datepicker("getDate");if(null===e){$("#txtactstart").addClass("ui-state-highlight"),flagAlertMessage("Enter start date","alert");return}$("#txtactstart").removeClass("ui-state-highlight");var t=$("#txtactend").datepicker("getDate");null===t&&(t=new Date);var i={cmd:"actrpt",start:e.toLocaleDateString(),end:t.toLocaleDateString(),psg:n.idPsg};$("#cbVisits").prop("checked")&&(i.visit="on"),$("#cbReserv").prop("checked")&&(i.resv="on"),$("#cbHospStay").prop("checked")&&(i.hstay="on"),$.post("ws_resc.php",i,function(e){if(e){try{e=$.parseJSON(e)}catch(t){alert("Parser error - "+t.message);return}e.error?(e.gotopage&&window.open(e.gotopage,"_self"),flagAlertMessage(e.error,"error")):e.success&&($("#activityLog").remove(),$("#vvisitLog").append($('<div id="activityLog"/>').append($(e.success))),$(".hhk-viewvisit").css("cursor","pointer"),$("#activityLog").on("click",".hhk-viewvisit",function(){if($(this).data("visitid")){var e=$(this).data("visitid").split("_");2===e.length&&viewVisit(0,e[0],{Save:function(){saveFees(0,e[0],e[1])},Cancel:function(){$(this).dialog("close")}},"View Visit","n",e[1])}else $(this).data("reservid")&&window.location.assign("Reserve.php?rid="+$(this).data("reservid"))}))}})}),showGuestPhoto||useDocUpload){var c=window.uploader;$(document).on("click",".upload-guest-photo",function(){$(c.container).removeClass().addClass("uppload-container"),c.updatePlugins(e=>[]),c.updateSettings({maxSize:[500,500],customClass:"guestPhotouploadContainer",uploader:function e(t){return new Promise(function(e,i){var a=new FormData;a.append("cmd","putguestphoto"),a.append("guestId",n.id),a.append("guestPhoto",t),$.ajax({type:"POST",url:"../house/ws_resc.php",dataType:"json",data:a,contentType:!1,processData:!1,success:function(t){t.error?i(t.error):(e("success"),$("#hhk-guest-photo").css("background-image","url(../house/ws_resc.php?cmd=getguestphoto&guestId="+n.id+"r&x="+new Date().getTime()+")"),$(".delete-guest-photo").show()),c.navigate("local")},error:function(e){i(e)}})})}});var e=new Upploader.Local({maxFileSize:5e6,mimeTypes:["image/jpeg","image/png"]});window.camera=new Upploader.Camera,c.use([e,new Upploader.Crop({aspectRatio:1}),window.camera]),c.open()}),c.on("open",function(){1==c.effects.length?$(c.container).find(".effects-tabs").hide():$(c.container).find(".effects-tabs").show()}),c.on("close",function(){c.navigate("local");var e=c.services.filter(e=>"camera"==e.name);1==e.length&&e[0].stop()}),$(document).on("click","#hhk-guest-photo",function(e){e.preventDefault()}),$(".hhk-visitdialog #hhk-guest-photo").on({mouseenter:function(){$(this).find("#hhk-guest-photo-actions").show(),$(this).find("#hhk-guest-photo img").fadeTo(100,.5)},mouseleave:function(){$(this).find("#hhk-guest-photo-actions").hide(),$(this).find("#hhk-guest-photo img").fadeTo(100,1)}}),$(".delete-guest-photo").on("click",function(){confirm("Really Delete this photo?")&&$.ajax({type:"POST",url:"../house/ws_resc.php",dataType:"json",data:{cmd:"deleteguestphoto",guestId:n.id},success:function(e){if(e.error){e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage("Server error - "+e.error,"error");return}$("#hhk-guest-photo").css("background-image","url(../house/ws_resc.php?cmd=getguestphoto&guestId="+n.id+"&rx="+new Date().getTime()+")")},error:function(e){flagAlertMessage("AJAX error - "+e)}})})}});