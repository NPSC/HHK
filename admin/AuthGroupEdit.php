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

// Get Client IP
function getClientIP(){       
     if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)){
            return  $_SERVER["HTTP_X_FORWARDED_FOR"];  
     }else if (array_key_exists('REMOTE_ADDR', $_SERVER)) { 
            return $_SERVER["REMOTE_ADDR"]; 
     }else if (array_key_exists('HTTP_CLIENT_IP', $_SERVER)) {
            return $_SERVER["HTTP_CLIENT_IP"]; 
     } 

     return '';
}

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

				//Cookie restriction
                if (isset($_POST[$wgRS->Cookie_Restricted->getColUnticked()][$gc])) {
                    $wgRS->Cookie_Restricted->setNewVal(1);
                } else {
                    $wgRS->Cookie_Restricted->setNewVal(0);
                }
                
                //IP restriction
                if (isset($_POST[$wgRS->IP_Restricted->getColUnticked()][$gc])) {
                    $wgRS->IP_Restricted->setNewVal(1);
                } else {
                    $wgRS->IP_Restricted->setNewVal(0);
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



if (isset($_POST['setIP']) && SecurityComponent::is_Admin()) {
    $ipRS = new W_auth_ipRS();
    $ipRS->IP->setNewVal(getClientIP());
	$id = EditRS::insert($dbh, $ipRS);

    if (count(EditRS::select($dbh, $ipRS, array(getClientIP()))) > 0) {
        $ipReply = "IP-Restricted Access is set for this device.";
    } else {
        $ipReply = "Failed to get IP address!";
    }

} else if (isset($_POST['removeIP'])) {
    

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
    
    $iprAttr = array(
        'name' => $wgroupRS->IP_Restricted->getColUnticked().$cde,
        'type' => 'checkbox'
    );

    if ($wgroupRS->IP_Restricted->getStoredVal() == 1) {
        $iprAttr['checked'] = 'checked';
    }

    $tbl->addBodyTr(
            HTMLTable::makeTd(HTMLInput::generateMarkup($wgroupRS->Group_Code->getStoredVal(), array('name' => $wgroupRS->Group_Code->getColUnticked().$cde, 'size' => '4', 'readonly'=>'readonly', 'style'=>'border:none;')))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($wgroupRS->Title->getStoredVal(), array('name' => $wgroupRS->Title->getColUnticked().$cde, 'size' => '20')))
            . HTMLTable::makeTd(HTMLInput::generateMarkup('', $crAttr), array('style' => 'text-align:center;'))
            . HTMLTable::makeTd(HTMLInput::generateMarkup('', $iprAttr), array('style' => 'text-align:center;'))
            . HTMLTable::makeTd(HTMLContainer::generateMarkup('textarea', $wgroupRS->Description->getStoredVal(), array('name' => $wgroupRS->Description->getColUnticked().$cde, 'rows' => '1', 'cols' => '40')))
    );
}

$tbl->addHeaderTr(HTMLTable::makeTh('Group Code') . HTMLTable::makeTh('Title') . HTMLTable::makeTh('Cookie-Restricted') . HTMLTable::makeTh('IP-Restricted') . HTMLTable::makeTh('Description'));

//build IP restrictions table
$ip_tbl = new HTMLTable();

$wauthipRS = new W_auth_ipRS();
$iprows = EditRS::select($dbh, $wauthipRS, array());

foreach ($iprows as $r) {

    EditRS::loadRow($r, $wauthipRS);

	$cde = '[' . $wauthipRS->IP->getStoredVal() . ']';

    $ip_tbl->addBodyTr(
        HTMLTable::makeTd($wauthipRS->IP->getStoredVal())
        . HTMLTable::makeTd(HTMLInput::generateMarkup('Revoke', array('name' => 'ip_revoke'.$cde, 'type' => 'submit')))

    );
}

$ip_tbl->addHeaderTr(HTMLTable::makeTh('IP Address') . HTMLTable::makeTh('Revoke'));


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
                    <div class="ui-widget ui-widget-content ui-corner-all" style="margin: 0.7em 0em">
	                    <h3>Cookie Restrictions</h3>
						<input name="setCookie" type="submit" value="Set PC Access" style="margin:10px;"/><input name="removeCookie" type="submit" value="Remove Access" style="margin:10px;"/><strong><?php echo $cookieReply; ?></strong>
                    </div>
                    <div class="ui-widget ui-widget-content ui-corner-all" style="margin: 0.7em 0em">
	                    <h3>IP Address Restrictions</h3>
						<input name="setIP" type="submit" value="Set PC Access" style="margin:10px;"/><input name="removeIP" type="submit" value="Remove Access" style="margin:10px;"/> <?php echo getClientIP(); ?> <strong><?php echo $ipReply; ?></strong>
                     <div style="margin: 10px;">
	                     <h4 style="margin-bottom: 10px;">Authorized IP Addresses</h4>
                     	<?php echo $ip_tbl->generateMarkup(); ?>
                     </div>
                    </div>
                 <span style="float:right;margin:10px;">

                    <input type="submit" name="btnSave" value="Save"/>
                </span>
                </form>
            </div>

        </div>
    </body>
</html>

