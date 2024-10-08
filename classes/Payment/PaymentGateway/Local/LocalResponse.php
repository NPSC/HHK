<?php

namespace HHK\Payment\PaymentGateway\Local;

use HHK\HTMLControls\HTMLTable;
use HHK\Payment\PaymentGateway\CreditPayments\AbstractCreditPayments;
use HHK\Payment\PaymentResponse\AbstractCreditResponse;
use HHK\SysConst\PaymentMethod;

/**
 * LocalResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of LocalResponse
 *
 * @author Eric
 */
class LocalResponse extends AbstractCreditResponse {


    /**
     * Summary of __construct
     * @param mixed $gwResp
     * @param mixed $idPayor
     * @param mixed $idRegistration
     * @param mixed $idToken
     * @param mixed $paymentStatusCode
     */
    function __construct( $gwResp, $idPayor, $idRegistration, $idToken, $paymentStatusCode = '') {

        $this->response = $gwResp;
        $this->idPayor = $idPayor;
        $this->setIdToken($idToken);
        $this->idRegistration = $idRegistration;
        $this->invoiceNumber = $gwResp->getInvoiceNumber();
        $this->amount = $gwResp->getAuthorizedAmount();
        $this->setPaymentStatusCode($paymentStatusCode);

    }

    /**
     * Summary of getStatus
     * @return string
     */
    public function getStatus() {
        return AbstractCreditPayments::STATUS_APPROVED;
    }

    /**
     * Summary of receiptMarkup: adds receipt markup to $tbl
     * @param \PDO $dbh
     * @param mixed $tbl
     * @return void
     */
    public function receiptMarkup(\PDO $dbh, &$tbl) {

        $tbl->addBodyTr(HTMLTable::makeTd("Credit Card:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($this->getAmount(), 2)));
        $tbl->addBodyTr(HTMLTable::makeTd($this->response->getCardType() . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd("xxx...". $this->response->getMaskedAccount()));

        if ($this->response->getCardHolderName() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Card Holder: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($this->response->getCardHolderName()));
        }

    }

    /**
     * Summary of getPaymentMethod
     * @return int
     */
    public function getPaymentMethod() {
        return PaymentMethod::Charge;
    }

}
