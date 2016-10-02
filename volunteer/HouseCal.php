<?php
/**
 * HouseCal.php
 *
 * @category  Volunteer
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
require ('VolIncludes.php');
require (SEC . 'Login.php');

$uS = Session::getInstance();
$uS->destroy();
$uS->regenSessionId();

if (isset($_COOKIE["volpc"])) {

    $cval = decryptMessage($_COOKIE['volpc']);

    if ($cval != $_SERVER['REMOTE_ADDR'] . 'eric') {

        header('location:index.php');
        exit();
    }
} else {

    header('location:index.php');
    exit();
}

require ('classes' . DS . 'cEventClass.php');
require (CLASSES . "shellEvent_Class.php");
require (CLASSES . 'UserCategories.php');
require (CLASSES . 'PDOdata.php');
require (DB_TABLES . 'volCalendarRS.php');
require (CLASSES . 'emailClass.php');
require (CLASSES . 'VolCal.php');
require (MEMBER . 'MemberSearch.php');
require (CLASSES . 'Purchase/PriceModel.php');



// Get the site configuration object
$config = Login::initializeSession(ciCFG_FILE);


$resourceURL = $config->getString("site", "Site_URL", "");
$siteName = $config->getString("site","Site_Name", "");

//
//
$categories = array();
$userData = array();
//
// Personal data
$userData["myId"] = "0";
$userData["role"] = "100";
$userData["name"] = '';
//
if (isset($_GET["c"])) {

    $c = filter_var($_GET["c"], FILTER_SANITIZE_STRING);

//    $myId = intval($uid, 10);
//    $cats = new UserCategories($myId, $roleId, $uname);
//    $cats->loadFromDb($dbcon);
//
    addslashesextended($_REQUEST);

    $vcc = "";
    if (isset($_GET["vcc"])) {
        $vcc = filter_var(urldecode($_GET["vcc"]), FILTER_SANITIZE_STRING);
    }

    //InitSec::startUp();

    $dbh = initPDO();


    $startTime = "";
    $endTime = "";

    try {

        switch ($c) {

            case "get":
                if (isset($_GET["start"])) {
                    $startTime = intval(filter_var(urldecode($_GET["start"]), FILTER_SANITIZE_NUMBER_INT), 10);
                }
                if (isset($_GET["end"])) {
                    $endTime = intval(filter_var(urldecode($_GET["end"]), FILTER_SANITIZE_NUMBER_INT), 10);
                }
                $houseCal = 1;
                if (isset($_GET["hc"])) {
                    $houseCal = intval(filter_var(urldecode($_GET["hc"]), FILTER_SANITIZE_NUMBER_INT), 10);
                }


                $events = VolCal::GetCalView($dbh, $startTime, $endTime, $houseCal, $vcc, new UserCategories(0, 100));

                break;

            case "getevent":
                $eid = "";
                if (isset($_GET["eid"])) {
                    $eid = filter_var(urldecode($_GET["eid"]), FILTER_SANITIZE_STRING);
                }

                $events = VolCal::getEvent($dbh, $eid);
                break;


            case "new":
                $evt = new cEventClass();
                $evt->LoadFromGetString($_GET);

                $cats = new UserCategories($evt->get_idName(), '100');
                $cats->loadFromDb($dbh);

                $events = VolCal::CreateCalEvent($dbh, $evt, $cats);
                break;

            case "upd":
                $evt = new cEventClass();
                $evt->LoadFromGetString($_GET);

                $cats = new UserCategories($evt->get_idName(), '100');
                $cats->loadFromDb($dbh);

                $events = VolCal::UpdateCalEvent($dbh, $evt, $cats);
                break;

            case "del":
                $eid = "";
                $delall = "0";
                $justme = "0";
                $sendemail = "0";
                $myId = 0;

                if (isset($_GET["id"])) {
                    $eid = filter_var($_GET["id"], FILTER_SANITIZE_STRING);
                }
                if (isset($_GET["delall"])) {
                    $delall = filter_var($_GET["delall"], FILTER_SANITIZE_STRING);
                }
                if (isset($_GET["justme"])) {
                    $justme = filter_var($_GET["justme"], FILTER_SANITIZE_STRING);
                }
                if (isset($_GET["sendemail"])) {
                    $sendemail = filter_var($_GET["sendemail"], FILTER_SANITIZE_STRING);
                }

                if (isset($_GET['myid'])) {
                    $myId = intval(filter_var($_GET['myid'], FILTER_SANITIZE_NUMBER_INT), 10);
                }

                $cats = new UserCategories($myId, '100');
                $cats->loadFromDb($dbh);

                $events = VolCal::DeleteCalEvent($dbh, $eid, $delall, $justme, $sendemail, $cats);

                break;

            case "filter":

                //get the q parameter from URL
                $q = filter_var(urldecode($_REQUEST["letters"]), FILTER_SANITIZE_STRING);

                // get basis
                $basis = filter_var(urldecode($_REQUEST["basis"]), FILTER_SANITIZE_STRING);

                $fltr = filter_var(urldecode($_REQUEST["filter"]), FILTER_SANITIZE_STRING);

                $events = MemberSearch::volunteerCmteFilter($dbh, $q, $basis, $fltr);

                break;

            default:
                $events[] = array("error" => "Bad Command to Calendar Feeder");
        }
    } catch (PDOException $ex) {

        $events = array("error" => "Database Error" . $ex->getMessage());
    } catch (Hk_Exception $ex) {

        $events = array("error" => "HouseKeeper Error" . $ex->getMessage());
    }



    echo( json_encode($events) );
    exit();
}




$showOpenShifts = 'y';

$categories[0] = array(
    "Vol_Category" => 'Vol_Activities',
    "Vol_Code" => '1',
    "Vol_Rank" => 'm',
    "Vol_Code_Title" => 'Friendly Faces',
    "Vol_Rank_Title" => 'Member',
    "AllowCalSelect" => 'n',
    "ShowAddAll" => 'n',
    "HideAddMem" => '',
    "showOpenShifts" => $showOpenShifts,
    "Colors" => 'green'
);




$calAlert = new alertMessage("calContainer");
$calAlert->set_DisplayAttr("none");
$calAlert->set_Context(alertMessage::Success);
$calAlert->set_iconId("calIcon");
$calAlert->set_styleId("calResponse");
$calAlert->set_txtSpanId("calMessage");
$calAlert->set_Text("oh-oh");

$calReplyMessage = $calAlert->createMarkup();

$categoryData = json_encode($categories);
$userDataEnc = json_encode($userData);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
    "http://www.w3.org/TR/html4/loose.dtd">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $siteName; ?></title>
<?php echo TOP_NAV_CSS; ?>
        <link href="<?php echo JQ_UI_CSS; ?>" rel="stylesheet" type="text/css" />
        <link href="<?php echo FULLC_CSS; ?>" rel="stylesheet" type="text/css" />
        <link href="css/publicStyle.css" rel="stylesheet" type="text/css" />
        <script type="text/javascript" src="<?php echo $resourceURL; ?><?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo $resourceURL; ?><?php echo JQ_UI_JS; ?>"></script>
    </head>
    <body>
        <div>
            <div id="divAlertMsg"><?php echo $calReplyMessage; ?></div>
            <select id='selcustomCat' style='display:none;'><option value='0' selected='selected'></option></select>
            <div id="volCalendar">
                <div id="whoami" class="ui-corner-all" style="position: absolute; top: 7px; right: 250px; z-index: 5; padding:30px; background-color: white;border: 1px solid #D19405;">
                    <input type="button" value="Who Are You?" id="btnwhoami"/>
                </div>
                <div id="mainTabs">
                    <ul>
                        <li><a href="#calTab">Calendar</a></li>
                        <li><a href="#shiftsTab">Open Shifts</a></li>
                    </ul>
                    <div id="calTab">
                        <div id="btnRefresh" style="float:left;">
                            <button>Refresh Calendar</button>
                        </div>
                        <div style="float:left;margin-top:5px; position: relative;z-index: 9;">
                            <label for="gotoDate" style="margin-left:15px;">Go To Date: </label>
                            <input type="text" id="gotoDate" class="ckdate ignrSave" value="" style='z-index: 9;'/>
                        </div>
                        <div style="clear: both;"></div>
                        <div id="calendar" style="margin-top:10px;"></div>
                    </div>
                    <div id="shiftsTab">
                        <div id="openShifts" class="hhk-border"></div>
                    </div>
                </div>
                <div id="repeatReturn" style="display:none;">
                    <table>
                        <tr>
                            <th colspan="2"><span id="spnRetMessage"></span></th>
                        </tr>
                        <tr>
                            <td class="tdlabel">New Appointments:</td>
                            <td><span id="spnRetNew"></span></td>
                        </tr>
                        <tr>
                            <td class="tdlabel">Already Taken:</td>
                            <td><span id="spnRetLost"></span></td>
                        </tr>
                        <tr>
                            <td class="tdlabel">I already Had:</td>
                            <td><span id="spnRetMine"></span></td>
                        </tr>
                        <tr>
                            <td class="tdlabel">I took over:</td>
                            <td><span id="spnRetReplaced"></span></td>
                        </tr>
                    </table>
                </div>
                <div id="whoru" style="display:none;">
                    <table style="margin: 20px;">
                        <tr>
                            <th>Who Are You?</th>
                        </tr>
                        <tr>
                            <td>Type the first 3 letters of your last name and then choose your name from the list of names that should appear.</td>
                        </tr>
                        <tr>
                            <td title="Type the first 3 letters of your last name"><input type="text" id="txtsearch" size="20" title="Type the first 3 letters of your last name" /></td>
                        </tr>
                    </table>
                </div>
                <div id="checkDelete" style="display:none;">
                    <table style="margin: 20px;">
                        <tr>
                            <td style="margin:5px; border:none;"><input type="radio" id="justthisone" name="oneorall"  checked="checked" /><label for="justthisone">Just this appointment</label></td>
                        </tr>
                        <tr>
                            <td id="ckDelall" style="margin:5px; border:none;"><input type="radio" id="allofem" name="oneorall"/><label for="allofem">This and all future appointments</label></td>
                        </tr>
                        <tr>
                            <td class="hhk-deljustme" style="margin:5px; border:none;"><input type="checkbox" id="delMe" name="meorboth" checked="checked" disabled="disabled" /><label for="delMe">Just Remove My Name</label></td>
                        </tr>
                        <tr>
                            <td id="hhk-sendEmail" style="margin:5px; border:none; display:none;"><input type="checkbox" id="sendDelEmail" checked="checked"/><label for="sendDelEmail">Send Email Notice</label></td>
                        </tr>
                    </table>
                </div>
                <div id="dialog" class="hhk-border" style="display: none;">
                    <table>
                        <tr>
                            <td colspan="2"><p class="validateTips"></p></td>
                        </tr><tr>
                            <td class="tdlabel">Title</td>
                            <td title="Type or edit the title"><input type="text" id="eTitle" class="dis-me" value="" size="30" title="Type or edit the title"/></td>
                        </tr><tr>
                            <td class="tdlabel" title="Start date and time">Start</td>
                            <td><input class="dis-me ckdate" type="text" id="eStart" value="" title="Click to change the start date"/>&nbsp;<select id="sHour" class="dis-me" title="Select the start time hour"><option value="0">12 (am)</option><option value="1">1 (am)</option><option value="2">2 (am)</option><option value="3">3 (am)</option><option value="4">4 (am)</option><option value="5">5 (am)</option><option value="6">6 (am)</option><option value="" selected="selected"></option><option value="7">7 (am)</option><option value="8">8 (am)</option><option value="9">9 (am)</option><option value="10">10 (am)</option><option value="11">11 (am)</option><option value="12">12 (pm)</option><option value="13">1 (pm)</option><option value="14">2 (pm)</option><option value="15">3 (pm)</option><option value="16">4 (pm)</option><option value="17">5 (pm)</option><option value="18">6 (pm)</option><option value="19">7 (pm)</option><option value="20">8 (pm)</option><option value="pm21">9 (pm)</option><option value="pm22">10 (pm)</option><option value="23">11 (pm)</option></select>
                                &nbsp;:&nbsp;<select class="dis-me" id="sMin" title="Select the start time minutes"><option value="0">00</option><option value="5">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30">30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option></select></td>
                        </tr><tr>
                            <td class="tdlabel" title="End date and time">End</td>
                            <td><input class="dis-me ckdate" type="text" id="eEnd" value="" title="Click to change the end date"/>&nbsp;<select id="eHour" class="dis-me" title="Select the end time hour"><option value="0">12 (am)</option><option value="1">1 (am)</option><option value="2">2 (am)</option><option value="3">3 (am)</option><option value="4">4 (am)</option><option value="5">5 (am)</option><option value="6">6 (am)</option><option value="" selected="selected"></option><option value="7">7 (am)</option><option value="8">8 (am)</option><option value="9">9 (am)</option><option value="10">10 (am)</option><option value="11">11 (am)</option><option value="12">12 (pm)</option><option value="13">1 (pm)</option><option value="14">2 (pm)</option><option value="15">3 (pm)</option><option value="16">4 (pm)</option><option value="17">5 (pm)</option><option value="18">6 (pm)</option><option value="19">7 (pm)</option><option value="20">8 (pm)</option><option value="21">9 (pm)</option><option value="22">10 (pm)</option><option value="23">11 (pm)</option></select>
                                &nbsp;:&nbsp;<select class="dis-me" id="eMin" title="Select the end time minutes"><option value="0">00</option><option value="5">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30">30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option></select></td>
                        </tr><tr>
                            <td class="tdlabel">Duration</td>
                            <td><input type="text" id="duration" class="ro" value="" size="17" readonly="readonly" title="Edit the Start and/or End times to accurately reflect your service duration"/><span class="logtime-display" style="margin-left:15px;" title="Check the box to acknowledge volunteer time served."><label for="cbLogTime" title="Check the box to acknowledge volunteer time served.">Log My Time: </label><input type="checkbox" id="cbLogTime" class="dis-me" title="Check the box to acknowledge volunteer time served." /></span></td>
                        </tr><tr>
                            <td class="tdlabel" title="Enter any notes or other reminders">Notes</td>
                            <td><textarea id="eDesc" class="dis-me" rows="1" cols="30" title="Enter any notes or other reminders"></textarea><input type="hidden" id="eId" value="" /></td>
                        </tr><tr>
                            <td class="tdlabel">Name</td>
                            <td><input type="text" id="memName" class="ro" value="" readonly="readonly" title="Volunteer's Name" />
                                <span style="margin-right:5px; margin-left:10px;">Id</span>
                                <input type="text" id="memId" class="ro" value="" size="3" readonly="readonly"/></td>
                        </tr><tr>
                            <td class="tdlabel shift-partner" title="Search for a partner for this shift.">Shift Partner</td>
                            <td class="shift-partner"><input type="text" id="secondName" value="" class="dis-me" title="Type the first 3 letters of the first or last name." />
                                <span style="margin-right:5px; margin-left:10px;">Id</span>
                                <input type="text" id="secondId" class="ro" value="" size="3" readonly="readonly"/></td>
                        </tr><tr>
                            <td class="tdlabel category-wide"><label for="catWide">Include All</label></td>
                            <td class="category-wide"><input type="checkbox" id="catWide" class="dis-me" title="Check to add this appointment to everyone's personal calendar." /></td>
                        </tr><tr>
                            <td class="tdlabel category-display">Category</td>
                            <td class="category-display"><input type="text" id="memVcc" class="ro" size="25" readonly="readonly" title="Volunteer Category" /><span id="source"></span><input type="hidden" id="eCathdn" value="" /></td>
                        </tr>
                        <tr><td colspan="2"  class="repeater-display"></td></tr>
                        <tr>
                            <td colspan="2" class="repeater-display" style="border-width:0;margin:0;padding:0;">
                                <div>
                                    <table style="width:100%">
                                        <tr>
                                            <td colspan="4" style="border-top-width: 0;"><label for="cbRepeater">Create Repeat Assignments </label><input type="checkbox" id="cbRepeater" class="dis-me"/></td>
                                        </tr>
                                        <tr>
                                            <td class="tdlabel">Each</td>
                                            <td><select id="selRepeaterUnits" class="repeater-disable dis-me" disabled="disabled"><option value="w1" selected="selected">Week</option><option value="w2">2 Weeks</option><option value="m1">Month</option></select></td>
                                            <td class="tdlabel"><span style="margin-right:5px; margin-left:10px;">For</span></td>
                                            <td><select id="selRepeaterMonths" class="repeater-disable dis-me" disabled="disabled"><option value="1" selected="selected">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option></select> Months</td>
                                        </tr>
                                        <tr class="monthDayChooser-display" style="text-align: center;">
                                            <td colspan="4"><span id="txtRepWeek"></span></td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="margin:0; padding:0;">
                                <div id="divRelievable" style="display:none;">
                                    <label for="eMakeAvailable"><span id="lblMakeAvail" title="Check to allow others to serve this shift">Make Available:</span></label><input type="checkbox" id="eMakeAvailable" title="Check to allow others to serve this shift" />
                                    <label for="eTmFixed"><span id="lblFixed" style="padding-left: 7px;" title="Check to disallow changes to the start or end times">Time Fixed:</span></label><input type="checkbox" id="eTmFixed" title="Check to disallow changes to the start or end times" />
                                    <label for="eLocked"><span id="lblLocked" style="padding-left: 7px;" title="Check to disallow any changes">Locked:</span></label><input type="checkbox" id="eLocked" title="Check to disallow any changes"/>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <script type="text/javascript" src="<?php echo $resourceURL; ?><?php echo FULLC_JS; ?>"></script>
        <script type="text/javascript" src="js/houseCal.js"></script>
        <script type="text/javascript">
            $(document).ready(function() {
                "use strict";
                $.ajaxSetup({
                    beforeSend: function() {
                        //$('#loader').show()
                        $('body').css('cursor', "wait");
                    },
                    complete: function() {
                        $('body').css('cursor', "auto");
                        //$('#loader').hide()
                    },
                    cache: false
                });

                var d = new Date();
                var catData = $.parseJSON('<?php echo $categoryData; ?>');
                var userData = $.parseJSON('<?php echo $userDataEnc; ?>');
                var wsAddress = "HouseCal.php";
                var eventJSONString = '';
                var edm;
                var userTimer;
                if (catData !== undefined) {
                    eventJSONString = wsAddress + "?c=get&vcc=" + get_vcc(catData[$('#selcustomCat').val()]);
                }
                var calReload = setInterval(
                    function(){
                        if (userData.myId == 0) {
                            var view = $('#calendar').fullCalendar('getView');
                            if (view.title != 'month') {
                                $('#calendar').fullCalendar('changeView', 'month');
                            }
                            $('#calendar').fullCalendar('refetchEvents');
                        }
                    },
                    300 * 1000  // 5 minutes
                );
                $('#mainTabs').tabs();
                $('#checkDelete').dialog({
                    autoOpen: false,
                    width: 400,
                    resizable: false,
                    title: 'Delete Appointment',
                    buttons: {
                        "Delete": function() {
                            var delall = 0, justme = 0, sendemail = 0;
                            if ($('#allofem').prop("checked")) {
                                delall = 1;
                            }
                            if ($('#delMe').prop("checked")) {
                                justme = 1;
                            }
                            if ($('#sendDelEmail').prop("Checked")) {
                                sendemail = 1;
                            }

                            doCalDelete(edm.evt.id, delall, justme, sendemail, userData.myId);
                            edm.evt = null;

                            $(this).dialog("close");
                        },
                        Cancel: function() {
                            $(this).dialog("close");
                        }
                    }
                })
                function dialogSaveButton() {
                    edm.removeClass("ui-state-error");
                    edm.tipsP.text("").removeClass("ui-state-highlight");
                    if (doDialogSave(userData, catData[$('#selcustomCat').val()], edm) != false) {
                        edm.evt = null;
                        $(this).dialog("close");
                    }
                }
                function dialogDeleteButton() {
                    if (edm.evt && edm.evt.shl != 1) {
                        $(this).dialog("close");

                        $('#justthisone').prop("checked", true);

                        if (edm.evt.rptid == 0) {
                            $('#ckDelall').css("display", "none");
                        } else {
                            $('#ckDelall').css("display", "table-cell");
                        }

                        // set the remove partner options
                        if (edm.evt.nid2 == userData.myId) {
                            $('.hhk-deljustme').css("display", "table-cell");
                        } else {
                            $('.hhk-deljustme').css("display", "none");
                        }

                        $('#sendDelEmail').prop("checked", false);
                        // Offer to send delete email
                        if (edm.evt.showEmailDel === 1 && edm.evt.nid2 != userData.myId) {
                            $('#hhk-sendEmail').css('display', 'table-cell');
                            if (edm.evt.end >= d) {
                                $('#sendDelEmail').prop("checked", true);
                            }
                        } else {
                            $('#hhk-sendEmail').css('display', 'none');
                        }

                        $('#checkDelete').dialog('open');
                    }
                }
                function dialogResetButton() {
                    if (edm) {
                        resetDialog(edm);
                    }
                }
                edm = {
                    titleTB: $('#eTitle'), startTB: $('#eStart'), endTB: $('#eEnd'), mvccTB: $('#memVcc'),
                    shourSEL: $('#sHour'), sminSEL: $('#sMin'), ehourSEL: $('#eHour'), eminSEL: $('#eMin'),
                    edescTA: $("#eDesc"),
                    memIdTB: $('#memId'), memNameTB: $('#memName'), txtSchTB: $('#txtsearch'),
                    searchDisp: $('td.namesch-display'), catWideCB: $('#catWide'),
                    partnerDisp: $('.shift-partner'), catWideDisp: $('.category-wide'),
                    secondNameTB: $('#secondName'), secondIdTB: $('#secondId'),
                    repeatrCB: $('#cbRepeater'), repeatrDisp: $('td.repeater-display'), repeatrMonthDisp: $('tr.monthDayChooser-display'),
                    repeatrUnitsSEL: $('#selRepeaterUnits'), repeatrUnitQtySEL: $('#selRepeaterMonths'), RepeatrWeekTxt: $('#txtRepWeek'),
                    logTimeCB: $('#cbLogTime'), logTimeSPAN: $('#duration'), logTimeDisp: $('span.logtime-display'),
                    tipsP: $(".validateTips"),
                    eid: '',
                    evt: null,
                    newEvent: false,
                    removeClass: function(rc) {
                        $("#dialog input, #dialog select").removeClass(rc);
                        //this.edesc.removeClass(rc);
                    },
                    makeButtons: {
                        "Reset": dialogResetButton,
                        "Save": dialogSaveButton,
                        Cancel: function() {
                            $(this).dialog("close");
                        }
                    },
                    viewButtons: {
                        "Ok": function() {
                            $(this).dialog("close");
                        }
                    },
                    editButtons: {
                        "Delete": dialogDeleteButton,
                        "Update": dialogSaveButton,
                        Cancel: function() {
                            $(this).dialog("close");
                        }
                    }
                };
                $('.ckdate').datepicker({
                    changeMonth: true,
                    changeYear: false,
                    autoSize: true
                });
                $('#cbRepeater').change(function() {
                    if ($(this).prop('checked')) {
                        $('.repeater-disable').prop("disabled", false);
                    } else {
                        $('.repeater-disable').prop("disabled", "disabled");
                    }
                });
                $('#selRepeaterUnits').change(function() {
                    if ($(this).val() == 'm1') {
                        $('tr.monthDayChooser-display').show();
                    } else {
                        $('tr.monthDayChooser-display').hide();
                    }
                });
                $('tr.monthDayChooser-display').hide();
                edm.startTB.change(function() {
                    edm.tipsP.text("").removeClass("ui-state-highlight");
                    edm.removeClass("ui-state-error");
                    $("#" + edm.endTB.attr("id")).datepicker("option", "minDate", new Date($(this).val()));
                    updateDuration(edm);
                });
                edm.endTB.change(function() {
                    edm.tipsP.text("").removeClass("ui-state-highlight");
                    edm.removeClass("ui-state-error");
                    $("#" + edm.startTB.attr("id")).datepicker("option", "maxDate", new Date($(this).val()));
                    updateDuration(edm);
                });
                $('#eHour, #eMin').change(function() {
                    // The rest Only important if the same day
                    if (edm.startTB.val() == edm.endTB.val() && edm.shourSEL.val() != '' && edm.ehourSEL.val() != '') {


                        var st = parseInt(edm.shourSEL.val());
                        var ed = parseInt(edm.ehourSEL.val());
                        if (st > ed) {
                            updateTips('End Time must be greater than the start time.', edm.tipsP, edm.ehourSEL);
                            edm.ehourSEL.children(':eq(7)').prop('selected', true);
                            edm.eminSEL.children(':first-child').prop('selected', true);
                        } else {
                            edm.tipsP.text("").removeClass("ui-state-highlight");
                            edm.removeClass("ui-state-error");
                        }
                    }

                    updateDuration(edm);
                });
                $('#sHour, #sMin').change(function() {
                    edm.tipsP.text("").removeClass("ui-state-highlight");
                    edm.removeClass("ui-state-error");
                    // If the end hour is unset, set it one hour later than the start.
                    if (edm.ehourSEL.val() == '') {
                        // then change it to one hour past start hour
                        if (edm.shourSEL.children(':selected').val() == edm.shourSEL.children(':last-child').val()) {
                            edm.ehourSEL.children(':last-child').prop("selected", true);
                        } else {
                            var v = edm.shourSEL.children(':selected').next().val()
                            edm.ehourSEL.children('[value="' + v + '"]').prop("selected", true);
                            if (v == '') {
                                // opps, picked the blank entry
                                edm.ehourSEL.children(':selected').next().prop('selected', true);
                            }
                        }
                    }

                    // The rest Only important if the same day
                    if (edm.startTB.val() == edm.endTB.val() && edm.shourSEL.val() != '' && edm.ehourSEL.val() != '') {

                        // Push the end time around
                        var st = parseInt(edm.shourSEL.val());
                        var ed = parseInt(edm.ehourSEL.val());
                        if (st > ed) {
                            // Adjust the end hour to the start hour
                            edm.ehourSEL.children('[value="' + st + '"]').prop("selected", true);
                        }

                        if (edm.shourSEL.val() == edm.ehourSEL.val()) {
                            // check the minutes
                            if (edm.sminSEL.val() > edm.eminSEL.val()) {
                                // Make the end the same.
                                edm.eminSEL.children('[value="' + edm.sminSEL.val() + '"]').prop("selected", true);

                            }
                        }
                    }
                    updateDuration(edm);
                });
                $("#dialog").dialog({
                    autoOpen: false,
                    width: 475,
                    resizable: false,
                    close: function() {
                        edm.removeClass("ui-state-error");
                        edm.tipsP.text("").removeClass("ui-state-highlight");
                    }
                });
                $('#whoru').dialog({
                    autoOpen: false,
                    width: 475,
                    resizable: false,
                    title: 'Friendly Face Name Getter',
                    buttons: {
                        Cancel: function () {$(this).dialog('close')}
                    }
                });
                $('button, #btnwhoami').button();
                $('#repeatReturn').dialog({
                    autoOpen: false,
                    width: 475,
                    resizable: false,
                    title: 'Results',
                    buttons: {
                        "Ok": function() {
                            $(this).dialog("close");
                        }
                    }
                })
                $('#calendar').fullCalendar({
                    aspectRatio: 1.6,
                    theme: true,
                    header: {left: 'title', center: 'month,agendaWeek,agendaDay', right: 'today prev,next'},
                    allDayDefault: false,
                    lazyFetching: true,
                    draggable: true,
                    editable: true,
                    selectHelper: true,
                    selectable: true,
                    unselectAuto: true,
                    minTime: '5:00am',
                    firstHour: 7,
                    year: d.getFullYear(),
                    month: d.getMonth(),
                    ignoreTimezone: false,
                    loading: function(isLoading) {
                        if (!isLoading) {
                            if (catData[$('#selcustomCat').val()]["showOpenShifts"] == 'y') {
                                showOpenShifts('openShifts',
                                        $(this).fullCalendar('clientEvents', function(event) {
                                    if (event.shlid > 0 && event.shl == 1) {
                                        return true;
                                    }
                                    return false;
                                }
                                ));
                                $('#openShiftsLink').css('display', 'block');
                                $('input.hhk-openshift').click(function() {
                                    var evt = $('#calendar').fullCalendar('clientEvents', $(this).attr('name'));
                                    if (evt[0] == null) {
                                        $(this).remove();
                                        return;
                                    }
                                    edm.evt = evt[0];
                                    edm.newEvent = false;
                                    edm.removeClass("ui-state-error");
                                    edm.tipsP.text("").removeClass("ui-state-highlight");
                                    clickEvent($('#calendar').fullCalendar('getView'), userData, catData[$('#selcustomCat').val()], edm);
                                });
                            } else {
                                $('#openShifts').off("click", 'input.hhk-openshift');
                                $('#openShifts').html('');
                                $('#openShiftsLink').css('display', 'none');
                            }
                            $('body').css('cursor', "auto");
                        }
                    },
                    eventSources: [{
                            url: eventJSONString,
                            ignoreTimezone: false
                        }],
                    select: function(startDate, endDate, allDay, jsEvent, view) {
                    },
                    eventDrop: function(event, dayDelta, minuteDelta, allDay, revertFunc, jsEvent, ui, view) {
                        revertFunc();

                    },
                    eventResize: function(event, dayDelta, minuteDelta, revertFunc, jsEvent, ui, view) {
                        revertFunc();
                    },
                    eventClick: function(calEvent, jsEvent, view) {
                        //dialogEvent = calEvent;
                        edm.evt = calEvent;
                        edm.newEvent = false;
                        edm.removeClass("ui-state-error");
                        edm.tipsP.text("").removeClass("ui-state-highlight");
                        if (calEvent.shl === 1 && (userData.myId == 0)) {
                            $('input#txtsearch').val('');
                            $('#whoru').dialog('open');
                        } else {
                            clickEvent(view, userData, null, edm);
                        }
                        // Reset timer
                        if (userData.myId > 0) {
                            clearTimeout(userTimer);
                            userTimer = setTimeout(function () {userTimeout()}, 600 * 1000)
                        }
                    }
                });
                $("#btnRefresh").click(function() {
                    $('#calendar').fullCalendar('refetchEvents');
                });
                $("#btnwhoami").click(function() {
                    if ($(this).val() == 'Who Are You?') {
                        $('input#txtsearch').val('');
                        $('#whoru').dialog('open');
                    } else if ($(this).val() == 'Log Out') {
                        clearTimeout(userTimer);
                        $('#btnwhoami').val('Who Are You?')
                        $('#whoami span').remove();
                        $('#whoami').css('background-color', 'white');
                        $('#dialog').dialog('close');
                        userData.myId = "0";
                        userData.role = '100';
                        userData.name = "";
                    }
                });
                function userTimeout() {
                    if ($("#btnwhoami").val() == 'Log Out') {
                        $("#btnwhoami").click();
                    }
                }
                $('#gotoDate').change(function() {
                    var gtDate = new Date($('#gotoDate').datepicker('getDate'));
                    $('#calendar').fullCalendar('gotoDate', gtDate);
                });
                function calendarSourceString() {
//                    var cat = get_vcc(catData[$('#selcustomCat').val()]);
//                    var hc;
//                    if ($('#includeHouseCal').prop('checked')) {
//                        hc = "";
//                    } else {
//                        hc = "&hc=0";
//                    }
//                    $('#calendar').fullCalendar('removeEventSource', eventJSONString);
//                    $('#calendar').fullCalendar('removeEvents');
//
//                    eventJSONString = wsAddress + "?c=get&myid=" + userData.myId + "&vcc=" + cat + hc;
//
//                    $('#calendar').fullCalendar('addEventSource', eventJSONString);

                }
                $('#includeHouseCal').change(function() {
                    calendarSourceString();
                });
                $('#secondName').autocomplete({
                    source: function(request, response) {
                        var gvcc;
                        if (edm.evt) {
                            gvcc = edm.evt.vcc;
                        } else {
                            gvcc = get_vcc(catData[$('#selcustomCat').val()]);
                        }
                        if (gvcc != 'all55' && gvcc != '') {
                            var inpt = {
                                cmd: "filter",
                                letters: request.term,
                                basis: "m",
                                id: userData.myId,
                                filter: gvcc
                            };
                            $.getJSON("VolNameSearch.php", inpt,
                                    function(data, status, xhr) {
                                        if (data.error) {
                                            data = [{"value": data.error}];
                                        }
                                        response(data);
                                    }
                            );
                        } else {
                            response([{}]);
                        }
                    },
                    minLength: 3,
                    select: function(event, ui) {
                        if (ui.item && ui.item.id > 0) {
                            edm.secondIdTB.val(ui.item.id);
                        } else {
                            edm.secondIdTB.val('');
                        }
                    },
                    change: function(event, ui) {
                        if ($(this).val() == '') {
                            edm.secondIdTB.val('');
                        }
                    }
                });
                $('#txtsearch').autocomplete({
                    source: function(request, response) {
                        var gvcc;
                        if (edm.evt) {
                            gvcc = edm.evt.vcc;
                        } else {
                            gvcc = get_vcc(catData[$('#selcustomCat').val()]);
                        }
                        if (gvcc != '') {
                            var inpt = {
                                c: "filter",
                                letters: request.term,
                                basis: "m",
                                filter: gvcc
                            };
                            $.getJSON("HouseCal.php", inpt,
                                    function(data, status, xhr) {
                                        if (data.error) {
                                            data = [{"value": data.error}];
                                        }
                                        response(data);
                                    }
                            );
                        } else {
                            response();
                        }
                    },
                    minLength: 2,
                    select: function(event, ui) {
                        if (ui.item && ui.item.id > 0) {
                            $('#whoru').dialog("close");
                            $('#btnwhoami').val('Log Out')
                            $('#whoami').prepend($('<span style="font-weight:bold;margin-right:10px;">Welcome ' + ui.item.first + ' ' + ui.item.last + '</span>'));
                            $('#whoami').css('background-color', 'lightgreen');
                            userData.myId = ui.item.id;
                            userData.role = '100';
                            userData.name = ui.item.value;
                            clearTimeout(userTimer);
                            userTimer = setTimeout(function () {userTimeout()}, 600 * 1000);
                            if (edm.evt) {
                                clickEvent(null, userData, null, edm);
                            }
                        }
                    }
                });
            });
        </script>
    </body>
</html>
