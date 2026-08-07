/**
 * hospitalStay.js
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 */

import { verifyDocAgent } from "../common/addrPrefs";

export function viewHospitalStay(idHs, idVisit, $hsDialog) {
  $.post("ws_resv.php", { cmd: "viewHS", idhs: idHs }, function (data) {
    if (!data) {
      alert("Bad Reply from Server");
      return;
    }

    try {
      data = JSON.parse(data);
    } catch {
      alert("Bad JSON Encoding");
      return;
    }

    if (data.error) {
      if (data.gotopage) {
        window.open(data.gotopage, "_self");
      }
      flagAlertMessage(data.error, "error");
      return;
    } else if (data.success) {
      $hsDialog.empty();
      $hsDialog.append($(data.success));
      $hsDialog.dialog({
        autoOpen: true,
        width: getDialogWidth(1050),
        resizable: true,
        modal: true,
        title: data.title ? data.title : "Hospital Details",
        buttons: {
          Cancel: function () {
            $(".ckhsdate").datepicker("hide");
            $(this).dialog("close");
          },
          Save: function () {
            //verify doc and agent
            const agentValid = verifyDocAgent("agentInfo");
            const docValid = verifyDocAgent("docInfo");
            if (agentValid === false || docValid === false) {
              flagAlertMessage(
                "Some or all of the indicated Hospital Information is missing",
                "error",
              );
              return false;
            }

            $(".ckhsdate").datepicker("hide");
            saveHospitalStay(idHs, idVisit);
            $(this).dialog("close");
          },
        },
      });

      // add closer to visit dialog box
      if ($("#keysfees").length > 0) {
        $("#keysfees").on("dialogclose", function (_event, _ui) {
          // Close hospital stay dialog
          if ($hsDialog.dialog("isOpen")) {
            $hsDialog.dialog("close");
            $(".ckhsdate").datepicker("hide");
          }
        });
      }

      //Autocompletes for agent and doctor
      createAutoComplete(
        $(".hhk-hsdialog #txtAgentSch"),
        3,
        { cmd: "filter", add: "phone", basis: "ra" },
        getAgent,
      );
      if ($(".hhk-hsdialog #a_txtLastName").val() === "") {
        $(".hhk-hsdialog .hhk-agentInfo").hide();
      }

      $(document).on("click", "#a_delete", function () {
        $(".hhk-hsdialog #a_idName").val("");
        $(".hhk-hsdialog input.hhk-agentInfo").val("");
        $(".hhk-hsdialog .hhk-agentInfo").hide();
      });

      if ($(".hhk-hsdialog #a_idName").val() !== "") {
        $(".hhk-hsdialog input.hhk-agentInfo.name").attr("readonly", "readonly");
      } else {
        $(".hhk-hsdialog input.hhk-agentInfo.name").removeAttr("readonly");
      }

      createAutoComplete($(".hhk-hsdialog #txtDocSch"), 3, { cmd: "filter", basis: "doc" }, getDoc);
      if ($(".hhk-hsdialog #d_txtLastName").val() === "") {
        $(".hhk-hsdialog .hhk-docInfo").hide();
      }

      if ($(".hhk-hsdialog #d_idName").val() !== "") {
        $(".hhk-hsdialog input.hhk-docInfo.name").attr("readonly", "readonly");
      } else {
        $(".hhk-hsdialog input.hhk-docInfo.name").removeAttr("readonly");
      }

      $(document).on("click", "#d_delete", function () {
        $(".hhk-hsdialog #d_idName").val("");
        $(".hhk-hsdialog input.hhk-docInfo").val("");
        $(".hhk-hsdialog .hhk-docInfo").hide();
      });

      // Diagnosis Search
      let diagSelect = function (item) {
        if (item.id !== "n") {
          $("#selDiagnosis").val(item.id);
          $("#selectedDiag").text(item.label).closest("tr").removeClass("d-none");
        }
      };
      createAutoComplete($("#diagSearch"), 3, { cmd: "diagnosis" }, diagSelect, false);

      //Diagnosis delete button
      $(document).on("click", "#delDiagnosis", function (_e) {
        $("#selDiagnosis").val("");
        $("#diagSearch").val("");
        $(this).closest("tr").addClass("d-none");
      });

      // Calendars for treatment start and end dates
      $(".ckhsdate").datepicker({
        yearRange: "-01:+01",
        changeMonth: true,
        changeYear: true,
        autoSize: true,
        numberOfMonths: 1,
        dateFormat: "M d, yy",
        showButtonPanel: true,
        beforeShow: function (input) {
          setTimeout(function () {
            var buttonPane = $(input).datepicker("widget").find(".ui-datepicker-buttonpane");

            buttonPane.empty();

            $("<button>", {
              text: "Clear",
              click: function () {
                //Code to clear your date field (text box, read only field etc.) I had to remove the line below and add custom code here
                $.datepicker._clearDate(input);
              },
            })
              .appendTo(buttonPane)
              .addClass("ui-datepicker-clear ui-state-default ui-priority-primary ui-corner-all");
          }, 1);
        },
        onChangeMonthYear: function (year, month, instance) {
          setTimeout(function () {
            var buttonPane = $(instance).datepicker("widget").find(".ui-datepicker-buttonpane");
            buttonPane.empty();
            $("<button>", {
              text: "Clear",
              click: function () {
                //Code to clear your date field (text box, read only field etc.) I had to remove the line below and add custom code here
                $.datepicker._clearDate(instance.input);
              },
            })
              .appendTo(buttonPane)
              .addClass("ui-datepicker-clear ui-state-default ui-priority-primary ui-corner-all");
          }, 1);
        },
      });
    }
  });
}

function saveHospitalStay(idHs, idVisit) {
  var parms = [
    { name: "cmd", value: "saveHS" },
    { name: "idhs", value: idHs },
    { name: "idv", value: idVisit },
  ];
  var parms = parms.concat($(".hospital-stay:not(#txtDiagnosis)").serializeArray());

  //diagnosis
  let txtDiagnosis = $("#txtDiagnosis").val();
  if (typeof txtDiagnosis == "string") {
    txtDiagnosis = buffer.Buffer.from(txtDiagnosis).toString("base64");
  }
  parms.push({ name: "txtDiagnosis", value: txtDiagnosis });

  $.post("ws_resv.php", parms, function (data) {
    if (!data) {
      alert("Bad Reply from Server");
      return;
    }

    try {
      data = JSON.parse(data);
    } catch {
      alert("Bad JSON Encoding");
      return;
    }

    if (data.error) {
      if (data.gotopage) {
        window.open(data.gotopage, "_self");
      }
      flagAlertMessage(data.error, "error");
      return;
    } else if (data.success) {
      flagAlertMessage(data.success, "success");
      if (data.newHsId && data.newHsId > 0) {
        $(".hhk-hospitalstay").each(function () {
          $(this).data("idhs", data.newHsId);
        });
      }
    }
  });
}
