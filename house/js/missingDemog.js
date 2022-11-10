$(document).ready(function () {
    "use strict";
    
    var columns = JSON.parse($('#columns').val());
    var demos = JSON.parse($('#demos').val());
    var dtCols = [];
    var target = 0;
    
    columns.forEach(function(column) {
    	var title;
    	var render;
    	var search = false;
    	if(column.db == "idName"){
    		title = column.dt;
    		search = true;
    		render = function ( data, type ){
    			return '<a href="GuestEdit.php?id=' + data + '">' + data + '</a>';
    		}
    	}else if(demos[column.dt]){
    		search = true;
    		title = demos[column.dt].title;
    		render = function ( data, type, row ) {
    			var select = $("<select>").attr("name", 'sel' + column.dt + '[' + row.id + ']').addClass('demog');
    			var option = $("<option>");
    			select.append(option);
    			$.each(demos[column.dt].list, function(key, item){
    				var option = $("<option>").attr("value", item[0]).text(item[1]);
    				if(data == item[0]){
    					option.attr("selected","selected");
    				}
    				select.append(option);
    			});
                return select[0].outerHTML;
    		}
    	}else{
    		title = column.dt;
    		search = true;
    		render = function ( data, type ){
    			return data;
    		}
    	}
    	
    	dtCols.push({
    		"targets": [ target ],
    		"title": title,
    		"searchable": search,
    		"sortable": search,
    		"data": column.dt,
    		render: render
    	});
    	
    	target++;
    });
    
    dtCols.push({
		"targets": [ target ],
		"title": "Unknown",
		"searchable": false,
		"sortable": false,
		"data": "idName",
		className: "dt-body-center",
		render: function ( data, type ){
			return $("<input>").attr({"type":"checkbox", "class": "cbUnkn"})[0].outerHTML;
		}
	});
    
    var filterData = $('#fcat').serializeArray();
    
    var missingDemogTable = $('#dataTbl').DataTable({
        "columnDefs": dtCols,
        "serverSide": true,
        "processing": true,
        "deferRender": true,
        "language": {"search": "Search missing demographics:"},
        "sorting": [[0,'desc']],
        "displayLength": 25,
        "lengthMenu": [[10, 25, 50], [10, 25, 50]],
        "dom": '<"top fg-toolbar ui-toolbar ui-widget-header ui-helper-clearfix ui-corner-tl ui-corner-tr"lf><"hhk-overflow-x"rt><"bottom fg-toolbar ui-toolbar ui-widget-header ui-helper-clearfix ui-corner-bl ui-corner-br"ip>',
        ajax: {
            url: "GuestDemog.php",
            type: "post",
            data: function(d){
            	d.cmd = "getMissingDemog";
            	$.each(filterData, function(k,v){
            		d[v.name] = v.value;
            	});
            }
        },
        /* "fixedHeader": {
        	headerOffset: 38,
        }, */
        "initComplete": function(settings, json) {
        	$('.bottom').append('<div class="savebtns" style="float:right; padding-top: 0.25em;"><button id="dt-cancel" style="padding:0.5em; margin-right: 2px;">Cancel</button><button id="dt-save" style="padding:0.5em; margin-left: 2px;">Save</button></div>');
        	$('.bottom .savebtns').buttonset().hide();
        	
        	this.api().columns().every( function () {
                var column = this;
                var filter = false;
                //get column title from columns object
                if(columns[column.index()]){
                	var columnTitle = columns[column.index()].dt;
                }else{
                	var columnTitle = dtCols[column.index()].title;
                }
                
                if(demos[columnTitle]){
                //if(column.index() > 2){
	                var filter = $("<select>").prop("multiple","multiple").addClass("filter");
	                var option = $("<option>").prop("value", "").text("Not set");
	                filter.append(option);
	    			$.each(demos[columnTitle].list, function(key, item){
	    				var option = $("<option>").attr("value", item[0]).text(item[1]);
	    				filter.append(option);
	    			});
                };

                if(filter){
                    filter.appendTo( $(column.header()))
                    .on( 'change', function () {
                    	if($(".bottom .savebtns").is(':visible')){
                    		if(confirm("You have unsaved data, would you like to save first?")){
                    			$(".savebtns #dt-save").click();
                    		}else{
                    			var prevValue = $(this).data('prevValue');
                    			$(this).val(prevValue);
                    			$(this).multiselect("refresh");
                    			$(this).blur();
                    			return;
                    		}
                    	}
                    	
                    	$(this).data("prevValue", $(this).val());
                    	
                        var data = $(this).val();
                        
                        if($.isArray(data)){
                            $.each(data, function(i,v){
								data[i] = v ? '^' + v + '$': '^$';
                            });
							var searchStr = data.join('|');
                        }else{
                        	var searchStr = data ? '^' + $.fn.dataTable.util.escapeRegex(
                            	data
                            ) + '$' : '^$';
                        }
                        
                        column
                        .search(searchStr, true, false )
                        .draw();
                    } );
                    
                    filter.click( function(e) {
                        e.stopPropagation();
                    });

                    if(filter.is("select")){
						filter.multiselect({
							noneSelectedText: "Select Filter",
							buttonWidth: "150",
							selectedList: 3
                        });
                    }
                }else{
					filter = $('<div>&nbsp;</div>').appendTo( $(column.header()));
                }
        	});
        },
        "drawCallback": function(){
        	$('.bottom .savebtns').hide();
        	$('.bottom .dataTables_paginate').show();
        }
    });
    
    $('#dataTbl').on('change', '.cbUnkn', function(e){
    	var cb = $(this);
    	var row = cb.closest("tr");
    	if(cb.prop("checked")){
    		row.find('select').each(function(k,select){
    			if($(select).val() == ""){
    				$(select).find('option[value="z"]').attr("selected", "selected");
    			}
    		});
    	}else{
    		row.find('select').each(function(k,select){
    			if($(select).val() == "z"){
    				$(select).find('option[value="z"]').removeAttr("selected");
    			}
    		});
    	}
    	
    	row.find("select").trigger("change");
    });
    
    $('#dataTbl').on('change', 'select.demog', function(e){
    	$(".bottom .dataTables_paginate").hide();
    	$(".bottom .savebtns").show();
    });
    
    $(document).on("click", ".savebtns #dt-cancel", function(e){
    	$('#dataTbl').DataTable().ajax.reload(null, false);
    });
    
    $(document).on("click", ".savebtns #dt-save", function(e){
    	var data = $("#dataTbl select").serializeArray();
    	data.push({name: "cmd", value: "save"});
    	$.ajax({
    		type: "POST",
            url: "GuestDemog.php",
                    data: data,
                    dataType: "json",
                    success: function(data){
                    	flagAlertMessage(data.success, 'success');
                    },
                    error: function(data){
                    	flagAlertMessage(data.error, 'error');
                    },
                    datatype: "json"
                });
    	
    	$('#dataTbl').DataTable().ajax.reload(null, false);
    });
    
    $(document).on('click', "#fcat #btnHere", function(e){
    	e.preventDefault();
    	filterData = $(this).serializeArray();
    	filterData.push({name: "btnHere", value: "true"});
    	missingDemogTable.ajax.reload();
    });
    
    $(document).on('click', "#fcat #btnReset", function(e){
    	e.preventDefault();
    	filterData = $(this).serializeArray();
    	missingDemogTable.ajax.reload();
    });
    
    $(window).on('beforeunload', function(){
		if($(".bottom .savebtns").is(':visible')){
			return true; //prevent user from leaving
		}
	});
    
});