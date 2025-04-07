$(document).ready(function(){var e=buffer.Buffer.from(referralFormVars.formDataStr).toString("base64"),t=[],a=0,r=!1;$.ajax({url:"ws_forms.php",method:referralFormVars.method,data:{cmd:referralFormVars.cmd,id:referralFormVars.id,formData:e,initialGuests:referralFormVars.initialGuests,maxGuests:referralFormVars.maxGuests},dataType:"json",success:function(e){if($("#formContent").removeClass("hhk-loading"),e.formData&&e.formSettings){try{formData=JSON.parse(e.formData)}catch(s){formData=JSON.parse(buffer.Buffer.from(e.formData,"base64").toString("utf-8"))}formSuccessTitle=e.formSettings.successTitle,formSuccessContent=e.formSettings.successContent,$("style#mainStyle").append(e.formSettings.formStyle),e.formSettings.fontImport&&$("style#fontImport").text(e.formSettings.fontImport),e.formSettings.enableRecaptcha&&$("head").append(e.formSettings.recaptchaScript),$.each(formData,function(e,a){var r=JSON.parse(JSON.stringify(a));"guest"==r.group&&t.push(r),"guestHeader"==r.className&&(formData[e].label=r.label.replace("${guestNum}","1"))}),formData=JSON.stringify(formData),r=$("#formContent").formRender({formData,layoutTemplates:{noLabel:function(e,t,a,r){return"paragraph"==r.type?(e=$(e).removeAttr("width"),$("<div/>").addClass(r.width+" field-container").append(e)):"button"==r.type?$("<div/>").addClass(r.width+" mb-3 field-container").append(e):$("<div/>").addClass(r.width+" field-container").append(e)},default:function(t,a,r,s){t=$(t).removeAttr("width"),s.description&&(r=$("<small/>").addClass("helpText text-muted ms-2").text(s.description));var n=$("<div/>").addClass("validationText").attr("data-field",s.id);if("radio-group"==s.type)return $(t).children().addClass("form-check"),$(t).find(".formbuilder-radio-inline").addClass("form-check-inline"),$(t).find("input[type=radio").addClass("form-check-input"),$(t).find("label").addClass("form-check-label"),$(t).find("input.other-option").css("margin-top","0.5em"),$(t).find("input.other-val").addClass("form-control d-inline-block w-75 ms-2"),$("<div/>").addClass(s.width+" mb-3 field-container").append($("<div/>").addClass("card").append($("<div/>").addClass("card-body").append(a,t,r,n)));if("checkbox-group"==s.type)return $(t).children().addClass("form-check"),$(t).find(".formbuilder-checkbox-inline").addClass("form-check-inline"),$(t).find("input[type=checkbox").addClass("form-check-input"),$(t).find("label").addClass("form-check-label"),$(t).find("input.other-option").css("margin-top","0.5em"),$(t).find("input.other-val").addClass("form-control d-inline-block w-75 ms-2"),$("<div/>").addClass(s.width+" mb-3 field-container").append($("<div/>").addClass("card").append($("<div/>").addClass("card-body").append(a,t,r,n)));if("select"==s.type){if(s.dataSource&&e.lookups[s.dataSource]){var d={};for(i in d=e.lookups[s.dataSource],$(t).html("<option disabled selected>"+s.placeholder+"</option>"),d)void 0!==s.userData&&d[i].Code==s.userData[0]?$(t).append('<option value="'+d[i].Code+'" selected>'+d[i].Description+"</option>"):$(t).append('<option value="'+d[i].Code+'">'+d[i].Description+"</option>")}if($(t).hasClass("bfh-states")?$(t).bfhstates($(t).data()).val($(t).attr("user-data")):$(t).hasClass("bfh-countries")&&$(t).bfhcountries($(t).data()).val($(t).attr("user-data")),s.multiple)return $("<div/>").addClass(s.width+" mb-3 field-container").append($("<div/>").addClass("card").append($("<div/>").addClass("card-body").append(a,t,r,n)))}return $("<div/>").addClass(s.width+" mb-3 field-container").append($("<div/>").addClass("form-floating").append(t,a,r,n))}},i18n:{location:"../js/formBuilder"}}),$(".formBuilder-injected-style").remove();var n=e.formSettings.recaptchaSiteKey,d=e.formSettings.enableRecaptcha,o=$(document).find("#formContent");o.find(".rendered-form").addClass("row"),o.find("input.hhk-zipsearch").each(function(){var e=$(this).attr("id").replace("adrzip","").replaceAll(".","\\.");$(this).data("hhkprefix",e).data("hhkindex","")}),o.find("input.hhk-zipsearch").each(function(){var e;createZipAutoComplete($(this),"ws_forms.php",e,null)}),o.find(".address").prop("autocomplete","search"),verifyAddrs(o),$("input.form-control").blur(function(){var e=$(this).val().replaceAll('"',"'");$(this).val(e)}),o.find("input, textarea").each(function(){var e=he.decode($(this).val());$(this).val(e)}),$(document).on("submit","form#renderedForm",function(e){e.preventDefault(),d?grecaptcha.execute(n,{action:"submit"}).then(function(e){m(e)}):m()});var l=0,c=1,p=o.find("#addGuest");if(o.on("click","#addGuest",function(){f()}),o.on("click","#removeGuest",function(){var t,a;t=$(this).attr("guest-index"),c--,a=r.userData,a=a.filter(e=>!("guest"==e.group&&e.guestIndex==t)),o.formRender("render",a),o.find(".rendered-form").addClass("row"),o.find("input.hhk-zipsearch").each(function(){var e;createZipAutoComplete($(this),"ws_forms.php",e,null)}),o.find(".address").prop("autocomplete","search"),verifyAddrs(o),$("input.form-control").blur(function(){var e=$(this).val().replaceAll('"',"'");$(this).val(e)}),t+1<e.formSettings.maxGuests&&o.find("#addGuest").attr("disabled",!1).parents(".field-container").removeClass("d-none")}),p.length>0)for(;c<e.formSettings.initialGuests;)f();function f(){l++,c++;var s=r.userData,n=[];$.each(s,function(e,t){"addGuest"==t.name&&(a=e)}),$.each(t,function(e,t){var a=JSON.parse(JSON.stringify(t));a.name&&(a.name=a.name.replace(/\.g([0-9]+)\./ig,".g"+l+"."),a.name=a.name.replace(/([a-z,-]+-[0-9]*-)([0-9]{1,2})$/ig,"$1"+l)),"guestHeader"===a.className&&(guestNum=l+1,a.label=a.label.replace("${guestNum}",guestNum)),a.guestIndex=l,n.push(a)}),Array.prototype.splice.apply(s,[a,0].concat(n)),o.formRender("render",s),o.find(".rendered-form").addClass("row"),o.find("input.hhk-zipsearch").each(function(){var e=$(this).attr("id").replace("adrzip","").replaceAll(".","\\.");$(this).data("hhkprefix",e).data("hhkindex","")}),o.find("input.hhk-zipsearch").each(function(){var e;createZipAutoComplete($(this),"ws_forms.php",e,null)}),o.find(".address").prop("autocomplete","search"),verifyAddrs(o),$("input.form-control").blur(function(){var e=$(this).val().replaceAll('"',"'");$(this).val(e)}),c>=e.formSettings.maxGuests&&o.find("#addGuest").attr("disabled","disabled").parents(".field-container").addClass("d-none")}function m(e=""){var t=$("<span/>").addClass("spinner-border spinner-border-sm");o.find(".submit-btn").prop("disabled","disabled").html(t).append(" Submitting..."),$(".errmsg, .msg").hide();var a=function e(t){for(var a="",r=0;r<t.length;r++)127>=t.charCodeAt(r)&&(a+=t.charAt(r));return a}(JSON.stringify(r.userData));$.ajax({url:"ws_forms.php",type:"POST",data:{cmd:"submitform",formRenderData:buffer.Buffer.from(a).toString("base64"),recaptchaToken:e,template:referralFormVars.template},dataType:"json",success:function(e,t,a){$("input, select, textarea").removeClass("is-invalid"),$(".validationText").empty().removeClass("invalid-feedback"),$(".submit-btn").text("Submit").removeAttr("disabled"),e.errors&&$.each(e.errors,function(e,t){"server"==e?($("#errorcontent").text(t),$(".errmsg").show()):($('form *[name="'+t.field+'"]').addClass("is-invalid"),$('form *[name="'+t.field+'[]"]').addClass("is-invalid").parents(".checkbox-group").addClass("is-invalid"),$('.validationText[data-field="'+t.field+'"').addClass("invalid-feedback").text(t.error),$(".errmsg .alert-heading").text("Error"),$(".errmsg #errorcontent").text("You have validation errors in your submission, please correct the fields marked in red and try again."),$(".errmsg").show())}),"success"==e.status&&($(".rendered-form button[type=submit]").attr("disabled","disabled").hide(),$(".rendered-form input, .rendered-form textarea, .rendered-form select").attr("disabled","disabled"),$(".msg .alert-heading").html(formSuccessTitle),$(".msg .successmsg").html(formSuccessContent),$(".msg").show(),e.recaptchaScore?$(".msg #recaptchascore").text(e.recaptchaScore):$(".msg #recaptchascore").empty(),$(".errmsg").hide()),$("html, body").animate({scrollTop:$(document).height()},"slow")},error:function(e,t,a){$("input, textarea, select, .checkbox-group").removeClass("is-invalid"),$(".validationText").empty().removeClass("invalid-feedback"),$(".submit-btn").text("Submit").removeAttr("disabled")},statusCode:{501:function(){$(".errmsg .alert-heading").text("Error"),$(".errmsg #errorcontent").text("You have invalid special characters in your submission. Is your cat on your keyboard? Please check your form and try again."),$(".errmsg").show()},500:function(){$(".errmsg .alert-heading").text("Server Error"),$(".errmsg #errorcontent").text("We are unable to process your submission at this time due to a server error. We're hard at work fixing the issue. Please try again in a few minutes."),$(".errmsg").show()},403:function(){$(".errmsg .alert-heading").text("Server Error"),$(".errmsg #errorcontent").text("We are unable to process your submission at this time due to a server error. We're hard at work fixing the issue. Please try again in a few minutes."),$(".errmsg").show()}}})}}else e.error&&$("#formError").text(e.error)},error:function(t,a,r){if($("#formError").text("Error "+t.status+": "+r),"function"==typeof hhkReportError){var s={responseCode:t.status,source:"<?php echo $cmd; ?>",docId:"<?php echo $id; ?>",formData:e};hhkReportError(r,s=btoa(JSON.stringify(s)))}}})});