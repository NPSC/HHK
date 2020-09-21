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
	
	$("#filterSets").on("change", "select", function(){
		var $this = $(this);
		console.log($this.val());
		$("#filterSetBtns button").hide();
		if($this.val() != ""){
			$("#delSet").show();
			$("#filterSetTitle").text($this.find("option:selected").text());
		}else{
			$("#filterSetTitle").text("");
		}
	});
	
	$("#fields").on("change", "select", function(){
		var filterSet = $("#filterSets select").val();
		
		if(filterSet != ""){
			$("#filterSetBtns button").show();
		}
	});
	
	$("#filterSetBtns").on("click", "button", function(e){
		e.preventDefault();
		console.log($(this).attr('id'));
	});
    
});