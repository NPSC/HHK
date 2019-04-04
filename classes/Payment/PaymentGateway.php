<?php

/**
 * Description of PaymentGateway
 *
 * @author Eric
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

    protected abstract function getGatewayName();

    /**
     *  Get the gateway information from the database.
     */
    protected abstract function loadGateway(\PDO $dbh);

    /**
     *  Interpret database info into payment gateway credentials object.
     */
    protected abstract function setCredentials($credentials);

    // used to determine if it's a real gateway or out of band, local gateway
    protected abstract function getPaymentMethod();

    public abstract function creditSale(\PDO $dbh, $pmp, $invoice, $postbackUrl);

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

    protected function getPaymentMethod() {
        return PaymentMethod::ChgAsCash;
    }

    protected function getGatewayName() {
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
