import $ from "../common/jquery.js";
import Uppy from "@uppy/core";
import Dashboard from "@uppy/dashboard";
import XHRUpload from "@uppy/xhr-upload";

import "@uppy/core/css/style.min.css";
import "@uppy/dashboard/css/style.min.css";

var ALLOWED_DOC_MIME_TYPES = [
  "application/pdf",
  "application/msword",
  "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
  "image/jpeg",
  "image/png",
];
var MAX_DOC_FILE_SIZE = 5000000;

(function ($) {
  $.fn.docUploader = function (options) {
    var uploader =
      '<div id="docUploadBtn" class="ui-button ui-corner-all ui-widget">' +
      '<span class="ui-icon ui-icon-plusthick" style="margin-right: 0.5em"></span>New Document' +
      "</div>";

    var defaults = {
      guestId: 0,
      psgId: 0,
      rid: 0,
      serviceURL: "ws_resc.php",
      newLabel: "New Document",
      visitorLabel: "Guest",
      tableAttrs: {
        class: "display compact",
        width: "100%",
      },
    };

    var settings = $.extend(true, {}, defaults, options);

    settings.dtCols = [
      {
        targets: [0],
        title: "Actions",
        data: "Action",
        sortable: false,
        searchable: false,
        width: "50px",
        render: function (data, type, row) {
          return createActions(data, row);
        },
      },
      {
        targets: [1],
        title: "Date",
        data: "Date",
        width: "70px",
        render: function (data, type) {
          return dateRender(data, type, "MMM D, YYYY h:mm a");
        },
      },
      {
        targets: [2],
        title: settings.visitorLabel,
        searchable: true,
        sortable: true,
        className: "docGuest",
        data: "Guest",
        width: "150px",
      },
      {
        targets: [3],
        title: "Title",
        searchable: true,
        sortable: false,
        className: "docTitle",
        data: "Title",
      },
      {
        targets: [4],
        title: "User",
        sortable: false,
        searchable: false,
        visible: true,
        data: "User",
        width: "100px",
      },
      {
        targets: [5],
        title: "View Doc",
        data: "View Doc",
        sortable: false,
        searchable: false,
        width: "50px",
        render: function (data, type, row) {
          return createDownload(data, row);
        },
      },
    ];

    var $wrapper = $(this);
    $wrapper.uploader = uploader;

    reinitialize($wrapper, settings);
    createViewer($wrapper, settings);

    return this;
  };

  function clearform($wrapper) {
    $($wrapper.uploader).find("input").val("");
  }

  function createActions(docId) {
    var $ul, $li;

    $ul = $("<ul />").addClass("ui-widget ui-helper-clearfix hhk-ui-icons hhk-flex");

    // Edit icon
    $li = $('<li title="Edit Doc" />').addClass(
      "hhk-doc-button doc-edit ui-corner-all ui-state-default",
    );
    $li.append($('<span class="ui-icon ui-icon-pencil" />'));

    $ul.append($li);

    // Save(Done) Edit Icon
    $li = $('<li title="Save Doc" />')
      .addClass("hhk-doc-button doc-done doc-action ui-corner-all ui-state-default")
      .hide();
    $li.append($('<span class="ui-icon ui-icon-check" />'));

    $ul.append($li);

    // Cancel Edit Icon
    $li = $('<li title="Cancel" />')
      .addClass("hhk-doc-button doc-cancel doc-action ui-corner-all ui-state-default")
      .hide();
    $li.append($('<span class="ui-icon ui-icon-cancel" />'));

    $ul.append($li);

    // Delete Edit Icon
    $li = $('<li title="Delete Doc" data-docid="' + docId + '" />').addClass(
      "hhk-doc-button doc-delete ui-corner-all ui-state-default",
    );
    $li.append($('<span class="ui-icon ui-icon-trash" />'));

    $ul.append($li);

    // Undo Delete Edit Icon
    $li = $('<li title="Undo Delete" data-docid="' + docId + '" />')
      .addClass("hhk-doc-button doc-undodelete ui-corner-all ui-state-default")
      .hide();
    $li.append($('<span class="ui-icon ui-icon-arrowreturnthick-1-w" />'));

    $ul.append($li);

    return $("<div />").append($ul).html();

    //return $ul.html();
  }

  function createDownload(docId) {
    var $btn;

    $btn = $(
      '<a data-docid="' +
        docId +
        '" href="ws_resc.php?cmd=getdoc&docId=' +
        docId +
        '" target="_blank" />',
    ).addClass("hhk-doc-button doc-download ui-corner-all ui-state-default");

    $btn
      .append('Open<span class="ui-icon ui-icon-extlink" style="margin-left: 0.5em;"></span>')
      .button();

    return $("<div />").append($btn).html();

    //return $ul.html();
  }

  function actions($wrapper, settings, $table) {
    //Show Edit mode
    $wrapper.on("click", ".doc-edit", function (e) {
      e.preventDefault();
      var $row = $(this).closest("tr");
      let rowdata = $table.row($row).data();
      $row
        .find(".docTitle")
        .html(
          '<input type="text" size="' +
            rowdata.Title.length +
            '" id="editDocTitle" value="' +
            rowdata.Title +
            '" class="p-1">',
        );
      $row
        .find(".docGuest")
        .html(
          '<input type="text" size="' +
            rowdata.Guest.length +
            '" id="editDocGuest" value="' +
            rowdata.Guest +
            '" data-idguest="' +
            rowdata.Guest_Id +
            '" class="p-1" readonly>',
        );

      let selectGuest = function (item) {
        $row.find("#editDocGuest").attr("data-idguest", item.id).blur();
      };

      let inputParms = {
        cmd: "filter",
        psg: settings.psgId,
        basis: "psg",
      };

      // change attached guest
      createAutoComplete(
        $row.find("#editDocGuest"),
        0,
        inputParms,
        selectGuest,
        false,
        null,
        null,
        true,
      );

      $row.on("focus", "#editDocGuest", function () {
        $(this).autocomplete("search", "");
      });

      $(this).closest("td").find(".doc-action").show();
      $(this).closest("td").find(".doc-delete").hide();
      $(this).hide();
    });
    //End Show Edit mode

    //Edit Doc
    $wrapper.on("click", ".doc-done", function (e) {
      e.preventDefault();
      var row = $(this).closest("tr");
      var rowdata = $table.row(row).data();
      var docTitle = row.find("#editDocTitle").val();
      var docGuestId = row.find("#editDocGuest").data("idguest");
      var docId = rowdata.DocId;

      if (docTitle != "" || docGuestId != "") {
        $.ajax({
          url: settings.serviceURL,
          dataType: "JSON",
          type: "post",
          data: {
            cmd: "updatedoc",
            docId: docId,
            docTitle: docTitle,
            docGuestId: docGuestId,
          },
          success: function (data) {
            if (data.idDoc > 0) {
              $table.ajax.reload(null, false);
            } else {
              if (data.error) {
                flagAlertMessage(data.error, "error");
              } else {
                flagAlertMessage("An unknown error occurred.", "alert");
              }
            }
          },
        });
      }
    });
    //End Edit Doc

    //Cancel Doc
    $wrapper.on("click", ".doc-cancel", function (e) {
      e.preventDefault();
      $table.ajax.reload(null, false);
    });
    //End Cancel Doc

    //Delete Doc
    $wrapper.on("click", ".doc-delete", function (e) {
      var docId = $(this).data("docid");
      var row = $(this).closest("tr");
      e.preventDefault();
      $.ajax({
        url: settings.serviceURL,
        dataType: "JSON",
        type: "post",
        data: {
          cmd: "deletedoc",
          docId: docId,
        },
        success: function (data) {
          if (data.idDoc > 0) {
            row.find("td:not(:first)").css("opacity", "0.3");
            var docTitle = row.find("#editDocTitle").val();
            row.find(".docTitle").html(docTitle);
            row.find(".doc-action").hide();
            row.find(".doc-delete").hide();
            row.find(".doc-edit").hide();
            row.find(".doc-undodelete").show();
            $("#docTitle").val("");
            $("#hhk-newNote").removeAttr("disabled").text(settings.newLabel);
          } else {
            flagAlertMessage(data.error, "error");
          }
        },
      });
    });
    //End Delete Doc

    //Undo Delete Doc
    $wrapper.on("click", ".doc-undodelete", function (e) {
      var docId = $(this).data("docid");
      var row = $(this).parents("tr");

      e.preventDefault();
      $.ajax({
        url: settings.serviceURL,
        dataType: "JSON",
        type: "post",
        data: {
          cmd: "undodeletedoc",
          docId: docId,
        },
        success: function (data) {
          if (data.idDoc > 0) {
            //$table.ajax.reload();
            var rowdata = $table.row(row).data();
            $table.row(row).data(rowdata);
            row.find("td").css("opacity", "1");
            $("#docTitle").val("");
            $("#hhk-newNote").removeAttr("disabled").text(settings.newLabel);
          } else {
            flagAlertMessage(data.error, "error");
          }
        },
      });
    });
    //End Undo Delete Note
  }

  function createViewer($wrapper, settings) {
    if (settings.guestId > 0 || settings.psgId > 0 || settings.rid > 0) {
      //add new doc btn
      $wrapper.append($wrapper.uploader);

      var $table = $("<table />").attr(settings.tableAttrs).appendTo($wrapper);

      var dtTable = $table.DataTable({
        columnDefs: settings.dtCols,
        serverSide: true,
        processing: true,
        deferRender: true,
        language: { sSearch: "Search Docs:" },
        sorting: [[1, "desc"]],
        paging: false,
        lengthMenu: [
          [5, 10, 25, -1],
          [5, 10, 25, "All"],
        ],
        ajax: {
          url: settings.serviceURL,
          data: {
            cmd: "getDocumentList",
            psgId: settings.psgId,
          },
        },
        drawCallback: function () {
          $wrapper.find(".hhk-doc-button").button();
        },
      });

      actions($wrapper, settings, dtTable);

      //add ignrSave class to Dt controls
      $(".dataTables_filter").addClass("ignrSave");
      $(".dtBottom").addClass("ignrSave");

      var uppy = new Uppy({
        restrictions: {
          maxFileSize: MAX_DOC_FILE_SIZE,
          allowedFileTypes: ALLOWED_DOC_MIME_TYPES,
        },
        meta: {
          cmd: "putdoc",
          guestId: settings.guestId,
          psgId: settings.psgId,
        },
        onBeforeFileAdded: function (file) {
          return {
            ...file,
            meta: {
              ...file.meta,
              docTitle: file.name
                ? file.name.substr(0, file.name.lastIndexOf(".")) || file.name
                : file.name,
              mimetype: file.type,
            },
          };
        },
      })
        .use(Dashboard, {
          target: $wrapper[0],
          inline: false,
          closeModalOnClickOutside: true,
          closeAfterFinish: true,
          note: "Allowed filetypes: pdf, doc, docx, jpeg, png. Maximum file size: 5MB.",
          metaFields: [{ id: "docTitle", name: "Title", placeholder: "Enter document title" }],
        })
        .use(XHRUpload, {
          endpoint: settings.serviceURL,
          method: "post",
          fieldName: "file",
          formData: true,
        });

      uppy.on("upload-success", function (file, response) {
        var data = response.body || {};

        if (data.idDoc > 0) {
          dtTable.ajax.reload();
          clearform($wrapper);
        } else {
          flagAlertMessage(data.error || "An unknown error occurred.", "error");
        }
      });

      $wrapper.on("click", "#docUploadBtn", function () {
        uppy.getPlugin("Dashboard").openModal();
      });
    }
  }

  function reinitialize($wrapper) {
    $wrapper.off("click", "*");
  }
})($);
