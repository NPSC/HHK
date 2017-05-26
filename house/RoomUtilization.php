<?php
/**
 * RoomUtilization.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

require (CLASSES . 'History.php');

require (HOUSE . 'VisitLog.php');
require (HOUSE . 'RoomLog.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'ResourceView.php');
require (HOUSE . 'RoomReport.php');

require(CLASSES . "chkBoxCtrlClass.php");
require(CLASSES . "selCtrl.php");


try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

$dbh = $wInit->dbh;
$pageTitle = $wInit->pageTitle;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();

// Load the session with member - based lookups
$wInit->sessionLoadGenLkUps();
$wInit->sessionLoadGuestLkUps();


$output = "";
$year = date('Y');
$month = date('n');
$type = 'm';

$gArray = array(
    1 => array(1, 'January'),
    2 => array(2, 'February'),
    3 => array(3, 'March'), 4 => array(4, 'April'), 5 => array(5, 'May'), 6 => array(6, 'June'),
    7 => array(7, 'July'), 8 => array(8, 'August'), 9 => array(9, 'September'), 10 => array(10, 'October'), 11 => array(11, 'November'), 12 => array(12, 'December'));

if (isset($_POST['btnByGuest']) || isset($_POST['btnByRoom'])) {
    addslashesextended($_POST);

    if (isset($_POST['selIntMonth'])) {
        $month = intval(filter_var($_POST['selIntMonth'], FILTER_SANITIZE_NUMBER_INT), 10);
    }

    if (isset($_POST['selIntYear'])) {
        $year = intval(filter_var($_POST['selIntYear'], FILTER_SANITIZE_NUMBER_INT), 10);
    }

    if (isset($_POST['rbType'])) {
        $type = filter_var($_POST['rbType'], FILTER_SANITIZE_STRING);
        if ($type != 'y') {
            $type = 'm';
        }
    }

    $start = $year . '-' . $month . '-01';

    $endDate = new DateTime($start);
    $endDate->add(new DateInterval('P1M'));
    $endDate->sub(new DateInterval("P1D"));

    if (isset($_POST['btnByGuest'])) {
        $output = RoomReport::roomNOR($dbh, $start, $endDate->format('Y-m-d'), $type);
    } else {
        $output = RoomReport::rescUtilization($dbh, $start, $endDate->format('Y-m-d'), $type);
    }
}

$monthSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($gArray, $month, FALSE), array('name' => 'selIntMonth'));
$yearSelector = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(getYearArray(), $year), array('name' => 'selIntYear'));

$attrs = array('type'=>'radio', 'name'=>'rbType', 'id'=>'rbTypey');
if ($type == 'y') {
    $attrs['checked'] = 'checked';
}
$rbByYear = HTMLInput::generateMarkup('y', $attrs);

$attrs['id'] = 'rbTypem';
if ($type == 'm') {
    $attrs['checked'] = 'checked';
} else {
    unset($attrs['checked']);
}

$rbByMonth = HTMLInput::generateMarkup('m', $attrs);

$resultMessage = "";
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS ?>
        <link rel="icon" type="image/png" href="../images/hhkIcon.png" />
        
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PAG_JS; ?>"></script>
        <script type="text/javascript">
        $(document).ready(function() {
            "use strict";
            
            $('#btnByGuest, #btnByRoom').button();
        });
        </script>
    </head>
    <body <?php if ($wInit->testVersion) {
            echo "class='testbody'";
        } ?>>
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
<?php echo $resultMessage ?>
            <div style="clear:both;"></div>

            <form action="RoomUtilization.php" method="post"  id="form1" name="form1" >
                <div class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-member-detail hhk-visitdialog">
                    <table>
                        <tr>
                            <th colspan="2">Time Period</th>
                        </tr>
                        <tr>
                            <th>Month</th>
                            <th>Year</th>
                        </tr>

                        <tr>
                            <td><?php echo $monthSelector; ?></td>
                            <td><?php echo $yearSelector; ?></td>
                        </tr>
                        <tr>
                            <td colspan="2"><?php echo $rbByYear; ?><label for="rbTypey"> Year by Month</label></td>
                        </tr>
                        <tr>
                            <td colspan="2"><?php echo $rbByMonth; ?><label for="rbTypem"> Month by Day</label></td>
                        </tr>
                    </table>
                    <input type="submit" name="btnByGuest" value="By Guest" id="btnByGuest" />
                    <input type="submit" name="btnByRoom" value="By Room" id="btnByRoom" />
                </div>
            </form>
            <div style="clear:both;"></div>
            <div id="rmMgmt" style="float: left; margin-top: 30px; margin-bottom: 10px; font-size: .9em;" class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog">
<?php echo $output; ?>
            </div>

        </div>  <!-- div id="contentDiv"-->
    </body>
</html>
