$(function(){
    if($.fn.dataTable.ext.errMode != undefined && typeof flagAlertMessage === "function"){
        $.fn.dataTable.ext.errMode = function(settings, techNote, message){
            flagAlertMessage(message, 'error');
        }
    }
});