<?php
/**
 * CheckIn.php
 *
* @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'registrationRS.php');
require (DB_TABLES . 'ReservationRS.php');

require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');

require (HOUSE . 'RoleMember.php');
require (HOUSE . 'Role.php');
require (HOUSE . 'Reservation_1.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'Attributes.php');
require (HOUSE . 'Constraint.php');


try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

$dbh = $wInit->dbh;

// get session instance
$uS = Session::getInstance();

// Get labels
$labels = new Config_Lite(LABEL_FILE);
$config = new Config_Lite(ciCFG_FILE);

$wListMarkup = '';

// Guest Search markup
$gMk = Role::createSearchHeaderMkup('', 'Guest Search: ', TRUE);
$mk1 = $gMk['hdr'];

// Hide guest search?
if ($uS->OpenCheckin) {
    $gSearchDisplay = '';
} else {
    $gSearchDisplay = 'display:none;';
}

// Load reservations
$inside = Reservation_1::showListByStatus($dbh, $config->getString('house', 'ReservationPage', 'Reserve.php'), 'CheckingIn.php', ReservationStatus::Committed, TRUE, NULL, 2, TRUE);
if ($inside == '') {
    $inside = "<p style='margin-left:60px;'>-None are imminent-</p>";
}

$committedMarkup = HTMLContainer::generateMarkup('h3', $labels->getString('register', 'reservationTab', 'Confirmed Reservations')
        . HTMLContainer::generateMarkup('span', '', array('style'=>'float:right;', 'class'=>'ui-icon ui-icon-triangle-1-e')),
        array('id'=>'hhk-confResvHdr', 'style'=>'margin-bottom:15px;padding:5px;background-color: #D3D3D3;', 'title'=>'Click to show or hide the '
            . $labels->getString('register', 'reservationTab', 'Confirmed Reservations')))
    . HTMLContainer::generateMarkup('div', $inside, array('id'=>'hhk-confResv', 'style'=>'margin-bottom:5px;'));


if ($uS->OpenCheckin) {

    $inside = Reservation_1::showListByStatus($dbh, $config->getString('house', 'ReservationPage', 'Reserve.php'), 'CheckingIn.php', ReservationStatus::Waitlist, TRUE, NULL, 2, TRUE);

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
        <title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_DT_CSS; ?>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo FAVICON; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS ?>"></script>
    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div style="clear:both"></div>

            <div id="divResvList" style="font-size:.7em;" class="ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox hhk-visitdialog">
                <?php echo $committedMarkup; ?>
                <?php echo $wListMarkup; ?>
                <h3 id="hhk-chkedInHdr" style='padding:5px;background-color: #D3D3D3;' title="Click to show or hide the Checked-In Guests">Checked-In Guests
                    <span class="ui-icon ui-icon-triangle-1-e" style="float:right;"></span></h3>
                <?php echo $stayingMarkup; ?>
            </div>
            <form  action="CheckIn.php" method="post"  id="form1">
                <div id="guestSearchWrapper" style="display: none;">
                    <div id="guestSearch" style="clear:left;float:left;padding-left:0;<?php echo $gSearchDisplay; ?>" class="hhk-panel">
                        <?php echo $mk1; ?>
                    </div>
                </div>
            </form>
            <input type="hidden" id="dateFormat" value ="<?php echo $labels->getString("momentFormats", "reportDay", "ddd, MMM D YYYY"); ?>" />
        </div>  <!-- div id="contentDiv"-->
        <script type="text/javascript" src="js/checkin.js"></script>
    </body>
</html>
