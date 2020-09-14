<?php
use HHK\SysConst\VolRank;
use HHK\sec\WebInit;
use HHK\Config_Lite\Config_Lite;
use HHK\sec\Session;
use HHK\SysConst\WebRole;
use HHK\AlertControl\AlertMessage;
use HHK\sec\Labels;

/**
 * VolAction.php
 *
 * @category  Volunteer
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

require_once ('VolIncludes.php');

//require_once (CLASSES . 'UserCategories.php');


function getVolUSerMarkup(\PDO $dbh, $id) {

    $gvol = array();
    $idName = intval($id);

    $stmt = $dbh->query("select `Vol_Category_Title`, `Vol_Code_Title`, `Vol_Status`, `Vol_Rank_Title`, `Vol_Begin`, `Vol_End`, concat(Vol_Category, '|', Vol_Code) as `Vol_Cat_Code`, `Vol_Rank`
    from `vmember_categories` where idName = $idName and `Vol_Category` <> 'Vol_Type' order by `Vol_Category`, `Vol_Code`;");

    while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $gvol[$r["Vol_Cat_Code"]] = $r;
    }


    if (count($gvol) > 0) {

        // Get counts
        $query = "select  concat(v.Vol_Category, '|', v.Vol_Code) as `Vol_Cat_Code`, count(v.idname) as `count`
        from `vmember_categories` v join `vmember_categories` vc
            on concat(v.Vol_Category, '|', v.Vol_Code) = concat(vc.Vol_Category, '|', vc.Vol_Code) and vc.idName = $idName
        where  v.Vol_Category <> 'Vol_Type' and v.Vol_Status = 'a'
        group by v.Vol_Category, v.Vol_Code
        order by v.Vol_Category, v.Vol_Code;";

        $stmtr = $dbh->query($query);

        while ($r = $stmtr->fetch(\PDO::FETCH_ASSOC)) {

            if (isset($r["Vol_Cat_Code"]) && isset($gvol[$r["Vol_Cat_Code"]])) {
                $gvol[$r["Vol_Cat_Code"]]["count"] = $r["count"];
            }
        }


        $mk = "<tr>
                <th>Description</th>
                <th>My Status</th>
                <th>My Role</th>
                <th>Member Count</th>
                <th>Chair List</th>
            </tr>";

        foreach ($gvol as $rw) {

            if ($rw["Vol_Status"] == 'a') {
                $status = "Active";
            } else if (!is_null($rw["Vol_End"])) {
                $status = "Retired";
            } else {
                $status = "Inactive";
            }

            $count = "0";
            if (isset($rw["count"])) {
                $count = $rw["count"];
            }

            $vrank = $rw["Vol_Rank_Title"];
            if (($rw["Vol_Rank"] == VolRank::Chair || $rw["Vol_Rank"] == VolRank::CoChair) && $rw["Vol_Status"] == 'a') {
                $vrank = "<input type='button' class='inputForChair' id='c|" . $rw["Vol_Cat_Code"] . "' name='chair' value='$vrank' title='Click to edit members.'/> ";
            } else if ($rw["Vol_Status"] != 'a') {
                $vrank = "";
            }

            $mk .= "<tr>
            <td style='vertical-align: middle;'><span id='vcgd" . $rw["Vol_Cat_Code"] . "'>" . $rw["Vol_Code_Title"] . "</span></td>
            <td style='vertical-align: middle; text-align:center;'>" . $status . "</td>
                <td style='vertical-align: middle; text-align:center;'>" . $vrank . "</td>
                <td style='vertical-align: middle; text-align:center;'>" . $count . "</td>";
            if ($count > 0) {
                $mk .= "<td style='vertical-align: middle;'><input type='button' class='inptForList' id='" . $rw["Vol_Cat_Code"] . "' name='chairs' value='List Contacts' style='font-size: 0.8em;'/></td>";
            } else {
                $mk .= "<td>&nbsp;</td>";
            }

            $mk .= "</tr>";
        }

    } else {
        $mk = "<tr><td>No Volunteer Groups</td></tr>";
    }

    return $mk;
}



$wInit = new WebInit("p");

$dbh = $wInit->dbh;
$PageMenu = $wInit->generatePageMenu();
$labels = Labels::getLabels();

// Load the session with member - based lookups
$wInit->sessionLoadGenLkUps();
$wInit->sessionLoadVolLkUps();

// set user id
$uS = Session::getInstance();
$id = $uS->uid;
$role = $uS->rolecode;
$uname = $uS->username;

$welcomeMessage = "";
$customItems = "";
$committeeSelector = "";
$categories = array();
$userData = array();

// Personal data
$userData["myId"] = "$id";
$userData["role"] = "$role";
$userData["name"] = $uname;


if ($role <= WebRole::Admin) {

    $query = "select
v.Name_First as First,
v.Name_Last as Last,
v.Name_Nickname as Nickname,
c.Vol_Code,
c.Vol_Category,
c.Vol_Category_Title,
c.`Vol_Code_Title`,
'' as `Vol_Rank_Title`,
'' as `Vol_Rank`,
c.Colors,
ifnull(g.Description, 'y') as `Allow_Select`,
ifnull(g2.Description, 'y') as `Hide_Add_Members`,
ifnull(g3.Description, 'n') as `Show_AllCategory`
from vcategory_listing c left join gen_lookups g on g.Table_Name='Cal_Select' and g.Code = concat(c.Vol_Category, c.Vol_Code)
left join gen_lookups g2 on g2.Table_Name = 'Cal_Hide_Add_Members' and g2.Code = concat(c.Vol_Category, c.Vol_Code)
left join gen_lookups g3 on g3.Table_Name = 'Cal_Show_AllCategory' and g3.Code = concat(c.Vol_Category, c.Vol_Code)
, vmember_listing v
where c.Vol_Category <> 'Vol_Type' and v.Id = :id
order by c.Vol_Code_Title;";

} else {

    $query = "select
v.Name_First as First,
v.Name_Last as Last,
v.Name_Nickname as Nickname,
case when c.Vol_Rank = '' then '" . VolRank::Guest . "' else c.Vol_Rank end as Vol_Rank,
case when c.Vol_Rank_Title = '' then 'Guest' else c.Vol_Rank_Title end as Vol_Rank_Title,
c.Vol_Code,
c.Vol_Category,
c.Vol_Category_Title,
c.Vol_Code_Title,
c.Colors,
ifnull(g.Description, 'y') as `Allow_Select`,
ifnull(g2.Description, 'y') as `Hide_Add_Members`,
ifnull(g3.Description, 'n') as `Show_AllCategory`
from vmember_categories c join vmember_listing v ON v.Id = c.idName
left join gen_lookups g on g.Table_Name='Cal_Select' and g.Code = concat(c.Vol_Category, c.Vol_Code)
left join gen_lookups g2 on g2.Table_Name = 'Cal_Hide_Add_Members' and g2.Code = concat(c.Vol_Category, c.Vol_Code)
left join gen_lookups g3 on g3.Table_Name = 'Cal_Show_AllCategory' and g3.Code = concat(c.Vol_Category, c.Vol_Code)
where c.Vol_Status = 'a' and c.idName = :id and c.Vol_Category <> 'Vol_Type'
order by c.Vol_Code_Title;";

}

$parms = array(":id" => $id);
$stmt = $dbh->prepare($query, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
$stmt->execute($parms);
$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);


// get the name
if (count($rows) > 0 ) {

    if ($rows[0]["Nickname"] != "") {
        $userData["name"] = $rows[0]["Nickname"] . " " . $rows[0]["Last"];
    } else {
        $userData["name"] = $rows[0]["First"] . " " . $rows[0]["Last"];
    }
    $welcomeMessage = "Welcome ".$userData["name"];
}

if (count($rows) == 0) {

    $committeeSelector = "<span style='font-weight: bold; margin-right:5px;'></span>
        <select id='selcustomCat' style='display:none;' ><option value='0' selected='selected'></option></select>";

    $categories[0] = array(
        "Vol_Category" => '',
        "Vol_Code" => '',
        "Vol_Rank" => 'm',
        "Vol_Code_Title" => 'none',
        "Vol_Rank_Title" => 'Member',
        "AllowCalSelect" => '',
        "ShowAddAll" => '',
        "HideAddMem" => 'y',
        "showOpenShifts" => 'n',
        "Colors" => ''
    );

}



// Single Category?  Hide the category selector.
if (count($rows) == 1) {

    $r = $rows[0];
    // in case there is only a single entry, set it up as selected
    $committeeSelector = "<span style='font-weight: bold; margin-right:5px;'>" . $r["Vol_Code_Title"] . " - " . $r["Vol_Rank_Title"] . "</span>
        <select id='selcustomCat' style='display:none;' ><option value='0' selected='selected'></option></select>";

    $showOpenShifts = 'y';
    if ($r["Allow_Select"] == 'y') {
        $showOpenShifts = 'n';
    }

    $categories[0] = array(
        "Vol_Category" => $r["Vol_Category"],
        "Vol_Code" => $r["Vol_Code"],
        "Vol_Rank" => $r["Vol_Rank"],
        "Vol_Code_Title" => $r["Vol_Code_Title"],
        "Vol_Rank_Title" => $r["Vol_Rank_Title"],
        "AllowCalSelect" => $r["Allow_Select"],
        "ShowAddAll" => $r["Show_AllCategory"],
        "HideAddMem" => $r["Hide_Add_Members"],
        "showOpenShifts" => $showOpenShifts,
        "Colors" => $r["Colors"]
    );

}

if (count($rows) > 1) {
    // More than one committee
    $customItems = "<option value='all55' selected='selected'>All My Entries</option>";
    $categories["all55"] = "all55";


    for ($i = 0; $i < count($rows); $i++) {

        if ($rows[$i]["Vol_Rank_Title"] != "") {
            $rank = " - " . $rows[$i]["Vol_Rank_Title"];
        } else {
            $rank = "";
        }


        $customItems .= "<option value='$i'>"
                . $rows[$i]["Vol_Code_Title"] . $rank . "</option>";

        $showOpenShifts = 'y';
        if ($rows[$i]["Allow_Select"] == 'y') {
            $showOpenShifts = 'n';
        }

        $categories[$i] = array(
            "Vol_Category" => $rows[$i]["Vol_Category"],
            "Vol_Code" => $rows[$i]["Vol_Code"],
            "Vol_Rank" => $rows[$i]["Vol_Rank"],
            "Vol_Code_Title" => $rows[$i]["Vol_Code_Title"],
            "Vol_Rank_Title" => $rows[$i]["Vol_Rank_Title"],
            "AllowCalSelect" => $rows[$i]["Allow_Select"],
            "ShowAddAll" => $rows[$i]["Show_AllCategory"],
            "HideAddMem" => $rows[$i]["Hide_Add_Members"],
            "showOpenShifts" => $showOpenShifts,
            "Colors" => $rows[$i]["Colors"]
        );
    }

    $committeeSelector = "<span style='font-weight: bold; margin-right:5px;'>Select Volunteer Category:</span>
                        <select id='selcustomCat' >$customItems</select>";

}

/*
 * Volunteer categories
 */
$volPanelMkup = getVolUSerMarkup($dbh, $id);


$calAlert = new AlertMessage("calContainer");
$calAlert->set_DisplayAttr("none");
$calAlert->set_Context(AlertMessage::Success);
$calAlert->set_iconId("calIcon");
$calAlert->set_styleId("calResponse");
$calAlert->set_txtSpanId("calMessage");
$calAlert->set_Text("oh-oh");

$calReplyMessage = $calAlert->createMarkup();

$categoryData = json_encode($categories);
$userDataEnc = json_encode($userData);


?>
<!DOCTYPE html >
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title><?php echo $wInit->pageTitle; ?></title>
        <?php echo JQ_DT_CSS; ?>
        <?php echo JQ_UI_CSS; ?>
        <?php echo FULLC_CSS; ?>
        <?php echo PUBLIC_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo FAVICON; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo FULLC_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="js/volAction.js"></script>
    <script type="text/javascript">
        var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM d, YYYY"); ?>';
        $(document).ready(function() {
            "use strict";
            var d = new Date();
            var catData = $.parseJSON('<?php echo $categoryData; ?>');
            var userData = $.parseJSON('<?php echo $userDataEnc; ?>');
            var wsAddress = "gCalFeed.php";
            var eventJSONString = '';
            var edm, listEvtTable;
            if (catData !== undefined) {
                eventJSONString = wsAddress + "?c=get&myid=" + userData.myId + "&vcc=" + get_vcc(catData[$('#selcustomCat').val()]);
            }
            var listJSON = wsAddress + "?c=list&myid=" + userData.myId;
            $('#mainTabs').tabs({
                beforeActivate: function (event, ui) {

                    if (ui.newPanel.length > 0) {

                        if (ui.newTab.prop('id') === 'lilisttab' && !listEvtTable) {

                            listEvtTable = $('#dataTbl').DataTable({
                                "processing": true,
                                "ajax": {
                                    "url": listJSON,
                                    "type": "POST"
                                },
                                "columnDefs": dtCols,
                                "deferRender": true,
                                "order": [[2, 'desc']],
                                "displayLength": 30,
                                "lengthMenu": [[15, 30, 60, -1], [15, 30, 60, "All"]],
                                "dom": '<"top"if>rt<"bottom"ip>'
                            });

                            $('#dataTbl').on( 'click', 'tbody tr', function () {
                                var eid = listEvtTable.row(this).data();
                                listClickRow(eid.id, userData, catData, edm, wsAddress);
                            });

                            $('#listDateRange').text('Starting from this month through 1 year');
                        }
                    }
                }
            });

            $('#checkDelete').dialog({
                autoOpen: false,
                width: 400,
                resizable: false,
                title: 'Delete Appointment',
                buttons: {
                    "Delete": function () {
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

                        $( this ).dialog( "close" ); },
                    Cancel: function () { $( this ).dialog( "close" ); }
                }
            })
            function dialogSaveButton() {

                edm.removeClass("ui-state-error");
                edm.tipsP.text("").removeClass("ui-state-highlight");

                if (doDialogSave(userData, catData[$('#selcustomCat').val()], edm) != false) {
                    edm.evt = null;
                    $(this).dialog( "close" );
                }

            };
            function dialogDeleteButton() {
                if (edm.evt && edm.evt.shl != 1) {
                    $( this ).dialog( "close" );

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
            };
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
                tipsP: $( ".validateTips" ),
                eid: '',
                evt: null,
                newEvent: false,
                removeClass: function (rc) {
                    $("#dialog input, #dialog select").removeClass(rc);
                    //this.edesc.removeClass(rc);
                },
                makeButtons: {
                    "Reset": dialogResetButton,
                    "Save": dialogSaveButton,
                    Cancel: function () { $( this ).dialog( "close" ); }
                },
                viewButtons: {
                    "Ok": function () { $( this ).dialog( "close" ); }
                },
                editButtons: {
                    "Delete": dialogDeleteButton,
                    "Update": dialogSaveButton,
                    Cancel: function () { $( this ).dialog( "close" ); }
                }
            };
            $('.ckdate').datepicker({
                changeMonth: true,
                changeYear: false,
                autoSize: true
            });
            $('#cbRepeater').change(function () {
                if ($(this).prop('checked')) {
                    $('.repeater-disable').prop("disabled", false);
                } else {
                    $('.repeater-disable').prop("disabled", "disabled");
                }
            });
            $('#selRepeaterUnits').change(function () {
                if ($(this).val() == 'm1') {
                    $('tr.monthDayChooser-display').show();
                } else {
                    $('tr.monthDayChooser-display').hide();
                }
            });
            $('tr.monthDayChooser-display').hide();
            $("#dListmembers").dialog({
                autoOpen: false,
                width: 750,
                resizable: true
            });
            edm.startTB.change(function () {
                        edm.tipsP.text("").removeClass("ui-state-highlight");
                        edm.removeClass("ui-state-error");
                $("#" + edm.endTB.attr("id") ).datepicker( "option", "minDate", new Date($(this).val()));
                updateDuration(edm);
            });
            edm.endTB.change(function () {
                        edm.tipsP.text("").removeClass("ui-state-highlight");
                        edm.removeClass("ui-state-error");
                $("#" + edm.startTB.attr("id") ).datepicker( "option", "maxDate", new Date($(this).val()));
                updateDuration(edm);
            });
            $('#eHour, #eMin').change(function () {
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
            $('#sHour, #sMin').change(function () {
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
            $( "#dialog" ).dialog({
                autoOpen: false,
                width: 475,
                resizable: false,
                close: function() {
                    edm.removeClass("ui-state-error");
                    edm.tipsP.text("").removeClass("ui-state-highlight");
                }
            });
            $('button').button();
            $('#repeatReturn').dialog({
                autoOpen: false,
                width: 475,
                resizable: false,
                title: 'Results',
                buttons: {
                    "Ok": function () { $( this ).dialog( "close" ); }
                }
            })
            $('#calendar').fullCalendar({
                aspectRatio: 1.7,
                theme: true,
                header: {left: 'title', center: 'month,agendaWeek,agendaDay', right: 'today prev,next' },
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
                                $(this).fullCalendar( 'clientEvents', function (event) {
                                    if (event.shlid > 0 && event.shl == 1) {
                                        return true;
                                    }
                                    return false;
                                }
                            ));
                            $('#openShiftsLink').css('display','block');
                            $('input.hhk-openshift').click(function () {
                                var evt = $('#calendar').fullCalendar('clientEvents', $(this).attr('name'));
                                if (evt[0] === null) {
                                    $(this).remove();
                                    return;
                                }
                                edm.evt = evt[0];
                                edm.newEvent = false;
                                edm.removeClass("ui-state-error");
                                edm.tipsP.text("").removeClass("ui-state-highlight");
                                clickEvent($('#calendar').fullCalendar( 'getView' ), userData, catData[$('#selcustomCat').val()], edm);
                            });
                        } else {
                            $('#openShifts').off("click", 'input.hhk-openshift');
                            $('#openShifts').html('');
                            $('#openShiftsLink').css('display','none');
                        }
                        $('body').css('cursor', "auto");
                    }
                },
                eventSources: [{
                        url: eventJSONString,
                        ignoreTimezone: false
                    }],
                select: function( startDate, endDate, allDay, jsEvent, view ) {
                    var rightNow = new Date();
                    var tdy = new Date(rightNow.getFullYear(), rightNow.getMonth(), rightNow.getDate(), 0, 0, 0, 0);
                    if (startDate < tdy || catData[$('#selcustomCat').val()] === undefined) {
                        return;
                    }
                    var category = catData[$('#selcustomCat').val()];

                    if (catData[$('#selcustomCat').val()]["AllowCalSelect"] == 'y' || userData.role <= 10 || (category.Vol_rank && category.Vol_Rank[0] == 'c')) {

                        edm.evt = null;
                        edm.tipsP.text("").removeClass("ui-state-highlight");
                        edm.removeClass("ui-state-error");
                        edm.newEvent = true;
                        calSelect(startDate, endDate, allDay, view, userData, catData[$('#selcustomCat').val()], edm);

                    } else if (get_vcc(catData[$('#selcustomCat').val()]) == 'all55') {
                        // Cannot create a new event until you pick a category.
                        flagAlertMessage('Select a category from above.', true);
                        $('#divSelCategory').effect('pulsate', 200);
                        $('#divSelCategory').addClass("ui-state-highlight");

                    }
                },
                eventDrop: function( event, dayDelta, minuteDelta, allDay, revertFunc, jsEvent, ui, view ) {
                    if (event.shl === 0) {
                        dropEvent(event, dayDelta, minuteDelta, allDay, revertFunc, userData.myId);
                    } else {
                        revertFunc();
                    }
                },
                eventResize: function( event, dayDelta, minuteDelta, revertFunc, jsEvent, ui, view ) {
                    if (event.shl === 0) {
                        resizeEvent(event, dayDelta, minuteDelta, revertFunc, userData.myId);
                    } else {
                        revertFunc();
                    }
                },
                eventClick: function(calEvent, jsEvent, view) {
                    //dialogEvent = calEvent;
                    edm.evt = calEvent;
                    edm.newEvent = false;
                    edm.removeClass("ui-state-error");
                    $('#divSelCategory').removeClass("ui-state-highlight");
                    edm.tipsP.text("").removeClass("ui-state-highlight");
                    clickEvent(view, userData, catData[$('#selcustomCat').val()], edm);
                }
            });
            $("#btnRefresh").click( function () {
                $('#calendar').fullCalendar( 'refetchEvents');
            });
            $("#btnListRefresh").click( function () {
                listEvtTable.ajax.reload();
            });

            $('#gotoListDate').change( function() {
                var dp = new Date($('#gotoListDate').datepicker('getDate'));
                var gt = "&start=" + (dp.getMonth() + 1) + "/" + dp.getDate() + "/" + dp.getFullYear();

                listEvtTable.ajax.url(listJSON + gt).load();
                $('#listDateRange').text('Starting from ' + (dp.getMonth() + 1) + "/" + dp.getDate() + "/" + dp.getFullYear() + ' through 1 year');
            });

            $('#gotoDate').change( function() {
                var gtDate = new Date($('#gotoDate').datepicker('getDate'));
                $('#calendar').fullCalendar('gotoDate', gtDate);
            });
//                $('.fc-day-number').mouseenter (function () {
//                    $('body').css('cursor', 'pointer');
//                });
//                $('.fc-day-number').mouseleave (function () {
//                    $('body').css('cursor', 'auto');
//                });
//                $('.fc-day-number').mousedown (function (event) {
//                    $('body').css('cursor', 'auto');
//                    event.preventDefault();
//                    var date = this.innerHTML;
//                    var dt = $('#calendar').fullCalendar('getDate');
//                    dt.setDate(date);
//                    $('#calendar').fullCalendar( 'changeView', 'agendaDay' );
//                    $('#calendar').fullCalendar( 'gotoDate', dt );
//                });
        function calendarSourceString() {
                var cat = get_vcc(catData[$('#selcustomCat').val()]);
                var hc;
                if ($('#includeHouseCal').prop('checked')) {
                    hc = "";
                } else {
                    hc = "&hc=0";
                }
                $('#calendar').fullCalendar('removeEventSource', eventJSONString);
                $('#calendar').fullCalendar('removeEvents');

                if (cat == "all55") {
                    eventJSONString = wsAddress + "?c=get&myid=" + userData.myId + hc;
                    //listJSON = wsAddress + "?c=list&myid=" + userData.myId;
                } else {
                    eventJSONString = wsAddress + "?c=get&myid=" + userData.myId + "&vcc=" + cat + hc;
                    //listJSON = wsAddress + "?c=list&myid=" + userData.myId + "&vcc=" + cat;
                }

                $('#calendar').fullCalendar('addEventSource', eventJSONString);

        }

        $('#selcustomCat').change( function() {
            $('#divSelCategory').removeClass("ui-state-highlight");
            calendarSourceString();
        });
        $('#includeHouseCal').change(function () {
            calendarSourceString();
        });
        $('#secondName').autocomplete({
            source: function (request, response) {
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
                                data = [{"value" : data.error}];
                            }
                            response(data);
                        }
                    );
                } else {
                    response([{}]);
                }
            },
            minLength: 3,
            select: function( event, ui ) {
                if (ui.item && ui.item.id > 0) {
                    edm.secondIdTB.val(ui.item.id);
                } else {
                    edm.secondIdTB.val('');
                }
            },
            change: function (event, ui) {
                if ($(this).val() == '') {
                    edm.secondIdTB.val('');
                }
            }
        });
        $('#txtsearch').autocomplete({
            source: function (request, response) {
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
                                data = [{"value" : data.error}];
                            }
                            response(data);
                        }
                    );
                } else {
                    response();
                }
            },
            minLength: 3,
            select: function( event, ui ) {
                if (ui.item && ui.item.id > 0) {
                    edm.memIdTB.val(ui.item.id);
                    edm.memNameTB.val(ui.item.value);
                    if (edm.evt.shlid > 0) {
                        edm.titleTB.val(edm.memNameTB.val());
                    }
                }
            }
        });

$('input.inptForList, input.inputForChair').button();
$('input.inptForList').click(function () {
    var vcodes = $(this).attr("id");
    var inptName = $(this).attr("name");
    var desc = document.getElementById('vcgd' + vcodes);
    desc = desc.innerHTML;
    $.post("ws_vol.php", {
            cmd: inptName,
            code: vcodes,
            desc: desc
        },
    function(data) {

        try {
            data = $.parseJSON(data);
        } catch (err) {
            alert('Bad JSON Encoding');
            return;
        }

        if (data.error) {

            if (data.gotopage) {
                window.open(data.gotopage, '_self');
            }
            flagAlertMessage(data.error, true);
            return;

        } else if (data.table) {

            $('#' + data.removeId).remove();
            var tbl = $(data.table);
            $('#dListmembers').append(tbl);

            $('#dListmembers').dialog("option", "title", "Contacts Listing for " + data.title);
            $('#dListmembers').dialog("open");
        }
    });
});
$('.inputForChair').click(function () {
    var vcodes, codes;
    vcodes = $(this).attr("id");
    codes = vcodes.split("|");
    window.location = 'MemEdit.php?vg=' + codes[1] + "|" + codes[2];
});

});
    </script>

        <style type="text/css">
            @media print {
                .prtClass {display:block;}
                .hhk-welcomeVol, #listTab, #volcatTab, #divnoPrt, #openShifts, #openShiftsLink, #ulnoPrt, header, #version {display:none;}
            }
        </style>
    </head>
    <body>
            <?php echo $PageMenu; ?>
        <div id="page">
            <div id="volCalendar">
                <div class="hhk-welcomeVol"><?php echo $welcomeMessage; ?></div>
                <div id="divAlertMsg"><?php echo $calReplyMessage; ?></div>
                <div id="mainTabs">
                    <ul id="ulnoPrt">
                        <li><a href="#calTab">Calendar View</a></li>
                        <li id="lilisttab"><a href="#listTab">List View</a></li>
                        <li><a href="#volcatTab">My Volunteer Categories</a></li>
                    </ul>
                    <div id="listTab" class="ui-tabs-hide hhk-border" style="display:none;">
                            <div id="btnListRefresh" style="font-size: 0.9em; float: left;margin-bottom:7px; padding:3px;">
                                <button>Refresh List</button>
                            </div>
                             <div style="float: left; padding-top:5px;">
                                <label for="gotoListDate" style="margin-left:15px;">Go To Date: </label>
                                <input type="text" id="gotoListDate" class="ckdate ignrSave" value=""/>
                                <span id="listDateRange" style="margin-left:15px;"></span>
                             </div>
                        <div style="clear: both;"></div>
                        <table class="display" border="0" id="dataTbl"></table>
                    </div>
                    <div id="calTab">
                        <div id="divnoPrt" style="margin-bottom:7px; padding:3px; border-bottom: solid 1px;">
                            <div id="btnRefresh" style="font-size: 0.9em; float: left;">
                                <button>Refresh Calendar</button>
                            </div>
                            <div id="divSelCategory" style="float: left; margin-left:20px;">
                                <?php echo $committeeSelector; ?>
                            </div>
                            <div style="font-size: 0.9em; float: left; padding-top:5px;">
                                <label for="gotoDate" style="margin-left:15px;">Go To Date: </label>
                                <input type="text" id="gotoDate" class="ckdate ignrSave" value=""/>
                                <label for="includeHouseCal" style="margin-left:15px;">Include House Calendar</label>
                                <input type="checkbox" id="includeHouseCal" class="ignrSave" checked="checked" />
                            </div>
                            <div style="clear: both;"></div>
                        </div>
                        <div id="openShiftsLink" style="display:none;"><a href="#openShifts">See Open Shifts</a></div>
                        <div id="calendar" class="prtClass"></div>
                        <div id="openShifts" class="hhk-border"></div>
                    </div>
                    <div id="volcatTab"  class="ui-tabs-hide hhk-border" style="display:none;">
                            <table>
                                <?php echo $volPanelMkup ?>
                            </table>
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
                        <tr>
                            <td colspan="2" class="namesch-display" style="margin:0; padding:0;">
                                <div><table style="width:100%"><tr>
                                    <th title="Search for other members in this committee">Member Search</th>
                                    <td title="Type the first 3 letters of the first or last name"><input type="text" id="txtsearch" size="20" class="dis-me" title="Type the first 3 letters of the first or last name" /></td>
                                </tr></table></div>
                            </td>
                        </tr>
                    </table>
                </div>
                <div id="dListmembers" class="hhk-border">
                    <table id="tblListTable"></table>
                </div>
            </div>
        </div>
    </body>
</html>
