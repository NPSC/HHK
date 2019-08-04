<?php
/**
 * homeIncludes.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

define('JSV', '?qs=6');

define('HOUSE_CSS', "<link href='css/house.css' rel='stylesheet' type='text/css' />");

define('RESV_MANAGER_JS', 'js/resvManager-min.js' . JSV);
define('PAYMENT_JS', "js/payments.js" . JSV);
define('VISIT_DIALOG_JS', "js/visitDialog-min.js" . JSV);

define('RESV_JS', "js/resv.js" . JSV);
define('INS_EMBED_JS', '<script src="https://instamedprd.cachefly.net/Content/Js/embed.js" data-displaymode="embedded" data-hostname="https://online.instamed.com/providers" data-mobiledisplaymode="embedded"></script>');

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


