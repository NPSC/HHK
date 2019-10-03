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
require CLASSES . 'CreateMarkupFromDB.php';


$wInit = new webInit();
$dbh = $wInit->dbh;

$menuMarkup = $wInit->generatePageMenu();

// View user log
$log = '';
$users = array();
$actions = array();
$userNameDate = '';

if (isset($_POST['btnAccess'])) {

    $whereStr = '';


    $dte = filter_var($_POST['aclogdate'], FILTER_SANITIZE_STRING);

    if ($dte != '') {
        $userNameDate = date('M j, Y', strtotime($dte));
        $whereStr = " and DATE(Access_Date) = DATE('" . date('Y-m-d', strtotime($dte)) . "') ";
    }

    if (isset($_POST['selUsers'])) {
        $userStr = '';
        $postUsers = filter_var_array($_POST['selUsers']);

        foreach ($postUsers as $u) {
            if ($u != '') {
                $userStr .= ($userStr == '' ? "'" : ",'") . $u . "'";
                $users[$u] = $u;
            }
        }

        if ($userStr != '') {
            $whereStr .= " and w.idName in (" . $userStr . ")";
        }
    }

    if (isset($_POST['selActions'])) {
        $userStr = '';
        $postActions = filter_var_array($_POST['selActions']);

        foreach ($postActions as $u) {
            if ($u != '') {
                $userStr .= ($userStr == '' ? "'" : ",'") . $u . "'";
                $actions[$u] = $u;
            }
        }

        if ($userStr != '') {
            $whereStr .= " and l.Action in (" . $userStr . ")";
        }
    }

    $stmt = $dbh->query("Select w.idName as Id, l.Username, l.IP, l.Access_Date, l.`Action` from w_user_log l left join w_users w on l.Username = w.User_Name WHERE 1=1 $whereStr order by Access_Date DESC Limit 100;");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $edRows = array();

    foreach ($rows as $r) {

        $r['Date'] = date('M j, Y H:i:s', strtotime($r['Access_Date']));

        if ($r['Id'] > 0) {
            $r['Id'] = HTMLContainer::generateMarkup('a', $r['Id'], array('href'=>'NameEdit.php?id='.$r['Id']));
        }

        unset($r['Access_Date']);

        $edRows[] = $r;
    }

    $log = CreateMarkupFromDB::generateHTML_Table($edRows, 'userlog');

}

$usernames = HTMLSelector::generateMarkup(HTMLSelector::getLookups($dbh, "select idName, User_Name from w_users", $users, TRUE), array('name'=>'selUsers[]', 'multiple'=>'multiple', 'size'=>'5'));

$actOpts = array(
    0=>array(0=>'L', 1=>'Logins'),
    1=>array(0=>'PS', 1=>'Set Password'),
    2=>array(0=>'PC', 1=>'Change Password'),
    3=>array(0=>'PL', 1=>'Lock out'),

);

$actionsel = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($actOpts, $actions, TRUE), array('name'=>'selActions[]', 'multiple'=>'multiple', 'size'=>'4'));
?>
<!DOCTYPE html >
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo FAVICON; ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

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
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
<?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <form method="POST">
            <div class="ui-widget ui-widget-content hhk-tdbox" style="float:left;">
                <table>
                    <tr>
                        <td>Choose a date (leave empty for most recent entries):</td>
                        <td><input type="text" id ="aclogdate" class="autoCal" name="aclogdate" VALUE='<?php echo $userNameDate; ?>' /></td>
                    </tr>
                    <tr>
                        <td >Choose one or more usernames:</td>
                        <td><?php echo $usernames; ?></td>

                    </tr>
                    <tr>
                        <td >Choose one or more Actions</td>
                        <td><?php echo $actionsel; ?></td>
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
