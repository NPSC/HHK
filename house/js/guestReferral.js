
var idPatient, idDoc;

$(document).ready(function() {
    "use strict";

	var $btnDone = $('#btnDone');

	idPatient = parseInt($('#idPatient').val());
	idDoc = parseInt($('#idDoc').val());
		
	$btnDone.button();

	// Search includes columns
	$('.hhk-includeSearch').hide();
		
	// Set up done button
	if (idPatient < 0) {
		
		// Patient chooser
		$btnDone.val('Save Patient');
		$('#finaly').val('');
		
		// Search includes columns
		//$('.hhk-includeSearch').show();
		
		// wire search checkboxes to search back end
		
		
		$('input:radio[name=rbPatient]').change(function () {
			var id = $(this).val();
			$('.hhi-wasblank').text('');
			if (id > 0) {
				 // Copy empty td's with guest submitted data
				if ($('#tbPatMiddle'+id).text() === "") {
					//$('#tbPatMiddle'+id).text($('#tbPatMiddle').text()).addClass('hhi-wasblank');
				} else if ($('#tbPatMiddle'+id).text() !== $('#tbPatMiddle').text()) {
					$('#tbPatMiddle'+id).css('color', 'red');
				}
				if ($('#tbPatSuffix'+id).text() === "") {
					//$('#tbPatSuffix'+id).text($('#tbPatSuffix').text()).addClass('hhi-wasblank');
				} else if ($('#tbPatSuffix'+id).text() !== $('#tbPatSuffix').text()) {
					$('#tbPatSuffix'+id).css('color', 'red');
				}
				if ($('#tbPatNickname'+id).text() === "") {
					//$('#tbPatNickname'+id).text($('#tbPatNickname').text()).addClass('hhi-wasblank');
				} else if ($('#tbPatNickname'+id).text() !== $('#tbPatNickname').text()) {
					$('#tbPatNickname'+id).css('color', 'red');
				}
				if ($('#tbPatBD'+id).text() === "") {
					//$('#tbPatBD'+id).text($('#tbPatBD').text()).addClass('hhi-wasblank');
				} else if ($('#tbPatBD'+id).text() !== $('#tbPatBD').text()) {
					$('#tbPatBD'+id).css('color', 'red');
				}
				if ($('#tbPatPhone'+id).text() === "") {
					//$('#tbPatPhone'+id).text($('#tbPatPhone').text()).addClass('hhi-wasblank');
				} else if ($('#tbPatPhone'+id).text() !== $('#tbPatPhone').text()) {
					$('#tbPatPhone'+id).css('color', 'red');
				}
				if ($('#tbPatEmail'+id).text() === "") {
					//$('#tbPatEmail'+id).text($('#tbPatEmail').text()).addClass('hhi-wasblank');
				} else if ($('#tbPatEmail'+id).text() !== $('#tbPatEmail').text()) {
					$('#tbPatEmail'+id).css('color', 'red');
				}
				if ($('#tbPatStreet'+id).text() === "") {
					//$('#tbPatStreet'+id).text($('#tbPatStreet').text()).addClass('hhi-wasblank');
				} else if ($('#tbPatStreet'+id).text() !== $('#tbPatStreet').text()) {
					$('#tbPatStreet'+id).css('color', 'red');
				}
				if ($('#tbPatCity'+id).text() === "") {
					//$('#tbPatCity'+id).text($('#tbPatCity').text()).addClass('hhi-wasblank');
				} else if ($('#tbPatCity'+id).text() !== $('#tbPatCity').text()) {
					$('#tbPatCity'+id).css('color', 'red');
				}
				if ($('#tbPatCounty'+id).text() === "") {
					//$('#tbPatCounty'+id).text($('#tbPatCounty').text()).addClass('hhi-wasblank');
				} else if ($('#tbPatCounty'+id).text() !== $('#tbPatCounty').text()) {
					$('#tbPatCounty'+id).css('color', 'red');
				}
				if ($('#tbPatState'+id).text() === "") {
					//$('#tbPatState'+id).text($('#tbPatState').text()).addClass('hhi-wasblank');
				} else if ($('#tbPatState'+id).text() !== $('#tbPatState').text()) {
					$('#tbPatState'+id).css('color', 'red');
				}
				if ($('#tbPatZip'+id).text() === "") {
					//$('#tbPatZip'+id).text($('#tbPatZip').text()).addClass('hhi-wasblank');
				} else if ($('#tbPatZip'+id).text() !== $('#tbPatZip').text()) {
					$('#tbPatZip'+id).css('color', 'red');
				}
				if ($('#tbPatCountry'+id).text() === "") {
					//$('#tbPatCountry'+id).text($('#tbPatCountry').text()).addClass('hhi-wasblank');
				} else if ($('#tbPatCountry'+id).text() !== $('#tbPatCountry').text()) {
					$('#tbPatCountry'+id).css('color', 'red');
				}
				
			}
		});
		
		$('input:radio[name=rbPatient]').change();
		
	} else {
		// Guest chooser
		$btnDone.val('Save Guests');
		$('#finaly').val('1');
		
		var gindx = 'g0';
		$('input:radio[name=rbGuest'+gindx+']').change(function () {
			var id = $(this).val();
			$('.hhi-wasblank').text('');
			if (id > 0) {
				if ($('#tbGuestRel'+id).text() !== $('#tbGuestRel').text()) {
					$('#tbGuestRel'+id).css('color', 'red');
				}
			}
		
		});
	}

});