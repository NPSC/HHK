/* global getDoc, memberData, rctMkup, psgTabIndex, getAgent, pmtMkup, $,
  flagAlertMessage, saveFees, viewVisit, dateRender, dateFormat */

/**
 * guestload.js
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 */
function isNumber(n) {
    "use strict";
    return !isNaN(parseFloat(n)) && isFinite(n);
}

var dtCols = [
    {
        "targets": [ 0 ],
        "title": "Date",
        'data': 'Date',
        render: function ( data, type ) {
            return dateRender(data, type, dateFormat);
        }
    },
    {
        "targets": [ 1 ],
        "title": "Type",
        "searchable": false,
        "sortable": false,
        "data": "Type"
    },
    {
        "targets": [ 2 ],
        "title": "Sub-Type",
        "searchable": false,
        "sortable": false,
        "data": "Sub-Type"
    },
     {
        "targets": [ 3 ],
        "title": "User",
        "searchable": false,
        "sortable": true,
        "data": "User"
    },
    {
        "targets": [ 4 ],
        "visible": false,
        "data": "Id"
    },
    {
        "targets": [ 5 ],
        "title": "Log Text",
        "sortable": false,
        "data": "Log Text"
    }

];

function relationReturn(dat) {

    var data = $.parseJSON(dat);
    if (data.error) {
        if (data.gotopage) {
            window.open(data.gotopage, '_self');
        }
        flagAlertMessage(data.error, 'error');
    } else if (data.success) {
        if (data.rc && data.markup) {
            var div = $('#acm' + data.rc);
            div.children().remove();
            var newDiv = $(data.markup);
            div.append(newDiv.children());
        }
        flagAlertMessage(data.success, 'success');
    }
}

function setupPsgNotes(rid, $container) {

    $container.notesViewer({
        linkId: rid,
        linkType: 'psg',
        newNoteAttrs: {id:'psgNewNote', name:'psgNewNote'},
        alertMessage: function(text, type) {
            flagAlertMessage(text, type);
        }
    });

    return $container;
}

function manageRelation(id, rId, relCode, cmd) {
    $.post('ws_admin.php', {'id':id, 'rId':rId, 'rc':relCode, 'cmd':cmd}, relationReturn);
}

function paymentRefresh() {
	var indx = $('#psgList').tabs('option','active');
	$('#psgList').tabs('load', indx);
}


// Init j-query.
$(document).ready(function () {
    "use strict";
    var memData = memberData;
    var nextVeh = 1;
    var listJSON = '../admin/ws_gen.php?cmd=chglog&vw=vguest_audit_log&uid=' + memData.id;
    var listEvtTable;
    var setupNotes,
        $psgList;

//    $.widget( "ui.autocomplete", $.ui.autocomplete, {
//        _resizeMenu: function() {
//            this.menu.element.outerWidth( Math.max(
//                    ul.width( "" ).outerWidth() + 1,
//                    this.element.outerWidth()
//            ) * 1.1 );
//        }
//    });

    $("#divFuncTabs").tabs({
        collapsible: true
    });

    //incident reports
    $('#vIncidentContent').incidentViewer({
		guestLabel: memData.guestLabel,
		visitorLabel: memData.visitorLabel,
        guestId: memData.id,
        psgId: memData.idPsg,
        alertMessage: function(text, type) {
            flagAlertMessage(text, type);
        }
    });

    if(useDocUpload){
    	//doc uploader
    	$('#vDocsContent').docUploader({
    		visitorLabel: memData.visitorLabel,
        	guestId: memData.id,
        	psgId: memData.idPsg,
        	alertMessage: function(text, type) {
            	flagAlertMessage(text, type);
        	}
		});
    }

    $(".btnTextGuest").smsDialog({"guestId":memData.id});

    // relationship dialog
    $("#submit").dialog({
        autoOpen: false,
        resizable: false,
        width: getDialogWidth(300),
        modal: true,
        buttons: {
            "Exit": function () {
                $(this).dialog("close");
            }
        }
    });

    $('#keysfees').dialog({
        autoOpen: false,
        resizable: true,
        modal: true,
        close: function () {$('div#submitButtons').show();},
        open: function () {$('div#submitButtons').hide();}
    });

    // Payment Recepit
    $('#pmtRcpt').dialog({
        autoOpen: false,
        resizable: true,
        modal: true,
        title: 'Payment Receipt'
    });

    // Income Dialog
    $("#faDialog").dialog({
        autoOpen: false,
        resizable: true,
        width: getDialogWidth(600),
        modal: true,
        title: 'Income Chooser'
    });

    if (rctMkup !== '') {
        showReceipt('#pmtRcpt', rctMkup);
    }

    if (pmtMkup !== '') {
        $('#paymentMessage').html(pmtMkup).show();
    }

    // Visits
    $('.hhk-view-visit').click(function (event) {
        var vid = $(this).data('vid');
        var gid = $(this).data('gid');
        var span = $(this).data('span');
        var target = $(event.target);

        if (target.hasClass('hhk-hospitalstay')) {
        	return;
        }

        var buttons = {
            "Show Statement": function() {
                window.open('ShowStatement.php?vid=' + vid, '_blank');
            },
            "Show Registration Form": function() {
                window.open('ShowRegForm.php?vid=' + vid + '&span=' + span, '_blank');
            },
            "Save": function() {
                saveFees(gid, vid, span, false, 'GuestEdit.php?id=' + gid + '&psg=' + memData.idPsg);
            },
            "Cancel": function() {
                $(this).dialog("close");
            }
        };
         viewVisit(gid, vid, buttons, 'Edit Visit #' + vid + '-' + span, '', span);
         $('#divAlert1').hide();
    });

    // Reservations
    $('#resvAccordion').accordion({
        heightStyle: "content",
        collapsible: true,
        active: false,
        icons: false
    });

    // relationship events
    $('div.hhk-relations').each(function () {
        var schLinkCode = $(this).attr('name');
        $(this).on('click', 'td.hhk-deletelink', function () {
            if (memData.id > 0) {
                if (confirm($(this).attr('title') + '?')) {
                    manageRelation(memData.id, $(this).attr('name'), schLinkCode, 'delRel');
                }
            }
        });
        $(this).on('click', 'td.hhk-newlink', function () {
            if (memData.id > 0) {
                var title = $(this).attr('title');
                $('#hdnRelCode').val(schLinkCode);
                $('#submit').dialog("option", "title", title);
                $('#submit').dialog('open');
            }
        });
    });

    // Vehicle
    $('#cbNoVehicle').change(function () {
        if (this.checked) {
            $('#tblVehicle').hide();
        } else {
            $('#tblVehicle').show();
        }
    });
    $('#cbNoVehicle').change();

    $('#btnNextVeh, #exAll, #exNone').button();

    $('#btnNextVeh').click(function () {
        $('#trVeh' + nextVeh).show('fade');
        nextVeh++;
        if (nextVeh > 4) {
            $('#btnNextVeh').hide('fade');
        }
    });

    $('#divNametabs').tabs({

        beforeActivate: function (event, ui) {

        // Change Log
		if (ui.newTab.prop('id') === 'chglog' && !listEvtTable) {

                listEvtTable = $('#dataTbl').dataTable({
                "columnDefs": dtCols,
                "serverSide": true,
                "processing": true,
                "deferRender": true,
                "language": {"search": "Search Log Text:"},
                "sorting": [[0,'desc']],
                "displayLength": 25,
                "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                "Dom": '<"top"ilf>rt<"bottom"ip>',
                ajax: {
                    url: listJSON
                }
                });
            }

        },
        collapsible: true
    });

    $('#btnSubmit, #btnReset, #btnCred').button();

    // phone - email tabs block
    $('#phEmlTabs').tabs();
    $('#emergTabs').tabs();
    $('#addrsTabs').tabs();
    $('#InsTabs').tabs();

    $psgList = $('#psgList').tabs({
        collapsible: true,
        beforeActivate: function (event, ui) {
            if (ui.newPanel.length > 0) {

                if (ui.newTab.prop('id') === 'fin') {
                    getIncomeDiag(0, memData.idReg);
                    event.preventDefault();
                }

                if (ui.newTab.prop('id') === 'lipsg' && !setupNotes) {
                    setupNotes = setupPsgNotes(memData.idPsg, $('#psgNoteViewer'));
                }
            }
        },
        load: function (event, ui) {
            if (ui.tab.prop('id') === 'pmtsTable') {
                paymentsTable('feesTable', 'rptfeediv', paymentRefresh);
            }
            if (ui.tab.prop('id') === 'stmtTab') {
                $("#stmtDiv textarea.hhk-autosize").trigger("input");
            }
        }
    });

    if (memData.psgOnly) {
        $psgList.tabs("disable");
    }

    $psgList.tabs("enable", psgTabIndex);
    $psgList.tabs("option", "active", psgTabIndex);

    $('#cbnoReturn').change(function () {
        if (this.checked) {
            $('#selnoReturn').show();
        } else {
            $('#selnoReturn').hide();
        }
    });
    $('#cbnoReturn').change();

    if (memData.id === 0) {
        // enable tabs for a "new" member
        $("#divFuncTabs").tabs("option", "disabled", [2,3,4]);
        $('#phEmlTabs').tabs("option", "active", 1);
        $('#phEmlTabs').tabs("option", "disabled", [0]);
    } else {
        // Existing member
        var tbIndex = parseInt($('#addrsTabs').children('ul').data('actidx'), 10);
        if (isNaN(tbIndex)) {tbIndex = 0;}
        $('#addrsTabs').tabs("option", "active", tbIndex);
    }

    $.datepicker.setDefaults({
        yearRange: '-0:+02',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        numberOfMonths: 1,
        dateFormat: 'M d, yy'
    });

    $('.ckdate').datepicker({
        yearRange: '-02:+03'
    });

    $('.ckbdate').datepicker({
        yearRange: '-99:+00',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        maxDate: 0,
        dateFormat: 'M d, yy',
        showButtonPanel: memData.datePickerButtons,
        beforeShow: function (input) {
            setTimeout(function () {
                var buttonPane = $(input)
                    .datepicker("widget")
                    .find(".ui-datepicker-buttonpane");
                buttonPane.empty();
                $("<button>", {
                    text: "Minor",
                    click: function () {
                        var target = $(input);
                        var inst = $.datepicker._getInst(target[0]);
                        inst.input.val('Minor');
                        var onSelect = $.datepicker._get(inst, "onSelect");

                        if (onSelect) {
                            onSelect.apply((inst.input ? inst.input[0] : null), ['Minor', inst]);  // trigger custom callback
                        } else if (inst.input) {
                            inst.input.trigger("change"); // fire the change event
                        }

                        if (inst.inline) {
                            $.datepicker._updateDatepicker(inst);
                        } else {
                            $.datepicker._hideDatepicker();
                            $.datepicker._lastInput = inst.input[0];
                            if (typeof (inst.input[0]) !== "object") {
                                inst.input.trigger("focus"); // restore focus
                            }
                            $.datepicker._lastInput = null;
                        }
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
                    text: "Minor",
                    click: function () {
                        var target = $(instance.input);
                        var inst = $.datepicker._getInst(target[0]);
                        inst.input.val('Minor');
                        var onSelect = $.datepicker._get(inst, "onSelect");
                        if (onSelect) {
                            onSelect.apply((inst.input ? inst.input[0] : null), ['Minor', inst]);  // trigger custom callback
                        } else if (inst.input) {
                            inst.input.trigger("change"); // fire the change event
                        }

                        if (inst.inline) {
                            $.datepicker._updateDatepicker(inst);
                        } else {
                            $.datepicker._hideDatepicker();
                            $.datepicker._lastInput = inst.input[0];
                            if (typeof (inst.input[0]) !== "object") {
                                inst.input.trigger("focus"); // restore focus
                            }
                            $.datepicker._lastInput = null;
                        }
                    }
                }).appendTo(buttonPane).addClass("ui-datepicker-clear ui-state-default ui-priority-primary ui-corner-all");
            }, 1);
        }
    });

    $('#cbLastConfirmed').change(function () {
        if ($(this).prop('checked')) {
            $('#txtLastConfirmed').datepicker('setDate', '+0');
        } else {
            // restore date textbox
            $('#txtLastConfirmed').val($('#txtLastConfirmed').prop('defaultValue'));
        }
    });

    $('#txtLastConfirmed').change(function () {
        if ($('#txtLastConfirmed').val() == $('#txtLastConfirmed').prop('defaultValue')) {
            $('#cbLastConfirmed').prop('checked', false);
        } else {
            $('#cbLastConfirmed').prop('checked', true);
        }
    });

    $('.checklistTbl').on('change', '.hhk-checkboxlist', function () {
        if ($(this).prop('checked')) {
            $('#date' + $(this).data('code')).datepicker('setDate', '+0');
            $('#disp' + $(this).data('code')).show();
        } else {
            // restore date textbox
            $('#date' + $(this).data('code')).val($('#date' + $(this).data('code')).prop('defaultValue'));
            $('#disp' + $(this).data('code')).hide();
        }
    });

    verifyAddrs('div#nameTab, div#hospitalSection');

    addrPrefs(memData);

    var zipXhr;

    createZipAutoComplete($('input.hhk-zipsearch'), 'ws_admin.php', zipXhr);

    // Main form submit button.  Disable page during POST
    $('#btnSubmit').click(function () {
        if ($(this).val() === 'Saving>>>>') {
            return false;
        }
        $(this).val('Saving>>>>');
    });

    // Member search letter input box
    $('#txtsearch').keypress(function (event) {
        var mm = $(this).val();
        if (event.keyCode == '13') {
            if (mm == '' || !isNumber(parseInt(mm, 10))) {
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

    // Date of death
    $('#cbdeceased').change(function () {
        if ($(this).prop('checked')) {
            $('#disp_deceased').show();
        } else {
            $('#disp_deceased').hide();
        }
    });

    // Date of background check
    $('#cbbackgroundcheck').change(function () {
        if ($(this).prop('checked')) {
        	$('#txtBackgroundCheckDate').datepicker('setDate', '+0');
            $('#disp_backgroundcheck').show();
        } else {
        	$('#txtBackgroundCheckDate').val('');
            $('#disp_backgroundcheck').hide();
        }
    });

    $('select.hhk-multisel').each( function () {
        $(this).multiselect({
            selectedList: 3
        });
    });

	// Name Search
    createRoleAutoComplete($('#txtsearch'), 3, {cmd: 'guest'},
        function (item) {
            if (item.id > 0) {
                window.location.assign("GuestEdit.php?id=" + item.id);
            }
        }, false);


    // MRN Search
    createRoleAutoComplete($('#txtMRNsearch'), 3, {cmd: 'mrn'},
        function (item) {
            if (item.id > 0) {
                window.location.assign("GuestEdit.php?id=" + item.id);
            }
        }, false);


	// Phone Search
    createRoleAutoComplete($('#txtPhsearch'), 5, {cmd: 'phone'},
        function (item) {
            if (item.id > 0) {
                window.location.assign("GuestEdit.php?id=" + item.id);
            }
        }, false);

    createAutoComplete($('#txtRelSch'), 3, {cmd: 'srrel', basis: $('#hdnRelCode').val(), id: memData.id}, function (item) {
        $.post('ws_admin.php', {'rId':item.id, 'id':memData.id, 'rc':$('#hdnRelCode').val(), 'cmd':'newRel'}, relationReturn);
    });

    // Any results
    if (resultMessage !== '') {
        flagAlertMessage(resultMessage, 'alert');
    }


    // Excludes tab "Check-all" button
    $('input.hhk-check-button').click(function () {
        if ($(this).prop('id') === 'exAll') {
            $('input.hhk-ex').prop('checked', true);
        } else {
            $('input.hhk-ex').prop('checked', false);
        }
    });

    $('#divFuncTabs').show();

    $('.hhk-showonload').show();

    $('#txtsearch').focus();

    // Unsaved changes on form are caught here.
    // Set Dirrty initial value manually for bfh
    $(document).find("bfh-states").each(function(){
	$(this).data("dirrty-initial-value", $(this).data('state'));
    });

    $(document).find("bfh-country").each(function(){
	$(this).data("dirrty-initial-value", $(this).data('country'));
    });

    $('#btnCred').click(function () {
        cardOnFile($(this).data('id'), $(this).data('idreg'), 'GuestEdit.php?id=' + $(this).data('id') + '&psg=' + memData.idPsg, $(this).data('indx'));
    });

    setupCOF($('.tblCreditExpandg'), $('#btnCred').data('indx'));

    $('#keysfees').mousedown(function (event) {
        var target = $(event.target);
        if ( target[0].id !== 'pudiv' && target.parents("#" + 'pudiv').length === 0) {
            $('div#pudiv').remove();
        }
    });

    // init dirrty
    $("#form1").dirrty();



    $('#btnActvtyGo').button().click(function () {
        $(".hhk-alert").hide();
        var stDate = $('#txtactstart').datepicker("getDate");
        if (stDate === null) {
            $('#txtactstart').addClass('ui-state-highlight');
            flagAlertMessage('Enter start date', 'alert');
            return;
        } else {
            $('#txtactstart').removeClass('ui-state-highlight');
        }
        var edDate = $('#txtactend').datepicker("getDate");
        if (edDate === null) {
            edDate = new Date();
        }
        var parms = {
            cmd: 'actrpt',
            start: stDate.toLocaleDateString(),
            end: edDate.toLocaleDateString(),
			psg: memData.idPsg
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

                        $('#activityLog').remove();
						$('#vvisitLog').append($('<div id="activityLog"/>').append($(data.success)));

                        $('.hhk-viewvisit').css('cursor', 'pointer');
                        $('#activityLog').on('click', '.hhk-viewvisit', function () {
                            if ($(this).data('visitid')) {
                                var parts = $(this).data('visitid').split('_');
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

	//guest photo
    if(showGuestPhoto || useDocUpload){
		var guestPhoto = window.uploader;
		$(document).on('click', '.upload-guest-photo', function(){
			$(guestPhoto.container).removeClass().addClass('uppload-container');
			guestPhoto.updatePlugins(plugins => []);
			guestPhoto.updateSettings({
				maxSize: [500, 500],
				customClass: 'guestPhotouploadContainer',
				uploader: function uploadFunction(file){
	            	return new Promise(function (resolve, reject) {
	                	var formData = new FormData();
	                	formData.append('cmd', 'putguestphoto');
	                	formData.append('guestId', memData.id);
	                	formData.append('guestPhoto', file);

	                	$.ajax({
	                    	type: "POST",
	                   	 	url: "../house/ws_resc.php",
	                   	 	dataType: "json",
	                    	data: formData,
	                    	//use contentType, processData for sure.
	                    	contentType: false,
	                    	processData: false,
	                    	success: function(data) {
	                        	if(data.error){
	                            	reject(data.error);
	                        	}else{
	                            	resolve("success");
	                            	$("#hhk-guest-photo").css("background-image", "url(../house/ws_resc.php?cmd=getguestphoto&guestId=" + memData.id + "r&x="+new Date().getTime() + ")");
	                            	$(".delete-guest-photo").show();
	                        	}
	                        	guestPhoto.navigate('local');
	                    	},
	                    	error: function(error) {
	                        	reject(error);
	                    	}
	                	});
	            	});
	        	},
			});


			var guestphotoLocal = new Upploader.Local(
	    	{
	        	maxFileSize: 5000000,
	        	mimeTypes: ["image/jpeg", "image/png"]
	    	});

	    	window.camera = new Upploader.Camera()

	    	guestPhoto.use([guestphotoLocal, new Upploader.Crop({aspectRatio: 1}), window.camera]);

	    	guestPhoto.open();

	    });

	    guestPhoto.on("open", function(){
			//hide effects if only one
	        if(guestPhoto.effects.length == 1) {
	        	$(guestPhoto.container).find(".effects-tabs").hide();
	        }else{
	        	$(guestPhoto.container).find(".effects-tabs").show();
	        }
	    });

	    guestPhoto.on('close', function(){
	    	guestPhoto.navigate('local'); //trigger camera to stop
	    	var camera = guestPhoto.services.filter(service => service.name == 'camera');
	    	if(camera.length == 1){
	    		camera[0].stop();
	    	}
	    });

	    $(document).on("click", "#hhk-guest-photo", function(e){
	        e.preventDefault();
	    });

	    //toggle guest photo action buttons on hover
	    $(".hhk-visitdialog #hhk-guest-photo").on({
	        mouseenter: function () {
	            $(this).find("#hhk-guest-photo-actions").show();
	            $(this).find("#hhk-guest-photo img").fadeTo(100, 0.5);
	        },
	        mouseleave: function () {
	            $(this).find("#hhk-guest-photo-actions").hide();
	            $(this).find("#hhk-guest-photo img").fadeTo(100, 1);
	        }
	    });

	    $(".delete-guest-photo").on("click", function(){

	        if (confirm("Really Delete this photo?")) {
	            $.ajax({
	                type: "POST",
	                url: "../house/ws_resc.php",
	                dataType: "json",
	                data: {
	                        cmd: "deleteguestphoto",
	                        guestId: memData.id
	                    },
	                success: function(data) {
	                    if(data.error){

	                        if (data.gotopage) {
	                            window.location.assign(data.gotopage);
	                        }

	                        flagAlertMessage("Server error - " + data.error, 'error');
	                        return;

	                    }else{
	                        $("#hhk-guest-photo").css("background-image", "url(../house/ws_resc.php?cmd=getguestphoto&guestId=" + memData.id + "&rx="+new Date().getTime() + ")");
	                    }
	                },
	                error: function(error) {
	                    flagAlertMessage("AJAX error - " + error);
	                }
	            });
	        }
	    });
    };
});
