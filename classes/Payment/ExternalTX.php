<?php

namespace HHK\Payment;

use HHK\Payment\PaymentResponse\ExternalResponse;
use HHK\SysConst\{PaymentStatusCode, TransMethod, TransType};
use HHK\Tables\EditRS;
use HHK\Tables\Payment\PaymentRS;
use HHK\Exception\PaymentException;

class ExternalTX {

    public static function sale(\PDO $dbh, ExternalResponse &$pr, $username, $paymentDate) {

        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Sale, TransMethod::External);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());
        $pr->setPaymentDate($paymentDate);
        $pr->setPaymentStatusCode(PaymentStatusCode::Paid);

        $pr->recordPayment($dbh, $username);
        $pr->recordInfoCheck($dbh);
    }

    public static function returnAmount(\PDO $dbh, ExternalResponse &$pr, $username, $paymentDate) {

        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Retrn, TransMethod::External);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());
        $pr->setPaymentStatusCode(PaymentStatusCode::Paid);
        $pr->setRefund(TRUE);
        $pr->setPaymentDate($paymentDate);

        $pr->recordPayment($dbh, $username);
        $pr->recordInfoCheck($dbh);
    }

    public static function externalReturn(\PDO $dbh, ExternalResponse &$pr, $username, PaymentRS $payRs) {

        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Retrn, TransMethod::External);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());

        if ($payRs->idPayment->getStoredVal() == 0) {
            throw new PaymentException('Payment Id not given.  ');
        }

        $payRs->Status_Code->setNewVal(PaymentStatusCode::Retrn);
        $payRs->Updated_By->setNewVal($username);
        $payRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

        EditRS::update($dbh, $payRs, array($payRs->idPayment));
        EditRS::updateStoredVals($payRs);
        $pr->paymentRs = $payRs;
        $pr->setPaymentDate(date('Y-m-d H:i:s'));
    }

    public static function undoExternalReturn(\PDO $dbh, ExternalResponse &$pr, $username, PaymentRS $payRs) {

        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::undoRetrn, TransMethod::External);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());

        if ($payRs->idPayment->getStoredVal() == 0) {
            throw new PaymentException('Payment Id not given.  ');
        }

        $payRs->Status_Code->setNewVal(PaymentStatusCode::Paid);
        $payRs->Updated_By->setNewVal($username);
        $payRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

        EditRS::update($dbh, $payRs, array($payRs->idPayment));
        EditRS::updateStoredVals($payRs);
        $pr->paymentRs = $payRs;
        $pr->setPaymentDate(date('Y-m-d H:i:s'));
    }

    public static function undoReturnAmount(\PDO $dbh, ExternalResponse &$pr, $idPayment) {

        $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::undoRetrn, TransMethod::External);
        $pr->setIdTrans($transRs->idTrans->getStoredVal());

        $delPmtInvStmt = $dbh->prepare("DELETE FROM `payment_invoice` WHERE `Payment_Id` = :idPayment");
        $delPmtInvStmt->execute([':idPayment' => $idPayment]);
        $delCheckStmt = $dbh->prepare("DELETE FROM `payment_info_check` WHERE `idPayment` = :idPayment");
        $delCheckStmt->execute([':idPayment' => $idPayment]);
        $delPmtStmt = $dbh->prepare("DELETE FROM `payment` WHERE `idPayment` = :idPayment");
        $delPmtStmt->execute([':idPayment' => $idPayment]);
    }
}
?>
