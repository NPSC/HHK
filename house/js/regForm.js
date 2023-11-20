// page setup
function setupRegForm(idReg, rctMkup, regMarkup, payId, invoiceNumber, vid, primaryGuestId, idPsg){
    
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

        $('.btnSave').prop('disabled', 'disabled').html(loading);

        try{
            var docCode = $(this).data("tab");
            $(this).closest('.ui-tabs-panel').find("div.PrintArea .btnSign").remove();
            var formContent = $(this).closest('.ui-tabs-panel').find("div.PrintArea")[0].outerHTML;

            var formData = new FormData();
            formData.append('cmd', 'saveRegForm');
            formData.append('guestId', primaryGuestId);
            formData.append('psgId', idPsg);
            formData.append('idVisit', '<?php echo $idVisit; ?>');
            formData.append('idResv', '<?php echo $idResv; ?>');
            formData.append('docTitle', "Registration Form");
            formData.append('docContents', btoa(formContent));

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

    $('#mainTabs').show();
    $('#regTabDiv, #signedRegTabDiv').tabs();

};

// esign JS
$(document).ready(function(){

    $("#jSignDialog").dialog({
    	autoOpen: false,
    	width: getDialogWidth(800),
    	height: 350,
    	modal: true,
    	buttons: {
            "Clear": function(){
                var idName = $(this).find("input#idName").val();
    		var formCode = $(this).find("input#formCode").val();
    		$(this).find(".signature").jSignature('clear');
    		$("#" + formCode + " .signWrapper[data-idname=" + idName + "] .sigLine img").attr("src", "").hide();
    		$("#" + formCode + " .signWrapper[data-idname=" + idName + "] .signDate").hide();
            },
            "Sign": function() {
            	var idName = $(this).find("input#idName").val();
            	var formCode = $(this).find("input#formCode").val();
		var signature = $(this).find('.signature').jSignature("getData");
            	$("#" + formCode + " .signWrapper[data-idname=" + idName + "] .sigLine img").attr("src", signature).show();
            	$("#" + formCode + " .signWrapper[data-idname=" + idName + "] .signDate").show();

                $(this).dialog("close");
            }
        }
    });

    $("#jSignDialog .signature").jSignature({"width": "750px", "height": "141px"});

    $("#topazDialog").dialog({
    	autoOpen: false,
    	width: getDialogWidth(550),
    	height: 300,
    	modal: true,
    	buttons: {
            "Clear": function(){
                var idName = $(this).find("input#idName").val();
    		var formCode = $(this).find("input#formCode").val();
    		onClear();
    		$("#" + formCode + " .signWrapper[data-idname=" + idName + "] .sigLine img").attr("src", "").hide();
    		$("#" + formCode + " .signWrapper[data-idname=" + idName + "] .signDate").hide();
            },
            "Sign": function() {
            	var idName = $(this).find("input#idName").val();
            	var formCode = $(this).find("input#formCode").val();
                if(onDone() != false){
                    var signature = $(this).find("canvas#sigImg")[0].toDataURL("image/png");
                    $("#" + formCode + " .signWrapper[data-idname=" + idName + "] .sigLine img").attr("src", signature).show();
                    $("#" + formCode + " .signWrapper[data-idname=" + idName + "] .signDate").show();

                    $(this).dialog("close");
                }else{
                    showAlert("Please sign before continuing", false, false);
                    $(this).dialog("option", "height", 350);
                }
            }
        },
        open: function(){
        	initTopaz();
            $(this).dialog("option", "height", 300);
        }
    });

    $(".btnSign").on("click", function(){
    	let eSignMethod = $(this).data('esign');
    	let name = $(this).closest(".row").find(".printName").text();

    	if(eSignMethod === 'jSign'){
            $("#jSignDialog input#idName").val($(this).closest(".signWrapper").data("idname"));
            $("#jSignDialog input#formCode").val($(this).closest(".ui-tabs-panel").attr('id'));
            $("#jSignDialog").find(".signature").jSignature('clear');
            $("#jSignDialog").dialog("option", "title", "Signature: " + name).dialog("open");
        }else if(eSignMethod === 'topaz'){
            $("#topazDialog input#idName").val($(this).closest(".signWrapper").data("idname"));
            $("#topazDialog input#formCode").val($(this).closest(".ui-tabs-panel").attr('id'));
            $("#topazDialog").dialog("option", "title", "Signature: " + name).dialog("open");
	}
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
            SetDisplayXSize( 500 );
            SetDisplayYSize( 100 );
            SetTabletState(0, tmr);
            SetJustifyMode(0);
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

    //Perform the following actions on
    //	1. Browser Closure
    //	2. Tab Closure
    //	3. Tab Refresh
    window.onbeforeunload = function(evt){
        close();
        clearInterval(tmr);
        evt.preventDefault(); //For Firefox, needed for browser closure
    };
    //end Topaz code
});
