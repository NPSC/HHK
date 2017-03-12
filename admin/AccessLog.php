<?php
/**
 * AccessLog.php
 *
-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2017 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");
require CLASSES . 'CreateMarkupFromDB.php';


$wInit = new webInit();
$dbh = $wInit->dbh;
$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

$menuMarkup = $wInit->generatePageMenu();

// View user log
$log = '';
$users = array();
$userNameDate = '';

if (isset($_POST['btnAccess'])) {

    $whereStr = '';
    $dte = filter_var($_POST['aclogdate'], FILTER_SANITIZE_STRING);

    if ($dte != '') {
        $userNameDate = date('M j, Y', strtotime($dte));
        $whereStr = " DATE(Access_Date) = DATE('" . date('Y-m-d', strtotime($dte)) . "') ";
    }

    $userStr = '';

    if (isset($_POST['selUsers'])) {

        $postUsers = filter_var_array($_POST['selUsers']);

        foreach ($postUsers as $u) {
            $userStr .= ($userStr == '' ? "'" : ",'") . $u . "'";
            $users[$u] = $u;
        }

        if ($userStr != '') {
            $userStr = " w.idName in (" . $userStr . ")";
        }
    }

    if ($whereStr != '' && $userStr != '') {
        $whereStr = " where " . $whereStr . ' and ' . $userStr;
    } else if ($whereStr != '' && $userStr == '') {
        $whereStr = " where " . $whereStr;
    } else if ($whereStr == '' && $userStr != '') {
        $whereStr = "where " . $userStr;
    }

    $stmt = $dbh->query("Select w.idName as Id, l.* from w_user_log l left join w_users w on l.Username = w.User_Name $whereStr order by Access_Date DESC Limit 100;");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $edRows = array();

    foreach ($rows as $r) {

        $r['Date'] = date('M j, Y H:i:s', strtotime($r['Access_Date']));

        unset($r['Session_Id']);
        unset($r['Access_Date']);
        unset($r['Page']);

        $edRows[] = $r;
    }

    $log = CreateMarkupFromDB::generateHTML_Table($edRows, 'userlog');

}

$usernames = HTMLSelector::generateMarkup(HTMLSelector::getLookups($dbh, "select idName, User_Name from w_users", $users, TRUE), array('name'=>'selUsers[]', 'multiple'=>'multiple', 'size'=>'5'));

?>
<!DOCTYPE html >
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />

        <?php echo DEFAULT_CSS; ?>

        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PAG_JS; ?>"></script>

        <script type="text/javascript">
$(document).ready(function() {

    $( "input.autoCal" ).datepicker({
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        dateFormat: 'M d, yy'
    });
});
        </script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?>>
<?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <form method="POST">
            <div class="ui-widget ui-widget-content hhk-tdbox" style="float:left;">
                <table>
                    <tr>
                        <td >Choose a date (leave empty for most recent entries):</td>
                        <td><input type="text" id ="aclogdate" class="autoCal" name="aclogdate" VALUE='<?php echo $userNameDate; ?>' /></td>
                    </tr>
                    <tr>
                        <td >Choose one or more usernames:</td>
                        <td><?php echo $usernames; ?></td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align: center;"><input name="btnAccess" id="btnAccess" type="submit" value="View Access Log" style="margin:3px;"/></td>
                    </tr>
                </table>

                <div style="margin-top:10px;">
                    <?php echo $log; ?>
                </div>

            </div>
            </form>
        </div>

    </body>
</html>
