<?php
/**
 * Waitlist.php
 *
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
require("homeIncludes.php");


require(CLASSES . 'History.php');
require(CLASSES . 'Notes.php');
require(HOUSE . 'Waitlist.php');



try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

$dbh = $wInit->dbh;
$pageTitle = $wInit->pageTitle;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();
$isGuestAdmin = ComponentAuthClass::is_Authorized('guestadmin');

// Load the session with member - based lookups
$wInit->sessionLoadGenLkUps();
$wInit->sessionLoadGuestLkUps();

// Instantiate the alert message control
$alertMsg = new alertMessage("divAlert1");
$alertMsg->set_DisplayAttr("none");
$alertMsg->set_Context(alertMessage::Success);
$alertMsg->set_iconId("alrIcon");
$alertMsg->set_styleId("alrResponse");
$alertMsg->set_txtSpanId("alrMessage");
$alertMsg->set_Text("f");

$resultMessage = $alertMsg->createMarkup();

$wlMarkup = Waitlist::createDialog(
        array(),
        $uS->guestLookups[GL_TableNames::Hospital],
        $uS->guestLookups[GL_TableNames::WL_Status]
        );


?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
    <?php echo JQ_UI_CSS; ?>
        <?php echo JQ_DT_CSS ?>
    <?php echo TOP_NAV_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript">
function isNumber(n) {
    "use strict";
    return !isNaN(parseFloat(n)) && isFinite(n);
}
function alertCallback(containerId) {
    "use strict";
    setTimeout(function () {
        $("#" + containerId + ":visible").removeAttr("style").fadeOut(500);
    }, 3500
    );
}
function flagAlertMessage(mess, wasError) {
    "use strict";
    var spn = document.getElementById('alrMessage');

    if (!wasError) {
        // define the error message markup
        $('#alrResponse').removeClass("ui-state-error").addClass("ui-state-highlight");
        $('#alrIcon').removeClass("ui-icon-alert").addClass("ui-icon-info");
        spn.innerHTML = "<strong>Success: </strong>" + mess;
        $("#divAlert1").show("slide", {}, 500, alertCallback('divAlert1'));
    } else {
        // define the success message markup
        $('alrResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
        $('#alrIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
        spn.innerHTML = "<strong>Alert: </strong>" + mess;
        $("#divAlert1").show("pulsate", {}, 200, alertCallback('divAlert1'));
    }
}
var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', ];
var waitListTable;
var dtColsCounter = 0;
var dtCols = [
    {
        "aTargets": [ dtColsCounter++ ],
        "sTitle": "Action",
        "bSearchable": false,
        "bSortable": true,
        "sWidth": "70px",
        "mDataProp": "Action"
    },
    {
        "aTargets": [ dtColsCounter++ ],
        "sTitle": "Status",
        "bSearchable": false,
        "bSortable": true,

        "mDataProp": "Status_Title"
    },
    {
        "aTargets": [ dtColsCounter++ ],
        "sTitle": "Date Entered",
        "sType": "date",
        "bSortable": true,
        "mDataProp": function (source, type, val) {
            "use strict";
            if (type === 'set') {
                source.Timestamp = val;
                return null;
            } else if (type === 'display') {
                if (source.Timestamp_display === undefined) {
                    var dt = new Date(Date.parse(source.Timestamp));
                    source.Timestamp_display = (months[dt.getMonth()]) + ' ' + dt.getDate() + ', ' + dt.getFullYear();
                }
                return source.Timestamp_display;
            }
            return source.Timestamp;
        }
    },
    {
        "aTargets": [ dtColsCounter++ ],
        "sTitle": "Patient",
        "bSortable": true,
        "mDataProp": "Patient_Name"
    },
    {
        "aTargets": [ dtColsCounter++ ],
        "sTitle": "Guest",
        "bSortable": true,
        "mDataProp": "Guest_Name"
    },
    {
        "aTargets": [ dtColsCounter++ ],
        "sTitle": "Arrival",
        "sType": "date",
        "bSortable": true,
        "mDataProp": function (source, type, val) {
            "use strict";
            if (type === 'set') {
                source.Arrival_Date = val;
                return null;
            } else if (type === 'display') {
                if (source.Arrival_Date_display === undefined) {
                    var dt = new Date(Date.parse(source.Arrival_Date));
                    source.Arrival_Date_display = (months[dt.getMonth()]) + ' ' + dt.getDate() + ', ' + dt.getFullYear();
                }
                return source.Arrival_Date_display;
            }
            return source.Arrival_Date;
        }
    },
    {
        "aTargets": [ dtColsCounter++ ],
        "sTitle": "Days",

        "bSearchable": false,
        "bSortable": false,
        "mDataProp": "Expected_Duration"
    },
    {
        "aTargets": [ dtColsCounter++ ],
        "sTitle": "Phone",
        "bSearchable": false,
        "bSortable": false,
        "mDataProp": "Phone"
    },
    {
        "aTargets": [ dtColsCounter++ ],
        "sTitle": "Notes",
        "sWidth": "400px",
        "bVisible": true,
        "bSearchable": false,
        "bSortable": false,
        "mDataProp": "Notes"
    },
    {
        "aTargets": [ dtColsCounter++ ],
        "bVisible": false,
        "bSearchable": false,
        "bSortable": false,
        "mDataProp": "Status"
    }
];
function wlEdit(cmd, wlId) {

    // get data from dtables
    if (waitListTable) {

        if (cmd == 5) {
            // Edit
            var row, i, obh = waitListTable.fnGetData();
            for (i=0; i<obh.length; i++){
                if (obh[i].idWaitlist == wlId) {
                    row = obh[i];
                }
            }
            $('#wlValidate').text('').removeClass('ui-state-highlight');
            $('input.wsDiag').removeClass('ui-state-highlight');
            $('#wlpName').parent().parent().hide();
            $('#wlgName').parent().parent().hide();

            $('#selwlStatus').val(row.Status);
            var dt;
            if (row.Timestamp) {
                dt = new Date(Date.parse(row.Timestamp));
            } else {
                dt = new Date();
            }
            $('#wlDate').val((dt.getMonth() + 1) + '/' + dt.getDate() + '/' + dt.getFullYear());
            $('#wlpLast').val(row.Patient_Last);
            $('#wlpFirst').val(row.Patient_First);
            $('#wlgLast').val(row.Guest_Last);
            $('#wlgFirst').val(row.Guest_First);
            $('#selwlHospital').val(row.Hospital);
            dt = new Date(Date.parse(row.Arrival_Date));
            $('#arDate').val((dt.getMonth() + 1) + '/' + dt.getDate() + '/' + dt.getFullYear());
            $('#wlDays').val(row.Expected_Duration);
            $('#wlPhone').val(row.Phone);
            $('#wlEmail').val(row.Email);
            $('#wlAdult').val(row.Number_Adults);
            $('#wlChild').val(row.Number_Children);
//            $('#selwlFinalStatus').val(row.Final_Status);
//            $('#wlfsDate').val(row.Final_Status_Date);
            if (row.Notes === null) {
                $('#hhk-existgNotes').text('');
            } else {
                $('#hhk-existgNotes').html(row.Notes);
            }
            $('#idWL').val(row.idWaitlist);
            $('#wlIdGuest').val(row.idGuest);
            $('#wlIdPatient').val(row.idPatient);

            $('#newWaitDialog').dialog("option", "title", "Edit Anticipated Visit");
            $('#newWaitDialog').dialog('open');
        } else if (cmd == 3) {
            // Checkin
            window.location = 'CheckIn.php?idWL=' + wlId;
        } else if (cmd == 2) {
            // Delete entry
            if (confirm("Delete this anticipated visit?")) {
                $.post('ws_ckin.php', {
                    cmd: 'delWL',
                    wlid: wlId
                },
                function (data) {
                    if (data) {
                        try {
                            data = $.parseJSON(data);
                        } catch (err) {
                            alert("Parser error - " + err.message);
                            return;
                        }
                        if (data.error) {
                            alert("Server error - " + data.error);
                        } else if (data.success) {
                            // fire a waitlist dataTables reload
                            if (waitListTable) {
                                waitListTable.fnDraw();
                            }
                            flagAlertMessage('Record deleted.', false);
                        }
                    }
                });
            }
        }
    }
}

$(document).ready(function () {
    "use strict";
    var d=new Date();
    var wsAddress = 'ws_ckin.php';
    var wListJSON = wsAddress + '?cmd=wlist&ao=1';
    var eventJSONString = wsAddress + '?cmd=resvlist';
    var lastXhr;
    $.ajaxSetup({
        beforeSend: function () {
            $('body').css('cursor', "wait");
        },
        complete: function () {
            $('body').css('cursor', "auto");
        },
        cache: false
    });
    $('#cbActiveOnly').change(function () {
        if (this.checked) {
            wListJSON = wsAddress + '?cmd=wlist&ao=1';
        } else {
            wListJSON = wsAddress + '?cmd=wlist';
        }
        if (waitListTable) {
            var oSettings = waitListTable.fnSettings();
            oSettings.sAjaxSource = wListJSON;

            waitListTable.fnDraw();
        }
    });
    waitListTable = $('#dataTbl').dataTable({
        "aoColumnDefs": dtCols,
        "fnDrawCallback": function () {
            $('.wlmenu').menu();
        },
        "bServerSide": true,
        "bProcessing": true,
        "bDeferRender": true,
        "sServerMethod": "POST",
        "iDisplayLength": 15,
        "aLengthMenu": [[15, 30, -1], [15, 30, "All"]],
        "sDom": '<"top ignrSave"ilp>rt<"bottom"p>',
        "sAjaxSource": wListJSON
    });
    $('#btnNewWl').button();
    $('.ckdate').datepicker({
        yearRange: '-02:+03',
        changeMonth: true,
        changeYear: true,
        numberOfMonths: 3,
        autoSize: true
    });
    $('#newWaitDialog').dialog({
        autoOpen: false,
        width: 575,
        resizable: true,
        modal: true,
        buttons: {
            "Save": function () {
                // Last Name must be set...
                if ($('#wlgLast').val() == '') {
                    $('#wlValidate').text('The Guest Last Name must be filled in.').addClass('ui-state-highlight');
                    $('#wlgLast').addClass('ui-state-highlight');
                    return;
                } else {
                    $('#wlValidate').text('').removeClass('ui-state-highlight');
                    $('#wlgLast').removeClass('ui-state-highlight');
                }
                if ($('#arDate').val() == '') {
                    $('#wlValidate').text('The Stay Date must be filled in.').addClass('ui-state-highlight');
                    $('#arDate').addClass('ui-state-highlight');
                    return;
                } else {
                    $('#wlValidate').text('').removeClass('ui-state-highlight');
                    $('#arDate').removeClass('ui-state-highlight');
                }
                if ($('#wlPhone').val() == '' && $('#wlEmail').val() == '') {
                    $('#wlValidate').text('The Phone or Email must be filled in.').addClass('ui-state-highlight');
                    if ($('#wlPhone').val() == '') {
                        $('#wlPhone').addClass('ui-state-highlight');
                    }
                    if ($('#wlEmail').val() == ''){
                        $('#wlEmail').addClass('ui-state-highlight');
                    }
                    return;
                } else {
                    $('#wlValidate').text('').removeClass('ui-state-highlight');
                    $('#wlEmail').removeClass('ui-state-highlight');
                    $('#wlPhone').removeClass('ui-state-highlight');
                }
                var parms = {};
                $('div#newWaitDialog').find('.wsDiag').each(function () {
                    parms[$(this).attr('id')] = $(this).val();
                });
                $.post(wsAddress, {
                    cmd: 'addWL',
                    parm: parms
                }, function (data) {
                    if (data) {
                        try {
                            data = $.parseJSON(data);
                        } catch (err) {
                            alert("Parser error - " + err.message);
                            return;
                        }
                        if (data.error) {
                            alert("Server error - " + data.error);
                        } else if (data.success) {
                            // fire a waitlist dataTables reload
                            if (waitListTable) {
                                waitListTable.fnDraw();
                            }
                            flagAlertMessage('Record saved.', false);
                        }
                    }
                });
                $('#wlNotes').val('');
                $(this).dialog('close');
            },
            "Cancel": function () {
                $(this).dialog("close");
            }
        },
        close: function () {}
    });
    $('#btnNewWl').click(function () {
        $('#wlValidate').text('').removeClass('ui-state-highlight');
        $('input.wsDiag').removeClass('ui-state-highlight');
        $('input.wsDiag, select.wsDiag').val('');
        $('#hhk-existgNotes').text('');
        $('#wlDate').val(new Date().toDateString());
        //$('div#newWaitDialog').find('tr.hide-new').hide();
        $('#wlpName').parent().parent().show();
        $('#wlgName').parent().parent().show();
        $('#newWaitDialog').dialog("option", "title", "New Anticipated Visit");
        $('#newWaitDialog').dialog('open');
    });
    $('input.number-only').change(function () {
        if (isNumber(this.value) === false) {
            $(this).val('0');
        }
        $(this).val(parseInt(this.value));
    });
    // Email input field verifyer
    $('input.hhk-emailInput').change(function () {
        // Inspect email text box input for correctness.
        var rexEmail = /^[A-Z0-9._%+\-]+@(?:[A-Z0-9]+\.)+[A-Z]{2,4}$/i;
        $('#emailWarning').text("");
        // each email input control
        $('input.hhk-emailInput').each(function () {
            if ($.trim($(this).val()) != '' && rexEmail.test($(this).val()) === false) {
                $(this).next('span').text("*");
                $('#emailWarning').text("Incorrect Email Address");

            } else {
                $(this).next('span').text("");
            }
        });
    });
    // Phone input field verifier
    $('input.hhk-phoneInput').change(function () {
        // inspect each phone number text box for correctness
        var testreg = /^([\(]{1}[0-9]{3}[\)]{1}[\.| |\-]{0,1}|^[0-9]{3}[\.|\-| ]?)?[0-9]{3}(\.|\-| )?[0-9]{4}$/;
        var regexp = /^(?:(?:[\+]?([\d]{1,3}(?:[ ]+|[\-.])))?[(]?([2-9][\d]{2})[\-\/)]?(?:[ ]+)?)?([2-9][0-9]{2})[\-.\/)]?(?:[ ]+)?([\d]{4})(?:(?:[ ]+|[xX]|(i:ext[\.]?)){1,2}([\d]{1,5}))?$/;
        var numarry;
        $('#phoneWarning').text("");
        $('input.hhk-phoneInput').each(function () {
            if ($.trim($(this).val()) != '' && testreg.test($(this).val()) === false) {
                $(this).nextAll('span').show();
                $('#phoneWarning').text("Incorrect Phone Number");

            } else {
                $(this).nextAll('span').hide();
                regexp.lastIndex = 0;
                // 0 = matached, 1 = 1st capturing group, 2 = 2nd, etc.
                numarry = regexp.exec($(this).val());
                if (numarry != null && numarry.length > 3) {
                    var ph = "";
                    // Country code?
                    if (numarry[1] != null && numarry[1] != "") {
                        ph = '+' + numarry[1];
                    }
                    // The main part
                    $(this).val(ph + '(' + numarry[2] + ') ' + numarry[3] + '-' + numarry[4]);
                    // Extension?
                    if (numarry[6] != null && numarry[6] != "") {
                        $(this).next('input').val(numarry[6]);
                    }
                }
            }
        });
    });
    // Run hte verifiers on existing email and phone data.
    $('input.hhk-phoneInput').change();
    $('input.hhk-emailInput').change();
    $('#wlgName').autocomplete({
        source: function (request, response) {
            var inpt = {
                cmd: "filter",
                letters: request.term,
                basis: "g"
            };
            lastXhr = $.getJSON("roleSearch.php", inpt,
                function(data, status, xhr) {
                    if (xhr === lastXhr) {
                        if (data.error) {
                            data.value = data.error;
                        }
                        response(data);
                    } else {
                        response();
                    }
                }
            );
        },
        minLength: 3,
        select: function( event, ui ) {
            if (!ui.item) {
                return;
            }
            $('#wlIdGuest').val(ui.item.id);
            $('#hhk_processing').show();

            if (ui.item.id > 0) {
                // Load names
                var parms = {
                    cmd: 'getWLname',
                    id: ui.item.id,
                    role: 'g'
                };
                $.getJSON(
                    'ws_ckin.php',
                    parms,
                    function (data) {
                        $('#hhk_processing').hide();
                        if (!data) {
                            alert('Bad Reply from Server');
                            return;
                        }
                        if (data.error) {
                            flagAlertMessage(data.error, true);
                            return;
                        }
                        if (data.g) {
                            $('#wlgLast').val(data.g.last);
                            $('#wlgFirst').val(data.g.first);
                            $('#wlPhone').val(data.g.phone);
                            $('#wlEmail').val(data.g.email);
                            $('#wlgName').parent().parent().hide();
                        }
                        if (data.p && data.p.id > 0) {
                            $('#wlIdPatient').val(data.p.id);
                            $('#wlpLast').val(data.p.last);
                            $('#wlpFirst').val(data.p.first);
                            $('#selwlHospital').val(data.hospital);
                            $('#wlpName').parent().parent().hide();
                        }
                    }
                );
            }
        }
    });
    $('#wlpName').autocomplete({
        source: function (request, response) {
            var inpt = {
                cmd: "filter",
                letters: request.term,
                basis: "p"
            };
            lastXhr = $.getJSON("roleSearch.php", inpt,
                function(data, status, xhr) {
                    if (xhr === lastXhr) {
                        if (data.error) {
                            data.value = data.error;
                        }
                        response(data);
                    } else {
                        response();
                    }
                }
            );
        },
        minLength: 3,
        select: function( event, ui ) {
            if (!ui.item) {
                return;
            }
            $('#hhk_processing').show();
            $('#wlIdPatient').val(ui.item.id);
            if (ui.item.id > 0) {
                // Load names
                var parms = {
                    cmd: 'getWLname',
                    id: ui.item.id,
                    role: 'p'
                };
                $.getJSON(
                    'ws_ckin.php',
                    parms,
                    function (data) {
                        $('#hhk_processing').hide();
                        if (!data) {
                            alert('Bad Reply from Server');
                            return;
                        }
                        if (data.error) {
                            flagAlertMessage(data.error, true);
                            return;
                        }
                        if (data.p) {
                            $('#wlIdPatient').val(data.p.id);
                            $('#wlpLast').val(data.p.last);
                            $('#wlpFirst').val(data.p.first);
                            $('#selwlHospital').val(data.hospital);
                            $('#wlpName').parent().parent().hide();
                        }
                    }
                );
            }
        }
    });
});
    </script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <div style="float:left; margin-right: 100px; margin-top:10px;">
                <h1><?php echo $wInit->pageHeading; ?></h1>
            </div>
            <div style="clear:both;"></div>
            <?php echo $resultMessage ?>
            <div id="waitList" class="ui-widget ui-widget-content ui-corner-all hhk-tdbox  hhk-member-detail hhk-visitdialog" style="padding:10px; font-size: .9em; clear:left;">
                <input type="button" id="btnNewWl" value="New Entry" style="margin-bottom: 10px;"/>
                <label style="margin-left:1em;"><input type="checkbox" id="cbActiveOnly" checked="checked"/> Only show Active records</label>
                <table cellpadding="0" cellspacing="0" border="0" class="display" id="dataTbl"></table>
            </div>
        </div>  <!-- div id="contentDiv"-->
        <div id="newWaitDialog" style="display:none; font-size: .9em;" class="hhk-tdbox">
            <div id="hhk_processing" class="dataTables_processing" style="visibility: hidden;">Processing...</div>
            <?php echo $wlMarkup; ?>
        </div>
    </body>
</html>
