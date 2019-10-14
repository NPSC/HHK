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

    public static function processReply(\PDO $dbh, PaymentResponse $pr, $userName, $payRs = NULL, $attempts = 1) {

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


    protected static function caseApproved(\PDO $dbh, PaymentResponse $pr, $userName, $payRs = NULL, $attempts = 1) {
        throw new Hk_Exception_Payment('Payments::caseApproved Method not overridden!');
    }

    protected static function caseDeclined(\PDO $dbh, PaymentResponse $pr, $userName, $payRs = NULL, $attempts = 1) {
        return $pr;
    }
    protected static function caseOther(\PDO $dbh, PaymentResponse $pr, $userName, $payRs = NULL, $attempts = 1) {
        return $pr;
    }

}

class SaleReply extends CreditPayments {


    protected static function caseApproved(\PDO $dbh, PaymentResponse $pr, $username, $pRs = NULL, $attempts = 1) {

        $uS = Session::getInstance();
        $vr = $pr->response;

        // Store any tokens
        $pr->setIdToken(CreditToken::storeToken($dbh, $pr->idRegistration, $pr->idPayor, $vr, $pr->getIdToken()));

        // Check for replay - AP*
        if ($vr->getResponseMessage() == MpStatusMessage::Replay) {

            // Find previous response, if we caught it.
            $paRs = new Payment_AuthRS();
            $paRs->Approval_Code->setStoredVal($vr->getAuthCode());
            $paRs->Invoice_Number->setStoredVal($vr->getInvoiceNumber());

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

        $payRs->Amount->setNewVal($pr->getAmount());
        $payRs->Payment_Date->setNewVal(date("Y-m-d H:i:s"));
        $payRs->idPayor->setNewVal($pr->idPayor);
        $payRs->idTrans->setNewVal($pr->getIdTrans());
        $payRs->idToken->setNewVal($pr->getIdToken());
        $payRs->idPayment_Method->setNewVal(PaymentMethod::Charge);
        $payRs->Result->setNewVal(MpStatusValues::Approved);
        $payRs->Attempt->setNewVal($attempts);
        $payRs->Status_Code->setNewVal(PaymentStatusCode::Paid);
        $payRs->Created_By->setNewVal($username);
        $payRs->Notes->setNewVal($pr->payNotes);

        $idPayment = EditRS::insert($dbh, $payRs);
        $payRs->idPayment->setNewVal($idPayment);
        EditRS::updateStoredVals($payRs);

        $pr->setPaymentDate(date("Y-m-d H:i:s"));
        $pr->paymentRs = $payRs;

        if ($idPayment > 0) {

            //Payment Detail
            $pDetailRS = new Payment_AuthRS();
            $pDetailRS->idPayment->setNewVal($idPayment);
            $pDetailRS->Approved_Amount->setNewVal($vr->getAuthorizedAmount());
            $pDetailRS->Approval_Code->setNewVal($vr->getAuthCode());
            $pDetailRS->Status_Message->setNewVal($vr->getResponseMessage());
            $pDetailRS->Reference_Num->setNewVal($vr->getRefNo());
            $pDetailRS->Acct_Number->setNewVal($vr->getMaskedAccount());
            $pDetailRS->Card_Type->setNewVal($vr->getCardType());
            $pDetailRS->Cardholder_Name->setNewVal($vr->getCardHolderName());
            $pDetailRS->AVS->setNewVal($vr->getAVSResult());
            $pDetailRS->Invoice_Number->setNewVal($pr->getInvoiceNumber());
            $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
            $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
            $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
            $pDetailRS->CVV->setNewVal($vr->getCvvResult());
            $pDetailRS->Processor->setNewVal($uS->PaymentGateway);
            $pDetailRS->Response_Message->setNewVal($vr->getAuthorizationText());
            $pDetailRS->Response_Code->setNewVal($vr->getTransactionStatus());
            $pDetailRS->Customer_Id->setNewVal($vr->getOperatorId());
            $pDetailRS->Signature_Required->setNewVal($vr->SignatureRequired());
            $pDetailRS->PartialPayment->setNewVal($vr->getPartialPaymentAmount() > 0 ? 1 : 0);

            $pDetailRS->Updated_By->setNewVal($username);
            $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
            $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::Paid);

            // EMV
            $pDetailRS->EMVApplicationIdentifier->setNewVal($vr->getEMVApplicationIdentifier());
            $pDetailRS->EMVApplicationResponseCode->setNewVal($vr->getEMVApplicationResponseCode());
            $pDetailRS->EMVIssuerApplicationData->setNewVal($vr->getEMVIssuerApplicationData());
            $pDetailRS->EMVTerminalVerificationResults->setNewVal($vr->getEMVTerminalVerificationResults());
            $pDetailRS->EMVTransactionStatusInformation->setNewVal($vr->getEMVTransactionStatusInformation());


            $pr->idPaymentAuth = EditRS::insert($dbh, $pDetailRS);

        }

       return $pr;
    }

    protected static function caseDeclined(\PDO $dbh, PaymentResponse $pr, $username, $pRs = NULL, $attempts = 1) {

        $uS = Session::getInstance();
        $vr = $pr->response;

        $payRs = new PaymentRS();

        if ($vr->getTranType() == MpTranType::ReturnAmt) {
            $payRs->Is_Refund->setStoredVal(1);
        }

        $payRs->Payment_Date->setNewVal(date("Y-m-d H:i:s"));
        $payRs->idPayor->setNewVal($pr->idPayor);
        $payRs->idToken->setNewVal($pr->getIdToken());
        $payRs->idTrans->setNewVal($pr->getIdTrans());
        $payRs->idPayment_Method->setNewVal(PaymentMethod::Charge);
        $payRs->Status_Code->setNewVal(PaymentStatusCode::Declined);
        $payRs->Result->setNewVal(MpStatusValues::Declined);
        $payRs->Created_By->setNewVal($username);
        $payRs->Attempt->setNewVal($attempts);
        $payRs->Amount->setNewVal($pr->getAmount());
        $payRs->Notes->setNewVal($pr->payNotes);

        $idPmt = EditRS::insert($dbh, $payRs);
        $payRs->idPayment->setNewVal($idPmt);
        EditRS::updateStoredVals($payRs);

        $pr->setPaymentDate(date("Y-m-d H:i:s"));
        $pr->paymentRs = $payRs;

        if ($idPmt > 0) {

            //Payment Detail
            $pDetailRS = new Payment_AuthRS();
            $pDetailRS->idPayment->setNewVal($idPmt);
            $pDetailRS->Approved_Amount->setNewVal($vr->getAuthorizedAmount());
            $pDetailRS->Approval_Code->setNewVal($vr->getAuthCode());
            $pDetailRS->Status_Message->setNewVal($vr->getResponseMessage());
            $pDetailRS->Reference_Num->setNewVal($vr->getRefNo());
            $pDetailRS->Acct_Number->setNewVal($vr->getMaskedAccount());
            $pDetailRS->Card_Type->setNewVal($vr->getCardType());
            $pDetailRS->Cardholder_Name->setNewVal($vr->getCardHolderName());
            $pDetailRS->AVS->setNewVal($vr->getAVSResult());
            $pDetailRS->Invoice_Number->setNewVal($pr->getInvoiceNumber());
            $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
            $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
            $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
            $pDetailRS->CVV->setNewVal($vr->getCvvResult());
            $pDetailRS->Processor->setNewVal($uS->PaymentGateway);
            $pDetailRS->Response_Message->setNewVal($vr->getAuthorizationText());
            $pDetailRS->Response_Code->setNewVal($vr->getTransactionStatus());
            $pDetailRS->Customer_Id->setNewVal($vr->getOperatorId());
            $pDetailRS->Signature_Required->setNewVal($vr->SignatureRequired());
            $pDetailRS->PartialPayment->setNewVal($vr->getPartialPaymentAmount() > 0 ? 1 : 0);

            $pDetailRS->Updated_By->setNewVal($username);
            $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
            $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::Declined);

            // EMV
            $pDetailRS->EMVApplicationIdentifier->setNewVal($vr->getEMVApplicationIdentifier());
            $pDetailRS->EMVApplicationResponseCode->setNewVal($vr->getEMVApplicationResponseCode());
            $pDetailRS->EMVIssuerApplicationData->setNewVal($vr->getEMVIssuerApplicationData());
            $pDetailRS->EMVTerminalVerificationResults->setNewVal($vr->getEMVTerminalVerificationResults());
            $pDetailRS->EMVTransactionStatusInformation->setNewVal($vr->getEMVTransactionStatusInformation());

            $pr->idPaymentAuth = EditRS::insert($dbh, $pDetailRS);

        }

        return $pr;
    }

}


class VoidReply extends CreditPayments {

    protected static function caseApproved(\PDO $dbh, PaymentResponse $pr, $username, $payRs = NULL, $attempts = 1){

        if (is_null($payRs) || $payRs->idPayment->getStoredVal() == 0) {
            throw new Hk_Exception_Payment('Payment Id not given.  ');
        }

        $uS = Session::getInstance();
        $vr = $pr->response;

        // Store any tokens
        $pr->setIdToken(CreditToken::storeToken($dbh, $pr->idRegistration, $pr->idPayor, $vr, $pr->getIdToken()));

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

        $pr->setPaymentDate(date("Y-m-d H:i:s"));
        $pr->paymentRs = $payRs;

        // Payment Detail
        $pDetailRS = new Payment_AuthRS();
        $pDetailRS->idPayment->setNewVal($payRs->idPayment->getStoredVal());
        $pDetailRS->Approved_Amount->setNewVal($pr->getAmount());
        $pDetailRS->Approval_Code->setNewVal($vr->getAuthCode());
        $pDetailRS->Reference_Num->setNewVal($vr->getRefNo());
        $pDetailRS->AVS->setNewVal($vr->getAVSResult());
        $pDetailRS->Acct_Number->setNewVal($vr->getMaskedAccount());
        $pDetailRS->Card_Type->setNewVal($vr->getCardType());
            $pDetailRS->Cardholder_Name->setNewVal($vr->getCardHolderName());
        $pDetailRS->Invoice_Number->setNewVal($vr->getInvoiceNumber());
        $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
        $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
        $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
        $pDetailRS->CVV->setNewVal($vr->getCvvResult());
        $pDetailRS->Processor->setNewVal($uS->PaymentGateway);
        $pDetailRS->Response_Code->setNewVal($vr->getTransactionStatus());
        $pDetailRS->Response_Message->setNewVal($vr->getAuthorizationText());
            $pDetailRS->Signature_Required->setNewVal($vr->SignatureRequired());
            $pDetailRS->PartialPayment->setNewVal($vr->getPartialPaymentAmount() > 0 ? 1 : 0);

        // EMV
        $pDetailRS->EMVApplicationIdentifier->setNewVal($vr->getEMVApplicationIdentifier());
        $pDetailRS->EMVApplicationResponseCode->setNewVal($vr->getEMVApplicationResponseCode());
        $pDetailRS->EMVIssuerApplicationData->setNewVal($vr->getEMVIssuerApplicationData());
        $pDetailRS->EMVTerminalVerificationResults->setNewVal($vr->getEMVTerminalVerificationResults());
        $pDetailRS->EMVTransactionStatusInformation->setNewVal($vr->getEMVTransactionStatusInformation());

        $pDetailRS->Updated_By->setNewVal($username);
        $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::VoidSale);

        $pr->idPaymentAuth = EditRS::insert($dbh, $pDetailRS);

//        $pDetailRS->idPayment_auth->setNewVal($idPaymentAuth);
//        EditRS::updateStoredVals($pDetailRS);


        return $pr;

    }

    protected static function caseDeclined(\PDO $dbh, PaymentResponse $pr, $username, $payRs = NULL, $attempts = 1) {

        if ($pr->response->getResponseMessage() == 'ITEM VOIDED') {
            $pr = self::caseApproved($dbh, $pr, $username, $payRs, $attempts);
        }
        return $pr;
    }
}

class ReverseReply extends CreditPayments {

    protected static function caseApproved(\PDO $dbh, PaymentResponse $pr, $username, $payRs = NULL, $attempts = 1){

        if (is_null($payRs) || $payRs->idPayment->getStoredVal() == 0) {
            throw new Hk_Exception_Payment('Payment Id not given.  ');
        }

        $uS = Session::getInstance();
        $vr = $pr->response;

        // Store any tokens
        $pr->setIdToken(CreditToken::storeToken($dbh, $pr->idRegistration, $pr->idPayor, $vr, $pr->getIdToken()));

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

        $pr->setPaymentDate(date("Y-m-d H:i:s"));
        $pr->paymentRs = $payRs;


        // Payment Detail
        $pDetailRS = new Payment_AuthRS();
        $pDetailRS->idPayment->setNewVal($payRs->idPayment->getStoredVal());
        $pDetailRS->Approved_Amount->setNewVal($vr->getAuthorizedAmount());
        $pDetailRS->Approval_Code->setNewVal($vr->getAuthCode());
        $pDetailRS->Reference_Num->setNewVal($vr->getRefNo());
        $pDetailRS->AVS->setNewVal($vr->getAVSResult());
        $pDetailRS->Acct_Number->setNewVal($vr->getMaskedAccount());
        $pDetailRS->Card_Type->setNewVal($vr->getCardType());
            $pDetailRS->Cardholder_Name->setNewVal($vr->getCardHolderName());
        $pDetailRS->Invoice_Number->setNewVal($vr->getInvoiceNumber());
        $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
        $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
        $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
        $pDetailRS->CVV->setNewVal($vr->getCvvResult());
        $pDetailRS->Processor->setNewVal($uS->PaymentGateway);
        $pDetailRS->Response_Code->setNewVal($vr->getTransactionStatus());
        $pDetailRS->Response_Message->setNewVal($vr->getAuthorizationText());
            $pDetailRS->Signature_Required->setNewVal($vr->SignatureRequired());
            $pDetailRS->PartialPayment->setNewVal($vr->getPartialPaymentAmount() > 0 ? 1 : 0);

        // EMV
        $pDetailRS->EMVApplicationIdentifier->setNewVal($vr->getEMVApplicationIdentifier());
        $pDetailRS->EMVApplicationResponseCode->setNewVal($vr->getEMVApplicationResponseCode());
        $pDetailRS->EMVIssuerApplicationData->setNewVal($vr->getEMVIssuerApplicationData());
        $pDetailRS->EMVTerminalVerificationResults->setNewVal($vr->getEMVTerminalVerificationResults());
        $pDetailRS->EMVTransactionStatusInformation->setNewVal($vr->getEMVTransactionStatusInformation());

        $pDetailRS->Updated_By->setNewVal($username);
        $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::Reverse);

        $pr->idPaymentAuth = EditRS::insert($dbh, $pDetailRS);

        return $pr;

    }
}


class ReturnReply extends CreditPayments {

    protected static function caseApproved(\PDO $dbh, PaymentResponse $pr, $username, $payRs = NULL, $attempts = 1){

        $uS = Session::getInstance();
        $vr = $pr->response;

        // Store any tokens
        $pr->setIdToken(CreditToken::storeToken($dbh, $pr->idRegistration, $pr->idPayor, $vr, $pr->getIdToken()));

        if (is_null($payRs)) {

            // New Return payment
            $payRs = new PaymentRS();

            $payRs->Amount->setNewVal($pr->getAmount());
            $payRs->Payment_Date->setNewVal(date("Y-m-d H:i:s"));
            $payRs->idPayor->setNewVal($pr->idPayor);
            $payRs->idTrans->setNewVal($pr->getIdTrans());
            $payRs->idToken->setNewVal($pr->getToken());
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

        $pr->setPaymentDate(date("Y-m-d H:i:s"));
        $pr->paymentRs = $payRs;



        //Payment Detail
        $pDetailRS = new Payment_AuthRS();
        $pDetailRS->idPayment->setNewVal($payRs->idPayment->getStoredVal());
        $pDetailRS->Approved_Amount->setNewVal($vr->getAuthorizedAmount());
        $pDetailRS->Approval_Code->setNewVal($vr->getAuthCode());
        $pDetailRS->Reference_Num->setNewVal($vr->getRefNo());
        $pDetailRS->AVS->setNewVal($vr->getAVSResult());
        $pDetailRS->Acct_Number->setNewVal($vr->getMaskedAccount());
        $pDetailRS->Card_Type->setNewVal($vr->getCardType());
            $pDetailRS->Cardholder_Name->setNewVal($vr->getCardHolderName());
        $pDetailRS->Invoice_Number->setNewVal($vr->getInvoiceNumber());
        $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
        $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
        $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
        $pDetailRS->CVV->setNewVal($vr->getCvvResult());
        $pDetailRS->Updated_By->setNewVal($username);
        $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::Retrn);
        $pDetailRS->Processor->setNewVal($uS->PaymentGateway);
        $pDetailRS->Response_Code->setNewVal($vr->getTransactionStatus());
        $pDetailRS->Response_Message->setNewVal($vr->getAuthorizationText());
            $pDetailRS->Signature_Required->setNewVal($vr->SignatureRequired());
            $pDetailRS->PartialPayment->setNewVal($vr->getPartialPaymentAmount() > 0 ? 1 : 0);

        // EMV
        $pDetailRS->EMVApplicationIdentifier->setNewVal($vr->getEMVApplicationIdentifier());
        $pDetailRS->EMVApplicationResponseCode->setNewVal($vr->getEMVApplicationResponseCode());
        $pDetailRS->EMVIssuerApplicationData->setNewVal($vr->getEMVIssuerApplicationData());
        $pDetailRS->EMVTerminalVerificationResults->setNewVal($vr->getEMVTerminalVerificationResults());
        $pDetailRS->EMVTransactionStatusInformation->setNewVal($vr->getEMVTransactionStatusInformation());

        $pr->idPaymentAuth = EditRS::insert($dbh, $pDetailRS);

        return $pr;

    }

    protected static function caseDeclined(\PDO $dbh, PaymentResponse $pr, $username, $pRs = NULL, $attempts = 1) {

        if (is_null($pRs)) {

            $uS = Session::getInstance();
            $vr = $pr->response;

            $payRs = new PaymentRS();

            $payRs->Payment_Date->setNewVal(date("Y-m-d H:i:s"));
            $payRs->idPayor->setNewVal($pr->idPayor);
            $payRs->idToken->setNewVal($pr->getToken());
            $payRs->idTrans->setNewVal($pr->getIdTrans());
            $payRs->idPayment_Method->setNewVal(PaymentMethod::Charge);
            $payRs->Status_Code->setNewVal(PaymentStatusCode::Declined);
            $payRs->Result->setNewVal(MpStatusValues::Declined);
            $payRs->Created_By->setNewVal($username);
            $payRs->Attempt->setNewVal($attempts);
            $payRs->Is_Refund->setNewVal(1);
            $payRs->Amount->setNewVal($pr->getAmount());
            $payRs->Balance->setNewVal($vr->getAuthorizedAmount());
            $payRs->Notes->setNewVal($pr->payNotes);

            $idPmt = EditRS::insert($dbh, $payRs);
            $payRs->idPayment->setNewVal($idPmt);
            EditRS::updateStoredVals($payRs);

            $pr->setPaymentDate(date("Y-m-d H:i:s"));
            $pr->paymentRs = $payRs;

            if ($idPmt > 0) {

                //Payment Detail
                $pDetailRS = new Payment_AuthRS();
                $pDetailRS->idPayment->setNewVal($idPmt);
                $pDetailRS->Approved_Amount->setNewVal($vr->getAuthorizedAmount());
                $pDetailRS->Approval_Code->setNewVal($vr->getAuthCode());
                $pDetailRS->Reference_Num->setNewVal($vr->getRefNo());
                $pDetailRS->Acct_Number->setNewVal($vr->getMaskedAccount());
                $pDetailRS->Card_Type->setNewVal($vr->getCardType());
            $pDetailRS->Cardholder_Name->setNewVal($vr->getCardHolderName());
                $pDetailRS->AVS->setNewVal($vr->getAVSResult());
                $pDetailRS->Invoice_Number->setNewVal($vr->getInvoiceNumber());
                $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
                $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
                $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
                $pDetailRS->CVV->setNewVal($vr->getCvvResult());
                $pDetailRS->Processor->setNewVal($uS->PaymentGateway);
                $pDetailRS->Response_Code->setNewVal($vr->getTransactionStatus());
                $pDetailRS->Response_Message->setNewVal($vr->getAuthorizationText());
            $pDetailRS->Signature_Required->setNewVal($vr->SignatureRequired());
            $pDetailRS->PartialPayment->setNewVal($vr->getPartialPaymentAmount() > 0 ? 1 : 0);

                // EMV
                $pDetailRS->EMVApplicationIdentifier->setNewVal($vr->getEMVApplicationIdentifier());
                $pDetailRS->EMVApplicationResponseCode->setNewVal($vr->getEMVApplicationResponseCode());
                $pDetailRS->EMVIssuerApplicationData->setNewVal($vr->getEMVIssuerApplicationData());
                $pDetailRS->EMVTerminalVerificationResults->setNewVal($vr->getEMVTerminalVerificationResults());
                $pDetailRS->EMVTransactionStatusInformation->setNewVal($vr->getEMVTransactionStatusInformation());

                $pDetailRS->Updated_By->setNewVal($username);
                $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
                $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::Declined);

                $pr->idPaymentAuth = EditRS::insert($dbh, $pDetailRS);

            }
        }

        return $pr;
    }

}

class VoidReturnReply extends CreditPayments {

    protected static function caseApproved(\PDO $dbh, PaymentResponse $pr, $username, $payRs = NULL, $attempts = 1){

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
        if ($pr->payNotes != '') {

            if ($payRs->Notes->getStoredVal() != '') {
                $payRs->Notes->setNewVal($payRs->Notes->getStoredVal() . " | " . $pr->payNotes);
            } else {
                $payRs->Notes->setNewVal($pr->payNotes);
            }
        }

        EditRS::update($dbh, $payRs, array($payRs->idPayment));
        EditRS::updateStoredVals($payRs);

        $pr->setPaymentDate(date("Y-m-d H:i:s"));
        $pr->paymentRs = $payRs;

        // Payment Detail
        $pDetailRS = new Payment_AuthRS();
        $pDetailRS->idPayment->setNewVal($payRs->idPayment->getStoredVal());
        $pDetailRS->Approved_Amount->setNewVal($vr->getAuthorizedAmount());
        $pDetailRS->Approval_Code->setNewVal($vr->getAuthCode());
        $pDetailRS->Reference_Num->setNewVal($vr->getRefNo());
        $pDetailRS->AVS->setNewVal($vr->getAVSResult());
        $pDetailRS->Acct_Number->setNewVal($vr->getMaskedAccount());
        $pDetailRS->Card_Type->setNewVal($vr->getCardType());
            $pDetailRS->Cardholder_Name->setNewVal($vr->getCardHolderName());
        $pDetailRS->Invoice_Number->setNewVal($vr->getInvoiceNumber());
        $pDetailRS->idTrans->setNewVal($pr->idTrans);
        $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
        $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
        $pDetailRS->CVV->setNewVal($vr->getCvvResult());
        $pDetailRS->Updated_By->setNewVal($username);
        $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::VoidReturn);
        $pDetailRS->Processor->setNewVal($uS->PaymentGateway);
        $pDetailRS->Response_Code->setNewVal($vr->getTransactionStatus());
        $pDetailRS->Response_Message->setNewVal($vr->getAuthorizationText());
            $pDetailRS->Signature_Required->setNewVal($vr->SignatureRequired());
            $pDetailRS->PartialPayment->setNewVal($vr->getPartialPaymentAmount() > 0 ? 1 : 0);

        $pr->idPaymentAuth = EditRS::insert($dbh, $pDetailRS);

        return $pr;

    }

    protected static function caseDeclined(\PDO $dbh, PaymentResponse $pr, $userName, $payRs = NULL, $attempts = 1) {
        // todo:  Return a timed out message - only works on un-captured transactions.
        return $pr;
    }

}
