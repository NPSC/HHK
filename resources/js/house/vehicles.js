/**
 * vehicles.js
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 */

export function setupVehicle(veh) {
  let nextVehId = 2;
  let $cbVeh = veh.find("#cbNoVehicle");
  let $nextVeh = veh.find("#btnNextVeh");
  let $tblVeh = veh.find("#tblVehicle");

  $cbVeh.change(function () {
    if (this.checked) {
      $tblVeh.hide("scale, horizontal");
    } else {
      $tblVeh.show("scale, horizontal");
    }
  });

  $cbVeh.change();
  $nextVeh.button();

  $nextVeh.click(function () {
    veh.find("#trVeh" + nextVehId).show("fade");
    nextVehId++;
    if (nextVehId > 4) {
      $nextVeh.hide("fade");
    }
  });
}

export function viewVehicleDialog(idVisit, $vehDialog) {
  $.post(
    "ws_resv.php",
    { cmd: "viewVeh", idV: idVisit },
    function (data) {
      if (data.error) {
        if (data.gotopage) {
          window.open(data.gotopage, "_self");
        }
        flagAlertMessage(data.error, "error");
        return;
      } else if (data.success) {
        $vehDialog.empty();
        $vehDialog.append($(data.success));

        setupVehicle($vehDialog);

        $vehDialog.dialog({
          autoOpen: true,
          width: getDialogWidth(900),
          resizable: true,
          modal: true,
          title: data.title ? data.title : "Vehicle Details",
          buttons: {
            Cancel: function () {
              $(this).dialog("close");
            },
            Save: function () {
              saveVehicles(idVisit, $vehDialog);
              $(this).dialog("close");
            },
          },
          close: function () {
            $(this).dialog("destroy").empty();
          },
        });
      }
    },
    "json",
  );
}

function saveVehicles(idVisit, $vehDialog) {
  let params = $vehDialog
    .find("#tblVehicle input, #tblVehicle select, #cbNoVehicle")
    .serializeArray();
  params.push({ name: "cmd", value: "saveVeh" });
  params.push({ name: "idV", value: idVisit });
  $.post(
    "ws_resv.php",
    params,
    function (data) {
      if (data.error) {
        if (data.gotopage) {
          window.open(data.gotopage, "_self");
        }
        flagAlertMessage(data.error, "error");
        return;
      } else if (data.success) {
        flagAlertMessage(data.success, "success");
      }
    },
    "json",
  );
}
