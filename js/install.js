/* 
 * The MIT License
 *
 * Copyright 2017 Eric Crane <ecrane at nonprofitsoftwarecorp.org>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

$(document).ready(function () {
    "use strict";

    var loading = '<img src="../images/ui-anim_basic_16x16.gif">'

    $('form#installdb').on('submit', function (e) {
        e.preventDefault();
        var $btn = $(this).find("#btnSave")
        $btn.html(loading).prop('disabled', true);

        $.post('ws_install.php', {cmd: 'installdb'}, function (data) {
                try {
                    data = $.parseJSON(data);
                } catch (err) {
                    alert("Parser error - " + err.message);
                    return;
                }

                if (data.success) {
                    $('#successMsg').html(data.success);
                    $btn.text('Completed!');
                }
        }, "json")
        .fail(function(response, textStatus, errorThrown){
            if(response.responseJSON.error){
                $('#errorMsg').text(data.error);
            }else if(response.responseJSON.errors){
                $('#errorMsg').html(response.responseJSON.errors.join('<br>'));
            }else if(response.responseText){
                $('#errorMsg').text(response.responseText);
            }
            
            $btn.text("Install DB").prop("disabled", false);
        });
    });

    $('form#loadmd').on('submit', function (e) {
        e.preventDefault();

        var $btn = $(this).find('#btnMeta');

        var pw1 = $('#txtpw1'),
            pw2 = $('#txtpw2'),
            pword;

        $('#spanpwerror').text('');
        pword = pw1.val();

        if (checkStrength(pword)) {

            // Strength ok, check second copy
            if (pword !== pw2.val()) {
                $('#spanpwerror').text('Passwords are not the same.');
                return;
            }

        } else {
            $('#spanpwerror').text("Password must have 8 or more characters including at least one uppercase and one lower case alphabetical character and one number and one of ! @ # $ % ^ & * ( ) - = _ + ~ . , \" < > / ? ; : ' | [ ] { }");
            return;
        }

        $btn.html(loading).prop('disabled', true);
        $.post('ws_install.php', {cmd: 'loadmd', 'new': pword}, function (data) {
            if (data) {
                try {
                    data = $.parseJSON(data);
                } catch (err) {
                    alert("Parser error - " + err.message);
                    return;
                }

                if (data.success) {
                    $('#spanDone').text(data.success);
                    $btn.text('Completed!');
                }

                if (data.error) {
                    $('#spanpwerror').text(data.error);
                    $btn.text("Load Metadata").prop("disabled", false);
                }
            }
        });

        pw1.val('');
        pw2.val('');

    });

    $('form[name=formz]').on('submit', function (e) {
        e.preventDefault();
        var $btn = $(this).find("button[type=submit]");
        $btn.html(loading).prop('disabled', true);

        var formData = new FormData($(this)[0]);
        formData.append("cmd", "uploadZipCodFile");

        $.ajax({
            url:'ws_install.php',
            data: formData, 
            type:"post",
            dataType: "json",
            contentType: false,
            processData: false,
            success:function (data) {
                if (data) {
                    try {
                        data = $.parseJSON(data);
                    } catch (err) {
                        alert("Parser error - " + err.message);
                        return;
                    }

                    if (data.success) {
                        $('#zipMsg').css("color","green").html(data.success);
                        $btn.text('Completed!');
                    }

                    if (data.error) {
                        $('#zipMsg').css("color", "red").text(data.error);
                        $btn.text("Load Zip Code File").prop("disabled", false);
                    }
                }else{
                    $btn.text("Load Zip Code File");
                }
            }
        });
    });
});

function testDb(parms) {
    $.post('ws_install.php',
        parms,
        function(data) {
            if (!data) {
                alert('Bad Reply from Web Server');
                return;
            }
            try {
                data = $.parseJSON(data);
            } catch (err) {
                alert('Bad JSON Encoding');
                return;
            }

            if (data.error) {
                $('#dbResult').text(data.error);
            } else if (data.success) {
                $('#dbResult').text(data.success);
            }
        }
    );
}

function checkStrength(pwTxt) {
    var strongRegex = new RegExp(
			"^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])(?!.*[<>])(?=.{8,50})");
    var rtn = true;
    if(strongRegex.test(pwTxt)) {
        rtn = true;
    } else {
        rtn = false;
    }
    return rtn;
}
