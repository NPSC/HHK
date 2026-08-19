<?php

use HHK\sec\{Session, WebInit};
use HHK\HTMLControls\{HTMLContainer, HTMLInput};
use HHK\SysConst\WebPageCode;
use HHK\SysConst\ReservationStatus;
use HHK\House\Reservation\ReservationSvcs;
use HHK\Vite\Vite;

/**
 * PrtRegForm.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

$wInit = new WebInit(WebPageCode::Page);
$pageTitle = $wInit->pageTitle;

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$uS = Session::getInstance();

$queryForm = '';
$regForm = '';
$sty = '';
$checkinDate = '';

if (isset($_GET['d'])) {
    $checkinDate = filter_var($_GET['d'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

if (isset($_POST['regckindate'])) {
    $checkinDate = filter_var($_POST['regckindate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

if ($checkinDate == '') {

    $queryForm = HTMLContainer::generateMarkup('div', 'Check-in Date: ' . HTMLInput::generateMarkup('', array('name' => 'regckindate', 'class' => 'ckdate hhk-prtRegForm'))
                    . HTMLInput::generateMarkup('Print Registration Forms', array('id' => 'btnPrintRegForm', 'type' => 'submit', 'data-page' => 'PrtRegForm.php', 'class' => 'hhk-prtRegForm', 'style' => 'margin-left:.3em;'))
                    , array('style' => 'margin-left:5em;padding:15px;margin:10px;border:solid 1px #62A0CE;background-color:#E8E5E5;float:left;'));

} else {

    $ckinDT = new DateTime($checkinDate);

    // get reservations on the date indicated
    $stmt = $dbh->prepare("SELECT `idReservation` FROM `reservation` WHERE `Status` = :status AND DATE(`Expected_Arrival`) = :ckinDate");
    $stmt->execute([':status' => ReservationStatus::Committed, ':ckinDate' => $ckinDT->format('Y-m-d')]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) == 0) {
        $regForm = '<h2 style="margin-top:20px;">No reservation found for ' . $ckinDT->format('M j, Y') . '</h2>';
    }

    foreach ($rows as $index=>$r) {

        $reservArray = ReservationSvcs::generateCkinDoc($dbh, $r['idReservation'], 0, 0, $wInit->resourceURL . '../conf/registrationLogo.png');

        $sty = $reservArray['docs'][0]['style'];
        
        $regForm .= HTMLContainer::generateMarkup('div', $reservArray['docs'][0]['doc'], array('class'=>'regFormContainer pagebreak'));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title><?php echo $pageTitle; ?></title>

        <?php echo Vite::asset('resources/js/house.js'); ?>
        
        <?php echo FAVICON; ?>
        <?php echo CSSVARS; ?>
        
        <?php echo $sty; ?>

        <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", () => {
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
        <div class="PrintArea"><?php echo $regForm; ?></div>

    </body>
</html>
