/**
 * campaignEdit.js
 *
 * Client-side validation and AJAX load/save for admin/campaignEdit.php.
 * The server repeats every check; this just gives faster feedback.
 */
(function ($) {
    'use strict';

    const PAGE = 'campaignEdit.php';
    const NEW_CODE = 'vNew';
    const PERCENT_TYPE = 'pct';
    const MONEY_RE = /^\d{1,13}(\.\d{1,2})?$/;
    const PERCENT_RE = /^\d{1,3}(\.\d{1,3})?$/;

    // Changing one field can make another field's error stale.
    const RELATED = {
        startDate: ['endDate'],
        minDonation: ['maxDonation'],
        type: ['percent']
    };

    let $form;
    let current = {};   // last campaign loaded from the server
    let snapshot = '';  // serialized form at load time, for the unsaved-changes check

    function field(name) {
        return $form.find('[name="' + name + '"]');
    }

    // ---- errors -------------------------------------------------------

    function setError(name, msg) {
        field(name).attr('aria-invalid', 'true').closest('.cf-field').addClass('cf-has-error');
        $('#err-' + name).text(msg);
    }

    function clearError(name) {
        field(name).removeAttr('aria-invalid').closest('.cf-field').removeClass('cf-has-error');
        $('#err-' + name).text('');
    }

    function clearErrors() {
        $form.find('[aria-invalid]').removeAttr('aria-invalid');
        $form.find('.cf-has-error').removeClass('cf-has-error');
        $form.find('.cf-error').text('');
    }

    function showErrors(errors) {
        $.each(errors, setError);
        $form.find('[aria-invalid="true"]').first().trigger('focus');
    }

    // ---- validation ---------------------------------------------------

    function parseMoney(raw) {
        const s = String(raw || '').replace(/[$,\s]/g, '');
        if (s === '') {
            return { value: 0 };
        }
        if (!MONEY_RE.test(s)) {
            return { error: true };
        }
        return { value: parseFloat(s) };
    }

    function checkDate(name, label, errors) {
        const el = field(name)[0];
        if (el.validity && el.validity.badInput) {
            errors[name] = label + ' is not a valid date.';
        } else if (!el.value) {
            errors[name] = label + ' is required.';
        }
    }

    function validate() {
        const errors = {};

        if ($.trim(field('title').val()) === '') {
            errors.title = 'Title is required.';
        }

        checkDate('startDate', 'Start date', errors);
        checkDate('endDate', 'End date', errors);
        const sd = field('startDate').val(), ed = field('endDate').val();
        if (!errors.startDate && !errors.endDate && ed < sd) {
            errors.endDate = 'End date must be on or after the start date.';
        }

        if (!field('status').val()) {
            errors.status = 'Choose a status.';
        }

        const type = field('type').val();
        if (!type) {
            errors.type = 'Choose a campaign type.';
        } else if (type === PERCENT_TYPE) {
            const pct = String(field('percent').val() || '').replace(/[%\s]/g, '');
            const n = parseFloat(pct);
            if (!PERCENT_RE.test(pct) || n <= 0 || n > 100) {
                errors.percent = 'Enter a percentage greater than 0 and no more than 100, with up to 3 decimal places.';
            }
        }

        const min = parseMoney(field('minDonation').val());
        const max = parseMoney(field('maxDonation').val());
        const target = parseMoney(field('target').val());

        if (min.error) {
            errors.minDonation = 'Minimum donation must be a positive dollar amount, like 25 or 25.00.';
        }
        if (max.error) {
            errors.maxDonation = 'Maximum donation must be a positive dollar amount, like 25 or 25.00.';
        }
        if (target.error) {
            errors.target = 'Goal must be a positive dollar amount, like 25 or 25.00.';
        }
        if (!min.error && !max.error && max.value > 0 && min.value > max.value) {
            errors.maxDonation = 'Maximum donation must be greater than or equal to the minimum.';
        }

        return errors;
    }

    // Re-check a field when the user leaves it, plus any related field already showing an error.
    function revalidate(name) {
        const errors = validate();
        [name].concat(RELATED[name] || []).forEach(function (n) {
            if (n !== name && !$('#err-' + n).text()) {
                return;
            }
            if (errors[n]) {
                setError(n, errors[n]);
            } else {
                clearError(n);
            }
        });
    }

    // ---- form state ---------------------------------------------------

    function formState() {
        return $form.find(':input').not('#selCamp').serialize();
    }

    function isDirty() {
        return formState() !== snapshot;
    }

    function togglePercent() {
        const show = field('type').val() === PERCENT_TYPE;
        $('#percentField').prop('hidden', !show);
        if (!show) {
            clearError('percent');
        }
    }

    function fillForm(c) {
        current = c;

        $('#selCamp').val(c.campCode);
        ['title', 'startDate', 'endDate', 'status', 'type', 'percent',
            'minDonation', 'maxDonation', 'target', 'category', 'mergeCode', 'description'
        ].forEach(function (n) {
            field(n).val(c[n]);
        });

        togglePercent();
        clearErrors();

        let meta = '';
        if (c.campCode !== NEW_CODE && c.lastUpdated) {
            meta = 'Last updated ' + c.lastUpdated + (c.updatedBy ? ' by ' + c.updatedBy : '') + '.';
        }
        $('#campMeta').text(meta);

        snapshot = formState();

        const url = c.campCode === NEW_CODE ? PAGE : PAGE + '?cp=' + encodeURIComponent(c.campCode);
        if (window.history && history.replaceState) {
            history.replaceState(null, '', url);
        }
    }

    // ---- AJAX ---------------------------------------------------------

    function ajaxFail(xhr) {
        let msg = 'The server could not be reached. Please try again.';
        if (xhr && xhr.status === 200) {
            // Got HTML instead of JSON: usually an expired session redirecting to login.
            msg = 'Unexpected response from the server. Your session may have expired; reload the page.';
        } else if (xhr && xhr.status) {
            msg = 'Server error (' + xhr.status + '). Please try again.';
        }
        flagAlertMessage(msg, true);
    }

    function load(code) {
        $.getJSON(PAGE, { cmd: 'load', cp: code })
            .done(function (resp) {
                if (resp.error) {
                    flagAlertMessage(resp.error, true);
                    $('#selCamp').val(current.campCode);
                    return;
                }
                fillForm(resp.campaign);
            })
            .fail(function (xhr) {
                $('#selCamp').val(current.campCode);
                ajaxFail(xhr);
            });
    }

    function save() {
        clearErrors();

        const errors = validate();
        if (!$.isEmptyObject(errors)) {
            showErrors(errors);
            flagAlertMessage('Please correct the highlighted fields.', true);
            return;
        }

        const $btn = $('#btnSave').prop('disabled', true).val('Saving...');

        $.post(PAGE, $form.serialize(), null, 'json')
            .done(function (resp) {
                if (resp.errors) {
                    showErrors(resp.errors);
                }
                if (resp.error) {
                    flagAlertMessage(resp.error, true);
                    return;
                }
                if (resp.options) {
                    $('#selCamp').html(resp.options);
                }
                fillForm(resp.campaign);
                flagAlertMessage(resp.success, false);
            })
            .fail(ajaxFail)
            .always(function () {
                $btn.prop('disabled', false).val('Save');
            });
    }

    // ---- wire up ------------------------------------------------------

    $(function () {
        $form = $('#campForm');

        // Strip non-numeric input and format to 2 decimals on blur (pag.js).
        setupNumericInputs($form.find('.hhk-money'));

        // Percent: same input filtering, but no padding to 2 decimals (12.50 -> 12.5).
        setupNumericInputs(field('percent'), function (val) {
            const n = parseFloat(val);
            return isNaN(n) ? '' : String(n);
        });

        fillForm($form.data('campaign'));

        $('#selCamp').on('change', function () {
            const code = $(this).val();
            if (isDirty() && !window.confirm('Discard your unsaved changes to this campaign?')) {
                $(this).val(current.campCode);
                return;
            }
            load(code);
        });

        field('type').on('change', togglePercent);

        $form.on('change', ':input:not(#selCamp)', function () {
            if (this.name && this.name !== 'cmd') {
                revalidate(this.name);
            }
        });

        // Clear a field's error as soon as the user starts fixing it.
        $form.on('input', ':input', function () {
            if (this.name) {
                clearError(this.name);
            }
        });

        $('#btnReset').on('click', function () {
            fillForm(current);
        });

        $form.on('submit', function (e) {
            e.preventDefault();
            save();
        });

        $(window).on('beforeunload', function () {
            if (isDirty()) {
                return 'You have unsaved changes.';
            }
        });
    });

})(jQuery);
