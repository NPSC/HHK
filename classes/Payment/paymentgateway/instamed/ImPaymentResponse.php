<?php

namespace HHK\Payment\PaymentGateway\Instamed;

use HHK\Payment\PaymentGateway\CreditPayments\AbstractCreditPayments;
use HHK\Payment\PaymentResponse\AbstractCreditResponse;
use HHK\Payment\GatewayResponse\GatewayResponseInterface;
use HHK\SysConst\{PayType, PaymentMethod};
use HHK\HTMLControls\{HTMLTable};

/**
 * ImPaymentResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


class ImPaymentResponse extends AbstractCreditResponse {


    function __construct(GatewayResponseInterface $vcr, $idPayor, $idGroup, $invoiceNumber, $payNotes, $payDate, $isPartialApprovalAmount, $paymentStatusCode = '') {
        $this->response = $vcr;
        $this->paymentType = PayType::Charge;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->invoiceNumber = $invoiceNumber;
        $this->payNotes = $payNotes;
        $this->amount = $vcr->getRequestAmount();
        $this->setPaymentStatusCode($paymentStatusCode);
        
        if ($isPartialApprovalAmount) {
            $this->setPartialPayment(TRUE);
            $this->amount = $vcr->getPartialPaymentAmount();
        } else {
            $this->setPartialPayment(FALSE);
        }
        
        $this->setPaymentDate($payDate);
    }

    public function getPaymentMethod() {
        return PaymentMethod::Charge;
    }

    public function getStatus() {

        $status = '';

        if ($this->response->getTransactionStatus() == InstamedGateway::DECLINE || $this->response->getResponseCode() == '001') {

            $status = AbstractCreditPayments::STATUS_DECLINED;

        } else if ($this->response->getTransactionStatus() == InstamedGateway::CAPTURED_APPROVED
                || $this->response->getTransactionStatus() == InstaMedGateway::SAVE_ON_FILE_APPROVAL
        		|| $this->response->getResponseCode() == '000') {

        		    $status = AbstractCreditPayments::STATUS_APPROVED;

        } else {
            $status = AbstractCreditPayments::STATUS_ERROR;
        }

        return $status;
    }

    public function getErrorMessage() {
        return $this->response->getErrorMessage();
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

        if ($this->response->getEMVApplicationIdentifier() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("AID: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getEMVApplicationIdentifier(), array('style'=>'font-size:.8em;')));
        }
        if ($this->response->getEMVTerminalVerificationResults() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("TVR: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getEMVTerminalVerificationResults(), array('style'=>'font-size:.8em;')));
        }
        if ($this->response->getEMVIssuerApplicationData() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("IAD: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getEMVIssuerApplicationData(), array('style'=>'font-size:.8em;')));
        }
        if ($this->response->getEMVTransactionStatusInformation() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("TSI: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getEMVTransactionStatusInformation(), array('style'=>'font-size:.8em;')));
        }
        if ($this->response->getEMVApplicationResponseCode() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("ARC: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getEMVApplicationResponseCode(), array('style'=>'font-size:.8em;')));
        }

        if ($this->response->getAuthorizationText() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($this->response->getAuthorizationText(), array('colspan'=>2, 'style'=>'font-size:.8em;')));
        }

        if ($this->getStatus() != AbstractCreditPayments::STATUS_DECLINED && $this->response->SignatureRequired() == 1) {
            $tbl->addBodyTr(HTMLTable::makeTd("Sign: ", array('class'=>'tdlabel')) . HTMLTable::makeTd('', array('style'=>'height:35px; width:310px; border: solid 1px gray;')));
        }

    }

}
?>