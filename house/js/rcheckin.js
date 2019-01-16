/* global dateFormat */

/**
 * rcheckin.js
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 */
$(document).ready(function() {
    "use strict";
    $.widget( "ui.autocomplete", $.ui.autocomplete, {
        _resizeMenu: function() {
            var ul = this.menu.element;
            ul.outerWidth( Math.max(
                    ul.width( "" ).outerWidth() + 1,
                    this.element.outerWidth()
            ) * 1.1 );
        }
    });
    $(':input[type="button"]').button();
    $.extend($.fn.dataTable.defaults, {
        "dom": '<"top"if>rt<"bottom"lp><"clear">',
        "displayLength": 25,
        "lengthMenu": [[25, 50, -1], [25, 50, "All"]],
        "order": [[ 4, 'asc' ]]
    });
    $('#atblgetter, #stblgetter, #wtblgetter, #imtblgetter').DataTable({
        'columnDefs': [
            {'targets': [4,5],
             'type': 'date',
             'render': function ( data, type ) {return dateRender(data, type, dateFormat);}
            }
         ]
     });
    $('#hhk-confResvHdr').click(function () {
        $('#hhk-confResv').toggle('blind');
    });
    $('#hhk-chkedInHdr').click(function () {
        $('#hhk-chkedIn').toggle('blind');
    });
    $('#hhk-wListResvHdr').click(function () {
        $('#hhk-wListResv').toggle('blind');
    });
    createAutoComplete($('#Search'), 3, {cmd: 'role', gp:'1'}, function (item) {
        window.open('Reserve.php?id=' + item.id, '_self');
    });
    createAutoComplete($('#phSearch'), 5, {cmd: 'role', gp:'1'}, function (item) {
        window.open('Reserve.php?id=' + item.id, '_self');
    });
    $('#Search').keypress(function(event) {
        $(this).removeClass('ui-state-highlight');
    });
    $('#guestSearchWrapper').show();
});
