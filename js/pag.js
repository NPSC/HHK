/**
 *
 * @param {string} mess
 * @param {boolean} wasError
 * @param {jQuery} $txtCtrl
 * @returns {undefined}
 */
function flagAlertMessage(mess, wasError, $txtCtrl) {
    "use strict";
    //Types:  alert, success, warning, error, info/information
    var type = 'info';
    
    if (!mess || mess == '') {
        return;
    }

    if (typeof wasError === 'boolean') {
        type = (wasError ? 'error' : 'success');
    } else if (typeof wasError === 'string') {
        type = wasError;
    }

    try {
        new Noty(
            {
                type: type,
                text: mess
            }
        ).show();
    } catch(err) {
        // do nothing for now.
    }
    
    // Show message in a given container.
    if ($txtCtrl === undefined || $txtCtrl === null) {
        return;
    }
    
    $txtCtrl.text(mess).show();
}
function dateRender(data, type, format) {
    // If display or filter data is requested, format the date
    if (type === 'display' || type === 'filter') {

        if (data === null || data === '') {
            return '';
        }

        data = data.trim();

        if (data === null || data === '') {
            return '';
        }

        if (!format || format === '') {
            format = 'MMM D, YYYY';
        }

        return moment(data).format(format);
    }

    // Otherwise the data type requested (`type`) is type detection or
    // sorting data, for which we want to use the integer, so just return
    // that, unaltered
    return data;
}

function isIE() {
    var ua = window.navigator.userAgent;
    return /MSIE|Trident/.test(ua);
}

function checkStrength(pwCtrl) {
    var strongRegex = new RegExp("^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])(?=.{8,})");
    var rtn = true;
    if(strongRegex.test(pwCtrl.val())) {
        pwCtrl.removeClass("ui-state-error");
    } else {
        pwCtrl.addClass("ui-state-error");
        rtn = false;
    }
    return rtn;
}

$(document).ready(function () {
    "use strict";
    //Hover states on the nav bar left icons.
    $("ul.hhk-ui-icons li").hover(
            function () {
                $(this).addClass("ui-state-hover");
            },
            function () {
                $(this).removeClass("ui-state-hover");
            }
    );

    $('#contentDiv').css('margin-top', $('#global-nav').css('height'));

    if ($('#dchgPw').length > 0) {
        $('#version').css('cursor','pointer');
        $('#version').hover(
            function () {
                $(this).addClass("ui-state-hover");
            },
            function () {
                $(this).removeClass("ui-state-hover");
            }
        );

        $('#version').click(function () {
            $('div#dchgPw').find('input').removeClass("ui-state-error").val('');
            $('#pwChangeErrMsg').text('');

            $('#dchgPw').dialog("option", "title", "Change Your Password");
            $('#dchgPw').dialog('open');
            $('#txtOldPw').focus();
        });
        $('div#dchgPw').on('change', 'input', function () {
            $(this).removeClass("ui-state-error");
            $(".hhk-alert").hide();
            $('#pwChangeErrMsg').text('');
        });
    
        $('#dchgPw').dialog({
            autoOpen: false,
            width: 490,
            resizable: true,
            modal: true,
            buttons: {
                "Save": function () {

                    var oldpw = $('#txtOldPw'), 
                            pw1 = $('#txtNewPw1'),
                            pw2 = $('#txtNewPw2'),
                            oldpwMD5, 
                            newpwMD5,
                            msg = $('#pwChangeErrMsg');

                    if (oldpw.val() == "") {
                        oldpw.addClass("ui-state-error");
                        oldpw.focus();
                        msg.text('Enter your old password');
                        return;
                    } else {
                        oldpw.removeClass("ui-state-error");
                    }

                    if (pw1.val() !== pw2.val()) {
                        msg.text("New passwords do not match");
                        return;
                    }

                    if (oldpw.val() == pw1.val()) {
                        pw1.addClass("ui-state-error");
                        msg.text("The new password must be different from the old password");
                        pw1.focus();
                        pw2.val('');
                        return;
                    }

                    if (checkStrength(pw1) === false) {
                        pw1.addClass("ui-state-error");
                        msg.text('Password must have 8 or more characters including uppercase and lowercase letters, numbers and symbols.');
                        pw1.focus();
                        return;
                    }

                    pw1.removeClass("ui-state-error");

                    // make MD5 hash of password and concatenate challenge value
                    // next calculate MD5 hash of combined values
                    oldpwMD5 = hex_md5(hex_md5(oldpw.val()) + challVar);
                    newpwMD5 = hex_md5(pw1.val());

                    oldpw.val('');
                    pw1.val('');
                    pw2.val('');

                    $.post("ws_admin.php",
                        {
                            cmd: 'chgpw',
                            old: oldpwMD5,
                            newer: newpwMD5
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

                                    if (data.gotopage) {
                                        window.open(data.gotopage, '_self');
                                    }
                                    flagAlertMessage(data.error, 'error');

                                } else if (data.success) {

                                    $('#dchgPw').dialog("close");
                                    flagAlertMessage(data.success, 'success');

                                } else if (data.warning) {
                                    $('#pwChangeErrMsg').text(data.warning);
                                }
                            }
                        }
                    );
                },
                "Cancel": function () {
                    $(this).dialog("close");
                }
            }
        });
    }
});
