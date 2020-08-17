<?php

use HHK\sec\{SecurityComponent, Session, UserClass, WebInit};
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\{HTMLInput, HTMLSelector, HTMLTable};
use HHK\Tables\EditRS;
use HHK\Tables\WebSec\{W_groupsRS, W_auth_ipRS};

/**
 * AuthGroupEdit.php
 *
  -- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
  -- @copyright 2010-2018 <nonprofitsoftwarecorp.org>
  -- @license   MIT
  -- @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");

$wInit = new webInit();

$dbh = $wInit->dbh;
// get session instance
$uS = Session::getInstance();

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;
$menuMarkup = $wInit->generatePageMenu();

$ipReply = "";
$revokeReply = "";

if (isset($_POST['btnSave'])) {

    $uS = Session::getInstance();

    if (isset($_POST['Group_Code']) && is_array($_POST['Group_Code'])) {

        foreach ($_POST['Group_Code'] as $c) {

            $gc = filter_var($c, FILTER_SANITIZE_STRING);

            $wgRS = new W_groupsRS();
            $wgRS->Group_Code->setStoredVal($gc);
            $wgRows = EditRS::select($dbh, $wgRS, array($wgRS->Group_Code));

            if (count($wgRows) == 1) {

                EditRS::loadRow($wgRows[0], $wgRS);

                $wgRS->Title->setNewVal(filter_var($_POST[$wgRS->Title->getColUnticked()][$gc], FILTER_SANITIZE_STRING));
                $wgRS->Description->setNewVal(filter_var($_POST[$wgRS->Description->getColUnticked()][$gc], FILTER_SANITIZE_STRING));
                
                if (isset($_POST[$wgRS->IP_Restricted->getColUnticked()][$gc]) && count($_POST[$wgRS->IP_Restricted->getColUnticked()][$gc]) > 0) {
                    $wgRS->IP_Restricted->setNewVal(1);
                }else{
                    $wgRS->IP_Restricted->setNewVal(0);
                }

                $wgRS->Updated_By->setNewVal($uS->username);
                $wgRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

                EditRS::update($dbh, $wgRS, array($wgRS->Group_Code));
            }

            //set ip access
            $query = "DELETE FROM `w_group_ip` WHERE `Group_Code` = '$gc'";
            $stmt = $dbh->prepare($query);
            $stmt->execute();

            if (isset($_POST[$wgRS->IP_Restricted->getColUnticked()][$gc]) && count($_POST[$wgRS->IP_Restricted->getColUnticked()][$gc]) > 0) {

                foreach ($_POST[$wgRS->IP_Restricted->getColUnticked()][$gc] as $ipAddr) {
                    $query = "INSERT INTO `w_group_ip` (`Group_Code`, `IP_addr`) VALUES('$gc', '$ipAddr');";
                    $stmt = $dbh->prepare($query);
                    $stmt->execute();
                }
            }
        }
    }

    if (isset($_POST['ip_title']) && $_POST['ip_cidr'] && is_array($_POST['ip_cidr'])) {
        
        foreach ($_POST['ip_cidr'] as $ip => $cidr) {
            $wauthipRS = new W_auth_ipRS();
            $wauthipRS->IP_addr->setStoredVal($ip);
            $wauthipRows = EditRS::select($dbh, $wauthipRS, array($wauthipRS->IP_addr));

            if (count($wauthipRows) == 1) {
                EditRS::loadRow($wauthipRows[0], $wauthipRS);

                $wauthipRS->Title->setNewVal(filter_var($_POST['ip_title'][$ip], FILTER_SANITIZE_STRING));
                $wauthipRS->cidr->setNewVal(filter_var($_POST['ip_cidr'][$ip], FILTER_SANITIZE_NUMBER_INT));
                $wauthipRS->Updated_By->setNewVal($uS->username);
                $wauthipRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

                EditRS::update($dbh, $wauthipRS, array($wauthipRS->IP_addr));
            }
        }
    }
}

if (isset($_POST['ip_revoke']) && SecurityComponent::is_Admin()) {
    $ipAddr = array_keys($_POST['ip_revoke'])[0];
    $revokeReply = UserClass::revokePCAccess($dbh, $ipAddr);
}

if (isset($_POST['setAccess']) && SecurityComponent::is_Admin()) {
    $ipReply = UserClass::setPCAccess($dbh, $_POST['PC_name']);
}

$tbl = new HTMLTable();

$wgroupRS = new W_groupsRS();
$rows = EditRS::select($dbh, $wgroupRS, array());

foreach ($rows as $r) {

    EditRS::loadRow($r, $wgroupRS);

    //get IPs
    $gc = $wgroupRS->Group_Code->getStoredVal();
    $query = "SELECT `IP_addr` FROM `w_group_ip` where `Group_Code` = '$gc';";
    $stmt = $dbh->prepare($query);
    $stmt->execute();
    $ips = $stmt->fetchAll();
    $selected = array();
    foreach ($ips as $ip) {
        $selected[$ip['IP_addr']] = $ip['IP_addr'];
    }

    $cde = '[' . $wgroupRS->Group_Code->getStoredVal() . ']';

    $iprAttr = array(
        'name' => $wgroupRS->IP_Restricted->getColUnticked() . $cde . '[]',
        'multiple' => 'multiple',
        'class' => 'hhk-multisel',
        'style' => 'display: none'
    );

    //build ip list
    $ipListMarkup = HTMLSelector::getLookups($dbh, "SELECT IP_addr, title from w_auth_ip", $selected, FALSE);

    $tbl->addBodyTr(
            HTMLTable::makeTd(HTMLInput::generateMarkup($wgroupRS->Group_Code->getStoredVal(), array('name' => $wgroupRS->Group_Code->getColUnticked() . $cde, 'size' => '4', 'readonly' => 'readonly', 'style' => 'border:none;')))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($wgroupRS->Title->getStoredVal(), array('name' => $wgroupRS->Title->getColUnticked() . $cde, 'size' => '20')))
            . HTMLTable::makeTd(HTMLSelector::generateMarkup($ipListMarkup, $iprAttr), array('style' => 'text-align:center;'))
            . HTMLTable::makeTd(HTMLContainer::generateMarkup('textarea', $wgroupRS->Description->getStoredVal(), array('name' => $wgroupRS->Description->getColUnticked() . $cde, 'rows' => '1', 'cols' => '40')))
    );
}

$tbl->addHeaderTr(HTMLTable::makeTh('Group Code') . HTMLTable::makeTh('Title') . HTMLTable::makeTh('Device-Restricted') . HTMLTable::makeTh('Description'));

//build IP restrictions table
$ip_tbl = new HTMLTable();

$wauthipRS = new W_auth_ipRS();
$iprows = EditRS::select($dbh, $wauthipRS, array());
if (count($iprows) == 0) {
    $ip_tbl->addBodyTr(
            HTMLTable::makeTd("No IP Addresses found", array('colspan' => '4'))
    );
}
foreach ($iprows as $r) {

    EditRS::loadRow($r, $wauthipRS);

    $cde = '[' . $wauthipRS->IP_addr->getStoredVal() . ']';

    $ip_tbl->addBodyTr(
            HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'ip_title' . $cde, 'value' => $wauthipRS->Title->getStoredVal(), 'type' => 'text')))
            . HTMLTable::makeTd($wauthipRS->IP_addr->getStoredVal())
            . HTMLTable::makeTd(" / " . HTMLInput::generateMarkup('', array('name' => 'ip_cidr' . $cde, 'value' => $wauthipRS->cidr->getStoredVal(), 'type' => 'number', 'max' => '32', 'min' => '1', 'style' => 'width: 3em;')))
            . HTMLTable::makeTd(HTMLInput::generateMarkup('Revoke', array('name' => 'ip_revoke' . $cde, 'value' => $cde, 'type' => 'submit')))
    );
}

$ip_tbl->addHeaderTr(HTMLTable::makeTh('Name') . HTMLTable::makeTh('IP Address') . HTMLTable::makeTh('CIDR') . HTMLTable::makeTh('Revoke'));
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
    "http://www.w3.org/TR/html4/loose.dtd"><html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
<?php echo JQ_UI_CSS; ?>
<?php echo DEFAULT_CSS; ?>
<?php echo FAVICON; ?>
<?php echo MULTISELECT_CSS; ?>
<?php echo NOTY_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MULTISELECT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MD5_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript">
            var table, accordIndex;
            $(document).ready(function () {
                $('input:submit').button();
            });

        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?>>
<?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div class="ui-widget ui-widget-content ui-corner-all" style="font-size:0.95em; float:left; padding: 0.7em 1.0em;">
                <form method="POST" action="AuthGroupEdit.php" name="form1">
        <?php echo $tbl->generateMarkup(); ?>
                    <div style="display: flex; margin-top: 10px;">
                        <div class="ui-widget ui-widget-content ui-corner-all" style="padding: 10px; margin: 10px">
                            <h3 style="margin-bottom: 10px;">Authorized IP Addresses</h3>
<?php if ($revokeReply) { ?>
                                <p style="margin-bottom: 10px;"> <?php echo $revokeReply; ?></p>
                    <?php } ?>
<?php echo $ip_tbl->generateMarkup(); ?>
                            <p style="margin-top: 10px;">Note: Only change the CIDR value if you understand what it means.</p>
                        </div>
                        <div class="ui-widget ui-widget-content ui-corner-all" style="padding: 10px; margin: 10px;">
                            <h3 style="margin-bottom: 10px;">This PC</h3>
                            <?php if (UserClass::checkPCAccess($dbh)) { ?>
                                <p style="margin-bottom: 10px;">This PC is authorized</p>
<?php } else { ?>
                                <p><?php echo $ipReply; ?></p>
                                <p style="margin-bottom: 10px;">Current IP Address: <strong><?php echo UserClass::getRemoteIp(); ?></strong></p>
                                <input name="PC_name" type="text" placeholder="Enter name for this PC" style="display: block; padding: 5px;">
                                <input name="setAccess" type="submit" value="Set PC Access" style="margin:10px;"/>
                            <?php } ?>
                        </div>
                    </div>
                    <span style="float:right;margin:10px;">
                        <input type="submit" name="btnSave" value="Save"/>
                    </span>
                </form>
            </div>
        </div>
        <script type="text/javascript">
            $(document).ready(function () {
                $('select.hhk-multisel').each(function () {
                    $(this).multiselect({
                        selectedList: 3
                    });
                });
            });
        </script>
    </body>
</html>

