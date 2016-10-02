/**
 * guestload.js
 *
 *  This file is streamed in with the page itself.
 *
 * @category  House
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * 
 * @param {string} t
 * @returns {undefined}
 */
function updateTips(t) {
    "use strict";
    tips.text(t).addClass("ui-state-highlight");
//    setTimeout(function() {
//        tips.removeClass( "ui-state-highlight", 360000 );
//    }, 500 );
}

function errorOnZero(o, n) {
    "use strict";
    if (o.val() == "" || o.val() == "0" || o.val() == "00") {
        o.addClass("ui-state-error");
        updateTips(n + " cannot be zero");
        return false;
    } else {
        return true;
    }
}

function checkLength(o, n, min, max) {
    "use strict";
    if (o.val().length > max || o.val().length < min) {
        o.addClass("ui-state-error");
        if (o.val().length == 0) {
            updateTips("Fill in the " + n);
        }else if (min == max) {
            updateTips("The " + n + " must be " + max + " characters.");
        } else if (o.val().length > max) {
            updateTips("The " + n + " length is to long");
        } else {
            updateTips("The " + n + " length must be between " + min + " and " + max + ".");
        }
        return false;
    } else {
        return true;
    }
}

function isNumber(n) {
    "use strict";
    return !isNaN(parseFloat(n)) && isFinite(n);
}
var dtCols = [
{
    "aTargets": [ 0 ],
    "sTitle": "Date",
    "sType": "date",
    "mDataProp": function (source, type, val) {
        "use strict";
        if (type === 'set') {
            source.LogDate = val;
            return null;
        } else if (type === 'display') {
            if (source.Date_display === undefined) {
                var dt = new Date(Date.parse(source.LogDate));
                source.Date_display = (dt.getMonth() + 1) + '/' + dt.getDate() + '/' + dt.getFullYear() + ' ' + dt.getHours() + ':' + dt.getMinutes();
            }
            return source.Date_display;
        }
        return source.LogDate;
    }
},
{
    "aTargets": [ 1 ],
    "sTitle": "Type",
    "bSearchable": false,
    "bSortable": false,
    "mDataProp": "LogType"
},
{
    "aTargets": [ 2 ],
    "sTitle": "Sub-Type",
    "bSearchable": false,
    "bSortable": false,
    "mDataProp": "Subtype"
},
{
    "aTargets": [ 3 ],
    "sTitle": "User",
    "bSearchable": false,
    "bSortable": false,
    "mDataProp": "User"
},
{
    "aTargets": [ 4 ],
    "bVisible": false,
    "mDataProp": "idName"
},
{
    "aTargets": [ 5 ],
    "sTitle": "Log Text",
    "bSortable": false,
    "mDataProp": "LogText"
}

];
function updateVisitMessage(header, body, vPrefix) {
    //$('#visitMsg').toggle("clip");
    $('#' + vPrefix + 'h3VisitMsgHdr').text(header);
    $('#' + vPrefix + 'spnVisitMsg').text(body);
    $('#' + vPrefix + 'visitMsg').effect("pulsate");
}

function relationReturn(data) {

    data = $.parseJSON(data);
    if (data.error) {
        if (data.gotopage) {
            window.open(data.gotopage, '_self');
        }
        flagAlertMessage(data.error, true);
    } else if (data.success) {
        if (data.rc && data.markup) {
            var div = $('#acm' + data.rc);
            div.children().remove();
            var newDiv = $(data.markup);
            div.append(newDiv.children());
        }
        flagAlertMessage(data.success, false);
    }
}

function manageRelation(id, rId, relCode, cmd) {
    $.post('ws_admin.php', {'id':id, 'rId':rId, 'rc':relCode, 'cmd':cmd}, relationReturn);
}

/**
 * 
 * @param {string} btnid
 * @param {string} vorr
 * @param {int} idPayment
 * @param {float} amt
 * @returns {undefined}
 */
function sendVoidReturn(btnid, vorr, idPayment, amt) {
    
    var prms = {pid: idPayment, bid: btnid};
    
    if (vorr && vorr === 'v') {
        prms.cmd = 'void';
    } else if (vorr && vorr === 'rv') {
        prms.cmd = 'revpmt';
    } else if (vorr && vorr === 'r') {
        prms.cmd = 'rtn';
        prms.amt = amt;
    } else if (vorr && vorr === 'vr') {
        prms.cmd = 'voidret';
    }
    $.post('ws_ckin.php', prms, function (data) {
        var revMessage = '';
        if (data) {
            try {
                data = $.parseJSON(data);
            } catch (err) {
                alert("Parser error - " + err.message);
                return;
            }
            if (data.bid) {
                // clear button control
                $('#' + data.bid).remove();
            }
            if (data.error) {
                if (data.gotopage) {
                    window.location.assign(data.gotopage);
                }
                flagAlertMessage(data.error, true);
                return;
            }
            if (data.reversal && data.reversal !== '') {
                revMessage = data.reversal;
            }
            if (data.warning) {
                flagAlertMessage(revMessage + data.warning, true);
                return;
            }
            if (data.success) {
                 flagAlertMessage(revMessage + data.success, false);
            }
            if (data.receipt) {
                showReceipt('#pmtRcpt', data.receipt, 'Receipt');
            }
        }
    });
}

function cardOnFile(id, idGroup, idPsg) {
    var parms = {cmd: 'cof', idGuest: id, idGrp: idGroup, pbp: 'GuestEdit.php?id=' + id + '&psg=' + idPsg};
    $('#tblupCredit').find('input').each(function() {
        if (this.checked) {
            parms[$(this).attr('id')] = $(this).val();
        }
    });
    // Go to the server for payment data, then come back and submit to new URL to enter credit info.
    $.post('ws_ckin.php', parms,
    function(data) {
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
                flagAlertMessage(data.error, true);
                return;
            }
            if (data.hostedError) {
                flagAlertMessage(data.hostedError, true);
            }
            if (data.xfer) {
                var xferForm = $('#xform');
                xferForm.prop('action', data.xfer);
                $('#CardID').val(data.cardId);
                xferForm.submit();
            }
            if (data.success) {
                flagAlertMessage(data.success, false);
            }
        }
    });
}

// Init j-query.
$(document).ready(function () {
    "use strict";
    var savePressed = false;
    var nextVeh = 1;

    // Unsaved changes on form are caught here.
    $(window).bind('beforeunload', function () {
        // skip if the save button was pressed
        if (savePressed !== true) {
            var isDirty = false;
            $('#form1').find("input[type='text'],textarea").not(".ignrSave").each(function () {
                
                if (this.value != this.defaultValue && $(this).parents('div.ignrSave').length === 0) {
                    var nm = this.value;
                    isDirty = true;
                    return false;
                }
            });
            $('#form1').find("input[type='radio'],input[type='checkbox']").not(".ignrSave").each(function () {
                if ($(this).prop("checked") !== $(this).prop("defaultChecked") && $(this).parents('div.ignrSave').length === 0) {
                    var nm = $(this).prop("checked");
                    isDirty = true;
                    return false;
                }
            });
            $('#form1').find("select").not(".ignrSave").each(function () {
                if ($(this).data('bfhstates')) {
                    if ($(this).data('state') !== $(this).val()) {
                        isDirty = true;
                        return false;
                    }
                } else if ($(this).data('bfhcountries')) {
                    if ($(this).data('country') !== $(this).val()) {
                        isDirty = true;
                        return false;
                    }
                } else {
                    // gotta look at each option
                    $(this).children('option').each(function () {

                        if (this.defaultSelected !== this.selected) {
                            var nm = this.selected;
                            isDirty = true;
                            return false;
                        }
                    });
                }
            });
            if (isDirty === true) {
                return false;
            }
        }
    });
    $.ajaxSetup({
        beforeSend: function () {
            $('body').css('cursor', "wait");
        },
        complete: function () {
            $('body').css('cursor', "auto");
        },
        cache: false
    });
    $('#contentDiv').css('margin-top', $('#global-nav').css('height'));
    var listEvtTable;
    $("#divFuncTabs").tabs({
        collapsible: true,
    });
    // relationship dialog
    $("#submit").dialog({
        autoOpen: false,
        resizable: false,
        width: 300,
        modal: true,
        buttons: {
            "Exit": function () {
                $(this).dialog("close");
            }
        }
    });
    $('#keysfees').dialog({
        autoOpen: false,
        resizable: true,
        modal: true,
        close: function (event, ui) {$('div#submitButtons').show();},
        open: function (event, ui) {$('div#submitButtons').hide();}
    });
    $('#pmtRcpt').dialog({
        autoOpen: false,
        resizable: true,
        modal: true,
        title: 'Payment Receipt'
    });
    $("#faDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: 650,
        modal: true,
        title: 'Income Chooser'
    });
    if (rctMkup !== '') {
        showReceipt('#pmtRcpt', rctMkup);
    }
    $('.hhk-view-visit').click(function () {
        var vid = $(this).data('vid');
        var gid = $(this).data('gid');
        var span = $(this).data('span');

        var buttons = {
            "Show Statement": function() {
                window.open('ShowStatement.php?vid=' + vid, '_blank');
            },
            "Show Registration Form": function() {
                window.open('ShowRegForm.php?vid=' + vid, '_blank');
            },
            "Save": function() {
                saveFees(gid, vid, span, false, 'GuestEdit.php?id=' + gid + '&psg=' + memData.idPsg);
            },
            "Cancel": function() {
                $(this).dialog("close");
            }
        };
         viewVisit(gid, vid, buttons, 'Edit Visit #' + vid + '-' + span, '', span);
         $('#divAlert1').hide();
    });
    $('#resvAccordion').accordion({
        heightStyle: "content",
        collapsible: true,
        active: false,
        icons: false
    });
    // relationship events
    $('div.hhk-relations').each(function () {
        var schLinkCode = $(this).attr('name');
        $(this).on('click', 'td.hhk-deletelink', function () {
            if (memData.id > 0) {
                if (confirm($(this).attr('title') + '?')) {
                    manageRelation(memData.id, $(this).attr('name'), schLinkCode, 'delRel');
                }
            }
        });
        $(this).on('click', 'td.hhk-newlink', function () {
            if (memData.id > 0) {
                var title = $(this).attr('title');
                $('#hdnRelCode').val(schLinkCode);
                $('#submit').dialog("option", "title", title);
                $('#submit').dialog('open');
            }
        });
    });
    
    $('#cbNoVehicle').change(function () {
        if (this.checked) {
            $('#tblVehicle').hide();
        } else {
            $('#tblVehicle').show();
        }
    });
    $('#cbNoVehicle').change();
    
    $('#btnNextVeh, #exAll, #exNone').button();
    
    $('#btnNextVeh').click(function () {
        $('#trVeh' + nextVeh).show('fade');
        nextVeh++;
        if (nextVeh > 4) {
            $('#btnNextVeh').hide('fade');
        }
    });
    
    $("#schLogText").keyup( function () {
        /* Filter on the column (the index) of this element */
        if (this.value.length > 2 && listEvtTable)
            listEvtTable.fnFilter( this.value, 5 );
    });
    $('#divNametabs').tabs({
        beforeActivate: function (event, ui) {
            var tbl = $('#vvisitLog').find('table');
            if (ui.newTab.index() == 3 && tbl.length == 0) {
                $.post('ws_ckin.php', {cmd: 'gtvlog', idReg: memData.idReg}, function (data) {
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
                            flagAlertMessage(data.error, true);
                        } else if (data.vlog) {
                            $('#vvisitLog').append($(data.vlog));
                        }
                    }
                })
            }
        },
        collapsible: true
        });
    $('#btnSubmit, #btnReset, #btnCred').button();
    $('#btnCred').click(function () {
        cardOnFile($(this).data('id'), $(this).data('idreg'), memData.idPsg);
    });
    // phone - email tabs block
    $('#phEmlTabs').tabs();
    $('#emergTabs').tabs();
    $('#addrsTabs').tabs();
    $('#psgList').tabs({
        collapsible: true,
        beforeActivate: function (event, ui) {
            if (ui.newPanel.length > 0) {
                if (ui.newPanel.selector === '#vfin') {
                    getIncomeDiag(0, memData.idReg);
                    event.preventDefault();
                }
            }
        }
    });

    if (memData.psgOnly) {
        $('#psgList').tabs("disable");
    }

    $('#psgList').tabs("enable", psgTabIndex);
    $('#psgList').tabs("option", "active", psgTabIndex);

    $('#cbnoReturn').change(function () {
        if (this.checked) {
            $('#selnoReturn').show();
        } else {
            $('#selnoReturn').hide();
        }
    });
    $('#cbnoReturn').change();
    
    if (memData.id === 0) {
        // enable tabs for a "new" member
        $("#divFuncTabs").tabs("option", "disabled", [2,3,4]);
        $('#phEmlTabs').tabs("option", "active", 1);
        $('#phEmlTabs').tabs("option", "disabled", [0]);
    } else {
        // Existing member
        var tbIndex = parseInt($('#addrsTabs').children('ul').data('actidx'), 10);
        if (isNaN(tbIndex)) {tbIndex = 0}
        $('#addrsTabs').tabs("option", "active", tbIndex);
    }
    $.datepicker.setDefaults({
        yearRange: '-0:+02',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        numberOfMonths: 1,
        dateFormat: 'M d, yy'
    });
    $('.ckdate').datepicker({
        yearRange: '-02:+03'
    });
    $('.ckbdate').datepicker({
        yearRange: '-99:+00',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        maxDate:0,
        dateFormat: 'M d, yy'
    });
    $('#cbLastConfirmed').change(function () {
        if ($(this).prop('checked')) {
            $('#txtLastConfirmed').datepicker('setDate', '+0');
        } else {
            // restore date textbox
            $('#txtLastConfirmed').val($('#txtLastConfirmed').prop('defaultValue'));
        }
    });
    $('#txtLastConfirmed').change(function () {
        if ($('#txtLastConfirmed').val() == $('#txtLastConfirmed').prop('defaultValue')) {
            $('#cbLastConfirmed').prop('checked', false);
        } else {
            $('#cbLastConfirmed').prop('checked', true);
        }
    });
    verifyAddrs('div#nameTab, div#hospitalSection');
    addrPrefs(memData);
    var zipXhr;
    createZipAutoComplete($('input.hhk-zipsearch'), 'ws_admin.php', zipXhr);
    // Main form submit button.  Disable page during POST
    $('#btnSubmit').click(function () {
        if ($(this).val() == 'Saving>>>>') {
            return false;
        }
        savePressed = true;
        $(this).val('Saving>>>>');
    });
    // Member search letter input box
    $('#txtsearch').keypress(function (event) {
        var mm = $(this).val();
        if (event.keyCode == '13') {
            if (mm == '' || !isNumber(parseInt(mm, 10))) {
                alert("Don't press the return key unless you enter an Id.");
                event.preventDefault();
            } else {
                window.location.assign("GuestEdit.php?id=" + mm);
            }
        }
    });
    // Date of death
    $('#cbdeceased').change(function () {
        if ($(this).prop('checked')) {
            $('#disp_deceased').show();
        } else {
            $('#disp_deceased').hide();
        }
    });

    $('select.hhk-multisel').each( function () {
        $(this).multiselect({
            selectedList: 3
        });
    });
    
    var lstXhr;
    createAutoComplete($('#txtAgentSch'), 3, {cmd: 'filter', add: 'phone', basis: 'ra'}, getAgent, lstXhr);
    if ($('#a_txtLastName').val() === '') {
        $('.hhk-agentInfo').hide();
    }
    createAutoComplete($('#txtDocSch'), 3, {cmd: 'filter', basis: 'doc'}, getDoc, lstXhr);
    if ($('#d_txtLastName').val() === '') {
        $('.hhk-docInfo').hide();
    }
    var lastXhr;
    var oldData;
    $('#txtsearch').autocomplete({
        source: function (request, response) {
            if (isNumber(parseInt(request.term, 10))) {
                response();
                return;
            }
            if (request.term.search(' ') == (request.term.length - 1) || request.term.search(',') == (request.term.length - 1)) {
                response(oldData);
            } else {
                var inpt = {
                    cmd: "role",
                    mode: 'mo',
                    letters: request.term
                };

                lastXhr = $.getJSON("roleSearch.php", inpt,
                    function(data, status, xhr) {
                        if (xhr === lastXhr) {
                            if (data.error) {
                                if (data.gotopage) {
                                    window.open(data.gotopage, '_self');
                                }
                                data.value = data.error;
                            }
                            response(data);
                            oldData = data;
                        }
                    }
                );
            }
        },
        minLength: 3,
        select: function( event, ui ) {
            if (!ui.item) {
                return;
            }
            var cid = ui.item.id;
            if (cid !== 0) {
                window.location.assign("GuestEdit.php?id=" + cid);
            }
        }
    });
    $('#txtPhsearch').autocomplete({
        source: function (request, response) {
            var inpt = {
                cmd: "role",
                mode: 'mo',
                letters: request.term
            };

            lastXhr = $.getJSON("roleSearch.php", inpt,
                function(data, status, xhr) {
                    if (xhr === lastXhr) {
                        if (data.error) {
                            if (data.gotopage) {
                                window.open(data.gotopage, '_self');
                            }
                            data.value = data.error;
                        }
                        response(data);
                    }
                }
            );
        },
        minLength: 5,
        select: function( event, ui ) {
            if (!ui.item) {
                return;
            }
            var cid = ui.item.id;
            if (cid !== 0) {
                window.location.assign("GuestEdit.php?id=" + cid);
            }
        }
    });
    $('#txtRelSch').autocomplete({
        source: function (request, response) {
            // get more data
            if (request.term.search(' ') == (request.term.length - 1) || request.term.search(',') == (request.term.length - 1)) {
                response(oldData);
            } else {
            var inpt = {
                cmd: "srrel",
                letters: request.term,
                basis: $('#hdnRelCode').val(),
                id: memData.id,
                nonly: '1'
            };
            lastXhr = $.getJSON("roleSearch.php", inpt,
                function(data, status, xhr) {
                    if (xhr === lastXhr) {
                        if (data.error) {
                            if (data.gotopage) {
                                window.open(data.gotopage, '_self');
                            }
                            data.value = data.error;
                        }
                        response(data);
                        oldData = data;
                    } else {
                        response();
                    }
            });
        }
        },
        minLength: 3,
        select: function( event, ui ) {
            if (!ui.item) {
                return;
            }
            $('#submit').dialog('close');

            var cid = parseInt(ui.item.id, 10);
            if (isNumber(cid)) {
                $.post('ws_admin.php', {'rId':cid, 'id':memData.id, 'rc':$('#hdnRelCode').val(), 'cmd':'newRel'}, relationReturn);
            }
        }
    });
    // Excludes tab "Check-all" button
    $('input.hhk-check-button').click(function () {
        if ($(this).prop('id') == 'exAll') {
            $('input.hhk-ex').prop('checked', true);
        } else {
            $('input.hhk-ex').prop('checked', false);
        }
    });
    // Hide the member status and basis controls
    $(".hhk-hideStatus, .hhk-hideBasis").hide();
    $('#divFuncTabs').show();
    $('.hhk-showonload').show();
    $('#txtsearch').focus();
});
