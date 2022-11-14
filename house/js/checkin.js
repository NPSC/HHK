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
    var dateFormat = $('dateFormat').val();
    $('input[type="button"]').button();
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
            },
            {'targets':[0],
            	sortable: false
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
    createRoleAutoComplete($('#Search'), 3, {cmd: 'guest'}, function (item) {
        window.open('Reserve.php?id=' + item.id + '&title=c', '_self');
    });
    createRoleAutoComplete($('#phSearch'), 5, {cmd: 'phone'}, function (item) {
        window.open('Reserve.php?id=' + item.id + '&title=c', '_self');
    });
    createRoleAutoComplete($('#MRNSearch'), 3, {cmd: 'mrn'}, function (item) {
        window.open('Reserve.php?id=' + item.id + '&title=c', '_self');
    });

    $('#Search').keypress(function() {
        $(this).removeClass('ui-state-highlight');
    });

});