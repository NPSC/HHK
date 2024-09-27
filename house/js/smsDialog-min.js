!function(e){e.fn.smsDialog=function(t){var s={smsTabs:`<div id="smsTabs" class="ui-tabs ui-corner-all, ui-widget ui-widget-content ui-tabs-vertical ui-helper-clearfix ui-corner-all">
    <ul class="ui-tabs-nav ui-corner-all ui-helper-reset ui-helper-clearfix ui-widget-header"></ul>
</div> `,msgsTabMkup:`<div id="msgsTabContent" class="hhk-overflow-x ui-tabs-panel ui-corner-all ui-widget-content">
<h4 class="msgTitle"></h4>
<div class="msgsContainer loading"></div>
<div class="newMsg hhk-flex">
    <textarea class="ui-widget-content ui-corner-all" maxlength="306" placeholder="Message..."></textarea>
    <button class="ui-button ui-corner-all sendMsg" name="sendMsg" title="Send Message"><i class="bi bi-send-fill"></i></button>
</div>
</div>`,allGuestsTabMkup:`<div id="allGuestsTabContent" class="hhk-overflow-x ui-tabs-panel ui-corner-all ui-widget-content">
<h4 class="msgTitle"></h4>
<div class="allRecipients ui-widget-content ui-corner-all">
    <h5>To</h5>
</div>
<div class="newMsg hhk-flex">
    <textarea class="ui-widget-content ui-corner-all" maxlength="306" placeholder="Message..."></textarea>
    <button class="ui-button ui-corner-all sendMsg" name="sendMsg" title="Send Message"><i class="bi bi-send-fill"></i></button>
</div>
</div>`,campaignTabMkup:`<div id="campaignTabContent" class="hhk-overflow-x ui-tabs-panel ui-corner-all ui-widget-content">
<h4 class="msgTitle"></h4>
<div class="allRecipients ui-widget-content ui-corner-all">
    <h5>To</h5>
</div>
<div class="newMsg hhk-flex">
    <textarea class="ui-widget-content ui-corner-all" maxlength="288" placeholder="Message..."></textarea>
    <button class="ui-button ui-corner-all sendCampaign" name="sendCampaign" title="Send Message"><i class="bi bi-send-fill"></i></button>
</div>
</div>`,msgMkup:`<div class="msgContainer hhk-flex"> 
    <div class="msg">
        <div class="msgContent ui-widget-content ui-corner-all"></div>
        <div class="msgMeta"></div>
    </div>
</div>`,sendIcon:'<i class="bi bi-send-fill"></i>'},i=e(document).find("#viewMsgsDialog");0==i.length&&(i=e('<div id="viewMsgsDialog" class="loading">'));var n,l,d,o,r=e.extend(!0,{},{guestId:0,psgId:0,resvId:0,visitId:0,spanId:0,campaign:!1,autoOpen:!1,dialogTitle:"Text Guests",serviceURL:"ws_resv.php",guestData:[]},t),g=e(this);return function e(a,t){a.off("click","*")}(i,r),n=i,l=s,d=r,(o=g).on("click",function(a){a.preventDefault(),function a(t,s,i){t.dialog({autoOpen:!1,position:{my:"top",at:"top+10%"},width:getDialogWidth(600),resizable:!0,modal:!0,title:i.dialogTitle,buttons:{Close:function(){e(this).dialog("close")}},close:function(a,t){e(this).empty().dialog("destroy")}})}(n,l,d),n.dialog("open")}),n.on("dialogopen",function(t,s){(function t(s,i,n){if(s.addClass("loading"),n.visitId||n.resvId||n.guestId){if(n.visitId)var l={cmd:"getVisitMsgsDialog",idVisit:n.visitId,idSpan:n.spanId};else if(n.resvId)var l={cmd:"getResvMsgsDialog",idResv:n.resvId};else if(n.guestId)var l={cmd:"getGuestMsgsDialog",idName:n.guestId};e.ajax({method:"get",url:n.serviceURL,data:l,dataType:"json",success:function(t){n.guestData=t,n.guestData[0]&&n.guestData[0].Room?s.dialog("option","title","Text Guests in Room "+n.guestData[0].Room):n.guestData[0]&&n.guestData[0].dialogTitle&&s.dialog("option","title",n.guestData[0].dialogTitle);var l=0;if(e.each(n.guestData,function(e,a){1==a.isMobile&&l++}),n.guestData.length>1&&l>0){s.html(i.smsTabs),s.find("#smsTabs ul").append(e('<li><a href="#allGuestsTabContent">Current Guests</a></li>'));let d=[];e.each(n.guestData,function(a,t){li=e('<li><a href="#msgsTabContent">'+t.Name_Full+"</a></li>").data(t),0==t.isMobile&&d.push(a+1),s.find("#smsTabs ul").append(li)}),s.find("#smsTabs").append(i.msgsTabMkup+i.allGuestsTabMkup).tabs({active:0,disabled:d,create:function(t,l){l.tab&&l.tab.data("Name_Full")&&(l.panel.find(".msgTitle").text(l.tab.data("Name_Full")+" - "+l.tab.data("Phone_Num")),l.panel.find(".newMsg textarea").attr("placeholder","Message "+l.tab.data("Phone_Num")+"...").val(""),l.panel.find(".newMsg button[name=sendMsg]").data("idname",l.tab.data("idName")),a($msgsContainer=l.panel.find(".msgsContainer").empty().addClass("loading"),i,n,l.tab.data("idName"))),s.find("#allGuestsTabContent .msgTitle").text("Text All Guests"),s.find("#allGuestsTabContent .newMsg textarea").attr("placeholder","Message...").val(""),s.find("#allGuestsTabContent .newMsg button[name=sendMsg]").data("idname",""),s.find("#allGuestsTabContent .allRecipients").empty().html("<h5>To:</h5>"),e.each(n.guestData,function(e,a){a.isMobile&&s.find("#allGuestsTabContent .allRecipients").append("<span title='"+a.Phone_Num+"'>"+a.Name_Full+"</span>")})},beforeActivate:function(e,t){t.newTab&&t.newTab.data("Name_Full")&&(t.newPanel.find(".msgTitle").text(t.newTab.data("Name_Full")+" - "+t.newTab.data("Phone_Num")),t.newPanel.find(".newMsg textarea").attr("placeholder","Message "+t.newTab.data("Phone_Num")+"...").val(""),t.newPanel.find(".newMsg button[name=sendMsg]").data("idname",t.newTab.data("idName")),a($msgsContainer=t.newPanel.find(".msgsContainer").empty().addClass("loading"),i,n,t.newTab.data("idName")))}}).find("li").removeClass("ui-corner-top").addClass("ui-corner-left"),s.find("button").button(),s.removeClass("loading")}else 1==n.guestData.length&&1===l?(s.html(i.msgsTabMkup),s.find(".msgTitle").text(n.guestData[0].Name_Full+" - "+n.guestData[0].Phone_Num),s.find(".newMsg textarea").attr("placeholder","Message "+n.guestData[0].Phone_Num+"...").val(""),s.find(".newMsg button[name=sendMsg]").data("idname",n.guestData[0].idName),a($msgsContainer=s.find(".msgsContainer").empty().addClass("loading"),i,n,n.guestData[0].idName),s.removeClass("loading")):(0==n.guestData.length||0===l)&&(s.html("<div class='ui-state-error ui-corner-all p-2'>No guests have opted in to receive text messages.</div>"),s.removeClass("loading"))}})}else n.campaign?e.ajax({method:"get",url:n.serviceURL,data:{cmd:"getCampaignMsgsDialog",status:n.campaign},dataType:"json",success:function(a){if(n.guestData=a,n.guestData.title&&s.dialog("option","title","Text "+n.guestData.title),n.guestData.contacts.length>0){s.html(i.campaignTabMkup),s.find(".msgTitle").text("Text "+n.guestData.title),s.find(".newMsg textarea").attr("placeholder","Message...").val("");var t="";e.each(n.guestData.contacts,function(e,a){t+=a.Name_First+" "+a.Name_Last+" - "+a.Phone_Num+"<br>"}),s.find(".allRecipients").empty().html("<h5>To:</h5><span class='hhk-tooltip' id='collapsedrecipients' title=''>"+n.guestData.title+" ("+n.guestData.contacts.length+")</span>"),s.find("#collapsedrecipients").tooltip({content:t}),s.removeClass("loading")}else 0==n.guestData.contacts.length&&(s.html("<div class='ui-state-error ui-corner-all p-2'>No guests have opted in to recieve text messages.</div>"),s.removeClass("loading"))}}):(s.html("<div class='ui-state-error ui-corner-all p-2'>No guests found.</div>"),s.removeClass("loading"))})(n,l,d)}),n.on("input",".newMsg textarea",function(){this.style.height="auto",this.style.height=this.scrollHeight+3+"px"}),n.on("click","#msgsTabContent .sendMsg",function(){var t=e(this);t.attr("disabled",!0).html("&nbsp;").addClass("loading");var s=e(this).data("idname"),i=e(this).parent(".newMsg").find("textarea").attr("disabled",!0),n=e(this).parents("#msgsTabContent").find(".msgsContainer"),o=i.val();e.ajax({method:"post",url:d.serviceURL,data:{cmd:"sendMsg",idName:s,msgText:o},dataType:"json",success:function(e){if(e.error){e.gotopage&&window.open(e.gotopage,"_self"),flagAlertMessage(e.error,"error"),t.attr("disabled",!1).html(l.sendIcon).removeClass("loading"),i.attr("disabled",!1);return}e.id&&(flagAlertMessage("Message sent successfully","success"),a(n,l,d,s),t.attr("disabled",!1).html(l.sendIcon).removeClass("loading"),i.val("").attr("disabled",!1))}})}),n.on("click","#allGuestsTabContent .sendMsg",function(){var a=e(this);a.attr("disabled",!0).html("&nbsp;").addClass("loading");var t=e(this).parent(".newMsg").find("textarea").attr("disabled",!0),s=t.val();if(d.visitId>0)var i={cmd:"sendVisitMsg",idVisit:d.visitId,idSpan:d.spanId,msgText:s};else if(d.resvId>0)var i={cmd:"sendResvMsg",idResv:d.resvId,msgText:s};else var i={};e.ajax({method:"post",url:d.serviceURL,data:i,dataType:"json",success:function(e){e.error&&(e.gotopage&&window.open(e.gotopage,"_self"),flagAlertMessage(e.error,"error")),e.success&&flagAlertMessage(e.success,"success"),a.attr("disabled",!1).html(l.sendIcon).removeClass("loading"),t.val("").attr("disabled",!1)}})}),n.on("click","#campaignTabContent .sendCampaign",function(){var a=e(this);a.attr("disabled",!0).html("&nbsp;").addClass("loading");var t=e(this).parent(".newMsg").find("textarea").attr("disabled",!0),s=t.val();e.ajax({method:"post",url:d.serviceURL,data:{cmd:"sendCampaign",status:d.guestData.status,msgText:s},dataType:"json",success:function(e){if(e.error){e.gotopage&&window.open(e.gotopage,"_self"),flagAlertMessage(e.error,"error"),a.attr("disabled",!1).html(l.sendIcon).removeClass("loading");return}e.success&&(flagAlertMessage(e.success,"success"),a.attr("disabled",!1).html(l.sendIcon).removeClass("loading"),t.val("").attr("disabled",!1),n.dialog("close"))}})}),this};function a(a,t,s,i){a.addClass("loading"),e.ajax({method:"get",url:s.serviceURL,data:{cmd:"loadMsgs",idName:i},dataType:"json",success:function(s){if(s.error){s.gotopage&&window.open(s.gotopage,"_self"),flagAlertMessage(s.error,"error");return}s.msgs&&($msgsTab=a.parent(),!1===s.subscriptionStatus?($msgsTab.find(".msgTitle").append("<span class='smsUnsubscribed ui-corner-all p-1 ui-state-error' title='The user has unsubscribed and will not receive any messages.'>Unsubscribed</span>"),$msgsTab.find(".newMsg").addClass("d-none")):$msgsTab.find(".newMsg").removeClass("d-none"),a.empty(),e.each(s.msgs,function(i,n){let l=e(t.msgMkup),d=moment(n.timestamp);l.find(".msgContent").text(n.text),"MT"==n.directionType?l.addClass("houseMsg").find(".msgMeta").text(s.siteName+" - "+d.calendar()):"MO"==n.directionType&&l.addClass("guestMsg").find(".msgMeta").text(s.Name_First+" - "+d.calendar()),a.append(l)}),a.removeClass("loading").scrollTop(a.prop("scrollHeight")))}})}}(jQuery);