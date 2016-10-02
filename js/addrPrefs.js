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

