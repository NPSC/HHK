<?php
use HHK\sec\WebInit;
use HHK\sec\Session;
use HHK\AlertControl\AlertControl;
use HHK\Member\AbstractMember;
use HHK\SysConst\MemBasis;
use HHK\SysConst\GLTableNames;
use HHK\Member\Address\Address;
use HHK\Member\Address\Phones;
use HHK\Member\Address\Emails;
use HHK\Member\Address\Addresses;

/**
 * VolNameEdit.php
 *
 * @category  Volunteer
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
require ("VolIncludes.php");

$wInit = new WebInit();
$dbh = $wInit->dbh;

$pageTitle = $wInit->pageTitle;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();
$resourceURL = $uS->resourceURL;


$id = intval($uS->uid, 10);
if ($id < 1) {
    $wInit->logout();
}

$uname = $uS->username;


// Load the session with member - based lookups
$wInit->sessionLoadGenLkUps();
$wInit->sessionLoadVolLkUps();



// Instantiate the alert message control
//$alertMsg = new alertMessage("divAlert1");
$resultMessage = "";



// Instantiate the member object
try {

    $name = AbstractMember::GetDesignatedMember($dbh, $id, MemBasis::Indivual);

} catch (Exception $ex) {

    $wInit->logout();

}


// the rest
try {

    $address = new Address($dbh, $name, $uS->nameLookups[GLTableNames::AddrPurpose]);
    $phones = new Phones($dbh, $name, $uS->nameLookups[GLTableNames::PhonePurpose]);
    $emails = new Emails($dbh, $name, $uS->nameLookups[GLTableNames::EmailPurpose]);

} catch (Exception $ex) {
    exit("Error opening supporting objects: " . $ex->getMessage());
}



// test and handle Name Edit save
if (filter_has_var(INPUT_POST, "btnSavePI")) {

    $msg = "";

try {

        // Name
        $msg .= $name->saveChanges($dbh, $_POST);
        $id = $name->get_idName();

        // Address
        $msg .= $address->savePost($dbh, $_POST, $uname);

        // Phone number
        $msg .= $phones->savePost($dbh, $_POST, $uname);

        // Email Address
        $msg .= $emails->savePost($dbh, $_POST, $uname);

        if (checkHijack($uS)) {
            setHijack($dbh, $uS, "done");
            $msg .= " Name and Address verified.  Thank you.";
        }

        // success
        if ($msg != '') {
            $resultMessage = AlertControl::createMarkup('alm', AlertControl::Success, $msg, TRUE);
        }

    } catch (Exception $ex) {

//        $alertMsg->set_Context(alertMessage::Alert);
//        $alertMsg->set_Text($ex->getMessage());
        $resultMessage = AlertControl::createMarkup('alm', AlertControl::Alert, $ex->getMessage(), TRUE);
    }
}

//
// Name Edit Row
$nameMarkup = $name->createMarkupTable($dbh, 'hhk-hideStatus', 'hhk-hideBasis');


// Excludes, Demographics and admin tabs
//$miscTabs = $name->createMiscTabsMarkup($dbh);


//
// Addresses
$addrPanelMkup = $address->createMarkup('', FALSE, $uS->county);

//
// Phone Numbers
$phoneMkup = $phones->createMarkup();

//
// Email addresses
$emailMkup = $emails->createMarkup();


// Mark Preferred phone and email
$prefMkup = Addresses::getPreferredPanel($phones, $emails);


// member data object to pass to the javascript on the page
$memberData = array();
$memberData["id"] = $id;
$memberData["coId"] = $name->get_companyId();
$memberData["coName"] = $name->get_company();
$memberData["memDesig"] = $name->getMemberDesignation();
$memberData['addrPref'] = ($name->get_preferredMailAddr() == '' ? '1' : $name->get_preferredMailAddr());
$memberData['phonePref'] = $name->get_preferredPhone();
$memberData['emailPref'] = $name->get_preferredEmail();
$memberData['memName'] = $name->getMemberName();
$memberData['memStatus'] = $name->get_status();

// Hijack user to verify hte addrss
if (checkHijack($uS)) {
    $memberData['verifyAddress'] = "true";
} else {
    $memberData['verifyAddress'] = "no";
}


$memDataJSON = json_encode($memberData);




// Instantiate the alert message control
$pwMsg = AlertControl::createMarkup('pw');

$pwAlertJSON = json_encode(AlertControl::makeJsonPackage('pw'));


//// Squirms
//$plus5 = time() + (1 * 60 * 60);
//$squirm = date("Y/m/d H:i:s", $plus5);
//$squirm = encryptMessage($squirm);
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>
        <?php echo JQ_UI_CSS; ?>
        <?php echo PUBLIC_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>

        <script type="text/javascript"><?php require("js/vNameEdit.js") ?></script>
    </head>
    <body>
<?php echo $menuMarkup; ?>
        <div id="page">
            <div id="volNameEdit">
                    <div id="editTab" class="ui-widget ui-widget-content ui-corner-all hhk-member-detail">
<?php echo $resultMessage ?>
                        <h2 style="padding: 2px 5px;text-align: center;" class="ui-corner-all ui-state-active">Manage My Contact Information</h2>
                        <form action="VolNameEdit.php" method="post" id="formEd" name="formEd">
                            <div class="ui-widget ui-widget-content ui-corner-all" style="float:left; padding: 0.7em 1.0em;">

                                <?php echo $nameMarkup; ?>
                            </div>
                            <div style="clear:both;"></div>
                            <div id="phEmlTabs" class="hhk-member-detail">
                                <ul>
                                    <li><a href="#prefTab" title="Show only preferred phone and Email">Summary</a></li>
                                    <li><a href="#phonesTab" title="Edit the Phone Numbers and designate the preferred number">Phone</a></li>
                                    <li><a href="#emailTab" title="Edit the Email Addresses and designate the preferred address">Email</a></li>
                                </ul>
                                <div id="prefTab" >
                                <?php echo $prefMkup; ?>
                                </div>
                                <div id="phonesTab" style="display:none;" >
<?php echo $phoneMkup; ?>
                                </div>
                                <div id="emailTab" style="display:none;">
<?php echo $emailMkup; ?>
                                </div>
                            </div>
                            <div id="addrsTabs" class="hhk-member-detail">
<?php echo $addrPanelMkup; ?>
                            </div>
                        </form>
                    </div>  <!-- end name edit tab -->

                <div id="submit"></div>
            </div>
        </div>
    </body>
</html>
