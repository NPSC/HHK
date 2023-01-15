var fixedRate;
var payFailPage;
var dateFormat;
var paymentMarkup;
var pageManager;
var receiptMarkup;

$(document).ready(function() {
    "use strict";
    var t = this;
    var $guestSearch = $('#gstSearch');
    var resv = $.parseJSON($('#resv').val());
    var pageManagerOptions = $.parseJSON($('#resvManagerOptions').val());
    var pageManager = t.pageManager;
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
            'Download MS Word': function () {
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

				    if (data.xfer || data.inctx) {
				        paymentRedirect (data, $('#xform'));
				        return;
				    }
				    
                    if (data.receiptMarkup && data.receiptMarkup != '') {
						showReceipt('#pmtRcpt', data.receiptMarkup, 'Payment Receipt');
					}
					
					if (data.deleted) {
						$('#form1').remove();
						$('#contentDiv').append('<p>' + data.deleted + '</p>');
						$('#spnStatus').text('Deleted');
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
            
            
            $.post(
                'ws_resv.php',
                $('#form1').serialize() + '&cmd=saveResv&idPsg=' + pageManager.getIdPsg() + '&prePayment=' + pageManager.getPrePaymtAmt() + '&rid=' + pageManager.getIdResv() + '&' + $.param({mem: pageManager.people.list()}),
                function(data) {
                    try {
                        data = $.parseJSON(data);
                    } catch (err) {
                        flagAlertMessage(err.message, 'error');
                        return;
                    }

                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }

                    if (data.error) {
                        flagAlertMessage(data.error, 'error');
                        $('#btnDone').val('Save').show();
                    }

				    if (data.warning) {
				        flagAlertMessage(data.warning, 'warning');
				    }

				    if (data.xfer || data.inctx) {
				        paymentRedirect (data, $('#xform'));
				        return;
				    }
				    
				    if (data.redirTo) {
				       location.replace(data.redirTo);
				    }
				    
                    pageManager.loadResv(data);
                    
                    if (data.receiptMarkup && data.receiptMarkup != '') {
						showReceipt('#pmtRcpt', data.receiptMarkup, 'Payment Receipt');
					}

                    if (data.resv !== undefined) {
                        if (data.warning === undefined) {
                            flagAlertMessage(data.resvTitle + ' Saved. ' + (data.resv.rdiv.rStatTitle === undefined ? '' : ' Status: ' + data.resv.rdiv.rStatTitle), 'success');
                        }
                    } else {
                        flagAlertMessage( (data.resvTitle === undefined ? '' : data.resvTitle) + ' Saved.', 'success');
                    }
                }
            );

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


