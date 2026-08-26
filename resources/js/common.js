// common app functions/jquery plugins used accross all pages.
import { flagAlertMessage, dateRender, getDialogWidth } from "./common/pag.js";

window.flagAlertMessage = flagAlertMessage;
window.dateRender = dateRender;
window.getDialogWidth = getDialogWidth;

//notesViewer.js - attaches $.fn.notesViewer
import "./common/notesViewer.js";

//smsDialog.js - attaches $.fn.smsDialog
import "./common/smsDialog.js";

import "./common/jquery.PrintArea.js";

import "./common/stateCountry.js";

import "jquery-ui-multiselect-widget";
import "jquery-ui-multiselect-widget/css/jquery.multiselect.css";
