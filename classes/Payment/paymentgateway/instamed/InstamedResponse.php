<?php
/**
 * InstamedResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


class ImPaymentResponse extends PaymentResponse {


    function __construct(iGatewayResponse $vcr, $idPayor, $idGroup, $invoiceNumber, $payNotes, $isPartialApprovalAmount = FALSE) {
        $this->response = $vcr;
        $this->paymentType = PayType::Charge;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->invoiceNumber = $invoiceNumber;
        $this->payNotes = $payNotes;
        $this->amount = $vcr->getRequestAmount();

        if ($isPartialApprovalAmount) {
            $this->setPartialPayment(TRUE);
            $this->amount = $vcr->getPartialPaymentAmount();
        } else {
            $this->setPartialPayment(FALSE);
        }
    }

    public function getStatus() {

        $status = '';

        if ($this->response->getTransactionStatus() == InstamedGateway::DECLINE || $this->response->getResponseCode() == '001') {

           $status = CreditPayments::STATUS_DECLINED;

        } else if ($this->response->getTransactionStatus() == InstamedGateway::CAPTURED_APPROVED
                || $this->response->getTransactionStatus() == 'O'
                || $this->response->getResponseCode() == '000') {

            $status = CreditPayments::STATUS_APPROVED;

        } else {
            $status = CreditPayments::STATUS_ERROR;
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

        if ($this->getStatus() != CreditPayments::STATUS_DECLINED && $this->response->SignatureRequired() == 1) {
            $tbl->addBodyTr(HTMLTable::makeTd("Sign: ", array('class'=>'tdlabel')) . HTMLTable::makeTd('', array('style'=>'height:35px; width:310px; border: solid 1px gray;')));
        }

    }

}

class ImCofResponse extends PaymentResponse {

    public $idToken;


    function __construct(iGatewayResponse $vcr, $idPayor, $idGroup) {
        $this->response = $vcr;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->idToken = $vcr->getToken();

    }

    public function getStatus() {

        switch ($this->response->getResponseCode()) {

            case '000':
                $status = CreditPayments::STATUS_APPROVED;
                break;

            case '001':
                $status = CreditPayments::STATUS_DECLINED;
                break;

            case '003':
                $status = CreditPayments::STATUS_DECLINED;
                break;

            case '005':
                $status = CreditPayments::STATUS_DECLINED;
                break;

            case '051':
                $status = CreditPayments::STATUS_DECLINED;
                break;

            case '063':
                $status = CreditPayments::STATUS_DECLINED;
                break;

            default:
                $status = CreditPayments::STATUS_ERROR;

        }

        return $status;
    }

    public function receiptMarkup(\PDO $dbh, &$tbl) {
        return array('error'=>'Receipts not available.');
    }

}

