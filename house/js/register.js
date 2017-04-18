/* global pmtMkup, rvCols, wlCols, roomCnt, viewDays, rctMkup, defaultTab, isGuestAdmin */

/**
 * register.js
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * 
 * @param {mixed} n
 * @returns {Boolean}
 */
function isNumber(n) {
    "use strict";
    return !isNaN(parseFloat(n)) && isFinite(n);
}

function refreshdTables(data) {
    "use strict";
    var tbl;
    if (data.curres && $('#divcurres').length > 0) {
        tbl = $('#curres').DataTable();
        tbl.ajax.reload();
    }
    
    if (data.reservs && $('div#vresvs').length > 0) {
        tbl = $('#reservs').DataTable();
        tbl.ajax.reload();
    }
    
    if (data.waitlist && $('div#vwls').length > 0) {
        tbl = $('#waitlist').DataTable();
        tbl.ajax.reload();
    }
    
    if (data.unreserv && $('div#vuncon').length > 0) {
        tbl = $('#unreserv').DataTable();
        tbl.ajax.reload();
    }

}

/**
 * 
 * @param {int} rid
 * @param {string} status
 * @returns {undefined}
 */
function cgResvStatus(rid, status) {
    $.post('ws_ckin.php', {cmd: 'rvstat', rid: rid, stat: status},
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
                    window.location.assign(data.gotopage);
                }
                flagAlertMessage(data.error, true);
                return;
            }
            if (data.success) {
                flagAlertMessage(data.success, false);
                $('#calendar').hhkCalendar('refetchEvents');
            }
            refreshdTables(data);
        }
    });
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
    } else if (vorr && vorr === 'd') {
        prms.cmd = 'delWaive';
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
function invPay(id, pbp, dialg) {
    // cash payment
    if (verifyAmtTendrd() === false) {
        return;
    }
    var parms = {cmd: 'payInv', pbp: pbp, id: id};
    
    // Fees and Keys
    $('.hhk-feeskeys').each(function() {
        if ($(this).attr('type') === 'checkbox') {
            if (this.checked !== false) {
                parms[$(this).attr('id')] = 'on';
            }
        } else if ($(this).hasClass('ckdate')) {
            var tdate = $(this).datepicker('getDate');
            if (tdate) {
                parms[$(this).attr('id')] = tdate.toJSON();
            } else {
                 parms[$(this).attr('id')] = '';
            }
        } else if ($(this).attr('type') === 'radio') {
            if (this.checked !== false) {
                parms[$(this).attr('id')] = this.value;
            }
        } else{
            parms[$(this).attr('id')] = this.value;
        }
    });
    dialg.dialog("close");
    
    $.post('ws_ckin.php', parms,
        function(data) {
            try {
                data = $.parseJSON(data);
            } catch (err) {
                alert("Parser error - " + err.message);
                return;
            }
            
            if (data.error) {
                if (data.gotopage) {
                    window.location.assign(data.gotopage);
                }
                flagAlertMessage(data.error, true);
                
            }
            
            paymentReply(data, false);
            $('#btnInvGo').click();
    });
}

function invLoadPc(nme, id, iid) {
"use strict";    
    var buttons = {
        "Pay Fees": function() {
            invPay(id, 'register.php', $('div#keysfees'));
        },
        "Cancel": function() {
            $(this).dialog("close");
        }
    };
    
    $.post('ws_ckin.php',
        {
            cmd: 'showPayInv',
            id: id,
            iid: iid
        },
        
        function(data) {
        "use strict";
        if (data) {
            try {
                data = $.parseJSON(data);
            } catch (err) {
                alert("Parser error - " + err.message);
                return;
            }
            
            if (data.error) {
                if (data.gotopage) {
                    window.location.assign(data.gotopage);
                }
                flagAlertMessage(data.error, true);
                
            } else if (data.mkup) {
                
                $('div#keysfees').children().remove();
                $('div#keysfees').append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(data.mkup)));
                $('div#keysfees .ckdate').datepicker({
                    yearRange: '-01:+01',
                    changeMonth: true,
                    changeYear: true,
                    autoSize: true,
                    numberOfMonths: 1,
                    dateFormat: 'M d, yy'
                });
                
                isCheckedOut = false;
                setupPayments(data.resc, '', '', 0, $('#pmtRcpt'));
                
                $('#keysfees').dialog('option', 'buttons', buttons);
                $('#keysfees').dialog('option', 'title', 'Pay Invoice');
                $('#keysfees').dialog('option', 'width', 700);
                $('#keysfees').dialog('open');
            }
        }
    });
}

function invSetBill(inb, name, idDiag, idElement, billDate, notes, notesElement) {
    "use strict";
    var dialg =  $(idDiag);
    var buttons = {
        "Save": function() {

            var dt;
            var nt = dialg.find('#taBillNotes').val();
            
            if (dialg.find('#txtBillDate').val() != '') {
                dt = dialg.find('#txtBillDate').datepicker('getDate').toJSON();
            }

            $.post('ws_resc.php', {cmd: 'invSetBill', inb:inb, date:dt, ele: idElement, nts: nt, ntele: notesElement},
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
                            window.location.assign(data.gotopage);
                        }
                        
                        flagAlertMessage(data.error, true);
                        
                    } else if (data.success) {

                        if (data.elemt && data.strDate) {
                            $(data.elemt).text(data.strDate);
                            
                        }

                        if (data.notesElemt && data.notes) {
                            $(data.notesElemt).text(data.notes);
                            
                        }
                        
                        flagAlertMessage(data.success, false);
                    }
                }
            });

            $(this).dialog("close");

        },
        "Cancel": function() {
            $(this).dialog("close");
        }
    };

    dialg.find('#spnInvNumber').text(inb);
    dialg.find('#spnBillPayor').text(name);
    dialg.find('#txtBillDate').val(billDate);
    dialg.find('#taBillNotes').val(notes);
    dialg.find('#txtBillDate').datepicker({numberOfMonths: 1});

    dialg.dialog('option', 'buttons', buttons);
    dialg.dialog('option', 'width', 500);
    dialg.dialog('open');
}

function chgRoomCleanStatus(idRoom, statusCode) {
    "use strict";
    if (confirm('Change the room status?')) {

        $.post('ws_resc.php', {cmd: 'saveRmCleanCode', idr: idRoom, stat: statusCode},
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
                        window.location.assign(data.gotopage);
                    }
                    flagAlertMessage("Server error - " + data.error, true);
                    return;
                }
                
                refreshdTables(data);
                
                if (data.msg && data.msg != '') {
                    flagAlertMessage(data.msg, false);
                }
            }

        });
    }
}
function payFee(gname, id, idVisit, span) {
    var buttons = {
        "Show Statement": function() {
            window.open('ShowStatement.php?vid=' + idVisit, '_blank');
        },
        "Pay Fees": function() {
            saveFees(id, idVisit, span, false, 'register.php');
        },
        "Cancel": function() {
            $(this).dialog("close");
        }
    };
    viewVisit(id, idVisit, buttons, 'Pay Fees for ' + gname, 'pf', span);
}
function editPSG(psg) {
    var buttons = {
//        "Save PSG": function() {
//            saveFees(id, idVisit, span, false, 'register.php');
//        },
        "Cancel": function() {
            $(this).dialog("close");
        }
    };
    $.post('ws_ckin.php',
            {
                cmd: 'viewPSG',
                psg: psg
            },
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
                    window.location.assign(data.gotopage);
                }
                flagAlertMessage(data.error, true);
            } else if (data.markup) {
                var diag = $('div#keysfees');
                diag.children().remove();
                diag.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(data.markup)));
                diag.dialog('option', 'buttons', buttons);
                diag.dialog('option', 'title', 'View Patient Support Group');
                diag.dialog('option', 'width', 900);
                diag.dialog('open');
            }
        }
    });
}
function ckOut(gname, id, idVisit, span) {
    var buttons = {
        "Show Statement": function() {
            window.open('ShowStatement.php?vid=' + idVisit, '_blank');
        },
        "Show Registration Form": function() {
            window.open('ShowRegForm.php?vid=' + idVisit, '_blank');
        },
        "Check Out": function() {
            saveFees(id, idVisit, span, true, 'register.php');
        },
        "Cancel": function() {
            $(this).dialog("close");
        }
    };
    viewVisit(id, idVisit, buttons, 'Check Out ' + gname, 'co', span);
}
function editVisit(gname, id, idVisit, span) {
    var buttons = {
        "Show Statement": function() {
            window.open('ShowStatement.php?vid=' + idVisit, '_blank');
        },
        "Show Registration Form": function() {
            window.open('ShowRegForm.php?vid=' + idVisit, '_blank');
        },
        "Save": function() {
            saveFees(id, idVisit, span, true, 'register.php');
        },
        "Cancel": function() {
            $(this).dialog("close");
        }
    };
    viewVisit(id, idVisit, buttons, 'Edit Visit #' + idVisit + '-' + span, '', span);
}
function getStatusEvent(idResc, type, title) {
    "use strict";
    $.post('ws_resc.php', {
        cmd: 'getStatEvent',
        tp: type,
        title: title,
        id: idResc
    }, function(data) {
        if (data) {
            try {
                data = $.parseJSON(data);
            } catch (err) {
                alert("Parser error - " + err.message);
                return;
            }
            if (data.error) {
                if (data.gotopage) {
                    window.location.assign(data.gotopage);
                }
                alert("Server error - " + data.error);
            } else if (data.tbl) {
                $('#statEvents').children().remove().end().append($(data.tbl));
                $('.ckdate').datepicker({autoSize: true, dateFormat: 'M d, yy'});
                var buttons = {
                    "Save": function () {
                        saveStatusEvent(idResc, type);
                    },
                    'Cancel': function () {
                        $(this).dialog('close');
                    }
                };
                $('#statEvents').dialog('option', 'buttons', buttons);
                $('#statEvents').dialog('open');
            }
        }
    });
}
function saveStatusEvent(idResc, type) {
    "use strict";
    $.post('ws_resc.php', $('#statForm').serialize() + '&cmd=saveStatEvent' + '&id=' + idResc + '&tp=' + type,
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
                    window.location.assign(data.gotopage);
                }
                alert("Server error - " + data.error);
            }
            if (data.reload && data.reload == 1) {
                $('#calendar').hhkCalendar('refetchEvents');
            }
            if (data.msg && data.msg != '') {
                flagAlertMessage(data.msg, false);
            }
        }
        $('#statEvents').dialog('close');
    });
}
function cgRoom(gname, id, idVisit, span) {
    var buttons = {
        "Change Rooms": function() {
            saveFees(id, idVisit, span, true, 'register.php');
        },
        "Cancel": function() {
            $(this).dialog("close");
        }
    };
    viewVisit(id, idVisit, buttons, 'Change Rooms for ' + gname, 'cr', span);
}
function moveVisit(mode, idVisit, visitSpan, startDelta, endDelta) {
    $.post('ws_ckin.php',
            {
                cmd: mode,
                idVisit: idVisit,
                span: visitSpan,
                sdelta: startDelta,
                edelta: endDelta
            },
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
                    window.location.assign(data.gotopage);
                }
                flagAlertMessage(data.error, true);
            } else if (data.success) {
                $('#calendar').hhkCalendar('refetchEvents');
                flagAlertMessage(data.success, false);
                refreshdTables(data);
            }
        }
    });
}
function getRoomList(idResv, eid) {
    if (idResv) {
        // place "loading" icon
        $.post('ws_ckin.php', {cmd: 'rmlist', rid: idResv, x:eid}, function(data) {
            try {
                data = $.parseJSON(data);
            } catch (err) {
                alert("Parser error - " + err.message);
                return;
            }
            if (data.error) {
                if (data.gotopage) {
                    window.location.assign(data.gotopage);
                }
                flagAlertMessage(data.error, true);
                return;
            }
            if (data.container) {
                var contr = $(data.container);
                $('body').append(contr);
                contr.position({
                    my: 'top',
                    at: 'bottom',
                    of: "#" + data.eid
                });
                $('#selRoom').change(function () {
                    if ($('#selRoom').val() == '') {
                        contr.remove();
                        return;
                    }
                    if (confirm('Change room to ' + $('#selRoom option:selected').text() + '?')) {
                        $.post('ws_ckin.php', {cmd: 'setRoom', rid: data.rid, idResc: $('#selRoom').val()}, function(data) {
                            try {
                                data = $.parseJSON(data);
                            } catch (err) {
                                alert("Parser error - " + err.message);
                                return;
                            }
                            if (data.error) {
                                if (data.gotopage) {
                                    window.location.assign(data.gotopage);
                                }
                                flagAlertMessage(data.error, true);
                                return;
                            }
                            if (data.msg && data.msg != '') {
                                flagAlertMessage(data.msg, false);
                            }
                            $('#calendar').hhkCalendar('refetchEvents');
                            refreshdTables(data);
                        });
                    }
                    contr.remove();
                });
            }
        });
    }
}
function checkStrength(pwCtrl) {
    var strongRegex = new RegExp("^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])(?=.{8,})");
    var mediumRegex = new RegExp("^(((?=.*[a-z])(?=.*[A-Z]))|((?=.*[a-z])(?=.*[0-9]))|((?=.*[A-Z])(?=.*[0-9])))(?=.{8,})");
    var rtn = true;
    if(strongRegex.test(pwCtrl.val())) {
        pwCtrl.removeClass("ui-state-error");
    } else if(mediumRegex.test(pwCtrl.val())) {
        pwCtrl.removeClass("ui-state-error");
    } else {
        pwCtrl.addClass("ui-state-error");
        rtn = false;
    }
    return rtn;
}

$(document).ready(function () {
    "use strict";
    var d = new Date();
    var wsAddress = 'ws_ckin.php';
    var eventJSONString = wsAddress + '?cmd=register';
    var hindx = 0;

    if (pmtMkup !== '') {
        $('#paymentMessage').html(pmtMkup).show("pulsate", {}, 400);
    }
    
    $(':input[type="button"], :input[type="submit"]').button();
    
    $.datepicker.setDefaults({
        yearRange: '-10:+02',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        numberOfMonths: 2,
        dateFormat: 'M d, yy'
    });
    
    $('#vstays').on('click', '.stpayFees', function (event) {
        event.preventDefault();
        $("#divAlert1, #paymentMessage").hide();
        payFee($(this).data('name'), $(this).data('id'), $(this).data('vid'), $(this).data('spn'));
    });
    
    $('#vstays').on('click', '.applyDisc', function (event) {
        event.preventDefault();
        $("#divAlert1, #paymentMessage").hide();
        getApplyDiscDiag($(this).data('vid'), $('#pmtRcpt'));
    });
    
    $('#vstays, #vresvs, #vwls, #vuncon').on('click', '.stupCredit', function (event) {
        event.preventDefault();
        $("#divAlert1, #paymentMessage").hide();
        updateCredit($(this).data('id'), $(this).data('reg'), $(this).data('name'), 'cardonfile');
    });
    $('#vstays').on('click', '.stckout', function (event) {
        event.preventDefault();
        $("#divAlert1, #paymentMessage").hide();
        ckOut($(this).data('name'), $(this).data('id'), $(this).data('vid'), $(this).data('spn'));
    });
    $('#vstays').on('click', '.stvisit', function (event) {
        event.preventDefault();
        $("#divAlert1, #paymentMessage").hide();
        editVisit($(this).data('name'), $(this).data('id'), $(this).data('vid'), $(this).data('spn'));
    });
    $('#vstays').on('click', '.hhk-getPSGDialog', function (event) {
        event.preventDefault();
        $("#divAlert1, #paymentMessage").hide();
        editPSG($(this).data('psg'));
    });
    $('#vstays').on('click', '.stchgrooms', function (event) {
        event.preventDefault();
        $("#divAlert1, #paymentMessage").hide();
        cgRoom($(this).data('name'), $(this).data('id'), $(this).data('vid'), $(this).data('spn'));
    });
    $('#vstays').on('click', '.stcleaning', function (event) {
        event.preventDefault();
        $("#divAlert1, #paymentMessage").hide();
        chgRoomCleanStatus($(this).data('idroom'), $(this).data('clean'));
    });
    $('#vresvs, #vwls, #vuncon').on('click', '.resvStat', function (event) {
        event.preventDefault();
        $("#divAlert1, #paymentMessage").hide();
        cgResvStatus($(this).data('rid'), $(this).data('stat'));
    });

    $.extend($.fn.dataTable.defaults, {
        "dom": '<"top"if>rt<"bottom"lp><"clear">',
        "displayLength": 50,
        "lengthMenu": [[25, 50, -1], [25, 50, "All"]],
        "order": [[ 2, 'asc' ]]
    });

    $('#curres').DataTable({
       ajax: {
           url: 'ws_resc.php?cmd=getHist&tbl=curres',
           dataSrc: 'curres'
       },
       "deferRender": true,
       "drawCallback": function (settings) {
            $('#curres .gmenu').menu();
       },
       "columns": cgCols
    });

    $('#reservs').DataTable({
       ajax: {
           url: 'ws_resc.php?cmd=getHist&tbl=reservs',
           dataSrc: 'reservs'
       },
       "drawCallback": function (settings) {
            $('#reservs .gmenu').menu();
       },
       "deferRender": true,
       "columns": rvCols
    });

    if ($('#unreserv').length > 0) {
        $('#unreserv').DataTable({
           ajax: {
               url: 'ws_resc.php?cmd=getHist&tbl=unreserv',
               dataSrc: 'unreserv'
           },
           "drawCallback": function (settings) {
                $('#unreserv .gmenu').menu();
           },
           "deferRender": true,
           "columns": rvCols
        });
    }

    $('#waitlist').DataTable({
       ajax: {
           url: 'ws_resc.php?cmd=getHist&tbl=waitlist',
           dataSrc: 'waitlist'
       },
       "drawCallback": function (settings) {
            $('#waitlist .gmenu').menu();
       },
       "deferRender": true,
       "columns": wlCols
    });


    $('.ckdate3').datepicker({
        onClose: function (dateText, inst) {
            var def = $(this).prop("defaultValue");
            if (dateText != '' && dateText != def) {
                changeExptDeparture($(this).data('id'), $(this).data('vid'), dateText, $(this));
                $(this).val($(this).prop("defaultValue"));
            }
        }
    });
    
    $('#statEvents').dialog({
        autoOpen: false,
        resizable: true,
        width: 830,
        modal: true,
        title: 'Manage Status Events'
    });

    $('#keysfees').dialog({
        autoOpen: false,
        resizable: true,
        modal: true,
        close: function (event, ui) {
            $('div#submitButtons').show();
        },
        open: function (event, ui) {
            $('div#submitButtons').hide();
        }
    });
    $('#keysfees').mousedown(function (event) {
        var target = $(event.target);
        if ( target[0].id !== 'pudiv' && target.parents("#" + 'pudiv').length === 0) {
            $('div#pudiv').remove();
        }
    });

    $("#faDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: 650,
        modal: true,
        title: 'Income Chooser'
    });
    $("#setBillDate").dialog({
        autoOpen: false,
        resizable: true,
        modal: true,
        title: 'Set Invoice Billing Date'
    });
    $('#pmtRcpt').dialog({
        autoOpen: false,
        resizable: true,
        width: 530,
        modal: true,
        title: 'Payment Receipt'
    });
    $('#cardonfile').dialog({
        autoOpen: false,
        resizable: true,
        modal: true,
        title: 'Update Credit Card On File',
        close: function (event, ui) {
            $('div#submitButtons').show();
        },
        open: function (event, ui) {
            $('div#submitButtons').hide();
        }
    });

    $('.ckdate').datepicker();

    if ($('#txtactstart').val() === '') {
        var nowdt = new Date();
        nowdt.setTime(nowdt.getTime() - (5 * 86400000));
        $('#txtactstart').datepicker('setDate', nowdt);
    }

    if ($('#txtfeestart').val() === '') {
        var nowdt = new Date();
        nowdt.setTime(nowdt.getTime() - (3 * 86400000));
        $('#txtfeestart').datepicker('setDate', nowdt);
    }
    
        // Member search letter input box
    $('#txtsearch').keypress(function (event) {
        var mm = $(this).val();
        if (event.keyCode == '13') {
            if (mm === '' || !isNumber(parseInt(mm, 10))) {
                alert("Don't press the return key unless you enter an Id.");
                event.preventDefault();
            } else {
                window.location.assign("GuestEdit.php?id=" + mm);
            }
        }
    });
    createAutoComplete($('#txtsearch'), 3, {cmd: "role",  mode: 'mo'}, 
        function(item) { 
            var cid = item.id;
            if (cid !== 0) {
                window.location.assign("GuestEdit.php?id=" + cid);
            }
        },
        false
    );
    
    var vdays = parseInt(viewDays, 10);
    
    $('#calendar').hhkCalendar({
        defaultView: 'twoweeks',
        viewDays: vdays,
        hospitalSelector: null,
        theme: true,
        contentHeight: parseInt(roomCnt) * 30,
        header: {
            left: 'title',
            center: 'goto',
            right: 'refresh,today prev,next'
        },
        allDayDefault: true,
        lazyFetching: true,
        draggable: false,
        editable: true,
        selectHelper: true,
        selectable: true,
        unselectAuto: true,
        year: d.getFullYear(),
        month: d.getMonth(),
        ignoreTimezone: true,
        eventSources: [{
                url: eventJSONString,
                ignoreTimezone: true
            }],
        select: function (startDate, endDate, allDay, jsEvent, view) {

        },
        eventDrop: function (event, dayDelta, minuteDelta, allDay, revertFunc, jsEvent, ui, view) {
            $("#divAlert1, #paymentMessage").hide();
            if (event.idVisit > 0 && isGuestAdmin) {
                if (confirm('Move Visit to a new start date?')) {
                    moveVisit('visitMove', event.idVisit, event.Span, dayDelta, dayDelta);
                }
            }
            if (event.idReservation > 0 && isGuestAdmin) {
                if (confirm('Move Reservation to a new start date?')) {
                    moveVisit('reservMove', event.idReservation, event.Span, dayDelta, dayDelta);
                }
            }
            revertFunc();
        },
        eventResize: function (event, dayDelta, minuteDelta, revertFunc, jsEvent, ui, view) {
            $("#divAlert1, #paymentMessage").hide();
            if (event.idVisit > 0 && isGuestAdmin) {
                if (confirm('Move check out date?')) {
                    moveVisit('visitMove', event.idVisit, event.Span, 0, dayDelta);
                }
            }
            if (event.idReservation > 0 && isGuestAdmin) {
                if (confirm('Move expected end date?')) {
                    moveVisit('reservMove', event.idReservation, event.Span, 0, dayDelta);
                }
            }
            revertFunc();
        },
        eventClick: function (calEvent, jsEvent, view) {
            $("#divAlert1, #paymentMessage").hide();
            // resources
            if (calEvent.idResc && calEvent.idResc > 0) {
                getStatusEvent(calEvent.idResc, 'resc', calEvent.title);
                return;
            }
            // reservations
            if (calEvent.idReservation && calEvent.idReservation > 0) {
                if (jsEvent.target.classList.contains('hhk-schrm')) {
                    getRoomList(calEvent.idReservation, jsEvent.target.id);
                    return;
                } else {
                    window.location.assign('Referral.php?rid=' + calEvent.idReservation);
                }
            }
            // dont lookup blank events - placeholders
            if (isNaN(parseInt(calEvent.id, 10))) {
                return;
            }
            var buttons = {
                "Show Statement": function() {
                    window.open('ShowStatement.php?vid=' + calEvent.idVisit, '_blank');
                },
                "Show Registration Form": function() {
                    window.open('ShowRegForm.php?vid=' + calEvent.idVisit, '_blank');
                },
                "Save": function () {
                    saveFees(0, calEvent.idVisit, calEvent.Span, true, 'register.php');
                },
                "Cancel": function () {
                    $(this).dialog("close");
                }
            };
            viewVisit(0, calEvent.idVisit, buttons, 'Edit Visit #' + calEvent.idVisit + '-' + calEvent.Span, '', calEvent.Span);
        },
        eventRender: function (event, element) {
            if (hindx == undefined || hindx === 0 || event.idAssoc == hindx || event.idHosp == hindx || event.idHosp == 0) {
                return true;
            }
            return false;
        }
    });

    // disappear the pop-up room chooser.
    $(document).mousedown(function (event) {
        var target = $(event.target);
        if ( target[0].id !== 'pudiv' && target.parents("#" + 'pudiv').length === 0) {
            $('div#pudiv').remove();
        }
    });

    if ($('.spnHosp').length > 0) {
        $('.spnHosp').click(function () {
            $('.spnHosp').css('border', 'solid 1px black').css('font-size', '100%');
            hindx = parseInt($(this).data('id'), 10);
            if (isNaN(hindx))
                hindx = 0;
            $('#calendar').hhkCalendar('rerenderEvents');
            $(this).css('border', 'solid 3px black').css('font-size', '120%');
        });
    }

    $('#btnActvtyGo').click(function () {
        $("#divAlert1, #paymentMessage").hide();
        var stDate = $('#txtactstart').datepicker("getDate");
        if (stDate === null) {
            $('#txtactstart').addClass('ui-state-highlight');
            flagAlertMessage('Enter start date', true);
            return;
        } else {
            $('#txtactstart').removeClass('ui-state-highlight');
        }
        var edDate = $('#txtactend').datepicker("getDate");
        if (edDate === null) {
            edDate = new Date();
        }
        var parms = {
            cmd: 'actrpt',
            start: stDate.toJSON(),
            end: edDate.toJSON()
        };
        if ($('#cbVisits').prop('checked')) {
            parms.visit = 'on';
        }
        if ($('#cbReserv').prop('checked')) {
            parms.resv = 'on';
        }
        if ($('#cbHospStay').prop('checked')) {
            parms.hstay = 'on';
        }
        $.post('ws_resc.php', parms,
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
                        flagAlertMessage(data.error, true);

                    } else if (data.success) {
                        $('#rptdiv').remove();
                        $('#vactivity').append($('<div id="rptdiv"/>').append($(data.success)));
                        $('.hhk-viewvisit').css('cursor', 'pointer');
                        $('#rptdiv').on('click', '.hhk-viewvisit', function () {
                            if ($(this).data('visitid')) {
                                var parts = $(this).data('visitid').split('_');
                                if (parts.length !== 2)
                                    return;
                                var buttons = {
                                    "Save": function () {
                                        saveFees(0, parts[0], parts[1]);
                                    },
                                    "Cancel": function () {
                                        $(this).dialog("close");
                                    }
                                };
                                viewVisit(0, parts[0], buttons, 'View Visit', 'n', parts[1]);
                            } else if ($(this).data('reservid')) {
                                window.location.assign('Referral.php?id=' + $(this).data('reservid'));
                            }
                        });
                    }
                }
            });
    });

    $('#btnFeesGo').click(function () {
        $("#divAlert1, #paymentMessage").hide();
        var stDate = $('#txtfeestart').datepicker("getDate");
        if (stDate === null) {
            $('#txtfeestart').addClass('ui-state-highlight');
            flagAlertMessage('Enter start date', true);
            return;
        } else {
            $('#txtfeestart').removeClass('ui-state-highlight');
        }
        var edDate = $('#txtfeeend').datepicker("getDate");
        if (edDate === null) {
            edDate = new Date();
        }
        var statuses = $('#selPayStatus').val() || [];
        var ptypes = $('#selPayType').val() || [];

        var parms = {
            cmd: 'actrpt',
            start: stDate.toJSON(),
            end: edDate.toJSON(),
            st: statuses,
            pt: ptypes
        };
        
        if ($('#fcbdinv').prop('checked') !== false) {
            parms['sdinv'] = 'on';
        }

        parms.fee = 'on';
        $.post('ws_resc.php', parms,
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
                    flagAlertMessage(data.error, true);

                } else if (data.success) {
                    $('#rptfeediv').remove();
                    $('#vfees').append($('<div id="rptfeediv"/>').append($(data.success)));
                    
                    $('#feesTable').dataTable({
                        "dom": '<"top"if>rt<"bottom"lp><"clear">',
                        "iDisplayLength": 50,
                        "aLengthMenu": [[25, 50, -1], [25, 50, "All"]]
                    });
                    
                    $('#rptfeediv').on('click', '.invAction', function (event) {
                        invoiceAction($(this).data('iid'), 'view', event.target.id);
                    });
                    
                    // Void/Reverse button
                    $('#rptfeediv').on('click', '.hhk-voidPmt', function () {
                        var btn = $(this);
                        if (btn.val() != 'Saving...' && confirm('Void/Reverse?')) {
                            btn.val('Saving...');
                            sendVoidReturn(btn.attr('id'), 'rv', btn.data('pid'));
                        }
                    });
                    
                    // Void-return button
                    $('#rptfeediv').on('click', '.hhk-voidRefundPmt', function () {
                        var btn = $(this);
                        if (btn.val() != 'Saving...' && confirm('Void this Return?')) {
                            btn.val('Saving...');
                            sendVoidReturn(btn.attr('id'), 'vr', btn.data('pid'));
                        }
                    });
                    
                    $('#rptfeediv').on('click', '.hhk-returnPmt', function () {
                        var btn = $(this);
                        if (btn.val() != 'Saving...') {
                            
                            var amt = parseFloat($(this).data('amt'));
                            //var rtn = prompt('Amount to return:', amt.toFixed(2).toString());
                            if (confirm('Return $' + amt.toFixed(2).toString() + '?')) {  //rtn !== null) {
                                btn.val('Saving...');
                                sendVoidReturn(btn.attr('id'), 'r', btn.data('pid'), amt);
                            }
                        }
                    });
                    
                    $('#rptfeediv').on('click', '.pmtRecpt', function () {
                        reprintReceipt($(this).data('pid'), '#pmtRcpt');
                    });
                }
            }
        });
    });
    
    $('#btnInvGo').click(function () {
        var statuses = ['up'];
        var parms = {
            cmd: 'actrpt',
            st: statuses,
            inv: 'on'
        };
        
        $.post('ws_resc.php', parms,
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
                        flagAlertMessage(data.error, true);

                    } else if (data.success) {
                        
                        $('#rptInvdiv').remove();
                        $('#vInv').append($('<div id="rptInvdiv" style="min-height:500px;"/>').append($(data.success)));
                        $('#rptInvdiv .gmenu').menu();
                        
                        $('#rptInvdiv').on('click', '.invLoadPc', function (event) {
                            event.preventDefault();
                            $("#divAlert1, #paymentMessage").hide();
                            invLoadPc($(this).data('name'), $(this).data('id'), $(this).data('iid'));
                        });
                        
                        $('#rptInvdiv').on('click', '.invSetBill', function (event) {
                            event.preventDefault();
                            $("#divAlert1, #paymentMessage").hide();
                            invSetBill($(this).data('inb'), $(this).data('name'), 'div#setBillDate', '#trBillDate' + $(this).data('inb'), $('#trBillDate' + $(this).data('inb')).text(), $('#divInvNotes' + $(this).data('inb')).text(), '#divInvNotes' + $(this).data('inb'));
                        });
                        
                        $('#rptInvdiv').on('click', '.invAction', function (event) {
                            event.preventDefault();
                            $("#divAlert1, #paymentMessage").hide();
                            
                            if ($(this).data('stat') == 'del') {
                                if (!confirm('Delete this Invoice?')) {
                                    return;
                                }
                            }
                            
                            // Check for email
                            if ($(this).data('stat') === 'vem') {
                                    window.open('ShowInvoice.php?invnum=' + $(this).data('inb'));
                                    return;
                            }
   
                            invoiceAction($(this).data('iid'), $(this).data('stat'), event.target.id);
                            $('#rptInvdiv .gmenu').menu("collapse");
                        });
                        
                        $('#InvTable').dataTable({
                            "dom": '<"top"if>rt<"bottom"lp><"clear">',
                            "displayLength": 50,
                            "lengthMenu": [[20, 50, 100, -1], [20, 50, 100, "All"]],
                            "order": [[ 1, 'asc' ]]
                        });
                    }
                }
            });
    });

    $('#btnPrintRegForm').click(function () {
        window.open($(this).data('page') + '?d=' + $('#regckindate').val(), '_blank');
    });

    $('#btnPrintWL').click(function () {
        window.open($(this).data('page') + '?d=' + $('#regwldate').val(), '_blank');
    });

    if (rctMkup !== '') {
        showReceipt('#pmtRcpt', rctMkup, 'Payment Receipt');
    }
    
    $('.gmenu').menu();

    $('#version').click(function () {
        $('div#dchgPw').find('input').removeClass("ui-state-error").val('');
        $('#pwChangeErrMsg').text('');

        $('#dchgPw').dialog("option", "title", "Change Your Password");
        $('#dchgPw').dialog('open');
        $('#txtOldPw').focus();
    });

    $('div#dchgPw').on('change', 'input', function () {
        $(this).removeClass("ui-state-error");
        $('#pwChangeErrMsg').text('');
    });
    
    $('#dchgPw').dialog({
        autoOpen: false,
        width: 450,
        resizable: true,
        modal: true,
        buttons: {
            "Save": function () {
                
                var oldpw = $('#txtOldPw'), 
                        pw1 = $('#txtNewPw1'),
                        pw2 = $('#txtNewPw2'),
                        oldpwMD5, 
                        newpwMD5,
                        msg = $('#pwChangeErrMsg');
                
                if (oldpw.val() == "") {
                    oldpw.addClass("ui-state-error");
                    oldpw.focus();
                    msg.text('Enter your old password');
                    return;
                }

                if (checkStrength(pw1) === false) {
                    pw1.addClass("ui-state-error");
                    msg.text('Password must have 8 characters including at least one uppercase and one lower case alphabetical character and one number.');
                    pw1.focus();
                    return;
                }

                if (pw1.val() !== pw2.val()) {
                    msg.text("New passwords do not match");
                    return;
                }

                if (oldpw.val() == pw1.val()) {
                    pw1.addClass("ui-state-error");
                    msg.text("The new password must be different from the old password");
                    pw1.focus();
                    return;
                }

                // make MD5 hash of password and concatenate challenge value
                // next calculate MD5 hash of combined values
                oldpwMD5 = hex_md5(hex_md5(oldpw.val()) + challVar);
                newpwMD5 = hex_md5(pw1.val());

                oldpw.val('');
                pw1.val('');
                pw2.val('');
                
                $.post("ws_admin.php",
                    {
                        cmd: 'chgpw',
                        old: oldpwMD5,
                        newer: newpwMD5
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
                                flagAlertMessage(data.error, true);
                                                                
                            } else if (data.success) {
                                
                                $('#dchgPw').dialog("close");
                                flagAlertMessage(data.success, false);
                                
                            } else if (data.warning) {
                                $('#pwChangeErrMsg').text(data.warning);
                            }
                        }
                    }
                );
            },
            "Cancel": function () {
                $(this).dialog("close");
            }
        }
    });

    $('#mainTabs').tabs({
        beforeActivate: function (event, ui) {
            if (ui.newTab.prop('id') === 'liInvoice') {
                $('#btnInvGo').click();
            }
        },
        activate: function (event, ui) {
            if (ui.newTab.prop('id') === 'liCal') {
                $('#calendar').hhkCalendar('render');
            }
        }
    });
    $('#mainTabs').show();
    $('#mainTabs').tabs("option", "active", defaultTab);
    $('#calendar').hhkCalendar('render');

});
