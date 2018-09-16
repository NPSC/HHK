<?php
/**
 * homeIncludes.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

define('JSV', '?i9er=45');

define('HOUSE_CSS', "<link href='css/house.css' rel='stylesheet' type='text/css' />");
define('NOTY_CSS', "<link href='css/noty/noty.css' rel='stylesheet' type='text/css' /><link href='css/noty/themes/semanticui.css' rel='stylesheet' type='text/css' /><link href='css/noty/animate.css' rel='stylesheet' type='text/css' />");


define('PAYMENT_JS', "js/payments-min.js" . JSV);
define('RESV_JS', "js/resv.js" . JSV);
define('VISIT_DIALOG_JS', "js/visitDialog-min.js" . JSV);


/**
 * Includes
 */
require ('../functions/commonDefines.php');

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
require (CLASSES . 'SysConst.php');
require (SEC . 'webInit.php');
require (CLASSES . 'Purchase/PriceModel.php');


