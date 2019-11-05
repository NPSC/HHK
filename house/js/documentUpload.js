(function ($) {

    $.fn.docUploader = function (options) {

		var uploader = '<div class="ui-widget ui-widget-content ui-corner-all hhk-tdbox  hhk-member-detail hhk-visitdialog" style="margin-top: 0.5em; margin-bottom: 0.5em; width: 100%;">' +
			'<form class="hhk-panel">' +
				'<table style="width: 100%">' +
					'<tr>' +
						'<th style="min-width: 120px;">New Document</th>' +
						'<td>' +
							'<input type="text" name="title" placeholder="Title" style="padding: 0.5em" size="100">' +
						'</td>' +
						'<td style="min-width: 110px;">' +
							'<button id="docUploadBtn" class="ui-button ui-corner-all ui-widget" style="width: 100%">' +
								'<span class="ui-icon ui-icon-plusthick" style="margin-right: 0.5em"></span>Upload' +
							'</button>' +
						'</td>' +
					'</tr>' +
				'</table>' +
			'</form>' +
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
            newIncidentAttrs: {
                id: 'incidentTitle',
                style: 'width: 80%;',
                rows: 2
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
                    width: "70%",
                    className: 'incidentTitle',
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
            ]
        };

        var settings = $.extend(true, {}, defaults, options);

        var $wrapper = $(this);
        $wrapper.uploader = uploader;

        createViewer($wrapper, settings);

        return this;
    };

    function clearform($wrapper) {
        $wrapper.uploader.find("input").val("");
    }

    function createActions(reportId, row) {

        var $ul, $li;

        $ul = $('<ul />').addClass('ui-widget ui-helper-clearfix hhk-ui-icons');

        // Edit icon
        $li = $('<li title="Edit Incident" data-incidentid="' + reportId + '" />').addClass('hhk-report-button incident-edit ui-corner-all ui-state-default');
        $li.append($('<span class="ui-icon ui-icon-pencil" />'));

        $ul.append($li);

        // Delete Edit Icon
        $li = $('<li title="Delete report" data-reportid="' + reportId + '" />').addClass('hhk-report-button incident-delete ui-corner-all ui-state-default');
        $li.append($('<span class="ui-icon ui-icon-trash" />'));

        $ul.append($li);

        // Undo Delete Edit Icon
        $li = $('<li title="Undo Delete" data-reportid="' + reportId + '" />').addClass('hhk-report-button incident-undodelete ui-corner-all ui-state-default').hide();
        $li.append($('<span class="ui-icon ui-icon-arrowreturnthick-1-w" />'));

        $ul.append($li);

        return $('<div />').append($ul).html();

        //return $ul.html();
    }

    function saveDoc($wrapper, settings, $table) {
        //validate
        var error = false;

        if (error == true) {
            settings.alertMessage.call("Incident not saved. Check fields in red.", 'alert');
        } else {
            var repID = $wrapper.incidentdialog.find("input[name=reportId]").val();
            var data = $wrapper.incidentdialog.find("form").serialize();
            var signature = encodeURIComponent($wrapper.incidentdialog.find(".jsignature").jSignature("getData"));
            data += "&signature=" + signature;
            if (repID > 0) {
                data += "&cmd=editIncident&repId=" + repID;
            } else {
                data += "&cmd=saveIncident&guestId=" + settings.guestId + "&psgId=" + settings.psgId;
            }

            $.ajax({
                url: settings.serviceURL,
                dataType: 'JSON',
                type: 'post',
                data: data,
                success: function (data) {
                    if (data.idReport > 0) {
                        if (print) {
                            Print($wrapper, settings, data.idReport);
                        }
                        $table.ajax.reload();
                        $wrapper.incidentdialog.dialog("close");
                        clearform($wrapper);
                    } else {
                        if (data.error) {
                            settings.alertMessage.call(data.error, 'alert');
                        } else {
                            settings.alertMessage.call('An unknown error occurred.', 'alert');
                        }
                    }
                }
            });
    }
    }

    function actions($wrapper, settings, $table) {

        //Show new incident
        $wrapper.on('click', '#incident-create', function (e) {
            e.preventDefault();
            clearform($wrapper);
            $wrapper.incidentdialog.dialog("open");
        });

        //Show Edit mode
        $wrapper.on('click', '.incident-edit', function (e) {
            e.preventDefault();
            clearform($wrapper);
            var repID = $(e.target).parent().data('incidentid');
            $.ajax({
                url: settings.serviceURL,
                dataType: 'JSON',
                type: 'post',
                data: {
                    cmd: 'getincidentreport',
                    repid: repID,
                },
                success: function (data) {
                    if (data.title) {

                    } else {

                    }
                }
            });


        });
        //End Show Edit mode

        //Delete Report
        $wrapper.on('click', '.incident-delete', function (e) {
            var idReport = $(this).data("reportid");
            var row = $(this).closest('tr');
            e.preventDefault();
            $.ajax({
                url: settings.serviceURL,
                dataType: 'JSON',
                type: 'post',
                data: {
                    cmd: 'deleteIncident',
                    idReport: idReport
                },
                success: function (data) {
                    if (data.idReport > 0) {
                        row.find("td:not(:first)").css("opacity", "0.3");
                        row.find('.incident-action').hide();
                        row.find('.incident-delete').hide();
                        row.find('.incident-edit').hide();
                        row.find('.incident-undodelete').show();
                    } else {
                        settings.alertMessage.call(data.error, 'error');
                    }
                }
            });

        });
        //End Delete Report

        //Undo Delete Report
        $wrapper.on('click', '.incident-undodelete', function (e) {
            var idReport = $(this).data("reportid");

            e.preventDefault();
            $.ajax({
                url: settings.serviceURL,
                dataType: 'JSON',
                type: 'post',
                data: {
                    cmd: 'undoDeleteIncident',
                    idReport: idReport
                },
                success: function (data) {
                    if (data.idReport > 0) {
                        $table.ajax.reload();
                    } else {
                        settings.alertMessage.call(data.error, 'error');
                    }
                }
            });

        });
        //End Undo Delete Report
    }

    function createViewer($wrapper, settings) {

        if (settings.guestId > 0 || settings.psgId > 0 || settings.rid > 0) {
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
                                'guestId': settings.guestId,
                                'psgId': settings.psgId,
                            },
                        }

                    });

            actions($wrapper, settings, dtTable);

            //add ignrSave class to Dt controls
            $(".dataTables_filter").addClass('ignrSave');
            $(".dtBottom").addClass('ignrSave');

            //add incident dialog
            $wrapper.append($wrapper.uploader);
            
            new Uppload({
	            call: ["#docUploadBtn"],
		        uploadFunction: function uploadFunction(file){
		            return new Promise(function (resolve, reject) {
		                var formData = new FormData();
		                formData.append('cmd', 'putguestphoto');
		                formData.append('guestId', "1");
		                formData.append('guestPhoto', file);
						console.log(file);
						resolve("success");
		            });
		        },
		        services: [
		            "upload"
		        ],
		        defaultService: "upload",
		        allowedTypes: "application/pdf",
		    });
		    
		    $wrapper.on("click", "#docUploadBtn", function(e){
			    e.preventDefault();
		    })
    
        }
    }

}(jQuery));