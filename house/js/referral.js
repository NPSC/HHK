/* global reserv */

/**
 * referral.js
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * 
 * @param {object} item
 * @returns {undefined}
 */
function additionalGuest(item) {
    "use strict";
    var resv = reserv;
    hideAlertMessage();
    if (item.id > 0) {
        for (var i = 0; i < resv.members.length; i++) {
            var pan = resv.members[i];
            if (pan.idName == item.id && pan.idPrefix == resv.patientPrefix && resv.patStaying == true) {
                flagAlertMessage('This guest is already added.', true);
                return;
            }
        }
    }
    if (confirm("Add " + item.value + "?")) {
        
        resv.addRoom = false;
        
        if ($('#cbAddnlRoom').prop('checked')) {
            resv.addRoom = true;
        }
        
        var parms = {
            cmd: 'addResv',
            id: item.id,
            rid: resv.idReserv,
            addRoom: resv.addRoom
        };
        
        $.post('ws_ckin.php',parms,
        function(data) {
            
            "use strict";
            var resv = reserv;
            try {
                data = $.parseJSON(data);
            } catch (err) {
                alert("Parser error - " + err.message);
                return;
            }
            
            if (!data) {
                alert('Bad Reply from Server');
                return;
            }
            
            if (data.error) {
                if (data.gotopage) {
                    window.open(data.gotopage, '_self');
                }
                flagAlertMessage(data.error, true);
                return;
            }
            
            $('#txtAddGuest').val('');
            if (data.newRoom && data.newRoom > 1) {
                flagAlertMessage('<a href="Referral.php?rid=' + data.newRoom + '">View New Reservation</a>', false);
                return;
            }
            
            if (data.addr) {
                resv.addr = data.addr;
            }
            
            if (data.addtguest && data.addtguest !== '') {
                
                $('#diagAddGuest').remove();
                // create a dialog and show the form.
                var acDiv = $(data.addtguest);
                acDiv.css('font-size', '.85em');
                
                acDiv.dialog({
                    autoOpen: false,
                    resizable: true,
                    width: 1100,
                    modal: true,
                    title: 'Additional Guest',
                    close: function (event, ui) {$('div#submitButtons').show();},
                    open: function (event, ui) {$('div#submitButtons').hide();},
                    buttons: {
                        Save: function() {
                            var isMissing = false;
                            
                            $('#adgstMsg').text('');
                            
                            // Check patient relationship
                            if ($('#bselPatRel').val() == '') {
                                $('#bselPatRel').addClass('ui-state-error');
                                isMissing = true;
                            } else {
                                $('#bselPatRel').removeClass('ui-state-error');
                            }
                            // guest first and last name
                            if ($('#btxtFirstName').val() == '') {
                                $('#btxtFirstName').addClass('ui-state-error');
                                isMissing = true;
                            } else {
                                $('#btxtFirstName').removeClass('ui-state-error');
                            }

                            if ($('#btxtLastName').val() == '') {
                                $('#btxtLastName').addClass('ui-state-error');
                                isMissing = true;
                            } else {
                                $('#btxtLastName').removeClass('ui-state-error');
                            }

                            // validate guest address
                            if ($('#bincomplete').prop('checked') === false) {

                                $('.bhhk-addr-val').not('.hhk-MissingOk').each(function() {
                                    
                                    if ($(this).val() === "" && !$(this).hasClass('bfh-states')) {
                                        $(this).addClass('ui-state-error');
                                        isMissing = true;
                                    } else {
                                        $(this).removeClass('ui-state-error');
                                    }
                                });
                            }
                            if (isMissing) {
                                $('#adgstMsg').text('Fill in missing information');
                                return;
                            }

                            // Validate Phone Number
                            isMissing = false;
                            var testreg = /^([\(]{1}[0-9]{3}[\)]{1}[\.| |\-]{0,1}|^[0-9]{3}[\.|\-| ]?)?[0-9]{3}(\.|\-| )?[0-9]{4}$/;
                            $('.hhk-phoneInput[id^="btxtPhone"]').each(function (){

                                if ($.trim($(this).val()) != '' && testreg.test($(this).val()) === false) {
                                    // error
                                    $(this).addClass('ui-state-error');
                                    isMissing = true;
                                }        
                            });

                            if (isMissing) {
                                $('#adgstMsg').text("Guest has an invalid phone number.  ");
                                $('#diagAddGuest #bphEmlTabs').tabs("option", "active", 1);
                                return false;
                            }
        

                            $.post('ws_ckin.php', $('#fAddGuest').serialize() + '&cmd=addResv' + '&rid=' + resv.idReserv + '&addRoom=' + resv.addRoom, function(data) {
                                data = $.parseJSON(data);
                                if (data.error) {
                                    if (data.gotopage) {
                                        window.open(data.gotopage, '_self');
                                    }
                                    flagAlertMessage(data.error, true);
                                    return;
                                }
                                injectSlot(data);
                            });
                            $(this).dialog("close");
                        },
                        "Cancel": function() {
                            $(this).dialog("close");
                        }
                    }
                });
                
                acDiv.find('select.bfh-countries').each(function() {
                    var $countries = $(this);
                    $countries.bfhcountries($countries.data());
                });
                
                acDiv.find('select.bfh-states').each(function() {
                    var $states = $(this);
                    $states.bfhstates($states.data());
                });
                
                $('#diagAddGuest #bphEmlTabs').tabs();
                verifyAddrs('#diagAddGuest');
                var lastXhr;
                createZipAutoComplete($('#diagAddGuest input.hhk-zipsearch'), 'ws_admin.php', lastXhr);
                
                $('#diagAddGuest input.ckbdate').datepicker({
                    yearRange: '-99:+00',
                    changeMonth: true,
                    changeYear: true,
                    autoSize: true,
                    maxDate: 0,
                    dateFormat: 'M d, yy'
                });

                
                $('#diagAddGuest').on('click', '.hhk-addrCopy', function() {
                    var prefix = $(this).data('prefix');
                    
                    if (resv.addr && resv.addr.adraddress1 != '' && $('#' + prefix + 'adraddress1' + resv.adrPurpose).val() != resv.addr.adraddress1) {
                        $('#' + prefix + 'adraddress1' + resv.adrPurpose).val(resv.addr.adraddress1);
                        $('#' + prefix + 'adraddress2' + resv.adrPurpose).val(resv.addr.adraddress2);
                        $('#' + prefix + 'adrcity' + resv.adrPurpose).val(resv.addr.adrcity);
                        $('#' + prefix + 'adrcounty' + resv.adrPurpose).val(resv.addr.adrcounty);
                        $('#' + prefix + 'adrstate' + resv.adrPurpose).val(resv.addr.adrstate);
                        $('#' + prefix + 'adrcountry' + resv.adrPurpose).val(resv.addr.adrcountry);
                        $('#' + prefix + 'adrzip' + resv.adrPurpose).val(resv.addr.adrzip);
                        return;
                    }
                    
                    if (resv.members.length < 1) {
                        return;
                    }
                    
                    for (var i = 0; i < resv.members.length; i++) {
                        
                        if (resv.members[i] && resv.members[i].idPrefix !== prefix) {
                            
                            $('#' + prefix + 'adraddress1' + resv.adrPurpose).val($('#' + resv.members[i].idPrefix + 'adraddress1' + resv.adrPurpose).val());
                            $('#' + prefix + 'adraddress2' + resv.adrPurpose).val($('#' + resv.members[i].idPrefix + 'adraddress2' + resv.adrPurpose).val());
                            $('#' + prefix + 'adrcity' + resv.adrPurpose).val($('#' + resv.members[i].idPrefix + 'adrcity' + resv.adrPurpose).val());
                            $('#' + prefix + 'adrcounty' + resv.adrPurpose).val($('#' + resv.members[i].idPrefix + 'adrcounty' + resv.adrPurpose).val());
                            $('#' + prefix + 'adrstate' + resv.adrPurpose).val($('#' + resv.members[i].idPrefix + 'adrstate' + resv.adrPurpose).val());
                            $('#' + prefix + 'adrcountry' + resv.adrPurpose).val($('#' + resv.members[i].idPrefix + 'adrcountry' + resv.adrPurpose).val());
                            $('#' + prefix + 'adrzip' + resv.adrPurpose).val($('#' + resv.members[i].idPrefix + 'adrzip' + resv.adrPurpose).val());
                        }
                    }
                });
                
                $('#diagAddGuest').on('click', '.hhk-addrErase', function() {
                    var prefix = $(this).data('prefix');
                    $('#' + prefix + 'adraddress1' + resv.adrPurpose).val('');
                    $('#' + prefix + 'adraddress2' + resv.adrPurpose).val('');
                    $('#' + prefix + 'adrcity' + resv.adrPurpose).val('');
                    $('#' + prefix + 'adrcounty' + resv.adrPurpose).val('');
                    $('#' + prefix + 'adrstate' + resv.adrPurpose).val('');
                    $('#' + prefix + 'adrcountry' + resv.adrPurpose).val('');
                    $('#' + prefix + 'adrzip' + resv.adrPurpose).val('');
                    $('#' + prefix + 'adrbad' + resv.adrPurpose).prop('checked', false);
                });
                acDiv.dialog('open');
                return;
                
            } else {
                injectSlot(data);
                $('#selRateCategory').change();
            }
        });
    }
}
/**
 * 
 * @param {object} item
 * @returns {undefined}
 */
function delAdditionalGuest(item) {
    "use strict";
    var resv = reserv;
    hideAlertMessage();
    if (confirm("Remove " + item.value + "?")) {
        for (var i = 0; i < resv.members.length; i++) {
            var pan = resv.members[i];
            if (pan && pan.idName === item.id) {
                resv.members.splice(i, 1);
            }
        }
        $('#spnNumGuests').text(resv.members.length);
        $.post('ws_ckin.php',{
            cmd: 'delResvGst',
            id: item.id,
            rid: resv.idReserv
        }).done(
        function(data) {
            "use strict";
            data = $.parseJSON(data);
            if (!data) {
                alert('Bad Reply from Server');
                return;
            }
            if (data.error) {
                if (data.gotopage) {
                    window.open(data.gotopage, '_self');
                }
                flagAlertMessage(data.error, true);
                return;
            }
            injectSlot(data);
            $('#selRateCategory').change();
        });
    }
}
/**
 * 
 * @param {object} data
 * @returns {undefined}
 */
function injectSlot(data) {
    "use strict";
    var resv = reserv;

    if (data.memMkup && data.txtHdr) {
        var accdDiv = $('div#guestAccordion'),
            acDiv = $('<div id="' + data.idPrefix +  'divGstpnl" />').append($(data.memMkup));

        acDiv.addClass('Slot gstdetail');

        var expanderButton = $("<ul style='float:right;margin-left:5px;padding-top:1px;' class='ui-widget ui-helper-clearfix hhk-ui-icons'/>")
            .append($("<li class='ui-widget-header ui-corner-all' title='Open - Close'>")
            .append($("<span id='" + data.idPrefix + "drpDown' class='ui-icon ui-icon-circle-triangle-n'></span>")));


        // Header
        var acHdr = $('<div id="' + data.idPrefix +  'divGsthdr" style="padding:2px;"/>')
                .append($(data.txtHdr))
                .append($(expanderButton)) 
                .append($('<div style="clear:both;"/>'))
                .click(function() {
                    var disp = acDiv.css('display');
                    if (disp === 'none') {
                        acDiv.show('blind');
                        acHdr.removeClass('ui-corner-all').addClass('ui-corner-top');
                        $('#' + data.idPrefix + 'drpDown').removeClass('ui-icon-circle-triangle-s').addClass('ui-icon-circle-triangle-n');
                    } else {
                        acDiv.hide('blind');
                        acHdr.removeClass('ui-corner-top').addClass('ui-corner-all');
                        $('#' + data.idPrefix + 'drpDown').removeClass('ui-icon-circle-triangle-n').addClass('ui-icon-circle-triangle-s');
                    }
                });


        acHdr.addClass('ui-widget-header ui-state-default ui-corner-top');

        // Guest
        accdDiv.children().remove();
        accdDiv.append(acHdr);
        accdDiv.append(acDiv);
        // set country and state selectors
        acDiv.find('select.bfh-countries').each(function() {
            var $countries = $(this);
            $countries.bfhcountries($countries.data());
        });
        
        acDiv.find('select.bfh-states').each(function() {
            var $states = $(this);
            $states.bfhstates($states.data());
        });

        $('#' + data.idPrefix + 'selPatRel').change(function() {
            if ($(this).val() === 'slf' || $(this).val() === '') {
                
                $('div#patientSection').hide('blind');
                if ($(this).val() === 'slf') {
                    acHdr.removeClass('ui-state-default');
                    acHdr.find('#pgspnHdrLabel').text((resv.patAsGuest ? resv.patientLabel + '/' : '') + 'Primary Guest: ');
                }
                
                resv.patSection = false;

            } else {
                $('div#patientSection').show('blind');
                acHdr.addClass('ui-state-default');
                acHdr.find('#pgspnHdrLabel').text('Primary Guest: ');
                resv.patSection = true;
            }
        });

        $('#' + data.idPrefix + 'phEmlTabs').tabs();

        if (data.idName === 0) {
            $('#' + data.idPrefix + 'phEmlTabs').tabs("option", "active", 1);
            $('#' + data.idPrefix + 'phEmlTabs').tabs("option", "disabled", [0]);
        }

        $('#guestSearch').hide();
    }

    if (data.expDates !== undefined && data.expDates !== '') {

        $('#datesSection').children().remove();
        $('#datesSection').append($(data.expDates));

        var gstDate = $('#gstDate'),
            gstCoDate = $('#gstCoDate');

        $('#spnRangePicker').dateRangePicker(
	{
            format: 'MMM D, YYYY',
            separator : ' to ',
            minDays: 1,
            getValue: function()
            {
                if (gstDate.val() && gstCoDate.val() ) {
                    return gstDate.val() + ' to ' + gstCoDate.val();
                } else {
                    return '';
                }
            },
            setValue: function(s,s1,s2)
            {
                gstDate.val(s1);
                gstCoDate.val(s2);
            }
	});

        $('#datesSection').show();

    }

    if (data.notes !== undefined) {
        $('#notesGuest').children().remove().end().append($(data.notes)).show();
    }

    if (data.patStay !== undefined) {
        resv.patStaying = data.patStay;
    }

    if (data.idPsg) {
        resv.idPsg = data.idPsg;
    }

    // Hospital
    if (data.hosp !== undefined) {
        var hDiv = $(data.hosp.div).addClass('ui-widget-content').prop('id', 'divhospDetail');
        var expanderButton = $("<ul id='ulIcons' style='float:right;margin-left:5px;padding-top:1px;' class='ui-widget'/>")
            .append($("<li class='ui-widget-header ui-corner-all' title='Open - Close'>")
            .append($("<span id='h_drpDown' class='ui-icon ui-icon-circle-triangle-n'></span>")));
        var hHdr = $('<div id="divhospHdr" style="padding:2px; cursor:pointer;"/>')
                .append($(data.hosp.hdr))
                .append(expanderButton).append('<div style="clear:both;"/>');


        
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
        $('#txtEntryDate, #txtExitDate').datepicker();
        
        if ($('#txtAgentSch').length > 0) {
            createAutoComplete($('#txtAgentSch'), 3, {cmd: 'filter', basis: 'ra'}, getAgent);
            if ($('#a_txtLastName').val() === '') {
                $('.hhk-agentInfo').hide();
            }
        }
        
        if ($('#txtDocSch').length > 0) {
            createAutoComplete($('#txtDocSch'), 3, {cmd: 'filter', basis: 'doc'}, getDoc);
            if ($('#d_txtLastName').val() === '') {
                $('.hhk-docInfo').hide();
            }
        }
        
        // hospital changes may incure room availability
        // Oh, well.  Maybe next iteration.
        
        $('#hospitalSection').show('blind');
        
        if ($('#selHospital').val() !== '' && data.rvstCode && data.rvstCode !== '') {
            hHdr.click();
        }
    }

    // Patient
    if (data.patient !== undefined && data.patient != '') {
        // Patient
        var patSection = $('div#patientSection'),
            pcDiv = $('<div id="h_divGstpnl" />').append($(data.patient));
        var expanderButton = $("<ul id='ulIcons' style='float:right;margin-left:5px;padding-top:1px;' class='ui-widget'/>")
            .append($("<li class='ui-widget-header ui-corner-all' title='Open - Close'>")
            .append($("<span id='h_drpDown' class='ui-icon ui-icon-circle-triangle-n'></span>")));

        // Header
        var pcHdr = $('<div id="h_divGsthdr" style="padding:4px;" class="hhk-checkinHdr"/>')
                .append($('<span >' + resv.patientLabel + ': </span>'))
                .append($('<span id="h_hdrFirstName">' + pcDiv.find('#h_txtFirstName').val() + ' ' + '</span>'))
                .append($('<span id="h_hdrLastName">' + pcDiv.find('#h_txtLastName').val() + '</span>'))
                .append($('<span id="h_hdrMsg">' + (data.patStay ? ' (staying)' : '') + '</span>'))
                .append(expanderButton)
                .append($('<div style="clear:both;"/>'))
                .click(function() {
                        var disp = pcDiv.css('display');
                        if (disp === 'none') {
                            pcDiv.show('blind');
                            pcHdr.removeClass('ui-corner-all').addClass('ui-corner-top');
                            $('#h_drpDown').removeClass('ui-icon-circle-triangle-s').addClass('ui-icon-circle-triangle-n');
                        } else {
                            pcDiv.hide('blind');
                            pcHdr.removeClass('ui-corner-top').addClass('ui-corner-all');
                            $('#h_drpDown').removeClass('ui-icon-circle-triangle-n').addClass('ui-icon-circle-triangle-s');
                        }
                })
                .addClass('ui-widget-header ui-corner-top');


        pcDiv.find('select.bfh-countries').each(function() {
            var $countries = $(this);
            $countries.bfhcountries($countries.data());
        });
        pcDiv.find('select.bfh-states').each(function() {
            var $states = $(this);
            $states.bfhstates($states.data());
        });

        pcDiv.find('#h_phEmlTabs').tabs();

        if (data.idPsg === undefined || data.idPsg == 0) {
            pcDiv.find('#h_phEmlTabs').tabs("option", "active", 1);
            pcDiv.find('#h_phEmlTabs').tabs("option", "disabled", [0]);
        }

        resv.patSection = true;

        if ($('.patientRelch').length > 0) {
            $('.patientRelch option[value="slf"]').remove();
        }

        patSection
                .children().remove()
                .end()
                .append(pcHdr)
                .append(pcDiv)
                .show('scale, horizontal');

        if (pcDiv.find('#h_txtLastName').val() !== '') {
            $('#h_drpDown').click();
        }

    }

    $('#' + data.idPrefix + 'selPatRel').change();

    if (data.ratelist !== undefined) {
        resv.rateList = data.ratelist;
    }

    if (data.rooms !== undefined) {
        resv.resources = data.rooms;
    }

    if (data.vfee !== undefined) {
        resv.visitFees = data.vfee;
    }

    // Room chooser
    if (data.resc !== undefined) {
        
        $('#rescList').children().remove().end().append($(data.resc)).show();
        
        if (data.resv !== undefined) {
            
            $('#resvStatus').children().remove().end().append($(data.resv)).show();
            
            $('.hhk-viewResvActivity').click(function () {
              $.post('ws_ckin.php', {cmd:'viewActivity', rid: $(this).data('rid')}, function(data) {
                data = $.parseJSON(data);
                
                if (data.error) {
                    
                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }
                    flagAlertMessage(data.error, true);
                    return;
                }
                 if (data.activity) {
                     
                    $('div#submitButtons').hide();
                    $("#activityDialog").children().remove();
                    $("#activityDialog").append($(data.activity));
                    $("#activityDialog").dialog('open');
                }
                });
                
            });
            
            // Room selector update for constraints changes.
            $('input.hhk-constraintsCB').change( function () {
                updateRoomChooser(resv.idReserv, $('#spnNumGuests').text(), $('#pggstDate').val(), $('#pggstCoDate').val());
            });

            //
            $('#btnShowCnfrm').button();
            $('#btnShowCnfrm').click(function () {
                $.post('ws_ckin.php', {cmd:'confrv', rid: $(this).data('rid'), amt: $('#spnAmount').text(), eml: '0'}, function(data) {
                    
                    data = $.parseJSON(data);
                    
                    if (data.error) {
                        if (data.gotopage) {
                            window.open(data.gotopage, '_self');
                        }
                        flagAlertMessage(data.error, true);
                        return;
                    }
                    
                     if (data.confrv) {
                         
                        $('div#submitButtons').hide();
                        $("#frmConfirm").children().remove();
                        $("#frmConfirm").html(data.confrv)
                            .append($('<div style="padding-top:10px;" class="ui-dialog-buttonpane ui-widget-content ui-helper-clearfix"><span>Email Address </span><input type="text" id="confEmail" value="'+data.email+'"/></div>'));

                        $("#confirmDialog").dialog('open');
                    }
                });
            });
        }
        
        $('#btnDone').val('Save');
    }
    
    // rate
    if (data.rate !== undefined) {
        $('#rate').children().remove().end().append($(data.rate)).show();
        $('#btnFapp').button();
        $('#btnFapp').click(function() {
            getIncomeDiag(resv.idReserv);
        });
        
        setupRates(resv, $('#selResource').val());
        
        $('#h_drpDown, #divhospHdr, #' + data.idPrefix + 'drpDown').click();
        
        $('#selResource').change(function () {
            $('#selRateCategory').change();
            
            var selected = $("option:selected", this);
            selected.parent()[0].label === "Not Suitable" ? $('#hhkroomMsg').text("Not Suitable").show(): $('#hhkroomMsg').hide(); 
        });
    }

    // Financial
    if (data.pay !== undefined) {
        $('#pay').children().remove().end().append($(data.pay)).show();
        $('#paymentDate').datepicker({
            yearRange: '-1:+01',
            numberOfMonths: 1
        });
        setupPayments(data.rooms, $('#selResource'), $('#selRateCategory'));
    }

    // Reservation Guest list
    if (data.adguests) {
        
        $('#resvGuest').children().remove().end().append($(data.adguests)).show();
        $('.hhk-addResv, .hhk-delResv').button();
        
        if (resv.patStaying) {
            $('#h_hdrMsg').text('(Staying)');
        } else {
            $('#h_hdrMsg').text('');
        }

        if (!data.static || data.static !== 'y') {
            
            createAutoComplete($('#txtAddGuest'), 3, {cmd: 'role'}, additionalGuest);
            createAutoComplete($('#txtAddPhone'), 5, {cmd: 'role'}, additionalGuest);
            
            $('.hhk-addResv').click(function () {
                var item = {id: $(this).data('id'), value: $(this).data('name')};
                additionalGuest(item);
            });
            
            $('.hhk-delResv').click(function () {
                var item = {id: $(this).data('id'), value: $(this).data('name')};
                delAdditionalGuest(item);
                $(this).parent('td').next().children('a').removeClass('ui-state-highlight');
                $(this).remove();
            });
        }
    }

    // Vehicle
    if (data.vehicle) {
        $('#vehicle').children().remove().end().append($(data.vehicle)).show();
        $('#cbNoVehicle').change(function() {
            if (this.checked) {
                $('#tblVehicle').hide('scale, horizontal');
            } else {
                $('#tblVehicle').show('scale, horizontal');
            }
        });
        $('#cbNoVehicle').change();
        $('#btnNextVeh').button();
        resv.nextVeh = 1;
        $('#btnNextVeh').click(function () {
            $('#trVeh' + resv.nextVeh).show('fade');
            resv.nextVeh++;
            if (resv.nextVeh > 4) {
                $('#btnNextVeh').hide('fade');
            }
        });
    }
 
    // Number of guests
    if (data.numGuests) {
        $('#spnNumGuests').text(data.numGuests);
    }

    if (data.rvstatus) {
        $('#spnStatus').text(' - ' + data.rvstatus).show();
    } else {
        $('#spnStatus').text('').hide();
    }

    if (data.showRegBtn && data.showRegBtn === 'y') {
        $('#btnCkinForm').data('rid', resv.idReserv).show();  
    } else {
        $('#btnCkinForm').hide();    
    }
    
    if (resv.idReserv > 0 && (data.rvstCode === 'a' || data.rvstCode === 'w' || data.rvstCode === 'uc')) {
        $('#btnDelete').show();
    } else {
        $('#btnDelete').hide();
    }

    if (data.resun) {
        flagAlertMessage(data.resun, true);
    }

    $('.ckbdate').datepicker({
        yearRange: '-99:+00',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        maxDate: 0,
        dateFormat: 'M d, yy'
    });
    
    var lastXhr;
    createZipAutoComplete($('input.hhk-zipsearch'), 'ws_admin.php', lastXhr);
    

}
/**
 * 
 * @param {object} data
 * @param {string} saveBtnText
 * @returns {undefined}
 */
function loadResources(data, saveBtnText) {
    "use strict";
    var resv = reserv;
    var err;

    hideAlertMessage();

    try {
        data = $.parseJSON(data);
    } catch (err) {
        flagAlertMessage(err.message, true);
        $('form#form1').remove();        
    }

    if (data.error) {
        if (data.gotopage) {
            window.open(data.gotopage, '_self');
        }
        flagAlertMessage(data.error, true);
        $('form#form1').remove();
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

    if (data.warning) {
        flagAlertMessage(data.warning, true);
    }
    
    if (data.idReserv) {
        resv.idReserv = parseInt(data.idReserv, 10);
    }

    if ($('#btnDone').val() === 'Saving >>>>') {
        $('#btnDone').val(saveBtnText);
    }

    if (data) {
        injectSlot(data);
    }

    if (data.resCh) {
        resvPicker(data, $("#resDialog"));
        return;
    }

    if (data.receipt && data.receipt !== '') {
        showReceipt('#pmtRcpt', data.receipt, 'Payment Receipt');
    }

}
/**
 * 
 * @param {object} incmg
 * @param {string} role
 * @param {int} idPsg
 * @param {boolean} patientStaying
 * @returns {undefined}
 */
function loadGuest(incmg, role, idPsg, patientStaying) {
    "use strict";
    var resv = reserv,
        id = incmg.id,
        idPrefix = 'pg';

    hideAlertMessage();
    if (!role || role == '') {
        role = 'g';
    }

    if (role == 'p') {
       idPrefix = 'h_';
    }
    
    resv.role = role;
    resv.patStaying = patientStaying;
    
    // Check to make sure the guest is not already loaded into a slot
    if (id > 0) {
        
        for (var i = 0; i < resv.members.length; i++) {
            
            var pan = resv.members[i];
            
            if (pan.idName == id) {
                
                if (role === 'p' && pan.isPatient === false) {
                    flagAlertMessage("To make the guest also the " + resv.patientLabel + ", set this Guest's " + resv.patientLabel + " Relationship to " + resv.patientLabel + ".", true);
                    $('#pgselPatRel').addClass('ui-state-highlight');
                    return;
                }
            }
        }
    }
    // add a filled-in guest panel to the accordion.
    var parms = {
        cmd: 'getResv',
        id: id,
        rid: resv.idReserv,
        idPrefix: idPrefix,
        idPsg: idPsg,
        role: role,
        patStay: patientStaying
    };
    $.getJSON(
        'ws_ckin.php',
        parms,
        function(data) {
            var resv = reserv;
            if (!data) {
                alert('Bad Reply from Server');
                return;
            }
            if (data.error) {
                if (data.gotopage) {
                    window.open(data.gotopage, '_self');
                }
                flagAlertMessage(data.error, true);
                return;
            }
            if (data.warning) {
                flagAlertMessage(data.warning, true);
            }
            if (data.resCh) {
                resvPicker(data, $("#resDialog"));
                return;
            }
            if (data.choosePsg) {
                resv.idGuest = data.idGuest;
                psgChooser(data.choosePsg);
                return;
            }
            // Add guest to the page
            injectSlot(data);
            
            if (data.static && data.static === 'y') {
                
                $('input#btnDone').hide();

                
            } else {
                
                $('input#btnDone').show();
                
                
                if (data.patient && data.patient !== '') {
                    var idPstr = $('div#patientSection #h_idName').val();
                    
                    var idP = parseInt((idPstr == '' ? 0 : idPstr), 10);
                    
                    if (isNaN(idP) === false && idP > -1) {
                        var mp = new Mpanel('h_', idP);
                        mp.isPatient = true;
                        mp.isPG = false;
                        resv.members.push(mp);
                    }
                }
                
                if (data.idName !== null) {
                    
                    var idG = parseInt(data.idName, 10);
                    
                    if (isNaN(idG) === false && idG > -1) {
                        var mg = new Mpanel(data.idPrefix, idG);
                        mg.isPG = true;
                        resv.members.push(mg);
                    }
                }
            }
            
            $('input#gstSearch').val('');
            $('input#pggstDate').focus();
        }
    );
}
/**
 * 
 * @param {object} data
 * @returns {undefined}
 */
function psgChooser(choosePsg) {
    "use strict";
    var resv = reserv;
    $('#psgDialog')
        .children().remove().end().append($(choosePsg))
        .dialog('option', 'buttons', {
            Open: function() {
                if ($('#cbpstayy').prop('checked') == false && $('#cbpstayn').prop('checked') == false) {
                    $('#spnstaymsg').text('Choose Yes or No');
                    $('.pstaytd').addClass('ui-state-highlight');
                    return;
                }
                resv.idPsg = $('#psgDialog input[name=cbselpsg]:checked').val();
                loadGuest({id:resv.idGuest}, resv.role, resv.idPsg, $('#cbpstayy').prop('checked'));
                $('#psgDialog').dialog('close');
            },
            Cancel: function () {
                $('#gstSearch').val('');
                $('#psgDialog').dialog('close');
            }
        })
        .dialog('option', 'title', 'Patient Details')
        .dialog('open');
}
/**
 * 
 * @param {object} data
 * @param {jQuery} $faDiag
 * @returns {undefined}
 */
function resvPicker(data, $faDiag) {
    "use strict";
    var resv = reserv,
        buttons = {};

    $faDiag.children().remove();
    $faDiag.append($(data.resCh));
    $faDiag.children().find('input:button').button();
    
    $faDiag.children().find('.hhk-checkinNow').click(function () {
        window.open('CheckIn.php?rid=' + $(this).data('rid') + '&gid=' + data.id, '_self');
    });
    
    if (data.addtnlRoom) {
        
        buttons['Additional Room'] = function() {
            
            var parms = {
                cmd: 'addResv',
                id: data.id,
                rid: 0,
                psg: data.idPsg,
                arr: data.arr,
                dep: data.dep,
                addRoom: true};
        
            $.post('ws_ckin.php',parms,
                function(data) {
                "use strict";
                try {
                    data = $.parseJSON(data);
                } catch (err) {
                    alert("Parser error - " + err.message);
                    return;
                }

                if (!data) {
                    alert('Bad Reply from Server');
                    return;
                }

                if (data.error) {
                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }
                    flagAlertMessage(data.error, true);
                    return;
                }

                $('#txtAddGuest').val('');
                if (data.newRoom && data.newRoom > 1) {
                    $('#submitButtons').hide();
                    flagAlertMessage('<a href="Referral.php?rid=' + data.newRoom + '">View ' + data.newButtonLabel + '</a>', false);
                    return;
                }
            });
            
            $(this).dialog("close");
        };
    }
    
    if (data.newPatient) {
        buttons[data.newPatient] = function() {
            $(this).dialog("close");
            resv.idGuest = data.idGuest;
            psgChooser(data.newPsgChooser);
        };
    }
    
    if (data.newButtonLabel) {
        buttons[data.newButtonLabel] = function() {
            resv.idReserv = -1;
            $(this).dialog("close");
            loadGuest(data, resv.role, data.idPsg, (data.idPatient == data.id ? true : resv.patStaying));
        };
    }
    
    buttons['Exit'] = function() {$(this).dialog("close");};

    $faDiag.dialog('option', 'buttons', buttons);
    $faDiag.dialog('option', 'title', data.title);
    $faDiag.dialog('open');

}

/**
 * 
 * @param {object} reserv
 * @returns {Boolean}
 */
function verifyDone(reserv) {
    "use strict";
    var resv = reserv,
        havePatient = false,
        $selStatus = $('#selResvStatus'),
        $arrDate = $('#gstDate'),
        $deptDate = $('#gstCoDate');
    
    hideAlertMessage();

    // Cancel, no show, turned down
    if ($selStatus.val() === 'c' || $selStatus.val() === 'td' || $selStatus.val() === 'ns' || $selStatus.val() === 'h') {
        return true;
    }

    if (resv.members.length === 0) {
        flagAlertMessage("Use 'Add Guest' to enter a Guest.", true, 0);
        $('#gstSearch').addClass('ui-state-highlight').show('blind');
        return false;
    }
    
    // User set status to "Confirmed" or unconfirmed and no room set.
    if ( ($('#selResource').val() === '' || $('#selResource').val() === "0")) {
        $selStatus.val('w');
        $selStatus.change();
    }

    // Check in Date
    if ($arrDate.val() === '') {

        $arrDate.addClass('ui-state-error');
        flagAlertMessage("This " + resvTitle + " is missing the check-in date.", true);
        return false;

    } else {

        resv.ciDate = new Date($arrDate.val());

        if (isNaN(resv.ciDate.getTime())) {
            $arrDate.addClass('ui-state-error');
            flagAlertMessage("This " + resvTitle + " is missing the check-in date.", true);
            return false;
        }
    }

    // Check-out date
    if ($deptDate.val() == '') {
        $deptDate.addClass('ui-state-error');
        flagAlertMessage("This " + resvTitle + " is missing the expected departure date.", true);
        return false;

    } else {

        resv.coDate = new Date($deptDate.val());

        if (isNaN(resv.coDate.getTime())) {
            $deptDate.addClass('ui-state-error');
            flagAlertMessage("This " + resvTitle + " is missing the expected departure date", true);
            return false;
        }

        if (resv.ciDate > resv.coDate) {
            $arrDate.addClass('ui-state-error');
            flagAlertMessage("This " + resvTitle + "'s check-in date is after the expected departure date.", true);
            return false;
        }
    }


    // Remove guest highlights
    $('#guestAccordion').find('.ui-state-error').each(function() {
        $(this).removeClass('ui-state-error');
    });

    // Check for valid guest(s)
    for (var i = 0; i < reserv.members.length; i++) {

        var pan = reserv.members[i];
        var gstMsg = $('#' + pan.idPrefix + 'memMsg');

        gstMsg.text("");  // clear any error message

        // Have patient?
        if (pan.idPrefix === 'h_') {
            
            havePatient = true;
            
            if (reserv.patientBirthDate) {
                
                $('#h_txtBirthDate').removeClass('ui-state-error');
                
                if ($('#h_txtBirthDate').val() === '') {
                    
                    flagAlertMessage(resv.patientLabel + " needs a birth date.", true);
                    gstMsg.text("Birth date");
                    $('#h_txtBirthDate').addClass('ui-state-error');
                    return false;
                }
                
                // check date value
                var bDate = new Date($('#h_txtBirthDate').val());
                var today = new Date();
                
                if (bDate > today) {
                    flagAlertMessage("The " + resv.patientLabel + " birth date cannot be in the future.", true);
                    gstMsg.text("Birth date");
                    $('#h_txtBirthDate').addClass('ui-state-error');
                    return false;
                }
            }
        }

        var isMissing = false;
        var nameText = $('span#' + pan.idPrefix + 'hdrFirstName').text() + ' ' + $('span#' + pan.idPrefix + 'hdrLastName').text();

        // guest first and last name
        if ($('#' + pan.idPrefix + 'txtFirstName').val() == '') {
            $('#' + pan.idPrefix + 'txtFirstName').addClass('ui-state-error');
            isMissing = true;
        }

        if ($('#' + pan.idPrefix + 'txtLastName').val() == '') {
            $('#' + pan.idPrefix + 'txtLastName').addClass('ui-state-error');
            isMissing = true;
        }

        if (isMissing) {
            flagAlertMessage("Enter a first and last name for the " + (pan.idPrefix === 'h_' ? resv.patientLabel : 'Primary Guest') + ".", true);
            gstMsg.text("Incomplete Name");
            $('#' + pan.idPrefix + 'divGstpnl').show('blind');
            $('#' + pan.idPrefix + 'divGsthdr').removeClass('ui-corner-all').addClass('ui-corner-top');
            return false;
        }

        // validate guest address
        if ($('#' + pan.idPrefix + 'incomplete').prop('checked') === false) {

            $('.' + pan.idPrefix + 'hhk-addr-val').not('.hhk-MissingOk').each(function() {
                if ($(this).val() === "") {
                    if (!$(this).hasClass('bfh-states')) {
                        gstMsg.text("Incomplete Address");
                        $(this).addClass('ui-state-error');
                        isMissing = true;
                    }
                }
            });

            if (isMissing) {
                flagAlertMessage((pan.idPrefix === 'h_' ? resv.patientLabel : 'Primary Guest') + " (" + nameText + ") is missing some or all of their address.", true);
                $('#' + pan.idPrefix + 'divGstpnl').show('blind');
                $('#' + pan.idPrefix + 'divGsthdr').removeClass('ui-corner-all').addClass('ui-corner-top');
                return false;
            }
        }
        
        // Validate Phone Number
        isMissing = false;
        var faMesg = '';
        var testreg = /^([\(]{1}[0-9]{3}[\)]{1}[\.| |\-]{0,1}|^[0-9]{3}[\.|\-| ]?)?[0-9]{3}(\.|\-| )?[0-9]{4}$/;
        $('.hhk-phoneInput[id^="' + pan.idPrefix + 'txtPhone"]').each(function (){

            if ($.trim($(this).val()) != '' && testreg.test($(this).val()) === false) {
                // error
                $(this).addClass('ui-state-error');
                faMesg += (pan.idPrefix === 'h_' ? resv.patientLabel : 'Primary Guest') + " (" + nameText + ") has an invalid phone number.  ";
                isMissing = true;
            }        
        });
        
        if (isMissing) {
            flagAlertMessage(faMesg, true);
            $('#' + pan.idPrefix + 'divGstpnl').show('blind');
            $('#' + pan.idPrefix + 'divGsthdr').removeClass('ui-corner-all').addClass('ui-corner-top');
            $('#' + pan.idPrefix + 'phEmlTabs').tabs("option", "active", 1);
            return false;
        }
        
        // Check patient relationship
        if ($('#' + pan.idPrefix + 'selPatRel').val() === '') {

            $('#' + pan.idPrefix + 'selPatRel').addClass('ui-state-error');
            gstMsg.text("Set Primary Guest - " + resv.patientLabel + " Relationship");
            flagAlertMessage("Primary Guest (" + nameText + ") is missing their relationship to the " + resv.patientLabel + ".", true);
            $('#' + pan.idPrefix + 'divGstpnl').show('blind');
            $('#' + pan.idPrefix + 'divGsthdr').removeClass('ui-corner-all').addClass('ui-corner-top');
            return false;

        } else if ($('#' + pan.idPrefix + 'selPatRel').val() === 'slf') {
            
            havePatient = true;
            
            if (reserv.patientBirthDate) {
                
                $('#' + pan.idPrefix + 'txtBirthDate').removeClass('ui-state-error');
                
                if ($('#' + pan.idPrefix + 'txtBirthDate').val() === '') {
                    
                    flagAlertMessage(resv.patientLabel + " needs a birth date.", true);
                    gstMsg.text("Birth date");
                    $('#' + pan.idPrefix + 'txtBirthDate').addClass('ui-state-error');
                    return false;
                }
            }
        }


        $('#' + pan.idPrefix + 'divGstpnl').hide('blind');
        $('#' + pan.idPrefix + 'divGsthdr').removeClass('ui-corner-top').addClass('ui-corner-all');
    }

    if (havePatient === false) {
        flagAlertMessage("A " + resv.patientLabel + " is not selected", true);
        return false
    }

    // check Hospital
    $('#hospitalSection').find('.ui-state-error').each(function() {
        $(this).removeClass('ui-state-error');
    });
    if ($('#selHospital').length > 0 && $('#hospitalSection:visible').length > 0) {
        
        if ($('#selHospital').val() == "" ) {
            
            $('#selHospital').addClass('ui-state-error');

            flagAlertMessage("Select a hospital.", true, 0);
            
            $('#divhospDetail').show('blind');
            $('#divhospHdr').removeClass('ui-corner-all').addClass('ui-corner-top');
            return false;
        }
    }

    $('#divhospDetail').hide('blind');
    $('#divhospHdr').removeClass('ui-corner-top').addClass('ui-corner-all');


    return true;
}

/**
 * 
 * @param {string} idPrefix
 * @param {int} idName
 * @returns {undefined}
 */
function Mpanel(idPrefix, idName) {
    var t = this;
    t.idPrefix = idPrefix;
    t.isPG;
    t.isPatient;
    t.idName = idName;
}

function Reserv() {
     "use strict";
   var t = this;
   t.idReserv;
   t.members = [];
   t.idPsg = 0;
   t.adrPurpose = '1';
   t.gpnl;
   t.Total = 0;
   t.rateList;
   t.isFixed;
   t.resources;
   t.visitFees;
   t.patStaying;
   t.patAsGuest;
   t.role;
   t.patSection;
   t.ciDate;
   t.coDate;
}

$(document).ready(function() {
    "use strict";
    var resv = reserv;
    
    $.widget( "ui.autocomplete", $.ui.autocomplete, {
        _resizeMenu: function() {
            var ul = this.menu.element;
            ul.outerWidth( Math.max(
                    ul.width( "" ).outerWidth() + 1,
                    this.element.outerWidth()
            ) * 1.1 );
        }
    });
    
    $(window).bind('beforeunload', function() {
        if ($('#btnDone').val() === 'Saving >>>>') {
            return;
        }
        var isDirty = false;
        $('#guestAccordion').find("input[type='text']").not(".ignrSave").each(function() {
            if ($(this).val() !== $(this).prop("defaultValue")) {
                isDirty = true;
            }
        });
        $('#rescList').find("input[type='checkbox']").each(function () {
            if ( $(this).prop("checked") != $(this).prop("defaultChecked")) {
                isDirty = true;
            }            
        });
        $('#rescList').find("select").not(".ignrSave").each(function () {
            // gotta look at each option
            $(this).children('option').each(function () {
                // find the default option
                if (this.defaultSelected != this.selected) {
                    isDirty = true;
                }
            });
        });
        if (isDirty === true) {
            return 'You have unsaved changes.';
        }
    });
    
    $('#btnDone, #btnCkinForm, #btnDelete').button();
    
    $('#btnCkinForm').click(function () {
        if ($(this).data('rid') > 0) {
            window.open('ShowRegForm.php?rid=' + $(this).data('rid'), '_blank');
        }
    });
    
    $('#btnDelete').click(function () {
        if ($(this).val() === 'Deleting >>>>') {
            return;
        }
        
        if (confirm('Delete this ' + resvTitle + '?')) {
            
            var cmdStr = '&cmd=delResv' + '&rid=' + resv.idReserv;
                
            $(this).val('Deleting >>>>');

            $.post(
                    'ws_ckin.php', 
                    cmdStr, 
                    function(data) {
                        try {
                            data = $.parseJSON(data);
                        } catch (err) {
                            flagAlertMessage(err.message, true);
                            $('form#form1').remove();        
                        }

                        if (data.error) {
                            if (data.gotopage) {
                                window.open(data.gotopage, '_self');
                            }
                            flagAlertMessage(data.error, true);
                            $('form#form1').remove();
                        }
                        
                        $('#btnDelete').val('Delete');
                        
                        if (data.warning) {
                            flagAlertMessage(data.warning, true);
                        }
                        
                        if (data.result) {
                            $('form#form1').remove();
                            flagAlertMessage(data.result + ' <a href="register.php">Continue</a>', true);
                        }
                    }
            );
        }
    });
    
    $('#pmtRcpt').dialog({
        autoOpen: false,
        resizable: true,
        modal: true,
        title: 'Payment Receipt'
    });
    
    $('#confirmDialog').dialog({
        autoOpen: false,
        resizable: true,
        width: 850,
        modal: true,
        title: 'Confirmation Form',
        close: function () {$('div#submitButtons').show(); $("#frmConfirm").children().remove();},
        buttons: {
            'Download MS Word': function () {
                var $confForm = $("form#frmConfirm");
                $confForm.append($('<input name="hdnCfmRid" type="hidden" value="' + $('#btnShowCnfrm').data('rid') + '"/>'))
                $confForm.submit();
            },
            'Send Email': function() {
                $.post('ws_ckin.php', {cmd:'confrv', rid: $('#btnShowCnfrm').data('rid'), eml: '1', eaddr: $('#confEmail').val(), amt: $('#spnAmount').text(), notes: $('#tbCfmNotes').val()}, function(data) {
                    data = $.parseJSON(data);
                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }
                    flagAlertMessage(data.mesg, true);
                });
                $(this).dialog("close");
            },
            "Cancel": function() {
                $(this).dialog("close");
            }
        }
    });
    
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
                $.post('ws_ckin.php', $('#formf').serialize() + '&cmd=savefap' + '&rid=' + resv.idReserv, function(data) {
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
        }
    });
    $("#psgDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: 500,
        modal: true,
        title: resv.patientLabel + ' Support Group Chooser',
        close: function (event, ui) {$('div#submitButtons').show();},
        open: function (event, ui) {$('div#submitButtons').hide();}
    });
    $("#activityDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: 900,
        modal: true,
        title: 'Reservation Activity Log',
        close: function (event, ui) {$('div#submitButtons').show();},
        open: function (event, ui) {$('div#submitButtons').hide();},
        buttons: {
            "Exit": function() {
                $(this).dialog("close");
            }
        }
    });
    $("#resDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: 900,
        modal: true,
        title: 'Reservtion Chooser',
        buttons: {
            "Exit": function() {
                $(this).dialog("close");
            }
        }
    });
    $('#patientPrompt').dialog({
        autoOpen: false,
        resizable: true,
        width: 470,
        modal: true,
        title: ''
    });
    
    if (pmtMkup !== '') {
        $('#paymentMessage').html(pmtMkup).show("pulsate", {}, 400);
    }
    if (rctMkup !== '') {
        showReceipt('#pmtRcpt', rctMkup, 'Payment Receipt');
    }

    $.datepicker.setDefaults({
        yearRange: '-0:+02',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        dateFormat: 'M d, yy'
    });

    $('div#guestAccordion, div#patientSection').on('click', '.hhk-addrCopy', function() {
        var prefix = $(this).data('prefix'),
            otfix = 'h_';
            
        if (resv.addr && resv.addr.adraddress1 != '' && $('#' + prefix + 'adraddress1' + resv.adrPurpose).val() != resv.addr.adraddress1) {
            $('#' + prefix + 'adraddress1' + resv.adrPurpose).val(resv.addr.adraddress1);
            $('#' + prefix + 'adraddress2' + resv.adrPurpose).val(resv.addr.adraddress2);
            $('#' + prefix + 'adrcity' + resv.adrPurpose).val(resv.addr.adrcity);
            $('#' + prefix + 'adrcounty' + resv.adrPurpose).val(resv.addr.adrcounty);
            $('#' + prefix + 'adrstate' + resv.adrPurpose).val(resv.addr.adrstate);
            $('#' + prefix + 'adrcountry' + resv.adrPurpose).val(resv.addr.adrcountry);
            $('#' + prefix + 'adrzip' + resv.adrPurpose).val(resv.addr.adrzip);
            return;
        }
        if (prefix === 'h_') {
            otfix = 'pg';
        }

        if ($('#' + otfix + 'adrcity1').val() == '') {
            return;
        }

        $('#' + prefix + 'adraddress1' + resv.adrPurpose).val($('#' + otfix + 'adraddress11').val());
        $('#' + prefix + 'adraddress2' + resv.adrPurpose).val($('#' + otfix + 'adraddress21').val());
        $('#' + prefix + 'adrcity' + resv.adrPurpose).val($('#' + otfix + 'adrcity1').val());
        $('#' + prefix + 'adrcounty' + resv.adrPurpose).val($('#' + otfix + 'adrcounty1').val());
        $('#' + prefix + 'adrstate' + resv.adrPurpose).val($('#' + otfix + 'adrstate1').val());
        $('#' + prefix + 'adrcountry' + resv.adrPurpose).val($('#' + otfix + 'adrcountry1').val());
        $('#' + prefix + 'adrzip' + resv.adrPurpose).val($('#' + otfix + 'adrzip1').val());
    });

    $('div#guestAccordion, div#patientSection').on('click', '.hhk-addrErase', function() {
        var prefix = $(this).data('prefix');
        $('#' + prefix + 'adraddress11').val('');
        $('#' + prefix + 'adraddress21').val('');
        $('#' + prefix + 'adrcity1').val('');
        $('#' + prefix + 'adrcounty1').val('');
        $('#' + prefix + 'adrstate1').val('');
        $('#' + prefix + 'adrcountry1').val('');
        $('#' + prefix + 'adrzip1').val('');
        $('#' + prefix + 'adrbad1').prop('checked', false);
    });

    verifyAddrs('div#guestAccordion, #hospitalSection, div#patientSection');

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
    
    $('#selHospital').change();
        
    $('#btnDone').click(function() {
        
        if ($(this).val() === 'Saving >>>>') {
            return;
        }
        
        $('#divPayMessage').remove();
        
        if (verifyDone(resv) === true) {
                        
            var btnVal = $(this).val(),
                cmdStr = '&cmd=makeResv' + '&idPsg=' + resv.idPsg + '&rid=' + resv.idReserv + '&patStay=' + resv.patStaying + '&ciDate=' + resv.ciDate.toJSON() + '&coDate=' + resv.coDate.toJSON();
                
            $(this).val('Saving >>>>');

            $.post(
                    'ws_ckin.php', 
                    $('#form1').serialize() + cmdStr, 
                    function(data) {
                        loadResources(data, btnVal);
                    }
            );
        }
    });
    
    function getGuest(item) {
        
        if (item.No_Return !== undefined && item.No_Return !== '') {
            flagAlertMessage('This person is set for No Return: ' + item.No_Return + '.', true);
            return;
        }

        loadGuest(item, 'g', resv.idPsg, resv.patStaying);
    }

    createAutoComplete($('#gstSearch'), 3, {cmd: 'role', gp:'1'}, getGuest);
    
    // Phone number search
    createAutoComplete($('#gstphSearch'), 4, {cmd: 'role', gp:'1'}, getGuest);
    
    $('#gstSearch').keypress(function(event) {
        $(this).removeClass('ui-state-highlight');
    });

    function getPatient(item) {
        
        if (item.No_Return !== undefined && item.No_Return !== '') {
            flagAlertMessage('This person is set for No Return: ' + item.No_Return + '.', true);
            return;
        }
        
        if (resv.patAsGuest) {
            if (item.fullName === undefined) {
                item.fullName = 'the ' + resv.patientLabel;
            }
            $('#hhk-patPromptQuery').text('Is ' + item.fullName + ' staying the FIRST night (or longer)?');
            $('#patientPrompt')
                .dialog('option', 'buttons', {
                    Yes: function() {
                        loadGuest(item, 'p', resv.idPsg, true);
                        $('#patientPrompt').dialog('close');
                    },
                    No: function () {
                        loadGuest(item, 'p', resv.idPsg, false);
                        $('#patientPrompt').dialog('close');
                    }
                })
                .dialog('open');
        } else {
            loadGuest(item, 'p', resv.idPsg, false);
        }
    }

    createAutoComplete($('#h_Search'), 3, {cmd: 'role', gp:'1'}, getPatient);
    // Phone number search
    createAutoComplete($('#h_phSearch'), 4, {cmd: 'role', gp:'1'}, getPatient);

    if (resv.gpnl && resv.gpnl !== '') {
        loadGuest({id: resv.gpnl}, 'g', resv.idPsg);
    }
    
    $('#gstSearch').focus();

});
