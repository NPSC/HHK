$(document).ready(function () {
    $('#btnPrint, #btnEmail, #btnWord').button();
    $(document).on('click', '#stmtDiv #btnEmail', function () {
        
        $('#emMsg').text('');
        if ($('#txtEmail').val() === '') {
            flagAlertMessage('Enter an Email Address.', 'error');
            return;
        }
        if ($('#txtSubject').val() === '') {
            flagAlertMessage('Enter a Subject line.', 'error');
            return;
        }
        $(this).attr('disabled', true).addClass("loading");
        $.post('ShowStatement.php', $('#stmtDiv .emTbl input, #stmtDiv .emTbl textarea').serialize() + '&cmd=email' + '&reg=' + $(this).data('reg') + '&vid=' + $(this).data('vid'), function (data) {
            $('#btnEmail').attr('disabled', false).removeClass("loading");
            try {
                data = $.parseJSON(data);
            } catch (err) {
                alert('Bad JSON Encoding');
                return;
            }
            if (data.error) {
                if (data.gotopage) {
                    window.open(data.gotopage, '_self');
                }
            }
            if (data.msg) {
                flagAlertMessage(data.msg, 'success');
            }
        });
    });

    $(document).on('click', '#stmtDiv #btnPrint', function () {
        $('div.PrintArea').printArea(
            {
                mode: 'popup',
                popClose: true,
                popHt: $('#divStmt').height(),
                popWd: $('#divStmt').width(),
                popX: 20,
                popY: 20,
                popTitle: 'Statement'
            }
        );
    });
});