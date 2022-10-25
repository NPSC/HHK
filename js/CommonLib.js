import Noty from "noty";
export default class CommonLib {

	static flagAlertMessage(mess, wasError, $txtCtrl) {
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
	
	static dateRender(data, type, format) {
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
	
	static isIE() {
		var ua = window.navigator.userAgent;
		return /MSIE|Trident/.test(ua);
	}
	
	static checkStrength(pwCtrl) {
		var strongRegex = new RegExp(
				"^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])(?!.*[<>])(?=.{8,})");
		var rtn = true;
		if (strongRegex.test(pwCtrl.val())) {
			pwCtrl.removeClass("ui-state-error");
		} else {
			pwCtrl.addClass("ui-state-error");
			rtn = false;
		}
		return rtn;
	}
	
	static openiframe(src, width, height, title, buttons) {
		var $dialog = $('<div id="iframeDialog" style="overflow:hidden"><div class="hhk-loading-spinner" style="width: 100%; height: 100%; margin-top: 100px; text-align: center"><img src="../images/ui-anim_basic_16x16.gif"><p>Loading...</p></div><iframe id="hhk-iframe" src="'
				+ src
				+ '" style="border: none; height: 95%; width: 100%"></iframe></div>');
		$("#contentDiv").append($dialog);
	
		$dialog.dialog({
			width : getDialogWidth(width),
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
	
	static logoutTimer(){
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
			width : getDialogWidth(400),
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
	
	/* set dialog width based on screen size */
	static getDialogWidth(defaultWidth){
		var winWidth = $(window).width();
		var dialogWidth = defaultWidth;
		
		if(typeof defaultWidth == "number" && winWidth < defaultWidth - 30){
			dialogWidth = winWidth - 30;
		}
		return dialogWidth;
	}
}