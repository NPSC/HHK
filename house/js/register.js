/* global pmtMkup, rvCols, wlCols, roomCnt, viewDays, rctMkup, defaultTab, isGuestAdmin, moment, FullCalendar */

/**
 * register.js
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
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

// Change reservation room.

/**
 *
 * @param {int} idResv
 * @param {int} idResc
 * @returns {Boolean}
 */
function setRoomTo(idResv, idResc) {

    $.post('ws_resv.php', {cmd: 'moveResvRoom', rid: idResv, idResc: idResc}, function(data) {
        try {
            data = $.parseJSON(data);
        } catch (err) {
            alert("Parser error - " + err.message);
            return false;
        }
        if (data.error) {
            if (data.gotopage) {
                window.location.assign(data.gotopage);
            }
            flagAlertMessage(data.error, 'error');
            return false;
        }
        if (data.warning && data.warning !== '') {
            flagAlertMessage(data.warning, 'warning');
            return false;
        }
        if (data.msg && data.msg !== '') {
            flagAlertMessage(data.msg, 'info');
        }
        if (data.success && data.success !== '') {
            flagAlertMessage(data.msg, 'success');
        }
        calendar.refetchEvents();
        refreshdTables(data);
        return true;
    });
}

var $dailyTbl;
function refreshdTables(data) {
    "use strict";

    if (data.curres && $('#divcurres').length > 0) {
        let tbl = $('#curres').DataTable();
        tbl.ajax.reload();
    }

    if (data.reservs && $('div#vresvs').length > 0) {
        let tbl = $('#reservs').DataTable();
        tbl.ajax.reload();
    }

    if (data.waitlist && $('div#vwls').length > 0) {
        let tbl = $('#waitlist').DataTable();
        tbl.ajax.reload();
    }

    if (data.unreserv && $('div#vuncon').length > 0) {
        let tbl = $('#unreserv').DataTable();
        tbl.ajax.reload();
    }

    if ($('#daily').length > 0 && $dailyTbl) {
        $dailyTbl.ajax.reload();
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
                flagAlertMessage(data.error, 'error');
                return;
            }
            if (data.success) {
                flagAlertMessage(data.success, 'info');
                calendar.refetchEvents();
            }
            refreshdTables(data);
        }
    });
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
                    flagAlertMessage("Server error - " + data.error, 'error');
                    return;
                }

                refreshdTables(data);

                if (data.msg && data.msg != '') {
                    flagAlertMessage(data.msg, 'info');
                }
            }

        });
    }
}

function editPSG(psg) {
    var buttons = {
        "Close": function() {
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
                flagAlertMessage(data.error, 'error');
            } else if (data.markup) {
                let diag = $('div#keysfees');
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
function ckOut(gname, id, idReserv, idVisit, span) {
    var buttons = {
        "Show Statement": function() {
            window.open('ShowStatement.php?vid=' + idVisit, '_blank');
        },
        "Show Registration Form": function() {
            window.open('ShowRegForm.php?rid=' + idReserv, '_blank');
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
function editVisit(gname, id, idReserv, idVisit, span) {
    var buttons = {
        "Show Statement": function() {
            window.open('ShowStatement.php?vid=' + idVisit, '_blank');
        },
        "Show Registration Form": function() {
            window.open('ShowRegForm.php?rid=' + idReserv, '_blank');
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
                $('.ckdate').datepicker({autoSize: true, dateFormat: 'M d, yy',
                	beforeShow: function(el,ui){
                		var startEl = $(this).closest('tr').find('[id^="txtstart"]');
                		var endEl = $(this).closest('tr').find('[id^="txtend"]');
                		if($(this).attr("id").startsWith("txtstart") && endEl.val() != ''){
                			$(this).datepicker('option',"maxDate", endEl.val());
                		}
                		if($(this).attr("id").startsWith("txtend") && startEl.val() != ''){
                			$(this).datepicker('option', "minDate", startEl.val());
                		}
                	}
                });
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
                calendar.refetchResources();
                calendar.refetchEvents();
            }

            if (data.msg && data.msg != '') {
                flagAlertMessage(data.msg, 'info');
            }
        }
        $('#statEvents').dialog('close');
    });
}

// Change Visit's' Room
function showChangeRoom(gname, id, idVisit, span) {
	// Get the change rooms dialog box

    this.rooms = {};

    $.post('ws_ckin.php',
        {
            cmd: 'showChangeRooms',
            idVisit: idVisit,
            span: span,
            idGuest: id
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
                flagAlertMessage(data.error, 'error');
                return;

            }

            let sDate = new Date(data.start)

            let $diagbox = $('#chgRoomDialog');

            $diagbox.children().remove();
            $diagbox.append($('<div class="hhk-tdbox hhk-visitdialog" style="font-size:0.8em;"/>').append($(data.success)));

			let $selResource = $diagbox.find('#selResource');
            let $changeDate = $('#resvChangeDate');
            let $replaceRoom = $('input[name=rbReplaceRoom]');
            let $cbUseDefaultRate = $('#cbUseDefaultRate');

            $changeDate.datepicker({
                yearRange: '-05:+00',
                changeMonth: true,
                changeYear: true,
                autoSize: true,
                numberOfMonths: 1,
                maxDate: 0,
                minDate: sDate,
                dateFormat: 'M d, yy'
            });

            $changeDate.datepicker('setDate', new Date());

            // room changer radiobutton
            $replaceRoom.change(function () {
				if($(this).val() == 'new' && $changeDate.val() !== '') {
					getVisitRoomList(idVisit, span, $changeDate.datepicker( "getDate" ), $selResource);
				} else if ($(this).val() == 'rpl') {
					getVisitRoomList(idVisit, span, sDate, $selResource);
				}
			});

            // Date Control
            $changeDate.change(function (){
				$('input[name=rbReplaceRoomnew]').prop('checked', true);
				getVisitRoomList(idVisit, span, $changeDate.datepicker( "getDate" ), $selResource);
			});

            //init room selector data
            if (data.rooms) {
                rooms = data.rooms;
            }else{
            	rooms = {};
            }

			// Room selector
            $selResource.change( function(){
            	let selResource = $(this).val();
            	// Deposit required message
            	if(rooms[selResource] && data.curResc.key < rooms[selResource].key){
            		$diagbox.find('#rmDepMessage').text('Deposit required').show();
            	}else{
            		$diagbox.find('#rmDepMessage').empty().hide();
            	}

            	// Room default rate message.
            	if ((data.curResc.defaultRateCat == '' && rooms[selResource].defaultRateCat != '')
            		|| (data.curResc.defaultRateCat != ''  && rooms[selResource].defaultRateCat != '' && data.curResc.defaultRateCat != rooms[selResource].defaultRateCat)) {

					$diagbox.find('#trUseDefaultRate').show();

				} else {
					$diagbox.find('#trUseDefaultRate').hide();
				}
            });

            $selResource.change();

			// Define dialog box buttons.
		    let buttons = {
		        "Change Rooms": function() {
		        	if($('#selResource').val() > 0){
		            	changeRooms(idVisit, span, $selResource.val(), $('input[name="rbReplaceRoom"]:checked').val(), $cbUseDefaultRate.prop('checked'), $changeDate.datepicker( "getDate" ).toUTCString());
		            	$(this).dialog("close");
		            }else{
		            	$('#rmDepMessage').text('Choose a room').show();
		            }
		        },
		        "Cancel": function() {
		            $(this).dialog("close");
		        }
		    };

            $diagbox.dialog('option', 'title', 'Change Rooms for ' + gname);
            $diagbox.dialog('option', 'width', '400px');
            $diagbox.dialog('option', 'buttons', buttons);
            $diagbox.dialog('open');

        }
    });

	function changeRooms(idVisit, span, idRoom, replaceRoom, useDefaultRate, changeDate) {

		let parms = {cmd: 'doChangeRooms', idVisit: idVisit, span: span, idRoom: idRoom, replaceRoom: replaceRoom, useDefault: useDefaultRate, changeDate: changeDate};

		$.post('ws_ckin.php', parms,
			function (data) {

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
	                flagAlertMessage(data.error, 'error');
	                return;
	            }

	            // Open visit edit window?
	            if (data.openvisitviewer) {
					editVisit('', 0, idVisit, data.openvisitviewer);
				}

	            if (data.msg && data.msg != '') {
	                flagAlertMessage(data.msg, 'info');
	            }

				calendar.refetchEvents();
	            refreshdTables(data);

		});

	}

    function getVisitRoomList(idVisit, visitSpan, changeDate, $rescSelector) {

	    $rescSelector.prop('disabled', true);
	    $('#hhk-roomChsrtitle').addClass('hhk-loading');
	    $('#rmDepMessage').text('').hide();

	    d = new Date();


	    let parms = {cmd:'chgRoomList', idVisit:idVisit, span:visitSpan, chgDate:changeDate.toDateString(), selRescId:$rescSelector.val()};

	    $.post('ws_ckin.php', parms,
	        function (data) {
	            let newSel;

	            $rescSelector.prop('disabled', false);
	            $('#hhk-roomChsrtitle').removeClass('hhk-loading');

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
	                flagAlertMessage(data.error, 'error');
	                return;
	            }

	            if (data.sel) {
	                newSel = $(data.sel);
	                $rescSelector.children().remove();

	                newSel.children().appendTo($rescSelector);
	                $rescSelector.val(data.idResc).change();

	            }

	            if (data.rooms) {
	                rooms = data.rooms;
	            }else{
	            	rooms = {};
	            }

        });
	}

}

function moveVisit(mode, idVisit, visitSpan, startDelta, endDelta, updateCal) {
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
                flagAlertMessage(data.error, 'error');

            } else if (data.success) {
                flagAlertMessage(data.success, 'success');
            }
            if (updateCal === undefined || updateCal === true) {
                calendar.refetchEvents();
                refreshdTables(data);
            }
        }
    });
}

function getRoomList(idResv, eid, targetEl) {
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
                flagAlertMessage(data.error, 'error');
                return;
            }
            if (data.container) {
                let contr = $(data.container);
                $('body').append(contr);
                contr.position({
                    my: 'top',
                    at: 'bottom',
                    of: targetEl
                });
                $('#selRoom').change(function () {

                    if ($('#selRoom').val() == '') {
                        contr.remove();
                        return;
                    }

                    if (confirm('Change room to ' + $('#selRoom option:selected').text() + '?')) {
                        setRoomTo(data.rid, $('#selRoom').val());
                    }
                    contr.remove();
                });
            }
        });
    }
}

function refreshPayments() {
	$('#btnFeesGo').click();
}

function getDtBtns(title, stripHtml = true, className = ""){
		return [
            {
                extend: "print",
                className: "ui-corner-all",
                autoPrint: true,
                paperSize: "letter",
                exportOptions: {
                    stripHtml: stripHtml,
                	columns: ":not('.noPrint')",
                },
                title: function(){
                    return title;
                },
                messageBottom: function(){
                	var now = moment().format("MMM D, YYYY") + " at " + moment().format("h:mm a");
                	return '<div style="padding-top: 10px; position: fixed; bottom: 0; right: 0">Printed on '+now+'</div>';
                },
                customize: function (win) {
                    $(win.document.body)
                        .css("font-size", "0.9em");
                    
                    if (className.length > 0) {
                        $(win.document.body)
                            .addClass(className);
                    }

                    $(win.document.body).find("table")
                        //.addClass("compact")
                        .css("font-size", "inherit");
                }
            }
    	];
    }

    var hindx = 0,
    pmtMkup = $('#pmtMkup').val(),
    rctMkup = $('#rctMkup').val(),
    defaultTab = $('#defaultTab').val(),
    resourceGroupBy = $('#resourceGroupBy').val(),
    resourceColumnWidth = $('#resourceColumnWidth').val(),
    patientLabel = $('#patientLabel').val(),
    visitorLabel = $('#visitorLabel').val(),
    referralFormTitleLabel = $('#referralFormTitleLabel').val(),
    reservationLabel = $('#reservationLabel').val(),
    reservationTabLabel = $('#reservationTabLabel').val(),
    unconfirmedResvTabLabel = $('#unconfirmedResvTabLabel').val(),
    defaultView = $('#defaultView').val(),
    defaultEventColor = $('#defaultEventColor').val(),
    defCalEventTextColor = $('#defCalEventTextColor').val(),
    calDateIncrement = $('#calDateIncrement').val(),
    dateFormat = $('#dateFormat').val(),
    fixedRate = $('#fixedRate').val(),
    resvPageName = $('#resvPageName').val(),
    showCreatedDate = $('#showCreatedDate').val(),
    expandResources = $('#expandResources').val(),
    shoHospitalName = $('#shoHospitalName').val(),
    showRateCol = $('#showRateCol').val(),
    hospTitle = $('#hospTitle').val(),
    showDiags = $('#showDiags').val(),
    showLocs = $('#showLocs').val(),
    locationTitle = $('#locationTitle').val(),
    diagnosisTitle = $('#diagnosisTitle').val(),
    showWlNotes = $('#showWlNotes').val(),
    wlTitle = $('#wlTitle').val(),
    showCharges = $('#showCharges').val(),
	acceptResvPay = $('#acceptResvPay').val(),
	holidays = $.parseJSON($('#holidays').val()),
    closedDays = $.parseJSON($('#closedDays').val()),
	showCurrentGuestPhotos = $("#showCurrentGuestPhotos").val(),
    useOnlineReferral = $('#useOnlineReferral').val(),
    calendar;

$(document).ready(function () {
    "use strict";

    // Current Guests
    let cgCols = [
            {data: 'Action', title: 'Action', sortable: false, searchable:false, className: "noPrint"},
            {data: visitorLabel+' First', title: visitorLabel+' First'},
            {data: visitorLabel+' Last', title: visitorLabel+' Last'},
            {data: 'Checked In', title: 'Checked In', render: function (data, type) {return dateRender(data, type, dateFormat);}},
            {data: 'Nights', title: 'Nights', className: 'hhk-justify-c'},
            {data: 'Expected Departure', title: 'Expected Departure', render: function (data, type) {return dateRender(data, type, dateFormat);}},
            {data: 'Room', title: 'Room', className: 'hhk-justify-c'}];

        if(showRateCol) {
           cgCols.push({data: 'Rate', title: 'Rate'});
        }

        cgCols.push({data: 'Phone', title: 'Phone'});

        if(shoHospitalName) {
            cgCols.push({data: 'Hospital', title: hospTitle});
        }

        cgCols.push({data: 'Patient', title: patientLabel});

		if(showCurrentGuestPhotos){
			cgCols.unshift({data: 'photo', title: 'Photo', sortable: false, searchable: false, className: "noPrint", width: "80px"});
    }
    
    $("#btnTextCurGuests").button().smsDialog({ "campaign": "checked_in"});

    $("#btnTextConfResvGuests").button().smsDialog({ "campaign": "confirmed_reservation" });

    $("#btnTextUnConfResvGuests").button().smsDialog({ "campaign": "unconfirmed_reservation" });

    $("#btnTextWaitlistGuests").button().smsDialog({ "campaign": "waitlist" });

    // Reservations
    let rvCols = [
            {data: 'Action', title: 'Action', sortable: false, searchable:false, className: "noPrint"},
            {data: 'Guest First', title: visitorLabel+' First'},
            {data: 'Guest Last', title: visitorLabel+' Last'},
            {data: 'Expected Arrival', title: 'Expected Arrival', render: function (data, type) {return dateRender(data, type, dateFormat);}},
            {data: 'Nights', title: 'Nights', className: 'hhk-justify-c'},
            {data: 'Expected Departure', title: 'Expected Departure', render: function (data, type) {return dateRender(data, type, dateFormat);}},
            {data: 'Room', title: 'Room', className: 'hhk-justify-c'}];

            if(showRateCol) {
               rvCols.push({data: 'Rate', title: 'Rate'});
            }

            rvCols.push({data: 'Occupants', title: 'Occupants', className: 'hhk-justify-c'});

            if (acceptResvPay) {
				rvCols.push({data: 'PrePaymt', title: 'Pre-Paymt', className: 'hhk-justify-c'});
			}

            if(shoHospitalName) {
                rvCols.push({data: 'Hospital', title: hospTitle});
            }

            if(showLocs) {
                rvCols.push({data: 'Location', title: locationTitle});
            }
            if(showDiags) {
                rvCols.push({data: 'Diagnosis', title: diagnosisTitle});
            }

            rvCols.push({data: 'Patient', title: patientLabel});

    //Waitlist
    let wlCols = [
            {data: 'Action', title: 'Action', sortable: false, searchable:false, "className": "noPrint"},
            {data: 'Guest First', title: visitorLabel+' First'},
            {data: 'Guest Last', title: visitorLabel+' Last'}];

            if (showCreatedDate) {
                wlCols.push({data: 'Timestamp', title: 'Created On', render: function (data, type) {return dateRender(data, type, "MMM D, YYYY H:mm")}});
				wlCols.push({data: 'Updated_By', title: 'Updated By'});
            }

            wlCols.push({data: 'Expected Arrival', title: 'Expected Arrival', render: function (data, type) {return dateRender(data, type, dateFormat);}});
            wlCols.push({data: 'Nights', title: 'Nights', className: 'hhk-justify-c'});
            wlCols.push({data: 'Expected Departure', title: 'Expected Departure', render: function (data, type) {return dateRender(data, type, dateFormat);}});
            wlCols.push({data: 'Occupants', title: 'Occupants', className: 'hhk-justify-c'});

            if (acceptResvPay) {
				wlCols.push({data: 'PrePaymt', title: 'Pre-Paymt', className: 'hhk-justify-c'});
			}

            if(shoHospitalName) {
                wlCols.push({data: 'Hospital', title: hospTitle});
            }

            if(showLocs) {
                wlCols.push({data: 'Location', title: locationTitle});
            }
            if(showDiags) {
                wlCols.push({data: 'Diagnosis', title: diagnosisTitle});
            }

            wlCols.push({data: 'Patient', title: patientLabel});

            if (showWlNotes) {
                wlCols.push({data: 'WL Notes', title: wlTitle});
            }

    // Dailey Report
    let dailyCols = [
            {data: 'titleSort', 'visible': false },
            {data: 'Title', title: 'Room', 'orderData': [0, 1], className: 'hhk-justify-c'},
            {data: 'Status', title: 'Status', searchable:false},
            {data: 'Guests', title: visitorLabel+'s'},
            {data: 'Patient_Name', title: patientLabel}];

            if (showCharges) {
                dailyCols.push({data: 'Unpaid', title: 'Unpaid', className: 'hhk-justify-r'});
            }
            dailyCols.push({data: 'Visit_Notes', title: 'Last Visit Note', sortable: false});
            dailyCols.push({data: 'Notes', title: 'Room Notes', sortable: false});

    // Show payment message
    if (pmtMkup !== '') {
        $('#paymentMessage').html(pmtMkup).show("pulsate", {}, 400);
    }

    $('input[type="button"], input[type="submit"]').button();

    $.datepicker.setDefaults({
        yearRange: '-10:+02',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        numberOfMonths: 2,
        dateFormat: 'M d, yy'
    });
    $.extend( $.fn.dataTable.defaults, {
        "dom": '<"dtTop"if>rt<"dtBottom"lp><"clear">',
        "displayLength": 50,
        "lengthMenu": [[25, 50, -1], [25, 50, "All"]],
        "order": [[ 3, 'asc' ]],
        "processing": true,
        "deferRender": true
    });

    $('#vstays').on('click', '.applyDisc', function (event) {
        event.preventDefault();
        $(".hhk-alert").hide();
        getApplyDiscDiag($(this).data('vid'), $('#pmtRcpt'));
    });
    $('#vstays').on('click', '.stckout', function (event) {
        event.preventDefault();
        $(".hhk-alert").hide();
        ckOut($(this).data('name'), $(this).data('id'), $(this).data('rid'), $(this).data('vid'), $(this).data('spn'));
    });
    $('#vstays').on('click', '.stvisit', function (event) {
        event.preventDefault();
        $(".hhk-alert").hide();
        editVisit($(this).data('name'), $(this).data('id'), $(this).data('rid'), $(this).data('vid'), $(this).data('spn'));
    });
    $('#vstays').on('click', '.hhk-getPSGDialog', function (event) {
        event.preventDefault();
        $(".hhk-alert").hide();
        editPSG($(this).data('psg'));
    });
    $('#vstays').on('click', '.stchgrooms', function (event) {
        event.preventDefault();
        $(".hhk-alert").hide();
        showChangeRoom($(this).data('name'), $(this).data('id'), $(this).data('vid'), $(this).data('spn'));
    });
    $('#vstays').on('click', '.stcleaning', function (event) {
        event.preventDefault();
        $(".hhk-alert").hide();
        chgRoomCleanStatus($(this).data('idroom'), $(this).data('clean'));
    });
    $('#vresvs, #vwls, #vuncon').on('click', '.resvStat', function (event) {
        event.preventDefault();
        $(".hhk-alert").hide();
        cgResvStatus($(this).data('rid'), $(this).data('stat'));
    });

    $('.ckdate').datepicker();
    $('#regckindate').val(moment().format("MMM DD, YYYY"));

    $('#statEvents').dialog({
        autoOpen: false,
        resizable: true,
        width: getDialogWidth(1000),
        modal: true,
        title: 'Manage Status Events'
    });

    //turn off autofocus
    $("#statEvents").data("uiDialog")._focusTabbable = function(){};

    $('#keysfees').dialog({
        autoOpen: false,
        resizable: true,
        modal: true,
        close: function (event, ui) {
            $('div#submitButtons').show();  // Page submit buttons, hide when dialog is open, show when closed.
        },
        open: function (event, ui) {
            $('div#submitButtons').hide();
        }
    });


    $(document).mousedown(function (e) {
        var roomChooser = $('div#pudiv');

        // remove room chooser
        if (!roomChooser.is(e.target) && roomChooser.has(e.target).length === 0) {
            roomChooser.remove();
        }

    });

    $("#faDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: getDialogWidth(650),
        modal: true,
        title: 'Income Chooser'
    });
    $("#setBillDate").dialog({
        autoOpen: false,
        resizable: true,
        modal: true,
        title: 'Set Invoice Billing Date'
    });

    $('#chgRoomDialog').dialog({
        autoOpen: false,
        resizable: true,
        modal: true
    });
    $('#pmtRcpt').dialog({
        autoOpen: false,
        resizable: true,
        width: getDialogWidth(530),
        modal: true,
        title: 'Payment Receipt'
    });
    if ($('#txtactstart').val() === '') {
        let nowdt = new Date();
        nowdt.setTime(nowdt.getTime() - (5 * 86400000));
        $('#txtactstart').datepicker('setDate', nowdt);
    }

    if ($('#txtfeestart').val() === '') {
        let nowdt = new Date();
        nowdt.setTime(nowdt.getTime() - (3 * 86400000));
        $('#txtfeestart').datepicker('setDate', nowdt);
    }

    // Member search letter input box
    $('#txtsearch').keypress(function (event) {
        let mm = $(this).val();
        if (event.keyCode == '13') {
            if (mm === '' || !isNumber(parseInt(mm, 10))) {
                alert("Don't press the return key unless you enter an Id.");
                event.preventDefault();
            } else {
                if (mm > 0) {
                    window.location.assign("GuestEdit.php?id=" + mm);
                }
                event.preventDefault();
            }
        }
    });

	// Name Search
    createRoleAutoComplete($('#txtsearch'), 3, {cmd: 'guest'},
        function (item) {
            if (item.id > 0) {
                window.location.assign("GuestEdit.php?id=" + item.id);
            }
        },
        false);

    let dateIncrementObj = null;

    if (calDateIncrement > 0 && calDateIncrement < 5) {
        dateIncrementObj = {weeks: calDateIncrement};
    }

    $('#selRoomGroupScheme').val(resourceGroupBy);

    let winHieght = window.innerHeight;

	//change default view on mobile + tablet
	if(window.innerWidth < 576){ //mobile
		defaultView = 'timeline4days';
		dateIncrementObj = {days: 4};
	}else if(window.innerWidth <= 768){ //tablet
		defaultView = 'timeline1weeks';
	}

	let calendarEl = document.getElementById('calendar');

    calendar = new FullCalendar.Calendar(calendarEl, {

        schedulerLicenseKey: 'CC-Attribution-NonCommercial-NoDerivatives',
        height: winHieght - 187,

        firstDay: 0,
        dateIncrement: dateIncrementObj,
        nextDayThreshold: '13:00',
        eventColor: defaultEventColor,
        eventTextColor: defCalEventTextColor,
        eventOrder: "start, id, title",
		eventResizableFromStart: false,
        initialView: defaultView,
        editable: true,
        resourcesInitiallyExpanded: expandResources,
		resourceAreaHeaderContent: 'Rooms',

		nowIndicator: false,
        resourceAreaWidth: resourceColumnWidth,
        refetchResourcesOnNavigate: true,
        resourceGroupField: resourceGroupBy,

        customButtons: {
            refresh: {
              text: 'Refresh',
              click: function() {
                calendar.refetchResources();
                calendar.refetchEvents();
              }
            },
            prevMonth: {
              click: function() {
                calendar.incrementDate({months: -1});
              }
            },
            nextMonth: {
              click: function() {
                calendar.incrementDate({months: 1});
              }
            }
        },

        buttonIcons: {
			nextMonth: 'chevrons-right',
			prevMonth: 'chevrons-left'
		},

        views: {
        	timeline4days: {
                type: 'resourceTimeline',
                slotDuration: {days: 1},
                slotLabelFormat: {weekday: 'short', day: 'numeric'},
                duration: {days: 4 },
                buttonText: '4 Days'
            },
            timeline1weeks: {
                type: 'resourceTimeline',
                slotDuration: {days: 1},
                slotLabelFormat: {weekday: 'short', day: 'numeric'},
                duration: {weeks: 1 },
                buttonText: '1'
            },
            timeline2weeks: {
                type: 'resourceTimeline',
                slotLabelFormat: {weekday: 'short', day: 'numeric'},
                slotDuration: {days: 1},
                duration: {weeks: 2 },
                buttonText: '2'
            },
            timeline3weeks: {
                type: 'resourceTimeline',
                slotLabelFormat: {weekday: 'short', day: 'numeric'},
                slotDuration: {days: 1},
                duration: {weeks: 3 },
                buttonText: '3'
            },
            timeline4weeks: {
                type: 'resourceTimeline',
                slotLabelFormat: [
					{month: 'short', year: 'numeric'},
					{day: 'numeric'}
				],
                slotDuration: {days: 7},
                duration: {weeks: 26 },
                buttonText: '26'
            }
        },

        headerToolbar: {
            left: 'title',
            center: '',
            right: 'timeline1weeks,timeline2weeks,timeline3weeks,timeline4weeks refresh,today prevMonth,prev,next,nextMonth'
        },

        slotLabelClassNames: 'hhk-fc-slot-title',

		slotLaneClassNames: function (info) {
			if (info.isToday) {
				return 'hhk-fcslot-today';
			} else {
				let strDay = info.date.getFullYear() + '-' + (info.date.getMonth() + 1) + '-' + info.date.getDate();

				if (holidays.includes(strDay)) {
					return 'hhk-fcslot-holiday';
				}
                if(closedDays.includes(info.date.getDay())){
                    return 'fc-cell-shaded';
                }
			}
		},

        loading: function (isLoading) {

            if (isLoading) {
                $('#pCalLoad').show();
                $('#spnGotoDate').hide();
            } else {
                $('#pCalLoad').hide();
                $('#spnGotoDate').show();
            }
        },
		resourceOrder: 'Util_Priority,idResc',
        resources: {
                url: 'ws_calendar.php',
                extraParams: {
                    cmd: 'resclist',
                    gpby: $('#selRoomGroupScheme').val()
                },
        },

        resourceLabelDidMount: function(info) {

            info.el.style.background = info.resource.extendedProps.bgColor;
            info.el.style.color = info.resource.extendedProps.textColor;

            if (info.resource.extendedProps.idResc > 0) {

				if(info.resource.extendedProps.hoverText){
                	info.el.title = info.resource.extendedProps.hoverText;
                }else{
                	info.el.title = 'Maximum Occupants: ' + info.resource.extendedProps.maxOcc;
                }
                info.el.style.cursor = 'pointer'
				// Bring up OOS dialog
                info.el.onclick = function(){ getStatusEvent(info.resource.extendedProps.idResc, 'resc', info.resource.title) };
            }

        },

        eventOverlap: function (stillEvent, movingEvent) {

            if (stillEvent.idVisit === movingEvent.idVisit) {
                return true;
            }
            return false;
        },

        events: {
            url: 'ws_calendar.php?cmd=eventlist',
            failure: function() {
                $('#pCalError').text('Error getting events!').show();
            }
        },

        eventDrop: function (info) {

            $(".hhk-alert").hide();

            let event = info.event;

            // visit
            if (event.extendedProps.idVisit > 0 && info.delta.days !== 0) {
                if (confirm('Move Visit to a new start date?')) {
                    moveVisit('visitMove', event.extendedProps.idVisit, event.extendedProps.Span, info.delta.days, info.delta.days);
                	return;
                }
            }

            // Reservation
            if (event.extendedProps.idReservation > 0) {

                let resources = event.getResources();
                let resource = resources[0];

                // move by date?
                if (info.delta.days !== 0 && resource.extendedProps.idResc === event.extendedProps.idResc) {

                    if (confirm('Move Reservation to a new start date?')) {
                        moveVisit('reservMove', event.extendedProps.idReservation, 0, info.delta.days, info.delta.days);
                        return;
                    }
                } else if (info.delta.days !== 0) {

                    if (confirm('Move Reservation to a new start date?')) {
                        moveVisit('reservMove', event.extendedProps.idReservation, 0, info.delta.days, info.delta.days, false);
                    }
				}

                // Change rooms?
                if (resource.extendedProps.idResc !== event.extendedProps.idResc) {

                	let mssg = 'Move Reservation to a new room?';

                	if (resource.extendedProps.idResc == 0) {
                		mssg = 'Move Reservation to the waitlist?'
                	}

                    if (confirm(mssg)) {
                        if (setRoomTo(event.extendedProps.idReservation, resource.extendedProps.idResc)) {
                        	return;
                        }
                    }
                }
            }
            info.revert();
        },

        eventResize: function (info) {
            $(".hhk-alert").hide();

            if (info.endDelta === undefined) {
                info.revert();
                return;
            }
            if (info.event.extendedProps.idVisit > 0) {
                if (confirm('Move check out date?')) {
                    moveVisit('visitMove', info.event.extendedProps.idVisit, info.event.extendedProps.Span, 0, info.endDelta.days);
                    return;
                }
            }
            if (info.event.extendedProps.idReservation > 0) {
                if (confirm('Move expected end date?')) {
                    moveVisit('reservMove', info.event.extendedProps.idReservation, 0, 0, info.endDelta.days);
                    return;
                }
            }
            info.revert();
        },

        eventClick: function (info) {
            $(".hhk-alert").hide();

            // OOS events
            if (info.event.extendedProps.kind && info.event.extendedProps.kind === 'oos') {
                getStatusEvent(info.event.extendedProps.idResc, 'resc', info.event.title);
                return;
            }

            // reservations
            if (info.event.extendedProps.idReservation && info.event.extendedProps.idReservation > 0) {
                if (info.jsEvent.target.classList.contains('hhk-schrm')) {
                    getRoomList(info.event.extendedProps.idReservation, info.jsEvent.target.id, info.jsEvent.target);
                    return;
                } else {
                    window.location.assign(resvPageName + '?rid=' + info.event.extendedProps.idReservation);
                }
            }

            // visit
            if (info.event.extendedProps.idVisit && info.event.extendedProps.idVisit > 0) {
                let buttons = {
                    "Show Statement": function() {
                        window.open('ShowStatement.php?vid=' + info.event.extendedProps.idVisit, '_blank');
                    },
                    "Show Registration Form": function() {
                        window.open('ShowRegForm.php?rid=' + info.event.extendedProps.idResv , '_blank');
                    },
                    "Save": function () {
                        saveFees(0, info.event.extendedProps.idVisit, info.event.extendedProps.Span, true, 'register.php');
                    },
                    "Cancel": function () {
                        $(this).dialog("close");
                    }
                };
                viewVisit(0, info.event.extendedProps.idVisit, buttons, 'Edit Visit #' + info.event.extendedProps.idVisit + '-' + info.event.extendedProps.Span, '', info.event.extendedProps.Span);
            }

        },

        eventContent: function (info) {
            let titleEl = document.createElement('span');
            titleEl.appendChild(document.createTextNode(info.event.title));
            titleEl.classList.add("ml-1");

			if (info.event.extendedProps.idReservation !== undefined) {

				let chooserEl = document.createElement('Span');
				chooserEl.classList.add("hhk-schrm", "ui-icon", "ui-icon-arrowthick-2-n-s");
				chooserEl.style.backgroundColor = '#fff';
				chooserEl.style.border = '0px solid black';
				chooserEl.id = info.event.extendedProps.idResc

				let arrayOfNodes = [chooserEl, titleEl];
				return { domNodes: arrayOfNodes }
            } else {
                return { domNodes: [titleEl] };
            }
		},

        eventDidMount: function (info) {

            if (hindx === undefined || hindx === 0 || info.event.extendedProps.idHosp === undefined || info.event.extendedProps.idAssoc == hindx || info.event.extendedProps.idHosp == hindx) {

                let resource = calendar.getResourceById("id-" + info.event.extendedProps.idResc);

                //set arrow color
                if (!info.textColor || (typeof info.textColor === 'string' && (info.textColor.toLowerCase() == "white" || info.textColor.toLowerCase() == "#ffffff"))) {
                    info.el.classList.add("hhk-event-light");
                }

                // Reservations
                if (info.event.extendedProps.idReservation !== undefined) {

                    info.el.title = info.event.extendedProps.fullName + (info.event.extendedProps.idResc > 0 ? ', ' + resource.title : '') +  ', ' + info.event.extendedProps.resvStatus + (shoHospitalName ? ', ' + info.event.extendedProps.hospName : '');

                    // update border for uncommitted reservations.
                    if (info.event.extendedProps.status === 'uc') {
                        info.el.style.border = "3px dashed black";
                        info.el.style.padding = '1px 0';
                    } else {
                        info.el.style.border = "3px solid black";
                        info.el.style.padding = '1px 0';
                    }

                    //2nd ribbon color
                    if (info.event.extendedProps.backBorderColor != '') {

                    	info.el.style.cssText += 'box-shadow: ' + info.event.extendedProps.backBorderColor + ' 0px 9px 0 0; margin-bottom:10px;';

                    }

                // visits
                } else if (info.event.extendedProps.idVisit !== undefined) {

                    if (info.event.extendedProps.vStatusCode == 'a') {
                    	info.el.title = info.event.extendedProps.fullName + ', Room: ' + resource.title + ', Status: ' + info.event.extendedProps.visitStatus + ', ' + info.event.extendedProps.guests + (info.event.extendedProps.guests > 1 ? ' ' + visitorLabel + 's': ' '+ visitorLabel) + (shoHospitalName ? ', ' + hospTitle + ': ' + info.event.extendedProps.hospName : '');
					} else {
                    	info.el.title = info.event.extendedProps.fullName + ', Room: ' + resource.title + ', Status: ' + info.event.extendedProps.visitStatus + (shoHospitalName ? ', ' + hospTitle + ': ' + info.event.extendedProps.hospName : '');
                    }

                    if (info.event.extendedProps.extended !== undefined && info.event.extendedProps.extended) {
                    	info.el.classList.remove('fc-event-end'); //trick fc into adding right arrow
                    }

                    //2nd ribbon color
                    if (info.event.extendedProps.backBorderColor != '') {

                    	info.el.style.cssText += 'box-shadow: ' + info.event.extendedProps.backBorderColor + ' 0px 9px 0 0; margin-bottom:10px;';
                    }

                // Out of service
                } else if (info.event.extendedProps.kind === 'oos') {
                    info.el.title = info.event.extendedProps.reason;
                }

				info.event.setProp('display', 'auto');
            } else {
                info.event.setProp('display', 'none');
            }
        }
    });

    calendar.render();

    //redraw calendar after finishing window resize
	var resizeTimer;
	window.onresize = function(){
	  clearTimeout(resizeTimer);
	  resizeTimer = setTimeout(calendar.setOption('height', window.innerHeight - 187), 100);
	};

    if ($('.btnHosp').length > 0) {
        $('.btnHosp').click(function (e) {
        	e.preventDefault();
            $(".hhk-alert").hide();
            $('.btnHosp').removeClass("hospActive");
            $(this).addClass("hospActive");
            hindx = parseInt($(this).data('id'), 10);
            if (isNaN(hindx))
                hindx = 0;
            calendar.refetchEvents();
            //$(this).css('border', 'solid 3px black').css('font-size', '120%');
        });
    }

    $('#btnFeesGo').click(function () {
        $(".hhk-alert").hide();
        let stDate = $('#txtfeestart').datepicker("getDate");
        if (stDate === null) {
            $('#txtfeestart').addClass('ui-state-highlight');
            flagAlertMessage('Enter start date', 'alert');
            return;
        } else {
            $('#txtfeestart').removeClass('ui-state-highlight');
        }
        let edDate = $('#txtfeeend').datepicker("getDate");
        if (edDate === null) {
            edDate = new Date();
        }
        let statuses = $('#selPayStatus').val() || [];
        let ptypes = $('#selPayType').val() || [];

        let parms = {
            cmd: 'actrpt',
            start: stDate.toDateString(),
            end: edDate.toDateString(),
            st: statuses,
            pt: ptypes
        };

        if ($('#fcbdinv').prop('checked') !== false) {
            parms['sdinv'] = 'on';
        }

        $('#rptFeeLoading').show();

        parms.fee = 'on';
        $.post('ws_resc.php', parms,
            function (data) {
                $('#rptFeeLoading').hide();
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
                    flagAlertMessage(data.error, 'error');

                } else if (data.success) {

                    $('#rptfeediv').remove();
                    $('#vfees').append($('<div id="rptfeediv"/>').append($(data.success)));

                    // Set up controls for table.
                    paymentsTable('feesTable', 'rptfeediv', refreshPayments);

                    // Hide refresh button.
                    $('#btnPayHistRef').hide();

                }
            }
        });
    });

    $('#btnInvGo').click(function () {
        let statuses = ['up'];
        let parms = {
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
                        flagAlertMessage(data.error, 'error');

                    } else if (data.success) {

                        $('#rptInvdiv').remove();
                        $('#vInv').append($('<div id="rptInvdiv"/>').append($(data.success)));
                        $('#rptInvdiv .gmenu').menu({
           					focus:function(e, ui){
           						$("#rptInvdiv .gmenu").not(this).menu("collapseAll", null, true);
           					}
           				});

                        // Bring up payment box
                        $('#rptInvdiv').on('click', '.invLoadPc', function (event) {
                            event.preventDefault();
                            $("#divAlert1, #paymentMessage").hide();
                            invLoadPc($(this).data('name'), $(this).data('id'), $(this).data('iid'));
                        });

                        // Set the billing date
                        $('#rptInvdiv').on('click', '.invSetBill', function (event) {
                            event.preventDefault();
                            $(".hhk-alert").hide();
                            invSetBill($(this).data('inb'), $(this).data('name'), 'div#setBillDate', '#trBillDate' + $(this).data('inb'), $('#trBillDate' + $(this).data('inb')).text(), $('#divInvNotes' + $(this).data('inb')).text(), '#divInvNotes' + $(this).data('inb'));
                        });

                        // Handles several actions
                        $('#rptInvdiv').on('click', '.invAction', function (event) {
                            event.preventDefault();
                            let invContainer = '#rptInvdiv';

                            $(".hhk-alert").hide();

                            // Delete invoice
                            if ($(this).data('stat') == 'del') {
                                if (!confirm('Delete Invoice ' + $(this).data('inb') + ($(this).data('payor') != '' ? ' for ' + $(this).data('payor') : '') + '?')) {
                                    return;
                                }
                                invContainer = '';
                            }

                            // Check for email
                            if ($(this).data('stat') === 'vem') {
                                    window.open('ShowInvoice.php?invnum=' + $(this).data('inb'));
                                    return;
                            }

                            invoiceAction($(this).data('iid'), $(this).data('stat'), $(this).prop('id'), invContainer, true);
                            $('#rptInvdiv .gmenu').menu("collapse");
                        });

                        $('#InvTable').dataTable({
                            'columnDefs': [
                                {'targets': [2,4],
                                 'type': 'date',
                                 'render': function ( data, type, row ) {return dateRender(data, type);}
                                }
                             ],
                            "dom": '<"top"if><"hhk-overflow-x hhk-tbl-wrap"rt><"bottom"lp><"clear">',
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

    $('#btnPrtDaily').button().click(function() {
        $("#divdaily").printArea();
    });

    $('#btnRefreshDaily').button().click(function() {
        let tbl = $('#daily').DataTable();
        tbl.ajax.reload();
    });

    $('#txtGotoDate').change(function () {
        $(".hhk-alert").hide();
        calendar.gotoDate($(this).datepicker('getDate'));
    });

    // Capture room Grouping schema change event.
    $('#selRoomGroupScheme').change(function () {
        $('#divRoomGrouping').hide();
        calendar.setOption('resourceGroupField', $(this).val());
    });

    if (rctMkup !== '') {
        showReceipt('#pmtRcpt', rctMkup, 'Payment Receipt');
    }

    $('#mainTabs').tabs({

        beforeActivate: function (event, ui) {
            if (ui.newTab.prop('id') === 'liInvoice') {
                $('#btnInvGo').click();
            }
            if (ui.newTab.prop('id') === 'liDaylog' && !$dailyTbl) {
                $dailyTbl = $('#daily').DataTable({
                   ajax: {
                       url: 'ws_resc.php?cmd=getHist&tbl=daily',
                       dataSrc: 'daily'
                   },
                   order: [[ 0, 'asc' ]],
                   columns: dailyCols,
                   infoCallback: function( settings, start, end, max, total, pre ) {
                        return "Prepared: " + dateRender(new Date().toISOString(), 'display', 'ddd, MMM D YYYY, h:mm a');
                  },
                  "dom": '<"top"if><\"hhk-overflow-x hhk-tbl-wrap\"rt><"bottom ui-toolbar ui-helper-clearfix"lp>',
                });
            }
            if(ui.newTab.prop('id') === 'liStaffNotes'){
            	var staffNoteCats = JSON.parse($('#staffNoteCats').val());

            	$('.staffNotesDiv').empty().notesViewer({
					linkType: 'staff',
					newNoteAttrs: {id:'staffNewNote', name:'staffNewNote'},
					newNoteLocation:'top',
					defaultLength: 25,
					defaultLengthMenu: [[5,10,25,50],["5","10","25","50"]],
					alertMessage: function(text, type) {
					    flagAlertMessage(text, type);
					},
					staffNoteCats: staffNoteCats,
			    });
            }

            if(ui.newTab.prop('id') === 'liCal'){
            	calendar.refetchEvents();
            }
        },

        active: defaultTab
    });


    // Calendar date goto button.
    $('#calendar .fc-header-toolbar .fc-toolbar-chunk:nth-child(2)').html($('#divGoto').show());

    //hide week buttons on mobile
    $('#calendar .fc-header-toolbar .fc-toolbar-chunk:nth-child(3) .fc-button-group:nth-child(1)').addClass('hideMobile');

	$('#hhk-loading-spinner').hide();
	$('#mainTabs').show();

	//referralViewer
    if (useOnlineReferral) {
	    $.ajax({
            url: 'ws_resc.php',
            dataType: 'JSON',
            type: 'get',
            data: {
                cmd: 'listforms',
                totalsonly: 'true'
            },
            success: function( data ){
                if(data.totals){
                    $('#vreferrals').referralViewer({statuses: data.totals, labels: {patient: patientLabel, referralFormTitle: referralFormTitleLabel, reservation: reservationLabel}});
                    $("#spnNumReferral").text(data.totals.n.count);

                }
            }
        });
    }

    $('#curres').DataTable({
       ajax: {
           url: 'ws_resc.php?cmd=getHist&tbl=curres',
           dataSrc: 'curres'
       },
       drawCallback: function (settings) {
           let ncur = this.api().rows().data().length;
           $('#spnNumCurrent').text(this.api().rows().data().length);
           $('#spnCurrentS').text('s');
           if (ncur == 1) {
               $('#spnCurrentS').text('');
           }
           $('#curres .gmenu').menu({
           		focus:function(e, ui){
           			$("#curres .gmenu").not(this).menu("collapseAll", null, true);
           		}
           });

           $("#curres .btnShowVisitMsgs").off('click');
            $("#curres .btnShowVisitMsgs").each(function () {
                $(this).smsDialog({ "visitId": $(this).data('vid'), "spanId": $(this).data("span") });
            });
       },
       columns: cgCols,
       "buttons": getDtBtns("Current " + visitorLabel + "s - " + moment().format("MMM D, YYYY"), false, "hhk-strip-links"),
       "dom": '<"top"Bif><\"hhk-overflow-x\"rt><"bottom ui-toolbar ui-helper-clearfix"lp>',
       autoWidth:false,
    });


    $('#reservs').DataTable({
       ajax: {
           url: 'ws_resc.php?cmd=getHist&tbl=reservs',
           dataSrc: 'reservs'
       },
       drawCallback: function (settings) {
           $('#spnNumConfirmed').text(this.api().rows().data().length);
           $('#reservs .gmenu').menu({
           		focus:function(e, ui){
           			$("#reservs .gmenu").not(this).menu("collapseAll", null, true);
           		}
           });

           $("#reservs .btnShowResvMsgs").off('click');
           $("#reservs .btnShowResvMsgs").each(function () {
            $(this).smsDialog({ "resvId": $(this).data('rid') });
        });
       },
       columns: rvCols,
       "buttons": getDtBtns(reservationTabLabel + " - " + moment().format("MMM D, YYYY")),
       "dom": '<"top"Bif><\"hhk-overflow-x\"rt><"bottom ui-toolbar ui-helper-clearfix"lp>',
    });

    if ($('#unreserv').length > 0) {
        $('#unreserv').DataTable({
           ajax: {
               url: 'ws_resc.php?cmd=getHist&tbl=unreserv',
               dataSrc: 'unreserv'
           },
           drawCallback: function (settings) {
                $('#spnNumUnconfirmed').text(this.api().rows().data().length);
                $('#unreserv .gmenu').menu({
           			focus:function(e, ui){
           				$("#unreserv .gmenu").not(this).menu("collapseAll", null, true);
           			}
                });
               
               $("#unreserv .btnShowResvMsgs").off('click');
                $("#unreserv .btnShowResvMsgs").each(function () {
                    $(this).smsDialog({ "resvId": $(this).data('rid') });
                });
           },
           columns: rvCols,
           "buttons": getDtBtns(unconfirmedResvTabLabel + " - " + moment().format("MMM D, YYYY")),
       		"dom": '<"top"Bif><\"hhk-overflow-x\"rt><"bottom ui-toolbar ui-helper-clearfix"lp>',
        });
    }

    $('#waitlist').DataTable({
       ajax: {
           url: 'ws_resc.php?cmd=getHist&tbl=waitlist',
           dataSrc: 'waitlist'
       },
       order: [[ (showCreatedDate ? 5 : 3), 'asc' ]],
       drawCallback: function () {
            $('#spnNumWaitlist').text(this.api().rows().data().length);
            $('#waitlist .gmenu').menu({
           		focus:function(e, ui){
           			$("#waitlist .gmenu").not(this).menu("collapseAll", null, true);
           		}
            });
           
           $("#waitlist .btnShowResvMsgs").off('click');
           $("#waitlist .btnShowResvMsgs").each(function () {
               $(this).smsDialog({ "resvId": $(this).data('rid') });
           });
       },
       columns: wlCols,
       "buttons": getDtBtns("Waitlist - " + moment().format("MMM D, YYYY")),
       "dom": '<"top"Bif><\"hhk-overflow-x\"rt><"bottom ui-toolbar ui-helper-clearfix"lp>',
    });

    //move datatable buttons to title row
    $("#vstays, #vresvs, #vuncon, #vdaily, #vwls").each(function(){
    	$(this).find('h3 span:first').after($(this).find(".dt-buttons button").css("font-size", "0.9em").addClass("ml-5"));
	});
});
