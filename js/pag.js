/**
 * 
 * @param {string} mess
 * @param {boolean} wasError
 * @returns {undefined}
 */
function flagAlertMessage(mess, wasError, $txtCtrl) {
    "use strict";
    //Types:  alert, success, warning, error, info/information
    var type = 'info';
    
    if (!mess || mess == '') {
        return;
    }

    if (typeof wasError === 'boolean') {
        type = (wasError ? 'error' : 'success');
    } else if (typeof wasError === 'string') {
        type = wasError;
    }

    try {
        new Noty(
            {
                type: type,
                text: mess
            }
        ).show();
    } catch(err) {
        // do nothing for now.
    }
    
    // Show message in a given container.
    $txtCtrl.text(mess).show();
}

//function altFlagAlertMessage(mess, wasError) {
//    
//    var spn = document.getElementById('alrMessage');
//    if (!wasError) {
//        // define the success message markup
//        $('#alrResponse').removeClass("ui-state-error").addClass("ui-state-highlight");
//        $('#alrIcon').removeClass("ui-icon-alert").addClass("ui-icon-info");
//        spn.innerHTML = "<strong>Success: </strong>" + mess;
//        $("#divAlert1").show();
//        window.scrollTo(0, 5);
//    } else {
//        // define the error message markup
//        $('alrResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
//        $('#alrIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
//        spn.innerHTML = "<strong>Alert: </strong>" + mess;
//        $("#divAlert1").show("pulsate");
//        window.scrollTo(0, 5);
//    }
//
//}
//
function dateRender(data, type, format) {
    // If display or filter data is requested, format the date
    if (type === 'display' || type === 'filter') {

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

function isIE() {
    var ua = window.navigator.userAgent;
    return /MSIE|Trident/.test(ua);
}

$(document).ready(function () {
    "use strict";

    //Hover states on the nav bar left icons.
    $("ul.hhk-ui-icons li").hover(
            function () {
                $(this).addClass("ui-state-hover");
            },
            function () {
                $(this).removeClass("ui-state-hover");
            }
    );

    $('#contentDiv').css('margin-top', $('#global-nav').css('height'));

});
