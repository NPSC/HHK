<?php
/**
 * LocalResponse.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of LocalResponse
 *
 * @author Eric
 */
class LocalResponse extends CreditResponse {


    function __construct( $gwResp, $idPayor, $idRegistration, $idToken, $paymentStatusCode = '') {

        $this->response = $gwResp;
        $this->idPayor = $idPayor;
        $this->setIdToken($idToken);
        $this->idRegistration = $idRegistration;
        $this->invoiceNumber = $gwResp->getInvoiceNumber();
        $this->amount = $gwResp->getAuthorizedAmount();
        $this->setPaymentStatusCode($paymentStatusCode);

    }

    public function getStatus() {
        return CreditPayments::STATUS_APPROVED;
    }

    public function receiptMarkup(\PDO $dbh, &$tbl) {

        $tbl->addBodyTr(HTMLTable::makeTd("Credit Card:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($this->getAmount(), 2)));
        $tbl->addBodyTr(HTMLTable::makeTd($this->response->getCardType() . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd("xxx...". $this->response->getMaskedAccount()));

        if ($this->response->getCardHolderName() != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Card Holder: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($this->response->getCardHolderName()));
        }

    }

    public function getPaymentMethod() {
        return PaymentMethod::Charge;
    }

}


class LocalGwResp implements iGatewayResponse {

    protected $result;

    protected $tranType;
    protected $merchant = '';
    protected $processor = 'local';
    protected $operatorId;

    public function __construct($amount, $invoiceNumber, $cardType, $cardAcct, $cardHolderName, $tranType, $operatorId) {

        $this->tranType = $tranType;
        $this->operatorId = $operatorId;

        $this->result = array(
            'CardHolderName' => $cardHolderName,
            'AuthorizedAmount' => $amount,
            'Account' => $cardAcct,
            'CardType' => $cardType,
            'Invoice' => $invoiceNumber,
        );
    }

    public function getStatus() {
        return '';
    }

    public function getTranType() {
        return $this->tranType;
    }

    public function getProcessor() {
        return $this->processor;
    }

    public function getMerchant() {
        return $this->merchant;
    }
    public function saveCardOnFile() {
        return TRUE;
    }

    public function getCardHolderName() {
        if (isset($this->result['CardHolderName'])) {
            return $this->result['CardHolderName'];
        }

        return '';
    }

    public function getMaskedAccount() {
        if (isset($this->result['Account'])) {
            return str_ireplace('x', '', $this->result['Account']);
        }

        return '';
    }

    public function getAuthorizedAmount() {
        if (isset($this->result['AuthorizedAmount'])) {
            return $this->result['AuthorizedAmount'];
        }
        return 0.00;
    }

    public function getCardType() {
        if (isset($this->result['CardType'])) {
            return $this->result['CardType'];
        }
        return '';
    }

    public function getInvoiceNumber() {
        if (isset($this->result['Invoice'])) {
            return $this->result['Invoice'];
        }
        return '';
    }

    public function getEMVApplicationIdentifier() {
        return '';
    }
    public function getEMVTerminalVerificationResults() {
        return '';
    }
    public function getEMVIssuerApplicationData() {
        return '';
    }
    public function getEMVTransactionStatusInformation() {
        return '';
    }
    public function getEMVApplicationResponseCode() {
        return '';
    }

    public function SignatureRequired() {
        return 0;
    }

    public function getErrorMessage() {
        return '';
    }

    public function getTransactionStatus() {
        return '';
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

    public function getExpDate() {
        return '';
    }

    public function getOperatorId() {
        return $this->operatorId;
    }

    public function getResponseMessage() {
        return '';
    }

    public function getResponseCode() {
        return '';
    }

    public function getTransPostTime() {
        return '';
    }

    public function getAcqRefData() {
        return '';
    }

    public function getAuthCode() {
        return '';
    }

    public function getRequestAmount() {
        return '';
    }

    public function getAVSResult() {
        return '';
    }

    public function getCvvResult() {
        return '';
    }

    public function getRefNo() {
        return '';
    }

    public function getProcessData() {
       return '';
    }

    public function getToken() {
        return $this->getRandomString();
    }

    protected function getRandomString($length=40){
        if(!is_int($length)||$length<1){
          $length = 40;
        }
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $randstring = '';
        $maxvalue = strlen($chars) - 1;
        for($i=0; $i<$length; $i++){
          $randstring .= substr($chars, rand(0,$maxvalue), 1);
        }
        return $randstring;
  }


}
