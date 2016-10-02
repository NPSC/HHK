<?php
/**
 * CheckTX.php
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

class CheckResponse extends PaymentResponse {

    function __construct($amount, $idPayor, $invoiceNumber, $checkNumber = '', $payNotes = '') {

        $this->paymentType = PayType::Check;
        $this->idPayor = $idPayor;
        $this->amount = $amount;
        $this->invoiceNumber = $invoiceNumber;
        $this->checkNumber = $checkNumber;
        $this->payNotes = $payNotes;

    }

    public function receiptMarkup(\PDO $dbh, &$tbl) {

        $tbl->addBodyTr(HTMLTable::makeTd("Check:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($this->getAmount(), 2)));
        $tbl->addBodyTr(HTMLTable::makeTd('Check Number:', array('class'=>'tdlabel')) . HTMLTable::makeTd($this->checkNumber));
    }


}


/**
 * Description of CheckTX
 *
 * @author Eric
 */
class CheckTX {

    public static function checkSale(\PDO $dbh, \CheckResponse &$pr, $userName, $paymentDate) {

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Sale, TransMethod::Check);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());


        // Record Payment
        $payRs = new PaymentRS();
        $payRs->Amount->setNewVal($pr->getAmount());
        $payRs->Payment_Date->setNewVal($paymentDate);
        $payRs->idPayor->setNewVal($pr->idPayor);
        $payRs->idTrans->setNewVal($pr->getIdTrans());
        $payRs->Notes->setNewVal($pr->payNotes);
        $payRs->idPayment_Method->setNewVal(PaymentMethod::Check);
        $payRs->Attempt->setNewVal(1);
        $payRs->Status_Code->setNewVal(PaymentStatusCode::Paid);
        $payRs->Created_By->setNewVal($userName);

        $idPayment = EditRS::insert($dbh, $payRs);
        $payRs->idPayment->setNewVal($idPayment);
        EditRS::updateStoredVals($payRs);
        $pr->paymentRs = $payRs;

        if ($idPayment > 0) {

            // Check table
            $ckRs = new PaymentInfoCheckRS();
            $ckRs->Check_Date->setNewVal($paymentDate);
            $ckRs->Check_Number->setNewVal($pr->checkNumber);
            $ckRs->idPayment->setNewVal($idPayment);

            EditRS::insert($dbh, $ckRs);
        }

    }

    public static function checkReturn(\PDO $dbh, \CheckResponse &$pr, $username, PaymentRS $payRs) {

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Retrn, TransMethod::Check);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());

        if ($payRs->idPayment->getStoredVal() == 0) {
            throw new Hk_Exception_Payment('Payment Id not given.  ');
        }


        // Payment record
        $payRs->Status_Code->setNewVal(PaymentStatusCode::Retrn);
        $payRs->Balance->setNewVal($payRs->Amount->getStoredVal());
        $payRs->Updated_By->setNewVal($username);
        $payRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

        // payment Note
        if ($pr->payNotes != '') {

            if ($payRs->Notes->getStoredVal() != '') {
                $payRs->Notes->setNewVal($payRs->Notes->getStoredVal() . " | " . $pr->payNotes);
            } else {
                $payRs->Notes->setNewVal($pr->payNotes);
            }
        }

        EditRS::update($dbh, $payRs, array($payRs->idPayment));
        EditRS::updateStoredVals($payRs);
        $pr->paymentRs = $payRs;


    }

}



class TransferResponse extends PaymentResponse {

    function __construct($amount, $idPayor, $invoiceNumber, $transferAcct = '', $payNotes = '') {

        $this->paymentType = PayType::Transfer;
        $this->idPayor = $idPayor;
        $this->amount = $amount;
        $this->invoiceNumber = $invoiceNumber;
        $this->checkNumber = $transferAcct;
        $this->payNotes = $payNotes;

    }

    public function receiptMarkup(\PDO $dbh, &$tbl) {

        $tbl->addBodyTr(HTMLTable::makeTd("Transfer:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($this->getAmount(), 2)));
        $tbl->addBodyTr(HTMLTable::makeTd('Transfer Acct:', array('class'=>'tdlabel')) . HTMLTable::makeTd($this->checkNumber));

    }

}



class TransferTX {

    public static function sale(\PDO $dbh, \TransferResponse &$pr, $userName, $paymentDate) {

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Sale, TransMethod::Transfer);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());


        // Record Payment
        $payRs = new PaymentRS();
        $payRs->Amount->setNewVal($pr->getAmount());
        $payRs->Payment_Date->setNewVal($paymentDate);
        $payRs->idPayor->setNewVal($pr->idPayor);
        $payRs->idTrans->setNewVal($pr->getIdTrans());
        $payRs->Notes->setNewVal($pr->payNotes);
        $payRs->idPayment_Method->setNewVal(PaymentMethod::Transfer);
        $payRs->Attempt->setNewVal(1);
        $payRs->Status_Code->setNewVal(PaymentStatusCode::Paid);
        $payRs->Created_By->setNewVal($userName);

        $idPayment = EditRS::insert($dbh, $payRs);
        $payRs->idPayment->setNewVal($idPayment);
        EditRS::updateStoredVals($payRs);
        $pr->paymentRs = $payRs;

        if ($idPayment > 0) {

            // Check table
            $ckRs = new PaymentInfoCheckRS();
            $ckRs->Check_Date->setNewVal($paymentDate);
            $ckRs->Check_Number->setNewVal($pr->checkNumber);
            $ckRs->idPayment->setNewVal($idPayment);

            EditRS::insert($dbh, $ckRs);
        }

    }

    public static function transferReturn(\PDO $dbh, \TransferResponse &$pr, $username, PaymentRS $payRs) {

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Retrn, TransMethod::Transfer);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());

        if ($payRs->idPayment->getStoredVal() == 0) {
            throw new Hk_Exception_Payment('Payment Id not given.  ');
        }


        // Payment record
        $payRs->Status_Code->setNewVal(PaymentStatusCode::Retrn);
        $payRs->Balance->setNewVal($payRs->Amount->getStoredVal());
        $payRs->Updated_By->setNewVal($username);
        $payRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

        // payment Note
        if ($pr->payNotes != '') {

            if ($payRs->Notes->getStoredVal() != '') {
                $payRs->Notes->setNewVal($payRs->Notes->getStoredVal() . " | " . $pr->payNotes);
            } else {
                $payRs->Notes->setNewVal($pr->payNotes);
            }
        }

        EditRS::update($dbh, $payRs, array($payRs->idPayment));
        EditRS::updateStoredVals($payRs);
        $pr->paymentRs = $payRs;

    }

}
