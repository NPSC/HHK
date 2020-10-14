(function ($) {

    $.fn.docUploader = function (options) {

		var uploader = 
						'<div id="docUploadBtn" class="ui-button ui-corner-all ui-widget">' +
							'<span class="ui-icon ui-icon-plusthick" style="margin-right: 0.5em"></span>New Document' +
						'</div>';

        var defaults = {
            guestId: 0,
            psgId: 0,
            rid: 0,
            serviceURL: 'ws_resc.php',
            newLabel: 'New Document',
            tableAttrs: {
                class: 'display compact',
                width: '100%'
            },
            alertMessage: function (text, type) {},

            dtCols: [
                {
                    "targets": [0],
                    title: "Actions",
                    data: "Action",
                    sortable: false,
                    searchable: false,
                    render: function (data, type, row) {
                        return createActions(data, row);
                    }
                },
                {
                    "targets": [1],
                    title: "Date",
                    data: 'Date',
                    render: function (data, type) {
                        return dateRender(data, type, dateFormat);
                    }
                },
                {
                    "targets": [2],
                    title: "Guest",
                    searchable: true,
                    sortable: true,
                    data: "Guest"
                },
                {
                    "targets": [3],
                    title: "Title",
                    searchable: true,
                    sortable: false,
                    className: 'docTitle',
                    data: "Title",
                },
                {
                    "targets": [4],
                    title: "User",
                    sortable: false,
                    searchable: false,
                    visible: true,
                    data: "User"
                },
                {
                    "targets": [5],
                    title: "View Doc",
                    data: "View Doc",
                    sortable: false,
                    searchable: false,
                    render: function (data, type, row) {
                        return createDownload(data, row);
                    }
                },
            ]
        };

        var settings = $.extend(true, {}, defaults, options);

        var $wrapper = $(this);
        $wrapper.uploader = uploader;

        createViewer($wrapper, settings);

        return this;
    };

    function clearform($wrapper) {
        $($wrapper.uploader).find("input").val("");
    }

    function createActions(docId, row) {
        
        var $ul, $li;
        
        $ul = $('<ul />').addClass('ui-widget ui-helper-clearfix hhk-ui-icons');
        
        // Edit icon
        $li = $('<li title="Edit Doc" data-docid="' + docId + '" data-docTitle="' + row.Title + '" />').addClass('hhk-doc-button doc-edit ui-corner-all ui-state-default');
        $li.append($('<span class="ui-icon ui-icon-pencil" />'));
        
        $ul.append($li);
        
        // Save(Done) Edit Icon
        $li = $('<li title="Save Doc" />').addClass('hhk-doc-button doc-done doc-action ui-corner-all ui-state-default').hide();
        $li.append($('<span class="ui-icon ui-icon-check" />'));
        
        $ul.append($li);
        
        // Cancel Edit Icon
        $li = $('<li title="Cancel" data-titletext="' + row.Title + '" />').addClass('hhk-doc-button doc-cancel doc-action ui-corner-all ui-state-default').hide();
        $li.append($('<span class="ui-icon ui-icon-cancel" />'));
        
        $ul.append($li);
        
        // Delete Edit Icon
        $li = $('<li title="Delete Doc" data-docid="' + docId + '" />').addClass('hhk-doc-button doc-delete ui-corner-all ui-state-default');
        $li.append($('<span class="ui-icon ui-icon-trash" />'));
        
        $ul.append($li);
        
        // Undo Delete Edit Icon
        $li = $('<li title="Undo Delete" data-docid="' + docId + '" />').addClass('hhk-doc-button doc-undodelete ui-corner-all ui-state-default').hide();
        $li.append($('<span class="ui-icon ui-icon-arrowreturnthick-1-w" />'));
        
        $ul.append($li);
        
        return $('<div />').append($ul).html();

        //return $ul.html();
    }
    
    function createDownload(docId, row) {
        
        var $btn
                
        $btn = $('<a data-docid="' + docId + '" href="ws_resc.php?cmd=getdoc&docId=' + docId + '" target="_blank" />').addClass('hhk-doc-button doc-download ui-corner-all ui-state-default');
        
        $btn.append('Open<span class="ui-icon ui-icon-extlink" style="margin-left: 0.5em;"></span>').button();
                
        return $('<div />').append($btn).html();

        //return $ul.html();
    }

    function actions($wrapper, settings, $table) {
        
        //Show Edit mode
        $wrapper.on('click', '.doc-edit', function(e){
            e.preventDefault();
            $(this).closest('tr').find('.docTitle').html('<input type="text" style="width: 100%; height: ' + $(this).closest('tr').find('.docTitle').height() +'px;" id="editDocTitle" value="' + $(this).data('doctitle') + '">');
            $(this).closest('td').find('.doc-action').show();
            $(this).closest('td').find('.doc-delete').hide();
            $(this).hide();
        });
        //End Show Edit mode
        
        //Edit Doc
        $wrapper.on('click', '.doc-done', function(e){
            e.preventDefault();
            var row = $(this).closest('tr');
            var docTitle = row.find('#editDocTitle').val();
            var docId = $(this).closest('td').find('.doc-edit').data('docid');

            if(docTitle != ""){
                $.ajax({
                    url: settings.serviceURL,
                    dataType: 'JSON',
                    type: 'post',
                    data: {
                            cmd: 'updatedoctitle',
                            docId: docId,
                            docTitle: docTitle
                    },
                    success: function( data ){
                        if(data.idDoc > 0){
                            //$table.ajax.reload();
                            var rowdata = $table.row(row).data();
                            rowdata["Title"] = docTitle;
							$table.row(row).data(rowdata);
                        }else{
                            if(data.error){
                                settings.alertMessage(data.error, 'error');
                            }else{
                                settings.alertMessage('An unknown error occurred.', 'alert');
                            }
                        }
                    }
                });
            }
        });
        //End Edit Doc
        
        //Cancel Doc
        $wrapper.on('click', '.doc-cancel', function(e){
            e.preventDefault();
            var docTitle = $(this).closest('tr').find('#editDocTitle').val();
            $(this).closest('tr').find('.docTitle').html(docTitle);
            $(this).closest('td').find('.doc-action').hide();
            $(this).closest('td').find('.doc-edit').show();
            $(this).closest('td').find('.doc-delete').show();

        });
        //End Cancel Doc
        
        //Delete Doc
        $wrapper.on('click', '.doc-delete', function(e){
            var docId = $(this).data("docid");
            var row = $(this).closest('tr');
            e.preventDefault();
            $.ajax({
                    url: settings.serviceURL,
                    dataType: 'JSON',
                    type: 'post',
                    data: {
                        cmd: 'deletedoc',
                        docId: docId
                    },
                    success: function( data ){
                        if(data.idDoc > 0){
                            row.find("td:not(:first)").css("opacity", "0.3");
                            var docTitle = row.find('#editDocTitle').val();
                                    row.find('.docTitle').html(docTitle);
                                    row.find('.doc-action').hide();
                                    row.find('.doc-delete').hide();
                                    row.find('.doc-edit').hide();
                                    row.find('.doc-undodelete').show();
                            $("#docTitle").val("");
                            $('#hhk-newNote').removeAttr("disabled").text(settings.newLabel);
                        }else{
                            settings.alertMessage(data.error, 'error');
                        }
                    }
                });

        });
        //End Delete Doc
        
        //Undo Delete Doc
        $wrapper.on('click', '.doc-undodelete', function(e){
            var docId = $(this).data("docid");
			var row = $(this).parents("tr");
			
            e.preventDefault();
            $.ajax({
                url: settings.serviceURL,
                dataType: 'JSON',
                type: 'post',
                data: {
                    cmd: 'undodeletedoc',
                    docId: docId
                },
                success: function( data ){
                    if(data.idDoc > 0){
                        //$table.ajax.reload();
                        var rowdata = $table.row(row).data();
                        $table.row(row).data(rowdata);
                        row.find("td").css("opacity", "1");
                        $("#docTitle").val("");
                        $('#hhk-newNote').removeAttr("disabled").text(settings.newLabel);
                    }else{
                        settings.alertMessage(data.error, 'error');
                    }
                }
            });

        });
        //End Undo Delete Note
    }

    function createViewer($wrapper, settings) {

        if (settings.guestId > 0 || settings.psgId > 0 || settings.rid > 0) {
	        //add new doc btn
            $wrapper.append($wrapper.uploader);
            
            var $table = $('<table />').attr(settings.tableAttrs).appendTo($wrapper);

            var dtTable = $table
                    .DataTable({
                        "columnDefs": settings.dtCols,
                        "serverSide": true,
                        "processing": true,
                        "deferRender": true,
                        "language": {"sSearch": "Search Docs:"},
                        "sorting": [[1, 'desc']],
                        "paging": false,
                        "lengthMenu": [[5, 10, 25, -1], [5, 10, 25, "All"]],
                        "dom": '<"dtTop"if>rt<"dtBottom"lp><"clear">',
                        ajax: {
                            url: settings.serviceURL,
                            data: {
                                'cmd': 'getDocumentList',
                                'psgId': settings.psgId,
                            },
                        },
						"drawCallback": function(settings){
							$wrapper.find('.hhk-doc-button').button();
		        		},
                    });

            actions($wrapper, settings, dtTable);

            //add ignrSave class to Dt controls
            $(".dataTables_filter").addClass('ignrSave');
            $(".dtBottom").addClass('ignrSave');
            
            var filename = '';
            var docTitle = '';
            
            DocUppload = new Upploader.Uppload({
            	maxSize: [1500, 1500],
            	call: ["#docUploadBtn"],
            	lang: Upploader.en,
		        uploader: function uploadFunction(file){
			        
			        //set title if none specified
			        if(docTitle == "" || docTitle == undefined){
			        	docTitle = file.name.substr(0, file.name.lastIndexOf('.')) || file.name;
			        }
			        
		            return new Promise(function (resolve, reject) {
		                var formData = new FormData();
		                formData.append('cmd', 'putdoc');
		                formData.append('guestId', settings.guestId);
		                formData.append('psgId', settings.psgId);
		                formData.append('docTitle', docTitle);
		                formData.append("mimetype", file.type);
		                formData.append('file', file);
						
						$.ajax({
			                url: settings.serviceURL,
			                dataType: 'JSON',
			                type: 'post',
			                data: formData,
			                contentType: false,
							processData: false,
			                success: function (data) {
			                    if (data.idDoc > 0) {
			                        dtTable.ajax.reload();
			                        clearform($wrapper);
			                        resolve("success");
			                    } else {
			                        if (data.error) {
			                            reject(data.error);
			                            new Noty({
			                            	type: "error",
			                            	text: "Error: " + data.error
			                            }).show();
			                        } else {
			                            reject('An unknown error occurred.');
			                            new Noty({
			                            	type: "error",
			                            	text: "Error: " + data.error
			                            }).show();
			                        }
			                    }
			                },
			            });
		            });
		        },

            });
            
            //include docTitle and helptext
            DocUppload.on("open", function(){
				docTitle = '';
            	//add docTitle
            	$(DocUppload.container).find(".uppload-service--local").prepend("<input type='text' placeholder='Enter Document Title' class='input' id='docTitle'>");
            	//add helptext
            	$(DocUppload.container).find(".drop-area").append('<p>Allowed filetypes: pdf, doc, docx, jpeg, png<br>Maximum File Size: 5MB</p>');
            });
            
            //get docTitle value
            $(document).on("change", "input#docTitle", function(){
            	docTitle = $("input#docTitle").val();
            });
            
            
            local = new Upploader.Local(
            {
            	maxFileSize: 5000000,
            	mimeTypes: ["application/pdf", "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document", "image/jpeg", "image/png"]
            });
            
            DocUppload.use([local, new Upploader.Crop({hideAspectRatioSettings: true})]);
            
        }
    }

}(jQuery));