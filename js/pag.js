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

		return moment(data, 'YYYY-MM-DD HH:mm:ss Z').format(format);
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

function openiframe(src, width, height, title, buttons) {
	var $dialog = $('<div id="iframeDialog" style="overflow:hidden"><div class="hhk-loading-spinner" style="width: 100%; height: 100%; margin-top: 100px; text-align: center"><img src="../images/ui-anim_basic_16x16.gif"><p>Loading...</p></div><iframe id="hhk-iframe" src="'
			+ src
			+ '" style="border: none; height: 95%; width: 100%"></iframe></div>');
	$("#contentDiv").append($dialog);

	$dialog.dialog({
		width : width,
		height : height,
		modal : true,
		title : title,
		buttons : buttons,
		close : function(event, ui) {
			$dialog.dialog("destroy").remove();
		}
	});

	$dialog.find("#hhk-iframe").on("load", function() {
		$dialog.find(".hhk-loading-spinner").hide();
	});

}

function logoutTimer(){
	var timerID;
	var intervalID;
	
	function resetTimer(){
		$.ajax({
			url: '../admin/ws_session.php',
			dataType: 'json',
			success: function(data){
				clearTimeout(timerID);
				clearInterval(intervalID);
				if(data.ExpiresIn > 60){
					timerID = setTimeout(resetTimer, (data.ExpiresIn-60)*1000);
					$dialog.dialog('close');
				}else{
					$("#expiresIn").text(data.ExpiresIn);
					intervalID = setInterval(countdown, 1000);
					$dialog.dialog('open');
					timerID = setTimeout(function(){location.href = 'index.php?log=lo';}, data.ExpiresIn*1000);
				}
			}
		});
	}
	
	function countdown(){
		var expiresIn = $("#expiresIn").text();
		$("#expiresIn").text(expiresIn - 1);
	}
	
	$dialog = $('<div id="logoutTimer" style="display:none; text-align: center;"><h3>You will be logged out in</h3><h2><span id="expiresIn"></span> Seconds</h2></div>');
	$('#contentDiv').append($dialog);
	
	$dialog.dialog({
		width : 400,
		height : 225,
		modal : true,
		autoOpen: false,
		title : "Stay Logged In?",
		closeOnEscape: false,
		buttons : {
			"Stay Logged In" : function(){
				$.ajax({
					url: '../admin/ws_session.php?cmd=extend',
					dataType: 'json',
					success: function(data){
						clearTimeout(timerID);
						clearInterval(intervalID);
						if(data.ExpiresIn > 60){
							timerID = setTimeout(resetTimer, (data.ExpiresIn-60)*1000);
							$dialog.dialog('close');
						}
					}
				});
			}
		}
	});
	
	resetTimer();
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

					var oldpw = $('#utxtOldPw'), pw1 = $('#utxtNewPw1'), pw2 = $('#utxtNewPw2'), oldpwMD5, newpwMD5, challVar = $(
							"#challVar").val(), msg = $('#pwChangeErrMsg'), qmsg = $('#SecQuestionErrMsg'), success = false;
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
							msg.text("The new password must be different from the old password");
							pw1.focus();
							pw2.val('');
							return;
						}

						if (checkStrength(pw1) === false) {
							pw1.addClass("ui-state-error");
							msg.html('Password must have at least 8 characters including at least <br>one uppercase, one lower case letter, one number and one symbol.');
							pw1.focus();
							return;
						}

						pw1.removeClass("ui-state-error");

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
									flagAlertMessage(data.error, 'error');

								} else if (data.success) {

									flagAlertMessage(data.success,
											'success');
									$("#dchgPw").dialog('close');

								} else if (data.warning) {
									$('#pwChangeErrMsg').text(
											data.warning);
								}
							}
						});
					}
				}
			}		
						//two factor Auth
						$('div#dchgPw #mfaTabs').tabs();
						
						$('div#dchgPw #TwoFactorHelp').accordion({
							active: false,
							collapsible:true,
							heightStyle: 'content',
						});
						
						//generate new Authenticator secret and QR code
						$('div#dchgPw').on('click', '#mfaAuthenticator button#genTOTPSecret', function(){
							$.post("../house/ws_admin.php", {
								cmd : 'gen2fa',
								method : 'authenticator'
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
										$('#mfaAuthenticator input[name=secret]').val(data.secret);
										$('div#qrcode').html('<img src="'+ data.url + '">');
										$('#mfaAuthenticator .otpForm').show();
										$('button#genTOTPSecret').hide();
									}
								}
							});
						});
						
						//show existing Authenticator QR code
						$('div#dchgPw').on('click', '#mfaAuthenticator button#getTOTPSecret', function(){
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
										
										$('div#qrcode').html('<img src="'+ data.url + '">');
										$('#mfaAuthenticator div#otpForm').hide();
									}
								}
							});
						});
						
						$('div#dchgPw').on('click', 'button#enableEmail', function(){
							$.post("../house/ws_admin.php", {
								cmd : 'gen2fa',
								method : 'email'
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
										$('div#OTPSecret').text(data.secret).show();
										
										$('div#otpForm').show();
										$('button#genSecret').text("Regenerate QR Code");
									}
								}
							});
						});
						
						
						//submit + verify OTP
						$('div#dchgPw').on('submit', '.otpForm', function(e){
							e.preventDefaults();
							$.post("../house/ws_admin.php", $(this).serialize(), 
								function(data) {
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
										console.log($(this));
										$(".otpForm").hide();
										if(data.backupCodes){
											$("p#backupCodes").html(data.backupCodes.join("<br>"));
											$("div#backupCodeDiv").show();
										}
										flagAlertMessage("Two Step Verification enabled successfully", 'success');
									}
								}
							});
						});

			$('.hhk-tooltip').tooltip({
				classes : {
					"ui-tooltip" : "ui-corner-all"
				}
			});

			$('#userSettingsBtn').button().click(
					function() {
						chPwButtons["Cancel"] = function() {
							$(this).dialog("close");
						};
						$(".PassExpDesc").hide();
						$('div#dchgPw').find('input').removeClass("ui-state-error").val('');
						$('#pwChangeErrMsg').text('');

						$('div#dchgPw').find('button').button();
						$('div#dchgPw').find("#qrcode").empty();
						$('div#dchgPw').find("#otpForm").hide();
						$('#dchgPw').dialog("option", "title", "User Settings");
						$('#dchgPw').dialog("option", "closeOnEscape", true);
						$('#dchgPw').dialog("option", "dialogClass", '');
						$('#dchgPw').dialog("option", "buttons", chPwButtons);
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
			} else {
				var autoOpen = false;
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
				width : '1000',
				autoResize : true,
				resizable : true,
				modal : true,
				title : "Welcome",
				buttons : chPwButtons
			});
		}
		
		//Logout after inactivity
		//logoutTimer();
});
