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
use HHK\sec\Login;
use HHK\sec\ScriptAuthClass;
use HHK\sec\SecurityComponent;
use HHK\sec\Pages;

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

$genders = readGenLookupsPDO($dbh, 'gender', 'Order');
unset($genders['z']);
$patientRels = readGenLookupsPDO($dbh, 'Patient_Rel_Type', 'Order');
unset($patientRels['slf']);
$formTemplate = '';
$formData = '';
$style = '';
$error = '';

if(isset($_GET['form'])){
    $id = filter_var($_GET["form"], FILTER_SANITIZE_NUMBER_INT);
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
        <script type='text/javascript'>
$(document).ready(function() {

	const formData = `<?php echo $formData; ?>`;

    $('#formContent').formRender({
    	formData,
    	layoutTemplates: {
  			default: function(field, label, help, data) {
    			return $('<div/>').addClass(data.width + " form-group").append(label, field, help);
  			}
		},
    	"i18n":{
			"location":"../js/formBuilder"
		}
	});
	$(document).find('.rendered-form').addClass('row');
	
	var genders = <?php echo json_encode($genders); ?>;
	var patientRels = <?php echo json_encode($patientRels); ?>;
	for(i in genders){
		$(document).find('.rendered-form #patientSex').append('<option value="' + genders[i].Code + '">' + genders[i].Description + '</option>');
	}
	for(i in patientRels){
		$(document).find('.rendered-form #familyRelationship').append('<option value="' + patientRels[i].Code + '">' + patientRels[i].Description + '</option>');
	}
	
});
</script>

	<style>
	   <?php echo $style; ?>
	</style>

    </head>
    <body>
        <div id="formContent" class="container-fluid">
			<div style="text-align: center">
				<?php echo $error; ?>
			</div>
        </div>
    </body>
</html>
