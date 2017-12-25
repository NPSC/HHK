<?php
require ("homeIncludes.php");

if (isset($_POST['tx'])) {

    $agreemt = filter_input(INPUT_POST, 'tx');

    file_put_contents('../conf/agreement.html', $agreemt);
}

include ('../conf/regSections.php');

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title></title>
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />

	<link rel="stylesheet" href="css/simple_editor.css">

        <?php echo JQ_UI_CSS; ?>

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
            $(".control-btn-wrapper").append('<button class="control-btn unOrderedList-btn" title="Insert unorderd list"><span class="fa fa-list-ul"></span></button>');
            $(".control-btn-wrapper").append('<button class="control-btn indent-btn" title="Indent text"><span class="fa fa-indent"></span></button>');
            $(".control-btn-wrapper").append('<button class="control-btn outdent-btn" title="Outdend text"><span class="fa fa-outdent"></span></button>');
            $(".control-btn-wrapper").append('<button class="control-btn justifyLeft-btn" title="Align Left"><span class="fa fa-align-left"></span></button>');
            $(".control-btn-wrapper").append('<button class="control-btn justifyRight-btn" title="Align Right"><span class="fa fa-align-right"></span></button>');
            $(".control-btn-wrapper").append('<button class="control-btn justifyCenter-btn" title="Align Center"><span class="fa fa-align-center"></span></button>');
            $(".control-btn-wrapper").append('<button class="control-btn justifyFull-btn" title="Justify"><span class="fa fa-align-justify"></span></button>');
            $(".control-btn-wrapper").append('<button class="control-btn subscript-btn" title="Subscript"><span class="fa fa-subscript"></span></button>');
            $(".control-btn-wrapper").append('<button class="control-btn superscript-btn" title="Superscript"><span class="fa fa-superscript"></span></button>');
            $(".control-btn-wrapper").append('<button class="control-btn undo-btn" title="Undo"><span class="fa fa-undo"></span></button>');
            $(".control-btn-wrapper").append('<button class="control-btn increaseFontSize-btn" title="Increase Font Size"><span class="fa fa-expand"></span></button>');
            $(".control-btn-wrapper").append('<button class="control-btn decreaseFontSize-btn" title="Decrease Font Size"><span class="fa fa-compress"></span></button>');

            theCont.append('<button class="save-btn">Save</button>');
            $('.save-btn').click(function () {
                var text = {'tx': instance.html()};
                $.post('wysiEdit.php', text);

            });


            $(".bold-btn").click(function(){
                    instance.doAction("bold");
            });

            $(".italic-btn").click(function(){
                    instance.doAction("italic");
            });

            $(".underline-btn").click(function(){
                    instance.doAction("underline");
            });

            $(".unOrderedList-btn").click(function(){
                    instance.doAction("insertUnorderedList");
            });

            $(".indent-btn").click(function(){
                    instance.doAction("indent");
            });

            $(".outdent-btn").click(function(){
                    instance.doAction("outdent");
            });

            $(".justifyLeft-btn").click(function(){
                    instance.doAction("justifyLeft");
            });

            $(".justifyRight-btn").click(function(){
                    instance.doAction("justifyRight");
            });

            $(".justifyCenter-btn").click(function(){
                    instance.doAction("justifyCenter");
            });

            $(".justifyFull-btn").click(function(){
                    instance.doAction("justifyFull");
            });

            $(".subscript-btn").click(function(){
                    instance.doAction("subscript");
            });

            $(".superscript-btn").click(function(){
                    instance.doAction("superscript");
            });

            $(".increaseFontSize-btn").click(function(){
                    instance.doAction("increaseFontSize");
            });

            $(".decreaseFontSize-btn").click(function(){
                    instance.doAction("decreaseFontSize");
            });

            $(".undo-btn").click(function(){
                    instance.doAction("undo");
            });


    };

    $("#taformtext").simpleEditor();

});
        </script>
    </head>
    <body>
        <div class="container">

        <h1 style="margin:15px auto 30px auto;">Registration Document Agreement Section</h1>

            <div class="simpleEditorContainer">

                <textarea id="taformtext" name="taformtext" ><?php echo $instructions; ?></textarea>
                

            </div>
        </div>
    </body>
</html>
