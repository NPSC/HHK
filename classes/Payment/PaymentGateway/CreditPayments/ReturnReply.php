<?php

namespace HHK\Payment\PaymentGateway\CreditPayments;

use HHK\Payment\CreditToken;
use HHK\Payment\PaymentResponse\AbstractCreditResponse;
use HHK\SysConst\PaymentStatusCode;
use HHK\Tables\EditRS;
use HHK\sec\Session;
use HHK\Exception\PaymentException;

/**
 * CreditPayments.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ReturnReply extends AbstractCreditPayments {

    protected static function caseApproved(\PDO $dbh, AbstractCreditResponse $pr, $username, $payRs = NULL, $attempts = 1){

        $uS = Session::getInstance();
        //$vr = $pr->response;

        // Store any tokens
        $pr->setIdToken(CreditToken::storeToken($dbh, $pr->idRegistration, $pr->idPayor, $pr->response, $pr->getIdToken()));

        if (is_null($payRs)) {

            // New Return payment
            $pr->setRefund(TRUE);
            $pr->setPaymentStatusCode(PaymentStatusCode::Paid);
            $pr->recordPayment($dbh, $username);
            
        } else if ($payRs->idPayment->getStoredVal() > 0) {

            // Update existing Payment record
            $payRs->Status_Code->setNewVal(PaymentStatusCode::Retrn);
            $payRs->Updated_By->setNewVal($username);
            $payRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            // payment Note
            if ($pr->getPaymentNotes() != '') {

                if ($payRs->Notes->getStoredVal() != '') {
                    $payRs->Notes->setNewVal($payRs->Notes->getStoredVal() . " | " . getPaymentNotes());
                } else {
                    $payRs->Notes->setNewVal(getPaymentNotes());
                }
            }


            // Save the payment record
            if ($payRs->idPayment->getStoredVal() > 0) {
                EditRS::update($dbh, $payRs, array($payRs->idPayment));
            } else {
                $idPayment = EditRS::insert($dbh, $payRs);
                $payRs->idPayment->setNewVal($idPayment);
            }

            EditRS::updateStoredVals($payRs);

            $pr->setIdPayment($payRs->idPayment->getStoredVal());

        } else {
            throw new PaymentException('Payment Id not given.  ');
        }

        $pr->recordPaymentAuth($dbh, $uS->PaymentGateway, $username);

        return $pr;

    }

    protected static function caseDeclined(\PDO $dbh, AbstractCreditResponse $pr, $username, $pRs = NULL, $attempts = 1) {

        if (is_null($pRs)) {

            $uS = Session::getInstance();
//            $vr = $pr->response;

            $pr->setRefund(TRUE);
            $pr->setPaymentStatusCode(PaymentStatusCode::Declined);
            $pr->recordPayment($dbh, $username, $attempts);

            $pr->recordPaymentAuth($dbh, $uS->PaymentGateway, $username);

        }

        return $pr;
    }

}
?>