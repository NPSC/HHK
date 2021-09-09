$(document).ready(function () {
    $('#selmtype').change(function() {
        $('#divExpansion').children().remove();
        $.post('Duplicates.php', {cmd: 'list', mType: $(this).val()},
            function (data) {
                "use strict";
                if (!data) {
                    alert('Bad Reply from Server');
                    return;
                }
                try {
                    data = $.parseJSON(data);
                } catch (err) {
                    alert("Parser error - " + err.message);
                    return;
                }
                if (data.error) {
                    flagAlertMessage(data.error, true);
                    return;
                }
                $('#divList').children().remove().end().append($(data.mk));

                $('.hhk-expand').click(function () {
                    $('#dupNames td').css('background-color','');
                    $(this).parent('td').css('background-color','yellow');
                    $.post('Duplicates.php', {cmd: 'exp', nf: $(this).data('fn'), mType: $(this).data('type')},
                        function (data) {
                            "use strict";
                            if (!data) {
                                alert('Bad Reply from Server');
                                return;
                            }
                            try {
                                data = $.parseJSON(data);
                            } catch (err) {
                                alert("Parser error - " + err.message);
                                return;
                            }
                            if (data.error) {
                                flagAlertMessage(data.error, true);
                                return;
                            }

                            $('#divExpansion').children().remove().end().append($(data.mk)).show();
                            $('#btnCombPSG, #btnCombId').button();
                            $('#btnCombine').click(function () {
                                var id = $('input[name=rbchoose]:checked').val();
                                $('#spnAlert').text('');
                                if (!id || id == 0) {
                                    $('#spnAlert').text('Pick a name to combine.');
                                    return false;
                                }
                                $.post('Duplicates.php', {cmd: 'pik', id: id, mType: $(this).data('type')},
                                        function (data) {
                                            "use strict";
                                            if (!data) {
                                                alert('Bad Reply from Server');
                                                return;
                                            }
                                            try {
                                                data = $.parseJSON(data);
                                            } catch (err) {
                                                alert("Parser error - " + err.message);
                                                return;
                                            }
                                            if (data.error) {
                                                flagAlertMessage(data.error, true);
                                                return;
                                            }
                                            if (data.msg) {
                                                $('#divExpansion').children().remove().end().append($(data.msg)).show();
                                            }
                                        });
                            });
                            $('#btnCombPSG').click(function () {
                                var idGood = $('input[name=rbgood]:checked').val();
                                var idBad = $('input[name=rbbad]:checked').val();
                                $('#spnAlert').text('');
                                if (!idGood || idGood == 0) {
                                    $('#spnAlert').text('Pick a Good PSG to combine.');
                                    return false;
                                }
                                if (!idBad || idBad == 0) {
                                    $('#spnAlert').text('Pick a Bad PSG to combine.');
                                    return false;
                                }
                                if (idBad == idGood) {
                                    $('#spnAlert').text('Pick a different bad and good PSG to combine.');
                                    return false;
                                }
                                $.post('Duplicates.php', {cmd: 'cpsg', idg: idGood, idb: idBad},
                                        function (data) {
                                            "use strict";
                                            if (!data) {
                                                alert('Bad Reply from Server');
                                                return;
                                            }
                                            try {
                                                data = $.parseJSON(data);
                                            } catch (err) {
                                                alert("Parser error - " + err.message);
                                                return;
                                            }
                                            if (data.error) {
                                                flagAlertMessage(data.error, 'error');
                                                return;
                                            }
                                            if (data.msg && data.msg != '') {
                                                flagAlertMessage(data.msg, 'info');
                                            }
                                });
                            });
                            $('#btnCombId').click(function () {
                                var idGood = $('input[name=rbsave]:checked').val();
                                var idBad = $('input[name=rbremove]:checked').val();
                                $('#spnAlert').text('');
                                if (!idGood || idGood == 0) {
                                    $('#spnAlert').text('Pick a Save Id to combine.');
                                    return false;
                                }
                                if (!idBad || idBad == 0) {
                                    $('#spnAlert').text('Pick a Remove Id to combine.');
                                    return false;
                                }
                                if (idBad == idGood) {
                                    $('#spnAlert').text('Pick a different save and remove Id to combine.');
                                    return false;
                                }
                                $.post('Duplicates.php', {cmd: 'cids', idg: idGood, idb: idBad},
                                        function (data) {
                                            "use strict";
                                            if (!data) {
                                                alert('Bad Reply from Server');
                                                return;
                                            }
                                            try {
                                                data = $.parseJSON(data);
                                            } catch (err) {
                                                alert("Parser error - " + err.message);
                                                return;
                                            }
                                            if (data.error) {
                                                flagAlertMessage(data.error, 'alert');
                                                return;
                                            }
                                            if (data.msg && data.msg != '') {
                                                flagAlertMessage(data.msg, 'success');
                                            }
                                });
                            });
                    });
                });
        });
    });
});
