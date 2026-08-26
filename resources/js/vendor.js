//jquery
import "./common/jquery.js";
import "jquery-ui-dist/jquery-ui.js";

import "jquery-serializejson";
import "jquery-boot";
import "jquery-search";
import "jquery-dirty";

//bootstrap + icons - only Collapse (navbar-toggler) and Dropdown (nav menus)
import Collapse from "bootstrap/js/dist/collapse";
import Dropdown from "bootstrap/js/dist/dropdown";
import "bootstrap/dist/css/bootstrap-grid.min.css";
import "../css/bootstrapNavbar.css";
import "bootstrap-icons/font/bootstrap-icons.css";
window.bootstrap = { Collapse, Dropdown };

//htmlentities
import he from "he";
window.he = he;

//daterangepicker requires moment on window
import moment from "moment";
window.moment = moment;

//datatables v3, core + Responsive/Scroller/Buttons/FixedHeader/RowGroup.
//jQuery UI styling integration.
import DataTable from "datatables.net-jqui";
import "datatables.net-jqui/css/dataTables.jqueryui.css";
import "datatables.net-buttons-jqui";
import "datatables.net-buttons-jqui/css/buttons.jqueryui.css";
import "datatables.net-fixedheader-jqui";
import "datatables.net-fixedheader-jqui/css/fixedHeader.jqueryui.css";
import "datatables.net-rowgroup";

DataTable.ext.errMode = function (settings, techNote, message) {
  flagAlertMessage(message, "error");
};
DataTable.ext.classes.layout.tableCell += " hhk-overflow-x";
DataTable.defaults.layout.topStart = "info";
DataTable.defaults.layout.bottomStart = "pageLength";
window.DataTable = DataTable;
