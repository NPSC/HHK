<?php

namespace HHK\Payment\PaymentGateway\CreditPayments;

use HHK\Payment\CreditToken;
use HHK\Payment\PaymentResponse\AbstractCreditResponse;
use HHK\SysConst\PaymentStatusCode;
use HHK\Tables\EditRS;
use HHK\sec\Session;
use HHK\Exception\PaymentException;

/**
 * VoidReply.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class VoidReply extends AbstractCreditPayments {

    protected static function caseApproved(\PDO $dbh, AbstractCreditResponse $pr, $username, $payRs = NULL, $attempts = 1){

        if (is_null($payRs) || $payRs->idPayment->getStoredVal() == 0) {
            throw new PaymentException('Payment Id not given.  ');
        }

        $uS = Session::getInstance();

        // Store any tokens
        $pr->setIdToken(CreditToken::storeToken($dbh, $pr->idRegistration, $pr->idPayor, $pr->response, $pr->getIdToken()));

        // Payment record
        $payRs->Status_Code->setNewVal(PaymentStatusCode::VoidSale);
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

        $pr->setIdPayment($payRs->idPayment->getStoredVal());

        // Payment Detail
        $pr->recordPaymentAuth($dbh, $uS->PaymentGateway, $username);

        return $pr;

    }

    protected static function caseDeclined(\PDO $dbh, AbstractCreditResponse $pr, $username, $payRs = NULL, $attempts = 1) {

        if ($pr->response->getResponseMessage() == 'ITEM VOIDED') {
            $pr = self::caseApproved($dbh, $pr, $username, $payRs, $attempts);
        }
        return $pr;
    }
}
?>