//jQuery
window.$ = window.jQuery = require("jquery");

//jquery ui
require("jquery-ui-bundle");
import '../../house/css/jqui/jquery-ui.min.css';

//Noty
import Noty from "noty";
Noty.overrideDefaults({
    layout   : 'top',
    theme    : 'semanticui',
    timeout  : '4000',
    progressBar : true,
    closeWith: ['click'],
    animation: {
        open : 'animated bounceInDown',
        close: 'animated bounceOutUp'
    }
});

//pag.js
import('../../js/pag.js');

//import 'bootstrap';
require('bootstrap');

//datatables
require('datatables.net-dt');


//import CSS
import '../../house/css/house.css';
import '../../css/bootstrapNavbar.css';
import '../../css/bootstrap-grid.min.css';


require('../../house/js/reportfieldSets.js');
//require('../../js/datatables.min.js');

import CommonLib from '../../js/CommonLib.js';

window.CommonLib = CommonLib;