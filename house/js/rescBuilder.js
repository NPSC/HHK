function isNumber(n) {
    "use strict";
    return !isNaN(parseFloat(n)) && isFinite(n);
}

var fixedRate = $('#fixedRate').val();
var savedRow;

function getRoomFees(cat) {
    if (cat != '' && cat != fixedRate) {
        // go get the total
        var ds = parseInt($('#txtNites').val(), 10);
        if (isNaN(ds)) {
            ds = 0;
        }
        var ct = parseInt($('#txtCredit').val(), 10);
        if (isNaN(ct)) {
            ct = 0;
        }
        $('#spnAmount').text('').addClass('ui-autocomplete-loading');
        $.post('ws_ckin.php', {
            cmd: 'rtcalc',
            rcat: cat,
            nites: ds,
            credit: ct
        }, function (data) {
            $('#spnAmount').text('').removeClass('ui-autocomplete-loading');
            data = $.parseJSON(data);
            if (data.error) {
                if (data.gotopage) {
                    window.open(data.gotopage, '_self');
                }
                flagAlertMessage(data.error, true);
                return;
            }
            if (data.amt) {
                $('#spnAmount').text(data.amt);
            }
            if (data.cat) {
                $('#selRateCategory').val(cat);
            }
        });
    }
}
function setupRates() {
    "use strict";
    $('#txtFixedRate').change(function () {
        if ($('#selRateCategory').val() == fixedRate) {
            var amt = parseFloat($(this).val());
            if (isNaN(amt) || amt < 0) {
                amt = parseFloat($(this).prop("defaultValue"));
                if (isNaN(amt) || amt < 0)
                    amt = 0;
                $(this).val(amt);
            }
            var ds = parseInt($('#txtNites').val(), 10);
            if (isNaN(ds)) {
                ds = 0;
            }
            $('#spnAmount').text(amt * ds);
        }
    });
    $('#txtNites, #txtCredit').change(function () {
        getRoomFees($('#selRateCategory').val());
    });
    $('#selRateCategory').change(function () {
        if ($(this).val() == fixedRate) {
            $('.hhk-fxFixed').show();
        } else {
            $('.hhk-fxFixed').hide();
            getRoomFees($(this).val());
        }
        $('#txtFixedRate').change();
    });
    $('#selRateCategory').change();
}
function getResource(idResc, type, trow) {
    "use strict";
    if ($('#cancelbtn').length > 0) {
        $('#cancelbtn').click();
    }
    $.post('ws_resc.php', {
        cmd: 'getResc',
        tp: type,
        id: idResc
    }, function (data) {
        if (data) {
            try {
                data = $.parseJSON(data);
            } catch (err) {
                alert("Parser error - " + err.message);
                return;
            }
            if (data.error) {
                if (data.gotopage) {
                    window.open(data.gotopage, '_self');
                }
                flagAlertMessage(data.error, true);
                return;
            }
            if (data.row) {
                savedRow = trow.children();
                trow.children().remove().end().append($(data.row));
                $('#savebtn').button().click(function () {
                    var btn = $(this);
                    saveResource(btn.data('id'), btn.data('type'), btn.data('cls'));
                });
                $('#cancelbtn').button().click(function () {
                    trow.children().remove().end().append(savedRow);
                    $('.reNewBtn').button();
                });
            }
        }
    });
}
function getStatusEvent(idResc, type, title) {
    "use strict";
    $.post('ws_resc.php', {
        cmd: 'getStatEvent',
        tp: type,
        title: title,
        id: idResc
    }, function (data) {
        if (data) {
            try {
                data = $.parseJSON(data);
            } catch (err) {
                alert("Parser error - " + err.message);
                return;
            }
            if (data.error) {
                if (data.gotopage) {
                    window.open(data.gotopage, '_self');
                }
                flagAlertMessage(data.error, true);
                return;
            }
            if (data.tbl) {
                $('#statEvents').children().remove().end().append($(data.tbl));
                $('.ckdate').datepicker({autoSize: true, dateFormat: 'M d, yy'});
                var buttons = {
                    "Save": function () {
                        saveStatusEvent(idResc, type);
                    },
                    'Cancel': function () {
                        $(this).dialog('close');
                    }
                };
                $('#statEvents').dialog('option', 'buttons', buttons);
                $('#statEvents').dialog('open');
            }
        }
    });
}
function saveStatusEvent(idResc, type) {
    "use strict";
    $.post('ws_resc.php', $('#statForm').serialize() + '&cmd=saveStatEvent' + '&id=' + idResc + '&tp=' + type,
            function (data) {
                $('#statEvents').dialog('close');
                if (data) {
                    try {
                        data = $.parseJSON(data);
                    } catch (err) {
                        alert("Parser error - " + err.message);
                        return;
                    }
                    if (data.error) {
                        if (data.gotopage) {
                            window.open(data.gotopage, '_self');
                        }
                        flagAlertMessage(data.error, true);
                        return;
                    }

                    if (data.msg && data.msg != '') {
                        flagAlertMessage(data.msg, false);
                    }

                }
            });
}
function saveResource(idresc, type, clas) {
    "use strict";
    var parms = {};
    $('.' + clas).each(function () {

        if ($(this).attr('type') === 'radio' || $(this).attr('type') === 'checkbox') {
            if (this.checked !== false) {
                parms[$(this).attr('id')] = 'on';
            }
        } else {
            parms[$(this).attr('id')] = $(this).val();
        }
    });
    $.post('ws_resc.php', {
        cmd: 'redit',
        tp: type,
        id: idresc,
        parm: parms
    }, function (data) {
        if (data) {
            try {
                data = $.parseJSON(data);
            } catch (err) {
                alert("Parser error - " + err.message);
                return;
            }
            if (data.error) {
                if (data.gotopage) {
                    window.open(data.gotopage, '_self');
                }
                flagAlertMessage(data.error, true);
                return;
            } else if (data.roomList) {
                $('#roomTable').children().remove().end().append($(data.roomList));
                $('#tblroom').dataTable({
                    "dom": '<"top"if>rt<"bottom"lp><"clear">',
                    "displayLength": 50,
                    "order": [[1, 'asc']],
                    "lengthMenu": [[20, 50, -1], [20, 50, "All"]]
                });
            } else if (data.rescList) {
                $('#rescTable').children().remove().end().append($(data.rescList));
                $('#tblresc').dataTable({
                    "dom": '<"top"if>rt<"bottom"lp><"clear">',
                    "displayLength": 50,
                    "order": [[1, 'asc']],
                    "lengthMenu": [[20, 50, -1], [20, 50, "All"]]
                });
            } else if (data.constList) {
                $('#constr').children().remove().end().append($(data.constList));
            }
            $('.reNewBtn').button();
        }
    });
}

$(document).ready(function () {
    "use strict";
    
	$('#formBuilder').hhkFormBuilder({
		labels: $.parseJSON($("#labels").val()),
		fieldOptions:$.parseJSON($("#frmOptions").val()),
		demogs: $.parseJSON($('#frmDemog').val())
	});

    var tabIndex = parseInt($('#tabIndex').val());
    $('#btnMulti, #btnkfSave, #btnNewK, #btnNewF, #btnAttrSave, #btnhSave, #btnItemSave, .reNewBtn').button();

    $('#txtFaIncome, #txtFaSize').change(function () {
        var inc = $('#txtFaIncome').val().replace(',', ''),
                size = $('#txtFaSize').val(),
                errmsg = $('#spnErrorMsg');
        errmsg.text('');
        $('#txtFaIncome, #txtFaSize, #spnErrorMsg').removeClass('ui-state-highlight');
        if (inc == '' || size == '') {
            $('#spnFaCatTitle').text('');
            $('#hdnRateCat').val('');
            return false;
        }
        if (inc == '' || inc == '0' || isNaN(inc)) {
            $('#txtFaIncome').addClass('ui-state-highlight');
            errmsg.text('Fill in the Household Income').addClass('ui-state-highlight');
            return false;
        }
        if (size == '' || size == '0' || isNaN(size)) {
            $('#txtFaSize').addClass('ui-state-highlight');
            errmsg.text('Fill in the Household Size').addClass('ui-state-highlight');
            return false;
        }
        $.post('ws_ckin.php', {
            cmd: 'rtcalc',
            income: inc,
            hhsize: size,
            nites: 0
        }, function (data) {
            data = $.parseJSON(data);
            if (data.catTitle) {
                $('#spnFaCatTitle').text(data.catTitle);
            }
            if (data.cat) {
                $('#hdnRateCat').val(data.cat);
            }
        });
        return false;
    });
    setupRates();
    $('#mainTabs').tabs();
    $('#mainTabs').tabs("option", "active", tabIndex);
    $('#statEvents').dialog({
        autoOpen: false,
        resizable: true,
        width: getDialogWidth(1000),
        modal: true,
        title: 'Manage Status Events'
    });
    $('#divNewForm').dialog({
        autoOpen: false,
        resizable: true,
        width: getDialogWidth(425),
        modal: true,
        title: 'Create New Form',
        buttons: {
            "Create New Form": function() {
                var fmType = $('#hdnFormType').val(),
                    fmLang = $('txtformLang').val();

                if (fmType !== '' && fmLang !== '') {
                    // Make a new form
                    $('#formFormNew').submit();
                    $(this).dialog("close");
                }
            },
            "Cancel": function() {
                $(this).dialog("close");
                $('#regTabDiv').tabs('option', 'active', 0);
            }
        }
    });

    $('div#mainTabs').on('click', '.reEditBtn, .reNewBtn', function () {
        getResource($(this).attr('name'), $(this).data('enty'), $(this).parents('tr'));
    });
    $('div#mainTabs').on('click', '.reStatBtn', function () {
        getStatusEvent($(this).attr('name'), $(this).data('enty'), $(this).data('title'));
    });
    $('#btnNewForm').button().click(function () {
    	$('#divNewForm').dialog('open');
    });
    $('#selFormUpload').change(function (e, changeEventData) {
		
        $('#hdnFormType').val('');
        
        if ($(this).val() == '') {
        	$('#divUploadForm').empty();
        	$('#btnNewForm').hide()
        	return;
        }
        $('#spnFrmLoading').show();
        
        $.post('ResourceBuilder.php', {'ldfm': $(this).val()},
            function (data) {
                $('#spnFrmLoading').hide();

                if (data) {
                	data = $.parseJSON(data);

                    $('#divUploadForm').empty().append(data.mkup);
                    $('#btnNewForm').show();
                    $('#hdnFormType').val(data.type);
                   	$('#spanFrmTypeTitle').text(data.title);
                   	$('#txtformLang').val('');

                    $('#regTabDiv').tabs({
                        collapsible: true,
                    });

                    $('#regTabDiv .ui-tabs-nav').sortable({
                        axis: "x",
                        items: "> li.hhk-sortable",
                        stop: function(event, ui) {
                          $('#regTabDiv').tabs( "refresh" );

                          var order = $('#regTabDiv .ui-tabs-nav').sortable('toArray', {'attribute': 'data-code'});
                          
						  data = {
								  'cmd':'reorderfm',
								  'formDef':$(document).find('#regTabDiv').data('formdef'),
								  'order':order
								  };
						  
                          $.ajax({
                       		url: 'ResourceBuilder.php',
                        	type: "POST",
                        	data: JSON.stringify(data),
                        	processData: false,
                        	contentType: "application/json; charset=UTF-8",
                        	dataType: 'json',
                        	success: function(data) {
                            	if(data && data.status == "error"){
                            		flagAlertMessage("Unable to set form order: " + data.message, true);
                            	}
                        	}
                          });
                        }
                      });

                    $('#divUploadForm button').button();
                    $('#divUploadForm input[type=submit]').button();
                    if(changeEventData && changeEventData.docCode){
                    	$('#regTabDiv').find('li.ui-tab[aria-selected=false] #docTab-' + changeEventData.docCode).click();
                    }
                }
            });
    });
    $('#selFormUpload').change();

	$(document).on("click", ".uploadFormDiv form #docDelFm", function(e){
		$(".uploadFormDiv form input[name=docAction]").val("docDelete");
	});
	
	$(document).on("click", ".uploadFormDiv form #docSaveFm", function(e){
		$(".uploadFormDiv form input[name=docAction]").val("docUpload");
	});

	$(document).on("submit", ".uploadFormDiv form, #formFormNew", function(e) {
	    e.preventDefault();
	    var formData = new FormData(this);
	
		$.ajax({
	        url: $(this).attr("action"),
	        type: 'POST',
	        data: formData,
	        dataType: "json",
	        success: function (data) {
	        	if(data.success){
	        		flagAlertMessage(data.success, false);
	        	}else if(data.error){
	        		flagAlertMessage(data.error, true);
	        	}
	            if(data.docCode){
	            	$('#selFormUpload').trigger('change', [{"docCode":data.docCode}]);
	            }else{
	            	$('#selFormUpload').change();
	            }
	        },
	        cache: false,
	        contentType: false,
	        processData: false
	    });
	});

    $('#tblroom, #tblresc').dataTable({
        "dom": '<"top"if>rt<"bottom"lp><"clear">',
        "displayLength": 50,
        "order": [[1, 'asc']],
        "lengthMenu": [[20, 50, -1], [20, 50, "All"]]
    });
    
    $('.hhk-selLookup').change(function () {
        let $sel = $(this),
            table = $(this).find("option:selected").text(),
            type = $(this).val();

        if ($sel.data('type') === 'd') {
            table = $sel.val();
            type = 'd';
        }else if($sel.val() == "ReservStatus"){
			table = "ReservStatus";
			type = "ReservStatus";
        }else if ($sel.data('type') === 'insurance'){
        	table = "insurance";
        	type = $sel.val();
        }

        $sel.closest('form').children('div').empty().text('Loading...');
        $sel.prop('disabled', true);

        $.post('ResourceBuilder.php', {table: table, cmd: "load", tp: type},
                function (data) {
                    $sel.prop('disabled', false);
                    if (data) {
                        $sel.closest('form').children('div').empty().append(data).find(".sortable tbody")
                        	.sortable({
                        		items: "tr:not(.no-sort)",
                        		handle: ".sort-handle",
                        		update: function (e, ui) {
                        			$(this).find("tr").each(function(i){
                        				$(this).find("td:first input").val(i);
                        			});
                        		}
                        	});
                    }
                });
    });
    $('.hhk-saveLookup').click(function () {
        let $frm = $(this).closest('form');
        let sel = $frm.find('select.hhk-selLookup');
        let table = sel.find('option:selected').text(),
            type = $frm.find('select').val(),
            $btn = $(this);

        if (sel.data('type') === 'd') {
            table = sel.val();
            type = 'd';
        }

        if ($btn.val() === 'Saving...') {
            return;
        }

        $btn.val('Saving...');

        $.post('ResourceBuilder.php', $frm.serialize() + '&cmd=save' + '&table=' + table + '&tp=' + type,
            function(data) {
                $btn.val('Save');
                if (data) {
                    $frm.children('div').empty().append(data).find(".sortable tbody")
                        	.sortable({
                        		items: "tr:not(.no-sort)",
                        		handle: ".sort-handle",
                        		update: function (e, ui) {
                        			$(this).find("tr").each(function(i){
                        				$(this).find("td:first input").val(i);
                        			});
                        		}
                        	});
                    
                }
            });
    }).button();

    $('#btndemoSave').click(function () {
        var $frm = $(this).closest('form');

        $.post('ResourceBuilder.php', $frm.serialize() + '&cmd=save' + '&table=' + 'Demographics' + '&tp=' + 'm',
            function(data) {
                if (data) {
                    $frm.children('div').children().remove().end().append(data).find(".sortable tbody")
                        	.sortable({
                        		items: "tr:not(.no-sort)",
                        		handle: ".sort-handle",
                        		update: function (e, ui) {
                        			$(this).find("tr").each(function(i){
                        				$(this).find("td:first input").val(i);
                        			});
                        		}
                        	});
                }
            });
    }).button();
    
    $(document).on("click", "#btnInsSave", function (e) {
        var $frm = $(this).closest('form');

        $.post('ResourceBuilder.php', $frm.serialize(),
            function(data) {
            	if(data.success){
            		flagAlertMessage(data.success, false);
            	}else if(data.error){
            		flagAlertMessage(data.error, true);
            	}
                $("#selInsLookup").trigger("change");
            },
            "json");
    })

    // Add diagnosis and locations
    if ($('#btnAddDiags').length > 0) {
        $('#btnAddDiags').button();
    }
    if ($('#btnAddLocs').length > 0) {
        $('#btnAddLocs').button();
    }
    if ($('#btnHouseDiscs').length > 0) {
        $('#btnHouseDiscs').button();
    }
    if ($('#btnAddnlCharge').length > 0) {
        $('#btnAddnlCharge').button();
    }

    $('input.number-only').change(function () {
        if (isNumber(this.value) === false) {
            $(this).val('0');
        }
        $(this).val(this.value);
    });
    $('#mainTabs').addClass('d-inline-block');

    $(document).on('click', '.replaceForm', function(){
        var form = $(this).closest("div.row").find('.uploadFormDiv');
        if(form.is(':hidden')){
			$(this).text('Cancel');
        }else{
			$(this).text('Replace Form');
			form.find('input[type=file]').val('');
        }
		form.toggle();
		
    });
});
