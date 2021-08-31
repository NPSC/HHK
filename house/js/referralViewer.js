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
            reserveURL: 'GuestReferral.php',
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
                //{
                //"targets": [ 1 ],
                //        title: "Document Id",
                //        data: "idDocument",
                //        sortable: false,
                //        searchable: false,
                //        className:'actionBtns',
                //},
                {
                "targets": [ 1 ],
                        title: "Patient First Name",
                        data: 'Patient First Name',
                        sortable: true
                },
                {
                "targets": [ 2 ],
                        title: "Patient Last Name",
                        data: 'Patient Last Name',
                        sortable: true,
                        render: function (data, type, row){
                        	return '<a href="#" class="formDetails" data-docid="' + row.idDocument + '">' + data + '</a>';
                        }
                },
                {
                "targets": [ 3 ],
                        title: "Expected Checkin",
                        data: 'Expected Checkin',
                        sortable: true,
                        render: function (data, type) {
                            return dateRender(data, type, dateFormat);
                        }
                },
                {
                "targets": [ 4 ],
                        title: "Expected Checkout",
                        data: 'Expected Checkout',
                        sortable: true,
                        render: function (data, type) {
                            return dateRender(data, type, dateFormat);
                        }
                },
                {
                "targets": [ 5 ],
                        title: "Hospital",
                        data: 'Hospital',
                        sortable: true
                },
                {
                "targets": [ 6 ],
                        title: "Status",
                        data: 'Status',
                        sortable: true
                },
                {
                "targets": [ 7 ],
                        title: "Submit Date",
                        data: 'Date',
                        sortable: true,
                        render: function (data, type) {
                            return dateRender(data, type, dateFormat);
                        }
                },
            ],
            formDetailsDialogBtns: 
            {
      			"Create Reservation": function(){
      			
      			},
        		Close: function(){
        			formDetailsDialog.dialog( "close" );
        		}
        	}
			
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
      		buttons: settings.formDetailsDialogBtns
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
		
		//build status tabs
		$wrapper.find('#referralTabs ul').append('<li data-status="inbox"><a href="#referralTabContent">' + (settings.statuses['n'].icon ? '<span class="' + settings.statuses['n'].icon + '"></span>' : '<span class="ui-icon ui-icon-blank"></span>') + ' Inbox (' + settings.statuses['n'].count + ')</a></li>');
		$.each(settings.statuses, function(key,value){
			if(value.idStatus != 'n' && value.idStatus != 'ip'){
				$wrapper.find('#referralTabs ul').append('<li data-status="' + value.idStatus + '"><a href="#referralTabContent">' + (value.icon ? '<span class="' + value.icon + '"></span>':'<span class="ui-icon ui-icon-blank"></span>') + ' ' + value.Status + ' (' + settings.statuses[value.idStatus].count + ')</a></li>');
			}
		});
		
		
		settings.dtTable = $wrapper.find('#referralTabContent table').DataTable({
			"columnDefs": settings.dtCols,
			"serverSide": true,
			"processing": true,
			"deferRender": true,
			"language": {"sSearch": "Search Referrals:"},
			"sorting": [[6,'desc'], [7,'desc']],
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
				$wrapper.find('.hhk-ui-icons li').button();
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
		
	}
	
	function createActions(idDocument, row){
		return `
			<ul class="hhk-ui-icons">
				<li class="formDetails" data-docid="` + idDocument + `" data-status="` + row.idStatus + `" title="Form Details"><span class="ui-icon ui-icon-extlink"></span></li>
				<li class="formArchive" data-docid="` + idDocument + `" title="Archive Form"><span class="ui-icon ui-icon-folder-open"></span></li>
				<li class="formDelete" data-docid="` + idDocument + `" title="Delete Form"><span class="ui-icon ui-icon-trash"></span></li>
			</ul>
		`;
		//return '<button type="button" class="formDetails" data-id="' + idDocument + '" style="margin-right: 0.5em">Open</button><button type="button" class="formDelete" data-id="' + idDocument + '"><span class="ui-icon ui-icon-trash"></span></button>';
	}
	
	function actions($wrapper, settings, formDetailsDialog){
		
		$wrapper.on('click', '.formDetails', function(e){
			var idDocument = $(e.currentTarget).data('docid');
			var idStatus = $(e.currentTarget).data('status');
			formDetailsDialog.find("#formDetailsIframe").attr('src', settings.detailURL + '?form=' + idDocument);
			
			settings.formDetailsDialogBtns["Create Reservation"] = function(){
				window.location.href = settings.reserveURL + "?docid=" + idDocument;
			};
			
			formDetailsDialog.dialog('option', 'buttons', settings.formDetailsDialogBtns);
			
			if(idStatus == 'n'){
				$.ajax({
					url: settings.serviceURL,
					dataType: 'JSON',
					type: 'get',
					data: {
						cmd: 'updateFormStatus',
						idDocument: idDocument,
						status: 'ip'
					},
					success: function( data ){
						settings.dtTable.ajax.reload();
					}
				});
			}
			formDetailsDialog.dialog('open');
		
		}); 
		
		$wrapper.on('click', '.formDelete', function(e){
			var idDocument = $(e.currentTarget).data('docid');
			if(idDocument){
				$.ajax({
					url: settings.serviceURL,
					dataType: 'JSON',
					type: 'get',
					data: {
						cmd: 'updateFormStatus',
						idDocument: idDocument,
						status: 'd'
					},
					success: function( data ){
						settings.dtTable.ajax.reload();
					}
				});
			}
		}); 
		$wrapper.on('click', '.formArchive', function(e){
			var idDocument = $(e.currentTarget).data('docid');
			if(idDocument){
				$.ajax({
					url: settings.serviceURL,
					dataType: 'JSON',
					type: 'get',
					data: {
						cmd: 'updateFormStatus',
						idDocument: idDocument,
						status: 'ar'
					},
					success: function( data ){
						settings.dtTable.ajax.reload();
					}
				});
			}
		}); 
		
	}
}(jQuery));