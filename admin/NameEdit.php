<?php
/**
 * NameEdit.php
 *
-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2017 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK
 */

require ("AdminIncludes.php");


require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'ActivityRS.php');
require (DB_TABLES . 'WebSecRS.php');
require (DB_TABLES . 'visitRS.php');

require (DB_TABLES . 'registrationRS.php');
require (DB_TABLES . 'ReservationRS.php');

require (CLASSES . 'Campaign.php');
require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (MEMBER . 'OrgMember.php');
require (MEMBER . 'Addresses.php');
require (MEMBER . 'WebUser.php');

require (CLASSES . 'UserCategories.php');
require (CLASSES . 'volunteer.php');
require (CLASSES . 'selCtrl.php');
require (CLASSES . 'History.php');
require (CLASSES . 'CleanAddress.php');
require (CLASSES . 'Donate.php');
require (CLASSES . 'AuditLog.php');
require (CLASSES . 'Relation.php');

require (CLASSES . 'Notes.php');

require (HOUSE . 'psg.php');
require (HOUSE . 'Hospital.php');
require (HOUSE . 'Role.php');
require (HOUSE . 'Guest.php');
require (HOUSE . 'Patient.php');
require (HOUSE . 'Agent.php');
require (HOUSE . 'RoleMember.php');
require (HOUSE . 'Doctor.php');

require (HOUSE . 'VisitLog.php');


$wInit = new webInit();

$dbh = $wInit->dbh;


$pageTitle = $wInit->pageTitle;
$testVersion = $wInit->testVersion;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();

// Load the session with member - based lookups
$wInit->sessionLoadGenLkUps();
$volLkups = $wInit->sessionLoadVolLkUps();
$wInit->sessionLoadGuestLkUps();

$donationsFlag = ComponentAuthClass::is_Authorized("NameEdit_Donations");

// Maintainence component - and role = admin is also required.
if (SecurityComponent::is_Admin($uS->rolecode, $uS->username)) {
    $maintFlag = ComponentAuthClass::is_Authorized("NameEdit_Maint");
    $privacyFlag = ComponentAuthClass::is_Authorized("PrivacyGroup");
} else {
    $maintFlag = false;
    $privacyFlag = false;
}



$resultMessage = "";
$id = 0;
$uname = $uS->username;

addslashesextended($_GET);

// User data object
$userData = array();
$userData["userName"] = $uname;
$userData["donFlag"] = $donationsFlag;




/*
 * called with get parameters id=x, load that id if feasable.
 */
$idRaw = filter_input(INPUT_GET, "id", FILTER_SANITIZE_NUMBER_INT);
if (is_null($idRaw) === FALSE) {

    $id = intval($idRaw, 10);
}
/*
 *  Check for new member command on get line
 *
 */
$setForOrg = false;
$cmd = filter_input(INPUT_GET, 'cmd', FILTER_SANITIZE_STRING);
if (is_null($cmd) === FALSE) {
    $id = 0;
    if ($cmd == "neworg") {
        $setForOrg = true;
    }
}



// Instantiate the alert message control
$alertMsg = new alertMessage("divAlert1");


/*
 * This is the ID that the previous page instance saved for us.
 */
$h_idTxt = filter_input(INPUT_POST, 'hdnid', FILTER_SANITIZE_NUMBER_INT);
if (is_null($h_idTxt) === FALSE) {

    $id = intval($h_idTxt, 10);
}



// If posting a new member, check the member basis
$mbasis = filter_input(INPUT_POST, 'selMbrType', FILTER_SANITIZE_STRING);
if (is_null($mbasis) === FALSE) {

    if (isset($uS->nameLookups[GL_TableNames::MemberBasis][$mbasis])) {
        if ($uS->nameLookups[GL_TableNames::MemberBasis][$mbasis][Member::SUBT] == MemDesignation::Organization) {
            $setForOrg = TRUE;
        }
    }
}



// Determine the default member type.
$defBasis = MemBasis::Indivual;
if ($setForOrg) {
    $defBasis = MemBasis::Company;
}

// Instantiate the member object
try {

    $name = Member::GetDesignatedMember($dbh, $id, $defBasis);

} catch (Exception $ex) {

    $alertMsg->set_Context(alertMessage::Alert);
    $alertMsg->set_Text($ex->getMessage());
    $resultMessage = $alertMsg->createMarkup();

    $id = 0;
    $name = Member::GetDesignatedMember($dbh, $id, $defBasis);

}


// the rest
try {

    $address = new Address($dbh, $name, $uS->nameLookups[GL_TableNames::AddrPurpose]);
    $phones = new Phones($dbh, $name, $uS->nameLookups[GL_TableNames::PhonePurpose]);
    $emails = new Emails($dbh, $name, $uS->nameLookups[GL_TableNames::EmailPurpose]);

    $rel = $name->loadRealtionships($dbh);


    // Volunteers
    $vols = array();
    foreach ($volLkups["Vol_Category"] as $purpose) {
        $volunteer = new VolunteerCategory($purpose[0], $purpose[1], $purpose[2]);
        $volunteer->set_rankOptions($volLkups["Vol_Rank"]);
        $vols[$purpose[0]] = $volunteer;
    }


} catch (Exception $ex) {
    exit("Error opening supporting objects: " . $ex->getMessage());
}






/*
 * This is the main SAVE submit button.  It checks for a change in any data field
 * and updates the database accordingly.
 */
if (isset($_POST["btnSubmit"])) {
    $msg = "";

    // Strip slashes
    addslashesextended($_POST);

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

        // Volunteers
        foreach ($vols as $v) {
            $msg .= $v->saveVolCategory($dbh, $id, $_POST[$v->getCategoryCode()], $uname);
        }

        // kludge for Billing agents
        foreach ($uS->siteList as $s) {
            if ($s["Site_Code"] == 'h' && $name->getMemberDesignation() == MemDesignation::Organization) {

                $dbh->exec("UPDATE `name` n LEFT JOIN `name_volunteer2` nv ON n.idName = nv.idName
SET n.Record_Member = 1 WHERE n.Record_Member != 1 and n.idName = $id AND nv.idName IS NOT NULL AND nv.Vol_Category = 'Vol_Type' AND nv.Vol_Code = 'ba'");

                break;
            }
        }

        // success
        $alertMsg->set_Context(alertMessage::Success);
        $alertMsg->set_Text($msg);
        $resultMessage = $alertMsg->createMarkup();


    } catch (Exception $ex) {

        $alertMsg->set_Context(alertMessage::Alert);
        $alertMsg->set_Text($ex->getMessage());
        $resultMessage = $alertMsg->createMarkup();

    }
}






// Heading member name text
if ($name->isNew()) {

    $NiceName = "<span style='font-size:2em;'>New Member</span>";

} else {

    $NiceName = "<span style='font-size:2em;'>Member: " . $name->getMemberName() . "</span>";
}





//
// Name Edit Row
$nameMarkup = $name->createMarkupTable($dbh);


// Excludes, Demographics and admin tabs
$miscTabs = $name->createMiscTabsMarkup($dbh);


//
// Addresses
$addrPanelMkup = $address->createMarkup('', TRUE, $uS->county);

//
// Phone Numbers
$phoneMkup = $phones->createMarkup();

//
// Email addresses
$emailMkup = $emails->createMarkup();


// Mark Preferred phone and email
$prefMkup = Addresses::getPreferredPanel($phones, $emails);


//
// Student
$student = null;


// Relationships
$relationsMarkup = $name->createRelationsTabs($rel, 'NameEdit.php');



/*
 * Volunteer categories
 */
$volTabNames = "";
$volPanelMkup = "";
$volNotesMkup = array();

foreach ($vols as $v) {

    $volPanelMkup .= $v->volCategoryDivMarkup($dbh, $id);
    $volTabNames .= $v->getTabMarkup();
    $volNotesMkup[$v->getCategoryTitle()] = $v->getNotesMarkup();
}



// Notes Tab
//
$notesMarkup = "";
if ($id > 0) {
    $notesMarkup = $name->genNotesMarkup($volNotesMkup);
}



//
// Admin History - add this ID.
History::addToMemberHistoryList($dbh, $id, $uS->rolecode);

// Admin History tab markup
$recHistory = History::getMemberHistoryMarkup($dbh);



/*
 * Donations
 */

// but if $id = 0, disable the donation tab
if ($id != 0 && $donationsFlag) {

    $CampOpt = Campaign::CampaignSelOptionMarkup($dbh, '', false, false);

    $donateMkup = Donate::createDonateMarkup(
            $CampOpt,
            removeOptionGroups($uS->nameLookups[GL_TableNames::AddrPurpose]),
            $name->get_preferredMailAddr(),
            $uS->nameLookups[GL_TableNames::SalutationCodes],
            SalutationCodes::FirstOnly,
            SalutationCodes::Formal,
            $name->getAssocDonorList($rel),
            $name->getDefaultDonor($rel),
            $name->getAssocDonorLabel(),
            removeOptionGroups($uS->nameLookups[GL_TableNames::PayType]),
            NULL
            );

}



// member data object to pass to the javascript on the page
$memberData = array();


// Web User page
$wUserRS = WebUser::loadWebUserRS($dbh, $id);
$userName = $wUserRS->User_Name->getStoredVal();
$memberData['webUserName'] = $userName;
$webUserDialogMarkup = WebUser::getSecurityGroupMarkup($dbh, $id, $maintFlag) . WebUser::getWebUserMarkup($dbh, $id, $maintFlag, $wUserRS);


// instantiate a ChallengeGenerator object
$challengeVar = $uS->challenge;


$memberData["id"] = $id;
$memberData["coId"] = $name->get_companyId();
$memberData["coName"] = $name->get_company();
$memberData["memDesig"] = $name->getMemberDesignation();
$memberData['addrPref'] = ($name->get_preferredMailAddr() == '' ? '1' : $name->get_preferredMailAddr());
$memberData['phonePref'] = $name->get_preferredPhone();
$memberData['emailPref'] = $name->get_preferredEmail();
$memberData['memName'] = $name->getMemberName();
$memberData['memStatus'] = $name->get_status();


$memDataJSON = json_encode($memberData);
$usrDataJSON = json_encode($userData);

// Squirms
$plus5 = time() + (1 * 60 * 60);

$squirm = encryptMessage(date("Y/m/d H:i:s", $plus5));

$PWresultMessage = "";

// Instantiate the alert message control
//$alertMsg = new alertMessage("divAlert1");
$alertMsg->set_DisplayAttr("none");
$alertMsg->set_Context(alertMessage::Success);
$alertMsg->set_iconId("alrIcon");
$alertMsg->set_styleId("alrResponse");
$alertMsg->set_txtSpanId("alrMessage");
$alertMsg->set_Text("uh-oh");

$alertMessage = $alertMsg->createMarkup();


?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />
        <?php echo DEFAULT_CSS; ?>
        <link href="<?php echo JQ_DT_CSS; ?>" rel="stylesheet" type="text/css" />
        <?php echo MULTISELECT_CSS; ?>
        <style>
            .ui-menu-item {min-width: 400px;}
        </style>


        <link href="css/volCtrl.css" rel="stylesheet" type="text/css" />
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PAG_JS; ?>"></script>
    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?>>
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">

            <div style="float:left; margin-right: 100px; margin-top:10px;">
                <?php echo $NiceName; ?></div>
            <div class="ui-corner-all hhk-member-detail" style="background:#EFDBC2; margin-bottom:10px;">
                <div style="float: left; border-width: 1px; border-color: gray; border-style: ridge; padding: 5px;">
                    <span>Search: </span>
                    <span style="margin: 0 10px;">
                        <label for="rbmemName">Name</label><input type="radio" name="msearch" checked="checked" id="rbmemName" />
                        <label for="rbmemEmail">Email</label><input type="radio" name="msearch" id="rbmemEmail" />
                    </span>
                    <input type="text" id="txtsearch" size="20" title="Enter at least 3 characters to invoke search" />
                </div>
            </div>
            <div style="clear:both;"></div>
            <?php echo $resultMessage; ?> <?php echo $alertMessage; ?>
            <form action="NameEdit.php" method="post" id="form1" name="form1" >
                <div class="ui-widget ui-widget-content ui-corner-all" style="font-size:0.95em; float:left; padding: 0.7em 1.0em;">
                    <?php echo $nameMarkup; ?>
                </div>
                <div style="clear:both;"></div>
                <div id="linkTabs" class="ui-widget ui-widget-content ui-corner-all" style="font-size:0.95em; float:left; padding: 0.7em 1.0em;">
                        <?php echo $relationsMarkup; ?>
                </div>
                <div style="clear:both;"></div>
                <div id="divaddrTabs" class="hhk-showonload" style="display:none;">
                    <div id="phEmlTabs" class="hhk-member-detail">
                        <ul>
                            <li><a href="#prefTab" title="Show only preferred phone and Email">Summary</a></li>
                            <li><a href="#phonesTab" title="Edit the Phone Numbers and designate the preferred number">Phone</a></li>
                            <li><a href="#emailTab" title="Edit the Email Addresses and designate the preferred address">Email</a></li>
                        </ul>
                        <div id="prefTab" class="ui-tabs-hide" >
                            <?php echo $prefMkup; ?>
                        </div>
                        <div id="phonesTab" class="ui-tabs-hide" >
                            <?php echo $phoneMkup; ?>
                        </div>
                        <div id="emailTab" class="ui-tabs-hide" >
                            <?php echo $emailMkup; ?>
                        </div>
                    </div>
                    <div id="addrsTabs" class="hhk-member-detail">
                        <?php echo $addrPanelMkup; ?>
                    </div>
                    <div id="demographicTabs" class="hhk-member-detail">
                        <?php echo $miscTabs; ?>
                    </div>
                    <div style="clear:both;"></div>
                </div>
                <div id="divFuncTabs" class="hhk-member-detail" style="display:none; margin-bottom: 50px;" >
                    <ul>
                        <li><a href="#vhistory">History</a></li>
                        <?php echo $volTabNames; ?>
                        <?php if ($donationsFlag) { echo "<li id='donblank'><a href='#vdonblank'>Donations...</a></li>\n"; } ?>
                        <li><a href="#vnotes">Notes</a></li>
                        <li id="wbuser"><a href="#vwuser">Web Account...</a></li>
                        <li id="changelog"><a href="#vchangelog">Change Log</a></li>
                    </ul>
                    <div id="vhistory" style="background:#EFDBC2;">
                            <?php echo $recHistory; ?>
                    </div>
                    <div id="widget-docs">
                        <?php echo $volPanelMkup; ?>
                    </div>
                    <div id="vnotes" >
                        <?php echo $notesMarkup; ?>
                    </div>
                    <div id="vchangelog" class="ignrSave">
                      <table cellpadding="0" cellspacing="0" border="0" class="display ignrSave" id="dataTbl"></table>
                    </div>
                    <div id="vdonblank"> </div>
                    <div id="vwuser"> </div>
               </div>
                <!-- End of Tabs Control -->
                <div id="divSubmitButtons" class="ui-corner-all">
                    <input type="reset" name="btnReset" value="Reset" id="btnReset" />
                    <input type="submit" name="btnSubmit" value="Save" id="btnSubmit" />
                </div>
                <input type="hidden" name="hdnid" id="hdnid" value="<?php echo $id; ?>" />
                <input type="hidden" name="spouse_for" id="spouse_for" value="" />
                <input type="hidden" name="squirm" id="squirm" value="<?php echo $squirm; ?>" />
            </form>
            <div id="submit" class="hhk-member-detail" style="display:none;" >
                <table>
                    <tr>
                        <td>Search: </td><td><input type="text" id="txtRelSch" size="15" value="" title="Type at least 3 letters to invoke the search."/></td>
                    </tr>
                    <tr><td><input type="hidden" id="hdnRelCode" value=""/></td><td></td></tr>
                </table>
            </div>
            <div id="dchgPw" class="hhk-member-detail" style="display:none;font-size:0.95em;">
                <table>
                    <tr>
                        <td class="tdlabel">User Name</td><td><input id="txtUserName" type="text" value="<?php echo $userName; ?>" class="ro ignrSave" readonly="readonly" /></td>
                    </tr><tr>
                        <td class="tdlabel">Admin Password</td><td><input id="txtOldPw" type="password" value="" title="Enter your password, not the users old password." /></td>
                    </tr><tr>
                        <td class="tdlabel">Enter New Password</td><td><input id="txtNewPw1" type="password" value="" title="This will be the users new password."/></td>
                    </tr><tr>
                        <td class="tdlabel">Enter New Password Again</td><td><input id="txtNewPw2" type="password" value=""  /></td>
                    </tr><tr>
                        <td colspan ="2"><span id="pwChangeErrMsg"><?php echo $PWresultMessage; ?></span></td>
                    </tr>
                </table>
            </div>
            <div id="vdon" style="display:none;font-size:0.9em;">
                <?php echo $donateMkup; ?>
                <div id="divListDonation">
                    <input type="checkbox" class="hhk-undonate" />
                </div>
            </div>
            <div id="zipSearch" class="hhk-tdbox-noborder"  style="display:none;">
                <table width="100%">
                    <tr>
                        <td class="tdlabel">Country: </td><td><select id="zipCtry" class="input-medium bfh-countries" data-country="US"></select></td>
                    </tr>
                    <tr>
                        <td class="tdlabel">Postal Code: </td><td><input type="text" id="txtZipSch" class="input-medium" value="" title="Type in the postal code."/></td>
                    </tr>
                    <tr><td colspan="3" id="placeList"></td></tr>
                </table>
            </div>
            <div id="vwebUser" class="hhk-member-detail " style="display:none;">
                <?php echo $webUserDialogMarkup; ?>
            </div>
        </div>  <!-- div id="page"-->
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo MD5_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?><?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?>js/jquery.multiselect.min.js"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?>js/stateCountry.js"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?>js/verifyAddrs-min.js"></script>
        <script type="text/javascript" src="<?php echo $wInit->resourceURL; ?>js/addrPrefs-min.js"></script>
        <script type="text/javascript" src="js/genfunc.js"></script>
        <script type="text/javascript" src="../house/js/resv.js"></script>
        <script type="text/javascript"><?php include_once("js/nameEd.js") ?></script>
    </body>
</html>
