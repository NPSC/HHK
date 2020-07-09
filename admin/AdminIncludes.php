<?php
/**
 * AdminIncludes.php
 *
-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2017 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK
 */

define('JSV', '?eG=90');

define('DEFAULT_CSS', '<link href="css/default.css' . JSV . '" rel="stylesheet" type="text/css" />');


/*
 * includes
 */
require ('../functions/commonDefines.php');

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
require (CLASSES . 'SysConst.php');
require (SEC . 'webInit.php');
require (SEC . 'UserClass.php');