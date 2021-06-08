/**
 * formBuilder.js
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

  $.fn.hhkFormBuilder = function (options) {

	    var defaults = {    
            serviceURL: 'ws_resc.php',
            previewURL: 'showReferral.php',
            formBuilder: null,
            fields: [
    		{
      			label: "Source",
      			type: "text",
      			subtype: "text"
    		},
    		{
    			label: "Submit",
    			type: "button",
    			subtype: "submit",
    			className: "submit-btn"
    			
    		}
  			],
            inputSets: [
      		{
        		label: 'Patient Details',
        		name: 'patient-details', // optional - one will be generated from the label if name not supplied
        		showHeader: true, // optional - Use the label as the header for this set of inputs
        		fields: [
				{
					"type": "text",
    				"label": "Patient First Name",
    				"placeholder": "First Name",
    				"className": "form-control",
    				"name": "patientFirstName",
    				"width": "col-md-4"
  				},
  				{
  					"type": "text",
    				"label": "Patient Last Name",
    				"placeholder": "Last Name",
    				"className": "form-control",
    				"name": "patientLastName",
    				"width": "col-md-4"
  				},
  				{
    				"type": "date",
    				"label": "Patient Birthdate",
    				"placeholder": "Patient Birthdate",
    				"className": "form-control",
    				"name": "patientBirthdate",
    				"width": "col-md-2"
  				},
  				{
    				"type": "select",
    				"label": "Patient Sex",
    				"placeholder": "Patient Sex",
    				"className": "form-select",
    				"name": "patientSex",
    				"width": "col-md-2",
    				"multiple": false,
    				"values": []
  				},
  				{
					"type": "header",
					"subtype": "h3",
    				"label": "Address",
    				"placeholder": "Address",
    				"className": "col-md-12"
    				
  				},
  				{
					"type": "text",
    				"label": "Street",
    				"placeholder": "Street",
    				"className": "form-control",
    				"name": "adrStreet",
    				"width": "col-md-12"
  				},
  				{
  					"type": "text",
    				"label": "City",
    				"placeholder": "City",
    				"className": "form-control",
    				"name": "adrCity",
    				"width": "col-md-5"
  				},
  				{
    				"type": "text",
    				"label": "State",
    				"placeholder": "State",
    				"className": "form-control",
    				"name": "adrState",
    				"width": "col-md-2"
  				},
  				{
    				"type": "text",
    				"label": "Zip Code",
    				"placeholder": "Zip Code",
    				"className": "form-control",
    				"name": "adrZip",
    				"width": "col-md-2"
    			},
    			{
    				"type": "text",
    				"label": "Country",
    				"placeholder": "Country",
    				"className": "form-control",
    				"name": "adrCountry",
    				"width": "col-md-3"
    			},
    			{
    				"type": "text",
    				"subtype": "tel",
    				"label": "Phone",
    				"placeholder": "Phone",
    				"className": "form-control",
    				"name": "phone",
    				"width": "col-md-6"
    			},
    			{
    				"type": "text",
    				"subtype": "email",
    				"label": "Email",
    				"placeholder": "Email",
    				"className": "form-control",
    				"name": "email",
    				"width": "col-md-6"
    			}
  				]
  			},
  			{
        		label: 'Address',
        		name: 'pat-address',
        		showHeader: true,
        		fields: [
				{
					"type": "text",
    				"label": "Street",
    				"placeholder": "Street",
    				"className": "form-control",
    				"name": "adrStreet",
    				"width": "col-md-12"
  				},
  				{
  					"type": "text",
    				"label": "City",
    				"placeholder": "City",
    				"className": "form-control",
    				"name": "adrCity",
    				"width": "col-md-5"
  				},
  				{
    				"type": "text",
    				"label": "State",
    				"placeholder": "State",
    				"className": "form-control",
    				"name": "adrState",
    				"width": "col-md-2"
  				},
  				{
    				"type": "text",
    				"label": "Zip Code",
    				"placeholder": "Zip Code",
    				"className": "form-control",
    				"name": "adrZip",
    				"width": "col-md-2"
    			},
    			{
    				"type": "text",
    				"label": "Country",
    				"placeholder": "Country",
    				"className": "form-control",
    				"name": "adrCountry",
    				"width": "col-md-3"
    			},
    			{
    				"type": "text",
    				"subtype": "tel",
    				"label": "Phone",
    				"placeholder": "Phone",
    				"className": "form-control",
    				"name": "phone",
    				"width": "col-md-6"
    			},
    			{
    				"type": "text",
    				"subtype": "email",
    				"label": "Email",
    				"placeholder": "Email",
    				"className": "form-control",
    				"name": "email",
    				"width": "col-md-6"
    			}
    			]
  			},
  			{
        		label: 'Stay Dates',
        		name: 'stay-dates',
        		showHeader: true,
        		fields: [
				{
					"type": "date",
    				"label": "Checkin Date",
    				"className": "form-control",
    				"name": "checkindate",
    				"width": "col-md-6"
  				},
  				{
					"type": "date",
    				"label": "Checkout Date",
    				"className": "form-control",
    				"name": "checkoutdate",
    				"width": "col-md-6"
  				}
  				]
  			},
  			{
        		label: 'Family Members/Caregivers',
        		name: 'family-members',
        		showHeader: true,
        		fields: [
				{
					"type": "text",
    				"label": "First Name",
    				"placeholder": "First Name",
    				"className": "form-control",
    				"name": "guests[0][firstName]",
    				"width": "col-md-3"
  				},
  				{
  					"type": "text",
    				"label": "Last Name",
    				"placeholder": "Last Name",
    				"className": "form-control",
    				"name": "guests[0][lastName]",
    				"width": "col-md-3"
  				},
  				{
    				"type": "text",
    				"subtype": "tel",
    				"label": "Phone",
    				"placeholder": "Phone",
    				"className": "form-control",
    				"name": "guests[0][phone]",
    				"width": "col-md-3"
    			},
    			{
  					"type": "select",
    				"label": "Relationship to Patient",
    				"placeholder": "Relationship to Patient",
    				"className": "form-select",
    				"name": "guests[0][relationship]",
    				"width": "col-md-3",
    				"multiple": false,
    				"values": [
      				{
        				"label": "Patient Relationship",
        				"value": "",
        				"selected": true
      				}
    				]
  				},
  				{
					"type": "text",
    				"label": "First Name",
    				"placeholder": "First Name",
    				"className": "form-control",
    				"name": "guests[1][firstName]",
    				"width": "col-md-3"
  				},
  				{
  					"type": "text",
    				"label": "Last Name",
    				"placeholder": "Last Name",
    				"className": "form-control",
    				"name": "guests[1][lastName]",
    				"width": "col-md-3"
  				},
  				{
    				"type": "text",
    				"subtype": "tel",
    				"label": "Phone",
    				"placeholder": "Phone",
    				"className": "form-control",
    				"name": "guests[1][phone]",
    				"width": "col-md-3"
    			},
    			{
  					"type": "select",
    				"label": "Relationship to Patient",
    				"placeholder": "Relationship to Patient",
    				"className": "form-select",
    				"name": "guests[1][relationship]",
    				"width": "col-md-3",
    				"multiple": false,
    				"values": [
      				{
        				"label": "Patient Relationship",
        				"value": "",
        				"selected": true
      				}
    				]
  				},
  				{
					"type": "text",
    				"label": "First Name",
    				"placeholder": "First Name",
    				"className": "form-control",
    				"name": "guests[2][firstName]",
    				"width": "col-md-3"
  				},
  				{
  					"type": "text",
    				"label": "Last Name",
    				"placeholder": "Last Name",
    				"className": "form-control",
    				"name": "guests[2][lastName]",
    				"width": "col-md-3"
  				},
  				{
    				"type": "text",
    				"subtype": "tel",
    				"label": "Phone",
    				"placeholder": "Phone",
    				"className": "form-control",
    				"name": "guests[2][phone]",
    				"width": "col-md-3"
    			},
    			{
  					"type": "select",
    				"label": "Relationship to Patient",
    				"placeholder": "Relationship to Patient",
    				"className": "form-select",
    				"name": "guests[2][relationship]",
    				"width": "col-md-3",
    				"multiple": false,
    				"values": [
      				{
        				"label": "Patient Relationship",
        				"value": "",
        				"selected": true
      				}
    				]
  				},
  				]
  			},
  			{
        		label: 'Hospital Info',
        		name: 'hospital-info',
        		showHeader: true,
        		fields: [
				{
					"type": "text",
    				"label": "Hospital",
    				"placeholder": "Hospital Name",
    				"className": "form-control",
    				"name": "hospital[name]",
    				"width": "col-md-3",
  				},
  				{
					"type": "text",
    				"label": "Doctor",
    				"placeholder": "Doctor",
    				"className": "form-control",
    				"name": "hospital[doctor]",
    				"width": "col-md-3",
  				},
  				{
					"type": "date",
    				"label": "Treatment Start Date",
    				"placeholder": "Treatment Start",
    				"className": "form-control",
    				"name": "hospital[treatmentStart]",
    				"width": "col-md-3",
  				},
  				{
					"type": "date",
    				"label": "Treatment End Date",
    				"placeholder": "Treatment End",
    				"className": "form-control",
    				"name": "hospital[treatmentEnd]",
    				"width": "col-md-3",
  				}
  				]
  			}
  			],
  			disableFields: [
      			'autocomplete',
      			'button',
      			'checkbox-group',
      			'date',
      			'file',
      			'hidden',
      			'number'
    		],
    		actionButtons: [
    		{
    			id: 'saveAction',
    			className: 'btn btn-default',
    			label: 'Save and Publish',
    			type: 'button',
    			events: {
    				click: function() {
    					settings.formBuilder.actions.save();
    				}
  				}
  			},
    		{
    			id: 'editStyleAction',
    			className: 'btn btn-default',
    			label: 'Edit Styles',
    			type: 'button',
    			events: {
    				click: function() {
      					editStyleDialog.dialog('open');
    				}
  				}
  			},
  			{
    			id: 'editSuccessAction',
    			className: 'btn btn-default',
    			label: 'Edit Success Message',
    			type: 'button',
    			events: {
    				click: function() {
      					editSuccessDialog.dialog('open');
    				}
  				}
  			},
  			{
    			id: 'previewAction',
    			className: 'btn btn-default',
    			label: 'Preview',
    			type: 'button',
    			events: {
    				click: function() {
    					var formData = settings.formBuilder.actions.getData();
    					console.log(JSON.stringify(formData));
    					
    					var f = $("<form target='formPreviewIframe' method='POST' style='display:none;'></form>").attr({
        					action: settings.previewURL
    					}).appendTo(document.body);
    					
    					f.append('<input type="hidden" name="cmd" value="preview">');
    					f.append('<input type="hidden" name="formData" value="' + encodeURI(JSON.stringify(formData)) + '">');
    					f.append('<input type="hidden" name="style" value="' + editStyleDialog.find("textarea").val() + '">');
    					console.log(f);
    					f.submit();
    					f.remove();
	    				
      					formPreviewDialog.dialog('open');
    				}
  				}
  			}
  			],
  			disabledAttrs: ['access', 'name'],
  			typeUserAttrs: {
  				text: {
    				width: {
      					label: 'Field Width',
      					multiple: false,
      					options: {
        					'col-md-12': '12 Columns',
        					'col-md-11': '11 Columns',
        					'col-md-10': '10 Columns',
        					'col-md-9': '9 Columns',
        					'col-md-8': '8 Columns',
        					'col-md-7': '7 Columns',
        					'col-md-6': '6 Columns',
        					'col-md-5': '5 Columns',
        					'col-md-4': '4 Columns',
        					'col-md-3': '3 Columns',
        					'col-md-2': '2 Columns',
        					'col-md-1': '1 Column',
      					},
      					value: 'col-md-12'
    				}
    			},
  				
  				select: {
    				width: {
      					label: 'Field Width',
      					multiple: false,
      					options: {
        					'col-md-12': '12 Columns',
        					'col-md-11': '11 Columns',
        					'col-md-10': '10 Columns',
        					'col-md-9': '9 Columns',
        					'col-md-8': '8 Columns',
        					'col-md-7': '7 Columns',
        					'col-md-6': '6 Columns',
        					'col-md-5': '5 Columns',
        					'col-md-4': '4 Columns',
        					'col-md-3': '3 Columns',
        					'col-md-2': '2 Columns',
        					'col-md-1': '1 Column',
      					},
      					value: 'col-md-12'
    				}
  				},
  				date: {
    				width: {
      					label: 'Field Width',
      					multiple: false,
      					options: {
        					'col-md-12': '12 Columns',
        					'col-md-11': '11 Columns',
        					'col-md-10': '10 Columns',
        					'col-md-9': '9 Columns',
        					'col-md-8': '8 Columns',
        					'col-md-7': '7 Columns',
        					'col-md-6': '6 Columns',
        					'col-md-5': '5 Columns',
        					'col-md-4': '4 Columns',
        					'col-md-3': '3 Columns',
        					'col-md-2': '2 Columns',
        					'col-md-1': '1 Column',
      					},
      					value: 'col-md-12'
    				}
  				},
  				paragraph: {
    				width: {
      					label: 'Field Width',
      					multiple: false,
      					options: {
        					'col-md-12': '12 Columns',
        					'col-md-11': '11 Columns',
        					'col-md-10': '10 Columns',
        					'col-md-9': '9 Columns',
        					'col-md-8': '8 Columns',
        					'col-md-7': '7 Columns',
        					'col-md-6': '6 Columns',
        					'col-md-5': '5 Columns',
        					'col-md-4': '4 Columns',
        					'col-md-3': '3 Columns',
        					'col-md-2': '2 Columns',
        					'col-md-1': '1 Column',
      					},
      					value: 'col-md-12'
    				}
  				},
  				textarea: {
    				width: {
      					label: 'Field Width',
      					multiple: false,
      					options: {
        					'col-md-12': '12 Columns',
        					'col-md-11': '11 Columns',
        					'col-md-10': '10 Columns',
        					'col-md-9': '9 Columns',
        					'col-md-8': '8 Columns',
        					'col-md-7': '7 Columns',
        					'col-md-6': '6 Columns',
        					'col-md-5': '5 Columns',
        					'col-md-4': '4 Columns',
        					'col-md-3': '3 Columns',
        					'col-md-2': '2 Columns',
        					'col-md-1': '1 Column',
      					},
      					value: 'col-md-12'
    				}
  				}
			},
			disabledActionButtons: ['data', 'save', 'clear'],
			layoutTemplates: {
  				default: function(field, label, help, data) {
    				return $('<div/>').addClass(data.width).append(field);
  				}
			}
        };

        var settings = $.extend(true, {}, defaults, options);

        var $wrapper = $(this);
        
        createMarkup($wrapper, settings);

		$wrapper.find("button").button();
		
		var editStyleDialog = $wrapper.find('#formStyleDialog').dialog({
      		autoOpen: false,
      		height: 800,
      		width: 800,
      		modal: true,
      		buttons: {
        		"Revert Changes": function() {
        			editStyleDialog.find('textarea').val(editStyleDialog.find('textarea').data('oldstyles'));
          			editStyleDialog.dialog( "close" );
        		},
        		Continue: function(){
        			editStyleDialog.dialog( "close" );
        		}
      		}
    	});
    	
    	var editSuccessDialog = $wrapper.find('#formSuccessDialog').dialog({
      		autoOpen: false,
      		height: 400,
      		width: 600,
      		modal: true,
      		buttons: {
        		"Revert Changes": function() {
        			editSuccessDialog.find('textarea').val(editSuccessDialog.find('textarea').data('oldval'));
          			editSuccessDialog.dialog( "close" );
        		},
        		Continue: function(){
        			editSuccessDialog.dialog( "close" );
        		}
      		}
    	});
    	
    	var formPreviewDialog = $wrapper.find('#formPreviewDialog').dialog({
      		autoOpen: false,
      		height: "auto",
      		width: "auto",
      		modal: true,
      		buttons: {
        		Close: function(){
        			formPreviewDialog.dialog( "close" );
        		}
      		}
    	});
	
		actions($wrapper, settings, editStyleDialog, formPreviewDialog, editSuccessDialog);
		
		return this;
	}
	
	function createMarkup($wrapper, settings){
		$wrapper.html(
		`
			<div>
				<label for="selectform">Select a form: </label>
				<select id="selectform" name="selectform" style="margin: 0 0.5em; padding:0.4em 0.5em;">
					<option value=""></option>
				</select>
				<button id="newReferral">New Referral Form</button>
				<span class="formTitleContainer">
					<label for="formTitle">Form Title: </label>
					<input typle="text" id="formTitle" placeholder="Form Title" style="padding:0.4em 0.5em;">
				</span>
				<span id="formiframe" style="margin-left: 0.5em;"></span>
			</div>
			<div id="formBuilderContent" style="margin-top: 1em;"></div>
			<div id="formStyleDialog" title="Edit Form Styles">
				<div class="row">
					<div class="col-9">
						<h3>Edit Form Style</h3>
						<textarea id="formStyle" name="formStyle" style="width: 100%; height: 600px;" data-oldstyles=""></textarea>
					</div>
					<div class="col-3">
						<h3>Available Styles</h3>
						<ul style="list-style:none;">
							<li>h1</li>
							<li>h2</li>
							<li>h3</li>
							<li>label</li>
							<li>.submit-btn</li>
						</ul>
					</div>
				</div>
			</div>
			<div id="formSuccessDialog" title="Edit Success Message">
				<div class="row">
					<div class="col-12">
						<p style="margin-bottom: 1em;">Add a custom message displayed on a successful form submission</p>
						<label for="formSuccessTitle" style="display:block">Sucess Title</label>
						<input type="text" id="formSuccessTitle" name="formSuccessTitle" placeholder="Success Title" style="margin-bottom: 0.5em; padding:0.4em 0.5em; width: 100%">
						<label for="formSuccessContent" style="display:block">Success Content</label>
						<textarea name="formSuccessContent" placeholder="Success Content" rows="5" style="padding:0.4em 0.5em; width: 100%"></textarea>
					</div>
				</div>
			</div>
			<div id="formPreviewDialog" title="Preview">
				<iframe id="formPreviewIframe" name="formPreviewIframe" width="1024" height="768" style="border: 0"></iframe>
			</div>
		`
		);
		
		//get forms
		$.ajax({
	    	url : settings.serviceURL,
	   		type: "GET",
	    	data : {
	    		"cmd":"getformtemplates"
	    	},
	    	dataType: "json",
	    	success: function(data, textStatus, jqXHR)
	    	{
	    		if(data.forms){
	    			for(i in data.forms){
	    				$wrapper.find('#selectform').append('<option value="' + data.forms[i].idDocument + '">' + data.forms[i].Title + '</option>');
	    			}
	    		}
	    	}
	    });
		
	}
	
	function actions($wrapper, settings, editStyleDialog, formPreviewDialog, editSuccessDialog){
	
		$wrapper.on('click', '#newReferral', function(){
			$wrapper.find('#selectform').val("").change();
			settings.formBuilder = $wrapper.find('#formBuilderContent').empty().formBuilder({
				inputSets: settings.inputSets,
				fields: settings.fields,
				disableFields: settings.disableFields,
				disabledAttrs: settings.disabledAttrs,
				disabledActionButtons: settings.disabledActionButtons,
				actionButtons: settings.actionButtons,
				typeUserAttrs: settings.typeUserAttrs,
				layoutTemplates: settings.layoutTemplates,
				onSave: onSave
			});
		});
		
		$wrapper.on('change', '#selectform', function(){
			var idDocument = $(this).val();
			if(idDocument){
				$.ajax({
	    			url : settings.serviceURL,
	   				type: "GET",
	    			data : {
	    				"cmd":"loadformtemplate",
	    				"idDocument": idDocument
	    			},
	    			dataType: "json",
	    			success: function(data, textStatus, jqXHR)
	    			{
	    				if(data.status == "success"){
	    					settings.formBuilder = $wrapper.find('#formBuilderContent').empty().formBuilder({
	    						formData: data.formTemplate,
								inputSets: settings.inputSets,
								fields: settings.fields,
								disableFields: settings.disableFields,
								disabledAttrs: settings.disabledAttrs,
								disabledActionButtons: settings.disabledActionButtons,
								actionButtons: settings.actionButtons,
								typeUserAttrs: settings.typeUserAttrs,
								layoutTemplates: settings.layoutTemplates,
								onSave: onSave
							});
							
							$wrapper.find('#formiframe').empty().append('<strong>Embed Code: </strong>').append($('<code/>').text('<iframe src="' + data.formURL + '" width="100%" height="1000"></iframe>'));
	    					editStyleDialog.find('textarea').val(data.formStyle).data('oldstyles', data.formStyle);
	    					$wrapper.find('#formTitle').val(data.formTitle);
	    				}
	    			}
	    		});
			}else{
				$wrapper.find('#formBuilderContent').empty();
				$wrapper.find('#formiframe').empty();
				$wrapper.find('#formTitle').val("");
				editStyleDialog.find('textarea').val('').data('oldstyles', '');
			}
			
		});
		
		var onSave = function(event, formData){
			
			var idDocument = $wrapper.find('#selectform').val();
			var title = $wrapper.find('#formTitle').val();
			var style = editStyleDialog.find('textarea').val();
			var formData = settings.formBuilder.actions.getData();
			
			if(idDocument, title, style, formData){
				$.ajax({
	    			url : settings.serviceURL,
	   				type: "POST",
	    			data : {
	    				"cmd":"saveformtemplate",
	    				"idDocument": idDocument,
	    				"title": title,
	    				"doc": JSON.stringify(formData),
	    				"style": style,
	    			},
	    			dataType: "json",
	    			success: function(data, textStatus, jqXHR)
	    			{
	    				if(data.status == "success"){
	    					flagAlertMessage(data.msg, false);
	    					$wrapper.find('#selectform').val(idDocument).change();
	    				}else if(data.status == "error" && data.errors){
	    					var errors  = "<ul>";
	    					for(i in data.errors){
	    						console.log(data.errors[i]);
	    						if(data.errors[i].errors){
	    							for(k in data.errors[i].errors){
	    								errors += "<li>Styles: Line:" + data.errors[i].errors[k].line[0] + ":" + data.errors[i].errors[k].message[0] + "</li>";
	    							}
	    						}
	    					}
	    					errors += "</ul>";
	    					
	    					flagAlertMessage("The following erros were found" + errors, true);
	    				}else if(data.status == "error"){
	    					flagAlertMessage(data.msg, true);
	    				}
	    			}
	    		});
			}
			
		}
	}
}(jQuery));