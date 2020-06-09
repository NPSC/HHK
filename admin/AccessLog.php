<?php
/**
 * AccessLog.php
 *
-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2018 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");

$wInit = new webInit();
$dbh = $wInit->dbh;

$menuMarkup = $wInit->generatePageMenu();
$usernameList = array();
$actions = array();

$lookups = readGenLookups($dbh, "Web_User_Actions");
foreach($lookups as $action){
    $actions[] = ['id'=>$action['Code'], 'title'=>$action['Description']];
}

//disable inactive users
$stmt = $dbh->query("select * from w_users");
if ($stmt->rowCount() > 0) {
    $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach($users as $user){
        $user = UserClass::disableInactiveUser($dbh, $user);
        $usernameList[] = $user['User_Name'];
    }
}
?>
<!DOCTYPE html >
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo MULTISELECT_CSS; ?>
        <?php echo FAVICON; ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MD5_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo MULTISELECT_JS; ?>"></script>
        <script type="text/javascript" src="js/accessLog.js"></script>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
<?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div class="ui-widget ui-widget-content hhk-tdbox" style="float:left; padding: 10px;">
				<table style="width:100%;" class="display ignrSave" id="dtLog"></table>
            </div>
            <input type="hidden" id="usernames" value='<?php echo json_encode($usernameList); ?>'>
            <input type="hidden" id="actions" value='<?php echo json_encode($actions);?>'>
        </div>
    </body>
</html>
