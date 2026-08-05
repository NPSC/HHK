<?php

/*
 * Find my place and determine paths.
 */
define( 'DS', DIRECTORY_SEPARATOR );
define('P_ROOT', __DIR__ . DS );
define('REL_BASE_DIR', dirname(P_ROOT) . DS);
define('REL_BASE_SITE', '..'.DS);

// Find the vendor directory
$dirxx = 'vendor';
if (file_exists(REL_BASE_DIR . $dirxx) === FALSE) {
    $dirxx = '..'.DS . $dirxx;
    if (file_exists(REL_BASE_DIR . $dirxx) === FALSE) {
        $dirxx = '..'.DS . $dirxx;
        if (file_exists(REL_BASE_DIR . $dirxx) === FALSE) {
            $dirxx = '..'.DS . $dirxx;
            if (file_exists(REL_BASE_DIR . $dirxx) === FALSE) {
                throw new Exception('Cannot find the vendor directory.');
            }
        }
    }
}

define('THIRD_PARTY', REL_BASE_DIR . $dirxx . DS);


require(REL_BASE_DIR . 'functions' . DS . 'errorHandler.php');
require (THIRD_PARTY . 'autoload.php');
HHK\Config\Env::load(REL_BASE_DIR);
HHK\Debug\DebugBarSupport::bootstrap();

define('JSV', '?v=' . HHK\SysConst\CodeVersion::BUILD);

// Page header links and scripts
define('FAVICON', '<link rel="icon" type="image/svg+xml" href="../favicon.svg' . JSV . '" />');
define('INCIDENT_CSS', '<link href="css/incidentReports.css' . JSV . '" rel="stylesheet" type="text/css" />');

define('LOGIN_JS', '../js/login.js' . JSV);
