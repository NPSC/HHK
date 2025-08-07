<?php

/*
 * Find my place and determine paths.
 */
define( 'DS', DIRECTORY_SEPARATOR );
define('P_ROOT', dirname(__FILE__) . DS );
define('REL_BASE_DIR', '..' . DS);
define('REL_BASE_SITE', '../');


// Find the vendor directory
$dirxx = '../vendor';
if (file_exists($dirxx) === FALSE) {
    $dirxx = '../' . $dirxx;
    if (file_exists($dirxx) === FALSE) {
        $dirxx = '../' . $dirxx;
        if (file_exists($dirxx) === FALSE) {
            $dirxx = '../' . $dirxx;
            if (file_exists($dirxx) === FALSE) {
                throw new Exception('Cannot find the vendor directory.');
            }
        }
    }
}

define('THIRD_PARTY', $dirxx . DS);

// Configuration filename and paths
define('CONF_PATH', REL_BASE_DIR . 'conf' . DS);
define('ciCFG_FILE', 'site.cfg' );

// Common Directory Names
define('ADMIN_DIR', REL_BASE_DIR . 'admin' . DS);
define('CLASSES', REL_BASE_DIR . 'classes' . DS);
define('DB_TABLES', CLASSES . 'tables' . DS);
define('MEMBER', CLASSES . 'member' . DS);
define('HOUSE', CLASSES . 'house' . DS);
define('SEC', CLASSES . 'sec' . DS);
define('PMT', CLASSES . 'Payment' . DS);
define('FUNCTIONS', REL_BASE_DIR . 'functions' .DS);

require(FUNCTIONS . 'errorHandler.php');
require (THIRD_PARTY . '/autoload.php');
require (FUNCTIONS . 'commonFunc.php');

define('JSV', '?v=' . HHK\SysConst\CodeVersion::BUILD);

// Page header links and scripts
define('JQ_UI_CSS', '<link href="css/jqui/jquery-ui.min.css' . JSV . '" rel="stylesheet" type="text/css" />');
define('JQ_DT_CSS', '<link href="css/datatables.min.css' . JSV . '" rel="stylesheet" type="text/css" />');
define('MULTISELECT_CSS', '<link href="css/jquery.multiselect.css' . JSV . '" rel="stylesheet" type="text/css" />');
define('FAVICON', '<link rel="icon" type="image/svg+xml" href="../favicon.svg' . JSV . '" />');
define('DR_PICKER_CSS', '<link rel="stylesheet" href="css/daterangepicker.min.css' . JSV . '">');
define('FULLC_CSS', '<link href="css/fullcalendar.min.css' . JSV . '" rel="stylesheet" type="text/css" />');
define('SELECT2_CSS', '<link href="css/select2.min.css' . JSV . '" rel="stylesheet" type="text/css" />');
define('NOTY_CSS', "<link href='../css/toastr.css" . JSV . "' rel='stylesheet' type='text/css' />");
define('INCIDENT_CSS', '<link href="css/incidentReports.css' . JSV . '" rel="stylesheet" type="text/css" />');
define('UPPLOAD_CSS', '<link rel="stylesheet" href="../js/uppload/uppload.css' . JSV . '"><link rel="stylesheet" href="../js/uppload/light.css' . JSV . '">');

define('JQ_UI_JS', '../js/jquery-ui.min.js' . JSV);
define('JQ_JS', '../js/jquery-min.js' . JSV);
define('JQ_DT_JS', '../js/datatables.min.js' . JSV);
define('NOTY_JS', '../js/toastr.min.js' . JSV);
define('NOTY_SETTINGS_JS', '../js/toastrSettings.js' . JSV);  //These are local
define('JQ_DT_SETTINGS', '../js/datatablesSettings.js' . JSV);
define('DIRRTY_JS', '../js/jquery.dirrty.js' . JSV);
define('JSIGNATURE_JS', '../js/jSignature.min.js' . JSV);
define('BOOTSTRAP_JS', '../js/bootstrap.min.js' . JSV);
define('HTMLENTITIES_JS', '../js/he.js'. JSV);

define('PRINT_AREA_JS', '../js/jquery.PrintArea.js' . JSV);
define('CREATE_AUTO_COMPLETE_JS', '../js/createAutoComplete.js' . JSV);
define('ADDR_PREFS_JS', '../js/addrPrefs-min.js' . JSV);
define('STATE_COUNTRY_JS', '../js/stateCountry.js' . JSV);
define('PAG_JS', '../js/pag.js' . JSV);
define('LOGIN_JS', '../js/login.js' . JSV);
define('MOMENT_JS', '../js/moment.min.js' . JSV);
define('MULTISELECT_JS', '../js/jquery.multiselect.min.js' . JSV);
define('DR_PICKER_JS', '../js/daterangepicker.hhk.min.js' . JSV);
define('FULLC_JS', 'js/fullcalendarold.min.js' . JSV);
define('NOTES_VIEWER_JS', '../js/notesViewer.js' . JSV);
define('SELECT2_JS', '../js/select2.min.js' . JSV);
define('UPPLOAD_JS', '../js/uppload/uppload-3.2.1.min.js' . JSV);
define('BUFFER_JS', '../js/buffer.min.js' . JSV);
define('LIBPHONENUMBER_JS', '../js/libphonenumber.min.js' . JSV);