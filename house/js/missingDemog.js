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
    		title = demos[column.dt].title;
    		render = function ( data, type ) {
    			var select = $("<select>");
    			var option = $("<option>");
    			select.append(option);
    			$.each(demos[column.dt].list, function(key, item){
    				var option = $("<option>").attr("value", item[0]).text(item[1]);
    				if(data == item[0]){
    					option.attr("selected","selected");
    				}
    				select.append(option);
    			})        			
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
		render: function ( data, type ){
			return $("<input>").attr({"type":"checkbox"})[0].outerHTML;
		}
	});
    
    var missingDemogTable = $('#dataTbl').dataTable({
        "columnDefs": dtCols,
        "serverSide": true,
        "processing": true,
        "deferRender": true,
        "language": {"search": "Search missing demographics:"},
        "sorting": [[0,'desc']],
        "displayLength": 25,
        "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
        "Dom": '<"top"ilf>rt<"bottom"ip>',
        ajax: {
            url: "GuestDemog.php?cmd=getMissingDemog"
        }
        });
});