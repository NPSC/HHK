<?php
/**
 * checkDateReport.php
 *
 * @category  Report
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

require_once ("AdminIncludes.php");

require_once("functions" . DS . "CheckDateReport.php");
require_once ("classes" . DS . "VolCats.php");
require_once(CLASSES . "chkBoxCtrlClass.php");
require_once(CLASSES . "selCtrl.php");

$wInit = new webInit();
$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;


$menuMarkup = $wInit->generatePageMenu();

// Check strings for slashes, etc.
addslashesextended($_POST);

$sFuture = true;
$sFutmk = "checked='checked'";
$sPast = false;
$sPstmk = "";
$sDays = 5;
$makeTable = 0;
$checkDateHeadertable = "";
$markup = "";
$selectedVol = "";

// Postback
if (isset($_POST["btnCkDate"]) || isset($_POST["txtId"])) {


    $makeTable = 4;
    // Get page input fields
    if (isset($_POST["cbFuture"])) {
        $sFuture = filter_var($_POST["cbFuture"], FILTER_VALIDATE_BOOLEAN);
    }

    if (isset($_POST["cbPast"])) {
        $sPast = filter_var($_POST["cbPast"], FILTER_VALIDATE_BOOLEAN);
    }

    if (isset($_POST["txtNumDays"])) {
        $sDays = filter_var($_POST["txtNumDays"], FILTER_SANITIZE_NUMBER_INT);
    }

    if ($sPast) {
        $sPstmk = "checked='checked'";
    } else {
        $sPstmk = "";
    }

    if ($sFuture) {
        $sFutmk = "checked='checked'";
    } else {
        $sFutmk = "";
    }

    $mkups = chkDate($dbh);

    $markup = $mkups[0];
    $checkDateHeadertable = $mkups[1];


//    $campcodes = filter_input_array(INPUT_POST, "selVol", FILTER_SANITIZE_STRING);
//    if (is_null($campcodes) === FALSE) {
//        foreach ($campcodes as $item) {
//            // remember picked values for this control
//            $selectedVol .= $item . "|";
//        }
//    }
}

// CheckDates category selector
$g = readGenLookups($dbh, "Vol_Category");
$ckSelCategories = "<option value='' selected='selected'></option>";

foreach ($g as $row) {
    $ckSelCategories .= "<option value='" . $row[1] . "'>" . $row[1] . "</option>";
}


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
    "http://www.w3.org/TR/html4/loose.dtd">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link href="css/default.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />
        <link href="<?php echo JQ_DT_CSS; ?>" rel="stylesheet" type="text/css" />
<?php echo TOP_NAV_CSS; ?>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript">
            var listTable;
            var makeTable = <?php echo $makeTable; ?>;
            // Init j-query and the page blocker.
            $(document).ready(function() {

                if (listTable) {
                    listTable.fnDestroy();
                }
                if (makeTable == 4) {
                    try {
                        listTable = $('#tblCheckDate').dataTable();
                    } catch (err) {}
                }
                // Member search letter input box
                $('#txtsearch').autocomplete({
                    source: function (request, response) {
                        // Don't send for numbers
                        if (isNumber(parseInt(request.term, 10))) {
                            response();
                        }
                        var schType = 'm';
                        // get more data
                        var inpt = {
                            cmd: "srrel",
                            letters: request.term,
                            basis: schType,
                            id: 0
                        };
                        lastXhr = $.getJSON("liveNameSearch.php", inpt,
                            function(data, status, xhr) {
                             if (xhr === lastXhr) {
                                if (data.error) {
                                    data.value = data.error;
                                }

                                response(data);
                            }
                            });
                    },
                    minLength: 3,
                    select: function( event, ui ) {
                        if (!ui.item) {
                            return;
                        }

                        var cid = parseInt(ui.item.id, 10);
                        if (isNumber(cid)) {
                            $('#txtId').val(cid);
                            $('#fCkdate').submit();
                        }
                    }
                });
                function isNumber(n) {
                    return !isNaN(parseFloat(n)) && isFinite(n);
                }
            });
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?> >
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <div id="vckdate"  class="ui-widget ui-widget-content ui-corner-all hhk-member-detail">
                <form id="fCkdate" action="checkDateReport.php" method="post">
                    <table>
                        <tr>
                            <td><h2><?php echo $wInit->pageHeading; ?></h2></td>
                        </tr>
                        <tr>
                            <td><span id="errmess"></span></td>
                        </tr>
                        <tr>
                            <td><h4>Set Check Date Report Parameters</h4>
                                <table>
                                    <tr><td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" style="vertical-align:middle;">Contact Date Interval.  Today and</td></tr>
                                    <tr><td colspan="2" style="text-align:left;">This number of days: <input type="text" name="txtNumDays" value ="<?php echo $sDays; ?>" size="2" /></td>
                                    </tr>
                                    <tr><td colspan="2" style="text-align:left;"> <input type="checkbox" name="cbFuture" <?php echo $sFutmk ?> /> in the Future</td></tr>
                                    <tr><td colspan="2" style="text-align:left;"> <input type="checkbox" name="cbPast" <?php echo $sPstmk ?> /> in the Past</td></tr>
                                </table>
                        </tr>
                        <tr>
                            <td><h4>Select Categories</h4>
                                <table>
                                    <tr><td><select id="selVol" name="selVol[]" size="4" multiple="multiple" title="Select one or more categories">
                                                <?php echo $ckSelCategories; ?>
                                                <option value="General">General</option>
                                            </select><input type="hidden" id="hdnVolSelected" value="<?php echo $selectedVol; ?>" /> </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3"><span id="vGenMessage" ></span></td><td>
                                            <input type="submit" name="btnCkDate" id="btnCkDate" value="Get Contact Dates"  />
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td><h2>Lookup Individual Contact Dates</h2>
                                <table>
                                    <tr><td></td>
                                    </tr>
                                    <tr><td colspan="3">Select a name to create an Individual report.</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            Member
                                            <input type="text" id="txtsearch" name="txtsearch"  size="10" title="Type the first 3 letters of the first or last name." />
                                            <input type="hidden" id="txtId" name="txtId" value=""/>
                                        </td>
                                    </tr>

                                </table>
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
            <div style="clear:both;"></div>
            <div id="printArea" class="ui-widget ui-widget-content" style="float:left;">
                <table>
                    <?php echo $checkDateHeadertable; ?>
                </table>
                <table id="tblCheckDate" cellpadding="0" cellspacing="0" border="0" class="display">
                    <?php echo $markup; ?>
                </table>
            </div>
        </div>
    </body>
</html>
