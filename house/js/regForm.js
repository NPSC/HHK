// page setup
function setupRegForm(idReg, rctMkup, regMarkup, payId, invoiceNumber, vid, rid, primaryGuestId, idPsg){
    
    var opt = {mode: 'popup',
        popClose: true,
        popHt      : $('div.PrintArea').height(),
        popWd      : 950,
        popX       : 20,
        popY       : 20,
        popTitle   : 'Registration Form',
        extraHead  : $('#regFormStyle').prop('outerHTML')};

    $('#mainTabs').tabs();

    $('.btnPrint').click(function() {
        opt.popHt = $(this).closest('.ui-tabs-panel').find('div.PrintArea').height();
        opt.popTitle = $(this).data('title');
        $(this).closest('.ui-tabs-panel').find('div.PrintArea').printArea(opt);
    }).button();

    $('.btnSave').click(function(){
        var loading = $('<div/>').addClass('hhk-loading-btn');
        var isSigned = ($(this).closest('.ui-tabs-panel').find("div.PrintArea .signDate:visible").length > 0);

    	if(!isSigned){
    		flagAlertMessage("<strong>Error:</strong> At least one signature is required", true);
    		return;
        }
        
        var regFormFields = [];

        //gather input fields
        $(this).parent(".ui-tabs-panel").find(".regFormInput").each(function (i, element) {
            var val = $(this).val();
            var field = { class: "regFormInput", uniqId: i, val: val };

            field.type = ($(this).data("inputtype") != undefined ? $(this).data("inputtype") : "text");
            field.height = ($(this).data("inputtype") == "textarea" ? $(this).height(): undefined);

            regFormFields.push(field);
        });

        //append signatures
        regFormFields = regFormFields.concat(window.regFormSignatures);

        $('.btnSave').prop('disabled', 'disabled').html(loading);

        try{
            var docCode = $(this).data("tab");
            $(this).closest('.ui-tabs-panel').find("div.PrintArea .btnSign, div.PrintArea .btnInitial").remove();

            var formData = new FormData();
            formData.append('cmd', 'saveRegForm');
            formData.append('guestId', primaryGuestId);
            formData.append('psgId', idPsg);
            formData.append('idVisit', vid);
            formData.append('idResv', rid);
            formData.append('docTitle', "Registration Form");
            formData.append('uuid', $(this).data('uuid'));
            formData.append('formCode', docCode);
            formData.append('docSignatures', JSON.stringify(regFormFields));

            $.ajax({
                url: 'ws_ckin.php',
                dataType: 'JSON',
                type: 'post',
                data: formData,
                contentType: false,
                processData: false,
                success: function (data) {
                    if (data.idDoc > 0) {
                        flagAlertMessage("<strong>Success:</strong> Registration form saved successfully", false);
                        $(".btnSave").hide();
                    } else {
                        if (data.error) {
                            flagAlertMessage("<strong>Error: </strong>" + data.error, true);
                        }
                        $('.btnSave').prop('disabled', false).text("Save");
                    }
                },
                error: function(xhr, errorText, errorThrown){
                    flagAlertMessage("<strong>Error:</strong> A server error has occurred - " + errorText, true);
                    $('.btnSave').prop('disabled', false).text("Save");
                }
            });
        }catch(e){
            flagAlertMessage("<strong>Error:</strong> " + e.message, true);
            $('.btnSave').prop('disabled', false).text("Save");
            if(typeof hhkReportError == "function"){
                var errorInfo = {
                    stackTrace: e.stack,
                    source:"saveRegForm",
                    idResv:"<?php echo $idResv; ?>",
                    idVisit:"<?php echo $idVisit; ?>"
                }
                errorInfo = btoa(JSON.stringify(errorInfo));
                hhkReportError(e.message, errorInfo);
            }
        }

    }).button();

    $('#btnReg').click(function() {
        getRegistrationDialog(idReg);
    }).button();

    $('#btnStmt').click(function() {
        window.open('ShowStatement.php?vid=' + vid, '_blank');
    }).button();

    $('#pmtRcpt').dialog({
        autoOpen: false,
        resizable: true,
        width: getDialogWidth(530),
        modal: true,
        title: 'Payment Receipt'
    });

    if (rctMkup !== '') {
        showReceipt('#pmtRcpt', rctMkup, 'Payment Receipt');
    }
    if (regMarkup) {
        showRegDialog(regMarkup, idReg);
    }

    if (payId && payId > 0) {
        reprintReceipt(payId, '#pmtRcpt');
    }

    if (invoiceNumber && invoiceNumber !== '') {
        window.open('ShowInvoice.php?invnum=' + invoiceNumber);
    }

    $("#regTabDiv .checkboxWrapper").on("click", function () {
        if ($(this).hasClass("bi-square")) {
            $(this).find("input").val("true");
            $(this).removeClass("bi-square").addClass("bi-check-square-fill");
        } else if ($(this).hasClass("bi-check-square-fill")) {
            $(this).find("input").val("false");
            $(this).removeClass("bi-check-square-fill").addClass("bi-square");
        }
    });

    $('#mainTabs').show();
    $('#regTabDiv, #signedRegTabDiv').tabs();

};

function loadSignatures(signatures){
    $('#vsignedReg .btnSign, #vsignedReg .btnInitial').hide();

    try{
        for( docId in signatures){
            
            //get regforminputs
            var regFormInputs = $('#vsignedReg #' + docId + ' .regFormInput').attr("disabled", true);

            signatures[docId].forEach(element => {
                if (element.type == "signature" && element.uniqId !== undefined) { // if is agreement signature
                    var signWrapper = $(regFormInputs[element.uniqId]).parents(".signWrapper");
                    if (element.val.length > 0) {
                        signWrapper.find('.sigLine img').prop('src', element.val).show();
                        signWrapper.find('.signDate').show();
                    }
                }else if (element.class == "regFormInput" && element.uniqId !== undefined) { //other agreement fields
                    $(regFormInputs[element.uniqId]).val(element.val);

                    if (element.type == "checkbox" && element.val == "true") {
                        var wrapper = $(regFormInputs[element.uniqId]).parents(".checkboxWrapper");
                        wrapper.removeClass("bi-square").addClass("bi-check-square-fill");
                    }

                    if (element.height !== undefined) {
                        $(regFormInputs[element.uniqId]).height(element.height);
                    }

                } else if (element.idName) { //original/default signature lines
                    $('#' + docId + ' .signWrapper[data-idname=' + element.idName + '] .sigLine img').prop('src', element.signature).show();
                    $('#' + docId + ' .signWrapper[data-idname=' + element.idName + '] .signDate').show();
                }
            });
        };
    }catch(e){
        flagAlertMessage("Failed to load registration form fields: "+ e.message, true);
    }

}

// esign JS
function setupEsign(){

    window.regFormSignatures = [];

    $("#jSignDialog").dialog({
    	autoOpen: false,
    	width: getDialogWidth(800),
    	modal: true,
    	buttons: {
            "Clear": function(){
                var idName = $(this).find("input#idName").val();
                var formCode = $(this).find("input#formCode").val();
                var uniqId = $(this).find("input#idBtn").val();
                $(this).find(".signature").jSignature('clear');
                window.regFormSignatures.some((sig, i)=>{
                    if(sig.formCode == formCode && sig.idName == idName && sig.uniqId == uniqId){
                        window.regFormSignatures.splice(i, 1);
                        return true;
                        
                    }
                });
                $("#" + formCode + " .signWrapper[data-uniqid=" + uniqId + "] .sigLine img").attr("src", "").hide();
                $("#" + formCode + " .signWrapper[data-uniqid=" + uniqId + "] .sigLine input.regFormInput").val("");
                $("#" + formCode + " .signWrapper[data-uniqid=" + uniqId + "] .signDate").hide();
            },
            "Sign": function() {
            	var idName = $(this).find("input#idName").val();
            	var formCode = $(this).find("input#formCode").val();
                var signature = "data:" + $(this).find('.signature').jSignature("getData", "svgbase64").join(",");
                var uniqId = $(this).find("input#idBtn").val();
                if (idName > 0) {
                    window.regFormSignatures.push({formCode: formCode, idName: idName, uniqId: uniqId, signature: signature});
                }
                $("#" + formCode + " .signWrapper[data-uniqid=" + uniqId + "] .sigLine img").attr("src", signature).show();
                $("#" + formCode + " .signWrapper[data-uniqid=" + uniqId + "] .sigLine input.regFormInput").val(signature);
            	$("#" + formCode + " .signWrapper[data-uniqid=" + uniqId + "] .signDate").show();

                $(this).dialog("close");
            }
        }
    });

    $("#jSignDialog .signature").jSignature({"width": "175px", "height": "141px"});

    $("#topazDialog").dialog({
    	autoOpen: false,
    	width: getDialogWidth(550),
    	modal: true,
    	buttons: {
            "Clear": function(){
                var idName = $(this).find("input#idName").val();
                var formCode = $(this).find("input#formCode").val();
                var uniqId = $(this).find("input#idBtn").val();
                onClear();
                window.regFormSignatures.some((sig, i)=>{
                    if(sig.formCode == formCode && sig.idName == idName && sig.uniqId == uniqId){
                        window.regFormSignatures.splice(i, 1);
                        return true;
                        
                    }
                });
                $("#" + formCode + " .signWrapper[data-uniqid=" + uniqId + "] .sigLine img").attr("src", "").hide();
                $("#" + formCode + " .signWrapper[data-uniqid=" + uniqId + "] .sigLine input.regFormInput").val("");
                $("#" + formCode + " .signWrapper[data-uniqid=" + uniqId + "] .signDate").hide();
            },
            "Sign": function() {
            	var idName = $(this).find("input#idName").val();
                var formCode = $(this).find("input#formCode").val();
                var uniqId = $(this).find("input#idBtn").val();
                if(onDone() != false){
                    var signature = $(this).find("canvas#sigImg")[0].toDataURL("image/png");
                    if (idName > 0) {
                        window.regFormSignatures.push({ formCode: formCode, idName: idName, uniqId: uniqId, signature: signature });
                    }
                    $("#" + formCode + " .signWrapper[data-uniqid=" + uniqId + "] .sigLine img").attr("src", signature).show();
                    $("#" + formCode + " .signWrapper[data-uniqid=" + uniqId + "] .sigLine input.regFormInput").val(signature);
                    $("#" + formCode + " .signWrapper[data-uniqid=" + uniqId + "] .signDate").show();
                    $(this).dialog("close");
                }else{
                    showAlert("Please sign before continuing", false, false);
                }
            }
        },
        open: function () {
        	initTopaz();
        },
        close: function () {
            //gracefully close Topaz
            close();
            clearInterval(tmr);
        }
    });

    $(".btnSign, .btnInitial").uniqueId().on("click", function(){
    	let eSignMethod = $(this).data('esign');
        let name = $(this).closest(".row").find(".printName").text();
        let uniqId = $(this).attr("id");
        $(this).parents(".signWrapper").attr("data-uniqid", uniqId);

    	if(eSignMethod === 'jSign'){
            $("#jSignDialog input#idName").val($(this).closest(".signWrapper").data("idname"));
            $("#jSignDialog input#formCode").val($(this).closest(".ui-tabs-panel").attr('id'));
            $("#jSignDialog input#idBtn").val(uniqId);
            $("#jSignDialog").find(".signature").jSignature('clear');
            if ($(this).hasClass("btnInitial")) {
                $("#jSignDialog .signature").empty().jSignature({"width": "175px", "height": "141px"});
                $("#jSignDialog").dialog("option", "title", "Initial").dialog("option", "width", getDialogWidth(240)).dialog("open");
            } else {
                $("#jSignDialog .signature").empty().jSignature({"width": "750px", "height": "141px"});
                $("#jSignDialog").dialog("option", "title", "Signature: " + name).dialog("option", "width", getDialogWidth(800)).dialog("open");
            }
        }else if(eSignMethod === 'topaz'){
            $("#topazDialog input#idName").val($(this).closest(".signWrapper").data("idname"));
            $("#topazDialog input#formCode").val($(this).closest(".ui-tabs-panel").attr('id'));
            $("#topazDialog input#idBtn").val(uniqId);
            if ($(this).hasClass("btnInitial")) {
                $("#topazDialog #sigImg").attr("width", "150");
                $("#topazDialog").dialog("option", "title", "Initial").dialog("option", "width", getDialogWidth(225)).dialog("open");
            } else {
                $("#topazDialog #sigImg").attr("width", "500");
                $("#topazDialog").dialog("option", "title", "Signature: " + name).dialog("option", "width", getDialogWidth(550)).dialog("open");
            }

	    }
    });

    $(document).on("input", "textarea.regFormInput", function () {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight + 3) + 'px';
    });

    //start topaz code
    var tmr;

    var resetIsSupported = false;
    var SigWeb_1_6_4_0_IsInstalled = false; //SigWeb 1.6.4.0 and above add the Reset() and GetSigWebVersion functions
    var SigWeb_1_7_0_0_IsInstalled = false; //SigWeb 1.7.0.0 and above add the GetDaysUntilCertificateExpires() function

    function showAlert(msg, showInstallBtn = false, hideSigBox = true){
        let alertContainer = $("#sigWebAlert");
        let alertMsg = alertContainer.find("#alertMessage");
        let installSigWebBtn = $("<a></a>").prop("href", "https://www.topazsystems.com/software/sigweb.exe").text("Install SigWeb").button();

        if(msg){
            alertMsg.html("<p>" + msg + "</p>");
        }
        if(showInstallBtn){
            alertMsg.append(installSigWebBtn);
        }

        if(hideSigBox){
            $("#sigImg").hide();
        }
        alertContainer.show();
    }

    function initTopaz(){
        $("#sigWebAlert").hide();
        
    	if(IsSigWebInstalled()){
    	    var sigWebVer = "";
    	    try{
    	        sigWebVer = GetSigWebVersion();
    	    } catch(err){
                console.log("Unable to get SigWeb Version: "+err.message);
            }

            if(sigWebVer !== ""){
                try {
                    SigWeb_1_7_0_0_IsInstalled = isSigWeb_1_7_0_0_Installed(sigWebVer);
    		} catch( err ){
                    console.log(err.message);
                }
    		//if SigWeb 1.7.0.0 is installed, then enable corresponding functionality
    		if(SigWeb_1_7_0_0_IsInstalled){
    		    resetIsSupported = true;
                    ClearTablet();
                    onSign();
    		} else {
    		    try{
                        SigWeb_1_6_4_0_IsInstalled = isSigWeb_1_6_4_0_Installed(sigWebVer);
    			//if SigWeb 1.6.4.0 is installed, then enable corresponding functionality
                    } catch( err ){
                        console.log(err.message);
                    }
                    if(SigWeb_1_6_4_0_IsInstalled){
                        resetIsSupported = true;
                        showAlert("SigWeb 1.6.4 is installed. Please install the latest version: ", true);
                    } else{
                        showAlert("A newer version of SigWeb is available. Please install the latest version: ", true);
                    }
                }
            } else{
                //Older version of SigWeb installed that does not support retrieving the version of SigWeb (Version 1.6.0.2 and older)
    		showAlert("A newer version of SigWeb is available. Please install the latest version: ", true);
            }
    	}else{
            showAlert("Unable to communicate with SigWeb, please make sure it is installed and running ", true);
    	}
    }

    function isSigWeb_1_6_4_0_Installed(sigWebVer){
        var minSigWebVersionResetSupport = "1.6.4.0";

        if(isOlderSigWebVersionInstalled(minSigWebVersionResetSupport, sigWebVer)){
            console.log("SigWeb version 1.6.4.0 or higher not installed.");
            return false;
        }
        return true;
    }

    function isSigWeb_1_7_0_0_Installed(sigWebVer) {
        var minSigWebVersionGetDaysUntilCertificateExpiresSupport = "1.7.0.0";

    	if(isOlderSigWebVersionInstalled(minSigWebVersionGetDaysUntilCertificateExpiresSupport, sigWebVer)){
            console.log("SigWeb version 1.7.0.0 or higher not installed.");
            return false;
        }
        return true;
    }

    function isOlderSigWebVersionInstalled(cmprVer, sigWebVer){
        return isOlderVersion(cmprVer, sigWebVer);
    }

    function isOlderVersion (oldVer, newVer) {
        const oldParts = oldVer.split('.');
        const newParts = newVer.split('.');
        for (var i = 0; i < newParts.length; i++) {
            const a = parseInt(newParts[i]) || 0;
            const b = parseInt(oldParts[i]) || 0;
            if (a < b) return true;
            if (a > b) return false;
        }
        return false;
    }

    function onSign(){
        if(IsSigWebInstalled()){
            $("#sigImg").show();
            var ctx = document.getElementById('sigImg').getContext('2d');
            SetDisplayXSize( $("#sigImg")[0].width );
            SetDisplayYSize( $("#sigImg")[0].height );
            SetTabletState(0, tmr);
            SetJustifyMode(5);
            ClearTablet();
            if(tmr === null){
                tmr = SetTabletState(1, ctx, 50);
            }else{
                SetTabletState(0, tmr);
                tmr = null;
                tmr = SetTabletState(1, ctx, 50);
            }

            if(GetTabletState() == 0){
                showAlert("Unable to connect to Signature Pad, please check that it is connected to this computer", false, true);
            }
        } else{
            showAlert("Unable to communicate with SigWeb, please make sure it is installed and running ", true);
        }
    }

    function onClear(){
        if (typeof clearTablet !== 'undefined') {
            ClearTablet();
        }
    }

    function onDone(){
        if(NumberOfTabletPoints() == 0){
            return false;
        }else{
            SetTabletState(0, tmr);
            return true;
        }
    }

    function close(){
        if(resetIsSupported){
            Reset();
        } else{
            if (typeof clearTablet !== 'undefined') {
                ClearTablet();
                SetTabletState(0, tmr);
            }
        }
    }

    document.addEventListener("beforeunload", function (evt) {
        try{
            close();
            clearInterval(tmr);
        }catch(e){

        }
    }, false);
    //end Topaz code
};
