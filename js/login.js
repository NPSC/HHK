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
	    otp: $otp.val()
	};
	$.post('index.php', parms, function (data){
	    try {
	        data = $.parseJSON(data);
	    } catch (err) {
	        alert(data);
	        $('#divLoginCtls').remove();
	        return;
	    }
	    if(data.OTPRequired){
	    	$("#loginTitle").text("Two Factor Verification");
	    	$('#userRow, #pwRow').hide();
	    	$('#otpRow').removeClass("d-none");
	    	$('#txtOTP').data("2fa", "true").focus();
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
	});
}
$(document).ready(function () {

	//newsletter
	var iframe = $('<iframe frameborder="0" marginwidth="0" marginheight="0"></iframe>');
    var dialog = $("<div></div>").append(iframe).appendTo("body").dialog({
        autoOpen: false,
        modal: true,
        resizable: false,
        width: "auto",
        height: "auto",
        closeOnEscape: true,
        close: function () {
            iframe.attr("src", "");
        }
    });
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
		sendHhkLogin();
	});
	
	$('#txtPW').val('');
	$('#txtUname').focus();
	
	$("button, input[type=submit]").button();
	
	$(document).on('mousedown', '.showPw', function() {
		var input = $(this).parent().find("input");
		input.prop("type", "text");
	});
	
	$(document).on('mouseup', '.showPw', function() {
		var input = $(this).parent().find("input");
		input.prop("type", "password");
	});

});
