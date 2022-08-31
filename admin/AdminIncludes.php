<?php
/**
 * AdminIncludes.php
 *
-- @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
-- @copyright 2010-2017 <nonprofitsoftwarecorp.org>
-- @license   MIT
-- @link      https://github.com/NPSC/HHK
 */

define('JSV', '?eG=92');

define('DEFAULT_CSS', '<link href="css/default.css' . JSV . '" rel="stylesheet" type="text/css" />');
define('ROOT_CSS', "<link href='../css/root.css" . JSV . "' rel='stylesheet' type='text/css' />");
define('GRID_CSS', "<link href='css/bootstrap-grid.min.css'" . JSV . " rel='stylesheet' type='text/css' />");
define('NAVBAR_CSS', "<link href='../css/bootstrapNavbar.css" . JSV . "' rel='stylesheet' type='text/css' />");

/*
 * includes
 */
require ('../functions/commonDefines.php');
require (THIRD_PARTY . '/autoload.php');
require (FUNCTIONS . 'commonFunc.php');
