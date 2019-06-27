(function( $ ) {
 
var dtCols = [
    {
        "targets": [ 0 ],
        "title": "Date",
        'data': 'Date',
        render: function ( data, type ) {
            return dateRender(data, type, dateFormat);
        }
    },
    {
        "targets": [ 1 ],
        "title": "Type",
        "searchable": false,
        "sortable": false,
        "data": "Type"
    },
    {
        "targets": [ 2 ],
        "title": "Sub-Type",
        "searchable": false,
        "sortable": false,
        "data": "Sub-Type"
    },
     {
        "targets": [ 3 ],
        "title": "User",
        "searchable": false,
        "sortable": true,
        "data": "User"
    },
    {
        "targets": [ 4 ],
        "visible": false,
        "data": "Id"
    },
    {
        "targets": [ 5 ],
        "title": "Log Text",
        "sortable": false,
        "data": "Log Text"
    }

];
 
    $.fn.incidentReport = function( options ) {
 
        this.append(
	        $([
			  '<fieldset class="hhk-panel">',
			  '  <legend style="font-weight:bold">Incidents</legend>',
			  '  <div class="datatable" id="incidentsDT"></div>',
			  '</fieldset>'
			].join("\n"))
		);
 
		incidentReportTbl = $('#dataTbl').dataTable({
            "columnDefs": dtCols,
            "serverSide": true,
            "processing": true,
            "deferRender": true,
            "language": {"search": "Search Log Text:"},
            "sorting": [[0,'desc']],
            "displayLength": 25,
            "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
            "Dom": '<"top"ilf>rt<"bottom"ip>',
            ajax: {
                url: ""
            }
        });
        return this;
 
    };
 
}( jQuery ));