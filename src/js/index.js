//load css
require("../css/app.scss");

//load jquery
window.$ = window.jQuery = require('jquery');

require("./pag");

//buffer
window.buffer = require('buffer');

//jquery plugins
require('./notesViewer');
require('./smsDialog');
require('./reportfieldSets');
require('./referralViewer');


window.moment = require("moment");