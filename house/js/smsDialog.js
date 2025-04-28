(function ($) {

    $.fn.smsDialog = function (options) {

        var uiMkup = {
            smsTabs:
                `<div id="smsTabs" class="ui-tabs ui-corner-all, ui-widget ui-widget-content ui-tabs-vertical ui-helper-clearfix ui-corner-all">
                    <ul class="ui-tabs-nav ui-corner-all ui-helper-reset ui-helper-clearfix ui-widget-header"></ul>
            </div> `,
            msgsTabMkup:
                `<div id="msgsTabContent" class="hhk-overflow-x ui-tabs-panel ui-corner-all ui-widget-content">
                <h4 class="msgTitle"></h4>
                <div class="msgsContainer loading"></div>
                <div class="newMsg hhk-flex">
                    <textarea class="ui-widget-content ui-corner-all" maxlength="306" placeholder="Message..."></textarea>
                    <button class="ui-button ui-corner-all sendMsg" name="sendMsg" title="Send Message"><i class="bi bi-send-fill"></i></button>
                </div>
            </div>`,
            allGuestsTabMkup:
                `<div id="allGuestsTabContent" class="hhk-overflow-x ui-tabs-panel ui-corner-all ui-widget-content">
                <h4 class="msgTitle"></h4>
                <div class="allRecipients ui-widget-content ui-corner-all">
                    <h5>To</h5>
                </div>
                <div class="newMsg hhk-flex">
                    <textarea class="ui-widget-content ui-corner-all" maxlength="306" placeholder="Message..."></textarea>
                    <button class="ui-button ui-corner-all sendMsg" name="sendMsg" title="Send Message"><i class="bi bi-send-fill"></i></button>
                </div>
            </div>`,
            campaignTabMkup:
                `<div id="campaignTabContent" class="hhk-overflow-x ui-tabs-panel ui-corner-all ui-widget-content">
                <h4 class="msgTitle"></h4>
                <div class="allRecipients ui-widget-content ui-corner-all">
                    <h5>To</h5>
                </div>
                <div class="newMsg hhk-flex">
                    <textarea class="ui-widget-content ui-corner-all" maxlength="288" placeholder="Message..."></textarea>
                    <button class="ui-button ui-corner-all sendCampaign" name="sendCampaign" title="Send Message"><i class="bi bi-send-fill"></i></button>
                </div>
                <div class="infoMsg hhk-flex ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox my-2 align-items-center justify-content-between" style="display:none">
                    <div class="smsInfoMsg"></div>
                    <button class="ui-button ui-corner-all btnBatchWait" name="btnBatchWait">Keep wating</button>
                </div>
            </div>`,
            msgMkup:
                `<div class="msgContainer hhk-flex"> 
                    <div class="msg">
                        <div class="msgContent ui-widget-content ui-corner-all"></div>
                        <div class="msgMeta"></div>
                    </div>
                </div>`,
            sendIcon: `<i class="bi bi-send-fill"></i>`
        };

        var $dialog = $(document).find("#viewMsgsDialog");
        if ($dialog.length == 0) {
            $dialog = $('<div id="viewMsgsDialog" class="loading">');
        }
        
        var defaults = {
            guestId: 0,
            psgId: 0,
            resvId: 0,
            visitId: 0,
            spanId: 0,
            campaign: false,
            autoOpen:false,
            dialogTitle: "Text Guests",
            serviceURL: 'ws_resv.php',
            guestData: []
            
        };

        var settings = $.extend(true, {}, defaults, options);

        var $btn = $(this);

		reinitialize($dialog, settings);

        actions($dialog, uiMkup, settings, $btn);

        return this;
    };

    function actions($dialog, uiMkup, settings, $btn) {
        $btn.on('click', function (e) {
            e.preventDefault();
            createDialog($dialog, uiMkup, settings);
            $dialog.dialog('open');
        });

        $dialog.on("dialogopen", function (e, ui) {
            loadDialog($dialog, uiMkup, settings);
        });

        $dialog.on("input", ".newMsg textarea", function () {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight + 3) + 'px';
        });

        $dialog.on("click", "#msgsTabContent .sendMsg", function () {
            
            var sendMsgBtn = $(this);
            sendMsgBtn.attr('disabled', true).html('&nbsp;').addClass("loading");
            
            var idName = $(this).data('idname');
            var msgMkup = $(this).parent('.newMsg').find("textarea").attr("disabled", true);
            var $msgsContainer = $(this).parents("#msgsTabContent").find(".msgsContainer");
            var msgText = msgMkup.val();
                
            $.ajax({
                method: "post",
                url: settings.serviceURL,
                data: {
                    cmd: "sendMsg",
                    idName: idName,
                    msgText: msgText
                },
                dataType: "json",
                success: function (data) {
                    if (data.error) {
                        if (data.gotopage) {
                            window.open(data.gotopage, '_self');
                        }
                        flagAlertMessage(data.error, 'error');
                        sendMsgBtn.attr('disabled', false).html(uiMkup.sendIcon).removeClass("loading");
                        msgMkup.attr('disabled', false);
                        return;
            
                    } else if (data.id) {
                        flagAlertMessage("Message sent successfully", 'success');
                        loadMsgs($msgsContainer, uiMkup, settings, idName);
                        sendMsgBtn.attr('disabled', false).html(uiMkup.sendIcon).removeClass("loading");
                        msgMkup.val("").attr("disabled", false);
                    }
                }
            });
            
        });


        $dialog.on("click", "#allGuestsTabContent .sendMsg", function () {
            
            var sendMsgBtn = $(this);
            sendMsgBtn.attr('disabled', true).html('&nbsp;').addClass("loading");
            
            var msgMkup = $(this).parent('.newMsg').find("textarea").attr("disabled", true);
            var msgText = msgMkup.val();
            
            if (settings.visitId > 0) {
                var data = {
                    cmd: "sendVisitMsg",
                    idVisit: settings.visitId,
                    idSpan: settings.spanId,
                    msgText: msgText
                }
            } else if (settings.resvId > 0) {
                var data = {
                    cmd: "sendResvMsg",
                    idResv: settings.resvId,
                    msgText: msgText
                }
            } else {
                var data = {};
            }

            $.ajax({
                method: "post",
                url: settings.serviceURL,
                data: data,
                dataType: "json",
                success: function (data) {
                    if (data.error) {
                        if (data.gotopage) {
                            window.open(data.gotopage, '_self');
                        }
                        flagAlertMessage(data.error, 'error');
                    }
                    
                    if (data.success) {
                        flagAlertMessage(data.success, "success");
                    }
                    sendMsgBtn.attr('disabled', false).html(uiMkup.sendIcon).removeClass("loading");
                    msgMkup.val("").attr('disabled', false);
                }
            });
            
        });

        $dialog.on("click", "#campaignTabContent .sendCampaign", function () {
            var sendMsgBtn = $(this);
            var msgMkup = $(this).parent('.newMsg').find("textarea");
            var msgText = msgMkup.val();
            var infoMkup = $(this).parents('#campaignTabContent').find(".infoMsg");
            
            if (msgText.length > 0) {
                sendMsgBtn.attr('disabled', true).html('&nbsp;').addClass("loading");
                msgMkup.attr("disabled", true);

                $.ajax({
                    method: "post",
                    url: settings.serviceURL,
                    data: {
                        cmd: "sendCampaign",
                        status: settings.guestData.status,
                        msgText: msgText
                    },
                    dataType: "json",
                    success: function (data) {
                        if (data.error) {
                            if (data.gotopage) {
                                window.open(data.gotopage, '_self');
                            }
                            flagAlertMessage(data.error, 'error');
                            sendMsgBtn.attr('disabled', false).html(uiMkup.sendIcon).removeClass("loading");
                            return;
                
                        } else if (data.success) {
                            flagAlertMessage(data.success, "success");
                            sendMsgBtn.attr('disabled', false).html(uiMkup.sendIcon).removeClass("loading");
                            msgMkup.val("").attr('disabled', false);
                            $dialog.dialog("close");
                        } else if (data.info) {
                            infoMkup.find(".smsInfoMsg").text(data.info);
                            infoMkup.show();
                            delete data.info;
                            infoMkup.find(".btnBatchWait").data("batchInfo", data);
                            console.log(infoMkup.find(".btnBatchWait").data("batchInfo"));
                        }
                    }
                });
            } else {
                flagAlertMessage("SMS Message... field cannot be blank.");
            }
        });

        $dialog.on("click", "#campaignTabContent .btnBatchWait", function () {
            var sendMsgBtn = $(this);
            
            var msgMkup = $(this).parents('#campaignTabContent').find('.newMsg textarea').attr("disabled", true);
            var msgText = msgMkup.val();
            var infoMkup = $(this).parents('#campaignTabContent').find(".infoMsg");
            var batchInfo = infoMkup.find(".btnBatchWait").data("batchInfo");
            infoMkup.hide();
            $.ajax({
                method: "post",
                url: settings.serviceURL,
                data: {
                    cmd: "sendCampaign",
                    msgText: msgText,
                    ...batchInfo
                },
                dataType: "json",
                success: function (data) {
                    if (data.error) {
                        if (data.gotopage) {
                            window.open(data.gotopage, '_self');
                        }
                        flagAlertMessage(data.error, 'error');
                        sendMsgBtn.attr('disabled', false).html(uiMkup.sendIcon).removeClass("loading");
                        return;
            
                    } else if (data.success) {
                        flagAlertMessage(data.success, "success");
                        sendMsgBtn.attr('disabled', false).html(uiMkup.sendIcon).removeClass("loading");
                        msgMkup.val("").attr('disabled', false);
                        $dialog.dialog("close");
                    } else if (data.info) {
                        infoMkup.find(".smsInfoMsg").text(data.info);
                        infoMkup.show();
                    }
                }
            });
            
        });
    }

    function createDialog($dialog, uiMkup, settings) {

        $dialog.dialog({
            autoOpen: false,
            position: {
                my: "top",
                at: "top+10%"
            },
            width: getDialogWidth(600),
            resizable: true,
            modal: true,
            title: settings.dialogTitle,
            buttons: {
                "Close": function () {
                    $(this).dialog("close");
                }
            },
            close: function(event, ui) {
                $(this).empty().dialog('destroy');
            }
        });

        
    }

    function loadDialog($dialog, uiMkup, settings) {
        $dialog.addClass("loading");
        
        //if is visit, resv or single guest
        if (settings.visitId || settings.resvId || settings.guestId) {

            if (settings.visitId) {
                var data = {
                    cmd: 'getVisitMsgsDialog',
                    idVisit: settings.visitId,
                    idSpan: settings.spanId
                }
            } else if (settings.resvId) {
                var data = {
                    cmd: 'getResvMsgsDialog',
                    idResv: settings.resvId
                }
            } else if (settings.guestId) {
                var data = {
                    cmd: 'getGuestMsgsDialog',
                    idName: settings.guestId
                };
            }

            $.ajax({
                method: "get",
                url: settings.serviceURL,
                data: data,
                dataType: "json",
                success: function (data) {
                    settings.guestData = data;

                    if (data.error) {
                        if (data.gotopage) {
                            window.open(data.gotopage, '_self');
                        }
                        flagAlertMessage(data.error, 'error');
                        $dialog.dialog("close");
                        return;
                    }

                    //set dialog title
                    if (settings.guestData[0] && settings.guestData[0].Room) {
                        $dialog.dialog("option", "title", "Text Guests in Room " + settings.guestData[0].Room);
                    } else if (settings.guestData[0] && settings.guestData[0].dialogTitle) {
                        $dialog.dialog("option", "title", settings.guestData[0].dialogTitle);
                    }

                    var phoneCount = 0;
                    $.each(settings.guestData, function (i, guest) {
                        if (guest.isMobile == 1) {
                            phoneCount++;
                        }
                    });

                    if (settings.guestData.length > 1 && phoneCount > 0) {
                        $dialog.html(uiMkup.smsTabs);

                        $dialog.find("#smsTabs ul").append($(`<li><a href="#allGuestsTabContent">Current Guests</a></li>`));
                        
                        let disabledTabs = [];

                        $.each(settings.guestData, function (i, guest) {
                            li = $(`<li><a href="#msgsTabContent">` + guest.Name_Full + `</a></li>`).data(guest);
                            if (guest.isMobile == 0) {
                                disabledTabs.push(i + 1);
                            }
                            $dialog.find("#smsTabs ul").append(li);
                        });

                        $dialog.find("#smsTabs").append(uiMkup.msgsTabMkup + uiMkup.allGuestsTabMkup)
                            .tabs({
                                active: 0,
                                disabled: disabledTabs,
                                create: function (event, ui) {
                                    if (ui.tab && ui.tab.data("Name_Full")) {
                                        ui.panel.find(".msgTitle").text(ui.tab.data("Name_Full") + " - " + ui.tab.data("Phone_Num"));
                                        ui.panel.find(".newMsg textarea").attr("placeholder", "Message " + ui.tab.data("Phone_Num") + "...").val("");
                                        ui.panel.find(".newMsg button[name=sendMsg]").data("idname", ui.tab.data("idName"));
                                        $msgsContainer = ui.panel.find(".msgsContainer").empty().addClass("loading");
                                        loadMsgs($msgsContainer, uiMkup, settings, ui.tab.data('idName'));
                                    }

                                    //all guests tab
                                    $dialog.find("#allGuestsTabContent .msgTitle").text("Text All Guests");
                                    $dialog.find("#allGuestsTabContent .newMsg textarea").attr("placeholder", "Message...").val("");
                                    $dialog.find("#allGuestsTabContent .newMsg button[name=sendMsg]").data("idname", "");
                                    $dialog.find("#allGuestsTabContent .allRecipients").empty().html("<h5>To:</h5>")
                                    $.each(settings.guestData, function (i, guest) {
                                        if (guest.isMobile) {
                                            $dialog.find("#allGuestsTabContent .allRecipients").append("<span title='" + guest.Phone_Num + "'>" + guest.Name_Full + "</span>");
                                        }
                                    });
                                },
                                beforeActivate: function (event, ui) {
                                    if (ui.newTab && ui.newTab.data("Name_Full")) {
                                        ui.newPanel.find(".msgTitle").text(ui.newTab.data("Name_Full") + " - " + ui.newTab.data("Phone_Num"));
                                        ui.newPanel.find(".newMsg textarea").attr("placeholder", "Message " + ui.newTab.data("Phone_Num") + "...").val("");
                                        ui.newPanel.find(".newMsg button[name=sendMsg]").data("idname", ui.newTab.data("idName"));
                                        $msgsContainer = ui.newPanel.find(".msgsContainer").empty().addClass("loading");
                                        loadMsgs($msgsContainer, uiMkup, settings, ui.newTab.data('idName'));
                                    }
                                }
                            }).find("li").removeClass("ui-corner-top").addClass("ui-corner-left");

                        $dialog.find("button").button();

                        $dialog.removeClass("loading");
                    } else if (settings.guestData.length == 1 && phoneCount === 1) {
                        $dialog.html(uiMkup.msgsTabMkup);
                        $dialog.find(".msgTitle").text(settings.guestData[0].Name_Full + " - " + settings.guestData[0].Phone_Num);
                        $dialog.find(".newMsg textarea").attr("placeholder", "Message " + settings.guestData[0].Phone_Num + "...").val("");
                        $dialog.find(".newMsg button[name=sendMsg]").data("idname", settings.guestData[0].idName);
                        $msgsContainer = $dialog.find(".msgsContainer").empty().addClass("loading");
                        loadMsgs($msgsContainer, uiMkup, settings, settings.guestData[0].idName);
                        $dialog.removeClass("loading");
                    } else if (settings.guestData.length == 0 || phoneCount === 0) {
                        $dialog.html("<div class='ui-state-error ui-corner-all p-2'>No guests have opted in to receive text messages.</div>");
                        $dialog.removeClass("loading");
                    }
                    
                }
            });
        } else if (settings.campaign) {
            $.ajax({
                method: "get",
                url: settings.serviceURL,
                data: {
                    cmd: 'getCampaignMsgsDialog',
                    status: settings.campaign
                },
                dataType: "json",
                success: function (data) {
                    settings.guestData = data;

                    if (data.error) {
                        if (data.gotopage) {
                            window.open(data.gotopage, '_self');
                        }
                        flagAlertMessage(data.error, 'error');
                        $dialog.dialog("close");
                        return;
                    }

                    //set dialog title
                    if (settings.guestData.title) {
                        $dialog.dialog("option", "title", "Text " + settings.guestData.title);
                    }

                    if (settings.guestData.contacts.length > 0) {
                        $dialog.html(uiMkup.campaignTabMkup);

                        //all guests tab
                        $dialog.find(".msgTitle").text("Text " + settings.guestData.title);
                        $dialog.find(".newMsg textarea").attr("placeholder", "Message...").val("");
                        
                        var guestStr = ""
                        $.each(settings.guestData.contacts, function (i, contact) {
                            guestStr += contact.Name_First + " " + contact.Name_Last + " - " + contact.Phone_Num + "<br>";
                        });

                        $dialog.find(".allRecipients").empty().html("<h5>To:</h5><span class='hhk-tooltip' id='collapsedrecipients' title=''>" + settings.guestData.title + " (" + settings.guestData.contacts.length + ")</span>");
                        
                        $dialog.find("#collapsedrecipients").tooltip({
                            content: guestStr
                        });
                        
                        $dialog.removeClass("loading");
                    } else if (settings.guestData.contacts.length == 0) {
                        $dialog.html("<div class='ui-state-error ui-corner-all p-2'>No guests have opted in to recieve text messages.</div>");
                        $dialog.removeClass("loading");
                    }
                }
            });
        } else {
            $dialog.html("<div class='ui-state-error ui-corner-all p-2'>No guests found.</div>");
            $dialog.removeClass("loading");
        }
    }

    function loadMsgs($msgsContainer, uiMkup, settings, idName) {
        $msgsContainer.addClass("loading");
        $.ajax({
            method: "get",
            url: settings.serviceURL,
            data: {
                cmd: 'loadMsgs',
                idName: idName
            },
            dataType: "json",
            success: function (data) {
                if (data.error) {
                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }
                    flagAlertMessage(data.error, 'error');
                    return;
        
                } else if (data.msgs) {
                    $msgsTab = $msgsContainer.parent();
                    if (data.subscriptionStatus === false) {
                        $msgsTab.find(".msgTitle").append("<span class='smsUnsubscribed ui-corner-all p-1 ui-state-error' title='The user has unsubscribed and will not receive any messages.'>Unsubscribed</span>");
                        $msgsTab.find(".newMsg").addClass("d-none");

                    } else {
                        $msgsTab.find(".newMsg").removeClass("d-none");
                    }

                    $msgsContainer.empty();
                    
                    $.each(data.msgs, function (i, msg) {
                        let $msgMkup = $(uiMkup.msgMkup);
                        let timestamp = moment(msg.timestamp);

                        $msgMkup.find(".msgContent").text(msg.text);

                        if (msg.directionType == "MT") {
                            $msgMkup.addClass("houseMsg").find(".msgMeta").text(data.siteName + " - " + timestamp.calendar());
                        } else if (msg.directionType == "MO") {
                            $msgMkup.addClass("guestMsg").find(".msgMeta").text(data.Name_First + " - " + timestamp.calendar());
                        }
                        $msgsContainer.append($msgMkup);
                    });
                    $msgsContainer.removeClass("loading").scrollTop($msgsContainer.prop('scrollHeight'));
                }
            }
        });
    }
    
    function reinitialize($dialog, settings){
		$dialog.off('click', '*');
    }

}(jQuery));