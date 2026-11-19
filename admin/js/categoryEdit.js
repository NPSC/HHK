/**
 * categoryEdit.js
 *
 * Client-side validation and AJAX load/save for admin/CategoryEdit.php.
 * The server repeats every check; this just gives faster feedback.
 */
(function ($) {
    'use strict';

    const PAGE = 'CategoryEdit.php';
    const MAX_DESC = 255;

    let $form;
    let current = { group: '', categories: [] };   // last data loaded from the server
    let snapshot = '';

    // ---- helpers ------------------------------------------------------

    function fieldByName(name) {
        // Codes can contain characters that are awkward in selectors, so compare names directly.
        return $form.find('[name]').filter(function () { return this.name === name; });
    }

    function errorBox($f) {
        return $f.closest('td, .cf-field').find('.cf-error').first();
    }

    function setError(name, msg) {
        const $f = fieldByName(name);
        $f.attr('aria-invalid', 'true').closest('td, .cf-field').addClass('cf-has-error');
        errorBox($f).text(msg);
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

    function formState() {
        return $form.find(':input').not('#selGroup').serialize();
    }

    function isDirty() {
        return formState() !== snapshot;
    }

    function errorDiv() {
        return $('<div class="cf-error" role="alert"></div>');
    }

    // ---- rendering ----------------------------------------------------

    function buildRow(cat) {
        const $tr = $('<tr>').attr('data-code', cat.code);

        $tr.append($('<td class="ce-code">').text(cat.code));

        const $name = $('<input type="text" size="30">')
            .attr({ name: 'desc[' + cat.code + ']', maxlength: MAX_DESC, 'aria-label': 'Name for code ' + cat.code })
            .val(cat.description);
        $tr.append($('<td>').append($name, errorDiv()));

        $tr.append($('<td class="ce-num">').text(cat.members));

        return $tr;
    }

    function render(data) {
        current = data;

        $('#selGroup').val(data.group);

        const $body = $('#catRows').empty();
        data.categories.forEach(function (cat) {
            $body.append(buildRow(cat));
        });
        $('#catCount').text(data.categories.length + (data.categories.length === 1 ? ' Entry' : ' Entries'));

        clearErrors();
        snapshot = formState();

        if (window.history && history.replaceState) {
            history.replaceState(null, '', PAGE + '?grp=' + encodeURIComponent(data.group));
        }
    }

    // ---- validation ---------------------------------------------------

    function validate() {
        const errors = {};
        const names = {};

        current.categories.forEach(function (cat) {
            const name = $.trim(fieldByName('desc[' + cat.code + ']').val());
            const key = name.toLowerCase();
            if (name === '') {
                errors['desc[' + cat.code + ']'] = 'Name is required.';
            } else if (names[key]) {
                errors['desc[' + cat.code + ']'] = 'Another category in this group already has this name.';
            }
            names[key] = true;
        });

        return errors;
    }

    // ---- AJAX ---------------------------------------------------------

    function ajaxFail(xhr) {
        let msg = 'The server could not be reached. Please try again.';
        if (xhr && xhr.status === 200) {
            msg = 'Unexpected response from the server. Your session may have expired; reload the page.';
        } else if (xhr && xhr.status) {
            msg = 'Server error (' + xhr.status + '). Please try again.';
        }
        flagAlertMessage(msg, true);
    }

    function load(group) {
        $.getJSON(PAGE, { cmd: 'load', grp: group })
            .done(function (resp) {
                if (resp.error) {
                    flagAlertMessage(resp.error, true);
                    $('#selGroup').val(current.group);
                    return;
                }
                render({ group: resp.group, categories: resp.categories });
            })
            .fail(function (xhr) {
                $('#selGroup').val(current.group);
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
                render({ group: resp.group, categories: resp.categories });
                flagAlertMessage(resp.success, false);
            })
            .fail(ajaxFail)
            .always(function () {
                $btn.prop('disabled', false).val('Save');
            });
    }

    // ---- wire up ------------------------------------------------------

    $(function () {
        $form = $('#catForm');
        if ($form.length === 0) {
            return;
        }

        render($form.data('initial'));

        $('#selGroup').on('change', function () {
            if (isDirty() && !window.confirm('Discard your unsaved changes to this group?')) {
                $(this).val(current.group);
                return;
            }
            load($(this).val());
        });

        $form.on('input change', ':input', function () {
            if (this.name) {
                const $f = $(this);
                $f.removeAttr('aria-invalid').closest('td, .cf-field').removeClass('cf-has-error');
                errorBox($f).text('');
            }
        });

        $('#btnReset').on('click', function () {
            render(current);
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
