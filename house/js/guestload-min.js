/**
 * guestload.js
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
*/ function isNumber(t){return!isNaN(parseFloat(t))&&isFinite(t)}var dtCols=[{targets:[0],title:"Date",data:"Date",render:function(t,e){return dateRender(t,e,dateFormat)}},{targets:[1],title:"Type",searchable:!1,sortable:!1,data:"Type"},{targets:[2],title:"Sub-Type",searchable:!1,sortable:!1,data:"Sub-Type"},{targets:[3],title:"User",searchable:!1,sortable:!0,data:"User"},{targets:[4],visible:!1,data:"Id"},{targets:[5],title:"Log Text",sortable:!1,data:"Log Text"}];function relationReturn(t){var e=$.parseJSON(t);if(e.error)e.gotopage&&window.open(e.gotopage,"_self"),flagAlertMessage(e.error,"error");else if(e.success){if(e.rc&&e.markup){var i=$("#acm"+e.rc);i.children().remove();var a=$(e.markup);i.append(a.children())}flagAlertMessage(e.success,"success")}}function setupPsgNotes(t,e){return e.notesViewer({linkId:t,linkType:"psg",newNoteAttrs:{id:"psgNewNote",name:"psgNewNote"},alertMessage:function(t,e){flagAlertMessage(t,e)}}),e}function manageRelation(t,e,i,a){$.post("ws_admin.php",{id:t,rId:e,rc:i,cmd:a},relationReturn)}function paymentRefresh(){var t=$("#psgList").tabs("option","active");$("#psgList").tabs("load",t)}$(document).ready(function(){"use strict";var t,e,i,a,n=memberData,s=1,o="../admin/ws_gen.php?cmd=chglog&vw=vguest_audit_log&uid="+n.id;if($("#divFuncTabs").tabs({collapsible:!0}),$("#vIncidentContent").incidentViewer({guestLabel:n.guestLabel,visitorLabel:n.visitorLabel,guestId:n.id,psgId:n.idPsg,alertMessage:function(t,e){flagAlertMessage(t,e)}}),useDocUpload&&$("#vDocsContent").docUploader({visitorLabel:n.visitorLabel,guestId:n.id,psgId:n.idPsg,alertMessage:function(t,e){flagAlertMessage(t,e)}}),$(".btnTextGuest").smsDialog({guestId:n.id}),$("#submit").dialog({autoOpen:!1,resizable:!1,width:getDialogWidth(300),modal:!0,buttons:{Exit:function(){$(this).dialog("close")}}}),$("#keysfees").dialog({autoOpen:!1,resizable:!0,modal:!0,close:function(){$("div#submitButtons").show()},open:function(){$("div#submitButtons").hide()}}),$("#pmtRcpt").dialog({autoOpen:!1,resizable:!0,modal:!0,title:"Payment Receipt"}),$("#faDialog").dialog({autoOpen:!1,resizable:!0,width:getDialogWidth(600),modal:!0,title:"Income Chooser"}),""!==rctMkup&&showReceipt("#pmtRcpt",rctMkup),""!==pmtMkup&&$("#paymentMessage").html(pmtMkup).show(),$(".hhk-view-visit").click(function(t){var e=$(this).data("vid"),i=$(this).data("gid"),a=$(this).data("span");!$(t.target).hasClass("hhk-hospitalstay")&&(viewVisit(i,e,{"Show Statement":function(){window.open("ShowStatement.php?vid="+e,"_blank")},"Show Registration Form":function(){window.open("ShowRegForm.php?vid="+e+"&span="+a,"_blank")},Save:function(){saveFees(i,e,a,!1,"GuestEdit.php?id="+i+"&psg="+n.idPsg)},Cancel:function(){$(this).dialog("close")}},"Edit Visit #"+e+"-"+a,"",a),$("#divAlert1").hide())}),$("#resvAccordion").accordion({heightStyle:"content",collapsible:!0,active:!1,icons:!1}),$("div.hhk-relations").each(function(){var t=$(this).attr("name");$(this).on("click","td.hhk-deletelink",function(){n.id>0&&confirm($(this).attr("title")+"?")&&manageRelation(n.id,$(this).attr("name"),t,"delRel")}),$(this).on("click","td.hhk-newlink",function(){if(n.id>0){var e=$(this).attr("title");$("#hdnRelCode").val(t),$("#submit").dialog("option","title",e),$("#submit").dialog("open")}})}),$("#cbNoVehicle").change(function(){this.checked?$("#tblVehicle").hide():$("#tblVehicle").show()}),$("#cbNoVehicle").change(),$("#btnNextVeh, #exAll, #exNone").button(),$("#btnNextVeh").click(function(){$("#trVeh"+s).show("fade"),++s>4&&$("#btnNextVeh").hide("fade")}),$("#divNametabs").tabs({beforeActivate:function(e,i){"chglog"!==i.newTab.prop("id")||t||(t=$("#dataTbl").dataTable({columnDefs:dtCols,serverSide:!0,processing:!0,deferRender:!0,language:{search:"Search Log Text:"},sorting:[[0,"desc"]],displayLength:25,lengthMenu:[[25,50,100,-1],[25,50,100,"All"]],Dom:'<"top"ilf>rt<"bottom"ip>',ajax:{url:o}}))},collapsible:!0}),$("#btnSubmit, #btnReset, #btnCred").button(),$("#phEmlTabs").tabs(),$("#emergTabs").tabs(),$("#addrsTabs").tabs(),$("#InsTabs").tabs(),i=$("#psgList").tabs({collapsible:!0,beforeActivate:function(t,i){i.newPanel.length>0&&("fin"===i.newTab.prop("id")&&(getIncomeDiag(0,n.idReg),t.preventDefault()),"lipsg"!==i.newTab.prop("id")||e||(e=setupPsgNotes(n.idPsg,$("#psgNoteViewer"))))},load:function(t,e){"pmtsTable"===e.tab.prop("id")&&paymentsTable("feesTable","rptfeediv",paymentRefresh),"stmtTab"===e.tab.prop("id")&&$("#stmtDiv textarea.hhk-autosize").trigger("input")}}),n.psgOnly&&i.tabs("disable"),i.tabs("enable",psgTabIndex),i.tabs("option","active",psgTabIndex),$("#cbnoReturn").change(function(){this.checked?$("#selnoReturn").show():$("#selnoReturn").hide()}),$("#cbnoReturn").change(),0===n.id)$("#divFuncTabs").tabs("option","disabled",[2,3,4]),$("#phEmlTabs").tabs("option","active",1),$("#phEmlTabs").tabs("option","disabled",[0]);else{var r=parseInt($("#addrsTabs").children("ul").data("actidx"),10);isNaN(r)&&(r=0),$("#addrsTabs").tabs("option","active",r)}if($.datepicker.setDefaults({yearRange:"-0:+02",changeMonth:!0,changeYear:!0,autoSize:!0,numberOfMonths:1,dateFormat:"M d, yy"}),$(".ckdate").datepicker({yearRange:"-02:+03"}),$(".ckbdate").datepicker({yearRange:"-99:+00",changeMonth:!0,changeYear:!0,autoSize:!0,maxDate:0,dateFormat:"M d, yy",showButtonPanel:n.datePickerButtons,beforeShow:function(t){setTimeout(function(){var e=$(t).datepicker("widget").find(".ui-datepicker-buttonpane");e.empty(),$("<button>",{text:"Minor",click:function(){var e=$(t),i=$.datepicker._getInst(e[0]);i.input.val("Minor");var a=$.datepicker._get(i,"onSelect");a?a.apply(i.input?i.input[0]:null,["Minor",i]):i.input&&i.input.trigger("change"),i.inline?$.datepicker._updateDatepicker(i):($.datepicker._hideDatepicker(),$.datepicker._lastInput=i.input[0],"object"!=typeof i.input[0]&&i.input.trigger("focus"),$.datepicker._lastInput=null)}}).appendTo(e).addClass("ui-datepicker-clear ui-state-default ui-priority-primary ui-corner-all")},1)},onChangeMonthYear:function(t,e,i){setTimeout(function(){var t=$(i).datepicker("widget").find(".ui-datepicker-buttonpane");t.empty(),$("<button>",{text:"Minor",click:function(){var t=$(i.input),e=$.datepicker._getInst(t[0]);e.input.val("Minor");var a=$.datepicker._get(e,"onSelect");a?a.apply(e.input?e.input[0]:null,["Minor",e]):e.input&&e.input.trigger("change"),e.inline?$.datepicker._updateDatepicker(e):($.datepicker._hideDatepicker(),$.datepicker._lastInput=e.input[0],"object"!=typeof e.input[0]&&e.input.trigger("focus"),$.datepicker._lastInput=null)}}).appendTo(t).addClass("ui-datepicker-clear ui-state-default ui-priority-primary ui-corner-all")},1)}}),$("#cbLastConfirmed").change(function(){$(this).prop("checked")?$("#txtLastConfirmed").datepicker("setDate","+0"):$("#txtLastConfirmed").val($("#txtLastConfirmed").prop("defaultValue"))}),$("#txtLastConfirmed").change(function(){$("#txtLastConfirmed").val()==$("#txtLastConfirmed").prop("defaultValue")?$("#cbLastConfirmed").prop("checked",!1):$("#cbLastConfirmed").prop("checked",!0)}),$(".checklistTbl").on("change",".hhk-checkboxlist",function(){$(this).prop("checked")?($("#date"+$(this).data("code")).datepicker("setDate","+0"),$("#disp"+$(this).data("code")).show()):($("#date"+$(this).data("code")).val($("#date"+$(this).data("code")).prop("defaultValue")),$("#disp"+$(this).data("code")).hide())}),verifyAddrs("div#nameTab, div#hospitalSection"),addrPrefs(n),createZipAutoComplete($("input.hhk-zipsearch"),"ws_admin.php",a),$("#btnSubmit").click(function(){if("Saving>>>>"===$(this).val())return!1;$(this).val("Saving>>>>")}),$("#txtsearch").keypress(function(t){var e=$(this).val();"13"==t.keyCode&&(""!=e&&isNumber(parseInt(e,10))?(e>0&&window.location.assign("GuestEdit.php?id="+e),t.preventDefault()):(alert("Don't press the return key unless you enter an Id."),t.preventDefault()))}),$("#cbdeceased").change(function(){$(this).prop("checked")?$("#disp_deceased").show():$("#disp_deceased").hide()}),$("#cbbackgroundcheck").change(function(){$(this).prop("checked")?($("#txtBackgroundCheckDate").datepicker("setDate","+0"),$("#disp_backgroundcheck").show()):($("#txtBackgroundCheckDate").val(""),$("#disp_backgroundcheck").hide())}),$("select.hhk-multisel").each(function(){$(this).multiselect({selectedList:3})}),createRoleAutoComplete($("#txtsearch"),3,{cmd:"guest"},function(t){t.id>0&&window.location.assign("GuestEdit.php?id="+t.id)},!1),createRoleAutoComplete($("#txtMRNsearch"),3,{cmd:"mrn"},function(t){t.id>0&&window.location.assign("GuestEdit.php?id="+t.id)},!1),createRoleAutoComplete($("#txtPhsearch"),5,{cmd:"phone"},function(t){t.id>0&&window.location.assign("GuestEdit.php?id="+t.id)},!1),createAutoComplete($("#txtRelSch"),3,{cmd:"srrel",basis:$("#hdnRelCode").val(),id:n.id},function(t){$.post("ws_admin.php",{rId:t.id,id:n.id,rc:$("#hdnRelCode").val(),cmd:"newRel"},relationReturn)}),""!==resultMessage&&flagAlertMessage(resultMessage,"alert"),$("input.hhk-check-button").click(function(){"exAll"===$(this).prop("id")?$("input.hhk-ex").prop("checked",!0):$("input.hhk-ex").prop("checked",!1)}),$("#divFuncTabs").show(),$(".hhk-showonload").show(),$("#txtsearch").focus(),$(document).find("bfh-states").each(function(){$(this).data("dirrty-initial-value",$(this).data("state"))}),$(document).find("bfh-country").each(function(){$(this).data("dirrty-initial-value",$(this).data("country"))}),$("#btnCred").click(function(){cardOnFile($(this).data("id"),$(this).data("idreg"),"GuestEdit.php?id="+$(this).data("id")+"&psg="+n.idPsg,$(this).data("indx"))}),setupCOF($(".tblCreditExpandg"),$("#btnCred").data("indx")),$("#keysfees").mousedown(function(t){var e=$(t.target);"pudiv"!==e[0].id&&0===e.parents("#pudiv").length&&$("div#pudiv").remove()}),$("#form1").dirrty(),$("#btnActvtyGo").button().click(function(){$(".hhk-alert").hide();var t=$("#txtactstart").datepicker("getDate");if(null===t){$("#txtactstart").addClass("ui-state-highlight"),flagAlertMessage("Enter start date","alert");return}$("#txtactstart").removeClass("ui-state-highlight");var e=$("#txtactend").datepicker("getDate");null===e&&(e=new Date);var i={cmd:"actrpt",start:t.toLocaleDateString(),end:e.toLocaleDateString(),psg:n.idPsg};$("#cbVisits").prop("checked")&&(i.visit="on"),$("#cbReserv").prop("checked")&&(i.resv="on"),$("#cbHospStay").prop("checked")&&(i.hstay="on"),$.post("ws_resc.php",i,function(t){if(t){try{t=$.parseJSON(t)}catch(e){alert("Parser error - "+e.message);return}t.error?(t.gotopage&&window.open(t.gotopage,"_self"),flagAlertMessage(t.error,"error")):t.success&&($("#activityLog").remove(),$("#vvisitLog").append($('<div id="activityLog"/>').append($(t.success))),$(".hhk-viewvisit").css("cursor","pointer"),$("#activityLog").on("click",".hhk-viewvisit",function(){if($(this).data("visitid")){var t=$(this).data("visitid").split("_");2===t.length&&viewVisit(0,t[0],{Save:function(){saveFees(0,t[0],t[1])},Cancel:function(){$(this).dialog("close")}},"View Visit","n",t[1])}else $(this).data("reservid")&&window.location.assign("Reserve.php?rid="+$(this).data("reservid"))}))}})}),showGuestPhoto||useDocUpload){var c=window.uploader;$(document).on("click",".upload-guest-photo",function(){$(c.container).removeClass().addClass("uppload-container"),c.updatePlugins(t=>[]),c.updateSettings({maxSize:[500,500],customClass:"guestPhotouploadContainer",uploader:function t(e){return new Promise(function(t,i){var a=new FormData;a.append("cmd","putguestphoto"),a.append("guestId",n.id),a.append("guestPhoto",e),$.ajax({type:"POST",url:"../house/ws_resc.php",dataType:"json",data:a,contentType:!1,processData:!1,success:function(e){e.error?i(e.error):(t("success"),$("#hhk-guest-photo").css("background-image","url(../house/ws_resc.php?cmd=getguestphoto&guestId="+n.id+"r&x="+new Date().getTime()+")"),$(".delete-guest-photo").show()),c.navigate("local")},error:function(t){i(t)}})})}});var t=new Upploader.Local({maxFileSize:5e6,mimeTypes:["image/jpeg","image/png"]});window.camera=new Upploader.Camera,c.use([t,new Upploader.Crop({aspectRatio:1}),window.camera]),c.open()}),c.on("open",function(){1==c.effects.length?$(c.container).find(".effects-tabs").hide():$(c.container).find(".effects-tabs").show()}),c.on("close",function(){c.navigate("local");var t=c.services.filter(t=>"camera"==t.name);1==t.length&&t[0].stop()}),$(document).on("click","#hhk-guest-photo",function(t){t.preventDefault()}),$(".hhk-visitdialog #hhk-guest-photo").on({mouseenter:function(){$(this).find("#hhk-guest-photo-actions").show(),$(this).find("#hhk-guest-photo img").fadeTo(100,.5)},mouseleave:function(){$(this).find("#hhk-guest-photo-actions").hide(),$(this).find("#hhk-guest-photo img").fadeTo(100,1)}}),$(".delete-guest-photo").on("click",function(){confirm("Really Delete this photo?")&&$.ajax({type:"POST",url:"../house/ws_resc.php",dataType:"json",data:{cmd:"deleteguestphoto",guestId:n.id},success:function(t){if(t.error){t.gotopage&&window.location.assign(t.gotopage),flagAlertMessage("Server error - "+t.error,"error");return}$("#hhk-guest-photo").css("background-image","url(../house/ws_resc.php?cmd=getguestphoto&guestId="+n.id+"&rx="+new Date().getTime()+")")},error:function(t){flagAlertMessage("AJAX error - "+t)}})})}});