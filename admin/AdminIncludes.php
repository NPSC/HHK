<?php
/**
 * AdminIncludes.php
 *
-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2017 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK
 */

/*
 * Find my place and determine paths.
 */
define( 'DS', DIRECTORY_SEPARATOR );
define('P_ROOT', dirname(__FILE__) . DS );


// FInd the third party directory
$dirxx = '../thirdParty';
if (file_exists($dirxx) === FALSE) {
    $dirxx = '../../thirdParty';
    if (file_exists($dirxx) === FALSE) {
        $dirxx = '../../../thirdParty';
        if (file_exists($dirxx) === FALSE) {
            throw new Exception('Cannot find the thirdParty directory.');
        }
    }
}

define('THIRD_PARTY', $dirxx . DS);

define('REL_BASE_DIR', ".." . DS);
define('REL_BASE_SITE', "../");
define('ciCFG_FILE', REL_BASE_DIR . 'conf' . DS . 'site.cfg' );
define('LABEL_FILE', REL_BASE_DIR . 'conf' . DS . 'labels.cfg' );
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

define('JS_V', '?v=3');
define('CSS_V', '?v=2');

define('JQ_UI_CSS', 'css/ss/jquery-ui.min.css' . CSS_V);
define('JQ_DT_CSS', 'css/datatables.min.css' . CSS_V);
define('JQ_UI_JS', 'js/jquery-ui.min.js'.JS_V);
define('JQ_JS', 'js/jquery-1.11.3.min.js'.JS_V);
define('JQ_DT_JS', 'js/datatables.min.js'.JS_V);
define('PRINT_AREA_JS', "js/jquery.PrintArea.js".JS_V);
define('MD5_JS', "js/md5-min.js".JS_V);
define('MULTISELECT_CSS', "<link href='css/jquery.multiselect.css" . CSS_V . "' rel='stylesheet' type='text/css' />");

define('TOP_NAV_CSS', "<link href='css/topNav.css" . CSS_V . "' rel='stylesheet' type='text/css' />");

date_default_timezone_set('America/Chicago');

/*
 * includes
 */
require (CLASSES . 'PDOdata.php');
require (CLASSES . 'HTML_Controls.php');
require (DB_TABLES . 'HouseRS.php');

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

require (CLASSES . 'Purchase/PriceModel.php');

