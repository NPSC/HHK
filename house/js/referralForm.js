
$(document).ready(function() {

    var previewFormData = buffer.Buffer.from(referralFormVars.formDataStr).toString("base64");

    var guestGroup = [];
    var addGuestPosition = 0;
    var formRender = false;

    $.ajax({
        url: 'ws_forms.php',
        method: referralFormVars.method,
        data: {
            cmd: referralFormVars.cmd,
            id: referralFormVars.id,
            formData: previewFormData,
            initialGuests: referralFormVars.initialGuests,
            maxGuests: referralFormVars.maxGuests

        },
        dataType:'json',
        success: function(ajaxData){
            if(ajaxData.formData && ajaxData.formSettings){
                try{
                    formData = JSON.parse(ajaxData.formData);
                }catch(e){
                    formData = JSON.parse(buffer.Buffer.from(ajaxData.formData, 'base64').toString('utf-8'));
                }
                formSuccessTitle = ajaxData.formSettings.successTitle;
                formSuccessContent = ajaxData.formSettings.successContent;

                $("style#mainStyle").append(ajaxData.formSettings.formStyle);
                if(ajaxData.formSettings.fontImport){
                    $("style#fontImport").text(ajaxData.formSettings.fontImport);
                }

                if(ajaxData.formSettings.enableRecaptcha){
                    $("head").append(ajaxData.formSettings.recaptchaScript);
                }

                $.each(formData, function(key, element){
                    var elementCopy = JSON.parse(JSON.stringify(element)); // force deep copy
                    if(elementCopy.group == 'guest'){
                        guestGroup.push(elementCopy);
                    }

                    if(elementCopy.className == 'guestHeader'){
                        formData[key].label = elementCopy.label.replace("${guestNum}", "1");
                    }
                });

                formData = JSON.stringify(formData);

                formRender = $('#formContent').formRender({
                    formData,
                    layoutTemplates: {
                        noLabel: function(field, label, help, data){
                            if(data.type == 'paragraph'){
                                field = $(field).removeAttr('width');
                                return $('<div/>').addClass(data.width + ' field-container').append(field);
                            }else if(data.type == 'button'){
                                return $('<div/>').addClass(data.width + ' mb-3 field-container').append(field);
                            }else{
                                return $('<div/>').addClass(data.width + ' field-container').append(field);
                            }
                        },
                          default: function(field, label, help, data) {
                              field = $(field).removeAttr('width');
                              if(data.description){
                                  help = $('<small/>').addClass('helpText text-muted ms-2').text(data.description);
                              }
                              var validation = $('<div/>').addClass('validationText').attr("data-field", data.id);

                              if(data.type == 'radio-group'){
                                  $(field).children().addClass('form-check');
                                  $(field).find('.formbuilder-radio-inline').addClass('form-check-inline');
                                  $(field).find('input[type=radio').addClass('form-check-input');
                                  $(field).find('label').addClass('form-check-label');
                                  $(field).find('input.other-option').css('margin-top', '0.5em');
                                  $(field).find('input.other-val').addClass('form-control d-inline-block w-75 ms-2');

                                  return $('<div/>').addClass(data.width + ' mb-3 field-container')
                                          .append($('<div/>').addClass('card')
                                          .append($('<div/>').addClass('card-body')
                                          .append(label, field, help, validation)
                                      )
                                  );
                              } else if(data.type == 'checkbox-group'){
                                  $(field).children().addClass('form-check');
                                  $(field).find('.formbuilder-checkbox-inline').addClass('form-check-inline');
                                  $(field).find('input[type=checkbox').addClass('form-check-input');
                                  $(field).find('label').addClass('form-check-label');
                                  $(field).find('input.other-option').css('margin-top', '0.5em');
                                  $(field).find('input.other-val').addClass('form-control d-inline-block w-75 ms-2');
                                  return $('<div/>').addClass(data.width + ' mb-3 field-container')
                                          .append($('<div/>').addClass('card')
                                          .append($('<div/>').addClass('card-body')
                                          .append(label, field, help, validation)
                                      )
                                  );
                              }else if(data.type == 'select'){
                                  if(data.dataSource && ajaxData.lookups[data.dataSource]){
                                      var options = {};
                                      options = ajaxData.lookups[data.dataSource];

                                      $(field).html('<option disabled selected>' + data.placeholder + '</option>');
                                      for(i in options){
                                          if(typeof data.userData != 'undefined' && options[i].Code == data.userData[0]){
                                            $(field).append('<option value="' + options[i].Code + '" selected>' + options[i].Description + '</option>');
                                        }else{
                                            $(field).append('<option value="' + options[i].Code + '">' + options[i].Description + '</option>');
                                        }
                                    }
                                }

                                if($(field).hasClass("bfh-states")){
                                    $(field).bfhstates($(field).data()).val($(field).attr('user-data'));
                                }else if($(field).hasClass("bfh-countries")){
                                    $(field).bfhcountries($(field).data()).val($(field).attr('user-data'));
                                }
                                

                                if(data.multiple){
                                    return $('<div/>').addClass(data.width + ' mb-3 field-container')
                                      .append($('<div/>').addClass('card')
                                          .append($('<div/>').addClass('card-body')
                                              .append(label, field, help, validation)
                                          )
                                      );
                                }
                              }

                            return $('<div/>').addClass(data.width + ' mb-3 field-container').append($('<div/>').addClass('form-floating').append(field, label, help, validation));
                          }
                    },
                    "i18n":{
                        "location":"../js/formBuilder"
                    }
                });

                $('.formBuilder-injected-style').remove();

                var siteKey = ajaxData.formSettings.recaptchaSiteKey;
                var recaptchaEnabled = ajaxData.formSettings.enableRecaptcha;

                var $renderedForm = $(document).find('#formContent');
                $renderedForm.find('.rendered-form').addClass('row');

                $renderedForm.find('input.hhk-zipsearch').each(function(){
                        var hhkprefix = $(this).attr('id').replace("adrzip", "").replaceAll(".", '\\.');
                        $(this).data('hhkprefix', hhkprefix).data('hhkindex','');
                    });

                //zip code search
                $renderedForm.find('input.hhk-zipsearch').each(function() {
                    var lastXhr;
                    createZipAutoComplete($(this), 'ws_forms.php', lastXhr, null);
                });

                $renderedForm.find('.address').prop('autocomplete', 'search');

                //phone format
                verifyAddrs($renderedForm);

                $('input.form-control').blur(function(){
                    var val = $(this).val().replaceAll('"', "'");
                    $(this).val(val);
                });

                $renderedForm.find('input, textarea').each(function() {
                    var val = he.decode($(this).val());
                    $(this).val(val);
                });

                $(document).on('submit', 'form#renderedForm', function(e){
                    e.preventDefault();
                    if(recaptchaEnabled){
                        grecaptcha.execute(siteKey, {action: 'submit'}).then(function(token){
                            submitForm(token);
                        });
                    }else{
                        submitForm();
                    }
                });

                var guestIndex = 0;
                var guestCount = 1;
                var $addGuestBtn = $renderedForm.find('#addGuest');

                $renderedForm.on('click', '#addGuest', function(){
                    addGuest();
                });

                $renderedForm.on('click', '#removeGuest', function(){
                    let index = $(this).attr("guest-index");
                    removeGuest(index);
                });

                if($addGuestBtn.length > 0){
                    while(guestCount < ajaxData.formSettings.initialGuests){
                        addGuest();
                    }
                }

                function addGuest(){
                    guestIndex++;
                    guestCount++;

                    var userData = formRender.userData;
                    var thisGuestGroup = [];

                    $.each(userData, function(key, element){
                        if(element.name == 'addGuest'){
                            addGuestPosition = key;
                        }
                    });

                    $.each(guestGroup, function(key, element){
                        var newElement = JSON.parse(JSON.stringify(element)); //deep copy object (prevent reference/pointer issues)
                        if(newElement.name){
                            newElement.name = newElement.name.replace(/\.g([0-9]+)\./ig, ".g" + guestIndex + ".");
                                                            newElement.name = newElement.name.replace(/([a-z,-]+-[0-9]*-)([0-9]{1,2})$/ig, "$1" + guestIndex);
                        }

                        if(newElement.className === "guestHeader"){
                            guestNum = guestIndex+1;
                            newElement.label = newElement.label.replace("${guestNum}", guestNum);
                        }

                        newElement.guestIndex = guestIndex;

                        thisGuestGroup.push(newElement);
                    });

                    Array.prototype.splice.apply(userData, [addGuestPosition, 0].concat(thisGuestGroup));
                    $renderedForm.formRender('render', userData);

                    $renderedForm.find('.rendered-form').addClass('row');

                    $renderedForm.find('input.hhk-zipsearch').each(function(){
                        var hhkprefix = $(this).attr('id').replace("adrzip", "").replaceAll(".", '\\.');
                        $(this).data('hhkprefix', hhkprefix).data('hhkindex','');
                    });

                    //zip code search
                    $renderedForm.find('input.hhk-zipsearch').each(function() {
                        var lastXhr;
                        createZipAutoComplete($(this), 'ws_forms.php', lastXhr, null);
                    });

                    $renderedForm.find('.address').prop('autocomplete', 'search');

                    //phone format
                    verifyAddrs($renderedForm);

                    $('input.form-control').blur(function(){
                        var val = $(this).val().replaceAll('"', "'");
                        $(this).val(val);
                    });

                    if(guestCount >= ajaxData.formSettings.maxGuests){
                        $renderedForm.find('#addGuest').attr('disabled','disabled').parents(".field-container").addClass("d-none");
                    }
                }

                function removeGuest(guestIndex){
                    guestCount--;

                    var userData = formRender.userData;
                    var thisGuestGroup = [];

                    userData = userData.filter(element=>!(element.group == 'guest' && element.guestIndex == guestIndex));

                    //console.log(userData);

                    $renderedForm.formRender('render', userData);

                    $renderedForm.find('.rendered-form').addClass('row');

                    //zip code search
                    $renderedForm.find('input.hhk-zipsearch').each(function() {
                        var lastXhr;
                        createZipAutoComplete($(this), 'ws_forms.php', lastXhr, null);
                    });

                    $renderedForm.find('.address').prop('autocomplete', 'search');

                    //phone format
                    verifyAddrs($renderedForm);

                    $('input.form-control').blur(function(){
                        var val = $(this).val().replaceAll('"', "'");
                        $(this).val(val);
                    });

                    if(guestIndex+1 < ajaxData.formSettings.maxGuests){
                        $renderedForm.find('#addGuest').attr('disabled',false).parents(".field-container").removeClass("d-none");
                    }
                }

                function cleanString(input) {
                    var output = "";
                    for (var i=0; i<input.length; i++) {
                        if (input.charCodeAt(i) <= 127) {
                            output += input.charAt(i);
                        }
                    }
                    return output;
                }

                function submitForm(token = ''){
                    var spinner = $('<span/>').addClass("spinner-border spinner-border-sm");
                    $renderedForm.find('.submit-btn').prop('disabled','disabled').html(spinner).append(' Submitting...');
                    $('.errmsg, .msg').hide();

                    var formRenderData = cleanString(JSON.stringify(formRender.userData));

                    $.ajax({
                        url : "ws_forms.php",
                           type: "POST",
                        data : {
                            cmd: "submitform",
                            formRenderData: buffer.Buffer.from(formRenderData).toString('base64'),
                            recaptchaToken: token,
                            template: referralFormVars.template
                        },
                        dataType: "json",
                        success: function(data, textStatus, jqXHR)
                        {
                            $('input, select, textarea').removeClass('is-invalid');
                            $('.validationText').empty().removeClass('invalid-feedback');
                            $('.submit-btn').text('Submit').removeAttr('disabled');

                            if(data.errors){
                                $.each(data.errors, function(key, error){
                                    if(key == 'server'){
                                        $('#errorcontent').text(error);
                                        $('.errmsg').show();
                                    }else{
                                        $('form *[name="' + error.field + '"]').addClass('is-invalid');
                                        $('form *[name="' + error.field + '[]"]').addClass('is-invalid').parents(".checkbox-group").addClass('is-invalid');
                                        $('.validationText[data-field="' + error.field + '"').addClass('invalid-feedback').text(error.error);
                                        $('.errmsg .alert-heading').text('Error');
                                        $('.errmsg #errorcontent').text('You have validation errors in your submission, please correct the fields marked in red and try again.');
                                        $('.errmsg').show();
                                    }
                                });
                            }
                            if(data.status == "success") {
                                $('.rendered-form button[type=submit]').attr("disabled", "disabled").hide();
                                $('.rendered-form input, .rendered-form textarea, .rendered-form select').attr('disabled', 'disabled');
                                $('.msg .alert-heading').html(formSuccessTitle);
                                $('.msg .successmsg').html(formSuccessContent);
                                $('.msg').show();
                                if(data.recaptchaScore){
                                    $('.msg #recaptchascore').text(data.recaptchaScore);
                                }else{
                                    $('.msg #recaptchascore').empty();
                                }
                                $('.errmsg').hide();
                            }
                            $('html, body').animate({scrollTop:$(document).height()}, 'slow');
                        },
                        error: function(data, textStatus, errorThrown){
                            $('input, textarea, select, .checkbox-group').removeClass('is-invalid');
                            $('.validationText').empty().removeClass('invalid-feedback');
                            $('.submit-btn').text('Submit').removeAttr('disabled');
                        },
                        statusCode: {
                            501: function() {
                                $('.errmsg .alert-heading').text('Error');
                                $('.errmsg #errorcontent').text('You have invalid special characters in your submission. Is your cat on your keyboard? Please check your form and try again.');
                                $('.errmsg').show();
                            },
                            500: function() {
                                $('.errmsg .alert-heading').text('Server Error');
                                $('.errmsg #errorcontent').text("We are unable to process your submission at this time due to a server error. We're hard at work fixing the issue. Please try again in a few minutes.");
                                $('.errmsg').show();
                            },
                            403: function() {
                                $('.errmsg .alert-heading').text('Server Error');
                                $('.errmsg #errorcontent').text("We are unable to process your submission at this time due to a server error. We're hard at work fixing the issue. Please try again in a few minutes.");
                                $('.errmsg').show();
                            }
                          }
                    });
                }

            }else if(ajaxData.error){
                $("#formError").text(ajaxData.error);
            }
        },
        error: function(XHR, textStatus, errorText){
            $("#formError").text("Error " + XHR.status + ": " + errorText);
            if(typeof hhkReportError == "function"){
                var errorInfo = {
                    responseCode: XHR.status,
                    source:"<?php echo $cmd; ?>",
                    docId: "<?php echo $id; ?>",
                    formData: previewFormData
                }
                errorInfo = btoa(JSON.stringify(errorInfo));
                hhkReportError(errorText, errorInfo);
            }
        }
    });

});