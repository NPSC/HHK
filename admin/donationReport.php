<?php
/**
 * donationReport.php
 *
 * @category  Report
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
require_once ("AdminIncludes.php");

require_once(CLASSES . "chkBoxCtrlClass.php");
require_once(CLASSES . "selCtrl.php");
require_once(CLASSES . 'Campaign.php');

$wInit = new webInit();
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
$cbBasisDonor = new chkBoxCtrlClass($dbh, "Member_Basis", "Include", "cbDonBasis", true);
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
if (isset($_POST["btnDonors"]) || isset($_POST["btnDonDL"])) {
#--------------------------------------------------------------
    require_once("functions" . DS . "donorReportManager.php");
    require_once(CLASSES . "OpenXML.php");
    require_once("classes" . DS . "VolCats.php");
    require_once("classes" . DS . "Salutation.php");

    //$selectedTab = "2";
    $makeTable = 2;

    // collect the parameters
    $maxMkup = filter_var($_POST["txtmax"], FILTER_SANITIZE_NUMBER_INT);
    $minMkup = filter_var($_POST["txtmin"], FILTER_SANITIZE_NUMBER_INT);
    $sDate = filter_var($_POST["sdate"], FILTER_SANITIZE_STRING);
    $eDate = filter_var($_POST["edate"], FILTER_SANITIZE_STRING);

    $selectRoll = filter_var($_POST["selrollup"], FILTER_SANITIZE_STRING);
    if ($selectRoll == "rd") {
        $rollup = 't';
    } else {
        $rollup = 'f';
    }

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
    $voldCat = prepDonorRpt($dbh, $cbBasisDonor, $donSelMemberType, $overrideSalutations, filter_var($_POST[$letterSalSelector->get_htmlNameBase()], FILTER_SANITIZE_STRING), filter_var($_POST[$envSalSelector->get_htmlNameBase()], FILTER_SANITIZE_STRING), TRUE);

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
                var rollup = '<?php echo $rollup; ?>';
                if (listTable)
                    listTable.fnDestroy();

                if (makeTable == 2) {
                    $('div#printArea').css('display', 'block');
                    try {
                        listTable = $('#tblDonor').dataTable({
//                            "aoColumnDefs": [
//                                {"aTargets": [ 4 ],
//                                 "mDataProp": function ( source, type, val ) {
//
//                                    if (type === 'set') {
//                                        source.g = val;
//                                        if (rollup == 'f') {
//                                            // Store the computed dislay and filter values for efficiency
//                                            source.g_display = val=="" ? "" : "$" + addCommas(val);
//                                            source.g_filter  = val=="" ? "" : "$" + addCommas(val) + " " + val;
//                                        } else {
//                                            source.g_display = val;
//                                            source.g_filter  = val;
//                                        }
//                                        return;
//                                    }
//                                    else if (type === 'display') {
//                                        return source.g_display;
//                                    }
//                                    else if (type === 'filter') {
//                                        return source.g_filter;
//                                    }
//                                    // 'sort' and 'type' both just use the integer
//                                    return source.g;
//                                }},
//                                {"aTargets": [ 5 ],
//                                 "mDataProp": function ( source, type, val ) {
//
//                                    if (type === 'set') {
//                                        source.tot = val;
//                                        if (rollup == 't') {
//                                            source.tot_display = val=="" ? "" : "$" + addCommas(val);
//                                            source.tot_filter  = val=="" ? "" : "$" + addCommas(val) + " " + val;
//                                        } else {
//                                            // Store the computed dislay and filter values for efficiency
//                                            source.tot_display = val=="0.0" ? "" : val + '%';
//                                            source.tot_filter  = val=="0.0" ? "" : val + " " + val;
//                                         }
//                                        return;
//                                    }
//                                    else if (type === 'display') {
//                                        return source.tot_display;
//                                    }
//                                    else if (type === 'filter') {
//                                        return source.tot_filter;
//                                    }
//                                    // 'sort' and 'type' both just use the integer
//                                    return source.tot;
//                                }},
//                                {"aTargets": [ 6 ],
//                                 "mDataProp": function ( source, type, val ) {
//
//                                    if (type === 'set') {
//                                        source.hou = val;
//                                        // Store the computed dislay and filter values for efficiency
//                                        source.hou_display = val=="" ? "" : "$" + addCommas(val);
//                                        source.hou_filter  = val=="" ? "" : "$" + addCommas(val) + " " + val;
//                                        return;
//                                    }
//                                    else if (type === 'display') {
//                                        return source.hou_display;
//                                    }
//                                    else if (type === 'filter') {
//                                        return source.hou_filter;
//                                    }
//                                    // 'sort' and 'type' both just use the integer
//                                    return source.hou;
//                                }}
//                            ],
                            "iDisplayLength": 50,
                            "aLengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
                            "dom": '<"top"ilf>rt<"bottom"ip>'
                        });
                    } catch (err) { alert(err);}
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
            <div id="vdonor" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail">
                <form id="fDonor" action="donationReport.php" method="post">
                    <table>
                        <tr>
                            <td colspan="4"><h2>Donations With Amounts</h2></td>
                        </tr>
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
                            <td><select name="selrollup" id="selrollup" size="2">
                                    <option value="rd">Roll-up by Donor</option>
                                    <option value="in" selected="selected">Individual Donations</option>
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
                        <tr>
                            <td colspan="4" style="text-align:right;"><input name="btnDonors" type="submit" value="Run Report" />
                                <input name="btnDonDL" type="submit" value="Download File" /></td>
                        </tr>
                    </table>
                </form>
            </div>
            <div style="clear:both;"></div>
            <div id="printArea" style="display: none; margin-top: 10px;" class="ui-widget ui-widget-content">
                <div style="float: left;"><table style="margin-bottom:10px;"><?php echo $donHdrMarkup; ?></table></div>
                <div style="float: left;">&nbsp;<input id='Print_Button' type='button' value='Print'/></div>
                <div style="clear:both;"></div>
                <table id="tblDonor" class="display"><?php echo $donmarkup; ?></table>
            </div>

        </div>
    </body>
</html>
