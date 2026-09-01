import { flagAlertMessage } from "../common/pag";

document.addEventListener("DOMContentLoaded", () => {
  "use strict";

  var columns = JSON.parse($("#columns").val());
  var demos = JSON.parse($("#demos").val());
  var dtCols = [];
  var target = 0;

  columns.forEach(function (column) {
    var title;
    var render;
    var search;
    if (column.db == "idName") {
      title = column.dt;
      search = true;
      render = function (data, _type) {
        return '<a href="GuestEdit.php?id=' + data + '">' + data + "</a>";
      };
    } else if (demos[column.dt]) {
      search = true;
      title = demos[column.dt].title;
      render = function (data, type, row) {
        var select = $("<select>")
          .attr("name", "sel" + column.dt + "[" + row.id + "]")
          .addClass("demog");
        var option = $("<option>");
        select.append(option);
        $.each(demos[column.dt].list, function (key, item) {
          var option = $("<option>").attr("value", item[0]).text(item[1]);
          if (data == item[0]) {
            option.attr("selected", "selected");
          }
          select.append(option);
        });
        return select[0].outerHTML;
      };
    } else {
      title = column.dt;
      search = true;
      render = function (data, _type) {
        return data;
      };
    }

    dtCols.push({
      targets: [target],
      title: title,
      searchable: search,
      sortable: search,
      data: column.dt,
      render: render,
    });

    target++;
  });

  dtCols.push({
    targets: [target],
    title: "Unknown",
    searchable: false,
    sortable: false,
    data: "idName",
    className: "dt-body-center",
    render: function (_data, _type) {
      return $("<input>").attr({ type: "checkbox", class: "cbUnkn" })[0].outerHTML;
    },
  });

  var filterData = [];
  var hasUnsavedChanges = false;

  var missingDemogTable = $("#dataTbl").DataTable({
    columnDefs: dtCols,
    serverSide: true,
    processing: true,
    deferRender: true,
    autoWidth: false,
    language: { search: "Search missing demographics:" },
    sorting: [[0, "desc"]],
    displayLength: 25,
    lengthMenu: [
      [10, 25, 50],
      [10, 25, 50],
    ],
    ajax: {
      url: "GuestDemog.php",
      type: "post",
      data: function (d) {
        d.cmd = "getMissingDemog";
        $.each(filterData, function (k, v) {
          d[v.name] = v.value;
        });
      },
    },
    /* "fixedHeader": {
        	headerOffset: 38,
        }, */
    layout: {
      top1End: {
        buttons: getSaveBtns(),
      },
      bottom1End: {
        buttons: getSaveBtns(),
      },
    },
    initComplete: function (_settings, _json) {
      this.api()
        .columns()
        .every(function () {
          var column = this;
          var filter = false;
          //get column title from columns object
          if (columns[column.index()]) {
            var columnTitle = columns[column.index()].dt;
          } else {
            var columnTitle = dtCols[column.index()].title;
          }

          if (demos[columnTitle]) {
            //if(column.index() > 2){
            var filter = $("<select>").prop("multiple", "multiple").addClass("filter");
            var option = $("<option>").prop("value", "").text("Not set");
            filter.append(option);
            $.each(demos[columnTitle].list, function (key, item) {
              var option = $("<option>").attr("value", item[0]).text(item[1]);
              filter.append(option);
            });
          }

          if (filter) {
            filter.appendTo($(column.header())).on("change", function () {
              if (hasUnsavedChanges) {
                if (confirm("You have unsaved data, would you like to save first?")) {
                  saveDemog(missingDemogTable);
                } else {
                  var prevValue = $(this).data("prevValue");
                  $(this).val(prevValue);
                  $(this).multiselect("refresh");
                  $(this).blur();
                  return;
                }
              }

              $(this).data("prevValue", $(this).val());

              var data = $(this).val();

              if ($.isArray(data)) {
                $.each(data, function (i, v) {
                  data[i] = v ? "^" + v + "$" : "^$";
                });
                var searchStr = data.join("|");
              } else {
                var searchStr = data ? "^" + $.fn.dataTable.util.escapeRegex(data) + "$" : "^$";
              }

              column.search(searchStr, true, false).draw();
            });

            filter.click(function (e) {
              e.stopPropagation();
            });

            if (filter.is("select")) {
              filter.multiselect({
                noneSelectedText: "Select Filter",
                buttonWidth: "150",
                selectedList: 3,
              });
            }
          } else {
            filter = $("<div>&nbsp;</div>").appendTo($(column.header()));
          }
        });
    },
    drawCallback: function () {
      hasUnsavedChanges = false;
    },
  });

  function getSaveBtns() {
    return [
      {
        text: "Cancel",
        className: "dt-cancel-btn",
        action: function (_e, dt, _node, _config) {
          dt.ajax.reload(null, false);
        },
      },
      {
        text: "Save",
        className: "dt-save-btn",
        action: function (_e, dt, _node, _config) {
          saveDemog(dt);
        },
      },
    ];
  }

  function saveDemog(dt) {
    var data = $("#dataTbl select").serializeArray();
    data.push({ name: "cmd", value: "save" });
    $.ajax({
      type: "POST",
      url: "GuestDemog.php",
      data: data,
      dataType: "json",
      success: function (data) {
        flagAlertMessage(data.success, "success");
      },
      error: function (data) {
        flagAlertMessage(data.error, "error");
      },
      datatype: "json",
    });

    dt.ajax.reload(null, false);
  }

  $("#dataTbl").on("change", ".cbUnkn", function (_e) {
    var cb = $(this);
    var row = cb.closest("tr");
    if (cb.prop("checked")) {
      row.find("select").each(function (k, select) {
        if ($(select).val() == "") {
          $(select).find('option[value="z"]').attr("selected", "selected");
        }
      });
    } else {
      row.find("select").each(function (k, select) {
        if ($(select).val() == "z") {
          $(select).find('option[value="z"]').removeAttr("selected");
        }
      });
    }

    row.find("select").trigger("change");
  });

  $("#dataTbl").on("change", "select.demog", function (_e) {
    hasUnsavedChanges = true;
  });

  $(document).on("click", "#fcat #btnHere", function (e) {
    e.preventDefault();
    filterData = $("#fcat").serializeArray();
    filterData.push({ name: "btnHere", value: "true" });
    missingDemogTable.ajax.reload();
  });

  $(document).on("click", "#fcat #btnReset", function (e) {
    e.preventDefault();
    filterData = [];
    missingDemogTable.ajax.reload();
  });

  $(window).on("beforeunload", function () {
    if (hasUnsavedChanges) {
      return true; //prevent user from leaving
    }
  });
});
