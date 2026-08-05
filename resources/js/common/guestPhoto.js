import Uppy from "@uppy/core";
import Dashboard from "@uppy/dashboard";
import Webcam from "@uppy/webcam";
import ImageEditor from "@uppy/image-editor";
import XHRUpload from "@uppy/xhr-upload";

import "@uppy/core/css/style.min.css";
import "@uppy/dashboard/css/style.min.css";
import "@uppy/webcam/css/style.min.css";
import "@uppy/image-editor/css/style.min.css";

var MAX_PHOTO_FILE_SIZE = 5000000;
var ALLOWED_PHOTO_MIME_TYPES = ["image/jpeg", "image/png"];

/**
 * Wires up the guest photo widget (markup from Photo::showGuestPicture) to an
 * Uppy dashboard for capture/upload, and to the delete button. Used on both
 * admin (NameEdit.php) and house (GuestEdit.php) guest pages.
 *
 * @param {object} options
 * @param {function} options.getGuestId - returns the current guest id
 * @param {string} options.endpoint - ws_resc.php URL, relative to the page
 * @param {string} [options.container] - photo element selector
 * @param {string} [options.triggerSelector] - "add photo" button selector
 * @param {string} [options.actionsSelector] - hover actions container selector
 * @param {string} [options.deleteSelector] - delete button selector
 * @param {function} [options.onError] - called with a message on upload/delete failure
 */
export function initGuestPhoto(options) {
  "use strict";

  var settings = $.extend(
    {
      container: "#hhk-guest-photo",
      triggerSelector: ".upload-guest-photo",
      actionsSelector: "#hhk-guest-photo-actions",
      deleteSelector: ".delete-guest-photo",
      onError: function (message) {
        alert(message);
      },
    },
    options,
  );

  var $container = $(settings.container);

  if ($container.length === 0) {
    return null;
  }

  function refreshPhoto() {
    $container.css(
      "background-image",
      "url(" +
        settings.endpoint +
        "?cmd=getguestphoto&guestId=" +
        settings.getGuestId() +
        "&x=" +
        new Date().getTime() +
        ")",
    );
  }

  var uppy = new Uppy({
    restrictions: {
      maxFileSize: MAX_PHOTO_FILE_SIZE,
      allowedFileTypes: ALLOWED_PHOTO_MIME_TYPES,
      maxNumberOfFiles: 1,
    },
  })
    .use(Dashboard, {
      target: document.body,
      inline: false,
      closeModalOnClickOutside: true,
      closeAfterFinish: true,
      proudlyDisplayPoweredByUppy: false,
      note: "JPEG or PNG. Crop to a square with the edit tool.",
    })
    .use(Webcam, { target: Dashboard, modes: ["picture"] })
    .use(ImageEditor, {
      target: Dashboard,
      cropperOptions: {
        aspectRatio: 1,
        viewMode: 1,
        autoCropArea: 1,
      },
    })
    .use(XHRUpload, {
      endpoint: settings.endpoint,
      method: "post",
      fieldName: "guestPhoto",
      formData: true,
    });

  uppy.on("upload-success", function (file, response) {
    var data = response.body || {};

    if (data.error) {
      if (data.gotopage) {
        window.open(data.gotopage, "_self");
      }
      settings.onError(data.error);
    } else {
      refreshPhoto();
      $(settings.deleteSelector).show();
    }

    uppy.cancelAll();
  });

  uppy.on("upload-error", function (file, error) {
    settings.onError(error.message || "Upload failed.");
  });

  $(document).on("click", settings.triggerSelector, function () {
    uppy.setMeta({ cmd: "putguestphoto", guestId: settings.getGuestId() });
    void uppy.getPlugin("Dashboard").openModal();
  });

  $(document).on("click", settings.container, function (e) {
    e.preventDefault();
  });

  $(document).on(
    {
      mouseenter: function () {
        $(this).find(settings.actionsSelector).show();
        $(this).find("img").fadeTo(100, 0.5);
      },
      mouseleave: function () {
        $(this).find(settings.actionsSelector).hide();
        $(this).find("img").fadeTo(100, 1);
      },
    },
    settings.container,
  );

  $(document).on("click", settings.deleteSelector, function () {
    if (confirm("Really Delete this photo?")) {
      $.ajax({
        type: "POST",
        url: settings.endpoint,
        dataType: "json",
        data: {
          cmd: "deleteguestphoto",
          guestId: settings.getGuestId(),
        },
        success: function (data) {
          if (data.error) {
            if (data.gotopage) {
              window.location.assign(data.gotopage);
              return;
            }
            settings.onError("Server error - " + data.error);
          } else {
            refreshPhoto();
          }
        },
        error: function (error) {
          settings.onError("AJAX error - " + error);
        },
      });
    }
  });

  return uppy;
}
