<?php
/**
 * MercuryHCClient.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */



/**
* Non Profit Software Corporation HostedCheckout PHP Client
*
* ©2013-2017 Non Profit Software Corporation - all rights reserved.
*
* Disclaimer:
* This software and all specifications and documentation contained
* herein or provided to you hereunder (the "Software") are provided
* free of charge strictly on an "AS IS" basis. No representations or
* warranties are expressed or implied, including, but not limited to,
* warranties of suitability, quality, merchantability, or fitness for a
* particular purpose (irrespective of any course of dealing, custom or
* usage of trade), and all such warranties are expressly and
* specifically disclaimed. on Profit Software Corporation shall have no
* liability or responsibility to you nor any other person or entity
* with respect to any liability, loss, or damage, including lost
* profits whether foreseeable or not, or other obligation for any cause
* whatsoever, caused or alleged to be caused directly or indirectly by
* the Software. Use of the Software signifies agreement with this
* disclaimer notice.
*/

// Mercury helper classes
Class MpStatusValues {

    const Approved = 'Approved';
    const Declined = 'Declined';
    const Error = 'Error';
    const Invalid = 'Invalid';
    const AuthFail = 'AuthFail';
    const MPSError = 'MPSError';
    const Blank = 'Blank';
    const MercInternalFail = 'MercuryInternalFail';
    const ValidateFail = 'ValidateFail';
}

class MpStatusMessage {
    const Approved = 'AP';
    const Replay = 'AP*';
}

Class MpFrequencyValues {
    const OneTime = 'OneTime';
    const Recurring = 'Recurring';
}

Class MpTranType {
    const Sale = 'Sale';
    const PreAuth = 'PreAuth';
    const ReturnAmt = 'Return';
    const Void = 'VoidSale';
    const VoidReturn = 'VoidReturn';
    const Reverse = 'ReverseSale';
    const CardOnFile = 'COF';
}

Class MpTokenTransaction {

}

class MpReturnCodeValues {

    public static function returnCodeToText($returnCode) {
        $rmsg = '';

        switch ($returnCode) {

            case '0':
                $rmsg = 'Success';
                break;

            case '100':
                $rmsg = 'Auth Fail (bad merchant password or bad merchant Id).';
                break;

            case '101':
                $rmsg = 'Card Declined – the card was declined for the transaction. Status=Decline';
                break;

            case '102':
                $rmsg = 'Cancel. The user pressed cancel.';
                break;

            case '103':
                $rmsg = 'Session Timeout';
                break;

            case '104':
                $rmsg = '“Payment processing is temporarily unavailable at this time.';
                break;

            case '200':
                $rmsg = 'Mercury Internal Error.';
                break;

            case '203':
                $rmsg = 'Process Payment Fail – unable to process.';
                break;

            case '204':
                $rmsg = 'PreAuth Fail DBErr– internal database error for PreAuth transaction.';
                break;

            case '205':
                $rmsg = 'Sales Not Completed DBErr – internal database error for Sale transaction.';
                break;

            case '206':
                $rmsg = 'Save CardInfo Fail – A transaction error occurred processing the card info.';
                break;

            case '207':
                $rmsg = 'Load CardInfo Fail – Could not retrieve the card info for the supplied CardID.';
                break;

            case '208':
                $rmsg = 'Process CardInfo Fail – unable to process. CardInfo Status=Error.';
                break;

            case '300':
                $rmsg = 'Validation failure – one of the request parameters was either missing or invalid.';
                break;

            case '301':
                $rmsg = 'Validation CC Fail – Credit Card failed Mod10 check multiple times';
                break;

            case '302':
                $rmsg = 'Validation Server Side Failure – possible tampering suspected';
                break;

            case '303':
                $rmsg = 'Validate Name Fail. Invalid data entered in cardholder name field.';
                break;

            default:
                $rmsg = 'Unknown Return Code: '.$returnCode;
        }
        return $rmsg;
    }

    public static function responseCodeToText($responseCode) {
        $rmsg = '';

        switch ($responseCode) {

            case '0':
                $rmsg = 'Success';
                break;

            case '100':
                $rmsg = 'Auth Fail (bad password or id).';
                break;

            case '200':
                $rmsg = 'Mercury Internal Error.  Specific error will be logged in Mercury’s internal error log.';
                break;

            case '300':
                $rmsg = 'Validation failure – one of the request parameters was either missing or invalid.';
                break;

            default:
                $rmsg = 'Unknown Response Code: '.$responseCode;
        }
        return $rmsg;

    }

}

class MpTokenLifetimeDays {
    const OneTime = '170';
    const Recurring = '600';
}

class MpVersion {
    const PosVersion = 'hhkpos-3.7';
}

class CVVResult {

    const M = '(M) CVV Match.';
    const N = '(N) CVV No Match.';
    const P = '(P) CVV Not Processed.';
    const S = '(S) CVV should be on card but merchant indicated it is not present (Visa/Discover only).';
    const U = '(U) CVV Issuer is Not Certified, CID not checked (AMEX only).';
    const BLANK = 'CVV is Blank.';

    const MATCH = 'm';
    const NO_MATCH = 'nm';
    const NOT_VERIFIED = 'nv';
    const NO_ADVISE = 'na';


    protected $code;
    protected $resultMessage;
    protected $cvvResult;

    public function __construct($cvvCode) {
        $this->code = $cvvCode;
        $this->setCvvResult($cvvCode);
    }

    protected function setCvvResult($code) {

        switch ($code) {
            case 'M':
                $this->resultMessage = self::M;
                $this->cvvResult = self::MATCH;
                break;

            case 'N':
                $this->resultMessage = self::N;
                $this->cvvResult = self::NO_MATCH;
                break;

            case 'P':
                $this->resultMessage = self::P;
                $this->cvvResult = self::NOT_VERIFIED;
                break;

            case 'S':
                $this->resultMessage = self::S;
                $this->cvvResult = self::NO_ADVISE;
                break;

            case 'U':
                $this->resultMessage = self::U;
                $this->cvvResult = self::NO_ADVISE;
                break;

            case '':
                $this->resultMessage = self::BLANK;
                $this->cvvResult = self::NO_ADVISE;
                break;

            default:
                $this->resultMessage = 'CVV Unknown Code: ' . $code;
                $this->cvvResult = self::NO_ADVISE;

        }

    }

    public function isCvvMatch() {
        if ($this->getCvvResult() == self::MATCH) {
            return TRUE;
        }

        return FALSE;
    }

    public function getResultMessage() {
        return $this->resultMessage;
    }

    public function getCvvResult() {
        return $this->cvvResult;
    }

    public function getCvvCode() {
        return $this->code;
    }

}

class AVSResult {

    const RC_A = '(A) Address matches, Zip does not.';
    const RC_B = '(B) Street address match. Postal code not verified: incompatible formats.';
    const RC_C = '(C) Street address and postal code not verified: incompatible formats.';
    const RC_D = '(D) Street Address and postal codes match (International transactions).';
    const RC_G = '(G) Address information not verified for International transaction.';
    const RC_I = '(I) Address information not verified (International transaction).';
    const RC_M = '(M) Street address and postal code match (International transactions).';
    const RC_N = '(N) AVS No Match.';
    const RC_P = '(P) Postal code match. Street address not verified due to incompatible formats.';
    const RC_R = '(R) AVS Retry; System unavailable or timed out.';
    const RC_S = '(S) AVS not supported.';
    const RC_T = '(T) 9-digit ZIP matches, address does not.';
    const RC_U = '(U) AVS No data from issuer/ authorization system.';
    const RC_W = '(W) Nine-digit Zip Code matches, address does not.';
    const RC_X = '(X) 9-digit postal code and address match.';
    const RC_Y = '(Y) Street address and postal code match.';
    const RC_Z = '(Z) 5-digit ZIP code matches, address does not.';
    const BLANK = '() AVS is Blank.';

    const MATCH = 'm';
    const NO_MATCH = 'nm';
    const NOT_VERIFIED = 'nv';
    const NO_ADVISE = 'na';

    protected $code;
    protected $resultMessage;
    protected $zipResult;
    protected $addrResult;


    function __construct($AVScode) {
        $this->code = $AVScode;
        $this->setAVSResult($AVScode);
    }


    protected function setAVSResult($code) {

        $avsResult = $this;

        switch ($code) {
            case 'A':
                $avsResult->resultMessage = self::RC_A;
                $avsResult->addrResult = self::MATCH;
                $avsResult->zipResult = self::NO_MATCH;
                break;

            case 'B':
                $avsResult->resultMessage = self::RC_B;
                $avsResult->addrResult = self::MATCH;
                $avsResult->zipResult = self::NOT_VERIFIED;
                break;

            case 'C':
                $avsResult->resultMessage = self::RC_C;
                $avsResult->addrResult = self::NOT_VERIFIED;
                $avsResult->zipResult = self::NOT_VERIFIED;
                break;

            case 'D':
                $avsResult->resultMessage = self::RC_D;
                $avsResult->addrResult = self::MATCH;
                $avsResult->zipResult = self::MATCH;
                break;

            case 'G':
                $avsResult->resultMessage = self::RC_G;
                $avsResult->addrResult = self::NOT_VERIFIED;
                $avsResult->zipResult = self::NO_ADVISE;
                break;

            case 'I':
                $avsResult->resultMessage = self::RC_I;
                $avsResult->addrResult = self::NOT_VERIFIED;
                $avsResult->zipResult = self::NO_ADVISE;
                break;

            case 'M':
                $avsResult->resultMessage = self::RC_M;
                $avsResult->addrResult = self::MATCH;
                $avsResult->zipResult = self::MATCH;
                break;

            case 'N':
                $avsResult->resultMessage = self::RC_N;
                $avsResult->addrResult = self::NO_MATCH;
                $avsResult->zipResult = self::NO_MATCH;
                break;

            case 'P':
                $avsResult->resultMessage = self::RC_P;
                $avsResult->addrResult = self::NOT_VERIFIED;
                $avsResult->zipResult = self::MATCH;
                break;

            case 'R':
                $avsResult->resultMessage = self::RC_R;
                $avsResult->addrResult = self::NO_ADVISE;
                $avsResult->zipResult = self::NO_ADVISE;
                break;

            case 'S':
                $avsResult->resultMessage = self::RC_S;
                $avsResult->addrResult = self::NO_ADVISE;
                $avsResult->zipResult = self::NO_ADVISE;
                break;

            case 'T':
                $avsResult->resultMessage = self::RC_T;
                $avsResult->addrResult = self::NO_MATCH;
                $avsResult->zipResult = self::MATCH;
                break;

            case 'U':
                $avsResult->resultMessage = self::RC_U;
                $avsResult->addrResult = self::NO_ADVISE;
                $avsResult->zipResult = self::NO_ADVISE;
                break;

            case 'W':
                $avsResult->resultMessage = self::RC_W;
                $avsResult->addrResult = self::NO_MATCH;
                $avsResult->zipResult = self::MATCH;
                break;

            case 'X':
                $avsResult->resultMessage = self::RC_X;
                $avsResult->addrResult = self::MATCH;
                $avsResult->zipResult = self::MATCH;
                break;

            case 'Y':
                $avsResult->resultMessage = self::RC_Y;
                $avsResult->addrResult = self::MATCH;
                $avsResult->zipResult = self::MATCH;
                break;

            case 'Z':
                $avsResult->resultMessage = self::RC_Z;
                $avsResult->addrResult = self::NO_MATCH;
                $avsResult->zipResult = self::MATCH;
                break;

            case '':
                $avsResult->resultMessage = self::BLANK;
                $avsResult->addrResult = self::NO_ADVISE;
                $avsResult->zipResult = self::NO_ADVISE;
                break;

            default:
                $avsResult->resultMessage = 'Unknown Code: ' . $code;
                $avsResult->addrResult = self::NO_ADVISE;
                $avsResult->zipResult = self::NO_ADVISE;

        }

    }

    public function isZipMatch() {
        if ($this->getZipResult() == self::MATCH) {
            return TRUE;
        }

        return FALSE;
    }

    public function isAddrMatch() {
        if ($this->getAddrResult() == self::MATCH) {
            return TRUE;
        }

        return FALSE;

    }

    public function getAVScode() {
        return $this->code;
    }

    public function getResultMessage() {
        return $this->resultMessage;
    }

    public function getZipResult() {
        return $this->zipResult;
    }

    public function getAddrResult() {
        return $this->addrResult;
    }

}

/*
 *  $gateWay array is required by all requests and must contain these Mercury-defined fields:
 *      Merchant_Id
 *      Password
 *      Credit_Url
 *      Trans_Url
 *      CardInfo_Url
 *      Checkout_Url
 *
 */



// Base class Mercury Request and Response objects.
abstract class MercRequest {

    /**
     * Request parameters array
     *
     * @var array
     */
    protected $fields = array();

    /**
     * Gateway array defined in the comment above.
     *
     * @var array
     */
    protected $gateWay = array();

    protected $paymentPageCode;

    protected $title;

    /**
     *
     * @param array $gway
     * @return response object
     * @throws Hk_Exception_Payment
     */
    public function submit(array $gway, $trace = FALSE) {

        $this->setMerchantId($gway['Merchant_Id']);
        $this->gateWay = $gway;

        // Keep the PW out of the object's fields array
        $req = $this->getFieldsArray();
        $req['Password'] = $gway['Password'];

        $data = array("request" => $req);

        try {
            // Create the Soap, prepre the data
            $txClient = new SoapClient($gway['Credit_Url'], array('trace'=>$trace));

            // Each child object must call its own Soap function.  This can be rewritten so that the children objecs
            // set a string function name, but then we have to get into the Soap.
            $xaction = $this->execute($txClient, $data);

        } catch (SoapFault $sf) {

            throw new Hk_Exception_Payment('Problem with HHK web server contacting the Mercury Payment system:  ' . $sf->getMessage() .     ' (' . $sf->getCode() . '); ' . ' Trace: ' . $sf->getTraceAsString());
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


    // Each child must call it's own soap method.
    protected abstract function execute(SoapClient $txClient, array $data);


    protected function setMerchantId($v) {
        $this->fields['MerchantID'] = $v;
        return $this;
    }

    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }

    public function getTitle() {
        return $this->title;
    }

    public function getFieldsArray() {
        return $this->fields;
    }

    public function getPaymentPageCode() {
        return $this->paymentPageCode;
    }

    public function setPaymentPageCode($v) {
        $this->paymentPageCode = $v;
    }
}

abstract class MercResponse {

    /**
     *
     * @var array
     */
    protected $response;

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
    function __construct($response) {
        if (is_array($response) || is_object($response)) {
            $this->response = $response;
        } else {
            throw new Hk_Exception_Payment('Empty response object. ');
        }
    }

    public function getResponseCode() {
        if (isset($this->result->ResponseCode)) {
            return $this->result->ResponseCode;
        }
        return '';
    }

    public function getResponseText() {
        if (isset($this->result->ResponseCode)) {
            return MpReturnCodeValues::responseCodeToText($this->result->ResponseCode);
        }
        return '';
    }


    public function getResultArray() {
        if (isset($this->result)) {
            return $this->result;
        }
        return array();
    }

    public function getTranType() {
        return $this->tranType;
    }


    public function getAuthorizedAmount() {
        return 0;
    }

}



// Card Info Hosted transactions
class InitCiRequest extends MercRequest {

    function __construct($pageTitle = '', $displayStyle = '', $title = '') {

        if ($pageTitle != '') {
            $this->setPageTitle($pageTitle);
        }

        if ($displayStyle != '') {
            $this->setDisplayStyle($displayStyle);
        }

        $this->title = $title;
    }

    protected function execute(\SoapClient $txClient, array $data) {
        if ($this->getPaymentPageCode() == '') {
            throw new Hk_Exception_Payment('Mercury Card Info Page is not set.  ');
        }
        return new InitCiResponse($txClient->InitializeCardInfo($data), $this->gateWay[$this->getPaymentPageCode()]);
    }

    public function setOperatorID($v) {
        $this->fields["OperatorID"] = $v;
        return $this;
    }

    public function setDisplayStyle($v) {
        // Valid values are Mercury or Custom
        if ($v != '' && strtolower($v) != 'mercury') {
            $this->fields["DisplayStyle"] = 'Custom';
        }
        return $this;
    }

    public function setPageTitle($v) {
        if ($v != '') {
            $this->fields["PageTitle"] = $v;
        }
        return $this;
    }

    public function setDefaultSwipe($v) {
        //Valid values = Manual or Swipe
        if ($v != '') {
            $this->fields["DefaultSwipe"] = $v;
        }
        return $this;
    }

    public function setCardEntryMethod($v) {
        if ($v != '') {
            $this->fields["CardEntryMethod"] = $v;
        }
        return $this;
    }

    public function setLogoUrl($v) {
        if ($v != '') {
            $this->fields["LogoUrl"] = $v;
        }
        return $this;
    }

    public function setFrequency($frequency) {
        $this->fields["Frequency"] = $frequency;
        return $this;
    }

    public function setCompleteURL($completeURL) {
        $this->fields["ProcessCompleteUrl"] = $completeURL;
        return $this;
    }

    public function setReturnURL($returnURL) {
        $this->fields["ReturnUrl"] = $returnURL;
        return $this;
    }

    public function setCardHolderName($v) {
        $this->fields["CardHolderName"] = $v;
        return $this;
    }

//    public function setCVV($v) {
//        //Valid values = off or on. Determines whether CVV field is displayed. Default is on.
//        if (strtolower($v) == 'on') {
//            $this->fields["CVV"] = 'on';
//        } else if (strtolower($v) == 'off') {
//            $this->fields["CVV"] = 'off';
//        }
//        return $this;
//    }

}

class VerifyCIRequest extends MercRequest{

    protected function execute(\SoapClient $txClient, array $data) {
        return new VerifyCiResponse($txClient->VerifyCardInfo($data));
    }


    public function setCardId($cardId) {
        $this->fields["CardID"] = $cardId;
        return $this;
    }

}


class InitCiResponse extends MercResponse {

    private $cardInfoURL = '';

    function __construct($response, $cardInfoURL) {
        parent::__construct($response);
        $this->cardInfoURL = $cardInfoURL;

        if (isset($this->response->InitializeCardInfoResult)) {
            $this->result = $this->response->InitializeCardInfoResult;
        } else {
            throw new Hk_Exception_Payment("InitializeCardInfoResult is missing from the payment gateway response.  ");
        }

    }

    public function getMessage() {
        if (isset($this->result->Message)) {
            return $this->result->Message;
        }
        return '';
    }

    public function getCardId() {
        if (isset($this->result->CardID)) {
            return $this->result->CardID;
        }
        return '';
    }

    public function getCardInfoUrl() {
        return $this->cardInfoURL;
    }

}

class VerifyCiResponse extends MercResponse implements iGatewayResponse {

    function __construct($response) {
        parent::__construct($response);

        if (isset($this->response->VerifyCardInfoResult)) {
            $this->result = $this->response->VerifyCardInfoResult;
        }
        else {
            throw new Hk_Exception_Payment("VerifyCardInfoResult is missing from the payment gateway response.  ");
        }

        $this->tranType = MpTranType::CardOnFile;
    }
    public function getAVSAddress() {

    }

    public function getAVSResult() {

    }

    public function getAVSZip() {

    }

    public function getAcqRefData() {

    }

    public function getAuthCode() {

    }

    public function getAuthorizationText() {

    }

    public function getCvvResult() {

    }

    public function getInvoiceNumber() {

    }

    public function getPartialPaymentAmount() {

    }

    public function getProcessData() {

    }

    public function getRefNo() {

    }

    public function getResponseMessage() {

    }

    public function getTransPostTime() {

    }

    public function isEMVTransaction() {

    }

    public function getCardId() {
        if (isset($this->result->CardID)) {
            return $this->result->CardID;
        }
        return '';
    }

    public function getStatus() {
        if (isset($this->result->Status)) {
            return $this->result->Status;
        }
        return '';
    }

    public function getStatusMessage() {
        if (isset($this->result->StatusMessage)) {
            return $this->result->StatusMessage;
        }
        return '';
    }

    public function getDisplayMessage() {
        if (isset($this->result->DisplayMessage)) {
            return $this->result->DisplayMessage;
        }
        return '';
    }

    public function getToken() {
        if (isset($this->result->Token)) {
            return $this->result->Token;
        }
        return '';
    }

    public function getCardType() {
        if (isset($this->result->CardType)) {
            return $this->result->CardType;
        }
        return '';
    }

    public function getMaskedAccount() {
        if (isset($this->result->MaskedAccount)) {
            return $this->result->MaskedAccount;
        }
        return '';
    }

    public function getTranType() {
        if (isset($this->result->TranType)) {
            return $this->result->TranType;
        }
        return '';
    }

    public function getCardUsage() {
        if (isset($this->result->CardUsage)) {
            return $this->result->CardUsage;
        }
        return '';
    }

    public function getCardIDExpired() {
        if (isset($this->result->CardIDExpired)) {
            return $this->result->CardIDExpired;
        }
        return '';
    }

    public function getCardHolderName() {
        if (isset($this->result->CardHolderName)) {
            return $this->result->CardHolderName;
        }
        return '';
    }

    public function getOperatorID() {
        if (isset($this->result->OperatorID)) {
            return $this->result->OperatorID;
        }
        return '';
    }

    public function getExpDate() {
        if (isset($this->result->ExpDate)) {
            return $this->result->ExpDate;
        }
        return '';
    }

    public function getPaymentID() {
        if (isset($this->result->PaymentID)) {
            return $this->result->PaymentID;
        }
        return '';
    }

}



// Credit Payment Hosted transactions
class InitCkOutRequest extends MercRequest {

    function __construct($pageTitle = '', $displayStyle = '', $title = '') {

        if ($pageTitle != '') {
            $this->setPageTitle($pageTitle);
        }

        if ($displayStyle != '') {
            $this->setDisplayStyle($displayStyle);
        }

        $this->title = $title;
    }

    protected function execute(\SoapClient $txClient, array $data) {
        if ($this->getPaymentPageCode() == '') {
            throw new Hk_Exception_Payment('Mercury Payment Page is not set.  ');
        }
        return new InitCkOutResponse($txClient->InitializePayment($data), $this->gateWay[$this->getPaymentPageCode()]);
    }

    public function setFrequency($frequency) {
        $this->fields["Frequency"] = $frequency;
        return $this;
    }

    public function setCompleteURL($completeURL) {
        $this->fields["ProcessCompleteUrl"] = $completeURL;
        return $this;
    }

    public function setReturnURL($returnURL) {
        $this->fields["ReturnUrl"] = $returnURL;
        return $this;
    }

    public function setCardHolderName($v) {
        $this->fields["CardHolderName"] = $v;
        return $this;
    }

    public function setTranType($v) {
        $this->fields["TranType"] = $v;
        return $this;
    }

    public function setTotalAmount($v) {
        $amt = number_format($v, 2, '.', '');
        $this->fields["TotalAmount"] = $amt;
        return $this;
    }

    public function setInvoice($v) {
        $this->fields["Invoice"] = $v;
        return $this;
    }

    public function setMemo($v) {
        $this->fields["Memo"] = $v;
        return $this;
    }

    public function setTaxAmount($v) {
        $amt = number_format($v, 2, '.', '');
        $this->fields["TaxAmount"] = $amt;
        return $this;
    }

    public function setCVV($v) {
        //Valid values = off or on. Determines whether CVV field is displayed. Default is on.
        if (strtolower($v) == 'on') {
            $this->fields["CVV"] = 'on';
        } else if (strtolower($v) == 'off') {
            $this->fields["CVV"] = 'off';
        }
        return $this;
    }

    public function setAVSZip($v) {
        if ($v != '' && is_numeric($v)) {
            $a = substr($v, 0, 9);
            $this->fields["AVSZip"] = $a;
        }
         return $this;
    }

    public function setAVSAddress($v) {
        $this->fields["AVSAddress"] = $v;
        return $this;
    }

    public function setAVSFields($v) {
        // Valid values = Off, Zip, or Both.
        if (strtolower($v) == 'off') {
            $this->fields["AVSFields"] = 'Off';
        } else if (strtolower($v) == 'zip') {
            $this->fields["AVSFields"] = 'Zip';
        } else if (strtolower($v) == 'both') {
            $this->fields["AVSFields"] = 'Both';
        }
        return $this;
    }

    public function setOperatorID($v) {
        $this->fields["OperatorID"] = $v;
        return $this;
    }

    public function setDisplayStyle($v) {
        // Valid values are Mercury or Custom
        if ($v != '' && strtolower($v) != 'mercury') {
            $this->fields["DisplayStyle"] = 'Custom';
        }
        return $this;
    }

    public function setLogoUrl($v) {
        if ($v != '') {
            $this->fields["LogoUrl"] = $v;
        }
        return $this;
    }

    public function setPageTitle($v) {
        if ($v != '') {
            $this->fields["PageTitle"] = $v;
        }
        return $this;
    }

    public function setOrderTotal($v) {
        // Valid values are on and off.
        if ($v === TRUE) {
            $f = 'on';
        } else {
            $f = 'off';
        }
        $this->fields["OrderTotal"] = $f;
        return $this;
    }

    /**
     *
     * @param bool $v
     * @return \InitCkOutRequest
     */
    public function setPartialAuth($v) {
        // Values = on or off
        if ($v === TRUE) {
            $f = 'on';
        } else {
            $f = 'off';
        }
        $this->fields["PartialAuth"] = $f;
        return $this;
    }

    public function setDefaultSwipe($v) {
        //Valid values = Manual or Swipe
        if ($v != '') {
            $this->fields["DefaultSwipe"] = $v;
        }
        return $this;
    }

    public function setCardEntryMethod($v) {
        if ($v != '') {
            $this->fields["CardEntryMethod"] = $v;
        }
        return $this;
    }


}

class VerifyCkOutRequest extends MercRequest{

    protected function execute(\SoapClient $txClient, array $data) {
        return new VerifyCkOutResponse($txClient->VerifyPayment($data));
    }

    public function setPaymentId($paymentId) {
        $this->fields["PaymentID"] = $paymentId;
        return $this;
    }
}


class InitCkOutResponse extends MercResponse {

    private $checkoutURL = '';  //Checkout_Url

    function __construct($response, $checkoutURL) {
        parent::__construct($response);
        $this->checkoutURL = $checkoutURL;

        if (isset($this->response->InitializePaymentResult)) {
            $this->result = $this->response->InitializePaymentResult;
        } else {
            throw new Hk_Exception_Payment("InitializePaymentResult is missing from the payment gateway response.  ");
        }
    }

    public function getMessage() {
        if (isset($this->result->Message)) {
            return $this->result->Message;
        }
        return '';
    }

    public function getPaymentId() {
        if (isset($this->result->PaymentID)) {
            return $this->result->PaymentID;
        }
        return '';
    }

    public function getCheckoutUrl() {
        return $this->checkoutURL;
    }

}

class VerifyCkOutResponse extends MercResponse {

    function __construct($response) {
        parent::__construct($response);

        if (isset($this->response->VerifyPaymentResult)) {
            $this->result = $this->response->VerifyPaymentResult;
        } else {
            throw new Hk_Exception_Payment("VerifyPaymentResult is missing from the payment gateway response.  ");
        }

        $this->tranType = MpTranType::Sale;

    }

    public function getStatus() {
        if (isset($this->result->Status)) {
            return $this->result->Status;
        }
        return '';
    }

    public function getStatusMessage() {
        if (isset($this->result->StatusMessage)) {
            return $this->result->StatusMessage;
        }
        return '';
    }

    public function getMessage() {
        if (isset($this->result->StatusMessage)) {
            return $this->result->StatusMessage;
        }
        return '';
    }

    public function getDisplayMessage() {
        if (isset($this->result->DisplayMessage)) {
            return $this->result->DisplayMessage;
        }
        return '';
    }

    public function getToken() {
        if (isset($this->result->Token)) {
            return $this->result->Token;
        }
        return '';
    }

    public function getCardType() {
        if (isset($this->result->CardType)) {
            return $this->result->CardType;
        }
        return '';
    }

    public function getCardUsage() {
        if (isset($this->result->CardUsage)) {
            return $this->result->CardUsage;
        }
        return '';
    }

    public function getMaskedAccount() {
        if (isset($this->result->MaskedAccount)) {
            return $this->result->MaskedAccount;
        }
        return '';
    }

    public function getTranType() {
        if (isset($this->result->TranType)) {
            return $this->result->TranType;
        }
        return '';
    }

    public function getPaymentIDExpired() {
        if (isset($this->result->PaymentIDExpired)) {
            return $this->result->PaymentIDExpired;
        }
        return '';
    }

    public function getCardHolderName() {
        if (isset($this->result->CardholderName)) {
            return $this->result->CardholderName;
        }
        return '';
    }

    public function getExpDate() {
        if (isset($this->result->ExpDate)) {
            return $this->result->ExpDate;
        }
        return '';
    }

    public function getAcqRefData() {
        if (isset($this->result->AcqRefData)) {
            return $this->result->AcqRefData;
        }
        return '';
    }

    public function getAuthorizeAmount() {
        if (isset($this->result->AuthAmount)) {
            return $this->result->AuthAmount;
        }
        return '';
    }

    public function getAuthCode() {

        if (isset($this->result->AuthCode)) {
            return $this->result->AuthCode;
        }
        return '';
    }

    public function getAVSAddress() {
        // Address used for AVS verification. Note it is truncated to 8 characters.
        if (isset($this->result->AVSAddress)) {
            return $this->result->AVSAddress;
        }
        return '';
    }

    public function getAVSResult() {
        if (isset($this->result->AvsResult)) {
            return $this->result->AvsResult;
        }
        return '';
    }

    public function getAVSZip() {
        // Postal code used for AVS verification
        if (isset($this->result->AVSZip)) {
            return $this->result->AVSZip;
        }
        return '';
    }

    public function getCvvResult() {
        if (isset($this->result->CvvResult)) {
            return $this->result->CvvResult;
        }
        return '';
    }

    public function getInvoice() {
        if (isset($this->result->Invoice)) {
            return $this->result->Invoice;
        }
        return '';
    }

    public function getMemo() {
        if (isset($this->result->Memo)) {
            return $this->result->Memo;
        }
        return '';
    }

    public function getProcessData() {
        if (isset($this->result->ProcessData)) {
            return $this->result->ProcessData;
        }
        return '';
    }

    public function getRefNo() {
        if (isset($this->result->RefNo)) {
            return $this->result->RefNo;
        }
        return '';
    }

    public function getTaxAmount() {
        if (isset($this->result->TaxAmount)) {
            return $this->result->TaxAmount;
        }
        return '';
    }

    public function getAmount() {
        if (isset($this->result->Amount)) {
            return $this->result->Amount;
        }
        return '';
    }

    public function getTransPostTime() {
        if (isset($this->result->TransPostTime)) {
            return $this->result->TransPostTime;
        }
        return '';
    }

    public function getCustomerCode() {
        if (isset($this->result->CustomerCode)) {
            return $this->result->CustomerCode;
        }
        return '';
    }

    public function getOperatorID() {
        if (isset($this->result->OperatorID)) {
            return $this->result->OperatorID;
        }
        return '';
    }


}


// Mercury Token transactions
abstract class MercTokenRequest extends MercRequest {

    protected $tokenId;
    protected $transType;

    /**
     * The password is handled differently for Tokens.
     *
     * @param array $gway
     * @return \CreditResponse
     * @throws Hk_Exception_Payment
     */
    public function submit(array $gway, $trace = FALSE) {

        $this->setMerchantId($gway['Merchant_Id']);
        $data = array("request" => $this->getFieldsArray(), "password" => $gway['Password']);

        try {
            $txClient = new SoapClient($gway['Trans_Url'], array('trace'=>$trace));
            $xaction = $this->execute($txClient, $data);

        } catch (SoapFault $sf) {
            throw new Hk_Exception_Payment('Problem with HHK web server contacting the Mercury Payment system:  ' . $sf->getMessage());
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

    public function setCardHolderName($v) {
        if ($v != '') {
            $a = substr($v, 0, 30);
            $this->fields["CardHolderName"] = $a;
        }
        return $this;
    }

    public function getCardHolderName() {
        return $this->fields["CardHolderName"];
    }

    public function setFrequency($frequency) {
        if ($frequency == 'Recurring' || $frequency == 'OneTime') {
            $this->fields["Frequency"] = $frequency;
        }
        return $this;
    }

    public function setInvoice($v) {
        if ($v != '') {
            $a = substr($v, 0, 16);
            $this->fields["Invoice"] = $a;
        }
        return $this;
    }

    public function setToken($v) {
        if ($v != '') {
            $a = substr($v, 0, 100);
            $this->fields["Token"] = $a;
        }
        return $this;
    }

    public function setTokenId($idToken) {
        $this->tokenId = $idToken;
        return $this;
    }

    public function getTokenId() {
        return $this->tokenId;
    }

    public function setMemo($v) {
        if ($v != '') {
            $a = substr($v, 0, 40);
            $this->fields["Memo"] = $a;
        }
        return $this;
    }

    public function setOperatorID($v) {
        if ($v != '') {
            $a = substr($v, 0, 16);
            $this->fields["OperatorID"] = $a;
        }
        return $this;
    }

    public function setTerminalName($v) {
        if ($v != '') {
            $a = substr($v, 0, 20);
            $this->fields["TerminalName"] = $a;
        }
        return $this;
    }


}

class CreditSaleTokenRequest extends MercTokenRequest {

    protected function execute(\SoapClient $txClient, array $data) {
        return new CreditTokenResponse($txClient->CreditSaleToken($data), 'CreditSaleTokenResult', MpTranType::Sale);
    }

    public function setAddress($v) {
        $this->fields["Address"] = substr($v, 0, 19);
        return $this;
    }

    public function setCVV($v) {
        if ($v != '' && is_numeric($v)) {
            $a = substr($v, 0, 4);
            $this->fields["CVV"] = $a;
        }
        return $this;
    }

    public function setCustomerCode($v) {
        if ($v != '') {
            $a = substr($v, 0, 16);
            $this->fields["CustomerCode"] = $a;
        }
        return $this;
    }

    public function setPurchaseAmount($v) {
        $amt = number_format($v, 2, '.', '');
        $this->fields["PurchaseAmount"] = $amt;
        return $this;
    }

    public function setTaxAmount($v) {
        $amt = number_format($v, 2, '.', '');
        $this->fields["TaxAmount"] = $amt;
        return $this;
    }

    public function setPartialAuth($v) {
        if ($v === TRUE) {
            $f = 'true';
        } else {
            $f = 'false';
        }
        $this->fields["PartialAuth"] = $f;
        return $this;
    }

    public function setZip($v) {
        if ($v != '' && is_numeric($v)) {
            $a = substr($v, 0, 9);
            $this->fields["Zip"] = $a;
        }
        return $this;
    }

}

class CreditVoidSaleTokenRequest extends MercTokenRequest {

    protected function execute(\SoapClient $txClient, array $data) {
        return new CreditTokenResponse($txClient->CreditVoidSaleToken($data), 'CreditVoidSaleTokenResult', MpTranType::Void);
    }

    public function setRefNo($v) {
        if ($v != '') {
            $a = substr($v, 0, 16);
            $this->fields["RefNo"] = $a;
        }
        return $this;
    }

    public function setAuthCode($v) {
        if ($v != '') {
            $a = substr($v, 0, 16);
            $this->fields["AuthCode"] = $a;
        }
        return $this;
    }

    public function setPurchaseAmount($v) {
        $amt = number_format($v, 2, '.', '');
        $this->fields["PurchaseAmount"] = $amt;
        return $this;
    }


}

class CreditReturnTokenRequest extends MercTokenRequest {

    protected function execute(\SoapClient $txClient, array $data) {
        return new CreditTokenResponse($txClient->CreditReturnToken($data), 'CreditReturnTokenResult', MpTranType::ReturnAmt);
    }

    public function setPurchaseAmount($v) {
        $amt = number_format($v, 2, '.', '');
        $this->fields["PurchaseAmount"] = $amt;
        return $this;
    }


}

class CreditVoidReturnTokenRequest extends MercTokenRequest {

    protected function execute(\SoapClient $txClient, array $data) {
        return new CreditTokenResponse($txClient->CreditVoidReturnToken($data), 'CreditVoidReturnTokenResult', MpTranType::VoidReturn);
    }

    public function setPurchaseAmount($v) {
        $amt = number_format($v, 2, '.', '');
        $this->fields["PurchaseAmount"] = $amt;
        return $this;
    }

    public function setRefNo($v) {
        if ($v != '') {
            $a = substr($v, 0, 16);
            $this->fields["RefNo"] = $a;
        }
        return $this;
    }

    public function setAuthCode($v) {
        if ($v != '') {
            $a = substr($v, 0, 16);
            $this->fields["AuthCode"] = $a;
        }
        return $this;
    }

}

class CreditReversalTokenRequest extends MercTokenRequest {

    protected function execute(\SoapClient $txClient, array $data) {
        return new CreditTokenResponse($txClient->CreditReversalToken($data), 'CreditReversalTokenResult', MpTranType::Reverse);
    }

    public function setPurchaseAmount($v) {
        $amt = number_format($v, 2, '.', '');
        $this->fields["PurchaseAmount"] = $amt;
        return $this;
    }

    public function setRefNo($v) {
        if ($v != '') {
            $a = substr($v, 0, 16);
            $this->fields["RefNo"] = $a;
        }
        return $this;
    }

    public function setAuthCode($v) {
        if ($v != '') {
            $a = substr($v, 0, 16);
            $this->fields["AuthCode"] = $a;
        }
        return $this;
    }

    public function setAcqRefData($v) {
        if ($v != '') {
            $a = substr($v, 0, 200);
            $this->fields["AcqRefData"] = $a;
        }
        return $this;
    }

    public function setProcessData($v) {
        if ($v != '') {
            $a = substr($v, 0, 200);
            $this->fields["ProcessData"] = $a;
        }
        return $this;
    }

}

class CreditAdjustTokenRequest extends MercTokenRequest {

    protected function execute(\SoapClient $txClient, array $data) {
        return new CreditTokenResponse($txClient->CreditAdjustToken($data), 'CreditAdjustTokenResult');
    }

    public function setPurchaseAmount($v) {
        $amt = number_format($v, 2, '.', '');
        $this->fields["PurchaseAmount"] = $amt;
        return $this;
    }

    public function setGratuityAmount($v) {
        $amt = number_format($v, 2, '.', '');
        $this->fields["GratuityAmount"] = $amt;
        return $this;
    }

    public function setRefNo($v) {
        if ($v != '') {
            $a = substr($v, 0, 16);
            $this->fields["RefNo"] = $a;
        }
        return $this;
    }

    public function setAuthCode($v) {
        if ($v != '') {
            $a = substr($v, 0, 16);
            $this->fields["AuthCode"] = $a;
        }
        return $this;
    }

}


class CreditTokenResponse extends MercResponse implements iGatewayResponse {

    protected $tranType;

    /**
     *
     * @param StdObj $response
     * @throws Hk_Exception_Payment
     */
    function __construct($response, $resultName, $tranType = '') {
        parent::__construct($response);

        $this->tranType = $tranType;

        foreach ($this->response as $k => $v) {
            if ($k == $resultName) {
                $this->result = $v;
            }
        }

        if (is_null($this->result)) {
            throw new Hk_Exception_Payment($resultName . ' is missing from the payment gateway response. ');
        }
    }

    public function getPartialPaymentAmount() {
        return 0;
    }

    public function getAuthorizationText() {
        return '';
    }

    public function getAVSAddress() {
        return '';
    }

    public function getAVSZip() {
        return '';
    }

    public function getCardHolderName() {
        return '';
    }

    public function getExpDate() {
        return '';
    }

    public function getOperatorId() {
        return '';
    }

    public function getResponseMessage() {
        return $this->getMessage();
    }

    public function getTransPostTime() {
        return '';
    }

    public function getMaskedAccount() {
        if (isset($this->result->Account)) {
            return $this->result->Account;
        } else if (isset($this->result->MaskedAccount)) {
            return $this->result->MaskedAccount;
        }

        return '';
    }

    public function getAcqRefData() {
        if (isset($this->result->AcqRefData)) {
            return $this->result->AcqRefData;
        }
        return '';
    }

    public function getAuthCode() {
        if (isset($this->result->AuthCode)) {
            return $this->result->AuthCode;
        }
        return '';
    }

    public function getAuthorizedAmount() {
        if (isset($this->result->AuthorizeAmount)) {
            return $this->result->AuthorizeAmount;
        } else if (isset($this->result->Amount)) {
            return $this->result->Amount;
        }
        return 0.00;
    }


    public function getAVSResult() {
        if (isset($this->result->AVSResult)) {
            return $this->result->AVSResult;
        }
        return '';
    }

    public function getBatchNo() {
        if (isset($this->result->BatchNo)) {
            return $this->result->BatchNo;
        }
        return '';
    }

    public function getCardType() {
        if (isset($this->result->CardType)) {
            return $this->result->CardType;
        }
        return '';
    }

    public function getCvvResult() {
        if (isset($this->result->CVVResult)) {
            return $this->result->CVVResult;
        }
        return '';
    }

    public function getGratuityAmount() {
        if (isset($this->result->GratuityAmount)) {
            return $this->result->GratuityAmount;
        }
        return 0.00;
    }

    public function getInvoiceNumber() {
        if (isset($this->result->Invoice)) {
            return $this->result->Invoice;
        }
        return '';
    }

    public function getPurchaseAmount() {
        if (isset($this->result->PurchaseAmount)) {
            return $this->result->PurchaseAmount;
        }
        return 0.00;
    }

    public function getRefNo() {
        if (isset($this->result->RefNo)) {
            return $this->result->RefNo;
        }
        return '';
    }

    public function getProcessData() {
         if (isset($this->result->ProcessData)) {
            return $this->result->ProcessData;
        }
       return '';
    }

//    public function getStatus() {
//        if (isset($this->result->Status)) {
//            return $this->result->Status;
//        }
//        return '';
//    }

    public function getMessage() {
        if (isset($this->result->Message)) {
            return $this->result->Message;
        }
        return '';
    }

    public function getStatusMessage() {
        if (isset($this->result->Message)) {
            return $this->result->Message;
        }
        return '';
    }

    public function getDisplayMessage() {
        return $this->getStatus();
    }

    public function getToken() {
        if (isset($this->result->Token)) {
            return $this->result->Token;
        }
        return '';
    }

    public function isEMVTransaction() {
        return FALSE;
    }


}


