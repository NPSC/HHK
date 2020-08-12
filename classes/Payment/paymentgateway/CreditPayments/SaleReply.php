<?php

namespace HHK\Payment\PaymentGateway\CreditPayments;

use HHK\Payment\CreditToken;
use HHK\Payment\PaymentResponse\AbstractCreditResponse;
use HHK\Tables\EditRS;
use HHK\Tables\Payment\{Payment_AuthRS, PaymentRS};
use HHK\SysConst\MpStatusMessage;
use HHK\sec\Session;
use HHK\Exception\PaymentException;

/**
 * SaleReply.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class SaleReply extends AbstractCreditPayments {

    protected static function caseApproved(\PDO $dbh, AbstractCreditResponse $pr, $username, $pRs = NULL, $attempts = 1) {

        $uS = Session::getInstance();

        // Store any tokens
        $pr->setIdToken(CreditToken::storeToken($dbh, $pr->idRegistration, $pr->idPayor, $pr->response, $pr->getIdToken()));

        // Check for replay - AP*
        if ($pr->response->getResponseMessage() == MpStatusMessage::Replay) {

            // Find previous response, if we caught it.
            $paRs = new Payment_AuthRS();
            $paRs->Approval_Code->setStoredVal($pr->response->getAuthCode());
            $paRs->Invoice_Number->setStoredVal($pr->response->getInvoiceNumber());

            $rows = EditRS::select($dbh, $paRs, array($paRs->Approval_Code, $paRs->Invoice_Number));

            if (count($rows) > 0) {

                // Already caught this payment
                EditRS::loadRow($rows[0], $paRs);

                // Load the original payment record.
                $payRs = new PaymentRS();
                $payRs->idPayment->setStoredVal($paRs->idPayment->getStoredVal());
                $pmts = EditRS::select($dbh, $payRs, array($payRs->idPayment));

                if (count($pmts) == 1) {

                    EditRS::loadRow($pmts[0], $payRs);
                    $pr->setIdPayment($payRs->idPayment->getStoredVal());
                    return $pr;

                } else {
                    throw new PaymentException("Payment Id missing for Payment_auth Id " . $paRs->idPayment_auth->getStoredVal());
                }
            }
        }

        $pr->recordPayment($dbh, $username, $attempts);

        $pr->recordPaymentAuth($dbh, $uS->PaymentGateway, $username);

        return $pr;
    }

    protected static function caseDeclined(\PDO $dbh, AbstractCreditResponse $pr, $username, $pRs = NULL, $attempts = 1) {

        $uS = Session::getInstance();
//        $vr = $pr->response;

        $pr->recordPayment($dbh, $username, $attempts);

        $pr->recordPaymentAuth($dbh, $uS->PaymentGateway, $username);

        return $pr;
    }

}
?>