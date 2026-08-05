// common app functions/jquery plugins used accross all pages.
import {
  flagAlertMessage,
  dateRender,
  dayRender,
  isIE,
  openiframe,
  logoutTimer,
  getDialogWidth,
  hhkReportError,
} from "./common/pag.js";
import {
  createAutoComplete,
  createRoleAutoComplete,
  createZipAutoComplete,
} from "./common/createAutoComplete.js";
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
window.isIE = isIE;
window.openiframe = openiframe;
window.logoutTimer = logoutTimer;
window.getDialogWidth = getDialogWidth;
window.hhkReportError = hhkReportError;

window.createAutoComplete = createAutoComplete;
window.createRoleAutoComplete = createRoleAutoComplete;
window.createZipAutoComplete = createZipAutoComplete;

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
