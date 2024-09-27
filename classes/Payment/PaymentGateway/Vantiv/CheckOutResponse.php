<?php

namespace HHK\Payment\PaymentGateway\Vantiv;

use HHK\HTMLControls\HTMLTable;
use HHK\Payment\PaymentGateway\CreditPayments\AbstractCreditPayments;
use HHK\Payment\PaymentResponse\AbstractCreditResponse;
use HHK\SysConst\{MpStatusValues, PayType, PaymentMethod, PaymentStatusCode};

/**
 * CheckoutResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class CheckOutResponse extends AbstractCreditResponse {

    /**
     * Summary of __construct
     * @param mixed $verifyCkOutResponse
     * @param int $idPayor
     * @param int $idGroup
     * @param string $invoiceNumber
     * @param string $payNotes
     * @param string $payDate
     */
    function __construct($verifyCkOutResponse, $idPayor, $idGroup, $invoiceNumber, $payNotes, $payDate) {
        $this->response = $verifyCkOutResponse;
        $this->paymentType = PayType::Charge;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->invoiceNumber = $invoiceNumber;
        $this->expDate = $verifyCkOutResponse->getExpDate();
        $this->cardNum = str_ireplace('x', '', $verifyCkOutResponse->getMaskedAccount());
        $this->cardType = $verifyCkOutResponse->getCardType();
        $this->cardName = $verifyCkOutResponse->getCardHolderName();
        $this->amount = $verifyCkOutResponse->getAuthorizedAmount();
        $this->payNotes = $payNotes;
        $this->setPaymentDate($payDate);
    }

    /**
     * Summary of getPaymentMethod
     * @return int
     */
    public function getPaymentMethod() {
        return PaymentMethod::Charge;
    }

    /**
     * Summary of getStatus
     * @return string
     */
    public function getStatus() {

        switch ($this->response->getStatus()) {

            case MpStatusValues::Approved:
                $pr = AbstractCreditPayments::STATUS_APPROVED;
                break;

            case MpStatusValues::Declined:
                $pr = AbstractCreditPayments::STATUS_DECLINED;
                break;

            default:
                $pr = AbstractCreditPayments::STATUS_DECLINED;
        }

        return $pr;
    }

    /**
     * Summary of receiptMarkup
     * @param \PDO $dbh
     * @param mixed $tbl
     * @return void
     */
    public function receiptMarkup(\PDO $dbh, &$tbl) {

        $tbl->addBodyTr(HTMLTable::makeTd("Credit Card:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($this->getAmount(), 2)));
        $tbl->addBodyTr(HTMLTable::makeTd($this->cardType . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd("xxxxx...". $this->cardNum));

        if ($this->cardName != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Card Holder: ", array('class'=>'tdlabel')) . HTMLTable::makeTd(htmlentities($this->cardName, ENT_QUOTES)));
        }

        if ($this->response->getAuthCode() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Authorization Code: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getAuthCode() . ' ('.ucfirst($this->response->getMerchant()). ')', array('style'=>'font-size:.8em;')));
        }

        if ($this->response->getResponseMessage() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Response Message: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getResponseMessage() . ($this->response->getResponseCode() == '' ? '' :  '  (Code: ' . $this->response->getResponseCode() . ")"), array('style'=>'font-size:.8em;')));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Sign: ", array('class'=>'tdlabel')) . HTMLTable::makeTd('', array('style'=>'height:35px; width:250px; border: solid 1px gray;')));

    }

    public function getPaymentStatusCode() {

        if ($this->getStatus() == AbstractCreditPayments::STATUS_APPROVED) {
            return PaymentStatusCode::Paid;
        } else {
            return PaymentStatusCode::Declined;
        }
    }

}
?>