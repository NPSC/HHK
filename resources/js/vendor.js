//jquery
import './common/jquery.js';
import 'jquery-ui-dist/jquery-ui.js';

//moment.js
import moment from 'moment';
window.moment = moment;

//bootstrap + icons - only Collapse (navbar-toggler) and Dropdown (nav menus)
import Collapse from 'bootstrap/js/dist/collapse';
import Dropdown from 'bootstrap/js/dist/dropdown';
import 'bootstrap-icons/font/bootstrap-icons.css';
window.bootstrap = { Collapse, Dropdown };

//htmlentities
import he from 'he';
window.he = he;

//DOMPurify
import DOMPurify from 'dompurify';
window.DOMPurify = DOMPurify;

//buffer.js
import Buffer from 'buffer';
window.buffer = Buffer;

//google-libphonenumber
import * as libphonenumber from 'google-libphonenumber';
window.libphonenumber = libphonenumber;

//datatables v3, core only - no Buttons/RowGroup/Responsive/FixedHeader
//extensions, since no currently-migrated page uses them. jQuery UI styling
//integration.
import DataTable from 'datatables.net-jqui';
import 'datatables.net-jqui/css/dataTables.jqueryui.css';
window.DataTable = DataTable;