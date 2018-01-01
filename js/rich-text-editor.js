(function($) {

    $.fn.richTextEditor = function (options) {
        
        var defaults = {
                wrapperAttrs: {
                    class: 'rich-text-editor'
                },
                menuBarAttrs: {
                    class: 'rte-menus'
                },
                toolBarAttrs: {
                    class: 'rte-tools'
                },
                editBoxAttrs: {
                    class: 'rte-editbox'
                },
                modeBoxAttrs: {
                    class: 'rte-switchmode'
                },
                saveBtnAttrs: {
                    class: 'rte-Submit',
                    type: 'button',
                    title: 'Save Form',
                    value: 'Save',
                    style: "float:right;"
                },
                resetBtnAttrs: {
                    type: 'button',
                    title: 'Reset Form',
                    value: 'Reset',
                    style: "float:right;margin-right:.5em;"
                },
                formName: '',
                content: '',
                buttons: {},
                menus: {},
                onGet: function(){return 'Nothing to load.';},
                onSave: function(){},
                customCommands: {
                    "printDoc": function (oDoc) {
                        if (!validateMode(oDoc)) { return; }
                        var oPrntWin = window.open("","_blank","width=450,height=470,left=400,top=100,menubar=yes,toolbar=no,location=no,scrollbars=yes");
                        oPrntWin.document.open();
                        oPrntWin.document.write("<!doctype html><html><head><title>Print<\/title><\/head><body onload=\"print();\">" + oDoc.html() + "<\/body><\/html>");
                        oPrntWin.document.close();
                    },
                    "cleanDoc": function (oDoc) {
                        if (validateMode(oDoc) && confirm("Are you sure?")) { oDoc.html(""); };
                    },
                    "createLink": function (oDoc) {
                        var sLnk = prompt("Write the URL here", "http:\/\/");
                        if (sLnk && sLnk !== "http://"){ formatDoc(oDoc, "createlink", sLnk); }
                    }
                }
            };

        var settings = $.extend( true, {}, defaults, options );
        
        var $wrapper = $(this);
        
        var $editBox = createEditor($wrapper, settings);
        
        settings.content = settings.onGet.call();

        $wrapper.attr(settings.wrapperAttrs);
        
        $editBox.html(settings.content).prop('contentEditable', true);

        return this;
    };
    
    function formatDoc ($editBox, sCmd, sValue) {
        document.execCommand(sCmd, false, sValue);
        $editBox.focus();
    }

    function menuSelect ($editBox, $menu) {
        formatDoc($editBox, $menu.data('cmd'), $menu.val());
    }

    function buttonClick (customCommands, $editBox, $button) {
        var sCmd = $button.data('cmd');
        customCommands.hasOwnProperty(sCmd) ? customCommands[sCmd]($editBox) : formatDoc($editBox, sCmd, $button.attr('alt') || false);
    }

    function createMenuItem (sValue, sLabel) {
        return new Option(sLabel, sValue); ;
    }

    createMenuBar = function (menus) {
        
        var mBar = $("<div />");
        
        for (var $menu, oMenuOpts, vOpt, nMenu = 0; nMenu < menus.length; nMenu++) {

            $menu = $("<select />")
                    .data('cmd', menus[nMenu].command)
                    .append(createMenuItem('0', menus[nMenu].header))
                    .prop('selectedIndex', '0');

            oMenuOpts = menus[nMenu].values;

            if (oMenuOpts.constructor === Array) {
                for (vOpt = 0; vOpt < oMenuOpts.length; $menu.append(createMenuItem(oMenuOpts[vOpt], oMenuOpts[vOpt++])));
            } else {
                for (vOpt in oMenuOpts) { $menu.append(createMenuItem(vOpt, $('<div />').html(oMenuOpts[vOpt]).text())); }				
            }
            
            mBar.append($menu);
        }
        return mBar;

    };
    
    createToolBar = function(buttons) {

        var tBar = $("<div />");
        
        for (var oBtnDef, $button, nBtn = 0; nBtn < buttons.length; nBtn++) {

            oBtnDef = buttons[nBtn];
            $button = $('<img class="rte-button" />');

            $button.attr('src', oBtnDef.image);
            if (oBtnDef.hasOwnProperty("value")) { $button.attr('alt', oBtnDef.value); }
            $button.attr('title', oBtnDef.text);
            $button.data('cmd', oBtnDef.command);

            tBar.append($button);
        }

        return tBar;
    };
    
    function createEditor ($wrapper, settings) {
        var 
            $menuBar = createMenuBar(settings.menus)
                .attr(settings.menuBarAttrs)
                .appendTo($wrapper),
            $toolsBar = createToolBar(settings.buttons)
                .attr(settings.toolBarAttrs)
                .appendTo($wrapper), 
            $editBox = $("<div />")
                .attr(settings.editBoxAttrs)
                .appendTo($wrapper),
            $saveBtn = $('<input />').attr(settings.saveBtnAttrs),
            $resetBtn = $('<input />').attr(settings.resetBtnAttrs);


        $menuBar.on('change', 'select', function (){
            if ($(this).val() !== '0') {
                menuSelect($editBox, $(this));
                $(this).children('option:first-child').prop('selected', true);
            }
        });

        // Save button 
        $saveBtn.click(function (event){
            settings.onSave.call($editBox);
        });
        
        // Reset button 
        $resetBtn.click(function (event){
            $editBox.html(settings.content).prop('contentEditable', true);
        });
        
        $toolsBar.append($saveBtn).append($resetBtn);
        $toolsBar.on('click', 'img', function (){
            buttonClick(settings.customCommands, $editBox, $(this));
        });

        $wrapper.append($menuBar);
        $wrapper.append($toolsBar);
        $wrapper.append($editBox);

        return $editBox;
    }
    
}(jQuery));