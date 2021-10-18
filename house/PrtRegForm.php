<?php

use HHK\sec\{Session, WebInit};
use HHK\HTMLControls\{HTMLContainer, HTMLInput};
use HHK\SysConst\WebPageCode;
use HHK\SysConst\ReservationStatus;
use HHK\House\Reservation\ReservationSvcs;

/**
 * PrtRegForm.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

$wInit = new webInit(WebPageCode::Page);
$pageTitle = $wInit->pageTitle;

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$uS = Session::getInstance();

$queryForm = '';
$regForm = '';
$sty = '';
$checkinDate = '';

if (isset($_GET['d'])) {
    $checkinDate = filter_var($_GET['d'], FILTER_SANITIZE_STRING);
}

if (isset($_POST['regckindate'])) {
    $checkinDate = filter_var($_POST['regckindate'], FILTER_SANITIZE_STRING);
}

if ($checkinDate == '') {

    $queryForm = HTMLContainer::generateMarkup('div', 'Check-in Date: ' . HTMLInput::generateMarkup('', array('name' => 'regckindate', 'class' => 'ckdate hhk-prtRegForm'))
                    . HTMLInput::generateMarkup('Print Registration Forms', array('id' => 'btnPrintRegForm', 'type' => 'submit', 'data-page' => 'PrtRegForm.php', 'class' => 'hhk-prtRegForm', 'style' => 'margin-left:.3em;'))
                    , array('style' => 'margin-left:5em;padding:15px;margin:10px;border:solid 1px #62A0CE;background-color:#E8E5E5;float:left;'));

} else {

    $ckinDT = new DateTime($checkinDate);

    // get reservations on the date indicated
    $stmt = $dbh->query("select idReservation from reservation where Status = '" . ReservationStatus::Committed . "' and DATE(Expected_Arrival) = '" . $ckinDT->format('Y-m-d') . "'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) == 0) {
        $regForm = '<h2 style="margin-top:20px;">No registrations found for ' . $ckinDT->format('M j, Y') . '</h2>';
    }

    foreach ($rows as $r) {

        $reservArray = ReservationSvcs::generateCkinDoc($dbh, $r['idReservation'], 0, 0, $wInit->resourceURL . '../conf/registrationLogo.png');
        //$sty = $reservArray['style'];

        $regForm .= $reservArray['docs'][0]['doc'] . HTMLContainer::generateMarkup('div', '', array('style'=>'page-break-before: right;'));

    }
}
?>
<!DOCTYPE html>
<html lang="en" moznomarginboxes>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo $sty; ?>
        <script type="text/javascript" src="<?php echo JQ_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript">
    $(document).ready(function () {
    "use strict";
    $('#btnPrintRegForm').button();
    $('.ckdate').datepicker();
     });
        </script>
    </head>
    <body>
        <form action="#" method="post" name="form1">
            <?php echo $queryForm; ?>
        </form>
<?php echo $regForm; ?>
    </body>
</html>
