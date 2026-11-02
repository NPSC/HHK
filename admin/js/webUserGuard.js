/**
 * webUserGuard.js
 *
 * Puts the Web Account dialog on NameEdit behind re-authentication (see reauth.js and
 * ReauthGuard.php), and resets passwords from it without asking for the admin's password
 * again.  Opened by the dialog's Reset Password button (nameEd.js).
 */
$(function () {
    "use strict";

    var $guard = $('#webUserGuard');

    if ($guard.length === 0) {
        return;
    }

    var purpose = $guard.data('purpose'),
        idName = $guard.data('id'),
        memName = $guard.data('name'),
        minPwLength = $guard.data('minpw');

    // However the dialog gets opened, don't show it until the user has verified
    $('#vwebUser').on('dialogopen', function () {
        var $wu = $(this);

        if (hhkReauth.isGranted(purpose)) {
            return;
        }

        $wu.dialog('close');
        hhkReauth.require(purpose).then(function () {
            $wu.dialog('open');
        }, function () {
            // cancelled
        });
    });

    // Password reset
    function pwRow(label, id, placeholder, errId) {
        return $('<div class="row mx-0 mt-2"/>').append(
            $('<label class="col-5 pwLabel"/>').attr('for', id).text(label),
            $('<div class="col-7"/>').append(
                $('<div class="hhk-flex"/>').append(
                    $('<input type="password" value="" autocomplete="new-password"/>').attr({id: id, placeholder: placeholder}),
                    $('<button type="button" class="showPw" tabindex="-1" title="Show password"><i class="bi bi-eye-fill"></i></button>')
                ),
                $('<div class="fieldErrMsg"/>').attr('id', errId)
            )
        );
    }

    var $reset = $('<form class="hhk-pwForm" style="font-size: 0.95em;"/>').append(
        $('<div class="mb-2"/>').text('The user will be required to change their password the next time they log in.'),
        pwRow('New Password:', 'wgNewPw1', 'Leave blank for a random password', 'wgNewPwErr'),
        pwRow('Confirm New Password:', 'wgNewPw2', 'Confirm new password', 'wgConfirmPwErr'),
        $('<div class="row mx-0 mt-2"/>').append(
            $('<div class="col-12" style="font-size: smaller;"/>').text('If you enter a password, it must have at least ' + minPwLength + ' characters with at least 1 uppercase letter, 1 lowercase letter, a number and a symbol. It cannot include < or >. Do not use names or dictionary words.')
        ),
        $('<div class="hhk-reset-msg" style="margin: 0.5em 0;"/>'),
        $('<div class="hhk-reset-pw" style="display:none; margin: 0.5em 0;"/>')
    );

    var fieldInputs = {newer: '#wgNewPw1', confirm: '#wgNewPw2'},
        fieldMsgs = {newer: '#wgNewPwErr', confirm: '#wgConfirmPwErr'};

    function clearResetMessages() {
        $reset.find('.hhk-reset-msg').text('');
        $reset.find('.hhk-reset-pw').empty().hide();
        $reset.find('.fieldErrMsg').empty();
        $reset.find('input').removeClass('ui-state-error').prop('type', 'password');
        $reset.find('.showPw i').removeClass('bi-eye-slash-fill').addClass('bi-eye-fill');
    }

    function resetPassword() {
        var $msg = $reset.find('.hhk-reset-msg'),
            $pw = $reset.find('.hhk-reset-pw'),
            newPw = $('#wgNewPw1').val(),
            confirmPw = $('#wgNewPw2').val();

        clearResetMessages();

        fetch('ws_gen.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: new URLSearchParams({ cmd: 'adresetpw', uid: idName, newpw: newPw, confirmpw: confirmPw })
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.reauth) {
                    hhkReauth.forget(purpose);
                    hhkReauth.require(purpose).then(resetPassword, function () {
                        $msg.text(data.error);
                    });
                } else if (data.error) {
                    if (data.gotopage) {
                        window.open(data.gotopage, '_self');
                    }
                    $.each(data.fieldErrors || {}, function (field, msgs) {
                        if (fieldInputs[field]) {
                            $(fieldInputs[field]).addClass('ui-state-error');
                            $(fieldMsgs[field]).html(msgs.join('<br>'));
                        }
                    });
                    $msg.text(data.error);
                } else if (data.success) {
                    $reset.find('input').val('');
                    $msg.text(data.success);
                    $reset.dialog('option', 'buttons', { "Close": function () { $reset.dialog('close'); } });
                    if (data.tempPW) {
                        $pw.append(
                            $('<strong>').text('New Temporary Password: '),
                            $('<span style="user-select:all;">').text(data.tempPW)
                        ).show();
                    }
                }
            })
            .catch(function () {
                $msg.text('Unexpected response from the server.  Password may be unchanged.');
            });
    }

    $reset.on('submit', function (e) {
        e.preventDefault();
        resetPassword();
    });

    $reset.dialog({
        autoOpen: false,
        width: 'auto',
        resizable: true,
        modal: true,
        title: 'Reset Password for ' + memName
    });

    $('#vwebUser').on('hhk:resetPassword', function () {
        clearResetMessages();
        $reset.find('input').val('');
        $reset.dialog('option', 'buttons', {
            "Reset Password": resetPassword,
            "Cancel": function () { $reset.dialog('close'); }
        });
        $reset.dialog('open');
        $('#wgNewPw1').focus();
    });
});
