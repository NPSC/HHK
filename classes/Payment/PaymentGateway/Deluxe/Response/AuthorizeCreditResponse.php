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

        switch($this->getStatus()){
            case AbstractCreditPayments::STATUS_APPROVED:
                $this->setPaymentStatusCode(PaymentStatusCode::Paid);
                break;
            case AbstractCreditPayments::STATUS_DECLINED:
                $this->setPaymentStatusCode(PaymentStatusCode::Declined);
                break;
        }

    }

    public function getPaymentMethod() {
        return PaymentMethod::Charge;
    }

    public function getStatus() {

        return $this->response->getResponseCode() == '0' ? AbstractCreditPayments::STATUS_APPROVED : AbstractCreditPayments::STATUS_DECLINED;
        
    }

    public function receiptMarkup(\PDO $dbh, &$tbl) {
        return array('error'=>'Receipts not available.');
    }

}
?>