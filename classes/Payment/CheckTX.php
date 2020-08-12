<?php

namespace HHK\Payment;

use HHK\Payment\PaymentResponse\CashResponse;
use HHK\Payment\PaymentResponse\CheckResponse;
use HHK\SysConst\{TransMethod, TransType, PaymentStatusCode};
use HHK\Tables\EditRS;
use HHK\Tables\Payment\PaymentRS;
use HHK\Exception\PaymentException;

/**
 * CheckTX.php
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
class CheckTX {

    public static function checkSale(\PDO $dbh, CheckResponse &$pr, $username, $paymentDate) {

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Sale, TransMethod::Check);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());
        $pr->setPaymentStatusCode(PaymentStatusCode::Paid);
        $pr->setPaymentDate($paymentDate);

        // Record Payment
        $pr->recordPayment($dbh, $username);

        $pr->recordInfoCheck($dbh);

    }

    public static function returnAmount(\PDO $dbh, CheckResponse &$pr, $userName, $paymentDate) {

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Retrn, TransMethod::Check);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());
        $pr->setPaymentStatusCode(PaymentStatusCode::Paid);
        $pr->setRefund(TRUE);
        $pr->setPaymentDate($paymentDate);


        // Record Payment
        $pr->recordPayment($dbh, $userName);

        $pr->recordInfoCheck($dbh);

    }

    public static function checkReturn(\PDO $dbh, CheckResponse &$pr, $username, PaymentRS $payRs) {

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Retrn, TransMethod::Check);
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

    public static function undoReturnPayment(\PDO $dbh, CheckResponse &$pr, $username, PaymentRS $payRs) {

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::undoRetrn, TransMethod::Check);
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

    public static function undoReturnAmount(\PDO $dbh, CashResponse &$pr, $idPayment) {

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::undoRetrn, TransMethod::Check);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());

        $dbh->exec("delete from payment_invoice where Payment_Id = $idPayment");
        $dbh->exec("delete from payment_info_check where idPayment = $idPayment");
        $dbh->exec("delete from payment where idPayment = $idPayment");

    }

}
?>