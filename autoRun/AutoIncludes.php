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

define('ciCFG_FILE', REL_BASE_DIR . 'conf' . DS . 'site.cfg' );
define('LABEL_FILE', REL_BASE_DIR . 'conf' . DS . 'labels.cfg' );

// FInd the vendor directory
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

define('CLASSES', REL_BASE_DIR . 'classes' . DS);
define('DB_TABLES', CLASSES . 'tables' . DS);
define('MEMBER', CLASSES . 'member' . DS);
define('HOUSE', CLASSES . 'house' . DS);
define('SEC', CLASSES . 'sec' . DS);
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

require (SEC . 'sessionClass.php');

require (SEC . 'SecurityComponent.php');
require (SEC . 'ScriptAuthClass.php');
require (SEC . 'webInit.php');
require(SEC . 'Login.php');
