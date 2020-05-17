/**
 * 
 * @param {string}
 *            mess
 * @param {boolean}
 *            wasError
 * @param {jQuery}
 *            $txtCtrl
 * @returns {undefined}
 */
function flagAlertMessage(mess, wasError, $txtCtrl) {
	"use strict";
	// Types: alert, success, warning, error, info/information
	var type = 'info';

	if (!mess || mess == '') {
		return;
	}

	if (typeof wasError === 'boolean') {
		type = (wasError ? 'error' : 'success');
	} else if (typeof wasError === 'string') {
		type = wasError;
	}

	try {
		new Noty({
			type : type,
			text : mess
		}).show();
	} catch (err) {
		// do nothing for now.
	}

	// Show message in a given container.
	if ($txtCtrl === undefined || $txtCtrl === null) {
		return;
	}

	$txtCtrl.text(mess).show();
}
function dateRender(data, type, format) {
	// If display or filter data is requested, format the date
	if (type === 'display' || type === 'filter') {

		if (data === null || data === '') {
			return '';
		}

		data = data.trim();

		if (data === null || data === '') {
			return '';
		}

		if (!format || format === '') {
			format = 'MMM D, YYYY';
		}

		return moment(data).format(format);
	}

	// Otherwise the data type requested (`type`) is type detection or
	// sorting data, for which we want to use the integer, so just return
	// that, unaltered
	return data;
}

function isIE() {
	var ua = window.navigator.userAgent;
	return /MSIE|Trident/.test(ua);
}

function checkStrength(pwCtrl) {
	var strongRegex = new RegExp(
			"^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])(?=.{8,})");
	var rtn = true;
	if (strongRegex.test(pwCtrl.val())) {
		pwCtrl.removeClass("ui-state-error");
	} else {
		pwCtrl.addClass("ui-state-error");
		rtn = false;
	}
	return rtn;
}

$(document).ready(
				function() {
					"use strict";
					// Hover states on the nav bar left icons.
					$("ul.hhk-ui-icons li").hover(function() {
						$(this).addClass("ui-state-hover");
					}, function() {
						$(this).removeClass("ui-state-hover");
					});

					$('#contentDiv').css('margin-top',
							$('#global-nav').css('height'));

					if ($('#dchgPw').length > 0) {
						var chPwButtons = {
								"Save" : function() {

									var oldpw = $('#utxtOldPw'), pw1 = $('#utxtNewPw1'), pw2 = $('#utxtNewPw2'), oldpwMD5, newpwMD5, challVar = $("#challVar").val(), msg = $('#pwChangeErrMsg'), qmsg = $('#SecQuestionErrMsg'), success = false;
									$('div#dchgPw').find("input").prop("type",
											"password");
									$('div#dchgPw').find("button.showPw").text(
											"Show");
									var errors = false;
									msg.empty();

									if (oldpw.val() == "") {
										msg.text("Old password is required");
										return;
									}

									// if intent is to change password
									if (oldpw.val() != "") {

										if (pw1.val() !== pw2.val()) {
											msg.text("New passwords do not match");
											return;
										}

										if (oldpw.val() == pw1.val()) {
											pw1.addClass("ui-state-error");
											msg
													.text("The new password must be different from the old password");
											pw1.focus();
											pw2.val('');
											return;
										}

										if (checkStrength(pw1) === false) {
											pw1.addClass("ui-state-error");
											msg
													.text('Password must have at least 8 characters including at least one uppercase, one lower case letter, one number and one symbol.');
											pw1.focus();
											return;
										}

										pw1.removeClass("ui-state-error");

										// make MD5 hash of password and concatenate
										// challenge value
										// next calculate MD5 hash of combined
										// values
										//console.log("challVar: " + challVar);
										//oldpwMD5 = hex_md5(hex_md5(oldpw.val())
										//		+ challVar);
										//newpwMD5 = hex_md5(pw1.val());

										var oldpwval = oldpw.val();
										var newpwval = pw1.val();
										oldpw.val('');
										pw1.val('');
										pw2.val('');

										$.post("../house/ws_admin.php", {
											cmd : 'chgpw',
											old : oldpwval,
											newer : newpwval
										}, function(data) {
											if (data) {
												try {
													data = $.parseJSON(data);
												} catch (err) {
													alert("Parser error - "
															+ err.message);
													return;
												}
												if (data.error) {

													if (data.gotopage) {
														window.open(data.gotopage,
																'_self');
													}
													flagAlertMessage(data.error,
															'error');

												} else if (data.success) {

													flagAlertMessage(data.success, 'success');
													$("#dchgPw").dialog('close');

												} else if (data.warning) {
													$('#pwChangeErrMsg').text(
															data.warning);
												}
											}
										});
									}
									;
								}
							};
						
						$('.hhk-tooltip').tooltip({
							classes: {
								"ui-tooltip": "ui-corner-all"
							}
						});
						
/*						$('#version').css('cursor', 'pointer');
						$('#version').hover(function() {
							$(this).addClass("ui-state-hover");
						}, function() {
							$(this).removeClass("ui-state-hover");
						});

						$('#version').click(
*/
						$('#userSettingsBtn').button().click(
								function() {
									chPwButtons["Cancel"] = function() {
										$(this).dialog("close");
									};
									$(".PassExpDesc").hide();
									$('div#dchgPw').find('input').removeClass(
											"ui-state-error").val('');
									$('#pwChangeErrMsg').text('');

									$('div#dchgPw').find("#qrcode").empty();
									$('div#dchgPw').find("#otpForm").hide();
									$('div#dchgPw').find('button').button();
									$('#dchgPw').dialog("option", "title","User Settings");
									$('#dchgPw').dialog("option", "closeOnEscape",true);
									$('#dchgPw').dialog("option", "dialogClass",'');
									$('#dchgPw').dialog("option", "buttons",chPwButtons);
									$('#dchgPw').dialog('open');
									$('#txtOldPw').focus();
								});

						$('div#dchgPw').on('mousedown', '.showPw', function() {
							var input = $(this).closest("td").find("input");
							input.prop("type", "text");
						});
						
						$('div#dchgPw').on('mouseup', '.showPw', function() {
							var input = $(this).closest("td").find("input");
							input.prop("type", "password");
						});

						

						var chgPW = $("input#isPassExpired").val();
						if (chgPW) {
							var autoOpen = true;
							var closeOnEscape = false;
							var dialogClass = "no-close";
						} else {
							var autoOpen = false;
							var closeOnEscape = true;
							var dialogClass = '';
							chPwButtons["Cancel"] = function() {
								$(this).dialog("close");
							};
							$(".PassExpDesc").hide();
						}
						
						$('div#dchgPw').find('button').button();

						$('div#dchgPw').on('change', 'input', function() {
							$(this).removeClass("ui-state-error");
							$(".hhk-alert").hide();
							$('#pwChangeErrMsg').text('');
						});

						$('#dchgPw').dialog({
							autoOpen : autoOpen,
							width : '60em',
							autoResize : true,
							resizable : true,
							modal : true,
							dialogClass : dialogClass,
							closeOnEscape : closeOnEscape,
							title : "Welcome",
							buttons : chPwButtons
						});
						
						//two factor Auth
						
						$('div#dchgPw #TwoFactorHelp').accordion({
							active: false,
							collapsible:true,
							heightStyle: 'content',
						});
						
						$('div#dchgPw').on('click', 'button#genSecret', function(){
							$.post("../house/ws_admin.php", {
								cmd : 'gen2fa'
							}, function(data) {
								if (data) {
									try {
										data = $.parseJSON(data);
									} catch (err) {
										alert("Parser error - "
												+ err.message);
										return;
									}
									if (data.error) {
										flagAlertMessage(data.error,'error');
									} else if (data.success) {
										
										$('div#qrcode').html('<p><strong>Secret: </strong> <span id="OTPSecret">' + data.secret + '</span></p><img src="'+ data.url + '"></p>');
										$('div#otpForm').show();
										$('button#genSecret').text("Regenerate QR Code");
									}
								}
							});
						});
						
						$('div#dchgPw').on('click', 'button#getSecret', function(){
							$.post("../house/ws_admin.php", {
								cmd : 'get2fa'
							}, function(data) {
								if (data) {
									try {
										data = $.parseJSON(data);
									} catch (err) {
										alert("Parser error - "
												+ err.message);
										return;
									}
									if (data.error) {
										flagAlertMessage(data.error,'error');
									} else if (data.success) {
										
										$('div#qrcode').html('<img src="'+ data.url + '"></p>');
										$('div#otpForm').hide();
									}
								}
							});
						});
						
						$('div#dchgPw').on('click', 'button#submitSetupOTP', function(){
							var secret = $('div#dchgPw').find("#OTPSecret").text();
							var otp = $('#setupOTP').val();
							$.post("../house/ws_admin.php", {
								cmd : 'save2fa',
								secret: secret,
								OTP: otp
							}, function(data) {
								if (data) {
									try {
										data = $.parseJSON(data);
									} catch (err) {
										alert("Parser error - "
												+ err.message);
										return;
									}
									if (data.error) {
										flagAlertMessage(data.error,'error');
									} else if (data.success) {
										flagAlertMessage("Two Factor Authentication enabled successfully", 'success');
										$('div#dchgPw').dialog('close');
									}
								}
							});
						});
					}
				});

