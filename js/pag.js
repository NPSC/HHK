/**
 * 
 * @param {string} mess
 * @param {boolean} wasError
 * @returns {undefined}
 */
function flagAlertMessage(mess, wasError) {
    "use strict";
    //Types:  alert, success, warning, error, info/information
    var type = 'info';
    
    if (typeof wasError === 'boolean') {
        type = (wasError ? 'error' : 'success');
    } else if (typeof wasError === 'string') {
        type = wasError;
    }
    
    new Noty(
            {
                type: type,
                text: mess
            }
            ).show();
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
