var fixedRate;
var payFailPage;
var dateFormat;
var paymentMarkup;
var pageManager;

$(document).ready(function() {
    "use strict";
    var t = this;
    var $guestSearch = $('#gstSearch');
    var resv = $.parseJSON($('#resv').val());
    var pageManager = t.pageManager;
    fixedRate = $('#fixedRate').val();
    payFailPage = $('#payFailPage').val();
    dateFormat = $('#dateFormat').val();
    paymentMarkup = $('#paymentMarkup').val();

    $.widget( "ui.autocomplete", $.ui.autocomplete, {
        _resizeMenu: function() {
            var ul = this.menu.element;
            ul.outerWidth( Math.max(
                    ul.width( "" ).outerWidth() + 1,
                    this.element.outerWidth()
            ) * 1.1 );
        }
    });

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

    $("#activityDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: 900,
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
        width: 500,
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
        width: 530,
        modal: true,
        title: 'Payment Receipt'
    });

    if (paymentMarkup !== '') {
        $('#paymentMessage').show();
    }


    pageManager = new resvManager(resv);

    // hide the alert on mousedown
    $(document).mousedown(function (event) {

        if (isIE()) {
            var target = $(event.target[0]);

            if (target.id && target.id !== undefined && target.id !== 'divSelAddr' && target.closest('div') && target.closest('div').id !== 'divSelAddr') {
                $('#divSelAddr').remove();
            }

        } else {

            if (event.target.className === undefined || event.target.className !== 'hhk-addrPickerPanel') {
                $('#divSelAddr').remove();
            }
        }
    });

// Buttons
    $('#btnDone, #btnShowReg, #btnDelete, #btnCheckinNow').button();

    $('#btnDelete').click(function () {

        if ($(this).val() === 'Deleting >>>>') {
            return;
        }

        if (confirm('Delete this ' + pageManager.resvTitle + '?')) {

            $(this).val('Deleting >>>>');

            pageManager.deleteReserve(pageManager.getIdResv(), 'form#form1', $(this));
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
                $('#form1').serialize() + '&cmd=saveResv&idPsg=' + pageManager.getIdPsg() + '&rid=' + pageManager.getIdResv() + '&' + $.param({mem: pageManager.people.list()}),
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

                    pageManager.loadResv(data);

                    if (data.resv !== undefined) {
                        if (data.warning === undefined) {
                            flagAlertMessage(data.resvTitle + ' Saved. ' + (data.resv.rdiv.rStatTitle === undefined ? '' : ' Status: ' + data.resv.rdiv.rStatTitle, 'success'));
                        }
                    } else {
                        flagAlertMessage( (data.resvTitle === undefined ? '' : data.resvTitle) + ' Saved.', 'success');
                    }
                }
            );

        }
    });


	//incident reports
	if(resv.idPsg){
		$('#incidentContent').incidentViewer({
			psgId: resv.idPsg
		}).hide();
		
		$('#incidentsSection').show();
	}
	//incident block
	var Ihdr = $("#incidentsSection .hhk-incidentHdr");
	var Icontent = $("#incidentsSection #incidentContent");
	Ihdr.click(function() {
	    if (Icontent.css('display') === 'none') {
	        Icontent.show('blind');
	        Ihdr.removeClass('ui-corner-all').addClass('ui-corner-top');
	    } else {
	        Icontent.hide('blind');
	        Ihdr.removeClass('ui-corner-top').addClass('ui-corner-all');
	    }
	});

    function getGuest(item) {

        if (item.No_Return !== undefined && item.No_Return !== '') {
            flagAlertMessage('This person is set for No Return: ' + item.No_Return + '.', 'alert');
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

        createAutoComplete($guestSearch, 3, {cmd: 'role', gp:'1'}, getGuest);

        // Phone number search
        createAutoComplete($('#gstphSearch'), 4, {cmd: 'role', gp:'1'}, getGuest);

        $guestSearch.keypress(function(event) {
            $(this).removeClass('ui-state-highlight');
        });

        $guestSearch.focus();
    }
});


