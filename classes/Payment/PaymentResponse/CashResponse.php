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


    protected float $amountTendered;

    function __construct($amount, $idPayor, $invoiceNumber, $payNote = '', float $amountTendered = 0) {

        $this->paymentType = PayType::Cash;
        $this->idPayor = $idPayor;
        $this->amount = $amount;
        $this->invoiceNumber = $invoiceNumber;
        $this->payNotes = $payNote;
        $this->amountTendered = $amountTendered;

    }

    public function getPaymentMethod() {
        return PaymentMethod::Cash;
    }

    public function getStatus() {
        return AbstractCreditPayments::STATUS_APPROVED;
    }

    public function getAmountTendered(){
        return $this->amountTendered;
    }

    /**
     * Summary of receiptMarkup
     * @param \PDO $dbh
     * @param mixed $tbl
     * @return void
     */
    public function receiptMarkup(\PDO $dbh, &$tbl) {

        if ($this->getAmount() != 0) {
            //include amount tendered for sale receipts
            if ($this->getAmountTendered() >= $this->getAmount() && $this->refund === false) {
                $change = $this->getAmountTendered() - $this->getAmount();
                $tbl->addBodyTr(HTMLTable::makeTd(Labels::getString('Reciept', 'cashTendered', 'Cash Tendered') . ":", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format(abs($this->getAmountTendered()), 2)));
                $tbl->addBodyTr(HTMLTable::makeTd(Labels::getString('Receipt', 'changeGiven', 'Change') . ":", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format(abs($change), 2)));
            }

            $tbl->addBodyTr(HTMLTable::makeTd("Cash Paid:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format(abs($this->getAmount()), 2)));
        }

    }

}
?>