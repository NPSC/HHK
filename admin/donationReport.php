<?php

use HHK\sec\WebInit;
use HHK\HTMLControls\{selCtrl, chkBoxCtrl};
use HHK\SysConst\SalutationCodes;
use HHK\Donation\Campaign;

/**
 * donationReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");

$wInit = new WebInit();
$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();

 addslashesextended($_POST);

$makeTable = 0;
$donmarkup = "<thead><tr><td></td></tr></thead><tbody><tr><td></td></tr></tbody>";

$sDate = "";
$eDate = "";
$maxMkup = "";
$minMkup = "";
$selectRoll = "";
$donHdrMarkup = "";
$anddChecked = "checked='checked'";
$ordChecked = "";
$exDeceasedChecked = "";
$donSelMemberType = new selCtrl($dbh, "Vol_Type", false, "donMT", true);
$cbBasisDonor = new chkBoxCtrl($dbh, "Member_Basis", "Include", "cbDonBasis", true);
$cbBasisDonor->set_class("ui-widget");
$SelectDonCamp = "";
$rollup = 't';

$overRideSalChecked = "checked='checked'";

$envSalSelector = new selCtrl($dbh, 'Salutation', FALSE, 'envSal', FALSE);
$envSalSelector->set_value(TRUE, SalutationCodes::Formal);
$letterSalSelector = new selCtrl($dbh, 'Salutation', FALSE, 'letSal', FALSE);
$letterSalSelector->set_value(TRUE, SalutationCodes::FirstOnly);

#--------------------------------------------------------------
// form1 save button:
if (isset($_POST["btnDonors"]) || isset($_POST["btnDonDL"]) || isset($_POST["btnstreamlined"])) {
#--------------------------------------------------------------
    require_once("functions" . DS . "donorReportManager.php");

    //$selectedTab = "2";
    $makeTable = 2;

    // collect the parameters
    $maxMkup = filter_var($_POST["txtmax"], FILTER_SANITIZE_NUMBER_INT);
    $minMkup = filter_var($_POST["txtmin"], FILTER_SANITIZE_NUMBER_INT);
    $sDate = filter_var($_POST["sdate"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $eDate = filter_var($_POST["edate"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $selectRoll = filter_var($_POST["selrollup"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $letterSalSelector->setReturnValues($_POST[$letterSalSelector->get_htmlNameBase()]);
    $envSalSelector->setReturnValues($_POST[$envSalSelector->get_htmlNameBase()]);

    if (isset($_POST["overRideSal"])) {
        $overRideSalChecked = "checked='checked'";
        $overrideSalutations = TRUE;
    } else {
        $overRideSalChecked = '';
        $overrideSalutations = FALSE;
    }

    // check campaign codes
    if (isset($_POST["selDonCamp"])) {
        $campcodes = $_POST["selDonCamp"];
        foreach ($campcodes as $item) {
            // remember picked values for this control
            $SelectDonCamp .= $item . "|";
        }
    }


    // Do the report
    $voldCat = prepDonorRpt($dbh, $cbBasisDonor, $donSelMemberType, $overrideSalutations, filter_var($_POST[$letterSalSelector->get_htmlNameBase()], FILTER_SANITIZE_FULL_SPECIAL_CHARS), filter_var($_POST[$envSalSelector->get_htmlNameBase()], FILTER_SANITIZE_FULL_SPECIAL_CHARS), TRUE);

    if ($voldCat->get_andOr() == "or") {
        $anddChecked = "";
        $ordChecked = "checked='checked'";
    }

    if (isset($_POST["exDeceased"])) {
        $exDeceasedChecked = " checked='checked' ";
    }

    $donmarkup = $voldCat->get_reportMarkup();
    $donHdrMarkup = $voldCat->reportHdrMarkup;
}

$CampOpt = Campaign::CampaignSelOptionMarkup($dbh, '', FALSE);
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
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
            var listTable;
            var makeTable = <?php echo $makeTable; ?>;

            // Init j-query and the page blocker.
            function addCommas(nStr) {
                nStr += '';
                x = nStr.split('.');
                x1 = x[0];
                x2 = x.length > 1 ? '.' + x[1] : '';
                var rgx = /(\d+)(\d{3})/;
                while (rgx.test(x1)) {
                    x1 = x1.replace(rgx, '$1' + ',' + '$2');
                }
                return x1 + x2;
            }
            $(document).ready(function() {

            	$("input[type=submit], input[type=button]").button();

                var rollup = '<?php echo $rollup; ?>';

                if (makeTable == 2) {
                    $('div#printArea').css('display', 'block');

                $('#tblDonor').dataTable({
                    "displayLength": 50,
                    "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                    "dom": '<"top"lf><"hhk-overflow-x"rt><"bottom"ip>'
                });

                }
                $( ".ckdate" ).datepicker({
                    changeMonth: true,
                    changeYear: true,
                    autoSize: true
                });
                $('#Print_Button').click(function() {
                    $("div#printArea").printArea();
                });
                $('.dollarsOnly').change(function () {
                    var amt = this.value.indexOf('.');
                    if (amt > 0) {
                        this.value = this.value.substring(0, amt);
                    }
                });
                $('#overRideSal').change(function () {
                    if (this.checked) {
                        $('.hhk-hideSalutation').css("display","block");
                    } else {
                        $('.hhk-hideSalutation').css("display","none");
                    }
                });

                setSelectorOptions("selDonCamp", "donCampSelection");
                setSelectorOptions("selrollup", "hdnselrollup");
            });
            function setSelectorOptions(selCtrl, hddnCtrl) {
                var valStr = document.getElementById(hddnCtrl).value;
                if (valStr != null && valStr != "") {
                    var names = valStr.split('|');
                    var ctrl = document.getElementById(selCtrl);
                    for (i=0; i< ctrl.options.length; i++){
                        for(itm in names) {
                            if (names[itm] != "" && names[itm] == ctrl.options[i].value) {
                                ctrl.options[i].selected=true;
                            }
                            else
                                ctrl.options[i].removeAttribute("selected");
                        }
                    }
                }
            }

        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?> >
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
        	<h2><?php echo $wInit->pageHeading; ?> With Amounts</h2>
            <div id="vdonor" class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-3">
                <form id="fDonor" action="donationReport.php" method="post">
                    <table>
                        <tr>
                            <th>Campaigns</th>
                            <th>Amount Range</th>
                            <th>Date Range</th>
                            <th>Member Basis</th>
                        </tr><tr>
                            <td rowspan="4">
                                <select id="selDonCamp" name="selDonCamp[]" multiple="multiple" size="8"><option value='All' selected = 'selected'>All Campaigns</option><?php echo $CampOpt ?></select>
                                <input type="hidden" id="donCampSelection" value="<?php echo $SelectDonCamp ?>" />
                            </td>
                            <td>
                                <label for="txtmin" title="Whole Dollars Only">Min Amount: $</label><input type="text" class="dollarsOnly" title="Whole Dollars Only" name="txtmin" id="txtmin" size="7" value="<?php echo $minMkup; ?>" />
                            </td>
                            <td>Starting:
                                <input type="text" id ="sdate" name="sdate" class="ckdate" VALUE='<?php echo $sDate; ?>'/>
                            </td>
                            <td rowspan="4" style="vertical-align:top;"><?php echo $cbBasisDonor->createMarkup(); ?>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label for="txtmax" title="Whole Dollars Only">Max Amount: $</label><input type="text" class="dollarsOnly" title="Whole Dollars Only" name="txtmax" id="txtmax" size="7" value="<?php echo $maxMkup; ?>"/>
                            </td>
                            <td>Ending:
                                <INPUT TYPE='text' NAME='edate' id="edate" class="ckdate" VALUE='<?php echo $eDate; ?>'/>
                            </td>
                        </tr>
                        <tr>
                            <th style="max-height: 25px;">Report Type</th>
                            <td style="max-height: 25px; min-width: 185px;"><input type="checkbox" id="overRideSal" name="overRideSal" <?php echo $overRideSalChecked ?>/><label for="overRideSal"> Over-ride Salutations</label></td>
                        </tr>
                        <tr>
                            <td><select name="selrollup" id="selrollup" size="3">
                                    <option value="rd">Roll-up by Donor</option>
                                    <option value="in" selected="selected">Individual Donations</option>
                                    <option value="ft" >First Donation</option>
                                </select>
                                <input type="hidden" id="hdnselrollup" value="<?php echo $selectRoll ?>" />
                            </td>
                            <td><div class="hhk-hideSalutation"><table>
                                <tr><td style="border: none;"><?php echo "Sal:". $letterSalSelector->createMarkup(); ?></td></tr>
                                <tr><td style="border: none;"><?php echo "Env:" . $envSalSelector->createMarkup(); ?></td></tr>
                                </table></div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="text-align: center;"><em>Optionally select categories</em></td>
                        </tr>
                        <tr>
                            <th>Member Type</th>
                            <th>Combiner</th>
                            <th colspan="2">Member Status</th>
                        </tr>
                        <tr>
                            <td><?php echo $donSelMemberType->createMarkup(5, true); ?></td>
                            <td>And<input type="radio" name="rb_dandOr" value="and" <?php echo $anddChecked; ?> />
                                &nbsp;&nbsp;Or<input type="radio" name="rb_dandOr" value ="or" <?php echo $ordChecked; ?> />
                                <input type="hidden" id="hdnseldDor" value="" /></td>
                            <td  colspan="3"><input type="checkbox" name="exDeceased" id="exDeceased" <?php echo $exDeceasedChecked; ?>/><label for="exDeceased"> Include Deceased Members</label></td>
                        </tr>
                    </table>
                    <div class="hhk-flex mt-3" style="justify-content: space-evenly;">
						<input name="btnDonors" type="submit" value="Run Report" />
						<input name="btnstreamlined" type="submit" value="Run Streamlined Report" />
                        <input name="btnDonDL" type="submit" value="Download File" />
                    </div>
                </form>
            </div>
            <div id="printArea" style="display: none;" class="ui-widget ui-widget-content hhk-widget-content ui-corner-all mb-3">
                <div class="mb-3"><table><?php echo $donHdrMarkup; ?></table></div>
                <div class="mb-3"><input id='Print_Button' type='button' value='Print'/></div>
                <table id="tblDonor" class="display"><?php echo $donmarkup; ?></table>
            </div>

        </div>
    </body>
</html>
