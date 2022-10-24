<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $wInit->pageTitle; ?></title>
        <meta http-equiv="x-ua-compatible" content="IE=edge">

		<%= htmlWebpackPlugin.tags.headTags %>

		<?php echo (isset($wInit->template->head) ? $wInit->template->head: ""); ?>

		<?php
		  if(isset($wInit->template->inlineJS)){
		      echo '<script type="text/javascript">window.addEventListener("DOMContentLoaded", function(){' . $wInit->template->inlineJS . '});</script>';
		  }
		?>

        <style>
            <?php echo (isset($wInit->template->headStyles) ? $wInit->template->headStyles: ""); ?>
        </style>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";}?> >
    	<%= htmlWebpackPlugin.tags.bodyTags %>
        <?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
			<?php echo (isset($wInit->template->contentDiv) ? $wInit->template->contentDiv: ""); ?>
        </div>  <!-- div id="contentDiv"-->
        <?php echo (isset($wInit->template->footer) ? $wInit->template->footer: ""); ?>
    </body>
</html>