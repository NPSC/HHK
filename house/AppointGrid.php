<?php

use HHK\sec\{SecurityComponent, Session, WebInit};
use HHK\sec\Labels;

/**
 * Reserve.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");


try {
    $wInit = new webInit();
} catch (Exception $exw) {
    die($exw->getMessage());
}

$dbh = $wInit->dbh;

// get session instance
$uS = Session::getInstance();


// Get labels
$labels = Labels::getLabels();
$isGuestAdmin = SecurityComponent::is_Authorized('guestadmin');
$defaultView = 'resourceTimelineFourDays';


?>
<!DOCTYPE html>
<html lang='en'>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $wInit->pageTitle; ?></title>
        <meta http-equiv="x-ua-compatible" content="IE=edge">
        <?php echo HOUSE_CSS; ?>
        <link href='css/fullcalendar5.11.0.min.css'  rel='stylesheet' type='text/css' />
        <?php echo JQ_UI_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo FAVICON; ?>

        <style>
            #calendar {
              max-width: 1100px;
              margin: 40px auto;
            }
        </style>

        <script type="text/javascript" src="js/fullcalendar5.11.0.min.js"></script>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $wInit->generatePageMenu() ?>
        <div id="contentDiv" class="container-fluid" style="margin-left: auto;">
            <h2><?php echo $wInit->pageHeading; ?> <span id="spnStatus" style="display:inline;"></span></h2>

             <div id="calendar"></div>

        </div>

        <input  type="hidden" id="defaultView" value='<?php echo $defaultView; ?>' />
        <input  type="hidden" id="isGuestAdmin" value='<?php echo $isGuestAdmin; ?>' />

        <script type="text/javascript" src="<?php echo APPOINTGRID_JS; ?>"></script>

    </body>
</html>
