(function ($) {

    $.fn.notesViewer = function (options) {

        var defaults = {
            idReservation: 0,
            idVisit: 0,
            idPsg: 0,
            serviceURL: 'ws_resv.php?cmd=getNoteList&rid=',
            newLabel: 'New Note',
            tableAttrs: {
                class: 'display compact',
                width: '100%'
            },
            textAreaAttrs: {
                id: 'taNoteText',
                width: '100%',
                rows: 1
            },
           
            dtCols: [
                {
                "targets": 0,
                        "title": "Actions",
                        'data': "Action",
                        'width': "15%",
                        render: function (data, type, row) {
                            return '<button class="editNote ui-button ui-corner-all ui-widget" data-noteid="' + data + '">Edit</button><button class="done ui-button ui-corner-all ui-widget" style="display: none; margin-bottom:5px;">Save</button><button class="deleteNote ui-button ui-corner-all ui-widget" data-noteid="' + data + '" style="display: none;">Delete</button>';
                        }
                },
                {
                "targets": [ 1 ],
                        "title": "Date",
                        'data': 'Date',
                        'width': '25%',
                        render: function (data, type) {
                            return dateRender(data, type, dateFormat);
                        }
                },
                {
                "targets": [ 2 ],
                        "title": "User",
                        "searchable": true,
                        "sortable": true,
                        "width": "20%",
                        "data": "User"
                },
                {
                "targets": [ 3 ],
                        "title": "Note",
                        "searchable": true,
                        "sortable": false,
                        "className":'noteText',
                        "data": "Note"
                }
            ]
        };

        var settings = $.extend(true, {}, defaults, options);

        var $wrapper = $(this);

        createViewer($wrapper, settings);

        return this;
    };

    function createFooter(settings) {
        
        return $('<tfoot />')
                .append('<td>New Note</td>')
                .append( $('<td/>').append($('<textarea').attr(settings.textAreaAttrs)).attr('colspan', (settings.dtCols.length - 1) ));
    }

    function createViewer($wrapper, settings) {
        
        var $table = $('<table />').attr(settings.tableAttrs).append(createFooter(settings));
        
        var listNoteTable = $table.DataTable({
	        "columnDefs": settings.dtCols,
	        "serverSide": true,
	        "processing": true,
	        "deferRender": true,
	        "language": {"sSearch": "Search Notes:"},
	        "sorting": [[0,'desc']],
	        "displayLength": 5,
	        "lengthMenu": [[5, 10, 25, 50, 100, -1], [5, 10, 25, 50, 100, "All"]],
 	        "Dom": '<"top"ilf>rt<"bottom"ip><"clear">',
	        ajax: {
	            url: settings.serviceURL + settings.idReservation
	        }
	        });



        
    }

}(jQuery));