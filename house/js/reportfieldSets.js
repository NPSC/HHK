/**
 * reportfieldSets.js
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 */

(function ($) {

  $.fn.fieldSets = function (options) {

	    var defaults = {    
            serviceURL: 'ws_reportFilter.php',
            reportName: '',
            defaultFields: []
        };

        var settings = $.extend(true, {}, defaults, options);

        var $wrapper = $(this);

		$wrapper.find("button").button();
	
		var selectedSet = {};
	
		actions($wrapper, settings);
		
		return this;
	}
	
	
	function actions($wrapper, settings){
	
		$wrapper.on("change", "#filterSets select", function(){
			var $this = $(this);
			$wrapper.find("#filterSetBtns button, #filterSetBtns #fieldSetName, #divFieldsetError").hide();
			if($this.val() != ""){
				$wrapper.find("#filterSetTitle").text($this.find("option:selected").text());
				
				var formData = {cmd:"getFieldSet",idFieldSet:$this.val()}; 
	 
				$.ajax({
	    			url : settings.serviceURL,
	    			type: "POST",
	    			data : formData,
	    			dataType: "json",
	    			success: function(data, textStatus, jqXHR)
	    			{
	    				if(data.success){
	        				$wrapper.find("#fields select").val("").trigger("change");
	        				$wrapper.find("#fields select").val(data.fieldSet.Fields).trigger("change");
	        				$wrapper.find("#filterSetBtns input").val(data.fieldSet.Title);
	        				
	        				selectedSet = data.fieldSet;
	        				
	        				if(data.canEdit){
	        					$wrapper.find("#filterSetBtns button#delSet").show();
	        					selectedSet.canEdit = true;
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
				$wrapper.find("#filterSetTitle").text("");
				$wrapper.find("#fields select").val(settings.defaultFields);
				$wrapper.find("#fieldsetName").val("");
				selectedSet = {};
			}
		});
		
		$wrapper.on("change", "#fields select", function(e){
			if (e.originalEvent) {
				var filterSet = $wrapper.find("#filterSets select").val();
			
				if(filterSet != ""){
					if(selectedSet.canEdit){
						$wrapper.find("#filterSetBtns button, #filterSetBtns #fieldSetName").show();
					}
				}else{
					$wrapper.find("#filterSetBtns button#saveNewSet, #filterSetBtns button#saveGlobalSet, #filterSetBtns #fieldSetName").show();
				}
			}
		});
		
		$wrapper.find('#cbColClearAll').on('click', function () {
            $wrapper.find('#fields select option').each(function () {
                $(this).prop('selected', false);
            });
        });

        $wrapper.find('#cbColSelAll').on('click', function () {
            $wrapper.find('#fields select option').each(function () {
                $(this).prop('selected', true);
            });
        });
		
		$wrapper.on("click", "#filterSetBtns button", function(e){
			e.preventDefault();
			var id = $(this).attr('id');
			var formData = {};
			$wrapper.find("#divFieldsetError").hide();
			var success = function(data, textStatus, jqXHR)
	    			{
	    				if(data.error){
	    					new Noty({
								type : "error",
								text : "Error: " + data.error
							}).show();
	    				}else if(data.success){
	    					new Noty({
								type : "success",
								text : data.success
							}).show();
	    				}
	    			};
			switch(id){
				case 'saveNewSet':
					formData = {
						'cmd':'createFieldSet',
						'report':settings.reportName,
						'title': $wrapper.find('input[name=fieldsetName]').val(),
						'fields': $wrapper.find('#fields select').val(),
						'global':'false'
					};
					
					success = function(data, textStatus, jqXHR)
	    			{
	    				if(data.error){
	    					$wrapper.find("#divFieldsetError #alrMessage").html(data.error);
	    					$wrapper.find("#divFieldsetError").css('margin-top', '1em').show();
	    				}else if(data.success){
	    					new Noty({
								type : "success",
								text : data.success
							}).show();
							
							if(data.fieldSet){
								if($wrapper.find("#fieldset optgroup[label='" + data.fieldSet.optGroup + "']").length == 0){
									$wrapper.find("#fieldset").append("<optgroup label='" + data.fieldSet.optGroup + "'></optgroup>");
								}
								$wrapper.find("#fieldset optgroup[label='" + data.fieldSet.optGroup + "']").append('<option value="' + data.fieldSet.idFieldSet + '">' + data.fieldSet.title + '</option>');
								$wrapper.find("#fieldset").attr('size', function(i,v){
									return (v*1)+1;
								}).val(data.fieldSet.idFieldSet).trigger("change");
							}
	    				}
	    			};
					
					break;
					
				case 'saveGlobalSet':
					formData = {
						'cmd':'createFieldSet',
						'report': settings.reportName,
						'title': $wrapper.find('input[name=fieldsetName]').val(),
						'fields': $wrapper.find('#fields select').val(),
						'global':'true'
					};
					
					success = function(data, textStatus, jqXHR)
	    			{
	    				if(data.error){
	    					$wrapper.find("#divFieldsetError #alrMessage").html(data.error);
	    					$wrapper.find("#divFieldsetError").css('margin-top', '1em').show();
	    				}else if(data.success){
	    					new Noty({
								type : "success",
								text : data.success
							}).show();
							
							if(data.fieldSet){
								if($wrapper.find("#fieldset optgroup[label='" + data.fieldSet.optGroup + "']").length == 0){
									$wrapper.find("#fieldset").append("<optgroup label='" + data.fieldSet.optGroup + "'></optgroup>");
								}
								$wrapper.find("#fieldset optgroup[label='" + data.fieldSet.optGroup + "']").append('<option value="' + data.fieldSet.idFieldSet + '">' + data.fieldSet.title + '</option>');
								$wrapper.find("#fieldset").attr('size', function(i,v){
									return (v*1)+1;
								}).val(data.fieldSet.idFieldSet).trigger("change");
							}
	    				}
	    			};
	    			
					break;
					
				case 'saveSet':
					formData = {
						'cmd':'updateFieldSet',
						'idFieldSet': $wrapper.find('select#fieldset').val(),
						'title': $wrapper.find('input[name=fieldsetName]').val(),
						'fields': $wrapper.find('#fields select').val(),
					};
					
					success = function(data, textStatus, jqXHR)
	    			{
	    				if(data.error){
	    					new Noty({
								type : "error",
								text : "Error: " + data.error
							}).show();
	    				}else if(data.success){
	    					new Noty({
								type : "success",
								text : data.success
							}).show();
							
							$wrapper.find("#fieldset option[value=" + data.fieldSet.idFieldSet + "]").text(data.fieldSet.title);
							$wrapper.find("#fieldset").trigger("change");
							
	    				}
	    			}
					
					break;
					
				case 'delSet':
					if (confirm("Delete Field Set?")) {
						
						formData = {
							'cmd': 'deleteFieldSet',
							'idFieldSet': $wrapper.find('select#fieldset').val()
						};
						
						success = function(data, textStatus, jqXHR)
		    			{
		    				if(data.error){
		    					new Noty({
									type : "error",
									text : "Error: " + data.error
								}).show();
		    				}else if(data.success){
		    					new Noty({
									type : "success",
									text : data.success
								}).show();
								
								$wrapper.find("#fieldset option[value=" + data.idFieldSet + "]").remove();
								$wrapper.find("#fieldset").val("").trigger("change");
								
		    				}
		    			}
					}
					
					break;
				default:
					formData = {};
			}
	
			if(!$.isEmptyObject(formData)){
				$.ajax({
	    			url : settings.serviceURL,
	    			type: "POST",
	    			data : formData,
	    			dataType: "json",
	    			success: success
				});
			};
			
		});
	}
}(jQuery));