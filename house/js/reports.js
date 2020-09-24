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
	
	var defaultFields = $("#fields").val();
	console.log(defaultFields);
	
	$("#filterSets").on("change", "select", function(){
		var $this = $(this);
		console.log($this.val());
		$("#filterSetBtns button, #filterSetBtns #fieldSetName").hide();
		if($this.val() != ""){
			$("#delSet").show();
			$("#filterSetTitle").text($this.find("option:selected").text());
			
			var formData = {cmd:"getFieldSet",idFieldSet:$this.val()}; 
 
			$.ajax({
    			url : "/house/ws_report.php",
    			type: "POST",
    			data : formData,
    			dataType: "json",
    			success: function(data, textStatus, jqXHR)
    			{
    				console.log(data);
        			$("#fields select").val("").trigger("change");
        			$("#fields select").val(data.fieldSet.Fields).trigger("change");
        			$("#filterSetBtns input").val(data.fieldSet.Title);
    			},
    			error: function (jqXHR, textStatus, errorThrown)
    			{
 					new Noty({
						type : "error",
						text : errorthrown
					}).show();
    			}
			});
			
		}else{
			$("#filterSetTitle").text("");
			$("#fields select").val(defaultFields);
			
		}
	});
	
	$("#fields").on("change", "select", function(e){
		if (e.originalEvent) {
			var filterSet = $("#filterSets select").val();
		
			if(filterSet != ""){
				$("#filterSetBtns button, #filterSetBtns #fieldSetName").show();
			}
		}
	});
	
	$("#filterSetBtns").on("click", "button", function(e){
		e.preventDefault();
		var id = $(this).attr('id');
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
		}
		
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
		
	});
    
});