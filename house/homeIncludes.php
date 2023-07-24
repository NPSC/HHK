<?php
/**
 * homeIncludes.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Includes
 */
require ('../functions/commonDefines.php');

define('HOUSE_CSS', "<link href='css/house.css" . JSV . "' rel='stylesheet' type='text/css' />");
define('ROOT_CSS', "<link href='../css/root.css" . JSV . "' rel='stylesheet' type='text/css' />");

define('RESV_MANAGER_JS', 'js/resvManager-min.js' . JSV);
define('PAYMENT_JS', "js/payments-min.js" . JSV);
define('VISIT_DIALOG_JS', "js/visitDialogmin.js" . JSV);
define('INCIDENT_REP_JS', 'js/incidentReports.min.js' . JSV);
define('DOC_UPLOAD_JS', 'js/documentUpload.min.js' . JSV);
define('GUESTLOAD_JS', 'js/guestload-min.js' . JSV);
define('REGISTER_JS', 'js/register-min.js' . JSV);
define('RESV_JS', "js/resv.js" . JSV);
define('INVOICE_JS', "js/invoice.js" . JSV);
define('REPORTFIELDSETS_JS', "js/reportfieldSets.js" . JSV);
define('REFERRAL_VIEWER_JS', "js/referralViewer.js" . JSV);
define('FORMBUILDER_JS', "js/formBuilder.js" . JSV);
define('RESERVE_JS', 'js/reserve.js' . JSV);
define('CHECKIN_JS', 'js/checkin.js' . JSV);
define('CHECKINGIN_JS', 'js/checkingIn.js' . JSV);
define('SERIALIZEJSON', 'js/jquery.serializejson.js' . JSV);
define('RESCBUILDER_JS', 'js/rescBuilder.js' . JSV);
define('MISSINGDEMOG_JS', 'js/missingDemog.js' . JSV);
define('GUESTTRANSFER_JS', 'js/guestTransfer.js' . JSV);
define('INS_EMBED_JS', '<script src="https://instamedprd.cachefly.net/Content/Js/embed.js" data-displaymode="embedded" data-hostname="https://online.instamed.com/providers" data-mobiledisplaymode="embedded"></script>');
define('GUEST_REFERRAL_JS', '<script src="js/guestReferral.js' . JSV. '"></script>');
define('FULLCALENDAR_JS', "../js/fullcalendar5.11.0.min.js" . JSV);
define('TOPAZ_SIGWEB_JS', 'js/SigWebTablet.js' . JSV);
define('REG_FORM_ESIGN_JS', 'js/regFormEsign.js' . JSV);

define('FULLCALENDAR_CSS', '<link href="css/fullcalendar5.11.0.min.css' . JSV . '"  rel="stylesheet" type="text/css" />');
define('GRID_CSS', "<link href='css/bootstrap-grid.min.css" . JSV . "' rel='stylesheet' type='text/css' />");
define('BOOTSTRAP_CSS', "<link href='css/bootstrap.min.css" . JSV . "' rel='stylesheet' type='text/css' /><link href='css/bootstrap-print-fix.css" . JSV . "' rel='stylesheet' type='text/css' />");
define('NAVBAR_CSS', "<link href='../css/bootstrapNavbar.css" . JSV . "' rel='stylesheet' type='text/css' />");
define('CSSVARS', "<link href='ws_resc.php?cmd=getCssVars' rel='stylesheet' type='text/css' />");