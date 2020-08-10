<?php

use HHK\MailList;
use HHK\sec\{Session, WebInit};
use HHK\HTMLControls\selCtrl;
use HHK\Donation\Campaign;

/**
 * solicitReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require("AdminIncludes.php");

// require(CLASSES . "chkBoxCtrlClass.php");
// require(CLASSES . "selCtrl.php");
// require(CLASSES . "Campaign.php");
// require(CLASSES . "MailList.php");

$wInit = new webInit();
$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();
$uS = Session::getInstance();

$catSelMarkup = "";

$catagoryHeadertable = "";

// Selector Controls for Category section
$catInclCtrl = new selCtrl($dbh, 'Vol_Type', false, "selInType", true);
// add 'None' to control
$catInclCtrl->set_label('Everyone', '');

// Selector Exclude Controls for Category section
$catExclCtrl = new selCtrl($dbh, 'Vol_Type', false, "selExType", true);
// add 'None' to control
$catExclCtrl->set_label('None', '');


// campaign selectors
$campSelOptions = Campaign::CampaignSelOptionMarkup($dbh, '', FALSE);
$campSelExOpt = Campaign::CampaignSelOptionMarkup($dbh, '', FALSE);


// set Envelope and Salutation controls
$envNameCtrl = new selCtrl($dbh, 'Salutation', FALSE, "selEnvName", TRUE);
$envNameCtrl->set_value(TRUE, 'for');
$salNameCtrl = new selCtrl($dbh, "Salutation", FALSE, "selSalName", TRUE);
$salNameCtrl->set_value(TRUE, 'inf');

$report = '';
$refreshDate = 'Never';
$affectedRows = 0;

// create date of mail listing table
$stmt = $dbh->query("select Description from gen_lookups where Table_Name='Mail_List' and Code = 'Refresh_Date'");
$rows = $stmt->fetchAll(PDO::FETCH_NUM);
if (count($rows) > 0) {
    $refreshDate = date('M j, Y', strtotime($rows[0][0]));
}


if (isset($_POST['btnPrep'])) {

    // Load the table with fresh data
    $affectedRows = MailList::fillMailistTable($dbh, $uS->SolicitBuffer);

    if ($affectedRows > 0) {
        $dbh->exec("replace into gen_lookups (`Table_Name`, `Code`, `Description`) values ('Mail_List', 'Refresh_Date', '" . date('Y-m-d') . "')");
    }

}

// Postback logic
if (isset($_POST["btnDlSol"])) {

    ini_set('memory_limit', "128M");
    addslashesextended($_POST);

    $catInclCtrl->setReturnValues($_POST[$catInclCtrl->get_htmlNameBase()]);
    $catExclCtrl->setReturnValues($_POST[$catExclCtrl->get_htmlNameBase()]);
    $envNameCtrl->setReturnValues($_POST[$envNameCtrl->get_htmlNameBase()]);
    $salNameCtrl->setReturnValues($salNameCtrl->get_htmlNameBase());

    $report = SolicitReportGen::createSqlSelect($dbh, $_POST);

}

// Create category/code selector controls.
$catSelMarkup = "<td style='text-align: center;'>" . $catInclCtrl->createMarkup($catInclCtrl->get_rows(), true) . "</td>";
$catSelExcl = "<td style='text-align: center;'>" . $catExclCtrl->createMarkup($catExclCtrl->get_rows(), true) . "</td>";
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo NOTY_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MD5_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?> >
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h2><?php echo $wInit->pageHeading; ?></h2>
            <p>(Only lists members with valid street addresses)</p>
            <div id="vcategory" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail">
                <form id="form1" action="solicitReport.php" method="post">
                    <table><tr>
                            <td style="text-align: center;"><h3>Only These Categories</h3></td>
                            <td style="background-color:lightgrey; border-bottom-color: lightgrey; padding: 0 3px;"></td>
                            <td style="text-align: center;"><h3>Exclude Categories</h3></td>
                        </tr>
                        <tr>
                            <?php echo $catSelMarkup; ?>
                            <td style="background-color:lightgrey; border-bottom-color: lightgrey; padding: 0 3px;"></td>
                            <?php echo $catSelExcl; ?>
                        </tr>
                        <tr><td colspan="5" style="background-color:lightgrey; font-size: 3px;">&nbsp;</td></tr>
                        <tr>
                            <td style="text-align: center;"><h3>Only These Campaigns</h3></td>
                            <td style="background-color:lightgrey; border-bottom-color: lightgrey; padding: 0 3px;"></td>
                            <td style="text-align: center;"><h3>Exclude Campaigns</h3></td>
                        </tr>
                        <tr><td>
                                <select id="selCamp" name="selCamp[]" multiple="multiple" size="8"><option value='' selected = 'selected'>All</option><?php echo $campSelOptions; ?></select>
                                <input type="hidden" id="campSelection" value="" /></td>
                            <td style="background-color:lightgrey; border-bottom-color: lightgrey; padding: 0 3px;"></td>
                             <td>   <select id="selCampEx" name="selCampEx[]" multiple="multiple" size="8"><option value='' selected = 'selected'>None</option><?php echo $campSelExOpt; ?></select>
                                <input type="hidden" id="campSelectionEx" value="" /></td>
                        </tr>
                        <tr><td colspan="5" style="background-color:lightgrey; font-size: 3px;">&nbsp;</td></tr>
                    </table>
                    <table>
                        <tr><td colspan="4" style="text-align: center;"><h3>General Constraints</h3></td></tr>
                        <tr>
                            <th>Member Status</th>
                            <th>Member Basis</th>
                            <th>Envelope Name</th>
                            <th>Letter Name</th>
                        </tr><tr>
                            <td><input type="checkbox" id="cbActive" name="cbActive" checked="checked" /><label for="cbActive">Active</label><br/>
                                <input type="checkbox" id="cbInactive" name="cbInactive" /><label for="cbInactive">Inactive</label></td>
                            <td><input type="checkbox" id="cbInd" name="cbInd" checked="checked" /><label for="cbInd">Individual</label><br/>
                                <input type="checkbox" id="cbOrg" name="cbOrg" checked="checked" /><label for="cbOrg">Organization</label></td>
                            <td><?php echo $envNameCtrl->createMarkup($envNameCtrl->get_rows()); ?></td>
                            <td><?php echo $salNameCtrl->createMarkup($salNameCtrl->get_rows()); ?></td>
                        </tr>
                    </table>
                    <table><tr>
                            <td style="height:30px;">Last mail list refresh date: <span style="font-weight: bold;"><?php echo $refreshDate; ?></span></td>
                        </tr>
                        <tr><td style="text-align:center;">
                                <input type="submit" name="btnPrep" value="Prepare Address Table" />
                                <input type="submit" name="btnDlSol" value="Download to Excel File" />
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
            <div style="clear:both;"></div>
            <div id="submit"></div>
        </div>
    </body>
</html>
