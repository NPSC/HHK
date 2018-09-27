/**
 * memEdit.js
 *
 *
 * @category  member
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

//function $() {}
function updateTips(jqCtrl, text) {
    "use strict";
    jqCtrl.text(text).addClass("ui-state-highlight");
//    setTimeout(function() {
//        tips.removeClass( "ui-state-highlight", 360000 );
//    }, 500 );
}

function errorOnZero(o, n) {
    "use strict";
    if (o.val() == "" || o.val() == "0" || o.val() == "00") {
        o.addClass("ui-state-error");
        updateTips(n + " cannot be zero");
        return false;
    } else {
        return true;
    }
}

function checkLength(o, n, min, max) {
    "use strict";
    if (o.val().length > max || o.val().length < min) {
        o.addClass("ui-state-error");
        if (o.val().length == 0) {
            updateTips("Fill in the " + n);
        } else if (min == max) {
            updateTips("The " + n + " must be " + max + " characters.");
        } else if (o.val().length > max) {
            updateTips("The " + n + " length is to long");
        } else {
            updateTips("The " + n + " length must be between " + min + " and " + max + ".");
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
function alertCallback(containerId) {
    "use strict";
    setTimeout(function () {
        $("#" + containerId + ":visible").removeAttr("style").fadeOut(500);
    }, 3500
    );
}

function handleResponse(dataTxt, statusTxt, xhrObject) {
    "use strict";
    $('div.ui-dialog-buttonset').css("display", "block");
    if (statusTxt != "success") {
        alert('Server had a problem.  ' + xhrObject.status + ", " + xhrObject.responseText);
    } else {
        var data,  wasError = false, r = "", i, spn;

        data = $.parseJSON(dataTxt);
        for (i = 0; i < dataTxt.lenth; i++) {
            if (data[i].error) {
                wasError = true;
                r += data[i].error;
            }
        }

        spn = document.getElementById('webMessage');

        if (!wasError) {
            // define the error message markup
            $('#webResponse').removeClass("ui-state-error").addClass("ui-state-highlight");
            //$('#webContainer').attr("style", "display:block;");
            $('#webIcon').removeClass("ui-icon-alert").addClass("ui-icon-info");
            spn.innerHTML = "Okay";
            $("#webContainer").show("slide", {}, 700, alertCallback('webContainer'));
        } else {
            // define the success message markup
            $('webResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
            //$('#webContainer').attr("style", "display:block;");
            $('#webIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
            spn.innerHTML = "<strong>Error: </strong>" + r;
            $("#webContainer").show("slide", {}, 700, alertCallback('webContainer'));
        }
    }
}
function handleError(xhrObject, stat, thrwnError) {
    "use strict";
    $('div.ui-dialog-buttonset').css("display", "block");
    alert("Server error: " + stat + ", " + thrwnError);
}
//function flagCalAlertMessage(mess, wasError) {
//    "use strict";
//
//    var spn = document.getElementById('calMessage');
//
//    if (!wasError) {
//        // define the error message markup
//        $('#calResponse').removeClass("ui-state-error").addClass("ui-state-highlight");
//        $('#calIcon').removeClass("ui-icon-alert").addClass("ui-icon-info");
//        spn.innerHTML = "<strong>Success: </strong>" + mess;
//        $("#calContainer").show("slide", {}, 500, alertCallback('calContainer'));
//    } else {
//        // define the success message markup
//        $('calResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
//        $('#calIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
//        spn.innerHTML = "<strong>Alert: </strong>" + mess;
//        $("#calContainer").show("pulsate", {}, 200, alertCallback('calContainer'));
//    }
//}


// Init j-query.
$(document).ready(function () {
    "use strict";
    var memData = $.parseJSON('<?php echo $memDataJSON; ?>');

    $.ajaxSetup({
        beforeSend: function () {
            $('body').css('cursor', "wait");
        },
        complete: function () {
            $('body').css('cursor', "auto");
        },
        cache: false
    });
    $("#divFuncTabs").tabs({
        collapsible: true
    });
    // phone - email tabs block
    $('#phEmlTabs').tabs();
    $('#addrsTabs').tabs();
    $('#demographicTabs').tabs();
    $('#divVolInfoTabs').tabs();
    $(".hhk-hideStatus, .hhk-hideBasis").hide();
    // enable tabs for a "new" member
    if (memData.id == '0') {
        $('#phEmlTabs').tabs('option', 'active', 1);
        $('#phEmlTabs').tabs("option", "disabled", [0]);
    } else {
        // Existing member
        $('#addrsTabs').tabs('option', 'active', memData.addrPref - 1);
    }
    $('.ckdate').datepicker({
        yearRange: '-03:+05',
        changeMonth: true,
        changeYear: true
    });
    $('.ckbdate').datepicker({
        yearRange: '-99:+01',
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        dateFormat: 'M d, yy'
    });
    $('#divSubmitButtons').mouseover(function () {
        $(this).css("background-color", "white");
    });
    $('#divSubmitButtons').mouseout(function () {
        $(this).css("background-color", "transparent");
    });
    $('.prefPhone').each(function () {
        if (this.checked) {
            memData.phonePref = this.value;
        }
    });
    $('.prefEmail').each(function () {
        if (this.checked) {
            memData.emailPref = this.value;
        }
    });
    $('.ckzip').blur(function () {
        var txt, zipError;
        var postCode = /^(?:[A-Z]{1,2}[0-9][A-Z0-9]? [0-9][ABD-HJLNP-UW-Z]{2}|[ABCEGHJKLMNPRSTVXY][0-9][A-Z] [0-9][A-Z][0-9]|[0-9]{5}(?:\-[0-9]{4})?)$/i;
        txt = $(this).val();
        zipError = $('#w' + $(this).attr("id"));
        if (txt != "" && !postCode.test(txt)) {
            zipError.text("Bad Postal Code");
        } else {
            zipError.text("");
        }
    });
    $('#btnSubmit, #btnReset, #btnSendEmail').button();
    // Main form submit button dialog form for disabling page during POST
    $("#submit").dialog({
        autoOpen: false,
        resizable: false,
        modal: true
    });
    $('#emailDialog').dialog({
        autoOpen: false,
        resizable: true,
        width: 800,
        modal: true,
        buttons: {
            Cancel: function () {
                $(this).dialog("close");
            },
            "Send": function () {
                $('div#emailDialog').find('input, textarea').removeClass("ui-state-error");
                $('#emDialogMessage').text();
                if ($('#emailSubject').val() == '') {
                    // error - mo subject
                    $('#emailSubject').addClass("ui-state-error");
                    updateTips($('#emDialogMessage'), 'The subject line cannot be empty.')
                    return;
                } else if ($('#emailSubject').val().length > 70) {
                    $('#emailSubject').addClass("ui-state-error");
                    updateTips($('#emDialogMessage'), 'The subject line is too long.')
                    return;
                }
                if ($('#emailBody').val() == '') {
                    $('#emailBody').addClass("ui-state-error");
                    updateTips($('#emDialogMessage'), 'The message body cannot be empty.')
                    return;
                } else if ($('#emailBody').val().length > 3300) {
                    $('#emailBody').addClass("ui-state-error");
                    updateTips($('#emDialogMessage'), 'The message body is too long.')
                    return;
                }
                $.post('ws_vol.php', {cmd: 'sendEmail', vcc: $('#selVolGroup').val(), subj: $('#emailSubject').val(), body: $('#emailBody').val()},
                    function (data) {
                        try {
                            data = $.parseJSON(data);
                        } catch (e) {
                            alert("Decoding error.");
                        }

                        if (data.error) {
                            $('#alertMsg2').html("<strong>Error: </strong>" + data.error);
                            $('#divAlert2').show();
                        } else if (data.success) {
                            $('#alertMsg2').html("<strong>Info: </strong>" + data.success);
                            $('#divAlert2').show();
                            $('#emailBody').val();
                            $('#emailSubject').val();
                        }
                    }
                );
                $(this).dialog('close');
            }
        }

    });
    $('#btnSendEmail').click(function () {
        $('div#emailDialog').find('input, textarea').removeClass("ui-state-error");
        $('#emDialogMessage').text();
        $('#emailDialog').dialog('option', 'title', 'Write Email for: ' + '<?php echo $committeeTitle; ?>');
        $('#emailDialog').dialog('open');
    });
    // Main form submit button.  Disable page during POST
    $('#btnSubmit').click(function () {
        $('#submit').dialog("option", "title", "<h3> Saving... </h3>");
        $('#submit').dialog('open');
    });
    $('#selVolGroup').change(function () {
        if ($('#selVolGroup').val() != "") {
            $('#submit').dialog("option", "title", "<h3> Fetching Group " + $('#selVolGroup option:selected').text() + "</h3>");
            $('#submit').dialog('open');
            $('#groupSelectForm').submit();
        }
    });
    // Don't let user choose a blank address as preferred.'
    $('.addrPrefs').click(function () {
        var indx = this.value, adr1, cty, foundOne;

        adr1 = document.getElementById("adraddress1" + indx);
        cty = document.getElementById("adrcity" + indx);
        if ((adr1 != null && adr1.value == "") || (cty != null && cty.value == "")) {
            alert("This address is blank.  It cannot be the 'preferred' address.");
            this.checked = false;
            foundOne = false;

            // see if the old preferred phone has a number - then we check it and done.
            if (memData.addrPref != "" && $("#adraddress1" + memData.addrPref).val() != "") {
                $('#rbPrefMail' + memData.addrPref).prop('checked', true);
                foundOne = true;
            }

            if (!foundOne) {
                $('.addrPrefs').each(function () {
                    if ($("#adraddress1" + this.value).val() != "") {
                        $(this).prop('checked', true);
                        memData.addrPref = this.value;

                    }
                });
            }
        }
    });
    // enforce the Preferred phone number actually has a number
    $('input.prefPhone').change(function () {
        var foundOne, ctl = $("#txtPhone" + this.value);
        if (ctl !== null && ctl.val() == "") {
            alert("This Phone Number is blank.  It cannot be the 'preferred' phone number.");
            this.checked = false;
            foundOne = false;

            // see if the old preferred phone has a number - then we check it and done.
            if (memData.phonePref != "" && $("#txtPhone" + memData.phonePref).val() != "") {
                $('#ph' + memData.phonePref).prop('checked', true);
                foundOne = true;
            }

            // If we did not check a rb, then find one that has a textbox with info in it
            if (!foundOne) {
                $('.prefPhone').each(function () {
                    if ($("#txtPhone" + this.value).val() != "") {
                        $(this).prop('checked', true);
                        memData.phonePref = this.value;
                        return;
                    }
                });
            }
        }
    });
    // Enforce preferred Email has a value defined.
    $('input.prefEmail').change(function () {
        var ctl = $("#txtEmail" + this.value), foundOne;
        if (ctl != null && ctl.val() == "") {
            alert("This Email Address is blank.  It cannot be the 'preferred' Email address.");
            foundOne = false;
            this.checked = false;

            if (memData.emailPref != "" && $("#txtEmail" + memData.emailPref).val() != "") {
                $('#em' + memData.emailPref).prop('checked', true);
                foundOne = true;
            }

            // If we did not check a rb, then find one that has a textbox with info in it
            if (!foundOne) {
                $('.prefEmail').each(function () {
                    if ($("#txtEmail" + this.value).val() != "") {
                        $(this).prop('checked', true);
                        memData.emailPref = this.value;
                        return;
                    }
                });
            }
        }
    });
    $('input.hhk-emailInput').change(function () {
        // Inspect email text box input for correctness.
        var rexEmail = /^[A-Z0-9._%+\-]+@(?:[A-Z0-9]+\.)+[A-Z]{2,4}$/i;
        $('#emailWarning').text("");
        // each email input control
        $('input.hhk-emailInput').each(function () {
            if ($(this).val() != '' && rexEmail.test($(this).val()) === false) {
                $(this).next('span').text("*");
                $('#emailWarning').text("Incorrect Email Address");

            } else {
                $(this).next('span').text("");
            }
        });
    });
    $('input.hhk-phoneInput').change(function () {
        // inspect each phone number text box for correctness
        var testreg = /^([\(]{1}[0-9]{3}[\)]{1}[\.| |\-]{0,1}|^[0-9]{3}[\.|\-| ]?)?[0-9]{3}(\.|\-| )?[0-9]{4}$/;
        var regexp = /^(?:(?:[\+]?([\d]{1,3}(?:[ ]+|[\-.])))?[(]?([2-9][\d]{2})[\-\/)]?(?:[ ]+)?)?([2-9][0-9]{2})[\-.\/)]?(?:[ ]+)?([\d]{4})(?:(?:[ ]+|[xX]|(i:ext[\.]?)){1,2}([\d]{1,5}))?$/;
        var numarry;
        $('#phoneWarning').text("");
        $('input.hhk-phoneInput').each(function () {
            if ($(this).val() != '' && testreg.test($(this).val()) === false) {
                $(this).nextAll('span').text("*");
                $('#phoneWarning').text("Incorrect Phone Number");

            } else {
                $(this).nextAll('span').text("");
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
    $('input.hhk-phoneInput').change();
    $('input.hhk-emailInput').change();
    $('input.hhk-check-button').click(function () {
    if ($(this).prop('id') == 'exAll') {
        $('input.hhk-ex').prop('checked', true);
    } else {
        $('input.hhk-ex').prop('checked', false);
    }
});

    // Member search letter input box
    $('#txtsearch').keypress(function (event) {
        var mm = $(this).val();
        if (event.keyCode == '13' && (mm == '' || !isNumber(parseInt(mm, 10)))) {
            alert("Don't press the return key unless you enter an Id.");
            event.preventDefault();
        }
    });
    $('#txtsearch').autocomplete({
        source: function (request, response) {
            var gvcc = $('#selVolGroup').val();
            var inpt = {
                cmd: "filter",
                letters: request.term,
                basis: "m",

                filter: gvcc
            };
            $.getJSON("VolNameSearch.php", inpt,
                function(data, status, xhr) {
                    if (data.error) {
                        data = [{"value" : data.error}];
                    }
                    response(data);
                }
            );
        },
        minLength: 3,
        select: function( event, ui ) {
            if (ui.item && ui.item.id > 0) {
                window.location = "MemEdit.php?id=" + ui.item.id + "&vg=" + $('#selVolGroup').val();
            }
        }
    });
    $('.hhk-showonload').show();
});
// End of jQuery