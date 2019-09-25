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
    } else {
        $('#a_txtFirstName').val('');
        $('#a_txtLastName').val('');
        $('#a_txtPhonemc').val('');
        $('#a_txtPhonegw').val('');
        $('#a_txtEmail1').val('');
        $('#a_idName').val('0');
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
    } else {
        $('#d_txtFirstName').val('');
        $('#d_txtLastName').val('');
        $('#d_idName').val('0');
    }
    $('.hhk-docInfo').show();
}
function gotIncomeDiag(idResv, idReg, data) {
    if (data.error) {
        if (data.gotopage) {
            window.location.assign(data.gotopage);
        }
        flagAlertMessage(data.error, 'error');
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
                        flagAlertMessage(data.error, 'error');
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

function setupRates(ckIn) {
    "use strict";
    var $selRateCat = $('#selRateCategory');
    var $selResource = $('#selResource');
    var $selVisitFee = $('#selVisitFee');

    if ($selVisitFee.length > 0) {

        $selVisitFee.change(function() {
            
            $selRateCat.change();
            
            if ($('#visitFeeCb').length > 0) {
                // update the visit fee
                var amt = parseFloat(ckIn.visitFees[$(this).val()][2]);
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

        $selVisitFee.change();
    }

    $('#txtFixedRate').change(function() {

        if ($selRateCat.val() === fixedRate) {

            var amt = parseFloat($(this).val()),
                fa = 0,
                taxAmt = 0,
                total,
                lodging,
                days = parseInt($('#spnNites').text(), 10),
                tax = parseFloat($('#spnRcTax').data('tax'));

            if (isNaN(days)) {
                days = 0;
            }

            if (isNaN(tax)) {
                tax = 0;
            }
            if (isNaN(amt) || amt < 0) {
                amt = parseFloat($(this).prop("defaultValue"));
                if (isNaN(amt) || amt < 0)
                    amt = 0;
                $(this).val(amt);
            }

            if ($selResource.length > 0 && ckIn.resources[$selResource.val()]) {
                ckIn.resources[$selResource.val()].rate = amt;
            }

            if ($selVisitFee.length > 0) {
                fa = parseFloat(ckIn.visitFees[$selVisitFee.val()][2]);
                if (isNaN(fa) || fa < 0) {
                    fa = 0;
                }
            }

            lodging = amt * days;
            $('#spnLodging').text('$' + lodging.toFixed(2).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","));
            
            if (tax > 0) {
                taxAmt = amt * tax;
                $('#spnRcTax').text('$' + taxAmt.toFixed(2).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","));
            }
            total = (amt * days) + fa + taxAmt;
            $('#spnAmount').text('$' + total.toFixed(2).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","));
        }
    });

    $('#txtadjAmount').change(function () {

        if ($selRateCat.val() !== fixedRate) {

            var adj = parseFloat($(this).val()),
                fa = 0,
                taxAmt = 0,
                days = parseInt($('#spnNites').text(), 10),
                tax = parseFloat($('#spnRcTax').data('tax'));

            if (isNaN(days)) {
                days = 0;
            }
            if (isNaN(tax)) {
                tax = 0;
            }

            if (isNaN(adj)) {

                adj = parseFloat($(this).prop("defaultValue"));

                if (isNaN(adj)) {
                    adj = 0;
                }
                $(this).val(adj);
            }

            if ($selVisitFee.length > 0) {

                fa = parseFloat(ckIn.visitFees[$selVisitFee.val()][2]);

                if (isNaN(fa) || fa < 0) {
                    fa = 0;
                }
            }

            daysCalculator(days, $selRateCat.val(), 0, 0, adj, parseInt($('#spnNumGuests').text()), (ckIn.idResv === undefined ? 0 : ckIn.idResv), function(amt) {

                $('#spnLodging').text('$' + amt.toFixed(2).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","));
                
                if (tax > 0) {
                    taxAmt = amt * tax;
                    $('#spnRcTax').text('$' + taxAmt.toFixed(2).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","));
                }
                amt += fa + taxAmt;
                $('#spnAmount').text('$' + amt.toFixed(2).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","));
            });

        }
    });

    $selRateCat.change(function () {

        if ($(this).val() === fixedRate) {

            $('.hhk-fxAdj').hide();
            $('.hhk-fxFixed').show();
            
            if (ckIn.resources[$selResource.val()]) {
                $('#txtFixedRate').val(ckIn.resources[$selResource.val()].rate);
            }
        } else {
            
            $('.hhk-fxFixed').hide();
            $('.hhk-fxAdj').show();
        }

        $('#txtFixedRate').change();
        $('#txtadjAmount').change();
        
        if (ckIn.resources[$selResource.val()].key > 0) {
            $('#spnDepAmt').text('($'+ckIn.resources[$selResource.val()].key+')');
            $('#hdnKeyDepAmt').val(ckIn.resources[$selResource.val()].key);
            $('.hhk-kdrow').show();
            $('#keyDepRx').change();
        } else {
            $('#spnDepAmt').text('');
            $('#hdnKeyDepAmt').val(0);
            $('#keyDepAmt').val('');
            $('.hhk-kdrow').hide();
            $('#keyDepRx')
                    .prop('checked', false)
                    .change();
        }
        
    });

    $selRateCat.change();
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
                flagAlertMessage(data.error, 'error');
                return;
            } else if (data.warning) {
                flagAlertMessage(data.warning, 'warning');

            } else if (data.result) {
                $('#divPSGContainer').children().remove();
                $('#divPSGContainer').append($(data.result));
            }
        });
}

function getRegistrationDialog(idReg, cDiv) {
    "use strict";
    $.post(
            'ws_ckin.php',
            {cmd: 'getReg',
                reg: idReg},
    function(data) {
        if (!data) {
            alert('Bad Reply from Server');
            return;
        }
        try {
            data = $.parseJSON(data);
        } catch (err) {
            alert('Bad JSON Encoding');
            return;
        }
        if (data.error) {
            if (data.gotopage) {
                window.open(data.gotopage, '_self');
            }
            flagAlertMessage(data.error, 'error');
            return;
        } else if (data.success) {
            showRegDialog(data.success, idReg, cDiv);
        }
    }
    );
}

function showRegDialog(markup, idReg, container) {
    "use strict";
    var regDialog = $('<div id="regDialog" />').append($(markup));
    container.append(regDialog);
    $('#regDialog').dialog({
        autoOpen: true,
        width: 360,
        resizable: true,
        modal: true,
        title: 'Registration Info',
        buttons: {
            "Cancel": function() {
                $(this).dialog("close");
            },
            "Save": function() {
                var parms = {};
                $('.hhk-regvalue').each(function() {
                    if ($(this).attr('type') === 'checkbox') {
                        if (this.checked !== false) {
                            parms[$(this).attr('name')] = 'on';
                        }
                    } else {
                        parms[$(this).attr('name')] = this.value;
                    }
                });
                $(this).dialog("close");
                $.post('ws_ckin.php',
                        {cmd: 'saveReg',
                            reg: idReg,
                            parm: parms},
                function(data) {
                    if (!data) {
                        alert('Bad Reply from Server');
                        return;
                    }
                    try {
                        data = $.parseJSON(data);
                    } catch (err) {
                        alert('Bad JSON Encoding');
                        return;
                    }
                    if (data.error) {
                        if (data.gotopage) {
                            window.open(data.gotopage, '_self');
                        }
                        alert(data.error);
                        return;
                    } else if (data.success) {
                        $('#mesgReg').text(data.success);
                    }
                });
            }
        }
    });
}

