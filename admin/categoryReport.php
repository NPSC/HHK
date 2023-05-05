<?php

use HHK\sec\{SecurityComponent, WebInit};
use HHK\HTMLControls\selCtrl;
use HHK\sec\Session;

/**
 * categoryReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require("AdminIncludes.php");

$wInit = new WebInit();

$dbh = $wInit->dbh;
$uS = Session::getInstance();

$page = $wInit->page;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();

$donationsFlag = SecurityComponent::is_Authorized("NameEdit_Donations");
$donorFlag = SecurityComponent::is_Authorized("Cat_Donor");

$showMemberTypes = false;
if ($donationsFlag || $donorFlag) {
    $showMemberTypes = true;
}


$catmarkup = "<thead><tr><td></td></tr></thead><tbody><tr><td></td></tr></tbody>";
$andChecked = "";
$unionChecked = "";
$orChecked = "checked='checked'";
$makeTable = 0;
$catSelMarkup = "";
$catSelTitleMarkup = "";
$catagoryHeadertable = "";

// Selector Controls for Category section
$gSel = readGenLookupsPDO($dbh, "Vol_Category");
$catSelCtrls = array();

foreach ($gSel as $selData) {
    if ($selData[0] != 'Vol_Type' || $showMemberTypes === TRUE) {
        $catSelCtrls[$selData[0]] = new selCtrl($dbh, $selData[0], false, "sel" . $selData[0], true, $selData[1]);
    }
}

$catSelRoles = new selCtrl($dbh, "Vol_Rank", false, "catRol", true);
$catSelDormancy = new selCtrl($dbh, "Dormant_Selector_Code", false, "catDor", false);
$catVolStatus = new selCtrl($dbh, "Vol_Status", false, "catVolStatus", true);
// Predefine active as the default selection.
$catVolStatus->set_value(TRUE, 'a');


// Postback logic
if (filter_has_var(INPUT_POST, "btnCat") || filter_has_var(INPUT_POST, "btnCatDL") || filter_has_var(INPUT_POST, "btnCSVEmail")) {
    $makeTable = 1;

    ini_set('memory_limit', "128M");

    require("functions" . DS . "CategoryReportMgr.php");

    $volCat = processCategory($dbh, $catSelCtrls, $catSelRoles, $catSelDormancy, $catVolStatus, $uS->SolicitBuffer);

    if ($volCat->get_andOr() == "and") {
        $andChecked = "checked='checked'";
        $orChecked = "";
        $unionChecked = "";
    } else if ($volCat->get_andOr() == "union") {
        $andChecked = "";
        $orChecked = "";
        $unionChecked = "checked='checked'";
    }

    $catagoryHeadertable = $volCat->reportHdrMarkup;
    $catmarkup = $volCat->get_reportMarkup();
}

// Create category/code selector controls.
foreach ($catSelCtrls as $sel) {
    $catSelMarkup .= "<td>" . $sel->createMarkup(5, true) . "</td>";
    $catSelTitleMarkup .= "<th>" . $sel->get_title() . "</th>";
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
            // Init j-query and the page blocker.
            $(document).ready(function() {
            	$("input[type=submit], input[type=button]").button();

                var listTable;
                var makeTable = <?php echo $makeTable; ?>;
                var now = new Date();
                $('#selIntMonth option:eq(' + now.getMonth() + ')').prop("selected", true);
                $('#m').css('display', 'table-cell');
                $('#fy').css('display', 'none');
                $('#cy').css('display', 'none');
                $('#selHoursInterval').change(function() {
                    $('#m').css('display', 'none');
                    $('#fy').css('display', 'none');
                    $('#cy').css('display', 'none');
                    $('#' + $(this).val()).css('display','table-cell');
                });
                if ($('#cbIncludeHours').checked) {
                    $('.hhk-showHours').css('display', 'table-cell');
                } else {
                    $('.hhk-showHours').css('display', 'none');
                }
                $('#cbIncludeHours').change(function () {
                    if (this.checked) {
                        $('.hhk-showHours').css('display', 'table-cell');
                    } else {
                        $('.hhk-showHours').css('display', 'none');
                    }
                });
                if (listTable) {
                    listTable.fnDestroy();
                }
                if (makeTable === 1) {
                    $('div#printArea').css('display', 'block');
                    try {
                        listTable = $('#tblCategory').dataTable({
                            "displayLength": 50,
                            "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                            "dom": '<"top"lf><"hhk-overflow-x"rt><"bottom"p>',
                            "order": [[1,'asc'], [2,'asc']]
                        });
                    }
                    catch (err) {

                    }
                }
                $('#Print_Button').click(function() {
                    $("div#printArea").printArea();
                });
            });
        </script>
    </head>
    <body <?php if ($testVersion){ echo "class='testbody'";} ?> >
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
        	<h2><?php echo $wInit->pageHeading; ?></h2>
            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-3">
                <form id="fcat" action="categoryReport.php" method="post">
                    <table>
                        <tr>
                            <?php echo $catSelTitleMarkup; ?>
                        </tr><tr>
                            <?php echo $catSelMarkup; ?>
                        </tr>
                    </table>
                    <table><tr>
                            <th style="display:none;">Dormancy</th>
                            <th style="min-width: 100px;">Combiner</th>
                            <th style="min-width: 100px;">Membership</th>
                            <th style="min-width: 100px;">Role</th>
                        </tr><tr>
                            <td style="display:none;">
                                <?php //echo $catSelDormancy->createMarkup($catSelDormancy->get_rows(), false);   ?>
                            </td>
                            <td>
                                <input type="radio" id="rband" name="rb_andOr" value="and" <?php echo $andChecked; ?> class="clsAndOr" /><label for="rband"> And</label><br/>
                                <input type="radio" id="rbor" name="rb_andOr" value ="or" <?php echo $orChecked; ?> class="clsAndOr" /><label for="rbor"> Or</label><br/>
                                <input type="radio" id="rbunion" name="rb_andOr" value="union" <?php echo $unionChecked; ?> class="clsAndOr" /><label for="rbunion"> Union</label>
                            </td><td>
                                <?php echo $catVolStatus->createMarkup($catVolStatus->get_rows(), false); ?>
                            </td><td>
                                <?php echo $catSelRoles->createMarkup($catSelRoles->get_rows(), true); ?>
                            </td>
                        </tr>
                    </table>
                    <h4 style="text-align:center;" class="mt-3">Regular Category Reports:</h4>
                    <div class="hhk-flex mt-1" style="justify-content: space-evenly;">
                    	<input type="submit" name="btnCSVEmail" value="Run Email List" />
                        <input type="submit" name="btnCat" value="Run Category Report" />
                        <input type="submit" name="btnCatDL" value="Download Category Excel File" />
                    </div>
                </form>
            </div>
            <div id="printArea" style="display:none;" class="ui-widget ui-widget-content ui-corner-all hhk-widget-content">
            	<?php if($catagoryHeadertable != "") { ?>
                <table class="my-3" style="min-width: 350px;">
                    <?php echo $catagoryHeadertable; ?>
                </table>
                <?php } ?>
                <?php if($catmarkup != ""){ ?>
                <table id="tblCategory" class="display">
                    <?php echo $catmarkup; ?>
                </table>
                <?php } ?>
            </div>
            <div id="submit"></div>
        </div>
    </body>
</html>
