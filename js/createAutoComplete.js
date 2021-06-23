function createZipAutoComplete(txtCtrl, wsUrl, lastXhr, selCallback, csrfToken) {
    "use strict";
    txtCtrl.autocomplete({
        source: function(request, response) {
              lastXhr = $.getJSON(wsUrl, {zip: request.term, cmd: 'schzip', csrfToken: csrfToken})
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
            $('#' + prf + 'adrzip' + idx).val(ui.item.value);
            
            if ( $('#' + prf + 'adrcounty' + idx).length > 0) {
                $('#' + prf + 'adrcounty' + idx).val(ui.item.County);
            }
            
            $('#' + prf + 'adrcountry' + idx).val('US');
            $('#' + prf + 'adrcountry' + idx).change();
            $('#' + prf + 'adrstate' + idx).val(ui.item.State);

            
            if ($.isFunction(selCallback)) {
                selCallback(prf);
            }
        }
    });
}
    
function createAutoComplete(txtCtrl, minChars, inputParms, selectFunction, shoNew, searchURL, $basisCtrl) {
    "use strict";
    var cache = {};
    var _source = function (request, response, cache, shoNew, inputParms, $basisCtrl, minChars, searchURL) {
    
        var term = request.term.substr(0,minChars);
        if ( term in cache ) {

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

            var t;
            for (t in cache[term]) {
                if (cache[term][t].id === 'i' || cache[term][t].id === 'o') {
                    filtered.push(cache[term][t]);
                }
            }

            if (shoNew) {
                filtered.push({'id':0, 'value':'New Person'});
            }

             if (filtered.length === 0) {
                filtered.push({'id':'n', 'value':'No one found'});
                cache = {};
            }

            response( filtered );

        } else {

            inputParms.letters = term;

            // Get basis from active control
            if ($basisCtrl !== undefined && $basisCtrl.length > 0) {
                inputParms.basis = $basisCtrl.val();
            }

            $.getJSON( searchURL, inputParms, function( data, status, xhr ) {

                if (data.gotopage) {
                    response();
                    window.open(data.gotopage);
                }

                cache[ term ] = data;
                response( data );
            });
        }
    };

    if (shoNew === undefined || shoNew === null) {
        shoNew = true;
    }
    
    if (searchURL === undefined || searchURL === null) {
        searchURL = "../house/roleSearch.php";
    }
    
    txtCtrl.autocomplete({
        source: function(request, response) {
            _source(request, response, cache, shoNew, inputParms, $basisCtrl, minChars, searchURL);
        },
        position: { my: "left top", at: "left bottom", collision: "flip" },
        minLength: minChars, 
        select: function(event, ui) {
            if (ui.item) {
                selectFunction(ui.item);
            }
        },
        delay: 120
    });
}

