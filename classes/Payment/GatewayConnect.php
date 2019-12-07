<?php
/**
 * GatewayConnect.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


interface iGatewayResponse {

    public function getResponseCode();
    public function getResponseMessage();
    public function getTranType();
    public function getCcGateway();
    public function getProcessor();

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
    public function SignatureRequired();

    public function getToken();
    public function saveCardonFile();
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
    protected $processor;
    protected $ccGateway;

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

    public function getProcessor() {
        return $this->processor;
    }

    public function getCcGateway() {
        return $this->ccGateway;
    }

    public function getErrorMessage() {

        if (isset($this->result['errorMessage'])) {
            return $this->result['errorMessage'];
        }
        return '';
    }

    public function saveCardonFile() {
        return TRUE;
    }
}


abstract class CurlRequest {

    public function submit($parmStr, $url, $accountId, $password, $trace = FALSE) {

        if ($url == '') {
            throw new Hk_Exception_Payment('Curl Request is missing the URL.  ');
        }

        $xaction = $this->execute($url, $parmStr, $accountId, $password);

        try {
            if ($trace) {
                file_put_contents(REL_BASE_DIR . 'patch' . DS . 'soapLog.xml', '; |new__' . $parmStr . '|||' . json_encode($xaction), FILE_APPEND);
            }

        } catch(Exception $ex) {

            throw new Hk_Exception_Payment('Trace file error:  ' . $ex->getMessage());
        }

        return $xaction;
    }

    protected abstract function execute($url, $params, $accountId, $password);

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


class StandInGwResponse implements iGatewayResponse {

    protected $pAuthRs;
    protected $invoiceNumber;
    protected $requestAmount;
    protected $operatorId;
    protected $cardholderName;
    protected $expDate;
    protected $token;
    protected $processor;
    protected $ccGateway;

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

        if ($this->cardholderName == '') {
            return htmlentities($this->pAuthRs->Cardholder_Name->getStoredVal(), ENT_QUOTES);
        }

        return htmlentities($this->cardholderName, ENT_QUOTES);
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

    public function SignatureRequired() {
        return $this->pAuthRs->Signature_Required->getStoredVal();
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

    public function isSignatureRequired() {
        if ($this->SignatureRequired() == 1) {
            return TRUE;
        }
        return FALSE;
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

    public function saveCardonFile() {

        if ($this->getToken() != '') {
            return TRUE;
        }

        return FALSE;
    }

    public function getTranType() {
        return '';
    }

    public function getProcessor() {
        return $this->processor;
    }

    public function getCcGateway() {
        return $this->ccGateway;
    }

    public function setProcessor($v) {
        $this->processor = $v;
    }

    public function setCcGateway($v) {
        $this->ccGateway = $v;
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