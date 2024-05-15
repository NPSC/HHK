$(document).ready(
	function() {
		"use strict";
		// Hover states on the nav bar left icons.
		$("ul.hhk-ui-icons li").hover(function() {
			$(this).addClass("ui-state-hover");
		}, function() {
			$(this).removeClass("ui-state-hover");
		});

		//hover on bootstrap nav dropdowns
		$('.navbar-nav .dropdown').hover(function(){
			$(this).find(".dropdown-toggle").dropdown('show');
		}, function(){
			$(this).find(".dropdown-toggle").dropdown('hide');
		});

		//$('#contentDiv').css('margin-top',
		//		$('#global-nav').css('height'));

		if ($('#dchgPw').length > 0) {
			var chPwButtons = {
				"Save" : function() {

					var oldpw = $('#utxtOldPw'), pw1 = $('#utxtNewPw1'), pw2 = $('#utxtNewPw2'), oldpwMD5, newpwMD5, challVar = $(
							"#challVar").val(), msg = $('#pwChangeErrMsg'), qmsg = $('#SecQuestionErrMsg'), success = false;
					$('div#chgPassword').find("input").prop("type",
							"password");
					$('div#chgPassword').find("button.showPw").text(
							"Show");
					var errors = false;
					msg.empty();

					if (oldpw.val() == "" && (pw1.val() != "" || pw2.val() != "")) {
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
					//$("#dchgPw").dialog('close');
				}
			}
						//two factor Auth
						$('div#dchgPw #mfaTabs').tabs();
						$('div#dchgPw button, div#dchgPw input[type=submit]').button();


						$('div#dchgPw #mfaEmail tbody tbody').addClass('hhk-flex');

						//delete saved evices
						$('div#dchgPw').on('click', 'button#clearDevices', function(){
							$.post("../house/ws_admin.php", {
								cmd : 'clear2faTokens'
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
										flagAlertMessage("Saved devices cleared successfully", "success");
										$('div#dchgPw #savedDevices').hide();
									}
								}
							});
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
										$('div#qrcode').html('<img src="'+ data.url + '">').show();
										$('#mfaAuthenticator .otpForm').show();
										$('#mfaAuthenticator #showqrhelp').hide();
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
										$('#mfaAuthenticator .otpForm').hide();
										$('#mfaAuthenticator #showqrhelp').show();
									}
								}
							});
						});

						$('div#dchgPw').on('click', 'button#genEmailSecret', function(){
							var $target = $(this);
							var method = 'email';
							var inputdata = $('#userSettingsEmail').serialize();
							inputdata += "&method=email&cmd=gen2fa";
							$.post("../house/ws_admin.php", inputdata, function(data) {
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
										$target.parents('.mfaContent').find('button').button();
										$target.parents('.mfaContent').find('.otpForm input[name=secret]').val(data.secret);
										$target.parents('.mfaContent').find('.otpForm').show();
										$target.hide();
									}
								}
							});
						});

						$('div#dchgPw').on('click', 'button.disableMFA', function(){
							var $target = $(this);

							$.post("../house/ws_admin.php", {
								cmd : 'disable2fa',
								method : $(this).data("method")
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
										flagAlertMessage("Two step verification method disabled.",'success');
										$target.parents('.mfaContent').html(data.mkup).find('button, input[type=submit]').button();
										$('div#dchgPw #mfaEmail tbody tbody').addClass('hhk-flex');
									}
								}
							});
						});


						//submit + verify OTP
						$('div#dchgPw').on('submit', '.otpForm', function(e){
							e.preventDefault();
							var $this = $(this);

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
										$(".otpForm").hide();
										$("#qrcode").empty().hide();
										if(data.backupCodes){
											$("p#backupCodes").html("<br>" + data.backupCodes.join("<br><br>"));
											$("div#backupCodeDiv").show();
										}
										flagAlertMessage("Two Step Verification enabled successfully", 'success');
										if($this.find("input[name=method]").val() == "email"){
											$("#dchgPw").dialog('close');
										}
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
						$('div#dchgPw #chgPassword').find('input').removeClass("ui-state-error").val('');
						$('#pwChangeErrMsg').text('');

						$('div#dchgPw').find('button, input[type=submit]').button();
						$('div#dchgPw').find("#qrcode").empty();
						$('div#dchgPw').find("#otpForm").hide();
						$('#dchgPw').dialog("option", "title", "User Settings");
						$('#dchgPw').dialog("option", "closeOnEscape", true);
						$('#dchgPw').dialog("option", "dialogClass", '');
						$('#dchgPw').dialog("option", "buttons", chPwButtons);
						$('#dchgPw').dialog('open');
						$('#txtOldPw').focus();
					});

			$(document).on('click', '.showPw', function(e) {
				var input = $(this).parent().find("input");
				if(input.prop("type") == "password"){
					input.prop("type", "text");
					$(this).text("Hide");
				}else{
					input.prop("type", "password");
					$(this).text("Show");
				}
			});

			var chgPW = $("input#showUserSettings").val();
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
				width : getDialogWidth(1000),
				autoResize : true,
				resizable : true,
				modal : true,
				title : "Welcome",
				buttons : chPwButtons
			});
		}

		$(document).on("input", "textarea.hhk-fluidheight", function () {
			this.style.height = 'auto';
			this.style.height = (this.scrollHeight + 3) + 'px';
		});

		//Logout after inactivity
		//logoutTimer();

		//autosize textarea based on content
		$(document).on("input", "textarea.hhk-autosize", function () {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight + 3) + 'px';
		});
		$(document).find("textarea.hhk-autosize").trigger("input");
});
