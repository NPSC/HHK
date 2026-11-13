// Show DataTables errors as toast messages instead of the default alert() box.
// Set immediately (not on document ready) so tables initialised during page load are covered.
// flagAlertMessage (pag.js) is looked up when an error happens, so script order doesn't matter.
(function ($) {
    if (!$ || !$.fn.dataTable || !$.fn.dataTable.ext) {
        return;
    }
    $.fn.dataTable.ext.errMode = function (settings, techNote, message) {
        if (typeof flagAlertMessage === "function") {
            flagAlertMessage(message, 'error');
        } else if (window.console) {
            console.error(message);
        }
    };
})(window.jQuery);
