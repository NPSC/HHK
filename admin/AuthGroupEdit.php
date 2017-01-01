<?php
/**
 * AuthGroupEdit.php
 *
-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2017 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK
 */
require_once ("AdminIncludes.php");

require_once (DB_TABLES . 'WebSecRS.php');


$wInit = new webInit();

$dbh = $wInit->dbh;


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
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />
<?php echo TOP_NAV_CSS; ?>
        <link href="css/default.css" rel="stylesheet" type="text/css" />
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS ?>"></script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?>>
        <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div class="ui-widget ui-widget-content ui-corner-all" style="font-size:0.95em; float:left; padding: 0.7em 1.0em;">
                <form method="POST" action="AuthGroupEdit.php" name="form1">
                    <?php echo $tbl->generateMarkup(); ?>
                 <span style="float:right;margin:10px;">
                    <input type="submit" name="btnSave" value="Save"/>
                </span>
                </form>
            </div>

        </div>
    </body>
</html>

