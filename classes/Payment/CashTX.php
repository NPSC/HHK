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





// class ChargeAsCashTX {

//     public static function sale(\PDO $dbh, ManualChargeResponse &$pr, $username, $paymentDate) {

//         // Record transaction
//         $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Sale, TransMethod::Cash);
//         $pr->setIdTrans($transRs->idTrans->getStoredVal());

//         $chargeCards = readGenLookupsPDO($dbh, 'Charge_Cards');

//         $cType = '';
//         if (count($chargeCards) > 0 && isset($chargeCards[$pr->getChargeType()])) {
//             $cType = $chargeCards[$pr->getChargeType()][1];
//         }

//         // Record Payment
//         $payRs = new PaymentRS();
//         $payRs->Amount->setNewVal($pr->getAmount());
//         $payRs->Payment_Date->setNewVal($paymentDate);
//         $payRs->idPayor->setNewVal($pr->idPayor);
//         $payRs->idTrans->setNewVal($pr->getIdTrans());
//         $payRs->Notes->setNewVal($pr->payNotes);
//         $payRs->idPayment_Method->setNewVal(PaymentMethod::ChgAsCash);
//         $payRs->Attempt->setNewVal(1);
//         $payRs->Status_Code->setNewVal(PaymentStatusCode::Paid);
//         $payRs->Created_By->setNewVal($username);

//         $idPayment = EditRS::insert($dbh, $payRs);
//         $payRs->idPayment->setNewVal($idPayment);
//         EditRS::updateStoredVals($payRs);

//         $pr->setPaymentDate($paymentDate);
//         $pr->paymentRs = $payRs;

//         if ($idPayment > 0) {

//             //Payment Detail
//             $pDetailRS = new Payment_AuthRS();
//             $pDetailRS->idPayment->setNewVal($idPayment);
//             $pDetailRS->Approved_Amount->setNewVal($pr->getAmount());
//             $pDetailRS->Acct_Number->setNewVal($pr->getCardNum());
//             $pDetailRS->Card_Type->setNewVal($cType);
//             $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
//             $pDetailRS->Invoice_Number->setNewVal($pr->getInvoiceNumber());

//             $pDetailRS->Updated_By->setNewVal($username);
//             $pDetailRS->Last_Updated->setNewVal($paymentDate);
//             $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::Paid);

//             $idPaymentAuth = EditRS::insert($dbh, $pDetailRS);
//             $pDetailRS->idPayment_auth->setNewVal($idPaymentAuth);
//             EditRS::updateStoredVals($pDetailRS);


//         }

//     }

//     public static function refundAmount(\PDO $dbh, ManualChargeResponse &$pr, $username, $paymentDate) {

//         // Record transaction
//         $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Retrn, TransMethod::Cash);
//         $pr->setIdTrans($transRs->idTrans->getStoredVal());

//         $chargeCards = readGenLookupsPDO($dbh, 'Charge_Cards');

//         $cType = '';
//         if (count($chargeCards) > 0 && isset($chargeCards[$pr->getChargeType()])) {
//             $cType = $chargeCards[$pr->getChargeType()][1];
//         }

//         // Record Payment
//         $payRs = new PaymentRS();
//         $payRs->Amount->setNewVal($pr->getAmount());
//         $payRs->Payment_Date->setNewVal($paymentDate);
//         $payRs->idPayor->setNewVal($pr->idPayor);
//         $payRs->idTrans->setNewVal($pr->getIdTrans());
//         $payRs->Notes->setNewVal($pr->payNotes);
//         $payRs->idPayment_Method->setNewVal(PaymentMethod::ChgAsCash);
//         $payRs->Attempt->setNewVal(1);
//         $payRs->Is_Refund->setNewVal(1);
//         $payRs->Status_Code->setNewVal(PaymentStatusCode::Paid);
//         $payRs->Created_By->setNewVal($username);

//         $idPayment = EditRS::insert($dbh, $payRs);
//         $payRs->idPayment->setNewVal($idPayment);
//         EditRS::updateStoredVals($payRs);

//         $pr->setPaymentDate($paymentDate);
//         $pr->paymentRs = $payRs;

//         if ($idPayment > 0) {

//             //Payment Detail
//             $pDetailRS = new Payment_AuthRS();
//             $pDetailRS->idPayment->setNewVal($idPayment);
//             $pDetailRS->Approved_Amount->setNewVal($pr->getAmount());
//             $pDetailRS->Acct_Number->setNewVal($pr->getCardNum());
//             $pDetailRS->Card_Type->setNewVal($cType);
//             $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
//             $pDetailRS->Invoice_Number->setNewVal($pr->getInvoiceNumber());
//             $pDetailRS->Updated_By->setNewVal($username);
//             $pDetailRS->Last_Updated->setNewVal($paymentDate);
//             $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::Paid);

//             $idPaymentAuth = EditRS::insert($dbh, $pDetailRS);
//             $pDetailRS->idPayment_auth->setNewVal($idPaymentAuth);
//             EditRS::updateStoredVals($pDetailRS);


//         }

//     }

//     public static function returnPayment(\PDO $dbh, ManualChargeResponse &$pr, $username, PaymentRS $payRs) {

//         // Record transaction
//         $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::Retrn, TransMethod::Cash);
//         $pr->setIdTrans($transRs->idTrans->getStoredVal());

//         if ($payRs->idPayment->getStoredVal() == 0) {
//             throw new Hk_Exception_Payment('Payment Id not given.  ');
//         }

//         // Payment record
//         $payRs->Status_Code->setNewVal(PaymentStatusCode::Retrn);
// //        $payRs->Balance->setNewVal($payRs->Amount->getStoredVal());
//         $payRs->Updated_By->setNewVal($username);
//         $payRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

//         // payment Note
//         if ($pr->payNotes != '') {

//             if ($payRs->Notes->getStoredVal() != '') {
//                 $payRs->Notes->setNewVal($payRs->Notes->getStoredVal() . " | " . $pr->payNotes);
//             } else {
//                 $payRs->Notes->setNewVal($pr->payNotes);
//             }
//         }

//         EditRS::update($dbh, $payRs, array($payRs->idPayment));
//         EditRS::updateStoredVals($payRs);
//         $pr->paymentRs = $payRs;
//         $pr->setPaymentDate(date('Y-m-d H:i:s'));


//         //Payment Detail
//         $pDetailRS = new Payment_AuthRS();
//         $pDetailRS->idPayment->setNewVal($payRs->idPayment->getStoredVal());
//         $pDetailRS->Approved_Amount->setNewVal($pr->getAmount());
//         $pDetailRS->Acct_Number->setNewVal($pr->getCardNum());
//         $pDetailRS->Card_Type->setNewVal($pr->getChargeType());
//         $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
//         $pDetailRS->Invoice_Number->setNewVal($pr->getInvoiceNumber());

//         $pDetailRS->Updated_By->setNewVal($username);
//         $pDetailRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
//         $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::Retrn);

//         $idPaymentAuth = EditRS::insert($dbh, $pDetailRS);
//         $pDetailRS->idPayment_auth->setNewVal($idPaymentAuth);
//         EditRS::updateStoredVals($pDetailRS);

//     }

//     public static function undoReturnPayment(\PDO $dbh, ManualChargeResponse &$pr, $username, PaymentRS $payRs) {

//                 // Record transaction
//         $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::undoRetrn, TransMethod::Cash);
//         $pr->setIdTrans($transRs->idTrans->getStoredVal());

//         // Payment record
//         $payRs->Status_Code->setNewVal(PaymentStatusCode::Paid);
//         $payRs->Updated_By->setNewVal($username);
//         $payRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

//         EditRS::update($dbh, $payRs, array($payRs->idPayment));
//         EditRS::updateStoredVals($payRs);
//         $pr->paymentRs = $payRs;
//         $pr->setPaymentDate(date('Y-m-d H:i:s'));


//         //Payment Detail
//         $pDetailRS = new Payment_AuthRS();
//         $pDetailRS->idPayment->setStoredVal($payRs->idPayment->getStoredVal());
//         $pDetailRS->Status_Code->setStoredVal(PaymentStatusCode::Retrn);

//         // Delete return payment auth record.
//         EditRS::delete($dbh, $pDetailRS, array($pDetailRS->idPayment, $pDetailRS->Status_Code));

//     }

//     public static function undoReturnAmount(\PDO $dbh, ManualChargeResponse &$pr, $idPayment) {

//         // Record transaction
//         $transRs = Transaction::recordTransaction($dbh, $pr, '', TransType::undoRetrn, TransMethod::Cash);
//         $pr->setIdTrans($transRs->idTrans->getStoredVal());

//         $dbh->exec("delete from payment_invoice where Payment_Id = $idPayment");
//         $dbh->exec("delete from payment_auth where idPayment = $idPayment");
//         $dbh->exec("delete from payment where idPayment = $idPayment");

//     }

// }
?>