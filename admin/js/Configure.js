
$(document).ready(function () {
    var tabIndex = $('#tabIndex').val();
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
            "targets": [0],
            "title": "Type",
            "searchable": false,
            "sortable": false,
            "data": "Log_Type"
        },
        {
            "targets": [1],
            "title": "Sub-Type",
            "searchable": false,
            "sortable": true,
            "data": "Sub_Type"
        },
        {
            "targets": [2],
            "title": "User",
            "searchable": true,
            "sortable": true,
            "data": "User_Name"
        },
        {
            "targets": [3],
            "title": "Id",
            "searchable": true,
            "sortable": true,
            "data": "Id1"
        },
        {
            "targets": [4],
            "title": "Item",
            "searchable": true,
            "sortable": false,
            "data": "Str1"
        },
        {
            "targets": [5],
            "title": "Detail",
            "searchable": false,
            "sortable": false,
            "data": "Str2"
        },
        {
            "targets": [6],
            "title": "Log Text",
            "sortable": false,
            "data": "Log_Text"
        },
        {
            "targets": [7],
            "title": "Date",
            'data': 'Timestamp',
            render: function (data, type) {
                return dateRender(data, type, dateFormat);
            }
        }
    ];

// CC processor tab
    if ($('.hhk-delMerchant').length > 0) {
        $('.hhk-delMerchant').on('change', function () {
            if ($(this).prop('checked') && !confirm('Delete the ' + $(this).data('merchant') + ' merchant?')) {
                $(this).prop('checked', false);
            }
        });
    }
    if ($('.hhk-setMerchantRooms').length > 0) {
        $('.hhk-setMerchantRooms').on('change', function () {
            if ($(this).prop('checked') && !confirm('This sets ALL the rooms to the ' + $(this).data('merchant') + ' merchant!')) {
                $(this).prop('checked', false);
            }
        });
    }

// save holidays

$(document).on("submit", "form#formHolidays", function(e){
    e.preventDefault();
    var postData = $(this).serializeArray();
    postData.push({name: "saveHolidays", value:true});

    $.ajax({
        url: 'Configure.php',
        method: 'post',
        data: postData,
        dataType: 'json',
        success: function (data) {
            if (data.success) {
                flagAlertMessage(data.success, false);
            } else if (data.error) {
                flagAlertMessage(data.error, true);
            }
        },
        error: function (xhr, textStatus, errorThrown) {
            flagAlertMessage("Error: " + errorThrown, true);
        }
    });

});

$(document).on('click', "#btnCalcAddresses", function(){
    var numCalls = $("#numAddrCalc").val();
    if(numCalls > 0){
        $.ajax({
            url: 'Configure.php',
            method: 'post',
            data: {
                'cmd': 'calcDistArray',
                'numCalls': numCalls
            },
            dataType: 'json',
            success: function (data) {
                if (data.success) {
                    flagAlertMessage(data.success, false);
                } else if (data.error) {
                    flagAlertMessage(data.error, true);
                }
            },
            error: function (xhr, textStatus, errorThrown) {
                flagAlertMessage("Error: " + errorThrown, true);
            }
        });
    }
});

$(document).on('click', "#btnLoadUncalcAddrTbl", function(){
        $.ajax({
            url: 'Configure.php',
            method: 'post',
            data: {
                'cmd': 'getUncalculatedAddressTbl'
            },
            dataType: 'text',
            success: function (data) {
                $("#uncalcAddrTbl").html(data);
            },
            error: function (xhr, textStatus, errorThrown) {
                flagAlertMessage("Error: " + errorThrown, true);
            }
        });
});

$(document).on('change', "#numAddrCalc", function(){
    var ApiCost = $("#googleApiCostPerCall").val();
    var numCalls = $("#numAddrCalc").val();
    console.log(ApiCost);
    console.log(numCalls);
    if(ApiCost > 0 && numCalls > 0){
        $("#googleApiCost").text("$" + ApiCost*numCalls);
    }
});


	//display noty

	if(notyMsg.type){
		flagAlertMessage(notyMsg.text, notyMsg.type);
	}


    tbs = $('#tabs').tabs({

        // activate the first log tab, Sys Config Log.
        beforeActivate: function (event, ui) {
            var pid = 'liss';

            // CMS export
            if (ui.newTab.prop('id') === 'liService') {

                $.post('ws_gen.php', {'cmd': 'shoCmConf'}, function (data) {
                    $('#serviceContent').empty();
                    $('#serviceContent').prepend(data);
                });
            }

            if (ui.newTab.prop('id') === 'liLogs' && !logTable[pid]) {
                logTable[pid] = 1;

                $('#table' + pid).dataTable({
                    "columnDefs": dtCols,
                    "serverSide": true,
                    "processing": true,
                    //"deferRender": true,
                    "language": {"sSearch": "Search Log:"},
                    "sorting": [[7, 'desc']],
                    "displayLength": 25,
                    "lengthMenu": [[25, 50, 100], [25, 50, 100]],
                    "dom": '<"top"lf><"hhk-overflow-x"rt><"bottom"ip>',
                    ajax: {
                        url: 'ws_gen.php',
                        data: {
                            'cmd': 'showLog',
                            'logId': pid
                        }
                    }
                });
            }

            if (ui.newTab.prop('id') === 'liCron') {
                if (!cronLoaded) {
                    $.ajax({
                        url: 'ws_gen.php',
                        async: false,
                        dataType: 'json',
                        data: {"cmd": "getCronLookups"},
                        success: function (data) {
                            cronLookups = data;
                        }
                    });

                    cronLoaded = true;
                    cronTable = $('table#cronJobs').DataTable({
                        "columnDefs": dtCronCols,
                        "serverSide": false,
                        "processing": true,
                        "language": {"sSearch": "Search Jobs:"},
                        "sorting": [[0, 'asc']],
                        "displayLength": 25,
                        "lengthMenu": [[25, 50, 100], [25, 50, 100]],
                        "dom": '<"top"lf><"hhk-overflow-x"rt><"bottom"ip>',
                        ajax: {
                            url: 'ws_gen.php',
                            data: {
                                'cmd': 'showCron',
                            }
                        }
                    });

                    cronActions($('table#cronJobs'), cronTable);

                } else {
                    cronTable.ajax.reload();
                }
            }
        }
    });

//cron tab
    $("#cronTabs").tabs({
        beforeActivate: function (event, ui) {
            if (ui.newTab.prop('id') === 'liCronLog') {
                if (!cronLogLoaded) {
                    cronLogLoaded = true;
                    cronLogTable = $('table#cronLog').DataTable({
                        "columnDefs": dtCronLogCols,
                        "serverSide": true,
                        "processing": true,
                        "language": {"sSearch": "Search Jobs:"},
                        "sorting": [[4, 'desc']],
                        "displayLength": 25,
                        "lengthMenu": [[25, 50, 100], [25, 50, 100]],
                        "dom": '<"top"lf><"hhk-overflow-x"rt><"bottom"ip>',
                        ajax: {
                            url: 'ws_gen.php',
                            data: {
                                'cmd': 'showCronLog',
                            }
                        }
                    });
                } else {
                    cronLogTable.ajax.reload();
                }
            }
        }
    });

    var jobWeekdays = new Array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");
    var cronLookups = [];
    var dtCronCols = [
        {
            "targets": [0],
            "title": "ID",
            "searchable": false,
            "sortable": false,
            "data": "ID",
            "width": 10
        },
        {
            "targets": [1],
            "title": "Title",
            "searchable": false,
            "sortable": true,
            "data": "Title",
            "width": 200,
            "className": "jobTitle"
        },
        {
            "targets": [2],
            "title": "Type",
            "searchable": false,
            "sortable": true,
            "data": "Type",
            "className": "jobType"
        },
        {
            "targets": [3],
            "title": "Parameters",
            "searchable": false,
            "sortable": true,
            "data": "Params",
            "width": 200,
            "className": "jobParams",
            render: function (data, type) {
                data = JSON.parse(data);
                var mkup = '';
                $.each(data, function (key, value) {
                    if (value.length > 0) {
                        if (key == "fieldSet" && cronLookups.inputSets[value] !== undefined) {
                            value = cronLookups.inputSets[value].Title;
                        } else if (key == "EmailTemplate" && cronLookups.docs[value] !== undefined) {
                            value = cronLookups.docs[value].Title;
                        } else if (key == "ResvStatus" && cronLookups.resvStatus[value] !== undefined) {
                            value = cronLookups.resvStatus[value][1];
                        }
                    }
                    mkup += '<div class="hhk-flex"><span class="mr-2"><strong>' + key.charAt(0).toUpperCase() + key.slice(1) + "</strong>: </span><span>" + value + "</span></div>";
                });
                return mkup;
            }
        },
        {
            "targets": [4],
            "title": "Interval",
            "searchable": true,
            "sortable": true,
            "data": "Interval",
            render: function (data, type) {
                return data.charAt(0).toUpperCase() + data.slice(1)
            },
            "width": 50,
            "className": "jobInterval"
        },
        {
            "targets": [5],
            "title": "Day",
            "searchable": false,
            "sortable": true,
            "data": "Day",
            "width": 50,
            "className": "jobDay",
            render: function (data, type, row) {
                if (row.Interval == 'weekly' && jobWeekdays[data] !== undefined) {
                    return jobWeekdays[data];
                } else {
                    return data;
                }
            }
        },
        {
            "targets": [6],
            "title": "Hour",
            "searchable": false,
            "sortable": true,
            "data": "Hour",
            "width": 50,
            "className": "jobHour"
        },
        {
            "targets": [7],
            "title": "Minute",
            "searchable": false,
            "sortable": true,
            "data": "Minute",
            "width": 50,
            "className": "jobMinute"
        },
        {
            "targets": [8],
            "title": "Status",
            "searchable": true,
            "sortable": true,
            "data": "Status",
            render: function (data, type) {
                switch (data) {
                    case 'a':
                        return "Active";
                        break;
                    case 'd':
                        return "Disabled";
                        break;
                    default:
                        return "";
                }
                ;
            },
            "width": 50,
            "className": "jobStatus"
        },
        {
            "targets": [9],
            "title": "Last Run",
            'data': 'Last Run',
            render: function (data, type) {
                return dateRender(data, type, dateFormat);
            },
            "width": 150
        },
        {
            "targets": [10],
            "title": "Actions",
            'data': 'ID',
            "width": 275,
            render: function (data, type, row) {
                return '<div class="cronActions hhk-flex">'
                        + ($('#canEditCron').val() == true ? '<button type="button" class="editCron ui-button ui-corner-all" data-job="' + data + '" data-jobtitle="' + row.Title + '" data-type="' + row.Type + '" data-interval="' + row.Interval + '" data-day="' + row.Day + '" data-hour="' + row.Hour + '" data-minute="' + row.Minute + '" data-status="' + row.Status + '">Edit</button>' : '')
                        + '<button type="button" class="runCron ui-button ui-corner-all" data-job="' + data + '" data-dryRun="1">Dry Run</button>'
                        + ($('#canForceRunCron').val() == true ? '<button type="button" class="runCron ui-button ui-corner-all" data-job="' + data + '" data-dryRun="0">Run Now</button>' : '')
                        + '<button type="button" class="saveCron ui-button ui-corner-all" data-job="' + data + '" style="display:none;">Save</button>'
                        + '<button type="button" class="deleteCron ui-button ui-corner-all" data-job="' + data + '" style="display:none;">Delete</button>'
                        + '<button type="button" class="cancelCron ui-button ui-corner-all" data-job="' + data + '" data-jobtitle="' + row.Title + '" data-interval="' + row.Interval + '" data-day="' + row.Day + '" data-hour="' + row.Hour + '" data-minute="' + row.Minute + '" data-status="' + row.Status + '" style="display:none;">Cancel</button>'
                        + '</div>';
            },
        }
    ];



    $('table#cronJobs').on('click', '.runCron', function (event) {
        var job = $(event.target).data('job');
        var dryRun = $(event.target).data('dryrun');
        $.ajax({
            url: 'ws_gen.php',
            method: 'post',
            data: {
                'cmd': 'forceRunCron',
                'dryRun': dryRun,
                'idJob': job,
            },
            dataType: 'json',
            success: function (data) {
                if (data.status && data.status == 's') {
                    flagAlertMessage(data.logMsg, false);
                } else if (data.status && data.status == 'f') {
                    flagAlertMessage(data.logMsg, true);
                }
                cronTable.ajax.reload();
            },
            error: function (xhr, textStatus, errorThrown) {
                flagAlertMessage("Cron error: " + errorThrown, true);
            }
        });
    });

    $('#newJob').on('click', '#addJob', function (event) {
        var jobType = $('#newJob #newJobType').val();
        var fresh = ($('table#cronJobs').find('button[data-job=-1]').length > 0 ? false : true);
        if (jobType && fresh) {
            var row = {
                'ID': -1,
                'Title': '',
                'Type': jobType,
                'Params': '{}',
                'Interval': '',
                'Day': '',
                'Hour': '',
                'Minute': '',
                'Status': 'd',
                'Last Run': '',
                'Actions': -1
            };
            cronTable.row.add(row).draw();
            $('#cronJobs').find(".editCron[data-job='-1']").click();
            $('#newJob #newJobType').val('');
        }
    });

    function cronActions($wrapper, cronTable) {

        //Show Edit mode
        $wrapper.on('click', '.editCron', function (e) {
            e.preventDefault();
            var $editBtn = $(this);
            var $row = $(this).closest('tr')
            var jobIntervalMkup = '';
            var jobIntervals = new Array("hourly", "daily", "weekly", "monthly");
            var jobStatuses = {"a": "Active", "d": "Disabled"};

            var jobTitleMkup = '<input type="text" id="editJobTitle" style="width: 100%" value="' + $editBtn.data('jobtitle') + '">';

            jobIntervalMkup += '<select id="editJobInterval">';
            $.each(jobIntervals, function (k, interval) {
                if ($editBtn.data('interval') == interval) {
                    jobIntervalMkup += '<option value="' + interval + '" selected="selected">' + interval.charAt(0).toUpperCase() + interval.slice(1) + '</option>';
                } else {
                    jobIntervalMkup += '<option value="' + interval + '">' + interval.charAt(0).toUpperCase() + interval.slice(1) + '</option>';
                }
            });
            jobIntervalMkup += '</select>';

            var jobDay = $editBtn.data("day");
            var jobHour = $editBtn.data("hour");
            var jobMinute = $editBtn.data("minute");

            var jobDayMkup = '<select id="editJobDay"><option disabled="disabled">Day</option>';
            for (var d = 1; d < 32; d++) {
                d = d.toLocaleString('en-US', {
                    minimumIntegerDigits: 2,
                    useGrouping: false
                })
                if (jobDay == d) {
                    jobDayMkup += '<option value="' + d + '" selected="selected">' + d + '</option>';
                } else {
                    jobDayMkup += '<option value="' + d + '">' + d + '</option>';
                }
            }
            jobDayMkup += '</select>';

            var jobWeekdayMkup = '<select id="editJobWeekday"><option disabled="disabled">Day</option>';
            jobWeekdays.forEach(function (item, index) {
                if (jobDay == index) {
                    jobWeekdayMkup += '<option value="' + index + '" selected="selected">' + item + '</option>';
                } else {
                    jobWeekdayMkup += '<option value="' + index + '">' + item + '</option>';
                }
            });
            jobWeekdayMkup += '</select>';


            var jobHourMkup = '<select id="editJobHour"><option disabled="disabled">Hour</option>';
            for (var h = 0; h < 24; h++) {
                h = h.toLocaleString('en-US', {
                    minimumIntegerDigits: 2,
                    useGrouping: false
                })
                if (jobHour == h) {
                    jobHourMkup += '<option value="' + h + '" selected="selected">' + h + '</option>';
                } else {
                    jobHourMkup += '<option value="' + h + '">' + h + '</option>';
                }
            }
            jobHourMkup += '</select>';

            var jobMinuteMkup = '<select id="editJobMinute"><option disabled="disabled">Minute</option>';
            for (var m = 0; m < 60; m++) {
                m = m.toLocaleString('en-US', {
                    minimumIntegerDigits: 2,
                    useGrouping: false
                })
                if (jobMinute == m) {
                    jobMinuteMkup += '<option value="' + m + '" selected="selected">' + m + '</option>';
                } else {
                    jobMinuteMkup += '<option value="' + m + '">' + m + '</option>';
                }
            }
            jobMinuteMkup += '</select>';

            var jobStatusMkup = '';
            var jobStatus = $editBtn.data('status');
            jobStatusMkup += '<select id="editJobStatus">';
            $.each(jobStatuses, function (k, v) {
                if (jobStatus == k) {
                    jobStatusMkup += '<option value="' + k + '" selected="selected">' + v + '</option>';
                } else {
                    jobStatusMkup += '<option value="' + k + '">' + v + '</option>';
                }
            });
            jobStatusMkup += '</select>';

            //get params mkup
            $.ajax({
                url: 'ws_gen.php',
                dataType: 'JSON',
                type: 'post',
                async: false,
                data: {
                    cmd: 'getCronParamMkup',
                    idJob: $editBtn.data('job'),
                    jobType: $editBtn.data('type'),
                },
                success: function (data) {
                    if (data.paramMkup) {
                        $row.find(".jobParams").html(data.paramMkup);
                    }
                }
            });

            $row.find('.jobTitle').html(jobTitleMkup);
            $row.find('.jobInterval').html(jobIntervalMkup);
            $row.find('.jobDay').html(jobWeekdayMkup + jobDayMkup);
            $row.find('.jobHour').html(jobHourMkup);
            $row.find('.jobMinute').html(jobMinuteMkup);
            $row.find('.jobStatus').html(jobStatusMkup);
            $row.find('.runCron, .editCron').hide();
            $row.find('.saveCron, .deleteCron, .cancelCron').show();
            $row.on('change', '#editJobInterval', function (e) {
                var interval = $(e.target).val();

                switch (interval) {
                    case "hourly":
                        $(this).closest('tr').find('#editJobDay, #editJobWeekday, #editJobHour').hide();
                        break;
                    case "daily":
                        $(this).closest('tr').find('#editJobDay, #editJobWeekday').hide();
                        $(this).closest('tr').find('#editJobHour').show();
                        break;
                    case "weekly":
                        $(this).closest('tr').find('#editJobWeekday, #editJobHour').show();
                        $(this).closest('tr').find('#editJobDay').hide();
                        break;
                    case "monthly":
                        $(this).closest('tr').find('#editJobDay, #editJobHour').show();
                        $(this).closest('tr').find('#editJobWeekday').hide();
                        break;
                }

            });
            $(this).closest('tr').find('#editJobInterval').trigger('change');

            $row.on('change', '.editParam[data-name=report]', function (e) {
                $.ajax({
                    url: '../house/ws_reportFilter.php',
                    method: 'post',
                    data: {
                        'cmd': 'getFieldSetOptMkup',
                        'report': $row.find('.editParam[data-name=report]').val(),
                        'selection': $row.find('.editParam[data-name=fieldSet]').data('curval'),
                    },
                    dataType: 'json',
                    success: function (data) {
                        if (data.fieldSetOptMkup) {
                            $row.find('.editParam[data-name=fieldSet]').html(data.fieldSetOptMkup);
                        }
                    },
                    error: function (xhr, textStatus, errorThrown) {
                        flagAlertMessage("Cron error: " + errorThrown, true);
                    }
                });
            });
            $row.find('.editParam[data-name=report]').trigger('change');

        });
        //End Show Edit mode

        //Edit Job
        $wrapper.on('click', '.saveCron', function (e) {
            e.preventDefault();
            var row = $(this).closest("tr");
            var jobId = $(this).data('job');
            var title = row.find("#editJobTitle").val();
            var jobType = row.find(".jobType").text();
            var interval = row.find('#editJobInterval').val();
            var day = row.find("#editJobDay").val();
            var weekday = row.find("#editJobWeekday").val();
            var hour = row.find("#editJobHour").val();
            var minute = row.find("#editJobMinute").val();
            var status = row.find("#editJobStatus").val();

            var data = {
                cmd: 'updateCronJob',
                idJob: jobId,
                title: title,
                jobType: jobType,
                params: {},
                interval: interval,
                day: day,
                weekday: weekday,
                hour: hour,
                minute: minute,
                status: status,

            }

            //get params
            row.find(".jobParams .editParam").each(function () {
                data.params[$(this).data('name')] = $(this).val();
            });

            if (jobId != "") {
                $.ajax({
                    url: 'ws_gen.php',
                    dataType: 'JSON',
                    type: 'post',
                    data: data,
                    success: function (data) {
                        if (data.job && data.job.idJob > 0) {
                            var rowdata = cronTable.row(row).data();
                            rowdata["ID"] = data.job.idJob;
                            rowdata["Title"] = data.job.Title;
                            rowdata["Interval"] = data.job.Interval;
                            rowdata["Params"] = data.job.Params;
                            rowdata["Day"] = data.job.Day;
                            rowdata["Hour"] = data.job.Hour;
                            rowdata["Minute"] = data.job.Minute;
                            rowdata["Status"] = data.job.Status;
                            rowdata["Actions"] = data.job.idJob;

                            cronTable.row(row).data(rowdata);
                        } else {
                            if (data.error) {
                                flagAlertMessage(data.error, true);
                            } else {
                                flagAlertMessage('An unknown error occurred.', true);
                            }
                        }
                    }
                });
            }
        });
        //End Edit Job

        //Cancel Edit Job
        $wrapper.on('click', '.cancelCron', function (e) {
            e.preventDefault();
            var data = cronTable.row($(this).parents('tr')).data();
            if (data.ID == '-1') {
                cronTable.row($(this).parents('tr')).remove().draw();
            } else {
                cronTable.row($(this).parents('tr')).data(data);
            }
        });
        //End Cancel Edit Job

        //Delete Cron Job
        $wrapper.on('click', '.deleteCron', function (e) {
            e.preventDefault();
            var row = $(this).closest("tr");
            if ($(this).data('job') == '-1') {
                row.find(".cancelCron").trigger('click');
            } else {
                if (confirm("Are you sure you want to delete " + row.find("#editJobTitle").val() + "?")) {
                    row.find("#editJobStatus").append('<option value="del"></option>').val('del');
                    row.find(".saveCron").trigger('click');
                    cronTable.row(row).remove().draw();
                }
            }
        });
        //End Delete Cron Job
    }

    var dtCronLogCols = [
        {
            "targets": [0],
            "title": "Job ID",
            "searchable": false,
            "sortable": false,
            "data": "Job ID",
        },
        {
            "targets": [1],
            "title": "Job",
            "searchable": false,
            "sortable": true,
            "data": "Job",
        },
        {
            "targets": [2],
            "title": "Log Text",
            "searchable": true,
            "sortable": true,
            "data": "Log Text",
        },
        {
            "targets": [3],
            "title": "Status",
            "searchable": true,
            "sortable": true,
            "data": "Status",
            render: function (data, type) {
                switch (data) {
                    case 's':
                        return "Success";
                        break;
                    case 'f':
                        return "Fail";
                        break;
                    default:
                        return "";
                }
            }
        },
        {
            "targets": [4],
            "title": "Run Time",
            'data': 'Run Time',
            render: function (data, type) {
                return dateRender(data, type, dateFormat);
            }
        }
    ];

    var dtNotificationLogCols = [
        {
            "targets": [0],
            "title": "Type",
            "searchable": false,
            "sortable": false,
            "data": "Log_Type",
        },
        {
            "targets": [1],
            "title": "Sub Type",
            "searchable": false,
            "sortable": true,
            "data": "Sub_Type",
        },
        {
            "targets": [2],
            "title": "User",
            "searchable": true,
            "sortable": true,
            "data": "username",
        },
        {
            "targets": [3],
            "title": "To",
            "searchable": true,
            "sortable": true,
            "data": "To",
        },
        {
            "targets": [4],
            "title": "From",
            "searchable": true,
            "sortable": true,
            "data": "From",
        },
        {
            "targets": [5],
            "title": "Log Text",
            "searchable": true,
            "sortable": true,
            "data": "Log_Text"
        },
        {
            "targets": [6],
            "title": "Details",
            'data': 'Log_Details',
            render: function (data, type) {
                var json = JSON.parse(data);
                var detailStr = "";
                $.each(json, function (k, v) {
                    if (typeof v == 'string') {
                        detailStr += "<div><strong>" + k + ":</strong>" + v + "</div>";
                    } else {
                        detailStr += "<div><strong>" + k + ":</strong><pre>" + JSON.stringify(v, null, 2) + "</pre></div>";
                    }
                });
                return detailStr;
            }
        },
        {
            "targets": [7],
            "title": "Timestamp",
            'data': 'Timestamp',
            render: function (data, type) {
                return dateRender(data, type, 'MMM D YYYY h:mm:ss.SSSS a');
            }
        }
    ];

    var dtAPIAccessLogCols = [
        {
            targets: [0],
            className: 'dt-control',
            orderable: false,
            data: null,
            defaultContent: ''
        },
        {
            "targets": [1],
            "title": "Request Endpoint",
            "searchable": false,
            "sortable": false,
            "data": "requestPath",
        },
        {
            "targets": [2],
            "title": "Response Code",
            "searchable": false,
            "sortable": true,
            "data": "responseCode",
        },
        {
            "targets": [3],
            "title": "OAuth Client ID",
            "searchable": false,
            "sortable": true,
            "data": "oauth_client_id",
        },
        {
            "targets": [4],
            "title": "oauth_access_token_id",
            "searchable": true,
            "sortable": true,
            "data": "oauth_access_token_id",
            "visible": false,
        },
        {
            "targets": [5],
            "title": "Request",
            "searchable": true,
            "sortable": true,
            "data": "request",
            "visible": false,
        },
        {
            "targets": [6],
            "title": "Response",
            "searchable": true,
            "sortable": true,
            "data": "response",
            "visible": false,
        },
        {
            "targets": [7],
            "title": "IP Address",
            "searchable": true,
            "sortable": true,
            "data": "ip_address",
        },
        {
            "targets": [8],
            "title": "Timestamp",
            'data': 'Timestamp',
            render: function (data, type) {
                return dateRender(data, type, 'MMM D YYYY h:mm:ss a');
            }
        }
    ];

    $('#logsTabDiv').tabs({

        beforeActivate: function (event, ui) {

            var pid = ui.newTab.prop('id');
            if (!logTable[pid]) {
                logTable[pid] = 1;

                //notification log
                if (pid == "linl") {

                    $('#table' + pid).dataTable({
                        "columnDefs": dtNotificationLogCols,
                        "serverSide": true,
                        "processing": true,
                        //"deferRender": true,
                        "language": { "sSearch": "Search Log:" },
                        "sorting": [[7, 'desc']],
                        "displayLength": 25,
                        "lengthMenu": [[25, 50, 100], [25, 50, 100]],
                        "dom": '<"top"lf><"hhk-overflow-x"rt><"bottom"ip>',
                        ajax: {
                            url: 'ws_gen.php',
                            data: {
                                'cmd': 'showNotificationLog'
                            }
                        }
                    });
                } else if (pid == "liapi") {

                    let table = $('#table' + pid).dataTable({
                        "columnDefs": dtAPIAccessLogCols,
                        "serverSide": true,
                        "processing": true,
                        //"deferRender": true,
                        "language": { "sSearch": "Search Log:" },
                        "sorting": [[8, 'desc']],
                        "displayLength": 25,
                        "lengthMenu": [[25, 50, 100], [25, 50, 100]],
                        "dom": '<"top"lf><"hhk-overflow-x"rt><"bottom"ip>',
                        ajax: {
                            url: 'ws_gen.php',
                            data: {
                                'cmd': 'showAPIAccessLog'
                            }
                        }
                    });

                    table.on('click', 'td.dt-control', function (e) {
                        let tr = e.target.closest('tr');
                        let row = table.DataTable().row(tr);
                    
                        if (row.child.isShown()) {
                            // This row is already open - close it
                            row.child.hide();
                        }
                        else {
                            // Open this row
                            row.child(formatAPIDetails(row.data())).show();
                        }
                    });
                } else {

                    $('#table' + pid).dataTable({
                        "columnDefs": dtCols,
                        "serverSide": true,
                        "processing": true,
                        //"deferRender": true,
                        "language": { "sSearch": "Search Log:" },
                        "sorting": [[7, 'desc']],
                        "displayLength": 25,
                        "lengthMenu": [[25, 50, 100], [25, 50, 100]],
                        "dom": '<"top"lf><"hhk-overflow-x"rt><"bottom"ip>',
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
        }
    });

    function formatAPIDetails(row){
        return `
            <div>` +
            (row.oauth_access_token_id ?
                `<div class="mb-3">
                    <strong>Access Token ID</strong>
                    <pre style="white-space: pre-wrap;">${row.oauth_access_token_id}</pre>
                </div>` : '') +
                `<div class="mb-3">
                    <strong>Request</strong>
                    <pre style="white-space: pre-wrap;">${row.request}</pre>
                </div>
                <div>
                    <strong>Response</strong>
                    <pre style="white-space: pre-wrap;">${row.response}</pre>
                </div>
            </div>
        `;
    }

    $('#logsTabDiv').tabs("option", "active", 1);

    $("input[type=submit], input[type=reset], button").button();
    $('#financialRoomSubsidyId, #financialReturnPayorId').change(function () {

        $('#financialRoomSubsidyId, #financialReturnPayorId').removeClass('ui-state-error');

        if ($('#financialRoomSubsidyId').val() != 0 && $('#financialRoomSubsidyId').val() === $('#financialReturnPayorId').val()) {
            $('#financialRoomSubsidyId, #financialReturnPayorId').addClass('ui-state-error');
            alert('Subsidy Id must be different than the Return Payor Id');
        }
    });

    tbs.tabs("option", "active", tabIndex);
    $('#tabs').show();
    $(document).find("textarea.hhk-autosize").trigger("input");

    //authentication tab
    authTabs = $('#authTabs').tabs();
    authTabs.tabs("option", "active", 0);
    $('#authTabs').show();

    $(document).on("submit", "form#siteConfigForm", function (e) {
        
        $(this).find("textarea").each(function () {
            let base64val = buffer.Buffer.from($(this).val()).toString("base64");
            $(this).val(base64val);
        });
    });

    $(document).on("submit", "form.authForm", function (e) {
        e.preventDefault();
        $this = $(this);
        $submitbtn = $(this).find("input[type=submit]")
        $submitbtn.prop("disabled", true);
        data = new FormData($(this)[0]);
        data.append('saveIdP', 'true');

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
					flagAlertMessage(data.success, false);
				}
				if(data.error){
					flagAlertMessage(data.error, true);
				}
				if(data.idpMkup && data.idpName){
					$this.empty().html(data.idpMkup);
					$("#authTabs ul li.ui-tabs-active a").text(data.idpName);
				}

				$submitbtn.prop("disabled", false);
            },
            error: function (e) {
				flagAlertMessage(e.responseText, true);
                $submitbtn.prop("disabled", false);
            }
        });
    });
});