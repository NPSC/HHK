/**
 * attpGrid.js
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 */
 
 var defaultView,
    defaultEventColor,
    defCalEventTextColor,
    isGuestAdmin,
    patientLabel,
    guestLabel,
    visitorLabel,
    reservationLabel,
    dateFormat,
    slotDuration = '00:30:00',
    slotStartTime = "14:00:00",
    slotEndTime = "20:00:00";


var winHieght = window.innerHeight - 170;

document.addEventListener('DOMContentLoaded', function() {
  var calendarEl = document.getElementById('calendar');

  var calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'timeGridFourDay',
    timeZone: 'local',
    initialDate: new Date(),
    expandRows: true,
    height: winHieght,
    allDaySlot: false,
    nowIndicator: true,
    slotDuration: slotDuration,
    slotMinTime: slotStartTime,
    slotMaxTime: slotEndTime,
    slotLabelInterval: slotDuration,
    forceEventDuration: true,
    defaultTimedEventDuration: slotDuration,
    eventDurationEditable: false,
    editable: true,
    displayEventTime: true,
    
    headerToolbar: {
      right: 'prev,next today',
      center: 'title',
      left: 'timeGridDay,timeGridFourDay,timeGridSevenDay,listWeek,dayGridWeek'
    },
    
    views: {
      timeGridFourDay: {
        type: 'timeGrid',
        duration: { days: 4 },
        buttonText: '4 day'
      },
      timeGridSevenDay: {
        type: 'timeGrid',
        duration: { days: 7 },
        buttonText: '7 day'
      }
    },
    
    events: {
	   url: 'ws_calendar.php',
	    method: 'GET',
	    extraParams: {
	      cmd: 'timeGrid'
	    },
	    failure: function() {
	      $('#pCalError').text('Error getting events: ' + errorThrown).show();
	    }

	},
	
	eventClick: function(info) {
		
		 alert('Event: ' + info.event.title);
	},
	
	eventDrop: function(info) {
		
		
	    if (!confirm("Are you sure about this change?")) {
	      info.revert();
	    }

	}
  });

  calendar.render();
});