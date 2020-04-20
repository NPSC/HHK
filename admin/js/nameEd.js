// Init j-query.
$(document).ready(function () {
    "use strict";

    var memData = $.parseJSON('<?php echo $memDataJSON; ?>');
    var userData = $.parseJSON('<?php echo $usrDataJSON; ?>');
    var listJSON = 'ws_gen.php?cmd=chglog&uid=' + memData.id;
    var donName;
    var savePressed = false;
    var forceNamePrefix = '<?php echo isset($uS->ForceNamePrefix) ? $uS->ForceNamePrefix : "false"; ?>';

    $.widget( "ui.autocomplete", $.ui.autocomplete, {
        _resizeMenu: function() {
            var ul = this.menu.element;
            ul.outerWidth( Math.max(
                    ul.width( "" ).outerWidth() + 1,
                    this.element.outerWidth()
            ) * 1.1 );
        }
    });

    // phone - email tabs block
    $('#phEmlTabs').tabs();
    $('#demographicTabs').tabs();
    $('#addrsTabs').tabs();
    var tabs, tbs;
    var listEvtTable;
    tabs = $("#divFuncTabs").tabs({
        collapsible: true,
        beforeActivate: function (event, ui) {
            if (ui.newPanel.length > 0) {
                if (ui.newTab.prop('id') === 'changelog' && !listEvtTable) {
                    listEvtTable = $('#dataTbl').dataTable({
                    "columnDefs": dtCols,
                    "serverSide": true,
                    "processing": true,
                    "deferRender": true,
                    "language": {"sSearch": "Search Log Text:"},
                    "sorting": [[0,'desc']],
                    "displayLength": 25,
                    "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                    "Dom": '<"top"ilf>rt<"bottom"ip>',
                    ajax: {
                        url: listJSON
                    }
                    });
                }
                // Donation dialog setup
                if (ui.newTab.prop('id') === 'donblank' && userData.donFlag) {
                    donName = memData.memName;
                    if (memData.memDesig == 'i' && memData.coId > 0) {
                        donName = donName + '  (' + memData.coName + ')';
                    }
                    // create the markup for the new donations list.
                    getDonationMarkup(memData.id);
                    // Write the selected campaign amount range info into the donor tab.
                    $('#dselCamp').change();
                    $('#vdon').dialog("option", "title", 'Record Donations for ' + donName);
                    $('#vdon').dialog('open');
                    event.preventDefault();
                }

                if (ui.newTab.prop('id') === 'wbuser') {
                    $('#vwebUser').dialog("option", "title", 'Web Access Info for ' + memData.memName);
                    $('#vwebUser').dialog('open');
                    event.preventDefault();
                }
            }
        }
    });
    tbs = tabs.find( ".ui-tabs-nav" ).children('li').length;
    // enable tabs for a "new" member
    if (memData.id == '0') {
        if (userData.donFlag) {
            tabs.tabs("option", "disabled", [ tbs - 4, tbs - 3, tbs - 2, tbs - 1]);
        } else {
            tabs.tabs("option", "disabled", [ tbs - 3, tbs - 2, tbs - 1]);
        }
        $('#phEmlTabs').tabs("option", "active", 1);
        $('#phEmlTabs').tabs("option", "disabled", [0]);
    } else {
        // Existing member
        var tbIndex = parseInt($('#addrsTabs').children('ul').data('actidx'), 10);
        if (isNaN(tbIndex)) {tbIndex = 0}
        $('#addrsTabs').tabs("option", "active", tbIndex);
    }

    // relationship dialog
    $("#submit").dialog({
        autoOpen: false,
        resizable: true,
        width: 400,
        height: 500,
        modal: true,
        buttons: {
            "Exit": function () {
                $(this).dialog("close");
            }
        }
    });
    // Relationship events
    $('div.hhk-relations').each(function () {
        var schLinkCode = $(this).attr('name');
        $(this).on('click', 'td.hhk-deletelink', function () {
            if (memData.id > 0) {
                if (confirm($(this).attr('title') + '?')) {
                    manageRelation(memData.id, $(this).attr('name'), schLinkCode, 'delRel');
                }
            }
        });
        $(this).on('click', 'td.hhk-careoflink', function () {
            if (memData.id > 0) {
                if (confirm($(this).attr('title') + '?')) {
                    var flag = $(this).find('span').attr('name');
                    manageRelation(memData.id, $(this).attr('name'), schLinkCode, flag);
                }
            }
        });
        $(this).on('click', 'td.hhk-newlink', function () {
            if (memData.id > 0) {
                var title = $(this).attr('title');
                $('#hdnRelCode').val(schLinkCode);
                $('input#txtRelSch').val('');
                $('#submit').dialog("option", "title", title);
                $('#submit').dialog('open');
            }
        });
    });
    $('#divListDonation').on('click', 'input.hhk-undonate', function () {
        var parts = $(this).attr('id').split('_');
        if (parts.length > 1) {
            var did = parseInt(parts[1]);
            if (!isNaN(did)) {
                if (confirm('Delete this Donation?')) {
                    $.post("donate.php",
                        {
                            did: did,
                            sq: $("#squirm").val(),
                            cmd: 'delete'
                        },
                        function (data) {
                            donateDeleteMarkup(data, memData.id);
                        }
                    );
                } else {
                    $(this).prop('checked', false);
                }
            }
        }
    });
    $('#btnSubmit, #btnReset, #btnCopy, #chgPW').button();
    var lastXhr;
    createZipAutoComplete($('input.hhk-zipsearch'), 'ws_gen.php', lastXhr);
    
    $('#dselCamp').change(function () {
        getCampaign($(this).val());
    });
    
    $('#chgPW').click(function () {
        $('#achgPw').dialog("option", "title", "Change Password for " + memData.memName);
        $('#txtOldPw').val('');
        $('#txtNewPw1').val('');
        $('#txtNewPw2').val('');

        $('#achgPw').dialog('open');
        $('#pwChangeErrMsg').val('');
        $('#txtOldPw').focus();
    });
    
    $('#achgPw').dialog({
        autoOpen: false,
        width: 550,
        resizable: true,
        modal: true,
        buttons: {
            "Save": function () {
                var tips = $('#apwChangeErrMsg'),
                        oldpw = $('#txtOldPw'), 
                        pw1 = $('#txtNewPw1'),
                        pw2 = $('#txtNewPw2'),
                        oldpwMD5, 
                        newpwMD5,
                		resetNext = $('#resetNext').prop('checked');
                
                oldpw.removeClass("ui-state-error");
                pw1.removeClass("ui-state-error").css('background-color', 'white');
                updateTips(tips, '');

                if (oldpw.val() == "") {
                    oldpw.addClass("ui-state-error").focus();
                    updateTips(tips, 'Enter your admin password');
                    return;
                }

                if (checkStrength(pw1)) {

                    if (pw1.val() !== pw2.val()) {
                        updateTips(tips, "New passwords do not match");
                        return;
                    }
                    
                } else {
                    updateTips(tips, "Password must have 8 or more characters including at least one uppercase and one lower case letter, one number and one symbol.");
                    return;
                }

                // make MD5 hash of password and concatenate challenge value
                // next calculate MD5 hash of combined values
                oldpwMD5 = hex_md5(hex_md5(oldpw.val()) + '<?php echo $challengeVar; ?>');
                newpwMD5 = hex_md5(pw1.val());

                oldpw.val('');
                pw1.val('');
                pw2.val('');
                pw1.removeClass("ui-state-error").css('background-color', 'white');
                
                $.post("ws_gen.php",
                    {
                        cmd: 'adchgpw',
                        adpw: oldpwMD5,
                        uid: memData.id,
                        uname: memData.webUserName,
                        newer: newpwMD5,
                        resetNext: resetNext
                    },
                    function (data) {
                        if (data) {
                            try {
                                data = $.parseJSON(data);
                            } catch (err) {
                                alert("Parser error - " + err.message);
                                return;
                            }
                            if (data.error) {
                                if (data.gotopage) {
                                    window.open(data.gotopage, '_self');
                                }
                                updateTips(tips, data.error);

                            } else if (data.success) {
                                
                                updateTips(tips, data.success);
                            }
                        }
                    }
                );

            },
            "Cancel": function () {
                $(this).dialog("close");
            }
        },
        close: function () {
            $('body').css('cursor', "auto");
        }
    });
    
    // Donation panel dialog box.
    $("#vdon").dialog({
        autoOpen: false,
        height: 480,
        width: 900,
        resizable: true,
        modal: true,
        buttons: {
            "Record": function () {
                var amt = parseFloat($('#damount').val());
                if (isNaN(amt) || amt <= 0) {
                    return;
                }
                // collect the donation data
                var parms = {};
                $('.hhk-ajx-dondata').each(function() {
                    parms[$(this).attr("id")] = $(this).val();
                });
                $.post(
                    "donate.php",
                    {
                        cmd: "insert",
                        id: memData.id,
                        sq: $('#squirm').val(),
                        qd: parms
                    },
                    function (data) {
                        donateResponse(data, memData.id);
                    });
            },
            "Print": function () {
                $("#divListDonation").printArea();
            },
            "Exit": function () {
                $(this).dialog("close");
            }
        },
        close: function () {
            $('#damount').val('');
            $('#donateResponseContainer').attr("style", "display:none;");
        }
    });
    
    $('#vwebUser').dialog({
        autoOpen: false,
        height: 450,
        width: 'auto', // 732
        resizable: true,
        modal: true,
        buttons: {
            "Save": function (event) {
                var parms = {},
                    tipmsg = $('#hhk-wuprompt');

                $("#webContainer").hide();

                $('.grpSec').each(function (index) {
                    if ($(this).prop("checked")) {
                        parms[$(this).attr("id")] = "checked";
                    } else {
                        parms[$(this).attr("id")] = "unchecked";
                    }
                });
                if ($('#txtwUserName').length > 0) {
                    if (!checkLength($('#txtwUserName'), 'User Name', 6, 35, tipmsg)) {
                        return;
                    }
                    if (!checkStrength($('#txtwUserPW'))) {
                        updateTips(tipmsg, 'Password must have 8 or more characters including at least one uppercase and one lower case letter, one number and one symbol.');
                        return;
                    }
                    parms['wuname'] = $('#txtwUserName').val();
                    parms['wupw'] = hex_md5($('#txtwUserPW').val());
                    parms['grpSec_v'] = 'checked';  // check the volunteer auth code.
                    
                }
                
                parms['role'] = $("#selwRole").val();
                parms['defaultPage'] = $('#txtwDefaultPage').val();
                parms['uid'] = memData.id;
                parms['status'] = $('#selwStatus').val();
                parms['admin'] = userData.userName;
                parms['vaddr'] = $('#selwVerify').val();
                parms['resetNext'] = $('#resetNew').prop('checked');

                //$('div.ui-dialog-buttonset').css("display", "none");
                $.post("ws_gen.php", {cmd: 'save', parms : parms}, function (rdata) {
                    var mess = '', data = {};
                    try {
                        data = $.parseJSON(rdata);
                    } catch (err) {
                        data.error = err.message;
                    }

                    if (data.error) {

                        if (data.gotopage) {
                            window.open(data.gotopage, '_self');
                        }
                        
                        mess = "Alert: " + data.error;
                        $('#webResponse').removeClass("ui-state-error").addClass("ui-state-highlight");
                        $('#webIcon').removeClass("ui-icon-alert").addClass("ui-icon-info");

                    } else if (data.warning) {
                        
                        $('webResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
                        $('#webIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
                        mess = "Warning: " + data.warning;
                        
                    } else if (data.success) {
                        
                        mess = "Success: " + data.success;
                        $('webResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
                        $('#webIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");

                    }

                    if (mess !== '') {
                        $('#webMessage').text(mess);
                        $("#webContainer").show("pulsate");
                    }

                });
            },
            "Exit": function () {
                $(this).dialog("close");
            }
        },
        close: function () {
            $('body').css('cursor', "auto");
        }
    });
    $('.ckdate').datepicker({
        yearRange: '-03:+05',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        dateFormat: 'M d, yy'
    });
    $('.ckbdate').datepicker({
        yearRange: '-99:+01',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        dateFormat: 'M d, yy'
    });
    $('#goWebSite').click(function () {
        var site = $('#txtWebSite').val();
        if (site != "") {
            var parts = site.split(':');

            if (parts.length < 2) {
                site = 'http://' + site;
            }
            window.open(site);
            return false;
        }
    });
    
    // Main form submit button.  Disable page during POST
    $('#btnSubmit').click(function () {
        if ($(this).val() == 'Saving>>>>') {
            return false;
        }
        
        if (forceNamePrefix && $('#selPrefix').val() === '') {
            $('#selPrefix').addClass('ui-state-error');
            flagAlertMessage("Enter a Prefix for the name.", true);
            return false;
        }
        
        savePressed = true;
        $(this).val('Saving>>>>');

    });
    // Notes search button click handler
    $('#btnNoteSch').click(function () {
        var stxt = $("#txtSearchNotes").val();
        $('#schNotes textarea').each(function () {

            if ($(this).val().toUpperCase().indexOf(stxt.toUpperCase()) > -1) {
                $(this).css("background-color", "yellow");
            } else {
                $(this).css("background-color", "white");
            }
        });
    });
//    // Don't let user choose a blank address as preferred.'
    addrPrefs(memData);
    verifyAddrs('div#divaddrTabs');

    createAutoComplete($('#txtsearch'), 3, {cmd: "srrel", basis: ($('#rbmemEmail').prop("checked") ? 'e' : 'm'), id: 0}, 
        function( item ) {
            if (item.id === 'i') {
                // New Individual
                window.location = "NameEdit.php?cmd=newind";
            } else if (item.id === 'o') {
                window.location = "NameEdit.php?cmd=neworg";
            }

            var cid = parseInt(item.id, 10);
            if (isNumber(cid)) {
                window.location = "NameEdit.php?id=" + cid;
            }
        }, 
        false, 
        "liveNameSearch.php"
    );

    createAutoComplete($('#txtRelSch'), 3, {cmd: 'srrel', id: memData.id, nonly: '1'}, 
        function (item) {
            $('#submit').dialog('close');
            $.post('ws_gen.php', {'rId':item.id, 'id':memData.id, 'rc':$('#hdnRelCode').val(), 'cmd':'newRel'}, relationReturn);
        },
        false,
        "liveNameSearch.php",
        $('#hdnRelCode')
    );

    // Member search letter input box
    $('#txtsearch').keypress(function (event) {
        var mm = $(this).val();
        if (event.keyCode == '13') {
            if (mm == '' || !isNumber(parseInt(mm, 10))) {
                alert("Don't press the return key unless you enter an Id.");
                event.preventDefault();
            } else {
                window.location = "NameEdit.php?id=" + mm;
            }
        }
    });
    $('input.hhk-check-button').click(function () {
        if ($(this).prop('id') == 'exAll') {
            $('input.hhk-ex').prop('checked', true);
        } else {
            $('input.hhk-ex').prop('checked', false);
        }
    });
    if ($('#hdnidpsg').length > 0) {
        var lstXhr;
        createAutoComplete($('#txtAgentSch'), 3, {cmd: 'filter', add: 'phone', basis: 'ra'}, getAgent, lstXhr);
        if ($('#a_txtLastName').val() === '') {
            $('.hhk-agentInfo').hide();
        }
        createAutoComplete($('#txtDocSch'), 3, {cmd: 'filter', basis: 'doc'}, getDoc, lstXhr);
        if ($('#d_txtLastName').val() === '') {
            $('.hhk-docInfo').hide();
        }
    }
    changeMemberStatus($("#selStatus"), memData, savePressed);
    // Flag member status if not active
    $("#selStatus").change(function () {
        changeMemberStatus($(this), memData, savePressed);
    });
        // Date of death
    $('#cbdeceased').change(function () {
        if ($(this).prop('checked')) {
            $('#disp_deceased').show('blind');
        } else {
            $('#disp_deceased').hide('blind');
        }
    });

    $('#selLanguage').multiselect();
    

    $('#divFuncTabs').css('display', 'block');
    $('.hhk-showonload').css('display', 'block');
    //show details/hide details
    $(".toggle-docs-detail").toggle(function(){

        var theUl = $(this).text("Show details").parent().next("ul");
        theUl.find("li > div:first-child").removeClass("header-open")
        .nextAll().hide();

        //e.preventDefault();

        // adjust the size of the vol tab control
        var sumpx = 0;

        theUl.children().each( function () {
            sumpx = sumpx + $(this).height();
        });

        theUl.parent().height(sumpx + 40);

    },function(e){
        var theUl = $(this).text("Hide details").parent().next("ul");
        var details = theUl.find("li > div:first-child").addClass("header-open");
        details.next().show();
        //e.preventDefault();

        // adjust the size of the vol tab control
        var sumpx = 0;

        theUl.children().each( function () {
            sumpx = sumpx + $(this).height();
        });

        theUl.parent().height(sumpx + 40);

    });
    
    //Make list items collapsible
    $('div.option-header h3').on('click', function(e) {

        var details = $(this).parent().toggleClass('header-open');
        details.next().toggle();
        e.preventDefault();

        // adjust the size of the vol tab control
        // Open:  140, closed: 35
        var sumpx = 0;
        var theUl = details.closest('ul');
        theUl.children().each( function () {
            sumpx = sumpx + $(this).height();
        });

        theUl.parent().height(sumpx + 40);
    });

    // Hover states on the static widgets
    $( ".hhk-relations td, .hhk-gotoweb" ).hover(
            function() {
                    $( this ).addClass( "ui-state-hover" );
            },
            function() {
                    $( this ).removeClass( "ui-state-hover" );
            }
    );

    // Unsaved changes on form are caught here.
    // Set Dirrty initial value manually for bfh
    $(document).find("bfh-states").each(function(){
	$(this).data("dirrty-initial-value", $(this).data('state'));
    });
    
    $(document).find("bfh-country").each(function(){
	$(this).data("dirrty-initial-value", $(this).data('country'));
    });
    
    // init dirrty
    $("#form1").dirrty();

});

