<?php
/**
 * AutoIncludes.php
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
define('CLASSES', REL_BASE_DIR . 'classes' . DS);
define('DB_TABLES', CLASSES . 'tables' . DS);
define('MEMBER', CLASSES . 'member' . DS);
define('HOUSE', CLASSES . 'house' . DS);
/**
 * SEC path to security classes
 */
define('SEC', CLASSES . 'sec' . DS);
/**
 * PMT path to payment classes
 */
define('PMT', CLASSES . 'Payment' . DS);
define('FUNCTIONS', REL_BASE_DIR . 'functions' .DS);



date_default_timezone_set('America/Chicago');

/*
 * includes
 */
require (CLASSES . 'Exception_hk' . DS . 'Hk_Exception.php');

require (FUNCTIONS . 'commonFunc.php');
require (CLASSES . 'config'. DS . 'Lite.php');
require (CLASSES . 'SysConst.php');
require (CLASSES . 'HTML_Controls.php');

