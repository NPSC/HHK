/* 
 * The MIT License
 *
 * Copyright 2017 Eric Crane <ecrane at nonprofitsoftwarecorp.org>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

function sendHhkLogin() {
	var parms = {},
	$uname = $('#txtUname'),
	$psw = $('#txtPW'),
	$btn = $('#btnLogn'),
	$chall = $('#challenge');
	$xf = $('#xf');
	$otp = $('#txtOTP');
	$otpMethod = $('#otpMethod');
	$changeMethod = $("#changeMethod");
	$rememberMe = $("input[name=rememberMe]");
	
	$('.hhk-logerrmsg').hide();
	$('#valMsg').text('');
	if ($uname.val() === '') {
	    $('#errUname').text('Enter your Username').show();
	    return;
	}
	if ($psw.val() === '') {
	    $('#errPW').text('Enter your Password').show();
	    return;
	}
	if ($otp.val() === '' && $otp.data("2fa") == "true") {
	    $('#errOTP').text('Enter Two Factor Code').show();
	    return;
	}
	
	parms = {
	    //challenge: hex_md5(hex_md5($psw.val()) + $chall.val()),
	    txtUname: $uname.val(),
	    txtPass: $psw.val(),
	    xf: $xf.val(),
	    otp: $otp.val(),
	    otpMethod: $otpMethod.val(),
	    showMethodMkup: $changeMethod.data("showmkup"),
	    rememberMe: $rememberMe.prop('checked')
	};
	
	$.post('index.php', parms, function (data){
	    try {
	        data = $.parseJSON(data);
	    } catch (err) {
	        alert(data);
	        //$('#divLoginCtls').remove();
	        return;
	    }
	    if(data.OTPRequired){
	    	$("#loginTitle").text("Verify Your Identity");
	    	$('#userRow, #pwRow').hide();
	    	if(data.method != '' && !data.otpMethodMkup){
	    		$('#otpChoiceRow').addClass('d-none');
	    		$('#otpMsg').html(data.methodMsg);
	    		$('#otpRow, #rememberRow').removeClass("d-none");
	    		$('#txtOTP').data("2fa", "true").focus();
	    		$('#otpMethod').val(data.method);
	    		$('#loginBtnRow').removeClass('d-none');
	    		if(data.showMethodBtn){
	    			$('#changeMethod').parent().removeClass('d-none');
	    		}else{
	    			$('#changeMethod').parent().addClass('d-none');
	    		}
	    	}else if(data.otpMethodMkup){
	    		$('#otpChoices').html(data.otpMethodMkup);
	    		$('#otpChoiceRow').removeClass('d-none');
	    		$('#otpChoiceRow button').button();
	    		$('#otpRow, #rememberRow').addClass("d-none");
	    		$('#otpMethod').val('');
	    		$('#loginBtnRow').addClass('d-none');
	    	}
	    }
	    if (data.page && data.page !== '') {
	        window.location.assign(data.page);
	    }
	    if (data.mess) {
	    	$('#valMsg').text(data.mess).show();    }
	    if (data.chall && data.chall !== '') {
	        $chall.val(data.chall);
	    }
	    if (data.stop) {
	        $btn.css('disable', true);
	    }
	}).fail(function(data, textStatus, xhr){
		$('#valMsg').text("A server error occurred, please check your username/password and try again").show();
	});
}
$(document).ready(function () {

	//newsletter
	var iframe = $('<iframe frameborder="0" marginwidth="0" marginheight="0"></iframe>');
    var dialog = $('<div id="newsletterDialog"></div>').append(iframe).appendTo("body").dialog({
        autoOpen: false,
        modal: true,
        resizable: false,
        width: "auto",
        height: "auto",
        closeOnEscape: true,
        close: function () {
            iframe.attr("src", "");
        },
        classes:{
        	"ui-dialog-titlebar": "ui-corner-top",
        	"ui-dialog-content": "ui-corner-bottom"
        }
    });
    
    dialog.find(".ui-widget-header").removeClass("ui-corner-all").addClass("ui-corner-top");
    dialog.find(".ui-widget-content").addClass("ui-corner-bottom");
    
    $("#newsletteriframe").on("click", function (e) {
        e.preventDefault();
        var src = $(this).attr("href");
        var title = $(this).attr("data-title");
        var width = 500;
        var height = 600;
        iframe.attr({
            width: +width,
            height: +height,
            src: src
        });
        dialog.dialog("option", "title", title).dialog("open");
    });
    
    //welcome widget
    $.ajax({
		method: 'get',
		url: $("#welcomeWidget").data('url'),
		success: function(data){
			if(data.content && data.bgcolor && data.textcolor){
				$("#welcomeWidget .welcomeContent").html(data.content);
				$("#welcomeWidget").css({"background":data.bgcolor, 'color':data.textcolor}).removeClass('d-none');
				$("#hhk-loading-spinner").addClass('d-none');
			}else{
				$("#welcomeWidget .welcomeContent").html("<h3>Did you know?</h3><br><p>Questions can be emailed to support@nonprofitsoftwarecorp.org</p>");
				$("#welcomeWidget").css({"background":'#c4e7d4', 'color':'#000000'}).removeClass('d-none');
				$("#hhk-loading-spinner").addClass('d-none');
			}
		},
		error: function(){
			$("#welcomeWidget .welcomeContent").html("<h3>Did you know?</h3><br><p>Questions can be emailed to support@nonprofitsoftwarecorp.org</p>");
			$("#welcomeWidget").css({"background":'#c4e7d4', 'color':'#000000'}).removeClass('d-none');
			$("#hhk-loading-spinner").addClass('d-none');
		}
		
	});

	$('.hhk-tooltip').tooltip({
		classes : {
			"ui-tooltip" : "ui-corner-all loginErrorTip"
		},
		tooltipClass: "loginErrorTip"
	});
	
	$('.hhk-tooltip').on("mouseenter", function (e) {
    	e.stopImmediatePropagation();
	});

	$('.hhk-tooltip').on("click", function (e) {
    	$('.hhk-tooltip').tooltip("open");
	});


	$('.hhk-tooltip').on("mouseleave", function (e) {
    	e.stopImmediatePropagation();
	});


	$(document).mouseup(function (e) {
    	var container = $(".ui-tooltip");
    	if (! container.is(e.target) && 
        	container.has(e.target).length === 0)
    	{
        	$('.hhk-tooltip').tooltip("close");
    	}
	});

	$(document).on('submit', "#hhkLogin", function(e){
		e.preventDefault();
		console.log("submit triggered");
		sendHhkLogin();
	});
	
	$(document).on('click', "#changeMethod", function(e){
		e.preventDefault();
		$(this).data('showmkup', 'true');
		$('#txtOTP').data("2fa", "false").val('');
		sendHhkLogin();
	});
	
	$(document).on('click', "#otpChoiceRow button", function(e){
		e.preventDefault();
		$("#otpMethod").val($(this).data('method'));
		$("#changeMethod").data('showmkup', 'false');
		sendHhkLogin();
	});
	
	$('#txtPW').val('');
	$('#txtUname').focus();
	
	$("button, input[type=submit]").button();
	
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

});
