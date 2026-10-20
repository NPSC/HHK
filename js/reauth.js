/**
 * reauth.js
 *
 * Step-up re-authentication dialog (see ReauthGuard.php).  Local users enter their password; SSO
 * users enter a two step verification code from User Settings.
 *
 *   hhkReauth.require(purpose)  Promise that resolves once the user has a live grant for purpose,
 *                               asking them to verify when they don't.  Rejects if they cancel.
 *   hhkReauth.isGranted(purpose)  Synchronous best guess from what this page last heard.
 *   hhkReauth.forget(purpose)     Drop that guess, e.g. after the server answers {reauth: purpose}.
 *
 * Any jQuery ajax response holding {reauth: purpose} brings the dialog up so the user can retry.
 *
 * Loaded with the user settings markup on every page, and may be included again by a page.
 */
(function () {
    "use strict";

    if (window.hhkReauth) {
        return;
    }

    var serviceUrl = new URL('../auth/ws_reauth.php', document.currentScript.src).href,
        grantedUntil = {},
        pending = null,     // {purpose, promise, resolve, reject, $dlg}
        statusRequests = {},
        OTP_LABELS = { authenticator: 'Authenticator app', email: 'Email', backup: 'Backup code' };

    function remember(purpose, expiresIn) {
        // a little early, so we ask the server before it would refuse us
        grantedUntil[purpose] = Date.now() + Math.max(0, (expiresIn || 0) - 30) * 1000;
    }

    function forget(purpose) {
        delete grantedUntil[purpose];
    }

    function isGranted(purpose) {
        return (grantedUntil[purpose] || 0) > Date.now();
    }

    function post(params) {
        return fetch(serviceUrl, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams(params) })
            .then(function (r) {
                return r.json();
            });
    }

    function getStatus(purpose) {
        if (!statusRequests[purpose]) {
            statusRequests[purpose] = fetch(serviceUrl + '?cmd=status&purpose=' + encodeURIComponent(purpose), { credentials: 'same-origin' })
                .then(function (r) {
                    return r.json();
                })
                .finally(function () {
                    delete statusRequests[purpose];
                });
        }
        return statusRequests[purpose];
    }

    function finish(ok, expiresIn, err) {
        if (!pending) {
            return;
        }
        var p = pending;
        pending = null;

        if (ok) {
            remember(p.purpose, expiresIn);
            p.resolve();
        } else {
            p.reject(err || new Error('Verification cancelled'));
        }
        p.$dlg.dialog('close').remove();
    }

    function showMessage($dlg, msg, isError) {
        $dlg.find('.hhk-reauth-msg').text(msg || '').toggle(!!msg).toggleClass('ui-state-error', !!isError).toggleClass('ui-state-highlight', !isError);
    }

    // Same layout as the login page's password row.  jQuery UI sizes dialog inputs and buttons to
    // the dialog text, so size them like the login page.
    function inputRow(label, $input, $button) {
        var $ = window.jQuery;
        $input.addClass('w-100').css({ minWidth: 0, fontSize: '0.85em' });
        if ($button) {
            $button.addClass('mx-1').css({ flexShrink: 0, fontSize: '0.75em' }).button();
        }
        return $('<div class="row mt-3 mx-0"/>').append(
            $('<label class="col-4 pr-0 tdlabel"/>').text(label),
            $('<div class="col-8"/>').append($('<div class="d-flex"/>').append($input, $button || []))
        );
    }

    function prompt(purpose, status) {
        var $ = window.jQuery;

        if (pending) {
            return pending.purpose === purpose ? pending.promise : Promise.reject(new Error('Another verification is in progress'));
        }

        var $dlg = $('<div style="font-size: 0.95em;"/>'),
            $intro = $('<p style="margin: 0.5em 0;"/>'),
            $msg = $('<div class="hhk-reauth-msg" style="display:none; padding: 0.3em; margin: 0.5em 0;"/>'),
            buttons = {},
            verify = null;

        if (status.method === 'otp-setup') {
            $intro.text('For your security, this needs a two step verification code.  Set up two step verification in User Settings, then try again.');
            $dlg.append($intro);

            if ($('#userSettingsBtn').length) {
                buttons['Open User Settings'] = function () {
                    finish(false);
                    $('#userSettingsBtn').trigger('click');
                };
            }

        } else if (status.method === 'otp') {
            var $method = $('<select/>'),
                $code = $('<input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6"/>'),
                emailSent = false,
                sendCode = function () {
                    showMessage($dlg, 'Sending a code to your email...');
                    post({ cmd: 'sendcode', purpose: purpose })
                        .then(function (data) {
                            emailSent = emailSent || !!data.success;
                            showMessage($dlg, data.success ? 'We emailed you a code.  It is good for 5 minutes.' : (data.error || 'Unable to send a code.'), !data.success);
                        })
                        .catch(function () {
                            showMessage($dlg, 'Unexpected response from the server.', true);
                        });
                },
                $send = $('<button type="button">Resend code</button>').on('click', sendCode);

            status.otpMethods.forEach(function (m) {
                $method.append($('<option/>').val(m).text(OTP_LABELS[m] || m));
            });

            // Like the login page, email codes are sent as soon as email is the chosen method
            var onMethod = function () {
                var isEmail = ($method.val() === 'email');
                $send.toggle(isEmail);
                showMessage($dlg, '');
                $code.val('').focus();
                if (isEmail && !emailSent) {
                    sendCode();
                }
            };
            $method.on('change', onMethod);

            verify = function () {
                return { secret: $code.val(), otpMethod: $method.val() };
            };

            $intro.text('For your security, enter a two step verification code to continue.');
            $dlg.append($intro);
            if (status.otpMethods.length > 1) {
                $dlg.append(inputRow('Method:', $method));
            }
            $dlg.append(inputRow('Code:', $code, $send), $msg);
            onMethod();

        } else {
            var $pw = $('<input type="password" autocomplete="current-password"/>'),
                $show = $('<button type="button" tabindex="-1">Show</button>').on('click', function () {
                    var hidden = $pw.prop('type') === 'password';
                    $pw.prop('type', hidden ? 'text' : 'password');
                    $show.button('option', 'label', hidden ? 'Hide' : 'Show');
                });

            verify = function () {
                return { secret: $pw.val() };
            };

            $intro.text('For your security, enter your password to continue.');
            $dlg.append($intro, inputRow('Password:', $pw, $show), $msg);
        }

        if (verify) {
            var submit = function () {
                showMessage($dlg, '');
                post($.extend({ cmd: 'verify', purpose: purpose }, verify()))
                    .then(function (data) {
                        $dlg.find('input').val('');
                        if (data.success) {
                            finish(true, data.expiresIn);
                        } else {
                            showMessage($dlg, data.error || 'Verification failed.', true);
                            $dlg.find('input').first().focus();
                        }
                    })
                    .catch(function () {
                        showMessage($dlg, 'Unexpected response from the server.', true);
                    });
            };

            $dlg.on('keydown', 'input', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submit();
                }
            });
            buttons['Verify'] = submit;
        }

        buttons['Cancel'] = function () {
            finish(false);
        };

        pending = { purpose: purpose, $dlg: $dlg };
        pending.promise = new Promise(function (resolve, reject) {
            pending.resolve = resolve;
            pending.reject = reject;
        });

        $dlg.dialog({
            title: 'Verify Your Identity',
            modal: true,
            width: 400,
            resizable: false,
            buttons: buttons,
            close: function () {
                finish(false);
            },
            open: function () {
                $dlg.find('input').first().focus();
            }
        });

        return pending.promise;
    }

    function require(purpose) {
        if (isGranted(purpose)) {
            return Promise.resolve();
        }

        return getStatus(purpose).then(function (status) {
            if (status.error) {
                throw new Error(status.error);
            }
            if (status.granted) {
                remember(purpose, status.expiresIn);
                return;
            }
            return prompt(purpose, status);
        });
    }

    window.hhkReauth = { require: require, isGranted: isGranted, forget: forget };

    // jQuery may load after this script
    function hookAjax() {
        if (!window.jQuery) {
            return;
        }

        window.jQuery(document).ajaxComplete(function (event, xhr) {
            var text = xhr.responseText;
            if (typeof text !== 'string' || text.indexOf('"reauth"') < 0) {
                return;
            }
            try {
                var data = JSON.parse(text);
                if (data && typeof data.reauth === 'string') {
                    forget(data.reauth);
                    require(data.reauth).catch(function () {});
                }
            } catch (err) {
                // not JSON
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hookAjax);
    } else {
        hookAjax();
    }

})();
