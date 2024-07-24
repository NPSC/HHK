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
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ReturnReply extends AbstractCreditPayments {

    /**
     * Summary of caseApproved
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentResponse\AbstractCreditResponse $pr
     * @param mixed $username
     * @param \HHK\Tables\Payment\PaymentRS|null $payRs
     * @param mixed $attempts
     * @throws \HHK\Exception\PaymentException
     * @return AbstractCreditResponse
     */
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
            if($pr->getAmount() == $payRs->Amount->getStoredVal()){
                $payRs->Status_Code->setNewVal(PaymentStatusCode::Retrn);
            }
            $payRs->Updated_By->setNewVal($username);
            $payRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

            // payment Note
            if ($pr->getPaymentNotes() != '') {

                if ($payRs->Notes->getStoredVal() != '') {
                    $payRs->Notes->setNewVal($payRs->Notes->getStoredVal() . " | " . $pr->getPaymentNotes());
                } else {
                    $payRs->Notes->setNewVal($pr->getPaymentNotes());
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

    /**
     * Summary of caseDeclined
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentResponse\AbstractCreditResponse $pr
     * @param mixed $username
     * @param mixed $pRs
     * @param mixed $attempts
     * @return AbstractCreditResponse
     */
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