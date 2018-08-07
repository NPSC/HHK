function getNotes(rid, container)
{
	rid = parseInt(rid, 10);
	if(Number.isInteger(rid) ){
	
		$(container).empty();
		$(container).html('<div id="resvNotesHeader" class="ui-widget-header ui-state-default ui-corner-all"><div class="hhk-checkinHdr"></div></div><div id="resvNotesDetail"></div>');
		$("#resvNotesHeader div").html(resvTitle + " Notes");
		$("#resvNotesHeader div").append($('<input type="button" id="hhk-newNote" style="margin-left: 30px; margin-bottom: 5px; font-size: 0.8em;" value="New Note">').button());
		$(container + " #resvNotesDetail").html('<table style="width: 100%"></table>');
		
		var dtCols = [
			{
	        "targets": [ 3 ],
	        'data': 'NoteId',
	        'visible': false
	    },
	    {
	        "targets": [ 0 ],
	        "title": "Date",
	        'data': 'Date',
	        render: function ( data, type ) {
	            return dateRender(data, type);
	        }
	    },
	    {
	        "targets": [ 1 ],
	        "title": "Username",
	        "searchable": false,
	        "sortable": false,
	        "data": "Type"
	    },
	    {
	        "targets": [ 2 ],
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
	            url: 'ws_resv.php?cmd=getNoteList&rid=' + rid
	        }
	        });
	        
	        $(container).show();
	}else{
		$("divAlertMsg").html("Cannot get Reservation Notes for specified Reservation ID").show();
	}
};