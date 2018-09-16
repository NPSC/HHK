function isNumber(e){"use strict";return!isNaN(parseFloat(e))&&isFinite(e)}var dtCols=[{targets:[0],title:"Date",data:"Date",render:function(e,t){return dateRender(e,t,dateFormat)}},{targets:[1],title:"Type",searchable:!1,sortable:!1,data:"Type"},{targets:[2],title:"Sub-Type",searchable:!1,sortable:!1,data:"Sub-Type"},{targets:[3],title:"User",searchable:!1,sortable:!0,data:"User"},{targets:[4],visible:!1,data:"Id"},{targets:[5],title:"Log Text",sortable:!1,data:"Log Text"}];function relationReturn(e){if((e=$.parseJSON(e)).error)e.gotopage&&window.open(e.gotopage,"_self"),flagAlertMessage(e.error,"error");else if(e.success){if(e.rc&&e.markup){var t=$("#acm"+e.rc);t.children().remove();var i=$(e.markup);t.append(i.children())}flagAlertMessage(e.success,"success")}}function setupPsgNotes(e,t){return t.notesViewer({linkId:e,linkType:"psg",newNoteAttrs:{id:"psgNewNote",name:"psgNewNote"},alertMessage:function(e,t){flagAlertMessage(e,t)}}),t}function manageRelation(e,t,i,a){$.post("ws_admin.php",{id:e,rId:t,rc:i,cmd:a},relationReturn)}$(document).ready(function(){"use strict";var i,a=memberData,e=1,n="../admin/ws_gen.php?cmd=chglog&vw=vguest_audit_log&uid="+a.id;if($.widget("ui.autocomplete",$.ui.autocomplete,{_resizeMenu:function(){var e=this.menu.element;e.outerWidth(1.1*Math.max(e.width("").outerWidth()+1,this.element.outerWidth()))}}),$("#divFuncTabs").tabs({collapsible:!0}),$("#submit").dialog({autoOpen:!1,resizable:!1,width:300,modal:!0,buttons:{Exit:function(){$(this).dialog("close")}}}),$("#keysfees").dialog({autoOpen:!1,resizable:!0,modal:!0,close:function(e,t){$("div#submitButtons").show()},open:function(e,t){$("div#submitButtons").hide()}}),$("#pmtRcpt").dialog({autoOpen:!1,resizable:!0,modal:!0,title:"Payment Receipt"}),$("#faDialog").dialog({autoOpen:!1,resizable:!0,width:650,modal:!0,title:"Income Chooser"}),""!==rctMkup&&showReceipt("#pmtRcpt",rctMkup),$(".hhk-view-visit").click(function(){var e=$(this).data("vid"),t=$(this).data("gid"),i=$(this).data("span");viewVisit(t,e,{"Show Statement":function(){window.open("ShowStatement.php?vid="+e,"_blank")},"Show Registration Form":function(){window.open("ShowRegForm.php?vid="+e,"_blank")},Save:function(){saveFees(t,e,i,!1,"GuestEdit.php?id="+t+"&psg="+a.idPsg)},Cancel:function(){$(this).dialog("close")}},"Edit Visit #"+e+"-"+i,"",i),$("#divAlert1").hide()}),$("#resvAccordion").accordion({heightStyle:"content",collapsible:!0,active:!1,icons:!1}),$("div.hhk-relations").each(function(){var t=$(this).attr("name");$(this).on("click","td.hhk-deletelink",function(){0<a.id&&confirm($(this).attr("title")+"?")&&manageRelation(a.id,$(this).attr("name"),t,"delRel")}),$(this).on("click","td.hhk-newlink",function(){if(0<a.id){var e=$(this).attr("title");$("#hdnRelCode").val(t),$("#submit").dialog("option","title",e),$("#submit").dialog("open")}})}),$("#cbNoVehicle").change(function(){this.checked?$("#tblVehicle").hide():$("#tblVehicle").show()}),$("#cbNoVehicle").change(),$("#btnNextVeh, #exAll, #exNone").button(),$("#btnNextVeh").click(function(){$("#trVeh"+e).show("fade"),4<++e&&$("#btnNextVeh").hide("fade")}),$("#divNametabs").tabs({beforeActivate:function(e,t){var i=$("#vvisitLog").find("table");"visitLog"===t.newTab.prop("id")&&0===i.length&&$.post("ws_ckin.php",{cmd:"gtvlog",idReg:a.idReg},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error?(e.gotopage&&window.open(e.gotopage,"_self"),flagAlertMessage(e.error,"error")):e.vlog&&$("#vvisitLog").append($(e.vlog))}})},collapsible:!0}),$("#btnSubmit, #btnReset, #btnCred").button(),$("#btnCred").click(function(){cardOnFile($(this).data("id"),$(this).data("idreg"),"GuestEdit.php?id="+$(this).data("id")+"&psg="+a.idPsg)}),$("#phEmlTabs").tabs(),$("#emergTabs").tabs(),$("#addrsTabs").tabs(),$("#psgList").tabs({collapsible:!0,beforeActivate:function(e,t){0<t.newPanel.length&&("fin"===t.newTab.prop("id")&&(getIncomeDiag(0,a.idReg),e.preventDefault()),"chglog"!==t.newTab.prop("id")||i||(i=$("#dataTbl").dataTable({columnDefs:dtCols,serverSide:!0,processing:!0,deferRender:!0,language:{search:"Search Log Text:"},sorting:[[0,"desc"]],displayLength:25,lengthMenu:[[25,50,100,-1],[25,50,100,"All"]],Dom:'<"top"ilf>rt<"bottom"ip>',ajax:{url:n}})))}}),a.psgOnly&&$("#psgList").tabs("disable"),$("#psgList").tabs("enable",psgTabIndex),$("#psgList").tabs("option","active",psgTabIndex),$("#cbnoReturn").change(function(){this.checked?$("#selnoReturn").show():$("#selnoReturn").hide()}),$("#cbnoReturn").change(),0===a.id)$("#divFuncTabs").tabs("option","disabled",[2,3,4]),$("#phEmlTabs").tabs("option","active",1),$("#phEmlTabs").tabs("option","disabled",[0]);else{var t=parseInt($("#addrsTabs").children("ul").data("actidx"),10);isNaN(t)&&(t=0),$("#addrsTabs").tabs("option","active",t)}$.datepicker.setDefaults({yearRange:"-0:+02",changeMonth:!0,changeYear:!0,autoSize:!0,numberOfMonths:1,dateFormat:"M d, yy"}),$(".ckdate").datepicker({yearRange:"-02:+03"}),$(".ckbdate").datepicker({yearRange:"-99:+00",changeMonth:!0,changeYear:!0,autoSize:!0,maxDate:0,dateFormat:"M d, yy"}),$("#cbLastConfirmed").change(function(){$(this).prop("checked")?$("#txtLastConfirmed").datepicker("setDate","+0"):$("#txtLastConfirmed").val($("#txtLastConfirmed").prop("defaultValue"))}),$("#txtLastConfirmed").change(function(){$("#txtLastConfirmed").val()==$("#txtLastConfirmed").prop("defaultValue")?$("#cbLastConfirmed").prop("checked",!1):$("#cbLastConfirmed").prop("checked",!0)}),verifyAddrs("div#nameTab, div#hospitalSection"),addrPrefs(a),createZipAutoComplete($("input.hhk-zipsearch"),"ws_admin.php",void 0),$("#btnSubmit").click(function(){if("Saving>>>>"===$(this).val())return!1;$(this).val("Saving>>>>")}),$("#txtsearch").keypress(function(e){var t=$(this).val();"13"==e.keyCode&&(""!=t&&isNumber(parseInt(t,10))?0<t&&window.location.assign("GuestEdit.php?id="+t):alert("Don't press the return key unless you enter an Id."),e.preventDefault())}),$("#cbdeceased").change(function(){$(this).prop("checked")?$("#disp_deceased").show():$("#disp_deceased").hide()}),$("select.hhk-multisel").each(function(){$(this).multiselect({selectedList:3})}),createAutoComplete($("#txtAgentSch"),3,{cmd:"filter",add:"phone",basis:"ra"},getAgent),""===$("#a_txtLastName").val()&&$(".hhk-agentInfo").hide(),createAutoComplete($("#txtDocSch"),3,{cmd:"filter",basis:"doc"},getDoc),""===$("#d_txtLastName").val()&&$(".hhk-docInfo").hide(),createAutoComplete($("#txtsearch"),3,{cmd:"role",mode:"mo",gp:"1"},function(e){0<e.id&&window.location.assign("GuestEdit.php?id="+e.id)}),createAutoComplete($("#txtPhsearch"),5,{cmd:"role",mode:"mo",gp:"1"},function(e){0<e.id&&window.location.assign("GuestEdit.php?id="+e.id)}),createAutoComplete($("#txtRelSch"),3,{cmd:"srrel",basis:$("#hdnRelCode").val(),id:a.id},function(e){$.post("ws_admin.php",{rId:e.id,id:a.id,rc:$("#hdnRelCode").val(),cmd:"newRel"},relationReturn)}),setupPsgNotes(a.idPsg,$("#psgNoteViewer")),$("input.hhk-check-button").click(function(){"exAll"===$(this).prop("id")?$("input.hhk-ex").prop("checked",!0):$("input.hhk-ex").prop("checked",!1)}),$(".hhk-hideStatus, .hhk-hideBasis").hide(),$("#divFuncTabs").show(),$(".hhk-showonload").show(),$("#txtsearch").focus(),$(document).find("bfh-states").each(function(){$(this).data("dirrty-initial-value",$(this).data("state"))}),$(document).find("bfh-country").each(function(){$(this).data("dirrty-initial-value",$(this).data("country"))}),$("#form1").dirrty()});
