/**
 *
 * @param {string} mess
 * @param {boolean} wasError
 * @param {jQuery} $txtCtrl
 * @returns {undefined}
 */
function flagAlertMessage(mess, wasError, $txtCtrl) {
    "use strict";
    //Types:  alert, success, warning, error, info/information
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
        new Noty(
            {
                type: type,
                text: mess
            }
        ).show();
    } catch(err) {
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
    var strongRegex = new RegExp("^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])(?=.{8,})");
    var rtn = true;
    if(strongRegex.test(pwCtrl.val())) {
        pwCtrl.removeClass("ui-state-error");
    } else {
        pwCtrl.addClass("ui-state-error");
        rtn = false;
    }
    return rtn;
}

$(document).ready(function () {
    "use strict";
    //Hover states on the nav bar left icons.
    $("ul.hhk-ui-icons li").hover(
            function () {
                $(this).addClass("ui-state-hover");
            },
            function () {
                $(this).removeClass("ui-state-hover");
            }
    );

    $('#contentDiv').css('margin-top', $('#global-nav').css('height'));

    if ($('#dchgPw').length > 0) {
        $('#version').css('cursor','pointer');
        $('#version').hover(
            function () {
                $(this).addClass("ui-state-hover");
            },
            function () {
                $(this).removeClass("ui-state-hover");
            }
        );

        $('#version').click(function () {
            $('div#dchgPw').find('input').removeClass("ui-state-error").val('');
            $('#pwChangeErrMsg').text('');
            
            $('div#dchgPw').find('button').button();
            $('#dchgPw').dialog("option", "title", "User Settings");
            $('#dchgPw').dialog('open');
            $('#txtOldPw').focus();
        });
        
        $('div#dchgPw').on('click', '.showPw', function(){
        	var input = $(this).closest("td").find("input");
        	if(input.prop("type") == 'password'){
        		input.prop("type", "text");
        		$(this).text("Hide");
        	}else{
        		input.prop("type", "password");
        		$(this).text("Show");
        	}
        });
        
        var chPwButtons = {
                "Save": function () {
                	
                    var oldpw = $('#txtOldPw'), 
                            pw1 = $('#txtNewPw1'),
                            pw2 = $('#txtNewPw2'),
                            oldpwMD5, 
                            newpwMD5,
                            questions = [$('#secQ1').val(), $('#secQ2').val(), $('#secQ3').val()],
                            answerIds = [$('#txtAns1').data('ansid'), $('#txtAns2').data('ansid'), $('#txtAns3').data('ansid')],
                            answers = [$('#txtAns1').val(), $('#txtAns2').val(), $('#txtAns3').val()],
                            msg = $('#pwChangeErrMsg'),
                    		qmsg= $('#SecQuestionErrMsg');
                    $('div#dchgPw').find("input").prop("type", "password");
                    $('div#dchgPw').find("button.showPw").text("Show");
                    var errors = false;
                    msg.empty();
                    qmsg.empty();
                    
                    if($.inArray(null, questions) > -1){
                    	qmsg.append('You must choose 3 security questions<br>');
                    	errors = true;
                    }
                    
                    //check for duplicate questions
                    var alreadySeen = []
                    questions.forEach(function(str) {
                    	if(str){
                    		
    	                    if (alreadySeen[str]){
    	                    	qmsg.append('You cannot choose the same question twice<br>');
    	                    	errors = true;
    	                    	return false;
    	                    }else{
    	                    	alreadySeen[str] = true;
    	                    }
                    	}
                    });
                    
                    //If answer is new, ensure it is not blank
                    questions.forEach(function(val, i) {
                    	var answerNum = parseInt(i)+1;
                        if (answerIds[i] == "" && answers[i] == ""){
                        	qmsg.append('Answer ' + answerNum + ' is required<br>');
                        	errors = true;
                        }
                    });
                    if(errors){
                    	return;
                    }
                    
                    //if updating security questions
                    var changed = false;
                    answers.forEach(function(val, i) {
                        if (val != ""){
                        	answers[i] = hex_md5(answers[i]);
                        	changed = true;
                        }
                    });
                    
                    if(changed){
                    	
                    	$.post("ws_admin.php",
                            {
                                cmd: 'chgquestions',
                                q1: questions[0],
                                aid1: answerIds[0],
                                a1: answers[0],
                                q2: questions[1],
                                aid2: answerIds[1],
                                a2: answers[1],
                                q3: questions[2],
                                aid3: answerIds[2],
                                a3: answers[2]
                            },
                            function (data) {
                                if (data) {
                                    try {
                                        data = $.parseJSON(data);
                                    } catch (err) {
                                        alert("Parser error - " + err.message);
                                        return;
                                    }
                                    if (data.error) {

                                        if (data.gotopage) {
                                            window.open(data.gotopage, '_self');
                                        }
                                        flagAlertMessage(data.error, 'error');

                                    } else if (data.success) {

                                        $('#dchgPw').dialog("close");
                                        flagAlertMessage(data.success, 'success');

                                    } else if (data.warning) {
                                        $('#pwChangeErrMsg').text(data.warning);
                                    }
                                }
                            }
                        );
                    	
                    }
                    
                    //if intent is to change password
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
                            msg.text('Password must have at least 8 characters including at least one uppercase, one lower case letter, one number and one symbol.');
                            pw1.focus();
                            return;
                        }

                        pw1.removeClass("ui-state-error");

                        // make MD5 hash of password and concatenate challenge value
                        // next calculate MD5 hash of combined values
                        oldpwMD5 = hex_md5(hex_md5(oldpw.val()) + challVar);
                        newpwMD5 = hex_md5(pw1.val());

                        oldpw.val('');
                        pw1.val('');
                        pw2.val('');

                        $.post("ws_admin.php",
                            {
                                cmd: 'chgpw',
                                old: oldpwMD5,
                                newer: newpwMD5
                            },
                            function (data) {
                                if (data) {
                                    try {
                                        data = $.parseJSON(data);
                                    } catch (err) {
                                        alert("Parser error - " + err.message);
                                        return;
                                    }
                                    if (data.error) {

                                        if (data.gotopage) {
                                            window.open(data.gotopage, '_self');
                                        }
                                        flagAlertMessage(data.error, 'error');

                                    } else if (data.success) {

                                        $('#dchgPw').dialog("close");
                                        flagAlertMessage(data.success, 'success');

                                    } else if (data.warning) {
                                        $('#pwChangeErrMsg').text(data.warning);
                                    }
                                }
                            }
                        );
                    };
                },
                "Cancel": function(){
                	$(this).dialog('close');
                }
            };
        
        var isUserNew = $("input#isUserNew").val();
        
        if(isUserNew){
        	var isUserNew = true;
        	var closeOnEscape = false;
        	var dialogClass = "no-close";
        	
        	$('div#dchgPw').find('button').button();        	
        }else{
        	var isUserNew = false;
        	var closeOnEscape = true;
        	var dialogClass = '';
        	chPwButtons["Cancel"] = function () {
        		$(this).dialog("close");
        	};
        }
        
        $('div#dchgPw').on('change', 'input', function () {
            $(this).removeClass("ui-state-error");
            $(".hhk-alert").hide();
            $('#pwChangeErrMsg').text('');
        });
    
        $('#dchgPw').dialog({
            autoOpen: isUserNew,
            width: 'auto',
            autoResize: true,
            resizable: true,
            modal: true,
            dialogClass: dialogClass,
            closeOnEscape: closeOnEscape,
            title: "Welcome",
            buttons: chPwButtons
        });
    }
});
