<?php

namespace HHK\Payment\PaymentGateway\CreditPayments;

use HHK\Payment\PaymentResponse\AbstractCreditResponse;
use HHK\SysConst\PaymentStatusCode;
use HHK\Tables\EditRS;
use HHK\sec\Session;
use HHK\Exception\PaymentException;

/**
 * VoidReturnReply.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class VoidReturnReply extends AbstractCreditPayments {

    protected static function caseApproved(\PDO $dbh, AbstractCreditResponse $pr, $username, $payRs = NULL, $attempts = 1){

        if (is_null($payRs) || $payRs->idPayment->getStoredVal() == 0) {
            throw new PaymentException('Payment Id is undefined (0).  ');
        }

        $uS = Session::getInstance();

        // Is a payment returned, or is this a stand-alone return?
        if ($payRs->Is_Refund->getStoredVal() == 1) {

            // Stand-alone return payment.
            $payRs->Status_Code->setNewVal(PaymentStatusCode::VoidReturn);

        } else {

            // Return an exsiting Payment record
            $payRs->Status_Code->setNewVal(PaymentStatusCode::Paid);
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

        EditRS::update($dbh, $payRs, array($payRs->idPayment));
        EditRS::updateStoredVals($payRs);

        $pr->setPaymentDate(date("Y-m-d H:i:s"));
        $pr->setIdPayment($payRs->idPayment->getStoredVal());

        $pr->recordPaymentAuth($dbh, $uS->PaymentGateway, $username);

        return $pr;

    }

    protected static function caseDeclined(\PDO $dbh, AbstractCreditResponse $pr, $userName, $payRs = NULL, $attempts = 1) {
        // todo:  Return a timed out message - only works on un-captured transactions.
        return $pr;
    }

}
?>