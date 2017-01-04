/**
 * rcheckin.js
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
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
        // define the success  message markup
        $('#alrResponse').removeClass("ui-state-error").addClass("ui-state-highlight");
        $('#alrIcon').removeClass("ui-icon-alert").addClass("ui-icon-info");
        spn.innerHTML = "<strong>Success: </strong>" + mess;
        $("#divAlert1").show("scale horizontal");
        window.scrollTo(0, 5);
    } else {
        // define the error markup
        $('alrResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
        $('#alrIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
        spn.innerHTML = "<strong>Alert: </strong>" + mess;
        $("#divAlert1").show("pulsate");
        window.scrollTo(0, 5);
    }
}
/**
 * 
 * @param {int} idPrefix The prefix of the slot class name.
 * @returns {undefined}
 */
function removeSlot(idPrefix) {
    "use strict";

    if (idPrefix && idPrefix !== '') {

        // Remove the slot
        $('.' + idPrefix + 'Slot').remove();
        // Show new guest count
        countGuests();

    }
}
function removePatient() {
    "use strict";
    var checkIn = chkIn;
    
    for (var i = 0; i < checkIn.members.length; i++) {
        if (checkIn.members[i] && checkIn.members[i].idName === checkIn.patient.idName) {
            checkIn.members.splice(i, 1);
        }
    }

    checkIn.patient = null;
    checkIn.havePatient = false;
    checkIn.patientStaying = null;
    $('div#patientSection').children().remove().end().hide("scale, horizontal");
}
/**
 * 
 * @param {object} data
 * @returns {undefined}
 */
function resvPicker(data) {
    "use strict";
    var faDiag = $("#resDialog");
    var checkIn = chkIn;
    checkIn.role = data.role;
    faDiag.children().remove();
    faDiag.append($(data.resCh));
    
    $('#resDialog :button').button();
    
    $('#resDialog .hhk-checkinNow').click(function () {
        window.open('CheckIn.php?rid=' + $(this).data('rid'), '_self')
    });
    
    faDiag.dialog('open');
}
/**
 * 
 * @param {object} data
 * @returns {String} The prefix of the slot.
 */
function injectSlot(data) {
    "use strict";
    var slot = '';
    if (data.idPrefix) {
        slot = data.idPrefix;
    }
    if (data.memMkup && data.txtHdr) {
        var acDiv = $('<div/>').append($(data.memMkup));
        acDiv.addClass(slot + 'Slot ' + slot + 'detail' + ' ui-widget-content ui-corner-bottom');

        // Header
        var acHdr = $('<div id="' + slot + 'memHdr"/>')
                .append($(data.txtHdr))
                .append($('<span id="' + slot + 'memMsg" style="float:left;color: red; margin-right:20px;margin-left:20px;"></span>'))
                .append($('<span id="' + slot + 'drpDown" class="ui-icon ui-icon-circle-triangle-s" title="Open / Close" style="float:right;"></span>')
                    .click(function() {
                        var disp = acDiv.css('display');
                        if (disp === 'none') {
                            acDiv.show('blind');
                            acHdr.removeClass('ui-corner-all').addClass('ui-corner-top');
                        } else {
                            acDiv.hide('blind');
                            acHdr.removeClass('ui-corner-top').addClass('ui-corner-all');
                        }
                })
        );

        // Remove Button
        if (data.rmvbtn) {
            var removeBtn = $('<input type="button" id="' + slot + 'removeMem" data-slot="' + slot + '" value="Remove" style="float:right;margin-right:.3em;padding: 0.2em 1em;" />');
            removeBtn.addClass('removeMem');
            removeBtn.button();
            acHdr.append(removeBtn);
        }
        
        acHdr.append($('<div style="clear:both;"/>'));
        acHdr.addClass(slot + 'Slot ui-widget-header ui-state-default ui-corner-top').css('padding', '2px').css('min-width', '840px');
        
        $('.' + slot + 'Slot').remove();
        var accdDiv = $('div#guestAccordion');
        
        // patient relationship selector
        var sel = acDiv.find('select#' + slot + 'selPatRel');
        if (sel.val() === 'slf') {
            accdDiv.prepend(acDiv);
            accdDiv.prepend(acHdr);
            acHdr.removeClass('ui-state-default');
        } else {
            accdDiv.append(acHdr);
            accdDiv.append(acDiv);
        }
        
        // Incomplete checkbox
        var incAddr = acDiv.find('input#' + slot + 'incomplete');
        if (incAddr.length > 0) {
            incAddr.change(function() {
                if ($(this).prop('checked')) {
                    $('#' + slot + 'naAddrIcon').show();
                } else {
                    $('#' + slot + 'naAddrIcon').hide();
                }
            });
            incAddr.change();
        } else {
            $('#' + slot + 'naAddrIcon').hide();
        }
        
        // Guest date
        $('div#guestAccordion .ckdate').datepicker();
        
        acDiv.find('select.bfh-countries').each(function() {
            var $countries = $(this);
            $countries.bfhcountries($countries.data());
        });
        acDiv.find('select.bfh-states').each(function() {
            var $states = $(this);
            $states.bfhstates($states.data());
        });
        $('#' + slot + 'phEmlTabs').tabs();
        if (data.idName && data.idName === 0) {
            $('#' + slot + 'phEmlTabs').tabs("option", 'active', 1);
            $('#' + slot + 'phEmlTabs').tabs("option", "disabled", 0);
        }
        sel.change();
        var lastXhr;
        createZipAutoComplete($('input.hhk-zipsearch'), 'ws_admin.php', lastXhr);
        $('#gstprompt').text('Add Guest: ');
//        $('#gstpostPrompt').text('(No additional guests? Press "Done Adding Guests" at the bottom of the screen.)');
    }
    return slot;
}
/**
 * 
 * @param {object} data
 * @returns {undefined}
 */
function ckedIn(data) {
    "use strict";
    $("#divAlert1").hide();
    
    if (data.warning) {
        flagAlertMessage(data.warning, true);
    }
    
    if (data.xfer) {
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
    
    if (data.success) {
        //flagAlertMessage(data.success, false);
        var cDiv = $('#contentDiv');
        cDiv.children().remove();

        if (data.regform && data.style) {
            cDiv.append($('<div id="print_button" style="float:left;">Print</div>'))
                    .append($('<div id="btnReg" style="float:left; margin-left:10px;">Check In Followup</div>'))
                    .append($('<div id="mesgReg" style="color: darkgreen; clear:left; font-size:1.5em;"></div>'))
                    .append($('<div style="clear: left;" class="RegArea"/>')
                            .append($(data.style)).append($(data.regform)));

            $("div#print_button, div#btnReg").button();
            $("div#print_button").click(function() {
                $("div.RegArea").printArea();
            });
            $('div#btnReg').click(function() {
                getRegistrationDialog(data.reg, cDiv);
            });
        }
        
        if (data.ckmeout) {
            var buttons = {
                "Show Statement": function() {
                    window.open('ShowStatement.php?vid=' + data.vid, '_blank');
                },
                "Check Out": function() {
                    saveFees(data.gid, data.vid, 0, true, 'register.php');
                },
                "Cancel": function() {
                    $(this).dialog("close");
                }
            };
            viewVisit(data.gid, data.vid, buttons, 'Check Out', 'co', 0, data.ckmeout);
        }
        
        if (data.regDialog) {
            showRegDialog(data.regDialog, data.reg, cDiv);
        }
        
        if (data.receipt) {
            showReceipt('#pmtRcpt', data.receipt);
        }
    }
}
function offerShare(incmg, ckIn) {
    loadReserv(incmg, ckIn);
}
/**
 * 
 * @param {object} incmg
 * @returns {undefined}
 */
function loadReserv(incmg) {
    "use strict";
    var checkIn = chkIn;
    if (incmg.rid) {
        checkIn.idReserv = parseInt(incmg.rid, 10);
    }
    $('div#guestAccordion').hide();
    
    var okay = processGuests(incmg);
    
    if (incmg.warning && incmg.warning != '') {
        okay = false;
        flagAlertMessage(incmg.warning, true);
    }
    
    if (okay) {
        // Vehicle
        if (incmg.vehicle) {
            $('#vehicle').children().remove().end().append($(incmg.vehicle)).show();
            $('#cbNoVehicle').change(function() {
                if (this.checked) {
                    $('#tblVehicle').hide("blind");
                } else {
                    $('#tblVehicle').show("blind");
                }
                $('#vehValidate').text('');
            });
            $('#cbNoVehicle').change();
            $('#btnNextVeh').button();
            checkIn.nextVeh = 1;
            $('#btnNextVeh').click(function () {
                $('#trVeh' + checkIn.nextVeh).show('fade');
                checkIn.nextVeh++;
                if (checkIn.nextVeh > 4) {
                    $('#btnNextVeh').hide('fade');
                }
            });
        }
        // Room Chooser 
        if (incmg.resc) {
            $('#rescList').children().remove().end().append($(incmg.resc)).show();
            countGuests();
        }
        if (incmg.ratelist) {
            checkIn.rateList = incmg.ratelist;
        }
        // Rate chooser & financial 
        if (incmg.rate) {
            $('#rate').children().remove().end().append($(incmg.rate)).show();
            if ($('#btnFapp').length > 0) {
                $('#btnFapp').button();
                $('#btnFapp').click(function() {
                    getIncomeDiag(checkIn.idReserv);
                });
            }
            setupRates(checkIn, $('#selResource').val());
        }
        // Payment
        if (incmg.pay) {
            $('#pay').children().remove().end().append($(incmg.pay)).show();
            $('#paymentDate').datepicker({
            yearRange: '-1:+01',
            numberOfMonths: 1
            });
            setupPayments(checkIn.resources, $('#selResource'), $('#selRateCategory'));
        }

        checkIn.hideGuests = true;
        $('#btnChkin').show();
        $('#btnDone').hide();
    } else {
        $('#btnChkin').hide();
        if (countGuests() > 0) {
            $('#btnDone').show();
        } else {
            $('#btnDone').hide();
        }
        $('#guestSearch').show();

    }
    $('div#guestAccordion').show("blind");
}
/**
 * 
 * @param {object} incmg
 * @returns {Boolean}
 */
function processGuests(incmg) {
    "use strict";
    var checkIn = chkIn;
    var $hospSection = $('#hospitalSection');

    if (incmg.hvPat !== undefined) {
        checkIn.havePatient = incmg.hvPat;
    }
    if (checkIn.havePatient) {
        $('#patientSearch').hide("blind");
    } else {
        $('#patientSearch').show("blind");
    }

    if (incmg.patStay) {
        checkIn.patientStaying = incmg.patStay;
    }
    
    if (incmg.addRoom) {
        checkIn.addRoom = incmg.addRoom;
    }
    
    if (incmg.idPsg) {
        if (checkIn.idPsg && checkIn.idPsg > 0 && incmg.idPsg > 0 && checkIn.idPsg != incmg.idPsg) {
            flagAlertMessage('Dazed and confused!  Please try again. ', true);
            checkIn.idPsg = 0;
            return false;
        }
        checkIn.idPsg = parseInt(incmg.idPsg, 10);
    }
    // Visit fees array
    if (incmg.vfee) {
        checkIn.visitFees = incmg.vfee;
    }
    
    if (incmg.rooms) {
        checkIn.resources = incmg.rooms;
    }
    
    // count guests
    if (incmg.rcur) {
        checkIn.currentGuests = parseInt(incmg.rcur, 10);
    }
    
    // Maximum room size
    if (incmg.rmax) {
        checkIn.maxRoom = parseInt(incmg.rmax, 10);
    }
    
    if (incmg.guests) {
        for (var i = 0; i < incmg.guests.length; i++) {
            var data = incmg.guests[i];

            // have we this guest already?
            var foundGuest = false;
            
            for (var k = 0; k < checkIn.members.length; k++) {
                
                if (data.idName === 0 && checkIn.members[k].idPrefix == data.idPrefix) {
                    foundGuest = true;
                } else if (data.idName > 0 && checkIn.members[k].idName === data.idName) {
                    foundGuest = true;
                }
            }
            
            if (!foundGuest) {
                // Add guest to the page
                var slot = injectSlot(data);
                checkIn.members.push(new Mpanel(slot, data.idName, data.role, slot));
                if (checkIn.hideGuests) {
                    $('.' + slot + 'detail').hide("scale, horizontal");
                }
            }
        }
        // Room Full?
        roomFull();
    }

    if (incmg.hosp && ($hospSection.children().length === 0 || incmg.patient)) {
        if (incmg.hosp.div) {
            var hDiv = $(incmg.hosp.div).addClass('pgdetail ui-widget-content');
            var hHdr = $('<div id="divHdrHosp" style="padding:2px; cursor:pointer;"/>')
                    .append($(incmg.hosp.hdr))
                    .append($('<span id="drpDown" class="ui-icon ui-icon-circle-triangle-s" style="float:right; margin:right:3px;"></span>')
                    ).append($('<div style="clear:both;"/>'));
            hHdr.addClass('ui-widget-header ui-state-default ui-corner-top');
            hHdr.click(function() {
                var disp = hDiv.css('display');
                if (disp === 'none') {
                    hDiv.show('blind');
                    hHdr.removeClass('ui-corner-all').addClass('ui-corner-top');
                } else {
                    hDiv.hide('blind');
                    hHdr.removeClass('ui-corner-top').addClass('ui-corner-all');
                }
            });
            $('#hospitalSection').children().remove().end().append(hHdr).append(hDiv);
            
            if ($('#selHospital').val() != "") {
                $('#divHdrHosp').click();
            }
            
        } else {
            $('#hospitalSection').children().remove().end().append($(incmg.hosp)).show("blind");        
        }
        
        $('#txtEntryDate, #txtExitDate').datepicker();
        
        var lstXhr;
        createAutoComplete($('#txtAgentSch'), 3, {cmd: 'filter', add: 'phone', basis: 'ra'}, getAgent);
        if ($('#a_txtLastName').val() === '') {
            $('.hhk-agentInfo').hide();
        }
        createAutoComplete($('#txtDocSch'), 3, {cmd: 'filter', basis: 'doc'}, getDoc);
        if ($('#d_txtLastName').val() === '') {
            $('.hhk-docInfo').hide();
        }
    }
    
    // Patient section
    if (incmg.patient && incmg.patient != '') {
        
        // count existing patients
        var cnt = 0;
        $('.patientRelch').each(function(indx) {
            
            if ($(this).val() === 'slf') {
                cnt++;
            }
        });
        
        if (cnt > 0) {
            
            flagAlertMessage("One of the guests is already set as the " + checkIn.patientLabel + ".  ", true);
            checkIn.patient = null;

        } else {
            var patSection = $('div#patientSection'),
                pcDiv = $(incmg.patient),
                pHdr,
                ciHdr,
                inAddr;
        
            pcDiv.addClass('h_detail');
            
            ciHdr = $('<div style="float:left;"/>')
                    .append($('<span style="font-size:1.2em;">' + checkIn.patientLabel + ': </span>'))
                    .append($('<span id="h_hdrFirstName" style="font-size:1.2em;">' + pcDiv.find('#h_txtFirstName').val() + '</span>'))
                    .append($('<span id="h_hdrLastName" style="font-size:1.2em;">' + pcDiv.find('#h_txtLastName').val() + '</span>'));

            if (checkIn.patAsGuest) {
                ciHdr.append($('<span style="font-size:1.2em;"> - Not staying tonight</span>'));
            }
            
            ciHdr.append($('<span id="h_naAddrIcon" class="hhk-icon-redLight" title="Incomplete Address" style="float:right;margin-top:2px;margin-left:5px;"><span>'));
    
            pHdr = $('<div class="ui-widget-header ui-corner-top" style="padding:2px;"/>').append(ciHdr)
            .append($('<span id="h_memMsg" style="color: red; margin-right:20px;margin-left:20px;"></span>'))
                .append($('<span id="h_drpDown" class="ui-icon ui-icon-circle-triangle-s" style="float:right; cursor:pointer; margin:right:3px;"></span>')
                .click(function() {
                    var disp = pcDiv.css('display');
                    if (disp === 'none') {
                        pcDiv.show('blind');
                        pHdr.removeClass('ui-corner-all').addClass('ui-corner-top');
                    } else {
                        pcDiv.hide();
                        pHdr.removeClass('ui-corner-top').addClass('ui-corner-all');
                    }
                })
            );
    
            // Remove Button
            if (incmg.rmvbtnp !== undefined && incmg.rmvbtnp === true && $('#h_Search').length > 0) {

                var removeBtn = $('<input type="button" id="remPatient"  value="Remove" style="float:right;margin-right:.3em;padding: 0.2em 1em;" />');
                removeBtn.click(function () {
                    if (confirm("Remove " + checkIn.patientLabel + "?")) {
                        removePatient();
                        $('#patientSearch').show("blind");
                        $('#hospitalSection').hide("blind");
                        $('#h_Search').focus();
                    }
                });
                removeBtn.button();
                pHdr.append(removeBtn).append($('<div style="clear:both;"/>'));
            }

            patSection.children().remove().end()
                    .removeClass('hhk-panel')
                    .removeClass('hhk-visitdialog')
                    .addClass('ui-widget hhk-panel hhk-visitdialog')
                    .append(pHdr)
                    .append(pcDiv);

            patSection.find('select.bfh-countries').each(function() {
                var $countries = $(this);
                $countries.bfhcountries($countries.data());
            });
            patSection.find('select.bfh-states').each(function() {
                var $states = $(this);
                $states.bfhstates($states.data());
            });
            
            // Incomplete address icon
            inAddr = pcDiv.find('input#h_incomplete');
            if (inAddr.length > 0) {
                
                inAddr.change(function() {
                    if ($(this).prop('checked')) {
                        $('#h_naAddrIcon').show();
                    } else {
                        $('#h_naAddrIcon').hide();
                    }
                });
                
                inAddr.change();
                
            } else {
                $('#h_naAddrIcon').hide();
            }


            patSection.find('#h_phEmlTabs').tabs();

            if (checkIn.idPsg < 1) {
                $('div#patientSection #h_phEmlTabs').tabs("option", "active", 1);
                $('div#patientSection #h_phEmlTabs').tabs("option", "disabled", [0]);
            } else {
                $('#h_drpDown').click();
            }

            var lastXhr;
            createZipAutoComplete($('div#patientSection .hhk-zipsearch'), 'ws_admin.php', lastXhr);

            checkIn.patient = new Mpanel(checkIn.patientPrefix, $('#h_idName').val(), 'p', checkIn.patientPrefix);
            checkIn.members.push(checkIn.patient);

            // Remove Patient from each patient relationship selector
            $('.patientRelch option[value="slf"]').remove();
            $('#hospitalSection').show("blind");
            $('#patientSearch').hide("blind");

            patSection.show('blind');
        }
    }
    
    
    // Occupants;
    if (incmg.stays) {
        $('#stays').children().remove().end().append($(incmg.stays)).show("blind");
    }
    
    if (incmg.adnlrm) {
        $('#addRoom').children().remove().end().append($(incmg.adnlrm)).show("blind");
        $('#cbAddnlRoom').change(function () {
            if ($(this).prop('checked') === true) {
                checkIn.addRoom = true;
            } else {
                checkIn.addRoom = false;
            }
        });
    } else {
        $('#addRoom').children().remove().end().hide("blind");
    }
    
    $('.ckbdate').datepicker({
        yearRange: '-99:+00',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        dateFormat: 'M d, yy'
    });
    return true;
}
/**
 * 
 * @param {object} data
 * @returns {undefined}
 */
function psgChooser(data) {
    "use strict";
    var idGuest = data.idGuest,
        role = data.role;

    $('#psgDialog')
            .children().remove().end().append($(data.choosePsg))
            .dialog('option', 'buttons', {
                Open: function() {
                    var setStaying = false,
                        pid = $('#psgDialog input[name=cbselpsg]:checked').data('pid'),
                        ngid = $('#psgDialog input[name=cbselpsg]:checked').data('ngid');
                
                    chkIn.idPsg = $('#psgDialog input[name=cbselpsg]:checked').val();
                    
                    // Is the patient staying?
                    if ($('#cbpstayy').length > 0 && chkIn.idPsg > 0) {
                        // Have to check yes or no...
                        if ($('#cbpstayy').prop('checked') === false && $('#cbpstayn').prop('checked') === false) {
                            $('#spnstaymsg').text('Choose Yes or No');
                            $('.pstaytd').addClass('ui-state-highlight');
                            return;
                        }
                        
                        setStaying = $('#cbpstayy').prop('checked');
                        
                        if (pid === idGuest) {
                            role = 'p';
                        }
                    }
                    
                    loadGuest(idGuest, chkIn.idPsg, role, setStaying);
                    
                    $('#psgDialog').dialog('close');
                },
                Cancel: function () {
                    $('#' + chkIn.guestSearchPrefix + 'Search').val('');
                    $('#psgDialog').dialog('close');
                }
            })
            .dialog('open');
}
/**
 * 
 * @param {int} id
 * @param {int} idPsg
 * @param {string} role
 * @param {boolean} patientStaying
 * @returns {undefined}
 */
function loadGuest(id, idPsg, role, patientStaying) {
    "use strict";
    var checkIn = chkIn;
    $("#divAlert1").hide();
    
    // Check to make sure the guest is not already loaded into a slot
    if (id > 0) {
        
        // patient?
        if (checkIn.patient !== null && checkIn.patient.idName == id){
            
            if (confirm('The ' + checkIn.patientLabel + ' is already set as NOT staying at the house tonight.  Is the ' + checkIn.patientLabel + ' staying here tonight after all?')) {
                
                removePatient();
                patientStaying = true;
                
            } else {
                return;
            }
        }
        
        for (var i = 0; i < checkIn.members.length; i++) {
            
            if (checkIn.members[i].idName == id) {
                flagAlertMessage('This person is already added.', true);;
                return;
            }
        }
    }

    if (!role || role == '') {
        role = 'g';
    }

    if (patientStaying !== null) {
        checkIn.patientStaying = patientStaying;
    }

    var parms = {
        cmd: 'getMember',
        id: id,
        rid: checkIn.idReserv,
        idPrefix: checkIn.guestPrefix++,
        role: role,
        psg: idPsg,
        patStay: patientStaying,
        hvPat: checkIn.havePatient,
        addRoom: checkIn.addRoom
    };
    var posting = $.post('ws_ckin.php', parms);
    posting.done(function(incmg) {
        if (!incmg) {
            alert('Bad Reply from Server');
            return;
        }
        try {
        incmg = $.parseJSON(incmg);
        } catch (err) {
            alert('Bad JSON Encoding');
            return;
        }
        
        if (incmg.error) {
            if (incmg.gotopage) {
                window.open(incmg.gotopage, '_self');
            }
            // Stop Processing and return.
            flagAlertMessage(incmg.error, true);
            return;
        }
        
        if (incmg.warning) {
            // Don't stop.  Continue processing.
            flagAlertMessage(incmg.warning, true);
        }
        
        var checkIn = chkIn;

        if (incmg.hvPat !== undefined && incmg.hvPat !== null) {
            checkIn.havePatient = incmg.hvPat;
        }
        
        if (incmg.patStay !== undefined && incmg.patStay !== null) {
            checkIn.patientStaying = incmg.patStay;
        }
        
        if (incmg.choosePsg) {
            psgChooser(incmg);
            return;
        }
        
        if (incmg.resCh) {
            checkIn.idGuest = incmg.id;
            checkIn.idPsg = incmg.idPsg;
            checkIn.role = incmg.role;
            resvPicker(incmg);
            return;
        }
        
        if (incmg.rid !== undefined && incmg.rid !== null) {
            checkIn.idReserv = parseInt(incmg.rid);
        }
        
        if (incmg.addr) {
            checkIn.addr = incmg.addr;
        }
        
        processGuests(incmg);
        
        if (incmg.resc) {
            $('#rescList').children().remove().end().append($(incmg.resc)).show();
            if ($('#spnNumGuests').length > 0) {
                $('#spnNumGuests').text(checkIn.members.length - 1 + checkIn.currentGuests);
            }
        }
        
        $('#' + checkIn.guestSearchPrefix + 'Search').val('');
        $('#' + checkIn.patientPrefix + 'Search').val('');

        $('.hhk-info').hide();
        $('#divResvList').hide();
        $('#guestSearch').show();

        if (countGuests() > 0) {
            $('#btnDone').show();
        } else {
            $('#' + checkIn.guestSearchPrefix + 'Search').focus();
        }

    });
}
function countGuests() {
    "use strict";
    var checkIn = chkIn;
    var gCount = checkIn.members.length;
    
    // If patient not staying, decrement counter.
    if (checkIn.patient) {
        gCount--;
    }
    
    if (checkIn.addRoom == false) {
        gCount += checkIn.currentGuests;
    }
    if ($('#spnNumGuests').length > 0) {
        $('#spnNumGuests').text(gCount);
    }
    
    return gCount;
    
}
/**
 * 
 * @returns {undefined}
 */
function roomFull() {
    "use strict";
    var checkIn = chkIn;
    var gCount = countGuests();
       
    if (checkIn.maxRoom > 0 && gCount > checkIn.maxRoom) {
        flagAlertMessage("The room is full.  ", true);
    }
}
/**
 * 
 * @returns {Boolean}
 */
function verifyDone() {
    "use strict";
    var checkIn = chkIn;
    var hospEntryDate;
    var pgCount = 0;
    var latestCkOut, earliestCkIn, emergContactCnt = 0;
    
    // Optional Emergency Contact.
    if (!chkIn.fillEmergCont) {
        emergContactCnt = 2;
    }
    
    if (checkIn.members.length === 0) {
        flagAlertMessage("Enter a Guest or " + checkIn.patientLabel + ".", true);
        $('#' + checkIn.guestSearchPrefix + 'Search').addClass('ui-state-highlight');
        return false;
    }

    // look for guest-patient
    $('.patientRelch').each(function() {
        if ($(this).val() === 'slf') {
            pgCount++;
        }
    });

    if (!checkIn.patient && pgCount === 0 && !checkIn.havePatient) {
        flagAlertMessage("Enter a " + checkIn.patientLabel + ".", true);
        return false;
    }
    
    // check Hospital
    $('#hospitalSection').find('input, select').each(function() {
        $(this).removeClass('ui-state-error');
    });
    if ($('#selHospital').val() === "") {
        $('#selHospital').addClass('ui-state-error');
        flagAlertMessage("Select a hospital.", true);
        $('.pgdetail').show("blind");
        return false;
    }
    // Hospital entry
    var hemsg = '';
    if ($('#txtEntryDate').length > 0 && checkIn.verifyHospDate) {
        if ($('#txtEntryDate').val() === "") {
            // Hospital entry date missing
            $('#txtEntryDate').addClass('ui-state-error');
            hemsg = 'The Treatment Start Date is missing.';
        } else {
            try {
                hospEntryDate = $('#txtEntryDate').datepicker('getDate');
                hospEntryDate.setTime(0, 0, 0);
            } catch (err) {
                hemsg = 'Something is wrong with the Treatment Start Date.';
            }
        }
        if (hemsg !== '') {
            flagAlertMessage(hemsg, true);
            $('.pgdetail').show("blind");
            return false;
        }
    }
    // hide the hospital
    $('.pgdetail').hide("blind");
    
    // Remove guest highlights
    $('#guestAccordion, #patientSection').find('.ui-state-error').each(function() {
        $(this).removeClass('ui-state-error');
    });
    
    // Check for valid guest(s)
    for (var i = 0; i < checkIn.members.length; i++) {
        var pan = checkIn.members[i], ciDate, coDate;
        var gstMsg = $('#' + pan.idPrefix + 'memMsg');
        gstMsg.text("");  // clear any error message
        var isMissing = false;
        var nameText = $('span#' + pan.idPrefix + 'hdrFirstName').text() + ' ' + $('span#' + pan.idPrefix + 'hdrLastName').text();

        // guest first and last name
        if ($('#' + pan.idPrefix + 'txtFirstName').val() === '') {
            $('#' + pan.idPrefix + 'txtFirstName').addClass('ui-state-error');
            isMissing = true;
        }
        if ($('#' + pan.idPrefix + 'txtLastName').val() === '') {
            $('#' + pan.idPrefix + 'txtLastName').addClass('ui-state-error');
            isMissing = true;
        }
        
        if (checkIn.forceNamePrefix && $('#' + pan.idPrefix + 'selPrefix').val() === '') {
            $('#' + pan.idPrefix + 'selPrefix').addClass('ui-state-error');
            isMissing = true;
        }
        
        if (isMissing) {
            flagAlertMessage("Enter a first and last name for each guest & " + checkIn.patientLabel + ".", true);
            $('.' + pan.idSlot + 'detail').show("blind");
            gstMsg.text("Incomplete Name");
            return false;
        }

        // Check patient relationship
        if ($('#' + pan.idPrefix + 'selPatRel').length > 0 && $('#' + pan.idPrefix + 'selPatRel').val() === '') {
            $('#' + pan.idPrefix + 'selPatRel').addClass('ui-state-error');
            gstMsg.text("Set " + checkIn.patientLabel + " Relationship");
            $('.' + pan.idSlot + 'detail').show("blind");
            flagAlertMessage(nameText + " is missing their " + checkIn.patientLabel + " relationship.", true);
            return false;
        }
        
        // validate guest address
        if ($('#' + pan.idPrefix + 'incomplete').prop('checked') === false) {
            $('.' + pan.idPrefix + 'hhk-addr-val').each(function() {
                if ($(this).val() === "") {
                    if (!$(this).hasClass('bfh-states')) {
                        gstMsg.text("Incomplete Address");
                        $(this).addClass('ui-state-error');
                        isMissing = true;
                    }
                }
            });
            if (isMissing) {
                flagAlertMessage(nameText + " is missing some or all of their address.", true);
                $('.' + pan.idSlot + 'detail').show("blind");
                return false;
            }
        }
        
        // Emergency Contact
        if ($('#' + pan.idPrefix + 'cbEmrgLater').length > 0 && $('#' + pan.idPrefix + 'cbEmrgLater').prop('checked') === false && emergContactCnt < 1) {
            // check the emergency contact
            if ($('#' + pan.idPrefix + 'txtEmrgFirst').val() === '' && $('#' + pan.idPrefix + 'txtEmrgLast').val() === '') {
                $('#' + pan.idPrefix + 'txtEmrgFirst').addClass('ui-state-error');
                $('#' + pan.idPrefix + 'txtEmrgLast').addClass('ui-state-error');
                isMissing = true;
            }
            if ($('#' + pan.idPrefix + 'txtEmrgPhn').val() === '') {
                $('#' + pan.idPrefix + 'txtEmrgPhn').addClass('ui-state-error');
                isMissing = true;
            }
            if ($('#' + pan.idPrefix + 'selEmrgRel').val() === '') {
                $('#' + pan.idPrefix + 'selEmrgRel').addClass('ui-state-error');
                isMissing = true;
            }
            if (isMissing) {
                gstMsg.text("Enter emergency contact information");
                flagAlertMessage("Fill in " + nameText + "'s emergency contact information", true);
                $('.' + pan.idSlot + 'detail').show("blind");
                return false;
            }
            emergContactCnt++;
        }
        
        // Check in Date
        if ($('#' + pan.idPrefix + 'gstDate').length > 0) {
            
            if ($('#' + pan.idPrefix + 'gstDate').val() === '') {
                $('#' + pan.idPrefix + 'gstDate').addClass('ui-state-error');
                gstMsg.text("Enter guest check in date.");
                flagAlertMessage(nameText + " is missing their check-in date.", true);
                return false;
            } else {
                ciDate = $('#' + pan.idPrefix + 'gstDate').datepicker("getDate");
                if (ciDate === null) {
                    $('#' + pan.idPrefix + 'gstDate').addClass('ui-state-error');
                    gstMsg.text("Guest check in date error.");
                    flagAlertMessage(nameText + " is missing their check-in date.", true);
                    return false;
                }
                var nowdate = new Date();
                if (ciDate > nowdate) {
                    $('#' + pan.idPrefix + 'gstDate').addClass('ui-state-error');
                    gstMsg.text("The check-in date cannot be in the future.");
                    flagAlertMessage(nameText + " cannot check into the future.  This is not the twilight zone.", true);
                    return false;
                }
                if (!earliestCkIn) {
                    earliestCkIn = ciDate;
                } else if (ciDate < earliestCkIn) {
                    earliestCkIn = ciDate;
                }
            }
            // Check-out date
            if ($('#' + pan.idPrefix + 'gstCoDate').val() !== '') {
                coDate = $('#' + pan.idPrefix + 'gstCoDate').datepicker("getDate");
                if (coDate === null) {
                    $('#' + pan.idPrefix + 'gstCoDate').addClass('ui-state-error');
                    gstMsg.text("The Expected Departure date error.");
                    flagAlertMessage(nameText + " is missing their Expected Departure date.", true);
                    return false;
                }
                // Keep the latest check out date
                if (!latestCkOut) {
                    latestCkOut = coDate;
                } else if (coDate > latestCkOut) {
                    latestCkOut = coDate;
                }
    //            if (hospExitDate && coDate > hospExitDate) {
    //                $('#' + pan.idPrefix + 'gstCoDate').addClass('ui-state-error');
    //                gstMsg.text("Expected Departure date is past hospital exit date.");
    //                flagAlertMessage(nameText + " cannot expect to depart later than the hospital exit date.", true, gstMsg.offset().top);
    //                return false;
    //            }
                if (ciDate > coDate) {
                    $('#' + pan.idPrefix + 'gstDate').addClass('ui-state-error');
                    gstMsg.text("Check in date is after check out date.");
                    flagAlertMessage(nameText + "  check in date is after their expected departure date.  We cannot reverse time (yet).", true);
                    return false;
                }
            }
        }
        
        $('div.' + pan.idPrefix + 'detail').hide('blind');
    }

    if (!latestCkOut) {
        flagAlertMessage("Expected departure date not set", true);
        $('input.gstchkoutdate').addClass('ui-state-error');
        return false;
    } else {
        checkIn.resv.checkOut = latestCkOut;
    }
    if (!earliestCkIn) {
        flagAlertMessage("Check in date not set", true);
        $('input.gstdate').addClass('ui-state-error');
        return false;
    } else {
        checkIn.resv.checkIn = earliestCkIn;
    }
    return true;
}
/**
 * 
 * @param {int} idReg
 * @param {jquery} cDiv
 * @returns {undefined}
 */
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
            flagAlertMessage(data.error, true);
            return;
        } else if (data.success) {
            showRegDialog(data.success, idReg, cDiv);
        }
    }
    );
}
/**
 * 
 * @param {string} markup
 * @param {int} idReg
 * @param {jquery} container
 * @returns {undefined}
 */
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

/**
 * 
 * @param {string} idPrefix
 * @param {int} idName
 * @param {string} role
 * @param {string} idSlot
 * @returns {undefined}
 */
function Mpanel(idPrefix, idName, role, idSlot) {
    "use strict";
    var t = this;
    t.idPrefix = idPrefix;
    t.idName = idName;
    t.role = role;
    t.idSlot = idSlot;
}

function CheckIn() {
     "use strict";
   var t = this;
    t.patientPrefix = 'h_';
    t.patient = null;
    t.idPsg = 0;
    t.currentGuests = 0;
    t.guestSearchPrefix = 'gst';
    t.guestPrefix;
    t.adrPurpose = '1';
    t.resv;
    t.resources;
    t.maxRoom;
    t.textInfo;
    t.rateList;
    t.ppnl;
    t.gpnl;
    t.idReserv;
    t.members;
    t.hideGuests = false;
    t.idGuest;
    t.role;
    t.visitFees;
    t.patAsGuest;
    t.patientStaying = null;
    t.havePatient = false;
    t.verifyHospDate;
    t.addRoom = false;
}

/**
 * 
 * @param {object} data
 * @returns {undefined}
 */
function gotMember(data) {
    "use strict";
    var checkIn = chkIn;
    $('#btnDone').val('Done Adding Guests');
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
        // Stop Processing and return.
        flagAlertMessage(data.error, true);
        $('form#form1').remove();
        return;
    }
    if (data.ofrShr) {
        // Offer a share room to extra guest
        offerShare(data, checkIn);
        return;
    }
    $('div#guestAccordion').children().remove();
    if (data.ovld) {
        // Max room size exceeded.
        flagAlertMessage(data.ovld, true);
        $('#btnChkin, #btnDone').hide();
        return;
    }
    if (data.warning && data.warning != '') {
        // Don't stop.  Continue processing.
        flagAlertMessage(data.warning, true);
    }
    checkIn.hideGuests = true;
    loadReserv(data);
}

function reservation() {
    "use strict";
    var t = this;
    t.arrive;
    t.depart;
    t.checkIn;  // Date
    t.checkOut; // Date
    t.adults;
    t.children;
    t.resource;
    t.txtStart;
    t.txtEnd;
}
/**
 * 
 * @param {string} cnum
 * @returns {String}
 */
function validateCar(cnum) {
    "use strict";
    var err = '';
    if ($('#car' + cnum + 'txtVehLic').val() === '' && $('#car' + cnum + 'txtVehMake').val() === '') {
        return "Enter vehicle info or check the 'No Vehicle' checkbox. ";
    }
    if ($('#car' + cnum + 'txtVehLic').val() === '') {
        if ($('#car' + cnum + 'txtVehModel').val() === '') {
            $('#car' + cnum + 'txtVehModel').addClass('ui-state-highlight');
            err = 'Enter Model';
        }
        if ($('#car' + cnum + 'txtVehColor').val() === '') {
            $('#car' + cnum + 'txtVehColor').addClass('ui-state-highlight');
            err = 'Enter Color';
        }
        if ($('#car' + cnum + 'selVehLicense').val() === '') {
            $('#car' + cnum + 'selVehLicense').addClass('ui-state-highlight');
            err = 'Enter state license plate registration';
        }
    } else if ($('#car' + cnum + 'txtVehMake').val() === '') {
        if ($('#car' + cnum + 'txtVehLic').val() === '') {
            $('#car' + cnum + 'txtVehLic').addClass('ui-state-highlight');
            err = 'Enter a license plate number.';
        }
    }
    return err;
}
/**
 * 
 * @param {object} item Autocomplete object.
 * @returns {undefined}
 */
function getECRel(item) {
    "use strict";
    $('#ecSearch').dialog('close');
    var cid = parseInt(item.id, 10);
    if (isNaN(cid) === false && cid > 0) {
        var prefix = $('#hdnEcSchPrefix').val();
        if (prefix == '') {
            return;
        }
        $('#' + prefix + 'txtEmrgFirst').val(item.first);
        $('#' + prefix + 'txtEmrgLast').val(item.last);
        $('#' + prefix + 'txtEmrgPhn').val(item.phone);
        $('#' + prefix + 'txtEmrgAlt').val('');
        $('#' + prefix + 'selEmrgRel').val('');
    }
}

$(document).ready(function() {
    "use strict";
    var lastXhr;
    var checkIn = chkIn;
    var postBackPage = postBkPg;

    
    // Unsaved changes on form are caught here.
    $(window).bind('beforeunload', function() {
        var isDirty = false;
        $('#guestAccordion').find("input[type='text']").not(".ignrSave").each(function() {
            if ($(this).val() !== $(this).prop("defaultValue")) {
                isDirty = true;
            }
        });
        if (isDirty === true) {
            return 'You have unsaved changes.';
        }
    });
    $.ajaxSetup({
        beforeSend: function() {
            $('body').css('cursor', "wait");
        },
        complete: function() {
            $('body').css('cursor', "auto");
        },
        cache: false
    });
    $('#ajaxError').ajaxError(function(event, jqXHR, ajaxSettings) {
        flagAlertMessage('AJAX Error', true);
    });
    // put the content beneth the menu.
    $('#contentDiv').css('margin-top', $('#global-nav').css('height'));
    
    $(':input[type="button"]').button();
    
    // Delete unfinished checkins
    if ($('#btnDelUnfinished').length !== 0) {
        $('#btnDelUnfinished').click(function () {
            $.post('ws_ckin.php', { cmd: 'dunf' }, function(data) {
                $('#divUnfinished').hide();
            });
        });
    }
    $.extend($.fn.dataTable.defaults, {
        "dom": '<"top"if>rt<"bottom"lp><"clear">',
        "iDisplayLength": 25,
        "aLengthMenu": [[25, 50, -1], [25, 50, "All"]],
        "order": [[ 4, 'asc' ]]
    });

    $('#atblgetter, #stblgetter').DataTable();
    $.datepicker.setDefaults({
        yearRange: '-5:+02',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        numberOfMonths: 2,
        dateFormat: 'M d, yy'
    });
    $("#psgDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: 400,
        modal: true,
        title: checkIn.patientLabel + ' Selection'
    });
    $("#resDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: 900,
        modal: true,
        title: 'Reservtion Chooser',
        buttons: {
            "Exit": function() {
                $('#' + checkIn.patientPrefix + 'Search').val('');
                $('#' + checkIn.guestSearchPrefix + 'Search').val('');                
                $(this).dialog("close");
            }
        }
    });
    $("#ecSearch").dialog({
        autoOpen: false,
        resizable: false,
        width: 300,
        title: 'Emergency Contact',
        modal: true,
        buttons: {
            "Exit": function() {
                $(this).dialog("close");
            }
        }
    });
    $('#pmtRcpt').dialog({
        autoOpen: false,
        resizable: true,
        width: 530,
        modal: true,
        title: 'Payment Receipt'
    });
    $('#patientPrompt').dialog({
        autoOpen: false,
        resizable: true,
        width: 470,
        modal: true,
        title: ''
    });
    $('.ckdate').datepicker();
    $("#faDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: 650,
        modal: true,
        title: 'Income Chooser',
        close: function (event, ui) {$('div#submitButtons').show();},
        open: function (event, ui) {$('div#submitButtons').hide();},
        buttons: {
            Save: function() {
                $.post('ws_ckin.php', $('#formf').serialize() + '&cmd=savefap' + '&rid=' + checkIn.idReserv, function(data) {
                    try {
                        data = $.parseJSON(data);
                    } catch (err) {
                        alert('Bad JSON Encoding');
                        return;
                    }
                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }
                    if (data.rstat && data.rstat == true) {
                        if (data.rcat && data.rcat != '' && $('#selRateCategory').length > 0) {
                            $('#selRateCategory').val(data.rcat);
                        }
                    }
                });
                $(this).dialog("close");
            },
            "Exit": function() {
                $(this).dialog("close");
            }
        }
    });
    
    $('div#guestAccordion').on('click', 'input.removeMem', function() {
        var nme = $(this).data('slot');
        if (nme != checkIn.patientPrefix) {
            
            for (var i = 0; i < checkIn.members.length; i++) {
                var pan = checkIn.members[i];
                if (pan && pan.idPrefix == nme) {
                    checkIn.members.splice(i, 1);
                    if ($('#' + pan.idPrefix + 'selPatRel').val() == 'slf') {
                        checkIn.patientStaying = null;
                        checkIn.havePatient = false;
                    }
                }
            }
            
            removeSlot(nme);
            $('.patientRelch').change();
            $('#gstspan').show();
            if (checkIn.members.length < 2) {
                $('#gstprompt').text('Add Guest: ');
                $('#gstpostPrompt').text('');
            }
        }
    });
    
    $('div#guestAccordion, div#patientSection').on('click', '.hhk-addrCopy', function() {

        var prefix = $(this).attr('name');
        
        if (checkIn.addr && checkIn.addr.adraddress1 != '' && $('#' + prefix + 'adraddress1' + checkIn.adrPurpose).val() != checkIn.addr.adraddress1) {
            $('#' + prefix + 'adraddress1' + checkIn.adrPurpose).val(checkIn.addr.adraddress1);
            $('#' + prefix + 'adraddress2' + checkIn.adrPurpose).val(checkIn.addr.adraddress2);
            $('#' + prefix + 'adrcity' + checkIn.adrPurpose).val(checkIn.addr.adrcity);
            $('#' + prefix + 'adrcounty' + checkIn.adrPurpose).val(checkIn.addr.adrcounty);
            $('#' + prefix + 'adrstate' + checkIn.adrPurpose).val(checkIn.addr.adrstate);
            $('#' + prefix + 'adrcountry' + checkIn.adrPurpose).val(checkIn.addr.adrcountry);
            $('#' + prefix + 'adrzip' + checkIn.adrPurpose).val(checkIn.addr.adrzip);
            return;
        }
        
        for (var i = 0; i < checkIn.members.length; i++) {
            
            if (checkIn.members[i] && checkIn.members[i].idPrefix !== prefix && $('#' + checkIn.members[i].idPrefix + 'adraddress1' + checkIn.adrPurpose).val() != '') {

                $('#' + prefix + 'adraddress1' + checkIn.adrPurpose).val($('#' + checkIn.members[i].idPrefix + 'adraddress1' + checkIn.adrPurpose).val());
                $('#' + prefix + 'adraddress2' + checkIn.adrPurpose).val($('#' + checkIn.members[i].idPrefix + 'adraddress2' + checkIn.adrPurpose).val());
                $('#' + prefix + 'adrcity' + checkIn.adrPurpose).val($('#' + checkIn.members[i].idPrefix + 'adrcity' + checkIn.adrPurpose).val());
                $('#' + prefix + 'adrcounty' + checkIn.adrPurpose).val($('#' + checkIn.members[i].idPrefix + 'adrcounty' + checkIn.adrPurpose).val());
                $('#' + prefix + 'adrstate' + checkIn.adrPurpose).val($('#' + checkIn.members[i].idPrefix + 'adrstate' + checkIn.adrPurpose).val());
                $('#' + prefix + 'adrcountry' + checkIn.adrPurpose).val($('#' + checkIn.members[i].idPrefix + 'adrcountry' + checkIn.adrPurpose).val());
                $('#' + prefix + 'adrzip' + checkIn.adrPurpose).val($('#' + checkIn.members[i].idPrefix + 'adrzip' + checkIn.adrPurpose).val());
                
                return;
            }
        }

    });

    $('div#guestAccordion, div#patientSection').on('click', '.hhk-addrErase', function() {
        var prefix = $(this).attr('name');
        $('#' + prefix + 'adraddress1' + checkIn.adrPurpose).val('');
        $('#' + prefix + 'adraddress2' + checkIn.adrPurpose).val('');
        $('#' + prefix + 'adrcity' + checkIn.adrPurpose).val('');
        $('#' + prefix + 'adrcounty' + checkIn.adrPurpose).val('');
        $('#' + prefix + 'adrcountry' + checkIn.adrPurpose).val('');
        $('#' + prefix + 'adrstate' + checkIn.adrPurpose).val('');
        $('#' + prefix + 'adrzip' + checkIn.adrPurpose).val('');
        $('#' + prefix + 'adrbad' + checkIn.adrPurpose).prop('checked', false);
    });
    
    $('div#guestAccordion').on('click', '.hhk-guestSearch', function() {
        $('#hdnEcSchPrefix').val($(this).attr('name'));
        $('#ecSearch').dialog('open');
    });
    
    $('div#guestAccordion').on('change', '.patientRelch', function() {
        var cnt = 0;
        $("#divAlert1").hide();
        checkIn.patientStaying = null;
        if (checkIn.patient !== null && checkIn.patient !== undefined) {
            checkIn.patientStaying = false;
            cnt++;
        }
        
        $('.patientRelch').each(function(indx) {
            
            if ($(this).val() === 'slf') {
                cnt++;
                if (cnt > 1) {
                    $(this).val('');
                    flagAlertMessage('Only one guest can also be the ' + checkIn.patientLabel + '.', true);
                } else {
                    $('div#hospitalSection').show("blind");
                    checkIn.patientStaying = true;

                    $('#' + $(this).data('prefix') + 'memHdr').removeClass('ui-state-default');
                    $('div#patientSection').hide("blind");
                    $('div#patientSearch').hide("blind");
                    $('span#' + $(this).data('prefix') + 'spnHdrLabel').text(checkIn.patientLabel);
                    
                }
            } else {
                $('#' + $(this).data('prefix') + 'memHdr').addClass('ui-state-default');
                $('span#' + $(this).data('prefix') + 'spnHdrLabel').text('Guest: ');
            }
        });
        
        if (cnt === 0) {
            $('div#patientSearch').show("blind");
            $('div#hospitalSection').hide("blind");
        }

    });
    
    $('div#guestAccordion, div#patientSection').on('change', 'input.hhk-lastname', function() {
        $('span#' + $(this).data('prefix') + 'hdrLastName').text(' ' + $(this).val());
    });
    
    $('div#guestAccordion, div#patientSection').on('change', 'input.hhk-firstname', function() {
        $('span#' + $(this).data('prefix') + 'hdrFirstName').text(' ' + $(this).val());
    });
    
    $('div#hospitalSection').on('click', '.hhk-agentSearch, .hhk-docSearch', function() {
        $('#txtAgentSch').val('');
        $('#txtDocSch').val('');
    });
    
    $('div#hospitalSection').on('change', '#selHospital, #selAssoc', function() {
        var hosp = $('#selAssoc').find('option:selected').text();
        if (hosp != '') {
            hosp += '/ ';
        }
        $('span#spnHospName').text(hosp + $('#selHospital').find('option:selected').text());
    });
    
    verifyAddrs('div#guestAccordion, div#hospitalSection, div#patientSection');
    
    $('#btnDone').click(function() {
        $("#divAlert1").hide();
        if ($(this).val() === 'Saving >>>>') {
            return;
        }
        if (verifyDone() === true) {
            var mbrs = '';
            var checkIn = chkIn;
            for (var i = 0; i < checkIn.members.length; i++) {
                mbrs += '&mbrs[]=' + checkIn.members[i].idPrefix;
            }

            checkIn.resv.checkOut.setHours(10);
            var nowDate = new Date();
            checkIn.resv.checkIn.setHours(nowDate.getHours());
            checkIn.resv.checkIn.setMinutes(nowDate.getMinutes());
            mbrs += '&ckindt=' + checkIn.resv.checkIn.toJSON();
            mbrs += '&ckoutdt=' + checkIn.resv.checkOut.toJSON(); 
            mbrs += '&rid=' + checkIn.idReserv;
            mbrs += '&psgId=' + checkIn.idPsg
            mbrs += '&addRoom=' + checkIn.addRoom;
            checkIn.members = [];
            checkIn.patient = null;
            $.post('ws_ckin.php', $('#form1').serialize() + mbrs + '&cmd=saveMem', function(data) {
                gotMember(data);
            });
            $(this).val('Saving >>>>');
            $('#guestSearch').hide();
            $('#rescList').hide();
        }
    });
    
    $('#btnChkin').click(function(event) {
        $("#divAlert1").hide();
        if ($(this).val() === 'Saving >>>>') {
            return;
        }
        $('#payChooserMsg').text("").hide('fade');
        if (verifyDone() === false) {
            return;
        }
        // vehicle
        if ($('#cbNoVehicle').length > 0) {
            if ($('#cbNoVehicle').prop("checked") === false) {
                var carVal = validateCar(1);
                if (carVal != '') {
                    var carVal2 = validateCar(2);
                    if (carVal2 != '') {
                        $('#vehValidate').text(carVal2);
                        flagAlertMessage(carVal, true);
                        return;
                    }
                }
            }
            $('#vehValidate').text('');
        }
        if ($('#selResource').length > 0 && ($('#selResource').val() === '' || $('#selResource').val() === '0')) {
            flagAlertMessage("Choose a room.", true);
            $('#selResource').addClass('ui-state-error');
            return;
        } else {
            $('#selResource').removeClass('ui-state-error');
        }
        // Room rate
        if ($('#selCategory').val() == 'x' && $('#txtFixedRate').length > 0 && $('#txtFixedRate').val() == '') {
            flagAlertMessage("Set the Room Rate to an amount, or 0.", true);
            $('#txtRoomRate').addClass('ui-state-error');
            return;
        } else {
            $('#txtRoomRate').removeClass('ui-state-error');
        }
        // Room fees paid
        if ($('input#feesPayment').length > 0 && $('input#feesPayment').val() == '') {
            //flagAlertMessage("Set the Room Fees to an amount, or 0.", true);
            $('#payChooserMsg').text("Set the Room Fees to an amount, or 0.").show('fade');
            $('input#feesPayment').addClass('ui-state-error');
            return;
        } else {
            $('input#feesPayment').removeClass('ui-state-error');
        }
        
        // Verify cash amount tendered
        if (verifyAmtTendrd !== undefined && verifyAmtTendrd() === false) {
            return;
        }
        
        // Dates
        checkIn.resv.checkOut.setHours(10);
        var nowDate = new Date();
        checkIn.resv.checkIn.setHours(nowDate.getHours());
        checkIn.resv.checkIn.setMinutes(nowDate.getMinutes());

        var mbrs = '';
        for (var i = 0; i < checkIn.members.length; i++) {
            mbrs += '&mbrs[]=' + checkIn.members[i].idPrefix;
        }
        mbrs += '&ckindt=' + checkIn.resv.checkIn.toJSON();
        mbrs += '&ckoutdt=' + checkIn.resv.checkOut.toJSON();            
        mbrs += '&rid=' + checkIn.idReserv;
        mbrs += '&hvPat=' + checkIn.havePatient;
        mbrs += '&addRoom=' + checkIn.addRoom;

        $.post('ws_ckin.php', $('#form1').serialize() + mbrs + '&cmd=savePage' + '&pbp=' + postBackPage, function(data) {
            var checkIn = chkIn;
            $('#btnChkin').val('Check In');
            try {
                data = $.parseJSON(data);
            } catch (err) {
                alert('Bad JSON Encoding');
                var cDiv = $('#contentDiv');
                cDiv.children().remove();
                checkIn = null;
                return;
            }
            if (data.error) {
                // Stop Processing and return.
                if (data.gotopage) {
                    window.open(data.gotopage, '_self');
                }
                flagAlertMessage(data.error, true);
                return;
            }
            if (data.warning) {
                // Don't stop.  Continue processing.
                flagAlertMessage(data.warning, true);
            }
            ckedIn(data);
        });
        $(this).val('Saving >>>>');
    });
    
    $('#hhk-confResvHdr').click(function () {
        $('#hhk-confResv').toggle('blind');
    });
    $('#hhk-chkedInHdr').click(function () {
        $('#hhk-chkedIn').toggle('blind');
    });
    
    createAutoComplete($('#txtRelSch'), 3, {cmd: 'filter', add: 'phone', basis: 'g'}, getECRel, lastXhr);
    
    createAutoComplete($('#' + checkIn.guestSearchPrefix + 'Search'), 3, {cmd: 'role'}, function (item) {
            loadGuest(item.id, checkIn.idPsg, 'g', checkIn.patientStaying);
        });
    createAutoComplete($('#' + checkIn.guestSearchPrefix + 'phSearch'), 5, {cmd: 'role'}, function (item) {
            loadGuest(item.id, checkIn.idPsg, 'g', checkIn.patientStaying);
        });
        
    function getPatient(item) {
        if (item.id > 0) {
            for (var i = 0; i < checkIn.members.length; i++) {
                var pan = checkIn.members[i];
                if (pan.idName === item.id) {
                    flagAlertMessage('This person is already added.  Use the ' + checkIn.patientLabel + ' Relationship selector to select "' + checkIn.patientLabel + '".', true);
                    return;
                }
            }
        }
        if (checkIn.patAsGuest && checkIn.patientStaying === null) {
            $('#patientPrompt')
                .dialog('option', 'buttons', {
                    Yes: function() {
                        loadGuest(item.id, checkIn.idPsg, 'p', true);
                        $('#patientPrompt').dialog('close');
                    },
                    No: function () {
                        loadGuest(item.id, checkIn.idPsg, 'p', false);
                        $('#patientPrompt').dialog('close');
                    }
                })
                .dialog('open');
        } else {
            loadGuest(item.id, checkIn.idPsg, 'p', checkIn.patientStaying);
        }
    }
    
    createAutoComplete($('#' + checkIn.patientPrefix + 'Search'), 3, {cmd: 'role'}, getPatient);
    createAutoComplete($('#' + checkIn.patientPrefix + 'phSearch'), 5, {cmd: 'role'}, getPatient);
    
    $('#' + checkIn.guestSearchPrefix + 'Search').keypress(function(event) {
        $(this).removeClass('ui-state-highlight');
    });

    if (checkIn.gpnl !== '') {
        loadGuest(checkIn.gpnl, checkIn.idPsg, 'g', checkIn.patientStaying);
    }
    if (checkIn.ppnl !== '') {
        loadGuest(checkIn.ppnl, checkIn.idPsg, 'p', checkIn.patientStaying);
    }
    if (checkIn.idReserv > 0) {
        loadGuest(checkIn.addGuestId, 0, 'r', checkIn.patientStaying);
    }
});