<?php
/**
 * Payments.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

namespace npsc;

abstract class PaymentResponse {

    protected $paymentType;
    protected $amount;

    public $idPayor = 0;
    protected $invoiceNumber = '';

    public $idVisit;
    public $idReservation;
    public $idRegistration;
    public $idTrans = 0;

    public $expDate = '';
    public $cardNum = '';
    public $cardType = '';
    public $cardName = '';
    public $idGuestToken = 0;

    public $checkNumber = '';

    public $payNotes = '';


    /**
     *
     * @var PaymentRS
     */
    public $paymentRs;

    public function getPaymentType() {
        return $this->paymentType;
    }

    public function getAmount() {
        return $this->amount;
    }

    public function getInvoice() {
        return $this->invoiceNumber;
    }

    public function getIdPayment() {

        if (is_null($this->paymentRs) === FALSE) {
            return $this->paymentRs->idPayment->getStoredVal();
        }

        return 0;
    }

    public function getPaymentDate() {

        if (is_null($this->paymentRs) === FALSE) {
            return $this->paymentRs->Payment_Date->getStoredVal();
        }

        return '';
    }

    public function getIdTrans() {
        return $this->idTrans;
    }

    public function setIdTrans($idTrans) {
        $this->idTrans = $idTrans;
        return $this;
    }

    public abstract function receiptMarkup(\PDO $dbh, &$tbl);

}




/**
 * Description of Payments
 *
 * @author Eric
 */
abstract class CreditPayments {

    public static function processReply(\PDO $dbh, PaymentResponse $pr, $userName, PaymentRs $payRs = NULL, $attempts = 1) {

        // Transaction status
        switch ($pr->response->getStatus()) {

            case MpStatusValues::Approved:
                $pr = static::caseApproved($dbh, $pr, $userName, $payRs, $attempts);
                break;

            case MpStatusValues::Declined:
                $pr = static::caseDeclined($dbh, $pr, $userName, $payRs, $attempts);
                break;

//            case MpStatusValues::Invalid:
//                // Indicates that the user entered invalid card data too many times and was therefore redirected back to the Merchants eCommerce site.
//                //throw new Hk_Exception_Payment("Repeated invalid account number entries.  " . $vr->getDisplayMessage());
//                break;
//
//            case MpStatusValues::Error:
//                // A transaction processing error occurred.
//                //throw new Hk_Exception_Payment("Transaction processing error.  Try again later.  " . $vr->getDisplayMessage());
//                break;
//
//            case MpStatusValues::AuthFail:
//                // Authentication failed for MerchantID/password.
//                //throw new Hk_Exception_Payment("Bad Merchant Id or password. ");
//                break;
//
//            case MpStatusValues::MercInternalFail:
//                // An error occurred internal to Mercury.
//                //throw new Hk_Exception_Payment("Mercury Internal Error.  Try again later. ");
//                break;
//
//            case MpStatusValues::ValidateFail:
//                // Validation of the request failed. See Message for validation errors.
//                //throw new Hk_Exception_Payment('Validation Fail: ' . $vr->getDisplayMessage());
//                break;

            default:
                static::caseOther($dbh, $pr, $userName, $payRs);

        }

        return $pr;
    }


    protected static function caseApproved(\PDO $dbh, PaymentResponse $pr, $userName, PaymentRs $payRs = NULL, $attempts = 1) {
        throw new Hk_Exception_Payment('Payments::caseApproved Method not overridden!');
    }

    protected static function caseDeclined(\PDO $dbh, PaymentResponse $pr, $userName, PaymentRs $payRs = NULL, $attempts = 1) {
        return $pr;
    }
    protected static function caseOther(\PDO $dbh, PaymentResponse $pr, $userName, PaymentRs $payRs = NULL, $attempts = 1) {
        return $pr;
    }

}

class SaleReply extends CreditPayments {


    protected static function caseApproved(\PDO $dbh, PaymentResponse $pr, $username, PaymentRs $pRs = NULL, $attempts = 1) {

        $vr = $pr->response;

        // Check for replay - AP*
        if ($vr->getStatusMessage() == MpStatusMessage::Replay) {

            // Find previous response, if we caught it.
            $paRs = new Payment_AuthRS();
            $paRs->Approval_Code->setStoredVal($vr->getAuthCode());
            $paRs->Invoice_Number->setStoredVal($vr->getInvoice());

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
                    $pr->paymentRs = $payRs;
                    return $pr;

                } else {
                    throw new Hk_Exception_Payment("Payment Id missing for Payment_auth Id " . $paRs->idPayment_auth->getStoredVal());
                }
            }
        }


        // Record Payment
        $payRs = new PaymentRS();

        if ($vr->getTranType() == MpTranType::ReturnAmt) {
            $payRs->Is_Refund->setStoredVal(1);
        }

        $payRs->Amount->setNewVal($vr->getAuthorizeAmount());
        $payRs->Payment_Date->setNewVal(date("Y-m-d H:i:s"));
        $payRs->idPayor->setNewVal($pr->idPayor);
        $payRs->idTrans->setNewVal($pr->getIdTrans());
        $payRs->idToken->setNewVal($pr->idToken);
        $payRs->idPayment_Method->setNewVal(PaymentMethod::Charge);
        $payRs->Result->setNewVal(MpStatusValues::Approved);
        $payRs->Attempt->setNewVal($attempts);
        $payRs->Status_Code->setNewVal(PaymentStatusCode::Paid);
        $payRs->Created_By->setNewVal($username);
        $payRs->Notes->setNewVal($pr->payNotes);

        $idPayment = EditRS::insert($dbh, $payRs);
        $payRs->idPayment->setNewVal($idPayment);
        EditRS::updateStoredVals($payRs);
        $pr->paymentRs = $payRs;

        if ($idPayment > 0) {

            //Payment Detail
            $pDetailRS = new Payment_AuthRS();
            $pDetailRS->idPayment->setNewVal($idPayment);
            $pDetailRS->Approved_Amount->setNewVal($vr->getAuthorizeAmount());
            $pDetailRS->Approval_Code->setNewVal($vr->getAuthCode());
            $pDetailRS->Status_Message->setNewVal($vr->getStatusMessage());
            $pDetailRS->Reference_Num->setNewVal($vr->getRefNo());
            $pDetailRS->Acct_Number->setNewVal($pr->cardNum);
            $pDetailRS->Card_Type->setNewVal($vr->getCardType());
            $pDetailRS->AVS->setNewVal($vr->getAVSResult());
            $pDetailRS->Invoice_Number->setNewVal($vr->getInvoice());
            $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
            $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
            $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
            $pDetailRS->Code3->setNewVal($vr->getCvvResult());

            $pDetailRS->Updated_By->setNewVal($username);
            $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
            $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::Paid);

            $idPaymentAuth = EditRS::insert($dbh, $pDetailRS);
            $pDetailRS->idPayment_auth->setNewVal($idPaymentAuth);
            EditRS::updateStoredVals($pDetailRS);

        }

        return $pr;
    }

    protected static function caseDeclined(\PDO $dbh, PaymentResponse $pr, $username, PaymentRs $pRs = NULL, $attempts = 1) {

        $vr = $pr->response;

        $payRs = new PaymentRS();

        if ($vr->getTranType() == MpTranType::ReturnAmt) {
            $payRs->Is_Refund->setStoredVal(1);
        }

        $payRs->Payment_Date->setNewVal(date("Y-m-d H:i:s"));
        $payRs->idPayor->setNewVal($pr->idPayor);
        $payRs->idToken->setNewVal($pr->idToken);
        $payRs->idTrans->setNewVal($pr->getIdTrans());
        $payRs->idPayment_Method->setNewVal(PaymentMethod::Charge);
        $payRs->Status_Code->setNewVal(PaymentStatusCode::Declined);
        $payRs->Result->setNewVal(MpStatusValues::Declined);
        $payRs->Created_By->setNewVal($username);
        $payRs->Attempt->setNewVal($attempts);
        $payRs->Amount->setNewVal($vr->getAuthorizeAmount());
        $payRs->Notes->setNewVal($pr->payNotes);

        $idPmt = EditRS::insert($dbh, $payRs);
        $payRs->idPayment->setNewVal($idPmt);
        EditRS::updateStoredVals($payRs);
        $pr->paymentRs = $payRs;

        if ($idPmt > 0) {

            //Payment Detail
            $pDetailRS = new Payment_AuthRS();
            $pDetailRS->idPayment->setNewVal($idPmt);
            $pDetailRS->Approved_Amount->setNewVal($vr->getAuthorizeAmount());
            $pDetailRS->Approval_Code->setNewVal($vr->getAuthCode());
            $pDetailRS->Reference_Num->setNewVal($vr->getRefNo());
            $pDetailRS->Acct_Number->setNewVal($pr->cardNum);
            $pDetailRS->Card_Type->setNewVal($vr->getCardType());
            $pDetailRS->AVS->setNewVal($vr->getAVSResult());
            $pDetailRS->Invoice_Number->setNewVal($vr->getInvoice());
            $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
            $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
            $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
            $pDetailRS->Code3->setNewVal($vr->getCvvResult());

            $pDetailRS->Updated_By->setNewVal($username);
            $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
            $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::Declined);

            $idPaymentAuth = EditRS::insert($dbh, $pDetailRS);
            $pDetailRS->idPayment_auth->setNewVal($idPaymentAuth);
            EditRS::updateStoredVals($pDetailRS);

        }

        return $pr;
    }

}


class VoidReply extends CreditPayments {

    protected static function caseApproved(\PDO $dbh, PaymentResponse $pr, $username, PaymentRs $payRs = NULL, $attempts = 1){

        if (is_null($payRs) || $payRs->idPayment->getStoredVal() == 0) {
            throw new Hk_Exception_Payment('Payment Id not given.  ');
        }

        $vr = $pr->response;

        // Payment record
        $payRs->Status_Code->setNewVal(PaymentStatusCode::VoidSale);
        $payRs->Updated_By->setNewVal($username);
        $payRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

        // payment Note
        if ($pr->payNotes != '') {

            if ($payRs->Notes->getStoredVal() != '') {
                $payRs->Notes->setNewVal($payRs->Notes->getStoredVal() . " | " . $pr->payNotes);
            } else {
                $payRs->Notes->setNewVal($pr->payNotes);
            }
        }

        EditRS::update($dbh, $payRs, array($payRs->idPayment));
        EditRS::updateStoredVals($payRs);
        $pr->paymentRs = $payRs;

        // Payment Detail
        $pDetailRS = new Payment_AuthRS();
        $pDetailRS->idPayment->setNewVal($payRs->idPayment->getStoredVal());
        $pDetailRS->Approved_Amount->setNewVal($vr->getAuthorizeAmount());
        $pDetailRS->Approval_Code->setNewVal($vr->getAuthCode());
        $pDetailRS->Reference_Num->setNewVal($vr->getRefNo());
        $pDetailRS->AVS->setNewVal($vr->getAVSResult());
        $pDetailRS->Acct_Number->setNewVal($pr->cardNum);
        $pDetailRS->Card_Type->setNewVal($vr->getCardType());
        $pDetailRS->Invoice_Number->setNewVal($vr->getInvoice());
        $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
        $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
        $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
        $pDetailRS->Code3->setNewVal($vr->getCvvResult());

        $pDetailRS->Updated_By->setNewVal($username);
        $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::VoidSale);

        $idPaymentAuth = EditRS::insert($dbh, $pDetailRS);
        $pDetailRS->idPayment_auth->setNewVal($idPaymentAuth);
        EditRS::updateStoredVals($pDetailRS);


        return $pr;

    }

    protected static function caseDeclined(\PDO $dbh, PaymentResponse $pr, $username, PaymentRs $payRs = NULL, $attempts = 1) {

        if ($pr->response->getMessage() == 'ITEM VOIDED') {
            $pr = self::caseApproved($dbh, $pr, $username, $payRs, $attempts);
        }
        return $pr;
    }
}

class ReverseReply extends CreditPayments {

    protected static function caseApproved(\PDO $dbh, PaymentResponse $pr, $username, PaymentRs $payRs = NULL, $attempts = 1){

        if (is_null($payRs) || $payRs->idPayment->getStoredVal() == 0) {
            throw new Hk_Exception_Payment('Payment Id not given.  ');
        }

        $vr = $pr->response;

        // Payment record
        $payRs->Status_Code->setNewVal(PaymentStatusCode::Reverse);
        $payRs->Updated_By->setNewVal($username);
        $payRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

        // payment Note
        if ($pr->payNotes != '') {

            if ($payRs->Notes->getStoredVal() != '') {
                $payRs->Notes->setNewVal($payRs->Notes->getStoredVal() . " | " . $pr->payNotes);
            } else {
                $payRs->Notes->setNewVal($pr->payNotes);
            }
        }

        EditRS::update($dbh, $payRs, array($payRs->idPayment));
        EditRS::updateStoredVals($payRs);
        $pr->paymentRs = $payRs;


        // Payment Detail
        $pDetailRS = new Payment_AuthRS();
        $pDetailRS->idPayment->setNewVal($payRs->idPayment->getStoredVal());
        $pDetailRS->Approved_Amount->setNewVal($vr->getAuthorizeAmount());
        $pDetailRS->Approval_Code->setNewVal($vr->getAuthCode());
        $pDetailRS->Reference_Num->setNewVal($vr->getRefNo());
        $pDetailRS->AVS->setNewVal($vr->getAVSResult());
        $pDetailRS->Acct_Number->setNewVal($pr->cardNum);
        $pDetailRS->Card_Type->setNewVal($vr->getCardType());
        $pDetailRS->Invoice_Number->setNewVal($vr->getInvoice());
        $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
        $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
        $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
        $pDetailRS->Code3->setNewVal($vr->getCvvResult());

        $pDetailRS->Updated_By->setNewVal($username);
        $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::Reverse);

        $idPaymentAuth = EditRS::insert($dbh, $pDetailRS);
        $pDetailRS->idPayment_auth->setNewVal($idPaymentAuth);
        EditRS::updateStoredVals($pDetailRS);
        //$pr->paymentAuthRs = $pDetailRS;

        return $pr;

    }
}


class ReturnReply extends CreditPayments {

    protected static function caseApproved(\PDO $dbh, PaymentResponse $pr, $username, PaymentRs $payRs = NULL, $attempts = 1){

        $vr = $pr->response;

        if (is_null($payRs)) {

            // New Return payment
            $payRs = new PaymentRS();

            $payRs->Amount->setNewVal($vr->getAuthorizeAmount());
            $payRs->Payment_Date->setNewVal(date("Y-m-d H:i:s"));
            $payRs->idPayor->setNewVal($pr->idPayor);
            $payRs->idTrans->setNewVal($pr->getIdTrans());
            $payRs->idToken->setNewVal($pr->idToken);
            $payRs->idPayment_Method->setNewVal(PaymentMethod::Charge);
            $payRs->Result->setNewVal(MpStatusValues::Approved);
            $payRs->Attempt->setNewVal($attempts);
            $payRs->Is_Refund->setNewVal(1);
            $payRs->Status_Code->setNewVal(PaymentStatusCode::Paid);
            $payRs->Created_By->setNewVal($username);

        } else if ($payRs->idPayment->getStoredVal() > 0) {

            // Update existing Payment record
            $payRs->Status_Code->setNewVal(PaymentStatusCode::Retrn);
            $payRs->Updated_By->setNewVal($username);
            $payRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

        } else {
            throw new Hk_Exception_Payment('Payment Id not given.  ');
        }



        // payment Note
        if ($pr->payNotes != '') {

            if ($payRs->Notes->getStoredVal() != '') {
                $payRs->Notes->setNewVal($payRs->Notes->getStoredVal() . " | " . $pr->payNotes);
            } else {
                $payRs->Notes->setNewVal($pr->payNotes);
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
        $pr->paymentRs = $payRs;



        //Payment Detail
        $pDetailRS = new Payment_AuthRS();
        $pDetailRS->idPayment->setNewVal($payRs->idPayment->getStoredVal());
        $pDetailRS->Approved_Amount->setNewVal($vr->getAuthorizeAmount());
        $pDetailRS->Approval_Code->setNewVal($vr->getAuthCode());
        $pDetailRS->Reference_Num->setNewVal($vr->getRefNo());
        $pDetailRS->AVS->setNewVal($vr->getAVSResult());
        $pDetailRS->Acct_Number->setNewVal($pr->cardNum);
        $pDetailRS->Card_Type->setNewVal($vr->getCardType());
        $pDetailRS->Invoice_Number->setNewVal($vr->getInvoice());
        $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
        $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
        $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
        $pDetailRS->Code3->setNewVal($vr->getCvvResult());
        $pDetailRS->Updated_By->setNewVal($username);
        $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::Retrn);

        $idPaymentAuth = EditRS::insert($dbh, $pDetailRS);

        $pDetailRS->idPayment_auth->setNewVal($idPaymentAuth);
        EditRS::updateStoredVals($pDetailRS);


        return $pr;

    }

    protected static function caseDeclined(\PDO $dbh, PaymentResponse $pr, $username, PaymentRs $pRs = NULL, $attempts = 1) {

        if (is_null($pRs)) {

            $vr = $pr->response;

            $payRs = new PaymentRS();

            $payRs->Payment_Date->setNewVal(date("Y-m-d H:i:s"));
            $payRs->idPayor->setNewVal($pr->idPayor);
            $payRs->idToken->setNewVal($pr->idToken);
            $payRs->idTrans->setNewVal($pr->getIdTrans());
            $payRs->idPayment_Method->setNewVal(PaymentMethod::Charge);
            $payRs->Status_Code->setNewVal(PaymentStatusCode::Declined);
            $payRs->Result->setNewVal(MpStatusValues::Declined);
            $payRs->Created_By->setNewVal($username);
            $payRs->Attempt->setNewVal($attempts);
            $payRs->Is_Refund->setNewVal(1);
            $payRs->Amount->setNewVal($vr->getAuthorizeAmount());
            $payRs->Balance->setNewVal($vr->getAuthorizeAmount());
            $payRs->Notes->setNewVal($pr->payNotes);

            $idPmt = EditRS::insert($dbh, $payRs);
            $payRs->idPayment->setNewVal($idPmt);
            EditRS::updateStoredVals($payRs);
            $pr->paymentRs = $payRs;

            if ($idPmt > 0) {

                //Payment Detail
                $pDetailRS = new Payment_AuthRS();
                $pDetailRS->idPayment->setNewVal($idPmt);
                $pDetailRS->Approved_Amount->setNewVal($vr->getAuthorizeAmount());
                $pDetailRS->Approval_Code->setNewVal($vr->getAuthCode());
                $pDetailRS->Reference_Num->setNewVal($vr->getRefNo());
                $pDetailRS->Acct_Number->setNewVal($pr->cardNum);
                $pDetailRS->Card_Type->setNewVal($vr->getCardType());
                $pDetailRS->AVS->setNewVal($vr->getAVSResult());
                $pDetailRS->Invoice_Number->setNewVal($vr->getInvoice());
                $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
                $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
                $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
                $pDetailRS->Code3->setNewVal($vr->getCvvResult());

                $pDetailRS->Updated_By->setNewVal($username);
                $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
                $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::Declined);

                $idPaymentAuth = EditRS::insert($dbh, $pDetailRS);
                $pDetailRS->idPayment_auth->setNewVal($idPaymentAuth);
                EditRS::updateStoredVals($pDetailRS);

            }
        }

        return $pr;
    }

}

class VoidReturnReply extends CreditPayments {

    protected static function caseApproved(\PDO $dbh, PaymentResponse $pr, $username, PaymentRs $payRs = NULL, $attempts = 1){

        if (is_null($payRs) || $payRs->idPayment->getStoredVal() == 0) {
            throw new Hk_Exception_Payment('Payment Id is undefined (0).  ');
        }

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
        if ($pr->payNotes != '') {

            if ($payRs->Notes->getStoredVal() != '') {
                $payRs->Notes->setNewVal($payRs->Notes->getStoredVal() . " | " . $pr->payNotes);
            } else {
                $payRs->Notes->setNewVal($pr->payNotes);
            }
        }

        EditRS::update($dbh, $payRs, array($payRs->idPayment));
        EditRS::updateStoredVals($payRs);
        $pr->paymentRs = $payRs;

        // Payment Detail
        $pDetailRS = new Payment_AuthRS();
        $pDetailRS->idPayment->setNewVal($payRs->idPayment->getStoredVal());
        $pDetailRS->Approved_Amount->setNewVal($vr->getAuthorizeAmount());
        $pDetailRS->Approval_Code->setNewVal($vr->getAuthCode());
        $pDetailRS->Reference_Num->setNewVal($vr->getRefNo());
        $pDetailRS->AVS->setNewVal($vr->getAVSResult());
        $pDetailRS->Acct_Number->setNewVal($pr->cardNum);
        $pDetailRS->Card_Type->setNewVal($vr->getCardType());
        $pDetailRS->Invoice_Number->setNewVal($vr->getInvoice());
        $pDetailRS->idTrans->setNewVal($pr->idTrans);
        $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
        $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
        $pDetailRS->Code3->setNewVal($vr->getCvvResult());
        $pDetailRS->Updated_By->setNewVal($username);
        $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::VoidReturn);

        $idPaymentAuth = EditRS::insert($dbh, $pDetailRS);

        $pDetailRS->idPayment_auth->setNewVal($idPaymentAuth);
        EditRS::updateStoredVals($pDetailRS);

        return $pr;

    }

    protected static function caseDeclined(\PDO $dbh, PaymentResponse $pr, $userName, PaymentRs $payRs = NULL, $attempts = 1) {
        // todo:  Return a timed out message - only works on un-captured transactions.
        return $pr;
    }

}
