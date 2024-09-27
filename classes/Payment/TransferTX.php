<?php

namespace HHK\Payment;

use HHK\Payment\PaymentResponse\CashResponse;
use HHK\Payment\PaymentResponse\TransferResponse;
use HHK\SysConst\{TransMethod, TransType, PaymentStatusCode};
use HHK\Tables\EditRS;
use HHK\Tables\Payment\PaymentRS;
use HHK\Exception\PaymentException;

/**
 * TransferTX.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


/**
 * Description of CheckTX
 *
 * @author Eric
 */

class TransferTX {

    /**
     * Summary of sale
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentResponse\TransferResponse $pr
     * @param mixed $username
     * @param mixed $paymentDate
     * @return void
     */
    public static function sale(\PDO $dbh, TransferResponse &$pr, $username, $paymentDate) {

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Sale, TransMethod::Transfer);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());
        $pr->setPaymentDate($paymentDate);

        $pr->setPaymentStatusCode(PaymentStatusCode::Paid);

        // Record Payment
        $pr->recordPayment($dbh, $username);

        $pr->recordInfoCheck($dbh);

    }

    /**
     * Summary of returnAmount
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentResponse\TransferResponse $pr
     * @param mixed $username
     * @param mixed $paymentDate
     * @return void
     */
    public static function returnAmount(\PDO $dbh, TransferResponse &$pr, $username, $paymentDate) {

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Retrn, TransMethod::Transfer);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());

        $pr->setPaymentStatusCode(PaymentStatusCode::Paid);
        $pr->setRefund(TRUE);
        $pr->setPaymentDate($paymentDate);


        // Record Payment
        $pr->recordPayment($dbh, $username);

        $pr->recordInfoCheck($dbh);

    }

    /**
     * Summary of transferReturn
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentResponse\TransferResponse $pr
     * @param mixed $username
     * @param \HHK\Tables\Payment\PaymentRS $payRs
     * @throws \HHK\Exception\PaymentException
     * @return void
     */
    public static function transferReturn(\PDO $dbh, TransferResponse &$pr, $username, PaymentRS $payRs) {

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Retrn, TransMethod::Transfer);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());

        if ($payRs->idPayment->getStoredVal() == 0) {
            throw new PaymentException('Payment Id not given.  ');
        }


        // Payment record
        $payRs->Status_Code->setNewVal(PaymentStatusCode::Retrn);
        $payRs->Updated_By->setNewVal($username);
        $payRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

        EditRS::update($dbh, $payRs, array($payRs->idPayment));
        EditRS::updateStoredVals($payRs);
        $pr->paymentRs = $payRs;
        $pr->setPaymentDate(date('Y-m-d H:i:s'));

    }

    /**
     * Summary of undoTransferReturn
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentResponse\TransferResponse $pr
     * @param mixed $username
     * @param \HHK\Tables\Payment\PaymentRS $payRs
     * @throws \HHK\Exception\PaymentException
     * @return void
     */
    public static function undoTransferReturn(\PDO $dbh, TransferResponse &$pr, $username, PaymentRS $payRs) {

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::undoRetrn, TransMethod::Transfer);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());

        if ($payRs->idPayment->getStoredVal() == 0) {
            throw new PaymentException('Payment Id not given.  ');
        }


        // Payment record
        $payRs->Status_Code->setNewVal(PaymentStatusCode::Paid);
        $payRs->Updated_By->setNewVal($username);
        $payRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

        EditRS::update($dbh, $payRs, array($payRs->idPayment));
        EditRS::updateStoredVals($payRs);
        $pr->paymentRs = $payRs;
        $pr->setPaymentDate(date('Y-m-d H:i:s'));

    }

    /**
     * Summary of undoReturnAmount
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentResponse\TransferResponse $pr
     * @param mixed $idPayment
     * @return void
     */
    public static function undoReturnAmount(\PDO $dbh, TransferResponse &$pr, $idPayment) {

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::undoRetrn, TransMethod::Cash);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());

        $dbh->exec("delete from payment_invoice where Payment_Id = $idPayment");
        $dbh->exec("delete from payment_info_check where idPayment = $idPayment");
        $dbh->exec("delete from payment where idPayment = $idPayment");

    }

}
?>