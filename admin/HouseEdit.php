<?php
/**
 * HouseEdit.php
 *
 * @category  Configuration
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2015 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

require_once ("AdminIncludes.php");
require_once (REL_BASE_DIR . "classes" . DS . "DataTableMarkup.php");



$wInit = new webInit();

$dbh = $wInit->dbh;

//$page = $wInit->page;
//$menuTitle = $wInit->menuTitle;
$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();

// Get the room listing
$rows = array();

if (($stmt = $dbh->query("select * from vroom_listing;")) !== FALSE) {

    if ($stmt->rowCount() > 0) {
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$roomListing = DataTableMarkup::generateHTML_Table($rows, "tblRoom", false);
unset($rows);

// Location Listing
if (($stmt = $dbh->query("select * from vlocation_listing;")) !== FALSE) {

    if ($stmt->rowCount() > 0) {
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$locationListing = DataTableMarkup::generateHTML_Table($rows, "tblLoc", false);

$dbh = null;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
    "http://www.w3.org/TR/html4/loose.dtd"><html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link href="css/ss/jquery-ui.css" rel="stylesheet" type="text/css" />
        <link href="css/default.css" rel="stylesheet" type="text/css" />
        <link href="css/dTables.css" rel="stylesheet" type="text/css" />
        <script type="text/javascript" src="../js/jquery.js"></script>
        <script type="text/javascript" src="../js/jquery-ui.js"></script>
        <script type="text/javascript" src="../js/printArea.js"></script>
        <script type="text/javascript" src="../js/dTables.js"></script>
        <script type="text/javascript">
            function roomClickRow(aData, listTable) {


                $('#roomEdit').dialog("option", "title", "Edit Room Information");
                $('#roomEdit').dialog('open');

            }
            function locClickRow(aData, listTable) {

                $('#locEdit').dialog("option", "title", "Edit Location Information");
                $('#locEdit').dialog('open');

            }
            $(document).ready(function() {
                $('div#printArea').css('display', 'block');
                var listTable;
                try {
                    listTable = $('#tblRoom').dataTable({
                        "fnCreatedRow": function( nRow, aData, iDataIndex ) {
                            $(nRow).click( function (event) {
                                roomClickRow(aData, listTable);
                            })
                        },
                        "iDisplayLength": 20,
                        "aLengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]]
                        , "sDom": '<"top"ilfp>rt<"bottom"p>',
                        "aaSorting": []
                    });
                } catch (err) {}
                $('#roomEdit').dialog({
                    autoOpen: false,
                    width: 550,
                    resizable: true,
                    modal: true
                });
                var locTable;
                try {
                    locTable = $('#tblLoc').dataTable({
                        "fnCreatedRow": function( nRow, aData, iDataIndex ) {
                            $(nRow).click( function (event) {
                                locClickRow(aData, locTable);
                            })
                        },
                        "iDisplayLength": 10,
                        "aLengthMenu": [[10, 25, -1], [10, 25, "All"]]
                        , "sDom": '<"top"ilfp>rt<"bottom"p>',
                        "aaSorting": []
                    });
                } catch (err) {}
                $('#locEdit').dialog({
                    autoOpen: false,
                    width: 550,
                    resizable: true,
                    modal: true
                });
            });
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?>>
        <div id="contentDiv">
            <?php echo $menuMarkup; ?>
            <h1>Edit House Facilities</h1>
            <form id="form1" action="HouseEdit.php" method="post">
                <div id="divRooms" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail">
                    <p style="margin-bottom: 10px;">Click a row to edit an existing room or: <input id="newRoom" type="button" value="New Room" /> </p>
                    <?php echo $roomListing ?>
                </div>
                <div id="divLocations" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail">
                    <p style="margin-bottom: 10px;">Click a row to edit an existing Location or: <input id="newLoc" type="button" value="New Location" /> </p>
                    <?php echo $locationListing ?>
                </div>
                <div id="roomEdit">
                    <table>
                        <tr>
                            <td>
                                Title
                            </td>
                            <td>
                                <input type="text" id="title" name="title" value="" />
                            </td>
                        </tr>
                        <tr>
                            <td>

                            </td>
                            <td>

                            </td>
                        </tr>
                        <tr>
                            <td>

                            </td>
                            <td>

                            </td>
                        </tr>
                        <tr>
                            <td>

                            </td>
                            <td>

                            </td>
                        </tr>
                    </table>
                </div>
                <div id="locEdit"></div>
            </form>
        </div>
    </body>
</html>

