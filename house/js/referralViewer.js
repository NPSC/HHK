/**
 * referralViewer.js
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2021 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 */
(function ($) {

  $.fn.referralViewer = function (options) {

	    var defaults = {    
            serviceURL: 'ws_resc.php',
            detailURL: 'showReferral.php',
            statuses: [],
            dtTable: "",
            dtData: {'cmd': 'listforms', 'status':'inbox'},
            dtCols: [
                {
                "targets": [ 0 ],
                        title: "Actions",
                        data: "Actions",
                        sortable: false,
                        searchable: false,
                        className:'actionBtns',
                        render: function (data, type, row) {
                            return createActions(data, row);  
                        }
                },
                {
                "targets": [ 1 ],
                        title: "Document Id",
                        data: "idDocument",
                        sortable: false,
                        searchable: false,
                        className:'actionBtns',
                },
                {
                "targets": [ 2 ],
                        title: "Patient First Name",
                        data: 'Patient First Name',
                        sortable: true
                },
                {
                "targets": [ 3 ],
                        title: "Patient Last Name",
                        data: 'Patient Last Name',
                        sortable: true
                },
                {
                "targets": [ 4 ],
                        title: "Expected Checkin",
                        data: 'Expected Checkin',
                        sortable: true,
                        render: function (data, type) {
                            return dateRender(data, type, dateFormat);
                        }
                },
                {
                "targets": [ 5 ],
                        title: "Expected Checkout",
                        data: 'Expected Checkout',
                        sortable: true,
                        render: function (data, type) {
                            return dateRender(data, type, dateFormat);
                        }
                },
                {
                "targets": [ 6 ],
                        title: "Hopsital",
                        data: 'Hospital',
                        sortable: true
                },
                {
                "targets": [ 7 ],
                        title: "Status",
                        data: 'Status',
                        sortable: true
                },
                {
                "targets": [ 8 ],
                        title: "Submit Date",
                        data: 'Date',
                        sortable: true,
                        render: function (data, type) {
                            return dateRender(data, type, dateFormat);
                        }
                },
            ]
            
			
        };

        var settings = $.extend(true, {}, defaults, options);

        var $wrapper = $(this);
        
        createMarkup($wrapper, settings);

		$wrapper.find("button").button();
    	
    	var formDetailsDialog = $wrapper.find('#formDetailsDialog').dialog({
      		autoOpen: false,
      		height: "auto",
      		width: "auto",
      		modal: true,
      		buttons: {
        		Close: function(){
        			formDetailsDialog.dialog( "close" );
        		}
      		}
    	});
	
		actions($wrapper, settings, formDetailsDialog);
		
		return this;
	}
	
	function createMarkup($wrapper, settings, dtTable){
		$wrapper.html(
		`
			<div id="referralTabs">
				<ul>
				</ul>
				<div id="referralTabContent">
					<table style="width: 100%"></table>
				</div>
			</div>
			<div id="formDetailsDialog" title="Details">
				<iframe id="formDetailsIframe" name="formPreviewIframe" width="1024" height="768" style="border: 0"></iframe>
			</div>
		`
		);
		$wrapper.find('#referralTabs ul').append('<li data-status="inbox"><a href="#referralTabContent">Inbox</a></li>');
		$.each(settings.statuses, function(key,value){
			if(value.Code != 'n' && value.Code != 'ip'){
				$wrapper.find('#referralTabs ul').append('<li data-status="' + value.Code + '"><a href="#referralTabContent">' + value.Description + '</a></li>');
			}
		});
		
		
		settings.dtTable = $wrapper.find('#referralTabContent table').DataTable({
			"columnDefs": settings.dtCols,
			"serverSide": true,
			"processing": true,
			"deferRender": true,
			"language": {"sSearch": "Search Referrals:"},
			"sorting": [[7,'desc'], [8,'desc']],
			"displayLength": 10,
			"lengthMenu": [[5, 10, 25, -1], [5, 10, 25, "All"]],
			"dom": '<"dtTop"if>rt<"dtBottom"lp><"clear">',
			ajax: {
			    url: settings.serviceURL,
			    data: function(d){
			    	return $.extend(d, settings.dtData);
			    },
			},
			"drawCallback": function(settings){
				$wrapper.find('.actionBtns button').button();
			},
			"createdRow": function( row, data, dataIndex){
				if( data["idStatus"] ==  "n"){
	                $(row).css("font-weight", "bold");
	            }
			}
		});
		
		$wrapper.find('#referralTabs').tabs({
			active: 0,
			beforeActivate: function( event, ui ) {
				if(ui.newTab){
					
					settings.dtData.status = ui.newTab.data("status");
			        settings.dtTable.ajax.reload();
				}
			}
		}).addClass( "ui-tabs-vertical ui-helper-clearfix" );
		$wrapper.find('#referralTabs li').removeClass( "ui-corner-top" ).addClass( "ui-corner-left" );
		//$wrapper.find("#referralTabs").tabs( "option", "active", "0" );
		
	}
	
	function createActions(idDocument, row){
		return '<button type="button" class="formDetails" data-id="' + idDocument + '">Open</button>';
	}
	
	function actions($wrapper, settings, formDetailsDialog){
		
		$wrapper.on('click', '.formDetails', function(e){
			var idDocument = $(e.target).data('id');
			formDetailsDialog.find("#formDetailsIframe").attr('src', settings.detailURL + '?form=' + idDocument);
			formDetailsDialog.dialog('open');
		
		}); 
		
	}
	
	function getTotals(settings){
		$.ajax({
            url: settings.serviceURL,
            dataType: 'JSON',
            type: 'get',
            data: {
                cmd: 'listforms',
                totalsonly: 'true'
            },
            success: function( data ){
                if(data.totals){
                	$.each(settings.statuses, function(key,status){
                    	$.each(data.totals, function(totalkey,total){
                    		if(total.status = status.Code){
                    			status.Total = total.count;
                    		}else{
                    			status.Total = 0;
                    		}
                    	});
                    });
                }else{
                    return [];
                }
            }
        });
	}
}(jQuery));