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
var donationFilter = null;

/**
 * Apply (or clear, with null) the donations list filter and reload the table.
 *
 * @param {jQuery|null} $filter the filter section (#donFilter); its controls are posted with the list request
 */
function setDonationFilter($filter) {
    "use strict";

    donationFilter = null;

    if ($filter) {
        donationFilter = {donFilter: 1};

        // Collect into an object; "name[]" controls become arrays (posted as name[]=...).
        $.each($filter.find('select, input').serializeArray(), function (i, p) {
            if (p.name.slice(-2) === '[]') {
                var n = p.name.slice(0, -2);
                donationFilter[n] = donationFilter[n] || [];
                donationFilter[n].push(p.value);
            } else {
                donationFilter[p.name] = p.value;
            }
        });
    }

    if (donationTable) {
        donationTable.ajax.reload();
    }
}

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
                return $.extend({}, donationFilter, {cmd: 'list', id: id, sq: $('#squirm').val()});
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
                // One summary item per line, with the label in bold.
                var filterItems = json.filterItems || [];
                var items = [{label: 'Donor', value: json.donorName}, {label: 'Total', value: '$' + json.total}].concat(filterItems);
                $('#donSummary').empty().append($.map(items, function (item) {
                    return $('<div/>').append($('<strong/>').text(item.label + ':'), document.createTextNode(' ' + item.value));
                }));
                $('#donFilterState').text(filterItems.length ? '(' + $.map(filterItems, function (item) {
                    return item.label + ': ' + item.value;
                }).join('; ') + ')' : '');
                return json.data;
            }
        },
        columns: [
            {title: 'Actions', data: 'id', orderable: false, searchable: false, width: '60px', className: 'actionBtns', render: function (data, type, row) {
                if (type !== 'display') {
                    return '';
                }
                // Same markup as the notes viewer's action icons (js/notesViewer.js createActions).
                var did = parseInt(data, 10),
                    edit;

                if (row.editable) {
                    edit = '<li title="Edit Donation" data-did="' + did + '" class="hhk-donation-button donation-edit ui-corner-all ui-state-default">'
                        + '<span class="ui-icon ui-icon-pencil"></span></li>';
                } else {
                    edit = '<li title="Joint gift: edit it from the spouse\'s page" class="hhk-donation-button ui-corner-all ui-state-default ui-state-disabled">'
                        + '<span class="ui-icon ui-icon-pencil"></span></li>';
                }

                return '<ul class="ui-widget ui-helper-clearfix hhk-ui-icons hhk-flex">' + edit
                    + '<li title="Delete Donation" data-did="' + did + '" class="hhk-donation-button donation-delete ui-corner-all ui-state-default">'
                    + '<span class="ui-icon ui-icon-trash"></span></li></ul>';
            }},
            {title: 'Date', data: {_: 'dateDisplay', sort: 'date'}},
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
            {title: 'Note', data: 'note', render: $.fn.dataTable.render.text()},
            {title: 'Amount', data: 'amount', className: 'dt-right', render: function (data, type, row) {
                if (type !== 'display') {
                    return data;
                }
                var amt = '$' + Number(data).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                return row.inKind ? '<span style="font-style:italic;" title="In-kind; not included in the total">' + amt + '</span>' : amt;
            }}
        ],
        order: [[1, 'desc']],
        autoWidth: false,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        language: {emptyTable: 'No donations recorded.'},
        drawCallback: function () {
            $('#tblDonations .hhk-donation-button').not('.ui-state-disabled').button();
        },
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
                // Keep the summary's lines (its items are text-escaped divs).
                return $('#donSummary').html();
            },
            // Everything but the Actions column.
            exportOptions: {columns: [1, 2, 3, 4, 5]}
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

/**
 * Show error messages in the donation dialog's message box (one per line), or hide it when there are none.
 *
 * @param {string[]} errors
 */
function showDonationFormErrors(errors) {
    "use strict";

    if (!errors || errors.length === 0) {
        $('#donateResponseContainer').attr("style", "display:none;");
        return;
    }

    $('#donateResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
    $('#donateResponseContainer').attr("style", "display:block;");
    $('#donateResponseIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");

    var $msg = $('#donResultMessage').html("<strong>Error: </strong>");
    if (errors.length === 1) {
        $msg.append(document.createTextNode(errors[0]));
    } else {
        $msg.append($('<ul class="mb-0 pl-3"/>').append($.map(errors, function (e) {
            return $('<li/>').text(e);
        })));
    }
}

/**
 * Check the donation dialog before saving.  Mirrors the server's checks (Donation.php) so most
 * mistakes are caught without a round trip; the server still validates everything.
 *
 * @param {object} opts {maxDonate: number}
 * @returns {boolean} true when the form can be saved
 */
function validateDonationForm(opts) {
    "use strict";
    var errors = [],
        money = function (n) {
            return '$' + Number(n).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        },
        flag = function ($fld, msg) {
            $fld.addClass('ui-state-error');
            errors.push(msg);
        };

    $('#vdon .hhk-ajx-dondata').removeClass('ui-state-error');

    var $camp = $('#dselCamp');
    if (!$camp.val()) {
        flag($camp, 'Choose a campaign.');
    }

    var $payType = $('#dselPaytype');
    if (!$payType.val()) {
        flag($payType, 'Choose how the donation was paid.');
    }

    var $date = $('#ddate'), dateVal = $.trim($date.val());
    if (dateVal === '') {
        flag($date, 'Enter the donation date.');
    } else {
        try {
            $.datepicker.parseDate('M d, yy', dateVal);
        } catch (err) {
            flag($date, 'The date "' + dateVal + '" is not valid; use a date like ' + $.datepicker.formatDate('M d, yy', new Date()) + '.');
        }
    }

    var $amt = $('#damount'), amtVal = $.trim($amt.val()), amt = parseFloat(amtVal.replace(/[^0-9.]/g, ''));
    if (amtVal === '' || isNaN(amt) || amt <= 0) {
        flag($amt, 'Enter an amount greater than $0.');
    } else if (opts.maxDonate > 0 && amt > opts.maxDonate) {
        flag($amt, 'The amount can be at most ' + money(opts.maxDonate) + '.');
    } else {
        // Campaign range, loaded by getCampaign() when the campaign is chosen.  0 = no limit.
        var camp = $('#cLimits').data('camp');
        if (camp) {
            var min = parseFloat(camp.mindonation) || 0, max = parseFloat(camp.maxdonation) || 0;
            if ((min > 0 && amt < min) || (max > 0 && amt > max)) {
                flag($amt, 'The amount is outside this campaign\'s range (' + $('#cLimits').text() + ').');
            }
        }
    }

    showDonationFormErrors(errors);

    if (errors.length > 0) {
        $('#vdon .ui-state-error').first().trigger('focus');
    }

    return errors.length === 0;
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
        showDonationFormErrors([data.error]);

    } else {
        $('#vdon').dialog('close');
        flagAlertMessage(data.success || "Donation saved", false);

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

                // Keep the limits for validateDonationForm().
                amtLimit.removeData('camp');

                if (data.error) {
                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }
                    amtLimit.text('');
                } else if (data.camp) {
                    amtLimit.data('camp', data.camp);

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
            $(document).trigger('hhk-relation-changed', [data.rc]);
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
