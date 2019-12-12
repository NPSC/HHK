(function ($) {

    $.fn.notesViewer = function (options) {

        var defaults = {
            linkId: 0,
            linkType: 0,
            serviceURL: 'ws_resv.php',
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
            alertMessage: function (text, type) {},

            newTaLabel: 'New note text here',
            
            dtCols: [
                {
                "targets": [ 0 ],
                        title: "Flag",
                        data: "Flag",
                        sortable: true,
                        searchable: false,
                        render: function (data, type, row) {
                            return showFlag(data, row);  
                        }
                },
                {
                "targets": [ 1 ],
                        title: "Actions",
                        data: "Action",
                        sortable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            return createActions(data, row);  
                        }
                },
                {
                "targets": [ 2 ],
                        title: "Date",
                        data: 'Date',
                        sortable: true,
                        render: function (data, type) {
                            return dateRender(data, type, dateFormat);
                        }
                },
                {
                "targets": [ 3 ],
                        title: "User",
                        searchable: true,
                        sortable: true,
                        data: "User"
                },
                {
                "targets": [ 4 ],
                        title: "Note",
                        searchable: true,
                        sortable: false,
                        width:"70%",
                        className:'noteText',
                        data: "Note",
                        render: function (data, type, row) {
                            
                            if (row.Title !== '') {
                                return row.Title + ' - ' + data;
                            }
                            return data;
                        }
                },
                {
                "targets": [ 5 ],
                        sortable: false,
                        searchable: false,
                        visible: false,
                        data: "Title"
                }
            ]
        };

        var settings = $.extend(true, {}, defaults, options);

        var $wrapper = $(this);

        createViewer($wrapper, settings);

        return this;
    };

    function createNewNote(settings, dtTable) {
        var $div, $ta, $button;
        
        // Create textarea contorl with greyed out label
        $ta = $('<textarea placeholder="' + settings.newTaLabel + '" />').attr(settings.newNoteAttrs);
                
        $div = $('<div style="margin-top:5px;" class="hhk-panel" />').append($ta);
        
        if (settings.linkId > 0) {
            
            $button = $('<button class=" ui-button ui-corner-all ui-widget" id="note-newNote" style="vertical-align: top; margin:7px;">Save New Note</button>')
                .click(function (e) {
                    e.preventDefault();
                    var noteTextarea = $('#' + settings.newNoteAttrs.id);
                    var noteData = noteTextarea.val();

                    if (settings.linkId == 0) {
                        settings.alertMessage.call('Link Id is not set.  ', 'alert');
                        return;
                    }

                    if(noteData != ""){

                        $('#note-newNote').attr("disabled", "disabled").text("Saving...");

                        $.ajax({
                            url: 'ws_resv.php',
                            dataType: 'JSON',
                            type: 'post',
                            data: {
                                cmd: 'saveNote',
                                linkType: settings.linkType,
                                linkId: settings.linkId,
                                data: noteData
                            },
                            success: function( data ){
                                if(data.idNote > 0){
                                    dtTable.ajax.reload();
                                    noteTextarea.val("");
                                    $('#note-newNote').removeAttr("disabled").text(settings.newLabel);
                                }else{
                                    settings.alertMessage.call(data.error, 'alert');
                                }
                            }
                        });
                    }
                });

            
            $div.append($button);
        }
        
        return $div;
    }

	function showFlag(flagged, row){
		if(flagged){
			return '<label for="flag">Flag</label>' +
			'<input type="checkbox" name="flag" class="flag" checked="true">';
		}else{
			return '<label for="flag">Flag</label>' +
			'<input type="checkbox" name="flag" class="flag" checked="false">';
		}
	}

    function createActions(noteId, row) {
        
        var $ul, $li;
        
        $ul = $('<ul />').addClass('ui-widget ui-helper-clearfix hhk-ui-icons');
        
        // Edit icon
        $li = $('<li title="Edit Note" data-noteid="' + noteId + '" data-notetext="' + row.Note + '" />').addClass('hhk-note-button note-edit ui-corner-all ui-state-default');
        $li.append($('<span class="ui-icon ui-icon-pencil" />'));
        
        $ul.append($li);
        
        // Save(Done) Edit Icon
        $li = $('<li title="Save Note" />').addClass('hhk-note-button note-done note-action ui-corner-all ui-state-default').hide();
        $li.append($('<span class="ui-icon ui-icon-check" />'));
        
        $ul.append($li);
        
        // Cancel Edit Icon
        $li = $('<li title="Cancel" data-titletext="' + row.Title + '" />').addClass('hhk-note-button note-cancel note-action ui-corner-all ui-state-default').hide();
        $li.append($('<span class="ui-icon ui-icon-cancel" />'));
        
        $ul.append($li);
        
        // Delete Edit Icon
        $li = $('<li title="Delete Note" data-noteid="' + noteId + '" />').addClass('hhk-note-button note-delete ui-corner-all ui-state-default');
        $li.append($('<span class="ui-icon ui-icon-trash" />'));
        
        $ul.append($li);
        
        // Undo Delete Edit Icon
        $li = $('<li title="Undo Delete" data-noteid="' + noteId + '" />').addClass('hhk-note-button note-undodelete ui-corner-all ui-state-default').hide();
        $li.append($('<span class="ui-icon ui-icon-arrowreturnthick-1-w" />'));
        
        $ul.append($li);
        
        return $('<div />').append($ul).html();

        //return $ul.html();
    }
    
    function actions($wrapper, settings, $table) {
        
        //Show Edit mode
        $wrapper.on('click', '.note-edit', function(e){
            e.preventDefault();
            $(this).closest('tr').find('.noteText').html('<textarea style="width: 100%; height: ' + $(this).closest('tr').find('.noteText').height() +'px;" id="editNoteText">' + $(this).data('notetext') + '</textarea>');
            $(this).closest('td').find('.note-action').show();
            $(this).closest('td').find('.note-delete').hide();
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
        
        if (settings.linkId > 0) {
            var $table = $('<table />').attr(settings.tableAttrs).appendTo($wrapper);

            var dtTable = $table.DataTable({
	        "columnDefs": settings.dtCols,
	        "serverSide": true,
	        "processing": true,
	        "deferRender": true,
	        "language": {"sSearch": "Search Notes:"},
	        "sorting": [[0,'asc'], [2,'desc']],
	        "displayLength": 5,
	        "lengthMenu": [[5, 10, 25, -1], [5, 10, 25, "All"]],
                "dom": '<"dtTop"if>rt<"dtBottom"lp><"clear">',
	        ajax: {
	            url: settings.serviceURL,
                    data: {
                        'cmd': 'getNoteList',
                        'linkType': settings.linkType,
                        'linkId': settings.linkId
                    },
	        }
            });

            actions($wrapper, settings, dtTable);
            
            //add ignrSave class to Dt controls
            $(".dataTables_filter").addClass('ignrSave');
            $(".dtBottom").addClass('ignrSave');
            
        }
        
        $wrapper.append(createNewNote(settings, dtTable));

		$wrapper.find('.hhk-note-button').button();
		
		$wrapper.find(".flag").checkboxradio({
			icon: false
		});

        $wrapper.show();

    }

}(jQuery));