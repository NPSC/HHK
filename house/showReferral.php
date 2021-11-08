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
    $dbh = $login->initHhkSession(ciCFG_FILE);

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


$genders = readGenLookupsPDO($dbh, 'gender', 'Order');
unset($genders['z']);
$patientRels = readGenLookupsPDO($dbh, 'Patient_Rel_Type', 'Order');
unset($patientRels['slf']);
$mediaSources = readGenLookupsPDO($dbh, 'Media_Source','Order');
$namePrefixes = readGenLookupsPDO($dbh, 'Name_Prefix', 'Order');
$nameSuffixes = readGenLookupsPDO($dbh, 'Name_Suffix', 'Order');
$diagnosis = readGenLookupsPDO($dbh, 'Diagnosis', 'Order');
$locations = readGenLookupsPDO($dbh, 'Location', 'Order');
$hospitals = Hospital::loadHospitals($dbh);
$hospitalAr = array();
foreach($hospitals as $hospital){
    $hospitalAr[] = ['Code'=>$hospital['idHospital'], 'Description'=>$hospital['Title']];
}
$stateList = array('', 'AB', 'AE', 'AL', 'AK', 'AR', 'AZ', 'BC', 'CA', 'CO', 'CT', 'CZ', 'DC', 'DE', 'FL', 'GA', 'GU', 'HI', 'IA', 'ID', 'IL', 'IN', 'KS',
    'KY', 'LA', 'LB', 'MA', 'MB', 'MD', 'ME', 'MI', 'MN', 'MO', 'MS', 'MT', 'NB', 'NC', 'ND', 'NE', 'NF', 'NH', 'NJ', 'NM', 'NS', 'NT', 'NV', 'NY', 'OH',
    'OK', 'ON', 'OR', 'PA', 'PE', 'PR', 'PQ', 'RI', 'SC', 'SD', 'SK', 'TN', 'TX', 'UT', 'VA', 'VI', 'VT', 'WA', 'WI', 'WV', 'WY');
$formattedStates = array();
foreach($stateList as $state){
    $formattedStates[$state] = ["Code"=>$state, "Description"=>$state];
}

$formTemplate = '';
$formData = '';
$style = '';
$successTitle = '';
$successContent = '';
$enableRecaptcha = false;
$error = '';

if(isset($_GET['template'])){
    $id = filter_var($_GET["template"], FILTER_SANITIZE_NUMBER_INT);
    if($id > 0){
        $formTemplate = new FormTemplate();
        if($formTemplate->loadTemplate($dbh, $id)){
            $formData = $formTemplate->getTemplate();
            $formSettings = $formTemplate->getSettings();
            $style = $formSettings['formStyle'];
            $successTitle = $formSettings['successTitle'];
            $successContent = $formSettings['successContent'];

            //enableRecaptcha
            if(($uS->mode == 'demo' || $uS->mode == 'prod') && $formSettings['enableRecaptcha']){
                $enableRecaptcha = $formSettings['enableRecaptcha'];
            }
        }else{
            $error = "Document is not a form template";
        }
    }else{
        $error = "No Referral form found";
    }

}else if(isset($_GET['form'])){
    if(!$uS->logged){
        $error = "Unauthorized for page: Please login";
    }else{
        $id = filter_var($_GET["form"], FILTER_SANITIZE_NUMBER_INT);
        if($id > 0){
            $formDocument = new FormDocument();
            if($formDocument->loadDocument($dbh, $id)){
                $formData = $formDocument->getDoc();
                $style = "";
            }else{
                $error = "Document not found";
            }
        }else{
            $error = "No Referral form found";
        }
    }

}else if(isset($_POST['cmd']) && $_POST['cmd'] == "preview" && isset($_POST['formData']) && isset($_POST['style'])){

    if(!$uS->logged){
        $error = "Unauthorized for page: Please login";
    }else{
        $formData = urldecode($_POST['formData']);
        $style = $_POST['style'];
    }
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
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo BOOTSTRAP_CSS; ?>
        <?php echo FAVICON; ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo ADDR_PREFS_JS; ?>"></script>
        <script type="text/javascript" src="../js/formBuilder/form-render.min.js"></script>
        <?php
        if($enableRecaptcha){
            echo $recaptcha->getScriptTag();
        }
        ?>
        <script type='text/javascript'>
            $(document).ready(function() {

            	const formData = `<?php echo $formData; ?>`;
            	var genders = <?php echo json_encode($genders); ?>;
            	var patientRels = <?php echo json_encode($patientRels); ?>;
            	var vehicleStates = <?php echo json_encode($formattedStates); ?>;
            	var mediaSources = <?php echo json_encode($mediaSources); ?>;
            	var namePrefixes = <?php echo json_encode($namePrefixes); ?>;
            	var nameSuffixes = <?php echo json_encode($nameSuffixes); ?>;
				var hospitals = <?php echo json_encode($hospitalAr); ?>;
				var diagnosis = <?php echo json_encode($diagnosis); ?>;
				var locations = <?php echo json_encode($locations); ?>;

                const formRender = $('#formContent').formRender({
                	formData,
                	layoutTemplates: {
              			default: function(field, label, help, data) {
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
              				}else if(data.type == 'date'){
              					$(field).attr('type','text').attr('autocomplete', 'off')
              					if(data.name == 'patient.birthdate'){
              						$(field).datepicker({
                                        yearRange: '-99:+00',
                                        changeMonth: true,
                                        changeYear: true,
                                        autoSize: true,
                                        maxDate:0,
                                        dateFormat: 'M d, yy'
                                    });
              					}else{
              						$(field).datepicker();
              					}
              				}else if(data.type == 'select'){
              					if(data.dataSource){
                  					var options = {};
                  					switch(data.dataSource){
                  						case 'namePrefix':
                  							options = namePrefixes;
                  							break;
                  						case 'nameSuffix':
                  							options = nameSuffixes;
                  							break;
                  						case 'gender':
                  							options = genders;
                  							break;
                  						case 'patientRelation':
                  							options = patientRels;
                  							break;
                  						case 'mediaSource':
                  							options = mediaSources;
                  							break;
                  						case 'vehicleStates':
                  							options = vehicleStates;
                  							break;
                  						case 'hospitals':
                  							options = hospitals;
                  							break;
                  						case 'diagnosis':
                  							options = diagnosis;
                  							break;
                  						case 'unit':
                  							options = locations;
                  							break;
                  						default:
                  							options = {};
                  					}
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

            	var csrfToken = '<?php echo $login->generateCSRF(); ?>';
            	var siteKey = '<?php echo $recaptcha->getSiteKey(); ?>';
            	var recaptchaEnabled = '<?php echo $enableRecaptcha; ?>';

            	var $renderedForm = $(document).find('.rendered-form');
            	$renderedForm.addClass('row');

            	$renderedForm.find('input.hhk-zipsearch').data('hhkprefix', 'patient\\.address\\.').data('hhkindex','');

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
                    createZipAutoComplete($(this), 'ws_forms.php', lastXhr, null, csrfToken);
                });

                $renderedForm.find('.address').prop('autocomplete', 'search');

                //phone format
                verifyAddrs($renderedForm);

		$('input.form-control').blur(function(){
			var val = $(this).val().replaceAll('"', "'");
			$(this).val(val);
			console.log(val);
			console.log($(this).html());
		});
                //add guest button
                //var elements = $renderedForm.find('input[group=guest], select[group=guest]').parents('.field-container').remove();
                //console.log(elements);
                //index = 0;
                //$renderedForm.on('click', '#addGuest', function(){
	    		//	elements.each(function(){
	    		//		formgroup = $(this).clone();
	    		//		var fieldname = formgroup.find('input, select').prop('name')
	    		//		formgroup.find('input, select').prop('name', 'guests.g' + index + '.' + fieldname);
	    		//		formgroup.insertBefore('.field-addGuest');
	    		//	});
	    		//	index++;
            	//});

            	function submitForm(token = ''){
            		var spinner = $('<span/>').addClass("spinner-border spinner-border-sm");
            		$renderedForm.find('.submit-btn').prop('disabled','disabled').html(spinner).append(' Submitting...');

            		var formRenderData = formRender.userData;

					console.log(formRenderData);

            		$.ajax({
            	    	url : "ws_forms.php",
            	   		type: "POST",
            	    	data : {
            	    		cmd: "submitform",
            	    		formRenderData: JSON.stringify(formRenderData),
            	    		csrfToken: csrfToken,
            	    		recaptchaToken: token
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
            	    	    	$('.msg').show();
            	    	    	if(data.recaptchaScore){
            	    	    		$('.msg #recaptchascore').text(data.recaptchaScore);
            	    	    	}else{
            	    	    		$('.msg #recaptchascore').empty();
            	    	    	}
            	    	    	$('.errmsg').hide();
            	    	    }
            	    	    $('html, body').animate({scrollTop:$(document).height()}, 'slow');
            	    	}
            	    });
                }

            	$(document).on('submit', 'form', function(e){
            		e.preventDefault();
            		if(recaptchaEnabled){
            		    grecaptcha.execute(siteKey, {action: 'submit'}).then(function(token){
            		    	submitForm(token);
            		    });
            		}else{
            			submitForm();
            		}
            	});

            });
	</script>

	<style>
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

	</style>

    </head>
    <body>


    	<?php if(isset($_GET['form'])){ ?>
    	<fieldset disabled="disabled">
    	<?php }else{ ?>
    	<form action="#" method="POST" novalidate>
    	<?php } ?>
        <div id="formContent" class="container-fluid">
			<div style="text-align: center">
				<?php echo $error; ?>
			</div>
        </div>
        <div class="alert alert-success msg" role="alert" style="display: none">
    		<h4 class="alert-heading"><?php echo $successTitle; ?></h4>
    		<p class="successmsg"><?php echo $successContent; ?></p>
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
