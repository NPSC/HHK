<?php
require ("homeIncludes.php");

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title></title>
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
	<link rel="stylesheet" href="css/simple_editor.css">

        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="../js/wysiwyg.js"></script>
        <script type="text/javascript">
    $(document).ready(function(){
	$.fn.simpleEditor = function(){

		var options = {
			size : {
				width: "99%",
				height: "300px"
			},
			defaults: {
				"fontFamily": "Calibri"
			},
			css: {
				"border": "1px solid silver",
				"margin": "0 auto"
			}
		};

		var instance = $(this).wysiwyg(options);

		var $iframe = instance.getIframe();

		var theCont = $iframe.parent(".simpleEditorContainer");

		theCont.prepend('<div class="control-btn-wrapper"></div>');

		$(".control-btn-wrapper").append('<button class="control-btn bold-btn"><span class="fa fa-bold"></span></button>');
		$(".control-btn-wrapper").append('<button class="control-btn italic-btn"><span class="fa fa-italic"></span></button>');
		$(".control-btn-wrapper").append('<button class="control-btn underline-btn"><span class="fa fa-underline"></span></button>');

		$(".bold-btn").click(function(){
			instance.doAction("bold");
		});

		$(".italic-btn").click(function(){
			instance.doAction("italic");
		});

		$(".underline-btn").click(function(){
			instance.doAction("underline");
		});

	};

        $("#taformtext").simpleEditor();
});
        </script>
    </head>
    <body>
        <div class="container">

        <h1 style="margin:15px auto 30px auto;">Basic WYSIWYG Text Editor Demo</h1>

            <div class="simpleEditorContainer">

                <textarea id="taformtext" >hello</textarea>
            </div>
        </div>
    </body>
</html>
