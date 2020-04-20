/**
 * 
 */
function setAlert(msgText) {
    msgText = msgText.replace(/^\s+|\s+$/g, "");
    var spn = document.getElementById('donResultMessage');
    if (msgText == '') {
        // hide the control
        $('#donateResponseContainer').attr("style", "display:none;");
    }
    else {
        // define the error message markup
        $('#donateResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
        $('#donateResponseContainer').attr("style", "display:block;");
        $('#donateResponseIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
        spn.innerHTML = "<strong>Warning: </strong>" + msgText;
    }
}
function updateTips(t) {
    setAlert(t);
}
function checkLength(o, n, min, max) {
    if (o.val().length > max || o.val().length < min) {
        o.addClass("ui-state-error");
        if (min == max)
            updateTips("Length of the " + n + " must be " + max + ".");
        else
            updateTips("Length of the " + n + " must be between " +
                    min + " and " + max + ".");
        return false;
    } else {
        return true;
    }
}
$(document).ready(function() {
    var rexEmail = /^[A-Z0-9._%+-]+@(?:[A-Z0-9-]+\.)+[A-Z]{2,4}$/i,
        regPhone = /^(?:(?:[\+]?([\d]{1,3}(?:[ ]+|[\-.])))?[(]?([2-9][\d]{2})[\-\/)]?(?:[ ]+)?)?([2-9][0-9]{2})[\-.\/)]?(?:[ ]+)?([\d]{4})(?:(?:[ ]+|[xX]|(i:ext[\.]?)){1,2}([\d]{1,5}))?$/,
        $psw = $('#txtPW'),
        $ps2 = $('#txtPW2'),
        $phone = $('#txtPhone');


    $('#btnCancel, #btnReg').button();

    $("#btnCancel").click(function() {
        // This is a one-way trip.
        $('#cancelDiv').removeClass("dispNone").addClass("dispBlock");
        $('#regFormDiv').removeClass("dispBlock").addClass("dispNone");
    });

    $psw.change(function() {
        updateTips("");
        if (checkStrength($psw)) {
            if ($ps2.val() !== "" && $ps2.val() !== this.value) {
                updateTips("Passwords do not match");
            }
        } else {
            updateTips("A password must have 8 or more characters including upper case and lower case letters, symbols and numbers.");
        }
    });

    $ps2.change(function() {
        if ($psw.val() !== "" && $psw.val() !== this.value) {
            updateTips("Passwords do not match");
        }
    });

    $phone.change(function() {
        regPhone.lastIndex = 0;
        // 0 = matached, 1 = 1st capturing group, 2 = 2nd, etc.
        var numarry = regPhone.exec(this.value);
        if (numarry !== null && numarry.length > 3) {
            this.value = "";
            // Country code?
            if (numarry[1] !== null && numarry[1] !== "")
                this.value = '+' + numarry[1];
            // The main part
            this.value = '(' + numarry[2] + ') ' + numarry[3] + '-' + numarry[4].substr(0, 4);
        }
    });

    $("#btnReg").click(function() {

        $('.hhk-txtInput').removeClass("ui-state-error");
        setAlert('');
        $('#returnError').text('');

        if ($('#g-recaptcha-response').val() == '') {
            updateTips("Click the box on the reCAPTCHA");
            return;
        }

        if (!checkLength($('#txtFirstName'), 'First name', 1, 45))
            return;
        if (!checkLength($('#txtLastName'), 'Last name', 1, 45))
            return;
        if (!checkLength($('#txtEmail'), 'Email address', 5, 100))
            return;
        if (!checkRegexp($('#txtEmail'), rexEmail, 'Incorrect Email'))
            return;
        if (!checkLength($('#txtPun'), 'User Name', 6, 45))
            return;
        if (!checkLength($psw, 'Password', 8, 45))
            return;

        if ($psw.val() !== $ps2.val()) {
            updateTips("Passwords do not match");
            return;
        }

        $('#pwHdn').val(hex_md5($psw.val()));

        $('#form1').submit();

    });

    $('input:first').focus();

});
