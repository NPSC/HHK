<?php

namespace HHK\Payment\PaymentGateway\Deluxe\Response;

use HHK\HTMLControls\HTMLTable;
use HHK\Payment\PaymentGateway\CreditPayments\AbstractCreditPayments;
use HHK\Payment\PaymentResponse\AbstractCreditResponse;
use HHK\Payment\GatewayResponse\GatewayResponseInterface;
use HHK\SysConst\PaymentMethod;
use HHK\SysConst\PaymentStatusCode;
use HHK\Tables\Payment\PaymentRS;
use HHK\Tables\PaymentGW\Guest_TokenRS;

/**
 * VoidCreditResponse.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2024 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class VoidCreditResponse extends AbstractCreditResponse {

    protected $tkRs;

    function __construct(GatewayResponseInterface $vcr, $idPayor, $idGroup, PaymentRS $payRS, Guest_TokenRS $tkRs) {
        $this->response = $vcr;
        $this->tkRs = $tkRs;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->amount = $payRS->Amount->getStoredVal();
        $this->invoiceNumber = $vcr->getInvoiceNumber();
        $this->idGuestToken = $tkRs->idGuest_token->getStoredVal();

        switch($this->getStatus()){
            case AbstractCreditPayments::STATUS_APPROVED:
                $this->setPaymentStatusCode(PaymentStatusCode::VoidSale);
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

            case '10': //insufficient funds
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
        $tbl->addBodyTr(HTMLTable::makeTd($this->tkRs->CardType->getStoredVal() . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd('xxx...' . $this->tkRs->MaskedAccount->getStoredVal()));

        if ($this->tkRs->CardHolderName->getStoredVal() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Card Holder: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($this->tkRs->CardHolderName->getStoredVal()));
        }

        if ($this->response->getAuthCode() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Authorization Code: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getAuthCode(), array('style'=>'font-size:.8em;')));
        }

        if ($this->response->getMessage() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Response Message: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getMessage() . ($this->response->getResponseCode() == '' ? '' :  '  (Code: ' . $this->response->getResponseCode() . ")"), array('style'=>'font-size:.8em;')));
        }

        if ($this->response->getAuthorizationText() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($this->response->getAuthorizationText(), array('colspan'=>2, 'style'=>'font-size:.8em;')));
        }

    }

}