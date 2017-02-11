// resv.js
function getAgent(item) {
    "use strict";
    var cid = parseInt(item.id, 10);
    if (isNaN(cid) === false && cid > 0) {
        $('#a_txtFirstName').val(item.first);
        $('#a_txtLastName').val(item.last);
        $('#a_txtPhonemc').val(item.cphone);
        $('#a_txtPhonegw').val(item.wphone);
        $('#a_txtEmail1').val(item.email);
        $('#a_idName').val(cid);
        $('#txtAgentSch').val('');
    } else {
        $('#a_txtFirstName').val('');
        $('#a_txtLastName').val('');
        $('#a_txtPhonemc').val('');
        $('#a_txtPhonegw').val('');
        $('#a_txtEmail1').val('');
        $('#a_idName').val('0');
        $('#txtAgentSch').val('');        
    }
    $('.hhk-agentInfo').show();
}
function getDoc(item) {
    "use strict";
    var cid = parseInt(item.id, 10);
    if (isNaN(cid) === false && cid > 0) {
        $('#d_txtFirstName').val(item.first);
        $('#d_txtLastName').val(item.last);
        $('#d_idName').val(cid);
        $('#txtDocSch').val('');
    } else {
        $('#d_txtFirstName').val('');
        $('#d_txtLastName').val('');
        $('#d_idName').val('0');
        $('#txtDocSch').val('');
    }
    $('.hhk-docInfo').show();
}
function gotIncomeDiag(idResv, idReg, data) {
    if (data.error) {
        if (data.gotopage) {
            window.location.assign(data.gotopage);
        }
        flagAlertMessage(data.error, true);
        return;
    }
    if (data.incomeDiag) {
        var buttons = {
            Save: function() {
                $.post('ws_ckin.php', $('#formf').serialize() + '&cmd=savefap' + '&rid=' + idResv + '&rgId=' + idReg, function(data) {
                    try {
                        data = $.parseJSON(data);
                    } catch (err) {
                        alert('Bad JSON Encoding');
                        return;
                    }
                    if (data.gotopage) {
                        window.location.assign(data.gotopage);
                    }
                    if (data.error) {
                        flagAlertMessage(data.error, true);
                    }
                    if (data.rstat && data.rstat == true) {
                        var selCat = $('#selRateCategory');
                        if (data.rcat && data.rcat != '' && selCat.length > 0) {
                            selCat.val(data.rcat);
                            selCat.change();
                        }
                    }
                });
                $(this).dialog("close");
            },
            "Exit": function() {
                $(this).dialog("close");
            }
        };
        $("#faDialog").children().remove().end().append($(data.incomeDiag)).dialog("option", "buttons", buttons).dialog('open');
        $('.ckdate').datepicker();
        $('#txtFaIncome, #txtFaSize').change(function () {
            var income = $('#txtFaIncome'),
                size = $('#txtFaSize');
            if (income.val() === '' || size.val() === '') {
                return;
            }
            var inc = income.val().replace(',', ''),
                sizeVal = size.val(),
                errmsg = $('#spnErrorMsg');
            errmsg.text('');
            $('#txtFaIncome, #txtFaSize, #spnErrorMsg').removeClass('ui-state-highlight');
            if (isNaN(inc)) {
                $('#txtFaIncome').addClass('ui-state-highlight');
                errmsg.text('Fill in the Household Income').addClass('ui-state-highlight');
                return false;
            }
            if (sizeVal == '0' || isNaN(sizeVal)) {
                size.addClass('ui-state-highlight');
                errmsg.text('Fill in the Household Size').addClass('ui-state-highlight');
                return false;
            }
            $('#spnFaCatTitle').hide();
            $.post('ws_ckin.php', {
                cmd: 'rtcalc',
                income: inc,
                hhsize: sizeVal,
                nites: 0
            }, function(data) {
                data = $.parseJSON(data);
                if (data.gotopage) {
                    window.location.assign(data.gotopage);
                }
                if (data.catTitle) {
                    $('#spnFaCatTitle').text(data.catTitle).show('slide horizontal');
                }
                if (data.cat) {
                    $('#hdnRateCat').val(data.cat);
                }
            });
            return false;
        });
    }
}
function getIncomeDiag(idResv, idReg) {
    "use strict";
    $.getJSON("ws_ckin.php", {rid: idResv, rgId: idReg, cmd: 'getincmdiag'})
        .done(function(data) {
            gotIncomeDiag(idResv, idReg, data);
        });
}
function setupRates(ckIn, idResource) {
    "use strict";
    if ($('#selVisitFee').length > 0) {
        $('#selVisitFee').change(function() {
            $('#selRateCategory').change();
            if ($('#visitFeeCb').length > 0) {
                // update the visit fee
                var amt = parseFloat(ckIn.visitFees[$('#selVisitFee').val()][2]);
                if (isNaN(amt) || amt < 0) {
                    amt = 0;
                }
                if (amt === 0) {
                    $('#visitFeeAmt').val('');
                    $('#visitFeeCb').prop('checked', false);
                    $('.hhk-vfrow').hide('fade');
                } else {
                    $('#visitFeeAmt').val(amt.toFixed(2).toString());
                    $('#spnvfeeAmt').text('($' + amt.toFixed(2).toString() + ')');
                    $('.hhk-vfrow').show('fade');
                }
                $('#spnvfeeAmt').data('amt', amt);
                $('#visitFeeCb').change();
            }
        });
        $('#selVisitFee').change();
    }
    $('#txtFixedRate').change(function() {
        if ($('#selRateCategory').val() == 'x') {
            var amt = parseFloat($(this).val());
            if (isNaN(amt) || amt < 0) {
                amt = parseFloat($(this).prop("defaultValue"));
                if (isNaN(amt) || amt < 0)
                    amt = 0;
                $(this).val(amt);
            }
            if ($('#selResource').length > 0 && ckIn.resources[$('#selResource').val()]) {
                ckIn.resources[$('#selResource').val()].rate = amt;
            }
            var ds = parseInt($('#spnNites').text(), 10);
            if (isNaN(ds)) {
                ds = 0;
            }
            var fa = 0;
            if ($('#selVisitFee').length > 0) {
                fa = parseFloat(ckIn.visitFees[$('#selVisitFee').val()][2]);
                if (isNaN(fa) || fa < 0) {
                    fa = 0;
                }
            }
            $('#spnLodging').text('$' + (amt * ds));
            var total = (amt * ds) + fa;
            $('#spnAmount').text('$' + total);
        }
    });
    $('#txtadjAmount').change(function () {
        var amt = 0,
            guests = 1,
            category = $('#selRateCategory');
        if (category.val() != 'x') {
            var adj = parseFloat($(this).val());
            if (isNaN(adj)) {
                adj = parseFloat($(this).prop("defaultValue"));
                if (isNaN(adj)) {
                    adj = 0;
                }
                $(this).val(adj);
            }
            var fa = 0;
            if ($('#selVisitFee').length > 0) {
                fa = parseFloat(ckIn.visitFees[$('#selVisitFee').val()][2]);
                if (isNaN(fa) || fa < 0) {
                    fa = 0;
                }
            }
            if (ckIn.rateList && ckIn.rateList[category.val()] !== false) {
                amt = parseFloat(ckIn.rateList[category.val()]);
                if (isNaN(amt) || amt < 0) {
                    amt = 0;
                }
                if (category.val() == 'dg') {
                    guests = parseInt($('#spnNumGuests').text(), 10);
                    if (guests < 1) {
                        guests = 1;
                    }
                }
                var newAmt = (amt * (1 + adj/100) * guests);
                $('#spnLodging').text('$' + newAmt);
                newAmt += fa;
                $('#spnAmount').text('$' + newAmt);
            }
        }
    });
    $('#selRateCategory').change(function () {
        if ($(this).val() == 'x') {
            $('.hhk-fxFixed').show();
            var idresc = $('#selResource').val();
            if (ckIn.resources[idresc]) {
                $('#txtFixedRate').val(ckIn.resources[idresc].rate);
            }
            $('.hhk-fxAdj').hide();
        } else {
            $('.hhk-fxFixed').hide();
            $('.hhk-fxAdj').show();
        }
        $('#txtFixedRate').change();
        $('#txtadjAmount').change();
    });
    $('#selRateCategory').change();
}
function updateRoomChooser(idReserv, numGuests, arrivalDate, departureDate) {
    
    var cbRS = {};
    var idResc;

    if ($('#selResource').length === 0 || $('input.hhk-constraintsCB').length === 0) {
        return;
    }
    
    idResc = $('#selResource option:selected').val();

    hideAlertMessage();

    $('#selResource').prop('disabled', true);
    $('#hhk-roomChsrtitle').addClass('hhk-loading');
    $('#hhkroomMsg').text('').hide();
    
    // loading symbol
    //var loadg = $('<div id="divLoadg">').append()
    
    $('input.hhk-constraintsCB:checked').each(function () {
        var nod = $(this).data('cnid');
        cbRS[nod] = 'ON';
    });

    $.post('ws_ckin.php', 
      {cmd: 'newConstraint', 
          rid: idReserv, 
          numguests:numGuests, 
          expArr:arrivalDate, 
          expDep:departureDate, 
          idr:idResc, 
          cbRS:cbRS},
      function(data) {
          var newSel;
          
        $('#selResource').prop('disabled', false);
        $('#hhk-roomChsrtitle').removeClass('hhk-loading');
        
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

        
        if (data.selectr) {
            
            newSel = $(data.selectr);
            $('#selResource').children().remove();

            newSel.children().appendTo($('#selResource'));
            $('#selResource').val(data.idResource).change();

            if (data.msg && data.msg !== '') {
                $('#hhkroomMsg').text(data.msg).show();
            }
        }

    });

}
function changePsgPatient(idPsg, idGuest, patientName) {
    "use strict";
    if (!confirm('Change PSG patient to: ' + patientName)) {
        return;
    }
    $.getJSON("ws_ckin.php", {psg: idPsg, gid: idGuest, cmd: 'changePatient'})
        .done(function(data) {
            if (data.error) {
                if (data.gotopage) {
                    window.location.assign(data.gotopage);
                }
                flagAlertMessage(data.error, true);
                return;
            } else if (data.warning) {
                flagAlertMessage(data.warning, false);

            } else if (data.result) {
                $('#divPSGContainer').children().remove();
                $('#divPSGContainer').append($(data.result));
            }
        });
}
