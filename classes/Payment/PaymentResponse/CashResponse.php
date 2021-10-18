<?php

namespace HHK\Payment\PaymentResponse;

use HHK\Payment\PaymentGateway\CreditPayments\AbstractCreditPayments;
use HHK\SysConst\{PaymentMethod, PayType};
use HHK\HTMLControls\HTMLTable;

/**
 * CashResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class CashResponse extends AbstractPaymentResponse {

    function __construct($amount, $idPayor, $invoiceNumber, $payNote = '') {

        $this->paymentType = PayType::Cash;
        $this->idPayor = $idPayor;
        $this->amount = $amount;
        $this->invoiceNumber = $invoiceNumber;
        $this->payNotes = $payNote;

    }

    public function getPaymentMethod() {
        return PaymentMethod::Cash;
    }

    public function getStatus() {
        return AbstractCreditPayments::STATUS_APPROVED;
    }

    public function receiptMarkup(\PDO $dbh, &$tbl) {

        if ($this->getAmount() != 0) {
            $tbl->addBodyTr(HTMLTable::makeTd("Cash Tendered:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format(abs($this->getAmount()), 2)));
        }
    }

}
?>