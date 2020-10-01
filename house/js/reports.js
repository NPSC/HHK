/**
 * reports.js
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 */
$(document).ready(function() {
    "use strict";

	$("#vcategory button").button();
	
	var defaultFields = $("#fields select").val();
	
	$("#filterSets").on("change", "select", function(){
		var $this = $(this);
		$("#filterSetBtns button, #filterSetBtns #fieldSetName").hide();
		if($this.val() != ""){
			$("#filterSetTitle").text($this.find("option:selected").text());
			
			var formData = {cmd:"getFieldSet",idFieldSet:$this.val()}; 
 
			$.ajax({
    			url : "/house/ws_report.php",
    			type: "POST",
    			data : formData,
    			dataType: "json",
    			success: function(data, textStatus, jqXHR)
    			{
    				if(data.success){
        				$("#fields select").val("").trigger("change");
        				$("#fields select").val(data.success.fieldSet.Fields).trigger("change");
        				$("#filterSetBtns input").val(data.success.fieldSet.Title);
        				
        				if(data.success.canEdit){
        					$("#filterSetBtns button#delSet").show();
        				}
        			}else if(data.error){
        				new Noty({
							type : "error",
							text : data.error
						}).show();
        			}
    			},
			});
			
		}else{
			$("#filterSetTitle").text("");
			$("#fields select").val(defaultFields);
			$("#fieldsetName").val("");
		}
	});
	
	$("#fields").on("change", "select", function(e){
		if (e.originalEvent) {
			var filterSet = $("#filterSets select").val();
		
			if(filterSet != ""){
				$("#filterSetBtns button, #filterSetBtns #fieldSetName").show();
			}else{
				$("#filterSetBtns button#saveNewSet, #filterSetBtns button#saveGlobalSet, #filterSetBtns #fieldSetName").show();
			}
		}
	});
	
	$("#filterSetBtns").on("click", "button", function(e){
		e.preventDefault();
		var id = $(this).attr('id');
		console.log(id);
		var formData = {};
		switch(id){
			case 'saveNewSet':
				formData = {
					'cmd':'createFieldSet',
					'report':'visit',
					'title': $('input[name=fieldsetName]').val(),
					'fields': $('select#selFld').val(),
					'global':'false'
				};
				
				break;
				
			case 'saveGlobalSet':
				formData = {
					'cmd':'createFieldSet',
					'report':'visit',
					'title': $('input[name=fieldsetName]').val(),
					'fields': $('select#selFld').val(),
					'global':'true'
				};
				
				break;
				
			case 'saveSet':
				formData = {
					'cmd':'updateFieldSet',
					'idFieldSet': $('select#fieldset').val(),
					'title': $('input[name=fieldsetName]').val(),
					'fields': $('select#selFld').val(),
				};
				
				break;
				
			case 'delSet':
				formData = {
					'cmd': 'deleteFieldSet',
					'idFieldSet': $('select#fieldset').val()
				};
				
				break;
			default:
				formData = {};
		}

		if(!$.isEmptyObject(formData)){
			$.ajax({
    			url : "/house/ws_report.php",
    			type: "POST",
    			data : formData,
    			dataType: "json",
    			success: function(data, textStatus, jqXHR)
    			{
    				console.log(data);
    				if(data.error){
    					new Noty({
							type : "error",
							text : "Error: " + data.error
						}).show();
    				}else{
    					new Noty({
							type : "success",
							text : "Success"
						}).show();
    				}
    			},
    			error: function (jqXHR, textStatus, errorThrown)
    			{
 					
    			}
			});
		};
		
	});
    
});