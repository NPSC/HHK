<?php
/**
 * AuthGroupEdit.php
 *
-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2018 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK
 */

require ("AdminIncludes.php");

require (DB_TABLES . 'WebSecRS.php');
require (SEC . 'UserClass.php');


$wInit = new webInit();

$dbh = $wInit->dbh;
// get session instance
$uS = Session::getInstance();

$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;
$menuMarkup = $wInit->generatePageMenu();


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

                if (isset($_POST[$wgRS->Cookie_Restricted->getColUnticked()][$gc])) {
                    $wgRS->Cookie_Restricted->setNewVal(1);
                } else {
                    $wgRS->Cookie_Restricted->setNewVal(0);
                }

                $wgRS->Updated_By->setNewVal($uS->username);
                $wgRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

                EditRS::update($dbh, $wgRS, array($wgRS->Group_Code));
            }
        }
    }
}


$cookieReply = "No access cookie found on this device. ";

if (isset($_COOKIE['housepc'])) {
    $cookieReply = "Cookie-Restricted Access is set for this device. ";
}

if (isset($_POST['setCookie']) && SecurityComponent::is_Admin()) {
    $accordIndex = 7;

    if (UserClass::setCookieAccess($wInit->page->getRootPath(), TRUE)) {
        $cookieReply = "Cookie-Restricted Access is set for this device.";
    } else {
        $cookieReply = "Failed to set the access cookie!";
    }

} else if (isset($_POST['removeCookie']) && isset($_COOKIE['housepc'])) {
    $accordIndex = 7;

    if (UserClass::setCookieAccess($wInit->page->getRootPath(), FALSE) ) {
        $cookieReply = "Cookie-Restricted Access is removed from this device.";
    } else {
        $cookieReply .= " Failed to remove the access cookie!";
    }
}

$tbl = new HTMLTable();

$wgroupRS = new W_groupsRS();
$rows = EditRS::select($dbh, $wgroupRS, array());

foreach ($rows as $r) {

    EditRS::loadRow($r, $wgroupRS);

    $cde = '[' . $wgroupRS->Group_Code->getStoredVal() . ']';

    $crAttr = array(
        'name' => $wgroupRS->Cookie_Restricted->getColUnticked().$cde,
        'type' => 'checkbox'
    );

    if ($wgroupRS->Cookie_Restricted->getStoredVal() == 1) {
        $crAttr['checked'] = 'checked';
    }

    $tbl->addBodyTr(
            HTMLTable::makeTd(HTMLInput::generateMarkup($wgroupRS->Group_Code->getStoredVal(), array('name' => $wgroupRS->Group_Code->getColUnticked().$cde, 'size' => '4', 'readonly'=>'readonly', 'style'=>'border:none;')))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($wgroupRS->Title->getStoredVal(), array('name' => $wgroupRS->Title->getColUnticked().$cde, 'size' => '20')))
            . HTMLTable::makeTd(HTMLInput::generateMarkup('', $crAttr), array('style' => 'text-align:center;'))
            . HTMLTable::makeTd(HTMLContainer::generateMarkup('textarea', $wgroupRS->Description->getStoredVal(), array('name' => $wgroupRS->Description->getColUnticked().$cde, 'rows' => '1', 'cols' => '40')))
    );
}

$tbl->addHeaderTr(HTMLTable::makeTh('Group Code') . HTMLTable::makeTh('Title') . HTMLTable::makeTh('Cookie-Rrestricted') . HTMLTable::makeTh('Description'));


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
    "http://www.w3.org/TR/html4/loose.dtd"><html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo FAVICON; ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript">
            var table, accordIndex;
            $(document).ready(function() {
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
                     <input name="setCookie" type="submit" value="Set PC Access" style="margin:10px;"/><input name="removeCookie" type="submit" value="Remove Access" style="margin:10px;"/>
                 <span style="float:right;margin:10px;">

                    <input type="submit" name="btnSave" value="Save"/>
                </span>
                </form>
                <h3><?php echo $cookieReply; ?></h3>
            </div>

        </div>
    </body>
</html>

