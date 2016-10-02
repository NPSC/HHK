<?php
/**
 * InstallIncludes.php
 *
 * @category  Utility
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/*
 * Find my place and determine paths.
 */
define( 'DS', DIRECTORY_SEPARATOR );
define('P_ROOT', dirname(__FILE__) . DS );


define('REL_BASE_DIR', ".." . DS);
define('REL_BASE_SITE', "../");
define('ciCFG_FILE', REL_BASE_DIR . 'conf' . DS . 'site.cfg' );
define('CLASSES', REL_BASE_DIR . 'classes' . DS);
define('DB_TABLES', CLASSES . 'tables' . DS);
define('MEMBER', CLASSES . 'member' . DS);
define('SEC', CLASSES . 'sec' . DS);
define('FUNCTIONS', REL_BASE_DIR . 'functions' .DS);


define('JQ_UI_CSS', 'css/ss/jquery-ui-1.9.2.custom.min.css');
define('JQ_DT_CSS', 'css/jquery.datatables.css');
define('JQ_UI_JS', 'js/jquery-ui.min.js');
define('JQ_JS', 'js/jquery-1.11.3.min.js');
define('JQ_DT_JS', 'js/jquery.datatables.min.js');
define('PRINT_AREA_JS', "../js/jquery.PrintArea.js");
define('MD5_JS', "js/md5-min.js");

define('TOP_NAV_CSS', "<link href='css/topNav.css' rel='stylesheet' type='text/css' />");
date_default_timezone_set('America/Chicago');

/*
 * includes
 */
require (CLASSES . 'Exception_hk' . DS . 'Hk_Exception.php');

require (FUNCTIONS . 'commonFunc.php');
require (SEC . 'sessionClass.php');
require (CLASSES . 'alertMessage.php');
require (CLASSES . 'config'. DS . 'Lite.php');
require (SEC . 'SecurityComponent.php');
require (SEC . 'ScriptAuthClass.php');
require (SEC . 'ComponentAuthClass.php');
require (CLASSES . 'SysConst.php');
require (SEC . 'webInit.php');
require (CLASSES . 'HTML_Controls.php');

