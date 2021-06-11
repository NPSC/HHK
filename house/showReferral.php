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
$formTemplate = '';
$formData = '';
$style = '';
$error = '';

if(isset($_GET['template'])){
    $id = filter_var($_GET["template"], FILTER_SANITIZE_NUMBER_INT);
    if($id > 0){
        $formTemplate = new FormTemplate();
        if($formTemplate->loadTemplate($dbh, $id)){
            $formData = $formTemplate->getTemplate();
            $style = $formTemplate->getStyle();
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
        <title>Referral Form</title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo BOOTSTRAP_CSS; ?>
        <?php echo FAVICON; ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="../js/formBuilder/form-render.min.js"></script>
        <?php
        if($uS->mode == 'demo' || $uS->mode == 'prod'){
            echo $recaptcha->getScriptTag();
        }
        ?>
        <script type='text/javascript'>
$(document).ready(function() {

	const formData = `<?php echo $formData; ?>`;

    const formRender = $('#formContent').formRender({
    	formData,
    	layoutTemplates: {
  			default: function(field, label, help, data) {
  				help = $('<div/>').addClass('validationText').attr("data-field", data.id);
    			return $('<div/>').addClass(data.width + " form-group").append(label, field, help);
  			}
		},
    	"i18n":{
			"location":"../js/formBuilder"
		}
	});
	
	$(document).find('.rendered-form').addClass('row');
	$('.ckdate').datepicker();
	
	var genders = <?php echo json_encode($genders); ?>;
	var patientRels = <?php echo json_encode($patientRels); ?>;
	var genderLabel = $(document).find('.rendered-form label[for=patientSex]').text();
	$(document).find('.rendered-form #patientSex').html('<option disabled selected>' + genderLabel + '</option>');
	for(i in genders){
		$(document).find('.rendered-form #patientSex').append('<option value="' + genders[i].Code + '">' + genders[i].Description + '</option>');
	}
	for(i in patientRels){
		$(document).find('.rendered-form #familyRelationship').append('<option value="' + patientRels[i].Code + '">' + patientRels[i].Description + '</option>');
	}
	

	var csrfToken = '<?php echo $login->generateCSRF(); ?>';
	var siteKey = '<?php echo $recaptcha->getSiteKey(); ?>';
	var recaptchaEnabled = <?php echo ($uS->mode == 'demo' || $uS->mode == 'live' ? 'true':'false');?>;
	
	function submitForm(token = ''){
		var formRenderData = formRender.userData;
		
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
	    	    
	    	    if(data.errors){
	    	    	$.each(data.errors, function(key, error){
	    	    		if(key == 'server'){
	    	    			$('#errorcontent').text(error);
	    	    			$('.errmsg').show();
	    	    		}else{
	    	    			$('input[name="' + error.field + '"]').addClass('is-invalid');
	    	    			$('.validationText[data-field="' + error.field + '"').addClass('invalid-feedback').text(error.error);
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
	    	    	$('html, body').animate({scrollTop:$(document).height()}, 'slow');
	    	    }
	    	}
	    });
    }
	
	$(document).on('submit', 'form', function(e){
		e.preventDefault();
		if(recaptchaEnabled){
		    grecaptcha.execute(siteKey, {action: 'submit'}).then(submitForm(token));
		}else{
			submitForm();
		}
	});
	
});
</script>

	<style>
	   <?php echo $style; ?>
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
    		<h4 class="alert-heading">Referral Form Submitted</h4>
    		<p>We've received your referral form and will be in touch shortly.</p>
    		<p>
    			Thank you
    		</p>
    		<p>Recaptcha Score: <span id="recaptchascore"></span></p>
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
