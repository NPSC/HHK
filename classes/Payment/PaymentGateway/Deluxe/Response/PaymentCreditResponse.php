<?php

namespace HHK\Payment\PaymentGateway\Deluxe\Response;

use HHK\HTMLControls\HTMLTable;
use HHK\Payment\CreditToken;
use HHK\Payment\PaymentGateway\CreditPayments\AbstractCreditPayments;
use HHK\Payment\PaymentResponse\AbstractCreditResponse;
use HHK\Payment\GatewayResponse\GatewayResponseInterface;
use HHK\SysConst\PaymentMethod;
use HHK\SysConst\PaymentStatusCode;

/**
 * PaymentCreditResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class PaymentCreditResponse extends AbstractCreditResponse {

    public $idToken;


    function __construct(GatewayResponseInterface $vcr, $idGuestToken, $idPayor, $idGroup, $payNotes = "") {
        $this->response = $vcr;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->idToken = $vcr->getToken();
        $this->idGuestToken = $idGuestToken;
        $this->amount = $vcr->getAuthorizedAmount();
        $this->invoiceNumber = $vcr->getInvoiceNumber();
        $this->payNotes = $payNotes;
        
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

        return $this->response->getStatus() == '0' ? AbstractCreditPayments::STATUS_APPROVED : AbstractCreditPayments::STATUS_DECLINED;

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

        if ($this->response->getMessage() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Response Message: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getMessage() . ($this->response->getResponseCode() == '' ? '' :  '  (Code: ' . $this->response->getResponseCode() . ")"), array('style'=>'font-size:.8em;')));
        }

    }

}