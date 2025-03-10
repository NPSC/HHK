/**
 * visitDialog.js
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 *
 * @param {int} vid
 * @param {$} $container
 * @returns {$}
 */
function setupVisitNotes(vid, $container) {

    $container.notesViewer({
        linkId: vid,
        linkType: 'visit',
        newNoteAttrs: {id:'taNewVNote', name:'taNewVNote'},
        alertMessage: function(text, type) {
            flagAlertMessage(text, type);
        }
    });

    return $container;
}


function viewHospitalStay(idHs, idVisit, $hsDialog) {

	$.post('ws_resv.php', {cmd: 'viewHS', 'idhs': idHs}, function (data) {
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

        	$hsDialog.empty();
        	$hsDialog.append($(data.success));
        	$hsDialog.dialog({
                autoOpen: true,
                width: getDialogWidth(1050),
                resizable: true,
                modal: true,
                title: (data.title ? data.title : 'Hospital Details'),
                buttons: {
                    "Cancel": function() {
                        $('.ckhsdate').datepicker("hide");
                        $(this).dialog("close");
                    },
                    "Save": function() {
                        $('.ckhsdate').datepicker("hide");
                    	saveHospitalStay(idHs, idVisit);
                    	$(this).dialog("close");
                    }
                }
            });

        	// add closer to visit dialog box
        	if ($('#keysfees').length > 0) {
	        	$('#keysfees').on( "dialogclose", function( event, ui ) {

	        	    // Close hospital stay dialog
	        	    if ($hsDialog.dialog('isOpen')) {
	        	    	$hsDialog.dialog('close');
                        $('.ckhsdate').datepicker( "hide");
	        	    }

	        	} );
	        }

        	//Autocompletes for agent and doctor
            createAutoComplete($('.hhk-hsdialog #txtAgentSch'), 3, {cmd: 'filter', add: 'phone', basis: 'ra'}, getAgent);
            if ($('.hhk-hsdialog #a_txtLastName').val() === '') {
                $('.hhk-hsdialog .hhk-agentInfo').hide();
            }

            $(document).on('click', '#a_delete', function(){
            	$('.hhk-hsdialog #a_idName').val('');
            	$('.hhk-hsdialog input.hhk-agentInfo').val('');
            	$('.hhk-hsdialog .hhk-agentInfo').hide();
            });


            if ($('.hhk-hsdialog #a_idName').val() !== '') {
            	$('.hhk-hsdialog input.hhk-agentInfo.name').attr('readonly', 'readonly');
            }else{
            	$('.hhk-hsdialog input.hhk-agentInfo.name').removeAttr('readonly');
            }

            createAutoComplete($('.hhk-hsdialog #txtDocSch'), 3, {cmd: 'filter', basis: 'doc'}, getDoc);
            if ($('.hhk-hsdialog #d_txtLastName').val() === '') {
                $('.hhk-hsdialog .hhk-docInfo').hide();
            }

            if ($('.hhk-hsdialog #d_idName').val() !== '') {
            	$('.hhk-hsdialog input.hhk-docInfo.name').attr('readonly', 'readonly');
            }else{
            	$('.hhk-hsdialog input.hhk-docInfo.name').removeAttr('readonly');
            }

            $(document).on('click', '#d_delete', function(){
            	$('.hhk-hsdialog #d_idName').val('');
            	$('.hhk-hsdialog input.hhk-docInfo').val('');
            	$('.hhk-hsdialog .hhk-docInfo').hide();
            });

            // Diagnosis Search
			let diagSelect = function(item){
                            if(item.id !== "n"){
				$('#selDiagnosis').val(item.id);
				$("#selectedDiag").text(item.label).closest("tr").removeClass("d-none");
                            }
			};
			createAutoComplete($('#diagSearch'), 3, {cmd: 'diagnosis'}, diagSelect, false);

			//Diagnosis delete button
			$(document).on('click', '#delDiagnosis', function(e){
				$("#selDiagnosis").val("");
				$("#diagSearch").val("");
				$(this).closest('tr').addClass('d-none');
			});

            // Calendars for treatment start and end dates
            $('.ckhsdate').datepicker({
                yearRange: '-01:+01',
                changeMonth: true,
                changeYear: true,
                autoSize: true,
                numberOfMonths: 1,
                dateFormat: 'M d, yy',
                showButtonPanel: true,
                beforeShow: function (input) {
                    setTimeout(function () {
                        var buttonPane = $(input)
                                .datepicker("widget")
                                .find(".ui-datepicker-buttonpane");

                        buttonPane.empty();

                        $("<button>", {
                            text: "Clear",
                            click: function () {
                                //Code to clear your date field (text box, read only field etc.) I had to remove the line below and add custom code here
                                $.datepicker._clearDate(input);
                            }
                        }).appendTo(buttonPane).addClass("ui-datepicker-clear ui-state-default ui-priority-primary ui-corner-all");
                    }, 1);
                },
                onChangeMonthYear: function (year, month, instance) {
                    setTimeout(function () {
                        var buttonPane = $(instance)
                                .datepicker("widget")
                                .find(".ui-datepicker-buttonpane");
                        buttonPane.empty();
                        $("<button>", {
                            text: "Clear",
                            click: function () {
                                //Code to clear your date field (text box, read only field etc.) I had to remove the line below and add custom code here
                                $.datepicker._clearDate(instance.input);
                            }
                        }).appendTo(buttonPane).addClass("ui-datepicker-clear ui-state-default ui-priority-primary ui-corner-all");
                    }, 1);
                }
            });

        }
	})
}

function saveHospitalStay(idHs, idVisit) {
	var parms = [{'name':'cmd', 'value': 'saveHS'},{'name': 'idhs', 'value': idHs}, {'name': 'idv', 'value': idVisit}];
    var parms = parms.concat($('.hospital-stay:not(#txtDiagnosis)').serializeArray());

    //diagnosis
    let txtDiagnosis = $('#txtDiagnosis').val();
    if (typeof txtDiagnosis == "string") {
        txtDiagnosis = buffer.Buffer.from(txtDiagnosis).toString("base64");
    }
    parms.push({ 'name': 'txtDiagnosis', 'value': txtDiagnosis });

	$.post('ws_resv.php', parms, function (data) {
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
        	flagAlertMessage(data.success, 'success');
            if (data.newHsId && data.newHsId > 0) {
                $('.hhk-hospitalstay').each(function () {
                    $(this).data('idhs', data.newHsId);
                });
            }
        }
	});
}

function setupVehicle(veh) {
    var nextVehId = 2;
    var $cbVeh = veh.find('#cbNoVehicle');
    var $nextVeh = veh.find('#btnNextVeh');
    var $tblVeh = veh.find('#tblVehicle');

    $cbVeh.change(function () {
        if (this.checked) {
            $tblVeh.hide('scale, horizontal');
        } else {
            $tblVeh.show('scale, horizontal');
        }
    });

    $cbVeh.change();
    $nextVeh.button();

    $nextVeh.click(function () {
        veh.find('#trVeh' + nextVehId).show('fade');
        nextVehId++;
        if (nextVehId > 4) {
            $nextVeh.hide('fade');
        }
    });

}

function viewVehicleDialog(idVisit, $vehDialog) {

    $.post('ws_resv.php', { cmd: 'viewVeh', 'idV': idVisit }, function (data) {

        if (data.error) {
            if (data.gotopage) {
                window.open(data.gotopage, '_self');
            }
            flagAlertMessage(data.error, 'error');
            return;

        } else if (data.success) {

            $vehDialog.empty();
            $vehDialog.append($(data.success));
            
            setupVehicle($vehDialog);

            $vehDialog.dialog({
                autoOpen: true,
                width: getDialogWidth(900),
                resizable: true,
                modal: true,
                title: (data.title ? data.title : 'Vehicle Details'),
                buttons: {
                    "Cancel": function () {
                        $(this).dialog("close");
                    },
                    "Save": function () {
                        saveVehicles(idVisit, $vehDialog);
                        $(this).dialog("close");
                    }
                }
            });
        }
    }, "json");
}

function saveVehicles(idVisit, $vehDialog) {
    let params = $vehDialog.find("#tblVehicle input, #tblVehicle select").serializeArray();
    params.push({ name: "cmd", value: "saveVeh" });
    params.push({ name: "idV", value: idVisit });
    $.post('ws_resv.php', params, function (data) {

        if (data.error) {
            if (data.gotopage) {
                window.open(data.gotopage, '_self');
            }
            flagAlertMessage(data.error, 'error');
            return;

        } else if (data.success) {
            flagAlertMessage(data.success, 'success');
        }
    }, "json");
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
 * @param {string} ckoutDates
 * @returns {undefined}
 */
function viewVisit(idGuest, idVisit, buttons, title, action, visitSpan, ckoutDates) {
    "use strict";
    $.post('ws_ckin.php',
        {
            cmd: 'visitFees',
            idVisit: idVisit,
            idGuest: idGuest,
            action: action,
            span: visitSpan,
            ckoutdt: ckoutDates
        },
    function(data) {
        "use strict";
        if (data === undefined || data === null || data == '') {
            return;
        }

        // Process data
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
            flagAlertMessage(data.error, 'error');
            return;
        }

        // The controlling container.
        var $diagbox = $('#keysfees');

        // The controlling container.  Already on the page?
        if ($diagbox.length == 0 || data.success === null || data.success == '') {
            return;
        }

        // clear it and append the new contents thoughtfully provided by the server.
        $diagbox.children().remove();
        $diagbox.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog" style="font-size:0.8em; width:100%"/>').append($(data.success)));

        $diagbox.find('.ckdate').datepicker({
            yearRange: '-07:+01',
            changeMonth: true,
            changeYear: true,
            autoSize: true,
            numberOfMonths: 1,
            maxDate: 0,
            dateFormat: 'M d, yy'//,
        });

        $diagbox.find('.ckdateFut').datepicker({
            yearRange: '-01:+01',
            changeMonth: true,
            changeYear: true,
            autoSize: true,
            numberOfMonths: 1,
            minDate: 0,
            dateFormat: 'M d, yy'
        });

        // Background color is different if visit is checking out in the past.
        $diagbox.css('background-color', '#fff');

        if (action === 'ref') {
            // Checking out in the past
            $diagbox.css('background-color', '#FEFF9B');
            $('.hhk-ckoutDate').prop('disabled', true);
        }

        // set up Weekend leave controls
        if ($('.hhk-extVisitSw').length > 0) {

            $('#extendDays').change(function () {
                $('#extendDays').removeClass('ui-state-error');
            });

            // Setting extended date sets "Extend Until" radio button.
            $('#extendDate').change(function () {
                $('#rbOlpicker-ext').prop('checked', true);
            });

            // Unchecking the extend-until rb clears the associated date field
            $('input[name="rbOlpicker"]').change(function () {
                if ($(this).val() !== 'ext') {
                    $('#extendDate').val('');
                }
            });

            // Enable checkbox, show or hide panel.
            $('.hhk-extVisitSw').change(function () {
                if (this.checked) {
                    // Disable Rate Changer
                    $('#rateChgCB').prop('checked', false).change().prop('disabled', true);
                    $('.hhk-extendVisit').show('fade');
                } else {
                    $('.hhk-extendVisit').hide('fade');
                    $('#rateChgCB').prop('disabled', isCheckedOut);
                }
            });

            $('.hhk-extVisitSw').trigger('change');
        }

        // Set up rate changer
        if ($('#rateChgCB').length > 0) {

            let rateChangeDate = $('#chgRateDate');

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
                    $('.hhk-extVisitSw').prop('checked', false).change().prop('disabled', true);
                    $('.changeRateTd').show();
                }else {
                    $('.changeRateTd').hide('fade');
                    $('.hhk-extVisitSw').prop('disabled', isCheckedOut);
                }
            });

            $('#rateChgCB').change();
        }

        // Hospital stay dialog
        $('#tblActiveVisit').on('click', '.hhk-hospitalstay', function (event){
            event.preventDefault();
            viewHospitalStay($(this).data('idhs'), idVisit, $('#hsDialog'));
        });

        $diagbox.on('click', '#vehAdjust', function (event){
            event.preventDefault();
            console.log("vehicle clicked");
            viewVehicleDialog(idVisit, $('#vehDialog'));
        });

        $('#spnExPay').hide();
        isCheckedOut = false;

        let roomChgBal = 0.00;
        let vFeeChgBal = 0.00;
        let totChgBal = 0.00;

        if ($('#spnCfBalDue').length > 0) {
            roomChgBal = parseFloat($('#spnCfBalDue').data('rmbal'));
            vFeeChgBal = parseFloat($('#spnCfBalDue').data('vfee'));
            totChgBal = parseFloat($('#spnCfBalDue').data('totbal'));

        }


        if ($('input.hhk-ckoutCB').length > 0) {
            // still checked in...

            // Checkout checkbox change function
            $('#tblStays').on('change', 'input.hhk-ckoutCB', function() {

                let ckout = true,
                    coTime = 1,
                    today = new Date(),
                    coStayDates = {};

                if (this.checked === false) {
                    $(this).next().val('');  // clear the checkout date field
                } else if ($(this).next().val() === '') {
                    $(this).next().val($.datepicker.formatDate('M d, yy', new Date()));  // set the checkout date field
                }

                // Is the visit ending?
                // Scan all checkout checkboxes
                $('input.hhk-ckoutCB').each(function () {

                    if (this.checked === false) {

                        ckout = false;	// not checking out.

                    } else if ($(this).next().val() != '') {

                        let d = new Date($(this).next().val());
                        coStayDates[$(this).next().data('gid')] = d.toDateString();

                        if (d.getTime() > today.getTime()) {
                            $(this).next().val('');
                            ckout = false;
                        } else if (d.getTime() > coTime) {
                            coTime = d.getTime();
                        }
                    }
                });


                if (ckout === true) {
                    // Visit is ending

                    isCheckedOut = true;

                    let todayStr = today.getFullYear() + '-' + today.getMonth() + '-' + today.getDate();
                    let coDate = new Date(coTime);
                    let coDateStr = coDate.getFullYear() + '-' + coDate.getMonth() + '-' + coDate.getDate();

                    if (coDate.getTime() > today.getTime()) {
                        return false;
                    }

                    // Check for early visit checkout (co before today)
                    if (todayStr !== coDateStr && action !== 'ref') {
                        // update dialog with new co date.  Sets the room fee amounts accordingly.
                        $diagbox.children().remove();
                        $diagbox.dialog('option', 'buttons', {});
                        $diagbox.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog"/>')
                                .append($('<div class="ui-autocomplete-loading" style="width:5em;">Loading</div>')));
                        viewVisit(idGuest, idVisit, buttons, title, 'ref', visitSpan, coStayDates);
                        return;
                    }

                    // Disable the Rate changer and the weekend leaver
                    $('#rateChgCB').prop('checked', false).trigger('change').prop('disabled', true);
                    $('.hhk-extVisitSw').prop('checked', false).trigger('change').prop('disabled', true);
                    $('#paymentAdjust').hide();

                    // hide deposit payment
                    $('.hhk-kdrow').hide('fade');
                    $('#keyDepAmt').val('');

                    // show deposit refund
                    let kdamt = parseFloat($('#DepRefundAmount').data('amt'));
                    if (isNaN(kdamt)) {
                        kdamt = 0;
                    }

                    if (kdamt > 0) {
                        $('#DepRefundAmount').val((kdamt).toFixed(2).toString());
                        $('.hhk-refundDeposit').show('fade');
                    } else {
                        $('#DepRefundAmount').val('');
                        $('.hhk-refundDeposit').hide('fade');
                    }

                    if (totChgBal < 0) {

                        $('#guestCredit').val(roomChgBal.toFixed(2).toString());
                        $('#feesCharges').val('');

                    } else {

                        $('#feesCharges').val(roomChgBal.toFixed(2).toString());
                        $('#guestCredit').val('');

                        // force pay cleaning fee if unpaid...
                        if ($('#visitFeeCb').length > 0 && Math.abs(totChgBal) >= vFeeChgBal) {
                            $('#visitFeeCb').prop('checked', true).prop('disabled', true);
                        }
                    }

                    // Update paying today
                    amtPaid();

                } else if (action === 'ref') {

                    // return back to normal visit viewer.
                    $diagbox.children().remove();
                    $diagbox.dialog('option', 'buttons', {});
                    $diagbox.append($('<div class="hhk-panel hhk-tdbox hhk-visitdialog"/>')
                            .append($('<div class="ui-autocomplete-loading" style="width:5em;">Loading</div>')));
                    viewVisit(idGuest, idVisit, buttons, title, '', visitSpan);
                    return;

                } else {

                    isCheckedOut = false;

                    $('#feesCharges').val('');
                    $('#guestCredit').val('');
                    $('#feesPayment').val('');
                    $('.hhk-refundDeposit').hide('fade');
                    $('#DepRefundAmount').val('');
                    $('#visitFeeCb').prop('checked', false).prop('disabled', false);
                    $('#rateChgCB').prop('checked', false).prop('disabled', false).change()
                    $('.hhk-extVisitSw').prop('checked', false).prop('disabled', false).change();
                    $('#paymentAdjust').show();

                    // Update paying today
                    amtPaid();
                }
            });


            $('#tblStays').on('change', 'input.hhk-ckoutDate', function() {

                if ($(this).val() != '') {
                    const myckin = new Date($(this).data('ckin'));
                    const myCheckout = new Date($(this).val());

                    myckin.setHours(0);
                    myckin.setMinutes(0);
                    myckin.setSeconds(0);
                    myCheckout.setHours(0);
                    myCheckout.setMinutes(0);
                    myCheckout.setSeconds(0);

                    if (myckin.getTime() > myCheckout.getTime()) {
                        $(this).val('');
                        $(this).prev().prop('checked', false);
                        flagAlertMessage('Checkout cannot be earlier than check-in. ', 'error');
                    } else {
                        $(this).prev().prop('checked', true);
                    }

                } else {
                    $(this).prev().prop('checked',false);
                }

                $('input.hhk-ckoutCB').change();
            });

            $('#cbCoAll').button().click(function () {

                $('input.hhk-ckoutCB').each(function () {
                    $(this).prop('checked', true);
                });
                $('input.hhk-ckoutCB').change();
            });

            $('input.hhk-ckoutCB').change();

        } else {

            isCheckedOut = true;

            // hide deposit payment
            $('.hhk-kdrow').hide('fade');
            $('#keyDepAmt').val('');

            // show deposit refund
            let kdamt = parseFloat($('#kdPaid').data('amt'));
            if (isNaN(kdamt)) {
                kdamt = 0;
            }

            if (kdamt > 0) {
                $('#DepRefundAmount').val((kdamt).toFixed(2).toString());
                $('.hhk-refundDeposit').show('fade');
            } else {
                $('#DepRefundAmount').val('');
                $('.hhk-refundDeposit').hide('fade');
            }

            if (roomChgBal < 0) {
                $('#guestCredit').val(roomChgBal.toFixed(2).toString());
                $('#feesCharges').val('');
            } else {
                $('#feesCharges').val(roomChgBal.toFixed(2).toString());
                $('#guestCredit').val('');
            }

            $diagbox.css('background-color', '#F2F2F2');
        }


        setupPayments($('#selRateCategory').val(), idVisit, visitSpan, $('#pmtRcpt'), '#keysfees');

        // Financial Application
        let $btnFapp = $('#btnFapp');
        if ($btnFapp.length > 0) {
            $btnFapp.button();
            $btnFapp.click(function () {
                getIncomeDiag($btnFapp.data('rid'));
            });
        }

        // Add Guest button
        if ($('#btnAddGuest').length > 0) {
            $('#btnAddGuest').button();
            $('#btnAddGuest').click(function () {
                window.location.assign('CheckingIn.php?vid=' + $(this).data('vid') + '&span=' + $(this).data('span') + '&rid=' + $(this).data('rid') + '&vstatus=' + $(this).data('vstatus'));
            });
        }

        if ($(".viewMsgs").length > 0) {
            $(".viewMsgs").button().smsDialog({
                visitId: idVisit,
                spanId: visitSpan
            });
        }

        if ($('#selRateCategory').length > 0) {
            $('#selRateCategory').change(function () {
                if ($(this).val() == fixedRate) {
                    $('.hhk-fxFixed').show('fade');
                    $('.hhk-fxAdj').hide('fade');
                } else {
                    $('.hhk-fxFixed').hide('fade');
                    $('.hhk-fxAdj').show('fade');
                }
            });
            $('#selRateCategory').change();
        }

        // Notes
        setupVisitNotes(idVisit, $diagbox.find('#visitNoteViewer'));

        $diagbox.dialog('option', 'buttons', buttons);
        $diagbox.dialog('option', 'title', title);
        $diagbox.dialog('option', 'width', ($( window ).width() * .95));
        $diagbox.dialog('option', 'height', $( window ).height());
        $diagbox.dialog('open');


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
    let ckoutlist = [];
    let removeList = [];
    let undoCheckout = false;
    let undoRoomChg = false;
    let parms = {
        cmd: 'saveFees',
        idGuest: idGuest,
        idVisit: idVisit,
        span: visitSpan,
        rtntbl: (rtnTbl === true ? '1' : '0'),
        pbp: postbackPage
    };

    // Expected Checkout date
    $('input.hhk-expckout').each(function() {
        let parts = $(this).attr('id').split('_');
        if (parts.length > 0) {
            parms[parts[0] + '[' + parts[1] + ']'] = $(this).val();
        }
    });

    // Change Check in stay dates
    $('input.hhk-stayckin').each(function() {
        let parts = $(this).attr('id').split('_');
        if (parts.length > 0) {
            parms[parts[0] + '[' + parts[1] + ']'] = $(this).val();
        }
    });

    // Undo checkout
    if ($('#undoCkout').length > 0 && $('#undoCkout').prop('checked')) {
        undoCheckout = true;
    }

    // Undo Room Change
    if ($('#undoRmChg').length > 0 && $('#undoRmChg').prop('checked')) {
        undoRoomChg = true;
    }

    // Overpayment disposition
    if (isCheckedOut && verifyBalDisp() === false && undoCheckout === false && undoRoomChg === false) {
        return;
    }

    // Cash amount tendered
    if (verifyAmtTendrd() === false) {
        return;
    }

    // Check number days for Visit extension
    if ($('.hhk-extVisitSw').length > 0) {

		if ($('#extendCb').prop('checked') && $('#extendDays').val() < 1) {
			$('#extendDays').addClass('ui-state-error');
			flagAlertMessage('Weekend Leave days must be filled in. ', 'error');
			return;
		}
	}


    // Checkout check boxes, one per active guest.
    $('input.hhk-ckoutCB').each(function() {
        if (this.checked) {

            let parts = $(this).attr('id').split('_');

            if (parts.length > 0) {

                parms['stayActionCb[' + parts[1] + ']'] = 'on';
                var tdate = $('#stayCkOutDate_' + parts[1]).datepicker('getDate');

                if (tdate) {

                    var nowDate = new Date();
                    tdate.setHours(nowDate.getHours(), nowDate.getMinutes(), 0, 0);

                } else {
                    tdate = new Date();
                }

                if ($('#stayCkOutHour_' + parts[1]).length > 0) {
                    parms['stayCkOutHour[' + parts[1] + ']'] = $('#stayCkOutHour_' + parts[1]).val();
                }

                parms['stayCkOutDate[' + parts[1] + ']'] = tdate.toJSON();
                ckoutlist.push($(this).data('nm') + ', ' + tdate.toDateString());
            }
        }
    });

    // Remove stay
    $('input.hhk-removeCB').each(function () {
        if (this.checked) {
            let parts = $(this).attr('id').split('_');
            if (parts.length > 0) {
                parms[parts[0] + '[' + parts[1] + ']'] = 'on';
                removeList.push($(this).data('nm'));
            }
        }
    });

    // Confirm checking out
    if (ckoutlist.length > 0) {
        var cnfMsg = 'Check Out:\n' + ckoutlist.join('\n');
        if (confirm(cnfMsg + '?') === false) {
            $('#keysfees').dialog("close");
            return;
        }
    }

    // Confirm remove guests
    if (removeList.length > 0) {
        if (confirm('Remove:\n' + removeList.join('\n') + '?') === false) {
            $('#keysfees').dialog("close");
            return;
        }
    }

    $('#keyDepAmt').removeClass('ui-state-highlight');

	// Save Ribbon Note
	parms['txtRibbonNote'] = $('#txtRibbonNote').val();

    // Save Note
    if ($('#taNewVNote').length > 0 && $('#taNewVNote').val() !== '') {
        parms['taNewVNote'] = btoa($('#taNewVNote').val());
    }

    // Notice to Checkout
    if ($('#noticeToCheckout').length > 0 && $('#noticeToCheckout').val() !== '') {
        parms['noticeToCheckout'] = $('#noticeToCheckout').val();
    }

    // Fees and Keys
    $('.hhk-feeskeys').each(function() {
        if ($(this).attr('type') === 'checkbox') {
            if (this.checked !== false) {
                parms[$(this).attr('id')] = 'on';
                parms[$(this).attr('name')] = 'on';
            }
        } else if ($(this).hasClass('ckdate')) {
            var tdate = $(this).datepicker('getDate');
            if (tdate) {
                parms[$(this).attr('id')] = $(this).val();
            } else {
                 parms[$(this).attr('id')] = '';
            }
        } else if ($(this).attr('type') === 'radio') {
            if (this.checked !== false) {
                parms[$(this).attr('name')] = $(this).val();
            }
        } else{
            parms[$(this).attr('id')] = $(this).val();
            parms[$(this).attr('name')] = $(this).val();
        }
    });

    $('#keysfees').css('background-color', 'white');

    // show working icon
    $('#keysfees').empty().append('<div id="hhk-loading-spinner" style="width: 100%; height: 100%; margin-top: 100px; text-align: center"><img src="../images/ui-anim_basic_16x16.gif"><p>Working...</p></div>');

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

                flagAlertMessage(data.error, 'error');
                $('#keysfees').dialog("close");

                if (data.receipt && data.receipt !== '') {
                    showReceipt('#pmtRcpt', data.receipt, 'Payment Receipt');
                }

                return;
            }

            paymentRedirect(data, $('#xform'), parms);

            $('#keysfees').dialog("close");

            if (typeof refreshdTables !== 'undefined') {
                refreshdTables(data);
            }

            if (typeof pageManager !== 'undefined') {
                var dates = {'date1': new Date($('#gstDate').val()), 'date2': new Date($('#gstCoDate').val())};
                pageManager.doOnDatesChange(dates);
            }

            if (data.success && data.success !== '') {
                flagAlertMessage(data.success, 'success');

                if (typeof calendar !== 'undefined') {
                    calendar.refetchResources();
                    calendar.refetchEvents();
                }
            }

            if (data.warning && data.warning !== '') {
                flagAlertMessage(data.warning, 'error');
            }

            if (data.receipt && data.receipt !== '') {
                showReceipt('#pmtRcpt', data.receipt, 'Payment Receipt');
            }

            if (data.invoiceNumber && data.invoiceNumber !== '') {
                window.open('ShowInvoice.php?invnum=' + data.invoiceNumber);
            }

    });

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
