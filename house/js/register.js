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
// Change reservation room.
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
            flagAlertMessage(data.warning, 'alert');
            return false;
        }
        if (data.msg && data.msg !== '') {
            flagAlertMessage(data.msg, 'info');
        }
        $('#calendar').fullCalendar('refetchEvents');
        refreshdTables(data);
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
                $('#calendar').fullCalendar('refetchEvents');
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
//        "Save PSG": function() {
//            saveFees(id, idVisit, span, false, 'register.php');
//        },
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
function ckOut(gname, id, idVisit, span) {
    var buttons = {
        "Show Statement": function() {
            window.open('ShowStatement.php?vid=' + idVisit, '_blank');
        },
        "Show Registration Form": function() {
            window.open('ShowRegForm.php?vid=' + idVisit + '&span=' + span, '_blank');
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
            window.open('ShowRegForm.php?vid=' + idVisit + '&span=' + span, '_blank');
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
                $('#calendar').fullCalendar('refetchResources');
                $('#calendar').fullCalendar('refetchEvents');
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
	            
				$('#calendar').fullCalendar('refetchEvents');
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
                flagAlertMessage(data.error, 'error');
                
            } else if (data.success) {
                $('#calendar').fullCalendar('refetchEvents');
                flagAlertMessage(data.success, 'success');
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
                flagAlertMessage(data.error, 'error');
                return;
            }
            if (data.container) {
                let contr = $(data.container);
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

var isGuestAdmin,
    pmtMkup,
    rctMkup,
    defaultTab,
    resourceGroupBy,
    resourceColumnWidth,
    patientLabel,
    guestLabel,
    visitorLabel,
    referralFormTitleLabel,
    reservationLabel,
    challVar,
    defaultView,
    defaultEventColor,
    defCalEventTextColor,
    calDateIncrement,
    dateFormat,
    fixedRate,
    resvPageName,
    showCreatedDate,
    expandResources,
    shoHospitalName,
    showRateCol,
    hospTitle,
    showDiags,
    showLocs,
    locationTitle,
    diagnosisTitle,
    showWlNotes,
    showCharges,
    wlTitle,
    cgCols,
    rvCols,
    wlCols,
    dailyCols;

$(document).ready(function () {
    "use strict";
    var hindx = 0;
    var calStartDate = new moment();
    
    isGuestAdmin = $('#isGuestAdmin').val();
    pmtMkup = $('#pmtMkup').val();
    rctMkup = $('#rctMkup').val();
    defaultTab = $('#defaultTab').val();
    resourceGroupBy = $('#resourceGroupBy').val();
    resourceColumnWidth = $('#resourceColumnWidth').val();
    patientLabel = $('#patientLabel').val();
    visitorLabel = $('#visitorLabel').val();
    guestLabel = $('#guestLabel').val();
    referralFormTitleLabel = $('#referralFormTitleLabel').val();
    reservationLabel = $('#reservationLabel').val();
    challVar = $('#challVar').val();
    defaultView = $('#defaultView').val();
    defaultEventColor = $('#defaultEventColor').val();
    defCalEventTextColor = $('#defCalEventTextColor').val();
    calDateIncrement = $('#calDateIncrement').val();
    dateFormat = $('#dateFormat').val();
    fixedRate = $('#fixedRate').val();
    resvPageName = $('#resvPageName').val();
    showCreatedDate = $('#showCreatedDate').val();
    expandResources = $('#expandResources').val();
    shoHospitalName = $('#shoHospitalName').val();
    showRateCol = $('#showRateCol').val();
    hospTitle = $('#hospTitle').val();
    showDiags = $('#showDiags').val();
    showLocs = $('#showLocs').val();
    locationTitle = $('#locationTitle').val();
    diagnosisTitle = $('#diagnosisTitle').val();
    showWlNotes = $('#showWlNotes').val();
    wlTitle = $('#wlTitle').val();
    showCharges = $('#showCharges').val();

    // Current Guests
    cgCols = [
            {data: 'Action', title: 'Action', sortable: false, searchable:false},
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

    // Reservations
    rvCols = [
            {data: 'Action', title: 'Action', sortable: false, searchable:false},
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
    wlCols = [
            {data: 'Action', title: 'Action', sortable: false, searchable:false},
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
    dailyCols = [
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




    $.widget( "ui.autocomplete", $.ui.autocomplete, {
        _resizeMenu: function() {
            let ul = this.menu.element;
            ul.outerWidth( Math.max(
                    ul.width( "" ).outerWidth() + 1,
                    this.element.outerWidth()
            ) * 1.1 );
        }
    });
    
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
        ckOut($(this).data('name'), $(this).data('id'), $(this).data('vid'), $(this).data('spn'));
    });
    $('#vstays').on('click', '.stvisit', function (event) {
        event.preventDefault();
        $(".hhk-alert").hide();
        editVisit($(this).data('name'), $(this).data('id'), $(this).data('vid'), $(this).data('spn'));
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

    $(document).mousedown(function (event) {
        let target = $(event.target);
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
    
    $('#chgRoomDialog').dialog({
        autoOpen: false,
        resizable: true,
        modal: true
    });
    $('#pmtRcpt').dialog({
        autoOpen: false,
        resizable: true,
        width: 530,
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

    createAutoComplete($('#txtsearch'), 3, {cmd: "role",  mode: 'mo', gp:'1'}, 
        function(item) { 
            let cid = item.id;
            if (cid > 0) {
                window.location.assign("GuestEdit.php?id=" + cid);
            }
        },
        false
    );

    var dateIncrementObj = null;

    if (calDateIncrement > 0 && calDateIncrement < 5) {
        dateIncrementObj = {weeks: calDateIncrement};
    }

    $('#selRoomGroupScheme').val(resourceGroupBy);

    var winHieght = window.innerHeight;

    $('#calendar').fullCalendar({
        height: winHieght - 175,
        //aspectRatio: 2.2,
        themeSystem: 'jquery-ui',
        allDay: true,
        firstDay: 0,
        dateIncrement: dateIncrementObj,
        nextDayThreshold: '13:00',
        schedulerLicenseKey: 'CC-Attribution-NonCommercial-NoDerivatives',
        eventColor: defaultEventColor,
        eventTextColor: defCalEventTextColor,
        
        customButtons: {
            refresh: {
              text: 'Refresh',
              click: function() {
                $('#calendar').fullCalendar( 'refetchResources' );
                $('#calendar').fullCalendar( 'refetchEvents' );
              }
            },
            prevMonth: {
              click: function() {
                $('#calendar').fullCalendar('incrementDate', {months: -1});
              },
              themeIcon: 'ui-icon-seek-prev'
            },
            nextMonth: {
              click: function() {
                $('#calendar').fullCalendar('incrementDate', {months: 1});
              },
              themeIcon: 'ui-icon-seek-next'
            },
            setup: {
              click: function() {
                $('#divRoomGrouping').show('fade');
              },
              themeIcon: 'ui-icon-gear'
            }
        },

        views: {
            timeline1weeks: {
                type: 'timeline',
                slotDuration: {days: 1},
                duration: {weeks: 1 },
                buttonText: '1'
            },
            timeline2weeks: {
                type: 'timeline',
                slotDuration: {days: 1},
                duration: {weeks: 2 },
                buttonText: '2'
            },
            timeline3weeks: {
                type: 'timeline',
                slotDuration: {days: 1},
                duration: {weeks: 3 },
                buttonText: '3'
            },
            timeline4weeks: {
                type: 'timeline',
                slotDuration: {days: 7},
                duration: {weeks: 26 },
                buttonText: '26'
            }
        },
        
        viewRender: function (view, element) {
            defaultView = view.name;
            calStartDate = $('#calendar').fullCalendar('getDate');
        },

        header: {
            left: 'setup timeline1weeks,timeline2weeks,timeline3weeks,timeline4weeks title',
            center: '',
            right: 'refresh,today prevMonth,prev,next,nextMonth'
        },

        defaultView: defaultView,
        editable: true,
        resourcesInitiallyExpanded: expandResources,
        resourceLabelText: 'Rooms',
        resourceAreaWidth: resourceColumnWidth,
        refetchResourcesOnNavigate: false,
        resourceGroupField: resourceGroupBy,
        loading: function (isLoading, View) {

            if (isLoading) {
                $('#pCalLoad').show();
                $('#spnGotoDate').hide();
            } else {
                $('#pCalLoad').hide();
                $('#spnGotoDate').show();
            }
        },

        resources: function (callback) {
            $.ajax({
                url: 'ws_calendar.php',
                dataType: 'JSON',
                data: {
                    cmd: 'resclist',
                    start: calStartDate.format('YYYY-MM-DD'),
                    view: defaultView,
                    gpby: $('#selRoomGroupScheme').val()
                },
                success: function (data) {
                    callback(data);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $('#pCalError').text('Error getting resources: ' + errorThrown).show();
                }
            });
        },

        resourceGroupText: function (txt) {
            return txt;
        },

        resourceRender: function(resourceObj, labelTds, bodyTds) {

            labelTds.css('background', resourceObj.bgColor)
                    .css('color', resourceObj.textColor);

            if (resourceObj.id > 0) {

                let cont = resourceObj.title 
                        + (resourceObj.maxOcc == 0 ? '' : ' (' + resourceObj.maxOcc + ')');

                labelTds.prop('title', cont);

                labelTds.click(function () {
                    // Bring up OOS dialog
                    getStatusEvent(resourceObj.id, 'resc', resourceObj.title);
                });
            }
        },

        eventOverlap: function (stillEvent, movingEvent) {

            if (stillEvent.kind === 'bak' || stillEvent.idVisit === movingEvent.idVisit) {
                return true;
            }
            return false;
        },

        events: {
            url: 'ws_calendar.php?cmd=eventlist',
            error: function(jqXHR, textStatus, errorThrown) {
                $('#pCalError').text('Error getting events: ' + errorThrown).show();
            }
        },
        
        eventDrop: function (event, delta, revertFunc) {
            
            $(".hhk-alert").hide();
            
            // visit
            if (event.idVisit > 0 && delta.asDays() !== 0) {
                if (confirm('Move Visit to a new start date?')) {
                    moveVisit('visitMove', event.idVisit, event.Span, delta.asDays(), delta.asDays());
                }
            }
            
            // Reservation
            if (event.idReservation > 0) {
                
                // move both?
//                if (delta.asDays() > 0 && event.resourceId !== event.idResc) {
//                    
//                }
                
                // move by date?
                if (delta.asDays() !== 0) {
                    if (confirm('Move Reservation to a new start date?')) {
                        moveVisit('reservMove', event.idReservation, 0, delta.asDays(), delta.asDays());
                        return;
                    }
                }
                
                // Change rooms?
                if (event.resourceId !== event.idResc) {
                	
                	let mssg = 'Move Reservation to a new room?';
                	
                	if (event.resourceId == 0) {
                		mssg = 'Move Reservation to the waitlist?'
                	}
                	
                    if (confirm(mssg)) {
                        if (setRoomTo(event.idReservation, event.resourceId)) {
                        	return;
                        }
                    }
                }
            }
            revertFunc();
        },

        eventResize: function (event, delta, revertFunc) {
            $(".hhk-alert").hide();
            
            if (delta === undefined) {
                revertFunc();
                return;
            }
            if (event.idVisit > 0) {
                if (confirm('Move check out date?')) {
                    moveVisit('visitMove', event.idVisit, event.Span, 0, delta.asDays());
                    return;
                }
            }
            if (event.idReservation > 0) {
                if (confirm('Move expected end date?')) {
                    moveVisit('reservMove', event.idReservation, 0, 0, delta.asDays());
                    return;
                }
            }
            revertFunc();
        },

        eventClick: function (calEvent, jsEvent) {
            $(".hhk-alert").hide();

            // OOS events
            if (calEvent.kind && calEvent.kind === 'oos') {
                getStatusEvent(calEvent.resourceId, 'resc', calEvent.title);
                return;
            }

            // reservations
            if (calEvent.idReservation && calEvent.idReservation > 0) {
                if (jsEvent.target.classList.contains('hhk-schrm')) {
                    getRoomList(calEvent.idReservation, jsEvent.target.id);
                    return;
                } else {
                    window.location.assign(resvPageName + '?rid=' + calEvent.idReservation);
                }
            }

            // visit
            if (calEvent.idVisit && calEvent.idVisit > 0) {
                let buttons = {
                    "Show Statement": function() {
                        window.open('ShowStatement.php?vid=' + calEvent.idVisit, '_blank');
                    },
                    "Show Registration Form": function() {
                        window.open('ShowRegForm.php?vid=' + calEvent.idVisit + '&span=' + calEvent.Span, '_blank');
                    },
                    "Save": function () {
                        saveFees(0, calEvent.idVisit, calEvent.Span, true, 'register.php');
                    },
                    "Cancel": function () {
                        $(this).dialog("close");
                    }
                };
                viewVisit(0, calEvent.idVisit, buttons, 'Edit Visit #' + calEvent.idVisit + '-' + calEvent.Span, '', calEvent.Span);
            }
        },

        eventRender: function (event, element) {

            if (hindx === undefined || hindx === 0 || event.idHosp === undefined || event.idAssoc == hindx || event.idHosp == hindx) {

                let resource = $('#calendar').fullCalendar('getResourceById', event.resourceId);

                // Reservations
                if (event.idReservation !== undefined) {

                    element.prop('title', event.fullName + (event.resourceId > 0 ? ', Room: ' + resource.title : '') +  ', Status: ' + event.resvStatus + (shoHospitalName ? ', ' + hospTitle + ': ' + event.hospName : ''));

                    // update border for uncommitted reservations.
                    if (event.status === 'uc') {
                        element.css('border', '2px dashed black').css('padding', '1px 0');
                    } else {
                        element.css('border', '2px solid black').css('padding', '1px 0');
                    }

                // visits
                } else if (event.idVisit !== undefined) {
                    
                    element.prop('title', event.fullName + ', Room: ' + resource.title + ', Status: ' + event.visitStatus + ', ' + event.guests + (event.guests > 1 ? ' '+visitorLabel+'s': ' '+visitorLabel) + (shoHospitalName ? ', ' + hospTitle + ': ' + event.hospName : ''));
                    
                    if (event.extended !== undefined && event.extended) {
                        element.find('div.fc-content')
                            .append($('<span style="float:right;margin-right:5px;" class="hhk-fc-title"/>'));
                    }

                // Out of service
                } else if (event.kind === 'oos') {
                    element.prop('title', event.reason);
                }

                element.show();
            } else {
                element.hide();
            }
        }
    });

    // disappear the pop-up room chooser.
    $(document).mousedown(function (event) {
        var target = $(event.target);
        if (target[0].id !== 'divRoomGrouping' && target[0].id !== 'selRoomGroupScheme') {
            $('#divRoomGrouping').hide();
        }
    });

    if ($('.spnHosp').length > 0) {
        $('.spnHosp').click(function () {
            $(".hhk-alert").hide();
            $('.spnHosp').css('border', 'solid 1px black').css('font-size', '100%');
            hindx = parseInt($(this).data('id'), 10);
            if (isNaN(hindx))
                hindx = 0;
            $('#calendar').fullCalendar('rerenderEvents');
            $(this).css('border', 'solid 3px black').css('font-size', '120%');
        });
    }

    $('#btnActvtyGo').click(function () {
        $(".hhk-alert").hide();
        let stDate = $('#txtactstart').datepicker("getDate");
        if (stDate === null) {
            $('#txtactstart').addClass('ui-state-highlight');
            flagAlertMessage('Enter start date', 'alert');
            return;
        } else {
            $('#txtactstart').removeClass('ui-state-highlight');
        }
        let edDate = $('#txtactend').datepicker("getDate");
        if (edDate === null) {
            edDate = new Date();
        }
        let parms = {
            cmd: 'actrpt',
            start: stDate.toLocaleDateString(),
            end: edDate.toLocaleDateString()
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
                        flagAlertMessage(data.error, 'error');

                    } else if (data.success) {
                        $('#rptdiv').remove();
                        $('#vactivity').append($('<div id="rptdiv"/>').append($(data.success)));
                        $('.hhk-viewvisit').css('cursor', 'pointer');
                        $('#rptdiv').on('click', '.hhk-viewvisit', function () {
                            if ($(this).data('visitid')) {
                                let parts = $(this).data('visitid').split('_');
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
                                window.location.assign('Reserve.php?rid=' + $(this).data('reservid'));
                            }
                        });
                    }
                }
            });
    });
    

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
                        $('#vInv').append($('<div id="rptInvdiv" style="min-height:500px;"/>').append($(data.success)));
                        $('#rptInvdiv .gmenu').menu({
           					focus:function(e, ui){
           						$("#rptInvdiv .gmenu").not(this).menu("collapseAll", null, true);
           					}
           				});
                        
                        $('#rptInvdiv').on('click', '.invLoadPc', function (event) {
                            event.preventDefault();
                            $("#divAlert1, #paymentMessage").hide();
                            invLoadPc($(this).data('name'), $(this).data('id'), $(this).data('iid'));
                        });
                        
                        $('#rptInvdiv').on('click', '.invSetBill', function (event) {
                            event.preventDefault();
                            $(".hhk-alert").hide();
                            invSetBill($(this).data('inb'), $(this).data('name'), 'div#setBillDate', '#trBillDate' + $(this).data('inb'), $('#trBillDate' + $(this).data('inb')).text(), $('#divInvNotes' + $(this).data('inb')).text(), '#divInvNotes' + $(this).data('inb'));
                        });
                        
                        $('#rptInvdiv').on('click', '.invAction', function (event) {
                            event.preventDefault();
                            $(".hhk-alert").hide();
                            
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
                            'columnDefs': [
                                {'targets': [2,4],
                                 'type': 'date',
                                 'render': function ( data, type, row ) {return dateRender(data, type);}
                                }
                             ],
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

    $('#btnPrtDaily').button().click(function() {
        $("#divdaily").printArea();
    });

    $('#btnRefreshDaily').button().click(function() {
        let tbl = $('#daily').DataTable();
        tbl.ajax.reload();
    });

    $('#txtGotoDate').change(function () {
        $(".hhk-alert").hide();
        calStartDate = new moment($(this).datepicker('getDate'));
        $('#calendar').fullCalendar( 'refetchResources' );
        $('#calendar').fullCalendar('gotoDate', calStartDate);
    });

    // Capture room Grouping schema change event.
    $('#selRoomGroupScheme').change(function () {
        $('#divRoomGrouping').hide();
        $('#calendar').fullCalendar('option', 'resourceGroupField', $(this).val());
        $('#calendar').fullCalendar( 'refetchResources' );
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
                  }
                });
            }
        },
        
        activate: function(event, ui) {
            if (ui.newTab.prop('id') === 'liCal') {
                $('#calendar').fullCalendar('render');
                // Calendar date goto button.
                $('#divGoto').position({
                        my: 'center top',
                        at: 'center top+8',
                        of: '#calendar',
                        within: '#calendar'
                });
            }
        },
        active: defaultTab
    });
    
    $('#mainTabs').show();

    // Calendar date goto button.
    $('#divGoto').position({
            my: 'center top',
            at: 'center top+8',
            of: '#calendar',
            within: '#calendar'
    });


	//referralViewer
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
	


    $('#curres').DataTable({
       ajax: {
           url: 'ws_resc.php?cmd=getHist&tbl=curres',
           dataSrc: 'curres'
       },
       drawCallback: function (settings) {
           $('#spnNumCurrent').text(this.api().rows().data().length);
           $('#curres .gmenu').menu({
           		focus:function(e, ui){
           			$("#curres .gmenu").not(this).menu("collapseAll", null, true);
           		}
           });
       },
       columns: cgCols
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
       },
       columns: rvCols,

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
           },
           columns: rvCols
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
       },
       columns: wlCols
    });

});
