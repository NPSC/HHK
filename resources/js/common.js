//pag.js - attach functions to window globally
import { flagAlertMessage, dateRender, dayRender, isIE, openiframe, logoutTimer, getDialogWidth, hhkReportError } from './common/pag.js';
window.flagAlertMessage = flagAlertMessage;
window.dateRender = dateRender;
window.dayRender = dayRender;
window.isIE = isIE;
window.openiframe = openiframe;
window.logoutTimer = logoutTimer;
window.getDialogWidth = getDialogWidth;
window.hhkReportError = hhkReportError;

//notesViewer.js - attaches $.fn.notesViewer
import './common/notesViewer.js';

//smsDialog.js - attaches $.fn.smsDialog
import './common/smsDialog.js';
