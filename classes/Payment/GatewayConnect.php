<?php
/**
 * Description of GatewayConnect
 *
 * @author Eric
 */


interface iGatewayResponse {

    public function getResponseCode();
    public function getResponseMessage();
    public function getTranType();

    public function getAuthorizedAmount();
    public function getRequestAmount();
    public function getPartialPaymentAmount();
    public function getAuthCode();
    public function getTransPostTime();
    public function getAuthorizationText();
    public function getRefNo();
    public function getAcqRefData();
    public function getProcessData();
    public function getTransactionStatus();

    public function getAVSAddress();
    public function getAVSResult();
    public function getAVSZip();
    public function getCvvResult();

    public function getCardType();
    public function getMaskedAccount();
    public function getCardHolderName();
    public function getExpDate();

    public function getToken();
    public function saveCardonFIle();
    public function getInvoiceNumber();
    public function getOperatorId();
    public function getErrorMessage();

    public function getEMVApplicationIdentifier();
    public function getEMVTerminalVerificationResults();
    public function getEMVIssuerApplicationData();
    public function getEMVTransactionStatusInformation();
    public function getEMVApplicationResponseCode();


}


abstract class GatewayResponse {

    /**
     *
     * @var array
     */
    protected $response;
    protected $errors;

    /**
     *
     * @var array
     */
    protected $result;

    protected $tranType;

    /**
     * The child is expected to define $result.
     *
     * @param array $response
     * @throws Hk_Exception_Payment
     */
    function __construct($response, $tranType = '') {
        if (is_array($response) || is_object($response)) {
            $this->response = $response;
        } else {
            throw new Hk_Exception_Payment('Empty response object. ');
        }

        $this->tranType = $tranType;

        $this->parseResponse();
    }

    // Returns Result
    protected abstract function parseResponse();

    public abstract function getResponseCode();


    public function getResultArray() {
        if (isset($this->result)) {
            return $this->result;
        }
        return array();
    }

    public function getTranType() {
        return $this->tranType;
    }

    public function getErrorMessage() {

        if (isset($this->result['errorMessage'])) {
            return $this->result['errorMessage'];
        }
        return '';
    }

        public function saveCardonFIle() {
            return TRUE;
        }
}

class WebhookResponse extends GatewayResponse implements iGatewayResponse {

    public function parseResponse(){

        if(is_array($this->response)){
            $this->result = $this->response;
        }else{
            throw new Hk_Exception_Payment("Webhook data is invalid.  ");
        }
            return '';
    }

    public function getResponseCode() {
        if (isset($this->result['ResponseCode'])) {
            return $this->result['ResponseCode'];
        }
        return '';
    }

    public function getResponseMessage() {
        if (isset($this->result['CurrentTransactionStatusDescription'])) {
            return $this->result['CurrentTransactionStatusDescription'];
        }
        return '';
    }

    public function getTranType() {
        if ($this->getTransactionAction() == 'Sale' && $this->getTransactionStatus() == InstamedGateway::VOID) {
            return MpTranType::Void;
        } else if ($this->getTransactionAction() == 'Sale') {
            return MpTranType::Sale;
        } else if ($this->getTransactionAction() == 'Refund') {
            return MpTranType::ReturnAmt;
        }
        return '';
    }

    public function getToken() {
        return $this->getPaymentPlanID();
    }

    public function saveCardonFIle() {
        if (isset($this->result['SaveCardOnFile']) && strtolower($this->result['SaveCardOnFile']) === 'true') {
            return TRUE;
        }

        return FALSE;
    }

    public function getSsoToken() {
        if (isset($this->result['SingleSignOnToken'])) {
            return $this->result['SingleSignOnToken'];
        }
        return '';    }

    public function getCardType() {
        if (isset($this->result['CardType'])) {
            return $this->result['CardType'];
        }
        return '';
    }

    public function getMaskedAccount() {
        if (isset($this->result['CardLastFourDigits'])) {
            $last4 = str_replace('*', '', $this->result['CardLastFourDigits']);
            return $last4;
        }
        return '';
    }

    public function getCardHolderName() {
        if (isset($this->result['CardHolderName'])) {
            return $this->result['CardHolderName'];
        }
        return '';
    }

    public function getExpDate() {

        if (isset($this->result['ExpDate'])) {
            return str_replace('/', '', $this->result['ExpDate']);
        }

        return '';
    }

    public function getAuthorizedAmount() {

        if ($this->getPartialPaymentAmount() != '') {
            return $this->getPartialPaymentAmount();
        } else if (isset($this->result['Amount'])) {
            return $this->result['Amount'];
        }

        return '';
    }

    public function getRequestAmount() {
        if (isset($this->result['RequestAmount'])) {
            return $this->result['RequestAmount'];
        }
        return '';
    }

    public function getPartialPaymentAmount() {
        if (isset($this->result['PartialApprovalAmount'])) {
            return $this->result['PartialApprovalAmount'];
        }
        return '';
    }

    public function getAuthCode() {

        if (isset($this->result['AuthorizationCode'])) {
            return $this->result['AuthorizationCode'];
        }
        return '';
    }

    public function getAVSAddress() {
        return '';
    }

    public function getAVSResult() {
        //AddressVerificationResponseCode
        return $this->result['AddressVerificationResponseCode'];
    }

    public function getAVSZip() {
        return '';
    }

    public function getCvvResult() {
        return $this->result['CardVerificationResponseCode'];
    }

    public function getInvoiceNumber() {
        if (isset($this->result['InvoiceNumber'])) {
            return $this->result['InvoiceNumber'];
        }

        return '';
    }

    public function getRefNo() {
        return $this->getPaymentPlanID();
    }
    public function getAcqRefData() {
        return $this->getPrimaryTransactionID();
    }
    public function getProcessData() {
        return '';
    }

    public function getOperatorId() {
        if (isset($this->result['UserID'])) {
            return $this->result['UserID'];
        }
        return '';
    }

    public function getPaymentPlanID() {
        if (isset($this->result['PaymentPlanID'])) {
            return $this->result['PaymentPlanID'];
        }
        return '';
    }

    public function getPrimaryTransactionID() {
        if (isset($this->result['OriginalTransactionID'])) {
            return $this->result['OriginalTransactionID'];
        }
        return '';
    }


    public function getTransactionStatus() {
        if (isset($this->result['CurrentTransactionStatusCode'])) {
            return $this->result['CurrentTransactionStatusCode'];
        }
        return '';
    }

    public function getTransactionAction() {
        if (isset($this->result['TransactionAction'])) {
            return $this->result['TransactionAction'];
        }
        return '';
    }

    public function getTransPostTime() {
        if (isset($this->result['transactionDate'])) {
            return $this->result['transactionDate'];
        }
        return '';
    }

    public function getAuthorizationText() {
        if (isset($this->result['AuthorizationText'])) {
            return $this->result['AuthorizationText'];
        }
        return '';

    }


    public function getEMVApplicationIdentifier() {
        if (isset($this->result['EMVApplicationIdentifier'])) {
            return $this->result['EMVApplicationIdentifier'];
        }
        return '';
    }
    public function getEMVTerminalVerificationResults() {
        if (isset($this->result['EMVTerminalVerificationResults'])) {
            return $this->result['EMVTerminalVerificationResults'];
        }
        return '';
    }
    public function getEMVIssuerApplicationData() {
        if (isset($this->result['EMVIssuerApplicationData'])) {
            return $this->result['EMVIssuerApplicationData'];
        }
        return '';
    }
    public function getEMVTransactionStatusInformation() {
        if (isset($this->result['EMVTransactionStatusInformation'])) {
            return $this->result['EMVTransactionStatusInformation'];
        }
        return '';
    }
    public function getEMVApplicationResponseCode() {
        if (isset($this->result['EMVApplicationResponseCode'])) {
            return $this->result['EMVApplicationResponseCode'];
        }
        return '';
    }

    public function getEMVApplicationName() {
        return '';
    }


    public function getEMVCardHolderVerification() {
        return '';
    }


}

class VerifyCurlResponse extends GatewayResponse implements iGatewayResponse {

    public function parseResponse(){

        if(is_array($this->response)){
            $this->result = $this->response;
        }else{
            throw new Hk_Exception_Payment("Curl transaction response is invalid.  ");
        }
            return '';
    }

    public function getResponseCode() {
        if (isset($this->result['responseCode'])) {
            return $this->result['responseCode'];
        }
        return '';
    }

    public function getResponseMessage() {
        if (isset($this->result['responseMessage'])) {
            return $this->result['responseMessage'];
        }
        return '';
    }

    public function getToken() {
        return $this->getPaymentPlanID();
    }

    public function getCardType() {
        if (isset($this->result['cardBrand'])) {
            return $this->result['cardBrand'];
        }
        return '';
    }

    public function getMaskedAccount() {
        if (isset($this->result['lastFourDigits'])) {
            return $this->result['lastFourDigits'];
        }
        return '';
    }

    public function getCardHolderName() {
        if (isset($this->result['cardHolderName'])) {
            return $this->result['cardHolderName'];
        }
        return '';
    }

    public function getExpDate() {

        if (isset($this->result['cardExpirationMonth']) && isset($this->result['cardExpirationYear'])) {

	    if($this->result['cardExpirationMonth'] < 10){
            	$month = '0' . $this->result['cardExpirationMonth'];
            }else{
	        $month = $this->result['cardExpirationMonth'];
            }

            $year = $this->result['cardExpirationYear'];



            return $month . substr($year, 2);
        }

        return '';
    }

    public function getAuthorizedAmount() {

        if ($this->getPartialPaymentAmount() != '') {
            return $this->getPartialPaymentAmount();
        } else if (isset($this->result['Amount'])) {
            return $this->result['Amount'];
        }

        return '';
    }

    public function getPartialPaymentAmount() {
        if (isset($this->result['partialApprovalAmount'])) {
            return trim($this->result['partialApprovalAmount']);
        }
        return '';
    }

    public function getRequestAmount() {
        if (isset($this->result['Amount'])) {
            return trim($this->result['Amount']);
        }
        return '';
    }

    public function getAuthCode() {

        if (isset($this->result['authorizationNumber'])) {
            return $this->result['authorizationNumber'];
        }
        return '';
    }

    public function getAVSAddress() {
        return '';
    }

    public function getAVSResult() {
        //AddressVerificationResponseCode
        return '';
    }

    public function getAVSZip() {
        return '';
    }

    public function getCvvResult() {
        //CardVerificationResponseCode
        return '';
    }

    public function getInvoiceNumber() {
        if (isset($this->result['InvoiceNumber'])) {
            return $this->result['InvoiceNumber'];
        }

        return '';
    }

    public function getRefNo() {
        return $this->getPaymentPlanID();
    }
    public function getAcqRefData() {
        return $this->getPrimaryTransactionID();
    }
    public function getProcessData() {
        return $this->getTransactionId();
    }

    public function getOperatorId() {
        if (isset($this->result['userID'])) {
            return $this->result['userID'];
        }
        return '';
    }

    public function getPaymentPlanID() {
        if (isset($this->result['paymentPlanID'])) {
            return $this->result['paymentPlanID'];
        }
        return '';
    }

    public function getPrimaryTransactionID() {
        if (isset($this->result['primaryTransactionID'])) {
            return $this->result['primaryTransactionID'];
        }
        return '';
    }

    public function getTransactionId() {
        if (isset($this->result['transactionID'])) {
            return $this->result['transactionID'];
        }
        return '';
    }

    public function getTransactionStatus() {
        if (isset($this->result['primaryTransactionStatus'])) {
            return $this->result['primaryTransactionStatus'];
        } else if (isset($this->result['transactionStatus'])) {
            return $this->result['transactionStatus'];
        }
        return '';
    }

    public function getTransPostTime() {
        if (isset($this->result['transactionDate'])) {
            return $this->result['transactionDate'];
        }
        return '';
    }

    public function getAuthorizationText() {
        if (isset($this->result['authorizationText'])) {
            return $this->result['authorizationText'];
        }
        return '';

    }

    public function IsEMVVerifiedByPIN() {

        if (isset($this->result['IsEMVVerifiedByPIN'])) {
            if ($this->result['IsEMVVerifiedByPIN'] == 'true') {
                return TRUE;
            }
        }
        return FALSE;

    }

    public function getEMVApplicationIdentifier() {
        if (isset($this->result['EMVApplicationIdentifier'])) {
            return $this->result['EMVApplicationIdentifier'];
        }
        return '';
    }
    public function getEMVTerminalVerificationResults() {
        if (isset($this->result['EMVTerminalVerificationResults'])) {
            return $this->result['EMVTerminalVerificationResults'];
        }
        return '';
    }
    public function getEMVIssuerApplicationData() {
        if (isset($this->result['EMVIssuerApplicationData'])) {
            return $this->result['EMVIssuerApplicationData'];
        }
        return '';
    }
    public function getEMVTransactionStatusInformation() {
        if (isset($this->result['EMVTransactionStatusInformation'])) {
            return $this->result['EMVTransactionStatusInformation'];
        }
        return '';
    }
    public function getEMVApplicationResponseCode() {
        if (isset($this->result['EMVApplicationResponseCode'])) {
            return $this->result['EMVApplicationResponseCode'];
        }
        return '';
    }

}

class VerifyCurlVoidResponse extends VerifyCurlResponse {

    public function getResponseMessage() {

        if (isset($this->result['responseMessage'])) {
            return $this->result['responseMessage'];
        }

        $this->getErrorMessage();
    }

    public function getAuthorizedAmount() {
        if (isset($this->result['Amount'])) {
            return $this->result['Amount'];
        }

        return '';
    }

    public function getRequestAmount() {
        if (isset($this->result['Amount'])) {
            return $this->result['Amount'];
        }

        return '';
    }

    public function getErrorMessage() {

        if (isset($this->result['errorMessage'])) {
            return $this->result['errorMessage'];
        }

        return '';
    }

    public function getResponseCode() {

        if (isset($this->result['responseCode'])) {
            return $this->result['responseCode'];
        }

        return '001';  //decline
    }
}

class HeaderResponse extends GatewayResponse {

    protected function parseResponse() {

        //"https://online.instamed.com/providers/Form/SSO/SSOError?respCode=401&respMessage=Invalid AccountID or Password.&lightWeight=true"

        if (isset($this->response[InstamedGateway::RELAY_STATE])) {

            $qs = parse_url($this->response[InstamedGateway::RELAY_STATE], PHP_URL_QUERY);
            parse_str($qs, $this->result);

            $this->result[InstamedGateway::RELAY_STATE] = $this->response[InstamedGateway::RELAY_STATE];

        } else {
            $this->errors = 'response is missing. ';
        }

    }

    public function getRelayState() {
        return $this->result[InstamedGateway::RELAY_STATE];
    }

    public function getToken() {

        if (isset($this->result['token'])) {
            return $this->result['token'];
        }

        return '';
    }

    public function getResponseCode() {

        if (isset($this->result['respCode'])) {

            return intval($this->result['respCode'], 10);
        }

        return 0;
    }

    public function getResponseMessage() {

        if (isset($this->result['respMessage'])) {
            return $this->result['respMessage'];
        }

        return '';
    }
}

class CurlRequest {

    protected $gateWay;

    public function submit($parmStr, $url, $trace = FALSE) {

        if ($url == '') {
            throw new Hk_Exception_Payment('Curl Request is missing the URL.  ');
        }

        $xaction = $this->execute($url, $parmStr);

        try {
            if ($trace) {
                file_put_contents(REL_BASE_DIR . 'patch' . DS . 'soapLog.xml', '; |new__' . $parmStr . '|||' . json_encode($xaction), FILE_APPEND);
            }

        } catch(Exception $ex) {

            throw new Hk_Exception_Payment('Trace file error:  ' . $ex->getMessage());
        }

        return $xaction;
    }

    protected function execute($url, $params) {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url . $params);
        curl_setopt($ch, CURLOPT_USERPWD, "NP.SOFTWARE.TEST:vno9cFqM");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $responseString = curl_exec($ch);
        $msg = curl_error($ch);
        curl_close($ch);

        if ( ! $responseString ) {
            throw new Hk_Exception_Payment('Network (cURL) Error: ' . $msg);
        }

        $transaction = array();
        parse_str($responseString, $transaction);

        return $transaction;
    }

}


abstract class SoapRequest {

    protected $gateWay;

    public function submit(array $req, $url, $trace = FALSE) {

        try {
            // Create the Soap, prepre the data
            $txClient = new SoapClient($url, array('trace'=>$trace));

            $xaction = $this->execute($txClient, $req);

        } catch (SoapFault $sf) {

            throw new Hk_Exception_Payment('Problem with HHK web server contacting the payment gateway:  ' . $sf->getMessage() .     ' (' . $sf->getCode() . '); ' . ' Trace: ' . $sf->getTraceAsString());
        }

        try {
            if ($trace) {
                file_put_contents(REL_BASE_DIR . 'patch' . DS . 'soapLog.xml', $txClient->__getLastRequest() . $txClient->__getLastResponse(), FILE_APPEND);
            }

        } catch(Exception $ex) {

            throw new Hk_Exception_Payment('Trace file error:  ' . $ex->getMessage());
        }

        return $xaction;
    }

    protected abstract function execute(SoapClient $sc, $data);

}

class PollingRequest extends SoapRequest {

    protected function execute(\SoapClient $soapClient, $data) {
        return new PollingResponse($soapClient->GetSSOTokenStatus($data));
    }
}

class DoVoidRequest extends SoapRequest {

    protected function execute(\SoapClient $soapClient, $data) {
        return new DoVoidResponse($soapClient->DoCreditCardSecondaryVoid($data));
    }
}

class DoVoidResponse extends GatewayResponse {

    protected function parseResponse() {

        if (isset($this->response->SecondaryCreditCardVoidRequestData)) {
            $this->result = $this->response->DoCreditCardSecondaryVoidResponse;
        } else {
            throw new Hk_Exception_Payment("DoCreditCardSecondaryVoidResponse is missing from the payment gateway response.  ");
        }
    }

    public function getResponseCode() {

        if (isset($this->result['PrimaryTransactionStatus'])) {
            return $this->result['PrimaryTransactionStatus'];
        }

        return '';
    }
}

class PollingResponse extends GatewayResponse {

    const WAIT = 'NEW';
    const EXPIRED = 'EXPIRED';
    const COMPLETE = 'complete';


    protected function parseResponse() {

        if (isset($this->response->GetSSOTokenStatusResponse)) {
            $this->result = $this->response->GetSSOTokenStatusResponse;
        } else {
            throw new Hk_Exception_Payment("GetSSOTokenStatusResponse is missing from the payment gateway response.  ");
        }
    }

    public function getResponseCode() {

        if (isset($this->result['GetSSOTokenStatusResult'])) {
            return $this->result['GetSSOTokenStatusResult'];
        } else {
            throw new Hk_Exception_Payment("GetSSOTokenStatusResult is missing from the payment gateway response.  ");
        }
    }

    public function isWaiting() {
        if ($this->getResponseCode() == PollingResponse::WAIT) {
            return TRUE;
        }
        return FALSE;
    }

    public function isExpired() {
        if ($this->getResponseCode() == PollingResponse::EXPIRED) {
            return TRUE;
        }
        return FALSE;
    }

    public function isComplete() {
        if ($this->getResponseCode() == PollingResponse::COMPLETE) {
            return TRUE;
        }
        return FALSE;
    }

}

class StandInGwResponse implements iGatewayResponse {

    protected $pAuthRs;
    protected $invoiceNumber;
    protected $requestAmount;
    protected $operatorId;
    protected $cardholderName;
    protected $expDate;
    protected $token;

    public function __construct(Payment_AuthRS $pAuthRs, $operatorId, $cardholderName, $expDate, $token, $invoiceNumber, $amount) {

        $this->pAuthRs = $pAuthRs;

        $this->invoiceNumber = $invoiceNumber;
        $this->requestAmount = $amount;

        $this->operatorId = $operatorId;
        $this->expDate = $expDate;
        $this->cardholderName = $cardholderName;
        $this->token = $token;
    }

    public function getAVSAddress() {
        return 'Not Available';
    }

    public function getAVSResult() {
        return $this->pAuthRs->AVS->getStoredVal();
    }

    public function getAVSZip() {
        return 'Not Available';
    }

    public function getOperatorId() {
        return $this->operatorId;
    }


    public function getAcqRefData() {
        return $this->pAuthRs->AcqRefData->getStoredVal();
    }

    public function getAuthCode() {
        return $this->pAuthRs->Approval_Code->getStoredVal();
    }

    public function getAuthorizationText() {
        return $this->pAuthRs->Response_Message->getStoredVal();
    }

    public function getAuthorizedAmount() {
        return $this->pAuthRs->Approved_Amount->getStoredVal();
    }

    public function getRequestAmount() {
        return $this->requestAmount;
    }

    public function getCardHolderName() {
        return $this->cardholderName;
    }

    public function getCardType() {
        return $this->pAuthRs->Card_Type->getStoredVal();
    }

    public function getCvvResult() {
        return $this->pAuthRs->CVV->getStoredVal();
    }

    public function getExpDate() {
        return $this->expDate;
    }

    public function getInvoiceNumber() {
        return $this->invoiceNumber;
    }

    public function getMaskedAccount() {
        return $this->pAuthRs->Acct_Number->getStoredVal();
    }

    public function getPartialPaymentAmount() {
        return $this->pAuthRs->Approved_Amount->getStoredVal();
    }

    public function getProcessData() {
        return $this->pAuthRs->ProcessData->getStoredVal();
    }

    public function getRefNo() {
        return $this->pAuthRs->Reference_Num->getStoredVal();
    }

    public function getResponseCode() {
        if ($this->pAuthRs->Status_Code->getStoredVal() == PaymentStatusCode::Declined) {
            return '001';
        }

        return '000';
    }

    public function getResponseMessage() {
        return $this->pAuthRs->Status_Message->getStoredVal();
    }

    public function getTransactionStatus() {
        return $this->pAuthRs->Response_Code->getStoredVal();
    }

    public function getToken() {
        return $this->token;
    }

    public function saveCardonFIle() {

        if ($this->getToken() != '') {
            return TRUE;
        }

        return FALSE;
    }

    public function getTranType() {
        return '';
    }

    public function getTransPostTime() {
        return $this->pAuthRs->Timestamp->getStoredVal();
    }

    public function getEMVApplicationIdentifier() {
        return $this->pAuthRs->EMVApplicationIdentifier->getStoredVal();
    }
    public function getEMVTerminalVerificationResults() {
        return $this->pAuthRs->EMVTerminalVerificationResults->getStoredVal();
    }
    public function getEMVIssuerApplicationData() {
        return $this->pAuthRs->EMVIssuerApplicationData->getStoredVal();
    }
    public function getEMVTransactionStatusInformation() {
        return $this->pAuthRs->EMVTransactionStatusInformation->getStoredVal();
    }
    public function getEMVApplicationResponseCode() {
        return $this->pAuthRs->EMVApplicationResponseCode->getStoredVal();
    }

    public function getErrorMessage() {
        return '';
    }

}