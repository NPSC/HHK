<?php

use HHK\Donation\Campaign;
use HHK\AlertControl\AlertMessage;
use HHK\SysConst\CampaignType;
use HHK\Tables\EditRS;
use HHK\Tables\Donate\CampaignRS;
use HHK\sec\WebInit;
use HHK\HTMLControls\selCtrl;

/**
 * campaignEdit.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

 require ("AdminIncludes.php");

function saveCampaign(PDO $dbh, $campCode, $type, $post) {

    $campRS = new CampaignRS();

    if ($campCode != "vNew" && $campCode != "") {

        $campRS->Campaign_Code->setStoredVal($campCode);
        $cRows = EditRS::select($dbh, $campRS, array($campRS->Campaign_Code));
        if (count($cRows) > 0) {
            EditRS::loadRow($cRows[0], $campRS);
        } else {
            return "The Campaign Code was not found.";
        }
    }

    // Campaign Type
    if ($type == "") {
        $type = CampaignType::Normal;       // normal.
    }
    $campRS->Campaign_Type->setNewVal($type);

    // Title
    if (isset($post['txtTitle']) && $post['txtTitle'] != '') {
        $campRS->Title->setNewVal(filter_var($post['txtTitle'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    } else {
        return "The title must be specified.";
    }

    // Start and end dates
    if (isset($post['sdate']) && isset($post['edate'])) {

        $stDateStr = filter_var($post["sdate"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $enDateStr = filter_var($post["edate"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ($stDateStr == '' || $enDateStr == '') {
            return "Start and End dates must be specified.";
        }

        try {
            $stDate = new \DateTime($stDateStr);
            $endDate = new \DateTime($enDateStr);
        } catch (Exception $ex) {
            return "Undecipherable Start and/or End Dates.";
        }

        if ($stDate > $endDate) {
            return "The End date must be after the Start date.";
        }

        $campRS->Start_Date->setNewVal($stDate->format('Y-m-d'));
        $campRS->End_Date->setNewVal($endDate->format('Y-m-d'));
    } else {
        return "Start and End dates must be specified.";
    }

    // Min & max
    $min = filter_var($post["txtMin"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $max = filter_var($post["txtMax"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    // Some logic to manage max and min amounts
    if ($max < 0 || $min < 0) {
        return "Use only positive values for Min and Max donations";
    }
    if ($max > 0 && $min > $max) {
        return "Check your minimum and maximum donation amounts (Min must be less than Max).";
    }

    $campRS->Min_Donation->setNewVal($min);
    $campRS->Max_Donation->setNewVal($max);


    // Target and percent
    if (isset($post['txtTarget'])) {
        $campRS->Target->setNewVal(filter_var($post['txtTarget'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
    }
    if (isset($post['txtPercent'])) {
        $campRS->Percent_Cut->setNewVal(filter_var($post['txtPercent'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
    }

    if (isset($post['selStatus'])) {
        $campRS->Status->setNewVal(filter_var($post['selStatus'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    }
    if (isset($post['txtCat'])) {
        $campRS->Category->setNewVal(filter_var($post['txtCat'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    }
    if (isset($post['txtMergeCode'])) {
        $campRS->Campaign_Merge_Code->setNewVal(filter_var($post['txtMergeCode'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    }
    if (isset($post['txtDesc'])) {
        $campRS->Description->setNewVal(filter_var($post['txtDesc'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    }

    // if a new code
    if ($campCode == "vNew" || $campCode == '') {

        $dbh->query("CALL IncrementCounter('codes', @num);");
        foreach ($dbh->query("SELECT @num") as $row) {
            $rptId = $row[0];
        }
        if ($rptId == 0) {
            return "Event Repeater counter not set up.";
        }
        $campCode  = 'cp'.$rptId;

        $campRS->Campaign_Code->setNewVal($campCode);
        EditRS::insert($dbh, $campRS);
        return "Record Inserted";

    } else {

        EditRS::update($dbh, $campRS, array($campRS->Campaign_Code));
        return "Record Updated";
    }

    return "";

}

$wInit = new webInit();
$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;


$menuMarkup = $wInit->generatePageMenu();


$campCode = "";


//Check GET
if (isset($_GET["cp"])) {
     addslashesextended($_GET);
    $campCode = filter_var($_GET["cp"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

}


$selType = new selCtrl($dbh, "Campaign_Type", false, "selType", false, "", "Code");
$resultMessage = "";



// form1 save button:
if (isset($_POST["bttncamp"])) {
     addslashesextended($_POST);
    // validate and if okay, save data
    // if not okay, redisplay form with errors marked.
    $campAlert = new AlertMessage("campAlert");

    $selType->setReturnValues($_POST[$selType->get_htmlNameBase()]);
    $type = filter_var($_POST[$selType->get_htmlNameBase()], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $campCode = filter_var($_POST['selCamp'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $mes = saveCampaign($dbh, $campCode, $type, $_POST);

    $campAlert->set_Context(AlertMessage::Success);
    $campAlert->set_Text($mes);

    $resultMessage = $campAlert->createMarkup();
}




//
// Load the selector control with all the campaigns.
$CampOpt = Campaign::CampaignSelOptionMarkup($dbh, $campCode, TRUE, TRUE);

$campaign = new Campaign($dbh, $campCode);

$statusOpt = doLookups($dbh, "Campaign_Status", $campaign->get_status(), false);
$stDate = "";
$enDate = "";
$lastDate = "";

if ($campaign->get_startdate() != null)
    $stDate = date("m/d/Y", strtotime($campaign->get_startdate()));

if ($campaign->get_enddate() != null)
    $enDate = date("m/d/Y", strtotime($campaign->get_enddate()));

if ($campaign->get_lastupdated() != null)
    $lastDate = date("m/d/Y", strtotime($campaign->get_lastupdated()));

$selType->set_value(TRUE, $campaign->get_type());

?>
<!DOCTYPE html >
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>

        <script type="text/javascript">
    function hidePercent(ctrl) {
        if (ctrl.val() != 'pct') {
            // Hide the percent text box
            $('.hhk-hide-percent').css("display", "none");
        } else {
            $('.hhk-hide-percent').css("display", "table-cell");
        }
    }
    $(document).ready(function() {
        $( ".ckdate" ).datepicker({
            changeMonth: true,
            changeYear: true
        });
        $('#selType').change( function () {
            hidePercent($(this));
        });
        hidePercent($('#selType'));
        $('#selCamp').change(function () {
            window.location = "campaignEdit.php?cp=" + $(this).val();
        });
    });
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?>>
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">

            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div style="clear: both;">
                <?php echo $resultMessage ?>
            </div>
            <div class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-3">
                <form id="campForm" name="campForm" action="campaignEdit.php" method="post">
            	<div class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-3">
                    <span>Select a Campaign to Edit: </span>
                    <select style="width:230px" name="selCamp" id="selCamp"><?php echo $CampOpt ?></select>
            	</div>
                    <table>
                        <tr>
                            <td>
                                <div class="mainDiv">
                                    <table>
                                        <tr>
                                            <td><h4>Campaign Details</h4></td>
                                        </tr>
                                        <tr>
                                            <th>Title</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                        </tr>
                                        <tr>
                                            <td><input id="txtTitle" name="txtTitle" type="text" size="40" value="<?php echo $campaign->get_title(); ?>" /></td>
                                            <td><INPUT TYPE='text' NAME='sdate' class="ckdate" VALUE='<?php echo $stDate; ?>' size=10 />
                                            </td>
                                            <td><INPUT TYPE='text' NAME='edate' class="ckdate" VALUE='<?php echo $enDate; ?>' size=10 />
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="mainDiv">
                                    <table>
                                        <tr>
                                            <th>Min. Donation</th>
                                            <th>Max. Donation</th>
                                            <th>Goal</th>
                                            <td>&nbsp;</td>
                                            <th>Type</th>
                                            <th class='hhk-hide-percent'>Value</th>

<!--                                            <th class='ui-widget-header'><span class="lbl">Lump Sum Cost</span></th>-->
                                        </tr>
                                        <tr>
                                            <td>$<input id="txtMin" name="txtMin" type="text" size="10" value="<?php if ($campaign->get_mindonation() > 0) echo number_format($campaign->get_mindonation(), 2); ?>" /></td>
                                            <td>$<input id="txtMax" name="txtMax" type="text" size="10" value="<?php if ($campaign->get_maxdonation() > 0)echo number_format($campaign->get_maxdonation(), 2); ?>" /></td>
                                            <td>$<input id="txtTarget" name="txtTarget" type="text" size="10" value="<?php if ($campaign->get_target() > 0)echo number_format($campaign->get_target(), 2); ?>" /></td>
                                            <td>&nbsp;</td>
                                            <td><?php echo $selType->createMarkup(1); ?></td>
                                            <td class="hhk-hide-percent"><input id="txtPercent" name="txtPercent" type="text" size="10" value="<?php if ($campaign->get_percentCut() > 0) echo number_format($campaign->get_percentCut(), 2); ?>" />%</td>

<!--                                             <td class="tdBox">$<input id="txtLumpsum" name="txtLumpsum" type="text" size="10" value=""/></td>-->
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="mainDiv">
                                    <table>
                                        <tr>
                                            <th>Status</th>
                                            <th>Last Updated</th>
                                            <th>Updated By</th>
                                            <th>Category</th>
                                            <th>Mail Merge Code</th>
                                        </tr>
                                        <tr>
                                            <td><select id="selStatus" name="selStatus"><?php echo $statusOpt ?></select></td>
                                            <td><input type="text" class="ro"  size="10" readonly="readonly" value="<?php echo $lastDate; ?>" /></td>
                                            <td><input type="text" class="ro"  size="10" readonly="readonly" value="<?php echo $campaign->get_updatedby(); ?>"/></td>
                                            <td><input id="txtCat" name="txtCat" type="text" value="<?php echo $campaign->get_category(); ?>" /></td>
                                            <td><input id="txtMergeCode" name="txtMergeCode" type="text" value="<?php echo $campaign->get_mergeCode(); ?>" /></td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="mainDiv">
                                    <table>
                                        <tr>
                                            <th><span class="lbl">Description</span></th>
                                        </tr>
                                        <tr>
                                            <td><textarea id="txtDesc" name="txtDesc" rows="1" cols="70" ><?php echo $campaign->get_description(); ?></textarea></td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    </table>
                    <div class="hhk-flex mt-1" style="justify-content: space-evenly;">
                    	<input type="reset" value="Reset" class="ui-button ui-widget ui-corner-all"/>
                    	<input id="bttncamp" name="bttncamp" type="submit" value="Save" class="ui-button ui-widget ui-corner-all"/>
                    </div>
                </form>
            </div>

            <DIV ID="testdiv1" STYLE="position:absolute;visibility:hidden;background-color:white;"></DIV>
        </div>
    </body>
</html>
