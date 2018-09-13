<!DOCTYPE html>
<html>
    <head>
        <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
        <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
        <style>
            .btn-outline-primary{ color: #005596; border-color: #005596;}.btn-outline-primary:hover{color:white;background-color:#005596;}.btn-outline-primary:active{color:white !important;background-color:#005596 !important;}</style>
        <script src="https://instamedprd.cachefly.net/Content/Js/token.js" data-displaymode="incontext" data-environment="UAT" data-mobiledisplaymode="incontext"></script>
        <script src="https://developers.instamed.com/wp-content/themes/devportal/js/secureTokenHelper.js"></script>
        <script> var s = new Date();
        function onloadHelper() {
            if ($("#CardEntry-CardNumber,#CardEntry,#cardinput").length > 0) {
                InstaMed2.onReady();
            } else if ((new Date()).getTime() - s.getTime() < 3000) {
                setTimeout("onloadHelper();", 5);
            }
        }
        onloadHelper();
        </script>
        </head>
    <body style="padding:16px;">
        <div>
            <input type="button" class="btn btn-outline-primary" value="Add Card" onclick="addCard('NP.SOFTWARE.TEST@instamed.net', '400', '600'); return false;" />
        </div>
        <input type="text" id="cardinput" style="display:none;">
        <input type="hidden" id="txtCardNumber" name="txtCardNumber">
        <input type="hidden" id="txtExpDate" name="txtExpDate">
        <p style="display:none" id="instamedcardinfo"><span id="instamedcardinfocontent">Card :</span> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" onclick="javascript: InstaMed2.removeCard(); return false;" >Remove</a></p>
    </body>
</html>