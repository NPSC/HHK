<?php

use HHK\Config_Lite\Config_Lite;
use HHK\sec\{Session, WebInit};
use HHK\HTMLControls\HTMLContainer;
use HHK\Exception\RuntimeException;
use HHK\House\ReserveData\ReserveData;
use HHK\Member\Role\AbstractRole;
use HHK\sec\Labels;
use HHK\House\ReferralForm;
use HHK\SysConst\{GLTableNames, MemStatus, PhonePurpose};
use HHK\House\PSG;
use HHK\SysConst\ReferralFormStatus;
use HHK\Tables\WebSec\Page_SecurityGroupRS;

/**
 * Referral.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2021 <nonprofitsoftwarecorp.org>
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

$errorMessage = '';
$idDoc = 0;
$idPatient = -1;    // negative number triggers patient search.
$patMkup = '';
$guestMkup = '';
$chosen = ' Search';
$continueLink = HTMLContainer::generateMarkup('a', 'Continue', array('href'=>'register.php'));

$datesMkup = '';
$displayGuest = 'display:none;';
$final = '';

// Referral form
if (isset($_GET['docid'])) {
    $idDoc = intval(filter_input(INPUT_GET, 'docid', FILTER_SANITIZE_NUMBER_INT), 10);
} else if (isset($_POST['idDoc'])) {
    $idDoc = intval(filter_input(INPUT_POST, 'idDoc', FILTER_SANITIZE_NUMBER_INT), 10);
}

// Patient
if (isset($_POST['rbPatient'])) {

    $idPatient = intval(filter_input(INPUT_POST, 'rbPatient', FILTER_SANITIZE_NUMBER_INT), 10);

} else if (isset($_POST['idPatient'])) {

    // Patient already selected
    $idPatient = intval(filter_input(INPUT_POST, 'idPatient', FILTER_SANITIZE_NUMBER_INT), 10);
}


// final step
if (isset($_POST['finaly'])) {
    $final = intval(filter_input(INPUT_POST, 'finaly', FILTER_SANITIZE_NUMBER_INT), 10);
}



if ($idDoc > 0) {

    // House user selected a referral form.
    try {
    	$refForm = new ReferralForm($dbh, $idDoc);

    	if ($refForm->getReferralStatus() == ReferralFormStatus::New || $refForm->getReferralStatus() == ReferralFormStatus::InProcess) {

        	$refForm->setDates();
        	$datesMkup = $refForm->datesMarkup();

        	if ($idPatient < 0) {

        	    // Patient search
            	$includes = [];

            	if (isset($_POST[$refForm::HTML_Incl_Birthday])) {
            	    $includes[$refForm::HTML_Incl_Birthday] = 'y';
            	}

            	if (isset($_POST[$refForm::HTML_Incl_Phone])) {
            	    $includes[$refForm::HTML_Incl_Phone] = 'y';
            	}

            	if (isset($_POST[$refForm::HTML_Incl_Email])) {
            	    $includes[$refForm::HTML_Incl_Email] = 'y';
            	}

            	$refForm->searchPatient($dbh, $includes);
            	$patMkup = $refForm->createPatientMarkup();

        	} else if ($final != 1) {
        	    // Guest search

        	    // Save selected patient
        	    $patient = $refForm->setPatient($dbh, $idPatient);
        	    $patMkup = $refForm->chosenMemberMkup($patient);
        	    $idPatient = $patient->getIdName();

        	    // Search guests and present results to UI
        	    $guests = $refForm->searchGuests($dbh);

        	    // Any guests to search for?
        	    if (count($guests) > 0) {

        	        // Guests are listed.
            	    $guestMkup .= $refForm->guestsMarkup();

            	    // Unhide guest section
            	    $displayGuest = '';
            	    $chosen = ' Chosen';   // Patient title ribbon.

        	    } else {
        	        // no guests, Create reservation
        	        $refForm->finishReferral($dbh, $patient->getIdName());
        	    }

        	} else {
        	    // Create reservation
        	    $refForm->finishReferral($dbh, $idPatient);
        	}

    	} else {
    	    // Wrong document status

    	    $lookups = readGenLookupsPDO($dbh, 'Referral_Form_Status');

    	    if ($refForm->getReferralStatus() == ReferralFormStatus::Accepted) {

    	        $errorMessage = 'This Referral has already been accepted.  ' . $continueLink;

    	    } else {

    	       $errorMessage = 'The Referral has the wrong status: ' . (isset($lookups[$refForm->getReferralStatus()]) ? $lookups[$refForm->getReferralStatus()][1] : 'Unknown Status')
    	       . '.  ' . $continueLink;
    	    }
    	}

    } catch (\Exception $ex) {
        $errorMessage = $ex->getMessage() . '.  ' . $continueLink;
    }

} else {
    $errorMessage = "The Referral Form Document Id is missing.  " . $continueLink;
}

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $wInit->pageTitle; ?></title>
        <meta http-equiv="x-ua-compatible" content="IE=edge">
        <?php echo JQ_UI_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo GRID_CSS; ?>

        <?php echo FAVICON; ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo ADDR_PREFS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <div id="contentDiv" class="container-fluid" style="margin-left: auto; margin-top: 5px;">
            <h1><?php echo $wInit->pageHeading; ?> <span id="spnStatus" style="display:inline;"></span></h1>
            <div id="errorMessage" style="clear:left;float:left; margin-top:5px;margin-bottom:5px; <?php if ($errorMessage == '') {echo('display:none;');} ?>" class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox">
                <?php echo $errorMessage; ?>
            </div>
            <div id="divuserData" style="<?php if ($errorMessage != '') {echo('display:none;');} ?>">
            <form action="GuestReferral.php" method="post"  id="form1">
                <div id="datesSection" class="ui-widget ui-widget-header ui-state-default ui-corner-all hhk-panel mb-3">
                <?php echo $datesMkup; ?>
                </div>
                <div id="PatientSection" style="font-size: .9em; min-width: 810px;"  class="ui-widget hhk-visitdialog mb-3">
                    <div id="PatientHeader" class="ui-widget ui-widget-header ui-state-default ui-corner-top hhk-panel">
                    	<?php echo $labels->getString('MemberType', 'patient', 'Patient') . $chosen; ?>
                    </div>
                    <div class="ui-corner-bottom hhk-tdbox ui-widget-content" style="padding:5px;">
                    	<?php echo $patMkup; ?>
                    	</div>
                </div>
                <div id="GuestSection" style="font-size: .9em; min-width: 810px;<?php echo $displayGuest; ?>"  class="ui-widget hhk-visitdialog mb-3">
                    <div id="GuestHeader" class="ui-widget ui-widget-header ui-state-default ui-corner-top hhk-panel">
                    	<?php echo $labels->getString('MemberType', 'guest', 'Guest') . 's'; ?>
                    </div>
                    <div class="ui-corner-bottom hhk-tdbox ui-widget-content" style="padding:5px;">
                    	<?php echo $guestMkup; ?>
                    </div>
                </div>
                <div id="submitButtons" class="ui-corner-all" style="font-size:.9em; clear:both;">
                    <table >
                        <tr><td ><span id="pWarnings" style="display:none; font-size: 1.4em; border: 1px solid #ddce99;margin-bottom:3px; padding: 0 2px; color:red; background-color: yellow; float:right;"></span></td></tr>
                        <tr><td>
                        	<input type='submit' id='btnDone' name='btnDone' />
                        </td></tr>
                    </table>
                </div>
                <input type="hidden" value="<?php echo $idDoc ?>" id="idDoc" name="idDoc" />
                <input type="hidden" value="<?php echo $idPatient ?>" id="idPatient" name='idPatient'/>
                <input type="hidden" value="<?php echo $final ?>" id="finaly" name='finaly'/>
            </form>
            </div>
        </div>
        <input type="hidden" value="<?php echo $labels->getString("momentFormats", "report", "MMM D, YYYY"); ?>" id="dateFormat"/>
        <input type="hidden" value="<?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>" id="visitorLabel" />
        <input type="hidden" value="<?php echo $labels->getString('MemberType', 'guest', 'Guest'); ?>" id="guestLabel" />
        <?php echo GUEST_REFERRAL_JS; ?>
    </body>
</html>
