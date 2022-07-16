var $dailyTbl,isGuestAdmin,pmtMkup,rctMkup,defaultTab,resourceGroupBy,resourceColumnWidth,patientLabel,guestLabel,visitorLabel,referralFormTitleLabel,reservationLabel,challVar,defaultView,defaultEventColor,defCalEventTextColor,calDateIncrement,dateFormat,fixedRate,resvPageName,showCreatedDate,expandResources,shoHospitalName,showRateCol,hospTitle,showDiags,showLocs,locationTitle,diagnosisTitle,showWlNotes,showCharges,wlTitle,cgCols,rvCols,wlCols,dailyCols,calendar,calStartDate;/* global pmtMkup, rvCols, wlCols, roomCnt, viewDays, rctMkup, defaultTab, isGuestAdmin */ /**
 * register.js
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 */ /**
 * 
 * @param {mixed} n
 * @returns {Boolean}
 */ function isNumber(a){return!isNaN(parseFloat(a))&&isFinite(a)}function setRoomTo(a,b){$.post("ws_resv.php",{cmd:"moveResvRoom",rid:a,idResc:b},function(a){try{a=$.parseJSON(a)}catch(b){return alert("Parser error - "+b.message),!1}return a.error?(a.gotopage&&window.location.assign(a.gotopage),flagAlertMessage(a.error,"error"),!1):a.warning&&""!==a.warning?(flagAlertMessage(a.warning,"alert"),!1):void(a.msg&&""!==a.msg&&flagAlertMessage(a.msg,"info"),calendar.refetchEvents(),refreshdTables(a))})}function refreshdTables(a){"use strict";a.curres&&$("#divcurres").length>0&&$("#curres").DataTable().ajax.reload(),a.reservs&&$("div#vresvs").length>0&&$("#reservs").DataTable().ajax.reload(),a.waitlist&&$("div#vwls").length>0&&$("#waitlist").DataTable().ajax.reload(),a.unreserv&&$("div#vuncon").length>0&&$("#unreserv").DataTable().ajax.reload(),$("#daily").length>0&&$dailyTbl&&$dailyTbl.ajax.reload()}function cgResvStatus(a,b){$.post("ws_ckin.php",{cmd:"rvstat",rid:a,stat:b},function(a){if(a){try{a=$.parseJSON(a)}catch(b){alert("Parser error - "+b.message);return}if(a.error){a.gotopage&&window.location.assign(a.gotopage),flagAlertMessage(a.error,"error");return}a.success&&(flagAlertMessage(a.success,"info"),calendar.refetchEvents()),refreshdTables(a)}})}function chgRoomCleanStatus(a,b){"use strict";confirm("Change the room status?")&&$.post("ws_resc.php",{cmd:"saveRmCleanCode",idr:a,stat:b},function(a){if(a){try{a=$.parseJSON(a)}catch(b){alert("Parser error - "+b.message);return}if(a.error){a.gotopage&&window.location.assign(a.gotopage),flagAlertMessage("Server error - "+a.error,"error");return}refreshdTables(a),a.msg&&""!=a.msg&&flagAlertMessage(a.msg,"info")}})}function editPSG(a){var b={Close:function(){$(this).dialog("close")}};$.post("ws_ckin.php",{cmd:"viewPSG",psg:a},function(a){if(a){try{a=$.parseJSON(a)}catch(d){alert("Parser error - "+d.message);return}if(a.error)a.gotopage&&window.location.assign(a.gotopage),flagAlertMessage(a.error,"error");else if(a.markup){let c=$("div#keysfees");c.children().remove(),c.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(a.markup))),c.dialog("option","buttons",b),c.dialog("option","title","View Patient Support Group"),c.dialog("option","width",900),c.dialog("open")}}})}function ckOut(a,b,c,d){viewVisit(b,c,{"Show Statement":function(){window.open("ShowStatement.php?vid="+c,"_blank")},"Show Registration Form":function(){window.open("ShowRegForm.php?vid="+c+"&span="+d,"_blank")},"Check Out":function(){saveFees(b,c,d,!0,"register.php")},Cancel:function(){$(this).dialog("close")}},"Check Out "+a,"co",d)}function editVisit(d,c,a,b){viewVisit(c,a,{"Show Statement":function(){window.open("ShowStatement.php?vid="+a,"_blank")},"Show Registration Form":function(){window.open("ShowRegForm.php?vid="+a+"&span="+b,"_blank")},Save:function(){saveFees(c,a,b,!0,"register.php")},Cancel:function(){$(this).dialog("close")}},"Edit Visit #"+a+"-"+b,"",b)}function getStatusEvent(a,b,c){"use strict";$.post("ws_resc.php",{cmd:"getStatEvent",tp:b,title:c,id:a},function(c){if(c){try{c=$.parseJSON(c)}catch(d){alert("Parser error - "+d.message);return}if(c.error)c.gotopage&&window.location.assign(c.gotopage),alert("Server error - "+c.error);else if(c.tbl){$("#statEvents").children().remove().end().append($(c.tbl)),$(".ckdate").datepicker({autoSize:!0,dateFormat:"M d, yy"});var e={Save:function(){saveStatusEvent(a,b)},Cancel:function(){$(this).dialog("close")}};$("#statEvents").dialog("option","buttons",e),$("#statEvents").dialog("open")}}})}function saveStatusEvent(a,b){"use strict";$.post("ws_resc.php",$("#statForm").serialize()+"&cmd=saveStatEvent&id="+a+"&tp="+b,function(a){if(a){try{a=$.parseJSON(a)}catch(b){alert("Parser error - "+b.message);return}a.error&&(a.gotopage&&window.location.assign(a.gotopage),alert("Server error - "+a.error)),a.reload&&1==a.reload&&(calendar.refetchResources(),calendar.refetchEvents()),a.msg&&""!=a.msg&&flagAlertMessage(a.msg,"info")}$("#statEvents").dialog("close")})}function showChangeRoom(d,a,b,c){function e(b,c,d,a){a.prop("disabled",!0),$("#hhk-roomChsrtitle").addClass("hhk-loading"),$("#rmDepMessage").text("").hide(),d=new Date;let e={cmd:"chgRoomList",idVisit:b,span:c,chgDate:d.toDateString(),selRescId:a.val()};$.post("ws_ckin.php",e,function(b){let c;a.prop("disabled",!1),$("#hhk-roomChsrtitle").removeClass("hhk-loading");try{b=$.parseJSON(b)}catch(d){alert("Parser error - "+d.message);return}if(b.error){b.gotopage&&window.open(b.gotopage),flagAlertMessage(b.error,"error");return}b.sel&&(c=$(b.sel),a.children().remove(),c.children().appendTo(a),a.val(b.idResc).change()),rooms=b.rooms?b.rooms:{}})}this.rooms={},$.post("ws_ckin.php",{cmd:"showChangeRooms",idVisit:b,span:c,idGuest:a},function(a){"use strict";if(a){try{a=$.parseJSON(a)}catch(i){alert("Parser error - "+i.message);return}if(a.error){if(a.gotopage){window.location.assign(a.gotopage);return}flagAlertMessage(a.error,"error");return}let j=new Date(a.start),f=$("#chgRoomDialog");f.children().remove(),f.append($('<div class="hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(a.success)));let h=f.find("#selResource"),g=$("#resvChangeDate"),k=$("input[name=rbReplaceRoom]"),m=$("#cbUseDefaultRate");g.datepicker({yearRange:"-05:+00",changeMonth:!0,changeYear:!0,autoSize:!0,numberOfMonths:1,maxDate:0,minDate:j,dateFormat:"M d, yy"}),g.datepicker("setDate",new Date),k.change(function(){"new"==$(this).val()&&""!==g.val()?e(b,c,g.datepicker("getDate"),h):"rpl"==$(this).val()&&e(b,c,j,h)}),g.change(function(){$("input[name=rbReplaceRoomnew]").prop("checked",!0),e(b,c,g.datepicker("getDate"),h)}),rooms=a.rooms?a.rooms:{},h.change(function(){let b=$(this).val();rooms[b]&&a.curResc.key<rooms[b].key?f.find("#rmDepMessage").text("Deposit required").show():f.find("#rmDepMessage").empty().hide(),""==a.curResc.defaultRateCat&&""!=rooms[b].defaultRateCat||""!=a.curResc.defaultRateCat&&""!=rooms[b].defaultRateCat&&a.curResc.defaultRateCat!=rooms[b].defaultRateCat?f.find("#trUseDefaultRate").show():f.find("#trUseDefaultRate").hide()}),h.change();let l={"Change Rooms":function(){var a,d,e,f,i,j;$("#selResource").val()>0?(a=b,d=c,e=h.val(),f=$('input[name="rbReplaceRoom"]:checked').val(),i=m.prop("checked"),j=g.datepicker("getDate").toUTCString(),$.post("ws_ckin.php",{cmd:"doChangeRooms",idVisit:a,span:d,idRoom:e,replaceRoom:f,useDefault:i,changeDate:j},function(b){try{b=$.parseJSON(b)}catch(c){alert("Parser error - "+c.message);return}if(b.error){b.gotopage&&window.open(b.gotopage),flagAlertMessage(b.error,"error");return}b.openvisitviewer&&editVisit("",0,a,b.openvisitviewer),b.msg&&""!=b.msg&&flagAlertMessage(b.msg,"info"),calendar.refetchEvents(),refreshdTables(b)}),$(this).dialog("close")):$("#rmDepMessage").text("Choose a room").show()},Cancel:function(){$(this).dialog("close")}};f.dialog("option","title","Change Rooms for "+d),f.dialog("option","width","400px"),f.dialog("option","buttons",l),f.dialog("open")}})}function moveVisit(a,b,c,d,e,f){$.post("ws_ckin.php",{cmd:a,idVisit:b,span:c,sdelta:d,edelta:e},function(a){if(a){try{a=$.parseJSON(a)}catch(b){alert("Parser error - "+b.message);return}a.error?(a.gotopage&&window.location.assign(a.gotopage),flagAlertMessage(a.error,"error")):a.success&&(flagAlertMessage(a.success,"success"),(void 0===f|| !0===f)&&(calendar.refetchEvents(),refreshdTables(a)))}})}function getRoomList(a,b){a&&$.post("ws_ckin.php",{cmd:"rmlist",rid:a,x:b},function(a){try{a=$.parseJSON(a)}catch(c){alert("Parser error - "+c.message);return}if(a.error){a.gotopage&&window.location.assign(a.gotopage),flagAlertMessage(a.error,"error");return}if(a.container){let b=$(a.container);$("body").append(b),b.position({my:"top",at:"bottom",of:"#"+a.eid}),$("#selRoom").change(function(){if(""==$("#selRoom").val()){b.remove();return}confirm("Change room to "+$("#selRoom option:selected").text()+"?")&&setRoomTo(a.rid,$("#selRoom").val()),b.remove()})}})}function refreshPayments(){$("#btnFeesGo").click()}$(document).ready(function(){"use strict";var f=0;if(calStartDate=new moment,isGuestAdmin=$("#isGuestAdmin").val(),pmtMkup=$("#pmtMkup").val(),rctMkup=$("#rctMkup").val(),defaultTab=$("#defaultTab").val(),resourceGroupBy=$("#resourceGroupBy").val(),resourceColumnWidth=$("#resourceColumnWidth").val(),patientLabel=$("#patientLabel").val(),visitorLabel=$("#visitorLabel").val(),guestLabel=$("#guestLabel").val(),referralFormTitleLabel=$("#referralFormTitleLabel").val(),reservationLabel=$("#reservationLabel").val(),challVar=$("#challVar").val(),defaultView=$("#defaultView").val(),defaultEventColor=$("#defaultEventColor").val(),defCalEventTextColor=$("#defCalEventTextColor").val(),calDateIncrement=$("#calDateIncrement").val(),dateFormat=$("#dateFormat").val(),fixedRate=$("#fixedRate").val(),resvPageName=$("#resvPageName").val(),showCreatedDate=$("#showCreatedDate").val(),expandResources=$("#expandResources").val(),shoHospitalName=$("#shoHospitalName").val(),showRateCol=$("#showRateCol").val(),hospTitle=$("#hospTitle").val(),showDiags=$("#showDiags").val(),showLocs=$("#showLocs").val(),locationTitle=$("#locationTitle").val(),diagnosisTitle=$("#diagnosisTitle").val(),showWlNotes=$("#showWlNotes").val(),wlTitle=$("#wlTitle").val(),showCharges=$("#showCharges").val(),cgCols=[{data:"Action",title:"Action",sortable:!1,searchable:!1},{data:visitorLabel+" First",title:visitorLabel+" First"},{data:visitorLabel+" Last",title:visitorLabel+" Last"},{data:"Checked In",title:"Checked In",render:function(a,b){return dateRender(a,b,dateFormat)}},{data:"Nights",title:"Nights",className:"hhk-justify-c"},{data:"Expected Departure",title:"Expected Departure",render:function(a,b){return dateRender(a,b,dateFormat)}},{data:"Room",title:"Room",className:"hhk-justify-c"}],showRateCol&&cgCols.push({data:"Rate",title:"Rate"}),cgCols.push({data:"Phone",title:"Phone"}),shoHospitalName&&cgCols.push({data:"Hospital",title:hospTitle}),cgCols.push({data:"Patient",title:patientLabel}),rvCols=[{data:"Action",title:"Action",sortable:!1,searchable:!1},{data:"Guest First",title:visitorLabel+" First"},{data:"Guest Last",title:visitorLabel+" Last"},{data:"Expected Arrival",title:"Expected Arrival",render:function(a,b){return dateRender(a,b,dateFormat)}},{data:"Nights",title:"Nights",className:"hhk-justify-c"},{data:"Expected Departure",title:"Expected Departure",render:function(a,b){return dateRender(a,b,dateFormat)}},{data:"Room",title:"Room",className:"hhk-justify-c"}],showRateCol&&rvCols.push({data:"Rate",title:"Rate"}),rvCols.push({data:"Occupants",title:"Occupants",className:"hhk-justify-c"}),shoHospitalName&&rvCols.push({data:"Hospital",title:hospTitle}),showLocs&&rvCols.push({data:"Location",title:locationTitle}),showDiags&&rvCols.push({data:"Diagnosis",title:diagnosisTitle}),rvCols.push({data:"Patient",title:patientLabel}),wlCols=[{data:"Action",title:"Action",sortable:!1,searchable:!1},{data:"Guest First",title:visitorLabel+" First"},{data:"Guest Last",title:visitorLabel+" Last"}],showCreatedDate&&(wlCols.push({data:"Timestamp",title:"Created On",render:function(a,b){return dateRender(a,b,"MMM D, YYYY H:mm")}}),wlCols.push({data:"Updated_By",title:"Updated By"})),wlCols.push({data:"Expected Arrival",title:"Expected Arrival",render:function(a,b){return dateRender(a,b,dateFormat)}}),wlCols.push({data:"Nights",title:"Nights",className:"hhk-justify-c"}),wlCols.push({data:"Expected Departure",title:"Expected Departure",render:function(a,b){return dateRender(a,b,dateFormat)}}),wlCols.push({data:"Occupants",title:"Occupants",className:"hhk-justify-c"}),shoHospitalName&&wlCols.push({data:"Hospital",title:hospTitle}),showLocs&&wlCols.push({data:"Location",title:locationTitle}),showDiags&&wlCols.push({data:"Diagnosis",title:diagnosisTitle}),wlCols.push({data:"Patient",title:patientLabel}),showWlNotes&&wlCols.push({data:"WL Notes",title:wlTitle}),dailyCols=[{data:"titleSort",visible:!1},{data:"Title",title:"Room",orderData:[0,1],className:"hhk-justify-c"},{data:"Status",title:"Status",searchable:!1},{data:"Guests",title:visitorLabel+"s"},{data:"Patient_Name",title:patientLabel}],showCharges&&dailyCols.push({data:"Unpaid",title:"Unpaid",className:"hhk-justify-r"}),dailyCols.push({data:"Visit_Notes",title:"Last Visit Note",sortable:!1}),dailyCols.push({data:"Notes",title:"Room Notes",sortable:!1}),$.widget("ui.autocomplete",$.ui.autocomplete,{_resizeMenu:function(){let a=this.menu.element;a.outerWidth(1.1*Math.max(a.width("").outerWidth()+1,this.element.outerWidth()))}}),""!==pmtMkup&&$("#paymentMessage").html(pmtMkup).show("pulsate",{},400),$('input[type="button"], input[type="submit"]').button(),$.datepicker.setDefaults({yearRange:"-10:+02",changeMonth:!0,changeYear:!0,autoSize:!0,numberOfMonths:2,dateFormat:"M d, yy"}),$.extend($.fn.dataTable.defaults,{dom:'<"dtTop"if>rt<"dtBottom"lp><"clear">',displayLength:50,lengthMenu:[[25,50,-1],[25,50,"All"]],order:[[3,"asc"]],processing:!0,deferRender:!0}),$("#vstays").on("click",".applyDisc",function(a){a.preventDefault(),$(".hhk-alert").hide(),getApplyDiscDiag($(this).data("vid"),$("#pmtRcpt"))}),$("#vstays").on("click",".stckout",function(a){a.preventDefault(),$(".hhk-alert").hide(),ckOut($(this).data("name"),$(this).data("id"),$(this).data("vid"),$(this).data("spn"))}),$("#vstays").on("click",".stvisit",function(a){a.preventDefault(),$(".hhk-alert").hide(),editVisit($(this).data("name"),$(this).data("id"),$(this).data("vid"),$(this).data("spn"))}),$("#vstays").on("click",".hhk-getPSGDialog",function(a){a.preventDefault(),$(".hhk-alert").hide(),editPSG($(this).data("psg"))}),$("#vstays").on("click",".stchgrooms",function(a){a.preventDefault(),$(".hhk-alert").hide(),showChangeRoom($(this).data("name"),$(this).data("id"),$(this).data("vid"),$(this).data("spn"))}),$("#vstays").on("click",".stcleaning",function(a){a.preventDefault(),$(".hhk-alert").hide(),chgRoomCleanStatus($(this).data("idroom"),$(this).data("clean"))}),$("#vresvs, #vwls, #vuncon").on("click",".resvStat",function(a){a.preventDefault(),$(".hhk-alert").hide(),cgResvStatus($(this).data("rid"),$(this).data("stat"))}),$(".ckdate").datepicker(),$("#regckindate").val(moment().format("MMM DD, YYYY")),$("#statEvents").dialog({autoOpen:!1,resizable:!0,width:830,modal:!0,title:"Manage Status Events"}),$("#keysfees").dialog({autoOpen:!1,resizable:!0,modal:!0,close:function(a,b){$("div#submitButtons").show()},open:function(a,b){$("div#submitButtons").hide()}}),$(document).mousedown(function(b){let a=$(b.target);"pudiv"!==a[0].id&&0===a.parents("#pudiv").length&&$("div#pudiv").remove()}),$("#faDialog").dialog({autoOpen:!1,resizable:!0,width:650,modal:!0,title:"Income Chooser"}),$("#setBillDate").dialog({autoOpen:!1,resizable:!0,modal:!0,title:"Set Invoice Billing Date"}),$("#chgRoomDialog").dialog({autoOpen:!1,resizable:!0,modal:!0}),$("#pmtRcpt").dialog({autoOpen:!1,resizable:!0,width:530,modal:!0,title:"Payment Receipt"}),""===$("#txtactstart").val()){let a=new Date;a.setTime(a.getTime()-432e6),$("#txtactstart").datepicker("setDate",a)}if(""===$("#txtfeestart").val()){let b=new Date;b.setTime(b.getTime()-2592e5),$("#txtfeestart").datepicker("setDate",b)}$("#txtsearch").keypress(function(b){let a=$(this).val();"13"==b.keyCode&&(""!==a&&isNumber(parseInt(a,10))?(a>0&&window.location.assign("GuestEdit.php?id="+a),b.preventDefault()):(alert("Don't press the return key unless you enter an Id."),b.preventDefault()))}),createAutoComplete($("#txtsearch"),3,{cmd:"role",mode:"mo",gp:"1"},function(b){let a=b.id;a>0&&window.location.assign("GuestEdit.php?id="+a)},!1);let c=null;calDateIncrement>0&&calDateIncrement<5&&(c={weeks:calDateIncrement}),$("#selRoomGroupScheme").val(resourceGroupBy);let d=window.innerHeight;window.innerWidth<576?defaultView="timeline4days":window.innerWidth<=768&&(defaultView="timeline1weeks");let e=document.getElementById("calendar");(calendar=new FullCalendar.Calendar(e,{schedulerLicenseKey:"CC-Attribution-NonCommercial-NoDerivatives",height:d-175,firstDay:0,dateIncrement:c,nextDayThreshold:"13:00",eventColor:defaultEventColor,eventTextColor:defCalEventTextColor,eventResizableFromStart:!1,initialView:defaultView,editable:!0,resourcesInitiallyExpanded:expandResources,resourceAreaHeaderContent:"Rooms",nowIndicator:!1,resourceAreaWidth:resourceColumnWidth,refetchResourcesOnNavigate:!0,resourceGroupField:resourceGroupBy,resourceOrder:"",customButtons:{refresh:{text:"Refresh",click:function(){calendar.refetchResources(),calendar.refetchEvents()}},prevMonth:{click:function(){calendar.incrementDate({months:-1})}},nextMonth:{click:function(){calendar.incrementDate({months:1})}}},buttonIcons:{nextMonth:"chevrons-right",prevMonth:"chevrons-left"},views:{timeline4days:{type:"resourceTimeline",slotDuration:{days:1},slotLabelFormat:{weekday:"short",day:"numeric"},duration:{days:4},buttonText:"4 Days"},timeline1weeks:{type:"resourceTimeline",slotDuration:{days:1},slotLabelFormat:{weekday:"short",day:"numeric"},duration:{weeks:1},buttonText:"1"},timeline2weeks:{type:"resourceTimeline",slotLabelFormat:{weekday:"short",day:"numeric"},slotDuration:{days:1},duration:{weeks:2},buttonText:"2"},timeline3weeks:{type:"resourceTimeline",slotLabelFormat:{weekday:"short",day:"numeric"},slotDuration:{days:1},duration:{weeks:3},buttonText:"3"},timeline4weeks:{type:"resourceTimeline",slotLabelFormat:[{month:"short",year:"numeric"},{day:"numeric"}],slotDuration:{days:7},duration:{weeks:26},buttonText:"26"}},headerToolbar:{left:"title",center:"",right:"timeline1weeks,timeline2weeks,timeline3weeks,timeline4weeks refresh,today prevMonth,prev,next,nextMonth"},slotLabelClassNames:"hhk-fc-slot-title",slotLaneClassNames:function(a){if(a.isToday)return"hhk-fcslot-today"},loading:function(a){a?($("#pCalLoad").show(),$("#spnGotoDate").hide()):($("#pCalLoad").hide(),$("#spnGotoDate").show())},resources:{url:"ws_calendar.php",extraParams:{cmd:"resclist",gpby:$("#selRoomGroupScheme").val()}},resourceLabelDidMount:function(a){a.el.style.background=a.resource.extendedProps.bgColor,a.el.style.color=a.resource.extendedProps.textColor,a.resource.id>0&&(a.el.title="Maximum Occupants: "+a.resource.extendedProps.maxOcc,a.el.style.cursor="pointer",a.el.onclick=function(){getStatusEvent(a.resource.id,"resc",a.resource.title)})},eventOverlap:function(a,b){return a.idVisit===b.idVisit},events:{url:"ws_calendar.php?cmd=eventlist",failure:function(){$("#pCalError").text("Error getting events!").show()}},eventDrop:function(a){$(".hhk-alert").hide();let b=a.event;if(b.extendedProps.idVisit>0&&0!==a.delta.days&&confirm("Move Visit to a new start date?")){moveVisit("visitMove",b.extendedProps.idVisit,b.extendedProps.Span,a.delta.days,a.delta.days);return}if(b.extendedProps.idReservation>0){let c=b.getResources()[0];if(0!==a.delta.days&&c.id===b.extendedProps.idResc){if(confirm("Move Reservation to a new start date?")){moveVisit("reservMove",b.extendedProps.idReservation,0,a.delta.days,a.delta.days);return}}else 0!==a.delta.days&&confirm("Move Reservation to a new start date?")&&moveVisit("reservMove",b.extendedProps.idReservation,0,a.delta.days,a.delta.days,!1);if(c.id!==b.extendedProps.idResc){let d="Move Reservation to a new room?";if(0==c.id&&(d="Move Reservation to the waitlist?"),confirm(d)&&setRoomTo(b.extendedProps.idReservation,c.id))return}}a.revert()},eventResize:function(a){if($(".hhk-alert").hide(),void 0===a.endDelta){a.revert();return}if(a.event.extendedProps.idVisit>0&&confirm("Move check out date?")){moveVisit("visitMove",a.event.extendedProps.idVisit,a.event.extendedProps.Span,0,a.endDelta.days);return}if(a.event.extendedProps.idReservation>0&&confirm("Move expected end date?")){moveVisit("reservMove",a.event.extendedProps.idReservation,0,0,a.endDelta.days);return}a.revert()},eventClick:function(a){if($(".hhk-alert").hide(),a.event.extendedProps.kind&&"oos"===a.event.extendedProps.kind){getStatusEvent(a.event.extendedProps.idResc,"resc",a.event.title);return}if(a.event.extendedProps.idReservation&&a.event.extendedProps.idReservation>0){if(a.jsEvent.target.classList.contains("hhk-schrm")){getRoomList(a.event.extendedProps.idReservation,a.jsEvent.target.id);return}window.location.assign(resvPageName+"?rid="+a.event.extendedProps.idReservation)}if(a.event.extendedProps.idVisit&&a.event.extendedProps.idVisit>0){let b={"Show Statement":function(){window.open("ShowStatement.php?vid="+a.event.extendedProps.idVisit,"_blank")},"Show Registration Form":function(){window.open("ShowRegForm.php?vid="+a.event.extendedProps.idVisit+"&span="+a.event.extendedProps.Span,"_blank")},Save:function(){saveFees(0,a.event.extendedProps.idVisit,a.event.extendedProps.Span,!0,"register.php")},Cancel:function(){$(this).dialog("close")}};viewVisit(0,a.event.extendedProps.idVisit,b,"Edit Visit #"+a.event.extendedProps.idVisit+"-"+a.event.extendedProps.Span,"",a.event.extendedProps.Span)}},eventContent:function(b){if(void 0!==b.event.extendedProps.idReservation){let c=document.createElement("span");c.appendChild(document.createTextNode(b.event.title));let a=document.createElement("Span");return a.classList.add("hhk-schrm","ui-icon","ui-icon-arrowthick-2-n-s"),a.style.backgroundColor="#fff",a.style.border="0px solid black",a.style.marginRight=".3em",a.id=b.event.extendedProps.idResc,{domNodes:[a,c]}}},eventDidMount:function(a){if(void 0===f||0===f|| void 0===a.event.extendedProps.idHosp||a.event.extendedProps.idAssoc==f||a.event.extendedProps.idHosp==f){let b=calendar.getResourceById(a.event.extendedProps.idResc);void 0!==a.event.extendedProps.idReservation?(a.el.title=a.event.extendedProps.fullName+(a.event.extendedProps.idResc>0?", "+b.title:"")+", "+a.event.extendedProps.resvStatus+(shoHospitalName?", "+a.event.extendedProps.hospName:""),"uc"===a.event.extendedProps.status?(a.el.style.border="3px dashed black",a.el.style.padding="1px 0"):(a.el.style.border="3px solid black",a.el.style.padding="1px 0"),""!=a.event.extendedProps.backBorderColor&&(a.el.style.cssText+="border-bottom: 9px solid "+a.event.extendedProps.backBorderColor)):void 0!==a.event.extendedProps.idVisit?("a"==a.event.extendedProps.vStatusCode?a.el.title=a.event.extendedProps.fullName+", Room: "+b.title+", Status: "+a.event.extendedProps.visitStatus+", "+a.event.extendedProps.guests+(a.event.extendedProps.guests>1?" "+visitorLabel+"s":" "+visitorLabel)+(shoHospitalName?", "+hospTitle+": "+a.event.extendedProps.hospName:""):a.el.title=a.event.extendedProps.fullName+", Room: "+b.title+", Status: "+a.event.extendedProps.visitStatus+(shoHospitalName?", "+hospTitle+": "+a.event.extendedProps.hospName:""),void 0!==a.event.extendedProps.extended&&a.event.extendedProps.extended&&a.el.classList.remove("fc-event-end"),""!=a.event.extendedProps.backBorderColor&&(a.el.style.cssText+="border-bottom: 9px solid "+a.event.extendedProps.backBorderColor)):"oos"===a.event.extendedProps.kind&&(a.el.title=a.event.extendedProps.reason),a.event.setProp("display","auto")}else a.event.setProp("display","none")}})).render(),$(document).mousedown(function(b){var a=$(b.target);"divRoomGrouping"!==a[0].id&&"selRoomGroupScheme"!==a[0].id&&$("#divRoomGrouping").hide()}),$(".btnHosp").length>0&&$(".btnHosp").click(function(a){a.preventDefault(),$(".hhk-alert").hide(),$(".btnHosp").removeClass("hospActive"),$(this).addClass("hospActive"),isNaN(f=parseInt($(this).data("id"),10))&&(f=0),calendar.refetchEvents()}),$("#btnActvtyGo").click(function(){$(".hhk-alert").hide();let c=$("#txtactstart").datepicker("getDate");if(null===c){$("#txtactstart").addClass("ui-state-highlight"),flagAlertMessage("Enter start date","alert");return}$("#txtactstart").removeClass("ui-state-highlight");let b=$("#txtactend").datepicker("getDate");null===b&&(b=new Date);let a={cmd:"actrpt",start:c.toLocaleDateString(),end:b.toLocaleDateString()};$("#cbVisits").prop("checked")&&(a.visit="on"),$("#cbReserv").prop("checked")&&(a.resv="on"),$("#cbHospStay").prop("checked")&&(a.hstay="on"),$.post("ws_resc.php",a,function(a){if(a){try{a=$.parseJSON(a)}catch(b){alert("Parser error - "+b.message);return}a.error?(a.gotopage&&window.open(a.gotopage,"_self"),flagAlertMessage(a.error,"error")):a.success&&($("#rptdiv").remove(),$("#vactivity").append($('<div id="rptdiv"/>').append($(a.success))),$(".hhk-viewvisit").css("cursor","pointer"),$("#rptdiv").on("click",".hhk-viewvisit",function(){if($(this).data("visitid")){let a=$(this).data("visitid").split("_");if(2===a.length){var b={Save:function(){saveFees(0,a[0],a[1])},Cancel:function(){$(this).dialog("close")}};viewVisit(0,a[0],b,"View Visit","n",a[1])}}else $(this).data("reservid")&&window.location.assign("Reserve.php?rid="+$(this).data("reservid"))}))}})}),$("#btnFeesGo").click(function(){$(".hhk-alert").hide();let c=$("#txtfeestart").datepicker("getDate");if(null===c){$("#txtfeestart").addClass("ui-state-highlight"),flagAlertMessage("Enter start date","alert");return}$("#txtfeestart").removeClass("ui-state-highlight");let a=$("#txtfeeend").datepicker("getDate");null===a&&(a=new Date);let d=$("#selPayStatus").val()||[],e=$("#selPayType").val()||[],b={cmd:"actrpt",start:c.toDateString(),end:a.toDateString(),st:d,pt:e};!1!==$("#fcbdinv").prop("checked")&&(b.sdinv="on"),$("#rptFeeLoading").show(),b.fee="on",$.post("ws_resc.php",b,function(a){if($("#rptFeeLoading").hide(),a){try{a=$.parseJSON(a)}catch(b){alert("Parser error - "+b.message);return}a.error?(a.gotopage&&window.open(a.gotopage,"_self"),flagAlertMessage(a.error,"error")):a.success&&($("#rptfeediv").remove(),$("#vfees").append($('<div id="rptfeediv"/>').append($(a.success))),paymentsTable("feesTable","rptfeediv",refreshPayments),$("#btnPayHistRef").hide())}})}),$("#btnInvGo").click(function(){$.post("ws_resc.php",{cmd:"actrpt",st:["up"],inv:"on"},function(a){if(a){try{a=$.parseJSON(a)}catch(b){alert("Parser error - "+b.message);return}a.error?(a.gotopage&&window.open(a.gotopage,"_self"),flagAlertMessage(a.error,"error")):a.success&&($("#rptInvdiv").remove(),$("#vInv").append($('<div id="rptInvdiv" style="min-height:500px;"/>').append($(a.success))),$("#rptInvdiv .gmenu").menu({focus:function(a,b){$("#rptInvdiv .gmenu").not(this).menu("collapseAll",null,!0)}}),$("#rptInvdiv").on("click",".invLoadPc",function(a){a.preventDefault(),$("#divAlert1, #paymentMessage").hide(),invLoadPc($(this).data("name"),$(this).data("id"),$(this).data("iid"))}),$("#rptInvdiv").on("click",".invSetBill",function(a){a.preventDefault(),$(".hhk-alert").hide(),invSetBill($(this).data("inb"),$(this).data("name"),"div#setBillDate","#trBillDate"+$(this).data("inb"),$("#trBillDate"+$(this).data("inb")).text(),$("#divInvNotes"+$(this).data("inb")).text(),"#divInvNotes"+$(this).data("inb"))}),$("#rptInvdiv").on("click",".invAction",function(a){if(a.preventDefault(),$(".hhk-alert").hide(),"del"!=$(this).data("stat")||confirm("Delete this Invoice?")){if("vem"===$(this).data("stat")){window.open("ShowInvoice.php?invnum="+$(this).data("inb"));return}invoiceAction($(this).data("iid"),$(this).data("stat"),a.target.id),$("#rptInvdiv .gmenu").menu("collapse")}}),$("#InvTable").dataTable({columnDefs:[{targets:[2,4],type:"date",render:function(a,b,c){return dateRender(a,b)}}],dom:'<"top"if>rt<"bottom"lp><"clear">',displayLength:50,lengthMenu:[[20,50,100,-1],[20,50,100,"All"]],order:[[1,"asc"]]}))}})}),$("#btnPrintRegForm").click(function(){window.open($(this).data("page")+"?d="+$("#regckindate").val(),"_blank")}),$("#btnPrintWL").click(function(){window.open($(this).data("page")+"?d="+$("#regwldate").val(),"_blank")}),$("#btnPrtDaily").button().click(function(){$("#divdaily").printArea()}),$("#btnRefreshDaily").button().click(function(){$("#daily").DataTable().ajax.reload()}),$("#txtGotoDate").change(function(){$(".hhk-alert").hide(),calendar.gotoDate($(this).datepicker("getDate"))}),$("#selRoomGroupScheme").change(function(){$("#divRoomGrouping").hide(),calendar.setOption("resourceGroupField",$(this).val())}),""!==rctMkup&&showReceipt("#pmtRcpt",rctMkup,"Payment Receipt"),$("#mainTabs").tabs({beforeActivate:function(b,a){"liInvoice"===a.newTab.prop("id")&&$("#btnInvGo").click(),"liDaylog"!==a.newTab.prop("id")||$dailyTbl||($dailyTbl=$("#daily").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=daily",dataSrc:"daily"},order:[[0,"asc"]],columns:dailyCols,infoCallback:function(a,b,c,d,e,f){return"Prepared: "+dateRender(new Date().toISOString(),"display","ddd, MMM D YYYY, h:mm a")}})),"liStaffNotes"===a.newTab.prop("id")&&$(".staffNotesDiv").empty().notesViewer({linkType:"staff",newNoteAttrs:{id:"staffNewNote",name:"staffNewNote"},alertMessage:function(a,b){flagAlertMessage(a,b)}})},active:defaultTab}),$("#mainTabs").show(),$("#calendar .fc-header-toolbar .fc-toolbar-chunk:nth-child(2)").html($("#divGoto").show()),$.ajax({url:"ws_resc.php",dataType:"JSON",type:"get",data:{cmd:"listforms",totalsonly:"true"},success:function(a){a.totals&&($("#vreferrals").referralViewer({statuses:a.totals,labels:{patient:patientLabel,referralFormTitle:referralFormTitleLabel,reservation:reservationLabel}}),$("#spnNumReferral").text(a.totals.n.count))}}),$("#curres").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=curres",dataSrc:"curres"},drawCallback:function(a){$("#spnNumCurrent").text(this.api().rows().data().length),$("#curres .gmenu").menu({focus:function(a,b){$("#curres .gmenu").not(this).menu("collapseAll",null,!0)}})},columns:cgCols}),$("#reservs").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=reservs",dataSrc:"reservs"},drawCallback:function(a){$("#spnNumConfirmed").text(this.api().rows().data().length),$("#reservs .gmenu").menu({focus:function(a,b){$("#reservs .gmenu").not(this).menu("collapseAll",null,!0)}})},columns:rvCols}),$("#unreserv").length>0&&$("#unreserv").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=unreserv",dataSrc:"unreserv"},drawCallback:function(a){$("#spnNumUnconfirmed").text(this.api().rows().data().length),$("#unreserv .gmenu").menu({focus:function(a,b){$("#unreserv .gmenu").not(this).menu("collapseAll",null,!0)}})},columns:rvCols}),$("#waitlist").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=waitlist",dataSrc:"waitlist"},order:[[showCreatedDate?5:3,"asc"]],drawCallback:function(){$("#spnNumWaitlist").text(this.api().rows().data().length),$("#waitlist .gmenu").menu({focus:function(a,b){$("#waitlist .gmenu").not(this).menu("collapseAll",null,!0)}})},columns:wlCols})})