function updateTips(tips, t, container = false) {
    tips.text(t).addClass("ui-state-highlight");
    if(container instanceof jQuery){
    	if(t.length > 0){
    		container.show();
    	}else{
    		container.hide();
    	}
    }
//    setTimeout(function() {
//        tips.removeClass( "ui-state-highlight", 360000 );
//    }, 500 );
}

function errorOnZero(o, n, tips) {
    "use strict";
    if (o.val() == "" || o.val() == "0" || o.val() == "00") {
        o.addClass("ui-state-error");
        updateTips(tips, n + " cannot be zero");
        return false;
    } else {
        return true;
    }
}

function checkLength(o, n, min, max, tips, container = false) {
    if (o.val().length > max || o.val().length < min) {
        o.addClass("ui-state-error");
        if (o.val().length == 0) {
            updateTips(tips, "Fill in the " + n, container);
        } else if (min == max) {
            updateTips(tips, "The " + n + " must be " + max + " characters.", container);
        } else if (o.val().length > max) {
            updateTips(tips, "The " + n + " length is to long", container);
        } else {
            updateTips(tips, "The " + n + " length must be between " + min + " and " + max + ".", container);
        }
        return false;
    } else {
        return true;
    }
}

function isNumber(n) {
    "use strict";
    return !isNaN(parseFloat(n)) && isFinite(n);
}

function changeMemberStatus(sc, memData, savePressed) {
    "use strict";

    // Set background color for user emphasis
    if (sc.val() == "p" || sc.val() == "TBD" || sc.val() == "u") {
        sc.css('background', "RED");
    } else if (sc.val() == 'in' || sc.val() == 'd') {
        sc.css('background', "YELLOW");
    } else {
        sc.css('background', "WHITE");
    }

}
var donationTable = null;
var donationDonorName = '';

function donEscape(s) {
    return $('<div/>').text(s == null ? '' : String(s)).html();
}

/**
 * Load (first call) or reload the donations DataTable on the NameEdit Donations tab.
 *
 * @param {number} id member id
 * @param {function} onNewDonation opens the record-donation dialog (used on first call only)
 */
function getDonationMarkup(id, onNewDonation) {
    "use strict";

    if (donationTable) {
        donationTable.ajax.reload();
        return;
    }

    donationTable = $('#tblDonations').DataTable({
        ajax: {
            url: 'donate.php',
            type: 'POST',
            data: function () {
                return {cmd: 'list', id: id, sq: $('#squirm').val()};
            },
            dataSrc: function (json) {
                if (json.error) {
                    if (json.gotopage) {
                        window.open(json.gotopage, '_self');
                    }
                    $('#donSummary').text('Error: ' + json.error);
                    return [];
                }
                donationDonorName = json.donorName;
                $('#donSummary').text('Donor: ' + json.donorName + ';  Total: $' + json.total);
                return json.data;
            }
        },
        columns: [
            {title: 'Source', data: 'src', width: '120px', render: function (data, type, row) {
                if (type !== 'display') {
                    return data;
                }
                if (row.careOfId > 0) {
                    return '<a href="NameEdit.php?id=' + row.careOfId + '" title="' + donEscape(row.srcTitle) + '">' + donEscape(row.careOfName) + '</a>';
                }
                return '<span title="' + donEscape(row.srcTitle) + '">' + donEscape(data) + '</span>';
            }},
            {title: 'Campaign', data: 'campaign', render: $.fn.dataTable.render.text()},
            {title: 'Amount', data: 'amount', className: 'dt-right', render: function (data, type, row) {
                if (type !== 'display') {
                    return data;
                }
                var amt = '$' + Number(data).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                return row.inKind ? '<span style="font-style:italic;" title="In-kind; not included in the total">' + amt + '</span>' : amt;
            }},
            {title: 'Date', data: {_: 'dateDisplay', sort: 'date'}},
            {title: 'Note', data: 'note', render: $.fn.dataTable.render.text()},
            {title: '', data: 'id', orderable: false, searchable: false, width: '30px', className: 'dt-center', render: function (data, type) {
                if (type !== 'display') {
                    return '';
                }
                return '<button type="button" class="ui-button ui-corner-all ui-widget hhk-undonate" data-did="' + parseInt(data, 10) + '" title="Delete Donation" aria-label="Delete Donation" style="padding:0.1em 0.4em;">'
                    + '<i class="bi bi-trash3"></i></button>';
            }}
        ],
        order: [[3, 'desc']],
        autoWidth: false,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        language: {emptyTable: 'No donations recorded.'},
        layout: {
            top2Start: 'buttons'
        },
        buttons: [{
            text: '<i class="bi bi-plus-lg mr-1"></i>New Donation',
            action: function () {
                if (typeof onNewDonation === 'function') {
                    onNewDonation();
                }
            }
        }, {
            extend: 'print',
            text: '<i class="bi bi-printer mr-1"></i>Print',
            title: function () {
                return 'Donations for ' + donationDonorName;
            },
            messageTop: function () {
                return $('#donSummary').text();
            },
            exportOptions: {columns: [0, 1, 2, 3, 4]}
        }]
    });
}

function donateDeleteMarkup(dataTxt, id) {
    "use strict";
    var data = $.parseJSON(dataTxt);

    if (data.gotopage) {
        window.open(data.gotopage, '_self');
    }

    if (data.error) {
        flagAlertMessage(data.error, true);
    } else {
        flagAlertMessage("Donation Deleted", false);
        getDonationMarkup(id);
    }
}

function donateResponse(dataTxt, id) {
    "use strict";
    var cbox, data;

    data = $.parseJSON(dataTxt);
    if (data.gotopage) {
        window.open(data.gotopage, '_self');
    }

    if (data.error) {
        // Keep the dialog open so the user can correct the entry.
        $('#donateResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
        $('#donateResponseContainer').attr("style", "display:block;");
        $('#donateResponseIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
        $('#donResultMessage').html("<strong>Error: </strong>").append(document.createTextNode(data.error));

    } else {
        $('#damount').val('');
        $('#dnote').val('');
        $('#vdon').dialog('close');
        flagAlertMessage("Donation recorded", false);

        // refresh the donations list.
        getDonationMarkup(id);

        // update the Member_Type.Donor record on the page if needed
        cbox = document.getElementById("Vol_Type_cb_d");
        if (cbox && !cbox.checked) {
            cbox.checked = 'checked';
        }
    }
}

function getCampaign(code) {
    "use strict";

    if (code != "") {
        $.post(
            "liveGetCamp.php",
            { qc: code },
            function (data) {
                data = $.parseJSON(data);
                var amtLimit = $('#cLimits');

                if (data.error) {
                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }
                    amtLimit.val('');
                } else if (data.camp) {

                    if (data.camp.mindonation == 0 && data.camp.maxdonation == 0) {
                        amtLimit.text("Any amount.");
                    } else if (data.camp.mindonation > 0 && data.camp.maxdonation == 0) {
                        amtLimit.text("At least $" + data.camp.mindonation);
                    } else if (data.camp.mindonation == 0 && data.camp.maxdonation > 0) {
                        amtLimit.text("At most $" + data.camp.maxdonation);
                    } else {
                        amtLimit.text("$" + data.camp.mindonation + " to $" + data.camp.maxdonation);
                    }
                    // Deal with scholarships
                    if (data.camp.type == 'sch' && $('#dselStudent option').length > 0) {
                        $('#dbdyStudent, #dhdrStudent').show();
                    } else {
                        $('#dbdyStudent, #dhdrStudent').hide();
                    }
                }
            }
        );
    }
}

function relationReturn(data) {

    data = $.parseJSON(data);
    if (data.error) {
        if (data.gotopage) {
            window.open(data.gotopage, '_self');
        }
        flagAlertMessage(data.error, true);
    } else if (data.success) {
        if (data.rc && data.markup) {
            var div = $('#acm' + data.rc);
            div.children().remove();
            var newDiv = $(data.markup);
            div.append(newDiv.children());
        }
        flagAlertMessage(data.success, false);
    }
}

function manageRelation(id, rId, relCode, cmd) {
    $.post('ws_gen.php', {'id':id, 'rId':rId, 'rc':relCode, 'cmd':cmd}, relationReturn);
}


var dtCols = [
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
        "title": "Type",
        "searchable": false,
        "orderable": false,
        "data": "Type"
    },
    {
        "targets": [ 2 ],
        "title": "Sub-Type",
        "searchable": false,
        "orderable": false,
        "data": "Sub-Type"
    },
     {
        "targets": [ 3 ],
        "title": "User",
        "searchable": false,
        "orderable": false,
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
        "orderable": false,
        "data": "Log Text"
    }

];
