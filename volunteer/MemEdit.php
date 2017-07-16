<?php
/**
 * memEdit.php
 *
 * @category  Volunteer
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("VolIncludes.php");


require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (MEMBER . 'OrgMember.php');
require (MEMBER . "Addresses.php");
require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'ActivityRS.php');
require (CLASSES . 'CleanAddress.php');
require (CLASSES . 'AuditLog.php');
require (CLASSES . 'Relation.php');
require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';
require (CLASSES . "volunteer.php");

$wInit = new webInit();
$dbh = $wInit->dbh;



$page = $wInit->page;
$pageTitle = $wInit->pageTitle;

// get session instance
$uS = Session::getInstance();

$menuMarkup = $wInit->generatePageMenu();
$resourceURL = $uS->resourceURL;


// Instantiate the alert message control
$emAlertMsg = new alertMessage("divAlert2");
$emAlertMsg->set_txtSpanId("alertMsg2");
$emAlertMsg->set_DisplayAttr("none");
$emResultAlert = $emAlertMsg->createMarkup("ignore");

// Load the session with member - based lookups
$wInit->sessionLoadGenLkUps();
$volLkups = $wInit->sessionLoadVolLkUps();


// Initial vars
$resultMessage = "";
$alertMsg = new alertMessage("divAlert1");
$id = 0;

$uname = $uS->username;
$uid = intval($uS->uid, 10);
$volGroupCode = "";
$GroupSelect = "";

/*
 * Volunteer categories
 */
$volGroups = array();
$query = "select distinct Category, Description, Category_Code, Vol_Code
from vvol_categories2 where Category_Code <> 'Vol_Type'
and concat(Category_Code, Vol_Code) in (select concat(Vol_Category, Vol_Code) from name_volunteer2 where idName = :uid and Vol_Status='a'
and (Vol_Rank = '" . VolRank::Chair . "' or Vol_Rank = '" . VolRank::CoChair . "')) order by Category, Description;";

$stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
$stmt->execute(array(':uid'=>$uid));

if ($stmt->rowCount() > 0) {
    $volGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//  user chairs Any volunteer categories?
if (count($volGroups) == 0) {
    header("location=" . $page->get_Default_Page());
    die("");
} else if (count($volGroups) == 1) {
    // Just a single group, so dont bother with the group selector.
    $volGroupCode = $volGroups[0]["Category_Code"] . "|" . $volGroups[0]["Vol_Code"];

}


// get code if already selected.
if (isset($_POST["selVolGroup"])) {
    $volGroupCode = filter_var($_POST["selVolGroup"], FILTER_SANITIZE_STRING);
}




/*
 * Check Get and Post
 */
if (isset($_GET["id"]) || isset($_GET["vg"])) {
    addslashesextended($_GET);
    if (isset($_GET["id"])) {
        $id = intval(filter_var($_GET["id"], FILTER_SANITIZE_NUMBER_INT), 10);
    }

    if (isset($_GET["vg"])) {
        $volGroupCode = filter_var($_GET["vg"], FILTER_SANITIZE_STRING);
    }
} else if (isset($_POST["hdnid"])) {
    /*
     * This is the ID that the previous page instance saved for us.
     */
    $h_idTxt = filter_var($_POST["hdnid"], FILTER_SANITIZE_NUMBER_INT);
    $h_id = intval($h_idTxt, 10);
    $id = $h_id;
}




// make sure vol group code is kosher
if ($volGroupCode != "") {
    $groupCodeGood = false;
    // Make sure the group code is contained in our list of good codes.
    foreach ($volGroups as $g) {
        $catGroup = $g["Category_Code"] . "|" . $g["Vol_Code"];
        if ($catGroup == $volGroupCode) {
            $groupCodeGood = true;
        }
    }

    // Now check the flag...
    if ($groupCodeGood == false) {
        // trun off both main indicators.
        $volGroupCode = "";
        $alertMsg->set_Context(alertMessage::Alert);
        $alertMsg->set_Text("Bad Volunteer Category.");
        $resultMessage = $alertMsg->createMarkup();
    }
}

// if the volunteer group code is empty, zero the ID and don't load the member edit portion of hte page
if ($volGroupCode == "") {
    $id = 0;
}

// Determine the default member type.
$defBasis = MemBasis::Indivual;

// Instantiate the member object
try {

    $name = Member::GetDesignatedMember($dbh, $id, $defBasis);

} catch (Exception $ex) {

    $alertMsg->set_Context(alertMessage::Alert);
    $alertMsg->set_Text($ex);
    $resultMessage = $alertMsg->createMarkup();

    $id = 0;
    $name = Member::GetDesignatedMember($dbh, $id, $defBasis);

}


// the rest
try {

    $address = new Address($dbh, $name, $uS->nameLookups[GL_TableNames::AddrPurpose]);
    $phones = new Phones($dbh, $name, $uS->nameLookups[GL_TableNames::PhonePurpose]);
    $emails = new Emails($dbh, $name, $uS->nameLookups[GL_TableNames::EmailPurpose]);

} catch (Exception $ex) {
    exit("Error opening supporting objects: " . $ex->getMessage());
}




/*
 * This is the member Name Edit SAVE submit button.  It checks for a change in any data field
 * and updates the database accordingly.
 */
if (isset($_POST["btnSubmit"]) && $id > 0) {

    $msg = "";

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

        // Volunteer Category
        if ($volGroupCode != '') {
            $parts = explode('|', $volGroupCode);
            if (count($parts) == 2) {
                $vl = $volLkups["Vol_Category"][$parts[0]];
                $volCat = new VolunteerCategory($vl[0], $vl[1], $vl[2]);
                $msg .= $volCat->saveVolCategory($dbh, $id, $_POST[$volCat->getCategoryCode()], $uname);
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


//
// Create the committee chooser control
$committeeTabName = "";
$CategoryTitle = "";
$committeeTitle = "";

$grpSelOptions = "";
foreach ($volGroups as $r) {
    $groupSelectorCode = $r["Category_Code"] . "|" . $r["Vol_Code"];
    if ($groupSelectorCode == $volGroupCode) {
        $selGroupSelected = "selected='selected'";
        $committeeTabName = $r["Category"] . " - " . $r["Description"];
        $CategoryTitle = $r["Category"];
        $committeeTitle = $r["Description"];
    } else {
        $selGroupSelected = "";
    }
    $grpSelOptions .= "<option value='$groupSelectorCode' $selGroupSelected>" . $r["Category"] . ": " . $r["Description"] . "</option>";
}

if (count($volGroups) > 1) {
    // Set up the group selector...
    $GroupSelect = "Choose a committee to view: <select id='selVolGroup' name='selVolGroup' ><option value=''>Retrieved ".count($volGroups)." Committees...</option>";
    $GroupSelect .= $grpSelOptions . "</select>";
}



//
// Name Edit Row
$nameMarkup = $name->createMarkupTable($dbh, 'hhk-hideStatus', 'hhk-hideBasis');
$niceName = $name->getMemberName();

// Excludes, Demographics and admin tabs
$miscTabs = $name->createMiscTabsMarkup($dbh);
// Addresses
$addrPanelMkup = $address->createMarkup('', TRUE, $uS->county);
// Phone Numbers
$phoneMkup = $phones->createMarkup();
// Email addresses
$emailMkup = $emails->createMarkup();
// Mark Preferred phone and email
$prefMkup = Addresses::getPreferredPanel($phones, $emails);



/*
 * Volunteer categories
 */
$volMkup = "";
//$volRanks = readGenLookups($dbcon, "Vol_Rank");

if ($id > 0 && $volGroupCode != "") {


    $query = "select distinct g.Table_Name, g.Code as Vol_Code, ifnull(nv.Vol_Notes,'') as Vol_Notes, ifnull(nv.Vol_Status, 'z') as Vol_Status,
nv.Vol_Begin, nv.Vol_End, nv.Vol_Check_Date, ifnull(g.Description,'') as Description, ifnull(nv.Vol_Trainer, '') as Vol_Trainer, nv.Vol_Training_Date,
ifnull(nv.Dormant_Code, '') as Dormant_Code, ifnull(nv.Vol_Rank,'') as Vol_Rank, ifnull(nv.Updated_By,'') as Updated_By, nv.Last_Updated, nv.Vol_Category
from gen_lookups g left join name_volunteer2 nv on g.Table_Name = nv.Vol_Category and g.Code = nv.Vol_Code
where g.Table_Name = nv.Vol_Category and nv.idName = :id and concat(nv.Vol_Category, '|', nv.Vol_Code) = :volGroupCode and nv.Vol_Status = 'a';";
    $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
    $stmt->execute(array(':volGroupCode'=>$volGroupCode, ':id'=>$id));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) == 1) {
        $r = $rows[0];

        $volunteer = new VolunteerCategory($r["Vol_Category"], $CategoryTitle);
        $volunteer->set_rankOptions($volLkups["Vol_Rank"]);

        $volMkup = $volunteer->skinnyGroupMarkup($r);
    }

}


//Member List tab markup
$recHistory = "";
if ($volGroupCode != "") {
    $query = "SELECT v.idName, n.Name_Full, gr.Description as Role, v.Updated_By, v.Last_Updated, v.Vol_Check_Date, v.Vol_Training_Date, v.Vol_Trainer, v.Vol_Begin, v.Vol_End, v.Vol_Notes
from name_volunteer2 v left join gen_lookups gr on gr.Table_Name='Vol_Rank' and gr.Code = v.Vol_Rank
left join name n on v.idName = n.idName
where concat(v.Vol_Category, '|', v.Vol_Code) = :volGroupCode and v.Vol_Status = 'a' and n.Record_Member = 1 and n.Member_Status='a';";
    $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
    $stmt->execute(array(':volGroupCode'=>$volGroupCode));

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $recHistory = getVolGroupList($rows, $volGroupCode);

}

// Dont show the name edit part unless the name is set.
$showForm = "none";
$showMemSearch = "none";
if ($volGroupCode != "") {
    $showMemSearch = "block";
    if ($id > 0) {
        $showForm = "block";
    }
}


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

$memDataJSON = json_encode($memberData);


function getVolGroupList($rows, $volGroup, $page = "MemEdit.php") {

    $markUp = "<div id='divEmailButton'><input id='btnSendEmail' name='$volGroup' type='button' value='Send Email' /></div>";

    $markUp .= "<table><thead><tr>
    <th>Id</th>
    <th>Name</th>
    <th>Role</th>
    <th>Start</th>
    <th>Train Date</th>
    <th>Trainer</th>
    <th>Contact Date</th>
    <th>Upd. By</th>
    <th>Upd. On</th>
</tr></thead><tbody>";

    foreach ($rows as $r) {
        $markUp .= "<tr><td class='tdBox' style='vertical-align: middle;'><span class='class_history'><a href='".$page."?id=" . $r["idName"] . "&vg=$volGroup'>" . $r["idName"] . "</a></span></td>";

        $markUp .= "<td style='vertical-align: middle;'>" . $r["Name_Full"] . "</td>";
        $markUp .= "<td style='vertical-align: middle;'>" . $r["Role"] . "</td>";
        $markUp .= "<td style='vertical-align: middle;'>" . date('m/d/Y', strtotime($r["Vol_Begin"])) . "</td>";
        $markUp .= "<td>" . date('m/d/Y', strtotime($r["Vol_Training_Date"])) . "</td>";
        $markUp .= "<td>" . $r["Vol_Trainer"] . "</td>";
        $markUp .= "<td>" . date('m/d/Y', strtotime($r["Vol_Check_Date"])) . "</td>";
        $markUp .= "<td style='vertical-align: middle;'>" . $r["Updated_By"] . "</td>";
        $markUp .= "<td style='vertical-align: middle;'>" . date('m/d/Y', strtotime($r["Last_Updated"])) . "</td>";
    }

    return $markUp . "</tbody></table>";
}



?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $pageTitle; ?></title>
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />
        <?php echo PUBLIC_CSS; ?>
        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
    </head>
    <body <?php if ($wInit->testVersion) echo "class='testbody'"; ?> >
            <?php echo $menuMarkup; ?>
        <div id="page">
            <div id="contentDiv">
                <div class="ui-widget ui-widget-content ui-corner-all hhk-member-detail">
                <div style="margin: 5px;">
                    <form action="MemEdit.php" method="post" id="groupSelectForm">
                        <p style="margin-bottom:15px;"><?php echo $GroupSelect; ?></p>
                    </form>
                </div>
                <div id="searchDiv"  style="display:<?php echo $showMemSearch; ?>; margin: 15px;">
                    <input type="hidden" id="selVolGroup" name="selVolGroup" value="<?php echo $volGroupCode; ?>" />
                    <p>Member Search:<input type="text" class="ui-widget allSearch" id="txtsearch" name="txtsearch" size="20" title="Enter at least 3 characters to invoke search" />
                    </p>
                </div>
                </div>
                <div style="clear:both; margin: 15px;"><?php echo $resultMessage ?></div>
                <div id="showMemberForm"  class="hhk-border" style="float:left; display:<?php echo $showForm; ?>; border: 1px solid #383838; padding:15px; margin-bottom:15px;">
                    <form action="MemEdit.php" method="post"  id="form1" name="form1" >
                        <h3><?php echo $niceName; ?></h3>
                <div class="ui-widget ui-widget-content ui-corner-all hhk-member-detail">
                    <?php echo $nameMarkup; ?>
                </div>
                <div style="clear:both;"></div>
                <div id="divaddrTabs" class="hhk-showonload hhk-member-detail" style="display:none;">
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
                        <div style="clear:both;"></div>
                        <div id="divVolInfoTabs" class="hhk-member-detail">
                            <ul>
                                <li><a href="#vVolTab"><?php echo $committeeTabName ?></a></li>
                            </ul>
                            <div id="vVoltab">
                                <?php echo $volMkup; ?>
                            </div>
                        </div>
                        <div style="clear:both;"></div>
                        <div style="text-align: right; margin-right:15px; margin-top:5px;">
                            <input type="reset" id="btnReset" name="btnReset" value="Reset" />
                            <input type="submit" id="btnSubmit" value="Save" name="btnSubmit" />
                        </div>
                        <div id="submit"></div>
                        <input type="hidden" name="hdnid" id="hdnid" value="<?php echo $id ?>" />
                        <input type="hidden" name="selVolGroup" value="<?php echo $volGroupCode; ?>" />

                    </form>
                </div>
                <div style="clear:both;"></div>
                <div style="display:<?php echo $showMemSearch; ?>;">
                    <div id="divFuncTabs" class="hhk-member-detail">
                        <ul>
                            <li><a href="#mListing">Member Listings: <?php echo $committeeTabName; ?></a></li>
                        </ul>
                        <div id="mListing" class="hhk-border ui-tabs-hide">
                            <?php echo $emResultAlert; ?>
                            <?php echo $recHistory; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div id="emailDialog" style="display:none;">
                <table>
                    <tr><td><span id="emDialogMessage"></span></td></tr>
                    <tr><th>Subject</th></tr>
                    <tr>
                        <td>
                        <input id="emailSubject" type="text" value="" size="80" />
                        </td>
                    </tr>
                    <tr><th>Message</th></tr>
                    <tr>
                        <td>
                            <textarea id="emailBody" rows="15" cols="80" ></textarea>
                        </td>
                    </tr>
                </table>
            </div>
        </div>  <!-- div id="page"-->
        <script type="text/javascript" src="../js/stateCountry.js"></script>
        <script type="text/javascript"><?php include_once("js/memEdit.js") ?></script>
    </body>
</html>
