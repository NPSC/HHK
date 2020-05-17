<?php
/**
 * VolIncludes.php
 *
 * @category  Site
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

define('MAX_IDLE_TIME', '1800');

define('PUBLIC_CSS', "<link href='css/publicStyle.css' rel='stylesheet' type='text/css' />");

define('GRID_CSS', "<link href='css/bootstrap-grid.min.css' rel='stylesheet' type='text/css' />");

require ('../functions/commonDefines.php');

require (CLASSES . 'PDOdata.php');
require (DB_TABLES . 'HouseRS.php');
require (CLASSES . 'HTML_Controls.php');
require (FUNCTIONS . 'commonFunc.php');
require (CLASSES . 'config'. DS . 'Lite.php');
require (SEC . 'sessionClass.php');
require (CLASSES . 'alertMessage.php');
require (CLASSES . 'Exception_hk/Hk_Exception.php');
require (SEC . 'SecurityComponent.php');
require (SEC . 'ScriptAuthClass.php');
require (CLASSES . 'SysConst.php');
require (SEC . 'webInit.php');
require (SEC . 'UserClass.php');
