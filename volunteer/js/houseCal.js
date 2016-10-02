/**
 * houseCal.js
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
    var osMkup = "", i;

    if (!divId || divId == '' || !openShiftEvents) {
        return false;
    }

    // clear contents
    $('#hhk-openshift').remove();
    $('#' + divId).html(osMkup);

    osMkup = '<h2>Open Shifts</h2><table><thead><tr><th> </th><th>Date</th><th>Time</th></tr></thead><tbody>';

    for (i = 0; i < openShiftEvents.length; i++) {

        osMkup += "<tr><td><input type='button' class='hhk-openshift' name='" + openShiftEvents[i].id + "' value='Sign Up' /></td>";
        osMkup += '<td>' + $.fullCalendar.formatDate(openShiftEvents[i].start, 'dddd, MMMM dd') + '</td>';
        osMkup += '<td>' + $.fullCalendar.formatDate(openShiftEvents[i].start, 'h:mm tt') + ' to ' + $.fullCalendar.formatDate(openShiftEvents[i].end, 'h:mm tt') + '</td>';
        osMkup += '</tr>';
    }

    osMkup += '</tbody></table>';

    $('#' + divId).html(osMkup);
    return true;
}

function resetDialog(edMkup) {
    "use strict";
    if (edMkup) {
        edMkup.titleTB.val('');
        edMkup.edescTA.val('');
        edMkup.secondNameTB.val('');
        edMkup.secondIdTB.val('');
        //edMkup.allDayCB.attr("checked", false);

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

//    if (calEvent.hideAddMem == 0) {
        edMkup.partnerDisp.css('display', 'table-cell');
//        edMkup.catWideCB.attr('checked', false);
//    } else {
//        edMkup.partnerDisp.css('display', 'none');
        edMkup.catWideCB.prop('checked', true);
//    }

        isChair = false;


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
        //$('select.dis-me').attr("disabled", "disabled");
        dialogButtons = "view";

    } else {
        $('.dis-me').prop("disabled", false);
       // $('select.dis-me').attr("disabled", false);

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



function doCalDelete(eid, delall, justme, sendemail, myId, wsAddress) {
    "use strict";
    if (wsAddress == undefined || wsAddress == '') {
        wsAddress = 'HouseCal.php';
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
                    if (data.rptid && data.rptid > 0 && data.num > 1) {
                        // remove events from the calendar list
                        $('#calendar').fullCalendar('refetchEvents');
                        flagCalAlertMessage(data.num + ' Appointments Deleted', false);
                    } else if(data.num === 1) {
                        $('#calendar').fullCalendar('refetchEvents');
                        flagCalAlertMessage(data.num + ' Appointment Deleted', false);
                    } else if (data.num === 0) {
                        flagCalAlertMessage('Nothing Deleted', false);
                    }
                } else if (data.success == 'y' && data.justme && data.justme == 1) {
                    $('#calendar').fullCalendar('refetchEvents');
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
        wsAddress = 'HouseCal.php';
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

