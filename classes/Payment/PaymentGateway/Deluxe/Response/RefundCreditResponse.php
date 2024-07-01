<?php

namespace HHK\Payment\PaymentGateway\Deluxe\Response;

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
        return array('error'=>'Receipts not available.');
    }

}
?>