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
require('../../functions/commonDefines.php');

define('INVOICE_CSS', "<link href='css/invoice.css" . JSV . "' rel='stylesheet' type='text/css' />");
define('STATEMENT_CSS', "<link href='css/statement.css" . JSV . "' rel='stylesheet' type='text/css' />");
define('CUSTOM_REGFORM_CSS', "<link href='css/customRegForm.css" . JSV . "' rel='stylesheet' type='text/css' />");

// CSS bundles for PDFs generated via mPDF (Receipt/Statement/Invoice), which fetch
// their own <link> stylesheets rather than loading a page in a browser.
define('RECEIPT_CSS', \HHK\Vite\Vite::cssLink('resources/css/pdf/receipt.css'));
define('STATEMENT_PDF_CSS', \HHK\Vite\Vite::cssLink('resources/css/pdf/statement.css'));
define('INVOICE_PDF_CSS', \HHK\Vite\Vite::cssLink('resources/css/pdf/invoice.css'));

/* common mins */
define('PAYMENT_JS', "js/payments-min.js" . JSV);
/* end mins */

/* page mins */
define('RESV_JS', "js/resv.js" . JSV);
define('INVOICE_JS', "js/invoice.js" . JSV);
define('RESERVE_JS', 'js/reserve.js' . JSV);
define('CHECKIN_JS', 'js/checkin.js' . JSV);
define('CHECKINGIN_JS', 'js/checkingIn.js' . JSV);
define('RESCBUILDER_JS', 'js/rescBuilder.js' . JSV);
define('MISSINGDEMOG_JS', 'js/missingDemog.js' . JSV);
define('GUESTTRANSFER_JS', 'js/guestTransfer.js' . JSV);
define('INS_EMBED_JS', '<script src="https://cdn.instamed.com/Content/Js/embed.js" data-displaymode="incontext" data-hostname="https://online.instamed.com/providers" data-mobiledisplaymode="incontext"></script>');
define('DELUXE_SANDBOX_EMBED_JS', '<script src="https://hostedform2.deluxe.com/V2/deluxe.js"></script>');
define('DELUXE_EMBED_JS', '<script src="https://hostedpaymentform.deluxe.com/v2/deluxe.js"></script>');
define('GUEST_REFERRAL_JS', '<script src="js/guestReferral.js' . JSV . '"></script>');
define('REFERRAL_FORM_JS', 'js/referralForm.js' . JSV);
define('TOPAZ_SIGWEB_JS', 'js/SigWebTablet.js' . JSV);
define('VISIT_INTERVAL_JS', "js/visitInterval.js" . JSV);
define('HOUSEKEEPING_JS', 'js/housekeeping.js' . JSV);

define('CSSVARS', "<link href='ws_resc.php?cmd=getCssVars' rel='stylesheet' type='text/css' />");
