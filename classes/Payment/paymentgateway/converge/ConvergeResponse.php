<?php
/**
 * ConvergeResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


class ConvergePaymentResponse extends PaymentResponse {


    function __construct(iGatewayResponse $vcr, $idPayor, $idGroup, $invoiceNumber, $idToken, $payNotes, $isPartialApprovalAmount = FALSE) {
        $this->response = $vcr;
        $this->paymentType = PayType::Charge;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->invoiceNumber = $invoiceNumber;
        $this->payNotes = $payNotes;
        $this->amount = $vcr->getAuthorizedAmount();
        $this->idToken = $vcr->getToken();

        if ($isPartialApprovalAmount) {
            $this->setPartialPayment(TRUE);
        } else {
            $this->setPartialPayment(FALSE);
        }
    }

    public function getStatus() {

        $status = '';

        If ($his->response->getErrorCode() != '') {
            // Error
            $status = CreditPayments::STATUS_ERROR;
        } else if ($this->response->getResponseCode() == 0) {
            //Approved
            $status = CreditPayments::STATUS_APPROVED;
        } else {
            // Declined
            $status = CreditPayments::STATUS_DECLINED;
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
            $tbl->addBodyTr(HTMLTable::makeTd($this->response->getAuthorizationText(), array('colspan'=>2)));
        }

        if ($this->getStatus() != CreditPayments::STATUS_DECLINED) {
            $tbl->addBodyTr(HTMLTable::makeTd("Sign: ", array('class'=>'tdlabel')) . HTMLTable::makeTd('', array('style'=>'height:35px; width:310px; border: solid 1px gray;')));
        }

    }

}

class ConvergeCofResponse extends PaymentResponse {

    public $idToken;


    function __construct(iGatewayResponse $vcr, $idPayor, $idGroup) {
        $this->response = $vcr;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->idToken = $vcr->getToken();

    }

    public function getStatus() {

        $status = '';

        If ($his->response->getErrorCode() != '') {
            // Error
            $status = CreditPayments::STATUS_ERROR;
        } else if ($this->response->getResponseCode() == 0) {
            //Approved
            $status = CreditPayments::STATUS_APPROVED;
        } else {
            // Declined
            $status = CreditPayments::STATUS_DECLINED;
        }

        return $status;
    }

    public function getErrorMessage() {
        return $this->response->getErrorMessage();
    }

    public function receiptMarkup(\PDO $dbh, &$tbl) {
        return array('error'=>'Receipts not available.');
    }

}

