<?php
/**
 * InstallIncludes.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/*
 * Find my place and determine paths.
 */
define( 'DS', DIRECTORY_SEPARATOR );
define('P_ROOT', __DIR__ . DS );


define('REL_BASE_DIR', P_ROOT .".." . DS);
define('REL_BASE_SITE', P_ROOT.".." . DS);
define('CONF_PATH', REL_BASE_DIR . 'conf' . DS);
define('ciCFG_FILE', 'site.cfg' );
define('FUNCTIONS', REL_BASE_DIR . 'functions' .DS);


define('JQ_UI_JS', 'js/jquery-ui.min.js');
define('JQ_JS', 'js/jquery-min.js');

date_default_timezone_set('America/Chicago');

/*
 * includes
 */

if (file_exists(REL_BASE_SITE . 'vendor'.DS.'autoload.php')) {
    require(REL_BASE_SITE . 'vendor'.DS.'autoload.php');
} else {
    exit("Unable to load dependancies, be sure to run 'composer install'");
}