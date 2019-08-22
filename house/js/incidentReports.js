(function ($) {

    $.fn.incidentViewer = function (options) {

		var incidentdialog = $('<div id="incidentDialog" class="hhk-tdbox hhk-visitdialog" title="Incident">' +
		'<form>' +
		'<input type="hidden" name="reportId">' +
			'<table>' +
			'<tr>' +
				'<td class="tdlabel">Incident Title</td>' +
				'<td>' +
					'<input type="text" name="incidentTitle">' +
				'</td>' +
			'</tr>' +
			'<tr>' +
				'<td class="tdlabel">Incident Date</td>' +
				'<td>' +
					'<input type="text" name="incidentDate" class="incdate" readonly="readonly">' +
				'</td>' +
			'</tr>' +
			'<tr>' +
				'<td class="tdlabel">Incident Description</td>' +
				'<td>' +
					'<textarea name="incidentDescription" rows="5"></textarea>' +
				'</td>' +
			'</tr>' +
			'<tr>' +
				'<td class="tdlabel">Incident Status</td>' +
				'<td>' +
					'<select name="incidentStatus">' +
						'<option value="a">Active</option>' +
						'<option value="r">Resolved</option>' +
						'<option value="h">On Hold</option>' +
						'<option value="d">Delete</option>' +
					'</select>' +
				'</td>' +
			'</tr>' +
			'<tr>' +
				'<td class="tdlabel">Incident Resolution</td>' +
				'<td>' +
					'<textarea name="incidentResolution" rows="5"></textarea>' +
				'</td>' +
			'</tr>' +
			'<tr>' +
				'<td class="tdlabel" style="width: 25%">Resolution Date</td>' +
				'<td>' +
					'<input type="text" name="resolutionDate" class="resdate" readonly="readonly">' +
				'</td>' +
			'</tr>' +
			'<tr style="display: none;">' +
				'<td class="tdlabel" style="width: 25%">Signature<br><div style="color: #959595; display: block;">Use your mouse, finger or touch pen to sign</div><span></td>' +
				'<td>' +
					'<div class="signature-actions" style="text-align: right;">' +
						'<div style="display: inline-block" class="hhk-clear-signature-btn hhk-report-button incident-edit ui-corner-all ui-state-default">Clear Signature</span>' +
					'</div>' +
					'<div style="height: 141px;" class="jsignature"></div>' +
				'</td>' +
			'</table>' +
		'</form>' +
		'</div>');


        var defaults = {
            guestId: 0,
            psgId: 0,
            rid: 0,
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
                        title: "Guest",
                        searchable: true,
                        sortable: true,
                        data: "Guest"
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
	
	function clearform($wrapper){
		$wrapper.incidentdialog.find("input").val("");
		$wrapper.incidentdialog.find("textarea").empty();
		$wrapper.incidentdialog.find("textarea").val("");
		$wrapper.incidentdialog.find("option").removeAttr("selected");
		$wrapper.incidentdialog.find(".jsignature").empty();
		$wrapper.incidentdialog.find(".jsignature").jSignature({"width":"563px", "height": "141px"});
		$wrapper.incidentdialog.find(".hhk-clear-signature-btn").button();
		$wrapper.incidentdialog.on("click", ".hhk-clear-signature-btn", function(){
			$wrapper.incidentdialog.find(".jsignature").jSignature("clear");
		});
		$wrapper.incidentdialog.find(".incdate").datepicker({autoSize: true, dateFormat: 'M d, yy'}).datepicker("setDate", "today");
		$wrapper.incidentdialog.find(".resdate").datepicker({autoSize: true, dateFormat: 'M d, yy'});
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
    
    function saveIncident($wrapper, settings, $table){
	    var repID = $wrapper.incidentdialog.find("input[name=reportId]").val();
	    var data = $wrapper.incidentdialog.find("form").serialize();
	    if(repID > 0){
		    data += "&cmd=editIncident&repId=" + repID;
	    }else{
		    data += "&cmd=saveIncident&guestId=" + settings.guestId + "&psgId=" + settings.psgId;
	    }
		
		console.log(data);
        $.ajax({
            url: settings.serviceURL,
            dataType: 'JSON',
            type: 'post',
            data: data,
            success: function( data ){
                    if(data.idReport > 0){
                        $table.ajax.reload();
                        $wrapper.incidentdialog.dialog( "close" );
                        clearform($wrapper);
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
            clearform($wrapper);
            $wrapper.incidentdialog.dialog("open");			
        });
        
        //Show Edit mode
        $wrapper.on('click', '.incident-edit', function(e){
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
                success: function( data ){
                        if(data.title){
                            $wrapper.incidentdialog.find("input[name=reportId]").val(repID);
                            $wrapper.incidentdialog.find("input[name=incidentTitle]").val(data.title);
							$wrapper.incidentdialog.find("input[name=incidentDate]").val(data.reportDate);
							$wrapper.incidentdialog.find("textarea[name=incidentDescription]").val(data.description);
							$wrapper.incidentdialog.find("option[value=" + data.status + "]").attr("selected", "selected");
							$wrapper.incidentdialog.find("textarea[name=incidentResolution]").val(data.resolution);
							$wrapper.incidentdialog.find("input[name=resolutionDate]").val(data.resolutionDate);
                            $wrapper.incidentdialog.dialog("open");
				            
                        }else{
                            
                        }
                }
            });
            
            
			
        });
        //End Show Edit mode
        
        //Delete Report
        $wrapper.on('click', '.incident-delete', function(e){
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
                    success: function( data ){
                        if(data.idReport > 0){
                            row.find("td:not(:first)").css("opacity", "0.3");
                            row.find('.incident-action').hide();
                            row.find('.incident-delete').hide();
                            row.find('.incident-edit').hide();
                            row.find('.incident-undodelete').show();
                        }else{
                            settings.alertMessage.call(data.error, 'error');
                        }
                    }
                });

        });
        //End Delete Report
        
        //Undo Delete Report
        $wrapper.on('click', '.incident-undodelete', function(e){
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
                    success: function( data ){
                        if(data.idReport > 0){
                            $table.ajax.reload();
                        }else{
                            settings.alertMessage.call(data.error, 'error');
                        }
                    }
                });

        });
        //End Undo Delete Report
    }
    
    function Print($wrapper, settings)
	{
		var repID = $wrapper.incidentdialog.find("input[name=reportId]").val();

		$.ajax({
        url: settings.serviceURL,
        dataType: 'JSON',
        type: 'post',
        data: {
                cmd: 'getincidentreport',
                repid: repID,
        },
        success: function( data ){
                if(data.title){
	                console.log(data);
	                var status = "";
	                if(data.status == "a"){
		                status = "Active";
	                }else if(data.status == "h"){
		                status = "On Hold";
	                }else if(data.status == "r"){
		                status = "Resolved";
	                }
                    var body = '<div id="incidentPrint">' +
							'<table cellpadding="10">' +
							'<tr>' +
								'<td class="tdlabel">Title</td>' +
								'<td>' +
									data.title +
								'</td>' +
							'</tr>' +
							'<tr>' +
								'<td class="tdlabel">Date</td>' +
								'<td>' +
									data.reportDate +
								'</td>' +
							'</tr>' +
							'<tr>' +
								'<td class="tdlabel">Description</td>' +
								'<td>' +
									data.description +
								'</td>' +
							'</tr>' +
							'<tr>' +
								'<td class="tdlabel">Status</td>' +
								'<td>' +
									status +
								'</td>' +
							'</tr>' +
							'<tr>' +
								'<td class="tdlabel">Resolution</td>' +
								'<td>' +
									data.resolution +
								'</td>' +
							'</tr>' +
							'<tr>' +
								'<td class="tdlabel" style="width: 25%;">Resolution Date</td>' +
								'<td>' +
									data.resolutionDate +
								'</td>' +
							'</tr>' +
							'<tr>' +
								'<td class="tdlabel" style="width: 25%; height: 50px;">Signature</td>' +
								'<td>' +
									
								'</td>' +
							'</table>' +
						'</div>';
		            var mywindow = window.open('', 'PRINT', 'height=600,width=800');
				    mywindow.document.write('<html><head><title>' + document.title  + '</title>');
				    mywindow.document.write('<link href="css/incidentReports.css" rel="stylesheet" type="text/css">');
				    mywindow.document.write('</head><body >');
				    mywindow.document.write('<h2>' + document.title  + ' - Incident Report</h>');
				    mywindow.document.write(body);
				    mywindow.document.write('</body></html>');
				
				    mywindow.document.close(); // necessary for IE >= 10
				    mywindow.focus(); // necessary for IE >= 10*/
				
				    mywindow.print();
				    mywindow.close();
		            
                }else{
                    
                }
        }
    });
	    
	
	    return true;
	}

    function createViewer($wrapper, settings) {
        
        if (settings.guestId > 0 || settings.psgId > 0 || settings.rid > 0) {
	        var newBtn = $('<button class="ui-button ui-corner-all ui-state-default" id="incident-create"><span class="ui-icon ui-icon-plus"></span>New Incident</button>').appendTo($wrapper);
            var $table = $('<table />').attr(settings.tableAttrs).appendTo($wrapper);

            var dtTable = $table
            .on( 'draw.dt', function (e, settings) {
	            var api = new $.fn.dataTable.Api( settings );
	            var result = api.rows().data();
                var active = 0;
                $.each(result, function( index, value ) {
	                if(value.Status == "Active"){
		                active++;
	                }
	            })
	            $("#incidentCounts").text(" - " + active + " active");
		    } )
            .DataTable({
	        "columnDefs": settings.dtCols,
	        "serverSide": true,
	        "processing": true,
	        "deferRender": true,
	        "language": {"sSearch": "Search Incidents:"},
	        "sorting": [[5,'asc']],
	        "displayLength": 5,
	        "lengthMenu": [[5, 10, 25, -1], [5, 10, 25, "All"]],
                "dom": '<"dtTop"if>rt<"dtBottom"lp><"clear">',
	        ajax: {
	            url: settings.serviceURL,
                data: {
                    'cmd': 'getIncidentList',
                    'guestId': settings.guestId,
                    'psgId': settings.psgId,
                    'rid': settings.rid
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
					Print: function() {
						Print($wrapper, settings);
        			},
					Cancel: function() {
						$wrapper.incidentdialog.dialog( "close" );
						clearform($wrapper);
        			},
        			"Save": function(){
						saveIncident($wrapper, settings, dtTable);
					}
      			},
			});
        }
        
        $wrapper.show();
    }

}(jQuery));