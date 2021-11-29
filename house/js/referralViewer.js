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
            labels: {},
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
                            return createActions(data, row, settings);  
                        },
                },
                {
                "targets": [ 1 ],
                        title: (options.labels.patient || 'Patient') + " First Name",
                        data: 'Patient First Name',
                        sortable: true
                },
                {
                "targets": [ 2 ],
                        title: (options.labels.patient || 'Patient') + " Last Name",
                        data: 'Patient Last Name',
                        sortable: true,
                        render: function (data, type, row){
                        	return '<a href="#" class="formDetails" data-docid="' + row.idDocument + '" data-status="' + row.idStatus + '" data-resvid="' + row.idResv + '">' + data + '</a>';
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
                        title: (options.labels.hospital || 'Hospital'),
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
                        title: (options.labels.reservation || 'Reservation') + " Status",
                        data: 'resvStatusName',
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
		
		$wrapper.find('.gmenu').menu();
		
		return this;
	}
	
	function createMarkup($wrapper, settings, dtTable){
		$wrapper.html(
		`
			<div id="referralTabs">
				<ul>
				</ul>
				<div id="referralTabContent" style="width: 100%">
					<table style="width: 100%"></table>
				</div>
			</div>
			<div id="formDetailsDialog" title="Details">
				<iframe id="formDetailsIframe" name="formPreviewIframe" width="1024" height="768" style="border: 0"></iframe>
			</div>
		`
		);
		
		//build status tabs
		$wrapper.find('#referralTabs ul').append('<li data-status="inbox"><a href="#referralTabContent">' + (settings.statuses['n'].icon ? '<span class="' + settings.statuses['n'].icon + '"></span>' : '<span class="ui-icon ui-icon-blank"></span>') + ' Inbox (<span class="referralCount">' + settings.statuses['n'].count + '</span>)</a></li>');
		$.each(settings.statuses, function(key,value){
			if(value.idStatus != 'n' && value.idStatus != 'ip'){
				$wrapper.find('#referralTabs ul').append('<li data-status="' + value.idStatus + '"><a href="#referralTabContent">' + (value.icon ? '<span class="' + value.icon + '"></span>':'<span class="ui-icon ui-icon-blank"></span>') + ' ' + value.Status + ' (<span class="referralCount">' + settings.statuses[value.idStatus].count + '</span>)</a></li>');
			}
		});
		
		
		settings.dtTable = $wrapper.find('#referralTabContent table').DataTable({
			"columnDefs": settings.dtCols,
			"serverSide": true,
			"processing": true,
			"deferRender": true,
			"language": {"sSearch": "Search Referrals:"},
			"sorting": [[6,'desc'], [8,'desc']],
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
				$wrapper.find('.gmenu').menu();
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
	
	function createActions(idDocument, row, settings){
		return `
			<ul class="gmenu" style="font-weight:normal">
				<li>Action
					<ul>
						<li class="formDetails" data-docid="` + idDocument + `" data-status="` + row.idStatus + `" data-resvid="` + row.idResv + `" title="Form Details"><div>View ` + (settings.labels.referralFormTitle || 'Referral Form') + `</div></li>` +
						(row.idResv ? `<li class="formResv"><div><a href="Reserve.php?rid=` + row.idResv + `" style="text-decoration:none;">View ` + (settings.labels.reservation || 'Reservation') + `</a></div></li>`: ``) +
						`<li></li>` +
						(row.idStatus == 'ip' ? `<li class="formUpdateStatus" data-docid="` + idDocument + `" data-target="n" title="Mark as Unread"><div>Mark as Unread</div></li>`:'') +
						(row.idStatus != 'n' && row.idStatus != 'ip' && !row.idResv ? `<li class="formUpdateStatus" data-docid="` + idDocument + `" data-target="ip" title="Move to Inbox"><div>Move to Inbox</div></li>`:'') +
						(row.idStatus != 'ar' && !row.idResv ? `<li class="formUpdateStatus" data-docid="` + idDocument + `" data-target="ar" title="Archive Form"><div>Archive</div></li>`:'') +
						(!row.idResv ? `<li class="formUpdateStatus" data-docid="` + idDocument + `" data-target="d" title="Delete Form"><div>Delete</div></li>`:'') +
					`</ul>
				</li>
			</ul>
		`;
	}
	
	function actions($wrapper, settings, formDetailsDialog){
		
		$wrapper.on('click', '.formDetails', function(e){
			var idDocument = $(e.currentTarget).data('docid');
			var idStatus = $(e.currentTarget).data('status');
			var idResv = $(e.currentTarget).data('resvid');
			
			formDetailsDialog.find("#formDetailsIframe").attr('src', settings.detailURL + '?form=' + idDocument);
			
			if(!idResv){
				settings.formDetailsDialogBtns["Create Reservation"] = function(){
					window.location.href = settings.reserveURL + "?docid=" + idDocument;
				};
			}else{
				delete settings.formDetailsDialogBtns["Create Reservation"];
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
						reloadTotals($wrapper, settings);
					}
				});
			}
			formDetailsDialog.dialog('open');
		
		}); 
		
		$wrapper.on('click', '.formUpdateStatus', function(e){
			var idDocument = $(e.currentTarget).data('docid');
			var idStatus = $(e.currentTarget).data('target');
			if(idDocument && idStatus){
				$.ajax({
					url: settings.serviceURL,
					dataType: 'JSON',
					type: 'get',
					data: {
						cmd: 'updateFormStatus',
						idDocument: idDocument,
						status: idStatus
					},
					success: function( data ){
						settings.dtTable.ajax.reload();
						reloadTotals($wrapper, settings);
					}
				});
			}
		});		
	}
	
	function reloadTotals($wrapper, settings){
		
		$.ajax({
			url: settings.serviceURL,
			dataType: 'JSON',
			type: 'get',
			data: {
				cmd: 'listforms',
				totalsonly: true
			},
			success: function( data ){
				if(data.totals){
					$wrapper.find('#referralTabs ul li[data-status=inbox] span.referralCount').text(data.totals['n'].count);
					$('#spnNumReferral').text(data.totals['n'].count);
					$.each(data.totals, function(key,value){
						$wrapper.find('#referralTabs ul li[data-status=' + key + '] span.referralCount').text(value.count);
					});
					settings.statuses = data.totals;
				}
			}
		});
		
	}
}(jQuery));