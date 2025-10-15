<?php

use HHK\MailList;
use HHK\sec\{Session, WebInit};
use HHK\HTMLControls\{chkBoxCtrl, selCtrl};
use HHK\Admin\Reports\DirectoryReport;

/**
 * directory.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");

$wInit = new webInit();

$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();
$uS = Session::getInstance();

// Checkbox controls
$cbBasisDir = new chkBoxCtrl($dbh, "Member_Basis", "Include", "cbDirBasis", true);
$cbBasisDir->set_class("hhk-dirBasis");

$cbRelationDir = new chkBoxCtrl($dbh, "Rel_Type", "Show", "cbRelt", false, "Description");
$cbRelationDir->set_class("hhk-dirRel");
// Set partner true
$cbRelationDir->set_cbValueArray(true, "sp");


$selDirType = new selCtrl($dbh, "Dir_Type_Selector_Code", false, "selDirType", false, "", "Description");
//$selDirType->set_class("ui-widget");

$cbEmpChecked = "";
$dirmarkup = "";
$refreshDate = 'Never';
$affectedRows = 0;

// create date of mail listing table
$stmt = $dbh->query("select Description from gen_lookups where Table_Name='Mail_List' and Code = 'Refresh_Date'");
$rows = $stmt->fetchAll(PDO::FETCH_NUM);
if (count($rows) > 0) {
    $refreshDate = date('M j, Y', strtotime($rows[0][0]));
}


if (filter_has_var(INPUT_POST, 'btnPrep')) {

    // Load the table with fresh data
    $affectedRows = MailList::fillMailistTable($dbh, $uS->SolicitBuffer);

    if ($affectedRows > 0) {
        $dbh->exec("replace into gen_lookups (`Table_Name`, `Code`, `Description`) values ('Mail_List', 'Refresh_Date', '" . date('Y-m-d') . "')");
    }

}



if (filter_has_var(INPUT_POST, "btnExcel") || filter_has_var(INPUT_POST, "btnHere")) {

    // Form returned to generate directory
    $dirmarkup = DirectoryReport::dirReport($dbh, $cbBasisDir, $cbRelationDir, $selDirType, $uS->SolicitBuffer);

    if (filter_has_var(INPUT_POST, "cbEmployee")) {
        $cbEmpChecked = "checked='checked'";
    }
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>

        <script type="text/javascript">
            function basisType(ctrl) {
                if (ctrl.value == 'ai') {
                    if (ctrl.checked == true) {
                        // set partner
                        $('input.hhk-dirRel[value="sp"]').prop("checked", true);
                    } else {
                        // clear all, check employee
                        $('input.hhk-dirRel').prop("checked", false);
                        $('input#cbEmployee').prop("checked", true);
                    }
                } else {
                    // a organization cb changed state
                    // If all are unchecked, uncheck employee
                    var n = $('input:checked.hhk-dirBasis[value!="ai"]').length;
                    if (n == 0) {
                        $('input#cbEmployee').prop("checked", false);
                    }
                }
            }
            function dirType(ctrl) {
                if ($(ctrl).val() == 'd') {
                    $('.tdDisp').css("visibility", "visible");

                }
                else if ($(ctrl).val() == 'e') {
                    $('.tdDisp').css("visibility", "hidden");

                } else {
                    $('.tdDisp').css("visibility", "hidden");

                }
            }
            // Init j-query
            $(document).ready(function() {

            	$("input[type=submit], input[type=button]").button();

                $('#selDirType').change( function() {
                    dirType(this);
                });
                $('input.hhk-check-button').click(function () {
                    if ($(this).prop('id') == 'btnCkAll') {
                        $('input.hhk-dirRel').prop('checked', true);
                    } else {
                        $('input.hhk-dirRel').prop('checked', false);
                    }
                });
                dirType(document.getElementById('selDirType'));
                $('input.hhk-dirBasis').change(function () {
                    basisType(this);
                });

                try {
                    $('#tblDirectory').dataTable({
                        "displayLength": 50,
                        "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                        "dom": '<"top"ilf><"hhk-overflow-x"rt><"bottom"p>',
                        "order": [[1,'asc'], [2,'asc']]
                    });
                }
                catch (err) {console.log(err)}

            });
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?>>
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?> - Excludes Guests and Patients.</h2>
            <div id="vdirectory"  class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-3">
                <form id="fDirectory" action="directory.php" method="post">
                    <table>
                        <tr>
                            <td>
                                <?php echo $selDirType->createMarkup(3); ?>
                            </td>
                            <td>
                                <?php echo $cbBasisDir->createMarkup(); ?>
                            </td>
<!--                            <td style="width:200px;" class="tdDisp">
                                <?php //echo $cbRelationDir->createMarkup(); ?>
                                <div style="padding: 0.2em;"><input type="checkbox" id="cbEmployee" value="emp" class="hhk-dirRel" name="cbEmployee" <?php //echo $cbEmpChecked; ?> />
                                    <label for="cbEmployee">Employee</label></div>
                                <div style="padding: 2px 5px; margin-top: 6px;"><input type="button" class="hhk-check-button" id="btnCkAll" value="Check All"/><input type="button" class="hhk-check-button" id="btnCkNone" style="margin-left:.5em;" value="Uncheck"/></div>
                            </td>-->
                        </tr>
                        <tr>
                            <td colspan="3">Last mail list refresh date: <span style="font-weight: bold;"><?php echo $refreshDate." "; ?><input type="submit" name="btnPrep" value="Prepare Address Table" /></span></td>
                        </tr>
                    </table>
                    <div class="hhk-flex mt-3" style="justify-content: space-evenly;">
                    	<input name="btnHere" id="btnHere" type="submit" value="Run Here" />
                        <input id="btnExcel" name="btnExcel" type="submit" value="Download Excel File" />
                    </div>
                </form>
            </div>
            <?php echo $dirmarkup; ?>

            <div id="submit"></div>
        </div>
    </body>
</html>
