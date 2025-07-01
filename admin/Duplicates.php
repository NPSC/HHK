<?php

use HHK\AlertControl\AlertMessage;
use HHK\Duplicate;
use HHK\HTMLControls\HTMLInput;
use HHK\sec\{Session, WebInit};
use HHK\HTMLControls\{HTMLContainer, HTMLSelector};
use HHK\sec\Labels;
use HHK\SysConst\GLTableNames;

/**
 * Duplicates.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("AdminIncludes.php");


$wInit = new webInit();
$dbh = $wInit->dbh;
$uS = Session::getInstance();
$debugMode = ($uS->mode == "dev");

$wInit->sessionLoadGuestLkUps();

// AJAX
if (filter_has_var(INPUT_POST, 'cmd')) {

    $cmd = filter_input(INPUT_POST, 'cmd', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $markup = '';
    $events = array();

    try {
    if ($cmd == 'exp' && filter_has_var(INPUT_POST, 'nf') && $_POST['nf'] != '') {

        $fullName = filter_input(INPUT_POST, 'nf', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $markup = Duplicate::expand($dbh, $fullName, $_POST, $uS->guestLookups[GLTableNames::PatientRel]);

        $events = array('mk' => $markup);

    } else if ($cmd == 'list') {

        $mType = filter_input(INPUT_POST, 'selmtype', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $filters = (filter_has_var(INPUT_POST, "filter") ? filter_input(INPUT_POST, "filter", FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FORCE_ARRAY) : []);

        $events = array('mk'=>Duplicate::listNames($dbh, $mType, $filters));

    } else if ($cmd == 'pik') {
        // Combine members.
        $mType = filter_input(INPUT_POST, 'mType', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $id = intval(filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT), 10);

        $markup = Duplicate::combine($dbh, $mType, $id);

        $events = array('msg' => HTMLContainer::generateMarkup('p', $markup));

    } else if ($cmd == 'cpsg') {

        $idGood = intval(filter_input(INPUT_POST, 'idg', FILTER_SANITIZE_NUMBER_INT), 10);
        $idBad = intval(filter_input(INPUT_POST, 'idb', FILTER_SANITIZE_NUMBER_INT), 10);

        $events = Duplicate::combinePsg($dbh, $idGood, $idBad);


    } else if ($cmd == 'cids') {

        $idGood = intval(filter_input(INPUT_POST, 'idg', FILTER_SANITIZE_NUMBER_INT), 10);
        $idBad = intval(filter_input(INPUT_POST, 'idb', FILTER_SANITIZE_NUMBER_INT), 10);

        $events = Duplicate::combineId($dbh, $idGood, $idBad);
    }

    } catch (PDOException $pex) {
        $events = array('error'=> $pex->getMessage() . ($debugMode ? '---' . $pex->getTraceAsString(): ""));
    }

    echo json_encode($events);
    exit();
}


$mtypes = array(
    array(0 => 'p', 1 => Labels::getString("MemberType", "patient", "Patient") . 's'),
    array(0 => 'g', 1 => Labels::getString("MemberType", "guest", "Guest") . 's in the same ' . Labels::getString('Statement', 'psgAbrev', "PSG")),
    array(0 => 'pg', 1 => Labels::getString("MemberType", "guest", "Guest") . 's or ' . Labels::getString("MemberType", "patient", "Patient") . 's in any ' . Labels::getString('Statement', 'psgAbrev', "PSG")),
    array(0 => 'ra', 1 => 'Referral Agents'),
    array(0 => 'doc', 1 => 'Doctors')
);

$mtypeSel = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($mtypes, '', TRUE), array('name' => 'selmtype', 'required'=>'required'));

$filterCBs = HTMLContainer::generateMarkup("div",
    HTMLInput::generateMarkup("", array("type"=>"checkbox", "checked"=>"checked", "name"=>"filter[]", "value"=>"name", "id"=>"filterName", "disabled"=>"disabled")) . 
    HTMLContainer::generateMarkup("label", "Full Name", array("for"=>"filterName"))
) . HTMLContainer::generateMarkup("div",
    HTMLInput::generateMarkup("", array("type"=>"checkbox", "name"=>"filter[]", "value"=>"birthdate", "id"=>"filterBirthdate")) .
    HTMLContainer::generateMarkup("label", "Birth date", array("for"=>"filterBirthdate"))
) . HTMLContainer::generateMarkup("div",
    HTMLInput::generateMarkup("", array("type"=>"checkbox", "name"=>"filter[]", "value"=>"phone", "id"=>"filterPhone")) .
    HTMLContainer::generateMarkup("label", "Phone", array("for"=>"filterPhone"))
) . HTMLContainer::generateMarkup("div",
    HTMLInput::generateMarkup("", array("type"=>"checkbox", "name"=>"filter[]", "value"=>"email", "id"=>"filterEmail")) .
    HTMLContainer::generateMarkup("label", "Email", array("for"=>"filterEmail"))
) . HTMLContainer::generateMarkup("div",
    HTMLInput::generateMarkup("", array("type"=>"checkbox", "name"=>"filter[]", "value"=>"address", "id"=>"filterAddress")) .
    HTMLContainer::generateMarkup("label", "Address", array("for"=>"filterAddress"))
) . HTMLContainer::generateMarkup("div",
    HTMLInput::generateMarkup("Search for duplicates", array("type"=>"submit", "class"=>"ui-button ui-corner-all"))
);


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
            <form id="searchSel" class="ui-widget ui-widget-content ui-corner-all p-3 hhk-flex"><span>Search for: </span><?php echo $mtypeSel; ?> <span>with matching</span> <?php echo $filterCBs; ?></form>
			<div style="place-content: center;" class="hhk-flex">
                <div id="duplicatesReadme" class="ui-widget ui-widget-content hhk-widget-content ui-corner-all col-md-4">
                    <h3 style="text-align: center;">About this Tool</h3>
                    <p>This tool can find and merge potential duplicate records based on search criteria.</p>
                    <p>It can merge the following information:</p>
                    <ul>
                        <li>PSGs</li>
                        <li>Visits</li>
                        <li>Reservations</li>
                        <li>Payments</li>
                        <li>Documents</li>
                        <li>Incidents</li>
                    </ul>

                    <h4 class="mt-3">Limitations</h4>
                    <p>This tool CANNOT merge:</p>
                    <ul>
                        <li>Addresses</li>
                        <li>Email Addresses</li>
                        <li>Phone Numbers</li>
                        <li>Demographics</li>
                        <li>Insurance information</li>
                    </ul>
                    <p>If you wish to keep the above information, you must copy the information to the ID you wish to keep.</p>

                </div>
            </div>
            <div class="hhk-flex mb-3">
            	<div id="divList" class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mr-3" style="display: none; font-size:.85em;"></div>
            	<div id="divExpansion" style="display:none;font-size:.85em;text-align:center;" class="ui-widget ui-widget-content ui-corner-all hhk-widget-content"></div>
        	</div>
        </div>
    </body>
</html>
