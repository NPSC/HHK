function updateTips(tips, t) {
    "use strict";
    tips.text(t).addClass("ui-state-highlight");
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

function checkLength(o, n, min, max, tips) {
    "use strict";
    if (o.val().length > max || o.val().length < min) {
        o.addClass("ui-state-error");
        if (o.val().length == 0) {
            updateTips(tips, "Fill in the " + n);
        } else if (min == max) {
            updateTips(tips, "The " + n + " must be " + max + " characters.");
        } else if (o.val().length > max) {
            updateTips(tips, "The " + n + " length is to long");
        } else {
            updateTips(tips, "The " + n + " length must be between " + min + " and " + max + ".");
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
function getDonationMarkup(id) {
    "use strict";
    $.post(
        "donate.php",
        {
            id: id,
            sq: $("#squirm").val(),
            cmd: 'markup'
        },
        function (data) {
            var msg;
            data = $.parseJSON(data);

	        if (data.error) {
	            if (data.gotopage) {
	                window.open(data.gotopage, '_self');
	            }
	            msg = data.error;
	
	        } else {
	            msg = data.success;
	        }
	        
	        $('#divListDonation').children().remove();
	        $("#divListDonation").append($(msg));
	        
	        $('.hhk-edit-donation').on('click', function () {
	        	var idDonation = $(this).data('idDonation');
	        	alert('Not implemented yet.');
	        });

        });
}
function donateDeleteMarkup(dataTxt, id) {
    "use strict";
    var spn, data;

    data = $.parseJSON(dataTxt);
    if (data.gotopage) {
        window.open(data.gotopage, '_self');
    }

    spn = document.getElementById('donResultMessage');
    document.getElementById('damount').value = "";

    if (data.error) {
        // define the error message markup
        $('#donateResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
        $('#donateResponseContainer').attr("style", "display:block;");
        $('#donateResponseIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
        spn.innerHTML = "<strong>Error: </strong>" + data.error;

    } else {
        // define the success message markup
        $('#donateResponse').removeClass("ui-state-error").addClass("ui-state-highlight");
        $('#donateResponseContainer').attr("style", "display:block;");
        $('#donateResponseIcon').removeClass("ui-icon-alert").addClass("ui-icon-info");
        spn.innerHTML = "Donation Deleted";

        // create the markup for the new donations list.
        getDonationMarkup(id);
    }
}

function donateResponse(dataTxt, id) {
    "use strict";
    var spn, cbox, data;

        data = $.parseJSON(dataTxt);
        if (data.gotopage) {
            window.open(data.gotopage, '_self');
        }

        spn = document.getElementById('donResultMessage');
        $('#damount').val('');
        $('#dnote').val('');

        if (data.error) {
            // define the error message markup
            $('#donateResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
            $('#donateResponseContainer').attr("style", "display:block;");
            $('#donateResponseIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
            spn.innerHTML = "<strong>Error: </strong>" + data.error;

        } else {
            // define the success message markup
            $('#donateResponse').removeClass("ui-state-error").addClass("ui-state-highlight");
            $('#donateResponseContainer').attr("style", "display:block;");
            $('#donateResponseIcon').removeClass("ui-icon-alert").addClass("ui-icon-info");
            spn.innerHTML = "Okay";

            // create the markup for the new donations list.
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
                        amtLimit.val("Any amount.");
                    } else if (data.camp.mindonation > 0 && data.camp.maxdonation == 0) {
                        amtLimit.val("At least $" + data.camp.mindonation);
                    } else if (data.camp.mindonation == 0 && data.camp.maxdonation > 0) {
                        amtLimit.val("At most $" + data.camp.maxdonation);
                    } else {
                        amtLimit.val("$" + data.camp.mindonation + " to $" + data.camp.maxdonation);
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
        "sortable": false,
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
