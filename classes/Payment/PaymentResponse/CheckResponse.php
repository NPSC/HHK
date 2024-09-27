<?php

namespace HHK\Payment\PaymentResponse;

use HHK\Payment\PaymentGateway\CreditPayments\AbstractCreditPayments;
use HHK\SysConst\{PayType, PaymentMethod};
use HHK\HTMLControls\{HTMLTable};
use HHK\Tables\Payment\PaymentInfoCheckRS;
use HHK\Tables\EditRS;

/**
 * CheckResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class CheckResponse extends AbstractPaymentResponse {

    /**
     * Summary of idInfoCheck
     * @var mixed
     */
    public $idInfoCheck;

    /**
     * Summary of checkNumber
     * @var string
     */
    protected $checkNumber;

    /**
     * Summary of __construct
     * @param float $amount
     * @param int $idPayor
     * @param string $invoiceNumber
     * @param string $checkNumber
     * @param string $payNotes
     */
    function __construct($amount, $idPayor, $invoiceNumber, $checkNumber = '', $payNotes = '') {

        $this->paymentType = PayType::Check;
        $this->idPayor = $idPayor;
        $this->amount = $amount;
        $this->invoiceNumber = $invoiceNumber;
        $this->checkNumber = $checkNumber;
        $this->payNotes = $payNotes;

    }

    public function getPaymentMethod() {
        return PaymentMethod::Check;
    }

    public function getStatus() {
        return AbstractCreditPayments::STATUS_APPROVED;
    }

    /**
     * Summary of receiptMarkup
     * @param \PDO $dbh
     * @param mixed $tbl
     * @return void
     */
    public function receiptMarkup(\PDO $dbh, &$tbl) {

        $tbl->addBodyTr(HTMLTable::makeTd("Check:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format(abs($this->getAmount()), 2)));
        $tbl->addBodyTr(HTMLTable::makeTd('Check Number:', array('class'=>'tdlabel')) . HTMLTable::makeTd($this->getCheckNumber()));
    }

    /**
     * Summary of recordInfoCheck
     * @param \PDO $dbh
     * @return void
     */
    public function recordInfoCheck(\PDO $dbh) {

        if ($this->getIdPayment() > 0) {

            // Check table
            $ckRs = new PaymentInfoCheckRS();
            $ckRs->Check_Date->setNewVal($this->getPaymentDate());
            $ckRs->Check_Number->setNewVal($this->getCheckNumber());
            $ckRs->idPayment->setNewVal($this->getIdPayment());

            $this->idInfoCheck = EditRS::insert($dbh, $ckRs);
        }

    }

    /**
     * Summary of getCheckNumber
     * @return mixed
     */
    public function getCheckNumber() {
        return $this->checkNumber;
    }

}
?>