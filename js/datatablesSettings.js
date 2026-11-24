// HHK-wide DataTables settings.  Load right after the DataTables library.
(function ($) {
    if (!$ || !$.fn.dataTable || !$.fn.dataTable.ext) {
        return;
    }
    var DataTable = $.fn.dataTable;

    // Show DataTables errors as toast messages instead of the default alert() box.
    // Set immediately (not on document ready) so tables initialised during page load are covered.
    // flagAlertMessage (pag.js) is looked up when an error happens, so script order doesn't matter.
    DataTable.ext.errMode = function (settings, techNote, message) {
        if (typeof flagAlertMessage === "function") {
            flagAlertMessage(message, 'error');
        } else if (window.console) {
            console.error(message);
        }
    };
    
        DataTable.defaults.autoWidth = false;

    // Standard control layout (DataTables 2+):
    //   top:    info ............ search
    //   bottom: page length ..... paging
    if (DataTable.versionCheck && DataTable.versionCheck('2') && DataTable.defaults.layout) {
        DataTable.defaults.layout.topStart = 'info';
        DataTable.defaults.layout.topEnd = 'search';
        DataTable.defaults.layout.bottomStart = 'pageLength';
        DataTable.defaults.layout.bottomEnd = 'paging';
    }
})(window.jQuery);
