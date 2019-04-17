function isNumber(n){"use strict";return!isNaN(parseFloat(n))&&isFinite(n)}
var dtCols=[{"targets":[0],"title":"Date",'data':'Date',render:function(data,type){return dateRender(data,type,dateFormat)}},{"targets":[1],"title":"Type","searchable":!1,"sortable":!1,"data":"Type"},{"targets":[2],"title":"Sub-Type","searchable":!1,"sortable":!1,"data":"Sub-Type"},{"targets":[3],"title":"User","searchable":!1,"sortable":!0,"data":"User"},{"targets":[4],"visible":!1,"data":"Id"},{"targets":[5],"title":"Log Text","sortable":!1,"data":"Log Text"}];function relationReturn(data){data=$.parseJSON(data);if(data.error){if(data.gotopage){window.open(data.gotopage,'_self')}
flagAlertMessage(data.error,'error')}else if(data.success){if(data.rc&&data.markup){var div=$('#acm'+data.rc);div.children().remove();var newDiv=$(data.markup);div.append(newDiv.children())}
flagAlertMessage(data.success,'success')}}
function setupPsgNotes(rid,$container){$container.notesViewer({linkId:rid,linkType:'psg',newNoteAttrs:{id:'psgNewNote',name:'psgNewNote'},alertMessage:function(text,type){flagAlertMessage(text,type)}});return $container}
function manageRelation(id,rId,relCode,cmd){$.post('ws_admin.php',{'id':id,'rId':rId,'rc':relCode,'cmd':cmd},relationReturn)}
$(document).ready(function(){"use strict";var memData=memberData;var nextVeh=1;var listJSON='../admin/ws_gen.php?cmd=chglog&vw=vguest_audit_log&uid='+memData.id;var listEvtTable;var setupNotes,$psgList;$.widget("ui.autocomplete",$.ui.autocomplete,{_resizeMenu:function(){var ul=this.menu.element;ul.outerWidth(Math.max(ul.width("").outerWidth()+1,this.element.outerWidth())*1.1)}});$("#divFuncTabs").tabs({collapsible:!0});$("#submit").dialog({autoOpen:!1,resizable:!1,width:300,modal:!0,buttons:{"Exit":function(){$(this).dialog("close")}}});$('#keysfees').dialog({autoOpen:!1,resizable:!0,modal:!0,close:function(event,ui){$('div#submitButtons').show()},open:function(event,ui){$('div#submitButtons').hide()}});$('#pmtRcpt').dialog({autoOpen:!1,resizable:!0,modal:!0,title:'Payment Receipt'});$("#faDialog").dialog({autoOpen:!1,resizable:!0,width:650,modal:!0,title:'Income Chooser'});if(rctMkup!==''){showReceipt('#pmtRcpt',rctMkup)}
if(pmtMkup!==''){$('#paymentMessage').html(pmtMkup).show()}
$('.hhk-view-visit').click(function(){var vid=$(this).data('vid');var gid=$(this).data('gid');var span=$(this).data('span');var buttons={"Show Statement":function(){window.open('ShowStatement.php?vid='+vid,'_blank')},"Show Registration Form":function(){window.open('ShowRegForm.php?vid='+vid,'_blank')},"Save":function(){saveFees(gid,vid,span,!1,'GuestEdit.php?id='+gid+'&psg='+memData.idPsg)},"Cancel":function(){$(this).dialog("close")}};viewVisit(gid,vid,buttons,'Edit Visit #'+vid+'-'+span,'',span);$('#divAlert1').hide()});$('#resvAccordion').accordion({heightStyle:"content",collapsible:!0,active:!1,icons:!1});$('div.hhk-relations').each(function(){var schLinkCode=$(this).attr('name');$(this).on('click','td.hhk-deletelink',function(){if(memData.id>0){if(confirm($(this).attr('title')+'?')){manageRelation(memData.id,$(this).attr('name'),schLinkCode,'delRel')}}});$(this).on('click','td.hhk-newlink',function(){if(memData.id>0){var title=$(this).attr('title');$('#hdnRelCode').val(schLinkCode);$('#submit').dialog("option","title",title);$('#submit').dialog('open')}})});$('#cbNoVehicle').change(function(){if(this.checked){$('#tblVehicle').hide()}else{$('#tblVehicle').show()}});$('#cbNoVehicle').change();$('#btnNextVeh, #exAll, #exNone').button();$('#btnNextVeh').click(function(){$('#trVeh'+nextVeh).show('fade');nextVeh++;if(nextVeh>4){$('#btnNextVeh').hide('fade')}});$('#divNametabs').tabs({beforeActivate:function(event,ui){var tbl=$('#vvisitLog').find('table');if(ui.newTab.prop('id')==='visitLog'&&tbl.length===0){$.post('ws_ckin.php',{cmd:'gtvlog',idReg:memData.idReg},function(data){if(data){try{data=$.parseJSON(data)}catch(err){alert("Parser error - "+err.message);return}
if(data.error){if(data.gotopage){window.open(data.gotopage,'_self')}
flagAlertMessage(data.error,'error')}else if(data.vlog){$('#vvisitLog').append($(data.vlog))}}})}else if(ui.newTab.prop('id')==='chglog'&&!listEvtTable){listEvtTable=$('#dataTbl').dataTable({"columnDefs":dtCols,"serverSide":!0,"processing":!0,"deferRender":!0,"language":{"search":"Search Log Text:"},"sorting":[[0,'desc']],"displayLength":25,"lengthMenu":[[25,50,100,-1],[25,50,100,"All"]],"Dom":'<"top"ilf>rt<"bottom"ip>',ajax:{url:listJSON}})}},collapsible:!0});$('#btnSubmit, #btnReset, #btnCred').button();$('#btnCred').click(function(){cardOnFile($(this).data('id'),$(this).data('idreg'),'GuestEdit.php?id='+$(this).data('id')+'&psg='+memData.idPsg)});$('#phEmlTabs').tabs();$('#emergTabs').tabs();$('#addrsTabs').tabs();$psgList=$('#psgList').tabs({collapsible:!0,beforeActivate:function(event,ui){if(ui.newPanel.length>0){if(ui.newTab.prop('id')==='fin'){getIncomeDiag(0,memData.idReg);event.preventDefault()}
if(ui.newTab.prop('id')==='lipsg'&&!setupNotes){setupNotes=setupPsgNotes(memData.idPsg,$('#psgNoteViewer'))}}}});if(memData.psgOnly){$psgList.tabs("disable")}
$psgList.tabs("enable",psgTabIndex);$psgList.tabs("option","active",psgTabIndex);$('#cbnoReturn').change(function(){if(this.checked){$('#selnoReturn').show()}else{$('#selnoReturn').hide()}});$('#cbnoReturn').change();if(memData.id===0){$("#divFuncTabs").tabs("option","disabled",[2,3,4]);$('#phEmlTabs').tabs("option","active",1);$('#phEmlTabs').tabs("option","disabled",[0])}else{var tbIndex=parseInt($('#addrsTabs').children('ul').data('actidx'),10);if(isNaN(tbIndex)){tbIndex=0}
$('#addrsTabs').tabs("option","active",tbIndex)}
$.datepicker.setDefaults({yearRange:'-0:+02',changeMonth:!0,changeYear:!0,autoSize:!0,numberOfMonths:1,dateFormat:'M d, yy'});$('.ckdate').datepicker({yearRange:'-02:+03'});$('.ckbdate').datepicker({yearRange:'-99:+00',changeMonth:!0,changeYear:!0,autoSize:!0,maxDate:0,dateFormat:'M d, yy'});$('#cbLastConfirmed').change(function(){if($(this).prop('checked')){$('#txtLastConfirmed').datepicker('setDate','+0')}else{$('#txtLastConfirmed').val($('#txtLastConfirmed').prop('defaultValue'))}});$('#txtLastConfirmed').change(function(){if($('#txtLastConfirmed').val()==$('#txtLastConfirmed').prop('defaultValue')){$('#cbLastConfirmed').prop('checked',!1)}else{$('#cbLastConfirmed').prop('checked',!0)}});verifyAddrs('div#nameTab, div#hospitalSection');addrPrefs(memData);var zipXhr;createZipAutoComplete($('input.hhk-zipsearch'),'ws_admin.php',zipXhr);$('#btnSubmit').click(function(){if($(this).val()==='Saving>>>>'){return!1}
$(this).val('Saving>>>>')});$('#txtsearch').keypress(function(event){var mm=$(this).val();if(event.keyCode=='13'){if(mm==''||!isNumber(parseInt(mm,10))){alert("Don't press the return key unless you enter an Id.");event.preventDefault()}else{if(mm>0){window.location.assign("GuestEdit.php?id="+mm)}
event.preventDefault()}}});$('#cbdeceased').change(function(){if($(this).prop('checked')){$('#disp_deceased').show()}else{$('#disp_deceased').hide()}});$('select.hhk-multisel').each(function(){$(this).multiselect({selectedList:3})});createAutoComplete($('#txtAgentSch'),3,{cmd:'filter',add:'phone',basis:'ra'},getAgent);if($('#a_txtLastName').val()===''){$('.hhk-agentInfo').hide()}
createAutoComplete($('#txtDocSch'),3,{cmd:'filter',basis:'doc'},getDoc);if($('#d_txtLastName').val()===''){$('.hhk-docInfo').hide()}
createAutoComplete($('#txtsearch'),3,{cmd:'role',mode:'mo',gp:'1'},function(item){if(item.id>0){window.location.assign("GuestEdit.php?id="+item.id)}});createAutoComplete($('#txtPhsearch'),5,{cmd:'role',mode:'mo',gp:'1'},function(item){if(item.id>0){window.location.assign("GuestEdit.php?id="+item.id)}});createAutoComplete($('#txtRelSch'),3,{cmd:'srrel',basis:$('#hdnRelCode').val(),id:memData.id},function(item){$.post('ws_admin.php',{'rId':item.id,'id':memData.id,'rc':$('#hdnRelCode').val(),'cmd':'newRel'},relationReturn)});if(resultMessage!==''){flagAlertMessage(resultMessage,'alert')}
$('input.hhk-check-button').click(function(){if($(this).prop('id')==='exAll'){$('input.hhk-ex').prop('checked',!0)}else{$('input.hhk-ex').prop('checked',!1)}});$(".hhk-hideStatus, .hhk-hideBasis").hide();$('#divFuncTabs').show();$('.hhk-showonload').show();$('#txtsearch').focus();$(document).find("bfh-states").each(function(){$(this).data("dirrty-initial-value",$(this).data('state'))});$(document).find("bfh-country").each(function(){$(this).data("dirrty-initial-value",$(this).data('country'))});$("#form1").dirrty();new Uppload({uploadFunction:(file,metadata)=>{return new Promise((resolve,reject)=>{var formData=new FormData();formData.append('cmd','putguestphoto');formData.append('guestPhoto',file);formData.append('guestId',guestId);$.ajax({type:"POST",url:"ws_resc.php",dataType:"json",data:formData,contentType:!1,processData:!1,success:function(data){if(data.error){reject(data.error)}else{resolve("success");$("#guestPhoto").prop("src","ws_resc.php?cmd=getguestphoto&guestId="+guestId)}},error:function(error){reject(error)}})})},services:["camera","upload"],defaultService:"camera",allowedTypes:"image",crop:{aspectRatio:1/1,}});$(".uppload-branding").hide();$(document).on("click","#hhk-guest-photo",function(e){e.preventDefault()});$("#hhk-guest-photo").on({mouseenter:function(){$("#hhk-guest-photo-actions").show();$("#hhk-guest-photo img").fadeTo(100,0.5)},mouseleave:function(){$("#hhk-guest-photo-actions").hide();$("#hhk-guest-photo img").fadeTo(100,1)}});$(".delete-guest-photo").on("click",function(e){$.ajax({type:"POST",url:"ws_resc.php",dataType:"json",data:{cmd:"deleteguestphoto",guestId:guestId},success:function(data){if(data.error){console.log(data.error)}else{$("#guestPhoto").prop("src","ws_resc.php?cmd=getguestphoto&guestId="+guestId)}},error:function(error){console.log(error)}})})})