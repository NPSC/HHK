<?php
/**
 * homeIncludes.php
 *
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
/**
 * Constants
 */
/**
 * P_BASE
 */
define('P_BASE', dirname(__FILE__) );

/**
 * DS directory seperator
 */
define( 'DS', DIRECTORY_SEPARATOR );

/**
 *  REL_BASE_DIR Relative parent directory
 */
define('REL_BASE_DIR', ".." . DS);


/**
 * ciCFG_FILE configuration file location
 */
define('ciCFG_FILE', REL_BASE_DIR . 'conf' . DS . 'site.cfg' );

define('LABEL_FILE', REL_BASE_DIR . 'conf' . DS . 'labels.cfg' );

/**
 * ADMIN_DIR admin directory
 */
define( 'ADMIN_DIR', REL_BASE_DIR . "admin" . DS);

/**
 * REL_BASE_SITE relative parent site
 */
define('REL_BASE_SITE', "../");

/**
 * CLASSES path to base classes directory
 */
define('CLASSES', REL_BASE_DIR . 'classes' . DS);

/**
 * DB_TABLES path to database table record set classes
 */
define('DB_TABLES', CLASSES . 'tables' . DS);

/**
 * SEC path to security classes
 */
define('SEC', CLASSES . 'sec' . DS);

/**
 * PMT path to payment classes
 */
define('PMT', CLASSES . 'Payment' . DS);

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

/**
 * FUNCTIONS path to base functions directory
 */
define('FUNCTIONS', REL_BASE_DIR . 'functions' .DS);

define('HOUSE', CLASSES . 'house' . DS);
define('MEMBER', CLASSES . 'member' . DS);

define('JS_V', '?v=3');
define('CSS_V', '?v=2');

// paths
define('JQ_UI_CSS', '<link href="css/redmond/jquery-ui.min.css' . CSS_V . '" rel="stylesheet" type="text/css" />');
define('JQ_DT_CSS', '<link href="css/datatables.min.css' . CSS_V . '" rel="stylesheet" type="text/css" />');
define('TOP_NAV_CSS', "<link href='css/topNav.css" . CSS_V . "' rel='stylesheet' type='text/css' />");
define('MULTISELECT_CSS', "<link href='css/jquery.multiselect.css" . CSS_V . "' rel='stylesheet' type='text/css' />");
define('HOUSE_CSS', "<link href='css/house.css" . CSS_V . "' rel='stylesheet' type='text/css' />");

define('JQ_UI_JS', 'js/jquery-ui.min.js' . JS_V);
define('JQ_JS', 'js/jquery-1.11.3.min.js' . JS_V);
define('JQ_DT_JS', 'js/datatables.min.js' . JS_V);
define('PRINT_AREA_JS', "js/jquery.PrintArea.js" . JS_V);
define('VERIFY_ADDRS_JS', "js/verifyAddrs-min.js" . JS_V);
define('STATE_COUNTRY_JS', "js/stateCountry.js" . JS_V);
define('PAYMENT_JS', "js/payments.js" . JS_V);
define('VISIT_DIALOG_JS', "js/visitDialog-min.js" . JS_V);
define('RESV_JS', "js/resv.js" . JS_V);
define('MD5_JS', 'js/md5-min.js');


date_default_timezone_set('America/Chicago');

/**
 * Includes
 */
require (CLASSES . 'PDOdata.php');
require (CLASSES . 'HTML_Controls.php');
require (DB_TABLES . 'HouseRS.php');

require (FUNCTIONS . 'commonFunc.php');
require (CLASSES . 'config'. DS . 'Lite.php');
require (SEC . 'sessionClass.php');
require (CLASSES . 'alertMessage.php');
require (CLASSES . 'Exception_hk' . DS . 'Hk_Exception.php');
require (SEC . 'SecurityComponent.php');
require (SEC . 'ScriptAuthClass.php');
require (SEC . 'ComponentAuthClass.php');
require (CLASSES . 'SysConst.php');
require (SEC . 'webInit.php');
require (CLASSES . 'Purchase/PriceModel.php');


