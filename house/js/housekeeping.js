window.housekeeping = {};

window.housekeeping.getDtBtns = function(title){
    return [
        {
            extend: "print",
            className: "ui-corner-all",
            autoPrint: true,
            paperSize: "letter",
            exportOptions: {
                columns: ":not('.noPrint')",
                orthogonal:"print",
            },
            title: function(){
                return title;
            },
            messageBottom: function(){
                var now = moment().format("MMM D, YYYY") + " at " + moment().format("h:mm a");
                return '<div style="padding-top: 10px; position: fixed; bottom: 0; right: 0">Printed on '+now+'</div>';
            },
            customize: function (win) {
                $(win.document.body).css("font-size", "0.9em");
                $(win.document.body).find("td .hhk-noprint").hide();

                $(win.document.body).find("table").css("font-size", "inherit");
            }
        }
    ];
}

$(document).ready(function () {
    "use strict";

    var dateFormat = 'ddd MMM D, YYYY';
    var groupingTitle = $('#groupingTitle').val();
    
    housekeeping.cgCols = [
        {	'data': 'Group_Title',
            'title': groupingTitle,
            "visible": false
        },
        {
            'data': 'Room',
            'title': 'Room',
            'searchable': true
        },
        {
            'data': 'Status',
            'title': 'Status',
            'searchable': false,
            'sortable': true,
            'createdCell': function(td, cellData, rowData, col){
                if(rowData.StatusColor){
                    $(td).css("background-color", rowData.StatusColor);
                }
            }

        },
        {
            'data': 'Action',
            'title': 'Action',
            'searchable': false,
            'sortable': false,
            className: "noPrint"
        },
        {
            'data': 'Occupant',
            'title': 'Occupant',
            'searchable': true,
            'sortable': true
        },
        {
            'data': 'numGuests',
            'title': 'Guests',
            'searchable': false,
            'sortable': true
        },
        {
            'data': 'Checked_In',
            'title': 'Checked In',
            'type': 'date',
            render: function (data, type, row) {
                return dateRender(data, type, dateFormat);
            },
            'searchable': true,
            'sortable': true
        },
        {
            'data': 'Expected_Checkout',
            'title': 'Expected Checkout',
            'type': 'date',
            render: function (data, type, row) {
                return dateRender(data, type, dateFormat);
            },
            'searchable': true,
            'sortable': true
        },
        {
            'data': 'Next_Expected_Arrival',
            'title': 'Next Expected Arrival',
            'type': 'date',
            render: function (data, type, row) {
                return dateRender(data, type, dateFormat);
            },
            'searchable': true,
            'sortable': true
        },
        {
            'data': 'Last_Cleaned',
            'title': 'Last Cleaned',
            'type': 'date',
            render: function (data, type, row) {
                return dateRender(data, type, dateFormat);
            },
            'searchable': true,
            'sortable': true
        },
        {
            'data': 'Last_Deep_Clean',
            'title': 'Last Deep Clean',
            'type': 'date',
            render: function (data, type, row) {
                if(type == 'print'){
                    return $(data).val();
                }else{
                    return data;
                }
            },
            'searchable': true,
            'sortable': true
        },
        {
            'data': 'Notes',
            'title': 'Latest Note',
            'searchable': true,
            'sortable': false,
            render: function(data, type, row){
                if(type == 'print'){
                    data = $(data);
                    data.find(".hhk-noprint").remove();
                    return data[0].outerHTML;
                }else{
                    return data;
                }
            }
        }
    ];

    housekeeping.inCols = [
        {
            'data': 'Primary Guest',
            'title': window.labels.primaryGuest,
            'searchable': true,
            'sortable': true
        },
        {
            'data': 'Guests',
            'title': window.labels.visitor + 's',
            'searchable': true,
            'sortable': true
        },
        {
            'data': 'Arrival Date',
            'title': 'Expected Arrival',
            'type': 'date',
            render: function (data, type) {
                return dateRender(data, type, dateFormat);
            },
            'searchable': true,
            'sortable': true
        },
        {
            'data': 'Expected Departure',
            'title': 'Expected Departure',
            'type': 'date',
            render: function (data, type) {
                return dateRender(data, type, dateFormat);
            },
            'searchable': true,
            'sortable': true
        },
        {
            'data': 'Room',
            'title': 'Room',
            'searchable': true,
            'sortable': false
        },
        {
            'data': 'Nights',
            'title': 'Nights',
            'searchable': true,
            'sortable': false
        }
    ];

    housekeeping.outCols = [
        {
            'data': 'Room',
            'title': 'Room',
            'searchable': true,
            'sortable': true
        },
        {
            'data': 'Visit Status',
            'title': 'Status',
            'searchable': false,
            'sortable': true
        },
        {
            'data': 'Primary Guest',
            'title': 'Occupant',
            'searchable': true,
            'sortable': true
        },
        {
            'data': 'Arrival Date',
            'title': 'Checked In',
            'type': 'date',
            render: function (data, type) {
                return dateRender(data, type, dateFormat);
            },
            'searchable': true,
            'sortable': true
        },
        {
            'data': 'Expected Checkout',
            'title': 'Expected Checkout',
            'type': 'date',
            render: function (data, type) {
                return dateRender(data, type, dateFormat);
            },
            'searchable': true,
            'sortable': true
        },
        {
            'data': 'Notes',
            'title': 'Latest Note',
            'searchable': true,
            'sortable': false
        }
    ];

    housekeeping.dtLogColDefs = [
        {
            'targets': [0],
            'data': 'Room',
            'title': 'Room',
            'searchable': true,
            'sortable': true
        },
        {
            'targets': [1],
            'data': 'Type',
            'visible': false
        },
        {
            'targets': [2],
            'data': 'Status',
            'title': 'Status',
            'searchable': false,
            'sortable': true
        },
        {
            "targets": [3],
            'data': 'Last Cleaned',
            'title': 'Last Cleaned',
            render: function (data, type, row) {
                return dateRender(data, type, dateFormat);
            }
        },
        {
            "targets": [4],
            'data': 'Last Deep Clean',
            'title': 'Last Deep Clean',
            render: function (data, type, row) {
                return dateRender(data, type, dateFormat);
            }
        },
        {
            'targets': [5],
            'data': 'Notes',
            'title': 'Latest Note',
            'searchable': true,
            'sortable': false
        },
        {
            'targets': [6],
            'data': 'User',
            'title': 'User',
            'sortable': true,
            'searchable': true
        },
        {
            "targets": [7],
            'data': 'Timestamp',
            'title': 'Timestamp',
            render: function (data, type, row) {
                return dateRender(data, type, dateFormat);
            }
        }
    ];

    var listEvtTable;
    var coDate = new Date();

    $.extend($.fn.dataTable.defaults, {
        "dom": '<"top"if>rt<"bottom"lp><"clear">',
        "displayLength": 50,
        "lengthMenu": [[25, 50, -1], [25, 50, "All"]]
    });

    $('#btnReset1, #btnSubmitClean, #btnReset2, #btnPrintAll, #btnExcelAll, #btnSubmitTable, #prtCkOut, #prtCkIn, #prtClnToday').button();

    $("#roomDetailsDialog").dialog({
        width: getDialogWidth(1000),
        autoOpen: false,
        modal: true,
        close: function(){
            $("#roomDetailsDialog").empty();
            try{
                //refresh all tables with notes
                $("table#dirtyTable, table#outTable, table#roomTable").DataTable().ajax.reload();
            }catch(e){

            }
        }
    });

    $("#mainTabs").on("click", ".roomDetails", function(e){
        
        let $this = $(this);
        $("#roomDetailsDialog").empty();
        $("#roomDetailsDialog").append('<div class="roomNotes hhk-tdbox"></div>');

        $("#roomDetailsDialog").find(".roomNotes").notesViewer({
            linkId: $this.data("idroom"),
            linkType: 'room',
            newNoteAttrs: {id:'taNewRNote', name:'taNewRNote'},
            alertMessage: function(text, type) {
                flagAlertMessage(text, type);
            }
        });

        $("#roomDetailsDialog").dialog("option", "title", $this.data("title"));
        $("#roomDetailsDialog").dialog("open");
    });

    window.housekeeping.setRoomStatus = function(idResc, status, srcTbl){
        try{
            fetch('ws_resc.php',{
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "Accept": "application/json"
                },
                body: new URLSearchParams({
                    cmd: "saveRmCleanCode",
                    idr: idResc,
                    stat: status
                })
            })
            .then((resp)=>{
                if(resp.ok){
                    return resp.json()
                }else{
                    throw new Error(resp.status);
                }
                
            })
            .then((data) => {
                if(data.status == "success"){
                    srcTbl.DataTable().ajax.reload();
                }else if(data.status == "error"){
                    flagAlertMessage(data.msg, 'error');
                }else if(data.gotopage){
                    window.open(data.gotopage, '_self');
                }

            })
            .catch((reason)=>{
                flagAlertMessage(reason, "error");
            })

        }catch(e){

        }
    };

    $(document).on('click', ".setRoomStat", function(e){
        $(e.target).parents("td").addClass("hhk-loading");
        let idResc = $(e.target).data("idroom");
        let status = $(e.target).data('setstatus');
        let srcTbl = $(e.target).parents("table");
        housekeeping.setRoomStatus(idResc, status, srcTbl);
    });

    $('#mainTabs').tabs({
        beforeActivate: function (event, ui) {
            if (ui.newPanel.length > 0) {
                if (ui.newTab.prop('id') === 'lishoCL' && !listEvtTable) {

                    listEvtTable = $('#dataTbl').DataTable({
                        "processing": true,
                        "serverSide": true,
                        "ajax": {
                            "url": "ws_resc.php?cmd=clnlog",
                            "type": "POST"
                        },
                        "columnDefs": housekeeping.dtLogColDefs,
                        "deferRender": true,
                        "order": [[7, 'desc']],
                        "pageLength": 50,
                        "lengthMenu": [25, 50, 100],
                        "dom": '<"top"Bif><\"hhk-overflow-x\"rt><"bottom"lp>',
                        "buttons": housekeeping.getDtBtns("Housekeeping - Cleaning Log"),
                    });
                }else if(ui.newPanel.find("table#roomTable.dataTable").length > 0 || ui.newPanel.find("table#dirtyTable.dataTable").length > 0){
                    ui.newPanel.find("table").DataTable().ajax.reload();
                }
            }
        }
    });
    $('#mainTabs').tabs("option", "active", window.curTab);

    $('#ckInDate').datepicker({
        yearRange: '-1:+01',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        numberOfMonths: 1,
        dateFormat: 'M d, yy',
        onClose: function (dateText) {
            var d = new Date(dateText);
            if (d != coDate) {
                coDate = d;
                $('#inTable').DataTable().ajax.url('ws_resc.php?cmd=cleanStat&tbl=inTable&stdte=' + $.datepicker.formatDate("yy-mm-dd", coDate) + '&enddte=' + $.datepicker.formatDate("yy-mm-dd", coDate));
                var updatedBtn = housekeeping.getDtBtns("Housekeeping - " + labels.visitor + "s Checking In - " + $.datepicker.formatDate("M d, yy", coDate))[0];
                $('#inTable').DataTable().button(0).remove().add(0, updatedBtn);
                $('#inTable').DataTable().ajax.reload();
            }
        }
    });

    $('#ckInDate').datepicker('setDate', coDate);

    $('#inButtonSet.week-button-group').on('click', 'button', function (e) {
        var btn = $(this)
        $('.week-button-group button').removeClass("ui-state-active");
        if (btn.data("weeks")) {
            var startDate = new Date();
            var endDate = new Date();
            endDate.setDate(startDate.getDate() + (btn.data("weeks") * 7));

            $('#inTable').DataTable().ajax.url('ws_resc.php?cmd=cleanStat&tbl=inTable&stdte=' + $.datepicker.formatDate("yy-mm-dd", startDate) + '&enddte=' + $.datepicker.formatDate("yy-mm-dd", endDate));
            var updatedBtn = housekeeping.getDtBtns("Housekeeping - " + labels.visitor + "s Checking In - " + $.datepicker.formatDate("M d, yy", startDate) + " to " + $.datepicker.formatDate("M d, yy", endDate))[0];
            $('#inTable').DataTable().button(0).remove().add(0, updatedBtn);
            $('#inTable').DataTable().ajax.reload();
            btn.addClass("ui-state-active");
        }
    });

    $('#ckoutDate').datepicker({
        yearRange: '-1:+01',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        numberOfMonths: 1,
        dateFormat: 'M d, yy',
        onClose: function (dateText) {
            var d = new Date(dateText);
            if (d != coDate) {
                coDate = d;
                $('#outTable').DataTable().ajax.url('ws_resc.php?cmd=cleanStat&tbl=outTable&stdte=' + $.datepicker.formatDate("yy-mm-dd", coDate) + '&enddte=' + $.datepicker.formatDate("yy-mm-dd", coDate));
                var updatedBtn = housekeeping.getDtBtns("Housekeeping - " + labels.visitor + "s Checking Out - " + $.datepicker.formatDate("M d, yy", coDate))[0];
                $('#outTable').DataTable().button(0).remove().add(0, updatedBtn);
                $('#outTable').DataTable().ajax.reload();
            }
        }
    });

    $('#ckoutDate').datepicker('setDate', coDate);

    $('#outButtonSet.week-button-group').on('click', 'button', function (e) {
        var btn = $(this)
        $('.week-button-group button').removeClass("ui-state-active");
        if (btn.data("weeks")) {
            var startDate = new Date();
            var endDate = new Date();
            endDate.setDate(startDate.getDate() + (btn.data("weeks") * 7));

            $('#outTable').DataTable().ajax.url('ws_resc.php?cmd=cleanStat&tbl=outTable&stdte=' + $.datepicker.formatDate("yy-mm-dd", startDate) + '&enddte=' + $.datepicker.formatDate("yy-mm-dd", endDate));
            var updatedBtn = housekeeping.getDtBtns("Housekeeping - " + labels.visitor + "s Checking Out - " + $.datepicker.formatDate("M d, yy", startDate) + " to " + $.datepicker.formatDate("M d, yy", endDate))[0];
            $('#outTable').DataTable().button(0).remove().add(0, updatedBtn);
            $('#outTable').DataTable().ajax.reload();
            btn.addClass("ui-state-active");
        }
    });


    $('#roomTable').dataTable({
        ajax: {
            url: 'ws_resc.php?cmd=cleanStat&tbl=roomTable',
            dataSrc: 'roomTable'
        },
        "deferRender": true,
        "columns": housekeeping.cgCols,
        rowGroup: {dataSrc: 'Group_Title'},
        "dom": '<"top"Bif><\"hhk-overflow-x\"rt><"bottom"lp>',
        "buttons": housekeeping.getDtBtns("Housekeeping - All Rooms"),
        "drawCallback": function(settings, json){
            $('.ckdate').datepicker({
                yearRange: startYear + ':+01',
                changeMonth: true,
                changeYear: true,
                autoSize: true,
                numberOfMonths: 1,
                maxDate: 0,
                dateFormat: 'M d, yy'
            });
        }
    });

    $('#dirtyTable').dataTable({
        'responsive':true,
        ajax: {
            url: 'ws_resc.php?cmd=cleanStat&tbl=dirtyTable',
            dataSrc: 'dirtyTable'
        },
        "deferRender": true,
        "columns": housekeeping.cgCols,
        rowGroup: {dataSrc: 'Group_Title'},
        "dom": '<"top"Bif><\"hhk-overflow-x\"rt><"bottom"lp>',
        "buttons": housekeeping.getDtBtns("Housekeeping - Rooms Not Ready"),
        "drawCallback": function(settings, json){
            $('.ckdate').datepicker({
                yearRange: startYear + ':+01',
                changeMonth: true,
                changeYear: true,
                autoSize: true,
                numberOfMonths: 1,
                maxDate: 0,
                dateFormat: 'M d, yy'
            });
        }
    });

    var outTbl = $('#outTable').DataTable({
        ajax: {
            url: 'ws_resc.php?cmd=cleanStat&tbl=outTable&stdte=' + $.datepicker.formatDate("yy-mm-dd", coDate) + '&enddte=' + $.datepicker.formatDate("yy-mm-dd", coDate),
            dataSrc: 'outTable'
        },
        "deferRender": true,
        "columns": housekeeping.outCols,
        "dom": '<"top"Bif><\"hhk-overflow-x\"rt><"bottom"lp>',
        "buttons": housekeeping.getDtBtns("Housekeeping - " + labels.visitor + "s Checking Out - " + $.datepicker.formatDate("M d, yy", coDate)),
    });
    outTbl.buttons().container().appendTo("#ckout .tbl-btns");

    var inTbl = $('#inTable').DataTable({
        ajax: {
            url: 'ws_resc.php?cmd=cleanStat&tbl=inTable&stdte=' + $.datepicker.formatDate("yy-mm-dd", coDate) + '&enddte=' + $.datepicker.formatDate("yy-mm-dd", coDate),
            dataSrc: 'inTable'
        },
        "deferRender": true,
        "columns": housekeeping.inCols,
        "dom": '<"top"Bif><\"hhk-overflow-x\"rt><"bottom"lp>',
        "buttons": housekeeping.getDtBtns("Housekeeping - " + labels.visitor + "s Checking In - " + $.datepicker.formatDate("M d, yy", coDate)),
    });

    inTbl.buttons().container().appendTo("#ckin .tbl-btns");

    $('#atblgetter').dataTable({
        'responsive':true,
        'columnDefs': [
            {'targets': [3, 4],
                'type': 'date',
                'render': function (data, type) {
                    return dateRender(data, type, dateFormat);
                }
            }
        ],
        "dom": '<"top"if><\"hhk-overflow-x\"rt><"bottom"lp>',
    });

    $('#outButtonSet').controlgroup();
    $('#inButtonSet').controlgroup();

    $('div#mainTabs').show();
});