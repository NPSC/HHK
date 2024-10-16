(function ($) {

    $.fn.notesViewer = function (options) {

        var defaults = {
            linkId: 0,
            linkType: 0,
            uid: 0,
            serviceURL: 'ws_resv.php',
            newLabel: 'Save New Note',
            tableAttrs: {
                class: 'display compact',
                width: '100%'
            },
            newNoteAttrs: {
                id: 'noteText',
                style: 'width: 100%;',
                rows: 2,
                class: 'mr-3 p-2 hhk-autosize ui-widget-content ui-corner-all'
            },
            newNoteLocation: 'bottom',
            
            defaultLength: 5,
            defaultLengthMenu: [[5, 10, 25, -1], [5, 10, 25, "All"]],
            
            alertMessage: function (text, type) {},

            newTaLabel: 'New note...',
            
            dtCols: [
                {
                "targets":0,
                        title: "Flag",
                        data: "Flag",
                        sortable: true,
                        searchable: false,
                        className:'actionBtns',
                        width: "40px",
                        render: function (data, type, row) {
                            return showFlag(data, row);  
                        }
                },
                {
                "targets":1,
                        title: "Actions",
                        data: "Action",
                        sortable: false,
                        searchable: false,
                        className:'actionBtns',
                        width: "50px",
                        render: function (data, type, row) {
                            return createActions(data, row);  
                        }
                },
                {
                "targets":2,
                        title: "Date",
                        data: 'Date',
                        sortable: true,
                        width: "110px",
                        render: function (data, type) {
                            return dateRender(data, type, 'MMM D, YYYY h:mm a');
                        }
                },
                {
                "targets":3,
                        title: "User",
                        searchable: true,
                        sortable: true,
                        data: "User",
                        width: "100px"
                },
                {
                "targets":4,
                        title: "Note",
                        searchable: true,
                        sortable: false,
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
                "targets":5,
                        sortable: false,
                        searchable: false,
                        visible: false,
                        data: "Title"
                }
            ]
        };

        var settings = $.extend(true, {}, defaults, options);

        var $wrapper = $(this);
        
        if(settings.linkType == 'staff'){
        	settings.dtCols.forEach(function(El, Index, array){
        		if(El.targets > 3){
        			El.targets = El.targets + 1;
        			settings.dtCols[Index] = El;
        		}
        	});
        
        	settings.dtCols.push({
                	"targets": 4,
                        sortable: true,
                        searchable: true,
                        data: "Category",
                        title: "Category",
                        className: "noteCategory",
                        name: "Category",
                        width: "120px",
                        render: function (data, type, row) {
                            
                            if (settings.staffNoteCats[data]) {
                                return settings.staffNoteCats[data].Description + '<span data-cat="' + data + '"></span>';
                            }
                            return data;
                        }
                	});
        }
        
        //set uid
        $wrapper.attr('id', '');
        $wrapper.uniqueId();
        uid = $wrapper.attr('id');

        createViewer($wrapper, settings);
        
        return this;
    };

    function createNewNote(settings, dtTable, $wrapper) {
        var $div, $ta, $button;
        
        // Create textarea contorl with greyed out label
        $ta = $('<textarea placeholder="' + settings.newTaLabel + '" />').attr(settings.newNoteAttrs);
                
        $div = $('<div class="hhk-panel d-block d-md-flex" style="align-items: center" />').append($ta);
        
        if (settings.linkType == 'staff'){
        	$div.append(categorySelector(settings));
        }
        
        if (settings.linkId >= 0) {
            
            $button = $('<button class=" ui-button ui-corner-all ui-widget mt-2 mt-md-0 ml-3" id="note-newNote" style="min-width:fit-content">Save New Note</button>')
                .click(function (e) {
                    e.preventDefault();
                    var noteTextarea = $('#' + settings.newNoteAttrs.id);
                    var noteData = noteTextarea.val();
                    if(settings.linkType == "staff"){
                    	var noteCategory = $wrapper.find("#noteCategory").val();
                    }else{
                    	var noteCategory = '';
                    }

                    if (settings.linkId < 0) {
                        flagAlertMessage('Link Id is not set.  ', 'error');
                        return;
                    }

                    if (noteData != "") {
                        
                        //convert noteData to base64
                        let base64note = buffer.Buffer.from(noteData).toString("base64");

                        $('#note-newNote').attr("disabled", "disabled").text("Saving...");

                        $.ajax({
                            url: settings.serviceURL,
                            dataType: 'JSON',
                            type: 'post',
                            data: {
                                cmd: 'saveNote',
                                linkType: settings.linkType,
                                linkId: settings.linkId,
                                noteCategory:noteCategory,
                                data: base64note
                            },
                            success: function( data ){
                                if(data.idNote > 0){
                                    dtTable.ajax.reload();
                                    noteTextarea.val("").trigger('input');
                                    $('#note-newNote').removeAttr("disabled").text(settings.newLabel);
                                }else if(data.error){
                                    if (data.gotopage) {
                                        window.open(data.gotopage);
                                    }
                                    flagAlertMessage(data.error, 'error');
                                }
                                $('#note-newNote').removeAttr("disabled").text(settings.newLabel);
                            },
                            error: function(XHR, textStatus, errorText){
                                flagAlertMessage("Error " + XHR.status + ": " + errorText, 'error');
                                $('#note-newNote').removeAttr("disabled").text(settings.newLabel);
                                if(typeof hhkReportError == "function"){
                                    var errorInfo = {
                                        responseCode: XHR.status,
                                        source:"notesViewer::saveNote",
                                        linkType: settings.linkType,
                                        linkId: settings.linkId,
                                        noteText: noteData
                                    }
                                    errorInfo = btoa(JSON.stringify(errorInfo));
                                    hhkReportError(errorText, errorInfo);
                                }
                                $('#note-newNote').removeAttr("disabled").text(settings.newLabel);
                            }
                        });
                    }
                });

            
            $div.append($button);
        }
        
        return $div;
    }

	function showFlag(flagged, row){
		var flagContainer = $("<span />");
		var flagLabel = $("<label />").prop("for", "flag-" + uid + "-" + row.NoteId).prop('title', 'Flag this note to bold it and make it stay at the top of the list').text("Flag");
		
		if(flagged == "1"){
			var flagEl = $('<input type="checkbox" name="flag" checked="true" id="flag-' + uid + "-" + row.NoteId + '" />').addClass("flag");
		}else{
			var flagEl = $('<input type="checkbox" name="flag" id="flag-' + uid + "-" + row.NoteId + '" />').addClass("flag");

		}
		
		flagContainer.append(flagLabel);
		flagContainer.append(flagEl);
		
		return flagContainer.html();
	}

    function createActions(noteId, row) {
        
        var $ul, $li;
        
        $ul = $('<ul />').addClass('ui-widget ui-helper-clearfix hhk-ui-icons hhk-flex');
        
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
        
        $wrapper.off('click');
        
        //Show Edit mode
        $wrapper.on('click', '.note-edit', function(e){
            e.preventDefault();
            var selectedCategory = $(this).closest('tr').find('.noteCategory span[data-cat]').data('cat');
            $(this).closest('tr').find('.noteCategory').html(categorySelector(settings, selectedCategory));
            $(this).closest('tr').find('.noteText').html('<div class="hhk-flex"><textarea class="p-2 hhk-autosize ui-widget-content ui-corner-all" style="width: 100%; height: ' + $(this).closest('tr').find('.noteText').height() +'px;" id="editNoteText">' + $(this).data('notetext') + '</textarea></div>');
            $(this).closest('td').find('.note-action').show();
            $(this).closest('td').find('.note-delete').hide();
            $(this).hide();
            $wrapper.find('.hhk-note-button').button();
        });
        //End Show Edit mode
        
        //Edit Note
        $wrapper.on('click', '.note-done', function(e){
            e.preventDefault();
            var row = $(this).closest("tr");
            var noteCategory = $(this).closest('tr').find('#noteCategory').val();
            var noteText = $(this).closest('tr').find('#editNoteText').val();
            var noteId = $(this).closest('td').find('.note-edit').data('noteid');

            if (noteText != "") {
                
                //convert noteText to base64
                let base64note = buffer.Buffer.from(noteText).toString("base64");


                $.ajax({
                    url: settings.serviceURL,
                    dataType: 'JSON',
                    type: 'post',
                    data: {
                            cmd: 'updateNoteContent',
                            idNote: noteId,
                            data: base64note,
                            noteCategory: noteCategory,
                    },
                    success: function( data ){
                        if(data.idNote > 0){
                            //$table.ajax.reload();
                            var rowdata = $table.row(row).data();
                            rowdata["Note"] = noteText;
                            rowdata["Category"] = noteCategory;
							$table.row(row).data(rowdata);
							row.find('.noteText').removeClass('hhk-flex');
							row.find("input.flag").checkboxradio({icon:false});
							$wrapper.find('.hhk-note-button').button();
                        }else if(data.error){
                            if (data.gotopage) {
                                window.open(data.gotopage);
                            }
                            flagAlertMessage(data.error, 'error');
                        }else{
                            flagAlertMessage('An unknown error occurred.', 'alert');
                        }
                    },
                    error: function(XHR, textStatus, errorText){
                        flagAlertMessage(errorText, 'error');
                        $('#hhk-newNote').removeAttr("disabled").text(settings.newLabel);
                        if(typeof hhkReportError == "function"){
                            var errorInfo = {
                                responseCode: XHR.status,
                                source:"notesViewer::updateNoteContent",
                                linkType: settings.linkType,
                                linkId: settings.linkId,
                                idNote: noteId,
                                noteText: noteText
                            }
                            errorInfo = btoa(JSON.stringify(errorInfo));
                            hhkReportError(errorText, errorInfo);
                        }
                    }
                });
            }
        });
        //End Edit Note
        
        //Cancel Note
        $wrapper.on('click', '.note-cancel', function(e){
            e.preventDefault();
			
			var row = $(this).closest("tr");
			var rowdata = $table.row(row).data();
			$table.row(row).data(rowdata);
			row.find("input.flag").checkboxradio({icon:false});
			$wrapper.find('.hhk-note-button').button();

        });
        //End Cancel Note
        
        //Delete Note
        $wrapper.on('click', '.note-delete', function(e){
            var idnote = $(this).data("noteid");
            var row = $(this).closest('tr');
            e.preventDefault();
            if($table.row(row).data()["Flag"] == "1"){
	            var confirmed = confirm("This Note is flagged, are you sure you want to delete it?");
	            if(!confirmed){
		            return;
	            }
            }
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
                            row.find("td:not(.actionBtns)").css("opacity", "0.3");
                            var noteText = row.find('#editNoteText').val();
                                    row.find('.noteText').html(noteText);
                                    row.find('.note-action').hide();
                                    row.find('.note-delete').hide();
                                    row.find('.note-edit').hide();
                                    row.find('.note-undodelete').show();
                            $("#noteText").val("");
                            $('#hhk-newNote').removeAttr("disabled").text(settings.newLabel);
                            $wrapper.find('.hhk-note-button').button();
                        }else if(data.error){
                            if (data.gotopage) {
                                window.open(data.gotopage);
                            }
                            flagAlertMessage(data.error, 'error');
						}
                    },
                    error: function(XHR, textStatus, errorText){
                        flagAlertMessage(errorText, 'error');
                        $('#hhk-newNote').removeAttr("disabled").text(settings.newLabel);
                        if(typeof hhkReportError == "function"){
                            var errorInfo = {
                                responseCode: XHR.status,
                                source:"notesViewer::deleteNote",
                                linkType: settings.linkType,
                                linkId: settings.linkId,
                                idNote: idnote
                            }
                            errorInfo = btoa(JSON.stringify(errorInfo));
                            hhkReportError(errorText, errorInfo);
                        }
                    }
                });

        });
        //End Delete Note
        
        //Undo Delete Note
        $wrapper.on('click', '.note-undodelete', function(e){
            var idnote = $(this).data("noteid");
			var row = $(this).parents("tr");
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
                            //$table.ajax.reload();
                            var rowdata = $table.row(row).data();
                            $table.row(row).data(rowdata);
                            row.find("td").css("opacity", "1");
                            row.find("input.flag").checkboxradio({icon:false});
                            $("#noteText").val("");
                            $('#hhk-newNote').removeAttr("disabled").text(settings.newLabel);
                            $wrapper.find('.hhk-note-button').button();
                        }else if(data.error){
                            if (data.gotopage) {
                                window.open(data.gotopage);
                            }
                            flagAlertMessage(data.error, 'error');
                            $('#hhk-newNote').removeAttr("disabled").text(settings.newLabel);
                        }
                    },
                    error: function(XHR, textStatus, errorText){
                        flagAlertMessage(errorText, 'error');
                        $('#hhk-newNote').removeAttr("disabled").text(settings.newLabel);
                        if(typeof hhkReportError == "function"){
                            var errorInfo = {
                                responseCode: XHR.status,
                                source:"notesViewer::undoDeleteNote",
                                linkType: settings.linkType,
                                linkId: settings.linkId,
                                idNote: idnote
                            }
                            errorInfo = btoa(JSON.stringify(errorInfo));
                            hhkReportError(errorText, errorInfo);
                        }
                    }
                });

        });
        //End Undo Delete Note
        
        //Flag Note
        $wrapper.on('click', 'input.flag', function(e){
            var rowtr = $(this).closest('tr');
            var row = $table.row(rowtr);
            var idnote = row.data()["NoteId"];
            if($(this).prop('checked') == true){
	            flag = 1;
	            rowtr.css("font-weight", "bold");
            }else{
	            flag = 0;
	            rowtr.css("font-weight", "normal")
            }
            rowtr.find("td").css("opacity", "1");
            
            if(rowtr.find(".note-done").is(":visible")){
	            rowtr.find(".note-done").trigger("click");
            }
            
            e.preventDefault();
            $.ajax({
                    url: settings.serviceURL,
                    dataType: 'JSON',
                    type: 'post',
                    data: {
                        cmd: 'flagNote',
                        idNote: idnote,
                        flag: flag,
                    },
                    success: function( data ){
                        if(data.idNote > 0){
	                        var rowdata = row.data();
	                        rowdata["Flag"] = data.flag;
                            row.data(rowdata);
                            rowtr.find("input.flag").checkboxradio({icon:false});
                            $wrapper.find('.hhk-note-button').button();
                        }else if(data.error){
                            if (data.gotopage) {
                                window.open(data.gotopage);
                            }
                            flagAlertMessage(data.error, 'error');
                            $('#hhk-newNote').removeAttr("disabled").text(settings.newLabel);
                        }
                    },
                    error: function(XHR, textStatus, errorText){
                        flagAlertMessage(errorText, 'error');
                        $('#hhk-newNote').removeAttr("disabled").text(settings.newLabel);
                        if(typeof hhkReportError == "function"){
                            var errorInfo = {
                                responseCode: XHR.status,
                                source:"notesViewer::flagNote",
                                linkType: settings.linkType,
                                linkId: settings.linkId,
                                idNote: idnote
                            }
                            errorInfo = btoa(JSON.stringify(errorInfo));
                            hhkReportError(errorText, errorInfo);
                        }
                    }
                });

        });
        //End Flag Note
        
        //Category Filter
        
        $wrapper.on('click', '.btnCat', function(e){
        	e.preventDefault();
        	$btnWrapper = $(this).closest('#noteCatBtns');
        	$btnWrapper.find('button').removeClass('catActive');
        	$(this).addClass('catActive');
        	searchVal = $(this).data('id');
        	if(searchVal != ''){
        		$table.column(".noteCategory").search("^" + searchVal + "$" , true).draw();
        	}else{
        		$table.column(".noteCategory").search('').draw();
        	}
        });
        
        //End Category Filter
    }
    
    function categorySelector(settings, selected = false){
    
    	$catSelect = $('<select name="noteCategory" id="noteCategory" class="mt-2 mt-md-0"><option disabled ' + (selected == false ? 'selected': '') + '>-- Select Category --</option><option></option></select>');
        
        for(var k in settings.staffNoteCats){
        	if(k == selected){
        		$catSelect.append('<option value="' + k + '" selected>' + settings.staffNoteCats[k].Description + '</option>');
        	}else{
        		$catSelect.append('<option value="' + k + '">' + settings.staffNoteCats[k].Description + '</option>');
        	}
        };
        return $catSelect;
    }
    
    function categoryFilter(settings){
    	$filterWrapper = $('<div class="d-flex mt-2"><div class="d-md-none d-flex" style="align-items:center"><span class="ui-icon ui-icon-triangle-1-w"></span></div><div id="noteCatBtns"></div><div class="d-md-none d-flex" style="align-items:center"><span class="ui-icon ui-icon-triangle-1-e"></span></div></div>');
    	$noteCatBtns = $filterWrapper.find('#noteCatBtns');
    	$noteCatBtns.append('<button class="btnCat catActive" data-id="0">All</button>');
    	for(var k in settings.staffNoteCats){
        	$noteCatBtns.append('<button class="btnCat" data-id="' + k + '">' + settings.staffNoteCats[k].Description + '</button>');
        };
        
        return $filterWrapper;
    }

    function createViewer($wrapper, settings) {
        
        if (settings.linkId >= 0) {
            var $table = $('<table />').attr(settings.tableAttrs).appendTo($wrapper);

            var dtTable = $table.DataTable({
		        "columnDefs": settings.dtCols,
		        "serverSide": true,
		        "processing": true,
		        "deferRender": true,
		        "language": {"sSearch": "Search Notes:"},
		        "sorting": [[0,'desc'], [2,'desc']],
		        "displayLength": settings.defaultLength,
		        "lengthMenu": settings.defaultLengthMenu,
	                "dom": '<"dtTop"if><"hhk-overflow-x"rt><"dtBottom"lp><"clear">',
		        ajax: {
		            url: settings.serviceURL,
	                data: {
	                    'cmd': 'getNoteList',
	                    'linkType': settings.linkType,
	                    'linkId': settings.linkId
	                },
                    error: function(XHR, textStatus, errorText){
                        flagAlertMessage(errorText, "error");
                        if(typeof hhkReportError == "function"){
                            var errorInfo = {
                                responseCode: XHR.status,
                                source:"notesViewer::createViewer",
                                linkType: settings.linkType,
                                linkId: settings.linkId
                            }
                            errorInfo = btoa(JSON.stringify(errorInfo));
                            hhkReportError(errorText, errorInfo);
                        }
                    }
		        },
		        "drawCallback": function(settings){
			        $wrapper.find("input.flag").checkboxradio({icon:false});
			        
			        $wrapper.find('.hhk-note-button').button();
		        },
		        "createdRow": function( row, data, dataIndex){
	                if( data["Flag"] ==  1){
	                    $(row).css("font-weight", "bold");
	                }
	            }
            });

            actions($wrapper, settings, dtTable);
            
            //add jquery UI checkbox
            $wrapper.find('.flag').checkboxradio({icon: false});
            
            //add ignrSave class to Dt controls
            $(".dataTables_filter").addClass('ignrSave');
            $(".dtBottom").addClass('ignrSave');
            
        }
        
        if(settings.newNoteLocation == 'top'){
        	$wrapper.prepend(categoryFilter(settings));
        	$wrapper.prepend(createNewNote(settings, dtTable, $wrapper));
        }else{
        	$wrapper.append(createNewNote(settings, dtTable, $wrapper));
		}
		
        $wrapper.show();

    }

}(jQuery));