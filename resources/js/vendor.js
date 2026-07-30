import $ from 'jquery';

// Legacy, non-module scripts across the site (public/js/*.js) expect jQuery
// as a global. Set it before importing jquery-ui-dist, which itself looks
// for a global jQuery to attach to rather than importing the module.
window.$ = window.jQuery = $;

import 'jquery-ui-dist/jquery-ui.js';

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import toastr from 'toastr';
window.toastr = toastr;

import '../css/toastr.css';
