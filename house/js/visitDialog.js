/**
 * visitDialog.js
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * 
 * @param {string} mess
 * @param {boolean} wasError
 * @returns {undefined}
 */
function flagAlertMessage(mess, wasError) {
    "use strict";
    var spn = document.getElementById('alrMessage');
    if (!wasError) {
        // define the error message markup
        $('#alrResponse').removeClass("ui-state-error").addClass("ui-state-highlight");
        $('#alrIcon').removeClass("ui-icon-alert").addClass("ui-icon-info");
        spn.innerHTML = "<strong>Result: </strong>" + mess;
        $("#divAlert1").show("pulsate", {}, 400);
        window.scrollTo(0, 5);
    } else {
        // define the success message markup
        $('alrResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
        $('#alrIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
        spn.innerHTML = "<strong>Alert: </strong>" + mess;
        $("#divAlert1").show("pulsate", {}, 400);
        window.scrollTo(0, 5);
    }
}
/**
 * 
 * @param {object} item
 * @param {int} idVisit
 * @param {int} visitSpan
 * @returns {undefined}
 */
function getMember(item, idVisit, visitSpan) {
    "use strict";
    $.post('ws_ckin.php',
            {
                cmd: 'addStay',
                vid: idVisit,
                id: item.id,
                span: visitSpan
            },
        function(data) {
            myReply(data, item.id, idVisit, visitSpan);
        });
    function myReply(data, idGuest, idVisit, visitSpan) {
        "use strict";
        if (!data) {
            alert('Bad Reply from Server');
            return;
        }
        try {
            data = $.parseJSON(data);
        } catch (err) {
            alert("Parser error - " + err.message);
            return;
        }
        if (data.error) {
            if (data.gotopage) {
                window.open(data.gotopage);
            }
            flagAlertMessage(data.error, true);
            return;
        }
        $('#txtAddGuest').val('');
        if (data.addtguest) {
            $('#keysfees').dialog('close');
            $('#diagAddGuest').remove();
            // create a dialog and show the form.
            var acBody = $('<div style="font-size:.9em;"/>').append($(data.addtguest.memMkup));
            var acHdr = $('<div style="min-height:30px; padding:3px;font-size:.9em;"/>')
                .append($(data.addtguest.txtHdr))
                .append($('<span id="' + data.addtguest.idPrefix + 'memMsg" style="color: red; margin-right:20px;margin-left:20px;margin-top:7px;"></span>'))
                .addClass('ui-widget-header ui-state-default ui-corner-top');
            var acDiv = $('<div id="diagAddGuest"/>').append($('<form id="fAddGuest"  style="font-size:.9em;"/>').append(acHdr).append(acBody));
            acDiv.dialog({
                autoOpen: false,
                resizable: true,
                width: 950,
                modal: true,
                title: 'Additional Guest',
                buttons: {
                    Save: function () {
                        myReq(idGuest, idVisit, visitSpan);
                        $(this).dialog("close");
                    },
                    "Cancel": function() {
                        $(this).dialog("close");
                    }
                }
            });
            // Guest date
            $('div#diagAddGuest .ckdate').datepicker();
            acDiv.find('select.bfh-countries').each(function() {
                var $countries = $(this);
                $countries.bfhcountries($countries.data());
            });
            acDiv.find('select.bfh-states').each(function() {
                var $states = $(this);
                $states.bfhstates($states.data());
            });
            $('#diagAddGuest #qphEmlTabs').tabs();
            verifyAddrs('#diagAddGuest');
            if (data.addr) {
                $('#diagAddGuest').on('click', '.hhk-addrCopy', function() {
                    $('#qadraddress11').val(data.addr.adraddress1);
                    $('#qadraddress21').val(data.addr.adraddress2);
                    $('#qadrcity1').val(data.addr.adrcity);
                    $('#qadrstate1').val(data.addr.adrstate);
                    $('#qadrzip1').val(data.addr.adrzip);
                });
            }
            $('#diagAddGuest').on('click', '.hhk-addrErase', function() {
                $('#qadraddress11').val('');
                $('#qadraddress21').val('');
                $('#qadrcity1').val('');
                $('#qadrstate1').val('');
                $('#qadrzip1').val('');
                $('#qadrbad1').prop('checked', false);
            });
            acDiv.dialog('open');
            return;
        }
    }
    function myReq(idGuest, idVisit, visitSpan) {
        $.post('ws_ckin.php', $('#fAddGuest').serialize() + '&cmd=addStay' + '&id=' + idGuest + '&vid=' + idVisit + '&span=' + visitSpan, function(data) {
            try {
                data = $.parseJSON(data);
            } catch (err) {
                alert("Parser error - " + err.message);
                return;
            }
            if (data.error) {
                if (data.gotopage) {
                    window.open(data.gotopage);
                }
                flagAlertMessage(data.error, true);
            }
            if (data.stays && data.stays !== '') {
                $('#keysfees').dialog('open');
                $('#divksStays').children().remove();
                $('#divksStays').append($(data.stays));
            }
        });
    }
}
/**
 * 
 * @param {object} item
 * @param {int} orderNum
 * @returns {undefined}
 */
function getInvoicee(item, orderNum) {
    "use strict";
    var cid = parseInt(item.id, 10);
    if (isNaN(cid) === false && cid > 0) {
        $('#txtInvName').val(item.value);
        $('#txtInvId').val(cid);
    } else {
        $('#txtInvName').val('');
        $('#txtInvId').val('');
    }
    $('#txtOrderNum').val(orderNum);
    $('#txtInvSearch').val('');
}

var isCheckedOut = false;
/**
 * 
 * @param {int} idGuest
 * @param {int} idVisit
 * @param {object} buttons
 * @param {string} title
 * @param {string} action
 * @param {int} visitSpan
 * @param {string} ckoutDt
 * @returns {undefined}
 */
function viewVisit(idGuest, idVisit, buttons, title, action, visitSpan, ckoutDt) {
    "use strict";
    $.post('ws_ckin.php',
        {
            cmd: 'visitFees',
            idVisit: idVisit,
            idGuest: idGuest,
            action: action,
            span: visitSpan,
            ckoutdt: ckoutDt
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
                    return;
                }
                flagAlertMessage(data.error, true);
                
            } else if (data.success) {
                
                var $diagbox = $('#keysfees');

                $diagbox.children().remove();
                $diagbox.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(data.success)));
                $diagbox.find('.ckdate').datepicker({
                    yearRange: '-01:+01',
                    changeMonth: true,
                    changeYear: true,
                    autoSize: true,
                    numberOfMonths: 1,
                    maxDate: 0,
                    dateFormat: 'M d, yy',
                    onSelect: function() {
                        this.lastShown = new Date().getTime();
                    },
                    beforeShow: function() {
                        var time = new Date().getTime();
                        return this.lastShown === undefined || time - this.lastShown > 500;
                    },
                    onClose: function () {
                        $(this).change();
                    }
                });
                
                $diagbox.find('.ckdateFut').datepicker({
                    yearRange: '-01:+01',
                    changeMonth: true,
                    changeYear: true,
                    autoSize: true,
                    numberOfMonths: 1,
                    minDate: 0,
                    dateFormat: 'M d, yy',
                    onSelect: function() {
                        this.lastShown = new Date().getTime();
                    },
                    beforeShow: function() {
                        var time = new Date().getTime();
                        return this.lastShown === undefined || time - this.lastShown > 500;
                    },
                    onClose: function () {
                        $(this).change();
                    }
                });
                
                $diagbox.css('background-color', '#fff');
                
                if (action === 'ref') {
                    $diagbox.css('background-color', '#FEFF9B');
                }
                            
                
                if ($('.hhk-extVisitSw').length > 0) {
                    $('.hhk-extVisitSw').change(function () {
                        if (this.checked) {
                            $('.hhk-extendVisit').show('fade');
                        } else {
                            $('.hhk-extendVisit').hide('fade');
                        }
                    });
                    $('.hhk-extVisitSw').change();
                }
                
                if ($('#rateChgCB').length > 0) {
                    
                    var rateChangeDate = $('#chgRateDate');
                    
                    rateChangeDate.datepicker({
                        changeMonth: true,
                        changeYear: true,
                        autoSize: true,
                        numberOfMonths: 1,
                        dateFormat: 'M d, yy',
                        maxDate: new Date(data.end),
                        minDate: new Date(data.start)
                    });
                    
                    rateChangeDate.change(function () {
                        if (this.value !== '') {
                            rateChangeDate.siblings('input#rbReplaceRate').prop('checked', true);
                        }
                    });
                    
                    $('input#rbReplaceRate').change(function () {
                        if (this.checked && rateChangeDate.val() === '') {
                            rateChangeDate.val($.datepicker.formatDate('M d, yy', new Date()));
                        } else {
                            rateChangeDate.val('');
                        }
                    });
                    
                    $('#rateChgCB').change(function () {
                        if (this.checked) {
                            $('.changeRateTd').show('fade');
                            $('#showRateTd').hide('fade');
                        }else {
                            $('.changeRateTd').hide('fade');
                            $('#showRateTd').show('fade');
                        }
                    });
                    
                    $('#rateChgCB').change();
                }
                
                $('#spnExPay').hide();

                if ($('input.hhk-ckoutCB').length > 0) {
                    // still checked in...
                    
                    $('#tblStays').on('change', 'input.hhk-ckoutCB', function() {
                        
                        var ckout = true,
                            coTime = 1,
                            today = new Date();

                        if (this.checked === false) {
                            $(this).next().val('');  // clear the checkout date field
                        } else if ($(this).next().val() == '') {
                            $(this).next().val($.datepicker.formatDate('M d, yy', new Date()));  // set the checkout date field
                        }
                        
                        // Are we checking out?
                        // Scan all checkout checkboxes
                        $('input.hhk-ckoutCB').each(function () {
                            
                            if (this.checked === false) {
                                
                                ckout = false;
                                
                            } else if ($(this).next().val() != '') {
                                
                                var d = new Date($(this).next().val());
                                
                                if (d.getTime() > today.getTime()) {
                                    $(this).next().val('');
                                    ckout = false;
                                } else if (d.getTime() > coTime) {
                                    coTime = d.getTime();
                                }
                                
                            }
                        });
                        
                        
                        if (ckout === true) {
                            
                            isCheckedOut = true;
                            // check to update the final amount...
                            var today = new Date();
                            var todayStr = today.getFullYear() + '-' + today.getMonth() + '-' + today.getDate();
                            var coDate = new Date(coTime);
                            var coDateStr = coDate.getFullYear() + '-' + coDate.getMonth() + '-' + coDate.getDate();
                            var roomChgBal = parseFloat($('#spnCfBalDue').data('bal'));
                            var vFeeChgBal = parseFloat($('#spnCfBalDue').data('vfee'));
                            roomChgBal -= vFeeChgBal;
                            
                            if (coDate.getTime() > today.getTime()) {
                                return false;
                            }
                            
                            if (todayStr !== coDateStr && action !== 'ref') {
                                // update dialog with new co date.
                                $diagbox.children().remove();
                                $diagbox.dialog('option', 'buttons', {});
                                $diagbox.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog"/>')
                                        .append($('<div class="ui-autocomplete-loading" style="width:5em;">Loading</div>')));
                                viewVisit(idGuest, idVisit, buttons, title, 'ref', visitSpan, coDate.toDateString());
                                return;
                            }
                            
                            // hide deposit payment
                            $('.hhk-kdrow').hide('fade');
                            
                            // Show final payment checkbox
                            $('.hhk-finalPayment').show('fade');
                            
                            var kdamt = parseFloat($('#kdPaid').data('amt'));
                            if (isNaN(kdamt)) {
                                kdamt = 0;
                            }
                            
                            if (kdamt > 0) {
                                $('#DepRefundAmount').val((0 - kdamt).toFixed(2).toString());
                                $('.hhk-refundDeposit').show('fade');
                            } else {
                                $('#DepRefundAmount').val('');
                                $('.hhk-refundDeposit').hide('fade');
                            }


                            if (roomChgBal < 0) {
                                $('#guestCredit').val(roomChgBal.toFixed(2).toString());
                                $('#feesCharges').val('');
                                $('.hhk-RoomCharge').hide();
                                $('.hhk-GuestCredit').show();
                            } else {
                                $('#feesCharges').val(roomChgBal.toFixed(2).toString());
                                $('#guestCredit').val('');
                                $('.hhk-GuestCredit').hide();
                                $('.hhk-RoomCharge').show();
                            }
                            
                            $('input#cbFinalPayment').change();
                            
                        } else if (action === 'ref') {

                            $diagbox.children().remove();
                            $diagbox.dialog('option', 'buttons', {});
                            $diagbox.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog"/>')
                                    .append($('<div class="ui-autocomplete-loading" style="width:5em;">Loading</div>')));
                            viewVisit(idGuest, idVisit, buttons, title, '', visitSpan);
                            return;
                            
                        } else {
                            
                            isCheckedOut = false;
                            $('.hhk-finalPayment').hide('fade');
                            $('.hhk-GuestCredit').hide();
                            $('.hhk-RoomCharge').hide();
                            $('#feesCharges').val('');
                            $('#guestCredit').val('');
                            $('.hhk-refundDeposit').hide('fade');
                            $('#DepRefundAmount').val('');
                            $('input#cbFinalPayment').prop('checked', false);
                            $('input#cbFinalPayment').change();
                        }
                    });
                    
                    $('#tblStays').on('change', 'input.hhk-ckoutDate', function() {
                        
                        if ($(this).val() != '') {
                            var cb = $(this).prev();
                            cb.prop('checked', true);
                        } else {
                            $(this).prev().prop('checked',false);
                        }
                        $('input.hhk-ckoutCB').change();
                    });
                    
                    $('input.hhk-ckoutCB').change();
                    
                } else if ($('#cbFinalPayment').length > 0) {
                    
                    isCheckedOut = true;
                    var roomChgBal = parseFloat($('#spnCfBalDue').data('bal'));
                    var vFeeChgBal = parseFloat($('#spnCfBalDue').data('vfee'));
                    roomChgBal -= vFeeChgBal;
                    
                    $('.hhk-finalPayment').show();
                    var kdamt = parseFloat($('#kdPaid').data('amt'));

                    if (isNaN(kdamt)) {
                         $('#DepRefundAmount').val('');
                        $('.hhk-refundDeposit').hide('fade');
                    } else {
                         $('#DepRefundAmount').val((0 - kdamt).toFixed(2).toString());
                        $('.hhk-refundDeposit').show('fade');
                    }

                    if (roomChgBal < 0) {
                        $('#guestCredit').val(roomChgBal.toFixed(2).toString());
                        $('#feesCharges').val('');
                        $('.hhk-RoomCharge').hide();
                        $('.hhk-GuestCredit').show();
                    } else {
                        $('#feesCharges').val(roomChgBal.toFixed(2).toString());
                        $('#guestCredit').val('');
                        $('.hhk-GuestCredit').hide();
                        $('.hhk-RoomCharge').show();
                    }
                    
                    $diagbox.css('background-color', '#F2F2F2');
                }
                
                
                setupPayments(data.resc, $('#selResource'), $('#selRateCategory'), idVisit, $('#pmtRcpt'));
                
                var $btnFapp = $('#btnFapp');
                if ($btnFapp.length > 0) {
                    $btnFapp.button();
                    $btnFapp.click(function () {
                        getIncomeDiag($btnFapp.data('rid'));
                    });
                }
                
                $('#guestAdd').click(function () {
                    $('.hhk-addGuest').toggle();
                });
                
                var lstXhr;
                $('#txtInvSearch').keypress(function (event) {
                    var mm = $(this).val();
                    if (event.keyCode == '13') {
                        
                        if (mm == '' || !isNumber(parseInt(mm, 10))) {
                            
                            alert("Don't press the return key unless you enter an Id.");
                            event.preventDefault();
                            
                        } else {
                            
                            $.getJSON("../house/roleSearch.php", {cmd: "filter", 'basis':'ba', letters:mm},
                            function(data) {
                                try {
                                    data = data[0];
                                } catch (err) {
                                    alert("Parser error - " + err.message);
                                    return;
                                }
                                if (data && data.error) {
                                    if (data.gotopage) {
                                        response();
                                        window.open(data.gotopage);
                                    }
                                    data.value = data.error;
                                }
                                getInvoicee(data, idVisit);
                            });
                            
                        }
                    }
                });
                createAutoComplete($('#txtInvSearch'), 3, {cmd: "filter", 'basis':'ba'}, function (item) { getInvoicee(item, idVisit); }, lstXhr, false);
                createAutoComplete($('#txtAddGuest'), 3, {cmd: "role"}, function (item) { getMember(item, idVisit, visitSpan); }, lstXhr);

                if ($('#selRateCategory').length > 0) {
                    $('#selRateCategory').change(function () {
                        if ($(this).val() == 'x') {
                            $('.hhk-fxFixed').show('fade');
                            $('.hhk-fxAdj').hide('fade');
                        } else {
                            $('.hhk-fxFixed').hide('fade');
                            $('.hhk-fxAdj').show('fade');
                        }
                    });
                    $('#selRateCategory').change();
                }

                $diagbox.dialog('option', 'buttons', buttons);
                $diagbox.dialog('option', 'title', title);
                $diagbox.dialog('option', 'width', ($( window ).width() * .8));
                $diagbox.dialog('option', 'height', $( window ).height());
                $diagbox.dialog('open');
            }
        }
    });
}

/**
 * 
 * @param {int} idGuest
 * @param {int} idVisit
 * @param {int} visitSpan
 * @param {boolean} rtnTbl
 * @param {string} postbackPage
 * @returns {undefined}
 */
function saveFees(idGuest, idVisit, visitSpan, rtnTbl, postbackPage) {
    "use strict";
    var ckoutlist = [];
    var removeList = [];
    var resvResc = '0';
    var undoCheckout = false;
    var parms = {
        cmd: 'saveFees',
        idGuest: idGuest,
        idVisit: idVisit,
        span: visitSpan,
        rtntbl: (rtnTbl === true ? '1' : '0'),
        pbp: postbackPage
    };
    
    $('input.hhk-expckout').each(function() {
        var parts = $(this).attr('id').split('_');
        if (parts.length > 0) {
            parms[parts[0] + '[' + parts[1] + ']'] = $(this).val();
        }
    });
    
    // Undo checkout
    if ($('#undoCkout').length > 0 && $('#undoCkout').prop('checked')) {
        undoCheckout = true;
    }
    
    // Overpayment disposition
    if (isCheckedOut && verifyBalDisp() === false && undoCheckout === false) {
        return;
    }
    
    // Cash amount tendered
    if (verifyAmtTendrd() === false) {
        return;
    }
    

    $('input.hhk-ckoutCB').each(function() {
        if (this.checked) {
            var parts = $(this).attr('id').split('_');
            if (parts.length > 0) {
                parms['stayActionCb[' + parts[1] + ']'] = 'on';
                var tdate = $('#stayCkOutDate_' + parts[1]).datepicker('getDate');
                if (tdate) {
                    var nowDate = new Date();
                    tdate.setHours(nowDate.getHours());
                    tdate.setMinutes(nowDate.getMinutes());
                } else {
                    tdate = new Date();
                }
                parms['stayCkOutDate[' + parts[1] + ']'] = tdate.toJSON();
                ckoutlist.push($(this).data('nm') + ', ' + tdate.toDateString());
            }
        }
    });
    
    $('input.hhk-removeCB').each(function () {
        if (this.checked) {
            var parts = $(this).attr('id').split('_');
            if (parts.length > 0) {
                parms[parts[0] + '[' + parts[1] + ']'] = 'on';
                removeList.push($(this).data('nm'));
            }
        }
    });
    
    if (ckoutlist.length > 0) {
        var cnfMsg = 'Check Out:\n' + ckoutlist.join('\n');
        if ($('#EmptyExtend').val() === '1' && $('#extendCb').prop('checked') && ckoutlist.length >= $('#currGuests').val()) {
           cnfMsg += '\nand extend the visit for ' + $('#extendDays').val() + ' days';
        }
        if (confirm(cnfMsg + '?') === false) {
            $('#keysfees').dialog("close");
            return;
        }
    }
    
    if (removeList.length > 0) {
        if (confirm('Remove:\n' + removeList.join('\n') + '?') === false) {
            $('#keysfees').dialog("close");
            return;
        }
    }
    
    $('#keyDepAmt').removeClass('ui-state-highlight');
    
    if ($('#resvResource').length > 0) {
        
        resvResc = $('#resvResource').val();
    
        if (resvResc != '0') {
            $('#resvChangeDate').removeClass('ui-state-highlight');
            $('#chgmsg').text('');
            var valMsg = $('<span id="chgmsg"/>');
            if ($('#resvChangeDate').val() == '') {
                valMsg.text("Enter a change room date.");
                valMsg.css('color', 'red');
                $('#moveTable').prepend($('<tr/>').append($('<td colspan="2">').append(valMsg)));
                $('#resvChangeDate').addClass('ui-state-highlight');
                return;
            }
            var chgDate = $('#resvChangeDate').datepicker("getDate");
            if (!chgDate) {
                valMsg.text("Something wrong with the change room date.");
                valMsg.css('color', 'red');
                $('#moveTable').prepend($('<tr/>').append($('<td colspan="2">').append(valMsg)));
                $('#resvChangeDate').addClass('ui-state-highlight');
                return;
            }
            if (chgDate > new Date()) {
                valMsg.text("Change room date can't be in the future.");
                valMsg.css('color', 'red');
                $('#moveTable').prepend($('<tr/>').append($('<td colspan="2">').append(valMsg)));
                $('#resvChangeDate').addClass('ui-state-highlight');
                return;
            }
            if (confirm('Change Rooms?') === false) {
                $('#keysfees').dialog("close");
                return;
            }
        }
    }

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
    
    $('#keysfees').css('background-color', 'white');
    $('#keysfees').dialog("close");

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
            
            paymentReply(data, true);
            
            if (typeof refreshdTables !== 'undefined') {
                refreshdTables(data);
            }
    });
}

function paymentReply (data, updateCal) {
    "use strict";
    if (data) {
        
        if (data.hostedError) {
            
            flagAlertMessage(data.hostedError, true);
            
        } else if (data.xfer && $('#xform').length > 0) {
            
            var xferForm = $('#xform');
            xferForm.children('input').remove();
            xferForm.prop('action', data.xfer);
            if (data.paymentId && data.paymentId != '') {
                xferForm.append($('<input type="hidden" name="PaymentID" value="' + data.paymentId + '"/>'));
            } else if (data.cardId && data.cardId != '') {
                xferForm.append($('<input type="hidden" name="CardID" value="' + data.cardId + '"/>'));
            } else {
                flagAlertMessage('PaymentId and CardId are missing!', true);
                return;
            }
            
            xferForm.submit();
        }
        
        
        if (data.success && data.success !== '') {
            flagAlertMessage(data.success, false);
        
            if ($('#calendar').length > 0 && updateCal) {
                $('#calendar').hhkCalendar('refetchEvents');
            }
        }
        
        if (data.receipt && data.receipt !== '') {
            showReceipt('#pmtRcpt', data.receipt, 'Payment Receipt');
        }
        
        if (data.invoice && data.invoice !== '') {
            showReceipt('#pmtRcpt', data.invoice, 'Invoice', 800);
        }
    }

}
/**
 * 
 * @param {string} header
 * @param {string} body
 * @returns {undefined}
 */
function updateVisitMessage(header, body) {
    //$('#visitMsg').toggle("clip");
    $('#h3VisitMsgHdr').text(header);
    $('#spnVisitMsg').text(body);
    $('#visitMsg').effect("pulsate");
}


