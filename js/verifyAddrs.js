function createZipAutoComplete(txtCtrl, wsUrl, lastXhr) {
    "use strict";
    txtCtrl.autocomplete({
        source: function(request, response) {
              lastXhr = $.getJSON(wsUrl, {zip: request.term, cmd: 'schzip'})
                .done(function(data, status, xhr) {
                    if (xhr === lastXhr) {
                        if (data && data.error) {
                            if (data.gotopage) {
                                window.open(data.gotopage);
                            }
                            data.value = data.error;
                        }
                        response(data);
                    } else {
                        response();
                    }
                })
                .fail(function(jqxhr, textStatus, error) {
                    var err = textStatus + ', ' + error;
                    alert("Postal code request failed: " + err);
                });
        },
        position: { my: "left top", at: "left bottom", collision: "flip" },
        delay: 100,
        minLength: 3,
        select: function(event, ui) {
            if (!ui.item) {
                return;
            }
            var idx = $(this).data('hhkindex');
            var prf = $(this).data('hhkprefix');

            $('#' + prf + 'adrcity' + idx).val(ui.item.City);
            $('#' + prf + 'adrcountry' + idx).val('US');
            $('#' + prf + 'adrcountry' + idx).change();
            $('#' + prf + 'adrstate' + idx).val(ui.item.State);
            
            if ( $('#' + prf + 'adrcounty' + idx).length > 0) {
                $('#' + prf + 'adrcounty' + idx).val(ui.item.County);
            }
        }
    });
}

function createAutoComplete(txtCtrl, minChars, inputParms, selectFunction, shoNew, searchURL) {
    "use strict";
    var cache = {};
    
    if (shoNew === undefined || shoNew === null) {
        shoNew = true;
    }
    
    if (searchURL === undefined || searchURL === null) {
        searchURL = "../house/roleSearch.php";
    }
    
    txtCtrl.autocomplete({
        source: function(request, response) {
            
            var term = request.term.substr(0,minChars);
            if ( term in cache ) {
                
                txtCtrl.autocomplete( "option", "delay", 0 );
                var bldr, 
                    terms = request.term.replace(',', '').split(" "),
                    matcher,
                    filtered;
                
                if (terms.length > 1) {
                    bldr = '\\b(' + $.ui.autocomplete.escapeRegex( terms[0] ) + ').+\\b(' + $.ui.autocomplete.escapeRegex( terms[1] ) + ')'
                            + '|\\b(' + $.ui.autocomplete.escapeRegex( terms[1] ) + ').+\\b(' + $.ui.autocomplete.escapeRegex( terms[0] ) + ')';
                } else {
                    bldr = '\\b(' + $.ui.autocomplete.escapeRegex( request.term ) + ')';
                }
                
                matcher = new RegExp( bldr , "i" );
                filtered = $.grep( cache[ term ], function( item ){
                    return matcher.test( item.value );
                });
                
                if (filtered.length === 0) {
                    filtered.push({'id':0, 'value':'No one found'});
                    cache = {};
                }
                
                if (shoNew) {
                    filtered.push({'id':0, 'value':'New Person'});
                }
                
                response( filtered );

                return;
            }

            txtCtrl.autocomplete( "option", "delay", 120 );
            inputParms.letters = request.term;
            $.getJSON( searchURL, inputParms, function( data, status, xhr ) {
                
                if (data.gotopage) {
                    response();
                    window.open(data.gotopage);
                }

              cache[ term ] = data;
              response( data );
            });

        },
        position: { my: "left top", at: "left bottom", collision: "flip" },
        minLength: minChars, 

        select: function(event, ui) {
            if (ui.item) {
                selectFunction(ui.item);
            }
        }
    });
}
function verifyAddrs(container) {
    "use strict";
    $(container).on('change', 'input.hhk-emailInput', function() {
        var rexEmail = /^[A-Z0-9._%+\-]+@(?:[A-Z0-9]+\.)+[A-Z]{2,4}$/i;
        if ($.trim($(this).val()) !== '' && rexEmail.test($(this).val()) === false) {
            $(this).addClass('ui-state-error');
        } else {
            $(this).removeClass('ui-state-error');
        }
    });
    $(container).on('change', 'input.hhk-phoneInput', function() {
        // inspect each phone number text box for correctness
        var testreg = /^([\(]{1}[0-9]{3}[\)]{1}[\.| |\-]{0,1}|^[0-9]{3}[\.|\-| ]?)?[0-9]{3}(\.|\-| )?[0-9]{4}$/;
        var regexp = /^(?:(?:[\+]?([\d]{1,3}(?:[ ]+|[\-.])))?[(]?([2-9][\d]{2})[\-\/)]?(?:[ ]+)?)?([2-9][0-9]{2})[\-.\/)]?(?:[ ]+)?([\d]{4})(?:(?:[ ]+|[xX]|(i:ext[\.]?)){1,2}([\d]{1,5}))?$/;
        var numarry;
        if ($.trim($(this).val()) != '' && testreg.test($(this).val()) === false) {
            // error
            $(this).addClass('ui-state-error');

        } else {
            $(this).removeClass('ui-state-error');
            regexp.lastIndex = 0;
            // 0 = matached, 1 = 1st capturing group, 2 = 2nd, etc.
            numarry = regexp.exec($(this).val());
            if (numarry != null && numarry.length > 3) {
                var ph = "";
                // Country code?
                if (numarry[1] != null && numarry[1] != "") {
                    ph = '+' + numarry[1];
                }
                // The main part
                $(this).val(ph + '(' + numarry[2] + ') ' + numarry[3] + '-' + numarry[4]);
                // Extension?
                if (numarry[6] != null && numarry[6] != "") {
                    $(this).next('input').val(numarry[6]);
                }
            }
        }
    });
    $(container).on('change', 'input.ckzip', function() {
        var postCode = /^(?:[A-Z]{1,2}[0-9][A-Z0-9]? [0-9][ABD-HJLNP-UW-Z]{2}|[ABCEGHJKLMNPRSTVXY][0-9][A-Z] [0-9][A-Z][0-9]|[0-9]{5}(?:\-[0-9]{4})?)$/i;
        if ($(this).val() !== "" && !postCode.test($(this).val())) {
            $(this).addClass('ui-state-error');
        } else {
            $(this).removeClass('ui-state-error');
        }
    });
}