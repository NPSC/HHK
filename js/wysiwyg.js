$(document).ready(function () {


    $.fn.wysiwyg = function (options) {

        var iframe = $('<iframe class="wysiwyg">');

        $(this).replaceWith(iframe);

        iframe[0].contentDocument.body.innerHTML = $(this).text();

        iframe[0].contentDocument.designMode = "On";

        if (iframe[0].contentWindow) {

            iframe[0].contentWindow.document.open();
            // optionally write content here
            iframe[0].contentWindow.document.close();
            iframe[0].contentWindow.document.designMode = "on";
            iframe[0].contentWindow.document.body.innerHTML = $(this).text();

        }

        iframe[0].contentDocument.execCommand('styleWithCSS', true, null);

        if (options.size) {
            
            if (options.size.height) {
                iframe.height(options.size.height);
            }

            if (options.size.width) {
                iframe.width(options.size.width);
            }
        }

        iframe[0].contentDocument.execCommand('enableObjectResizing', false, false);
        //iframe[0].contentDocument.execCommand('useCSS', true, null);




// defaults object
        if (options.defaults)
        {
            if (options.defaults.fontFamily)
            {
                iframe[0].contentDocument.body.style.fontFamily = options.defaults.fontFamily;
            }

            if (options.defaults.border)
            {
                iframe[0].style.border = options.defaults.border;
            }
        }


// CSS Object

        if (options.css)
        {
            iframe.css(options.css);
        }

        return new WYSIWYG(iframe);

    };

    function htmlEntities(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function WYSIWYG(iframe)
    {
        var editor = iframe[0].contentDocument;
        this.doAction = function (actionName, value) {
            if (value)
            {
                editor.execCommand(actionName, false, value);
            } else {
                iframe[0].contentDocument.execCommand(actionName, false, null);
            }
        };

        this.html = function ()
        {
            return iframe[0].contentDocument.body.innerHTML;
        };

        this.rawHTML = function ()
        {
            return htmlEntities(this.html());
        };

        this.getIframe = function ()
        {
            return iframe;
        };

    }

});

