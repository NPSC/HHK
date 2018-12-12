<?php
/**
 * homeIncludes.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

define('JSV', '?ir=3');

define('HOUSE_CSS', "<link href='css/house.css' rel='stylesheet' type='text/css' />");

define('RESV_MANAGER_JS', 'js/resvManager.js' . JSV);
define('PAYMENT_JS', "js/payments.js" . JSV);
define('RESV_JS', "js/resv.js" . JSV);
define('VISIT_DIALOG_JS', "js/visitDialog.js" . JSV);
define('INS_EMBED_JS', '<script src="js/embed.js" data-displaymode="incontext" data-hostname="https://online.instamed.com/providers" data-mobiledisplaymode="incontext"></script>');

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


