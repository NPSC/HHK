import { createAutoComplete } from "../common/createAutoComplete.js";

function dispVehicle(item) {
  if (item.id > 0) {
    let $tr = $("<tr />");

    $tr
      .append($("<td>" + item.License_Number + "</td>"))
      .append($("<td>" + item.Make + "</td>"))
      .append($("<td>" + item.Model + "</td>"))
      .append($("<td>" + item.Color + "</td>"))
      .append($("<td>" + item.State_Reg + "</td>"))
      .append($('<td><a href="GuestEdit.php?id=' + item.id + '">' + item.Patient + "</a></td>"))
      .append($("<td>" + item.Room + "</td>"));

    $("#tbl").append($tr);
  }
}

document.addEventListener("DOMContentLoaded", () => {
  "use strict";

  $.widget("ui.autocomplete", $.ui.autocomplete, {
    _resizeMenu: function () {
      let ul = this.menu.element;
      ul.outerWidth(
        Math.max(
          // Firefox wraps long text (possibly a rounding bug)
          // so we add 1px to avoid the wrapping (#7513)
          ul.width("").outerWidth() + 1,
          this.element.outerWidth(),
        ) * 1.1,
      );
    },
  });

  createAutoComplete($("#schTag"), 3, { cmd: "vehsch" }, dispVehicle, false, "ws_resc.php");
});
