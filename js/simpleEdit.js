$(document).ready(function(){
$.fn.simpleEditor = function(){

    var options = {
        size : {
            width: "99%",
            height: "600px"
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
    $(".control-btn-wrapper").append('<button class="control-btn bold-btn"><i class="fas fa-bold"></i></button>');
    $(".control-btn-wrapper").append('<button class="control-btn italic-btn"><i class="fas fa-italic"></i></button>');
    $(".control-btn-wrapper").append('<button class="control-btn underline-btn"><i class="fas fa-underline"></i></button>');
    $(".control-btn-wrapper").append('<button class="control-btn unOrderedList-btn" title="Insert unorderd list"><i class="fas fa-list-ul"></i></button>');
    $(".control-btn-wrapper").append('<button class="control-btn indent-btn" title="Indent text"><i class="fas fa-indent"></i></button>');
    $(".control-btn-wrapper").append('<button class="control-btn outdent-btn" title="Outdend text"><i class="fas fa-outdent"></i></button>');
    $(".control-btn-wrapper").append('<button class="control-btn justifyLeft-btn" title="Align Left"><i class="fas fa-align-left"></i></button>');
    $(".control-btn-wrapper").append('<button class="control-btn justifyRight-btn" title="Align Right"><i class="fas fa-align-right"></i></button>');
    $(".control-btn-wrapper").append('<button class="control-btn justifyCenter-btn" title="Align Center"><i class="fas fa-align-center"></i></button>');
    $(".control-btn-wrapper").append('<button class="control-btn justifyFull-btn" title="Justify"><i class="fas fa-align-justify"></i></button>');
    $(".control-btn-wrapper").append('<button class="control-btn subscript-btn" title="Subscript"><i class="fas fa-subscript"></i></button>');
    $(".control-btn-wrapper").append('<button class="control-btn superscript-btn" title="Superscript"><i class="fas fa-superscript"></i></button>');
    $(".control-btn-wrapper").append('<button class="control-btn undo-btn" title="Undo"><i class="fas fa-undo"></i></button>');
    
    $(".control-btn-wrapper").append('<button class="control-btn save-btn"><i class="fas fa-save fa-lg"></i></button>');
    $(".control-btn-wrapper").append('<span id="spnMsg"></span>');
    $('.save-btn').click(function () {
        var text = {'tx': instance.html()};
        $.post('Configure.php', text, function() {
            $('#spnMsg').text('File Saved.')
        });
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
    $(".undo-btn").click(function(){
        instance.doAction("undo");
    });
};
});

