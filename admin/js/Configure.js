
$(document).ready(function () {
    var tabIndex = $('#tabIndex').val();
	var servFile = $('#wsServFile').val();
    var tbs;
    var authTabs;
    var logTable = [];
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
        }
    });


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
    	data.append('cmd','saveIdP');
    	
    	$.ajax({
        	type: 'POST',
            enctype: 'multipart/form-data',
            url: 'ws_gen.php',
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