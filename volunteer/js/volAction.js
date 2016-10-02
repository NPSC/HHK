/**
 * volAction.js
 *
 *
 * @category  member
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

//function $() {}
function alertCallback() {
    "use strict";

    setTimeout(function () {
        $("#calContainer:visible").removeAttr("style").fadeOut(700);
    }, 3000
    );
}

function flagCalAlertMessage($mess, wasError) {
    "use strict";

    var spn = document.getElementById('calMessage');

    if (!wasError) {
        // define the error message markup
        $('#calResponse').removeClass("ui-state-error").addClass("ui-state-highlight");
        $('#calIcon').removeClass("ui-icon-alert").addClass("ui-icon-info");
        spn.innerHTML = "<strong>Success: </strong>" + $mess;
        $("#calContainer").show("slide", {}, 500, alertCallback);
    } else {
        // define the success message markup
        $('calResponse').removeClass("ui-state-highlight").addClass("ui-state-error");
        $('#calIcon').removeClass("ui-icon-info").addClass("ui-icon-alert");
        spn.innerHTML = "<strong>Alert: </strong>" + $mess;
        $("#calContainer").show("slide", {}, 500, alertCallback);
    }
}

function updateTips(t, tips, o) {
    "use strict";
    tips
        .text(t)
        .addClass("ui-state-highlight");
    //    setTimeout(function() {
    //        tips.removeClass( "ui-state-highlight", 360000 );
    //    }, 500 );
    if (o) {
        o.addClass("ui-state-error");
    }

}

function errorOnZero(o, n, tips) {
    "use strict";
    if (o.val() === "" || o.val() === "0" || o.val() === "00") {
        o.addClass("ui-state-error");
        updateTips(n + " cannot be zero", tips);
        return false;
    } else {
        return true;
    }
}

function checkLength(o, n, min, max, tips) {
    "use strict";
    if (o.val().length > max || o.val().length < min) {
        o.addClass("ui-state-error");
        if (o.val().length === 0) {
            updateTips("Fill in the " + n, tips);
        } else if (min === max) {
            updateTips("The " + n + " must be " + max + " characters.", tips);
        } else if (o.val().length > max) {
            updateTips("The " + n + " length is to long", tips);
        } else {
            updateTips("The " + n + " length must be between " + min + " and " + max + ".", tips);
        }
        return false;
    } else {
        return true;
    }
}

function checkRegexp(o, regexp, n, tips) {
    "use strict";
    if (!(regexp.test(o.val()))) {
        o.addClass("ui-state-error");

        updateTips(n, tips);
        return false;
    } else {
        return true;
    }
}

function dayOfWeek(day, month, year) {
    "use strict";
    var a, y, m, d;
    a = Math.floor((14 - month) / 12);
    y = year - a;
    m = month + 12 * a - 2;
    d = (day + y + Math.floor(y / 4) - Math.floor(y / 100) + Math.floor(y / 400) + Math.floor((31 * m) / 12)) % 7;
    return d + 1;
}

function leapYear(year) {
    "use strict";
    if ((year / 4)   != Math.floor(year / 4)) {
        return false;
    }
    if ((year / 100) != Math.floor(year / 100)) {
        return true;
    }
    if ((year / 400) != Math.floor(year / 400)) {
        return false;
    }
    return true;
}

function nthDay(nth, weekday, month, year) {
    "use strict";
    var days, daysofmonth = [], daysofmonthLY = [];
    daysofmonth[0] = 12;
    daysofmonth[1] = 31;
    daysofmonth[2] = 28;
    daysofmonth[3] = 31;
    daysofmonth[4] = 30;
    daysofmonth[5] = 31;
    daysofmonth[6] = 30;
    daysofmonth[7] = 31;
    daysofmonth[8] = 31;
    daysofmonth[9] = 30;
    daysofmonth[10] = 31;
    daysofmonth[11] = 30;
    daysofmonth[12] = 31;

    daysofmonthLY[0] = 12;
    daysofmonthLY[1] = 31;
    daysofmonthLY[2] = 29;
    daysofmonthLY[3] = 31;
    daysofmonthLY[4] = 30;
    daysofmonthLY[5] = 31;
    daysofmonthLY[6] = 30;
    daysofmonthLY[7] = 31;
    daysofmonthLY[8] = 31;
    daysofmonthLY[9] = 30;
    daysofmonthLY[10] = 31;
    daysofmonthLY[11] = 30;
    daysofmonthLY[12] = 31;

    if (nth > 0) {
        return (nth - 1) * 7 + 1 + (7 + weekday - dayOfWeek((nth - 1) * 7 + 1, month, year)) % 7;
    }

    if (leapYear(year)) {
        days = daysofmonthLY[month];
    } else {
        days = daysofmonth[month];
    }

    return days - (dayOfWeek(days, month, year) - weekday + 7) % 7;
}

function getMS(date) {
    "use strict";
    return Date.UTC(date.getFullYear(), date.getMonth(), date.getDate(), date.getHours(), date.getMinutes(), date.getSeconds());
}

function isDST(atDate) {
    "use strict";
    var year, DSTstart, DSTend, todayMS, DSTstartMS, DSTendMS;
    year = atDate.getFullYear();
    DSTstart = new Date(year, 3 - 1, nthDay(2, 1, 3, year), 2, 0, 0);
    DSTend   = new Date(year, 11 - 1, nthDay(1, 1, 11, year), 2, 0, 0);

    todayMS = getMS(atDate);
    DSTstartMS = getMS(DSTstart);
    DSTendMS = getMS(DSTend);

    if (todayMS > DSTstartMS && todayMS < DSTendMS) {
        return true;
    } else {
        return false;
    }
}

function convert(value) {
    "use strict";
    var hours, mins, secs, display_hours;
    hours = parseInt(value, 10);
    value -= parseInt(value, 10);
    value *= 60;
    mins = parseInt(value, 10);
    value -= parseInt(value, 10);
    value *= 60;
    secs = parseInt(value, 10);
    display_hours = hours;
    // handle GMT case (00:00)
    if (hours === 0) {
        display_hours = "00";
    } else if (hours > 0) {
        // add a plus sign and perhaps an extra 0
        display_hours = (hours < 10) ? "+0" + hours : "+" + hours;
    } else {
        // add an extra 0 if needed
        display_hours = (hours > -10) ? "-0" + Math.abs(hours) : hours;
    }

    mins = (mins < 10) ? "0" + mins : mins;
    return display_hours + ":" + mins;
}

function calculate_time_offset(atDate) {
    "use strict";
    var rightNow = new Date(), jan1, june1, temp, jan2, june2, std_time_offset, daylight_time_offset, dst, hemisphere;
    if (atDate) {
        rightNow = new Date(atDate.toString());
    }

    jan1 = new Date(rightNow.getFullYear(), 0, 1, 0, 0, 0, 0);  // jan 1st
    june1 = new Date(rightNow.getFullYear(), 6, 1, 0, 0, 0, 0); // july 1st

    temp = jan1.toGMTString();
    jan2 = new Date(temp.substring(0, temp.lastIndexOf(" ") - 1));
    temp = june1.toGMTString();
    june2 = new Date(temp.substring(0, temp.lastIndexOf(" ") - 1));
    std_time_offset = (jan1 - jan2) / (1000 * 60 * 60);
    daylight_time_offset = (june1 - june2) / (1000 * 60 * 60);


    if (std_time_offset === daylight_time_offset) {
        dst = "0"; // daylight savings time is NOT observed
    } else {
        // positive is southern, negative is northern hemisphere
        hemisphere = std_time_offset - daylight_time_offset;
        if (hemisphere >= 0) {
            std_time_offset = daylight_time_offset;
        }
        dst = "1"; // daylight savings time is observed
    }
    if (isDST(rightNow)) {
        return convert(daylight_time_offset);
    } else {
        return convert(std_time_offset);
    }
}

function cleanMS(targ) {
    "use strict";
    if (targ.length === 0) {
        targ = '00';
    } else if (targ.length === 1) {
        targ = '0' + targ;
    }

    return targ;
}

function get_vcc(catData) {
    "use strict";
    if (catData === undefined) {
        return '';
    }

    if (catData.Vol_Category == null) {
        return catData;
    }

    return catData.Vol_Category + "|" + catData.Vol_Code + "|" + catData.Vol_Rank;
}

function handleError(xhrObject, stat, thrwnError) {
    "use strict";
    alert("Server error: " + stat + ", " + thrwnError);
}

function handleListContacts(data, statusTxt, xhrObject, listTable) {
    "use strict";
    if (statusTxt != "success") {
        alert('Server had a problem.  ');
    }
    var dataObj, txt, title;

    if (data) {
        try {
            dataObj = $.parseJSON(data);
        } catch (err) {
            txt = "There was an error on this page.\n\n";
            txt += "Error description: " + err.message + "\n\n";
            txt += "Click OK to continue.\n\n";
            alert(txt);
            return;
        }

        if (dataObj.error) {
            alert('Application Error');
        } else if (dataObj.title) {
            listTable.fnAddData(dataObj.data);
            if (dataObj.title) {
                title = dataObj.title;
            }
            $('#dListmembers').dialog("option", "title", "Contacts Listing for " + title);
            $('#dListmembers').dialog("open");
        }
    }
}

var dtCols = [
    {
        "aTargets": [ 0 ],
        "bVisible": false,
        "bSortable": false,
        "bSearchable": false,
        "mDataProp": function (source, type, val) {
            "use strict";
            if (type === 'set') {
                source.id = val;
                return null;
            }
            return source.id;
        }
    },
    {
        "aTargets": [ 1 ],
        "sTitle": "Title",
        "mDataProp": function (source, type, val) {
            "use strict";
            if (type === 'set') {
                source.E_Title = val;
                return null;
            }
            return source.E_Title;
        }
    },
    {
        "aTargets": [ 2 ],
        "sTitle": "Date",
        "sType": "date",

        "mDataProp": function (source, type, val) {
            "use strict";
            if (type === 'set') {
                source.E_Start = val;
                return null;
            } else if (type === 'display') {
                if (source.E_Start_display === undefined) {
                    var dt = new Date(Date.parse(source.E_Start));
                    source.E_Start_display = $.fullCalendar.formatDate(dt, "ddd, M/d/yyyy");
                }
                return source.E_Start_display;
            }
            return source.E_Start;
        }
    },
    {
        "aTargets": [ 3 ],
        "sTitle": "Start",
        "bSortable": false,
        "sWidth": "50px",
        "mDataProp": function (source, type, val) {
            "use strict";
            if (type === 'set') {
                // We don't set this here.
                return null;
            } else if (type === 'display') {
                if (source.E_StartTime_display == undefined) {
                    var dt = new Date(Date.parse(source.E_Start));
                    source.E_StartTime_display = $.fullCalendar.formatDate(dt, "h:mmtt");
                }
                return source.E_StartTime_display;
            }

            // 'sort', 'type' and undefined all just use the integer
            return source.E_Start;
        }
    },
    {
        "aTargets": [ 4 ],
        "sTitle": "Stop",
        "bSortable": false,
        "sWidth": "50px",
        "mDataProp": function (source, type, val) {
            "use strict";
            if (type === 'set') {
                source.E_End = val;
                return null;
            } else if (type === 'display') {
                if (source.E_EndTime_display == undefined) {
                    var dt = new Date(Date.parse(source.E_End));
                    source.E_EndTime_display = $.fullCalendar.formatDate(dt, "h:mmtt");
                }
                return source.E_EndTime_display;
            }
            return source.E_End;
        }
    },
    {
        "aTargets": [ 5 ],
        "sTitle": "Hours",
        "sType": "numeric",
        "bSortable": true,
        "sWidth": "50px",
        "mDataProp": function (source, type, val) {
            "use strict";
            if (type === 'set') {
                source.E_Time_Display = null;
            }
            if (source.E_Time_Display == undefined || source.E_Time_Display == null) {
                var ds, de, dss, des, hours;
                ds = new Date(Date.parse(source.E_Start));
                de = new Date(Date.parse(source.E_End));
                dss = ds.getTime();
                des = de.getTime();
                hours = (des - dss) / 3600000;
                source.E_Time_Display = hours.toFixed(2);
            }
            return source.E_Time_Display;
        }
    },
    {
        "aTargets": [ 6 ],
        "sTitle": "Category",
        "mDataProp": function (source, type, val) {
            "use strict";
            if (type === 'set') {
                source.Category = val;
                return null;
            }
            return source.Category;
        }
    },
    {
        "aTargets": [ 7 ],
        "sTitle": "Name",
        "bVisible": false,
        "bSortable": false,
        "bSearchable": false,
        "mDataProp": function (source, type, val) {
            "use strict";
            if (type === 'set') {
                source.Name = val;
                return null;
            }
            return source.Name;
        }
    },
    {
        "aTargets": [ 8 ],
        "bVisible": false,
        "bSortable": false,
        "bSearchable": false,
        "mDataProp": function (source, type, val) {
            "use strict";
            if (type === 'set') {
                source.E_Rpt_Id = val;
                return null;
            }
            return source.E_Rpt_Id;
        }
    },
    {
        "aTargets": [ 9 ],
        "sTitle": "Logged",
        "bVisible": true,
        "bSortable": true,
        "bSearchable": false,
        "mDataProp": function (source, type, val) {
            "use strict";
            if (type === 'set') {
                source.E_Status = val;
                return null;
            }
            return source.E_Status;
        }
    }
];

function updateDuration(edm) {
    "use strict";
    if (edm.shourSEL.val() == '' || edm.ehourSEL.val() == '') {
        edm.logTimeSPAN.val('');
        return;
    }
    var st, ed, tim, days, hours, mins;
    st = new Date(edm.startTB.val() + ' ' + edm.shourSEL.val() + ':' + edm.sminSEL.val());
    ed = new Date(edm.endTB.val() + ' ' + edm.ehourSEL.val() + ':' + edm.eminSEL.val());
    tim = (ed - st) / 1000;
    days = Math.floor((tim) / 86400);
    tim = tim - (days * 86400);
    hours = Math.floor(tim / 3600);
    mins = (tim - (hours * 3600)) / 60;

    if (days == 1) {
        days = '1 day, ';
    } else if (days > 1) {
        days = days + 'days, ';
    } else {
        days = '';
    }
    if (hours == 1) {
        hours = '1 hour';
    } else {
        hours = hours + ' hours';
    }
    if (mins == 1) {
        mins = ', 1 min';
    } else if (mins > 1) {
        mins = ', ' + mins + ' mins';
    } else {
        mins = '';
    }

    edm.logTimeSPAN.val(days + hours + mins);
}


function showOpenShifts(divId, openShiftEvents) {
    "use strict";
    var osMkup = "", i, tnow = new Date();

    if (!divId || divId == '' || !openShiftEvents) {
        return false;
    }

    // clear contents
    $('#hhk-openshift').remove();
    $('#' + divId).html(osMkup);

    osMkup = '<h2>Open Shifts</h2><table><thead><tr><th> </th><th>Date</th><th>Time</th></tr></thead><tbody>';

    for (i = 0; i < openShiftEvents.length; i++) {

        if (openShiftEvents[i].start >= tnow) {
            osMkup += "<tr><td><input type='button' class='hhk-openshift' name='" + openShiftEvents[i].id + "' value='Sign Up' /></td>";
            osMkup += '<td>' + $.fullCalendar.formatDate(openShiftEvents[i].start, 'dddd, MMMM dd') + '</td>';
            osMkup += '<td>' + $.fullCalendar.formatDate(openShiftEvents[i].start, 'h:mm tt') + ' to ' + $.fullCalendar.formatDate(openShiftEvents[i].end, 'h:mm tt') + '</td>';
            osMkup += '</tr>';
        }
    }

    osMkup += '</tbody></table>';

    $('#' + divId).html(osMkup);
    return true;
}

function dropEvent(event, dayDelta, minuteDelta, allDay, revertFunc, dropUserId, wsAddress) {
    "use strict";
    if (wsAddress == undefined || wsAddress == '') {
        wsAddress = 'gCalFeed.php';
    }

    if (event.fix === 0 && (!event.shl || event.shl === 0)) {


        $.get(
            wsAddress + "?c=drp",
            {
                dayDelta: dayDelta,
                minuteDelta: minuteDelta,
                id: event.id,
                myid: dropUserId,
                nid: event.nid,
                allDay: allDay,
                source: event.source,
                end: event.end
            },

            function (data) {

                if (data !== null && data !== "") {

                    // Parse the returned data
                    try {
                        data = $.parseJSON(data);
                    } catch (e) {
                        alert("Receive Error: " + data.error);
                        return;
                    }

                    if (data.error) {
                        alert("Calendar Server error: " + data.error);
                        revertFunc();

                    } else if (data.warning) {
                        alert("Calendar Server warning: " + data.warning);
                        revertFunc();

                    } else {
                        flagCalAlertMessage('Appointment Moved', false);
                    }
                } else {
                    alert('Nothing was returned from the Calendar Server');
                    revertFunc();
                }
            }
        );
    } else {
        revertFunc();
    }
}

function resizeEvent(event, dayDelta, minuteDelta, revertFunc, myId, wsAddress) {
    "use strict";
    if (wsAddress == undefined || wsAddress == '') {
        wsAddress = 'gCalFeed.php';
    }


    if (event.fix === 0 && (!event.shl || event.shl === 0)) {

        $.get(
            wsAddress + "?c=rsz",
            {
                dayDelta: dayDelta,
                minuteDelta: minuteDelta,
                id: event.id,
                myid: myId,
                nid: event.nid,
                end: event.end
            },
            function (data) {

                if (data !== null && data !== "") {

                    // Parse the returned data
                    try {
                        data = $.parseJSON(data);
                    } catch (e) {
                        alert("Receive Error: " + data.error);
                        return;
                    }
                    if (data.error) {
                        alert("Calendar Server error: " + data.error);
                        revertFunc();
                    } else if (data.warning) {
                        alert("Calendar Server warning: " + data.warning);
                        revertFunc();
                    } else {
                        flagCalAlertMessage('Appointment Re-sized', false);
                    }
                } else {
                    alert('Nothing was returned from the Calendar Server');
                    revertFunc();
                }
            }
        );
    } else {
        revertFunc();
    }
}

function getCalendarList(listTable, listJSON, rangeSpan) {
    "use strict";
    listTable.fnClearTable(true);
    $.get(listJSON, {},
        function (data) {

            if (data) {
                try {
                    data = $.parseJSON(data);
                } catch (err) {
                    alert("Data Parse Error");
                    return;
                }

                if (data.error) {
                    alert('Server Error: ' + data.error);
                } else if (data.aaData) {
                    if (rangeSpan) {
                        rangeSpan.text("Showing from " + data.start + " to " + data.end);
                    }
                    listTable.fnAddData(data.aaData);
                }
            }
        }
        );

}

function listClickRow(eid, userData, catData, edMkup, listTable, listJSON, wsAddress) {
    "use strict";
    edMkup.removeClass("ui-state-error");
    edMkup.tipsP.text("").removeClass("ui-state-highlight");
    edMkup.newEvent = false;

    if (wsAddress == undefined || wsAddress == '') {
        wsAddress = 'gCalFeed.php';
    }
    $.get(
        wsAddress,
        {
            c: "getevent",
            eid: eid
        },
        function (data) {

            if (data) {
                try {
                    data = $.parseJSON(data);
                } catch (err) {
                    alert("Data Parse Error");
                    return;
                }

                if (data.error) {
                    alert('Server Error: ' + data.error);

                } else {

                    if (data.warning) {
                        alert(data.warning + ", try 'Refresh'");
                        return;
                    }

//                    if (data.nid != userData.myId && data.nid2 != userData.myId) {
//                        // not my event anymore!
//                        if (confirm("This is not your event any more.  Press OK to refresh this list.")) {
//                            getCalendarList(listTable, listJSON);
//                            return;
//                        }
//                    }
                    // convert to date objects
                    data.start = new Date(data.start);
                    data.end = new Date(data.end);
                    edMkup.evt = data;

                    var catcode, catDataItem, c;
                    catcode = data.vcc.split('|');
                    catDataItem = null;

                    if (catcode.length < 2) {
                        alert('Bad Volunteer Category.');
                        return;
                    }

                    // find the catData index
                    for (c in catData) {
                        if (catData[c] && catData[c].Vol_Category == catcode[0] && catData[c].Vol_Code == catcode[1]) {
                            catDataItem = catData[c];
                        }
                    }

                    if (catDataItem != null) {
                        clickEvent(null, userData, catDataItem, edMkup);
                    } else {
                        alert("You are not a member of this volunteer committee.");
                    }
                }
            }
        }
    );

}

function resetDialog(edMkup) {
    "use strict";
    if (edMkup) {
        edMkup.titleTB.val('');
        edMkup.edescTA.val('');
        edMkup.secondNameTB.val('');
        edMkup.secondIdTB.val('');
        //edMkup.allDayCB.prop("checked", false);

        edMkup.repeatrCB.prop("checked", false);
        edMkup.repeatrMonthDisp.hide();

        edMkup.removeClass("ui-state-error");
        edMkup.tipsP.text("").removeClass("ui-state-highlight");

    }
}

function setRepeatrMonthControls(edMkup, startDate) {
    "use strict";
    var weekDayName, weekNumer, dow, wom;
    // set up month repeater day chooser for this week day
    weekDayName = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    weekNumer = ['', '1st ', '2nd ', '3rd ', '4th '];
    dow = dayOfWeek(startDate.getDate(), (startDate.getMonth() + 1), startDate.getFullYear());

    // set up month repeater week chooser for this day on this week number
    wom = Math.ceil(startDate.getDate() / 7);
    if (wom > 4) {
        wom = 4;
    }
    edMkup.RepeatrWeekTxt.html('(Every ' + weekNumer[wom] + weekDayName[dow] + ')');
}

function calSelect(startDate, endDate, allDay, view, userData, catData, edMkup) {
    "use strict";
    var min, hour, colors;

    edMkup.txtSchTB.val('');
    //edMkup.searchSEL.children().remove();

    // for all views
    edMkup.repeatrDisp.css("display", "table-cell");
    setRepeatrMonthControls(edMkup, startDate);
    edMkup.logTimeCB.prop("checked", false);
    edMkup.logTimeDisp.hide();



    if (catData.ShowAddAll == 'y') {
        edMkup.catWideDisp.css('display', 'table-cell');
        edMkup.catWideCB.prop("checked", false);
    }else {
        edMkup.catWideDisp.css('display', 'none');
        // default - invite whole category
        edMkup.catWideCB.prop("checked", true);
    }

    if (catData.HideAddMem == 'n') {
        edMkup.partnerDisp.css('display', 'table-cell');
    } else {
        edMkup.partnerDisp.css('display', 'none');
    }

    edMkup.memIdTB.val(userData.myId);
    edMkup.memNameTB.val(userData.name);
    edMkup.secondNameTB.val('');
    edMkup.secondIdTB.val('');
    edMkup.mvccTB.val(catData.Vol_Code_Title);
    edMkup.titleTB.val('');
    edMkup.edescTA.val('');


    // do color if available
    if (catData.Colors != "") {
        colors = catData.Colors.split(',');
        edMkup.mvccTB.css("background-color", colors[0]).css("color", colors[1]);
    } else {
        edMkup.mvccTB.css("background-color", "blue").css("color", "white");
    }

    $('input.dis-me').prop("disabled", false);
    $('select.dis-me').prop("disabled", false);

    if (view.name === 'month' || view.name === 'basicWeek' || view.name === 'twoweeks') {

        edMkup.startTB.val($.fullCalendar.formatDate(startDate, 'MM/dd/yyyy'));
        edMkup.endTB.val($.fullCalendar.formatDate(endDate, 'MM/dd/yyyy'));
        edMkup.shourSEL.children(':eq(7)').prop('selected', true);
        edMkup.ehourSEL.children(':eq(7)').prop('selected', true);
        edMkup.sminSEL.children(':first-child').prop('selected', true);
        edMkup.eminSEL.children(':first-child').prop('selected', true);

    } else if (view.name === 'agendaDay' || view.name === 'agendaWeek') {

        edMkup.startTB.val($.fullCalendar.formatDate(startDate, 'MM/dd/yyyy'));
        min = $.fullCalendar.formatDate(startDate, 'mm');
        edMkup.sminSEL.children().each(function () {
            if (min - this.value >= 0 && min - this.value < 5) {
                this.selected = true;
            } else {
                this.selected = false;
            }
        });

        hour =  $.fullCalendar.formatDate(startDate, 'H');
        edMkup.shourSEL.children().each(function () {
            if (this.value == hour) {
                this.selected = true;
            } else {
                this.selected = false;
            }
        });

        edMkup.endTB.val($.fullCalendar.formatDate(endDate, 'MM/dd/yyyy'));
        min = $.fullCalendar.formatDate(endDate, 'mm');
        edMkup.eminSEL.children().each(function () {
            if (min - this.value >= 0 && min - this.value < 5) {
                this.selected = true;
            } else {
                this.selected = false;
            }
        });

        hour =  $.fullCalendar.formatDate(endDate, 'H');
        edMkup.ehourSEL.children().each(function () {
            if (this.value == hour) {
                this.selected = true;
            } else {
                this.selected = false;
            }
        });

    //        if (allDay) {
    //            edMkup.allDayCB.prop("checked", true);
    //        }
    }

    if (userData.role < 11 || catData.Vol_Rank === 'c' || catData.Vol_Rank === 'cc') {
        edMkup.searchDisp.show();
    } else {
        edMkup.searchDisp.hide();
    }

    $('#dialog').dialog("option", "buttons", edMkup.makeButtons);
    $('#dialog').dialog({
        title: 'Make Appointment'
    });
    $('#dialog').dialog('open');

    return true;
}

function clickEvent(view, userData, catData, edMkup) {
    "use strict";
    var hour, min, isChair, dialogButtons = "edit", calEvent = edMkup.evt, tim;

    edMkup.txtSchTB.val('');
    edMkup.repeatrDisp.hide();


    if (calEvent.showAddAll == 1) {
        edMkup.catWideDisp.css('display', 'table-cell');
        if (calEvent.addAll) {
            edMkup.catWideCB.prop('checked', true);
        }
    }else {
        edMkup.catWideDisp.css('display', 'none');
    }

    if (calEvent.hideAddMem == 0) {
        edMkup.partnerDisp.css('display', 'table-cell');
        edMkup.catWideCB.prop('checked', false);
    } else {
        edMkup.partnerDisp.css('display', 'none');
        edMkup.catWideCB.prop('checked', true);
    }

    if (userData.role < 11 || catData.Vol_Rank === 'c' || catData.Vol_Rank === 'cc') {
        isChair = true;
    } else {
        isChair = false;
    }

    // set up date selectors
    edMkup.startTB.val($.fullCalendar.formatDate(calEvent.start, 'MM/dd/yyyy'));
    $("#" + edMkup.startTB.attr("id")).datepicker("option", "maxDate", calEvent.end);
    edMkup.endTB.val($.fullCalendar.formatDate(calEvent.end, 'MM/dd/yyyy'));
    $("#" + edMkup.endTB.attr("id")).datepicker("option", "minDate", calEvent.start);

    min = $.fullCalendar.formatDate(calEvent.start, 'mm');
    edMkup.sminSEL.children().each(function () {
        if (min - this.value >= 0 && min - this.value < 5) {
            this.selected = true;
        } else {
            this.selected = false;
        }
    });

    hour =  $.fullCalendar.formatDate(calEvent.start, 'H');
    edMkup.shourSEL.children().each(function () {
        if (this.value == hour) {
            this.selected = true;
        } else {
            this.selected = false;
        }
    });

    min = $.fullCalendar.formatDate(calEvent.end, 'mm');
    edMkup.eminSEL.children().each(function () {
        if (min - this.value >= 0 && min - this.value < 5) {
            this.selected = true;
        } else {
            this.selected = false;
        }
    });

    hour =  $.fullCalendar.formatDate(calEvent.end, 'H');
    edMkup.ehourSEL.children().each(function () {
        if (this.value == hour) {
            this.selected = true;
        } else {
            this.selected = false;
        }
    });

    edMkup.edescTA.val(calEvent.desc);
    edMkup.newEvent = false;
    updateDuration(edMkup);


    if (calEvent.shl === 1) {
        // Shell event special handling -  use my data instead of event data
        edMkup.memIdTB.val(userData.myId);
        edMkup.memNameTB.val(userData.name);
        calEvent.mName = userData.name;
        edMkup.titleTB.val(userData.name);
        edMkup.secondNameTB.val('');
        edMkup.secondIdTB.val('');
        dialogButtons = "make";
        // Select the "week" units on the repeater control, remove the Month option"
        edMkup.repeatrDisp.show();
        setRepeatrMonthControls(edMkup, calEvent.start);
        edMkup.logTimeDisp.hide();

    } else {
        // Regular event
        edMkup.titleTB.val(calEvent.title);
        edMkup.eid = calEvent.id;
        edMkup.memIdTB.val(calEvent.nid);
        edMkup.memNameTB.val(calEvent.mName);
        edMkup.secondNameTB.val(calEvent.mName2);
        edMkup.secondIdTB.val(calEvent.nid2);
        edMkup.repeatrDisp.hide();

        tim = new Date();
        if (calEvent.end < tim) {
            edMkup.logTimeDisp.show();
        } else {
            edMkup.logTimeDisp.hide();
        }

    }

    if ((!isChair && userData.myId !== edMkup.memIdTB.val() && userData.myId !== edMkup.secondIdTB.val()) || calEvent.lkd) {
        // Not my Event.
        $('.dis-me').prop("disabled", true);
        //$('select.dis-me').prop("disabled", "disabled");
        dialogButtons = "view";

    } else {
        $('.dis-me').prop("disabled", false);
       // $('select.dis-me').prop("disabled", false);

    }


    if (calEvent.vdesc) {
        edMkup.mvccTB.val(calEvent.vdesc);
    }

    if (calEvent.backgroundColor != "" || calEvent.textColor != "") {
        edMkup.mvccTB.css("background-color", calEvent.backgroundColor).css("color", calEvent.textColor);
    } else {
        edMkup.mvccTB.css("background-color", "blue").css("color", "white");
    }

    // Uncleck the repeat checkbox
    edMkup.repeatrCB.prop('checked', false);
    $('.repeater-disable').prop("disabled", true);


    // show member search box if appropriate
    if (isChair) {
        edMkup.searchDisp.show();
    } else {
        edMkup.searchDisp.hide();
    }

    // set log time CB
    if (calEvent.timelogged) {
        edMkup.logTimeCB.prop("checked", true);
    } else {
        edMkup.logTimeCB.prop("checked", false);
    }

    // if the shell id is set, then we cannot allow date changes
    if (calEvent.shlid > 0) {
        edMkup.startTB.prop('disabled', true);
        edMkup.endTB.prop('disabled', true);
    }

    //    if (calEvent.allDay) {
    //        edMkup.allDayCB.attr("checked", true);
    //    } else {
    //        edMkup.allDayCB.attr("checked", false);
    //    }

    //    if (calEvent.fix) {
    //        $('#eTmFixed').attr("checked", true);
    //    } else {
    //        $('#eTmFixed').attr("checked", false);
    //    }
    //
    //    if (calEvent.lkd) {
    //        $('#eLocked').attr("checked", true);
    //    } else {
    //        $('#eLocked').attr("checked", false);
    //    }

    if (dialogButtons == "make") {
        $('#dialog').dialog("option", "buttons", edMkup.makeButtons);
        $('#dialog').dialog({
            title: 'Make Appointment'
        });
    } else if (dialogButtons == "view") {
        $('#dialog').dialog("option", "buttons", edMkup.viewButtons);
        $('#dialog').dialog({
            title: 'View Appointment'
        });
    } else {
        $('#dialog').dialog("option", "buttons", edMkup.editButtons);
        $('#dialog').dialog({
            title: 'Update Appointment'
        });
    }

    $('#dialog').dialog('open');

    return true;
}

// Search for names, place any found into the appropiriate selector
function getNames(ctrl, slectr, code, lid, fltr) {
    "use strict";
    if (ctrl && ctrl.val() !== "") {
        var inpt = {
            cmd: "filter",
            letters: ctrl.val(),
            basis: code,
            id: lid,
            filter: fltr
        };



        $.get("VolNameSearch.php",
            inpt,
            function (data) {

                if (data) {
                    var names, sel, optText, x, evt, firstLast, fls;
                    // Parse the returned data
                    try {
                        names = $.parseJSON(data);
                    } catch (e) {
                        alert("Receive Error: " + data.error);
                        return;
                    }

                    if (names && names.length > 0) {
                        if (names[0].error) {
                            alert("Server error: " + names[0].error);
                        //ctrl.val('');
                        } else {
                            sel = $('#' + slectr);
                            sel.children().remove();
                            //ctrl.val('');
                            optText = '';
                            if (names[0].id !== 0) {
                                if (names.length === 1) {
                                    optText = "<option value=''>Retrieved " + names.length + " Name</option>";
                                } else {
                                    optText = "<option value=''>Retrieved " + names.length + " Names</option>";
                                }

                                sel.append(optText);
                            }
                            for (x = 0; x < names.length; x++) {
                                evt = names[x];
                                if (evt.name) {
                                    fls = evt.name.split(',');
                                    if (fls.length > 1) {
                                        firstLast = fls[1] + ' ' + fls[0];
                                    } else {
                                        firstLast = evt.name;
                                    }
                                    optText = "<option value='" + evt.id + "'>" + firstLast + "</option>";
                                    sel.append(optText);
                                }
                            }
                        }
                    }
                } else {
                    alert('Nothing was returned from the server');
                }
            });
    }
}

function doCalDelete(eid, delall, justme, sendemail, myId, wsAddress) {
    "use strict";
    if (wsAddress == undefined || wsAddress == '') {
        wsAddress = 'gCalFeed.php';
    }

    $.get(
        wsAddress,
        {
            c: "del",
            id: eid,
            delall: delall,
            justme: justme,
            sendemail: sendemail,
            myid: myId
        },
        function (data) {

            if (data !== null && data !== "") {

                // Parse the returned data
                try {
                    data = $.parseJSON(data);
                } catch (e) {
                    alert("Receive Error: " + data.error);
                    return;
                }

                if (data.error) {
                    alert(data.error);
                } else if (data.success == 'y' && (!data.justme || data.justme == 0)) {
                    $('#calendar').fullCalendar('refetchEvents');
                    if (data.rptid && data.rptid > 0 && data.num > 1) {
//                        // remove events from the calendar list
//                        $('#calendar').fullCalendar('removeEvents', function (event) {
//                            if (event.rptid && event.rptid == data.rptid) {
//                                return true;
//                            }
//                            return false;
//                        });
                        flagCalAlertMessage(data.num + ' Appointments Deleted', false);
                    } else if(data.num === 1) {
                        //$('#calendar').fullCalendar('removeEvents', eid);
                        flagCalAlertMessage(data.num + ' Appointment Deleted', false);
                    } else if (data.num === 0) {
                        flagCalAlertMessage('Nothing Deleted', false);
                    }
                } else if (data.justme && data.justme == 1) {

                    flagCalAlertMessage('Partner removed from ' + data.num + ' appointment(s).', false);
                }
            } else {
                alert('Nothing was returned from the Calendar Server');
            }
        }
    );
}

function doDialogSave(userData, catData, edMkup, wsAddress) {
    "use strict";
    var bValid = true, sthnum, edhnum, stmnum, edmnum, smn = "", shr = "", ehr = "", emn = "", edt = "", sdt = "",
        evtDate, stText, endText, tm = '', parms, c, shlId, relievableFlag, fixedFlag, lockedFlag,
        rptr = 0, rptrUnit, rptrQty, saveEvent = edMkup.evt, startDate, endDate;

    bValid = bValid && checkLength(edMkup.titleTB, "Title", 1, 45, edMkup.tipsP);
    bValid = bValid && checkLength(edMkup.edescTA, "Notes", 0, 200, edMkup.tipsP);

    if (bValid === false) {
        return false;
    }

    if (saveEvent && saveEvent.lkd) {
        alert("This Event is locked.  It cannot be saved.");
        return true;
    }

    // Start date - must be valid
    sdt = edMkup.startTB.val();
    if (sdt === null || sdt === "") {
        updateTips('The Start Date is missing', edMkup.tipsP, edMkup.startTB);
        return false;
    }

    try {
        startDate = $.datepicker.parseDate('mm/dd/yy', sdt);
    } catch (err) {
        updateTips('The Start Date is somehow wrong', edMkup.tipsP, edMkup.startTB);
        return false;
    }

    // end date
    edt = edMkup.endTB.val();
    if (edt == "") {
        // Set end date to start date
        edt = sdt;
        edMkup.endTB.val(sdt);
    } else {
        try {
            endDate = $.datepicker.parseDate('mm/dd/yy', edt);
        } catch (er) {
            updateTips('The End Date is somehow wrong', edMkup.tipsP, edMkup.endTB);
            return false;
        }
    }

    // Start Date Earlier than end date?
    if (startDate.getTime() > endDate.getTime()) {
        updateTips('The Start Date must be earlier than the End Date', edMkup.tipsP, edMkup.endTB);
        return false;
    }


    // start hour
    if (edMkup.shourSEL.val() == '') {
        updateTips('The Start hour is missing', edMkup.tipsP, edMkup.shourSEL);
        return false;
    }

    sthnum = parseInt(edMkup.shourSEL.val(), 10);
    sthnum = sthnum % 24;

    // End hour
    if (edMkup.ehourSEL.val() == "") {
        updateTips('The End hour is missing', edMkup.tipsP, edMkup.ehourSEL);
        return false;
    } else {
        edhnum = parseInt(edMkup.ehourSEL.val(), 10);
        edhnum = edhnum % 24;
    }

    if (sthnum > edhnum && sdt == edt) {
        updateTips('The Start Hour must be the same or earlier than the End Hour', edMkup.tipsP);
        return false;
    }

    shr = String(sthnum);
    ehr = String(edhnum);

    // Start minutes
    stmnum = parseInt(edMkup.sminSEL.val(), 10);
    stmnum = stmnum % 60;

    // End Minutes
    edmnum = parseInt(edMkup.eminSEL.val(), 10);
    edmnum = edmnum % 60;


    if (stmnum >= edmnum  && sthnum == edhnum && sdt == edt) {
        updateTips('The End Minutes must be later than the Start Minutes', edMkup.tipsP);
        return false;
    }


    smn = String(stmnum);
    smn = cleanMS(smn) + ':00';

    emn = String(edmnum);
    emn = cleanMS(emn) + ':00';

    shr = cleanMS(shr) + ':';
    ehr = cleanMS(ehr) + ':';



    if (wsAddress == undefined || wsAddress == '') {
        wsAddress = 'gCalFeed.php';
    }

    if (bValid) {

        evtDate = new Date(sdt + ' ' + shr + smn);
        stText = sdt + ' ' + shr + smn + calculate_time_offset(evtDate);

        if (edt == "") {
            endText = "";
        } else {
            evtDate = new Date(edt + ' ' + ehr + emn);
            endText = edt + ' ' + ehr + emn + calculate_time_offset(evtDate);
        }

        // Event Repeater set?
        if (edMkup.repeatrCB.prop('checked')) {

            if (!confirm("Do you want to repeat this assignment?")) {
                edMkup.repeatrCB.prop('checked', false);
                return false;
            }

            rptr = 1;
            rptrUnit = edMkup.repeatrUnitsSEL.val();
            rptrQty = parseInt(edMkup.repeatrUnitQtySEL.val(), 10);
        //            rptrDay = parseInt(edMkup.repeatrDaySEL.val());
        //            rptrWeek = parseInt(edMkup.RepeatrWeekSEL.val());
        //            if (rptrDay > 0) {
        //                rptrDay--;  // PHP likes it 0-based
        //            }

        } else {
            rptr = 0;
        }

        var catWide = 0;
        if (edMkup.catWideCB.prop('checked')) {
            catWide = 1;
        }

        //        if (edMkup.allDayCB.prop('checked')) {
        //            allDayFlag = 1;
        //        } else {
        //            allDayFlag = 0;
        //        }

        //        if ($('#eMakeAvailable').prop('checked')) {
        //            relievableFlag = 1;
        //        } else {
        //            relievableFlag = 0;
        //        }
        //
        //        if ($('#eTmFixed').prop('checked')) {
        //            fixedFlag = 1;
        //        } else {
        //            fixedFlag = 0;
        //        }
        //
        //        if ($('#eLocked').prop('checked')) {
        //            lockedFlag = 1;
        //        } else {
        //            lockedFlag = 0;
        //        }

        // New or used event?
        if (edMkup.newEvent !== false) {
            // New event: render event locally

            parms = {
                title: edMkup.titleTB.val(),
                start: stText,
                end: endText,

                rlf: relievableFlag,
                fix: fixedFlag,
                lkd: lockedFlag,
                myid: userData.myId,
                rptr: rptr,
                addAll: catWide,
                vcc: get_vcc(catData)
            };

            if (rptr == 1) {
                parms.rptrUnit = rptrUnit;
                parms.rptrQty = rptrQty;
            //                parms.rptrDay = rptrDay;
            //                parms.rptrWeek = rptrWeek;
            }

            if (edMkup.edescTA.val() !== "") {
                parms.desc = edMkup.edescTA.val();
            }

            if (edMkup.memIdTB.val() !== "") {
                parms.nid = edMkup.memIdTB.val();
            }

            if (edMkup.secondIdTB.val() !== "" && edMkup.secondNameTB.val() !== "") {
                parms.nid2 = edMkup.secondIdTB.val();
            }

            // Send event to the database
            $.get(
                wsAddress + "?c=new",
                parms,
                function (data) {
                    // Nothing returned?
                    if (data === null || data == "") {

                        alert('Nothing was returned from the Calendar Server');
                        return;
                    }

                    // Parse the returned data
                    try {
                        data = $.parseJSON(data);
                    } catch (e) {
                        alert("Receive Error: " + data.error);
                        return;
                    }

                    // Server report an error?
                    if (data.error) {
                        alert("Calender Server error: " + data.error);
                        return;
                    }

                    if (data.success) {

                        if (data.event) {
                            // single event saved.
                            $('#calendar').fullCalendar('renderEvent', data.event);
                            flagCalAlertMessage('Appointment Saved', false);
                        }

                        if (data.repeatmsg) {
                            // multiple events saved
                            var rmsg = data.repeatmsg;

                            if (rmsg.shlid > 0) {
                                // display shell repeat results
                                $('#spnRetMessage').html(data.success);
                                $('#spnRetNew').html(rmsg.enew);
                                $('#spnRetLost').html(rmsg.removed);
                                $('#spnRetMine').html(rmsg.mine);
                                $('#spnRetReplaced').html(rmsg.taken);
                                $('#repeatReturn').dialog('open');
                            }

                            $('#calendar').fullCalendar('refetchEvents');
                            flagCalAlertMessage(rmsg.enew + ' Appointments Saved', false);

                        }

                        resetDialog(edMkup);
                    }
                }
            );
        } else {
            c = '';
            shlId = edMkup.evt.shlid;
            if (edMkup.evt.shl) {
                c = 'new';

            } else {
                c = 'upd';
                tm = edMkup.evt.id;
            }

            parms = {
                title: edMkup.titleTB.val(),
                start: stText,
                end: endText,
                id: tm,

                rlf: relievableFlag,
                fix: fixedFlag,
                lkd: lockedFlag,
                shlid: shlId,
                myid: userData.myId,
                rptr: rptr,
                addAll: catWide,
                vcc: edMkup.evt.vcc,
                desc: edMkup.edescTA.val()
            };

            if (edMkup.memIdTB.val() !== "") {
                parms.nid = edMkup.memIdTB.val();
            }

            if (edMkup.secondIdTB.val() !== "" && edMkup.secondNameTB.val() !== "") {
                parms.nid2 = edMkup.secondIdTB.val();
            }

            if (rptr == 1) {
                parms.rptrUnit = rptrUnit;
                parms.rptrQty = rptrQty;
            //                parms.rptrDay = rptrDay;
            //                parms.rptrWeek = rptrWeek;
            }

            if (edMkup.logTimeCB.prop('checked')) {
                parms.logtime = 1;
            } else {
                parms.logtime = 0;
            }


            // Send event to the database
            $.get(
                wsAddress + "?c=" + c,
                parms,
                function (data) {

                    // Nothing returned?
                    if (data === null || data == "") {
                        //var ev = data.replace("[", "").replace("]", "");
                        alert('Nothing was returned from the Calendar Server');
                        return;
                    }

                    // Parse the returned data
                    try {
                        data = $.parseJSON(data);
                    } catch (e) {
                        alert("Receive Error: " + data.error);
                        return;
                    }

                    // Server report an error?
                    if (data.error) {
                        alert("Calender Server error: " + data.error);
                        return;
                    }

                    // Server report Success?
                    if (data.success) {

                        if (data.event) {
                            // An event was returned.  Update the calendar
                            var revt = data.event;

                            if (revt.className) {
                                saveEvent.className = revt.className;
                            } else {
                                saveEvent.className = '';
                            }

                            if (revt.title) {
                                saveEvent.title = revt.title;
                            }
                            if (revt.start) {
                                saveEvent.start = revt.start;
                            }
                            if (revt.end) {
                                saveEvent.end = revt.end;
                            }
                            //                            if (data.allDay) {
                            //                                saveEvent.allDay = data.allDay;
                            //                            }
                            //                        if (revt.rlf) {
                            //                            saveEvent.rlf = revt.rlf;
                            //                        }
                            //                        if (revt.fix) {
                            //                            saveEvent.fix = revt.fix;
                            //                        }
                            //                        if (revt.lkd) {
                            //                            saveEvent.lkd = revt.lkd;
                            //                        }
                            if (revt.myid) {
                                saveEvent.myid = revt.myid;
                            }
                            if (revt.vcc) {
                                saveEvent.vcc = revt.vcc;
                            }
                            if (revt.color) {
                                saveEvent.color = revt.color;
                            }
                            if (revt.backgroundColor) {
                                saveEvent.backgroundColor = revt.backgroundColor;
                            }
                            if (revt.borderColor) {
                                saveEvent.borderColor = revt.borderColor;
                            }
                            if (revt.textColor) {
                                saveEvent.textColor = revt.textColor;
                            }
                            if (revt.vdesc) {
                                saveEvent.vdesc = revt.vdesc;
                            }
                            if (revt.mName) {
                                saveEvent.mName = revt.mName;
                            }
                            if (revt.nid) {
                                saveEvent.nid = revt.nid;
                            }
                            if (revt.mName2) {
                                saveEvent.mName2 = revt.mName2;
                            } else {
                                saveEvent.mName2 = '';
                            }
                            if (revt.nid2) {
                                saveEvent.nid2 = revt.nid2;
                            } else {
                                saveEvent.nid2 = '';
                            }
                            if (revt.desc) {
                                saveEvent.desc = revt.desc;
                            } else {
                                saveEvent.desc = '';
                            }
                            if (revt.shlid) {
                                saveEvent.shlid = revt.shlid;
                            } else {
                                saveEvent.shlid = 0;
                            }
                            if (revt.rptid) {
                                saveEvent.rptid = revt.rptid;
                            } else {
                                saveEvent.rptid = 0;
                            }

                            if (revt.addAll) {
                                saveEvent.addAll = revt.addAll;
                            } else {
                                saveEvent.addAll = 0;
                            }

                            if (revt.timelogged) {
                                saveEvent.timelogged = revt.timelogged;
                            } else {
                                saveEvent.timelogged = 0;
                            }

                            if (saveEvent.shl == 1) {
                                var ele = $('input.hhk-openshift[name=' + saveEvent.id + ']');
                                ele.val('Update');
                            }

                            if (revt.id) {
                                saveEvent.id = revt.id;
                            }
                            saveEvent.shl = 0;

                            $('#calendar').fullCalendar('updateEvent', saveEvent);
                            flagCalAlertMessage(data.success, false);
                            $('#calendar').fullCalendar('refetchEvents');

                        }

                        if (data.repeatmsg) {
                            // multiple events saved
                            var rmsg = data.repeatmsg;

                            if (rmsg.shlid > 0) {
                                // display shell repeat results
                                $('#spnRetMessage').html(data.success);
                                $('#spnRetNew').html(rmsg.enew);
                                $('#spnRetLost').html(rmsg.removed);
                                $('#spnRetMine').html(rmsg.mine);
                                $('#spnRetReplaced').html(rmsg.taken);
                                $('#repeatReturn').dialog('open');
                            }

                            $('#calendar').fullCalendar('refetchEvents');
                            flagCalAlertMessage(rmsg.enew + ' Appointments Saved', false);

                        }


                        resetDialog(edMkup);
                    }

                }
            );
        }
        return true;
    }
    return false;
}

