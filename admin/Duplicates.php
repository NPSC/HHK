<?php

use HHK\AlertControl\AlertMessage;
use HHK\Duplicate;
use HHK\sec\{Session, WebInit};
use HHK\HTMLControls\{HTMLContainer, HTMLSelector};
use HHK\SysConst\GLTableNames;

/**
 * Duplicates.php
 *
-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2018 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");


$wInit = new webInit();
$dbh = $wInit->dbh;
$uS = Session::getInstance();

$wInit->sessionLoadGuestLkUps();

// AJAX
if (isset($_POST['cmd'])) {

    $cmd = filter_var($_POST['cmd'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $markup = '';
    $events = array();

    try {
    if ($cmd == 'exp' && isset($_POST['nf']) && $_POST['nf'] != '') {

        $fullName = filter_var($_POST['nf'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $markup = Duplicate::expand($dbh, $fullName, $_POST, $uS->guestLookups[GLTableNames::PatientRel]);

        $events = array('mk' => $markup);

    } else if ($cmd == 'list') {

        $mType = filter_var($_POST['mType'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $events = array('mk'=>Duplicate::listNames($dbh, $mType));

    } else if ($cmd == 'pik') {
        // Combine members.
        $mType = filter_var($_POST['mType'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $id = intval(filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT), 10);

        $markup = Duplicate::combine($dbh, $mType, $id);

        $events = array('msg' => HTMLContainer::generateMarkup('p', $markup));

    } else if ($cmd == 'cpsg') {

        $idGood = intval(filter_var($_POST['idg'], FILTER_SANITIZE_NUMBER_INT), 10);
        $idBad = intval(filter_var($_POST['idb'], FILTER_SANITIZE_NUMBER_INT), 10);

        $events = Duplicate::combinePsg($dbh, $idGood, $idBad);


    } else if ($cmd == 'cids') {

        $idGood = intval(filter_var($_POST['idg'], FILTER_SANITIZE_NUMBER_INT), 10);
        $idBad = intval(filter_var($_POST['idb'], FILTER_SANITIZE_NUMBER_INT), 10);

        $events = Duplicate::combineId($dbh, $idGood, $idBad);
    }

    } catch (PDOException $pex) {
        $events = array('error'=> $pex->getMessage() . '---' . $pex->getTraceAsString());
    }

    echo json_encode($events);
    exit();
}


$mtypes = array(
    array(0 => 'g', 1 => 'Guest'),
    array(0 => 'p', 1 => 'Patient'),
    array(0 => 'ra', 1 => 'Referral Agent'),
    array(0 => 'doc', 1 => 'Doctor')
);

$mtypeSel = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($mtypes, '', TRUE), array('name' => 'selmtype'));


?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>

        <?php echo FAVICON; ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="js/duplicate.js"></script>

    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
            <h1><?php echo $wInit->pageHeading; ?></h1>
            <div id="searchSel" style="margin: 1em 0"><?php echo 'Search for: ' . $mtypeSel; ?></div>
			<div class="hhk-flex mb-3">
            	<div id="divList" class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mr-3" style="display: none; font-size:.85em;"></div>
            	<div id="divExpansion" style="display:none;font-size:.85em;text-align:center;" class="ui-widget ui-widget-content ui-corner-all hhk-widget-content"></div>
        	</div>
        </div>
    </body>
</html>
