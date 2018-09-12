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
            alertMessage: function (text, isError) {},

            newTaLabel: '  New note text here',
            dtCols: [
                {
                "targets": [ 0 ],
                        "title": "Actions",
                        'data': "Action",
                        "sortable": false,
                        "searchable": false,
                        render: function (data, type, row) {
                            return '<button class="note-edit ui-button ui-corner-all ui-widget" data-noteid="' + data + '">Edit</button>\n\
                                <button class="note-cancel note-action ui-button " title="Cancel Edit" style="display: none; margin-bottom:2px;">Cancel</button>\n\
                                <button class="note-done note-action ui-button ui-corner-all ui-widget" style="display: none; margin-bottom:2px;">Save</button>\n\
                                <button class="note-delete note-action ui-button ui-corner-all ui-widget" data-noteid="' + data + '" style="display: none;">Delete</button>\n\
                                <button class="note-undodelete ui-button ui-corner-all ui-widget" data-noteid="' + data + '" style="display: none;">Undo Delete</button>';
                        }
                },
                {
                "targets": [ 1 ],
                        "title": "Date",
                        'data': 'Date',
                        render: function (data, type) {
                            return dateRender(data, type, dateFormat);
                        }
                },
                {
                "targets": [ 2 ],
                        "title": "User",
                        "searchable": true,
                        "sortable": true,
                        "data": "User"
                },
                {
                "targets": [ 3 ],
                        "title": "Note",
                        "searchable": true,
                        "sortable": false,
                        'width':"60%",
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

    function createNewNote(settings, dtTable) {
        var $div, $ta, $button;
        
        // Create textarea contorl with greyed out label
        $ta = $('<textarea placeholder="' + settings.newTaLabel + '" />').attr(settings.newNoteAttrs);
                
        $div = $('<div style="margin-top:5px;" class="hhk-panel" />').append($ta);
        
        if (settings.linkId > 0) {
            
            $button = $('<button class=" ui-button ui-corner-all ui-widget" id=' + settings.linkId + '"note-newNote" style="vertical-align: top; margin:7px;">Save New Note</button>')
                .click(function (e) {
                    e.preventDefault();
                    var noteTextarea = $('#' + settings.newNoteAttrs.id);
                    var noteData = noteTextarea.val();

                    if (settings.linkId == 0) {
                        settings.alertMessage.call('Link Id is not set.  ', false);
                        return;
                    }

                    if(noteData != ""){

                        $('#note-newNote').attr("disabled", "disabled").text("Saving...");

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
                                    dtTable.ajax.reload();
                                    noteTextarea.val("");
                                    $('#note-newNote').removeAttr("disabled").text(settings.newLabel);
                                }else{
                                    settings.alertMessage.call(data.error, true);
                                }
                            }
                        });
                    }
                });

            
            $div.append($button);
        }
        
        return $div;
    }
    
    function actions($wrapper, settings, $table) {
        
        //Show Edit mode
        $wrapper.on('click', '.note-edit', function(e){
            e.preventDefault();
            var noteText = $(this).closest('tr').find('.noteText').html();
            var noteHeight = $(this).closest('tr').find('.noteText').height();
            $(this).closest('tr').find('.noteText').html('<textarea style="width: 100%; height: ' + noteHeight +'px;" id="editNoteText">' + noteText + '</textarea>');
            $(this).closest('td').find('.note-action').show();
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
                            'cmd': 'updateNoteContent',
                            'idNote': noteId,
                            'data': noteText
                    },
                    success: function( data ){
                            if(data.idNote > 0){
                                $table.ajax.reload();
                            }else{
                                if(data.error){
                                    settings.alertMessage.call(data.error, true);
                                }else{
                                    settings.alertMessage.call('An unknown error occurred.', true);
                                }
                            }
                    }
                });
            }

            $(this).closest('td').find('.note-action').hide();
            $(this).closest('td').find('.note-edit').show();
        });
        //End Edit Note
        
        //Cancel Note
        $wrapper.on('click', '.note-cancel', function(e){
            e.preventDefault();
            var noteText = $(this).closest('tr').find('#editNoteText').val();
            $(this).closest('tr').find('.noteText').html(noteText);
            $(this).closest('td').find('.note-action').hide();
            $(this).closest('td').find('.note-edit').show();

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
                        'cmd': 'deleteNote',
                        'idNote': idnote
                    },
                    success: function( data ){
                        if(data.idNote > 0){
                            row.find("td:not(:first)").css("opacity", "0.3");
                            var noteText = row.find('#editNoteText').val();
                                    row.find('.noteText').html(noteText);
                                    row.find('.note-action').hide();
                                    row.find('.note-undodelete').show();
                            $("#noteText").val("");
                            $('#hhk-newNote').removeAttr("disabled").text(settings.newLabel);
                        }else{
                            settings.alertMessage.call(data.error, true);
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
                        'cmd': 'undoDeleteNote',
                        'idNote': idnote
                    },
                    success: function( data ){
                        if(data.idNote > 0){
                            $table.ajax.reload();
                            $("#noteText").val("");
                            $('#hhk-newNote').removeAttr("disabled").text(settings.newLabel);
                        }else{
                            settings.alertMessage.call(data.error, true);
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
	        "sorting": [[1,'desc']],
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

        $wrapper.show();

    }

}(jQuery));