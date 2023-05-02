
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
        ClearTablet();

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
            ClearTablet();
            SetTabletState(0, tmr);
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
