<?php
/**
 * LocalResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of LocalResponse
 *
 * @author Eric
 */
class LocalResponse extends CreditResponse {

    protected $cardNum;
    protected $cardType;


    function __construct($amount, $idPayor, $invoiceNumber, $cardType, $cardAcct, $idToken, $payNote = '') {

        $this->paymentType = PayType::ChargeAsCash;
        $this->idPayor = $idPayor;
        $this->amount = $amount;
        $this->invoiceNumber = $invoiceNumber;
        $this->cardNum = $cardAcct;
        $this->cardType = $cardType;
        $this->setIdToken($idToken);
        $this->payNotes = $payNote;

    }


    public function getChargeType() {
        return $this->cardType;
    }

    public function getCardNum() {
        return $this->cardNum;
    }

    public function getStatus() {
        return CreditPayments::STATUS_APPROVED;
    }

    public function receiptMarkup(\PDO $dbh, &$tbl) {

        $chgTypes = readGenLookupsPDO($dbh, 'Charge_Cards');
        $cgType = $this->getChargeType();

        if (isset($chgTypes[$this->getChargeType()])) {
            $cgType = $chgTypes[$this->getChargeType()][1];
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Credit Card:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($this->getAmount(), 2)));
        $tbl->addBodyTr(HTMLTable::makeTd($cgType . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd($this->getCardNum()));

    }

    public function getPaymentMethod() {
        return PaymentMethod::Charge;
    }

    public function getPaymentStatusCode() {
        return PaymentStatusCode::Paid;
    }

}
