function flagAlertMessage(a,b){"use strict";var c=document.getElementById("alrMessage");b?($("alrResponse").removeClass("ui-state-highlight").addClass("ui-state-error"),$("#alrIcon").removeClass("ui-icon-info").addClass("ui-icon-alert"),c.innerHTML="<strong>Alert: </strong>"+a,$("#divAlert1").show("pulsate",{},400),window.scrollTo(0,5)):($("#alrResponse").removeClass("ui-state-error").addClass("ui-state-highlight"),$("#alrIcon").removeClass("ui-icon-alert").addClass("ui-icon-info"),c.innerHTML="<strong>Result: </strong>"+a,$("#divAlert1").show("pulsate",{},400),window.scrollTo(0,5))}function getMember(a,b,c){"use strict";function d(a,b,c,d){if(!a)return void alert("Bad Reply from Server");try{a=$.parseJSON(a)}catch(a){return void alert("Parser error - "+a.message)}if(a.error)return a.gotopage&&window.open(a.gotopage),void flagAlertMessage(a.error,!0);if($("#txtAddGuest").val(""),a.addtguest){$("#keysfees").dialog("close"),$("#diagAddGuest").remove();var f=$('<div style="font-size:.9em;"/>').append($(a.addtguest.memMkup)),g=$('<div style="min-height:30px; padding:3px;font-size:.9em;"/>').append($(a.addtguest.txtHdr)).append($('<span id="'+a.addtguest.idPrefix+'memMsg" style="color: red; margin-right:20px;margin-left:20px;margin-top:7px;"></span>')).addClass("ui-widget-header ui-state-default ui-corner-top"),h=$('<div id="diagAddGuest"/>').append($('<form id="fAddGuest"  style="font-size:.9em;"/>').append(g).append(f));return h.dialog({autoOpen:!1,resizable:!0,width:950,modal:!0,title:"Additional Guest",buttons:{Save:function(){e(b,c,d),$(this).dialog("close")},Cancel:function(){$(this).dialog("close")}}}),$("div#diagAddGuest .ckdate").datepicker(),h.find("select.bfh-countries").each(function(){var a=$(this);a.bfhcountries(a.data())}),h.find("select.bfh-states").each(function(){var a=$(this);a.bfhstates(a.data())}),$("#diagAddGuest #qphEmlTabs").tabs(),verifyAddrs("#diagAddGuest"),a.addr&&$("#diagAddGuest").on("click",".hhk-addrCopy",function(){$("#qadraddress11").val(a.addr.adraddress1),$("#qadraddress21").val(a.addr.adraddress2),$("#qadrcity1").val(a.addr.adrcity),$("#qadrstate1").val(a.addr.adrstate),$("#qadrzip1").val(a.addr.adrzip)}),$("#diagAddGuest").on("click",".hhk-addrErase",function(){$("#qadraddress11").val(""),$("#qadraddress21").val(""),$("#qadrcity1").val(""),$("#qadrstate1").val(""),$("#qadrzip1").val(""),$("#qadrbad1").prop("checked",!1)}),void h.dialog("open")}}function e(a,b,c){$.post("ws_ckin.php",$("#fAddGuest").serialize()+"&cmd=addStay&id="+a+"&vid="+b+"&span="+c,function(a){try{a=$.parseJSON(a)}catch(a){return void alert("Parser error - "+a.message)}a.error&&(a.gotopage&&window.open(a.gotopage),flagAlertMessage(a.error,!0)),a.stays&&""!==a.stays&&($("#keysfees").dialog("open"),$("#divksStays").children().remove(),$("#divksStays").append($(a.stays)))})}$.post("ws_ckin.php",{cmd:"addStay",vid:b,id:a.id,span:c},function(e){d(e,a.id,b,c)})}function getInvoicee(a,b){"use strict";var c=parseInt(a.id,10);isNaN(c)===!1&&c>0?($("#txtInvName").val(a.value),$("#txtInvId").val(c)):($("#txtInvName").val(""),$("#txtInvId").val("")),$("#txtOrderNum").val(b),$("#txtInvSearch").val("")}function viewVisit(a,b,c,d,e,f,g){"use strict";$.post("ws_ckin.php",{cmd:"visitFees",idVisit:b,idGuest:a,action:e,span:f,ckoutdt:g},function(g){if(g){try{g=$.parseJSON(g)}catch(a){return void alert("Parser error - "+a.message)}if(g.error)return g.gotopage?void window.location.assign(g.gotopage):void flagAlertMessage(g.error,!0);var h=$("#keysfees");if(h.children().remove(),h.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(g.success))),h.find(".ckdate").datepicker({yearRange:"-01:+01",changeMonth:!0,changeYear:!0,autoSize:!0,numberOfMonths:1,maxDate:0,dateFormat:"M d, yy",onSelect:function(){this.lastShown=(new Date).getTime()},beforeShow:function(){var a=(new Date).getTime();return void 0===this.lastShown||a-this.lastShown>500},onClose:function(){$(this).change()}}),h.find(".ckdateFut").datepicker({yearRange:"-01:+01",changeMonth:!0,changeYear:!0,autoSize:!0,numberOfMonths:1,minDate:0,dateFormat:"M d, yy",onSelect:function(){this.lastShown=(new Date).getTime()},beforeShow:function(){var a=(new Date).getTime();return void 0===this.lastShown||a-this.lastShown>500},onClose:function(){$(this).change()}}),h.css("background-color","#fff"),"ref"===e&&h.css("background-color","#FEFF9B"),$(".hhk-extVisitSw").length>0&&($(".hhk-extVisitSw").change(function(){this.checked?$(".hhk-extendVisit").show("fade"):$(".hhk-extendVisit").hide("fade")}),$(".hhk-extVisitSw").change()),$("#rateChgCB").length>0){var i=$("#chgRateDate");i.datepicker({changeMonth:!0,changeYear:!0,autoSize:!0,numberOfMonths:1,dateFormat:"M d, yy",maxDate:new Date(g.end),minDate:new Date(g.start)}),i.change(function(){""!==this.value&&i.siblings("input#rbReplaceRate").prop("checked",!0)}),$("input#rbReplaceRate").change(function(){this.checked&&""===i.val()?i.val($.datepicker.formatDate("M d, yy",new Date)):i.val("")}),$("#rateChgCB").change(function(){this.checked?($(".changeRateTd").show("fade"),$("#showRateTd").hide("fade")):($(".changeRateTd").hide("fade"),$("#showRateTd").show("fade"))}),$("#rateChgCB").change()}$("#spnExPay").hide(),isCheckedOut=!1;var j=0,k=0;if($("#spnCfBalDue").length>0&&(j=parseFloat($("#spnCfBalDue").data("bal")),k=parseFloat($("#spnCfBalDue").data("vfee")),j-=k),$("input.hhk-ckoutCB").length>0)$("#tblStays").on("change","input.hhk-ckoutCB",function(){var g=!0,i=1,l=new Date;if(this.checked===!1?$(this).next().val(""):""==$(this).next().val()&&$(this).next().val($.datepicker.formatDate("M d, yy",new Date)),$("input.hhk-ckoutCB").each(function(){if(this.checked===!1)g=!1;else if(""!=$(this).next().val()){var a=new Date($(this).next().val());a.getTime()>l.getTime()?($(this).next().val(""),g=!1):a.getTime()>i&&(i=a.getTime())}}),g===!0){isCheckedOut=!0;var l=new Date,m=l.getFullYear()+"-"+l.getMonth()+"-"+l.getDate(),n=new Date(i),o=n.getFullYear()+"-"+n.getMonth()+"-"+n.getDate();if(n.getTime()>l.getTime())return!1;if(m!==o&&"ref"!==e)return h.children().remove(),h.dialog("option","buttons",{}),h.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog"/>').append($('<div class="ui-autocomplete-loading" style="width:5em;">Loading</div>'))),void viewVisit(a,b,c,d,"ref",f,n.toDateString());$(".hhk-kdrow").hide("fade"),$(".hhk-finalPayment").show("fade");var p=parseFloat($("#kdPaid").data("amt"));isNaN(p)&&(p=0),p>0?($("#DepRefundAmount").val((0-p).toFixed(2).toString()),$(".hhk-refundDeposit").show("fade")):($("#DepRefundAmount").val(""),$(".hhk-refundDeposit").hide("fade")),j<0?($("#guestCredit").val(j.toFixed(2).toString()),$("#feesCharges").val(""),$(".hhk-RoomCharge").hide(),$(".hhk-GuestCredit").show(),$("#visitFeeCb").length>0&&Math.abs(j)>=k&&$("#visitFeeCb").prop("checked",!0).prop("disabled",!0)):($("#feesCharges").val(j.toFixed(2).toString()),$("#guestCredit").val(""),$(".hhk-GuestCredit").hide(),$(".hhk-RoomCharge").show()),$("input#cbFinalPayment").change()}else{if("ref"===e)return h.children().remove(),h.dialog("option","buttons",{}),h.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog"/>').append($('<div class="ui-autocomplete-loading" style="width:5em;">Loading</div>'))),void viewVisit(a,b,c,d,"",f);isCheckedOut=!1,$(".hhk-finalPayment").hide("fade"),$(".hhk-GuestCredit").hide(),$(".hhk-RoomCharge").hide(),$("#feesCharges").val(""),$("#guestCredit").val(""),$(".hhk-refundDeposit").hide("fade"),$("#DepRefundAmount").val(""),$("input#cbFinalPayment").prop("checked",!1),$("input#cbFinalPayment").change()}}),$("#tblStays").on("change","input.hhk-ckoutDate",function(){if(""!=$(this).val()){var a=$(this).prev();a.prop("checked",!0)}else $(this).prev().prop("checked",!1);$("input.hhk-ckoutCB").change()}),$("input.hhk-ckoutCB").change();else if($("#cbFinalPayment").length>0){isCheckedOut=!0,$(".hhk-finalPayment").show();var l=parseFloat($("#kdPaid").data("amt"));isNaN(l)?($("#DepRefundAmount").val(""),$(".hhk-refundDeposit").hide("fade")):($("#DepRefundAmount").val((0-l).toFixed(2).toString()),$(".hhk-refundDeposit").show("fade")),j<0?($("#guestCredit").val(j.toFixed(2).toString()),$("#feesCharges").val(""),$(".hhk-RoomCharge").hide(),$(".hhk-GuestCredit").show()):($("#feesCharges").val(j.toFixed(2).toString()),$("#guestCredit").val(""),$(".hhk-GuestCredit").hide(),$(".hhk-RoomCharge").show()),h.css("background-color","#F2F2F2")}setupPayments(g.resc,$("#selResource"),$("#selRateCategory"),b,$("#pmtRcpt"));var m=$("#btnFapp");m.length>0&&(m.button(),m.click(function(){getIncomeDiag(m.data("rid"))})),$("#guestAdd").click(function(){$(".hhk-addGuest").toggle()});var n;$("#txtInvSearch").keypress(function(a){var c=$(this).val();"13"==a.keyCode&&(""!=c&&isNumber(parseInt(c,10))?$.getJSON("../house/roleSearch.php",{cmd:"filter",basis:"ba",letters:c},function(a){try{a=a[0]}catch(a){return void alert("Parser error - "+a.message)}a&&a.error&&(a.gotopage&&(response(),window.open(a.gotopage)),a.value=a.error),getInvoicee(a,b)}):(alert("Don't press the return key unless you enter an Id."),a.preventDefault()))}),createAutoComplete($("#txtInvSearch"),3,{cmd:"filter",basis:"ba"},function(a){getInvoicee(a,b)},n,!1),createAutoComplete($("#txtAddGuest"),3,{cmd:"role"},function(a){getMember(a,b,f)},n),$("#selRateCategory").length>0&&($("#selRateCategory").change(function(){"x"==$(this).val()?($(".hhk-fxFixed").show("fade"),$(".hhk-fxAdj").hide("fade")):($(".hhk-fxFixed").hide("fade"),$(".hhk-fxAdj").show("fade"))}),$("#selRateCategory").change()),h.dialog("option","buttons",c),h.dialog("option","title",d),h.dialog("option","width",.8*$(window).width()),h.dialog("option","height",$(window).height()),h.dialog("open")}})}function saveFees(a,b,c,d,e){"use strict";var f=[],g=[],h="0",i=!1,j={cmd:"saveFees",idGuest:a,idVisit:b,span:c,rtntbl:d===!0?"1":"0",pbp:e};if($("input.hhk-expckout").each(function(){var a=$(this).attr("id").split("_");a.length>0&&(j[a[0]+"["+a[1]+"]"]=$(this).val())}),$("#undoCkout").length>0&&$("#undoCkout").prop("checked")&&(i=!0),(!isCheckedOut||verifyBalDisp()!==!1||i!==!1)&&verifyAmtTendrd()!==!1){if($("input.hhk-ckoutCB").each(function(){if(this.checked){var a=$(this).attr("id").split("_");if(a.length>0){j["stayActionCb["+a[1]+"]"]="on";var b=$("#stayCkOutDate_"+a[1]).datepicker("getDate");if(b){var c=new Date;b.setHours(c.getHours()),b.setMinutes(c.getMinutes())}else b=new Date;j["stayCkOutDate["+a[1]+"]"]=b.toJSON(),f.push($(this).data("nm")+", "+b.toDateString())}}}),$("input.hhk-removeCB").each(function(){if(this.checked){var a=$(this).attr("id").split("_");a.length>0&&(j[a[0]+"["+a[1]+"]"]="on",g.push($(this).data("nm")))}}),f.length>0){var k="Check Out:\n"+f.join("\n");if("1"===$("#EmptyExtend").val()&&$("#extendCb").prop("checked")&&f.length>=$("#currGuests").val()&&(k+="\nand extend the visit for "+$("#extendDays").val()+" days"),confirm(k+"?")===!1)return void $("#keysfees").dialog("close")}if(g.length>0&&confirm("Remove:\n"+g.join("\n")+"?")===!1)return void $("#keysfees").dialog("close");if($("#keyDepAmt").removeClass("ui-state-highlight"),$("#resvResource").length>0&&(h=$("#resvResource").val(),"0"!=h)){$("#resvChangeDate").removeClass("ui-state-highlight"),$("#chgmsg").text("");var l=$('<span id="chgmsg"/>');if(""==$("#resvChangeDate").val())return l.text("Enter a change room date."),l.css("color","red"),$("#moveTable").prepend($("<tr/>").append($('<td colspan="2">').append(l))),void $("#resvChangeDate").addClass("ui-state-highlight");var m=$("#resvChangeDate").datepicker("getDate");if(!m)return l.text("Something wrong with the change room date."),l.css("color","red"),$("#moveTable").prepend($("<tr/>").append($('<td colspan="2">').append(l))),void $("#resvChangeDate").addClass("ui-state-highlight");if(m>new Date)return l.text("Change room date can't be in the future."),l.css("color","red"),$("#moveTable").prepend($("<tr/>").append($('<td colspan="2">').append(l))),void $("#resvChangeDate").addClass("ui-state-highlight");if(confirm("Change Rooms?")===!1)return void $("#keysfees").dialog("close")}$(".hhk-feeskeys").each(function(){if("checkbox"===$(this).attr("type"))this.checked!==!1&&(j[$(this).attr("id")]="on");else if($(this).hasClass("ckdate")){var a=$(this).datepicker("getDate");a?j[$(this).attr("id")]=a.toJSON():j[$(this).attr("id")]=""}else"radio"===$(this).attr("type")?this.checked!==!1&&(j[$(this).attr("id")]=this.value):j[$(this).attr("id")]=this.value}),$("#keysfees").css("background-color","white"),$("#keysfees").dialog("close"),$.post("ws_ckin.php",j,function(a){try{a=$.parseJSON(a)}catch(a){return void alert("Parser error - "+a.message)}a.error&&(a.gotopage&&window.location.assign(a.gotopage),flagAlertMessage(a.error,!0)),paymentReply(a,!0),"undefined"!=typeof refreshdTables&&refreshdTables(a)})}}function paymentReply(a,b){"use strict";if(a){if(a.hostedError)flagAlertMessage(a.hostedError,!0);else if(a.xfer&&$("#xform").length>0){var c=$("#xform");if(c.children("input").remove(),c.prop("action",a.xfer),a.paymentId&&""!=a.paymentId)c.append($('<input type="hidden" name="PaymentID" value="'+a.paymentId+'"/>'));else{if(!a.cardId||""==a.cardId)return void flagAlertMessage("PaymentId and CardId are missing!",!0);c.append($('<input type="hidden" name="CardID" value="'+a.cardId+'"/>'))}c.submit()}a.success&&""!==a.success&&(flagAlertMessage(a.success,!1),$("#calendar").length>0&&b&&$("#calendar").hhkCalendar("refetchEvents")),a.receipt&&""!==a.receipt&&showReceipt("#pmtRcpt",a.receipt,"Payment Receipt"),a.invoice&&""!==a.invoice&&showReceipt("#pmtRcpt",a.invoice,"Invoice",800)}}function updateVisitMessage(a,b){$("#h3VisitMsgHdr").text(a),$("#spnVisitMsg").text(b),$("#visitMsg").effect("pulsate")}var isCheckedOut=!1;
