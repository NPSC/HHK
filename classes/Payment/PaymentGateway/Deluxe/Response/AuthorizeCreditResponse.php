<?php

namespace HHK\Payment\PaymentGateway\Deluxe\Response;

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

class AuthorizeCreditResponse extends AbstractCreditResponse {

    public $idToken;
    public $merchant;
    public $expDate;


    function __construct(GatewayResponseInterface $vcr, $idPayor, $idGroup) {
        $this->response = $vcr;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->idToken = $vcr->getToken();
        $this->merchant = $vcr->getMerchant();
        $this->expDate = $vcr->getExpDate();

    }

    public function getPaymentMethod() {
        return PaymentMethod::Charge;
    }

    public function getPaymentStatusCode() {
        return PaymentStatusCode::Paid;
    }

    public function getStatus() {

        switch ($this->response->getResponseCode()) {

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