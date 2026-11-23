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

    function buildRow(cat, all, canAddRemove) {
        const $tr = $('<tr>').attr('data-code', cat.code);

        $tr.append($('<td class="ce-code">').text(cat.code));

        const $name = $('<input type="text" size="30">')
            .attr({ name: 'desc[' + cat.code + ']', maxlength: MAX_DESC, 'aria-label': 'Name for code ' + cat.code })
            .val(cat.description);
        $tr.append($('<td>').append($name, errorDiv()));

        $tr.append($('<td class="ce-num">').text(cat.members));

        // Locked groups (Member Type) can only be renamed.
        if (!canAddRemove) {
            return $tr;
        }

        $tr.append($('<td class="ce-center">').append(
            $('<input type="checkbox" value="1" class="ce-del">')
                .attr({ name: 'del[' + cat.code + ']', 'aria-label': 'Delete ' + cat.description }),
            errorDiv()
        ));

        // Like Resource Builder: the column shows only while a Delete is checked, and each
        // row's dropdown only while its own Delete is checked.
        const $replTd = $('<td class="ce-replCol" style="display:none;">');
        if (cat.members > 0) {
            const $sel = $('<select class="ce-repl" disabled style="display:none;">')
                .attr({ name: 'repl[' + cat.code + ']', 'aria-label': 'Replace ' + cat.description + ' with' })
                .append($('<option value="">').text('(none - remove members)'));
            all.forEach(function (o) {
                if (o.code !== cat.code) {
                    $sel.append($('<option>').val(o.code).text(o.description));
                }
            });
            $replTd.append($sel, errorDiv());
        }
        $tr.append($replTd);

        return $tr;
    }

    function render(data) {
        current = data;

        $('#selGroup').val(data.group);

        const canAddRemove = data.canAddRemove !== false;

        const $body = $('#catRows').empty();
        data.categories.forEach(function (cat) {
            $body.append(buildRow(cat, data.categories, canAddRemove));
        });
        $('#catCount').text(data.categories.length + (data.categories.length === 1 ? ' Entry' : ' Entries'));
        $('#catTable .ce-replCol').hide();
        $form.find('.ce-addRemove').toggle(canAddRemove);
        $form.find('.ce-locked').toggle(!canAddRemove);
        // Disabled so a hidden New field is never posted for a locked group.
        $('#txtNewDesc').val('').prop('disabled', !canAddRemove);

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
        const deleting = {};

        $form.find('.ce-del:checked').each(function () {
            deleting[$(this).closest('tr').data('code')] = true;
        });

        current.categories.forEach(function (cat) {
            if (deleting[cat.code]) {
                const r = fieldByName('repl[' + cat.code + ']').val();
                if (r && deleting[r]) {
                    errors['repl[' + cat.code + ']'] = 'The replacement is also being deleted.';
                }
                return;
            }
            const name = $.trim(fieldByName('desc[' + cat.code + ']').val());
            const key = name.toLowerCase();
            if (name === '') {
                errors['desc[' + cat.code + ']'] = 'Name is required.';
            } else if (names[key]) {
                errors['desc[' + cat.code + ']'] = 'Another category in this group already has this name.';
            }
            names[key] = true;
        });

        const newName = $.trim($('#txtNewDesc').val());
        if (newName !== '' && names[newName.toLowerCase()]) {
            errors.newDesc = 'A category in this group already has this name.';
        }

        return errors;
    }

    // Deleting with no replacement removes members from the category; make sure that's intended.
    function confirmRemovals() {
        const lines = [];
        current.categories.forEach(function (cat) {
            if (cat.members > 0 && fieldByName('del[' + cat.code + ']').prop('checked')
                    && !fieldByName('repl[' + cat.code + ']').val()) {
                lines.push(cat.description + ' (' + cat.members + ')');
            }
        });
        if (lines.length === 0) {
            return true;
        }
        return window.confirm('These categories will be deleted and their members removed from them:\n\n'
            + lines.join('\n') + '\n\nContinue?');
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
                render({ group: resp.group, canAddRemove: resp.canAddRemove, categories: resp.categories });
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

        if (!confirmRemovals()) {
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
                render({ group: resp.group, canAddRemove: resp.canAddRemove, categories: resp.categories });
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

        // Show "Replace With" only for rows being deleted, and the column only while any are; strike the name.
        $form.on('change', '.ce-del', function () {
            const $tr = $(this).closest('tr');
            const del = this.checked;
            $tr.toggleClass('ce-deleting', del);
            $tr.find('.ce-repl').prop('disabled', !del).toggle(del);
            $('#catTable .ce-replCol').toggle($form.find('.ce-del:checked').length > 0);
            $tr.find('input[type=text]').prop('readonly', del);
            if (!del) {
                $tr.find('.cf-error').text('');
                $tr.find('.cf-has-error').removeClass('cf-has-error');
            }
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
