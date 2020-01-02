<?php
/**
 * CreditPayments.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


abstract class CreditPayments {

    const STATUS_APPROVED = 'AP';
    const STATUS_DECLINED = 'DECLINED';
    const STATUS_ERROR = 'Error';

    public static function processReply(\PDO $dbh, CreditResponse $pr, $userName, $payRs = NULL, $attempts = 1) {

        // Transaction status
        switch ($pr->getStatus()) {

            case CreditPayments::STATUS_APPROVED:
                $pr = static::caseApproved($dbh, $pr, $userName, $payRs, $attempts);
                break;

            case CreditPayments::STATUS_DECLINED:
                $pr = static::caseDeclined($dbh, $pr, $userName, $payRs, $attempts);
                break;

            default:
                static::caseOther($dbh, $pr, $userName, $payRs);

        }

        return $pr;
    }


    protected static function caseApproved(\PDO $dbh, CreditResponse $pr, $userName, $payRs = NULL, $attempts = 1) {
        throw new Hk_Exception_Payment('Payments::caseApproved Method not overridden!');
    }

    protected static function caseDeclined(\PDO $dbh, CreditResponse $pr, $userName, $payRs = NULL, $attempts = 1) {
        return $pr;
    }
    protected static function caseOther(\PDO $dbh, CreditResponse $pr, $userName, $payRs = NULL, $attempts = 1) {
        return $pr;
    }

}

class SaleReply extends CreditPayments {

    protected static function caseApproved(\PDO $dbh, CreditResponse $pr, $username, $pRs = NULL, $attempts = 1) {

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
                    throw new Hk_Exception_Payment("Payment Id missing for Payment_auth Id " . $paRs->idPayment_auth->getStoredVal());
                }
            }
        }

        $pr->recordPayment($dbh, $username, $attempts);

        $pr->recordPaymentAuth($dbh, $uS->PaymentGateway, $username);

        return $pr;
    }

    protected static function caseDeclined(\PDO $dbh, CreditResponse $pr, $username, $pRs = NULL, $attempts = 1) {

        $uS = Session::getInstance();
//        $vr = $pr->response;

        $pr->recordPayment($dbh, $username, $attempts);

        $pr->recordPaymentAuth($dbh, $uS->PaymentGateway, $username);

        return $pr;
    }

}


class VoidReply extends CreditPayments {

    protected static function caseApproved(\PDO $dbh, CreditResponse $pr, $username, $payRs = NULL, $attempts = 1){

        if (is_null($payRs) || $payRs->idPayment->getStoredVal() == 0) {
            throw new Hk_Exception_Payment('Payment Id not given.  ');
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

        //$pr->setPaymentDate(date("Y-m-d H:i:s"));
        $$pr->setIdPayment($payRs->idPayment->getStoredVal());

        // Payment Detail
        $pr->recordPaymentAuth($dbh, $uS->PaymentGateway, $username);

        return $pr;

    }

    protected static function caseDeclined(\PDO $dbh, CreditResponse $pr, $username, $payRs = NULL, $attempts = 1) {

        if ($pr->response->getResponseMessage() == 'ITEM VOIDED') {
            $pr = self::caseApproved($dbh, $pr, $username, $payRs, $attempts);
        }
        return $pr;
    }
}

class ReverseReply extends CreditPayments {

    protected static function caseApproved(\PDO $dbh, CreditResponse $pr, $username, $payRs = NULL, $attempts = 1){

        if (is_null($payRs) || $payRs->idPayment->getStoredVal() == 0) {
            throw new Hk_Exception_Payment('Payment Id not given.  ');
        }

        $uS = Session::getInstance();
//        $vr = $pr->response;

        // Store any tokens
        $pr->setIdToken(CreditToken::storeToken($dbh, $pr->idRegistration, $pr->idPayor, $pr->response, $pr->getIdToken()));

        // Payment record
        $payRs->Status_Code->setNewVal(PaymentStatusCode::Reverse);
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

        EditRS::update($dbh, $payRs, array($payRs->idPayment));
        EditRS::updateStoredVals($payRs);

        $pr->setIdPayment($payRs->idPayment->getStoredVal());

        $pr->recordPaymentAuth($dbh, $uS->PaymentGateway, $username);

        return $pr;

    }
}


class ReturnReply extends CreditPayments {

    protected static function caseApproved(\PDO $dbh, CreditResponse $pr, $username, $payRs = NULL, $attempts = 1){

        $uS = Session::getInstance();
        //$vr = $pr->response;

        // Store any tokens
        $pr->setIdToken(CreditToken::storeToken($dbh, $pr->idRegistration, $pr->idPayor, $pr->response, $pr->getIdToken()));

        if (is_null($payRs)) {

            // New Return payment
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
            throw new Hk_Exception_Payment('Payment Id not given.  ');
        }

        $pr->recordPaymentAuth($dbh, $uS->PaymentGateway, $username);

        return $pr;

    }

    protected static function caseDeclined(\PDO $dbh, CreditResponse $pr, $username, $pRs = NULL, $attempts = 1) {

        if (is_null($pRs)) {

            $uS = Session::getInstance();
//            $vr = $pr->response;

            $pr->recordPayment($dbh, $username, $attempts);

            $pr->recordPaymentAuth($dbh, $uS->PaymentGateway, $username);

        }

        return $pr;
    }

}

class VoidReturnReply extends CreditPayments {

    protected static function caseApproved(\PDO $dbh, CreditResponse $pr, $username, $payRs = NULL, $attempts = 1){

        if (is_null($payRs) || $payRs->idPayment->getStoredVal() == 0) {
            throw new Hk_Exception_Payment('Payment Id is undefined (0).  ');
        }

        $uS = Session::getInstance();
        $vr = $pr->response;

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
                $payRs->Notes->setNewVal($payRs->Notes->getStoredVal() . " | " . getPaymentNotes());
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

    protected static function caseDeclined(\PDO $dbh, CreditResponse $pr, $userName, $payRs = NULL, $attempts = 1) {
        // todo:  Return a timed out message - only works on un-captured transactions.
        return $pr;
    }

}
