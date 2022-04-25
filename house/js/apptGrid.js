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
    isGuestAdmin,
    reservationLabel,
    dateFormat,
    slotDuration,
    slotStartTime,
    slotEndTime;


var winHieght = window.innerHeight - 170;

document.addEventListener('DOMContentLoaded', function() {
	
	defaultView = $('#defaultView').val();
	
  var calendarEl = document.getElementById('calendar');

  var calendar = new FullCalendar.Calendar(calendarEl, {
	schedulerLicenseKey: 'CC-Attribution-NonCommercial-NoDerivatives',
    initialView: defaultView,
    timeZone: 'local',
    initialDate: new Date(),
	editable: true,
    height: winHieght,
    allDaySlot: false,
    nowIndicator: true,
    eventDurationEditable: false,
    displayEventTime: true,
    slotDuration: '24:00:00',
    resourceAreaHeaderContent: 'Time Slots',
    resourceAreaWidth: '8%',
    
    slotLabelFormat: [
		{ month: 'long', day:'numeric', year: 'numeric' },
		{ weekday: 'short' }
	],
    
    headerToolbar: {
      right: 'prev,next today',
      center: 'title',
      left: 'resourceTimelineFourDays,resourceTimelineSevenDays,resourceTimelineWeek'
    },
    
    views: {
      resourceTimelineFourDays: {
        type: 'resourceTimeline',
        duration: { days: 4 },
        buttonText: '4 day'
      },
      resourceTimelineSevenDays: {
        type: 'resourceTimeline',
        duration: { days: 7 },
        buttonText: '7 day'
      }
    },
    
    resources: {
	   url: 'ws_calendar.php',
	    method: 'GET',
	    extraParams: {
	      cmd: 'apptRecs'
	    },
	    failure: function() {
	      $('#pCalError').text('Error getting resources: ' + errorThrown).show();
	    }

	},
	

    events: {
	   url: 'ws_calendar.php',
	    method: 'GET',
	    extraParams: {
	      cmd: 'apptEvents'
	    },
	    failure: function() {
	      $('#pCalError').text('Error getting events: ' + errorThrown).show();
	    }

	},
	
	eventClick: function(info) {
		info.jsEvent.preventDefault(); // don't let the browser navigate
		
		// no action for blocked appts
		if (info.event.extendedProps.tpe == 'b') {
			return;
		}
		
		if (info.event.url) {
	      window.open(info.event.url);
	    }
	    
	},
	
	eventDrop: function(info) {
		
		// no action for blocked appts
		if (info.event.extendedProps.tpe == 'b') {
			info.revert();
			return;
		}
		

	}
  });

  calendar.render();
});