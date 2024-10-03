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

function createAutoComplete(txtCtrl, minChars, inputParms, selectFunction, shoNew, searchURL, $basisCtrl, shoBlank = false) {
    
    var cache = {};
    var _source = function (request, response, cache, shoNew, inputParms, $basisCtrl, minChars, searchURL, shoBlank) {
    
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

            let notFoundTxt = "No one found";
            if(inputParms.cmd === 'diagnosis'){
                notFoundTxt = "Nothing found";
            }

             if (filtered.length === 0) {
                filtered.push({'id':'n', 'value':notFoundTxt});
                cache = {};
            }

            response( filtered );

        } else {

            inputParms.letters = term //request.term;

            // Get basis from active control
            if ($basisCtrl !== undefined && $basisCtrl !== null && $basisCtrl.length > 0) {
                inputParms.basis = $basisCtrl.val();
            }

            $.getJSON( searchURL, inputParms, function( data ) {

                if (data.gotopage) {
                    response();
                    window.open(data.gotopage);
                }

                cache[term] = data;
                if (shoBlank) {
                    data.unshift({ 'id': 0, 'value': "Unassigned"});
                }
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
            _source(request, response, cache, shoNew, inputParms, $basisCtrl, minChars, searchURL, shoBlank);
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


function createRoleAutoComplete(txtCtrl, minChars, inputParms, selectFunction, shoNew) {
    "use strict";
    var cache = {};
    
    const maxLength = 10;
    
    const _source = function (request, response, cache, shoNew, inputParms, minChars) {
    
        let term = request.term.toString().substr(0,minChars);
        
        if ( term in cache ) {

            let bldr, 
                terms = request.term.toString().replace(',', '').split(" "),
                matcher,
                filtered;

            if (terms.length > 1) {
                bldr = '\\b(' + $.ui.autocomplete.escapeRegex( terms[0] ) + ').+\\b(' + $.ui.autocomplete.escapeRegex( terms[1] ) + ')'
                        + '|\\b(' + $.ui.autocomplete.escapeRegex( terms[1] ) + ').+\\b(' + $.ui.autocomplete.escapeRegex( terms[0] ) + ')';
            } else {
                bldr = '\\b(' + $.ui.autocomplete.escapeRegex( request.term.toString().replace(',', '') ) + ')';
            }

            matcher = new RegExp( bldr , "i" );
            
            filtered = $.grep( cache[ term ], function( item ){
                return matcher.test( item.value );
            });

            if (shoNew) {
                filtered.unshift({'id':0, 'fullName':'<span class="ui-icon ui-icon-plusthick"></span><span style="padding-left:1em;">New Person</span>'});
            } else if (filtered.length === 0) {
                filtered.push({'id':'n', 'substitute':'No one found'});
                cache = {};
            }
            
            if(filtered.length > maxLength){
				filtered.unshift({'id':'n', 'substitute':'Keep typing... (More than ' + maxLength + ' results found)'});
			}

            response( filtered );

        } else {

            inputParms.letters = term;

            $.getJSON("../house/roleSearch.php", inputParms, function(data) {

                if (data.gotopage) {
                    response();
                    window.open(data.gotopage);
                }

	            if (shoNew) {
	                data.unshift({'id':0, 'fullName':'<span class="ui-icon ui-icon-plusthick"></span><span style="padding-left:1em;">New Person</span>'});
	            } else if (data.length === 0) {
	                data.push({'id':'n', 'substitute':'No one found'});
	                cache = {};
	            }
	            
	            if(data.length > maxLength){
					data.unshift({'id':'n', 'substitute':'Keep typing... (More than ' + maxLength + ' results found)'});
				}

                cache[ term ] = data;
                response( data );
            });
        }
    };

    if (shoNew === undefined || shoNew === null) {
        shoNew = true;
    }
    
    txtCtrl.autocomplete({
        source: function(request, response) {
            _source(request, response, cache, shoNew, inputParms, minChars);
        },
        position: { my: "left top", at: "left bottom", collision: "flip" },
        minLength: minChars, 
        select: function(event, ui) {
            if (ui.item) {
                selectFunction(ui.item);
            }
        },
        delay: 120
    })
    if(txtCtrl.autocomplete("instance") !== undefined){
	    txtCtrl.autocomplete( "instance" )
	    	._renderItem = function( ul, item ) {
				let firstRow = "", detailsRow = "", rightContent = "", mrnRow = "";
				if (item.noReturn === undefined) {
					item.noReturn = '';
				} else if (item.noReturn != '') {
					item.noReturn = "<span class='autocompleteNoReturn'>" + item.noReturn + "</span>";
                }
                if (item.room === undefined) {
					item.room = '';
				} else if (item.room != '') {
					item.room = "<span class='autocompleteRoom'>Room " + item.room + "</span>";
				}
				if (item.fullName === undefined) {
					item.fullName = ''
				} else if (item.fullName != '') {
					item.fullName = "<span class='autocompleteName'>" + item.fullName + "</span>";
				}
				if (item.memberStatus === undefined) {
					item.memberStatus = ''
				} else if (item.memberStatus != '') {
					item.memberStatus = "<span class='autocompleteMemStatus'>" + item.memberStatus + "</span>";
				}
				if (item.birthDate === undefined) {
					item.birthDate = ''
				} else if (item.birthDate != '') {
					item.birthDate = "<span style='margin-left:.5em;'>(" + item.birthDate + ")</span>";
				}
				if (item.mrn === undefined) {
					item.mrn = ''
				} else if (item.mrn != '') {
					item.mrn = "<span class='autocompleteMRN'>" + item.mrn + "</span>";
				}
				if (item.phone === undefined) {
					item.phone = ''
				} else if (item.phone != '') {
					item.phone = "<span style='margin-right:.5em;'>" + item.phone + "</span>";
				}
				if (item.city === undefined) {
					item.city = ''
				} else if (item.city != '') {
					item.city = "<span>" + item.city + "</span>";
				}
				if (item.state === undefined) {
					item.state = ''
				} else if (item.state != ''){
					let comma = '';
					if (item.city != '') {
						comma = ', ';
					}
					item.state = "<span>" + comma + item.state + "</span>";
				}
				if (item.substitute === undefined) {
					item.substitute = '';
				} else if (item.substitute != '') {
					item.substitute = "<span>" + item.substitute + "</span>";
				}
				
				firstRow = "<div class='autocompleteItemTitle'>" + item.substitute + item.fullName + item.birthDate + "</div>";

				if (item.phone != '' || item.city != '' || item.state != '') {
					detailsRow = "<div class='autocompleteItemDetails'>" + item.phone +  item.city + item.state + "</div>";
				}
				
				if(item.mrn != ''){
					mrnRow = "<div class='autocompleteItemMRN'>" + item.mrn + "</div>";
				}
				
				if(item.memberStatus != '' || item.noReturn != '' || item.room != ''){
					rightContent = "<div class='right'>" + item.memberStatus + item.room + "</div>";
                }
                
				
			    return $( "<li>" )
			        .append( "<div style='font-size:.85em;'><div class='hhk-flex'><div class='left'>" + firstRow + detailsRow + mrnRow + "</div>" + rightContent + "</div></div>" )
			        .appendTo( ul );
		};
		txtCtrl.autocomplete( "instance" )
			._resizeMenu = function() {
			//this.menu.element.outerWidth(this.element.outerWidth() * 2.7 );
			this.menu.element.outerWidth(txtCtrl.outerWidth());
		};
	}
}

