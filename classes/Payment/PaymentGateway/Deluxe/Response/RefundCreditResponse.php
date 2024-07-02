<?php

namespace HHK\Payment\PaymentGateway\Deluxe\Response;

use HHK\HTMLControls\HTMLTable;
use HHK\Payment\PaymentGateway\CreditPayments\AbstractCreditPayments;
use HHK\Payment\PaymentResponse\AbstractCreditResponse;
use HHK\Payment\GatewayResponse\GatewayResponseInterface;
use HHK\SysConst\PaymentMethod;
use HHK\SysConst\PaymentStatusCode;
use HHK\Tables\Payment\PaymentRS;

/**
 * RefundCreditResponse.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2024 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class RefundCreditResponse extends AbstractCreditResponse {

    function __construct(GatewayResponseInterface $vcr, $idPayor, $idGroup, $amount) {
        $this->response = $vcr;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->amount = $amount;
        $this->invoiceNumber = $vcr->getInvoiceNumber();

    }

    public function getPaymentMethod() {
        return PaymentMethod::Charge;
    }

    public function getPaymentStatusCode() {
        return PaymentStatusCode::Retrn;
    }

    public function getStatus() {

        switch ($this->response->getStatus()) {

            case '0':
                $status = AbstractCreditPayments::STATUS_APPROVED;
                break;

            case '1434'://decline
                $status = AbstractCreditPayments::STATUS_DECLINED;
                break;
            case '1446'://expired card
                $status = AbstractCreditPayments::STATUS_DECLINED;
                break;

            case '005':
                $status = AbstractCreditPayments::STATUS_DECLINED;
                break;

            case '051':
                $status = AbstractCreditPayments::STATUS_DECLINED;
                break;

            case '063':
                $status = AbstractCreditPayments::STATUS_DECLINED;
                break;

            default:
                $status = AbstractCreditPayments::STATUS_ERROR;

        }

        return $status;
    }

    public function receiptMarkup(\PDO $dbh, &$tbl) {

        if ($this->isPartialPayment()) {
            $tbl->addBodyTr(HTMLTable::makeTd("Partial Payment", array('colspan'=>2, 'style'=>'font-weight:bold;align:center;')));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Credit Card Total:", array('class'=>'tdlabel')) . HTMLTable::makeTd('$'.number_format($this->getAmount(), 2)));
        $tbl->addBodyTr(HTMLTable::makeTd($this->response->getCardType() . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd('xxx...' . $this->response->getMaskedAccount()));

        if ($this->response->getCardHolderName() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Card Holder: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($this->response->getCardHolderName()));
        }

        if ($this->response->getAuthCode() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Authorization Code: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getAuthCode(), array('style'=>'font-size:.8em;')));
        }

        if ($this->response->getResponseMessage() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Response Message: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getResponseMessage() . ($this->response->getResponseCode() == '' ? '' :  '  (Code: ' . $this->response->getResponseCode() . ")"), array('style'=>'font-size:.8em;')));
        }

    }

}
?>