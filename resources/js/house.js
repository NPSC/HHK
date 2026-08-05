import "./vendor.js";
import "./common.js";

import "../css/jqui-house/jquery-ui.min.css";
import "../css/house/house.css";
import "daterangepicker/daterangepicker.css";

// import jquery plugins - automatically attached to jQuery object
import "daterangepicker";
import "./house/incidentReports.js";
import "./house/documentUpload.js";
import "./house/reportfieldSets.js";
import "./house/referralViewer.js";
import "./house/hhkFormBuilder.js";
import "formBuilder";
import "formBuilder/dist/form-render.min.js";

//visit dialog imports
import { viewVisit, saveFees } from "./house/visitDialog.js";
window.viewVisit = viewVisit;
window.saveFees = saveFees;
