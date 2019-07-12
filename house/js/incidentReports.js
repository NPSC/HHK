(function ($) {

    $.fn.incidentViewer = function (options) {

		var incidentdialog = $('<div id="incidentDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.8em;" title="Incident">' +
		'<form>' +
			'<table style="width: 100%">' +
			'<tr>' +
				'<td class="tdlabel">Incident Title</td>' +
				'<td>' +
					'<input type="text" name="incidentTitle" style="width: 100%">' +
				'</td>' +
			'</tr>' +
			'<tr>' +
				'<td class="tdlabel" style="width: 25%">Incident Date</td>' +
				'<td>' +
					'<input type="text" name="incidentDate" class="ckdate" style="width: 100%">' +
				'</td>' +
			'</tr>' +
			'<tr>' +
				'<td class="tdlabel">Incident Description</td>' +
				'<td>' +
					'<textarea name="incidentDescription" rows="5" style="width: 100%"></textarea>' +
				'</td>' +
			'</tr>' +
			'<tr>' +
				'<td class="tdlabel">Incident Status</td>' +
				'<td>' +
					'<select name="incidentStatus" style="width: 100%">' +
						'<option vlaue="a">Active</option>' +
						'<option value="r">Resolved</option>' +
						'<option value="h">On Hold</option>' +
						'<option value="d">Delete</option>' +
					'</select>' +
				'</td>' +
			'</tr>' +
			'<tr>' +
				'<td class="tdlabel">Incident Resolution</td>' +
				'<td>' +
					'<textarea name="incidentResolution" rows="5" style="width: 100%"></textarea>' +
				'</td>' +
			'</tr>' +
			'<tr>' +
				'<td class="tdlabel" style="width: 25%">Resolution Date</td>' +
				'<td>' +
					'<input type="text" name="resolutionDate" class="ckdate" style="width: 100%">' +
				'</td>' +
			'</tr>' +
			'</table>' +
		'</form>' +
		'</div>');


        var defaults = {
            guestId: 0,
            psgId: 0,
            serviceURL: 'ws_resv.php',
            newLabel: 'New Incident',
            tableAttrs: {
                class: 'display compact',
                width: '100%'
            },
            newIncidentAttrs: {
                id: 'incidentTitle',
                style: 'width: 80%;',
                rows: 2
            },
            alertMessage: function (text, type) {},
            
            dtCols: [
                {
                "targets": [ 0 ],
                        title: "Actions",
                        data: "Action",
                        sortable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            return createActions(data, row);  
                        }
                },
                {
                "targets": [ 1 ],
                        title: "Date",
                        data: 'Date',
                        render: function (data, type) {
                            return dateRender(data, type, dateFormat);
                        }
                },
                {
                "targets": [ 2 ],
                        title: "Category",
                        searchable: true,
                        sortable: true,
                        data: "Category"
                },
                {
                "targets": [ 3 ],
                        title: "Title",
                        searchable: true,
                        sortable: false,
                        width:"70%",
                        className:'incidentTitle',
                        data: "Title",
                },
                {
                "targets": [ 4 ],
                		title:"User",
                        sortable: false,
                        searchable: false,
                        visible: true,
                        data: "User"
                },
                {
                "targets": [ 5 ],
                		title: "Status",
                		sortable: true,
                		searchable: true,
                		visible: true,
                		data: "Status",
                }
            ]
        };

        var settings = $.extend(true, {}, defaults, options);

        var $wrapper = $(this);
        $wrapper.incidentdialog = incidentdialog

        createViewer($wrapper, settings);

        return this;
    };
	
    function createActions(reportId, row) {
        
        var $ul, $li;
        
        $ul = $('<ul />').addClass('ui-widget ui-helper-clearfix hhk-ui-icons');
        
        // Edit icon
        $li = $('<li title="Edit Incident" data-incidentid="' + reportId + '" />').addClass('hhk-report-button incident-edit ui-corner-all ui-state-default');
        $li.append($('<span class="ui-icon ui-icon-pencil" />'));
        
        $ul.append($li);
        
        // Delete Edit Icon
        $li = $('<li title="Delete report" data-reportid="' + reportId + '" />').addClass('hhk-report-button report-delete ui-corner-all ui-state-default');
        $li.append($('<span class="ui-icon ui-icon-trash" />'));
        
        $ul.append($li);
        
        // Undo Delete Edit Icon
        $li = $('<li title="Undo Delete" data-reportid="' + reportId + '" />').addClass('hhk-report-button report-undodelete ui-corner-all ui-state-default').hide();
        $li.append($('<span class="ui-icon ui-icon-notice" />'));
        
        $ul.append($li);
        
        return $('<div />').append($ul).html();

        //return $ul.html();
    }
    
    function saveIncident($wrapper){
	    console.log($wrapper.incidentdialog.find("form").serialize());

        $.ajax({
            url: settings.serviceURL,
            dataType: 'JSON',
            type: 'post',
            data: {
                    cmd: 'newIncident',
            },
            success: function( data ){
                    if(data.idReport > 0){
                        $table.ajax.reload();
                    }else{
                        if(data.error){
                            settings.alertMessage.call(data.error, 'alert');
                        }else{
                            settings.alertMessage.call('An unknown error occurred.', 'alert');
                        }
                    }
            }
        });
    }
    
    function actions($wrapper, settings, $table) {
        
        //Show new incident
        $wrapper.on('click', '#incident-create', function(e){
        	e.preventDefault();                            
            $wrapper.incidentdialog.find("form")[0].reset();
            $wrapper.incidentdialog.find("textarea").empty();
            $wrapper.incidentdialog.find("option").removeAttr("selected");
            $wrapper.incidentdialog.find("option[value=a]").attr("selected", "selected");
            $wrapper.incidentdialog.dialog("open");			
        });
        
        //Show Edit mode
        $wrapper.on('click', '.incident-edit', function(e){
            e.preventDefault();
            var repID = $(e.target).parent().data('incidentid');
            $.ajax({
                url: 'ws_ckin.php',
                dataType: 'JSON',
                type: 'post',
                data: {
                        cmd: 'getincidentreport',
                        repid: repID,
                },
                success: function( data ){
                        if(data.title){
                            
                            $wrapper.incidentdialog.find("input[name=incidentTitle]").val(data.title);
							$wrapper.incidentdialog.find("input[name=incidentDate]").val(data.reportDate);
							$wrapper.incidentdialog.find("textarea[name=incidentDescription]").text(data.description);
							$wrapper.incidentdialog.find("option[value=" + data.status + "]").attr("selected", "selected");
							$wrapper.incidentdialog.find("textarea[name=incidentResolution]").text(data.resolution);
							$wrapper.incidentdialog.find("input[name=resolutionDate]").val(data.resolutionDate);
                            $wrapper.incidentdialog.dialog("open");
				            
                        }else{
                            
                        }
                }
            });
            
            
			
        });
        //End Show Edit mode
        
        //Edit Note
        $wrapper.on('click', '.note-done', function(e){
            e.preventDefault();
            var noteText = $(this).closest('tr').find('#editNoteText').val();
            var noteId = $(this).closest('td').find('.note-edit').data('noteid');

            if(noteText != ""){
                $.ajax({
                    url: settings.serviceURL,
                    dataType: 'JSON',
                    type: 'post',
                    data: {
                            cmd: 'updateNoteContent',
                            idNote: noteId,
                            data: noteText
                    },
                    success: function( data ){
                            if(data.idNote > 0){
                                $table.ajax.reload();
                            }else{
                                if(data.error){
                                    settings.alertMessage.call(data.error, 'alert');
                                }else{
                                    settings.alertMessage.call('An unknown error occurred.', 'alert');
                                }
                            }
                    }
                });
            }

            $(this).closest('td').find('.note-action').hide();
            $(this).closest('td').find('.note-edit').show();
            $(this).closest('td').find('.note-delete').show();
        });
        //End Edit Note
        
        //Cancel Note
        $wrapper.on('click', '.note-cancel', function(e){
            e.preventDefault();
            var noteText = $(this).data('titletext') + ' - ' + $(this).closest('tr').find('#editNoteText').val();
            $(this).closest('tr').find('.noteText').html(noteText);
            $(this).closest('td').find('.note-action').hide();
            $(this).closest('td').find('.note-edit').show();
            $(this).closest('td').find('.note-delete').show();

        });
        //End Cancel Note
        
        //Delete Note
        $wrapper.on('click', '.note-delete', function(e){
            var idnote = $(this).data("noteid");
            var row = $(this).closest('tr');
            e.preventDefault();
            $.ajax({
                    url: settings.serviceURL,
                    dataType: 'JSON',
                    type: 'post',
                    data: {
                        cmd: 'deleteNote',
                        idNote: idnote
                    },
                    success: function( data ){
                        if(data.idNote > 0){
                            row.find("td:not(:first)").css("opacity", "0.3");
                            var noteText = row.find('#editNoteText').val();
                                    row.find('.noteText').html(noteText);
                                    row.find('.note-action').hide();
                                    row.find('.note-delete').hide();
                                    row.find('.note-edit').hide();
                                    row.find('.note-undodelete').show();
                            $("#noteText").val("");
                            $('#hhk-newNote').removeAttr("disabled").text(settings.newLabel);
                        }else{
                            settings.alertMessage.call(data.error, 'error');
                        }
                    }
                });

        });
        //End Delete Note
        
        //Undo Delete Note
        $wrapper.on('click', '.note-undodelete', function(e){
            var idnote = $(this).data("noteid");

            e.preventDefault();
            $.ajax({
                    url: settings.serviceURL,
                    dataType: 'JSON',
                    type: 'post',
                    data: {
                        cmd: 'undoDeleteNote',
                        idNote: idnote
                    },
                    success: function( data ){
                        if(data.idNote > 0){
                            $table.ajax.reload();
                            $("#noteText").val("");
                            $('#hhk-newNote').removeAttr("disabled").text(settings.newLabel);
                        }else{
                            settings.alertMessage.call(data.error, 'error');
                        }
                    }
                });

        });
        //End Undo Delete Note
    }

    function createViewer($wrapper, settings) {
        
//        console.log(settings.serviceURL + settings.idReservation);
//        console.log(settings.dtCols);
        
        if (settings.guestId > 0 || settings.psgId > 0) {
	        var newBtn = $('<button class="ui-button ui-corner-all ui-state-default" id="incident-create"><span class="ui-icon ui-icon-plus"></span>New Incident</button>').appendTo($wrapper);
            var $table = $('<table />').attr(settings.tableAttrs).appendTo($wrapper);

            var dtTable = $table.DataTable({
	        "columnDefs": settings.dtCols,
	        "serverSide": true,
	        "processing": true,
	        "deferRender": true,
	        "language": {"sSearch": "Search Incidents:"},
	        "sorting": [[1,'desc']],
	        "displayLength": 5,
	        "lengthMenu": [[5, 10, 25, -1], [5, 10, 25, "All"]],
                "dom": '<"dtTop"if>rt<"dtBottom"lp><"clear">',
	        ajax: {
	            url: settings.serviceURL,
                    data: {
                        'cmd': 'getIncidentList',
                        'guestId': settings.guestId,
                        'psgId': settings.psgId
                    },
	        }
            });

            actions($wrapper, settings, dtTable);
            
            //add ignrSave class to Dt controls
            $(".dataTables_filter").addClass('ignrSave');
            $(".dtBottom").addClass('ignrSave');
            
            //add incident dialog
            $wrapper.append($wrapper.incidentdialog);
			$wrapper.incidentdialog.dialog({
				autoOpen: false,
				modal:true,
				width: 800,
				buttons: {
					"Save": function(){
						saveIncident($wrapper)
					},
					Cancel: function() {
						$wrapper.incidentdialog.dialog( "close" );
        			}
      			},
			});
        }
        
        //$wrapper.append(createNewNote(settings, dtTable));

        $wrapper.show();

    }

}(jQuery));