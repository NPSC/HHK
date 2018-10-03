/**
 * vNameEdit.js
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

function isNumber(n) {
    "use strict";
    return !isNaN(parseFloat(n)) && isFinite(n);
}

function alertCallback(alertId) {
    "use strict";
    setTimeout(function () {
        $("#" + alertId).removeAttr("style").fadeOut(500);
    }, 3000);
}

//function flagAlertMessage(alertPkg, msg, context) {
//    "use strict";
//
//    $('#' + alertPkg.Id_Style).removeClass().addClass(alertPkg.States[context]);
//    $('#' + alertPkg.Id_Icon).removeClass().addClass(alertPkg.Icons[context]);
//    $('#' + alertPkg.Id_Message).val(msg);
//    $('#' + alertPkg.Id_Control).show('slide', {}, 500,
//        setTimeout(function () {
//            $("#" + alertPkg.Id_Control).removeAttr("style").fadeOut(500);
//        }, 3000)
//    );

//    if (!wasError) {
//        // define the error message markup
//        $('#calResponse').removeClass("ui-state-error").addClass("ui-state-highlight");
//        $('#calIcon').removeClass("ui-icon-alert").addClass("ui-icon-info");
//        spn.innerHTML = "<strong>Success: </strong>" + $mess;
//        $("#calContainer").show("slide", {}, 500, alertCallback);
//    } else {
//        // define the success message markup
//        $('calResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
//        $('#calIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
//        spn.innerHTML = "<strong>Alert: </strong>" + $mess;
//        $("#calContainer").show("pulsate", {}, 200, alertCallback);
//    }
//}

function handleResponse(data, statusTxt, xhrObject) {
    "use strict";
    if (buttonWaiting) {
        buttonWaiting.css('cursor', btnWaitingCursor);
    }
    if (statusTxt != "success") {
        alert('Server had a problem.  ' + xhrObject.status + ", " + xhrObject.responseText);
    }
    var dataObj, txt, title;

    if (data) {
        try {
            dataObj = $.parseJSON(data);
        } catch (err) {
            txt = "There was an error on this page.\n\n";
            txt += "Error description: " + err.message + "\n\n";
            txt += "Click OK to continue.\n\n";
            alert(txt);
            return;
        }

        if (dataObj.error) {
            alert('Application Error');
        } else if (dataObj.title) {
            listTable.fnAddData(dataObj.data);
            if (dataObj.title) {
                title = dataObj.title;
            }
            $('#dListmembers').dialog("option", "title", "Contacts Listing for " + title);
            $('#dListmembers').dialog("open");
        }
    }
}
function handleChangePW(pwAlertPkg, data, statusTxt, xhrObject) {
    "use strict";
    //$('#submit').dialog( 'close' );
    if (statusTxt != "success") {
        alert('Server had a problem.  ' + xhrObject.status + ", " + xhrObject.responseText);
    }
    var dataObj;

    if (data) {
        try{
            dataObj = $.parseJSON(data);
        } catch (err) {
            alert('Data Parse Error');
            return;
        }
        if (dataObj.success) {
            // clear text boxes
            $('#txtNewPw2').val('');
            $('#txtNewPw1').val('');

            $('#dchgPw').dialog("close");
            flagAlertMessage(pwAlertPkg, dataObj.success, pwAlertPkg.Success);

        } else if (dataObj.error) {
            $('#pwChangeErrMsg').text(dataObj.error);
        }
    }
}

function handleError(xhrObject, stat, thrwnError) {
    "use strict";
    alert("Server error: " + stat + ", " + thrwnError);
}
function noenter(event) {
    "use strict";
    if ((event && event.which == 13) || (window.event && window.event.keyCode == 13)) {
        return false;
    } else {
        return true;
    }
}
function submitDoc(strbutton) {
    "use strict";
    var getCBtn = document.getElementById(strbutton);
    getCBtn.click();
}
// Init j-query.
$(document).ready(function () {
    "use strict";
    var memData = $.parseJSON('<?php echo $memDataJSON; ?>');
    var pwAlertPkg = $.parseJSON('<?php echo $pwAlertJSON; ?>');
    $.ajaxSetup({
        beforeSend: function () {
            //$('#loader').show()
            $('body').css('cursor', "wait");
        },
        complete: function () {
            $('body').css('cursor', "auto");
        //$('#loader').hide()
        },
        cache: false
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
        var postCode = /^(?:[A-Z]{1,2}[0-9][A-Z0-9]? [0-9][ABD-HJLNP-UW-Z]{2}|[ABCEGHJKLMNPRSTVXY][0-9][A-Z] [0-9][A-Z][0-9]|[0-9]{5}(?:-[0-9]{4})?)$/i;
        var txt, zipError;
        txt = $(this).val();
        zipError = $('#w' + $(this).attr("id"));
        if (txt != "" && !postCode.test(txt)) {
            zipError.text("Bad Postal Code");
        } else {
            zipError.text("");
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
    $('#chgPW').click(function () {
        $('div#dchgPw').find('input').removeClass("ui-state-error").val('');
        $('#pwChangeErrMsg').text('');

        $('#dchgPw').dialog("option", "title", "Change Your Password");
        $('#dchgPw').dialog('open');
        document.getElementById('txtOldPw').focus();
    });
    $('#dchgPw').dialog({
        autoOpen: false,
        width: 500,
        resizable: true,
        modal: true,
        buttons: {
            "Save": function () {
                $('div#dchgPw').find('input').removeClass("ui-state-error");
                var oldpw, pw1, oldpwMD5, newpwMD5;
                $('#pwChangeErrMsg').text('');
                $('#pwChangeErrMsg').addClass('ui-state-highlight');

                oldpw = $('#txtOldPw').val();

                if (!oldpw || oldpw == "") {
                    $('#txtOldPw').addClass("ui-state-error");
                    document.getElementById('txtOldPw').focus();
                    $('#pwChangeErrMsg').text('Enter your old password');
                    return;
                }

                pw1 = $('#txtNewPw1').val();
                if (pw1.length < 7 || pw1.length > 35) {
                    $('#txtNewPw1').addClass("ui-state-error");
                    $('#pwChangeErrMsg').text('The new password must be at least 7 characters');
                    return;
                }

                if ($('#txtNewPw1').val() != $('#txtNewPw2').val()) {
                    $('#pwChangeErrMsg').text("New passwords do not match");
                    return;
                }

                if (oldpw == pw1) {
                    $('#pwChangeErrMsg').text("The new password must be different from the old password");
                    return;
                }

                // immediatly clear the old password.
                $('#txtOldPw').val('');

                // make MD5 hash of password and concatenate challenge value
                // next calculate MD5 hash of combined values
                oldpwMD5 = hex_md5(hex_md5(oldpw) + '<?php echo $challengeVar; ?>');
                newpwMD5 = hex_md5(pw1);

                $.ajax({
                    type: "POST",
                    url: "ws_vol.php",
                    data: ({
                        cmd: 'chgpw',
                        old: oldpwMD5,
                        newer: newpwMD5
                    }),
                    success: function (data, statusTxt, xhrObject) {
                        handleChangePW(pwAlertPkg, data, statusTxt, xhrObject);
                    },
                    error: handleError,
                    datatype: "json"
                });
            },
            "Cancel": function () {
                $(this).dialog("close");
            }
        }
    });
    $(".hhk-hideStatus, .hhk-hideBasis").hide();
    // phone - email tabs block
    $('#phEmlTabs').tabs();
    $('#addrsTabs').tabs();
    $('#demographicTabs').tabs();
    $('#addrsTabs').tabs('option', 'active', memData.addrPref - 1);
    $('.ckdate').datepicker({
        changeMonth: true,
        changeYear: true,
        autoSize: true
    });
    $('#btnSavePI, #btnResetAddr, #chgPW').button();
    try {
        listTable = $('#tblListMembers').dataTable({
            "iDisplayLength": 20,
            "oLanguage": {"sEmptyTable": "No Contacts"},
            "sDom": '<"top">rt<"bottom">'
        });
    } catch (err) {}
    $('.hhk-showonload').css('display', 'block');
    if (memData.verifyAddress == 'true') {
        alert('First Login:  Verify your name and addresses (mail, phone and email).  Make any necessary changes and press Save.');
    }
});

