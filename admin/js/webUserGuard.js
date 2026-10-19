/**
 * webUserGuard.js
 *
 * Puts the Web Account dialog on NameEdit behind re-authentication (see reauth.js and
 * ReauthGuard.php), and resets passwords from it without asking for the admin's password
 * again.  Takes over the Reset Password button from nameEd.js.
 */
$(function () {
    "use strict";

    var $guard = $('#webUserGuard');

    if ($guard.length === 0) {
        return;
    }

    var purpose = $guard.data('purpose'),
        idName = $guard.data('id'),
        memName = $guard.data('name');

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
    var $reset = $('<div style="font-size: 0.95em;"/>').append(
        $('<div style="margin: 0.5em 0;"/>').html("The user's password will be reset to a random temporary password. <br>They will be required to change their password when they log in."),
        $('<div class="hhk-reset-msg" style="margin: 0.5em 0;"/>'),
        $('<div class="hhk-reset-pw" style="display:none; margin: 0.5em 0;"/>')
    );

    function resetPassword() {
        var $msg = $reset.find('.hhk-reset-msg').text(''),
            $pw = $reset.find('.hhk-reset-pw').empty().hide();

        fetch('ws_gen.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: new URLSearchParams({ cmd: 'adresetpw', uid: idName })
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
                    $msg.text(data.error);
                } else if (data.success) {
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

    $reset.dialog({
        autoOpen: false,
        width: 'auto',
        resizable: true,
        modal: true,
        title: 'Reset Password for ' + memName
    });

    // Capture phase, so nameEd.js's own handler on the button never sees the click
    document.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('#chgPW')) {
            e.preventDefault();
            e.stopImmediatePropagation();

            $reset.find('.hhk-reset-msg').text('');
            $reset.find('.hhk-reset-pw').empty().hide();
            $reset.dialog('option', 'buttons', {
                "Reset Password": resetPassword,
                "Cancel": function () { $reset.dialog('close'); }
            });
            $reset.dialog('open');
        }
    }, true);
});
