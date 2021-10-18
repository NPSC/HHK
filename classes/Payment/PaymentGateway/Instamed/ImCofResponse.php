<?php

namespace HHK\Payment\PaymentGateway\Instamed;

use HHK\Payment\PaymentGateway\CreditPayments\AbstractCreditPayments;
use HHK\Payment\PaymentResponse\AbstractCreditResponse;
use HHK\Payment\GatewayResponse\GatewayResponseInterface;
use HHK\SysConst\PaymentMethod;
use HHK\SysConst\PaymentStatusCode;

/**
 * ImCofResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ImCofResponse extends AbstractCreditResponse {

    public $idToken;


    function __construct(GatewayResponseInterface $vcr, $idPayor, $idGroup) {
        $this->response = $vcr;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->idToken = $vcr->getToken();

    }

    public function getPaymentMethod() {
        return PaymentMethod::Charge;
    }

    public function getPaymentStatusCode() {
        return PaymentStatusCode::Paid;
    }

    public function getStatus() {

        switch ($this->response->getResponseCode()) {

            case '000':
                $status = AbstractCreditPayments::STATUS_APPROVED;
                break;

            case '001':
                $status = AbstractCreditPayments::STATUS_DECLINED;
                break;

            case '003':
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