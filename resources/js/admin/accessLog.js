import { dateRender } from "../common/pag";

document.addEventListener("DOMContentLoaded", () => {
  var usernames = JSON.parse($("#usernames").val());
  var actions = JSON.parse($("#actions").val());

  function getActionName(data) {
    for (let action of actions) {
      if (action.id == data) {
        return action.title;
      }
    }
    return data;
  }

  const dtCols = [
    {
      targets: [0],
      title: "Username",
      data: "Username",
      sortable: true,
      searchable: true,
    },
    {
      targets: [1],
      title: "IP",
      data: "IP",
      sortable: true,
      searchable: true,
    },
    {
      targets: [2],
      title: "Action",
      searchable: true,
      sortable: true,
      data: "Action",
      render: function (data, _type) {
        return getActionName(data);
      },
    },
    {
      targets: [3],
      title: "Date",
      searchable: true,
      sortable: true,
      data: "Date",
      render: function (data, type) {
        return dateRender(data, type, "MMM D, YYYY h:mm a");
      },
    },
    {
      targets: [4],
      title: "Browser",
      data: "Browser",
      sortable: true,
      searchable: true,
    },
    {
      targets: [5],
      title: "OS",
      data: "OS",
      sortable: true,
      searchable: true,
    },
  ];

  $("#dtLog").DataTable({
    columnDefs: dtCols,
    serverSide: true,
    processing: true,
    deferRender: true,
    autoWidth: false,
    language: { sSearch: "Search Access Log :" },
    sorting: [[3, "desc"]],
    paging: true,
    lengthMenu: [
      [25, 50, 100, -1],
      [25, 50, 100, "All"],
    ],
    ajax: {
      url: "ws_gen.php",
      data: {
        cmd: "accesslog",
      },
    },
    initComplete: function () {
      this.api()
        .columns()
        .every(function () {
          var column = this;
          var filter = false;

          if (column.index() == 0) {
            //username column
            filter = $('<select style="max-width: 100%" multiple></select>');
            for (const username of usernames) {
              filter.append('<option value="' + username + '">' + username + "</option>");
            }
          } else if (column.index() == 2) {
            //actions column
            filter = $('<select style="max-width: 100%" multiple></select>');

            for (const action of actions) {
              filter.append('<option value="' + action.id + '">' + action.title + "</option>");
            }
          } else if (column.index() == 3) {
            //date column
            filter = $('<input type="text" style="max-width: 100%" class="autoCal" data-date="">');
          }

          if (filter) {
            filter.appendTo($(column.header())).on("change", function () {
              var data = $(this).val();
              if ($.isArray(data)) {
                $.each(data, function (i, v) {
                  data[i] = v ? "^" + v + "$" : "";
                });
                var searchStr = data.join("|");
              } else if ($(this).hasClass("autoCal")) {
                var d = $.datepicker.parseDate("M d, yy", data);
                var date = $.datepicker.formatDate("yy-mm-dd", d);
                var searchStr = data ? "^" + $.fn.dataTable.util.escapeRegex(date) : "";
              } else {
                var searchStr = data ? "^" + $.fn.dataTable.util.escapeRegex(data) + "$" : "";
              }

              column.search(searchStr, true, false).draw();
            });

            filter.click(function (e) {
              e.stopPropagation();
            });

            if (filter.is("select")) {
              filter.multiselect({
                noneSelectedText: "Select Filter",
              });
            }
            if (filter.hasClass("autoCal")) {
              filter.datepicker({
                changeMonth: true,
                changeYear: true,
                autoSize: true,
                dateFormat: "M d, yy",
              });
            }
          } else {
            $("<div>&nbsp;</div>").appendTo($(column.header()));
          }
        });
    },
  });

  $("input.autoCal").datepicker({
    changeMonth: true,
    changeYear: true,
    autoSize: true,
    dateFormat: "M d, yy",
  });
});
