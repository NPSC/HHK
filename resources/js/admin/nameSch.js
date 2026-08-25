import { createAutoComplete } from "../common/createAutoComplete.js";
import { isNumber } from "../admin/genfunc.js";

document.addEventListener("DOMContentLoaded", () => {
  $.widget("ui.autocomplete", $.ui.autocomplete, {
    _resizeMenu: function () {
      let ul = this.menu.element;
      ul.outerWidth(Math.max(ul.width("").outerWidth() + 1, this.element.outerWidth()) * 1.1);
    },
  });

  $("#historyTabs").tabs();

  createAutoComplete(
    $("#txtsearch"),
    3,
    { cmd: "srrel", id: 0 },
    function (item) {
      if (item.id === "i") {
        // New Individual
        window.location = "NameEdit.php?cmd=newind";
      } else if (item.id === "o") {
        window.location = "NameEdit.php?cmd=neworg";
      }

      let cid = parseInt(item.id, 10);
      if (isNumber(cid)) {
        window.location = "NameEdit.php?id=" + cid;
      }
    },
    false,
    "liveNameSearch.php",
    $("#txtBasis"),
  );

  $('input[name="msearch"]').click(function () {
    if ($("#rbmemName").prop("checked")) {
      $("#txtBasis").val("m");
    } else {
      $("#txtBasis").val("e");
    }
  });

  $("#txtsearch").keypress(function (event) {
    var mm = $(this).val();
    if (event.keyCode == "13") {
      event.preventDefault();
      if (mm == "" || !isNumber(parseInt(mm, 10))) {
        alert("Don't press the return key unless you enter an Id.");
      } else {
        window.location = "NameEdit.php?id=" + mm;
      }
    }
  });
  $(".fc-icon-wrap").append("\u00A0"); //fix short icon buttons
  $("#historyTabs").show();
});
