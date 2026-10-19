/**
 * reauth.js
 *
 * Step-up re-authentication dialog (see ReauthGuard.php).
 *
 *   hhkReauth.require(purpose)  Promise that resolves once the user has a live grant for purpose,
 *                               asking for their password (or, for SSO users, sending them to their
 *                               IdP in a popup) when they don't.  Rejects if they cancel.
 *   hhkReauth.isGranted(purpose)  Synchronous best guess from what this page last heard.
 *   hhkReauth.forget(purpose)     Drop that guess, e.g. after the server answers {reauth: purpose}.
 *
 * Any jQuery ajax response holding {reauth: purpose} brings the dialog up so the user can retry.
 */
(function ($) {
    "use strict";

    var base = new URL('../auth/', document.currentScript.src),
        serviceUrl = new URL('ws_reauth.php', base).href,
        ssoUrl = new URL('ws_SSO.php', base).href,
        CHANNEL = 'hhk-reauth',
        grantedUntil = {},
        pending = null,     // {purpose, promise, resolve, reject, $dlg}
        statusRequests = {};

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

    function showError($dlg, msg) {
        $dlg.find('.hhk-reauth-error').text(msg || '').toggle(!!msg);
    }

    function prompt(purpose, status) {
        if (pending) {
            return pending.purpose === purpose ? pending.promise : Promise.reject(new Error('Another verification is in progress'));
        }

        var $dlg = $('<div style="font-size: 0.95em;"/>'),
            $msg = $('<p style="margin: 0.5em 0;"/>'),
            $err = $('<div class="hhk-reauth-error ui-state-error" style="display:none; padding: 0.3em; margin: 0.5em 0;"/>'),
            buttons = {};

        if (status.method === 'sso') {
            var idp = status.idpName || 'your SSO provider',
                $state = $('<span style="margin-left: 0.5em;"/>');

            $msg.text('For your security, verify your identity with ' + idp + ' to continue.');
            $dlg.append($msg, $('<div style="margin: 0.5em 0;"/>').append(
                $('<button type="button"/>').text('Verify with ' + idp).button().on('click', function () {
                    showError($dlg, '');
                    var popup = window.open(ssoUrl + '?cmd=reauth&purpose=' + encodeURIComponent(purpose), 'hhkReauth', 'popup,width=600,height=700');
                    if (popup) {
                        $state.text('Waiting for verification...');
                    } else {
                        showError($dlg, 'Allow pop-ups for this site and try again.');
                    }
                }),
                $state
            ), $err);

        } else {
            var $pw = $('<input type="password" autocomplete="current-password" style="width: 100%;"/>');

            var verify = function () {
                showError($dlg, '');
                fetch(serviceUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: new URLSearchParams({ cmd: 'verify', purpose: purpose, pw: $pw.val() })
                })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (data) {
                        $pw.val('');
                        if (data.success) {
                            finish(true, data.expiresIn);
                        } else {
                            showError($dlg, data.error || 'Verification failed.');
                            $pw.focus();
                        }
                    })
                    .catch(function () {
                        showError($dlg, 'Unexpected response from the server.');
                    });
            };

            $msg.text('For your security, enter your password to continue.');
            $dlg.append($msg, $('<label style="display:block;"/>').text('Password').append($pw), $err);
            $pw.on('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    verify();
                }
            });
            buttons['Verify'] = verify;
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
                $dlg.find('input[type=password]').focus();
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

    // SSO popup results.  BroadcastChannel still works when the IdP has cut window.opener.
    function onSsoResult(data) {
        if (!data || data.type !== CHANNEL || !pending) {
            return;
        }
        if (data.ok && data.purpose === pending.purpose) {
            // expiry comes from the server on the next status check
            finish(true, 60);
        } else if (!data.ok) {
            showError(pending.$dlg, data.message);
        }
    }

    try {
        new BroadcastChannel(CHANNEL).onmessage = function (e) {
            onSsoResult(e.data);
        };
    } catch (err) {
        // older browser - rely on postMessage
    }

    window.addEventListener('message', function (e) {
        if (e.origin === window.location.origin) {
            onSsoResult(e.data);
        }
    });

    $(document).ajaxComplete(function (event, xhr) {
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

    window.hhkReauth = { require: require, isGranted: isGranted, forget: forget };

})(jQuery);
