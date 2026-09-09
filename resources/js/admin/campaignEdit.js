import { updateTips } from "./genfunc.js";

const FIELD_INPUT_PREFIX = {
  title: "campTitle",
  type: "campType",
  sdate: "campStart",
  edate: "campEnd",
  min: "campMin",
  max: "campMax",
  target: "campTarget",
  percent: "campPercent",
  status: "campStatus",
  cat: "campCat",
  mergeCode: "campMergeCode",
  desc: "campDesc",
};

document.addEventListener("DOMContentLoaded", () => {
  "use strict";

  var $table = $("#campaignsTbl");
  var $errBanner = $("#campErrors");
  var $form = $("#campForm");
  var newRowHtml = $table.find('tr[data-code="0"]')[0].outerHTML;

  function initRowDatepickers($scope) {
    $scope.find(".ckdate").datepicker({
      changeMonth: true,
      changeYear: true,
    });
  }

  function togglePercent($sel) {
    $sel
      .closest("tr")
      .find('input[name^="campPercent"]')
      .prop("disabled", $sel.val() !== "pct");
  }

  initRowDatepickers($table);
  $table.find(".campTypeSel").each(function () {
    togglePercent($(this));
  });

  // Strip non-numeric, non-decimal characters as the user types in money fields.
  $table.on("input", ".hhk-money", function () {
    var val = this.value;
    var stripped = val.replace(/[^0-9.]/g, "");

    if (stripped !== val) {
      var pos = Math.max(0, this.selectionStart - (val.length - stripped.length));
      this.value = stripped;
      this.setSelectionRange(pos, pos);
    }
  });

  // Format money fields to 2 decimal places once the user leaves the field.
  $table.on("blur", ".hhk-money", function () {
    var amt = parseFloat(this.value);
    this.value = isNaN(amt) ? "" : amt.toFixed(2);
  });

  $table.on("change", ".campTypeSel", function () {
    togglePercent($(this));
  });

  function clearErrors() {
    $table.find(".ui-state-error").removeClass("ui-state-error").removeAttr("title");
    $errBanner.empty().hide();
  }

  function applyErrors(errors) {
    var messages = [];

    Object.keys(errors).forEach(function (code) {
      var rowTitle = $table.find('[name="campTitle[' + code + ']"]').val() || "(untitled)";

      Object.keys(errors[code]).forEach(function (field) {
        var msgs = errors[code][field];
        var prefix = FIELD_INPUT_PREFIX[field];

        if (prefix) {
          $table
            .find('[name="' + prefix + "[" + code + ']"]')
            .addClass("ui-state-error")
            .attr("title", msgs.join(" "));
        }

        messages.push('"' + rowTitle + '": ' + msgs.join(" "));
      });
    });

    if (messages.length > 0) {
      $errBanner
        .removeClass("ui-state-highlight")
        .addClass("ui-state-error")
        .html(messages.join("<br>"))
        .show();
    }
  }

  function applySaved(saved) {
    Object.keys(saved).forEach(function (origCode) {
      var info = saved[origCode];
      var $row = $table.find('tr[data-code="' + origCode + '"]');

      if ($row.length === 0) {
        return;
      }

      if (origCode === "0") {
        // The blank "New" row just became a real campaign: rename its fields from
        // the placeholder code to the assigned one, then drop in a fresh blank row.
        $row.attr("data-code", info.code);
        $row.find("td").first().text(info.code);
        $row.find("[name]").each(function () {
          this.name = this.name.replace("[0]", "[" + info.code + "]");
        });

        var $newRow = $(newRowHtml);
        $row.after($newRow);
        initRowDatepickers($newRow);
        togglePercent($newRow.find(".campTypeSel"));
      }

      var $tds = $row.find("td");
      $tds.eq($tds.length - 2).text(info.lastUpdated);
      $tds.eq($tds.length - 1).text(info.updatedBy);
    });
  }

  $form.on("submit", function (e) {
    e.preventDefault();

    clearErrors();

    var $btn = $("#bttncamp").prop("disabled", true);

    fetch("campaignEdit.php", {
      method: "POST",
      headers: { accept: "application/json" },
      body: new URLSearchParams($form.serialize() + "&bttncamp=Save"),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.saved) {
          applySaved(data.saved);
        }

        if (data.errors && Object.keys(data.errors).length > 0) {
          applyErrors(data.errors);
        } else if (data.success) {
          updateTips($errBanner, "Campaigns saved.");
          $errBanner.removeClass("ui-state-error").addClass("ui-state-highlight").show();
        }
      })
      .catch((error) => {
        if (window.flagAlertMessage) {
          window.flagAlertMessage(error, "error");
        } else {
          alert(error);
        }
      })
      .finally(() => {
        $btn.prop("disabled", false);
      });
  });
});
