/**
 * resvManager.js
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 */ function resvManager(e,t){var a=this,i=e.patLabel,r=e.visitorLabel;e.guestLabel;var s=e.primaryGuestLabel,n=e.resvTitle,o=e.saveButtonLabel,d=e.patBD,l=e.gstBD,c=e.patAddr,h=e.gstAddr,p=e.patAsGuest,u=void 0!==e.emergencyContact&&e.emergencyContact,v=void 0!==e.isCheckin&&e.isCheckin,f=e.addrPurpose,g=e.idPsg,m=e.rid,C=e.id,y=e.vid,k=e.span,x=e.arrival,b=e.insistPayFilledIn,w=e.prePaymt,S=!1,_=[],D=new W,R=new W,P=new function e(a){var n,o=this,m="divfamDetail",C=!1;function y(){var e=0;return $(".hhk-cbStay").each(function(){var t=$(this).data("prefix");$(this).prop("checked")?(D.list()[t].stay="1",e++):1===$(".hhk-cbStay").length?D.list()[t].stay="1":D.list()[t].stay="0"}),e}function k(){var e=$("input[type=radio][name=rbPriGuest]:checked").val();for(var t in D.list())D.list()[t].pri="0";void 0!==e&&(D.list()[e].pri="1")}function x(e){var t=$("#divfamDetail");!0===e?(t.show("blind"),t.prev("div").removeClass("ui-corner-all").addClass("ui-corner-top")):(t.hide("blind"),t.prev("div").addClass("ui-corner-all").removeClass("ui-corner-top"))}function b(e){"use strict";$("#ecSearch").dialog("close");var t=parseInt(e.id,10);if(!1===isNaN(t)&&t>0){var a=$("#hdnEcSchPrefix").val();if(""==a)return;$("#"+a+"txtEmrgFirst").val(e.first),$("#"+a+"txtEmrgLast").val(e.last),$("#"+a+"txtEmrgPhn").val(e.phone),$("#"+a+"txtEmrgAlt").val(""),$("#"+a+"selEmrgRel").val("")}}function w(e){var t=/^([\(]{1}[0-9]{3}[\)]{1}[\.| |\-]{0,1}|^[0-9]{3}[\.|\-| ]?)?[0-9]{3}(\.|\-| )?[0-9]{4}$/,a=!1;return $("#"+e+"incomplete").length>0&&!1===$("#"+e+"incomplete").prop("checked")&&($("."+e+"hhk-addr-val").not(".hhk-MissingOk").each(function(){""!==$(this).val()||$(this).hasClass("bfh-states")?$(this).removeClass("ui-state-error"):($(this).addClass("ui-state-error"),a=!0)}),a)?($("#"+e+"toggleAddr").find("span").hasClass("ui-icon-circle-triangle-s")&&$("#"+e+"toggleAddr").click(),"Some or all of the indicated addresses are missing.  "):($('.hhk-phoneInput[id^="'+e+'txtPhone"]').each(function(){""!==$.trim($(this).val())&&!1===t.test($(this).val())?($(this).addClass("ui-state-error"),$("#"+e+"toggleAddr").find("span").hasClass("ui-icon-circle-triangle-s")&&$("#"+e+"toggleAddr").click(),$("#"+e+"phEmlTabs").tabs("option","active",1),a=!0):$(this).removeClass("ui-state-error")}),"")}function _(e){var t=!1;return($("."+e+"hhk-demog-input").each(function(){""===$(this).val()?($(this).addClass("ui-state-error"),t=!0):$(this).removeClass("ui-state-error")}),t)?"Some or all Demographics are not set":""}function P(e){var t=!1,a=$("#"+e+"txtEmrgFirst"),i=$("#"+e+"txtEmrgLast"),r=$("#"+e+"txtEmrgPhn"),s=$("#"+e+"selEmrgRel");return(a.removeClass("ui-state-error"),i.removeClass("ui-state-error"),r.removeClass("ui-state-error"),s.removeClass("ui-state-error"),$("#"+e+"cbEmrgLater").length>0&&!1===$("#"+e+"cbEmrgLater").prop("checked")&&(""===a.val()&&""===i.val()&&(a.addClass("ui-state-error"),i.addClass("ui-state-error"),t=!0),""===r.val()&&(r.addClass("ui-state-error"),t=!0),""===s.val()&&(s.addClass("ui-state-error"),t=!0),t))?"Some or all of the indicated Emergency Contact Information is missing.  ":""}function A(e){return void 0!==e&&!!e&&""!=e&&""!==$("#"+e+"adraddress1"+f).val()&&""!==$("#"+e+"adrzip"+f).val()&&""!==$("#"+e+"adrstate"+f).val()&&""!==$("#"+e+"adrcity"+f).val()}function I(e){void 0!==e&&(R.list()[e].Address_1=$("#"+e+"adraddress1"+f).val(),R.list()[e].Address_2=$("#"+e+"adraddress2"+f).val(),R.list()[e].City=$("#"+e+"adrcity"+f).val(),R.list()[e].County=$("#"+e+"adrcounty"+f).val(),R.list()[e].State_Province=$("#"+e+"adrstate"+f).val(),R.list()[e].Country_Code=$("#"+e+"adrcountry"+f).val(),R.list()[e].Postal_Code=$("#"+e+"adrzip"+f).val(),N($("#"+e+"liaddrflag")))}function N(e){var t=e.data("pref");!0===$("#"+t+"incomplete").prop("checked")?(e.show().find("span").removeClass("ui-icon-alert").addClass("ui-icon-check").attr("title","Incomplete Address is checked"),e.removeClass("ui-state-error").addClass("ui-state-highlight")):A(t)?e.hide():(e.show().find("span").removeClass("ui-icon-check").addClass("ui-icon-alert").attr("title","Address is Incomplete"),e.removeClass("ui-state-highlight").addClass("ui-state-error"))}o.findStaysChecked=y,o.findStays=function e(t){var a=0;for(var i in D.list())D.list()[i].stay===t&&a++;return a},o.findPrimaryGuest=k,o.setUp=function e(i){var r,s,o,d,l,c;if(void 0!==i.famSection&&void 0!==i.famSection.tblId&&""!==i.famSection.tblId){for(var h in!1===C&&(o=i,d=$("<div/>").addClass("ui-widget-content ui-corner-bottom hhk-tdbox hhk-overflow-x").prop("id",m).css("padding","5px"),n=$("<table/>").prop("id",o.famSection.tblId).addClass("hhk-table").append($("<thead/>").append($(o.famSection.tblHead))).append($("<tbody/>")),d.append(n).append($(o.famSection.adtnl)),c=$("<ul style='list-style-type:none; float:right;margin-left:5px;padding-top:2px;' class='ui-widget'/>").append($("<li class='ui-widget-header ui-corner-all' title='Open - Close'>").append($("<span id='f_drpDown' class='ui-icon ui-icon-circle-triangle-n'></span>"))),(l=$('<div id="divfamHdr" style="padding:2px; cursor:pointer;"/>').append($(o.famSection.hdr)).append(c).append('<div style="clear:both;"/>')).addClass("ui-widget-header ui-state-default ui-corner-top"),l.click(function(){"none"===d.css("display")?(d.show("blind"),l.removeClass("ui-corner-all").addClass("ui-corner-top")):(d.hide("blind"),l.removeClass("ui-corner-top").addClass("ui-corner-all"))}),a.empty().append(l).append(d).show(),t.UseIncidentReports&&function e(){if(g){var t,i,r;t=$('<div style="font-size: 0.9em; max-width: 100%; margin-bottom: 0.5em; margin-top: 0.5em; display: none;"/>').addClass("ui-widget hhk-visitdialog hhk-row").prop("id","incidentsSection"),i=$('<div style="padding:2px; cursor:pointer;"/>').addClass("ui-widget-header ui-state-default ui-corner-all hhk-incidentHdr"),r=$('<div style="padding: 5px;"/>').addClass("ui-corner-bottom hhk-tdbox ui-widget-content").prop("id","incidentContent").hide(),i.append('<div class="hhk-checkinHdr" style="display: inline-block">Incidents<span id="incidentCounts"/></div>').append('<ul style="list-style-type:none; float:right;margin-left:5px;padding-top:2px;" class="ui-widget"><li class="ui-widget-header ui-corner-all" title="Open - Close"><span id="f_drpDown" class="ui-icon ui-icon-circle-triangle-n"></span></li></ul>'),r.incidentViewer({psgId:g,guestLabel:$("#guestLabel").val(),visitorLabel:$("#visitorLabel").val()}),i.click(function(){"none"===r.css("display")?(r.show("blind"),i.removeClass("ui-corner-all").addClass("ui-corner-top")):(r.hide("blind"),i.removeClass("ui-corner-top").addClass("ui-corner-all"))}),t.append(i).append(r).show(),a.find("#divfamDetail").append(t)}}()),i.famSection.mem){let p=D.findItem("pref",i.famSection.mem[h].pref);p&&(n.find("tr#"+p.id+"n").remove(),n.find("tr#"+p.id+"a").remove(),n.find("input#"+p.pref+"idName").parents("tr").next("tr").remove(),n.find("input#"+p.pref+"idName").parents("tr").remove(),D.removeIndex(p.pref),R.removeIndex(p.pref))}for(var u in D.makeList(i.famSection.mem,"pref"),R.makeList(i.famSection.addrs,"pref"),void 0!==i.famSection.tblBody["1"]&&n.find("tbody:first").prepend($(i.famSection.tblBody["1"])),void 0!==i.famSection.tblBody["0"]&&n.find("tbody:first").prepend($(i.famSection.tblBody["0"])),i.famSection.tblBody)"0"!==u&&"1"!==u&&n.find("tbody:first").append($(i.famSection.tblBody[u]));for(var y in $(".hhk-cbStay").checkboxradio({classes:{"ui-checkboxradio-label":"hhk-unselected-text"}}),$(".hhk-lblStay").each(function(){"1"==$(this).data("stay")&&$(this).click()}),$(".ckbdate").datepicker({yearRange:"-99:+00",changeMonth:!0,changeYear:!0,autoSize:!0,maxDate:0,dateFormat:"M d, yy"}),$(".hhk-addrPanel").find("select.bfh-countries").each(function(){var e=$(this);e.bfhcountries(e.data()),$(this).data("dirrty-initial-value",$(this).data("country"))}),$(".hhk-addrPanel").find("select.bfh-states").each(function(){var e=$(this);e.bfhstates(e.data()),$(this).data("dirrty-initial-value",$(this).data("state"))}),$(".hhk-phemtabs").tabs(),$("#InsTabs").tabs(),verifyAddrs("#divfamDetail"),$("input.hhk-zipsearch").each(function(){var e;createZipAutoComplete($(this),"ws_admin.php",e,I)}),!1===C&&($("#lnCopy").click(function(){var e=$("input.hhk-lastname").first().val();$("input.hhk-lastname").each(function(){""===$(this).val()&&$(this).val(e)})}),$("#adrCopy").click(function(){!function e(t){for(var a in R.list())t!=a&&(""===$("#"+a+"adraddress1"+f).val()||""===$("#"+a+"adrzip"+f).val())&&($("#"+a+"adraddress1"+f).val(R.list()[t].Address_1),$("#"+a+"adraddress2"+f).val(R.list()[t].Address_2),$("#"+a+"adrcity"+f).val(R.list()[t].City),$("#"+a+"adrcounty"+f).val(R.list()[t].County),$("#"+a+"adrzip"+f).val(R.list()[t].Postal_Code),$("#"+a+"adrcountry"+f).val()!=R.list()[t].Country_Code&&$("#"+a+"adrcountry"+f).val(R.list()[t].Country_Code).change(),$("#"+a+"adrstate"+f).val(R.list()[t].State_Province),!0===$("#"+t+"incomplete").prop("checked")?$("#"+a+"incomplete").prop("checked",!0):A(a)&&!0===$("#"+a+"incomplete").prop("checked")&&$("#"+a+"incomplete").prop("checked",!1),N($("#"+a+"liaddrflag")))}($("li.hhk-AddrFlag").first().data("pref"))}),$("#"+m).on("click",".hhk-togAddr",function(){r=$(this),s=$(this).siblings(),"none"===$(this).parents("tr").next("tr").css("display")?($(this).parents("tr").next("tr").show(),r.find("span").removeClass("ui-icon-circle-triangle-s").addClass("ui-icon-circle-triangle-n"),r.attr("title","Hide Address Section")):($(this).parents("tr").next("tr").hide(),r.find("span").removeClass("ui-icon-circle-triangle-n").addClass("ui-icon-circle-triangle-s"),r.attr("title","Show Address Section"),isIE()&&$("#divSelAddr").remove())}),$("#"+m).on("click",".hhk-AddrFlag",function(){$("#"+$(this).data("pref")+"incomplete").click()}),$("#"+m).on("change",".hhk-copy-target",function(){I($(this).data("pref"))}),$("#"+m).on("click",".hhk-addrCopy",function(){!function e(t,a){$(".hhk-addrPicker").remove();var i=$('<select id="selAddrch" multiple="multiple" />'),r=0,s=[];for(var n in R.list())if(""!=R.list()[n].Address_1||""!=R.list()[n].Postal_Code){for(var o=!0,d=R.list()[n].Address_1+", "+(""==R.list()[n].Address_2?"":R.list()[n].Address_2+", ")+R.list()[n].City+", "+R.list()[n].State_Province+"  "+R.list()[n].Postal_Code,l=0;l<=s.length;l++)if(s[l]==d){o=!1;continue}o&&(s[r]=d,r++,$('<option class="hhk-addrPickerPanel" value="'+n+'">'+d+"</option>").appendTo(i))}r>0&&(i.prop("size",r+1).prepend($('<option value="0" >(Cancel)</option>')),i.change(function(){(function e(t,a){if(0==a){$("#divSelAddr").remove();return}$("#"+t+"adraddress1"+f).val(R.list()[a].Address_1),$("#"+t+"adraddress2"+f).val(R.list()[a].Address_2),$("#"+t+"adrcity"+f).val(R.list()[a].City),$("#"+t+"adrcounty"+f).val(R.list()[a].County),$("#"+t+"adrzip"+f).val(R.list()[a].Postal_Code),$("#"+t+"adrcountry"+f).val()!=R.list()[a].Country_Code&&$("#"+t+"adrcountry"+f).val(R.list()[a].Country_Code).change(),$("#"+t+"adrstate"+f).val(R.list()[a].State_Province),A(t)&&!0===$("#"+t+"incomplete").prop("checked")&&$("#"+t+"incomplete").prop("checked",!1),N($("#"+t+"liaddrflag")),$("#divSelAddr").remove()})(a,$(this).val())}),$('<div id="divSelAddr" style="position:absolute; vertical-align:top;" class="hhk-addrPicker hhk-addrPickerPanel"/>').append($('<p class="hhk-addrPickerPanel">Choose an Address: </p>')).append(i).appendTo($("body")).position({my:"left top",at:"right center",of:t}))}($(this),$(this).data("prefix"))}),$("#"+m).on("click",".hhk-addrErase",function(){var e;e=$(this).data("prefix"),$("#"+e+"adraddress1"+f).val(""),$("#"+e+"adraddress2"+f).val(""),$("#"+e+"adrcity"+f).val(""),$("#"+e+"adrcounty"+f).val(""),$("#"+e+"adrstate"+f).val(""),$("#"+e+"adrcountry"+f).val(""),$("#"+e+"adrzip"+f).val(""),N($("#"+e+"liaddrflag"))}),$("#"+m).on("click",".hhk-incompleteAddr",function(){N($("#"+$(this).data("prefix")+"liaddrflag"))}),$("#"+m).on("click",".hhk-removeBtn",function(){(""===$("#"+$(this).data("prefix")+"txtFirstName").val()&&""===$("#"+$(this).data("prefix")+"txtLastName").val()||!1!==confirm("Remove this person: "+$("#"+$(this).data("prefix")+"txtFirstName").val()+" "+$("#"+$(this).data("prefix")+"txtLastName").val()+"?"))&&(D.removeIndex($(this).data("prefix")),R.removeIndex($(this).data("prefix")),$(this).parentsUntil("tbody","tr").next().remove(),$(this).parentsUntil("tbody","tr").remove())}),$("#"+m).on("change",".patientRelch",function(){"slf"===$(this).val()?D.list()[$(this).data("prefix")].role="p":D.list()[$(this).data("prefix")].role="g"}),createRoleAutoComplete($("#txtPersonSearch"),3,{cmd:"guest"},function(e){!function e(t,a,i){if(void 0!==t.No_Return&&""!==t.No_Return){flagAlertMessage("This person is set for No Return: "+t.No_Return+".","alert");return}if(void 0!==t.id){if(t.id>0&&null!==D.findItem("id",t.id)){flagAlertMessage("This person is already listed here. ","alert");return}var r={id:t.id,rid:a.rid,idPsg:a.idPsg,isCheckin:v,gstDate:$("#gstDate").val(),gstCoDate:$("#gstCoDate").val(),cmd:"addResvGuest",schTerm:i};q(r)}}(e,i,$("#txtPersonSearch").val())}),$("#"+m).on("click",".hhk-emSearch",function(){$("#hdnEcSchPrefix").val($(this).data("prefix")),$("#ecSearch").dialog("open")}),createAutoComplete($("#txtemSch"),3,{cmd:"filter",add:"phone",basis:"psg",psg:i.idPsg},b),$("ul.hhk-ui-icons li").hover(function(){$(this).addClass("ui-state-hover")},function(){$(this).removeClass("ui-state-hover")})),D.list())N($("#"+y+"liaddrflag"));$(".hhk-togAddr").each(function(){$(this).parents("tr").next("tr").hide(),$(this).find("span").removeClass("ui-icon-circle-triangle-n").addClass("ui-icon-circle-triangle-s"),$(this).attr("title","Show Address Section")}),C=!0}},o.newGuestMarkup=function e(t,a){var i,r,s,o,d;void 0!==t.tblId&&""!=t.tblId&&0!==n.length&&(s=n.children("tbody").children("tr").last().hasClass("odd")?"even":"odd",n.find("tbody:first").append($(t.ntr).addClass(s)).append($(t.atr).addClass(s)),$("#"+a+"cbStay").checkboxradio({classes:{"ui-checkboxradio-label":"hhk-unselected-text"}}),"1"==$("#"+a+"lblStay").data("stay")&&$("#"+a+"lblStay").click(),$(".ckbdate").datepicker({yearRange:"-99:+00",changeMonth:!0,changeYear:!0,autoSize:!0,maxDate:0,dateFormat:"M d, yy"}),d=(o=$("#"+a+"liaddrflag")).siblings(),N(o),d.parents("tr").next("tr").hide(),d.find("span").removeClass("ui-icon-circle-triangle-n").addClass("ui-icon-circle-triangle-s"),d.attr("title","Show Address Section"),(i=$("#"+a+"adrcountry"+f)).bfhcountries(i.data()),$(this).data("dirrty-initial-value",$(this).data("country")),(r=$("#"+a+"adrstate"+f)).bfhstates(r.data()),$(this).data("dirrty-initial-value",$(this).data("state")),$("#"+a+"phEmlTabs").tabs(),$("input#"+a+"adrzip1").each(function(){var e;createZipAutoComplete($(this),"ws_admin.php",e,I)}))},o.verify=function e(){var t=0,n=0,o=0,v=0,f=!1,g=0,m=!1;if($(".patientRelch").removeClass("ui-state-error"),$(".patientRelch").each(function(){""===$(this).val()?($(this).addClass("ui-state-error"),m=!0):$(this).removeClass("ui-state-error")}),m)return flagAlertMessage("Set the highlighted Relationship(s).","alert",E),!1;for(var b in k(),y(),D.list())t++,"p"===D.list()[b].role&&n++,"1"===D.list()[b].stay&&o++,"1"===D.list()[b].pri&&v++,$("#"+b+"toggleAddr").find("span").hasClass("ui-icon-circle-triangle-n")&&$("#"+b+"toggleAddr").click();if(n<1)return flagAlertMessage("Choose a "+i+".","alert",E),$(".patientRelch").addClass("ui-state-error"),!1;if(n>1){for(var b in flagAlertMessage("Only 1 "+i+" is allowed.","alert",E),D.list())"p"===D.list()[b].role&&$("#"+b+"selPatRel").addClass("ui-state-error");return!1}if(o<1)return flagAlertMessage("There is no one actually staying.  Pick someone to stay.","alert",E),!1;if($("input.hhk-rbPri").parent().removeClass("ui-state-error"),0===v&&1===t)for(var b in D.list())D.list()[b].pri="1";else if(0===v)return E.text("Set one "+r+" as "+s+".").show(),flagAlertMessage("Set one "+r+" as "+s+".","alert",E),$("input.hhk-rbPri").parent().addClass("ui-state-error"),!1;if(a.find(".hhk-lastname").each(function(){""==$(this).val()?($(this).addClass("ui-state-error"),f=!0):$(this).removeClass("ui-state-error")}),a.find(".hhk-firstname").each(function(){""==$(this).val()?($(this).addClass("ui-state-error"),f=!0):$(this).removeClass("ui-state-error")}),!0===f)return x(!0),flagAlertMessage("Enter a first and last name for the people highlighted.","alert",E),!1;for(var R in u&&a.find(".hhk-EmergCb").each(function(){var e=P($(this).data("prefix"));(!0===$(this).prop("checked")||""===e)&&g++}),D.list()){if("p"===D.list()[R].role){if(d&""===$("#"+R+"txtBirthDate").val())return $("#"+R+"txtBirthDate").addClass("ui-state-error"),flagAlertMessage(i+" is missing the Birth Date.","alert",E),x(!0),!1;if($("#"+R+"txtBirthDate").removeClass("ui-state-error"),c||p){var A=w(R);if(""!==A)return flagAlertMessage(A,"alert",E),x(!0),$("#"+R+"toggleAddr").find("span").hasClass("ui-icon-circle-triangle-s")&&$("#"+R+"toggleAddr").click(),!1}}else{if(l&""===$("#"+R+"txtBirthDate").val())return $("#"+R+"txtBirthDate").addClass("ui-state-error"),flagAlertMessage(r+" is missing the Birth Date.","alert",E),x(!0),!1;if($("#"+R+"txtBirthDate").removeClass("ui-state-error"),h){var A=w(R);if(""!==A)return flagAlertMessage(A,"alert",E),x(!0),$("#"+R+"toggleAddr").find("span").hasClass("ui-icon-circle-triangle-s")&&$("#"+R+"toggleAddr").click(),!1}}if($("#"+R+"txtBirthDate").length>0&&""!==$("#"+R+"txtBirthDate").val()){var I=new Date($("#"+R+"txtBirthDate").val()),N=new Date;if(I>N)return $("#"+R+"txtBirthDate").addClass("ui-state-error"),flagAlertMessage("This birth date cannot be in the future.","alert",E),x(!0),!1;$("#"+R+"txtBirthDate").removeClass("ui-state-error")}if(u&&g<1){var A=P(R);if(""!==A)return flagAlertMessage(A,"alert",E),x(!0),$("#"+R+"toggleAddr").find("span").hasClass("ui-icon-circle-triangle-s")&&$("#"+R+"toggleAddr").click(),!1}if(S){var A=_(R);if(""!==A)return flagAlertMessage(A,"alert",E),x(!0),$("#"+R+"toggleAddr").find("span").hasClass("ui-icon-circle-triangle-s")&&$("#"+R+"toggleAddr").click(),!1}}return C=!1,!0},o.divFamDetailId=m,o.$famTbl=n}($("#famSection")),A=new function e(t){var a,i,r,s,n=this;function o(e){var t=_[$("#selResource").val()],a=!0;$("#selccgw"+e+" option").each(function(){this.value===t.merchant&&(a=!1)}),a&&$("#selccgw"+e).append('<option value="'+t.merchant+'">'+t.merchant+"</option>"),$("#selccgw"+e).val(t.merchant)}function d(e){"use strict";var t="";return""===$("#car"+e+"txtVehLic").val()&&""===$("#car"+e+"txtVehMake").val()?"Enter vehicle info or check the 'No Vehicle' checkbox. ":(""===$("#car"+e+"txtVehLic").val()?(""===$("#car"+e+"txtVehModel").val()&&($("#car"+e+"txtVehModel").addClass("ui-state-highlight"),t="Enter Model"),""===$("#car"+e+"txtVehColor").val()&&($("#car"+e+"txtVehColor").addClass("ui-state-highlight"),t="Enter Color"),""===$("#car"+e+"selVehLicense").val()&&($("#car"+e+"selVehLicense").addClass("ui-state-highlight"),t="Enter state license plate registration")):""===$("#car"+e+"txtVehMake").val()&&""===$("#car"+e+"txtVehLic").val()&&($("#car"+e+"txtVehLic").addClass("ui-state-highlight"),t="Enter a license plate number."),t)}n.setupComplete=!1,n.checkPayments=!0,n.setUp=function e(d){if(a=$("<div/>").addClass(" hhk-tdbox ui-corner-bottom hhk-tdbox ui-widget-content hhk-flex hhk-flex-wrap hhk-overflow-x").prop("id","divResvDetail").css({padding:"5px"}),void 0!==d.resv.rdiv.rChooser&&a.append($(d.resv.rdiv.rChooser)),void 0!==d.resv.rdiv.rate&&a.append($(d.resv.rdiv.rate)),void 0!==d.resv.rdiv.cof&&a.append(d.resv.rdiv.cof),void 0!==d.resv.rdiv.rstat&&a.append($(d.resv.rdiv.rstat)),void 0!==d.resv.rdiv.vehicle&&(i=$(d.resv.rdiv.vehicle),a.append(i),c=2,h=(l=i).find("#cbNoVehicle"),p=l.find("#btnNextVeh"),u=l.find("#tblVehicle"),h.change(function(){this.checked?u.hide("scale, horizontal"):u.show("scale, horizontal")}),h.change(),p.button(),p.click(function(){l.find("#trVeh"+c).show("fade"),++c>4&&p.hide("fade")})),void 0!==d.resv.rdiv.pay&&a.append($(d.resv.rdiv.pay)),void 0!==d.resv.rdiv.notes&&a.append((v=d.rid,(f=$(d.resv.rdiv.notes)).notesViewer({linkId:v,linkType:"reservation",newNoteAttrs:{id:"taNewNote",name:"taNewNote"},alertMessage:function(e,t){flagAlertMessage(e,t)}}),f)),void 0!==d.resv.rdiv.wlnotes&&a.append($(d.resv.rdiv.wlnotes)),s=$("<ul style='list-style-type:none; float:right; margin-left:5px; padding-top:2px;' class='ui-widget'/>").append($("<li class='ui-widget-header ui-corner-all' title='Open - Close'>").append($("<span id='r_drpDown' class='ui-icon ui-icon-circle-triangle-n'></span>"))),(r=$('<div id="divResvHdr" style="padding:2px; cursor:pointer;"/>').append($(d.resv.hdr)).append(s).append('<div style="clear:both;"/>')).addClass("ui-widget-header ui-state-default ui-corner-top"),r.click(function(e){var t=$(e.target);("divResvHdr"===t[0].id||"r_drpDown"===t[0].id)&&("none"===a.css("display")?(a.show("blind"),r.removeClass("ui-corner-all").addClass("ui-corner-top")):(a.hide("blind"),r.removeClass("ui-corner-top").addClass("ui-corner-all")))}),t.empty().append(r).append(a).show(),n.$totalGuests=$("#spnNumGuests"),n.origRoomId=$("#selResource").val(),n.checkPayments=!0,$(".hhk-viewResvActivity").length>0&&$(".hhk-viewResvActivity").click(function(){$.post("ws_ckin.php",{cmd:"viewActivity",rid:$(this).data("rid")},function(e){if((e=$.parseJSON(e)).error){e.gotopage&&window.open(e.gotopage,"_self"),flagAlertMessage(e.error,"error");return}e.activity&&($("div#submitButtons").hide(),$("#activityDialog").children().remove(),$("#activityDialog").append($(e.activity)),$("#activityDialog").dialog("open"))})}),$("#btnShowCnfrm").button().click(function(){var e=$("#spnAmount").text();""===e&&(e=0),$.post("ws_ckin.php",{cmd:"confrv",rid:$(this).data("rid"),amt:e,eml:"0"},function(e){if((d=$.parseJSON(e)).error){d.gotopage&&window.open(d.gotopage,"_self"),flagAlertMessage(d.error,"error");return}if(d.confrv){$("div#submitButtons").hide(),$("#frmConfirm").children().remove(),$("#frmConfirm").html(d.confrv);var t='<div class="col-md-6 my-2 hhk-flex"><label for="confEmail" class="pr-2" style="min-width:fit-content;">To Address</label><input type="text" style="width:100%" id="confEmail" value="'+d.email+'"></div>';t+='<div class="col-md-6 my-2 hhk-flex"><label for="ccConfEmail" class="pr-2" style="min-width:fit-content;">CC Address</label><input type="text" style="width:100%" id="ccConfEmail" value="'+d.ccemail+'"></div>',$("#frmConfirm").append($('<div class="ui-dialog-buttonpane ui-widget-content ui-helper-clearfix hhk-flex hhk-flex-wrap">'+t+"</div>")),$("#frmConfirm").find("#confirmTabDiv").tabs(),$("#confirmDialog").dialog("open")}})}),g=d.rid,M.idReservation=g,$("input.hhk-constraintsCB").change(function(){M.go($("#gstDate").val(),$("#gstCoDate").val())}),void 0!==d.resv.rdiv.rate&&(C=d,y={},(k=t.find("#btnFapp")).length>0&&($("#faDialog").dialog({autoOpen:!1,resizable:!0,width:getDialogWidth(680),modal:!0,title:"Income Chooser",close:function(){$("div#submitButtons").show()},open:function(){$("div#submitButtons").hide()},buttons:{Save:function(){$.post("ws_ckin.php",$("#formf").serialize()+"&cmd=savefap&rid="+C.rid,function(e){try{e=$.parseJSON(e)}catch(t){alert("Bad JSON Encoding");return}if(e.gotopage&&window.open(e.gotopage,"_self"),e.rstat&&!0==e.rstat){var a=$("#selRateCategory");e.rcat&&""!=e.rcat&&a.length>0&&(a.val(e.rcat),a.change())}}),$(this).dialog("close")},Exit:function(){$(this).dialog("close")}}}),k.button().click(function(){getIncomeDiag(C.rid)})),C.resv.rdiv.ratelist&&(y.rateList=C.resv.rdiv.ratelist,y.resources=C.resv.rdiv.rooms,y.visitFees=C.resv.rdiv.vfee,y.idResv=m,setupRates(y)),$("#selResource").length>0&&$("#selResource").change(function(){var e=_[$("#selResource").val()];e.defaultRateCat&&""!=e.defaultRateCat&&"a"==T&&$("#selRateCategory").val(e.defaultRateCat),$("#selRateCategory").change();var t=$("option:selected",this).parent()[0].label;null==t?$("#hhkroomMsg").hide():$("#hhkroomMsg").text(t).show(),$("#selccgw").length>0?o(""):$("#selccgwg").length>0&&o("g")})),void 0!==d.resv.rdiv.pay&&$("#selResource").length>0&&$("#selRateCategory").length>0&&(setupPayments($("#selRateCategory")),$("#paymentDate").datepicker({yearRange:"-1:+00",numberOfMonths:1,autoSize:!0,dateFormat:"M d, yy"}),$("#selResvStatus").change(function(){w>0&&"a"!=$(this).val()&&"uc"!=$(this).val()&&"w"!=$(this).val()?(isCheckedOut=!0,$("#cbHeld").prop("checked",!0),amtPaid()):(isCheckedOut=!1,$("#cbHeld").prop("checked",!1),amtPaid())}),$("#selResvStatus").change()),void 0!==d.resv.rdiv.cof){var l,c,h,p,u,v,f,g,C,y,k,x,b=_[$("#selResource").val()];$("#btnUpdtCred").button().click(function(){cardOnFile($(this).data("id"),$(this).data("idreg"),"Reserve.php?rid="+m,$(this).data("indx"))}),setupCOF($("#trvdCHNameg"),$("#btnUpdtCred").data("indx")),$("#selccgwg").val(b.merchant)}$("#addGuestHeader").length>0&&(N.openControl=!0,N.setUp(d.resv.rdiv,U,$("#addGuestHeader"))),n.setupComplete=!0},n.verify=function e(){if($("#cbNoVehicle").length>0){if(!1===$("#cbNoVehicle").prop("checked")){var t=d(1);if(""!=t){var a=d(2);if(""!=a)return $("#vehValidate").text(a),flagAlertMessage(t,"alert",E),!1}}$("#vehValidate").text("")}if(v){if(!0===n.checkPayments){if($("#selCategory").val()==fixedRate&&$("#txtFixedRate").length>0&&""==$("#txtFixedRate").val())return flagAlertMessage("Set the Room Rate to an amount, or to 0.","alert",E),$("#txtFixedRate").addClass("ui-state-error"),!1;if($("#txtFixedRate").removeClass("ui-state-error"),$("input#feesPayment").length>0&&""==$("input#feesPayment").val()&&b)return flagAlertMessage("Set the Room Fees to an amount, or 0.","alert",E),$("#payChooserMsg").text("Set the Room Fees to an amount, or 0.").show(),$("input#feesPayment").addClass("ui-state-error"),!1;if($("input#feesPayment").removeClass("ui-state-error"),void 0!==verifyAmtTendrd&&!1===verifyAmtTendrd())return!1}}else{if($("#selccgw").length>0&&(0==$("input[name=rbUseCard]:checked").val()||!0===$("input[name=rbUseCard]").prop("checked"))&&($("#selccgw").removeClass("ui-state-highlight"),0===$("#selccgw option:selected").length))return $("#tdChargeMsg").text("Select a location.").show("fade"),$("#selccgw").addClass("ui-state-highlight"),!1;if(w>0&&isCheckedOut&&""==$("#selexcpay").val())return $("#selexcpay").addClass("ui-state-error"),flagAlertMessage("Determine how to handle the pre-payment.","alert",E),$("#payChooserMsg").text("Determine how to handle the pre-payment.").show(),!1;$("#selexcpay").removeClass("ui-state-error")}return!0}}($("#resvSection")),I=new function e(t){var a=this;a.setupComplete=!1,a.setUp=function(e){var i=$(e.div).addClass("ui-widget-content").prop("id","divhospDetail").hide(),r=$("<ul style='list-style-type:none; float:right;margin-left:5px;padding-top:2px;' class='ui-widget'/>").append($("<li class='ui-widget-header ui-corner-all' title='Open - Close'>").append($("<span id='h_drpDown' class='ui-icon ui-icon-circle-triangle-n'></span>"))),s=$('<div id="divhospHdr" style="padding:2px; cursor:pointer;"/>').append($(e.hdr)).append(r).append('<div style="clear:both;"/>');s.addClass("ui-widget-header ui-state-default ui-corner-all"),s.click(function(){"none"===i.css("display")?(i.show("blind"),s.removeClass("ui-corner-all").addClass("ui-corner-top")):(i.hide("blind"),s.removeClass("ui-corner-top").addClass("ui-corner-all"))}),t.empty().append(s).append(i),$("#txtEntryDate, #txtExitDate").datepicker({yearRange:"-01:+01",changeMonth:!0,changeYear:!0,autoSize:!0,dateFormat:"M d, yy"}),$("#txtAgentSch").length>0&&(createAutoComplete($("#txtAgentSch"),3,{cmd:"filter",basis:"ra"},getAgent),""===$("#a_txtLastName").val()&&$(".hhk-agentInfo").hide(),$(document).on("click","#a_delete",function(){$("#a_idName").val(""),$("input.hhk-agentInfo").val(""),$(".hhk-agentInfo").hide()}),""!==$("#a_idName").val()?$("input.hhk-agentInfo.name").attr("readonly","readonly"):$("input.hhk-agentInfo.name").removeAttr("readonly")),$("#txtDocSch").length>0&&(createAutoComplete($("#txtDocSch"),3,{cmd:"filter",basis:"doc"},getDoc),""===$("#d_txtLastName").val()&&$(".hhk-docInfo").hide(),""!==$("#d_idName").val()?$("input.hhk-docInfo.name").attr("readonly","readonly"):$("input.hhk-docInfo.name").removeAttr("readonly"),$(document).on("click","#d_delete",function(){$("#d_idName").val(""),$("input.hhk-docInfo").val(""),$(".hhk-docInfo").hide()})),verifyAddrs("#divhospDetail"),t.on("change","#selHospital, #selAssoc",function(){var e=$("#selAssoc").find("option:selected").text();""!=e&&(e+="/ "),$("span#spnHospName").text(e+$("#selHospital").find("option:selected").text())}),t.show(),""===$("#selHospital").val()&&s.click(),a.setupComplete=!0},a.verify=function(){return(t.find(".ui-state-error").each(function(){$(this).removeClass("ui-state-error")}),$("#selHospital").length>0&&!0===a.setupComplete&&""==$("#selHospital").val())?($("#selHospital").addClass("ui-state-error"),flagAlertMessage("Select a hospital.","alert",E),$("#divhospDetail").show("blind"),$("#divhospHdr").removeClass("ui-corner-all").addClass("ui-corner-top"),!1):($("#divhospDetail").hide("blind"),$("#divhospHdr").removeClass("ui-corner-top").addClass("ui-corner-all"),!0)}}($("#hospitalSection")),N=new function e(){var t=this;t.setupComplete=!1,t.ciDate=new Date,t.coDate=new Date,t.openControl=!1,t.setUp=function(e,a,i){if(i.empty(),e.mu&&""!==e.mu){i.append($(e.mu));var r,s=$("#gstDate"),n=$("#gstCoDate"),o=parseInt(e.defdays,10),d=!1,l=!1;""===s.val()&&x&&s.val(x),e.startDate&&(d=e.startDate),e.endDate&&(l=e.endDate),$("#spnRangePicker").length>0&&(r=$("#spnRangePicker").dateRangePicker({format:"MMM D, YYYY",separator:" to ",minDays:1,autoClose:!0,showShortcuts:!0,shortcuts:{"next-days":[o]},getValue:function(){return s.val()&&n.val()?s.val()+" to "+n.val():""},setValue:function(e,t,a){s.val(t),n.val(a)},startDate:d,endDate:l}),e.updateOnChange&&r.bind("datepicker-change",function(t,i){var r=Math.ceil((i.date2.getTime()-i.date1.getTime())/864e5);$("#"+e.daysEle).val(r),$("#spnNites").length>0&&$("#spnNites").text(r),$("#gstDate").removeClass("ui-state-error"),$("#gstCoDate").removeClass("ui-state-error"),$.isFunction(a)&&a(i)})),i.show(),t.openControl&&$("#spnRangePicker").length>0&&r.open()}setupComplete=!0},t.verify=function(){var e=$("#gstDate"),a=$("#gstCoDate");if(e.removeClass("ui-state-error"),a.removeClass("ui-state-error"),""===e.val()||(t.ciDate=new Date(e.val()),isNaN(t.ciDate.getTime())))return e.addClass("ui-state-error"),flagAlertMessage("This "+n+" is missing the check-in date.","alert",E),!1;if(void 0!==v&&!0===v){var i=moment($("#gstDate").val(),"MMM D, YYYY"),r=moment().endOf("date");if(i>r)return e.addClass("ui-state-error"),flagAlertMessage("Set the Check in date to today or earlier.","alert",E),!1}return""===a.val()?(a.addClass("ui-state-error"),flagAlertMessage("This "+n+" is missing the expected departure date.","alert",E),!1):(t.coDate=new Date(a.val()),isNaN(t.coDate.getTime()))?(a.addClass("ui-state-error"),flagAlertMessage("This "+n+" is missing the expected departure date","alert",E),!1):!(t.ciDate>t.coDate)||(e.addClass("ui-state-error"),flagAlertMessage("This "+n+"'s check-in date is after the expected departure date.","alert",E),!1)}},M=new M,E=$("#pWarnings"),t=t,B="",T="";function F(){return w}function L(e){_=e}function V(){return m}function H(){return y}function O(){return k}function z(){return g}function Y(){return C}function G(){return S}function U(e){E.text("").hide();var t=!1;for(var a in D.list())if(D.list()[a].id>0){t=!0;break}if(t){$(".hhk-stayIndicate").hide().parent("td").addClass("hhk-loading");var i={cmd:"updateAgenda",idPsg:g,idResv:m,idVisit:y,span:k,dt1:e.date1.getFullYear()+"-"+(e.date1.getMonth()+1)+"-"+e.date1.getDate(),dt2:e.date2.getFullYear()+"-"+(e.date2.getMonth()+1)+"-"+e.date2.getDate(),mems:D.list()};$.post("ws_resv.php",i,function(e){$(".hhk-stayIndicate").show().parent("td").removeClass("hhk-loading");try{e=$.parseJSON(e)}catch(t){flagAlertMessage(t.message,"error");return}if(e.gotopage&&window.open(e.gotopage,"_self"),e.error&&flagAlertMessage(e.error,"error"),e.stayCtrl){for(var a in e.stayCtrl){var i;$("#sb"+a).empty().html(e.stayCtrl[a].ctrl),$("#"+a+"cbStay").checkboxradio({classes:{"ui-checkboxradio-label":"hhk-unselected-text"}}),D.list()[a].stay="0",(i=$("#"+a+"lblStay")).length>0&&"1"==i.data("stay")&&i.click()}$(".hhk-getVDialog").button(),""!=$("#gstDate").val()&&""!=$("#gstCoDate").val()&&M.go($("#gstDate").val(),$("#gstCoDate").val()),$(".hhk-cbStay").change()}})}J(e.date1.t,m)}function J(e,t,a){var i=moment(e,"MMM D, YYYY"),r=moment().endOf("date");t>0&&i<=r&&!a?$("#btnCheckinNow").show():$("#btnCheckinNow").hide()}function M(){var e=this,t={};e.omitSelf=!0,e.numberGuests=0,e.idReservation=0,e.go=function a(i,r){var s,n=$("#selResource");0!==n.length&&(s=n.find("option:selected").val(),n.prop("disabled",!0),$("#hhk-roomChsrtitle").addClass("hhk-loading"),$("#hhkroomMsg").text("").hide(),t={},$("input.hhk-constraintsCB:checked").each(function(){t[$(this).data("cnid")]="ON"}),$.post("ws_ckin.php",{cmd:"newConstraint",rid:e.idReservation,numguests:e.numberGuests,expArr:i,expDep:r,idr:s,cbRS:t,omsf:e.omitSelf},function(e){var t,a;n.prop("disabled",!1),$("#hhk-roomChsrtitle").removeClass("hhk-loading");try{e=$.parseJSON(e)}catch(i){alert("Parser error - "+i.message);return}if(e.error){e.gotopage&&window.location.assign(e.gotopage),flagAlertMessage(e.error,"error");return}e.rooms&&(_=a=e.rooms),e.selectr&&(t=$(e.selectr),n.children().remove(),t.children().appendTo(n),n.val(e.idResource).change(),e.msg&&""!==e.msg&&$("#hhkroomMsg").text(e.msg).show())}))}}function W(){var e,t={},a=this;function i(){return t}function r(a){return!1===s(a)&&(t[a[e]]=a,!0)}function s(a){return void 0!==t[a[e]]}a.hasItem=s,a.findItem=function e(a,i){for(var r in t)if(t[r][a]==i)return t[r];return null},a.addItem=r,a.removeIndex=function e(a){delete t[a]},a.list=i,a.makeList=function t(a,i){for(var s in e=i,a)r(a[s])},a._list=t}function j(e,t){"use strict";$("input#txtPersonSearch").val(""),t.empty().append($(e.psgChooser)).dialog("option","buttons",{Open:function(){$(this).dialog("close"),q({idPsg:t.find("input[name=cbselpsg]:checked").val(),id:e.id,cmd:"getResv"})},Cancel:function(){$(this).dialog("close"),$("input#gstSearch").val("").focus()}}).dialog("option","title",e.patLabel+" Chooser"+(void 0===e.fullName?"":" For: "+e.fullName)).dialog("open")}function q(e){var t={id:e.id,rid:e.rid,idPsg:e.idPsg,vid:e.vid,span:e.span,isCheckin:v,gstDate:e.gstDate,gstCoDate:e.gstCoDate,cmd:e.cmd};$.post("ws_resv.php",t,function(e){try{e=$.parseJSON(e)}catch(t){flagAlertMessage(t.message,"error");return}e.gotopage&&window.open(e.gotopage,"_self"),e.error&&(flagAlertMessage(e.error,"error",E),$("#btnDone").val("Save "+n).show()),K(e)})}function K(e){var t,a,i,r,s,d;if(e.xfer||e.inctx){paymentRedirect(t=e,$("#xform"));return}if(e.deleted){$("#guestSearch").hide(),$("#contentDiv").append("<p>"+e.deleted+"</p>"),$("#spnStatus").text("Deleted");return}if(e.resvChooser&&""!==e.resvChooser){a=e,i=$("#resDialog"),r=$("#psgDialog"),s={},$("input#txtPersonSearch").val(""),i.empty().append($(a.resvChooser)).children().find("input:button").button(),i.children().find(".hhk-checkinNow").click(function(){window.open("CheckingIn.php?rid="+$(this).data("rid")+"&gid="+a.id,"_self")}),a.psgChooser&&""!==a.psgChooser&&(s[a.patLabel+" Chooser"]=function(){$(this).dialog("close"),j(a,r)}),a.resvTitle&&(s["New "+a.resvTitle]=function(){a.rid=-1,a.cmd="getResv",$(this).dialog("close"),q(a)}),s.Exit=function(){$(this).dialog("close"),$("input#gstSearch").val("").focus()},i.dialog("option","width","95%"),i.dialog("option","buttons",s),i.dialog("option","title",a.resvTitle+" Chooser"),i.dialog("open"),d=i.find("table").width(),i.dialog("option","width",d+80);return}if(e.psgChooser&&""!==e.psgChooser){j(e,$("#psgDialog"));return}e.idPsg&&(g=e.idPsg),e.id&&(C=e.id),e.rid&&(m=e.rid),e.vid&&(y=e.vid),e.span&&(k=e.span),e.resvStatusCode&&(B=e.resvStatusCode),e.resvStatusType&&(T=e.resvStatusType),e.prePayment&&(w=e.prePayment),void 0!==e.hosp&&I.setUp(e.hosp),e.famSection&&(P.setUp(e),$("div#guestSearch").hide(),$("#btnDone").val("Save Family").show(),$("select.hhk-multisel").each(function(){$(this).multiselect({selectedList:3})})),void 0!==e.expDates&&""!==e.expDates&&(N.openControl=!1,N.setUp(e.expDates,U,$("#datesSection"))),void 0!==e.warning&&""!==e.warning&&flagAlertMessage(e.warning,"warning",E),void 0!==e.resv&&(e.resv.rdiv.rooms&&(_=e.resv.rdiv.rooms),A.setUp(e),$("#"+P.divFamDetailId).on("change",".hhk-cbStay",function(){var e=P.findStaysChecked()+P.findStays("r");if(A.$totalGuests.text(e),$("#selRateCategory").trigger("change"),$("#selResource").length>0&&"0"!==$("#selResource").val()){var t="Room may be too small",a=_[$("#selResource").val()];e>a.maxOcc?$("#hhkroomMsg").text(t).show():$("#hhkroomMsg").text()==t&&$("#hhkroomMsg").text("").hide()}e>0?A.$totalGuests.parent().removeClass("ui-state-highlight"):A.$totalGuests.parent().addClass("ui-state-highlight")}),$("#"+P.divFamDetailId).on("click",".hhk-getVDialog",function(){var e,t=$(this).data("vid"),a=$(this).data("span");e={"Show Statement":function(){window.open("ShowStatement.php?vid="+t,"_blank")},"Show Registration Form":function(){window.open("ShowRegForm.php?vid="+t+"&span="+a,"_blank")},Save:function(){saveFees(0,t,a,!1,payFailPage)},Cancel:function(){$(this).dialog("close")}},viewVisit(0,t,e,"Edit Visit #"+t+"-"+a,"hf",a),$("#submitButtons").hide()}),$(".hhk-cbStay").change(),$("#btnDone").val(o).show(),e.rid>0&&($("#btnDelete").val("Delete "+n).show(),$("#btnShowReg").show(),$("#spnStatus").text(""===e.resv.rdiv.rStatTitle?"":" - "+e.resv.rdiv.rStatTitle)),J($("#gstDate").val(),e.rid,e.resv.rdiv.hideCiNowBtn),S=e.insistCkinDemog),void 0!==e.addPerson&&($("input#txtPersonSearch").val(""),D.addItem(e.addPerson.mem)&&(R.addItem(e.addPerson.addrs),P.newGuestMarkup(e.addPerson,e.addPerson.mem.pref),P.findStaysChecked(),$(".hhk-cbStay").change(),$("#"+e.addPerson.mem.pref+"txtFirstName").focus())),$("#submitButtons").show()}a.getReserve=q,a.verifyInput=function e(){return E.text("").hide(),!1!==N.verify()&&!1!==P.verify()&&!1!==I.verify()&&(!0!==A.setupComplete||!1!==A.verify())},a.loadResv=K,a.deleteReserve=function e(){return!(w>0)||""!=$("#selexcpay").val()||(isCheckedOut?($("#selexcpay").addClass("ui-state-error"),flagAlertMessage("Determine how to handle the pre-payment.","alert",E),$("#payChooserMsg").text("Determine how to handle the pre-payment.").show(),!1):($("#selexcpay").removeClass("ui-state-error"),isCheckedOut=!0,$("#cbHeld").prop("checked",!0),amtPaid(),!1))},a.resvTitle=n,a.people=D,a.addrs=R,a.getIdPsg=z,a.getIdResv=V,a.getIdName=Y,a.getIdVisit=H,a.getPrePaymtAmt=F,a.getSpan=O,a.setRooms=L,a.options=t}
 