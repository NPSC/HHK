function resvManager(e,D){var t=this,A=e.patLabel,i=e.resvTitle,a=e.saveButtonLabel,R=e.patBD,M=e.patAddr,P=e.gstAddr,_=e.patAsGuest,N=void 0!==e.emergencyContact&&e.emergencyContact,I=void 0!==e.isCheckin&&e.isCheckin,E=e.addrPurpose,F=e.idPsg,k=e.rid,r=e.id,s=e.vid,n=e.span,c=e.arrival,o=e.insistPayFilledIn,x=[],V=new v,T=new v,d=new function(u){var v,e=this,g="divfamDetail",f=!1;function h(){var t=0;return $(".hhk-cbStay").each(function(){var e=$(this).data("prefix");$(this).prop("checked")?(V.list()[e].stay="1",t++):1===$(".hhk-cbStay").length?V.list()[e].stay="1":V.list()[e].stay="0"}),t}function p(){var e=$("input[type=radio][name=rbPriGuest]:checked").val();for(var t in V.list())V.list()[t].pri="0";void 0!==e&&(V.list()[e].pri="1")}function m(e){var t=$("#divfamDetail");!0===e?(t.show("blind"),t.prev("div").removeClass("ui-corner-all").addClass("ui-corner-top")):(t.hide("blind"),t.prev("div").addClass("ui-corner-all").removeClass("ui-corner-top"))}function C(e){"use strict";$("#ecSearch").dialog("close");var t=parseInt(e.id,10);if(!1===isNaN(t)&&0<t){var a=$("#hdnEcSchPrefix").val();if(""==a)return;$("#"+a+"txtEmrgFirst").val(e.first),$("#"+a+"txtEmrgLast").val(e.last),$("#"+a+"txtEmrgPhn").val(e.phone),$("#"+a+"txtEmrgAlt").val(""),$("#"+a+"selEmrgRel").val("")}}function y(e){var t=/^([\(]{1}[0-9]{3}[\)]{1}[\.| |\-]{0,1}|^[0-9]{3}[\.|\-| ]?)?[0-9]{3}(\.|\-| )?[0-9]{4}$/,a=!1;return 0<$("#"+e+"incomplete").length&&!1===$("#"+e+"incomplete").prop("checked")&&($("."+e+"hhk-addr-val").not(".hhk-MissingOk").each(function(){""!==$(this).val()||$(this).hasClass("bfh-states")?$(this).removeClass("ui-state-error"):($(this).addClass("ui-state-error"),a=!0)}),a)?($("#"+e+"toggleAddr").find("span").hasClass("ui-icon-circle-triangle-s")&&$("#"+e+"toggleAddr").click(),"Some or all of the indicated addresses are missing.  "):($('.hhk-phoneInput[id^="'+e+'txtPhone"]').each(function(){""!==$.trim($(this).val())&&!1===t.test($(this).val())?($(this).addClass("ui-state-error"),$("#"+e+"toggleAddr").find("span").hasClass("ui-icon-circle-triangle-s")&&$("#"+e+"toggleAddr").click(),$("#"+e+"phEmlTabs").tabs("option","active",1),a=!0):$(this).removeClass("ui-state-error")}),"")}function k(e){var t=!1,a=$("#"+e+"txtEmrgFirst"),i=$("#"+e+"txtEmrgLast"),r=$("#"+e+"txtEmrgPhn"),s=$("#"+e+"selEmrgRel");return a.removeClass("ui-state-error"),i.removeClass("ui-state-error"),r.removeClass("ui-state-error"),s.removeClass("ui-state-error"),0<$("#"+e+"cbEmrgLater").length&&!1===$("#"+e+"cbEmrgLater").prop("checked")&&(""===a.val()&&""===i.val()&&(a.addClass("ui-state-error"),i.addClass("ui-state-error"),t=!0),""===r.val()&&(r.addClass("ui-state-error"),t=!0),""===s.val()&&(s.addClass("ui-state-error"),t=!0),t)?"Some or all of the indicated Emergency Contact Information is missing.  ":""}function x(e){return void 0!==e&&e&&""!=e&&(""!==$("#"+e+"adraddress1"+E).val()&&""!==$("#"+e+"adrzip"+E).val()&&""!==$("#"+e+"adrstate"+E).val()&&""!==$("#"+e+"adrcity"+E).val())}function b(e,t){$(".hhk-addrPicker").remove();var a=$('<select id="selAddrch" multiple="multiple" />'),i=0,r=[];for(var s in T.list())if(""!=T.list()[s].Address_1||""!=T.list()[s].Postal_Code){for(var n=!0,o=T.list()[s].Address_1+", "+(""==T.list()[s].Address_2?"":T.list()[s].Address_2+", ")+T.list()[s].City+", "+T.list()[s].State_Province+"  "+T.list()[s].Postal_Code,d=0;d<=r.length;d++)r[d]!=o||(n=!1);n&&(r[i]=o,i++,$('<option class="hhk-addrPickerPanel" value="'+s+'">'+o+"</option>").appendTo(a))}0<i&&(a.prop("size",i+1).prepend($('<option value="0" >(Cancel)</option>')),a.change(function(){!function(e,t){if(0==t)return $("#divSelAddr").remove();$("#"+e+"adraddress1"+E).val(T.list()[t].Address_1),$("#"+e+"adraddress2"+E).val(T.list()[t].Address_2),$("#"+e+"adrcity"+E).val(T.list()[t].City),$("#"+e+"adrcounty"+E).val(T.list()[t].County),$("#"+e+"adrzip"+E).val(T.list()[t].Postal_Code),$("#"+e+"adrcountry"+E).val()!=T.list()[t].Country_Code&&$("#"+e+"adrcountry"+E).val(T.list()[t].Country_Code).change();$("#"+e+"adrstate"+E).val(T.list()[t].State_Province),x(e)&&!0===$("#"+e+"incomplete").prop("checked")&&$("#"+e+"incomplete").prop("checked",!1);S($("#"+e+"liaddrflag")),$("#divSelAddr").remove()}(t,$(this).val())}),$('<div id="divSelAddr" style="position:absolute; vertical-align:top;" class="hhk-addrPicker hhk-addrPickerPanel"/>').append($('<p class="hhk-addrPickerPanel">Choose an Address: </p>')).append(a).appendTo($("body")).position({my:"left top",at:"right center",of:e}))}function w(e){void 0!==e&&(T.list()[e].Address_1=$("#"+e+"adraddress1"+E).val(),T.list()[e].Address_2=$("#"+e+"adraddress2"+E).val(),T.list()[e].City=$("#"+e+"adrcity"+E).val(),T.list()[e].County=$("#"+e+"adrcounty"+E).val(),T.list()[e].State_Province=$("#"+e+"adrstate"+E).val(),T.list()[e].Country_Code=$("#"+e+"adrcountry"+E).val(),T.list()[e].Postal_Code=$("#"+e+"adrzip"+E).val(),S($("#"+e+"liaddrflag")))}function S(e){var t=e.data("pref");!0===$("#"+t+"incomplete").prop("checked")?(e.show().find("span").removeClass("ui-icon-alert").addClass("ui-icon-check").attr("title","Incomplete Address is checked"),e.removeClass("ui-state-error").addClass("ui-state-highlight")):x(t)?e.hide():(e.show().find("span").removeClass("ui-icon-check").addClass("ui-icon-alert").attr("title","Address is Incomplete"),e.removeClass("ui-state-highlight").addClass("ui-state-error"))}e.findStaysChecked=h,e.findStays=function(e){var t=0;for(var a in V.list())V.list()[a].stay===e&&t++;return t},e.findPrimaryGuest=p,e.setUp=function(i){var e;if(void 0!==i.famSection&&void 0!==i.famSection.tblId&&""!==i.famSection.tblId){var t,a,r,s,n,o,d;for(var l in!1===f&&(s=i,n=$("<div/>").addClass("ui-widget-content ui-corner-bottom hhk-tdbox").prop("id",g).css("padding","5px"),v=$("<table/>").prop("id",s.famSection.tblId).addClass("hhk-table").append($("<thead/>").append($(s.famSection.tblHead))).append($("<tbody/>")),n.append(v).append($(s.famSection.adtnl)),d=$("<ul style='list-style-type:none; float:right;margin-left:5px;padding-top:2px;' class='ui-widget'/>").append($("<li class='ui-widget-header ui-corner-all' title='Open - Close'>").append($("<span id='f_drpDown' class='ui-icon ui-icon-circle-triangle-n'></span>"))),(o=$('<div id="divfamHdr" style="padding:2px; cursor:pointer;"/>').append($(s.famSection.hdr)).append(d).append('<div style="clear:both;"/>')).addClass("ui-widget-header ui-state-default ui-corner-top"),o.click(function(){"none"===n.css("display")?(n.show("blind"),o.removeClass("ui-corner-all").addClass("ui-corner-top")):(n.hide("blind"),o.removeClass("ui-corner-top").addClass("ui-corner-all"))}),u.empty().append(o).append(n).show(),D.UseIncidentReports&&F&&(t=$('<div style="font-size: 0.9em; min-width: 810px; margin-bottom: 0.5em; margin-top: 0.5em; display: none;"/>').addClass("ui-widget hhk-visitdialog hhk-row").prop("id","incidentsSection"),a=$('<div style="padding:2px; cursor:pointer;"/>').addClass("ui-widget-header ui-state-default ui-corner-all hhk-incidentHdr"),r=$('<div style="padding: 5px;"/>').addClass("ui-corner-bottom hhk-tdbox ui-widget-content").prop("id","incidentContent").hide(),a.append('<div class="hhk-checkinHdr" style="display: inline-block">Incidents<span id="incidentCounts"/></div>').append('<ul style="list-style-type:none; float:right;margin-left:5px;padding-top:2px;" class="ui-widget"><li class="ui-widget-header ui-corner-all" title="Open - Close"><span id="f_drpDown" class="ui-icon ui-icon-circle-triangle-n"></span></li></ul>'),r.incidentViewer({psgId:F}),a.click(function(){"none"===r.css("display")?(r.show("blind"),a.removeClass("ui-corner-all").addClass("ui-corner-top")):(r.hide("blind"),a.removeClass("ui-corner-top").addClass("ui-corner-all"))}),t.append(a).append(r).show(),u.find("#divfamDetail").append(t))),i.famSection.mem){var c=V.findItem("pref",i.famSection.mem[l].pref);c&&(v.find("tr#"+c.id+"n").remove(),v.find("tr#"+c.id+"a").remove(),v.find("input#"+c.pref+"idName").parents("tr").next("tr").remove(),v.find("input#"+c.pref+"idName").parents("tr").remove(),V.removeIndex(c.pref),T.removeIndex(c.pref))}for(var h in V.makeList(i.famSection.mem,"pref"),T.makeList(i.famSection.addrs,"pref"),void 0!==i.famSection.tblBody[1]&&v.find("tbody:first").prepend($(i.famSection.tblBody[1])),void 0!==i.famSection.tblBody[0]&&v.find("tbody:first").prepend($(i.famSection.tblBody[0])),i.famSection.tblBody)"0"!==h&&"1"!==h&&v.find("tbody:first").append($(i.famSection.tblBody[h]));for(var p in $(".hhk-cbStay").checkboxradio({classes:{"ui-checkboxradio-label":"hhk-unselected-text"}}),$(".hhk-lblStay").each(function(){"1"==$(this).data("stay")&&$(this).click()}),$(".ckbdate").datepicker({yearRange:"-99:+00",changeMonth:!0,changeYear:!0,autoSize:!0,maxDate:0,dateFormat:"M d, yy"}),$(".hhk-addrPanel").find("select.bfh-countries").each(function(){var e=$(this);e.bfhcountries(e.data()),$(this).data("dirrty-initial-value",$(this).data("country"))}),$(".hhk-addrPanel").find("select.bfh-states").each(function(){var e=$(this);e.bfhstates(e.data()),$(this).data("dirrty-initial-value",$(this).data("state"))}),$(".hhk-phemtabs").tabs(),verifyAddrs("#divfamDetail"),$("input.hhk-zipsearch").each(function(){createZipAutoComplete($(this),"ws_admin.php",void 0,w)}),!1===f&&($("#lnCopy").click(function(){var e=$("input.hhk-lastname").first().val();$("input.hhk-lastname").each(function(){""===$(this).val()&&$(this).val(e)})}),$("#adrCopy").click(function(){!function(e){for(var t in T.list())e!=t&&(""!==$("#"+t+"adraddress1"+E).val()&&""!==$("#"+t+"adrzip"+E).val()||($("#"+t+"adraddress1"+E).val(T.list()[e].Address_1),$("#"+t+"adraddress2"+E).val(T.list()[e].Address_2),$("#"+t+"adrcity"+E).val(T.list()[e].City),$("#"+t+"adrcounty"+E).val(T.list()[e].County),$("#"+t+"adrzip"+E).val(T.list()[e].Postal_Code),$("#"+t+"adrcountry"+E).val()!=T.list()[e].Country_Code&&$("#"+t+"adrcountry"+E).val(T.list()[e].Country_Code).change(),$("#"+t+"adrstate"+E).val(T.list()[e].State_Province),!0===$("#"+e+"incomplete").prop("checked")?$("#"+t+"incomplete").prop("checked",!0):x(t)&&!0===$("#"+t+"incomplete").prop("checked")&&$("#"+t+"incomplete").prop("checked",!1),S($("#"+t+"liaddrflag"))))}($("li.hhk-AddrFlag").first().data("pref"))}),$("#"+g).on("click",".hhk-togAddr",function(){e=$(this),$(this).siblings(),"none"===$(this).parents("tr").next("tr").css("display")?($(this).parents("tr").next("tr").show(),e.find("span").removeClass("ui-icon-circle-triangle-s").addClass("ui-icon-circle-triangle-n"),e.attr("title","Hide Address Section")):($(this).parents("tr").next("tr").hide(),e.find("span").removeClass("ui-icon-circle-triangle-n").addClass("ui-icon-circle-triangle-s"),e.attr("title","Show Address Section"),isIE()&&$("#divSelAddr").remove())}),$("#"+g).on("click",".hhk-AddrFlag",function(){$("#"+$(this).data("pref")+"incomplete").click()}),$("#"+g).on("change",".hhk-copy-target",function(){w($(this).data("pref"))}),$("#"+g).on("click",".hhk-addrCopy",function(){b($(this),$(this).data("prefix"))}),$("#"+g).on("click",".hhk-addrErase",function(){var e;e=$(this).data("prefix"),$("#"+e+"adraddress1"+E).val(""),$("#"+e+"adraddress2"+E).val(""),$("#"+e+"adrcity"+E).val(""),$("#"+e+"adrcounty"+E).val(""),$("#"+e+"adrstate"+E).val(""),$("#"+e+"adrcountry"+E).val(""),$("#"+e+"adrzip"+E).val(""),S($("#"+e+"liaddrflag"))}),$("#"+g).on("click",".hhk-incompleteAddr",function(){S($("#"+$(this).data("prefix")+"liaddrflag"))}),$("#"+g).on("click",".hhk-removeBtn",function(){(""===$("#"+$(this).data("prefix")+"txtFirstName").val()&&""===$("#"+$(this).data("prefix")+"txtLastName").val()||!1!==confirm("Remove this person: "+$("#"+$(this).data("prefix")+"txtFirstName").val()+" "+$("#"+$(this).data("prefix")+"txtLastName").val()+"?"))&&(V.removeIndex($(this).data("prefix")),T.removeIndex($(this).data("prefix")),$(this).parentsUntil("tbody","tr").next().remove(),$(this).parentsUntil("tbody","tr").remove())}),$("#"+g).on("change",".patientRelch",function(){"slf"===$(this).val()?V.list()[$(this).data("prefix")].role="p":V.list()[$(this).data("prefix")].role="g"}),createAutoComplete($("#txtPersonSearch"),3,{cmd:"role",gp:"1"},function(e){var t,a;a=i,void 0===(t=e).No_Return||""===t.No_Return?void 0!==t.id&&(0<t.id&&null!==V.findItem("id",t.id)?flagAlertMessage("This person is already listed here. ","alert"):O({id:t.id,rid:a.rid,idPsg:a.idPsg,isCheckin:I,gstDate:$("#gstDate").val(),gstCoDate:$("#gstCoDate").val(),cmd:"addResvGuest"})):flagAlertMessage("This person is set for No Return: "+t.No_Return+".","alert")}),$("#"+g).on("click",".hhk-emSearch",function(){$("#hdnEcSchPrefix").val($(this).data("prefix")),$("#ecSearch").dialog("open")}),createAutoComplete($("#txtemSch"),3,{cmd:"filter",add:"phone",basis:"g"},C),$("ul.hhk-ui-icons li").hover(function(){$(this).addClass("ui-state-hover")},function(){$(this).removeClass("ui-state-hover")})),V.list())S($("#"+p+"liaddrflag"));$(".hhk-togAddr").each(function(){$(this).parents("tr").next("tr").hide(),$(this).find("span").removeClass("ui-icon-circle-triangle-n").addClass("ui-icon-circle-triangle-s"),$(this).attr("title","Show Address Section")}),f=!0}},e.newGuestMarkup=function(e,t){var a,i,r,s,n;void 0!==e.tblId&&""!=e.tblId&&0!==v.length&&(r=v.children("tbody").children("tr").last().hasClass("odd")?"even":"odd",v.find("tbody:first").append($(e.ntr).addClass(r)).append($(e.atr).addClass(r)),$("#"+t+"cbStay").checkboxradio({classes:{"ui-checkboxradio-label":"hhk-unselected-text"}}),"1"==$("#"+t+"lblStay").data("stay")&&$("#"+t+"lblStay").click(),$(".ckbdate").datepicker({yearRange:"-99:+00",changeMonth:!0,changeYear:!0,autoSize:!0,maxDate:0,dateFormat:"M d, yy"}),n=(s=$("#"+t+"liaddrflag")).siblings(),S(s),n.parents("tr").next("tr").hide(),n.find("span").removeClass("ui-icon-circle-triangle-n").addClass("ui-icon-circle-triangle-s"),n.attr("title","Show Address Section"),(a=$("#"+t+"adrcountry"+E)).bfhcountries(a.data()),$(this).data("dirrty-initial-value",$(this).data("country")),(i=$("#"+t+"adrstate"+E)).bfhstates(i.data()),$(this).data("dirrty-initial-value",$(this).data("state")),$("#"+t+"phEmlTabs").tabs(),$("input#"+t+"adrzip1").each(function(){createZipAutoComplete($(this),"ws_admin.php",void 0,w)}))},e.verify=function(){var e=0,t=0,a=0,i=0,r=!1,s=0,n=!1;if($(".patientRelch").removeClass("ui-state-error"),$(".patientRelch").each(function(){""===$(this).val()?($(this).addClass("ui-state-error"),n=!0):$(this).removeClass("ui-state-error")}),n)return flagAlertMessage("Set the highlighted Relationship(s).","alert",B),!1;for(var o in p(),h(),V.list())e++,"p"===V.list()[o].role&&t++,"1"===V.list()[o].stay&&a++,"1"===V.list()[o].pri&&i++,$("#"+o+"toggleAddr").find("span").hasClass("ui-icon-circle-triangle-n")&&$("#"+o+"toggleAddr").click();if(t<1)return flagAlertMessage("Choose a "+A+".","alert",B),$(".patientRelch").addClass("ui-state-error"),!1;if(1<t){for(var o in flagAlertMessage("Only 1 "+A+" is allowed.","alert",B),V.list())"p"===V.list()[o].role&&$("#"+o+"selPatRel").addClass("ui-state-error");return!1}if(a<1)return flagAlertMessage("There is no one actually staying.  Pick someone to stay.","alert",B),!1;if($("input.hhk-rbPri").parent().removeClass("ui-state-error"),0===i&&1===e)for(var o in V.list())V.list()[o].pri="1";else if(0===i)return B.text("Set one guest as primary guest.").show(),flagAlertMessage("Set one guest as primary guest.","alert",B),$("input.hhk-rbPri").parent().addClass("ui-state-error"),!1;if(u.find(".hhk-lastname").each(function(){""==$(this).val()?($(this).addClass("ui-state-error"),r=!0):$(this).removeClass("ui-state-error")}),u.find(".hhk-firstname").each(function(){""==$(this).val()?($(this).addClass("ui-state-error"),r=!0):$(this).removeClass("ui-state-error")}),!0===r)return m(!0),flagAlertMessage("Enter a first and last name for the people highlighted.","alert",B),!1;for(var d in N&&u.find(".hhk-EmergCb").each(function(){var e=k($(this).data("prefix"));!0!==$(this).prop("checked")&&""!==e||s++}),V.list()){if("p"===V.list()[d].role){if(R&""===$("#"+d+"txtBirthDate").val())return $("#"+d+"txtBirthDate").addClass("ui-state-error"),flagAlertMessage(A+" is missing the Birth Date.","alert",B),m(!0),!1;if($("#"+d+"txtBirthDate").removeClass("ui-state-error"),M||_)if(""!==(c=y(d)))return flagAlertMessage(c,"alert",B),m(!0),$("#"+d+"toggleAddr").find("span").hasClass("ui-icon-circle-triangle-s")&&$("#"+d+"toggleAddr").click(),!1}else{if(P)if(""!==(c=y(d)))return flagAlertMessage(c,"alert",B),m(!0),$("#"+d+"toggleAddr").find("span").hasClass("ui-icon-circle-triangle-s")&&$("#"+d+"toggleAddr").click(),!1}if(0<$("#"+d+"txtBirthDate").length&&""!==$("#"+d+"txtBirthDate").val()){var l=new Date($("#"+d+"txtBirthDate").val());if(new Date<l)return $("#"+d+"txtBirthDate").addClass("ui-state-error"),flagAlertMessage("This birth date cannot be in the future.","alert",B),m(!0),!1;$("#"+d+"txtBirthDate").removeClass("ui-state-error")}var c;if(N&&s<1)if(""!==(c=k(d)))return flagAlertMessage(c,"alert",B),m(!0),$("#"+d+"toggleAddr").find("span").hasClass("ui-icon-circle-triangle-s")&&$("#"+d+"toggleAddr").click(),!1}return!(f=!1)},e.divFamDetailId=g,e.$famTbl=v}($("#famSection")),l=new function(u){var v,g,f,m,C=this;function y(e){var t=x[$("#selResource").val()],a=!0;$("#selccgw"+e+" option").each(function(){this.value===t.merchant&&(a=!1)}),a&&$("#selccgw"+e).append('<option value="'+t.merchant+'">'+t.merchant+"</option>"),$("#selccgw"+e).val(t.merchant)}function a(e){"use strict";var t="";return""===$("#car"+e+"txtVehLic").val()&&""===$("#car"+e+"txtVehMake").val()?"Enter vehicle info or check the 'No Vehicle' checkbox. ":(""===$("#car"+e+"txtVehLic").val()?(""===$("#car"+e+"txtVehModel").val()&&($("#car"+e+"txtVehModel").addClass("ui-state-highlight"),t="Enter Model"),""===$("#car"+e+"txtVehColor").val()&&($("#car"+e+"txtVehColor").addClass("ui-state-highlight"),t="Enter Color"),""===$("#car"+e+"selVehLicense").val()&&($("#car"+e+"selVehLicense").addClass("ui-state-highlight"),t="Enter state license plate registration")):""===$("#car"+e+"txtVehMake").val()&&""===$("#car"+e+"txtVehLic").val()&&($("#car"+e+"txtVehLic").addClass("ui-state-highlight"),t="Enter a license plate number."),t)}C.setupComplete=!1,C.checkPayments=!0,C.setUp=function(t){var e,a,i,r,s,n,o,d,l,c,h;if(v=$("<div/>").addClass(" hhk-tdbox ui-corner-bottom hhk-tdbox ui-widget-content").prop("id","divResvDetail").css({padding:"5px",display:"flex","flex-wrap":"wrap"}),void 0!==t.resv.rdiv.rChooser&&v.append($(t.resv.rdiv.rChooser)),void 0!==t.resv.rdiv.rate&&v.append($(t.resv.rdiv.rate)),void 0!==t.resv.rdiv.cof&&v.append(t.resv.rdiv.cof),void 0!==t.resv.rdiv.rstat&&v.append($(t.resv.rdiv.rstat)),void 0!==t.resv.rdiv.vehicle&&(g=$(t.resv.rdiv.vehicle),v.append(g),a=1,i=(e=g).find("#cbNoVehicle"),r=e.find("#btnNextVeh"),s=e.find("#tblVehicle"),i.change(function(){this.checked?s.hide("scale, horizontal"):s.show("scale, horizontal")}),i.change(),r.button(),r.click(function(){e.find("#trVeh"+a).show("fade"),4<++a&&r.hide("fade")})),void 0!==t.resv.rdiv.pay&&v.append($(t.resv.rdiv.pay)),void 0!==t.resv.rdiv.notes&&v.append((n=t.rid,(o=$(t.resv.rdiv.notes)).notesViewer({linkId:n,linkType:"reservation",newNoteAttrs:{id:"taNewNote",name:"taNewNote"},alertMessage:function(e,t){flagAlertMessage(e,t)}}),o)),void 0!==t.resv.rdiv.wlnotes&&v.append($(t.resv.rdiv.wlnotes)),m=$("<ul style='list-style-type:none; float:right; margin-left:5px; padding-top:2px;' class='ui-widget'/>").append($("<li class='ui-widget-header ui-corner-all' title='Open - Close'>").append($("<span id='r_drpDown' class='ui-icon ui-icon-circle-triangle-n'></span>"))),(f=$('<div id="divResvHdr" style="padding:2px; cursor:pointer;"/>').append($(t.resv.hdr)).append(m).append('<div style="clear:both;"/>')).addClass("ui-widget-header ui-state-default ui-corner-top"),f.click(function(e){var t=$(e.target);"divResvHdr"!==t[0].id&&"r_drpDown"!==t[0].id||("none"===v.css("display")?(v.show("blind"),f.removeClass("ui-corner-all").addClass("ui-corner-top")):(v.hide("blind"),f.removeClass("ui-corner-top").addClass("ui-corner-all")))}),u.empty().append(f).append(v).show(),C.$totalGuests=$("#spnNumGuests"),C.origRoomId=$("#selResource").val(),C.checkPayments=!0,0<$(".hhk-viewResvActivity").length&&$(".hhk-viewResvActivity").click(function(){$.post("ws_ckin.php",{cmd:"viewActivity",rid:$(this).data("rid")},function(e){if((e=$.parseJSON(e)).error)return e.gotopage&&window.open(e.gotopage,"_self"),void flagAlertMessage(e.error,"error");e.activity&&($("div#submitButtons").hide(),$("#activityDialog").children().remove(),$("#activityDialog").append($(e.activity)),$("#activityDialog").dialog("open"))})}),$("#btnShowCnfrm").button().click(function(){var e=$("#spnAmount").text();""===e&&(e=0),$.post("ws_ckin.php",{cmd:"confrv",rid:$(this).data("rid"),amt:e,eml:"0"},function(e){if((t=$.parseJSON(e)).error)return t.gotopage&&window.open(t.gotopage,"_self"),void flagAlertMessage(t.error,"error");t.confrv&&($("div#submitButtons").hide(),$("#frmConfirm").children().remove(),$("#frmConfirm").html(t.confrv).append($('<div style="padding-top:10px;" class="ui-dialog-buttonpane ui-widget-content ui-helper-clearfix"><span>Email Address </span><input type="text" id="confEmail" value="'+t.email+'"/></div>')),$("#frmConfirm").find("#confirmTabDiv").tabs(),$("#confirmDialog").dialog("open"))})}),d=t.rid,w.idReservation=d,$("input.hhk-constraintsCB").change(function(){w.go($("#gstDate").val(),$("#gstCoDate").val())}),void 0!==t.resv.rdiv.rate&&(l=t,c={},0<(h=u.find("#btnFapp")).length&&($("#faDialog").dialog({autoOpen:!1,resizable:!0,width:680,modal:!0,title:"Income Chooser",close:function(){$("div#submitButtons").show()},open:function(){$("div#submitButtons").hide()},buttons:{Save:function(){$.post("ws_ckin.php",$("#formf").serialize()+"&cmd=savefap&rid="+l.rid,function(e){try{e=$.parseJSON(e)}catch(e){return void alert("Bad JSON Encoding")}if(e.gotopage&&window.open(e.gotopage,"_self"),e.rstat&&1==e.rstat){var t=$("#selRateCategory");e.rcat&&""!=e.rcat&&0<t.length&&(t.val(e.rcat),t.change())}}),$(this).dialog("close")},Exit:function(){$(this).dialog("close")}}}),h.button().click(function(){getIncomeDiag(l.rid)})),l.resv.rdiv.ratelist&&(c.rateList=l.resv.rdiv.ratelist,c.resources=l.resv.rdiv.rooms,c.visitFees=l.resv.rdiv.vfee,c.idResv=k,setupRates(c)),0<$("#selResource").length&&$("#selResource").change(function(){$("#selRateCategory").change();var e=$("option:selected",this).parent()[0].label;null==e?$("#hhkroomMsg").hide():$("#hhkroomMsg").text(e).show(),0<$("#selccgw").length?y(""):0<$("#selccgwg").length&&y("g")})),void 0!==t.resv.rdiv.pay&&0<$("#selResource").length&&0<$("#selRateCategory").length&&(setupPayments($("#selRateCategory")),$("#paymentDate").datepicker({yearRange:"-1:+00",numberOfMonths:1,autoSize:!0,dateFormat:"M d, yy"})),void 0!==t.resv.rdiv.cof){var p=x[$("#selResource").val()];$("#btnUpdtCred").button().click(function(){cardOnFile($(this).data("id"),$(this).data("idreg"),"Reserve.php?rid="+k,$(this).data("indx"))}),setupCOF($("#trvdCHNameg"),$("#btnUpdtCred").data("indx")),$("#selccgwg").val(p.merchant)}0<$("#addGuestHeader").length&&(b.openControl=!0,b.setUp(t.resv.rdiv,S,$("#addGuestHeader"))),C.setupComplete=!0},C.verify=function(){if(0<$("#cbNoVehicle").length){if(!1===$("#cbNoVehicle").prop("checked")){var e=a(1);if(""!=e){var t=a(2);if(""!=t)return $("#vehValidate").text(t),flagAlertMessage(e,"alert",B),!1}}$("#vehValidate").text("")}if(I&&!0===C.checkPayments){if($("#selCategory").val()==fixedRate&&0<$("#txtFixedRate").length&&""==$("#txtFixedRate").val())return flagAlertMessage("Set the Room Rate to an amount, or to 0.","alert",B),$("#txtFixedRate").addClass("ui-state-error"),!1;if($("#txtFixedRate").removeClass("ui-state-error"),0<$("input#feesPayment").length&&""==$("input#feesPayment").val()&&o)return flagAlertMessage("Set the Room Fees to an amount, or 0.","alert",B),$("#payChooserMsg").text("Set the Room Fees to an amount, or 0.").show(),$("input#feesPayment").addClass("ui-state-error"),!1;if($("input#feesPayment").removeClass("ui-state-error"),void 0!==verifyAmtTendrd&&!1===verifyAmtTendrd())return!1}return!0}}($("#resvSection")),h=new function(r){var s=this;s.setupComplete=!1,s.setUp=function(e){var t=$(e.div).addClass("ui-widget-content").prop("id","divhospDetail").hide(),a=$("<ul style='list-style-type:none; float:right;margin-left:5px;padding-top:2px;' class='ui-widget'/>").append($("<li class='ui-widget-header ui-corner-all' title='Open - Close'>").append($("<span id='h_drpDown' class='ui-icon ui-icon-circle-triangle-n'></span>"))),i=$('<div id="divhospHdr" style="padding:2px; cursor:pointer;"/>').append($(e.hdr)).append(a).append('<div style="clear:both;"/>');i.addClass("ui-widget-header ui-state-default ui-corner-all"),i.click(function(){"none"===t.css("display")?(t.show("blind"),i.removeClass("ui-corner-all").addClass("ui-corner-top")):(t.hide("blind"),i.removeClass("ui-corner-top").addClass("ui-corner-all"))}),r.empty().append(i).append(t),$("#txtEntryDate, #txtExitDate").datepicker({yearRange:"-01:+01",changeMonth:!0,changeYear:!0,autoSize:!0,dateFormat:"M d, yy"}),0<$("#txtAgentSch").length&&(createAutoComplete($("#txtAgentSch"),3,{cmd:"filter",basis:"ra"},getAgent),""===$("#a_txtLastName").val()&&$(".hhk-agentInfo").hide()),0<$("#txtDocSch").length&&(createAutoComplete($("#txtDocSch"),3,{cmd:"filter",basis:"doc"},getDoc),""===$("#d_txtLastName").val()&&$(".hhk-docInfo").hide()),verifyAddrs("#divhospDetail"),r.on("change","#selHospital, #selAssoc",function(){var e=$("#selAssoc").find("option:selected").text();""!=e&&(e+="/ "),$("span#spnHospName").text(e+$("#selHospital").find("option:selected").text())}),r.show(),""===$("#selHospital").val()&&i.click(),s.setupComplete=!0},s.verify=function(){return r.find(".ui-state-error").each(function(){$(this).removeClass("ui-state-error")}),0<$("#selHospital").length&&!0===s.setupComplete&&""==$("#selHospital").val()?($("#selHospital").addClass("ui-state-error"),flagAlertMessage("Select a hospital.","alert",B),$("#divhospDetail").show("blind"),$("#divhospHdr").removeClass("ui-corner-all").addClass("ui-corner-top"),!1):($("#divhospDetail").hide("blind"),$("#divhospHdr").removeClass("ui-corner-top").addClass("ui-corner-all"),!0)}}($("#hospitalSection")),b=new function(){var l=this;l.setupComplete=!1,l.ciDate=new Date,l.coDate=new Date,l.openControl=!1,l.setUp=function(i,r,e){if(e.empty(),i.mu&&""!==i.mu){e.append($(i.mu));var t,s=$("#gstDate"),n=$("#gstCoDate"),a=parseInt(i.defdays,10),o=!1,d=!1;""===s.val()&&c&&s.val(c),i.startDate&&(o=i.startDate),i.endDate&&(d=i.endDate),t=$("#spnRangePicker").dateRangePicker({format:"MMM D, YYYY",separator:" to ",minDays:1,autoClose:!0,showShortcuts:!0,shortcuts:{"next-days":[a]},getValue:function(){return s.val()&&n.val()?s.val()+" to "+n.val():""},setValue:function(e,t,a){s.val(t),n.val(a)},startDate:o,endDate:d}),i.updateOnChange&&t.bind("datepicker-change",function(e,t){var a=Math.ceil((t.date2.getTime()-t.date1.getTime())/864e5);$("#"+i.daysEle).val(a),0<$("#spnNites").length&&$("#spnNites").text(a),$("#gstDate").removeClass("ui-state-error"),$("#gstCoDate").removeClass("ui-state-error"),$.isFunction(r)&&r(t)}),e.show(),l.openControl&&$("#spnRangePicker").data("dateRangePicker").open()}setupComplete=!0},l.verify=function(){var e=$("#gstDate"),t=$("#gstCoDate");if(e.removeClass("ui-state-error"),t.removeClass("ui-state-error"),""===e.val())return e.addClass("ui-state-error"),flagAlertMessage("This "+i+" is missing the check-in date.","alert",B),!1;if(l.ciDate=new Date(e.val()),isNaN(l.ciDate.getTime()))return e.addClass("ui-state-error"),flagAlertMessage("This "+i+" is missing the check-in date.","alert",B),!1;if(void 0!==I&&!0===I){var a=moment($("#gstDate").val(),"MMM D, YYYY");if(moment().endOf("date")<a)return e.addClass("ui-state-error"),flagAlertMessage("Set the Check in date to today or earlier.","alert",B),!1}return""===t.val()?(t.addClass("ui-state-error"),flagAlertMessage("This "+i+" is missing the expected departure date.","alert",B),!1):(l.coDate=new Date(t.val()),isNaN(l.coDate.getTime())?(t.addClass("ui-state-error"),flagAlertMessage("This "+i+" is missing the expected departure date","alert",B),!1):!(l.ciDate>l.coDate)||(e.addClass("ui-state-error"),flagAlertMessage("This "+i+"'s check-in date is after the expected departure date.","alert",B),!1))}},w=new w,B=$("#pWarnings");D=D;function p(e){x=e}function S(e){B.text("").hide();var t=!1;for(var a in V.list())if(0<V.list()[a].id){t=!0;break}if(t){$(".hhk-stayIndicate").hide().parent("td").addClass("hhk-loading");var i={cmd:"updateAgenda",idPsg:F,idResv:k,idVisit:s,span:n,dt1:e.date1.getFullYear()+"-"+(e.date1.getMonth()+1)+"-"+e.date1.getDate(),dt2:e.date2.getFullYear()+"-"+(e.date2.getMonth()+1)+"-"+e.date2.getDate(),mems:V.list()};$.post("ws_resv.php",i,function(e){$(".hhk-stayIndicate").show().parent("td").removeClass("hhk-loading");try{e=$.parseJSON(e)}catch(e){return void flagAlertMessage(e.message,"error")}if(e.gotopage&&window.open(e.gotopage,"_self"),e.error&&flagAlertMessage(e.error,"error"),e.stayCtrl){for(var t in e.stayCtrl){var a;$("#sb"+t).empty().html(e.stayCtrl[t].ctrl),$("#"+t+"cbStay").checkboxradio({classes:{"ui-checkboxradio-label":"hhk-unselected-text"}}),V.list()[t].stay="0",0<(a=$("#"+t+"lblStay")).length&&"1"==a.data("stay")&&a.click()}$(".hhk-getVDialog").button(),""!=$("#gstDate").val()&&""!=$("#gstCoDate").val()&&w.go($("#gstDate").val(),$("#gstCoDate").val()),$(".hhk-cbStay").change()}})}u(e.date1.t,k)}function u(e,t,a){var i=moment(e,"MMM D, YYYY"),r=moment().endOf("date");0<t&&i<=r&&!a?$("#btnCheckinNow").show():$("#btnCheckinNow").hide()}function w(){var r=this,s={};r.omitSelf=!0,r.numberGuests=0,r.idReservation=0,r.go=function(e,t){var a,i=$("#selResource");if(0===i.length)return;a=i.find("option:selected").val(),i.prop("disabled",!0),$("#hhk-roomChsrtitle").addClass("hhk-loading"),$("#hhkroomMsg").text("").hide(),s={},$("input.hhk-constraintsCB:checked").each(function(){s[$(this).data("cnid")]="ON"}),$.post("ws_ckin.php",{cmd:"newConstraint",rid:r.idReservation,numguests:r.numberGuests,expArr:e,expDep:t,idr:a,cbRS:s,omsf:r.omitSelf},function(e){var t;i.prop("disabled",!1),$("#hhk-roomChsrtitle").removeClass("hhk-loading");try{e=$.parseJSON(e)}catch(e){return void alert("Parser error - "+e.message)}if(e.error)return e.gotopage&&window.location.assign(e.gotopage),void flagAlertMessage(e.error,"error");e.rooms&&p(e.rooms),e.selectr&&(t=$(e.selectr),i.children().remove(),t.children().appendTo(i),i.val(e.idResource).change(),e.msg&&""!==e.msg&&$("#hhkroomMsg").text(e.msg).show())})}}function v(){var i,r={},e=this;function s(e){return!1===t(e)&&(r[e[i]]=e,!0)}function t(e){return void 0!==r[e[i]]}e.hasItem=t,e.findItem=function(e,t){for(var a in r)if(r[a][e]==t)return r[a];return null},e.addItem=s,e.removeIndex=function(e){delete r[e]},e.list=function(){return r},e.makeList=function(e,t){for(var a in i=t,e)s(e[a])},e._list=r}function g(e,t){"use strict";$("input#txtPersonSearch").val(""),t.empty().append($(e.psgChooser)).dialog("option","buttons",{Open:function(){$(this).dialog("close"),O({idPsg:t.find("input[name=cbselpsg]:checked").val(),id:e.id,cmd:"getResv"})},Cancel:function(){$(this).dialog("close"),$("input#gstSearch").val("").focus()}}).dialog("option","title",e.patLabel+" Chooser"+(void 0===e.fullName?"":" For: "+e.fullName)).dialog("open")}function O(e){var t={id:e.id,rid:e.rid,idPsg:e.idPsg,vid:e.vid,span:e.span,isCheckin:I,gstDate:e.gstDate,gstCoDate:e.gstCoDate,cmd:e.cmd};$.post("ws_resv.php",t,function(e){try{e=$.parseJSON(e)}catch(e){return void flagAlertMessage(e.message,"error")}e.gotopage&&window.open(e.gotopage,"_self"),e.error&&(flagAlertMessage(e.error,"error",B),$("#btnDone").val("Save "+i).show()),f(e)})}function f(e){e.xfer||e.inctx?paymentRedirect(e,$("#xform")):e.resvChooser&&""!==e.resvChooser?function(e,t,a){"use strict";var i={};$("input#txtPersonSearch").val(""),t.empty().append($(e.resvChooser)).children().find("input:button").button(),t.children().find(".hhk-checkinNow").click(function(){window.open("CheckingIn.php?rid="+$(this).data("rid")+"&gid="+e.id,"_self")}),e.psgChooser&&""!==e.psgChooser&&(i[e.patLabel+" Chooser"]=function(){$(this).dialog("close"),g(e,a)}),e.resvTitle&&(i["New "+e.resvTitle]=function(){e.rid=-1,e.cmd="getResv",$(this).dialog("close"),O(e)}),i.Exit=function(){$(this).dialog("close"),$("input#gstSearch").val("").focus()},t.dialog("option","width","95%"),t.dialog("option","buttons",i),t.dialog("option","title",e.resvTitle+" Chooser"),t.dialog("open");var r=t.find("table").width();t.dialog("option","width",r+80)}(e,$("#resDialog"),$("#psgDialog")):e.psgChooser&&""!==e.psgChooser?g(e,$("#psgDialog")):(e.idPsg&&(F=e.idPsg),e.id&&(r=e.id),e.rid&&(k=e.rid),e.vid&&(s=e.vid),e.span&&(n=e.span),void 0!==e.hosp&&h.setUp(e.hosp),e.famSection&&(d.setUp(e),$("div#guestSearch").hide(),$("#btnDone").val("Save Family").show(),$("select.hhk-multisel").each(function(){$(this).multiselect({selectedList:3})})),void 0!==e.expDates&&""!==e.expDates&&(b.openControl=!1,b.setUp(e.expDates,S,$("#datesSection"))),void 0!==e.warning&&""!==e.warning&&flagAlertMessage(e.warning,"warning",B),void 0!==e.resv&&(e.resv.rdiv.rooms&&(x=e.resv.rdiv.rooms),l.setUp(e),$("#"+d.divFamDetailId).on("change",".hhk-cbStay",function(){var e=d.findStaysChecked()+d.findStays("r");if(l.$totalGuests.text(e),0<$("#selResource").length&&"0"!==$("#selResource").val()){var t="Room may be too small";e>x[$("#selResource").val()].maxOcc?$("#hhkroomMsg").text(t).show():$("#hhkroomMsg").text()==t&&$("#hhkroomMsg").text("").hide()}0<e?l.$totalGuests.parent().removeClass("ui-state-highlight"):l.$totalGuests.parent().addClass("ui-state-highlight")}),$("#"+d.divFamDetailId).on("click",".hhk-getVDialog",function(){var e=$(this).data("vid"),t=$(this).data("span");viewVisit(0,e,{"Show Statement":function(){window.open("ShowStatement.php?vid="+e,"_blank")},"Show Registration Form":function(){window.open("ShowRegForm.php?vid="+e+"&span="+t,"_blank")},Save:function(){saveFees(0,e,t,!1,payFailPage)},Cancel:function(){$(this).dialog("close")}},"Edit Visit #"+e+"-"+t,"hf",t),$("#submitButtons").hide()}),$(".hhk-cbStay").change(),$("#btnDone").val(a).show(),0<e.rid&&($("#btnDelete").val("Delete "+i).show(),$("#btnShowReg").show(),$("#spnStatus").text(""===e.resv.rdiv.rStatTitle?"":" - "+e.resv.rdiv.rStatTitle)),u($("#gstDate").val(),e.rid,e.resv.rdiv.hideCiNowBtn)),void 0!==e.addPerson&&($("input#txtPersonSearch").val(""),V.addItem(e.addPerson.mem)&&(T.addItem(e.addPerson.addrs),d.newGuestMarkup(e.addPerson,e.addPerson.mem.pref),d.findStaysChecked(),$(".hhk-cbStay").change(),$("#"+e.addPerson.mem.pref+"txtFirstName").focus())))}t.getReserve=O,t.verifyInput=function(){if(B.text("").hide(),!1===b.verify())return!1;if(!1===d.verify())return!1;if(!1===h.verify())return!1;if(!0===l.setupComplete&&!1===l.verify())return!1;return!0},t.loadResv=f,t.deleteReserve=function(e,a,i){var t="&cmd=delResv&rid="+e;$.post("ws_ckin.php",t,function(e){var t;try{t=$.parseJSON(e)}catch(e){flagAlertMessage(e.message,"error"),$(a).remove()}t.error&&(t.gotopage&&window.open(t.gotopage,"_self"),flagAlertMessage(t.error,"error"),$(a).remove()),t.warning&&(flagAlertMessage(t.warning,"warning"),i.hide()),t.result&&($(a).remove(),flagAlertMessage(t.result+' <a href="Reserve.php">Continue</a>',"success"))})},t.resvTitle=i,t.people=V,t.addrs=T,t.getIdPsg=function(){return F},t.getIdResv=function(){return k},t.getIdName=function(){return r},t.getIdVisit=function(){return s},t.getSpan=function(){return n},t.setRooms=p,t.options=D}
