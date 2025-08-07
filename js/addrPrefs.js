function addrPrefs(memData) {
    "use strict";
    $('input.prefPhone').each(function () {
        if (this.checked) {
            memData.phonePref = this.value;
        }
    });
    $('input.prefEmail').each(function () {
        if (this.checked) {
            memData.emailPref = this.value;
        }
    });
    $('input.addrPrefs').each(function () {
        if (this.checked) {
            memData.addrPref = this.value;
        }
    });
    $('input.addrPrefs').click(function () {
        var indx = this.value, adr1, cty, foundOne;

        adr1 = document.getElementById("adraddress1" + indx);
        cty = document.getElementById("adrcity" + indx);
        if ((adr1 != null && adr1.value == "") || (cty != null && cty.value == "")) {
            alert("This address is blank.  It cannot be the 'preferred' address.");
            this.checked = false;
            foundOne = false;

            // see if the old preferred  - then we check it and done.
            if (memData.addrPref != "" && $("#adraddress1" + memData.addrPref).val() != "") {
                $('#rbPrefMail' + memData.addrPref).prop('checked', true);
                foundOne = true;
            }

            if (!foundOne) {
                $('input.addrPrefs').each(function () {
                    if ($("#adraddress1" + this.value).val() != "") {
                        $(this).prop('checked', true);
                        memData.addrPref = this.value;

                    }
                });
            }
        }
    });
    // enforce the Preferred phone number actually has a number
    $('input.prefPhone').change(function () {
        var foundOne, ctl = $("#txtPhone" + this.value);
        if (ctl !== null && ctl.val() == "") {
            alert("This Phone Number is blank.  It cannot be the 'preferred' phone number.");
            this.checked = false;
            foundOne = false;

            // see if the old preferred phone has a number - then we check it and done.
            if (memData.phonePref != "" && $("#txtPhone" + memData.phonePref).val() != "") {
                $('#ph' + memData.phonePref).prop('checked', true);
                foundOne = true;
            }

            // If we did not check a rb, then find one that has a textbox with info in it
            if (!foundOne) {
                $('input.prefPhone').each(function () {
                    if ($("#txtPhone" + this.value).val() != "") {
                        $(this).prop('checked', true);
                        memData.phonePref = this.value;
                        return;
                    }
                });
            }
        }
    });
    // Enforce preferred Email has a value defined.
    $('input.prefEmail').change(function () {
        var ctl = $("#txtEmail" + this.value), foundOne;
        if (ctl != null && ctl.val() == "") {
            alert("This Email Address is blank.  It cannot be the 'preferred' Email address.");
            foundOne = false;
            this.checked = false;

            if (memData.emailPref != "" && $("#txtEmail" + memData.emailPref).val() != "") {
                $('#em' + memData.emailPref).prop('checked', true);
                foundOne = true;
            }

            // If we did not check a rb, then find one that has a textbox with info in it
            if (!foundOne) {
                $('input.prefEmail').each(function () {
                    if ($("#txtEmail" + this.value).val() != "") {
                        $(this).prop('checked', true);
                        memData.emailPref = this.value;
                        return;
                    }
                });
            }
        }
    });
}

function verifyAddrs(container) {
    "use strict";
    var $container;
    if (typeof(container) === 'string') {
        $container = $(container);
    } else {
        $container = container;
    }

    $container.on('change', 'input.hhk-emailInput', function() {
        var rexEmail = /^[A-Z0-9._%+\-]+@(?:[A-Z0-9]+\.)+[A-Z]{2,20}$/i;
        if ($.trim($(this).val()) !== '' && rexEmail.test($(this).val()) === false) {
            $(this).addClass('ui-state-error');
        } else {
            $(this).removeClass('ui-state-error');
        }
    });

/*     $container.on('change', 'input.hhk-phoneInput', function() {
        // inspect each phone number text box for correctness
        var testreg = /^([\(]{1}[0-9]{3}[\)]{1}[\.| |\-]{0,1}|^[0-9]{3}[\.|\-| ]?)?[0-9]{3}(\.|\-| )?[0-9]{4}$/;
        var regexp = /^(?:(?:[\+]?([\d]{1,3}(?:[ ]+|[\-.])))?[(]?([2-9][\d]{2})[\-\/)]?(?:[ ]+)?)?([2-9][0-9]{2})[\-.\/)]?(?:[ ]+)?([\d]{4})(?:(?:[ ]+|[xX]|(i:ext[\.]?)){1,2}([\d]{1,5}))?$/;
        var numarry;

        //strip out non printable characters
        $(this).val($(this).val().replace(/[\u{0080}-\u{FFFF}]/gu, ""));

        if ($.trim($(this).val()) != '' && testreg.test($(this).val()) === false) {
            // error
            $(this).addClass('ui-state-error');

        } else {

            $(this).removeClass('ui-state-error');
            regexp.lastIndex = 0;
            // 0 = matached, 1 = 1st capturing group, 2 = 2nd, etc.
            numarry = regexp.exec($(this).val());
            if (numarry != null && numarry.length > 3) {
                var ph = "";

                // Country code?
                if (numarry[1] != null && numarry[1] != "") {
                    ph = '+' + numarry[1];
                }

                // The main part
                $(this).val(ph + '(' + numarry[2] + ') ' + numarry[3] + '-' + numarry[4]);

                // Extension?
                if (numarry[6] != null && numarry[6] != "") {
                    $(this).next('input').val(numarry[6]);
                }
            }
        }
    }); */

    $container.on('change', 'input.hhk-phoneInput', function() {
        if($(this).val() !== ""){
            let input = $(this).val();
            input = cleanPhoneNumber(input);
            const {isValid, formatted} = validatePhoneNumber(input);
            if(isValid){
                $(this).removeClass("ui-state-error");
                $(this).val(formatted);
            }else{
                $(this).addClass("ui-state-error");
            }
        }else{
            $(this).removeClass("ui-state-error");
        }
    });

    $container.on('change', 'input.ckzip', function() {
        var postCode = /^(?:[A-Z]{1,2}[0-9][A-Z0-9]? [0-9][ABD-HJLNP-UW-Z]{2}|[ABCEGHJKLMNPRSTVXY][0-9][A-Z] [0-9][A-Z][0-9]|[0-9]{5}(?:\-[0-9]{4})?)$/i;
        if ($(this).val() !== "" && !postCode.test($(this).val())) {
            $(this).addClass('ui-state-error');
        } else {
            $(this).removeClass('ui-state-error');
        }
    });

    $container.find('input.ckzip, input.hhk-phoneInput, input.hhk-emailInput').trigger('change');
}


// Use google's libphonenumber to validate and format international phone numbers
function validatePhoneNumber(phone, defaultRegion = 'US') {
    try {
        const phoneUtil = libphonenumber.PhoneNumberUtil.getInstance();
        const PNF = libphonenumber.PhoneNumberFormat;

        const number = phoneUtil.parseAndKeepRawInput(phone, defaultRegion);
        const regionCode = phoneUtil.getRegionCodeForNumber(number);

        const isValid = phoneUtil.isValidNumber(number);

        const formatType = regionCode === defaultRegion ? PNF.NATIONAL : PNF.INTERNATIONAL;
        const formatted = phoneUtil.format(number, formatType);
        return { isValid, formatted };
      } catch (e) {
        return { isValid: false, formatted: null };
      }
}

function cleanPhoneNumber(input) {
  const cleaned = input.replace(/[^\d+]/g, '');
  return cleaned.startsWith('+')
    ? '+' + cleaned.slice(1).replace(/\+/g, '')
    : cleaned.replace(/\+/g, '');
}