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
define('ciCFG_FILE', REL_BASE_DIR . 'conf' . DS . 'site.cfg' );
define('LABEL_FILE', REL_BASE_DIR . 'conf' . DS . 'labels.cfg' );

// Common Directory Names
define('ADMIN_DIR', REL_BASE_DIR . 'admin' . DS);
define('CLASSES', REL_BASE_DIR . 'classes' . DS);
define('DB_TABLES', CLASSES . 'tables' . DS);
define('MEMBER', CLASSES . 'member' . DS);
define('HOUSE', CLASSES . 'house' . DS);
define('SEC', CLASSES . 'sec' . DS);
define('PMT', CLASSES . 'Payment' . DS);
define('FUNCTIONS', REL_BASE_DIR . 'functions' .DS);


// Page header links and scripts
define('JQ_UI_CSS', '<link href="css/jqui/jquery-ui.min.css" rel="stylesheet" type="text/css" />');
define('JQ_DT_CSS', '<link href="css/datatables.min.css" rel="stylesheet" type="text/css" />');
define('MULTISELECT_CSS', '<link href="css/jquery.multiselect.css" rel="stylesheet" type="text/css" />');
define('FAVICON', '<link rel="icon" type="image/png" href="../images/hhkIcon.png" />');
define('DR_PICKER_CSS', '<link rel="stylesheet" href="css/daterangepicker.min.css">');
define('FULLC_CSS', '<link href="css/fullcalendar.css" rel="stylesheet" type="text/css" />');
define('RTE_CSS', '<link href="css/rich-text-editor.css" rel="stylesheet" type="text/css" />');
define('SELECT2_CSS', '<link href="css/select2.min.css" rel="stylesheet" type="text/css" />');
define('NOTY_CSS', "<link href='css/noty/noty.css' rel='stylesheet' type='text/css' /><link href='css/noty/themes/semanticui.css' rel='stylesheet' type='text/css' /><link href='css/noty/animate.css' rel='stylesheet' type='text/css' />");
define('DROPZONE_CSS', "<link href='css/basic.min.css' rel='stylesheet' type='text/css' /><link href='css/dropzone.min.css' rel='stylesheet' type='text/css' />");

define('JQ_UI_JS', '../js/jquery-ui.min.js');
define('JQ_JS', '../js/jquery-3.1.1.min.js');
define('JQ_DT_JS', '../js/datatables.min.js');
define('NOTY_JS', '../js/noty-min.js');
define('NOTY_SETTINGS_JS', 'js/notySettings.js');  //These are local
define('DIRRTY_JS', '../js/jquery.dirrty.js');

define('PRINT_AREA_JS', '../js/jquery.PrintArea.js');
define('CREATE_AUTO_COMPLETE_JS', '../js/createAutoComplete.js');
define('ADDR_PREFS_JS', '../js/addrPrefs-min.js');
define('STATE_COUNTRY_JS', '../js/stateCountry.js');
define('PAG_JS', '../js/pag.js');
define('MD5_JS', '../js/md5-min.js');
define('LOGIN_JS', '../js/login.js');
define('MOMENT_JS', '../js/moment.min.js');
define('MULTISELECT_JS', '../js/jquery.multiselect.min.js');
define('DR_PICKER_JS', '../js/daterangepicker.hhk.min.js');
define('FULLC_JS', '../js/fullcalendarold.min.js');
define('RTE_JS', '../js/rich-text-editor.js');
define('NOTES_VIEWER_JS', '../js/notesViewer.js');
define('DROPZONE_JS', '../js/dropzone.js');
define('SELECT2_JS', '../js/select2.min.js');
