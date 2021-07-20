
var idPatient, idDoc, final;

$(document).ready(function() {
    "use strict";

	idPatient = parseInt($('#idPatient').val());
	idDoc = parseInt($('#idDoc').val());
	
	var $btnDone = $('#btnDone');
	$btnDone.button();

	// Set up done button
	if (idPatient < 0) {
		// Patient chooser
		$btnDone.val('Save Patient');
		$('#final').val('');
		
		// Search includes columns
		$('.hhk-includeSearch').show();
		
	} else {
		// Guest chooser
		$btnDone.val('Save Guests');
		$('#final').val('1');
		
		// Search includes columns
		$('.hhk-includeSearch').hide();
	}

});