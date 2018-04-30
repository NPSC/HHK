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

/**
 * 
 * @param {string} containerId
 * @returns {undefined}
 */
function hideAlertMessage(containerId) {
        "use strict";
    if (!containerId) {
        containerId = 'divAlert1';
    }
    $("#" + containerId + ":visible").removeAttr("style").hide();
}
/**
 * 
 * @param {string} mess
 * @param {boolean} wasError
 * @returns {undefined}
 */
function flagAlertMessage(mess, wasError) {
    "use strict";
    var spn = document.getElementById('alrMessage');
    if (!wasError) {
        // define the success message markup
        $('#alrResponse').removeClass("ui-state-error").addClass("ui-state-highlight");
        $('#alrIcon').removeClass("ui-icon-alert").addClass("ui-icon-info");
        spn.innerHTML = "<strong>Success: </strong>" + mess;
        $("#divAlert1").show("slide");
        window.scrollTo(0, 5);
    } else {
        // define the error message markup
        $('alrResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
        $('#alrIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
        spn.innerHTML = "<strong>Alert: </strong>" + mess;
        $("#divAlert1").show("pulsate");
        window.scrollTo(0, 5);
    }
}

function dateRender(data, type, format) {
    // If display or filter data is requested, format the date
    if ( type === 'display' || type === 'filter' ) {

        if (data === null || data === '') {
            return '';
        }

        data = data.trim();

        if (data === null || data === '') {
            return '';
        }

        if (!format || format === '') {
            format = 'MMM D, YYYY';
        }

        return moment(data).format(format);
    }

    // Otherwise the data type requested (`type`) is type detection or
    // sorting data, for which we want to use the integer, so just return
    // that, unaltered
    return data;
}

$(document).ready(function() {
    "use strict";

    //Hover states on the nav bar left icons.
   $( "ul.hhk-ui-icons li" ).hover(
       function() {
               $( this ).addClass( "ui-state-hover" );
       },
       function() {
               $( this ).removeClass( "ui-state-hover" );
       }
   );
   
   $('#contentDiv').css('margin-top', $('#global-nav').css('height'));

   
});
