
$(document).ready(function () {
    var tabIndex = $('#tabIndex').val();
	var servFile = $('#wsServFile').val();
    var tbs;
    var authTabs;
    var logTable = [];
    var cronLoaded = false;
    var cronLogLoaded = false;
    var cronTable;
    var cronLogTable;
    var dateFormat = $('#dateFormat').val();
    var notyMsg = $('#notymsg').val();
    notyMsg = JSON.parse(notyMsg);

    var dtCols = [
    {
        "targets": [ 0 ],
        "title": "Type",
        "searchable": false,
        "sortable": false,
        "data": "Log_Type"
    },
    {
        "targets": [ 1 ],
        "title": "Sub-Type",
        "searchable": false,
        "sortable": true,
        "data": "Sub_Type"
    },
     {
         "targets": [ 2 ],
        "title": "User",
        "searchable": true,
        "sortable": true,
        "data": "User_Name"
    },
    {
        "targets": [ 3 ],
        "title": "Id",
        "searchable": true,
        "sortable": true,
        "data": "Id1"
    },
     {
         "targets": [ 4 ],
        "title": "Item",
        "searchable": true,
        "sortable": false,
        "data": "Str1"
    },
    {
        "targets": [ 5 ],
        "title": "Detail",
        "searchable": false,
        "sortable": false,
        "data": "Str2"
    },
    {
        "targets": [ 6 ],
        "title": "Log Text",
        "sortable": false,
        "data": "Log_Text"
    },
    {
        "targets": [ 7 ],
        "title": "Date",
        'data': 'Ts',
        render: function ( data, type ) {
            return dateRender(data, type, dateFormat);
        }
    }
];

	//display noty

	if(notyMsg.type){
		new Noty({
			type : notyMsg.type,
			text : notyMsg.text
		}).show();
	}

    tbs = $('#tabs').tabs({

        // activate the first log tab, Sys Config Log.
        beforeActivate: function (event, ui) {
            var pid = 'liss';

            if (ui.newTab.prop('id') === 'liService') {

				$.post('ws_gen.php', {'cmd':'shoConfNeon', 'servFile': servFile}, function (data) {
					$('#serviceContent').empty();
					$('#serviceContent').prepend(data);
				});
            }

            if (ui.newTab.prop('id') === 'liLogs' && !logTable[pid]) {
                logTable[pid] = 1;

                $('#table'+pid).dataTable({
                    "columnDefs": dtCols,
                    "serverSide": true,
                    "processing": true,
                    //"deferRender": true,
                    "language": {"sSearch": "Search Log:"},
                    "sorting": [[7,'desc']],
                    "displayLength": 25,
                    "lengthMenu": [[25, 50, 100], [25, 50, 100]],
                    "dom": '<"dtTop"if>rt<"dtBottom"lp><"clear">',
                    ajax: {
                        url: 'ws_gen.php',
                        data: {
                            'cmd': 'showLog',
                            'logId': pid
                        }
                    }
                });
            }
            
            if (ui.newTab.prop('id') === 'liCron'){
            	if(!cronLoaded){
	            	cronLoaded = true;
	            	cronTable = $('table#cronJobs').DataTable({
						"columnDefs": dtCronCols,
						"serverSide": true,
						"processing": true,
						"language": {"sSearch": "Search Jobs:"},
						"sorting": [[1,'desc']],
						"displayLength": 25,
						"lengthMenu": [[25, 50, 100], [25, 50, 100]],
						"dom": '<"dtTop"if>rt<"dtBottom"lp><"clear">',
						ajax: {
						    url: 'ws_gen.php',
							data: {
							    'cmd': 'showCron',
							}
						}
					});
					
					cronActions($('table#cronJobs'), cronTable);
					
				}else{
					cronTable.ajax.reload();
				}
            }
        }
    });

//cron tab
$("#cronTabs").tabs({
	beforeActivate: function (event, ui) {
        if (ui.newTab.prop('id') === 'liCronLog') {
        	if(!cronLogLoaded){
        		cronLogLoaded = true;
        		cronLogTable = $('table#cronLog').DataTable({
					"columnDefs": dtCronLogCols,
				    "serverSide": true,
				    "processing": true,
				    "language": {"sSearch": "Search Jobs:"},
				    "sorting": [[4,'desc']],
				    "displayLength": 25,
				    "lengthMenu": [[25, 50, 100], [25, 50, 100]],
				    "dom": '<"dtTop"if>rt<"dtBottom"lp><"clear">',
				    ajax: {
				        url: 'ws_gen.php',
				        data: {
				            'cmd': 'showCronLog',
				        }
				    }
				});
        	}else{
        		cronLogTable.ajax.reload();
        	}
        }
    }
});

var dtCronCols = [
    {
        "targets": [ 0 ],
        "title": "ID",
        "searchable": false,
        "sortable": false,
        "data": "ID",
        "width": 10
    },
    {
        "targets": [ 1 ],
        "title": "Title",
        "searchable": false,
        "sortable": true,
        "data": "Title",
        "width": 200,
        "className":"jobTitle"
    },
     {
         "targets": [ 2 ],
        "title": "Interval",
        "searchable": true,
        "sortable": true,
        "data": "Interval",
        render: function ( data, type ) {
        	return data.charAt(0).toUpperCase() + data.slice(1)
        },
        "width":50,
        "className":"jobInterval"
    },
    {
        "targets": [ 3 ],
        "title": "Day",
        "searchable": false,
        "sortable": true,
        "data": "Day",
        "width":50,
        "className":"jobDay"
    },
    {
        "targets": [ 4 ],
        "title": "Hour",
        "searchable": false,
        "sortable": true,
        "data": "Hour",
        "width":50,
        "className":"jobHour"
    },
    {
        "targets": [ 5 ],
        "title": "Minute",
        "searchable": false,
        "sortable": true,
        "data": "Minute",
        "width":50,
        "className":"jobMinute"
    },
     {
         "targets": [ 6 ],
        "title": "Status",
        "searchable": true,
        "sortable": true,
        "data": "Status",
        render: function ( data, type ) {
            switch (data){
            	case 'a':
            		return "Active";
            	break;
            	case 'd':
            		return "Disabled";
            	break;
            	default:
            		return "";
            };
        },
        "width":50,
        "className":"jobStatus"
    },
    {
        "targets": [ 7 ],
        "title": "Last Run",
        'data': 'Last Run',
        render: function ( data, type ) {
            return dateRender(data, type, dateFormat);
        },
        "width":150
    },
    {
        "targets": [ 8 ],
        "title": "Actions",
        'data': 'ID',
        render: function ( data, type, row) {
            return '<div class="cronActions">'
            		+ ($('#canEditCron').val() == true ? '<button type="button" class="editCron ui-button ui-corner-all" data-job="' + data + '" data-jobtitle="' + row.Title + '" data-interval="' + row.Interval + '" data-day="' + row.Day + '" data-hour="' + row.Hour + '" data-minute="' + row.Minute + '" data-status="' + row.Status + '">Edit</button>' : '')
            		+ '<button type="button" class="runCron ui-button ui-corner-all" data-job="' + data + '" data-dryRun="1">Dry Run</button>'
            		+ ($('#canForceRunCron').val() == true ? '<button type="button" class="runCron ui-button ui-corner-all" data-job="' + data + '" data-dryRun="0">Run Now</button>': '')
            		+ '<button type="button" class="saveCron ui-button ui-corner-all" data-job="' + data + '" style="display:none;">Save</button>'
            		+ '<button type="button" class="cancelCron ui-button ui-corner-all" data-job="' + data + '" data-jobtitle="' + row.Title + '" data-interval="' + row.Interval + '" data-day="' + row.Day + '" data-hour="' + row.Hour + '" data-minute="' + row.Minute + '" data-status="' + row.Status + '" style="display:none;">Cancel</button>'
            	+ '</div>';
        },
    }
];



$('table#cronJobs').on('click', '.runCron', function(event){
	var job = $(event.target).data('job');
	var dryRun = $(event.target).data('dryrun');
	$.ajax({
		url: 'ws_gen.php',
		method: 'post',
		data: {
			'cmd':'forceRunCron',
			'dryRun':dryRun,
			'idJob':job,
		},
		dataType:'json',
		success: function(data){
			if(data.status && data.status == 's'){
				flagAlertMessage(data.logMsg, false);
			}else if(data.status && data.status == 'f'){
				flagAlertMessage(data.logMsg, true);
			}
			cronTable.ajax.reload();
			cronLogTable.ajax.reload();
		},
		error: function (xhr, textStatus, errorThrown){
			flagAlertMessage("Cron error: " + errorThrown, true);
		}
	});
});

	

	function cronActions($wrapper, cronTable) {
        
        //Show Edit mode
        $wrapper.on('click', '.editCron', function(e){
            e.preventDefault();
            var $editBtn = $(this);
            var jobIntervalMkup = '';
            var jobIntervals = new Array("hourly","daily","monthly");
            var jobStatuses = {"a":"Active", "d":"Disabled"};
            
            jobIntervalMkup += '<select id="editJobInterval">';
            $.each(jobIntervals, function(k,interval){
            	if($editBtn.data('interval') == interval){
            		jobIntervalMkup += '<option value="' + interval + '" selected="selected">' + interval.charAt(0).toUpperCase() + interval.slice(1) + '</option>';
            	}else{
            		jobIntervalMkup += '<option value="' + interval + '">' + interval.charAt(0).toUpperCase() + interval.slice(1) + '</option>';
            	}
            });
            jobIntervalMkup += '</select>';
            
            var jobDay = $editBtn.data("day");
            var jobHour = $editBtn.data("hour");
            var jobMinute = $editBtn.data("minute");
            
            var jobDayMkup ='<select id="editJobDay"><option disabled="disabled">Day</option>';
        			for (var d = 1; d < 32; d++){
        				d = d.toLocaleString('en-US', {
      						minimumIntegerDigits: 2,
      						useGrouping: false
    					})
        				if(jobDay == d){
        					jobDayMkup += '<option value="' + d + '" selected="selected">' + d +'</option>';
        				}else{
        					jobDayMkup += '<option value="' + d + '">' + d +'</option>';
        				}
        			}
        		jobDayMkup += '</select>';
            	
            	var jobHourMkup ='<select id="editJobHour"><option disabled="disabled">Hour</option>';
        			for (var h = 0; h < 24; h++){
        				h = h.toLocaleString('en-US', {
      						minimumIntegerDigits: 2,
      						useGrouping: false
    					})
        				if(jobHour == h){
        					jobHourMkup += '<option value="' + h + '" selected="selected">' + h +'</option>';
        				}else{
        					jobHourMkup += '<option value="' + h + '">' + h +'</option>';
        				}
        			}
        		jobHourMkup += '</select>';
        		
        		var jobMinuteMkup = '<select id="editJobMinute"><option disabled="disabled">Minute</option>';
        			for (var m = 0; m < 60; m++){
        				m = m.toLocaleString('en-US', {
      						minimumIntegerDigits: 2,
      						useGrouping: false
    					})
        				if(jobMinute == m){
        					jobMinuteMkup += '<option value="' + m + '" selected="selected">' + m +'</option>';
        				}else{
        					jobMinuteMkup += '<option value="' + m + '">' + m +'</option>';
        				}
        			}
        		jobMinuteMkup += '</select>';
        		
        		var jobStatusMkup = '';
        		var jobStatus = $editBtn.data('status');
            	jobStatusMkup += '<select id="editJobStatus">';
        			$.each(jobStatuses, function(k,v){
        				if(jobStatus == k){
        					jobStatusMkup += '<option value="' + k + '" selected="selected">' + v +'</option>';
        				}else{
        					jobStatusMkup += '<option value="' + k + '">' + v +'</option>';
        				}
        			});
        		jobStatusMkup += '</select>';
        		
            $(this).closest('tr').find('.jobInterval').html(jobIntervalMkup);
        	$(this).closest('tr').find('.jobDay').html(jobDayMkup);
        	$(this).closest('tr').find('.jobHour').html(jobHourMkup);
        	$(this).closest('tr').find('.jobMinute').html(jobMinuteMkup);
        	$(this).closest('tr').find('.jobStatus').html(jobStatusMkup);
        	$(this).closest('tr').find('.runCron, .editCron').hide();
        	$(this).closest('tr').find('.saveCron, .cancelCron').show();
        	
        	$(this).closest('tr').on('change', '#editJobInterval', function(e){
        		var interval = $(e.target).val();
        		
        		switch(interval){
        			case "hourly":
        				$(this).closest('tr').find('#editJobDay, #editJobHour').hide();
        				break;
        			case "daily":
        				$(this).closest('tr').find('#editJobDay').hide();
        				$(this).closest('tr').find('#editJobHour').show();
        				break;
        			case "monthly":
        				$(this).closest('tr').find('#editJobDay, #editJobHour').show();
        				break;
        		}
        		
        	});
        	$(this).closest('tr').find('#editJobInterval').trigger('change');
        });
        //End Show Edit mode
        
        //Edit Job
        $wrapper.on('click', '.saveCron', function(e){
            e.preventDefault();
            var row = $(this).closest("tr");
            var jobId = $(this).data('job');
            var interval = row.find('#editJobInterval').val();
            var day = row.find("#editJobDay").val();
            var hour = row.find("#editJobHour").val();
            var minute = row.find("#editJobMinute").val();
            var status = row.find("#editJobStatus").val();

            if(jobId != ""){
                $.ajax({
                    url: 'ws_gen.php',
                    dataType: 'JSON',
                    type: 'post',
                    data: {
                            cmd: 'updateCronJob',
                            idJob: jobId,
                            interval: interval,
                            day: day,
                            hour: hour,
                            minute: minute,
                            status: status,
                            
                    },
                    success: function( data ){
                            if(data.job && data.job.idJob > 0){
                                var rowdata = cronTable.row(row).data();
                                rowdata["Interval"] = data.job.Interval;
                                rowdata["Day"] = data.job.Day;
                                rowdata["Hour"] = data.job.Hour;
                                rowdata["Minute"] = data.job.Minute;
                                rowdata["Status"] = data.job.Status;
                                
								cronTable.row(row).data(rowdata);
                            }else{
                                if(data.error){
                                    flagAlertMessage(data.error, true);
                                }else{
                                    flagAlertMessage('An unknown error occurred.', true);
                                }
                            }
                    }
                });
            }
        });
        //End Edit Job
        
        //Cancel Edit Job
        $wrapper.on('click', '.cancelCron', function(e){
            e.preventDefault();
            var data = cronTable.row($(this).parents('tr')).data();
            cronTable.row($(this).parents('tr')).data(data);

        });
        //End Cancel Edit Job
        
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
                        }else{
                            settings.alertMessage.call(data.error, 'error');
                        }
                    }
                });

        });
        //End Undo Delete Note
    }

var dtCronLogCols = [
    {
        "targets": [ 0 ],
        "title": "Job ID",
        "searchable": false,
        "sortable": false,
        "data": "Job ID",
    },
    {
        "targets": [ 1 ],
        "title": "Job",
        "searchable": false,
        "sortable": true,
        "data": "Job",
    },
     {
         "targets": [ 2 ],
        "title": "Log Text",
        "searchable": true,
        "sortable": true,
        "data": "Log Text",
    },
     {
         "targets": [ 3 ],
        "title": "Status",
        "searchable": true,
        "sortable": true,
        "data": "Status",
        render: function ( data, type ) {
            switch (data){
            	case 's':
            		return "Success";
            	break;
            	case 'f':
            		return "Fail";
            	break;
            	default:
            		return "";
            };
        }
    },
    {
        "targets": [ 4 ],
        "title": "Run Time",
        'data': 'Run Time',
        render: function ( data, type ) {
            return dateRender(data, type, dateFormat);
        }
    }
];

    $('#logsTabDiv').tabs({

        beforeActivate: function (event, ui) {

            var pid = ui.newTab.prop('id');
            if (!logTable[pid]) {
                logTable[pid] = 1;

                $('#table'+pid).dataTable({
                    "columnDefs": dtCols,
                    "serverSide": true,
                    "processing": true,
                    //"deferRender": true,
                    "language": {"sSearch": "Search Log:"},
                    "sorting": [[7,'desc']],
                    "displayLength": 25,
                    "lengthMenu": [[25, 50, 100], [25, 50, 100]],
                    "dom": '<"dtTop"if>rt<"dtBottom"lp><"clear">',
                    ajax: {
                        url: 'ws_gen.php',
                        data: {
                            'cmd': 'showLog',
                            'logId': pid
                        }
                    }
                });
            }
        }
    });

$('#logsTabDiv').tabs("option", "active", 1);

$("input[type=submit], input[type=reset]").button();
$('#financialRoomSubsidyId, #financialReturnPayorId').change(function () {

        $('#financialRoomSubsidyId, #financialReturnPayorId').removeClass('ui-state-error');

        if ($('#financialRoomSubsidyId').val() != 0 && $('#financialRoomSubsidyId').val() === $('#financialReturnPayorId').val()) {
            $('#financialRoomSubsidyId, #financialReturnPayorId').addClass('ui-state-error');
            alert('Subsidy Id must be different than the Return Payor Id');
        }
});

tbs.tabs("option", "active", tabIndex);
$('#tabs').show();

	//authentication tab
	authTabs = $('#authTabs').tabs();
	authTabs.tabs("option", "active", 0);
    $('#authTabs').show();
    
    $(document).on("submit", "form.authForm", function(e){
    	e.preventDefault();
    	$this = $(this);
    	$submitbtn = $(this).find("input[type=submit]")
    	$submitbtn.prop("disabled", true);
    	data = new FormData($(this)[0]);
    	data.append('saveIdP','true');
    	
    	$.ajax({
        	type: 'POST',
            enctype: 'multipart/form-data',
            url: 'Configure.php',
            data: data,
            processData: false,
            contentType: false,
            dataType: 'json',
            cache: false,
            timeout: 800000,
            success: function (data) {
            	if(data.success){
	            	new Noty({
						type : 'success',
						text : data.success
					}).show();
				}
				if(data.error){
	            	new Noty({
						type : 'error',
						text : data.error
					}).show();
				}
				if(data.idpMkup && data.idpName){
					$this.empty().html(data.idpMkup);
					$("#authTabs ul li.ui-tabs-active a").text(data.idpName);
				}
				
				$submitbtn.prop("disabled", false);
            },
            error: function (e) {
            	new Noty({
					type : 'error',
					text : e.responseText
				}).show();
                $submitbtn.prop("disabled", false);
            }
        });
    });
});