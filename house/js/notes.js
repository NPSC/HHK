function getNotes(rid)
{
	rid = parseInt(rid, 10);
	if(Number.isInteger(rid) ){
	
		$("#resvNotes").empty();
		$("#resvNotes").html('<div id="resvNotesHeader" class="ui-widget-header ui-state-default ui-corner-all"><div class="hhk-checkinHdr"></div></div><div id="resvNotesDetail"></div>');
		$("#resvNotesHeader div").html("Reservation Notes");
		$("#resvNotes #resvNotesDetail").html('<table style="width: 100%"></table>');
		
		var dtCols = [
			{
	        "targets": [ 0 ],
	        'data': 'id',
	        'visible': false
	    },
	    {
	        "targets": [ 1 ],
	        "title": "Date",
	        'data': 'Date',
	        render: function ( data, type ) {
	            return dateRender(data, type);
	        }
	    },
	    {
	        "targets": [ 2 ],
	        "title": "Username",
	        "searchable": false,
	        "sortable": false,
	        "data": "Type"
	    },
	    {
	        "targets": [ 3 ],
	        "title": "Note",
	        "searchable": false,
	        "sortable": false,
	        "data": "Sub-Type"
	    }
	];
	
			
		listNoteTable = $('#resvNotesDetail table').dataTable({
	        "columnDefs": dtCols,
	        "serverSide": true,
	        "processing": true,
	        "deferRender": true,
	        "language": {"sSearch": "Search Notes:"},
	        "sorting": [[0,'desc']],
	        "displayLength": 25,
	        "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
	        "Dom": '<"top"ilf>rt<"bottom"ip>',
	        ajax: {
	            url: 'ws_resv.php?cmd=getNotesList&rid=' + rid
	        }
	        });
	}else{
		$("divAlertMsg").html("Cannot get Reservation Notes for specified Reservation ID").show();
	}
};