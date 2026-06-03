<?php

namespace HHK\Payment\PaymentResponse;

use HHK\SysConst\PaymentMethod;
use HHK\HTMLControls\HTMLTable;
use HHK\SysConst\PayType;
use Override;

/**
 * TransferResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class TransferResponse extends CheckResponse {

    public function __construct($amount, $idPayor, $invoiceNumber, $checkNumber = '', $payNotes = '')
    {
        parent::__construct($amount, $idPayor, $invoiceNumber, $checkNumber, $payNotes);
        $this->paymentType = PayType::Transfer;
    }

    /**
     * Summary of getPaymentMethod
     * @return int
     */
    public function getPaymentMethod() {
        return PaymentMethod::Transfer;
    }

    /**
     * Summary of receiptMarkup
     * @param \PDO $dbh
     * @param mixed $tbl
     * @return void
     */
    public function receiptMarkup(\PDO $dbh, &$tbl) {

        $tbl->addBodyTr(HTMLTable::makeTd($this->getPaymentTypeTitle($dbh) . ":", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($this->getAmount(), 2)));
        $tbl->addBodyTr(HTMLTable::makeTd($this->getPaymentTypeTitle($dbh) . " Acct:", array('class'=>'tdlabel')) . HTMLTable::makeTd($this->getCheckNumber()));

    }

}
?>