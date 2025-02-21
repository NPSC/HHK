var fixedRate;
var payFailPage;
var dateFormat;
var paymentMarkup;
var pageManager;
var receiptMarkup;

async function formDataToJsonAndFetch(formData, url, options = {}) {
    try {
        // 1. Convert FormData to JSON
        const jsonObject = Object.fromEntries(formData.entries());


        // 2. Fetch the data
        // Default fetch options if not provided
        const defaultOptions = {
            method: 'POST', // Or 'GET', 'PUT', 'DELETE', etc. as needed
            headers: {
                'Content-Type': 'application/json', // Important for JSON data
            },
            body: JSON.stringify(jsonObject), // Convert JSON object to string
        };

        // Merge default options with user-provided options (if any)
        const fetchOptions = { ...defaultOptions, ...options };  // Spread operator for merging

        const response = await fetch(url, fetchOptions);

        if (!response.ok) {
            const errorText = await response.text(); // Get error message from server
            throw new Error(`HTTP error! status: ${response.status},  Message: ${errorText}`);
        }


        // 3. Process the response (e.g., parse JSON response from server)
        const responseData = await response.json(); // If server sends JSON back
        return responseData; // Return the parsed JSON data

    } catch (error) {
        console.error("Error during fetch or FormData conversion:", error);
        throw error; // Re-throw the error to be handled by the caller if needed
    }
}
$(document).ready(function() {
    "use strict";
    var t = this;
    var $guestSearch = $('#gstSearch');
    var resv = $.parseJSON($('#resv').val());
    var pageManagerOptions = $.parseJSON($('#resvManagerOptions').val());
    var pageManager = t.pageManager;
    let isRepeatReservHost = $('#isRepeatReservHost').val();
    fixedRate = $('#fixedRate').val();
    payFailPage = $('#payFailPage').val();
    dateFormat = $('#dateFormat').val();
    paymentMarkup = $('#paymentMarkup').val();
    receiptMarkup = $('#receiptMarkup').val();

// Dialog Boxes
    $("#resDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: '95%',
        modal: true
    });

    $('#confirmDialog').dialog({
        autoOpen: false,
        resizable: true,
        width: getDialogWidth(1200),
        modal: true,
        title: 'Confirmation Form',
        close: function () {$('div#submitButtons').show(); $("#frmConfirm").children().remove();},
        buttons: {
            'Download to MS Word': function () {
                var $confForm = $("form#frmConfirm");
                var $hdnCfmRid = $confForm.find('input[name="hdnCfmRid"]');
                var $hdnCfmDocCode = $confForm.find('input[name="hdnCfmDocCode"]');
                var $hdnCfmAmt = $confForm.find('input[name="hdnCfmAmt"]');
                var $hdnTabIndex = $confForm.find('input[name="hdnTabIndex"]');

                if($hdnCfmRid.length > 0){
                	$hdnCfmRid.val($('#btnShowCnfrm').data('rid'));
                }else{
                	$confForm.append($('<input name="hdnCfmRid" type="hidden" value="' + $('#btnShowCnfrm').data('rid') + '"/>'));
                }
                if($hdnCfmDocCode.length > 0){
                	$hdnCfmDocCode.val($('div[id="confirmTabDiv"] ul .ui-tabs-active').attr("data-docId"));
                }else{
                	$confForm.append($('<input name="hdnCfmDocCode" type="hidden" value="' + $('div[id="confirmTabDiv"] ul .ui-tabs-active').attr("data-docId") + '"/>'));
                }
                if($hdnCfmAmt.length > 0){
                	$hdnCfmAmt.val($('#spnAmount').text());
                }else{
                	$confForm.append($('<input name="hdnCfmAmt" type="hidden" value="' + $('#spnAmount').text() + '"/>'));
                }
                if($hdnTabIndex.length > 0){
                	$hdnTabIndex.val($('div[id="confirmTabDiv"] ul .ui-tabs-active').attr("aria-controls"));
                }else{
                	$confForm.append($('<input name="hdnTabIndex" type="hidden" value="' + $('div[id="confirmTabDiv"] ul .ui-tabs-active').attr("aria-controls") + '"/>'));
                }
                $confForm.submit();
            },
            'Send Email': function() {
                var tabIndex = $('div[id="confirmTabDiv"] ul .ui-tabs-active').attr("aria-controls");
                $.post('ws_ckin.php', {cmd:'confrv', rid: $('#btnShowCnfrm').data('rid'), eml: '1', eaddr: $('#confEmail').val(), ccAddr: $('#ccConfEmail').val(), amt: $('#spnAmount').text(), notes: $('#tbCfmNotes'+tabIndex).val(), tabIndex: tabIndex}, function(data) {
                    data = $.parseJSON(data);
                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }

                    if(data.status == 'success'){
                    	flagAlertMessage(data.mesg, false);
                    }else{
                    	flagAlertMessage(data.mesg, true);
                    }
                });
                $(this).dialog("close");
            },
            "Cancel": function() {
                $(this).dialog("close");

            }
        }
    });

    $("#activityDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: getDialogWidth(900),
        modal: true,
        title: 'Reservation Activity Log',
        close: function () {$('div#submitButtons').show();},
        open: function () {$('div#submitButtons').hide();},
        buttons: {
            "Exit": function() {
                $(this).dialog("close");
            }
        }
    });

    $("#psgDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: getDialogWidth(500),
        modal: true,
        title: resv.patLabel + ' Chooser',
        close: function (event, ui) {$('div#submitButtons').show();},
        open: function (event, ui) {$('div#submitButtons').hide();}
    });

    $('#keysfees').dialog({
        autoOpen: false,
        resizable: true,
        modal: true,
        close: function() {$('#submitButtons').show();}
    });

    $('#pmtRcpt').dialog({
        autoOpen: false,
        resizable: true,
        width: getDialogWidth(530),
        modal: true,
        title: 'Payment Receipt'
    });

    $("#ecSearch").dialog({
        autoOpen: false,
        resizable: false,
        width: getDialogWidth(300),
        title: 'Emergency Contact',
        modal: true,
        buttons: {
            "Exit": function() {
                $(this).dialog("close");
            }
        }
    });

    if (paymentMarkup !== '') {
        $('#paymentMessage').show();
    }

    if (receiptMarkup !== '') {
        showReceipt('#pmtRcpt', receiptMarkup, 'Payment Receipt');
    }


    pageManager = new resvManager(resv, pageManagerOptions);

    $(document).mousedown(function (event) {
    	// hide the alert on mousedown
        if (event.target.className === undefined || event.target.className !== 'hhk-addrPickerPanel') {
            $('#divSelAddr').remove();
        }
		// Hide invoice view box.
        if (event.target.id && event.target.id !== undefined && event.target.id !== 'pudiv') {
            $('div#pudiv').remove();
        }

    });

// Buttons
    $('#btnDone, #btnShowReg, #btnDelete, #btnCheckinNow').button();

    $('#btnDelete').click(function () {

        if ($(this).val() === 'Deleting >>>>') {
            return;
        }

        if (confirm('Delete this ' + pageManager.resvTitle + '?')) {

            if (pageManager.deleteReserve() === false) {
				$(this).val('Final Delete');
				$('#btnDone').hide();
				$('#btnCheckinNow').hide();
				$('#btnShowReg').hide();
				return;
			}

            $(this).val('Deleting >>>>');

			 $.post(
				'ws_resv.php',
                $('#form1').serialize() + '&cmd=delResv&idPsg=' + pageManager.getIdPsg() + '&prePayment=' + pageManager.getPrePaymtAmt() + '&rid=' + pageManager.getIdResv() + '&' + $.param({mem: pageManager.people.list()}),
                 function(datas) {
                    let data;
                    try {
                        data = $.parseJSON(datas);
                    } catch (err) {
                        flagAlertMessage(err.message, 'error');
                        $(idForm).remove();
                    }

                    if (data.error) {
                        flagAlertMessage(data.error, 'error');
                        $('#btnDelete').val('Delete').show();
                    }

				    if (data.warning) {
				        flagAlertMessage(data.warning, 'warning');
				    }

                    if (data.receiptMarkup && data.receiptMarkup != '') {
						showReceipt('#pmtRcpt', data.receiptMarkup, 'Payment Receipt');
					}

					if (data.deleted) {
						$('#form1').remove();
						$('#contentDiv').append('<p>' + data.deleted + '</p>');

						$('#spnStatus').text('Deleted');
                    }

                    if (data.xfer || data.inctx || data.deluxehpf) {
				        paymentRedirect (data, $('#xform'), {resvId: pageManager.getIdResv()});
				        //return;
				    }

                }
        	);
        }
    });

    $('#btnShowReg').click(function () {
        window.open('ShowRegForm.php?rid=' + pageManager.getIdResv(), '_blank');
    });

    $('#btnCheckinNow').click(function () {
        $('#resvCkinNow').val('yes');
        $('#btnDone').click();
    });

    $('#btnDone').click(function () {

        if ($(this).val() === 'Saving >>>>') {
            return;
        }

        $('#pWarnings').hide();

        if (pageManager.verifyInput() === true) {

            $(this).val('Saving >>>>');

            var formData = new FormData($('#form1')[0]);

            formData.append('cmd', 'saveResv');
            formData.append('idPsg', pageManager.getIdPsg());
            formData.append('prePayment', pageManager.getPrePaymtAmt());
            formData.append('rid', pageManager.getIdResv());

            //diagnosis
            let txtDiagnosis = $('#txtDiagnosis').val();
            if (typeof txtDiagnosis === "string") {
                txtDiagnosis = buffer.Buffer.from(txtDiagnosis).toString("base64");
            }
            formData.append('txtDiagnosis', txtDiagnosis);

            let peopleStr = $.param({ mem: pageManager.people.list() });
            let people = new URLSearchParams(peopleStr);

            for (const [key, value] of people.entries()) {
                formData.append(key, value);
            }

            try {
                const response = formDataToJsonAndFetch(formData, 'ws_resv.php');
                console.log("Success! Response from server:", response);

                if (response.gotopage) {
                    window.open(response.gotopage, '_self');
                }

                if (response.error) {
                    flagAlertMessage(response.error, 'error');
                    $('#btnDone').val('Save').show();
                }

                if (response.xfer || response.inctx || response.deluxehpf) {
                    paymentRedirect(response, $('#xform'), { resvId: pageManager.getIdResv() });
                    //return;
                }

                if (response.redirTo) {
                    location.replace(response.redirTo);
                }

                pageManager.loadResv(response);

                if (response.receiptMarkup && response.receiptMarkup != '') {
                    showReceipt('#pmtRcpt', response.receiptMarkup, 'Payment Receipt');
                }

                if (response.resv !== undefined) {
                    if (response.warning === undefined) {
                        flagAlertMessage(response.resvTitle + ' Saved. ' + (response.resv.rdiv.rStatTitle === undefined ? '' : ' Status: ' + response.resv.rdiv.rStatTitle), 'success');
                    }
                } else {
                    flagAlertMessage((response.resvTitle === undefined ? '' : response.resvTitle) + ' Saved. ', 'success');
                }

                if (response.info) {
                    flagAlertMessage(response.info, 'info');
                }

            } catch (error) {
                console.error("Error handling form submission:", error);

                flagAlertMessage("An error occurred during form submission.", "error");
                $('#btnDone').val("Save");
            }
        }

    });


    function getGuest(item) {

        if (item.noReturn !== undefined && item.noReturn !== '') {
            flagAlertMessage('This person is set for No Return: ' + item.noReturn + '.', 'alert');
            return;
        }

        if (typeof item.id !== 'undefined') {
            resv.id = item.id;
        } else if (typeof item.rid !== 'undefined') {
            resv.rid = item.rid;
        } else {
            return;
        }

        resv.fullName = item.fullName;
        resv.cmd = 'getResv';
        resv.guestSearchTerm = $guestSearch.val();

        pageManager.getReserve(resv);

    }

    if (parseInt(resv.id, 10) >= 0 || parseInt(resv.rid, 10) > 0) {

        // Avoid automatic new guest for existing reservations.
        if (parseInt(resv.id, 10) === 0 && parseInt(resv.rid, 10) > 0) {
            resv.id = -2;
        }

        resv.cmd = 'getResv';
        pageManager.getReserve(resv);

    } else {

    	createRoleAutoComplete($guestSearch, 3, {cmd: 'guest'}, getGuest);

        // MRN search
        createRoleAutoComplete($('#gstMRNSearch'), 3, {cmd: 'mrn'}, getGuest);

        // Phone number search
	    createRoleAutoComplete($('#gstphSearch'), 5, {cmd: 'phone'}, getGuest);

        $guestSearch.keypress(function() {
            $(this).removeClass('ui-state-highlight');
        });

        $guestSearch.focus();
    }

});


