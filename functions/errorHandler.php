<?php
register_shutdown_function( "fatal_handler" );
$errorMsg = "";

function fatal_handler() {
    $errfile = "unknown file";
    $errstr  = "shutdown";
    $errno   = E_CORE_ERROR;
    $errline = 0;

	//get error
    $error = error_get_last();

	//split error object into vars
    if( $error !== NULL) {
        $errno   = $error["type"];
        $errfile = $error["file"];
        $errline = $error["line"];
        $errstr  = $error["message"];

        formHandler($error);
        buildPage($error);

		die();
    }
}

function formHandler($error){
	$sec = new SecurityComponent;
	$uS = Session::getInstance();

	//get report email address
	$to = $uS->Error_Report_Email == "" ? "support@nonprofitsoftwarecorp.org": $uS->Error_Report_Email;

	//if post data exists, send email
	if($_POST && isset($_POST['name'], $_POST['email'], $_POST['message'])) {

    	$name = $_POST['name'];
		$email = $_POST['email'];
		$message = "New bug report received from " . $uS->siteName . "\r\n\r\n";
		$message.= "Name: " . $name . "\r\n\r\n";
		$message.= "Email: " . $email . "\r\n\r\n";
		$message.= "Message: " . $_POST['message'] . "\r\n\r\n";
		$message.= "File: " . $error["file"] . " line " . $error["line"] . "\r\n\r\n";
		$message.= "Error: " . $error["message"];
      	$subject = "New bug report received from " . $uS->siteName;
	  	$headers = "From: BugReporter<noreply@nonprofitsoftwarecorp.org>" . "\r\n";
	  	// send email and redirect
	  	mail($to, $subject, $message, $headers);
	  	header("location: ?status=success");
	  	exit;
	}
}

function buildPage($error){
	$wInit = new webInit();
	$pageTitle = $wInit->pageTitle;
	$sec = new SecurityComponent;
	?>

	<!DOCTYPE html>
	<html lang="en">
	    <head>
	        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	        <title><?php echo $pageTitle; ?></title>
	        <meta http-equiv="x-ua-compatible" content="IE=edge">
            <?php echo JQ_UI_CSS; ?>
	        <?php echo MULTISELECT_CSS; ?>
	        <?php echo HOUSE_CSS; ?>
	        <?php echo JQ_DT_CSS; ?>
	        <?php echo NOTY_CSS; ?>
	        <?php echo FAVICON; ?>

	        <style>
		        .wrapper {
			        margin-top: 2em;
		        }
		        .container {
			        width: 1140px;
		        }

		        .col-6 {
			        width: 45%;
			        display: inline-block;
		        }

		        h1 {
			        text-align: center;
			        margin-bottom: 1em;
		        }

		        h2 {
			        margin: .2em;
		        }
		        
		        h4,.ui-button {
			        margin: 1em;
		        }

		        form input,textarea {
			        display: block;
			        width: 95%;
			        padding: .5em;
			        font-size: 1em;
			        border-radius: 5px;
			        border: 1px solid #ccc;
		        }

		        .form-input {
			        margin: 1em;
			        text-align: center;
		        }

		        .logo-text {
			        width: 45%;
			        display: block;
			        margin: 1em auto;
		        }
	        </style>
	    </head>
	    <body>
		    <div class="wrapper">
		        <h1>Uh oh, something's not right!</h1>
		        <div class="container">
			        <div class="col-6" style="text-align: center;">
				        <img src="<?php echo $sec->getRootURL(); ?>images/hhkLogo.png">
				        <div class="logo-text">
				        	<p>Sometimes errors happen, help us stop them in their digital tracks by submitting a bug report.</p>
				        </div>
			        </div>
					<div class="col-6">
						<div class="ui-widget-header ui-state-default ui-corner-top">
							<h2>File a bug report</h2>
						</div>
						<div class="ui-widget-content ui-corner-bottom hhk-tdbox">
							<?php if(isset($_GET["status"]) && $_GET["status"] == "success"){ ?>
								<h4>Thanks for submitting!</h4>
								<a href="<?php echo $sec->getRootURL(); ?>" class="ui-button ui-corner-all ui-widget">Go Home</a>
							<?php }else{ ?>
							<form action="#" method="POST">
								<div class="form-input">
									<input type="text" name="name" placeholder="Name">
								</div>
								<div class="form-input">
									<input type="email" name="email" placeholder="Email Address">
								</div>
								<div class="form-input">
									<textarea name="message" placeholder="What were you doing before you got here?"></textarea>
								</div>
								<div class="form-input">
									<input type="submit" class="ui-button ui-corner-all ui-widget" value="Submit" style="width: initial;">
								</div>
							</form>
							<?php } ?>
						</div>
					</div>
		        </div>
		    </div>
	    </body>
	</html>

	<?php
}
?>