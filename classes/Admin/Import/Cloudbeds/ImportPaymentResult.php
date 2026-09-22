<?php
namespace HHK\Admin\Import\Cloudbeds;

use HHK\Payment\PaymentResult\PaymentResult;

/**
 * PaymentResult for historical payments being imported: identical to PaymentResult (payment-invoice record, receipt)
 * except that it never emails a receipt, so importing old payments doesn't send guests receipts for them.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class ImportPaymentResult extends PaymentResult {

    public function emailReceipt(\PDO $dbh) {
        return [];
    }
}
