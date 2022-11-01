/**
 * register.js
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 */ var $dailyTbl,isGuestAdmin,pmtMkup,rctMkup,defaultTab,resourceGroupBy,resourceColumnWidth,patientLabel,guestLabel,visitorLabel,referralFormTitleLabel,reservationLabel,challVar,defaultView,defaultEventColor,defCalEventTextColor,calDateIncrement,dateFormat,fixedRate,resvPageName,showCreatedDate,expandResources,shoHospitalName,showRateCol,hospTitle,showDiags,showLocs,locationTitle,diagnosisTitle,showWlNotes,showCharges,wlTitle,cgCols,rvCols,wlCols,dailyCols,calendar,calStartDate,acceptResvPay;function isNumber(e){return!isNaN(parseFloat(e))&&isFinite(e)}function setRoomTo(e,t){$.post("ws_resv.php",{cmd:"moveResvRoom",rid:e,idResc:t},function(e){try{e=$.parseJSON(e)}catch(t){return alert("Parser error - "+t.message),!1}return e.error?(e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,"error"),!1):e.warning&&""!==e.warning?(flagAlertMessage(e.warning,"alert"),!1):void(e.msg&&""!==e.msg&&flagAlertMessage(e.msg,"info"),calendar.refetchEvents(),refreshdTables(e))})}function refreshdTables(e){"use strict";e.curres&&$("#divcurres").length>0&&$("#curres").DataTable().ajax.reload(),e.reservs&&$("div#vresvs").length>0&&$("#reservs").DataTable().ajax.reload(),e.waitlist&&$("div#vwls").length>0&&$("#waitlist").DataTable().ajax.reload(),e.unreserv&&$("div#vuncon").length>0&&$("#unreserv").DataTable().ajax.reload(),$("#daily").length>0&&$dailyTbl&&$dailyTbl.ajax.reload()}function cgResvStatus(e,t){$.post("ws_ckin.php",{cmd:"rvstat",rid:e,stat:t},function(e){if(e){try{e=$.parseJSON(e)}catch(t){alert("Parser error - "+t.message);return}if(e.error){e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,"error");return}e.success&&(flagAlertMessage(e.success,"info"),calendar.refetchEvents()),refreshdTables(e)}})}function chgRoomCleanStatus(e,t){"use strict";confirm("Change the room status?")&&$.post("ws_resc.php",{cmd:"saveRmCleanCode",idr:e,stat:t},function(e){if(e){try{e=$.parseJSON(e)}catch(t){alert("Parser error - "+t.message);return}if(e.error){e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage("Server error - "+e.error,"error");return}refreshdTables(e),e.msg&&""!=e.msg&&flagAlertMessage(e.msg,"info")}})}function editPSG(e){var t={Close:function(){$(this).dialog("close")}};$.post("ws_ckin.php",{cmd:"viewPSG",psg:e},function(e){if(e){try{e=$.parseJSON(e)}catch(a){alert("Parser error - "+a.message);return}if(e.error)e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,"error");else if(e.markup){let s=$("div#keysfees");s.children().remove(),s.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(e.markup))),s.dialog("option","buttons",t),s.dialog("option","title","View Patient Support Group"),s.dialog("option","width",900),s.dialog("open")}}})}function ckOut(e,t,a,s){viewVisit(t,a,{"Show Statement":function(){window.open("ShowStatement.php?vid="+a,"_blank")},"Show Registration Form":function(){window.open("ShowRegForm.php?vid="+a+"&span="+s,"_blank")},"Check Out":function(){saveFees(t,a,s,!0,"register.php")},Cancel:function(){$(this).dialog("close")}},"Check Out "+e,"co",s)}function editVisit(e,t,a,s){viewVisit(t,a,{"Show Statement":function(){window.open("ShowStatement.php?vid="+a,"_blank")},"Show Registration Form":function(){window.open("ShowRegForm.php?vid="+a+"&span="+s,"_blank")},Save:function(){saveFees(t,a,s,!0,"register.php")},Cancel:function(){$(this).dialog("close")}},"Edit Visit #"+a+"-"+s,"",s)}function getStatusEvent(e,t,a){"use strict";$.post("ws_resc.php",{cmd:"getStatEvent",tp:t,title:a,id:e},function(a){if(a){try{a=$.parseJSON(a)}catch(s){alert("Parser error - "+s.message);return}a.error?(a.gotopage&&window.location.assign(a.gotopage),alert("Server error - "+a.error)):a.tbl&&($("#statEvents").children().remove().end().append($(a.tbl)),$(".ckdate").datepicker({autoSize:!0,dateFormat:"M d, yy",beforeShow:function(e,t){var a=$(this).closest("tr").find('[id^="txtstart"]'),s=$(this).closest("tr").find('[id^="txtend"]');$(this).attr("id").startsWith("txtstart")&&""!=s.val()&&$(this).datepicker("option","maxDate",s.val()),$(this).attr("id").startsWith("txtend")&&""!=a.val()&&$(this).datepicker("option","minDate",a.val())}}),$("#statEvents").dialog("option","buttons",{Save:function(){saveStatusEvent(e,t)},Cancel:function(){$(this).dialog("close")}}),$("#statEvents").dialog("open"))}})}function saveStatusEvent(e,t){"use strict";$.post("ws_resc.php",$("#statForm").serialize()+"&cmd=saveStatEvent&id="+e+"&tp="+t,function(e){if(e){try{e=$.parseJSON(e)}catch(t){alert("Parser error - "+t.message);return}e.error&&(e.gotopage&&window.location.assign(e.gotopage),alert("Server error - "+e.error)),e.reload&&1==e.reload&&(calendar.refetchResources(),calendar.refetchEvents()),e.msg&&""!=e.msg&&flagAlertMessage(e.msg,"info")}$("#statEvents").dialog("close")})}function showChangeRoom(e,t,a,s){this.rooms={},$.post("ws_ckin.php",{cmd:"showChangeRooms",idVisit:a,span:s,idGuest:t},function(t){"use strict";if(t){try{t=$.parseJSON(t)}catch(r){alert("Parser error - "+r.message);return}if(t.error){if(t.gotopage){window.location.assign(t.gotopage);return}flagAlertMessage(t.error,"error");return}let i=new Date(t.start),n=$("#chgRoomDialog");n.children().remove(),n.append($('<div class="hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(t.success)));let l=n.find("#selResource"),c=$("#resvChangeDate"),p=$("input[name=rbReplaceRoom]"),u=$("#cbUseDefaultRate");c.datepicker({yearRange:"-05:+00",changeMonth:!0,changeYear:!0,autoSize:!0,numberOfMonths:1,maxDate:0,minDate:i,dateFormat:"M d, yy"}),c.datepicker("setDate",new Date),p.change(function(){"new"==$(this).val()&&""!==c.val()?o(a,s,c.datepicker("getDate"),l):"rpl"==$(this).val()&&o(a,s,i,l)}),c.change(function(){$("input[name=rbReplaceRoomnew]").prop("checked",!0),o(a,s,c.datepicker("getDate"),l)}),rooms=t.rooms?t.rooms:{},l.change(function(){let e=$(this).val();rooms[e]&&t.curResc.key<rooms[e].key?n.find("#rmDepMessage").text("Deposit required").show():n.find("#rmDepMessage").empty().hide(),""==t.curResc.defaultRateCat&&""!=rooms[e].defaultRateCat||""!=t.curResc.defaultRateCat&&""!=rooms[e].defaultRateCat&&t.curResc.defaultRateCat!=rooms[e].defaultRateCat?n.find("#trUseDefaultRate").show():n.find("#trUseDefaultRate").hide()}),l.change(),n.dialog("option","title","Change Rooms for "+e),n.dialog("option","width","400px"),n.dialog("option","buttons",{"Change Rooms":function(){var e,t,o,r,i,n;$("#selResource").val()>0?(e=a,t=s,o=l.val(),r=$('input[name="rbReplaceRoom"]:checked').val(),i=u.prop("checked"),n=c.datepicker("getDate").toUTCString(),$.post("ws_ckin.php",{cmd:"doChangeRooms",idVisit:e,span:t,idRoom:o,replaceRoom:r,useDefault:i,changeDate:n},function(t){try{t=$.parseJSON(t)}catch(a){alert("Parser error - "+a.message);return}if(t.error){t.gotopage&&window.open(t.gotopage),flagAlertMessage(t.error,"error");return}t.openvisitviewer&&editVisit("",0,e,t.openvisitviewer),t.msg&&""!=t.msg&&flagAlertMessage(t.msg,"info"),calendar.refetchEvents(),refreshdTables(t)}),$(this).dialog("close")):$("#rmDepMessage").text("Choose a room").show()},Cancel:function(){$(this).dialog("close")}}),n.dialog("open")}});function o(e,t,a,s){s.prop("disabled",!0),$("#hhk-roomChsrtitle").addClass("hhk-loading"),$("#rmDepMessage").text("").hide(),d=new Date;let o={cmd:"chgRoomList",idVisit:e,span:t,chgDate:a.toDateString(),selRescId:s.val()};$.post("ws_ckin.php",o,function(e){let t;s.prop("disabled",!1),$("#hhk-roomChsrtitle").removeClass("hhk-loading");try{e=$.parseJSON(e)}catch(a){alert("Parser error - "+a.message);return}if(e.error){e.gotopage&&window.open(e.gotopage),flagAlertMessage(e.error,"error");return}e.sel&&(t=$(e.sel),s.children().remove(),t.children().appendTo(s),s.val(e.idResc).change()),rooms=e.rooms?e.rooms:{}})}}function moveVisit(e,t,a,s,o,r){$.post("ws_ckin.php",{cmd:e,idVisit:t,span:a,sdelta:s,edelta:o},function(e){if(e){try{e=$.parseJSON(e)}catch(t){alert("Parser error - "+t.message);return}e.error?(e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,"error")):e.success&&(flagAlertMessage(e.success,"success"),(void 0===r||!0===r)&&(calendar.refetchEvents(),refreshdTables(e)))}})}function getRoomList(e,t,a){e&&$.post("ws_ckin.php",{cmd:"rmlist",rid:e,x:t},function(e){try{e=$.parseJSON(e)}catch(t){alert("Parser error - "+t.message);return}if(e.error){e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,"error");return}if(e.container){let s=$(e.container);$("body").append(s),s.position({my:"top",at:"bottom",of:a}),$("#selRoom").change(function(){if(""==$("#selRoom").val()){s.remove();return}confirm("Change room to "+$("#selRoom option:selected").text()+"?")&&setRoomTo(e.rid,$("#selRoom").val()),s.remove()})}})}function refreshPayments(){$("#btnFeesGo").click()}$(document).ready(function(){"use strict";var e,t=0;if(calStartDate=new moment,isGuestAdmin=$("#isGuestAdmin").val(),pmtMkup=$("#pmtMkup").val(),rctMkup=$("#rctMkup").val(),defaultTab=$("#defaultTab").val(),resourceGroupBy=$("#resourceGroupBy").val(),resourceColumnWidth=$("#resourceColumnWidth").val(),patientLabel=$("#patientLabel").val(),visitorLabel=$("#visitorLabel").val(),guestLabel=$("#guestLabel").val(),referralFormTitleLabel=$("#referralFormTitleLabel").val(),reservationLabel=$("#reservationLabel").val(),challVar=$("#challVar").val(),defaultView=$("#defaultView").val(),defaultEventColor=$("#defaultEventColor").val(),defCalEventTextColor=$("#defCalEventTextColor").val(),calDateIncrement=$("#calDateIncrement").val(),dateFormat=$("#dateFormat").val(),fixedRate=$("#fixedRate").val(),resvPageName=$("#resvPageName").val(),showCreatedDate=$("#showCreatedDate").val(),expandResources=$("#expandResources").val(),shoHospitalName=$("#shoHospitalName").val(),showRateCol=$("#showRateCol").val(),hospTitle=$("#hospTitle").val(),showDiags=$("#showDiags").val(),showLocs=$("#showLocs").val(),locationTitle=$("#locationTitle").val(),diagnosisTitle=$("#diagnosisTitle").val(),showWlNotes=$("#showWlNotes").val(),wlTitle=$("#wlTitle").val(),showCharges=$("#showCharges").val(),acceptResvPay=$("#acceptResvPay").val(),cgCols=[{data:"Action",title:"Action",sortable:!1,searchable:!1},{data:visitorLabel+" First",title:visitorLabel+" First"},{data:visitorLabel+" Last",title:visitorLabel+" Last"},{data:"Checked In",title:"Checked In",render:function(e,t){return dateRender(e,t,dateFormat)}},{data:"Nights",title:"Nights",className:"hhk-justify-c"},{data:"Expected Departure",title:"Expected Departure",render:function(e,t){return dateRender(e,t,dateFormat)}},{data:"Room",title:"Room",className:"hhk-justify-c"}],showRateCol&&cgCols.push({data:"Rate",title:"Rate"}),cgCols.push({data:"Phone",title:"Phone"}),shoHospitalName&&cgCols.push({data:"Hospital",title:hospTitle}),cgCols.push({data:"Patient",title:patientLabel}),rvCols=[{data:"Action",title:"Action",sortable:!1,searchable:!1},{data:"Guest First",title:visitorLabel+" First"},{data:"Guest Last",title:visitorLabel+" Last"},{data:"Expected Arrival",title:"Expected Arrival",render:function(e,t){return dateRender(e,t,dateFormat)}},{data:"Nights",title:"Nights",className:"hhk-justify-c"},{data:"Expected Departure",title:"Expected Departure",render:function(e,t){return dateRender(e,t,dateFormat)}},{data:"Room",title:"Room",className:"hhk-justify-c"}],showRateCol&&rvCols.push({data:"Rate",title:"Rate"}),rvCols.push({data:"Occupants",title:"Occupants",className:"hhk-justify-c"}),acceptResvPay&&rvCols.push({data:"PrePaymt",title:"Pre-Paymt",className:"hhk-justify-c"}),shoHospitalName&&rvCols.push({data:"Hospital",title:hospTitle}),showLocs&&rvCols.push({data:"Location",title:locationTitle}),showDiags&&rvCols.push({data:"Diagnosis",title:diagnosisTitle}),rvCols.push({data:"Patient",title:patientLabel}),wlCols=[{data:"Action",title:"Action",sortable:!1,searchable:!1},{data:"Guest First",title:visitorLabel+" First"},{data:"Guest Last",title:visitorLabel+" Last"}],showCreatedDate&&(wlCols.push({data:"Timestamp",title:"Created On",render:function(e,t){return dateRender(e,t,"MMM D, YYYY H:mm")}}),wlCols.push({data:"Updated_By",title:"Updated By"})),wlCols.push({data:"Expected Arrival",title:"Expected Arrival",render:function(e,t){return dateRender(e,t,dateFormat)}}),wlCols.push({data:"Nights",title:"Nights",className:"hhk-justify-c"}),wlCols.push({data:"Expected Departure",title:"Expected Departure",render:function(e,t){return dateRender(e,t,dateFormat)}}),wlCols.push({data:"Occupants",title:"Occupants",className:"hhk-justify-c"}),acceptResvPay&&wlCols.push({data:"PrePaymt",title:"Pre-Paymt",className:"hhk-justify-c"}),shoHospitalName&&wlCols.push({data:"Hospital",title:hospTitle}),showLocs&&wlCols.push({data:"Location",title:locationTitle}),showDiags&&wlCols.push({data:"Diagnosis",title:diagnosisTitle}),wlCols.push({data:"Patient",title:patientLabel}),showWlNotes&&wlCols.push({data:"WL Notes",title:wlTitle}),dailyCols=[{data:"titleSort",visible:!1},{data:"Title",title:"Room",orderData:[0,1],className:"hhk-justify-c"},{data:"Status",title:"Status",searchable:!1},{data:"Guests",title:visitorLabel+"s"},{data:"Patient_Name",title:patientLabel}],showCharges&&dailyCols.push({data:"Unpaid",title:"Unpaid",className:"hhk-justify-r"}),dailyCols.push({data:"Visit_Notes",title:"Last Visit Note",sortable:!1}),dailyCols.push({data:"Notes",title:"Room Notes",sortable:!1}),$.widget("ui.autocomplete",$.ui.autocomplete,{_resizeMenu:function(){let e=this.menu.element;e.outerWidth(1.1*Math.max(e.width("").outerWidth()+1,this.element.outerWidth()))}}),""!==pmtMkup&&$("#paymentMessage").html(pmtMkup).show("pulsate",{},400),$('input[type="button"], input[type="submit"]').button(),$.datepicker.setDefaults({yearRange:"-10:+02",changeMonth:!0,changeYear:!0,autoSize:!0,numberOfMonths:2,dateFormat:"M d, yy"}),$.extend($.fn.dataTable.defaults,{dom:'<"dtTop"if>rt<"dtBottom"lp><"clear">',displayLength:50,lengthMenu:[[25,50,-1],[25,50,"All"]],order:[[3,"asc"]],processing:!0,deferRender:!0}),$("#vstays").on("click",".applyDisc",function(e){e.preventDefault(),$(".hhk-alert").hide(),getApplyDiscDiag($(this).data("vid"),$("#pmtRcpt"))}),$("#vstays").on("click",".stckout",function(e){e.preventDefault(),$(".hhk-alert").hide(),ckOut($(this).data("name"),$(this).data("id"),$(this).data("vid"),$(this).data("spn"))}),$("#vstays").on("click",".stvisit",function(e){e.preventDefault(),$(".hhk-alert").hide(),editVisit($(this).data("name"),$(this).data("id"),$(this).data("vid"),$(this).data("spn"))}),$("#vstays").on("click",".hhk-getPSGDialog",function(e){e.preventDefault(),$(".hhk-alert").hide(),editPSG($(this).data("psg"))}),$("#vstays").on("click",".stchgrooms",function(e){e.preventDefault(),$(".hhk-alert").hide(),showChangeRoom($(this).data("name"),$(this).data("id"),$(this).data("vid"),$(this).data("spn"))}),$("#vstays").on("click",".stcleaning",function(e){e.preventDefault(),$(".hhk-alert").hide(),chgRoomCleanStatus($(this).data("idroom"),$(this).data("clean"))}),$("#vresvs, #vwls, #vuncon").on("click",".resvStat",function(e){e.preventDefault(),$(".hhk-alert").hide(),cgResvStatus($(this).data("rid"),$(this).data("stat"))}),$(".ckdate").datepicker(),$("#regckindate").val(moment().format("MMM DD, YYYY")),$("#statEvents").dialog({autoOpen:!1,resizable:!0,width:getDialogWidth(830),modal:!0,title:"Manage Status Events"}),$("#statEvents").data("uiDialog")._focusTabbable=function(){},$("#keysfees").dialog({autoOpen:!1,resizable:!0,modal:!0,close:function(e,t){$("div#submitButtons").show()},open:function(e,t){$("div#submitButtons").hide()}}),$(document).mousedown(function(e){e.target.id&&void 0!==e.target.id&&"pudiv"!==e.target.id&&$("div#pudiv").remove()}),$("#faDialog").dialog({autoOpen:!1,resizable:!0,width:getDialogWidth(650),modal:!0,title:"Income Chooser"}),$("#setBillDate").dialog({autoOpen:!1,resizable:!0,modal:!0,title:"Set Invoice Billing Date"}),$("#chgRoomDialog").dialog({autoOpen:!1,resizable:!0,modal:!0}),$("#pmtRcpt").dialog({autoOpen:!1,resizable:!0,width:getDialogWidth(530),modal:!0,title:"Payment Receipt"}),""===$("#txtactstart").val()){let a=new Date;a.setTime(a.getTime()-432e6),$("#txtactstart").datepicker("setDate",a)}if(""===$("#txtfeestart").val()){let s=new Date;s.setTime(s.getTime()-2592e5),$("#txtfeestart").datepicker("setDate",s)}$("#txtsearch").keypress(function(e){let t=$(this).val();"13"==e.keyCode&&(""!==t&&isNumber(parseInt(t,10))?(t>0&&window.location.assign("GuestEdit.php?id="+t),e.preventDefault()):(alert("Don't press the return key unless you enter an Id."),e.preventDefault()))}),createAutoComplete($("#txtsearch"),3,{cmd:"role",mode:"mo",gp:"1"},function(e){let t=e.id;t>0&&window.location.assign("GuestEdit.php?id="+t)},!1);let o=null;calDateIncrement>0&&calDateIncrement<5&&(o={weeks:calDateIncrement}),$("#selRoomGroupScheme").val(resourceGroupBy);let r=window.innerHeight;window.innerWidth<576?defaultView="timeline4days":window.innerWidth<=768&&(defaultView="timeline1weeks");let i=document.getElementById("calendar");(calendar=new FullCalendar.Calendar(i,{schedulerLicenseKey:"CC-Attribution-NonCommercial-NoDerivatives",height:r-187,firstDay:0,dateIncrement:o,nextDayThreshold:"13:00",eventColor:defaultEventColor,eventTextColor:defCalEventTextColor,eventResizableFromStart:!1,initialView:defaultView,editable:!0,resourcesInitiallyExpanded:expandResources,resourceAreaHeaderContent:"Rooms",nowIndicator:!1,resourceAreaWidth:resourceColumnWidth,refetchResourcesOnNavigate:!0,resourceGroupField:resourceGroupBy,customButtons:{refresh:{text:"Refresh",click:function(){calendar.refetchResources(),calendar.refetchEvents()}},prevMonth:{click:function(){calendar.incrementDate({months:-1})}},nextMonth:{click:function(){calendar.incrementDate({months:1})}}},buttonIcons:{nextMonth:"chevrons-right",prevMonth:"chevrons-left"},views:{timeline4days:{type:"resourceTimeline",slotDuration:{days:1},slotLabelFormat:{weekday:"short",day:"numeric"},duration:{days:4},buttonText:"4 Days"},timeline1weeks:{type:"resourceTimeline",slotDuration:{days:1},slotLabelFormat:{weekday:"short",day:"numeric"},duration:{weeks:1},buttonText:"1"},timeline2weeks:{type:"resourceTimeline",slotLabelFormat:{weekday:"short",day:"numeric"},slotDuration:{days:1},duration:{weeks:2},buttonText:"2"},timeline3weeks:{type:"resourceTimeline",slotLabelFormat:{weekday:"short",day:"numeric"},slotDuration:{days:1},duration:{weeks:3},buttonText:"3"},timeline4weeks:{type:"resourceTimeline",slotLabelFormat:[{month:"short",year:"numeric"},{day:"numeric"}],slotDuration:{days:7},duration:{weeks:26},buttonText:"26"}},headerToolbar:{left:"title",center:"",right:"timeline1weeks,timeline2weeks,timeline3weeks,timeline4weeks refresh,today prevMonth,prev,next,nextMonth"},slotLabelClassNames:"hhk-fc-slot-title",slotLaneClassNames:function(e){if(e.isToday)return"hhk-fcslot-today"},loading:function(e){e?($("#pCalLoad").show(),$("#spnGotoDate").hide()):($("#pCalLoad").hide(),$("#spnGotoDate").show())},resourceOrder:"Util_Priority,idResc",resources:{url:"ws_calendar.php",extraParams:{cmd:"resclist",gpby:$("#selRoomGroupScheme").val()}},resourceLabelDidMount:function(e){e.el.style.background=e.resource.extendedProps.bgColor,e.el.style.color=e.resource.extendedProps.textColor,e.resource.extendedProps.idResc>0&&(e.resource.extendedProps.hoverText?e.el.title=e.resource.extendedProps.hoverText:e.el.title="Maximum Occupants: "+e.resource.extendedProps.maxOcc,e.el.style.cursor="pointer",e.el.onclick=function(){getStatusEvent(e.resource.extendedProps.idResc,"resc",e.resource.title)})},eventOverlap:function(e,t){return e.idVisit===t.idVisit},events:{url:"ws_calendar.php?cmd=eventlist",failure:function(){$("#pCalError").text("Error getting events!").show()}},eventDrop:function(e){$(".hhk-alert").hide();let t=e.event;if(t.extendedProps.idVisit>0&&0!==e.delta.days&&confirm("Move Visit to a new start date?")){moveVisit("visitMove",t.extendedProps.idVisit,t.extendedProps.Span,e.delta.days,e.delta.days);return}if(t.extendedProps.idReservation>0){let a=t.getResources()[0];if(0!==e.delta.days&&a.extendedProps.idResc===t.extendedProps.idResc){if(confirm("Move Reservation to a new start date?")){moveVisit("reservMove",t.extendedProps.idReservation,0,e.delta.days,e.delta.days);return}}else 0!==e.delta.days&&confirm("Move Reservation to a new start date?")&&moveVisit("reservMove",t.extendedProps.idReservation,0,e.delta.days,e.delta.days,!1);if(a.extendedProps.idResc!==t.extendedProps.idResc){let s="Move Reservation to a new room?";if(0==a.extendedProps.idResc&&(s="Move Reservation to the waitlist?"),confirm(s)&&setRoomTo(t.extendedProps.idReservation,a.extendedProps.idResc))return}}e.revert()},eventResize:function(e){if($(".hhk-alert").hide(),void 0===e.endDelta){e.revert();return}if(e.event.extendedProps.idVisit>0&&confirm("Move check out date?")){moveVisit("visitMove",e.event.extendedProps.idVisit,e.event.extendedProps.Span,0,e.endDelta.days);return}if(e.event.extendedProps.idReservation>0&&confirm("Move expected end date?")){moveVisit("reservMove",e.event.extendedProps.idReservation,0,0,e.endDelta.days);return}e.revert()},eventClick:function(e){if($(".hhk-alert").hide(),e.event.extendedProps.kind&&"oos"===e.event.extendedProps.kind){getStatusEvent(e.event.extendedProps.idResc,"resc",e.event.title);return}if(e.event.extendedProps.idReservation&&e.event.extendedProps.idReservation>0){if(e.jsEvent.target.classList.contains("hhk-schrm")){getRoomList(e.event.extendedProps.idReservation,e.jsEvent.target.id,e.jsEvent.target);return}window.location.assign(resvPageName+"?rid="+e.event.extendedProps.idReservation)}e.event.extendedProps.idVisit&&e.event.extendedProps.idVisit>0&&viewVisit(0,e.event.extendedProps.idVisit,{"Show Statement":function(){window.open("ShowStatement.php?vid="+e.event.extendedProps.idVisit,"_blank")},"Show Registration Form":function(){window.open("ShowRegForm.php?vid="+e.event.extendedProps.idVisit+"&span="+e.event.extendedProps.Span,"_blank")},Save:function(){saveFees(0,e.event.extendedProps.idVisit,e.event.extendedProps.Span,!0,"register.php")},Cancel:function(){$(this).dialog("close")}},"Edit Visit #"+e.event.extendedProps.idVisit+"-"+e.event.extendedProps.Span,"",e.event.extendedProps.Span)},eventContent:function(e){if(void 0!==e.event.extendedProps.idReservation){let t=document.createElement("span");t.appendChild(document.createTextNode(e.event.title));let a=document.createElement("Span");return a.classList.add("hhk-schrm","ui-icon","ui-icon-arrowthick-2-n-s"),a.style.backgroundColor="#fff",a.style.border="0px solid black",a.style.marginRight=".3em",a.id=e.event.extendedProps.idResc,{domNodes:[a,t]}}},eventDidMount:function(e){if(void 0===t||0===t||void 0===e.event.extendedProps.idHosp||e.event.extendedProps.idAssoc==t||e.event.extendedProps.idHosp==t){let a=calendar.getResourceById("id-"+e.event.extendedProps.idResc);void 0!==e.event.extendedProps.idReservation?(e.el.title=e.event.extendedProps.fullName+(e.event.extendedProps.idResc>0?", "+a.title:"")+", "+e.event.extendedProps.resvStatus+(shoHospitalName?", "+e.event.extendedProps.hospName:""),"uc"===e.event.extendedProps.status?(e.el.style.border="3px dashed black",e.el.style.padding="1px 0"):(e.el.style.border="3px solid black",e.el.style.padding="1px 0"),""!=e.event.extendedProps.backBorderColor&&(e.el.style.cssText+="box-shadow: "+e.event.extendedProps.backBorderColor+" 0px 9px 0 0; margin-bottom:10px;")):void 0!==e.event.extendedProps.idVisit?("a"==e.event.extendedProps.vStatusCode?e.el.title=e.event.extendedProps.fullName+", Room: "+a.title+", Status: "+e.event.extendedProps.visitStatus+", "+e.event.extendedProps.guests+(e.event.extendedProps.guests>1?" "+visitorLabel+"s":" "+visitorLabel)+(shoHospitalName?", "+hospTitle+": "+e.event.extendedProps.hospName:""):e.el.title=e.event.extendedProps.fullName+", Room: "+a.title+", Status: "+e.event.extendedProps.visitStatus+(shoHospitalName?", "+hospTitle+": "+e.event.extendedProps.hospName:""),void 0!==e.event.extendedProps.extended&&e.event.extendedProps.extended&&e.el.classList.remove("fc-event-end"),""!=e.event.extendedProps.backBorderColor&&(e.el.style.cssText+="box-shadow: "+e.event.extendedProps.backBorderColor+" 0px 9px 0 0; margin-bottom:10px;")):"oos"===e.event.extendedProps.kind&&(e.el.title=e.event.extendedProps.reason),e.event.setProp("display","auto")}else e.event.setProp("display","none")}})).render(),window.onresize=function(){clearTimeout(e),e=setTimeout(calendar.setOption("height",window.innerHeight-187),100)},$(".btnHosp").length>0&&$(".btnHosp").click(function(e){e.preventDefault(),$(".hhk-alert").hide(),$(".btnHosp").removeClass("hospActive"),$(this).addClass("hospActive"),isNaN(t=parseInt($(this).data("id"),10))&&(t=0),calendar.refetchEvents()}),$("#btnFeesGo").click(function(){$(".hhk-alert").hide();let e=$("#txtfeestart").datepicker("getDate");if(null===e){$("#txtfeestart").addClass("ui-state-highlight"),flagAlertMessage("Enter start date","alert");return}$("#txtfeestart").removeClass("ui-state-highlight");let t=$("#txtfeeend").datepicker("getDate");null===t&&(t=new Date);let a=$("#selPayStatus").val()||[],s=$("#selPayType").val()||[],o={cmd:"actrpt",start:e.toDateString(),end:t.toDateString(),st:a,pt:s};!1!==$("#fcbdinv").prop("checked")&&(o.sdinv="on"),$("#rptFeeLoading").show(),o.fee="on",$.post("ws_resc.php",o,function(e){if($("#rptFeeLoading").hide(),e){try{e=$.parseJSON(e)}catch(t){alert("Parser error - "+t.message);return}e.error?(e.gotopage&&window.open(e.gotopage,"_self"),flagAlertMessage(e.error,"error")):e.success&&($("#rptfeediv").remove(),$("#vfees").append($('<div id="rptfeediv"/>').append($(e.success))),paymentsTable("feesTable","rptfeediv",refreshPayments),$("#btnPayHistRef").hide())}})}),$("#btnInvGo").click(function(){$.post("ws_resc.php",{cmd:"actrpt",st:["up"],inv:"on"},function(e){if(e){try{e=$.parseJSON(e)}catch(t){alert("Parser error - "+t.message);return}e.error?(e.gotopage&&window.open(e.gotopage,"_self"),flagAlertMessage(e.error,"error")):e.success&&($("#rptInvdiv").remove(),$("#vInv").append($('<div id="rptInvdiv" style="min-height:500px;"/>').append($(e.success))),$("#rptInvdiv .gmenu").menu({focus:function(e,t){$("#rptInvdiv .gmenu").not(this).menu("collapseAll",null,!0)}}),$("#rptInvdiv").on("click",".invLoadPc",function(e){e.preventDefault(),$("#divAlert1, #paymentMessage").hide(),invLoadPc($(this).data("name"),$(this).data("id"),$(this).data("iid"))}),$("#rptInvdiv").on("click",".invSetBill",function(e){e.preventDefault(),$(".hhk-alert").hide(),invSetBill($(this).data("inb"),$(this).data("name"),"div#setBillDate","#trBillDate"+$(this).data("inb"),$("#trBillDate"+$(this).data("inb")).text(),$("#divInvNotes"+$(this).data("inb")).text(),"#divInvNotes"+$(this).data("inb"))}),$("#rptInvdiv").on("click",".invAction",function(e){if(e.preventDefault(),$(".hhk-alert").hide(),"del"!=$(this).data("stat")||confirm("Delete this Invoice?")){if("vem"===$(this).data("stat")){window.open("ShowInvoice.php?invnum="+$(this).data("inb"));return}invoiceAction($(this).data("iid"),$(this).data("stat"),e.target.id),$("#rptInvdiv .gmenu").menu("collapse")}}),$("#InvTable").dataTable({columnDefs:[{targets:[2,4],type:"date",render:function(e,t,a){return dateRender(e,t)}}],dom:'<"top"if><"hhk-overflow-x hhk-tbl-wrap"rt><"bottom"lp><"clear">',displayLength:50,lengthMenu:[[20,50,100,-1],[20,50,100,"All"]],order:[[1,"asc"]]}))}})}),$("#btnPrintRegForm").click(function(){window.open($(this).data("page")+"?d="+$("#regckindate").val(),"_blank")}),$("#btnPrintWL").click(function(){window.open($(this).data("page")+"?d="+$("#regwldate").val(),"_blank")}),$("#btnPrtDaily").button().click(function(){$("#divdaily").printArea()}),$("#btnRefreshDaily").button().click(function(){$("#daily").DataTable().ajax.reload()}),$("#txtGotoDate").change(function(){$(".hhk-alert").hide(),calendar.gotoDate($(this).datepicker("getDate"))}),$("#selRoomGroupScheme").change(function(){$("#divRoomGrouping").hide(),calendar.setOption("resourceGroupField",$(this).val())}),""!==rctMkup&&showReceipt("#pmtRcpt",rctMkup,"Payment Receipt"),$("#mainTabs").tabs({beforeActivate:function(e,t){"liInvoice"===t.newTab.prop("id")&&$("#btnInvGo").click(),"liDaylog"!==t.newTab.prop("id")||$dailyTbl||($dailyTbl=$("#daily").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=daily",dataSrc:"daily"},order:[[0,"asc"]],columns:dailyCols,infoCallback:function(e,t,a,s,o,r){return"Prepared: "+dateRender(new Date().toISOString(),"display","ddd, MMM D YYYY, h:mm a")},dom:'<"top"if><"hhk-overflow-x hhk-tbl-wrap"rt><"bottom ui-toolbar ui-helper-clearfix"lp>'})),"liStaffNotes"===t.newTab.prop("id")&&$(".staffNotesDiv").empty().notesViewer({linkType:"staff",newNoteAttrs:{id:"staffNewNote",name:"staffNewNote"},newNoteLocation:"top",defaultLength:25,defaultLengthMenu:[[5,10,25,50],["5","10","25","50"]],alertMessage:function(e,t){flagAlertMessage(e,t)}}),"liCal"===t.newTab.prop("id")&&calendar.refetchEvents()},active:defaultTab}),$("#calendar .fc-header-toolbar .fc-toolbar-chunk:nth-child(2)").html($("#divGoto").show()),$("#calendar .fc-header-toolbar .fc-toolbar-chunk:nth-child(3) .fc-button-group:nth-child(1)").addClass("hideMobile"),$("#hhk-loading-spinner").hide(),$("#mainTabs").show(),$.ajax({url:"ws_resc.php",dataType:"JSON",type:"get",data:{cmd:"listforms",totalsonly:"true"},success:function(e){e.totals&&($("#vreferrals").referralViewer({statuses:e.totals,labels:{patient:patientLabel,referralFormTitle:referralFormTitleLabel,reservation:reservationLabel}}),$("#spnNumReferral").text(e.totals.n.count))}}),$("#curres").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=curres",dataSrc:"curres"},drawCallback:function(e){$("#spnNumCurrent").text(this.api().rows().data().length),$("#curres .gmenu").menu({focus:function(e,t){$("#curres .gmenu").not(this).menu("collapseAll",null,!0)}})},columns:cgCols,dom:'<"top"if><"hhk-overflow-x"rt><"bottom ui-toolbar ui-helper-clearfix"lp>'}),$("#reservs").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=reservs",dataSrc:"reservs"},drawCallback:function(e){$("#spnNumConfirmed").text(this.api().rows().data().length),$("#reservs .gmenu").menu({focus:function(e,t){$("#reservs .gmenu").not(this).menu("collapseAll",null,!0)}})},columns:rvCols,dom:'<"top"if><"hhk-overflow-x"rt><"bottom ui-toolbar ui-helper-clearfix"lp>'}),$("#unreserv").length>0&&$("#unreserv").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=unreserv",dataSrc:"unreserv"},drawCallback:function(e){$("#spnNumUnconfirmed").text(this.api().rows().data().length),$("#unreserv .gmenu").menu({focus:function(e,t){$("#unreserv .gmenu").not(this).menu("collapseAll",null,!0)}})},columns:rvCols,dom:'<"top"if><"hhk-overflow-x"rt><"bottom ui-toolbar ui-helper-clearfix"lp>'}),$("#waitlist").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=waitlist",dataSrc:"waitlist"},order:[[showCreatedDate?5:3,"asc"]],drawCallback:function(){$("#spnNumWaitlist").text(this.api().rows().data().length),$("#waitlist .gmenu").menu({focus:function(e,t){$("#waitlist .gmenu").not(this).menu("collapseAll",null,!0)}})},columns:wlCols,dom:'<"top"if><"hhk-overflow-x"rt><"bottom ui-toolbar ui-helper-clearfix"lp>'})});
 