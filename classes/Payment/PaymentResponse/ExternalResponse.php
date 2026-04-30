<?php

namespace HHK\Payment\PaymentResponse;

use HHK\Common;
use HHK\SysConst\{PayType, PaymentMethod};
use HHK\HTMLControls\HTMLTable;
use PDO;

/**
 * ExternalResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ExternalResponse extends CheckResponse {

    protected $externalPaymentTypeCode;

    protected $externalPaymentTypeTitle;

    function __construct($amount, $idPayor, $invoiceNumber, $externalId = '', $payNotes = '', $externalPaymentTypeCode = PayType::External, $externalPaymentTypeTitle = '') {

        parent::__construct($amount, $idPayor, $invoiceNumber, $externalId, $payNotes);

        $this->paymentType = PayType::External;
        $this->externalPaymentTypeCode = $externalPaymentTypeCode == '' ? PayType::External : $externalPaymentTypeCode;
        $this->externalPaymentTypeTitle = $externalPaymentTypeTitle;
    }

    /**
     * Summary of getPaymentMethod
     * @return int
     */
    public function getPaymentMethod() {
        return PaymentMethod::External;
    }

    public function getExternalPaymentTypeCode() {
        return $this->externalPaymentTypeCode;
    }

    public function getExternalPaymentTypeTitle(?PDO $dbh) {

        if ($this->externalPaymentTypeTitle != '') {
            return $this->externalPaymentTypeTitle;
        }

        if ($dbh instanceof PDO && $this->externalPaymentTypeCode != '') {
            $payTypes = Common::readGenLookupsPDO($dbh, 'Pay_Type');

            if (isset($payTypes[$this->externalPaymentTypeCode])) {
                return $payTypes[$this->externalPaymentTypeCode][1];
            }
        }

        return 'External';
    }

    /**
     * Summary of receiptMarkup
     * @param PDO $dbh
     * @param mixed $tbl
     * @return void
     */
    public function receiptMarkup(PDO $dbh, &$tbl): void {

        $tbl->addBodyTr(HTMLTable::makeTd($this->getExternalPaymentTypeTitle($dbh) . ":", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($this->getAmount(), 2)));
        $tbl->addBodyTr(HTMLTable::makeTd($this->getExternalPaymentTypeTitle($dbh) . ' ID:', array('class'=>'tdlabel')) . HTMLTable::makeTd($this->getCheckNumber()));

    }

}
?>
