<?php
register_shutdown_function( "fatal_handler" );
$errorMsg = "";
$success = "";

function fatal_handler() {
    $errfile = "unknown file";
    $errstr  = "shutdown";
    $errno   = E_CORE_ERROR;
    $errline = 0;

    $error = error_get_last();

    if( $error !== NULL) {
        $errno   = $error["type"];
        $errfile = $error["file"];
        $errline = $error["line"];
        $errstr  = $error["message"];

        //error_mail(format_error( $errno, $errstr, $errfile, $errline));
        formHandler($error);
        buildPage($error);
        
		die();
    }
}

function formHandler($error){
	if($_POST && isset($_POST['name'], $_POST['email'], $_POST['message'])) {

    $name = $_POST['name'];
    $email = $_POST['email'];
    $subject = $_POST['subject'];
    $message = "New bug report received\r\nName: " . $name . "\r\nEmail: " . $email . "\r\n";
    $message = "Message: " . $_POST['message'] . "\r\n";

	$message .= "File: " . $error["file"] . " line " . $error["line"] . "\r\nError: " . $error["message"];
      // send email and redirect
      $to = "wireland@nonprofitsoftwarecorp.org";
      $subject = "New bug report received";
      $headers = "From: noreply@wireland.me" . "\r\n";
      mail($to, $subject, $message, $headers);
      header('Location: /functions/errorsuccess.php');
      exit;
  }
}

function buildPage($error){
	$wInit = new webInit();
	$pageTitle = $wInit->pageTitle;
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
				        <img src="/HHK/images/hhkLogo.png">
				        <div class="logo-text">
				        	<p>Sometimes errors happen, help us stop them in their digital tracks by submitting a bug report.</p>
				        </div>
			        </div>
					<div class="col-6">
						<div class="ui-widget-header ui-state-default ui-corner-top">
							<h2>File a bug report</h2>
						</div>
						<div class="ui-widget-content ui-corner-bottom hhk-tdbox">
							<form action="<?PHP echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
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
						</div>
					</div>
		        </div>
		    </div>
	    </body>
	</html>
	
	<?php
}
?>