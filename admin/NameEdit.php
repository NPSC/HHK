<?php

use HHK\Donation\{Campaign, DonateMarkup};
use HHK\History;
use HHK\AlertControl\AlertMessage;
use HHK\Member\{AbstractMember, WebUser};
use HHK\SysConst\{GLTableNames, MemBasis, MemDesignation, SalutationCodes};
use HHK\sec\{SecurityComponent, Session, WebInit};
use HHK\Volunteer\VolunteerCategory;
use HHK\Member\Address\{Address, Phones, Emails, Addresses};
use HHK\sec\SAML;

/**
 * NameEdit.php
 *
-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2017 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK
 */

require ("AdminIncludes.php");

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

$donationsFlag = SecurityComponent::is_Authorized("NameEdit_Donations");

// Maintainence component - and role = admin is also required.
if (SecurityComponent::is_Admin($uS->rolecode, $uS->username)) {
    $maintFlag = SecurityComponent::is_Authorized("NameEdit_Maint");

} else {
    $maintFlag = false;

}



$resultMessage = "";
$id = 0;
$uname = $uS->username;

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
$cmd = filter_input(INPUT_GET, 'cmd', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (is_null($cmd) === FALSE) {
    $id = 0;
    if ($cmd == "neworg") {
        $setForOrg = true;
    }
}



// Instantiate the alert message control
$alertMsg = new AlertMessage("divAlert1");


/*
 * This is the ID that the previous page instance saved for us.
 */
$h_idTxt = filter_input(INPUT_POST, 'hdnid', FILTER_SANITIZE_NUMBER_INT);
if (is_null($h_idTxt) === FALSE) {

    $id = intval($h_idTxt, 10);

}

if ($id < 0) {
    $id = 0;
}


// If posting a new member, check the member basis
$mbasis = filter_input(INPUT_POST, 'selMbrType', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (is_null($mbasis) === FALSE) {

    if (isset($uS->nameLookups[GLTableNames::MemberBasis][$mbasis])) {
        if ($uS->nameLookups[GLTableNames::MemberBasis][$mbasis][AbstractMember::SUBT] == MemDesignation::Organization) {
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

    $name = AbstractMember::GetDesignatedMember($dbh, $id, $defBasis);

} catch (Exception $ex) {

    $alertMsg->set_Context(alertMessage::Alert);
    $alertMsg->set_Text($ex->getMessage());
    $resultMessage = $alertMsg->createMarkup();

    $id = 0;
    $name = AbstractMember::GetDesignatedMember($dbh, $id, $defBasis);

}


// the rest
try {

    $address = new Address($dbh, $name, $uS->nameLookups[GLTableNames::AddrPurpose]);
    $phones = new Phones($dbh, $name, $uS->nameLookups[GLTableNames::PhonePurpose]);
    $emails = new Emails($dbh, $name, $uS->nameLookups[GLTableNames::EmailPurpose]);

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
if(filter_has_var(INPUT_POST, "btnSubmit")){
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

$donateMkup = '';

// but if $id = 0, disable the donation tab
if ($id != 0 && $donationsFlag) {

    $CampOpt = Campaign::CampaignSelOptionMarkup($dbh, '', false, false);

    $donateMkup = DonateMarkup::createDonateMarkup(
            $CampOpt,
            removeOptionGroups($uS->nameLookups[GLTableNames::AddrPurpose]),
            $name->get_preferredMailAddr(),
            $uS->nameLookups[GLTableNames::SalutationCodes],
            SalutationCodes::FirstOnly,
            SalutationCodes::Formal,
            $name->getAssocDonorList($rel),
            $name->getDefaultDonor($rel),
            $name->getAssocDonorLabel(),
            removeOptionGroups($uS->nameLookups[GLTableNames::PayType]),
            NULL
            );

}



// member data object to pass to the javascript on the page
$memberData = array();


// Web User page
$wUserRS = WebUser::loadWebUserRS($dbh, $id);
$userName = $wUserRS->User_Name->getStoredVal();
$memberData['webUserName'] = $userName;
if($wUserRS->idIdp->getStoredVal() > 0){
    $saml = new SAML($dbh, $wUserRS->idIdp->getStoredVal());
    if($saml->getIdpManageRoles() == 1){
        $editSecGroups = false;
    }else{
        $editSecGroups = $maintFlag;
    }
}else{
    $editSecGroups = $maintFlag;
}
$webUserDialogMarkup = WebUser::getSSOMsg($dbh, $id) . WebUser::getSecurityGroupMarkup($dbh, $id, $editSecGroups) . WebUser::getWebUserMarkup($dbh, $id, $maintFlag, $wUserRS);


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
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $pageTitle; ?></title>

        <?php echo JQ_UI_CSS; ?>
        <?php echo DEFAULT_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo MULTISELECT_CSS; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>
        <?php echo UPPLOAD_CSS; ?>
        <link href="css/volCtrl.css" rel="stylesheet" type="text/css" />

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>

        <script type="text/javascript" src="<?php echo MOMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MULTISELECT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo ADDR_PREFS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo DIRRTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="js/genfunc.js"></script>

    </head>
    <body <?php if ($testVersion) echo "class='testbody'"; ?>>
            <?php echo $menuMarkup; ?>
        <div id="contentDiv">

            <div class="hhk-flex my-3">
                <?php echo $NiceName; ?>
                <div class="ui-widget ui-widget-content ui-corner-all hhk-widget-content p-2 ml-3" style="background:#EFDBC2;">
                    <div style="border-width: 1px; border-color: gray; border-style: ridge; padding: 2px;">
                        <span>Search: </span>
                        <span style="margin: 0 10px;">
                            <label for="rbmemName">Name</label><input type="radio" name="msearch" checked="checked" id="rbmemName" />
                            <label for="rbmemEmail">Email</label><input type="radio" name="msearch" id="rbmemEmail" />
                        </span>
                        <input type="text" id='hdnblank' value='' style='display:none;'/>
                        <input type="search" id="txtsearch" size="20" title="Enter at least 3 characters to invoke search" />
                    </div>
                </div>
            </div>
            <?php echo $resultMessage; ?> <?php echo $alertMessage; ?>
            <form action="NameEdit.php" method="post" id="form1" name="form1" >
                <div class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-2 hhk-flex" style="font-size:0.95em;padding: 0.7em 1.0em;">
                    <?php echo $nameMarkup; ?>
                </div>
                <div id="linkTabs" class="ui-widget ui-widget-content ui-corner-all hhk-widget-content mb-2" style="font-size:0.95em;padding: 0.7em 1.0em;">
                        <?php echo $relationsMarkup; ?>
                </div>
                <div id="divaddrTabs" class="hhk-showonload hhk-flex hhk-flex-wrap" style="display:none;">
                    <div id="phEmlTabs" class="hhk-member-detail mr-2 mb-2">
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
                    <div id="addrsTabs" class="hhk-member-detail mr-2 mb-2">
                        <?php echo $addrPanelMkup; ?>
                    </div>
                    <div id="demographicTabs" class="hhk-member-detail mr-2 mb-2">
                        <?php echo $miscTabs; ?>
                    </div>
                </div>
                <div id="divFuncTabs" class="hhk-widget-content" style="display:none; margin-bottom: 50px;" >
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
                      <table style="width:100%;" class="display ignrSave" id="dataTbl"></table>
                    </div>
                    <div id="vdonblank"> </div>
                    <div id="vwuser"> </div>
               </div>
                <!-- End of Tabs Control -->
                <div class="divSubmitButtons" class="ui-corner-all">
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
            <div id="achgPw" class="hhk-member-detail" style="display:none;font-size:0.95em;">
            	<div style="margin: 0.5em 0 0.5em 0;">The user's password will be reset to a random temporary password. <br>They will be required to change their password when they log in.</div>
                <table>
                    <tr>
                        <td class="tdlabel">User Name</td><td><input id="txtUserName" type="text" value="<?php echo $userName; ?>" class="ro ignrSave" readonly="readonly" style="width: 100%" /></td>
                    </tr><tr>
                        <td class="tdlabel">Admin Password</td><td style="display: flex"><input id="txtOldPw" type="password" value="" title="Enter your password, not the users old password." /><button class="showPw" style="font-size: .75em; margin-left: 1em;" tabindex="-1">Show</button></td>
                    </tr><tr>
                        <td colspan ="2">
                        	<span id="apwChangeErrMsg"><?php echo $PWresultMessage; ?></span>
                        	<div id="apwNewPW" style="display:hidden; margin: 0.5em 0 0.5em 0;"></div>
                        </td>
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
                <table style="width:100%;">
                    <tr>
                        <td class="tdlabel">Country: </td><td><select id="zipCtry" class="input-medium bfh-countries" data-country="US"></select></td>
                    </tr>
                    <tr>
                        <td class="tdlabel">Postal Code: </td><td><input type="text" id="txtZipSch" class="input-medium" value="" title="Type in the postal code."/></td>
                    </tr>
                    <tr><td colspan="3" id="placeList"></td></tr>
                </table>
            </div>
            <div id="vwebUser" style="display:none; font-size: 0.9em;">
                <?php echo $webUserDialogMarkup; ?>
            </div>
        </div>  <!-- div id="page"-->
        <?php if ($uS->ShowGuestPhoto) {
            echo '<script type="text/javascript" src="' . UPPLOAD_JS . '"></script>';
        ?>
        	<script>
        		$(document).ready(function(){
        			window.uploader = new Upploader.Uppload({lang: Upploader.en});
        		});
        	</script>
        <?php } ?>
        <script type="text/javascript"><?php include_once("js/nameEd.js") ?></script>
    </body>
</html>
