<?php

namespace HHK\Payment;

use HHK\Payment\PaymentResponse\CashResponse;
use HHK\SysConst\{PaymentStatusCode, TransType, TransMethod};
use HHK\Tables\EditRS;
use HHK\Tables\Payment\PaymentRS;
use HHK\Exception\PaymentException;

/**
 * CashTX.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of CashTX
 *
 * @author Eric
 */
class CashTX {

    public static function cashSale(\PDO $dbh, CashResponse &$pr, $username, $paymentDate) {

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Sale, TransMethod::Cash);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());

        $pr->setPaymentStatusCode(PaymentStatusCode::Paid);
        $pr->setPaymentDate($paymentDate);

        // Record Payment
        $pr->recordPayment($dbh, $username);

    }

    /**
     * Return an amount directly from an invoice
     *
     * @param \PDO $dbh
     * @param CashResponse $pr
     * @param string $userName
     * @param string $paymentDate
     */
    public static function returnAmount(\PDO $dbh, CashResponse &$pr, $username, $paymentDate) {

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Retrn, TransMethod::Cash);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());

        $pr->setPaymentStatusCode(PaymentStatusCode::Paid);
        $pr->setRefund(TRUE);
        $pr->setPaymentDate($paymentDate);


        // Record Payment
        $pr->recordPayment($dbh, $username);

    }

    /**
     * Return a previous payment.
     *
     * @param \PDO $dbh
     * @param CashResponse $pr
     * @param string $username
     * @param PaymentRS $payRs
     * @throws PaymentException
     */
    public static function returnPayment(\PDO $dbh, CashResponse &$pr, $username, PaymentRS $payRs) {

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Retrn, TransMethod::Cash);
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

    public static function undoReturnPayment(\PDO $dbh, CashResponse &$pr, $username, PaymentRS $payRs) {

        // Record transaction
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::undoRetrn, TransMethod::Cash);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());

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
        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::undoRetrn, TransMethod::Cash);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());

        $dbh->exec("delete from payment_invoice where Payment_Id = $idPayment");
        $dbh->exec("delete from payment where idPayment = $idPayment");

    }
}


?>