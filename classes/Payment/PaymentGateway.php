<?php
/**
 * PaymentGateway.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

Class MpTranType {
    const Sale = 'Sale';
    const PreAuth = 'PreAuth';
    const ReturnAmt = 'ReturnAmount';
    const ReturnSale = 'ReturnSale';
    const Void = 'VoidSale';
    const VoidReturn = 'VoidReturn';
    const Reverse = 'ReverseSale';
    const CardOnFile = 'COF';
}

abstract class PaymentGateway {

    const VANTIV = 'vantiv';
    const INSTAMED = 'instamed';
    const CONVERGE = 'converge';
    const LOCAL = '';

    protected $gwName;
    protected $gwType;
    protected $credentials;
    protected $responseErrors;
    protected $useAVS;
    protected $useCVV;

    public function __construct(\PDO $dbh, $gwType) {

        $this->gwType = $gwType;
        $this->setCredentials($this->loadGateway($dbh));
    }

    public function getGatewayType() {
        return $this->gwType;
    }

    public abstract function getGatewayName();

    /**
     *  Get the gateway information from the database.
     */
    protected abstract function loadGateway(\PDO $dbh);

    /**
     *  Interpret database info into payment gateway credentials object.
     */
    protected abstract function setCredentials($credentials);

    // used to determine if it's a real gateway or out of band, local gateway
    public abstract function getPaymentMethod();

    public function creditSale(\PDO $dbh, $pmp, $invoice, $postbackUrl) {

        // Initialiaze hosted payment
        try {

            $fwrder = $this->initHostedPayment($dbh, $invoice, $postbackUrl, $pmp->getManualKeyEntry());

            $payResult = new PaymentResult($invoice->getIdInvoice(), $invoice->getIdGroup(), $invoice->getSoldToId());
            $payResult->setForwardHostedPayment($fwrder);
            $payResult->setDisplayMessage('Forward to Payment Page. ');
        } catch (Hk_Exception_Payment $hpx) {

            $payResult = new PaymentResult($invoice->getIdInvoice(), 0, 0);
            $payResult->setStatus(PaymentResult::ERROR);
            $payResult->setDisplayMessage($hpx->getMessage());
        }

        return $payResult;
    }

    public function voidSale(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, $paymentNotes, $bid) {

        // Find hte detail record.
        $stmt = $dbh->query("Select * from payment_auth where idPayment = " . $payRs->idPayment->getStoredVal() . " order by idPayment_auth");
        $arows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($arows) < 1) {
            return array('warning' => 'Payment Detail record not found.  Unable to Void this purchase. ', 'bid' => $bid);
        }

        $pAuthRs = new Payment_AuthRS();
        EditRS::loadRow(array_pop($arows), $pAuthRs);

        if ($pAuthRs->Status_Code->getStoredVal() == PaymentStatusCode::Paid) {
            return $this->sendVoid($dbh, $payRs, $pAuthRs, $invoice, $paymentNotes, $bid);
        }

        return array('warning' => 'Payment is ineligable for void.  ', 'bid' => $bid);
    }

    public function voidReturn(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs) {
        return array('warning' => 'Not Available.  ');
    }

    public function returnPayment(\PDO $dbh, PaymentRS $payRs, Invoice $invoice, $bid) {

        // Find hte detail record.
        $stmt = $dbh->query("Select * from payment_auth where idPayment = " . $payRs->idPayment->getStoredVal() . " order by idPayment_auth");
        $arows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($arows) < 1) {
            return array('warning' => 'Payment Detail record not found.  Unable to Return. ', 'bid' => $bid);
        }

        $pAuthRs = new Payment_AuthRS();
        EditRS::loadRow(array_pop($arows), $pAuthRs);

        if ($pAuthRs->Status_Code->getStoredVal() == PaymentStatusCode::Paid) {

            return $this->sendReturn($dbh, $payRs, $pAuthRs, $invoice, $pAuthRs->Approved_Amount->getStoredVal(), $bid);
        }

        return array('warning' => 'This Payment is ineligable for Return. ', 'bid' => $bid);
    }

    public function returnAmount(\PDO $dbh, Invoice $invoice, $rtnToken, $paymentNotes = '') {

        return array('warning' => 'Return Amount is not implemented. ');
    }

    public function reverseSale(\PDO $dbh, PaymentRS $payRs, Invoice $invoice, $bid, $paymentNotes) {

        return $this->voidSale($dbh, $invoice, $payRs, $paymentNotes, $bid);
    }

    public abstract function processHostedReply(\PDO $dbh, $post, $token, $idInv, $payNotes, $userName);

    public function processWebhook(\PDO $dbh, $post, $payNotes, $userName) {
        throw new Hk_Exception_Payment('Webhook not implemeneted');
    }

    public abstract function createEditMarkup(\PDO $dbh);

    public abstract function SaveEditMarkup(\PDO $dbh, $post);

    public static function logGwTx(PDO $dbh, $status, $request, $response, $transType) {

        $gwRs = new Gateway_TransactionRS();

        $gwRs->Vendor_Response->setNewVal($response);
        $gwRs->Vendor_Request->setNewVal($request);
        $gwRs->GwResultCode->setNewVal($status);
        $gwRs->GwTransCode->setNewVal($transType);

        return EditRS::insert($dbh, $gwRs);
    }

    public function updatePayTypes(\PDO $dbh, $username) {

        $msg = '';

        $glRs = new GenLookupsRS();
        $glRs->Table_Name->setStoredVal('Pay_Type');
        $glRs->Code->setStoredVal(PayType::Charge);
        $rows = EditRS::select($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));

        if (count($rows) > 0) {

            $glRs = new GenLookupsRS();
            EditRS::loadRow($rows[0], $glRs);

            $glRs->Substitute->setNewVal($this->getPaymentMethod());

            $ctr = EditRS::update($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));

            if ($ctr > 0) {
                $logText = HouseLog::getUpdateText($glRs);
                HouseLog::logGenLookups($dbh, 'Pay_Type', PayType::Charge, $logText, "update", $username);
                $msg = "Pay_Type Charge is updated.  ";
            }
        }

        return $msg;
    }

    public static function factory(\PDO $dbh, $gwType, $gwName) {

        switch (strtolower($gwType)) {

            case PaymentGateway::VANTIV:

                return new VantivGateway($dbh, $gwName);

            case PaymentGateway::INSTAMED:

                return new InstamedGateway($dbh, $gwName);

            case PaymentGateway::CONVERGE:

                return new ConvergeGateway($dbh, $gwName);

            default:

                return new LocalGateway($dbh, $gwName);
        }
    }

    public function getResponseErrors() {
        return $this->responseErrors;
    }

    public function getCredentials() {
        return $this->credentials;
    }

    public function useAVS() {
        if ($this->credentials->Use_AVS_Flag->getStoredVal() > 0) {
            return TRUE;
        }
        return FALSE;
    }

    public abstract function getPaymentResponseObj(iGatewayResponse $vcr, $idPayor, $idGroup, $invoiceNumber, $idToken = 0, $payNotes = '');

    public abstract function getCofResponseObj(iGatewayResponse $vcr, $idPayor, $idGroup);
}

class LocalGateway extends PaymentGateway {

    public function getPaymentMethod() {
        return PaymentMethod::ChgAsCash;
    }

    public function getGatewayName() {
        return 'local';
    }

    protected function loadGateway(\PDO $dbh) {

    }

    protected function setCredentials($credentials) {

    }

    public function SaveEditMarkup(\PDO $dbh, $post) {

    }

    public function createEditMarkup(\PDO $dbh) {
        return '';
    }

    public function creditSale(\PDO $dbh, $pmp, $invoice, $postbackUrl) {

    }

    public function processHostedReply(\PDO $dbh, $post, $token, $idInv, $payNotes, $userName) {

    }

    public function getPaymentResponseObj(iGatewayResponse $vcr, $idPayor, $idGroup, $invoiceNumber, $idToken = 0, $payNotes = '') {

        return new ManualChargeResponse($vcr->getAuthorizedAmount(), $idPayor, $invoiceNumber, $vcr->getCardType(), $vcr->getMaskedAccount());

    }

    public function getCofResponseObj(iGatewayResponse $vcr, $idPayor, $idGroup) {

    }

    public function processWebhook(\PDO $dbh, $post, $payNotes, $userName) {

    }

}
