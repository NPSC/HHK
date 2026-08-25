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

define('INS_EMBED_JS', '<script src="https://cdn.instamed.com/Content/Js/embed.js" data-displaymode="incontext" data-hostname="https://online.instamed.com/providers" data-mobiledisplaymode="incontext"></script>');
define('DELUXE_SANDBOX_EMBED_JS', '<script src="https://hostedform2.deluxe.com/V2/deluxe.js"></script>');
define('DELUXE_EMBED_JS', '<script src="https://hostedpaymentform.deluxe.com/v2/deluxe.js"></script>');

define('CSSVARS', "<link href='ws_resc.php?cmd=getCssVars' rel='stylesheet' type='text/css' />");
