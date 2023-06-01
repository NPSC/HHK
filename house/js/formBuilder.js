/**
 * formBuilder.js
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

		setUpDemogFields(options);
		setUpDataSources(options);

	    var defaults = {    
            serviceURL: 'ws_resc.php',
            previewURL: 'showReferral.php',
            formBuilder: null,
            labels: {},
            fieldOptions: {},
            demogs:{},
            fields: [
    		{
    			"type": "select",
    			"label": "Referral Source",
    			"placeholder": "Referral Source",
    			"className": "form-select",
    			"name": "patient.demographics.Media_Source",
    			"width": "col-md-2",
    			"dataSource":"mediaSource",
    			"multiple": false,
    			"values": []
  			},
  			{
    			"type": "select",
    			"label": (options.labels.diagnosis || 'Diagnosis'),
    			"placeholder": (options.labels.diagnosis || 'Diagnosis'),
    			"className": "form-select",
    			"name": "hospital.diagnosis",
    			"width": "col-md-2",
    			"dataSource":"diagnosis",
    			"multiple": false,
    			"values": []
  			},
  			... (options.fieldOptions.diagnosisDetails ?
  				[{
				"type": "text",
				"required": false,
    			"label": (options.labels.diagnosis || 'Diagnosis') + " Details",
    			"placeholder": (options.labels.diagnosis || 'Diagnosis') + " Details",
    			"className": "form-control",
    			"name": "hospital.diagnosisDetails",
    			"width": "col-md-3"
  			}]:[]),
  			{
    			"type": "select",
    			"label": (options.labels.location || 'Unit'),
    			"placeholder": (options.labels.location || 'Unit'),
    			"className": "form-select",
    			"name": "hospital.location",
    			"width": "col-md-2",
    			"dataSource":"location",
    			"multiple": false,
    			"values": []
  			},
  			... (options.demogs.Ethnicity ?
  				[{
    			"type": "select",
    			"label": (options.labels.patient || 'Patient') + " " + (options.demogs.Ethnicity.Description || 'Ethnicity'),
    			"placeholder": (options.labels.patient || 'Patient') + " " + (options.demogs.Ethnicity.Description || 'Ethnicity'),
    			"className": "form-select",
    			"name": "patient.demographics.ethnicity",
    			"width": "col-md-4",
    			"dataSource":"ethnicity",
    			"multiple": false,
    			"values": []
  			}]:[]),
    		{
    			label: "Submit",
    			type: "button",
    			subtype: "submit",
    			className: "submit-btn btn btn-primary",
    			name: "submit",
    		}
  			],
  			requiredFields:[ //fields that every referral form must include and set as required
  				'patient.firstName',
  				'patient.lastName',
  				'checkindate',
  				'checkoutdate',
  				'hospital.idHospital',
  				'submit'
  			],
            inputSets: [
      		{
        		label: (options.labels.patient || 'Patient') + ' Details',
        		name: 'patient-details', // optional - one will be generated from the label if name not supplied
        		showHeader: true, // optional - Use the label as the header for this set of inputs
        		fields: [
				{
    				"type": "select",
    				"label": (options.labels.namePrefix || 'Prefix'),
    				"placeholder": (options.labels.namePrefix || 'Prefix'),
    				"className": "form-select",
    				"name": "patient.prefix",
    				"width": "col-md-3",
    				"dataSource":"namePrefix",
    				"multiple": false,
    				"values": []
  				},
  				{
					"type": "text",
					"required": true,
    				"label": (options.labels.patient || 'Patient') + " First Name",
    				"placeholder": "First Name",
    				"className": "form-control",
    				"name": "patient.firstName",
    				"width": "col-md-3"
  				},
  				{
  					"type": "text",
  					"required": true,
    				"label": (options.labels.patient || 'Patient') + " Last Name",
    				"placeholder": "Last Name",
    				"className": "form-control",
    				"name": "patient.lastName",
    				"width": "col-md-3"
  				},
  				{
    				"type": "select",
    				"label": "Suffix",
    				"placeholder": "Suffix",
    				"className": "form-select",
    				"name": "patient.suffix",
    				"width": "col-md-3",
    				"dataSource":"nameSuffix",
    				"multiple": false,
    				"values": []
  				},
  				{
  					"type": "text",
  					"required": false,
    				"label": (options.labels.patient || 'Patient') + " Middle Name",
    				"placeholder": "Middle Name",
    				"className": "form-control",
    				"name": "patient.middleName",
    				"width": "col-md-3"
  				},
  				{
  					"type": "text",
  					"required": false,
    				"label": (options.labels.nickname || 'Nickname'),
    				"placeholder": (options.labels.nickname || 'Nickname'),
    				"className": "form-control",
    				"name": "patient.nickname",
    				"width": "col-md-4"
  				},
  				{
    				"type": "date",
    				"label": (options.labels.patient || 'Patient') + " Birthdate",
    				"placeholder": "Patient Birthdate",
    				"className": "form-control",
    				"name": "patient.birthdate",
    				"width": "col-md-4",
    				"validation": "lessThanToday"
  				},
  				... (options.patientDemogFields ? options.patientDemogFields:[]),
    			{
    				"type": "text",
    				"subtype": "tel",
    				"label": "Phone",
    				"placeholder": "Phone",
    				"className": "form-control hhk-phoneInput",
    				"name": "patient.phone",
    				"width": "col-md-6"
    			},
    			{
    				"type": "text",
    				"subtype": "email",
    				"label": "Email",
    				"placeholder": "Email",
    				"className": "form-control",
    				"name": "patient.email",
    				"width": "col-md-6"
    			}
  				]
  			},
  			{
        		label: 'Patient Emergency Contact',
        		name: 'emergency-contact',
        		showHeader: true,
        		fields: [
				{
					"type": "text",
    				"label": "First Name",
    				"placeholder": "First Name",
    				"className": "form-control",
    				"name": "patient.emerg.firstName",
    				"width": "col-md-3"
  				},
  				{
					"type": "text",
    				"label": "Last Name",
    				"placeholder": "Last Name",
    				"className": "form-control",
    				"name": "patient.emerg.lastName",
    				"width": "col-md-3"
  				},
  				{
					"type": "text",
					"subtype": "tel",
    				"label": "Phone",
    				"placeholder": "Phone",
    				"className": "form-control hhk-phoneInput",
    				"name": "patient.emerg.phone",
    				"width": "col-md-2"
  				},
  				{
					"type": "text",
					"subtype": "tel",
    				"label": "Alternate Phone",
    				"placeholder": "Alternate Phone",
    				"className": "form-control hhk-phoneInput",
    				"name": "patient.emerg.altphone",
    				"width": "col-md-2"
  				},
  				{
  					"type": "select",
    				"label": "Relationship to " + (options.labels.patient || 'Patient'),
    				"placeholder": "Relationship to " + (options.labels.patient || 'Patient'),
    				"className": "form-select",
    				"name": "patient.emerg.relation",
    				"width": "col-md-2",
    				"dataSource":"patientRelation",
    				"multiple": false,
    				"values": [
      				{
        				"label": (options.labels.patient || 'Patient') + " Relationship",
        				"value": "",
        				"selected": true
      				}
    				]
  				},
  				]
  			},
  			{
        		label: 'Vehicle',
        		name: 'vehicle',
        		showHeader: true,
        		fields: [
				{
					"type": "text",
    				"label": "Make",
    				"placeholder": "Make",
    				"className": "form-control",
    				"name": "vehicle.make",
    				"width": "col-md-3"
  				},
  				{
					"type": "text",
    				"label": "Model",
    				"placeholder": "Model",
    				"className": "form-control",
    				"name": "vehicle.model",
    				"width": "col-md-3"
  				},
  				{
					"type": "text",
    				"label": "Color",
    				"placeholder": "Color",
    				"className": "form-control",
    				"name": "vehicle.color",
    				"width": "col-md-2"
  				},
  				{
  					"type": "select",
    				"label": "State",
    				"placeholder": "State",
    				"className": "form-select",
    				"name": "vehicle.state",
    				"width": "col-md-2",
    				"dataSource":"vehicleStates",
    				"multiple": false,
    				"values": [
      				{
        				"label": "State",
        				"value": "",
        				"selected": true
      				}
    				]
  				},
  				{
					"type": "text",
    				"label": "License Plate",
    				"placeholder": "License Plate",
    				"className": "form-control",
    				"name": "vehicle.license",
    				"width": "col-md-2"
  				},
  				]
  			},
  			{
        		label: 'Patient Address',
        		name: 'pat-address',
        		showHeader: true,
        		fields: [
				{
					"type": "text",
    				"label": "Street",
    				"placeholder": "Street",
    				"className": "form-control",
    				"name": "patient.address.street",
    				"width": "col-md-12"
  				},
  				{
    				"type": "text",
    				"label": "Zip Code",
    				"placeholder": "Zip Code",
    				"className": "form-control address ckzip hhk-zipsearch ui-autocomplete-input",
    				"name": "patient.address.adrzip",
    				"width": "col-md-2"
    			},
  				{
  					"type": "text",
    				"label": "City",
    				"placeholder": "City",
    				"className": "form-control address",
    				"name": "patient.address.adrcity",
    				"width": "col-md-5"
  				},
  				... (options.fieldOptions.county ?
  				[{
  					"type": "text",
    				"label": "County",
    				"placeholder": "County",
    				"className": "form-control address",
    				"name": "patient.address.adrcounty",
    				"width": "col-md-5"
  				}]:[]),
  				{
    				"type": "select",
    				"label": "State",
    				"placeholder": "State",
    				"className": "form-select bfh-states address",
    				"name": "patient.address.adrstate",
    				"width": "col-md-2",
    				"values":[]
  				},
    			{
    				"type": "select",
    				"label": "Country",
    				"placeholder": "Country",
    				"className": "form-select bfh-countries address",
    				"name": "patient.address.adrcountry",
    				"width": "col-md-3",
    				"values":[]
    			}
    			]
  			},
  			{
        		label: (options.labels.guest || 'Guest') + 's',
        		name: 'guests',
        		showHeader: true,
        		fields: [
        		{
        			"type": "header",
        			"subtype": "h3",
        			"label": "Guest:",
        			"group": "guest",
        			"width": "col-md-2",
        			"className": "guestHeader"
    			},
    			{
  					"type": "button",
  					"name": 'removeGuest',
  					"label": "Remove " + (options.labels.guest || 'Guest'),
  					"width": "col-md-10",
  					"group":"guest",
  					"style":"danger"
  				},
  				{
  					"type": "paragraph",
  					"label": "&nbsp;",
  					"group": "guest"
  				},
    			{
    				"type": "select",
    				"label": (options.labels.namePrefix || 'Prefix'),
    				"placeholder": (options.labels.namePrefix || 'Prefix'),
    				"className": "form-select",
    				"name": "guests.g0.prefix",
    				"width": "col-md-3",
    				"dataSource":"namePrefix",
    				"multiple": false,
    				"values": [],
    				"group": "guest"
  				},
				{
					"type": "text",
    				"label": "First Name",
    				"placeholder": "First Name",
    				"className": "form-control",
    				"name": "guests.g0.firstName",
    				"width": "col-md-3",
    				"group": "guest"
  				},
  				{
  					"type": "text",
    				"label": "Last Name",
    				"placeholder": "Last Name",
    				"className": "form-control",
    				"name": "guests.g0.lastName",
    				"width": "col-md-3",
    				"group": "guest"
  				},
  				{
    				"type": "date",
    				"label": "Birthdate",
    				"placeholder": "Birthdate",
    				"className": "form-control",
    				"name": "guests.g0.birthdate",
    				"width": "col-md-4",
    				"validation": "lessThanToday",
    				"group": "guest"
  				},
  				{
    				"type": "text",
    				"subtype": "tel",
    				"label": "Phone",
    				"placeholder": "Phone",
    				"className": "form-control hhk-phoneInput",
    				"name": "guests.g0.phone",
    				"width": "col-md-3",
    				"group": "guest"
    			},
    			{
    				"type": "text",
    				"subtype": "email",
    				"label": "Email",
    				"placeholder": "Email",
    				"className": "form-control",
    				"name": "guests.g0.email",
    				"width": "col-md-3",
    				"group": "guest"
    			},
    			... (options.guestDemogFields ? options.guestDemogFields:[]),
    			{
  					"type": "select",
    				"label": "Relationship to " + (options.labels.patient || 'Patient'),
    				"placeholder": "Relationship to " + (options.labels.patient || 'Patient'),
    				"className": "form-select",
    				"name": "guests.g0.relationship",
    				"width": "col-md-3",
    				"group": "guest",
    				"dataSource":"patientRelation",
    				"multiple": false,
    				"values": [
      				{
        				"label": (options.labels.patient || 'Patient') + " Relationship",
        				"value": "",
        				"selected": true
      				}
    				]
  				},
  				{
  					"type": "paragraph",
  					"label": "<hr>",
  					"group": "guest"
  				},
  				{
  					"type": "button",
  					"name": 'addGuest',
  					"label": "Add " + (options.labels.guest || 'Guest'),
  					"style":"success"
  				}
  				]
  			},
  			{
        		label: 'Guest Address',
        		name: 'guest-address',
        		showHeader: true,
        		fields: [
				{
					"type": "text",
    				"label": "Street",
    				"placeholder": "Street",
    				"className": "form-control",
    				"name": "guests.g0.address.street",
    				"width": "col-md-12",
    				"group": "guest"
    				
  				},
  				{
    				"type": "text",
    				"label": "Zip Code",
    				"placeholder": "Zip Code",
    				"className": "form-control address ckzip hhk-zipsearch ui-autocomplete-input",
    				"name": "guests.g0.address.adrzip",
    				"width": "col-md-2",
    				"group": "guest"
    			},
  				{
  					"type": "text",
    				"label": "City",
    				"placeholder": "City",
    				"className": "form-control address",
    				"name": "guests.g0.address.adrcity",
    				"width": "col-md-5",
    				"group": "guest"
  				},
  				... (options.fieldOptions.county ?
  				[{
  					"type": "text",
    				"label": "County",
    				"placeholder": "County",
    				"className": "form-control address",
    				"name": "guests.g0.address.adrcounty",
    				"width": "col-md-5",
    				"group": "guest"
  				}]:[]),
  				{
    				"type": "select",
    				"label": "State",
    				"placeholder": "State",
    				"className": "form-select bfh-states address",
    				"name": "guests.g0.address.adrstate",
    				"width": "col-md-2",
    				"values":[],
    				"group": "guest"
  				},
    			{
    				"type": "select",
    				"label": "Country",
    				"placeholder": "Country",
    				"className": "form-select bfh-countries address",
    				"name": "guests.g0.address.adrcountry",
    				"width": "col-md-3",
    				"values":[],
    				"group": "guest"
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
					"required": true,
    				"label": "Checkin Date",
					"placeholder": "Checkin Date",
    				"className": "form-control",
    				"name": "checkindate",
    				"width": "col-md-6"
  				},
  				{
					"type": "date",
					"required": true,
    				"label": "Checkout Date",
					"placeholder": "Checkout Date",
    				"className": "form-control",
    				"name": "checkoutdate",
    				"width": "col-md-6"
  				}
  				]
  			},
  			{
        		label: (options.labels.hospital || 'Hospital') + ' Info',
        		name: 'hospital-info',
        		showHeader: true,
        		fields: [
				{
					"type": "select",
					"required": true,
    				"label": (options.labels.hospital || 'Hospital'),
    				"placeholder": (options.labels.hospital || 'Hospital'),
    				"className": "form-select",
    				"name": "hospital.idHospital",
    				"width": "col-md-3",
    				"dataSource":"hospitals",
    				"multiple": false,
    				"values": [
    				{
    					"label": (options.labels.hospital || 'Hospital'),
    					"value": "",
    					"selected": true
    				}
    				]
  				},
  				{
					"type": "text",
    				"label": (options.labels.mrn || 'MRN'),
    				"placeholder": (options.labels.mrn || 'MRN'),
    				"className": "form-control",
    				"name": "hospital.mrn",
    				"width": "col-md-3",
  				},
  				... (options.fieldOptions.doctor ?
  				[{
					"type": "text",
    				"label": "Doctor",
    				"placeholder": "Doctor",
    				"className": "form-control",
    				"name": "hospital.doctor",
    				"width": "col-md-3",
  				}]:[]),
  				{
					"type": "date",
    				"label": (options.labels.treatmentStart || 'Treatment Start'),
    				"placeholder": (options.labels.treatmentStart || 'Treatment Start'),
    				"className": "form-control",
    				"name": "hospital.treatmentStart",
    				"width": "col-md-3",
  				},
  				{
					"type": "date",
    				"label": (options.labels.treatmentEnd || 'Treatment End'),
    				"placeholder": (options.labels.treatmentEnd || 'Treatment End'),
    				"className": "form-control",
    				"name": "hospital.treatmentEnd",
    				"width": "col-md-3",
  				}
  				]
  			},
  			... (options.fieldOptions.referralAgent ?
  			[{
        		label: 'Referral Agent',
        		name: 'referral-agent',
        		showHeader: true,
        		fields: [
  				{
					"type": "text",
    				"label": "First Name",
    				"placeholder": "First Name",
    				"className": "form-control",
    				"name": "hospital.referralAgent.firstName",
    				"width": "col-md-3",
  				},
  				{
					"type": "text",
    				"label": "Last Name",
    				"placeholder": "Last Name",
    				"className": "form-control",
    				"name": "hospital.referralAgent.lastName",
    				"width": "col-md-3",
  				},
  				{
					"type": "text",
					"subtype": "tel",
    				"label": "Phone",
    				"placeholder": "Phone",
    				"className": "form-control hhk-phoneInput",
    				"name": "hospital.referralAgent.phone",
    				"width": "col-md-3",
  				},
  				{
					"type": "text",
    				"label": "Email",
    				"placeholder": "Email",
    				"className": "form-control",
    				"name": "hospital.referralAgent.email",
    				"width": "col-md-3",
  				},
  				]
  				
  			}]:[])
  			],
  			disableFields: [
      			'autocomplete',
      			'button',
      			'file',
      			'hidden',
      			'number'
    		],
    		actionButtons: [
    		{
    			id: 'editSettingsAction',
    			className: 'ui-button ui-corner-left',
    			label: 'Form Settings',
    			type: 'button',
    			events: {
    				click: function() {
      					settingsDialog.dialog('open');
    				}
  				}
  			},
  			{
    			id: 'previewAction',
    			className: 'ui-button',
    			label: 'Preview',
    			type: 'button',
    			events: {
    				click: function() {
    					var formData = settings.formBuilder.actions.getData();
    					
    					var f = $("<form target='formPreviewIframe' method='POST' style='display:none;'></form>").attr({
        					action: settings.previewURL
    					}).appendTo(document.body);
    					
    					f.append('<input type="hidden" name="cmd" value="preview">');
    					f.append('<textarea name="formData" style="display:none">' + JSON.stringify(formData) + '</textarea>');
    					f.append('<input type="hidden" name="style" value="' + settingsDialog.find("textarea#formStyle").val() + '">');
    					f.append('<input type="hidden" name="initialGuests" value="' + settingsDialog.find("input[name=initialGuests]").val() + '">');
    					f.append('<input type="hidden" name="maxGuests" value="' + settingsDialog.find("input[name=maxGuests]").val() + '">');
    					f.submit();
    					f.remove();
	    				
      					formPreviewDialog.dialog('open');
    				}
  				}
  			},
  			{
    			id: 'saveAction',
    			className: 'ui-button ui-corner-right',
    			label: 'Save and Publish',
    			type: 'button',
    			events: {
    				click: function() {
    					settings.formBuilder.actions.save();
    				}
  				}
  			}
  			],
  			disabledAttrs: ['access', 'name'],
  			typeUserAttrs: {
  				header: {
					width: {
      					label: 'Field Width',
      					multiple: false,
      					options: {
        					'col-md-12': '12 Columns (100%)',
        					'col-md-11': '11 Columns (91%)',
        					'col-md-10': '10 Columns (83%)',
        					'col-md-9': '9 Columns (75%)',
        					'col-md-8': '8 Columns (66%)',
        					'col-md-7': '7 Columns (58%)',
        					'col-md-6': '6 Columns (50%)',
        					'col-md-5': '5 Columns (41%)',
        					'col-md-4': '4 Columns (33%)',
        					'col-md-3': '3 Columns (25%)',
        					'col-md-2': '2 Columns (16%)',
        					'col-md-1': '1 Column (8%)',
      					},
      					value: 'col-md-12'
    				},
  					group: {
  						label: 'Group',
  						multiple: false,
  						options: {
  							'': '',
  							'guest': 'Guest (used with Add Guest button)'
  						}
  					}
  				},
  				button: {
					width: {
      					label: 'Field Width',
      					multiple: false,
      					options: {
        					'col-md-12': '12 Columns (100%)',
        					'col-md-11': '11 Columns (91%)',
        					'col-md-10': '10 Columns (83%)',
        					'col-md-9': '9 Columns (75%)',
        					'col-md-8': '8 Columns (66%)',
        					'col-md-7': '7 Columns (58%)',
        					'col-md-6': '6 Columns (50%)',
        					'col-md-5': '5 Columns (41%)',
        					'col-md-4': '4 Columns (33%)',
        					'col-md-3': '3 Columns (25%)',
        					'col-md-2': '2 Columns (16%)',
        					'col-md-1': '1 Column (8%)',
      					},
      					value: 'col-md-12'
    				},
  					group: {
  						label: 'Group',
  						multiple: false,
  						options: {
  							'': '',
  							'guest': 'Guest (used with Add Guest button)'
  						}
  					}
  				},
  				text: {
    				width: {
      					label: 'Field Width',
      					multiple: false,
      					options: {
        					'col-md-12': '12 Columns (100%)',
        					'col-md-11': '11 Columns (91%)',
        					'col-md-10': '10 Columns (83%)',
        					'col-md-9': '9 Columns (75%)',
        					'col-md-8': '8 Columns (66%)',
        					'col-md-7': '7 Columns (58%)',
        					'col-md-6': '6 Columns (50%)',
        					'col-md-5': '5 Columns (41%)',
        					'col-md-4': '4 Columns (33%)',
        					'col-md-3': '3 Columns (25%)',
        					'col-md-2': '2 Columns (16%)',
        					'col-md-1': '1 Column (8%)',
      					},
      					value: 'col-md-12'
    				},
    				group: {
  						label: 'Group',
  						multiple: false,
  						options: {
  							'': '',
  							'guest': 'Guest (used with Add Guest button)'
  						}
  					}
    			},
  				number: {
    				width: {
      					label: 'Field Width',
      					multiple: false,
      					options: {
        					'col-md-12': '12 Columns (100%)',
        					'col-md-11': '11 Columns (91%)',
        					'col-md-10': '10 Columns (83%)',
        					'col-md-9': '9 Columns (75%)',
        					'col-md-8': '8 Columns (66%)',
        					'col-md-7': '7 Columns (58%)',
        					'col-md-6': '6 Columns (50%)',
        					'col-md-5': '5 Columns (41%)',
        					'col-md-4': '4 Columns (33%)',
        					'col-md-3': '3 Columns (25%)',
        					'col-md-2': '2 Columns (16%)',
        					'col-md-1': '1 Column (8%)',
      					},
      					value: 'col-md-12'
    				},
    				group: {
  						label: 'Group',
  						multiple: false,
  						options: {
  							'': '',
  							'guest': 'Guest (used with Add Guest button)'
  						}
  					}
    			},
  				select: {
    				width: {
      					label: 'Field Width',
      					multiple: false,
      					options: {
        					'col-md-12': '12 Columns (100%)',
        					'col-md-11': '11 Columns (91%)',
        					'col-md-10': '10 Columns (83%)',
        					'col-md-9': '9 Columns (75%)',
        					'col-md-8': '8 Columns (66%)',
        					'col-md-7': '7 Columns (58%)',
        					'col-md-6': '6 Columns (50%)',
        					'col-md-5': '5 Columns (41%)',
        					'col-md-4': '4 Columns (33%)',
        					'col-md-3': '3 Columns (25%)',
        					'col-md-2': '2 Columns (16%)',
        					'col-md-1': '1 Column (8%)',
      					},
      					value: 'col-md-12'
    				},
    				dataSource: {
    					label: 'Data Source',
    					multiple: false,
    					options: options.dataSources
    				},
    				group: {
  						label: 'Group',
  						multiple: false,
  						options: {
  							'': '',
  							'guest': 'Guest (used with Add Guest button)'
  						}
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
    				},
    				group: {
  						label: 'Group',
  						multiple: false,
  						options: {
  							'': '',
  							'guest': 'Guest (used with Add Guest button)'
  						}
  					},
    				validation: {
    					label: 'Validation Rule',
    					multiple: false,
    					options: {
    						'':'',
    						'lessThanToday': "Date must be in the past",
    						'greaterThanToday': "Date must be in the future"
    					}
    				}
  				},
  				paragraph: {
    				width: {
      					label: 'Field Width',
      					multiple: false,
      					options: {
        					'col-md-12': '12 Columns (100%)',
        					'col-md-11': '11 Columns (91%)',
        					'col-md-10': '10 Columns (83%)',
        					'col-md-9': '9 Columns (75%)',
        					'col-md-8': '8 Columns (66%)',
        					'col-md-7': '7 Columns (58%)',
        					'col-md-6': '6 Columns (50%)',
        					'col-md-5': '5 Columns (41%)',
        					'col-md-4': '4 Columns (33%)',
        					'col-md-3': '3 Columns (25%)',
        					'col-md-2': '2 Columns (16%)',
        					'col-md-1': '1 Column (8%)',
      					},
      					value: 'col-md-12'
    				},
    				group: {
  						label: 'Group',
  						multiple: false,
  						options: {
  							'': '',
  							'guest': 'Guest (used with Add Guest button)'
  						}
  					}
  				},
  				textarea: {
    				width: {
      					label: 'Field Width',
      					multiple: false,
      					options: {
        					'col-md-12': '12 Columns (100%)',
        					'col-md-11': '11 Columns (91%)',
        					'col-md-10': '10 Columns (83%)',
        					'col-md-9': '9 Columns (75%)',
        					'col-md-8': '8 Columns (66%)',
        					'col-md-7': '7 Columns (58%)',
        					'col-md-6': '6 Columns (50%)',
        					'col-md-5': '5 Columns (41%)',
        					'col-md-4': '4 Columns (33%)',
        					'col-md-3': '3 Columns (25%)',
        					'col-md-2': '2 Columns (16%)',
        					'col-md-1': '1 Column (8%)',
      					},
      					value: 'col-md-12'
    				},
    				group: {
  						label: 'Group',
  						multiple: false,
  						options: {
  							'': '',
  							'guest': 'Guest (used with Add Guest button)'
  						}
  					}
  				},
  				"radio-group": {
    				width: {
      					label: 'Field Width',
      					multiple: false,
      					options: {
        					'col-md-12': '12 Columns (100%)',
        					'col-md-11': '11 Columns (91%)',
        					'col-md-10': '10 Columns (83%)',
        					'col-md-9': '9 Columns (75%)',
        					'col-md-8': '8 Columns (66%)',
        					'col-md-7': '7 Columns (58%)',
        					'col-md-6': '6 Columns (50%)',
        					'col-md-5': '5 Columns (41%)',
        					'col-md-4': '4 Columns (33%)',
        					'col-md-3': '3 Columns (25%)',
        					'col-md-2': '2 Columns (16%)',
        					'col-md-1': '1 Column (8%)',
      					},
      					value: 'col-md-12'
    				},
    				group: {
  						label: 'Group',
  						multiple: false,
  						options: {
  							'': '',
  							'guest': 'Guest (used with Add Guest button)'
  						}
  					}
  				},
  				"checkbox-group": {
    				width: {
      					label: 'Field Width',
      					multiple: false,
      					options: {
        					'col-md-12': '12 Columns (100%)',
        					'col-md-11': '11 Columns (91%)',
        					'col-md-10': '10 Columns (83%)',
        					'col-md-9': '9 Columns (75%)',
        					'col-md-8': '8 Columns (66%)',
        					'col-md-7': '7 Columns (58%)',
        					'col-md-6': '6 Columns (50%)',
        					'col-md-5': '5 Columns (41%)',
        					'col-md-4': '4 Columns (33%)',
        					'col-md-3': '3 Columns (25%)',
        					'col-md-2': '2 Columns (16%)',
        					'col-md-1': '1 Column (8%)',
      					},
      					value: 'col-md-12'
    				},
    				group: {
  						label: 'Group',
  						multiple: false,
  						options: {
  							'': '',
  							'guest': 'Guest (used with Add Guest button)'
  						}
  					}
  				}
			},
			disabledActionButtons: ['data', 'save', 'clear'],
			layoutTemplates: {
  				default: function(field, label, help, data) {
    				return $('<div/>').addClass(data.width).append(field);
  				},
  				noLabel: function(field, label, help, data) {
    				return $('<div/>').addClass(data.width).append(field);
  				}
			},
			stickyControls: {
				enable: true,
				offset: {
					top: 50,
			        bottom: 'auto',
			        right: 'auto',
			    },
			}
        };

        var settings = $.extend(true, {}, defaults, options);

        var $wrapper = $(this);
        
        createMarkup($wrapper, settings);

		$wrapper.find("button").button();
		
		var settingsDialog = $wrapper.find('#settingsDialog').dialog({
      		autoOpen: false,
      		height: 800,
      		width: getDialogWidth(900),
      		modal: true,
      		buttons: {
        		"Revert Changes": function() {
        			settingsDialog.find('textarea, input').each(
        				function(i,element){
        					if($(this).prop('type') == 'checkbox'){
        						$(this).prop('checked', $(this).data('oldval'));
        					}else{
        						$(this).val($(this).data('oldVal'));
        					}
        				}
        			);
          			settingsDialog.dialog( "close" );
        		},
        		Continue: function(){
        			settingsDialog.dialog( "close" );
        		}
      		},
      		create: function (event, ui) {
      			$(event.target).find('#formSettingsTabs').tabs();
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

		var embedInfoDialog = $wrapper.find("#embedInfoDialog").dialog({
			autoOpen: false,
			height: 700,
			width: getDialogWidth(900),
			modal: true,
			buttons: {
        		Close: function(){
        			embedInfoDialog.dialog( "close" );
        		}
      		},
			create: function (event, ui) {
				//$(event.target).find("#embedAccordion").accordion();
			}
		});
	
		actions($wrapper, settings, settingsDialog, formPreviewDialog, embedInfoDialog);
		
		return this;
	}
	
	function setUpDemogFields(options){
		var patientDemogFields = [];
		var guestDemogFields = [];
		
		$.each(options.demogs, function(index, demog){
			
			patientDemogFields.push({
	  			"type": "select",
	    		"label": (options.labels.patient || 'Patient') + " " + (demog.Description || ''),
	    		"placeholder": (options.labels.patient || 'Patient') + " " + (demog.Description || ''),
	    		"className": "form-select",
	    		"name": "patient.demographics." + index,
	    		"width": "col-md-3",
	    		"dataSource":index,
	  		});
	  		
	  		guestDemogFields.push({
	  			"type": "select",
	    		"label": (options.labels.guest || 'Guest') + " " + (demog.Description || ''),
	    		"placeholder": (options.labels.guest || 'Guest') + " " + (demog.Description || ''),
	    		"className": "form-select",
	    		"name": "guests.g0.demographics." + index,
	    		"width": "col-md-3",
	    		"group": "guest",
	    		"dataSource":index,
	  		});
	  	});
	  	
	  	options.patientDemogFields = patientDemogFields;
	  	options.guestDemogFields = guestDemogFields;
	  	
	}
	
	function setUpDataSources(options){
		var dataSources = {
    		'':'',
    		'namePrefix': 'Name Prefix',
    		'nameSuffix': 'Name Suffix',
    		'patientRelation': 'Patient Relationship',
    		'vehicleStates': 'Vehicle States',
    		'hospitals': 'Hospital',
    		'diagnosis': 'Diagnosis',
    		'location': 'Unit'
    	}
    	
    	$.each(options.demogs, function(index, demog){
    		dataSources[index] = demog.Description;
    	});
    	options.dataSources = dataSources;
	}
	
	function createMarkup($wrapper, settings){
		$wrapper.html(
		`
			<div>
				<form autocomplete="off">
				<label for="selectform">Select a form: </label>
				<select id="selectform" name="selectform" style="margin: 0 0.5em; padding:0.4em 0.5em;">
					<option value=""></option>
				</select>
				<button id="newReferral">New Referral Form</button>
				<span class="formTitleContainer">
					<label for="formTitle">Form Title: </label>
					<input typle="text" id="formTitle" placeholder="Form Title" style="padding:0.4em 0.5em;">
				</span>
				<button id="formiframebtn" style="margin-left: 0.5em; display: none;">Embed Instructions</button>
				</form>
			</div>
			<div id="formBuilderContent" style="margin-top: 1em;"></div>
			<div id="settingsDialog" title="Form Settings">
			
				<div id="formSettingsTabs">
    				<ul>
        				<li><a href="#tabs-1">Success Message</a></li>
        				<li class="d-none"><a href="#tabs-2">Notifications</a></li>
        				<li><a href="#tabs-3">Form Styles</a></li>
        				<li><a href="#tabs-5">Guests</a></li>
        				<li><a href="#tabs-4">Miscellaneous</a></li>
    				</ul>
    
				    <div id="tabs-1">
				        <div class="row">
							<div class="col-12">
								<p style="margin-bottom: 1em;">Add a custom message displayed on a successful form submission</p>
								<label for="formSuccessTitle" style="display:block">Sucess Title</label>
								<input type="text" id="formSuccessTitle" name="formSuccessTitle" placeholder="Success Title" style="margin-bottom: 0.5em; padding:0.4em 0.5em; width: 100%">
								<label for="formSuccessContent" style="display:block">Success Content</label>
								<textarea id="formSuccessContent" name="formSuccessContent" placeholder="Success Content" rows="5" style="padding:0.4em 0.5em; width: 100%"></textarea>
							</div>
						</div>
				    </div>
				    
				    <div id="tabs-2" class="d-none">
				        <div class="row">
							<div class="col-12">
								<p style="margin-bottom: 1em;">Notify patient by email after form submission</p>
								<div class="mb-3">
				        		<div>
				        			<label for="emailPatient" style="margin-right: 0.5em;">Enable</label>
				        			<input type="checkbox" name="emailPatient" id="emailPatient">
				        		</div>
				        		<small class="mb-3">Email will be sent to the email address from the Patient Details section</small>
				        		</div>
								<label for="notifySubject" style="display:block">Email Subject</label>
								<input type="text" id="notifySubject" name="notifySubject" placeholder="Email Subject" style="margin-bottom: 0.5em; padding:0.4em 0.5em; width: 100%">
								<label for="notifyContent" style="display:block">Email Content</label>
								<textarea id="notifyContent" name="notifyContent" placeholder="Email Content" rows="5" style="padding:0.4em 0.5em; width: 100%"></textarea>
							</div>
						</div>
				    </div>
				    
				    <div id="tabs-3">
				        <div class="row">
							<div class="col-8">
								<h3>Edit Form Style</h3>
								<textarea id="formStyle" name="formStyle" style="width: 100%; height: 600px;"></textarea>
							</div>
							<div class="col-4">
								<h3>Import Fonts</h3>
								<p class="my-3">Use <a href="https://fonts.google.com" target="_blank">Google Fonts</a> to select the fonts for the page. Paste the final @import statement below.</p>
								<textarea id="fontImport" style="width: 100%; height: 200px;" class="mb-3"></textarea>
								<h3>Style Guide</h3>
								<p>Forms use Bootstrap 5.0 with .form-floating</p>
								<h3>Available Styles</h3>
								<ul class="styleList">
									<li>h1</li>
									<li>h2</li>
									<li>h3</li>
									<li>label</li>
									<li>.submit-btn</li>
									<li>.form-control</li>
									<li>.form-select</li>
									<li>.msg - the success message, which uses bootstrap's alert-success class</li>
									<li>.errmsg - the error message, uses bootstrap's alert-danger class</li>
									
								</ul>
								
							</div>
						</div>
				    </div>
				    
				    <div id="tabs-4">
				        <div class="row mb-3">
				        	<div class="col-12">
				        		<div>
				        			<label for="enableRecaptcha" style="margin-right: 0.5em;">Enable Recaptcha</label>
				        			<input type="checkbox" name="enableRecaptcha" id="enableRecaptcha" checked="checked">
				        		</div>
				        		<small>Combat spam submissions by using <a href="https://www.google.com/recaptcha/about/" target="_blank">Google Recaptcha Enterprise</a>. Using this service is subject to Google's <a href="https://www.google.com/intl/en/policies/terms/" target="_blank">Terms of Use</a> and <a href="https://www.google.com/intl/en/policies/privacy/" target="_blank">Privacy Policy</a></small>
				        	</div>
				        </div>
				        <div class="row mb-3">
				        	<div class="col-12">
				        		<div>
				        			<label for="enableReservation" style="margin-right: 0.5em;">Create Reservation from form</label>
				        			<input type="checkbox" name="enableReservation" id="enableReservation" checked="checked">
				        		</div>
				        		<small>When checked, this form can be turned into a reservation/referral</small>
				        	</div>
				        </div>
				    </div>
				    
				    <div id="tabs-5">
				    	<div class="row mb-3">
				    		<div class="col-12">
				    			<p>These settings affect the behavior of the Add Guest button.</p>
				    		</div>
				    	</div>
				    	<div class="row mb-3">
				    		<div class="col-12">
				    			<label for="initialGuests">Initial Guests:</label>
				        		<input type="number" name="initialGuests" min="1" max="20" style="width: 5em;">
				    		</div>
				    	</div>
				    	<div class="row mb-3">
				    		<div class="col-12">
				    			<label for="maxGuests">Max Guests:</label>
				        		<input type="number" name="maxGuests" min="1" max="20" style="width: 5em;">
				    		</div>
				    	</div>
				    	<div class="ui-state-highlight ui-corner-all p-1" id="guestErrorMsg" style="display:none;">
				    		
				    	</div>
				    </div>
    
				</div>
				
			</div>
			<div id="formPreviewDialog" title="Preview">
				<iframe id="formPreviewIframe" name="formPreviewIframe" width="1024" height="768" style="border: 0"></iframe>
			</div>
			<div id="embedInfoDialog" title="Embed Instructions">
				<p class="pb-2">To add this form to your website, you'll need to either add a code snippet, or use the Embed function of your Content Management System (Wordpress, Wix, Squarespace, etc)</p>

				<div class="ui-widget hhk-visitdialog mb-3">
					<h3 class="ui-widget-header ui-corner-top">Use a Code Snippet</h3>
					<div class="ui-widget-content ui-corner-bottom p-3">
						<p class="pb-2">If you are able to add code directly to your website, copy and paste the following Code Snippet into your website.</p>
						<div class="my-3"><label for="changeHeight">Height: </label><input type="number" id="changeHeight" min="0" value="1000" class="mr-2" style="width: 75px;">px
						<div style="font-size: 0.9em; color:#919191" class="mt-2">If you have a long form, you may need to increase this value to eliminate scrollbars on your website</div>
						</div>
						<pre id="embedCodeSnippet" class="ui-widget-content ui-corner-all p-3 hhk-overflow-x" style="white-space:pre-wrap;"></pre>
					</div>
				</div>
				<div class="ui-widget hhk-visitdialog mb-3">
					<h3 class="ui-widget-header ui-corner-top">Embedding in a CMS</h3>
					<div class="ui-widget-content ui-corner-bottom p-3">
						<p class="pb-2">When embedding into a CMS, look for a function called "Embed", "Embed a Website", "Embed an iFrame", "Add External Content", or something similar.</p>
						<p class="pb-2">When asked what website you want to embed, copy and paste the following URL</p>
						<pre id="embedURL" class="ui-widget-content ui-corner-all p-3 hhk-overflow-x" style="white-space:pre-wrap;"></pre>
					</div>
				</div>
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
	
	function actions($wrapper, settings, settingsDialog, formPreviewDialog, embedInfoDialog){
	
		$wrapper.on('click', '#newReferral', function(e){
			e.preventDefault();
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
				onSave: onSave,
				onAddField:onAddField,
				//onCloseFieldEdit:onCloseFieldEdit,
				stickyControls: settings.stickyControls,
				"i18n":{
					"location":"../js/formBuilder"
				}
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
								onSave: onSave,
								onAddField:onAddField,
								//onCloseFieldEdit:onCloseFieldEdit,
								stickyControls: settings.stickyControls,
								"i18n":{
									"location":"../js/formBuilder"
								}
							});
							
							$wrapper.find('#formiframebtn').data('url', data.formURL).show();
							settingsDialog.find('input#formSuccessTitle').val(data.formSettings.successTitle).data('oldVal',data.formSettings.successTitle);
							settingsDialog.find('textarea#formSuccessContent').val(data.formSettings.successContent).data('oldVal',data.formSettings.successContent);
	    					settingsDialog.find('textarea#formStyle').val(data.formSettings.formStyle).data('oldVal', data.formSettings.formStyle);
	    					settingsDialog.find('input#enableRecaptcha').prop('checked', data.formSettings.enableRecaptcha).data('oldval', data.formSettings.enableRecaptcha);
	    					settingsDialog.find('input#enableReservation').prop('checked', data.formSettings.enableReservation).data('oldval', data.formSettings.enableReservation);
	    					settingsDialog.find('input#notifySubject').val(data.formSettings.notifySubject).data('oldVal',data.formSettings.notifySubject);
							settingsDialog.find('textarea#notifyContent').val(data.formSettings.notifyContent).data('oldVal',data.formSettings.notifyContent);
	    					settingsDialog.find('input#emailPatient').prop('checked', data.formSettings.emailPatient).data('oldval', data.formSettings.emailPatient);
	    					settingsDialog.find('input[name=initialGuests]').val(data.formSettings.initialGuests).data('oldVal',data.formSettings.initialGuests);
	    					settingsDialog.find('input[name=maxGuests]').val(data.formSettings.maxGuests).data('oldVal',data.formSettings.maxGuests);
	    					settingsDialog.find('textarea#fontImport').val(data.formSettings.fontImport).data('oldVal',data.formSettings.fontImport);
	    					$wrapper.find('#formTitle').val(data.formTitle);
	    				}
	    			}
	    		});
			}else{
				$wrapper.find('#formBuilderContent').empty();
				$wrapper.find('#formiframebtn').data('code', '').hide();
				$wrapper.find('#formTitle').val("");
				settingsDialog.find('textarea').val('').data('oldstyles', '');
				settingsDialog.find('input#enableRecaptcha').prop('checked', false);
			}
			
		});
		
		$wrapper.on('click', '#formiframebtn', function(e){
			e.preventDefault();
			let url = $(this).data("url");
			embedInfoDialog.find("#embedURL").text(url);
			embedInfoDialog.find("#embedCodeSnippet").text('<iframe src="' + url + '" width="100%" height="1000"></iframe>');
			embedInfoDialog.dialog("open");
		});
		
		embedInfoDialog.on('change', 'input#changeHeight', function(e){
			let val = $(this).val();
			let embedCode = embedInfoDialog.find("#embedCodeSnippet").text();
			console.log(val);
			console.log(embedCode);
			embedCode = embedCode.replace(/(height=")([0-9]*)(")/gm, '$1' + val + '$3');
			console.log(embedCode);
			embedInfoDialog.find("#embedCodeSnippet").text(embedCode);
		});

		$wrapper.on('blur', '[contenteditable]', function(){
			var val = $(this).html().replaceAll('"', "'");
			$(this).html(val);
//			//console.log(val);
//			//console.log($(this).html());
		});
		
		settingsDialog.on('blur', 'input[name=initialGuests], input[name=maxGuests]', function(){
			var min = parseInt($(this).attr('min'));
			var max = parseInt($(this).attr('max'));
			var val = parseInt($(this).val());
			
			if(val < min){
				$(this).val(min);
			}
			
			if(val > max){
				$(this).val(max);
			}
			
			//check if initial is less than max
			var initial = parseInt($("input[name=initialGuests]").val());
			var max = parseInt($("input[name=maxGuests]").val());
			if(initial > max){
				//console.log("initial>max true");
				settingsDialog.find("#guestErrorMsg").text("Initial guests cannot be greater than max guests").show();
			}else{
				//console.log("initial>max false");
				settingsDialog.find("#guestErrorMsg").text("").hide();
			}
		});
		
		var onAddField = function(fieldId, field){
			var formData = (settings.formBuilder.actions.getData instanceof Function) ? settings.formBuilder.actions.getData() : [];
			var filtered = formData.filter(x=> (x.name === field.name)); //check for duplicate field
			
			if(filtered.length > 1 && field.name && field.label){
				var msg = "<strong>" + field.label + " (" + field.name + ")</strong> already exists on the form, duplicate field removed";
				flagAlertMessage(msg, true);
				settings.formBuilder.actions.removeField(fieldId);
			}
		};
		
		var onCloseFieldEdit = function(editPanel){
			var group = $(editPanel).find('select[name=group]').val();
                        var fieldType = $(editPanel).parent('li').prop('type');
			
			if(group === 'guest' && (fieldType === 'checkbox-group' || fieldType === 'radio-group' )){
				flagAlertMessage('The "Guest" group does not support checkbox or radio buttons, the field has been removed from the "Guest" group', true);
				$(editPanel).find('select[name=group]').val("");
			}
		};
		
		var onSave = function(event, formData){
			settings.formBuilder.actions.closeAllFieldEdit();
			
			var idDocument = $wrapper.find('#selectform').val();
			var title = $wrapper.find('#formTitle').val();
			var style = settingsDialog.find('textarea#formStyle').val();
			var successTitle = settingsDialog.find('input#formSuccessTitle').val();
			var successContent = settingsDialog.find('textarea#formSuccessContent').val();
			var enableRecaptcha = settingsDialog.find('input#enableRecaptcha').prop('checked');
			var enableReservation = settingsDialog.find('input#enableReservation').prop('checked');
			var emailPatient = settingsDialog.find('input#emailPatient').prop('checked');
			var notifySubject = settingsDialog.find('input#notifySubject').val();
			var notifyContent = settingsDialog.find('textarea#notifyContent').val();
			var initialGuests = settingsDialog.find('input[name=initialGuests]').val();
			var maxGuests = settingsDialog.find('input[name=maxGuests]').val();
			var fontImport = settingsDialog.find('textarea#fontImport').val();
			var formData = settings.formBuilder.actions.getData();
			
			if(typeof idDocument !== 'undefined', typeof title !== 'undefined', typeof style !== 'undefined', typeof formData !== 'undefined', typeof successTitle !== 'undefined', typeof successContent !== 'undefined'){
				//check required fields
				var missingFields = [];
				var emailErrorMsg = '';
				
				//check field group
				formData.forEach(function(field){
					/*if(field.group == 'guest' && field.name && !field.name.startsWith("guests.g")){
						field.group = '';
						flagAlertMessage('The "Guest" group can only be used with Guest fields, group has been removed', true);
					}*/
				});
				
				if(emailPatient){
					var filtered = formData.filter(x=> (x.name === 'patient.email'));
					if(filtered.length == 0){
						emailErrorMsg += "<br>The patient email field is required for email notifications";
					}
					if(notifySubject.length == 0){
						emailErrorMsg += "<br>Email Subject cannot be blank";
					}
					if(notifyContent.length == 0){
						emailErrorMsg += "<br>Email Content cannot be blank";
					}
				}
				
				settings.requiredFields.forEach(function(field){
					var filtered = formData.filter(x=> (x.name === field && x.required === true) || x.name === 'submit');
					if(filtered.length == 0){
						missingFields.push(field);
					}
				});
				
				//format font import
				matches = fontImport.match(/@import url\('https:\/\/fonts.googleapis.com\/css2(\?.*)\'\);/);
				if(Array.isArray(matches) && matches[1] != null){
					queryString = matches[1];
					urlparams = new URLSearchParams(queryString);
  					fontImport = urlparams.getAll("family");
				}else{
					settingsDialog.find('textarea#fontImport').val('');
				}
				
				if(missingFields.length == 0 && title.length > 0 && emailErrorMsg.length == 0){
					$.ajax({
		    			url : settings.serviceURL,
		   				type: "POST",
		    			data : {
		    				"cmd":"saveformtemplate",
		    				"idDocument": idDocument,
		    				"title": title,
		    				"doc": JSON.stringify(formData),
		    				"style": style,
		    				"successTitle": successTitle,
		    				"successContent": successContent,
		    				"enableRecaptcha": enableRecaptcha,
		    				"enableReservation": enableReservation,
		    				"emailPatient": emailPatient,
		    				"notifySubject": notifySubject,
		    				"notifyContent": notifyContent,
		    				"initialGuests": initialGuests,
		    				"maxGuests": maxGuests,
		    				"fontImport": fontImport,
		    			},
		    			dataType: "json",
		    			success: function(data, textStatus, jqXHR)
		    			{
		    				if(data.status == "success"){
		    					flagAlertMessage(data.msg, false);
		    					
		    					if(data.doc){
		    						$wrapper.find('#selectform').append('<option value="' + data.doc.idDocument + '">' + data.doc.title + '</option>');
		    						idDocument = data.doc.idDocument;
		    					}
		    					
		    					$wrapper.find('#selectform').val(idDocument).change();
		    				}else if(data.status == "error" && data.errors){
		    					var errors  = "<ul>";
		    					for(i in data.errors){
		    						if(data.errors[i].errors){
		    							for(k in data.errors[i].errors){
		    								errors += "<li>Styles: Line:" + data.errors[i].errors[k].line[0] + ":" + data.errors[i].errors[k].message[0] + "</li>";
		    							}
		    						}else{
		    							errors += "<li>" + data.errors[i] + "</li>";
		    						}
		    					}
		    					errors += "</ul>";
		    					
		    					flagAlertMessage("The following errors were found" + errors, true);
		    				}else if(data.status == "error"){
		    					flagAlertMessage(data.msg, true);
		    				}
		    			}
		    		});
	    		}else{
	    			var errorMsg = "<strong>Error: </strong>";
	    			if(title.length == 0){
	    				errorMsg += "Form title is required<br>"
	    			}
	    			if(emailErrorMsg.length > 0){
	    				errorMsg+= emailErrorMsg;
	    			}
	    			if(missingFields.length > 0){
	    				errorMsg += "The following fields must be included and set as required: " + missingFields.join(', ');
	    			}
	    			flagAlertMessage(errorMsg, true);
	    		}
			}
			
		}
	}
}(jQuery));