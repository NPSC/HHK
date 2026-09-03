<?php

use HHK\sec\{Session, WebInit};
use HHK\HTMLControls\{chkBoxCtrl, selCtrl};
use HHK\Admin\Reports\DirectoryReport;
use HHK\Vite\Vite;

/**
 * directory.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");

$wInit = new WebInit();

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

        <?php echo Vite::asset('resources/js/admin.js'); ?>
        
        <?php echo FAVICON; ?>
        
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
            
            document.addEventListener("DOMContentLoaded", () => {

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
                        </tr>
                    </table>
                    <div class="hhk-flex mt-3 justify-content-evenly">
                    	<button name="btnHere" id="btnHere" type="submit" class="ui-button ui-widget ui-corner-all">Run Here</button>
                        <button name="btnExcel" id="btnExcel" type="submit" class="ui-button ui-widget ui-corner-all">Download Excel</button>
                    </div>
                </form>
            </div>
            <?php echo $dirmarkup; ?>

            <div id="submit"></div>
        </div>
    </body>
</html>
