<?php
/**
 * Payments.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


abstract class PaymentResponse {

    protected $paymentType;
    protected $amountDue = 0.0;
    protected $invoiceNumber = '';
    protected $partialPaymentFlag;
    protected $amount;


    public $idPayor = 0;
    public $idVisit;
    public $idReservation;
    public $idRegistration;
    public $idTrans = 0;
    public $response;
    public $idGuestToken = 0;

    public $payNotes = '';


    /**
     *
     * @var PaymentRS
     */
    public $paymentRs;
    
    public abstract function getStatus();
    public abstract function receiptMarkup(\PDO $dbh, &$tbl);


    public function getPaymentType() {
        return $this->paymentType;
    }

    public function getAmountDue() {
        return $this->amountDue;
    }

    public function getAmount() {
        return $this->amount;
    }

    public function getInvoiceNumber() {
        return $this->invoiceNumber;
    }

    public function isPartialPayment() {
        return $this->partialPaymentFlag;
    }

    public function setPartialPayment($v) {
        if ($v) {
            $this->partialPaymentFlag = TRUE;
        } else {
            $this->partialPaymentFlag = FALSE;
        }
    }

    public function getIdPayment() {

        if (is_null($this->paymentRs) === FALSE) {
            return $this->paymentRs->idPayment->getStoredVal();
        }

        return 0;
    }


    public function getIdTrans() {
        return $this->idTrans;
    }

    public function setIdTrans($idTrans) {
        $this->idTrans = $idTrans;
        return $this;
    }

}


class ImPaymentResponse extends PaymentResponse {


    public $isEMV;

    function __construct(iGatewayResponse $vcr, $idPayor, $idGroup, $invoiceNumber, $payNotes) {
        $this->response = $vcr;
        $this->paymentType = PayType::Charge;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->invoiceNumber = $invoiceNumber;
        $this->isEMV = $vcr->isEMVTransaction();
        $this->payNotes = $payNotes;

        if ($vcr->getPartialPaymentAmount() > 0) {
            $this->setPartialPayment(TRUE);
        } else {
            $this->setPartialPayment(FALSE);
        }
    }

    public function getStatus() {

        $status = '';

        switch ($this->response->getResponseCode()) {

            case '000':
                $status = CreditPayments::STATUS_APPROVED;
                break;

            case '010':
                // Partial Payment
                $status = CreditPayments::STATUS_APPROVED;
                break;

            case '001':
                $status = CreditPayments::STATUS_DECLINED;
                break;

            case '003':
                $status = CreditPayments::STATUS_DECLINED;
                break;

            case '005':
                $status = CreditPayments::STATUS_DECLINED;
                break;

            case '051':
                $status = CreditPayments::STATUS_DECLINED;
                break;

            case '063':
                $status = CreditPayments::STATUS_DECLINED;
                break;

            default:
                $status = CreditPayments::STATUS_ERROR;
        }

        return $status;
    }

    public function receiptMarkup(\PDO $dbh, &$tbl) {

        $tbl->addBodyTr(HTMLTable::makeTd("Credit Card Total:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($this->getAmount(), 2)));
        $tbl->addBodyTr(HTMLTable::makeTd($this->response->getCardType() . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd($this->response->getMaskedAccount()));

        if ($this->response->getCardHolderName() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Card Holder: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($this->response->getCardHolderName()));
        }

        if ($this->response->getAuthCode() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Authorization Code: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getAuthCode(), array('style'=>'font-size:.8em;')));
        }

        if ($this->response->getResponseMessage() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Response Message Code: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getResponseMessage() . '  ' . $this->response->getResponseCode(), array('style'=>'font-size:.8em;')));
        }

        $this->getEMVItems($tbl);

        if ($this->response->getAuthorizationText() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($this->response->getAuthorizationText(), array('colspan'=>2)));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Sign: ", array('class'=>'tdlabel')) . HTMLTable::makeTd('', array('style'=>'height:35px; width:310px; border: solid 1px gray;')));

    }

    public function getEMVItems(&$tbl) {

        if ($this->response->isEMVTransaction()) {

            if ($this->response->getEMVCardEntryMode() != '') {
                $tbl->addBodyTr(HTMLTable::makeTd("Card Entry Mode: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getEMVCardEntryMode(), array('style'=>'font-size:.8em;')));
            }
            if ($this->response->getEMVAuthorizationMode() != '') {
                $tbl->addBodyTr(HTMLTable::makeTd("Mode: ", array('class'=>'tdlabel','style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getEMVAuthorizationMode(), array('style'=>'font-size:.8em;')));
            }
            if ($this->response->getEMVApplicationIdentifier() != '') {
                $tbl->addBodyTr(HTMLTable::makeTd("AID: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getEMVApplicationIdentifier(), array('style'=>'font-size:.8em;')));
            }
            if ($this->response->getEMVTerminalVerificationResults() != '') {
                $tbl->addBodyTr(HTMLTable::makeTd("TVR: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getEMVTerminalVerificationResults(), array('style'=>'font-size:.8em;')));
            }
            if ($this->response->getEMVIssuerApplicationData() != '') {
                $tbl->addBodyTr(HTMLTable::makeTd("IAD: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getEMVIssuerApplicationData(), array('style'=>'font-size:.8em;')));
            }
            if ($this->response->getEMVTransactionStatusInformation() != '') {
                $tbl->addBodyTr(HTMLTable::makeTd("TSI: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getEMVTransactionStatusInformation(), array('style'=>'font-size:.8em;')));
            }
            if ($this->response->getEMVApplicationResponseCode() != '') {
                $tbl->addBodyTr(HTMLTable::makeTd("ARC: ", array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd($this->response->getEMVApplicationResponseCode(), array('style'=>'font-size:.8em;')));
            }

        }

    }

}

//class ImVoidResponse extends PaymentResponse {
//
//
//    public $idToken = '';
//
//    function __construct(iGatewayResponse $vcr, $idPayor, $idGroup, $invoiceNumber, $payNotes) {
//        $this->response = $vcr;
//        $this->paymentType = PayType::Charge;
//        $this->idPayor = $idPayor;
//        $this->idRegistration = $idGroup;
//        $this->invoiceNumber = $invoiceNumber;
//        $this->payNotes = $payNotes;
//        $this->isEMV = $vcr->isEMVTransaction();
//    }
//
//    public function getStatus() {
//
//        switch ($this->response->getResponseCode()) {
//
//            case '000':
//                $status = CreditPayments::STATUS_APPROVED;
//                break;
//
//            case '001':
//                $status = CreditPayments::STATUS_DECLINED;
//                break;
//
//            case '003':
//                $status = CreditPayments::STATUS_DECLINED;
//                break;
//
//            case '005':
//                $status = CreditPayments::STATUS_DECLINED;
//                break;
//
//            case '051':
//                $status = CreditPayments::STATUS_DECLINED;
//                break;
//
//            case '063':
//                $status = CreditPayments::STATUS_DECLINED;
//                break;
//
//            default:
//                $status = CreditPayments::STATUS_ERROR;
//        }
//
//        return $status;
//    }
//
//}
//
//class ImReturnResponse extends PaymentResponse {
//
//
//    public $idToken = '';
//
//    function __construct(VerifyCurlResponse $vcr, $idPayor, $idGroup, $invoiceNumber, $payNotes) {
//        $this->response = $vcr;
//        $this->paymentType = PayType::Charge;
//        $this->idPayor = $idPayor;
//        $this->idRegistration = $idGroup;
//        $this->invoiceNumber = $invoiceNumber;
//        $this->payNotes = $payNotes;
//        $this->isEMV = $vcr->isEMVTransaction();
//    }
//
//    public function getStatus() {
//
//        switch ($this->response->getResponseCode()) {
//
//            case '000':
//                $status = CreditPayments::STATUS_APPROVED;
//                break;
//
//            case '001':
//                $status = CreditPayments::STATUS_DECLINED;
//                break;
//
//            case '003':
//                $status = CreditPayments::STATUS_DECLINED;
//                break;
//
//            case '005':
//                $status = CreditPayments::STATUS_DECLINED;
//                break;
//
//            case '051':
//                $status = CreditPayments::STATUS_DECLINED;
//                break;
//
//            case '063':
//                $status = CreditPayments::STATUS_DECLINED;
//                break;
//
//            default:
//                $status = CreditPayments::STATUS_ERROR;
//        }
//
//        return $status;
//    }
//
//}
//
class ImCofResponse extends PaymentResponse {

    public $idToken;
    public $isEMV;

    function __construct(iGatewayResponse $vcr, $idPayor, $idGroup) {
        $this->response = $vcr;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->idToken = $vcr->getToken();
        $this->isEMV = $vcr->isEMVTransaction();
    }

    public function getStatus() {

        switch ($this->response->getResponseCode()) {

            case '000':
                $status = CreditPayments::STATUS_APPROVED;
                break;

            case '001':
                $status = CreditPayments::STATUS_DECLINED;
                break;

            case '003':
                $status = CreditPayments::STATUS_DECLINED;
                break;

            case '005':
                $status = CreditPayments::STATUS_DECLINED;
                break;

            case '051':
                $status = CreditPayments::STATUS_DECLINED;
                break;

            case '063':
                $status = CreditPayments::STATUS_DECLINED;
                break;

            default:
                $status = CreditPayments::STATUS_ERROR;

        }

        return $status;
    }

    public function receiptMarkup(\PDO $dbh, &$tbl) {
        return array('error'=>'Receipts not available.');
    }

}

/**
 * Description of Payments
 *
 * @author Eric
 */
abstract class CreditPayments {

    const STATUS_APPROVED = 'AP';
    const STATUS_DECLINED = 'DECLINED';
    const STATUS_ERROR = 'Error';

    public static function processReply(\PDO $dbh, PaymentResponse $pr, $userName, PaymentRs $payRs = NULL, $attempts = 1) {

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

        // Store any tokens
        $idToken = CreditToken::storeToken($dbh, $pr->idRegistration, $pr->idPayor, $vr);


        // Check for replay - AP*
        if ($vr->getResponseMessage() == MpStatusMessage::Replay) {

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
        $payRs->idToken->setNewVal($idToken);
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
            $pDetailRS->Status_Message->setNewVal($vr->getResponseMessage());
            $pDetailRS->Reference_Num->setNewVal($vr->getRefNo());
            $pDetailRS->Acct_Number->setNewVal($vr->getMaskedAccount());
            $pDetailRS->Card_Type->setNewVal($vr->getCardType());
            $pDetailRS->AVS->setNewVal($vr->getAVSResult());
            $pDetailRS->Invoice_Number->setNewVal($vr->getInvoiceNumber());
            $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
            $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
            $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
            $pDetailRS->Code3->setNewVal($vr->getCvvResult());

            $pDetailRS->Updated_By->setNewVal($username);
            $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
            $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::Paid);

            // EMV
            if ($pr->isEMV) {

                $pDetailRS->EMVCardEntryMode->setNewVal($vr->getEMVCardEntryMode());
                $pDetailRS->EMVApplicationIdentifier->setNewVal($vr->getEMVApplicationIdentifier());
                $pDetailRS->EMVApplicationResponseCode->setNewVal($vr->getEMVApplicationResponseCode());
                $pDetailRS->EMVIssuerApplicationData->setNewVal($vr->getEMVIssuerApplicationData());
                $pDetailRS->EMVTerminalVerificationResults->setNewVal($vr->getEMVTerminalVerificationResults());
                $pDetailRS->EMVTransactionStatusInformation->setNewVal($vr->getEMVTransactionStatusInformation());

            }

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
        $payRs->idToken->setNewVal($vr->getIdToken());
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
            $pDetailRS->Status_Message->setNewVal($vr->getResponseMessage());
            $pDetailRS->Reference_Num->setNewVal($vr->getRefNo());
            $pDetailRS->Acct_Number->setNewVal($vr->getMaskedAccount());
            $pDetailRS->Card_Type->setNewVal($vr->getCardType());
            $pDetailRS->AVS->setNewVal($vr->getAVSResult());
            $pDetailRS->Invoice_Number->setNewVal($vr->getInvoiceNumber());
            $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
            $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
            $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
            $pDetailRS->Code3->setNewVal($vr->getCvvResult());

            $pDetailRS->Updated_By->setNewVal($username);
            $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
            $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::Declined);

            // EMV
            if ($pr->isEMV) {

                $pDetailRS->EMVCardEntryMode->setNewVal($vr->getEMVCardEntryMode());
                $pDetailRS->EMVApplicationIdentifier->setNewVal($vr->getEMVApplicationIdentifier());
                $pDetailRS->EMVApplicationResponseCode->setNewVal($vr->getEMVApplicationResponseCode());
                $pDetailRS->EMVIssuerApplicationData->setNewVal($vr->getEMVIssuerApplicationData());
                $pDetailRS->EMVTerminalVerificationResults->setNewVal($vr->getEMVTerminalVerificationResults());
                $pDetailRS->EMVTransactionStatusInformation->setNewVal($vr->getEMVTransactionStatusInformation());

            }

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
        $pDetailRS->Approved_Amount->setNewVal($pr->getAmount());
        $pDetailRS->Approval_Code->setNewVal($vr->getAuthCode());
        $pDetailRS->Reference_Num->setNewVal($vr->getRefNo());
        $pDetailRS->AVS->setNewVal($vr->getAVSResult());
        $pDetailRS->Acct_Number->setNewVal($vr->getMaskedAccount());
        $pDetailRS->Card_Type->setNewVal($vr->getCardType());
        $pDetailRS->Invoice_Number->setNewVal($vr->getInvoiceNumber());
        $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
        $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
        $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
        $pDetailRS->Code3->setNewVal($vr->getCvvResult());

        // EMV
        if ($pr->isEMV) {

            $pDetailRS->EMVCardEntryMode->setNewVal($vr->getEMVCardEntryMode());
            $pDetailRS->EMVApplicationIdentifier->setNewVal($vr->getEMVApplicationIdentifier());
            $pDetailRS->EMVApplicationResponseCode->setNewVal($vr->getEMVApplicationResponseCode());
            $pDetailRS->EMVIssuerApplicationData->setNewVal($vr->getEMVIssuerApplicationData());
            $pDetailRS->EMVTerminalVerificationResults->setNewVal($vr->getEMVTerminalVerificationResults());
            $pDetailRS->EMVTransactionStatusInformation->setNewVal($vr->getEMVTransactionStatusInformation());

        }

        $pDetailRS->Updated_By->setNewVal($username);
        $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::VoidSale);

        $idPaymentAuth = EditRS::insert($dbh, $pDetailRS);

        $pDetailRS->idPayment_auth->setNewVal($idPaymentAuth);
        EditRS::updateStoredVals($pDetailRS);


        return $pr;

    }

    protected static function caseDeclined(\PDO $dbh, PaymentResponse $pr, $username, PaymentRs $payRs = NULL, $attempts = 1) {

        if ($pr->response->getResponseMessage() == 'ITEM VOIDED') {
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
        $pDetailRS->Approved_Amount->setNewVal($vr->getAuthorizedAmount());
        $pDetailRS->Approval_Code->setNewVal($vr->getAuthCode());
        $pDetailRS->Reference_Num->setNewVal($vr->getRefNo());
        $pDetailRS->AVS->setNewVal($vr->getAVSResult());
        $pDetailRS->Acct_Number->setNewVal($vr->getMaskedAccount());
        $pDetailRS->Card_Type->setNewVal($vr->getCardType());
        $pDetailRS->Invoice_Number->setNewVal($vr->getInvoiceNumber());
        $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
        $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
        $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
        $pDetailRS->Code3->setNewVal($vr->getCvvResult());

        // EMV
        if ($pr->isEMV) {

            $pDetailRS->EMVCardEntryMode->setNewVal($vr->getEMVCardEntryMode());
            $pDetailRS->EMVApplicationIdentifier->setNewVal($vr->getEMVApplicationIdentifier());
            $pDetailRS->EMVApplicationResponseCode->setNewVal($vr->getEMVApplicationResponseCode());
            $pDetailRS->EMVIssuerApplicationData->setNewVal($vr->getEMVIssuerApplicationData());
            $pDetailRS->EMVTerminalVerificationResults->setNewVal($vr->getEMVTerminalVerificationResults());
            $pDetailRS->EMVTransactionStatusInformation->setNewVal($vr->getEMVTransactionStatusInformation());

        }

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
            $payRs->idToken->setNewVal($vr->getToken());
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
        $pDetailRS->Acct_Number->setNewVal($vr->getMaskedAccount());
        $pDetailRS->Card_Type->setNewVal($vr->getCardType());
        $pDetailRS->Invoice_Number->setNewVal($vr->getInvoiceNumber());
        $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
        $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
        $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
        $pDetailRS->Code3->setNewVal($vr->getCvvResult());
        $pDetailRS->Updated_By->setNewVal($username);
        $pDetailRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $pDetailRS->Status_Code->setNewVal(PaymentStatusCode::Retrn);

        // EMV
        if ($pr->isEMV) {

            $pDetailRS->EMVCardEntryMode->setNewVal($vr->getEMVCardEntryMode());
            $pDetailRS->EMVApplicationIdentifier->setNewVal($vr->getEMVApplicationIdentifier());
            $pDetailRS->EMVApplicationResponseCode->setNewVal($vr->getEMVApplicationResponseCode());
            $pDetailRS->EMVIssuerApplicationData->setNewVal($vr->getEMVIssuerApplicationData());
            $pDetailRS->EMVTerminalVerificationResults->setNewVal($vr->getEMVTerminalVerificationResults());
            $pDetailRS->EMVTransactionStatusInformation->setNewVal($vr->getEMVTransactionStatusInformation());

        }

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
            $payRs->idToken->setNewVal($vr->getToken());
            $payRs->idTrans->setNewVal($pr->getIdTrans());
            $payRs->idPayment_Method->setNewVal(PaymentMethod::Charge);
            $payRs->Status_Code->setNewVal(PaymentStatusCode::Declined);
            $payRs->Result->setNewVal(MpStatusValues::Declined);
            $payRs->Created_By->setNewVal($username);
            $payRs->Attempt->setNewVal($attempts);
            $payRs->Is_Refund->setNewVal(1);
            $payRs->Amount->setNewVal($vr->getAuthorizedAmount());
            $payRs->Balance->setNewVal($vr->getAuthorizedAmount());
            $payRs->Notes->setNewVal($pr->payNotes);

            $idPmt = EditRS::insert($dbh, $payRs);
            $payRs->idPayment->setNewVal($idPmt);
            EditRS::updateStoredVals($payRs);
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
                $pDetailRS->AVS->setNewVal($vr->getAVSResult());
                $pDetailRS->Invoice_Number->setNewVal($vr->getInvoiceNumber());
                $pDetailRS->idTrans->setNewVal($pr->getIdTrans());
                $pDetailRS->AcqRefData->setNewVal($vr->getAcqRefData());
                $pDetailRS->ProcessData->setNewVal($vr->getProcessData());
                $pDetailRS->Code3->setNewVal($vr->getCvvResult());

                // EMV
                if ($pr->isEMV) {

                    $pDetailRS->EMVCardEntryMode->setNewVal($vr->getEMVCardEntryMode());
                    $pDetailRS->EMVApplicationIdentifier->setNewVal($vr->getEMVApplicationIdentifier());
                    $pDetailRS->EMVApplicationResponseCode->setNewVal($vr->getEMVApplicationResponseCode());
                    $pDetailRS->EMVIssuerApplicationData->setNewVal($vr->getEMVIssuerApplicationData());
                    $pDetailRS->EMVTerminalVerificationResults->setNewVal($vr->getEMVTerminalVerificationResults());
                    $pDetailRS->EMVTransactionStatusInformation->setNewVal($vr->getEMVTransactionStatusInformation());

                }

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
        $pDetailRS->Acct_Number->setNewVal($vr->getMaskedAccount());
        $pDetailRS->Card_Type->setNewVal($vr->getCardType());
        $pDetailRS->Invoice_Number->setNewVal($vr->getInvoiceNumber());
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
