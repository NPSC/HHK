<?php

use HHK\sec\{Session, Labels};
use HHK\sec\Login;
use HHK\sec\ScriptAuthClass;
use HHK\sec\Recaptcha;

/**
 * shwoReferral.php
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
    $formData = json_decode(base64_decode($_POST['formData']));
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
		<script type="text/javascript" src="<?php echo HTMLENTITIES_JS; ?>"></script>
		<script type="text/javascript" src="<?php echo BUFFER_JS; ?>"></script>
        <script type="text/javascript" src="../js/formBuilder/form-render.min.js"></script>
		<script type="text/javascript" src="<?php echo REFERRAL_FORM_JS; ?>"></script>

        <script type='text/javascript'>
			var referralFormVars = {
				formDataStr: JSON.stringify(<?php echo json_encode($formData); ?>),
				method: '<?php echo $method; ?>',
				cmd: '<?php echo $cmd; ?>',
				id: '<?php echo $id; ?>',
				initialGuests: '<?php echo (isset($initialGuests) ? $initialGuests: 0); ?>',
				maxGuests: '<?php echo (isset($maxGuests) ? $maxGuests: 0); ?>',
				template: <?php echo (isset($_GET['template']) ? $_GET['template'] : 0); ?>
			};
		</script>
    </head>
    <body>


    	<?php if(isset($_GET['form'])){ ?>
    	<fieldset disabled="disabled">
    	<?php }else{ ?>
    	<form action="#" method="POST" novalidate id="renderedForm">
    	<?php } ?>
        <div id="formContent" class="container-fluid">
			<div style="background: url(../images/ui-anim_basic_16x16.gif) center no-repeat; width: 100%; height: 10em;"></div>
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
