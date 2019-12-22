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
    const ZeroAuth = 'ZeroAuth';
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

    protected $gwType;
    protected $credentials;
    protected $responseErrors;
    protected $useAVS;
    protected $useCVV;

    public function __construct(\PDO $dbh, $gwType) {

        $this->gwType = $gwType;
        $this->setCredentials($this->loadGateway($dbh));
    }

    /**
     *  Interpret database info into payment gateway credentials object.
     */
    protected abstract function setCredentials($credentials);

    /**
     *  Get the gateway information from the database.
     */
    protected abstract function loadGateway(\PDO $dbh);

    public abstract function getPaymentResponseObj(iGatewayResponse $vcr, $idPayor, $idGroup, $invoiceNumber, $idToken = 0, $payNotes = '');

    public abstract function getCofResponseObj(iGatewayResponse $vcr, $idPayor, $idGroup);

    public abstract function processHostedReply(\PDO $dbh, $post, $ssoToken, $idInv, $payNotes);

    public abstract function selectPaymentMarkup(\PDO $dbh, &$payTable);

    public function creditSale(\PDO $dbh, $pmp, $invoice, $postbackUrl) {
        return array('warning' => 'Credit Sale is not implemented. ');
    }

    public function voidSale(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $bid) {

        if ($pAuthRs->Status_Code->getStoredVal() == PaymentStatusCode::Paid) {
            return $this->_voidSale($dbh, $payRs, $pAuthRs, $invoice, $bid);
        }

        return array('warning' => 'Payment is ineligable for void.  ', 'bid' => $bid);
    }

    protected function _voidSale(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $bid) {
        return array('warning' => '_voidSale is not implemented. ');
    }

    public function returnPayment(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $bid) {

        if ($pAuthRs->Status_Code->getStoredVal() == PaymentStatusCode::Paid && $pAuthRs->Status_Code->getStoredVal() != PaymentStatusCode::VoidReturn) {
            return $this->_returnPayment($dbh, $invoice, $payRs, $pAuthRs, $pAuthRs->Approved_Amount->getStoredVal(), $bid);
        }

        return array('warning' => 'This Payment is ineligable for Return. ', 'bid' => $bid);
    }

    protected function _returnPayment(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $retAmount, $bid) {
        return array('warning' => '_returnPayment is not implemented. ');
    }

    public function voidReturn(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $bid) {
        return array('warning' => 'Not Available.  ');
    }

    public function returnAmount(\PDO $dbh, Invoice $invoice, $rtnToken, $paymentNotes = '') {
        return array('warning' => 'Return Amount is not implemented. ');
    }

    public function reverseSale(\PDO $dbh, Invoice $invoice, PaymentRS $payRs, Payment_AuthRS $pAuthRs, $bid) {
        return $this->voidSale($dbh, $invoice, $payRs, $pAuthRs, $bid);
    }

    public function processWebhook(\PDO $dbh, $post, $payNotes, $userName) {
        throw new Hk_Exception_Payment('Webhook not implemeneted');
    }

    public static function createEditMarkup(\PDO $dbh, $gatewayName) {

        switch (strtolower($gatewayName)) {

            case PaymentGateway::VANTIV:

                return VantivGateway::_createEditMarkup($dbh, $gatewayName);

            case PaymentGateway::INSTAMED:

                return InstamedGateway::_createEditMarkup($dbh, $gatewayName);

            case PaymentGateway::CONVERGE:

                return ConvergeGateway::_createEditMarkup($dbh, $gatewayName);

            default:

                return LocalGateway::_createEditMarkup($dbh, $gatewayName);
        }
    }

    public static function saveEditMarkup(\PDO $dbh, $gatewayName, $post) {

        switch (strtolower($gatewayName)) {

            case PaymentGateway::VANTIV:

                return VantivGateway::_saveEditMarkup($dbh, $gatewayName, $post);

            case PaymentGateway::INSTAMED:

                return InstamedGateway::_saveEditMarkup($dbh, $gatewayName, $post);

            case PaymentGateway::CONVERGE:

                return ConvergeGateway::_saveEditMarkup($dbh, $gatewayName, $post);

            default:

                return LocalGateway::_saveEditMarkup($dbh, $gatewayName, $post);
        }
    }

    public static function logGwTx(PDO $dbh, $status, $request, $response, $transType) {

        $gwRs = new Gateway_TransactionRS();

        $gwRs->Vendor_Response->setNewVal($response);
        $gwRs->Vendor_Request->setNewVal($request);
        $gwRs->GwResultCode->setNewVal($status);
        $gwRs->GwTransCode->setNewVal($transType);

        return EditRS::insert($dbh, $gwRs);
    }

    public static function updatePayTypes(\PDO $dbh, $paymentMethod, $username) {

        $msg = '';

        $glRs = new GenLookupsRS();
        $glRs->Table_Name->setStoredVal('Pay_Type');
        $glRs->Code->setStoredVal(PayType::Charge);
        $rows = EditRS::select($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));

        if (count($rows) > 0) {

            $glRs = new GenLookupsRS();
            EditRS::loadRow($rows[0], $glRs);

            $glRs->Substitute->setNewVal($paymentMethod);

            $ctr = EditRS::update($dbh, $glRs, array($glRs->Table_Name, $glRs->Code));

            if ($ctr > 0) {
                $logText = HouseLog::getUpdateText($glRs);
                HouseLog::logGenLookups($dbh, 'Pay_Type', PayType::Charge, $logText, "update", $username);
                $msg = "Pay_Type Charge is updated.  ";
            }
        }

        return $msg;
    }

    public static function factory(\PDO $dbh, $gwName, $gwType) {

        switch (strtolower($gwName)) {

            case PaymentGateway::VANTIV:

                return new VantivGateway($dbh, $gwType);

            case PaymentGateway::INSTAMED:

                return new InstamedGateway($dbh, $gwType);

            case PaymentGateway::CONVERGE:

                return new ConvergeGateway($dbh, $gwType);

            default:

                return new LocalGateway($dbh, $gwType);
        }
    }

    public static function getCreditGatewayNames(\PDO $dbh, $idVisit, $span) {

        $ccNames = array();

        $volStmt = $dbh->prepare("call get_credit_gw(:idVisit, :span);", array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $volStmt->execute(array(':idVisit'=>intval($idVisit), ':span'=>intval($span)));
        $rows = $volStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {

            foreach ($rows as $r) {
                $ccNames[$r['idLocation']] = $r['Merchant'];
            }
        }

        return $ccNames;
    }

    // used to determine if it's a real gateway or out of band, local gateway
    public static function getPaymentMethod() {
        throw new Hk_Exception_Payment('Payment Method not defined.  ');
    }

    public abstract function getGatewayName();

    public function getMerchant() {
        return $this->getGatewayType();
    }

    public function getGatewayType() {

        $myType = '';

        if (is_array($this->gwType) && count($this->gwType) == 1) {
            $myType = strtolower(array_values($this->gwType)[0]);
        } else if (is_array($this->gwType) === FALSE) {
            $myType = $this->gwType;
        }

        return $myType;
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

    protected function getInfoFromCardId(\PDO $dbh, $cardId) {

        $infoArray = array();

        $query = "select `idName`, `idGroup`, `InvoiceNumber`, `Amount`, `CardID`, `Merchant` from `card_id` where `CardID` = :cid";
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':cid'=>$cardId));

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) > 0) {

            $infoArray = $rows[0];

            // Delete to discourge replays.
            $stmt = $dbh->prepare("delete from card_id where CardID = :cid");
            $stmt->execute(array(':cid'=>$cardId));

        }

        return $infoArray;
    }

}

