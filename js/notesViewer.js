(function ($) {

    $.fn.notesViewer = function (options) {

        var defaults = {
            linkId: 0,
            linkType: 0,
            serviceURL: 'ws_resv.php?cmd=getNoteList&rid=',
            newLabel: 'New Note',
            tableAttrs: {
                class: 'display compact',
                width: '100%'
            },
            newNoteAttrs: {
                id: 'noteText',
                style: 'width: 80%;',
                rows: 2
            },

            dtCols: [
                {
                "targets": [ 0 ],
                        "title": "Actions",
                        'data': "Action",
                        'width': "15%",
                        render: function (data, type, row) {
                            return '<button class="note-edit ui-button ui-corner-all ui-widget" data-noteid="' + data + '">Edit</button><button class="note-done ui-button ui-corner-all ui-widget" style="display: none; margin-bottom:5px;">Save</button><button class="note-delete ui-button ui-corner-all ui-widget" data-noteid="' + data + '" style="display: none;">Delete</button>';
                        }
                },
                {
                "targets": [ 1 ],
                        "title": "Date",
                        'data': 'Date',
                        'width': '25%',
                        render: function (data, type) {
                            return dateRender(data, type, dateFormat);
                        }
                },
                {
                "targets": [ 2 ],
                        "title": "User",
                        "searchable": true,
                        "sortable": true,
                        "width": "20%",
                        "data": "User"
                },
                {
                "targets": [ 3 ],
                        "title": "Note",
                        "searchable": true,
                        "sortable": false,
                        "className":'noteText',
                        "data": "Note"
                }
            ]
        };

        var settings = $.extend(true, {}, defaults, options);

        var $wrapper = $(this);

        createViewer($wrapper, settings);

        return this;
    };

    function createFooter(settings) {
        
        return $('<tfoot />').append($('<td colspan="7" style="vertical-align:top;"/>')
                .append( $('<textarea/>').attr(settings.newNoteAttrs))
                .append('<button class=" ui-button ui-corner-all ui-widget" id="note-newNote" style="vertical-align: top; margin:6px;">Save New Note</button>')
                );
    }
    
    function actions($wrapper, settings, $table) {
		
        //Create new note
        $wrapper.on('click', '#note-newNote', function(e){
            e.preventDefault();
            $('#note-newNote').attr("disabled", "disabled").text("Saving...");
            var noteTextarea = $('#' + settings.newNoteAttrs.id);
            var noteData = noteTextarea.val();

            if(noteData != ""){
                $.ajax({
                        url: 'ws_resv.php',
                        dataType: 'JSON',
                        type: 'post',
                        data: {
                                'cmd': 'saveNote',
                                'linkType': settings.linkType,
                                'linkId': settings.linkId,
                                'data': noteData
                        },
                        success: function( data ){
                                if(data.idNote > 0){
                                        $table.ajax.reload();
                                        noteTextarea.val("");
                                        $('#note-newNote').removeAttr("disabled").text(settings.newLabel);
                                }else{
                                        $("#divAlertMsg #alrMessage").html("<strong>Error:</strong> " + data.error);
                                        $("#divAlertMsg #divAlert1").show();
                                }
                        }
                });
            }else{
                $('#note-newNote').removeAttr("disabled").text(settings.newLabel);
            }
        });
        //End Create new note
        
        //Show Edit mode
        $wrapper.on('click', '.note-edit', function(e){
            e.preventDefault();
            var noteText = $(this).closest('tr').find('.noteText').html();
            var noteHeight = $(this).closest('tr').find('.noteText').height();
            $(this).closest('tr').find('.noteText').html('<textarea style="width: 100%; height: ' + noteHeight +'px;" id="editNoteText">' + noteText + '</textarea>');
            $(this).closest('td').find('.note-delete').show();
            $(this).closest('td').find('.note-done').show();
            $(this).hide();
        });
        //End Show Edit mode
        
        //Edit Note
        $wrapper.on('click', '.note-done', function(e){
            e.preventDefault();
            var noteText = $(this).closest('tr').find('#editNoteText').val();
            var noteId = $(this).closest('td').find('.note-edit').data('noteid');

            if(noteText != ""){
                    $.ajax({
                            url: 'ws_resv.php',
                            dataType: 'JSON',
                            type: 'post',
                            data: {
                                    'cmd': 'updateNoteContent',
                                    'idNote': noteId,
                                    'data': noteText
                            },
                            success: function( data ){
                                    if(data.idNote > 0){
                                            $table.ajax.reload();
                                    }else{
                                            if(data.error){
                                                    $("#divAlertMsg #alrMessage").html("<strong>Error:</strong> " + data.error);
                                            }else{
                                                    $("#divAlertMsg #alrMessage").html("<strong>Error:</strong> An unknown error occurred.");
                                            }
                                                    $("#divAlertMsg #divAlert1").show();
                                    }
                            }
                        });
            }

            $(this).closest('td').find('.note-delete').hide();
            $(this).closest('td').find('.note-edit').show();
            $(this).hide();
        });
        //End Edit Note
        
        //Delete Note
        $wrapper.on('click', '.note-delete', function(e){
	        var idnote = $(this).data("noteid");
	        e.preventDefault();
	        $.ajax({
		    	url: 'ws_resv.php',
		    	dataType: 'JSON',
		    	type: 'post',
		    	data: {
			    	'cmd': 'deleteNote',
			    	'idNote': idnote
		    	},
		    	success: function( data ){
			    	if(data.idNote > 0){
			    		$table.ajax.reload();
			    		$("#noteText").val("");
			    		$('#hhk-newNote').removeAttr("disabled").text(settings.newLabel);
			    	}else{
				    	$("#divAlertMsg #alrMessage").html("<strong>Error:</strong> " + data.error);
					    $("#divAlertMsg #divAlert1").show();
			    	}
		    	}
		    });
	        
        });
        //End Delete Note
	}

    function createViewer($wrapper, settings) {
        
        console.log(settings.serviceURL + settings.idReservation);
        console.log(settings.dtCols);
        var $table = $('<table />').attr(settings.tableAttrs).appendTo($wrapper);

        var dtTable = $table.DataTable({
	        "columnDefs": settings.dtCols,
	        "serverSide": true,
	        "processing": true,
	        "deferRender": true,
	        "language": {"sSearch": "Search Notes:"},
	        "sorting": [[0,'desc']],
	        "displayLength": 5,
	        "lengthMenu": [[5, 10, 25, -1], [5, 10, 25, "All"]],
                "dom": '<"dtTop"if>rt<"dtBottom"lp><"clear">',
	        ajax: {
	            url: settings.serviceURL + settings.linkId
	        }
		});
		
		$table.append(createFooter(settings));
		
		actions($wrapper, settings, dtTable);
		
		$wrapper.show();

    }

}(jQuery));