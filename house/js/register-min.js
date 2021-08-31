function isNumber(e){"use strict";return!isNaN(parseFloat(e))&&isFinite(e)}function setRoomTo(e,t){$.post("ws_resv.php",{cmd:"moveResvRoom",rid:e,idResc:t},function(e){try{e=$.parseJSON(e)}catch(e){return alert("Parser error - "+e.message),!1}return e.error?(e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,"error"),!1):e.warning&&""!==e.warning?(flagAlertMessage(e.warning,"alert"),!1):(e.msg&&""!==e.msg&&flagAlertMessage(e.msg,"info"),$("#calendar").fullCalendar("refetchEvents"),void refreshdTables(e))})}var $dailyTbl,isGuestAdmin,pmtMkup,rctMkup,defaultTab,resourceGroupBy,resourceColumnWidth,patientLabel,guestLabel,visitorLabel,challVar,defaultView,defaultEventColor,defCalEventTextColor,calDateIncrement,dateFormat,fixedRate,resvPageName,showCreatedDate,expandResources,shoHospitalName,showRateCol,hospTitle,showDiags,showLocs,locationTitle,diagnosisTitle,showWlNotes,showCharges,wlTitle,cgCols,rvCols,wlCols,dailyCols;function refreshdTables(e){"use strict";e.curres&&$("#divcurres").length>0&&$("#curres").DataTable().ajax.reload();e.reservs&&$("div#vresvs").length>0&&$("#reservs").DataTable().ajax.reload();e.waitlist&&$("div#vwls").length>0&&$("#waitlist").DataTable().ajax.reload();e.unreserv&&$("div#vuncon").length>0&&$("#unreserv").DataTable().ajax.reload();$("#daily").length>0&&$dailyTbl&&$dailyTbl.ajax.reload()}function cgResvStatus(e,t){$.post("ws_ckin.php",{cmd:"rvstat",rid:e,stat:t},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)return e.gotopage&&window.location.assign(e.gotopage),void flagAlertMessage(e.error,"error");e.success&&(flagAlertMessage(e.success,"info"),$("#calendar").fullCalendar("refetchEvents")),refreshdTables(e)}})}function chgRoomCleanStatus(e,t){"use strict";confirm("Change the room status?")&&$.post("ws_resc.php",{cmd:"saveRmCleanCode",idr:e,stat:t},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)return e.gotopage&&window.location.assign(e.gotopage),void flagAlertMessage("Server error - "+e.error,"error");refreshdTables(e),e.msg&&""!=e.msg&&flagAlertMessage(e.msg,"info")}})}function payFee(e,t,a,i){viewVisit(t,a,{"Show Statement":function(){window.open("ShowStatement.php?vid="+a,"_blank")},"Pay Fees":function(){saveFees(t,a,i,!1,"register.php")},Cancel:function(){$(this).dialog("close")}},"Pay Fees for "+e,"pf",i)}function editPSG(e){var t={Cancel:function(){$(this).dialog("close")}};$.post("ws_ckin.php",{cmd:"viewPSG",psg:e},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,"error");else if(e.markup){var a=$("div#keysfees");a.children().remove(),a.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(e.markup))),a.dialog("option","buttons",t),a.dialog("option","title","View Patient Support Group"),a.dialog("option","width",900),a.dialog("open")}}})}function ckOut(e,t,a,i){viewVisit(t,a,{"Show Statement":function(){window.open("ShowStatement.php?vid="+a,"_blank")},"Show Registration Form":function(){window.open("ShowRegForm.php?vid="+a+"&span="+i,"_blank")},"Check Out":function(){saveFees(t,a,i,!0,"register.php")},Cancel:function(){$(this).dialog("close")}},"Check Out "+e,"co",i)}function editVisit(e,t,a,i){viewVisit(t,a,{"Show Statement":function(){window.open("ShowStatement.php?vid="+a,"_blank")},"Show Registration Form":function(){window.open("ShowRegForm.php?vid="+a+"&span="+i,"_blank")},Save:function(){saveFees(t,a,i,!0,"register.php")},Cancel:function(){$(this).dialog("close")}},"Edit Visit #"+a+"-"+i,"",i)}function getStatusEvent(e,t,a){"use strict";$.post("ws_resc.php",{cmd:"getStatEvent",tp:t,title:a,id:e},function(a){if(a){try{a=$.parseJSON(a)}catch(e){return void alert("Parser error - "+e.message)}if(a.error)a.gotopage&&window.location.assign(a.gotopage),alert("Server error - "+a.error);else if(a.tbl){$("#statEvents").children().remove().end().append($(a.tbl)),$(".ckdate").datepicker({autoSize:!0,dateFormat:"M d, yy"});var i={Save:function(){saveStatusEvent(e,t)},Cancel:function(){$(this).dialog("close")}};$("#statEvents").dialog("option","buttons",i),$("#statEvents").dialog("open")}}})}function saveStatusEvent(e,t){"use strict";$.post("ws_resc.php",$("#statForm").serialize()+"&cmd=saveStatEvent&id="+e+"&tp="+t,function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error&&(e.gotopage&&window.location.assign(e.gotopage),alert("Server error - "+e.error)),e.reload&&1==e.reload&&($("#calendar").fullCalendar("refetchResources"),$("#calendar").fullCalendar("refetchEvents")),e.msg&&""!=e.msg&&flagAlertMessage(e.msg,"info")}$("#statEvents").dialog("close")})}function updateRescChooser(e,t,a,i,s){var o,r=$("#selResource");0!==r.length&&(o=r.find("option:selected").val(),r.prop("disabled",!0),$("#hhk-roomChsrtitle").addClass("hhk-loading"),$("#hhkroomMsg").text("").hide(),cbRS={},$("input.hhk-constraintsCB:checked").each(function(){cbRS[$(this).data("cnid")]="ON"}),$.post("ws_ckin.php",{cmd:"newConstraint",rid:e,numguests:t,expArr:i,expDep:s,idr:o,cbRS:cbRS,omsf:!0},function(e){var t;r.prop("disabled",!1),$("#hhk-roomChsrtitle").removeClass("hhk-loading");try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)return e.gotopage&&window.location.assign(e.gotopage),void flagAlertMessage(e.error,"error");e.selectr&&(t=$(e.selectr),r.children().remove(),t.children().appendTo(r),r.val(e.idResource).change(),e.msg&&""!==e.msg&&$("#hhkroomMsg").text(e.msg).show()),e.rooms?rooms=e.rooms:rooms={}}))}function cgRoom(e,t,a,i){var s="Change Rooms for "+e,o={"Change Rooms":function(){$("#selResource").val()>0?saveFees(t,a,i,!0,"register.php"):$("#rmDepMessage").text("Choose a room").show()},Cancel:function(){$(this).dialog("close")}};this.rooms={},$.post("ws_ckin.php",{cmd:"visitFees",idVisit:a,action:"cr",span:i},function(e){"use strict";if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)return e.gotopage?void window.location.assign(e.gotopage):void flagAlertMessage(e.error,"error");var t=$("#pmtRcpt");t.children().remove(),t.append($('<div class="hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(e.success))),t.find(".ckdate").datepicker({yearRange:"-07:+01",changeMonth:!0,changeYear:!0,autoSize:!0,numberOfMonths:1,maxDate:0,dateFormat:"M d, yy",onSelect:function(){this.lastShown=(new Date).getTime()},beforeShow:function(){var e=(new Date).getTime();return void 0===this.lastShown||e-this.lastShown>500},onClose:function(){$("#rbReplaceRoomnew").attr("checked","checked"),$(this).change()}}),updateRescChooser(e.idReservation,e.numGuests,e.cbRs,e.visitStart,e.expDep),t.on("change","input[name=rbReplaceRoom], input[name=resvChangeDate]",function(){var t="";"rpl"==$(this).val()?t=e.visitStart:$(this).val()&&"new"!=$(this).val()&&(t=$(this).val()),t&&updateRescChooser(e.idReservation,e.numGuests,e.cbRs,t,e.expDep)}),t.on("change","#selResource",function(){var a=$(this).val();rooms[a]&&e.deposit<rooms[a].key?t.find("#rmDepMessage").text("Deposit required").show():t.find("#rmDepMessage").empty().hide()}),t.dialog("option","title",s),t.dialog("option","width","400px"),t.dialog("option","buttons",o),t.dialog("open")}})}function moveVisit(e,t,a,i,s){$.post("ws_ckin.php",{cmd:e,idVisit:t,span:a,sdelta:i,edelta:s},function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error?(e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,"error")):e.success&&($("#calendar").fullCalendar("refetchEvents"),flagAlertMessage(e.success,"success"),refreshdTables(e))}})}function getRoomList(e,t){e&&$.post("ws_ckin.php",{cmd:"rmlist",rid:e,x:t},function(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)return e.gotopage&&window.location.assign(e.gotopage),void flagAlertMessage(e.error,"error");if(e.container){var t=$(e.container);$("body").append(t),t.position({my:"top",at:"bottom",of:"#"+e.eid}),$("#selRoom").change(function(){""!=$("#selRoom").val()?(confirm("Change room to "+$("#selRoom option:selected").text()+"?")&&setRoomTo(e.rid,$("#selRoom").val()),t.remove()):t.remove()})}})}function refreshPayments(){$("#btnFeesGo").click()}$(document).ready(function(){"use strict";var e,t=0,a=new moment;(isGuestAdmin=$("#isGuestAdmin").val(),pmtMkup=$("#pmtMkup").val(),rctMkup=$("#rctMkup").val(),defaultTab=$("#defaultTab").val(),resourceGroupBy=$("#resourceGroupBy").val(),resourceColumnWidth=$("#resourceColumnWidth").val(),patientLabel=$("#patientLabel").val(),visitorLabel=$("#visitorLabel").val(),guestLabel=$("#guestLabel").val(),challVar=$("#challVar").val(),defaultView=$("#defaultView").val(),defaultEventColor=$("#defaultEventColor").val(),defCalEventTextColor=$("#defCalEventTextColor").val(),calDateIncrement=$("#calDateIncrement").val(),dateFormat=$("#dateFormat").val(),fixedRate=$("#fixedRate").val(),resvPageName=$("#resvPageName").val(),showCreatedDate=$("#showCreatedDate").val(),expandResources=$("#expandResources").val(),shoHospitalName=$("#shoHospitalName").val(),showRateCol=$("#showRateCol").val(),hospTitle=$("#hospTitle").val(),showDiags=$("#showDiags").val(),showLocs=$("#showLocs").val(),locationTitle=$("#locationTitle").val(),diagnosisTitle=$("#diagnosisTitle").val(),showWlNotes=$("#showWlNotes").val(),wlTitle=$("#wlTitle").val(),showCharges=$("#showCharges").val(),cgCols=[{data:"Action",title:"Action",sortable:!1,searchable:!1},{data:visitorLabel+" First",title:visitorLabel+" First"},{data:visitorLabel+" Last",title:visitorLabel+" Last"},{data:"Checked In",title:"Checked In",render:function(e,t){return dateRender(e,t,dateFormat)}},{data:"Nights",title:"Nights",className:"hhk-justify-c"},{data:"Expected Departure",title:"Expected Departure",render:function(e,t){return dateRender(e,t,dateFormat)}},{data:"Room",title:"Room",className:"hhk-justify-c"}],showRateCol&&cgCols.push({data:"Rate",title:"Rate"}),cgCols.push({data:"Phone",title:"Phone"}),shoHospitalName&&cgCols.push({data:"Hospital",title:hospTitle}),cgCols.push({data:"Patient",title:patientLabel}),rvCols=[{data:"Action",title:"Action",sortable:!1,searchable:!1},{data:"Guest First",title:visitorLabel+" First"},{data:"Guest Last",title:visitorLabel+" Last"},{data:"Expected Arrival",title:"Expected Arrival",render:function(e,t){return dateRender(e,t,dateFormat)}},{data:"Nights",title:"Nights",className:"hhk-justify-c"},{data:"Expected Departure",title:"Expected Departure",render:function(e,t){return dateRender(e,t,dateFormat)}},{data:"Room",title:"Room",className:"hhk-justify-c"}],showRateCol&&rvCols.push({data:"Rate",title:"Rate"}),rvCols.push({data:"Occupants",title:"Occupants",className:"hhk-justify-c"}),shoHospitalName&&rvCols.push({data:"Hospital",title:hospTitle}),showLocs&&rvCols.push({data:"Location",title:locationTitle}),showDiags&&rvCols.push({data:"Diagnosis",title:diagnosisTitle}),rvCols.push({data:"Patient",title:patientLabel}),wlCols=[{data:"Action",title:"Action",sortable:!1,searchable:!1},{data:"Guest First",title:visitorLabel+" First"},{data:"Guest Last",title:visitorLabel+" Last"}],showCreatedDate&&(wlCols.push({data:"Timestamp",title:"Created On",render:function(e,t){return dateRender(e,t,"MMM D, YYYY H:mm")}}),wlCols.push({data:"Updated_By",title:"Updated By"})),wlCols.push({data:"Expected Arrival",title:"Expected Arrival",render:function(e,t){return dateRender(e,t,dateFormat)}}),wlCols.push({data:"Nights",title:"Nights",className:"hhk-justify-c"}),wlCols.push({data:"Expected Departure",title:"Expected Departure",render:function(e,t){return dateRender(e,t,dateFormat)}}),wlCols.push({data:"Occupants",title:"Occupants",className:"hhk-justify-c"}),shoHospitalName&&wlCols.push({data:"Hospital",title:hospTitle}),showLocs&&wlCols.push({data:"Location",title:locationTitle}),showDiags&&wlCols.push({data:"Diagnosis",title:diagnosisTitle}),wlCols.push({data:"Patient",title:patientLabel}),showWlNotes&&wlCols.push({data:"WL Notes",title:wlTitle}),dailyCols=[{data:"titleSort",visible:!1},{data:"Title",title:"Room",orderData:[0,1],className:"hhk-justify-c"},{data:"Status",title:"Status",searchable:!1},{data:"Guests",title:visitorLabel+"s"},{data:"Patient_Name",title:patientLabel}],showCharges&&dailyCols.push({data:"Unpaid",title:"Unpaid",className:"hhk-justify-r"}),dailyCols.push({data:"Visit_Notes",title:"Last Visit Note",sortable:!1}),dailyCols.push({data:"Notes",title:"Room Notes",sortable:!1}),$.widget("ui.autocomplete",$.ui.autocomplete,{_resizeMenu:function(){var e=this.menu.element;e.outerWidth(1.1*Math.max(e.width("").outerWidth()+1,this.element.outerWidth()))}}),""!==pmtMkup&&$("#paymentMessage").html(pmtMkup).show("pulsate",{},400),$(':input[type="button"], :input[type="submit"]').button(),$.datepicker.setDefaults({yearRange:"-10:+02",changeMonth:!0,changeYear:!0,autoSize:!0,numberOfMonths:2,dateFormat:"M d, yy"}),$.extend($.fn.dataTable.defaults,{dom:'<"dtTop"if>rt<"dtBottom"lp><"clear">',displayLength:50,lengthMenu:[[25,50,-1],[25,50,"All"]],order:[[3,"asc"]],processing:!0,deferRender:!0}),$("#vstays").on("click",".stpayFees",function(e){e.preventDefault(),$(".hhk-alert").hide(),payFee($(this).data("name"),$(this).data("id"),$(this).data("vid"),$(this).data("spn"))}),$("#vstays").on("click",".applyDisc",function(e){e.preventDefault(),$(".hhk-alert").hide(),getApplyDiscDiag($(this).data("vid"),$("#pmtRcpt"))}),$("#vstays").on("click",".stckout",function(e){e.preventDefault(),$(".hhk-alert").hide(),ckOut($(this).data("name"),$(this).data("id"),$(this).data("vid"),$(this).data("spn"))}),$("#vstays").on("click",".stvisit",function(e){e.preventDefault(),$(".hhk-alert").hide(),editVisit($(this).data("name"),$(this).data("id"),$(this).data("vid"),$(this).data("spn"))}),$("#vstays").on("click",".hhk-getPSGDialog",function(e){e.preventDefault(),$(".hhk-alert").hide(),editPSG($(this).data("psg"))}),$("#vstays").on("click",".stchgrooms",function(e){e.preventDefault(),$(".hhk-alert").hide(),cgRoom($(this).data("name"),$(this).data("id"),$(this).data("vid"),$(this).data("spn"))}),$("#vstays").on("click",".stcleaning",function(e){e.preventDefault(),$(".hhk-alert").hide(),chgRoomCleanStatus($(this).data("idroom"),$(this).data("clean"))}),$("#vresvs, #vwls, #vuncon").on("click",".resvStat",function(e){e.preventDefault(),$(".hhk-alert").hide(),cgResvStatus($(this).data("rid"),$(this).data("stat"))}),$(".ckdate").datepicker(),$("#regckindate").val(moment().format("MMM DD, YYYY")),$("#statEvents").dialog({autoOpen:!1,resizable:!0,width:830,modal:!0,title:"Manage Status Events"}),$("#keysfees").dialog({autoOpen:!1,resizable:!0,modal:!0,close:function(e,t){$("div#submitButtons").show()},open:function(e,t){$("div#submitButtons").hide()}}),$(document).mousedown(function(e){var t=$(e.target);"pudiv"!==t[0].id&&0===t.parents("#pudiv").length&&$("div#pudiv").remove()}),$("#faDialog").dialog({autoOpen:!1,resizable:!0,width:650,modal:!0,title:"Income Chooser"}),$("#setBillDate").dialog({autoOpen:!1,resizable:!0,modal:!0,title:"Set Invoice Billing Date"}),$("#pmtRcpt").dialog({autoOpen:!1,resizable:!0,width:530,modal:!0,title:"Payment Receipt"}),""===$("#txtactstart").val())&&((e=new Date).setTime(e.getTime()-432e6),$("#txtactstart").datepicker("setDate",e));""===$("#txtfeestart").val()&&((e=new Date).setTime(e.getTime()-2592e5),$("#txtfeestart").datepicker("setDate",e));$("#txtsearch").keypress(function(e){var t=$(this).val();"13"==e.keyCode&&(""!==t&&isNumber(parseInt(t,10))?(t>0&&window.location.assign("GuestEdit.php?id="+t),e.preventDefault()):(alert("Don't press the return key unless you enter an Id."),e.preventDefault()))}),createAutoComplete($("#txtsearch"),3,{cmd:"role",mode:"mo",gp:"1"},function(e){var t=e.id;t>0&&window.location.assign("GuestEdit.php?id="+t)},!1);var i=null;calDateIncrement>0&&calDateIncrement<5&&(i={weeks:calDateIncrement}),$("#selRoomGroupScheme").val(resourceGroupBy);var s=window.innerHeight;$("#calendar").fullCalendar({height:s-175,themeSystem:"jquery-ui",allDay:!0,firstDay:0,dateIncrement:i,nextDayThreshold:"13:00",schedulerLicenseKey:"CC-Attribution-NonCommercial-NoDerivatives",eventColor:defaultEventColor,eventTextColor:defCalEventTextColor,customButtons:{refresh:{text:"Refresh",click:function(){$("#calendar").fullCalendar("refetchResources"),$("#calendar").fullCalendar("refetchEvents")}},prevMonth:{click:function(){$("#calendar").fullCalendar("incrementDate",{months:-1})},themeIcon:"ui-icon-seek-prev"},nextMonth:{click:function(){$("#calendar").fullCalendar("incrementDate",{months:1})},themeIcon:"ui-icon-seek-next"},setup:{click:function(){$("#divRoomGrouping").show("fade")},themeIcon:"ui-icon-gear"}},views:{timeline1weeks:{type:"timeline",slotDuration:{days:1},duration:{weeks:1},buttonText:"1"},timeline2weeks:{type:"timeline",slotDuration:{days:1},duration:{weeks:2},buttonText:"2"},timeline3weeks:{type:"timeline",slotDuration:{days:1},duration:{weeks:3},buttonText:"3"},timeline4weeks:{type:"timeline",slotDuration:{days:7},duration:{weeks:26},buttonText:"26"}},viewRender:function(e,t){defaultView=e.name,a=$("#calendar").fullCalendar("getDate")},header:{left:"setup timeline1weeks,timeline2weeks,timeline3weeks,timeline4weeks title",center:"",right:"refresh,today prevMonth,prev,next,nextMonth"},defaultView:defaultView,editable:!0,resourcesInitiallyExpanded:expandResources,resourceLabelText:"Rooms",resourceAreaWidth:resourceColumnWidth,refetchResourcesOnNavigate:!1,resourceGroupField:resourceGroupBy,loading:function(e,t){e?($("#pCalLoad").show(),$("#spnGotoDate").hide()):($("#pCalLoad").hide(),$("#spnGotoDate").show())},resources:function(e){$.ajax({url:"ws_calendar.php",dataType:"JSON",data:{cmd:"resclist",start:a.format("YYYY-MM-DD"),view:defaultView,gpby:$("#selRoomGroupScheme").val()},success:function(t){e(t)},error:function(e,t,a){$("#pCalError").text("Error getting resources: "+a).show()}})},resourceGroupText:function(e){return e},resourceRender:function(e,t,a){if(t.css("background",e.bgColor).css("color",e.textColor),e.id>0){var i=e.title+(0==e.maxOcc?"":" ("+e.maxOcc+")");t.prop("title",i),t.click(function(){getStatusEvent(e.id,"resc",e.title)})}},eventOverlap:function(e,t){return"bak"===e.kind||e.idVisit===t.idVisit},events:{url:"ws_calendar.php?cmd=eventlist",error:function(e,t,a){$("#pCalError").text("Error getting events: "+a).show()}},eventDrop:function(e,t,a){if($(".hhk-alert").hide(),e.idVisit>0&&0!==t.asDays()&&confirm("Move Visit to a new start date?")&&moveVisit("visitMove",e.idVisit,e.Span,t.asDays(),t.asDays()),e.idReservation>0){if(0!==t.asDays()&&confirm("Move Reservation to a new start date?"))return void moveVisit("reservMove",e.idReservation,0,t.asDays(),t.asDays());if(e.resourceId!==e.idResc){var i="Move Reservation to a new room?";if(0==e.resourceId&&(i="Move Reservation to the waitlist?"),confirm(i)&&setRoomTo(e.idReservation,e.resourceId))return}}a()},eventResize:function(e,t,a){$(".hhk-alert").hide(),void 0!==t?e.idVisit>0&&confirm("Move check out date?")?moveVisit("visitMove",e.idVisit,e.Span,0,t.asDays()):e.idReservation>0&&confirm("Move expected end date?")?moveVisit("reservMove",e.idReservation,0,0,t.asDays()):a():a()},eventClick:function(e,t){if($(".hhk-alert").hide(),e.kind&&"oos"===e.kind)getStatusEvent(e.resourceId,"resc",e.title);else{if(e.idReservation&&e.idReservation>0){if(t.target.classList.contains("hhk-schrm"))return void getRoomList(e.idReservation,t.target.id);window.location.assign(resvPageName+"?rid="+e.idReservation)}if(e.idVisit&&e.idVisit>0){var a={"Show Statement":function(){window.open("ShowStatement.php?vid="+e.idVisit,"_blank")},"Show Registration Form":function(){window.open("ShowRegForm.php?vid="+e.idVisit+"&span="+e.Span,"_blank")},Save:function(){saveFees(0,e.idVisit,e.Span,!0,"register.php")},Cancel:function(){$(this).dialog("close")}};viewVisit(0,e.idVisit,a,"Edit Visit #"+e.idVisit+"-"+e.Span,"",e.Span)}}},eventRender:function(e,a){if(void 0===t||0===t||void 0===e.idHosp||e.idAssoc==t||e.idHosp==t){var i=$("#calendar").fullCalendar("getResourceById",e.resourceId);void 0!==e.idReservation?(a.prop("title",e.fullName+(e.resourceId>0?", Room: "+i.title:"")+", Status: "+e.resvStatus+(shoHospitalName?", "+hospTitle+": "+e.hospName:"")),"uc"===e.status?a.css("border","2px dashed black").css("padding","1px 0"):a.css("border","2px solid black").css("padding","1px 0")):void 0!==e.idVisit?(a.prop("title",e.fullName+", Room: "+i.title+", Status: "+e.visitStatus+", "+e.guests+(e.guests>1?" "+visitorLabel+"s":" "+visitorLabel)+(shoHospitalName?", "+hospTitle+": "+e.hospName:"")),void 0!==e.extended&&e.extended&&a.find("div.fc-content").append($('<span style="float:right;margin-right:5px;" class="hhk-fc-title"/>'))):"oos"===e.kind&&a.prop("title",e.reason),a.show()}else a.hide()}}),$(document).mousedown(function(e){var t=$(e.target);"divRoomGrouping"!==t[0].id&&"selRoomGroupScheme"!==t[0].id&&$("#divRoomGrouping").hide()}),$(".spnHosp").length>0&&$(".spnHosp").click(function(){$(".hhk-alert").hide(),$(".spnHosp").css("border","solid 1px black").css("font-size","100%"),t=parseInt($(this).data("id"),10),isNaN(t)&&(t=0),$("#calendar").fullCalendar("rerenderEvents"),$(this).css("border","solid 3px black").css("font-size","120%")}),$("#btnActvtyGo").click(function(){$(".hhk-alert").hide();var e=$("#txtactstart").datepicker("getDate");if(null===e)return $("#txtactstart").addClass("ui-state-highlight"),void flagAlertMessage("Enter start date","alert");$("#txtactstart").removeClass("ui-state-highlight");var t=$("#txtactend").datepicker("getDate");null===t&&(t=new Date);var a={cmd:"actrpt",start:e.toLocaleDateString(),end:t.toLocaleDateString()};$("#cbVisits").prop("checked")&&(a.visit="on"),$("#cbReserv").prop("checked")&&(a.resv="on"),$("#cbHospStay").prop("checked")&&(a.hstay="on"),$.post("ws_resc.php",a,function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error?(e.gotopage&&window.open(e.gotopage,"_self"),flagAlertMessage(e.error,"error")):e.success&&($("#rptdiv").remove(),$("#vactivity").append($('<div id="rptdiv"/>').append($(e.success))),$(".hhk-viewvisit").css("cursor","pointer"),$("#rptdiv").on("click",".hhk-viewvisit",function(){if($(this).data("visitid")){var e=$(this).data("visitid").split("_");if(2!==e.length)return;var t={Save:function(){saveFees(0,e[0],e[1])},Cancel:function(){$(this).dialog("close")}};viewVisit(0,e[0],t,"View Visit","n",e[1])}else $(this).data("reservid")&&window.location.assign("Reserve.php?rid="+$(this).data("reservid"))}))}})}),$("#btnFeesGo").click(function(){$(".hhk-alert").hide();var e=$("#txtfeestart").datepicker("getDate");if(null===e)return $("#txtfeestart").addClass("ui-state-highlight"),void flagAlertMessage("Enter start date","alert");$("#txtfeestart").removeClass("ui-state-highlight");var t=$("#txtfeeend").datepicker("getDate");null===t&&(t=new Date);var a=$("#selPayStatus").val()||[],i=$("#selPayType").val()||[],s={cmd:"actrpt",start:e.toDateString(),end:t.toDateString(),st:a,pt:i};!1!==$("#fcbdinv").prop("checked")&&(s.sdinv="on"),$("#rptFeeLoading").show(),s.fee="on",$.post("ws_resc.php",s,function(e){if($("#rptFeeLoading").hide(),e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error?(e.gotopage&&window.open(e.gotopage,"_self"),flagAlertMessage(e.error,"error")):e.success&&($("#rptfeediv").remove(),$("#vfees").append($('<div id="rptfeediv"/>').append($(e.success))),paymentsTable("feesTable","rptfeediv",refreshPayments),$("#btnPayHistRef").hide())}})}),$("#btnInvGo").click(function(){var e={cmd:"actrpt",st:["up"],inv:"on"};$.post("ws_resc.php",e,function(e){if(e){try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}e.error?(e.gotopage&&window.open(e.gotopage,"_self"),flagAlertMessage(e.error,"error")):e.success&&($("#rptInvdiv").remove(),$("#vInv").append($('<div id="rptInvdiv" style="min-height:500px;"/>').append($(e.success))),$("#rptInvdiv .gmenu").menu(),$("#rptInvdiv").on("click",".invLoadPc",function(e){e.preventDefault(),$("#divAlert1, #paymentMessage").hide(),invLoadPc($(this).data("name"),$(this).data("id"),$(this).data("iid"))}),$("#rptInvdiv").on("click",".invSetBill",function(e){e.preventDefault(),$(".hhk-alert").hide(),invSetBill($(this).data("inb"),$(this).data("name"),"div#setBillDate","#trBillDate"+$(this).data("inb"),$("#trBillDate"+$(this).data("inb")).text(),$("#divInvNotes"+$(this).data("inb")).text(),"#divInvNotes"+$(this).data("inb"))}),$("#rptInvdiv").on("click",".invAction",function(e){e.preventDefault(),$(".hhk-alert").hide(),("del"!=$(this).data("stat")||confirm("Delete this Invoice?"))&&("vem"!==$(this).data("stat")?(invoiceAction($(this).data("iid"),$(this).data("stat"),e.target.id),$("#rptInvdiv .gmenu").menu("collapse")):window.open("ShowInvoice.php?invnum="+$(this).data("inb")))}),$("#InvTable").dataTable({columnDefs:[{targets:[2,4],type:"date",render:function(e,t,a){return dateRender(e,t)}}],dom:'<"top"if>rt<"bottom"lp><"clear">',displayLength:50,lengthMenu:[[20,50,100,-1],[20,50,100,"All"]],order:[[1,"asc"]]}))}})}),$("#btnPrintRegForm").click(function(){window.open($(this).data("page")+"?d="+$("#regckindate").val(),"_blank")}),$("#btnPrintWL").click(function(){window.open($(this).data("page")+"?d="+$("#regwldate").val(),"_blank")}),$("#btnPrtDaily").button().click(function(){$("#divdaily").printArea()}),$("#btnRefreshDaily").button().click(function(){$("#daily").DataTable().ajax.reload()}),$("#txtGotoDate").change(function(){$(".hhk-alert").hide(),a=new moment($(this).datepicker("getDate")),$("#calendar").fullCalendar("refetchResources"),$("#calendar").fullCalendar("gotoDate",a)}),$("#selRoomGroupScheme").change(function(){$("#divRoomGrouping").hide(),$("#calendar").fullCalendar("option","resourceGroupField",$(this).val()),$("#calendar").fullCalendar("refetchResources")}),""!==rctMkup&&showReceipt("#pmtRcpt",rctMkup,"Payment Receipt"),$("#mainTabs").tabs({beforeActivate:function(e,t){"liInvoice"===t.newTab.prop("id")&&$("#btnInvGo").click(),"liDaylog"!==t.newTab.prop("id")||$dailyTbl||($dailyTbl=$("#daily").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=daily",dataSrc:"daily"},order:[[0,"asc"]],columns:dailyCols,infoCallback:function(e,t,a,i,s,o){return"Prepared: "+dateRender((new Date).toISOString(),"display","ddd, MMM D YYYY, h:mm a")}}))},activate:function(e,t){"liCal"===t.newTab.prop("id")&&($("#calendar").fullCalendar("render"),$("#divGoto").position({my:"center top",at:"center top+8",of:"#calendar",within:"#calendar"}))},active:defaultTab}),$("#mainTabs").show(),$("#divGoto").position({my:"center top",at:"center top+8",of:"#calendar",within:"#calendar"}),$("#curres").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=curres",dataSrc:"curres"},drawCallback:function(e){$("#spnNumCurrent").text(this.api().rows().data().length),$("#curres .gmenu").menu()},columns:cgCols}),$("#reservs").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=reservs",dataSrc:"reservs"},drawCallback:function(e){$("#spnNumConfirmed").text(this.api().rows().data().length),$("#reservs .gmenu").menu()},columns:rvCols}),$("#unreserv").length>0&&$("#unreserv").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=unreserv",dataSrc:"unreserv"},drawCallback:function(e){$("#spnNumUnconfirmed").text(this.api().rows().data().length),$("#unreserv .gmenu").menu()},columns:rvCols}),$("#waitlist").DataTable({ajax:{url:"ws_resc.php?cmd=getHist&tbl=waitlist",dataSrc:"waitlist"},order:[[showCreatedDate?4:3,"asc"]],drawCallback:function(){$("#spnNumWaitlist").text(this.api().rows().data().length),$("#waitlist .gmenu").menu()},columns:wlCols})});