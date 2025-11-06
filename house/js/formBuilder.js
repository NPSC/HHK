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
		setUpInsuranceFields(options);

		var defaults = {
			serviceURL: 'ws_resc.php',
			previewURL: 'showReferral.php',
			formBuilder: null,
			labels: {},
			fieldOptions: {},
			demogs: {},
			fields: [
				{
					"type": "select",
					"label": "Select",
					"className": "form-select"
				},
				{
					"type": "select",
					"label": "Referral Source",
					"placeholder": "Referral Source",
					"className": "form-select",
					"name": "patient.demographics.Media_Source",
					"width": "col-md-2",
					"dataSource": "mediaSource",
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
					"dataSource": "diagnosis",
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
					}] : []),
				{
					"type": "select",
					"label": (options.labels.location || 'Unit'),
					"placeholder": (options.labels.location || 'Unit'),
					"className": "form-select",
					"name": "hospital.location",
					"width": "col-md-2",
					"dataSource": "location",
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
						"dataSource": "ethnicity",
						"multiple": false,
						"values": []
					}] : []),
				{
					label: "Notes",
					type: "textarea",
					className: "form-control",
					width: "col-md-12",
					name: "resvNotes",
					hhkField: (options.labels.reservation || 'Reservation') + " Notes",
				},
				{
					label: "Submit",
					type: "button",
					subtype: "submit",
					className: "submit-btn btn btn-primary",
					name: "submit",
				},
				{
					"type": "text",
					"subtype": "email",
					"label": "Confirmation Email",
					"placeholder": "Confirmation Email",
					"description": "Send a confirmation email on form submission",
					"className": "form-control",
					"name": "notifyMeEmail",
					"hhkField": "",
					"width": "col-md-3",
				}
			],
			requiredFields: [ //fields that every referral form must include and set as required
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
							"hhkField": (options.labels.patient || 'Patient') + " " + (options.labels.namePrefix || 'Prefix'),
							"width": "col-md-3",
							"dataSource": "namePrefix",
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
							"hhkField": (options.labels.patient || 'Patient') + " First Name",
							"width": "col-md-3"
						},
						{
							"type": "text",
							"required": true,
							"label": (options.labels.patient || 'Patient') + " Middle Name",
							"placeholder": "Middle Name",
							"className": "form-control",
							"name": "patient.middleName",
							"hhkField": (options.labels.patient || 'Patient') + " Middle Name",
							"width": "col-md-3"
						},
						{
							"type": "text",
							"required": true,
							"label": (options.labels.patient || 'Patient') + " Last Name",
							"placeholder": "Last Name",
							"className": "form-control",
							"name": "patient.lastName",
							"hhkField": (options.labels.patient || 'Patient') + " Last Name",
							"width": "col-md-3"
						},
						{
							"type": "select",
							"label": "Suffix",
							"placeholder": "Suffix",
							"className": "form-select",
							"name": "patient.suffix",
							"hhkField": (options.labels.patient || 'Patient') + " Suffix",
							"width": "col-md-3",
							"dataSource": "nameSuffix",
							"multiple": false,
							"values": []
						},
						{
							"type": "text",
							"required": false,
							"label": (options.labels.nickname || 'Nickname'),
							"placeholder": (options.labels.nickname || 'Nickname'),
							"className": "form-control",
							"name": "patient.nickname",
							"hhkField": (options.labels.patient || 'Patient') + " " + (options.labels.nickname || 'Nickname'),
							"width": "col-md-4"
						},
						{
							"type": "date",
							"label": (options.labels.patient || 'Patient') + " Birthdate",
							"placeholder": "Patient Birthdate",
							"className": "form-control",
							"name": "patient.birthdate",
							"hhkField": (options.labels.patient || 'Patient') + " Birthdate",
							"width": "col-md-4",
							"validation": "lessThanToday"
						},
						... (options.patientDemogFields ? options.patientDemogFields : []),
						{
							"type": "text",
							"subtype": "tel",
							"label": "Phone",
							"placeholder": "Phone",
							"className": "form-control hhk-phoneInput",
							"name": "patient.phone",
							"hhkField": (options.labels.patient || 'Patient') + " Phone",
							"width": "col-md-6"
						},
						{
							"type": "select",
							"label": "SMS Opt In",
							"placeholder": "Opt in to receive text messages",
							"className": "form-select",
							"name": "patient.sms_status",
							"hhkField": (options.labels.patient || 'Patient') + " SMS Opt In",
							"width": "col-md-3",
							"multiple": false,
							"values": [
								{
									"label": "",
									"value": "",
									"selected": true
								},
								{
									"label": "Opt In",
									"value": "opt_in",
									"selected": false
								},
								{
									"label": "Opt Out",
									"value": "opt_out",
									"selected": false
								}
							]
						},
						{
							"type": "text",
							"subtype": "email",
							"label": "Email",
							"placeholder": "Email",
							"className": "form-control",
							"name": "patient.email",
							"hhkField": (options.labels.patient || 'Patient') + " Email",
							"width": "col-md-6"
						}
					]
				},
				{
					label: (options.labels.patient || 'Patient') + ' Emergency Contact',
					name: 'emergency-contact',
					showHeader: true,
					fields: [
						{
							"type": "text",
							"label": "First Name",
							"placeholder": "First Name",
							"className": "form-control",
							"name": "patient.emerg.firstName",
							"hhkField": (options.labels.patient || 'Patient') + " Emergency Contact First Name",
							"width": "col-md-3"
						},
						{
							"type": "text",
							"label": "Last Name",
							"placeholder": "Last Name",
							"className": "form-control",
							"name": "patient.emerg.lastName",
							"hhkField": (options.labels.patient || 'Patient') + " Emergency Contact Last Name",
							"width": "col-md-3"
						},
						{
							"type": "text",
							"subtype": "tel",
							"label": "Phone",
							"placeholder": "Phone",
							"className": "form-control hhk-phoneInput",
							"name": "patient.emerg.phone",
							"hhkField": (options.labels.patient || 'Patient') + " Emergency Contact Phone",
							"width": "col-md-2"
						},
						{
							"type": "text",
							"subtype": "tel",
							"label": "Alternate Phone",
							"placeholder": "Alternate Phone",
							"className": "form-control hhk-phoneInput",
							"name": "patient.emerg.altphone",
							"hhkField": (options.labels.patient || 'Patient') + " Emergency Contact Alternate Phone",
							"width": "col-md-2"
						},
						{
							"type": "select",
							"label": "Relationship to " + (options.labels.patient || 'Patient'),
							"placeholder": "Relationship to " + (options.labels.patient || 'Patient'),
							"className": "form-select",
							"name": "patient.emerg.relation",
							"hhkField": "Emergency Contact Relationship to " + (options.labels.patient || 'Patient'),
							"width": "col-md-2",
							"dataSource": "patientRelation",
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
				... options.insuranceInputSets,
				{
					label: (options.labels.patient || 'Patient') + ' Address',
					name: 'pat-address',
					showHeader: true,
					fields: [
						{
							"type": "text",
							"label": "Street",
							"placeholder": "Street",
							"className": "form-control",
							"name": "patient.address.street",
							"hhkField": (options.labels.patient || 'Patient') + " Street",
							"width": "col-md-12"
						},
						{
							"type": "text",
							"label": "Zip Code",
							"placeholder": "Zip Code",
							"className": "form-control address ckzip hhk-zipsearch ui-autocomplete-input",
							"name": "patient.address.adrzip",
							"hhkField": (options.labels.patient || 'Patient') + " Zip Code",
							"width": "col-md-2"
						},
						{
							"type": "text",
							"label": "City",
							"placeholder": "City",
							"className": "form-control address",
							"name": "patient.address.adrcity",
							"hhkField": (options.labels.patient || 'Patient') + " City",
							"width": "col-md-5"
						},
						... (options.fieldOptions.county ?
							[{
								"type": "text",
								"label": "County",
								"placeholder": "County",
								"className": "form-control address",
								"name": "patient.address.adrcounty",
								"hhkField": (options.labels.patient || 'Patient') + " County",
								"width": "col-md-5"
							}] : []),
						{
							"type": "select",
							"label": "State",
							"placeholder": "State",
							"className": "form-select bfh-states address",
							"name": "patient.address.adrstate",
							"hhkField": (options.labels.patient || 'Patient') + " State",
							"width": "col-md-2",
							"values": []
						},
						{
							"type": "select",
							"label": "Country",
							"placeholder": "Country",
							"className": "form-select bfh-countries address",
							"name": "patient.address.adrcountry",
							"hhkField": (options.labels.patient || 'Patient') + " Country",
							"width": "col-md-3",
							"values": []
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
							"group": "guest",
							"style": "danger"
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
							"hhkField": (options.labels.guest || 'Guest') + " " + (options.labels.namePrefix || 'Prefix'),
							"width": "col-md-3",
							"dataSource": "namePrefix",
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
							"hhkField": (options.labels.guest || 'Guest') + " First Name",
							"width": "col-md-3",
							"group": "guest"
						},
						{
							"type": "text",
							"label": "Middle Name",
							"placeholder": "Middle Name",
							"className": "form-control",
							"name": "guests.g0.middleName",
							"hhkField": (options.labels.guest || 'Guest') + " Middle Name",
							"width": "col-md-3",
							"group": "guest"
						},
						{
							"type": "text",
							"label": "Last Name",
							"placeholder": "Last Name",
							"className": "form-control",
							"name": "guests.g0.lastName",
							"hhkField": (options.labels.guest || 'Guest') + " Last Name",
							"width": "col-md-3",
							"group": "guest"
						},
						{
							"type": "date",
							"label": "Birthdate",
							"placeholder": "Birthdate",
							"className": "form-control",
							"name": "guests.g0.birthdate",
							"hhkField": (options.labels.guest || 'Guest') + " Birthdate",
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
							"hhkField": (options.labels.guest || 'Guest') + " Phone",
							"width": "col-md-3",
							"group": "guest"
						},
						{
							"type": "select",
							"label": "SMS Opt In",
							"placeholder": "Opt in to receive text messages",
							"className": "form-select",
							"name": "guests.g0.sms_status",
							"hhkField": (options.labels.guest || 'Guest') + " SMS Opt In",
							"width": "col-md-3",
							"group": "guest",
							"multiple": false,
							"values": [
								{
									"label": "",
									"value": "",
									"selected": true
								},
								{
									"label": "Opt In",
									"value": "opt_in",
									"selected": false
								},
								{
									"label": "Opt Out",
									"value": "opt_out",
									"selected": false
								}
							]
						},
						{
							"type": "text",
							"subtype": "email",
							"label": "Email",
							"placeholder": "Email",
							"className": "form-control",
							"name": "guests.g0.email",
							"hhkField": (options.labels.guest || 'Guest') + " Email",
							"width": "col-md-3",
							"group": "guest"
						},
						... (options.guestDemogFields ? options.guestDemogFields : []),
						{
							"type": "select",
							"label": "Relationship to " + (options.labels.patient || 'Patient'),
							"placeholder": "Relationship to " + (options.labels.patient || 'Patient'),
							"className": "form-select",
							"name": "guests.g0.relationship",
							"hhkField": (options.labels.guest || 'Guest') + " Relationship to " + (options.labels.patient || 'Patient'),
							"width": "col-md-3",
							"group": "guest",
							"dataSource": "patientRelation",
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
							"style": "success"
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
							"hhkField": (options.labels.guest || 'Guest') + " Street",
							"width": "col-md-12",
							"group": "guest"

						},
						{
							"type": "text",
							"label": "Zip Code",
							"placeholder": "Zip Code",
							"className": "form-control address ckzip hhk-zipsearch ui-autocomplete-input",
							"name": "guests.g0.address.adrzip",
							"hhkField": (options.labels.guest || 'Guest') + " Zip Code",
							"width": "col-md-2",
							"group": "guest"
						},
						{
							"type": "text",
							"label": "City",
							"placeholder": "City",
							"className": "form-control address",
							"name": "guests.g0.address.adrcity",
							"hhkField": (options.labels.guest || 'Guest') + " City",
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
								"hhkField": (options.labels.guest || 'Guest') + " County",
								"width": "col-md-5",
								"group": "guest"
							}] : []),
						{
							"type": "select",
							"label": "State",
							"placeholder": "State",
							"className": "form-select bfh-states address",
							"name": "guests.g0.address.adrstate",
							"hhkField": (options.labels.guest || 'Guest') + " State",
							"width": "col-md-2",
							"values": [],
							"group": "guest"
						},
						{
							"type": "select",
							"label": "Country",
							"placeholder": "Country",
							"className": "form-select bfh-countries address",
							"name": "guests.g0.address.adrcountry",
							"hhkField": (options.labels.guest || 'Guest') + " Country",
							"width": "col-md-3",
							"values": [],
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
							"hhkField": "Checkin Date",
							"width": "col-md-6"
						},
						{
							"type": "date",
							"required": true,
							"label": "Checkout Date",
							"placeholder": "Checkout Date",
							"className": "form-control",
							"name": "checkoutdate",
							"hhkField": "Expected Checkout Date",
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
							"hhkField": (options.labels.hospital || 'Hospital'),
							"width": "col-md-3",
							"dataSource": "hospitals",
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
							"hhkField": (options.labels.patient || 'Patient') + " " + (options.labels.mrn || 'MRN'),
							"width": "col-md-3",
						},
						... (options.fieldOptions.doctor ?
							[{
								"type": "text",
								"label": "Doctor",
								"placeholder": "Doctor",
								"className": "form-control",
								"name": "hospital.doctor",
								"hhkField": (options.labels.patient || 'Patient') + " Doctor",
								"width": "col-md-3",
							}] : []),
						{
							"type": "date",
							"label": (options.labels.treatmentStart || 'Treatment Start'),
							"placeholder": (options.labels.treatmentStart || 'Treatment Start'),
							"className": "form-control",
							"name": "hospital.treatmentStart",
							"hhkField": (options.labels.patient || 'Patient') + " " + (options.labels.treatmentStart || 'Treatment Start'),
							"width": "col-md-3",
						},
						{
							"type": "date",
							"label": (options.labels.treatmentEnd || 'Treatment End'),
							"placeholder": (options.labels.treatmentEnd || 'Treatment End'),
							"className": "form-control",
							"name": "hospital.treatmentEnd",
							"hhkField": (options.labels.patient || 'Patient') + " " + (options.labels.treatmentEnd || 'Treatment End'),
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
								"hhkField": "Referral Agent First Name",
								"width": "col-md-3",
							},
							{
								"type": "text",
								"label": "Last Name",
								"placeholder": "Last Name",
								"className": "form-control",
								"name": "hospital.referralAgent.lastName",
								"hhkField": "Referral Agent Last Name",
								"width": "col-md-3",
							},
							{
								"type": "text",
								"subtype": "tel",
								"label": "Phone",
								"placeholder": "Phone",
								"className": "form-control hhk-phoneInput",
								"name": "hospital.referralAgent.phone",
								"hhkField": "Referral Agent Phone",
								"width": "col-md-3",
							},
							{
								"type": "text",
								"subtype": "email",
								"label": "Email",
								"placeholder": "Email",
								"className": "form-control",
								"name": "hospital.referralAgent.email",
								"hhkField": "Referral Agent Email",
								"width": "col-md-3",
							},
						]

					}] : []),
			],
			disableFields: [
				'autocomplete',
				'button',
				'file',
				'hidden',
				'number',
				'select'
			],
			actionButtons: [
				{
					id: 'editSettingsAction',
					className: 'ui-button ui-corner-left',
					label: 'Form Settings',
					type: 'button',
					events: {
						click: function () {
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
						click: function () {
							//var formData = btoa(JSON.stringify(settings.formBuilder.actions.getData()));
							var formData = buffer.Buffer.from(JSON.stringify(settings.formBuilder.actions.getData())).toString("base64");
							var f = $("<form target='formPreviewIframe' method='POST' style='display:none;'></form>").attr({
								action: settings.previewURL
							}).appendTo(document.body);

							f.append('<input type="hidden" name="cmd" value="preview">');
							f.append('<textarea name="formData" style="display:none">' + formData + '</textarea>');
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
						click: function () {
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
							'col-md-12': '12 of 12 Columns (100%)',
							'col-md-11': '11 of 12 Columns (91%)',
							'col-md-10': '10 of 12 Columns (83%)',
							'col-md-9': '9 of 12 Columns (75%)',
							'col-md-8': '8 of 12 Columns (66%)',
							'col-md-7': '7 of 12 Columns (58%)',
							'col-md-6': '6 of 12 Columns (50%)',
							'col-md-5': '5 of 12 Columns (41%)',
							'col-md-4': '4 of 12 Columns (33%)',
							'col-md-3': '3 of 12 Columns (25%)',
							'col-md-2': '2 of 12 Columns (16%)',
							'col-md-1': '1 of 12 Column (8%)',
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
				},
				button: {
					width: {
						label: 'Field Width',
						multiple: false,
						options: {
							'col-md-12': '12 of 12 Columns (100%)',
							'col-md-11': '11 of 12 Columns (91%)',
							'col-md-10': '10 of 12 Columns (83%)',
							'col-md-9': '9 of 12 Columns (75%)',
							'col-md-8': '8 of 12 Columns (66%)',
							'col-md-7': '7 of 12 Columns (58%)',
							'col-md-6': '6 of 12 Columns (50%)',
							'col-md-5': '5 of 12 Columns (41%)',
							'col-md-4': '4 of 12 Columns (33%)',
							'col-md-3': '3 of 12 Columns (25%)',
							'col-md-2': '2 of 12 Columns (16%)',
							'col-md-1': '1 of 12 Column (8%)',
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
							'col-md-12': '12 of 12 Columns (100%)',
							'col-md-11': '11 of 12 Columns (91%)',
							'col-md-10': '10 of 12 Columns (83%)',
							'col-md-9': '9 of 12 Columns (75%)',
							'col-md-8': '8 of 12 Columns (66%)',
							'col-md-7': '7 of 12 Columns (58%)',
							'col-md-6': '6 of 12 Columns (50%)',
							'col-md-5': '5 of 12 Columns (41%)',
							'col-md-4': '4 of 12 Columns (33%)',
							'col-md-3': '3 of 12 Columns (25%)',
							'col-md-2': '2 of 12 Columns (16%)',
							'col-md-1': '1 of 12 Column (8%)',
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
					hhkField: {
						label: "HHK Field",
						className: 'hhk-formbuilder-readonly',
						value: "",
						readonly: "readonly",
						description: "Data entered into this field correspond to this field in HHK"
					}
				},
				number: {
					width: {
						label: 'Field Width',
						multiple: false,
						options: {
							'col-md-12': '12 of 12 Columns (100%)',
							'col-md-11': '11 of 12 Columns (91%)',
							'col-md-10': '10 of 12 Columns (83%)',
							'col-md-9': '9 of 12 Columns (75%)',
							'col-md-8': '8 of 12 Columns (66%)',
							'col-md-7': '7 of 12 Columns (58%)',
							'col-md-6': '6 of 12 Columns (50%)',
							'col-md-5': '5 of 12 Columns (41%)',
							'col-md-4': '4 of 12 Columns (33%)',
							'col-md-3': '3 of 12 Columns (25%)',
							'col-md-2': '2 of 12 Columns (16%)',
							'col-md-1': '1 of 12 Column (8%)',
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
					hhkField: {
						label: "HHK Field",
						className: 'hhk-formbuilder-readonly',
						value: "",
						readonly: "readonly",
						description: "Data entered into this field correspond to this field in HHK"
					}
				},
				select: {
					width: {
						label: 'Field Width',
						multiple: false,
						options: {
							'col-md-12': '12 of 12 Columns (100%)',
							'col-md-11': '11 of 12 Columns (91%)',
							'col-md-10': '10 of 12 Columns (83%)',
							'col-md-9': '9 of 12 Columns (75%)',
							'col-md-8': '8 of 12 Columns (66%)',
							'col-md-7': '7 of 12 Columns (58%)',
							'col-md-6': '6 of 12 Columns (50%)',
							'col-md-5': '5 of 12 Columns (41%)',
							'col-md-4': '4 of 12 Columns (33%)',
							'col-md-3': '3 of 12 Columns (25%)',
							'col-md-2': '2 of 12 Columns (16%)',
							'col-md-1': '1 of 12 Column (8%)',
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
					},
					hhkField: {
						label: "HHK Field",
						className: 'hhk-formbuilder-readonly',
						value: "",
						readonly: "readonly",
						description: "Data entered into this field correspond to this field in HHK"
					}
				},
				date: {
					width: {
						label: 'Field Width',
						multiple: false,
						options: {
							'col-md-12': '12 of 12 Columns (100%)',
							'col-md-11': '11 of 12 Columns (91%)',
							'col-md-10': '10 of 12 Columns (83%)',
							'col-md-9': '9 of 12 Columns (75%)',
							'col-md-8': '8 of 12 Columns (66%)',
							'col-md-7': '7 of 12 Columns (58%)',
							'col-md-6': '6 of 12 Columns (50%)',
							'col-md-5': '5 of 12 Columns (41%)',
							'col-md-4': '4 of 12 Columns (33%)',
							'col-md-3': '3 of 12 Columns (25%)',
							'col-md-2': '2 of 12 Columns (16%)',
							'col-md-1': '1 of 12 Column (8%)',
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
							'': '',
							'lessThanToday': "Date must be in the past",
							'greaterThanToday': "Date must be in the future"
						}
					},
					hhkField: {
						label: "HHK Field",
						className: 'hhk-formbuilder-readonly',
						value: "",
						readonly: "readonly",
						description: "Data entered into this field correspond to this field in HHK"
					}
				},
				paragraph: {
					width: {
						label: 'Field Width',
						multiple: false,
						options: {
							'col-md-12': '12 of 12 Columns (100%)',
							'col-md-11': '11 of 12 Columns (91%)',
							'col-md-10': '10 of 12 Columns (83%)',
							'col-md-9': '9 of 12 Columns (75%)',
							'col-md-8': '8 of 12 Columns (66%)',
							'col-md-7': '7 of 12 Columns (58%)',
							'col-md-6': '6 of 12 Columns (50%)',
							'col-md-5': '5 of 12 Columns (41%)',
							'col-md-4': '4 of 12 Columns (33%)',
							'col-md-3': '3 of 12 Columns (25%)',
							'col-md-2': '2 of 12 Columns (16%)',
							'col-md-1': '1 of 12 Column (8%)',
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
							'col-md-12': '12 of 12 Columns (100%)',
							'col-md-11': '11 of 12 Columns (91%)',
							'col-md-10': '10 of 12 Columns (83%)',
							'col-md-9': '9 of 12 Columns (75%)',
							'col-md-8': '8 of 12 Columns (66%)',
							'col-md-7': '7 of 12 Columns (58%)',
							'col-md-6': '6 of 12 Columns (50%)',
							'col-md-5': '5 of 12 Columns (41%)',
							'col-md-4': '4 of 12 Columns (33%)',
							'col-md-3': '3 of 12 Columns (25%)',
							'col-md-2': '2 of 12 Columns (16%)',
							'col-md-1': '1 of 12 Column (8%)',
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
					hhkField: {
						label: "HHK Field",
						className: 'hhk-formbuilder-readonly',
						value: "",
						readonly: "readonly",
						description: "Data entered into this field correspond to this field in HHK"
					}
				},
				"radio-group": {
					width: {
						label: 'Field Width',
						multiple: false,
						options: {
							'col-md-12': '12 of 12 Columns (100%)',
							'col-md-11': '11 of 12 Columns (91%)',
							'col-md-10': '10 of 12 Columns (83%)',
							'col-md-9': '9 of 12 Columns (75%)',
							'col-md-8': '8 of 12 Columns (66%)',
							'col-md-7': '7 of 12 Columns (58%)',
							'col-md-6': '6 of 12 Columns (50%)',
							'col-md-5': '5 of 12 Columns (41%)',
							'col-md-4': '4 of 12 Columns (33%)',
							'col-md-3': '3 of 12 Columns (25%)',
							'col-md-2': '2 of 12 Columns (16%)',
							'col-md-1': '1 of 12 Column (8%)',
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
							'col-md-12': '12 of 12 Columns (100%)',
							'col-md-11': '11 of 12 Columns (91%)',
							'col-md-10': '10 of 12 Columns (83%)',
							'col-md-9': '9 of 12 Columns (75%)',
							'col-md-8': '8 of 12 Columns (66%)',
							'col-md-7': '7 of 12 Columns (58%)',
							'col-md-6': '6 of 12 Columns (50%)',
							'col-md-5': '5 of 12 Columns (41%)',
							'col-md-4': '4 of 12 Columns (33%)',
							'col-md-3': '3 of 12 Columns (25%)',
							'col-md-2': '2 of 12 Columns (16%)',
							'col-md-1': '1 of 12 Column (8%)',
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
				default: function (field, label, help, data) {
					return $('<div/>').addClass(data.width).append(field);
				},
				noLabel: function (field, label, help, data) {
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
			},
			defaultFormSettings: {
				successTitle: "Referral Form Submitted",
				successContent:
					`Thank you for submitting your referral form. Someone will be in touch shortly.

Thank You,
House Staff`,
				initialGuests: 1,
				maxGuests: 4
			}
		};

		var settings = $.extend(true, {}, defaults, options);

		var $wrapper = $(this);
console.log(options.insuranceInputSets);
console.log(options.insTypes);
		createMarkup($wrapper, settings);

		$wrapper.find("button").button();

		var settingsDialog = $wrapper.find('#settingsDialog').dialog({
			autoOpen: false,
			height: 800,
			width: getDialogWidth(900),
			modal: true,
			buttons: {
				"Revert Changes": function () {
					settingsDialog.find('textarea, input').each(
						function (i, element) {
							if ($(this).prop('type') == 'checkbox') {
								$(this).prop('checked', $(this).data('oldval'));
							} else {
								$(this).val($(this).data('oldVal'));
							}
						}
					);
					settingsDialog.dialog("close");
				},
				Continue: function () {
					settingsDialog.dialog("close");
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
				Close: function () {
					formPreviewDialog.dialog("close");
				}
			}
		});

		var embedInfoDialog = $wrapper.find("#embedInfoDialog").dialog({
			autoOpen: false,
			height: 700,
			width: getDialogWidth(900),
			modal: true,
			buttons: {
				Close: function () {
					embedInfoDialog.dialog("close");
				}
			},
			create: function (event, ui) {
				//$(event.target).find("#embedAccordion").accordion();
			}
		});

		actions($wrapper, settings, settingsDialog, formPreviewDialog, embedInfoDialog);

		return this;
	}

	function setUpInsuranceFields(options) {

		let insuranceInputSets = [];

		$.each(options.insTypes, function (index, insType) {

			insuranceInputSets.push({
					label: insType.Title + ' Insurance',
					name: 'insurance'+insType.idInsurance_type,
					showHeader: true,
					fields: [
						{
							"type": "select",
							"label": "Insurance Company",
							"placeholder": "Insurance Company",
							"className": "form-control",
							"name": "insurance.i" + insType.idInsurance_type + ".insuranceId",
							"hhkField": "Insurance",
							"width": "col-md-3",
							"dataSource": "insurance"+insType.idInsurance_type,
							"multiple": false,
							"values": [
								{
									"label": "Insurance Company",
									"value": "",
									"selected": true
								}
							]
						},
						{
							"type": "text",
							"label": "Other Insurance Not Listed",
							"placeholder": "Other Insurance Not Listed",
							"className": "form-control",
							"name": "insurance.i" + insType.idInsurance_type + ".other",
							"width": "col-md-3"
						},
						{
							"type": "text",
							"label": "Group Number",
							"placeholder": "Group Number",
							"className": "form-control",
							"name": "insurance.i" + insType.idInsurance_type + ".groupNum",
							"hhkField": "Group Number",
							"width": "col-md-3"
						},
						{
							"type": "text",
							"label": "Member Number",
							"placeholder": "Member Number",
							"className": "form-control",
							"name": "insurance.i" + insType.idInsurance_type + ".memNum",
							"hhkField": "Member Number",
							"width": "col-md-2"
						}
					],
				});
		});

		options.insuranceInputSets = insuranceInputSets;

	}

	function setUpDemogFields(options) {
		var patientDemogFields = [];
		var guestDemogFields = [];

		$.each(options.demogs, function (index, demog) {

			patientDemogFields.push({
				"type": "select",
				"label": (options.labels.patient || 'Patient') + " " + (demog.Description || ''),
				"placeholder": (options.labels.patient || 'Patient') + " " + (demog.Description || ''),
				"className": "form-select",
				"name": "patient.demographics." + index,
				"hhkField": (options.labels.patient || 'Patient') + " " + (demog.Description || ''),
				"width": "col-md-3",
				"dataSource": index,
			});

			guestDemogFields.push({
				"type": "select",
				"label": (options.labels.guest || 'Guest') + " " + (demog.Description || ''),
				"placeholder": (options.labels.guest || 'Guest') + " " + (demog.Description || ''),
				"className": "form-select",
				"name": "guests.g0.demographics." + index,
				"hhkField": (options.labels.guest || 'Guest') + " " + (demog.Description || ''),
				"width": "col-md-3",
				"group": "guest",
				"dataSource": index,
			});
		});

		options.patientDemogFields = patientDemogFields;
		options.guestDemogFields = guestDemogFields;

	}

	function setUpDataSources(options) {
		var dataSources = {
			'': '',
			'namePrefix': (options.labels.namePrefix || 'Prefix'),
			'nameSuffix': 'Name Suffix',
			'patientRelation': (options.labels.patient || 'Patient') + ' Relationship',
			'vehicleStates': 'Vehicle States',
			'hospitals': 'Hospital',
			'diagnosis': 'Diagnosis',
			'location': 'Unit'
		}

		$.each(options.insTypes, function(index, insType){
			dataSources['insurance'+insType.idInsurance_type] = insType.Title + ' Insurance';
		});

		$.each(options.demogs, function (index, demog) {
			dataSources[index] = demog.Description;
		});
		options.dataSources = dataSources;
	}

	function createMarkup($wrapper, settings) {
		$wrapper.html(
			`
			<div>
				<form autocomplete="off">
				<label for="selectform">Select a form: </label>
				<select id="selectform" name="selectform" style="margin: 0 0.5em; padding:0.4em 0.5em;">
					<option value=""></option>
				</select>
				<button id="newReferral">New Referral Form</button>
				<button id="duplicateReferral">Duplicate this Referral Form</button>
				<button id="deleteReferral">Delete this Referral Form</button>
				<span class="formTitleContainer">
					<label for="formTitle">Form Title: </label>
					<input typle="text" id="formTitle" placeholder="Form Title" style="padding:0.4em 0.5em;">
				</span>
				<button id="formiframebtn" style="margin-left: 0.5em; display: none;">Embed Instructions</button>
				</form>
			</div>
			<div id="formBuilderContent" style="margin-top: 1em;"></div>
			<div id="settingsDialog" title="Form Settings" style="font-size: 0.9em;">
			
				<div id="formSettingsTabs">
    				<ul>
        				<li><a href="#tabs-1">Success Message</a></li>
        				<li><a href="#tabs-2">Notifications</a></li>
        				<li><a href="#tabs-3">Form Styles</a></li>
        				<li><a href="#tabs-5">Guests</a></li>
        				<li><a href="#tabs-4">Miscellaneous</a></li>
    				</ul>
    
				    <div id="tabs-1">
				        <div class="row">
							<div class="col-12">
								<p style="margin-bottom: 1em;">Add a custom message displayed on a successful form submission</p>
								<label for="formSuccessTitle" style="display:block">Sucess Title</label>
								<input type="text" id="formSuccessTitle" name="formSuccessTitle" placeholder="Success Title" class="p-2 mb-2" style="width: 100%">
								<label for="formSuccessContent" style="display:block">Success Content</label>
								<textarea id="formSuccessContent" name="formSuccessContent" placeholder="Success Content" rows="5" class="p-2 mb-2" style="width: 100%"></textarea>
							</div>
						</div>
				    </div>
				    
				    <div id="tabs-2">
						<div class="ui-widget mb-3">
							<p class="ui-widget-header ui-corner-top p-2">Staff Notification</p>
							<div class="ui-widget ui-widget-content ui-corner-bottom p-2">
								<p style="margin-bottom: 1em;">Any addresses listed in "referralFormEmail" in Site Configuration will be notified by email when a form is submitted</p>
								<label for="notifySubject" style="display:block">Email Subject</label>
								<input type="text" id="notifySubject" name="notifySubject" placeholder="Email Subject" class="p-2 mb-2" style="width: 100%">
							</div>
						</div>
						<div class="ui-widget">
							<p class="ui-widget-header ui-corner-top p-2">Confirmation Notification</p>
							<div class="ui-widget ui-widget-content ui-corner-bottom p-2">
								<div class="mb-3">
									<input type="checkbox" name="notifyMe" id="notifyMe">
									<label for="notifyMe">Send confirmation to email address in "Confirmation Email" field</label>
								</div>
								<label for="notifyMeSubject" style="display:block">Email Subject</label>
								<input type="text" id="notifyMeSubject" name="notifyMeSubject" placeholder="Email Subject" class="p-2 mb-2" style="width: 100%">
								<label for="notifyMeContent" style="display:block">Email Body</label>
								<textarea id="notifyMeContent" name="notifyMeContent" placeholder="Email Body" class="hhk-autosize p-2 mb-2" rows="5" style="width: 100%; resize: none;"></textarea>
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
				        		<input type="number" name="initialGuests" min="1" max="20" style="width: 5em;" id="initialGuests">
				    		</div>
				    	</div>
				    	<div class="row mb-3">
				    		<div class="col-12">
				    			<label for="maxGuests">Max Guests:</label>
				        		<input type="number" name="maxGuests" min="1" max="20" style="width: 5em;" id="maxGuests">
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
			url: settings.serviceURL,
			type: "GET",
			data: {
				"cmd": "getformtemplates"
			},
			dataType: "json",
			success: function (data, textStatus, jqXHR) {
				if (data.forms) {
					for (i in data.forms) {
						$wrapper.find('#selectform').append('<option value="' + data.forms[i].idDocument + '">' + data.forms[i].Title + '</option>');
					}
				}
			}
		});

	}

	function actions($wrapper, settings, settingsDialog, formPreviewDialog, embedInfoDialog) {

		$wrapper.on('click', '#newReferral', function (e) {
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
				onAddFieldAfter: onAddField,
				//onCloseFieldEdit:onCloseFieldEdit,
				stickyControls: settings.stickyControls,
				"i18n": {
					"location": "../js/formBuilder"
				}
			});
			$wrapper.find('.formTitleContainer').show();
			$wrapper.find('#newReferral').hide();
			settingsDialog.find('input#formSuccessTitle').val(settings.defaultFormSettings.successTitle).data('oldVal', "");
			settingsDialog.find('textarea#formSuccessContent').val(settings.defaultFormSettings.successContent).data('oldVal', "");
			settingsDialog.find('input[name=initialGuests]').val(settings.defaultFormSettings.initialGuests).data('oldVal', "");
			settingsDialog.find('input[name=maxGuests]').val(settings.defaultFormSettings.maxGuests).data('oldVal', "");
		});

		$wrapper.on('click', '#duplicateReferral', function (e) {
			e.preventDefault();
			onSave("new");
		});

		$wrapper.find('#duplicateReferral, #deleteReferral, .formTitleContainer').hide();

		$wrapper.on('change', '#selectform', function () {
			var idDocument = $(this).val();
			if (idDocument) {
				$.ajax({
					url: settings.serviceURL,
					type: "GET",
					data: {
						"cmd": "loadformtemplate",
						"idDocument": idDocument
					},
					dataType: "json",
					success: function (data, textStatus, jqXHR) {
						if (data.status == "success") {
							try {
								JSON.parse(data.formTemplate);
								formData = data.formTemplate;
							} catch (e) {
								formData = buffer.Buffer.from(data.formTemplate, 'base64').toString('utf-8');
							}
							settings.formBuilder = $wrapper.find('#formBuilderContent').empty().formBuilder({
								formData: formData,
								inputSets: settings.inputSets,
								fields: settings.fields,
								disableFields: settings.disableFields,
								disabledAttrs: settings.disabledAttrs,
								disabledActionButtons: settings.disabledActionButtons,
								actionButtons: settings.actionButtons,
								typeUserAttrs: settings.typeUserAttrs,
								layoutTemplates: settings.layoutTemplates,
								onSave: onSave,
								onAddFieldAfter: onAddField,
								//onCloseFieldEdit:onCloseFieldEdit,
								stickyControls: settings.stickyControls,
								"i18n": {
									"location": "../js/formBuilder"
								}
							});

							$wrapper.find('#formiframebtn').data('url', data.formURL).show();
							$wrapper.find('#duplicateReferral, #deleteReferral, .formTitleContainer').show();
							$wrapper.find('#newReferral').hide();
							settingsDialog.find('input#formSuccessTitle').val(data.formSettings.successTitle).data('oldVal', data.formSettings.successTitle);
							settingsDialog.find('textarea#formSuccessContent').val(data.formSettings.successContent).data('oldVal', data.formSettings.successContent);
							settingsDialog.find('textarea#formStyle').val(data.formSettings.formStyle).data('oldVal', data.formSettings.formStyle);
							settingsDialog.find('input#enableRecaptcha').prop('checked', data.formSettings.enableRecaptcha).data('oldval', data.formSettings.enableRecaptcha);
							settingsDialog.find('input#enableReservation').prop('checked', data.formSettings.enableReservation).data('oldval', data.formSettings.enableReservation);
							settingsDialog.find('input#notifySubject').val(data.formSettings.notifySubject).data('oldVal', data.formSettings.notifySubject);
							settingsDialog.find('input#notifyMeSubject').val(data.formSettings.notifyMeSubject).data('oldVal', data.formSettings.notifyMeSubject);
							settingsDialog.find('textarea#notifyMeContent').val(data.formSettings.notifyMeContent).data('oldVal', data.formSettings.notifyMeContent);
							settingsDialog.find('input#notifyMe').prop('checked', data.formSettings.notifyMe).data('oldval', data.formSettings.notifyMe);
							settingsDialog.find('input[name=initialGuests]').val(data.formSettings.initialGuests).data('oldVal', data.formSettings.initialGuests);
							settingsDialog.find('input[name=maxGuests]').val(data.formSettings.maxGuests).data('oldVal', data.formSettings.maxGuests);
							settingsDialog.find('textarea#fontImport').val(data.formSettings.fontImport).data('oldVal', data.formSettings.fontImport);
							$wrapper.find('#formTitle').val(data.formTitle);
						}
					}
				});
			} else {
				$wrapper.find('#formBuilderContent').empty();
				$wrapper.find('#formiframebtn').data('code', '').hide();
				$wrapper.find('#duplicateReferral, #deleteReferral, .formTitleContainer').hide();
				$wrapper.find('#newReferral').show();
				$wrapper.find('#formTitle').val("");
				settingsDialog.find('textarea').val('').data('oldstyles', '');
				settingsDialog.find('input#enableRecaptcha').prop('checked', false);
			}

		});

		$wrapper.on('click', '#deleteReferral', function (e) {
			e.preventDefault();
			var idDocument = $wrapper.find('#selectform').val();

			if (idDocument && confirm("Are you sure you want to delete this form? This action cannot be undone.")) {
				$.ajax({
					url: settings.serviceURL,
					type: "GET",
					data: {
						"cmd": "deleteformtemplate",
						"idDocument": idDocument
					},
					dataType: "json",
					success: function (data, textStatus, jqXHR) {
						if (data.status == "success") {
							$wrapper.find("#selectform option[value='" + idDocument + "']").remove();
							$wrapper.find("#selectform").val("").change();
							flagAlertMessage(data.msg, false);
						} else {
							flagAlertMessage(data.msg, true);
						}
					}
				});
			}

		});

		$wrapper.on('click', '#formiframebtn', function (e) {
			e.preventDefault();
			let url = $(this).data("url");
			embedInfoDialog.find("#embedURL").text(url);
			embedInfoDialog.find("#embedCodeSnippet").text('<iframe src="' + url + '" width="100%" height="1000"></iframe>');
			embedInfoDialog.dialog("open");
		});

		embedInfoDialog.on('change', 'input#changeHeight', function (e) {
			let val = $(this).val();
			let embedCode = embedInfoDialog.find("#embedCodeSnippet").text();
			console.log(val);
			console.log(embedCode);
			embedCode = embedCode.replace(/(height=")([0-9]*)(")/gm, '$1' + val + '$3');
			console.log(embedCode);
			embedInfoDialog.find("#embedCodeSnippet").text(embedCode);
		});

		$wrapper.on('blur', '[contenteditable]', function () {
			var val = $(this).html().replaceAll('"', "'");
			$(this).html(val);
			//			//console.log(val);
			//			//console.log($(this).html());
		});

		settingsDialog.on('blur', 'input[name=initialGuests], input[name=maxGuests]', function () {
			var min = parseInt($(this).attr('min'));
			var max = parseInt($(this).attr('max'));
			var val = parseInt($(this).val());

			if (val < min) {
				$(this).val(min);
			}

			if (val > max) {
				$(this).val(max);
			}

			//check if initial is less than max
			var initial = parseInt($("input[name=initialGuests]").val());
			var max = parseInt($("input[name=maxGuests]").val());
			if (initial > max) {
				//console.log("initial>max true");
				settingsDialog.find("#guestErrorMsg").text("Initial guests cannot be greater than max guests").show();
			} else {
				//console.log("initial>max false");
				settingsDialog.find("#guestErrorMsg").text("").hide();
			}
		});

		var onAddField = function (fieldId, field) {
			var formData = (settings.formBuilder.actions.getData instanceof Function) ? settings.formBuilder.actions.getData() : [];
			var filtered = formData.filter(x => (x.name === field.name)); //check for duplicate field

			if (filtered.length > 1 && field.name && field.label) {
				var msg = "<strong>" + field.label + " (" + field.name + ")</strong> already exists on the form, duplicate field removed";
				flagAlertMessage(msg, true);
				settings.formBuilder.actions.removeField(fieldId);
			}
		};

		var onCloseFieldEdit = function (editPanel) {
			var group = $(editPanel).find('select[name=group]').val();
			var fieldType = $(editPanel).parent('li').prop('type');

			if (group === 'guest' && (fieldType === 'checkbox-group' || fieldType === 'radio-group')) {
				flagAlertMessage('The "Guest" group does not support checkbox or radio buttons, the field has been removed from the "Guest" group', true);
				$(editPanel).find('select[name=group]').val("");
			}
		};

		var onSave = function (data) {
			settings.formBuilder.actions.closeAllFieldEdit();
			if (data == "new") { //duplicate form
				var idDocument = 0
				var title = $wrapper.find('#formTitle').val() + " (copy)";
			} else {
				var idDocument = $wrapper.find('#selectform').val();
				var title = $wrapper.find('#formTitle').val();
			}
			var style = settingsDialog.find('textarea#formStyle').val();
			var successTitle = settingsDialog.find('input#formSuccessTitle').val();
			var successContent = settingsDialog.find('textarea#formSuccessContent').val();
			var enableRecaptcha = settingsDialog.find('input#enableRecaptcha').prop('checked');
			var enableReservation = settingsDialog.find('input#enableReservation').prop('checked');
			var notifyMe = settingsDialog.find('input#notifyMe').prop('checked');
			var notifySubject = settingsDialog.find('input#notifySubject').val();
			var notifyMeSubject = settingsDialog.find('input#notifyMeSubject').val();
			var notifyMeContent = settingsDialog.find('textarea#notifyMeContent').val();
			var initialGuests = settingsDialog.find('input[name=initialGuests]').val();
			var maxGuests = settingsDialog.find('input[name=maxGuests]').val();
			var fontImport = settingsDialog.find('textarea#fontImport').val();
			var formData = settings.formBuilder.actions.getData();

			if (typeof idDocument !== 'undefined', typeof title !== 'undefined', typeof style !== 'undefined', typeof formData !== 'undefined', typeof successTitle !== 'undefined', typeof successContent !== 'undefined') {
				//check required fields
				var missingFields = [];
				var emailErrorMsg = '';

				//convert html entities
				formData.forEach(function (field, i, formData) {
					if (field.label) {
						field.label = he.encode(field.label, { 'allowUnsafeSymbols': true });
						formData[i] = field;
					}
				});

				if (notifyMe) {
					var filtered = formData.filter(x => (x.name === 'notifyMeEmail'));
					if (filtered.length == 0) {
						emailErrorMsg += "<br>The Confirmation Email field is required for email notifications";
					}
					if (notifySubject.length == 0) {
						emailErrorMsg += "<br>Email Subject cannot be blank";
					}
					if (notifyMeSubject.length == 0) {
						emailErrorMsg += "<br>Confirmation Email Subject cannot be blank";
					}
					if (notifyMeContent.length == 0) {
						emailErrorMsg += "<br>Confirmation Email Content cannot be blank";
					}
				}

				settings.requiredFields.forEach(function (field) {
					var filtered = formData.filter(x => (x.name === field && x.required === true) || x.name === 'submit');
					if (filtered.length == 0) {
						missingFields.push(field);
					}
				});

				//format font import
				matches = fontImport.match(/.*@import url\('https:\/\/fonts.googleapis.com\/css2(\?.*)\'\).*/);
				if (Array.isArray(matches) && matches[1] != null) {
					queryString = matches[1];
					urlparams = new URLSearchParams(queryString);
					fontImport = urlparams.getAll("family");
				} else {
					settingsDialog.find('textarea#fontImport').val('');
				}

				if (missingFields.length == 0 && title.length > 0 && emailErrorMsg.length == 0) {
					$.ajax({
						url: settings.serviceURL,
						type: "POST",
						data: {
							"cmd": "saveformtemplate",
							"idDocument": idDocument,
							"title": title,
							"doc": buffer.Buffer.from(JSON.stringify(formData)).toString("base64"),
							"style": style,
							"successTitle": successTitle,
							"successContent": successContent,
							"enableRecaptcha": enableRecaptcha,
							"enableReservation": enableReservation,
							"notifySubject": notifySubject,
							"notifyMe": notifyMe,
							"notifyMeSubject": notifyMeSubject,
							"notifyMeContent": notifyMeContent,
							"initialGuests": initialGuests,
							"maxGuests": maxGuests,
							"fontImport": fontImport,
						},
						dataType: "json",
						success: function (data, textStatus, jqXHR) {
							if (data.status == "success") {
								flagAlertMessage(data.msg, false);

								if (data.doc) {
									$wrapper.find('#selectform').append('<option value="' + data.doc.idDocument + '">' + data.doc.title + '</option>');
									idDocument = data.doc.idDocument;
								}

								$wrapper.find('#selectform').val(idDocument).change();
							} else if (data.status == "error" && data.errors) {
								var errors = "<ul>";
								for (i in data.errors) {
									if (data.errors[i].errors) {
										for (k in data.errors[i].errors) {
											errors += "<li>Styles: Line:" + data.errors[i].errors[k].line[0] + ":" + data.errors[i].errors[k].message[0] + "</li>";
										}
									} else {
										errors += "<li>" + data.errors[i] + "</li>";
									}
								}
								errors += "</ul>";

								flagAlertMessage("The following errors were found" + errors, true);
							} else if (data.status == "error") {
								flagAlertMessage(data.msg, true);
							}
						}
					});
				} else {
					var errorMsg = "<strong>Error: </strong>";
					if (title.length == 0) {
						errorMsg += "Form title is required<br>"
					}
					if (emailErrorMsg.length > 0) {
						errorMsg += emailErrorMsg;
					}
					if (missingFields.length > 0) {
						errorMsg += "The following fields must be included and set as required: " + missingFields.join(', ');
					}
					flagAlertMessage(errorMsg, true);
				}
			}

		}
	}
}(jQuery));