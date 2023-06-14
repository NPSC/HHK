<?php
use HHK\sec\WebInit;
use HHK\sec\Session;
use HHK\Config_Lite\Config_Lite;
use HHK\Member\Role\AbstractRole;
use HHK\House\Reservation\Reservation_1;
use HHK\SysConst\ReservationStatus;
use HHK\HTMLControls\HTMLContainer;
use HHK\sec\Labels;

/**
 * CheckIn.php
 *
* @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

try {
    $wInit = new WebInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

$dbh = $wInit->dbh;

// get session instance
$uS = Session::getInstance();

// Get labels
$labels = Labels::getLabels();

$wListMarkup = '';

// Guest Search markup
$gMk = AbstractRole::createSearchHeaderMkup('', $labels->getString('MemberType', 'guest', 'Guest').' or ' . $labels->getString('MemberType', 'patient', 'Patient') . ' Search: ', true, $uS->searchMRN);
$mk1 = $gMk['hdr'];

// Hide guest search?
if ($uS->OpenCheckin) {
    $gSearchDisplay = '';
} else {
    $gSearchDisplay = 'display:none;';
}

// Load reservations
$inside = Reservation_1::showListByStatus($dbh, 'Reserve.php', 'CheckingIn.php', ReservationStatus::Committed, TRUE, NULL, ($uS->ResvEarlyArrDays >= 0 ? $uS->ResvEarlyArrDays : 2), TRUE);
if ($inside == '') {
    $inside = "<p style='margin-left:60px;'>-None are imminent-</p>";
}

$committedMarkup = HTMLContainer::generateMarkup('h3', $labels->getString('register', 'reservationTab', 'Confirmed Reservations')
        . HTMLContainer::generateMarkup('span', '', array('style'=>'float:right;', 'class'=>'ui-icon ui-icon-triangle-1-e')),
        array('id'=>'hhk-confResvHdr', 'style'=>'margin-bottom:15px;padding:5px;background-color: #D3D3D3;', 'title'=>'Click to show or hide the '
            . $labels->getString('register', 'reservationTab', 'Confirmed Reservations')))
    . HTMLContainer::generateMarkup('div', $inside, array('id'=>'hhk-confResv', 'style'=>'margin-bottom:5px;'));


if ($uS->OpenCheckin) {

    $inside = Reservation_1::showListByStatus($dbh, 'Reserve.php', 'CheckingIn.php', ReservationStatus::Waitlist, TRUE, NULL, ($uS->ResvEarlyArrDays >= 0 ? $uS->ResvEarlyArrDays : 2), TRUE);

    if ($inside != '') {
        $wListMarkup = HTMLContainer::generateMarkup('h3', 'Wait List' . HTMLContainer::generateMarkup('span', '', array('style'=>'float:right;', 'class'=>'ui-icon ui-icon-triangle-1-e')), array('id'=>'hhk-wListResvHdr'
            , 'style'=>'margin-bottom:15px;padding:5px;background-color: #D3D3D3;', 'title'=>'Click to show or hide the wait list'))
                . HTMLContainer::generateMarkup('div', $inside, array('id'=>'hhk-wListResv', 'style'=>'margin-bottom:5px;'));

    }
}

$stayingMarkup = HTMLContainer::generateMarkup('div', Reservation_1::showListByStatus($dbh, 'GuestEdit.php', 'CheckingIn.php', ReservationStatus::Staying), array('id'=>'hhk-chkedIn'));
if ($stayingMarkup == '') {
    $stayingMarkup = "<p style='margin-left:60px;'>-None-</p>";
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_DT_CSS; ?>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <?php echo CSSVARS; ?>


        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div style="clear:both"></div>
            <form autocomplete="off">
            <div id="divResvList" style="font-size:.7em;" class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog">
                <?php echo $committedMarkup; ?>
                <?php echo $wListMarkup; ?>
                <h3 id="hhk-chkedInHdr" style='padding:5px;background-color: #D3D3D3;' title="Click to show or hide the Checked-In <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s">Checked-In <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s
                    <span class="ui-icon ui-icon-triangle-1-e" style="float:right;"></span></h3>

                    <?php echo $stayingMarkup; ?>

            </div>
            </form>
            <div id="guestSearch" style="margin-left:10px;margin-right:10px; <?php echo $gSearchDisplay; ?>" class="mb-3 hhk-panel row">
                <?php echo $mk1; ?>
            </div>


            <input type="hidden" id="dateFormat" value ="<?php echo $labels->getString("momentFormats", "reportDay", "ddd, MMM D YYYY"); ?>" />
        </div>  <!-- div id="contentDiv"-->
        <script type="text/javascript" src="<?php echo CHECKIN_JS; ?>"></script>
    </body>
</html>
