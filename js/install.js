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
    var strongRegex = /^.*(?=.{8,50})(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\!\@\#\$\%\^\&\*\(\)\-\=\_\+\~\.\,\<\>\/\?\;\:\'\"\\\|\[\]\{\}]).*$/g;
    var rtn = true;
    if(strongRegex.test(pwTxt)) {
        rtn = true;
    } else {
        rtn = false;
    }
    return rtn;
}
