(function ($) {

    $.fn.cronManager = function (options) {

        var defaults = {
            serviceURL: 'ws_gen.php',
            canEditCron: false,
            canForceRunCron: false,
            dtCronCols = [
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
                    "width": 200
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
                    "width":50
                },
                {
                    "targets": [ 3 ],
                    "title": "Time",
                    "searchable": false,
                    "sortable": true,
                    "data": "Time",
                    "width":50
                },
                 {
                     "targets": [ 4 ],
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
                    "width":50
                },
                {
                    "targets": [ 5 ],
                    "title": "Last Run",
                    'data': 'Last Run',
                    render: function ( data, type ) {
                        return dateRender(data, type, dateFormat);
                    },
                    "width":150
                },
                {
                    "targets": [ 6 ],
                    "title": "Actions",
                    'data': 'ID',
                    render: function ( data, type ) {
                        return createActions(data, row);
                    }
                }
            ]
        };

        var settings = $.extend(true, {}, defaults, options);

        var $wrapper = $(this);

        //set uid
        $wrapper.attr('id', '');
        $wrapper.uniqueId();
        uid = $wrapper.attr('id');

        createViewer($wrapper, settings);

        return this;
    };

    function createActions(jobId, row) {

        var $ul, $li;

        $ul = $('<ul />').addClass('ui-widget ui-helper-clearfix hhk-ui-icons');

        // Edit icon
        $li = $('<li title="Edit Job" data-jobid="' + jobId + '" data-title="' + row.Title + '" />').addClass('hhk-job-button job-edit ui-corner-all ui-state-default');
        $li.append($('<span class="ui-icon ui-icon-pencil" />'));

        $ul.append($li);

        // Save(Done) Edit Icon
        $li = $('<li title="Save Job" />').addClass('hhk-job-button job-done job-action ui-corner-all ui-state-default').hide();
        $li.append($('<span class="ui-icon ui-icon-check" />'));

        $ul.append($li);

        // Cancel Edit Icon
        $li = $('<li title="Cancel" data-titletext="' + row.Title + '" />').addClass('hhk-job-button job-cancel job-action ui-corner-all ui-state-default').hide();
        $li.append($('<span class="ui-icon ui-icon-cancel" />'));

        $ul.append($li);

        // Dry Run button
        $li = $('<li title="Dry Run" data-jobid="' + jobId + '" />').addClass('hhk-job-button job-dry-run ui-corner-all ui-state-default');
        $li.append($('Dry Run'));

        $ul.append($li);

        // Force Run button
        $li = $('<li title="Run Now" data-jobid="' + jobId + '" />').addClass('hhk-job-button job-force-run ui-corner-all ui-state-default');
        $li.append($('Run Now'));

        $ul.append($li);

        return $('<div />').append($ul).html();
    }

    function createViewer($wrapper, settings) {
    	var cronLogLoaded = false;
    	var cronLogTable;

    	//cron tabs
        $("#cronTabs").tabs({
                beforeActivate: function (event, ui) {
                if (ui.newTab.prop('id') === 'liCronLog') {
                        if(!cronLogLoaded){
                            cronLogLoaded = true;
                            cronLogTable = $('table#cronLog').DataTable({
                                "columnDefs": settings.dtCronLogCols,
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
                                        'cmd': 'showCronLog'
                                    }
                                }
                            });
                        }else{
                                cronLogTable.ajax.reload();
                        }
                }
            }
        });
        
        var $table = $('<table />').attr(settings.tableAttrs).appendTo($wrapper);

        $table.DataTable({
            "columnDefs": settings.dtCols,
            "serverSide": true,
	    "processing": true,
	    "deferRender": true,
	    "language": {"sSearch": "Search Jobs:"},
            "sorting": [[0,'desc']],
	    "displayLength": 5,
	    "lengthMenu": [[5, 10, 25, -1], [5, 10, 25, "All"]],
	    "dom": '<"dtTop"if>rt<"dtBottom"lp><"clear">',
	    ajax: {
	        url: settings.serviceURL,
	        data: {
	            'cmd': 'getNoteList',
	            'linkType': settings.linkType,
	            'linkId': settings.linkId
	        }
            }
        });
            
        //add ignrSave class to Dt controls
        $(".dataTables_filter").addClass('ignrSave');
        $(".dtBottom").addClass('ignrSave');

        $wrapper.show();
    }

}(jQuery));