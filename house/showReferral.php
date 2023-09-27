<?php

use HHK\sec\{Session, WebInit, Labels};
use HHK\SysConst\WebPageCode;
use HHK\Member\Role\Guest;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\Payment\Receipt;
use HHK\House\Visit\Visit;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLInput;
use HHK\Document\FormTemplate;
use HHK\Document\FormDocument;
use HHK\sec\Login;
use HHK\sec\ScriptAuthClass;
use HHK\sec\SecurityComponent;
use HHK\sec\Pages;
use HHK\sec\SysConfig;
use HHK\sec\Recaptcha;
use HHK\SysConst\GLTableNames;
use HHK\House\Hospital\Hospital;

/**
 * ShowStatement.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");

// Access the login object, set session vars,
try {

    $login = new Login();
    $dbh = $login->initHhkSession(CONF_PATH, ciCFG_FILE);

} catch (InvalidArgumentException $pex) {
    exit ("<h3>Database Access Error.   <a href='index.php'>Continue</a></h3>");

} catch (Exception $ex) {
    exit ("<h3>" . $ex->getMessage());
}


// Load the page information
try {
    $page = new ScriptAuthClass($dbh);
} catch (Exception $ex) {
    $uS->destroy(true);
    exit('<h2>Page not in database.</h2>');
}

$uS = Session::getInstance();
$labels = Labels::getLabels();
$recaptcha = new Recaptcha();

$formTemplate = '';
$id = -1;
$formData = '';
$style = '';
$successTitle = '';
$successContent = '';
$enableRecaptcha = false;
$error = '';
$cmd = '';
$method = '';
$fontImport = false;

if(isset($_GET['template'])){
    $cmd = 'gettemplate';
    $method = 'get';
    $id = filter_var($_GET["template"], FILTER_SANITIZE_NUMBER_INT);
}else if(isset($_GET['form'])){
    $cmd = 'getform';
    $method = 'get';
    $id = filter_var($_GET["form"], FILTER_SANITIZE_NUMBER_INT);
}else if(isset($_POST['cmd']) && $_POST['cmd'] == "preview" && isset($_POST['formData']) && isset($_POST['style'])){
    $cmd = 'previewform';
    $method = 'post';
    $formData = json_decode($_POST['formData']);
    $style = $_POST['style'];
    $initialGuests = $_POST['initialGuests'];
    $maxGuests = $_POST['maxGuests'];
}else{
    $error = "Missing required parameters";
}


?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Referral Form</title>

		<style id="fontImport"></style>

        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo BOOTSTRAP_CSS; ?>
        <?php echo FAVICON; ?>

        <style id="mainStyle">
    	   <?php echo $style; ?>

    	   fieldset[disabled=disabled] button {
    	       display: none;
    	   }

    	   .msg, .errmsg {
    	       margin: 1em;
    	   }
    	   .msg p.successmsg {
    	       white-space: pre-wrap;
    	   }

    	   @media print {
                body{
                    zoom: 85%;
                }
           }

           input{ touch-action: pan-x pan-y; }

    	</style>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo ADDR_PREFS_JS; ?>"></script>
		<script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="../js/formBuilder/form-render.min.js"></script>

        <script type='text/javascript'>
            $(document).ready(function() {

            	var previewFormData = JSON.stringify(<?php echo json_encode($formData); ?>);

				var guestGroup = [];
				var addGuestPosition = 0;
				var formRender = false;

				$.ajax({
					url: 'ws_forms.php',
					method: '<?php echo $method; ?>',
					data: {
						cmd: '<?php echo $cmd; ?>',
						id: '<?php echo $id; ?>',
						formData: previewFormData,
						initialGuests: '<?php echo (isset($initialGuests) ? $initialGuests: 0); ?>',
						maxGuests: '<?php echo (isset($maxGuests) ? $maxGuests: 0); ?>'

					},
					dataType:'json',
					success: function(ajaxData){
						if(ajaxData.formData && ajaxData.formSettings){
    						formData = JSON.parse(ajaxData.formData);
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

                        	var siteKey = '<?php echo $recaptcha->getSiteKey(); ?>';
                        	var recaptchaEnabled = ajaxData.formSettings.enableRecaptcha;

                        	var $renderedForm = $(document).find('#formContent');
                        	$renderedForm.find('.rendered-form').addClass('row');

                        	$renderedForm.find('input.hhk-zipsearch').each(function(){
    								var hhkprefix = $(this).attr('id').replace("adrzip", "").replaceAll(".", '\\.');
                            		$(this).data('hhkprefix', hhkprefix).data('hhkindex','');
    							});

                        	// set country and state selectors
                            $renderedForm.find('select.bfh-countries').each(function() {
                                var $countries = $(this);
                                $countries.bfhcountries($countries.data()).val($countries.attr('user-data'));
                            });
                            $renderedForm.find('select.bfh-states').each(function() {
                                var $states = $(this);
                                $states.bfhstates($states.data()).val($states.attr('user-data'));
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

                            	// set country and state selectors
                                $renderedForm.find('select.bfh-countries').each(function() {
                                    var $countries = $(this);
                                    $countries.bfhcountries($countries.data()).val($countries.attr('user-data'));
                                });
                                $renderedForm.find('select.bfh-states').each(function() {
                                    var $states = $(this);
                                    $states.bfhstates($states.data()).val($states.attr('user-data'));
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

                        	function submitForm(token = ''){
                        		var spinner = $('<span/>').addClass("spinner-border spinner-border-sm");
                        		$renderedForm.find('.submit-btn').prop('disabled','disabled').html(spinner).append(' Submitting...');

                        		var formRenderData = formRender.userData;

                        		$.ajax({
                        	    	url : "ws_forms.php",
                        	   		type: "POST",
                        	    	data : {
                        	    		cmd: "submitform",
                        	    		formRenderData: JSON.stringify(formRenderData),
                        	    		recaptchaToken: token,
                        	    		template: <?php echo (isset($_GET['template']) ? $_GET['template'] : 0); ?>
                        	    	},
                        	    	dataType: "json",
                        	    	success: function(data, textStatus, jqXHR)
                        	    	{
                        	    	    $('input, select').removeClass('is-invalid');
                        	    	    $('.validationText').empty().removeClass('invalid-feedback');
                        	    	    $('.submit-btn').text('Submit').removeAttr('disabled');

                        	    	    if(data.errors){
                        	    	    	$.each(data.errors, function(key, error){
                        	    	    		if(key == 'server'){
                        	    	    			$('#errorcontent').text(error);
                        	    	    			$('.errmsg').show();
                        	    	    		}else{
                        	    	    			$('form *[name="' + error.field + '"]').addClass('is-invalid');
                        	    	    			$('.validationText[data-field="' + error.field + '"').addClass('invalid-feedback').text(error.error);
                        	    	    			$('.errmsg .alert-heading').text('Error');
                        	    	    			$('.errmsg #errorcontent').text('You have validation errors in your submission, please correct the fields marked in red and try again.');
                        	    	    			$('.errmsg').show();
                        	    	    		}
                        	    	    	});
                        	    	    }
                        	    	    if(data.status == "success") {
                        	    	    	$('.rendered-form button[type=submit]').attr("disabled", "disabled").hide();
                        	    	    	$('.rendered-form input, .rendered-form select').attr('disabled', 'disabled');
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
                        	    		$('input, select').removeClass('is-invalid');
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
	</script>



    </head>
    <body>


    	<?php if(isset($_GET['form'])){ ?>
    	<fieldset disabled="disabled">
    	<?php }else{ ?>
    	<form action="#" method="POST" novalidate id="renderedForm">
    	<?php } ?>
        <div id="formContent" class="container-fluid">
			<div id="formError" style="text-align: center"></div>
        </div>
        <div class="alert alert-success msg" role="alert" style="display: none">
    		<h4 class="alert-heading"></h4>
    		<p class="successmsg"></p>
    		<p style="display:none">Recaptcha Score: <span id="recaptchascore"></span></p>
    	</div>
    	<div class="alert alert-danger errmsg" role="alert" style="display: none">
    		<h4 class="alert-heading">Server Error</h4>
    		<p id="errorcontent"></p>
    	</div>
        <?php if(isset($_GET['form'])){ ?>
        </fieldset>
        <?php }else{ ?>
        </form>
        <?php } ?>

    </body>
</html>
