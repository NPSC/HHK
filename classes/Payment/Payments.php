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

    public abstract function getStatus();

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


class ImSaleResponse extends PaymentResponse {

    public $response;
    public $idToken = '';

    function __construct(VerifyCurlResponse $verifyCurlResponse, $idPayor, $idGroup, $invoiceNumber, $payNotes) {
        $this->response = $verifyCurlResponse;
        $this->paymentType = PayType::Charge;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->invoiceNumber = $invoiceNumber;
        $this->expDate = $verifyCurlResponse->getExpDate();
        $this->cardNum = $verifyCurlResponse->getMaskedAccount();
        $this->cardType = $verifyCurlResponse->getCardType();
        $this->cardName = $verifyCurlResponse->getCardHolderName();
        $this->amount = $verifyCurlResponse->getAuthorizeAmount();
        $this->payNotes = $payNotes;
    }

    public function getStatus() {

        switch ($this->response->getStatus()) {

            case '000':
                $status = CreditPayments::STATUS_APPROVED;
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
                $status = CreditPayments::STATUS_DECLINED;
        }

        return $status;
    }

    public function receiptMarkup(\PDO $dbh, &$tbl) {

        $tbl->addBodyTr(HTMLTable::makeTd("Credit Card:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($this->getAmount(), 2)));
        $tbl->addBodyTr(HTMLTable::makeTd($this->cardType . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd("xxxxx...". $this->cardNum));

        if ($this->cardName != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Card Holder: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($this->cardName));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Sign: ", array('class'=>'tdlabel')) . HTMLTable::makeTd('', array('style'=>'height:35px; width:250px; border: solid 1px gray;')));

    }

}

class ImVoidResponse extends PaymentResponse {

    public $response;
    public $idToken = '';

    function __construct(VerifyVoidResponse $verifyVoidResp, $idPayor, $idGroup, $invoiceNumber, $payNotes) {
        $this->response = $verifyVoidResp;
        $this->paymentType = PayType::Charge;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->invoiceNumber = $invoiceNumber;
        $this->amount = $verifyVoidResp->getAuthorizeAmount();
        $this->payNotes = $payNotes;
    }

    public function getStatus() {

        switch ($this->response->getStatus()) {

            case '000':
                $status = CreditPayments::STATUS_APPROVED;
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
                $status = CreditPayments::STATUS_DECLINED;
        }

        return $status;
    }

    public function receiptMarkup(\PDO $dbh, &$tbl) {

        $tbl->addBodyTr(HTMLTable::makeTd("Credit Card:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($this->getAmount(), 2)));
        $tbl->addBodyTr(HTMLTable::makeTd($this->cardType . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd("xxxxx...". $this->cardNum));

        if ($this->cardName != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Card Holder: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($this->cardName));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Sign: ", array('class'=>'tdlabel')) . HTMLTable::makeTd('', array('style'=>'height:35px; width:250px; border: solid 1px gray;')));

    }

}

class ImReturnResponse extends PaymentResponse {

    public $response;
    public $idToken = '';
//IsEMVVerifiedByPIN=false
//isEMVTransaction=FALSE
//EMVCardEntryMode=KEYED
//cardBrand=DISCOVER
//cardExpirationMonth=12
//cardExpirationYear=2019
//cardBINNumber=601100
//cardHolderName=JOE MONTANA
//paymentCardType=CREDIT
//lastFourDigits=9424
//authorizationNumber=9A5DDA
//responseCode=000
//responseMessage=APPROVAL
//transactionStatus=C
//primaryTransactionID=D5E2BF0800AB41CEAC2D8CBF406E800E
//authorizationText=THE ABOVE AMOUNT HAS BEEN REFUNDED.
//transactionID=96A66FDA25964C8F9C0AEB0EF8123AAF
//transactionDate=2016-04-27T19:17:05.2770143Z
//saveOnFileTransactionID=2FA23B1C2C8C4E0D951C24EB300DCCFB

    function __construct(VerifyReturnResponse $verifyReturnResp, $idPayor, $idGroup, $invoiceNumber, $payNotes) {
        $this->response = $verifyReturnResp;
        $this->paymentType = PayType::Charge;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->invoiceNumber = $invoiceNumber;
        $this->amount = $verifyReturnResp->getAuthorizeAmount();
        $this->payNotes = $payNotes;
    }

    public function getStatus() {

        switch ($this->response->getStatus()) {

            case '000':
                $status = CreditPayments::STATUS_APPROVED;
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
                $status = CreditPayments::STATUS_DECLINED;
        }

        return $status;
    }

    public function receiptMarkup(\PDO $dbh, &$tbl) {

        $tbl->addBodyTr(HTMLTable::makeTd("Credit Card:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($this->getAmount(), 2)));
        $tbl->addBodyTr(HTMLTable::makeTd($this->cardType . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd("xxxxx...". $this->cardNum));

        if ($this->cardName != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Card Holder: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($this->cardName));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Sign: ", array('class'=>'tdlabel')) . HTMLTable::makeTd('', array('style'=>'height:35px; width:250px; border: solid 1px gray;')));

    }

}

class ImCofResponse extends PaymentResponse {


    function __construct($verifyCurlResponse, $idPayor, $idGroup) {
        $this->response = $verifyCurlResponse;
        $this->idPayor = $idPayor;
        $this->idRegistration = $idGroup;
        $this->expDate = $verifyCurlResponse->getExpDate();
        $this->cardNum = str_ireplace('x', '', $verifyCurlResponse->getMaskedAccount());
        $this->cardType = $verifyCurlResponse->getCardType();
        $this->cardName = $verifyCurlResponse->getCardHolderName();
    }

    public function getStatus() {

        switch ($this->response->getStatus()) {

            case '000':
                $status = CreditPayments::STATUS_APPROVED;
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
                $status = CreditPayments::STATUS_DECLINED;

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

