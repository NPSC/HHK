<?php
register_shutdown_function( "fatal_handler" );

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
        
        buildPage($error);
		die();
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
	    </head>
	    <body>
		    <div style="text-align: center;">
		        <h1>Uh oh, something's not right!</h1>
		        <h2>Error Type: <?php echo $error["type"]; ?></h2>
		        <h2>File: <?php echo $error["file"]; ?></h2>
		        <h2>Line: <?php echo $error["line"]; ?></h2>
		        <h2>Error: <?php echo $error["message"]; ?></h2>
		    </div>
	    </body>
	</html>
	
	<?php
}
?>