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

function sendHhkLogin() {
    
    var parms = {},
        $uname = $('#txtUname'),
        $psw = $('#txtPW'),
        $btn = $('#btnLogn'),
        $chall = $('#challenge');

    $('#errUname, #errPW').hide();
    $('#valMsg').text('');

    if ($uname.val() === '') {
        $('#errUname').text('-Enter your Username').show().css('color', 'red');
        return;
    }

    if ($psw.val() === '') {
        $('#errPW').text('-Enter your Password').css('color', 'red').show();
        return;
    }

    parms = {
        challenge: hex_md5(hex_md5($psw.val()) + $chall.val()),
        txtUname: $uname.val()
    };

    $.post('index.php', parms, function (data){
        try {
            data = $.parseJSON(data);
        } catch (err) {
            alert(data);
            $('#divLoginCtls').remove();
            return;
        }

        if (data.page && data.page !== '') {
            window.location.assign(data.page);
        }

        if (data.mess) {
            $('#valMsg').text(data.mess);
        }

        if (data.chall && data.chall !== '') {
            $chall.val(data.chall);
        }

        if (data.stop) {
            $btn.remove();
        }
    });

}

$(document).ready(function () {
    var $psw = $('#txtPW'),
        $btn = $('#btnLogn');

    $btn.button();
    $btn.click(function () {
        sendHhkLogin();
    });
    
    $('#txtPW, #txtUname').keypress(function (event) {
        if (event.keyCode == '13') {
            sendHhkLogin();
        }
    });

    $('#divLoginCtls').on('click', '#errUname, #errPW', function () {
        $(this).hide();
    });


    $psw.val('');
    $('#txtUname').focus();
});
