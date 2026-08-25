// common app functions/jquery plugins used accross all pages.
import {
  flagAlertMessage,
  dateRender,
  dayRender,
  logoutTimer,
  getDialogWidth,
  hhkReportError,
} from "./common/pag.js";
import {
  addrPrefs,
  verifyAddrs,
  validatePhoneNumber,
  cleanPhoneNumber,
} from "./common/addrPrefs.js";
import { initGuestPhoto } from "./common/guestPhoto.js";

window.flagAlertMessage = flagAlertMessage;
window.dateRender = dateRender;
window.dayRender = dayRender;
window.logoutTimer = logoutTimer;
window.getDialogWidth = getDialogWidth;
window.hhkReportError = hhkReportError;

window.addrPrefs = addrPrefs;
window.verifyAddrs = verifyAddrs;
window.validatePhoneNumber = validatePhoneNumber;
window.cleanPhoneNumber = cleanPhoneNumber;

window.initGuestPhoto = initGuestPhoto;

//notesViewer.js - attaches $.fn.notesViewer
import "./common/notesViewer.js";

//smsDialog.js - attaches $.fn.smsDialog
import "./common/smsDialog.js";

import "./common/jquery.PrintArea.js";

import "./common/stateCountry.js";

import "jquery-ui-multiselect-widget";
import "jquery-ui-multiselect-widget/css/jquery.multiselect.css";
